# Email Marketing / CRM: REST API Contract (derived from the React admin UI)

Source of truth: `includes/admin/marketing-src/src/` (React app mounted into `#qcwpbot-app`) plus the DOM helper `js/marketing-admin.js`.
This document was produced clean-room from the frontend only. Line numbers refer to the `.jsx` files in `includes/admin/marketing-src/src/components/`.

---

## 0. Global conventions

### 0.1 Transport
- Every call goes through `@wordpress/api-fetch` with a `path` (never a `url`). There is **no** custom middleware in `index.js`: no `rootURL`, no nonce middleware is registered by the app. The backend must therefore enqueue the bundle with the `wp-api-fetch` script dependency (core sets up the root URL and `X-WP-Nonce` middleware automatically). All endpoints are standard WP REST routes authenticated by cookie + `wp_rest` nonce.
- Namespace: `qcwpbot/v1`. Almost every route sits under `/qcwpbot/v1/email/...`. **One exception:** `/qcwpbot/v1/leadfinder/settings` (no `email/` segment).
- Request bodies are sent with `data: {...}` (JSON, `Content-Type: application/json`), except the CSV upload, which sends `body: FormData` (multipart).
- `apiFetch` is used with its default `parse: true`, so **response headers (e.g. `X-WP-Total`, `X-WP-TotalPages`) are never read**. Pagination is not used anywhere in the UI.
- Methods used: `GET`, `POST`, `PUT`, `DELETE`. `PATCH` is never used.

### 0.2 Error shape
- On non-2xx, `apiFetch` rejects with the parsed JSON body. The UI only ever reads `err.message` (and sometimes `err.code` as a fallback), then shows `alert('<prefix>' + err.message)`.
- Canonical error: the standard `WP_Error` serialisation `{ "code": "qcwpbot_xxx", "message": "Human readable text", "data": { "status": 4xx } }`. **`message` must always be a user-presentable sentence**, because it is shown verbatim.
- Several components ignore errors except `console.error` (Lists, Tags, Campaign delete/duplicate, Dashboard). See each endpoint.

### 0.3 List response wrappers
The UI is inconsistent about list envelopes. Per endpoint:

| Endpoint | UI accepts | Canonical shape to return |
|---|---|---|
| `GET email/campaigns` | `res.items` or bare array | `{ "items": [...] }` |
| `GET email/templates` | `res.items` or bare array | `{ "items": [...] }` |
| `GET email/lists` | `res.items` or bare array | `{ "items": [...] }` |
| `GET email/tags` | `res.items` or bare array | `{ "items": [...] }` |
| `GET email/subscribers` | **only** `res.items` (bare array ignored) | `{ "items": [...] }` (required) |
| `GET email/automations` | **only** `res.items` (+ `res.overview`) | `{ "items": [...], "overview": {...} }` (required) |
| `GET email/smtp` | **only** a bare array (`res.length`, `res[0]`) | `[ {...} ]` (required, bare array) |
| `GET email/leads/search` | `res.items` or bare array | `{ "items": [...] }` |

Recommendation: return `{ "items": [...], "total": n }` everywhere **except** `GET email/smtp`, which must stay a bare array unless the UI changes.

### 0.4 IDs and types
- The UI compares IDs with `Array.prototype.includes` / `===` (e.g. `settings.lists.includes(list.id)`). IDs must have **the same JS type everywhere**. Canonical: **integers** for all `id`, `list_ids`, `tag_ids`, `settings.lists`, `settings.tags`.
- Dates: the UI parses them with `new Date(...)`. Subscribers tolerate unix seconds, unix ms, `YYYY-MM-DD HH:MM:SS` or ISO. Campaigns/Lists/Tags call `new Date(created_at)` directly (MySQL `YYYY-MM-DD HH:MM:SS` gives Invalid Date in Safari). Templates do `date.split(' ')[0]` (must be a **string**; a number would crash the render). Canonical: **string `YYYY-MM-DD HH:MM:SS` (site timezone)** for templates, and ISO-8601 or the same MySQL string elsewhere. A safe single choice for every endpoint: `"2026-10-26 14:03:00"` (works for Templates and Subscribers; works in Chromium for the rest). See Bugs section.
- Booleans: send real JSON booleans in responses (`true`/`false`). Some requests send `1/0` (Settings), others booleans; the backend must accept both.

---

## 1. `window.qcwpbotData` (localized by PHP, read by the React app)

| Field | Type | Used in | Purpose / behaviour |
|---|---|---|---|
| `hasPro` | bool | Campaigns.jsx:182, LeadFinder.jsx:6, Templates.jsx:6, Automations.jsx:6, Smtp.jsx:6 | Gating of Pro UI. `Campaigns`/`LeadFinder` use `?? false`; others use `Boolean()`. |
| `proUrl` | string | Campaigns, LeadFinder, Templates, Automations, Smtp, AiTools (`ProFooter`) | Upgrade link. Default fallback `https://www.wpbot.pro/pricing/`. |
| `siteName` | string | Settings.jsx:70 | Default for `from_name`, `company_name`, and the default footer `<p>&copy; {YEAR} {siteName}. All rights reserved.</p>`. |
| `adminEmail` | string | Settings.jsx:71 | Default for `from_email` and `reply_to`. |

Not used anywhere in the source: `freeLimits` (the campaign list hard-codes 30 campaigns / 3 scheduled locally and never renders them), any nonce, any REST root.

### 1.1 `window.qcwpbotMarketingUI` (DOM helper `js/marketing-admin.js`)
The helper makes **no network calls** (no `fetch`, `$.ajax`, `apiFetch`, `admin-ajax`). It only decorates the DOM and opens a SweetAlert2 upgrade dialog. PHP must localize:

| Field | Type | Purpose |
|---|---|---|
| `hasPro` | bool | If true, upgrade-dialog interception is disabled. |
| `proUrl` | string | Opened by the dialog's confirm button. |
| `proFeatures` | string[] | Bullet list in the upgrade dialog. |
| `checkIcon` | string (URL) | Icon for each bullet. |
| `i18n` | object | Keys: `upgradeTitle`, `upgradeFeature` (contains `%s`), `upgradeGeneric`, `upgradeText`, `upgradeButton`, `maybeLater`, `ok`. |
| `cards` | object | Map of lower-cased settings sub-tab label -> `{icon, desc}`. |
| `collapsedSections` | string[] | Accordion keys collapsed by default. |
| `tools` | object | Map of lower-cased AI tool title -> `{icon, desc}`. |
| `navLabels` | object | Map original sidebar label -> translated label. |

Also uses `localStorage` key `qcwpbot_mk_notice_closed`. The React app uses `localStorage` keys `qcwpbot_active_tab` and `qcwpbot_settings_tab` (client only).

---

## 2. Navigation (App.jsx, Sidebar.jsx)
Tabs (id -> component): `analytics`->Dashboard, `contacts`->Subscribers, `lists`, `tags`, `lead-finder` (PRO badge), `campaigns`, `templates`, `automations`, `import-export`, `ai-tools`, `smtp` (PRO badge), `settings`. No API calls.

---

## 3. Endpoints by component

### 3.1 Dashboard (Dashboard.jsx)

