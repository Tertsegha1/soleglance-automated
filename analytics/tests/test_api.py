import importlib
from datetime import datetime, timedelta, timezone

import pytest
from fastapi.testclient import TestClient


@pytest.fixture()
def client(monkeypatch, tmp_path):
    # Lightweight configuration: lexicon sentiment and keyword topics, no model downloads.
    monkeypatch.setenv("ANALYTICS_TOKEN", "test-token")
    monkeypatch.setenv("SENTIMENT_BACKEND", "vader")
    monkeypatch.setenv("TOPIC_BACKEND", "keywords")
    monkeypatch.setenv("MODEL_DIR", str(tmp_path))
    import soleglance_analytics.app as app_module
    import soleglance_analytics.sentiment as sentiment

    sentiment.get_backend.cache_clear()
    app_module = importlib.reload(app_module)
    return TestClient(app_module.app)


AUTH = {"Authorization": "Bearer test-token"}


def test_health(client):
    body = client.get("/health").json()
    assert body["status"] == "ok" and body["sentiment_model"] == "vader" and body["topic_model"] == "keywords"


def test_token_is_required(client):
    assert client.post("/v1/sentiment", json={"texts": ["hi"]}).status_code == 401
    assert client.post("/v1/sentiment", json={"texts": ["hi"]}, headers={"Authorization": "Bearer nope"}).status_code == 401


def test_sentiment_endpoint(client):
    r = client.post("/v1/sentiment", json={"texts": ["What a wonderful day!", "yeh bohat bura hai"]}, headers=AUTH)
    assert r.status_code == 200
    body = r.json()
    assert body["model"] == "vader"
    assert body["results"][0]["label"] == "positive"
    assert body["results"][0]["language"] == "en"
    assert body["results"][1]["language"] == "ur-Latn"
    assert set(body["results"][0]["scores"]) == {"negative", "neutral", "positive"}


def test_batch_limits(client):
    assert client.post("/v1/sentiment", json={"texts": []}, headers=AUTH).status_code == 422
    assert client.post("/v1/sentiment", json={"texts": ["x"] * 257}, headers=AUTH).status_code == 422


def test_topics_endpoint(client):
    r = client.post("/v1/topics", json={"texts": ["Heavy rain and floods expected in Karachi"]}, headers=AUTH)
    assert r.json()["results"][0]["category"] == "weather"


def test_trends_endpoint(client):
    now = datetime(2026, 10, 1, tzinfo=timezone.utc)
    docs = [{"id": f"post:{i}", "text": "Petrol price hike announced", "created_at": (now - timedelta(hours=2)).isoformat(),
             "sentiment": "negative", "sentiment_score": 0.9} for i in range(6)]
    docs += [{"id": f"article:{i}", "text": "Weather is pleasant", "created_at": (now - timedelta(days=d, hours=1)).isoformat()}
             for i, d in enumerate(range(8))]
    r = client.post("/v1/trends", json={"documents": docs, "now": now.isoformat(), "min_mentions": 3}, headers=AUTH)
    assert r.status_code == 200
    trends = r.json()["trends"]
    assert trends and trends[0]["mentions"] == 6
    assert trends[0]["sentiment"]["negative"] == 6
    assert trends[0]["document_ids"][0].startswith("post:")
    assert all("weather" not in t["term"] for t in trends)
