<?php
/**
 * Persuasion choices of one piece (round 3, CR-13): reader stage,
 * framework, angle, extra techniques, or "No specific technique".
 *
 * Choices are made per piece, never site-wide: AI Writer detects them
 * (from search intent, the idea's source or the kind of piece) and the
 * owner can change them where the piece is written. The choice is kept
 * with the piece (brief and post meta).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

use QuantumCloud\AiWriter\Topics\Ideas;
use QuantumCloud\WPBotCore\Persuasion\Stages;
use QuantumCloud\WPBotCore\Persuasion\Techniques;

defined( 'ABSPATH' ) || exit;

/**
 * Per-piece persuasion choices.
 */
final class Choices {

	/** Post meta holding the choices a piece was written with. */
	const META = '_qcld_wpbot_writer_persuasion';

	/** Most extra techniques per piece. */
	const MAX_TECHNIQUES = 4;

	/** Frameworks to try first per kind of piece (the first that fits the stage wins). */
	const FRAMEWORKS = array(
		'blog_post'     => array( 'and_but_therefore', 'insight_led', 'pas', 'bab' ),
		'help_article'  => array(),
		'faq'           => array(),
		'page_copy'     => array( 'pas', 'customer_hero', 'situation_need', 'bab', 'aida' ),
		'landing'       => array( 'pas', 'aida', 'customer_hero', 'bab' ),
		'page_section'  => array( 'feature_benefit', 'aida', 'bab' ),
		'product'       => array( 'feature_benefit', 'bab' ),
		'product_blurb' => array( 'feature_benefit', 'bab' ),
		'buying_guide'  => array( 'feature_benefit' ),
		'comparison'    => array( 'feature_benefit' ),
		'email'         => array( 'aida', 'pas', 'bab' ),
		'ad'            => array( 'aida', 'pas' ),
		'linkedin'      => array( 'insight_led', 'and_but_therefore' ),
		'facebook'      => array( 'bab', 'customer_hero', 'aida' ),
		'instagram'     => array( 'bab', 'customer_hero' ),
		'x'             => array( 'and_but_therefore', 'aida' ),
		'pinterest'     => array(),
		'video_script'  => array( 'and_but_therefore', 'pas' ),
		'review_reply'  => array(),
	);

	/** Techniques added by default per kind of piece. */
	const TECHNIQUES = array(
		'help_article' => array( 'answer_first' ),
		'faq'          => array( 'answer_first' ),
		'blog_post'    => array( 'answer_first' ),
		'review_reply' => array( 'calm_reply' ),
		'pinterest'    => array( 'how_to' ),
	);

	/** Reader stage suggested by the source of a Topic Finder idea. */
	const IDEA_STAGES = array(
		'search_log'        => 'problem_aware',
		'search_console'    => 'problem_aware',
		'chat'              => 'problem_aware',
		'store'             => 'product_aware',
		'seasonal'          => 'solution_aware',
		'competitors'       => 'solution_aware',
		'competitor_ads'    => 'product_aware',
		'rss'               => 'unaware',
		'brainstorm'        => 'unaware',
		'web_research'      => 'unaware',
		'youtube'           => 'unaware',
		'wikipedia'         => 'unaware',
		'coverage_gaps'     => 'problem_aware',
		'decay'             => 'solution_aware',
		'visitor_questions' => 'problem_aware',
	);

	/** Default technique per section job. */
	const JOB_TECHNIQUES = array(
		'hook'      => 'be_specific',
		'problem'   => 'customers_words',
		'explain'   => 'answer_first',
		'proof'     => 'real_proof',
		'objection' => 'job_worries',
		'cta'       => 'easy_next_step',
	);

	/**
	 * Empty choices.
	 *
	 * @return array
	 */
	public static function blank() {
		return array(
			'stage'        => '',
			'stage_source' => '',
			'framework'    => '',
			'angle'        => '',
			'none'         => false,
			'techniques'   => array(),
			'owner'        => false,
		);
	}

