<?php
/**
 * Technique: Loss angle (loss). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'loss',
	'kind'         => 'angle',
	'group'        => 'reader',
	'label'        => __( 'Loss angle', 'chatbot' ),
	'method'       => 'Loss framing (prospect theory)',
	'why'          => __( 'Some readers act to avoid losing something: show what they keep or save, calmly.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'problem_aware', 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'hook', 'problem', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware' ),
		'types'  => array( 'review_reply' ),
		'text'   => __( 'Calm, never fear-mongering.', 'chatbot' ),
	),
	'instructions' => 'Frame the message around what the reader avoids losing, calmly and with real facts. No fear tactics, no fake fear of missing out.',
	'examples'     => array(
		'good' => array( 'Stop losing leads at night: answer visitors 24/7.' ),
		'bad'  => array( 'You will lose EVERYTHING if you wait!' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
