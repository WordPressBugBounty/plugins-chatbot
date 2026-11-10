# End-to-end tests

Playwright (Python) scripts that drive a real WordPress site with WPBot and the Insights module.

## Setup

1. A local WordPress (any: Local, wp-env, Docker, or `php -S` with the SQLite Database Integration plugin; run the suites on MySQL or MariaDB before a release too, since SQLite accepts SQL that MariaDB rejects) with WPBot Free active and Insights built (`npm run build` in `includes/insights`).
2. Copy `mu-plugin-qcin-test.php` to `wp-content/mu-plugins/qcin-test.php`. It lets the headless browser be recorded (normally automated browsers are skipped), puts every visit in the heatmap sample and flushes every 2.5 s. **Never install it on a live site.**
3. Create two pages:
   - `/contact/` with a form: `#name` (text), `#email` (email), `#msg` (textarea), `#send` button and a link `#pricing-link` to `/pricing/`.
   - `/pricing/` with a tall section and the text "Bottom of pricing".
4. `pip install playwright && playwright install chromium`
5. For `ai.py`: copy `mock-ai.php` to the site's web root and add `define( 'QCIN_TEST_MOCK_AI', 'http://127.0.0.1:8899/mock-ai.php' );` to `wp-config.php`. The mu-plugin then sends every AI request to the mock instead of a real provider. **Never on a live site.**
6. For `reports.py`: add `define( 'QCIN_TEST_SENDMAIL', '/path/to/tests/e2e/fake-sendmail.sh' );` to `wp-config.php`. The mu-plugin then hands every email to that script, which saves it as an `.eml` file in `QCIN_MAIL_DIR` (default `/tmp/qcin-mail`) instead of sending it (make it executable first: `chmod +x fake-sendmail.sh`). **Never on a live site.**
7. Optional environment: `QCIN_BASE` (default `http://127.0.0.1:8899`), `QCIN_USER`, `QCIN_PASS` (default `admin` / `admin`).

## Run

```
python3 e2e.py onboarding   # turn recording on from the empty state
python3 e2e.py visit        # scripted visitor: types into the form, scrolls, goes to /pricing/
python3 e2e.py admin        # recordings list, replay both pages, save a setting
python3 e2e.py mobile       # admin at phone width
python3 consent.py          # consent "always" without a consent plugin, WP Consent API deny → allow, Do Not Track
python3 heatmaps.py         # 3 scripted visitors (recorded desktop, not-recorded desktop, iPhone): exact click counts,
                            # rage/dead/error/quick-back signals, scroll reach, overview, frustration filter, player markers
python3 ai.py               # needs QCIN_WP_CLI and the mock; run after heatmaps.py and e2e.py visit. AI settings copied once from
                            # WPBot (active provider, keys masked), copy again, test connection, 401, provider switch, typed and
                            # removed keys, summary (no typed values sent, cached), usage panel, daily limit, bad answers, phone width
python3 reports.py          # needs QCIN_WP_CLI and fake-sendmail.sh; seeds known visits (deletes all data). Email report numbers,
                            # comparison, links, redaction, credit and upgrade line, plain-text part, due/skip, monthly, Settings section
```

`heatmaps.py` deletes all Insights data first. Set `QCIN_WP_CLI` to a WP-CLI command (e.g. `QCIN_WP_CLI="wp --path=/var/www/html"`) to also run the background jobs and check that the hourly aggregates match the live counts and that the quick back is detected.

Screenshots go to `shots/`. After `visit`, check that typed values never reached the server:

```
for f in $(find wp-content/uploads/qc-insights-* -name "*.gz"); do zcat $f | grep -c "jane@example.com\|98765"; done   # all 0
```

Ingest endpoint checks (expect 403, 403, 400, 400):

```
curl -s -X POST "$BASE/wp-json/qcin/v1/ingest?token=nope" --data-binary '{}'
curl -s -X POST "$BASE/wp-json/qcin/v1/ingest?token=$TOKEN" -H 'Origin: https://evil.example' --data-binary '{}'
curl -s -X POST "$BASE/wp-json/qcin/v1/ingest?token=$TOKEN" -H 'User-Agent: Mozilla/5.0 Chrome/120' --data-binary '{"v":1,"sid":"x"}'
python3 -c "import gzip,sys; sys.stdout.buffer.write(gzip.compress(b'{'+b' '*(20*1024*1024)+b'}'))" > bomb.gz
curl -s -X POST "$BASE/wp-json/qcin/v1/ingest?token=$TOKEN" -H 'User-Agent: Mozilla/5.0 Chrome/120' --data-binary @bomb.gz
```

`TOKEN` is the `qcin_site_token` option.
