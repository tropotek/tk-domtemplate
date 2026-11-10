"""
Browser test for the example site's HTMX + Alpine page.

Run with bin/e2e (needs the example site running: docker compose up -d).
Exits non-zero when a check fails.
"""
import os

from playwright.sync_api import expect, sync_playwright

BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8080").rstrip("/")

errors: list[str] = []

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page()
    page.on("pageerror", lambda e: errors.append(f"pageerror: {e}"))
    page.on("console", lambda m: errors.append(f"console.{m.type}: {m.text}") if m.type == "error" else None)
    page.goto(f"{BASE_URL}/htmx.php", wait_until="networkidle")

    # Alpine on the page itself
    toggle_text = page.locator('main p[x-show="open"]')
    expect(toggle_text).to_be_hidden()
    page.get_by_role("button", name="Toggle with Alpine").click()
    expect(toggle_text).to_be_visible()
    print("PASS Alpine toggle on the page")

    # HTMX swaps in a fragment rendered by a template
    page.get_by_role("button", name="Load a fragment").click()
    target = page.locator("#fragment")
    expect(target.get_by_text("Rendered at")).to_be_visible(timeout=5000)
    print("PASS fragment swapped in")

    # The fragment's own inline script (added with js()) ran after the swap
    expect(page.locator("#fragmentScript")).to_have_text("The fragment's own script ran after the swap.")
    print("PASS fragment script ran")

    # Alpine initialised the swapped-in x-data counter
    counter = target.get_by_role("button")
    expect(counter).to_have_text("Clicked 0 times")
    for _ in range(3):
        counter.click()
    expect(counter).to_have_text("Clicked 3 times")
    print("PASS Alpine counter in the fragment")

    # Loading again replaces the fragment
    page.get_by_role("button", name="Load a fragment").click()
    expect(target.get_by_role("button")).to_have_text("Clicked 0 times")
    print("PASS second load replaces the fragment")

    browser.close()

if errors:
    raise SystemExit("Browser errors:\n" + "\n".join(errors))
print("PASS no browser errors")
