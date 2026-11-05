<?php
/**
 * Shared keyword map (brief 7.10): which page targets which search. Each
 * page has one main query and a few supporting queries, each with a source
 * (ai, manual, search_console, title) and a locked flag; manual or locked
 * entries are never overwritten by automatic updates. Planned pages (saved
 * ideas, calendar items) have no post yet and carry a plan reference.
 *
 * Read API for every module: main_query(), posts_for_query(), entries(),
 * unmapped_posts(). Writes: set_post(), set_topic(), set_planned().
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Map;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword map.
 */
final class Keyword_Map {

	const SOURCES = array( 'ai', 'manual', 'search_console', 'title' );

	/**
	 * Normalise a search: lower case, letters and digits, single spaces.
	 *
	 * @param string $query Query.
	 * @return string
	 */
	public static function normalise( $query ) {
		$query = function_exists( 'mb_strtolower' ) ? mb_strtolower( wp_strip_all_tags( (string) $query ), 'UTF-8' ) : strtolower( wp_strip_all_tags( (string) $query ) );
		$query = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $query ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 191 ) : substr( $query, 0, 191 );
	}

	/**
	 * Set a page's main query (and supporting queries and topic).
	 *
	 * A manual or locked main query is kept unless this change is manual.
	 *
	 * @param int    $post_id Post.
	 * @param string $query   Main query.
	 * @param array  $args    source, topic (name), topic_id, supporting (string[]), module, locked.
	 * @return bool Whether the map changed.
	 */
	public static function set_post( $post_id, $query, array $args = array() ) {
		$post_id = absint( $post_id );
		$query   = self::normalise( $query );
		if ( ! $post_id || '' === $query ) {
			return false;
		}
		$source  = isset( $args['source'] ) && in_array( $args['source'], self::SOURCES, true ) ? $args['source'] : 'ai';
		$current = self::main_query( $post_id );
		if ( $current && ( $current['locked'] || 'manual' === $current['source'] ) && 'manual' !== $source ) {
			return false;
		}
		$topic_id = isset( $args['topic_id'] ) ? absint( $args['topic_id'] ) : 0;
		if ( ! $topic_id && ! empty( $args['topic'] ) ) {
			$topic_id = Topic_Map::ensure( (string) $args['topic'] );
		}
		self::delete_entries( array( 'post_id' => $post_id ) );
		$module = isset( $args['module'] ) ? sanitize_key( $args['module'] ) : '';
		self::insert( $post_id, '', $query, 'main', $topic_id, $source, ! empty( $args['locked'] ), $module );
		foreach ( array_slice( isset( $args['supporting'] ) ? (array) $args['supporting'] : array(), 0, 5 ) as $supporting ) {
			$supporting = self::normalise( $supporting );
			if ( '' !== $supporting && $supporting !== $query ) {
				self::insert( $post_id, '', $supporting, 'supporting', $topic_id, $source, false, $module );
			}
		}
		do_action( 'qcld_wpbot_core/keyword_map_changed', $post_id, '' );
		return true;
	}

	/**
	 * Record a planned page (a saved idea or a calendar item).
	 *
	 * @param string $ref    Plan reference, unique per module (for example writer-idea-12).
	 * @param string $query  Query it will target.
	 * @param array  $args   topic (name), topic_id, module.
	 * @return bool
	 */
	public static function set_planned( $ref, $query, array $args = array() ) {
		$ref   = substr( sanitize_key( $ref ), 0, 100 );
		$query = self::normalise( $query );
		if ( '' === $ref || '' === $query ) {
			return false;
		}
		$topic_id = isset( $args['topic_id'] ) ? absint( $args['topic_id'] ) : 0;
		if ( ! $topic_id && ! empty( $args['topic'] ) ) {
			$topic_id = Topic_Map::ensure( (string) $args['topic'] );
		}
		self::delete_entries( array( 'plan_ref' => $ref ) );
		self::insert( 0, $ref, $query, 'main', $topic_id, 'ai', false, isset( $args['module'] ) ? sanitize_key( $args['module'] ) : '' );
		do_action( 'qcld_wpbot_core/keyword_map_changed', 0, $ref );
		return true;
	}

	/**
	 * Remove a planned page (the idea was dismissed or became a post).
	 *
	 * @param string $ref Plan reference.
	 * @return void
	 */
	public static function remove_planned( $ref ) {
		self::delete_entries( array( 'plan_ref' => substr( sanitize_key( $ref ), 0, 100 ) ) );
	}

	/**
	 * A planned page became a post: move its entry to the post (unless the
	 * post already has a main query).
	 *
	 * @param string $ref     Plan reference.
	 * @param int    $post_id Post.
	 * @return void
	 */
	public static function attach_planned( $ref, $post_id ) {
		$entries = self::entries( array( 'plan_ref' => substr( sanitize_key( $ref ), 0, 100 ) ) );
		self::remove_planned( $ref );
		if ( $entries && ! self::main_query( $post_id ) ) {
			self::set_post(
				$post_id,
				$entries[0]['query'],
				array(
					'topic_id' => $entries[0]['topic_id'],
					'module'   => $entries[0]['module'],
				)
			);
		}
	}

	/**
	 * Forget a post (deleted).
	 *
	 * @param int $post_id Post.
	 * @return void
	 */
	public static function forget_post( $post_id ) {
		self::delete_entries( array( 'post_id' => absint( $post_id ) ) );
	}

	/**
	 * Main query of a post, or null.
	 *
	 * @param int $post_id Post.
	 * @return array|null Entry.
	 */
	public static function main_query( $post_id ) {
		if ( ! $post_id ) {
			return null;
		}
		$entries = self::entries(
			array(
				'post_id' => absint( $post_id ),
				'role'    => 'main',
			)
		);
		return $entries ? $entries[0] : null;
	}

	/**
	 * Pages and planned pages whose main query is this query.
	 *
	 * @param string $query   Query.
	 * @param array  $exclude post_ids and plan_refs to leave out.
	 * @return array[] Entries.
	 */
	public static function posts_for_query( $query, array $exclude = array() ) {
		$query = self::normalise( $query );
		if ( '' === $query ) {
			return array();
		}
		$out = array();
		foreach ( self::entries(
			array(
				'query_hash' => md5( $query ),
				'role'       => 'main',
			)
		) as $entry ) {
			// Planned pages have post id 0: a 0 in post_ids never excludes them.
			if ( ( $entry['post_id'] && in_array( $entry['post_id'], isset( $exclude['post_ids'] ) ? array_map( 'intval', (array) $exclude['post_ids'] ) : array(), true ) ) || ( '' !== $entry['plan_ref'] && in_array( $entry['plan_ref'], isset( $exclude['plan_refs'] ) ? (array) $exclude['plan_refs'] : array(), true ) ) ) {
				continue;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Entries matching simple conditions.
	 *
	 * @param array $where post_id, plan_ref, query_hash, role, topic_id.
	 * @param int   $limit Max rows.
	 * @return array[] id, post_id, plan_ref, query, role, topic_id, source, locked, module, status, title.
	 */
	public static function entries( array $where = array(), $limit = 500 ) {
		global $wpdb;
		$table = Schema::table( Schema::KEYWORDS );
		$sql   = "SELECT * FROM {$table} WHERE 1=1";
		foreach ( array(
			'post_id'    => '%d',
			'plan_ref'   => '%s',
			'query_hash' => '%s',
			'role'       => '%s',
			'topic_id'   => '%d',
		) as $field => $format ) {
			if ( array_key_exists( $field, $where ) ) {
				$sql .= $wpdb->prepare( " AND {$field} = {$format}", $where[ $field ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Field names and formats (%d or %s) from the list above.
			}
		}
		$sql .= $wpdb->prepare( ' ORDER BY role ASC, id ASC LIMIT %d', max( 1, (int) $limit ) );
		$out  = array();
		foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepare() fragments.
			$post_id = (int) $row['post_id'];
			$status  = 'planned';
			$title   = '';
			if ( $post_id ) {
				$status = (string) get_post_status( $post_id );
				$title  = $status ? html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) : '';
				if ( ! $status || 'trash' === $status ) {
					continue;
				}
			}
			$out[] = array(
				'id'       => (int) $row['id'],
				'post_id'  => $post_id,
				'plan_ref' => (string) $row['plan_ref'],
				'query'    => (string) $row['query'],
				'role'     => (string) $row['role'],
				'topic_id' => (int) $row['topic_id'],
				'source'   => (string) $row['source'],
				'locked'   => (bool) $row['locked'],
				'module'   => (string) $row['module'],
				'status'   => $status,
				'title'    => $title,
			);
		}
		return $out;
	}

	/**
	 * Move a page (all its entries) to a topic.
	 *
	 * Automatic moves never change a page whose main entry is manual or
	 * locked; a manual move marks the main entry manual, so a later
	 * automatic regroup keeps the owner's choice.
	 *
	 * @param int  $post_id  Post.
	 * @param int  $topic_id Topic (0 = no topic).
	 * @param bool $manual   Owner change.
	 * @return bool Whether the page moved.
	 */
	public static function set_topic( $post_id, $topic_id, $manual = false ) {
		global $wpdb;
		$main = self::main_query( $post_id );
		if ( ! $main || ( ! $manual && ( $main['locked'] || 'manual' === $main['source'] ) ) ) {
			return false;
		}
		$table = Schema::table( Schema::KEYWORDS );
		$now   = current_time( 'mysql', true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			$table,
			array(
				'topic_id'   => absint( $topic_id ),
				'updated_at' => $now,
			),
			array( 'post_id' => absint( $post_id ) )
		);
		if ( $manual ) {
			$wpdb->update( $table, array( 'source' => 'manual' ), array( 'id' => (int) $main['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		}
		do_action( 'qcld_wpbot_core/keyword_map_changed', absint( $post_id ), '' );
		return true;
	}

	/**
	 * Published pages that have no main query yet (for a backfill).
	 *
	 * @param string[] $types Post types.
	 * @param int      $limit Max ids.
	 * @param int      $after Only ids above this (paging).
	 * @return int[] Post ids, ascending.
	 */
	public static function unmapped_posts( array $types, $limit = 50, $after = 0 ) {
		global $wpdb;
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( array() === $types ) {
			return array();
		}
		$table = Schema::table( Schema::KEYWORDS );
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args  = array_merge( $types, array( (int) $after, max( 1, (int) $limit ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own table name from code; one %s per type.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$table} k ON k.post_id = p.ID AND k.role = 'main' WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND k.id IS NULL AND p.ID > %d ORDER BY p.ID ASC LIMIT %d", $args ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Insert one entry.
	 *
	 * @param int    $post_id  Post (0 for planned).
	 * @param string $ref      Plan reference.
	 * @param string $query    Normalised query.
	 * @param string $role     main or supporting.
	 * @param int    $topic_id Topic.
	 * @param string $source   Source.
	 * @param bool   $locked   Locked.
	 * @param string $module   Module id.
	 * @return void
	 */
	private static function insert( $post_id, $ref, $query, $role, $topic_id, $source, $locked, $module ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			Schema::table( Schema::KEYWORDS ),
			array(
				'post_id'    => (int) $post_id,
				'plan_ref'   => (string) $ref,
				'query'      => $query,
				'query_hash' => md5( $query ),
				'role'       => 'supporting' === $role ? 'supporting' : 'main',
				'topic_id'   => (int) $topic_id,
				'source'     => $source,
				'locked'     => $locked ? 1 : 0,
				'module'     => substr( (string) $module, 0, 40 ),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
	}

	/**
	 * Delete entries matching post_id or plan_ref.
	 *
	 * @param array $where post_id or plan_ref.
	 * @return void
	 */
	private static function delete_entries( array $where ) {
		global $wpdb;
		$table = Schema::table( Schema::KEYWORDS );
		if ( isset( $where['post_id'] ) && $where['post_id'] ) {
			$wpdb->delete( $table, array( 'post_id' => (int) $where['post_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		} elseif ( ! empty( $where['plan_ref'] ) ) {
			$wpdb->delete( $table, array( 'plan_ref' => (string) $where['plan_ref'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		}
	}
}
