"""Train the health-mention and health-misinformation classifiers.

    python -m research.train_health

Health mention: only English labels exist (RHMD), but the platform must
work in four languages, so the deployed model is pre-specified as the
multilingual sentence encoder + logistic regression (cross-lingual
transfer). A TF-IDF model is trained as an English-only reference.
Regularisation is chosen on the validation split.

Misinformation: trained on English (Constraint) and Urdu (Bend the Truth)
together. TF-IDF, embedding and ensemble models are compared on the mean of
per-language validation macro-F1, and the best is deployed.
"""

from __future__ import annotations

import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import f1_score
from sklearn.pipeline import FeatureUnion, Pipeline

from soleglance_analytics.config import DEFAULT_ENCODER
from soleglance_analytics.text import normalise

from . import health_data
from .embeddings import embed

ROOT = Path(__file__).resolve().parent
MODELS = ROOT.parent / "models"
RESULTS = ROOT / "results"


def tfidf(C: float) -> Pipeline:
    return Pipeline([
        ("features", FeatureUnion([
            ("word", TfidfVectorizer(ngram_range=(1, 2), min_df=2, sublinear_tf=True)),
            ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=2, sublinear_tf=True)),
        ])),
        ("clf", LogisticRegression(C=C, max_iter=3000, class_weight="balanced")),
    ])


def head(C: float) -> LogisticRegression:
    return LogisticRegression(C=C, max_iter=5000, class_weight="balanced")


def proba(est, X, positive: str) -> np.ndarray:
    return est.predict_proba(X)[:, list(est.classes_).index(positive)]


def train_mention(report: dict) -> None:
    d = health_data.rhmd()
    E_tr, E_va = embed(d["train"], "rhmd", "train"), embed(d["validation"], "rhmd", "validation")
    Xt, Xv = [normalise(t) for t in d["train"]["text"]], [normalise(t) for t in d["validation"]["text"]]
    yt, yv = d["train"]["label"], d["validation"]["label"]

    rows = {"embedding": [], "tfidf": []}
    best_emb = best_tf = None
    for C in (0.25, 1.0, 4.0, 16.0):
        est = head(C).fit(E_tr, yt)
        s = f1_score(yv, est.predict(E_va), average="macro")
        rows["embedding"].append({"C": C, "val_macro_f1": s})
        if best_emb is None or s > best_emb[0]:
            best_emb = (s, est, C)
        pipe = tfidf(C).fit(Xt, yt)
        s = f1_score(yv, pipe.predict(Xv), average="macro")
        rows["tfidf"].append({"C": C, "val_macro_f1": s})
        if best_tf is None or s > best_tf[0]:
            best_tf = (s, pipe, C)
    joblib.dump({"name": "mminilm-lr-rhmd", "features": "embedding", "encoder": DEFAULT_ENCODER,
                 "estimator": best_emb[1], "positive": "health", "C": best_emb[2]},
                MODELS / "health_mention.joblib", compress=3)
    joblib.dump({"name": "tfidf-lr-rhmd", "features": "tfidf", "estimator": best_tf[1], "positive": "health", "C": best_tf[2]},
                MODELS / "health_mention_tfidf_en.joblib", compress=3)
    report["mention"] = rows
    print("mention", {k: max(r["val_macro_f1"] for r in v) for k, v in rows.items()}, flush=True)


def train_misinfo(report: dict) -> None:
    sets = {"en": health_data.constraint(), "ur": health_data.urdu_fake()}
    train = pd.concat([s["train"] for s in sets.values()], ignore_index=True)
    E_tr = np.vstack([embed(sets[l]["train"], f"misinfo-{l}", "train") for l in sets])
    Xt = [normalise(t) for t in train["text"]]
    valid = {l: (sets[l]["validation"], embed(sets[l]["validation"], f"misinfo-{l}", "validation")) for l in sets}

    def mean_f1(predict) -> tuple[float, dict]:
        per = {}
        for l, (df, E) in valid.items():
            per[l] = f1_score(df["label"], predict(df, E), average="macro")
        return float(np.mean(list(per.values()))), per

    rows, best = [], None
    for C in (0.5, 2.0, 8.0):
        emb = head(C).fit(E_tr, train["label"])
        tf = tfidf(C).fit(Xt, train["label"])
        candidates = {
            "embedding": lambda df, E, emb=emb: emb.predict(E),
            "tfidf": lambda df, E, tf=tf: tf.predict([normalise(t) for t in df["text"]]),
            "ensemble": lambda df, E, emb=emb, tf=tf: np.where(
                (proba(emb, E, "false") + proba(tf, [normalise(t) for t in df["text"]], "false")) / 2 >= 0.5, "false", "true"),
        }
        for kind, fn in candidates.items():
            score, per = mean_f1(fn)
            rows.append({"C": C, "model": kind, "val_mean_macro_f1": score, "per_language": per})
            if best is None or score > best[0]:
                best = (score, kind, C, emb, tf)
    _, kind, C, emb, tf = best
    components = {"embedding": [("embedding", emb)], "tfidf": [("tfidf", tf)], "ensemble": [("embedding", emb), ("tfidf", tf)]}[kind]
    joblib.dump({"name": f"{kind}-misinfo-en-ur", "features": "components", "encoder": DEFAULT_ENCODER,
                 "components": components, "positive": "false", "C": C},
                MODELS / "misinformation.joblib", compress=3)
    report["misinformation"] = rows
    print("misinfo selected", kind, C, round(best[0], 4), flush=True)


def main() -> None:
    MODELS.mkdir(exist_ok=True)
    RESULTS.mkdir(exist_ok=True)
    report: dict = {}
    train_mention(report)
    train_misinfo(report)
    (RESULTS / "health_training.json").write_text(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
