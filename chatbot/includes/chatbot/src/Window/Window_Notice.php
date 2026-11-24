<?php
/**
 * One notice after the upgrade to the new chat window (owner, 20 November 2026, plan W7): on sites
 * that had the old window, administrators are told once that the window has a new look, which
 * design their old theme became, and where the Chat window designer is.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

use QuantumCloud\Chatbot\Admin\Window_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Upgrade notice.
 */
final class Window_Notice {

	const DISMISS = 'qcld_wpbot_window_notice_dismiss';
	const META    = 'qcld_wpbot_window_notice_dismissed';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Whether this user should see it: the site upgraded from the old window, the user may change
	 * the design and has not closed it.
	 *
	 * @return bool
	 */
	public static function due() {
		if ( '' === (string) get_option( Window_Migration::FLAG, '' ) || ! current_user_can( Window_Settings::capability() ) ) {
			return false;
		}
		return ! get_user_meta( get_current_user_id(), self::META, true );
	}

	/**
	 * Print the notice on WPBot screens.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::due() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is.
		if ( false === strpos( $page, 'wpbot' ) ) {
			return;
		}
		$design  = Window_Designs::get( Window_Theme::get()['design'] );
		$name    = $design && ! empty( $design['name'] ) ? $design['name'] : '';
		$open    = admin_url( 'admin.php?page=wpbot-panel&tab=design' );
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS );
		echo '<div class="notice notice-info qcld-wpbot-window-notice"><p><strong>' . esc_html__( 'Your chat window has a new look', 'chatbot' ) . '</strong> ';
		if ( '' !== $name ) {
			/* translators: %s: design name, for example "Classic". */
			echo esc_html( sprintf( __( 'Your old theme and colours now use the %s design.', 'chatbot' ), $name ) ) . ' ';
		}
		echo esc_html__( 'Pick another design, change colours, layout and motion, and see it live in the Chat window designer.', 'chatbot' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $open ) . '">' . esc_html__( 'Open the Chat window designer', 'chatbot' ) . '</a> <a class="button" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'chatbot' ) . '</a></p></div>';
	}

	/**
	 * Close it for this user.
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_admin_referer( self::DISMISS );
		if ( current_user_can( Window_Settings::capability() ) ) {
			update_user_meta( get_current_user_id(), self::META, gmdate( 'c' ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
