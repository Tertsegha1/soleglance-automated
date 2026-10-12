"""Train the character n-gram language identifier and choose the sentiment
model for each language (language-aware routing).

    python -m research.train_routing

Both decisions use the training and validation splits only:

1. Language ID: Perso-Arabic script -> ur; otherwise a character 1-4 gram
   logistic regression separates en, ur-Latn and pcm, trained on the
   English, Roman Urdu and Pidgin training texts. Regularisation is chosen on validation.
2. Routing: for each language, the sentiment backend with the highest
   validation macro-F1 is selected and written to models/routing.json.
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
from sklearn.pipeline import Pipeline

from soleglance_analytics.sentiment import build
from soleglance_analytics.sentiment.base import LABELS
from soleglance_analytics.text import normalise

from . import datasets
from .embeddings import embed

ROOT = Path(__file__).resolve().parent
MODELS = ROOT.parent / "models"
RESULTS = ROOT / "results"
CANDIDATES = ["xlmt", "afriberta", "tfidf", "embedding", "ensemble"]


def langid_pipeline(C: float) -> Pipeline:
    return Pipeline([
        ("chars", TfidfVectorizer(analyzer="char_wb", ngram_range=(1, 4), min_df=2, sublinear_tf=True, lowercase=True)),
        ("clf", LogisticRegression(C=C, max_iter=2000, class_weight="balanced")),
    ])


def main() -> None:
    data = datasets.all_sentiment()
    report: dict = {"langid": [], "routing": {}}

    latin = ["en", "ur-Latn", "pcm"]
    train = pd.concat([data[l]["train"] for l in latin], ignore_index=True)
    valid = pd.concat([data[l]["validation"] for l in latin], ignore_index=True)
    Xt, Xv = [normalise(t) for t in train["text"]], [normalise(t) for t in valid["text"]]
    best = None
    for C in (0.5, 2.0, 8.0):
        pipe = langid_pipeline(C).fit(Xt, train["lang"])
        score = f1_score(valid["lang"], pipe.predict(Xv), average="macro")
        report["langid"].append({"C": C, "val_macro_f1": score})
        print("langid", C, round(score, 4), flush=True)
        if best is None or score > best[0]:
            best = (score, pipe, C)
    joblib.dump({"pipeline": best[1], "C": best[2]}, MODELS / "langid.joblib", compress=3)

    routing = {}
    for lang in data:
        df = data[lang]["validation"]
        y = df["label"].to_numpy()
        classes = sorted(set(y))
        scores = {}
        for name in CANDIDATES:
            backend = build(name, MODELS)
            if name in ("embedding", "ensemble"):
                proba = backend.predict_proba(list(df["text"]), embed(df, lang, "validation"))
                p = np.array([LABELS[i] for i in proba.argmax(axis=1)])
            else:
                p = np.array([r.label for r in backend.predict(list(df["text"]))])
            scores[name] = float(f1_score(y, p, labels=classes, average="macro", zero_division=0))
        routing[lang] = max(scores, key=scores.get)
        report["routing"][lang] = {"validation_macro_f1": scores, "chosen": routing[lang]}
        print(lang, scores, "->", routing[lang], flush=True)

    (MODELS / "routing.json").write_text(json.dumps(routing, indent=2))

    # Reference distribution for drift monitoring (soleglance_analytics.mlops.Monitor).
    valid_all = pd.concat([data[l]["validation"] for l in data], ignore_index=True)
    reference = {
        "languages": valid_all["lang"].value_counts().to_dict(),
        "labels": valid_all["label"].value_counts().to_dict(),
        "source": "validation splits used for model selection",
    }
    (MODELS / "reference.json").write_text(json.dumps(reference, indent=2))
    RESULTS.mkdir(exist_ok=True)
    (RESULTS / "routing_selection.json").write_text(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
