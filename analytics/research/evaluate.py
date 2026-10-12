"""Evaluate every sentiment backend and the language identifier on the
held-out test splits.

    python -m research.evaluate [--skip-xlmt]

Writes research/results/sentiment_eval.json, a Markdown table and
confusion-matrix figures. All numbers in the paper come from this script.
"""

from __future__ import annotations

import argparse
import json
import time
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, recall_score

from soleglance_analytics import language
from soleglance_analytics.sentiment import build
from soleglance_analytics.sentiment.base import LABELS

from . import datasets
from .embeddings import embed

ROOT = Path(__file__).resolve().parent
RESULTS = ROOT / "results"
MODELS = ROOT.parent / "models"
RNG = np.random.default_rng(42)
N_BOOT = 1000


def bootstrap_ci(y: np.ndarray, p: np.ndarray, n: int = N_BOOT) -> tuple[float, float]:
    idx = RNG.integers(0, len(y), size=(n, len(y)))
    scores = [macro_f1(y[i], p[i]) for i in idx]
    return float(np.percentile(scores, 2.5)), float(np.percentile(scores, 97.5))


def paired_bootstrap(y: np.ndarray, a: np.ndarray, b: np.ndarray, n: int = N_BOOT) -> float:
    """One-sided p-value that system a is not better than b in macro-F1 (Berg-Kirkpatrick et al. 2012)."""
    observed = macro_f1(y, a) - macro_f1(y, b)
    if observed <= 0:
        return 1.0
    idx = RNG.integers(0, len(y), size=(n, len(y)))
    deltas = np.array([macro_f1(y[i], a[i]) - macro_f1(y[i], b[i]) for i in idx])
    return float(np.mean(deltas > 2 * observed))


def present(y: np.ndarray) -> list[str]:
    """Gold classes in this test set (Urdu is binary); predictions outside them count as errors."""
    return [l for l in LABELS if l in set(y)]


def macro_f1(y: np.ndarray, p: np.ndarray) -> float:
    return float(f1_score(y, p, labels=present(y), average="macro", zero_division=0))


def metrics(y: np.ndarray, p: np.ndarray) -> dict:
    lo, hi = bootstrap_ci(y, p)
    return {
        "n": int(len(y)),
        "classes": present(y),
        "accuracy": float(accuracy_score(y, p)),
        "macro_f1": macro_f1(y, p),
        "macro_f1_ci95": [lo, hi],
        "avg_recall": float(recall_score(y, p, labels=present(y), average="macro", zero_division=0)),  # SemEval-2017 primary metric
        "f1_per_class": dict(zip(LABELS, map(float, f1_score(y, p, labels=LABELS, average=None)))),
        "confusion": confusion_matrix(y, p, labels=LABELS).tolist(),
    }


def predict(name: str, backend, df: pd.DataFrame, lang: str) -> tuple[np.ndarray, float]:
    texts = list(df["text"])
    t0 = time.perf_counter()
    if name in ("embedding", "ensemble"):
        # Reuse cached embeddings; time is measured separately in latency().
        E = embed(df, lang, "test")
        proba = backend.predict_proba(texts, E)
        preds = np.array([LABELS[i] for i in proba.argmax(axis=1)])
    else:
        preds = np.array([p.label for p in backend.predict(texts)])
    return preds, time.perf_counter() - t0


def latency(backend, texts: list[str], repeats: int = 3) -> dict:
    """Wall-clock latency on this machine (CPU) for one text and a batch of 32."""
    single = []
    for t in texts[:20]:
        t0 = time.perf_counter()
        backend.predict([t])
        single.append(time.perf_counter() - t0)
    batch = []
    for _ in range(repeats):
        t0 = time.perf_counter()
        backend.predict(texts[:32])
        batch.append(time.perf_counter() - t0)
    return {"single_ms_median": 1000 * float(np.median(single[2:])), "batch32_ms_median": 1000 * float(np.median(batch))}


def evaluate_langid(data, detector=language.detect) -> dict:
    rows = []
    for lang, splits in data.items():
        for text in splits["test"]["text"]:
            rows.append((lang, detector(text)))
    y, p = zip(*rows)
    labels = ["en", "ur-Latn", "ur", "pcm"]
    return {
        "accuracy": float(accuracy_score(y, p)),
        "macro_f1": float(f1_score(y, p, labels=labels, average="macro")),
        "per_class_f1": dict(zip(labels, map(float, f1_score(y, p, labels=labels, average=None)))),
        "confusion": {"labels": labels, "matrix": confusion_matrix(y, p, labels=labels).tolist()},
    }


