<?php
/**
 * WPBot Free as host of AI Writer: the AI Writer menu item under WPBot,
 * WPBot's look, and the labelled, inactive previews of the WPBot Pro
 * features, each with what it does, its benefits and its features.
 *
 * No Pro code is bundled: the previews are text only, and AI Writer shows
 * them as inactive "Pro" badges where each feature would be (see the spot
 * list in AI Writer's docs/integration-readme.md, "Pro previews"). The owner
 * hides all of them with one click ("Hide Pro previews"). Nothing Pro shows
 * on the public site.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * AI Writer host adapter for WPBot Free.
 */
class QCLD_WPBot_Free_AI_Writer_Adapter extends \QuantumCloud\AiWriter\Host\Abstract_Host_Adapter {

	/**
	 * Where "Get WPBot Pro" goes (the preview id is added as the campaign).
	 */
	const PRO_URL = 'https://www.wpbot.pro/pricing/?utm_source=wpbot-free&utm_medium=ai-writer-preview&utm_campaign=';

	/**
	 * Host id (the same one WPBot Free uses for WPBot Core).
	 *
	 * @return string
	 */
	public function get_id() {
		return QCLD_WPBot_Free_Core_Adapter::ID;
	}

	/**
	 * Host name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'WPBot';
	}

	/**
	 * Under the WPBot menu.
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_menu_parent();
	}

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return __( 'AI Writer', 'chatbot' );
	}

	/**
	 * Where the old AI Content Generator was: above Automator, else last.
	 *
	 * @return string[]
	 */
	public function get_menu_before() {
		return array( 'wpbot-automator' );
	}

	/**
	 * WPBot brand values.
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_brand();
	}

	/**
	 * WPBot admin styles.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_admin_styles();
	}

	/**
	 * WPBot's header card.
	 *
	 * @return void
	 */
	public function render_admin_header() {
		QCLD_WPBot_Free_Core_Adapter::instance()->render_admin_header( __( 'AI Writer', 'chatbot' ) );
	}

	/**
	 * WPBot's settings.
	 *
	 * @return string
	 */
	public function get_settings_url() {
		return admin_url( 'admin.php?page=wpbot-panel' );
	}

	/**
	 * WPBot > AI Settings, for the AI readiness notice.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_ai_settings_url();
	}

	/**
	 * Labelled, inactive previews of the WPBot Pro features. Items with the
	 * spot "menu" are left-menu pages; the others show as a Pro badge where
	 * the feature would be. AI Writer hides all of them when the owner turns
	 * on "Hide Pro previews". Text only: nothing runs behind them.
	 *
	 * @return array[] Items with id, spot, label, text, benefits, features, url and button.
	 */
	public function get_pro_previews() {
		$cards = self::cards();
		$items = array();
		foreach ( self::spots() as $id => $spot ) {
			list( $where, $card, $label ) = array_pad( $spot, 3, '' );
			if ( ! isset( $cards[ $card ] ) ) {
				continue;
			}
			$items[] = array_merge(
				$cards[ $card ],
				array(
					'id'     => $id,
					'spot'   => $where,
					'label'  => '' !== $label ? $label : $cards[ $card ]['label'],
					'url'    => self::PRO_URL . $id,
					'button' => __( 'Get WPBot Pro', 'chatbot' ),
				)
			);
		}
		return $items;
	}

	/**
	 * Where each preview shows: preview id => array( spot, card id, label
	 * when it differs from the card's ).
	 *
	 * @return array<string,array>
	 */
	private static function spots() {
		return array(
			// Left-menu pages.
			'channels'                 => array( 'menu', 'channels' ),
			'competitors'              => array( 'menu', 'competitors' ),
			'results'                  => array( 'menu', 'results' ),
			'local'                    => array( 'menu', 'local' ),
			'headline-lab'             => array( 'menu', 'headline_lab' ),
			// Write.
			'interview'                => array( 'write.interview', 'interview' ),
			'bulk'                     => array( 'write.bulk', 'bulk' ),
			'saved-briefs'             => array( 'write.saved_briefs', 'saved_briefs' ),
			'voices'                   => array( 'write.voices', 'voices', __( 'Choose a voice', 'chatbot' ) ),
			'deep-humanize'            => array( 'write.deep_humanize', 'humanize' ),
			'review'                   => array( 'write.review', 'review' ),
			'data-web'                 => array( 'data.web', 'web_research' ),
			'data-who-ranks'           => array( 'data.who_ranks', 'analyse', __( 'Who ranks', 'chatbot' ) ),
			'data-interview'           => array( 'data.interview', 'interview' ),
			'data-research'            => array( 'data.research', 'research' ),
			'data-questions'           => array( 'data.questions', 'chat_ideas', __( 'Visitor questions from chats', 'chatbot' ) ),
			'research-activity'        => array( 'research.activity', 'research', __( 'Chats, orders and reviews, Search Console', 'chatbot' ) ),
			'who-ranks-analyse'        => array( 'who_ranks.analyse', 'analyse' ),
			'format-original-research' => array( 'format.original_research', 'format_research' ),
			'format-stats-roundup'     => array( 'format.stats_roundup', 'format_stats' ),
			'format-comparison'        => array( 'format.comparison', 'format_comparison' ),
			'format-case-study'        => array( 'format.case_study', 'format_case_study' ),
			'format-template'          => array( 'format.template', 'format_template' ),
			// Library.
			'library-review'           => array( 'library.review', 'review', __( 'Review status', 'chatbot' ) ),
			'library-bulk'             => array( 'library.bulk', 'regenerate_several' ),
			// Editors.
			'editor-internal-links'    => array( 'editor.internal_links', 'internal_links' ),
			'editor-fact-check'        => array( 'editor.fact_check', 'fact_check' ),
			'editor-translate'         => array( 'editor.translate', 'translate' ),
			'editor-channels'          => array( 'editor.channels', 'channels' ),
			'editor-voice'             => array( 'editor.voice', 'voices', __( 'Voice choice', 'chatbot' ) ),
			'editor-review'            => array( 'editor.review', 'review', __( 'Review status', 'chatbot' ) ),
			// Quick write.
			'quick-templates'          => array( 'quick.templates', 'saved_briefs', __( 'Custom templates', 'chatbot' ) ),
			'quick-bulk'               => array( 'quick.bulk', 'bulk_variations' ),
			'quick-campaigns'          => array( 'quick.campaigns', 'campaigns' ),
			// Topic Finder.
			'topics-chats'             => array( 'topics.chats', 'chat_ideas' ),
			'topics-youtube'           => array( 'topics.youtube', 'youtube' ),
			'topics-wikipedia'         => array( 'topics.wikipedia', 'wikipedia' ),
			'topics-web'               => array( 'topics.web', 'ai_web' ),
			'topics-competitors'       => array( 'topics.competitors', 'competitors', __( 'Competitor gaps', 'chatbot' ) ),
			'coverage-group-ai'        => array( 'coverage.group_ai', 'group_ai' ),
			'coverage-clusters'        => array( 'coverage.clusters', 'clusters' ),
			// Calendar.
			'calendar-ai-plan'         => array( 'calendar.ai_plan', 'ai_plan' ),
			'calendar-drafts-ahead'    => array( 'calendar.drafts_ahead', 'drafts_ahead' ),
			'calendar-reminders'       => array( 'calendar.reminders', 'reminders' ),
			// Brand & Business.
			'voice-add'                => array( 'voice.add', 'voices', __( 'Add a voice', 'chatbot' ) ),
			'voice-learning'           => array( 'voice.learning', 'learning' ),
			'voice-site-setup'         => array( 'voice.site_setup', 'site_setup' ),
			'profile-chats'            => array( 'profile.chats', 'chat_words' ),
			// Products.
			'woo-voice'                => array( 'woo.voice', 'product_voice' ),
			'woo-chat-questions'       => array( 'woo.chat_questions', 'product_questions' ),
			// Compare techniques.
			'compare-mix'              => array( 'compare.mix', 'compare_mix' ),
			'compare-ab-tests'         => array( 'compare.ab_tests', 'results', __( 'A/B tests', 'chatbot' ) ),
			'compare-subject-tests'    => array( 'compare.subject_tests', 'subject_tests' ),
		);
	}

