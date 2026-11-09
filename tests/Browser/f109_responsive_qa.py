"""Offline Chromium QA for F1-09. Requires preinstalled playwright and Chromium.

Run: python tests/Browser/f109_responsive_qa.py
No installation, network access, production integration or browser publishing.
"""
from pathlib import Path
import json
import os
import shutil
import sys

try:
    from playwright.sync_api import sync_playwright
except ImportError:
    print("BLOCKED: Python Playwright is not installed; no installation attempted", file=sys.stderr)
    sys.exit(2)

root = Path(__file__).resolve().parents[2]
html = (root / "examples/f109-comparador-versoes-offline.html").read_text(encoding="utf-8")
browser_path = os.environ.get("IMAGINE_CHROMIUM") or shutil.which("chromium")
if not browser_path:
    print("BLOCKED: Chromium executable is missing; no installation attempted", file=sys.stderr)
    sys.exit(2)

viewports = [(1440, 900), (1024, 768), (768, 1024), (390, 844), (320, 680)]
expected = {
    "safe": ("REVISÃO HUMANA NECESSÁRIA", "6/6", "0", "0"),
    "regression": ("BLOQUEADO · regressão crítica", "4/6", "2", "0"),
    "incomplete": ("INCONCLUSIVO · caso não executado", "5/6", "0", "1"),
}
checks = []
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path=browser_path, headless=True, args=["--no-sandbox"])
    try:
        for width, height in viewports:
            page = browser.new_page(viewport={"width": width, "height": height}, device_scale_factor=1)
            errors, requests = [], []
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.on("console", lambda msg: errors.append(msg.text) if msg.type == "error" else None)
            page.on("request", lambda request: requests.append(request.url))
            page.set_content(html, wait_until="load")
            assert page.locator("#page-overview").is_visible()
            page.keyboard.press("Tab")
            assert page.evaluate("document.activeElement.tagName") == "BUTTON", "keyboard focus"
            page.locator('[data-go="compare"]').click()
            assert page.locator("#page-compare").is_visible()
            region = page.locator('[role="region"][aria-label*="Tabela de comparação"]')
            assert region.count() == 1 and region.get_attribute("tabindex") == "0"
            for key, (title, passed, critical, pending) in expected.items():
                page.locator(f'[data-candidate="{key}"]').click()
                assert page.locator(f'[data-candidate="{key}"]').get_attribute("aria-pressed") == "true"
                page.locator("#compare-run").click()
                assert page.locator("#compare-gate strong").inner_text() == title
                assert page.locator("#stat-pass").inner_text() == passed
                assert page.locator("#stat-critical").inner_text() == critical
                assert page.locator("#stat-pending").inner_text() == pending
                assert page.locator("#compare-rows tr").count() == 6
            page.locator('[data-page="history"]').click()
            assert page.locator(".historyrow").count() == 3
            page.locator("#history-reset").click()
            assert page.locator(".historyrow").count() == 0
            page.locator('[data-page="compare"]').click()
            page.locator("#compare-clear").click()
            assert page.locator("#stat-pass").inner_text() == "—"
            dimensions = page.evaluate("""() => ({
                viewport: innerWidth,
                pageWidth: document.documentElement.scrollWidth,
                tableWidth: document.querySelector('.tablewrap').scrollWidth,
                tableVisible: document.querySelector('.tablewrap').clientWidth
            })""")
            assert dimensions["pageWidth"] <= width, f"horizontal page overflow: {dimensions}"
            if width <= 540:
                assert dimensions["tableWidth"] > dimensions["tableVisible"], "mobile table must scroll inside region"
                assert page.locator(".scrollhint").is_visible(), "mobile scroll hint"
            else:
                assert not page.locator(".scrollhint").is_visible(), "desktop hint hidden"
            assert errors == [], f"browser errors: {errors}"
            assert requests == [], f"external requests: {requests}"
            checks.append({"viewport": f"{width}x{height}", "page_overflow": False,
                           "cases": 3, "browser_errors": 0, "network_requests": 0,
                           "keyboard": "PASS", "table_region": "PASS"})
            page.close()
    finally:
        browser.close()

print(json.dumps({"status": "F109_CHROMIUM_PASS", "browser": "Chromium",
                  "mode": "HTML_SET_CONTENT_OFFLINE", "checks": checks}, ensure_ascii=False, indent=2))
