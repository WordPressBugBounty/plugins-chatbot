<?php
/**
 * Admin theme: preference, page detection, switches, toolbar toggle, entire-dashboard opt-in.
 *
 * @package QuantumCloud\AdminTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-user look for the WordPress admin.
 *
 * Each user chooses for themself (user meta, like the admin color scheme):
 * - the look: "light", "dark", "auto" (follows the device) or "classic" (WordPress as it is);
 * - where: the pages of the plugins that bundle the module ("plugin"), or the entire dashboard ("all").
 *
 * "Entire dashboard" restyles pages the plugin does not own, so it is a double opt-in:
 * 1. the user picks it and confirms a dialog (the server refuses the switch without that confirmation);
 * 2. it then runs as a trial: a bar on every admin page offers "Keep it" or "Switch back" until the user keeps it.
 * Right after the switch a toast offers Undo. Switching back restores exactly the preference the user had before.
 */
final class Qcld_Admin_Theme {

	/** Text domain: WPBot Free's own (retargeted from qcld-admin-theme, see README), so WordPress.org translations cover it. */
	const TEXT_DOMAIN = 'chatbot';

	/** User meta: light, dark, auto or classic. */
	const META_MODE = 'qcld_admin_theme';

	/** User meta: plugin or all. */
	const META_SCOPE = 'qcld_admin_theme_scope';

	/** User meta: state of the entire-dashboard opt-in: "trial" or "kept". */
	const META_ALL = 'qcld_admin_theme_all';

	/** User meta: the preference before "Entire dashboard" was turned on (for Switch back / Undo). */
	const META_PREV = 'qcld_admin_theme_prev';

	/** User meta: the last look other than Classic (light, dark or auto), used when the theme is turned back on. */
	const META_LAST = 'qcld_admin_theme_last';

	/** AJAX and admin-post action. */
	const ACTION = 'qcld_admin_theme_save';

	/** Nonce action. */
	const NONCE = 'qcld_admin_theme';

	/** Looks. */
	const MODES = array( 'light', 'dark', 'auto', 'classic' );

	/**
	 * The booted instance.
	 *
	 * @var Qcld_Admin_Theme|null
	 */
	private static $instance = null;

	/**
	 * Version of the copy that booted.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Main file of the copy that booted (for asset URLs).
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Whether the current admin page belongs to one of the plugins (cached).
	 *
	 * @var bool|null
	 */
	private $plugin_screen = null;

	/**
	 * Set when a profile save asked for "Entire dashboard" without confirming it.
	 *
	 * @var bool
	 */
	private $profile_needs_confirm = false;

