# Changelog

## Unreleased (Permissions: no Menu Access link)
- Permissions: areas that are not on the grid yet no longer link to `admin.php?page=wpbot-menu-capabilities`. That old Menu Access page has been replaced by Permissions, and WPBot Free never had it, so every "Open Menu Access" link went to the wrong page. They now say "Only administrators can change access to this area for now. It moves here when the module is updated."




## Unreleased (Business Context: how to start)
- Overview: two clear ways to start instead of one button at the top. **Let AI write it** says what AI reads ("40 pages and 5 other data sources", no typing needed first) and, without an AI key, says so with a "Set up AI" link; with nothing to read, it says to publish pages or connect a source. **Write it yourself** opens Your business. Once there is a summary: "Check my summary" and "Update it from my website with AI".
- Your business: the AI button shows only before there is a summary, as a short hint.
- Documents: says that AI must be set up first when it is not.
- The website source reads "40 pages" (products only when there are some).

## Unreleased (Business Context layout)
- **WPBot > Business Context** now uses the shared WPBot layout (`Admin\Shell`: the host's header and a left menu with the purple highlight, like the ChatBot and AI Marketing pages), split into short sections: Overview (explanation, "Build with AI", progress, "Used by"), Your business, Facts & rules, Customer questions, Documents, Data sources and Settings. Each section has its own address (`&tab=`); the menu switches in place.
  - Lists (products, strengths, questions, words, facts, never say) are tag inputs; one "Save changes" bar on the editing sections.
  - Data sources: missing ones first as cards with their set-up button; sources in use fold into one line. Documents: summaries open on demand.
  - Buttons and switches in the host's brand colour.

## Unreleased (Business Context)
- **WPBot > Business Context** (`Context\Business_Context_Page`), after AI Settings: one shared screen where the owner tells WPBot about the business once, for every AI feature. Explanation at the top; the summary is built with AI (cost shown first, using the first module whose AI is ready, filter `qcld_wpbot_core/context_ai_modules`), then edited and saved. The Site Context data (option `qcld_wpbot_core_context`) carries over.
  - New summary fields **Facts to always get right** and **Never say**: in every prompt (`Context::text()`), kept when the AI builds the summary again.
  - **Data you already have** (`Context\Context_Sources`): website, WooCommerce products, chatbot knowledge base, customer chat questions, competitors, Brand & Business, plus what modules add (filter `qcld_wpbot_core/context_sources`). A missing source says why it helps and links to where it is set up. Filter `qcld_wpbot_core/context_brand_url` for the Brand & Business link.
  - Permission row **Business Context** (section `context`; View, Use, Manage settings).
  - REST `wpbot-core/v1/context` (`/scan`, `/summarize`, `/save`, `/settings`, `/knowledge`).
  - Plain JS on `wp.element` (`assets/js/business-context.js`), no build step.
- **Fill-in notice** (`Context\Context_Notice`) on AI Marketing, AI Writer, SEO & AI Search and Social Media (filter `qcld_wpbot_core/context_notice_pages`) until the context is saved once; "Not now" hides it per user (user meta removed on uninstall).
- **Your documents** (`Context\Context_Documents`): upload up to 10 files of 10 MB (PDF, DOCX, TXT, MD, CSV). Each is read once with the shared readers (`Library\Document_Text`; a PDF is sent to the AI provider when it reads PDFs), summarised by AI (cost shown first; personal data removed from text before the call), and the file is deleted. The summary is shown and editable, feeds the business summary (it becomes out of date, so the screen offers to build it again) and every prompt (`Context::text()`). Option `qcld_wpbot_core_context_docs`, removed on uninstall. Routes `/context/docs` (upload), `/context/docs/{id}` (edit), `/summarize`, `/delete`.
- `Context::saved()`, `Context::ai_module()`.
- Competitor suggestions read the business context.
## Unreleased (CR-15: imports for Brand & Business)
- `Library\Page_Reader::read( $url )`: reads one page the owner named. It respects robots.txt (rules for WPBot, then `*`; cached a day), refuses private addresses (`wp_safe_remote_get`), and applies a 15 s timeout, a 2 MB cap and a User-Agent that names WPBot. It returns the title and readable text (scripts, menus, forms, header and footer removed; 40,000 characters at most). Filter `qcld_wpbot_core/page_reader_args`. `Page_Reader::html_to_text()` and `clean_text()` are public.
- `Library\Document_Text::read( $path, $name )`: text from .docx (PHP zip), .txt, .md and .csv (Windows-1252 converted to UTF-8), with no new library.
- AI requests carry **PDF documents** like images (`application/pdf`, up to 10 MB), sent in each provider's own format. `Providers::reads_pdf( $id )` lists OpenAI, Claude, Gemini and OpenRouter (filter `qcld_wpbot_core/providers_reading_pdf`).
- `qcld_wpbot_core_voc_add( $phrases, $source = 'import' )`: adds approved phrases to the voice-of-customer bank (redacted; repeats raise the weight). New source "Imported from your content".
- Unit test `ImportReadersTest`.

## Unreleased (Business Profile, round 3 I)
- **Google Business Profile** (`Connections\Business_Profile`), through the owner's Google connection:
  - accounts and locations (cached an hour);
  - a location's profile: name, address, phone, website, hours, special hours, category, place ID;
  - reviews, and a reply posted only on the owner's click (`reply()`);
  - performance: calls, website clicks, direction requests, views; and the searches that found the profile.
  - `status()` is off, not_connected, reconnect, pending or connected. "Pending" means Google hasn't given the owner's Cloud project API access yet; `explain()` gives the access-request link.
- **Scope:** `business.manage` (no read-only scope exists) is asked for only when a module turns Business Profile on (`qcld_wpbot_core/business_profile_enabled`), like the Google Ads exception.
- **Google:**
  - new endpoint names `gbpaccount`, `gbpinfo`, `gbpreviews`, `gbpperf`;
  - `Google::request( $method, ... )` for PUT (`api()` now calls it);
  - Business Profile errors never flag the Search Console connection.
- `review_link( $place_id )`.

- **Google's organic results** as an optional keyword operation: `Keyword_Data::research( 'serp', $search )`, with position, address, title and domain.
  - DataForSEO implements it (`serp()`, Live Regular, priced at $0.002 a search in `qcld_wpbot_core/keyword_prices`); other providers say it isn't offered.
  - Same budget, spend and health checks as other paid lookups; cached 30 days; never stored as keyword volumes.

## Unreleased (free data, round 3 B)
- **Bing (free) keyword data** (`Keywords\Providers\Bing_Free`):
  - weekly Bing impressions (GetKeywordStats) become "Bing searches a month" and a monthly trend;
  - related searches come from GetRelatedKeywords;
  - free, never counted as paid, labelled "Bing (free)".
  - `Keyword_Keys::for_op()` falls back to it for volumes and ideas when no paid provider and no Keyword Planner is set (`Keyword_Keys::free()`, filter `qcld_wpbot_core/free_keyword_data`).
  - WPBot Core keeps no Bing key: a module supplies the call through `qcld_wpbot_core/bing_webmaster_call`.
- **YouTube search** (`Connections\YouTube`, `qcld_wpbot_core_youtube_search()`):
  - videos for a search, with channel, date and views, through the owner's Google connection (read-only), cached 7 days;
  - the permission is asked for only when a module turns it on (`qcld_wpbot_core/youtube_enabled`);
  - quota and "API not enabled" errors are explained.
- **Google:** errors keep Google's own reason (`reason` in the error data). `Google::api()` takes an optional fourth argument `$mark` (default true); optional APIs pass false, so a refusal there never flags the Search Console connection.
- PHPUnit: `BingFreeTest`.

## Unreleased (context)
- **Site Context** (`Context\Context`, additive, no version bump). One shared, short profile of the business for every module, built in layers:
  1. **site scan** (`Site_Scan`): database only, no AI;
  2. **facts modules add** (filter `qcld_wpbot_core/context_facts`), plus the shared competitor list, the voice of customer bank (`qcld_wpbot_core_voice_of_customer()`) and, when it exists, AI Writer's planned Business Profile (`qcld_wpbot_core_profile`, text fields only; owner facts win);
  3. **the chatbot's knowledge base** (`Knowledge`, through the host's existing `get_knowledge_search` / `get_knowledge_writer`; "build" is a job, step `core_context_knowledge_add`);
  4. **customer questions** from chat sessions (`Customer_Questions`, opt-in, through `Chat_Source`, redacted);
  5. **AI summary** (`summarize( $module )`, feature `context_summary`), which includes 3–5 focus ideas, each with its source.
