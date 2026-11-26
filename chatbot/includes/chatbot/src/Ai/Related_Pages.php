<?php
/**
 * The old "related pages" links are retired (owner, 25 November 2026: "replace the old links with
 * the RAG based links. Whoever had the old one turned on should have this enabled.").
 *
 * The old links came from a plain WordPress keyword search on the visitor's message, so a message
 * made only of stop words ("who are you?") listed the newest pages. Knowledge base source links
 * (Knowledge_Step citations, AI Settings → Answers → "Show source links under answers") list the
 * pages an answer actually came from instead.
 *
 * - Once: a site that had the old links on for any provider gets source links on (only when they
 *   were turned off; the default is already on, and storing it would mark Answers as saved).
 * - Always: the old options read as off, so no old code path adds the keyword-search links (the
 *   hosts' bridge filter, the widget's get_relevant_pages call after a streamed reply, and the old
 *   per-provider handlers for requests the engine passes on). The saved values stay stored, so a
 *   rollback brings them back.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

use QuantumCloud\Chatbot\Conversation\Steps\Knowledge_Step;

defined( 'ABSPATH' ) || exit;

/**
 * Related pages retirement.
 */
final class Related_Pages {

	/**
	 * Set once the old setting was carried over: 'on' (the site had the old links on) or 'off'.
	 */
	const FLAG = 'qcld_wpbot_chat_related_pages_retired';

	/**
	 * The old "related pages" switches: OpenAI's, then each other provider's.
	 */
	const OLD_OPTIONS = array(
		'page_suggestion_enabled',
		'qcld_gemini_page_suggestion_enabled',
		'qcld_claude_page_suggestion_enabled',
		'qcld_grok_page_suggestion_enabled',
		'qcld_openrouter_page_suggestion_enabled',
		'qcld_mistral_page_suggestion_enabled',
		'qcld_ollama_page_suggestion_enabled',
		'qcld_deepseekai_page_suggestion_enabled',
	);

	/**
	 * Carry the old setting over (once), then keep the old links off.
	 *
	 * @return void
	 */
	public static function init() {
		self::migrate();
		foreach ( self::OLD_OPTIONS as $option ) {
			add_filter( 'pre_option_' . $option, array( __CLASS__, 'off' ) );
		}
	}

	/**
	 * Whether the site had the old links on for any provider (the stored values).
	 *
	 * @return bool
	 */
	public static function was_on() {
		foreach ( self::OLD_OPTIONS as $option ) {
			if ( '1' === (string) get_option( $option, '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Once per site: old links on → source links on. Runs before off() is hooked, so it reads the
	 * stored values.
	 *
	 * @return void
	 */
	public static function migrate() {
		if ( false !== get_option( self::FLAG, false ) ) {
			return;
		}
		$on = self::was_on();
		if ( $on && '0' === (string) get_option( Knowledge_Step::CITATIONS_OPTION, '1' ) ) {
			update_option( Knowledge_Step::CITATIONS_OPTION, '1', false );
		}
		update_option( self::FLAG, $on ? 'on' : 'off' );
	}

	/**
	 * The old switches read as off.
	 *
	 * @return string
	 */
	public static function off() {
		return '0';
	}
}
