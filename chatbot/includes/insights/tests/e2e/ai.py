"""
Phase 3 end-to-end test: Site Insight's own AI settings and AI session summaries.

Needs WP-CLI (QCIN_WP_CLI) and the mock provider from README.md (mock-ai.php in the
web root, QCIN_TEST_MOCK_AI in wp-config.php). Run heatmaps.py and `e2e.py visit` first
so there are recordings (one with typed form values, one with rage clicks).

Checks:
  - first visit copies keys and models from WPBot AI Settings and selects WPBot's active provider
  - the full API key never reaches the browser (only the last 4 characters)
  - later changes in WPBot do not change Site Insight's settings; "Copy again" does, after confirming
  - test connection, wrong key (401), provider switch, typed key, remove key
  - with AI off the recording links to the AI section of Settings
  - summary: sent digest has no typed values or query strings, result shown and cached
  - usage panel counts, daily request limit stops further calls and the screen says so

Usage: QCIN_WP_CLI="wp --path=/path/to/site" python3 ai.py
"""
import json, os, shlex, subprocess, sys, time
from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
CLI = os.environ.get("QCIN_WP_CLI", "")
OUT = os.path.join(os.path.dirname(__file__), "shots")
os.makedirs(OUT, exist_ok=True)
failures = []

CLAUDE_KEY = "sk-ant-test-BBBB9876"
OPENAI_KEY = "sk-test-openai-AAAA1234"
TYPED = ["jane@example.com", "98765", "Jane Secret", "private message"]
# Host plugin under test: "wpbot" (WPBot Free, default) or "wpbot-pro" (WPBot Pro).
HOST = os.environ.get("QCIN_HOST", "wpbot")
HOST_NAME = "WPBot Pro" if HOST == "wpbot-pro" else "WPBot"


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + (" - " + str(detail)[:300] if detail else ""))
    if not ok:
        failures.append(name)


def wp(php):
    out = subprocess.run(shlex.split(CLI) + ["eval", php], capture_output=True, text=True)
    return out.stdout.strip()


def api(page, path, method="GET", body=None):
    return page.evaluate(
        """async ([path, method, body]) => {
            const a = window.qcInsightsAdmin;
            const r = await fetch(a.restRoot + path, { method, headers: { 'X-WP-Nonce': a.nonce, 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
            return { status: r.status, body: await r.text() };
        }""",
        [path, method, body],
    )


ROOT = wp("echo ABSPATH;")
LOG = os.path.join(ROOT, "mock-ai.log")
MODE = os.path.join(ROOT, "mock-ai.mode")


def mock_mode(mode):
    with open(MODE, "w") as f:
        f.write(mode)


def mock_log():
    if not os.path.exists(LOG):
        return []
    with open(LOG) as f:
        return [json.loads(line) for line in f if line.strip()]


if not CLI:
    print("Set QCIN_WP_CLI.")
    sys.exit(2)

# Host AI Settings: OpenAI has a key but is off; Claude is the active provider.
# WPBot Pro keeps the OpenAI model in openai_model and also has Mistral and Ollama.
wp(
    f"""
update_option('open_ai_api_key','{OPENAI_KEY}'); update_option('ai_enabled',0); update_option('openai_engines','gpt-4o'); update_option('openai_model','gpt-4o');
delete_option('qcld_openai_custom_model');
update_option('qcld_claude_api_key','{CLAUDE_KEY}'); update_option('qcld_claude_enabled',1); update_option('qcld_claude_model','claude-sonnet-4-6');
foreach (array('qcld_gemini_api_key','qcld_grok_api_key','qcld_openrouter_api_key','qcld_mistral_api_key','qcld_ollama_enabled','qcld_gemini_enabled','qcld_grok_enabled','qcld_openrouter_enabled','qcld_mistral_enabled') as $o) delete_option($o);
delete_option('qcin_ai'); delete_option('qcin_ai_usage');
global $wpdb; $wpdb->query("DELETE FROM {{$wpdb->prefix}}qcin_ai_results");
"""
)
mock_mode("ok")
if os.path.exists(LOG):
    os.remove(LOG)

