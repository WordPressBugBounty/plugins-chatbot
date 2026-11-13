<?php
/**
 * "Fill in Business Context" notice on the AI feature screens (AI Marketing,
 * AI Writer, SEO & AI Search, Social Media; filter
 * qcld_wpbot_core/context_notice_pages). WPBot Core prints it itself, so the
 * modules need no change. It shows until the business context is saved once
 * (by anyone), and each user can hide it with "Not now".
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

use QuantumCloud\WPBotCore\Permissions\Sections;
use QuantumCloud\WPBotCore\Rest\Context_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * The notice.
 */
final class Context_Notice {

	const ACTION = 'qcld_wpbot_core_context_notice_hide';
	const META   = 'qcld_wpbot_core_context_notice_hidden';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'hide' ) );
	}

	/**
	 * Page slugs that show the notice.
	 *
	 * @return string[]
	 */
	public static function pages() {
		/**
		 * Admin page slugs where WPBot asks the owner to fill in Business Context.
		 *
		 * @param string[] $pages Page slugs.
		 */
		return array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_core/context_notice_pages', array( 'wpbot-marketing', 'wpbot-ai-writer', 'wpbot-seo-ai-search', 'wpbot-social' ) ) );
	}

	/**
	 * Should the current user see it on this screen?
	 *
	 * @param string $screen_id Screen id.
	 * @return bool
	 */
	public static function due( $screen_id ) {
		if ( Context::saved() || ! current_user_can( Sections::cap( Context_Controller::SECTION, 'view' ) ) || get_user_meta( get_current_user_id(), self::META, true ) ) {
			return false;
		}
		foreach ( self::pages() as $slug ) {
			$suffix = '_page_' . $slug;
			if ( '' !== $slug && substr( (string) $screen_id, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Print the notice.
	 *
	 * @return void
	 */
	public static function render() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::due( $screen->id ) ) {
			return;
		}
		$hide = wp_nonce_url( add_query_arg( 'action', self::ACTION, admin_url( 'admin-post.php' ) ), self::ACTION );
		?>
		<div class="notice notice-info qcwc-context-notice">
			<p>
				<strong><?php /* translators: %s: product name, for example WPBot. */ echo esc_html( sprintf( __( 'AI results are better when %s knows your business.', 'chatbot' ), \QuantumCloud\WPBotCore\Host\Brand::product() ) ); ?></strong>
				<?php esc_html_e( 'Fill in Business Context once (about 5 minutes) and every AI feature uses it: writing, marketing emails, SEO and social posts.', 'chatbot' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( Business_Context_Page::url() ); ?>"><?php esc_html_e( 'Fill in Business Context', 'chatbot' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $hide ); ?>"><?php esc_html_e( 'Not now', 'chatbot' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * "Not now": hide it for this user, then go back.
	 *
	 * @return void
	 */
	public static function hide() {
		check_admin_referer( self::ACTION );
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be logged in.', 'chatbot' ) );
		}
		update_user_meta( get_current_user_id(), self::META, gmdate( 'c' ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
