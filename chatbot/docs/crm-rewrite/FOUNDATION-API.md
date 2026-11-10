# CRM foundation API (for feature agents)

Everything lives in `includes/ai-marketing/`, namespace `QuantumCloud\WPBot\CRM`, autoloaded (`Sub/Class.php`).
PHP 7.4 syntax only. All dates stored in DB are **UTC** MySQL strings; use `Support\Dates` to format responses.
Repository rows are **arrays**: `id` + integer columns cast to `int`, flag columns to `bool`, JSON columns decoded to `array` (write arrays, they are encoded for you). Unknown keys passed to `insert/update` are silently dropped.

## 1. Registering your code (Plugin.php)

`includes/ai-marketing/Plugin.php` already lists the expected class names. **If your class uses one of these names you need not edit Plugin.php.** Otherwise add your FQCN to the right array (or use the filter).

```php
// Controllers   → Plugin::controllers()   filter 'wpbot_crm/controllers'
Http\ContactsController, Http\GroupsController, Http\CampaignsController, Http\TemplatesController,
Http\AutomationsController, Http\AnalyticsController, Http\SettingsController, Http\SmtpController,
Http\AiController, Http\LeadsController, Http\SubscribeController, Http\IntegrationsController
// Runtime services (hooks) → Plugin::services()  filter 'wpbot_crm/services'
Jobs\Scheduler, Mail\SmtpConfigurator, Mail\Tracking, Domain\AutomationEngine,
Integrations\ChatbotLeads, Integrations\WordPressUsers, Integrations\WooCommerce,
Integrations\Shortcode, Integrations\FormCapture, Integrations\LeadsAutopilot
```
- Controller: `class X extends Http\RestController { public function register_routes() {...} }` — instantiated with **no constructor args** on `rest_api_init`, only if `class_exists`.
- Service: `public static function init()` — called on `init` priority 5, only if `class_exists`. Register your hooks there (cron schedules, query_vars, shortcodes, `add_action`s).
- `Plugin::boot()`, `Plugin::dir(): string` (trailing slash), `Plugin::url( $path = '' ): string`.
- On `plugins_loaded` (prio 20) `Domain\Schema::maybe_upgrade()` runs.
- `bootstrap.php` is not yet required by `qcld-wpwbot.php` (lead engineer wires it).

## 2. Global functions (`functions.php`)
- `qcwpbot_has_pro(): bool` — `class_exists('qcld_wb_Chatbot') || defined('QC_PLUGIN_DIR')`, filter `wpbot_crm/has_pro`.
- `qcwpbot_free_limits(): array` — `campaigns_per_month 30, campaign_daily_sends 500, email_scheduled_campaigns 3, email_smtp_connections 2`, filter `wpbot_crm/free_limits`.

## 3. Domain\Schema
| | |
|---|---|
| `const VERSION = '1.0.0'`, `VERSION_OPTION = 'wpbot_crm_db_version'`, `SECRET_OPTION = 'wpbot_crm_secret'`, `TABLES` | |
| `static table( string $name ): string` | `table('contacts')` → `wp_wpbot_crm_contacts`. Names: contacts, groups, group_members, campaigns, messages, events, templates, automations, automation_runs |
| `static maybe_upgrade(): bool` | runs `install()` when the option ≠ VERSION |
| `static install(): void` | dbDelta, `secret()`, `Domain\TemplateSeeder::seed()` **if that class exists**, set version |
| `static secret(): string` | 64-char HMAC secret, created if missing |
| `static tables_exist(): bool` | |
| `static drop_all(): void` | drops all tables + deletes `wpbot_crm_*` options and transients (uninstall) |
| `static sql(): string[]` | CREATE TABLE statements |

`TemplateSeeder::seed()` (Agent B) must be **idempotent** (use `TemplateRepository::find_builtin_by_name()`); it runs on every install/upgrade. To re-run on an existing site, bump `Schema::VERSION` or delete the version option.

