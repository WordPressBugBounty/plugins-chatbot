<?php
/**
 * Shared Meta Graph API client for the Facebook and Instagram adapters.
 *
 * Tokens travel in the Authorization header, never in the URL (QA #11).
 * Every call carries appsecret_proof when the app secret is known. Errors
 * are sorted into definite and ambiguous outcomes so the pipeline never
 * double-posts.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Response;
use QuantumCloud\Social\Http\Transport;
use QuantumCloud\Social\Http\Wp_Transport;
use QuantumCloud\Social\Support\Redact;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal Graph API client.
 */
class Meta_Graph {

	/**
	 * Pinned Graph API version (checked 8 Nov 2026, docs/social/api-findings.md).
	 */
	const VERSION = 'v26.0';

	/**
	 * Graph host for Facebook Login tokens.
	 */
	const HOST = 'https://graph.facebook.com';

	/**
	 * Error codes that mean the token is expired or revoked.
	 *
	 * @var int[]
	 */
	const AUTH_CODES = array( 190, 102 );

	/**
	 * Error codes for rate limits (app, user, Page and custom limits).
	 *
	 * @var int[]
	 */
	const RATE_CODES = array( 4, 17, 32, 613, 80001, 80002, 80006 );

	/**
	 * Error codes for missing permission.
	 *
	 * @var int[]
	 */
	const PERMISSION_CODES = array( 10, 200, 299, 3 );

	/**
	 * Transport.
	 *
	 * @var Transport
	 */
	private $transport;

	/**
	 * Build.
	 *
	 * @param Transport|null $transport Transport; the live WordPress one by default.
	 */
	public function __construct( ?Transport $transport = null ) {
		$this->transport = $transport ? $transport : new Wp_Transport();
	}

	/**
	 * Call the Graph API.
	 *
	 * @param string $method GET or POST.
	 * @param string $path   Path after the version, for example "/123/feed".
	 * @param Target $target Target with the token and app secret.
	 * @param array  $fields Form fields (POST) or query fields (GET). Never tokens.
	 * @return Response
	 */
	public function call( $method, $path, Target $target, array $fields = array() ) {
		$headers = array( 'Authorization' => 'Bearer ' . $target->token() );
		$query   = array();
		if ( '' !== $target->app_secret() ) {
			$query['appsecret_proof'] = hash_hmac( 'sha256', $target->token(), $target->app_secret() );
		}
		if ( 'GET' === $method ) {
			$query  = array_merge( $fields, $query );
			$fields = array();
		}
		$url = self::HOST . '/' . self::VERSION . $path;
		if ( array() !== $query ) {
			$url .= '?' . http_build_query( $query, '', '&' );
		}
		return $this->transport->request( $method, $url, $headers, $fields );
	}

