<?php
/**
 * Semrush (owners with a Business plan and API units): phrase data,
 * related keywords, a domain's organic keywords and backlinks. Responses
 * are semicolon-separated text; errors come as "ERROR <code> :: <text>".
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords\Providers;

use QuantumCloud\WPBotCore\Keywords\Abstract_Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Semrush adapter.
 */
final class Semrush extends Abstract_Provider {

	/**
	 * API base.
	 */
	const BASE = 'https://api.semrush.com/';

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'semrush';
	}

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	public function label() {
		return 'Semrush';
	}

	/**
	 * Unit of costs and balances.
	 *
	 * @return string
	 */
	public function unit() {
		return 'units';
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
	 * API units (estimates; filterable).
	 *
	 * @param string $op    Operation.
	 * @param int    $count Lines.
	 * @return float
	 */
	public function estimate( $op, $count ) {
		$per_line = (array) apply_filters(
			'qcld_wpbot_core/keyword_prices',
			array(
				'volumes'         => 10,
				'ideas'           => 40,
				'domain_keywords' => 10,
				'backlinks'       => 40,
			),
			$this->id()
		);
		return (float) ( isset( $per_line[ $op ] ) ? $per_line[ $op ] : 10 ) * max( 1, (int) $count );
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
		$rows = $this->get(
			array(
				'type'           => 'phrase_these',
				'phrase'         => implode( ';', array_map( array( __CLASS__, 'clean' ), $keywords ) ),
				'database'       => $this->database( $country ),
				'export_columns' => 'Ph,Nq,Cp,Co,Td',
			)
		);
		return is_wp_error( $rows ) ? $rows : array_map( array( $this, 'phrase_row' ), $rows );
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
		unset( $language );
		$rows = $this->get(
			array(
				'type'           => 'phrase_related',
				'phrase'         => self::clean( $seed ),
				'database'       => $this->database( $country ),
				'display_limit'  => (int) $limit,
				'export_columns' => 'Ph,Nq,Cp,Co,Td',
			)
		);
		return is_wp_error( $rows ) ? $rows : array_map( array( $this, 'phrase_row' ), $rows );
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
		$rows = $this->get(
			array(
				'type'           => 'domain_organic',
				'domain'         => (string) $domain,
				'database'       => $this->database( $country ),
				'display_limit'  => (int) $limit,
				'export_columns' => 'Ph,Po,Nq,Cp',
			)
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->row( $row[0], isset( $row[2] ) ? $row[2] : null, isset( $row[3] ) ? $row[3] : null, null, array(), array( 'position' => isset( $row[1] ) ? (int) $row[1] : null ) );
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
		$rows = $this->get(
			array(
				'type'           => 'backlinks',
				'target'         => (string) $domain,
				'target_type'    => 'root_domain',
				'display_limit'  => (int) $limit,
				'export_columns' => 'source_url,target_url,anchor,nofollow',
			),
			'analytics/v1/'
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'source_url' => (string) $row[0],
				'target_url' => isset( $row[1] ) ? (string) $row[1] : '',
				'anchor'     => isset( $row[2] ) ? (string) $row[2] : '',
				'rel'        => isset( $row[3] ) && 'true' === strtolower( $row[3] ) ? 'nofollow' : 'follow',
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
		$response = $this->http( 'GET', add_query_arg( 'key', rawurlencode( $this->key ), 'https://www.semrush.com/users/countapiunits.html' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$error = $this->error( $response );
		return $error ? $error : (float) trim( $response['body'] );
	}

	/**
	 * Row from Ph, Nq, Cp, Co, Td.
	 *
	 * @param array $row Columns.
	 * @return array
	 */
	public function phrase_row( array $row ) {
		$trend = isset( $row[4] ) && '' !== $row[4] ? array_map( 'floatval', explode( ',', $row[4] ) ) : array();
		$vol   = isset( $row[1] ) ? (int) $row[1] : 0;
		// Semrush trends are shares of the peak month; turn them into volumes.
		$max   = $trend ? max( $trend ) : 0;
		$trend = $max > 0 ? array_map(
			static function ( $share ) use ( $vol, $max ) {
				return (int) round( $vol * $share / $max );
			},
			$trend
		) : array();
		return $this->row( $row[0], isset( $row[1] ) ? $row[1] : null, isset( $row[2] ) ? $row[2] : null, isset( $row[3] ) ? $row[3] : null, $trend );
	}

	/**
	 * Database for a country (Semrush uses lowercase country codes; "us" by default).
	 *
	 * @param string $country Country.
	 * @return string
	 */
	private function database( $country ) {
		$country = strtolower( (string) $country );
		return 'gb' === $country ? 'uk' : ( '' !== $country ? $country : 'us' );
	}

	/**
	 * Keyword safe for the list separator.
	 *
	 * @param string $keyword Keyword.
	 * @return string
	 */
	private static function clean( $keyword ) {
		return trim( str_replace( ';', ' ', (string) $keyword ) );
	}

	/**
	 * GET and parse the semicolon table (header row dropped).
	 *
	 * @param array  $params Params.
	 * @param string $path   Path.
	 * @return array|WP_Error Rows.
	 */
	private function get( array $params, $path = '' ) {
		$response = $this->http( 'GET', add_query_arg( array_map( 'rawurlencode', array_merge( $params, array( 'key' => $this->key ) ) ), self::BASE . $path ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 0 === stripos( trim( $response['body'] ), 'ERROR 50 ' ) ) {
			return array();
		}
		$error = $this->error( $response );
		if ( $error ) {
			return $error;
		}
		$lines = array_values( array_filter( preg_split( '/\R/', trim( $response['body'] ) ) ) );
		array_shift( $lines );
		return array_map(
			static function ( $line ) {
				return str_getcsv( $line, ';', '"', '\\' );
			},
			$lines
		);
	}

	/**
	 * Error from a response, or null.
	 *
	 * @param array $response Response.
	 * @return WP_Error|null
	 */
	private function error( array $response ) {
		$body = trim( $response['body'] );
		if ( preg_match( '/^ERROR (\d+) ::\s*(.*)$/', $body, $m ) ) {
			if ( in_array( (int) $m[1], array( 120, 121, 131 ), true ) ) {
				return $this->bad_key();
			}
			if ( 132 === (int) $m[1] ) {
				return $this->no_balance();
			}
			return $this->failed( $response['code'], $m[2] );
		}
		if ( 401 === $response['code'] || 403 === $response['code'] ) {
			return $this->bad_key();
		}
		return 200 === $response['code'] ? null : $this->failed( $response['code'] );
	}
}
