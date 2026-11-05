<?php
/**
 * Technique: Problem, then solution (pas). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'pas',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Problem, then solution', 'chatbot' ),
	'method'       => 'Copy framework: problem, agitate, solve',
	'why'          => __( 'Readers act when they recognise their own problem and see what it costs them.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'page_section', 'landing', 'blog_post', 'email', 'ad', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'problem_aware', 'solution_aware' ),
		'jobs'     => array( 'hook', 'problem', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'most_aware' ),
		'types'  => array( 'faq', 'help_article' ),
		'text'   => __( 'Not for readers who already know and trust you; it slows them down.', 'chatbot' ),
	),
	'instructions' => 'Name the reader\'s problem in their words, show its real cost briefly and calmly, then present the solution. Never exaggerate the pain or invent consequences.',
	'examples'     => array(
		'good' => array( 'A squeaky chain is more than noise: it wears your gears twice as fast. A 20-minute clean fixes both.' ),
		'bad'  => array( 'Your life is falling apart because of your chain!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
