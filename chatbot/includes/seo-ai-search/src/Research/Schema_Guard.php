<?php
/**
 * Honest numbers for keyword research (S14, brief 3.4).
 *
 * Every AI research answer is checked against a strict schema before it is
 * stored or shown. Demand and difficulty are the enums high, medium, low;
 * intent is one of four words. The schema has no numeric volume,
 * difficulty or cost-per-click field: anything outside the schema is
 * dropped, numbers in label fields become empty, and sentences in reasons
 * that state search numbers or prices are removed. Exact numbers come only
 * from a keyword data provider, Search Console or Google Ads, elsewhere.
 *
 * Pure logic, unit-tested.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and cleans AI research output.
 */
final class Schema_Guard {

	const LEVELS     = array( 'high', 'medium', 'low' );
	const INTENTS    = array( 'informational', 'commercial', 'transactional', 'navigational' );
	const PAGE_TYPES = array( 'guide', 'how_to', 'list', 'comparison', 'review', 'product', 'category', 'landing', 'faq', 'tool', 'news', 'other' );

	/**
	 * Max items per list.
	 */
	const MAX_ITEMS = 60;

	/**
	 * JSON shape the AI must return, as text for the prompt.
	 *
	 * @param string $kind keywords, niche, gap or enrich.
	 * @return string
	 */
	public static function shape( $kind ) {
		$item = '{"keyword": "...", "intent": "informational|commercial|transactional|navigational", "demand": "high|medium|low", "difficulty": "high|medium|low", "reason": "one short sentence"}';
		switch ( $kind ) {
			case 'niche':
				return '{"overview": "...", "subtopics": [{"name": "...", "why": "..."}], "gaps": [{"topic": "...", "why": "..."}], "first_cluster": [{"title": "...", "keyword": "...", "intent": "...", "page_type": "guide|how_to|list|comparison|review|product|category|landing|faq|tool|news|other"}], "monetisation": ["..."]}';
			case 'gap':
				return '{"gaps": [{"keyword": "...", "intent": "...", "demand": "high|medium|low", "page_type": "...", "why": "..."}], "shared": [{"keyword": "..."}], "advantages": ["..."], "plan": ["..."]}';
			case 'enrich':
				return '{"items": [{"keyword": "...", "intent": "...", "cluster": "...", "reason": "..."}]}';
			default:
				return '{"related": [' . $item . '], "long_tail": [' . $item . '], "questions": [' . $item . '], "clusters": [{"name": "...", "keywords": ["..."]}]}';
		}
	}

	/**
	 * Clean an AI answer of the given kind.
	 *
	 * @param string $kind keywords, niche, gap or enrich.
	 * @param mixed  $data Decoded JSON.
	 * @return array Clean result (only schema fields).
	 */
	public static function clean( $kind, $data ) {
		$data = is_array( $data ) ? $data : array();
		switch ( $kind ) {
			case 'niche':
				return array(
					'overview'      => self::text( self::pick( $data, 'overview' ), 1500 ),
					'subtopics'     => self::rows(
						self::pick( $data, 'subtopics' ),
						static function ( $row ) {
							return array(
								'name' => self::text( self::pick( $row, 'name' ), 120 ),
								'why'  => self::text( self::pick( $row, 'why' ), 300 ),
							);
						},
						'name',
						20
					),
					'gaps'          => self::rows(
						self::pick( $data, 'gaps' ),
						static function ( $row ) {
							return array(
								'topic' => self::text( self::pick( $row, 'topic' ), 120 ),
								'why'   => self::text( self::pick( $row, 'why' ), 300 ),
							);
						},
						'topic',
						20
					),
					'first_cluster' => self::rows(
						self::pick( $data, 'first_cluster' ),
						static function ( $row ) {
							return array(
								'title'     => self::text( self::pick( $row, 'title' ), 160 ),
								'keyword'   => self::keyword( self::pick( $row, 'keyword' ) ),
								'intent'    => self::enum( self::pick( $row, 'intent' ), self::INTENTS ),
								'page_type' => self::enum( self::pick( $row, 'page_type' ), self::PAGE_TYPES, 'other' ),
							);
						},
						'title',
						20
					),
					'monetisation'  => self::strings( self::pick( $data, 'monetisation' ), 8, 300 ),
				);
			case 'gap':
				return array(
					'gaps'       => self::rows(
						self::pick( $data, 'gaps' ),
						static function ( $row ) {
							return array(
								'keyword'   => self::keyword( self::pick( $row, 'keyword' ) ),
								'intent'    => self::enum( self::pick( $row, 'intent' ), self::INTENTS ),
								'demand'    => self::enum( self::pick( $row, 'demand' ), self::LEVELS ),
								'page_type' => self::enum( self::pick( $row, 'page_type' ), self::PAGE_TYPES, 'other' ),
								'why'       => self::text( self::pick( $row, 'why' ), 300 ),
							);
						},
						'keyword'
					),
					'shared'     => self::rows(
						self::pick( $data, 'shared' ),
						static function ( $row ) {
							return array( 'keyword' => self::keyword( is_array( $row ) ? self::pick( $row, 'keyword' ) : $row ) );
						},
						'keyword'
					),
					'advantages' => self::strings( self::pick( $data, 'advantages' ), 8, 300 ),
					'plan'       => self::strings( self::pick( $data, 'plan' ), 10, 300 ),
				);
			case 'enrich':
				return array(
					'items' => self::rows(
						self::pick( $data, 'items' ),
						static function ( $row ) {
							return array(
								'keyword' => self::keyword( self::pick( $row, 'keyword' ) ),
								'intent'  => self::enum( self::pick( $row, 'intent' ), self::INTENTS ),
								'cluster' => self::text( self::pick( $row, 'cluster' ), 80 ),
								'reason'  => self::text( self::pick( $row, 'reason' ), 200 ),
							);
						},
						'keyword',
						200
					),
				);
			default:
				$item     = static function ( $row ) {
					return array(
						'keyword'    => self::keyword( is_array( $row ) ? self::pick( $row, 'keyword' ) : $row ),
						'intent'     => self::enum( self::pick( $row, 'intent' ), self::INTENTS ),
						'demand'     => self::enum( self::pick( $row, 'demand' ), self::LEVELS ),
						'difficulty' => self::enum( self::pick( $row, 'difficulty' ), self::LEVELS ),
						'reason'     => self::text( self::pick( $row, 'reason' ), 200 ),
					);
				};
				$clusters = self::rows(
					self::pick( $data, 'clusters' ),
					static function ( $row ) {
						return array(
							'name'     => self::text( self::pick( $row, 'name' ), 80 ),
							'keywords' => array_values( array_filter( array_map( array( __CLASS__, 'keyword' ), array_slice( self::listing( self::pick( $row, 'keywords' ) ), 0, 30 ) ), 'strlen' ) ),
						);
					},
					'name',
					20
				);
				return array(
					'related'   => self::rows( self::pick( $data, 'related' ), $item, 'keyword' ),
					'long_tail' => self::rows( self::pick( $data, 'long_tail' ), $item, 'keyword' ),
					'questions' => self::rows( self::pick( $data, 'questions' ), $item, 'keyword' ),
					'clusters'  => $clusters,
				);
		}
	}

