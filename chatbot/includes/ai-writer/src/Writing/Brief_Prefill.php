<?php
/**
 * Prefill for the brief's options (CR-9): main search, who it is for,
 * search words, length and tone, each with the name of its source.
 *
 * Free sources cost nothing and are read as the topic is typed: searches
 * people make on the site, Search Console searches, the Keyword Vault (all
 * through Topic Finder's stored ideas and WPBot Core's Vault), search
 * numbers from the keyword cache, and the Brand Voice. Extensions (Pro) add
 * more through `qcld_wpbot_writer/brief_prefill`. "Suggest with AI" fills
 * what is still empty with one small AI call, only on the owner's click.
 * The screen only fills empty fields; it never replaces what was typed.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Topics\Ideas;
use QuantumCloud\AiWriter\Topics\Keyword_Numbers;
use QuantumCloud\AiWriter\Topics\Vault_Link;
use QuantumCloud\AiWriter\Voice\Voices;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Brief prefill.
 */
final class Brief_Prefill {

	/**
	 * Words that say nothing about a topic.
	 *
	 * @var string[]
	 */
	const STOP = array( 'the', 'and', 'for', 'with', 'how', 'what', 'why', 'when', 'your', 'you', 'our', 'are', 'can', 'does', 'from', 'that', 'this', 'into', 'about', 'best', 'guide', 'tips', 'ways' );

	/**
	 * Suggestions from free sources.
	 *
	 * @param string $topic   Topic.
	 * @param array  $context main_query, type, keywords (already typed).
	 * @return array main_query, audience, keywords, words, tone: each null or array( value|values, source ).
	 */
	public static function suggest( $topic, array $context = array() ) {
		$topic = trim( sanitize_text_field( (string) $topic ) );
		$out   = array(
			'main_query' => null,
			'audience'   => null,
			'keywords'   => null,
			'words'      => null,
			'tone'       => null,
		);
		if ( strlen( $topic ) >= 4 ) {
			$found = self::searches( $topic );
			if ( array() !== $found ) {
				$out['main_query'] = array(
					'value'  => $found[0]['query'],
					'source' => $found[0]['source'],
				);
				$rest              = array_slice( $found, 1, 6 );
				if ( array() !== $rest ) {
					$out['keywords'] = array(
						'values'  => wp_list_pluck( $rest, 'query' ),
						'source'  => implode( ', ', array_values( array_unique( wp_list_pluck( $rest, 'source' ) ) ) ),
						'volumes' => array_values( wp_list_pluck( $rest, 'volume' ) ),
					);
				}
			}
		}
		$voice = Voices::site_voice();
		if ( $voice && ! empty( $voice['profile']['summary'] ) ) {
			$out['tone'] = array(
				'value'  => substr( (string) $voice['profile']['summary'], 0, 100 ),
				'source' => __( 'Brand Voice', 'chatbot' ),
			);
		}

		/**
		 * Brief prefill suggestions (AI Writer Pro adds the Site Profile
		 * audience, the voice of the content type and ranking-page length).
		 *
		 * @param array  $out     Suggestions so far.
		 * @param string $topic   Topic.
		 * @param array  $context Context.
		 */
		$out = (array) apply_filters( 'qcld_wpbot_writer/brief_prefill', $out, $topic, $context );
		return self::clean( $out );
	}

