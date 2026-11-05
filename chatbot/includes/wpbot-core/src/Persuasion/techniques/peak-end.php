<?php
/**
 * Technique: Strong high point and ending (peak_end). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'peak_end',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Strong high point and ending', 'chatbot' ),
	'method'       => 'Peak-end rule',
	'why'          => __( 'People remember the best moment and the ending most.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'email', 'video_script', 'landing' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'proof', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Plan one clear high point (the main win for the reader) and a strong, warm ending with the next step. In a series, end each part on a real, useful point.',
	'examples'     => array(
		'good' => array( '...and that is how you save 3 hours a month. Next week: the 5-minute brake check.' ),
		'bad'  => array( 'In conclusion, thanks for reading.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
