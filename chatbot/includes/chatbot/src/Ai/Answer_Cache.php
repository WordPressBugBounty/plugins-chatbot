<?php
/**
 * Answer cache: saved AI answers for repeated first questions from visitors who are not logged in
 * (AI Settings > Limits and cost > Answer cache; off by default).
 *
 * An answer is reused only when the whole request matches: provider, model, the system text (prompt,
 * answer scope, the knowledge base text found for the question, page context) and the question, with
 * capitals, spaces and end punctuation ignored. The visitor's name is left out of the key; an answer
 * that mentions the name is never saved, and neither is one for a question with an email address, a
 * phone number or an order number. Answers expire after 7 days, are cleared when AI Settings are saved,
 * and stop being served once flagged (AI Settings > Answer cache, where the owner can check them with AI).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

use QuantumCloud\Chatbot\Sessions\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * The chatbot's answer cache (table qcld_wpbot_chat_answer_cache).
 */
final class Answer_Cache {

	/**
	 * Days a saved answer is reused.
	 */
	const TTL_DAYS = 7;

	/**
	 * Statuses.
	 */
	const NEW_ANSWER = 'new';
	const CORRECT    = 'correct';
	const FLAGGED    = 'flagged';

	/**
	 * Transient: last prune.
	 */
	const PRUNED = 'qcld_wpbot_chat_answer_cache_pruned';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Schema::answer_cache_table();
	}

	/**
	 * The question as the key sees it: lower case, single spaces, no end punctuation.
	 *
	 * @param string $question Question.
	 * @return string
	 */
	public static function normalize( $question ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $question ) ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		return trim( (string) preg_replace( '/[\s?!.,;:。？！…]+$/u', '', $text ) );
	}

	/**
	 * The system text without the visitor's name line (Ai_Step adds it to the context).
	 *
	 * @param string $system System text.
	 * @return string
	 */
	public static function without_name( $system ) {
		return trim( (string) preg_replace( "/The user's name is: .*?\\. Do not ask for their name\\.\\s*/su", '', (string) $system ) );
	}

	/**
	 * Whether a question carries personal data: an email address, a phone number or an order number.
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	public static function has_personal_data( $question ) {
		$text = (string) $question;
		if ( preg_match( '/[^\s@]+@[^\s@]+\.[a-z]{2,}/i', $text ) ) {
			return true;
		}
		// Seven or more digits, with spaces, dots, dashes, brackets or a leading + between them.
		if ( preg_match( '/\+?\d(?:[\s().-]*\d){6,}/', $text ) ) {
			return true;
		}
		// An order or invoice number: "#1234", "order 1234", "order no. 1234".
		return (bool) preg_match( '/(#\s*\d{3,}|\b(order|invoice|ticket|booking|reservation)\b\D{0,12}\d{3,})/i', $text );
	}

	/**
	 * The cache key for this request, or '' when this answer may not use the cache.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param string $feature  Feature id.
	 * @param object $request  WPBot Core AI\Request.
	 * @param string $question The visitor's question.
	 * @param array  $args     Ai_Client::reply() args.
	 * @return string
	 */
	public static function key( $provider, $model, $feature, $request, $question, array $args ) {
		if ( ! empty( $args['history'] ) || ! empty( $args['raw'] ) || self::has_personal_data( $question ) ) {
			return '';
		}
		$normal = self::normalize( $question );
		if ( '' === $normal ) {
			return '';
		}
		/**
		 * Whether this answer may come from, and go into, the answer cache.
		 *
		 * @param bool   $use      Default false (Routing turns it on with the setting).
		 * @param string $question Question.
		 * @param array  $args     reply() args (context, feature, …).
		 */
		if ( ! apply_filters( 'qcld_wpbot_chat/answer_cache', false, $question, $args ) ) {
			return '';
		}
		$parts = array(
			'chatbot',
			(string) $feature,
			(string) $provider,
			(string) $model,
			self::without_name( isset( $request->system ) ? (string) $request->system : '' ),
			$normal,
			isset( $request->max_tokens ) ? $request->max_tokens : null,
			isset( $request->temperature ) ? $request->temperature : null,
			! empty( $request->json ),
		);
		return hash( 'sha256', (string) wp_json_encode( $parts ) );
	}

	/**
	 * A saved answer that may be served (not flagged, not expired), counting the reuse.
	 *
	 * @param string $key Key.
	 * @return string|null The answer text.
	 */
	public static function get( $key ) {
		global $wpdb;
		if ( '' === (string) $key ) {
			return null;
		}
		$table = self::table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; a cache lookup.
			$wpdb->prepare(
				"SELECT id, answer FROM {$table} WHERE hash = %s AND status <> %s AND created >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				$key,
				self::FLAGGED,
				self::cutoff()
			),
			ARRAY_A
		);
		if ( ! $row || '' === (string) $row['answer'] ) {
			return null;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_hit = %s WHERE id = %d", current_time( 'mysql', true ), (int) $row['id'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
		);
		return (string) $row['answer'];
	}

	/**
	 * Save an answer. Skipped when it mentions the visitor's name.
	 *
	 * @param string $key      Key.
	 * @param string $question The visitor's question.
	 * @param string $answer   The AI's answer (before the provider's prepend and append text).
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param string $feature  Feature id.
	 * @param float  $cost     What the answer cost (USD estimate).
	 * @param string $name     The visitor's name ('' = unknown).
	 * @return bool Saved.
	 */
	public static function put( $key, $question, $answer, $provider, $model, $feature, $cost = 0.0, $name = '' ) {
		global $wpdb;
		$answer = (string) $answer;
		if ( '' === (string) $key || '' === trim( $answer ) || self::mentions( $answer, $name ) ) {
			return false;
		}
		self::maybe_prune();
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->prepare(
				"INSERT INTO {$table} (hash, question, answer, provider, model, feature, cost, hits, status, note, created, last_hit, reviewed) VALUES (%s, %s, %s, %s, %s, %s, %f, 0, %s, '', %s, %s, '0000-00-00 00:00:00') ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer), provider = VALUES(provider), model = VALUES(model), cost = VALUES(cost), hits = 0, status = VALUES(status), note = '', created = VALUES(created), last_hit = VALUES(last_hit), reviewed = '0000-00-00 00:00:00'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				$key,
				self::clip( wp_strip_all_tags( (string) $question ), 1000 ),
				$answer,
				substr( sanitize_key( (string) $provider ), 0, 40 ),
				substr( (string) $model, 0, 100 ),
				substr( sanitize_key( (string) $feature ), 0, 40 ),
				max( 0.0, (float) $cost ),
				self::NEW_ANSWER,
				$now,
				$now
			)
		);
		return true;
	}

	/**
	 * Whether an answer mentions the visitor's name.
	 *
	 * @param string $answer Answer.
	 * @param string $name   Name.
	 * @return bool
	 */
	public static function mentions( $answer, $name ) {
		$name = trim( (string) $name );
		if ( strlen( $name ) < 2 ) {
			return false;
		}
		foreach ( preg_split( '/\s+/u', $name ) as $part ) {
			if ( strlen( $part ) >= 2 && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $part, '/' ) . '(?![\p{L}\p{N}])/iu', (string) $answer ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Saved answers, newest first.
	 *
	 * @param array $args status ('' = all), search, page, per_page.
	 * @return array{rows:array[],total:int}
	 */
	public static function rows( array $args = array() ) {
		global $wpdb;
		$table    = self::table();
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$where    = array( $wpdb->prepare( 'created >= %s', self::cutoff() ) );
		$status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( in_array( $status, array( self::NEW_ANSWER, self::CORRECT, self::FLAGGED ), true ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}
		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = $wpdb->prepare( '( question LIKE %s OR answer LIKE %s )', $like, $like );
		}
		$sql_where = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; the WHERE parts are prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		// phpcs:enable
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * One saved answer.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function row( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Ids of answers not checked yet, oldest first.
	 *
	 * @param int $limit Most ids.
	 * @return int[]
	 */
	public static function unchecked_ids( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = %s AND created >= %s ORDER BY id ASC LIMIT %d", self::NEW_ANSWER, self::cutoff(), max( 1, min( 200, (int) $limit ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Totals for the summary: answers, by status, reuses and the estimated cost saved.
	 *
	 * @return array{answers:int,new:int,correct:int,flagged:int,hits:int,saved:float}
	 */
	public static function stats() {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->prepare(
				"SELECT COUNT(*) AS answers, SUM(status = %s) AS n, SUM(status = %s) AS c, SUM(status = %s) AS f, COALESCE(SUM(hits), 0) AS hits, COALESCE(SUM(hits * cost), 0) AS saved FROM {$table} WHERE created >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				self::NEW_ANSWER,
				self::CORRECT,
				self::FLAGGED,
				self::cutoff()
			),
			ARRAY_A
		);
		$row   = is_array( $row ) ? $row : array();
		return array(
			'answers' => isset( $row['answers'] ) ? (int) $row['answers'] : 0,
			'new'     => isset( $row['n'] ) ? (int) $row['n'] : 0,
			'correct' => isset( $row['c'] ) ? (int) $row['c'] : 0,
			'flagged' => isset( $row['f'] ) ? (int) $row['f'] : 0,
			'hits'    => isset( $row['hits'] ) ? (int) $row['hits'] : 0,
			'saved'   => isset( $row['saved'] ) ? (float) $row['saved'] : 0.0,
		);
	}

	/**
	 * Set an answer's status and note.
	 *
	 * @param int    $id     Id.
	 * @param string $status new, correct or flagged.
	 * @param string $note   Reason.
	 * @return bool
	 */
	public static function set_status( $id, $status, $note = '' ) {
		global $wpdb;
		if ( ! in_array( $status, array( self::NEW_ANSWER, self::CORRECT, self::FLAGGED ), true ) ) {
			return false;
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			self::table(),
			array(
				'status'   => $status,
				'note'     => self::clip( sanitize_text_field( (string) $note ), 500 ),
				'reviewed' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete one answer.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Delete every saved answer.
	 *
	 * @return void
	 */
	public static function clear() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
	}

	/**
	 * Delete expired answers, at most once a day.
	 *
	 * @return void
	 */
	public static function maybe_prune() {
		if ( get_transient( self::PRUNED ) ) {
			return;
		}
		set_transient( self::PRUNED, 1, DAY_IN_SECONDS );
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created < %s", self::cutoff() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
	}

	/**
	 * Oldest creation time still served (UTC).
	 *
	 * @return string
	 */
	public static function cutoff() {
		return gmdate( 'Y-m-d H:i:s', time() - self::TTL_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Shorten text to a byte-safe number of characters.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Characters.
	 * @return string
	 */
	private static function clip( $text, $chars ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $chars, 'UTF-8' ) : substr( (string) $text, 0, $chars );
	}
}
