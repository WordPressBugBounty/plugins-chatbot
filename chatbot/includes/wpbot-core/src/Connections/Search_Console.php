<?php
/**
 * Read-only Search Console data through the shared Google connection:
 * the owner's sites and Search Analytics rows. Results are cached for a
 * day, and requests use small date ranges (Google's load quotas).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Search Console client.
 */
final class Search_Console {

	/**
	 * Sites the connected Google account can read.
	 *
	 * @return string[]|WP_Error Property ids.
	 */
	public static function sites() {
		$data = Google::api( '/webmasters/v3/sites' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['siteEntry'] ) ? (array) $data['siteEntry'] : array() as $entry ) {
			if ( isset( $entry['siteUrl'], $entry['permissionLevel'] ) && 'siteUnverifiedUser' !== $entry['permissionLevel'] ) {
				$out[] = (string) $entry['siteUrl'];
			}
		}
		return $out;
	}

	/**
	 * The property to query: the saved one, else the one matching this site.
	 *
	 * @return string|WP_Error
	 */
	public static function property() {
		$saved = Google::property();
		if ( '' !== $saved ) {
			return $saved;
		}
		$sites = self::sites();
		if ( is_wp_error( $sites ) ) {
			return $sites;
		}
		$match = self::match( $sites, home_url( '/' ) );
		if ( '' === $match ) {
			return new WP_Error( 'qcld_wpbot_core_gsc_no_property', __( 'This site was not found in your Search Console account. Choose the property in Connections.', 'chatbot' ), array( 'status' => 400 ) );
		}
		Google::update( array( 'property' => $match ) );
		return $match;
	}

	/**
	 * The property that covers a URL (URL-prefix first, then domain).
	 *
	 * @param string[] $sites Properties.
	 * @param string   $url   Site URL.
	 * @return string
	 */
	public static function match( array $sites, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$bare = preg_replace( '/^www\./', '', $host );
		foreach ( $sites as $site ) {
			if ( 0 === stripos( $url, rtrim( $site, '/' ) ) ) {
				return $site;
			}
		}
		foreach ( $sites as $site ) {
			if ( 0 === strpos( $site, 'sc-domain:' ) ) {
				$domain = strtolower( substr( $site, 10 ) );
				if ( $bare === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
					return $site;
				}
			}
		}
		return '';
	}

	/**
	 * Search Analytics query, cached for a day.
	 *
	 * @param string   $start      Y-m-d.
	 * @param string   $end        Y-m-d.
	 * @param string[] $dimensions Dimensions: query, page, date.
	 * @param int      $limit      Rows (max 5000).
	 * @return array[]|WP_Error Rows: keys, clicks, impressions, ctr, position.
	 */
	public static function query( $start, $end, array $dimensions = array( 'query' ), $limit = 1000 ) {
		$property = self::property();
		if ( is_wp_error( $property ) ) {
			return $property;
		}
		$body  = array(
			'startDate'  => $start,
			'endDate'    => $end,
			'dimensions' => array_values( array_intersect( $dimensions, array( 'query', 'page', 'date', 'country', 'device' ) ) ),
			'rowLimit'   => max( 1, min( 5000, (int) $limit ) ),
			'dataState'  => 'final',
		);
		$cache = 'qcld_wpbot_core_gsc_' . md5( $property . wp_json_encode( $body ) );
		$found = get_transient( $cache );
		if ( is_array( $found ) ) {
			return $found;
		}
		$data = Google::api( '/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query', $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = array();
		foreach ( isset( $data['rows'] ) ? (array) $data['rows'] : array() as $row ) {
			$rows[] = array(
				'keys'        => array_map( 'strval', isset( $row['keys'] ) ? (array) $row['keys'] : array() ),
				'clicks'      => isset( $row['clicks'] ) ? (float) $row['clicks'] : 0,
				'impressions' => isset( $row['impressions'] ) ? (float) $row['impressions'] : 0,
				'ctr'         => isset( $row['ctr'] ) ? (float) $row['ctr'] : 0,
				'position'    => isset( $row['position'] ) ? (float) $row['position'] : 0,
			);
		}
		set_transient( $cache, $rows, DAY_IN_SECONDS );
		return $rows;
	}

	/**
	 * One page of Search Analytics rows for a data pull (not cached).
	 *
	 * Used by modules that store their own daily aggregates. Supports
	 * paging (startRow), dimension filters and the data state, which the
	 * cached query() does not.
	 *
	 * @param array $args start, end (Y-m-d), dimensions[], filters[] (dimension, operator, expression), row_limit (<= 25000), start_row, data_state (final|all), search_type (web|image|video|news|discover|googleNews).
	 * @return array|WP_Error Rows with keys, clicks, impressions, ctr, position.
	 */
	public static function rows( array $args ) {
		$property = self::property();
		if ( is_wp_error( $property ) ) {
			return $property;
		}
		$quota = Quota::take( 'gsc_query' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$filters = array();
		foreach ( isset( $args['filters'] ) ? (array) $args['filters'] : array() as $filter ) {
			if ( ! is_array( $filter ) || empty( $filter['dimension'] ) || ! isset( $filter['expression'] ) ) {
				continue;
			}
			$operator  = isset( $filter['operator'] ) ? (string) $filter['operator'] : 'equals';
			$filters[] = array(
				'dimension'  => in_array( $filter['dimension'], array( 'query', 'page', 'country', 'device', 'searchAppearance' ), true ) ? $filter['dimension'] : 'page',
				'operator'   => in_array( $operator, array( 'equals', 'notEquals', 'contains', 'notContains', 'includingRegex', 'excludingRegex' ), true ) ? $operator : 'equals',
				'expression' => (string) $filter['expression'],
			);
		}
		$body = array(
			'startDate'  => (string) $args['start'],
			'endDate'    => (string) $args['end'],
			'dimensions' => array_values( array_intersect( isset( $args['dimensions'] ) ? (array) $args['dimensions'] : array( 'query' ), array( 'query', 'page', 'date', 'country', 'device' ) ) ),
			'rowLimit'   => max( 1, min( 25000, isset( $args['row_limit'] ) ? (int) $args['row_limit'] : 25000 ) ),
			'startRow'   => max( 0, isset( $args['start_row'] ) ? (int) $args['start_row'] : 0 ),
			'dataState'  => isset( $args['data_state'] ) && 'all' === $args['data_state'] ? 'all' : 'final',
			'type'       => isset( $args['search_type'] ) && in_array( $args['search_type'], array( 'web', 'image', 'video', 'news', 'discover', 'googleNews' ), true ) ? $args['search_type'] : 'web',
		);
		if ( array() !== $filters ) {
			$body['dimensionFilterGroups'] = array( array( 'filters' => $filters ) );
		}
		$data = Google::api( '/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query', $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return self::shape_rows( $data );
	}

	/**
	 * Sitemaps submitted for the property.
	 *
	 * @return array|WP_Error List of path, last_submitted, last_downloaded, errors, warnings, pending, contents[].
	 */
	public static function sitemaps() {
		$property = self::property();
		if ( is_wp_error( $property ) ) {
			return $property;
		}
		$quota = Quota::take( 'gsc_query' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$data = Google::api( '/webmasters/v3/sites/' . rawurlencode( $property ) . '/sitemaps' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['sitemap'] ) ? (array) $data['sitemap'] : array() as $map ) {
			$out[] = array(
				'path'            => isset( $map['path'] ) ? esc_url_raw( (string) $map['path'] ) : '',
				'last_submitted'  => isset( $map['lastSubmitted'] ) ? (string) $map['lastSubmitted'] : '',
				'last_downloaded' => isset( $map['lastDownloaded'] ) ? (string) $map['lastDownloaded'] : '',
				'errors'          => isset( $map['errors'] ) ? (int) $map['errors'] : 0,
				'warnings'        => isset( $map['warnings'] ) ? (int) $map['warnings'] : 0,
				'pending'         => ! empty( $map['isPending'] ),
				'contents'        => isset( $map['contents'] ) ? (array) $map['contents'] : array(),
			);
		}
		return $out;
	}

	/**
	 * Index status of one URL (URL Inspection API; 2,000 a day per property).
	 *
	 * @param string $url Page URL inside the property.
	 * @return array|WP_Error verdict, coverage, indexing, robots, fetch, google_canonical, user_canonical, last_crawl, sitemaps[].
	 */
	public static function inspect( $url ) {
		$property = self::property();
		if ( is_wp_error( $property ) ) {
			return $property;
		}
		$quota = Quota::take( 'gsc_inspect' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$data = Google::api(
			'/v1/urlInspection/index:inspect',
			array(
				'inspectionUrl' => esc_url_raw( (string) $url ),
				'siteUrl'       => $property,
			),
			'searchconsole'
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$index = isset( $data['inspectionResult']['indexStatusResult'] ) ? (array) $data['inspectionResult']['indexStatusResult'] : array();
		$field = static function ( $key ) use ( $index ) {
			return isset( $index[ $key ] ) && is_scalar( $index[ $key ] ) ? (string) $index[ $key ] : '';
		};
		return array(
			'verdict'          => $field( 'verdict' ),
			'coverage'         => $field( 'coverageState' ),
			'indexing'         => $field( 'indexingState' ),
			'robots'           => $field( 'robotsTxtState' ),
			'fetch'            => $field( 'pageFetchState' ),
			'google_canonical' => $field( 'googleCanonical' ),
			'user_canonical'   => $field( 'userCanonical' ),
			'last_crawl'       => $field( 'lastCrawlTime' ),
			'sitemaps'         => isset( $index['sitemap'] ) ? array_map( 'strval', (array) $index['sitemap'] ) : array(),
		);
	}

	/**
	 * Normalise Search Analytics rows.
	 *
	 * @param array $data API response.
	 * @return array
	 */
	private static function shape_rows( array $data ) {
		$rows = array();
		foreach ( isset( $data['rows'] ) ? (array) $data['rows'] : array() as $row ) {
			$rows[] = array(
				'keys'        => array_map( 'strval', isset( $row['keys'] ) ? (array) $row['keys'] : array() ),
				'clicks'      => isset( $row['clicks'] ) ? (float) $row['clicks'] : 0,
				'impressions' => isset( $row['impressions'] ) ? (float) $row['impressions'] : 0,
				'ctr'         => isset( $row['ctr'] ) ? (float) $row['ctr'] : 0,
				'position'    => isset( $row['position'] ) ? (float) $row['position'] : 0,
			);
		}
		return $rows;
	}

	/**
	 * Clear cached results (after reconnecting or changing the property).
	 *
	 * @return void
	 */
	public static function flush() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_qcld_wpbot_core_gsc_' ) . '%', $wpdb->esc_like( '_transient_timeout_qcld_wpbot_core_gsc_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing this module's own transients.
	}
}