### Columns (see Schema.php for types)
- **contacts**: id, email (unique, lower-case), first_name, last_name, phone, company, website, status, source, attributes (JSON array), token (char32 unique), confirmed_at, unsubscribed_at, last_activity_at, created_at, updated_at
- **groups**: id, type (`list|tag`), name, slug (unique per type), description, created_at, updated_at
- **group_members**: group_id, contact_id, added_at (PK group_id+contact_id)
- **campaigns**: id, name, subject, preview_text, from_name, from_email, reply_to, content_html, template_id, audience (JSON), settings (JSON), status (`draft|scheduled|sending|paused|sent|failed`), scheduled_at, started_at, finished_at, recipients, sent, failed, created_at, updated_at
- **messages**: id, campaign_id (NULL for automations), automation_id, step_key, contact_id, status (`queued|sent|failed|skipped`), error, queued_at, sent_at, first_open_at, opens, first_click_at, clicks. UNIQUE(campaign_id, contact_id)
- **events**: id, contact_id, message_id, type, data (JSON), url_hash (sha1 of data.url), created_at
- **templates**: id, name, category, subject, content_html, design (JSON), is_builtin (bool), is_pro (bool), created_at, updated_at
- **automations**: id, name, status (`published|draft`), trigger_type (= contract `trigger_name`), trigger_config (JSON), steps (JSON array), enrolled, completed, created_at, updated_at
- **automation_runs**: id, automation_id, contact_id, step_index, status (`waiting|done|exited|failed`), next_run_at, created_at, updated_at. UNIQUE(automation_id, contact_id)

## 4. Repositories (Domain\, all `new X()` with no args)

### Common base `Domain\Repository` (every repository has these)
- `table(): string`
- `find( int $id ): ?array`
- `find_many( int[] $ids ): array<int,array>` keyed by id
- `insert( array $data ): int` (0 on failure; created_at/updated_at auto)
- `update( int $id, array $data ): bool` (updated_at auto)
- `delete( int $id ): bool`
- `increment( int $id, string $column, int $by = 1 ): bool` (int columns only, floors at 0)
- `hydrate( array $row ): array`
- `static decode( $json ): array`, `static ids( $mixed ): int[]` (unique positive ints)

### ContactRepository
- `find_by_email( string ): ?array`, `find_by_token( string ): ?array`
- `search( array $args ): array{items,total}` — args `page`(1), `per_page`(20, 0=all), `search`, `status`, `list_id`, `tag_id`, `orderby` (id|email|created_at|first_name|last_name|status), `order`
- `count( string[] $statuses = [] ): int`, `count_by_status(): array<string,int>`
- `existing_emails( string[] ): string[]` (lower-cased)
- `chunk( int $after_id, int $limit = 500 ): array[]` (keyset iteration for export)
- `touch_activity( int $id ): bool`
- `delete()` also removes memberships and runs
- Use **ContactService** for creating/updating (hooks, tokens, validation).

### GroupRepository (`const TYPES = ['list','tag']`)
- `all_with_counts( string $type, string $search = '' ): array[]` — each row + `subscribers_count` (int)
- `find_typed( int $id, string $type ): ?array`, `find_by_slug( string $type, string $slug ): ?array`
- `create( string $type, string $name, string $description = '' ): int` (unique slug)
- `ensure( string $type, string $name ): int` — find by slug or create
- `delete( int $id )` also deletes memberships
- `existing_ids( int[] $ids, string $type = '' ): int[]`
- `member_count( int $group_id ): int`
- `add_member( int $group_id, int $contact_id ): bool` (true if new), `remove_member(...)`: bool — **no hooks**
- `ids_for_contact( int $contact_id, string $type = '' ): int[]`
- `set_members_for_contact( int $contact_id, string $type, int[] $ids ): array{added:int[],removed:int[]}` — **no hooks** (ContactService::update fires them)
- `groups_for_contacts( int[] $contact_ids ): array` → `[contact_id => ['list'=>[['id'=>3,'name'=>'VIP']], 'tag'=>[...]]]` (one query; map `name`→`title` in responses)
- `static type( $t ): 'list'|'tag'`, `static slugify( $name ): string`

