"""Simulated human-in-the-loop adaptation with the promotion gate.

    python -m research.evaluate_feedback_loop

A language's users arrive on a platform whose model was trained without that
language. Each round, moderators correct BATCH posts from the incoming stream
(simulated with gold training labels), a candidate model is retrained, and the
promotion gate decides whether it replaces the production model:

    promote iff validation macro-F1 on the new language improves AND no other
    language's validation macro-F1 falls by more than GATE_TOLERANCE
    (soleglance_analytics.mlops.GATE_TOLERANCE).

We report, per round, the test macro-F1 of (a) every candidate and (b) the
gated production model, for two ways of choosing posts to correct
(uncertainty vs. random), over 3 seeds.

Scenarios: Nigerian Pidgin (pre-specified). Roman Urdu was added after the
Pidgin result was observed, to test the loop where training and test
distributions match (AfriSenti-pcm training data contain almost no neutral
tweets, while its test set is 13% neutral).
"""

from __future__ import annotations

import json
from pathlib import Path

import numpy as np
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import f1_score

from soleglance_analytics.mlops import GATE_TOLERANCE

from . import datasets
from .embeddings import embed

RESULTS = Path(__file__).resolve().parent / "results"
BATCH = 200
ROUNDS = 10
SEEDS = (0, 1, 2)
SCENARIOS = ("pcm", "ur-Latn")


def macro(y, p):
    return f1_score(y, p, labels=sorted(set(y)), average="macro", zero_division=0)


def run_scenario(target: str, E: dict, y: dict) -> dict:
    others = [l for l in E if l != target]
    Xb = np.vstack([E[l]["train"] for l in others])
    yb = np.concatenate([y[l]["train"] for l in others])
    pool_X, pool_y = E[target]["train"], y[target]["train"]

    def fit(extra):
        if not len(extra):
            return LogisticRegression(C=4.0, max_iter=3000, class_weight="balanced").fit(Xb, yb)
        X = np.vstack([Xb, pool_X[extra]])
        Y = np.concatenate([yb, pool_y[extra]])
        w = np.ones(len(Y))
        w[len(yb):] = len(yb) / (4 * len(extra))  # corrections jointly carry 25% of the base weight
        return LogisticRegression(C=4.0, max_iter=3000, class_weight="balanced").fit(X, Y, sample_weight=w)

    def scores(model):
        return {
            "target_test": macro(y[target]["test"], model.predict(E[target]["test"])),
            "target_val": macro(y[target]["validation"], model.predict(E[target]["validation"])),
            "others_val": {l: macro(y[l]["validation"], model.predict(E[l]["validation"])) for l in others},
        }

    base = fit([])
    base_s = scores(base)
    out = {"start_test_f1": base_s["target_test"], "strategies": {}}

    for strategy in ("uncertainty", "random"):
        cand_curves, prod_curves, promotions, worst = [], [], [], []
        for seed in SEEDS:
            rng = np.random.default_rng(seed)
            labelled: list[int] = []
            prod, prod_s = base, base_s
            cand_curve, prod_curve, promoted = [base_s["target_test"]], [base_s["target_test"]], 0
            for _ in range(ROUNDS):
                remaining = np.setdiff1d(np.arange(len(pool_y)), labelled)
                if strategy == "uncertainty":
                    conf = prod.predict_proba(pool_X[remaining]).max(axis=1)
                    pick = remaining[np.lexsort((rng.random(len(remaining)), conf))[:BATCH]]
                else:
                    pick = rng.choice(remaining, size=BATCH, replace=False)
                labelled.extend(pick.tolist())
                cand = fit(np.array(labelled))
                cs = scores(cand)
                cand_curve.append(cs["target_test"])
                worst.append(min(cs["others_val"][l] - base_s["others_val"][l] for l in others))
                gate = cs["target_val"] > prod_s["target_val"] and all(
                    cs["others_val"][l] >= prod_s["others_val"][l] - GATE_TOLERANCE for l in others)
                if gate:
                    prod, prod_s, promoted = cand, cs, promoted + 1
                prod_curve.append(prod_s["target_test"])
            cand_curves.append(cand_curve)
            prod_curves.append(prod_curve)
            promotions.append(promoted)
            print(target, strategy, seed, "cand", [round(c, 3) for c in cand_curve], "prod", [round(c, 3) for c in prod_curve], flush=True)
        c, p = np.array(cand_curves), np.array(prod_curves)
        out["strategies"][strategy] = {
            "labels": [BATCH * r for r in range(ROUNDS + 1)],
            "candidate_test_f1_mean": c.mean(axis=0).tolist(),
            "candidate_test_f1_sd": c.std(axis=0).tolist(),
            "production_test_f1_mean": p.mean(axis=0).tolist(),
            "production_test_f1_sd": p.std(axis=0).tolist(),
            "promotions_per_run": promotions,
            "worst_other_language_change_candidates": float(min(worst)),
        }
    out["all_labels_test_f1"] = scores(fit(np.arange(len(pool_y))))["target_test"]
    return out


def main() -> None:
    data = datasets.all_sentiment()
    E = {l: {s: embed(data[l][s], l, s) for s in ("train", "validation", "test")} for l in data}
    y = {l: {s: data[l][s]["label"].to_numpy() for s in ("train", "validation", "test")} for l in data}
    results = {"batch": BATCH, "rounds": ROUNDS, "seeds": list(SEEDS), "gate_tolerance": GATE_TOLERANCE,
               "scenarios": {t: run_scenario(t, E, y) for t in SCENARIOS}}
    RESULTS.mkdir(exist_ok=True)
    (RESULTS / "feedback_loop.json").write_text(json.dumps(results, indent=2))


if __name__ == "__main__":
    main()
