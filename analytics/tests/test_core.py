from datetime import datetime, timedelta, timezone

import numpy as np
import pytest

from soleglance_analytics import language
from soleglance_analytics.sentiment.base import from_proba
from soleglance_analytics.sentiment.lexicon import VaderBackend
from soleglance_analytics.text import content_tokens, normalise, tokenise
from soleglance_analytics.topics import KeywordClassifier
from soleglance_analytics.trends import Document, burst_score, candidate_terms, detect

NOW = datetime(2026, 10, 1, 12, tzinfo=timezone.utc)


def test_normalise_masks_urls_and_mentions():
    assert normalise("Hi @ali see https://x.y/z  now") == "Hi @user see http now"


def test_tokenise_keeps_hashtags_and_urdu():
    assert tokenise("#PSL final! میچ بہت اچھا") == ["#psl", "final", "میچ", "بہت", "اچھا"]


def test_content_tokens_drop_stopwords_in_all_three_languages():
    assert content_tokens("The match was great") == ["match", "great"]
    assert content_tokens("yeh match bohat acha hai") == ["match", "bohat", "acha"]
    assert "ہے" not in content_tokens("موسم اچھا ہے")


@pytest.mark.parametrize("text,expected", [
    ("The new budget looks promising for small businesses", "en"),
    ("yeh budget bohat acha hai, mehngai kam hogi", "ur-Latn"),
    ("aaj ka match bilkul bakwas tha", "ur-Latn"),
    ("بارش کے بعد موسم بہت خوشگوار ہو گیا ہے", "ur"),
    ("Is it raining in Lahore today?", "en"),
    ("", "en"),
])
def test_lexicon_language_detection(text, expected):
    assert language.detect_lexicon(text) == expected


def test_from_proba_orders_and_normalises():
    [p] = from_proba(np.array([[1.0, 1.0, 2.0]]))
    assert p.label == "positive" and p.score == pytest.approx(0.5)
    assert sum(p.scores.values()) == pytest.approx(1.0)


def test_vader_baseline_polarity():
    vader = VaderBackend()
    good, bad, neutral = vader.predict(["I love this, it is wonderful!", "This is terrible and sad.", "The meeting is at 3pm."])
    assert (good.label, bad.label, neutral.label) == ("positive", "negative", "neutral")


def test_keyword_topics():
    clf = KeywordClassifier()
    preds = clf.predict(["Pakistan won the cricket match by 5 wickets", "Inflation and petrol prices rise again", "hello there"])
    assert [p.category for p in preds] == ["sports", "business", "other"]


def test_burst_score_is_poisson_z_with_smoothing():
    assert burst_score(10, 0) == pytest.approx(10.0)
    assert burst_score(10, 3) == pytest.approx(7 / 2)


def test_candidate_terms_fold_hashtags_and_add_bigrams():
    assert candidate_terms("#Budget debate tonight") >= {"budget", "debate", "budget debate"}


def _docs(term_text: str, current: int, per_history_window: int, background: list[str]):
    docs = []
    for w in range(1, 8):
        for i in range(per_history_window):
            docs.append(Document(f"h{w}-{i}", term_text, NOW - timedelta(hours=24 * w + 1 + i % 20)))
    for i in range(current):
        docs.append(Document(f"c{i}", term_text, NOW - timedelta(hours=1 + i % 20), "negative", 0.8, "politics"))
    for i, text in enumerate(background):
        for w in range(8):
            docs.append(Document(f"b{w}-{i}", text, NOW - timedelta(hours=24 * w + 2)))
    return docs


def test_detect_ranks_bursting_phrase_over_constantly_common_words():
    background = ["weather today is nice", "weather update for tomorrow"] * 5
    docs = _docs("Budget debate in parliament", current=12, per_history_window=1, background=background)
    trends = detect(docs, NOW, min_mentions=3, limit=5)
    terms = [t.term for t in trends]

    assert "weather" not in terms  # common every day, not bursting
    top = trends[0]
    assert top.term in {"budget debate", "debate parliament"}
    assert top.mentions == 12
    assert top.baseline == pytest.approx(1.0)
    assert top.sentiment["negative"] == 12 and top.sentiment["mean"] == pytest.approx(-0.8)
    assert top.category == "politics"
    # Unigrams fully explained by the phrase are not repeated.
    assert "budget" not in terms


def test_detect_ignores_documents_outside_the_horizon_and_future():
    docs = [Document(str(i), "flood warning", NOW + timedelta(hours=1)) for i in range(5)]
    docs += [Document(f"old{i}", "flood warning", NOW - timedelta(days=30)) for i in range(5)]
    assert detect(docs, NOW, min_mentions=1) == []


def test_min_mentions_threshold():
    docs = [Document(str(i), "rare topic", NOW - timedelta(hours=1)) for i in range(2)]
    assert detect(docs, NOW, min_mentions=3) == []
    assert detect(docs, NOW, min_mentions=2)
