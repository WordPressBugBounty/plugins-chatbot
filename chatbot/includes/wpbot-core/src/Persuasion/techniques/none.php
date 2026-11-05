<?php
/**
 * Technique: No specific technique (none). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'none',
	'kind'         => 'none',
	'group'        => 'none',
	'label'        => __( 'No specific technique', 'chatbot' ),
	'method'       => '',
	'why'          => __( 'Plain, clear writing in your brand voice. Only the honesty checks apply.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array(),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Write plain, clear copy in the brand voice. Do not apply any persuasion framework or technique. Keep claims factual.',
	'examples'     => array(
		'good' => array( 'Our bikes are serviced by hand in Leeds. A full service takes one day.' ),
		'bad'  => array( 'Hurry! Only today! Thousands love us!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
