"""Evaluate the zero-shot topic classifiers on AG News.

    python -m research.evaluate_topics

AG News has four classes (World, Sports, Business, Sci/Tech), mapped to
politics, sports, business and technology. We report:

* closed-set accuracy/macro-F1: prediction restricted to those 4 classes;
* open-set accuracy: the classifier may answer any of SoleGlance's 8
  categories or "other", and anything outside the gold class is wrong.
"""

from __future__ import annotations

import json
from pathlib import Path

import numpy as np
from sklearn.metrics import accuracy_score, f1_score

from soleglance_analytics.config import DEFAULT_ENCODER
from soleglance_analytics.topics import CATEGORIES, HybridClassifier, KeywordClassifier, PrototypeClassifier

from . import datasets
from .embeddings import CACHE as EMB_CACHE

RESULTS = Path(__file__).resolve().parent / "results"
GOLD = ["politics", "sports", "business", "technology"]


def main() -> None:
    RESULTS.mkdir(exist_ok=True)
    df = datasets.ag_news()
    texts = list(df["text"])
    y = df["category"].to_numpy()

    EMB_CACHE.mkdir(parents=True, exist_ok=True)
    emb_path = EMB_CACHE / "ag_news-test.npy"
    if emb_path.exists():
        E = np.load(emb_path)
    else:
        from soleglance_analytics.sentiment.trained import encode

        print(f"encoding {len(texts)} AG News texts…", flush=True)
        E = encode(DEFAULT_ENCODER, texts)
        np.save(emb_path, E)

    gold_idx = [CATEGORIES.index(c) for c in GOLD]
    results = {"n": len(df)}

    kw = KeywordClassifier()
    kw_scores = kw.scores(texts)
    proto = PrototypeClassifier(DEFAULT_ENCODER)
    proto_probs, _ = proto.probabilities(texts, E)
    hybrid = HybridClassifier(DEFAULT_ENCODER)

    systems = {
        "keywords": (kw_scores, [p.category for p in kw.predict(texts)]),
        "prototypes": (proto_probs, [p.category for p in proto.predict(texts, E)]),
        "hybrid": (None, [p.category for p in hybrid.predict(texts, E)]),
    }
    for name, (scores, open_preds) in systems.items():
        if scores is None:
            k = kw_scores / np.maximum(1.0, kw_scores.sum(axis=1, keepdims=True)) * 1.5
            scores = proto_probs + k
        closed = np.array([GOLD[i] for i in scores[:, gold_idx].argmax(axis=1)])
        # Keyword classifier abstains on texts with no hits; count those as errors in closed-set too.
        if name == "keywords":
            closed = np.where(kw_scores.sum(axis=1) > 0, closed, "other")
        results[name] = {
            "closed_accuracy": float(accuracy_score(y, closed)),
            "closed_macro_f1": float(f1_score(y, closed, labels=GOLD, average="macro")),
            "open_accuracy": float(accuracy_score(y, open_preds)),
            "abstain_rate": float(np.mean(np.array(open_preds) == "other")),
        }
        print(name, results[name], flush=True)

    (RESULTS / "topic_eval.json").write_text(json.dumps(results, indent=2))


if __name__ == "__main__":
    main()
