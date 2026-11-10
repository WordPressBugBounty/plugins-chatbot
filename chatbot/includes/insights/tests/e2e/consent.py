"""
Consent and Do Not Track checks.
Before running, set Insights > Settings > Visitor consent to the second option
("Only record visitors who gave consent...") and make sure no consent plugin is active.
Expected: 0, 0, 1+ (recorder loads after consent), 0.
"""
from playwright.sync_api import sync_playwright
import time
import os
B=os.environ.get("QCIN_BASE","http://127.0.0.1:8899")
with sync_playwright() as pw:
    b=pw.chromium.launch()
    # 1. consent=always, no consent plugin -> nothing recorded
    c=b.new_context(); p=c.new_page(); hits=[]
    p.on("request", lambda r: hits.append(r.url) if "ingest" in r.url or "recorder.js" in r.url else None)
    p.goto(B+"/pricing/"); time.sleep(4); print("always/no-api requests:", len(hits)); c.close()
    # 2. fake WP Consent API: denied first, then allowed via event
    c=b.new_context(); p=c.new_page(); hits=[]
    p.add_init_script("window.__allow=false; window.wp_has_consent=function(){return window.__allow;};")
    p.on("request", lambda r: hits.append(r.url) if "ingest" in r.url or "recorder.js" in r.url else None)
    p.goto(B+"/pricing/"); time.sleep(3); print("api denied requests:", len(hits))
    p.evaluate("window.__allow=true; document.dispatchEvent(new CustomEvent('wp_listen_for_consent_change',{detail:{statistics:'allow'}}))")
    p.wait_for_function("window.qcInsights !== undefined", timeout=10000); p.mouse.wheel(0,300); time.sleep(3.5)
    print("api allowed requests:", len(hits), "localStorage:", p.evaluate("Object.keys(localStorage)")); c.close()
    # 3. DNT
    c=b.new_context(extra_http_headers={"DNT":"1"}); p=c.new_page(); hits=[]
    p.add_init_script("Object.defineProperty(navigator,'doNotTrack',{get:()=> '1'}); window.wp_has_consent=function(){return true;};")
    p.on("request", lambda r: hits.append(r.url) if "ingest" in r.url or "recorder.js" in r.url else None)
    p.goto(B+"/pricing/"); time.sleep(3); print("dnt requests:", len(hits), "localStorage:", p.evaluate("Object.keys(localStorage)")); c.close()
    b.close()
