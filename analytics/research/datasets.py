"""Public benchmark datasets used to train and evaluate SoleGlance's models.

Nothing here is redistributed; each loader downloads from the original
source into research/cache/ (git-ignored). Check each licence before reuse.

| key | language | source | licence / terms |
|-----|----------|--------|-----------------|
| en  | English  | TweetEval sentiment = SemEval-2017 Task 4A (Rosenthal et al. 2017; Barbieri et al. 2020), HF `cardiffnlp/tweet_eval` | Twitter ToS / research use |
| ru  | Roman Urdu | Roman Urdu Data Set (Sharf 2018), UCI ML Repository #458 | CC BY 4.0 |
| pcm | Nigerian Pidgin | AfriSenti-SemEval 2023 Task 12 (Muhammad et al. 2023), built on NaijaSenti (Muhammad et al. 2022), official splits | CC BY 4.0 |
| ur  | Urdu     | Urdu Sentiment Corpus v1 (Khan et al. 2020), github.com/MuhammadYaseenKhan/Urdu-Sentiment-Corpus | as published by the authors; research use |
| ag  | English news topics | AG News (Zhang et al. 2015), HF `fancyzhx/ag_news` | non-commercial research use |
"""

from __future__ import annotations

import io
import unicodedata
import zipfile
from pathlib import Path

import pandas as pd
import requests
from sklearn.model_selection import train_test_split

CACHE = Path(__file__).resolve().parent / "cache"
SEED = 42
LABELS = ["negative", "neutral", "positive"]

RU_URL = "https://archive.ics.uci.edu/static/public/458/roman+urdu+data+set.zip"
AFRISENTI_PCM = "https://raw.githubusercontent.com/afrisenti-semeval/afrisent-semeval-2023/main/data/pcm/{split}.tsv"
USC_URL = "https://raw.githubusercontent.com/MuhammadYaseenKhan/Urdu-Sentiment-Corpus/master/urdu-sentiment-corpus-v1.tsv"


def _download(url: str, name: str) -> bytes:
    CACHE.mkdir(parents=True, exist_ok=True)
    path = CACHE / name
    if not path.exists():
        r = requests.get(url, timeout=120, headers={"User-Agent": "SoleGlance-research/2.0"})
        r.raise_for_status()
        path.write_bytes(r.content)
    return path.read_bytes()


def _key(text: str) -> str:
    return " ".join(unicodedata.normalize("NFKC", str(text)).lower().split())


def _dedupe(df: pd.DataFrame) -> pd.DataFrame:
    """Drop exact duplicates (after normalisation) and texts with conflicting labels.

    Duplicates across train/test splits would inflate scores.
    """
    df = df.assign(_k=df["text"].map(_key))
    df = df[df["_k"].str.len() > 0]
    labels_per_text = df.groupby("_k")["label"].nunique()
    conflicting = labels_per_text[labels_per_text > 1].index
    df = df[~df["_k"].isin(conflicting)].drop_duplicates("_k")
    return df.drop(columns="_k").reset_index(drop=True)


def _split(df: pd.DataFrame, val: float, test: float) -> dict[str, pd.DataFrame]:
    train, rest = train_test_split(df, test_size=val + test, stratify=df["label"], random_state=SEED)
    valid, test_df = train_test_split(rest, test_size=test / (val + test), stratify=rest["label"], random_state=SEED)
    return {"train": train.reset_index(drop=True), "validation": valid.reset_index(drop=True), "test": test_df.reset_index(drop=True)}


def english() -> dict[str, pd.DataFrame]:
    from datasets import load_dataset

    ds = load_dataset("cardiffnlp/tweet_eval", "sentiment", cache_dir=str(CACHE / "hf"))
    out = {}
    for split in ("train", "validation", "test"):
        df = ds[split].to_pandas().rename(columns={"label": "y"})
        df["label"] = df["y"].map(dict(enumerate(LABELS)))
        out[split] = df[["text", "label"]].assign(lang="en")
    # Official splits; remove any test texts that also occur in train/validation.
    seen = set(pd.concat([out["train"], out["validation"]])["text"].map(_key))
    out["test"] = out["test"][~out["test"]["text"].map(_key).isin(seen)].reset_index(drop=True)
    return out


