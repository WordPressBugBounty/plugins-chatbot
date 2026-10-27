<?php
/**
 * Per-visitor and site-wide request limits for the public ingest endpoint.
 * Uses the object cache when available, else transients. IPs are only kept
 * as salted hashes inside one-minute counters.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Ingest;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	/**
	 * Whether this request is within limits. Counts the request.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public function allow( $ip ) {
		/**
		 * Ingest limits per minute.
		 *
		 * @param array{per_ip:int,site:int} $limits Limits.
		 */
		$limits = (array) apply_filters(
			'qcin/ingest_rate_limit',
			array(
				'per_ip' => 60,
				'site'   => 3000,
			)
		);
		$minute = (int) floor( time() / 60 );

		$site_key = 'qcin_rl_site_' . $minute;
		if ( $this->bump( $site_key ) > (int) $limits['site'] ) {
			return false;
		}
		$ip_key = 'qcin_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 ) . '_' . $minute;
		return $this->bump( $ip_key ) <= (int) $limits['per_ip'];
	}

	/**
	 * Increment a one-minute counter.
	 *
	 * @param string $key Key.
	 * @return int New value.
	 */
	private function bump( $key ) {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'qcin', 120 );
			return (int) wp_cache_incr( $key, 1, 'qcin' );
		}
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, 120 );
		return $count;
	}
}
