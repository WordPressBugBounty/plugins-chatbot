<?php
/**
 * Social posts as calendar items: for Social's Calendar and for AI Writer's
 * calendar (qcld_wpbot_writer/calendar_items).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Calendar;

use QuantumCloud\Social\Posts\Post_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Calendar items.
 */
final class Calendar_Items {

	/**
	 * Hook into AI Writer's calendar.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_writer/calendar_items', array( __CLASS__, 'for_writer' ), 10, 3 );
	}

	/**
	 * Items between two GMT dates.
	 *
	 * @param string $from     GMT Y-m-d H:i:s.
	 * @param string $to       GMT Y-m-d H:i:s.
	 * @param int    $brand_id Brand (0 = all).
	 * @return array[] Each: id, title, date (site time), status, networks, url.
	 */
	public static function between( $from, $to, $brand_id = 0 ) {
		$result = Post_Store::query(
			array(
				'from'     => $from,
				'to'       => $to,
				'brand_id' => $brand_id,
				'brands'   => \QuantumCloud\Social\Support\Brand_Access::allowed(),
				'limit'    => 200,
			)
		);
		$items  = array();
		foreach ( $result['items'] as $post ) {
			$items[] = array(
				'id'       => $post['id'],
				'title'    => '' !== $post['title'] ? $post['title'] : wp_trim_words( isset( $post['meta']['text'] ) ? (string) $post['meta']['text'] : '', 8 ),
				'date'     => get_date_from_gmt( (string) $post['schedule_at'], 'Y-m-d H:i' ),
				'status'   => $post['status'],
				'networks' => array_values( array_unique( array_column( $post['targets'], 'network' ) ) ),
				'url'      => admin_url( 'admin.php?page=wpbot-social&view=posts&post=' . $post['id'] ),
				'source'   => 'social',
			);
		}
		/**
		 * Filters Social's calendar items (the Master module adds queue slots).
		 *
		 * @param array[] $items Items.
		 * @param string  $from  GMT start.
		 * @param string  $to    GMT end.
		 */
		return (array) apply_filters( 'qcld_wpbot_social/calendar_items', $items, $from, $to );
	}

	/**
	 * Add Social posts to AI Writer's calendar.
	 *
	 * @param array  $items AI Writer items.
	 * @param string $from  Start (site date Y-m-d).
	 * @param string $to    End (site date Y-m-d).
	 * @return array
	 */
	public static function for_writer( $items, $from = '', $to = '' ) {
		if ( ! current_user_can( 'wpbot_social_view' ) || '' === (string) $from || '' === (string) $to ) {
			return $items;
		}
		$gmt_from = get_gmt_from_date( substr( (string) $from, 0, 10 ) . ' 00:00:00' );
		$gmt_to   = get_gmt_from_date( substr( (string) $to, 0, 10 ) . ' 23:59:59' );
		return array_merge( (array) $items, self::between( $gmt_from, $gmt_to ) );
	}
}