	/**
	 * Clean choices from a form, a CSV row or another module.
	 *
	 * @param mixed $raw Raw choices.
	 * @return array stage, stage_source, framework, angle, none, techniques, owner (true when the owner set them).
	 */
	public static function sanitise( $raw ) {
		$out = self::blank();
		if ( ! is_array( $raw ) || ! Persuasion::available() ) {
			return $out;
		}
		$stage = isset( $raw['stage'] ) ? sanitize_key( (string) $raw['stage'] ) : '';
		if ( in_array( $stage, Stages::IDS, true ) ) {
			$out['stage'] = $stage;
		}
		$source = isset( $raw['stage_source'] ) ? sanitize_key( (string) $raw['stage_source'] ) : '';
		if ( in_array( $source, array( 'owner', 'intent', 'idea', 'type', 'vault' ), true ) ) {
			$out['stage_source'] = $source;
		}
		$framework = isset( $raw['framework'] ) ? sanitize_key( (string) $raw['framework'] ) : '';
		$entry     = '' !== $framework ? Techniques::get( $framework ) : null;
		if ( $entry && 'framework' === $entry['kind'] ) {
			$out['framework'] = $framework;
		}
		$angle = isset( $raw['angle'] ) ? sanitize_key( (string) $raw['angle'] ) : '';
		if ( in_array( $angle, array( 'gain', 'loss' ), true ) ) {
			$out['angle'] = $angle;
		}
		$out['none'] = ! empty( $raw['none'] ) || 'none' === $framework;
		$list        = isset( $raw['techniques'] ) ? $raw['techniques'] : array();
		if ( is_string( $list ) ) {
			$list = preg_split( '/[;,|]\s*/', $list );
		}
		foreach ( (array) $list as $id ) {
			$id    = sanitize_key( (string) $id );
			$entry = Techniques::get( $id );
			if ( $entry && 'technique' === $entry['kind'] && ! in_array( $id, $out['techniques'], true ) ) {
				$out['techniques'][] = $id;
			}
			if ( 'none' === $id ) {
				$out['none'] = true;
			}
		}
		$out['techniques'] = array_slice( $out['techniques'], 0, self::MAX_TECHNIQUES );
		$out['owner']      = ! empty( $raw['owner'] );
		if ( $out['none'] ) {
			$out['framework']  = '';
			$out['angle']      = '';
			$out['techniques'] = array();
		}
		return $out;
	}

	/**
	 * Choices for a piece: what the owner set, the rest detected.
	 *
	 * @param array  $set    Choices already set (sanitised or raw).
	 * @param string $type   Content type or Quick write template id.
	 * @param array  $signal intent, idea_id, vault_id (what is known about the reader).
	 * @return array
	 */
	public static function resolve( $set, $type, array $signal = array() ) {
		$choices = self::sanitise( $set );
		if ( ! Persuasion::available() ) {
			return $choices;
		}
		if ( '' === $choices['stage'] ) {
			list( $choices['stage'], $choices['stage_source'] ) = self::detect_stage( $type, $signal );
		}
		if ( $choices['none'] ) {
			return $choices;
		}
		if ( '' === $choices['framework'] && empty( $choices['owner'] ) ) {
			$choices['framework'] = self::default_framework( $type, $choices['stage'] );
		}
		if ( array() === $choices['techniques'] && empty( $choices['owner'] ) && isset( self::TECHNIQUES[ $type ] ) ) {
			$choices['techniques'] = self::TECHNIQUES[ $type ];
		}
		return $choices;
	}

	/**
	 * Reader stage from what is known.
	 *
	 * @param string $type   Kind of piece.
	 * @param array  $signal intent, idea_id, vault_id.
	 * @return array{0: string, 1: string} Stage and where it came from.
	 */
	public static function detect_stage( $type, array $signal ) {
		$intent = isset( $signal['intent'] ) ? (string) $signal['intent'] : '';
		if ( '' === $intent && ! empty( $signal['vault_id'] ) && function_exists( 'qcld_wpbot_core_vault_get' ) ) {
			$row    = qcld_wpbot_core_vault_get( (int) $signal['vault_id'] );
			$intent = is_array( $row ) && ! empty( $row['intent'] ) ? (string) $row['intent'] : '';
			if ( '' !== Stages::from_intent( $intent ) ) {
				return array( Stages::from_intent( $intent ), 'vault' );
			}
		}
		if ( '' !== Stages::from_intent( $intent ) ) {
			return array( Stages::from_intent( $intent ), 'intent' );
		}
		if ( ! empty( $signal['idea_id'] ) ) {
			$idea = Ideas::get( (int) $signal['idea_id'] );
			if ( $idea && isset( self::IDEA_STAGES[ $idea['source'] ] ) ) {
				return array( self::IDEA_STAGES[ $idea['source'] ], 'idea' );
			}
		}
		return array( Stages::from_type( $type ), 'type' );
	}

