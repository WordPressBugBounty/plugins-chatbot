<?php
/**
 * How a social network login is performed: with the owner's own developer
 * app today, possibly a QuantumCloud connect app later (Social PLAN 6.3).
 *
 * Whatever the provider, the caller receives the same token array and
 * stores it the same way.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Social auth provider.
 */
interface Auth_Provider {

	/**
	 * Provider id, for example "own_app".
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Whether this provider can log in to an app ("meta", "threads", ...).
	 *
	 * @param string $app App id.
	 * @return bool
	 */
	public function supports( $app );

	/**
	 * Consent URL. Creates a one-time state bound to the current user.
	 *
	 * @param string $app        App id.
	 * @param array  $scopes     Scopes to ask for.
	 * @param string $return_url Admin URL to come back to.
	 * @param string $capability Capability the callback requires.
	 * @param string $owner      Module that started the login (for example
	 *                           "social"); handed back in the payload so each
	 *                           module only takes its own logins.
	 * @return string|WP_Error
	 */
	public function auth_url( $app, array $scopes, $return_url, $capability, $owner = '' );

	/**
	 * Swap an authorization code for tokens.
	 *
	 * @param string $app   App id.
	 * @param string $code  Code from the network.
	 * @param array  $state Consumed state payload (holds the PKCE verifier).
	 * @return array|WP_Error Token array: access_token, expires_at (0 = no expiry), plus network extras.
	 */
	public function exchange( $app, $code, array $state );

	/**
	 * Refresh or extend a token.
	 *
	 * @param string $app    App id.
	 * @param array  $tokens Current token array.
	 * @return array|WP_Error New token array.
	 */
	public function refresh( $app, array $tokens );
}