	/**
	 * Remove sentences that state search numbers, volumes or prices, so a
	 * reason never smuggles an invented number onto the screen.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function strip_numbers( $text ) {
		$sentences = preg_split( '/(?<=[.!?])\s+/u', (string) $text );
		$kept      = array();
		foreach ( (array) $sentences as $sentence ) {
			$numeric_claim = preg_match( '/\d[\d,.\s]*\s*(k\b|m\b|%|searches|search|monthly|per month|a month|\/mo|volume|visits|clicks|cpc|cost per click|difficulty|kd\b|usd|dollars?|eur|euros?)/iu', $sentence )
				|| preg_match( '/[$€£]\s*\d/u', $sentence )
				|| preg_match( '/(volume|difficulty|cpc|searches)\s*(of|:|is|around|about)?\s*~?\d/iu', $sentence );
			if ( ! $numeric_claim ) {
				$kept[] = $sentence;
			}
		}
		return trim( implode( ' ', $kept ) );
	}

	/**
	 * Value from an array, or null.
	 *
	 * @param mixed  $data Array.
	 * @param string $key  Key.
	 * @return mixed
	 */
	private static function pick( $data, $key ) {
		return is_array( $data ) && array_key_exists( $key, $data ) ? $data[ $key ] : null;
	}

	/**
	 * A list (array) or an empty one.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	private static function listing( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * Map a list of rows through a cleaner, drop rows without the key field
	 * and duplicates.
	 *
	 * @param mixed    $rows  Rows.
	 * @param callable $map   Cleaner.
	 * @param string   $field Required field.
	 * @param int      $max   Max rows.
	 * @return array[]
	 */
	private static function rows( $rows, callable $map, $field, $max = self::MAX_ITEMS ) {
		$out  = array();
		$seen = array();
		foreach ( self::listing( $rows ) as $row ) {
			$clean = $map( $row );
			$key   = strtolower( (string) $clean[ $field ] );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $clean;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * A list of short texts.
	 *
	 * @param mixed $value Value.
	 * @param int   $max   Max items.
	 * @param int   $len   Max length.
	 * @return string[]
	 */
	private static function strings( $value, $max, $len ) {
		$out = array();
		foreach ( self::listing( $value ) as $item ) {
			$text = is_scalar( $item ) ? self::text( $item, $len ) : '';
			if ( '' !== $text ) {
				$out[] = $text;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Plain text without number claims, capped.
	 *
	 * @param mixed $value Value.
	 * @param int   $len   Max length.
	 * @return string
	 */
	private static function text( $value, $len ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$text = self::strip_numbers( sanitize_text_field( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len ) : substr( $text, 0, $len );
	}

	/**
	 * A keyword: plain text, lower case, single spaces, at most 120 characters.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function keyword( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $value ) ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
	}

	/**
	 * One of the allowed values, or the default. Numbers are never allowed.
	 *
	 * @param mixed    $value   Value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $fallback Default.
	 * @return string
	 */
	private static function enum( $value, array $allowed, $fallback = '' ) {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}
		$value = str_replace( array( '-', ' ' ), '_', strtolower( trim( $value ) ) );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
