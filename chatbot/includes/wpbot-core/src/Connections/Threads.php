<?php
/**
 * Threads connection: its own OAuth (threads.net, graph.threads.net,
 * threads_* scopes) and long-lived token refresh, separate from Facebook
 * Login (Social build-brief A3, QA #9).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Threads API connection helpers.
 */
final class Threads {

	/**
	 * Threads Graph host.
	 */
	const HOST = 'https://graph.threads.net';

	/**
	 * Swap a short-lived token for a long-lived one (about 60 days).
	 *
	 * @param array $tokens Token array with access_token.
	 * @return array|WP_Error
	 */
	public static function extend( array $tokens ) {
		if ( empty( $tokens['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_threads_no_token', __( 'Threads did not return an access token.', 'chatbot' ) );
		}
		$query = http_build_query(
			array(
				'grant_type'    => 'th_exchange_token',
				'client_secret' => Social_Apps::client_secret( 'threads' ),
			),
			'',
			'&'
		);
		$data  = Social_Http::send( 'GET', self::HOST . '/access_token?' . $query, array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_threads_no_token', __( 'Threads did not return a long-lived access token.', 'chatbot' ) );
		}
		$data['user_id'] = isset( $tokens['user_id'] ) ? (string) $tokens['user_id'] : '';
		return Own_App_Auth::normalise( $data );
	}

	/**
	 * Refresh a long-lived token before it expires.
	 *
	 * @param array $tokens Token array with access_token.
	 * @return array|WP_Error
	 */
	public static function refresh( array $tokens ) {
		if ( empty( $tokens['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_threads_no_token', __( 'Threads is not connected.', 'chatbot' ) );
		}
		$data = Social_Http::send( 'GET', self::HOST . '/refresh_access_token?grant_type=th_refresh_token', array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_threads_no_token', __( 'Threads did not return a refreshed token.', 'chatbot' ) );
		}
		$data['user_id'] = isset( $tokens['user_id'] ) ? (string) $tokens['user_id'] : '';
		return Own_App_Auth::normalise( $data );
	}
}
