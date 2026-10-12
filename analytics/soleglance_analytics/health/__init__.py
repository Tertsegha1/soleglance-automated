"""Health signals: mention detection, misinformation flags and outbreak aberrations."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Sequence

from .. import language
from .extract import Mentions, extract
from .lexicon import COMMUNICABLE, CONCEPTS
from .models import HealthModels

HEALTH_THRESHOLD = 0.5
MISINFO_THRESHOLD = 0.7  # deliberately conservative: flags go to human review, never auto-removal
# The misinformation model was trained and validated on English and Urdu script
# only; other languages are not scored rather than scored unreliably.
MISINFO_LANGUAGES = frozenset({"en", "ur"})


def decide(m: Mentions, score: float) -> bool:
    """Health-mention rule shared by the service and the evaluation.

    A communicable-disease name counts directly; other terms (symptoms and
    conditions often used figuratively, e.g. "this traffic is killing me",
    "a cancer on society") need the classifier's confirmation.
    """
    if any(d in COMMUNICABLE for d in m.diseases):
        return True
    return bool(m.diseases or m.symptoms) and score >= HEALTH_THRESHOLD


@dataclass
class HealthResult:
    is_health: bool
    health_score: float
    diseases: list[str]
    symptoms: list[str]
    regions: list[str]
    countries: list[str]
    misinformation_score: float | None
    flag_misinformation: bool


def analyse(texts: Sequence[str], model_dir: Path, models: HealthModels | None = None) -> list[HealthResult]:
    models = models or HealthModels(model_dir)
    mentions: list[Mentions] = [extract(t) for t in texts]
    health = models.health_scores(texts, mentions)
    misinfo = models.misinformation_scores(texts)

    out = []
    for i, m in enumerate(mentions):
        # Lexicon evidence is always required, so the classifier filters
        # figurative uses rather than inventing diseases from unrelated text.
        has_terms = bool(m.diseases or m.symptoms)
        score = float(health[i]) if has_terms else 0.0
        is_health = decide(m, score)
        scorable = misinfo is not None and is_health and language.detect(texts[i]) in MISINFO_LANGUAGES
        mis = float(misinfo[i]) if scorable else None
        out.append(HealthResult(
            is_health=is_health,
            health_score=round(score, 4),
            diseases=m.diseases,
            symptoms=m.symptoms,
            regions=m.regions,
            countries=m.countries,
            misinformation_score=round(mis, 4) if mis is not None else None,
            flag_misinformation=mis is not None and mis >= MISINFO_THRESHOLD,
        ))
    return out


__all__ = ["COMMUNICABLE", "decide", "CONCEPTS", "HealthModels", "HealthResult", "analyse", "extract"]
