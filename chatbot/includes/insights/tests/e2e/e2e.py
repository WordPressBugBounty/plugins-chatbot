"""
End-to-end test: onboarding, a scripted visitor session, replay and settings.
Usage: python3 e2e.py [onboarding|visit|admin|mobile|all]
Needs the test pages /contact/ and /pricing/ and mu-plugin qcin-test.php (see README.md).
"""
import json, os, sys, time, gzip, glob
from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
OUT = os.path.join(os.path.dirname(__file__), "shots")
os.makedirs(OUT, exist_ok=True)
stage = sys.argv[1] if len(sys.argv) > 1 else "all"
errors = []

def shot(page, name, full=True):
    page.screenshot(path=os.path.join(OUT, name + ".png"), full_page=full)

def login(ctx):
    p = ctx.new_page()
    p.on("console", lambda m: errors.append(f"[admin console {m.type}] {m.text}") if m.type in ("error",) else None)
    p.on("pageerror", lambda e: errors.append(f"[admin pageerror] {e}"))
    p.goto(BASE + "/wp-login.php")
    p.fill("#user_login", os.environ.get("QCIN_USER", "admin"))
    p.fill("#user_pass", os.environ.get("QCIN_PASS", "admin"))
    p.click("#wp-submit")
    p.wait_for_load_state("networkidle")
    return p

with sync_playwright() as pw:
    browser = pw.chromium.launch()
    admin_ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    admin = login(admin_ctx)

    if stage in ("onboarding", "all"):
        admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights")
        admin.wait_for_selector(".qcin-onboarding", timeout=15000)
        shot(admin, "01-onboarding")
        admin.click("text=Turn on recording")
        admin.wait_for_selector(".qcin-stats", timeout=15000)
        time.sleep(1)
        shot(admin, "02-enabled-empty")

    if stage in ("visit", "all"):
        vctx = browser.new_context(viewport={"width": 1280, "height": 800})
        v = vctx.new_page()
        v.on("console", lambda m: errors.append(f"[visitor console {m.type}] {m.text}") if m.type == "error" else None)
        v.on("pageerror", lambda e: errors.append(f"[visitor pageerror] {e}"))
        reqs = []
        v.on("request", lambda r: reqs.append(r.url) if "qcin/v1/ingest" in r.url or "recorder.js" in r.url else None)
        v.goto(BASE + "/contact/")
        v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
        v.fill("#name", "Jane Secret")
        v.fill("#email", "jane@example.com")
        v.fill("#msg", "my private message 98765")
        v.mouse.move(200, 300)
        v.mouse.move(600, 400, steps=10)
        v.click("#send")
        v.mouse.wheel(0, 400)
        time.sleep(3)
        v.click("#pricing-link")
        v.wait_for_load_state("load")
        v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
        for i in range(6):
            v.mouse.wheel(0, 400)
            time.sleep(0.4)
        v.click("text=Bottom of pricing")
        time.sleep(3.5)
        v.close()  # pagehide -> sendBeacon
        time.sleep(1.5)
        vctx.close()
        print("tracker requests:", len(reqs), [r.split("?")[0][-40:] for r in reqs][:6])

    if stage in ("admin", "all"):
        admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings")
        admin.wait_for_selector(".qcin-table, .qcin-empty", timeout=15000)
        time.sleep(1.5)
        shot(admin, "03-recordings")
        rows = admin.locator(".qcin-row").count()
        print("rows:", rows)
        if rows:
            admin.locator(".qcin-row").first.click()
            admin.wait_for_selector(".qcin-replay .rr-player, .qcin-empty", timeout=20000)
            time.sleep(4)
            shot(admin, "04-player", full=False)
            # second page of the visit
            btns = admin.locator(".qcin-page")
            if btns.count() > 1:
                btns.nth(1).click()
                admin.wait_for_selector(".qcin-replay .rr-player", timeout=20000)
                time.sleep(3)
                shot(admin, "05-player-page2", full=False)
        admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
        admin.wait_for_selector(".qcin-settings", timeout=15000)
        time.sleep(2)
        shot(admin, "06-settings")
        admin.fill("#qcin-sample", "50" if admin.input_value("#qcin-sample") != "50" else "100")
        admin.click("text=Save settings")
        admin.wait_for_selector(".qcin-toast--success", timeout=10000)
        print("settings saved toast ok")

    if stage == "mobile":
        mctx = browser.new_context(viewport={"width": 390, "height": 844})
        m = login(mctx)
        m.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings")
        m.wait_for_selector(".qcin-table, .qcin-empty", timeout=15000)
        time.sleep(1)
        shot(m, "07-mobile")

    browser.close()

print("errors:", json.dumps(errors, indent=1))