- Owner edits are kept, and the weekly refresh never overwrites them. A switched-off layer is forgotten at once.
- Public functions: `qcld_wpbot_core_context()`, `qcld_wpbot_core_context_text()`, `qcld_wpbot_core_context_refresh( $module )`, `qcld_wpbot_core_context_post_ids()`.
- Action `qcld_wpbot_core/context_changed`; filter `qcld_wpbot_core/context_important_posts`.
- Option `qcld_wpbot_core_context` (autoload off) and cron `qcld_wpbot_core_context_weekly`, both removed on uninstall.
- PHPUnit: `ContextTest`.

## 0.15.0 (round 3, P4: Experiments service)
- **Experiments service** (`src/Experiments/`): one engine for A/B tests across modules. Tables `qcld_wpbot_core_experiments`, `_experiment_variants`, `_experiment_stats` (daily counts per variant and metric, no personal data) and `_technique_results`; schema version 3.
- Methods: split per visitor (sticky), before and after (search titles; labelled "Less reliable"), by time or channel (labelled "Indicator"). One running test per page.
- **Cache-safe splitting:** every variant is in the page, hidden by CSS; a small inline script in `<head>` picks the visitor's variant before the body is painted (no flicker). The control shows without JavaScript.
- Suggested privacy policy text (Settings → Privacy) describes the `qcx_a` cookie.
- **Consent** follows Site Insight's consent mode (filter `qcld_wpbot_core/experiments_consent_mode`) and the WP Consent API. Without consent nothing is stored or counted.
- **Measurement:** a public beacon `wpbot-core/v1/experiments/beacon` (views, impressions, clicks, calls to action, scroll, engaged time; running tests only; rate limited), WooCommerce add to cart and orders, and leads through `do_action( 'qcld_wpbot_core/experiment_goal', 'leads', $post_id )`. Other sources use the filter `qcld_wpbot_core/experiment_counts`.
- **Reliability:** a two-proportion z-test against the control, with the level split between versions (Bonferroni); a minimum sample per version; a duration estimate from traffic; "Not enough data yet", "Not enough traffic" and "No clear difference" in plain words. Never a fake winner. Each test has a maximum run time; the winner is applied automatically or by click.
- **Learning:** wins, losses and ties per technique, kind of copy and reader stage.
- **Time and channel tests** (social versions): tracked links `qcld_wpbot_core_experiment_link()` (`?qcx_s=` counts `link_clicks`, nothing stored about the visitor) and an "Indicator" assessment that names the version ahead once enough clicks are in, never a winner, and is never applied automatically.
- When a test starts or ends, the tested pages' post cache is cleaned and `qcld_wpbot_core/experiments_changed` fires, so page caches can purge their copies.
- API: `qcld_wpbot_core_experiment_create/start/stop/apply/record/markup/estimate`, `qcld_wpbot_core_experiment()`, `qcld_wpbot_core_experiments()`, `qcld_wpbot_core_experiments_running()`, `qcld_wpbot_core_technique_results()`, `qcld_wpbot_core_technique_winners()`. Actions `qcld_wpbot_core/experiment_apply` and `qcld_wpbot_core/experiment_switch`. REST `experiments`, `experiments/{id}`, `/stop`, `/apply`, `experiments/learning` (module-scoped).

