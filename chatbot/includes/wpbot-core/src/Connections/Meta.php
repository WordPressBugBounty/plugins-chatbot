<?php
/**
 * Meta connection (Facebook Login for Business): long-lived user tokens,
 * Page tokens, linked Instagram professional accounts, token checks and a
 * read-merge-write helper for Page webhook subscriptions.
 *
 * Shared with the chatbot channel add-ons (Social build-brief A3): Core
 * never overwrites a Page's subscribed fields and never revokes the app
 * grant (DELETE /me/permissions) here.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Meta Graph API connection helpers.
 */
final class Meta {

	/**
	 * Pinned Graph API version (checked 8 Nov 2026).
	 */
	const VERSION = 'v26.0';

	/**
	 * Graph host.
	 */
	const HOST = 'https://graph.facebook.com';

	/**
	 * Permissions to ask for at login: the union of what every consumer
	 * adds through qcld_wpbot_core/meta_permissions.
	 *
	 * @return string[]
	 */
	public static function permissions() {
		$list = (array) apply_filters( 'qcld_wpbot_core/meta_permissions', array() );
		$list = array_filter(
			array_map( 'strval', $list ),
			static function ( $permission ) {
				return (bool) preg_match( '/^[a-z_]{3,64}$/', $permission );
			}
		);
		return array_values( array_unique( $list ) );
	}

	/**
	 * The appsecret_proof for a token ('' when no app secret is saved).
	 *
	 * @param string      $token      Access token.
	 * @param string|null $app_secret Secret of the app that owns the token; null = the saved Meta app.
	 * @return string
	 */
	public static function proof( $token, $app_secret = null ) {
		$secret = null === $app_secret ? Social_Apps::client_secret( 'meta' ) : (string) $app_secret;
		return '' === $secret ? '' : hash_hmac( 'sha256', (string) $token, $secret );
	}

	/**
	 * Call the Graph API with the token in the Authorization header.
	 *
	 * @param string      $method GET or POST.
	 * @param string      $path   Path after the version, for example "/me/accounts".
	 * @param string      $token  Access token.
	 * @param array       $fields     Query (GET) or form fields (POST). Never tokens.
	 * @param string|null $app_secret Secret of the app that owns the token; null = the saved Meta app.
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $token, array $fields = array(), $app_secret = null ) {
		$query = array();
		$proof = self::proof( $token, $app_secret );
		if ( '' !== $proof ) {
			$query['appsecret_proof'] = $proof;
		}
		if ( 'GET' === $method ) {
			$query  = array_merge( $fields, $query );
			$fields = array();
		}
		$url = self::HOST . '/' . self::VERSION . $path . ( array() === $query ? '' : '?' . http_build_query( $query, '', '&' ) );
		return Social_Http::send( 'GET' === $method ? 'GET' : 'POST', $url, array( 'Authorization' => 'Bearer ' . $token ), $fields );
	}

	/**
	 * Swap a short-lived user token for a long-lived one (about 60 days).
	 *
	 * @param array $tokens Token array with access_token.
	 * @return array|WP_Error
	 */
	public static function extend( array $tokens ) {
		if ( empty( $tokens['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_meta_no_token', __( 'Meta did not return an access token.', 'chatbot' ) );
		}
		$data = Social_Http::send(
			'POST',
			self::HOST . '/' . self::VERSION . '/oauth/access_token',
			array( 'Accept' => 'application/json' ),
			array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => Social_Apps::client_id( 'meta' ),
				'client_secret'     => Social_Apps::client_secret( 'meta' ),
				'fb_exchange_token' => (string) $tokens['access_token'],
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_meta_no_token', __( 'Meta did not return a long-lived access token.', 'chatbot' ) );
		}
		return Own_App_Auth::normalise( $data );
	}

	/**
	 * Pages the user manages, with Page tokens and linked Instagram accounts.
	 *
	 * @param string $user_token Long-lived user token.
	 * @return array<int,array{id:string, name:string, token:string, tasks:string[], instagram:array{id:string, username:string}|null}>|WP_Error
	 */
	public static function pages( $user_token ) {
		$pages = array();
		$after = '';
		for ( $round = 0; $round < 10; $round++ ) {
			$fields = array(
				'fields' => 'id,name,access_token,tasks,instagram_business_account{id,username}',
				'limit'  => 100,
			);
			if ( '' !== $after ) {
				$fields['after'] = $after;
			}
			$data = self::request( 'GET', '/me/accounts', $user_token, $fields );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $page ) {
				if ( empty( $page['id'] ) || empty( $page['access_token'] ) ) {
					continue;
				}
				$ig      = isset( $page['instagram_business_account']['id'] ) ? $page['instagram_business_account'] : null;
				$pages[] = array(
					'id'        => (string) $page['id'],
					'name'      => isset( $page['name'] ) ? (string) $page['name'] : '',
					'token'     => (string) $page['access_token'],
					'tasks'     => isset( $page['tasks'] ) ? array_map( 'strval', (array) $page['tasks'] ) : array(),
					'instagram' => $ig ? array(
						'id'       => (string) $ig['id'],
						'username' => isset( $ig['username'] ) ? (string) $ig['username'] : '',
					) : null,
				);
			}
			$after = isset( $data['paging']['cursors']['after'], $data['paging']['next'] ) ? (string) $data['paging']['cursors']['after'] : '';
			if ( '' === $after ) {
				break;
			}
		}
		return $pages;
	}

