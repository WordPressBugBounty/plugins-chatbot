# Changelog

## 0.56.0 (AI Actions: "Write with AI" in the Live AI Playground; an answer that is not one of the examples counts)
Owner, 25 November 2026: "I will also need AI to write a prompt for AI actions for the admins. Could be in the preview window already present" (yes to "Improve this action" and to using Business Context).
- **Write with AI** (`Actions\Writer`, admin-ajax `qcld_wpbot_chat_ai_action_write`, administrators only): the admin describes the action and the site's AI drafts the title, the AI Prompt (when to use it, then numbered questions with short example answers in brackets, the format the engine follows best) and Reply when done. **Improve this action** sends the open action's fields (and optional notes) and returns a better version. WPBot Core's Business Context is added when it has a summary; the AI is told never to invent prices, plans or features. Nothing is saved until the admin saves the action. Usage feature `ai_action_writer`. The writer's instructions carry a short example and ask for reply instructions written as commands, never a fixed answer with sample values (staging, gpt-4o-mini, had written "I recommend ordering a cake for 20 people"). The hosts' AI Actions screen shows it as a tab in the playground (WPBot Pro and WPBot Free, same round).
- **"5" answers "How many sites? (1, 2 to 5, 6 to 10)":** the system text now says an answer counts even when it does not match the example answers exactly, and never to ask the same question twice in a row (wpbot.pro, gpt-6-luna, re-asked the sites question after "5").
- Tests: `AiActionWriterTest`; botmaster spec 120.

## 0.53.6 (WPBot Free: a picture of each Pro chat window design)
Owner, 25 November 2026: "In Free can you show a screenshot of the different Pro themes when user clicks on them? don't load it in the preview window. Show a popup with the preview".
- In the chat window designer, the six Pro designs (Poster, Till slip, Floating island, Console, Exercise book, Lightcycle) were greyed-out tiles that could not be clicked on sites without WPBot Pro.
- They can now be clicked. Each opens a pop-up with a picture of the design in the real chat window, its name and description, "See WPBot Pro" and Close. The live preview and the chosen design don't change.
  - The pictures ship with the module: `assets/window/previews/<design>.webp`, 600 px wide, 19–31 KB each (156 KB in all).
  - Escape, Close, × and a click outside close it, and the focus goes back to the tile. While it is open the live preview is inert, so the preview's chat can't take the focus.
  - The link goes to `https://www.wpbot.pro/pricing/`; the new filter `qcld_wpbot_chat/pro_url` changes it ('' hides it).
- With WPBot Pro these designs are usable and are chosen as before, with no pop-up.
## 0.53.5 (The cart count no longer flashes "0" while the chat button loads)
Owner, 25 November 2026: "The small circle that shows the woocommerce product number in the cart shows for a second when the icon loads."
- WPBot Pro prints the cart count with `hidden` while the cart is empty. This module's launcher stylesheet, which loads with the page, gave the count `display: grid !important`, and the rule that hides it came only with the hosts' common stylesheet a moment later. So a "0" showed for about half a second.
- The launcher stylesheet now keeps a hidden count hidden from the first paint. A cart with items shows its count as before.

## 0.53.4 (AI Actions: no entry before the visitor has answered; the next question, not the last one again; no suggestions mid-action)
Owner on wpbot.pro, 25 November 2026 ("Is WPBot right for me?", gpt-6-luna): "I got an email after 2 questions and before I finished the AI action. It kept asking for how many sites I want it for even after I said 5. Then I got another email."
- **No entry before the visitor has answered:** the model could fill in all six answers itself, call the tool early and pass the "6 answers" check, so the entry was saved and emailed after two questions. `Actions::answers()` counts the visitor's own messages since the Action started (after the last message that is its name); a numbered form now also needs one visitor message per question (allowing one fewer let the model make up the last answer and skip the last question on staging). Otherwise the call is "Not finished" and nothing is saved.
- **The next question:** after an early tool call the turn now always ends with one more round, tools off, that asks the first unanswered question (and only the question: gpt-6-luna once added its notes, "Must offer brackets short examples per instruction"). 0.53.3 showed the text the model wrote beside the early call, which was often the question just answered ("How many websites?" three times).
- **No typing suggestions or finish-my-question text while an Action asks its questions** (`window.js`, `qcldWpbotChatState().aiAction`): a suggestion ("Lead capture") had been sent in place of the visitor's answer.
- Tests: `AiActionStepsTest` (made-up answers wait for the visitor; `Actions::answers()`); botmaster spec 118.

