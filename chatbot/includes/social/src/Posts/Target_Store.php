<?php
/**
 * Post targets and their state machine (build-brief A2).
 *
 * States:
 *   draft → scheduled → queued (claimed) → sending → done
 *                         ↑                  ├→ retry (backoff) → queued …
 *                         └──────────────────├→ failed
 *                                            └→ unknown → (reconcile) → done | retry | check
 *
 * Every transition is a single conditional UPDATE, so two runners can never
 * both claim or send the same target.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Target storage.
 */
final class Target_Store {

	/**
	 * Seconds after which a "sending" target whose runner vanished becomes "unknown".
	 */
	const STALE_SENDING = 600;

	/**
	 * Targets of a post.
	 *
	 * @param int $post_id Post id.
	 * @return array[]
	 */
	public static function for_post( $post_id ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		return array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id", (int) $post_id ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * One target.
	 *
	 * @param int $id Target id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Ids of targets that are due: scheduled or retry, next attempt reached,
	 * and their account is connected (an expired login's targets wait).
	 *
	 * @param string        $now      GMT now.
	 * @param int           $limit    Max ids.
	 * @param string[]|null $networks Only targets on these networks (null: any).
	 * @return int[]
	 */
	public static function due_ids( $now, $limit = 10, $networks = null ) {
		global $wpdb;
		$targets  = Schema::table( 'post_targets' );
		$accounts = Schema::table( 'accounts' );
		$only     = '';
		$args     = array( $now );
		if ( is_array( $networks ) ) {
			// Targets on networks this site can't publish to (a Master network after a move to
			// WPBot Free) stay scheduled: never failed, never sent. They resume when the
			// network is back, or become Missed (#58) after the owner's grace time.
			$networks = array_values( array_filter( array_map( 'sanitize_key', $networks ) ) );
			if ( array() === $networks ) {
				return array();
			}
			$only = ' AND t.network IN (' . implode( ',', array_fill( 0, count( $networks ), '%s' ) ) . ')';
			$args = array_merge( $args, $networks );
		}
		$args[] = max( 1, (int) $limit );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT t.id FROM {$targets} t INNER JOIN {$accounts} a ON a.id = t.account_id WHERE t.state IN ('scheduled','retry') AND t.next_attempt_at <= %s AND a.status = 'ok'{$only} ORDER BY t.next_attempt_at ASC LIMIT %d", $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables; $only holds placeholders only, $args matches them.
	}

	/**
	 * Claim a due target for one runner. Returns the claim token, or '' when
	 * another runner got there first.
	 *
	 * @param int    $id  Target id.
	 * @param string $now GMT now.
	 * @return string
	 */
	public static function claim( $id, $now ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$claim = wp_generate_password( 32, false );
		$rows  = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'queued', claim = %s, claimed_at = %s WHERE id = %d AND state IN ('scheduled','retry') AND next_attempt_at <= %s", $claim, $now, (int) $id, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return 1 === (int) $rows ? $claim : '';
	}

	/**
	 * Move a claimed target to "sending" right before the API call.
	 *
	 * @param int    $id    Target id.
	 * @param string $claim Claim token.
	 * @param string $now   GMT now.
	 * @return bool Whether this runner may send.
	 */
	public static function start_sending( $id, $claim, $now ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'sending', attempt_started_at = %s, attempts = attempts + 1 WHERE id = %d AND state = 'queued' AND claim = %s", $now, (int) $id, (string) $claim ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Claim an "unknown" target for reconciling.
	 *
	 * @param int    $id  Target id.
	 * @param string $now GMT now.
	 * @return string Claim token or ''.
	 */
	public static function claim_unknown( $id, $now ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$claim = wp_generate_password( 32, false );
		$rows  = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'reconciling', claim = %s, claimed_at = %s WHERE id = %d AND state = 'unknown'", $claim, $now, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return 1 === (int) $rows ? $claim : '';
	}

	/**
	 * Ids of targets in state "unknown" that are due for a look.
	 *
	 * A reconcile that said "wait" sets next_attempt_at (QA #37).
	 *
	 * @param int         $limit Max ids.
	 * @param string|null $now   GMT now; defaults to the current time.
	 * @return int[]
	 */
	public static function unknown_ids( $limit = 10, $now = null ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$now   = null === $now ? current_time( 'mysql', true ) : (string) $now;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE state = 'unknown' AND ( next_attempt_at IS NULL OR next_attempt_at <= %s ) ORDER BY id LIMIT %d", $now, max( 1, (int) $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * A runner that died mid-send (lock expired, PHP killed) leaves a
	 * "sending" target. It is never retried: it becomes "unknown" and is
	 * reconciled first. Claimed-but-not-started targets go back to "retry";
	 * an interrupted reconcile goes back to "unknown".
	 *
	 * @param string $stale_before GMT time; older claims are stale.
	 * @return int Rows changed.
	 */
	public static function recover_stale( $stale_before ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$a     = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'unknown', error_code = 'interrupted', error_message = %s WHERE state = 'sending' AND attempt_started_at < %s", __( 'Publishing was interrupted. WPBot is checking whether the post went out.', 'chatbot' ), $stale_before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$b     = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'retry', claim = NULL WHERE state = 'queued' AND claimed_at < %s", $stale_before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$c     = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'unknown', claim = NULL WHERE state = 'reconciling' AND claimed_at < %s", $stale_before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $a + $b + $c;
	}

	/**
	 * Save a step's outcome only if this runner still owns the target (QA #53).
	 *
	 * A runner that took longer than STALE_SENDING has had its target moved to
	 * "unknown" by stale recovery; its late write must not overwrite what
	 * reconcile decides. The lost write is logged by the caller.
	 *
	 * @param int    $id     Target id.
	 * @param string $claim  Claim token the runner holds.
	 * @param string $state  State the runner expects (sending | reconciling).
	 * @param array  $fields Outcome fields, as for finish().
	 * @return bool Whether the write happened.
	 */
	public static function finish_owned( $id, $claim, $state, array $fields ) {
		global $wpdb;
		if ( isset( $fields['step_state'] ) ) {
			$fields['step_state'] = wp_json_encode( (array) $fields['step_state'] );
		}
		if ( isset( $fields['error_message'] ) ) {
			$fields['error_message'] = substr( (string) $fields['error_message'], 0, 255 );
		}
		$fields['claim'] = null;
		$rows            = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'post_targets' ),
			$fields,
			array(
				'id'    => (int) $id,
				'claim' => (string) $claim,
				'state' => (string) $state,
			)
		);
		return 1 === (int) $rows;
	}

	/**
	 * Save an outcome.
	 *
	 * @param int   $id     Target id.
	 * @param array $fields state, remote_id, remote_url, error_code, error_message, next_attempt_at, step_state (array), done_at.
	 * @return void
	 */
	public static function finish( $id, array $fields ) {
		global $wpdb;
		if ( isset( $fields['step_state'] ) ) {
			$fields['step_state'] = wp_json_encode( (array) $fields['step_state'] );
		}
		if ( isset( $fields['error_message'] ) ) {
			$fields['error_message'] = substr( (string) $fields['error_message'], 0, 255 );
		}
		$fields['claim'] = null;
		$wpdb->update( Schema::table( 'post_targets' ), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Owner's "Fix & retry" or "It went out" after Check and confirm, and
	 * "Mark as posted" or "Skip" for Post yourself targets.
	 *
	 * @param int    $id     Target id.
	 * @param string $action retry | posted | skip | post_now | reschedule.
	 * @param string $url    The post's address ("posted"), or the new GMT time ("reschedule").
	 * @return bool
	 */
	public static function owner_action( $id, $action, $url = '' ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$now   = current_time( 'mysql', true );
		if ( 'retry' === $action ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'retry', attempts = 0, next_attempt_at = %s, error_code = '', error_message = '' WHERE id = %d AND state IN ('failed','check')", $now, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		if ( 'posted' === $action ) {
			$url = esc_url_raw( (string) $url );
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'done', done_at = %s, remote_url = %s, error_code = 'confirmed_by_owner', error_message = '' WHERE id = %d AND state IN ('check','manual')", $now, $url, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		if ( 'post_now' === $action ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'retry', attempts = 0, next_attempt_at = %s, error_code = '', error_message = '' WHERE id = %d AND state = 'missed'", $now, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		if ( 'reschedule' === $action ) {
			$when = (string) $url;
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $when ) || strtotime( $when . ' UTC' ) < time() ) {
				return false;
			}
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'scheduled', attempts = 0, next_attempt_at = %s, error_code = '', error_message = '' WHERE id = %d AND state = 'missed'", $when, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		if ( 'skip' === $action ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'skipped', done_at = %s, error_code = 'skipped_by_owner', error_message = '' WHERE id = %d AND state IN ('manual','missed')", $now, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		return false;
	}

	/**
	 * QA #58: targets that came due long ago (a license pause, a long cron
	 * outage) are not published late in a burst. They become "missed" and the
	 * owner chooses: post now, reschedule or skip. "unknown" is never touched.
	 *
	 * @param string $before GMT time; due before this means missed.
	 * @return int Targets marked.
	 */
	public static function mark_missed( $before ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE state IN ('scheduled','retry') AND next_attempt_at < %s", $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( array() === $ids ) {
			return 0;
		}
		$marked = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = 'missed', error_code = 'missed', error_message = %s WHERE state IN ('scheduled','retry') AND id IN (" . implode( ',', $ids ) . ')', __( 'This was due while WPBot Social could not run (license paused or background jobs stopped). Choose: post now, reschedule or skip.', 'chatbot' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; ids are integers from the query above.
		$posts  = array_unique( array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT post_id FROM ' . $table . ' WHERE id IN (' . implode( ',', $ids ) . ')' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; ids are integers.
		foreach ( $posts as $post_id ) {
			Post_Store::refresh_status( $post_id );
		}
		update_option( 'qcld_wpbot_social_missed_notice', (int) get_option( 'qcld_wpbot_social_missed_notice', 0 ) + $marked, false );
		return $marked;
	}

	/**
	 * Post yourself targets: waiting ones first, then recently posted.
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public static function manual( $limit = 30 ) {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE state = 'manual' OR ( error_code = 'confirmed_by_owner' AND done_at >= %s AND state = 'done' ) ORDER BY state = 'manual' DESC, id DESC LIMIT %d", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ), max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * How many Post yourself targets are waiting.
	 *
	 * @return int
	 */
	public static function manual_count() {
		global $wpdb;
		$table = Schema::table( 'post_targets' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE state = 'manual'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * When the next Post yourself target comes due (GMT), or ''.
	 *
	 * @return string
	 */
	public static function next_manual() {
		global $wpdb;
		$targets  = Schema::table( 'post_targets' );
		$accounts = Schema::table( 'accounts' );
		return (string) $wpdb->get_var( "SELECT MIN(t.next_attempt_at) FROM {$targets} t INNER JOIN {$accounts} a ON a.id = t.account_id WHERE a.kind = 'manual' AND t.state IN ('scheduled','retry')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
	}

	/**
	 * Typed row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$row['id']         = (int) $row['id'];
		$row['post_id']    = (int) $row['post_id'];
		$row['account_id'] = (int) $row['account_id'];
		$row['attempts']   = (int) $row['attempts'];
		$draft             = json_decode( (string) $row['draft'], true );
		$row['draft']      = is_array( $draft ) ? $draft : array();
		$state             = json_decode( (string) $row['step_state'], true );
		$row['step_state'] = is_array( $state ) ? $state : array();
		return $row;
	}
}