def roman_urdu() -> dict[str, pd.DataFrame]:
    raw = _download(RU_URL, "roman_urdu.zip")
    with zipfile.ZipFile(io.BytesIO(raw)) as z:
        name = next(n for n in z.namelist() if n.lower().endswith(".csv"))
        df = pd.read_csv(z.open(name), header=None, encoding="utf-8", on_bad_lines="skip")
    df = df.iloc[:, :2]
    df.columns = ["text", "raw_label"]
    df["label"] = df["raw_label"].astype(str).str.strip().str.lower().map(
        {"positive": "positive", "negative": "negative", "neutral": "neutral", "neative": "negative"}
    )
    df = df.dropna(subset=["text", "label"])
    df = _dedupe(df[["text", "label"]])
    return {k: v.assign(lang="ur-Latn") for k, v in _split(df, 0.1, 0.1).items()}


def urdu() -> dict[str, pd.DataFrame]:
    raw = _download(USC_URL, "urdu_sentiment_corpus_v1.tsv")
    df = pd.read_csv(io.BytesIO(raw), sep="\t", encoding="utf-8")
    df.columns = [c.strip().lower() for c in df.columns]
    text_col = next(c for c in df.columns if "tweet" in c or "text" in c)
    label_col = next(c for c in df.columns if "class" in c or "label" in c)
    df = df.rename(columns={text_col: "text"})
    df["label"] = df[label_col].astype(str).str.strip().str.upper().map({"P": "positive", "N": "negative", "O": "neutral"})
    df = _dedupe(df.dropna(subset=["text", "label"])[["text", "label"]])
    # USC has only ~20 "objective" tweets, too few to learn or evaluate a
    # neutral class, so Urdu is treated as binary (positive vs negative).
    df = df[df["label"] != "neutral"].reset_index(drop=True)
    # Small corpus: keep a larger test share for a usable estimate.
    return {k: v.assign(lang="ur") for k, v in _split(df, 0.1, 0.2).items()}


def pidgin() -> dict[str, pd.DataFrame]:
    out = {}
    for split, name in (("train", "train"), ("validation", "dev"), ("test", "test")):
        raw = _download(AFRISENTI_PCM.format(split=name), f"afrisenti_pcm_{name}.tsv")
        df = pd.read_csv(io.BytesIO(raw), sep="\t", encoding="utf-8", encoding_errors="replace", quoting=3)
        df = df.rename(columns={"tweet": "text"})[["text", "label"]].dropna()
        df["label"] = df["label"].str.strip().str.lower()
        out[split] = df[df["label"].isin(LABELS)].reset_index(drop=True)
    # Official splits; drop test and validation texts that also occur in training.
    seen = set(out["train"]["text"].map(_key))
    for split in ("validation", "test"):
        out[split] = out[split][~out[split]["text"].map(_key).isin(seen)].reset_index(drop=True)
    out["train"] = _dedupe(out["train"])
    return {k: v.assign(lang="pcm") for k, v in out.items()}


def all_sentiment() -> dict[str, dict[str, pd.DataFrame]]:
    return {"en": english(), "ur-Latn": roman_urdu(), "ur": urdu(), "pcm": pidgin()}


def ag_news() -> pd.DataFrame:
    """AG News test split mapped onto SoleGlance categories."""
    from datasets import load_dataset

    ds = load_dataset("fancyzhx/ag_news", split="test", cache_dir=str(CACHE / "hf"))
    df = ds.to_pandas()
    df["category"] = df["label"].map({0: "politics", 1: "sports", 2: "business", 3: "technology"})
    return df[["text", "category"]]


def describe(data: dict[str, dict[str, pd.DataFrame]]) -> pd.DataFrame:
    rows = []
    for lang, splits in data.items():
        for split, df in splits.items():
            counts = df["label"].value_counts()
            rows.append({"lang": lang, "split": split, "n": len(df), **{l: int(counts.get(l, 0)) for l in LABELS}})
    return pd.DataFrame(rows)
