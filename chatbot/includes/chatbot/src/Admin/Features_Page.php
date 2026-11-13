<?php
/**
 * ChatBot → Features (chatbot features CB1d): opt-in features for hosts without Module Manager
 * (WPBot Free). Lists what registered through qcld_wpbot_chat/register_modules (Help Desk first)
 * with an on/off switch each. WPBot Pro keeps its Module Manager; a host turns this page on with
 * the filter qcld_wpbot_chat/features_page. The page stays hidden until a feature registers.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Features page.
 */
final class Features_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'qcld-wpbot-features';

	/**
	 * Nonce action.
	 */
	const NONCE = 'qcld_wpbot_chat_features';

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_post_' . self::NONCE, array( __CLASS__, 'save' ) );
	}

	/**
	 * Whether the host wants the page.
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Show the Features page (hosts without Module Manager).
		 *
		 * @param bool $show Default false.
		 */
		if ( ! apply_filters( 'qcld_wpbot_chat/features_page', false ) ) {
			return false;
		}
		// No menu item or tab with nothing to switch on (owner, 12 November 2026: an empty
		// "No extra features are available yet" page in WPBot Free looked broken).
		return array() !== \QuantumCloud\Chatbot\Module::instance()->modules()->all();
	}

	/**
	 * Add the menu item.
	 *
	 * @return void
	 */
	public static function menu() {
		if ( ! self::enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$parent = Train_Page::parent();
		add_submenu_page( $parent, esc_html__( 'Features', 'chatbot' ), esc_html__( 'Features', 'chatbot' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$registry = qcld_wpbot_chat_modules();
		$features = $registry->all();
		Admin_Pages::header( self::SLUG, __( 'Features', 'chatbot' ) );
		echo '<p class="qcwp-lead">' . esc_html__( 'Turn on the extra features you want. Features you leave off add nothing to your site.', 'chatbot' ) . '</p>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice only.
			echo '<div class="qcwp-notice" role="status">' . esc_html__( 'Saved.', 'chatbot' ) . '</div>';
		}
		if ( array() === $features ) {
			echo '<div class="qcwp-card"><p class="qcwp-empty">' . esc_html__( 'No extra features are available yet.', 'chatbot' ) . '</p></div>';
			Admin_Pages::footer();
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="qcwp-card">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::NONCE ) . '">';
		foreach ( $features as $feature ) {
			$field = 'qcld-feature-' . $feature['id'];
			echo '<label class="qcwp-check" for="' . esc_attr( $field ) . '"><input type="checkbox" id="' . esc_attr( $field ) . '" name="features[]" value="' . esc_attr( $feature['id'] ) . '"' . checked( $registry->is_active( $feature['id'] ), true, false ) . '><span><strong>' . esc_html( $feature['name'] ) . '</strong><small>' . esc_html( $feature['description'] ) . '</small></span></label>';
		}
		echo '<div class="qcwp-actions"><button type="submit" class="qcwp-btn is-primary">' . esc_html__( 'Save features', 'chatbot' ) . '</button></div></form>';
		Admin_Pages::footer();
	}

	/**
	 * Save the switches.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$on       = isset( $_POST['features'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['features'] ) ) : array();
		$registry = qcld_wpbot_chat_modules();
		$enable   = array();
		$disable  = array();
		foreach ( $registry->all() as $feature ) {
			if ( in_array( $feature['id'], $on, true ) ) {
				$enable[] = $feature['path'];
			} else {
				$disable[] = $feature['path'];
			}
		}
		if ( $enable ) {
			$registry->set_active( $enable, true );
		}
		if ( $disable ) {
			$registry->set_active( $disable, false );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
