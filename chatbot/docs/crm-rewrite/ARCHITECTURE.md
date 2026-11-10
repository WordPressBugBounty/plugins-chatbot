# WPBot CRM — Architecture Spec (clean-room rewrite)

Owner: QuantumCloud. Status: approved design for the rewrite of the email-marketing backend.

This document plus `API-CONTRACT.md` (derived only from WPBot's own React UI in
`includes/admin/marketing-src/`) are the **only** inputs for the implementation.

## 0. Clean-room rules (mandatory for every implementer)

- Do **not** open, search, or copy from:
  - `wp-content/plugins/ai-marketing-expert/` (any file)
  - `wp-content/plugins/chatbot/includes/admin/marketing-backend/` (legacy, being deleted)
  - `wp-content/plugins/chatbot/includes/ai-wpbot-seo-marketing/`
  - the backup folder `app/backup-marketing-backend-2026-10-26/`
- You may read any other WPBot code (e.g. `includes/insights/`, `qcld-wpwbot.php`, `qcld-wpwbot-search.php`) and WordPress core docs.
- Write all code from this spec. When the spec is silent, choose the simplest reasonable behaviour and note it in `docs/crm-rewrite/DECISIONS.md`.

## 1. Location, naming, conventions

| Item | Value |
|---|---|
| Folder | `includes/ai-marketing/` |
| PHP namespace | `QuantumCloud\WPBot\CRM` (sub-namespaces `Http`, `Domain`, `Mail`, `Jobs`, `Integrations`, `Support`) |
| Autoload | `includes/ai-marketing/autoload.php`: PSR-4-style `spl_autoload_register`, file name = class name (e.g. `Domain/ContactRepository.php`) |
| Bootstrap | `includes/ai-marketing/bootstrap.php`, required once from `qcld-wpwbot.php` |
| DB table prefix | `{$wpdb->prefix}wpbot_crm_` |
| Option prefix | `wpbot_crm_` |
| Hook prefix | `wpbot_crm/` (e.g. `wpbot_crm/contact_created`) |
| REST namespace | `wpbot-crm/v1` |
| Text domain | `chatbot` |
| Min | PHP 7.4, WP 6.2. No PHP 8-only syntax (no `match`, union types, constructor promotion, `readonly`, nullsafe `?->`). |

Coding style: WordPress Coding Standards, tabs, `$wpdb->prepare()` for every query with input,
`sanitize_*`/`esc_*` at the edges, `current_user_can( 'manage_options' )` on every admin route.
Small classes (target < 400 lines each). No SQL in REST controllers — controllers call services/repositories.

## 2. Layers

```
Http/            REST controllers (one per resource), thin: validate → call service → shape response
  RestController (abstract base: namespace, permission callback, pagination helpers, error helper)
  ContactsController, GroupsController (lists + tags), CampaignsController, TemplatesController,
  AutomationsController, AnalyticsController, SettingsController, SmtpController, AiController,
  LeadsController, IntegrationsController
Domain/          Repositories (SQL) + services (business rules)
  Schema             create/upgrade tables via dbDelta, version option wpbot_crm_db_version
  ContactRepository, GroupRepository, CampaignRepository, MessageRepository, EventRepository,
  TemplateRepository, AutomationRepository, RunRepository
  ContactService     create/update/merge/import/export, status changes, fires hooks
  AudienceResolver   turns {lists, tags, exclude_*} into contact ids (SQL, chunked)
  CampaignService    validate, schedule, enqueue recipients, duplicate, stats
  AutomationEngine   enroll on triggers, advance runs, execute steps
  Personalizer       merge tags ({first_name}, {unsubscribe_url}, …) per API-CONTRACT
  Limits             free vs pro limits (wraps qcwpbot_has_pro / free limits from §9)
Mail/
  Mailer             builds message, appends footer/compliance, rewrites links for tracking, sends via wp_mail
  SmtpConfigurator   hooks phpmailer_init when custom SMTP is enabled (Pro), encrypts password
  Tracking           signed URLs + front-end handler for open / click / unsubscribe / confirm
Jobs/
  Scheduler          registers the cron schedule + event; a lock so only one tick runs at a time
  Tick               one tick = activate due campaigns → send a batch → advance due automation runs → housekeeping
Integrations/
  ChatbotLeads       listens to qcwpbot_chatbot_lead_captured (fired by qcld-wpwbot-search.php)
  WordPressUsers     user_register → contact (if enabled in settings)
  WooCommerce        woocommerce_order_status_completed → contact + tag "customer" (if enabled)
  Shortcode          [wpbot_subscribe list="…" tag="…"] form + AJAX/REST submit
  FormPlugins        adapter used by the existing ThirdPartyController (see §8)
Support/
  Crypto             encrypt/decrypt secrets with openssl + wp_salt('auth'); graceful fallback
  Ai                 adapter over WPBot's existing Insights AI client (see §7)
  Csv                streaming CSV read/write helpers
```

