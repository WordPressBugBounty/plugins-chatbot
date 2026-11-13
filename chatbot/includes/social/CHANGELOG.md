# Changelog

## 0.2.3 (unreleased)
### Changed
- **The Google connection is set up in Social** (owner, 12 November 2026). Connect Google Business Profile and the Master module's YouTube Shorts steps said "WPBot settings › Connections › Google", a page Social does not have. Now:
  - Accounts › Connect Google Business Profile has three steps: turn on Business Profile access (Social's own switch, option `qcld_wpbot_social_gbp_access`, through WPBot Core's `qcld_wpbot_core/business_profile_enabled`), connect Google, add your locations.
  - The new `Admin\Google_Card` shows WPBot Core's one Google connection: status (not set up, not connected, reconnect needed with the missing permissions, needs attention, connected), the OAuth client steps (redirect address with Copy, client ID and secret), Connect or Reconnect Google, and Disconnect. AI Writer and SEO & AI Search use the same connection.
  - The action `qcld_wpbot_social/google_card` ( `$purpose`: `business` or `youtube` ) prints the card, so the Master module shows it in its YouTube steps.
  - REST (`wpbot_social_settings` and `manage_options`): `GET|POST /google/connection`, `POST /google/connect`, `POST /google/disconnect`, `POST /google/business-profile`.
  - "Reconnect Google in WPBot settings" messages now point to Social › Accounts.

## 0.2.2 (unreleased)
### Changed
- Settings › Writing: Social uses the keys saved in WPBot › AI Settings (WPBot Core R3: module key sets follow AI Settings by default); the help text says so.
### Fixed
- Create post › Write with AI: "Continue with selected" stayed visible before any version was written (the results bar's `hidden` attribute was overridden by its `display: flex`), and clicking it did nothing. Elements with `hidden` now stay hidden on every Social screen; a click with nothing written explains what to do.
- Write with AI: ticking versions for networks with no connected account ended with the server's "Choose at least one account." The button now names those networks and links to Accounts, and the versions stay on the screen. Ticked versions with no text say so instead of "Tick at least one version."
- On/off switches (Settings, Accounts, and Social Pro's Inbox, Queue and Team) were plain checkboxes stretched to 36×20 px, showing as a blue box with a tick. They are now real switches in the brand colour; settings cards put one setting per row.
- QA #88: the opt-in Social uninstall ("Delete Social data", last host) also deletes the two WPBot Core options that exist only for Social: `qcld_wpbot_core_social_apps` (the owner's app IDs and encrypted secrets) and `qcld_wpbot_core_connection_status`. No other Core data is touched (`qcld_wpbot_social_core_owned_options()`).
### Changed
- Loader version 0.2.2, so WPBot Pro Master's copy is never older than WPBot Free's by version either.

## 0.2.1 (WPBot Free, unreleased; 0.2.0 was the first test build)
### Added
- Run mode: optional host interface `Host\Mode_Host::get_social_mode()` (`free` | `master` | `off`) and `Module::mode()`; `Module::MODE_FREE`, `MODE_MASTER`, `MODE_OFF`. WPBot Free reports `free`, so the core features run there. Hosts without the interface keep the Master-only rule (`get_license() === 'master'`); `Host_Adapter` is unchanged. `qcld_wpbot_social/started` now also fires in `free` mode: Master-only listeners check `Module::mode()`.
- Social's own Telegram bot: `Connector::save_telegram_bot()`, `forget_telegram_bot()`, `telegram_bot_view()` (mask only), REST `GET|POST|DELETE wpbot-social/v1/telegram/bot` (`wpbot_social_settings`). Stored in `Token_Vault` (Secret_Box, autoload off); checked with `getMe` only. `telegram_token()` uses it before the ChatBot add-on's token. "Disconnect all" removes it.
- Overview tiles on the Posts screen (`Posts\Overview`): published in the last 30 days, scheduled, waiting for you, need attention (failed, check, missed, and targets still being checked: never shown as scheduled), and published per network; from Social's own tables, no network calls; hidden for people limited to some brands.
- Uninstall host list: `Module::remember_host()` adds each host that starts Social to `qcld_wpbot_social_hosts`; `qcld_wpbot_social_uninstall_for_host()` removes the host and deletes data (and schedules) only when no host is left and the owner opted in. Sites without a list behave as before.
### Changed
- A schedule that isn't empty and isn't `YYYY-MM-DD HH:MM` (seconds allowed) is refused with a problem message instead of meaning "now" (`Composer::schedule_valid()`).
- Targets on networks that aren't registered on the site (a Master network after a move to WPBot Free) are not picked by the scheduler (`Target_Store::due_ids( $now, $limit, $networks )`): they stay scheduled, then Missed (#58) or resume when the network is back. A forced publish of such a target fails with `network_unavailable`, not "reconnect".
- Replacing or removing Social's own Telegram bot marks the channels of another bot (or of the removed bot) "needs reconnecting" (their targets wait); the REST answer has `affected` and the view `channels`; the screen asks before removing.
- Core screens name Master features (and the Master Analytics screen) only in `master` mode; in Free the tracking-tag text points to the owner's own analytics tool.
- Loader version 0.2.1.
## Unreleased (Master build)
### Added
- SM0: newest-copy loader with a WPBot Core feature check; `Network` contract with `reconcile()`; Facebook Page and Instagram (single image) adapters; Graph client pinned to v26.0 with header tokens and `appsecret_proof`; error classification (definite vs ambiguous); replay/live transports with recorded fixtures citing their sources; encrypted `Token_Vault`; `Redact`; CI (PHPCS + PHPUnit on PHP 7.4 and 8.4).
- SM1: host adapter and Master gate, storage and migrations, Accounts, Connect Facebook & Instagram, Choose Pages, Telegram and Google Business Profile, Settings › Advanced, uninstall.
- SM2: scheduler with two-phase publishing and reconcile, Create post, Posts, Calendar items, tracked links, missed-post hold, Settings › Publishing and Links.
- SM3: writing engine, Write with AI, Post yourself (WhatsApp), AI Writer bridge, auto-share, Automator trigger and action, images.
- SM4 hooks for the Master module: `network_labels`, response headers, `check_notes`, `formats`, `target_problem`, `max_images`, `video_networks`, `queue_time`, `post_saved`, Create post options, `create_tabs`, `user_brands` (`Support\Brand_Access`), `save_intercept`; Instagram carousels, Reels and Stories; Facebook multi-photo; video in Create post.
- `Posts\Composer::save_as_user()`: one save gate (approval, brand membership, Publish) for REST and bulk import; `Post_Store::query( brands )` filters the Posts screen, Calendar and REST list by brand.
- E2E helper mu-plugin routes for Master replay, writer login, Inbox and Analytics seams.

