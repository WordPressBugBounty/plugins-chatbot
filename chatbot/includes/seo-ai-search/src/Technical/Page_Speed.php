<?php
/**
 * PageSpeed Insights checks: lab score and Core Web Vitals field data from
 * the Chrome UX Report (what real visitors saw). The owner's optional key
 * raises the daily limit. Results are kept for the Technical screen and
 * scans; Site Insight's own speed data is linked when it exists.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Secrets;
use QuantumCloud\WPBotCore\Connections\Quota;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * PageSpeed Insights client.
 */
final class Page_Speed {

	/**
	 * Option with the latest results per URL (autoload off).
	 */
	const OPTION = 'qcld_wpbot_seo_pagespeed';

	/**
	 * Check one page (mobile).
	 *
	 * @param string $url Page of this site.
	 * @return array|WP_Error lab (score, lcp, cls, tbt), field (overall, lcp, inp, cls), checked_at.
	 */
	public static function check( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_url', __( 'Choose a page of this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$quota = Quota::take( 'pagespeed' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$args = array(
			'url'      => $url,
			'strategy' => 'mobile',
			'category' => 'performance',
		);
		$key  = Secrets::get( 'pagespeed' );
		if ( '' !== $key ) {
			$args['key'] = $key;
		}
		$endpoint = (string) apply_filters( 'qcld_wpbot_seo/endpoint', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed', 'pagespeed' );
		$response = wp_safe_remote_get( add_query_arg( array_map( 'rawurlencode', $args ), $endpoint ), array( 'timeout' => 60 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_seo_pagespeed_unreachable', __( 'PageSpeed Insights could not be reached. Try again later.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $data ) ) {
			$message = 429 === $code ? __( 'PageSpeed Insights\' daily limit was reached. Add a free API key in Settings > Connections.', 'chatbot' ) : ( 400 === $code || 403 === $code ? __( 'PageSpeed Insights refused the request. Check the API key in Settings > Connections, or the page address.', 'chatbot' ) : __( 'PageSpeed Insights returned an error. Try again later.', 'chatbot' ) );
			return new WP_Error( 'qcld_wpbot_seo_pagespeed_error', $message, array( 'status' => $code ? $code : 502 ) );
		}
		$result = self::shape( $data );
		$all    = self::stored();
		$all    = array( $url => $result ) + array_diff_key( $all, array( $url => true ) );
		update_option( self::OPTION, array_slice( $all, 0, 50, true ), false );
		return $result;
	}

	/**
	 * Results kept from earlier checks.
	 *
	 * @return array url => result.
	 */
	public static function stored() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Lab and field numbers from the API response.
	 *
	 * @param array $data Response.
	 * @return array
	 */
	public static function shape( array $data ) {
		$audits = isset( $data['lighthouseResult']['audits'] ) ? (array) $data['lighthouseResult']['audits'] : array();
		$num    = static function ( $id ) use ( $audits ) {
			return isset( $audits[ $id ]['numericValue'] ) ? round( (float) $audits[ $id ]['numericValue'], 3 ) : null;
		};
		$field  = isset( $data['loadingExperience']['metrics'] ) ? (array) $data['loadingExperience']['metrics'] : array();
		$metric = static function ( $id ) use ( $field ) {
			return isset( $field[ $id ] ) ? array(
				'p75'      => isset( $field[ $id ]['percentile'] ) ? (float) $field[ $id ]['percentile'] : null,
				'category' => isset( $field[ $id ]['category'] ) ? (string) $field[ $id ]['category'] : '',
			) : null;
		};
		return array(
			'lab'        => array(
				'score' => isset( $data['lighthouseResult']['categories']['performance']['score'] ) ? (int) round( 100 * (float) $data['lighthouseResult']['categories']['performance']['score'] ) : null,
				'lcp'   => $num( 'largest-contentful-paint' ),
				'cls'   => $num( 'cumulative-layout-shift' ),
				'tbt'   => $num( 'total-blocking-time' ),
			),
			'field'      => array(
				'overall' => isset( $data['loadingExperience']['overall_category'] ) ? (string) $data['loadingExperience']['overall_category'] : '',
				'lcp'     => $metric( 'LARGEST_CONTENTFUL_PAINT_MS' ),
				'inp'     => $metric( 'INTERACTION_TO_NEXT_PAINT' ),
				'cls'     => $metric( 'CUMULATIVE_LAYOUT_SHIFT_SCORE' ),
			),
			'checked_at' => gmdate( 'c' ),
		);
	}
}
