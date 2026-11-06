<?php
/**
 * CR-16: article formats. Each format is a recipe: how the outline is
 * planned, how sections are written, which checks run, which structured data
 * is added, which downloads are offered and which real-data sources it
 * reads. "No format" keeps AI Writer's usual article.
 *
 * Formats never invent data: a missing fact becomes an [add: …] note.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Format registry.
 */
final class Formats {

	/**
	 * Post meta: the format a piece was written in.
	 */
	const META = '_qcld_wpbot_writer_format';

	/**
	 * All formats.
	 *
	 * Each: label, short (one line for the picker), outline (planning rules),
	 * section (writing rules), blocks (extra block shapes sections may use:
	 * table, checklist), checks (Format_Checks ids), schema (JSON-LD type or
	 * ''), downloads (pdf, print, docx, txt, csv), sources (Real data source
	 * ids), type (content type it suits, '' for any), panels (extra panels
	 * on the Real data step, for example 'research' for "Your research data").
	 *
	 * WPBot Free bundles five formats; the AI Writer Pro module adds the
	 * others through the qcld_wpbot_writer/formats filter.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$formats = array(
			'listicle'  => array(
				'label'     => __( 'Listicle', 'chatbot' ),
				'short'     => __( 'A numbered list of options with the same details for each, and a summary table.', 'chatbot' ),
				'outline'   => 'Format: listicle. Use the number of items in the title (or 7 if none is given). Section 1: who this is for and the criteria used to choose. Then one section per item, headed "1. Name", "2. Name" and so on. Give every item the same fields: what it is, best for, strengths, what to watch out for, and price only when it is a given fact. Last section: a summary table comparing the items, then how to choose.',
				'section'   => 'Listicle rules: for an item section, use the same fields as every other item, in the same order, as short paragraphs or a list. In the summary section, use a table with one row per item.',
				'blocks'    => array( 'table' ),
				'checks'    => array( 'count_matches_title', 'has_table' ),
				'schema'    => 'ItemList',
				'downloads' => array(),
				'sources'   => array( 'products', 'kit', 'knowledge', 'pages', 'web', 'who_ranks' ),
				'type'      => '',
			),
			'how_to'    => array(
				'label'     => __( 'How-to guide', 'chatbot' ),
				'short'     => __( 'What you need, the steps, common mistakes and what to do if it does not work.', 'chatbot' ),
				'outline'   => 'Format: how-to guide. Sections in this order: what you will achieve; what you need; the steps (one section, as a numbered list, or one section per larger step); common mistakes; if it does not work; next steps.',
				'section'   => 'How-to rules: write steps as a numbered list, each step starting with a verb. Be concrete; use only facts given for times, prices and measurements.',
				'blocks'    => array(),
				'checks'    => array( 'has_numbered_steps' ),
				'schema'    => 'HowTo',
				'downloads' => array(),
				'sources'   => array( 'knowledge', 'words', 'pages', 'uploads', 'kit' ),
				'type'      => '',
			),
			'tutorial'  => array(
				'label'     => __( 'Step-by-step tutorial', 'chatbot' ),
				'short'     => __( 'One task from start to finish: each step with the exact action and what you will see.', 'chatbot' ),
				'outline'   => 'Format: step-by-step tutorial for one task. Section 1: before you start (time, tools, level). Then one section per step headed "Step 1: action", "Step 2: action" and so on. Then troubleshooting and the finished result.',
				'section'   => 'Tutorial rules: in a step section, give the exact action first, then what the reader should see when it worked, then one tip. Keep each step to one action.',
				'blocks'    => array(),
				'checks'    => array( 'steps_numbered' ),
				'schema'    => 'HowTo',
				'downloads' => array(),
				'sources'   => array( 'uploads', 'knowledge', 'pages', 'interview' ),
				'type'      => '',
			),
			'checklist' => array(
				'label'     => __( 'Checklist', 'chatbot' ),
				'short'     => __( 'Grouped, tickable items to follow, with a PDF to download or print.', 'chatbot' ),
				'outline'   => 'Format: checklist. A short introduction on when to use it, then one section per group of items, then a short "when you are done" section.',
				'section'   => 'Checklist rules: in a group section, write the items as a checklist block (a list with "checklist": true). Each item starts with a verb and has at most 15 words. Add one short sentence before the list at most.',
				'blocks'    => array( 'checklist' ),
				'checks'    => array( 'has_checklist' ),
				'schema'    => 'ItemList',
				'downloads' => array( 'pdf', 'print' ),
				'sources'   => array( 'knowledge', 'pages', 'words', 'kit', 'uploads' ),
				'type'      => '',
			),
			'faq'       => array(
				'label'     => __( 'FAQ', 'chatbot' ),
				'short'     => __( 'Real customer questions grouped by topic, each answered directly.', 'chatbot' ),
				'outline'   => 'Format: FAQ. Group the questions by topic, one section per group. Use the real questions given first; add a question only when readers clearly need it.',
				'section'   => 'FAQ rules: in a group section, write each question as a level 3 heading ending with a question mark, then the answer: the direct answer in the first sentence, 40 to 90 words in all.',
				'blocks'    => array(),
				'checks'    => array( 'questions_answered' ),
				'schema'    => 'FAQPage',
				'downloads' => array(),
				'sources'   => array( 'questions', 'words', 'knowledge', 'pages' ),
				'type'      => 'faq',
			),
		);
		/**
		 * Article formats (CR-16).
		 *
		 * @param array<string,array> $formats id => recipe (see Formats::all()).
		 */
		$formats = (array) apply_filters( 'qcld_wpbot_writer/formats', $formats );
		$out     = array();
		foreach ( $formats as $id => $format ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $format ) || empty( $format['label'] ) ) {
				continue;
			}
			$out[ $id ] = wp_parse_args(
				$format,
				array(
					'short'     => '',
					'outline'   => '',
					'section'   => '',
					'blocks'    => array(),
					'checks'    => array(),
					'schema'    => '',
					'downloads' => array(),
					'sources'   => array(),
					'type'      => '',
					'panels'    => array(),
				)
			);
		}
		return $out;
	}

	/**
	 * One format, or null.
	 *
	 * @param string $id Format id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		$id  = sanitize_key( (string) $id );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * A clean format id ('' for none).
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function sanitise( $raw ) {
		$id = is_scalar( $raw ) ? sanitize_key( (string) $raw ) : '';
		return null !== self::get( $id ) ? $id : '';
	}

	/**
	 * The prompt part for a brief: how to plan and write this format.
	 *
	 * @param string $id Format id.
	 * @return string
	 */
	public static function prompt( $id ) {
		$format = self::get( $id );
		if ( null === $format ) {
			return '';
		}
		return $format['outline'] . "\n" . $format['section']
			. "\nNever invent a number, a quote, a customer, a price or a statistic. Where the format needs a fact that is not given, write a short note in square brackets such as [add: your warranty period].";
	}

	/**
	 * Extra block shapes the section answer may use for this format.
	 *
	 * @param string $id Format id.
	 * @return string JSON example fragment ('' for none).
	 */
	public static function block_shapes( $id ) {
		$format = self::get( $id );
		if ( null === $format ) {
			return '';
		}
		$shapes = array();
		if ( in_array( 'table', $format['blocks'], true ) ) {
			$shapes[] = '{"type":"table","head":["",""],"rows":[["",""]]}';
		}
		if ( in_array( 'checklist', $format['blocks'], true ) ) {
			$shapes[] = '{"type":"list","checklist":true,"items":[""]}';
		}
		return implode( ',', $shapes );
	}

	/**
	 * The format suggested for a topic, with the reason. Rules only (no AI
	 * call, no cost); other modules can refine it (filter).
	 *
	 * @param string $topic  Topic.
	 * @param string $intent Search intent ('' if unknown).
	 * @param string $type   Content type.
	 * @return array{id: string, reason: string}|null
	 */
	public static function suggest( $topic, $intent = '', $type = '' ) {
		$topic = strtolower( ' ' . sanitize_text_field( (string) $topic ) . ' ' );
		$rules = self::rules();
		$found = null;
		foreach ( $rules as $rule ) {
			if ( null !== self::get( $rule['id'] ) && preg_match( $rule['match'], $topic ) ) {
				$found = array(
					'id'     => $rule['id'],
					'reason' => $rule['reason'],
				);
				break;
			}
		}
		if ( null === $found && 'faq' === $type ) {
			$found = array(
				'id'     => 'faq',
				'reason' => __( 'An FAQ page answers each question directly.', 'chatbot' ),
			);
		}
		if ( null === $found && 'transactional' === $intent ) {
			$found = array(
				'id'     => 'comparison',
				'reason' => __( 'Searchers are close to buying; a fair comparison helps them choose.', 'chatbot' ),
			);
		}
		/**
		 * The suggested format for a topic (null for none).
		 *
		 * @param array|null $found  id, reason.
		 * @param string     $topic  Topic (lower case).
		 * @param string     $intent Search intent.
		 */
		$found = apply_filters( 'qcld_wpbot_writer/format_suggestion', $found, trim( $topic ), (string) $intent );
		return is_array( $found ) && null !== self::get( isset( $found['id'] ) ? $found['id'] : '' ) ? $found : null;
	}

	/**
	 * A format the site does not have that fits the topic better than any it
	 * has (WPBot Free: a Pro format), from hint-only rules; null for none.
	 *
	 * @param string $topic Topic.
	 * @return array{id: string, label: string}|null
	 */
	public static function hint( $topic ) {
		$topic = strtolower( ' ' . sanitize_text_field( (string) $topic ) . ' ' );
		foreach ( self::rules() as $rule ) {
			if ( ! preg_match( $rule['match'], $topic ) ) {
				continue;
			}
			if ( null !== self::get( $rule['id'] ) ) {
				return null;
			}
			if ( '' !== $rule['hint'] ) {
				return array(
					'id'    => $rule['id'],
					'label' => $rule['hint'],
				);
			}
		}
		return null;
	}

	/**
	 * Suggestion rules, first match wins: id, match (regex on the topic in
	 * lower case, padded with spaces), reason, order, hint.
	 *
	 * The core holds the rules of its own formats. The AI Writer Pro module
	 * adds its formats' rules, and a host without them (WPBot Free) can add
	 * hint-only rules (hint: a label such as "Case study (Pro)") so the
	 * suggestion can mention a format the site does not have. Rules run in
	 * "order" (lower first).
	 *
	 * @return array[]
	 */
	public static function rules() {
		$rules = array(
			array(
				'id'     => 'checklist',
				'match'  => '/\b(checklist|check list|before you|things to check|to-do)\b/',
				'reason' => __( 'Readers want something to tick off; a checklist is quick to follow and to print.', 'chatbot' ),
				'order'  => 50,
			),
			array(
				'id'     => 'tutorial',
				'match'  => '/\b(step by step|step-by-step|tutorial|set ?up|install|configure)\b/',
				'reason' => __( 'The topic is one task to complete; a step-by-step tutorial walks through it exactly.', 'chatbot' ),
				'order'  => 70,
			),
			array(
				'id'     => 'listicle',
				'match'  => '/\b(best|top \d+|\d+ (best|ways|ideas|tips|reasons|things|mistakes))\b/',
				'reason' => __( 'The topic asks for several options; a listicle gives each the same details and a summary table.', 'chatbot' ),
				'order'  => 80,
			),
			array(
				'id'     => 'faq',
				'match'  => '/\b(faq|questions|q&a)\b/',
				'reason' => __( 'The topic is a set of questions; an FAQ answers each one directly.', 'chatbot' ),
				'order'  => 90,
			),
			array(
				'id'     => 'how_to',
				'match'  => '/\b(how to|how do i|how can i|guide to|ways to)\b/',
				'reason' => __( 'Readers want to get something done; a how-to guide gives what they need and the steps.', 'chatbot' ),
				'order'  => 100,
			),
		);
		/**
		 * Format suggestion rules (see Formats::rules()).
		 *
		 * @param array[] $rules Rules.
		 */
		$rules = (array) apply_filters( 'qcld_wpbot_writer/format_rules', $rules );
		$out   = array();
		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['id'] ) || empty( $rule['match'] ) || false === @preg_match( (string) $rule['match'], '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An invalid pattern from a filter is skipped, not reported to visitors.
				continue;
			}
			$out[] = array(
				'id'     => sanitize_key( (string) $rule['id'] ),
				'match'  => (string) $rule['match'],
				'reason' => isset( $rule['reason'] ) ? (string) $rule['reason'] : '',
				'order'  => isset( $rule['order'] ) ? (float) $rule['order'] : 1000 + $index,
				'hint'   => isset( $rule['hint'] ) ? sanitize_text_field( (string) $rule['hint'] ) : '',
				'index'  => $index,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['order'] === $b['order'] ? $a['index'] - $b['index'] : ( $a['order'] < $b['order'] ? -1 : 1 );
			}
		);
		return $out;
	}

	/**
	 * A format for writing without the owner at the screen (calendar
	 * drafts ahead, bulk rows): Original research needs numbers the owner
	 * approves, so it is never used there.
	 *
	 * @param string $id Format id.
	 * @return string Format id, or '' for none.
	 */
	public static function automatic( $id ) {
		$id = self::sanitise( $id );
		return 'original_research' === $id ? '' : $id;
	}

	/**
	 * Whether format suggestions are on (Settings → Writing).
	 *
	 * @return bool
	 */
	public static function suggesting() {
		return (bool) Settings::get( 'format_suggest' );
	}

	/**
	 * The formats for the screens.
	 *
	 * @return array[] value, label, short, sources, downloads.
	 */
	public static function public_view() {
		$out = array();
		foreach ( self::all() as $id => $format ) {
			$out[] = array(
				'value'     => $id,
				'label'     => (string) $format['label'],
				'short'     => (string) $format['short'],
				'sources'   => array_values( array_map( 'strval', (array) $format['sources'] ) ),
				'downloads' => array_values( array_map( 'strval', (array) $format['downloads'] ) ),
				'type'      => (string) $format['type'],
				'panels'    => array_values( array_map( 'strval', (array) $format['panels'] ) ),
			);
		}
		return $out;
	}
}
