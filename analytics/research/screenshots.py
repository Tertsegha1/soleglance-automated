"""Capture interface screenshots of a running SoleGlance instance for the paper.

    python -m research.screenshots --base http://localhost:8000

Logs in as the fictional demo user created by DemoSeeder.
"""

from __future__ import annotations

import argparse
from pathlib import Path

from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parents[2] / "paper" / "figures" / "screens"
PAGES = {
    "feed": "/feed",
    "trending": "/trending",
    "topic": "/topic?q=petrol",
    "chat": "/messages/__CONV__",
    "health": "/health?days=30",
}


def main() -> None:
    p = argparse.ArgumentParser()
    p.add_argument("--base", default="http://localhost:8000")
    p.add_argument("--user", default="ayesha@example.test")
    args = p.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page(viewport={"width": 1280, "height": 860}, device_scale_factor=2)
        page.goto(f"{args.base}/login")
        page.fill("input[name=email]", args.user)
        page.fill("input[name=password]", "password")
        page.click("button[type=submit]")
        page.wait_for_url("**/feed")

        for name, path in PAGES.items():
            if "__CONV__" in path:
                page.goto(args.base + "/messages")
                link = page.locator("a[href*='/messages/']").first
                path = "/messages/" + link.get_attribute("href").rstrip("/").split("/")[-1] if link.count() else "/messages"
            page.goto(args.base + path)
            page.wait_for_load_state("networkidle")
            page.screenshot(path=str(OUT / f"{name}.png"))

        page.goto(args.base + "/sentiment")
        page.fill("textarea[name=text]", "Aaj ka match bilkul bakwas tha, team ne bohat bura khela.")
        page.click("form[action$='/sentiment'] button")
        page.wait_for_load_state("networkidle")
        page.screenshot(path=str(OUT / "sentiment.png"))
        browser.close()
    print("saved to", OUT)


if __name__ == "__main__":
    main()
