<?php
/**
 * Technique: Answer first (answer_first). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'answer_first',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Answer first', 'chatbot' ),
	'method'       => 'Processing fluency; bottom line up front',
	'why'          => __( 'Readers (and AI search) get the answer straight away, then the detail.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_section', 'email' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'explain', 'hook' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Give the direct answer in the first sentence or two, then the explanation. Use short paragraphs and clear headings.',
	'examples'     => array(
		'good' => array( 'Clean your chain every 300 km. Here is how.' ),
		'bad'  => array( 'Since the dawn of cycling, people have wondered...' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
