<?php
/**
 * Technique: Show real proof near the claim (real_proof). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'real_proof',
	'kind'         => 'technique',
	'group'        => 'stick',
	'label'        => __( 'Show real proof near the claim', 'chatbot' ),
	'method'       => 'Cialdini: social proof, authority, liking, unity',
	'why'          => __( 'People trust what others like them, and recognised authorities, already confirm.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'help_article', 'faq', 'buying_guide', 'comparison', 'page_copy', 'page_section', 'landing', 'product', 'product_blurb', 'email', 'ad', 'press', 'video_script', 'review_reply', 'job_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'solution_aware', 'product_aware', 'most_aware' ),
		'jobs'     => array( 'proof', 'cta' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array( 'unaware' ),
		'types'  => array(),
		'text'   => __( 'Only proof from your Persuasion Kit or live store data.', 'chatbot' ),
	),
	'instructions' => 'Place one piece of real proof from the Persuasion Kit next to the main claim or call to action. Use only the proof provided; never invent numbers, reviews, awards or media mentions. Quote customers only when marked as allowed.',
	'examples'     => array(
		'good' => array( 'Rated 4.8 by 312 customers (from your kit).' ),
		'bad'  => array( 'Trusted by millions worldwide (not in the kit).' ),
	),
	'needs'        => array( 'kit:review', 'kit:testimonial', 'kit:customer_count', 'kit:certification', 'kit:media_mention', 'kit:case_study' ),
	'edition'      => 'free',
);
