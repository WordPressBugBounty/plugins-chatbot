<?php
/**
 * Daily token checks. An expired login pauses only the accounts that came
 * from it, and the owner is told (screen 01 shows "Reconnect").
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Accounts;

use QuantumCloud\Social\Settings;
use QuantumCloud\Social\Support\Log;
use QuantumCloud\WPBotCore\Connections\Connection_Status;
use QuantumCloud\WPBotCore\Connections\Meta;
use QuantumCloud\WPBotCore\Connections\Social_Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Token health.
 */
final class Health {

	/**
	 * Daily cron hook.
	 */
	const EVENT = 'qcld_wpbot_social_daily';

	/**
	 * Days before expiry when a Threads token is refreshed.
	 */
	const REFRESH_DAYS = 7;

	/**
	 * Schedule the daily check.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::EVENT, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::EVENT );
		}
	}

	/**
	 * Run every check (daily, or "Check now").
	 *
	 * @param bool $force Run even when the setting is off.
	 * @return array{checked:int, expired:int}
	 */
	public static function run( $force = false ) {
		$result = array(
			'checked' => 0,
			'expired' => 0,
		);
		if ( ! \QuantumCloud\Social\Module::instance()->licensed() || ( ! $force && ! Settings::get( 'check_tokens' ) ) ) {
			return $result;
		}
		$logins = array();
		foreach ( Account_Store::all() as $account ) {
			$logins[ $account['login'] ][] = $account;
		}
		foreach ( $logins as $login => $accounts ) {
			$state = self::check_login( (string) $login );
			if ( null === $state ) {
				continue;
			}
			++$result['checked'];
			if ( 'expired' === $state ) {
				++$result['expired'];
			}
		}
		Log::prune( (int) Settings::get( 'log_days' ) );
		return $result;
	}

	/**
	 * Check one login and update its accounts.
	 *
	 * @param string $login Login key.
	 * @return string|null ok, expired, error; null when the login is not checkable.
	 */
	public static function check_login( $login ) {
		if ( 'unreadable' === Token_Vault::status( $login ) ) {
			// QA #42: the saved login no longer decrypts (salts changed).
			self::apply( 0 === strpos( $login, 'threads:' ) ? 'threads' : 'meta', $login, 'expired', __( 'WPBot can no longer read the saved login (the site\'s security keys changed, for example after a move). Reconnect to resume posting.', 'chatbot' ) );
			return 'expired';
		}
		if ( 0 === strpos( $login, 'meta:' ) ) {
			$token = Connector::login_token( $login );
			$state = '' === $token ? 'expired' : Meta::check( $token );
			self::apply( 'meta', $login, $state, __( 'The Facebook login expired or was removed. Reconnect to resume posting.', 'chatbot' ) );
			return $state;
		}
		if ( 0 === strpos( $login, 'threads:' ) ) {
			$tokens = json_decode( Token_Vault::get( $login ), true );
			$state  = self::threads_state( is_array( $tokens ) ? $tokens : array(), time() );
			if ( 'refresh' === $state ) {
				$provider = Social_Auth::provider( 'threads' );
				$fresh    = $provider ? $provider->refresh( 'threads', $tokens ) : null;
				if ( is_array( $fresh ) ) {
					Token_Vault::put( $login, (string) wp_json_encode( $fresh ) );
					$state = 'ok';
				} else {
					$state = 'error';
				}
			}
			self::apply( 'threads', $login, $state, __( 'The Threads login expired. Reconnect to resume posting.', 'chatbot' ) );
			return $state;
		}
		return null;
	}

	/**
	 * What to do with a Threads token: ok, refresh (near expiry) or expired.
	 *
	 * @param array $tokens Tokens with expires_at.
	 * @param int   $now    Unix time.
	 * @return string
	 */
	public static function threads_state( array $tokens, $now ) {
		if ( empty( $tokens['access_token'] ) ) {
			return 'expired';
		}
		$expires = isset( $tokens['expires_at'] ) ? (int) $tokens['expires_at'] : 0;
		if ( 0 === $expires ) {
			return 'ok';
		}
		if ( $expires <= $now ) {
			return 'expired';
		}
		return $expires - $now < self::REFRESH_DAYS * DAY_IN_SECONDS ? 'refresh' : 'ok';
	}

	/**
	 * Store the state on the login's accounts and in WPBot Core.
	 *
	 * @param string $connection Connection id.
	 * @param string $login      Login key.
	 * @param string $state      ok, expired or error.
	 * @param string $message    Message for expired logins.
	 * @return void
	 */
	private static function apply( $connection, $login, $state, $message ) {
		if ( 'expired' === $state ) {
			Account_Store::set_status( $login, 'expired', $message );
			if ( Connection_Status::set( $connection, $login, 'expired', $message ) ) {
				Log::add( 'paused', 'token_expired', $message );
			}
			return;
		}
		if ( 'ok' === $state ) {
			Account_Store::set_status( $login, 'ok' );
			Connection_Status::set( $connection, $login, 'ok' );
		}
	}
}
