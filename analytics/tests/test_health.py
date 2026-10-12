import importlib
import json

import numpy as np
import pytest
from fastapi.testclient import TestClient

from soleglance_analytics import language, mlops
from soleglance_analytics.health import analyse, extract
from soleglance_analytics.health.models import HealthModels
from soleglance_analytics.health.surveillance import detect, statistic


@pytest.mark.parametrize("text,diseases,symptoms,regions", [
    ("Lassa fever don kill 3 people for Owo, Ondo State", ["lassa_fever"], [], ["NG-ON"]),
    ("My pikin body dey hot and belle dey run since yesterday", [], ["fever", "diarrhoea"], []),
    ("Karachi mein dengue ke mareezon ki tadaad barh gayi", ["dengue"], [], ["PK-SD"]),
    ("کراچی میں ڈینگی کے مریضوں کی تعداد بڑھ گئی", ["dengue"], [], ["PK-SD"]),
    ("Cholera outbreak in Bauchi: 20 deaths reported", ["cholera"], [], ["NG-BA"]),
    ("This traffic in Lagos is killing me", [], [], ["NG-LA"]),
])
def test_extraction_in_four_languages(text, diseases, symptoms, regions):
    m = extract(text)
    assert m.diseases == diseases and m.symptoms == symptoms and m.regions == regions


def test_longest_match_wins():
    assert extract("lassa fever").diseases == ["lassa_fever"]
    assert extract("lassa fever").symptoms == []  # "fever" is part of the disease name here


def test_ears_c2_uses_guard_band_and_floors_sd():
    x = [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 12]
    stat = statistic(x, "c2", baseline=7, guard=2)
    assert np.isnan(stat[:9]).all()
    assert stat[-1] == pytest.approx(10.0)  # (12-2)/max(0, 1)
    assert detect(x, "c2", min_count=3)[0].start == 10


def test_ears_c3_accumulates_recent_c2():
    x = [1] * 10 + [5, 6, 7]
    assert detect(x, "c3", min_count=1)


def test_flat_series_raises_nothing():
    assert detect([3] * 30, "c1") == []
    assert detect([0] * 30, "poisson", min_count=0) == []


def test_rule_based_fallback_without_models(tmp_path):
    r = analyse(["Cholera outbreak in Bauchi, 20 deaths", "I love jollof rice", "body dey hot, plenty cases for clinic"], tmp_path)
    assert [x.is_health for x in r] == [True, False, True]
    assert r[0].countries == ["NG"] and r[0].misinformation_score is None


def test_pidgin_lexicon_identifier():
    assert language.detect_lexicon("Abeg wetin dey happen for Lagos sef") == "pcm"
    assert language.detect_lexicon("The weather in Lagos is nice today") == "en"


def test_feedback_keeps_latest_label(tmp_path):
    mlops.append_feedback(tmp_path, [{"task": "sentiment", "text": "na wa o", "label": "neutral"}])
    mlops.append_feedback(tmp_path, [{"task": "sentiment", "text": "na wa o", "label": "negative"},
                                     {"task": "bogus", "text": "x", "label": "y"}])
    rows = mlops.load_feedback(tmp_path, "sentiment")
    assert len(rows) == 1 and rows[0]["label"] == "negative"


def test_registry_rollback(tmp_path):
    (tmp_path / "sentiment_tfidf.joblib").write_bytes(b"v2")
    (tmp_path / "sentiment_tfidf.v1.joblib").write_bytes(b"v1")
    reg = {"sentiment_tfidf": {"active": "v2", "versions": [
        {"version": "v1", "file": "sentiment_tfidf.v1.joblib", "status": "retired"},
        {"version": "v2", "file": "sentiment_tfidf.v2.joblib", "status": "active"}]}}
    mlops.save_registry(tmp_path, reg)
    mlops.rollback(tmp_path, "sentiment_tfidf", "v1")
    assert (tmp_path / "sentiment_tfidf.joblib").read_bytes() == b"v1"
    assert mlops.load_registry(tmp_path)["sentiment_tfidf"]["active"] == "v1"


def test_psi_detects_shift():
    ref = {"en": 900, "pcm": 100}
    assert mlops.psi(ref, {"en": 890, "pcm": 110}) < 0.05
    assert mlops.psi(ref, {"en": 300, "pcm": 700}) > 0.2


