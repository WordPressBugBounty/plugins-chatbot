<?php
/**
 * Known links to the site (link monitor rows).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Add, list and update known links.
 */
final class Link_Store {

	/**
	 * Origins.
	 */
	const ORIGINS = array( 'csv', 'bing', 'outreach', 'manual', 'exchange', 'referral', 'backlinks' );

	/**
	 * Statuses.
	 */
	const STATUSES = array( 'unchecked', 'live', 'changed', 'lost', 'blocked', 'error' );

	/**
	 * Add a link, or update the anchor/rel/origin of a known one.
	 *
	 * @param array $link source_url, target_url, anchor, rel, origin, outreach_id, notes.
	 * @return int|WP_Error Link id.
	 */
	public static function add( array $link ) {
		global $wpdb;
		$raw    = trim( isset( $link['source_url'] ) ? (string) $link['source_url'] : '' );
		$source = preg_match( '#^https?://[a-z0-9-]+(\.[a-z0-9-]+)+(:\d+)?(/\S*)?$#i', $raw ) ? esc_url_raw( $raw, array( 'http', 'https' ) ) : '';
		$target = esc_url_raw( trim( isset( $link['target_url'] ) ? (string) $link['target_url'] : '' ), array( 'http', 'https' ) );
		if ( '' === $source || '' === Link_Finder::host( $source ) ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_url', __( 'Enter the full address of the page that links to you.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( '' === $target ) {
			$target = home_url( '/' );
		}
		if ( Link_Finder::host( $source ) === Link_Finder::host( home_url() ) ) {
			return new WP_Error( 'qcld_wpbot_seo_own_site', __( 'That page is on your own site; Links tracks links from other sites.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$origin = isset( $link['origin'] ) && in_array( $link['origin'], self::ORIGINS, true ) ? $link['origin'] : 'manual';
		$hash   = self::hash( $source, $target );
		$table  = Schema::table( Schema::LINKS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$id   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE hash = %s', $table, $hash ) );
		$data = array(
			'anchor' => self::cut( isset( $link['anchor'] ) ? sanitize_text_field( (string) $link['anchor'] ) : '', 250 ),
			'rel'    => isset( $link['rel'] ) && in_array( $link['rel'], array( 'follow', 'nofollow', 'sponsored', 'ugc' ), true ) ? $link['rel'] : '',
		);
		if ( $id ) {
			$update = array_filter( $data );
			if ( ! empty( $link['outreach_id'] ) ) {
				$update['outreach_id'] = absint( $link['outreach_id'] );
				$update['origin']      = 'outreach';
			}
			if ( $update ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				$wpdb->update( $table, $update, array( 'id' => $id ) );
			}
			return $id;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			$table,
			array_merge(
				$data,
				array(
					'hash'        => $hash,
					'source_url'  => $source,
					'source_host' => Link_Finder::host( $source ),
					'target_url'  => $target,
					'origin'      => $origin,
					'status'      => 'unchecked',
					'outreach_id' => isset( $link['outreach_id'] ) ? absint( $link['outreach_id'] ) : 0,
					'notes'       => isset( $link['notes'] ) ? sanitize_textarea_field( (string) $link['notes'] ) : '',
					'first_seen'  => current_time( 'mysql', true ),
				)
			)
		);
		$id = (int) $wpdb->insert_id;
		/**
		 * A link was added to the monitor.
		 *
		 * @param int    $id     Link id.
		 * @param string $origin Origin.
		 */
		do_action( 'qcld_wpbot_seo/link_added', $id, $origin );
		return $id;
	}

	/**
	 * One link.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::LINKS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Links with filters.
	 *
	 * @param array $args status, origin, search, limit, offset.
	 * @return array items, total, counts (by status).
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = Schema::table( Schema::LINKS );
		$where  = '1=1';
		$values = array( $table );
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where   .= ' AND status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['origin'] ) && in_array( $args['origin'], self::ORIGINS, true ) ) {
			$where   .= ' AND origin = %s';
			$values[] = $args['origin'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND (source_url LIKE %s OR target_url LIKE %s OR anchor LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		$limit  = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 100;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own table; table name passed as %i.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds only fixed placeholder fragments; values are passed to prepare() as one array.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $values, array( $limit, $offset ) ) ), ARRAY_A );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where holds only fixed placeholder fragments; values are passed to prepare().
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", $values ) );
		$counts = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', $table ), ARRAY_A );
		$sites  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT source_host) FROM %i WHERE status <> 'lost'", $table ) );
		// phpcs:enable
		return array(
			'items'  => array_map( array( __CLASS__, 'shape' ), $rows ),
			'total'  => $total,
			'counts' => array_map( 'intval', array_column( $counts, 'n', 'status' ) ),
			'sites'  => $sites,
		);
	}

	/**
	 * Ids of links to check (oldest check first).
	 *
	 * @param int[] $ids   Only these (empty = all).
	 * @param int   $limit Most links.
	 * @return int[]
	 */
	public static function ids_to_check( array $ids = array(), $limit = 1000 ) {
		global $wpdb;
		$table = Schema::table( Schema::LINKS );
		$ids   = array_filter( array_map( 'absint', $ids ) );
		$where = $ids ? 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')' : '1=1';
		$args  = array_merge( array( $table ), array_values( $ids ), array( (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds only %d placeholders; ids are passed to prepare() as one array.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE {$where} ORDER BY last_checked IS NOT NULL, last_checked ASC LIMIT %d", $args ) ) );
	}

	/**
	 * Save a check result; fires `qcld_wpbot_seo/link_status_changed` when the status changes.
	 *
	 * @param int   $id     Link id.
	 * @param array $result status, http_code, rel, anchor, target_ok.
	 * @return void
	 */
	public static function save_check( $id, array $result ) {
		global $wpdb;
		$before = self::get( $id );
		if ( ! $before ) {
			return;
		}
		$update = array(
			'status'       => in_array( $result['status'], self::STATUSES, true ) ? $result['status'] : 'error',
			'http_code'    => (int) $result['http_code'],
			'target_ok'    => empty( $result['target_ok'] ) ? 0 : 1,
			'last_checked' => current_time( 'mysql', true ),
		);
		if ( ! empty( $result['rel'] ) ) {
			$update['rel'] = $result['rel'];
		}
		if ( isset( $result['anchor'] ) && '' !== $result['anchor'] ) {
			$update['anchor'] = self::cut( $result['anchor'], 250 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::LINKS ), $update, array( 'id' => (int) $id ) );
		if ( $before['status'] !== $update['status'] ) {
			/**
			 * A known link changed status (for example live to lost).
			 *
			 * @param array  $link   Link after the check.
			 * @param string $before Earlier status.
			 */
			do_action( 'qcld_wpbot_seo/link_status_changed', self::get( $id ), $before['status'] );
		}
	}

	/**
	 * Is a link from this page already known (any target)?
	 *
	 * @param string $source Linking page.
	 * @return bool
	 */
	public static function has_source( $source ) {
		global $wpdb;
		$table = Schema::table( Schema::LINKS );
		$host  = Link_Finder::host( $source );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$urls = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT source_url FROM %i WHERE source_host = %s', $table, $host ) );
		$want = Link_Finder::key( $source );
		foreach ( $urls as $url ) {
			if ( Link_Finder::key( $url ) === $want ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Delete a link.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (bool) $wpdb->delete( Schema::table( Schema::LINKS ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Hosts already in the monitor.
	 *
	 * @return string[]
	 */
	public static function hosts() {
		global $wpdb;
		$table = Schema::table( Schema::LINKS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT source_host FROM %i', $table ) );
	}

	/**
	 * Row as returned to the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		foreach ( array( 'id', 'http_code', 'outreach_id' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}
		$row['target_ok']    = (bool) $row['target_ok'];
		$row['last_checked'] = (string) $row['last_checked'];
		unset( $row['hash'] );
		return $row;
	}

	/**
	 * Hash of a source and target pair.
	 *
	 * @param string $source Source URL.
	 * @param string $target Target URL.
	 * @return string
	 */
	public static function hash( $source, $target ) {
		$s = Link_Finder::key( $source );
		$t = Link_Finder::key( $target );
		$q = (string) wp_parse_url( $source, PHP_URL_QUERY );
		return md5( $s['host'] . $s['path'] . '?' . $q . '|' . $t['host'] . $t['path'] );
	}

	/**
	 * Cut a string.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $max ) : substr( (string) $text, 0, $max );
	}
}
