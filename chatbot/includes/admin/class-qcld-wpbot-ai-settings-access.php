<?php
/**
 * AI / Settings on WPBot > Permissions.
 *
 * Adds an "AI / Settings" section to WPBot Core's Permissions grid, so the site admin can let other
 * roles (for example Editors) open AI / Settings ("View") and change it ("Manage settings and keys").
 *
 * The AI Settings screen belongs to the synced chatbot module (includes/chatbot), which allows only
 * manage_options. Until the module reads a capability of its own, this host class lets a granted user
 * through those checks for the screen's own requests only: while the screen is drawn, and in its save,
 * "Test" and "Refresh models" requests (each after its nonce is checked). Everything else on the site
 * still sees the user's real capabilities.
 *
 * What stays with administrators:
 * - API keys and provider addresses (a provider address receives the key), removed from the post
 *   before the module saves it;
 * - the Knowledge Base, What the chatbot knows, Answer cache, Error log and Chatbot data sections
 *   (their own handlers need manage_options), and the AI Wizard (it saves keys).
 *
 * Without WPBot Core's Permissions grid, AI / Settings stays with its Menu Access capability.
 *
 * @package Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QCLD_WPBot_AI_Settings_Access' ) ) {

	/**
	 * AI / Settings access for roles chosen on the Permissions screen.
	 */
	final class QCLD_WPBot_AI_Settings_Access {

		/**
		 * Permissions grid section id (capabilities wpbot_ai_settings_view, wpbot_ai_settings_settings).
		 */
		const SECTION = 'ai_settings';

		/**
		 * The AI Settings page slug.
		 */
		const PAGE = 'wpbot_openAi';

		/**
		 * The module's save (admin-post), provider test and model refresh (AJAX) actions.
		 */
		const SAVE_ACTION    = 'qcld_wpbot_chat_ai_settings';
		const TEST_ACTION    = 'qcld_wpbot_chat_ai_test';
		const REFRESH_ACTION = 'qcld_wpbot_chat_ai_models';

		/**
		 * AI Settings sections a non-administrator may open.
		 */
		const OPEN_SECTIONS = array( 'providers', 'answers', 'limits', 'usage' );

		/**
		 * Sections a non-administrator may save.
		 */
		const SAVE_SECTIONS = array( 'providers', 'answers', 'limits' );

		/**
		 * Provider fields only an administrator may change (keys, and the address that receives them).
		 */
		const ADMIN_FIELDS = array( 'key', 'remove', 'url', 'management_key', 'voyage_key' );

		/**
		 * The user let through the module's manage_options checks for this request (0 = nobody).
		 *
		 * @var int
		 */
		private static $user_id = 0;

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'qcld_wpbot_core/permission_sections', array( __CLASS__, 'add_section' ) );
			add_filter( 'qcld_wpbot_core/adopted_sections', array( __CLASS__, 'adopt_section' ) );
			add_filter( 'qcld_wpbot_menu_capability_definitions', array( __CLASS__, 'menu_access_definitions' ) );
			add_filter( 'user_has_cap', array( __CLASS__, 'filter_caps' ), 20, 4 );
			add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'before_save' ), 1 );
			add_action( 'wp_ajax_' . self::TEST_ACTION, array( __CLASS__, 'before_ajax' ), 1 );
			add_action( 'wp_ajax_' . self::REFRESH_ACTION, array( __CLASS__, 'before_ajax' ), 1 );
			add_action( 'qcld_wpbot_chat/ai_settings_header', array( __CLASS__, 'limited_notice' ), 20 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		}

		/**
		 * Whether WPBot Core's Permissions grid is available.
		 *
		 * @return bool
		 */
		public static function grid_available() {
			return class_exists( '\QuantumCloud\WPBotCore\Permissions\Sections' );
		}

		/**
		 * Capability for a level of the AI / Settings section.
		 *
		 * @param string $level view or settings.
		 * @return string
		 */
		public static function cap( $level ) {
			if ( ! self::grid_available() ) {
				return 'manage_options';
			}
			return \QuantumCloud\WPBotCore\Permissions\Sections::cap( self::SECTION, $level );
		}

		/**
		 * Capability of the AI / Settings menu item.
		 *
		 * @return string
		 */
		public static function menu_capability() {
			if ( self::grid_available() ) {
				return self::cap( 'view' );
			}
			return function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'wpbot_openAi' ) : 'manage_options';
		}

		/**
		 * Add the section to the Permissions screen.
		 *
		 * @param array $sections Sections.
		 * @return array
		 */
		public static function add_section( $sections ) {
			$sections[ self::SECTION ] = array(
				'label'       => __( 'AI / Settings', 'chatbot' ),
				'description' => __( 'Which AI answers the chatbot, how it answers, spending and limits. API keys and provider addresses stay with administrators.', 'chatbot' ),
			);
			return $sections;
		}

		/**
		 * The old Menu Access screen no longer sets who opens AI / Settings when Permissions does.
		 *
		 * @param array $definitions Menu Access rows.
		 * @return array
		 */
		public static function menu_access_definitions( $definitions ) {
			if ( self::grid_available() ) {
				unset( $definitions['wpbot_openAi'] );
			}
			return $definitions;
		}

		/**
		 * Switch the section to the grid.
		 *
		 * @param string[] $ids Section ids.
		 * @return string[]
		 */
		public static function adopt_section( $ids ) {
			$ids   = (array) $ids;
			$ids[] = self::SECTION;
			return $ids;
		}

		/**
		 * Whether the current user opens AI / Settings through the Permissions grid (not as an administrator).
		 *
		 * @return bool
		 */
		public static function is_limited_user() {
			return self::grid_available() && ! current_user_can( 'manage_options' ) && current_user_can( self::cap( 'view' ) );
		}

		/**
		 * Whether the current limited user may save AI / Settings.
		 *
		 * @return bool
		 */
		public static function can_save() {
			return current_user_can( self::cap( 'settings' ) );
		}

		/**
		 * The requested AI Settings section ('' = the first one, Providers).
		 *
		 * @return string
		 */
		public static function requested_section() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A tab.
			return isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		}

		/**
		 * On loading AI Settings: send a limited user away from the administrators' sections.
		 *
		 * @return void
		 */
		public static function load_page() {
			if ( ! self::is_limited_user() ) {
				return;
			}
			$section = self::requested_section();
			$section = 'spending' === $section ? 'limits' : $section;
			if ( '' !== $section && ! in_array( $section, self::OPEN_SECTIONS, true ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'section' => 'providers' ), admin_url( 'admin.php' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				exit;
			}
		}

		/**
		 * Draw the AI Settings screen; a limited user passes the module's manage_options check
		 * only while it is drawn.
		 *
		 * @param callable $render Draws the screen.
		 * @return void
		 */
		public static function render( $render ) {
			if ( ! self::is_limited_user() ) {
				call_user_func( $render );
				return;
			}
			self::$user_id = get_current_user_id();
			try {
				call_user_func( $render );
			} finally {
				self::$user_id = 0;
			}
		}

		/**
		 * Let the chosen user through manage_options checks.
		 *
		 * @param array    $allcaps All capabilities of the user.
		 * @param string[] $caps    Required primitive capabilities.
		 * @param array    $args    Requested capability and arguments.
		 * @param \WP_User $user    User.
		 * @return array
		 */
		public static function filter_caps( $allcaps, $caps, $args, $user ) {
			if ( 0 !== self::$user_id && $user instanceof \WP_User && (int) $user->ID === self::$user_id && in_array( 'manage_options', (array) $caps, true ) ) {
				$allcaps['manage_options'] = true;
			}
			return $allcaps;
		}

		/**
		 * Before the module saves AI Settings: check a limited user's nonce and level, keep the
		 * administrators' fields out, then let the save through.
		 *
		 * @return void
		 */
		public static function before_save() {
			if ( ! self::is_limited_user() ) {
				return;
			}
			if ( ! self::can_save() ) {
				wp_die( esc_html__( 'You can view AI / Settings but not change it.', 'chatbot' ), 403 );
			}
			check_admin_referer( self::SAVE_ACTION );
			$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : 'providers';
			if ( ! in_array( $section, self::SAVE_SECTIONS, true ) ) {
				wp_die( esc_html__( 'Only an administrator can change this part of AI / Settings.', 'chatbot' ), 403 );
			}
			// Only removing fields: the post stays slashed for the module, which unslashes and sanitizes what is left.
			if ( isset( $_POST['providers'] ) && is_array( $_POST['providers'] ) ) {
				$_POST['providers'] = self::strip_admin_fields( $_POST['providers'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			}
			// The module's save redirects and exits; nothing after it runs in this request.
			self::$user_id = get_current_user_id();
		}

		/**
		 * Remove the fields only administrators may change from the posted providers.
		 *
		 * @param array $providers Posted providers (id => fields).
		 * @return array
		 */
		public static function strip_admin_fields( array $providers ) {
			foreach ( $providers as $id => $row ) {
				if ( ! is_array( $row ) ) {
					unset( $providers[ $id ] );
					continue;
				}
				foreach ( self::ADMIN_FIELDS as $field ) {
					unset( $providers[ $id ][ $field ] );
				}
			}
			return $providers;
		}

		/**
		 * Before the module's "Test" and "Refresh models": check a limited user's nonce and level,
		 * then let the request through. Both use the saved key and address, which a limited user
		 * cannot change.
		 *
		 * @return void
		 */
		public static function before_ajax() {
			if ( ! self::is_limited_user() || ! self::can_save() ) {
				return;
			}
			$action = current_action() === 'wp_ajax_' . self::TEST_ACTION ? self::TEST_ACTION : self::REFRESH_ACTION;
			if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
				return;
			}
			// The module answers with wp_send_json_*(), which ends the request.
			self::$user_id = get_current_user_id();
		}

		/**
		 * A line under the header for a limited user.
		 *
		 * @return void
		 */
		public static function limited_notice() {
			if ( 0 === self::$user_id ) {
				return;
			}
			$text = self::can_save()
				? __( 'An administrator lets you change AI / Settings. API keys and provider addresses can only be changed by an administrator.', 'chatbot' )
				: __( 'An administrator lets you view AI / Settings. Ask an administrator to change it.', 'chatbot' );
			echo '<div class="notice notice-info"><p>' . esc_html( $text ) . '</p></div>';
		}

		/**
		 * On AI Settings, for a limited user: hide the administrators' sections and lock the fields they cannot save.
		 *
		 * @return void
		 */
		public static function enqueue() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( strtolower( self::PAGE ) !== $page || ! self::is_limited_user() ) {
				return;
			}
			$hidden = array();
			foreach ( array( 'knowledge', 'known', 'cache', 'errors', 'data' ) as $section ) {
				$hidden[] = '.qcld-wpbot-ai-settings .qcwp-side a[href$="section=' . $section . '"]';
				// WPBot Core's admin design: the sections are tabs (and phone menu options).
				$hidden[] = '.qcld-wpbot-ai-settings .wpb-subnav a[href$="section=' . $section . '"]';
			}
			// The AI Wizard saves keys; the spending details and one-click raise use administrator-only REST routes.
			$hidden[] = '.qcld-wpbot-ai-settings #wpbot-trigger-wizard';
			$hidden[] = '.qcld-wpbot-ai-settings [data-spend-group]';
			$hidden[] = '.qcld-wpbot-ai-settings [data-spend-raise]';
			$css      = implode( ',', $hidden ) . '{display:none!important}';
			if ( ! self::can_save() ) {
				$css .= '.qcld-wpbot-ai-settings .qcwp-actions,.qcld-wpbot-ai-settings .qcwp-refresh-models{display:none!important}';
			}
			wp_register_style( 'qcld-wpbot-ai-settings-access', false, array(), QCLD_wpCHATBOT_VERSION );
			wp_enqueue_style( 'qcld-wpbot-ai-settings-access' );
			wp_add_inline_style( 'qcld-wpbot-ai-settings-access', $css );

			$fields = wp_json_encode( self::ADMIN_FIELDS );
			$locked = wp_json_encode( __( 'Only an administrator can change this.', 'chatbot' ) );
			$all    = self::can_save() ? 'false' : 'true';
			$script = '( function () { function lock() { var fields = ' . $fields . ', all = ' . $all . ', forms = document.querySelectorAll( ".qcld-wpbot-ai-settings form.qcwp-form" ); forms.forEach( function ( form ) { form.querySelectorAll( "input, select, textarea, button" ).forEach( function ( el ) { var m = /^providers\[[^\]]+\]\[([^\]]+)\]$/.exec( el.name || "" ); if ( all ? "hidden" !== el.type : ( m && -1 !== fields.indexOf( m[1] ) ) ) { el.disabled = true; el.title = ' . $locked . '; } } ); } ); } if ( "loading" === document.readyState ) { document.addEventListener( "DOMContentLoaded", lock ); } else { lock(); } } )();';
			wp_register_script( 'qcld-wpbot-ai-settings-access', false, array(), QCLD_wpCHATBOT_VERSION, true );
			wp_enqueue_script( 'qcld-wpbot-ai-settings-access' );
			wp_add_inline_script( 'qcld-wpbot-ai-settings-access', $script );
		}
	}

	QCLD_WPBot_AI_Settings_Access::init();
}
