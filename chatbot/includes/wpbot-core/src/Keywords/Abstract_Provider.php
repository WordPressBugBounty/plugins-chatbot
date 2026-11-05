<?php
/**
 * Shared HTTP handling for keyword providers: timeouts, endpoint filter
 * (tests point it at a mock), errors classified for the bold notice. The
 * key never leaves the server and is never logged.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base provider.
 */
abstract class Abstract_Provider implements Provider {

	/**
	 * Owner's key (plain, in memory only).
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Constructor.
	 *
	 * @param string $key Key.
	 */
	public function __construct( $key ) {
		$this->key = (string) $key;
	}

	/**
	 * Endpoint with the test filter.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	protected function endpoint( $url ) {
		/**
		 * Keyword provider endpoint (tests point it at a mock).
		 *
		 * @param string $url      URL.
		 * @param string $provider Provider id.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/keyword_endpoint', $url, $this->id() );
	}

	/**
	 * HTTP request.
	 *
	 * @param string $method GET or POST.
	 * @param string $url    URL.
	 * @param array  $args   wp_remote_request args.
	 * @return array|WP_Error code, body.
	 */
	protected function http( $method, $url, array $args = array() ) {
		$response = wp_remote_request(
			$this->endpoint( $url ),
			array_merge(
				array(
					'method'  => $method,
					'timeout' => 30,
				),
				$args
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: provider name. */
			return new WP_Error( 'qcld_wpbot_core_keyword_unreachable', sprintf( __( '%s could not be reached. Try again later.', 'chatbot' ), $this->label() ), array( 'status' => 502 ) );
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Error for a refused key.
	 *
	 * @return WP_Error
	 */
	protected function bad_key() {
		/* translators: %s: provider name. */
		return new WP_Error( 'qcld_wpbot_core_keyword_key', sprintf( __( '%s refused the key. Copy it again from your account and save it in Settings.', 'chatbot' ), $this->label() ), array( 'status' => 400 ) );
	}

	/**
	 * Error for an empty balance.
	 *
	 * @return WP_Error
	 */
	protected function no_balance() {
		/* translators: %s: provider name. */
		return new WP_Error( 'qcld_wpbot_core_keyword_balance', sprintf( __( 'Your %s balance is empty. Top it up in your account; lookups are paused until then.', 'chatbot' ), $this->label() ), array( 'status' => 402 ) );
	}

	/**
	 * Other provider error.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $message Provider message (no key in it).
	 * @return WP_Error
	 */
	protected function failed( $code, $message = '' ) {
		/* translators: 1: provider name, 2: HTTP status, 3: message. */
		return new WP_Error( 'qcld_wpbot_core_keyword_error', trim( sprintf( __( '%1$s returned an error (HTTP %2$d). %3$s', 'chatbot' ), $this->label(), $code, wp_strip_all_tags( substr( (string) $message, 0, 200 ) ) ) ), array( 'status' => 502 ) );
	}

	/**
	 * One normalised row.
	 *
	 * @param string     $keyword     Keyword.
	 * @param mixed      $volume      Volume.
	 * @param mixed      $cpc         Cost per click.
	 * @param mixed      $competition 0–1 or LOW/MEDIUM/HIGH.
	 * @param array      $trend       Monthly volumes, oldest first.
	 * @param array|null $extra       Extra fields.
	 * @return array
	 */
	protected function row( $keyword, $volume, $cpc, $competition, array $trend = array(), $extra = null ) {
		if ( is_string( $competition ) && ! is_numeric( $competition ) ) {
			$levels      = array(
				'LOW'    => 0.2,
				'MEDIUM' => 0.5,
				'HIGH'   => 0.8,
			);
			$competition = isset( $levels[ strtoupper( $competition ) ] ) ? $levels[ strtoupper( $competition ) ] : null;
		}
		$row = array(
			'keyword'     => (string) $keyword,
			'volume'      => null === $volume || '' === $volume ? null : (int) $volume,
			'cpc'         => null === $cpc || '' === $cpc ? null : round( (float) $cpc, 2 ),
			'competition' => null === $competition || '' === $competition ? null : max( 0.0, min( 1.0, (float) $competition ) ),
			'trend'       => array_values( array_map( 'intval', $trend ) ),
		);
		return is_array( $extra ) ? array_merge( $row, $extra ) : $row;
	}
}
