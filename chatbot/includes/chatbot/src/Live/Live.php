<?php
/**
 * Live chat engine (live chat rebuild LC1). Off unless a host or WPBot Pro switches it on with
 * `qcld_wpbot_chat/live_chat_enabled` (owner, 19 November 2026: the rebuild is Pro; the engine is
 * kept here so Slack can come to Free later).
 *
 * Performance budget (plan 3.3): nothing runs on an ordinary page view; the tables are created on
 * an admin page only when live chat is on; the visitor polls a static file.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Live chat bootstrap.
 */
final class Live {

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'admin_init', array( __CLASS__, 'install' ) );
		add_action( Channels::HOOK, array( Channels::class, 'dispatch' ) );
		Escalation::init();
		Assistant::init();
		Push::init();
	}

	/**
	 * Whether live chat is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Whether the live chat engine runs (WPBot Pro).
		 *
		 * @param bool $enabled Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_chat_enabled', false );
	}

	/**
	 * Routes, when live chat is on.
	 *
	 * @return void
	 */
	public static function rest() {
		if ( self::enabled() ) {
			Rest::register();
		}
	}

	/**
	 * Tables, when live chat is on (admin pages only).
	 *
	 * @return void
	 */
	public static function install() {
		if ( self::enabled() ) {
			Schema::maybe_install();
		}
	}

	/**
	 * The visitor's access key for a conversation (signed: conversation and session).
	 *
	 * @param array $conv Conversation row.
	 * @return string
	 */
	public static function visitor_key( array $conv ) {
		return substr( hash_hmac( 'sha256', 'live-visitor|' . (int) $conv['id'] . '|' . (string) $conv['session_id'], wp_salt( 'auth' ) ), 0, 40 );
	}

	/**
	 * Whether a visitor key opens a conversation.
	 *
	 * @param array  $conv Conversation row.
	 * @param string $key  Key sent by the browser.
	 * @return bool
	 */
	public static function visitor_can( array $conv, $key ) {
		return is_string( $key ) && '' !== $key && hash_equals( self::visitor_key( $conv ), $key );
	}

	/**
	 * Settings for the widget when the visitor is offered a person (no request on page views).
	 *
	 * @param string $department Department.
	 * @return array
	 */
	public static function widget_settings( $department = '' ) {
		return array(
			'enabled'   => self::enabled(),
			'available' => self::enabled() && Routing::available( $department ),
			'rest'      => esc_url_raw( rest_url( Rest::NS . '/live' ) ),
			// "Notify me when we reply" in the chat window (Web Push; see Push).
			'push'      => self::enabled() && Push::visitors_on() && Push::available() ? array(
				'key'   => Push::public_key(),
				'sw'    => esc_url_raw( add_query_arg( 'qcld_wpbot_live_sw', '1', home_url( '/' ) ) ),
				'scope' => (string) wp_parse_url( home_url( '/wpbot-live-push/' ), PHP_URL_PATH ),
			) : null,
		);
	}
}
