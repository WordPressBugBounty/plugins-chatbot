<?php
/**
 * Technique: Attention to action (aida). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'aida',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Attention to action', 'chatbot' ),
	'method'       => 'AIDA (attention, interest, desire, action)',
	'why'          => __( 'A familiar path from a hook to one clear next step keeps short copy focused.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'ad', 'email', 'landing', 'page_section', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'hook', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware' ),
		'types'  => array( 'help_article', 'faq' ),
		'text'   => __( 'Too pushy for long guides.', 'chatbot' ),
	),
	'instructions' => 'Open with a hook that earns attention honestly, build interest with one concrete detail, make the benefit wanted, end with one clear call to action.',
	'examples'     => array(
		'good' => array( 'New: same-day bike service. Drop it off at 9, ride home at 5. Book a slot.' ),
		'bad'  => array( 'ACT NOW!!! LIMITED!!!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
