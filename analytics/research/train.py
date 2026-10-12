"""Train the supervised sentiment models.

    python -m research.train

Model selection uses the validation splits only. The selection criterion
is the mean of the per-language macro-F1 scores, so the large English
corpus does not dominate the choice.
"""

from __future__ import annotations

import json
import time
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import f1_score
from sklearn.pipeline import FeatureUnion, Pipeline

from soleglance_analytics.config import DEFAULT_ENCODER
from soleglance_analytics.sentiment.base import LABELS
from soleglance_analytics.text import normalise

from . import datasets
from .embeddings import embed

ROOT = Path(__file__).resolve().parent
MODELS = ROOT.parent / "models"
RESULTS = ROOT / "results"


def language_weights(langs: pd.Series, balanced: bool) -> np.ndarray:
    if not balanced:
        return np.ones(len(langs))
    counts = langs.value_counts()
    return langs.map(lambda l: len(langs) / (len(counts) * counts[l])).to_numpy()


def mean_lang_f1(y_true: pd.Series, y_pred: np.ndarray, langs: pd.Series) -> tuple[float, dict[str, float]]:
    per = {}
    for l in sorted(langs.unique()):
        mask = (langs == l).to_numpy()
        yt = y_true[mask]
        per[l] = f1_score(yt, y_pred[mask], labels=sorted(set(yt)), average="macro", zero_division=0)
    return float(np.mean(list(per.values()))), per


def tfidf_pipeline(C: float) -> Pipeline:
    return Pipeline([
        ("features", FeatureUnion([
            ("word", TfidfVectorizer(ngram_range=(1, 2), min_df=2, max_features=200_000, sublinear_tf=True, lowercase=True)),
            ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=300_000, sublinear_tf=True, lowercase=True)),
        ])),
        ("clf", LogisticRegression(C=C, max_iter=3000, class_weight="balanced")),
    ])


def main() -> None:
    MODELS.mkdir(exist_ok=True)
    RESULTS.mkdir(exist_ok=True)
    data = datasets.all_sentiment()
    print(datasets.describe(data).to_string(index=False))

    train = pd.concat([d["train"] for d in data.values()], ignore_index=True)
    valid = pd.concat([d["validation"] for d in data.values()], ignore_index=True)
    report: dict = {"data": datasets.describe(data).to_dict(orient="records"), "tfidf": [], "embedding": []}

    # --- TF-IDF + logistic regression -------------------------------------------------
    best = None
    X_train = [normalise(t) for t in train["text"]]
    X_valid = [normalise(t) for t in valid["text"]]
    for C in (0.5, 2.0, 8.0):
        for balanced in (False, True):
            t0 = time.time()
            pipe = tfidf_pipeline(C)
            pipe.fit(X_train, train["label"], clf__sample_weight=language_weights(train["lang"], balanced))
            score, per = mean_lang_f1(valid["label"], pipe.predict(X_valid), valid["lang"])
            row = {"C": C, "language_balanced": balanced, "val_mean_macro_f1": score, "per_language": per, "fit_seconds": round(time.time() - t0, 1)}
            report["tfidf"].append(row)
            print("tfidf", row)
            if best is None or score > best[0]:
                best = (score, pipe, row)
    joblib.dump({"pipeline": best[1], "labels": LABELS, "selected": best[2]}, MODELS / "sentiment_tfidf.joblib", compress=3)

    # --- Multilingual sentence embeddings + logistic regression ------------------------
    E_train = np.vstack([embed(data[l]["train"], l, "train") for l in data])
    E_valid = np.vstack([embed(data[l]["validation"], l, "validation") for l in data])
    best = None
    for C in (0.25, 1.0, 4.0, 16.0):
        for balanced in (False, True):
            head = LogisticRegression(C=C, max_iter=5000, class_weight="balanced")
            head.fit(E_train, train["label"], sample_weight=language_weights(train["lang"], balanced))
            score, per = mean_lang_f1(valid["label"], head.predict(E_valid), valid["lang"])
            row = {"C": C, "language_balanced": balanced, "val_mean_macro_f1": score, "per_language": per}
            report["embedding"].append(row)
            print("embedding", row)
            if best is None or score > best[0]:
                best = (score, head, row)
    joblib.dump({"head": best[1], "encoder": DEFAULT_ENCODER, "labels": LABELS, "selected": best[2]},
                MODELS / "sentiment_embedding.joblib", compress=3)

    (RESULTS / "training.json").write_text(json.dumps(report, indent=2))
    print("Saved models to", MODELS)


if __name__ == "__main__":
    main()
