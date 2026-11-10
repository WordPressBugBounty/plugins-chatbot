"""
Extension points of the core module (0.4.0), tested with a fixture feature.

Setup (once): copy fixture-extensions.php to wp-content/mu-plugins/qcin-ext-fixture.php
and fixture-extensions.js to the site's web root. The script switches the fixture
on with the option qcin_test_ext and off again at the end.
Needs QCIN_WP_CLI and the mock AI (QCIN_TEST_MOCK_AI). Run after e2e.py visit
(it needs at least one recording). It ends with "Delete all data now", so run
it last.

Checks: admin screens, settings sections (and a broken part that must not break
the screen), overview cards, recordings filters and badges, player actions,
panels and markers, heatmap modes, admin data, tracker extensions and ingest
"ext" data, session query filter, AI feature registry and generic runner
(cache, usage, feature switch), Mistral and Ollama providers, capability
contexts, health jobs, feature tables in "Delete all", session_deleted.
"""
import json
import os
import shlex
import subprocess
import sys
import time
import urllib.request
import uuid

from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
CLI = os.environ.get("QCIN_WP_CLI", "")
OUT = os.path.join(os.path.dirname(__file__), "shots")
os.makedirs(OUT, exist_ok=True)
failures = []


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + (" - " + str(detail)[:300] if detail else ""))
    if not ok:
        failures.append(name)


def wp(php):
    out = subprocess.run(shlex.split(CLI) + ["eval", php], capture_output=True, text=True)
    return out.stdout.strip()


def api(page, path, method="GET", body=None, root=None):
    return page.evaluate(
        """async ([path, method, body, root]) => {
            const a = window.qcInsightsAdmin;
            const base = root ? a.restRoot.replace(/\\/admin\\/?$/, root) : a.restRoot;
            const r = await fetch(base + path, { method, headers: { 'X-WP-Nonce': a.nonce, 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
            const t = await r.text();
            let j = null; try { j = JSON.parse(t); } catch (e) {}
            return { status: r.status, body: j, text: t.slice(0, 300) };
        }""",
        [path, method, body, root],
    )


if not CLI:
    print("Set QCIN_WP_CLI.")
    sys.exit(2)

ROOT = wp("echo ABSPATH;")
LOG = os.path.join(ROOT, "mock-ai.log")
with open(os.path.join(ROOT, "mock-ai.mode"), "w") as f:
    f.write("ok")


def mock_log():
    if not os.path.exists(LOG):
        return []
    with open(LOG) as f:
        return [json.loads(line) for line in f if line.strip()]


wp("update_option('qcin_test_ext','1'); delete_option('qcin_test_ext_seen'); delete_option('qcin_test_cap'); delete_option('qcin_test_delete_all'); delete_option('qcin_test_deleted');")
TOKEN = wp("echo get_option('qcin_site_token');")

# Ingest: a malformed "ext" object is rejected, a good one passes.
def ingest(ext):
    payload = {"v": 1, "sid": str(uuid.uuid4()), "vid": str(uuid.uuid4()), "pv": str(uuid.uuid4()), "seq": 0,
               "meta": {"url": BASE + "/contact/", "title": "Contact", "vw": 1200, "vh": 800}, "t": 1000, "scroll": 10,
               "docH": 1000, "clicks": [], "signals": [], "events": [], "ext": ext}
    req = urllib.request.Request(BASE + "/wp-json/qcin/v1/ingest?token=" + TOKEN, data=json.dumps(payload).encode(),
                                 headers={"Content-Type": "text/plain", "Origin": BASE, "User-Agent": "Mozilla/5.0 Chrome/120"})
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read() or b"{}")
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")


