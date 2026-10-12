"""Aberration detection for count time series (outbreak early warning).

Implements the CDC Early Aberration Reporting System (EARS) C1, C2 and C3
statistics (Hutwagner et al., 2003), widely used in syndromic surveillance,
plus the Poisson burst score used for trending topics:

    C1_t = (x_t - mean(x_{t-B..t-1})) / sd(x_{t-B..t-1})
    C2_t = (x_t - mean(x_{t-B-G..t-1-G})) / sd(...)          (G-step guard band)
    C3_t = sum_{i=0..2} max(0, C2_{t-i} - 1)

The standard deviation is floored (min_sd) so that a flat zero baseline does
not turn a single count into an infinite score. Alerts are raised when the
statistic exceeds its threshold and the count reaches min_count; consecutive
alerting steps are merged into one alert episode.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Sequence

import numpy as np

DEFAULTS = {
    "c1": {"baseline": 7, "guard": 0, "threshold": 3.0},
    "c2": {"baseline": 7, "guard": 2, "threshold": 3.0},
    "c3": {"baseline": 7, "guard": 2, "threshold": 2.0},
    "poisson": {"baseline": 7, "guard": 0, "threshold": 3.0},
}


@dataclass
class Alert:
    start: int  # index of first alerting step
    end: int  # index of last alerting step
    peak: int  # index of the highest statistic in the episode
    statistic: float
    count: float


def _baseline_stats(x: np.ndarray, t: int, baseline: int, guard: int) -> tuple[float, float] | None:
    lo, hi = t - guard - baseline, t - guard
    if lo < 0:
        return None
    window = x[lo:hi]
    return float(window.mean()), float(window.std(ddof=1)) if len(window) > 1 else 0.0


def statistic(x: Sequence[float], method: str = "c2", baseline: int | None = None, guard: int | None = None,
              min_sd: float = 1.0) -> np.ndarray:
    """Per-step aberration statistic; NaN where the baseline is not yet available."""
    cfg = DEFAULTS[method]
    b = baseline if baseline is not None else cfg["baseline"]
    g = guard if guard is not None else cfg["guard"]
    x = np.asarray(x, dtype=float)

    if method == "c3":
        c2 = statistic(x, "c2", b, g, min_sd)
        out = np.full(len(x), np.nan)
        for t in range(2, len(x)):
            window = c2[t - 2:t + 1]
            if not np.isnan(window).any():
                out[t] = float(np.clip(window - 1, 0, None).sum())
        return out

    out = np.full(len(x), np.nan)
    for t in range(len(x)):
        stats = _baseline_stats(x, t, b, g)
        if stats is None:
            continue
        mean, sd = stats
        if method == "poisson":
            out[t] = (x[t] - mean) / np.sqrt(mean + 1.0)
        else:
            out[t] = (x[t] - mean) / max(sd, min_sd)
    return out


def detect(x: Sequence[float], method: str = "c2", threshold: float | None = None, min_count: float = 1,
           baseline: int | None = None, guard: int | None = None, min_sd: float = 1.0) -> list[Alert]:
    x = np.asarray(x, dtype=float)
    stat = statistic(x, method, baseline, guard, min_sd)
    k = threshold if threshold is not None else DEFAULTS[method]["threshold"]
    flags = (np.nan_to_num(stat, nan=-np.inf) > k) & (x >= min_count)

    alerts: list[Alert] = []
    t = 0
    while t < len(x):
        if flags[t]:
            start = t
            while t + 1 < len(x) and flags[t + 1]:
                t += 1
            seg = stat[start:t + 1]
            peak = start + int(np.nanargmax(seg))
            alerts.append(Alert(start, t, peak, float(stat[peak]), float(x[peak])))
        t += 1
    return alerts
