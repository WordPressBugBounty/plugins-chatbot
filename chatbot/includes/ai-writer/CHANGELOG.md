# Changelog

## 0.28.0 (AI Writer in WPBot Free)
- **Pro badges and cards (WPBot Free).** One shared component shows each Pro feature where it would be, as an inactive control with a **Pro** badge. Clicking it opens a card with what the feature does, **Benefits**, **Features**, a "Get WPBot Pro" link and **Hide Pro previews**. The texts come from the host (`Host_Adapter::get_pro_previews()`, now with `spot`, `text`, `benefits`, `features`, `url`, `button`); the core has no Pro wording. Spots: Write (Interview me, Many from a list, saved briefs, voices, deep humanize, review workflow), the Real data step and research panel, Who ranks, the format picker, Library, the block and classic editor ("More with WPBot Pro"), Quick write, Topic Finder sources and Coverage, Calendar, Brand & Business, Products and Compare techniques; left-menu Pro pages show the card full size. A host that includes Pro lists nothing, so nothing shows there. "Hide Pro previews" (card or Settings) removes all of them. New `Admin\Pro_Previews` cleans the list (https links only, text only).
- **Five formats in the core.** Listicle, how-to guide, step-by-step tutorial, checklist and FAQ stay in AI Writer; original research, statistics roundup, comparison / vs, case study and template / framework move to AI Writer Pro (0.19.0), which adds them back in the same order. Without Pro, a post saved in one of those formats is a usual article (its meta is kept).
- **Format suggestion rules are a list** (`Formats::rules()`, filter `qcld_wpbot_writer/format_rules`, each with an `order`), and hint-only rules let a host mention a format the site does not have ("A Case study (Pro) fits this topic", REST `write/formats/suggest` → `hint`, only while Pro previews show). New `Formats::hint()`.
- **Format checks of other modules** run through the new filter `qcld_wpbot_writer/format_check`; `Format_Checks::blocks()`, `headings()` and `text()` are public.
- **Real data panels** come from the format recipe (`panels`, for example `research`), not from a format id.
- **Pages to read per import** is an owner setting (Settings → Writing → Brand & Business; 0 = the default, 1 to 100). The `qcld_wpbot_writer/import_max_pages` filter now sets the default only.
- Tests: `f16` works with and without Pro (107 / 104 checks), `b15` covers the page setting, new browser suite `pro-previews` (26 checks); the stub host can list a preview on every spot (`qcwr_test_pro_previews` = `all`).

## 0.27.0 (CR-16: article formats with real data)
- **Ten article formats:** listicle, how-to guide, original research, statistics roundup, comparison / vs, step-by-step tutorial, checklist, case study, template / framework, FAQ. Each one gives the outline and every section its own structure (for example: numbered items with the same fields and a summary table; steps with a result; a comparison table and a verdict; a method section). Every format follows the same rule: never invent a number, quote, customer, price or statistic; a missing fact becomes an `[add: …]` note.
- **Format choice under the topic.** AI Writer suggests a format from the topic, with the reason (rules only: no AI call, no cost). The suggestion is selected until the owner picks a format; "Article (no format)" keeps today's behaviour. Settings → Writing → Article formats turns suggestions off.
- **Tables and checklists** are written as native blocks (`core/table`; `core/list` with the `qcwr-checklist` class).
- **Format checks** on the finished draft, in plain sentences: the item count against the title, a table or verdict missing, numbered steps, a checklist, a method section, a statistic with no source and year, numbers not in the facts, a copy-ready template block, at least three questions.
- **Structured data:** ItemList (listicle, checklist), HowTo (how-to, tutorial), FAQPage (FAQ) and Dataset (original research) JSON-LD, built from the published post's own blocks. Rich results are not promised. Filter `qcld_wpbot_writer/format_schema` (return null to switch off).
- The format is kept with the post (meta `_qcld_wpbot_writer_format`).
- New filters: `qcld_wpbot_writer/formats`, `qcld_wpbot_writer/format_suggestion`, `qcld_wpbot_writer/format_schema`. REST `GET write/formats/suggest`.
- **Real data step (F2)** for a piece in a format: Brief › Real data › Outline › Writing › Done.
  - Sources for the format, each with what it gives or why it is off: your products, Persuasion Kit, Customer words, Site Context (read directly, no AI call), chatbot knowledge, your three closest pages, and the brief's documents and notes (read together in one AI call). The cost shows first.
  - Facts are proposed with their source (linked where there is a page). A fact from text is kept only when the sentence it quotes is really in that source; the rest are left out and counted. Kit reviews are marked "may be quoted" only when the owner allowed it.
  - The owner ticks what to use. Only ticked facts reach the outline and the sections, with their source. Facts the format needs but nobody has become `[add: …]` notes. "Skip" writes without real data.
  - The facts used are kept with the post (meta `_qcld_wpbot_writer_facts`).
  - REST `write/data/sources`, `write/data/gather`. New filters `qcld_wpbot_writer/format_sources` and `qcld_wpbot_writer/gather_source` (WPBot Pro adds its sources there), JS slot `qcld_wpbot_writer.write.data_sources`. Sources a format lists that nothing offers show as labelled Pro previews when the host offers previews.
