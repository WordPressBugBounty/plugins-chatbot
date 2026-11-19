<?php
/**
 * Safe-answer mode (chatbot features CB1b; replaces the Context Aware Start Menu).
 *
 * The single AI call says whether it was sure: when a question is not covered, the AI ends its
 * reply with a marker the visitor never sees. The engine then marks the reply not grounded, adds
 * the handoff buttons the owner chose (start-menu items such as "Send us email" or "Leave your
 * number"), and logs the question as a knowledge gap (Questions Not Answered).
 *
 * Settings (Common AI Settings, next to Answer scope):
 * - qcld_wpbot_handoff_items: start-menu item labels shown when the bot is not sure.
 * - qcld_wpbot_handoff_max: most buttons (default 3).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Safe answers.
 */
final class Safe_Answer {

	/**
	 * Marker the AI adds when it is not sure (removed before the visitor sees the reply).
	 */
	const MARKER = '[[NOT_SURE]]';

	/**
	 * Option: start-menu item labels offered when the bot is not sure.
	 */
	const ITEMS_OPTION = 'qcld_wpbot_handoff_items';

	/**
	 * Option: most handoff buttons.
	 */
	const MAX_OPTION = 'qcld_wpbot_handoff_max';

	/**
	 * Rule added to the system prompt.
	 *
	 * @return string
	 */
	public static function rule() {
		return 'If you cannot answer a question about the business from the information you were given (whenever you would say you do not have that information or are not sure), say so briefly and end your reply with ' . self::MARKER . ' on its own. Never write ' . self::MARKER . ' otherwise.';
	}

	/**
	 * Remove the marker; report whether it was there.
	 *
	 * @param string $text Reply text.
	 * @return array { text, not_sure }
	 */
	public static function strip( $text ) {
		$text     = (string) $text;
		$not_sure = false !== strpos( $text, self::MARKER );
		return array(
			'text'     => $not_sure ? trim( str_replace( self::MARKER, '', $text ) ) : $text,
			'not_sure' => $not_sure,
		);
	}

	/**
	 * Phrases that mean the AI could not answer (English). A fallback for models that write
	 * "I don't have information on that" instead of the marker (QA 9.1: Train stayed empty).
	 *
	 * @return string[] Regular expressions.
	 */
	public static function unsure_phrases() {
		$phrases = array(
			'/\bI (?:do not|don\'t) have (?:that|any|specific|enough|the|more|further|detailed|this)?\s*(?:specific |detailed |exact |current )?(?:information|info|details)\b/i',
			'/\bI(?:\'m| am) not sure\b/i',
			'/\bI (?:could not|couldn\'t|cannot|can\'t|was unable to|wasn\'t able to) find\b/i',
			'/\bI(?:\'m| am) (?:sorry, )?(?:unable|not able) to (?:find|answer|provide)\b/i',
			'/\bI (?:do not|don\'t) know\b/i',
			'/\b(?:there is|there\'s) no (?:information|mention|details?) (?:about|on|of|regarding)\b/i',
			'/\b(?:does not|doesn\'t|do not|don\'t) (?:include|contain|mention|cover|provide) (?:any )?(?:information|details)\b/i',
		);
		/**
		 * Phrases (regular expressions) that mark an AI reply as "not sure" when the model left
		 * out the marker. Return an empty list to turn the fallback off.
		 *
		 * @param string[] $phrases Regular expressions.
		 */
		return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_chat/not_sure_phrases', $phrases ) ), 'strlen' ) );
	}

