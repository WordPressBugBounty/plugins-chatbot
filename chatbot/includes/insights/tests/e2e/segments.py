"""
Core 0.5.0: segments (qcin/segment + qcin.admin.screenFilters) and extra
signal types (qcin/signal_types). Uses the extensions fixture (option
qcin_test_ext = '1', fixture-extensions.php/.js installed, see README).

Visitors: one desktop, one mobile, one desktop that sends the fixture's
"wiggle" signal through qcInsights.registerDetector.
"""
import json
import os
import shlex
import subprocess
import sys
import time

from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
CLI = os.environ.get("QCIN_WP_CLI", "")
failures = []


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + (" - " + str(detail)[:300] if detail != "" else ""))
    if not ok:
        failures.append(name)


def wp(php):
    return subprocess.run(shlex.split(CLI) + ["eval", php], capture_output=True, text=True).stdout.strip()


def api(page, path, params=None):
    return page.evaluate(
        """async ([path, params]) => {
            const a = window.qcInsightsAdmin;
            const q = new URLSearchParams(params || {}).toString();
            const r = await fetch(a.restRoot + path + (q ? '?' + q : ''), { headers: { 'X-WP-Nonce': a.nonce }, credentials: 'same-origin' });
            return r.json();
        }""",
        [path, params or {}],
    )


def visit(browser, device=None, wiggle=False):
    ctx = browser.new_context(**(browser_devices[device] if device else {"viewport": {"width": 1280, "height": 800}}))
    v = ctx.new_page()
    v.goto(BASE + "/contact/")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    if wiggle:
        v.evaluate("qcInsights.registerDetector((api) => api.signal('wiggle', '#send', { n: 3 }))")
    if device:
        v.tap("#send")
    else:
        v.click("#send")
    time.sleep(3.5)
    v.close()
    ctx.close()


if not CLI:
    print("Set QCIN_WP_CLI.")
    sys.exit(2)

