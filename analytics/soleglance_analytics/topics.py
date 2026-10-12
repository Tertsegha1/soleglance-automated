"""Topic category classification for posts and news (trending dashboards).

Two zero-shot classifiers are provided, neither needing labelled data from
SoleGlance itself:

* KeywordClassifier: curated English / Roman Urdu / Urdu keyword lexicons.
* PrototypeClassifier: cosine similarity between the text embedding and
  the mean embedding of a few descriptive sentences per category.

Categories must stay in sync with app/Enums/Category.php.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Sequence

import numpy as np

from .text import content_tokens

OTHER = "other"

KEYWORDS: dict[str, set[str]] = {
    "politics": set("""
        election elections vote votes voting parliament senate minister ministers government govt president
        prime pm party parties opposition democracy policy policies law court supreme judge judiciary protest
        protests army military war ceasefire diplomat diplomacy treaty sanctions campaign coalition cabinet
        assembly constitution politics political politician corruption nab imf un nato rally
        hukumat siyasat siyasi intikhabat wazir wazeer adalat fauj jalsa
        حکومت سیاست سیاسی انتخابات وزیر عدالت فوج جلسہ پارلیمنٹ
    """.split()),
    "business": set("""
        economy economic market markets stock stocks shares bank banks banking inflation prices price rupee
        dollar trade trading exports imports investment investors company companies profit profits revenue
        budget tax taxes gdp growth oil petrol fuel business startup startups finance financial earnings
        mehngai karobar maeeshat qeemat qeematein
        معیشت مہنگائی کاروبار بجٹ ٹیکس ڈالر روپیہ قیمت پیٹرول
    """.split()),
    "sports": set("""
        cricket football soccer hockey tennis match matches tournament cup league team teams player players
        coach score scored goal goals wicket wickets innings batting bowling bowler batsman olympics
        champion champions championship final semifinal psl ipl fifa icc world series athlete stadium
        کرکٹ میچ ٹیم کھلاڑی فٹبال
    """.split()),
    "entertainment": set("""
        film films movie movies actor actress actors music song songs album singer concert drama dramas
        series season episode celebrity celebrities showbiz bollywood lollywood hollywood netflix tv award
        awards festival trailer box office star stars fashion
        gaana filmein
        فلم ڈرامہ گانا اداکار اداکارہ
    """.split()),
    "technology": set("""
        technology tech ai artificial intelligence software app apps google apple microsoft meta facebook
        twitter phone phones smartphone iphone android internet online digital data cyber hack hackers
        computer computers startup robot robots chip chips semiconductor space nasa satellite rocket
        science scientists research 5g crypto bitcoin blockchain
        ٹیکنالوجی انٹرنیٹ موبائل کمپیوٹر
    """.split()),
    "health": set("""
        health hospital hospitals doctor doctors patient patients disease diseases virus covid vaccine
        vaccines vaccination dengue polio cancer medicine medicines medical healthcare mental outbreak
        pandemic epidemic illness treatment surgery nutrition diet fitness
        bimari aspatal ilaj
        صحت ہسپتال ڈاکٹر بیماری ویکسین علاج
    """.split()),
    "weather": set("""
        weather rain rains rainfall monsoon flood floods flooding storm storms cyclone heatwave heat
        temperature temperatures snow snowfall fog smog drought forecast climate wind winds thunderstorm
        barish garmi sardi toofan selab
        موسم بارش گرمی سردی طوفان سیلاب دھند
    """.split()),
    "travel": set("""
        travel tourism tourist tourists flight flights airline airlines airport visa visas hotel hotels
        trip trips holiday holidays vacation destination destinations train trains railway pia
        passport journey tour tours
        safar sair
        سفر سیاحت پرواز ویزا
    """.split()),
}

PROTOTYPES: dict[str, list[str]] = {
    "politics": [
        "The government and opposition parties clashed in parliament over the new law.",
        "Voters head to the polls as the election campaign ends.",
        "The prime minister met foreign leaders to discuss the ceasefire and sanctions.",
    ],
    "business": [
        "Inflation rose again as fuel prices and the dollar exchange rate climbed.",
        "The stock market closed higher after strong company earnings.",
        "The central bank raised interest rates to control the economy.",
    ],
    "sports": [
        "The cricket team won the match by five wickets in the final over.",
        "The football club signed a new striker before the league season.",
        "The athlete won a gold medal at the championship.",
    ],
    "entertainment": [
        "The new film was a hit at the box office this weekend.",
        "The singer released a new album and announced a concert tour.",
        "Fans are excited about the latest episode of the television drama.",
    ],
    "technology": [
        "The company launched a new smartphone with artificial intelligence features.",
        "Researchers developed new software and computer chips.",
        "A cyber attack hacked thousands of online accounts.",
    ],
    "health": [
        "Hospitals reported a rise in dengue patients this week.",
        "Doctors recommend the new vaccine to prevent the disease.",
        "Health officials warned about the outbreak and urged treatment.",
    ],
    "weather": [
        "Heavy rain and floods are expected as the monsoon arrives.",
        "The heatwave pushed temperatures above forty five degrees.",
        "Forecasters warned of a storm with strong winds and snowfall.",
    ],
    "travel": [
        "Tourists flocked to the northern areas during the holidays.",
        "The airline added new international flights and visa rules eased.",
        "Hotels and travel destinations are fully booked this summer.",
    ],
}

CATEGORIES: tuple[str, ...] = tuple(KEYWORDS)


@dataclass(frozen=True)
class TopicPrediction:
    category: str
    confidence: float
    scores: dict[str, float]


def _from_scores(scores: np.ndarray, min_confidence: float) -> TopicPrediction:
    total = scores.sum()
    if total <= 0:
        return TopicPrediction(OTHER, 0.0, {c: 0.0 for c in CATEGORIES})
    probs = scores / total
    i = int(probs.argmax())
    cat = CATEGORIES[i] if probs[i] >= min_confidence else OTHER
    return TopicPrediction(cat, float(probs[i]), {c: float(p) for c, p in zip(CATEGORIES, probs)})


class KeywordClassifier:
    name = "keywords"

    def __init__(self, min_confidence: float = 0.0) -> None:
        self.min_confidence = min_confidence

    def scores(self, texts: Sequence[str]) -> np.ndarray:
        out = np.zeros((len(texts), len(CATEGORIES)))
        for r, text in enumerate(texts):
            toks = [t.lstrip("#") for t in content_tokens(text)]
            for c, cat in enumerate(CATEGORIES):
                out[r, c] = sum(1 for t in toks if t in KEYWORDS[cat])
        return out

    def predict(self, texts: Sequence[str]) -> list[TopicPrediction]:
        return [_from_scores(row, self.min_confidence) for row in self.scores(texts)]


class PrototypeClassifier:
    name = "prototypes"

    def __init__(self, encoder_name: str, temperature: float = 0.05, min_similarity: float = 0.15) -> None:
        from .sentiment.trained import encode

        self._encode = lambda texts: encode(encoder_name, texts)
        protos = [np.mean(self._encode(PROTOTYPES[c]), axis=0) for c in CATEGORIES]
        self.prototypes = np.vstack([p / np.linalg.norm(p) for p in protos])
        self.temperature = temperature
        self.min_similarity = min_similarity

    def probabilities(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> tuple[np.ndarray, np.ndarray]:
        X = embeddings if embeddings is not None else self._encode(list(texts))
        sims = X @ self.prototypes.T
        logits = sims / self.temperature
        logits -= logits.max(axis=1, keepdims=True)
        probs = np.exp(logits)
        return probs / probs.sum(axis=1, keepdims=True), sims

    def predict(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> list[TopicPrediction]:
        probs, sims = self.probabilities(texts, embeddings)
        out = []
        for p, s in zip(probs, sims):
            i = int(p.argmax())
            cat = CATEGORIES[i] if s[i] >= self.min_similarity else OTHER
            out.append(TopicPrediction(cat, float(p[i]), {c: float(v) for c, v in zip(CATEGORIES, p)}))
        return out


class HybridClassifier:
    """Keyword evidence when present, otherwise the embedding prototypes."""

    name = "hybrid"

    def __init__(self, encoder_name: str) -> None:
        self.keywords = KeywordClassifier()
        self.prototypes = PrototypeClassifier(encoder_name)

    def predict(self, texts: Sequence[str], embeddings: np.ndarray | None = None) -> list[TopicPrediction]:
        kw = self.keywords.scores(texts)
        probs, sims = self.prototypes.probabilities(texts, embeddings)
        out = []
        for k, p, s in zip(kw, probs, sims):
            # Each keyword hit counts as strong evidence; prototypes break ties and cover texts without hits.
            combined = p + k / max(1.0, k.sum()) * 1.5
            combined /= combined.sum()
            i = int(combined.argmax())
            confident = k.sum() > 0 or s[i] >= self.prototypes.min_similarity
            out.append(TopicPrediction(CATEGORIES[i] if confident else OTHER, float(combined[i]),
                                       {c: float(v) for c, v in zip(CATEGORIES, combined)}))
        return out
