<?php
/**
 * Reader stages (round 3, CR-13): how much the reader already knows, from
 * not knowing they have a problem to ready to buy. Based on Eugene
 * Schwartz's five levels of awareness; owners see plain words, the method
 * name sits in a tooltip.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Reader stages.
 */
final class Stages {

	/** Stage ids, from least to most aware. */
	const IDS = array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' );

	/** Method name for tooltips. */
	const METHOD = 'Eugene Schwartz: five levels of awareness';

	/**
	 * Every stage with its label, "why this works" note and writing hint.
	 *
	 * @return array<string,array{id: string, label: string, why: string, instructions: string}>
	 */
	public static function all() {
		$stages = array(
			'unaware'        => array(
				'label'        => __( 'Does not know about the problem yet', 'chatbot' ),
				'why'          => __( 'Readers who do not know they have a problem need a story or an insight first, not a pitch.', 'chatbot' ),
				'instructions' => 'The reader does not yet know they have this problem. Lead with a relatable story, situation or insight; do not pitch early; keep any call to action soft (read more, learn).',
			),
			'problem_aware'  => array(
				'label'        => __( 'Knows the problem', 'chatbot' ),
				'why'          => __( 'Readers who feel the problem want to see it understood before they hear about solutions.', 'chatbot' ),
				'instructions' => 'The reader knows the problem but not the solutions. Name the problem in their words, show you understand it, then introduce the kinds of solution.',
			),
			'solution_aware' => array(
				'label'        => __( 'Knows the solutions', 'chatbot' ),
				'why'          => __( 'Readers comparing solutions need to see why this one fits them.', 'chatbot' ),
				'instructions' => 'The reader knows the kinds of solution but not this product or service. Show why this approach fits them better, with real proof.',
			),
			'product_aware'  => array(
				'label'        => __( 'Knows your offer', 'chatbot' ),
				'why'          => __( 'Readers who know your offer need their last doubts answered and a clear next step.', 'chatbot' ),
				'instructions' => 'The reader knows this product or service but has not decided. Answer objections, show real proof and risk reversal, and give one clear call to action.',
			),
			'most_aware'     => array(
				'label'        => __( 'Ready to act', 'chatbot' ),
				'why'          => __( 'Readers ready to act want the details and the button, nothing in the way.', 'chatbot' ),
				'instructions' => 'The reader is ready to act. Be short and direct: the offer, the real price or deadline if any, and the call to action.',
			),
		);
		foreach ( $stages as $id => $stage ) {
			$stages[ $id ] = array( 'id' => $id ) + $stage + array( 'method' => self::METHOD );
		}
		return $stages;
	}

	/**
	 * One stage, or null.
	 *
	 * @param string $id Stage id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Stage suggested by search intent (SEO & AI Search, Keyword Vault).
	 *
	 * @param string $intent informational, commercial, transactional, navigational or local.
	 * @return string Stage id ('' when unknown).
	 */
	public static function from_intent( $intent ) {
		$map    = array(
			'informational' => 'problem_aware',
			'commercial'    => 'solution_aware',
			'transactional' => 'product_aware',
			'local'         => 'product_aware',
			'navigational'  => 'most_aware',
		);
		$intent = sanitize_key( (string) $intent );
		return isset( $map[ $intent ] ) ? $map[ $intent ] : '';
	}

	/**
	 * Stage suggested by the kind of piece when nothing better is known.
	 *
	 * @param string $type Content type or Quick write template id.
	 * @return string Stage id.
	 */
	public static function from_type( $type ) {
		$map  = array(
			'blog_post'     => 'problem_aware',
			'help_article'  => 'problem_aware',
			'faq'           => 'solution_aware',
			'buying_guide'  => 'solution_aware',
			'comparison'    => 'solution_aware',
			'page_copy'     => 'solution_aware',
			'landing'       => 'product_aware',
			'product'       => 'product_aware',
			'product_blurb' => 'product_aware',
			'ad'            => 'product_aware',
			'email'         => 'product_aware',
			'review_reply'  => 'most_aware',
		);
		$type = sanitize_key( (string) $type );
		return isset( $map[ $type ] ) ? $map[ $type ] : 'problem_aware';
	}
}
