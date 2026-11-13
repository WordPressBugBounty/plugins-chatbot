<?php
/**
 * Keyword Vault (SEO & AI Search S14): the keywords a site owner researched,
 * targets or ranks for, shared by every module.
 *
 * Statuses: researched (new), targeted (a page is planned or assigned),
 * ranking (real rank data shows the site in the top 100; never set from AI),
 * archived (manual only).
 *
 * Honest numbers: volume, cost per click, difficulty and rank are stored
 * only with a real source (a keyword data provider, Search Console or
 * Google Ads). AI estimates live in `demand` and `difficulty_label`
 * (high, medium, low) and never in a numeric field.
 *
 * A targeted keyword with no page is also written to the shared keyword map
 * as a planned entry (plan_ref "vault-{id}"), so the Topic Map counts it as
 * planned coverage and AI Writer sees it.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Vault;

use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Storage\Seo_Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Vault storage and rules.
 */
final class Vault {

	const STATUSES = array( 'researched', 'targeted', 'ranking', 'archived' );
	const INTENTS  = array( '', 'informational', 'commercial', 'transactional', 'navigational' );
	const LEVELS   = array( '', 'high', 'medium', 'low' );
	const SOURCES  = array( 'research', 'niche', 'gap', 'search_console', 'google_ads', 'manual', 'csv', 'provider' );

	/**
	 * Sources whose numbers are real (never AI).
	 */
	const REAL_SOURCES = array( 'search_console', 'google_ads', 'provider', 'dataforseo', 'keywordseverywhere', 'semrush', 'google_ads_planner' );

	/**
	 * Trend over the last 12 months (round 2, R5).
	 */
	const TRENDS = array( '', 'rising', 'steady', 'falling' );

	/**
	 * Rank at or above this counts as "ranking".
	 */
	const RANKING_TOP = 100;

	/**
	 * Fields a caller may set.
	 */
	const FIELDS = array( 'keyword', 'language', 'country', 'status', 'intent', 'topic_id', 'post_id', 'demand', 'difficulty_label', 'volume', 'volume_source', 'cpc', 'difficulty', 'difficulty_source', 'rank', 'rank_source', 'rank_at', 'notes', 'source', 'context', 'module', 'trend', 'competition', 'bid_low', 'bid_high' );

