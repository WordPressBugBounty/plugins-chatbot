# Changelog

## Unreleased (SEO Help QA fixes and Plugin Check, owner's go 13 November 2026)
- **llms.txt "Check live file" on self-signed sites** (issue #43): the request to the site's own `/llms.txt` follows WordPress's `https_local_ssl_verify` rule (as the scan does). A certificate error that remains is explained in plain words instead of the raw cURL text (`Llms_Check::error_text()`).
- **Weekly email:** "Preview this week's email" and "Send me a test email" show a busy state and are disabled while the request runs ("Sending…").
- **Uninstall** (issues #43 and wpbot-seo-pro#14): the last host's uninstall also clears the Pro task `qcld_wpbot_seo_pro_planner_refresh`, and with "Delete data on uninstall" on removes the transients `qcld_wpbot_seo_redirect_map`, `qcld_wpbot_seo_blocked_bots`, `qcld_wpbot_seo_home_text` and every `qcld_wpbot_seo_link_<hash>`.
- **Plugin Check 2.2.0** (issue #51): 0 findings in `src` (was 112). Table names go into `$wpdb->prepare()` as `%i` (needs WordPress 6.2, the hosts' minimum already). Fragments built only from `prepare()` keep a narrow ignore with the reason. `Link_Suggestions` no longer uses `post__not_in` (the current post is left out in PHP, same result). No behaviour change.
- **Tests:** integration suite `qa_fixes.php`; browser checks for the Business Context comma behaviour.

## Unreleased (Pro previews with Benefits)
- **Pro previews show Benefits next to Features** (owner, 12 November 2026). A host's `get_pro_previews()` items can carry `benefits` (strings, like `features`). The app then shows two lists, Benefits and Features, in the preview card and in preview tabs. Previews without benefits look as before.

## 1.4.0 (R8: SEO Help as a second host)
- **Follows one primary host:** `Module::host()` follows WPBot Core's primary host (the plugin installed first). Without it, the host stored first in `qcld_wpbot_seo_hosts` leads instead of whichever registered first in the request. Filter `qcld_wpbot_seo/primary_host` is kept.
- **Page slug from the host:** `Module::page_slug()` uses the host's optional `get_page_slug()`, default `wpbot-seo-ai-search` (unchanged for WPBot), filter `qcld_wpbot_seo/page_slug`. `Module::page_url( $args )` builds every link, in PHP, emails, JS and the Pro module. `Module::writer_url()` builds AI Writer links with AI Writer's own slug.
- **Product name instead of "WPBot":** `Module::product()`, `pro_product()` and `agent_token()` come from WPBot Core's `Host\Brand`. Every text that named WPBot uses them, and so do these:
  - "needs a newer version of <product>";
  - the `X-Redirect-By` header and the robots.txt comment;
  - user agents;
  - the React app, through `data.product` and `data.proProduct`.

  The robots.txt check honours the product token as well as `wpbot`. On WPBot sites every text reads as before.
- **Shared pages:** the page is listed in WPBot Core's `module_pages` for a host that isn't primary.
- **Tests:** `HostsTest`.

## 1.3.1 (keys follow AI Settings)
- **AI Search Provider > AI Settings** shows "Using WPBot AI Settings" by default: SEO & AI Search uses the keys, primary and backup saved in the host's AI Settings, read live (WPBot Core R3). "Use own keys for SEO & AI Search" switches to its own keys, starting from a copy; "Use AI Settings instead" switches back. The monthly budget stays per feature.



## Unreleased (fix)
- Keywords & Topics > Research: the live cost estimate is asked for only once the form has what a run needs (a seed of 2+ characters or a page; competitor domains for a gap). Before, opening the tab sent an estimate request with no keyword, which the server refused (400 "Enter a seed keyword or choose a page.") and the browser logged as an error.
- Tests: the Business Context browser checks wait for each section before typing.

## Unreleased (Business Context)
- **Get cited by AI** shows a short "Your business" card that links to WPBot > Business Context (WPBot Core), where the context is edited for every module. The full Site Context panel stays only for hosts with an older WPBot Core.
- SEO & AI Search adds its sources to Business Context > Data you already have (`Cited\Context_Sources_Seo`): Google Search Console, Keyword Vault, business location, Google Business Profile, and the "Add competitors" link.
- Tests: `tests/integration/business_context.php` (32, documents included); the browser test builds, edits and saves the context and uploads a document on the Business Context screen.

## Unreleased
- **Review link QR code** (Local SEO > Reviews): a QR code of Google's review link, shown under the link and downloadable as a PNG to print on receipts, at the counter or on flyers. Made in the browser; nothing is sent anywhere.
  - First third-party library in the module, approved by the owner on 4 November 2026: **qrcode-generator 2.0.4** by Kazuhiko Arase, MIT licence, https://github.com/kazuhikoarase/qrcode-generator (npm, pinned; bundled into `assets/build/admin.js`).

## Unreleased (round 3: Get cited by AI and Local SEO)
- **L. Local SEO > Service and area planner** (`Local\Area_Planner`).
  - Services from Site Context (short names) and product categories, or the owner's own list; areas from the locations' towns and the areas served, or the owner's own list (option `qcld_wpbot_seo_area_plan`, removed on uninstall).
  - A grid of "service + area" searches with searches per month when known ("Get searches per month for the grid" uses the keyword data provider with a cost estimate first; new volume scope `areas`), the page that covers each search (Keyword Map, location page) and a verdict: Covered, Deserves its own page (only where a location has an address and a phone), Mention on the service page (no doorway pages), Few searches.
  - Routes `/local/areas` (view) and `/local/areas/save` (manage); filters `qcld_wpbot_seo/area_view`, `qcld_wpbot_seo/area_services` and JS `qcld_wpbot_seo.local.areaCell` (Pro adds "Write this page").
  - Tests: `tests/integration/local_areas.php` (15).
- **K. Local SEO > local AI visibility slot:** JS filter `qcld_wpbot_seo.local.ai` (Pro fills it). Without an extension, a labelled, inactive Pro preview shows only when the host shows Pro previews (`LocalAiSlot`).
- **J. Local SEO > Reviews** (shown once a Business Profile location is linked).
  - Reviews read from Google into table `qcld_wpbot_seo_reviews` (3 pages, refreshed after 6 hours or with "Refresh from Google"); count, average stars and reviews without a reply.
  - Filters: all, without a reply, 1 to 3 stars.
  - **Reply drafts with AI** (feature `review_reply`, cost shown first): calm and helpful for bad reviews, short thanks for good ones; the reviewer's name and personal details are removed before the AI call. The owner edits, then "Post reply on Google" (needs `manage`).
  - Google's review link with a copy button and the rule: ask every customer, not only happy ones.
  - Routes `/local/reviews`, `/local/reviews/sync`, `/local/reviews/draft`, `/local/reviews/reply`; filter `qcld_wpbot_seo/reviews_view`, action `qcld_wpbot_seo/reviews_synced( $new_ids, $first )`, JS filter `qcld_wpbot_seo.local.reviews`.
  - Uninstall (with delete on) also removes the round 3 tables (cited places, cited posts, reviews), the review options and the Pro review events.
  - Tests: `tests/integration/local_gbp.php` (22 with reviews).
- **I. Local SEO > Google Business Profile.**
  - "Connect Business Profile" (setting `gbp_on`), then a Google reconnect.
  - If Google hasn't approved the project's API access, the card explains how to request it.
  - Choose the profile location (linked to the main location; option `qcld_wpbot_seo_gbp_links`, removed on uninstall).
  - **Health check** against Local SEO: business name, address, phone (formatting ignored), website link, opening hours, special hours in the next 60 days, main category, and Google's review link. Each difference says how to fix it on Google.
  - Routes `/local/gbp`, `/local/gbp/settings`, `/local/gbp/link`; filters `qcld_wpbot_seo/gbp_view` and JS `qcld_wpbot_seo.local.gbp` (Pro adds performance).
  - Tests: `tests/integration/local_gbp.php` (14); the browser test checks the card. The mock has Business Profile accounts, locations, reviews, replies and performance; a flag file simulates "no API access yet".
- **G hooks** (used by Pro):
  - filters `qcld_wpbot_seo/cited_places` (more places for a search, checked like the AI's) and `qcld_wpbot_seo/cited_estimate`;
  - new place source `citation` ("Cited by AI assistants");
  - Links opens a tab from the address (`&section=cited`);
  - the mock has `gone/` (404) and DataForSEO organic results.
- **F. Posted log.**
  - **"I posted it"** on a place saves the link to the owner's post, optionally with the page of their site it links to and a note.
  - The link must be on the place's platform and not on the owner's own site; the same link isn't logged twice.
  - The place is marked posted; removing the post from the log puts the place back.
  - **The "Posted" card** lists the posts.
  - A post with a linked page is recorded in Results & Reports as a change (kind `offsite`, filter "Posts on other sites"), so that page's clicks before and after show there.
  - `Cited\Posts::save_checks()` lets Pro add checks.
  - Action `qcld_wpbot_seo/cited_posted`; JS filters `qcld_wpbot_seo.cited.postStatus`, `qcld_wpbot_seo.cited.posted.after`.
  - Routes `/cited/posts`, `/cited/posts/log`, `/cited/posts/delete`.
  - Tests: `cited.php` 39 checks; `admin.e2e.mjs` checks "I posted it".
- **E. Drafts.** Each place has a draft button whose label suits the platform: Draft a reply, Draft an answer, Write the article, or Video idea.
  - **Before writing:** two optional quick questions (a tip, a real example) and "Steer this draft". The cost is shown first.
  - **Inputs:** the public page of the place is read on the owner's click, with personal data removed. The draft is written from the Site Context, the focus, the owner's answers and the platform's norms, always within the built-in rules (`Cited\Rules`; filter `qcld_wpbot_seo/cited_rules` can only add rules).
  - **After writing:**
    - the disclosure line is added when the draft mentions the business (setting `cited_disclosure`, default "(Disclosure: I work at <site name>.)");
    - WPBot Core's honesty check flags invented proof and similar claims;
    - a draft too close to another place's draft is rewritten once, else flagged;
    - a rule-breaking steer is explained under "Left out by the rules".
  - **The card:** Copy text, Open the thread, Why this works, Check this before posting, and a "Before you post" checklist. LinkedIn and Medium places also offer "Write in AI Writer" with a brief.
  - Routes `/cited/drafts/estimate`, `/cited/drafts/create`; JS filter `qcld_wpbot_seo.cited.draftActions`; filter `qcld_wpbot_seo/cited_thread_url`.
  - Tests: `cited.php` 31 checks; `admin.e2e.mjs` checks place to copied draft in under a minute.
- **C. Places to show up.**
  - Pick up to five searches: focus topics, Keyword Vault, top Search Console searches, or your own.
  - The cost is shown first, then a job runs:
    - the module's AI searches the web for threads, questions, videos and articles on Reddit, Quora, LinkedIn, Medium, Stack Exchange, forums and review sites;
    - a model without web search suggests communities, labelled "AI suggestion (not checked)";
    - YouTube videos come from WPBot Core's YouTube search once "Turn on YouTube" is clicked and Google is reconnected.
  - Places that are left out:
    - the owner's own site and competitors;
    - addresses that aren't on the platform they claim;
    - unknown or switched-off platforms;
    - places without a title.
  - Each place shows its source and its community's rules, and places are ranked by fit with the focus.
  - "Not relevant" hides a place, and it stays hidden when found again.
  - New tables `qcld_wpbot_seo_cited_places` and `qcld_wpbot_seo_cited_posts` (the posts table is used in F); settings `cited_platforms` and `cited_youtube`.
  - Routes: `/cited/places`, `/cited/places/estimate`, `/cited/places/find`, `/cited/places/status`, `/cited/settings`.
  - JS filter `qcld_wpbot_seo.cited.placeActions`.
- **D. Your focus.**
  - One-click focus suggestions from the Site Context, each with where it came from.
  - A focus sentence (up to 500 characters, personal data removed) and up to 8 topics. Option `qcld_wpbot_seo_cited_focus`, removed on uninstall.
  - Routes `/cited/focus`, `/cited/focus/save`.
- Tests: `tests/integration/cited.php` (18 checks); `admin.e2e.mjs` checks the one-click focus, the cost-first step and the places list.
- **B. Free keyword data.**
  - The owner's Bing Webmaster Tools key (Settings > Connections > Other services) now also gives Bing search numbers, trends and related searches, labelled "Bing (free)". They're used when no keyword data provider or Keyword Planner is set (`Keywords\Free_Data` gives WPBot Core this module's Bing client).
  - New setting `cited_youtube` (off by default) turns on WPBot Core's YouTube search, which asks Google for read-only YouTube access at the next reconnect.
  - The Keyword data card explains the free Bing numbers.
  - Mock: `bing/GetKeywordStats`, `bing/GetRelatedKeywords`, YouTube search and videos; the mock Google token grants YouTube unless the client id is `no-youtube`.
  - Tests: `tests/integration/free_data.php`.
  - `s12.php` and `r3.php` set the Bing key and any provider aside while they run, so "no provider" checks stay true.
- **A. Links > Get cited by AI, "What we know about your site".** The new tab starts with WPBot Core's shared Site Context:
  - The free scan of the site runs by itself on the first visit.
  - SEO adds what it already has: top Search Console searches, Keyword Vault keywords, the main location, and the most-clicked pages, which are scanned first. Filters: `qcld_wpbot_core/context_facts`, `qcld_wpbot_core/context_important_posts`.
  - The chatbot's knowledge base and customer questions are switched on with one click.
  - "Build it now" fills the chatbot's knowledge base from the main pages and products, as a job.
  - The AI summary shows its cost first, and can be edited and rebuilt. "Keep it up to date" is on by default.
  - Routes: `/cited/context`, `/cited/context/scan`, `/cited/context/summary`, `/cited/context/edit`, `/cited/context/settings`, `/cited/context/knowledge`.
  - Tests: `tests/integration/context.php`; `admin.e2e.mjs` checks the tab, the cost-first step and the summary. The stub host can fake a knowledge base and chat sessions (options `qcseo_test_knowledge`, `qcseo_test_chats`).
  - The list-field browser test saves the default results days back, so later checks see 28 days.

## Unreleased
- **Fix: typing in a list field crashed Settings.** "Send to" (Email & results) and "Paths to leave out of scans" (Data & scans) stored the typed text where the screen expected a list, so the next keystroke crashed the whole page. "Measure results after" could not start a new line. A shared `ListField` keeps the text as typed and sends the clean list. Tests: `admin.e2e.mjs` types into the fields, saves and reloads.
- **Settings forms spaced out.** Data & scans, Email & results, General and the Google card had their fields stacked with no space between them. They now have even spacing, a readable width (640px for single fields) and a line between sections (new `.qcseo-form` class). Email & results has a separate "Results" section with a short explanation of "Measure results after", and "Hide the footer line" says which line it hides. The "Add a competitor" button lines up with its field.
- **Competitor suggestions ask first.** "Suggest competitors" opens a short form: **Compete on** (a product, service or topic; it starts from the research keyword, the last answer, or the site's tagline or name) and **Kind of competitor** (any, local businesses near me, online shops and services, blogs and content sites). Both go to the AI through WPBot Core's new optional `focus` and `kind`. The results say what they were found for ("Competitors for: bike servicing · Local businesses near me"). The answers are remembered (option `qcld_wpbot_seo_competitor_prefs`, removed on uninstall).
- **Automatic suggestions only after the first brief.** Competitor gap no longer suggests on its own for a site that never gave a brief; it opens the form instead. Afterwards, an empty competitor field suggests on its own with the last kind and the typed keyword, once per brief (the last 30 briefs are remembered), and never while the shared list has competitors. Tests: `tests/integration/competitors.php`, `admin.e2e.mjs`.
- **Pro preview tabs.** A host's preview item can take `'tab' => 'section:name'` (sections `keywords`, `technical`, `links`, `aiSearch`). It then shows as an inactive tab marked "Pro" inside that section, with the preview card, instead of a left-menu item. A tab with that name already added by an extension (WPBot Pro) is never duplicated, and hidden previews add nothing. New helper `withPreviewTabs()` in `components/ProPreview.js`; screens pass a tab's `className` through. Tests: `previews.php` checks the placement and that tab previews stay out of the left menu.
- **Google Ads setup in Settings (Pro).** The Google Ads API setup now lives in **Settings & AI Provider > Connections**, under the Google card, with step-by-step instructions. A JS filter `qcld_wpbot_seo.settings.connections.google` hosts it, and the section has the anchor `#google-ads`. Keywords & Topics > Paid searches shows a short status with a link to it. The Free note there mentions Keyword Planner and links to the same section.
- **Free: Google Ads preview card.** When nothing fills the Google Ads spot (`qcld_wpbot_seo.settings.connections.google`) and the host shows Pro previews, the spot shows a labelled, inactive Pro preview of the Google Ads API and Keyword Planner (new component `GoogleAdsSlot`). With previews hidden, or on a host without previews, the spot stays empty and the Paid searches note drops the WPBot Pro sentence and its "Open Connections" button, so Free never links to an empty section. Tests: `admin.e2e.mjs` checks both cases without Pro (`QCSEO_PREVIEWS=1` with the stub option `qcseo_test_previews`).
- **No developer token.** Google retired developer tokens on 9 September 2026; access now belongs to the Google Cloud project, so the field is gone. The test mock refuses any request that still carries a token, and simulates a project with only Test access (customer `1111111111`) and one with Explorer access (customer `2222222222`).

## Unreleased (Pro previews)
- **Pro preview pages list what Pro adds.** A host's `get_pro_previews()` items take an optional `features` list (shown as a ticked list) and an optional `url` and `button` label (a "Learn more" style button). The line "This is a preview of a WPBot Pro feature. You can hide previews in Settings." is gone. The AI Search > Visibility tab uses the same preview card. New component `ProPreview`.
- **"Hide Pro previews" hides them everywhere.** The app no longer receives preview data when the setting is on, so the Overview card, the Prospects card and the Visibility tab show nothing Pro either (the left menu already respected it).
- Tests: `tests/integration/previews.php`; the stub host's preview has a feature list and a link.

## 1.3.0 (round 2)
- **Research: no "Check the cost" step (R1).**
  - "Run research" shows straight away, with the estimated cost under it. The estimate refreshes as the keyword, country or competitors change.
  - A run that costs more than the new setting `research_confirm_above` is confirmed first. The default is $0.50 in USD; 0 means always ask. The route `/research/run` returns 409 `qcld_wpbot_seo_research_confirm`, with the estimate, until it is sent with `confirmed`.
  - Provider credits in units other than USD always ask first.
  - A saved result opens with "Open saved result (free)".
- **Shared competitor list (R2).**
  - Every competitor field reads one list, kept in WPBot Core.
  - The list is seeded once from the competitors the site already had (Pro's list, and AI Writer Pro's Competitor Watch through `qcld_wpbot_seo/competitor_hosts`).
  - **Settings & AI Provider > Competitors:** the list, with each site's reason and source; add and remove sites.
  - **"Suggest competitors"** (`CompetitorSuggest`, also on `window.qcldWpbotSeoUI` for Pro) sits next to the Competitor gap field and in Settings. Verified suggestions are shown pre-ticked, with their reason, source and site title, and the sites that were left out are listed with why.
  - Ticked suggestions are added to the field. People who can change settings also save them to the list.
  - The Competitor gap field fills itself with suggestions once, for a site that has no competitors yet.
  - **Routes:**
    - `/competitors` (Use level)
    - `/competitors/suggest` (Use level; uses AI credits)
    - `/competitors/save` and `/competitors/add` (settings level)
  - The prompt gets the country, language, business type and area (`Research\Competitor_List`).
  - The shared list is left out of link prospects.
  - JS filter `qcld_wpbot_seo.competitors.after`.
- **Opportunities that work right away (R3).**
  - **First scan** (`Opportunities\First_Scan`): it starts once, the first time someone who can use the module opens the admin. It runs the site audit, the technical scan, and a first Topic Map from post and product categories (`Opportunities\Site_Topics`: topic source `site`; pages with no main search get their title, source `title`). Pages that already have a topic, and the owner's own entries, are never moved. No AI is used.
  - **New content-only types** (`Opportunities\Content_Types`), up to 30 each:
    - orphan page
    - internal link to add (Topic Map pillar to page and back, with link text)
    - no focus keyword
    - no search description
    - duplicate title
    - thin page
    - outdated content (a past year in the title, or an important post not updated for a year)
    - pages competing for one focus keyword
    - missing business details
    - WooCommerce products without a description, and categories without an introduction
    - AI search readiness (a long key page with no summary or FAQ)
    - AI topic gaps
  - **AI topic gaps:** route `/opportunities/topic-gaps`, run on a click. They are labelled "AI suggestion" and carry no numbers; digits are removed from the AI's reasons. Filter `qcld_wpbot_seo/topic_gap_numbers` lets real numbers be added.
  - **Ranking by page importance** (`Opportunities\Page_Importance`): home page, menu, internal links (counted in one pass), product sales and topic pillars. Search Console impressions are added when present.
  - **With Search Console:** on-page and technical fixes are now kept, ranked together with the Search Console types.
  - **New actions:** Edit this page, Edit this category, Add your business details.
  - **Actions for extensions:**
    - `qcld_wpbot_seo/opportunities_rebuild_start`
    - `qcld_wpbot_seo/opportunities_rebuilt` (new opportunities per type)
  - **The list response** includes `search_console`, `first_scan` and `topic_gaps`.
  - **Opportunities screen:**
    - a bold, non-dismissible "Connect Google Search Console for significantly better results" note until Search Console is connected; people who can't connect it are told to ask the site administrator
    - a first-scan banner that refreshes every 20 seconds while the scan runs
    - a "Find topic gaps with AI" button
- **llms.txt under Technical SEO (R4)** (`Llms\Llms_Txt`, `Llms_Builder`, `Llms_Rules`, `Llms_Check`). Off until the owner turns it on.
  - **Served live:** `/llms.txt` is built from the database on request; no file is written to the server.
  - **Format:** an H1 site name, a blockquote summary, optional notes, then H2 sections — Key pages, Topic Map topics with the pillar first, Products, then "Optional" — of `- [name](url): description` lines.
  - **Inclusion rules:** a page is listed only if it is published and public, has no password, is not noindexed (WPBot, Yoast, Rank Math, AIOSEO), is not blocked in robots.txt, is canonical to itself, and is a listed post type. Attachments, hidden WooCommerce products, members-only pages (Paid Memberships Pro, plus the filter `qcld_wpbot_seo/llms_txt_gated`) and near-empty placeholders are left out. The tab lists every left-out page with the reason.
  - **Owner settings:** summary, notes, most links (default 100), post types, pinned pages and kept-out pages.
  - **Always current:** any content, menu, permalink, Topic Map or settings change records a timestamp (`qcld_wpbot_seo_llms_changed`). The next request rebuilds the file, so deleted and unpublished pages drop out at once. A background rebuild follows within a minute.
  - **Caching:** responses carry `Cache-Control: max-age=300`, `ETag`/`Last-Modified` and a 304 reply. Each change purges WP Rocket, LiteSpeed Cache, WP Super Cache and W3 Total Cache; other caches can use the action `qcld_wpbot_seo/llms_txt_purge`.
  - **Live check:** "Check live file" loads the public URL and compares it with the generated file. A cache-busting second request tells a stale cache copy apart from another source. It also runs daily, two minutes after each change, and after a plugin is turned on or off.
  - **Conflicts reported:** a real llms.txt file on the server (never deleted), Yoast or Rank Math generating llms.txt, and a site living in a subfolder.
  - **Opportunity** "llms.txt for AI search": shown while it is off, or while the live file is not WPBot's.
  - **Routes:** `/llms` (Use level), `/llms/settings` (settings level), `/llms/check`, `/llms/rebuild`.
  - **Filters:** `qcld_wpbot_seo/llms_txt_description`, `qcld_wpbot_seo/llms_txt_url`, `qcld_wpbot_seo/llms_txt_refusal`, `qcld_wpbot_seo/llms_serve`.
  - The screen is honest that llms.txt is a proposed standard, with no ranking promise.
- **Real search numbers everywhere (R5).** These work with any keyword data source; Google Keyword Planner is turned on in Pro.
  - **Keyword Vault:** trend (rising, steady or falling), competition and value (the top-of-page bid range) next to the volume, with filters for trend, competition and a minimum number of monthly searches (route `/vault` passes `trend`, `competition` and `min_volume`).
  - **Topic Map:** monthly searches per page and per topic, with the source. Planned pages (gaps) are sorted by searches. A "Needs a pillar page" badge marks topics with three or more pages and no pillar.
  - **Paid searches:** a "Searches a month" column with its source, next to ad spend.
  - **AI Writer:** briefs from the Vault carry "Monthly searches", "Trend", "Competition" and "Source" in `numbers`. Vault ideas sent to the Topic Finder say "About N searches a month (source)" in their reason.
  - **Hooks:** filter `qcld_wpbot_seo/map_suggestion` (a page's suggested main and supporting searches) and action `qcld_wpbot_seo/topic_gaps_found`.
  - The source name "Google Keyword Planner" is shown wherever a number has a source.
- **Content opportunities:** the cap per type (30) can be changed with the filter `qcld_wpbot_seo/content_opportunity_cap`.
- **Fix:** the competitor hosts are de-duplicated. A site in both the shared list and Pro's list was counted twice in AI visibility share of voice.
- **Weekly email fix:** a result that only has an on-page score (no Search Console checkpoints yet) showed a PHP warning. It is now listed as "On-page score before → after".

## 1.2.0 (Page Audit and S13)
- Paid search data from Google Ads (S13): Free, with no extra setup, a daily import of the last 30 days of paid searches through the linked Google Analytics 4 property (`Ads\Ga_Ads_Pull`: `sessionGoogleAdsQuery` with `advertiserAdClicks`, `advertiserAdImpressions`, `advertiserAdCost`; with `landingPagePlusQueryString`, `sessions` and `keyEvents`), stored per source in table `qcld_wpbot_seo_ads` (`Ads\Ads_Data`; action `qcld_wpbot_seo/ads_pulled`). When GA4 returns no cost, searches are still imported and the screen says why. Routes `/ads`, `/ads/pull`, `/ads/vault`; Keywords & Topics > Paid searches (JS filter `qcld_wpbot_seo.paid.after`).
- Opportunities (`Ads\Paid_Searches`): "Save on ads" (top-3 organic searches with ad spend: review the bid in Google Ads; converting searches on page 2: improve the page) with an estimated monthly saving and its assumptions (50% and 30% of paid clicks); "Paid search with no ranking page". Converting paid searches go to the Vault (source Google Ads); paid searches become supporting searches of their landing pages (never over the owner's manual choices); AI Writer's Topic Finder gets the "Paid searches (Google Ads)" source (converting searches with no page, and new terms seen in ads first). Nothing is ever written to Google Ads.
- Page Audit & Fixes (F1–F4): the Fixes menu item is now "Page Audit & Fixes" (same URL). Run an audit for any public post type (products with WooCommerce) and page, with a target keyword pre-filled from the Vault, the SEO plugin, the Keyword Map or Search Console, and optionally save it as the focus keyword (SEO plugin field and a manual, locked Keyword Map entry; logged and undoable). Routes `/audit/*`.
- Full checklist (`Onpage\Checks` returns `checks` with pass / warning / fail / tip / info and a group): new keyword checks (start of the title, description, URL, introduction, subheadings, repeats as a warning only; `Onpage\Keyword_Checks`), links to other sites, unscored title tips (numbers, engaging words), and the AI search readiness items. Without a keyword, keyword checks say "add a target keyword" instead of failing. The page's target is the SEO plugin focus keyword, else the owner's manual Keyword Map entry, else the main Search Console search.
- AI fixes for image alt text, an FAQ and the introduction (`Onpage\Content_Fixer`, routes `/fixes/{id}/content`, `/fixes/{id}/content/apply` at Publish level): reviewed and edited before saving, logged as content changes and undoable (undo refuses when the page was edited since).
- Site summary (`/audit/summary`): good (70+), average (40–69) and poor (under 40) pages, score distribution, average score, counts per issue; the page list filters by band, issue and type.
- Audit history (`Onpage\Audit_History`, table `qcld_wpbot_seo_audits`, setting `audit_history_months`, default 12): every audit with keyword, score, check results and trigger; trend per page; past audits viewable. Action `qcld_wpbot_seo/audit_recorded`; `Audit::start_scan( $trigger )`.

## 1.1.0 (follow-ups 3.0 and S14)
- Keyword research without Search Console (S14): Keywords & Topics tabs are now Research, Vault, Keyword Map, Competing Pages and Topic Map. Research (`Research\Research`, routes `/research/*`) runs Keywords (related, long-tail, questions, clusters), Niche (web search when the model supports it) and Competitor gap; with a keyword data provider the lists and rankings come from the provider and AI adds intent, clusters and reasons; without one, a competitor gap politely reads sitemaps, titles and headings through the job queue (`Research\Competitor_Fetch`, filter `qcld_wpbot_seo/competitor_fetch`; robots.txt respected; nothing kept after the run). Every run shows its cost first, is cached 30 days per seed, language, country and data source, and is kept in the research history (table `qcld_wpbot_seo_research`). The S12 keyword ideas panel is part of Research.
- Honest numbers (`Research\Schema_Guard`): AI answers are checked against a strict schema (demand and difficulty are high, medium or low; no numeric volume, difficulty or cost-per-click field; number claims removed from reasons). Exact numbers appear only from a provider, Search Console or Google Ads, with a source badge; AI values say "AI estimate".
- Keyword Vault screen (`Research\Vault_Service`, routes `/vault/*`, data in WPBot Core): totals per status, search, filters, sortable columns, bulk status / assign / topic / ideas / archive / delete, CSV import (2 MB, 5,000 rows, sanitised) and export (formula cells escaped), and Write, Assign to page (manual, locked map entry), Add to calendar and Archive per keyword. Search Console positions move Vault keywords into and out of Ranking after each import.
- Search Console optional everywhere: an inline note replaces the blocking connect card; the Overview shows a Setup card (business details, first site check, three optional connections), on-page, technical and AI readiness health, Vault progress and the latest research (`Api\Site_Health`); Opportunities without Search Console list Vault keywords with no page, two pages on one Vault keyword, and on-page and technical fixes (`Opportunities\Site_Types`; rebuilt daily and after Vault changes); Results show the on-page score before and after each change; Settings say Search Console and Analytics are optional.
- Topic Map without Search Console: Vault keywords appear as planned pages and count as planned coverage; "Group pages into topics with AI Writer" reuses AI Writer Pro's grouping job (`/topics/group`).
- JS filter `qcld_wpbot_seo.research.after` for extensions; filter `qcld_wpbot_seo/ads_connected` for the Setup card.
- AI setup clarity (follow-up 3.0): WPBot Core's shared readiness check drives a bold notice at the top of every screen with AI actions (Opportunities, Keywords & Topics, Fixes, AI Search, Links, Local) with one button to the fix; "Fix with AI", "Suggest with AI" and "Write with AI" are disabled with the reason and link while AI isn't ready (`window.qcldWpbotSeoUI.AiReady`, `AiReason`, `aiBlocked`, `useAiReady` for extensions). Settings > AI shows each provider's status (Ready, Key saved but turned off with a Turn on button, Not working with the reason, No key) and where the keys were copied from. The menu item and page title are now "Settings & AI Provider" (same URL; `&section=ai` opens the AI tab). Filter `qcld_wpbot_core/ai_settings_url` points WPBot Core's notice to this page.

## 1.0.0 (finish)
- `Seo_Bridge` reads and writes SEO fields through WPBot Core's shared `Seo\Seo_Adapter` (request R-1, also used by AI Writer) whenever a supported SEO plugin is active; the change log and undo stay here, and the module's own fields remain the fallback without an SEO plugin.
- Form controls opt in to WordPress's 40px default size (`__next40pxDefaultSize`), ahead of WordPress 7.1.
- `Data_Store::post_for_url()` re-resolves stale cached posts (from S11).
- Fix: Settings > AI follows WPBot Core's primary and secondary providers (CR-2). The old per-provider "Turned on" switches and the "First provider that is turned on" choice sent an empty primary, so a provider switched on came back off after saving. The screen now has "Primary provider" and "Secondary provider (backup)" choices, and each provider card shows its role.

## 0.12.0 (S11 Keywords & Topics)
- Keyword Map (`Keywords\Map_Sync`) on WPBot Core's shared keyword map: each page's main search (most clicks) and up to four supporting searches (impressions, within the top 30) from its own Search Console data, written with source `search_console` and module `seo`; refreshed in the job queue after each Search Console import (`qcld_wpbot_seo/gsc_pulled`); manual and locked entries are never changed. Accept, change or lock per page; AI suggestions for pages without data (not saved until accepted); "Use as focus keyword" writes the main search into the SEO plugin only when its focus keyword is empty; gaps (searches no page targets, by volume when keyword data is connected); counts of mapped, not mapped and no-data pages; volumes from keyword data.
- Competing pages (`Keywords\Cannibalization`): from Search Console (two or more pages each with at least 15% of a search's impressions, 50+ impressions) and from the map (same main search); the stronger page first; fixes: link to the stronger page and target another search (Publish level), merge and redirect (Manage settings; the weaker page is set to draft and AI Writer opens on the stronger page). New opportunity type "Competing pages".
- Topic Map: the shared topics with coverage (covered, thin, missing, planned), pages and planned pages, as an expandable list and a visual view; move pages, rename topics, write missing or thin topics in AI Writer. Filter `qcld_wpbot_seo/topics`; JS filter `qcld_wpbot_seo.topics.badges`.
- `Data_Store::post_for_url()` re-resolves a cached address when its post was deleted, and daily for addresses without a post.
- REST `/map`, `/map/refresh`, `/map/gaps`, `/map/{id}` (+ `/accept`, `/suggest`, `/focus`), `/competing`, `/competing/fix`, `/competing/merge`, `/topics`, `/topics/move`, `/topics/{id}` (+ `/write`).

## 0.11.0 (S9 hooks for Pro reach)
- `Local\Locations::add()` adds another (non-main) location; `save()` without an id still edits the main location, as before.
- Uninstall (owner opt-in) also removes the Pro report event and location notes.

## 0.10.0 (S8 hooks for AI search visibility)
- Filter `qcld_wpbot_seo/ai_visibility_score`: the dashboard API's `ai_visibility_score` comes from the Pro module (null without it).
- Uninstall (owner opt-in) also removes the Pro visibility tables (`pro_prompts`, `pro_runs`, `pro_answers`) and the scheduled run.

## 0.9.0 (S12 Keyword data)
- Optional keyword data provider on the owner's own key (DataForSEO, Keywords Everywhere or Semrush), through WPBot Core's new keyword data layer: Settings > Connections > Keyword data (provider, key saved encrypted and never shown again, monthly budget, key and balance check). Everything works without a provider.
- Opportunities show searches a month, cost per click, a 12-month trend and the clicks a search could bring at position 3 (`Keywords\Volumes`); "Get search volumes" shows the estimate first and runs in the job queue; cached volumes (30 days) cost nothing.
- Keywords & Topics > Keyword ideas (`Keywords\Keyword_Ideas`): from a seed word or a page's main search, with volume, cost per click, potential clicks and whether you already appear; "Add to Topic Finder" and "Write a page" in AI Writer. The Keyword Map tabs arrive with S11 through `qcld_wpbot_seo.keywords.tabs`.
- AI Writer briefs from opportunities include the monthly searches when known.
- A failing keyword key or empty balance shows the same bold notice as a failing AI key; the dashboard API reports `connections.keyword_data`.
- Prospects can come from backlink data (source `data`, badge "From backlink data"); `VolumeLookup` shared with extensions through `window.qcldWpbotSeoUI`.
- REST `/keywords/status`, `/keywords/estimate`, `/keywords/volumes`, `/keywords/ideas`, `/keywords/ideas/add`, `/keywords/ideas/write`; filter `qcld_wpbot_seo/keyword_lookup_max` (200 in Free).

## 0.8.0 (S10 Link building)
- Links area with five tabs (Link Monitor, Prospects, Outreach, Exchange Records, Results), within Google's spam policies: no buying links, no link schemes, paid links marked sponsored.
- Link monitor (`Links\Link_Store`, table `qcld_wpbot_seo_links`): Search Console Links CSV import (`Links\Csv_Import`: linking pages become known links; "Top linking sites", "Top linked pages" and "Top linking text" kept as summaries), Bing Webmaster Tools link counts and linking pages of the most linked pages (`Links\Bing_Client`, owner's key), Site Insight referrals (`Links\Referrals`), links added by hand.
- Polite link checks in the job queue (`Links\Link_Checker`, 5 links per step, robots.txt respected through `Links\Robots`, private addresses refused): live, nofollow/sponsored/ugc, anchor text, whether your page still works; status lost or changed; action `qcld_wpbot_seo/link_status_changed`.
- Prospects (`Links\Prospects`, table `qcld_wpbot_seo_prospects`) by kind, added by hand; competitors left out (filter `qcld_wpbot_seo/competitor_hosts`); "Start outreach" with the matching type; linkable content from your own pages and a "Write it in AI Writer" idea; local citations checklist per country and business type with a name, address and phone check (`Links\Citations`, filter `qcld_wpbot_seo/citation_directories`).
- Outreach (`Links\Outreach`, tables `qcld_wpbot_seo_outreach` and `qcld_wpbot_seo_outreach_steps`): link request, guest post and collaboration with their own status steps, notes, follow-up dates (badge on Links) and history; a won link moves into the monitor and is checked automatically; a collaboration's reciprocal link goes into exchange records; guest-post rules, anchor and pacing warnings (`Links\Link_Guard`).
- AI drafts in the brand voice with a "Make it yours" question (`Links\Outreach_Drafts`); article ideas for guest posts and collaborations; guest articles and partner content open in AI Writer; the contact's name and email never go to the AI; a signature identifies the sender; copy or open in your own email. No email is sent by the core module.
- Exchange records (`Links\Exchanges`, table `qcld_wpbot_seo_exchanges`): both sides recorded and checked on demand, history, sponsored prompt when something was paid, guardrail warnings; action `qcld_wpbot_seo/exchange_checked`. No marketplace or partner search.
- Results (`Links\Links_Report`): links earned per month by type, guest posts published, collaborations live, visits per linking site and partner (Site Insight), linked pages' Search Console clicks and position 28 days before and after.
- Privacy exporter and eraser for outreach contacts. JS filters `qcld_wpbot_seo.links.tabs`, `qcld_wpbot_seo.outreach.actions`, `qcld_wpbot_seo.prospects.finders`, `qcld_wpbot_seo.links.results`; PHP filter `qcld_wpbot_seo/outreach_sending`; `Outreach::mark_sent()`, `sent_since()`, `earned_since()` for the Pro module.
- Exchange checks remember the partner's link type seen first, so a later change to nofollow or sponsored shows as broken.
- Uninstall (owner opt-in) also removes the Links options and Pro's link watch event and state.

## 0.7.0 (S7 extension points for Pro automation)
- Action `qcld_wpbot_seo/admin_enqueue` after the admin app's scripts load, so extensions can add their own bundle.
- JS filters `qcld_wpbot_seo.fixes.panels` (extra panels on Fixes) and `qcld_wpbot_seo.technical.tabs` (extra Technical SEO tabs, each with a `render` function).
- `Seo_Bridge::set()` accepts a `group` argument, stored as the change's group reference, so a batch of fixes can be listed and undone together.
- Uninstall (owner opt-in) also removes the Pro module's table, options and scheduled scan, since the Pro module is not loaded at uninstall.

## 0.6.0 (S6 Results and reports)
- Before-and-after results for every change (`Results\Results`): clicks, impressions, click-through rate, position (Search Console) and conversions (Analytics when connected), each checkpoint (14, 28, 90 days by default) compared with the same number of days before the change; filled daily once Search Console has the data; undone changes are not measured.
- Results & Reports screen: summary (changes in 30 days, improved after 28 days, clicks gained), every change with its results, weekly email preview and test.
- Weekly email summary (`Reports\Weekly_Email`): the week's numbers, new opportunities, results of changes, biggest movers, site health; sent only when there is news and at most once a week; recipients and day in Settings; a footer line from Free hosts (`get_email_footer()`) the owner can hide; sections from other modules through `qcld_wpbot_seo/report_sections`.
- Growth Dashboard data now includes the results summary.

## 0.5.0 (S5 AI readiness and local)
- AI search readiness per page (`Readiness\Readiness_Checks`): answer in the first lines, short summary, FAQ where useful, structured data, clear business and place names, author and date, AI search crawlers allowed; score per page; content items open AI Writer's Improve flow, schema and crawler items open their screens.
- AI crawler rules (`Readiness\Ai_Crawlers`, filter `qcld_wpbot_seo/ai_crawlers`): allow or block GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, Google-Extended, PerplexityBot, Applebot-Extended, CCBot, meta-externalagent, Bytespider through WordPress's robots.txt, or lines to paste when a physical robots.txt exists; what the current robots.txt blocks is shown.
- Local SEO (`Local\Locations`, table `qcld_wpbot_seo_locations`): one business location with type, address, phone, email, service area, opening hours and its page; LocalBusiness schema on the front page and the location page (only when no SEO plugin prints schema); checks for details, hours, schema, the location page, and the same phone number across the site.
- AI Search screen (Readiness, AI crawlers, Visibility tab) and Local SEO screen.

## 0.4.0 (S4 Technical SEO)
- Technical scan in the job queue (`Technical\Scan`): pages fetched from the site itself in small batches (setting) with a WPBot User-Agent, redirects followed by hand; falls back to reading pages from the database when the host blocks loopback requests, and the admin's browser can then check pages for full results. Runs under WP-Cron alone.
- Checks (`Technical\Issue_Builder`, filter `qcld_wpbot_seo/technical_issues`): pages with errors, noindex, URL Inspection for the top pages, robots.txt blocking, XML sitemap and Search Console sitemaps, broken internal and outbound links (polite HEAD checks, capped), redirect chains and loops, links to redirects, HTTP to HTTPS, canonicals, duplicate titles and descriptions, orphan and deep pages (link graph from the home page), structured data required fields, mixed content, hreflang, slow Core Web Vitals; health score; action `qcld_wpbot_seo/scan_completed`.
- 404 log (no IP addresses or user agents; kept for the set days), simple redirects (301, 302, 410; only when no SEO or redirect plugin handles them; loops refused; cached handler; hits), basic schema (Organization, WebSite, BlogPosting or WebPage, BreadcrumbList) only when no SEO plugin prints schema.
- PageSpeed Insights checks with lab score and Core Web Vitals field data (owner's optional key).
- Technical SEO screen (report by category with examples and fixes, one-click "Remove noindex", Add a redirect, 404 log, redirects, speed); health score on the Overview and the dashboard API.

## 0.3.0 (S3 On-page fixes)
- Page checks (`Onpage\Checks`, filter `qcld_wpbot_seo/issue_checks`): search title length and main search, description, duplicate titles and descriptions, thin content, headings, image alt text, internal links out and in (orphans), FAQ or summary on long pages, readability; score out of 100.
- Audit on publish or update (on by default, no AI, after the response), site-wide check in the job queue, Fixes screen grouped by impact with one page's issues, fields and changes.
- Fix with AI: search title and description proposed with the module's AI key, reviewed before saving; content issues open AI Writer's Improve flow with the issue filled in.
- SEO plugin bridge (`Seo\Seo_Bridge`): title and description through AI Writer's adapter (request R-1), focus keyword, noindex and canonical for Yoast, Rank Math and All in One SEO; WPBot's own fields with no SEO plugin (`Seo\Head_Output` prints them once).
- Change log (`qcld_wpbot_seo_changes`, action `qcld_wpbot_seo/fix_applied`) with undo for search fields and links; undo refused when the field changed since.
- Auto meta for empty fields on publish (off by default, background job, never overwrites, skips AI Writer posts).
- Internal link suggestions (links out and in, anchors found in the text), added one by one as normal links, undo removes them.
- Block editor panel with the score and top issues, pre-publish advice that never blocks publishing, Classic Editor box.

## 0.2.0 (S2 Overview and opportunities)
- Google Analytics 4 import (optional): sessions, engaged sessions, key events and revenue per landing page; the last 90 days once, then nightly; starts when the property is chosen.
- Opportunities: near page one, low click-through (against an expected click-through curve by position), missing page, poor engagement, decaying page; ranked by clicks to win, effort and business relevance (`qcld_wpbot_seo/opportunity_relevance`); rebuilt after every Search Console or Analytics update; dismissed ones stay dismissed. Extension types through `qcld_wpbot_seo/opportunity_types`.
- Actions into AI Writer: Improve this page, Write a new page, Refresh, Rewrite title and description (`qcld_wpbot_writer_open_brief_url()` with the fix, numbers and main search), Add to calendar (saved as an AI Writer idea); opportunities are a Topic Finder source (`qcld_wpbot_writer/idea_sources`).
- Overview: top 5 actions this week, connections, biggest movers; Opportunities screen with type filter, dismissed list and restore.
- REST `/opportunities`, `/opportunities/refresh`, `/opportunities/{id}/dismiss|restore|act`.

## 0.1.0 (S1 Foundations)
- Module scaffold in the AI Writer pattern: newest-copy loader (needs WPBot Core 0.6.0 with the SEO additions), host adapter (`get_menu_after`, `get_mailer`), feature registry, schema (`qcld_wpbot_seo_db_version`), settings (`qcld_wpbot_seo_settings`), encrypted service keys, REST `qcld-wpbot-seo/v1` (`/settings`, `/secrets`, `/analytics/*`, `/status`, `/overview`, `/pull`, `/dashboard`), permissions section `seo`.
- Search Console import: 16 months in week-long job steps (pages, searches, searches per page, site totals), then a nightly top-up of the last ten days; storage limit; daily aggregates in `qcld_wpbot_seo_gsc_daily` with URL and query dictionaries.
- Admin page in the shared WPBot shell with all ten sections, connect cards instead of empty screens, Overview with totals, trend and top pages and searches, Settings (Google guide for Search Console and Analytics, PageSpeed and Bing keys, AI keys, data and scans, general).
- Takes over the old SEO Analyzer's front-end output (description and Open Graph tags, FAQ schema, box styles) without a second description tag next to AI Writer's and without restyling every Details block.
- Public API: `qcld_wpbot_seo_get_page_data()`, `qcld_wpbot_seo_get_dashboard_data()`.
