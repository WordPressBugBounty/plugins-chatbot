<?php
/**
 * Technique: Real deadlines and stock (real_urgency). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'real_urgency',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Real deadlines and stock', 'chatbot' ),
	'method'       => 'Cialdini: scarcity (real data only)',
	'why'          => __( 'A true deadline or low stock helps people decide now, when it is real.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'product', 'product_blurb', 'page_copy', 'page_section', 'landing', 'email', 'ad', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'product_aware', 'most_aware' ),
		'jobs'     => array( 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware', 'problem_aware' ),
		'types'  => array( 'help_article', 'faq' ),
		'text'   => __( 'Only with a live sale date, real stock or a kit deadline. No countdown timers.', 'chatbot' ),
	),
	'instructions' => 'Mention urgency only when the data provided includes a real sale end date, real low stock or a kit offer with an end date, stated exactly. Never invent deadlines, never add countdowns, never imply scarcity that is not in the data.',
	'examples'     => array(
		'good' => array( 'Sale ends 30 November (store data).' ),
		'bad'  => array( 'Only 2 left! Offer ends in 10 minutes!' ),
	),
	'needs'        => array( 'woo:sale_end', 'woo:stock', 'kit:offer' ),
	'edition'      => 'free',
);
