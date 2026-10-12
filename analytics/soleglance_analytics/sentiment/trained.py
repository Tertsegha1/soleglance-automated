"""Supervised backends trained by research/train.py.

* TfidfBackend: word 1-2 gram + character 2-5 gram TF-IDF with a
  multinomial logistic regression. Character n-grams cope well with the
  spelling variation of Roman Urdu ("bohat", "bohot", "bht").
* EmbeddingBackend: a frozen multilingual sentence encoder with a logistic
  regression head. Runs on CPU.
* EnsembleBackend: average of the two probability vectors.

Artefacts are joblib files in MODEL_DIR containing the fitted estimator and
metadata (label order, encoder name, training data summary).
"""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import Sequence

import joblib
import numpy as np

from ..text import normalise
from .base import LABELS, Prediction, from_proba


def _load(path: Path) -> dict:
    if not path.exists():
        raise FileNotFoundError(f"Model artefact not found: {path}. Run `python -m research.train`.")
    artefact = joblib.load(path)
    if tuple(artefact["labels"]) != LABELS:
        raise ValueError(f"{path} was trained with labels {artefact['labels']}, expected {LABELS}")
    return artefact


def _ordered_proba(estimator, X) -> np.ndarray:
    proba = estimator.predict_proba(X)
    order = [list(estimator.classes_).index(l) for l in LABELS]
    return proba[:, order]


@lru_cache(maxsize=2)
def load_encoder(name: str):
    from sentence_transformers import SentenceTransformer

    return SentenceTransformer(name, device="cpu")


def encode(encoder_name: str, texts: Sequence[str], batch_size: int = 64) -> np.ndarray:
    return load_encoder(encoder_name).encode(
        [normalise(t) for t in texts], batch_size=batch_size, normalize_embeddings=True, show_progress_bar=False
    )


class TfidfBackend:
    name = "tfidf-lr"

    def __init__(self, model_dir: Path) -> None:
        self.artefact = _load(model_dir / "sentiment_tfidf.joblib")

    def predict_proba(self, texts: Sequence[str]) -> np.ndarray:
        return _ordered_proba(self.artefact["pipeline"], [normalise(t) for t in texts])

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        return from_proba(self.predict_proba(texts))


class EmbeddingBackend:
    name = "mminilm-lr"

    def __init__(self, model_dir: Path) -> None:
        self.artefact = _load(model_dir / "sentiment_embedding.joblib")
        self.encoder_name = self.artefact["encoder"]

    def predict_proba(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> np.ndarray:
        X = embeddings if embeddings is not None else encode(self.encoder_name, texts)
        return _ordered_proba(self.artefact["head"], X)

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        return from_proba(self.predict_proba(texts))


class EnsembleBackend:
    name = "ensemble"

    def __init__(self, model_dir: Path) -> None:
        self.tfidf = TfidfBackend(model_dir)
        self.embedding = EmbeddingBackend(model_dir)

    def predict_proba(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> np.ndarray:
        return (self.tfidf.predict_proba(texts) + self.embedding.predict_proba(texts, embeddings)) / 2

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        return from_proba(self.predict_proba(texts))


class RoutedBackend:
    """Language-aware routing: identify the language, then use the model that
    scored best on that language's validation split (models/routing.json)."""

    name = "routed"

    def __init__(self, model_dir: Path, routing: dict[str, str] | None = None, name: str = "routed") -> None:
        import json

        from . import build

        self.name = name
        if routing is None:
            path = model_dir / "routing.json"
            if not path.exists():
                raise FileNotFoundError(f"{path} not found. Run `python -m research.train_routing`.")
            routing = json.loads(path.read_text())
        self.routing: dict[str, str] = routing
        self.backends = {name: build(name, model_dir) for name in set(self.routing.values())}

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        from .. import language

        langs = [language.detect(t) for t in texts]
        out: list[Prediction | None] = [None] * len(texts)
        for lang in set(langs):
            idx = [i for i, l in enumerate(langs) if l == lang]
            backend = self.backends[self.routing.get(lang, self.routing.get("en"))]
            for i, pred in zip(idx, backend.predict([texts[i] for i in idx])):
                out[i] = pred
        return out  # type: ignore[return-value]
