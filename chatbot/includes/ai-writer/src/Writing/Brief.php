<?php
/**
 * The writing brief: sanitising and the length target.
 *
 * Fields: type, topic, length (short|standard|long), words (exact, optional),
 * audience, keywords[], tone, post_type (post|page), sources {post_ids[],
 * document_ids[], chatbot, notes}, answers [{q, a}]; from SEO & AI Search
 * (round 2): intent, questions[], link_to[], link_from[], keyword_numbers
 * {search => volume, source}, numbers {label => value}, vault_id.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Knowledge\Documents;
use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Brief helpers.
 */
final class Brief {

	/**
	 * Search intents and the kind of page each one asks for.
	 *
	 * @var string[]
	 */
	const INTENTS = array( 'informational', 'commercial', 'transactional', 'navigational', 'local' );

	/**
	 * A valid intent, or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function intent( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, self::INTENTS, true ) ? $value : '';
	}

	/**
	 * Up to 10 questions to answer.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	public static function questions( $value ) {
		$out = array();
		foreach ( (array) $value as $question ) {
			$question = substr( sanitize_text_field( is_scalar( $question ) ? (string) $question : '' ), 0, 300 );
			if ( '' !== $question && ! in_array( $question, $out, true ) ) {
				$out[] = $question;
			}
		}
		return array_slice( $out, 0, 10 );
	}

	/**
	 * Up to 5 post ids.
	 *
	 * @param mixed $value Raw list.
	 * @return int[]
	 */
	public static function post_ids( $value ) {
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) ), 0, 5 );
	}

	/**
	 * Supporting searches: each a string or array( query, volume, volume_range, source ).
	 *
	 * @param array $items Raw items.
	 * @return array{queries: string[], numbers: array} queries (up to 10) and search => array( volume, volume_range, source ) for those with numbers.
	 */
	public static function supporting( array $items ) {
		$queries = array();
		$numbers = array();
		foreach ( $items as $item ) {
			$query = is_array( $item ) ? ( isset( $item['query'] ) ? $item['query'] : '' ) : $item;
			$query = substr( sanitize_text_field( is_scalar( $query ) ? (string) $query : '' ), 0, 191 );
			if ( '' === $query || in_array( $query, $queries, true ) ) {
				continue;
			}
			$queries[] = $query;
			if ( is_array( $item ) && ( isset( $item['volume'] ) || isset( $item['volume_range'] ) ) ) {
				$range             = isset( $item['volume_range'] ) && is_array( $item['volume_range'] ) && 2 === count( $item['volume_range'] ) ? array_map( 'absint', array_values( $item['volume_range'] ) ) : null;
				$numbers[ $query ] = array(
					'volume'       => isset( $item['volume'] ) && null !== $item['volume'] ? absint( $item['volume'] ) : null,
					'volume_range' => $range,
					'source'       => \QuantumCloud\AiWriter\Topics\Keyword_Numbers::source_label( isset( $item['source'] ) ? (string) $item['source'] : '' ),
				);
			}
			if ( count( $queries ) >= 10 ) {
				break;
			}
		}
		return array(
			'queries' => $queries,
			'numbers' => $numbers,
		);
	}

	/**
	 * Labelled numbers (label => value, up to 8).
	 *
	 * @param mixed $value Raw map.
	 * @return array
	 */
	public static function numbers( $value ) {
		$out = array();
		foreach ( array_slice( (array) $value, 0, 8, true ) as $label => $number ) {
			$label = substr( sanitize_text_field( (string) $label ), 0, 60 );
			if ( '' !== $label && is_scalar( $number ) ) {
				$out[ $label ] = substr( sanitize_text_field( (string) $number ), 0, 60 );
			}
		}
		return $out;
	}

	/**
	 * Search numbers per keyword (keyword => volume, volume_range, source).
	 *
	 * @param mixed $value Raw map.
	 * @return array
	 */
	private static function keyword_numbers( $value ) {
		$out = array();
		foreach ( array_slice( (array) $value, 0, 10, true ) as $query => $row ) {
			$query = substr( sanitize_text_field( (string) $query ), 0, 191 );
			if ( '' === $query || ! is_array( $row ) ) {
				continue;
			}
			$out[ $query ] = array(
				'volume'       => isset( $row['volume'] ) && null !== $row['volume'] ? absint( $row['volume'] ) : null,
				'volume_range' => isset( $row['volume_range'] ) && is_array( $row['volume_range'] ) && 2 === count( $row['volume_range'] ) ? array_map( 'absint', array_values( $row['volume_range'] ) ) : null,
				'source'       => isset( $row['source'] ) ? substr( sanitize_text_field( (string) $row['source'] ), 0, 60 ) : '',
			);
		}
		return $out;
	}

	/**
	 * Clean a brief from the browser.
	 *
	 * @param array $raw Raw brief.
	 * @return array
	 */
	public static function sanitise( array $raw ) {
		$types   = Content_Types::ids();
		$lengths = array( 'short', 'standard', 'long' );
		$sources = isset( $raw['sources'] ) && is_array( $raw['sources'] ) ? $raw['sources'] : array();

		$brief = array(
			'type'            => isset( $raw['type'] ) && in_array( $raw['type'], $types, true ) ? $raw['type'] : (string) Settings::get( 'default_type' ),
			'topic'           => isset( $raw['topic'] ) ? substr( sanitize_text_field( $raw['topic'] ), 0, 300 ) : '',
			'length'          => isset( $raw['length'] ) && in_array( $raw['length'], $lengths, true ) ? $raw['length'] : (string) Settings::get( 'default_length' ),
			'words'           => isset( $raw['words'] ) ? min( 10000, absint( $raw['words'] ) ) : 0,
			'audience'        => isset( $raw['audience'] ) ? substr( sanitize_text_field( $raw['audience'] ), 0, 200 ) : '',
			'keywords'        => array_slice( array_values( array_filter( array_map( 'sanitize_text_field', isset( $raw['keywords'] ) ? (array) $raw['keywords'] : array() ) ) ), 0, 10 ),
			'tone'            => isset( $raw['tone'] ) ? substr( sanitize_text_field( $raw['tone'] ), 0, 100 ) : '',
			'post_type'       => isset( $raw['post_type'] ) && 'page' === $raw['post_type'] ? 'page' : 'post',
			// Edit screen: write into this existing draft instead of a new one.
			'target_post'     => isset( $raw['target_post'] ) ? absint( $raw['target_post'] ) : 0,
			// Calendar: the day the new draft is planned for (Y-m-d, site time zone).
			'date'            => isset( $raw['date'] ) && \QuantumCloud\AiWriter\Calendar\Calendar::valid_date( $raw['date'] ) ? (string) $raw['date'] : '',
			'sources'         => array(
				'post_ids'     => array_slice( array_values( array_filter( array_map( 'absint', isset( $sources['post_ids'] ) ? (array) $sources['post_ids'] : array() ) ) ), 0, 10 ),
				'document_ids' => array_slice( array_values( array_filter( array_map( 'absint', isset( $sources['document_ids'] ) ? (array) $sources['document_ids'] : array() ) ) ), 0, 5 ),
				'chatbot'      => ! empty( $sources['chatbot'] ),
				'notes'        => isset( $sources['notes'] ) ? substr( sanitize_textarea_field( $sources['notes'] ), 0, 20000 ) : '',
			),
			'answers'         => array(),
			// "Answer visitor questions": the question, redacted (it may have been typed with a name or email in it).
			// Shared keyword and topic map (brief 7.10): the search this piece targets and its topic.
			'main_query'      => isset( $raw['main_query'] ) ? substr( sanitize_text_field( $raw['main_query'] ), 0, 191 ) : '',
			'map_topic'       => isset( $raw['map_topic'] ) ? substr( sanitize_text_field( $raw['map_topic'] ), 0, 191 ) : '',
			'idea_id'         => isset( $raw['idea_id'] ) ? absint( $raw['idea_id'] ) : 0,
			// Bulk writing (Pro): continue past the outline without waiting for approval.
			'auto_outline'    => ! empty( $raw['auto_outline'] ),
			// CR-14: a whole-article regeneration (mode keep_outline|new_outline, options, kept sections).
			'regenerate'      => \QuantumCloud\AiWriter\Regenerate\Article::sanitise_brief_part( isset( $raw['regenerate'] ) ? $raw['regenerate'] : null ),
			'bulk_job'        => isset( $raw['bulk_job'] ) ? absint( $raw['bulk_job'] ) : 0,
			// Pro: the voice to write in (0 = the site voice).
			'voice_id'        => isset( $raw['voice_id'] ) ? absint( $raw['voice_id'] ) : 0,
			'question'        => isset( $raw['mode'] ) && 'answer' === $raw['mode'] && isset( $raw['topic'] ) ? substr( \QuantumCloud\WPBotCore\Privacy\Redactor::redact( sanitize_text_field( $raw['topic'] ) ), 0, 300 ) : '',
			// Round 2 (CR-6 W1), from SEO & AI Search or typed by the owner.
			'intent'          => isset( $raw['intent'] ) ? self::intent( $raw['intent'] ) : '',
			// CR-16: the article format ('' for a usual article).
			'format'          => isset( $raw['format'] ) ? \QuantumCloud\AiWriter\Formats\Formats::sanitise( $raw['format'] ) : '',
			// CR-16 F2: facts the owner ticked in the "Real data" step, and the facts still missing.
			'facts'           => self::ticked_facts( isset( $raw['facts'] ) ? $raw['facts'] : array() ),
			// CR-16 F3: research the owner approved (computed and kept on the server; the browser only sends its id).
			'research'        => isset( $raw['research_id'] ) ? (array) \QuantumCloud\AiWriter\Formats\Research_Data::stored( (string) $raw['research_id'] ) : array(),
			'missing'         => array_slice( array_values( array_filter( array_map( 'sanitize_text_field', isset( $raw['missing'] ) && is_array( $raw['missing'] ) ? $raw['missing'] : array() ) ) ), 0, 10 ),
			'questions'       => self::questions( isset( $raw['questions'] ) ? $raw['questions'] : array() ),
			'link_to'         => self::post_ids( isset( $raw['link_to'] ) ? $raw['link_to'] : array() ),
			'link_from'       => self::post_ids( isset( $raw['link_from'] ) ? $raw['link_from'] : array() ),
			'keyword_numbers' => self::keyword_numbers( isset( $raw['keyword_numbers'] ) ? $raw['keyword_numbers'] : array() ),
			'numbers'         => self::numbers( isset( $raw['numbers'] ) ? $raw['numbers'] : array() ),
			'vault_id'        => isset( $raw['vault_id'] ) ? absint( $raw['vault_id'] ) : 0,
			// Round 3 (CR-13): reader stage, framework, angle, techniques or "No specific technique".
			'persuasion'      => \QuantumCloud\AiWriter\Persuasion\Choices::sanitise( isset( $raw['persuasion'] ) ? $raw['persuasion'] : null ),
		);
		if ( '' !== $brief['question'] ) {
			$brief['topic'] = $brief['question'];
		}
		// Choices kept with the draft being written (a plan item, a product, an earlier run).
		if ( empty( $brief['persuasion']['owner'] ) && $brief['target_post'] ) {
			$kept = get_post_meta( $brief['target_post'], \QuantumCloud\AiWriter\Persuasion\Choices::META, true );
			if ( is_array( $kept ) && ! empty( $kept['owner'] ) ) {
				$brief['persuasion'] = \QuantumCloud\AiWriter\Persuasion\Choices::sanitise( $kept );
			}
		}
		// CR-16: a planned draft keeps its format when the brief does not choose one.
		if ( ! array_key_exists( 'format', $raw ) && $brief['target_post'] ) {
			$brief['format'] = \QuantumCloud\AiWriter\Formats\Formats::sanitise( (string) get_post_meta( $brief['target_post'], \QuantumCloud\AiWriter\Formats\Formats::META, true ) );
		}
		// A draft planned on the calendar from an idea: the idea tells the reader stage.
		if ( ! $brief['idea_id'] && $brief['target_post'] ) {
			$brief['idea_id'] = (int) get_post_meta( $brief['target_post'], '_qcld_wpbot_writer_idea_id', true );
		}
		// What the owner did not choose is detected for this piece (never site-wide).
		$brief['persuasion'] = \QuantumCloud\AiWriter\Persuasion\Choices::resolve(
			$brief['persuasion'],
			$brief['type'],
			array(
				'intent'   => $brief['intent'],
				'idea_id'  => $brief['idea_id'],
				'vault_id' => $brief['vault_id'],
			)
		);
		// Without a main search, the first keyword is the one this piece targets.
		if ( '' === $brief['main_query'] && array() !== $brief['keywords'] ) {
			$brief['main_query'] = $brief['keywords'][0];
		}

		foreach ( isset( $raw['answers'] ) ? array_slice( (array) $raw['answers'], 0, 6 ) : array() as $answer ) {
			if ( is_array( $answer ) && ! empty( $answer['a'] ) ) {
				$brief['answers'][] = array(
					'q' => substr( sanitize_text_field( isset( $answer['q'] ) ? $answer['q'] : '' ), 0, 300 ),
					'a' => substr( sanitize_textarea_field( $answer['a'] ), 0, 3000 ),
				);
			}
		}

		// "Turn any source into a draft": no topic, one document → its title.
		if ( '' === $brief['topic'] && array() !== $brief['sources']['document_ids'] ) {
			$document       = Documents::get( $brief['sources']['document_ids'][0], false );
			$brief['topic'] = $document ? $document['title'] : '';
		}
		return $brief;
	}

	/**
	 * Target length in words.
	 *
	 * @param array $brief Brief.
	 * @return int
	 */
	public static function target_words( array $brief ) {
		if ( ! empty( $brief['words'] ) && $brief['words'] >= 100 ) {
			return (int) $brief['words'];
		}
		$lengths = (array) Settings::get( 'lengths' );
		return isset( $lengths[ $brief['length'] ] ) ? (int) $lengths[ $brief['length'] ] : 1200;
	}

	/**
	 * Facts the draft must use: answers and pasted notes (first lines).
	 *
	 * @param array $brief Brief.
	 * @return string[]
	 */
	public static function facts( array $brief ) {
		$facts = array();
		foreach ( $brief['answers'] as $answer ) {
			$facts[] = ( '' !== $answer['q'] ? $answer['q'] . ' — ' : '' ) . $answer['a'];
		}
		// CR-16 F2: ticked real data, with where it came from.
		foreach ( isset( $brief['facts'] ) ? (array) $brief['facts'] : array() as $fact ) {
			$facts[] = $fact['text'] . ( '' !== $fact['label'] ? ' (source: ' . $fact['label'] . ( '' !== $fact['url'] ? ', ' . $fact['url'] : '' ) . ')' : '' ) . ( ! empty( $fact['quote'] ) ? ' [may be quoted word for word]' : '' );
		}
		return $facts;
	}

	/**
	 * CR-16 F2: the facts ticked in the "Real data" step, sanitised.
	 *
	 * @param mixed $value Raw list.
	 * @return array[] text, source, label, url, quote.
	 */
	public static function ticked_facts( $value ) {
		$out = array();
		foreach ( is_array( $value ) ? array_slice( $value, 0, \QuantumCloud\AiWriter\Formats\Gather::MAX_FACTS ) : array() as $fact ) {
			$clean = \QuantumCloud\AiWriter\Formats\Gather::clean_fact( $fact );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Short description of the brief for prompts.
	 *
	 * @param array $brief Brief.
	 * @return string
	 */
	public static function describe( array $brief ) {
		$lines = array(
			'Content type: ' . Prompts::type_label( $brief['type'] ),
			'Topic: ' . $brief['topic'],
			'Target length: about ' . self::target_words( $brief ) . ' words',
		);
		if ( '' !== $brief['audience'] ) {
			$lines[] = 'Audience: ' . $brief['audience'];
		}
		if ( array() !== $brief['keywords'] ) {
			$lines[] = self::keywords_line( $brief );
		}
		if ( ! empty( $brief['intent'] ) ) {
			$lines[] = 'Search intent: ' . $brief['intent'] . '. ' . self::intent_guidance( $brief['intent'] );
		}
		if ( ! empty( $brief['format'] ) ) {
			$lines[] = \QuantumCloud\AiWriter\Formats\Formats::prompt( $brief['format'] );
		}
		if ( ! empty( $brief['research'] ) ) {
			$lines[] = \QuantumCloud\AiWriter\Formats\Research_Data::text( $brief['research'] ) . "\nThe tables and bar charts of these findings are inserted after the first section, so refer to them instead of repeating every row. The method section must say what the method says.";
		}
		if ( ! empty( $brief['missing'] ) ) {
			$lines[] = 'Facts the owner does not have yet (where the text needs one, write a note such as [add: ' . $brief['missing'][0] . '] and never make it up): ' . implode( ' | ', $brief['missing'] );
		}
		if ( ! empty( $brief['questions'] ) ) {
			$lines[] = 'Questions readers search for (answer each one clearly; an FAQ section suits them): ' . implode( ' | ', $brief['questions'] );
		}
		$links = self::link_targets( isset( $brief['link_to'] ) ? (array) $brief['link_to'] : array() );
		if ( array() !== $links ) {
			$lines[] = 'Pages of ours to link to where they fit naturally (use these exact URLs, at most once each): ' . implode( '; ', $links );
		}
		if ( '' !== $brief['tone'] ) {
			$lines[] = 'Tone for this piece: ' . $brief['tone'];
		}
		if ( ! empty( $brief['main_query'] ) ) {
			$volumes = \QuantumCloud\AiWriter\Topics\Coverage::volumes( array( $brief['main_query'] ) );
			if ( array() !== $volumes ) {
				$lines[] = 'Main search: ' . $brief['main_query'] . ' (about ' . (int) reset( $volumes ) . ' searches a month)';
			}
		}
		if ( ! empty( $brief['question'] ) ) {
			$lines[] = 'Visitor question to answer: ' . $brief['question'];
			$lines[] = 'Answer the question directly in the first two sentences, then explain. Use the confirmed facts and sources only; where a needed fact is missing, write a [check: what to confirm] placeholder instead of guessing.';
		}
		$type = Content_Types::get( $brief['type'] );
		if ( '' !== $type['instructions'] ) {
			$lines[] = 'Instructions for this type: ' . $type['instructions'];
		}
		$lines[] = Prompts::business();
		return implode( "\n", $lines );
	}

	/**
	 * The keywords line: higher-volume keywords first, to be covered first
	 * and more fully (CR-6 W1). Without numbers, the owner's order is kept.
	 *
	 * @param array $brief Brief.
	 * @return string
	 */
	private static function keywords_line( array $brief ) {
		$numbers  = isset( $brief['keyword_numbers'] ) ? (array) $brief['keyword_numbers'] : array();
		$volumes  = array();
		$keywords = array_values( $brief['keywords'] );
		$known    = \QuantumCloud\AiWriter\Topics\Keyword_Numbers::lookup( $keywords );
		foreach ( $keywords as $keyword ) {
			$volume = isset( $numbers[ $keyword ]['volume'] ) ? $numbers[ $keyword ]['volume'] : null;
			if ( null === $volume ) {
				$key    = \QuantumCloud\AiWriter\Topics\Keyword_Numbers::normalise( $keyword );
				$volume = isset( $known[ $key ] ) ? $known[ $key ]['volume'] : null;
			}
			$volumes[ $keyword ] = $volume;
		}
		if ( array() === array_filter( $volumes, 'is_int' ) ) {
			return 'Keywords to cover naturally: ' . implode( ', ', $keywords );
		}
		// Stable sort: known volumes high to low, unknown ones after, in the owner's order.
		$order = array_flip( $keywords );
		usort(
			$keywords,
			static function ( $a, $b ) use ( $volumes, $order ) {
				$va = $volumes[ $a ];
				$vb = $volumes[ $b ];
				if ( $va === $vb || ( null === $va && null === $vb ) ) {
					return $order[ $a ] - $order[ $b ];
				}
				if ( null === $va ) {
					return 1;
				}
				if ( null === $vb ) {
					return -1;
				}
				return $vb - $va;
			}
		);
		$parts = array();
		foreach ( $keywords as $keyword ) {
			$parts[] = null !== $volumes[ $keyword ] ? $keyword . ' (about ' . (int) $volumes[ $keyword ] . ' searches a month)' : $keyword;
		}
		return 'Keywords, most searched first (cover the first ones early, fully and in headings; work the rest in naturally): ' . implode( ', ', $parts );
	}

	/**
	 * What kind of page an intent asks for.
	 *
	 * @param string $intent Intent.
	 * @return string
	 */
	public static function intent_guidance( $intent ) {
		$guidance = array(
			'informational' => 'Write a helpful guide that explains and teaches.',
			'commercial'    => 'Write a fair comparison that helps the reader choose, with clear pros and cons.',
			'transactional' => 'Write a product or service page with a clear offer and one clear next step.',
			'navigational'  => 'Help the reader reach what they are looking for quickly; keep it short and direct.',
			'local'         => 'Write a local page for nearby customers; use only the real local details given, never invented ones.',
		);
		return isset( $guidance[ $intent ] ) ? $guidance[ $intent ] : '';
	}

	/**
	 * "Title — URL" for pages to link to (published pages the user can read).
	 *
	 * @param int[] $ids Post ids.
	 * @return string[]
	 */
	public static function link_targets( array $ids ) {
		$out = array();
		foreach ( self::post_ids( $ids ) as $id ) {
			$post = get_post( $id );
			if ( $post && 'publish' === $post->post_status && current_user_can( 'read_post', $id ) ) {
				$out[] = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) . ' — ' . get_permalink( $post );
			}
		}
		return $out;
	}
}
