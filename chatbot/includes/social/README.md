# WPBot Social (core module)

Create on-brand posts for each social network from one idea, publish or schedule them from WordPress, and see which posts brought visitors, leads and sales. Part of the WPBot Growth Suite, bundled in **WPBot Pro (Master)** at `includes/social/`. The Master-only features live in https://github.com/qcld/wpbot-social-pro.

Plan, brief and screens: https://github.com/qcld/botmaster/tree/feature/social/docs/social

## Status
SM0–SM6 built (Master edition): accounts and connections, Create post and Write with AI, scheduling with two-phase publishing, Posts, Calendar, tracked links, Post yourself, auto-share and the AI Writer bridge, plus the hooks the Master module uses. The full contract, hooks, REST routes and data are in https://github.com/qcld/botmaster/blob/feature/social/docs/social/integration-readme.md; setup in `configuration.md` next to it.

## Architecture (foundations)
| Piece | What it does |
| --- | --- |
| `social.php`, `loader.php` | Newest-copy loader. Boots on `plugins_loaded` (priority 6) only when WPBot Core has the features Social needs (feature check, not only the version), else an admin notice. Fires `qcld_wpbot_social/loaded`. |
| `src/Networks/Network` | Adapter contract: `id()`, `label()`, `limits()`, `validate()`, `publish()`, `reconcile()`. |
| `Publish_Result` | `done`, `pending` (resume with state), `failed` (with `retryable`, `pause_account`), `unknown` (ambiguous: reconcile first, never retry blindly). |
| `Reconcile_Result` | `found`, `not_found`, `cannot_check` (ask the owner to check and confirm). |
| `Networks\Facebook` | Page text/link/photo posts; reconciles by matching text on the Page feed. |
| `Networks\Instagram` | Container → status → publish, resumable; reconciles from the saved container status. |
| `Networks\Meta_Graph` | Graph client pinned to **v26.0**; token in the `Authorization` header; `appsecret_proof`; error classification (190 → reconnect; 4/17/32/613 → retry later; 100 → refused; timeout/5xx on create → unknown). |
| `Http\Wp_Transport` / `Http\Replay_Transport` | Live calls through the WordPress HTTP API, or recorded responses (`tests/phpunit/fixtures/`). |
| `Accounts\Token_Vault` | Tokens encrypted with WPBot Core `Secret_Box`, one option per account, autoload off, masked to the last 4. |
| `Support\Redact` | Strips tokens from every log line, error and notice. |

## Hooks
| Hook | Type | Since |
| --- | --- | --- |
| `qcld_wpbot_social/loaded` | action | 0.1.0 |
| `qcld_wpbot_social/networks` (`Network[]`) | filter | 0.1.0 |
| `qcld_wpbot_social/started` (fires in `free` and `master` mode; check `Module::mode()`) | action | 0.2.0 |

The full list (about 40 hooks used by the Master module: `save_intercept`, `user_brands`, `post_saved`, `published`, `formats`, `queue_time` and more) is in the integration README linked above.

## Development
```bash
composer install
composer run lint
composer run test
```
CI runs PHPCS and PHPUnit on PHP 7.4 and 8.4 for PRs into `dev2`.

## Run mode (0.2.0)
A host may implement `QuantumCloud\Social\Host\Mode_Host` and return `free`, `master` or `off` from `get_social_mode()`. WPBot Free returns `free` (core features, no Master module). Hosts that don't implement it keep the original rule: Social runs only when `get_license()` is `master`. `Module::instance()->mode()` gives the mode; the Master module starts only on `master`.
