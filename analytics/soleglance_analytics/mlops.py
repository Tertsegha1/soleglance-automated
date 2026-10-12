"""Model operations: feedback capture, gated retraining, versioning, drift monitoring.

Feedback
    Corrections from moderators/users are appended to models/feedback/feedback.jsonl
    (text, task, label, language, source). Text is needed for retraining, so the
    platform only sends feedback on public posts and on text users chose to analyse.

Retraining (python -m soleglance_analytics.mlops retrain sentiment_tfidf)
    1. Train a candidate on the original training data + all feedback so far.
    2. Evaluate the candidate and the active model on the frozen validation
       sets of every language, and on the most recent 20% of feedback.
    3. Promote only if no language's validation macro-F1 drops by more than
       GATE_TOLERANCE and feedback macro-F1 does not drop. Otherwise the
       candidate is kept in the registry as "rejected" with its metrics.
    Every version is a separate file; models/registry.json records which is
    active, so a rollback is a one-line change (mlops rollback <name> <version>).

Drift
    The service counts predicted languages and confidence deciles. PSI against
    the reference distribution (from validation data) is reported; PSI > 0.2 is
    the conventional "significant shift" threshold.
"""

from __future__ import annotations

import json
import shutil
import sys
import threading
import time
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable

import numpy as np

GATE_TOLERANCE = 0.01  # informed by research/evaluate_gate_tolerance.py
PSI_ALERT = 0.2
TASKS = ("sentiment", "health_mention", "misinformation")
MANAGED = {
    # registry name -> (artefact file, task)
    "sentiment_tfidf": ("sentiment_tfidf.joblib", "sentiment"),
    "misinformation": ("misinformation.joblib", "misinformation"),
}


def _now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


# --- Feedback -----------------------------------------------------------------------------

def feedback_path(model_dir: Path) -> Path:
    return model_dir / "feedback" / "feedback.jsonl"


_lock = threading.Lock()


def append_feedback(model_dir: Path, items: Iterable[dict]) -> int:
    path = feedback_path(model_dir)
    path.parent.mkdir(parents=True, exist_ok=True)
    n = 0
    with _lock, path.open("a", encoding="utf-8") as fh:
        for item in items:
            if item.get("task") not in TASKS or not item.get("text") or not item.get("label"):
                continue
            fh.write(json.dumps({**item, "received_at": _now()}, ensure_ascii=False) + "\n")
            n += 1
    return n


def load_feedback(model_dir: Path, task: str) -> list[dict]:
    path = feedback_path(model_dir)
    if not path.exists():
        return []
    rows = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
    # Latest correction per text wins.
    latest: dict[str, dict] = {}
    for r in rows:
        if r["task"] == task:
            latest[r["text"]] = r
    return list(latest.values())


# --- Registry -------------------------------------------------------------------------------

def registry_path(model_dir: Path) -> Path:
    return model_dir / "registry.json"


def load_registry(model_dir: Path) -> dict:
    path = registry_path(model_dir)
    if path.exists():
        return json.loads(path.read_text(encoding="utf-8"))
    reg = {}
    for name, (artefact, _) in MANAGED.items():
        if (model_dir / artefact).exists():
            reg[name] = {"active": "v1", "versions": [{"version": "v1", "file": artefact, "status": "active",
                                                       "created_at": _now(), "note": "initial benchmark training"}]}
    return reg


def save_registry(model_dir: Path, reg: dict) -> None:
    registry_path(model_dir).write_text(json.dumps(reg, indent=2), encoding="utf-8")


def rollback(model_dir: Path, name: str, version: str) -> None:
    reg = load_registry(model_dir)
    entry = next(v for v in reg[name]["versions"] if v["version"] == version)
    shutil.copyfile(model_dir / entry["file"], model_dir / MANAGED[name][0])
    for v in reg[name]["versions"]:
        if v["status"] == "active":
            v["status"] = "retired"
    entry["status"] = "active"
    reg[name]["active"] = version
    save_registry(model_dir, reg)


# --- Retraining ----------------------------------------------------------------------------

def _macro_f1(y, p) -> float:
    from sklearn.metrics import f1_score

    return float(f1_score(y, p, labels=sorted(set(y)), average="macro", zero_division=0))


