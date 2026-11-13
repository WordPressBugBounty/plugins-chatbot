<?php
/**
 * The ChatBot FAQ (ChatBot Settings > FAQ) as knowledge.
 *
 * - Read: the questions and answers saved in the options support_query and
 *   support_ans. WPBot Pro stores one list per language (locale => list),
 *   WPBot Free one list; either may be serialized.
 * - Knowledge base: the source "faq" (one item per question; the owner can
 *   turn it off in Knowledge Base > Sources to include, option rag_embed_faq,
 *   on by default). Saving the FAQ updates the knowledge base at once: new and
 *   changed questions are stored (and embedded), removed ones are deleted.
 * - Search: match() finds the FAQ entry a visitor's question asks for, so the
 *   chatbot's search answers from the FAQ before searching pages.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Knowledge;

use QuantumCloud\Chatbot\Conversation\Steps\Search_Step;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ as knowledge.
 */
final class Faq {

	/**
	 * Knowledge source id.
	 */
	const SOURCE = 'faq';

	/**
	 * Option that turns the knowledge source off ('0'); unset means on.
	 */
	const OPTION = 'rag_embed_faq';

	/**
	 * Whether a sync is already queued for this request.
	 *
	 * @var bool
	 */
	private static $queued = false;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/kb_sources', array( __CLASS__, 'sources' ) );
		foreach ( array( 'support_query', 'support_ans', self::OPTION ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'queue_sync' ) );
			add_action( 'update_option_' . $option, array( __CLASS__, 'queue_sync' ) );
			add_action( 'delete_option_' . $option, array( __CLASS__, 'queue_sync' ) );
		}
		// Knowledge Base > Sources to include posts "rag_embed_faq" with the hosts' own settings (same nonce).
		add_action( 'wp_ajax_qcld_rag_settings_option', array( __CLASS__, 'save_setting' ), 1 );
	}

	/**
	 * Save the "FAQ" source switch from Knowledge Base > Sources to include. The host's own
	 * handler runs next and saves the other sources.
	 *
	 * @return void
	 */
	public static function save_setting() {
		if ( ! isset( $_POST['rag_embed_faq'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
			return;
		}
		update_option( self::OPTION, '1' === sanitize_text_field( wp_unslash( $_POST['rag_embed_faq'] ) ) ? '1' : '0', false );
	}

	/**
	 * Whether the FAQ is a knowledge source.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$value = get_option( self::OPTION, '1' );
		return ! in_array( $value, array( 0, '0', '', false, 'no', 'off' ), true );
	}

	/**
	 * Every FAQ entry.
	 *
	 * @param string $language Locale (en_US) or language (en) to read; '' for every language.
	 * @return array[] Each: id, locale, question, answer (HTML as saved).
	 */
	public static function entries( $language = '' ) {
		$questions = self::lists( get_option( 'support_query', array() ) );
		$answers   = self::lists( get_option( 'support_ans', array() ) );
		if ( '' !== $language ) {
			$locale = self::pick_locale( array_keys( $questions ), (string) $language );
			if ( null === $locale ) {
				return array();
			}
			$questions = array( $locale => $questions[ $locale ] );
		}
		$entries = array();
		$seen    = array();
		foreach ( $questions as $locale => $list ) {
			$list_answers = isset( $answers[ $locale ] ) ? $answers[ $locale ] : array();
			foreach ( $list as $index => $question ) {
				$question = trim( wp_strip_all_tags( html_entity_decode( (string) $question, ENT_QUOTES, 'UTF-8' ) ) );
				$answer   = isset( $list_answers[ $index ] ) ? trim( (string) $list_answers[ $index ] ) : '';
				if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
					continue;
				}
				$id = self::id( (string) $locale, $question );
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$entries[]   = array(
					'id'       => $id,
					'locale'   => (string) $locale,
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}//end foreach
		return $entries;
	}

	/**
	 * Knowledge source "faq", unless the owner turned it off.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function sources( $sources ) {
		if ( ! self::enabled() ) {
			return $sources;
		}
		$sources[ self::SOURCE ] = array(
			'label' => __( 'FAQ', 'chatbot' ),
			'count' => static function () {
				return count( self::entries() );
			},
			'items' => static function ( $offset, $limit ) {
				return array_map( array( __CLASS__, 'item' ), array_slice( self::entries(), (int) $offset, (int) $limit ) );
			},
		);
		return $sources;
	}

	/**
	 * One FAQ entry as a knowledge item.
	 *
	 * @param array $entry Entry from entries().
	 * @return array
	 */
	public static function item( array $entry ) {
		$answer = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $entry['answer'], ENT_QUOTES, 'UTF-8' ) ) ) );
		return array(
			'id'       => $entry['id'],
			'title'    => $entry['question'],
			'url'      => '',
			'content'  => 'Q: ' . $entry['question'] . "\nA: " . $answer,
			'language' => strtolower( substr( $entry['locale'], 0, 2 ) ),
		);
	}

	/**
	 * Sync the knowledge base once, after the FAQ options are saved (end of the request).
	 *
	 * @return void
	 */
	public static function queue_sync() {
		if ( self::$queued ) {
			return;
		}
		self::$queued = true;
		add_action( 'shutdown', array( __CLASS__, 'sync' ) );
	}

	/**
	 * Bring the knowledge base in line with the FAQ: store new and changed questions, delete the rest.
	 *
	 * @return array{stored:int,removed:int}|null Null when the knowledge base is not in use.
	 */
	public static function sync() {
		if ( ! Kb_Bridge::active() || ! function_exists( 'qcld_wpbot_core_kb_store' ) || ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Store' ) ) {
			return null;
		}
		$store   = '\QuantumCloud\WPBotCore\Knowledge\Kb_Store';
		$entries = self::enabled() ? self::entries() : array();
		$keep    = array();
		$stored  = 0;
		foreach ( $entries as $entry ) {
			$keep[ $entry['id'] ] = true;
			if ( 'stored' === qcld_wpbot_core_kb_store( self::SOURCE, self::item( $entry ) ) ) {
				++$stored;
			}
		}
		$removed = 0;
		foreach ( self::stored_ids() as $id ) {
			if ( ! isset( $keep[ $id ] ) && $store::remove( self::SOURCE, $id ) ) {
				++$removed;
			}
		}
		return array(
			'stored'  => $stored,
			'removed' => $removed,
		);
	}

	/**
	 * The FAQ entry a visitor's question asks for.
	 *
	 * The question must share at least half of its own words (search words, stop words left
	 * out) with the visitor's text, and the visitor's text at least half of its words with the
	 * question, so a long FAQ question is not matched by one word.
	 *
	 * @param string $text     Visitor's text.
	 * @param string $language Locale or language ('' for the site's).
	 * @return array|null Entry from entries(), or null.
	 */
	public static function match( $text, $language = '' ) {
		$text  = trim( (string) $text );
		$words = Search_Step::terms( $text );
		if ( '' === $text ) {
			return null;
		}
		$entries = self::entries( '' !== $language ? $language : get_locale() );
		if ( ! $entries ) {
			$entries = self::entries();
		}
		$plain = self::plain( $text );
		$best  = null;
		$score = 0.0;
		foreach ( $entries as $entry ) {
			if ( self::plain( $entry['question'] ) === $plain ) {
				return $entry;
			}
			$terms = Search_Step::terms( $entry['question'] );
			if ( ! $terms || ! $words ) {
				continue;
			}
			$shared = count( array_unique( array_intersect( $terms, $words ) ) );
			if ( $shared < Search_Step::needed( count( $terms ) ) || $shared < Search_Step::needed( count( $words ) ) ) {
				continue;
			}
			$value = $shared / count( $terms ) + $shared / count( $words );
			if ( $value > $score ) {
				$score = $value;
				$best  = $entry;
			}
		}
		/**
		 * FAQ entry that answers a visitor's question (null for none).
		 *
		 * @param array|null $best     Entry: id, locale, question, answer.
		 * @param string     $text     Visitor's text.
		 * @param string     $language Locale or language.
		 */
		return apply_filters( 'qcld_wpbot_chat/faq_match', $best, $text, $language );
	}

	/**
	 * The answer of an FAQ entry as chat HTML: the question as a heading, then the answer.
	 *
	 * @param array $entry Entry from entries().
	 * @return string
	 */
	public static function answer_html( array $entry ) {
		return '<div class="wpb-faq-answer" role="group" aria-label="' . esc_attr( $entry['question'] ) . '">'
			. '<p class="wpb-faq-answer__question"><strong>' . esc_html( $entry['question'] ) . '</strong></p>'
			. '<div class="wpb-faq-answer__text">' . wp_kses_post( wpautop( $entry['answer'] ) ) . '</div>'
			. '</div>';
	}

	/**
	 * Ids of the FAQ items in the knowledge base.
	 *
	 * @return string[]
	 */
	private static function stored_ids() {
		$store = '\QuantumCloud\WPBotCore\Knowledge\Kb_Store';
		$ids   = array();
		$page  = 1;
		do {
			$found = $store::items(
				array(
					'type'     => self::SOURCE,
					'per_page' => 100,
					'page'     => $page,
				)
			);
			foreach ( $found['items'] as $row ) {
				$ids[] = (string) $row['source_id'];
			}
			$batch = count( $found['items'] );
			++$page;
		} while ( 100 === $batch && $page < 100 );
		return $ids;
	}

	/**
	 * The saved option as locale => list of strings ('' for a list without languages).
	 *
	 * @param mixed $value Option value.
	 * @return array<string,string[]>
	 */
	private static function lists( $value ) {
		$value = maybe_unserialize( $value );
		if ( ! is_array( $value ) || ! $value ) {
			return array();
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( $is_list && ! is_array( reset( $value ) ) ) {
			return array( '' => array_values( array_map( 'strval', $value ) ) );
		}
		$lists = array();
		foreach ( $value as $locale => $list ) {
			if ( is_array( $list ) ) {
				$lists[ trim( (string) $locale ) ] = array_values( array_map( 'strval', $list ) );
			}
		}
		return $lists;
	}

	/**
	 * The stored locale for a language: exact (en_US), same language (en), or the only list.
	 *
	 * @param string[] $locales  Stored locales.
	 * @param string   $language Wanted locale or language.
	 * @return string|null
	 */
	private static function pick_locale( array $locales, $language ) {
		if ( in_array( $language, $locales, true ) ) {
			return $language;
		}
		$short = strtolower( substr( $language, 0, 2 ) );
		foreach ( $locales as $locale ) {
			if ( strtolower( substr( (string) $locale, 0, 2 ) ) === $short ) {
				return (string) $locale;
			}
		}
		return 1 === count( $locales ) ? (string) reset( $locales ) : null;
	}

	/**
	 * Stable id of an entry: the same question in the same language keeps its id when reordered.
	 *
	 * @param string $locale   Locale.
	 * @param string $question Question.
	 * @return string
	 */
	private static function id( $locale, $question ) {
		return substr( md5( strtolower( $locale ) . '|' . self::plain( $question ) ), 0, 16 );
	}

	/**
	 * Lowercase words only, for exact comparisons.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) );
	}
}
