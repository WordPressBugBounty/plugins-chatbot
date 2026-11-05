<?php
/**
 * Technique: Curiosity and open loops (curiosity). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'curiosity',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Curiosity and open loops', 'chatbot' ),
	'method'       => 'Information gap',
	'why'          => __( 'A question the reader wants answered keeps them reading, as long as the answer is delivered.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'email', 'x', 'linkedin', 'facebook', 'instagram', 'video_script' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook' ),
		'channels' => array( 'x', 'linkedin' ),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'faq' ),
		'text'   => __( 'Never mislead or withhold the answer.', 'chatbot' ),
	),
	'instructions' => 'Open a question or gap the copy answers later in the same piece (or the next part of a series). Never write clickbait that the content does not deliver.',
	'examples'     => array(
		'good' => array( 'The one bolt most people never check (and why it matters).' ),
		'bad'  => array( 'You will not believe what happened next...' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
