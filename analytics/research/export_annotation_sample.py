"""Export texts for native-speaker health-mention annotation.

    python -m research.export_annotation_sample [--n 300] [--lang pcm]

See paper/health-annotation-guidelines.md. The health classifier never saw
these corpora, so all splits are pooled. The strata make the sample useful
for both precision and recall:

* lexicon:  every text containing a lexicon disease/symptom term (capped)
* adjacent: texts with general health words the lexicon does not cover
            (hospital, sick, drug, ...), to estimate what the lexicon misses
* random:   the rest, as ordinary negatives
* news_health / news_other (Urdu only): the Urdu tweet corpus has very few
            health texts, so health articles from the Bend-the-Truth news
            corpus are added as positives, with articles on other topics as
            hard negatives so that "news" does not stand in for "health".
            Articles are trimmed to the headline and opening sentences.

Annotators get <lang>.csv (row, text, label) in shuffled order. The stratum of
each row is written to <lang>_strata.csv for the analysis only; do not open it
while labelling.
"""

from __future__ import annotations

import argparse
from pathlib import Path

import pandas as pd

from soleglance_analytics.health import extract
from soleglance_analytics.text import tokenise

from . import datasets, health_data

OUT = Path(__file__).resolve().parents[2] / "paper" / "annotation"

ADJACENT = {
    "pcm": {"hospital", "sick", "clinic", "doctor", "drug", "drugs", "medicine", "nurse", "injection", "pain",
            "ill", "illness", "disease", "virus", "vaccine", "sickness", "treatment", "health", "dey sick", "belle"},
    "ur-Latn": {"hospital", "aspatal", "bimar", "bimari", "doctor", "dawai", "dawa", "ilaj", "dard", "marz",
                "sehat", "virus", "vaccine", "tabiyat", "mareez", "takleef"},
    "ur": {"ہسپتال", "بیمار", "بیماری", "ڈاکٹر", "دوا", "علاج", "درد", "مرض", "صحت", "مریض", "طبیعت"},
}
LOADERS = {"pcm": datasets.pidgin, "ur-Latn": datasets.roman_urdu, "ur": datasets.urdu}


NEWS_HEALTH, NEWS_OTHER = 60, 30


def trim(text: str, limit: int = 320) -> str:
    """Headline and opening sentences, cut at an Urdu full stop where possible."""
    text = " ".join(text.split())
    if len(text) <= limit:
        return text
    cut = text.rfind("۔", 0, limit)
    return text[: cut + 1] if cut > 80 else text[:limit] + "…"


def urdu_news() -> pd.DataFrame:
    d = health_data.urdu_fake()
    df = pd.concat([d[s] for s in d]).drop_duplicates("text")
    health = df[df["category"] == "hlth"].sample(n=NEWS_HEALTH, random_state=42)
    other = df[df["category"] != "hlth"].sample(n=NEWS_OTHER, random_state=42)
    out = pd.concat([health.assign(stratum="news_health"), other.assign(stratum="news_other")])
    return out.assign(text=out["text"].map(trim))[["text", "stratum"]]


def export(lang: str, n: int) -> None:
    d = LOADERS[lang]()
    df = pd.concat([d[s] for s in d])[["text"]].drop_duplicates("text").reset_index(drop=True)
    n = min(n, len(df))
    has_term = df["text"].map(lambda t: bool(extract(t).diseases or extract(t).symptoms))
    adjacent = df["text"].map(lambda t: bool(set(tokenise(t)) & ADJACENT[lang])) & ~has_term

    news = urdu_news() if lang == "ur" else pd.DataFrame(columns=["text", "stratum"])
    n_tweets = n - len(news)
    lex = df[has_term].sample(n=min(int(has_term.sum()), n_tweets // 2), random_state=42)
    adj = df[adjacent].sample(n=min(int(adjacent.sum()), (n_tweets - len(lex)) // 2), random_state=42)
    rest_pool = df.drop(lex.index).drop(adj.index)
    rnd = rest_pool.sample(n=n_tweets - len(lex) - len(adj), random_state=42)

    sample = pd.concat([lex.assign(stratum="lexicon"), adj.assign(stratum="adjacent"), rnd.assign(stratum="random"), news],
                       ignore_index=True)
    sample = sample.sample(frac=1, random_state=7).reset_index(drop=True)
    sample.index.name = "row"

    OUT.mkdir(parents=True, exist_ok=True)
    sample[["text"]].assign(label="").to_csv(OUT / f"{lang}.csv", encoding="utf-8-sig")
    sample[["stratum"]].to_csv(OUT / f"{lang}_strata.csv", encoding="utf-8")
    print(f"{lang}: {len(sample)} rows " + str(sample["stratum"].value_counts().to_dict()) + f" -> {OUT / f'{lang}.csv'}")


def main() -> None:
    p = argparse.ArgumentParser()
    p.add_argument("--n", type=int, default=300)
    p.add_argument("--lang", choices=list(LOADERS), action="append")
    args = p.parse_args()
    for lang in args.lang or list(LOADERS):
        export(lang, args.n)


if __name__ == "__main__":
    main()
