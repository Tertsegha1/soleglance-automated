"""Figures for the paper from research/results/*.json.

    python -m research.plot_figures
"""

from __future__ import annotations

import json
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd

from . import health_data

RES = Path(__file__).resolve().parent / "results"
FIG = Path(__file__).resolve().parents[2] / "paper" / "figures"


def surveillance() -> None:
    s = pd.read_csv(RES / "lassa_weekly_series.csv", parse_dates=["week_start"])
    sv = json.loads((RES / "surveillance_eval.json").read_text())
    fig, axes = plt.subplots(1, 2, figsize=(10, 3.2), gridspec_kw={"width_ratios": [2.2, 1]})

    ax = axes[0]
    ax.bar(s["week_start"], s["confirmed"], width=6, color="#f43f5e", alpha=0.75, label="Confirmed Lassa cases (NCDC)")
    ax.set_ylabel("confirmed cases / week")
    ax2 = ax.twinx()
    ax2.plot(s["week_start"], s["en_wikipedia"], color="#4f46e5", lw=1, label="English Wikipedia views")
    ax2.set_ylabel("pageviews / week", color="#4f46e5")
    for onset in sv["lassa_nigeria"]["onset_weeks"]:
        ax.axvline(pd.Timestamp(onset), color="black", lw=0.6, ls=":")
    ax.set_title("(a) Lassa fever, Nigeria: cases vs. global English attention", fontsize=9)
    h1, l1 = ax.get_legend_handles_labels()
    h2, l2 = ax2.get_legend_handles_labels()
    ax.legend(h1 + h2, l1 + l2, fontsize=7, loc="upper right")

    ax = axes[1]
    d = health_data.dengue_pakistan_weekly()["national"]
    d = d[d["week_start_date"] >= "2015-07-06"].reset_index(drop=True)
    title = sv["dengue_pakistan"]["article"]
    daily = health_data.wiki_pageviews("ur.wikipedia", title, d["week_start_date"].min().date(),
                                       (d["week_start_date"].max() + pd.Timedelta(days=6)).date())
    views = np.array([daily[w:w + pd.Timedelta(days=6)].sum() for w in d["week_start_date"]])
    ax.bar(d["week_start_date"], d["dengue_total"], width=6, color="#f59e0b", alpha=0.8, label="Dengue cases (OpenDengue)")
    ax2 = ax.twinx()
    ax2.plot(d["week_start_date"], views, color="#4f46e5", lw=1.2, label="Urdu Wikipedia views")
    ax.set_title("(b) Dengue, Pakistan 2017: cases vs. Urdu attention", fontsize=9)
    ax.tick_params(axis="x", labelrotation=30, labelsize=7)
    h1, l1 = ax.get_legend_handles_labels()
    h2, l2 = ax2.get_legend_handles_labels()
    ax.legend(h1 + h2, l1 + l2, fontsize=7, loc="upper left")
    fig.tight_layout()
    fig.savefig(FIG / "surveillance.pdf")
    fig.savefig(FIG / "surveillance.png", dpi=180)


def feedback_loop() -> None:
    fl = json.loads((RES / "feedback_loop.json").read_text())
    names = {"pcm": "Nigerian Pidgin", "ur-Latn": "Roman Urdu"}
    fig, axes = plt.subplots(1, len(fl["scenarios"]), figsize=(9, 3), sharey=False)
    for ax, (tgt, sc) in zip(np.atleast_1d(axes), fl["scenarios"].items()):
        for name, color in (("uncertainty", "#4f46e5"), ("random", "#94a3b8")):
            st = sc["strategies"][name]
            x = np.array(st["labels"])
            ax.plot(x, st["candidate_test_f1_mean"], ls=":", color=color, lw=1, label=f"{name}: candidate")
            ax.plot(x, st["production_test_f1_mean"], marker="o", ms=3, color=color, label=f"{name}: gated production")
        ax.axhline(sc["all_labels_test_f1"], color="#10b981", ls="--", lw=1, label="all training labels")
        ax.set_title(names[tgt], fontsize=9)
        ax.set_xlabel("moderator corrections")
        ax.set_ylabel("test macro-F1")
    np.atleast_1d(axes)[0].legend(fontsize=6)
    fig.tight_layout()
    fig.savefig(FIG / "feedback_loop.pdf")
    fig.savefig(FIG / "feedback_loop.png", dpi=180)


if __name__ == "__main__":
    FIG.mkdir(parents=True, exist_ok=True)
    surveillance()
    feedback_loop()
    print("saved to", FIG)
