# WPBot AI Writer (core module)

Core module of WPBot AI Writer. Everything in this module ships unchanged in WPBot Free and is bundled into WPBot Pro (qcld/botmaster) by a sync script. Pro-only features live in qcld/wpbot-ai-writer-pro and plug in through hooks.

- Product: WPBot by QuantumCloud
- License: GPL-2.0-or-later
- Requires: WordPress 6.2+, PHP 7.4+
- Trunk: `dev` · Released versions: `main`

Do not edit synced copies of this module inside host plugins; change it here and re-sync.

## External services
Host plugins must copy this list into their `readme.txt`. Nothing is sent until an editor starts the action.

| Service | When | Data sent | Terms |
| --- | --- | --- | --- |
| The AI provider the admin set up (OpenAI, Anthropic, Google Gemini, OpenRouter, xAI, Mistral or a custom URL) | Writing, questions, outline, voice, AI images | The brief, chosen source text, site voice, prompts | The provider's own terms and privacy policy |
| Openverse (`api.openverse.org`) | Filling images when "Images" is set to Automatic | Search words taken from section headings | https://docs.openverse.org/terms_of_service.html |
| Pexels (`api.pexels.com`) | Filling images, only after the admin adds a Pexels key | Search words, the key | https://www.pexels.com/terms-of-service/ |
| Pixabay (`pixabay.com/api`) | Filling images, only after the admin adds a Pixabay key | Search words, the key | https://pixabay.com/service/terms/ |

Chosen images are downloaded to the Media Library with their licence and credit. Uploaded source documents never leave the site: only their extracted text is kept and the file is deleted.

## Architecture

- `ai-writer.php` registers this copy with the newest-copy loader; the newest copy boots on `plugins_loaded` (priority 5) when WPBot Core 0.1.0+ is loaded, then fires `qcld_wpbot_writer/loaded`.
- Hosts register a `QuantumCloud\AiWriter\Host\Host_Adapter` with `QuantumCloud\AiWriter\Module::instance()->register_host()` inside that action.
- Module id `writer`: it is the permissions section (`wpbot_writer_view|use|publish|settings`) and the key set in WPBot Core.
- The core never checks for Pro. Extensions register through the hooks below; inactive "Pro" previews come from the host adapter (`get_pro_previews()`).
- REST namespace `wpbot-writer/v1`. Admin assets load only on the AI Writer screen.
- Uninstall: hosts call `qcld_wpbot_writer_uninstall_for_host( $host_id )` from `uninstall-helper.php`.

### Public API (contract for other modules)

| Name | Type | Purpose |
| --- | --- | --- |
| `qcld_wpbot_writer_get_package( int $post_id )` | function | Content package of a finished piece, or null |
| `qcld_wpbot_writer_open_brief_url( array $brief )` | function | Admin URL that opens AI Writer pre-filled (brief stored 1 hour under a token) |
| `qcld_wpbot_writer_get_dashboard_data( array $args = array() )` | function | Numbers for the Growth Dashboard |
| `qcld_wpbot_writer/content_finalized` | action | `( $post_id, $package )` |
| `qcld_wpbot_writer/content_published` | action | `( $post_id, $package )` |
| `qcld_wpbot_writer/channel_versions_ready` | action | `( $post_id, $versions )` |
| `qcld_wpbot_writer/channels` | filter | Networks, limits and rules |
| `qcld_wpbot_writer/idea_sources` | filter | Topic Finder sources |
| `qcld_wpbot_writer/utm_args` | filter | UTM parameters on tracked links |

### Extension hooks

| Name | Type | Purpose |
| --- | --- | --- |
| `qcld_wpbot_writer/loaded` | action | Core booted (Pro boots on it, priority 20) |
| `qcld_wpbot_writer/register_features` | action | `( Feature_Registry $registry )` |
| `qcld_wpbot_writer/schema_tables` | filter | Add CREATE TABLE statements |
| `qcld_wpbot_writer/job_steps` | filter | Step kind => handler |
| `qcld_wpbot_writer/primary_host` | filter | Choose the primary host |
| `qcld_wpbot_writer/delete_all` | action | Uninstall cleanup |
| JS `qcld_wpbot_writer.admin.screens` | `wp.hooks` filter | Add or change admin screens |

## Development

```bash
composer install
npm install
composer run lint   # PHPCS: WordPress Coding Standards + PHPCompatibility (PHP 7.4+)
composer run test   # PHPUnit unit tests
npm run build       # @wordpress/scripts build into assets/build/
npm run lint:js
```

Strings use the placeholder text domain `qcwr-textdomain`; the host plugin's sync script replaces it (`wpchatbot` in WPBot Pro).