### CampaignRepository (`const STATUSES`)
- `search( array $args ): array{items,total}` — `page`, `per_page` (0=all), `search`, `status`; newest first
- `count_created_since( string $utc ): int`, `count_by_status( string ): int`, `by_status( string ): array[]`
- `due_scheduled( string $now_utc ): int[]`
- `set_status( int $id, string $status, array $extra = [] ): bool`
- `delete()` also deletes the campaign's still-`queued` messages
- int columns: template_id, recipients, sent, failed (use `increment()`)

### MessageRepository
- `insert( array $data ): int` (status `queued`, queued_at now by default) — single automation email
- `enqueue( array $rows ): int` — rows `{contact_id, campaign_id?, automation_id?, step_key?}`, multi-row INSERT IGNORE
- `claim( int $limit, int $campaign_id = 0 ): array[]` — oldest queued first; campaign messages only while the campaign is `sending`. **Does not change status** — caller must hold the Scheduler lock and then call one of:
- `mark_sent( int $id )`, `mark_failed( int $id, string $error = '' )`, `mark_skipped( int $id, string $reason = '' )`
- `counts_for_campaign( int $id ): array{queued,sent,failed,skipped,total,opened,clicked,opens,clicks}` (opened/clicked = unique recipients)
- `stats_for_campaigns( int[] $ids ): array<int,{sent,opened,clicked,queued}>` (one query, for lists)
- `remaining_for_campaign( int $id ): int`
- `count_sent_since( string $utc, bool $campaigns_only = true ): int`, `count_sent_between( string $from, string $to ): int`
- `record_open( int $id ): ?bool`, `record_click( int $id ): ?bool` — increments counter, sets first_* once; returns true on first, false on repeat, null if message missing
- `delete_for_campaign( int $campaign_id ): int`

### EventRepository
- `log( string $type, int $contact_id, array $data = [], int $message_id = 0 ): int` — sets url_hash when `$data['url']`
- `counts_by_type( string $from_utc, string $to_utc, string[] $types = [], bool $unique = false ): array<string,int>` (requested types default to 0)
- `clicks_by_url( array $args ): array[]` — args `campaign_id`, `from`, `to`, `limit`(20) → `{url, url_hash, clicks, contacts}`
- `for_contact( int $contact_id, int $limit = 50 ): array[]`
- `purge_older_than( string $utc ): int`

### TemplateRepository
- `all( array $args = [] ): array[]` — `search`, `category` (case-insensitive); built-ins first
- `find_builtin_by_name( string ): ?array`, `count_builtin(): int`

### AutomationRepository (`const STATUSES = ['published','draft']`)
- `all(): array[]` (newest first), `published_for_trigger( string $trigger ): array[]`
- `overview(): array{total_automations,total_enrolled,total_active,total_completed}` (active = waiting runs)
- `delete()` also deletes runs; int columns enrolled/completed (`increment()`)

### RunRepository
- `enroll( int $automation_id, int $contact_id, string $next_run_at_utc ): int` (0 if already enrolled — at most once per automation)
- `due( string $now_utc, int $limit = 100 ): array[]`
- `advance( int $id, int $step_index, string $next_run_at_utc ): bool`, `finish( int $id, string $status = 'done' ): bool`
- `count_by_status( int $automation_id = 0 ): array{waiting,done,exited,failed}`
- `exists( int $automation_id, int $contact_id ): bool`, `delete_for_automation( int ): int`

