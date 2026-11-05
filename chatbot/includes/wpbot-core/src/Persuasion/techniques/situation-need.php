<?php
/**
 * Technique: Ask about their situation (situation_need). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'situation_need',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Ask about their situation', 'chatbot' ),
	'method'       => 'SPIN questions (situation, problem, implication, need-payoff)',
	'why'          => __( 'Thinking through their own situation helps readers see the value themselves.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'landing', 'page_section', 'email', 'linkedin' ),
		'stages'   => array( 'problem_aware', 'solution_aware' ),
		'jobs'     => array( 'problem', 'objection' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware', 'most_aware' ),
		'types'  => array( 'product', 'product_blurb' ),
		'text'   => __( 'Too slow for small, quick purchases.', 'chatbot' ),
	),
	'instructions' => 'Describe the reader\'s situation, the problem it causes, what that problem leads to, and the payoff of solving it, as questions or short statements the reader recognises. Stay factual.',
	'examples'     => array(
		'good' => array( 'How many hours a week do your staff spend answering the same questions? What does that cost by Friday?' ),
		'bad'  => array( 'You are losing millions without knowing it!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
