<?php
/**
 * WPBot Free: Help (owner, 12 November 2026): a Help page with a section for each major feature, in the
 * ChatBot Settings design (header card, left tab card, content card), and Get Support in the WPBot menu,
 * linking to the plugin's forum on WordPress.org. Both sit above Upgrade to Pro.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Free_Help' ) ) {

	/**
	 * Help page and Get Support link.
	 */
	final class QCLD_WPBot_Free_Help {

		const SLUG        = 'wpbot-help';
		const SUPPORT_URL = 'https://wordpress.org/support/plugin/chatbot/';
		const DOCS_URL    = 'https://wpbot.pro/docs/';

		/**
		 * Below each Help section: what WPBot Pro adds to that feature.
		 *
		 * @param string $section Section id.
		 * @return void
		 */
		public static function pro_lists( $section ) {
			$map = array(
				'chatbot'   => 'start_menu_pro',
				'ai'        => 'chatbot_pro',
				'knowledge' => 'kb_sources',
				'writer'    => 'writer_pro',
				'seo'       => 'seo_pro',
				'social'    => 'social_pro',
				'insights'  => 'insight_pro',
				'marketing' => 'marketing_pro',
				'automator' => 'automator_pro',
			);
			if ( isset( $map[ $section ] ) ) {
				qcld_wpbot_free_pro_lists( $map[ $section ], array( 'compact' => true ) );
			}
		}

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			add_filter( 'qcld_wpbot_menu_order', array( __CLASS__, 'order' ) );
			add_action( 'qcld_wpbot_free_help_section', array( __CLASS__, 'pro_lists' ) );
		}

		/**
		 * Help and Get Support in the WPBot menu.
		 *
		 * @return void
		 */
		public static function menu() {
			global $submenu;
			add_submenu_page( 'wpbot_openAi', __( 'WPBot Help', 'chatbot' ), __( 'Help', 'chatbot' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
			if ( current_user_can( 'manage_options' ) ) {
				// An external link, like Upgrade to Pro: WordPress uses the slug as the address.
				$submenu['wpbot_openAi'][] = array( __( 'Get Support', 'chatbot' ), 'manage_options', self::SUPPORT_URL ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding a menu link.
			}
		}

		/**
		 * Help, then Get Support, just above Upgrade to Pro (WPBot menu order).
		 *
		 * @param string[] $order Menu slugs.
		 * @return string[]
		 */
		public static function order( $order ) {
			$order = array_values( array_diff( (array) $order, array( self::SLUG, self::SUPPORT_URL ) ) );
			$at    = count( $order );
			foreach ( $order as $i => $slug ) {
				if ( 0 === strpos( (string) $slug, 'https://www.wpbot.pro' ) ) {
					$at = $i;
					break;
				}
			}
			array_splice( $order, $at, 0, array( self::SLUG, self::SUPPORT_URL ) );
			return $order;
		}

		/**
		 * The page's stylesheet.
		 *
		 * @param string $hook Admin page hook.
		 * @return void
		 */
		public static function assets( $hook ) {
			if ( false === strpos( (string) $hook, self::SLUG ) ) {
				return;
			}
			$version = defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '1';
			// The WPBot header card's styles (the same file the ChatBot pages load).
			if ( ! wp_style_is( 'qlcd-wp-chatbot-admin-style', 'registered' ) ) {
				wp_register_style( 'qlcd-wp-chatbot-admin-style', QCLD_wpCHATBOT_PLUGIN_URL . 'css/admin-style.css', array(), $version, 'screen' );
			}
			wp_enqueue_style( 'qcld-wpbot-free-help', QCLD_wpCHATBOT_PLUGIN_URL . 'css/wpbot-free-help.css', array( 'dashicons', 'qlcd-wp-chatbot-admin-style' ), $version );
		}

		/**
		 * The section being shown.
		 *
		 * @return string
		 */
		public static function current() {
			$sections = self::sections();
			$section  = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A tab.
			return isset( $sections[ $section ] ) ? $section : (string) key( $sections );
		}

		/**
		 * A section's address.
		 *
		 * @param string $section Section id.
		 * @return string
		 */
		public static function url( $section ) {
			return add_query_arg(
				array(
					'page'    => self::SLUG,
					'section' => $section,
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * The Help sections: one per major feature.
		 *
		 * Each: title, icon (dashicon), intro, steps (in order), tips, open (screen to open: [label, admin page]).
		 *
		 * @return array<string,array>
		 */
		public static function sections() {
			$sections = array(
				'start'       => array(
					'title' => __( 'Start here', 'chatbot' ),
					'icon'  => 'dashicons-flag',
					'intro' => __( 'WPBot puts an AI chatbot on your site and adds AI tools for writing, SEO, social posts and visitor insight. Everything below works in WPBot Free. Five steps get you going.', 'chatbot' ),
					'steps' => array(
						__( 'AI Settings: choose an AI provider and paste its API key. Without a key the chatbot still answers from Simple Text Responses, FAQ and site search.', 'chatbot' ),
						__( 'Business Context: describe your business once. Every AI feature uses it, so answers and content sound like you.', 'chatbot' ),
						__( 'ChatBot: pick an icon and a theme, write the greeting and choose what the start menu offers.', 'chatbot' ),
						__( 'AI Settings > Knowledge Base: choose the pages, posts and FAQ the chatbot learns from, then click Sync now.', 'chatbot' ),
						__( 'Open your site and ask the chatbot a question a visitor would ask.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Every section of this page has the steps for one feature. Use the list on the left.', 'chatbot' ),
						__( 'Stuck? Troubleshooting and the error log are at the bottom of the list.', 'chatbot' ),
					),
					'open'  => array( __( 'Open AI Settings', 'chatbot' ), 'wpbot_openAi' ),
				),
				'chatbot'     => array(
					'title' => __( 'ChatBot', 'chatbot' ),
					'icon'  => 'dashicons-format-chat',
					'intro' => __( 'The chat window your visitors see. It greets them, offers a start menu, answers questions and collects emails, phone numbers and feedback.', 'chatbot' ),
					'steps' => array(
						__( 'ChatBot > Settings > Icons & Themes: choose the chat icon and a theme.', 'chatbot' ),
						__( 'Language Center: change the greeting and every message the bot says, in your own words.', 'chatbot' ),
						__( 'Start Menu: choose the buttons visitors see first, such as Site Search, Send us Email, FAQ and Leave your number.', 'chatbot' ),
						__( 'Simple Text Responses: add your own questions and answers. The bot matches visitors\' words to them.', 'chatbot' ),
						__( 'Chat Sessions and User Data: read conversations and the emails and numbers visitors left.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Without an AI provider the bot answers from Simple Text Responses, FAQ and site search.', 'chatbot' ),
						__( 'Show the bot on chosen pages only, or hide it on some, in Settings > General.', 'chatbot' ),
						__( 'The full ChatBot guide (intents, start menu, troubleshooting) is in ChatBot > Help and Debugging.', 'chatbot' ),
					),
					'open'  => array( __( 'Open ChatBot', 'chatbot' ), 'wpbot-panel' ),
					'more'  => array( __( 'ChatBot Help and Debugging', 'chatbot' ), 'wpbot_help_page' ),
				),
				'ai'          => array(
					'title' => __( 'AI Settings', 'chatbot' ),
					'icon'  => 'dashicons-admin-network',
					'intro' => __( 'Connect the AI that answers the chatbot and powers WPBot\'s other AI features. Keys stay on your server and are shown only as their last 4 characters.', 'chatbot' ),
					'steps' => array(
						__( 'Providers: pick the primary provider (OpenAI, Anthropic Claude, Google Gemini, OpenRouter, xAI Grok, Mistral or DeepSeek) and paste its API key. Ollama (local AI) is part of WPBot Pro.', 'chatbot' ),
						__( 'Choose a backup provider. It answers only when the primary fails: a bad key, no credit, a rate limit or an outage.', 'chatbot' ),
						__( 'Answers: set how long and in what tone the chatbot answers, and what it should never talk about.', 'chatbot' ),
						__( 'Spending and limits: set a monthly limit so AI costs never surprise you.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Use Test next to a key to check it before you save.', 'chatbot' ),
						__( 'A cheaper model for short questions saves money without hurting answers.', 'chatbot' ),
						__( 'AI Usage shows what each feature cost, day by day.', 'chatbot' ),
					),
					'open'  => array( __( 'Open AI Settings', 'chatbot' ), 'wpbot_openAi' ),
				),
				'knowledge'   => array(
					'title' => __( 'Knowledge Base', 'chatbot' ),
					'icon'  => 'dashicons-book',
					'intro' => __( 'What the chatbot learns from: your pages, posts, products, FAQ, Simple Text Responses, database tables and uploaded documents. The AI answers from these, not from guesses.', 'chatbot' ),
					'steps' => array(
						__( 'AI Settings > Knowledge Base > Sources to include: tick the content types the bot may use.', 'chatbot' ),
						__( 'Upload and sync: add PDFs or text files if you have them.', 'chatbot' ),
						__( 'Click Sync now. New and changed content is added; the status line shows what is waiting.', 'chatbot' ),
						__( 'What the chatbot knows: search it to see which pieces answer a question.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Changing the AI provider means syncing again: the notice at the top tells you when.', 'chatbot' ),
						__( 'FAQ answers are added automatically when you save the FAQ.', 'chatbot' ),
					),
					'open'  => array( __( 'Open Knowledge Base', 'chatbot' ), 'wpbot_openAi&section=knowledge' ),
				),
				'business'    => array(
					'title' => __( 'Business Context', 'chatbot' ),
					'icon'  => 'dashicons-store',
					'intro' => __( 'Describe your business once: what you sell, who you sell to, how you sound and what makes you different. The chatbot, AI Writer, SEO, Social and AI Marketing all use it.', 'chatbot' ),
					'steps' => array(
						__( 'Fill in the basics: business name, what you offer and your customers.', 'chatbot' ),
						__( 'Add your voice: a few words on tone and words to use or avoid.', 'chatbot' ),
						__( 'Upload a brochure or price list if you have one; WPBot reads it and suggests what to add.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'It takes about 5 minutes and makes every AI result more specific.', 'chatbot' ),
						__( 'Update it when your offer changes; every feature picks up the change.', 'chatbot' ),
					),
					'open'  => array( __( 'Open Business Context', 'chatbot' ), 'wpbot-business-context' ),
				),
				'writer'      => array(
					'title' => __( 'AI Writer', 'chatbot' ),
					'icon'  => 'dashicons-edit-page',
					'intro' => __( 'Write blog posts and pages with AI: from a short brief to an outline, a full draft with headings, images and a meta description, saved as a WordPress draft for you to edit.', 'chatbot' ),
					'steps' => array(
						__( 'AI Writer > New: describe the topic and who it is for.', 'chatbot' ),
						__( 'Check the outline and change the headings you want.', 'chatbot' ),
						__( 'Write the draft. Images come from Openverse, or from Pexels and Pixabay once you add their keys.', 'chatbot' ),
						__( 'Open the draft in WordPress, edit and publish.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Choose an article format (how-to, list, review…) for a better structure.', 'chatbot' ),
						__( 'Regenerate a single section instead of the whole article.', 'chatbot' ),
					),
					'open'  => array( __( 'Open AI Writer', 'chatbot' ), 'wpbot-ai-writer' ),
				),
				'seo'         => array(
					'title' => __( 'SEO & AI Search', 'chatbot' ),
					'icon'  => 'dashicons-search',
					'intro' => __( 'Get found on Google and in AI answers: see what your pages rank for, fix what holds them back and check how AI assistants describe your business.', 'chatbot' ),
					'steps' => array(
						__( 'Connect Google Search Console (your own Google app, read-only) to import your searches and clicks.', 'chatbot' ),
						__( 'Run the page audit and work through the fixes, most valuable first.', 'chatbot' ),
						__( 'Check AI visibility to see how AI assistants answer questions about your business.', 'chatbot' ),
						__( 'Publish llms.txt so AI tools find your best pages.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Speed checks use Google PageSpeed Insights.', 'chatbot' ),
						__( 'Local businesses: connect Google Business Profile for reviews and profile health.', 'chatbot' ),
					),
					'open'  => array( __( 'Open SEO & AI Search', 'chatbot' ), 'wpbot-seo-ai-search' ),
				),
				'social'      => array(
					'title' => __( 'Social', 'chatbot' ),
					'icon'  => 'dashicons-share',
					'intro' => __( 'Write on-brand posts for each network from one idea, publish or schedule them from WordPress, and see which posts brought visitors.', 'chatbot' ),
					'steps' => array(
						__( 'Social > Accounts: connect Facebook Pages, Instagram, Threads, Google Business Profile or a Telegram channel.', 'chatbot' ),
						__( 'Create post: write one idea, or let Write with AI make a version for each network.', 'chatbot' ),
						__( 'Publish now or schedule; the Calendar shows what goes out when.', 'chatbot' ),
						__( 'Posts: see each post\'s clicks through its tracked link.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Each network needs your own app (Meta, Threads, Google). The Connect screens walk you through it.', 'chatbot' ),
						__( 'Scheduled posts go out when WordPress cron runs; quiet sites should use a server cron (Settings > Advanced).', 'chatbot' ),
					),
					'open'  => array( __( 'Open Social', 'chatbot' ), 'wpbot-social' ),
				),
				'insights'    => array(
					'title' => __( 'Site Insight', 'chatbot' ),
					'icon'  => 'dashicons-visibility',
					'intro' => __( 'See how visitors use your site: session replays, click and scroll heatmaps, and frustration signals such as rage clicks, dead clicks and JavaScript errors. Recordings stay on your server.', 'chatbot' ),
					'steps' => array(
						__( 'Site Insight > Settings: turn recording on and choose how many visits to record.', 'chatbot' ),
						__( 'Recordings: watch visits; frustration signals are marked on the timeline.', 'chatbot' ),
						__( 'Heatmaps: see where people click and how far they scroll on each page.', 'chatbot' ),
						__( 'Turn on email reports for a weekly or monthly summary.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Form fields are masked in the browser before anything is sent.', 'chatbot' ),
						__( 'Recording follows your consent banner and Do Not Track.', 'chatbot' ),
					),
					'open'  => array( __( 'Open Site Insight', 'chatbot' ), 'qcin-insights' ),
				),
				'marketing'   => array(
					'title' => __( 'AI Marketing', 'chatbot' ),
					'icon'  => 'dashicons-megaphone',
					'intro' => __( 'Ready-made AI tools for marketing copy: ads, emails, product descriptions, landing page text and more, written from your Business Context.', 'chatbot' ),
					'steps' => array(
						__( 'AI Marketing: pick a tool from the list.', 'chatbot' ),
						__( 'Fill in the few fields it asks for.', 'chatbot' ),
						__( 'Generate, then copy the text or generate another version.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Fill in Business Context first: every tool uses it.', 'chatbot' ),
					),
					'open'  => array( __( 'Open AI Marketing', 'chatbot' ), 'wpbot-marketing' ),
				),
				'automator'   => array(
					'title' => __( 'Automator', 'chatbot' ),
					'icon'  => 'dashicons-randomize',
					'intro' => __( 'Connect events on your site to actions with a visual, no-code builder: when something happens (a form is sent, a post is published), WPBot does something (sends an email, asks the AI, posts a message).', 'chatbot' ),
					'steps' => array(
						__( 'Automator > New automation: choose the trigger.', 'chatbot' ),
						__( 'Add one or more actions and fill in their fields.', 'chatbot' ),
						__( 'Turn the automation on and test it.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'AI actions use the provider and limits from AI Settings.', 'chatbot' ),
					),
					'open'  => array( __( 'Open Automator', 'chatbot' ), 'wpbot-automator' ),
				),
				'permissions' => array(
					'title' => __( 'Permissions', 'chatbot' ),
					'icon'  => 'dashicons-groups',
					'intro' => __( 'Choose which user roles may view, use, publish with and change the settings of each WPBot section. Administrators always have full access.', 'chatbot' ),
					'steps' => array(
						__( 'Permissions: find the role, for example Editor.', 'chatbot' ),
						__( 'Tick what it may do in each section: view, use, publish or change settings.', 'chatbot' ),
						__( 'Save. The menu shows each person only what their role allows.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'Give writers "use" in AI Writer without "settings", so they can write but not change keys or limits.', 'chatbot' ),
					),
					'open'  => array( __( 'Open Permissions', 'chatbot' ), 'wpbot-permissions' ),
				),
				'trouble'     => array(
					'title' => __( 'Troubleshooting', 'chatbot' ),
					'icon'  => 'dashicons-sos',
					'intro' => __( 'Something not working? Most problems are a missing or used-up AI key, a knowledge base that needs syncing, or another plugin\'s script. The error log shows what went wrong.', 'chatbot' ),
					'steps' => array(
						__( 'AI Settings > Error log: look for recent errors and what they say.', 'chatbot' ),
						__( 'Check the AI key with Test, and the provider account\'s credit balance.', 'chatbot' ),
						__( 'If the chat window does not open, clear your caching plugin\'s cache and try a default theme.', 'chatbot' ),
						__( 'Still stuck? Describe the problem in the error log and send it to WPBot support, or ask on the support forum.', 'chatbot' ),
					),
					'tips'  => array(
						__( 'API keys, passwords and email addresses are removed from the report before it is sent.', 'chatbot' ),
					),
					'open'  => array( __( 'Open the error log', 'chatbot' ), 'wpbot_openAi&section=errors' ),
				),
			);
			/**
			 * WPBot Free Help sections.
			 *
			 * @param array $sections Sections (see self::sections()).
			 */
			return (array) apply_filters( 'qcld_wpbot_free_help_sections', $sections );
		}

		/**
		 * Print the page.
		 *
		 * @return void
		 */
		public static function render() {
			$sections = self::sections();
			$current  = self::current();
			$s        = $sections[ $current ];
			echo '<div class="wrap qcld-main-wrapper qcwph">';
			if ( class_exists( 'qcld_wb_Chatbot_free' ) ) {
				qcld_wb_Chatbot_free::qcld_wb_chatbot_get_instance()->render_unified_header( self::SLUG, false );
			}
			echo '<div class="qcwph-layout">';
			echo '<nav class="qcwph-tabs" aria-label="' . esc_attr__( 'Help topics', 'chatbot' ) . '"><ul>';
			foreach ( $sections as $id => $section ) {
				$active = $id === $current;
				echo '<li><a href="' . esc_url( self::url( $id ) ) . '" class="' . ( $active ? 'is-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '><span class="dashicons ' . esc_attr( $section['icon'] ) . '" aria-hidden="true"></span>' . esc_html( $section['title'] ) . '</a></li>';
			}
			echo '</ul></nav>';
			echo '<section class="qcwph-card" aria-labelledby="qcwph-title">';
			echo '<h2 id="qcwph-title">' . esc_html( $s['title'] ) . '</h2>';
			echo '<p class="qcwph-intro">' . esc_html( $s['intro'] ) . '</p>';
			if ( ! empty( $s['steps'] ) ) {
				echo '<h3>' . esc_html__( 'How to set it up', 'chatbot' ) . '</h3><ol class="qcwph-steps">';
				foreach ( $s['steps'] as $step ) {
					echo '<li>' . esc_html( $step ) . '</li>';
				}
				echo '</ol>';
			}
			if ( ! empty( $s['tips'] ) ) {
				echo '<h3>' . esc_html__( 'Good to know', 'chatbot' ) . '</h3><ul class="qcwph-tips">';
				foreach ( $s['tips'] as $tip ) {
					echo '<li>' . esc_html( $tip ) . '</li>';
				}
				echo '</ul>';
			}
			/** Fires below a Help section (WPBot Free), for example to add what WPBot Pro adds. */
			do_action( 'qcld_wpbot_free_help_section', $current );
			echo '<p class="qcwph-actions">';
			if ( ! empty( $s['open'] ) ) {
				echo '<a class="qcwph-btn is-primary" href="' . esc_url( admin_url( 'admin.php?page=' . $s['open'][1] ) ) . '">' . esc_html( $s['open'][0] ) . '</a>';
			}
			if ( ! empty( $s['more'] ) ) {
				echo '<a class="qcwph-btn" href="' . esc_url( admin_url( 'admin.php?page=' . $s['more'][1] ) ) . '">' . esc_html( $s['more'][0] ) . '</a>';
			}
			echo '<a class="qcwph-btn" href="' . esc_url( self::DOCS_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'chatbot' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'chatbot' ) . '</span></a>';
			echo '<a class="qcwph-btn" href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Ask on the support forum', 'chatbot' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'chatbot' ) . '</span></a>';
			echo '</p></section></div></div>';
		}
	}

	QCLD_WPBot_Free_Help::init();
}
