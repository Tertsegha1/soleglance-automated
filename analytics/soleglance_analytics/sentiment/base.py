from __future__ import annotations

from dataclasses import dataclass
from typing import Protocol, Sequence

import numpy as np

LABELS: tuple[str, str, str] = ("negative", "neutral", "positive")


@dataclass(frozen=True)
class Prediction:
    label: str
    score: float  # probability/confidence of `label`
    scores: dict[str, float]


class SentimentBackend(Protocol):
    name: str

    def predict(self, texts: Sequence[str]) -> list[Prediction]: ...


def from_proba(proba: np.ndarray) -> list[Prediction]:
    """Turn an (n, 3) probability matrix ordered as LABELS into predictions."""
    out = []
    for row in np.asarray(proba, dtype=float):
        row = np.clip(row, 0, None)
        total = row.sum()
        row = row / total if total > 0 else np.full(3, 1 / 3)
        i = int(row.argmax())
        out.append(Prediction(LABELS[i], float(row[i]), {l: float(p) for l, p in zip(LABELS, row)}))
    return out
