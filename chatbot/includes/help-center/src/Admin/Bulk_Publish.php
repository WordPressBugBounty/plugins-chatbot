<?php
/**
 * "Publish" in the article list's Bulk actions (owner, 19 November 2026): publish many drafts
 * (and articles pending review) at once, for example the starter drafts of Get started.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk publish.
 */
final class Bulk_Publish {

	/**
	 * Bulk action id.
	 */
	const ACTION = 'wpbot_help_publish';

	/**
	 * Query argument with the count after the redirect.
	 */
	const DONE = 'wpbot_help_published';

	/**
	 * Statuses that can be published here.
	 */
	const FROM = array( 'draft', 'pending' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'bulk_actions-edit-' . Post_Type::NAME, array( self::class, 'actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . Post_Type::NAME, array( self::class, 'handle' ), 10, 3 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	/**
	 * Add "Publish" for users who can publish articles.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function actions( $actions ) {
		$actions = is_array( $actions ) ? $actions : array();
		$type    = get_post_type_object( Post_Type::NAME );
		if ( $type && current_user_can( $type->cap->publish_posts ) ) {
			$actions = array( self::ACTION => __( 'Publish', 'chatbot' ) ) + $actions;
		}
		return $actions;
	}

	/**
	 * Publish the chosen drafts the user may publish.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Post ids.
	 * @return string
	 */
	public static function handle( $redirect, $action, $ids ) {
		if ( self::ACTION !== $action ) {
			return $redirect;
		}
		$done = self::publish( (array) $ids );
		return add_query_arg( self::DONE, $done, remove_query_arg( array( self::DONE, 'post_status' ), $redirect ) );
	}

	/**
	 * Publish articles that are drafts or pending, one by one, with the user's rights.
	 *
	 * @param int[] $ids Post ids.
	 * @return int How many were published.
	 */
	public static function publish( array $ids ) {
		$done = 0;
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || Post_Type::NAME !== $post->post_type || ! in_array( $post->post_status, self::FROM, true ) || ! current_user_can( 'publish_post', $id ) ) {
				continue;
			}
			// A draft with no date set gets today's date (wp_insert_post), like the Publish button.
			$result = wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				),
				true
			);
			if ( ! is_wp_error( $result ) && $result ) {
				++$done;
			}
		}
		return $done;
	}

	/**
	 * "N articles published."
	 *
	 * @return void
	 */
	public static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A message after the bulk action.
		if ( ! isset( $_GET[ self::DONE ] ) || ! Menu::is_help_center_screen() ) {
			return;
		}
		$count = absint( $_GET[ self::DONE ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		/* translators: %d: number of articles. */
		$text = 0 === $count ? __( 'No articles were published. Only drafts and articles pending review that you may publish can be published.', 'chatbot' ) : sprintf( _n( '%d article published.', '%d articles published.', $count, 'chatbot' ), $count );
		echo '<div class="notice notice-' . ( 0 === $count ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
