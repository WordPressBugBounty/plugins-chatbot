<?php
/**
 * AI calls for AI Writer: applies the per-step model choice from settings
 * and the module's keys, budget and usage through WPBot Core.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\AI\Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over AI_Service.
 */
final class Writer_AI {

	/**
	 * Ask for text.
	 *
	 * @param string $feature Feature id (usage reports, cache).
	 * @param string $system  System prompt.
	 * @param string $prompt  User prompt.
	 * @param array  $args    mode (creative|strict), json (bool), max_tokens, job_id, cache, web_search (bool; sources on the Response),
	 *                        images (mime + base64 data each; needs a WPBot Core that reads images, otherwise left out).
	 * @return Response|WP_Error
	 */
	public static function ask( $feature, $system, $prompt, array $args = array() ) {
		$mode    = isset( $args['mode'] ) && 'creative' === $args['mode'] ? 'creative' : 'strict';
		$request = Request::make(
			$system,
			$prompt,
			array(
				'mode'       => $mode,
				'json'       => ! empty( $args['json'] ),
				'max_tokens' => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 1200,
				'timeout'    => isset( $args['web_search'] ) && $args['web_search'] ? 90 : 45,
				'web_search' => ! empty( $args['web_search'] ),
				'images'     => isset( $args['images'] ) && is_array( $args['images'] ) ? $args['images'] : array(),
			)
		);

		$call  = array(
			'job_id' => isset( $args['job_id'] ) ? (int) $args['job_id'] : 0,
			'cache'  => isset( $args['cache'] ) ? (bool) $args['cache'] : true,
		);
		$steps = (array) Settings::get( 'step_models' );
		if ( ! empty( $steps[ $mode ]['provider'] ) ) {
			$call['provider'] = $steps[ $mode ]['provider'];
			if ( ! empty( $steps[ $mode ]['model'] ) ) {
				$call['model'] = $steps[ $mode ]['model'];
			}
		}
		return AI_Service::generate( Module::ID, $feature, $request, $call );
	}

	/**
	 * Ask for a JSON object.
	 *
	 * @param string $feature Feature id.
	 * @param string $system  System prompt.
	 * @param string $prompt  User prompt.
	 * @param array  $args    As ask().
	 * @return array{data: array, response: Response}|WP_Error
	 */
	public static function ask_json( $feature, $system, $prompt, array $args = array() ) {
		$args['json'] = true;
		$response     = self::ask( $feature, $system, $prompt, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = $response->json();
		if ( null === $data ) {
			return new WP_Error(
				'qcld_wpbot_writer_bad_answer',
				__( 'The AI answer could not be read. Trying again usually helps.', 'chatbot' ),
				array( 'retryable' => true )
			);
		}
		return array(
			'data'     => $data,
			'response' => $response,
		);
	}

	/**
	 * Usage of a response, for job steps.
	 *
	 * @param Response $response Response.
	 * @return array
	 */
	public static function usage( Response $response ) {
		return array(
			'tokens_in'  => $response->tokens_in,
			'tokens_out' => $response->tokens_out,
			'cost'       => $response->cost,
		);
	}
}
