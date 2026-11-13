<?php
/**
 * Knowledge gaps (chatbot features CB1c, Train section).
 *
 * Questions the bot could not answer (no answer, not sure; hosts can add thumbs-down replies and
 * handoffs through the same action), grouped by their keywords so "Do you ship to Iceland?" and
 * "do you ship to iceland" are one gap, and ranked by how often they were asked.
 *
 * The owner closes a gap by adding an answer (stored in the knowledge base, source "train"), by
 * writing an article (`qcld_wpbot_chat/gap_write_article`; Help Desk handles it when it is on,
 * otherwise a draft post opens), or by ignoring it.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Train;

use QuantumCloud\Chatbot\Sessions\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge gaps store.
 */
final class Gaps {

	/**
	 * Knowledge base source of the owner's answers.
	 */
	const SOURCE = 'train';

	/**
	 * Statuses.
	 */
	const STATUSES = array( 'open', 'answered', 'article', 'ignored' );

	/**
	 * Reasons a question becomes a gap.
	 */
	const REASONS = array( 'no_answer', 'not_sure', 'thumbs_down', 'handoff' );

	/**
	 * Words left out of the grouping key.
	 */
	const STOPWORDS = array( 'a', 'an', 'and', 'are', 'can', 'could', 'do', 'does', 'for', 'have', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'please', 'tell', 'the', 'to', 'we', 'what', 'whats', 'you', 'your' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/knowledge_gap', array( __CLASS__, 'on_gap' ), 10, 3 );
		add_filter( 'qcld_wpbot_core/kb_sources', array( __CLASS__, 'kb_source' ) );
	}

	/**
	 * A gap from the engine (or a host).
	 *
	 * @param string $text    Question.
	 * @param string $reason  Reason.
	 * @param mixed  $message Message (unused).
	 * @return void
	 */
	public static function on_gap( $text, $reason = 'no_answer', $message = null ) {
		$language = is_object( $message ) && isset( $message->language ) ? (string) $message->language : '';
		self::record( (string) $text, (string) $reason, $language );
	}

	/**
	 * Grouping key: the question's words without punctuation, stopwords and order.
	 *
	 * @param string $text Question.
	 * @return string '' when nothing is left (not a question worth keeping).
	 */
	public static function key( $text ) {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		$keep  = array();
		foreach ( (array) $words as $word ) {
			if ( ! in_array( $word, self::STOPWORDS, true ) ) {
				$keep[ $word ] = true;
			}
		}
		$keep = array_keys( $keep );
		sort( $keep );
		$key = implode( ' ', $keep );
		return strlen( $key ) > 191 ? substr( $key, 0, 150 ) . ':' . md5( $key ) : $key;
	}

