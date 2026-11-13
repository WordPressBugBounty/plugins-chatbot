<?php
/**
 * Builds the adapter Target for an account: the right token for each
 * network, read at send time and never stored outside its vault.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Accounts\Connector;
use QuantumCloud\Social\Accounts\Token_Vault;
use QuantumCloud\Social\Networks\Target;
use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Connections\Social_Apps;

defined( 'ABSPATH' ) || exit;

/**
 * Target factory.
 */
final class Target_Factory {

	/**
	 * Why make() returned null, in words for the owner.
	 *
	 * @param array $account Account.
	 * @return array{code:string, message:string, retryable?:bool}
	 */
	public static function problem( array $account ) {
		/**
		 * Why no token is available, from the module that supplies it (for example a
		 * token refresh that is busy or hit a network blip: retryable, no reconnect).
		 *
		 * @param array|null $problem code, message, retryable; null to use the default.
		 * @param array      $account Account.
		 */
		$custom = apply_filters( 'qcld_wpbot_social/target_problem', null, $account );
		if ( is_array( $custom ) && ! empty( $custom['code'] ) && ! empty( $custom['message'] ) ) {
			return array(
				'code'      => sanitize_key( (string) $custom['code'] ),
				'message'   => (string) $custom['message'],
				'retryable' => ! empty( $custom['retryable'] ),
			);
		}
		$key = in_array( $account['network'], array( 'facebook', 'instagram' ), true ) ? Account_Store::key( $account ) : (string) $account['login'];
		if ( '' !== $key && 'unreadable' === Token_Vault::status( $key ) ) {
			return array(
				'code'    => 'token_unreadable',
				'message' => __( 'WPBot can no longer read this account\'s saved login. This happens when the site\'s security keys change, for example after a move. Reconnect the account in Social › Accounts, then retry.', 'chatbot' ),
			);
		}
		return array(
			'code'    => 'reconnect',
			'message' => __( 'This account needs to be reconnected before WPBot can post to it.', 'chatbot' ),
		);
	}

	/**
	 * Target for an account and a stored target row.
	 *
	 * @param array $account Account.
	 * @param array $row     Target row (step_state, attempt_started_at).
	 * @return Target|null Null when no token is available (reconnect needed).
	 */
	public static function make( array $account, array $row ) {
		$token  = '';
		$secret = '';
		$remote = $account['remote_id'];
		switch ( $account['network'] ) {
			case 'facebook':
			case 'instagram':
				$token  = Token_Vault::get( Account_Store::key( $account ) );
				$secret = Social_Apps::client_secret( 'meta' );
				break;
			case 'threads':
				$tokens = json_decode( Token_Vault::get( $account['login'] ), true );
				$token  = is_array( $tokens ) && isset( $tokens['access_token'] ) ? (string) $tokens['access_token'] : '';
				break;
			case 'telegram':
				$token = Connector::telegram_token();
				break;
			case 'google_business':
				$access = class_exists( Google::class ) ? Google::access_token() : '';
				$token  = is_string( $access ) ? $access : '';
				$parent = isset( $account['meta']['account'] ) ? (string) $account['meta']['account'] : '';
				$remote = ( '' !== $parent && 0 !== strpos( $remote, 'accounts/' ) ) ? $parent . '/' . $remote : $remote;
				break;
			default:
				/**
				 * Token for networks added by the Master module.
				 *
				 * @param string $token   Token.
				 * @param array  $account Account.
				 */
				$token = (string) apply_filters( 'qcld_wpbot_social/target_token', '', $account );
		}
		if ( '' === $token ) {
			return null;
		}
		$started = ! empty( $row['attempt_started_at'] ) ? (int) strtotime( $row['attempt_started_at'] . ' UTC' ) : time();
		return new Target( $remote, $token, $secret, isset( $row['step_state'] ) ? (array) $row['step_state'] : array(), $started );
	}
}
