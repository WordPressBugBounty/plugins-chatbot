<?php
/**
 * Technique: Frame the real price (price_framing). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'price_framing',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Frame the real price', 'chatbot' ),
	'method'       => 'Price psychology: anchoring, decoy, per-day, savings shown',
	'why'          => __( 'The same real price feels fairer with honest context.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'product', 'product_blurb', 'page_copy', 'landing', 'email', 'page_section' ),
		'stages'   => array( 'product_aware', 'most_aware' ),
		'jobs'     => array( 'proof', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware' ),
		'types'  => array(),
		'text'   => __( 'Only real prices from the store or the kit; never change a price.', 'chatbot' ),
	),
	'instructions' => 'Frame only the real prices provided: per day or per use, the saving against the real regular price, or a comparison between the real plans. Never change, round up or invent a price or saving.',
	'examples'     => array(
		'good' => array( '49 euros a year: less than 1 euro a week for a safe bike.' ),
		'bad'  => array( 'Was 999, now 49! (no real regular price)' ),
	),
	'needs'        => array( 'woo:price' ),
	'edition'      => 'free',
);