def plot_confusions(results: dict, system: str) -> None:
    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    langs = list(results["sentiment"].keys())
    fig, axes = plt.subplots(1, len(langs), figsize=(4 * len(langs), 3.6))
    for ax, lang in zip(np.atleast_1d(axes), langs):
        cm = np.array(results["sentiment"][lang][system]["confusion"], dtype=float)
        cm_norm = cm / cm.sum(axis=1, keepdims=True).clip(min=1)
        ax.imshow(cm_norm, cmap="Blues", vmin=0, vmax=1)
        for i in range(3):
            for j in range(3):
                ax.text(j, i, f"{int(cm[i, j])}\n{cm_norm[i, j]:.0%}", ha="center", va="center",
                        color="white" if cm_norm[i, j] > 0.5 else "black", fontsize=8)
        ax.set_xticks(range(3), ["neg", "neu", "pos"])
        ax.set_yticks(range(3), ["neg", "neu", "pos"])
        ax.set_xlabel("predicted")
        ax.set_ylabel("true")
        ax.set_title({"en": "English", "ur-Latn": "Roman Urdu", "ur": "Urdu", "pcm": "Nigerian Pidgin"}[lang])
    fig.tight_layout()
    fig.savefig(RESULTS / f"confusion_{system}.pdf")
    fig.savefig(RESULTS / f"confusion_{system}.png", dpi=200)


def markdown_table(results: dict, systems: list[str]) -> str:
    langs = list(results["sentiment"].keys())
    head = "| System | " + " | ".join(f"{l} macro-F1 [95% CI]" for l in langs) + " | mean |\n"
    head += "|---|" + "---|" * (len(langs) + 1) + "\n"
    rows = []
    for s in systems:
        cells, vals = [], []
        for l in langs:
            m = results["sentiment"][l][s]
            vals.append(m["macro_f1"])
            cells.append(f"{m['macro_f1']:.3f} [{m['macro_f1_ci95'][0]:.3f}, {m['macro_f1_ci95'][1]:.3f}]")
        rows.append(f"| {s} | " + " | ".join(cells) + f" | {np.mean(vals):.3f} |")
    return head + "\n".join(rows) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--skip-xlmt", action="store_true", help="skip the 1 GB zero-shot transformer baseline")
    args = parser.parse_args()

    RESULTS.mkdir(exist_ok=True)
    data = datasets.all_sentiment()
    systems = ["textblob", "vader"] + ([] if args.skip_xlmt else ["xlmt", "afriberta"]) + ["tfidf", "embedding", "ensemble"] + ([] if args.skip_xlmt else ["routed"])
    backends = {s: build(s, MODELS) for s in systems}
    if "routed" in systems:
        # Leakage-free variant: route only between models trained here (no released
        # sentiment classifiers, whose training data overlap our test sets).
        from soleglance_analytics.sentiment.trained import RoutedBackend

        sel = json.loads((RESULTS / "routing_selection.json").read_text())["routing"]
        own = {l: max(("tfidf", "embedding", "ensemble"), key=lambda m: v["validation_macro_f1"][m]) for l, v in sel.items()}
        backends["routed_own"] = RoutedBackend(MODELS, own, "routed-own")
        systems.append("routed_own")
        results_routing_own = own

    results: dict = {"sentiment": {}, "latency": {}, "significance": {},
                     "routing_own": locals().get("results_routing_own")}
    preds_by: dict = {}
    for lang, splits in data.items():
        df = splits["test"]
        y = df["label"].to_numpy()
        results["sentiment"][lang] = {}
        preds_by[lang] = {}
        for s, backend in backends.items():
            p, seconds = predict(s, backend, df, lang)
            preds_by[lang][s] = p
            results["sentiment"][lang][s] = metrics(y, p) | {"seconds": round(seconds, 2)}
            print(lang, s, round(results["sentiment"][lang][s]["macro_f1"], 4), flush=True)

        # Deployed systems vs the strongest off-the-shelf baseline and vs each other.
        external = [s for s in systems if s in ("textblob", "vader", "xlmt", "afriberta")]
        best_external = max(external, key=lambda s: results["sentiment"][lang][s]["macro_f1"])
        pairs = [("ensemble", best_external), ("ensemble", "tfidf"), ("ensemble", "embedding")]
        if "routed" in systems:
            pairs += [("routed", best_external), ("routed", "ensemble"), (best_external, "routed"),
                      ("routed_own", best_external), (best_external, "routed_own")]
        results["significance"][lang] = {
            f"{a}_vs_{b}": paired_bootstrap(y, preds_by[lang][a], preds_by[lang][b]) for a, b in pairs if a != b
        }

    sample = list(data["en"]["test"]["text"][:48]) + list(data["ur-Latn"]["test"]["text"][:48]) + list(data["pcm"]["test"]["text"][:32])
    for s, backend in backends.items():
        results["latency"][s] = latency(backend, sample)

    results["language_id"] = evaluate_langid(data)
    results["language_id_lexicon"] = evaluate_langid(data, language.detect_lexicon)
    results["data"] = datasets.describe(data).to_dict(orient="records")

    (RESULTS / "sentiment_eval.json").write_text(json.dumps(results, indent=2))
    (RESULTS / "sentiment_eval.md").write_text(markdown_table(results, systems))
    plot_confusions(results, "ensemble")
    print(markdown_table(results, systems))
    print("language id:", json.dumps(results["language_id"], indent=2))


if __name__ == "__main__":
    main()
