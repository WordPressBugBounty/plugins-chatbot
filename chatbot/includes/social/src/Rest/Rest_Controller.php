<?php
/**
 * REST routes under wpbot-social/v1. Every route checks a wpbot_social_*
 * capability; cookie requests also need the wp_rest nonce (WordPress core).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Rest;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Admin\Google_Card;
use QuantumCloud\Social\Accounts\Connector;
use QuantumCloud\Social\Accounts\Health;
use QuantumCloud\Social\Accounts\Token_Vault;
use QuantumCloud\Social\Settings;
use QuantumCloud\Social\Support\Log;
use QuantumCloud\WPBotCore\Connections\Social_Apps;
use QuantumCloud\WPBotCore\Connections\Social_Auth;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class Rest_Controller {

	/**
	 * Namespace.
	 */
	const NS = 'wpbot-social/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view     = self::can( 'wpbot_social_view' );
		$settings = self::can( 'wpbot_social_settings' );
		// The Google connection is shared by every WPBot module: Social's settings and site administrators only.
		$google = static function () {
			return current_user_can( 'wpbot_social_settings' ) && current_user_can( 'manage_options' );
		};
		$id_arg = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		$routes = array(
			array( '/accounts', WP_REST_Server::READABLE, 'accounts', $view, array() ),
			array( '/accounts/(?P<id>\d+)', 'PATCH', 'update_account', $settings, $id_arg ),
			array( '/accounts/(?P<id>\d+)', WP_REST_Server::DELETABLE, 'delete_account', $settings, $id_arg ),
			array( '/apps/(?P<app>[a-z_]+)', WP_REST_Server::READABLE, 'get_app', $settings, array() ),
			array( '/apps/(?P<app>[a-z_]+)', WP_REST_Server::CREATABLE, 'save_app', $settings, array() ),
			array( '/connect/(?P<app>[a-z_]+)', WP_REST_Server::CREATABLE, 'connect', $settings, array() ),
			array( '/meta/pages', WP_REST_Server::READABLE, 'meta_pages', $settings, array() ),
			array( '/meta/pages', WP_REST_Server::CREATABLE, 'save_meta_pages', $settings, array() ),
			array( '/telegram', WP_REST_Server::CREATABLE, 'add_telegram', $settings, array() ),
			array( '/telegram/bot', WP_REST_Server::READABLE, 'telegram_bot', $settings, array() ),
			array( '/telegram/bot', WP_REST_Server::CREATABLE, 'save_telegram_bot', $settings, array() ),
			array( '/telegram/bot', WP_REST_Server::DELETABLE, 'forget_telegram_bot', $settings, array() ),
			array( '/google/locations', WP_REST_Server::READABLE, 'google_locations', $settings, array() ),
			array( '/google/locations', WP_REST_Server::CREATABLE, 'add_google_location', $settings, array() ),
			array( '/google/connection', WP_REST_Server::READABLE, 'google_connection', $google, array() ),
			array( '/google/connection', WP_REST_Server::CREATABLE, 'save_google_client', $google, array() ),
			array( '/google/connect', WP_REST_Server::CREATABLE, 'connect_google', $google, array() ),
			array( '/google/disconnect', WP_REST_Server::CREATABLE, 'disconnect_google', $google, array() ),
			array( '/google/business-profile', WP_REST_Server::CREATABLE, 'google_business_profile', $google, array() ),
			array( '/settings', WP_REST_Server::READABLE, 'get_settings', $settings, array() ),
			array( '/settings', WP_REST_Server::CREATABLE, 'save_settings', $settings, array() ),
			array( '/tokens/check', WP_REST_Server::CREATABLE, 'check_tokens', $settings, array() ),
			array( '/log', WP_REST_Server::READABLE, 'log', $settings, array() ),
			array( '/export', WP_REST_Server::READABLE, 'export', $settings, array() ),
			array( '/import', WP_REST_Server::CREATABLE, 'import', $settings, array() ),
			array( '/disconnect-all', WP_REST_Server::CREATABLE, 'disconnect_all', $settings, array() ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				self::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $route[3],
					'args'                => $route[4],
				)
			);
		}
	}

	/**
	 * Permission callback for a capability.
	 *
	 * @param string $cap Capability.
	 * @return callable
	 */
	public static function can( $cap ) {
		return static function () use ( $cap ) {
			return current_user_can( $cap );
		};
	}

	/**
	 * Accounts for the screen (never tokens).
	 *
	 * @return array
	 */
	public static function accounts() {
		return array_map( array( __CLASS__, 'account_view' ), Account_Store::all() );
	}

	/**
	 * Public view of an account.
	 *
	 * @param array $account Account.
	 * @return array
	 */
	public static function account_view( array $account ) {
		return array(
			'id'             => $account['id'],
			'network'        => $account['network'],
			'kind'           => $account['kind'],
			'name'           => $account['name'],
			'handle'         => $account['handle'],
			'brand_id'       => $account['brand_id'],
			'parent_id'      => $account['parent_id'],
			'status'         => $account['status'],
			'status_message' => $account['status_message'],
			'auto_share'     => $account['auto_share'],
		);
	}

	/**
	 * Change auto-share or brand.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update_account( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! Account_Store::get( $id ) ) {
			return new WP_Error( 'qcld_wpbot_social_not_found', __( 'Account not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$changes = array();
		foreach ( array( 'auto_share', 'brand_id' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = 'auto_share' === $key ? rest_sanitize_boolean( $request->get_param( $key ) ) : Connector::brand( $request->get_param( $key ) );
			}
		}
		Account_Store::update( $id, $changes );
		return self::account_view( Account_Store::get( $id ) );
	}

	/**
	 * Disconnect one account from this site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete_account( WP_REST_Request $request ) {
		$account = Account_Store::get( (int) $request['id'] );
		if ( ! $account ) {
			return new WP_Error( 'qcld_wpbot_social_not_found', __( 'Account not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		Account_Store::delete( $account['id'] );
		if ( array() === Account_Store::by_login( $account['login'] ) ) {
			Token_Vault::forget( $account['login'] );
		}
		Log::add( 'info', 'account_removed', $account['name'] . ' disconnected from this site' );
		return array( 'deleted' => true );
	}

	/**
	 * App settings (masked).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function get_app( WP_REST_Request $request ) {
		$app = sanitize_key( $request['app'] );
		if ( ! isset( Social_Apps::apps()[ $app ] ) ) {
			return new WP_Error( 'qcld_wpbot_social_app', __( 'Unknown app.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return array_merge( Social_Apps::public_view( $app ), array( 'redirect_uri' => Social_Auth::redirect_uri() ) );
	}

	/**
	 * Save an app ID and secret (a masked secret keeps the stored one).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_app( WP_REST_Request $request ) {
		$app = sanitize_key( $request['app'] );
		if ( ! isset( Social_Apps::apps()[ $app ] ) ) {
			return new WP_Error( 'qcld_wpbot_social_app', __( 'Unknown app.', 'chatbot' ), array( 'status' => 404 ) );
		}
		Social_Apps::update(
			$app,
			array(
				'client_id'     => (string) $request->get_param( 'client_id' ),
				'client_secret' => (string) $request->get_param( 'client_secret' ),
			)
		);
		return self::get_app( $request );
	}

	/**
	 * Consent URL for Meta or Threads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function connect( WP_REST_Request $request ) {
		$app     = sanitize_key( $request['app'] );
		$choices = array_map( 'sanitize_key', (array) $request->get_param( 'scopes' ) );
		if ( 'meta' === $app ) {
			Settings::update( array( 'scopes' => $choices ) );
		}
		$return = admin_url( 'admin.php?page=wpbot-social&view=' . ( 'meta' === $app ? 'pages' : 'accounts' ) );
		$url    = Connector::start( $app, Settings::get( 'scopes' ), $return );
		return is_wp_error( $url ) ? $url : array( 'url' => $url );
	}

	/**
	 * Pages for the pending Facebook login.
	 *
	 * @return array|WP_Error
	 */
	public static function meta_pages() {
		return Connector::pages();
	}

	/**
	 * Connect chosen Pages.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_meta_pages( WP_REST_Request $request ) {
		$selection = array();
		foreach ( (array) $request->get_param( 'pages' ) as $choice ) {
			if ( is_array( $choice ) ) {
				$selection[] = array(
					'page_id'   => isset( $choice['page_id'] ) ? preg_replace( '/[^0-9]/', '', (string) $choice['page_id'] ) : '',
					'instagram' => ! empty( $choice['instagram'] ),
					'brand_id'  => isset( $choice['brand_id'] ) ? Connector::brand( $choice['brand_id'] ) : 0,
				);
			}
		}
		return Connector::save_pages( $selection );
	}

	/**
	 * Add a Telegram channel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function add_telegram( WP_REST_Request $request ) {
		$account = Connector::add_telegram( sanitize_text_field( (string) $request->get_param( 'channel' ) ) );
		return is_wp_error( $account ) ? $account : self::account_view( $account );
	}

	/**
	 * Which Telegram bot Social uses (masked; never the token).
	 *
	 * @return array
	 */
	public static function telegram_bot() {
		return Connector::telegram_bot_view();
	}

	/**
	 * Save Social's own Telegram bot token (write-only: the answer holds
	 * only the mask and the bot's @username).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_telegram_bot( WP_REST_Request $request ) {
		return Connector::save_telegram_bot( (string) $request->get_param( 'token' ) );
	}

	/**
	 * Remove Social's own Telegram bot token.
	 *
	 * @return array
	 */
	public static function forget_telegram_bot() {
		return Connector::forget_telegram_bot();
	}

	/**
	 * The Google connection card's state (WPBot Core's Google connection).
	 *
	 * @param WP_REST_Request $request Request (purpose: business or youtube).
	 * @return array|WP_Error
	 */
	public static function google_connection( WP_REST_Request $request ) {
		if ( ! Google_Card::available() ) {
			return new WP_Error( 'qcld_wpbot_social_google', __( 'Update WPBot to set up the Google connection here.', 'chatbot' ), array( 'status' => 501 ) );
		}
		return Google_Card::state( (string) $request->get_param( 'purpose' ) );
	}

	/**
	 * Save the Google OAuth client (ID and secret).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_google_client( WP_REST_Request $request ) {
		if ( ! Google_Card::available() ) {
			return new WP_Error( 'qcld_wpbot_social_google', __( 'Update WPBot to set up the Google connection here.', 'chatbot' ), array( 'status' => 501 ) );
		}
		$id     = trim( sanitize_text_field( (string) $request->get_param( 'client_id' ) ) );
		$secret = trim( (string) $request->get_param( 'client_secret' ) );
		if ( '' === $id ) {
			return new WP_Error( 'qcld_wpbot_social_google', __( 'Enter the Google client ID.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$changes = array( 'client_id' => $id );
		if ( '' !== $secret ) {
			$changes['client_secret'] = $secret;
		}
		\QuantumCloud\WPBotCore\Connections\Google::update( $changes );
		return Google_Card::state( (string) $request->get_param( 'purpose' ) );
	}

	/**
	 * Google's consent address; Google sends the owner back to the page they came from.
	 *
	 * @param WP_REST_Request $request Request (return_url).
	 * @return array|WP_Error
	 */
	public static function connect_google( WP_REST_Request $request ) {
		if ( ! Google_Card::available() ) {
			return new WP_Error( 'qcld_wpbot_social_google', __( 'Update WPBot to set up the Google connection here.', 'chatbot' ), array( 'status' => 501 ) );
		}
		$back = esc_url_raw( (string) $request->get_param( 'return_url' ) );
		$url  = \QuantumCloud\WPBotCore\Connections\Google::auth_url( '' !== $back ? $back : Admin_Controller::url( 'accounts' ) );
		return is_wp_error( $url ) ? $url : array( 'url' => $url );
	}

	/**
	 * Disconnect Google (for every WPBot module).
	 *
	 * @return array|WP_Error
	 */
	public static function disconnect_google() {
		if ( ! Google_Card::available() ) {
			return new WP_Error( 'qcld_wpbot_social_google', __( 'Update WPBot to set up the Google connection here.', 'chatbot' ), array( 'status' => 501 ) );
		}
		\QuantumCloud\WPBotCore\Connections\Google::disconnect();
		return Google_Card::state();
	}

	/**
	 * Turn Social's Google Business Profile permission on or off.
	 *
	 * @param WP_REST_Request $request Request (on: bool).
	 * @return array
	 */
	public static function google_business_profile( WP_REST_Request $request ) {
		Google_Card::set_business_profile( rest_sanitize_boolean( $request->get_param( 'on' ) ) );
		return array( 'on' => (bool) get_option( Google_Card::GBP_OPTION, false ) );
	}

	/**
	 * Google Business Profile locations.
	 *
	 * @return array|WP_Error
	 */
	public static function google_locations() {
		return Connector::google_locations();
	}

	/**
	 * Connect a Google location.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function add_google_location( WP_REST_Request $request ) {
		$account = Connector::add_google_location( (string) $request->get_param( 'name' ), sanitize_text_field( (string) $request->get_param( 'title' ) ), (string) $request->get_param( 'account' ) );
		return is_wp_error( $account ) ? $account : self::account_view( $account );
	}

	/**
	 * Settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return Settings::all();
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save_settings( WP_REST_Request $request ) {
		return Settings::update( (array) $request->get_json_params() );
	}

	/**
	 * Check tokens now.
	 *
	 * @return array
	 */
	public static function check_tokens() {
		return Health::run( true );
	}

	/**
	 * Latest log entries.
	 *
	 * @return array
	 */
	public static function log() {
		return Log::latest( 50 );
	}

	/**
	 * Export settings (no accounts, no secrets).
	 *
	 * @return array
	 */
	public static function export() {
		return Settings::export();
	}

	/**
	 * Import settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		return Settings::import( (array) $request->get_json_params() ) ? array( 'imported' => true ) : new WP_Error( 'qcld_wpbot_social_import', __( 'This is not a WPBot Social settings file.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * Disconnect every account.
	 *
	 * @return array
	 */
	public static function disconnect_all() {
		return array( 'disconnected' => Connector::disconnect_all() );
	}
}
