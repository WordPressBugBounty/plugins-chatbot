<?php
/**
 * Social posts and their targets (one per account).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Networks\Draft;
use QuantumCloud\Social\Storage\Schema;
use QuantumCloud\Social\Support\Log;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Post storage.
 */
final class Post_Store {

	/**
	 * Post statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'draft', 'scheduled', 'publishing', 'published', 'partial', 'failed', 'missed', 'skipped' );

	/**
	 * Create a post with one target per account.
	 *
	 * @param array $post    title, brand_id, schedule_at (GMT Y-m-d H:i:s or '' for now), status (draft|scheduled), source_type, source_id, meta.
	 * @param array $targets Each: account_id, network, draft (Draft).
	 * @return int|WP_Error Post id.
	 */
	public static function create( array $post, array $targets ) {
		global $wpdb;
		if ( array() === $targets ) {
			return new WP_Error( 'qcld_wpbot_social_no_targets', __( 'Choose at least one account.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$now      = current_time( 'mysql', true );
		$status   = isset( $post['status'] ) && 'draft' === $post['status'] ? 'draft' : 'scheduled';
		$schedule = isset( $post['schedule_at'] ) && '' !== $post['schedule_at'] ? (string) $post['schedule_at'] : $now;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'posts' ),
			array(
				'brand_id'    => isset( $post['brand_id'] ) ? absint( $post['brand_id'] ) : 0,
				'title'       => substr( sanitize_text_field( isset( $post['title'] ) ? $post['title'] : '' ), 0, 191 ),
				'source_type' => sanitize_key( isset( $post['source_type'] ) ? $post['source_type'] : '' ),
				'source_id'   => isset( $post['source_id'] ) ? absint( $post['source_id'] ) : 0,
				'status'      => $status,
				'schedule_at' => $schedule,
				'author_id'   => get_current_user_id(),
				'meta'        => wp_json_encode( isset( $post['meta'] ) ? (array) $post['meta'] : array() ),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$post_id = (int) $wpdb->insert_id;
		if ( ! $post_id ) {
			return new WP_Error( 'qcld_wpbot_social_store', __( 'The post could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
		}
		foreach ( $targets as $target ) {
			$draft = $target['draft'] instanceof Draft ? $target['draft'] : Draft::from_array( (array) $target['draft'] );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				Schema::table( 'post_targets' ),
				array(
					'post_id'         => $post_id,
					'account_id'      => absint( $target['account_id'] ),
					'network'         => sanitize_key( $target['network'] ),
					'draft'           => wp_json_encode( $draft->to_array() ),
					'state'           => 'draft' === $status ? 'draft' : 'scheduled',
					'schedule_at'     => $schedule,
					'next_attempt_at' => $schedule,
					'attempts'        => 0,
					'idempotency_key' => hash( 'sha256', $post_id . '|' . absint( $target['account_id'] ) . '|' . wp_generate_password( 20, false ) ),
				)
			);
		}
		return $post_id;
	}

	/**
	 * One post with its targets.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$posts = Schema::table( 'posts' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$posts} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( ! $row ) {
			return null;
		}
		$row            = self::shape( $row );
		$row['targets'] = Target_Store::for_post( $row['id'] );
		return $row;
	}

	/**
	 * Posts for the Posts and Calendar screens.
	 *
	 * @param array $args status, brand_id, brands (int[]|null: only these brands and posts with no brand), from, to (GMT), search, limit, offset.
	 * @return array{items:array, total:int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$posts  = Schema::table( 'posts' );
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['brand_id'] ) ) {
			$where[]  = 'brand_id = %d';
			$params[] = absint( $args['brand_id'] );
		}
		if ( isset( $args['brands'] ) && is_array( $args['brands'] ) ) {
			$ids     = array_values( array_unique( array_merge( array( 0 ), array_map( 'absint', $args['brands'] ) ) ) );
			$where[] = 'brand_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params  = array_merge( $params, $ids );
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'schedule_at >= %s';
			$params[] = (string) $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'schedule_at < %s';
			$params[] = (string) $args['to'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}
		$limit     = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 20;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$sql_where = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$posts} WHERE {$sql_where} AND %d = %d", array_merge( $params, array( 1, 1 ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery -- Own table; placeholders and values built together above.
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$posts} WHERE {$sql_where} ORDER BY schedule_at DESC, id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $limit, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery -- Own table; placeholders and values built together above.
		$items     = array();
		foreach ( (array) $rows as $row ) {
			$row            = self::shape( $row );
			$row['targets'] = Target_Store::for_post( $row['id'] );
			$items[]        = $row;
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Recompute a post's status from its targets.
	 *
	 * @param int $post_id Post id.
	 * @return string New status.
	 */
	public static function refresh_status( $post_id ) {
		global $wpdb;
		$states = array_count_values( array_column( Target_Store::for_post( $post_id ), 'state' ) );
		$total  = array_sum( $states );
		$done   = ( isset( $states['done'] ) ? $states['done'] : 0 ) + ( isset( $states['skipped'] ) ? $states['skipped'] : 0 );
		$failed = ( isset( $states['failed'] ) ? $states['failed'] : 0 ) + ( isset( $states['check'] ) ? $states['check'] : 0 );
		if ( $total > 0 && $done === $total ) {
			// Every target skipped by the owner: nothing went out.
			$status = isset( $states['done'] ) ? 'published' : 'skipped';
		} elseif ( isset( $states['missed'] ) ) {
			$status = 'missed';
		} elseif ( $total > 0 && $failed === $total ) {
			$status = 'failed';
		} elseif ( $done + $failed === $total ) {
			$status = 'partial';
		} elseif ( isset( $states['draft'] ) && $states['draft'] === $total ) {
			$status = 'draft';
		} elseif ( $done > 0 || isset( $states['sending'] ) || isset( $states['queued'] ) || isset( $states['manual'] ) ) {
			$status = 'publishing';
		} else {
			$status = 'scheduled';
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'posts' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $post_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return $status;
	}

	/**
	 * Delete a post that has not gone out yet. Published targets stay on the
	 * networks; this removes only WPBot's record.
	 *
	 * @param int $post_id Post id.
	 * @return bool|WP_Error
	 */
	public static function delete( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$targets = Schema::table( 'post_targets' );
		foreach ( Target_Store::for_post( $post_id ) as $target ) {
			if ( in_array( $target['state'], array( 'sending', 'queued' ), true ) ) {
				return new WP_Error( 'qcld_wpbot_social_busy', __( 'This post is being published right now. Try again in a minute.', 'chatbot' ), array( 'status' => 409 ) );
			}
			if ( in_array( $target['state'], array( 'unknown', 'reconciling', 'check' ), true ) ) {
				// QA #54: it may already be live; deleting would lose the only trace.
				return new WP_Error( 'qcld_wpbot_social_unsure', __( 'WPBot is still finding out whether this post went out. Confirm it on the Posts screen ("It went out" or "Fix & retry") before deleting it.', 'chatbot' ), array( 'status' => 409 ) );
			}
		}
		// Conditional delete: a tick that claims a target between the check above
		// and here makes this delete nothing, and the post is kept (QA #54).
		$deletable = "'draft','scheduled','retry','failed','done','skipped','manual','missed'";
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$targets} WHERE post_id = %d AND state IN ({$deletable})", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; states from code.
		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$targets} WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $left > 0 ) {
			return new WP_Error( 'qcld_wpbot_social_busy', __( 'This post is being published right now. Try again in a minute.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$deleted = false !== $wpdb->delete( Schema::table( 'posts' ), array( 'id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $deleted ) {
			Log::add( 'info', 'post_deleted', sprintf( 'Post %d deleted', $post_id ), array( 'post' => $post_id ) );
		}
		return $deleted;
	}

	/**
	 * Typed row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$row['id']        = (int) $row['id'];
		$row['brand_id']  = (int) $row['brand_id'];
		$row['source_id'] = (int) $row['source_id'];
		$row['author_id'] = (int) $row['author_id'];
		$meta             = json_decode( (string) $row['meta'], true );
		$row['meta']      = is_array( $meta ) ? $meta : array();
		return $row;
	}
}