## 0.53.3 (AI Actions ask every question once, in order; "Popular on this page" waits)
Owner's staging, 25 November 2026 ("Is WPBot right for me?", gpt-6-luna, streaming on): questions were skipped, asked out of order, or shown twice in one bubble, and "Popular on this page" showed up in the middle of the action.
- **Cause:** the model called the Action's tool before the visitor answered, inventing answers (`{"1":"business or service site"}`). The "Not finished" result let it go on in the same turn, so up to three rounds ran and the visitor saw only the last round's question, sometimes with the earlier one repeated.
- **Fix:** a tool call that comes too early (`Actions::run()` returns `wait`: "Not finished" or no answers) now ends the turn (`Ai_Client` `tool_wait`, set by `Ai_Step`). The question the model wrote beside the call is the reply; if it wrote none, one more round with `tool_choice: none` writes it. The "Not finished" text now says never to guess or invent an answer and to ask the first question the visitor has not answered.
- **The first question once:** on the first turn (the message is the Action's name, sent by its start menu button) the system text says the user just started the Action and to ask its first question, instead of "their message answers your last question". A reply that is one block written twice back to back is kept once (`Ai_Client::once()`, tool path). With gpt-6-luna the first question had come out twice in one reply.
- **Popular on this page** (`assets/window/window.js`) waits while an AI Action asks its questions: hosts report `aiAction` in `window.qcldWpbotChatState()` (WPBot Pro and WPBot Free, same round).
- Tests: `AiActionReplyTest::test_only_an_early_call_ends_the_turn`, `::test_the_first_turn_asks_the_first_question`, `AiClientTest::test_once_keeps_one_copy_of_a_reply_written_twice`, `AiActionStepsTest`; botmaster specs 66 and 118.
## 0.53.2 (Suggestions above the message box: close after 6 seconds, close button)
Owner, 25 November 2026: "the suggestions should disappear after 6 seconds. also add a close button".
- The pop-up above the message box (Try asking, suggestions while typing) closes by itself 6 seconds after it opens.
  - It waits while the pointer is on it, while a suggestion has the keyboard focus, or while the visitor moves through it with the arrow keys; then it closes 6 seconds later.
  - Typing more resets the time, as each keystroke shows fresh suggestions.
- A **×** button at its top right closes it ("Close suggestions" for screen readers) and leaves the visitor in the message box. Escape and a click outside still close it too.

## 0.53.1 (Product details slide out from under the window)
Owner, 25 November 2026: "The popup will open from under the chatbot window so it looks like a part of it. Reduce the popup height by 20% and align middle with the chatbot window. The pop up needs slide out and slide in animation. Design must match each chatbot window theme."
- **Place:** WPBot Pro's product details panel sits beside the window, 80% of the window's height and centred on it, with its edge 18 px under the window. `window.js` measures the window when the panel opens and on resize (`--qcw-pp-x`, `-top`, `-w`, `-h`); up to 720 px wide (was 560 px).
- **Motion:** it slides out from under the window and back (transform and clip-path, so it never shows past the window's edge). Visitors who ask for reduced motion get no slide.
- **No room beside the window** (less than 520 px): it covers the window in its place (`.qcw-pp-over`), sliding in from the window's edge. Phones: full screen. Embedded and full-page windows: over the window, as before.
- **Look:** a see-through window header (Holo, Desk, Receipt, Notebook) now gives a see-through band; before, the panel fell back to the header token colour (the purple band in the owner's screenshot). New `--qcw-pp-accent` marks the chosen option and the focus ring: the send button's fill, the first colour of its gradient, or its text colour when it is outlined.
- **Layout** for the host's new markup (botmaster): header band with "View product page", gallery beside the details, price and stock, a three-line description with Read more, option buttons, a quantity stepper, and a footer that keeps Add to cart (and the "Added to your cart" message) in view.
- Fixed while building it: removing an absent class with `classList.remove()` rewrote the class attribute and woke the panel's observer again, which froze the page on phones.

## 0.53.0 (AI Actions: "Reply when done")
Owner, 25 November 2026: an AI Action for wpbot.pro visitors asks whether WPBot is right for them (pain point, what to automate, number of sites, …), then describes how WPBot helps and which license suits them. Until now a finished Action told the AI "Done: the details were sent. Tell the user, in one short sentence", so no full answer could follow.
- **New form setting `reply`** (`wpbot_ai_forms`, saved by the hosts' AI Actions screen as "Reply when done"): instructions for the bot's answer once every question is answered. Actions added with `qcld_wpbot_chat/actions` can give `reply` too.
- **Tool path:** when the Action's steps succeed, the tool result asks the AI to write its answer from the visitor's answers, following the instructions (`Actions::reply_rule()`, at most 6,000 bytes, `Ai_Forms::REPLY_MAX`). Lookup results come first, then the instructions. An unfinished or failed Action never gets them. Actions without `reply` keep the one-sentence confirmation. The general tool instruction now says "answer the user as the tool's result says" instead of "tell the user it is done".
- **Delimiter path** (models without tool calling): the form's instructions say to write the answer first and then the hidden data block (`Ai_Forms::reply_rule()`), so the visitor sees the answer and the entry is still saved.
- Tests: `AiActionReplyTest`.
- Versions 0.51.0 and 0.52.0 are open in other PRs (#84, #85, #87, #67); this number follows them.

## 0.50.21 (Lightcycle: a Light version of the GRID look)
Owner, 25 November 2026: "I think you broke the light and dark version of the lightcycle theme. there is no difference between light and dark now".
- 0.50.18 gave Lightcycle the GRID's navy palette in Light and Dark alike, so the Light / Dark / Follow the visitor choice changed nothing.
- **Light** now has its own palette: ice glass (`#eef8fc`), a deeper cyan accent and header (`#0891b2`, `#0e7490`), white bubbles and navy text. The 3D grid, lightcycles and glow are drawn in the light style. **Dark** keeps the GRID's navy and cyan.
- A Light palette still on the navy or the old Lightcycle colours, untouched, moves to the new Light palette; a palette the owner changed is kept.

## 0.50.20 (Caches are cleared after WPBot settings are saved)
Owner, 25 November 2026: "Can we clear the WordPress cache automatically every time the plugin settings is Saved?"
- New `Settings\Cache_Purge`. A save is an admin POST by a user who may manage options, from a WPBot screen, that changed at least one option. The screen is known from its `page` argument, from the page the request was sent from, or from a WPBot admin-post action.
  - Transients, `cron` and `rewrite_rules` don't count as changes.
  - AJAX counts only for saving actions (the name says save, setting, option or update), so the heartbeat and polling never clear anything.
- Caches are cleared once, at the end of the request:
  - The persistent object cache (Redis, Memcached), when the site has one.
  - Page caches through the Asset Optimizer module's integrations, when it is bundled: WP Rocket, LiteSpeed Cache, Autoptimize, W3 Total Cache, SiteGround Speed Optimizer, WP Super Cache and WP Fastest Cache. Without that module, the most common ones directly.
  - Also Hummingbird, Breeze, Cache Enabler, Nginx Helper, WP-Optimize and WP Engine.
- Filters `qcld_wpbot_chat/purge_cache_on_save` (default on) and `qcld_wpbot_chat/is_settings_save`. Action `qcld_wpbot_chat/cache_purged` with the names of what was cleared.

## 0.50.19 (Related pages links under AI answers come from the knowledge base)
Owner, 25 November 2026: "replace the old links with the RAG based links. Whoever had the old one turned on should have this enabled."
- **The old "Check the relevant pages…" links are retired.** They came from a plain WordPress keyword search on the visitor's message, not from the answer. A message made only of stop words ("who are you?") searched for nothing, so it listed the site's newest pages. New `Ai\Related_Pages`.
  - The old switches (`page_suggestion_enabled` and each provider's `qcld_*_page_suggestion_enabled`) now always read as off. So no old path adds those links: the hosts' bridge filter, the widget's `get_relevant_pages` call after a streamed reply, and the old per-provider handlers for requests the engine passes on (attached files, Grok collections, bridge off). The saved values stay stored, so a rollback brings them back.
  - The setting is gone from the provider settings (`Provider_Settings`, `Provider_Fields`). It had no screen in the new AI Settings anyway.
- **Knowledge base source links take their place** (AI Settings → Answers → "Show source links under answers"). They list the pages the answer came from, linked to the section. Once per site: a site that had the old links on for any provider gets source links on, if they had been turned off. They are on by default, and the default is not stored, so Answers is not marked as saved.
- **Source links are more relevant:**
  - Not under a "not sure" answer. The AI said the pages it was given do not answer the question. New third parameter of `Ai_Step::show_citations()`.
  - A source is cited only when its score is at least half the best source's score, so a weak match next to a strong one is left out. Constant `Knowledge_Step::MIN_RATIO`, filter `qcld_wpbot_chat/citation_min_ratio` (0 keeps every source). At most 3 sources, as before.
- Source links need the knowledge base on for the provider that answers. Sites that used the old links with the knowledge base off get no links under answers until they turn it on.
## 0.50.18 (Lightcycle in the GRID look)
Owner, 25 November 2026: "need to update the holo design's background to match the GRID theme with 3D grid and lightcycles"; the owner chose background B (the GRID look) and shadow 2 (an accent glow, WPBot Pro module 0.47.8).
- Lightcycle (`holo`) now starts with the admin GRID's colours in Light and Dark alike: navy glass (`#071533`), cyan accent and header (`#38e1f5`), deep blue bubbles and bright text.
- A site that never changed Lightcycle's colours moves to the new palette when the theme is read; a palette the owner changed is kept (each mode on its own).
- The 3D grid, lightcycles, stars and glow are drawn by the WPBot Pro module.

## 0.50.17 ("Popular on this page" after the first question, not during the greeting)
Owner, 25 November 2026: "Popular on this page must not show during the greeting sequence - asking for name email etc. it should start showing after the 1st question and response."
- **Before:** the card went in before the first row of buttons in the conversation. During the greeting, that is the email step's privacy note ("We will never spam you!"), so it appeared between the email question and the visitor's answer.
- **Now:** the card shows once, after the visitor's first question and its answer:
  - The greeting (name, email, phone) ends with the start menu, whose buttons carry `data-qcld-menu` in both hosts.
  - The first question is the visitor's first message after the start menu, typed or tapped.
  - The card waits until the bot has answered and the messages are quiet.
  - It also waits while the bot is still collecting something. For example, the "Leave your number" button is followed by the phone question, and the card comes after the phone flow.
  - It goes just before the latest answer's buttons (Back to Start, the menu), or after the answer.
- The hosts report their conversation step through a new read-only `window.qcldWpbotChatState()` (WPBot Pro and WPBot Free chat scripts). A host without it gets the card after the first answer.
- Design screen label: "Popular on this page" after the first question (was "before the start menu").

## 0.50.16 (File upload and mic buttons beside the message: matching line icons)
Owner, 24 November 2026: "The file upload button looks hideous. Show me alternate designs. Also the mic icon should match the size with it." Four designs were shown; the owner chose A.
- The AI file upload button (WPBot Pro's chat script) and the Voice add-on's mic (`#botstartvoice`, `#botstartvoicemicro`) now share one button style: 32 px, with a 20 px line icon (stroke 1.8, like the window's other icons) in the composer's quiet colour, and a soft square on hover or keyboard focus, like Try asking.
- Before, the paperclip was a heavy filled 18 px glyph and the mic an unstyled Font Awesome glyph, so they differed in size, weight and colour.
- The hosts' glyphs are hidden and the icons are drawn with CSS masks, so neither host and not the Voice add-on changes. Light, Dark and the window's colour themes follow automatically.

## 0.50.15 (Owner's WPBot Free list, 24 November 2026: Avatar Location, site search card and Load More, Icons preview; site search relevance; chat button position)

### Site search relevance; chat button position: distances and Left
Owner, 24 November 2026: "check if site search is returning relevant results. for the chatbot icon we are missing the option to fine tune the icon position from right with pixels. this should work with previously saved settings. also, need option to show the chatbot icon on the left."
- **Site search relevance.** The database match (`LIKE '%word%'` on title, excerpt and content) is now only the first pass (up to 300 rows, title matches first). Each row is then checked against its visible text: block comments, shortcodes, tags, scripts and styles are removed, and a word must start a word.
  - Before, the page builders' markup matched: "group", "image", "button" and "large" found every block post. Words inside other words matched too: "art" found "Partners" and "quarter", and ranked "Wholesale Partners" above "Art Night".
  - Plurals find singulars ("candles" → "Candle", "policies" → "Policy"). The stem is always the start of the word, so it never loses a match.
  - Scripts written without spaces (Chinese, Japanese, Thai and others) still match anywhere.
  - "How long / much / many / often / soon": the second word is no longer a search word ("how long does shipping take" found pages that say "longer").
  - New `Search_Step::rank()`, `visible()`, `has_word()` and `stem()`.
- **Chat button position** (Customize ChatBot Icons → Position):
  - **Side of the screen:** Right or Left, stored in the new option `qcld_wpbot_chat_launcher_side`; right by default, as before.
  - **From the side / From the bottom:** these are the hosts' saved `wp_chatbot_position_x` / `_y`, with Pro's px or % unit, so a site's earlier settings are kept. When WPBot Free's Basics fields are in the same form, the boxes edit them, so one value is saved.
  - **Live preview:** it follows the fields as they change, and shows the left corner when Left is picked.
  - **Chat window:** it opens on the button's side. The floating box already follows the button.
  - **Phones:** WPBot Pro uses its phone distances (`wp_chatbot_position_mp_*`). WPBot Free keeps its own phone spot, mirrored on the left (host CSS). The hosts' full-screen chat on phones is left alone.
  - New `Widget\Launcher_Position` and filter `qcld_wpbot_chat/launcher_side`.

### WPBot Free list: Avatar Location, site search results, Load More, Icons preview
Owner's Google Doc, WPBot Free tab.
- **Avatar Location works again** ("The following options stopped working"). WPBot Free saves Inside, Side or Hidden under Customize ChatBot Design (`qcld_chatbot_avatar_location`), but the chat window that replaced the old templates (W7, 21 November) never read it. The window now gets `qcw-av-inside` or `qcw-av-hidden` (Side is the default layout, no class): Inside puts the agent picture in the bubble, Hidden leaves it out. New `Window_Render::avatar_location()` and filter `qcld_wpbot_chat/window_avatar_location`. WPBot Pro has no such setting, so it stays on Side unless the option was saved in Free before an upgrade.
- **Site search results are a card** ("Search result design"): one card in the bot bubble's colours, a row per result with its picture and an arrow, the count above; Load More is a chip like Back to Start. They showed as underlined links and a grey system button.
- **Load More gets the next page** ("Load More button not working"). The widget asks with `wpbo_default_search_pagination2`, which WPBot Free never handled, and WPBot Pro's handler searched differently from page 1. A new shim (`Ajax_Shims::search_more()`, also on `wpbo_search_site_pagination2`) answers it with the same search, page by page; later pages leave out the "We have found … results" line. Extended Search keeps its handler, as for page 1. (The hosts also fix the click handler, which threw on a missing "loading" text.)
- **Customize ChatBot Icons: the live preview is on the right**, as on Customize ChatBot Design ("On the icons show the Live preview on the right"). On narrow screens it stays on top.
## 0.50.14 (AI spending cards on one row; the file upload button shows in the chat window)
Owner, 24 November 2026: "put these cards into one line. use an arrow to scroll back and forth for more items" (AI Settings > Providers, the spending cards) and "file upload icon does not show" (chat window composer).
- **Spending cards:** one row (`.qcwp-spend-row`), each card 200 px wide, scrolled with Previous and Next arrows that show only when the cards do not fit (and are disabled at the ends); the row also scrolls with a trackpad and with the arrow keys when focused. `Spend_Screen::render_strip()`, `assets/admin/spend.js`, `assets/admin/pages.css`.
- **File upload button:** the hosts' old stylesheets (`css/common-style.css`) paint the AI file upload button white for the dark legacy templates, so on this window's light composer it was white on white. `assets/window/window.css` gives it the composer's colours, like the mic (hover: text colour on a light tint).
- Tests: botmaster spec 112 (cards row and arrows; the upload button visible against the composer).
- Versions 0.50.11 to 0.50.13 are open in other PRs (#77, #78, #79); this number follows them.
## 0.50.13 (Rate limit message: no "Array to string conversion" warning)
Support report, 24 November 2026 (WPBot Pro Master 15.9.0, PHP 8.3): `PHP Warning: Array to string conversion in src/RateLimit/Role_Limits.php:131` on AI Settings, and the rate limit message could read "Array".
- **Cause:** the old ChatBot settings save `qlcd_wp_chatbot_ai_rate_limiting_message` as a serialized list, with per-language lists inside it on some sites. `Role_Limits::message()` cast the first entry to a string, and that entry was itself a list.
- **Fix:** `message()` walks the saved value (string, serialized string, list, lists inside lists) and returns the first non-empty text, or '' when there is none. It never casts a list to a string. The value `'1'` still means "no message".
- **Saving from AI Settings** now stores a list (the new message first, other saved variants after it), the shape the old ChatBot settings and the old AI reply (`[0]` behind `is_array()`) read. Before, it stored a plain string, so those readers fell back to the default text.
- Tests: `RoleLimitsTest` (plain string, flat list, nested list, serialized string, `'1'`, and the saved list shape).
- Versions 0.50.11 and 0.50.12 are open in other PRs (#77, #78); this number follows them.
## 0.50.12 (Live chat: a thumbs down offers a person right away)
Owner, 23 November 2026: with "Only when the bot can't answer", "A thumbs down on an answer counts like a not-sure answer" and "Offer after 2 answers the bot isn't sure about", several thumbs downs never offered a person.
- **Cause:** a thumbs down only added to the not-sure count. The offer could only come under the next bot answer, and only after the minimum number of bot answers, so rating answers down never showed it.
- **Fix:** when a thumbs down brings the count to "Offer after N answers", `Escalation::thumbs_down()` returns `thumbs_down` and `POST /live/thumbs` returns the offer (`offer`: "Chat with our team" / "No thanks", or "Leave a message" when nobody is available); the host's chat script shows it under the answer. The counts start again after the offer, as after any offer. Never in Always available (the start menu has the button), never with live chat off, the rule off, or a live chat already running.
- The trigger word ("livechat") now starts a live chat in every mode; that change is in the hosts' chat script (botmaster `js/qcld-wp-chatbot-plugin.js`).
- Tests: `LiveEscalationTest::test_thumbs_downs_offer_a_person_right_away_at_the_not_sure_count`, `test_thumbs_downs_never_offer_when_switched_off_in_always_mode_or_with_live_chat_off`; botmaster spec 102.

## 0.50.11 (Start menu chips readable on hover; Site Search follows the AI; API key links)
Owner, 23 November 2026.
- **Start menu chips on hover:** with the Ask bar or Side menu layout on a glass finish (Clear, the default, and Sphere, Marble, Bubble, Frosted, Liquid, Ring, Aurora), a hovered chip kept its pale glass background under white text and could not be read. The finish rules came later in `launcher-design.css` with the same weight as the hover rules. A heavier hover and keyboard-focus rule now gives every chip the accent with white text. Tests: botmaster spec 98 (fails without the fix: background `rgba(255,255,255,0.58)`).
- **Site Search follows the AI** ("If AI is enabled and working, Site Search must be disabled. If site search is disabled, it must not show in the Start menu Available menu area"): `Ai\Site_Search_Rule` reads `disable_wp_chatbot_site_search` as "1" while a provider is switched on and can be called (it has a key, or for Ollama a server address). Saving AI Settings already set it; sites that turned AI on another way kept the Site Search button. The start menu, the builder's Available Menu items and the hosts' settings all follow; with no provider answering, the stored value applies again. Tests: `SiteSearchRuleTest`, botmaster spec 98.
- **AI Settings > Providers:** each provider with an API key links to the page where its keys are made ("Get an API key from OpenAI ↗", new tab). Tests: `ProviderKeyPageTest`, botmaster spec 98.

## 0.50.10 (Connected sites (Pro) page: option B and members-only content)
- The "What you get" list names the two features wpbot-chatbot-pro 0.45.0 added (botmaster `docs/help-center/plan.md` 8.9): each site can keep its own chatbot and AI and search the shared knowledge, and members-only content can be shared, answering only members of the site it comes from. "Public content only" is gone.

## 0.50.9 (Connected sites (Pro) page in WPBot Core's admin design)
- The Pro information page for Connected sites (WPBot Free and Starter) uses the design tokens: the "Pro" labels are emerald (`--wpb-accent-*`), not purple, and the benefits are open columns instead of boxed cards (owner, 20 November 2026: "Premium Studio", no purple).

## 0.50.8 (Chat window: each message comes in once; live chat button in the start menu; n8n and OpenAI Agent Builder leave the start menu)
Owner, 23 November 2026: "Every message from the chatbot and start menu appears twice. Once with animation and once without."
- **Cause:** the window animates a new message while it has the class `qcw-new` and takes the class away after 1.4 s. WPBot Pro's and WPBot Free's old stylesheets (`css/common-style.css`) give every message their own animation (`messageAnim`, 0.5 s, from 80 px below and half width). When `qcw-new` went away, that animation started again, so each message and the start menu faded in a second time.
- **Fix:** inside the window, messages have no animation of their own; only the window's (`qcw-new`, per design and motion setting) plays.
- Tests: botmaster spec 88, "each message comes in once" (WPBot Pro and WPBot Free).

Owner, 23 November 2026: "Live chat is not showing the Start menu even after enabling it", and "Remove these options from Start menu" (n8n, OpenAI Agent Builder).
- **Live chat button:** with live chat on and "When to offer a person" set to Always available, the start menu shows a live chat button: the menu's own Live chat item (WPBot Pro's Items builder offers it) where it has one, otherwise "Chat with our team" as the first button, so it is never under "More options" (`Escalation::menu_button()` on `qcld_wpbot_chat/menu_items_shown`, after the Context Aware page rules). It is never added in the admin, so the builder never saves it. In Bot first and Only when the bot can't answer, live chat items stay hidden, as before.
- **Live chat off:** live chat items in the start menu are hidden (before, they showed and did nothing).
- **n8n and OpenAI Agent Builder** are removed start menu items: menus that had them no longer show them, and the next rebuild of the items drops them.
- Tests: `LiveEscalationTest::test_always_mode_adds_a_live_chat_button_when_the_menu_has_none`, `test_live_chat_menu_items_are_hidden_while_live_chat_is_off`; `MenuItemsTest::test_n8n_and_openai_agent_builder_leave_the_menu`; botmaster spec 94.
## 0.50.7 (Knowledge gaps from the Help Center keep their reason)

- Train → Knowledge gaps has three new reasons: "Help Center search found nothing" (`docs_search`), "AI assistant found nothing in your docs" (`ai_search`, from the Help Center's public docs server) and "Article marked not helpful" (`not_helpful`). Until now these were all recorded as "No answer".
- Help Center and AI assistant searches keep short queries, like the site search (`Gaps::SEARCH_REASONS`).

## 0.50.6 (Chat window: WPBot Pro's cart count and product details work again)
Owner, 23 November 2026: the WooCommerce cart count no longer showed on the chat button, and clicking a product no longer opened the product details window (variation, quantity, Add to Cart). Both came back broken when the old templates retired (0.49.0): the old template styles placed them.
- **Cart count** (the host prints it next to the button, as a link to the cart page): styled in `launcher-design.css`, which loads with the page, on the button's top corner (the other corner with the button on the left), in the button colour. The button clips to its circle, so the count sits beside it, not inside. `launcher.js` (Lazy Loader) lets a click on a link next to the button through, so the count opens the cart page before the chat has loaded.
- **Product details** (`.wp-chatbot-product-container`): placed by `window.css`. Beside the window when there is room, over the window below 1100 px, full screen on phones, and over the window in embedded and full-page windows; above the window where they cover it.
- **The product details take the selected window design** (owner: "The panel design should match the selected ChatBot window design/theme"). When the panel opens, `window.js` reads the open window's look and hands it to the panel as `--qcw-pp-*`: the board's surface (background, border, corners, shadow, glass), the header for a header band (the product title and the close button move into it), the window name's font for the title, the message box for the fields and the send button for Add to Cart (outlined when the send button has no fill). So it follows every design, WPBot Pro's included, the site's colours and light or dark mode. The window tokens stay the fallback.
- Tests: botmaster spec 95, "store in the chat window" (WPBot Pro with WooCommerce, desktop and phone; the design checks in Classic and Console).

## 0.50.5 (Chat window: start menu and Back to Start buttons are chips in WPBot Free too)
Owner, 23 November 2026 (staging screenshot): in WPBot Free the start menu buttons stacked one per line, some full width and unstyled, and "Back to Start" was plain text.
- **Cause:** the window only fed colours (`--qcld-chip-*`) to WPBot Pro's `css/start-menu-chips.css`, which paints the buttons. WPBot Free has no such file, and it wraps a message's buttons in `.chat-container.wp-chatbot-msg-flat > .wp-chatbot-textanimation`, so its old template styles showed.
- **Fix:** `window.css` paints the chips itself: buttons in a button-only message (`li.wp-chatbot-msg-buttons`) and buttons placed straight in a flat message (WPBot Free's site search results end with "Back to Start"). They sit in one wrapping row, start where the message bubbles start, and use the window's chip colours (light and dark). WPBot Pro looks the same as before; the Pro designs keep their own chips.
- Tests: botmaster spec 06, "start menu buttons are chips in a row, lined up with the messages" (WPBot Free and WPBot Pro, desktop and phone).

## 0.50.4 (Chat button: page sections no longer cover it before the chat loads)
Support report, 20 November 2026 (Divi site): "The chatbot icon was not appearing at all on our site … we had to use CSS to manually override and bring an element forward for it to be clickable", since an update after 8 November.
- **Cause:** until the widget loads, `Widget\Lazy_Loader::css()` holds the chat container as an empty `position:fixed` box with no `z-index`. A fixed element always starts its own stacking context, so the icon's `z-index:999999!important` only counted inside it: the icon sat at the page's level 0, and any page section with a `z-index` over the corner (Divi sets them on sections) covered it. In "load on first interaction" mode the widget loads only when the icon itself is hovered, tapped or clicked, so a covered icon never loaded the chat. With a setting that loads the widget on idle (auto open, the icon messages…), the widget's own stylesheet gave the container `z-index: 99999` a moment later, so the icon was only covered briefly.
- **Fix:** the waiting container has `z-index: 99999`, the level the widget's stylesheet gives it, so the icon is above the page from the first paint.
- Tests: `LazyLoaderTest::test_the_waiting_icon_stays_above_page_sections`; botmaster spec 93 (a page section with a `z-index` over the icon: the icon is on top and a click opens the chat).

## 0.50.3 (Chat window: styles and script carry the module version)
Found on staging, 23 November 2026: after installing 0.50.2 the page still loaded `window.css?ver=0.49.3` and `window.js?ver=0.49.3`, so browsers and page caches could keep the old window (with the "Powered by" line and the stacked like/dislike icons).
- **Cause:** the window's assets had their own version, `Window::ASSETS`, which was not bumped in 0.50.0–0.50.2.
- **Fix:** `window.css` and `window.js` use the module version (`QCLD_WPBOT_CHAT_VERSION`), like the module's other assets; `Window::ASSETS` is removed.
- Tests: `WindowAssetsTest`; botmaster spec 88 checks the page loads `window.css` and `window.js` with the bundled module's version.

## 0.50.2 (Chat window: like, dislike, report and share in one row under the answer)
Owner, 23 November 2026 (staging screenshot): "The like/dislike icons are broken". After an AI answer the icons stacked in a column above the bubble, underlined and in the old blue.
- **Cause:** the hosts' chat script shows the row with an inline `display:block`, which beat the window's `display:flex`, so each icon took a line of its own; the row also came before the bubble (the bubble has `order: 1`), and the hosts' old stylesheets coloured and padded the icons.
- **Fix:** the row sits under the bubble (`order: 2`) and is hidden until the script shows it, then laid out as one row (`[style*="block"]` → flex). Icons take the window's muted colour (accent once liked or disliked), with no underline, padding or hover zoom. The share menu opens beside the share icon. The avatar stays beside the bubble. Same for WPBot Pro (Font Awesome) and WPBot Free (dashicons). Before, rows the script had not shown yet were visible on every message in WPBot Free.
- Tests: botmaster spec 88, "like, dislike, report and share: one row under the answer, hidden until the script shows it" (WPBot Pro and WPBot Free, desktop and phone).

## 0.50.1 (Chat window: no "Powered by" line)
Owner, 23 November 2026 (staging screenshot, Desk design): "remove this line and tighten up the space".
- The chat window no longer prints a "Powered by" line under the message box, in any design. It showed when WPBot Pro's White Label switch "Show a "Powered by" line in the chat window" (`wpwl_enable_powered_by`) was on; that switch no longer changes the window. The message box now sits at the dock's 12 px bottom padding instead of 35 px.
- Tests: `WindowRenderTest::test_no_powered_by_line_even_with_the_old_white_label_switch_on`; botmaster spec 88.

## 0.50.0 (owner, 21 November 2026: "Select Clear Glass as for button finish as Icon default when users upgrade to the latest version")
- **Clear glass is the default button finish** (theme version 5). New installs and sites with no saved finish get it. A site still on the old default, smoked glass, moves to it once on upgrade (`Launcher_Theme::upgrade()`, stored on `init`); a site that picked another finish keeps it. A site that saves smoked glass again after the upgrade keeps it.
- The "Your chat window has a new look" notice opens ChatBot Settings → Customize ChatBot Design (`&tab=design`), where the Chat window designer now is (the hosts split Icons & Themes into Customize ChatBot Design and Customize ChatBot Icons).
- Note: the designer saves every option when the settings are saved, so a site that kept smoked glass on purpose before this version also moves to clear glass once; it can pick smoked glass again.

## 0.49.4 (Chat button: "Show the icon after N ms" holds the icon hidden from first paint)
QA, 21 November 2026 (botmaster spec 65, "a delay: hidden until it has passed, then shown once"). With a delay set, the icon showed at first paint, vanished when the chat scripts started the host's timer, then came back.
- **Cause:** WPBot Pro hid the icon with a timed keyframe on `.qcld-wpbot-lazy #wp-chatbot-ball`, but the launcher keeps the icon visible with `visibility:visible!important` (an `!important` declaration beats any animation), and the chat button design's rise animation on the same element replaced the keyframe anyway.
- **Fix:** `Widget\Lazy_Loader` holds the icon itself. With `delay_wp_chatbot_floating_icon` (the hosts' option; filter `qcld_wpbot_chat/icon_delay`) above 0, the head script adds `qcld-wpbot-icon-wait` to `<html>` and takes it off once the delay has passed since page load (`performance.now()`, as the hosts' chat scripts count it). While it is there the icon is `visibility:hidden!important` and its animations are paused, so the design's rise plays when the icon shows. The icon shows once.
- Tests: `LazyLoaderTest::test_the_icon_waits_for_the_hosts_delay`; botmaster spec 65 (WPBot Pro, with and without the new chat window).

## 0.49.3 (Chat window: no empty panel behind the chat button's messages; the window sits just above the button)
Found on staging, 21 November 2026 (owner: "fix for all themes").
- **Empty panel:** the window's live chat placeholder (`.wpbot-saas-live-chat`) is hidden until a live chat starts, as the old templates did. On sites with the Live Chat add-on, its stylesheet gave the empty placeholder a size and a shadow, which showed as a white panel behind the chat button's messages.
- **Place:** the floating window now sits 12 px above the chat button and lines up with its edge, wherever the site put the button (`--qcld-wpbot-bx`, `--qcld-wpbot-by` and `--qcld-wpbot-s` from the chat button script). Before, it was fixed at 20 px from the side and at the button size plus 66 px from the bottom, so a button placed low left a gap of about 45 px. When the button and the window are on different sides, the window keeps 20 px from its side. The open window's maximum height follows the same place. This applies to every design; the Pro Side layout keeps its own place.
- Tests: botmaster spec 88, "sits just above the chat button wherever the site put it; the empty live chat panel stays hidden" (WPBot Pro and WPBot Free).

## 0.49.2 (Chat window designer: the preview shows the window on sites with a start screen)
- On sites with the start screen (WPBot Pro home screen add-on), the designer's preview stopped at it. The preview frame now starts the conversation, so the window being designed shows (found on staging, 21 Nov).

## 0.49.1 (Chat window: focus back on the chat button after closing)
- Closing the window with Esc from the message box: the conversation script focused the message box again during the closing animation, so the focus was lost when the window hid. The window script now gives the focus back to the chat button when the window has closed with the focus inside it (botmaster spec 46).

## 0.49.0 (Chat window W7: the old templates retire)
Owner, 20 November 2026 (plan W7; Q4: delete the old template files). The hosts now print only the new window; their old templates, the Extended UI module and the window background image are removed in WPBot Pro and WPBot Free.
- **`Window\Window_Notice`** (new): on sites that had the old window (`qcld_wpbot_chat_window_migrated`), administrators see once, on WPBot screens, that the chat window has a new look, which design their old theme became, and a link to the Chat window designer; dismissed per user (`qcld_wpbot_window_notice_dismissed`, nonce-checked).
- **Embedded windows** can have their own design and width (`qcld_wpbot_chat/window_render` args `design`, an available design id, and `width`, e.g. `400px` or `80%`). The design's colour tokens are printed scoped to that window's container. WPBot Pro uses it for `[chatbot-widget]` (its old HUD designs are Lightcycle, light or dark).
- Tests: `WindowNoticeTest`; `WindowRenderTest::test_an_embedded_window_can_have_its_own_design_and_width`.

## 0.48.0 (Chat window W6: the Chat window designer)
Owner, 20 November 2026 (answer D): one "Chat window" designer under Appearance with a live preview, beside the chat button designer, replacing the theme grid and the Custom Color and Fonts sub-tab.
- **`Admin\Window_Settings`** (new): printed by the hosts with `do_action( 'qcld_wpbot_chat/window_settings' )` in Appearance > Icons & Themes. Tabs Design, Layout, Colours, Material, Motion, Guidance. Every design is listed; designs and values that need WPBot Pro are greyed out with a "Pro" label (Free: Classic and Front desk). Choosing a design loads its saved defaults and keeps the brand colour where the design uses it; the brand colour builds a readable palette (the same rules as `Window_Colors`); every token can be set for light and dark.
- **Live preview:** the site in a frame (the chat button designer's nonce-checked preview), shown at desktop width scaled to the column or at phone width, with the chat opened. Colours, size and radius change at once; other changes reload the frame with the edited theme. The override applies only in a preview frame with a valid nonce, for users who may change the design.
- **Saving:** with the host's settings form; the whole theme in one field, checked against the window schema (`Window_Theme::sanitize()`), `manage_options` (the chat button designer's capability filter) and a nonce.
- Tests: `WindowSettingsTest` (schema on save, preview override needs the frame, designs and Pro marks, Pro-only values, labels for every choice, nonce and capability); botmaster spec 89. Test stubs: `is_admin`, `wp_create_nonce`, `wp_verify_nonce`, `add_query_arg`.

## 0.47.0 (Chat window W5: guidance)
Owner, 20 November 2026; conservative "Try asking" (owner Q2: only when worthwhile and relevant to this page, only on tap).
- **`Window\Window_Guidance`** (new): this page's suggestions, with no AI call. Sources: the Context Aware page rules that match the page (score 10), start menu items related to the page title (`Menu_Matcher::related()`, its own threshold; hand-off items are never suggested), and items visitors clicked on this page (3 or more clicks in 60 days, up to 6). A suggestion needs a score of 2; at most 3; nothing passes, nothing shows. Filter `qcld_wpbot_chat/window_suggestions`.
- **Popular on this page:** a card with those suggestions, once per conversation, just before the start menu's buttons.
- **Try asking:** a lightbulb in the message box, shown only when the page has suggestions; the list opens only on tap.
- **Suggestions while typing:** start menu labels and FAQ questions that contain every word typed (3 letters or more), up to 3; arrow keys and Enter choose. A menu item runs its own button; a question is sent.
- **Finish my question** (WPBot Pro): the rest of the first matching phrase in grey; Tab or the right arrow takes it. **Nudge** (WPBot Pro): once per page per visit, after 25 seconds open with no message from the visitor, the page's suggestions with one line.
- **Clicks** on start menu buttons in the window are counted per page (`POST wpbot-chatbot/v1/window/click`, rate limited, only real menu item labels, no personal data; `menu_click` events now record the page as the object and the item in data) so popularity can rank suggestions.
- The window keeps every button the conversation script's own: suggestions are copies of its start menu buttons, so its click handlers run them.
- Tests: `WindowGuidanceTest` (nothing without a reason, page rules and hide rules, related to the title, at most three and the filter, labels to items, phrases, switches and Pro values); botmaster spec 88 guidance tests.

## 0.46.0 (Chat window W3: core motion)
Owner, 20 November 2026 (plan: botmaster `docs/chatbot-window/build-plan.md`).
- **Open and close:** Morph (from the chat button) and Fade, at the window's feel (Subtle 360 ms; Off: no motion). The conversation script still toggles `active-chat-board` from many places; the window script follows that class, keeps the window on screen while it closes (`qcw-closing`) and leaves nothing behind. Visitors who ask for reduced motion get a 180 ms fade. Embedded and Full page windows do not animate.
- **Messages:** Rise for new messages (Instant: none); start-menu and answer buttons come in one after another when "Stagger" is on.
- **Thinking:** Dots replace the old typing GIF or Weave loader in the chat bubble (the owner's saved defaults use Dots; Molecules is Lightcycle's, in WPBot Pro). The old loader stays for screen readers.
- **Send:** a ripple on the send button.
- **Extension point** `window.qcwMotion` (open, typing, send) for WPBot Pro: entries are keyframes, `{ frames, scale, easing }` or `{ run }`.
- A typing row the conversation script turns into the answer becomes a normal message again (agent picture, Rise); found by botmaster spec 61.
- Fixes to W2: the message area can be focused with the keyboard (axe "scrollable-region-focusable"); the three-dot menu's texts are translatable (`Window::strings()`, printed as `qcwStrings`); the old theme reads as `template-01` only while a front-end page is built (from `template_redirect`), so WP-CLI, cron and REST read and save the real value.
- Tests: `WindowRenderTest::test_every_script_text_is_translatable`; botmaster spec 88 motion tests.

## 0.45.0 (Chat window W2: the new window on the front end, Classic and Front desk)
Owner, 20 November 2026: one window, eight designs (plan: botmaster `docs/chatbot-window/build-plan.md`). From this version the front end shows the new window in WPBot Pro and WPBot Free; the old templates stay only as a fallback until W7.
- **`templates/window.php`** (new): one markup for every design. It keeps every ID, class and hook the conversation script and the add-ons use (`#wp-chatbot-board-container`, `#wp-chatbot-messages-container`, `#wp-chatbot-editor`, `#wp-chatbot-send-message`, `#wp-chatbot-desktop-close`, `#wp-chatbot-desktop-reload`, `#wp-chatbot-email-transcript`, `render_start_menu`, `render_back_to_menu_button`, `wpbot_voice_record_wrapper`, `wpbot_voice_icon`, `ml_render_lan_dropdown`, the bottom start-menu bar). New parts use the `qcw` prefix. The header is compact (agent picture from the Agent Image setting, name, three-dot menu, close); no "Enter to send" line, no online dot, no "Powered by" unless the site opted in (`wpwl_enable_powered_by`; White Label's label is used).
- **`Window\Window`** (new): the hosts call `apply_filters( 'qcld_wpbot_chat/window_render', false, array( 'context' => 'floating' ) )` where they included an old template and print nothing themselves when it returns true. On the front end the old theme option reads as `template-01` so the conversation script takes its plain path. The colour tokens and a rule that keeps the window hidden until it opens are printed in the head (the Lazy Loader may hold the stylesheet). Filter `qcld_wpbot_chat/new_window` (default true) turns the new window off; action `qcld_wpbot_chat/window_enqueue` lets WPBot Pro add its designs.
- **`Window\Window_Render`** (new): template data (classes for design, layout, material, motion, styles; texts from the hosts' per-language options) and the tokens as CSS variables for light, dark and automatic. Filters `qcld_wpbot_chat/window_data`, `qcld_wpbot_chat/window_agent_image`, `qcld_wpbot_chat/window_transcript`.
- **`assets/window/window.css`** (new): frame, header, three-dot menu, messages (the script's own markup), start-menu chips, message box (one line, grows to 110 px), themed scrollbars, glass, Embedded and Full page layouts, a full-screen sheet on phones, Classic and Front desk (Fraunces italic, OFL, bundled in `assets/window/fonts/`). The size wins over the hosts' "Window height" CSS.
- **`assets/window/window.js`** (new, no jQuery): the three-dot menu (new conversation with a confirm, email me this chat, download as text, sound, text size, language, talk to a person), Enter sends and Shift+Enter adds a line, the first-time tip (once, can be turned off), arrows for the start-menu bar, scroll to the newest message, `qcw:open` / `qcw:close` events.
- Tests: `WindowRenderTest` (legacy IDs kept, classes, contexts, render off and already printed, tokens CSS cannot carry markup, per-language texts, agent picture, Powered by opt-in, email item, data filter).

## 0.44.0 (Chat window W1: the window theme, the owner's defaults and the upgrade from the old settings)
Owner, 20 November 2026: the new chat window (eight designs, one markup; plan: botmaster `docs/chatbot-window/build-plan.md`). This milestone adds the data only; the front end is unchanged.
- **`Window\Window_Theme`** (new): the window's whole look and behaviour in the chat button theme's reserved `window` key (`qcld_wpbot_chat_launcher_theme`), so button and window are one theme. Schema version 1: design, layout, side, size, radius, mode, 13 colour tokens for light and dark, material, motion, three-dot menu items, guidance, suggestion and start menu styles, custom fonts switch, Lightcycle variants. Every value is checked (enums, ranges, hex colours); unknown keys are dropped.
  - Pro values (Compact and Side panel layouts, Liquid glass, flowing colour, most motion, finish-my-question, nudge, extra styles) are kept when saved but replaced with the free value while `qcld_wpbot_chat/window_pro_options` is false (`effective()`).
  - Filters `qcld_wpbot_chat/window_theme`, `qcld_wpbot_chat/window_pro_options`; action `qcld_wpbot_chat/window_theme_saved`.
- **`Window\Window_Designs`** (new): the eight designs (Classic, Front desk, Poster, Till slip, Floating island, Console, Exercise book, Lightcycle). Classic and Front desk are available here; WPBot Pro makes the others available through `qcld_wpbot_chat/window_designs`. A saved design that is not available falls back to Classic.
- **`Window\Window_Defaults`** (new): the owner's saved defaults for each design (botmaster `docs/chatbot-window/defaults/`). Tokens that follow the brand colour use the chat button's colour (Classic builds its whole palette from it); Lightcycle keeps its own.
- **`Window\Window_Migration`** (new): on sites where WPBot ran before, the first window theme is built from the old settings, once (`qcld_wpbot_chat_window_migrated`): old theme to design and layout (00-03, 06 Classic; 04 Front desk; 05 Classic + Compact; horizontal Classic + Side panel; 07 black header; 08 dark), custom colours (only when they were on) to the tokens with readable text, maximum height % to pixels, font settings in use to "Use custom fonts". Old options are left in place.
- **`Window\Window_Colors`** (new): contrast checks and palettes from one brand colour.
- `Launcher_Theme::sanitize()` checks the `window` key with the window schema (it was kept as it came). The window theme is stored on `init` (`Window_Theme::ensure_stored`).
- Tests: `WindowThemeTest` (defaults, sanitize, Pro fallbacks, brand colour, migration map, colours, height and fonts, runs once, save merge, colour helpers); `LauncherThemeTest` updated.

## 0.43.0 (AI Settings in WPBot Core's admin design)
Owner, 20 November 2026: one admin design for every WPBot page ("Premium Studio", emerald; wpbot-core `Admin\Design`, guide `docs/admin-design.md` there).
- **AI Settings:** with WPBot Core's design on, the sections are underlined tabs above the page (`Design::subnav()`, the Error log tab with its count) and the page uses the full width; without it the side menu stays.
- `Diagnostics\Error_Alert::allowed()` is public (the Error log tab's count).
- CLAUDE.md: shared rules 2026-11-20 (Admin design).

## 0.42.0 (Transcript text: start-menu buttons no longer run together)
- **`Session_Store::parse_legacy()`:** flattening the legacy transcript to text used `DOMNode::textContent`, which concatenates adjacent elements with no separator, so start-menu buttons appeared as "FAQSite Search" (QA B9). A new `node_text()` helper adds a single space between two consecutive element siblings only, leaving inline formatting inside a sentence ("Hello <b>world</b>.") unchanged. This feeds the Sessions screens, CSV export and emails.
- Test: `ServicesTest::test_parse_legacy_separates_adjacent_menu_buttons`.

## 0.41.0 (Live chat notifications on any device: Web Push for agents and visitors, reply emails)
Owner, 20 November 2026: desktop and phone notifications for new chats even when the Inbox is closed, notifications for visitors, agents must not change settings.
- **`Live\Push`** (new): Web Push with the site's own VAPID keys (made on first use, kept in `qcld_wpbot_chat_live_vapid`), no outside service and no payload: a push wakes the device's service worker, which asks the site for its notes (`POST /live/push/pending` with its endpoint) and shows them. Gone devices (404/410) are forgotten.
  - Agents (`POST /live/push/agent`, devices in user meta, ten per agent): a new chat (in their departments), a visitor message in their chat or in a waiting chat nobody took (at most once a minute), a chat transferred to them; not while their Inbox is open and visible (`/live/agent/sync` sends `visible`).
  - Visitors (`POST /live/{id}/push`, opt-in in the chat window): an agent's reply while they are away from the page. Their service worker (`/?qcld_wpbot_live_sw=1`) registers on a scope no page uses, so it never replaces a site's own service worker. Filter `qcld_wpbot_chat/live_visitor_push`.
  - Reply emails: two minutes after an agent's reply, a visitor who left and gave an email address gets it by email (once per 30 minutes per chat). Filter `qcld_wpbot_chat/live_reply_email`.
  - Where an agent's notification leads: filter `qcld_wpbot_chat/live_push_agent_url`. `Live::widget_settings()` gives the chat window the public key and the worker's address.
- **Block a visitor and Add to Train are for administrators** (`/live/agent/conversation/{id}/block|train` answer 403 to other agents).
- Tests: `LivePushTest` (7).

## 0.40.1 (fix: deleting the plugins removes the live chat role and signal files too)
Found in the deactivate and uninstall test (20 November 2026): with "Delete chatbot data when the plugin is deleted" on, deleting the last WPBot plugin removed the chatbot's tables and options but left the live chat agent role, the agent capability on other roles (Administrator) and the visitors' signal files in `uploads/wpbot-live`.
- **`qcld_wpbot_chat_uninstall_live_leftovers()`** (called by `qcld_wpbot_chat_uninstall_for_host()` after the options): removes the `wpbot_live_agent` role, the `qcld_wpbot_live_agent` capability from every role, and the `uploads/wpbot-live` folder.
- Test: `UninstallTest::test_live_chat_role_capability_and_signal_files_go_too`.

## 0.40.0 (Live chat: the bot conversation reaches the agent even without Chat Sessions)
- **`Conversations::handoff_history()`:** when a chat is handed to a person and the site has no saved messages for that session (Chat Sessions off), the bot conversation the visitor's chat window sends with the start request (`history`: up to 40 messages of 1,000 characters, plain text, source `handoff`) is stored for the agent. Nothing is added when the site already keeps the session.
- **`Session_Store::add()`:** a `force` flag stores a message even with Chat Sessions off (used only for chats that reach a person).
- **REST `live/start`:** accepts `history`.
- Test: `LiveEngineTest::test_handoff_history_is_kept_when_the_site_has_no_chat_session`.

## 0.39.1 (error log: settings first, a shorter log)
Owner, 19 November 2026: with a long log, the settings were far down the page and easy to miss.
- **AI Settings → Error log:** "Error log settings" now comes before the log (after "Send it to WPBot support").
- The log shows 25 errors a page (was 50) and scrolls inside its card when it is taller than the window, with the column headings kept in view.
## 0.39.0 (AI Settings: sections from other modules)
Owner, 19 November 2026: the Help Center is switched on from AI / Settings (it is not a chatbot module). Closes part 3 of #29.
- **New filter `qcld_wpbot_chat/ai_settings_sections`** (id => [ title, dashicon, lead ]): other WPBot modules add a section to AI Settings, shown before Chatbot data. Built-in ids cannot be taken.
- The page prints the section's form (nonce, Save button); the module prints its fields on the new action `qcld_wpbot_chat/ai_settings_section` and saves them on `qcld_wpbot_chat/ai_settings_save` (runs only for a `manage_options` user with a valid nonce). Saving such a section keeps the answer cache.

## 0.38.1 (fix: no empty reply at the AI spending limit)
- At the spending limit the chatbot answers without AI. When site search found pages, `Spend\Fallback::answer()` returned the reply's text, which a search reply does not have (it answers with HTML), so the visitor got an empty bubble. It now returns the HTML when there is no text, and the owner's message or the usual no-answer text when both are empty. New `Fallback::reply_text()`.
- Test: `SpendFallbackTest::test_reply_text_uses_html_when_there_is_no_text`.

## 0.38.0 (AI Actions: choice and range fields, answer buttons, conditions)
Store plan step 7b (botmaster `docs/store/plan.md` section 11.8, "Can it run on AI Actions? Yes, with an extension"). Additive: Actions without typed fields or conditions behave exactly as before.
- **New `Actions\Fields`.** A field is still `{ name, label, required }`, with new optional keys:
  - `type`: `text` (default), `choice` or `range`.
  - Choice: `options` (`[ { value, label } ]`, or plain strings) and `multiple` (several answers, stored as `"a, b"`).
  - Range: `min`, `max` and `step` (the number is clamped and rounded), or `bands` (`[ { value, label, min, max } ]`, preset buttons such as budget bands; a number picks its band).
  - `condition`: `{ field, values[] }`. The field is asked and kept only when an earlier answer is one of the values (any answer when `values` is empty).
- **Tool schema:** choice fields become `enum` (`array` of `enum` when multiple), banded ranges `enum`, number ranges `number` with `minimum` and `maximum`. Conditions are written into the description. Typed Actions send no `required` list: the runner asks for what is missing.
- **Runner (`Actions::run()`):**
  - Answers are checked against the options; invalid values and fields whose condition is not met are dropped (`Actions::data()`).
  - While a shown, required field has no answer, the tool returns "ask this ONE question" with `choices` (`field`, `label`, `multiple`, `options`), and nothing runs. New `Actions::ask()`.
  - The tool description of a typed Action tells the AI to call it early, so it learns the next question.
- **Buttons in the widget:** `Ai_Step` passes `choices` on as reply data. `Legacy\Ai_Bridge::choices_html()` renders them as the widget's quick replies (`<span class="wpb-quick-reply qcld-chat-common qcld-chatbot-choice-option" data-value>`), so a click sends the option as the visitor's answer in WPBot Free and Pro, with no new script. The hosts' chat button component (contract C-4) adds keyboard access and the used state.
- Tests: `ActionFieldsTest` (9 tests: normalizing, choice and range values, conditions, clean answers, tool schema, asking with buttons, unchanged untyped Actions, button HTML).

## 0.37.0 (visitor identity and chat events: shared contracts C-1 and C-5)
Store plan step 3 (botmaster `docs/store/plan.md`), agreed with the Live Chat / Start Menu work (`docs/live-chat/store-coordination.md`).
- **Visitor identity (`Visitor\Visitor`, C-1):**
  - One random visitor key per browser in a signed first-party cookie (`qcld_wpbot_vk`; a browser cannot choose another visitor's key), set only in chat requests (AJAX/REST), never on cached pages.
  - Chat AJAX requests (`identify_ajax()`, filter `qcld_wpbot_chat/visitor_action`) link the key to:
    - the logged-in user
    - Site Insight's visitor and session, sent by the widget only where Site Insight runs, i.e. after statistics consent. The hash is computed as Site Insight computes it, so the two join.
  - Later steps link the store customer.
  - Table `{prefix}qcld_wpbot_chat_visitors`. Generic: live chat and the Inbox use it too.
- **One-time email codes (`Visitor\Verification`):** a 6-digit code emailed, stored as a hash for 10 minutes, 5 tries, 5 codes an hour per visitor (`verify` bucket). On success the visitor is a verified customer for that email for 30 days (`Visitor::is_verified()`). The answer never says whether an email is a customer's.
- **Chat events (`Visitor\Events`, C-5):**
  - **Types:** knowledge_gap, handoff, menu_click, typed_instead, livechat_closed, product_view, product_search, add_to_cart, checkout_start, finder_no_match, finder_result, offer_shown, order_attributed. Filter `qcld_wpbot_chat/event_types` adds more.
  - **Shape and storage:** one payload shape (type, time, visitor, session, user, channel, object_type, object_id, value, data without personal data), stored in `{prefix}qcld_wpbot_chat_events`.
  - **Announced** on `qcld_wpbot_chat/event`.
  - **Knowledge gaps** are recorded as events too.
  - **Pruning:** after 180 days (filter `qcld_wpbot_chat/events_keep_days`).
- **Privacy:** personal data export and erase for visitor links and events (`Visitor\Visitor_Privacy`). Uninstall removes the tables, the option and the prune event with the module's other data.
- Tests: `VisitorIdentityTest` (signed cookie, tampered keys refused, Site Insight hash, email hash, event shape without personal data, contract event types, unknown types not recorded).

## 0.36.0 (Live chat history view, LC7)
Needs 0.35.0.
- **`Conversations::history()`** and the agent route `GET /live/agent/history?search=`: closed conversations, newest first, imported classic chats included, searched by the visitor's name or email; agents see their departments' and their own, managers all.
- **Agent summary:** `imported` (a read-only chat brought over from the classic live chat).
- Tests: `LiveEngineTest::test_history_lists_closed_chats_and_searches_names_and_emails`.

## 0.35.0 (Live chat: AI assistant for agents, flags, actions, answers while waiting; LC6)
Live chat plan 4.2; owner D8 extras (AI answers while waiting, sentiment and urgency flags, Automator and CRM events). Needs 0.34.0.
- **`Live\Assistant`:** for agents: a suggested reply (with the knowledge base when available), summarize, rewrite shorter or friendlier, translate; the AI Settings provider answers (`feature` live_assist) and the cost comes back with the text. Agent route action `assist` (task, text, lang).
- **Flags:** urgent and unhappy visitor messages (word checks, no AI call), stored on the conversation (`flags`, schema 2) and shown in the agent summary.
- **Actions:** block a visitor (only a hash is stored; a blocked visitor can't start a live chat), add an agent's answer to Train (the visitor's first question, answered), email the transcript.
- **While a visitor waits:** an answer from the knowledge base only (background job, at most once a minute per chat, "NONE" when the knowledge doesn't answer), when `qcld_wpbot_chat/live_wait_answers` is on (WPBot Pro setting); stored as a "bot" message.
- **Automation and CRM hooks:** `qcld_wpbot_chat/live_handoff`, `live_closed`, `live_rated`.
- Tests: `LiveAssistantTest` (4).

## 0.34.0 (Live chat: agent channel support, LC4/LC5/LC5b)
Needs 0.33.0.
- **`Conversations::assign()`** takes the name and channel of someone in an agent channel (Slack, Telegram, Teams) without a WordPress account; `close()` takes the channel too.
- **No echo:** an agent channel never gets back what came from it (a reply, a claim, a close), whatever the event type.
- Test stubs: the fake database has `replace()`; `trailingslashit()` and `untrailingslashit()`.
- Tests: `LiveEngineTest::test_a_channel_agent_takes_a_chat_under_their_own_name`.

## 0.33.0 (Live chat: escalation rules and the visitor side, LC2 / CA4)
Owner D9 (19 November 2026): bot first, offer after 2 not-sure answers, deflect a direct request once, at least 2 bot answers. Needs 0.32.0.
- **`Live\Escalation`:** the offer ("Chat with our team" / "No thanks") appears inside a bot reply only when a rule fires: not sure again (after N, default 2), a request for a person (deflected once), a thumbs down (counts as not sure), topics that always need a person (off), at least N bot answers (default 2; not for topics). Modes: bot first, only when the bot can't answer, always available. With nobody available the offer is "Leave a message" (the widget's email form). WPBot Pro can add "valuable visitors" on `qcld_wpbot_chat/live_offer_now`. Counts per chat session in a transient, only when live chat is on.
- **`qcld_wpbot_chat/reply_extras`:** filter for HTML under an AI answer, with the widget request (session, question); the escalation offer uses it.
- **Start menu:** live chat items (the old "livechat" keyword or `data-live`) show only in "Always available" mode when the new live chat is on.
- **REST:** `POST /live/thumbs` (chat token) records a thumbs down for the escalation rules.
- Tests: `LiveEscalationTest` (8).

## 0.32.0 (Live chat engine, LC1)
Live chat rebuild, owner decisions D1, D6 and "Free and Pro" (19 November 2026). Off unless `qcld_wpbot_chat/live_chat_enabled` returns true (WPBot Pro). Needs 0.31.0.
- **Tables (`Live\Schema`):** `qcld_wpbot_chat_conversations` (status, department, agent, visitor, page, timings, rating, last event; indexes status+department, agent+status, session, updated), `qcld_wpbot_chat_events` (conversation+id) and `qcld_wpbot_chat_agent_links` (channel+remote id, unique). Created on an admin page only when live chat is on. Messages stay in the chat messages table (`source` = live), next to the bot's, so agents always see the bot history.
- **Conversations:** start (one running conversation per chat session), message, assign, transfer (agent or department), close, rate, transcript (bot and live messages), summary for agents.
- **Events:** messages, queued, assigned, transferred, closed, notes (agents only), rating. Typing lives 6 seconds in a transient, never in the database.
- **Light poll for visitors (`Live\Signal`):** a static `uploads/wpbot-live/<signed>.json` file holds the last event id; the widget calls the REST API only when it changes (fallback: short REST polls).
- **Agents:** administrators and the `qcld_wpbot_live_agent` capability (`qcld_wpbot_chat/live_is_agent`); presence in one small option, written at most every 30 seconds per agent; departments from `qcld_wpbot_chat/live_agent_departments`.
- **Routing:** `qcld_wpbot_chat/live_available` (an agent online; Pro adds office hours and channel teams) and `qcld_wpbot_chat/live_route` (auto-assign; default: queue).
- **Agent channels:** the `Live\Channel` interface and registry (`qcld_wpbot_chat/live_channels`); events reach them in the background (Action Scheduler, else a one-off cron event); a channel's own message is never echoed back to it; conversation ↔ remote thread links.
- **REST (`wpbot-chatbot/v1/live`):** visitor start (chat token, rate limited), events, message, typing, end, rate (signed visitor key per conversation); agent sync (one call for presence, conversations and new events), conversation (with bot history and notes), claim, message, note, transfer, close, typing.
- **Uninstall:** the new tables and options share the module prefix; the start menu options are removed too.
- Tests: `LiveEngineTest` (10).

## 0.31.0 (Context Aware Start Menu: page rules and settings, CA3)
Owner, 19 November 2026 (approved plan sections 2.1, 3.1). Needs 0.30.0.
- **`Menu\Menu_Rules`:** page rules for the first menu: show an item first, show it only, or hide it where its conditions match, with an optional line above the menu. Conditions use the shared conditions library (contract C-2) through `qcld_wpbot_chat/menu_rule_matches` (WPBot Pro); without an evaluator, rules never match. "Order the rest by page" ranks the other items by the page title with the item keywords (no AI call).
- **`qcld_wpbot_chat/menu_items_shown`:** filter on the items of a shown menu (`Menu_Items::render()`); the rules use it on the front end and in chat requests, never in the builder.
- **Settings:** saved with the ChatBot Settings form (switch, page-aware, order the rest, max buttons, threshold, "More options", rules, item keywords per language). "Related buttons" under Behaviour keeps Context Aware on.
- **Preview:** `wp_ajax_qcld_wpbot_menu_preview` shows the first menu for a described page and the related buttons for a question, with unsaved rules. Nothing reaches visitors.
- **Version 1 migration (C5):** `qcld_dynamic_ai_menu` = 1 switches "Buttons after answers" on once, when there are no version 2 settings.
- Tests: `MenuRulesTest` (6).

## 0.30.0 (Start Menu > Items builder, SM2)
Owner, 19 November 2026. Needs 0.29.0.
- **`Menu_Items::admin_html()`:** the menu of one language for the Items builder: every item in that language's order, hidden ones included, with markers: `data-qcld-id`, `data-qcld-builtin`, `data-qcld-hidden` and `data-qcld-off` (a built-in item whose feature is switched off).
- **The builder saves hidden flags itself:** a saved menu whose spans carry `data-qcld-id` sets `hidden` from `data-qcld-hidden="1"`, and showing an item again clears it. Menus saved by the old builder keep the earlier rule (hidden flags kept by item).
- **Builder markers are never stored** (`ADMIN_MARKERS`). `data-qcld-icon` and `data-qcld-group` are kept: the chat shows the icon before the label and the group as a heading.
- Tests: `MenuItemsTest` (2 new).

## 0.29.0 (Start Menu > Behaviour: four questions, SM3)
Owner, 19 November 2026 (M5: home button and bottom bar unchanged; M2: 4 top-level buttons). Needs 0.28.0.
- **`Menu\Menu_Behaviour`:** "When the chat opens" (name then menu, name only, menu right away, first message only, start an intent) and "After each answer" (related buttons, whole menu, Back to Start, nothing) are stored in the options the widget already reads, so the chat behaves as before.
  - `current()` reads the answers from the old checkboxes. Every old combination maps to the answer that describes what the widget did with it. "Skip greetings and trigger an intent" without "Do not show the start menu in the beginning" showed the menu first and never started the intent; it reads as "menu right away", with `intent_hidden` set so the page can say so.
  - `options_for()` gives the old option values for an answer; "start an intent" also turns off the menu at the start, so the intent really starts.
  - `save()` rewrites only the options of an answer that changed (old combinations stay as they are until the owner picks another answer). "Related buttons" switches Context Aware's "Buttons after answers" on, and another answer switches it off; the other Context Aware settings are kept.
  - "Related buttons" is offered only when `qcld_wpbot_chat/menu_related_available` returns true (WPBot Pro).
  - `qcld_wpbot_menu_top_count`: top-level start menu buttons, 2 to 8 (default 4).
  - Saved with the ChatBot Settings form (`admin_init` priority 11, after the host's save; same nonce and capability). The page posts the old options back as hidden fields (`old_options()`), so the host's save keeps them.
- Tests: `MenuBehaviourTest` (23).

## 0.28.0 (Chat buttons: start menu marker for the top-level menu, SM5)
Owner, 19 November 2026 (M2: 4 top-level buttons, the rest under "More options"). Needs 0.27.0.
- **Start menu buttons carry `data-qcld-menu="1"`** when the menu is shown (`Menu_Items::render()`). The hosts' chat button component (botmaster `js/qcld-chat-buttons.js`) uses it to show the first top-level items and put the rest under "More options". Other button lists (FAQ questions, related buttons, handoff) are left alone.
- **The marker is never stored:** the admin builder saves the shown HTML back, and `data-qcld-menu` is dropped then.
- Tests: `MenuItemsTest::test_start_menu_buttons_carry_the_menu_marker_once`; render expectations updated.

## 0.27.0 (Context Aware Start Menu: related buttons in the answer, CA2)
Owner, 19 November 2026 (C3: related buttons plus "More options"). Needs 0.26.0.
- **`Legacy\Ai_Bridge::related_html()`:** an answered AI reply with `next_steps` of type `related` ends with `<div class="qcld-wpbot-related">`, made of three parts:
  - a "Related" title;
  - the related items as the widget's own start menu buttons, so their click handlers run them;
  - "More options", the widget's Back to Start button, which shows the whole menu. Setting `more`.

  Streaming replies get it in the last chunk. Not-sure replies keep the handoff buttons.
- **New `Menu_Items::render_ids()`:** chosen items in a given order; unavailable ids are skipped.
- Hosts: the widget skips repeating the whole menu after an answer that has `.qcld-wpbot-related` (botmaster `js/qcld-wp-chatbot-plugin.js`, `css/start-menu-chips.css`).
- Tests: `MenuMatcherTest::test_related_buttons_in_the_reply_reuse_the_widget_buttons_and_offer_more_options`.

## 0.26.0 (Context Aware Start Menu, version 2: the matcher, CA1)
Owner, 19 November 2026 (botmaster `docs/chatbot-features/context-aware-start-menu.md`, approved C1–C5, and M2). Needs 0.25.0 (`Menu_Items`).
- **New `Menu\Menu_Matcher`:** picks the start menu items closest to a question and its answer, **with no AI call**. It scores each item by word overlap (question words count twice) with light stemming and stop words. Items are read from their profile (`qcld_wpbot_menu_profiles`: description and keywords per language, written once by AI and editable, CA3), else from their label plus default keywords for built-in items.
  - Only available items match. Handoff items (live chat, email us, call me) never show as related buttons: the escalation rules offer them. Filter: `qcld_wpbot_chat/menu_item_handoff`.
  - Relevance threshold low, medium or high; at most 3 by default, never more than 4 (the top-level places, M2).
  - Pro adds vector similarity through `qcld_wpbot_chat/menu_match_score`.
- **Answered AI replies** carry `next_steps` of type `related` (label and item id) when Context Aware "Buttons after answers" is on (`qcld_wpbot_context_menu`). Not-sure replies keep the handoff buttons. Off by default (C5).
- Tests: `MenuMatcherTest` (6 tests).

## 0.25.0 (Start Menu items stored as data: Start Menu reorganization SM1)
Owner, 19 November 2026 (botmaster `docs/chatbot-features/start-menu-review.md`, decisions M1–M5): one start menu model for WPBot Free and Pro.
- **New `Menu\Menu_Items`:** the Active Menu is kept as data in `qcld_wpbot_menu_items` (not autoloaded): one entry per item with its key, type, built-in id, the span's attributes and a label per language. It is built once from the old HTML menu (`qc_wpbot_menu_order`, a string, a serialized string or one HTML per language), with the builder's drag classes, repeats and the **GoodBye** button dropped (M3).
- **Built-in items** (site search, FAQ, email, feedback, call me, subscription, STR categories, voice message, Messenger, WhatsApp, n8n) take their label from their own text setting, so a rename shows at once in every language, with no remove-and-add. They leave the menu when their feature is switched off. Filter `qcld_wpbot_chat/menu_builtins` adds built-in items.
- **Each language keeps exactly its items and order** from the old menu; a language with no menu of its own gets every item.
- **Readers are unchanged:** `qc_wpbot_menu_order` is filtered (priority 20) to the HTML built from the items, in the stored shape. The old admin builder keeps working: a save rebuilds the items and keeps hidden ones hidden. The stored HTML is never changed, so a downgrade still finds the old menu.
- **Safer output:** only `class`, `title` and `data-*` attributes are kept from stored HTML, and labels are plain text.
- Filters: `qcld_wpbot_chat/menu_item_available`, `qcld_wpbot_chat/menu_item_label`.
- A built-in item keeps the menu's own label while its setting says the same thing apart from case or variants (a fresh site stores "faq" in the setting and "FAQ" in the menu); a real rename shows at once.
- Tests: `MenuItemsTest` (9 tests).

## 0.24.0 (shared knowledge base: Free-side hooks and the Connected sites (Pro) page)
Owner, 19 November 2026: the shared knowledge base (hub and source mode) is a WPBot Pro feature in Professional and Master; WPBot Free and Starter get neither the feature nor its code. It lives in wpbot-chatbot-pro (pack `kb-hub`). This module keeps only general hooks and an information page.
- **ChatBot → Connected sites (Pro)** (`Admin\Connected_Sites_Teaser`): in packages without the feature (WPBot Free, WPBot Pro Starter) the menu item is shown marked Pro, with the benefits and features and which packages have it. An information page only: no feature code, no locked controls. Packages with the feature return true from the new filter `qcld_wpbot_chat/connected_sites` and show the real page instead.
- **New filter `qcld_wpbot_chat/ai_bridge_steps`**: the steps that answer the legacy widget's AI questions (default: knowledge base, then AI). Pro puts its hub step first.
- `Legacy\Ai_Bridge` passes the widget's session to the engine as `legacy_session` (not stored here).
- **Train → Knowledge gaps by site** (module schema 8, additive: `site` on the gaps table): gaps keep the site they came from (filter `qcld_wpbot_chat/gap_site`); with questions from more than one site, Train shows a Site filter and a site label on each gap (filter `qcld_wpbot_chat/site_label`). `Gaps::items()` takes an optional site; new `Gaps::sites()`, `Gaps::site_label()`.
- New `Kb_Bridge::chosen_post_types()` and `Kb_Bridge::auto_sync()`.

## 0.23.0 (not released)
Source mode was added here and moved to wpbot-chatbot-pro before any release (owner's decision in 0.24.0).

## 0.22.0 (shared knowledge base SK1: page context on connected sites)
Owner's go, 18 November 2026 (botmaster `docs/help-center/plan.md` section 8).
- **Page context for pages the knowledge base holds** although they are not posts on this site, such as pages of a site connected to a shared knowledge base hub: the AI gets the page's title, address and text from WPBot Core's knowledge base (`qcld_wpbot_core_kb_find_url()`), never from the browser, and only when the visitor's labels allow it (`Page_Context::known_page()`). Unknown pages still give only the title and address. Needs WPBot Core's SK1 building blocks (https://github.com/qcld/wpbot-core/pull/57); without them nothing changes.

## 0.21.8 (error log: "WPBot" or "Outside WPBot" on every error)
Owner, 19 November 2026: log every error, but mark clearly which come from WPBot and which from outside WPBot. Needs WPBot Core's `Diagnostics\Origin` (the screen looks as before with an older WPBot Core).
- **AI Settings → Error log:** a new "From" column shows a "WPBot" pill (with the WPBot part and the kind of error) or an "Outside WPBot" pill (with the part of the site, such as "Jetpack (plugin)", "Foodie Blocks (theme)" or "WordPress"). Outside errors say "Not a WPBot error: it came from … WPBot only recorded it." Errors recorded before WPBot Core told them apart show "Not known". The summary counts both ("9 errors: 3 from WPBot, 5 from outside WPBot"), and a new filter shows errors from WPBot, from outside WPBot, or of unknown origin. The kind "Outside service" is now "Request to a service", so it is not mistaken for "Outside WPBot".
- **Error log settings:** new checkbox "Errors from outside WPBot too: other plugins, the theme and WordPress" (on by default; greyed out with the other options while "Record errors" is off). The form posts `outside_sent=1` with it.
- **Error alert:** only WPBot's own errors count for the red "WPBot recorded N errors" alert and the menu bubble.
- **Phones:** the error log rows stack as cards below 600px wide.
- Tests: `ErrorAlertTest::test_only_wpbot_errors_count_for_the_alert`, `ErrorLogScreenTest::test_origin_labels_and_the_request_kind`.

## 0.21.7 (Error log settings: "Record errors" and alert switches)
Owner's brief, 19 November 2026: switch WPBot's error log off without touching the chatbot, and turn the red error alert off. Needs WPBot Core with `Error_Log::enabled()` (wpbot-core#63); with an older Core the Record switch is not shown and the alert works as before.
- **AI Settings → Error log:** the card "What to record" is now **Error log settings**, with **Record errors** (WPBot Core's `record` setting) and **Show an alert when WPBot records errors** above the browser errors and PHP notices boxes. While Record errors is unticked, the other three are greyed out (small inline script); without JavaScript the server ignores them while recording is off. The form posts `record_sent=1` and `alert_sent=1`.
- **Alert switch:** new option `qcld_wpbot_chat_error_alert` (`'1'`/`'0'`, default on, autoloaded) and filter `qcld_wpbot_chat/error_alert_enabled`. `Error_Alert::allowed()` (menu bubbles, bubble CSS, the Error log section bubble, the notice) also needs the switch on and WPBot Core's `Error_Log::enabled()`; when either is off, `summary()` runs no query. New `Error_Alert::enabled()`, `log_enabled()`, `active()`, `posted_setting( $post )`; `save_setting()` runs on `admin_post_qcld_wpbot_core_errors_settings` at priority 5 (nonce `Support_Report::NONCE`, `manage_options`), then Core saves its keys and redirects.
- **Log off:** the log card says "The error log is off. Nothing new is being recorded." and keeps the existing entries; an empty log says "No errors in the log." instead of "running without problems"; the support card notes the report has no recent errors. Help text: "When recording is on, these are always recorded: …".
- Tests: `ErrorAlertTest::test_alert_switch_option_and_filter`, `::test_alert_setting_from_the_form`.

## 0.21.6 (QA 9.1: only the best sources are cited)
- **Fewer, more relevant sources.** The source list under a knowledge answer showed every retrieved chunk, so loosely-related pages appeared (Careers under store hours, Wholesale under shipping). It now shows the top 3 by score (`Knowledge_Step::MAX_CITATIONS`, filter `qcld_wpbot_chat/max_citations`; 0 = no cap). Covered by `CitationsTest::test_only_the_top_sources_are_cited`.

## Unreleased (QA of WPBot Free 9.1)
- **Site Search finds products and words in the text.** With WooCommerce active, products are searched too (they were always left out). Excerpts and content are searched as well as titles unless the owner saved "Search the content as well as the title" off (`active_advance_query` '0'); title matches still rank first, and one word ("duvet") is enough to match. Filter `qcld_wpbot_chat/search_content`; WooCommerce detection: `Options::woocommerce_active()` (filter `qcld_wpbot_chat/woocommerce_active`).
- **Train → Knowledge gaps fills up.** The knowledge instructions now ask for the not-sure marker in the same words as the safe-answer rule (they told the model to say "I don't have that information" instead), and the industry presets no longer give a literal refusal sentence. An AI reply that says it does not know without the marker ("I don't have information on…", "I'm not sure", "I couldn't find…"; short replies only) counts as not sure: handoff buttons, Questions Not Answered and the `knowledge_gap` action ('not_sure'). Filters `qcld_wpbot_chat/not_sure_phrases` and `qcld_wpbot_chat/reply_not_sure`.
- **Site Search misses reach Train,** one-word ones included (new gap reason `search`, "Site search found nothing"): the widget's unanswered log after a search miss (remembered for ten minutes per visitor) and the engine's fallback after a search miss record a search gap.
- **Product pages send their price.** "Use the current page" adds the product's current price in the store's currency (sale and regular price, or the range of a variable product) and its stock status. It is on by default for WooCommerce shops whose owner never saved the setting (not stored; a saved value always wins).
- **No dead end when no AI can answer.** The error reply carries the same contact buttons as a "not sure" answer (also on its own `handoff` key in the JSON), in the JSON and streamed replies.
- **E-commerce preset:** no promise of order tracking (order status: the shop's contact details), never a price that is not in the context (link to the product page instead), even indentation.
- **Knowledge base panel:** "Knowledge from the previous version has been moved" shows only when items were moved (WPBot Core's `migrated_count`, or `migrated_to` with an older Core). New **Empty the knowledge base** button (confirm, admins only) uses `qcld_wpbot_core_kb_remove_all()` when WPBot Core has it, else removes every source type; the answer cache is cleared on `qcld_wpbot_core/kb_emptied`. **Sync now** shows "N of M items read…" every few seconds, then "Finished at 15:42", and refreshes the summary (stops asking after ten minutes).
- **Error alert:** requests from the site to itself (background self-checks such as the llms.txt and upload protection tests) no longer count for the red "WPBot recorded N errors" alert. Filter `qcld_wpbot_chat/error_alert_counts`.

## 0.21.5 (security: stored XSS in the saved transcript)
- **Chat Sessions transcript escapes message text.** `Session_Store::message_html()` ran visitor/bot message text through `wp_kses_post()`, which turned a value like `< div data-spy="affix" data-target="…">` (left intact by `sanitize_textarea_field()` because it is not a tag) into a real `<div>` with its `data-*` attributes, stored in the legacy transcript. A host screen that allows `data-*` and loads Bootstrap's affix data-api could then run `data-target` as script in an admin's session. A message is plain/Markdown text (`add()` keeps HTML as text), so it is now escaped with `esc_html()` and never rendered as HTML. Covered by `ServicesTest::test_message_html_keeps_html_as_text`.

## 0.21.4 (owner, 18 November 2026: "i think we have duplicate icon settings")
- **One icon picker.** The designer was showing the hosts' Icons list a second time. It is now the only place to pick the chat icon: the hosts' Icons list and its upload row are hidden (they stay in the form, so saving works as before). The designer has **Upload your own icon**, which opens the media library through the host's own uploader; the new picture is picked and shows in the preview before saving (`Launcher_Preview` reads `qcld_wpbot_icon_path` for admins with the preview nonce).
- The drawn icons no longer repeat the five the Icons list already has (molecule, chat bubble, smiling bubble, two bubbles, headset): 9 more icons are offered under "More icons drawn in your color".

## 0.21.3
- **Designer swatches in the dark admin theme.** The admin theme repainted the finish swatches with its own flat color, so most finish cards showed an empty circle; the swatches and the color dots now keep their real colors (`data-qa-skip`). Spec 71 checks the swatches.

## 0.21.2 (owner, 18 November 2026: "Remove badge. It makes it too busy. Replace Now with B. Also, add C and D as options.")
- **No unread badge.** The red "1" on the button while the floating box waited is gone (no setting); the floating box alone shows there is a message.
- **Glossy sphere is see-through.** It was a deep blue body that the blue logo blended into; it is now tinted glass at about half strength, so the icon shows and the page shows through softly. Sites that picked it keep the same value and get the new look.
- **Two new finishes:** `marble` (Glass marble: a clear ball with your color only at the rim) and `bubble` (Soap bubble: almost clear, with a rainbow rim). Drawn icons and the close X take the theme color on both; the floating box is clear glass, as with the sphere.

## 0.21.1 (owner, 18 November 2026: "In the Classic mode the options are washed out and do not work", "the preview window is too small", "Open and close preview not working", "Animations are jerky")
- **Options that did nothing on the site now work.** 27 rules had their page selector written twice (`html.qcld-wpbot-d2… html.qcld-wpbot-d2 #wp-chatbot-ball`), so they never matched: the frosted, liquid glass, glass bezel and aurora buttons, the white and no-button styles, the slide and soft square open and close, and the left-side label and typing bubble. Spec 71 now checks that each finish and style draws the button differently on the site.
- **Smooth motion.** The waiting motions on the button (gentle float, heartbeat, bounce, alive) used the same property as open and close, so opening jumped straight to the X and the button snapped back into place. They now use `translate` and `scale` and glide back when the chat opens; with Gentle float, Flip turns the button over frame by frame. The floating box is placed from the button's resting place, so it no longer moves with the button's own motion.
- **Designer.** In Classic the cards are no longer faded, and picking one switches to the new design. The preview is a real 1100 px desktop page (the chat opens as it does on a computer), showing the bottom-right corner at nearly real size so the open chat window and the floating box show whole; Phone width shows the full phone. "Open or close the chat" works in the preview (the widget loads at once there, and when the button is hidden the chat closes with its own close button).

## 0.21.0 (owner, 18 November 2026: chat button designer with every preview page option)
- **Designer.** Icons & Themes has one "Chat button and floating box" screen (`Admin\Launcher_Settings`): the site's own front page in a live preview (`Widget\Launcher_Preview`, admins with a nonce only) and cards for the design, icon, finish, style, color, size, page load, while it waits, open and close, floating box and typing dots. Small changes show in the preview at once (sent to the frame), layout and icon picture changes reload it. The values save with the host's form, as before.
- **Every option of the preview page** (theme version 4): button style solid, white or no button; finishes frosted, liquid glass, glass bezel and aurora; while it waits glow, float, orbit, light sweep, shine, heartbeat, bounce, "Chat with us" label, typing, hello wave, flip to "Hi!" and alive; page load pop, drop, spin and fade; open and close slide and soft square; floating box speech bubble and compact.
- **14 vector icons** (`Widget\Launcher_Icons`) drawn in the theme color: the molecule, chat bubble with dots, smiling bubble, two bubbles, headset, robot, AI spark, question bubble, plain bubble, AI chat bubble, heart bubble, light bulb, envelope and microphone. The hosts' icon-3, 9, 10 and 11 pictures are the same drawings and show as the vectors on the new design.

## 0.20.1 (owner, 18 November 2026: "the selected chatbot icon changed")
- **The button shows the icon selected in the Icons list, or the uploaded one.** Version 2 of the theme showed its own icon setting (the animated logo or the molecule) instead, so a picture the site owner had selected was replaced. The theme's `icon` is now `site` by default (version 3), and every older theme moves to it once; the rest of the theme is kept. In the list, "WPBot Logo" shows as the animated logo without its white background and the molecule is drawn in the theme color; uploaded icons and icon videos are always kept. `logo` and `molecule` stay available to force those icons (theme generator).
- The settings' Icon row now says where the icon comes from instead of offering a second choice; the "Try the new chat button" notice says the selected icon is kept.

## 0.20.0 (owner, 18 November 2026: more chat button choices, new default)
- **New default** (the owner's pick): smoked glass, the **animated WPBot logo**, no motion while it waits, **Rise** on page load, **Turn** to open, and the **Chat preview** floating box (the greeting and the message as chat bubbles from the button, the quick menu under them) with typing dots first.
- **More choices** in Icons & Themes, all part of the theme object (`Widget\Launcher_Theme`, version 2):
  - icon: `logo` (the animated WPBot logo, `assets/img/wpbot-logo.webp`, transparent so it sits on every finish), `molecule`, or `site` (the picture chosen in the host's Icons list). Uploaded icons and icon videos are always kept (`Launcher_Theme::icon_shown()`);
  - finish: `smoked`, `clear` (clear glass), `sphere` (glossy sphere), `flat`;
  - motion: wait `none` or `liquid`; page load `rise`, `roll` or `none`; open and close `turn`, `zoom` or `flip`;
  - floating box: `preview` (Chat preview), `card` (Card with quick replies), `pills` (Floating pills), `ask`, `side`, `off`.
- **Version 1 themes:** a site still on version 1's untouched defaults (smoked, Liquid, Roll in, Zoom, Ask bar) moves to the new default once (`Launcher_Theme::upgrade()`, stored on `init`); a box turned off stays off, the color, size and design are kept. A site that changed any of them keeps its look and the icon it showed.
- The "Try the new chat button" notice describes the new default.
- **The chat icon and agent pictures are never shown broken** (`Widget\Image_Fallback`, every design and template): a small script printed first in the head swaps a chat image that fails to load for the default icon (`icon-0.png`) or agent picture (`agent-0.png`), and hides it if that fails too. Filter `qcld_wpbot_chat/image_fallbacks`.
- **No more stepping box.** The classic design's float (`floatingbot_delay` on the chat container) kept moving the button up and down, so the floating box was re-placed in steps every 400 ms; the new design's button now only moves with its own motion settings, and the box shows only once it has been placed (found by spec 68 "the box appears in its place and stays there").

## 0.19.1 (owner, 17 November 2026: "the icon and notification area appearance are choppy")
Recorded on a site with the lazy launcher: the icon rolled in, vanished for about two seconds, came back and jumped twice; the floating box popped in and then jumped.
- **The icon keeps its place while the widget loads.** The widget's stylesheet arrives before its scripts and moved the icon 50 px (where the page could cover it); `Widget\Lazy_Loader::css()` now holds the launcher's place with `!important` until the widget has loaded.
- **The icon glides to the widget's own place** instead of jumping when the widget has loaded (`launcher.js`, 320 ms; none for visitors who reduce motion).
- **The floating box slides in when it really shows, already in its place.** It is invisible and nothing in it animates until `launcher-design.js` adds `qcld-wpbot-in`: its entrance used to play while it was still hidden. It waits until the button has landed (roll-in, glide), follows the hosts at once (class and style changes, one check per frame, not only every 400 ms), and slides in again each time the hosts show it again.

## 0.19.0 (owner, 17 November 2026: new chat button design)
- **New chat button design.** `Widget\Launcher_Design` prints inline CSS and a small script that restyle the hosts' own button and floating box (same IDs and classes, so their scripts, templates and add-ons keep working):
  - smoked glass or flat button in the theme color (logo blue `#3d8bd8` by default), 60 px;
  - the built-in molecule icon (`icon-molecule.svg`) drawn in the theme color; uploaded images, icon videos and the older built-in pictures fill the button as they are;
  - motion: rolls in on page load, gently changes shape while it waits, the icon zooms into a close X; nothing moves with "Motion" off or for visitors who reduce motion;
  - floating box: **Ask bar** (message, a "Type your question" bar that opens the chat with the cursor in the message field, the quick menu as chips) or **Side menu** (message above the button, quick menu stacked beside it), typing dots before the message, an unread badge until the chat is opened; works with both quick-menu markups the hosts print.
- **One theme object for the whole look** (`Widget\Launcher_Theme`, option `qcld_wpbot_chat_launcher_theme`): design, finish, color, size, motion, box, and a reserved `window` section. `save()` takes any subset and checks every value; filter `qcld_wpbot_chat/launcher_theme`, action `qcld_wpbot_chat/launcher_theme_saved`. Built for a later theme generator (from a prompt or a site's branding).
- **Existing sites keep their look.** A site where WPBot ran before (the module's schema exists) starts on the classic design and sees a "Try the new chat button" notice on WPBot screens ("Switch to the new design" or "Not now"); new installs start on the new design.
- **Settings** (`Admin\Launcher_Settings`): hosts show them with `do_action( 'qcld_wpbot_chat/launcher_settings' )` in Icons & Themes. Floating box "Off" is the hosts' "Disable Floating Notification Box"; either screen changes both.

## 0.18.7 (owner, 17 November 2026: web addresses in AI replies were plain text)
- **WPBot Free replies use Parsedown.** `Legacy\Ai_Bridge::html()` looked only for `Parsedown`; WPBot Free ships the same library as `Qcld_Parsedown`, so Free used the minimal fallback: no links for written-out addresses, no headings, tables, quotes or code blocks. It now uses whichever is loaded.
- **Written-out web and email addresses become links, and web links open in a new tab.** New `Ai_Bridge::links()` and `autolink()` run on every reply: `https://…`, `www.…` and `name@example.com` in the text become links (a closing "." or ")" stays outside the link), and links without a target get `target="_blank" rel="noopener noreferrer"`. Text inside links, `code` and `pre` is left alone.
- The fallback (no Parsedown at all) also shows headings, `_italics_` and `~~struck~~` text.

## 0.18.6 (QA, 17 November 2026: long AI Actions)
Found with the "Senior PHP Programmer Assessment" template (10 questions), reproduced with real OpenAI replies.
- **The AI gets each AI Action's full instructions.** They travelled only in the tool description, which is cut at 1,000 characters, so the AI saw the first 2-3 steps of a long form: it sent the form after name, email and phone, then made up its own questions. `Actions\Actions::instructions()` now adds each form's instructions to the system text (only the active one while it is filled in); the tool description is cut without splitting a letter.
- **A form with numbered questions is sent only when every question is answered.** New `Leads\Ai_Forms::step_count()` and `steps_rule()` ("Step 3: …", "3. …", "3) …"). The AI is told how many there are; a tool call with fewer answers saves nothing and tells the AI to keep asking (it does not tell the visitor anything failed). Forms without numbered questions are unchanged.
- **The same answers sent again within the hour are the entry already saved,** not "Sorry, I couldn't complete…". `Ai_Forms::save()` returns the earlier entry id; different answers are a new entry.
- **The AI sees up to 30 history messages while an AI Action is filled in** (`Ai_Client::FORM_HISTORY_LIMIT`, `history_limit` reply argument), 10 otherwise as before; with 10 it lost the first answers of a 10-question form and started again. `Conversation\Message` and `Chat::history()` keep 30.

## 0.18.5 (owner, 17 November 2026: the floating notification waits for a click)
- **The floating notification box shows by itself again.** The launcher loads the full widget on the first interaction, unless a setting needs it sooner (`Widget\Lazy_Loader::mode()`, "idle"). The notification box is shown by the widget, so it waited until the visitor touched the icon. The eager list named two notification options that do not exist. New `Lazy_Loader::notification_on()`: when the box is on ("Disable Floating Notification Box" not ticked, or its mobile option on a phone, and the floating icon not turned off), the widget loads when the page is idle. New filter `qcld_wpbot_chat/lazy_notification`.
- **The icon no longer disappears while the chat engine waits.** With WPBot's asset cache holding the engine ("Load chat engine": When idle or On first interaction, `window.wpbotAoEngine`), the launcher loaded the styles, removed its light icon and handed over, but the engine waited for the visitor: the icon faded out and came back only when the mouse moved. The launcher now keeps its icon until the asset cache reports that the engine has run (`qcld_wpbot_engine_ready`, asset cache 0.7.2), and leaves the visitor's click to the asset cache, which replays it.

## 0.18.4 (owner, 14 November 2026: Train gets thumbs-down and "asked for a person")
- **Thumbs down:** a dislike on an answer puts the visitor's question before it on Train (reason "Thumbs down"). The widget sends that question as `question` with `wpbot_save_feedback` (the `feedback` REST route takes it too); `Leads::feedback()` passes it to `Train\Feedback_Gaps::disliked()`. A like changes nothing.
- **Asked for a person:** when a visitor asks for a human ("talk to a person", "real agent", "I need a human"), the question they asked just before goes on Train (reason "Asked for a person"). New `Train\Feedback_Gaps` on `qcld_wpbot_chat/message_received`; the pattern is filterable with `qcld_wpbot_chat/person_request_pattern`.
- Train already had labels for both reasons; nothing recorded them before.

## 0.18.3 (support report, 14 November 2026: "??" in Bulgarian replies)
- **AI replies no longer show "??" in place of letters.** With AI Actions (forms) on, the stream guard that hides `AI_FORM_DATA` held back the last 20 bytes of the reply and sent the rest, cut by bytes. In Cyrillic, Greek, accented text, Arabic, CJK or emoji the cut could fall inside a letter; the SSE endpoint's `wp_json_encode()` then turned each half into "?" (a BG CONSULT UK reply read "Универса??ен кр??дит"). `Leads\Ai_Forms::stream_guard()` now cuts only between letters.
- New `Text\Utf8` helper: `boundary()`, `cut_bytes()`, `cut()`, `length()`.
- The same byte cuts are gone from the visitor's name and page title (`Conversation\Message`), the email subscription name and user agent (`Leads\Leads`) and the answer-scope summary (`Ai\Answer_Scope`). A long name or title in a non-Latin script could end in half a letter.

## 0.18.2 (owner, 14 November 2026: error log alert)
- **The error log tells you when something breaks.** New `Diagnostics\Error_Alert`. Significant errors are WPBot Core error log entries of level "error" seen in the last 7 days: crashes, PHP errors, failed AI and outside-service calls, failed emails and jobs, browser errors. Warnings and notices do not count, and repeats are one entry.
  - A red count bubble (WordPress's own `awaiting-mod` markup, with words for screen readers) on the host's menu item that holds AI Settings, on its AI Settings item, and on "Error log" in the AI Settings menu.
  - A notice on WPBot's screens (that menu's pages): "WPBot recorded N errors in the last 7 days", with "Open the error log" and "Dismiss until a new error". Dismissing stores the newest error's time in the user meta `qcld_wpbot_chat_error_alert_dismissed`; a newer error brings the notice back. Not shown on the Error log itself.
  - Only for users who can manage options, and only with WPBot Core's error log.
  - New filters: `qcld_wpbot_chat/error_alert_summary` (count, newest) and `qcld_wpbot_chat/error_alert_pages` (screens with the notice).

## 0.18.1 (owner, 13 November 2026: AI defaults for new installs)
- **New installs turn on "Use the Knowledge Base" and "Use the current page"** in AI Settings. A host adds the option `qcld_wpbot_chat_new_install` when it is activated on a site that never had WPBot; the first shared AI settings set (`Ai\Ai_Settings::get()`) then starts with both on, writes the knowledge base switch back to the providers' old options, and deletes the option. Existing sites keep their settings. The answer scope stays "Business first". The knowledge base sources (pages, posts, documents) are still chosen by the owner.

## 0.18.0 (chatbot features round 6, 12 November 2026)
- **Uninstall cleanup** (owner, 12 November 2026). New `uninstall-helper.php` with `qcld_wpbot_chat_uninstall_for_host( $host_id, $plugin_dir )` for the hosts' `uninstall.php`. While another WPBot plugin with the module is installed (active or not), it does nothing, so deleting WPBot Free under WPBot Pro, or the other way round, keeps the data. Otherwise it clears the module's scheduled events and, when the owner opted in, drops the messages, knowledge gaps, change log and answer cache tables (and any later `qcld_wpbot_chat_` table) and deletes the module's `qcld_wpbot_chat_*` options and transients. WPBot Core's keys and knowledge base follow WPBot Core's own setting.
- **New AI Settings section "Chatbot data":** row counts of the four tables and the switch "Delete chatbot data when the plugin is deleted" (option `qcld_wpbot_chat_delete_on_uninstall`, off by default). Saving it does not clear the answer cache.
- **Ollama in WPBot Free** (owner, 12 November 2026). Ollama is now one of the default providers in AI Settings > Providers, so WPBot Free shows its card too. The legacy bridge already answered `ollama_response` through the engine. The `qcld_wpbot_chat/ai_providers` filter can still add or remove providers.
- **AI Actions keep to their own questions** (QA, 12 November 2026). While a visitor fills in an AI Action, the AI gets only that action's instructions (`Leads\Ai_Forms::instructions()` takes the active form's title; `Actions\Actions::instructions( $actions, $active )` names the action in progress on the tool path). Before, every form was listed and the AI could drift into another action's questions. The hosts' widget no longer switches action when an answer is part of another action's title.
- **No source links under an AI Action's questions** or its "details sent" note (`Conversation\Steps\Ai_Step::show_citations()`). The links came from the knowledge search on the visitor's answer.
- **Train gets the widget's unanswered questions** (QA, 12 November 2026). The legacy "unanswered question" request (`wpbo_failed_response`) now also fires `qcld_wpbot_chat/knowledge_gap` (reason `no_answer`); before, those questions went only to the hosts' old "Questions Not Answered" table, so Train stayed empty unless the AI said it was not sure. Module schema 7 imports that table once more. `Train\Gaps::import_legacy()` is now safe to run again: a question already in Train only has its count raised to the old count.

## 0.17.4 (owner issues, WPBot Free, 12 November 2026)
- **Site search cards:** the result title is `<p class="wpbot_card_caption_title">` again, as in the legacy search. Every chat template styles it. The `<h2>` used since the rebuild took the theme's heading size, so a post title filled the chat window.
- **ChatBot › Features** shows no menu item or tab until a feature registers through `qcld_wpbot_chat/register_modules`. Hosts still turn it on with `qcld_wpbot_chat/features_page`. Before, WPBot Free showed an empty "No extra features are available yet" page.

## 0.17.3 (support report: contact only on request)
- AI Settings › Error log › Send to WPBot support (owner, 12 November 2026):
  - The consent box reads "Send this report to WPBot support (help@wpbot.pro), so they can reply." The site address and the admin's email address are no longer named there.
  - New, unticked: "Should we contact you after we fix the issue?" with the admin's email address prefilled (editable; shown once ticked). Only then does the email address go with the report (WPBot Core's `Support_Report`).
  - New notices: `sent_no_contact` and `need_email`.

## 0.17.2 (owner issues, 12 November 2026)
- AI Settings: links to the old Knowledge Base tab anchor (`#ai-knowledge-base-tab`, used by the "Sync your knowledge base" notice, help text and older screens) now open the Knowledge Base section. Before, they reloaded Providers. The script runs in the page head and keeps the anchor for the section's own tabs.

## 0.17.1 (AI Settings menu order)
- **Answer cache comes before Spending and limits** in the AI Settings menu (owner, 12 November 2026). The order is now Providers, Answers, Knowledge Base, What the chatbot knows, Answer cache, Spending and limits, AI Usage and Error log.

## 0.17.0 (Answer cache: safer, and checked with AI; Ollama without a server address)
- **The chatbot's answer cache uses its own table** (`qcld_wpbot_chat_answer_cache`, schema version 6), with the question, the answer, the number of reuses and a check status. WPBot Core's `Result_Cache` is no longer used for chatbot answers. The setting stays off by default (AI Settings > Spending and limits, or Limits and cost without the spend API, > Answer cache). New `Ai\Answer_Cache`.
- **Safer keys:**
  - The visitor's name is left out of the key, so visitors share answers. An answer that mentions the name is never saved.
  - Questions with an email address, a phone number or an order number are never cached.
  - Questions that differ only in capitals, spaces or end punctuation share one answer.
  - Answers from the backup provider are not saved under the primary's key.
- **Expiry and clearing:** saved answers expire after 7 days instead of 30, and are cleared whenever AI Settings are saved.
- **Prepend and append text** from the provider settings are now added to cached answers too. They used to be missing.
- **New AI Settings section, "Answer cache":**
  - The last 7 days: saved answers, times reused, estimated AI cost saved, and counts by status (Not checked, Correct, Flagged).
  - A list of saved answers with filters and search.
  - Actions on each answer: Check with AI, Keep (marks a flagged answer as correct) and Delete.
  - "Check N answers" for up to 50 not checked yet, with the estimated cost shown first, and "Clear answer cache". New `Admin\Answer_Cache_Screen` (AJAX `qcld_wpbot_chat_answer_cache`, nonce and `manage_options`).
- **AI check** (`Ai\Answer_Review`): it sends the question, the saved answer and the knowledge base text found for the question today to the provider that answers the chatbot.
  - The AI replies correct, wrong or unsure, with a reason.
  - Wrong and unsure answers are flagged and stop being served at once.
  - Checks run only when the owner clicks. They are recorded as AI use under the feature `cache_review`.
  - New filter `qcld_wpbot_chat/pre_answer_review`, so tests can answer without calling the AI.
- Tests: `AnswerCacheTest` covers the key rules, personal data, the visitor's name, saving, serving, clearing and the review verdicts. The fake `$wpdb` in the test stubs now supports `%f`.
- **Ollama without a server address** (first numbered 0.13.1 on this branch):
  - **Ollama with no server address no longer breaks replies.** When the widget asked Ollama and Ollama had no address, its old handler sent the request to an empty URL ("Ollama Error: Protocol "" not supported or disabled in libcurl"). Now the provider that answers replies instead, when one is set up (`Ai_Bridge::should_bridge()`). Ollama with no address is never picked as the backup (`Ai_Client::fallback_provider()`). When Ollama is the only choice, the error says what to do: "Ollama has no server address. Add it (for example http://localhost:11434) in AI Settings > Providers > Ollama."
  - New `Provider_Settings::is_set_up( $provider )`: the provider has an API key, or for Ollama a server address.
  - Tests: `AiBridgeTest`, `ProviderSettingsTest` and `AiClientTest` cover the cases.

## 0.16.0 (error log)
Owner request (11 November 2026): an error log for every part of WPBot that a site owner can send to WPBot support with a description.
- New AI Settings > **Error log** (`Diagnostics\Error_Log_Screen`; needs WPBot Core's `Diagnostics\Error_Log`, else hidden):
  - "Having a problem? Send it to WPBot support": a description, a consent box (the report, the site address and the admin's email go to help@wpbot.pro), **Send to WPBot support** and **Download report**.
  - The log: last seen, level, where (PHP, crash, outside service, AI, background job, email, browser) and module, the message with details (file and line, request, service, stack), and the count. Filter by source, 50 per page, **Clear the log**.
  - What to record: browser errors of the widget and WPBot's admin screens (on), minor PHP notices (off).
- **Help page card** ("Something not working? Send us your error log") on WPBot Pro's and WPBot Free's Help pages, with the number of errors in the last 7 days, leading to AI Settings > Error log (`qcld_wpbot_chat/error_log_help_pages`).
- The support report gets a ChatBot section: answering provider, model, providers on, knowledge base answers, site search, Simple Text Responses, FAQ questions, module version. No keys.

## 0.15.0 (ChatBot FAQ as knowledge)
Owner request (11 November 2026): "The ChatBot Settings > FAQ are not searchable or included in the RAG database."
- New `Knowledge\Faq`: reads ChatBot Settings > FAQ (`support_query` / `support_ans`; WPBot Pro's per-language lists and WPBot Free's single list, serialized or not).
- **Knowledge base:** new source `faq`, one item per question ("Q: … A: …", with its language). On by default; Knowledge Base > Sources to include has an "FAQ" switch (`rag_embed_faq`, saved with the hosts' own settings). Saving the FAQ updates the knowledge base at the end of the request: new and changed questions are stored and embedded, removed ones deleted; unchanged ones keep their vectors (no new embedding cost). "Sync now" includes the FAQ too.
- **Search:** the chatbot's search (the widget's site search and the engine's search step) answers from the FAQ first when a visitor's question shares at least half of its words with an FAQ question (and the other way round), or matches it exactly. The answer shows the question and the answer (`.wpb-faq-answer`, a labelled group). Also when an add-on runs the page search. Filter `qcld_wpbot_chat/faq_match`.

## 0.14.0 (rebuild 6: AI spending report, limits and alerts)
Owner request (11 November 2026). This needs WPBot Core's spend API (https://github.com/qcld/wpbot-core/pull/42); with an older WPBot Core, the chatbot's monthly budget works as before.
- **Spend strip** at the top of every AI Settings section. It shows Global, then each feature that has a limit or spend this period, as for example "$5.20 / $15.00 Weekly", with a bar that turns amber from 80% and red at the limit or when paused.
- **(i) details panel** for each feature: tasks with calls, tokens, cost, model and last run; the 10 most expensive runs; a 14-day trend. Pro adds per-channel and per-provider tables. At a limit, a one-click "Raise by 50% this period" button.
- **AI Settings > Spending and limits** (was "Limits and cost"):
  - the period (monthly or weekly, with a reset day);
  - a global limit, and a limit plus a pause switch for each of the 11 features;
  - alert thresholds, extra recipients and an email subject prefix;
  - the chatbot's message while AI is paused;
  - links to each provider's billing page.

  The old "ChatBot monthly budget" became the Chatbot limit. The answer cache and the rate limits stay.
- **AI Settings > AI Usage** (its own section in the AI Settings menu; owner, 11 November 2026: no separate menu item): a date range, a daily chart, and totals by feature, task and model. It also shows calls counted by WPBot Core's safety net and calls without a known price.
- **Quiet fallback at the limit:** the chatbot answers from saved answers, site search and the no-answer flow (with the owner's optional message), in the engine and in the legacy widget bridge. Visitors never see an error or the word budget. `Spend\Fallback`, filter `qcld_wpbot_chat/spend_fallback_reply`, option `qcld_wpbot_chat_spend_message`.
- `Ai_Client` asks `qcld_wpbot_core_spend_allow( 'chatbot', $feature )`, where the feature carries the channel (`chat`, `chat_whatsapp`…). `Ai\Budget` reads and writes the Chatbot limit, and its own 80/100% emails stop, because WPBot Core's alerts cover them.
- Action `qcld_wpbot_chat/spend_report_actions` (WPBot Pro adds CSV export).

## 0.13.0 (round R: ChatBot menu, AI Settings redesign; in progress)
- **Every model, recommended models highlighted (owner, 10 November 2026):** each provider card's Model list has a **Recommended** group (★ Best value, Best quality, Cheapest, from WPBot Core's catalogue) and **All models from your <provider> account**, read from the provider with the saved key on the server (WPBot Core `Model_Catalog`, cached a day; chat models only). "Refresh models" reads it again (AJAX `qcld_wpbot_chat_ai_models`, nonce and `manage_options`). A saved model the provider no longer offers gets a warning with the recommended replacement. The note under the list follows the chosen model, with prices from WPBot Core's weekly price feed for models it doesn't list. OpenRouter and Ollama suggest the same models while typing. The "Cheaper model for short questions" lists offer the same groups, cheapest first.
- **Default prompt (owner, 10 November 2026):** an empty system prompt is filled in with a short default when the shared set is first made, and the AI gets that default whenever the saved prompt is empty (`Ai_Settings::default_prompt()`, `prompt()`).
- **"What the chatbot knows" (owner, 10 November 2026):** its own AI Settings section, with the full status, search, source and status filters and pages of 20 (`Kb_Panel::render()`, `type_filters()`, filter `qcld_wpbot_chat/kb_type_filters`). The host can add its content table through `qcld_wpbot_chat/ai_settings_known`. The Knowledge Base settings page shows the status in one line, with Sync now, Update vectors and a link to the list (`Kb_Panel::render_compact()`).
- **QA fixes (round R):**
  - Extra instructions never reach the page or the visitor's question. They go only into the AI's system text on the server; the old prepend/append options are cleared when the shared set is first made and on every save.
  - Turning on the ChatBot rate limits no longer removes the built-in visitor limit: a role's limit comes on top, and a role left at 0 keeps the built-in limit.
  - A key pasted after the mask saves only the new key (`Ai_Settings_Page::typed_key()`).
  - Saving Providers keeps a provider that answers but isn't offered here (Ollama after WPBot Pro was deactivated).
  - The Providers text no longer lists features that one edition doesn't have.
  - A widget AI question is counted once in the rate limits (the AJAX guard counted it and the engine counted it again, halving visitors' limits since CB1a). `Limiter::counted()`.
  - `Ai_Client::reply( …, array( 'raw' => true, 'max_tokens' => … ) )` is for admin tasks such as reports: only the caller's instructions are sent, never cached, without the chatbot's prompt, answer scope or longest answer.
- **ChatBot menu (R1):** `Admin\Admin_Pages` lists the module's pages (Train, Features, and pages others add through `qcld_wpbot_chat/admin_pages`, such as WPBot Pro's AI Advanced) for the host's own ChatBot menu, with Train's count (open gaps plus changes waiting for approval). Hosts return false from `qcld_wpbot_chat/wp_menu_items` to hide the pages in the WordPress menu (they stay registered under the host's chatbot menu, so it stays open and highlighted), and print their header and ChatBot menu on `qcld_wpbot_chat/admin_page_header`. Pages keep their addresses.
- **AI Settings providers (R2/R3):** the provider list goes through the filter `qcld_wpbot_chat/ai_providers` (default without Ollama; WPBot Pro adds it). Each provider card has the id `provider-{id}`, so WPBot Core's AI readiness button opens the right card. `Ai\Ai_Settings::core_import()` is the hosts' `get_ai_import()` (primary, backup, every saved key) for WPBot Core key sets that follow AI Settings.
- Train and Features use the module's admin look (`assets/admin/pages.css`: cards, pill tabs, buttons, tables in the ChatBot header's colours).

## 0.12.1 (chatbot features: milestone review fixes)
- **Security:** page context no longer reads a post the visitor may not see. The page URL comes from the browser (anyone can set it), so the post now has to pass the same access labels as the knowledge base (`Page_Context::visible()`; WPBot Core's stored labels and `qcld_wpbot_core/kb_post_access`; filter `qcld_wpbot_chat/page_context_allowed`).
- Actions: `limit_users` counts logged-in visitors against the rate limit (store order lookups); `no_input` Actions get no arguments, whatever the AI sends.
- Approval queue: approve, reject and undo claim the change first (one winner); undoing an answer refuses when the question was answered or changed since.
- Abilities: `list-sessions` and `get-session` need the Train section's publish level; visitor-written text is marked `untrusted`; descriptions no longer say approval can be turned off.
- The widget's history, name and page title are no longer unslashed twice.

## 0.12.0 (chatbot features CB3 groundwork, Nov 2026)
- **Lookup Actions:** a step may return `{ ok, result, cards }`; `result` is what the AI reads (store search, order status, REST GET), `cards` reach the reply (`data.cards`) and the widget bridge shows them (`Ai_Bridge::cards_html()`: title, url, image, price, note, button). Actions may set `instructions` (their own system text) and `limit` (rate-limit bucket; new bucket `lookup`, 120 per hour). Actions without a `form` (Pro's) are not tied to the AI Forms switch.
- **Routing, failover, answer cache hooks** in `Ai_Client::reply()`: the model goes through WPBot Core `Model_Routing::route()` (filter `qcld_wpbot_core/route_model`); a failed call tries once with `Model_Routing::fallback()`'s provider (filter `qcld_wpbot_core/fallback_provider`; the first error still reaches `qcld_wpbot_chat/ai_failed`); answers come from and go into WPBot Core's `Result_Cache` only when `qcld_wpbot_chat/answer_cache` says so (off by default; never with conversation history; not for tool rounds).
- **Budget:** no AI call once WPBot Core's monthly budget for the module `chatbot` is used up (`Usage::budget_reached()`, as in `AI_Service`); visitors get the friendly message, admins the reason.
- Provider errors: the bridge shows site admins the provider's own words (WPBot Core error `detail`).
- Safe answers: saved handoff choices that all left the start menu fall back to the default items.
- Train: a gap keeps its first wording.

## 0.11.0 (chatbot features CB1d, Nov 2026)
- **WordPress Abilities** (`Abilities\Abilities`, WordPress 6.9+ only; skipped when `wp_register_ability` is missing), category `wpbot-chatbot`, offered to AI assistants by the official WordPress MCP adapter when it is installed (`meta.mcp.public`; not bundled). Read: `search-knowledge` (the caller's access labels), `list-sessions`, `get-session` (personal data removed with WPBot Core's redactor), `list-knowledge-gaps`, `list-actions`, `get-settings` (never keys), `get-usage`. Write: `add-knowledge`, `answer-gap` (approval queue, undo), `run-action` (logged). Filter `qcld_wpbot_chat/abilities`.
- **Approval queue and change log** (`Abilities\Changes`, table `qcld_wpbot_chat_changes`, module schema 5): writes record the previous value; Approve, Reject and one-click Undo on Train > Changes. Kinds through `qcld_wpbot_chat/change_kinds`; `qcld_wpbot_chat/change_needs_approval`.
- **Rate limit:** ability writes, bucket `ability` (30 per hour per user).
- **Permissions** (`Admin\Permission_Sections`): WPBot Core grid sections `chatbot_train` (adopted: view reads, use proposes, publish approves and undoes) and `chatbot_inbox` (for CB4). Train needs `wpbot_chatbot_train_view`; its actions `wpbot_chatbot_train_publish` (manage_options without WPBot Core Permissions).
- **Features page** (`Admin\Features_Page`) for hosts without Module Manager (filter `qcld_wpbot_chat/features_page`): the features registered through `qcld_wpbot_chat/register_modules`, with on/off switches.
- Ability names use WPBot Core's `Brand::name()` (white label).

## 0.10.0 (chatbot features CB1c, Nov 2026)
- **Train** (`Admin\Train_Page`, under the host's chatbot menu; filters `qcld_wpbot_chat/admin_parent`, `/train_capability`): knowledge gaps (questions the bot could not answer or was not sure about), grouped by their words and ranked by times asked. Add answer (stored in the knowledge base as source `train`, used from the next question), Write article (filter `qcld_wpbot_chat/gap_write_article` returns a URL; Help Desk handles it when on, otherwise a draft post opens), Ignore, Move back. A count badge on the menu item and a dashboard card.
- `Train\Gaps`: table `qcld_wpbot_chat_gaps` (module schema 3); fed by `qcld_wpbot_chat/knowledge_gap` (reasons `no_answer`, `not_sure`; hosts may send `thumbs_down`, `handoff`); the host's "Questions Not Answered" imported once. Action `qcld_wpbot_chat/gap_answered`.
- **Access labels:** knowledge searches carry the visitor's labels (`Knowledge\Visitor_Labels`: `public`; `logged_in` and `role:*` on the website; filter `qcld_wpbot_chat/visitor_labels` for membership, course and store labels), so labelled knowledge is left out before scoring (WPBot Core KB schema 2).
- **Citations** link to the section that answered (`url#anchor`, `section`) for heading-aware chunks.

## 0.9.0 (chatbot features CB1b, Nov 2026)
- **Actions engine** (`Actions\Actions`): AI Actions forms become Actions the AI calls as native tools (WPBot Core `AI\Tool_Calls`) once it has the answers; steps run on the server after validation and the visitor's Action rate limit (`action`, 10 per hour): save_entry (entry post, email, `ai_form_saved` still fires), webhook (https only), marketing_list. Filters `qcld_wpbot_chat/actions`, `/action_types`; action `qcld_wpbot_chat/action_ran`. Models without tool calling keep the delimiter path (`Leads\Ai_Forms`).
- **Tool rounds** in `Ai_Client` (up to 3; every call's cost recorded; the final text streams in one piece).
- **Safe-answer mode** (`Ai\Safe_Answer`): not-sure replies (a marker the visitor never sees) carry `grounded`, `confidence`, `next_steps`, show the owner's handoff buttons (start-menu items; options `qcld_wpbot_handoff_items`, `qcld_wpbot_handoff_max`; filter `qcld_wpbot_chat/handoff_buttons`) and are logged as knowledge gaps (`qcld_wpbot_chat/knowledge_gap`). Replaces the hosts' Context Aware Start Menu.
- **Retired models:** a saved model the provider retired is swapped for its replacement (WPBot Core `AI\Model_Routing`); `Admin\Retired_Model_Notice` tells the owner.
- Legacy bridge: safe Markdown fallback without Parsedown; the cost hand-over matches saved messages that include sources.
- Provider errors: action `qcld_wpbot_chat/ai_failed` ( WP_Error, Message ) so hosts keep the real error for the site admin (WPBot's AI error notice); the widget bridge shows it under the friendly message to site admins only, keys masked.

## 0.8.0 (chatbot features CB1a, Nov 2026)
- **Widget through the engine** (`Legacy\Ai_Bridge`): the legacy widget's AI actions (every provider, JSON and stream) are answered by the engine in the format the widget already reads, so the widget gets the knowledge base, Answer scope, AI forms, citations, cost and the debug view from one place. Old handlers still answer attached files, OpenAI Assistants and Grok collections. Switch: option `qcld_wpbot_chat_ai_bridge` ('0' = off); filters `qcld_wpbot_chat/ai_bridge`, `/ai_bridge_request`, `/ai_bridge_html`.
- **Citations:** `data.citations` (title, url, source_type, score) on knowledge-based replies; one setting `qcld_wpbot_citations` (on by default), next to Answer scope; filters `qcld_wpbot_chat/citations`, `/citations_enabled`.
- **Cost:** each AI reply recorded once in WPBot Core's usage log (`Ai_Client::record_usage()`, module `chatbot`, feature `chat` or `chat_{channel}`; filter `qcld_wpbot_chat/record_usage`). Messages schema 2: `provider` and `cost` columns. `Session_Store::costs()`, `ai_replies()`. Cost table on Common AI Settings, dashboard card (`Admin\Cost_Widget`).
- **Debug view** (`Pipeline::debug()`): steps, knowledge matches with scores, model, tokens, cost; saved with the message; in replies and under widget answers for administrators only (filter `qcld_wpbot_chat/debug_allowed`).
- **Page context** (`Ai\Page_Context`): a cleaned, size-limited extract of the published post the visitor is on, built on the server (filters `qcld_wpbot_chat/page_context`, `/page_context_max`).

## 0.7.3 (channel events, 9 Nov 2026)
- `Conversation\Channel::ai_reply()` takes an optional fifth argument, `array $extra`: scalar values carried on the engine message (`Message::$extra`), so `qcld_wpbot_chat/message_sent` listeners can tell which inbound channel item the bot answered. WPBot Pro's channel add-ons pass the item's `event_id` and `account_remote_id` (`qcld_wpbot_chat/channel_event`). New `Channel::message()` builds the message (session `{channel}-{sender}`, as before). Additive: existing calls are unchanged.

## 0.7.2 (knowledge base list, 8 Nov 2026)
- `Admin\Kb_Panel`: "What the chatbot knows", a searchable, paginated list of the knowledge base (synced pages and posts, uploads, Simple Text Responses) with the pieces ready for search, status and last update, under the status panel on the knowledge base screen. Needs WPBot Core's `qcld_wpbot_core_kb_items()`; hidden with an older core. Pages saved with Auto Sync were synced but only the old list (`rag_documents`) was shown, so they looked missing.

## 0.7.1 (QA, 7 Nov 2026)
- Answer scope: Business first and Anything say they apply even when the saved prompt limits answers to the documentation (sites that ran the old setup wizard); Business only treats general knowledge questions in the business's field as other topics.
- "Contact details for 'not sure' answers" next to the setting (option `qcld_wpbot_ai_scope_contact`; empty = Business Profile, then "through the website"). Live chat is its own clause (filter `qcld_wpbot_chat/answer_scope_live_chat`).

## 0.7.0 (next round, 7 Nov 2026)
- `Ai\Answer_Scope`: what the AI may answer, one setting for every provider (option `qcld_wpbot_ai_answer_scope`): Business only (business topics and greetings; other topics get the owner's message, option `qcld_wpbot_ai_scope_decline_text`), Business first (default for new and existing sites: the business plus general questions in its field), Anything. In every scope a business question the knowledge base does not answer gets "not sure" plus the business's contact details, never a guess or a refusal. The rules are added after the saved system prompt (`Answer_Scope::apply()`, once), describing the business from WPBot Core's Business Profile (`qcld_wpbot_core_profile`), then the Business Context summary, then the site title and tagline. `Ai_Client` applies them; hosts call `qcld_wpbot_chat_scope_system()` in their own provider handlers. Filters: `qcld_wpbot_chat/answer_scope`, `qcld_wpbot_chat/answer_scope_business`, `qcld_wpbot_chat/answer_scope_contact`, `qcld_wpbot_chat/answer_scope_rules`.
- `Admin\Answer_Scope_Field`: the setting on AI Settings (host prints it with `do_action( 'qcld_wpbot_chat/answer_scope_setting' )`), saved by AJAX (nonce, `manage_options`).
- DeepSeek reads its settings screen's model (`qcld_deepseekai_engines`), knowledge base and page suggestions options; AI forms are allowed for DeepSeek.

## 0.6.1 (QA fixes, 6 Nov 2026)
- Knowledge base: when the AI provider that answers has no embeddings the plugin can use (Grok, DeepSeek, Claude without a Voyage key), `Kb_Bridge::embedding()` uses the first other key saved on the site (OpenAI, Gemini, Mistral, OpenRouter; filter `qcld_wpbot_chat/kb_embedding_fallback`). Before, these sites had keyword search only.
- `Fallback_Step`: after an AI provider error the reply is the host's friendly message (option `qcld_wpbot_ai_error_text`, filter `qcld_wpbot_ai_friendly_error`) instead of "Sorry, No result found!", and `data.reason` gives the provider's error code to site admins only (`ai_unavailable` for visitors).

## 0.6.0 (M6)
- Extension hooks for the Pro module: `qcld_wpbot_chat/str_pre_match` (answer before the built-in matching), `qcld_wpbot_chat/str_keyword` (text used for matching), `qcld_wpbot_chat/str_legacy_handler` and `qcld_wpbot_chat/search_legacy_handler` (whether the old AJAX handlers still answer), `qcld_wpbot_chat/search_terms` (extra words such as synonyms; the number a result must match stays half of the visitor's own words), `qcld_wpbot_chat/search_post_types`, `qcld_wpbot_chat/search_limit`.

## 0.5.0 (M5)
- `Widget\Lazy_Loader` and `assets/js/launcher.js`: launcher first, widget on demand. The host's widget styles and scripts (adapter `widget_assets()`) leave the page queue; pages get a small CSS that shows only the chat icon and an inline launcher (about 2 KB gzipped, plain JavaScript) that loads the widget in order on the first interaction, on idle when auto-open, retargeting or keep-open is on, and normally on pages with an inline chat shortcode. Scripts download in parallel and run in order. Asset Cache copies are used, with the original file as a fallback. Idle prefetch is off by default (filter `qcld_wpbot_chat/prefetch_delay`, milliseconds). Off switch: option `qcld_wpbot_chat_lazy_widget` = "0", filter `qcld_wpbot_chat/lazy_widget`, constant `WPBOT_LAZY_WIDGET_DISABLE`. Filter `qcld_wpbot_chat/launcher_config`; event `qcld_wpbot_chat_loaded`; `window.qcldWpbotChatLoad( callback )` loads the widget from other scripts.

## 0.4.0 (M4)
- Conversation engine (`Conversation\Pipeline`): Simple Text Responses, DialogFlow v2/CX, site search, knowledge base, AI and fallback steps in the legacy widget's order (`qc_site_search_priority` puts site search after the AI). New hooks: `qcld_wpbot_chat/pipeline_steps` (filter), `qcld_wpbot_chat/message_received`, `qcld_wpbot_chat/message_sent`, `qcld_wpbot_chat/ai_context` (filter), `qcld_wpbot_chat/option_defaults` (filter; hosts can also give `option_defaults()` on their adapter).
- `Str_Step`: Free's and Pro's matching in one place (answer id, intent, exact question, category, keyword list, full-text with the weight setting, word match), on both table layouts; fixes the keyword-list and "bracket" queries that never matched.
- `Search_Step`: stop words and words under 3 characters are ignored, so a question no longer matches every post (B2); results ranked by matching words; no undefined-variable warnings (B9).
- `DialogFlow_Step`: v2 and CX from the server with a signed JWT (no Google library); CX agent chosen on the server; `webhook_authorized()` no longer lets a request without an Authorization header through (S2).
- `Ai_Step` and `Knowledge_Step`: the provider that is on answers through `Ai_Client`, with the knowledge base, page context and AI Actions forms (`Leads\Ai_Forms`: the form block is never shown or streamed to the visitor; only forms the owner defined are saved, fields capped, one save per form per session per hour).
- `RateLimit\Limiter`: 60 AI replies per guest IP per day by default (`qcld_wpbot_chat_ai_day_limit`, 0 = off; filter `qcld_wpbot_chat/rate_limit`); limits for messages, sessions, leads, feedback, reports and tokens.
- `Sessions\Session_Store`: new table `{prefix}qcld_wpbot_chat_messages` (one row per message; `qcld_wpbot_chat_db_version` 1) plus the legacy `wpbot_user` / `wpbot_conversation` rows built on the server, so today's Sessions screens and CSV export keep working. Sessions start when the visitor writes (B4). Privacy exporter and eraser.
- `Leads\Leads`: lead capture, feedback and reports (report emails throttled to one per 10 minutes per visitor).
- REST `wpbot-chatbot/v1`: `POST /token`, `POST /message` (JSON or server-sent events with `stream`), `POST /session/{id}`, `POST /lead`, `POST /feedback`, `POST /report`; visitor routes need the visitor token and are rate limited.
- `Legacy\Ajax_Shims`: the old widget's actions answered by the engine (`wpbo_search_response`, `wpbo_search_site`, `wpbo_failed_response`, `wpbot_save_feedback`, `wpbot_save_report`), visit counting once per 30 minutes (`qcld_wb_chatbot_session_count`), structured copies of saved transcripts (`qcld_wb_chatbot_conversation_save`), and guards (other-site requests refused, guest AI limit) on the AI and DialogFlow actions. STR Pro, Extended Search and provider extras keep their handlers until M6/M10.

## 0.3.0 (M3)
- `Knowledge\Kb_Bridge`: feeds WPBot Core's knowledge base from today's options (post types `rag_embed_pages` / `rag_embed_posts` / `rag_embed_cpts`, `rag_embed_str` adds Simple Text Responses, `rag_auto_sync_enabled` for sync on save) and picks the embedding model in the order and with the models WPBot stored before (OpenAI `text-embedding-3-large`, Gemini `gemini-embedding-001`, OpenRouter `openai/text-embedding-3-small`, Mistral `mistral-embed`, Claude via Voyage `voyage-4`, Ollama), so old vectors are reused. Starts the one-time move from `rag_documents` (free) and follows the old upload screens.
- `Admin\Kb_Panel`: knowledge base status on the existing knowledge base page (`do_action( 'qcld_wpbot_chat/kb_settings_panel' )`): counts, waiting pieces with the cost estimate, Sync now, Update vectors (AJAX, nonce, `manage_options`).

## 0.2.1 (M2)
- `Admin\Provider_Fields`: a "More options" panel on each AI provider's existing settings page (host prints it with `do_action( 'qcld_wpbot_chat/provider_settings_fields', $provider )`), showing only the common settings that provider had no field for, saved with the page's own Save button (AJAX, nonce, `manage_options`). Grok's model now has a field (its old option was never used; default stays `grok-3-latest`).

## 0.2.0 (M2)
- `Ai\Provider_Settings`: every AI provider's common settings (enabled, key, model, URL, system prompt, temperature, max tokens, streaming, knowledge base, page context, page suggestions, AI forms, file upload, prepend, append, rate limit) read from today's option names; settings a provider has no option for use `qcld_wpbot_chat_ai_{provider}_{setting}` and default to today's behaviour. `answering_provider()` keeps the widget's order.
- `Ai\Ai_Client::reply()`: one chat path for every provider through WPBot Core's client (streamed or not), with history (last 10 messages), prepend and append text.

## 0.1.0 (M1)
- Module skeleton: bootstrap, newest-copy loader (boots after WPBot Core), autoloader.
- Host adapter interface.
- Module registry behind Module Manager, reading and writing the existing `wpbot_master_addons` state; retired paths (Tavily) are dropped.