#### `GET /qcwpbot/v1/email/analytics/overview?days={7|30|90}`
- Called: Dashboard.jsx:19, on mount and whenever the 7d/30d/90d filter changes (default `days=30`).
- Request: query `days` integer, one of `7`, `30`, `90`.
- Response (bare object, no wrapper):
  ```json
  { "totals": { "subscribers": 120, "sent": 3400, "opened": 1200, "clicks": 300, "unsubscribes": 4 } }
  ```
  - Read: `totals.subscribers`, `totals.sent`, `totals.opened`. `clicks` and `unsubscribes` appear only in the error fallback object and are never rendered, but should be returned for completeness.
  - `totals.subscribers > 0` decides between the stats grid and the empty state ("Nobody on the list yet." with buttons that switch to the Contacts tab).
  - Semantics: `subscribers` = total contacts (status subscribed recommended) irrespective of `days`; `sent`/`opened`/`clicks`/`unsubscribes` = events within the last `days` days.
- Errors: any rejection is swallowed and replaced by all-zero totals (empty state shown). The page also CSS-hides `.qcwpbot-header .notice.notice-error`.

---

### 3.2 Campaigns (Campaigns.jsx)

#### `GET /qcwpbot/v1/email/campaigns`
- Called: Campaigns.jsx:13 (list mount and after delete/duplicate).
- No query params (no pagination, search or status filter are sent; the search box and status select at lines 66-73 are not wired).
- Response: `res.items || res` (canonical `{ "items": [Campaign] }`).
- Fields read per row:
  | Field | Usage |
  |---|---|
  | `id` | ID column, edit/duplicate/delete target |
  | `title` (fallback `name`) | Name column; fallback `(No Name)` |
  | `status` | Upper-cased; missing -> `DRAFT`. Values seen: `draft`, `sent`, `scheduled` (counted for a non-rendered limit). Recommended full enum: `draft`, `scheduled`, `queued`, `sending`, `sent`, `paused`, `failed`. |
  | `subject` | Subject column (note: the editor saves it as `email_subject`, see Bugs) |
  | `sent_count` | integer |
  | `open_rate` | **pre-formatted string**, e.g. `"12.5%"`; fallback `"0.0%"` |
  | `click_rate` | pre-formatted string, e.g. `"3.1%"` |
  | `created_at` | `new Date(created_at).toLocaleDateString()` |
- Errors: logged; empty list shown.
- Header shows `Campaigns ({count})`.

#### `DELETE /qcwpbot/v1/email/campaigns/{id}`
- Called: Campaigns.jsx:31 after `confirm()`. Response ignored; list re-fetched. No error handling.

#### `POST /qcwpbot/v1/email/campaigns/{id}/duplicate`
- Called: Campaigns.jsx:38. No body. Response ignored; list re-fetched. Backend should create a copy with status `draft` (suggest title suffix " (Copy)"), new `created_at`, zero stats.

#### `GET /qcwpbot/v1/email/campaigns/{id}`
- Called: Campaigns.jsx:239 when the wizard opens for edit.
- Response: bare object (not wrapped). Fields read (with tolerated aliases):
  | Canonical | Aliases tolerated | Type |
  |---|---|---|
  | `title` | `name` | string |
  | `email_subject` | `subject` | string |
  | `email_pre_header` | `preview_text` | string |
  | `email_body` | – | HTML string |
  | `utm_status` | – | bool (coerced with `!!`) |
  | `settings` | – | object **or** JSON string (UI `JSON.parse`s strings) |
  | `settings.lists` | `settings.list_ids` | int[] |
  | `settings.tags` | `settings.tag_ids` | int[] |
  | `settings.*` | – | every other key is spread back into state: `send_to`, `exclude_lists`, `exclude_tags`, `enable_ab`, `send_throttle`, `all` |
- Canonical: return `settings` as a JSON object with keys `lists`/`tags`.
- Errors: not handled.

#### `POST /qcwpbot/v1/email/campaigns` (create) and `PUT /qcwpbot/v1/email/campaigns/{id}` (update)
- Called: Campaigns.jsx:444 (create) / :429 (update) from "Save Draft" (header and footer) and from "Send Now" (step 4).
- Body (identical for both):
  ```json
  {
    "title": "Spring Sale Newsletter",
    "email_subject": "30% off everything",
    "email_pre_header": "Text shown in inbox preview",
    "email_body": "<p>Hi {{first_name}} ...</p>",
    "utm_status": false,
    "status": "draft",
    "settings": {
      "send_to": "all",            // "all" | "lists" | "tags"   ("segments" is a disabled PRO button, never sent)
      "lists": [3, 5],             // list IDs (sent even when send_to != "lists")
      "tags": [7],                 // tag IDs  (sent even when send_to != "tags")
      "exclude_lists": [],         // always [] - no UI to edit
      "exclude_tags": [],          // always [] - no UI to edit
      "enable_ab": false,          // PRO; checkbox disabled for free
      "send_throttle": 0,          // PRO; emails per minute, 0 = no limit. Number initially, STRING once edited ("120")
      "all": true                  // derived: send_to === "all"
    }
  }
  ```
  - `status` is **always `"draft"`**, even for Send Now; sending is triggered by the separate endpoints below.
  - No client validation of title/subject (labels show `*` only). Step 3 -> 4 blocks if `send_to` is `lists`/`tags` with nothing selected.
- Response:
  - Create: must return an object containing **`id`** (`res.id`). If `id` is missing the send chain is silently skipped but the UI still says "Campaign is queued" and returns to the list. Canonical: return the full Campaign object (`{ "id": 12, ... }`).
  - Update: response ignored.
- Errors: `alert('Error: ' + err.message)`.
- Pro gating (UI only; backend should enforce): `enable_ab`, `utm_status`, `send_throttle` are Pro. For free users the UI never toggles them, but `send_throttle` may still arrive from previously saved settings; backend should force `enable_ab=false`, `utm_status=false`, `send_throttle=0` when not Pro.

#### `POST /qcwpbot/v1/email/campaigns/{id}/send` then `POST /qcwpbot/v1/email/campaigns/{id}/process`
- Called: Campaigns.jsx:422-423, chained after a successful create/update when the user clicks **Send Now** on step 4. No bodies.
- UX: `send` -> then `process` -> `alert('Campaign is queued and will start sending shortly!')` -> back to list.
- Expected semantics:
  - `send`: validate (subject, body, recipients non-empty; SMTP/mailer available), resolve recipients from `settings` (`all` = all contacts with status `subscribed`; `lists`/`tags` = subscribed contacts in any of the selected lists/tags, de-duplicated, minus `exclude_*`), enqueue one queue row per recipient, set campaign `status` to `queued`/`sending`. Return `{ "success": true, "queued": 123, "message": "..." }`.
  - `process`: run one batch of the queue immediately (respecting Settings `batch_size`, `emails_per_second`, campaign `send_throttle`), the rest continues via WP-Cron every `batch_interval` seconds. Return `{ "success": true, "processed": 50, "remaining": 73 }`. Should be idempotent/safe if called twice.
  - Errors from either step are shown via `alert('Error: ' + err.message)` (e.g. "No recipients match this campaign.").
- Responses of both are ignored on success.

#### Shared data loaded by the wizard (Campaigns.jsx:218-236)
- `GET /qcwpbot/v1/email/templates` – used for the "Import Template" picker (fields: `id`, `title`/`name`, `content`, `isPro`/`is_pro`). Clicking a template copies `content` into `email_body`; Pro templates for free users show a confirm -> opens `proUrl`.
- `GET /qcwpbot/v1/email/lists` – fields `id`, `title`/`name`, `subscribers_count` (badge shown only if defined).
- `GET /qcwpbot/v1/email/tags` – fields `id`, `title`/`name`.

