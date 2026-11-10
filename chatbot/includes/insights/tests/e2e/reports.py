"""
Core 0.6.0: email reports.

Needs WP-CLI (QCIN_WP_CLI) and mail capture: in wp-config.php
    define( 'QCIN_TEST_SENDMAIL', '/path/to/tests/e2e/fake-sendmail.sh' );
with the test mu-plugin (mu-plugin-qcin-test.php) installed. Messages are
saved as .eml files in QCIN_MAIL_DIR (default /tmp/qcin-mail).
Seeds known visits with reports-seed.php, so it deletes all Insights data.

Checks: defaults (on, weekly, Monday, admin email), the daily job at 6:00
site time, the content of the email (numbers, comparison, links, redaction,
credit and upgrade line from the host, plain-text part), due/skip logic,
monthly periods, address validation, the Settings section (save, test,
preview, phone width) and the switch-off filter for features.
"""
import email
import email.policy
import glob
import json
import os
import shlex
import subprocess
import sys
import time

from playwright.sync_api import sync_playwright

BASE = os.environ.get("QCIN_BASE", "http://127.0.0.1:8899")
CLI = os.environ.get("QCIN_WP_CLI", "")
MAIL = os.environ.get("QCIN_MAIL_DIR", "/tmp/qcin-mail")
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "shots")
os.makedirs(OUT, exist_ok=True)
failures = []


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + (" - " + str(detail)[:300] if detail != "" else ""))
    if not ok:
        failures.append(name)


def wp(php):
    return subprocess.run(shlex.split(CLI) + ["eval", php], capture_output=True, text=True).stdout.strip()


def seed():
    return subprocess.run(shlex.split(CLI) + ["eval-file", os.path.join(HERE, "reports-seed.php")], capture_output=True, text=True).stdout.strip()


def mails():
    out = []
    for f in sorted(glob.glob(os.path.join(MAIL, "*.eml"))):
        with open(f, "rb") as fh:
            msg = email.message_from_binary_file(fh, policy=email.policy.default)
        html = msg.get_body(("html",))
        text = msg.get_body(("plain",))
        out.append({
            "to": str(msg["To"]),
            "subject": str(msg["Subject"]),
            "html": html.get_content() if html else "",
            "text": text.get_content() if text else "",
        })
    return out


def clear_mail():
    for f in glob.glob(os.path.join(MAIL, "*.eml")):
        os.remove(f)


def api(page, path, method="GET", body=None):
    return page.evaluate(
        """async ([path, method, body]) => {
            const a = window.qcInsightsAdmin;
            const r = await fetch(a.restRoot + path, { method, headers: { 'X-WP-Nonce': a.nonce, 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
            const t = await r.text();
            let j = null; try { j = JSON.parse(t); } catch (e) {}
            return { status: r.status, body: j, text: t.slice(0, 300) };
        }""",
        [path, method, body],
    )


def run_job():
    wp("delete_transient('qcin_lock_email_report'); do_action('qcin_email_report');")


def reset_job():
    # As if reports started long ago and nothing was sent for this period.
    wp("delete_option('qcin_report_last'); delete_option('qcin_report_checked'); update_option('qcin_report_since', 1);")


def send_now():
    wp("QuantumCloud\\Insights\\Reports\\Reports::send( true );")


if not CLI:
    print("Set QCIN_WP_CLI.")
    sys.exit(2)

old_tz = wp("echo get_option('timezone_string');")
wp("update_option('timezone_string', 'Asia/Dhaka'); delete_option('qcin_reports'); delete_option('qcin_report_last'); delete_option('qcin_report_result'); delete_option('qcin_report_checked'); delete_option('qcin_report_since'); delete_option('qcin_test_reports_off'); wp_clear_scheduled_hook('qcin_email_report');")
old_fmt = wp("echo get_option('date_format');")
clear_mail()

# Defaults and the daily job.
d = json.loads(wp("echo wp_json_encode(QuantumCloud\\Insights\\Reports\\Report_Settings::all());"))
admin_email = wp("echo get_option('admin_email');")
check("on by default, weekly, Monday, admin email", d == {"enabled": True, "frequency": "weekly", "day": 1, "recipients": admin_email}, d)
wp("do_action('init');")
nxt = wp("$t = wp_next_scheduled('qcin_email_report'); echo $t ? wp_date('H:i', $t) . ' ' . wp_get_schedule('qcin_email_report') : 'none';")
check("daily job at 6:00 site time", nxt == "06:00 daily", nxt)

