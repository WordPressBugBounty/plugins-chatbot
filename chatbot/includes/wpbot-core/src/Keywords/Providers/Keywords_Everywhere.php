<?php
/**
 * Keywords Everywhere: volume, CPC, competition and trend (one credit per
 * keyword, up to 100 per request); related keywords; domain keywords;
 * backlinks. Bearer key; form-encoded requests.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords\Providers;

use QuantumCloud\WPBotCore\Keywords\Abstract_Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Keywords Everywhere adapter.
 */
final class Keywords_Everywhere extends Abstract_Provider {

	/**
	 * API base.
	 */
	const BASE = 'https://api.keywordseverywhere.com/v1/';

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'keywordseverywhere';
	}

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	public function label() {
		return 'Keywords Everywhere';
	}

	/**
	 * Unit of costs and balances.
	 *
	 * @return string
	 */
	public function unit() {
		return 'credits';
	}

	/**
	 * Most keywords per volume request.
	 *
	 * @return int
	 */
	public function batch_size() {
		return 100;
	}

	/**
	 * Credits: one per keyword or row returned.
	 *
	 * @param string $op    Operation.
	 * @param int    $count Count.
	 * @return float
	 */
	public function estimate( $op, $count ) {
		unset( $op );
		return (float) max( 1, (int) $count );
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
		unset( $language );
		$data = $this->call( 'get_keyword_data', array_merge( $this->place( $country ), array( 'kw' => array_values( $keywords ) ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $item ) {
			if ( ! isset( $item['keyword'] ) ) {
				continue;
			}
			$cpc   = isset( $item['cpc'] ) ? ( is_array( $item['cpc'] ) ? ( isset( $item['cpc']['value'] ) ? $item['cpc']['value'] : null ) : $item['cpc'] ) : null;
			$trend = array();
			foreach ( isset( $item['trend'] ) ? (array) $item['trend'] : array() as $month ) {
				$trend[] = is_array( $month ) ? ( isset( $month['value'] ) ? $month['value'] : 0 ) : $month;
			}
			$out[] = $this->row( $item['keyword'], isset( $item['vol'] ) ? $item['vol'] : ( isset( $item['volume'] ) ? $item['volume'] : null ), $cpc, isset( $item['competition'] ) ? $item['competition'] : null, array_slice( $trend, -12 ) );
		}
		return $out;
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
		$data = $this->call(
			'get_related_keywords',
			array(
				'keyword' => (string) $seed,
				'num'     => (int) $limit,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$words = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $item ) {
			$words[] = is_array( $item ) ? ( isset( $item['keyword'] ) ? $item['keyword'] : '' ) : (string) $item;
		}
		$words = array_values( array_filter( $words ) );
		return array() === $words ? array() : $this->volumes( array_slice( $words, 0, 100 ), $country, $language );
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
		unset( $language );
		$data = $this->call(
			'get_domain_keywords',
			array_merge(
				$this->place( $country ),
				array(
					'domain' => (string) $domain,
					'num'    => (int) $limit,
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $item ) {
			if ( isset( $item['keyword'] ) ) {
				$out[] = $this->row( $item['keyword'], isset( $item['vol'] ) ? $item['vol'] : null, null, null, array(), array( 'position' => isset( $item['serp_position'] ) ? (int) $item['serp_position'] : null ) );
			}
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
		$data = $this->call(
			'get_domain_backlinks',
			array(
				'domain' => (string) $domain,
				'num'    => (int) $limit,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $item ) {
			$out[] = array(
				'source_url' => isset( $item['url_source'] ) ? (string) $item['url_source'] : '',
				'target_url' => isset( $item['url_target'] ) ? (string) $item['url_target'] : '',
				'anchor'     => isset( $item['anchor_text'] ) ? (string) $item['anchor_text'] : '',
				'rel'        => '',
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
		$response = $this->http( 'GET', self::BASE . 'account/credits', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$error = $this->error( $response );
		if ( $error ) {
			return $error;
		}
		$json = json_decode( $response['body'], true );
		if ( is_array( $json ) ) {
			$first = reset( $json );
			return (float) ( is_array( $first ) ? reset( $first ) : $first );
		}
		return (float) $json;
	}

	/**
	 * Country and currency.
	 *
	 * @param string $country Country.
	 * @return array
	 */
	private function place( $country ) {
		return array(
			'country'    => strtolower( (string) $country ),
			'currency'   => 'usd',
			'dataSource' => 'gkp',
		);
	}

	/**
	 * Headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . $this->key,
			'Accept'        => 'application/json',
		);
	}

	/**
	 * POST form call.
	 *
	 * @param string $method Method path.
	 * @param array  $params Params.
	 * @return array|WP_Error
	 */
	private function call( $method, array $params ) {
		$response = $this->http(
			'POST',
			self::BASE . $method,
			array(
				'headers' => $this->headers(),
				'body'    => $params,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$error = $this->error( $response );
		if ( $error ) {
			return $error;
		}
		$json = json_decode( $response['body'], true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Error from a response, or null.
	 *
	 * @param array $response Response.
	 * @return WP_Error|null
	 */
	private function error( array $response ) {
		if ( 401 === $response['code'] || 403 === $response['code'] ) {
			return $this->bad_key();
		}
		if ( 402 === $response['code'] || false !== stripos( $response['body'], 'insufficient credits' ) ) {
			return $this->no_balance();
		}
		if ( 200 !== $response['code'] ) {
			$json = json_decode( $response['body'], true );
			return $this->failed( $response['code'], is_array( $json ) && isset( $json['message'] ) ? (string) $json['message'] : '' );
		}
		return null;
	}
}
