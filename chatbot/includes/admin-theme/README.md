# QuantumCloud Admin Theme

A stand-alone module that gives the WordPress admin a warm, minimal look in **Light** and **Dark**, and a full-screen **GRID** look. Each user chooses their own look, and where it applies:

| Look | What it does |
| --- | --- |
| Light | Warm off-white pages, white cards, clay accent, serif headings |
| Dark | Warm charcoal pages and cards, same accent |
| the GRID | Deep-space gradient with a barely visible 3D floor and ceiling receding to a faint horizon, four soft light traces gliding along its lines and a very slow drift (one canvas that fades in, traces locked to the grid lines with gently changing speeds, paused in hidden tabs; owner, 22 November 2026; the old flat grid and light bar are removed), cyan accents, the admin menu as a floating panel, bundled fonts (Orbitron, Exo 2, Share Tech Mono). Drawn on the dark tokens: `data-qa-theme="dark"` plus `html.qa-scifi`. Like Light and Dark, it restyles WordPress's toolbar and admin menu (the floating menu panel) only with "Whole admin panel". For users who ask for reduced motion the space is one still frame. |
| Classic WordPress | WordPress as it is (default) |

| Applies to | |
| --- | --- |
| *Plugin* pages only | The admin pages of the plugins that bundle the module (default) |
| Entire dashboard | Every admin page, including WordPress's own and other plugins' pages. Double opt-in, see below |

The block editor, Customizer and Site Editor are never restyled.

## What users get

- **Toolbar toggle** on every admin page, next to "Howdy": one click switches Light and Dark. Beside it, a **the GRID** button: one click turns GRID on, a second click goes back to the last other look. Hovering opens a menu with three quick options, **_Plugin_ dashboard only**, **Whole admin panel…** (asks first, see below) and **Classic (theme off)**, then the looks (Light, Dark, GRID). Turning the theme back on from Classic brings back the user's last look.
- **Header switch** that a plugin can print in its own page header (owner, 21 November 2026): sun (Light), moon (Dark), a glowing disc (GRID), a divider, then **Default** (an arrow turning back). Default picks the host's default look (filter `qcld_admin_theme_default_mode`; WPBot: Light) on the plugin's pages only, so WordPress's own pages keep their original look. Default shows as selected while that is the setting. "Match device" is retired: a stored `auto` reads as the default look.
- **Profile > Personal Options > Admin theme**: the same choices as a form.

### Entire dashboard: double opt-in, alerts and immediate revert

Restyling pages the plugin does not own needs the user's explicit consent, twice:

1. **Confirm.** Choosing "Entire dashboard" (toolbar menu, toolbar toggle on a page outside the plugin, Profile) opens a dialog that explains what changes. Cancel is focused by default. The server refuses the switch without this confirmation (`confirm_required`), so no script or link can skip it. Without JavaScript, a confirmation page is shown instead; on Profile, a confirmation box must be ticked.
2. **Trial, then keep.** Once confirmed, the theme runs as a **trial**. A bar on every admin page says so and offers **Keep it** or **Switch back**. It stays a trial until the user keeps it.

Reverting is always one click:

- Right after confirming, a toast offers **Undo** (20 seconds, paused while hovered or focused).
- **Switch back** in the trial bar, at any time.
- The toolbar menu, at any time.

Undo and Switch back restore exactly the look and scope the user had before.

## Add it to a plugin

1. Copy this folder into the plugin, normally at `includes/admin-theme/`.
2. In the plugin's main file (when the file loads, not later in a hook):

```php
require_once __DIR__ . '/includes/admin-theme/admin-theme.php';

add_filter( 'qcld_admin_theme_plugins', function ( $plugins ) {
	$plugins['my-plugin'] = array(
		'name'  => 'My Plugin',                 // Shown as "My Plugin pages only".
		'dirs'  => array( __DIR__ ),            // Admin pages whose callback lives in this folder.
		'menus' => array( 'my-plugin' ),        // Every page under these top-level menu slugs.
		// Optional:
		'pages'      => array( 'my-other-page' ),                    // More ?page= slugs.
		'post_types' => array( 'my_cpt' ),                           // List/edit/taxonomy screens.
		'css'        => plugins_url( 'css/admin-theme.css', __FILE__ ), // Theme styles for your own markup.
		'skip'       => array( '#my-live-preview' ),                 // Left alone by the color mapping.
	);
	return $plugins;
} );
```

3. Optional: print the header switch in your page header:

```php
<?php do_action( 'qcld_admin_theme_switch' ); ?>
```

4. In the plugin's `uninstall.php`:

```php
if ( file_exists( __DIR__ . '/includes/admin-theme/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/admin-theme/uninstall-helper.php';
	qcld_admin_theme_uninstall( __DIR__ );
}
```