- **Original research (F3).** For the Original research format, the Real data step gets "Your research data":
  - Upload a CSV (comma, semicolon or tab separated; up to 5 MB; read, then deleted), or pick a CSV from the chatbot knowledge (through the host's new `get_knowledge_documents()`; Settings level). WPBot Pro adds chats, WooCommerce orders and reviews, and Search Console.
  - AI Writer computes the findings itself: the most common values and their shares, averages (with median and range), averages compared between groups, and a monthly trend. Groups smaller than Settings → Writing → "Original research: smallest group shown" (default 10, at least 5; filter `qcld_wpbot_writer/research_min_group`) are hidden and counted in the method. Too little data is refused with the reason.
  - "Check the numbers": tabs per finding, a bar chart, the table and the method; "Approve these numbers". The browser only sends the approved analysis's id; the numbers come from the server.
  - The article gets the tables, an inline SVG bar chart per finding (sanitised) and the method after its first section, and the AI is told to use those numbers and no others. The format check flags any other number. The approved research is kept with the post (meta `_qcld_wpbot_writer_research`) for the CSV download (F4).
  - Datasets from other modules may give a weight column and a unit (clicks, items sold, chats).
  - REST `write/research/sources`, `write/research/file`, `write/research/source`, `write/research/{id}`. Filters `qcld_wpbot_writer/research_sources`, `qcld_wpbot_writer/research_dataset`.
- **Downloads (F4)**, in a small "Take it with you" box after the published article (Settings → Writing → "Offer downloads on published articles"):
  - Checklist: **Download PDF** and **Print**. The PDF is made by Dompdf 3.1.6 (LGPL-2.1), bundled as AI Writer's own scoped copy in `lib/dompdf` (every class under `QuantumCloud\AiWriter\Vendor\`, so another plugin's Dompdf can't clash; remote files, PHP and JavaScript off; DejaVu Sans regular and bold). Rebuild with `npm run vendor:dompdf` (`tools/dompdf/build.php`, from Composer). Licences: `lib/dompdf/README.md`.
  - Template / framework: **Word (.docx)**, written with PHP's zip, and **text (.txt)**.
  - Original research: **the data as CSV**, cells escaped against formula injection.
  - Files are made on request from published, public posts only (`?qcwr_download=pdf|print|docx|txt|csv` on the post's address); nothing is stored. Filters `qcld_wpbot_writer/format_downloads` and `qcld_wpbot_writer/download_gate` (a forms or marketing module can ask for an email first; the setting shows only when one does).
- **Automation (F4):**
  - Topic Finder ideas show "Format: …" (the suggested format; its reason on hover), and "Write" opens the brief with that format chosen. `qcld_wpbot_writer_open_brief_url()` takes `format`.
  - Improve lists statistics roundups and listicles not updated for a year ("Figures may be out of date"), with a fix that asks for current figures with source and year (filter `qcld_wpbot_writer/stale_figures_days`, default 365).
- **Calendar and formats:** an idea placed on the calendar keeps its suggested format. Each planned piece shows a Format choice on the calendar (REST `POST calendar/items/{id}/format`; calendar items carry `format`). Writing into a planned draft (the editor's Write with AI, WPBot Pro's drafts ahead) uses that format unless the brief chooses another. `Formats::automatic()`: Original research is never used for writing without the owner, because its numbers need approval.
- Tests: integration `f16.php`, browser `formats.e2e.mjs`.

## 0.26.0 (CR-15: Brand & Business)
- **Brand & Business:** Brand Voice and Business Profile are one menu item, with tabs Brand Voice | Business Profile (sub-tabs Persuasion Kit | Customer words). The tab follows the address (`section=voice|kit|words`) and survives a reload. The old Business Profile link (`tab=profile`) and `#qcwr-site-voice` land on the right tab. Pro's voice panels stay on Brand Voice.
- **Fill from your content**, on both sub-tabs:
  - Pick pages of your site (their approved comments and reviews too), paste web addresses, or upload a document (Word, PDF, text, Markdown, CSV; up to 10 MB; read, then deleted).
  - The cost shows first. AI Writer then reads each source and proposes items: reviews, testimonials, numbers, results, awards, guarantees and so on for the Kit; the customers' own phrases for Customer words. You tick what to add.
  - A quote or phrase is proposed only when its exact words are in the source (checked by AI Writer, not the AI), so invented quotes are dropped. A PDF goes to your AI provider as a document (OpenAI, Claude, Gemini or OpenRouter); its items are marked "check the wording".
  - Items already in the Kit or Customer words aren't proposed again. Quotes are saved as "may be quoted" only when you tick that. Waiting proposals are kept for a day.
  - Web pages are read politely: robots.txt is respected, private addresses are refused, and a timeout and a size cap apply.
  - Free reads up to 5 pages and addresses per import (filter `qcld_wpbot_writer/import_max_pages`; WPBot Pro: 50). File types: filter `qcld_wpbot_writer/import_types`.
- REST `business/import/estimate`, `business/import` (GET waiting, POST one source, DELETE), `business/import/file` (multipart), `business/import/apply`.
- Needs WPBot Core with the CR-15 readers (`Library\Page_Reader`, `Library\Document_Text`, `qcld_wpbot_core_voc_add()`); without them the import is hidden.
- Includes 0.25.1 (Brand Voice empty-reply fix).

## 0.25.1 (fix)
- Fixed: Brand Voice showed "Unexpected reply from the server (HTTP 200)" on a site with no saved site voice yet. WordPress sends that empty answer as an empty body; AI Writer now reads a successful empty reply as "nothing yet" on every screen, instead of as a broken page. Browser test `voices.e2e.mjs` covers a site with no site voice.

## 0.25.0 (issue #35: shared Site Context)
- AI Writer reads WPBot Core's shared **Site Context** (what the business is: offers, audience, places, strengths, common questions, words to use) as its "about the business" source, so the owner never fills in a second profile. Brand Voice stays separate: Site Context is what the business is, Brand Voice is how it sounds.
- Used in article briefs, Quick write, editor actions (Write here, rewrite, FAQ, summary, titles…), Topic Finder's AI brainstorm and the brief's "Suggest with AI". It is sent as background (up to 1,500 characters), with an instruction not to state more than it says.
- Without a Site Context summary (or with an older WPBot Core), prompts are exactly as before.
- New `Prompts::business()`; filter `qcld_wpbot_writer/business_prompt` ( $text, $context ).
- AI Writer facts (Business Profile, voice of customer) already reach Site Context through WPBot Core's own `qcld_wpbot_core/context_facts` handlers.

## 0.24.0 (CR-14 R3: shared pieces for Pro, and more places in core)
- **Calendar:** each post item has **Regenerate** (keep the outline or write a new one, cost first). Drafts are rewritten with a revision; published posts get a proposed update.
- **Write → finished article:** "Regenerate the article" under the result; the article view refreshes when it is done.
- **Library:** a slot above the list for regenerating several pieces (JS filter `qcld_wpbot_writer.library.bulk`, given the listed items); Free shows "Regenerate several" as a labelled, inactive Pro item when the host offers Pro previews.
- Shared UI for other modules on `window.qcldWpbotWriterUI`: `ArticleRegenerate` (the whole-article control, also used by the editor sidebar), `OptionsForm` and `optionsLabel`.

## 0.23.0 (CR-14 R2: Regenerate articles, products and the rest of core)
- **Editor sidebar → Regenerate:** "Show the sections" lists each section (a level-2 heading and the blocks under it). Each has Regenerate and "Regenerate with changes ▾"; the new section is previewed (with honesty flags and a warning if protected facts went missing) and **Replace the section** swaps only that section's blocks, as one undoable change.
- **Whole article:** "Keep the outline, rewrite the sections" (no new outline call) or "Write a new outline", with the same options and What to change. The cost is always shown first; the result arrives as a **proposed update** (review, then publish), so the live post never changes on its own.
- **Drafts** (not yet published) are rewritten in place instead, with the earlier text kept in WordPress Revisions; the editor offers "Reload the draft".
- The Regenerate form shows a **Voice** choice when another module supplies voices (filter `qcld_wpbot_writer/regenerate_voices`; WPBot Pro fills it); the history label names the voice.
- **Outline step:** "Regenerate outline" before writing, with earlier outlines kept to flip back to (‹ 1 of 2 ›).
- **Products** (WooCommerce product tools), **Improve** review (each proposed section), and **Compare techniques** (each version, keeping its technique) get Regenerate too.
- **Brand Voice:** a new voice draft has "Regenerate the voice" (reading level and What to change), with earlier drafts kept to flip back to; the sample paragraph regenerates in one click. Nothing is saved until Save voice.
- REST `regenerate/sections`, `regenerate/section`, `regenerate/article` (`step` estimate or start, `mode` keep_outline or new_outline), `write/jobs/{id}/outline/regenerate`, `persuasion/compare/regenerate`, `improve/jobs/{id}/regenerate`. Woo product routes accept `regenerate` with `again`. The article brief carries a `regenerate` part.
- Fixed: on the Write page, a topic typed before the page finished loading could be cleared when the chatbot knowledge source switched on by default.
- Fixed two PHPCS warnings in the Improve job.

## 0.22.0 (CR-14 R1: Regenerate)
- **Regenerate** next to AI text in the editor (block toolbar actions, "Write here" and the AI block, title suggestions, excerpt, search title and description, FAQ, summary, variations), the classic editor box and Quick write. One click writes a new version with the same options. **Regenerate with changes ▾** lets the owner change the tone, length, reading level, technique (or No specific technique) and language, and add **What to change** (free text, quoted to the AI as a description, never as new instructions).
- **Versions:** each piece keeps its last versions (‹ 2 of 5 ›, with the options each used). Posts keep them in post meta `_qcld_wpbot_writer_versions`; Quick write keeps them for the user for a week. Nothing replaces the owner's text until they click Use.
- **Cost:** the button shows the estimate. Above the owner's threshold it asks first, with "Don't ask again this session". Settings → Writing → Regenerate: **Versions to keep per piece** (default 5, 1–20) and **Ask before regenerating above** (default US$0.05).
- REST `regenerate/estimate` and `regenerate/versions`. Editor routes and Quick write accept `again` with `regenerate` (options) and `slot`. Filters `qcld_wpbot_writer/regenerate_kinds` and `qcld_wpbot_writer/regenerate_options`. JS: `RegenerateControl`, `VersionPager`, `useVersions` on `window.qcldWpbotWriterUI`.
- The AI preview stays open in WordPress 6.2 while a new version is written.

## 0.21.0 (round 3, P5)
- Compare techniques for email subject lines and social posts hands the result to Pro's email and social tests (subject tests run through AI Marketing when it supports a subject per recipient; social tests use tracked links and are labelled Indicator).
- Integration readme: Persuasion Kit, technique library, voice of customer, Compare techniques and Experiments APIs.

## 0.20.0 (round 3, P4: tests on the Experiments service)
- Compare techniques has a slot under the versions (JS filter `qcld_wpbot_writer.compare.after`, given the result, the chosen version, the kind and the input); WPBot Pro adds "Send to an A/B test" there.
- Needs WPBot Core 0.15.0 for A/B tests (Pro); writing works with older cores.

## 0.19.0 (round 3, P3: Compare techniques and headline lab)
- **Compare techniques** wherever copy is written: the title (block and classic editor), "Rewrite with technique…" on a paragraph, intro or call to action (block toolbar), the search title, product descriptions, Quick write social posts and email subject lines. Choose techniques (or **No specific technique**), or "Suggest the best" for the reader stage and the kind of copy.
- Versions side by side, each with its label and "why this works" (method in the tooltip), reader-stage fit, length (against the limit where there is one), reading level, the Persuasion Kit proof it used, and honesty flags (which stay on the text that is used).
- **Cost first:** "Show the cost" gives the estimate; nothing is written until "Write the versions", and nothing is used until "Use this".
- **Red lines:** techniques that need real data (kit proof, a real price, real stock or sale dates) are left out, with the reason, when the data is missing.
- **Headline lab:** labelled variations per technique (specific, curiosity, how-to, gain, loss).
- Free compares two versions at a time; WPBot Pro raises it (filter `qcld_wpbot_writer/compare_max`) and allows mixing parts (`qcld_wpbot_writer/compare_mix`). Headline lab variations per technique: `qcld_wpbot_writer/headline_lab_per`. Suggestions: `qcld_wpbot_writer/compare_suggest` (past winners first, from P4). Kinds: `qcld_wpbot_writer/compare_kinds`.
- REST `persuasion/compare` (`step` estimate or generate) and `persuasion/apply` (one version, or parts in Pro). Versions are kept per user for a day; only generated and checked text can be applied. What the owner chose is recorded with the post (`_qcld_wpbot_writer_compare_log`) for learning.
- `CompareTechniques` added to `window.qcldWpbotWriterUI`.

## 0.18.0 (round 3, P2: writing with techniques)
- **How it persuades, per piece:** reader stage (detected from search intent, the Keyword Vault, the idea's source or the kind of piece), framework, main angle, extra techniques, or **No specific technique**. Shown in the brief, on the product screen, in Quick write and in Improve (re-angle); each with its plain label and "why this works" (method names in tooltips only). Choices are kept with the piece (`_qcld_wpbot_writer_persuasion`); nothing is site-wide.
- **Outline:** each section has a job (hook, problem, explain, proof, answer a worry, call to action) and one technique, changeable, with its reason; sections are written with it.
- **Prompts** carry the plan, the Persuasion Kit (the only proof allowed; expired offers left out; quotes only with permission) and customers' own words (wording only), plus the honesty rule.
- **Products:** feature to benefit, the job and the worries, framing of the real store price, the kit guarantee for risk reversal; honesty flags with the result.
- **Quick write:** calm reply for review replies (or a plain reply), network-fitting frameworks for social posts, gentle tone available for email; honesty flags on every variation.
- **Improve:** optional "Re-angle it" with another reader stage or technique.
- **Checklist:** answer first, one call to action, proof near it, risk reversal, specific details, and the **honesty flags** (invented proof, fake urgency, ended offers, prices that do not match, quotes without permission, engagement bait). Flags are stored after writing and on every save, cannot be dismissed, and publishing with flags is recorded (`_qcld_wpbot_writer_honesty`).
- Calendar drafts planned from an idea use it for the reader stage; `qcld_wpbot_writer_open_brief_url()` passes `persuasion` through; choices kept on a draft are used when it is written.
- REST `write/persuasion`; `persuasion` arguments on `woo/products/{id}/write`, `improve/start` and Quick write fields. Shared UI: `PersuasionPicker`, `HonestyFlags`, `WhyNote` on `window.qcldWpbotWriterUI`.

## 0.17.0 (round 3, P1: Business Profile)
- New **Business Profile** screen (Free):
  - **Persuasion Kit:** add, edit and delete real proof by type; "permission to quote" on reviews and testimonials; offers need an end date and show "Ended: never used in writing" afterwards.
  - **Customer words:** read reviews, the kit and the site search log (free, no AI); group them by topic with AI after a cost estimate; filter by topic; remove a phrase. Saved chats show as a labelled Pro item when the host offers Pro previews.
- The site search log is a voice-of-customer source (terms searched at least twice in 90 days).
- Needs WPBot Core 0.14.0 for the Business Profile (hidden with an older core).

## 0.16.0 (round 2: owner follow-ups F1–F8; 0.15.0 was an internal step)
- **Fixes (#24, #28).** "Words people search for" and the outline's FAQ field keep what you type (spaces, commas, new lines). Brand Voice > Site voice no longer fails with "not a valid JSON response": stray output from other plugins is held back during AI Writer's REST requests, and any non-JSON answer now shows a readable message.
- **AI setup clarity (CR-5, #18).** The menu item is now "Settings & AI Provider". When AI is not ready, every AI screen and the editors show a bold notice naming the cause with a link to the fix (Set up AI, Turn on, Pick a model, Update key, Add funds, Raise budget); AI buttons are disabled with the reason. Each provider card has a status line. Uses WPBot Core's shared readiness check.
- **SEO data in AI Writer (CR-4, CR-6).** Search numbers on Topic Finder ideas, the Coverage view and the brief, each with its source badge ("AI estimate" when guessed). A Keyword Vault source and "Write" from the Vault. Brief fields for intent, questions, pages to link to and from, and keyword numbers. "Link from" suggestions in the editor with one "Add link" button per page. RSS feeds from your competitors ("Use my competitors", "Suggest competitors"), sharing WPBot Core's competitor list.
- **Brief form (CR-9) and Who ranks (CR-8).** The brief's options are always visible and compact; main search, audience, keywords, length and tone are prefilled from your data (filter `qcld_wpbot_writer/brief_prefill`), with an optional AI suggestion that fills only empty fields. "Who ranks for this search?" is now in Free; "Analyse these pages" shows as a labelled Pro item (slot `qcld_wpbot_writer.who_ranks.after`).
- **Finished article and images (CR-10).** The article is shown, formatted and editable ("Edit here") on the Write page, with its images: Find a stock photo, Media Library, Create with AI. Missing images are explained. The AI Writer sidebar opens the first time an AI draft is opened. An "AI Writer: find or create an image" button under "Set featured image". New image choices: "Media Library, then stock, then AI if nothing is found" (default), AI first, a separate choice for the header image, and the AI image provider.
- **Edit Improve proposals (CR-7).** Proposed changes show formatted; "Edit" opens a text area (simple tags kept), with Save, Reset and an "Edited by you" badge. Edits are cleaned on the server.
- **Brand Voice list (CR-12).** A Voices list at the top of Brand Voice (slot `qcld_wpbot_writer.voice.list`): the site voice as the default, "Add a voice" as a labelled Pro item.
- **Images for the AI.** `Writer_AI::ask()` takes `images` (used by Pro to read ad screenshots).
- Requires WPBot Core 0.13.0 for screenshot reading; everything else works with 0.12.0.

## 0.14.0 (CR-2: primary and secondary AI provider)
- Settings > AI providers and keys: a **Primary provider** and a **Secondary provider (backup)** choice at the top replace the per-provider on/off switches and the "try other providers" switch. Each provider card shows Primary, Secondary (backup) or Off. Choosing the secondary as primary clears the secondary. Other providers keep their keys but stay off.
- The Test button works for providers that are off, so a key can be checked before choosing it.
- Requires WPBot Core 0.12.0.

## 0.13.0 (M13: Coverage view)
- Coverage view in Topic Finder (Free): topics of the shared map with their coverage (covered, thin, planned, missing), live and planned pages, missing subtopics and a "Write next" button that opens a brief for the topic or subtopic. Slots `qcld_wpbot_writer.coverage.actions` and `qcld_wpbot_writer.coverage.topic`, filter `qcld_wpbot_writer/coverage`. REST `GET /topics/coverage`, `POST /topics/coverage/{id}/write`.
- Search volumes when a keyword data provider is connected (wpbot-core's keyword data API, or the filter `qcld_wpbot_writer/search_volumes`): shown in the Coverage view, on Topic Finder ideas and in the brief sent to the writer. Nothing changes without a provider.
- Requires WPBot Core 0.11.0.

## 0.12.0 (requests from SEO & AI Search; M12 groundwork)
- The SEO adapter wraps WPBot Core's shared adapter; `qcld_wpbot_writer/seo_plugin` still works. AI Writer never prints its own title or description when SEOPress, Slim SEO or The SEO Framework is active (R-1).
- `qcld_wpbot_writer_open_brief_url()`: mode `refresh`, `supporting_queries`, `numbers` (R-2).
- `qcld_wpbot_writer/content_finalized` passes the content package as a third argument (R-3).
- Filter `qcld_wpbot_writer/improve_tasks`: other modules list Improve tasks next to decay alerts (R-4).
- Requires WPBot Core 0.10.0.

## 0.11.0 (M11 groundwork)
- `Writer_AI::ask()` takes `web_search`. Requires WPBot Core 0.9.0.

## 0.10.0 (M10 Channel versions, public API)
- `qcld_wpbot_writer/content_published` ( $post_id, $package ): a finished piece went live (first time, or again after being unpublished).
- `qcld_wpbot_writer_tracked_url( $url, $args )` adds UTM tags (source, medium, campaign, content); filter `qcld_wpbot_writer/utm_args`.

## 0.9.0 (M9 Calendar Pro, shared groundwork)
- Calendar: `author` filter (REST and `Calendar::items()`); JS slots `qcld_wpbot_writer.calendar.panels` and `qcld_wpbot_writer.calendar.filters`; items from other modules can carry `status_label`, `note`, `action_label` and `action_url` (shown in the item dialog).

## 0.8.0 (M8 Pro power, shared groundwork)
- Briefs carry `auto_outline` (continue past the outline without approval), `bulk_job` and `voice_id`. `Article_Job::start()` takes `budget` and `parent_id`.
- Filters for Pro: `qcld_wpbot_writer/brief_voice`, `quick_voice`, `voice_profile`, `voice_prompt`, `stitched_sections`, `article_steps`, `library_query`, `library_piece`. JS slots: `write.modes`, `write.brief_tools`, `library.filters`, `library.piece`, `editor.panels`, `voice.panels`, `templates.panels`.
- `Voices::items()`, `save_item()`, `delete_item()`: the voices table also stores saved briefs and custom templates by type.
- Job progress shows "reached its budget" with a field to raise it.
- Requires WPBot Core 0.8.0.

## 0.7.2 (CR-1: shared keyword and topic map)
- Ideas have optional `topic` and `main_query` fields (empty on older ideas); search-log and Search Console ideas target their own search; topics come from the site's main topics.
- `qcld_wpbot_writer/content_finalized` ( $post_id, $brief ) fires when a piece is finished; `Topics\Map_Sync` records its main search, supporting searches and topic in WPBot Core's map. Saved ideas (calendar ideas list) are planned pages; an idea placed on the calendar passes its entry to the dated draft; dismissed ideas leave the map.
- Briefs carry `main_query`, `map_topic` and `idea_id` (also through `qcld_wpbot_writer_open_brief_url()`); Write from Topic Finder pre-fills them; the brief shows the topic and a "Main search" field.
- Topic Finder: "Covered by: page X" reads the map first (titles as fallback); "Planned: X" when another idea or draft already plans the search; topic badge.
- Duplicate check before writing (brief 6.2): "Your page X already targets this" with Improve that page / Write a different angle / Continue anyway. Reads the map, then the SEO plugin's focus keyword (Yoast SEO, Rank Math), then titles. REST `POST /write/duplicates`. Requires WPBot Core 0.7.0.

## 0.7.1
- Admin layout from brief 2.4: AI Writer is shown inside WPBot Core's shared admin shell (left menu like WPBot's AI Marketing; every item has its own URL and works with the back button; count badges; a select menu on narrow screens). Menu: Overview, Write, Quick Write, Improve Content (badge: open decay alerts), Topic Finder, Content Calendar, Library, WooCommerce (when active), Brand Voice, Templates, Usage & Costs, Settings; inactive Pro items from the host (Free) with a setting to hide them. Items come from the `qcld_wpbot_writer/menu_items` PHP filter; the `qcld_wpbot_writer.admin.screens` JS filter now maps item ids to screens (`{ id: render }`). Brand voice and the style guide moved from Settings to Brand Voice. Requires WPBot Core 0.6.0.
- Library tab: finished pieces AI Writer wrote (search, status filter, pages; summary, key points and the visitor question from the content package; Edit, View, Improve), limited to what the user can edit; the user's saved Quick write items (search, Copy, Delete). REST `/library/pieces`, `/library/items`, `DELETE /library/items/{id}` (own items, or the Settings level).

## 0.7.0 (M7 Visitor questions and Topic Finder)
- Topic Finder tab: Sources strip (Connected, Not connected, Not available, Not working, each with a fix), Search Console connect card, bold notice for failing sources, refresh as a job (one step per source) weekly or on "Find new topics", one list per source with sources, trend, relevance, coverage ("Covered by" / "Not covered yet") and reason; actions Write (pre-filled brief), Add to calendar (dated draft), Dismiss. Registry `qcld_wpbot_writer/idea_sources`, combined list filter `qcld_wpbot_writer/topics_combined`, JS filters `qcld_wpbot_writer.topics.panels` and `.topics.columns`.
- Free sources: site search log (new aggregate logger: day, term, searches, searches with no results; no IP, user or visit; redacted; bots and editors skipped; kept 180 days), Search Console (rising queries, queries with no ranking page), store and visits (WooCommerce best sellers, Site Insight top landing pages), seasons and events (computed holidays per country, owner's store events, next 60 days), news feeds (headlines and links only), AI brainstorm.
- Search Console is a decay source for Improve (clicks per page, 28 days vs the 28 before).
- Weekly topics email (off by default): only when there are new ideas; recipients; hideable footer line (`qcld_wpbot_writer/weekly_email_footer`); "Send me a test email".
- Answer visitor questions: start card and Topic Finder questions open the write flow in answer mode (FAQ, help article or new "Page section" type); the question is redacted, answered directly, facts only; drafts remember the question. "Add to WPBot knowledge" in the editor sidebar after publishing (through the host's knowledge writer).
- Settings: Topic Finder card; Connections card (Google client, Connect/Disconnect, property; YouTube key when a feature uses it).
- Shared UI for extensions: `window.qcldWpbotWriterUI` (JobProgress, KeyNotice, api).
- Requires WPBot Core 0.5.0.


## 0.6.0 (M6 Content calendar)
- Calendar tab: month and week views of posts, pages and products by their WordPress dates (site time zone; no copy of the schedule). Drafts without a set date are shown on their current day and marked.
- Drag to reschedule drafts, pending and scheduled posts (time of day kept; scheduled posts stay in the future; moving scheduled posts needs the Publish level and WordPress publish rights). Keyboard path: open an item and pick a date.
- Click a day: "Write with AI for this day" (the new draft is dated that day) or plan one of your ideas there.
- Ideas list: add ideas, drag one onto a day to create a dated draft. Ideas API (`Topics\Ideas`) ready for Topic Finder.
- Filters (content type, status, only mine) and warnings (two posts going out on one day; scheduled posts that look unfinished, with the reasons).
- Other modules can add items with `qcld_wpbot_writer/calendar_items` (each item has `type` and `source_module`).

## 0.5.0 (M5 WooCommerce writing)
- Only with WooCommerce active (no code, route or asset loads without it).
- Classic product screen, AI Writer box: description (benefits and use from the AI; specifications and an "Available in" line built from the product data), short description, product FAQ, variation descriptions (saved to the ticked variations), gallery and product image alt text (saved when approved), search title and description.
- Facts rule: the AI gets name, type, prices, SKU, categories, tags, attributes, variations, size, stock and the owner's notes; claims the data does not support (guarantees, compatibility, materials, certifications, performance, availability, superlatives) are flagged next to the text, never written silently.
- Products list bulk action "Write descriptions with AI": a queued job writes each product's descriptions; the review screen shows them with flags; only approved products are saved; products edited meanwhile are not overwritten.
- Product categories and tags: "Write description with AI" on the edit screen.
- Buying guide and product comparison types (need two or more products as sources; product facts and links are given to the writer).
- Classic Editor (any post type): search title and description from the AI Writer box, saved to the SEO plugin with the post.
- Fixed: job progress could reload in a loop and stop the browser runner when a screen passed a new callback on each render.

## 0.4.0 (M4 Improve existing content)
- Improve: pick a post, page or product (or a task from the decay check, or "Improve with AI" in the posts list, or an open_brief link from another module); AI Writer reviews it section by section and proposes clearer, shorter or up-to-date text, plus a FAQ, a summary and search meta. Accept or reject each proposal.
- Changes are spliced into the exact text positions: block comments, attributes, custom blocks, shortcodes, embeds and special formatting stay byte for byte. Proposals that would lose a link, shortcode or number are blocked; text with special formatting is never rewritten.
- Drafts change directly (with a revision). Live pages get an update copy to check in the editor and publish into the live page ("Publish the update"); the copy can never be published as a separate post. The old version stays in the page's revisions.
- Content decay alerts: weekly check (and Check now) turns pages losing visits or search clicks into Improve tasks with the likely cause. Sources: Site Insight (built in when active) and any source on `qcld_wpbot_writer/decay_sources` (Search Console arrives with the Google connection).
- Editor: "Improve this post" link; notices on update copies and on pages with an update waiting. Classic Editor: Publish the update button.

## 0.3.0 (M3 Edit-screen writing)
- Block editor sidebar (AI Writer): Write with AI into the open draft (brief, outline, sections; a result that finished while you were away is offered again), title suggestions, excerpt, search title and description for the active SEO plugin, Add FAQ, Add summary, variations (headlines, hooks, calls to action, meta), featured image suggestions, image check, live checklist, AI chat, Quick write.
- Block toolbar on text blocks: Simplify, Shorten, Expand, Fix grammar, Translate, Make more natural, Change tone, on the selection or the whole block; preview, then Replace, Insert below, Try again or Discard; undo works.
- `/ai` block: write, continue or rewrite at that spot; replaced by native blocks; never saved or rendered.
- Image block "Replace with AI Writer": Pexels, Pixabay, Openverse, Unsplash (off by default, hotlinked with credit), Media Library, Upload, Create with AI, graphic templates (title, quote and list cards in brand colours). Pre-publish image check (featured image, alt text, large files, reuse, credits, placeholders, Unsplash only) with a fix for each; it never blocks publishing.
- Live checklist (replaces the old two scores): length, title, headings, readability, links, images, personal touches, FAQ, search title and description, excerpt, topic coverage, AI-pattern flags, voice and style-guide flags with highlights in the text and targeted rewrites that keep numbers, quotes and links.
- Basic style guide (words to use and avoid, product names, reading level) in prompts and checks.
- SEO adapter: writes only to Yoast SEO, Rank Math or All in One SEO when active (never their scores), else AI Writer's own meta, printed in the page head. Values from the editor are written when the owner saves.
- Classic Editor box: title suggestions, excerpt, Write with AI that inserts clean HTML at the cursor.
- Quick write tab and editor panel: 14 templates, three variations per call, network limits enforced, AI-pattern hints, copy, save to the Content Library.
- Requires WPBot Core 0.3.0.

## 0.2.0 (M2 Write something new)
- Write flow: brief (type, topic, length, more options, post or page), sources (pages and products, uploaded PDF/DOCX/TXT/MD, ChatBot knowledge, notes), "Make it yours" questions, cost estimate, outline review (titles, angle, sections with word budgets, reorder, remove, add, FAQ, summary, missing facts), live progress, finished screen with review counts.
- Article job: sources summarised once, outline (pauses for approval), one short step per section with the previous section's summary, FAQ, stitching of the joins, rule-based AI-pattern check and claim flags, one metadata call, images last, native-block draft (Classic Editor: HTML), content package.
- Images: Media Library match, Pexels, Pixabay, Openverse, AI (owner's key) or placeholders; resized, WebP when supported, alt text, credits and licence stored. An image failure never loses text.
- Site voice: created from the owner's writing and answers, editable sliders and fields, owner excerpts as style examples, Try this voice.
- Writing rules on every call; site language and spelling; tight HTML allowlist for AI text.
- Start screen: Write button, example topics on empty sites, running jobs with Continue, recent drafts. Requires WPBot Core 0.2.0.

## 0.1.0 (M1 Foundations)
- Newest-copy loader that requires WPBot Core 0.1.0+, host adapter contract, feature registry for extensions.
- Schema: ideas, voices/templates, documents and site search log tables (signature upgrades).
- Settings with sanitising: content type, length presets, spelling, questions, per-step models, runner interval, data removal.
- AI Writer section adopts the WPBot permissions grid (`wpbot_writer_view|use|publish|settings`).
- Admin app (React via `@wordpress/scripts`): tabs, bold failing-key notice, AI keys and models (masked), writing defaults, usage and cost page, diagnostics test job with live progress, stop and resume.
- REST namespace `wpbot-writer/v1`: `/settings`, `/diagnostics/test-job`.
- Public API: `qcld_wpbot_writer_get_package()`, `qcld_wpbot_writer_open_brief_url()`, `qcld_wpbot_writer_get_dashboard_data()`.