wp("update_option('qcin_test_ext', '1');")
with sync_playwright() as pw:
    browser_devices = pw.devices
    browser = pw.chromium.launch()
    admin_ctx = browser.new_context(viewport={"width": 1440, "height": 1000})
    page = admin_ctx.new_page()
    page.goto(BASE + "/wp-login.php")
    page.fill("#user_login", "admin")
    page.fill("#user_pass", "admin")
    page.click("#wp-submit")
    page.wait_for_load_state("networkidle")
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.wait_for_selector(".qcin-stats", timeout=15000)
    page.evaluate("""async () => { const a = window.qcInsightsAdmin;
        for (const [p, b] of [['/settings', {enabled: true}], ['/delete-all', {}]])
          await fetch(a.restRoot + p, {method: 'POST', headers: {'X-WP-Nonce': a.nonce, 'Content-Type': 'application/json'}, body: JSON.stringify(b), credentials: 'same-origin'}); }""")

    boot = page.evaluate("window.qcInsightsAdmin.signalTypes")
    check("extra signal type in the admin data", "wiggle" in boot and boot["wiggle"]["short"] == "Wiggle", boot)
    check("a type using a core bit is ignored", "bad" not in boot, boot)

    visit(browser)
    visit(browser, "iPhone 12")
    visit(browser, wiggle=True)
    time.sleep(1)

    ov = api(page, "/overview", {"range": "1"})
    seg = api(page, "/overview", {"range": "1", "demo_seg": "mobile"})
    check("overview counts all visits", ov["kpis"]["sessions"] == 3 and ov["segment"] is False, ov["kpis"]["sessions"])
    check("overview with segment counts mobile only", seg["kpis"]["sessions"] == 1 and seg["segment"] is True, seg["kpis"]["sessions"])
    check("segmented top pages", all(p["views"] == 1 for p in seg["pages"]) and len(seg["pages"]) == 1, seg["pages"])
    check("extra signal counted on the overview", ov["kpis"]["flags"].get("wiggle") == 1, ov["kpis"]["flags"])
    check("segment applies to the signal counts", seg["kpis"]["flags"].get("wiggle") == 0, seg["kpis"]["flags"])

    rec = api(page, "/sessions", {"range": "1"})
    rec_m = api(page, "/sessions", {"range": "1", "demo_seg": "mobile"})
    check("recordings with segment", rec["total"] == 3 and rec_m["total"] == 1 and rec_m["items"][0]["device"] == "mobile", (rec["total"], rec_m["total"]))
    wig = api(page, "/sessions", {"range": "1", "frustration": "wiggle"})
    check("recordings filtered by the extra signal", wig["total"] == 1 and "wiggle" in wig["items"][0]["flags"], wig["total"])

    pages_all = api(page, "/heatmaps/pages", {"range": "1"})["items"]
    url_id = pages_all[0]["url_id"]
    hm_m = api(page, "/heatmaps", {"range": "1", "url_id": url_id, "device": "mobile", "demo_seg": "mobile"})
    hm_d = api(page, "/heatmaps", {"range": "1", "url_id": url_id, "device": "desktop", "demo_seg": "mobile"})
    hm_all = api(page, "/heatmaps", {"range": "1", "url_id": url_id, "device": "desktop"})
    check("segmented heatmap: mobile clicks from raw clicks", hm_m["clicks"]["total"] == 1 and hm_m["segment"]["clicksFrom"], hm_m["clicks"]["total"])
    check("segmented heatmap: desktop clicks excluded", hm_d["clicks"]["total"] == 0 and hm_d["scroll"]["views"] == 0, (hm_d["clicks"]["total"], hm_d["scroll"]["views"]))
    check("heatmap without segment unchanged", hm_all["clicks"]["total"] == 2 and hm_all["segment"] is None, hm_all["clicks"]["total"])
    check("raw click window filter (7 days, or more if a feature asks)", int(wp("echo QuantumCloud\\Insights\\Storage\\Heatmap_Repository::raw_days();") or 0) >= 7)

    # Screens: one segment choice follows the visitor across screens.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.reload()
    page.wait_for_selector("#qcin-ov-demo_seg", timeout=15000)
    page.select_option("#qcin-ov-demo_seg", "mobile")
    page.wait_for_function("document.querySelector('.qcin-stat__value') && document.querySelector('.qcin-stat__value').textContent.trim() === '1'", timeout=15000)
    time.sleep(1)
    check("overview screen filter", page.locator(".qcin-stat__value").first.inner_text().strip() == "1", page.locator(".qcin-stat__value").first.inner_text())
    page.click(".qcin-nav >> text=Heatmaps")
    page.wait_for_selector("#qcin-hm-demo_seg", timeout=15000)
    check("heatmaps keep the chosen segment", page.eval_on_selector("#qcin-hm-demo_seg", "e => e.value") == "mobile")
    page.click(".qcin-nav >> text=Recordings")
    page.wait_for_selector("#qcin-f-demo_seg", timeout=15000)
    page.wait_for_selector(".qcin-row", timeout=15000)
    check("recordings keep the chosen segment", page.eval_on_selector("#qcin-f-demo_seg", "e => e.value") == "mobile" and page.locator(".qcin-row").count() == 1)
    page.select_option("#qcin-f-demo_seg", "")
    page.select_option("#qcin-f-frustration", "wiggle")
    page.wait_for_timeout(1500)
    check("extra signal in the frustration filter and badge", page.locator(".qcin-row").count() == 1 and "Wiggle" in page.locator(".qcin-row").first.inner_text(), (page.locator(".qcin-row").count(), page.locator(".qcin-row").first.inner_text()[:200] if page.locator(".qcin-row").count() else ""))
    browser.close()

wp("update_option('qcin_test_ext', '0');")
print("\n%d failure(s)" % len(failures) if failures else "\nAll checks passed")
sys.exit(1 if failures else 0)
