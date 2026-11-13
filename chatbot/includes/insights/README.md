# WPBot Insights (core module)

Self-hosted session recording, replay, heatmaps, frustration signals and AI session summaries for WordPress. A reusable module, not a plugin: a host plugin (WPBot Free, WPBot Pro, or another QuantumCloud plugin) bundles a copy and connects it with one adapter class.

Status: **0.6.4** — foundations, capture and replay, heatmaps and frustration signals, AI settings and AI session summaries, extension points for features (0.4–0.5), and email reports (0.6; 0.6.1 fixes a MariaDB 10.5+ syntax error and shows why a test email failed; 0.6.2 lets the host name the report, e.g. "WPBot Site Insight"; 0.6.3 makes the AI keys follow the host's AI Settings unless the owner saved Site Insight's own; 0.6.4 fixes OpenAI models newer than GPT-4, which reject a custom temperature, and takes its model list from WPBot Core's current catalogue when it is there). Pro features live in a separate module.

Requires PHP 7.2 or newer (the same as WordPress). On older PHP `insights.php` returns early: the host keeps working and Insights stays off.

Planning brief: https://claude.ai/code/artifact/06450f2e-4a84-4910-893c-b7742d3bd87f

## What it does today

- Records sampled, consented visits with rrweb. Every form input is masked in the browser before anything is sent; text can be masked or blocked by CSS selector or class (`qcin-mask`, `qcin-block`, `qcin-unmask`).
- Sends gzip batches to the site's own REST endpoint (`POST /wp-json/qcin/v1/ingest`) protected by a site token, Origin check, rate limits, size caps and gzip-bomb protection.
- Collects clicks and scroll depth on a separate heatmap sample (`heatmap_sample_rate`), including visits that are not recorded. Visits that are not recorded load only the light collector (~3 KB gz), not rrweb.
- Detects frustration signals in the browser: rage clicks, dead clicks and JavaScript errors; quick backs (A → B → A with less than 5 s on B) are found on the server when a session closes. Signals are markers on the replay timeline and filters in the recordings list.
- Stores recordings as gzip files in a protected uploads folder and metadata in nine tables (`qcin_sessions`, `qcin_pageviews`, `qcin_urls`, `qcin_selectors`, `qcin_clicks`, `qcin_heatmap_bins`, `qcin_scroll_bins`, `qcin_signals`, `qcin_ai_results`). Raw clicks are kept 7 days; hourly aggregates for 13 months.
- Admin screens (React on `wp.element`) inside the host's menu with the host's header and brand colours: Overview (sessions chart, KPIs, frustration, top pages, top JavaScript errors), Recordings (filters incl. frustration, replay player with signal markers), Heatmaps (click and scroll maps on the live page or a recorded snapshot; desktop, tablet, mobile; most clicked elements) and Settings (incl. a health check).
- Background jobs (WP-Cron): close idle sessions and detect quick backs every 5 minutes, aggregate heatmaps hourly, delete past retention and enforce the storage cap daily, health check daily.
- Registers a personal-data exporter and eraser (Tools → Export/Erase Personal Data). Recordings hold no personal data, so both report that nothing is stored.
- Consent: follows the WP Consent API (`statistics`), respects Do Not Track / Global Privacy Control, never records excluded roles or URLs.
- AI (off by default): its own AI section in Settings with an API key and model per provider (OpenAI, Claude, Gemini, Grok, OpenRouter), test connection, a switch per feature, daily/monthly token limits and a daily request limit (0 = no limit), and a usage panel. Keys stay on the server; the screen only gets their last 4 characters. Keys follow the host's AI settings live by default (`get_ai_import()` on the adapter; the host's active provider is used) and are not stored here; "Use own keys for Site Insight" keeps its own copy instead, and "Use … AI Settings instead" switches back (0.6.3). Sites that saved AI settings before 0.6.3 keep their own keys if any saved key, address or model differs from the host's, or if another provider with its own settings was chosen here; otherwise they follow. Switching to AI Settings keeps the own keys aside, and they come back when Site Insight uses its own keys again. Typing a key, address, model or another provider while following switches to own keys.
- Email reports (0.6.0, on by default): weekly (a chosen day, default Monday) or monthly (the previous month, on the 1st), at about 6:00 site time, to the admin email from Settings > General or up to 10 addresses. The report compares the period with the one before: visits, recorded visits, pages per visit, average visit length and scroll depth, visits with frustration, each frustration signal (with links to the matching recordings), the top 5 pages (with heatmap links), the top 3 JavaScript errors (redacted), devices, new and returning visitors, the top 3 traffic sources and countries, the busiest day, and warnings (tracking off, storage at 80% or more, background jobs not running). Totals only; page paths without query strings. HTML with a plain-text part. Skipped when a period had no visits. Settings > Email reports has "Send a test now" (it shows the sender address, the mailer's error when sending fails, and suggests an SMTP plugin when the site uses PHP `mail()`) and a preview. If the visit data can't be read, nothing is sent and the error is shown instead of an empty report. The subject is "[Site Title] {report name} weekly report: …"; the report name, the credit line and an optional upgrade line come from the host adapter (`get_report_branding()`).
- AI session summary on each recording: 2–3 sentences (goal, struggles, outcome) plus a frustration level. The provider gets a text digest of the visit (pages, clicked elements, fields used, frustration signals, JavaScript errors; about 2,000 tokens at most), never rrweb data or typed values; emails, phone and card-like numbers are removed again and URL query strings dropped. Results are cached in `qcin_ai_results` per visit, prompt version and model. Calls retry up to 3 times on rate-limit and server errors.

## Folder layout

```
insights.php            bootstrap a host includes (registers this copy's version)
loader.php              picks the newest bundled copy when several plugins bundle the module
uninstall-helper.php    call from the host's uninstall.php
src/                    PHP (namespace QuantumCloud\Insights, PSR-4 under src/)
assets/src/tracker/     loader.js (~1 KB gz), collector.js (~3 KB gz, clicks/scroll/signals only)
                        and recorder.js (~30 KB gz, rrweb + collector)
assets/src/admin/       admin app (JSX + CSS)
assets/build/           built files (not committed — run the build)
tests/e2e/              Playwright end-to-end scripts
```

## Building the JavaScript

Built files are not committed. Run this inside the module folder before testing or packaging:

```
npm install
npm run build        # or: npm run watch
```

The WPBot "Build test zip" GitHub Action runs this step automatically before zipping.

## Adding the module to a host plugin

1. Copy this folder to `includes/insights/` in the host (WPBot Free uses `includes/insights/`).
2. Write an adapter that extends `QuantumCloud\Insights\Host\Abstract_Host_Adapter` (see WPBot's `includes/class-wpbot-insights-adapter.php`).
   To offer the host's AI keys, return them from `get_ai_import()`: `array( 'active' => 'claude', 'providers' => array( 'claude' => array( 'key' => '…', 'model' => '…' ) ) )`.
   The adapter also names the menu item (`get_menu_title()`, WPBot uses "Site Insight") and places it (`get_menu_before()`: submenu slugs to sit above, first match wins; WPBot puts it above Automator).
3. Load it:

```php
require_once __DIR__ . '/includes/insights/insights.php';
add_action( 'qcin/loaded', function () {
	require_once __DIR__ . '/includes/class-my-insights-adapter.php';
	\QuantumCloud\Insights\Module::instance()->register_host( new My_Insights_Adapter() );
} );
```

**WPBot Free** loads it with one line in `qcld-wpwbot.php`, next to the other built-in modules (after the `includes/chat-sessions/wpbot-chat-sessions.php` line):

```php
// WPBot Insights: session recording and replay (includes/insights/).
require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-wpbot-insights.php' );
```

Rules: the module never calls host functions; everything host-specific goes through the adapter. No Pro checks in the module — Pro features register through `qcin/register_features`.

## Main hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `qcin/loaded` | action | Module classes are available; register the host adapter |
| `qcin/register_features` | action | Add features (Pro, other hosts) |
| `qcin/primary_host` | filter | Which host owns the admin menu |
| `qcin/should_track` | filter | Final decision to add the tracker to a request |
| `qcin/tracker_config` | filter | Config sent to the browser |
| `qcin/default_mask_selectors` | filter | Areas masked by default (WooCommerce account, order, address) |
| `qcin/allowed_query_params` | filter | Query params kept in stored URLs |
| `qcin/allowed_origins` | filter | Extra hosts accepted by the ingest endpoint |
| `qcin/ingest_rate_limit` | filter | Per-IP and site-wide limits per minute |
| `qcin/is_bot` | filter | Bot detection |
| `qcin/country` | filter | Country code when no CDN header is present |
| `qcin/recording_store` | filter | Replace local file storage (e.g. S3 in Pro) |
| `qcin/schema` | filter | Add tables |
| `qcin/admin_screens` | filter | Add screens to the Insights navigation |
| `qcin/privacy_policy_text` | filter | Suggested privacy-policy text |
| `qcin/frustration_rules` | filter | Thresholds for rage and dead clicks sent to the browser |
| `qcin/signal_recorded` | action | A frustration signal was stored (signal, session id, pageview id) |
| `qcin/session_finalized` | action | Idle sessions were closed (session ids) |
| `qcin/ai_providers` | filter | Provider catalog: labels, endpoints, default and suggested models |
| `qcin/ai_endpoint` | filter | URL an AI request goes to (proxy, gateway, tests) |
| `qcin/ai_client` | filter | Replace the AI client |
| `qcin/ai_digest` | filter | Visit digest sent for a summary |
| `qcin/ai_summary_created` | action | A session summary was stored (result, session row) |

Browser API (after the recorder or collector loads): `qcInsights.on(name, fn)`, `qcInsights.event(name, data)`, `qcInsights.tag(key, value)`, `qcInsights.push(namespace, item)` (feature data sent with the next batch, 0.4.0), `qcInsights.getContext()`, `qcInsights.registerDetector(fn)`, `qcInsights.stop()`.

### Extension points (0.4.0)

Generic hooks any host or feature can use. The core never checks for Pro; features plug in here.

| Hook | Type | Purpose |
| --- | --- | --- |
| `qcin/admin_scripts`, `qcin/admin_styles` | filter | Feature scripts and styles on the Insights screen, loaded after the app |
| `qcin/admin_data` | filter | Extra keys in `window.qcInsightsAdmin` (never secrets) |
| `qcin/admin_session` | filter | Extra fields on each session in the admin API (list and player) |
| `qcin/session_query` | filter | Extra conditions for the recordings list (`where` with placeholders + `params`); other request params arrive in `$filters['extra']` |
| `qcin/tracker_extensions` | filter | Extra tracker scripts (`id`, `src`, `when` = any / recorded / collected), loaded only after the tracker starts |
| `qcin/ingest_ext` | action | Feature data from a batch (`ext`: up to 8 namespaces, 200 items each, 32 KB), after all ingest checks |
| `qcin/capability` | filter | Capability per context: `view` (screens) and `settings` (settings, AI keys, delete all); use `Module::can( $context )` |
| `qcin/ai_features` | filter | AI features with their own switch (label, help, default). Add it on `qcin/loaded` |
| `qcin/schema_tables` | filter | Feature tables (`{prefix}qcin_*`) emptied by "Delete all" and dropped on uninstall. Features create their own tables with their own version option |
| `qcin/health_jobs` | filter | Feature cron hooks shown in the health report (call `Health_Check::record_run()`) |
| `qcin/delete_all` | action | After "Delete all data now" |
| `qcin/session_deleted` | action | A session's rows were deleted (retention, cap, Delete, eraser) |
| `qcin/storage_used_bytes`, `qcin/oldest_query` | filter | (0.4.1) What the storage limit counts and which sessions it may delete, for stores that move recordings off this server |
| `qcin/segment` | filter | (0.5.0) Request params (e.g. `seg=12`) → a condition on sessions (alias `s`, `where` with placeholders + `params`), applied to the Overview, Heatmaps (clicks then come from raw clicks) and the Recordings list. `Segment::from_params()` |
| `qcin/signal_types` | filter | (0.5.0) Extra frustration signal types: `type => [ bit (256 or higher), label, short, help ]`. The browser sends them through `registerDetector`; they set session flags, count on the Overview, filter Recordings and show as badges and markers |
| `qcin/raw_click_days` | filter | (0.5.0) Days raw clicks are kept (1-90, default 7); segmented click heatmaps cover this window |
| `qcin/email_reports` | filter | (0.6.0) Return false when a feature sends its own report; the core job and its Settings section turn off. `Report_Settings::all()` gives the owner's settings (enabled, frequency, day, recipients) to reuse |
| `qcin/report_sections` | filter | (0.6.0) Extra sections in the core report: `title`, `rows` (label, value, optional URL), `note`. Totals only |
| `qcin/report_warnings` | filter | (0.6.0) Plain-text warnings at the top of the report |
| `qcin/report_branding` | filter | (0.6.0) Report name in the subject and heading (`report_name`, 0.6.2; default the menu title), credit and upgrade line (`credit_text`, `credit_url`, `credit_label`, `promo_text`, `promo_url`); defaults come from the adapter's optional `get_report_branding()` |
| `qcin/report_email` | filter | (0.6.0) The email just before sending (`to`, `subject`, `html`, `text`, `headers`, `attachments`) |
| `qcin/report_sent` | action | (0.6.0) A report was sent (`to`, `subject`; facts; whether it was a test) |

`Insight_Service::run( $feature, $subject, $prompt_version, $messages, $opts )` runs any registered AI feature with the shared client, cache (`qcin_ai_results`), cost limits and usage log. `Insight_Service::get_result()` reads a stored result.

Admin app (`window.qcInsightsUI` shares `api`, `Section`, `Toggle`, `NumberField`, `TextArea`, `Icon`, `format`, `SIGNALS`, `RANGES`, `ExtPart`). Feature scripts add parts with `wp.hooks` filters: `qcin.admin.screens`, `qcin.admin.settingsSections` (0.5.1: sections also get `save`), `qcin.admin.overviewCards`, `qcin.admin.recordingsFilters`, `qcin.admin.recordingsTools` (0.5.1: a part under the Recordings filters, e.g. a search box), `qcin.admin.recordingBadges`, `qcin.admin.playerActions`, `qcin.admin.playerPanels`, `qcin.admin.playerMarkers`, `qcin.admin.heatmapModes`, `qcin.admin.screenFilters` (0.5.0: a select on the Overview, Heatmaps and Recordings, e.g. saved segments; the value is sent as a request param and kept across screens). See `assets/src/admin/extend.js` for the shapes. A part that throws is hidden; the screen keeps working.

AI providers: OpenAI, Claude, Gemini, Grok, OpenRouter, Mistral and Ollama (self-hosted: a server address instead of a key; `Host_Adapter::get_ai_import()` passes `url`).

## Text domain

Source strings use the placeholder domain `qcin-textdomain` (PHP and the admin app, which loads its translations with `wp_set_script_translations`). The host's release build replaces it with the host's text domain so translations come from the host's language pack. WPBot Free's `build-scripts/zip-plugin.js` does this while zipping (`chatbot`), so the copy in the repository stays identical to this one. By hand:

```
grep -rl "qcin-textdomain" includes/insights | xargs sed -i "s/qcin-textdomain/chatbot/g"
```

## Testing

See `tests/e2e/README.md`. The scripts record a scripted visit, check that typed form values never reach the server, replay it in the admin, test consent, Do Not Track and the ingest endpoint's rejections, and check heatmap counts, frustration signals and the overview against known scripted clicks, and test the AI settings and session summaries against a local mock provider. `extensions.py` (with `fixture-extensions.php` and `.js`) tests every extension point above; `segments.py` tests segments and extra signal types with the same fixture. `reports.py` (0.6.0) seeds known visits with `reports-seed.php` and checks the email report; mail is captured by `fake-sendmail.sh` (`QCIN_TEST_SENDMAIL` in wp-config.php).

## License

GPL-2.0-or-later. Bundles rrweb, rrweb-player and fflate (MIT).
