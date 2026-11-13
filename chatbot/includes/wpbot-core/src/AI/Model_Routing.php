<?php
/**
 * Model retirement and routing (chatbot features CB1b).
 *
 * - Retired models: a saved model the provider has retired is swapped for its replacement
 *   (`Model_Routing::replacement()`), so a site does not stop answering; callers show the owner
 *   a notice.
 * - Routing hooks for Pro: `qcld_wpbot_core/route_model` (cheap or strong model per question)
 *   and `qcld_wpbot_core/fallback_provider` (another provider when one fails).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Model routing.
 */
final class Model_Routing {

	/**
	 * Retired models and their replacements, per provider (filter qcld_wpbot_core/retired_models).
	 *
	 * @return array<string,array<string,string>> provider => retired model => replacement.
	 */
	public static function retired() {
		$retired = array(
			'openai'     => array(
				'text-davinci-003' => 'gpt-4o-mini',
				'text-davinci-002' => 'gpt-4o-mini',
				'text-davinci-001' => 'gpt-4o-mini',
				'text-curie-001'   => 'gpt-4o-mini',
				'text-babbage-001' => 'gpt-4o-mini',
				'text-ada-001'     => 'gpt-4o-mini',
				'gpt-3.5-turbo'    => 'gpt-4o-mini',
				'gpt-4-32k'        => 'gpt-4.1-mini',
				// Image models OpenAI no longer offers (checked 10 November 2026).
				'gpt-image-1'      => 'gpt-image-2',
				'dall-e-3'         => 'gpt-image-2',
				'dall-e-2'         => 'gpt-image-2',
			),
			'gemini'     => array(
				'gemini-pro'       => 'gemini-2.5-flash',
				'gemini-1.0-pro'   => 'gemini-2.5-flash',
				'gemini-1.5-flash' => 'gemini-2.5-flash',
				'gemini-1.5-pro'   => 'gemini-2.5-pro',
			),
			'claude'     => array(
				'claude-2.1'               => 'claude-haiku-5-5',
				'claude-2.0'               => 'claude-haiku-5-5',
				'claude-instant-1.2'       => 'claude-haiku-5-5',
				'claude-3-haiku-20240307'  => 'claude-haiku-5-5',
				'claude-3-sonnet-20240229' => 'claude-sonnet-5-5',
				'claude-3-opus-20240229'   => 'claude-opus-5-5',
			),
			'openrouter' => array(
				'openai/gpt-3.5-turbo' => 'openai/gpt-4o-mini',
			),
		);
		/**
		 * Retired models and their replacements.
		 *
		 * @param array $retired provider => retired model => replacement.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/retired_models', $retired );
	}

	/**
	 * The replacement for a retired model ('' when the model is not retired).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return string
	 */
	public static function replacement( $provider, $model ) {
		$retired = self::retired();
		return isset( $retired[ $provider ][ $model ] ) ? (string) $retired[ $provider ][ $model ] : '';
	}

	/**
	 * The model to use for a request (Pro routing; default: the saved model, or its replacement).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Saved model.
	 * @param array  $context  module, feature, question (text), tokens estimate.
	 * @return string
	 */
	public static function route( $provider, $model, array $context = array() ) {
		$replacement = self::replacement( $provider, $model );
		$chosen      = '' !== $replacement ? $replacement : $model;
		/**
		 * The model for this request (cheap or strong model per question).
		 *
		 * @param string $chosen   Model.
		 * @param string $provider Provider id.
		 * @param array  $context  module, feature, question.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/route_model', $chosen, $provider, $context );
	}

	/**
	 * Another provider to try when one failed ('' = none; default none, Pro adds failover).
	 *
	 * @param string    $provider Provider that failed.
	 * @param \WP_Error $error    Its error.
	 * @param array     $context  module, feature.
	 * @return string
	 */
	public static function fallback( $provider, $error, array $context = array() ) {
		/**
		 * Another provider to try when one fails ('' = none).
		 *
		 * @param string    $fallback Provider id.
		 * @param string    $provider Provider that failed.
		 * @param \WP_Error $error    Error.
		 * @param array     $context  module, feature.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/fallback_provider', '', $provider, $error, $context );
	}
}
