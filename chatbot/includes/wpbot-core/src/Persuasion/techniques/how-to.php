<?php
/**
 * Technique: How-to headline (how_to). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'how_to',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'How-to headline', 'chatbot' ),
	'method'       => 'Classic headline formula',
	'why'          => __( 'A clear promise of how to do something matches what people search for.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'pinterest', 'video_script', 'email' ),
		'stages'   => array( 'problem_aware', 'solution_aware' ),
		'jobs'     => array( 'hook' ),
		'channels' => array( 'pinterest' ),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'product', 'product_blurb' ),
		'text'   => '',
	),
	'instructions' => 'Write the headline as a clear how-to that the content fully delivers.',
	'examples'     => array(
		'good' => array( 'How to clean a bike chain in 10 minutes' ),
		'bad'  => array( 'How to become a millionaire with one trick' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