## 0.14.0 (AI Writer round 3, P1: persuasion foundations)
- **Persuasion Kit** (`Persuasion\Kit`, table `qcld_wpbot_core_kit`, schema 2): the owner's real proof in 13 types (review, testimonial, customer count, case study, certification or award, media mention, years in business, free offer, guarantee or return policy, offer or deadline, founder story, values, local identity). Reviews and testimonials carry "permission to quote"; an offer needs its end date and is never returned for writing after it; live WooCommerce price, regular price, sale end and stock per product. API `qcld_wpbot_core_persuasion_kit()`, `qcld_wpbot_core_kit_save()`, `qcld_wpbot_core_kit_delete()`; action `qcld_wpbot_core/kit_changed`.
- **Technique library** (`Persuasion\Techniques`): one PHP data file per technique in `src/Persuasion/techniques/` (29 entries, including "No specific technique"), with plain label, tooltip method name, "why this works", where it fits and must not be used, AI instructions, examples, proof needed and edition. Schema validation refuses NLP, hypnosis, subliminal and fake fear-of-missing-out instructions and trademarks in labels. Filter `qcld_wpbot_core/techniques`. API `qcld_wpbot_core_techniques()`, `qcld_wpbot_core_technique()`. **Reader stages** (`Persuasion\Stages`, five awareness levels) with mapping from search intent and content type: `qcld_wpbot_core_reader_stages()`.
- **Voice of customer** (`Persuasion\Voice_Of_Customer`, table `qcld_wpbot_core_voc`): sources by filter `qcld_wpbot_core/voc_sources` (WooCommerce reviews and kit reviews here; modules add more), every text redacted with `Privacy\Redactor` before storing and again in the AI grouping request; background job steps `core_voc_collect` and `core_voc_group` (grouping only on request, with an estimate). API `qcld_wpbot_core_voice_of_customer()`, `qcld_wpbot_core_voc_refresh()`.
- **Honesty checks** (`Persuasion\Honesty`, `qcld_wpbot_core_honesty_check()`): invented proof, fake urgency, expired deadline, price mismatch, quote without permission, engagement bait.
- REST `wpbot-core/v1/kit`, `kit/{id}`, `techniques`, `voc`, `voc/refresh`, `voc/{id}` (module-scoped levels).
- Uninstall drops the two new tables and `qcld_wpbot_core_voc_state` (with the existing opt-in rule).
- PHPUnit: `PersuasionKitTest`, `TechniqueLibraryTest`, `HonestyTest`, `VoiceOfCustomerTest`.