#### `POST /qcwpbot/v1/email/ai/generate-template`
- Called: Campaigns.jsx:387 ("Generate Email" in the AI Email Writer panel, step 1).
- Body:
  ```json
  { "prompt": "A spring sale announcement with 30% off ...", "style": "professional", "layout_mode": "simple-html" }
  ```
  - `prompt`: non-empty trimmed string (UI alerts if empty).
  - `style`: `professional` | `friendly` (free) | `formal` | `humorous` | `minimalist` (PRO-only; disabled options for free users).
  - `layout_mode`: `simple-html` | `table-safe` (PRO, "Table-Safe (Responsive)"). UI forces `simple-html` when not Pro.
- Response: UI reads `res.html`, else `res.content`, else the response itself if it is a string. Canonical: `{ "html": "<table>...</table>", "subject": "optional suggested subject" }`.
  - Empty/missing content -> `alert('No content received from AI. Please check your API key in General Settings.')`.
  - Success replaces the whole `email_body`.
- Errors: `alert('AI Generation Error: ' + (err.message || err.code))`. Return meaningful messages such as `code: "qcwpbot_ai_no_key", message: "No API key configured for OpenAI. Add one in General Settings > AI Provider & API."`.
- Backend: uses the provider/model/key from Settings (`ai_provider`, `ai_model`, `<provider>_api_key`). Must enforce Pro for `style` in {formal, humorous, minimalist} and `layout_mode=table-safe` (UI does not force `style` for free users if a stale value exists). Output must be email-safe HTML (inline styles; tables for `table-safe`), should include merge tags such as `{{first_name}}` and `{{unsubscribe_url}}`.

#### Merge tags exposed by the campaign editor (Campaigns.jsx:711-714)
`{{first_name}}`, `{{last_name}}`, `{{email}}`, `{{unsubscribe_url}}` (double braces). Automation email placeholders also mention `{{first_name}}`, `{{email}}` "etc.". The sending engine must replace these (and should treat unknown tags as empty strings). `{{unsubscribe_url}}` must resolve to a per-recipient signed unsubscribe link.

Editor also inserts images from the WP media library as `<img src="..." alt="..." style="max-width: 100%; height: auto; margin: 10px 0;" />` and links via `<a href>` (link tracking should rewrite these when `track_clicks` is on).

---

### 3.3 Contacts / Subscribers (Subscribers.jsx)

#### `GET /qcwpbot/v1/email/subscribers?per_page=100`
- Called: Subscribers.jsx:16 on mount and after add/edit/WP-Woo import.
- Query: `per_page=100` only. (Search box and filter selects are not wired. Recommended extra params for the backend to support anyway: `page`, `search`, `status`, `list_id`, `tag_id`.)
- Response: **must be `{ "items": [...] }`**; anything without `items` leaves the table unchanged.
- Subscriber fields read:
  | Field | Type | Usage |
  |---|---|---|
  | `id` | int | key, edit target |
  | `first_name`, `last_name` | string | "Full Name" column; edit form |
  | `email` | string | |
  | `phone` | string | edit form only |
  | `status` | string | badge `status-{status}` |
  | `tags` | array of `{id, title}` (or `name`), or of bare ids | Column renders `t.title || t.name` joined; edit form maps objects -> `id` |
  | `lists` | same as `tags` | |
  | `created_at` | unix s / unix ms / `YYYY-MM-DD HH:MM:SS` / ISO | `formatDate` |
- Canonical: `tags`/`lists` as arrays of `{ "id": 3, "title": "VIP" }` (bare ids would render as `-` in the table).
- Errors: logged only.

#### `POST /qcwpbot/v1/email/subscribers` (create)
- Called: Subscribers.jsx:137 ("Add Contact" modal).
- Body:
  ```json
  { "email": "jane@example.com", "first_name": "Jane", "last_name": "Doe", "phone": "+1 555 0100",
    "status": "subscribed", "list_ids": [3], "tag_ids": [7] }
  ```
  - `email` required (button disabled when empty).
  - `status` options in Add modal: `subscribed`, `unsubscribed`, `pending`, `bounced`, `complained`, `customer`.
- Response ignored; success alert, modal closes, list refreshed.
- Errors: `alert('Failed to add contact: ' + err.message)`. Backend should validate email format and reject duplicates with a clear message (e.g. `code: "qcwpbot_subscriber_exists", message: "A contact with this email already exists.", status 409`).
- Backend should fire automation triggers `subscriber_created`, `list_added`, `tag_added`.

#### `PUT /qcwpbot/v1/email/subscribers/{id}` (update)
- Called: Subscribers.jsx:186 ("Edit Contact" modal).
- Body: same fields as create. `list_ids`/`tag_ids` are the **complete desired set** (replace semantics: add missing, remove absent).
  - Status options in Edit modal: `subscribed`, `unsubscribed`, `unconfirmed`, `bounced` (differs from Add modal, see Bugs).
- Response ignored. Errors: `alert('Error updating contact: ' + err.message)`.
- Backend should fire `subscriber_status_change`, `tag_added`/`tag_removed`, `list_added`/`list_removed` for the diffs.
- No delete-contact endpoint is used by the UI (no delete button; header checkbox has no bulk actions).

#### `POST /qcwpbot/v1/email/subscribers/import`
Two different request shapes on the same route (Subscribers.jsx:96 and :104):

**A. CSV (multipart)** – source "CSV File":
- Body: `FormData` with `file` (the `.csv` File) and `source=csv`.
- The selected delimiter (`comma` | `semicolon` | `tab`) is **not sent** (Bug).
- Response ignored; UI alerts `"CSV file uploaded! Map Columns step would go here."` and leaves the modal open. No column-mapping step exists in the UI yet.
- Recommended canonical response (to support a future mapping step): `{ "import_id": "abc123", "headers": ["Email","First Name",...], "sample": [[...],[...]], "total_rows": 250, "suggested_mapping": { "Email": "email", "First Name": "first_name" } }`. Target fields for mapping: `email` (required), `first_name`, `last_name`, `phone`, `status`. Header row required; file must have unique headers (UI hint text).

**B. WordPress users / WooCommerce customers (JSON)**:
- Body:
  ```json
  { "source": "wp_users",          // or "woocommerce"
    "status": "subscribed",        // subscribed|unsubscribed|pending|bounced|complained|customer
    "update_existing": false,      // radio Yes/No
    "skip_disposable": false,      // PRO badge but checkbox NOT disabled
    "skip_test": true,             // default true: skip test/fake/localhost/placeholder emails
    "skip_role": false,            // PRO badge but checkbox NOT disabled: skip info@, support@, admin@...
    "list_ids": [3], "tag_ids": [7] }
  ```
- Response ignored; success alert "WordPress Users successfully imported!" / "WooCommerce Customers successfully imported!", modal closes, list refreshed. Canonical response: `{ "success": true, "imported": 40, "updated": 3, "skipped": 7, "message": "40 contacts imported, 3 updated, 7 skipped." }`.
- Errors: generic alert `'Import failed. Check console for details.'` (message not shown).
- Backend: ignore `skip_disposable` / `skip_role` for non-Pro. Map WP users: `user_email`, `first_name`, `last_name`; Woo customers: billing email/first/last/phone. For `woocommerce` when WooCommerce is inactive, return an error.

Also in the Import modal: "B2B Lead Finder" option just navigates to the Lead Finder tab. "Find Leads" and "Export Contacts" header buttons just switch tabs.

---

### 3.4 Lists (Lists.jsx) and Tags (Tags.jsx)
The two screens are identical except for the resource name. `{res}` = `lists` or `tags`.

