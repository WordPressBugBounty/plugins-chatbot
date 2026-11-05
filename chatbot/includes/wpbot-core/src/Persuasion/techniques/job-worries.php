<?php
/**
 * Technique: The job and the worries (job_worries). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'job_worries',
	'kind'         => 'technique',
	'group'        => 'reader',
	'label'        => __( 'The job and the worries', 'chatbot' ),
	'method'       => 'Jobs to be done (push, pull, anxiety, habit)',
	'why'          => __( 'People buy to get a job done; answering their worries removes what holds them back.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'product', 'product_blurb', 'page_copy', 'landing', 'faq', 'buying_guide' ),
		'stages'   => array( 'solution_aware', 'product_aware' ),
		'jobs'     => array( 'problem', 'objection' ),
		'channels' => array(),
	),
	'avoid'        => array(
		'stages' => array(),
		'types'  => array(),
		'text'   => '',
	),
	'instructions' => 'State the job the buyer wants done, what draws them to the product, and the worries that hold them back; answer each worry with a real fact or the guarantee from the kit.',
	'examples'     => array(
		'good' => array( 'Worried it will not fit? Free returns for 30 days (from our policy).' ),
		'bad'  => array( 'Zero risk, guaranteed forever (not in the kit).' ),
	),
	'needs'        => array( 'kit:guarantee' ),
	'edition'      => 'free',
);