def retrain_sentiment_tfidf(model_dir: Path) -> dict:
    """Retrain the TF-IDF sentiment model with feedback; gate on frozen validation sets."""
    import joblib
    import pandas as pd

    from research import datasets
    from research.train import language_weights, tfidf_pipeline

    from .text import normalise

    data = datasets.all_sentiment()
    feedback = load_feedback(model_dir, "sentiment")
    fb = pd.DataFrame(feedback) if feedback else pd.DataFrame(columns=["text", "label", "language"])
    cut = int(len(fb) * 0.8)
    fb_train, fb_hold = fb.iloc[:cut], fb.iloc[cut:]

    current = joblib.load(model_dir / "sentiment_tfidf.joblib")
    C = current.get("selected", {}).get("C", 2.0)
    balanced = current.get("selected", {}).get("language_balanced", True)

    train = pd.concat([d["train"] for d in data.values()] + [fb_train.rename(columns={"language": "lang"})[["text", "label", "lang"]]],
                      ignore_index=True)
    candidate = tfidf_pipeline(C)
    candidate.fit([normalise(t) for t in train["text"]], train["label"],
                  clf__sample_weight=language_weights(train["lang"].fillna("en"), balanced))

    report = {"feedback_used": len(fb_train), "feedback_holdout": len(fb_hold), "languages": {}}
    ok = True
    for lang, d in data.items():
        X = [normalise(t) for t in d["validation"]["text"]]
        y = d["validation"]["label"].to_numpy()
        old, new = _macro_f1(y, current["pipeline"].predict(X)), _macro_f1(y, candidate.predict(X))
        report["languages"][lang] = {"active": old, "candidate": new}
        ok &= new >= old - GATE_TOLERANCE
    if len(fb_hold):
        X = [normalise(t) for t in fb_hold["text"]]
        y = fb_hold["label"].to_numpy()
        old, new = _macro_f1(y, current["pipeline"].predict(X)), _macro_f1(y, candidate.predict(X))
        report["feedback_holdout_f1"] = {"active": old, "candidate": new}
        ok &= new >= old

    return _register(model_dir, "sentiment_tfidf", {**current, "pipeline": candidate, "trained_at": _now()}, report, ok)


def _register(model_dir: Path, name: str, artefact: dict, report: dict, promote: bool) -> dict:
    import joblib

    reg = load_registry(model_dir)
    entry = reg.setdefault(name, {"active": None, "versions": []})
    version = f"v{len(entry['versions']) + 1}"
    file = f"{Path(MANAGED[name][0]).stem}.{version}.joblib"
    joblib.dump(artefact, model_dir / file, compress=3)
    status = "active" if promote else "rejected"
    if promote:
        for v in entry["versions"]:
            if v["status"] == "active":
                v["status"] = "retired"
        shutil.copyfile(model_dir / file, model_dir / MANAGED[name][0])
        entry["active"] = version
    entry["versions"].append({"version": version, "file": file, "status": status, "created_at": _now(), "metrics": report})
    save_registry(model_dir, reg)
    return {"name": name, "version": version, "status": status, "metrics": report}


# --- Drift -------------------------------------------------------------------------------------

def psi(expected: dict, actual: dict, eps: float = 1e-4) -> float:
    keys = set(expected) | set(actual)
    e_tot, a_tot = sum(expected.values()) or 1, sum(actual.values()) or 1
    total = 0.0
    for k in keys:
        e = max(expected.get(k, 0) / e_tot, eps)
        a = max(actual.get(k, 0) / a_tot, eps)
        total += (a - e) * np.log(a / e)
    return float(total)


class Monitor:
    """In-memory counters of what the service has predicted (no text is kept)."""

    def __init__(self) -> None:
        self.languages: Counter = Counter()
        self.labels: Counter = Counter()
        self.confidence: Counter = Counter()
        self.requests = 0
        self.started = time.time()

    def observe(self, language: str, label: str, score: float) -> None:
        self.languages[language] += 1
        self.labels[label] += 1
        self.confidence[min(9, int(score * 10))] += 1

    def report(self, reference: dict | None) -> dict:
        out = {
            "observed": sum(self.languages.values()),
            "languages": dict(self.languages),
            "labels": dict(self.labels),
            "confidence_deciles": {str(k): v for k, v in sorted(self.confidence.items())},
            "uptime_seconds": int(time.time() - self.started),
        }
        if reference and out["observed"] >= 100:
            out["psi"] = {
                "languages": psi(reference.get("languages", {}), self.languages),
                "labels": psi(reference.get("labels", {}), self.labels),
            }
            out["drift_alert"] = any(v > PSI_ALERT for v in out["psi"].values())
        return out


def main(argv: list[str]) -> None:
    from .config import Settings

    model_dir = Settings.from_env().model_dir
    if argv[:2] == ["retrain", "sentiment_tfidf"]:
        print(json.dumps(retrain_sentiment_tfidf(model_dir), indent=2))
    elif argv[:1] == ["rollback"] and len(argv) == 3:
        rollback(model_dir, argv[1], argv[2])
        print(f"{argv[1]} -> {argv[2]}")
    elif argv[:1] == ["registry"]:
        print(json.dumps(load_registry(model_dir), indent=2))
    else:
        print("usage: python -m soleglance_analytics.mlops [retrain sentiment_tfidf | rollback <name> <version> | registry]")


if __name__ == "__main__":
    main(sys.argv[1:])