	/**
	 * Boot (once).
	 *
	 * @param string $file    Main file of the copy that boots.
	 * @param string $version Its version.
	 * @return Qcld_Admin_Theme
	 */
	public static function instance( $file = '', $version = '' ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $file, $version );
		}
		return self::$instance;
	}

	/**
	 * Hook in.
	 *
	 * @param string $file    Main file.
	 * @param string $version Version.
	 */
	private function __construct( $file, $version ) {
		$this->file    = $file;
		$this->version = $version;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax_save' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'admin_post_save' ) );

		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_head', array( $this, 'print_head_script' ), 0 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 95 );
		add_action( 'admin_notices', array( $this, 'trial_notice' ), 1 );
		add_action( 'personal_options', array( $this, 'profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'profile_save' ) );
		add_action( 'user_profile_update_errors', array( $this, 'profile_errors' ) );
		add_action( 'qcld_admin_theme_switch', array( __CLASS__, 'render_switch' ) );
	}

	/**
	 * Translations shipped with the module (languages/qcld-admin-theme-{locale}.mo).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		$mo = dirname( $this->file ) . '/languages/' . self::TEXT_DOMAIN . '-' . determine_locale() . '.mo';
		if ( is_readable( $mo ) ) {
			load_textdomain( self::TEXT_DOMAIN, $mo );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Preference
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Look for users who never chose one.
	 *
	 * @return string
	 */
	private static function default_mode() {
		/**
		 * Look for users who never chose one: light, dark, auto or classic.
		 *
		 * @param string $mode Default "classic".
		 */
		$mode = (string) apply_filters( 'qcld_admin_theme_default_mode', 'classic' );
		return in_array( $mode, self::MODES, true ) ? $mode : 'classic';
	}

	/**
	 * Clean a mode.
	 *
	 * @param mixed $mode Value.
	 * @return string
	 */
	private static function clean_mode( $mode ) {
		return in_array( $mode, self::MODES, true ) ? $mode : self::default_mode();
	}

	/**
	 * The user's preference.
	 *
	 * @param int $user_id User ID (default: current user).
	 * @return array{mode: string, scope: string, all: string}
	 */
	public static function get_preference( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array(
				'mode'  => self::default_mode(),
				'scope' => 'plugin',
				'all'   => '',
			);
		}
		$scope = 'all' === get_user_meta( $user_id, self::META_SCOPE, true ) ? 'all' : 'plugin';
		$all   = (string) get_user_meta( $user_id, self::META_ALL, true );
		if ( 'all' !== $scope ) {
			$all = '';
		} elseif ( 'trial' !== $all ) {
			$all = 'kept';
		}
		return array(
			'mode'  => self::clean_mode( (string) get_user_meta( $user_id, self::META_MODE, true ) ),
			'scope' => $scope,
			'all'   => $all,
		);
	}

	/**
	 * Change the user's look and scope.
	 *
	 * Turning on "Entire dashboard" needs $confirmed (step 1 of the opt-in); it then starts as a trial
	 * and remembers the previous preference for Switch back.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $mode      light, dark, auto or classic.
	 * @param string $scope     plugin or all.
	 * @param bool   $confirmed Whether the user confirmed "Entire dashboard".
	 * @return array|WP_Error The new preference, or confirm_required.
	 */
	public static function change_preference( $user_id, $mode, $scope, $confirmed = false ) {
		$user_id = (int) $user_id;
		$current = self::get_preference( $user_id );
		$mode    = self::clean_mode( $mode );
		$scope   = 'all' === $scope ? 'all' : 'plugin';

		if ( 'all' === $scope && 'all' !== $current['scope'] ) {
			if ( ! $confirmed ) {
				return new WP_Error( 'confirm_required', __( 'Confirm that the theme may restyle every admin page.', 'chatbot' ) );
			}
			update_user_meta(
				$user_id,
				self::META_PREV,
				array(
					'mode'  => $current['mode'],
					'scope' => $current['scope'],
				)
			);
			update_user_meta( $user_id, self::META_ALL, 'trial' );
		} elseif ( 'all' !== $scope ) {
			delete_user_meta( $user_id, self::META_ALL );
			delete_user_meta( $user_id, self::META_PREV );
		}

		update_user_meta( $user_id, self::META_MODE, $mode );
		update_user_meta( $user_id, self::META_SCOPE, $scope );
		if ( 'classic' !== $mode ) {
			update_user_meta( $user_id, self::META_LAST, $mode );
		}
		$new = self::get_preference( $user_id );
		/**
		 * A user changed their admin theme.
		 *
		 * @param int   $user_id User ID.
		 * @param array $new     New preference.
		 * @param array $old     Previous preference.
		 */
		do_action( 'qcld_admin_theme_changed', $user_id, $new, $current );
		return $new;
	}

	/**
	 * The user's last look other than Classic (default: light).
	 *
	 * @param int $user_id User ID (default: current user).
	 * @return string
	 */
	public static function last_look( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$last    = $user_id ? (string) get_user_meta( $user_id, self::META_LAST, true ) : '';
		return in_array( $last, array( 'light', 'dark', 'auto' ), true ) ? $last : 'light';
	}

	/**
	 * Step 2 of the opt-in: keep "Entire dashboard".
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function keep_all( $user_id ) {
		$pref = self::get_preference( $user_id );
		if ( 'all' === $pref['scope'] ) {
			update_user_meta( (int) $user_id, self::META_ALL, 'kept' );
			delete_user_meta( (int) $user_id, self::META_PREV );
		}
		return self::get_preference( $user_id );
	}

	/**
	 * Switch back / Undo: the preference from before "Entire dashboard" (else the theme on the plugins' pages only).
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function revert_all( $user_id ) {
		$user_id = (int) $user_id;
		$current = self::get_preference( $user_id );
		$prev    = get_user_meta( $user_id, self::META_PREV, true );
		$mode    = is_array( $prev ) && isset( $prev['mode'] ) ? self::clean_mode( $prev['mode'] ) : $current['mode'];
		update_user_meta( $user_id, self::META_MODE, $mode );
		update_user_meta( $user_id, self::META_SCOPE, 'plugin' );
		delete_user_meta( $user_id, self::META_ALL );
		delete_user_meta( $user_id, self::META_PREV );
		$new = self::get_preference( $user_id );
		/** This action is documented in change_preference(). */
		do_action( 'qcld_admin_theme_changed', $user_id, $new, $current );
		return $new;
	}

	/**
	 * Who may use the theme (default: anyone who can open the dashboard).
	 *
	 * @return bool
	 */
	private function user_can_use() {
		/**
		 * Capability needed to use the admin theme.
		 *
		 * @param string $capability Default "read".
		 */
		return is_user_logged_in() && current_user_can( (string) apply_filters( 'qcld_admin_theme_capability', 'read' ) );
	}

	/**
	 * Apply a request: op = save (mode, scope, confirm_all), keep or revert.
	 *
	 * @param array $request Request data.
	 * @return array|WP_Error
	 */
	private function handle_request( array $request ) {
		$user_id = get_current_user_id();
		$op      = isset( $request['op'] ) ? sanitize_key( wp_unslash( $request['op'] ) ) : 'save';
		if ( 'keep' === $op ) {
			return self::keep_all( $user_id );
		}
		if ( 'revert' === $op ) {
			return self::revert_all( $user_id );
		}
		$pref  = self::get_preference( $user_id );
		$mode  = isset( $request['mode'] ) ? sanitize_key( wp_unslash( $request['mode'] ) ) : $pref['mode'];
		$scope = isset( $request['scope'] ) ? sanitize_key( wp_unslash( $request['scope'] ) ) : $pref['scope'];
		return self::change_preference( $user_id, in_array( $mode, self::MODES, true ) ? $mode : $pref['mode'], $scope, ! empty( $request['confirm_all'] ) );
	}

	/**
	 * AJAX.
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! $this->user_can_use() ) {
			wp_send_json_error( null, 403 );
		}
		$result = $this->handle_request( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				400
			);
		}
		wp_send_json_success( $result );
	}

	/**
	 * Without JavaScript: toolbar links and notice buttons save and come back. "Entire dashboard"
	 * without confirmation shows a confirmation page first.
	 *
	 * @return void
	 */
	public function admin_post_save() {
		check_admin_referer( self::NONCE );
		if ( ! $this->user_can_use() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'chatbot' ), 403 );
		}
		$back   = wp_get_referer() ? wp_get_referer() : admin_url();
		$result = $this->handle_request( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked above.
		if ( is_wp_error( $result ) && 'confirm_required' === $result->get_error_code() ) {
			$args    = array_map( 'sanitize_key', wp_unslash( array_intersect_key( $_GET, array_flip( array( 'mode', 'scope' ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked above.
			$confirm = $this->save_url( array_merge( $args, array( 'confirm_all' => '1' ) ) );
			$s       = $this->strings();
			wp_die(
				'<h1>' . esc_html( $s['confirmTitle'] ) . '</h1><p>' . esc_html( $s['confirmBody'] ) . '</p><p>' . esc_html( $s['confirmTrial'] ) . '</p>'
				. '<p><a class="button button-primary" href="' . esc_url( $confirm ) . '">' . esc_html( $s['confirmYes'] ) . '</a> <a class="button" href="' . esc_url( $back ) . '">' . esc_html( $s['cancel'] ) . '</a></p>',
				esc_html( $s['confirmTitle'] ),
				array( 'response' => 200 )
			);
		}
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Link that applies a change without JavaScript.
	 *
	 * @param array $args op, mode, scope, confirm_all.
	 * @return string
	 */
	private function save_url( array $args ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => self::ACTION ), $args ), admin_url( 'admin-post.php' ) ), self::NONCE );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Which pages are the plugins'
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The plugins that bundle the module, normalized.
	 *
	 * Each host adds itself with the qcld_admin_theme_plugins filter (see README.md):
	 *   'id' => array(
	 *     'name'       => 'WPBot',            // Shown in "WPBot pages only".
	 *     'dirs'       => array( __DIR__ ),   // Admin pages whose callback is in these folders are the plugin's.
	 *     'menus'      => array( 'my-menu' ), // Every page in these admin menus (top level slug).
	 *     'pages'      => array( 'my-page' ), // More page slugs (?page=).
	 *     'post_types' => array( 'my_cpt' ),  // List, edit and taxonomy screens of these post types.
	 *     'css'        => 'https://…/x.css',  // Extra theme styles for the plugin's own markup (optional).
	 *     'skip'       => array( '#preview' ),// CSS selectors the color mapping leaves alone (optional).
	 *   )
	 *
	 * @return array
	 */
	public static function plugins() {
		/**
		 * The plugins that use the admin theme on their pages.
		 *
		 * @param array $plugins Plugins by id.
		 */
		$plugins = (array) apply_filters( 'qcld_admin_theme_plugins', array() );
		$out     = array();
		foreach ( $plugins as $id => $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}
			$dirs = array();
			foreach ( isset( $plugin['dirs'] ) ? (array) $plugin['dirs'] : array() as $dir ) {
				if ( is_string( $dir ) && '' !== $dir ) {
					$dirs[] = trailingslashit( wp_normalize_path( $dir ) );
				}
			}
			$out[ sanitize_key( $id ) ] = array(
				'name'       => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $id,
				'dirs'       => $dirs,
				'menus'      => array_map( 'strval', isset( $plugin['menus'] ) ? (array) $plugin['menus'] : array() ),
				'pages'      => array_map( 'strval', isset( $plugin['pages'] ) ? (array) $plugin['pages'] : array() ),
				'post_types' => array_map( 'strval', isset( $plugin['post_types'] ) ? (array) $plugin['post_types'] : array() ),
				'css'        => isset( $plugin['css'] ) ? (string) $plugin['css'] : '',
				'skip'       => array_values( array_filter( array_map( array( __CLASS__, 'clean_selector' ), isset( $plugin['skip'] ) ? (array) $plugin['skip'] : array() ) ) ),
			);
		}
		return $out;
	}

	/**
	 * A CSS selector from a host, or '' when it holds characters a selector list must not.
	 *
	 * @param mixed $selector Selector.
	 * @return string
	 */
	private static function clean_selector( $selector ) {
		$selector = is_string( $selector ) ? trim( $selector ) : '';
		return '' !== $selector && ! preg_match( '/[{}<>;]/', $selector ) ? $selector : '';
	}

	/**
	 * Whether the current admin page belongs to one of the plugins.
	 *
	 * @return bool
	 */
	public function is_plugin_screen() {
		if ( null !== $this->plugin_screen ) {
			return $this->plugin_screen;
		}
		global $plugin_page, $page_hook, $typenow, $submenu;

		$is      = false;
		$plugins = self::plugins();
		$page    = is_string( $plugin_page ) ? $plugin_page : '';

		foreach ( $plugins as $plugin ) {
			if ( $typenow && in_array( $typenow, $plugin['post_types'], true ) ) {
				$is = true;
				break;
			}
			if ( '' === $page ) {
				continue;
			}
			if ( in_array( $page, $plugin['pages'], true ) || in_array( $page, $plugin['menus'], true ) ) {
				$is = true;
				break;
			}
			foreach ( $plugin['menus'] as $menu ) {
				if ( ! empty( $submenu[ $menu ] ) && in_array( $page, wp_list_pluck( (array) $submenu[ $menu ], 2 ), true ) ) {
					$is = true;
					break 2;
				}
			}
		}

		// The page's callback lives in one of the plugins' folders (covers add-ons with their own menus).
		if ( ! $is && '' !== $page && $page_hook ) {
			$is = $this->hook_in_plugin_dirs( (string) $page_hook, $plugins );
		}

		/**
		 * Whether the current admin page belongs to a plugin that uses the admin theme.
		 *
		 * @param bool $is Detected value.
		 */
		$this->plugin_screen = (bool) apply_filters( 'qcld_admin_theme_is_plugin_screen', $is );
		return $this->plugin_screen;
	}

	/**
	 * Whether a callback of an admin page hook is defined in one of the plugins' folders.
	 *
	 * @param string $hook    Page hook.
	 * @param array  $plugins Plugins from plugins().
	 * @return bool
	 */
	private function hook_in_plugin_dirs( $hook, array $plugins ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
			return false;
		}
		$dirs = array();
		foreach ( $plugins as $plugin ) {
			$dirs = array_merge( $dirs, $plugin['dirs'] );
		}
		if ( ! $dirs ) {
			return false;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$file = $this->callback_file( $callback['function'] );
				foreach ( $dirs as $dir ) {
					if ( '' !== $file && 0 === strpos( $file, $dir ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * File that defines a callback ('' when unknown).
	 *
	 * @param mixed $callback Callable.
	 * @return string
	 */
	private function callback_file( $callback ) {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$ref = new ReflectionMethod( $callback[0], (string) $callback[1] );
			} elseif ( is_string( $callback ) || $callback instanceof Closure ) {
				$ref = new ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$ref = new ReflectionMethod( $callback, '__invoke' );
			} else {
				return '';
			}
			$file = $ref->getFileName();
			return $file ? wp_normalize_path( $file ) : '';
		} catch ( ReflectionException $e ) {
			return '';
		}
	}

	/**
	 * Screens the theme never styles (they have their own interface).
	 *
	 * @return bool
	 */
	private function is_excluded_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return true;
		}
		return in_array( $screen ? $screen->id : '', array( 'customize', 'site-editor' ), true );
	}

	/**
	 * Whether the theme applies to the current page for the current user.
	 *
	 * @return bool
	 */
	public function applies_here() {
		if ( ! $this->user_can_use() || $this->is_excluded_screen() ) {
			return false;
		}
		$pref = self::get_preference();
		return 'classic' !== $pref['mode'] && ( 'all' === $pref['scope'] || $this->is_plugin_screen() );
	}

	/**
	 * Whether this page shows the controls and loads the assets: every admin page the theme can
	 * style, so the toolbar toggle is always one click away.
	 *
	 * @return bool
	 */
	private function shows_controls() {
		/**
		 * Whether the toolbar toggle and the theme assets load on this admin page.
		 *
		 * @param bool $show Default: every admin page the theme can style.
		 */
		return (bool) apply_filters( 'qcld_admin_theme_show_controls', $this->user_can_use() && ! $this->is_excluded_screen() );
	}

	/**
	 * Names of the plugins, joined ("WPBot").
	 *
	 * @return string
	 */
	private function plugin_names() {
		$names = array_values( array_unique( array_filter( wp_list_pluck( self::plugins(), 'name' ) ) ) );
		return $names ? implode( ', ', $names ) : '';
	}

	/**
	 * User-facing strings (PHP and the script share them).
	 *
	 * @return array<string, string>
	 */
	private function strings() {
		$names = $this->plugin_names();
		return array(
			/* translators: %s: plugin names, e.g. "WPBot". */
			'scopePlugin'  => $names ? sprintf( __( '%s dashboard only', 'chatbot' ), $names ) : __( 'Plugin pages only', 'chatbot' ),
			'scopeAll'     => __( 'Whole admin panel', 'chatbot' ),
			'scopeAllHint' => __( 'Asks you to confirm first, then starts as a trial you can switch back from.', 'chatbot' ),
			'classicOff'   => __( 'Classic (theme off)', 'chatbot' ),
			'toDark'       => __( 'Switch to dark mode', 'chatbot' ),
			'toLight'      => __( 'Switch to light mode', 'chatbot' ),
			'darkMode'     => __( 'Dark mode', 'chatbot' ),
			'confirmTitle' => __( 'Use the theme on the whole admin panel?', 'chatbot' ),
			'confirmBody'  => $names
				/* translators: %s: plugin names, e.g. "WPBot". */
				? sprintf( __( 'The theme will also restyle WordPress’s own pages and other plugins’ pages, not only %s. Most pages look right, but some pages from other plugins may not.', 'chatbot' ), $names )
				: __( 'The theme will also restyle WordPress’s own pages and other plugins’ pages. Most pages look right, but some pages from other plugins may not.', 'chatbot' ),
			'confirmTrial' => __( 'It starts as a trial: a bar at the top of each page lets you keep it or switch back at any time. Only you see this change.', 'chatbot' ),
			'confirmYes'   => __( 'Use on entire dashboard', 'chatbot' ),
			'cancel'       => __( 'Cancel', 'chatbot' ),
			'appliedAll'   => __( 'The theme now applies to the whole admin panel.', 'chatbot' ),
			'undo'         => __( 'Undo', 'chatbot' ),
			'dismiss'      => __( 'Dismiss', 'chatbot' ),
			'trialText'    => __( 'You are trying the theme on the whole admin panel. If a page does not look right, switch back.', 'chatbot' ),
			'keep'         => __( 'Keep it', 'chatbot' ),
			'switchBack'   => __( 'Switch back', 'chatbot' ),
			'kept'         => __( 'Kept. The theme stays on for the whole admin panel.', 'chatbot' ),
			'reverted'     => __( 'Switched back to your previous theme setting.', 'chatbot' ),
			'saveError'    => __( 'Your theme choice could not be saved. Please try again.', 'chatbot' ),
			/* translators: %s: plugin names, e.g. "WPBot". */
			'notHere'      => $names ? sprintf( __( 'Saved for %s pages. This page keeps the classic look.', 'chatbot' ), $names ) : __( 'Saved for plugin pages. This page keeps the classic look.', 'chatbot' ),
			'useAll'       => __( 'Use everywhere', 'chatbot' ),
		);
	}

	/**
	 * Labels of the looks.
	 *
	 * @return array<string, string>
	 */
	private static function mode_labels() {
		return array(
			'light'   => __( 'Light', 'chatbot' ),
			'dark'    => __( 'Dark', 'chatbot' ),
			'auto'    => __( 'Match device', 'chatbot' ),
			'classic' => __( 'Classic WordPress', 'chatbot' ),
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Output
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Styles and script where the controls show, plus each plugin's own theme styles.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->shows_controls() ) {
			return;
		}
		$pref = self::get_preference();
		$deps = array();
		/**
		 * URL of the stylesheet with the heading font ('' to use the system serif).
		 *
		 * @param string $url Default: Google Fonts, Source Serif 4.
		 */
		$font = (string) apply_filters( 'qcld_admin_theme_font_url', 'https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&display=swap' );
		if ( '' !== $font ) {
			wp_enqueue_style( 'qcld-admin-theme-font', $font, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Font service URL.
			$deps[] = 'qcld-admin-theme-font';
		}
		wp_enqueue_style( 'qcld-admin-theme', plugins_url( 'assets/admin-theme.css', $this->file ), $deps, $this->asset_version( 'assets/admin-theme.css' ) );
		foreach ( self::plugins() as $id => $plugin ) {
			if ( '' !== $plugin['css'] ) {
				wp_enqueue_style( 'qcld-admin-theme-' . $id, $plugin['css'], array( 'qcld-admin-theme' ), $this->version );
			}
		}
		wp_enqueue_script( 'qcld-admin-theme', plugins_url( 'assets/admin-theme.js', $this->file ), array(), $this->asset_version( 'assets/admin-theme.js' ), true );
		wp_localize_script(
			'qcld-admin-theme',
			'qcldAdminThemeConfig',
			array(
				'mode'         => $pref['mode'],
				'scope'        => $pref['scope'],
				'all'          => $pref['all'],
				'last'         => self::last_look(),
				'isPluginPage' => $this->is_plugin_screen(),
				'skip'         => $this->skip_selectors(),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => self::ACTION,
				'nonce'        => wp_create_nonce( self::NONCE ),
				'i18n'         => $this->strings(),
			)
		);
	}

	/**
	 * Selectors every host asked the color mapping to leave alone.
	 *
	 * @return string[]
	 */
	private function skip_selectors() {
		$all = array();
		foreach ( self::plugins() as $plugin ) {
			foreach ( $plugin['skip'] as $selector ) {
				$all[ $selector ] = true;
			}
		}
		return array_keys( $all );
	}

	/**
	 * Version for an asset URL: the module version plus the file's change time, so browsers
	 * fetch a changed file after an update even when the module version stays the same.
	 *
	 * @param string $relative Path inside the module folder.
	 * @return string
	 */
	private function asset_version( $relative ) {
		$time = @filemtime( dirname( $this->file ) . '/' . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing file just keeps the module version.
		return $time ? $this->version . '.' . $time : $this->version;
	}

	/**
	 * Mark the page before it paints: data-qa-theme="light|dark" and the qa-theme class.
	 *
	 * @return void
	 */
	public function print_head_script() {
		if ( ! $this->applies_here() ) {
			return;
		}
		$pref = self::get_preference();
		?>
<script>(function(d,m,a){var t=m==='auto'?(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):m;d.setAttribute('data-qa-theme',t);d.className+=' qa-theme qa-preload'+(a?' qa-scope-all':'');})(document.documentElement,<?php echo wp_json_encode( $pref['mode'] ); ?>,<?php echo 'all' === $pref['scope'] ? 'true' : 'false'; ?>);</script>
		<?php
	}

	/**
	 * Body class.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $this->applies_here() ? $classes . ' qcld-admin-theme' : $classes;
	}

	/**
	 * Step 2 of the opt-in: while "Entire dashboard" is a trial, a bar on every admin page.
	 *
	 * @return void
	 */
	public function trial_notice() {
		if ( ! $this->user_can_use() || $this->is_excluded_screen() ) {
			return;
		}
		$pref = self::get_preference();
		if ( 'all' !== $pref['scope'] || 'trial' !== $pref['all'] || 'classic' === $pref['mode'] ) {
			return;
		}
		$s = $this->strings();
		?>
		<div class="notice qa-trial inline" data-qa-skip role="region" aria-label="<?php echo esc_attr( $s['scopeAll'] ); ?>">
			<p class="qa-trial__text"><?php echo esc_html( $s['trialText'] ); ?></p>
			<p class="qa-trial__actions">
				<a class="button button-primary qa-trial__keep" data-qa-op="keep" href="<?php echo esc_url( $this->save_url( array( 'op' => 'keep' ) ) ); ?>"><?php echo esc_html( $s['keep'] ); ?></a>
				<a class="button qa-trial__back" data-qa-op="revert" href="<?php echo esc_url( $this->save_url( array( 'op' => 'revert' ) ) ); ?>"><?php echo esc_html( $s['switchBack'] ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Toolbar: Dark mode toggle (one click) with the full menu on hover.
	 *
	 * @param WP_Admin_Bar $bar Toolbar.
	 * @return void
	 */
	public function admin_bar_menu( $bar ) {
		if ( ! is_admin() || ! $this->shows_controls() ) {
			return;
		}
		$pref = self::get_preference();
		$s    = $this->strings();
		// Without JavaScript the server only knows the stored look ("auto" counts as light here).
		$dark = 'dark' === $pref['mode'];
		$bar->add_node(
			array(
				'id'     => 'qcld-admin-theme',
				'parent' => 'top-secondary',
				'title'  => '<span class="qa-ab-toggle" aria-hidden="true"><span class="qa-ab-toggle__knob">' . self::icon( 'light' ) . self::icon( 'dark' ) . '</span></span><span class="ab-label">' . esc_html( $s['darkMode'] ) . '</span>',
				'href'   => $this->save_url( array( 'mode' => $dark ? 'light' : 'dark' ) ),
				'meta'   => array(
					'class' => 'qa-ab' . ( $dark ? ' qa-is-dark' : '' ),
					'title' => $dark ? $s['toLight'] : $s['toDark'],
				),
			)
		);
		// Quick options: where the theme applies, or Classic (theme off).
		$look  = 'classic' === $pref['mode'] ? self::last_look() : $pref['mode'];
		$quick = array(
			'plugin'  => array(
				'label' => $s['scopePlugin'],
				'args'  => array(
					'mode'  => $look,
					'scope' => 'plugin',
				),
				'on'    => 'classic' !== $pref['mode'] && 'plugin' === $pref['scope'],
			),
			'all'     => array(
				'label' => $s['scopeAll'] . '…',
				'args'  => array(
					'mode'  => $look,
					'scope' => 'all',
				),
				'on'    => 'classic' !== $pref['mode'] && 'all' === $pref['scope'],
			),
			'classic' => array(
				'label' => $s['classicOff'],
				'args'  => array( 'mode' => 'classic' ),
				'on'    => 'classic' === $pref['mode'],
			),
		);
		foreach ( $quick as $id => $item ) {
			$bar->add_node(
				array(
					'id'     => 'qcld-admin-theme-where-' . $id,
					'parent' => 'qcld-admin-theme',
					'title'  => '<span class="qa-ab-check" aria-hidden="true"></span>' . esc_html( $item['label'] ),
					'href'   => $this->save_url( $item['args'] ),
					'meta'   => array(
						'class' => 'qa-ab-where' . ( $item['on'] ? ' qa-selected' : '' ),
						'title' => 'all' === $id ? $s['scopeAllHint'] : '',
					),
				)
			);
		}
		// The look: Light, Dark or Match device.
		$bar->add_group(
			array(
				'id'     => 'qcld-admin-theme-look',
				'parent' => 'qcld-admin-theme',
				'meta'   => array( 'class' => 'ab-sub-secondary' ),
			)
		);
		$labels = self::mode_labels();
		foreach ( array( 'light', 'dark', 'auto' ) as $mode ) {
			$bar->add_node(
				array(
					'id'     => 'qcld-admin-theme-mode-' . $mode,
					'parent' => 'qcld-admin-theme-look',
					'title'  => '<span class="qa-ab-check" aria-hidden="true"></span>' . esc_html( $labels[ $mode ] ),
					'href'   => $this->save_url( array( 'mode' => $mode ) ),
					'meta'   => array( 'class' => 'qa-ab-mode' . ( $mode === $pref['mode'] ? ' qa-selected' : '' ) ),
				)
			);
		}
	}

	/**
	 * The switch hosts print in their page headers: do_action( 'qcld_admin_theme_switch' ).
	 *
	 * @return void
	 */
	public static function render_switch() {
		if ( null === self::$instance || ! self::$instance->user_can_use() ) {
			return;
		}
		$pref    = self::get_preference();
		$labels  = self::mode_labels();
		$strings = self::$instance->strings();
		?>
		<div class="qa-switch" role="group" aria-label="<?php echo esc_attr( $strings['darkMode'] ); ?>">
			<?php foreach ( array( 'light', 'dark', 'auto', 'classic' ) as $mode ) : ?>
				<button type="button" class="qa-switch__btn qa-switch__btn--<?php echo esc_attr( $mode ); ?>" data-qa-mode="<?php echo esc_attr( $mode ); ?>" aria-pressed="<?php echo $mode === $pref['mode'] ? 'true' : 'false'; ?>" title="<?php echo esc_attr( $labels[ $mode ] ); ?>" aria-label="<?php echo esc_attr( $labels[ $mode ] ); ?>">
					<?php echo self::icon( $mode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Profile > Personal Options: Admin theme.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function profile_fields( $user ) {
		if ( ! $user instanceof WP_User || get_current_user_id() !== (int) $user->ID || ! $this->user_can_use() ) {
			return;
		}
		$pref = self::get_preference( $user->ID );
		$s    = $this->strings();
		?>
		<tr class="user-qcld-admin-theme-wrap">
			<th scope="row"><?php esc_html_e( 'Admin theme', 'chatbot' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_html_e( 'Admin theme', 'chatbot' ); ?></span></legend>
					<?php wp_nonce_field( self::NONCE, 'qcld_admin_theme_nonce' ); ?>
					<?php foreach ( self::mode_labels() as $mode => $label ) : ?>
						<label><input type="radio" name="qcld_admin_theme" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $mode, $pref['mode'] ); ?> /> <?php echo esc_html( $label ); ?></label><br />
					<?php endforeach; ?>
					<p><strong><?php esc_html_e( 'Use it on', 'chatbot' ); ?></strong></p>
					<label><input type="radio" name="qcld_admin_theme_scope" value="plugin" <?php checked( 'plugin', $pref['scope'] ); ?> /> <?php echo esc_html( $s['scopePlugin'] ); ?></label><br />
					<label><input type="radio" name="qcld_admin_theme_scope" value="all" data-qa-scope-all <?php checked( 'all', $pref['scope'] ); ?> /> <?php echo esc_html( $s['scopeAll'] ); ?></label>
					<?php if ( 'all' !== $pref['scope'] ) : ?>
						<p class="description qa-profile-confirm">
							<label><input type="checkbox" name="qcld_admin_theme_confirm_all" id="qcld_admin_theme_confirm_all" value="1" /> <?php echo esc_html( $s['confirmBody'] ); ?></label>
						</p>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the profile fields ("Entire dashboard" needs the confirmation box).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function profile_save( $user_id ) {
		if ( empty( $_POST['qcld_admin_theme_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qcld_admin_theme_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( get_current_user_id() !== (int) $user_id || ! $this->user_can_use() ) {
			return;
		}
		$mode   = isset( $_POST['qcld_admin_theme'] ) ? sanitize_key( wp_unslash( $_POST['qcld_admin_theme'] ) ) : 'classic';
		$scope  = isset( $_POST['qcld_admin_theme_scope'] ) ? sanitize_key( wp_unslash( $_POST['qcld_admin_theme_scope'] ) ) : 'plugin';
		$result = self::change_preference( $user_id, $mode, $scope, ! empty( $_POST['qcld_admin_theme_confirm_all'] ) );
		if ( is_wp_error( $result ) ) {
			// Keep the look, stay on the plugins' pages, and tell the user why.
			self::change_preference( $user_id, $mode, 'plugin' );
			$this->profile_needs_confirm = true;
		}
	}

	/**
	 * Profile error when "Entire dashboard" was chosen without the confirmation box.
	 *
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public function profile_errors( $errors ) {
		if ( $this->profile_needs_confirm && $errors instanceof WP_Error ) {
			$errors->add( 'qcld_admin_theme_confirm', __( 'The admin theme stays on your plugin pages: tick the confirmation box to use it on the entire dashboard.', 'chatbot' ) );
		}
	}

	/**
	 * Icons for the looks.
	 *
	 * @param string $mode light, dark or auto.
	 * @return string
	 */
	private static function icon( $mode ) {
		$paths = array(
			// Classic (theme off): the familiar admin screen, a window with its side menu.
			'light'   => 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
			'dark'    => 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
			'auto'    => 'M2 4h20v13H2zM8 21h8M12 17v4',
			'classic' => 'M3 4h18v16H3zM8.5 4v16M11.5 8.5h6M11.5 12h6M11.5 15.5h4',
		);
		$d     = isset( $paths[ $mode ] ) ? $paths[ $mode ] : $paths['auto'];
		return '<svg class="qa-icon qa-icon--' . esc_attr( $mode ) . '" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="' . esc_attr( $d ) . '"/></svg>';
	}
}
