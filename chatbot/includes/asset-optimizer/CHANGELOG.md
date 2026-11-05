# Changelog

## 0.7.5 — tab section link

- The Performance tab item names its section (`data-section="#section-flip-performance"`), which the newer WPBot Free settings script uses to switch tabs (it falls back to the tab's position otherwise). WPBot Pro ignores the attribute.

## 0.7.4 — WordPress 5.7 minimum

- The module loads only on WordPress 5.7 or newer (it prints its scripts with `wp_print_script_tag()` / `wp_print_inline_script_tag()`); on older WordPress the host runs as before, without the Performance tab. WPBot Free and Pro still declare "Requires at least: 4.6".

## 0.7.3 — WPBot Free host

- Host adapters can name the Performance tab's icon (`get_tab_icon()`, default Font Awesome `fa fa-tachometer`), so it matches hosts whose settings screen uses another icon font (WPBot Free: dashicons).
- Fix: the `&tab=performance` deep link could lose a race with the host's own tab script (jQuery 3 runs ready callbacks after the load event when the page loads fast), so the first tab stayed open. It now waits for the ready queue.
- The status panel shows "already minified" instead of a negative saving for files that were minified already (copied as they are, plus the guard's one-line marker).
- Tests: `tests/e2e/flows-generic.js` (chatbot flows on any host), `c1-admin.js` takes `SETTINGS_PAGE` / `CUSTOM_TAB` for WPBot Free.

## 0.7.2 — fixes from the final review

- Fix: when a stylesheet inside a combined bundle was repaired (its cached file imports the original), rebuilding the bundle put that `@import` in the middle, where browsers ignore it, so the member's styles were lost on pages using the bundle. The bundle is now removed instead, so those pages fall back to the original files; new pages leave the repaired file out of bundles.
- Fix: a WPBot stylesheet with its own RTL file keeps its original on RTL sites (WordPress derives the RTL file's URL from the original).
- `wpbot_asset_cache_handle_allowed` gets a fourth argument, the source path relative to the host folder, when known (builds after the response, reports, Rebuild, bundles), so a rule does not depend on scripts being registered.
- New filter `wpbot_asset_cache_client_ip` for the rate limits, for sites behind a proxy that does not restore the visitor's address.
- The cache key includes the URL base that stylesheets' `url()`s are rewritten against, so moving uploads to another host rebuilds the copies.
- Uninstall with "delete data" removes every option under the `wpbot_asset_cache_` prefix.

## 0.7.1 — fix: state writes from parallel requests

- Fix: a build after the response, or a failure report, could save an old copy of the runtime state and drop file entries that other requests had added meanwhile; a combined bundle with such a member could not heal in place. `State::refresh()` now also drops WordPress's in-request option cache (sites without a persistent object cache), and `State::set()` re-reads before changing its one key. New integration test `StateIntegrationTest`.

## 0.7.0 — C7 hook for late-loaded scripts (additive)

- `ao-fresh.js` keeps the fetched values and exposes `wpbotAo.applyFresh()`, so a loader that adds WPBot's scripts after the page (the Pro engine loader) can apply fresh tokens and visitor details to objects those scripts define.

## 0.6.0 — C6 hooks for add-on assets (additive)

- `wpbot_asset_cache_handle_allowed` filter: allow handles by rule (for example ones an add-on builds at run time); files must still sit inside the host folder.
- `wpbot_asset_cache_fresh_data` and `wpbot_asset_cache_anonymous_data` filters: extensions add their own tokens and visitor fields for cached pages.
- Pages printed for logged-in users also get logged-out visitor values in inline "before" scripts of the form `window.name = {…};`.

## 0.5.0 — C5 Optional combine

- "Combine WPBot files" (off by default): neighbouring WPBot scripts of one print pass with the same loading strategy (WordPress's own decision per file), and neighbouring WPBot styles with the same media, become one bundle each. Files with "after" inline code, `@import`, conditional, RTL or translation data stay separate; a script with a "before" inline script can only start a bundle.
- Member handles become empty aliases of the bundle (marked done), so add-ons that depend on them still load after them. Localised data and "before" scripts print before the bundle in order.
- Bundles are built after the response from the members' cached copies; until a bundle exists the members print separately. The guard falls back to each member's original when a bundle fails; repairing a member rebuilds every bundle that contains it, in place. Bundles in use are kept by the daily cleanup.

## 0.4.0 — C4 Cached-page safety and coexistence

- Fresh-data endpoint (`admin-ajax.php?action=wpbot_ao_fresh`, never cached, rate-limited per IP hash): the visitor's own security tokens and details, and the live opening-hours / mobile / logged-in answers, from the host adapter.
- `ao-fresh.js`: pages older than a minute with live show/hide rules, pages printed for logged-in users and pages older than six hours refresh on load; other cached pages on first interaction. Updates WPBot's objects and rewrites old tokens in outgoing jQuery requests.
- Live show/hide rules: hosts print their answer for all eight opening-hours / mobile / logged-in cases (`qcld_wpbot_footer_rules`); the chatbot is held hidden (`#wpbot-ao-rules`) until the live case allows it.
- Pages printed for logged-in users carry the logged-out visitor fields; the real ones come from the endpoint.
- Caching and optimisation plugins (`src/Compat/`): WP Rocket, LiteSpeed Cache, Autoptimize, W3 Total Cache, SiteGround Speed Optimizer exclude WPBot's files (and keep jQuery and WPBot's inline data untouched) while WPBot's cache is on; opt-in purge of WP Rocket, LiteSpeed, W3 Total Cache, SiteGround, WP Super Cache and WP Fastest Cache page caches after Clear. Detected plugins and whether WPBot excluded itself are listed in the help panel.
- The guard and `ao-fresh.js` are always served from the module folder, so the safety net works right after Clear. The guard no longer reports runtime errors (the cached copy is the same code).

## 0.3.0 — C3 JS minification, defer, safety net

- Original safe JS minifier: a streaming tokenizer (strings, template literals with nested `${}`, regex versus division, comments) that copies every token as written and keeps a line break wherever automatic semicolon insertion could depend on it. Already-minified files pass through unchanged. Before writing: same number of strings, templates and regexes, balanced brackets, smaller output.
- Script URL swap; WPBot scripts marked `defer` through the WordPress 6.3 loading-strategy API (WordPress keeps normal loading where needed).
- Every cached script ends with a marker. A tiny error listener in the head and the guard at the end of the page load the original when a cached file did not load or did not parse, and report it.
- Repair in place: `admin-ajax.php?action=wpbot_ao_report` accepts only a registered handle whose cached file exists, rewrites that file with the original at the same URL (stylesheets: an `@import` of the original), serves the original for that file version from then on, and is rate-limited per IP hash and per site. Listed under "Repaired files".
- The guard itself is served minified from the cache.

## 0.2.0 — C2 CSS minification and non-blocking styles

- Original safe CSS minifier: comments and whitespace only; strings, url() and calc()/min()/max()/clamp() spacing kept; relative url() and @import rewritten to protocol-relative URLs of the original folder; balanced-structure check before writing.
- Style URL swap to cached copies; builds run after the response (fastcgi_finish_request() or a one-off cron event) under a 60-second lock, at most six per request; failed checks keep the original for that file version.
- Non-blocking chatbot styles: on pages with the floating chatbot, WPBot's stylesheets print with the footer in the same order; a one-line rule keeps the chatbot hidden until a rule after the last WPBot stylesheet reveals it (no script, CSP-safe). Pages with the chatbot or WPBot markup in the content keep normal loading.
- Font Awesome and animate.css load on first interaction or when the browser is idle (ao-guard.js); Google Fonts get display=swap.
- Rebuild now rebuilds every known file; nginx sites get the browser-caching snippet in the status panel.

## 0.1.0 — C1 Foundations

- Settings (`wpbot_asset_cache_settings`), state, handle registry with the "inside the plugin folder" rule and the "files to leave untouched" list.
- Cache folder `uploads/wpbot-cache/<package>/` with `index.php`, `.htaccess` (30-day browser caching), writability check and atomic writes.
- Performance tab (settings, status panel, help panel, Clear and Rebuild), unwritable-folder notice and update invitation.
- Kill switches: the setting, `WPBOT_ASSET_CACHE_DISABLE`, `SCRIPT_DEBUG`, `?wpbot_ao=off` for administrators.
- On for new installs, off with an invitation for sites updating from an older host version.
- Daily cleanup of superseded files after 30 days; uninstall helper.
- No front-end change yet.
