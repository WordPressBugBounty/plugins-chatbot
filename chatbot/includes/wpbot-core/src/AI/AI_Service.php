<?php
/**
 * The one entry point modules use to ask an AI model something.
 *
 * Picks the provider (respecting enabled switches and the fallback setting),
 * checks the spending limits (Spend), serves cached answers, records usage and keeps
 * the failing-key status up to date.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Facade over keys, client, cache, usage and key health.
 */
final class AI_Service {

	/**
	 * Generate text.
	 *
	 * @param string  $module  Module id (owner of the keys and the budget).
	 * @param string  $feature Feature id, for usage reports and the cache.
	 * @param Request $request Request.
	 * @param array   $args    Optional: provider, model (overrides), job_id, cache (bool, default true),
	 *                         estimate (expected USD, checked against the spending limits).
	 * @return Response|WP_Error
	 */
	public static function generate( $module, $feature, Request $request, array $args = array() ) {
		$module  = sanitize_key( $module );
		$feature = sanitize_key( $feature );

		$allowed = \QuantumCloud\WPBotCore\Spend\Spend::allow( $module, $feature, isset( $args['estimate'] ) ? (float) $args['estimate'] : 0.0 );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$order = Keys::call_order( $module );
		if ( ! empty( $args['provider'] ) ) {
			$wanted = sanitize_key( $args['provider'] );
			// An explicit provider (a step override) is used only if it is turned on. A key test
			// may check any provider, so the owner can test a key before choosing it.
			$allowed = ! empty( $args['key_test'] ) ? Providers::exists( $wanted ) : in_array( $wanted, self::enabled( $module ), true );
			$order   = $allowed ? array( $wanted ) : array();
		}
		if ( $request->web_search ) {
			$order = array_values(
				array_filter(
					$order,
					static function ( $provider ) {
						$definition = Providers::get( $provider );
						return $definition && ! empty( $definition['web_search'] );
					}
				)
			);
			if ( array() === $order ) {
				return new WP_Error(
					'qcld_wpbot_core_ai_no_web_search',
					__( 'Web research needs OpenAI, Anthropic Claude, Google Gemini or OpenRouter turned on in AI settings.', 'chatbot' ),
					array( 'status' => 400 )
				);
			}
		}
		if ( array() === $order ) {
			return new WP_Error(
				'qcld_wpbot_core_ai_no_provider',
				__( 'No AI provider is turned on. Add a key and turn a provider on in AI settings.', 'chatbot' ),
				array(
					'status' => 409,
					'cause'  => Error_Classifier::NO_KEY,
				)
			);
		}

		$use_cache = ! isset( $args['cache'] ) || (bool) $args['cache'];
		$job_id    = isset( $args['job_id'] ) ? (int) $args['job_id'] : 0;
		$last      = null;

		foreach ( $order as $index => $provider ) {
			$credentials = Keys::credentials( $module, $provider );
			if ( is_wp_error( $credentials ) ) {
				$data = (array) $credentials->get_error_data();
				$last = self::with_message( $credentials, $provider );
				if ( isset( $data['cause'] ) ) {
					Key_Health::record_failure( $module, $provider, $data['cause'] );
				}
				continue;
			}

			$model = ! empty( $args['model'] ) && 0 === $index ? (string) $args['model'] : $credentials['model'];

			$hash = Result_Cache::key( $module, $feature, $provider, $model, $request );
			if ( $use_cache ) {
				$cached = Result_Cache::get( $hash );
				if ( $cached ) {
					return $cached;
				}
			}

			$response = Provider_Client::send( $provider, $model, $credentials['key'], $request, $credentials['url'] );
			if ( is_wp_error( $response ) ) {
				$data  = (array) $response->get_error_data();
				$cause = isset( $data['cause'] ) ? $data['cause'] : '';
				Key_Health::record_failure( $module, $provider, $cause );
				$last = $response;
				// A refusal or a bad request will not improve on another provider.
				if ( in_array( $cause, array( Error_Classifier::REFUSED, Error_Classifier::BAD_REQUEST ), true ) ) {
					break;
				}
				continue;
			}

			Key_Health::record_success( $module, $provider );
			Usage::record( $module, $feature, $response, $job_id );
			if ( $use_cache && '' !== trim( $response->text ) && ! $response->truncated ) {
				Result_Cache::put( $hash, $module, $feature, $response );
			}
			return $response;
		}

		return $last instanceof WP_Error ? $last : new WP_Error( 'qcld_wpbot_core_ai_failed', __( 'The AI request failed.', 'chatbot' ) );
	}

	/**
	 * Enabled provider ids for a module.
	 *
	 * @param string $module Module id.
	 * @return string[]
	 */
	private static function enabled( $module ) {
		$set = Keys::get_set( $module );
		$ids = array();
		foreach ( $set['providers'] as $id => $entry ) {
			if ( ! empty( $entry['enabled'] ) ) {
				$ids[] = (string) $id;
			}
		}
		return $ids;
	}

	/**
	 * Give a credentials error its owner-facing message.
	 *
	 * @param WP_Error $error    Error from Keys::credentials().
	 * @param string   $provider Provider id.
	 * @return WP_Error
	 */
	private static function with_message( WP_Error $error, $provider ) {
		$data       = (array) $error->get_error_data();
		$cause      = isset( $data['cause'] ) ? $data['cause'] : Error_Classifier::BAD_REQUEST;
		$definition = Providers::get( $provider );
		$explained  = Error_Classifier::explain( $cause, $definition ? $definition['label'] : $provider );
		return new WP_Error(
			$error->get_error_code(),
			$explained['cause'] . ' ' . $explained['fix'],
			array(
				'cause'  => $cause,
				'status' => 409,
				'fix'    => $explained['fix'],
			)
		);
	}
}
