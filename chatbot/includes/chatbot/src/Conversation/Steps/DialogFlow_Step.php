<?php
/**
 * DialogFlow v2 (ES) and CX, called from the server with the owner's service
 * account. v1 (browser token) was shut down by Google and is removed.
 *
 * The access token is made from the service account key with a signed JWT
 * (RS256, OpenSSL) and cached for 50 minutes; the key never leaves the server
 * and is never logged. CX uses the first agent of the project, chosen on the
 * server (the legacy widget sent the agent name from the browser).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Settings\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * DialogFlow step.
 */
final class DialogFlow_Step implements Step {

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'dialogflow';
	}

	/**
	 * On when DialogFlow is enabled with a project and a key.
	 *
	 * @return bool
	 */
	public function enabled() {
		if ( ! Options::on( 'enable_wp_chatbot_dailogflow' ) || 'v1' === (string) get_option( 'wp_chatbot_df_api', '' ) ) {
			return false;
		}
		$config = self::config( '' );
		return '' !== $config['project'] && '' !== $config['key'];
	}

	/**
	 * Ask DialogFlow. The default fallback intent passes the message on.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return Reply|null
	 */
	public function handle( Message $message, Context $context ) {
		if ( '' === $message->text ) {
			return null;
		}
		$result = self::detect( $message->text, $message->session_id, $message->language );
		if ( is_wp_error( $result ) || '' === $result['text'] || $result['fallback'] ) {
			return null;
		}
		return Reply::make(
			'dialogflow',
			array(
				'text' => $result['text'],
				'data' => array( 'intent' => $result['intent'] ),
			)
		);
	}

	/**
	 * Project, key JSON and agent language for a locale (options may be per language).
	 *
	 * @param string $language Locale.
	 * @return array { project, key, language, cx }
	 */
	public static function config( $language ) {
		$locale = '' !== $language ? $language : Options::locale();
		$pick   = static function ( $name ) use ( $locale ) {
			$value = maybe_unserialize( get_option( $name, '' ) );
			if ( is_array( $value ) ) {
				$value = isset( $value[ $locale ] ) ? $value[ $locale ] : reset( $value );
			}
			return is_string( $value ) ? trim( $value ) : '';
		};
		$lang   = $pick( 'qlcd_wp_chatbot_dialogflow_agent_language' );
		return array(
			'project'  => $pick( 'qlcd_wp_chatbot_dialogflow_project_id' ),
			'key'      => $pick( 'qlcd_wp_chatbot_dialogflow_project_key' ),
			'language' => '' !== $lang ? $lang : 'en',
			'cx'       => Options::on( 'qlcd_wp_chatbot_dialogflow_cx' ),
			'locale'   => $locale,
		);
	}

	/**
	 * Detect an intent.
	 *
	 * @param string $text       Question.
	 * @param string $session_id Session.
	 * @param string $language   Locale.
	 * @return array|WP_Error { text, intent, fallback, raw }
	 */
	public static function detect( $text, $session_id, $language ) {
		$config = self::config( $language );
		$token  = self::access_token( $config['key'] );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$session = '' !== $session_id ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $session_id ) : 'wpbot-' . substr( md5( \QuantumCloud\Chatbot\RateLimit\Limiter::ip() ), 0, 12 );
		$project = rawurlencode( $config['project'] );
		if ( $config['cx'] ) {
			$agent = self::cx_agent( $config, $token );
			if ( is_wp_error( $agent ) ) {
				return $agent;
			}
			$url = 'https://dialogflow.googleapis.com/v3/' . $agent . '/sessions/' . $session . ':detectIntent';
		} else {
			$url = 'https://dialogflow.googleapis.com/v2/projects/' . $project . '/agent/sessions/' . $session . ':detectIntent';
		}
		$body = array(
			'queryInput' => array(
				'text'         => array(
					'text'         => $text,
					'languageCode' => $config['language'],
				),
				'languageCode' => $config['language'],
			),
		);
		if ( ! $config['cx'] ) {
			unset( $body['queryInput']['languageCode'] );
		}
		$data = self::request( 'POST', $url, $token, $body );
		return is_wp_error( $data ) ? $data : self::parse( $data, $config['cx'] );
	}

	/**
	 * Read a detectIntent response (pure).
	 *
	 * @param array $data Response.
	 * @param bool  $cx   CX format.
	 * @return array { text, intent, fallback, raw }
	 */
	public static function parse( array $data, $cx ) {
		$result = isset( $data['queryResult'] ) && is_array( $data['queryResult'] ) ? $data['queryResult'] : array();
		$texts  = array();
		if ( $cx ) {
			foreach ( isset( $result['responseMessages'] ) ? (array) $result['responseMessages'] : array() as $item ) {
				foreach ( isset( $item['text']['text'] ) ? (array) $item['text']['text'] : array() as $line ) {
					$texts[] = (string) $line;
				}
			}
			$intent   = isset( $result['match']['intent']['displayName'] ) ? (string) $result['match']['intent']['displayName'] : '';
			$fallback = isset( $result['match']['matchType'] ) && 'NO_MATCH' === $result['match']['matchType'];
		} else {
			if ( ! empty( $result['fulfillmentText'] ) ) {
				$texts[] = (string) $result['fulfillmentText'];
			} else {
				foreach ( isset( $result['fulfillmentMessages'] ) ? (array) $result['fulfillmentMessages'] : array() as $item ) {
					foreach ( isset( $item['text']['text'] ) ? (array) $item['text']['text'] : array() as $line ) {
						$texts[] = (string) $line;
					}
				}
			}
			$intent   = isset( $result['intent']['displayName'] ) ? (string) $result['intent']['displayName'] : '';
			$fallback = ! empty( $result['intent']['isFallback'] ) || 'Default Fallback Intent' === $intent;
		}//end if
		return array(
			'text'     => trim( implode( "\n", array_filter( $texts, 'strlen' ) ) ),
			'intent'   => $intent,
			'fallback' => $fallback,
			'raw'      => $data,
		);
	}

	/**
	 * First CX agent of the project (cached 12 hours).
	 *
	 * @param array  $config Config.
	 * @param string $token  Access token.
	 * @return string|WP_Error Agent resource name.
	 */
	public static function cx_agent( array $config, $token ) {
		$cache = 'qcld_wpbot_chat_df_cx_' . md5( $config['project'] );
		$name  = get_transient( $cache );
		if ( is_string( $name ) && '' !== $name ) {
			return $name;
		}
		$data = self::request( 'GET', 'https://dialogflow.googleapis.com/v3/projects/' . rawurlencode( $config['project'] ) . '/locations/global/agents', $token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$name = isset( $data['agents'][0]['name'] ) ? (string) $data['agents'][0]['name'] : '';
		if ( ! preg_match( '#^projects/[^/]+/locations/[^/]+/agents/[A-Za-z0-9\-]+$#', $name ) ) {
			return new WP_Error( 'qcld_wpbot_chat_df_no_agent', __( 'No DialogFlow CX agent was found in this project.', 'chatbot' ) );
		}
		set_transient( $cache, $name, 12 * HOUR_IN_SECONDS );
		return $name;
	}

	/**
	 * OAuth access token from the service account key (cached 50 minutes).
	 *
	 * @param string $key_json Service account key JSON.
	 * @return string|WP_Error
	 */
	public static function access_token( $key_json ) {
		$key = json_decode( (string) $key_json, true );
		if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) ) {
			return new WP_Error( 'qcld_wpbot_chat_df_key', __( 'The DialogFlow service account key is not valid JSON.', 'chatbot' ) );
		}
		$cache = 'qcld_wpbot_chat_df_token_' . md5( $key['client_email'] . '|' . $key['private_key'] );
		$token = get_transient( $cache );
		if ( is_string( $token ) && '' !== $token ) {
			return $token;
		}
		$jwt = self::jwt( $key, time() );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}
		$response = wp_remote_post(
			! empty( $key['token_uri'] ) && 0 === strpos( $key['token_uri'], 'https://oauth2.googleapis.com/' ) ? $key['token_uri'] : 'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_chat_df_auth', __( 'Google refused the DialogFlow service account key.', 'chatbot' ) );
		}
		set_transient( $cache, (string) $data['access_token'], 50 * MINUTE_IN_SECONDS );
		return (string) $data['access_token'];
	}

	/**
	 * Signed JWT for the token exchange.
	 *
	 * @param array $key Service account key.
	 * @param int   $now Unix time.
	 * @return string|WP_Error
	 */
	public static function jwt( array $key, $now ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'qcld_wpbot_chat_df_openssl', __( 'DialogFlow needs the PHP OpenSSL extension.', 'chatbot' ) );
		}
		$encode    = static function ( $data ) {
			return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		};
		$header    = $encode(
			wp_json_encode(
				array(
					'alg' => 'RS256',
					'typ' => 'JWT',
				)
			)
		);
		$claims    = $encode(
			wp_json_encode(
				array(
					'iss'   => $key['client_email'],
					'scope' => 'https://www.googleapis.com/auth/dialogflow',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$signature = '';
		if ( ! openssl_sign( $header . '.' . $claims, $signature, (string) $key['private_key'], 'sha256WithRSAEncryption' ) ) {
			return new WP_Error( 'qcld_wpbot_chat_df_sign', __( 'The DialogFlow private key could not sign the request.', 'chatbot' ) );
		}
		return $header . '.' . $claims . '.' . $encode( $signature );
	}

	/**
	 * JSON request to DialogFlow.
	 *
	 * @param string     $method GET or POST.
	 * @param string     $url    URL.
	 * @param string     $token  Access token.
	 * @param array|null $body   Body.
	 * @return array|WP_Error
	 */
	private static function request( $method, $url, $token, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'qcld_wpbot_chat_df_http', sanitize_text_field( $message ), array( 'status' => $code ) );
		}
		return $data;
	}

	/**
	 * Check the fulfillment webhook's Basic auth (fixes S2: a missing header used to pass).
	 *
	 * @param string $header Authorization header ('' when missing).
	 * @return bool
	 */
	public static function webhook_authorized( $header ) {
		if ( ! Options::on( 'enable_authentication_webhook' ) ) {
			return true;
		}
		$user = (string) get_option( 'qcld_auth_username', '' );
		$pass = (string) get_option( 'qcld_auth_password', '' );
		if ( '' === $user && '' === $pass ) {
			return false;
		}
		return '' !== $header && hash_equals( 'Basic ' . base64_encode( $user . ':' . $pass ), trim( (string) $header ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
