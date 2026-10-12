from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

DEFAULT_ENCODER = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"


@dataclass(frozen=True)
class Settings:
    token: str
    sentiment_backend: str
    topic_backend: str
    model_dir: Path
    encoder: str
    max_batch: int = 256
    max_text_chars: int = 5000
    max_documents: int = 50000

    @classmethod
    def from_env(cls) -> "Settings":
        return cls(
            token=os.environ.get("ANALYTICS_TOKEN", ""),
            sentiment_backend=os.environ.get("SENTIMENT_BACKEND", "routed"),
            topic_backend=os.environ.get("TOPIC_BACKEND", "hybrid"),
            model_dir=Path(os.environ.get("MODEL_DIR", Path(__file__).resolve().parent.parent / "models")),
            encoder=os.environ.get("ENCODER_MODEL", DEFAULT_ENCODER),
        )
