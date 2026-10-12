"""Controlled evaluation of trend detection with injected bursts.

    python -m research.evaluate_trends

Real text (TweetEval + Roman Urdu test splits) is spread uniformly over
eight 24-hour windows. We then inject K topics: for each, a term that
already appears at a low background rate gets `spike` extra documents in
the current window (the term inserted into otherwise ordinary texts). A
good detector ranks the injected terms at the top; a naive "most frequent
terms right now" detector instead surfaces words that are always common.

Metrics, averaged over seeds: precision@K and recall@K of injected terms.
"""

from __future__ import annotations

import json
from collections import Counter
from datetime import datetime, timedelta, timezone
from pathlib import Path

import numpy as np

from soleglance_analytics.trends import Document, candidate_terms, detect

from . import datasets

RESULTS = Path(__file__).resolve().parent / "results"
NOW = datetime(2026, 10, 1, tzinfo=timezone.utc)
WINDOWS = 8  # 1 current + 7 history
K = 10


def frequency_baseline(docs: list[Document], now: datetime, k: int) -> list[str]:
    current = Counter()
    for d in docs:
        if d.created_at >= now - timedelta(hours=24):
            current.update(t for t in candidate_terms(d.text) if " " not in t)
    return [t for t, _ in current.most_common(k)]


def run(texts: list[str], vocab_rates: Counter, rng: np.random.Generator, spike: int, docs_per_window: int) -> dict:
    sample = rng.choice(len(texts), size=docs_per_window * WINDOWS, replace=len(texts) < docs_per_window * WINDOWS)
    docs = []
    for i, idx in enumerate(sample):
        window = i % WINDOWS  # 0 = current
        offset = timedelta(hours=24 * window + float(rng.uniform(0, 24)))
        docs.append(Document(f"bg{i}", texts[idx], NOW - offset))

    # Candidate injection terms: present in the corpus but rare (<= 0.2% of docs).
    rare = [t for t, c in vocab_rates.items() if 2 <= c <= max(3, int(0.002 * len(texts))) and t.isalpha() and len(t) >= 5]
    injected = list(rng.choice(rare, size=K, replace=False))
    for j, term in enumerate(injected):
        for m in range(spike):
            base = texts[int(rng.integers(len(texts)))]
            words = base.split()
            pos = int(rng.integers(len(words) + 1))
            text = " ".join(words[:pos] + [term] + words[pos:])
            docs.append(Document(f"inj{j}-{m}", text, NOW - timedelta(hours=float(rng.uniform(0, 24)))))

    def score(ranked: list[str]) -> tuple[float, float]:
        hits = {t for t in injected if any(t == r or t in r.split() for r in ranked)}
        relevant = [r for r in ranked if any(t == r or t in r.split() for t in injected)]
        return len(relevant) / max(1, len(ranked)), len(hits) / K

    burst = [t.term for t in detect(docs, NOW, 24, WINDOWS - 1, min_mentions=3, limit=K)]
    freq = frequency_baseline(docs, NOW, K)
    pb, rb = score(burst)
    pf, rf = score(freq)
    return {"burst_precision": pb, "burst_recall": rb, "freq_precision": pf, "freq_recall": rf}


def main(seeds: int = 20) -> None:
    RESULTS.mkdir(exist_ok=True)
    data = datasets.all_sentiment()
    texts = list(data["en"]["test"]["text"]) + list(data["ur-Latn"]["test"]["text"])
    vocab = Counter()
    for t in texts:
        vocab.update(w for w in candidate_terms(t) if " " not in w)

    results = {"corpus_docs": len(texts), "K": K, "settings": []}
    for docs_per_window in (500, 1500):
        for spike in (3, 5, 10, 20):
            runs = [run(texts, vocab, np.random.default_rng(seed), spike, docs_per_window) for seed in range(seeds)]
            agg = {k: {"mean": float(np.mean([r[k] for r in runs])), "sd": float(np.std([r[k] for r in runs]))} for k in runs[0]}
            row = {"docs_per_window": docs_per_window, "spike": spike, **agg}
            results["settings"].append(row)
            print(docs_per_window, spike, {k: round(v["mean"], 3) for k, v in agg.items()}, flush=True)

    (RESULTS / "trend_eval.json").write_text(json.dumps(results, indent=2))


if __name__ == "__main__":
    main()