	/**
	 * Whether a message is worth keeping as a gap (not a greeting, not one word, not too long).
	 *
	 * @param string $text Message.
	 * @return bool
	 */
	public static function worth_keeping( $text ) {
		$text = trim( (string) $text );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len < 6 || $len > 255 ) {
			return false;
		}
		if ( class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Search' ) && \QuantumCloud\WPBotCore\Knowledge\Kb_Search::is_small_talk( $text ) ) {
			return false;
		}
		return false !== strpos( self::key( $text ), ' ' ) || false !== strpos( $text, '?' );
	}

	/**
	 * Record one unanswered question.
	 *
	 * @param string $text     Question.
	 * @param string $reason   no_answer, not_sure, thumbs_down or handoff.
	 * @param string $language Language.
	 * @return int Gap id (0 when not kept).
	 */
	public static function record( $text, $reason = 'no_answer', $language = '' ) {
		global $wpdb;
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( ! self::worth_keeping( $text ) ) {
			return 0;
		}
		$key    = self::key( $text );
		$reason = in_array( $reason, self::REASONS, true ) ? $reason : 'no_answer';
		$table  = Schema::gaps_table();
		$now    = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, status FROM %i WHERE gap_key = %s', $table, $key ), ARRAY_A );
		if ( $row ) {
			// Asked again after an answer: the answer did not help, so the gap opens again. Ignored stays ignored.
			$status = 'ignored' === $row['status'] ? 'ignored' : 'open';
			// The first wording stays (later ones are often shorter or shouted).
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_seen = %s, reason = %s, status = %s WHERE id = %d', $table, $now, $reason, $status, (int) $row['id'] ) );
			return (int) $row['id'];
		}
		$wpdb->insert(
			$table,
			array(
				'gap_key'    => $key,
				'question'   => $text,
				'reason'     => $reason,
				'hits'       => 1,
				'status'     => 'open',
				'language'   => substr( sanitize_key( $language ), 0, 12 ),
				'first_seen' => $now,
				'last_seen'  => $now,
			)
		);
		// phpcs:enable
		delete_transient( 'qcld_wpbot_chat_gaps_open' );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Gaps for the Train screen, most asked first.
	 *
	 * @param string $status   Status.
	 * @param int    $page     Page (from 1).
	 * @param int    $per_page Rows per page.
	 * @return array{items: array[], total: int}
	 */
	public static function items( $status = 'open', $page = 1, $per_page = 20 ) {
		global $wpdb;
		$status   = in_array( $status, self::STATUSES, true ) ? $status : 'open';
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;
		$table    = Schema::gaps_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own table.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, question, reason, hits, status, answer, first_seen, last_seen FROM %i WHERE status = %s ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d', $table, $status, $per_page, $offset ), ARRAY_A );
		// phpcs:enable
		return array(
			'items' => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Open gaps (the menu badge and the dashboard card).
	 *
	 * @return int
	 */
	public static function open_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', Schema::gaps_table(), 'open' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; cached by the caller.
	}

	/**
	 * One gap.
	 *
	 * @param int $id Gap id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::gaps_table(), (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? $row : null;
	}

	/**
	 * Set a gap's status (and answer).
	 *
	 * @param int         $id     Gap id.
	 * @param string      $status Status.
	 * @param string|null $answer Answer (null = keep).
	 * @return bool
	 */
	public static function set_status( $id, $status, $answer = null ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$data = array( 'status' => $status );
		if ( null !== $answer ) {
			$data['answer'] = (string) $answer;
		}
		return false !== $wpdb->update( Schema::gaps_table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Add the owner's answer: stored in the knowledge base (source "train") and the gap closed.
	 *
	 * @param int    $id     Gap id.
	 * @param string $answer Answer.
	 * @return bool|\WP_Error
	 */
	public static function answer( $id, $answer ) {
		$gap    = self::get( $id );
		$answer = trim( sanitize_textarea_field( (string) $answer ) );
		if ( ! $gap ) {
			return new \WP_Error( 'qcld_wpbot_chat_gap', __( 'This question is no longer in the list.', 'chatbot' ) );
		}
		if ( '' === $answer ) {
			return new \WP_Error( 'qcld_wpbot_chat_gap_answer', __( 'Write an answer first.', 'chatbot' ) );
		}
		self::set_status( $id, 'answered', $answer );
		if ( function_exists( 'qcld_wpbot_core_kb_store' ) ) {
			qcld_wpbot_core_kb_store( self::SOURCE, self::kb_item( array_merge( $gap, array( 'answer' => $answer ) ) ) );
		}
		/**
		 * The owner answered a knowledge gap.
		 *
		 * @param array  $gap    Gap row.
		 * @param string $answer Answer.
		 */
		do_action( 'qcld_wpbot_chat/gap_answered', $gap, $answer );
		return true;
	}

	/**
	 * Write an article for a gap. Help Desk (or any handler) takes `qcld_wpbot_chat/gap_write_article`
	 * and returns where to go; without one, a draft post titled with the question is created.
	 *
	 * @param int $id Gap id.
	 * @return string|\WP_Error URL to open.
	 */
	public static function write_article( $id ) {
		$gap = self::get( $id );
		if ( ! $gap ) {
			return new \WP_Error( 'qcld_wpbot_chat_gap', __( 'This question is no longer in the list.', 'chatbot' ) );
		}
		/**
		 * Write an article for a knowledge gap (Help Desk, AI Writer). Return the URL to open.
		 *
		 * @param string $url Empty: nobody handled it yet.
		 * @param array  $gap Gap row: id, question, reason, hits, language.
		 */
		$url = (string) apply_filters( 'qcld_wpbot_chat/gap_write_article', '', $gap );
		if ( '' === $url ) {
			$post_id = wp_insert_post(
				array(
					'post_title'   => wp_strip_all_tags( (string) $gap['question'] ),
					'post_status'  => 'draft',
					'post_type'    => 'post',
					/* translators: %d: how many times visitors asked. */
					'post_content' => '<!-- wp:paragraph --><p>' . esc_html( sprintf( _n( 'Visitors asked this %d time and the chatbot could not answer it.', 'Visitors asked this %d times and the chatbot could not answer it.', (int) $gap['hits'], 'chatbot' ), (int) $gap['hits'] ) ) . '</p><!-- /wp:paragraph -->',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			$url = (string) get_edit_post_link( $post_id, 'raw' );
		}
		self::set_status( $id, 'article' );
		return $url;
	}

	/**
	 * Knowledge base item for an answered gap.
	 *
	 * @param array $gap Gap row with answer.
	 * @return array
	 */
	public static function kb_item( array $gap ) {
		return array(
			'id'       => (string) (int) $gap['id'],
			'title'    => (string) $gap['question'],
			'url'      => '',
			'content'  => 'Question: ' . $gap['question'] . "\n\nAnswer: " . $gap['answer'],
			'language' => isset( $gap['language'] ) ? (string) $gap['language'] : '',
		);
	}

	/**
	 * The owner's answers as a knowledge base source (so "Sync now" keeps them).
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function kb_source( $sources ) {
		$sources[ self::SOURCE ] = array(
			'label' => __( 'Answers from Train', 'chatbot' ),
			'count' => static function () {
				global $wpdb;
				return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', Schema::gaps_table(), 'answered' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			},
			'items' => static function ( $offset, $limit ) {
				global $wpdb;
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, question, answer, language FROM %i WHERE status = %s ORDER BY id ASC LIMIT %d OFFSET %d', Schema::gaps_table(), 'answered', (int) $limit, (int) $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				return array_map( array( __CLASS__, 'kb_item' ), (array) $rows );
			},
		);
		return $sources;
	}

	/**
	 * Bring in the host's "Questions Not Answered" (wpbot_failed_response), most asked first.
	 *
	 * Safe to run again (module schema 7 runs it once more, chatbot features round 6, for the
	 * questions the widget logged only there): a question already in Train only has its count
	 * raised to the old table's count, never added twice.
	 *
	 * @return int Rows read.
	 */
	public static function import_legacy() {
		global $wpdb;
		$legacy = $wpdb->prefix . 'wpbot_failed_response';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Import from the host's table into the module's own.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) ) !== $legacy ) {
			return 0;
		}
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT `query`, `count` FROM %i ORDER BY `count` DESC LIMIT %d', $legacy, 500 ), ARRAY_A );
		$table = Schema::gaps_table();
		$read  = 0;
		foreach ( (array) $rows as $row ) {
			$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $row['query'] ) ) );
			$id   = self::worth_keeping( $text ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE gap_key = %s', $table, self::key( $text ) ) ) : 0;
			if ( ! $id ) {
				$id = self::record( $text, 'no_answer' );
			}
			if ( $id ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = GREATEST( hits, %d ) WHERE id = %d', $table, max( 1, (int) $row['count'] ), $id ) );
			}
			++$read;
		}
		// phpcs:enable
		delete_transient( 'qcld_wpbot_chat_gaps_open' );
		return $read;
	}
}
