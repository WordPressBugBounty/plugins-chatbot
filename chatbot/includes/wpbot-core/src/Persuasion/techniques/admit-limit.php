<?php
/**
 * Technique: Admit a limit (admit_limit). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'admit_limit',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Admit a limit', 'chatbot' ),
	'method'       => 'Pratfall effect',
	'why'          => __( 'Saying who it is not for makes the rest more believable.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'buying_guide', 'comparison', 'linkedin' ),
		'stages'   => array( 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'objection' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => __( 'Uses the owner\'s own input only.', 'chatbot' ),
	),
	'instructions' => 'Add a short, honest "Who this is not for" note using only facts provided by the owner.',
	'examples'     => array(
		'good' => array( 'Not for racing: this frame is built for comfort.' ),
		'bad'  => array( 'Our only weakness is being too good.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
