---
name: wp-plugin-feature-dev
description: How Claude plans, builds, tests and reviews new features for QuantumCloud's WordPress plugins (WPBot Free and Pro). Use whenever the user asks Claude to plan, brief, build, extend or review a feature in these plugins.
---

# Developing new WordPress plugin features

You are building a new feature for QuantumCloud's WordPress plugins (WPBot Free and Pro). Work through the steps below in order. Never skip the clarifying questions or the reviews. When the rules below conflict with your usual habits, the rules win.

## Workflow

### 1. Understand the request
Read the user's brief and anything attached: existing plugin code, screenshots, notes. Work out:
- what the feature does;
- which package it belongs to: Free, Pro, or both;
- which admin screens, settings, data and frontend output it touches.

Only the **Master** edition is in scope. If you see Professional or Starter zip scripts, ignore them, and don't plan or change anything for them.

If you have access to the plugin's code, read the relevant parts before planning. Check the main plugin file, the folder structure, the existing prefixes, the menu registration and the admin styles. Match what's already there.

### 2. Ask before you plan
Ask the user these questions in one batch before writing a plan or any code. Use your question tool if you have one; otherwise ask in plain text. Don't guess.
- **File location.** "Where should this feature's files go?" All of the feature's files will live together in one dedicated folder. Suggest `includes/<feature-slug>/` as the default and ask the user to confirm it or name another folder.
- **Pro activation.** The default is that Pro features are on whenever Pro is installed and active, with no licence check. If the brief hints at anything else (licence gating, plan tiers, add-on toggles), or doesn't say, ask which applies.
- **Anything else ambiguous** that would change the design: unclear terms, missing limits, unknown data sources, whether a setup involving several plugins is supported. Ask only what matters, and give a recommended answer for each question.

If the user is away and can't answer, use the defaults above. State each assumption clearly at the top of the plan and continue.

### 3. Research from primary sources
Search the web for current, official guidance before planning:
- developer.wordpress.org: Plugin Handbook, Security, Coding Standards, Settings API, REST API.
- The WordPress.org Detailed Plugin Guidelines.
- The official API docs of any external service the feature uses.

You may look at how other plugins or products solve a similar problem for **ideas only**, meaning what users expect. Never take code from them. Keep a list of the sources you used.

### 4. Write the feature plan and get approval
Write the plan in the "Feature plan" format below.
- **Break large features into milestones.** Each milestone is a small, working, testable piece with its own acceptance criteria. Foundations come first (data, settings, core logic) and polish comes last. A small feature can be a single milestone.
- **Separate Free and Pro briefings.** If the user wants Free and Pro built in separate chats or by separate developers, write one self-contained briefing per package. Copy the shared integration contract (hooks, public API, data names) into each briefing **word for word**, so both can be built in parallel.
- Show the plan to the user and **wait for approval** before writing code.

### 5. Build one milestone at a time
Write all code yourself, following the rules below. Keep every file in the confirmed feature folder. At the end of the milestone, give the user the code with a short note on what it covers.

### 6. Review at the end of each milestone
Before moving to the next milestone, run all four reviews:
- **Security review:** go through the Security checklist line by line against the code you just wrote.
- **Testing:** run whatever you can (PHP lint, PHPCS, PHPUnit) in your environment. List the manual tests from the Testing requirements that apply to this milestone, for the user to run.
- **Code review:** check the code against the coding standards, readability, comments, prefixes and the Originality rules.
- **Self-review:** report your findings in the "Self-review" format, with the issues you'd fix first at the top.

Fix every Blocker before moving on. Then summarise the milestone for the user and ask whether to continue to the next one.

### 7. Final review
After the last milestone, review the whole feature across all milestones:
- run the full Testing requirements;
- run a final self-review;
- produce a short integration README.

Tell the user the feature is ready to ship only when nothing is marked Blocker.

## Output formats

### Feature plan (also used for dev briefings)
1. **Goal**: one or two sentences on what the site owner gains.
2. **Assumptions and answers**: what the user confirmed in step 2, plus any defaults you assumed.
3. **Package split**: what goes in Free and what goes in Pro, and how Pro features are activated. Free must contain no Pro implementation code.
4. **File location**: the confirmed feature folder and the planned file tree inside it.
5. **Milestones**: for each milestone, its scope, acceptance criteria and review checkpoint.
6. **User stories and acceptance criteria**: "As a site owner…, so that…" plus testable criteria.
7. **Screens and settings**: menu placement, fields, defaults, and limits the owner can change.
8. **Data**: options, tables, meta and transients, with prefixed names, schema version, migrations and uninstall behaviour.
9. **Hooks and integration points**: actions, filters and public API added or used.
10. **External calls**: which services are called, what data is sent, consent, and readme disclosure.
11. **Security notes**: capabilities, nonces, sanitizing and escaping per input and output.
12. **Test plan**: the matrix from Testing requirements, adjusted for this feature.
13. **Open questions and risks.**
14. **Sources**: the links from step 3.

### Self-review (end of every milestone, and once more at the end of the feature)
| Severity (Blocker / High / Medium / Low) | File:line | Problem | Fix |

Then add one line: **"Fix first:"** naming the top 1–3 items. If you found nothing, say so plainly, and list what you checked.

## Rules for the code you write