## Unreleased (competitor-focus)
- **Competitor suggestions take the owner's brief** (`Competitors\Suggest`, `qcld_wpbot_core_suggest_competitors()`). Two optional arguments, additive:
  - `focus`: the product, service or topic to compete on (plain text, up to 120 characters);
  - `kind`: `any` (default), `local`, `online` or `content`.
  - Both go into the AI prompt, and the result echoes them back as `focus` and `kind`. Without them, suggestions work exactly as before (AI Writer is unaffected).
- New helpers `Suggest::focus()`, `Suggest::kind()`, `Suggest::brief()` and the constant `Suggest::KINDS`. PHPUnit: `CompetitorsTest::test_brief`.

## Unreleased (google-ads-no-developer-token)
- **No developer token** (`Connections\Google_Ads`). Google retired developer tokens on 9 September 2026; API access now belongs to the Google Cloud project of the owner's OAuth app.
  - The connection is on with just the customer ID (and the manager ID if used).
  - No `developer-token` header is sent: Google ignores it now and will reject it in a future API version.
  - A stored token is deleted at the next save, and `status()` no longer returns `token_hint`.
- **Clearer access errors:**
  - Test access only (`CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION`, or `ACTION_NOT_PERMITTED` before v25): apply for Explorer access.
  - Keyword Planner refused: Basic or Standard access is needed, after brand verification.
  - Both point to the project's Google Ads API Overview page in Google Cloud Console.

## 0.13.0 (AI Writer round 2: images on AI requests)
This version also carries the SEO & AI Search round 2 and uninstall changes listed under "Unreleased" below (merged into `dev` before it).

- **Images on AI requests.** `Request::make()` takes an optional `images` argument (up to 4; PNG, JPEG, WebP or GIF; base64; 5 MB each; anything else is left out). They go with the last user message in each format: OpenAI and OpenRouter (`image_url` data URLs; `input_image` on the Responses API), Anthropic (`image` blocks with a base64 source) and Gemini (`inline_data`). Requests without images are sent exactly as before and keep their cache fingerprint. Used by AI Writer Pro to read a pasted ad screenshot. Unit test: `RequestImagesTest`.

