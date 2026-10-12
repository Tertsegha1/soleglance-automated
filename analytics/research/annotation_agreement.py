"""Cohen's kappa between two annotators' CSV files (same rows, `label` column).

    python -m research.annotation_agreement paper/annotation/pcm_A.csv paper/annotation/pcm_B.csv
"""

from __future__ import annotations

import sys

import pandas as pd
from sklearn.metrics import cohen_kappa_score


def main(a: str, b: str) -> None:
    x, y = pd.read_csv(a, index_col="row"), pd.read_csv(b, index_col="row")
    both = x.join(y, lsuffix="_a", rsuffix="_b").dropna(subset=["label_a", "label_b"])
    three = cohen_kappa_score(both["label_a"], both["label_b"])
    binary = cohen_kappa_score(both["label_a"] == "health", both["label_b"] == "health")
    print(f"n={len(both)}  kappa(3-way)={three:.3f}  kappa(health vs not)={binary:.3f}")


if __name__ == "__main__":
    main(*sys.argv[1:3])
