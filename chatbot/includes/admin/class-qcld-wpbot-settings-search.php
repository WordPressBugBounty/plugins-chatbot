<?php
/**
 * Search settings and pages (owner, 23 November 2026: "Search Settings and pages is slow to type
 * and can't find relevant settings most of the time").
 *
 * The band's search button opened WordPress's command palette with only the top WPBot pages as
 * commands, so a search for "API key", "exit intent" or "welcome message" found nothing, and the
 * palette is heavy to type in. This replaces it on WPBot pages with a small, fast search:
 *
 * - An index built here: the WPBot pages (WPBot Core's tabs, the ChatBot tabs and their pages,
 *   the other WPBot menus), the AI Settings sections, the ChatBot settings sections, with the words
 *   people type for each (keywords()).
 * - The settings on the open page (their titles and labels), so a setting can be found and
 *   scrolled to where it is.
 * - Matching in the browser, on every key, with word prefixes, all words, and one typo allowed.
 *
 * The search opens from the band's button and Ctrl+K / Cmd+K on WPBot pages. It only reads; it
 * changes no setting.
 *
 * WPBot Free: same class as WPBot Pro, with Free's ChatBot settings sections and without the Pro
 * pages (Retargeting, store automations, Module Manager, White Label, licence, live chat, tickets).
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Settings_Search' ) ) {

	/**
	 * Search settings and pages.
	 */
	final class QCLD_WPBot_Settings_Search {

		/**
		 * Script and style handle.
		 */
		const HANDLE = 'qcld-wpbot-settings-search';

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 99 );
		}

		/**
		 * The words people type for a destination, by key: "page:<slug>", "ai:<section>",
		 * "chatbot:<tab>", or "title:<words in the title>".
		 *
		 * @return array<string,string>
		 */
		public static function keywords() {
			return array(
				// AI Settings.
				'ai:providers'               => 'api key keys openai chatgpt gpt gemini google claude anthropic grok xai mistral deepseek openrouter ollama model models provider primary backup enable ai switch on ai',
				'ai:answers'                 => 'system prompt prompt instructions tone personality temperature length streaming stream friendly error fallback message citations sources answer style',
				'ai:knowledge'               => 'knowledge base train training pdf upload documents files embeddings crawl website content rag pages posts',
				'ai:known'                   => 'what the chatbot knows sources content indexed',
				'ai:cache'                   => 'answer cache saved answers cached',
				'ai:limits'                  => 'limits rate limit budget spending cost tokens price money monthly',
				'ai:usage'                   => 'usage report cost tokens used spending chart',
				'ai:errors'                  => 'error log errors debug problems support report failing',
				'ai:data'                    => 'data uninstall delete data privacy gdpr remove',
				'ai:notifications'           => 'notifications email emails alerts digest frequency',
				// ChatBot settings sections.
				'chatbot:general'            => 'basics enable chatbot disable on off show hide pages exclude mobile position bot name chatbot name',
				'chatbot:greeting'           => 'greeting welcome message hello ask name ask email visitor name email phone skip greetings',
				'chatbot:design'             => 'design colors colours color theme template dark mode font size window layout style header launcher finish look',
				'chatbot:themes'             => 'icons icon avatar launcher chat button image logo agent picture position left right',
				'chatbot:startmenu'          => 'start menu buttons menu items quick replies chips options site search search posts',
				'chatbot:behaviour'          => 'behaviour behavior open automatically auto open start menu after answer',
				'chatbot:language'           => 'language translation texts labels translate wording messages strings change language',
				'chatbot:support'            => 'faq questions answers frequently asked',
				'chatbot:ai'                 => 'dialogflow google intents',
				'chatbot:rpl'                => 'conversational forms form builder conversations',
				'chatbot:custom_css'         => 'custom css style stylesheet code',
				// ChatBot pages.
				'page:simple-text-response'  => 'str simple text responses custom answers q&a questions train answers',
				'page:email-subscription'    => 'subscribers email subscription newsletter leads emails list user data',
				'page:wbcs-botsessions-page' => 'sessions chat history conversations transcripts export',
				'page:wpbot_ai_actions'      => 'ai actions actions tools functions forms',
				'title:permissions'          => 'permissions roles capabilities access users who can',
			);
		}

		/**
		 * A menu title as people read it: no count bubble, no markup, no entities.
		 *
		 * @param string $text Title.
		 * @return string
		 */
		public static function clean( $text ) {
			$text = preg_replace( '#<span[^>]*>.*?</span>#s', '', (string) $text ); // Count bubbles, screen reader notes.
			$text = trim( wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
			return trim( preg_replace( '/\s*\d+$/', '', $text ) );
		}

		/**
		 * The index: each entry t (title), c (where), u (address), k (keywords).
		 *
		 * @return array[]
		 */
		public static function index() {
			$words   = self::keywords();
			$entries = array();
			$add     = static function ( $title, $crumb, $url, $key = '' ) use ( &$entries, $words ) {
				$title = self::clean( $title );
				$crumb = self::clean( $crumb );
				if ( '' === $title || '' === (string) $url ) {
					return;
				}
				$k = '' !== $key && isset( $words[ $key ] ) ? $words[ $key ] : '';
				foreach ( $words as $wkey => $list ) {
					if ( 0 === strpos( $wkey, 'title:' ) && false !== stripos( $title . ' ' . $crumb, substr( $wkey, 6 ) ) ) {
						$k .= ' ' . $list;
					}
				}
				$id             = md5( $url . '|' . $title );
				$entries[ $id ] = array(
					't' => $title,
					'c' => (string) $crumb,
					'u' => (string) $url,
					'k' => trim( $k ),
				);
			};

			$chatbot = __( 'ChatBot', 'chatbot' );
			$arrow   = ' → ';

			// AI Settings sections.
			$ai = '\QuantumCloud\Chatbot\Admin\Ai_Settings_Page';
			if ( class_exists( $ai ) && is_callable( array( $ai, 'sections' ) ) ) {
				foreach ( $ai::sections() as $id => $item ) {
					$add( is_array( $item ) ? $item[0] : $item, __( 'AI / Settings', 'chatbot' ), $ai::url( (string) $id ), 'ai:' . $id );
				}
			}

			// ChatBot settings sections (admin.php?page=wpbot-panel&tab=…).
			$tabs = array(
				'general'    => __( 'Basics', 'chatbot' ),
				'greeting'   => __( 'Greeting & visitor details', 'chatbot' ),
				'design'     => __( 'Customize ChatBot Design', 'chatbot' ),
				'themes'     => __( 'Customize ChatBot Icons', 'chatbot' ),
				'startmenu'  => __( 'Start Menu Items', 'chatbot' ),
				'behaviour'  => __( 'Start Menu Behaviour', 'chatbot' ),
				'language'   => __( 'Change Language', 'chatbot' ),
				'support'    => __( 'FAQ', 'chatbot' ),
				'ai'         => __( 'Dialogflow', 'chatbot' ),
				'rpl'        => __( 'Conversational Form', 'chatbot' ),
				'custom_css' => __( 'Custom CSS', 'chatbot' ),
			);
			if ( current_user_can( function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'wpbot_settings' ) : 'manage_options' ) ) {
				foreach ( $tabs as $tab => $title ) {
					$add( $title, $chatbot . $arrow . __( 'ChatBot Settings', 'chatbot' ), admin_url( 'admin.php?page=wpbot-panel&tab=' . $tab ), 'chatbot:' . $tab );
				}
			}

			// The ChatBot tabs and their pages (only what the user may open).
			if ( class_exists( 'QCLD_WPBot_Admin_Nav' ) ) {
				foreach ( QCLD_WPBot_Admin_Nav::tabs() as $tab ) {
					$add( $tab['title'], $chatbot, $tab['url'], 'tab:' . $tab['id'] );
					foreach ( (array) $tab['pages'] as $page ) {
						$crumb = $chatbot . $arrow . $tab['title'];
						if ( ! empty( $page['group'] ) && isset( $tab['groups'][ $page['group'] ]['title'] ) ) {
							$crumb .= $arrow . $tab['groups'][ $page['group'] ]['title'];
						}
						$add( $page['title'], $crumb, $page['url'], 'page:' . $page['slug'] );
					}
				}
			}

			// WPBot Core's tabs (AI / Settings, ChatBot, AI Writer, …) and the other WPBot menus.
			global $menu, $submenu;
			$design = '\QuantumCloud\WPBotCore\Admin\Design';
			if ( class_exists( $design ) && is_callable( array( $design, 'tabs' ) ) ) {
				foreach ( $design::tabs() as $tab ) {
					if ( empty( $tab['external'] ) ) {
						$add( $tab['label'], 'WPBot', $tab['url'], 'page:' . $tab['slug'] );
					}
				}
			}
			foreach ( (array) $menu as $top ) {
				if ( empty( $top[2] ) || ! preg_match( '/wpbot|qcld|wbca|chatbot|kbx/i', (string) $top[2] ) ) {
					continue;
				}
				$top_title = self::clean( (string) $top[0] );
				foreach ( isset( $submenu[ $top[2] ] ) ? (array) $submenu[ $top[2] ] : array() as $item ) {
					if ( ! isset( $item[0], $item[2] ) || ( isset( $item[1] ) && ! current_user_can( $item[1] ) ) ) {
						continue;
					}
					$slug = (string) $item[2];
					$url  = false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
					$add( $item[0], $top_title, $url, 'page:' . $slug );
				}
			}

			/**
			 * The settings search's entries (each: t title, c where, u address, k keywords).
			 *
			 * @param array[] $entries Entries.
			 */
			return array_values( (array) apply_filters( 'qcld_wpbot_settings_search_index', array_values( $entries ) ) );
		}

		/**
		 * On WPBot pages (WPBot Core's design is on the page).
		 *
		 * @return void
		 */
		public static function enqueue() {
			if ( ! wp_script_is( 'qcld-wpbot-core-design', 'enqueued' ) || ! defined( 'QCLD_wpCHATBOT_PLUGIN_URL' ) ) {
				return;
			}
			$dir = defined( 'QCLD_wpCHATBOT_PLUGIN_DIR_PATH' ) ? QCLD_wpCHATBOT_PLUGIN_DIR_PATH : plugin_dir_path( dirname( __DIR__ ) );
			$ver = ( defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '1' ) . '.' . (int) @filemtime( $dir . 'js/qcld-wpbot-settings-search.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_enqueue_style( self::HANDLE, QCLD_wpCHATBOT_PLUGIN_URL . 'css/qcld-wpbot-settings-search.css', array(), $ver );
			wp_enqueue_script( self::HANDLE, QCLD_wpCHATBOT_PLUGIN_URL . 'js/qcld-wpbot-settings-search.js', array(), $ver, true );
			wp_localize_script(
				self::HANDLE,
				'qcldWpbotSearch',
				array(
					'index' => self::index(),
					'text'  => array(
						'placeholder' => __( 'Search settings and pages', 'chatbot' ),
						'label'       => __( 'Search WPBot settings and pages', 'chatbot' ),
						'onPage'      => __( 'On this page', 'chatbot' ),
						'none'        => __( 'Nothing found. Try another word, for example "API key", "welcome message" or "colors".', 'chatbot' ),
						'hint'        => __( '↑ ↓ to choose, Enter to open, Esc to close', 'chatbot' ),
						'close'       => __( 'Close', 'chatbot' ),
					),
				)
			);
		}
	}

	QCLD_WPBot_Settings_Search::init();
}