## Unreleased (uninstall fix)
- **Uninstall:** with "Delete data on uninstall" on and no host left, the keyword map and topic tables (`qcld_wpbot_core_keyword_map`, `qcld_wpbot_core_topics`) are now dropped with the others. A unit test checks the drop list against `Schema::all_tables()` and `Seo_Schema::all_tables()`.

## Unreleased (seo round 2)
- **Google Keyword Planner (R5).**
  - **Connector:** `Connections\Google_Ads::planner()` calls only `generateKeywordIdeas` and `generateKeywordHistoricalMetrics`. These are read-only planning data; nothing in the Ads account changes.
    - The reporting allow-list adds Google's `geo_target_constant` and `language_constant` lookup lists.
    - One shared, quota-counted request helper.
    - A clear message when the developer token lacks Basic or Standard access. Google's access-levels page lists Keyword Planner among the services Explorer access cannot use.
  - **Provider** (`Keywords\Providers\Keyword_Planner`, id `google_ads_planner`):
    - Supplies the volume (12-month average), a 12-month trend (oldest first), competition (level and 0–100 index as 0–1), the top-of-page bid range (low and high) and the average CPC.
    - Close variants share their keyword's numbers.
    - Ideas come from a seed keyword. Ideas for a site come from Google's site seed and are marked `site_ideas`, with no position: they are not rankings.
    - No backlinks, no cost per keyword. Places and languages are looked up once and cached (`qcld_wpbot_core_ads_constants`).
  - **Source per operation** (`Keyword_Keys::for_op()`):
    - Keyword Planner supplies volumes and ideas when it is turned on (filter `qcld_wpbot_core/keyword_planner_enabled`, set by SEO & AI Search Pro) and Google Ads is connected.
    - The paid provider supplies domain keywords (rankings) and backlinks.
    - The paid budget, balance and health never apply to Keyword Planner.
    - `qcld_wpbot_core_keyword_provider()` now describes the volumes source and adds `planner`, `rankings` and `backlinks`.
  - **Keyword data and Vault columns** (additive): `competition_level`, `bid_low` and `bid_high` on keyword data; `trend`, `competition`, `bid_low` and `bid_high` on the Vault.
    - Vault filters: `trend`, `competition` and `min_volume`. Vault order: `bid_high`. `google_ads_planner` counts as a real source.
  - `Keyword_Data::forget()` drops cached numbers, so monthly refreshes ask again.
  - `Keyword_Data::trend_direction()` returns rising, steady or falling from the last three months against the first three.
- **Shared competitor list (R2), `Competitors\Competitors`.**
  - Stored in option `qcld_wpbot_core_competitors` (autoload off, removed on uninstall). Up to 20 domains, each with `domain`, `reason`, `source` and `added_at`.
  - Domains are cleaned from URLs or hosts. IP addresses, localhost and the site itself are refused.
  - Action `qcld_wpbot_core/competitors_changed`.
- **AI competitor suggestions, `Competitors\Suggest`.**
  - The module's AI names direct competitors, using web search when the model can.
  - Filter `qcld_wpbot_core/competitor_candidates` adds more candidates; filter `qcld_wpbot_core/competitor_context` adds facts about the site (no personal data).
  - Every candidate is verified before it is shown:
    - Marketplaces, directories, social networks and large general sites are dropped (filter `qcld_wpbot_core/competitor_skip_hosts`).
    - The site must load with status 200–399 on its own domain. The home pages are checked in parallel, with an 8-second timeout, a safe URL check and the WordPress proxy settings.
  - Filter `qcld_wpbot_core/competitor_check_url` sets the address that is checked (used by the tests).
  - Nothing is saved until the owner saves.
- **Public functions:** `qcld_wpbot_core_competitors()`, `qcld_wpbot_core_suggest_competitors( $args )` and `qcld_wpbot_core_save_competitors( $items )`. Callers check the user's permission before saving.

