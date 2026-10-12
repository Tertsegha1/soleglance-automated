"""How strict should the promotion gate be?

    python -m research.evaluate_gate_tolerance

Same simulation as evaluate_feedback_loop (random selection of posts to
correct, 3 seeds, 10 rounds of 200), but every candidate's validation scores
are recorded so the gated production model can be replayed for several
tolerances. With random selection the candidates do not depend on the
production model, so one set of candidates serves every tolerance.

For each tolerance we report the final production test macro-F1 on the new
language and the worst production change on any other language's test set.
"""

from __future__ import annotations

import json
from pathlib import Path

import numpy as np
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import f1_score

from . import datasets
from .embeddings import embed

RESULTS = Path(__file__).resolve().parent / "results"
TOLERANCES = (0.0, 0.005, 0.01, 0.02, 0.05)
BATCH, ROUNDS, SEEDS = 200, 10, (0, 1, 2)


def macro(y, p):
    return f1_score(y, p, labels=sorted(set(y)), average="macro", zero_division=0)


def main() -> None:
    data = datasets.all_sentiment()
    E = {l: {s: embed(data[l][s], l, s) for s in ("train", "validation", "test")} for l in data}
    y = {l: {s: data[l][s]["label"].to_numpy() for s in ("train", "validation", "test")} for l in data}
    out = {"tolerances": list(TOLERANCES), "scenarios": {}}

    for target in ("pcm", "ur-Latn"):
        others = [l for l in E if l != target]
        Xb = np.vstack([E[l]["train"] for l in others])
        yb = np.concatenate([y[l]["train"] for l in others])
        pool_X, pool_y = E[target]["train"], y[target]["train"]

        def fit(extra):
            if not len(extra):
                return LogisticRegression(C=4.0, max_iter=3000, class_weight="balanced").fit(Xb, yb)
            w = np.ones(len(yb) + len(extra))
            w[len(yb):] = len(yb) / (4 * len(extra))
            return LogisticRegression(C=4.0, max_iter=3000, class_weight="balanced").fit(
                np.vstack([Xb, pool_X[extra]]), np.concatenate([yb, pool_y[extra]]), sample_weight=w)

        def score(model):
            return {split: {l: macro(y[l][split], model.predict(E[l][split])) for l in E} for split in ("validation", "test")}

        base = score(fit([]))
        runs = []
        for seed in SEEDS:
            rng = np.random.default_rng(seed)
            order = rng.permutation(len(pool_y))
            runs.append([score(fit(order[:BATCH * (r + 1)])) for r in range(ROUNDS)])
            print(target, seed, [round(c["test"][target], 3) for c in runs[-1]], flush=True)

        res = {}
        for tol in TOLERANCES:
            finals, worst = [], []
            for cands in runs:
                prod = base
                for c in cands:
                    if c["validation"][target] > prod["validation"][target] and all(
                            c["validation"][l] >= prod["validation"][l] - tol for l in others):
                        prod = c
                finals.append(prod["test"][target])
                worst.append(min(prod["test"][l] - base["test"][l] for l in others))
            res[str(tol)] = {"final_target_test_f1_mean": float(np.mean(finals)), "final_target_test_f1_sd": float(np.std(finals)),
                             "worst_other_test_change_mean": float(np.mean(worst)), "worst_other_test_change_min": float(np.min(worst))}
        out["scenarios"][target] = {"start_target_test_f1": base["test"][target],
                                    "ungated_final_target_test_f1_mean": float(np.mean([r[-1]["test"][target] for r in runs])),
                                    "ungated_worst_other_test_change_min": float(min(min(r[-1]["test"][l] - base["test"][l] for l in others) for r in runs)),
                                    "by_tolerance": res}
        print(target, json.dumps(res, indent=1), flush=True)

    RESULTS.mkdir(exist_ok=True)
    (RESULTS / "gate_tolerance.json").write_text(json.dumps(out, indent=2))


if __name__ == "__main__":
    main()