with sync_playwright() as pw:
    browser = pw.chromium.launch()
    ctx = browser.new_context(viewport={"width": 1440, "height": 1000})
    page = ctx.new_page()
    page.on("dialog", lambda d: d.accept())
    page.goto(BASE + "/wp-login.php")
    page.fill("#user_login", os.environ.get("QCIN_USER", "admin"))
    page.fill("#user_pass", os.environ.get("QCIN_PASS", "admin"))
    page.click("#wp-submit")
    page.wait_for_load_state("networkidle")

    # Keys never sent to the browser.
    html_seen = []
    page.on("response", lambda r: html_seen.append(r) if "qcin" in r.url or "page=qcin" in r.url else None)

    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
    page.wait_for_selector("#qcin-ai-provider", timeout=15000)
    check("provider defaults to WPBot's active provider", page.input_value("#qcin-ai-provider") == "claude", page.input_value("#qcin-ai-provider"))
    check("model copied from WPBot", page.input_value("#qcin-ai-model") == "claude-sonnet-4-6", page.input_value("#qcin-ai-model"))
    ph = page.get_attribute("#qcin-ai-key", "placeholder") or ""
    check("key shown as last 4 characters", "9876" in ph and CLAUDE_KEY not in ph, ph)
    imp = page.inner_text(".qcin-ai-import")
    check("import note names WPBot and both providers", ("Copied from %s AI Settings" % HOST_NAME) in imp and "OpenAI" in imp and "Claude" in imp, imp)
    check("AI off by default", not page.is_checked("#qcin-ai-enabled"))
    page.screenshot(path=os.path.join(OUT, "ai-settings.png"), full_page=True)

    body = api(page, "/ai")["body"] + page.content()
    for r in list(html_seen):
        try:
            body += r.text()
        except Exception:
            pass
    check("full API keys never in page or API responses", CLAUDE_KEY not in body and OPENAI_KEY not in body)

    # Later changes in WPBot do not change Site Insight.
    wp("update_option('qcld_claude_model','claude-haiku-4-5'); update_option('qcld_claude_api_key','sk-ant-new-CCCC5555');")
    page.reload()
    page.wait_for_selector("#qcin-ai-provider")
    check("WPBot change does not leak in", page.input_value("#qcin-ai-model") == "claude-sonnet-4-6" and "9876" in (page.get_attribute("#qcin-ai-key", "placeholder") or ""))

    # Test connection works while AI is still off; uses the saved Claude key.
    page.click("text=Test connection")
    page.wait_for_selector(".qcin-ai-test .qcin-ai-ok, .qcin-ai-test .qcin-ai-bad", timeout=20000)
    msg = page.inner_text(".qcin-ai-test")
    last = mock_log()[-1]
    check("test connection OK", "Connected" in msg, msg)
    check("test used Claude with the saved key", last["provider"] == "claude" and last["auth"] == "x-api-key " + CLAUDE_KEY and last["body"]["model"] == "claude-sonnet-4-6", last)

    # Wrong key.
    mock_mode("401")
    page.click("text=Test connection")
    page.wait_for_selector(".qcin-ai-test .qcin-ai-bad", timeout=20000)
    check("401 explained", "did not accept the API key" in page.inner_text(".qcin-ai-test"), page.inner_text(".qcin-ai-test"))
    mock_mode("ok")

    # Recording with AI off links to Settings > AI.
    sessions = json.loads(api(page, "/sessions", "GET")["body"])["items"]
    rage = next(s for s in sessions if "rage" in s["flags"] and s["pageviews"] > 1)
    typed = next(s for s in sessions if s["id"] != rage["id"] and s["pageviews"] > 1 and s["bytes"] > 0)
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings/" + rage["id"])
    page.wait_for_selector(".qcin-ai-summary h3", timeout=15000)
    page.wait_for_selector(".qcin-ai-summary a.qcin-btn", timeout=15000)
    link = page.get_attribute(".qcin-ai-summary a.qcin-btn", "href")
    check("AI unset: summary box links to AI settings", link == "#/settings?section=ai", link)
    page.click(".qcin-ai-summary a.qcin-btn")
    page.wait_for_selector("#qcin-ai-provider")
    page.wait_for_timeout(900)
    top = page.evaluate("document.getElementById('qcin-ai').getBoundingClientRect().top")
    check("link scrolls to the AI section", -50 < top < 300, top)

    # Turn AI on and save.
    page.click("label[for=qcin-ai-enabled]")
    page.click(".qcin-savebar button[type=submit]")
    page.wait_for_selector(".qcin-toast--success", timeout=10000)
    check("AI saved on", json.loads(api(page, "/ai")["body"])["settings"]["enabled"] is True)

    # Summarize the rage-click visit.
    n0 = len(mock_log())
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings/" + rage["id"])
    page.wait_for_selector("text=Summarize visit", timeout=15000)
    page.click("text=Summarize visit")
    page.wait_for_selector(".qcin-ai-summary__text", timeout=30000)
    text = page.inner_text(".qcin-ai-summary")
    check("summary shown", "coupon" in text and "Compare plans and use a coupon" in text and "High frustration" in text, text)
    sent = mock_log()[-1]["body"]
    digest = sent["messages"][0]["content"] if sent.get("messages") else ""
    check("one request sent", len(mock_log()) == n0 + 1)
    check("digest has rage click and pages", "rage-click" in digest and "view /contact/" in digest and "view /pricing/" in digest, digest)
    check("system prompt sent as Claude system field", "UX analyst" in sent.get("system", ""))
    page.screenshot(path=os.path.join(OUT, "ai-summary.png"), full_page=False)

    # Cached: reload shows the same summary without a new request.
    page.reload()
    page.wait_for_selector(".qcin-ai-summary__text", timeout=15000)
    check("summary cached (no new request)", len(mock_log()) == n0 + 1, len(mock_log()) - n0)

    # Visit with typed values: none of them is sent.
    r = api(page, "/sessions/" + typed["id"] + "/summary", "POST", {})
    sent = json.dumps(mock_log()[-1]["body"])
    check("summary of the form visit created", r["status"] == 200, r["body"][:200])
    check("typed values never sent", not any(t in sent for t in TYPED), [t for t in TYPED if t in sent])
    check("fields named without values", "filled in" in sent and "value hidden" in sent, sent[:400])
    check("no query strings in digest", "?" not in mock_log()[-1]["body"]["messages"][0]["content"].split("Visit log:")[-1].replace("…", ""))

    # Copy again from WPBot (confirm accepted by the dialog handler).
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings")
    page.wait_for_selector("text=Copy again from %s AI Settings" % HOST_NAME, timeout=15000)
    page.click("text=Copy again from %s AI Settings" % HOST_NAME)
    page.wait_for_function("(document.getElementById('qcin-ai-key')||{}).placeholder && document.getElementById('qcin-ai-key').placeholder.indexOf('5555') !== -1", timeout=10000)
    check("copy again replaces key and model", page.input_value("#qcin-ai-model") == "claude-haiku-4-5")
    check("copy again keeps AI on", page.is_checked("#qcin-ai-enabled"))

    # Switch to OpenAI (key copied from WPBot) and summarize again.
    page.select_option("#qcin-ai-provider", "openai")
    check("OpenAI key hint", "1234" in (page.get_attribute("#qcin-ai-key", "placeholder") or ""))
    page.fill("#qcin-ai-model", "gpt-4o-mini")
    # The "Copy again" toast may still be showing: wait for this save's answer.
    with page.expect_response(lambda resp: "/admin/ai" in resp.url and resp.request.method == "POST", timeout=15000):
        page.click(".qcin-savebar button[type=submit]")
    page.wait_for_timeout(300)
    r = api(page, "/sessions/" + rage["id"] + "/summary", "POST", {"force": True})
    last = mock_log()[-1]
    check("OpenAI request format", last["provider"] == "openai" and last["auth"] == "Bearer " + OPENAI_KEY and last["body"].get("max_completion_tokens") == 500 and last["body"].get("response_format", {}).get("type") == "json_object", last["body"].keys())
    check("summary label shows new model", "gpt-4o-mini" in json.loads(r["body"])["summary"]["model"], r["body"][:200])

    # Gemini with a typed key, then remove it.
    page.select_option("#qcin-ai-provider", "gemini")
    page.fill("#qcin-ai-key", "AIza-test-DDDD0000")
    page.click("text=Test connection")
    page.wait_for_selector(".qcin-ai-test .qcin-ai-ok, .qcin-ai-test .qcin-ai-bad", timeout=20000)
    last = mock_log()[-1]
    check("Gemini test uses the typed key", last["provider"] == "gemini" and last["auth"] == "goog AIza-test-DDDD0000" and "generationConfig" in last["body"], last)
    # The toast of the previous save may still be showing: wait for this save's answer.
    with page.expect_response(lambda resp: "/admin/ai" in resp.url and resp.request.method == "POST", timeout=15000):
        page.click(".qcin-savebar button[type=submit]")
    data = json.loads(api(page, "/ai")["body"])
    check("Gemini key saved, provider switched", data["settings"]["providers"]["gemini"]["keyHint"] == "0000" and data["settings"]["provider"] == "gemini")
    page.reload()
    page.wait_for_selector("#qcin-ai-provider")
    page.click("text=Remove key")
    page.click(".qcin-savebar button[type=submit]")
    page.wait_for_selector(".qcin-toast--success", timeout=10000)
    data = json.loads(api(page, "/ai")["body"])
    check("Gemini key removed, AI not ready", data["settings"]["providers"]["gemini"]["hasKey"] is False and data["settings"]["ready"] is False)

    # Back to OpenAI; usage panel; daily request limit.
    api(page, "/ai", "POST", {"provider": "openai"})
    data = json.loads(api(page, "/ai")["body"])
    reqs = data["usage"]["day"]["requests"]
    check("usage counted (failed calls without tokens are not)", reqs == 5 and data["usage"]["features"]["summary"]["requests"] == 3 and data["usage"]["day"]["tokens"] == 5100, data["usage"])
    page.reload()
    page.wait_for_selector(".qcin-ai-usage table", timeout=15000)
    check("usage panel lists features", "Session summaries" in page.inner_text(".qcin-ai-usage") and "Connection tests" in page.inner_text(".qcin-ai-usage"))
    page.fill("#qcin-ai-dayreq", str(reqs))
    page.click(".qcin-savebar button[type=submit]")
    page.wait_for_selector(".qcin-toast--success", timeout=10000)
    page.wait_for_selector(".qcin-ai-usage .qcin-notice--error", timeout=10000)
    check("settings say AI is paused", "daily limit" in page.inner_text(".qcin-ai-usage"))
    n1 = len(mock_log())
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings/" + rage["id"])
    page.wait_for_selector(".qcin-ai-summary .qcin-notice--error", timeout=15000)
    check("recording says AI is paused", "daily limit" in page.inner_text(".qcin-ai-summary"))
    r = api(page, "/sessions/" + rage["id"] + "/summary", "POST", {"force": True})
    check("limit stops the call", r["status"] == 429 and len(mock_log()) == n1, r["status"])

    # Bad answer is rejected, not saved.
    api(page, "/ai", "POST", {"limits": {"daily_requests": 0}})
    mock_mode("bad")
    r = api(page, "/sessions/" + rage["id"] + "/summary", "POST", {"force": True})
    check("unreadable answer rejected", r["status"] == 502, r["body"][:200])
    mock_mode("ok")

    # Mobile layout.
    m = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True, storage_state=ctx.storage_state())
    mp = m.new_page()
    mp.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings?section=ai")
    mp.wait_for_selector("#qcin-ai-provider", timeout=15000)
    mp.wait_for_timeout(800)
    overflow = mp.evaluate("document.querySelector('.qcin-app').scrollWidth - document.querySelector('.qcin-app').clientWidth")
    check("AI settings fit at phone width", overflow <= 1, overflow)
    mp.screenshot(path=os.path.join(OUT, "ai-settings-mobile.png"), full_page=False)
    mp.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/recordings/" + rage["id"])
    mp.wait_for_selector(".qcin-ai-summary__text", timeout=15000)
    mp.locator(".qcin-ai-summary").scroll_into_view_if_needed()
    mp.screenshot(path=os.path.join(OUT, "ai-summary-mobile.png"), full_page=False)

    browser.close()

print()
print("All checks passed" if not failures else "FAILED: " + ", ".join(failures))
sys.exit(1 if failures else 0)