That is all. Several plugins can bundle the module on one site: each copy registers itself and only the newest boots, so users get one toggle and one setting across all of them. The user data (six user meta keys) is deleted only when the last plugin with a copy is uninstalled.

## How plugin pages are themed

WordPress's own chrome (toolbar, menu, buttons, fields, notices, tables, tabs) is styled by `assets/admin-theme.css` from theme tokens.

A plugin's own pages need no stylesheet: `assets/admin-theme.js` reads each element's real colors once and adds classes that point them at the tokens. White and pale tints become surfaces. A saturated purple brand color becomes the accent. Pale red, green, amber and blue become their soft tokens. Dark and grey text become the text tokens, and large headings get the serif. Content added later (dialogs, tables loaded by script) is mapped too. Reads all happen before writes, so the page is laid out once.

Hover and focus are mapped too: while a control (link, button, tab, list item, anything with a pointer cursor) is hovered or focused, its part of the page is mapped again with that state's colors, and once more when the state ends. Colors in any CSS syntax are read (`oklch()` from Tailwind 4 included). After mapping, a contrast guard checks every text against what is really behind it: below 3:1 it gets the main text, pill or on-accent token, whichever reads best.

When the mapping gets something wrong, fix it in the plugin's `css` file. The theme is on when `html.qa-theme` is present; `html[data-qa-theme="light"|"dark"]` gives the look. Use the tokens (`var(--qa-surface)`, `var(--qa-text-1)`, `var(--qa-accent)`…; the full list is at the top of `assets/admin-theme.css`). To keep an element as it is, add `data-qa-skip` to it or list it in `skip`.

Set the plugin's body font in its `css` file: `html.qa-theme { --qa-font: "My Font", sans-serif; }`.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `qcld_admin_theme_plugins` | filter | Register a plugin (above). |
| `qcld_admin_theme_switch` | action | Print the header switch. |
| `qcld_admin_theme_default_mode` | filter | Look for users who never chose one (`classic`). |
| `qcld_admin_theme_capability` | filter | Capability needed to use the theme (`read`). |
| `qcld_admin_theme_show_controls` | filter | Whether the toggle and assets load on the current admin page. |
| `qcld_admin_theme_is_plugin_screen` | filter | Override whether the current page is a plugin page. |
| `qcld_admin_theme_font_url` | filter | Heading font stylesheet (`''` for the system serif). |
| `qcld_admin_theme_changed` | action | A user changed their theme (`$user_id`, `$new`, `$old`). |
| `qcld_admin_theme_change` | JS event | On `document`, after every change (`detail.mode`, `scope`, `all`, `look`). |
| `window.qcldAdminTheme` | JS API | `get()`, `set( mode, scope )`, `keep()`, `revert()`, `refresh( element )`. |

## Data

User meta, per user:

| Key | Values |
| --- | --- |
| `qcld_admin_theme` | `light`, `dark`, `scifi`, `classic` (a stored `auto`, from the retired Match device, reads as the default look) |
| `qcld_admin_theme_scope` | `plugin`, `all` |
| `qcld_admin_theme_all` | `trial`, `kept` (only while scope is `all`) |
| `qcld_admin_theme_prev` | The look and scope from before "Entire dashboard" (for Switch back) |
| `qcld_admin_theme_last` | The last look other than Classic |
| `qcld_admin_theme_plain` | The last look other than Classic and GRID (where the toolbar's GRID button goes back to) |

Saving goes through `admin-ajax.php?action=qcld_admin_theme_save` (nonce `qcld_admin_theme`, capability above). Users can only change their own choice, and only to the listed values.

## Translations

Strings use the text domain `qcld-admin-theme`. Translations load from `languages/qcld-admin-theme-{locale}.mo` inside this folder. A plugin on WordPress.org that wants its own translations to cover the module can retarget the domain when it builds its zip (for example with botmaster's `build-scripts/i18n-domain.js`).

## Privacy

The heading font (Source Serif 4) loads from Google Fonts by default, which sends the admin's IP address to Google. Bundle the font and point `qcld_admin_theme_font_url` at it, or return `''` to use the system serif. The GRID fonts are bundled in `assets/fonts` (SIL Open Font License 1.1, licences beside them) and load only while GRID is on.

## Files

```
admin-theme.php           Bootstrap: registers this copy; the newest copy boots on plugins_loaded.
src/class-qcld-admin-theme.php
assets/admin-theme.css    Tokens, WordPress chrome, toggle, dialog, toast, trial bar, mapping classes, GRID.
assets/fonts/             GRID fonts (woff2) and their OFL licences.
assets/admin-theme.js     Color mapping, toggle, opt-in dialog, toast, trial bar, saving.
uninstall-helper.php      qcld_admin_theme_uninstall( $host_dir ).
```
