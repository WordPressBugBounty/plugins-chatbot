"""
Phase 2 end-to-end test: clicks, scroll, frustration signals, heatmaps, overview.

Three scripted visitors with known clicks, then exact checks through the admin API:
  A  desktop, recorded:     5 fast clicks on #send (rage + dead), 1 click on #name, #pricing-link, scrolls Pricing to the end
  B  desktop, not recorded: 2 slow clicks on #send (dead), #pricing-link, back within 2 s (quick back), a JavaScript error
  C  mobile, not recorded:  3 fast taps on #send (rage + dead)

Expected on /contact/: desktop #send 7, #name 1, #pricing-link 2 (10 clicks); mobile #send 3.

Optional: set QCIN_WP_CLI to a WP-CLI command (e.g. "wp --path=/var/www/html") to also
run the background jobs and check that aggregated heatmaps match the live counts and
that the quick back is detected.

Usage: python3 heatmaps.py   (needs the pages and mu-plugin from README.md)
"""
import json, os, shlex, subprocess, sys, time
from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
CLI = os.environ.get("QCIN_WP_CLI", "")
OUT = os.path.join(os.path.dirname(__file__), "shots")
os.makedirs(OUT, exist_ok=True)
failures = []

# Force a visit to be "not recorded" (collector only) by editing the config before the loader reads it.
NOT_RECORDED = """
(() => { let c; Object.defineProperty(window, 'qcInsightsConfig', {
  configurable: true, get() { return c; }, set(v) { v.sample = 0; v.hsample = 100; c = v; } }); })();
"""


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + (" - " + str(detail) if detail else ""))
    if not ok:
        failures.append(name)


def wp(php):
    if not CLI:
        return None
    out = subprocess.run(shlex.split(CLI) + ["eval", php], capture_output=True, text=True)
    return out.stdout.strip()


def api(page, path, method="GET", body=None):
    return page.evaluate(
        """async ([path, method, body]) => {
            const a = window.qcInsightsAdmin;
            const r = await fetch(a.restRoot + path, { method, headers: { 'X-WP-Nonce': a.nonce, 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
            return r.json();
        }""",
        [path, method, body],
    )


def by_selector(heat):
    return {e["selector"]: e["clicks"] for e in heat["clicks"]["elements"]}


