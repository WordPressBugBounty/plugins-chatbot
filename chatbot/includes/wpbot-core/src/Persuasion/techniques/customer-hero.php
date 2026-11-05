<?php
/**
 * Technique: Customer as the hero (customer_hero). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'customer_hero',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Customer as the hero', 'chatbot' ),
	'method'       => 'Hero and guide story structure',
	'why'          => __( 'People remember stories where they are the hero and you are the helpful guide.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'page_copy', 'landing', 'blog_post', 'email', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'problem_aware', 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'hook', 'proof', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'faq', 'help_article' ),
		'text'   => __( 'Avoid on technical help pages.', 'chatbot' ),
	),
	'instructions' => 'Make the reader the hero with a goal and an obstacle; present the business as the guide with empathy and a simple plan; end with a clear call to action and what success looks like. Use only real facts.',
	'examples'     => array(
		'good' => array( 'You want to ride to work without worrying. We check 30 points so you can. Book a check.' ),
		'bad'  => array( 'We are the heroes who saved thousands.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