	/**
	 * The card texts: card id => label, text, benefits, features.
	 *
	 * @return array<string,array>
	 */
	private static function cards() {
		return array(
			'channels'           => array(
				'label'    => __( 'Channel versions', 'chatbot' ),
				'text'     => __( 'Turn every article into ready-to-post versions for each social network, your newsletter and video.', 'chatbot' ),
				'benefits' => array(
					__( 'Reach every channel without rewriting the same story five times.', 'chatbot' ),
					__( 'The same voice everywhere, adapted to each network.', 'chatbot' ),
					__( 'Hours of social and newsletter work done in minutes, ready when a post goes live.', 'chatbot' ),
					__( 'See which channel actually brings visitors back.', 'chatbot' ),
				),
				'features' => array(
					__( 'Facebook, Instagram caption and carousel, LinkedIn, X thread, Pinterest and Google Business Profile versions.', 'chatbot' ),
					__( 'A newsletter item, a short vertical video script and an audio narration.', 'chatbot' ),
					__( 'Each version in your voice\'s style for that network, with a technique that fits it.', 'chatbot' ),
					__( 'A tracked (UTM) link back to the post in every version.', 'chatbot' ),
					__( 'Edit, approve, switch off, schedule on the calendar, copy or download them all.', 'chatbot' ),
					__( 'Regenerate any version; earlier versions are kept and can be brought back.', 'chatbot' ),
					__( '"Compare channels by clicks" shows which network sends the most visitors.', 'chatbot' ),
					__( 'Scheduled versions go to WPBot Social when it is active.', 'chatbot' ),
				),
			),
			'competitors'        => array(
				'label'    => __( 'Competitor Watch', 'chatbot' ),
				'text'     => __( 'See what your competitors publish, and the topics they cover that you do not.', 'chatbot' ),
				'benefits' => array(
					__( 'Never miss a competitor\'s new page.', 'chatbot' ),
					__( 'Find the gaps before they rank for them.', 'chatbot' ),
					__( 'Learn which offers and angles they push in their ads.', 'chatbot' ),
					__( 'Plan pages that cover more than theirs, without copying them.', 'chatbot' ),
					__( 'Polite and safe: robots.txt is respected and no competitor text is kept.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to ten competitors, checked weekly or with "Check competitors now".', 'chatbot' ),
					__( 'Sitemaps (up to 20,000 addresses) and feeds read politely, storing only titles, headings and counts.', 'chatbot' ),
					__( 'New competitor pages since the last check.', 'chatbot' ),
					__( 'Topic gaps marked in your topic map and added to Topic Finder as ideas.', 'chatbot' ),
					__( 'Page structure and product comparison tables: price shown, features listed and FAQ.', 'chatbot' ),
					__( 'Competitor pages on your topic in the Write brief, with "Add as points to cover".', 'chatbot' ),
					__( 'Competitor ads: Meta Ad Library and Google Ads Transparency Center links, and EU and UK ads from Meta\'s Ad Library API with your own token.', 'chatbot' ),
					__( '"Paste an ad" (text or screenshot) for a summary, angles and ideas; "Find competitors with AI" fills your list.', 'chatbot' ),
				),
			),
			'results'            => array(
				'label'    => __( 'Results & A/B tests', 'chatbot' ),
				'text'     => __( 'Learn which pieces bring visits, leads and orders, and test what works best on real visitors.', 'chatbot' ),
				'benefits' => array(
					__( 'Know what to write more of.', 'chatbot' ),
					__( 'See which pieces bring leads and orders, not just visits.', 'chatbot' ),
					__( 'Better titles and calls to action, chosen by your visitors.', 'chatbot' ),
					__( 'Topics that worked move up in Topic Finder.', 'chatbot' ),
				),
				'features' => array(
					__( 'Visits, leads and orders per piece and per topic, refreshed weekly or with "Count now".', 'chatbot' ),
					__( 'Visits from Site Insight or Search Console; leads and orders from WPBot chats, WooCommerce and Site Insight goals.', 'chatbot' ),
					__( 'A/B tests for headlines, intros, sections, calls to action, product descriptions and search titles.', 'chatbot' ),
					__( 'Up to three new versions against your current text.', 'chatbot' ),
					__( 'Measured by call-to-action clicks, scroll depth or time on page, and add to cart or orders for products.', 'chatbot' ),
					__( 'A duration estimate first, with a plain warning when a page may not get enough traffic.', 'chatbot' ),
					__( 'Works with page caching; search engines see the same content.', 'chatbot' ),
					__( 'The winner is applied by your click, or automatically.', 'chatbot' ),
				),
			),
			'local'              => array(
				'label'    => __( 'Local pages', 'chatbot' ),
				'text'     => __( 'One page per service and place, written only from your real local details.', 'chatbot' ),
				'benefits' => array(
					__( 'Get found in every town you serve.', 'chatbot' ),
					__( 'No thin copy-and-paste pages that search engines ignore.', 'chatbot' ),
					__( 'Real local details that win local customers\' trust.', 'chatbot' ),
					__( 'A location page in minutes instead of an afternoon.', 'chatbot' ),
				),
				'features' => array(
					__( 'One page per service and place.', 'chatbot' ),
					__( 'Built from your real address or area served, phone or opening hours.', 'chatbot' ),
					__( 'Needs a detail true only for that place, such as a landmark, a local job or a travel time.', 'chatbot' ),
					__( 'The writer is told never to invent addresses, reviews or neighbourhoods.', 'chatbot' ),
					__( 'Warns before a second page for the same service and place.', 'chatbot' ),
					__( 'Written by the full AI Writer pipeline and saved as a draft page for you to check.', 'chatbot' ),
				),
			),
			'headline_lab'       => array(
				'label'    => __( 'Headline lab', 'chatbot' ),
				'text'     => __( 'Better titles for many posts at once.', 'chatbot' ),
				'benefits' => array(
					__( 'Lift clicks on older posts in minutes.', 'chatbot' ),
					__( 'Compare techniques side by side before you choose.', 'chatbot' ),
					__( 'Fresh titles for a whole set of posts without writing them one by one.', 'chatbot' ),
					__( 'You stay in control: no title changes without your click.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to ten posts per run, from Write, Headline lab.', 'chatbot' ),
					__( 'Four variations per persuasion technique.', 'chatbot' ),
					__( 'One combined cost estimate first, then one AI call per post.', 'chatbot' ),
					__( 'Nothing changes until you click "Use as title".', 'chatbot' ),
				),
			),
			'interview'          => array(
				'label'    => __( 'Interview me', 'chatbot' ),
				'text'     => __( 'Answer a few questions and AI Writer turns your own words into an article.', 'chatbot' ),
				'benefits' => array(
					__( 'Content that sounds like you and holds facts only you know.', 'chatbot' ),
					__( 'No blank page: just answer like in a conversation.', 'chatbot' ),
					__( 'First-hand experience that sets your piece apart from generic AI text.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to eight questions, one at a time, each building on your last answer.', 'chatbot' ),
					__( 'Your answers become the draft\'s facts and notes.', 'chatbot' ),
					__( '"Write from this interview" opens the Write screen with the topic filled in.', 'chatbot' ),
					__( 'The outline and sections are built from your own words.', 'chatbot' ),
					__( 'Also a source for case studies.', 'chatbot' ),
					__( 'Regenerate the finished draft from the finished-article screen.', 'chatbot' ),
				),
			),
			'bulk'               => array(
				'label'    => __( 'Many from a list', 'chatbot' ),
				'text'     => __( 'Write up to 200 articles from a list or a CSV file.', 'chatbot' ),
				'benefits' => array(
					__( 'Fill a month of content in an afternoon.', 'chatbot' ),
					__( 'One budget for the whole job, so there are no surprises.', 'chatbot' ),
					__( 'Every draft waits for your review before it goes live.', 'chatbot' ),
					__( 'Works on ordinary shared hosting.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to 200 articles from a list (one topic per line) or a CSV file.', 'chatbot' ),
					__( 'CSV columns per row: topic, type, length, keywords, main search, notes, date, post type and format.', 'chatbot' ),
					__( 'A persuasion technique per piece, the same for all, or spread to compare.', 'chatbot' ),
					__( 'Suggested topics from Topic Finder, ranked, plus cluster gaps and your saved ideas.', 'chatbot' ),
					__( 'Duplicates are skipped and every row shows its progress.', 'chatbot' ),
					__( 'Runs in the background, also when you close the tab.', 'chatbot' ),
					__( 'Pauses at the budget and continues when you raise it.', 'chatbot' ),
					__( 'Every draft arrives as "Needs review", with Regenerate on each row.', 'chatbot' ),
				),
			),
			'saved_briefs'       => array(
				'label'    => __( 'Saved briefs and templates', 'chatbot' ),
				'text'     => __( 'Save a brief once and reuse it for every similar piece, and make your own Quick write templates.', 'chatbot' ),
				'benefits' => array(
					__( 'The same quality every time.', 'chatbot' ),
					__( 'Faster briefs for you and your team.', 'chatbot' ),
					__( 'Your house rules are built in, so new writers get it right first time.', 'chatbot' ),
				),
				'features' => array(
					__( 'Save the current brief in one click.', 'chatbot' ),
					__( 'Apply every field at once: type, length, audience, tone, keywords, voice, post type, notes and chatbot source.', 'chatbot' ),
					__( 'Saved briefs as defaults for bulk writing, with your technique choices kept.', 'chatbot' ),
					__( 'Custom Quick write templates with your own questions, rules and character limit.', 'chatbot' ),
					__( 'Up to 100 custom templates, also usable with Bulk variations.', 'chatbot' ),
				),
			),
			'voices'             => array(
				'label'    => __( 'Several voices', 'chatbot' ),
				'text'     => __( 'A voice for your brand, each author and each product line.', 'chatbot' ),
				'benefits' => array(
					__( 'Every piece sounds right for who it speaks for.', 'chatbot' ),
					__( 'Authors keep their own style on one site.', 'chatbot' ),
					__( 'Product names and spelling stay the same everywhere.', 'chatbot' ),
					__( 'Fewer edits, because each voice knows its words to use and avoid.', 'chatbot' ),
				),
				'features' => array(
					__( 'Brand, author and product-line voices.', 'chatbot' ),
					__( 'A full style guide per voice: words to use and avoid, product names, reading level and UK or US spelling.', 'chatbot' ),
					__( 'A version of each voice for every social network, such as more casual on Instagram and more formal on LinkedIn.', 'chatbot' ),
					__( 'Pick the voice in each brief, in the editor and in Regenerate, or make one the default.', 'chatbot' ),
					__( 'Add, edit, duplicate and delete voices, and see how many drafts use each one.', 'chatbot' ),
					__( 'Quick write and channel versions use the voice\'s version for that network.', 'chatbot' ),
					__( 'Gets better over time by learning from your edits.', 'chatbot' ),
				),
			),
			'humanize'           => array(
				'label'    => __( 'Deep humanize', 'chatbot' ),
				'text'     => __( 'An extra pass that rewrites only the paragraphs that still sound written by AI.', 'chatbot' ),
				'benefits' => array(
					__( 'Reads naturally from the first line to the last.', 'chatbot' ),
					__( 'Your links, numbers and facts stay exactly as they are.', 'chatbot' ),
					__( 'Less time spent editing out stiff, repetitive AI phrasing.', 'chatbot' ),
				),
				'features' => array(
					__( 'Extra writing rules on every section: varied rhythm, plain verbs and no symmetrical structure.', 'chatbot' ),
					__( 'Finds the most AI-like paragraphs: cliches, same-length sentences and repeated openings.', 'chatbot' ),
					__( 'One targeted rewrite of only those paragraphs, in your voice, with your own writing as examples.', 'chatbot' ),
					__( 'A rewrite that loses a link or a number is thrown away.', 'chatbot' ),
					__( 'On by default; no synonym spinning or fake typos.', 'chatbot' ),
				),
			),
			'review'             => array(
				'label'    => __( 'Review workflow', 'chatbot' ),
				'text'     => __( 'Send drafts for review before they go live.', 'chatbot' ),
				'benefits' => array(
					__( 'Nothing goes out unchecked.', 'chatbot' ),
					__( 'A clear handover between writers and editors.', 'chatbot' ),
					__( 'Only the people you trust can approve.', 'chatbot' ),
				),
				'features' => array(
					__( '"Needs review" and "Approved", with a thread of notes.', 'chatbot' ),
					__( 'A Library filter, a badge, an editor sidebar panel and a column in the posts list.', 'chatbot' ),
					__( 'Bulk drafts always need review; a setting makes every AI Writer draft need it.', 'chatbot' ),
					__( 'Approving needs the AI Writer Publish level.', 'chatbot' ),
					__( 'Drafts written ahead that need review are scheduled only once approved.', 'chatbot' ),
				),
			),
			'web_research'       => array(
				'label'    => __( 'Web research with sources', 'chatbot' ),
				'text'     => __( 'Facts from the web, each with its link, publisher and year.', 'chatbot' ),
				'benefits' => array(
					__( 'Statistics readers can trust and check.', 'chatbot' ),
					__( 'No invented numbers.', 'chatbot' ),
					__( 'Hours of hunting for sources saved.', 'chatbot' ),
					__( 'Pages that earn trust because every figure links to where it came from.', 'chatbot' ),
				),
				'features' => array(
					__( 'Uses a model with web search: OpenAI, Anthropic Claude, Google Gemini or OpenRouter.', 'chatbot' ),
					__( 'Each fact keeps its link, publisher and year, and the page\'s exact words.', 'chatbot' ),
					__( 'Facts without a source, a quote or a real year are dropped.', 'chatbot' ),
					__( 'Figures more than two years old are marked "old figure".', 'chatbot' ),
					__( 'Who ranks: the pages that rank for your search, as facts to compare against.', 'chatbot' ),
					__( 'The cost shows first, and the fact check with sources runs on the draft afterwards.', 'chatbot' ),
				),
			),
			'analyse'            => array(
				'label'    => __( 'Analyse these pages', 'chatbot' ),
				'text'     => __( 'Find out what the pages that rank cover, and what none of them do.', 'chatbot' ),
				'benefits' => array(
					__( 'Write the page that deserves to rank.', 'chatbot' ),
					__( 'Stand out with what everyone else left out.', 'chatbot' ),
					__( 'Know the length, media and questions searchers expect.', 'chatbot' ),
					__( 'Minutes instead of hours studying the top results by hand.', 'chatbot' ),
				),
				'features' => array(
					__( 'Page type, headings, questions, target length, tables, images, video and FAQ of the ranking pages.', 'chatbot' ),
					__( 'Points every page covers, the products or names they all list, and the search intent.', 'chatbot' ),
					__( 'What none of them cover: your own angle.', 'chatbot' ),
					__( 'Roundups that do not list you.', 'chatbot' ),
					__( 'Pages read politely, only where robots.txt allows.', 'chatbot' ),
					__( 'Added to your brief in one click; their text is never copied or stored.', 'chatbot' ),
				),
			),
			'research'           => array(
				'label'    => __( 'Research from your activity', 'chatbot' ),
				'text'     => __( 'Real numbers from your own chatbot conversations, orders, reviews and Search Console.', 'chatbot' ),
				'benefits' => array(
					__( 'Original findings nobody else can copy.', 'chatbot' ),
					__( 'Pages other sites link to and cite.', 'chatbot' ),
					__( 'Data you already have, put to work without spreadsheets.', 'chatbot' ),
					__( 'Customer privacy protected by design.', 'chatbot' ),
				),
				'features' => array(
					__( 'Chats per topic and month, and whether the bot could help (no chat text is used).', 'chatbot' ),
					__( 'WooCommerce orders from the last 12 months: items sold per product, category and month (no customer details).', 'chatbot' ),
					__( 'Product review ratings per product and month (no reviewer names).', 'chatbot' ),
					__( 'Clicks per search and month for your top 40 Search Console searches.', 'chatbot' ),
					__( 'Small groups are hidden to protect privacy.', 'chatbot' ),
					__( 'You check and approve the numbers before anything is written.', 'chatbot' ),
				),
			),
			'format_research'    => array(
				'label'    => __( 'Original research', 'chatbot' ),
				'text'     => __( 'Turn your own data into a report other sites cite.', 'chatbot' ),
				'benefits' => array(
					__( 'Earn links and citations.', 'chatbot' ),
					__( 'Numbers nobody else has.', 'chatbot' ),
					__( 'Authority in your field that competitors cannot copy.', 'chatbot' ),
				),
				'features' => array(
					__( 'Findings from your CSV or your site\'s activity, with a method section.', 'chatbot' ),
					__( 'Check the numbers with a chart and approve them first.', 'chatbot' ),
					__( 'Only approved numbers are used; any other number is flagged.', 'chatbot' ),
					__( 'Tables and a bar chart per finding in the article.', 'chatbot' ),
					__( 'Dataset structured data and the data as a CSV download.', 'chatbot' ),
				),
			),
			'format_stats'       => array(
				'label'    => __( 'Statistics roundup', 'chatbot' ),
				'text'     => __( 'Every figure with its source and year.', 'chatbot' ),
				'benefits' => array(
					__( 'A reference page people bookmark and link to.', 'chatbot' ),
					__( 'Every figure shows where it comes from, so readers trust it.', 'chatbot' ),
					__( 'Saves hours of hunting for figures and sources.', 'chatbot' ),
				),
				'features' => array(
					__( 'Statistics grouped by theme, each with its source and year.', 'chatbot' ),
					__( 'Web research with sources, plus your Persuasion Kit, chatbot knowledge and research data.', 'chatbot' ),
					__( 'Old figures are marked and flagged for an update.', 'chatbot' ),
					__( 'Every number is checked against the facts.', 'chatbot' ),
					__( 'A short "what the numbers mean" section.', 'chatbot' ),
				),
			),
			'format_comparison'  => array(
				'label'    => __( 'Comparison / vs', 'chatbot' ),
				'text'     => __( 'A fair comparison with a table and a clear verdict.', 'chatbot' ),
				'benefits' => array(
					__( 'Helps buyers choose, and choose you.', 'chatbot' ),
					__( 'Fair to every option, so readers trust the verdict.', 'chatbot' ),
					__( 'Reaches shoppers at the moment they compare options.', 'chatbot' ),
				),
				'features' => array(
					__( 'A side-by-side table and a "which to choose" verdict for each kind of reader.', 'chatbot' ),
					__( 'A section per option: strengths, weaknesses and who it is best for.', 'chatbot' ),
					__( 'Head to head on the criteria that matter most, and an FAQ.', 'chatbot' ),
					__( 'Your products, Persuasion Kit, chatbot knowledge, pages, web research and the pages that rank as sources.', 'chatbot' ),
					__( 'Claims about any product come only from facts; gaps get an [add: ...] note.', 'chatbot' ),
					__( 'A check makes sure the table and the verdict are there.', 'chatbot' ),
				),
			),
			'format_case_study'  => array(
				'label'    => __( 'Case study', 'chatbot' ),
				'text'     => __( 'One customer\'s story: the challenge, the work and the results.', 'chatbot' ),
				'benefits' => array(
					__( 'Proof that sells.', 'chatbot' ),
					__( 'Real results that win the trust of new customers.', 'chatbot' ),
					__( 'Your best customer story, told in a fraction of the time.', 'chatbot' ),
				),
				'features' => array(
					__( 'At a glance, the challenge, what you did, the results and what others can take from it.', 'chatbot' ),
					__( 'Built from your Persuasion Kit, uploads, chatbot knowledge and an interview.', 'chatbot' ),
					__( 'Results come only from real facts you give; any other number is flagged.', 'chatbot' ),
					__( 'Customer quotes only when marked as allowed, word for word.', 'chatbot' ),
					__( 'The customer is named only if your facts do.', 'chatbot' ),
				),
			),
			'format_template'    => array(
				'label'    => __( 'Template / framework', 'chatbot' ),
				'text'     => __( 'A copy-ready template with a worked example.', 'chatbot' ),
				'benefits' => array(
					__( 'A resource readers save and share.', 'chatbot' ),
					__( 'A practical page that earns links and return visits.', 'chatbot' ),
					__( 'Shows your expertise with a worked example from your own business.', 'chatbot' ),
				),
				'features' => array(
					__( 'One copy-ready template block (a table or list) with blanks to fill in.', 'chatbot' ),
					__( 'How to fill it in, field by field.', 'chatbot' ),
					__( 'An example filled in for your business, from your own facts.', 'chatbot' ),
					__( 'Word (.docx) and text (.txt) downloads.', 'chatbot' ),
					__( 'Built from your site context, chatbot knowledge, uploads and interview.', 'chatbot' ),
				),
			),
			'regenerate_several' => array(
				'label'    => __( 'Regenerate several', 'chatbot' ),
				'text'     => __( 'Rewrite many pieces at once with new options.', 'chatbot' ),
				'benefits' => array(
					__( 'Refresh a whole section of your site in one go.', 'chatbot' ),
					__( 'Published pages are never changed without your review.', 'chatbot' ),
					__( 'Nothing is lost: earlier text stays in Revisions.', 'chatbot' ),
				),
				'features' => array(
					__( 'Tick pieces in the Library or under a finished batch.', 'chatbot' ),
					__( 'Up to 50 pieces per run, in the background.', 'chatbot' ),
					__( 'Tone, length, reading level, technique, voice, language and what to change.', 'chatbot' ),
					__( 'One combined cost estimate first, listing any piece that cannot be done and why.', 'chatbot' ),
					__( 'Published pieces become proposed updates to review; drafts are rewritten in place.', 'chatbot' ),
					__( 'Facts, numbers, names and links are kept.', 'chatbot' ),
				),
			),
			'internal_links'     => array(
				'label'    => __( 'Internal links', 'chatbot' ),
				'text'     => __( 'Links to your related pages, suggested while you write and kept up to date.', 'chatbot' ),
				'benefits' => array(
					__( 'Better rankings and longer visits.', 'chatbot' ),
					__( 'No broken or forgotten links when you add pages.', 'chatbot' ),
					__( 'Linking takes seconds, at no AI cost.', 'chatbot' ),
				),
				'features' => array(
					__( 'Link suggestions in the editor, with no AI cost.', 'chatbot' ),
					__( 'Anchor phrases that already appear in your text.', 'chatbot' ),
					__( 'Pages on the same topic and its main guide first, then pages whose search or title matches.', 'chatbot' ),
					__( 'Pages you already link to are skipped.', 'chatbot' ),
					__( 'Topic clusters with automatic link upkeep.', 'chatbot' ),
				),
			),
			'fact_check'         => array(
				'label'    => __( 'Fact check with sources', 'chatbot' ),
				'text'     => __( 'Checks the claims in a post against the web and shows the sources.', 'chatbot' ),
				'benefits' => array(
					__( 'Publish with confidence.', 'chatbot' ),
					__( 'Catch wrong numbers and claims before your readers do.', 'chatbot' ),
					__( 'Protect your reputation with claims you can back up.', 'chatbot' ),
				),
				'features' => array(
					__( 'Finds the claims that need a source: numbers, superlatives and guarantees.', 'chatbot' ),
					__( 'Up to eight claims checked by a model with web search: OpenAI, Anthropic Claude, Google Gemini or OpenRouter.', 'chatbot' ),
					__( 'Each flagged claim with a verdict (supported, contradicted or unclear) and a reason.', 'chatbot' ),
					__( 'Only source links that really open are kept.', 'chatbot' ),
					__( 'Nothing in the post changes by itself.', 'chatbot' ),
				),
			),
			'translate'          => array(
				'label'    => __( 'Translate', 'chatbot' ),
				'text'     => __( 'A translated draft linked to the original in WPML or Polylang.', 'chatbot' ),
				'benefits' => array(
					__( 'Reach new markets fast.', 'chatbot' ),
					__( 'Your layout, links and shortcodes survive, so there is nothing to rebuild.', 'chatbot' ),
					__( 'Works with the multilingual plugin you already use.', 'chatbot' ),
				),
				'features' => array(
					__( 'Works with WPML or Polylang.', 'chatbot' ),
					__( 'Title, excerpt and content translated part by part, in the background.', 'chatbot' ),
					__( 'Blocks, links, image addresses and shortcodes are kept.', 'chatbot' ),
					__( 'Saved as a draft linked to its original, so language switchers and SEO plugins see a normal translation.', 'chatbot' ),
					__( 'Nothing is published automatically.', 'chatbot' ),
				),
			),
			'bulk_variations'    => array(
				'label'    => __( 'Bulk variations', 'chatbot' ),
				'text'     => __( 'Many variations of a post or ad at once.', 'chatbot' ),
				'benefits' => array(
					__( 'Enough copy for weeks of posts and ad tests.', 'chatbot' ),
					__( 'Copy for a whole product range or offer list in one go.', 'chatbot' ),
					__( 'Fresh variations keep your feed from repeating itself.', 'chatbot' ),
				),
				'features' => array(
					__( 'Any Quick write template, including your own, over up to 100 lines.', 'chatbot' ),
					__( 'One input per line: product names, post titles or offers.', 'chatbot' ),
					__( 'Templates for LinkedIn, Facebook, Instagram, X, Google Business Profile, email, and Google or Meta ads.', 'chatbot' ),
					__( 'Three variations per line, in the background.', 'chatbot' ),
					__( 'Variations within each network\'s limits.', 'chatbot' ),
					__( 'Copy them or download them all as CSV.', 'chatbot' ),
				),
			),
			'campaigns'          => array(
				'label'    => __( 'Campaigns', 'chatbot' ),
				'text'     => __( 'A whole campaign from one brief.', 'chatbot' ),
				'benefits' => array(
					__( 'Launch an offer everywhere with one message.', 'chatbot' ),
					__( 'Days of campaign planning done in minutes.', 'chatbot' ),
					__( 'Every piece tells the same story, on the right dates.', 'chatbot' ),
				),
				'features' => array(
					__( 'From your offer, audience, goal and dates.', 'chatbot' ),
					__( 'A landing section, an article outline, social posts, an email and ad copy.', 'chatbot' ),
					__( 'Edit everything before you place it.', 'chatbot' ),
					__( 'The article becomes a planned draft on the calendar, with the outline as notes.', 'chatbot' ),
					__( 'Posts, email and ads spread over your campaign dates on the calendar.', 'chatbot' ),
					__( 'Regenerate in one click or with changes; earlier drafts stay on screen.', 'chatbot' ),
					__( 'Ready to copy, or to hand to WPBot Social and AI Marketing.', 'chatbot' ),
				),
			),
			'chat_ideas'         => array(
				'label'    => __( 'Ideas from saved chats', 'chatbot' ),
				'text'     => __( 'Topics from the questions visitors ask your chatbot.', 'chatbot' ),
				'benefits' => array(
					__( 'Write what your customers actually ask.', 'chatbot' ),
					__( 'Fewer repeat questions for your team and your bot.', 'chatbot' ),
					__( 'Find the gaps where your bot could not help.', 'chatbot' ),
					__( 'Visitor privacy is protected before anything is sent to AI.', 'chatbot' ),
				),
				'features' => array(
					__( 'Reads your saved chatbot conversations in the background, with a cost estimate first.', 'chatbot' ),
					__( 'Later runs read only new or changed chats.', 'chatbot' ),
					__( 'Greetings, small talk, spam and duplicates removed.', 'chatbot' ),
					__( 'Names, emails, phone numbers and addresses removed before anything is sent to AI.', 'chatbot' ),
					__( 'Questions grouped by topic, counted once per chat; rename, merge or dismiss groups.', 'chatbot' ),
					__( 'Ranked by demand, relevance and what the bot could not answer.', 'chatbot' ),
					__( 'Ideas also reach your calendar\'s ideas list.', 'chatbot' ),
				),
			),
			'youtube'            => array(
				'label'    => __( 'YouTube', 'chatbot' ),
				'text'     => __( 'Topics people watch videos about, with your own YouTube key.', 'chatbot' ),
				'benefits' => array(
					__( 'Spot rising topics early.', 'chatbot' ),
					__( 'Learn the questions your audience asks on video.', 'chatbot' ),
					__( 'Write about what people already watch, while it is popular.', 'chatbot' ),
				),
				'features' => array(
					__( 'Works with your own YouTube Data API key.', 'chatbot' ),
					__( 'Popular videos of the last 30 days on up to five main topics per run.', 'chatbot' ),
					__( 'Views per day and question-style titles on your main topics.', 'chatbot' ),
					__( 'Shows whether your site already covers the topic.', 'chatbot' ),
					__( 'Each idea links to its videos.', 'chatbot' ),
					__( 'Only ids, titles and counts are kept, for 30 days.', 'chatbot' ),
				),
			),
			'wikipedia'          => array(
				'label'    => __( 'Wikipedia', 'chatbot' ),
				'text'     => __( 'What is gaining interest on your topics, from Wikipedia page views.', 'chatbot' ),
				'benefits' => array(
					__( 'Time your content with rising interest.', 'chatbot' ),
					__( 'Spot seasonal peaks before they arrive.', 'chatbot' ),
					__( 'Free to use: no key or account needed.', 'chatbot' ),
				),
				'features' => array(
					__( 'Page views from Wikipedia (Wikimedia); no key needed.', 'chatbot' ),
					__( 'Each main topic matched to a Wikipedia article you can confirm or change.', 'chatbot' ),
					__( 'This week against the three weeks before and the same week last year.', 'chatbot' ),
					__( 'Rising linked articles as subtopics.', 'chatbot' ),
					__( 'Weighted lower for local businesses; only titles and counts are kept.', 'chatbot' ),
				),
			),
			'ai_web'             => array(
				'label'    => __( 'AI web research', 'chatbot' ),
				'text'     => __( 'What is new on your topics, each idea with its source link.', 'chatbot' ),
				'benefits' => array(
					__( 'Be first to cover the news in your field.', 'chatbot' ),
					__( 'Fresh ideas without reading dozens of sites.', 'chatbot' ),
					__( 'Every idea is easy to check at its source.', 'chatbot' ),
				),
				'features' => array(
					__( 'Uses a model with web search and your own key: OpenAI, Anthropic Claude, Google Gemini or OpenRouter.', 'chatbot' ),
					__( 'What is new on your main topics.', 'chatbot' ),
					__( 'Every idea links to where it was found.', 'chatbot' ),
					__( 'Merged with your other Topic Finder sources in one ranked list.', 'chatbot' ),
				),
			),
			'group_ai'           => array(
				'label'    => __( 'Group my posts with AI', 'chatbot' ),
				'text'     => __( 'Puts every post, page and product into a topic of your topic map.', 'chatbot' ),
				'benefits' => array(
					__( 'See at a glance what you cover and what is missing.', 'chatbot' ),
					__( 'Hours of sorting old posts done for you.', 'chatbot' ),
					__( 'A clear base for topic clusters and content plans.', 'chatbot' ),
				),
				'features' => array(
					__( 'Posts, pages and products grouped, up to 1,000 per run, in the background.', 'chatbot' ),
					__( 'The main search of each page.', 'chatbot' ),
					__( 'Main guides, parent topics and up to three missing subtopics per topic.', 'chatbot' ),
					__( 'Missing subtopics become Topic Finder ideas.', 'chatbot' ),
					__( '"Group my posts with AI" for new pages, or "Regroup everything".', 'chatbot' ),
					__( 'Create, rename, lock and delete topics, or move a page by hand.', 'chatbot' ),
					__( 'Topics and pages you set by hand are never changed.', 'chatbot' ),
				),
			),
			'clusters'           => array(
				'label'    => __( 'Topic clusters', 'chatbot' ),
				'text'     => __( 'A main guide per topic, linked to every page of the topic and back.', 'chatbot' ),
				'benefits' => array(
					__( 'Show search engines you are the authority on a topic.', 'chatbot' ),
					__( 'Visitors find the next page to read, so they stay longer.', 'chatbot' ),
					__( 'No linking by hand as your site grows.', 'chatbot' ),
				),
				'features' => array(
					__( 'Choose a main guide per topic in the Coverage view.', 'chatbot' ),
					__( 'The guide links to every page of the topic, and every page links back.', 'chatbot' ),
					__( 'Missing links added in one marked block per page ("More on this topic" and "Part of our guide").', 'chatbot' ),
					__( 'Links put back when pages are edited, added or moved.', 'chatbot' ),
					__( 'Only the marked block is changed; link upkeep can be switched off.', 'chatbot' ),
				),
			),
			'ai_plan'            => array(
				'label'    => __( 'AI content plan', 'chatbot' ),
				'text'     => __( 'A dated plan for weeks of content, from one goal.', 'chatbot' ),
				'benefits' => array(
					__( 'Never wonder what to write next.', 'chatbot' ),
					__( 'A series that builds authority, not random posts.', 'chatbot' ),
					__( 'Weeks of planning done in minutes.', 'chatbot' ),
				),
				'features' => array(
					__( 'Goal, topic, pieces a week and weeks: a dated plan to review and edit.', 'chatbot' ),
					__( 'Type a topic or pick one from your topic map, or use "Plan a series" in Coverage.', 'chatbot' ),
					__( 'A series starts with the main guide when the topic has none, and never repeats pages you have.', 'chatbot' ),
					__( 'A format and technique per planned piece.', 'chatbot' ),
					__( 'Planned drafts on the calendar for you or another author.', 'chatbot' ),
				),
			),
			'drafts_ahead'       => array(
				'label'    => __( 'Drafts written ahead', 'chatbot' ),
				'text'     => __( 'Planned drafts are written a few days before their date.', 'chatbot' ),
				'benefits' => array(
					__( 'Your calendar fills itself; you only review.', 'chatbot' ),
					__( 'Never miss a publishing day.', 'chatbot' ),
					__( 'You stay in control of what goes live.', 'chatbot' ),
				),
				'features' => array(
					__( 'Written three days ahead by default.', 'chatbot' ),
					__( 'Written as the planned author, in the planned format and technique.', 'chatbot' ),
					__( 'No outline approval needed; the draft takes the planned day.', 'chatbot' ),
					__( 'Up to five drafts per run, twice a day.', 'chatbot' ),
					__( 'Nothing is published unless you turn on scheduling.', 'chatbot' ),
					__( 'Drafts that need review are scheduled only once approved.', 'chatbot' ),
				),
			),
			'reminders'          => array(
				'label'    => __( 'Seasonal and refresh reminders', 'chatbot' ),
				'text'     => __( 'Seasonal and store events, and posts that need an update, on your calendar.', 'chatbot' ),
				'benefits' => array(
					__( 'Never miss a season or let a top post go stale.', 'chatbot' ),
					__( 'Keep the traffic of older posts that start to slip.', 'chatbot' ),
					__( 'Plan sales content around the dates that matter to your store.', 'chatbot' ),
				),
				'features' => array(
					__( 'Seasonal and store events on the calendar, each with a next step.', 'chatbot' ),
					__( 'Posts with decay alerts first.', 'chatbot' ),
					__( 'Posts not updated for a year, two a week, oldest first.', 'chatbot' ),
					__( 'Each reminder opens Improve Content.', 'chatbot' ),
					__( 'Can be turned off.', 'chatbot' ),
				),
			),
			'learning'           => array(
				'label'    => __( 'Learning from your edits', 'chatbot' ),
				'text'     => __( 'Your voice gets better from the changes you make to drafts.', 'chatbot' ),
				'benefits' => array(
					__( 'Fewer edits on every new draft.', 'chatbot' ),
					__( 'Your voice gets sharper without extra work.', 'chatbot' ),
					__( 'No extra AI cost.', 'chatbot' ),
				),
				'features' => array(
					__( 'Counts the words you remove from or add to drafts, per voice.', 'chatbot' ),
					__( 'Words removed in three or more drafts are suggested for the "never use" list.', 'chatbot' ),
					__( 'Words added in three or more drafts are suggested as preferred words.', 'chatbot' ),
					__( 'Rule-based; common words are ignored.', 'chatbot' ),
					__( 'Nothing changes until you approve.', 'chatbot' ),
				),
			),
			'site_setup'         => array(
				'label'    => __( 'Setup that reads your site', 'chatbot' ),
				'text'     => __( 'AI Writer reads your site and suggests your business details and voice.', 'chatbot' ),
				'benefits' => array(
					__( 'Set up in minutes, not hours.', 'chatbot' ),
					__( 'Every draft knows your business from the first day.', 'chatbot' ),
					__( 'More accurate drafts, with fewer generic phrases.', 'chatbot' ),
				),
				'features' => array(
					__( 'Reads your site name, tagline, main pages, recent posts and product names.', 'chatbot' ),
					__( 'Suggests what you do, your audience, facts, main topics, product names and voice pages.', 'chatbot' ),
					__( 'Saved only when you apply it.', 'chatbot' ),
					__( 'Then added to every prompt.', 'chatbot' ),
				),
			),
			'chat_words'         => array(
				'label'    => __( 'Saved chat sessions', 'chatbot' ),
				'text'     => __( 'Your customers\' own words, from your saved chatbot conversations.', 'chatbot' ),
				'benefits' => array(
					__( 'Copy that speaks the way your customers do.', 'chatbot' ),
					__( 'Real customer phrases that make headlines and offers land.', 'chatbot' ),
					__( 'Visitor privacy is protected.', 'chatbot' ),
				),
				'features' => array(
					__( 'Adds your saved chatbot conversations to Customer words.', 'chatbot' ),
					__( 'Only visitor messages are read.', 'chatbot' ),
					__( 'Names, emails, phone numbers and addresses are removed before anything is saved or sent to AI.', 'chatbot' ),
					__( 'Grouped by topic with your other customer words, such as reviews and site searches.', 'chatbot' ),
				),
			),
			'product_voice'      => array(
				'label'    => __( 'Voice on product copy', 'chatbot' ),
				'text'     => __( 'Product descriptions that follow your brand voice\'s full style guide.', 'chatbot' ),
				'benefits' => array(
					__( 'A store that sounds like one brand.', 'chatbot' ),
					__( 'Product names written the same way on every page.', 'chatbot' ),
					__( 'Less editing on every product.', 'chatbot' ),
				),
				'features' => array(
					__( 'Your voice\'s words to use and avoid, product names and reading level on product copy.', 'chatbot' ),
					__( 'UK or US spelling, as your voice sets it.', 'chatbot' ),
					__( 'Your business details from Setup that reads your site.', 'chatbot' ),
					__( 'Any saved voice when you regenerate product copy.', 'chatbot' ),
					__( 'Compare up to four versions of a product description and mix parts.', 'chatbot' ),
				),
			),
			'product_questions'  => array(
				'label'    => __( 'Product questions from chats', 'chatbot' ),
				'text'     => __( 'The questions shoppers ask your chatbot about products, as FAQ and description ideas.', 'chatbot' ),
				'benefits' => array(
					__( 'Answer objections before they cost you a sale.', 'chatbot' ),
					__( 'Fewer repeat questions to your team.', 'chatbot' ),
					__( 'Know what shoppers need to hear before they buy.', 'chatbot' ),
				),
				'features' => array(
					__( 'Product and buying questions found in your saved chats.', 'chatbot' ),
					__( 'Ranked higher on WooCommerce stores.', 'chatbot' ),
					__( 'Grouped by topic and counted once per chat.', 'chatbot' ),
					__( 'Personal details removed before anything is sent to AI.', 'chatbot' ),
				),
			),
			'compare_mix'        => array(
				'label'    => __( 'Compare four and mix parts', 'chatbot' ),
				'text'     => __( 'Compare up to four versions and combine the best parts.', 'chatbot' ),
				'benefits' => array(
					__( 'The strongest opening, middle and close in one piece.', 'chatbot' ),
					__( 'Choose with confidence after seeing every option side by side.', 'chatbot' ),
					__( 'Learn which techniques work on your site.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to four versions side by side.', 'chatbot' ),
					__( 'Mix parts for sections, product descriptions and social posts.', 'chatbot' ),
					__( 'Compare techniques on each channel version.', 'chatbot' ),
					__( 'Past winners suggested first.', 'chatbot' ),
					__( 'Send them to an A/B test.', 'chatbot' ),
				),
			),
			'subject_tests'      => array(
				'label'    => __( 'Email subject and social tests', 'chatbot' ),
				'text'     => __( 'Test email subject lines and social versions on your real audience.', 'chatbot' ),
				'benefits' => array(
					__( 'More opens and clicks from the same list.', 'chatbot' ),
					__( 'Learn which social versions bring visitors.', 'chatbot' ),
					__( 'Decisions based on real results, not guesses.', 'chatbot' ),
				),
				'features' => array(
					__( 'Subject lines tested in AI Marketing draft campaigns.', 'chatbot' ),
					__( 'Each recipient gets one subject; no extra data is stored about them.', 'chatbot' ),
					__( 'Measured by opens and link clicks; the winner applied by your click or automatically.', 'chatbot' ),
					__( 'Social versions compared by clicks, each with its own tracked link.', 'chatbot' ),
					__( 'By time (two to four versions of one post) or by channel.', 'chatbot' ),
					__( 'Social results labelled "Indicator"; nothing is applied automatically.', 'chatbot' ),
				),
			),
		);
	}
}
