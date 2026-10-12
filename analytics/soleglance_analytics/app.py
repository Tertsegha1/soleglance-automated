"""HTTP API used by the Laravel application.

Run:  uvicorn soleglance_analytics.app:app --port 8001
"""

from __future__ import annotations

import hmac
import json
import logging
import threading
from contextlib import asynccontextmanager
from datetime import datetime, timezone
from functools import lru_cache

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field, field_validator

from . import __version__, language, mlops
from . import health as health_module
from .config import Settings
from .health import surveillance
from .health.models import HealthModels
from .sentiment import get_backend
from .topics import HybridClassifier, KeywordClassifier, PrototypeClassifier
from .trends import Document, detect

log = logging.getLogger("soleglance_analytics")
settings = Settings.from_env()


@asynccontextmanager
async def lifespan(_: FastAPI):
    # Load (and on first run download) models before accepting traffic, so the
    # first real request does not time out while weights are fetched.
    sentiment_backend()
    topic_classifier()
    health_models()
    yield


app = FastAPI(title="SoleGlance analytics", version=__version__, lifespan=lifespan)


def require_token(authorization: str | None = Header(default=None)) -> None:
    if not settings.token:
        return  # auth disabled (local development only)
    expected = f"Bearer {settings.token}"
    if not authorization or not hmac.compare_digest(authorization, expected):
        raise HTTPException(status_code=401, detail="Invalid or missing token")


@lru_cache(maxsize=1)
def sentiment_backend():
    return get_backend(settings.sentiment_backend, str(settings.model_dir))


monitor = mlops.Monitor()
_retrain_state: dict = {"running": False, "last": None}


@lru_cache(maxsize=1)
def health_models() -> HealthModels:
    return HealthModels(settings.model_dir)


def reference_distribution() -> dict | None:
    path = settings.model_dir / "reference.json"
    return json.loads(path.read_text(encoding="utf-8")) if path.exists() else None


@lru_cache(maxsize=1)
def topic_classifier():
    try:
        if settings.topic_backend == "hybrid":
            return HybridClassifier(settings.encoder)
        if settings.topic_backend == "prototypes":
            return PrototypeClassifier(settings.encoder)
    except (ImportError, OSError) as e:
        log.warning("Embedding topic classifier unavailable (%s); using keywords", e)
    return KeywordClassifier()


class TextsIn(BaseModel):
    texts: list[str] = Field(min_length=1, max_length=settings.max_batch)

    @field_validator("texts")
    @classmethod
    def truncate(cls, v: list[str]) -> list[str]:
        return [t[: settings.max_text_chars] for t in v]


class SentimentOut(BaseModel):
    label: str
    score: float
    scores: dict[str, float]
    language: str


class DocumentIn(BaseModel):
    id: str = Field(max_length=64)
    text: str
    created_at: datetime
    sentiment: str | None = None
    sentiment_score: float | None = None
    category: str | None = None


class TrendsIn(BaseModel):
    documents: list[DocumentIn] = Field(max_length=settings.max_documents)
    now: datetime | None = None
    window_hours: int = Field(default=24, ge=1, le=24 * 30)
    history_windows: int = Field(default=7, ge=1, le=60)
    min_mentions: int = Field(default=3, ge=1)
    limit: int = Field(default=30, ge=1, le=200)


def _utc(dt: datetime) -> datetime:
    return dt.replace(tzinfo=timezone.utc) if dt.tzinfo is None else dt.astimezone(timezone.utc)


@app.get("/health")
def health() -> dict:
    return {
        "status": "ok",
        "version": __version__,
        "sentiment_model": sentiment_backend().name,
        "topic_model": topic_classifier().name,
        "health_models": health_models().names,
    }


@app.post("/v1/sentiment", dependencies=[Depends(require_token)])
def sentiment(body: TextsIn) -> dict:
    backend = sentiment_backend()
    preds = backend.predict(body.texts)
    results = []
    for p, t in zip(preds, body.texts):
        lang = language.detect(t)
        monitor.observe(lang, p.label, p.score)
        results.append(SentimentOut(label=p.label, score=round(p.score, 4),
                                    scores={k: round(v, 4) for k, v in p.scores.items()}, language=lang))
    return {"model": backend.name, "results": results}


@app.post("/v1/topics", dependencies=[Depends(require_token)])
def topics(body: TextsIn) -> dict:
    clf = topic_classifier()
    return {
        "model": clf.name,
        "results": [{"category": p.category, "confidence": round(p.confidence, 4)} for p in clf.predict(body.texts)],
    }


