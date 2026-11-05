<?php
/**
 * Site Context, layer 4 (opt-in): what visitors ask the chatbot. Reads the
 * host's chat sessions through Chat_Source (never the host's tables), keeps
 * only visitor messages that read like questions, removes personal data,
 * and groups repeats. Questions the bot could not answer are marked: they
 * are the best content and focus ideas.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

use QuantumCloud\WPBotCore\Core;
use QuantumCloud\WPBotCore\Privacy\Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * Customer questions from chat sessions.
 */
final class Customer_Questions {

	const SESSIONS = 300;
	const KEEP     = 40;

	/**
	 * Is any chat source available?
	 *
	 * @return bool
	 */
	public static function available() {
		return array() !== self::sources();
	}

	/**
	 * Read recent sessions (last 180 days) and return the most asked questions.
	 *
	 * @return array{questions: array, sessions: int} questions: text, count, unanswered.
	 */
	public static function collect() {
		$counts = array();
		$seen   = 0;
		$since  = gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS );
		foreach ( self::sources() as $source ) {
			$cursor = 0;
			while ( $seen < self::SESSIONS ) {
				$page = $source->get_sessions( $cursor, 50, $since );
				$list = isset( $page['sessions'] ) ? (array) $page['sessions'] : array();
				if ( array() === $list ) {
					break;
				}
				foreach ( $list as $session ) {
					++$seen;
					$names      = isset( $session['names'] ) ? (array) $session['names'] : array();
					$unanswered = ! empty( $session['signals']['unanswered'] );
					foreach ( isset( $session['messages'] ) ? (array) $session['messages'] : array() as $message ) {
						if ( ! isset( $message['role'], $message['text'] ) || 'visitor' !== $message['role'] ) {
							continue;
						}
						$question = self::question( Redactor::redact( (string) $message['text'], $names ) );
						if ( '' === $question ) {
							continue;
						}
						$key = self::key( $question );
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = array(
								'text'       => $question,
								'count'      => 0,
								'unanswered' => false,
							);
						}
						++$counts[ $key ]['count'];
						$counts[ $key ]['unanswered'] = $counts[ $key ]['unanswered'] || $unanswered;
					}
				}
				$next = isset( $page['cursor'] ) ? (int) $page['cursor'] : 0;
				if ( $next <= $cursor ) {
					break;
				}
				$cursor = $next;
			}
		}
		uasort(
			$counts,
			static function ( $a, $b ) {
				return array( $b['count'], (int) $b['unanswered'] ) <=> array( $a['count'], (int) $a['unanswered'] );
			}
		);
		return array(
			'questions' => array_values( array_slice( $counts, 0, self::KEEP ) ),
			'sessions'  => $seen,
		);
	}

	/**
	 * A visitor message cleaned into a question, or '' when it is not one.
	 *
	 * @param string $text Message (already redacted).
	 * @return string
	 */
	public static function question( $text ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		// Keep the question sentence only ("Do you ship? I'm [name], call [phone]" => "Do you ship?").
		$mark = strpos( $text, '?' );
		if ( false !== $mark ) {
			$text = trim( substr( $text, 0, $mark + 1 ) );
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len < 8 || $len > 200 ) {
			return '';
		}
		$starts = '/^(what|whats|what\'s|how|why|when|where|which|who|can|could|do|does|did|is|are|will|would|should|may|have|has|any)\b/i';
		if ( false === strpos( $text, '?' ) && ! preg_match( $starts, $text ) ) {
			return '';
		}
		// Nothing left but placeholders: nothing to learn from.
		if ( '' === trim( (string) preg_replace( '/\[[a-z ]+\]|[?.!,]/i', '', $text ) ) ) {
			return '';
		}
		return $text;
	}

	/**
	 * Grouping key: lowercase words without punctuation.
	 *
	 * @param string $text Question.
	 * @return string
	 */
	public static function key( $text ) {
		return trim( (string) preg_replace( '/[^\p{L}\p{N} ]+/u', '', strtolower( (string) $text ) ) );
	}

	/**
	 * Available chat sources from the host.
	 *
	 * @return \QuantumCloud\WPBotCore\Chat\Chat_Source[]
	 */
	private static function sources() {
		$host = Core::instance()->host();
		$out  = array();
		foreach ( $host ? (array) $host->get_chat_sources() : array() as $source ) {
			if ( $source instanceof \QuantumCloud\WPBotCore\Chat\Chat_Source && $source->is_available() ) {
				$out[] = $source;
			}
		}
		return $out;
	}
}