with sync_playwright() as pw:
    browser = pw.chromium.launch()
    admin_ctx = browser.new_context(viewport={"width": 1440, "height": 1000})
    admin = admin_ctx.new_page()
    admin.goto(BASE + "/wp-login.php")
    admin.fill("#user_login", os.environ.get("QCIN_USER", "admin"))
    admin.fill("#user_pass", os.environ.get("QCIN_PASS", "admin"))
    admin.click("#wp-submit")
    admin.wait_for_load_state("networkidle")
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
    admin.wait_for_selector(".qcin-settings", timeout=15000)
    api(admin, "/settings", "POST", {"enabled": True})
    api(admin, "/delete-all", "POST", {})

    # Visitor A: desktop, recorded.
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    v = ctx.new_page()
    v.goto(BASE + "/contact/")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    check("A is recorded", v.evaluate("window.qcInsights.getContext().recording") is True)
    for _ in range(5):
        v.click("#send", delay=10)
        time.sleep(0.05)
    time.sleep(0.8)
    v.click("#name")
    time.sleep(0.6)
    v.click("#pricing-link")
    v.wait_for_load_state("load")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    for _ in range(10):
        v.mouse.wheel(0, 600)
        time.sleep(0.25)
    time.sleep(3)
    v.close()
    ctx.close()

    # Visitor B: desktop, not recorded.
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    ctx.add_init_script(NOT_RECORDED)
    v = ctx.new_page()
    v.goto(BASE + "/contact/")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    check("B is not recorded", v.evaluate("window.qcInsights.getContext().recording") is False)
    v.click("#send")
    time.sleep(1.3)
    v.click("#send")
    time.sleep(0.8)
    v.click("#pricing-link")
    v.wait_for_load_state("load")
    time.sleep(1.5)
    v.go_back()
    v.wait_for_load_state("load")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    time.sleep(1)
    v.evaluate("setTimeout(() => { throw new Error('Checkout widget failed') }, 0)")
    time.sleep(3)
    v.close()
    ctx.close()

    # Visitor C: mobile, not recorded.
    ctx = browser.new_context(**pw.devices["iPhone 12"])
    ctx.add_init_script(NOT_RECORDED)
    v = ctx.new_page()
    v.goto(BASE + "/contact/")
    v.wait_for_function("window.qcInsights !== undefined", timeout=15000)
    for _ in range(3):
        v.tap("#send")
        time.sleep(0.08)
    time.sleep(3)
    v.close()
    ctx.close()
    time.sleep(1.5)

    # Heatmap counts.
    pages = api(admin, "/heatmaps/pages?range=1")["items"]
    urls = {p["url"].rstrip("/").split("/")[-1]: p["url_id"] for p in pages}
    contact, pricing = urls.get("contact"), urls.get("pricing")
    check("pages listed", contact is not None and pricing is not None, urls)
    d = api(admin, "/heatmaps?url_id=%s&device=desktop&range=1" % contact)
    sel = by_selector(d)
    check("desktop clicks total", d["clicks"]["total"] == 10, d["clicks"]["total"])
    check("desktop #send", sel.get("#send") == 7, sel)
    check("desktop #name", sel.get("#name") == 1, sel)
    check("desktop #pricing-link", sel.get("#pricing-link") == 2, sel)
    send = next(e for e in d["clicks"]["elements"] if e["selector"] == "#send")
    check("rage on #send (desktop)", send["rage"] == 1, send)
    check("dead on #send (desktop)", send["dead"] >= 2, send)
    m = api(admin, "/heatmaps?url_id=%s&device=mobile&range=1" % contact)
    check("mobile #send", by_selector(m).get("#send") == 3, by_selector(m))
    s = api(admin, "/heatmaps?url_id=%s&device=desktop&range=1" % pricing)["scroll"]
    check("pricing scroll views", s["views"] == 2, s)
    check("pricing reached 100%", s["buckets"]["100"] >= 1, s["buckets"])

    # Background jobs (optional): aggregation keeps counts; quick back detected.
    if CLI:
        wp(
            "global $wpdb; $p=$wpdb->prefix.'qcin_'; $old=gmdate('Y-m-d H:i:s', time()-3600);"
            "$wpdb->query(\"UPDATE {$p}sessions SET last_seen_at='$old'\"); $wpdb->query(\"UPDATE {$p}pageviews SET last_seen_at='$old'\");"
            "delete_transient('qcin_lock_qcin_aggregate'); delete_transient('qcin_lock_qcin_finalize_sessions');"
            "$s=new QuantumCloud\\Insights\\Processing\\Scheduler(); $s->aggregate(); $s->finalize_sessions();"
        )
        d2 = api(admin, "/heatmaps?url_id=%s&device=desktop&range=1" % contact)
        check("aggregated equals live", by_selector(d2) == sel and d2["clicks"]["total"] == 10, by_selector(d2))
        s2 = api(admin, "/heatmaps?url_id=%s&device=desktop&range=1" % pricing)["scroll"]
        check("aggregated scroll equals live", s2 == s, s2)

    ov = api(admin, "/overview?range=1")
    k = ov["kpis"]
    check("overview sessions", k["sessions"] == 3, k)
    check("overview recorded", k["recorded"] == 1, k)
    check("rage sessions", k["flags"]["rage"] == 2, k["flags"])
    check("dead sessions", k["flags"]["dead"] == 3, k["flags"])
    check("error sessions", k["flags"]["error"] == 1, k["flags"])
    if CLI:
        check("quick back sessions", k["flags"]["quickback"] == 1, k["flags"])
    check("top error listed", any("Checkout widget failed" in e["message"] for e in ov["errors"]), ov["errors"])

    rec = api(admin, "/sessions?range=1&frustration=rage")
    check("recordings filtered by rage", rec["total"] == 1 and "rage" in rec["items"][0]["flags"], rec["total"])

    # Screens.
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    admin.wait_for_selector(".qcin-chart svg", timeout=15000)
    time.sleep(1)
    admin.screenshot(path=os.path.join(OUT, "p2-overview.png"), full_page=True)
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/heatmaps?url=%s" % contact)
    admin.wait_for_selector(".qcin-hm-device iframe", timeout=15000)
    time.sleep(4)
    admin.screenshot(path=os.path.join(OUT, "p2-heatmap-desktop.png"), full_page=True)
    admin.click(".qcin-segmented button[title='Mobile']")
    time.sleep(4)
    admin.screenshot(path=os.path.join(OUT, "p2-heatmap-mobile.png"), full_page=True)
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/heatmaps?url=%s" % pricing)
    admin.wait_for_selector(".qcin-hm-device iframe", timeout=15000)
    time.sleep(2)
    admin.click(".qcin-segmented button:has-text('Scroll')")
    time.sleep(3)
    admin.screenshot(path=os.path.join(OUT, "p2-heatmap-scroll.png"), full_page=True)
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/heatmaps?url=%s" % contact)
    admin.wait_for_selector(".qcin-hm-device iframe", timeout=15000)
    time.sleep(2)
    admin.select_option("#qcin-hm-source", "recording")
    time.sleep(5)
    admin.screenshot(path=os.path.join(OUT, "p2-heatmap-snapshot.png"), full_page=True)
    admin.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings?f=rage&r=1")
    admin.wait_for_selector(".qcin-row", timeout=15000)
    admin.screenshot(path=os.path.join(OUT, "p2-recordings-rage.png"), full_page=True)
    admin.locator(".qcin-row").first.click()
    admin.wait_for_selector(".qcin-markers", timeout=20000)
    try:
        # Markers from the recording arrive with its events (feature markers may come first).
        admin.wait_for_function("document.querySelectorAll('.qcin-marker').length >= 2", timeout=15000)
    except Exception:  # noqa: BLE001
        pass
    markers = admin.locator(".qcin-marker").count()
    check("player shows frustration markers", markers >= 2, markers)
    time.sleep(2)
    admin.screenshot(path=os.path.join(OUT, "p2-player.png"), full_page=True)
    browser.close()

print("\n%d failure(s)" % len(failures) if failures else "\nAll checks passed")
sys.exit(1 if failures else 0)
