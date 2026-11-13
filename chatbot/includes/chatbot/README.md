# WPBot Chatbot (core module)

The chatbot core of WPBot. It ships unchanged in **WPBot Free** and **WPBot Pro**, synced into each host at `includes/chatbot/`. Pro-only features live in [wpbot-chatbot-pro](https://github.com/qcld/wpbot-chatbot-pro) and plug in through the hooks below.

- Plan, decisions and progress: [qcld/botmaster `docs/chatbot-rebuild/`](https://github.com/qcld/botmaster/tree/feature/chatbot-rebuild/docs/chatbot-rebuild)
- Baseline browser suite: [qcld/botmaster `tests/baseline/`](https://github.com/qcld/botmaster/tree/feature/chatbot-rebuild/tests/baseline)

Requirements: PHP 7.4+, WordPress 6.2+, WPBot Core (bundled by both hosts).

## How a host bundles it

```php
// In the host's main plugin file:
require_once __DIR__ . '/includes/chatbot/chatbot.php';

add_action( 'qcld_wpbot_chat/loaded', function ( $module ) {
	$module->register_host( new My_Host_Adapter() ); // implements QuantumCloud\Chatbot\Host\Host_Adapter
} );
```

Every bundled copy registers itself; on `plugins_loaded` (priority 3, after WPBot Core) only the newest copy boots. The first host to register wins; WPBot Free stops itself when Pro is active, so only one host registers on a normal site.

**Uninstall:** call the module's helper from the host's `uninstall.php`, before WPBot Core's:

```php
if ( file_exists( __DIR__ . '/includes/chatbot/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/chatbot/uninstall-helper.php';
	qcld_wpbot_chat_uninstall_for_host( 'wpbot-pro', __DIR__ ); // 'wpbot-free' in WPBot Free.
}
```

It does nothing while another plugin with the module (`*/includes/chatbot/chatbot.php`) is installed, active or not. Otherwise it clears the module's scheduled events and, only when the owner turned on "Delete chatbot data when the plugin is deleted" (AI Settings > Chatbot data, option `qcld_wpbot_chat_delete_on_uninstall`, off by default), drops the module's tables and deletes its `qcld_wpbot_chat_*` options and transients. WPBot Core's data follows WPBot Core's own setting.

**New installs:** when the host is activated on a site that never had WPBot (for example its response table did not exist yet), add the option `qcld_wpbot_chat_new_install` (`add_option( 'qcld_wpbot_chat_new_install', 1, '', false )`). The module then starts the shared AI settings with the knowledge base and the current page on, and deletes the option. Existing sites keep their settings.

Never edit the synced copy inside a host. Change this repository, then run the host's `npm run sync:chatbot` (`npm run check:chatbot-sync` fails the build on a hand-edited copy).

## Public API and hooks

| Name | Type | Purpose |
| --- | --- | --- |
| `qcld_wpbot_chat()` | function | The booted `QuantumCloud\Chatbot\Module`. |
| `qcld_wpbot_chat_modules()` | function | The module registry behind Module Manager. |
| `qcld_wpbot_chat/loaded` | action | The module booted; hosts call `register_host()`. |
| `qcld_wpbot_chat/host_ready` | action | A host registered. |
| `qcld_wpbot_chat/provider_settings_fields` | action | Host prints it on each AI provider's settings page (`$provider` id); the module adds the missing common options. |
| `qcld_wpbot_chat/kb_settings_panel` | action | Host prints it on its knowledge base settings page; the module adds the status panel. |
| `qcld_wpbot_chat/register_modules` | action | Register switchable features: `$registry->register( array( 'id', 'name', 'description', 'default', 'path', 'order' ) )`; retire a stored path with `$registry->retire( $path )`. |
| `qcld_wpbot_chat/ai_failed` | action | The AI provider failed (`WP_Error`, `Message`): visitors get the friendly message; hosts keep the real error for the site admin. |
| `qcld_wpbot_chat/knowledge_gap` | action | A question the bot could not answer (`$question`, `$reason`: no_answer, not_sure, thumbs_down, handoff, `$message`); recorded on Train. Fired by the engine (not sure, no answer) and by the legacy widget's unanswered-question request (`wpbo_failed_response`, `$message` null). |
| `qcld_wpbot_chat/gap_write_article` | filter | Write an article for a knowledge gap: return the URL to open (`$url`, `$gap`). Without a handler a draft post opens. |
| `qcld_wpbot_chat/gap_answered` | action | The owner answered a knowledge gap (`$gap`, `$answer`). |
| `qcld_wpbot_chat/visitor_labels` | filter | The visitor's knowledge access labels (`$labels`, `$message`, `$user`). |
| `qcld_wpbot_chat/admin_parent` | filter | Parent menu of the Train page (default the host's chatbot menu). |
| `qcld_wpbot_chat/abilities` | filter | The chatbot's WordPress Abilities (`name => label, description, input, execute, permission, readonly`); registered on WordPress 6.9+. |
| `qcld_wpbot_chat/change_kinds` | filter | Kinds of change in the approval queue (`kind => label, apply, undo, approval`). |
| `qcld_wpbot_chat/change_needs_approval` | filter | Whether a change waits for the owner (`$needs`, `$kind`, `$input`). |
| `qcld_wpbot_chat/features_page` | filter | Show the Features page (hosts without Module Manager). |
| `qcld_wpbot_chat/answer_cache` | filter | Whether an answer may come from and go into the answer cache (`$use` default false, `$question`, `$args`). Never return true for answers that used personal context. |
| `qcld_wpbot_chat/pre_answer_review` | filter | Return the AI's verdict text for an answer cache check without calling the AI (tests); `null` calls it (`$text`, `$prompt`). |
| `qcld_wpbot_chat/pipeline_steps` | filter | Conversation steps in order (`Conversation\Step` objects: `id()`, `enabled()`, `handle( Message, Context )` returning a `Reply` or null). |
| `qcld_wpbot_chat/message_received` | action | A visitor message arrived (`Message`). |
| `qcld_wpbot_chat/message_sent` | action | The bot answered (`Reply`, `Message`). For channel messages, `$message->channel` is the channel and `$message->extra` carries what the host passed to `Conversation\Channel::ai_reply()` (WPBot Pro: `event_id`, `account_remote_id` of the inbound item). |
| `qcld_wpbot_chat/channel_event` | action | Fired by WPBot Pro's channel add-ons (not by this module): one inbound item from Messenger, Instagram, WhatsApp or Telegram, after the signature check (`verified`), before the bot answers. Interface: botmaster `docs/chatbot-features/channel-event.md`. |
| `qcld_wpbot_chat/channel_reply_sent` | action | Fired by WPBot Pro's channel add-ons: the channel bot sent any reply to an inbound item (`$event_id`, `$channel`, `$account_remote_id`), once per item. |
| `qcld_wpbot_chat/ai_context` | filter | Extra system text parts sent to the AI with a question. |
| `qcld_wpbot_chat/session_saved` | action | A session's legacy rows were written (`$session_id`, `$wpbot_user_id`, `$is_new`). |
| `qcld_wpbot_chat/lead_captured` | action | A lead was saved (`name`, `email`, `phone`, `url`); `qcld_mailing_list_subscription_success` still fires. |
| `qcld_wpbot_chat/ai_form_saved` | action | An AI Actions form entry was saved (`$post_id`, `$title`, `$fields`). |
| `qcld_wpbot_chat/sessions_enabled` | filter | Whether sessions are saved (default: the Chat Sessions save handler is loaded). |
| `qcld_wpbot_chat/option_defaults` | filter | Defaults for options a site has not stored; a host adapter may also give `option_defaults()`. |
| `qcld_wpbot_chat/rate_limit` | filter | `[ requests, seconds ]` per bucket: `ai` (default 60 per day, option `qcld_wpbot_chat_ai_day_limit`), `message`, `session`, `lead`, `feedback`, `report`, `token`. |
| `qcld_wpbot_chat/rate_limit_text` | filter | Text shown when the AI limit is reached. |
| `qcld_wpbot_chat/visitor_ip` | filter | IP used for rate limits (sites behind a trusted proxy). |

### Launcher

Hosts that give `widget_assets()` on their adapter (`url`: assets under it belong to the widget; `exclude`: sub-paths that do not) get the launcher: the widget's assets load on the first interaction with the icon, on idle when a setting needs the widget early, and normally on pages with an inline chat. Turn it off with the option `qcld_wpbot_chat_lazy_widget` = "0", the filter `qcld_wpbot_chat/lazy_widget` or the constant `WPBOT_LAZY_WIDGET_DISABLE`. Scripts that need the widget call `window.qcldWpbotChatLoad( callback )` or listen for the `qcld_wpbot_chat_loaded` event on `document`.

### Conversation engine

`Conversation\Chat::answer( Message, $on_text )` answers a message end to end: history from the stored session, the pipeline (Simple Text Responses, DialogFlow v2/CX, site search, knowledge base, AI, fallback; `qc_site_search_priority` = 1 moves site search after the AI) and the transcript. REST, the legacy AJAX shims and (later) the channels all use it.

### REST routes (`wpbot-chatbot/v1`)

| Route | Purpose |
| --- | --- |
| `POST /token` | Visitor token (24 hours), fetched when the chat opens so cached pages work. |
| `POST /message` | `text`, `session_id`, `name`, `language`, `page_url`, `page_title`, `history`, `extra` (`strid`, `intent`, `ai_action`), `stream`. JSON `{ source, text, html, data, meta }`, or server-sent events (`{type:"text"}` pieces, then `{type:"done", reply}`) with `stream: true`. |
| `POST /session/{id}` | Visitor details for the session row (name, email, phone, source URL). |
| `POST /lead` | Lead capture (email and/or phone). |
| `POST /feedback` | `like` / `dislike` on a message. |
| `POST /report` | Report a message (admin email at most every 10 minutes per visitor). |

Visitor routes need the token (header `X-WPBot-Token` or `token`) and are rate limited.

### Data

- Table `{prefix}qcld_wpbot_chat_messages`: one row per message (session, role, channel, source step, text, JSON payload, model, tokens). Schema option `qcld_wpbot_chat_db_version` (7; schema 7 imports the hosts' old "Questions Not Answered" into Train again, never counting a question twice).
- Tables `{prefix}qcld_wpbot_chat_gaps`, `{prefix}qcld_wpbot_chat_changes` and `{prefix}qcld_wpbot_chat_answer_cache`. AI Settings > Chatbot data shows their row counts. All four are dropped on uninstall only as described under "How a host bundles it".
- The legacy `wpbot_user` / `wpbot_conversation` rows are written from it, so the Sessions screens and CSV export are unchanged. Privacy exporter and eraser are registered.

### Legacy AJAX actions

`Legacy\Ajax_Shims` answers the old widget's `wpbo_search_response`, `wpbo_search_site`, `wpbo_failed_response`, `wpbot_save_feedback` and `wpbot_save_report` with the engine (same JSON), counts visits once per 30 minutes, stores structured copies of saved transcripts, and guards the AI and DialogFlow actions (requests from other sites refused, guest AI limit). STR Pro, Extended Search and provider extras keep their own handlers until they move into the Pro module.

### Module registry

Module Manager lists what is registered. Each feature keeps the path it has always had in the stored state (`folder/file.php` under the host's add-on folder), and the state stays in the `wpbot_master_addons` option in the same format, so every site keeps its on/off choices. Features the site has never seen are added with their default; retired paths are dropped and never loaded.

## Development

```bash
composer install
vendor/bin/phpunit   # unit tests (no WordPress needed)
vendor/bin/phpcs     # WordPress Coding Standards + PHP 7.4 compatibility
```

Text domain placeholder: `qcbot-textdomain` (the sync script swaps it for `wpchatbot` in Pro and `chatbot` in Free). Prefixes: `qcld_wpbot_chat`, `QCLD_WPBot_Chat`, namespace `QuantumCloud\Chatbot`.
