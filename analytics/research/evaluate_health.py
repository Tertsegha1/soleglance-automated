"""Evaluate health-mention detection and misinformation flagging on held-out tests.

    python -m research.evaluate_health
"""

from __future__ import annotations

import json
from pathlib import Path

import joblib
import numpy as np
from sklearn.metrics import accuracy_score, f1_score, precision_score, recall_score

from soleglance_analytics.health import COMMUNICABLE, decide
from soleglance_analytics.health.extract import extract
from soleglance_analytics.health.models import _positive_proba
from soleglance_analytics.text import normalise

from . import health_data
from .embeddings import embed

ROOT = Path(__file__).resolve().parent
MODELS = ROOT.parent / "models"
RESULTS = ROOT / "results"
RNG = np.random.default_rng(42)


def ci(y, p, pos, n=1000):
    idx = RNG.integers(0, len(y), size=(n, len(y)))
    s = [f1_score(y[i], p[i], average="macro") for i in idx]
    return [float(np.percentile(s, 2.5)), float(np.percentile(s, 97.5))]


def scores(y, p, pos) -> dict:
    return {
        "n": int(len(y)),
        "accuracy": float(accuracy_score(y, p)),
        "macro_f1": float(f1_score(y, p, average="macro")),
        "macro_f1_ci95": ci(y, p, pos),
        "precision_pos": float(precision_score(y, p, pos_label=pos, zero_division=0)),
        "recall_pos": float(recall_score(y, p, pos_label=pos, zero_division=0)),
    }


def mention() -> dict:
    df = health_data.rhmd()["test"]
    y = df["label"].to_numpy()
    E = embed(df, "rhmd", "test")
    texts = list(df["text"])
    emb = joblib.load(MODELS / "health_mention.joblib")
    tf = joblib.load(MODELS / "health_mention_tfidf_en.joblib")
    p_emb = _positive_proba(emb, texts, E)
    p_tf = _positive_proba(tf, texts, None)
    mentions = [extract(t) for t in texts]
    has_terms = np.array([bool(m.diseases or m.symptoms) for m in mentions])
    rule = np.array([bool(m.diseases or (m.symptoms and m.outbreak_cues)) for m in mentions])
    lab = lambda b: np.where(b, "health", "other")
    deployed = np.array([decide(mm, float(p)) for mm, p in zip(mentions, p_emb)])
    return {
        "lexicon_rule": scores(y, lab(rule), "health"),
        "tfidf_en_only": scores(y, lab(p_tf >= 0.5), "health"),
        "mminilm_lr": scores(y, lab(p_emb >= 0.5), "health"),
        "classifier_gated_v1": scores(y, lab(has_terms & (p_emb >= 0.5)), "health"),
        "deployed_pipeline": scores(y, lab(deployed), "health"),
        "communicable_share_of_test": float(np.mean([any(d in COMMUNICABLE for d in mm.diseases) for mm in mentions])),
        "lexicon_coverage_of_health_posts": float(has_terms[y == "health"].mean()),
    }


def misinformation() -> dict:
    sets = {"en": health_data.constraint(), "ur": health_data.urdu_fake()}
    model = joblib.load(MODELS / "misinformation.joblib")
    out = {"model": model["name"]}
    for lang, s in sets.items():
        df = s["test"]
        E = embed(df, f"misinfo-{lang}", "test")
        p = _positive_proba(model, list(df["text"]), E)
        pred = np.where(p >= 0.5, "false", "true")
        y = df["label"].to_numpy()
        out[lang] = scores(y, pred, "false")
        if lang == "ur":
            h = (df["category"] == "hlth").to_numpy()
            out["ur_health_only"] = scores(y[h], pred[h], "false")
        # Precision at the conservative review threshold used by the platform.
        flagged = p >= 0.7
        out[f"{lang}_review_threshold_0.7"] = {
            "flag_rate": float(flagged.mean()),
            "precision": float((y[flagged] == "false").mean()) if flagged.any() else None,
        }

    # Cross-lingual transfer: English-only model applied to Urdu.
    from sklearn.linear_model import LogisticRegression

    en = sets["en"]
    head = LogisticRegression(C=2.0, max_iter=5000, class_weight="balanced").fit(
        embed(en["train"], "misinfo-en", "train"), en["train"]["label"])
    ur = sets["ur"]["test"]
    pred = head.predict(embed(ur, "misinfo-ur", "test"))
    out["zero_shot_en_to_ur"] = scores(ur["label"].to_numpy(), pred, "false")
    return out


def annotated() -> dict:
    """Native-speaker test sets (paper/health-annotation-guidelines.md), if present."""
    from soleglance_analytics.sentiment.trained import encode

    folder = Path(__file__).resolve().parents[2] / "paper" / "annotation"
    emb = joblib.load(MODELS / "health_mention.joblib")
    out = {}
    for path in sorted(folder.glob("*_gold.csv")):
        import pandas as pd

        df = pd.read_csv(path).dropna(subset=["label"])
        y = np.where(df["label"].str.strip().str.lower() == "health", "health", "other")
        texts = list(df["text"])
        p = _positive_proba(emb, texts, encode(emb["encoder"], texts))
        has_terms = np.array([bool(extract(t).diseases or extract(t).symptoms) for t in texts])
        out[path.stem.replace("_gold", "")] = {
            "mminilm_lr": scores(y, np.where(p >= 0.5, "health", "other"), "health"),
            "deployed_pipeline": scores(y, np.where([decide(extract(t), float(s)) for t, s in zip(texts, p)], "health", "other"), "health"),
        }
    return out


def main() -> None:
    RESULTS.mkdir(exist_ok=True)
    results = {"mention": mention(), "misinformation": misinformation(), "annotated": annotated()}
    (RESULTS / "health_eval.json").write_text(json.dumps(results, indent=2))
    print(json.dumps(results, indent=1)[:3000])


if __name__ == "__main__":
    main()
