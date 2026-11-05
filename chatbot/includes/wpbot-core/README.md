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
- REST namespace: `wpbot-core/v1` (`/keys/{module}`, `/keys/{module}/import`, `/keys/{module}/test`, `/health/{module}`, `/jobs`, `/jobs/{id}`, `/jobs/{id}/tick|stop|resume|cancel`, `/usage/{module}`).
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
| `qcld_wpbot_core/job_handlers` | filter | Step kind => callable |
| `qcld_wpbot_core/job_done` | action | A job finished |
| `qcld_wpbot_core/key_status_changed` | action | A key started or stopped failing |

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