	/**
	 * The framework that fits the kind of piece and stage ('' for none).
	 *
	 * @param string $type  Kind of piece.
	 * @param string $stage Stage.
	 * @return string
	 */
	public static function default_framework( $type, $stage ) {
		$fits = array();
		foreach ( Techniques::query(
			array(
				'kind'  => 'framework',
				'type'  => $type,
				'stage' => $stage,
			)
		) as $entry ) {
			$fits[] = $entry['id'];
		}
		foreach ( isset( self::FRAMEWORKS[ $type ] ) ? self::FRAMEWORKS[ $type ] : array() as $id ) {
			if ( in_array( $id, $fits, true ) ) {
				return $id;
			}
		}
		return isset( self::FRAMEWORKS[ $type ] ) ? '' : ( isset( $fits[0] ) ? $fits[0] : '' );
	}

	/**
	 * Plain-language labels of the choices (for screens and the post panel).
	 *
	 * @param array $choices Choices.
	 * @return array stage, framework, angle, techniques: each with id, label, why, method.
	 */
	public static function labels( array $choices ) {
		$out   = array(
			'none'       => ! empty( $choices['none'] ),
			'stage'      => null,
			'framework'  => null,
			'angle'      => null,
			'techniques' => array(),
		);
		$stage = '' !== $choices['stage'] ? Stages::get( $choices['stage'] ) : null;
		if ( $stage ) {
			$out['stage'] = array(
				'id'     => $stage['id'],
				'label'  => $stage['label'],
				'why'    => $stage['why'],
				'method' => $stage['method'],
				'source' => $choices['stage_source'],
			);
		}
		foreach ( array( 'framework', 'angle' ) as $key ) {
			$entry = '' !== $choices[ $key ] ? Techniques::get( $choices[ $key ] ) : null;
			if ( $entry ) {
				$out[ $key ] = self::view( $entry );
			}
		}
		foreach ( $choices['techniques'] as $id ) {
			$entry = Techniques::get( $id );
			if ( $entry ) {
				$out['techniques'][] = self::view( $entry );
			}
		}
		if ( $out['none'] ) {
			$out['framework'] = self::view( Techniques::get( 'none' ) );
		}
		return $out;
	}

	/**
	 * Label view of one entry.
	 *
	 * @param array|null $entry Entry.
	 * @return array|null
	 */
	public static function view( $entry ) {
		return $entry ? array(
			'id'     => $entry['id'],
			'label'  => $entry['label'],
			'why'    => $entry['why'],
			'method' => $entry['method'],
		) : null;
	}

	/**
	 * Prompt text for the whole piece. "No specific technique" writes plain
	 * copy; every variant carries the honesty rule.
	 *
	 * @param array $choices Choices (resolved).
	 * @return string
	 */
	public static function prompt( array $choices ) {
		if ( ! Persuasion::available() ) {
			return '';
		}
		if ( ! empty( $choices['none'] ) ) {
			return "Persuasion: none. Write plain, clear copy in the brand voice; do not apply any persuasion framework or technique.\n" . Techniques::honesty_rule();
		}
		$lines = array( 'Persuasion plan (keep it honest and natural; never stack techniques: one per section):' );
		$stage = '' !== $choices['stage'] ? Stages::get( $choices['stage'] ) : null;
		if ( $stage ) {
			$lines[] = '- Reader stage: ' . $stage['instructions'];
		}
		$ids     = array_filter( array_merge( array( $choices['framework'], $choices['angle'] ), $choices['techniques'] ) );
		$lines[] = Techniques::instructions( $ids );
		return implode( "\n", $lines );
	}

	/**
	 * Prompt text for one outline section's technique.
	 *
	 * @param array $section Section (job, technique).
	 * @return string
	 */
	public static function section_prompt( array $section ) {
		if ( ! Persuasion::available() || empty( $section['technique'] ) ) {
			return '';
		}
		$jobs = self::job_labels();
		$job  = isset( $section['job'], $jobs[ $section['job'] ] ) ? $section['job'] : '';
		if ( 'none' === $section['technique'] ) {
			return ( '' !== $job ? 'This section\'s job: ' . $job . ".\n" : '' ) . 'Use no particular persuasion technique in this section.';
		}
		$entry = Techniques::get( (string) $section['technique'] );
		return ( '' !== $job ? 'This section\'s job: ' . $job . ".\n" : '' ) . ( $entry ? 'Technique for this section (only this one): ' . $entry['label'] . ': ' . $entry['instructions'] : '' );
	}

