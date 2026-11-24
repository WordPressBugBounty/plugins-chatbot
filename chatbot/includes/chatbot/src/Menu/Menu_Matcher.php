<?php
/**
 * Context Aware Start Menu, version 2: the matcher (CA1).
 *
 * Picks the start menu items closest to a question and its answer, without any AI call: each item has
 * a profile (description and keywords per language, written once by AI when the menu is saved and
 * editable by the owner, or derived from the item when there is none), and a message is matched by
 * word overlap with light stemming. Pro adds vector similarity through a filter.
 *
 * Version 1 (`qcld_dynamic_ai_menu`) made a second AI call per message, lived only in five provider
 * handlers, offered items that didn't exist and fought Answer scope; see botmaster
 * `docs/chatbot-features/context-aware-start-menu.md`.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Menu;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Start menu item matcher.
 */
final class Menu_Matcher {

	/**
	 * Option: Context Aware settings.
	 */
	const SETTINGS = 'qcld_wpbot_context_menu';

	/**
	 * Option: item profiles (item key => { locale => { description, keywords[] } }).
	 */
	const PROFILES = 'qcld_wpbot_menu_profiles';

	/**
	 * Minimum score per relevance level.
	 */
	const THRESHOLDS = array(
		'low'    => 1.0,
		'medium' => 2.0,
		'high'   => 3.0,
	);

	/**
	 * Default keywords of built-in items (English; profiles add other languages).
	 */
	const BUILTIN_KEYWORDS = array(
		'site_search'    => array( 'search', 'find', 'look', 'page', 'article', 'post' ),
		'faq'            => array( 'faq', 'question', 'questions', 'help', 'common' ),
		'subscription'   => array( 'subscribe', 'newsletter', 'update', 'updates', 'news', 'email' ),
		'str_categories' => array( 'topic', 'topics', 'category', 'categories' ),
		'voice_message'  => array( 'voice', 'audio', 'record', 'message' ),
		'messenger'      => array( 'messenger', 'facebook' ),
		'whatsapp'       => array( 'whatsapp' ),
		'send_email'     => array( 'email', 'mail', 'contact', 'write' ),
		'call_me'        => array( 'call', 'phone', 'number', 'callback' ),
		'feedback'       => array( 'feedback', 'suggestion', 'review', 'opinion' ),
	);

	/**
	 * Words that never count.
	 */
	const STOP_WORDS = array( 'a', 'an', 'the', 'and', 'or', 'but', 'if', 'of', 'to', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'it', 'its', 'this', 'that', 'these', 'those', 'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'they', 'them', 'do', 'does', 'did', 'can', 'could', 'would', 'should', 'will', 'how', 'what', 'when', 'where', 'which', 'who', 'why', 'there', 'here', 'have', 'has', 'had', 'not', 'no', 'yes', 'so', 'as', 'about', 'please', 'thanks', 'hi', 'hello', 'any', 'some', 'all', 'just', 'get', 'got', 'want', 'need', 'like' );

	/**
	 * Context Aware settings with their defaults (everything off until the owner switches it on: C5).
	 *
	 * @return array
	 */
	public static function settings() {
		$saved           = get_option( self::SETTINGS, array() );
		$settings        = array_merge(
			array(
				'enabled'       => false,
				'after_answers' => false,
				'page_aware'    => false,
				'order_rest'    => false,
				'max'           => 3,
				'threshold'     => 'medium',
				'more'          => true,
			),
			is_array( $saved ) ? $saved : array()
		);
		$settings['max'] = max( 1, min( 4, (int) $settings['max'] ) );
		// Never more than the 4 top-level places (M2).
		$settings['threshold'] = isset( self::THRESHOLDS[ $settings['threshold'] ] ) ? $settings['threshold'] : 'medium';
		return $settings;
	}

	/**
	 * Whether "Buttons after answers" is on.
	 *
	 * @return bool
	 */
	public static function after_answers_on() {
		$settings = self::settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['after_answers'] );
	}

	/**
	 * The items related to a question and its answer, best first.
	 *
	 * @param string $question Visitor's question.
	 * @param string $answer   The bot's answer.
	 * @param string $locale   Locale ('' for the site's).
	 * @param int    $max      Most items (0: from the settings).
	 * @return array[] { id, key, label, score }.
	 */
	public static function related( $question, $answer = '', $locale = '', $max = 0 ) {
		$locale   = '' !== $locale ? $locale : Options::locale();
		$settings = self::settings();
		$max      = $max > 0 ? min( 4, (int) $max ) : $settings['max'];
		$min      = self::THRESHOLDS[ $settings['threshold'] ];
		$q_words  = self::words( $question );
		$a_words  = self::words( $answer );
		if ( ! $q_words && ! $a_words ) {
			return array();
		}
		$scored = array();
		foreach ( Menu_Items::items() as $item ) {
			if ( ! Menu_Items::available( $item ) || self::is_handoff( $item ) ) {
				continue;
			}
			$label = Menu_Items::label( $item, $locale );
			if ( '' === $label ) {
				continue;
			}
			$keywords = self::keywords( $item, $locale, $label );
			$score    = 2 * count( array_intersect( $keywords, $q_words ) ) + count( array_intersect( $keywords, $a_words ) );
			/**
			 * A start menu item's relevance score (Pro adds vector similarity).
			 *
			 * @param float  $score    Word-overlap score (question words count twice).
			 * @param array  $item     Item.
			 * @param string $question Question.
			 * @param string $answer   Answer.
			 * @param string $locale   Locale.
			 */
			$score = (float) apply_filters( 'qcld_wpbot_chat/menu_match_score', (float) $score, $item, $question, $answer, $locale );
			if ( $score >= $min ) {
				$scored[] = array(
					'id'    => (string) $item['id'],
					'key'   => (string) $item['key'],
					'label' => $label,
					'score' => $score,
				);
			}
		}//end foreach
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		return array_slice( $scored, 0, $max );
	}