@pytest.fixture()
def client(monkeypatch, tmp_path):
    monkeypatch.setenv("ANALYTICS_TOKEN", "t")
    monkeypatch.setenv("SENTIMENT_BACKEND", "vader")
    monkeypatch.setenv("TOPIC_BACKEND", "keywords")
    monkeypatch.setenv("MODEL_DIR", str(tmp_path))
    import soleglance_analytics.app as app_module
    import soleglance_analytics.sentiment as sentiment

    sentiment.get_backend.cache_clear()
    return TestClient(importlib.reload(app_module).app), tmp_path


H = {"Authorization": "Bearer t"}


def test_health_endpoints(client):
    c, model_dir = client
    r = c.post("/v1/health/analyse", json={"texts": ["Lassa fever cases rising in Edo"]}, headers=H).json()
    assert r["results"][0]["is_health"] and r["results"][0]["regions"] == ["NG-ED"]

    series = [{"key": "cholera|NG", "values": [1] * 20 + [9]}, {"key": "dengue|PK", "values": [2] * 21}]
    alerts = c.post("/v1/health/signals", json={"series": series, "min_count": 3}, headers=H).json()["alerts"]
    assert [a["key"] for a in alerts] == ["cholera|NG"]


def test_feedback_and_metrics_endpoints(client):
    c, model_dir = client
    r = c.post("/v1/feedback", json={"items": [{"task": "sentiment", "text": "abeg this one sweet", "label": "positive"}]}, headers=H)
    assert r.json() == {"stored": 1}
    stored = json.loads((model_dir / "feedback" / "feedback.jsonl").read_text(encoding="utf-8"))
    assert stored["language"] in ("pcm", "en")

    c.post("/v1/sentiment", json={"texts": ["good", "bad"]}, headers=H)
    m = c.get("/v1/admin/metrics", headers=H).json()
    assert m["monitor"]["observed"] == 2
    assert c.get("/v1/admin/metrics").status_code == 401


def test_communicable_names_count_without_classifier_but_figurative_terms_need_it():
    from soleglance_analytics.health import decide

    assert decide(extract("Karachi mein dengue ke cases"), score=0.05)      # communicable: accepted
    assert not decide(extract("this cancer of corruption"), score=0.05)    # often figurative: needs classifier
    assert decide(extract("my mother has cancer"), score=0.9)
    assert not decide(extract("I love jollof rice"), score=0.99)           # no lexicon evidence at all


def test_misinformation_is_only_scored_in_validated_languages(tmp_path):
    import joblib

    joblib.dump({"name": "dummy", "features": "tfidf", "estimator": _Constant(), "positive": "false"},
                tmp_path / "misinformation.joblib")
    r = analyse(["Drinking hot water cures cholera, share this", "Karachi mein dengue ke cases barh gaye"], tmp_path)
    assert r[0].misinformation_score is not None          # English: scored
    assert r[1].misinformation_score is None               # Roman Urdu: not validated, not scored


class _Constant:
    classes_ = ["false", "true"]

    def predict_proba(self, X):
        import numpy as np

        return np.tile([0.9, 0.1], (len(X), 1))


def test_model_fetcher_verifies_checksum_and_never_overwrites(tmp_path, monkeypatch):
    import hashlib
    import io
    import zipfile

    from soleglance_analytics import fetch_models

    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as z:
        z.writestr("routing.json", "{}")
        z.writestr("../evil.txt", "nope")
    data = buf.getvalue()

    class Resp(io.BytesIO):
        def __enter__(self):
            return self

        def __exit__(self, *a):
            return False

    monkeypatch.setattr(fetch_models.urllib.request, "urlopen", lambda *a, **k: Resp(data))
    with pytest.raises(RuntimeError):
        fetch_models.fetch(tmp_path, "http://x", "0" * 64)

    (tmp_path / "routing.json").write_text('{"keep": true}')
    written = fetch_models.fetch(tmp_path, "http://x", hashlib.sha256(data).hexdigest())
    assert written == ["evil.txt"] and (tmp_path / "evil.txt").exists()        # flattened into model_dir
    assert not (tmp_path.parent / "evil.txt").exists()                           # never outside it
    assert (tmp_path / "routing.json").read_text() == '{"keep": true}'          # existing file kept
