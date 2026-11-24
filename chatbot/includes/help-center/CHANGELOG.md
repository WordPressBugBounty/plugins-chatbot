# Changelog



## 0.19.1 (Docs server: rate limit behind proxies, gap reason, analytics)

- **Rate limit behind a proxy or CDN.** The docs server tells visitors apart by the address a proxy passes on (Cloudflare's `CF-Connecting-IP`, then `X-Real-IP`, then the last `X-Forwarded-For` entry). It reads that header automatically when the request comes from a private or local address, and for public addresses when the owner ticks "Visitors reach this site through Cloudflare or another proxy" in AI & GitHub (setting `docs_mcp_proxy`, off by default because anyone can send those headers). A site-wide ceiling of 3000 requests per 10 minutes holds whatever the addresses. New filters: `qcld_wpbot_help_center/docs_client_ip`, `docs_rate` and `docs_site_rate`.
- **Knowledge gaps.** Searches from AI assistants that find nothing are sent as `ai_search` (was `docs_search`). With the chatbot module 0.50.7 they show in Train as "AI assistant found nothing in your docs"; Help Center searches and "not helpful" votes keep their own reasons there too.
- **Analytics.** Help Center → Analytics has a "From AI assistants" section: searches, searches with no result and articles read through the docs server, with the most searched, no-result and most read lists. Stats schema 2 adds the table `{prefix}wpbot_help_ai_searches` and the column `ai_reads` on `{prefix}wpbot_help_daily`. New action `qcld_wpbot_help_center/docs_read`.
- Topic, section and knowledge base names reach assistants as plain text ("SEO & AI Search", not "SEO &amp; AI Search").

## 0.19.0 (HC3-B: public docs server for customers' AI assistants)

- New public, read-only MCP server at `POST /wp-json/wpbot-help/v1/docs` (Streamable HTTP, JSON responses, no key). Tools: `search_docs` (typo-corrected search), `get_doc` (an article as Markdown, by address or slug) and `list_topics` (knowledge bases, topics and article links).
- Shares only published articles without a password that the visitor may read: public articles for an assistant without a WordPress sign-in; members' articles follow the access labels for a signed-in user. Knowledge bases can be left out.
- Off by default. Turn it on in Help Center → AI & GitHub → "Let your customers' AI assistants read your docs", with the server address and a Claude Code command to copy. The route is registered only while it is on.
- Rate-limited to 120 requests per visitor per 10 minutes (hashed address, HTTP 429 with Retry-After); responses carry `X-Robots-Tag: noindex`.
- Searches that find nothing go to the chatbot's knowledge gaps (`qcld_wpbot_chat/knowledge_gap`, source `docs_search`); new action `qcld_wpbot_help_center/docs_searched`.
- The Help Center's llms.txt lists the server while it is on.
- `Seo::is_public()`, `Server::result()` and `Server::error()` are public now.
- AI & GitHub no longer scrolls sideways on phones (390 px).

## 0.18.0 (HC3-A: Claude builds the help center from GitHub, over MCP)
Owner, 20 November 2026: connect Claude, let it read a GitHub repository and fill the knowledge base. Decisions: Claude Code and Claude Desktop first; a built-in MCP endpoint plus WordPress Abilities; drafts by default with an auto-publish option; WPBot Free.
- **Help Center tools** (`Mcp\Tools`): `helpcenter_get_structure`, `helpcenter_search`, `helpcenter_get_article` (Markdown), `helpcenter_upsert_knowledge_base`, `helpcenter_upsert_category` (nested), `helpcenter_upsert_article` (Markdown, categories, knowledge bases, tags, parent, order, GitHub source), `helpcenter_set_order`, `helpcenter_list_gaps`, `helpcenter_propose_unpublish`, `helpcenter_get_changes`. Matched by slug, so running again updates instead of duplicating. The AI never deletes.
- **Review rules:** new articles are drafts, or published when the owner chose auto-publish. A change to an article a person edited (detected from a fingerprint of the AI's last write), or to a published article while the owner reviews, is kept as a suggestion to apply or dismiss. Every write is logged (`Mcp\Change_Log`) and limited to 600 an hour per user. Access follows the WordPress account (`edit_wpbot_articles`, `manage_wpbot_article_terms`, `publish_wpbot_articles`).
- **Markdown to blocks** (`Mcp\Markdown_Import`): headings, paragraphs, lists (two levels), code, quotes, GitHub alerts as Help Center callouts, tables, images, rules; raw HTML and unsafe links are dropped.
- **Built-in MCP endpoint** `POST /wp-json/wpbot-help/v1/mcp` (`Mcp\Server`): JSON-RPC over Streamable HTTP (initialize, ping, tools/list, tools/call), signed in with a WordPress Application Password. The same tools are WordPress Abilities (`wpbot-help-center/*`) for the WordPress MCP Adapter (`Mcp\Abilities`).
- **Help Center → AI & GitHub** (`Admin\Ai_Page`), in WPBot Core's admin design: create a connection key (shown once, never stored), ready commands for Claude Code and Claude Desktop, a prompt for a GitHub repository, drafts or auto-publish, "Publish all AI drafts", suggestions, and what Claude changed.
- **WPBot Core admin design** on Help Center screens: the header band and Notices bell, "Help Center" as the current band tab, and the Help Center tabs as WPBot Core section tabs (the old tabs remain without WPBot Core's design).

## 0.17.0 (switched on from AI / Settings; in the WPBot menu)
Owner, 19 November 2026: the Help Center is not a chatbot module.
- **Switch on AI / Settings** (`Admin\Ai_Settings_Section`): a Help Center section with "Turn on the Help Center". Needs wpbot-chatbot 0.25.0 (`qcld_wpbot_chat/ai_settings_sections`).
- **Own option** `qcld_wpbot_help_center_enabled` (`Feature_Switch::set()`), off by default. No longer in the Module Manager (Pro) or on the Features page (Free). The first read after the update moves the choice from the chatbot's module registry and removes the old entry.
- **WPBot → Help Center** (`Admin\Menu`): one item in the WPBot menu, just above Permissions, instead of a top-level WordPress menu. The Help Center's screens (articles, knowledge bases, categories, tags, glossary, Organize, Knowledge Gaps, Analytics, Design, Settings, Get started) are tabs at the top of each of them. Users who cannot open the WPBot menu keep the top-level Help Center menu.
- `Rewrite_Flush` watches the new option. The old registry entry is removed from both of its lists (active and inactive), also on admin requests while it is still stored.
- **AI / Settings → Help Center explains the Help Center**: why to turn it on, what it does for customers, and how it works with the ChatBot, AI Writer and SEO & AI Search.
- **Design is a tab of the Settings page** (General | Design). The old Design address opens the tab.
- **Shortcode `[wpbot_help_center]`** shows the whole Help Center home (title, search, knowledge bases, topics, most read) on any page (`title`, `articles`, `popular`). Settings → General lists every Help Center shortcode with a Copy button.
- **Get started is the first tab.**
- **Bulk actions → Publish** in the article list publishes the chosen drafts and articles pending review (`Admin\Bulk_Publish`), with the user's own publishing rights.
- **Theme fonts:** the Help Center no longer loads Geist; text uses the theme's fonts (code keeps the system monospace). The font files are removed.

## 0.16.1

- Switching the Help Center on in the Module Manager now gives working `/help/` addresses on the next request. The Module Manager saves while plugins load, so that request still ran with the Help Center off and its rewrite flush built rules without the Help Center (found on staging). The flush now waits for a request whose post type matches the new state.
- If the Help Center is on and its rules are missing anyway (another plugin flushed in a request where it was not registered yet, a restored database), they are rebuilt, at most once a minute. Seen on staging with WooCommerce, Elementor and SureRank active, where the module switch can lag one request behind.

## 0.16.0 (HC1-16)

- Languages: articles, knowledge bases, categories, tags and glossary terms are translatable in Polylang (`pll_get_post_types`, `pll_get_taxonomies`) and WPML (`wpml_config_array`, the same as a wpml-config.xml, which WPML reads only from a plugin's root). Access labels and the product version are copied to translations; votes, views and gap data are not.
- The Help Center's title and introduction are registered as strings to translate (Polylang, WPML String Translation).
- The glossary and search-vocabulary caches are kept per language, so popovers and typo correction use the visitor's language.
- The Markdown copy's URL rule names the post type, so Polylang adds its language prefix (`/fr/help/…md`). URL rules are now version 4.
- Checked with Polylang 3 languages (English, French, Arabic): per-language home, search, glossary, llms.txt and knowledge base; right-to-left layouts in all three designs pass axe.

## 0.15.0 (HC1-15)

- "Build your help center" (Help Center → Get started), offered on the dashboard and the Help Center screens while the Help Center has no articles (each user can dismiss it):
  - starter categories (shop categories when WooCommerce is active; filter `qcld_wpbot_help_center/starter_categories`);
  - drafts from questions the chatbot could not answer (Knowledge Gaps), through the Write article handler;
  - drafts copied from pages that read like help (FAQ, shipping, returns, warranty, guides…), with the page remembered in `_wpbot_source`; the pages stay as they are.
  - Drafts go into the starter category their title suggests. Nothing is published.
- Writing all drafts at once with AI comes with AI Writer's help center mode (qcld/wpbot-ai-writer#67); until then each draft opens with a link to AI Writer.

## 0.14.0 (HC1-14)

- Help Center → Analytics, for the last 7, 30 or 90 days or a year: article views, helpful rate (yes and no votes), searches and searches with no result, the questions the chatbot could not answer (open Knowledge Gaps), views per day (a chart with the numbers in a table), most read and least helpful articles (with the reasons visitors gave), most searched, and searches with no result with a link that starts an article titled with the search. People with the "view" level of the Help Center permission section can open it.
- Two small tables (`{prefix}wpbot_help_daily`, `{prefix}wpbot_help_searches`), one row per day; rows older than 13 months are removed once a day.
- Action `qcld_wpbot_help_center/searched` (search, number of results) from the results page.

## 0.13.0 (HC1-13)

- Help Center parts for any page, each as a shortcode, a server-rendered block (category "Help Center") and, for the basic set, an Elementor widget:
  - Search (`[wpbot_help_search]`, `wpbot-help/search-box`, Elementor) with live suggestions;
  - Knowledge bases (`[wpbot_help_kbs]`, `wpbot-help/kb-grid`);
  - Topics (`[wpbot_help_topics]`, `wpbot-help/topics`, Elementor);
  - Article list by category, knowledge base or tag, in Help Center order, recent, title or most read (`[wpbot_help_articles]`, `wpbot-help/article-list`, Elementor);
  - Most read (`[wpbot_help_popular]`, `wpbot-help/popular`);
  - Glossary (`[wpbot_help_glossary]`, `wpbot-help/glossary`, Elementor);
  - Contents and "Was this helpful?" for the article being viewed, for theme templates (`[wpbot_help_toc]`, `[wpbot_help_feedback]`, `wpbot-help/toc`, `wpbot-help/feedback`);
  - Help button: opens the assistant when it is on the page, otherwise links to the Help Center (`[wpbot_help_launcher]`, `wpbot-help/launcher`, Elementor).
- Parts follow the theme's fonts and colours with the Help Center accent; the styles and script load only on pages that show a part.

## 0.12.0 (HC1-12)

- Structured data for articles: TechArticle (headline, summary, section, dates, author, version, paid-content marking for signed-in-only articles), BreadcrumbList, HowTo from a Steps block, FAQPage from Accordion blocks. Through SEO & AI Search's `qcld_wpbot_seo/schema_graph` (replacing its generic WebPage and breadcrumb nodes for articles); printed by the Help Center itself when neither SEO & AI Search nor another SEO plugin prints structured data.
- A Markdown copy of each public article at its address + `.md`, announced with `<link rel="alternate" type="text/markdown">`; not indexed, canonical to the article. Signed-in-only articles answer 403.
- `<address>/llms.txt`: public articles by category, linking to the Markdown copies. In the site's own llms.txt (SEO & AI Search), help articles link to their Markdown copies (`qcld_wpbot_seo/llms_txt_url`). Adding the Help Center to the site's llms.txt by default needs the types filter in qcld/wpbot-seo#56.
- 301 redirects from earlier Help Center addresses to the same path under the current one, when the old URL finds nothing (owner decision H15). The SEO module's redirect helper takes over when qcld/wpbot-seo#56 lands.

## 0.11.0 (HC1-11)

- Write article (Train → Knowledge gaps), through `qcld_wpbot_chat/gap_write_article`, for people who may write help articles:
  - a question that is an article's title (a "not helpful" vote without words) opens that article for editing;
  - otherwise a help article draft titled with the question, in the category and knowledge base of the closest article (WPBot Core's knowledge search, else a title search).
  - The editor (block and classic) shows what visitors asked and how often, with a link to AI Writer with the question as the topic. Writing straight into the draft comes with qcld/wpbot-ai-writer#67.
- "Still need help?" under each article: the assistant and the contact address. Questions asked from an article carry it as context (the chatbot's page context when it is on, with room for a whole article; otherwise added here) with a rule to answer from the article first and name the section.
- Chat site search covers help articles (`qcld_wpbot_chat/search_post_types`).
- Article access labels go to `qcld_wpbot_core/kb_post_access`, so the chatbot's page context does not read a signed-in-only article to a visitor who may not read it.
- The widget's Help panel (Ask, Browse) needs a panel hook in the chatbot's widget (qcld/wpbot-chatbot#29).

## 0.10.0 (HC1-10)

- "Was this article helpful?" under each article. "No" asks why (four reasons and optional words) and then offers the assistant, the contact address and, through `qcld_wpbot_help_center/feedback_actions`, Pro's ticket form.
- Votes: REST `wpbot-help-center/v1/vote` and `/feedback`, public and without a nonce (cached pages work). One vote per visitor and article for a day (a changed vote moves the count); limited requests per address an hour. Reason counts in `_wpbot_helpful_reasons`, shown in the `help_stats` REST field.
- A "no" vote with its reason joins the chatbot's knowledge gaps (reason `not_helpful`; saved as `no_answer` until qcld/wpbot-chatbot#29): the visitor's words, otherwise the article title.
- Views: counted by the page script once per visit (`/view`), so cached pages count; editors and bots are skipped.
- Actions `qcld_wpbot_help_center/voted`, `/vote_reason` and `/viewed` for analytics.
- Glossary: terms under Help Center → Glossary (taxonomy `wpbot_glossary`), an A–Z page at `<address>/glossary/`, and popovers on the first use of each term in an article (keyboard, hover and touch; Escape closes). Popovers can be switched off in Settings.
- Access: articles for signed-in visitors (or a role) now show a sign-in note instead of the content on the page, in feeds, excerpts and REST. Pro adds labels through `qcld_wpbot_help_center/can_read`.
- URL rules carry a version, so an update that adds a rule flushes them once.

## 0.8.0 (HC1-08)

- Search: every word, or one of its synonyms (WPBot's site search synonyms, through `qcld_wpbot_chat/search_terms`), in the title, excerpt, content or alternative questions; the whole search in the title first, then a word in the title. Also used by the live suggestions.
- Typo tolerance: when nothing matches, each word is corrected to the closest word from article titles and alternative questions ("Showing results for …").
- A search that still finds nothing becomes a knowledge gap (`qcld_wpbot_chat/knowledge_gap`, reason `docs_search`), from the results page only.
- The AI answer above the results comes with the chatbot's search mode (qcld/wpbot-chatbot#29).

## 0.7.0 (HC1-07, with the reading features of HC1-09)

- Help Center pages inside the theme's header and footer (block and classic themes; a theme can override `wpbot-help-center/help-center.php`): home, knowledge base and category pages, article, search results.
- Three layouts (owner decision D1), in the approved design: **Help Center** (search with live suggestions and the assistant, numbered topics with their top articles, most read, recently updated, contact), **Docs** (knowledge-base links and search on top, a collapsible tree on the left with the current article marked, table of contents on the right) and **Simple** (one column in the theme's fonts and colours).
- Articles: breadcrumbs, updated date, reading time, "Applies to" version, Ask about this article (opens the chatbot), Print, Copy link, table of contents that follows the reader (inline "On this page" on phones), related articles, previous and next.
- **Design** screen: layout, accent colour (defaults to the brand colour from WPBot Core's Brand service), light, dark or the visitor's setting, home title and introduction, contact page, with a live preview.
- Geist and Geist Mono bundled (SIL Open Font License; no requests to font services). Styles and script load only on Help Center pages. Focus outlines, dark mode, logical properties for right-to-left languages, print styles.
- Hooks: `qcld_wpbot_help_center/after_article`, `qcld_wpbot_help_center/before_results`, `qcld_wpbot_help_center/contact_cells`.

## 0.6.0 (HC1-06)

- Help Center menu: **Organize** (the docs tree: knowledge base filter, categories with their articles; drag to reorder or Move up / Move down for keyboard users; saved at once through `POST wpbot-help-center/v1/order`), **Knowledge Gaps** (the chatbot's Train screen) and **Settings** (the address, with the clash check; earlier addresses are kept for the redirects of HC1-12).
- Article order in `menu_order`, category order in the term meta `wpbot_order`. Reordering articles needs the publish level, categories the right to manage terms.
- "Help Center" panel in the article editor: who can read it (publish level), the version it applies to, and other ways people ask (alternative questions).
- Fix: votes and views left REST meta (now the read-only REST field `help_stats`). The block editor sends the whole meta object on save, and a read-only key in it made every save with a meta change fail. Access labels accept the editor's save from anyone who may edit the article; during REST requests a change from someone without the publish level is ignored. Found in the real-WordPress test.

## 0.5.0 (HC1-05)

- Content blocks in the "Help Center" block category, registered from `blocks/<name>/block.json`: callout (note, tip, warning, important), steps (each step title is an `h3`, so the chatbot can cite a step), tabs (sections without JavaScript), accordion item (`details`), screenshot with numbered markers, a legend and zoom, "Applies to", "In short", related articles.
- Callout, In short, Applies to and Related articles are rendered on the server, so their translated labels are never saved into the post; the other blocks save only what the author typed.
- Code blocks in articles get a copy button. Front-end script without jQuery; block styles load only where a block is used.
- Plain JavaScript editor script using WordPress's `wp.*` packages: no build step.

## 0.4.1

- Fix: the switch is registered in the chatbot's module registry as soon as the module file is included (`QCLD_WPBot_Help_Center_Loader::register_switch()`), not at boot. WPBot Pro reads its add-on list while its main file loads, so the Help Center never appeared in the Module Manager and could not be switched on. Found in the first real-WordPress test.
- Permissions get a starting point the first time the Help Center runs: editors publish, authors write drafts (administrators always have every level). Written once to WPBot Core's grid (flag `qcld_wpbot_help_center_permissions_seeded`); the owner's later changes stay. Without it, only administrators could write articles.

## 0.4.0 (HC1-04)

- Articles are knowledge source `helpdesk_article` in WPBot Core's knowledge base: published articles without a password are stored with their headings (cited to the section), access labels (`_wpbot_access`) and alternative questions; removed when they leave "publish" or are deleted. Saves from the block editor are stored after their meta (`rest_after_insert_wpbot_article`).
- The post type is kept out of the generic post type source (`qcld_wpbot_core/kb_post_types`), so an article is never stored twice.
- `Content\Heading_Ids`: h1 to h4 get ASCII ids, the same on the article page (`the_content`) and in the stored HTML, so citation links land on the right section.
- Switching off removes the source's items; switching on starts WPBot Core's sync job for the articles. New action `qcld_wpbot_help_center/switched`; filter `qcld_wpbot_help_center/kb_item`.

## 0.3.0 (HC1-03)

- Content model, loaded only while the Help Center is on: post type `wpbot_article` (hierarchical, REST base `help-articles`), taxonomies `wpbot_kb`, `wpbot_article_cat` (nested) and `wpbot_article_tag`.
- Public address from the settings (`qcld_wpbot_help_center_settings`, default `help`): articles at `/help/<path>/`, the archive at `/help/`, terms at `/help/kb/`, `/help/category/`, `/help/tag/`. Top-level articles can't take the slugs `kb`, `category` or `tag`.
- `Address::sanitize()` and `Address::check()` (reserved WordPress paths, an existing page or post, another post type or taxonomy, a folder in the site root such as a separate install) for the settings screen.
- Capabilities follow the `help_center` permission section (use: write drafts; publish: publish, others', terms; view: private); without WPBot Core they fall back to the post capabilities.
- Article meta registered: access labels, product version, alternative questions (editable over REST by editors), votes and views (read only), source, AI write and human-edit markers (internal).
- Filters `qcld_wpbot_help_center/post_type_args`, `qcld_wpbot_help_center/taxonomy_args`.

## 0.2.0 (HC1-02)

- Permission section `help_center` on WPBot Core's grid (levels view, use, publish, settings), registered and adopted only while the Help Center is on; `Permissions::cap( $level )` falls back to `manage_options` without WPBot Core.
- Rewrite rules are flushed once, late on the next `init`, after the Help Center is switched on or off (watches the registry option `wpbot_master_addons`; flag `qcld_wpbot_help_center_flush_rewrite`). These two listeners are the only code that runs while it is off.

## 0.1.0 (HC1-01)

- Module skeleton: newest-copy loader (boots on `plugins_loaded` priority 6, after WPBot Core and the chatbot module), `Module` with `register_host()`, the `Host_Adapter` contract (`id()`, `plugin_file()`, `modules_capability()`).
- The Help Center switch: registered in the chatbot's module registry as `help-center`, **off by default**; nothing starts until it is on (`qcld_wpbot_help_center/host_ready`). Filter `qcld_wpbot_help_center/enabled`.
- Hooks: `qcld_wpbot_help_center/loaded`, `qcld_wpbot_help_center/host_ready`.
