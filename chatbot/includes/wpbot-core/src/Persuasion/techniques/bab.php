<?php
/**
 * Technique: Before, after, bridge (bab). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'bab',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Before, after, bridge', 'chatbot' ),
	'method'       => 'Copy framework: before, after, bridge',
	'why'          => __( 'Showing the change makes the benefit easy to picture.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'product', 'product_blurb', 'page_copy', 'landing', 'email', 'blog_post', 'linkedin', 'facebook', 'instagram', 'x', 'pinterest', 'google_business' ),
		'stages'   => array( 'problem_aware', 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'hook', 'problem', 'proof' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array( 'faq' ),
		'text'   => __( 'Avoid when the "after" would overpromise.', 'chatbot' ),
	),
	'instructions' => 'Describe the reader\'s situation now, then what it is like after the change, then how the product or service gets them there. Keep the "after" realistic and supported by facts.',
	'examples'     => array(
		'good' => array( 'Before: a bike that clicks on every hill. After: quiet, smooth gears. The bridge: our one-day tune-up.' ),
		'bad'  => array( 'After our service you will never have a problem again.' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
