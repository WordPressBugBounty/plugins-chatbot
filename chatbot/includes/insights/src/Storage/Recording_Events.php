<?php
/**
 * Reads the rrweb events of one recorded page view, merged from its chunks.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

use QuantumCloud\Insights\Module;

defined( 'ABSPATH' ) || exit;

final class Recording_Events {

	/**
	 * Events of a page view in time order.
	 *
	 * @param string $session_uuid  Session UUID.
	 * @param string $pageview_uuid Page view UUID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function load( $session_uuid, $pageview_uuid ) {
		$events = array();
		foreach ( Module::instance()->store()->get_pageview( $session_uuid, $pageview_uuid ) as $chunk ) {
			$json = ( "\x1f\x8b" === substr( $chunk, 0, 2 ) ) ? @gzdecode( $chunk ) : $chunk; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $json ) {
				continue;
			}
			$data = json_decode( $json, true );
			if ( is_array( $data ) && isset( $data['events'] ) && is_array( $data['events'] ) ) {
				foreach ( $data['events'] as $event ) {
					if ( is_array( $event ) ) {
						$events[] = $event;
					}
				}
			}
		}
		usort(
			$events,
			static function ( $a, $b ) {
				return ( $a['timestamp'] ?? 0 ) <=> ( $b['timestamp'] ?? 0 );
			}
		);
		return $events;
	}
}
