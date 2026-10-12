"""Cached sentence embeddings (encoding is the slow step on CPU)."""

from __future__ import annotations

import hashlib
from pathlib import Path

import numpy as np
import pandas as pd

from soleglance_analytics.config import DEFAULT_ENCODER
from soleglance_analytics.sentiment.trained import encode

CACHE = Path(__file__).resolve().parent / "cache" / "embeddings"


def embed(df: pd.DataFrame, lang: str, split: str, encoder: str = DEFAULT_ENCODER) -> np.ndarray:
    CACHE.mkdir(parents=True, exist_ok=True)
    digest = hashlib.sha1(("\n".join(df["text"]) + encoder).encode("utf-8")).hexdigest()[:12]
    path = CACHE / f"{lang}-{split}-{digest}.npy"
    if path.exists():
        return np.load(path)
    print(f"encoding {len(df)} {lang}/{split} texts…", flush=True)
    E = encode(encoder, list(df["text"]))
    np.save(path, E)
    return E
