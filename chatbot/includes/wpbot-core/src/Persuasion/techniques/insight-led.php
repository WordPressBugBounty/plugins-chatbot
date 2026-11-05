<?php
/**
 * Technique: Lead with a new insight (insight_led). Part of the technique library (round 3, CR-13).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'           => 'insight_led',
	'kind'         => 'framework',
	'group'        => 'structure',
	'label'        => __( 'Lead with a new insight', 'chatbot' ),
	'method'       => 'Challenger approach (teach, tailor, take control)',
	'why'          => __( 'A useful idea the reader had not considered builds trust before any pitch.', 'chatbot' ),
	'fits'         => array(
		'types'    => array( 'blog_post', 'linkedin', 'page_copy', 'email' ),
		'stages'   => array( 'unaware', 'problem_aware' ),
		'jobs'     => array( 'hook', 'explain' ),
		'channels' => array( 'linkedin' ),
	),
	'avoid'        => array(
		'stages' => array( 'most_aware' ),
		'types'  => array( 'product', 'product_blurb' ),
		'text'   => __( 'Only when you have a real, defensible insight.', 'chatbot' ),
	),
	'instructions' => 'Open with a genuine insight the reader probably has not considered, backed by a fact or example from the sources, then show what it means for them. Do not invent statistics.',
	'examples'     => array(
		'good' => array( 'Most chain wear comes from dirt, not miles. Clean it monthly and it lasts twice as long.' ),
		'bad'  => array( 'Studies show 97% of experts agree (no source).' ),
	),
	'needs'        => array(),
	'edition'      => 'free',
);
