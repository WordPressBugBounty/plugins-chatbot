<?php
/**
 * Technique: Gentle, respectful email (gentle_tone). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'gentle_tone',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Gentle, respectful email', 'chatbot' ),
	'method'       => 'Motivational interviewing',
	'why'          => __( 'Respecting the reader\'s choice makes re-engagement and nurture emails feel human, not pushy.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'email', 'review_reply' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook', 'cta' ),
		'channels' => array( 'email' ),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Write in a calm, respectful tone that acknowledges the reader\'s choice, asks an open question, reflects what they may want, and offers help without pressure.',
	'examples'     => array(
		'good' => array( 'No pressure at all. If you would like a quick check before spring, here is a slot.' ),
		'bad'  => array( 'Last chance! Do not ignore this email!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
