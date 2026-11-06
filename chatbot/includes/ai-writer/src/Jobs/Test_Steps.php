<?php
/**
 * Step handlers of the diagnostics test job.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Jobs;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Test steps: a short wait and a one-word AI answer.
 */
final class Test_Steps {

	/**
	 * Wait one second, like a quick real step.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function wait( array $step, array $job ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Step handler signature.
		sleep( 1 );
		return array(
			'n'       => isset( $step['input']['n'] ) ? (int) $step['input']['n'] : 0,
			'attempt' => (int) $step['attempts'],
			'done_at' => gmdate( 'c' ),
		);
	}

	/**
	 * Ask the active provider for one word.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function ai_ping( array $step, array $job ) {
		$response = AI_Service::generate(
			Module::ID,
			'test_job',
			Request::make( '', 'Reply with the single word OK.', array( 'max_tokens' => 20 ) ),
			array(
				'job_id' => (int) $job['id'],
				'cache'  => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'answer'   => sanitize_text_field( $response->text ),
			'provider' => $response->provider,
			'model'    => $response->model,
			'_usage'   => array(
				'tokens_in'  => $response->tokens_in,
				'tokens_out' => $response->tokens_out,
				'cost'       => $response->cost,
			),
		);
	}
}
