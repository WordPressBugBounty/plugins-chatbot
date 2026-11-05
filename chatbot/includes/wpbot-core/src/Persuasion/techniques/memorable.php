<?php
/**
 * Technique: Make it memorable (memorable). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'memorable',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Make it memorable', 'chatbot' ),
	'method'       => 'Made to Stick (simple, unexpected, concrete, credible, emotional, story)',
	'why'          => __( 'Simple, concrete messages with a small surprise are remembered and shared.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'email', 'video_script', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook', 'explain' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Keep one core message; make it concrete with an example, add one honest unexpected detail, and keep it credible with real facts.',
	'examples'     => array(
		'good' => array( 'A chain lasts as long as two tyres if you clean it monthly.' ),
		'bad'  => array( 'Synergistic holistic solutions.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
