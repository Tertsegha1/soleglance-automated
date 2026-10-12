"""Sentiment backends and a registry that picks the configured one."""

from __future__ import annotations

import logging
from functools import lru_cache
from pathlib import Path

from .base import LABELS, Prediction, SentimentBackend

log = logging.getLogger(__name__)

BACKENDS = ("routed", "ensemble", "embedding", "tfidf", "xlmt", "afriberta", "vader", "textblob")


def build(name: str, model_dir: Path) -> SentimentBackend:
    if name == "vader":
        from .lexicon import VaderBackend

        return VaderBackend()
    if name == "textblob":
        from .lexicon import TextBlobBackend

        return TextBlobBackend()
    if name == "tfidf":
        from .trained import TfidfBackend

        return TfidfBackend(model_dir)
    if name == "embedding":
        from .trained import EmbeddingBackend

        return EmbeddingBackend(model_dir)
    if name == "routed":
        from .trained import RoutedBackend

        return RoutedBackend(model_dir)
    if name == "ensemble":
        from .trained import EnsembleBackend

        return EnsembleBackend(model_dir)
    if name == "xlmt":
        from .xlmt import XlmtBackend

        return XlmtBackend()
    if name == "afriberta":
        from .xlmt import AfribertaBackend

        return AfribertaBackend()
    raise ValueError(f"Unknown sentiment backend {name!r}; choose from {BACKENDS}")


@lru_cache(maxsize=1)
def get_backend(preferred: str, model_dir: str) -> SentimentBackend:
    """The preferred backend, falling back to simpler ones if artefacts are missing."""
    chain = [preferred] + [b for b in ("ensemble", "tfidf", "vader") if b != preferred]
    for name in chain:
        try:
            backend = build(name, Path(model_dir))
            if name != preferred:
                log.warning("Sentiment backend %r unavailable; using %r instead", preferred, name)
            return backend
        except (FileNotFoundError, ImportError, OSError) as e:
            log.warning("Could not load sentiment backend %r: %s", name, e)
    raise RuntimeError("No sentiment backend could be loaded")


__all__ = ["LABELS", "Prediction", "SentimentBackend", "BACKENDS", "build", "get_backend"]
