<?php
/**
 * Technique: Be specific (be_specific). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'be_specific',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Be specific', 'chatbot' ),
	'method'       => 'Ogilvy and Hopkins: specificity',
	'why'          => __( 'Exact figures and details are more believable than adjectives.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook', 'proof', 'explain' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => __( 'Only facts you have.', 'chatbot' ),
	),
	'instructions' => 'Replace vague adjectives with exact, sourced details from the facts provided. If a detail is missing, write a short placeholder in square brackets instead of inventing it.',
	'examples'     => array(
		'good' => array( 'Serviced in 1 day, 30-point check, 12 months of free adjustments.' ),
		'bad'  => array( 'The best, fastest, most amazing service.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
