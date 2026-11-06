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
					__( 'See which channel actually brings visitors back.', 'chatbot' ),
				),
				'features' => array(
					__( 'Facebook, Instagram caption and carousel, LinkedIn, X thread, Pinterest and Google Business Profile versions.', 'chatbot' ),
					__( 'A newsletter item, a short video script and an audio narration.', 'chatbot' ),
					__( 'A tracked link in every version.', 'chatbot' ),
					__( 'Edit, approve, schedule on the calendar, copy or download them all.', 'chatbot' ),
					__( 'Campaigns: a landing section, article, posts, email and ads from one brief.', 'chatbot' ),
				),
			),
			'competitors'        => array(
				'label'    => __( 'Competitor Watch', 'chatbot' ),
				'text'     => __( 'See what your competitors publish, and the topics they cover that you do not.', 'chatbot' ),
				'benefits' => array(
					__( 'Never miss a competitor\'s new page.', 'chatbot' ),
					__( 'Find the gaps before they rank for them.', 'chatbot' ),
					__( 'Learn which offers and angles they push in their ads.', 'chatbot' ),
				),
				'features' => array(
					__( 'A weekly check of competitors\' sitemaps and feeds (only titles, headings and counts are stored).', 'chatbot' ),
					__( 'Topic gaps added to Topic Finder as ideas.', 'chatbot' ),
					__( 'Page structure and product comparison tables.', 'chatbot' ),
					__( 'Competitor ads with summaries, angles and ideas.', 'chatbot' ),
					__( '"Find competitors with AI" fills your list.', 'chatbot' ),
				),
			),
			'results'            => array(
				'label'    => __( 'Results & A/B tests', 'chatbot' ),
				'text'     => __( 'Learn which pieces bring visits, leads and orders, and test what works best on real visitors.', 'chatbot' ),
				'benefits' => array(
					__( 'Know what to write more of.', 'chatbot' ),
					__( 'Better titles and calls to action, chosen by your visitors.', 'chatbot' ),
					__( 'Topics that worked move up in Topic Finder.', 'chatbot' ),
				),
				'features' => array(
					__( 'Results per piece and per topic: visits, leads and orders.', 'chatbot' ),
					__( 'A/B tests for headlines, intros, calls to action, product descriptions and search titles.', 'chatbot' ),
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
				),
				'features' => array(
					__( 'Built from your real address or area served, phone or opening hours.', 'chatbot' ),
					__( 'Refused without a detail that is true only for that place.', 'chatbot' ),
					__( 'Warns before a second page for the same service and place.', 'chatbot' ),
					__( 'Saved as draft pages for you to check.', 'chatbot' ),
				),
			),
			'headline_lab'       => array(
				'label'    => __( 'Headline lab', 'chatbot' ),
				'text'     => __( 'Better titles for many posts at once.', 'chatbot' ),
				'benefits' => array(
					__( 'Lift clicks on older posts in minutes.', 'chatbot' ),
					__( 'Compare techniques side by side before you choose.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to ten posts per run.', 'chatbot' ),
					__( 'Four variations per technique.', 'chatbot' ),
					__( 'The cost is shown first.', 'chatbot' ),
					__( 'Nothing changes until you click "Use as title".', 'chatbot' ),
				),
			),
			'interview'          => array(
				'label'    => __( 'Interview me', 'chatbot' ),
				'text'     => __( 'Answer a few questions and AI Writer turns your own words into an article.', 'chatbot' ),
				'benefits' => array(
					__( 'Content that sounds like you and holds facts only you know.', 'chatbot' ),
					__( 'No blank page: just answer like in a conversation.', 'chatbot' ),
				),
				'features' => array(
					__( 'Guided questions about your topic.', 'chatbot' ),
					__( 'Your answers become the brief and the quotes.', 'chatbot' ),
					__( 'The outline and sections are built from your own words.', 'chatbot' ),
				),
			),
			'bulk'               => array(
				'label'    => __( 'Many from a list', 'chatbot' ),
				'text'     => __( 'Write up to 200 articles from a list or a CSV file.', 'chatbot' ),
				'benefits' => array(
					__( 'Fill a month of content in an afternoon.', 'chatbot' ),
					__( 'One budget for the whole job, so there are no surprises.', 'chatbot' ),
				),
				'features' => array(
					__( 'A list or CSV with topic, type, keywords, date and format per row.', 'chatbot' ),
					__( 'Suggested topics from Topic Finder, ranked.', 'chatbot' ),
					__( 'Duplicates are skipped and every row shows its progress.', 'chatbot' ),
					__( 'Runs in the background, also when you close the tab.', 'chatbot' ),
				),
			),
			'saved_briefs'       => array(
				'label'    => __( 'Saved briefs and templates', 'chatbot' ),
				'text'     => __( 'Save a brief once and reuse it for every similar piece, and make your own Quick write templates.', 'chatbot' ),
				'benefits' => array(
					__( 'The same quality every time.', 'chatbot' ),
					__( 'Faster briefs for you and your team.', 'chatbot' ),
				),
				'features' => array(
					__( 'Save and apply every field of a brief.', 'chatbot' ),
					__( 'Custom Quick write templates with your own questions, rules and length limit.', 'chatbot' ),
				),
			),
			'voices'             => array(
				'label'    => __( 'Several voices', 'chatbot' ),
				'text'     => __( 'A voice for your brand, each author and each product line.', 'chatbot' ),
				'benefits' => array(
					__( 'Every piece sounds right for who it speaks for.', 'chatbot' ),
					__( 'Authors keep their own style on one site.', 'chatbot' ),
				),
				'features' => array(
					__( 'Several voices, each with a full style guide.', 'chatbot' ),
					__( 'A version of each voice for every social network.', 'chatbot' ),
					__( 'Pick the voice in each brief, in the editor and in Regenerate, or make one the default.', 'chatbot' ),
				),
			),
			'humanize'           => array(
				'label'    => __( 'Deep humanize', 'chatbot' ),
				'text'     => __( 'An extra pass that rewrites only the paragraphs that still sound written by AI.', 'chatbot' ),
				'benefits' => array(
					__( 'Reads naturally from the first line to the last.', 'chatbot' ),
					__( 'Your links, numbers and facts stay exactly as they are.', 'chatbot' ),
				),
				'features' => array(
					__( 'Extra writing rules on every section.', 'chatbot' ),
					__( 'One targeted rewrite pass on the most AI-like paragraphs.', 'chatbot' ),
				),
			),
			'review'             => array(
				'label'    => __( 'Review workflow', 'chatbot' ),
				'text'     => __( 'Send drafts for review before they go live.', 'chatbot' ),
				'benefits' => array(
					__( 'Nothing goes out unchecked.', 'chatbot' ),
					__( 'A clear handover between writers and editors.', 'chatbot' ),
				),
				'features' => array(
					__( '"Needs review" and "Approved", with notes.', 'chatbot' ),
					__( 'A Library filter, a badge and a column in the posts list.', 'chatbot' ),
					__( 'Bulk drafts always need review; approving needs the Publish level.', 'chatbot' ),
				),
			),
			'web_research'       => array(
				'label'    => __( 'Web research with sources', 'chatbot' ),
				'text'     => __( 'Facts from the web, each with its link, publisher and year.', 'chatbot' ),
				'benefits' => array(
					__( 'Statistics readers can trust and check.', 'chatbot' ),
					__( 'No invented numbers.', 'chatbot' ),
				),
				'features' => array(
					__( 'Each fact comes with the page\'s exact words.', 'chatbot' ),
					__( 'Facts without a source, a quote or a real year are dropped.', 'chatbot' ),
					__( 'Figures more than two years old are marked.', 'chatbot' ),
				),
			),
			'analyse'            => array(
				'label'    => __( 'Analyse these pages', 'chatbot' ),
				'text'     => __( 'Find out what the pages that rank cover, and what none of them do.', 'chatbot' ),
				'benefits' => array(
					__( 'Write the page that deserves to rank.', 'chatbot' ),
					__( 'Stand out with what everyone else left out.', 'chatbot' ),
				),
				'features' => array(
					__( 'Page type, points every page covers, questions, target length and media.', 'chatbot' ),
					__( 'What none of them cover, and roundups that do not list you.', 'chatbot' ),
					__( 'Added to your brief in one click; their text is never copied.', 'chatbot' ),
				),
			),
			'research'           => array(
				'label'    => __( 'Research from your activity', 'chatbot' ),
				'text'     => __( 'Real numbers from your own chatbot conversations, orders, reviews and Search Console.', 'chatbot' ),
				'benefits' => array(
					__( 'Original findings nobody else can copy.', 'chatbot' ),
					__( 'Pages other sites link to and cite.', 'chatbot' ),
				),
				'features' => array(
					__( 'Chats per topic and month, and whether the bot could help (no chat text is used).', 'chatbot' ),
					__( 'Items sold per product and category; ratings per product (no customer details).', 'chatbot' ),
					__( 'Top searches by clicks from Search Console.', 'chatbot' ),
					__( 'Small groups are hidden to protect privacy.', 'chatbot' ),
				),
			),
			'format_research'    => array(
				'label'    => __( 'Original research', 'chatbot' ),
				'text'     => __( 'Turn your own data into a report other sites cite.', 'chatbot' ),
				'benefits' => array(
					__( 'Earn links and citations.', 'chatbot' ),
					__( 'Numbers nobody else has.', 'chatbot' ),
				),
				'features' => array(
					__( 'Findings from your CSV or your site\'s activity, with a method section.', 'chatbot' ),
					__( 'Charts, Dataset structured data and the data as a CSV download.', 'chatbot' ),
				),
			),
			'format_stats'       => array(
				'label'    => __( 'Statistics roundup', 'chatbot' ),
				'text'     => __( 'Every figure with its source and year.', 'chatbot' ),
				'benefits' => array(
					__( 'A reference page people bookmark and link to.', 'chatbot' ),
				),
				'features' => array(
					__( 'Web research with sources.', 'chatbot' ),
					__( 'Old figures are marked and flagged for an update.', 'chatbot' ),
					__( 'Every number is checked against the facts.', 'chatbot' ),
				),
			),
			'format_comparison'  => array(
				'label'    => __( 'Comparison / vs', 'chatbot' ),
				'text'     => __( 'A fair comparison with a table and a clear verdict.', 'chatbot' ),
				'benefits' => array(
					__( 'Helps buyers choose, and choose you.', 'chatbot' ),
				),
				'features' => array(
					__( 'A side-by-side table and a "which to choose" verdict.', 'chatbot' ),
					__( 'Your products and the pages that rank as sources.', 'chatbot' ),
				),
			),
			'format_case_study'  => array(
				'label'    => __( 'Case study', 'chatbot' ),
				'text'     => __( 'One customer\'s story: the challenge, the work and the results.', 'chatbot' ),
				'benefits' => array(
					__( 'Proof that sells.', 'chatbot' ),
				),
				'features' => array(
					__( 'Built from your Persuasion Kit, uploads and an interview.', 'chatbot' ),
					__( 'Results come only from real facts you give.', 'chatbot' ),
				),
			),
			'format_template'    => array(
				'label'    => __( 'Template / framework', 'chatbot' ),
				'text'     => __( 'A copy-ready template with a worked example.', 'chatbot' ),
				'benefits' => array(
					__( 'A resource readers save and share.', 'chatbot' ),
				),
				'features' => array(
					__( 'A template block readers fill in.', 'chatbot' ),
					__( 'Word and text downloads.', 'chatbot' ),
				),
			),
			'regenerate_several' => array(
				'label'    => __( 'Regenerate several', 'chatbot' ),
				'text'     => __( 'Rewrite many pieces at once with new options.', 'chatbot' ),
				'benefits' => array(
					__( 'Refresh a whole section of your site in one go.', 'chatbot' ),
					__( 'Published pages are never changed without your review.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to 50 pieces per run, in the background.', 'chatbot' ),
					__( 'Tone, length, reading level, technique, voice and what to change.', 'chatbot' ),
					__( 'One combined cost estimate first.', 'chatbot' ),
				),
			),
			'internal_links'     => array(
				'label'    => __( 'Internal links', 'chatbot' ),
				'text'     => __( 'Links to your related pages, suggested while you write and kept up to date.', 'chatbot' ),
				'benefits' => array(
					__( 'Better rankings and longer visits.', 'chatbot' ),
					__( 'No broken or forgotten links when you add pages.', 'chatbot' ),
				),
				'features' => array(
					__( 'Link suggestions in the editor.', 'chatbot' ),
					__( 'Topic clusters with automatic link upkeep.', 'chatbot' ),
				),
			),
			'fact_check'         => array(
				'label'    => __( 'Fact check with sources', 'chatbot' ),
				'text'     => __( 'Checks the claims in a post against the web and shows the sources.', 'chatbot' ),
				'benefits' => array(
					__( 'Publish with confidence.', 'chatbot' ),
				),
				'features' => array(
					__( 'Each flagged claim with a verdict, a reason and working source links.', 'chatbot' ),
					__( 'Nothing in the post changes by itself.', 'chatbot' ),
				),
			),
			'translate'          => array(
				'label'    => __( 'Translate', 'chatbot' ),
				'text'     => __( 'A translated draft linked to the original in WPML or Polylang.', 'chatbot' ),
				'benefits' => array(
					__( 'Reach new markets fast.', 'chatbot' ),
				),
				'features' => array(
					__( 'Title, excerpt and content translated part by part.', 'chatbot' ),
					__( 'Blocks, links and shortcodes are kept.', 'chatbot' ),
					__( 'Saved as a draft linked to its original.', 'chatbot' ),
				),
			),
			'bulk_variations'    => array(
				'label'    => __( 'Bulk variations', 'chatbot' ),
				'text'     => __( 'Many variations of a post or ad at once.', 'chatbot' ),
				'benefits' => array(
					__( 'Enough copy for weeks of posts and ad tests.', 'chatbot' ),
				),
				'features' => array(
					__( 'Variations within each network\'s limits.', 'chatbot' ),
					__( 'Download them all as CSV.', 'chatbot' ),
				),
			),
			'campaigns'          => array(
				'label'    => __( 'Campaigns', 'chatbot' ),
				'text'     => __( 'A whole campaign from one brief.', 'chatbot' ),
				'benefits' => array(
					__( 'Launch an offer everywhere with one message.', 'chatbot' ),
				),
				'features' => array(
					__( 'A landing section, an article, social posts, an email and ad copy.', 'chatbot' ),
					__( 'Spread over your campaign dates on the calendar.', 'chatbot' ),
				),
			),
			'chat_ideas'         => array(
				'label'    => __( 'Ideas from saved chats', 'chatbot' ),
				'text'     => __( 'Topics from the questions visitors ask your chatbot.', 'chatbot' ),
				'benefits' => array(
					__( 'Write what your customers actually ask.', 'chatbot' ),
					__( 'Fewer repeat questions for your team and your bot.', 'chatbot' ),
				),
				'features' => array(
					__( 'Questions grouped by topic, counted once per chat.', 'chatbot' ),
					__( 'Names, emails, phone numbers and addresses removed before anything is sent to AI.', 'chatbot' ),
					__( 'Ranked by demand and by what the bot could not answer.', 'chatbot' ),
				),
			),
			'youtube'            => array(
				'label'    => __( 'YouTube', 'chatbot' ),
				'text'     => __( 'Topics people watch videos about, with your own YouTube key.', 'chatbot' ),
				'benefits' => array(
					__( 'Spot rising topics early.', 'chatbot' ),
				),
				'features' => array(
					__( 'Views per day and question-style titles on your main topics.', 'chatbot' ),
					__( 'Only ids, titles and counts are kept, for 30 days.', 'chatbot' ),
				),
			),
			'wikipedia'          => array(
				'label'    => __( 'Wikipedia', 'chatbot' ),
				'text'     => __( 'What is gaining interest on your topics, from Wikipedia page views.', 'chatbot' ),
				'benefits' => array(
					__( 'Time your content with rising interest.', 'chatbot' ),
				),
				'features' => array(
					__( 'This week against the weeks before and last year.', 'chatbot' ),
					__( 'Rising linked articles as subtopics; no key needed.', 'chatbot' ),
				),
			),
			'ai_web'             => array(
				'label'    => __( 'AI web research', 'chatbot' ),
				'text'     => __( 'What is new on your topics, each idea with its source link.', 'chatbot' ),
				'benefits' => array(
					__( 'Be first to cover the news in your field.', 'chatbot' ),
				),
				'features' => array(
					__( 'Uses a model with web search and your own key.', 'chatbot' ),
					__( 'Every idea links to where it was found.', 'chatbot' ),
				),
			),
			'group_ai'           => array(
				'label'    => __( 'Group my posts with AI', 'chatbot' ),
				'text'     => __( 'Puts every post, page and product into a topic of your topic map.', 'chatbot' ),
				'benefits' => array(
					__( 'See at a glance what you cover and what is missing.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to 1,000 pages per run, in the background.', 'chatbot' ),
					__( 'Main guides, parent topics and up to three missing subtopics per topic.', 'chatbot' ),
					__( 'Topics you set by hand are never changed.', 'chatbot' ),
				),
			),
			'clusters'           => array(
				'label'    => __( 'Topic clusters', 'chatbot' ),
				'text'     => __( 'A main guide per topic, linked to every page of the topic and back.', 'chatbot' ),
				'benefits' => array(
					__( 'Show search engines you are the authority on a topic.', 'chatbot' ),
				),
				'features' => array(
					__( 'Missing links added in one marked block per page.', 'chatbot' ),
					__( 'Links kept up to date as pages change.', 'chatbot' ),
				),
			),
			'ai_plan'            => array(
				'label'    => __( 'AI content plan', 'chatbot' ),
				'text'     => __( 'A dated plan for weeks of content, from one goal.', 'chatbot' ),
				'benefits' => array(
					__( 'Never wonder what to write next.', 'chatbot' ),
					__( 'A series that builds authority, not random posts.', 'chatbot' ),
				),
				'features' => array(
					__( 'Goal, topic, pieces a week and weeks: a plan to review and edit.', 'chatbot' ),
					__( 'A format and technique per planned piece.', 'chatbot' ),
					__( 'Planned drafts for you or another author.', 'chatbot' ),
				),
			),
			'drafts_ahead'       => array(
				'label'    => __( 'Drafts written ahead', 'chatbot' ),
				'text'     => __( 'Planned drafts are written a few days before their date.', 'chatbot' ),
				'benefits' => array(
					__( 'Your calendar fills itself; you only review.', 'chatbot' ),
				),
				'features' => array(
					__( 'Written three days ahead by default.', 'chatbot' ),
					__( 'Nothing is published unless you turn on scheduling.', 'chatbot' ),
				),
			),
			'reminders'          => array(
				'label'    => __( 'Seasonal and refresh reminders', 'chatbot' ),
				'text'     => __( 'Seasonal and store events, and posts that need an update, on your calendar.', 'chatbot' ),
				'benefits' => array(
					__( 'Never miss a season or let a top post go stale.', 'chatbot' ),
				),
				'features' => array(
					__( 'Seasonal and store events with a next step.', 'chatbot' ),
					__( 'Decay alerts and posts not updated for a year, two a week.', 'chatbot' ),
				),
			),
			'learning'           => array(
				'label'    => __( 'Learning from your edits', 'chatbot' ),
				'text'     => __( 'Your voice gets better from the changes you make to drafts.', 'chatbot' ),
				'benefits' => array(
					__( 'Fewer edits on every new draft.', 'chatbot' ),
				),
				'features' => array(
					__( 'Words you remove or add in three or more drafts become suggestions.', 'chatbot' ),
					__( 'Nothing changes until you approve.', 'chatbot' ),
				),
			),
			'site_setup'         => array(
				'label'    => __( 'Setup that reads your site', 'chatbot' ),
				'text'     => __( 'AI Writer reads your site and suggests your business details and voice.', 'chatbot' ),
				'benefits' => array(
					__( 'Set up in minutes, not hours.', 'chatbot' ),
				),
				'features' => array(
					__( 'Suggests what you do, your audience, facts, main topics, product names and voice pages.', 'chatbot' ),
					__( 'Saved only when you apply it.', 'chatbot' ),
				),
			),
			'chat_words'         => array(
				'label'    => __( 'Saved chat sessions', 'chatbot' ),
				'text'     => __( 'Your customers\' own words, from your saved chatbot conversations.', 'chatbot' ),
				'benefits' => array(
					__( 'Copy that speaks the way your customers do.', 'chatbot' ),
				),
				'features' => array(
					__( 'Only visitor messages are read.', 'chatbot' ),
					__( 'Names, emails, phone numbers and addresses are removed before anything is saved or sent to AI.', 'chatbot' ),
				),
			),
			'product_voice'      => array(
				'label'    => __( 'Voice on product copy', 'chatbot' ),
				'text'     => __( 'Product descriptions in your brand or product-line voice.', 'chatbot' ),
				'benefits' => array(
					__( 'A store that sounds like one brand.', 'chatbot' ),
				),
				'features' => array(
					__( 'Choose the voice for single products and for bulk writing.', 'chatbot' ),
				),
			),
			'product_questions'  => array(
				'label'    => __( 'Product questions from chats', 'chatbot' ),
				'text'     => __( 'The questions shoppers ask your chatbot about products, as FAQ and description ideas.', 'chatbot' ),
				'benefits' => array(
					__( 'Answer objections before they cost you a sale.', 'chatbot' ),
				),
				'features' => array(
					__( 'Product questions grouped from your saved chats.', 'chatbot' ),
					__( 'Personal details removed before anything is sent to AI.', 'chatbot' ),
				),
			),
			'compare_mix'        => array(
				'label'    => __( 'Compare four and mix parts', 'chatbot' ),
				'text'     => __( 'Compare up to four versions and combine the best parts.', 'chatbot' ),
				'benefits' => array(
					__( 'The strongest opening, middle and close in one piece.', 'chatbot' ),
				),
				'features' => array(
					__( 'Up to four versions side by side.', 'chatbot' ),
					__( 'Mix parts for sections, product descriptions and social posts.', 'chatbot' ),
					__( 'Send them to an A/B test.', 'chatbot' ),
				),
			),
			'subject_tests'      => array(
				'label'    => __( 'Email subject and social tests', 'chatbot' ),
				'text'     => __( 'Test email subject lines and social versions on your real audience.', 'chatbot' ),
				'benefits' => array(
					__( 'More opens and clicks from the same list.', 'chatbot' ),
				),
				'features' => array(
					__( 'Subject lines tested in AI Marketing campaigns.', 'chatbot' ),
					__( 'Social versions compared by clicks, with tracked links.', 'chatbot' ),
				),
			),
		);
	}
}
