<?php
/**
 * Technique: Feature to benefit (feature_benefit). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'feature_benefit',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Feature to benefit', 'chatbot' ),
	'method'       => 'Copy framework: features to benefits',
	'why'          => __( 'Buyers care about what a feature does for them, not the spec itself.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'product', 'product_blurb', 'page_section', 'landing', 'comparison', 'buying_guide' ),
		'stages'   => array( 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'proof', 'explain' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'faq' ),
		'text'   => '',
	),
	'instructions' => 'For each real feature in the product facts, say what it means for the buyer in one sentence. Never add features or performance claims that are not in the facts.',
	'examples'     => array(
		'good' => array( 'Sealed bearings: less cleaning, fewer squeaks in the rain.' ),
		'bad'  => array( 'Revolutionary technology that changes everything.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
