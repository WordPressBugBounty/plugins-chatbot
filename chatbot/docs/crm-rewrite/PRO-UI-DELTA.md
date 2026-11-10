# WPBot Pro AI Marketing UI: REST delta vs. API-CONTRACT.md

Source of truth: the Pro React app at `botmaster/includes/admin/marketing-src/src/` (clean-room, frontend only).
Baseline: `chatbot/docs/crm-rewrite/API-CONTRACT.md` (Free UI, "the contract", rows `#1–#46` of its §4) and `ROUTE-MAP.md`.
Line numbers refer to `marketing-src/src/components/<File>.jsx` unless stated otherwise.

Transport is the same as in the contract (§0.1): `@wordpress/api-fetch` with a hard-coded `path` under `/qcwpbot/v1/...`, default `parse: true` (headers never read), JSON bodies via `data`, multipart via `body: FormData`. Error handling: the UI reads `err.message` (sometimes `err.code`). The AI Tools screen also reads `err.error`, `err.data.error` and `err.data.message`. See §1.0.

**Summary of counts**

| Category | Count |
|---|---|
| New endpoints (method+path not in the contract) | **9** |
| Contract endpoints with a changed request/response usage | **9** (#10, #15, #16, #25, #38, #39, #40, #41, #42) |
| Contract endpoints used identically | **37** |
| Contract endpoints not called by the Pro UI | 0 (all 46 are still called) |

---

## 1. New endpoints (not in API-CONTRACT.md)

### 1.0 Shared AI error handling (AiTools.jsx:5-14, 412-447)
All six AI Tools cards use `parseApiError(err)`:
```
detail = err.error || err.data?.error || err.data?.message || err.message || ''
msg    = err.message || ''
shown  = (detail && msg && detail !== msg) ? `${msg} — ${detail}` : (detail || msg || 'AI request failed. Please verify your AI provider settings.')
```
- The text appears in an inline red banner under the card (with a ✕ to dismiss), not in an `alert`.
- If the text contains `restriction` (case-insensitive; this also matches "ip address restriction"), the UI adds a "How to fix" box about API-key IP restrictions.
- Recommended error shape: `WP_Error` → `{ "code": "qcwpbot_ai_xxx", "message": "Human sentence", "data": { "status": 4xx|5xx, "error": "<raw provider error text>" } }`. `data.error` is optional. When it is present and differs from `message`, the banner shows `message — data.error`.
- Every AI call uses the provider, model and key from General Settings (`ai_provider`, `ai_model`, `<provider>_api_key`, see contract §3.9). The Settings screen describes that engine as powering "the AI Email Writer, Subject Line Optimizer, Template Generator, and Marketing AI tools".
- The client defines **no** loading timeout. Handle provider timeouts server-side and return a readable message.
- Validation pattern: each card checks the success response for a "shape marker" (listed per endpoint). If the marker is missing, the card shows `Unexpected response from AI.` Always return the documented top-level keys; never return a bare string.

---

### 1.1 `POST /qcwpbot/v1/email/ai/optimize-subject`
- **Caller:** AiTools.jsx:161 (`handleOptimizeSubject`, :152-183), card "Subject Line Optimizer". Button disabled when the subject is empty (:714).
- **Body:**
  ```json
  { "subject": "Check out our latest products", "audience": "general" }
  ```
  | Field | Type | Notes |
  |---|---|---|
  | `subject` | string, required, trimmed, non-empty | The client blocks empty values with "Please enter a subject line to optimize." |
  | `audience` | enum string | `general` (default) \| `customers` \| `inactive` \| `leads` \| `b2b`. Labels: General Subscribers, Existing Customers, Inactive / Churned Users, New Leads / Prospects, B2B Decision Makers (:690-694) |
- **Response** (bare object, no wrapper):
  ```json
  { "score": 62, "analysis": "Generic wording, no benefit or urgency...",
    "suggestions": ["Your spring picks are here (30% off)", "..."], "best_pick": 0 }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `suggestions` | **string[]** | Shape marker (truthy). Each string is rendered as one row with a Copy button (copies the string). Must be plain strings, not objects. |
  | `analysis` | string | Shape marker (truthy). Paragraph of text. |
  | `score` | number 0-100 | Optional. Rendered as `{score} / 100` only if `!== undefined`. Badge colour: ≥75 green, ≥50 yellow, else red (:735-748). |
  | `best_pick` | int (index into `suggestions`) | Optional. Default `0`. That row gets a "TOP PICK" badge (:762). |
  | `raw` | string | Fallback only. If neither `suggestions` nor `analysis` is present but `raw` is, the UI shows `raw` as the analysis with no suggestions (:172-173). |
- Success when `res.suggestions || res.analysis` is truthy. An empty array `[]` counts as truthy, so it still passes.
- Errors: §1.0 banner.
- Recommend 5 suggestions, each ≤ 60 characters, written for the given audience.

### 1.2 `POST /qcwpbot/v1/email/ai/score-content`
- **Caller:** AiTools.jsx:203 (`handleScoreContent`, :194-223), card "Content Scorer". Button disabled when the content is empty (:846).
- **Body:**
  ```json
  { "content": "<p>Hi {{first_name}}, ...</p>", "subject": "Spring sale" }
  ```
  | Field | Type | Notes |
  |---|---|---|
  | `content` | string, required, trimmed | Plain text or HTML pasted by the user |
  | `subject` | string, **may be `""`** | "Subject (Optional Context)" |
- **Response** (bare object):
  ```json
  { "overall_score": 78, "spam_score": 12, "readability_score": 81, "engagement_score": 70,
    "summary": "Clear offer, weak CTA...", "improvements": ["Add a single bold CTA", "...", "..."] }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `overall_score` | number 0-100 | Shape marker (`!== undefined`). Rendered `{overall_score \|\| '--'}/100`, so `0` displays as `--`. Label OVERALL. |
  | `spam_score` | number **0-100** | Label SPAM RISK. `{spam_score ?? '--'}/100`. Green if `< 30`, else red. **Different scale from 1.3** (0-10). |
  | `readability_score` | number 0-100 | `\|\| '--'` |
  | `engagement_score` | number 0-100 | `\|\| '--'` |
  | `summary` | string | Shape marker (truthy). Paragraph. |
  | `improvements` | **string[]** | Only the **first 3** are rendered as `<li>{imp}</li>`. Must be strings. |
- Success when `overall_score !== undefined || summary`. Errors: §1.0 banner.

### 1.3 `POST /qcwpbot/v1/email/ai/spam-check`
- **Caller:** AiTools.jsx:238 (`handleCheckSpam`, :228-258), card "Spam Checker". Button disabled when both inputs are empty (:944).
- **Body:**
  ```json
  { "subject": "FREE!!! Act now", "content": "Click here to claim..." }
  ```
  | Field | Type | Notes |
  |---|---|---|
  | `subject` | string, trimmed, may be `""` | |
  | `content` | string, trimmed, may be `""` | **The key is `content`, not `body`** (the contract's §3.10 guess was `{subject, body}`). At least one of the two fields is non-empty. |
- **Response** (bare object):
  ```json
  { "verdict": "warning", "spam_score": 4,
    "word_flags": ["FREE", "Act now", "Click here"],
    "triggers": [ { "issue": "Excessive punctuation in subject", "recommendation": "Remove the !!!" }, "All-caps word in subject" ] }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `verdict` | string | Shape marker (truthy). Rendered verbatim (fallback `Checked`). Colour: `clean` green, `warning` yellow, **anything else red**. Recommended enum: `clean` \| `warning` \| `spam`. |
  | `spam_score` | number **0-10** | Shape marker (`!== undefined`). Rendered `Spam Score: {spam_score ?? 0} / 10`. |
  | `word_flags` | string[] | Rendered as red chips. Also re-sent as `spam_words` to 1.4. |
  | `triggers` | array of `string` **or** `{issue, recommendation}` | Only the **first 2** are rendered. For an object the UI renders `issue` (fallback `recommendation`). |
- Success when `verdict || spam_score !== undefined`. A "✨ Rewrite Clean Copy" button always appears under the result (:1010-1017). Errors: §1.0 banner.
- Implementation hint: combine a deterministic word/pattern scan (caps ratio, `!!!`, known spam phrases, link/image ratio) with the AI verdict so the endpoint still returns something useful when the provider is unavailable.

### 1.4 `POST /qcwpbot/v1/email/ai/rewrite-without-spam`
- **Caller:** AiTools.jsx:267 (`handleRewriteWithoutSpam`, :260-285), from the button inside the Spam Checker result.
- Client guard: the call is skipped unless `spamResult.word_flags || spamResult.triggers` is truthy. An empty array is truthy, so return arrays (even empty) from 1.3.
- **Body:**
  ```json
  { "subject": "FREE!!! Act now", "content": "Click here to claim...", "spam_words": ["FREE", "Act now"] }
  ```
  | Field | Type | Notes |
  |---|---|---|
  | `subject` | string (current input, trimmed, may be `""`) | |
  | `content` | string (current input, trimmed, may be `""`) | |
  | `spam_words` | string[] | `spamResult.word_flags \|\| []` |
- **Response** (bare object): `{ "subject": "Your spring offer is here", "content": "Hi there, ..." }`
  - Rendered only if `content || subject` is truthy. Otherwise nothing happens and no message appears.
  - `subject` is rendered after "Subject:". `content` is rendered as **plain text** (`white-space: pre-line`, max 100 px height, scrolls), not as HTML. Return plain text with newlines. If the input was HTML, either strip tags or accept that tags will show literally.
  - "Copy Both" copies `Subject: {subject}\n\n{content}`.
- Errors: `alert('Failed to rewrite: ' + (err.message || 'Unknown error'))`. This one uses an alert, not the banner.

### 1.5 `GET /qcwpbot/v1/email/ai/send-time?industry={industry}`
- **Caller:** AiTools.jsx:295 (`handleOptimizeSendTime`, :290-310), card "Send Time Optimizer". **GET**, no body.
- **Query:** `industry` (URL-encoded) is one of `E-commerce` (default), `SaaS`, `Education`, `Healthcare`, `Real Estate`, `Agency`, `Non-profit`, `Finance`, `General` (:1063-1071). The client falls back to `general` if empty, but that cannot happen from the select. Treat the value case-insensitively.
- UI copy (:1077-1079): "Calculates optimal send times based on your historical engagement data combined with industry-specific benchmark models." So the backend should combine the site's open/click timestamps (if any) with industry benchmarks.
- **Response** (bare object):
  ```json
  { "best_day": "Tuesday", "best_hour": 10,
    "analysis": "Your opens peak mid-morning on weekdays...",
    "best_times": [ { "day": "Tuesday", "hour": 10 }, { "day": "Thursday", "hour": 14 } ] }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `best_day` | string (weekday name) | Shape marker (truthy). Fallback text `Tuesday`. |
  | `best_hour` | int 0-23 | Rendered `{best_hour}:00` if `!== undefined`, else `10:00 AM`. 24-hour clock, no timezone label. Use the **site timezone**. |
  | `analysis` | string | Shape marker (truthy). Paragraph. |
  | `best_times` | `[{day: string, hour: int}]` | Only the **first 4** are rendered as chips `"{day} {hour}:00"`. |
- Success when `best_day || analysis`. Errors: §1.0 banner.

### 1.6 `GET /qcwpbot/v1/email/ai/segment-suggestions?goal={goal}`
- **Caller:** AiTools.jsx:320 (`handleSuggestSegments`, :315-335), card "Segment Suggestions". **GET**, no body. The button is always enabled.
- **Query:** `goal` is free text, URL-encoded, and **may be empty** (`goal=`). Example: "Increase repeat sales".
- UI copy (:1166-1167): "Analyzes your subscriber list, tags, and behavior to recommend highly targeted segments". The backend should feed aggregate CRM stats (contact counts per status, lists and tags with counts, engagement buckets) into the prompt. It should not send PII.
- **Response** (bare object; **`segments` must be an array**):
  ```json
  { "segments": [
      { "name": "Lapsed buyers", "description": "Customers with no open in 60 days",
        "criteria": "status = customer AND last_open < 60 days", "estimated_size": "~120 contacts" } ] }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `segments` | array | Shape marker: `Array.isArray(res.segments)`. Header "Suggested Segments ({length})". |
  | `segments[].name` | string | Bold title |
  | `segments[].estimated_size` | string or number | Badge if truthy |
  | `segments[].description` | string | Grey line |
  | `segments[].criteria` | string | "Rule: {criteria}" plus a Copy button (copies the string). Hidden if falsy. |
- Errors: §1.0 banner. Display only: nothing is saved, and there is no segment endpoint in the UI.

### 1.7 `GET /qcwpbot/v1/email/leads/autopilot`
- **Caller:** LeadFinder.jsx:102 on mount, always (no Pro check).
- **Response** (bare object). Each field is applied only if present or truthy:
  | Field | Type | UI mapping |
  |---|---|---|
  | `is_autopilot_enabled` **or** `enabled` | bool | `!!res.is_autopilot_enabled \|\| !!res.enabled` sets the Running/Paused banner and the tab's "ACTIVE" badge (:105) |
  | `mode` | `"filters"` \| `"prompt"` | `prompt` → Option B (AI Prompt), `filters` → Option A (Smart Form Filters). Missing → stays Option A (:106-110) |
  | `industry` | string | Option A industry **select** value (:111). Select values: `saas`, `ecommerce`, `healthcare`, `real_estate`, `finance`, `marketing`, `logistics` (:1019-1026). A custom free-text value is put into the select state (shown blank) and is still re-sent on save. |
  | `role` | string | Select values: `founder`, `marketing_vp`, `sales_vp`, `hr_director`, `it_manager` (:1042-1047) |
  | `location` | string | Select values: `''` (worldwide), `us`, `uk`, `ca`, `au`, `eu` (:1063-1068) |
  | `company_size` | string | `''`, `1-10`, `11-50`, `51-200`, `201-500`, `500+` |
  | `keyword` | string | "Keywords / niche focus" |
  | `prompt` | string | Option B natural-language prompt |
  | `daily_target` | int | `parseInt`. If 25, 50 or 100 → the preset button "`N Leads / Day`". Otherwise "Custom Number" with the input set to that value (:118-126) |
  | `target_list_id` | int (`0` = no list) | Compared as `String(target_list_id)` to the list options `String(l.id)`. Applied if `!== undefined` (:128-130) |
  | `tag_names` | string[] **or** comma string | Arrays are joined with `", "` into the "Automated CRM Tags" text field (:131-134) |
  | `total_leads` | int | Stat "Total Leads Ingested" |
  | `duplicates` | int | Stat "Duplicates Prevented" |
  | `bad_mx` | int | Stat "Bad MX Domains Filtered" |
  | `search_depth` | string, e.g. `"Page 3"` | Stat "Search Depth Pointer" (rendered verbatim) |
  | `last_execution` | string, human readable | Stat "Last Execution" (default `Never`) |
- Canonical response:
  ```json
  { "enabled": false, "is_autopilot_enabled": false, "mode": "filters",
    "industry": "saas", "role": "founder", "location": "us", "company_size": "11-50", "keyword": "Shopify",
    "prompt": "", "daily_target": 25, "target_list_id": 0, "tag_names": ["AI-Autopilot"],
    "total_leads": 0, "duplicates": 0, "bad_mx": 0, "search_depth": "Page 1", "last_execution": "Never" }
  ```
- Errors: `console.error` only. These stats **replace** the stats that the Free contract read from `GET leadfinder/settings`.

### 1.8 `POST /qcwpbot/v1/email/leads/autopilot`
Called with **two different payload shapes**. The backend must **merge** (update only the keys present).

**A. Save Autopilot Pipeline**: LeadFinder.jsx:321 (`handleSaveAutopilot`, :293-356), button "💾 Save Autopilot Pipeline".
```json
{ "enabled": false, "mode": "filters",
  "industry": "saas", "role": "founder", "location": "us", "company_size": "11-50",
  "keyword": "Shopify", "prompt": "Founders of SaaS startups in USA with 10-50 employees",
  "daily_target": 25, "target_list_id": 0, "tag_names": "AI-Autopilot, Cold-Lead" }
```
| Field | Type | Notes |
|---|---|---|
| `enabled` | bool | Current toggle state (also sent on save) |
| `mode` | `"filters"` \| `"prompt"` | Option A → `filters`, Option B → `prompt` |
| `industry`, `role`, `location` | string | **The custom text box wins over the select**: `customAutoX.trim() \|\| autoX` (:302-304). The value can be a select value (enum above) or arbitrary text, e.g. "Solar Energy". |
| `company_size` | string | enum above, `''` = any |
| `keyword` | string (trimmed) | |
| `prompt` | string (trimmed) | Sent in both modes |
| `daily_target` | int | 25/50/100, or the custom number (input min 5, max 500, **not enforced**; NaN → 25) |
| `target_list_id` | int | `parseInt(destList) \|\| 0`. The list **ID** from `GET email/lists` (0 = "All Contacts (Default / No List)") |
| `tag_names` | **string** (comma-separated, raw text) | Note: `GET` accepts an array or a string, but `POST` always sends a string. Split on `,` and trim. Create tags by name if missing. |

The UI sends a second request after A: `POST leadfinder/settings` (see §2, #39). Success notice (6 s): "✓ Autopilot Pipeline configuration saved successfully! Daily WP-Cron will discover and ingest leads automatically." Error (either call): inline notice `'Error saving autopilot: ' + err.message`.

**B. Enable / Pause toggle**: LeadFinder.jsx:362 (`toggleAutopilot`, :358-378).
```json
{ "enabled": true }
```
- Optimistic UI. On error the UI reverts the toggle and logs to the console (no message). On success it shows a notice ("ACTIVE" / "PAUSED").
- Behaviour: `enabled=true` schedules the daily WP-Cron event, `false` unschedules it.

- Response is ignored for both shapes (canonical: the full GET object from 1.7).
- This replaces the Free contract's `POST leadfinder/settings {is_autopilot_enabled}` toggle.

### 1.9 `GET /qcwpbot/v1/email/subscribers/export[?status=..][&list_id=..]`
- **Caller:** ImportExport.jsx:131 (`handleDownloadCsv`, :114-180), "📥 Download CSV". No Pro check in this UI.
- **Query** (each param is added only when set):
  | Param | Sent when | Values |
  |---|---|---|
  | `status` | select ≠ `all` | `subscribed`, `pending`, `unsubscribed`, `bounced`, `complained` (:381-388) |
  | `list_id` | select ≠ `'0'` | list ID (string of the int) |
- **Response must be JSON** (apiFetch parses it; a raw `text/csv` stream would fail):
  ```json
  { "csv": "Email,First Name,...\r\njane@example.com,Jane,...", "filename": "contacts-export-2026-10-28.csv", "total": 250 }
  ```
  | Field | Type | Usage |
  |---|---|---|
  | `csv` | **string (required)** | `typeof res.csv === 'string'` is required. Otherwise the error notice is "Failed to receive CSV export data from server." The client turns it into a `Blob` (`text/csv;charset=utf-8`) and triggers a download. Prefixing a UTF-8 BOM (`﻿`) is recommended for Excel. |
  | `filename` | string | Download name; default `contacts-export.csv` |
  | `total` | int (required in practice) | Success notice `Successfully exported {total} contact{total===1?'':'s'} to CSV!`. A missing value renders "undefined contacts". |
- Columns promised by the UI copy (:411), in order: **Email, First Name, Last Name, Phone, Status, Assigned Lists, Assigned Tags, Lead Source, Subscription Date**. Lists and tags are names joined with `, ` (or `|`). Lead Source is the contact's `source`. Subscription Date is `created_at`.
- An empty result can return `{csv: "<header row>", total: 0}`.
- Errors: inline notice `err.message` (fallback "Failed to export contacts. Please try again.").
- Large exports: everything is built in memory and returned as one JSON string. Stream rows into a buffer and set a sane cap, or chunk internally.

---

## 2. Contract endpoints with different usage in the Pro UI

### #10 `POST email/ai/generate-template`: **second caller with different expectations**
The Campaigns.jsx:387 caller is identical to the contract. **New caller:** AiTools.jsx:82 (`handleGenerateTemplate`, :70-110), card "Template Generator".
- Body: `{ "prompt": "<TYPE> EMAIL: <description>", "style": "<tone>", "layout_mode": "simple-html" }`
  - `prompt` is built as `${templateType.toUpperCase()} EMAIL: ${templateDesc.trim()}`, e.g. `"RE-ENGAGEMENT EMAIL: Win back users inactive for 90 days"`. Type values: `welcome` (default), `promotional`, `newsletter`, `transactional`, `re-engagement`, `announcement` (label "Product Launch") (:72-77).
  - `style`: any of `professional` (default), `friendly`, `formal`, `humorous`, `minimalist`, with **no Pro gating** in this caller.
  - `layout_mode`: always `simple-html`.
- Response fields read: `html` → `content` → bare string (same as the contract), **plus `provider`** (rendered: "✓ Template Generated (openai)") and **`model`** (stored, not rendered). Canonical response: `{ "html": "...", "subject": "...", "provider": "openai", "model": "gpt-4o-mini" }`.
- Empty content: inline error "No HTML content returned by AI. Please check your AI API key in Settings."
- Errors: §1.0 banner (`parseApiError`), not `alert`.
- Rendering: preview via `dangerouslySetInnerHTML` or raw HTML textarea, a Copy HTML button, and "💾 Save as Template" → #25 below.

### #15 `POST email/subscribers/import`: **third request shape (synchronous CSV import)**
The Subscribers.jsx:96 and :104 shapes are unchanged. **New caller:** ImportExport.jsx:83 (`handleImport`, :63-111), "⬆️ Upload CSV & Import" (the Free contract says this button had no handler).
- Body: `FormData` (multipart):
  | Part | Value | When |
  |---|---|---|
  | `file` | the selected `.csv` File | always (the button is disabled without a file) |
  | `source` | `csv` | always |
  | `list_id` | list ID as string, e.g. `"3"` | only if a list is chosen (≠ `'0'`) |
  | `list_ids[]` | the same ID (PHP receives `list_ids` as an array) | same condition |
  | `validation[skip_invalid_format]` | `'1'` \| `'0'` | always (default `'1'`). PHP receives `validation` as an associative array. |
  | `validation[skip_test_fake]` | `'1'` \| `'0'` | always (default `'1'`) |
- **Not sent:** delimiter, status, tags, update-existing. Recommended defaults: auto-detect the delimiter (`,` `;` tab), new contacts `status = subscribed`, existing emails skipped (counted as `skipped`) or updated (counted as `updated`). Pick one and keep it consistent with `message`.
- CSV expectations (UI hint :263): a header row with an **`email`** column (required). `first_name`, `last_name`, `phone` are optional. Match headers case-insensitively and tolerate "First Name" etc.
- **Response is now read** (the Free contract said ignored):
  | Field | Alias | Usage |
  |---|---|---|
  | `message` | – | Preferred success text |
  | `imported` | `total_imported` | Count (fallback 0) |
  | `updated` | `total_updated` | Count |
  | `skipped` | `total_skipped` | Count |
  Fallback text: `Import completed successfully: {imported} imported, {updated} updated, {skipped} skipped.` Canonical response: `{ "success": true, "imported": 40, "updated": 0, "skipped": 7, "message": "40 contacts imported, 7 skipped." }`.
- Errors: inline notice `err.message` (fallback "Failed to import subscribers from CSV. Please check the file formatting."). **Return readable messages** such as "The CSV has no 'email' column."
- Because Subscribers.jsx:96 sends the same `file` + `source=csv` with no `validation[...]` and ignores the response, one implementation (full synchronous import) serves both callers. With no `validation` param, default both flags to on.
- Fire `subscriber_created` and `list_added` automation triggers per imported contact (as in the contract).

### #16 `GET email/lists`: **wrapper and field-name conflict**
| Caller | Wrapper accepted | Name field read |
|---|---|---|
| Lists.jsx:21 | `res.items \|\| res` | `title` only |
| Campaigns.jsx:227 | `res.items \|\| res` | `title \|\| name` |
| Subscribers.jsx:41 | `listsRes.items \|\| listsRes` | `title \|\| name` |
| **ImportExport.jsx:42-46** | **bare array only** (`Array.isArray(data)`). `{items}` leaves both list selectors empty, silently. | **`name` only** (:277, :400) |
| **LeadFinder.jsx:89-97** | bare array or `res.items` | **`name` only** (:95). The label is `undefined` if missing. |
- **Required:** each list object must carry **both `title` and `name`** (same value), plus `id` (int), `description`, `subscribers_count`, `created_at`.
- **Wrapper:** to satisfy every Pro caller, `GET lists` must return a **bare JSON array**. All other callers, and the Free UI, also accept a bare array. This differs from the contract's §0.3 recommendation of `{items}`. Alternatively the lead engineer can patch ImportExport.jsx:44 to `data.items || data`.
- LeadFinder uses `String(l.id)` as the option value, so IDs round-trip as `target_list_id` / `list_id` ints.

### #25 `POST email/templates`: **second caller with a different payload**
The Templates.jsx:221 caller is unchanged. **New caller:** AiTools.jsx:120 (`handleSaveTemplateToLibrary`, :112-139).
```json
{ "name": "Welcome Email Template", "title": "Welcome Email Template",
  "category": "welcome", "content": "<html…>", "description": "Announce a 30% spring sale…", "type": "custom" }
```
- Extra field **`title`** (same as `name`). Accept either one and prefer `name || title`.
- **`category` is lower-case** and may be a value outside the Templates category list: `welcome`, `promotional`, `newsletter`, `transactional`, `re-engagement`, `announcement` (fallback `general`). Templates.jsx filters case-insensitively, so lower-case is fine for filtering. On edit, however, it capitalises unknown values (`Re-engagement`, `Announcement`), which are not options of the category select. **Normalise on save:** `welcome→Welcome`, `promotional→Promotional`, `newsletter→Newsletter`, `transactional→Transactional`, `general→General`, `re-engagement→Marketing`, `announcement→Promotional` (or extend the enum, which needs a UI change).
- **`description` holds the user's brief, not a subject line.** Templates.jsx later loads `description` into the "SUBJECT LINE" field, so the brief shows up as the subject. The UI does not send the AI's suggested subject. Store `description` as-is and accept the quirk, or fix the UI so it sends `subject` from the #10 response.
- Response ignored. On success the button shows "✓ Saved!" and is disabled. Errors: `alert('Failed to save template: ' + err.message)`.
- Pro gating: none in this caller (the contract says create is Pro-only, see §4).

### #38 `GET leadfinder/settings`: **reads a different field set**
LeadFinder.jsx:146-159. Fields read (each only if truthy):
| Field | Status vs. contract |
|---|---|
| `hunter_key`, `firecrawl_key`, `places_api_option`, `places_key`, `openai_key` | unchanged |
| **`custom_industry`**, **`custom_role`**, **`custom_location`** | **new**. Prefill the Instant Search custom text boxes. **No UI path ever writes these keys** (see #39). Return `''` or omit. |
| `autopilot_option`, `ai_prompt`, `auto_volume`, `custom_auto_volume`, `dest_list`, `auto_tags`, `is_autopilot_enabled`, `total_leads`, `duplicates`, `bad_mx`, `search_depth`, `last_execution` | **no longer read here.** The autopilot config and stats come from `GET email/leads/autopilot` (§1.7). |
- `openai_key` help text (:1600-1601): "Leave blank to use the active AI Provider configured in BotMaster Settings". So when it is empty, Option B prompt parsing must fall back to the General Settings AI provider (`email/settings.ai_provider` + key).

### #39 `POST leadfinder/settings`: **payload shapes changed**
Merge semantics still apply. Two shapes (the third, toggle, shape from the contract is gone and replaced by §1.8 B):
1. **Autopilot UI state**, LeadFinder.jsx:328, sent right after `POST email/leads/autopilot` (§1.8 A):
   ```json
   { "autopilot_option": "option-a", "custom_auto_industry": "", "custom_auto_role": "", "custom_auto_location": "",
     "auto_volume": "25 Leads / Day", "custom_auto_volume": "25", "dest_list": "0", "auto_tags": "AI-Autopilot" }
   ```
   | Field | Type | Change vs. contract |
   |---|---|---|
   | `autopilot_option` | `option-a` \| `option-b` | same |
   | `custom_auto_industry`, `custom_auto_role`, `custom_auto_location` | string (raw, untrimmed) | **new** |
   | `auto_volume` | `25 Leads / Day` \| `50 Leads / Day` \| `100 Leads / Day` \| `Custom Number` | same |
   | `custom_auto_volume` | numeric string | same (UI default is now `"25"`, was `"5"`) |
   | `dest_list` | **string list ID** (`"0"` = none) | **semantics changed**: it was a list *name* ("Customers") |
   | `auto_tags` | comma string | same |
   | `ai_prompt` | – | **no longer sent** (now `prompt` in §1.8) |
   - **None of these keys are read back by the Pro UI** (GET #38 does not return them to any state, and the autopilot form is hydrated from §1.7). They are write-only, so the backend may store them verbatim or ignore them. The authoritative autopilot config is §1.8.
2. **Integration keys**, LeadFinder.jsx:462 (`handleSaveApiKeys`): `{ hunter_key, firecrawl_key, places_api_option, places_key, openai_key }`, all trimmed strings, the same as the contract. Feedback changed: inline notice instead of `alert`. Errors: `'Error saving API keys: ' + err.message`.

### #40 `GET email/leads/search`: **query semantics and response fields changed**
LeadFinder.jsx:197 (`handleSearch`, :177-226).
- Query (`URLSearchParams`, all keys always present):
  | Param | Pro behaviour | Contract (Free) |
  |---|---|---|
  | `industry` | **`customIndustry.trim() \|\| industry`**: the custom text wins over the dropdown | dropdown won |
  | `role` | `customRole.trim() \|\| role` | dropdown won |
  | `location` | `customLocation.trim() \|\| location` | dropdown won |
  | `company_size` | same enum | same |
  | `keyword` | trimmed free text | same |
  | `limit` | **`10`, `25` (default), `50`, or a custom int** (input min 3, max 50, not enforced; NaN → 25) | `25/50/100`; Custom sent 25 |
  - Instant industry select values: `saas`, `ecommerce`, `healthcare`, `real_estate`, `finance`, `marketing` (no `logistics`; that value exists only in the autopilot select). Role: `founder`, `marketing_vp`, `sales_vp`, `hr_director`, `it_manager`. Location: `''`, `us`, `uk`, `ca`, `au`, `eu`.
  - Clamp `limit` server-side (e.g. 1-100).
- Response: `res.items` or a bare array (same). **Additional fields read per lead:**
  | Field | Usage | New? |
  |---|---|---|
  | `first_name`, `last_name`, `title`, `company`, `email`, `location` (`\|\| '—'`) | table | same |
  | `domain` | link text `{domain} ↗` (shown only if truthy) | **new** |
  | `website` | link href; fallback `https://{domain}` | **new** |
  | `mx_verified` | bool. "✓ MX Deliverable" (green) vs "⚠️ No MX" (red) | **new** (the contract suggested `mx_valid`; the UI reads **`mx_verified`**) |
  | `is_in_crm` | bool. Badge "In CRM" vs "New Prospect" | **new** (CRM de-dupe flag; return such leads instead of dropping them) |
  | `message` (top level) | Shown as a warning notice when `items` is empty. Fallback: "No prospects found matching your specific criteria. Try broadening your industry, job role, or location." | **new** |
- Canonical response: `{ "items": [ { "first_name": "", "last_name": "", "title": "", "company": "", "domain": "", "website": "", "email": "", "phone": "", "linkedin": "", "location": "", "mx_verified": true, "is_in_crm": false } ], "message": "" }`.
- All rows start pre-selected. The user can deselect rows before import.
- Errors: inline notice `'Error initiating search: ' + err.message` (was `alert`).

### #41 `POST email/leads/import`: **response now read**
LeadFinder.jsx:264 (`handleImportLeads`, :246-288).
- Body (same keys): `{ "leads": [<selected lead objects, verbatim incl. mx_verified/is_in_crm/domain/...>], "list_id": <int, 0 = none>, "tag_names": ["Instant-Search", ...] }`. `list_id` comes from a list dropdown (it used to be fixed at 0). `tag_names` is parsed from the comma text field and defaults to `['Instant-Search']`.
- **Response read:** `res.message` becomes the success notice (fallback `Successfully saved N prospect(s) to your contacts!`). Canonical: `{ "success": true, "imported": 20, "duplicates": 5, "message": "20 contacts saved, 5 already existed." }`.
- The UI only deselects rows. It does not remove them, so a second click re-sends the same leads. Import must be idempotent (de-dupe by email).
- Errors: inline notice `'Error saving leads: ' + err.message`.

### #42 `POST email/leads/autopilot/run`: **now has a body and the response is read**
LeadFinder.jsx:394 (`handleRunNow`, :380-435).
- Body: **identical to §1.8 A** (`enabled, mode, industry, role, location, company_size, keyword, prompt, daily_target, target_list_id, tag_names` with `tag_names` a comma string). These are the current **unsaved** form values: run with the posted config and fall back to the saved config for missing keys. Do **not** persist them.
- **Response read:**
  ```json
  { "success": true, "message": "Ingested 18 leads (4 duplicates, 2 bad MX).",
    "result": { "imported": 18, "skipped_duplicates": 4, "skipped_mx": 2, "page_pointer": 3 } }
  ```
  | Field | Usage |
  |---|---|
  | `result.imported` | **added** to the displayed Total Leads (delta, not absolute) |
  | `result.skipped_duplicates` | added to Duplicates Prevented |
  | `result.skipped_mx` | added to Bad MX Domains Filtered |
  | `result.page_pointer` | int. Search Depth is set to `Page {page_pointer}` if truthy |
  | `message` | success notice (fallback "Autopilot pipeline executed successfully!") |
  If `result` is absent the stats are untouched. Last Execution is set to "Just now" only when `result` is present.
- Server-side: also persist the cumulative counters, `search_depth` ("Page N") and `last_execution` so §1.7 returns them on the next load.
- Errors: inline notice `'Error running autopilot test: ' + err.message`. This can take long (search + scrape + MX), so keep one run bounded by `daily_target` and a time budget.

### Unchanged endpoints (request and response usage identical to the contract)

| # | Method | Path | Pro caller(s) | Notes |
|---|---|---|---|---|
| 1 | GET | email/analytics/overview?days= | Dashboard.jsx:19 | |
| 2 | GET | email/campaigns | Campaigns.jsx:13 | Free limits are still computed and unused (:44-53) |
| 3 | POST | email/campaigns | Campaigns.jsx:444 | |
| 4 | GET | email/campaigns/{id} | Campaigns.jsx:239 | |
| 5 | PUT | email/campaigns/{id} | Campaigns.jsx:429 | |
| 6 | DELETE | email/campaigns/{id} | Campaigns.jsx:31 | |
| 7 | POST | email/campaigns/{id}/duplicate | Campaigns.jsx:38 | |
| 8 | POST | email/campaigns/{id}/send | Campaigns.jsx:422 | |
| 9 | POST | email/campaigns/{id}/process | Campaigns.jsx:423 | |
| 11 | POST | email/ai/test-connection | Settings.jsx:227 | |
| 12 | GET | email/subscribers?per_page=100 | Subscribers.jsx:16 | |
| 13 | POST | email/subscribers | Subscribers.jsx:137 | |
| 14 | PUT | email/subscribers/{id} | Subscribers.jsx:186 | |
| 17-19 | POST/PUT/DELETE | email/lists[/{id}] | Lists.jsx:38/64/85 | |
| 20 | GET | email/tags | Tags.jsx:19, Campaigns.jsx:232, Subscribers.jsx:39 | `{items}` or bare both fine |
| 21-23 | POST/PUT/DELETE | email/tags[/{id}] | Tags.jsx:38/64/85 | |
| 24 | GET | email/templates | Templates.jsx:32, Campaigns.jsx:218 | |
| 26 | PUT | email/templates/{id} | Templates.jsx:208 | |
| 27 | DELETE | email/templates/{id} | Templates.jsx:243 | |
| 28-32 | GET/POST/GET/PUT/DELETE | email/automations[/{id}] | Automations.jsx:31/132/59/130/106 | |
| 33 | PUT | email/automations/{id}/sequences | Automations.jsx:163 | |
| 34 | GET | email/smtp | Smtp.jsx:24 | bare array still required |
| 35 | POST | email/smtp | Smtp.jsx:66 | |
| 36 | GET | email/settings | Settings.jsx:125 | |
| 37 | POST | email/settings | Settings.jsx:198 | |
| 43 | POST | email/leads/autopilot/reset-pointer | LeadFinder.jsx:439 | Body `{search_depth:"Page 1"}`. Success now shows an info notice. |
| 44 | GET | email/thirdparty/status | LeadFinder.jsx:162 | |
| 45 | POST | email/thirdparty/capture | LeadFinder.jsx:520 | Same request/response. Only difference: HTTP errors are now shown via `alert('Error updating capture setting: ' + err.message)` (Free: unhandled), so return readable messages. |
| 46 | POST | email/thirdparty/sync | LeadFinder.jsx:499 | |

That is 37 method+path combinations (rows 17-19, 21-23 and 28-32 expand to 3, 3 and 5).

---

## 3. Window data

### 3.1 `window.qcwpbotData`: fields read by the Pro UI
The Pro UI reads **exactly the same four fields** as the Free contract (§1): `hasPro`, `proUrl`, `siteName` (Settings.jsx:70), `adminEmail` (Settings.jsx:71). **No new fields are read.** Differences:
- `hasPro` default: LeadFinder.jsx:6 uses **`?? true`** (Free: `?? false`). Campaigns.jsx:182 uses `?? false`. Templates, Automations and Smtp use `Boolean(...)`.
- `freeLimits`: still not read anywhere.
- `wp.media` (global) is used by Campaigns.jsx:299 and Templates.jsx:85 (requires `wp_enqueue_media()`, which PHP already calls).

PHP (`class-wpbot-menu.php`:457-487) localizes many fields that **no Pro UI file reads**: `apiUrl` (already `rest_url('wpbot-crm/v1')`), `nonce`, `adminUrl`, `pluginUrl`, `version`, `modules`, `siteUrl`, `timezone`, `currentPage`, `hasWooCommerce`, `wooCartCutoffMinutes`, `wpRoles`, `aiConfigured`, `blockEditorSettings`, `currentUser`, `menuSlug`, `isDebug`. `hasPro` is hard-coded `true` there, and `proUrl` is `https://www.wpbot.pro/pricing/`. Because every path is hard-coded to `/qcwpbot/v1/...`, the `apiUrl` value has no effect. Moving to `/wpbot-crm/v1/` requires editing the paths in the JSX (or an apiFetch middleware that rewrites `/qcwpbot/v1/` paths).

### 3.2 `window.qcwpbotMarketingUI`
Not read by any file in `marketing-src/src/`. It is consumed only by `js/marketing-admin.js` (outside this source). PHP (`marketing_ui_config()`, class-wpbot-menu.php:310-360+) localizes the same keys as the contract §1.1 (`hasPro`, `proUrl`, `checkIcon`, `proFeatures`, `cards`, `tools`, `collapsedSections`, `navLabels`, `i18n`). `tools` keys match the Pro AI card titles (`template generator`, `subject line optimizer`, `content scorer`, `spam checker`, `send time optimizer`, `segment suggestions`). `marketing_has_pro()` (:229-231) returns `true` unconditionally.

`localStorage` keys are unchanged: `qcwpbot_active_tab` (App.jsx:19/27), `qcwpbot_settings_tab` (Settings.jsx:75/84).

---

## 4. hasPro / freeLimits / lock-dependent behaviour in the Pro UI (removal candidates)

`hasPro` is always `true` in Pro (PHP hard-codes it), so every `!hasPro` branch below is dead code in practice. They are listed so they can be deleted. "Hard lock" = disabled or PRO-labelled **regardless** of `hasPro`, which is a real functional gap in Pro.

| File:line | Behaviour | Type |
|---|---|---|
| Campaigns.jsx:44-53 | `limitCampaigns = 30`, `limitScheduled = 3` and derived percentages computed, never rendered | dead free-limit code |
| Campaigns.jsx:182-183 | `hasPro ?? false`, `proUrl` | gate source |
| Campaigns.jsx:385 | `layout_mode` forced to `simple-html` unless `hasPro` | hasPro |
| Campaigns.jsx:524-526, 537 | AI tone options formal/humorous/minimalist and layout table-safe disabled and "(PRO)" when `!hasPro` | hasPro |
| Campaigns.jsx:586-591 | Pro template click → upgrade confirm when `!hasPro` | hasPro |
| Campaigns.jsx:730-736 | `enable_ab` checkbox disabled / "(Available in Pro)" | hasPro |
| Campaigns.jsx:746-752 | `utm_status` checkbox disabled / "(Available in Pro)" | hasPro |
| Campaigns.jsx:760-766 | `send_throttle` input disabled, value forced to 0, PRO help text | hasPro |
| **Campaigns.jsx:782** | **"Smart Segments" recipient button is always `disabled` with a PRO badge** (no `send_to: "segments"` path exists) | **hard lock** |
| Templates.jsx:6-7 | `hasPro`, `proUrl` | gate source |
| Templates.jsx:135-140 | "+ New Template" → upgrade confirm when `!hasPro` | hasPro |
| Templates.jsx:152-157 | Edit Pro template → upgrade confirm | hasPro |
| Templates.jsx:182-186 | Save new template blocked when `!hasPro` | hasPro |
| Templates.jsx:238-241 | Delete Pro template blocked | hasPro |
| Templates.jsx:265-269 | PRO badge on "+ New Template" | hasPro |
| Templates.jsx:339, 351-372, 381, 397 | Card title/tooltip, "🔒 Upgrade to PRO" link, lock icon on Edit for Pro templates | hasPro |
| **Templates.jsx:625-635** | "✨ AI Generate" button: upsell when `!hasPro`, **otherwise a no-op** (no endpoint wired; #10 could be reused) | hasPro + unimplemented |
| Automations.jsx:6-7 | `hasPro`, `proUrl` | gate source |
| **Automations.jsx:358-368** | "✨ AI Suggest Steps": upsell when `!hasPro`, **otherwise a no-op** (no endpoint) | hasPro + unimplemented |
| Smtp.jsx:6-7 | `hasPro`, `proUrl` | gate source |
| Smtp.jsx:23 | `GET email/smtp` skipped when `!hasPro` | hasPro |
| Smtp.jsx:42-46 | Save blocked with alert + opens `proUrl` | hasPro |
| Smtp.jsx:79-109 | Whole screen replaced by a "🔒 PRO Feature" lock card | hasPro |
| LeadFinder.jsx:6-7 | `hasPro ?? true`, `proUrl` | gate source |
| LeadFinder.jsx:492-495 | "Sync past" → opens `proUrl` when `!hasPro` | hasPro |
| LeadFinder.jsx:514-517 | Capture toggle for non-`wpforms` sources → opens `proUrl` | hasPro |
| LeadFinder.jsx:544-547 | `isProOnly = sourceKey !== 'wpforms'`, `isLocked = !hasPro && isProOnly`, `isCapturing` forced false when locked | hasPro |
| LeadFinder.jsx:569-586, 611-612 | PRO badge, "Pro Only" pill, locked description text | hasPro |
| LeadFinder.jsx:634, 637-651 | "Sync past" tooltip and PRO badge | hasPro |
| LeadFinder.jsx:654-668 | "Unlock Pro" button instead of Capture | hasPro |
| **ImportExport.jsx:5-18, 293-325** | `ProBadge` + four **always-disabled, always-unchecked** validation checkboxes: Skip disposable, Skip role-based, Skip suspicious spam-pattern, Check MX records. Never sent to the backend. | **hard lock** |
| Sidebar.jsx:28 | Renders a PRO badge when `item.isPro`. No item sets it (Free had lead-finder/smtp badges). | dead |
| Subscribers.jsx:334-335 | `className="btn-pro"` on "Find Leads" / "Export Contacts" buttons (styling hook for `.btn-pro .pro-badge`, style.css:248). No badge element is rendered. | cosmetic |
| class-wpbot-menu.php:229-231, 312, 467 | `marketing_has_pro()` returns `true`, and `hasPro => true` in both localized objects | PHP source of the flag |

Not gated in Pro (the Free contract had gates): AI Tools (no `hasPro` / `ProFooter` at all), Import/Export export button, Subscribers import modal skip flags (no PRO badges), Lead Finder autopilot/instant/integrations tabs (no lock screens; default tab is `autopilot`, LeadFinder.jsx:8).

**Backend consequence:** if the backend keeps Free/Pro enforcement (the contract's "Pro-only server checks"), Pro must report Pro, or every new endpoint in §1 and the changed #15/#25/#38-#42 will 403. For the Pro build, gate on a single server-side capability (e.g. `wpbot_crm_is_pro()`) and do not gate on any request field.

---

## 5. Route map: old Pro path → new path under `/wpbot-crm/v1/`

Conventions from ROUTE-MAP.md: drop `email/`, `subscribers` → `contacts`, `leadfinder/settings` → `leads/settings`, `thirdparty` → `integrations`, AI routes under `ai/`, permission `current_user_can('manage_options')`.

| Kind | Method | Old UI path (`/qcwpbot/v1/…`) | New path (`/wpbot-crm/v1/…`) | Controller | Owner (per ROUTE-MAP) |
|---|---|---|---|---|---|
| new | POST | email/ai/optimize-subject | ai/optimize-subject | AiController | D |
| new | POST | email/ai/score-content | ai/score-content | AiController | D |
| new | POST | email/ai/spam-check | ai/spam-check | AiController | D |
| new | POST | email/ai/rewrite-without-spam | ai/spam-rewrite | AiController | D |
| new | GET | email/ai/send-time?industry= | ai/send-time | AiController | D |
| new | GET | email/ai/segment-suggestions?goal= | ai/segment-suggestions | AiController | D |
| new | GET | email/leads/autopilot | leads/autopilot | LeadsController | D |
| new | POST | email/leads/autopilot | leads/autopilot | LeadsController | D |
| new | GET | email/subscribers/export?status=&list_id= | contacts/export | ContactsController | C |
| changed #10 | POST | email/ai/generate-template | ai/generate-email | AiController | D |
| changed #15 | POST | email/subscribers/import | contacts/import | ContactsController | C |
| changed #16 | GET | email/lists | lists | GroupsController (type=list) | C |
| changed #25 | POST | email/templates | templates | TemplatesController | B |
| changed #38 | GET | leadfinder/settings | leads/settings | LeadsController | D |
| changed #39 | POST | leadfinder/settings | leads/settings | LeadsController | D |
| changed #40 | GET | email/leads/search | leads/search | LeadsController | D |
| changed #41 | POST | email/leads/import | leads/import | LeadsController | D |
| changed #42 | POST | email/leads/autopilot/run | leads/autopilot/run | LeadsController | D |

Unchanged rows keep the mapping already in ROUTE-MAP.md.

Notes for the router:
- Register `contacts/export` and `contacts/import` **before** `contacts/(?P<id>\d+)`. The `\d+` pattern already prevents a clash, but avoid a loose `(?P<id>[^/]+)`.
- Register `leads/autopilot` (GET+POST) separately from `leads/autopilot/run` and `leads/autopilot/reset`.
- The two GET AI routes take query args. Declare `industry` (string, default `General`) and `goal` (string, default `''`) in `args` with `sanitize_text_field`.
- Every route uses `permission_callback` = `current_user_can('manage_options')`.

---

## 6. Other observations relevant to the backend

1. **Two stores for autopilot config.** §1.8 (`leads/autopilot`) is authoritative and read back. The `leadfinder/settings` autopilot keys (#39 shape 1) are write-only, so store them in one option or ignore them. Pick one cron source: `leads/autopilot.enabled`.
2. **`tag_names` type drift**: an array in `leads/import`, a comma string in `leads/autopilot` POST and `run`, and either in `leads/autopilot` GET. Accept both everywhere and return an array from GET.
3. **Spam score scales differ**: `score-content.spam_score` is 0-100 (green < 30), `spam-check.spam_score` is 0-10.
4. **The `mx_verified` field name** (not `mx_valid`) is what the UI reads for search results.
5. **Run stats are deltas.** `run.result.*` values are added client-side, while `GET leads/autopilot` returns absolute totals.
6. **Lists must carry `name` and `title`**, and `GET lists` should be a bare array (§2 #16).
7. **Export and import are JSON/multipart over apiFetch**: export must return JSON `{csv, filename, total}`, not a file stream.
8. **AI Tools errors** can carry provider detail in `data.error` (shown as `message — detail`).
9. **Unwired buttons**: Templates "AI Generate" and Automations "AI Suggest Steps" do nothing in Pro. "Connect to Cold Outreach Funnel" now navigates to the Automations tab (LeadFinder.jsx:1233). No endpoint is needed for it.
