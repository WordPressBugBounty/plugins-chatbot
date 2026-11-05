<?php
/**
 * Technique: One easy next step (easy_next_step). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'easy_next_step',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'One easy next step', 'chatbot' ),
	'method'       => 'Fogg behaviour model (motivation, ability, prompt)',
	'why'          => __( 'One clear, low-effort action gets more people to act than several choices.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'End each section that asks for action with one clear, specific call to action that is easy to do. Remove extra choices.',
	'examples'     => array(
		'good' => array( 'Book your service online in 2 minutes.' ),
		'bad'  => array( 'Call, email, visit, follow us, subscribe, buy now.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
