<?php
/**
 * Turns provider HTTP failures into a small set of causes with owner-facing fixes.
 *
 * The causes drive the bold failing-key notice: each has a plain-language
 * cause and the action that fixes it.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Maps status codes and error bodies to causes.
 */
final class Error_Classifier {

	const INVALID_KEY   = 'invalid_key';
	const NO_CREDIT     = 'no_credit';
	const RATE_LIMIT    = 'rate_limit';
	const MODEL_REMOVED = 'model_removed';
	const PROVIDER_DOWN = 'provider_down';
	const NETWORK       = 'network';
	const REFUSED       = 'refused';
	const BAD_REQUEST   = 'bad_request';
	const NO_KEY        = 'no_key';
	const UNREADABLE    = 'key_unreadable';

	/**
	 * Causes that mean the key or account needs the owner's attention.
	 *
	 * @var string[]
	 */
	const HEALTH_CAUSES = array( self::INVALID_KEY, self::NO_CREDIT, self::RATE_LIMIT, self::MODEL_REMOVED, self::NO_KEY, self::UNREADABLE );

	/**
	 * Causes worth retrying later without any change.
	 *
	 * @var string[]
	 */
	const RETRYABLE = array( self::RATE_LIMIT, self::PROVIDER_DOWN, self::NETWORK );

	/**
	 * Classify an HTTP failure.
	 *
	 * @param int    $status HTTP status (0 for transport errors).
	 * @param string $body   Raw response body.
	 * @return string One of the constants.
	 */
	public static function classify( $status, $body ) {
		$status = (int) $status;
		$text   = strtolower( (string) $body );

		if ( 0 === $status ) {
			return self::NETWORK;
		}

		// Billing problems arrive as 400, 402, 403 or 429 depending on the provider.
		$billing_words = array( 'insufficient_quota', 'credit balance', 'insufficient credits', 'billing', 'payment required', 'exceeded your current quota', 'out of credits', 'key limit exceeded', 'spending limit', 'spend limit' );
		foreach ( $billing_words as $word ) {
			if ( false !== strpos( $text, $word ) ) {
				return self::NO_CREDIT;
			}
		}
		if ( 402 === $status ) {
			return self::NO_CREDIT;
		}

		if ( 401 === $status || 403 === $status ) {
			return self::INVALID_KEY;
		}
		// Gemini answers 400 for a bad key.
		if ( 400 === $status && ( false !== strpos( $text, 'api key not valid' ) || false !== strpos( $text, 'api_key_invalid' ) ) ) {
			return self::INVALID_KEY;
		}

		$model_words = array( 'model_not_found', 'does not exist', 'no such model', 'is not found for api version', 'model not found', 'unknown model', 'invalid model', 'has been deprecated', 'decommissioned', 'not a valid model' );
		foreach ( $model_words as $word ) {
			if ( false !== strpos( $text, $word ) ) {
				return self::MODEL_REMOVED;
			}
		}
		if ( 404 === $status ) {
			return self::MODEL_REMOVED;
		}

		if ( 429 === $status ) {
			return self::RATE_LIMIT;
		}
		if ( $status >= 500 ) {
			return self::PROVIDER_DOWN;
		}
		return self::BAD_REQUEST;
	}

	/**
	 * Owner-facing cause and fix for a classified error.
	 *
	 * @param string $cause    One of the constants.
	 * @param string $provider Provider label.
	 * @return array{cause: string, fix: string}
	 */
	public static function explain( $cause, $provider ) {
		switch ( $cause ) {
			case self::INVALID_KEY:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'The %s API key is invalid or was revoked.', 'chatbot' ), $provider ),
					'fix'   => __( 'Create a new key in your provider account and paste it in AI settings.', 'chatbot' ),
				);
			case self::NO_CREDIT:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'Your %s account has no credit left.', 'chatbot' ), $provider ),
					'fix'   => __( 'Add funds or a payment method in your provider account, then try again.', 'chatbot' ),
				);
			case self::RATE_LIMIT:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( '%s is limiting how many requests you can send right now.', 'chatbot' ), $provider ),
					'fix'   => __( 'Wait a few minutes, or raise the rate limit in your provider account.', 'chatbot' ),
				);
			case self::MODEL_REMOVED:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'The selected %s model is no longer available.', 'chatbot' ), $provider ),
					'fix'   => __( 'Pick another model in AI settings.', 'chatbot' ),
				);
			case self::NO_KEY:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'No API key is saved for %s.', 'chatbot' ), $provider ),
					'fix'   => __( 'Add a key in AI settings, or copy it from the main AI Settings.', 'chatbot' ),
				);
			case self::UNREADABLE:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'The saved %s key can no longer be read (the site security keys changed).', 'chatbot' ), $provider ),
					'fix'   => __( 'Paste the key again in AI settings.', 'chatbot' ),
				);
			case self::PROVIDER_DOWN:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( '%s is having problems right now.', 'chatbot' ), $provider ),
					'fix'   => __( 'Try again in a few minutes.', 'chatbot' ),
				);
			case self::NETWORK:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( 'Your site could not reach %s.', 'chatbot' ), $provider ),
					'fix'   => __( 'Try again. If it keeps failing, ask your host whether outgoing connections are blocked.', 'chatbot' ),
				);
			case self::REFUSED:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( '%s declined to answer this request.', 'chatbot' ), $provider ),
					'fix'   => __( 'Rephrase the topic or try another model.', 'chatbot' ),
				);
			default:
				return array(
					/* translators: %s: AI provider name. */
					'cause' => sprintf( __( '%s rejected the request.', 'chatbot' ), $provider ),
					'fix'   => __( 'Try again. If it keeps failing, pick another model in AI settings.', 'chatbot' ),
				);
		}
	}
}