@app.post("/v1/trends", dependencies=[Depends(require_token)])
def trends(body: TrendsIn) -> dict:
    now = _utc(body.now or datetime.now(timezone.utc))
    docs = [Document(d.id, d.text, _utc(d.created_at), d.sentiment, d.sentiment_score, d.category) for d in body.documents]
    found = detect(docs, now, body.window_hours, body.history_windows, body.min_mentions, body.limit)
    return {"trends": [t.__dict__ for t in found]}


# --- Health ------------------------------------------------------------------------------------

class SeriesIn(BaseModel):
    key: str = Field(max_length=100)
    values: list[float] = Field(max_length=3660)


class SignalsIn(BaseModel):
    series: list[SeriesIn] = Field(max_length=2000)
    method: str = Field(default="c2", pattern="^(c1|c2|c3|poisson)$")
    baseline: int = Field(default=7, ge=3, le=56)
    guard: int | None = Field(default=None, ge=0, le=14)
    threshold: float | None = None
    min_count: float = Field(default=3, ge=0)
    recent: int = Field(default=3, ge=1, le=60)


@app.post("/v1/health/analyse", dependencies=[Depends(require_token)])
def health_analyse(body: TextsIn) -> dict:
    results = health_module.analyse(body.texts, settings.model_dir, health_models())
    return {"models": health_models().names, "results": [r.__dict__ for r in results]}


@app.post("/v1/health/signals", dependencies=[Depends(require_token)])
def health_signals(body: SignalsIn) -> dict:
    """EARS aberration detection per series; returns alerts that touch the most recent steps."""
    out = []
    for s in body.series:
        stat = surveillance.statistic(s.values, body.method, body.baseline, body.guard)
        alerts = surveillance.detect(s.values, body.method, body.threshold, body.min_count, body.baseline, body.guard)
        n = len(s.values)
        for a in alerts:
            if a.end >= n - body.recent:
                out.append({"key": s.key, "start": a.start, "end": a.end, "peak": a.peak,
                            "statistic": round(a.statistic, 3), "count": a.count,
                            "latest_statistic": None if stat[-1] != stat[-1] else round(float(stat[-1]), 3)})
    return {"method": body.method, "alerts": out}


# --- Feedback and model operations ---------------------------------------------------------------

class FeedbackItem(BaseModel):
    task: str = Field(pattern="^(sentiment|health_mention|misinformation)$")
    text: str = Field(max_length=5000)
    label: str = Field(max_length=20)
    language: str | None = Field(default=None, max_length=12)
    source: str | None = Field(default=None, max_length=40)


class FeedbackIn(BaseModel):
    items: list[FeedbackItem] = Field(min_length=1, max_length=1000)


@app.post("/v1/feedback", dependencies=[Depends(require_token)])
def feedback(body: FeedbackIn) -> dict:
    items = [i.model_dump() for i in body.items]
    for i in items:
        i["language"] = i["language"] or language.detect(i["text"])
    return {"stored": mlops.append_feedback(settings.model_dir, items)}


@app.get("/v1/admin/metrics", dependencies=[Depends(require_token)])
def admin_metrics() -> dict:
    return {"monitor": monitor.report(reference_distribution()), "retrain": _retrain_state}


@app.get("/v1/admin/registry", dependencies=[Depends(require_token)])
def admin_registry() -> dict:
    return mlops.load_registry(settings.model_dir)


@app.post("/v1/admin/retrain", status_code=202, dependencies=[Depends(require_token)])
def admin_retrain() -> dict:
    """Start gated retraining of the sentiment n-gram model in the background."""
    if _retrain_state["running"]:
        return {"started": False, "reason": "already running"}

    def run():
        _retrain_state["running"] = True
        try:
            _retrain_state["last"] = mlops.retrain_sentiment_tfidf(settings.model_dir)
            sentiment_backend.cache_clear()
            from .sentiment import get_backend as gb

            gb.cache_clear()
        except Exception as e:  # noqa: BLE001 - reported to the caller via /metrics
            _retrain_state["last"] = {"error": str(e)}
        finally:
            _retrain_state["running"] = False
            _retrain_state["finished_at"] = datetime.now(timezone.utc).isoformat(timespec="seconds")

    threading.Thread(target=run, daemon=True).start()
    return {"started": True}