# Periods.
per = json.loads(wp("$t = ( new DateTimeImmutable( '2026-10-01 07:00', wp_timezone() ) )->getTimestamp(); echo wp_json_encode( array( QuantumCloud\\Insights\\Reports\\Report_Builder::period( 'monthly', $t ), QuantumCloud\\Insights\\Reports\\Report_Builder::period( 'weekly', $t ) ) );"))
check("monthly period is the previous calendar month", per[0]["start"] == "2026-09-01" and per[0]["end"] == "2026-09-30" and per[0]["days"] == 30, per[0])
check("weekly period is the 7 days before today (site time)", per[1]["start"] == "2026-09-24" and per[1]["end"] == "2026-09-30" and per[1]["from"] == "2026-09-23 18:00:00", per[1])

print(seed())
today_w = int(wp("echo wp_date('w');"))

with sync_playwright() as pw:
    browser = pw.chromium.launch()
    ctx = browser.new_context(viewport={"width": 1440, "height": 1000})
    page = ctx.new_page()
    page.goto(BASE + "/wp-login.php")
    page.fill("#user_login", os.environ.get("QCIN_USER", "admin"))
    page.fill("#user_pass", os.environ.get("QCIN_PASS", "admin"))
    page.click("#wp-submit")
    page.wait_for_load_state()
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/overview")
    page.wait_for_selector(".qcin-nav", timeout=15000)

    r = api(page, "/reports")
    check("GET /reports", r["status"] == 200 and r["body"]["enabled"] is True and r["body"]["active"] is True and "admin-post.php" in r["body"]["previewUrl"], r["text"])

    # A test report with the seeded data.
    clear_mail()
    r = api(page, "/reports/test", "POST", {"recipients": "", "frequency": "weekly"})
    check("test report sent", r["status"] == 200 and r["body"]["sent"] is True, r["text"])
    m = mails()
    check("one email captured", len(m) == 1, len(m))
    m = m[-1] if m else {"to": "", "subject": "", "html": "", "text": ""}
    html, text = m["html"], m["text"]
    check("sent to the admin email", admin_email in m["to"], m["to"])
    site_name = wp("echo wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );")
    check("subject: site title, WPBot Site Insight, visits and frustration", m["subject"] == "[%s] WPBot Site Insight weekly report: 10 visits, 30%% frustrated" % site_name, m["subject"])
    check("heading names WPBot Site Insight", ">WPBot Site Insight weekly report</h2>" in html, "")
    fallback = wp("add_filter('qcin/report_branding', function ($b) { unset($b['report_name']); return $b; }); echo QuantumCloud\\Insights\\Reports\\Reports::branding()['report_name'];")
    check("without a host name the report uses the menu title", fallback == "Site Insight", fallback)
    check("headline: visits compared with the week before", "Visits" in html and ">10<" in html and "▲ 100%" in html, "")
    check("headline: pages, length, scroll, recorded", "2.0" in html and "1 min 0 s" in html and "60%" in html and ">7<" in html, "")
    check("frustration signals with links", "Rage clicks" in html and "Dead clicks" in html and "JavaScript errors" in html and "#/recordings?f=rage&#038;r=7" in html, "")
    check("signal up from 0 when the week before had visits", "Up from 0" in html and "No earlier data" not in html.split("Frustration signals")[1].split("Top pages")[0], "")
    check("visit outside the period not counted", "Quick backs" not in html and ">10<" in html)
    check("top pages with heatmap links", "Pricing" in html and "/contact/" in html and "#/heatmaps?url=" in html and "30 s · 50% scrolled" in html and "30 s · 70% scrolled" in html, "")
    check("error message redacted", "Checkout failed for" in html and "jane@example.com" not in html and "jane@example.com" not in text, "")
    check("devices, new and returning", "Desktop 60%" in html and "Mobile 30%" in html and "Tablet 10%" in html and "6 new · 4 returning" in html, "")
    check("sources and countries", "google.com (4)" in html and "newsletter (3)" in html and "Direct or unknown (3)" in html and ("United States (5)" in html or "US (5)" in html) and ("Bangladesh (3)" in html or "BD (3)" in html), "")
    check("busiest day", "Busiest day" in html and "(4 visits)" in html, "")
    check("open button and settings link", "#/overview" in html and "#/settings?section=reports" in html, "")
    check("WPBot credit linked to wpbot.pro", "Sent by Site Insight in WPBot" in html and "https://www.wpbot.pro/?utm_source=wpbot-free&#038;utm_medium=email-report&#038;utm_campaign=credit" in html and ">wpbot.pro</a>" in html, "")
    check("one WPBot Pro line", html.count("with WPBot Pro") == 1 and "utm_campaign=report-upgrade" in html, html.count("with WPBot Pro"))
    check("plain-text part", text.startswith("WPBot Site Insight weekly report") and "Visits: 10 (▲ 100%)" in text and "Sent by Site Insight in WPBot (https://www.wpbot.pro/" in text, text[:200])
    with open(os.path.join(OUT, "report-email.html"), "w") as fh:
        fh.write(html)

    r = api(page, "/reports/test", "POST", {})
    check("test is rate limited", r["status"] == 429, r["status"])
    time.sleep(16)
    clear_mail()
    r = api(page, "/reports/test", "POST", {"recipients": "a@example.com, b@example.com", "frequency": "monthly"})
    m = mails()
    check("test uses typed addresses and frequency", r["status"] == 200 and len(m) == 1 and "a@example.com" in m[0]["to"] and "b@example.com" in m[0]["to"] and "monthly report" in m[0]["subject"], (r["text"], [x["to"] for x in m]))

    # Validation.
    r = api(page, "/reports", "POST", {"recipients": "nope"})
    check("invalid address refused", r["status"] == 400 and "not a valid email" in r["text"], r["text"])
    r = api(page, "/reports", "POST", {"recipients": ", ".join("u%d@example.com" % i for i in range(11))})
    check("more than 10 addresses refused", r["status"] == 400, r["status"])
    r = api(page, "/reports", "POST", {"recipients": ""})
    check("empty list means the admin email", r["status"] == 200 and r["body"]["recipients"] == admin_email and wp("echo get_option('qcin_reports')['recipients'];") == "", r["text"])

    # The daily job sends when due, once.
    api(page, "/reports", "POST", {"enabled": True, "frequency": "weekly", "day": today_w})
    wp("delete_option('qcin_report_since'); delete_option('qcin_report_last'); delete_option('qcin_report_checked');")
    clear_mail()
    run_job()
    check("no surprise email on the first run after an update", len(mails()) == 0 and int(wp("echo (int) get_option('qcin_report_since');") or 0) > time.time() - 120, len(mails()))
    reset_job()
    clear_mail()
    run_job()
    m = mails()
    check("job sends on the chosen day", len(m) == 1 and "weekly report: 10 visits" in m[0]["subject"], len(m))
    last = wp("echo (int) get_option('qcin_report_last');")
    check("last sent saved", int(last or 0) > time.time() - 120, last)
    run_job()
    check("job does not send twice", len(mails()) == 1, len(mails()))
    api(page, "/reports", "POST", {"day": (today_w - 1) % 7})
    reset_job()
    clear_mail()
    run_job()
    check("missed day caught up on the next run", len(mails()) == 1, len(mails()))
    run_job()
    check("catch-up sends once", len(mails()) == 1, len(mails()))
    api(page, "/reports", "POST", {"day": (today_w - 3) % 7})
    reset_job()
    clear_mail()
    run_job()
    check("no catch-up after more than 2 days", len(mails()) == 0, len(mails()))
    api(page, "/reports", "POST", {"day": (today_w + 1) % 7})
    reset_job()
    run_job()
    check("job waits for the chosen day", len(mails()) == 0, len(mails()))
    api(page, "/reports", "POST", {"enabled": False, "day": today_w})
    reset_job()
    run_job()
    check("job does nothing when reports are off", len(mails()) == 0, len(mails()))
    wp("update_option('qcin_reports', array('enabled' => true, 'frequency' => 'monthly', 'day' => 1, 'recipients' => ''));")
    m1 = wp("$d = QuantumCloud\\Insights\\Reports\\Report_Settings::scheduled_day( ( new DateTimeImmutable( '2026-11-03 09:00', wp_timezone() ) )->getTimestamp() ); echo $d ? $d->format('Y-m-d') : 'none';")
    m2 = wp("$d = QuantumCloud\\Insights\\Reports\\Report_Settings::scheduled_day( ( new DateTimeImmutable( '2026-11-04 09:00', wp_timezone() ) )->getTimestamp() ); echo $d ? $d->format('Y-m-d') : 'none';")
    check("monthly catch-up until the 3rd", m1 == "2026-11-01" and m2 == "none", (m1, m2))
    wp("update_option('qcin_reports', array('enabled' => true, 'frequency' => 'weekly', 'day' => 1, 'recipients' => ''));")

    # Visitor-supplied text is cleaned.
    wp("global $wpdb; $p = $wpdb->prefix . 'qcin_'; $wpdb->query(\"UPDATE {$p}sessions SET utm_source = 'Call us at evil.example now' WHERE utm_source = 'newsletter' LIMIT 2\"); $sid = (int) $wpdb->get_var(\"SELECT id FROM {$p}sessions WHERE (flags & 8) > 0 LIMIT 1\"); $wpdb->insert($p . 'signals', array('session_id' => $sid, 'type' => 'error', 'data' => wp_json_encode(array('message' => 'Your site is hacked, visit https://evil.example/pay or call +1 555 123 4567')), 'created_at' => gmdate('Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS)));")
    wp("update_option('date_format', 'l, F j, Y');")
    clear_mail()
    send_now()
    m = mails()
    h = m[0]["html"] if m else ""
    t = m[0]["text"] if m else ""
    check("links removed from visitor text", "evil.example/pay" not in h and "https://evil" not in h and "[link]" in h and "evil.example/pay" not in t, "")
    check("phone numbers removed from visitor text", "555 123 4567" not in h and "555 123 4567" not in t, "")
    check("only plain campaign names shown", "Call us at" not in h and "Other campaign (2)" in h, "")
    check("errors marked as reported by browsers", "As reported by visitors" in h, "")
    import re as _re
    check("weekday not shown twice", not _re.search(r"(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), \1", h) and "Busiest day" in h, "")
    wp("update_option('date_format', '%s');" % old_fmt.replace("'", ""))
    seed()

    # Settings screen.
    api(page, "/reports", "POST", {"enabled": True, "frequency": "weekly", "day": 1, "recipients": ""})
    page.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings?section=reports")
    page.reload()
    page.wait_for_selector("#qcin-reports", timeout=15000)
    page.wait_for_timeout(900)
    top = page.evaluate("document.getElementById('qcin-reports').getBoundingClientRect().top")
    check("settings link scrolls to Email reports", -50 < top < 300, top)
    check("switch on, weekly, Monday", page.is_checked("#qcin-reports-enabled") and page.input_value("#qcin-reports-frequency") == "weekly" and page.input_value("#qcin-reports-day") == "1")
    check("next report shown", "Next report:" in page.inner_text("#qcin-reports"), page.inner_text("#qcin-reports")[:300])
    page.locator("#qcin-reports").screenshot(path=os.path.join(OUT, "report-settings.png"))
    page.select_option("#qcin-reports-frequency", "monthly")
    check("day hidden for monthly", page.locator("#qcin-reports-day").count() == 0)
    page.fill("#qcin-reports-to", "team@example.com, owner@example.com")
    with page.expect_response(lambda resp: "/admin/reports" in resp.url and resp.request.method == "POST", timeout=15000):
        page.click(".qcin-savebar button[type=submit]")
    page.wait_for_timeout(500)
    saved = json.loads(wp("echo wp_json_encode(QuantumCloud\\Insights\\Reports\\Report_Settings::all());"))
    check("saved from the screen", saved["frequency"] == "monthly" and saved["recipients"] == "team@example.com, owner@example.com", saved)
    time.sleep(16)
    clear_mail()
    page.click("text=Send a test now")
    page.wait_for_selector(".qcin-reports-test", timeout=15000)
    check("test from the screen", "Sent to team@example.com, owner@example.com" in page.inner_text(".qcin-reports-test") and len(mails()) == 1, page.inner_text(".qcin-reports-test"))
    check("test names the sender address", " from wordpress@" in page.inner_text(".qcin-reports-test"), page.inner_text(".qcin-reports-test"))
    check("no PHP mail() hint when the site uses another mailer", page.locator(".qcin-reports-hint").count() == 0)

    # A site that sends with PHP mail(): the screen suggests an SMTP plugin.
    def fake_phpmail(route):
        route.fulfill(status=200, content_type="application/json", body=json.dumps({"sent": True, "ok": True, "to": ["owner@example.com"], "from": "wordpress@example.com", "mailer": "mail"}))
    page.route("**/admin/reports/test*", fake_phpmail)
    page.click("text=Send a test now")
    page.wait_for_selector(".qcin-reports-hint", timeout=15000)
    check("PHP mail() hint suggests an SMTP plugin", "SMTP plugin" in page.inner_text(".qcin-reports-hint"))
    page.locator("#qcin-reports").screenshot(path=os.path.join(OUT, "report-settings-phpmail.png"))
    page.unroute("**/admin/reports/test*", fake_phpmail)

    # When sending fails, the mailer's own reason is shown.
    time.sleep(16)
    wp("update_option('qcin_test_mail_fail', 1);")
    r = api(page, "/reports/test", "POST", {})
    wp("delete_option('qcin_test_mail_fail');")
    check("failed send shows the mailer's reason", r["status"] == 502 and "The mailer said:" in r["text"], r["text"])
    href = page.get_attribute("#qcin-reports a.qcin-btn", "href")
    prev = page.request.get(href)
    check("preview opens the report", prev.status == 200 and "monthly report" in prev.text() and "Sent by Site Insight in WPBot" in prev.text(), prev.status)
    bad = page.request.get(href.split("_wpnonce=")[0] + "_wpnonce=bad")
    check("preview needs a valid nonce", bad.status in (403, 200) and "monthly report" not in bad.text(), bad.status)
    settings_text = page.inner_text(".qcin-settings")
    check("Pro preview renamed to AI summary in email reports", "AI summary in email reports" in settings_text and "Weekly email digest" not in settings_text)
    api(page, "/reports", "POST", {"frequency": "weekly", "day": 1, "recipients": ""})

    # Email at phone width.
    m = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True, storage_state=ctx.storage_state())
    mp = m.new_page()
    mp.set_content(html)
    mp.wait_for_timeout(300)
    overflow = mp.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
    check("email fits at phone width", overflow <= 1, overflow)
    mp.screenshot(path=os.path.join(OUT, "report-email-mobile.png"), full_page=True)
    mp.goto(BASE + "/wp-admin/admin.php?page=qcin-insights#/settings?section=reports")
    mp.wait_for_selector("#qcin-reports", timeout=15000)
    mp.wait_for_timeout(800)
    overflow = mp.evaluate("document.querySelector('.qcin-app').scrollWidth - document.querySelector('.qcin-app').clientWidth")
    check("settings section fits at phone width", overflow <= 1, overflow)
    m.close()
    dp = ctx.new_page()
    dp.set_viewport_size({"width": 760, "height": 1000})
    dp.set_content(html)
    dp.wait_for_timeout(300)
    dp.screenshot(path=os.path.join(OUT, "report-email.png"), full_page=True)
    dp.close()

    # No visits: the scheduled report is skipped.
    wp("( new QuantumCloud\\Insights\\Storage\\Session_Repository() )->truncate();")
    api(page, "/reports", "POST", {"day": today_w})
    reset_job()
    clear_mail()
    run_job()
    res = json.loads(wp("echo wp_json_encode(get_option('qcin_report_result'));") or "{}")
    check("skipped when the period had no visits", len(mails()) == 0 and res.get("skipped") is True, res)
    first_at = res.get("at")
    time.sleep(1)
    run_job()
    res2 = json.loads(wp("echo wp_json_encode(get_option('qcin_report_result'));") or "{}")
    check("a skipped period is not retried", res2.get("at") == first_at, (first_at, res2.get("at")))
    time.sleep(16)
    r = api(page, "/reports/test", "POST", {})
    m = mails()
    check("a test still goes out with no visits", r["status"] == 200 and len(m) == 1 and "no visits" in m[0]["subject"], m[0]["subject"] if m else "")

    # A feature that sends its own report switches this one off.
    wp("update_option('qcin_test_reports_off', 1);")
    page.reload()
    page.wait_for_selector(".qcin-savebar", timeout=15000)
    page.wait_for_timeout(800)
    check("section hidden when a feature replaces the report", page.locator("#qcin-reports").count() == 0 and page.evaluate("window.qcInsightsAdmin.reports === false"))
    seed()
    reset_job()
    clear_mail()
    run_job()
    check("job does not send when switched off by a feature", len(mails()) == 0, len(mails()))
    check("health report lists the job only while it is used", "qcin_email_report" not in api(page, "/status?health=1")["body"]["health"]["jobs"])
    wp("delete_option('qcin_test_reports_off');")
    check("health report lists the job", "qcin_email_report" in api(page, "/status?health=1")["body"]["health"]["jobs"])
    browser.close()

wp("update_option('timezone_string', '%s'); delete_option('qcin_reports'); delete_option('qcin_report_last'); delete_option('qcin_report_result'); delete_option('qcin_report_checked'); delete_option('qcin_report_since'); ( new QuantumCloud\\Insights\\Storage\\Session_Repository() )->truncate();" % old_tz.replace("'", ""))
print("\n%d failure(s)" % len(failures) if failures else "\nAll checks passed")
sys.exit(1 if failures else 0)
