"""Find disease, symptom and place mentions in text by token-sequence matching."""

from __future__ import annotations

from dataclasses import dataclass, field
from functools import lru_cache

from ..text import tokenise
from .geo import COUNTRY_NAMES, REGIONS
from .lexicon import CONCEPTS, OUTBREAK_CUES


@dataclass
class Mentions:
    diseases: list[str] = field(default_factory=list)
    symptoms: list[str] = field(default_factory=list)
    regions: list[str] = field(default_factory=list)
    countries: list[str] = field(default_factory=list)
    outbreak_cues: int = 0


def _tok(phrase: str) -> tuple[str, ...]:
    return tuple(t.lstrip("#") for t in tokenise(phrase))


@lru_cache(maxsize=1)
def _index() -> dict[tuple[str, ...], list[tuple[str, str]]]:
    """Map token sequences to (type, id) targets."""
    idx: dict[tuple[str, ...], list[tuple[str, str]]] = {}
    for cid, c in CONCEPTS.items():
        for term in c["terms"]:
            idx.setdefault(_tok(term), []).append((c["kind"], cid))
    for code, (country, _, forms) in REGIONS.items():
        for form in forms:
            idx.setdefault(_tok(form), []).append(("region", code))
    for country, forms in COUNTRY_NAMES.items():
        for form in forms:
            idx.setdefault(_tok(form), []).append(("country", country))
    idx.pop((), None)
    return idx


@lru_cache(maxsize=1)
def _max_len() -> int:
    return max(len(k) for k in _index())


def extract(text: str) -> Mentions:
    tokens = [t.lstrip("#") for t in tokenise(text)]
    idx, n = _index(), _max_len()
    found: dict[str, list[str]] = {"disease": [], "symptom": [], "region": [], "country": []}
    i = 0
    while i < len(tokens):
        # Longest match first so "lassa fever" beats "lassa", "benin city" beats nothing.
        for size in range(min(n, len(tokens) - i), 0, -1):
            hits = idx.get(tuple(tokens[i:i + size]))
            if hits:
                for kind, target in hits:
                    if target not in found[kind]:
                        found[kind].append(target)
                i += size
                break
        else:
            i += 1

    countries = list(found["country"])
    for code in found["region"]:
        if REGIONS[code][0] not in countries:
            countries.append(REGIONS[code][0])

    return Mentions(
        diseases=found["disease"],
        symptoms=found["symptom"],
        regions=found["region"],
        countries=countries,
        outbreak_cues=sum(1 for t in tokens if t in OUTBREAK_CUES),
    )
