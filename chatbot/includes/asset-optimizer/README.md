# WPBot Asset Cache — core module

The **Performance** tab under ChatBot Settings. When enabled, WPBot minifies
(and optionally combines) the chatbot's own JS and CSS at runtime, stores the
results in `uploads/wpbot-cache/<package>/` and serves them instead of the
originals. When it is off, or anything goes wrong, the original files are
served and the chatbot behaves exactly as before.

This repository is the **core module**. It ships unchanged in WPBot Free and
WPBot Pro, synced into each host at `includes/asset-optimizer/`. Pro-only
features live in [wpbot-asset-optimizer-pro](https://github.com/qcld/wpbot-asset-optimizer-pro)
and plug in through the hooks below.

- Specification: [docs/feature-plan.md](docs/feature-plan.md)
- Build brief and progress: [docs/build-brief.md](docs/build-brief.md), [docs/progress.md](docs/progress.md)

## How a host bundles it

```php
// In the host's main plugin file (after the Pro module, if any):
require_once __DIR__ . '/includes/asset-optimizer/asset-optimizer.php';

add_action( 'qcld_wpbot_ao_loaded', function () {
	\QuantumCloud\AssetOptimizer\Module::instance()->register_host( new My_Host_Adapter() );
} );
```

The adapter extends `QuantumCloud\AssetOptimizer\Host\Abstract_Host_Adapter`
and supplies the WPBot handles, the capability, the cache subfolder, where the
tab appears and (from C4) the per-visitor data for cached pages. The host's
settings template prints the tab through two actions it calls itself (WPBot
Pro: `qcld_wpbot_settings_tabs_before_custom_css` and
`qcld_wpbot_settings_sections_before_custom_css`). Uninstall:

```php
require_once __DIR__ . '/includes/asset-optimizer/uninstall-helper.php';
qcld_wpbot_ao_uninstall_for_host( 'pro' );
```

## Public API and hooks

| Hook or function | Kind | Purpose |
| --- | --- | --- |
| `wpbot_asset_cache()` | Function | The module: `is_active()`, `clear()`, `rebuild()`, `get_status()` |
| `wpbot_asset_cache_handles` | Filter | Add or remove handles the cache may process (`['script' => [], 'style' => []]`) |
| `wpbot_asset_cache_skip` | Filter | `( $skip, $handle, $type )`: leave one file untouched |
| `wpbot_asset_cache_dir` | Filter | `( ['path' => …, 'url' => …], $package )`: move the cache folder |
| `wpbot_asset_cache_minified` | Filter | `( $code, $handle, $type )`: adjust output before it is written |
| `wpbot_asset_cache_cleared` | Action | `( $deleted )`: fired after the cache is cleared |
| `wpbot_asset_cache_kb_url` | Filter | Knowledge-base link in the help panel |
| `WPBOT_ASSET_CACHE_DISABLE` | Constant | Forces the feature off |
| `qcld_wpbot_ao_loaded` | Action | The newest bundled copy booted; hosts register here (10), the Pro module adds its filters first (5) |
| `qcld_wpbot_ao_ready` | Action | `( $module )`: the module has a host and is set up for this request |
| `qcld_wpbot_ao_default_settings` / `qcld_wpbot_ao_sanitize_settings` | Filters | Extra settings keys (Pro) |
| `qcld_wpbot_ao_render_settings` / `qcld_wpbot_ao_render_help` | Actions | Extra fields and help on the Performance tab |
| `qcld_wpbot_ao_detected_plugins` | Filter | Detected caching plugins (slug => name) |
| `qcld_wpbot_ao_delete_all` | Action | Uninstall with no other package left: remove extension data |

## Data

| Name | Type | Holds |
| --- | --- | --- |
| `wpbot_asset_cache_settings` | Option, autoloaded | Settings |
| `wpbot_asset_cache_state` | Option, autoloaded | Module version, file map, repaired and skipped files, last error, update invitation |
| `wpbot_ao_lock_<key>` | Transient, 60 s | Build lock |
| `wpbot_ao_cleanup` | Daily cron event | Removes superseded cached files older than 30 days |
| `uploads/wpbot-cache/<package>/` | Files | Cached copies, `index.php`, `.htaccess` |

## Development

```bash
composer install --no-plugins      # dev tools only
composer lint && composer phpcs    # PHP lint, WordPress Coding Standards + PHPCompatibility 7.4
composer test                      # PHPUnit unit suite (no WordPress needed)
QCLD_AO_WP_LOAD=/path/to/wp-load.php vendor/bin/phpunit --testsuite integration
```

PHP 7.4 minimum (7.4 syntax only). Text domain placeholder `qcao-textdomain`,
swapped for the host's at sync. Original code only.

## Licence

GPL-2.0-or-later.

For developers of other WPBot modules and hosts: [docs/integration-readme.md](docs/integration-readme.md) (public API, hooks, registering files, caching-plugin settings).
