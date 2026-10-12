"""Trained health classifiers (artefacts written by research/train_health.py).

* health_mention.joblib: is the text a genuine mention of illness or a
  health event (vs. figurative use or unrelated text)? Trained on RHMD
  (English Reddit) over multilingual sentence embeddings, so it transfers
  zero-shot to Pidgin, Urdu and Roman Urdu.
* misinformation.joblib: probability that a health claim is false, trained
  on CoAID (English) and the Urdu fake-news corpus.

If an artefact is missing, a transparent lexicon rule is used instead.
"""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import Sequence

import joblib
import numpy as np

from ..sentiment.trained import encode
from ..text import normalise
from .extract import Mentions


@lru_cache(maxsize=4)
def _load(path: str):
    p = Path(path)
    return joblib.load(p) if p.exists() else None


def _component_proba(kind: str, est, positive: str, texts: Sequence[str], encoder: str | None,
                     embeddings: np.ndarray | None) -> np.ndarray:
    if kind == "embedding":
        X = embeddings if embeddings is not None else encode(encoder, list(texts))
    else:
        X = [normalise(t) for t in texts]
    return est.predict_proba(X)[:, list(est.classes_).index(positive)]


def _positive_proba(model: dict, texts: Sequence[str], embeddings: np.ndarray | None) -> np.ndarray:
    """Probability of the positive class; multi-component models average their members."""
    components = model["components"] if model["features"] == "components" else [(model["features"], model["estimator"])]
    probs = [_component_proba(kind, est, model["positive"], texts, model.get("encoder"), embeddings)
             for kind, est in components]
    return np.mean(probs, axis=0)


class HealthModels:
    def __init__(self, model_dir: Path) -> None:
        self.mention = _load(str(model_dir / "health_mention.joblib"))
        self.misinfo = _load(str(model_dir / "misinformation.joblib"))

    @property
    def names(self) -> dict[str, str]:
        return {
            "mention": self.mention["name"] if self.mention else "lexicon-rule",
            "misinformation": self.misinfo["name"] if self.misinfo else "unavailable",
        }

    def health_scores(self, texts: Sequence[str], mentions: Sequence[Mentions],
                      embeddings: np.ndarray | None = None) -> np.ndarray:
        if self.mention:
            return _positive_proba(self.mention, texts, embeddings)
        # Rule: a disease name, or a symptom alongside an outbreak/illness cue.
        return np.array([1.0 if m.diseases or (m.symptoms and m.outbreak_cues) else 0.0 for m in mentions])

    def misinformation_scores(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> np.ndarray | None:
        if not self.misinfo:
            return None
        return _positive_proba(self.misinfo, texts, embeddings)
