<?php
/**
 * Approval queue and change log (chatbot features CB1d, shared with Help Desk).
 *
 * Writes from WordPress Abilities and MCP clients go through here. Each kind of change knows how
 * to apply itself and how to undo itself: applying records the previous value, so the owner can
 * undo it in one click. Changes that publish something visitors see (knowledge the bot answers
 * from) wait for the owner's approval by default.
 *
 * Kinds: add_knowledge, answer_gap; more through the filter qcld_wpbot_chat/change_kinds.
 * Statuses: pending, applied, rejected, undone, failed, done (logged only, nothing to undo).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Abilities;

use QuantumCloud\Chatbot\Sessions\Schema;
use QuantumCloud\Chatbot\Train\Gaps;

defined( 'ABSPATH' ) || exit;

/**
 * Change log.
 */
final class Changes {

	/**
	 * Statuses.
	 */
	const STATUSES = array( 'pending', 'applied', 'rejected', 'undone', 'failed', 'done' );

	/**
	 * Kinds of change: label, apply( input ) => array{ result, undo }|WP_Error, undo( undo ) => true|WP_Error, approval.
	 *
	 * @return array<string,array>
	 */
	public static function kinds() {
		$kinds = array(
			'add_knowledge' => array(
				'label'    => __( 'Add knowledge', 'chatbot' ),
				'apply'    => array( __CLASS__, 'apply_add_knowledge' ),
				'undo'     => array( __CLASS__, 'undo_add_knowledge' ),
				'approval' => true,
			),
			'answer_gap'    => array(
				'label'    => __( 'Answer a knowledge gap', 'chatbot' ),
				'apply'    => array( __CLASS__, 'apply_answer_gap' ),
				'undo'     => array( __CLASS__, 'undo_answer_gap' ),
				'approval' => true,
			),
		);
		/**
		 * Kinds of change the approval queue knows (Help Desk adds article changes).
		 *
		 * @param array $kinds kind => label, apply, undo, approval.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/change_kinds', $kinds );
	}

	/**
	 * Propose a change: it waits for approval, or is applied now.
	 *
	 * @param string $kind    Kind.
	 * @param array  $input   Input.
	 * @param string $summary One line for the owner.
	 * @param string $via     Where it came from (ability, mcp, admin).
	 * @return array|\WP_Error { status: pending|applied, change_id, result? }
	 */
	public static function propose( $kind, array $input, $summary, $via = 'ability' ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_kind', __( 'Unknown change.', 'chatbot' ) );
		}
		/**
		 * Whether a change waits for the owner's approval.
		 *
		 * @param bool   $needs Default from the kind.
		 * @param string $kind  Kind.
		 * @param array  $input Input.
		 */
		$needs = (bool) apply_filters( 'qcld_wpbot_chat/change_needs_approval', ! empty( $kinds[ $kind ]['approval'] ), $kind, $input );
		$id    = self::insert( $kind, $input, $summary, $via, 'pending' );
		if ( ! $id ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_save', __( 'The change could not be saved.', 'chatbot' ) );
		}
		if ( $needs ) {
			return array(
				'status'    => 'pending',
				'change_id' => $id,
				'message'   => __( 'Saved for the site owner to approve (ChatBot > Train > Changes).', 'chatbot' ),
			);
		}
		return self::apply( $id );
	}

	/**
	 * Log a change that has nothing to undo (an Action run).
	 *
	 * @param string $kind    Kind.
	 * @param array  $input   Input.
	 * @param string $summary Summary.
	 * @param mixed  $result  Result.
	 * @param string $via     Where it came from.
	 * @return int Change id.
	 */
	public static function log( $kind, array $input, $summary, $result, $via = 'ability' ) {
		$id = self::insert( $kind, $input, $summary, $via, 'done' );
		if ( $id ) {
			self::update(
				$id,
				array(
					'result'  => wp_json_encode( $result ),
					'decided' => current_time( 'mysql', true ),
				)
			);
		}
		return $id;
	}