## Unreleased (ai-setup-clarity)
- Copied keys arrive turned on: when the host reports every copied provider as off (WPBot Pro's own switches), the host's active provider, or else the first copied provider with a key, becomes the primary with the host's model or the provider's default. Other copied providers keep their keys and stay off. "Copy again" never changes a set the owner saved.
- `owner_saved` flag per module set (set by `Keys::update()`); `Keys::repair_unsaved()` runs once on `admin_init` (option `qcld_wpbot_core_ai_setup_repaired`) and turns a provider on in copied, never-saved sets that ended up with none. Owner-saved sets are never changed.
- `Keys::public_view()` adds `status` per provider (`ready`, `off`, `failing`, `no_key`), `status_reason`, `owner_saved` and `imported_summary` ("Copied from WPBot Pro > AI Settings on {date}. {Provider} is on as your main AI."). Every existing field is unchanged.
- New `AI\AI_Readiness::check( $module )` and `qcld_wpbot_core_ai_readiness( $module )`: `status` (`ready`, `no_key`, `not_enabled`, `no_model`, `failing`, `budget_reached`), `cause`, `message`, `action` (`set_up`, `turn_on`, `pick_model`, `update_key`, `add_funds`, `raise_budget`, `wait`), `action_label`, `provider`, `url` (the module's AI settings page, filter `qcld_wpbot_core/ai_settings_url`) and `warning` (the backup is answering for a failing primary). REST `GET /wpbot-core/v1/ai-readiness/{module}` (View level); the keys view includes `readiness`.
- Shared notice: `AI_Readiness::notice_html()` / `admin_notice()` for PHP screens, and the script `qcld-wpbot-core-ai-readiness` (plain JS on `wp.element`, registered on `admin_enqueue_scripts`) exposing `window.qcldWpbotCore.AiReadyNotice` and `window.qcldWpbotCore.aiDisabledReason`, with the style of the same handle.
## Unreleased (seo-followups)
- Google Ads, read only (S13, `Connections\Google_Ads`): the owner's developer token (encrypted, masked), customer id and optional manager id; the `adwords` scope (Google Ads has no read-only scope) joins the shared Google connection only after the owner turns the connection on; the only call is the reporting method `googleAds:search` with a SELECT on `search_term_view`, `keyword_view`, `customer` or `campaign`; quota counter `google_ads` (2,800 a day, under Explorer access's 2,880). Filters `qcld_wpbot_core/google_ads_api_version` (default v26; v21 is retired) and `qcld_wpbot_core/google_endpoint` (`googleads`).
- Keyword Vault (S14): table `qcld_wpbot_core_vault` on the SEO migration track; `Vault\Vault` with statuses researched, targeted, ranking (real rank data only) and archived; numbers kept only with a real source; targeted keywords without a page are planned entries in the shared keyword map (`vault-{id}`). Public API `qcld_wpbot_core_vault_query/get/add/update/delete`, actions `qcld_wpbot_core/vault_changed` and `qcld_wpbot_core/vault_rank_changed`.

## Unreleased (seo-ai-search)
Additive changes from the SEO & AI Search build (branch `feature/seo-ai-search`); the version constant is unchanged, the AI Writer build owns releases.
- Google connection: scopes list with the filter `qcld_wpbot_core/google_scopes` (read-only scopes only), granted scopes recorded, `has_scope()` and `missing_scopes()` so a module can ask the owner to reconnect, `api()` takes an endpoint base (`searchconsole`, `analyticsdata`, `analyticsadmin`), Google Analytics property saved with the connection.
- `Connections\Search_Console`: `rows()` (paging with `startRow`, dimension filters, data state, search type; not cached), `sitemaps()`, `inspect()` (URL Inspection).
- `Connections\Analytics`: GA4 properties, key events and Data API reports (read-only).
- `Connections\Quota`: per-API request counters per day and minute (filter `qcld_wpbot_core/quota_limits`); jobs get a retryable error before a limit.
- `Storage\Seo_Schema`: own migration track (`qcld_wpbot_core_seo_db_version`); table `qcld_wpbot_core_keyword_data`.
- Keyword data on the owner's own key (`Keywords\`): provider interface with DataForSEO (Google Ads volumes, ideas, ranked keywords, backlinks; "login:password"), Keywords Everywhere (credits) and Semrush (API units); filters `qcld_wpbot_core/keyword_providers`, `qcld_wpbot_core/keyword_prices`, `qcld_wpbot_core/keyword_endpoint`.
- `Keywords\Keyword_Keys` (option `qcld_wpbot_core_keyword_keys`, autoload off): provider, encrypted key (masked, never returned), monthly budget and spend, balance, health; bold notice for a refused key or empty balance in the same shape as AI key notices.
- `Keywords\Keyword_Data`: cached reads (never cost anything), estimates before each lookup, 30-day cache per keyword, country and language (keywords without data cached too), monthly budget, batched lookups in the job queue (step `core_keyword_lookup`), ideas, domain keywords and backlinks (cached 30 and 7 days); `Keywords\Countries` (Google Ads location codes; default from the site locale).
- Public API: `qcld_wpbot_core_keyword_data( $keywords, $args )` (cached data for AI Writer) and `qcld_wpbot_core_keyword_provider()`. REST `/wpbot-core/v1/keyword-data` and `/keyword-data/balance` (Settings level of the calling module).

## 0.12.0 (CR-2: one primary and one secondary AI provider)
- `AI\Keys`: at most two providers are on per module, a primary (`active`) and an optional `secondary` that is tried only when the primary fails (bad key, no credit, rate limit, outage, as before). Every other provider keeps its key and model but stays off. `call_order()` returns at most `[primary, secondary]`.
- `Keys::update()` takes `secondary`. Turning a provider on fills the primary, then the secondary; a third is refused with `qcld_wpbot_core_two_providers` (HTTP 400) and nothing is saved. Turning the primary off promotes the secondary. A provider switched off in the same change is never made primary or secondary. `fallback` is now derived (true when a secondary is set); `fallback => false` removes the secondary.
- Existing sites are converted on first read: the main provider stays primary; if fallback was on, the first other enabled provider becomes secondary; the rest are switched off with keys kept. Copying keys from the host again keeps the owner's choices.
- `public_view()` and `GET /wpbot-core/v1/keys/{module}` return `secondary`; `POST` accepts it.
- The key test (`POST /keys/{module}/test`) can check a provider that is off, so a key can be tested before it is chosen.

## 0.11.0 (M13 Authority and reach: shared map read API finalised)
- `Map\Topic_Map`: `all()`, `children()`, `delete()` (owner action; pages lose the topic, subtopics move up), `coverage_for()` (covered: 3+ live pages or a live pillar and one more; thin: 1-2; planned: drafts or planned pages only; missing: nothing), `refresh_coverage()` (skips locked topics), `links_to()`, `missing_links()` (pillar to every live page of the topic and back).
- `Map\Keyword_Map`: `set_topic()` (automatic moves keep manual or locked pages; a manual move marks the page manual, so a regroup keeps it), `unmapped_posts()` (published pages with no main query, for a backfill).
- Fix: `Topic_Map::pages()` and the page counts in `topics()` count main entries only (supporting searches were counted as extra pages).

## 0.10.0 (shared SEO adapter, request R-1 from SEO & AI Search)
- `Seo\Seo_Adapter`: `detect()` (Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, The SEO Framework; filter `qcld_wpbot_core/seo_plugin`), `active()` (writable: the first three), `label()`, `outputs_schema()`, `read_field()` / `write_field()` for title, description, focus keyword, noindex and canonical in each plugin's own storage (All in One SEO through its model when available), `write_meta()`.

## 0.9.0 (M11: web search)
- `Request` takes `web_search`; OpenAI uses the Responses API with the `web_search` tool, Anthropic Claude the `web_search` tool, Gemini `google_search` grounding and OpenRouter `:online` models. Cited pages come back in `Response::sources` (also cached). Calls that need web search use only providers that support it, or fail with `qcld_wpbot_core_ai_no_web_search` and a clear message.

## 0.8.0 (M8 Pro power)
- Job budgets: a job's budget can be held by its parent (a bulk job and its articles share one budget); `Job_Store::spent()`, `budget_holder()`, `set_budget()`, `pause_for_budget()`; the runner pauses before a step once the budget is reached; `POST /wpbot-core/v1/jobs/{id}/budget` raises it and resumes paused jobs (children included). `parent_id` on jobs.
- Steps run as the job's owner, so WP-Cron (no logged-in user) has that person's rights, checked again at run time. Fixes drafts failing when a bulk job ran with no browser open.

## 0.7.0 (CR-1: shared keyword and topic map, brief 7.10)
- Tables `qcld_wpbot_core_keyword_map` (page or planned page → main and supporting searches, topic, source ai/manual/search_console/title, locked) and `qcld_wpbot_core_topics` (name, parent, pillar page, coverage covered/thin/missing/planned, source, locked); forward-only.
- `Map\Keyword_Map`: set_post(), set_planned(), attach_planned(), remove_planned(), forget_post() (on deleted_post), main_query(), posts_for_query(), entries(), normalise(). Manual or locked entries are never overwritten by automatic updates. `Map\Topic_Map`: ensure(), get(), find(), topics() with page counts, pages(), update(). Actions `qcld_wpbot_core/keyword_map_changed`, `qcld_wpbot_core/topic_map_changed`.

## 0.6.0 (brief 2.4: admin layout)
- Shared admin shell `Admin\Shell` for every module: host page header, WordPress's notices marker, a left menu inside the page (white rounded panel, icon and label per item, the current item a filled pill, in the look of WPBot's AI Marketing screen) and the content area. Items are real links with their own URLs; a single-page app takes over clicks through the cancelable `wpbot-shell:navigate` event and the address and back button still work (`assets/js/shell.js`, `window.wpbotShell`). Count badges, inactive "Pro" items, visible keyboard focus, and a select menu on narrow screens. Colours from the host's brand values.

