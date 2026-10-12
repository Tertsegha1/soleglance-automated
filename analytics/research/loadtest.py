"""HTTP load test for the web application (paper, section on performance).

    python -m research.loadtest --base http://localhost:8000 --users 50 --duration 60

Each virtual user logs in as a distinct perf_<n> account (created by
PerformanceSeeder) and then repeatedly requests a weighted mix of pages
with no think time, which is harsher than real use. Reports latency
percentiles, throughput and error rate per page type.
"""

from __future__ import annotations

import argparse
import asyncio
import json
import random
import re
import statistics
import time
from collections import defaultdict
from pathlib import Path

import httpx

TOKEN_RE = re.compile(r'name="_token"\s+value="([^"]+)"')
TERMS = ["budget", "cricket", "rain", "petrol", "election", "flood", "metro", "film"]

MIX = [  # (label, weight, path factory)
    ("feed", 35, lambda r: "/feed"),
    ("profile", 15, lambda r: f"/@perf_{r.randint(1, 1000)}"),
    ("search", 15, lambda r: f"/search?q={r.choice(TERMS)}"),
    ("trending", 10, lambda r: "/trending"),
    ("topic", 15, lambda r: f"/topic?q={r.choice(TERMS)}"),
    ("notifications", 5, lambda r: "/notifications"),
    ("health", 5, lambda r: f"/health?days={r.choice([14, 30])}"),
]


async def login(client: httpx.AsyncClient, n: int) -> None:
    page = await client.get("/login")
    token = TOKEN_RE.search(page.text).group(1)
    r = await client.post("/login", data={"_token": token, "email": f"perf_{n}@example.test", "password": "password"})
    if r.status_code not in (302, 303):
        raise RuntimeError(f"login failed for perf_{n}: {r.status_code}")


async def virtual_user(base: str, n: int, deadline: float, samples: dict, errors: dict, seed: int) -> None:
    rnd = random.Random(seed)
    labels = [m[0] for m in MIX]
    weights = [m[1] for m in MIX]
    paths = {m[0]: m[2] for m in MIX}
    async with httpx.AsyncClient(base_url=base, timeout=30, follow_redirects=False) as client:
        await login(client, n)
        while time.perf_counter() < deadline:
            label = rnd.choices(labels, weights)[0]
            t0 = time.perf_counter()
            try:
                r = await client.get(paths[label](rnd))
                ok = r.status_code == 200
            except httpx.HTTPError:
                ok = False
            elapsed = time.perf_counter() - t0
            (samples if ok else errors)[label].append(elapsed)


def pct(values: list[float], q: float) -> float:
    values = sorted(values)
    return values[min(len(values) - 1, int(q * len(values)))] if values else float("nan")


async def main() -> None:
    p = argparse.ArgumentParser()
    p.add_argument("--base", default="http://localhost:8000")
    p.add_argument("--users", type=int, default=20)
    p.add_argument("--duration", type=int, default=60)
    p.add_argument("--out", default=str(Path(__file__).resolve().parent / "results" / "loadtest.json"))
    args = p.parse_args()

    samples: dict = defaultdict(list)
    errors: dict = defaultdict(list)
    # Warm-up: one user, 10 s, discarded.
    await asyncio.gather(virtual_user(args.base, 999, time.perf_counter() + 10, defaultdict(list), defaultdict(list), 0))
    start = time.perf_counter()
    deadline = start + args.duration
    await asyncio.gather(*(virtual_user(args.base, i + 1, deadline, samples, errors, i) for i in range(args.users)))
    wall = time.perf_counter() - start

    report = {"users": args.users, "duration_s": round(wall, 1), "pages": {}}
    total = 0
    for label, *_ in MIX:
        s = samples[label]
        total += len(s)
        report["pages"][label] = {
            "requests": len(s),
            "errors": len(errors[label]),
            "p50_ms": round(1000 * statistics.median(s), 1) if s else None,
            "p95_ms": round(1000 * pct(s, 0.95), 1) if s else None,
            "p99_ms": round(1000 * pct(s, 0.99), 1) if s else None,
        }
    all_s = [x for v in samples.values() for x in v]
    report["overall"] = {
        "requests": total,
        "errors": sum(len(v) for v in errors.values()),
        "throughput_rps": round(total / wall, 1),
        "p50_ms": round(1000 * statistics.median(all_s), 1) if all_s else None,
        "p95_ms": round(1000 * pct(all_s, 0.95), 1) if all_s else None,
        "share_under_3s": round(sum(1 for x in all_s if x < 3) / len(all_s), 4) if all_s else None,
    }
    out = Path(args.out)
    out.parent.mkdir(exist_ok=True)
    existing = json.loads(out.read_text()) if out.exists() else []
    existing = [r for r in existing if r.get("users") != args.users] + [report]
    out.write_text(json.dumps(sorted(existing, key=lambda r: r["users"]), indent=2))
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    asyncio.run(main())
