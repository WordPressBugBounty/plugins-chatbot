<?php
/**
 * The Approval inbox: everything automation wants to do that waits for the
 * owner (new lists, uncertain placements, journeys to switch on, drafts).
 *
 * The core module stores and decides suggestions. What "approve" does is
 * up to the feature that made the suggestion: it handles the filter
 * qcld_wpbot_marketing/suggestion/approve for its kind.
 *
 * A dismissed suggestion with a dedupe key is never suggested again.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Autopilot;

use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Suggestions.
 */
final class Suggestions {

	const STATUSES = array( 'pending', 'approved', 'dismissed', 'expired', 'info' );

	/**
	 * Add a suggestion. With a dedupe key, an existing one (in any status) is returned instead.
	 *
	 * @param string $kind    Kind, e.g. list, placement, journey.
	 * @param string $title   Short title.
	 * @param string $reason  Why (shown to the owner).
	 * @param array  $payload What approving does.
	 * @param string $key     Dedupe key ('' for none).
	 * @param int    $days    Days until it expires (0: never).
	 * @param string $status  pending, or info for announcements (done by Autopilot, shown for review).
	 * @return int Id (0 on failure).
	 */
	public static function add( $kind, $title, $reason, array $payload = array(), $key = '', $days = 30, $status = 'pending' ) {
		global $wpdb;
		$table = Schema::table( 'suggestions' );
		$key   = substr( (string) $key, 0, 120 );
		if ( '' !== $key ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE dedupe_key = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			if ( $id ) {
				return $id;
			}
		}
		$saved = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$table,
			array(
				'kind'       => sanitize_key( (string) $kind ),
				'dedupe_key' => '' !== $key ? $key : null,
				'title'      => substr( sanitize_text_field( (string) $title ), 0, 255 ),
				'reason'     => sanitize_textarea_field( (string) $reason ),
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'info' === $status ? 'info' : 'pending',
				'expires_at' => $days > 0 ? gmdate( 'Y-m-d H:i:s', time() + (int) $days * DAY_IN_SECONDS ) : null,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		if ( ! $saved ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		/**
		 * A suggestion was added to the Approval inbox.
		 *
		 * @param array $suggestion Suggestion.
		 */
		do_action( 'qcld_wpbot_marketing/suggestion_added', self::get( $id ) );
		return $id;
	}

	/**
	 * One suggestion.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( 'suggestions' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * A suggestion by its dedupe key.
	 *
	 * @param string $key Dedupe key.
	 * @return array|null
	 */
	public static function by_key( $key ) {
		global $wpdb;
		$table = Schema::table( 'suggestions' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE dedupe_key = %s", substr( (string) $key, 0, 120 ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Suggestions by status, newest first.
	 *
	 * @param string $status Status, or '' for all.
	 * @param int    $limit  Max rows.
	 * @param string $kind   Kind filter.
	 * @return array[]
	 */
	public static function all( $status = 'pending', $limit = 100, $kind = '' ) {
		global $wpdb;
		self::expire();
		$table  = Schema::table( 'suggestions' );
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $status, self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $kind ) {
			$where[]  = 'kind = %s';
			$params[] = sanitize_key( $kind );
		}
		$params[] = max( 1, min( 500, (int) $limit ) );
		$sql      = implode( ' AND ', $where );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$sql} ORDER BY id DESC LIMIT %d", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own table; placeholders in $sql.
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * Pending count (for the menu badge).
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		$table = Schema::table( 'suggestions' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = %s AND ( expires_at IS NULL OR expires_at > %s )", 'pending', current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Approve or dismiss. Only a pending suggestion can be decided, once.
	 *
	 * @param int    $id       Id.
	 * @param string $decision approve or dismiss.
	 * @param int    $user_id  Who decided.
	 * @return array|WP_Error The suggestion.
	 */
	public static function decide( $id, $decision, $user_id = 0 ) {
		global $wpdb;
		$table  = Schema::table( 'suggestions' );
		$status = 'approve' === $decision ? 'approved' : ( 'dismiss' === $decision ? 'dismissed' : '' );
		if ( '' === $status ) {
			return new WP_Error( 'qcld_mk_decision', __( 'Choose approve or dismiss.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$taken = (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET status = %s, decided_at = %s, decided_by = %d WHERE id = %d AND status IN ('pending','info')", $status, current_time( 'mysql', true ), (int) $user_id, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; atomic decision.
		if ( ! $taken ) {
			return new WP_Error( 'qcld_mk_decided', __( 'This suggestion was already decided.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$suggestion = self::get( $id );
		if ( 'approved' === $status ) {
			/**
			 * Carry out an approved suggestion. Return true, or a WP_Error to keep it pending.
			 *
			 * @param null|true|WP_Error $result     Null when no feature handles the kind.
			 * @param array              $suggestion Suggestion.
			 */
			$result = apply_filters( 'qcld_wpbot_marketing/suggestion/approve', null, $suggestion );
			if ( is_wp_error( $result ) || null === $result ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
					$table,
					array(
						'status'     => 'pending',
						'decided_at' => null,
						'decided_by' => 0,
					),
					array( 'id' => (int) $id )
				);
				return is_wp_error( $result ) ? $result : new WP_Error( 'qcld_mk_unhandled', __( 'This suggestion can only be approved while WPBot AI Marketing Pro is active.', 'chatbot' ), array( 'status' => 400 ) );
			}
		}
		/**
		 * A suggestion was approved or dismissed.
		 *
		 * @param array  $suggestion Suggestion.
		 * @param string $status     approved or dismissed.
		 */
		do_action( 'qcld_wpbot_marketing/suggestion_decided', $suggestion, $status );
		return self::get( $id );
	}

	/**
	 * Mark pending suggestions past their date as expired.
	 *
	 * @return void
	 */
	public static function expire() {
		global $wpdb;
		$table = Schema::table( 'suggestions' );
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET status = 'expired' WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at <= %s", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * A row as returned to the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$payload = json_decode( (string) $row['payload'], true );
		return array(
			'id'         => (int) $row['id'],
			'kind'       => (string) $row['kind'],
			'key'        => (string) $row['dedupe_key'],
			'title'      => (string) $row['title'],
			'reason'     => (string) $row['reason'],
			'payload'    => is_array( $payload ) ? $payload : array(),
			'status'     => (string) $row['status'],
			'expires_at' => (string) $row['expires_at'],
			'created_at' => (string) $row['created_at'],
			'decided_at' => (string) $row['decided_at'],
		);
	}
}