st, body = ingest({"Bad Name": [{}]})
check("ingest rejects a bad ext namespace", st == 400 and body.get("c") == "bad_ext", (st, body))
st, body = ingest({"demo": [{"n": 1}] * 201})
check("ingest rejects too many ext items", st == 400, (st, body))
st, body = ingest({"demo": [{"n": 3}]})
check("ingest accepts ext and passes it to qcin/ingest_ext", st == 200 and any(x["n"] == 3 for x in json.loads(wp("echo wp_json_encode(get_option('qcin_test_ext_seen'));") or "[]")), (st, body))

with sync_playwright() as pw:
    browser = pw.chromium.launch()

    # Tracker extension: loaded after the tracker, its data reaches the server.
    vctx = browser.new_context(viewport={"width": 1280, "height": 900})
    v = vctx.new_page()
    v.goto(BASE + "/contact/")
    v.wait_for_function("window.__qcinDemoPushed === true", timeout=15000)
    time.sleep(4)
    v.goto(BASE + "/pricing/")
    time.sleep(1)
    vctx.close()
    seen = json.loads(wp("echo wp_json_encode(get_option('qcin_test_ext_seen'));") or "[]")
    check("tracker extension data stored via qcin/ingest_ext", any(x["n"] == 7 and x["session"] > 0 for x in seen), seen)

    ctx = browser.new_context(viewport={"width": 1440, "height": 1000})
    page = ctx.new_page()
    errors = []
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("dialog", lambda d: d.accept())
    page.goto(BASE + "/wp-login.php")
    page.fill("#user_login", os.environ.get("QCIN_USER", "admin"))
    page.fill("#user_pass", os.environ.get("QCIN_PASS", "admin"))
    page.click("#wp-submit")
    page.wait_for_load_state()

    # Screens and admin data.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/demo")
    page.wait_for_selector("#demo-screen", timeout=15000)
    check("feature screen drawn with shared UI and its data", page.inner_text("#demo-screen") == "Demo screen world ui-ok", page.inner_text("#demo-screen"))
    check("feature screen in the navigation", page.locator(".qcin-nav", has_text="Demo screen").count() == 1)
    check("qcin/admin_data adds keys", page.evaluate("window.qcInsightsAdmin.demoFlag === true && window.qcInsightsAdmin.can.settings === true"))
    page.screenshot(path=os.path.join(OUT, "ext-screen.png"))

    # Settings sections, AI feature switch, broken part isolated.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
    page.wait_for_selector("#demo-section", timeout=15000)
    check("settings section drawn", "demo body" in page.inner_text("#demo-section"))
    check("a broken extension part does not break the screen", page.locator(".qcin-savebar button[type=submit]").count() == 1)
    page.wait_for_selector("#qcin-ai-demo", state="attached", timeout=15000)
    check("AI feature switch from qcin/ai_features", page.locator("label[for=qcin-ai-demo]").count() + page.locator("#qcin-ai-demo").count() >= 1)

    # Overview card.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.wait_for_selector("#demo-card", timeout=15000)
    check("overview card drawn with the range", page.inner_text("#demo-card") == "Demo card 7", page.inner_text("#demo-card"))

    # Recordings filter, badges, session query.
    all_list = api(page, "/sessions", "GET")["body"]
    first = api(page, "/sessions?demo=first", "GET")["body"]
    check("qcin/session_query narrows the list", all_list["total"] >= 1 and first["total"] == 1, (all_list["total"], first["total"]))
    check("qcin/admin_session adds fields", "demo" in all_list["items"][0], all_list["items"][0].keys())
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings")
    page.wait_for_selector("#qcin-f-demo", timeout=15000)
    page.wait_for_selector(".qcin-table .demo-badge", timeout=15000)
    check("recording badges drawn", page.locator(".qcin-table .demo-badge").count() == all_list["items"].__len__() if all_list["total"] <= 20 else True)
    page.select_option("#qcin-f-demo", "first")
    page.wait_for_function("document.querySelectorAll('.qcin-table tbody tr').length === 1", timeout=15000)
    check("recordings filter sends its value", page.locator(".qcin-table tbody tr").count() == 1)
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings?demo=first")
    page.wait_for_function("document.getElementById('qcin-f-demo') && document.getElementById('qcin-f-demo').value === 'first'", timeout=15000)
    check("recordings filter read from the URL", True)

    # Player parts.
    sid = all_list["items"][0]["id"]
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings/" + sid)
    page.wait_for_selector("#demo-panel", timeout=15000)
    check("player panel gets the session", page.inner_text("#demo-panel") == "Demo panel has-session", page.inner_text("#demo-panel"))
    check("player action drawn", page.locator("#demo-action").count() == 1)
    page.wait_for_selector(".qcin-markers", timeout=15000)
    check("player marker from a feature", "Demo marker" in page.inner_text(".qcin-markers") and "Moments on this page" in page.inner_text(".qcin-markers"), page.inner_text(".qcin-markers")[:200])
    page.screenshot(path=os.path.join(OUT, "ext-player.png"))

    # Heatmap mode.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/heatmaps")
    page.wait_for_selector(".qcin-segmented button:has-text('Demo map')", timeout=15000)
    page.wait_for_selector(".qcin-hm-device iframe", timeout=20000)
    page.click(".qcin-segmented button:has-text('Demo map')")
    page.wait_for_selector("#demo-mode-summary", timeout=10000)
    page.wait_for_function("(() => { const f = document.querySelector('.qcin-hm-device iframe'); try { return !!f.contentDocument.getElementById('demo-drawn'); } catch (e) { return false; } })()", timeout=20000)
    check("heatmap mode draws into the page and shows its summary", page.inner_text("#demo-mode-summary") == "Demo summary")

    # AI: generic runner with Mistral.
    if os.path.exists(LOG):
        os.remove(LOG)
    r = api(page, "/ai", "POST", {"enabled": True, "provider": "mistral", "providers": {"mistral": {"key": "mk-test-1234", "model": "mistral-small-latest"}}, "features": {"demo": True}})
    s = r["body"]["settings"]
    check("Mistral saved, key masked", s["provider"] == "mistral" and s["providers"]["mistral"]["keyHint"] == "1234" and "mk-test" not in r["text"], s["providers"]["mistral"])
    demo_before = api(page, "/ai")["body"]["usage"]["features"].get("demo", {}).get("requests", 0)
    wp("global $wpdb; $wpdb->query(\"DELETE FROM {$wpdb->prefix}qcin_ai_results WHERE feature='demo'\");")
    r1 = api(page, "/demo-ai", "POST", {"page": 5}, root="/test")
    check("runner returns parsed JSON", r1["status"] == 200 and len(r1["body"]["result"]["findings"]) == 2 and r1["body"]["cached"] is False, r1["text"])
    last = mock_log()[-1] if mock_log() else {}
    check("Mistral request: OpenAI format, bearer key, JSON mode", last.get("provider") == "mistral" and last.get("auth") == "Bearer mk-test-1234" and last["body"].get("response_format", {}).get("type") == "json_object", last)
    n = len(mock_log())
    r2 = api(page, "/demo-ai", "POST", {"page": 5}, root="/test")
    check("runner caches the same input", r2["body"]["cached"] is True and len(mock_log()) == n, r2["text"])
    ai = api(page, "/ai")["body"]
    check("usage logged under the feature id", ai["usage"]["features"].get("demo", {}).get("requests") == demo_before + 1, ai["usage"]["features"])
    check("feature listed with its label", any(f["id"] == "demo" and f["label"] == "Demo insight" for f in ai["settings"]["featureList"]))
    api(page, "/ai", "POST", {"features": {"demo": False}})
    r3 = api(page, "/demo-ai", "POST", {"page": 6}, root="/test")
    check("feature switch off blocks the runner", r3["status"] == 409, r3["status"])

    # Ollama: server address instead of a key.
    r = api(page, "/ai", "POST", {"provider": "ollama", "providers": {"ollama": {"url": BASE + "/", "model": "llama3.1"}}})
    o = r["body"]["settings"]["providers"]["ollama"]
    check("Ollama needs a URL, not a key", o["needsUrl"] is True and o["needsKey"] is False and o["url"] == BASE, o)
    check("Ollama counts as ready without a key", r["body"]["settings"]["ready"] is True, r["body"]["settings"]["label"])
    t = api(page, "/ai/test", "POST", {"provider": "ollama", "url": BASE, "model": "llama3.1"})
    last = mock_log()[-1]
    check("Ollama test: no Authorization header", t["body"]["ok"] is True and last["provider"] == "ollama" and last["auth"] == "", (t["body"], last["auth"]))
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
    page.wait_for_selector("#qcin-ai-provider", timeout=15000)
    page.select_option("#qcin-ai-provider", "ollama")
    check("Ollama shows the address field, not a key field", page.locator("#qcin-ai-url").count() == 1 and page.locator("#qcin-ai-key").count() == 0)
    page.select_option("#qcin-ai-provider", "mistral")
    check("Mistral shows a key field", page.locator("#qcin-ai-key").count() == 1 and page.locator("#qcin-ai-url").count() == 0)
    page.screenshot(path=os.path.join(OUT, "ext-ai.png"), full_page=True)
    api(page, "/ai", "POST", {"enabled": False, "provider": "openai"})

    # Health jobs.
    h = api(page, "/status?health=1")["body"]["health"]
    check("qcin/health_jobs adds a job", "qcin_test_demo_job" in h["jobs"], list(h["jobs"].keys()))

    # Capability contexts.
    wp("update_option('qcin_test_cap','1');")
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.reload()
    page.wait_for_selector(".qcin-nav", timeout=15000)
    navs = page.locator(".qcin-nav").all_inner_texts()
    check("no Settings screen without the settings capability", "Settings" not in navs and page.evaluate("window.qcInsightsAdmin.can.settings === false"), navs)
    check("settings routes refuse", api(page, "/settings", "POST", {"enabled": True})["status"] == 403 and api(page, "/ai")["status"] == 403)
    check("view routes still work", api(page, "/sessions")["status"] == 200)
    wp("delete_option('qcin_test_cap');")

    # session_deleted and Delete all with a feature table.
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.reload()
    page.wait_for_selector(".qcin-nav", timeout=15000)
    del_id = api(page, "/sessions")["body"]["items"][-1]["id"]
    row_id = int(wp("global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare(\"SELECT id FROM {$wpdb->prefix}qcin_sessions WHERE uuid=%s\", '" + del_id + "'));"))
    api(page, "/sessions/" + del_id, "DELETE")
    check("qcin/session_deleted fires with the id", row_id in json.loads(wp("echo wp_json_encode(get_option('qcin_test_deleted'));") or "[]"), row_id)
    wp("global $wpdb; $wpdb->insert($wpdb->prefix.'qcin_demo', array('session_id'=>1));")
    posts_before = wp("global $wpdb; echo (int) $wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->posts}\");")
    api(page, "/delete-all", "POST")
    check("Delete all empties feature tables", wp("global $wpdb; echo (int) $wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->prefix}qcin_demo\");") == "0")
    check("Delete all ignores tables outside qcin_", wp("global $wpdb; echo (int) $wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->posts}\");") == posts_before)
    check("qcin/delete_all fires", wp("echo get_option('qcin_test_delete_all');") == "1")

    check("no page errors except the deliberate one", all("boom" in e for e in errors), errors)
    browser.close()

wp("delete_option('qcin_test_ext'); delete_option('qcin_test_ext_seen'); delete_option('qcin_test_delete_all'); delete_option('qcin_test_deleted'); global $wpdb; $wpdb->query(\"DROP TABLE IF EXISTS {$wpdb->prefix}qcin_demo\");")

print()
print("All checks passed" if not failures else "FAILED: " + ", ".join(failures))
sys.exit(1 if failures else 0)
