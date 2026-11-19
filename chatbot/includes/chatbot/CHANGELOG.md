# Changelog

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
