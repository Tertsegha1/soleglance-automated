"""Public datasets for the health module. Downloaded to research/cache/health/.

| key | task | source | licence / terms |
|-----|------|--------|-----------------|
| rhmd | health-mention detection (en) | RHMD, Naseem et al. (WWW 2022), github.com/usmaann/RHMD-Health-Mention-Dataset | research use, cite |
| constraint | COVID-19 misinformation (en) | Constraint@AAAI 2021 (Patwa et al.), github.com/diptamath/covid_fake_news | MIT (repository) |
| urdu_fake | fake news incl. health (ur) | Bend the Truth (Amjad et al. 2020), github.com/MaazAmjad/Datasets-for-Urdu-news | research use, cite |
| lassa | weekly confirmed Lassa fever cases, Nigeria 2020-2025 | NCDC situation reports compiled by E. Niyi, github.com/EmmanuelNiyi/ncdc-lassa-fever-timeseries-2020-2025 | CC BY 4.0 (data) / MIT (code) |
| dengue | dengue case counts, Pakistan | OpenDengue V1.3 (Clarke et al. 2024, Sci. Data) | CC BY 4.0 |
| wiki | daily Wikipedia pageviews | Wikimedia REST API | CC0 |
"""

from __future__ import annotations

import io
import json
import time
import zipfile
from datetime import date
from pathlib import Path
from urllib.parse import quote

import pandas as pd
import requests
from sklearn.model_selection import train_test_split

from .datasets import _dedupe, _key

CACHE = Path(__file__).resolve().parent / "cache" / "health"
SEED = 42
UA = {"User-Agent": "SoleGlance-research/2.0 (open-source research; https://github.com/Tertsegha1/soleglance-automated)"}

RHMD_URL = "https://raw.githubusercontent.com/usmaann/RHMD-Health-Mention-Dataset/main/RHMD_3_Class.csv"
CONSTRAINT = "https://raw.githubusercontent.com/diptamath/covid_fake_news/main/data/{name}"
URDU_FAKE = "https://raw.githubusercontent.com/MaazAmjad/Datasets-for-Urdu-news/master/Urdu%20Fake%20News%20Dataset.zip"
LASSA_URL = ("https://raw.githubusercontent.com/EmmanuelNiyi/ncdc-lassa-fever-timeseries-2020-2025/main/"
             "data%20files/lassa_fever_timeseries_full.csv")
OPENDENGUE_URL = "https://raw.githubusercontent.com/OpenDengue/master-repo/main/data/releases/V1.3/Temporal_extract_V1_3.zip"


def _get(url: str, name: str) -> bytes:
    CACHE.mkdir(parents=True, exist_ok=True)
    path = CACHE / name
    if not path.exists():
        r = requests.get(url, headers=UA, timeout=300)
        r.raise_for_status()
        path.write_bytes(r.content)
    return path.read_bytes()


def _split(df: pd.DataFrame, label: str, val: float = 0.1, test: float = 0.2) -> dict[str, pd.DataFrame]:
    train, rest = train_test_split(df, test_size=val + test, stratify=df[label], random_state=SEED)
    valid, test_df = train_test_split(rest, test_size=test / (val + test), stratify=rest[label], random_state=SEED)
    return {"train": train.reset_index(drop=True), "validation": valid.reset_index(drop=True), "test": test_df.reset_index(drop=True)}


def rhmd() -> dict[str, pd.DataFrame]:
    """Binary: genuine health mention (HM) vs. figurative (FM) or non-health (NHM)."""
    df = pd.read_csv(io.BytesIO(_get(RHMD_URL, "rhmd_3_class.csv")))
    df.columns = [c.strip().lower() for c in df.columns]
    df = df.rename(columns={next(c for c in df.columns if "text" in c): "text"})
    df["label"] = (df["label"].astype(int) == 2).map({True: "health", False: "other"})
    return _split(_dedupe(df[["text", "label"]].dropna()), "label")


def constraint() -> dict[str, pd.DataFrame]:
    out = {}
    for split, name in (("train", "Constraint_Train.csv"), ("validation", "Constraint_Val.csv"),
                        ("test", "english_test_with_labels.csv")):
        df = pd.read_csv(io.BytesIO(_get(CONSTRAINT.format(name=name), f"constraint_{name}")))
        df = df.rename(columns={"tweet": "text"})
        df["label"] = df["label"].str.strip().str.lower().map({"fake": "false", "real": "true"})
        out[split] = df[["text", "label"]].dropna().assign(lang="en")
    seen = set(out["train"]["text"].map(_key))
    for split in ("validation", "test"):
        out[split] = out[split][~out[split]["text"].map(_key).isin(seen)].reset_index(drop=True)
    return out


