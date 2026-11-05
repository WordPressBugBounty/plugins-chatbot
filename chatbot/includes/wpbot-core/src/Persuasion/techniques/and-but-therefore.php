<?php
/**
 * Technique: And, but, therefore (and_but_therefore). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'and_but_therefore',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'And, but, therefore', 'chatbot' ),
	'method'       => 'Narrative structure (and, but, therefore)',
	'why'          => __( 'A three-beat hook creates a small, honest tension that makes people read on.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'email', 'linkedin', 'video_script', 'x' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'product', 'product_blurb' ),
		'text'   => '',
	),
	'instructions' => 'Write the opening in three beats: a situation (and), a complication (but), and what follows (therefore). Keep it short and true.',
	'examples'     => array(
		'good' => array( 'You service your bike every year, and it still squeaks. But the problem is not the service. Therefore, let\'s look at the chain.' ),
		'bad'  => array( 'And but therefore everything changes forever.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
