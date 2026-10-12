"""Download the trained model bundle on first start if it is missing.

    python -m soleglance_analytics.fetch_models

Fresh clones do not contain trained models (they are too large for git).
This fetches the release bundle, verifies its SHA-256 and unpacks it into
MODEL_DIR. Existing files are never overwritten, so models retrained by
the platform (soleglance_analytics.mlops) are kept across restarts.
Set MODELS_URL/MODELS_SHA256 to use another bundle, or MODELS_URL="" to skip.
"""

from __future__ import annotations

import hashlib
import io
import os
import sys
import urllib.request
import zipfile
from pathlib import Path

from .config import Settings

DEFAULT_URL = "https://github.com/Tertsegha1/soleglance-automated/releases/download/models-v2.0.0/soleglance-models-2.0.0.zip"
DEFAULT_SHA256 = "7d49fe971914825a0fee424ac8120833198fa80c72c18bf349212e441857d6fa"
REQUIRED = (
    "langid.joblib", "routing.json", "reference.json", "sentiment_tfidf.joblib", "sentiment_embedding.joblib",
    "health_mention.joblib", "misinformation.joblib",
)


def missing(model_dir: Path) -> list[str]:
    return [f for f in REQUIRED if not (model_dir / f).exists()]


def fetch(model_dir: Path, url: str, sha256: str) -> list[str]:
    req = urllib.request.Request(url, headers={"User-Agent": "SoleGlance-analytics/2.0"})
    with urllib.request.urlopen(req, timeout=300) as r:
        data = r.read()
    digest = hashlib.sha256(data).hexdigest()
    if sha256 and digest != sha256:
        raise RuntimeError(f"Model bundle checksum mismatch: expected {sha256}, got {digest}")
    model_dir.mkdir(parents=True, exist_ok=True)
    written = []
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        for name in z.namelist():
            target = model_dir / Path(name).name  # flatten; never write outside model_dir
            if not target.exists():
                target.write_bytes(z.read(name))
                written.append(target.name)
    return written


def main() -> int:
    model_dir = Settings.from_env().model_dir
    need = missing(model_dir)
    if not need:
        print("models present", flush=True)
        return 0
    url = os.environ.get("MODELS_URL", DEFAULT_URL)
    if not url:
        print(f"models missing ({', '.join(need)}) and MODELS_URL is empty; the service will fall back to simpler models",
              flush=True)
        return 0
    try:
        written = fetch(model_dir, url, os.environ.get("MODELS_SHA256", DEFAULT_SHA256))
        print(f"downloaded {len(written)} model files: {', '.join(written)}", flush=True)
    except Exception as e:  # noqa: BLE001 - start anyway; the service degrades gracefully
        print(f"model download failed: {e}; the service will fall back to simpler models", file=sys.stderr, flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