def urdu_fake() -> dict[str, pd.DataFrame]:
    """Official train/test; validation carved from train. `category` keeps the topic (hlth = health)."""
    z = zipfile.ZipFile(io.BytesIO(_get(URDU_FAKE, "urdu_fake_news.zip")))
    rows = []
    for name in z.namelist():
        if not name.endswith(".txt"):
            continue
        parts = name.split("/")
        split, truth, fname = parts[-3].lower(), parts[-2].lower(), parts[-1]
        text = z.read(name).decode("utf-8", errors="replace").strip()
        category = "".join(ch for ch in fname.split(".")[0] if ch.isalpha())
        rows.append({"text": text, "label": "false" if truth == "fake" else "true", "split": split, "category": category})
    df = pd.DataFrame(rows)
    train = df[df["split"] == "train"]
    train, valid = train_test_split(train, test_size=0.15, stratify=train["label"], random_state=SEED)
    return {
        "train": train.drop(columns="split").reset_index(drop=True).assign(lang="ur"),
        "validation": valid.drop(columns="split").reset_index(drop=True).assign(lang="ur"),
        "test": df[df["split"] == "test"].drop(columns="split").reset_index(drop=True).assign(lang="ur"),
    }


def lassa_weekly() -> pd.DataFrame:
    df = pd.read_csv(io.BytesIO(_get(LASSA_URL, "lassa_full.csv")), parse_dates=["week_start_date", "week_end_date"])
    df = df.sort_values("week_start_date").drop_duplicates("week_start_date")
    return df[["week_start_date", "epi_year", "epi_week", "suspected_cases", "confirmed_cases", "deaths"]].reset_index(drop=True)


def dengue_pakistan_weekly() -> dict[str, pd.DataFrame]:
    """Weekly series available in OpenDengue for Pakistan: national (2013-2017) and Sindh (2020-2022)."""
    raw = _get(OPENDENGUE_URL, "opendengue_temporal.zip")
    df = pd.read_csv(zipfile.ZipFile(io.BytesIO(raw)).open("Temporal_extract_V1_3.csv"), low_memory=False)
    pk = df[(df["adm_0_name"].str.upper() == "PAKISTAN") & (df["T_res"] == "Week")].copy()
    pk["week_start_date"] = pd.to_datetime(pk["calendar_start_date"])
    national = (pk[pk["S_res"] == "Admin0"].groupby("week_start_date")["dengue_total"].sum().reset_index())
    sindh = pk[(pk["S_res"] == "Admin2") & (pk["adm_1_name"].str.upper() == "SINDH")]
    if sindh.empty:
        sindh = pk[(pk["S_res"] == "Admin1") & (pk["adm_1_name"].str.upper() == "SINDH")]
    sindh = sindh.groupby("week_start_date")["dengue_total"].sum().reset_index()
    return {"national": national, "sindh": sindh}


def wiki_pageviews(project: str, article: str, start: date, end: date) -> pd.Series:
    """Daily user pageviews for one article (Wikimedia REST API; data from July 2015)."""
    name = f"wiki_{project}_{quote(article, safe='')}_{start:%Y%m%d}_{end:%Y%m%d}.json"
    path = CACHE / name
    if not path.exists():
        url = (f"https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/{project}/all-access/user/"
               f"{quote(article, safe='')}/daily/{start:%Y%m%d}00/{end:%Y%m%d}00")
        for attempt in range(6):
            r = requests.get(url, headers=UA, timeout=120)
            if r.status_code != 429:
                break
            time.sleep(float(r.headers.get("Retry-After", 10 * (attempt + 1))))
        r.raise_for_status()
        CACHE.mkdir(parents=True, exist_ok=True)
        path.write_text(r.text, encoding="utf-8")
        time.sleep(0.5)
    items = json.loads(path.read_text(encoding="utf-8"))["items"]
    s = pd.Series({pd.Timestamp(i["timestamp"][:8]): i["views"] for i in items}, dtype=float)
    return s.asfreq("D", fill_value=0.0)


def wiki_title(project_lang: str, en_title: str) -> str | None:
    """Title of the equivalent article in another language edition, via interlanguage links."""
    r = requests.get("https://en.wikipedia.org/w/api.php", headers=UA, timeout=60, params={
        "action": "query", "titles": en_title, "prop": "langlinks", "lllang": project_lang, "format": "json"})
    r.raise_for_status()
    for page in r.json()["query"]["pages"].values():
        for ll in page.get("langlinks", []):
            return ll["*"].replace(" ", "_")
    return None
