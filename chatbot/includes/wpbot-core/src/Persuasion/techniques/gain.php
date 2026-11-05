<?php
/**
 * Technique: Gain angle (gain). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'gain',
	'kind'         => 'angle',
	'group'        => 'reader',
	'label'        => __( 'Gain angle', 'chatbot' ),
	'method'       => 'Regulatory focus: promotion',
	'why'          => __( 'Some readers move toward a goal: show what they get.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'unaware', 'problem_aware', 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'hook', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'Frame the message around what the reader gains, using real outcomes only.',
	'examples'     => array(
		'good' => array( 'Get more leads from your website.' ),
		'bad'  => array( 'Get rich overnight.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
