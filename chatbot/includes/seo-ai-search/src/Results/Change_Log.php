<?php
/**
 * Change log: every change the module makes (fixes, auto meta, links,
 * redirects, schema) with its before and after values, who made it, and
 * later its results. Meta and link changes can be undone.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Results;

use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Change log store.
 */
final class Change_Log {

	/**
	 * Record a change.
	 *
	 * @param array $change post_id, kind (meta|content|link|redirect|schema|page), field, before, after, source, plugin, group_ref.
	 * @return int Change id.
	 */
	public static function record( array $change ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			Schema::table( Schema::CHANGES ),
			array(
				'post_id'      => isset( $change['post_id'] ) ? (int) $change['post_id'] : 0,
				'kind'         => isset( $change['kind'] ) ? sanitize_key( $change['kind'] ) : 'meta',
				'field'        => isset( $change['field'] ) ? sanitize_key( $change['field'] ) : '',
				'before_value' => isset( $change['before'] ) ? (string) $change['before'] : '',
				'after_value'  => isset( $change['after'] ) ? (string) $change['after'] : '',
				'source'       => isset( $change['source'] ) ? sanitize_key( $change['source'] ) : '',
				'plugin'       => isset( $change['plugin'] ) ? sanitize_key( $change['plugin'] ) : '',
				'group_ref'    => isset( $change['group_ref'] ) ? substr( sanitize_key( $change['group_ref'] ), 0, 64 ) : '',
				'user_id'      => get_current_user_id(),
				'created_at'   => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		// S14: the page's on-page score before the change, so Results can show a before-and-after
		// even without Search Console.
		if ( $id && ! empty( $change['post_id'] ) && in_array( isset( $change['kind'] ) ? $change['kind'] : 'meta', array( 'meta', 'content', 'link', 'schema' ), true ) ) {
			$stored = \QuantumCloud\SeoAiSearch\Onpage\Audit::stored( (int) $change['post_id'] );
			if ( $stored && isset( $stored['score'] ) ) {
				self::set_results( $id, array( 'onpage' => array( 'before' => (int) $stored['score'] ) ) );
			}
		}
		/**
		 * A fix or change was saved.
		 *
		 * @param int   $post_id Post id.
		 * @param array $fix     kind, field, before, after, change_id.
		 */
		do_action( 'qcld_wpbot_seo/fix_applied', isset( $change['post_id'] ) ? (int) $change['post_id'] : 0, array_merge( $change, array( 'change_id' => $id ) ) );
		return $id;
	}

	/**
	 * One change, or null.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::CHANGES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Changes, newest first.
	 *
	 * @param array $args post_id, kind, limit, offset.
	 * @return array items, total.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = Schema::table( Schema::CHANGES );
		$where = '1=1';
		if ( ! empty( $args['post_id'] ) ) {
			$where .= $wpdb->prepare( ' AND post_id = %d', (int) $args['post_id'] );
		}
		if ( ! empty( $args['kind'] ) ) {
			$where .= $wpdb->prepare( ' AND kind = %s', sanitize_key( $args['kind'] ) );
		}
		$limit  = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant; $where built with prepare().
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		// phpcs:enable
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Undo a meta change (puts the before value back) or a link change.
	 *
	 * @param int $id Change id.
	 * @return true|WP_Error
	 */
	public static function undo( $id ) {
		global $wpdb;
		$change = self::get( $id );
		if ( ! $change ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This change no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( '' !== $change['undone_at'] ) {
			return new WP_Error( 'qcld_wpbot_seo_undone', __( 'This change was already undone.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $change['post_id'] ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( 'meta' === $change['kind'] ) {
			$current = \QuantumCloud\SeoAiSearch\Seo\Seo_Bridge::get( $change['post_id'], $change['field'] );
			$current = is_bool( $current ) ? ( $current ? '1' : '' ) : (string) $current;
			if ( $current !== $change['after'] ) {
				return new WP_Error( 'qcld_wpbot_seo_changed_since', __( 'This field was changed again since; undo would overwrite that. Edit it by hand instead.', 'chatbot' ), array( 'status' => 409 ) );
			}
			\QuantumCloud\SeoAiSearch\Seo\Seo_Bridge::put( $change['post_id'], $change['field'], 'noindex' === $change['field'] ? '1' === $change['before'] : $change['before'] );
		} elseif ( 'content' === $change['kind'] ) {
			// Page Audit content fixes (alt text, FAQ, introduction): put the old content back if nothing changed since.
			if ( (string) get_post_field( 'post_content', $change['post_id'] ) !== $change['after'] ) {
				return new WP_Error( 'qcld_wpbot_seo_changed_since', __( 'The page was edited again since; undo would overwrite that. Edit it by hand instead.', 'chatbot' ), array( 'status' => 409 ) );
			}
			$done = wp_update_post(
				array(
					'ID'           => (int) $change['post_id'],
					'post_content' => wp_slash( $change['before'] ),
				),
				true
			);
			if ( is_wp_error( $done ) ) {
				return $done;
			}
		} elseif ( 'link' === $change['kind'] ) {
			$undone = \QuantumCloud\SeoAiSearch\Onpage\Link_Suggestions::remove_link( $change );
			if ( is_wp_error( $undone ) ) {
				return $undone;
			}
		} else {
			return new WP_Error( 'qcld_wpbot_seo_no_undo', __( 'This kind of change cannot be undone here.', 'chatbot' ), array( 'status' => 400 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::CHANGES ), array( 'undone_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
		return true;
	}

	/**
	 * Save results for a change (S6).
	 *
	 * @param int   $id      Change id.
	 * @param array $results Results by day mark.
	 * @return void
	 */
	public static function set_results( $id, array $results ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::CHANGES ), array( 'results' => wp_json_encode( $results ) ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Row for screens.
	 *
	 * @param array $row Table row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$results = json_decode( (string) $row['results'], true );
		if ( is_array( $results ) && ! empty( $row['undone_at'] ) ) {
			// Undone changes are never measured (the on-page snapshot is not a result).
			unset( $results['onpage'] );
		}
		$post_id = (int) $row['post_id'];
		return array(
			'id'         => (int) $row['id'],
			'post_id'    => $post_id,
			'title'      => $post_id ? (string) get_the_title( $post_id ) : '',
			'kind'       => (string) $row['kind'],
			'field'      => (string) $row['field'],
			'before'     => (string) $row['before_value'],
			'after'      => (string) $row['after_value'],
			'source'     => (string) $row['source'],
			'plugin'     => (string) $row['plugin'],
			'group_ref'  => (string) $row['group_ref'],
			'user_id'    => (int) $row['user_id'],
			'created_at' => (string) $row['created_at'],
			'undone_at'  => (string) $row['undone_at'],
			'results'    => is_array( $results ) ? $results : array(),
		);
	}
}