	/**
	 * Whether a reply without the marker still says the AI could not answer. Conservative: only
	 * short replies (the model said it does not know, rather than answering at length with one
	 * caveat) and only the phrases above.
	 *
	 * @param string $text Reply text (marker already removed).
	 * @return bool
	 */
	public static function sounds_unsure( $text ) {
		$text   = trim( str_replace( array( "\u{2019}", "\u{2018}" ), "'", wp_strip_all_tags( (string) $text ) ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		$unsure = false;
		if ( '' !== $text && $length <= 400 ) {
			foreach ( self::unsure_phrases() as $phrase ) {
				if ( @preg_match( $phrase, $text ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A filtered pattern may be invalid.
					$unsure = true;
					break;
				}
			}
		}
		/**
		 * Whether an AI reply without the marker counts as "not sure" (handoff buttons, knowledge gap).
		 *
		 * @param bool   $unsure Default from the phrases.
		 * @param string $text   Reply text.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/reply_not_sure', $unsure, $text );
	}

	/**
	 * Wrap a stream callback so the marker never reaches the visitor. Returns the wrapped callback
	 * and a flush function to call after the stream.
	 *
	 * @param callable $on_text Callback.
	 * @return callable[] { 0: callable, 1: callable }
	 */
	public static function stream_guard( $on_text ) {
		$hold  = '';
		$send  = static function ( $piece ) use ( $on_text, &$hold ) {
			$hold .= (string) $piece;
			$hold  = str_replace( self::MARKER, '', $hold );
			// Keep back the longest tail that could still become the marker.
			$length = strlen( $hold );
			$cut    = $length;
			for ( $i = max( 0, $length - strlen( self::MARKER ) + 1 ); $i < $length; $i++ ) {
				if ( 0 === strpos( self::MARKER, substr( $hold, $i ) ) ) {
					$cut = $i;
					break;
				}
			}
			$out  = substr( $hold, 0, $cut );
			$hold = substr( $hold, $cut );
			if ( '' !== $out ) {
				call_user_func( $on_text, $out );
			}
		};
		$flush = static function () use ( $on_text, &$hold ) {
			$rest = str_replace( self::MARKER, '', $hold );
			$hold = '';
			if ( '' !== trim( $rest ) ) {
				call_user_func( $on_text, $rest );
			}
		};
		return array( $send, $flush );
	}

	/**
	 * Start-menu items (label => HTML) from the host's start menu option.
	 *
	 * @return array<string,string>
	 */
	public static function menu_items() {
		$menu  = maybe_unserialize( get_option( 'qc_wpbot_menu_order', '' ) );
		$menu  = is_array( $menu ) ? implode( '', array_map( 'strval', $menu ) ) : (string) $menu;
		$items = array();
		if ( preg_match_all( '#<span\b[^>]*>(.*?)</span>#is', $menu, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$label = trim( wp_strip_all_tags( $match[1] ) );
				if ( '' !== $label && ! isset( $items[ $label ] ) ) {
					$items[ $label ] = $match[0];
				}
			}
		}
		return $items;
	}

	/**
	 * The handoff buttons for a not-sure reply: { label, html } of the chosen start-menu items.
	 *
	 * @return array[]
	 */
	public static function handoff() {
		$chosen = get_option( self::ITEMS_OPTION, null );
		$items  = self::menu_items();
		// Saved choices that all left the start menu (renamed items): use the defaults again.
		if ( is_array( $chosen ) && array() !== $chosen && array() === array_intersect( array_map( 'strval', $chosen ), array_keys( $items ) ) ) {
			$chosen = null;
		}
		if ( null === $chosen ) {
			// Default: the support items a fresh site has (email, phone, feedback), when present.
			$chosen = array_values(
				array_filter(
					array_keys( $items ),
					static function ( $label ) {
						return (bool) preg_match( '/mail|number|phone|call|feedback|contact/i', $label );
					}
				)
			);
		}
		$max     = max( 1, min( 6, (int) get_option( self::MAX_OPTION, 3 ) ) );
		$buttons = array();
		foreach ( (array) $chosen as $label ) {
			if ( isset( $items[ $label ] ) && count( $buttons ) < $max ) {
				$buttons[] = array(
					'label' => (string) $label,
					'html'  => $items[ $label ],
				);
			}
		}
		/**
		 * Handoff buttons shown when the bot is not sure (CB4 adds Slack, Discord, Telegram chat).
		 *
		 * @param array[] $buttons { label, html }.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/handoff_buttons', $buttons );
	}
}