## 3. Data model (fresh schema, no legacy migration)

All tables: `ENGINE=InnoDB`, `$wpdb->get_charset_collate()`, `created_at`/`updated_at` DATETIME in UTC (`current_time('mysql', true)`).

**contacts** — `id` BIGINT PK, `email` VARCHAR(190) UNIQUE, `first_name`, `last_name`, `phone`, `company`, `website` (VARCHAR), `status` VARCHAR(20) (`subscribed|unsubscribed|pending|bounced|complained`), `source` VARCHAR(50), `attributes` LONGTEXT JSON (custom fields + notes array), `token` CHAR(32) UNIQUE (random, used in public links), `confirmed_at`, `unsubscribed_at`, `last_activity_at`, timestamps. Index on `status`, `created_at`.

**groups** — one table for both lists and tags. `id`, `type` ENUM-like VARCHAR(10) (`list|tag`), `name`, `slug`, `description`, timestamps. UNIQUE(`type`,`slug`).

**group_members** — `group_id`, `contact_id`, `added_at`. PK(`group_id`,`contact_id`), index `contact_id`.

**campaigns** — `id`, `name`, `subject`, `preview_text`, `from_name`, `from_email`, `reply_to`, `content_html` LONGTEXT, `template_id` NULL, `audience` LONGTEXT JSON (`{"lists":[],"tags":[],"exclude_lists":[],"exclude_tags":[]}`), `settings` LONGTEXT JSON (A/B, tracking toggles, anything else the contract needs), `status` (`draft|scheduled|sending|paused|sent|failed`), `scheduled_at`, `started_at`, `finished_at`, counters `recipients`, `sent`, `failed` INT, timestamps.

**messages** — one row per email to one contact (campaign or automation). `id`, `campaign_id` NULL, `automation_id` NULL, `step_key` VARCHAR(40) NULL, `contact_id`, `status` (`queued|sent|failed|skipped`), `error` TEXT, `queued_at`, `sent_at`, `first_open_at`, `opens` INT, `first_click_at`, `clicks` INT. UNIQUE(`campaign_id`,`contact_id`) (NULLs allowed for automation), index (`status`,`queued_at`).

**events** — append-only activity: `id`, `contact_id`, `message_id` NULL, `type` (`open|click|unsubscribe|bounce|subscribe|confirm|note|import`…), `data` TEXT JSON (e.g. url), `created_at`. Index (`type`,`created_at`), (`contact_id`).
Link-level stats are computed from `events` where type=click grouped by `data->url` (store url also in a `url_hash` CHAR(40) column for grouping).

**templates** — `id`, `name`, `category`, `subject`, `content_html` LONGTEXT, `design` LONGTEXT JSON NULL, `is_builtin` TINYINT, timestamps. Built-in starter templates are **written fresh** for WPBot (3–6 simple responsive layouts) and seeded on install.

**automations** — `id`, `name`, `status` (`active|paused|draft`), `trigger_type`, `trigger_config` JSON, `steps` LONGTEXT JSON (ordered array; each step has a stable `key`), timestamps, counters `enrolled`, `completed`.

**automation_runs** — `id`, `automation_id`, `contact_id`, `step_index` INT, `status` (`waiting|done|exited|failed`), `next_run_at`, timestamps. UNIQUE(`automation_id`,`contact_id`), index (`status`,`next_run_at`).

Options: `wpbot_crm_settings` (array, defaults in `SettingsController`), `wpbot_crm_smtp` (array, password encrypted), `wpbot_crm_leads` (lead finder settings), `wpbot_crm_db_version`, `wpbot_crm_secret` (random 64 chars, generated on install, used for HMAC).