#### `GET /qcwpbot/v1/email/{res}`
- Called: Lists.jsx:19 / Tags.jsx:19 (also by Campaigns wizard and Subscribers).
- Response: `res.items || res` (canonical `{ "items": [...] }`).
- Item fields: `id` (int), `title` (string; Lists/Tags screens read **only** `title`; other screens accept `title || name`), `description` (string), `subscribers_count` (int), `created_at` (rendered as `new Date(created_at).toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'})`).
- Errors: logged; empty table.

#### `POST /qcwpbot/v1/email/{res}`
- Body: `{ "title": "Newsletter", "description": "Optional description" }` (title required; trimmed non-empty client-side).
- Response ignored (canonical: created object). Errors: console only.

#### `PUT /qcwpbot/v1/email/{res}/{id}`
- Body: `{ "title": "...", "description": "..." }`. Response ignored. Errors: console only.

#### `DELETE /qcwpbot/v1/email/{res}/{id}`
- After `confirm()`. Response ignored; list re-fetched. Errors: console only.
- Backend should delete the pivot rows (subscriber-list / subscriber-tag) but not the subscribers.

---

### 3.5 Lead Finder (LeadFinder.jsx)
Four tabs: `autopilot`, `instant`, `integrations` (all three PRO; when `!hasPro` they render a lock screen and **no** request is made from them), and `thirdparty` (available to free with partial gating). Default tab: `instant` if Pro, else `thirdparty`.

On mount (always, regardless of Pro) two GETs run:

#### `GET /qcwpbot/v1/leadfinder/settings`  (note: no `/email/`)
- Called: LeadFinder.jsx:65.
- Response (bare object); each field applied only if truthy (stats if `!== undefined`):
  | Field | Type | UI default | Notes |
  |---|---|---|---|
  | `autopilot_option` | `"option-a"` \| `"option-b"` | `option-b` | A = "Smart Form Filters", B = "AI Prompt Specification" |
  | `ai_prompt` | string | `''` | natural-language prospecting prompt |
  | `auto_volume` | `"25 Leads / Day"` \| `"50 Leads / Day"` \| `"100 Leads / Day"` \| `"Custom Number"` | `25 Leads / Day` | display strings used as values |
  | `custom_auto_volume` | string (numeric) | `"5"` | used when `auto_volume = "Custom Number"` |
  | `dest_list` | string (list **name**) | `"Customers"` | only option in UI is `Customers` |
  | `auto_tags` | string (comma-separated tag names) | `"AI-Autopilot"` | |
  | `hunter_key` | string | | Hunter.io API key |
  | `firecrawl_key` | string | | Firecrawl API key |
  | `places_api_option` | `"serper"` \| `"outscraper"` \| `"google"` | `serper` | |
  | `places_key` | string | | key for the chosen places provider |
  | `openai_key` | string | | OpenAI key for Option B prompt parsing |
  | `is_autopilot_enabled` | bool | false | |
  | `total_leads` | int | 0 | stat: TOTAL LEADS INGESTED |
  | `duplicates` | int | 0 | stat: DUPLICATES PREVENTED |
  | `bad_mx` | int | 0 | stat: BAD MX DOMAINS FILTERED |
  | `search_depth` | string | `"Page 1"` | stat: SEARCH DEPTH POINTER, e.g. `"Page 3"` |
  | `last_execution` | string | `"Never"` | human-readable datetime |
- Errors: logged.
- Note: keys are displayed in password inputs but are returned in plain text by this contract; backend may instead return a masked value and ignore unchanged masked values on save.

#### `POST /qcwpbot/v1/leadfinder/settings`
Called with three **partial** payloads; the backend must **merge** (only update keys present):
1. Save Autopilot (LeadFinder.jsx:162): `{ autopilot_option, ai_prompt, auto_volume, custom_auto_volume, dest_list, auto_tags }`.
2. Save API Keys (LeadFinder.jsx:185): `{ hunter_key, firecrawl_key, places_api_option, places_key, openai_key }`.
3. Toggle Autopilot (LeadFinder.jsx:208): `{ is_autopilot_enabled: true|false }` (optimistic UI; errors only logged).
- Response ignored. Errors (1, 2): `alert('Error saving ...: ' + err.message)`.
- Backend: PRO-only (return 403 with message for free). Enabling autopilot should schedule a daily WP-Cron job; disabling unschedules it. Stats fields are read-only (ignore if posted).

