<?php
/**
 * Technique: Long or short argument (long_or_short). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'long_or_short',
	'kind'         => 'technique',
	'group'        => 'reader',
	'label'        => __( 'Long or short argument', 'chatbot' ),
	'method'       => 'Elaboration likelihood model',
	'why'          => __( 'Big decisions need evidence; small ones need a quick, clear reason.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'explain', 'proof' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Match the depth of the argument to the decision: for expensive or complex choices give evidence and detail; for small purchases give one clear reason and proof.',
	'examples'     => array(
		'good' => array( 'A 4,000 euro e-bike: range tests, warranty, service plan. A 9 euro bell: loud, fits every bar.' ),
		'bad'  => array( 'A wall of text for a 5 euro item.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