	/**
	 * Section jobs with labels.
	 *
	 * @return array<string,string>
	 */
	public static function job_labels() {
		return array(
			'hook'      => __( 'Hook', 'chatbot' ),
			'problem'   => __( 'Problem', 'chatbot' ),
			'explain'   => __( 'Explain', 'chatbot' ),
			'proof'     => __( 'Proof', 'chatbot' ),
			'objection' => __( 'Answer a worry', 'chatbot' ),
			'cta'       => __( 'Call to action', 'chatbot' ),
		);
	}

	/**
	 * Outline instructions: each section gets a job and one technique id.
	 *
	 * @param array  $choices Choices (resolved).
	 * @param string $type    Kind of piece.
	 * @return string
	 */
	public static function outline_prompt( array $choices, $type ) {
		if ( ! Persuasion::available() ) {
			return '';
		}
		$jobs = implode( ', ', array_keys( self::job_labels() ) );
		if ( ! empty( $choices['none'] ) ) {
			return 'For each section also give "job" (one of: ' . $jobs . ') and "technique":"none".';
		}
		$ids = array();
		foreach ( Techniques::query(
			array(
				'kind'  => array( 'technique' ),
				'type'  => $type,
				'stage' => $choices['stage'],
			)
		) as $entry ) {
			$ids[] = $entry['id'] . ' (' . $entry['label'] . ')';
		}
		return 'For each section also give "job" (one of: ' . $jobs . ') and "technique": one id from this list that fits the job, at most one per section: ' . implode( ', ', array_slice( $ids, 0, 20 ) ) . '. Use "none" where no technique helps.';
	}

	/**
	 * Clean a section's job and technique (defaults when the AI gave none).
	 *
	 * @param array $raw     Raw section.
	 * @param int   $index   Position.
	 * @param int   $count   Number of sections.
	 * @param bool  $none    "No specific technique" for the piece.
	 * @return array{job: string, technique: string}
	 */
	public static function section( array $raw, $index, $count, $none = false ) {
		$jobs = array_keys( self::job_labels() );
		$job  = isset( $raw['job'] ) ? sanitize_key( (string) $raw['job'] ) : '';
		if ( ! in_array( $job, $jobs, true ) ) {
			$job = 0 === $index ? 'hook' : ( $index === $count - 1 && $count > 2 ? 'cta' : 'explain' );
		}
		if ( $none || ! Persuasion::available() ) {
			return array(
				'job'       => $job,
				'technique' => 'none',
			);
		}
		$technique = isset( $raw['technique'] ) ? sanitize_key( (string) $raw['technique'] ) : '';
		$entry     = '' !== $technique ? Techniques::get( $technique ) : null;
		if ( ! $entry || ! in_array( $entry['kind'], array( 'technique', 'none' ), true ) ) {
			$technique = self::JOB_TECHNIQUES[ $job ];
		}
		return array(
			'job'       => $job,
			'technique' => $technique,
		);
	}

	/**
	 * Data for screens: stages, frameworks, angles, techniques (labels only), jobs.
	 *
	 * @return array|null Null without the persuasion pieces.
	 */
	public static function app_data() {
		if ( ! Persuasion::available() ) {
			return null;
		}
		$by_kind = array(
			'framework' => array(),
			'angle'     => array(),
			'technique' => array(),
		);
		foreach ( Techniques::all() as $entry ) {
			if ( isset( $by_kind[ $entry['kind'] ] ) ) {
				$view                        = Techniques::public_view( $entry );
				$by_kind[ $entry['kind'] ][] = $view;
			}
		}
		$stages = array();
		foreach ( Stages::all() as $stage ) {
			$stages[] = array(
				'id'     => $stage['id'],
				'label'  => $stage['label'],
				'why'    => $stage['why'],
				'method' => $stage['method'],
			);
		}
		$none = Techniques::get( 'none' );
		return array(
			'stages'     => $stages,
			'frameworks' => $by_kind['framework'],
			'angles'     => $by_kind['angle'],
			'techniques' => $by_kind['technique'],
			'none'       => self::view( $none ),
			'jobs'       => self::job_labels(),
			'compare'    => Compare::app_data(),
		);
	}
}