#### `GET /qcwpbot/v1/email/thirdparty/status`
- Called: LeadFinder.jsx:90.
- Response:
  ```json
  { "cf7": true, "wpforms": false, "gravity": false, "chatbot": true, "conversational": true,
    "capturing": { "cf7": false, "wpforms": false, "gravity": false, "chatbot": true, "conversational": false } }
  ```
  - Top-level booleans = "source plugin is installed/active" (`isActive`). If false the card shows "(Not Active)" and the Capture button is disabled and "Sync past" hidden.
  - `capturing.{source}` = live capture currently enabled.
  - Source keys: `cf7` (Contact Form 7), `wpforms` (WPForms), `gravity` (Gravity Forms), `chatbot` (Chatbot Sessions, i.e. this plugin's chat sessions), `conversational` (Conversational Forms built into this plugin).

#### `POST /qcwpbot/v1/email/thirdparty/capture`
- Called: LeadFinder.jsx:276 (Capture / Disable button).
- Body: `{ "source": "wpforms" }`. **It is a toggle**: the desired state is not sent; the backend flips the stored state.
- Response: `{ "success": true, "capturing": true, "message": "Auto lead capture enabled for WPForms." }`. UI requires `success` truthy; then sets `capturing[source] = !!res.capturing` and shows `message` (fallback text) for 5 seconds. If `success` is falsy nothing happens (no message shown) – return an HTTP error instead.
- Gating: free users may only toggle `wpforms`; others open `proUrl` in the UI. Backend must enforce (403 for non-Pro on other sources).
- Behaviour implied: when capturing, each new submission of that source is inserted as a subscriber into a list named **`"{Label} Leads"`** (e.g. "WPForms Leads", "Contact Form 7 Leads", "Gravity Forms Leads", "Chatbot Sessions Leads", "Conversational Forms Leads"); create the list if missing; de-duplicate by email; fire `form_submitted` automation trigger.

#### `POST /qcwpbot/v1/email/thirdparty/sync`
- Called: LeadFinder.jsx:255 ("Sync past" link; PRO only – free users are sent to `proUrl`).
- Body: `{ "source": "cf7" }`.
- Response: `{ "success": true, "message": "Imported 42 contacts from Contact Form 7.", "extracted": [ { "name": "Jane Doe", "email": "jane@example.com" } ] }`. UI alerts `message` (fallback "Sync successful") and lists `extracted[].name` / `extracted[].email` under "Recently Extracted Data". CF7 has no native storage; sync applies where submissions are stored (Flamingo/CFDB, WPForms entries, Gravity entries, plugin's own tables).
- Errors: `alert('Error syncing: ' + err.message)`.

#### `GET /qcwpbot/v1/email/leads/search?...`
- Called: LeadFinder.jsx:115 (Instant Prospect Search, PRO).
- Query string built with `URLSearchParams` (all keys always present, possibly empty):
  | Param | Value |
  |---|---|
  | `industry` | dropdown value **or**, if dropdown empty, the custom text. Dropdown values: `saas`, `ecommerce`, `healthcare`, `real_estate`, `finance`, `marketing` |
  | `role` | dropdown or custom text. Values: `founder`, `marketing_vp`, `sales_vp`, `hr_director`, `it_manager` |
  | `location` | dropdown or custom text (e.g. "Chicago, Illinois"). Values: `''` (worldwide), `us`, `uk`, `ca`, `au`, `eu` |
  | `company_size` | `''`, `1-10`, `11-50`, `51-200`, `201-500`, `500+` |
  | `keyword` | free text (singular `keyword`) |
  | `limit` | `25`, `50`, `100`; "Custom" sends `25` |
  Example: `/qcwpbot/v1/email/leads/search?industry=saas&role=founder&location=us&company_size=11-50&keyword=Shopify+Plus&limit=25`
- Response: `res.items` (array) or bare array. Canonical `{ "items": [Lead] }`.
  - Lead row fields shown: `first_name`, `last_name`, `company`, `title`, `email`, `location`. Return also any extra fields (e.g. `domain`, `website`, `phone`, `linkedin`, `mx_valid`) – the whole array is posted back to `leads/import`.
  - Empty `items` -> alert "Search finished but 0 leads were found."; no `items` and not array -> alert "Search completed but no items were found in the response."
- Errors: `alert('Error initiating search: ' + err.message)` (e.g. missing Serper/Hunter key).
- Implied pipeline: places/business search (Serper/Outscraper/Google Places) -> domain -> email discovery (Hunter, Firecrawl scrape) -> MX verification -> CRM de-duplication. Results are **not** saved until import.

#### `POST /qcwpbot/v1/email/leads/import`
- Called: LeadFinder.jsx:141 ("Save Leads to Contacts").
- Body: `{ "leads": [Lead, ...], "list_id": 0, "tag_names": ["Instant-Search"] }`. `list_id: 0` = no list. `tag_names` are names (create tags if missing).
- Response ignored (canonical `{ "success": true, "imported": 20, "duplicates": 5 }`); UI clears results.
- Errors: `alert('Error saving leads: ' + err.message)`.

#### `POST /qcwpbot/v1/email/leads/autopilot/run`
- Called: LeadFinder.jsx:221 ("Run Now (Test Run)"). No body. Response ignored; success alert. Stats are **not** re-fetched afterwards. Canonical response: updated stats `{ "total_leads", "duplicates", "bad_mx", "search_depth", "last_execution", "ingested_this_run" }`.
- Behaviour: one autopilot iteration using saved settings (Option A filters or Option B prompt parsed via OpenAI `openai_key`), daily volume from `auto_volume`/`custom_auto_volume`, push to `dest_list`, tag with `auto_tags`, advance `search_depth` page pointer, update counters and `last_execution`.

#### `POST /qcwpbot/v1/email/leads/autopilot/reset-pointer`
- Called: LeadFinder.jsx:235. Body `{ "search_depth": "Page 1" }`. Response ignored; UI sets "Page 1". Errors logged.

"Connect to Cold Outreach Funnel" button has no handler.

---

### 3.6 Templates (Templates.jsx)

#### `GET /qcwpbot/v1/email/templates`
- Called: Templates.jsx:32 (and Campaigns wizard).
- Response: `res.items || res` (canonical `{ "items": [Template] }`).
- Fields read:
  | Field | Alias | Usage |
  |---|---|---|
  | `id` | | |
  | `title` | `name` | card title / search (client-side, case-insensitive substring) |
  | `category` | | case-insensitive match to category chips; badge colour. Values: `General`, `Marketing`, `Transactional`, `Notification`, `Welcome`, `Newsletter`, `Promotional` |
  | `content` | | HTML; rendered as scaled preview via `dangerouslySetInnerHTML` |
  | `description` | `subject` | loaded into the "SUBJECT LINE" field of the editor |
  | `isPro` | `is_pro` | PRO badge + lock for free users |
  | `date` | `created_at` | **string**, UI shows `date.split(' ')[0]` (fallback "Just now") |
- Canonical: `{ "id": 1, "title": "Welcome Series #1", "name": "Welcome Series #1", "category": "Welcome", "subject": "Welcome aboard!", "description": "Welcome aboard!", "content": "<html>", "is_pro": false, "type": "system", "created_at": "2026-10-26 10:00:00" }` (send both `title` and `name`, both `subject` and `description` for safety).
- Backend should ship a seed set of system templates (some flagged `is_pro`).

#### `POST /qcwpbot/v1/email/templates` (create) / `PUT /qcwpbot/v1/email/templates/{id}` (update)
- Called: Templates.jsx:221 / :208.
- Body:
  ```json
  { "name": "Welcome Series #1", "category": "Marketing", "description": "Email subject line...", "content": "<p>HTML</p>", "type": "custom" }
  ```
  - `name` (required, trimmed) – note **`name`**, not `title`.
  - `category` – capitalised value from the list above (default `Marketing`).
  - `description` – actually holds the **subject line**.
  - `type` – always `"custom"`.
- Response ignored; modal closes; list refetched. Errors: `alert('Error creating/updating template: ' + err.message)`.
- Gating: create is PRO-only (UI blocks free). Edit allowed for free users on non-Pro templates; Pro templates cannot be edited by free users. Backend must enforce both. Editing a system template: recommended to save as an override/copy rather than mutating seed data.

#### `DELETE /qcwpbot/v1/email/templates/{id}`
- Called: Templates.jsx:243 after confirm. Free users are blocked client-side for Pro templates. Errors alerted with `err.message`.

The "AI Generate" button in the template modal is a no-op for Pro users and an upsell for free users (no endpoint).

---

### 3.7 Automations (Automations.jsx)

#### `GET /qcwpbot/v1/email/automations`
- Called: Automations.jsx:31 whenever the list view is shown.
- Response (**wrapper required**):
  ```json
  { "items": [ { "id": 4, "title": "Welcome flow", "status": "published", "trigger_name": "subscriber_created",
                 "enrolled_count": 120, "completed_count": 80 } ],
    "overview": { "total_automations": 3, "total_enrolled": 300, "total_active": 45, "total_completed": 200 } }
  ```
  - `status`: `published` | `draft` (filter select uses exactly these).
  - Search and status filtering are client-side.

#### `GET /qcwpbot/v1/email/automations/{id}`
- Called: Automations.jsx:59 (edit).
- Response (bare object): `{ id, title, trigger_name, status, sequences: [Sequence] }` where Sequence:
  | Field | Alias | Notes |
  |---|---|---|
  | `id` | | stored as `db_id` client-side, re-sent on save |
  | `action_name` | `type` | `add_to_list` -> UI `add_list`, `remove_from_list` -> `remove_list` |
  | `delay_value` | `delay` | integer |
  | `delay_unit` | | `minutes` \| `hours` \| `days` (lower-case; UI capitalises) |
  | `settings` | | object; `subject`/`email_subject`, `body`/`email_body` |
- Sequences must be returned in execution order.
- Errors: `alert('Error loading automation: ' + err.message)`.

#### `POST /qcwpbot/v1/email/automations` / `PUT /qcwpbot/v1/email/automations/{id}`
- Called: Automations.jsx:132 / :130 ("Save Automation"/"Update Automation"; title required client-side).
- Body: `{ "title": "Welcome flow", "trigger_name": "subscriber_created", "status": "draft", "type": "funnel" }`.
  - `trigger_name` (may be `""` if unset): `subscriber_created`, `subscriber_status_change`, `tag_added`, `tag_removed`, `list_added`, `list_removed`, `form_submitted`, `link_clicked`, `email_opened`, `woo_order_completed`, `user_registered`.
  - `status`: `draft` | `published`.
  - `type`: always `funnel`.
- Create response **must include `id`** (used for the next call). Update response ignored.

#### `PUT /qcwpbot/v1/email/automations/{id}/sequences`
- Called: Automations.jsx:163, always right after the create/update above.
- Body:
  ```json
  { "sequences": [
      { "id": 11, "action_name": "send_email", "type": "action", "delay_value": 0, "delay_unit": "minutes",
        "settings": { "subject": "Welcome {{first_name}}!", "email_subject": "Welcome {{first_name}}!",
                      "body": "Hi {{first_name}} ...", "email_body": "Hi {{first_name}} ..." } },
      { "action_name": "wait", "type": "action", "delay_value": 2, "delay_unit": "days", "settings": {} }
  ] }
  ```
  - `id` omitted for new steps (sent as `undefined` -> dropped by JSON).
  - `action_name` enum: `send_email`, `wait`, `add_tag`, `remove_tag`, `add_to_list`, `remove_from_list`, `update_contact`, `webhook`, `end`.
  - `type`: always `action`.
  - `delay_value`: int >= 0; `delay_unit`: `minutes`|`hours`|`days`. **Every** step type carries a delay (delay is applied before the step runs).
  - `settings`: for `send_email` the subject and body are duplicated under two keys each. Body is plain text/HTML from a textarea and supports `{{first_name}}`, `{{email}}` etc. Other step types currently get only whatever `settings` was loaded (the UI has no fields for tag/list/webhook/contact-field configuration).
- Semantics: **full replace** – array order = step order; existing rows whose `id` is absent must be deleted; rows with `id` updated; rows without `id` inserted. Response ignored; on success `alert('Automation saved successfully!')` and back to list.
- Errors (either call): `alert('Error saving automation: ' + err.message)`.

#### `DELETE /qcwpbot/v1/email/automations/{id}`
- Called: Automations.jsx:106 after confirm; then removes the row and re-fetches. Errors alerted.

Implied engine: when a `published` automation's trigger fires for a contact, enroll the contact (counts `enrolled_count`, `total_active`), execute steps in order honouring delays via WP-Cron, `end` finishes the funnel (counts `completed_count`, `total_completed`). "AI Suggest Steps" is an upsell only (no endpoint).

---

### 3.8 SMTP (Smtp.jsx) – PRO

#### `GET /qcwpbot/v1/email/smtp`
- Called: Smtp.jsx:24 only if `hasPro`.
- Response: **bare array** of connections; the UI uses `res[0]` when `res.length > 0`.
  ```json
  [ { "id": 1, "title": "My SMTP Connection", "provider": "smtp", "from_email": "info@site.com", "from_name": "Site",
      "smtp_host": "smtp.site.com", "smtp_port": "465", "smtp_encryption": "ssl", "smtp_auth": true, "smtp_user": "user@site.com" } ]
  ```
  - `smtp_auth`: anything other than strict `false` is treated as true – return a real boolean.
  - Do **not** return the password (UI never reads it; placeholder says "Leave blank to keep unchanged" when `id` is set).
- For non-Pro: return 403 or `[]`.

#### `POST /qcwpbot/v1/email/smtp`
- Called: Smtp.jsx:66.
- Body:
  ```json
  { "id": 1, "title": "My SMTP Connection", "provider": "smtp", "from_email": "...", "from_name": "...",
    "smtp_host": "smtp.site.com", "smtp_port": "465", "smtp_encryption": "ssl", "smtp_auth": true,
    "smtp_user": "...", "smtp_password": "secret" }
  ```
  - `id`: `''` for a new connection, otherwise the existing id (may be string or number).
  - `smtp_port`: string (default `"465"`).
  - `smtp_encryption`: `ssl` | `tls` | `none`.
  - `smtp_password`: present only when the user typed one; absent = keep stored password.
- Upsert: create when `id` empty, otherwise update. Because the UI does not read the response, a second save without reload re-sends `id: ''`; backend should treat SMTP as a **single active connection** (upsert the first/only row when `id` is empty) to avoid duplicates. Response canonical: the saved connection object (without password).
- Errors: `alert('Error saving SMTP settings: ' + err.message)`. Gating: Pro only (403 otherwise).
- No test-email/test-connection endpoint is called from this screen. Mailer must hook `phpmailer_init` for campaign/automation mail when a connection exists (encrypt password at rest).

---

### 3.9 General Settings (Settings.jsx)

#### `GET /qcwpbot/v1/email/settings`
- Called: Settings.jsx:125.
- Response: bare object. Fields and UI defaults (applied when the field is falsy/missing):
  | Field | Type | Default | Tab |
  |---|---|---|---|
  | `from_name` | string | `qcwpbotData.siteName` | General |
  | `from_email` | string | `qcwpbotData.adminEmail` | General |
  | `reply_to` | string | `from_email` or adminEmail | General |
  | `emails_per_second` | number/string | `10` | General |
  | `company_name` | string | siteName | General |
  | `company_address` | string | `''` | General |
  | `email_footer` | HTML string | `<p>&copy; {YEAR} {siteName}. All rights reserved.</p>` | General |
  | `unsubscribe_text` | string | `Unsubscribe` | General |
  | `double_opt_in` | bool/0/1 | false | Sending & Tracking |
  | `batch_size` | number/string | `50` | Sending & Tracking |
  | `batch_interval` | number/string (seconds) | `60` | Sending & Tracking |
  | `track_opens` | bool/0/1 | false | Sending & Tracking |
  | `track_clicks` | bool/0/1 | false | Sending & Tracking |
  | `gdpr_mode` | bool/0/1 | false | Sending & Tracking |
  | `ai_provider` | `openai`\|`gemini`\|`claude`\|`openrouter`\|`grok` | `openai` | AI |
  | `ai_model` | string | `gpt-4o-mini` | AI |
  | `openai_api_key`, `gemini_api_key`, `claude_api_key`, `openrouter_api_key`, `grok_api_key` | string | `''` | AI |
- Numeric fields are `.toString()`-ed, so return numbers or numeric strings.
- Backend should return its own defaults so the UI fallback rarely applies. API keys are displayed (password field with "Show Key"); consider masking.

#### `POST /qcwpbot/v1/email/settings`
- Called: Settings.jsx:198 from the Save button on any of the 3 tabs. **Always sends the full set** of fields above.
- Types sent: strings for all text/number fields (`emails_per_second`, `batch_size`, `batch_interval` are strings like `"50"`), `1`/`0` integers for `double_opt_in`, `track_opens`, `track_clicks`, `gdpr_mode`.
- Response ignored; `alert('Settings saved successfully!')`. Errors: `alert('Error saving settings: ' + err.message)`.
- Backend: sanitise emails, cast numerics (min 1), `wp_kses_post` the footer. Use these as defaults for all sends: From/Reply-To headers, footer appended to every email with company name/address and an unsubscribe link labelled `unsubscribe_text` (CAN-SPAM), open pixel when `track_opens`, link rewriting when `track_clicks`, confirmation email + `pending`/`unconfirmed` status when `double_opt_in`, GDPR mode (e.g. no IP/UA storage, consent requirements). Queue throttling: `batch_size` per cron run, every `batch_interval` seconds, max `emails_per_second`.

AI model presets offered (any custom model id string may also be saved):
- openai: `gpt-4o-mini` (default), `gpt-4o`, `gpt-3.5-turbo`
- gemini: `gemini-1.5-flash` (default), `gemini-1.5-pro`, `gemini-pro`
- claude: `claude-3-5-sonnet-20241022` (default), `claude-3-haiku-20240307`, `claude-3-opus-20240229`
- openrouter: `openai/gpt-4o-mini` (default), `anthropic/claude-3.5-sonnet`, `meta-llama/llama-3.1-70b-instruct`, `mistralai/mistral-7b-instruct`
- grok: `grok-beta` (default), `grok-2-latest`
Changing provider resets the model to that provider's first preset.

#### `POST /qcwpbot/v1/email/ai/test-connection`
- Called: Settings.jsx:227 ("Test Connection"; disabled when the active key is empty). Tests **unsaved** values.
- Body: `{ "provider": "openai", "api_key": "sk-...", "model": "gpt-4o-mini" }`.
- Response: `{ "success": true, "message": "Connected to OpenAI successfully.", "reply": "OK" }`.
  - `success` true -> green box: `message` + ` (AI responded: "<reply trimmed>")` if `reply` present. `reply` must be a string (UI calls `.trim()`).
  - `success` false -> red box with `message` (fallback "Connection test returned an unexpected response.").
- Errors (HTTP): red box with `err.message || err.code`. Either style is fine; prefer HTTP 200 + `success:false` + provider error message for auth failures so the user sees the provider's text.

---

### 3.10 Screens with no API calls
- **Import / Export (ImportExport.jsx)**: Source select (only `csv`), validation checkboxes (Skip invalid email format – default on; Skip test/fake/localhost/placeholder – default on; Skip disposable, Skip role-based, Skip suspicious spam-pattern, Check MX records – all PRO, disabled). "Upload CSV & Import" button has **no handler** and no file input. "Download CSV" (Export, PRO) is disabled. Implied future endpoints: `POST email/subscribers/import` (CSV, with `skip_invalid`, `skip_test`, `skip_disposable`, `skip_role`, `skip_spam`, `check_mx` flags) and `GET email/subscribers/export` (CSV download of all subscribers: email, first_name, last_name, phone, status, lists, tags, created_at; Pro-only).
- **AI Tools (AiTools.jsx)**: six cards, none wired:
  - Template Generator: TYPE `welcome` | `promotional` | `newsletter`, DESCRIPTION text, "Generate".
  - Subject Line Optimizer: SUBJECT text, "Optimize" (disabled).
  - Content Scorer (PRO): EMAIL CONTENT textarea, "Score" (disabled).
  - Spam Checker: SUBJECT + BODY, "Check".
  - Send Time Optimizer (PRO): INDUSTRY text, "Get Recommendation".
  - Segment Suggestions (PRO): GOAL text, "Suggest Segments" (disabled).
  These imply future endpoints (suggested names, not called today): `POST email/ai/generate-template` with `{type, description}` (could reuse the existing route), `POST email/ai/subject-optimize {subject}`, `POST email/ai/content-score {content}`, `POST email/ai/spam-check {subject, body}`, `POST email/ai/send-time {industry}`, `POST email/ai/segment-suggestions {goal}`.

---

## 4. Endpoint table (de-duplicated, as actually called)

| # | Method | Path | Purpose | Callers |
|---|---|---|---|---|
| 1 | GET | `/qcwpbot/v1/email/analytics/overview?days=` | Dashboard totals | Dashboard |
| 2 | GET | `/qcwpbot/v1/email/campaigns` | List campaigns | Campaigns |
| 3 | POST | `/qcwpbot/v1/email/campaigns` | Create campaign (returns `id`) | Campaigns |
| 4 | GET | `/qcwpbot/v1/email/campaigns/{id}` | Get campaign | Campaigns |
| 5 | PUT | `/qcwpbot/v1/email/campaigns/{id}` | Update campaign | Campaigns |
| 6 | DELETE | `/qcwpbot/v1/email/campaigns/{id}` | Delete campaign | Campaigns |
| 7 | POST | `/qcwpbot/v1/email/campaigns/{id}/duplicate` | Duplicate campaign | Campaigns |
| 8 | POST | `/qcwpbot/v1/email/campaigns/{id}/send` | Queue campaign for sending | Campaigns |
| 9 | POST | `/qcwpbot/v1/email/campaigns/{id}/process` | Process a send batch now | Campaigns |
| 10 | POST | `/qcwpbot/v1/email/ai/generate-template` | AI email HTML | Campaigns |
| 11 | POST | `/qcwpbot/v1/email/ai/test-connection` | Test AI provider key | Settings |
| 12 | GET | `/qcwpbot/v1/email/subscribers?per_page=100` | List contacts | Subscribers |
| 13 | POST | `/qcwpbot/v1/email/subscribers` | Create contact | Subscribers |
| 14 | PUT | `/qcwpbot/v1/email/subscribers/{id}` | Update contact (+ list/tag sync) | Subscribers |
| 15 | POST | `/qcwpbot/v1/email/subscribers/import` | Import: CSV multipart, or WP users / Woo JSON | Subscribers |
| 16 | GET | `/qcwpbot/v1/email/lists` | List lists | Lists, Campaigns, Subscribers |
| 17 | POST | `/qcwpbot/v1/email/lists` | Create list | Lists |
| 18 | PUT | `/qcwpbot/v1/email/lists/{id}` | Update list | Lists |
| 19 | DELETE | `/qcwpbot/v1/email/lists/{id}` | Delete list | Lists |
| 20 | GET | `/qcwpbot/v1/email/tags` | List tags | Tags, Campaigns, Subscribers |
| 21 | POST | `/qcwpbot/v1/email/tags` | Create tag | Tags |
| 22 | PUT | `/qcwpbot/v1/email/tags/{id}` | Update tag | Tags |
| 23 | DELETE | `/qcwpbot/v1/email/tags/{id}` | Delete tag | Tags |
| 24 | GET | `/qcwpbot/v1/email/templates` | List templates | Templates, Campaigns |
| 25 | POST | `/qcwpbot/v1/email/templates` | Create template (Pro) | Templates |
| 26 | PUT | `/qcwpbot/v1/email/templates/{id}` | Update template | Templates |
| 27 | DELETE | `/qcwpbot/v1/email/templates/{id}` | Delete template | Templates |
| 28 | GET | `/qcwpbot/v1/email/automations` | List automations + overview | Automations |
| 29 | POST | `/qcwpbot/v1/email/automations` | Create automation (returns `id`) | Automations |
| 30 | GET | `/qcwpbot/v1/email/automations/{id}` | Get automation + sequences | Automations |
| 31 | PUT | `/qcwpbot/v1/email/automations/{id}` | Update automation | Automations |
| 32 | DELETE | `/qcwpbot/v1/email/automations/{id}` | Delete automation | Automations |
| 33 | PUT | `/qcwpbot/v1/email/automations/{id}/sequences` | Replace automation steps | Automations |
| 34 | GET | `/qcwpbot/v1/email/smtp` | Get SMTP connections (bare array) | Smtp |
| 35 | POST | `/qcwpbot/v1/email/smtp` | Upsert SMTP connection | Smtp |
| 36 | GET | `/qcwpbot/v1/email/settings` | Get general settings | Settings |
| 37 | POST | `/qcwpbot/v1/email/settings` | Save general settings | Settings |
| 38 | GET | `/qcwpbot/v1/leadfinder/settings` | Lead finder config + stats | LeadFinder |
| 39 | POST | `/qcwpbot/v1/leadfinder/settings` | Merge-save lead finder config | LeadFinder |
| 40 | GET | `/qcwpbot/v1/email/leads/search?industry=&role=&location=&company_size=&keyword=&limit=` | Instant prospect search | LeadFinder |
| 41 | POST | `/qcwpbot/v1/email/leads/import` | Save leads as contacts | LeadFinder |
| 42 | POST | `/qcwpbot/v1/email/leads/autopilot/run` | Run autopilot once | LeadFinder |
| 43 | POST | `/qcwpbot/v1/email/leads/autopilot/reset-pointer` | Reset search pointer | LeadFinder |
| 44 | GET | `/qcwpbot/v1/email/thirdparty/status` | Third-party plugin + capture status | LeadFinder |
| 45 | POST | `/qcwpbot/v1/email/thirdparty/capture` | Toggle live capture | LeadFinder |
| 46 | POST | `/qcwpbot/v1/email/thirdparty/sync` | Import historical submissions (Pro) | LeadFinder |

Total: **46 method+path combinations** across 30 distinct route patterns. Permission callback for all: `current_user_can('manage_options')` (admin screen). Pro-only server checks: 25, 34-35, 38-43, 46, 45 (non-`wpforms` sources), plus field-level checks on 3/5 (`enable_ab`, `utm_status`, `send_throttle`), 10 (`style`, `layout_mode`), 15 (`skip_disposable`, `skip_role`), 26/27 (Pro templates).

---

## 5. Inconsistencies and bugs in the UI's API usage

1. **Campaign subject naming**: list reads `camp.subject`; editor reads `email_subject || subject` and saves `email_subject`. Backend list items must include `subject` (alias of `email_subject`) or the Subject column shows `---`. Same for `title`/`name`, `email_pre_header`/`preview_text`.
2. **Campaign `settings.lists`/`tags` vs `list_ids`/`tag_ids`**: UI reads either, writes `lists`/`tags`. Subscriber endpoints use `list_ids`/`tag_ids`. Canonical storage: `lists`/`tags` inside campaign settings.
3. **Campaign `settings` may be a JSON string**: UI `JSON.parse`s strings; return an object.
4. **Campaign status always `draft` on save**: even for Send Now. The `send` endpoint must own status transitions. Saving an already-sent campaign via PUT would reset it to `draft` – backend should refuse to downgrade `sent`/`sending` campaigns (or ignore `status` in PUT).
5. **Send chain silently skipped** if create response lacks `id`, yet the UI still claims "queued".
6. **`handleSave` mutates state** (`payload.settings.all = ...` writes into the React state object because the spread is shallow). Harmless but `all` ends up persisted and spread back on load.
7. **`send_throttle` type drift**: number `0` initially, string after editing.
8. **Campaign list search/status filter and the 30/3 free limits are computed but never applied/rendered**; no server params sent. `freeLimits` from `qcwpbotData` is not used at all – if limits are required they must be enforced server-side (e.g. 403 with message on create when over limit).
9. **Template naming**: create/update sends `name` and stores the subject in `description`; reading uses `title || name` and `description || subject`. Backend should store `title` from `name` and `subject` from `description`, and return both aliases.
10. **Template `date.split(' ')`** crashes if `date`/`created_at` is not a string.
11. **Subscriber status enums differ** between Add (`subscribed, unsubscribed, pending, bounced, complained, customer`) and Edit (`subscribed, unsubscribed, unconfirmed, bounced`). `pending` vs `unconfirmed` mean the same thing. Backend should accept the union and normalise `unconfirmed` -> `pending` (or vice versa). Editing a `pending`/`complained`/`customer` contact shows a mismatched select.
12. **`GET email/subscribers` requires `items`** while the sibling list endpoints accept bare arrays; **`GET email/smtp` requires a bare array** while everything else prefers `{items}`.
13. **CSV import** does not send the selected delimiter, does not send `list_ids`/`tag_ids`/status/skip flags, ignores the response and has no mapping step ("Map Columns step would go here"). The Import/Export page's "Upload CSV & Import" button has no handler.
14. **Import skip flags marked PRO are not disabled** in the Subscribers import modal (`skip_disposable`, `skip_role` can be sent by free users).
15. **Import error message ignored** (`'Import failed. Check console for details.'`).
16. **Lead Finder settings path lacks `/email/`** (`/qcwpbot/v1/leadfinder/settings`) unlike every other route.
17. **Lead Finder settings POST is partial** in three different shapes – must merge, not replace.
18. **Two OpenAI keys**: `leadfinder/settings.openai_key` and `email/settings.openai_api_key` are separate fields for the same provider.
19. **Lead search precedence**: dropdown value wins over the custom text (`industry || customIndustry`), so the custom text is ignored whenever a dropdown option is chosen. "Custom" volume sends `limit=25` (no custom number input). Param is `keyword` (singular) while the UI label is "Keywords".
20. **Autopilot `dest_list` is a list name string** (`"Customers"`), not an ID; `auto_tags` is a comma-separated string, while `leads/import` uses `list_id` (int, 0) and `tag_names` (array). Mixed name/ID conventions.
21. **Autopilot stats not refreshed** after "Run Now"; toggling autopilot is optimistic with errors swallowed.
22. **Third-party capture is a blind toggle** (no target state sent); a double click or a stale UI can invert the intended state. `success:false` responses produce no feedback. The status response object is stored wholesale, so `capturing` also lands in `integrationStatus` (harmless).
23. **SMTP**: after the first save the new `id` is not stored client-side, so a second save sends `id: ''` again; `smtp_auth` treats any non-`false` value (e.g. `0`, `"0"`) as true.
24. **Automation `send_email` settings duplicated** under `subject`+`email_subject` and `body`+`email_body`. Steps other than `send_email`/`wait`/`end` have no configuration UI (no tag/list/webhook URL/contact field pickers), so `add_tag`, `remove_tag`, `add_to_list`, `remove_from_list`, `update_contact`, `webhook` are saved with empty `settings` and cannot do anything meaningful yet. `wait` is redundant with per-step delays.
25. **Automation step type aliasing**: UI uses `add_list`/`remove_list` internally, API uses `add_to_list`/`remove_from_list`; GET accepts `action_name || type`, but POST always sends `type: "action"` – so `type` must not be used as the action discriminator when reading back (return `action_name`).
26. **Automation save is two non-atomic requests** (automation, then sequences). If the second fails after a create, the UI keeps `automationId` so a retry updates rather than duplicates – fine – but the backend should tolerate an automation with no sequences.
27. **Dashboard**: `clicks`/`unsubscribes` are never shown; failures silently show the empty state.
28. **No error handling** on campaign delete/duplicate (unhandled promise rejection) and Lists/Tags CRUD (console only) – backend error messages are invisible there.
29. **Lists/Tags screens read only `title`** while every other screen accepts `title || name`; always return `title`.
30. **Date parsing**: `new Date('YYYY-MM-DD HH:MM:SS')` is Invalid Date in Safari for Campaigns/Lists/Tags; Subscribers normalise the space to `T`. Prefer ISO-8601 (`2026-10-26T14:03:00`) for campaigns/lists/tags/subscribers, but templates need a space-separated string for `split(' ')[0]` to look nice (ISO still renders, just shows the full timestamp). Simplest compatible choice: ISO-8601 everywhere except template `date`, which should be `YYYY-MM-DD HH:MM:SS`.
31. **Merge tag syntax**: double braces `{{first_name}}` in the campaign editor and automation placeholders. The task brief mentions `{first_name}`; the UI never uses single braces. The backend may additionally support single-brace variants for robustness.
32. **AI style Pro gating** is only on the option list; a free user's previously selected Pro style is not reset before sending (layout mode is). Enforce server-side.
33. **Templates "AI Generate" and Automations "AI Suggest Steps"** do nothing for Pro users (no endpoint wired). All AI Tools cards are unwired.
