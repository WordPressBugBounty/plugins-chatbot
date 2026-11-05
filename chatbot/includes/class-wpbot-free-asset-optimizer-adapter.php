<?php
/**
 * WPBot Free adapter for WPBot Asset Cache: the chatbot's front-end handles,
 * the Settings capability, the Performance tab above Custom CSS, the pages
 * where the chatbot is part of the content, and the per-visitor values of
 * cached pages.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset cache host adapter for WPBot Free.
 */
class QCLD_WPBot_Free_Asset_Optimizer_Adapter extends \QuantumCloud\AssetOptimizer\Host\Abstract_Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'wpbot-free';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_package() {
		return 'free';
	}

	/**
	 * WPBot Free's ChatBot settings page needs manage_options.
	 *
	 * @return string
	 */
	public function get_capability() {
		return 'manage_options';
	}

	/**
	 * WPBot Free's settings tabs use dashicons.
	 *
	 * @return string
	 */
	public function get_tab_icon() {
		return 'dashicons dashicons-performance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_root_dir() {
		return dirname( QCLD_WPBOT_FREE_ASSET_CACHE_FILE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_root_url() {
		return plugin_dir_url( QCLD_WPBOT_FREE_ASSET_CACHE_FILE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_file() {
		return QCLD_WPBOT_FREE_ASSET_CACHE_FILE;
	}

	/**
	 * The chatbot's own front-end files (qcld_wb_chatbot_frontend_scripts() in
	 * qcld-wpwbot.php, and the template style enqueued by the footer).
	 * Remote files (Google Fonts) are never processed; they only get display=swap.
	 *
	 * @return array{script: string[], style: string[]}
	 */
	public function get_handles() {
		return array(
			'script' => array(
				'qcld-wp-chatbot-slimscroll-js',
				'qcld-wp-chatbot-jquery-cookie',
				'qcld-wp-chatbot-magnify-popup',
				'qcld-wp-chatbot-plugin',
				'qcld-wp-chatbot-front-js',
			),
			'style'  => array(
				'qcld-wp-chatbot-common-style',
				'qcld-wp-chatbot-magnific-popup',
				'qcld-wp-chatbot-style',
				'qcld-wp-chatbot-shortcode-style',
				'qcld-chatbot-user-google-fonts',
				'qcld-chatbot-bot-google-fonts',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_existing_install() {
		return defined( 'QCLD_WPBOT_FREE_ASSET_CACHE_EXISTING' ) && QCLD_WPBOT_FREE_ASSET_CACHE_EXISTING;
	}

	/**
	 * Shortcodes that put the chatbot, or WPBot-styled markup, into the page
	 * content. Pages with them keep normal style loading so nothing jumps.
	 *
	 * @return string[]
	 */
	private function content_shortcodes() {
		return array( 'wpwbot', 'wpwbot_products', 'wpwbot_cart' );
	}

	/**
	 * Whether the chatbot is part of this page's content: the shortcodes
	 * above, or the mobile app pages.
	 *
	 * @return bool
	 */
	public function is_embedded_page() {
		if ( is_page( array( 'wpwbot-mobile-app', 'wpwbot-app-checkout', 'wpwbot-app-order-thankyou' ) ) ) {
			return true;
		}
		$post = get_post();
		if ( ! $post || ! is_singular() ) {
			return false;
		}
		foreach ( $this->content_shortcodes() as $shortcode ) {
			if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The floating chatbot's outer element (printed in the footer).
	 *
	 * @return string[]
	 */
	public function get_chatbot_selectors() {
		return array( '#wp-chatbot-chat-container' );
	}

	/**
	 * Fresh, per-visitor values for cached pages, by JavaScript object: the
	 * security tokens WPBot Free prints (qcld_wb_chatbot_frontend_scripts()) and
	 * the visitor's own details. Only what this visitor's own page would have
	 * received.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fresh_data() {
		return array(
			'wp_chatbot_obj'   => array_merge(
				array(
					'ajax_nonce'    => wp_create_nonce( 'qcsecretbotnonceval123qc' ),
					'order_nonce'   => wp_create_nonce( 'wpwbot-order-nonce' ),
					'session_nonce' => wp_create_nonce( 'wp_chatbot' ),
				),
				$this->visitor_data( wp_get_current_user() )
			),
			'qcld_chatbot_obj' => array(
				'nonce' => wp_create_nonce( 'wp_chatbot' ),
			),
		);
	}

	/**
	 * What a logged-out visitor's page holds for the visitor fields, so a page
	 * printed for a logged-in user never carries their details in the HTML.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_anonymous_visitor_data() {
		return array( 'wp_chatbot_obj' => $this->visitor_data( null ) );
	}

	/**
	 * Visitor fields of wp_chatbot_obj for a user (null = logged out), as
	 * WPBot Free computes them.
	 *
	 * @param WP_User|null $user User.
	 * @return array<string,mixed>
	 */
	private function visitor_data( $user ) {
		$logged_in = $user instanceof WP_User && $user->exists();
		return array(
			'order_login'     => $logged_in,
			'current_user_id' => $logged_in ? (int) $user->ID : 0,
			'display_name'    => $logged_in ? esc_html( $user->display_name ) : '',
		);
	}

	/**
	 * The live answers for qcld_wpbot_free_rules_table(). WPBot Free's show/hide
	 * rules depend on the visitor only through "hide on mobile"; the other two
	 * are reported for completeness.
	 *
	 * @return array{open: bool, mobile: bool, logged_in: bool}
	 */
	public function get_live_values() {
		return array(
			'open'      => true,
			'mobile'    => function_exists( 'wp_chatbot_is_mobile' ) ? (bool) wp_chatbot_is_mobile() : wp_is_mobile(),
			'logged_in' => is_user_logged_in(),
		);
	}

	/**
	 * Names that mark WPBot's inline scripts (for optimisers' exclusion lists).
	 *
	 * @return string[]
	 */
	public function get_inline_markers() {
		return array( 'wp_chatbot_obj', 'qcld_chatbot_obj', 'wpbotAo' );
	}

	/**
	 * Turn on the live show/hide rules (functions.php, wp_chatbot_load_footer_html()
	 * and qcld_wpchatbot_init_fnc() in qcld-wpwbot.php) and hand their table to
	 * the module.
	 *
	 * @param callable $enabled Returns whether live rules apply to this page view.
	 * @param callable $table   Receives the rules table.
	 * @return void
	 */
	public function register_live_rules( callable $enabled, callable $table ) {
		add_filter( 'qcld_wpbot_footer_live_rules', $enabled );
		add_action( 'qcld_wpbot_footer_rules', $table );
	}

	/**
	 * The page slug of the current admin request.
	 *
	 * @return string
	 */
	private function page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		return is_admin() && isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_settings_screen() {
		// WPBot - ChatBot > ChatBot (wpbot-panel); "wpbot" is the older address of the same screen.
		return in_array( $this->page(), array( 'wpbot-panel', 'wpbot' ), true );
	}

	/**
	 * WPBot Free's own admin screens.
	 *
	 * @return bool
	 */
	public function is_host_screen() {
		$page = $this->page();
		return '' !== $page && ( 0 === strpos( $page, 'wpbot' ) || in_array( $page, array( 'simple-text-response', 'wbcs-botsessions-page', 'email-subscription' ), true ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_url() {
		return admin_url( 'admin.php?page=wpbot-panel&tab=performance' );
	}

	/**
	 * The Settings template prints these actions just before its Custom CSS
	 * tab and section (admin_ui.php).
	 *
	 * @param callable $render_nav     Prints the navigation item.
	 * @param callable $render_section Prints the section.
	 * @return void
	 */
	public function register_tab( callable $render_nav, callable $render_section ) {
		add_action( 'qcld_wpbot_settings_tabs_before_custom_css', $render_nav );
		add_action( 'qcld_wpbot_settings_sections_before_custom_css', $render_section );
	}
}