## 5. Domain\ContactService
`new ContactService( ?ContactRepository, ?GroupRepository, ?EventRepository )` (all optional).
- `const STATUSES = ['subscribed','unsubscribed','pending','bounced','complained','customer']`
- `static normalize_status( $status, string $fallback = '' ): string` — `unconfirmed`→`pending`; unknown → `$fallback`
- `create( array $data, string $source = 'manual' ): int|WP_Error` — fields email (required), first_name, last_name, phone, company, website, status, attributes, list_ids, tag_ids. Errors: `wpbot_crm_invalid_email` 400, `wpbot_crm_contact_exists` 409.
- `upsert( array $data, string $source, array $group_ids = [] ): int|WP_Error` — new contact: status = `$data['status']` or `subscribed`; if double opt-in is on and the source is public (`chatbot, shortcode, subscribe, form, cf7, wpforms, gravity, conversational`, filter `wpbot_crm/public_sources`) → `pending` + `send_confirmation()`. Existing contact: fills **only empty** fields, merges attributes (existing keys win), **never changes status**, adds groups (never removes). `list_ids`/`tag_ids` in `$data` are merged into `$group_ids`.
- `update( int $id, array $data ): array|WP_Error` — returns updated contact. `list_ids`/`tag_ids` present ⇒ replace semantics. `status` change routed through `change_status`. Errors 404 `wpbot_crm_contact_not_found`, 400 `wpbot_crm_invalid_status`, 409 exists.
- `change_status( int $id, string $status, array $context = [] ): array|WP_Error` — sets `unsubscribed_at` / `confirmed_at` (pending→subscribed|customer), logs event `unsubscribe` or `confirm` (context merged into data, `context['message_id']` links the message). **Do not log those events again.**
- `add_to_group( int $contact_id, int $group_id ): bool`, `remove_from_group(...)`: bool — fire hooks
- `find( int ): ?array`, `find_by_email( string ): ?array`
- `send_confirmation( ?array $contact )` → `do_action( 'wpbot_crm/send_confirmation', $contact )`
- `is_public_source( string ): bool`, `sanitize( array ): array`, `generate_token(): string`
- New contacts get a `subscribe` event (data.source).

## 6. Domain\AudienceResolver
- `static normalize( array $settings ): array{send_to,lists,tags,exclude_lists,exclude_tags}` (accepts list_ids/tag_ids aliases; unknown send_to → all)
- `where( array $settings ): string` — SQL on alias `c` (contacts); sendable = `subscribed|customer`; `lists`/`tags` with empty selection → `1=0`
- `count( array $settings ): int`, `ids( array $settings, int $limit = 0 ): int[]`
- `enqueue_for_campaign( int $campaign_id, array $settings ): int` — one `INSERT IGNORE … SELECT`, safe to repeat

## 7. Domain\Personalizer
- `static render( string $text, array $contact, array $extra = [], bool $escape = true ): string` — `{{tag}}` (unknown → '') and `{tag}` (only known tags replaced). Tags: first_name, last_name, full_name, email, phone, company, website, scalar `attributes`, site_name, site_url, year, plus `$extra` (e.g. `unsubscribe_url`), which overrides. `$escape=true` esc_html()s values (bodies); pass false for subjects.
- `static tags( array $contact, array $extra = [] ): array`

## 8. Domain\Limits (static)
- `is_pro(): bool`, `limit( string $key ): int`
- `can_create_campaign(): true|WP_Error` (30 per calendar month, site tz), `can_schedule(): true|WP_Error` (3 `scheduled`), `remaining_daily_sends(): int` (500 − campaign messages sent since local midnight; `PHP_INT_MAX` for Pro), `daily_limit_error(): WP_Error`
- `require_pro( string $feature = '' ): true|WP_Error`, `pro_error( string $msg = '' )` (`wpbot_crm_pro_required`, 402), `limit_error( string $msg )` (`wpbot_crm_limit_reached`, 429)