	/**
	 * Apply a pending change (owner approved, or no approval needed).
	 *
	 * @param int $id Change id.
	 * @return array|\WP_Error
	 */
	public static function apply( $id ) {
		$change = self::get( $id );
		$kinds  = self::kinds();
		if ( ! $change || 'pending' !== $change['status'] ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_state', __( 'This change is no longer waiting.', 'chatbot' ) );
		}
		if ( ! isset( $kinds[ $change['kind'] ] ) || ! is_callable( $kinds[ $change['kind'] ]['apply'] ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_kind', __( 'Unknown change.', 'chatbot' ) );
		}
		// Claim it first, so two approvals at once apply it only once.
		if ( ! self::claim( $id, 'pending', 'applying' ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_state', __( 'This change is no longer waiting.', 'chatbot' ) );
		}
		$out = call_user_func( $kinds[ $change['kind'] ]['apply'], (array) json_decode( (string) $change['input'], true ) );
		$now = current_time( 'mysql', true );
		if ( is_wp_error( $out ) ) {
			self::update(
				$id,
				array(
					'status'     => 'failed',
					'result'     => wp_json_encode( array( 'error' => $out->get_error_message() ) ),
					'decided_by' => get_current_user_id(),
					'decided'    => $now,
				)
			);
			return $out;
		}
		self::update(
			$id,
			array(
				'status'     => 'applied',
				'result'     => wp_json_encode( isset( $out['result'] ) ? $out['result'] : null ),
				'undo_data'  => wp_json_encode( isset( $out['undo'] ) ? $out['undo'] : null ),
				'decided_by' => get_current_user_id(),
				'decided'    => $now,
			)
		);
		return array(
			'status'    => 'applied',
			'change_id' => (int) $id,
			'result'    => isset( $out['result'] ) ? $out['result'] : null,
		);
	}

	/**
	 * Reject a pending change.
	 *
	 * @param int $id Change id.
	 * @return true|\WP_Error
	 */
	public static function reject( $id ) {
		$change = self::get( $id );
		if ( ! $change || ! self::claim( $id, 'pending', 'rejected' ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_state', __( 'This change is no longer waiting.', 'chatbot' ) );
		}
		self::update(
			$id,
			array(
				'status'     => 'rejected',
				'decided_by' => get_current_user_id(),
				'decided'    => current_time( 'mysql', true ),
			)
		);
		return true;
	}

	/**
	 * Undo an applied change with its recorded previous value.
	 *
	 * @param int $id Change id.
	 * @return true|\WP_Error
	 */
	public static function undo( $id ) {
		$change = self::get( $id );
		$kinds  = self::kinds();
		if ( ! $change || 'applied' !== $change['status'] || ! isset( $kinds[ $change['kind'] ]['undo'] ) || ! is_callable( $kinds[ $change['kind'] ]['undo'] ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_state', __( 'This change cannot be undone.', 'chatbot' ) );
		}
		if ( ! self::claim( $id, 'applied', 'undoing' ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_change_state', __( 'This change cannot be undone.', 'chatbot' ) );
		}
		$out = call_user_func( $kinds[ $change['kind'] ]['undo'], (array) json_decode( (string) $change['undo_data'], true ) );
		if ( is_wp_error( $out ) ) {
			self::update( $id, array( 'status' => 'applied' ) );
			return $out;
		}
		self::update(
			$id,
			array(
				'status'     => 'undone',
				'decided_by' => get_current_user_id(),
				'decided'    => current_time( 'mysql', true ),
			)
		);
		return true;
	}

	/**
	 * Changes, newest first.
	 *
	 * @param string $status   Status ('' = all).
	 * @param int    $page     Page.
	 * @param int    $per_page Per page.
	 * @return array{items: array[], total: int}
	 */
	public static function items( $status = '', $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table    = Schema::changes_table();
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( in_array( $status, self::STATUSES, true ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d', $table, $status, $per_page, $offset ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $per_page, $offset ), ARRAY_A );
		}
		// phpcs:enable
		return array(
			'items' => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Pending changes.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', Schema::changes_table(), 'pending' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * One change.
	 *
	 * @param int $id Change id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::changes_table(), (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? $row : null;
	}

	/**
	 * Apply: add a knowledge entry (stored in the knowledge base, source "train").
	 *
	 * @param array $input title, content.
	 * @return array|\WP_Error
	 */
	public static function apply_add_knowledge( array $input ) {
		$title   = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$content = isset( $input['content'] ) ? sanitize_textarea_field( (string) $input['content'] ) : '';
		if ( '' === $title || '' === $content ) {
			return new \WP_Error( 'qcld_wpbot_chat_knowledge', __( 'A title and the text are needed.', 'chatbot' ) );
		}
		if ( ! function_exists( 'qcld_wpbot_core_kb_store' ) ) {
			return new \WP_Error( 'qcld_wpbot_chat_knowledge', __( 'The knowledge base is not available.', 'chatbot' ) );
		}
		$id = 'entry-' . substr( md5( $title . "\n" . $content . microtime() ), 0, 12 );
		qcld_wpbot_core_kb_store(
			Gaps::SOURCE,
			array(
				'id'      => $id,
				'title'   => $title,
				'url'     => '',
				'content' => $content,
			)
		);
		return array(
			'result' => array( 'entry' => $id ),
			'undo'   => array( 'entry' => $id ),
		);
	}

	/**
	 * Undo: remove the knowledge entry.
	 *
	 * @param array $undo entry.
	 * @return true
	 */
	public static function undo_add_knowledge( array $undo ) {
		if ( ! empty( $undo['entry'] ) && function_exists( 'qcld_wpbot_core_kb_remove' ) ) {
			qcld_wpbot_core_kb_remove( Gaps::SOURCE, (string) $undo['entry'] );
		}
		return true;
	}

	/**
	 * Apply: answer a knowledge gap.
	 *
	 * @param array $input gap_id, answer.
	 * @return array|\WP_Error
	 */
	public static function apply_answer_gap( array $input ) {
		$id     = isset( $input['gap_id'] ) ? (int) $input['gap_id'] : 0;
		$before = Gaps::get( $id );
		$done   = Gaps::answer( $id, isset( $input['answer'] ) ? (string) $input['answer'] : '' );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		return array(
			'result' => array( 'gap_id' => $id ),
			'undo'   => array(
				'gap_id'  => $id,
				'status'  => $before ? (string) $before['status'] : 'open',
				'answer'  => $before ? (string) $before['answer'] : '',
				'applied' => trim( sanitize_textarea_field( isset( $input['answer'] ) ? (string) $input['answer'] : '' ) ),
			),
		);
	}

	/**
	 * Undo: the gap's previous status and answer back, and the answer out of the knowledge base.
	 *
	 * @param array $undo gap_id, status, answer.
	 * @return true|\WP_Error
	 */
	public static function undo_answer_gap( array $undo ) {
		$id      = isset( $undo['gap_id'] ) ? (int) $undo['gap_id'] : 0;
		$current = Gaps::get( $id );
		if ( isset( $undo['applied'] ) && ( ! $current || 'answered' !== $current['status'] || (string) $current['answer'] !== (string) $undo['applied'] ) ) {
			// Someone answered or changed the gap after this change: undoing would overwrite their work.
			return new \WP_Error( 'qcld_wpbot_chat_change_stale', __( 'This question was changed after this answer, so it cannot be undone from here.', 'chatbot' ) );
		}
		Gaps::set_status( $id, isset( $undo['status'] ) ? (string) $undo['status'] : 'open', isset( $undo['answer'] ) ? (string) $undo['answer'] : '' );
		if ( function_exists( 'qcld_wpbot_core_kb_remove' ) ) {
			qcld_wpbot_core_kb_remove( Gaps::SOURCE, (string) $id );
		}
		return true;
	}

	/**
	 * Move a change from one status to another only if it still has the first one (one winner).
	 *
	 * @param int    $id   Change id.
	 * @param string $from Expected status.
	 * @param string $to   New status.
	 * @return bool Whether this call moved it.
	 */
	private static function claim( $id, $from, $to ) {
		global $wpdb;
		return 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE id = %d AND status = %s', Schema::changes_table(), $to, (int) $id, $from ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Insert a row.
	 *
	 * @param string $kind    Kind.
	 * @param array  $input   Input.
	 * @param string $summary Summary.
	 * @param string $via     Via.
	 * @param string $status  Status.
	 * @return int
	 */
	private static function insert( $kind, array $input, $summary, $via, $status ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::changes_table(),
			array(
				'kind'    => substr( sanitize_key( $kind ), 0, 60 ),
				'summary' => function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( (string) $summary ), 0, 255 ) : substr( wp_strip_all_tags( (string) $summary ), 0, 255 ),
				'input'   => wp_json_encode( $input ),
				'status'  => $status,
				'via'     => substr( sanitize_key( $via ), 0, 20 ),
				'user_id' => get_current_user_id(),
				'created' => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a row.
	 *
	 * @param int   $id   Change id.
	 * @param array $data Columns.
	 * @return void
	 */
	private static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( Schema::changes_table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