	/**
	 * Fill empty options with one small AI call (on the owner's click).
	 *
	 * @param string $topic Topic.
	 * @param array  $brief Brief so far (type, audience …).
	 * @return array|WP_Error Same shape as suggest(); source "AI suggestion".
	 */
	public static function suggest_with_ai( $topic, array $brief = array() ) {
		$topic = trim( sanitize_text_field( (string) $topic ) );
		if ( strlen( $topic ) < 4 ) {
			return new WP_Error( 'qcld_wpbot_writer_no_topic', __( 'Write the topic first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$voice  = Voices::site_voice();
		$system = "Task: brief_prefill\nSuggest options for an article brief. Base them on the topic and the business; do not invent facts or numbers.\n"
			. Prompts::language()
			. "\nAnswer with one JSON object: {\"main_query\":\"the search this page should be found for, 2-6 words\",\"audience\":\"who it is for, under 12 words\",\"keywords\":[\"up to 5 related searches\"],\"tone\":\"tone in under 8 words\"}.";
		$prompt = 'Topic: ' . $topic
			. "\nContent type: " . Prompts::type_label( isset( $brief['type'] ) ? (string) $brief['type'] : 'blog_post' )
			. "\n" . Prompts::business()
			. ( $voice && ! empty( $voice['profile']['summary'] ) ? "\nBrand voice: " . $voice['profile']['summary'] : '' );
		$result = Writer_AI::ask_json( 'brief_prefill', $system, $prompt, array( 'max_tokens' => 400 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data        = (array) $result['data'];
		$source      = __( 'AI suggestion', 'chatbot' );
		$out         = array(
			'main_query' => ! empty( $data['main_query'] ) ? array(
				'value'  => (string) $data['main_query'],
				'source' => $source,
			) : null,
			'audience'   => ! empty( $data['audience'] ) ? array(
				'value'  => (string) $data['audience'],
				'source' => $source,
			) : null,
			'keywords'   => ! empty( $data['keywords'] ) ? array(
				'values' => array_slice( array_map( 'strval', (array) $data['keywords'] ), 0, 5 ),
				'source' => $source,
			) : null,
			'words'      => null,
			'tone'       => ! empty( $data['tone'] ) ? array(
				'value'  => (string) $data['tone'],
				'source' => $source,
			) : null,
		);
		$out         = self::clean( $out );
		$out['cost'] = round( (float) $result['response']->cost, 4 );
		return $out;
	}

	/**
	 * Real searches that match the topic: site search, Search Console and
	 * the Keyword Vault, best match first, then most searched.
	 *
	 * @param string $topic Topic.
	 * @return array[] query, source (label), volume (int|null).
	 */
	public static function searches( $topic ) {
		$words = self::words( $topic );
		if ( array() === $words ) {
			return array();
		}
		$labels     = array(
			'search_console' => __( 'Search Console', 'chatbot' ),
			'search_log'     => __( 'Site search', 'chatbot' ),
			'seo'            => __( 'Keyword Vault', 'chatbot' ),
		);
		$candidates = array();
		foreach ( Ideas::by_sources( array_keys( $labels ), 300, array( 'new', 'planned', 'done' ) ) as $idea ) {
			$query = '' !== (string) $idea['main_query'] ? (string) $idea['main_query'] : (string) $idea['title'];
			$candidates[ Keyword_Numbers::normalise( $query ) ] = array(
				'query'  => $query,
				'source' => $labels[ $idea['source'] ],
				'volume' => isset( $idea['demand']['volume'] ) ? $idea['demand']['volume'] : null,
				'weight' => isset( $idea['metrics']['impressions'] ) ? (int) $idea['metrics']['impressions'] : (int) $idea['session_count'],
			);
		}
		if ( Vault_Link::available() ) {
			foreach ( $words as $word ) {
				foreach ( (array) qcld_wpbot_core_vault_query(
					array(
						'search' => $word,
						'limit'  => 30,
					)
				) as $row ) {
					if ( is_array( $row ) && 'archived' !== $row['status'] ) {
						$candidates[ Keyword_Numbers::normalise( (string) $row['keyword'] ) ] = array(
							'query'  => (string) $row['keyword'],
							'source' => $labels['seo'],
							'volume' => null === $row['volume'] ? null : (int) $row['volume'],
							'weight' => 0,
						);
					}
				}
			}
		}
		$numbers = Keyword_Numbers::lookup( array_keys( $candidates ) );
		$scored  = array();
		foreach ( $candidates as $key => $candidate ) {
			$cw = self::words( $candidate['query'] );
			if ( array() === $cw || count( $cw ) > 8 ) {
				continue;
			}
			$shared = count( array_intersect( $words, $cw ) );
			// At least half the topic's words; closest match first (extra words count against it).
			if ( $shared < 1 || $shared / max( count( $words ), 1 ) < 0.5 ) {
				continue;
			}
			$score = $shared / count( array_unique( array_merge( $words, $cw ) ) );
			if ( null === $candidate['volume'] && isset( $numbers[ $key ] ) ) {
				$candidate['volume'] = $numbers[ $key ]['volume'];
			}
			$candidate['score'] = $score;
			$scored[]           = $candidate;
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a['score'] !== $b['score'] ) {
					return $a['score'] < $b['score'] ? 1 : -1;
				}
				$va = null === $a['volume'] ? -1 : $a['volume'];
				$vb = null === $b['volume'] ? -1 : $b['volume'];
				return $vb !== $va ? $vb - $va : $b['weight'] - $a['weight'];
			}
		);
		return array_map(
			static function ( $row ) {
				return array(
					'query'  => $row['query'],
					'source' => $row['source'],
					'volume' => $row['volume'],
				);
			},
			array_slice( $scored, 0, 8 )
		);
	}

	/**
	 * Meaningful words of a text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function words( $text ) {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array();
		foreach ( (array) $parts as $word ) {
			if ( strlen( $word ) >= 3 && ! in_array( $word, self::STOP, true ) ) {
				$words[] = $word;
			}
		}
		return array_values( array_unique( $words ) );
	}

	/**
	 * Clean suggestions for the browser.
	 *
	 * @param array $out Suggestions.
	 * @return array
	 */
	private static function clean( array $out ) {
		$clean = array();
		foreach ( array( 'main_query', 'audience', 'keywords', 'words', 'tone' ) as $field ) {
			$item = isset( $out[ $field ] ) && is_array( $out[ $field ] ) ? $out[ $field ] : null;
			if ( ! $item ) {
				$clean[ $field ] = null;
				continue;
			}
			$source = isset( $item['source'] ) ? substr( sanitize_text_field( (string) $item['source'] ), 0, 80 ) : '';
			if ( 'keywords' === $field ) {
				$values          = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', isset( $item['values'] ) ? (array) $item['values'] : array() ) ) ) ), 0, 8 );
				$clean[ $field ] = array() === $values ? null : array(
					'values' => $values,
					'source' => $source,
				);
				continue;
			}
			$value           = 'words' === $field ? min( 10000, absint( isset( $item['value'] ) ? $item['value'] : 0 ) ) : substr( sanitize_text_field( isset( $item['value'] ) ? (string) $item['value'] : '' ), 0, 191 );
			$clean[ $field ] = ( 'words' === $field ? $value >= 100 : '' !== $value ) ? array(
				'value'  => $value,
				'source' => $source,
			) : null;
		}
		return $clean;
	}
}
