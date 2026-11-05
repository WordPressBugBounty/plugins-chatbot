<?php
/**
 * Technique: Calm reply to a complaint (calm_reply). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'calm_reply',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Calm reply to a complaint', 'chatbot' ),
	'method'       => 'Nonviolent communication (observation, feeling, need, request)',
	'why'          => __( 'A calm reply that names what happened and what you will do turns many complaints around.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'review_reply', 'email' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'explain' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Reply calmly: describe what happened without blame, acknowledge how the customer feels, state the need or what matters, and make one clear, doable offer. No excuses, no copy-paste apology.',
	'examples'     => array(
		'good' => array( 'I am sorry your bike was not ready on Friday. That is not what we promised. Can we deliver it to you tomorrow free of charge?' ),
		'bad'  => array( 'We apologise for any inconvenience caused.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