	/**
	 * Turn a failed Graph response into a publish outcome.
	 *
	 * @param Response $response Response that was not ok.
	 * @param bool     $creates  Whether the request creates content (a lost
	 *                           answer then means "unknown", not "failed").
	 * @return Publish_Result
	 */
	public static function classify( Response $response, $creates ) {
		if ( $response->no_response() ) {
			$code = 'timeout' === $response->error ? 'timeout' : 'connection';
			if ( $creates ) {
				return Publish_Result::unknown( $code, __( 'The network did not answer in time. WPBot will check whether the post went out before trying again.', 'chatbot' ) );
			}
			return Publish_Result::failed( $code, __( 'The network did not answer in time. WPBot will try again shortly.', 'chatbot' ), true );
		}

		$error   = isset( $response->body['error'] ) && is_array( $response->body['error'] ) ? $response->body['error'] : array();
		$code    = isset( $error['code'] ) ? (int) $error['code'] : 0;
		$detail  = isset( $error['message'] ) ? Redact::secrets( (string) $error['message'] ) : '';
		$is_auth = in_array( $code, self::AUTH_CODES, true );

		if ( $is_auth ) {
			$result                = Publish_Result::failed( 'auth_expired', __( 'The connection to this account has expired or was removed. Reconnect the account in Social › Accounts, then retry.', 'chatbot' ) );
			$result->pause_account = true;
			return $result;
		}
		$subcode = isset( $error['error_subcode'] ) ? (int) $error['error_subcode'] : 0;
		if ( 9007 === $code || 2207027 === $subcode ) {
			// Instagram: the media container is not ready to publish yet.
			return Publish_Result::failed( 'media_not_ready', __( 'Instagram is still processing the image.', 'chatbot' ), true );
		}
		if ( 9004 === $code || 2207052 === $subcode ) {
			// Instagram: the image URL could not be fetched.
			return Publish_Result::failed( 'media_fetch', __( 'Instagram could not download the image. It must be a public JPEG link that anyone can open. Check the image and retry.', 'chatbot' ) );
		}
		if ( 2207042 === $subcode || ( 9 === $code && 0 === $subcode ) ) {
			// Instagram: the account reached its publishing limit for 24 hours.
			$result              = Publish_Result::failed( 'daily_limit', __( 'This Instagram account reached its posting limit for the last 24 hours. WPBot will try again later.', 'chatbot' ), true );
			$result->retry_after = 21600; // 6 hours.
			return $result;
		}
		if ( in_array( $code, self::RATE_CODES, true ) ) {
			return Publish_Result::failed( 'rate_limited', __( 'The network is limiting how often this account can post. WPBot will try again later.', 'chatbot' ), true );
		}
		if ( in_array( $code, self::PERMISSION_CODES, true ) ) {
			return Publish_Result::failed( 'permission', __( 'WPBot is missing a permission for this account. Reconnect it and allow every permission asked for.', 'chatbot' ) );
		}
		if ( 100 === $code ) {
			/* translators: %s: the network's own error message. */
			return Publish_Result::failed( 'bad_request', sprintf( __( 'The network refused the post: %s', 'chatbot' ), $detail ) );
		}
		if ( $response->code >= 500 || ! empty( $error['is_transient'] ) ) {
			if ( $creates ) {
				return Publish_Result::unknown( 'server_error', __( 'The network had a temporary problem. WPBot will check whether the post went out before trying again.', 'chatbot' ) );
			}
			return Publish_Result::failed( 'server_error', __( 'The network had a temporary problem. WPBot will try again shortly.', 'chatbot' ), true );
		}
		/* translators: %s: the network's own error message. */
		return Publish_Result::failed( 'error', sprintf( __( 'The network refused the post: %s', 'chatbot' ), '' !== $detail ? $detail : (string) $response->code ) );
	}

	/**
	 * Map a failed lookup (reconcile) to a Reconcile_Result.
	 *
	 * Rate limits, timeouts and temporary errors wait and look again; an
	 * expired or revoked token pauses the account and asks the owner; any
	 * other refusal asks the owner to check.
	 *
	 * @param Response $response Failed response.
	 * @param string   $message  Owner note when WPBot cannot check.
	 * @return Reconcile_Result
	 */
	public static function reconcile_error( Response $response, $message ) {
		$result = self::classify( $response, false );
		if ( $result->pause_account ) {
			$check                = new Reconcile_Result( Reconcile_Result::CANNOT_CHECK, '', $result->message );
			$check->pause_account = true;
			return $check;
		}
		if ( $result->retryable ) {
			return Reconcile_Result::wait( 'rate_limited' === $result->code ? 900 : 300, array(), $result->message );
		}
		return new Reconcile_Result( Reconcile_Result::CANNOT_CHECK, '', $message );
	}

	/**
	 * Read a container's status (Instagram status_code, Threads status).
	 *
	 * @param Response $response Response of GET /{container}.
	 * @param string   $field    status_code | status.
	 * @return array{0:string,1:Publish_Result|null} Status ('' on error) and the classified error.
	 */
	public static function container_status( Response $response, $field ) {
		if ( $response->ok() && isset( $response->body[ $field ] ) ) {
			return array( (string) $response->body[ $field ], null );
		}
		if ( $response->ok() ) {
			return array( '', Publish_Result::failed( 'server_error', __( 'The network returned an unreadable status. WPBot will try again shortly.', 'chatbot' ), true ) );
		}
		return array( '', self::classify( $response, false ) );
	}
}
