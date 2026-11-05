<?php
/**
 * The Performance tab: settings, status panel, help panel, Clear and Rebuild.
 *
 * The tab's fields sit inside the host's ChatBot Settings form and are saved
 * when that form is submitted, with their own nonce and the capability the
 * host uses for ChatBot Settings.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Admin;

use QuantumCloud\AssetOptimizer\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens and actions.
 */
class Admin {

	const NONCE_SAVE   = 'qcld_wpbot_ao_save';
	const NONCE_AJAX   = 'qcld_wpbot_ao_ajax';
	const NONCE_NOTICE = 'qcld_wpbot_ao_notice';
	const FIELD        = 'wpbot_asset_cache';

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Module $module The module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		$this->module->host()->register_tab( array( $this, 'render_nav' ), array( $this, 'render_section' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_qcld_wpbot_ao_clear', array( $this, 'ajax_clear' ) );
		add_action( 'wp_ajax_qcld_wpbot_ao_rebuild', array( $this, 'ajax_rebuild' ) );
		add_action( 'admin_post_qcld_wpbot_ao_notice', array( $this, 'handle_notice_action' ) );
		( new Notices( $this->module ) )->register();
	}

	/**
	 * Capability for everything on the tab.
	 *
	 * @return string
	 */
	public function capability() {
		return $this->module->host()->get_capability();
	}

	/**
	 * Print the tab's navigation item (the host places it above Custom CSS).
	 *
	 * @return void
	 */
	public function render_nav() {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}
		$host = $this->module->host();
		$icon = method_exists( $host, 'get_tab_icon' ) ? (string) $host->get_tab_icon() : 'fa fa-tachometer';
		printf(
			'<li tab-data="performance" data-section="#section-flip-performance"><a href="%1$s"> <span class="wpwbot-admin-tab-icon"> <i class="%2$s" aria-hidden="true"></i> </span> <span class="wpwbot-admin-tab-name">%3$s</span> </a></li>',
			esc_url( $host->get_settings_url() ),
			esc_attr( $icon ),
			esc_html__( 'Performance', 'chatbot' )
		);
	}

	/**
	 * Print the tab's content section.
	 *
	 * @return void
	 */
	public function render_section() {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}
		$module   = $this->module;
		$settings = $module->settings()->all();
		$status   = $module->get_status();
		$field    = self::FIELD;
		include QCLD_WPBOT_AO_DIR . '/views/tab.php';
	}

	/**
	 * Load the tab's styles and script on the ChatBot Settings screen only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->module->host()->is_settings_screen() || ! current_user_can( $this->capability() ) ) {
			return;
		}
		$url = plugins_url( 'assets/', QCLD_WPBOT_AO_FILE );
		wp_enqueue_style( 'qcld-wpbot-ao-admin', $url . 'ao-admin.css', array(), QCLD_WPBOT_AO_VERSION );
		wp_enqueue_script( 'qcld-wpbot-ao-admin', $url . 'ao-admin.js', array( 'jquery' ), QCLD_WPBOT_AO_VERSION, true );
		wp_localize_script(
			'qcld-wpbot-ao-admin',
			'qcldWpbotAo',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
				'i18n'    => array(
					'working' => __( 'Working…', 'chatbot' ),
					'failed'  => __( 'That did not work. Reload the page and try again.', 'chatbot' ),
				),
			)
		);
	}

	/**
	 * Save the tab's settings when the ChatBot Settings form is submitted.
	 *
	 * @return void
	 */
	public function maybe_save() {
		if ( ! isset( $_POST[ self::FIELD . '_nonce' ] ) || ! $this->module->host()->is_settings_screen() ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::FIELD . '_nonce' ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_SAVE ) || ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the Performance settings.', 'chatbot' ), 403 );
		}

		// Sanitized key by key in Settings::sanitize().
		$input = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? map_deep( wp_unslash( $_POST[ self::FIELD ] ), 'sanitize_textarea_field' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- map_deep() sanitizes every value.

		$before = $this->module->settings()->all();
		$after  = $this->module->settings()->save( $input );
		if ( $before['enabled'] !== $after['enabled'] || $this->module->settings()->output_signature() !== $this->signature( $before ) ) {
			$this->module->clear();
		}
		if ( $after['enabled'] ) {
			$this->module->state()->set( 'invite', false );
		}
	}

	/**
	 * Output signature of an older settings array.
	 *
	 * @param array<string,mixed> $values Settings.
	 * @return string
	 */
	private function signature( array $values ) {
		return (string) ( (int) ! empty( $values['minify_css'] ) ) . ( (int) ! empty( $values['minify_js'] ) );
	}

	/**
	 * Check the AJAX nonce and capability, or stop.
	 *
	 * @return void
	 */
	private function check_ajax() {
		if ( ! check_ajax_referer( self::NONCE_AJAX, 'nonce', false ) || ! current_user_can( $this->capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'chatbot' ) ), 403 );
		}
	}

	/**
	 * AJAX: Clear WPBot asset cache.
	 *
	 * @return void
	 */
	public function ajax_clear() {
		$this->check_ajax();
		$deleted = $this->module->clear();
		$known   = \QuantumCloud\AssetOptimizer\Compat::known();
		$purged  = array();
		if ( $this->module->settings()->get( 'purge_page_cache' ) && $this->module->is_active() ) {
			foreach ( \QuantumCloud\AssetOptimizer\Compat::purgeable() as $slug ) {
				$purged[] = isset( $known[ $slug ] ) ? $known[ $slug ]['name'] : $slug;
			}
		}
		wp_send_json_success(
			array(
				/* translators: %d: number of files deleted. */
				'message' => sprintf( _n( 'Cleared %d cached file.', 'Cleared %d cached files.', $deleted, 'chatbot' ), $deleted ),
				/* translators: %s: comma-separated names of caching plugins. */
				'notice'  => array() !== $purged ? sprintf( __( 'The page cache of %s was purged too.', 'chatbot' ), implode( ', ', $purged ) ) : Notices::purge_reminder_text(),
			)
		);
	}

	/**
	 * AJAX: Rebuild now.
	 *
	 * @return void
	 */
	public function ajax_rebuild() {
		$this->check_ajax();
		$result = $this->module->rebuild();
		wp_send_json_success(
			array(
				'message' => 0 === $result['built'] && 0 === $result['failed']
					? __( 'Nothing to rebuild yet. Cached files are built after visitors load pages with the chatbot.', 'chatbot' )
					/* translators: 1: files built, 2: files that kept their original. */
					: sprintf( __( 'Rebuilt %1$d files; %2$d kept their original.', 'chatbot' ), $result['built'], $result['failed'] ),
			)
		);
	}

	/**
	 * Notice buttons (admin-post.php): turn the cache on, or dismiss a notice.
	 *
	 * @return void
	 */
	public function handle_notice_action() {
		check_admin_referer( self::NONCE_NOTICE );
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'chatbot' ), 403 );
		}
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		switch ( $do ) {
			case 'enable':
				$this->module->settings()->update( array( 'enabled' => true ) );
				$this->module->state()->set( 'invite', false );
				break;
			case 'dismiss-invite':
				$this->module->state()->set( 'invite', false );
				break;
			case 'dismiss-unwritable':
				update_user_meta( get_current_user_id(), Notices::META_UNWRITABLE, $this->module->storage()->path() );
				break;
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : $this->module->host()->get_settings_url() );
		exit;
	}
}
