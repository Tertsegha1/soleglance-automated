"""Trending-topic detection by burst scoring.

A term is trending when the number of documents mentioning it in the
current window is far above what the previous windows lead us to expect.
For each candidate term t:

    c_t       documents containing t in the current window
    mu_t      mean documents per window over the H history windows
    score_t = (c_t - mu_t) / sqrt(mu_t + 1)

i.e. a Poisson z-score with add-one smoothing, so brand-new terms are not
divided by zero and rare terms need more evidence. Candidates are hashtags,
content unigrams and adjacent content bigrams. A unigram is dropped when
a selected bigram containing it covers most of its mentions, so we report
"budget debate" rather than both "budget" and "debate".
"""

from __future__ import annotations

import math
from collections import Counter, defaultdict
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from typing import Iterable, Sequence

from .text import content_tokens

SENTIMENT_SIGN = {"positive": 1.0, "neutral": 0.0, "negative": -1.0}


@dataclass(frozen=True)
class Document:
    id: str
    text: str
    created_at: datetime
    sentiment: str | None = None
    sentiment_score: float | None = None
    category: str | None = None


@dataclass
class Trend:
    term: str
    mentions: int
    baseline: float
    score: float
    category: str | None
    sentiment: dict[str, float] = field(default_factory=dict)
    document_ids: list[str] = field(default_factory=list)


def candidate_terms(text: str) -> set[str]:
    """Content unigrams (hashtags folded into the bare word) and adjacent bigrams."""
    words = [t.lstrip("#") for t in content_tokens(text)]
    terms = set(words)
    terms.update(f"{a} {b}" for a, b in zip(words, words[1:]) if a != b)
    return terms


def burst_score(count: int, baseline: float) -> float:
    return (count - baseline) / math.sqrt(baseline + 1.0)


def detect(
    documents: Iterable[Document],
    now: datetime,
    window_hours: int = 24,
    history_windows: int = 7,
    min_mentions: int = 3,
    limit: int = 30,
    max_doc_ids: int = 50,
) -> list[Trend]:
    window = timedelta(hours=window_hours)
    current_start = now - window
    oldest = now - window * (history_windows + 1)

    current_counts: Counter[str] = Counter()
    history_counts: Counter[str] = Counter()
    current_docs: dict[str, list[Document]] = defaultdict(list)

    for doc in documents:
        if doc.created_at > now or doc.created_at < oldest:
            continue
        terms = candidate_terms(doc.text)
        if doc.created_at >= current_start:
            current_counts.update(terms)
            for t in terms:
                current_docs[t].append(doc)
        else:
            history_counts.update(terms)

    scored: list[tuple[float, str, float]] = []
    for term, c in current_counts.items():
        if c < min_mentions:
            continue
        mu = history_counts[term] / history_windows if history_windows else 0.0
        if c <= mu:
            continue
        scored.append((burst_score(c, mu), term, mu))
    scored.sort(key=lambda x: (-x[0], x[1]))

    # A bigram "covers" a unigram when it accounts for most of the unigram's
    # current mentions; covered unigrams are reported via the bigram instead.
    covered: set[str] = set()
    phrases: set[str] = set()
    for _, term, _ in scored:
        if " " in term:
            hit = [w for w in term.split() if current_counts[term] >= 0.6 * current_counts[w]]
            if hit:
                covered.update(hit)
                phrases.add(term)

    selected = [
        (score, term, mu) for score, term, mu in scored
        if (" " in term and term in phrases) or (" " not in term and term not in covered)
    ][:limit]

    return [_build(term, score, mu, current_docs[term], max_doc_ids) for score, term, mu in selected]


def _build(term: str, score: float, mu: float, docs: Sequence[Document], max_doc_ids: int) -> Trend:
    labels = Counter(d.sentiment for d in docs if d.sentiment in SENTIMENT_SIGN)
    signed = [SENTIMENT_SIGN[d.sentiment] * (d.sentiment_score if d.sentiment_score is not None else 1.0)
              for d in docs if d.sentiment in SENTIMENT_SIGN]
    categories = Counter(d.category for d in docs if d.category and d.category != "other")
    newest_first = sorted(docs, key=lambda d: d.created_at, reverse=True)
    return Trend(
        term=term,
        mentions=len(docs),
        baseline=round(mu, 3),
        score=round(score, 3),
        category=categories.most_common(1)[0][0] if categories else None,
        sentiment={
            "positive": labels["positive"],
            "neutral": labels["neutral"],
            "negative": labels["negative"],
            "mean": round(sum(signed) / len(signed), 4) if signed else None,
        },
        document_ids=[d.id for d in newest_first[:max_doc_ids]],
    )