Uninstall: drop `wpbot_crm_*` tables and options only if the user enabled "delete data on uninstall" (hook into WPBot's existing `uninstall.php`).

## 4. REST API

Namespace `wpbot-crm/v1`. Resources are plural nouns; standard verbs:
`GET /x` (list, `page`/`per_page`/`search`, returns `{items, total, pages}` and sets `X-WP-Total`/`X-WP-TotalPages`),
`POST /x`, `GET /x/{id}`, `PUT /x/{id}`, `DELETE /x/{id}`, plus action sub-routes (`POST /campaigns/{id}/send`).
Errors: `WP_Error( code, message, ['status'=>…] )`; Pro gating uses code `wpbot_crm_pro_required` (status 402) and limit errors `wpbot_crm_limit_reached` (status 429).

Every endpoint the UI needs is listed in `API-CONTRACT.md`; map each contract endpoint to a route here in
`docs/crm-rewrite/ROUTE-MAP.md` (old UI path → new path). The UI will be updated to call the new paths through a single
`marketing-src/src/api.js` helper. Do **not** add endpoints the UI doesn't use.

## 5. Sending pipeline

1. `POST /campaigns/{id}/send` → `CampaignService::start()` validates (subject, content, audience non-empty, limits), sets `status=sending` (or `scheduled` if `scheduled_at` in future) and returns immediately.
2. `Tick` (cron every minute, plus an optional immediate run triggered by `POST /campaigns/{id}/process` for admin feedback):
   a. Scheduled campaigns whose `scheduled_at <= now` → `sending`.
   b. For each `sending` campaign without recipients yet: `AudienceResolver` inserts `messages` rows (`queued`) in chunks of 1,000 using `INSERT IGNORE … SELECT` for subscribed contacts only.
   c. Pull up to `batch_size` (setting, default 50) queued messages, oldest first; send each via `Mailer`; mark `sent|failed`; respect `emails_per_second` with `usleep`; stop at ~20 s wall time.
   d. When a campaign has no queued messages left → `status=sent`, `finished_at`.
   e. Advance `automation_runs` where `status=waiting AND next_run_at <= now` (up to 100 per tick).
   f. Daily housekeeping (once per day, tracked by option timestamp): purge `events` older than 365 days.
3. Lock: `add_option( 'wpbot_crm_tick_lock', time()+60, '', 'no' )` acts as a mutex; stale lock (>2 min) is taken over.
4. Free-plan limits come from `qcwpbot_free_limits()` equivalents (§9); enforce daily send cap by counting `messages.sent_at` today.

## 6. Tracking, unsubscribe, double opt-in

Front-end handler on `template_redirect` reading query var `wpbot_crm` (register via `query_vars` filter):
- Open: `?wpbot_crm=o&m={message_id}&s={sig}` → 1×1 transparent GIF, no-cache headers; increment `opens`, set `first_open_at`, log event.
- Click: `?wpbot_crm=c&m={message_id}&u={base64url(url)}&s={sig}` → validate sig, log, `wp_safe_redirect` is wrong for external links, so use `wp_redirect` **only** when the sig is valid (prevents open redirect); otherwise 400.
- Unsubscribe: `?wpbot_crm=u&t={contact_token}` → page (themed via `wp_die`-style minimal HTML or a template) with confirm button (POST, nonce-free but token-bound), then status `unsubscribed`. Also send `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers.
- Confirm (double opt-in): `?wpbot_crm=v&t={contact_token}` → `pending → subscribed`, fires `wpbot_crm/contact_confirmed`.
- `sig = substr( hash_hmac( 'sha256', "{type}|{message_id}|{payload}", wpbot_crm_secret ), 0, 20 )`, compared with `hash_equals`.
- Bot/prefetch protection: ignore opens within 2 s of `sent_at`; ignore HEAD requests.
- Respect settings `track_opens` / `track_clicks`.

## 7. AI

Do not add a new multi-provider HTTP client. Use WPBot's existing Insights client:
`QuantumCloud\Insights\AI\Provider_Client( $provider, $key, $model )` + `AI_Request( $messages, ['max_tokens'=>…, 'temperature'=>…, 'response_format'=>'json'|'text'] )` → `AI_Response` (`ok()`, `text`, `error_message`).
`Support/Ai` picks provider/key/model from WPBot's own AI settings (same option names `\WPBot_Insights_Adapter::get_ai_import()` in `includes/class-wpbot-insights-adapter.php` reads — call that method if available, else read the options directly), with an override in `wpbot_crm_settings['ai_provider'|'ai_model']`.
If the class isn't loaded, return `WP_Error('wpbot_crm_ai_unavailable', …, 503)`.
Prompts are written fresh, kept in one file `Support/Prompts.php`, and request JSON output where the UI expects structure; strip `<think>…</think>` blocks before parsing.

## 8. Integrations

- **Chatbot leads**: `add_action( 'qcwpbot_chatbot_lead_captured', … )` payload `{name,email,phone,url,user_agent}` → `ContactService::upsert()` with source `chatbot`, optional default list/tag from settings, double opt-in respected. **Do not** register `wp_ajax_qcld_wb_chatbot_email_subscription` (WPBot core owns it).
- **ThirdPartyController** (`includes/admin/marketing-backend/controllers/class-third-party-controller.php`) is QuantumCloud's own code and is kept. It will be moved to `includes/ai-marketing/Integrations/FormCapture.php` by the lead engineer (not by clean-room agents) and its direct SQL to old tables replaced with calls to `ContactService::upsert( array $data, string $source, array $group_ids )`. Implementers must expose that exact method signature.
- **Shortcode** `[wpbot_subscribe]` (attrs: `list`, `tag`, `button`, `placeholder`, `show_name`): renders a small form, posts to public REST route `POST /wpbot-crm/v1/subscribe` (nonce `wp_rest`, honeypot field, IP rate limit 10/10 min via transient).

## 9. Free vs Pro

Keep the global function names the UI-localization code relies on (`qcwpbot_has_pro()`, `qcwpbot_free_limits()`), but reimplement them in `includes/ai-marketing/functions.php`:
- `qcwpbot_has_pro()`: true if WPBot Pro is active (`class_exists( 'qcld_wb_Chatbot' )` or `defined( 'QC_PLUGIN_DIR' )`), filterable via `wpbot_crm/has_pro`.
- `qcwpbot_free_limits()`: `campaigns_per_month` 30, `campaign_daily_sends` 500, `email_scheduled_campaigns` 3, `email_smtp_connections` 2 — filterable via `wpbot_crm/free_limits`.
Pro-only features are those the UI marks as Pro in `API-CONTRACT.md`.

## 10. Events (public hooks for other WPBot code)

`wpbot_crm/contact_created( $contact )`, `wpbot_crm/contact_updated`, `wpbot_crm/contact_status_changed( $contact, $old, $new )`, `wpbot_crm/group_added( $contact_id, $group )`, `wpbot_crm/group_removed`, `wpbot_crm/message_sent( $message )`, `wpbot_crm/email_opened`, `wpbot_crm/link_clicked`. The `AutomationEngine` subscribes to these to enroll contacts.

## 11. Reconciliation with API-CONTRACT.md (these override earlier sections)

1. **Paths** follow `ROUTE-MAP.md` (namespace `wpbot-crm/v1`). The UI is updated by the lead engineer; **request/response field names and wrapper shapes stay exactly as in API-CONTRACT.md** (e.g. campaigns use `title`, `email_subject`, `email_pre_header`, `email_body`, `settings{send_to,lists,tags,…}`; list endpoints return `{items,total}` except SMTP which returns a bare array; automations list returns `{items,overview}`). Return documented aliases (`title`+`name`, `subject`+`email_subject`, `subject`+`description` on templates) so every screen renders.
2. **Merge tags** are double-brace: `{{first_name}}`, `{{last_name}}`, `{{email}}`, `{{unsubscribe_url}}`; also accept single-brace variants; unknown tags → empty string.
3. **Contact statuses**: store `subscribed|unsubscribed|pending|bounced|complained|customer`; normalise `unconfirmed` → `pending`. Sendable = `subscribed` and `customer`.
4. **Dates in responses**: ISO-8601 (`Y-m-d\TH:i:s`, site timezone) everywhere, except template `date`/`created_at` which is `Y-m-d H:i:s`.
5. **Campaign status** is owned by `send`; `PUT` never downgrades `sending|sent|scheduled`. Campaign `settings` JSON column holds `send_to, lists, tags, exclude_lists, exclude_tags, enable_ab, send_throttle (int, emails/minute), all`; `utm_status` is a column-free key inside `settings` too. Non-Pro: force `enable_ab=false, utm_status=false, send_throttle=0`. `utm_status=true` appends `utm_source=wpbot&utm_medium=email&utm_campaign={slug}` to tracked links.
6. **Automation statuses** `published|draft` (replaces active/paused). Triggers and step `action_name`s exactly as listed in the contract §3.7. `steps` JSON items: `{id:int, action_name, delay_value, delay_unit, settings}`; ids are assigned server-side (max+1) on full-replace PUT and preserved when sent. Execution: on enroll, schedule step 0 at now + its delay; after each step, schedule the next at now + next step's delay; `end` or last step → run `done` (+completed). `wait` is a no-op step (its delay does the waiting). Steps without settings (add_tag etc. with empty settings) are skipped silently. `webhook` POSTs contact JSON to `settings.url` via `wp_remote_post` (timeout 10) when set. Triggers `tag_added|tag_removed|list_added|list_removed` match any group unless `trigger_config` (not sent by UI today) narrows it. Each contact is enrolled at most once per automation.
7. **AI keys**: the CRM Settings screen has its own `ai_provider`, `ai_model` and `<provider>_api_key` fields (stored in `wpbot_crm_settings`, encrypted with `Support/Crypto`). `Support/Ai` uses those first; if the key for the chosen provider is empty, fall back to WPBot's own AI options (§7). `test-connection` uses the unsaved values in the body. Settings GET returns keys masked as `••••` + last 4 chars; a POSTed value equal to the mask keeps the stored key.
8. **Lead Finder** settings in option `wpbot_crm_leads` (merge on POST, stats read-only). Search pipeline: places provider (`serper` → `https://google.serper.dev/places`, `outscraper` → Outscraper Google Maps search API, `google` → Places API Text Search) for businesses matching industry/location/keyword → website domain → email discovery via Hunter `domain-search` (with role filter mapping) or, if no Hunter key, a Firecrawl scrape of the site's contact page with an email regex → MX check with `checkdnsrr`/`getmxrr` → drop emails already in contacts. Results returned, not saved. Option B autopilot prompts are turned into search params with the AI adapter (`openai_key` from lead settings, else the CRM AI settings). Autopilot runs daily via cron event `wpbot_crm/leads_autopilot` when `is_autopilot_enabled`. Missing key → `WP_Error('wpbot_crm_missing_key', 'Add a Serper, Outscraper or Google Places API key in Lead Finder → API Keys.', 400)`.
9. **CSV import** (UI has no mapping step): import immediately. Auto-detect delimiter (`,` `;` tab) and map headers case-insensitively (`email|e-mail|email address`, `first name|first_name|firstname`, `last name|…`, `phone|mobile`, `status`). Skip invalid/test emails. Respond with `{success, imported, updated, skipped, message}`. WP users / WooCommerce import as in the contract. `skip_disposable` uses a small built-in disposable-domain list (~100 domains, written fresh); `skip_role` skips `info|support|admin|sales|contact|noreply|no-reply|webmaster|postmaster|hello|office@`. Both ignored for non-Pro.
10. **Templates**: seed 6 original built-in templates (Welcome, Newsletter, Promotion, Product launch, Re-engagement, Event invite), 2 of them `is_pro`. Editing a built-in template saves a custom copy. Template create is Pro-only.
11. **Free limits** enforced server-side: max 30 campaigns created per calendar month, 3 simultaneously `scheduled`, 500 campaign emails per day (§5.4); errors use `wpbot_crm_limit_reached` with a clear sentence.
12. **Integrations routes** (`integrations/status|capture|sync`) are implemented by the lead engineer from the existing QuantumCloud ThirdPartyController — clean-room agents skip them but must provide `ContactService::upsert( array $data, string $source, array $group_ids = array() ): int|WP_Error` and `GroupRepository::ensure( string $type, string $name ): int`.

## 12. Testing / done criteria

- `php -l` clean on every file; no PHP 8-only syntax.
- Activate on the local site (`chatfree`), open WPBot → Marketing, and every tab loads without console/REST errors.
- Smoke script `docs/crm-rewrite/smoke.sh` (WP-CLI `wp eval`) creates a contact, list, tag, template, campaign, runs one tick, verifies a message is `sent` (use `pre_wp_mail` filter to capture instead of sending).
- Similarity check vs the original plugin < 15% per file (lead engineer runs it).
