"""Retrospective evaluation of outbreak early warning against official data.

    python -m research.evaluate_surveillance

Primary: Lassa fever, Nigeria, epi-weeks 2020-2025 (NCDC confirmed cases).
Signal: daily English Wikipedia pageviews of "Lassa fever", aggregated to
the same Monday-start weeks (Generous et al. 2014 used Wikipedia access
logs the same way). Wikipedia data are available the next day, whereas
official weekly reports are published with a delay.

Pre-specified analyses:
1. Lagged correlation (Spearman) between signal and confirmed cases,
   lags -4..+4 weeks (positive = signal leads).
2. Early warning. Season onset = first week of each epi-year in which
   confirmed cases reach the 75th percentile of all weeks, for two
   consecutive weeks. Each detector (EARS C1/C2/C3, Poisson burst) runs on
   the weekly signal with an 8-week baseline. A season is detected if an
   alert starts within [onset-6, onset+2] weeks; lead = onset - first alert.
   Alerts outside every [onset-6, season end] window are false alarms.
3. Nowcasting with leave-one-season-out regression of log cases on log
   signal, versus (a) the case count two weeks earlier, mimicking reporting
   delay, and (b) the same week of the previous year.

Secondary (exploratory, sparse data): dengue in Pakistan (OpenDengue weekly
series) against Urdu Wikipedia pageviews of the dengue article.
"""

from __future__ import annotations

import json
from datetime import date
from pathlib import Path

import numpy as np
import pandas as pd
from scipy.stats import spearmanr

from soleglance_analytics.health import surveillance

from . import health_data

RESULTS = Path(__file__).resolve().parent / "results"
METHODS = ("c1", "c2", "c3", "poisson")
BASELINE_WEEKS = 8


def weekly(daily: pd.Series, week_starts: pd.Series) -> pd.Series:
    """Sum daily values into Monday-start weeks aligned with the case data."""
    w = daily.resample("W-MON", label="left", closed="left").sum()
    return w.reindex(pd.DatetimeIndex(week_starts)).fillna(0.0)


def lagged_correlation(signal: np.ndarray, cases: np.ndarray, max_lag: int = 4) -> dict:
    out = {}
    for lag in range(-max_lag, max_lag + 1):
        # lag > 0: signal at t-lag compared with cases at t (signal leads).
        if lag >= 0:
            s, c = signal[: len(signal) - lag], cases[lag:]
        else:
            s, c = signal[-lag:], cases[: len(cases) + lag]
        rho, p = spearmanr(s, c)
        out[lag] = {"spearman": float(rho), "p": float(p)}
    return out


def onsets(df: pd.DataFrame, quantile: float = 0.75) -> tuple[list[int], list[int], float]:
    thr = float(np.quantile(df["confirmed_cases"], quantile))
    above = (df["confirmed_cases"] >= thr).to_numpy()
    onset_idx, end_idx = [], []
    for year, g in df.groupby("epi_year"):
        idx = g.index.to_numpy()
        for j in range(len(idx) - 1):
            if above[idx[j]] and above[idx[j + 1]]:
                onset_idx.append(int(idx[j]))
                k = j
                while k + 1 < len(idx) and above[idx[k + 1]]:
                    k += 1
                end_idx.append(int(idx[k]))
                break
    return onset_idx, end_idx, thr


def early_warning(signal: np.ndarray, onset_idx: list[int], end_idx: list[int], n_years: float) -> dict:
    out = {}
    for method in METHODS:
        alerts = surveillance.detect(signal, method, baseline=BASELINE_WEEKS,
                                     guard=0 if method in ("c1", "poisson") else 2,
                                     min_count=0, min_sd=max(1.0, float(np.std(signal)) * 0.05))
        starts = [a.start for a in alerts]
        detected, leads = 0, []
        for o in onset_idx:
            hits = [s for s in starts if o - 6 <= s <= o + 2]
            if hits:
                detected += 1
                leads.append(o - min(hits))
        windows = [(o - 6, e) for o, e in zip(onset_idx, end_idx)]
        false = [s for s in starts if not any(lo <= s <= hi for lo, hi in windows)]
        out[method] = {
            "seasons": len(onset_idx),
            "detected": detected,
            "sensitivity": detected / len(onset_idx) if onset_idx else None,
            "lead_weeks": leads,
            "median_lead_weeks": float(np.median(leads)) if leads else None,
            "false_alarms": len(false),
            "false_alarms_per_year": len(false) / n_years,
            "alert_starts": starts,
        }
    return out


