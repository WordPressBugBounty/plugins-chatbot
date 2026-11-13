<?php
/**
 * One account a post goes to, with the state saved between publish steps.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Publishing target: account credentials plus resumable state.
 */
final class Target {

	/**
	 * Remote account id (Page id, Instagram user id, ...).
	 *
	 * @var string
	 */
	private $account_id;

	/**
	 * Access token for the account. Never logged or sent to the browser.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * App secret for request signing (appsecret_proof), or ''.
	 *
	 * @var string
	 */
	private $app_secret;

	/**
	 * State saved by earlier steps, for example a media container id.
	 *
	 * @var array<string,mixed>
	 */
	private $state;

	/**
	 * Unix time the current attempt started (used when reconciling).
	 *
	 * @var int
	 */
	private $attempt_started;

	/**
	 * Build a target.
	 *
	 * @param string $account_id      Remote account id.
	 * @param string $token           Access token.
	 * @param string $app_secret      App secret or ''.
	 * @param array  $state           Saved state.
	 * @param int    $attempt_started Unix time the attempt started.
	 */
	public function __construct( $account_id, $token, $app_secret = '', array $state = array(), $attempt_started = 0 ) {
		$this->account_id      = (string) $account_id;
		$this->token           = (string) $token;
		$this->app_secret      = (string) $app_secret;
		$this->state           = $state;
		$this->attempt_started = (int) $attempt_started;
	}

	/**
	 * Remote account id.
	 *
	 * @return string
	 */
	public function account_id() {
		return $this->account_id;
	}

	/**
	 * Access token.
	 *
	 * @return string
	 */
	public function token() {
		return $this->token;
	}

	/**
	 * App secret.
	 *
	 * @return string
	 */
	public function app_secret() {
		return $this->app_secret;
	}

	/**
	 * One saved state value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Value when missing.
	 * @return mixed
	 */
	public function state( $key, $fallback = null ) {
		return array_key_exists( $key, $this->state ) ? $this->state[ $key ] : $fallback;
	}

	/**
	 * Unix time the attempt started.
	 *
	 * @return int
	 */
	public function attempt_started() {
		return $this->attempt_started;
	}

	/**
	 * Debug info without secrets.
	 *
	 * @return array<string,mixed>
	 */
	public function __debugInfo() {
		return array(
			'account_id' => $this->account_id,
			'state'      => $this->state,
		);
	}
}
