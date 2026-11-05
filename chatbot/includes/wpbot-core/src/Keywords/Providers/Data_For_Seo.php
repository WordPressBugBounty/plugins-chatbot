<?php
/**
 * DataForSEO (default suggestion): Google Ads volumes, CPC, competition
 * and trend; keyword ideas; ranked keywords of a domain (Labs); backlinks.
 * Key format: "API login:API password" (HTTP Basic). Live endpoints.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords\Providers;

use QuantumCloud\WPBotCore\Keywords\Abstract_Provider;
use QuantumCloud\WPBotCore\Keywords\Countries;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * DataForSEO adapter.
 */
final class Data_For_Seo extends Abstract_Provider {

	/**
	 * API base.
	 */
	const BASE = 'https://api.dataforseo.com/v3/';

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'dataforseo';
	}

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	public function label() {
		return 'DataForSEO';
	}

	/**
	 * Unit of costs and balances.
	 *
	 * @return string
	 */
	public function unit() {
		return 'usd';
	}

	/**
	 * Most keywords per volume request.
	 *
	 * @return int
	 */
	public function batch_size() {
		return 1000;
	}

	/**
	 * Estimates in USD (prices checked 31 October 2026: live search volume
	 * $0.09 per task of up to 1,000 keywords; filterable).
	 *
	 * @param string $op    Operation.
	 * @param int    $count Count.
	 * @return float
	 */
	public function estimate( $op, $count ) {
		$prices = (array) apply_filters(
			'qcld_wpbot_core/keyword_prices',
			array(
				'volumes'         => 0.09,
				'ideas'           => 0.09,
				'domain_keywords' => 0.011,
				'backlinks'       => 0.022,
				'serp'            => 0.002,
			),
			$this->id()
		);
		$price  = isset( $prices[ $op ] ) ? (float) $prices[ $op ] : 0.1;
		if ( 'volumes' === $op ) {
			return round( $price * max( 1, (int) ceil( $count / $this->batch_size() ) ), 4 );
		}
		if ( 'domain_keywords' === $op ) {
			return round( $price + 0.0001 * (int) $count, 4 );
		}
		if ( 'backlinks' === $op ) {
			return round( $price + 0.00003 * (int) $count, 4 );
		}
		return round( $price, 4 );
	}

	/**
	 * Volumes for one batch of keywords.
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country Country code.
	 * @param string   $language Language code.
	 * @return array|WP_Error
	 */
	public function volumes( array $keywords, $country, $language ) {
		$data = $this->post( 'keywords_data/google_ads/search_volume/live', array_merge( array( 'keywords' => array_values( $keywords ) ), $this->place( $country, $language ) ) );
		return is_wp_error( $data ) ? $data : $this->rows( $data );
	}

	/**
	 * Keyword ideas for a seed.
	 *
	 * @param string $seed Seed keyword.
	 * @param string $country Country code.
	 * @param string $language Language code.
	 * @param int    $limit Rows.
	 * @return array|WP_Error
	 */
	public function ideas( $seed, $country, $language, $limit ) {
		$data = $this->post( 'keywords_data/google_ads/keywords_for_keywords/live', array_merge( array( 'keywords' => array( (string) $seed ) ), $this->place( $country, $language ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = $this->rows( $data );
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $b['volume'] <=> (int) $a['volume'];
			}
		);
		return array_slice( $rows, 0, (int) $limit );
	}

	/**
	 * Keywords a domain ranks for.
	 *
	 * @param string $domain Domain.
	 * @param string $country Country code.
	 * @param string $language Language code.
	 * @param int    $limit Rows.
	 * @return array|WP_Error
	 */
	public function domain_keywords( $domain, $country, $language, $limit ) {
		$data = $this->post(
			'dataforseo_labs/google/ranked_keywords/live',
			array_merge(
				array(
					'target' => (string) $domain,
					'limit'  => (int) $limit,
				),
				$this->place( $country, $language )
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data[0]['items'] ) ? (array) $data[0]['items'] : array() as $item ) {
			$kd    = isset( $item['keyword_data'] ) ? $item['keyword_data'] : array();
			$info  = isset( $kd['keyword_info'] ) ? $kd['keyword_info'] : array();
			$out[] = $this->row(
				isset( $kd['keyword'] ) ? $kd['keyword'] : '',
				isset( $info['search_volume'] ) ? $info['search_volume'] : null,
				isset( $info['cpc'] ) ? $info['cpc'] : null,
				isset( $info['competition'] ) ? $info['competition'] : null,
				$this->trend( isset( $info['monthly_searches'] ) ? $info['monthly_searches'] : array() ),
				array( 'position' => isset( $item['ranked_serp_element']['serp_item']['rank_absolute'] ) ? (int) $item['ranked_serp_element']['serp_item']['rank_absolute'] : null )
			);
		}
		return $out;
	}

	/**
	 * Google's organic results for a search (round 3, G): position, address, title, domain.
	 *
	 * @param string $keyword  Search.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Results (up to 100).
	 * @return array|WP_Error
	 */
	public function serp( $keyword, $country, $language, $limit ) {
		$data = $this->post(
			'serp/google/organic/live/regular',
			array_merge(
				array(
					'keyword' => (string) $keyword,
					'depth'   => max( 10, min( 100, (int) $limit ) ),
				),
				$this->place( $country, $language )
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data[0]['items'] ) ? (array) $data[0]['items'] : array() as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['type'] ) && 'organic' !== $item['type'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$out[] = array(
				'position' => isset( $item['rank_absolute'] ) ? (int) $item['rank_absolute'] : count( $out ) + 1,
				'url'      => esc_url_raw( (string) $item['url'] ),
				'title'    => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
				'domain'   => isset( $item['domain'] ) ? strtolower( sanitize_text_field( (string) $item['domain'] ) ) : '',
			);
		}
		return $out;
	}

	/**
	 * Backlinks to a domain.
	 *
	 * @param string $domain Domain.
	 * @param int    $limit Rows.
	 * @return array|WP_Error
	 */
	public function backlinks( $domain, $limit ) {
		$data = $this->post(
			'backlinks/backlinks/live',
			array(
				'target' => (string) $domain,
				'limit'  => (int) $limit,
				'mode'   => 'as_is',
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data[0]['items'] ) ? (array) $data[0]['items'] : array() as $item ) {
			$out[] = array(
				'source_url' => isset( $item['url_from'] ) ? (string) $item['url_from'] : '',
				'target_url' => isset( $item['url_to'] ) ? (string) $item['url_to'] : '',
				'anchor'     => isset( $item['anchor'] ) ? (string) $item['anchor'] : '',
				'rel'        => ! empty( $item['dofollow'] ) ? 'follow' : 'nofollow',
			);
		}
		return $out;
	}

	/**
	 * Balance left on the account.
	 *
	 * @return float|WP_Error
	 */
	public function balance() {
		$data = $this->request( 'GET', 'appendix/user_data', null );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data[0]['money']['balance'] ) ? (float) $data[0]['money']['balance'] : 0.0;
	}

	/**
	 * Location and language fields.
	 *
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @return array
	 */
	private function place( $country, $language ) {
		$code = Countries::location_code( $country );
		return array_filter(
			array(
				'location_code' => $code ? $code : 2840,
				'language_code' => (string) $language,
			)
		);
	}

	/**
	 * Rows from a keyword result.
	 *
	 * @param array $result Result list.
	 * @return array
	 */
	private function rows( array $result ) {
		$out = array();
		foreach ( $result as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['keyword'] ) ) {
				continue;
			}
			$out[] = $this->row(
				$item['keyword'],
				isset( $item['search_volume'] ) ? $item['search_volume'] : null,
				isset( $item['cpc'] ) ? $item['cpc'] : null,
				isset( $item['competition_index'] ) ? $item['competition_index'] / 100 : ( isset( $item['competition'] ) ? $item['competition'] : null ),
				$this->trend( isset( $item['monthly_searches'] ) ? $item['monthly_searches'] : array() )
			);
		}
		return $out;
	}

	/**
	 * Monthly searches (newest first in the API) as oldest first.
	 *
	 * @param array $months Months.
	 * @return int[]
	 */
	private function trend( $months ) {
		$out = array();
		foreach ( (array) $months as $month ) {
			if ( isset( $month['year'], $month['month'] ) ) {
				$out[ sprintf( '%04d%02d', $month['year'], $month['month'] ) ] = isset( $month['search_volume'] ) ? (int) $month['search_volume'] : 0;
			}
		}
		ksort( $out );
		return array_slice( array_values( $out ), -12 );
	}

	/**
	 * POST one task.
	 *
	 * @param string $path Path.
	 * @param array  $task Task.
	 * @return array|WP_Error Result list.
	 */
	private function post( $path, array $task ) {
		return $this->request( 'POST', $path, array( $task ) );
	}

	/**
	 * Request; returns the first task's result.
	 *
	 * @param string     $method Method.
	 * @param string     $path   Path.
	 * @param array|null $body   Body.
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $body ) {
		$args = array(
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
				'Authorization' => 'Basic ' . base64_encode( $this->key ),
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = $this->http( $method, self::BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = json_decode( $response['body'], true );
		$code = isset( $json['status_code'] ) ? (int) $json['status_code'] : 0;
		$task = isset( $json['tasks'][0] ) ? $json['tasks'][0] : array();
		$tc   = isset( $task['status_code'] ) ? (int) $task['status_code'] : $code;
		if ( 401 === $response['code'] || 40100 === $code || 40100 === $tc ) {
			return $this->bad_key();
		}
		if ( 402 === $response['code'] || 40200 === $code || 40200 === $tc || 40210 === $tc ) {
			return $this->no_balance();
		}
		if ( 200 !== $response['code'] || 20000 !== $tc ) {
			return $this->failed( $response['code'], isset( $task['status_message'] ) ? $task['status_message'] : ( isset( $json['status_message'] ) ? $json['status_message'] : '' ) );
		}
		return isset( $task['result'] ) && is_array( $task['result'] ) ? $task['result'] : array();
	}
}