	/**
	 * Add a keyword, or update the existing entry for the same keyword,
	 * language and country (fields given win; status never moves back).
	 *
	 * @param array $fields Fields (keyword required).
	 * @return int|WP_Error Id.
	 */
	public static function add( array $fields ) {
		global $wpdb;
		$keyword = self::normalise( isset( $fields['keyword'] ) ? $fields['keyword'] : '' );
		if ( '' === $keyword ) {
			return new WP_Error( 'qcld_wpbot_core_vault_keyword', __( 'Enter a keyword.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$language = self::code( isset( $fields['language'] ) ? $fields['language'] : '' );
		$country  = self::code( isset( $fields['country'] ) ? $fields['country'] : '' );
		$existing = self::find( $keyword, $language, $country );
		if ( $existing ) {
			$update = $fields;
			unset( $update['keyword'], $update['language'], $update['country'] );
			if ( isset( $update['status'] ) && self::rank_of( $update['status'] ) < self::rank_of( $existing['status'] ) ) {
				unset( $update['status'] );
			}
			$done = self::update( $existing['id'], $update );
			return is_wp_error( $done ) ? $done : (int) $existing['id'];
		}

		$row = self::clean( array_merge( array( 'status' => 'researched' ), $fields ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$now                 = current_time( 'mysql', true );
		$row['keyword']      = $keyword;
		$row['keyword_hash'] = md5( $keyword );
		$row['language']     = $language;
		$row['country']      = $country;
		$row['created_by']   = get_current_user_id();
		$row['created_at']   = $now;
		$row['updated_at']   = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'qcld_wpbot_core_vault_save', __( 'The keyword could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;
		self::sync_map( $id );
		self::changed( $id, 'add', $row );
		return $id;
	}

	/**
	 * Change fields of one entry.
	 *
	 * @param int   $id     Id.
	 * @param array $fields Fields.
	 * @return bool|WP_Error
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$entry = self::get( $id );
		if ( ! $entry ) {
			return new WP_Error( 'qcld_wpbot_core_vault_missing', __( 'This keyword is no longer in the Vault.', 'chatbot' ), array( 'status' => 404 ) );
		}
		unset( $fields['keyword'], $fields['language'], $fields['country'] );
		// Assigning a page targets the keyword, unless it already ranks.
		if ( ! empty( $fields['post_id'] ) && ! isset( $fields['status'] ) && 'researched' === $entry['status'] ) {
			$fields['status'] = 'targeted';
		}
		$row = self::clean( $fields );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( array() === $row ) {
			return true;
		}
		$row['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, write.
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		self::sync_map( (int) $id );
		self::changed( (int) $id, 'update', $row );
		return true;
	}

	/**
	 * Delete one entry (and its planned map entry).
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		Keyword_Map::remove_planned( self::plan_ref( $id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, write.
		$done = (bool) $wpdb->delete( self::table(), array( 'id' => $id ) );
		if ( $done ) {
			self::changed( $id, 'delete', array() );
		}
		return $done;
	}

	/**
	 * One entry.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from code.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Entry for a keyword in a language and country.
	 *
	 * @param string $keyword  Keyword.
	 * @param string $language Language code ('' for any).
	 * @param string $country  Country code ('' for any).
	 * @return array|null
	 */
	public static function find( $keyword, $language = '', $country = '' ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from code.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE keyword_hash = %s AND language = %s AND country = %s", md5( self::normalise( $keyword ) ), self::code( $language ), self::code( $country ) ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Query entries.
	 *
	 * @param array $args status (string|string[]), source, intent, topic_id, has_page (bool), search, language, country, trend, competition, min_volume, orderby, order, limit (max 500), offset.
	 * @return array[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		list( $where, $params ) = self::where( $args );
		$orderby                = isset( $args['orderby'] ) && in_array( $args['orderby'], array( 'keyword', 'status', 'intent', 'demand', 'volume', 'cpc', 'difficulty', 'rank', 'bid_high', 'created_at', 'updated_at' ), true ) ? $args['orderby'] : 'updated_at';
		$orderby                = 'rank' === $orderby ? 'rank_position' : $orderby;
		$order                  = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$limit                  = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 50;
		$offset                 = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$table                  = self::table();
		$sql                    = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		$params                 = array_merge( $params, array( $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Built from code and placeholders only.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * Number of entries matching a query.
	 *
	 * @param array $args As query().
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		list( $where, $params ) = self::where( $args );
		$table                  = self::table();
		$sql                    = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Built from code and placeholders only.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Counts per status.
	 *
	 * @return array status => count.
	 */
	public static function totals() {
		global $wpdb;
		$table = self::table();
		$out   = array_fill_keys( self::STATUSES, 0 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from code.
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ) as $row ) {
			if ( isset( $out[ $row['status'] ] ) ) {
				$out[ $row['status'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Record a real rank (Search Console average position or a provider
	 * SERP check). Moves the entry to "ranking" in the top 100, and back to
	 * "targeted" when it drops out. Archived entries keep their status.
	 *
	 * @param int        $id       Id.
	 * @param float|null $position Position (null: not found in the results).
	 * @param string     $source   search_console or a provider id.
	 * @return bool
	 */
	public static function record_rank( $id, $position, $source ) {
		$entry = self::get( $id );
		if ( ! $entry || ! in_array( sanitize_key( $source ), self::REAL_SOURCES, true ) ) {
			return false;
		}
		$fields = array(
			'rank'        => null === $position ? null : (int) round( (float) $position ),
			'rank_source' => sanitize_key( $source ),
			'rank_at'     => current_time( 'mysql', true ),
		);
		if ( 'archived' !== $entry['status'] ) {
			$ranks = null !== $fields['rank'] && $fields['rank'] >= 1 && $fields['rank'] <= self::RANKING_TOP;
			if ( $ranks ) {
				$fields['status'] = 'ranking';
			} elseif ( 'ranking' === $entry['status'] ) {
				$fields['status'] = 'targeted';
			}
		}
		$done = self::update( $id, $fields );
		if ( ! is_wp_error( $done ) && isset( $fields['status'] ) && $fields['status'] !== $entry['status'] ) {
			/**
			 * Fires when real rank data moves a Vault keyword in or out of "ranking".
			 *
			 * @param int    $id     Id.
			 * @param string $before Previous status.
			 * @param string $after  New status.
			 * @param array  $fields Rank fields.
			 */
			do_action( 'qcld_wpbot_core/vault_rank_changed', (int) $id, $entry['status'], $fields['status'], $fields );
		}
		return ! is_wp_error( $done );
	}

	/**
	 * Normalise a keyword: lower case, single spaces, no control characters.
	 *
	 * @param mixed $keyword Raw.
	 * @return string
	 */
	public static function normalise( $keyword ) {
		$keyword = wp_strip_all_tags( (string) $keyword );
		$keyword = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $keyword );
		$keyword = trim( (string) preg_replace( '/\s+/u', ' ', (string) $keyword ) );
		$keyword = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword, 'UTF-8' ) : strtolower( $keyword );
		return function_exists( 'mb_substr' ) ? mb_substr( $keyword, 0, 191, 'UTF-8' ) : substr( $keyword, 0, 191 );
	}

	/**
	 * Plan reference in the shared keyword map.
	 *
	 * @param int $id Id.
	 * @return string
	 */
	public static function plan_ref( $id ) {
		return 'vault-' . (int) $id;
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Seo_Schema::table( Seo_Schema::VAULT );
	}

	/**
	 * Validate and convert caller fields to columns. Numbers without a real
	 * source are dropped (honest-number rule).
	 *
	 * @param array $fields Fields.
	 * @return array|WP_Error Columns.
	 */
	public static function clean( array $fields ) {
		$row = array();
		foreach ( $fields as $key => $value ) {
			if ( ! in_array( $key, self::FIELDS, true ) ) {
				continue;
			}
			switch ( $key ) {
				case 'status':
					$value = sanitize_key( (string) $value );
					if ( ! in_array( $value, self::STATUSES, true ) ) {
						return new WP_Error( 'qcld_wpbot_core_vault_status', __( 'Unknown status.', 'chatbot' ), array( 'status' => 400 ) );
					}
					$row['status'] = $value;
					break;
				case 'intent':
					$value         = sanitize_key( (string) $value );
					$row['intent'] = in_array( $value, self::INTENTS, true ) ? $value : '';
					break;
				case 'demand':
				case 'difficulty_label':
					$value       = sanitize_key( (string) $value );
					$row[ $key ] = in_array( $value, self::LEVELS, true ) ? $value : '';
					break;
				case 'topic_id':
				case 'post_id':
					$row[ $key ] = absint( $value );
					break;
				case 'source':
					$value         = sanitize_key( (string) $value );
					$row['source'] = in_array( $value, self::SOURCES, true ) ? $value : 'manual';
					break;
				case 'notes':
					$row['notes'] = substr( sanitize_textarea_field( (string) $value ), 0, 2000 );
					break;
				case 'module':
					$row['module'] = substr( sanitize_key( (string) $value ), 0, 40 );
					break;
				case 'context':
					$row['context'] = wp_json_encode( self::clean_context( is_array( $value ) ? $value : array() ) );
					break;
				case 'rank_at':
					$row['rank_at'] = null === $value ? null : gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) ? strtotime( (string) $value ) : time() );
					break;
				case 'trend':
					$value        = sanitize_key( (string) $value );
					$row['trend'] = in_array( $value, self::TRENDS, true ) ? $value : '';
					break;
				case 'competition':
					$value              = sanitize_key( (string) $value );
					$row['competition'] = in_array( $value, self::LEVELS, true ) ? $value : '';
					break;
				case 'volume_source':
				case 'difficulty_source':
				case 'rank_source':
					$row[ $key ] = substr( sanitize_key( (string) $value ), 0, 30 );
					break;
			}
		}

		// Numbers are kept only with a real source next to them.
		$pairs = array(
			'volume'     => 'volume_source',
			'cpc'        => 'volume_source',
			'bid_low'    => 'volume_source',
			'bid_high'   => 'volume_source',
			'difficulty' => 'difficulty_source',
			'rank'       => 'rank_source',
		);
		foreach ( $pairs as $field => $source_field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$source = isset( $row[ $source_field ] ) ? $row[ $source_field ] : '';
			$column = 'rank' === $field ? 'rank_position' : $field;
			if ( null === $fields[ $field ] ) {
				$row[ $column ] = null;
				continue;
			}
			if ( ! in_array( $source, self::REAL_SOURCES, true ) || ! is_numeric( $fields[ $field ] ) ) {
				continue;
			}
			$number = (float) $fields[ $field ];
			if ( in_array( $field, array( 'cpc', 'bid_low', 'bid_high' ), true ) ) {
				$row[ $column ] = round( max( 0, $number ), 2 );
			} elseif ( 'difficulty' === $field ) {
				$row[ $column ] = (int) max( 0, min( 100, $number ) );
			} else {
				$row[ $column ] = (int) max( 0, $number );
			}
		}
		return $row;
	}

	/**
	 * Row for callers: decoded context and the public `rank` name.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$context = json_decode( (string) $row['context'], true );
		return array(
			'id'                => (int) $row['id'],
			'keyword'           => (string) $row['keyword'],
			'language'          => (string) $row['language'],
			'country'           => (string) $row['country'],
			'status'            => (string) $row['status'],
			'intent'            => (string) $row['intent'],
			'topic_id'          => (int) $row['topic_id'],
			'post_id'           => (int) $row['post_id'],
			'demand'            => (string) $row['demand'],
			'difficulty_label'  => (string) $row['difficulty_label'],
			'volume'            => null === $row['volume'] ? null : (int) $row['volume'],
			'volume_source'     => (string) $row['volume_source'],
			'cpc'               => null === $row['cpc'] ? null : (float) $row['cpc'],
			'difficulty'        => null === $row['difficulty'] ? null : (int) $row['difficulty'],
			'difficulty_source' => (string) $row['difficulty_source'],
			'rank'              => null === $row['rank_position'] ? null : (int) $row['rank_position'],
			'rank_source'       => (string) $row['rank_source'],
			'rank_at'           => (string) $row['rank_at'],
			'notes'             => (string) $row['notes'],
			'source'            => (string) $row['source'],
			'context'           => is_array( $context ) ? $context : array(),
			'module'            => (string) $row['module'],
			'trend'             => isset( $row['trend'] ) ? (string) $row['trend'] : '',
			'competition'       => isset( $row['competition'] ) ? (string) $row['competition'] : '',
			'bid_low'           => isset( $row['bid_low'] ) && null !== $row['bid_low'] ? (float) $row['bid_low'] : null,
			'bid_high'          => isset( $row['bid_high'] ) && null !== $row['bid_high'] ? (float) $row['bid_high'] : null,
			'created_at'        => (string) $row['created_at'],
			'updated_at'        => (string) $row['updated_at'],
		);
	}

	/**
	 * Clean research context (supporting keywords, questions, competitor notes).
	 *
	 * @param array $context Raw.
	 * @return array
	 */
	private static function clean_context( array $context ) {
		$list = static function ( $value, $max ) {
			return array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', (array) $value ) ), 'strlen' ) ), 0, $max );
		};
		return array(
			'supporting'       => $list( isset( $context['supporting'] ) ? $context['supporting'] : array(), 10 ),
			'questions'        => $list( isset( $context['questions'] ) ? $context['questions'] : array(), 10 ),
			'competitor_notes' => substr( sanitize_textarea_field( isset( $context['competitor_notes'] ) ? (string) $context['competitor_notes'] : '' ), 0, 1000 ),
			'reason'           => substr( sanitize_text_field( isset( $context['reason'] ) ? (string) $context['reason'] : '' ), 0, 300 ),
		);
	}

	/**
	 * WHERE clause and parameters for query() and count().
	 *
	 * @param array $args Arguments.
	 * @return array array( sql, params ).
	 */
	private static function where( array $args ) {
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$statuses = array_values( array_intersect( array_map( 'sanitize_key', (array) $args['status'] ), self::STATUSES ) );
			if ( $statuses ) {
				$where[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
				$params  = array_merge( $params, $statuses );
			}
		}
		// Round 2 (R5): trend, competition and a lowest volume.
		foreach ( array( 'source', 'intent', 'language', 'country', 'trend', 'competition' ) as $field ) {
			if ( isset( $args[ $field ] ) && '' !== (string) $args[ $field ] ) {
				$where[]  = "{$field} = %s";
				$params[] = sanitize_key( (string) $args[ $field ] );
			}
		}
		if ( isset( $args['min_volume'] ) && '' !== (string) $args['min_volume'] ) {
			$where[]  = 'volume >= %d';
			$params[] = absint( $args['min_volume'] );
		}
		if ( ! empty( $args['topic_id'] ) ) {
			$where[]  = 'topic_id = %d';
			$params[] = absint( $args['topic_id'] );
		}
		if ( isset( $args['post_id'] ) && '' !== (string) $args['post_id'] ) {
			$where[]  = 'post_id = %d';
			$params[] = absint( $args['post_id'] );
		}
		if ( isset( $args['has_page'] ) && null !== $args['has_page'] ) {
			$where[] = $args['has_page'] ? 'post_id > 0' : 'post_id = 0';
		}
		if ( ! empty( $args['search'] ) ) {
			global $wpdb;
			$where[]  = 'keyword LIKE %s';
			$params[] = '%' . $wpdb->esc_like( self::normalise( $args['search'] ) ) . '%';
		}
		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Keep the shared keyword map in step: a targeted keyword without a
	 * page is a planned entry; anything else is not.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	private static function sync_map( $id ) {
		$entry = self::get( $id );
		if ( ! $entry ) {
			return;
		}
		if ( 'targeted' === $entry['status'] && ! $entry['post_id'] ) {
			Keyword_Map::set_planned(
				self::plan_ref( $id ),
				$entry['keyword'],
				array(
					'topic_id' => $entry['topic_id'],
					'module'   => $entry['module'] ? $entry['module'] : 'seo',
				)
			);
		} else {
			Keyword_Map::remove_planned( self::plan_ref( $id ) );
		}
	}

	/**
	 * Order of statuses (a merge never moves an entry back).
	 *
	 * @param string $status Status.
	 * @return int
	 */
	private static function rank_of( $status ) {
		$order = array(
			'researched' => 0,
			'targeted'   => 1,
			'ranking'    => 2,
			'archived'   => 3,
		);
		return isset( $order[ $status ] ) ? $order[ $status ] : 0;
	}

	/**
	 * Two-letter style code (language or country), lower case.
	 *
	 * @param mixed $code Raw.
	 * @return string
	 */
	private static function code( $code ) {
		return substr( (string) preg_replace( '/[^a-z-]/', '', strtolower( (string) $code ) ), 0, 8 );
	}

	/**
	 * Fire the change action.
	 *
	 * @param int    $id     Id.
	 * @param string $action add, update or delete.
	 * @param array  $fields Columns written.
	 * @return void
	 */
	private static function changed( $id, $action, array $fields ) {
		/**
		 * Fires after a Vault entry is added, changed or deleted.
		 *
		 * @param int    $id     Id.
		 * @param string $action add, update or delete.
		 * @param array  $fields Columns written.
		 */
		do_action( 'qcld_wpbot_core/vault_changed', (int) $id, $action, $fields );
	}
}