## 0.5.0 (M7 Visitor questions and Topic Finder)
- Shared Google connection through the owner's own OAuth client (`Connections\Google`): Client ID and encrypted secret, consent with a one-time state, encrypted tokens with refresh, revoke on disconnect, failing state for bold notices. Read-only Search Console scope.
- `Connections\Search_Console`: properties, property matching for this site, Search Analytics queries cached for a day.
- `Connections\YouTube_Key`: the owner's YouTube Data API key, encrypted and masked.
- `Privacy\Redactor`: removes emails, phone numbers, card numbers, street addresses, postcodes, links with personal paths and chat names (filter `qcld_wpbot_core/redact_patterns`); unit-tested with fixtures.
- REST `/connections/google`, `/connections/google/connect`, `/connections/google/disconnect`, `/connections/google/sites`, `/connections/youtube` (administrators).

## 0.4.0 (M4 Improve existing content)
- Content Library items keep backslashes in titles, content and data (input is slashed before `wp_insert_post()`, which unslashes it).

## 0.3.0 (M3 Edit-screen writing)
- Content Library items API (`Library\Items`): create, update, get, query and delete private items with revisions.

## 0.2.0 (M2 Write something new)
- Shared stock image keys (Pexels, Pixabay, Unsplash switch) in `qcld_wpbot_core_connections`, encrypted and masked; REST `/connections/stock` for administrators.
- Jobs waiting for owner input (payload `awaiting`) are not resumed by the generic Resume; progress reports `awaiting`.

## 0.1.0 (M1 Foundations)
- Newest-copy loader, host adapter contract, schema with forward-only signature upgrades.
- AI client for OpenAI, Anthropic Claude, Google Gemini, OpenRouter, xAI Grok, Mistral and custom OpenAI-compatible endpoints.
- Per-module keys encrypted at rest (libsodium), copied once from the host's main AI settings, masked in every response.
- Failing-key status with plain-language cause and fix; cleared after the next successful call.
- Usage log with cost estimates, monthly budgets per module, result cache.
- Job queue with atomic step claims, attempt IDs and expiring locks; browser runner (REST tick) and WP-Cron runner.
- Suite-wide permissions grid (sections × levels × roles) applied through `user_has_cap`; WPBot > Permissions screen with presets.
- Content Library: private `wpbot_content_item` post type and content packages table.
