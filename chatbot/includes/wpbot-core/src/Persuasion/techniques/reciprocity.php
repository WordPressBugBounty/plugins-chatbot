<?php
/**
 * Technique: Give something useful first (reciprocity). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'reciprocity',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Give something useful first', 'chatbot' ),
	'method'       => 'Cialdini: reciprocity',
	'why'          => __( 'A genuinely useful free offer earns attention and goodwill.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'page_section', 'landing', 'email', 'blog_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'problem_aware', 'solution_aware' ),
		'jobs'     => array( 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => __( 'Only offers in the kit.', 'chatbot' ),
	),
	'instructions' => 'Offer the free resource or offer from the Persuasion Kit, describe exactly what the reader gets, and ask for nothing hidden in return.',
	'examples'     => array(
		'good' => array( 'Free chain check while you wait (from your kit).' ),
		'bad'  => array( 'Free gift (invented).' ),
	),
	'needs'        => array( 'kit:free_offer' ),
	'edition'      => 'free',
);
