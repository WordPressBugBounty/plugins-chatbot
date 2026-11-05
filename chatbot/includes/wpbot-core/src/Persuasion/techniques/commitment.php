<?php
/**
 * Technique: Small first step (commitment). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'commitment',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Small first step', 'chatbot' ),
	'method'       => 'Cialdini: commitment and consistency',
	'why'          => __( 'A small, easy first step makes the next one feel natural.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'page_section', 'landing', 'email', 'landing', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Ask for one small, low-effort step first (read, try, book a free check) rather than a big decision. Never trick the reader into agreeing.',
	'examples'     => array(
		'good' => array( 'Start with a free 10-minute check.' ),
		'bad'  => array( 'Click yes to agree you hate wasting money.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
