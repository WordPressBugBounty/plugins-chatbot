# WPBot Core (shared services)

Shared services for the WPBot Growth Suite: AI client and keys, job queue, permissions, connections (Google, YouTube), Business Profile and Content Library. Bundled into WPBot Pro and Free by a sync script; used by AI Writer and later modules.

- Product: WPBot by QuantumCloud
- License: GPL-2.0-or-later
- Requires: WordPress 6.2+, PHP 7.4+
- Trunk: `dev` · Released versions: `main`

Do not edit synced copies of this module inside host plugins; change it here and re-sync.

## Architecture

- `wpbot-core.php` registers this copy with the newest-copy loader (`loader.php`); the newest bundled copy boots on `plugins_loaded` (priority 1) and fires `qcld_wpbot_core/loaded`.
- Hosts register a `QuantumCloud\WPBotCore\Host\Host_Adapter` with `QuantumCloud\WPBotCore\Core::instance()->register_host()` inside that action.
- Modules use `AI\AI_Service::generate()`, `Jobs\Job_Store`, the permission caps `wpbot_{section}_{view|use|publish|settings}` and the Content Library.
- Lower-level AI client (`AI\Provider_Client`, no keys or budget of its own; callers pass the key): `send( $provider, $model, $key, Request $request, $base_url = '' )`, `stream( $provider, $model, $key, Request $request, callable $on_text, $base_url = '' )` (server-sent events, every provider format), `embed( $provider, $model, $key, array $texts, $base_url = '' )` (vectors; catalogue in `AI\Embeddings`; a retired model is replaced through `Embeddings::current_model( $provider, $model )`, map `Embeddings::RETIRED`) and `list_models( $provider, $key, $base_url = '' )`. `AI\Request::make()` takes an optional `temperature` (0–2).
- Tool calling (`AI\Tool_Calls`): `Request->tools` ([ { name, description, parameters } ]) and `Request->tool_choice` ('auto', 'none', 'required' or a tool name); messages may hold assistant `tool_calls` and `role: tool` results; `Provider_Client::send()` returns `Response->tool_calls` ([ { id, name, arguments } ]) and `Response->finish_reason`. One internal format for OpenAI-compatible providers, Anthropic and Gemini; not on `stream()`. `AI\Providers::supports_tools( $provider, $model )` (filter `qcld_wpbot_core/supports_tools`).
- Several hosts (R8): `Core::primary_host_id()` (the registered host installed first; filter `qcld_wpbot_core/primary_host`), `Core::hosts()`, `Core::ai_host()` (the host whose AI Settings the keys follow; null = own keys), `Core::module_pages()` (filter `qcld_wpbot_core/module_pages`). Names: `Host\Brand::product()`, `pro_product()`, `agent_token()` (filter `qcld_wpbot_core/product_name`). Modules show `Brand::product()` instead of a literal "WPBot".
- Model lists (`AI\Model_Catalog`): `available()` returns the chat models the provider offers the saved key (cached a day), `offered()` whether a saved model is still offered, `picks()` the recommended models (`Providers` `pick`: value, quality, cheap; the best value pick is the default), `maybe_refresh_prices()` weekly current prices from OpenRouter's public model list (filter `qcld_wpbot_core/model_price_feed`, option `qcld_wpbot_core_model_prices`). Filter `qcld_wpbot_core/non_chat_models`.
- Model routing (`AI\Model_Routing`): `replacement()` for retired models (filter `qcld_wpbot_core/retired_models`), `route()` (filter `qcld_wpbot_core/route_model`), `fallback()` (filter `qcld_wpbot_core/fallback_provider`).
- Cost estimates: `AI\Usage::estimate( $provider, $model, $tokens_in, $tokens_out )` uses the catalogue's prices (`AI\Providers`, filter `qcld_wpbot_core/ai_providers`). `AI\Providers::price_known( $provider, $model )` says whether the model has a price (false for models typed by hand, so screens can show "price unknown" instead of $0; Ollama counts as known, cost 0); `model_info()` carries the same as `price_known`.
- Module key sets (`AI\Keys`, round R3): each set has a source. `settings` (the default, also for sets saved before R3) follows the host's AI Settings live: `get_set()`, `credentials()`, `call_order()` read the host's `Host_Adapter::get_ai_import()` (`active`, `secondary`, `providers`) on every request and nothing is copied; only image models are kept per module. `own` keeps the module's own encrypted copy. `Keys::update( $module, array( 'source' => 'own' | 'settings' ) )` switches (to `own` starts from a copy of AI Settings); any key, switch or primary/backup change saved while following switches to `own`. `Keys::follows_settings( $module )`; `public_view()` carries `source`, `source_name` (the host's name) and `source_url` (its AI Settings screen). `reimport()` does nothing while following. `POST /keys/{module}` accepts `source`.
- REST namespace: `wpbot-core/v1` (`/keys/{module}`, `/keys/{module}/import`, `/keys/{module}/test`, `/health/{module}`, `/jobs`, `/jobs/{id}`, `/jobs/{id}/tick|stop|resume|cancel`, `/usage/{module}`, `/spend`, `/spend/group/{id}`, `/spend/report`, `/spend/settings`, `/spend/raise`).
- Knowledge base (`Knowledge\Kb`): `qcld_wpbot_core_kb_search( $query, $args )` (args top_k, min_score, types, labels: the visitor's access labels, filtered in SQL before scoring, omitted = public only, false = everything; results: title, url, content, score, source_type, source_id, heading_path, anchor), `qcld_wpbot_core_kb_context( $query, $args )` (prompt text), `qcld_wpbot_core_kb_store( $source, $item )` (item: id, title, url, content, language, access, html, chunks), `qcld_wpbot_core_kb_set_access( $source, $id, $labels )`, `qcld_wpbot_core_kb_access( $source, $id )`, `qcld_wpbot_core_kb_remove( $source, $id )`, `qcld_wpbot_core_kb_items( $args )` (what the knowledge base holds, newest first, for settings screens: page, per_page, search, type, status (`ready` / `waiting`); each item source_type, source_id, title, url, chunks, ready, status, error, updated_at), `Knowledge\Kb::status()`, `Knowledge\Kb_Sources::sync()`, `Knowledge\Kb_Migration::start()`. Tables `qcld_wpbot_core_kb_sources` / `_kb_chunks` (migration option `qcld_wpbot_core_kb_db_version`). Modules never query these tables.
- Social connections (`Connections\…`, added for WPBot Social; shared with the chatbot channels by agreement, see wpbot-core#24):
  - `Social_Apps::update( $app, $changes )` / `client_id()` / `client_secret()` / `public_view()`: the owner's own app ID and secret per app (`meta`, `threads`, more through `qcld_wpbot_core/social_apps`), encrypted, masked, in `qcld_wpbot_core_social_apps` (autoload off).
  - `Auth_Provider` interface; `Own_App_Auth` (default provider; endpoints from `qcld_wpbot_core/social_oauth_endpoints`, optional PKCE, `client_param` and `basic_auth`); `Social_Auth::provider( $app )`, `Social_Auth::redirect_uri()` (`admin-post.php?action=qcld_wpbot_core_social_oauth`), `Social_Auth::take_notice()`.
  - `Oauth_State`: one-time state bound to the user, single use (only the request whose delete succeeds may use it), 10-minute expiry; PKCE verifier kept server-side. `auth_url( $app, $scopes, $return_url, $capability, $owner = '' )`: `owner` (for example `social`) comes back in the payload so each module only takes its own logins.
  - Masks: a posted secret made only of `•` or `*` followed by up to 4 characters (this screen's `••••abcd`, the chatbot screens' `********abcd`) keeps the stored secret (`Social_Apps::is_mask()`).
  - The callback checks the state and the capability the caller asked for, swaps the code (Meta and Threads tokens are made long-lived right away) and hands the tokens to the module through `qcld_wpbot_core/social_authorized`; Core never stores account tokens. Redirects only to this site's admin.
  - `Meta`: `VERSION` (pinned Graph version), `permissions()` (union of `qcld_wpbot_core/meta_permissions`), `request()` (token in the `Authorization` header, `appsecret_proof`), `extend()`, `pages( $user_token )` (Page tokens and linked Instagram accounts), `check( $token )` (ok / expired / error), `token_app_id( $token )`, `subscribed_fields()`, `merge_subscribed_fields( $page_id, $page_token, $fields, $app_secret = null )` (read, merge, write; matches the token's own app through `GET /app`, writes nothing when that app cannot be identified, never drops fields), `connection_in_use( $page_id )` (filter `qcld_wpbot_core/meta_connection_in_use`, default false: chatbot channels say when a Page's connection answers messages, so a module can warn before disconnecting it), `channel_app_mismatch()`. Core never calls `DELETE /me/permissions`.
  - One Meta app (v1): Social and the chatbot channels must use the same Meta app. `channel_app_mismatch()` returns the channels' app ID (filter `qcld_wpbot_core/channel_meta_app_id`, default the Messenger add-on's `wpfb_app_id`) when it differs from `Social_Apps`, so screens can warn. Webhooks keep checking signatures with the channel's own secret: the Messenger add-on's `wpfb_app_secret` takes precedence over `Social_Apps::client_secret( 'meta' )`, which is only its fallback when empty. The Messenger add-on still calls Graph v11.0; Core pins `Meta::VERSION`.
  - `Threads`: `extend()`, `refresh()` (own OAuth, `graph.threads.net`).
  - `Connection_Status::set( $connection, $account, $status, $cause )` / `get()` / `forget()` in `qcld_wpbot_core_connection_status`; fires `qcld_wpbot_core/connection_status_changed` on change.
- AI spending (`Spend\…`, rebuild 6): every AI call is checked against the owner's limits and recorded. All figures are estimates.
  - `Spend::allow( $module, $feature, $estimate = 0 )` returns true or a `WP_Error` with code `qcld_wpbot_core_budget_reached` (the old budget code, kept so existing callers work). Its data holds `cause` `budget_reached`, `reason` (`limit`, `estimate` or `paused`), `scope` (`global` or a group id) and `resets_at`.
  - `Spend::record( $module, $feature, array $usage, array $meta )` stores one call (usage: `tokens_in`, `tokens_out`, `tokens_cached`, `tokens_cache_write`, `tokens_reasoning`, `images`, `web_searches`, `audio_seconds`, `characters`, `kind`, `provider_cost`; meta: `provider`, `model`, `task`, `job_id`, `channel`, `cost`).
  - `Spend::wrap( $module, $feature, $callable, $meta )` is for code that keeps its own HTTP call: it allows the call, runs it, reads the usage from the JSON answer (or from `$meta['usage']`) and records it.
  - `Spend::estimate_job()` is the pre-flight check for big jobs. `Spend::status()` returns the spend strip's numbers.
  - The same are available as functions: `qcld_wpbot_core_spend_allow()`, `qcld_wpbot_core_spend_record()`, `qcld_wpbot_core_spend_wrap()`, `qcld_wpbot_core_spend_estimate_job()`, `qcld_wpbot_core_spend_status()`.
  - **Groups** (`Spend\Features`): `chatbot`, `knowledge_base`, `chat_sessions`, `forms_tickets`, `voice`, `images`, `writer`, `seo`, `social`, `insights`, `automator`, and `other` for unclaimed calls. A module/feature pair maps to a group with `Features::group_of()` (filters `qcld_wpbot_core/spend_features`, `qcld_wpbot_core/spend_feature_group`).
  - **Settings** (`Spend\Spend_Settings`, option `qcld_wpbot_core_spend`): a monthly (reset day 1–28) or weekly (weekday) period in the site's time zone, a global limit and a limit per group (0 = none), paused groups, and the alerts. Old module budgets (`Settings` `budgets`) are moved in once, and `Settings::budget()` now reads the group limit.
  - **Prices** (`Spend\Prices`, filter `qcld_wpbot_core/spend_prices`): tokens with cached-input discounts, embeddings, images, speech, transcription and web searches. Models without a price are recorded with `price_known` false. `Spend\Usage_Reader::from_body()` reads any provider's usage block.
  - `AI_Service::generate()` and the knowledge base's embeddings are metered. Jobs whose step hits a limit pause (`Job_Store::wait_for_spend()`) and resume after the reset or a raise (`Job_Store::resume_spend_waiting()`).
  - **Safety net** (`Spend\Net`): WPBot's own `wp_remote_*` requests to AI hosts that do not go through the meter are recorded (`source` `net`, module `unrouted`, the group taken from the file's folder). At the global limit, or for a paused group, they are refused. Other plugins' requests are never touched. Requests marked `qcld_wpbot_spend` = `metered` (`Provider_Client`) and requests inside `Spend::wrap()` are skipped. It cannot see raw cURL, `file_get_contents` or browser-side calls, so those are routed through the meter instead. Filters: `qcld_wpbot_core/spend_ai_hosts`, `qcld_wpbot_core/spend_own_paths`, `qcld_wpbot_core/spend_path_groups`.
  - **Alerts** (`Spend\Alerts`): an email when the global spend passes each of the owner's thresholds (default 50/80/100%), and when a group reaches its own limit. Each is sent once per period, to every administrator plus the extra recipients, with the optional subject prefix. Buttons open Spending and limits, which needs a login, and the email carries no token. A dismissible admin notice shows on the dashboard and WPBot screens. Alerts are checked when a call is recorded (at most once a minute) and hourly by WP-Cron (`qcld_wpbot_core_spend_hourly`). The daily event `qcld_wpbot_core_spend_daily` handles retention and the weekly hook. `Spend::raise( $scope )` adds half the limit for the rest of the period.
  - **Reports** (`Spend\Report`): `group( $id )` for the (i) panel (tasks, the 10 most expensive runs, a 14-day trend) and `report( $from, $to )` for the AI Usage page (days, groups, features, models, safety-net calls, calls without a price). Pro adds `channels`, `providers` and `csv` through `qcld_wpbot_core/spend_report_sections`. `rows()` gives per-call rows for CSV.
  - **REST** (`wpbot-core/v1`, capability `manage_options`, filter `qcld_wpbot_core/spend_capability`): `GET /spend`, `GET /spend/group/{id}`, `GET /spend/report?from&to`, `GET|POST /spend/settings`, `POST /spend/raise`.
  - **Retention** (`Spend\Rollup`): per-call rows are kept 90 days and daily totals 13 months (settings). **Privacy** (`Spend\Privacy`): personal data exporter and eraser for the admin user id kept with admin actions. Visitors' ids are never stored.
  - URLs: `Spend_Urls::settings( $group )` (filter `qcld_wpbot_core/spend_settings_url`, default the host's AI Settings with `section=spending`) and `Spend_Urls::report()` (filter `qcld_wpbot_core/spend_report_url`).
  - **Overshoot:** limits are checked before each call, so calls already in flight can pass a limit slightly (about one call per concurrent visitor).
  - **Data:** the usage table gains spend columns, and the new table `qcld_wpbot_core_usage_daily` holds daily totals. Migrations run on their own track, `qcld_wpbot_core_spend_db_version`. No prompt, visitor message or answer is ever stored.
- Uninstall: hosts call `qcld_wpbot_core_uninstall_for_host( $host_id )` from `uninstall-helper.php`.

### Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `qcld_wpbot_core/loaded` | action | Core booted; register hosts and modules |
| `qcld_wpbot_core/primary_host` | filter | Choose the primary host |
| `qcld_wpbot_core/adopted_sections` | filter | Section ids that use the permissions grid |
| `qcld_wpbot_core/permission_sections` | filter | Add sections (label, description) |
| `qcld_wpbot_core/ai_providers` | filter | Provider and model catalogue, prices |
| `qcld_wpbot_core/ai_endpoint` | filter | Endpoint URL (tests) |
| `qcld_wpbot_core/embedding_providers` | filter | Embedding provider and model catalogue (vector sizes, prices) |
| `qcld_wpbot_core/kb_embedding` | filter | Knowledge base embedding profile: provider, model, key, url (null = keyword search only) |
| `qcld_wpbot_core/kb_post_access` | filter | Access labels of a post in the knowledge base (`public`, `logged_in`, `role:*`, `membership:*`, `course:*`, `purchased:*`; [] = everyone) |
| `qcld_wpbot_core/brand` | filter | The brand: colours plus `name`, `logo`, `url` (read through `Host\Brand::name()`, `logo()`, `url()`; hosts set them in `get_brand()`) |
| `qcld_wpbot_core/kb_post_types` | filter | Post types the knowledge base reads |
| `qcld_wpbot_core/kb_post_text` | filter | Text of one post in the knowledge base |
| `qcld_wpbot_core/kb_sources` | filter | Other knowledge sources: id => label, count(), items( offset, limit ) |
| `qcld_wpbot_core/kb_scan_limit` | filter | Recent pieces scored by vector besides FULLTEXT hits (default 2000) |
| `qcld_wpbot_core/job_handlers` | filter | Step kind => callable |
| `qcld_wpbot_core/job_done` | action | A job finished |
| `qcld_wpbot_core/key_status_changed` | action | A key started or stopped failing |
| `qcld_wpbot_core/social_apps` | filter | Social apps whose ID and secret the owner can save |
| `qcld_wpbot_core/google_write_scopes` | filter | Google write scopes a module needs after the owner turned its feature on; only scopes in `Google::WRITE_SCOPES` (now `youtube.upload`) are accepted |
| `qcld_wpbot_core/social_oauth_endpoints` | filter | OAuth endpoints per app (authorize, token, separator, pkce; optional `client_param` for apps that name the client id differently, such as TikTok's `client_key`, and `basic_auth` to send the app id and secret as HTTP Basic on the token request, as X and Pinterest require) |
| `qcld_wpbot_core/social_auth_providers` | filter | Social auth providers (`Auth_Provider`); default `Own_App_Auth` |
| `qcld_wpbot_core/social_authorized` | filter | A social login finished: the module that started it stores the tokens and returns true or a `WP_Error` |
| `qcld_wpbot_core/social_refresh` | filter | Refresh tokens for apps added by modules |
| `qcld_wpbot_core/meta_permissions` | filter | Meta permissions each consumer needs (asked for as a union) |
| `qcld_wpbot_core/connection_status_changed` | action | A social connection's health changed (connection, account, status, cause) |
| `qcld_wpbot_core/meta_connection_in_use` | filter | Whether a chatbot channel uses a Page's Meta connection (`false`, `$page_id`) |
| `qcld_wpbot_core/channel_meta_app_id` | filter | The Meta app ID the chatbot channels use (default the `wpfb_app_id` option) |
| `qcld_wpbot_core/spend_features` | filter | Spending groups (id => label) |
| `qcld_wpbot_core/spend_feature_group` | filter | Group of a module's feature (group, module, feature) |
| `qcld_wpbot_core/spend_prices` | filter | Prices that are not per token, and cached-input shares |
| `qcld_wpbot_core/spend_ai_hosts` | filter | AI hosts the safety net watches (host => provider id) |
| `qcld_wpbot_core/spend_own_paths` | filter | Folders whose requests count as WPBot's own |
| `qcld_wpbot_core/spend_path_groups` | filter | Calling-file fragment => spending group for the safety net |
| `qcld_wpbot_core/spend_recorded` | action | One AI call was recorded (row without any text) |
| `qcld_wpbot_core/spend_limit_reached` | action | A limit stopped a call (scope, reason, module, feature), once per scope and request |
| `qcld_wpbot_core/spend_settings_saved` | action | Spending settings were saved |
| `qcld_wpbot_core/spend_capability` | filter | Capability for the spending screens and REST routes (default `manage_options`) |
| `qcld_wpbot_core/spend_alert_recipients` | filter | Who receives spending alerts |
| `qcld_wpbot_core/spend_notice_screen` | filter | Whether the spending notice shows on an admin screen |
| `qcld_wpbot_core/spend_report_sections` | filter | Extra report sections: `channels`, `providers`, `csv` (Pro) |
| `qcld_wpbot_core/spend_task_label` | filter | Owner-facing name of a task |
| `qcld_wpbot_core/spend_settings_url` | filter | URL of Spending and limits |
| `qcld_wpbot_core/spend_report_url` | filter | URL of the AI Usage report |
| `qcld_wpbot_core/spend_hourly` | action | Hourly spending check (status); Pro's spike alert |
| `qcld_wpbot_core/spend_weekly` | action | Once a week on the owner's weekday (settings); Pro's summary email |

Secrets: define `QCLD_WPBOT_CORE_KEY` in `wp-config.php` to use your own encryption key instead of the site salts.

## Development

```bash
composer install
npm install
composer run lint   # PHPCS: WordPress Coding Standards + PHPCompatibility (PHP 7.4+)
composer run test   # PHPUnit unit tests
npm run build       # @wordpress/scripts build
```

Strings use the placeholder text domain `qcwr-textdomain`; the host plugin's sync script replaces it (`wpchatbot` in WPBot Pro).