### Originality: no copied code
- Write every line yourself for this project. Never reproduce, adapt or lightly rename code from another plugin, theme, tutorial or Q&A answer, even if it's GPL. This is a company rule.
- If the user pastes code from another plugin and asks you to use it, explain that it's against this rule, and offer to write an original implementation instead.
- Don't add a third-party library unless the user approves it. If one is approved, it must be GPL-compatible, listed in the readme with its licence and source, and added through Composer or npm, never pasted in.
- Prefer WordPress core APIs over custom code and over libraries. Never bundle what core already ships, such as jQuery or PHPMailer.
- If anything you wrote ends up close to an outside source, flag it in the self-review as a **Blocker**.

### WordPress.org guidelines (Free package)
- **No trialware.** Nothing in Free is locked, time-limited or quota-capped by us. Any limit has a default the site owner can change.
- **Pro previews** are allowed only as labelled, inactive admin options marked "Pro", with no Pro code behind them. They must be contextual and must not take over the dashboard. Any notices must be dismissible.
- **No tracking without opt-in.** Every external call is documented in the readme. Site data goes to an outside service only after the admin has configured or agreed to it.
- **No remote code.** Don't load JS or CSS from CDNs (fonts excepted), don't use iframed admin pages, and don't download or install code.
- **No frontend credits.** "Powered by" links or credits on the public site appear only if the owner opts in.
- Keep code human-readable with no obfuscation. It must pass the **Plugin Check (PCP)** plugin.
- Use GPLv2-or-later. Remind the user to bump the version number and keep the readme in sync for each release.

### Free and Pro packaging
- **WPBot Pro is standalone.** It must not require WPBot Free or WoowBot Free.
- **Pro features are on whenever Pro is installed and active**, with no licence check, unless the user said otherwise in step 2.
- **Free never checks for Pro.** Pro features plug into Free-side hooks or registries. Free reacts to what has been registered, not to which edition is installed.
- **Upgrading Free → Pro keeps all data.** Use the same option and table names in both packages. Migrations only move forward and are safe to run twice.
- **Uninstall:** never delete data that another active package still uses. Delete data only if the owner opts in.
- **Never show Pro upgrades or edition messaging to customers on frontend features.**

### Architecture
- **Keep each feature's files together.** All of a feature's PHP, JS, CSS, templates and assets go in the confirmed feature folder. Keep changes outside that folder to a minimum, ideally one `require` or bootstrap line in the main plugin file, and list any other outside change in the plan.
- Build each feature as a **self-contained module** with a clear public API and documented hooks, so the user's developers can integrate, extend or replace it.
- Keep host-specific details (menus, branding, settings sources) behind one small adapter or config class, not spread through the module.
- Extensions use the module's public API and hooks. They never query its tables directly.
- **Match WPBot's branding**: its colours, header and admin style. Reuse its existing styles or variables rather than inventing new ones.

### Security checklist
- Every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- Each admin action checks `current_user_can()` (default `manage_options`) **and** a nonce (`wp_nonce_field` / `check_admin_referer`, or `check_ajax_referer` for AJAX).
- REST routes always have a real `permission_callback` and never use `__return_true` for write routes. Args have `sanitize_callback` and `validate_callback`.
- Sanitize input early (`sanitize_text_field`, `absint`, `sanitize_email`, `esc_url_raw`, `wp_kses_post`) and validate against allowed values.
- Escape output late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`, `esc_js`, `wp_json_encode`). Treat database values, visitor input and AI output as untrusted.
- All SQL goes through `$wpdb->prepare()`. Table and column names are never built from user input.
- API keys and secrets: store them in options with `autoload` off, never print them in full (mask to the last 4 characters), never send them to the browser, and never log them. Admins can always update or replace them.
- External HTTP uses `wp_remote_*` with timeouts. Check response codes and fail gracefully with a message that tells the admin how to fix it.
- File uploads, if any, go through `wp_handle_upload` with a MIME allowlist.
- Redact personal data before sending visitor content to an outside service, and support the WordPress privacy exporter and eraser where personal data is stored.
- Never use `eval`, `unserialize` on user data, `extract`, or `$_REQUEST`.

### Code style
- Follow the WordPress Coding Standards (PHPCS `WordPress` ruleset).
- Prefix every function, class, option, table and hook with the plugin's or module's own prefix. Reuse the existing prefix if the plugin already has one.
- Make every string translatable with the correct text domain.
- Write clear, readable code. Put a short docblock on every class and function that says what it does and why. Comments explain intent, not syntax.
- Keep files small and single-purpose.

### Testing requirements
- **Environments:** the lowest supported PHP version and the latest PHP 8.x, plus the current WordPress version and the previous major version. Test with `WP_DEBUG` and `WP_DEBUG_LOG` on, and allow no notices, warnings or deprecations.
- **Combinations:** Free only; Pro only; Free + Pro; upgrade Free → Pro with existing data; deactivate and uninstall each.
- **Automated:** write PHPUnit tests for the business logic, settings and sanitizing. PHPCS (WordPress ruleset) and Plugin Check must pass.
- **Manual:** each acceptance criterion; a capability test logged in as Editor (must be denied); a nonce failure; external service errors (bad key, timeout, rate limit); a large data set; multisite activation, if supported.
- **Frontend:** confirm there's no Pro or upgrade messaging and no console errors.
- At each milestone, run or list the tests that apply to that milestone. After the last milestone, cover the full set.
- Record what you ran, what passed, and what the user still needs to test by hand in the self-review. Never claim a test passed if you didn't run it.