	/**
	 * `next_steps` entries for an answered question (empty when "Buttons after answers" is off).
	 *
	 * @param string $question Question.
	 * @param string $answer   Answer.
	 * @param string $locale   Locale.
	 * @return array[] { label, type: related, id }.
	 */
	public static function next_steps( $question, $answer, $locale = '' ) {
		if ( ! self::after_answers_on() ) {
			return array();
		}
		return array_map(
			static function ( $found ) {
				return array(
					'label' => $found['label'],
					'type'  => 'related',
					'id'    => $found['id'],
				);
			},
			self::related( $question, $answer, $locale )
		);
	}

	/**
	 * Handoff items (live chat, email us, call me) are offered by the escalation rules, never as
	 * related buttons.
	 *
	 * @param array $item Item.
	 * @return bool
	 */
	public static function is_handoff( array $item ) {
		$builtin = isset( $item['builtin'] ) ? (string) $item['builtin'] : '';
		$handoff = in_array( $builtin, array( 'send_email', 'call_me' ), true );
		if ( ! $handoff && 0 === strpos( (string) $item['key'], 'intent:' ) ) {
			$intent  = substr( (string) $item['key'], 7 );
			$handoff = in_array( $intent, array( 'livechat', 'live chat', strtolower( Options::text( 'qlcd_wp_chatbot_sys_key_livechat', '', 'livechat' ) ) ), true );
		}
		/**
		 * Whether a start menu item is a handoff item (shown by escalation rules only).
		 *
		 * @param bool  $handoff Default.
		 * @param array $item    Item.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/menu_item_handoff', $handoff, $item );
	}

	/**
	 * An item's keywords in a language: its profile when there is one, else its label words and the
	 * built-in defaults.
	 *
	 * @param array  $item   Item.
	 * @param string $locale Locale.
	 * @param string $label  Label in that language.
	 * @return string[]
	 */
	public static function keywords( array $item, $locale, $label ) {
		$profiles = get_option( self::PROFILES, array() );
		$profile  = is_array( $profiles ) && isset( $profiles[ $item['key'] ] ) && is_array( $profiles[ $item['key'] ] ) ? $profiles[ $item['key'] ] : array();
		$own      = isset( $profile[ $locale ] ) ? $profile[ $locale ] : ( $profile ? reset( $profile ) : array() );
		$text     = $label;
		if ( is_array( $own ) ) {
			$text .= ' ' . ( isset( $own['description'] ) ? (string) $own['description'] : '' );
			$text .= ' ' . implode( ' ', isset( $own['keywords'] ) ? array_map( 'strval', (array) $own['keywords'] ) : array() );
		}
		$builtin = isset( $item['builtin'] ) ? (string) $item['builtin'] : '';
		if ( isset( self::BUILTIN_KEYWORDS[ $builtin ] ) ) {
			$text .= ' ' . implode( ' ', self::BUILTIN_KEYWORDS[ $builtin ] );
		}
		return self::words( $text );
	}

	/**
	 * Distinct word stems of a text, without stop words and very short words.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function words( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( wp_strip_all_tags( (string) $text ), 'UTF-8' ) : strtolower( wp_strip_all_tags( (string) $text ) );
		$out  = array();
		foreach ( (array) preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			if ( in_array( $word, self::STOP_WORDS, true ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( $word, 'UTF-8' ) : strlen( $word ) ) < 3 ) {
				continue;
			}
			$out[ self::stem( $word ) ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Light English stemming (plural, -ing, -ed, -ly), so "orders", "ordering" and "ordered" match.
	 *
	 * @param string $word Lower-case word.
	 * @return string
	 */
	private static function stem( $word ) {
		foreach ( array(
			'ies' => 'y',
			'ing' => '',
			'ed'  => '',
			'ly'  => '',
			's'   => '',
		) as $suffix => $replace ) {
			$len = strlen( $suffix );
			if ( strlen( $word ) > $len + 2 && substr( $word, -$len ) === $suffix && ! ( 's' === $suffix && in_array( substr( $word, -2 ), array( 'ss', 'us', 'is' ), true ) ) ) {
				$stem = substr( $word, 0, -$len ) . $replace;
				// A doubled last consonant is undone (shipping and stopped become ship and stop), except ll, ss, zz and ff.
				if ( in_array( $suffix, array( 'ing', 'ed' ), true ) && preg_match( '/([b-df-hj-np-tv-z])\1$/', $stem ) && ! preg_match( '/(ll|ss|zz|ff)$/', $stem ) ) {
					$stem = substr( $stem, 0, -1 );
				}
				return $stem;
			}
		}
		return $word;
	}
}
