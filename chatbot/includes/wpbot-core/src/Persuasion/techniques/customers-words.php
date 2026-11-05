<?php
/**
 * Technique: Use your customers' own words (customers_words). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'customers_words',
	'kind'         => 'technique',
	'group'        => 'reader',
	'label'        => __( 'Use your customers\' own words', 'chatbot' ),
	'method'       => 'Voice of customer research',
	'why'          => __( 'Copy in the words customers actually use feels familiar and is found in search.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook', 'problem' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Use the customer phrases provided (already anonymised) as wording for problems and headlines. Never present them as quotes and never add names.',
	'examples'     => array(
		'good' => array( '"My gears keep slipping" — fix slipping gears in 10 minutes.' ),
		'bad'  => array( 'As Jane from Leeds said... (no permission)' ),
	),
	'needs'        => array( 'voc' ),
	'edition'      => 'free',
);
