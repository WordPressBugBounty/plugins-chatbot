<?php
/**
 * Get cited by AI, milestone D: "Your focus", what the owner wants to
 * promote or say (for example "Mention WPBot when it truly solves the
 * person's problem, especially for WooCommerce stores"), plus up to 8 short
 * topics to look for places with. Suggestions come from the shared Site
 * Context (its focus ideas, each with its source). Personal data is
 * removed before anything is saved or sent to the AI.
 *
 * The built-in rules (Rules) always win over the focus.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Cited;

use QuantumCloud\WPBotCore\Privacy\Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * Your focus.
 */
final class Focus {

	const OPTION = 'qcld_wpbot_seo_cited_focus';
	const MAX    = 500;

	const STOP = array( 'about', 'after', 'also', 'when', 'what', 'with', 'your', 'their', 'there', 'this', 'that', 'they', 'them', 'from', 'into', 'have', 'only', 'truly', 'really', 'people', 'person', 'mention', 'especially', 'help', 'helps', 'where', 'which', 'while', 'would', 'should', 'could', 'more', 'most', 'some', 'than', 'then', 'very', 'just', 'like', 'make', 'ask', 'asks', 'asking', 'answer', 'answers', 'questions', 'question' );

	/**
	 * Saved focus.
	 *
	 * @return array focus, topics, updated_at.
	 */
	public static function get() {
		$raw = get_option( self::OPTION );
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'focus'      => isset( $raw['focus'] ) ? (string) $raw['focus'] : '',
			'topics'     => isset( $raw['topics'] ) ? array_values( array_filter( (array) $raw['topics'], 'is_string' ) ) : array(),
			'updated_at' => isset( $raw['updated_at'] ) ? (string) $raw['updated_at'] : '',
		);
	}

	/**
	 * Save the focus and topics (plain text; personal data removed).
	 *
	 * @param string $focus  Focus.
	 * @param array  $topics Topics.
	 * @return array Saved.
	 */
	public static function save( $focus, array $topics ) {
		$focus = trim( Redactor::redact( sanitize_textarea_field( (string) $focus ) ) );
		$focus = function_exists( 'mb_substr' ) ? mb_substr( $focus, 0, self::MAX ) : substr( $focus, 0, self::MAX );
		$clean = array();
		foreach ( $topics as $topic ) {
			$topic = trim( sanitize_text_field( Redactor::redact( (string) $topic ) ) );
			if ( '' !== $topic && strlen( $topic ) <= 80 && ! in_array( strtolower( $topic ), array_map( 'strtolower', $clean ), true ) ) {
				$clean[] = $topic;
			}
		}
		update_option(
			self::OPTION,
			array(
				'focus'      => $focus,
				'topics'     => array_slice( $clean, 0, 8 ),
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
		return self::get();
	}

	/**
	 * The focus text for prompts ('' when none).
	 *
	 * @return string
	 */
	public static function text() {
		return self::get()['focus'];
	}

	/**
	 * Topics to look for places with: the saved ones, else what the business offers.
	 *
	 * @return string[]
	 */
	public static function topics() {
		$topics = self::get()['topics'];
		if ( array() !== $topics ) {
			return $topics;
		}
		$context = function_exists( 'qcld_wpbot_core_context' ) ? qcld_wpbot_core_context() : array();
		return isset( $context['summary']['offers'] ) ? array_slice( (array) $context['summary']['offers'], 0, 4 ) : array();
	}

	/**
	 * Significant lowercase words of the focus, topics and offers (for fit).
	 *
	 * @return string[]
	 */
	public static function words() {
		$text = self::text() . ' ' . implode( ' ', self::topics() );
		$all  = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values(
			array_unique(
				array_filter(
					(array) $all,
					static function ( $word ) {
						return strlen( $word ) > 3 && ! in_array( $word, self::STOP, true );
					}
				)
			)
		);
	}

	/**
	 * Is a search one of the focus topics, or close to the focus?
	 *
	 * @param string $search Search.
	 * @return bool
	 */
	public static function is_focus_search( $search ) {
		$search = strtolower( trim( (string) $search ) );
		if ( in_array( $search, array_map( 'strtolower', self::topics() ), true ) ) {
			return true;
		}
		$have = preg_split( '/[^\p{L}\p{N}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY );
		return count( array_intersect( (array) $have, self::words() ) ) >= 2;
	}

	/**
	 * Focus suggestions from the Site Context (built by AI there), with their source.
	 *
	 * @return array[] focus, why, source.
	 */
	public static function suggestions() {
		$context = function_exists( 'qcld_wpbot_core_context' ) ? qcld_wpbot_core_context() : array();
		return isset( $context['summary']['focus_ideas'] ) ? (array) $context['summary']['focus_ideas'] : array();
	}
}
