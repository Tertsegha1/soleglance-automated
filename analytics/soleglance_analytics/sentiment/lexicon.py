"""Lexicon baselines.

VADER and TextBlob are English-only. They are kept as baselines (the 2022
prototype used TextBlob) and are expected to fail on Urdu and Roman Urdu.
"""

from __future__ import annotations

from typing import Sequence

from .base import Prediction


def _from_polarity(p: float, threshold: float) -> Prediction:
    p = max(-1.0, min(1.0, p))
    scores = {"negative": max(0.0, -p), "neutral": 1.0 - abs(p), "positive": max(0.0, p)}
    if p >= threshold:
        label = "positive"
    elif p <= -threshold:
        label = "negative"
    else:
        label = "neutral"
    return Prediction(label, scores[label], scores)


class VaderBackend:
    """VADER compound score with the authors' recommended +-0.05 thresholds."""

    name = "vader"

    def __init__(self) -> None:
        from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer

        self._sia = SentimentIntensityAnalyzer()

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        return [_from_polarity(self._sia.polarity_scores(t)["compound"], 0.05) for t in texts]


class TextBlobBackend:
    """TextBlob (Pattern) polarity; zero is neutral, as in the original prototype."""

    name = "textblob"

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        from textblob import TextBlob

        return [_from_polarity(TextBlob(t).sentiment.polarity, 1e-9) for t in texts]