def nowcast(df: pd.DataFrame, signal: np.ndarray) -> dict:
    y = np.log1p(df["confirmed_cases"].to_numpy(dtype=float))
    x = np.log1p(signal)
    years = df["epi_year"].to_numpy()
    pred_sig, pred_delay, pred_season, truth = [], [], [], []
    for yr in np.unique(years):
        test = years == yr
        train = ~test
        A = np.vstack([x[train], np.ones(train.sum())]).T
        coef, *_ = np.linalg.lstsq(A, y[train], rcond=None)
        idx = np.where(test)[0]
        for i in idx:
            if i < 52 or i < 2:
                continue  # need last year's same week for the seasonal baseline
            truth.append(y[i])
            pred_sig.append(coef[0] * x[i] + coef[1])
            pred_delay.append(y[i - 2])
            pred_season.append(y[i - 52])
    truth, pred_sig, pred_delay, pred_season = map(np.array, (truth, pred_sig, pred_delay, pred_season))

    def mae(p):
        return float(np.mean(np.abs(np.expm1(p) - np.expm1(truth))))

    return {
        "weeks_evaluated": int(len(truth)),
        "mae_cases_signal_model": mae(pred_sig),
        "mae_cases_two_week_old_count": mae(pred_delay),
        "mae_cases_same_week_last_year": mae(pred_season),
        "spearman_signal_model": float(spearmanr(pred_sig, truth)[0]),
    }


def lassa() -> dict:
    df = health_data.lassa_weekly()
    start, end = df["week_start_date"].min().date(), (df["week_start_date"].max() + pd.Timedelta(days=6)).date()
    signals = {
        "en_wikipedia": weekly(health_data.wiki_pageviews("en.wikipedia", "Lassa_fever", start, end), df["week_start_date"]).to_numpy(),
        "ha_wikipedia": weekly(health_data.wiki_pageviews("ha.wikipedia", "Lassa_fever", start, end), df["week_start_date"]).to_numpy(),
    }
    cases = df["confirmed_cases"].to_numpy(dtype=float)
    onset_idx, end_idx, thr = onsets(df)
    n_years = len(df) / 52.18

    series = pd.DataFrame({"week_start": df["week_start_date"].dt.strftime("%Y-%m-%d"), "confirmed": cases, **signals})
    series.to_csv(RESULTS / "lassa_weekly_series.csv", index=False)
    return {
        "weeks": int(len(df)),
        "period": [str(start), str(end)],
        "onset_threshold_cases": thr,
        "onset_weeks": [str(df.loc[i, "week_start_date"].date()) for i in onset_idx],
        "signal_totals": {k: float(v.sum()) for k, v in signals.items()},
        "correlation": {k: lagged_correlation(v, cases) for k, v in signals.items()},
        "early_warning": {k: early_warning(v, onset_idx, end_idx, n_years) for k, v in signals.items()},
        # Reference: the same detectors run on the official counts themselves.
        "early_warning_on_official_counts": early_warning(cases, onset_idx, end_idx, n_years),
        "nowcast": {k: nowcast(df, v) for k, v in signals.items()},
    }


def dengue() -> dict:
    title = health_data.wiki_title("ur", "Dengue fever") or "ڈینگی_بخار"
    out = {"article": title}
    for name, df in health_data.dengue_pakistan_weekly().items():
        df = df[df["week_start_date"] >= "2015-07-06"].reset_index(drop=True)  # pageviews start July 2015
        if len(df) < 12:
            out[name] = {"weeks": int(len(df)), "note": "too few weeks with pageview coverage"}
            continue
        start, end = df["week_start_date"].min().date(), (df["week_start_date"].max() + pd.Timedelta(days=6)).date()
        daily = health_data.wiki_pageviews("ur.wikipedia", title, start, end)
        # OpenDengue weeks start on Sunday; align by summing the 7 days from each start date.
        signal = np.array([daily[d:d + pd.Timedelta(days=6)].sum() for d in df["week_start_date"]])
        cases = df["dengue_total"].to_numpy(dtype=float)
        out[name] = {
            "weeks": int(len(df)),
            "period": [str(start), str(end)],
            "total_cases": float(cases.sum()),
            "correlation": lagged_correlation(signal, cases, max_lag=3),
        }
    return out


def main() -> None:
    RESULTS.mkdir(exist_ok=True)
    results = {"lassa_nigeria": lassa(), "dengue_pakistan": dengue()}
    (RESULTS / "surveillance_eval.json").write_text(json.dumps(results, indent=2, default=float))
    l = results["lassa_nigeria"]
    print("onsets:", l["onset_weeks"])
    for sig in l["correlation"]:
        print(sig, "corr:", {k: round(v["spearman"], 3) for k, v in l["correlation"][sig].items()})
        for m, r in l["early_warning"][sig].items():
            print("  ", m, r["detected"], "/", r["seasons"], "lead", r["lead_weeks"], "false/yr", round(r["false_alarms_per_year"], 2))
        print("   nowcast:", l["nowcast"][sig])
    print("dengue:", {k: (v.get("weeks"), {lag: round(c["spearman"], 2) for lag, c in v.get("correlation", {}).items()})
                      for k, v in results["dengue_pakistan"].items() if k != "article"})


if __name__ == "__main__":
    main()
