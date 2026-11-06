<?php
/**
 * Decay source: page views recorded by Site Insight (WPBot's own visitor
 * insight module), read through its repository class, never its tables.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Improve;

defined( 'ABSPATH' ) || exit;

/**
 * Site Insight page views.
 */
final class Site_Insight_Source {

	const REPOSITORY = '\QuantumCloud\Insights\Storage\Session_Repository';

	/**
	 * Whether Site Insight is active with the method this source uses.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( self::REPOSITORY ) && method_exists( self::REPOSITORY, 'top_pages' );
	}

	/**
	 * Views per post: the last 28 days and the 28 days before.
	 *
	 * @return array[]
	 */
	public static function rows() {
		if ( ! self::available() ) {
			return array();
		}
		$class  = self::REPOSITORY;
		$repo   = new $class();
		$recent = self::by_post( $repo->top_pages( gmdate( 'Y-m-d H:i:s', time() - 28 * DAY_IN_SECONDS ), 1000 ) );
		$both   = self::by_post( $repo->top_pages( gmdate( 'Y-m-d H:i:s', time() - 56 * DAY_IN_SECONDS ), 1000 ) );
		$rows   = array();
		foreach ( $both as $post_id => $total ) {
			$after  = isset( $recent[ $post_id ] ) ? $recent[ $post_id ]['views'] : 0;
			$rows[] = array(
				'post_id' => $post_id,
				'source'  => 'Site Insight',
				'metric'  => 'visits',
				'before'  => max( 0, $total['views'] - $after ),
				'after'   => $after,
				'scroll'  => isset( $recent[ $post_id ] ) ? $recent[ $post_id ]['scroll'] : 0,
			);
		}
		return $rows;
	}

	/**
	 * Sum page rows per post (several URLs can point to one post).
	 *
	 * @param array $pages Rows: url, views, avg_scroll.
	 * @return array post_id => views, scroll.
	 */
	private static function by_post( $pages ) {
		$out = array();
		foreach ( (array) $pages as $page ) {
			$page    = (array) $page;
			$post_id = isset( $page['url'] ) ? url_to_postid( (string) $page['url'] ) : 0;
			if ( ! $post_id ) {
				continue;
			}
			if ( ! isset( $out[ $post_id ] ) ) {
				$out[ $post_id ] = array(
					'views'  => 0,
					'scroll' => 0,
				);
			}
			$out[ $post_id ]['views'] += (int) $page['views'];
			$out[ $post_id ]['scroll'] = max( $out[ $post_id ]['scroll'], isset( $page['avg_scroll'] ) ? (float) $page['avg_scroll'] : 0 );
		}
		return $out;
	}
}