## 9. Support
### Settings (static, option `wpbot_crm_settings`)
- `defaults(): array` (contract §3.9; site name/admin email at runtime; filter `wpbot_crm/settings_defaults` to add keys)
- `all(): array` (typed; API keys **decrypted**), `get( $key, $fallback = null )`, `bool( $key )`, `int( $key )`, `string( $key )`
- `api_key( string $provider ): string` — provider openai|gemini|claude|openrouter|grok
- `for_response(): array` — booleans/ints typed, keys masked `••••` + last 4
- `save( array $input ): array` — merge-save known keys, sanitises (emails, ints min 1, `wp_kses_post` footer, provider whitelist), encrypts keys, ignores values starting with `••••`; returns `for_response()`
- `mask( string ): string`, `is_masked( string ): bool`, `key_fields(): string[]`, `to_bool( $v ): bool`, `reset_cache()`
- consts `PROVIDERS`, `BOOLS`, `INTS`, `MASK`

### Crypto (static)
- `encrypt( string ): string` → `enc:` + base64(iv+cipher), AES-256-CBC, key = sha256(wp_salt('auth')); plaintext returned if OpenSSL missing; '' stays ''
- `decrypt( string ): string` — non-prefixed values returned as-is; failure → ''
- `is_encrypted( $v ): bool`, `available(): bool`

### Csv (static)
- `sniff_delimiter( string $line ): string` (`,` `;` tab)
- `normalize_header( string ): string` → email|first_name|last_name|phone|status|company|website or sanitised key
- `rows( string $path, string $delimiter = '' ): Generator` — yields `[normalized_header => trimmed value]`, BOM-safe, skips blank lines
- `send_headers( string $filename )`, `write( array $rows, array $headers = [] )` → php://output (formula-injection safe), `safe_cell( $v ): string`

### Dates (static)
- `now(): string` UTC MySQL
- `iso( ?string $utc ): ?string` → `Y-m-d\TH:i:s` site tz (null when empty)
- `mysql( ?string $utc ): string` → `Y-m-d H:i:s` site tz ('' when empty) — templates
- `to_utc( string $local ): string` (site tz unless offset given; '' if unparsable)
- `today_start_utc()`, `month_start_utc()`, `days_ago_utc( int $days )`, `add( string $utc, int $value, string $unit )` (minutes|hours|days; '' base = now)

## 10. Http\RestController (abstract)
- `const NS = 'wpbot-crm/v1'`; `abstract register_routes()`
- `admin_permission(): bool|WP_Error` — use as `'permission_callback' => array( $this, 'admin_permission' )`
- protected: `items_response( array $items, int $total, int $per_page = 0 ): WP_REST_Response` (`{items,total,pages}` + X-WP-Total headers), `error( $code, $message, $status = 400 ): WP_Error`, `int_list( $value ): int[]` (array/CSV/scalar), `bool( $value ): bool`, `require_pro( string $feature = '' ): true|WP_Error`, `pagination( $request, int $default_per_page = 0 ): [page, per_page]` (max 500), `id_arg(): array`

## 11. Hooks fired by the foundation
| Hook | Args | Fired by |
|---|---|---|
| `wpbot_crm/contact_created` | `array $contact` | ContactService::create/upsert (after groups are attached) |
| `wpbot_crm/contact_updated` | `array $contact, array $old` | update/upsert (when something changed) |
| `wpbot_crm/contact_status_changed` | `array $contact, string $old, string $new` | change_status (also via update) |
| `wpbot_crm/group_added` | `int $contact_id, array $group` | create/upsert/update/add_to_group |
| `wpbot_crm/group_removed` | `int $contact_id, array $group` | update/remove_from_group |
| `wpbot_crm/send_confirmation` | `array $contact` | upsert (DOI) / send_confirmation |

`$group['type']` tells list vs tag (automation triggers list_added/tag_added). Feature agents fire the rest of ARCHITECTURE §10 (`message_sent`, `email_opened`, `link_clicked`, `contact_confirmed`).

Filters: `wpbot_crm/has_pro`, `wpbot_crm/free_limits`, `wpbot_crm/controllers`, `wpbot_crm/services`, `wpbot_crm/settings_defaults`, `wpbot_crm/public_sources`.