	/**
	 * Check a token: ok, expired (reconnect needed) or error (try later).
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function check( $token ) {
		$data = self::request( 'GET', '/me', $token, array( 'fields' => 'id' ) );
		if ( ! is_wp_error( $data ) ) {
			return 'ok';
		}
		$detail = $data->get_error_data();
		$code   = is_array( $detail ) && isset( $detail['code'] ) ? (int) $detail['code'] : 0;
		return in_array( $code, array( 190, 102 ), true ) ? 'expired' : 'error';
	}

	/**
	 * The id of the app a token belongs to (GET /app).
	 *
	 * @param string      $token      Access token.
	 * @param string|null $app_secret Secret of the app that owns the token; null = the saved Meta app.
	 * @return string|WP_Error
	 */
	public static function token_app_id( $token, $app_secret = null ) {
		$data = self::request( 'GET', '/app', $token, array( 'fields' => 'id' ), $app_secret );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_meta_no_app', __( 'Meta did not say which app this token belongs to.', 'chatbot' ) );
		}
		return (string) $data['id'];
	}

	/**
	 * Fields the token's own app is subscribed to on a Page.
	 *
	 * Meta applies a subscribed_apps write to the app that owns the token,
	 * so the match is on that app (GET /app), not on the app saved in
	 * Social_Apps: the chatbot's Messenger token may come from another app.
	 *
	 * @param string      $page_id    Page id.
	 * @param string      $page_token Page token.
	 * @param string|null $app_secret Secret of the app that owns the token; null = the saved Meta app.
	 * @return string[]|WP_Error Empty when that app is not subscribed yet.
	 */
	public static function subscribed_fields( $page_id, $page_token, $app_secret = null ) {
		$app_id = self::token_app_id( $page_token, $app_secret );
		if ( is_wp_error( $app_id ) ) {
			return $app_id;
		}
		$data = self::request( 'GET', '/' . rawurlencode( (string) $page_id ) . '/subscribed_apps', $page_token, array(), $app_secret );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $app ) {
			if ( isset( $app['id'] ) && (string) $app['id'] === $app_id ) {
				return isset( $app['subscribed_fields'] ) ? array_values( array_map( 'strval', (array) $app['subscribed_fields'] ) ) : array();
			}
		}
		return array();
	}

	/**
	 * Add fields to a Page subscription without dropping existing ones.
	 *
	 * If the token's app cannot be identified, nothing is written.
	 *
	 * @param string      $page_id    Page id.
	 * @param string      $page_token Page token.
	 * @param string[]    $fields     Fields to add.
	 * @param string|null $app_secret Secret of the app that owns the token; null = the saved Meta app.
	 * @return string[]|WP_Error The merged field list.
	 */
	public static function merge_subscribed_fields( $page_id, $page_token, array $fields, $app_secret = null ) {
		$current = self::subscribed_fields( $page_id, $page_token, $app_secret );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$wanted = array_filter(
			array_map( 'strval', $fields ),
			static function ( $field ) {
				return (bool) preg_match( '/^[a-z_]{2,64}$/', $field );
			}
		);
		$merged = array_values( array_unique( array_merge( $current, $wanted ) ) );
		if ( array() === array_diff( $merged, $current ) ) {
			return $current;
		}
		$result = self::request( 'POST', '/' . rawurlencode( (string) $page_id ) . '/subscribed_apps', $page_token, array( 'subscribed_fields' => implode( ',', $merged ) ), $app_secret );
		return is_wp_error( $result ) ? $result : $merged;
	}

	/**
	 * Whether a chatbot channel uses this Page's connection (for example
	 * the Messenger add-on answers its messages). Modules ask before they
	 * disconnect a Page so they can warn the owner; Social never removes
	 * the Meta grant or a Page subscription either way.
	 *
	 * @param string $page_id Page id.
	 * @return bool
	 */
	public static function connection_in_use( $page_id ) {
		/**
		 * Whether a chatbot channel uses this Page's Meta connection.
		 *
		 * @param bool   $in_use  Default false.
		 * @param string $page_id Page id.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/meta_connection_in_use', false, (string) $page_id );
	}

	/**
	 * The chatbot channels' Meta app id when it differs from the one saved
	 * in Social_Apps ('' when they match or either is empty).
	 *
	 * In v1 Social and the chatbot channels must use the same Meta app.
	 * When they differ, the owner is warned: webhooks keep using the
	 * channel's own app secret to check signatures.
	 *
	 * @return string
	 */
	public static function channel_app_mismatch() {
		$social = Social_Apps::client_id( 'meta' );
		/**
		 * The Meta app id the chatbot channels use ('' when none).
		 *
		 * @param string $app_id Default: the Messenger add-on's wpfb_app_id option.
		 */
		$channel = trim( (string) apply_filters( 'qcld_wpbot_core/channel_meta_app_id', (string) get_option( 'wpfb_app_id', '' ) ) );
		if ( '' === $social || '' === $channel || $social === $channel ) {
			return '';
		}
		return $channel;
	}
}
