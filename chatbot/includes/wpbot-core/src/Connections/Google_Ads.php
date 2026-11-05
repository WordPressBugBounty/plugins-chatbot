<?php
/**
 * Google Ads, read only (SEO & AI Search S13; reusable by the Growth
 * Dashboard).
 *
 * The owner's own Google Cloud app (the shared Google connection) and the
 * Google Ads customer ID. Since Google retired developer tokens on
 * 9 September 2026, API access belongs to the Google Cloud project: enabling
 * the Google Ads API gives Test access; Explorer access (about 2,880
 * operations a day, enough for a daily sync of reports) and Basic access
 * (needed for Keyword Planner) are applied for on the project's Google Ads
 * API Overview page. No developer token is stored or sent (Google ignores it
 * now and will reject it in a future API version). Google Ads has no read-only
 * OAuth scope, so the "adwords" scope is requested only after the owner
 * turns this connection on, and read-only is enforced here: the only call
 * is the reporting method googleAds:search with a SELECT query on an
 * allow-listed report. Nothing in Google Ads is ever changed.
 *
 * A developer token saved before the change is deleted at the next save.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google Ads reporting connection.
 */
final class Google_Ads {

	const SCOPE = 'https://www.googleapis.com/auth/adwords';

	/**
	 * Default Google Ads API version: v26 (checked 1 November 2026 against
	 * Google's sunset table; v23 is the oldest still served, until February 2027).
	 */
	const API_VERSION = 'v26';

	/**
	 * Reports that may be read (FROM clause). geo_target_constant and
	 * language_constant are Google's own lookup lists (Keyword Planner places).
	 */
	const REPORTS = array( 'search_term_view', 'keyword_view', 'customer', 'campaign', 'geo_target_constant', 'language_constant' );

	/**
	 * Keyword Planner methods that may be called (round 2, R5). They only
	 * read planning data; nothing in the Google Ads account changes.
	 */
	const PLANNER = array( 'generateKeywordIdeas', 'generateKeywordHistoricalMetrics' );

	/**
	 * Key inside the connections option.
	 */
	const KEY = 'google_ads';

	/**
	 * Hooks: the Ads scope joins the Google connection only once turned on;
	 * the daily quota counter.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/quota_limits', array( __CLASS__, 'quota' ) );
	}

	/**
	 * Explorer access: 2,880 operations a day (kept a little below).
	 *
	 * @param array $limits Limits.
	 * @return array
	 */
	public static function quota( $limits ) {
		$limits['google_ads'] = array(
			'day'    => 2800,
			'minute' => 30,
		);
		return $limits;
	}

	/**
	 * Whether the owner turned the connection on and saved the customer ID.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$set = self::load();
		return ! empty( $set['enabled'] ) && '' !== $set['customer_id'];
	}

	/**
	 * Whether Google granted the Ads scope (the owner reconnected Google after turning this on).
	 *
	 * @return bool
	 */
	public static function authorized() {
		return Google::connected() && Google::has_scope( self::SCOPE );
	}

	/**
	 * Status for screens: off, needs_google, needs_scope, connected, failing.
	 *
	 * @return array status, message, customer_id, login_customer_id, error.
	 */
	public static function status() {
		$set  = self::load();
		$view = array(
			'enabled'           => ! empty( $set['enabled'] ),
			'customer_id'       => $set['customer_id'],
			'login_customer_id' => $set['login_customer_id'],
			'error'             => (string) $set['error'],
		);
		if ( ! self::enabled() ) {
			$view['status']  = 'off';
			$view['message'] = __( 'Google Ads API is not set up.', 'chatbot' );
		} elseif ( ! Google::connected() ) {
			$view['status']  = 'needs_google';
			$view['message'] = __( 'Connect Google first (Search Console and Analytics use the same connection).', 'chatbot' );
		} elseif ( ! self::authorized() ) {
			$view['status']  = 'needs_scope';
			$view['message'] = __( 'Reconnect Google once so it can read your Google Ads reports.', 'chatbot' );
		} elseif ( '' !== $view['error'] ) {
			$view['status']  = 'failing';
			$view['message'] = $view['error'];
		} else {
			$view['status']  = 'connected';
			$view['message'] = __( 'Connected (read only).', 'chatbot' );
		}
		return $view;
	}

	/**
	 * Save the owner's settings.
	 *
	 * @param array $changes enabled, customer_id, login_customer_id. A developer_token is ignored (retired by Google).
	 * @return array|WP_Error Status.
	 */
	public static function update( array $changes ) {
		$set = self::load();
		if ( isset( $changes['enabled'] ) ) {
			$set['enabled'] = (bool) $changes['enabled'];
		}
		foreach ( array( 'customer_id', 'login_customer_id' ) as $field ) {
			if ( isset( $changes[ $field ] ) ) {
				$set[ $field ] = self::account( $changes[ $field ] );
			}
		}
		// Developer tokens were retired on 9 September 2026: a stored one is deleted.
		$set['token'] = '';
		$set['error'] = '';
		self::save( $set );
		return self::status();
	}

	/**
	 * Run a reporting query (read only).
	 *
	 * @param string $gaql SELECT query on an allow-listed report.
	 * @return array|WP_Error rows (decoded results).
	 */
	public static function search( $gaql ) {
		$gaql = trim( (string) $gaql );
		if ( ! preg_match( '/^SELECT\s.+\sFROM\s+([a-z_]+)\b/is', $gaql, $from ) || ! in_array( strtolower( $from[1] ), self::REPORTS, true ) ) {
			return new WP_Error( 'qcld_wpbot_core_ads_query', __( 'Only reading Google Ads reports is allowed.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$rows     = array();
		$page     = '';
		$requests = 0;
		do {
			$body = array( 'query' => $gaql );
			if ( '' !== $page ) {
				$body['pageToken'] = $page;
			}
			$data = self::call( '/googleAds:search', $body );
			++$requests;
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $result ) {
				$rows[] = $result;
			}
			$page = isset( $data['nextPageToken'] ) ? (string) $data['nextPageToken'] : '';
		} while ( '' !== $page && $requests < 10 );
		return $rows;
	}

	/**
	 * Call a Keyword Planner method (round 2, R5): read-only planning data.
	 * Google allows it only for Google Cloud projects with Basic or Standard access.
	 *
	 * @param string $method generateKeywordIdeas | generateKeywordHistoricalMetrics.
	 * @param array  $body   Request body.
	 * @return array|WP_Error Decoded response.
	 */
	public static function planner( $method, array $body ) {
		if ( ! in_array( $method, self::PLANNER, true ) ) {
			return new WP_Error( 'qcld_wpbot_core_ads_query', __( 'Only reading Google Ads reports and Keyword Planner data is allowed.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return self::call( ':' . $method, $body );
	}

	/**
	 * One request to the customer (quota counted, errors explained and remembered).
	 *
	 * @param string $suffix Path after customers/{id}: "/googleAds:search" or ":generateKeywordIdeas".
	 * @param array  $body   Body.
	 * @return array|WP_Error Decoded body.
	 */
	private static function call( $suffix, array $body ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'qcld_wpbot_core_ads_off', __( 'Google Ads API is not set up.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! self::authorized() ) {
			return new WP_Error( 'qcld_wpbot_core_ads_scope', __( 'Reconnect Google once so it can read your Google Ads reports.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$quota = Quota::take( 'google_ads' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$token = Google::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$set = self::load();
		// No developer-token header: access comes from the Google Cloud project of the OAuth app.
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
		if ( '' !== $set['login_customer_id'] ) {
			$headers['login-customer-id'] = $set['login_customer_id'];
		}
		/**
		 * Google Ads API version used for reports.
		 *
		 * @param string $version Version, for example v26 (the current release; Google retires each version about a year after release).
		 */
		$version  = (string) apply_filters( 'qcld_wpbot_core/google_ads_api_version', self::API_VERSION );
		$base     = (string) apply_filters( 'qcld_wpbot_core/google_endpoint', 'https://googleads.googleapis.com', 'googleads' );
		$response = wp_remote_post(
			untrailingslashit( $base ) . '/' . rawurlencode( $version ) . '/customers/' . $set['customer_id'] . $suffix,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_core_ads_network', __( 'Google Ads could not be reached. It is tried again at the next sync.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message      = self::explain( $code, is_array( $data ) ? $data : array(), $suffix );
			$set          = self::load();
			$set['error'] = $message;
			self::save( $set );
			return new WP_Error( 'qcld_wpbot_core_ads_' . $code, $message, array( 'status' => $code ) );
		}
		if ( '' !== (string) self::load()['error'] ) {
			$set          = self::load();
			$set['error'] = '';
			self::save( $set );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Owner-readable error with the fix.
	 *
	 * @param int    $code   HTTP status.
	 * @param array  $data   Body.
	 * @param string $suffix Method called.
	 * @return string
	 */
	private static function explain( $code, array $data, $suffix = '' ) {
		$json  = (string) wp_json_encode( $data );
		$where = __( 'the Google Ads API Overview page of your Google Cloud project (console.cloud.google.com, APIs & Services, Google Ads API)', 'chatbot' );
		// A project with only Test access calls a real account (v25 and later; ACTION_NOT_PERMITTED before).
		if ( false !== stripos( $json, 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION' ) || false !== stripos( $json, 'ACTION_NOT_PERMITTED' ) ) {
			/* translators: %s: where to apply. */
			return sprintf( __( 'Your Google Cloud project only has Test access, which cannot read real Google Ads accounts. Apply for Explorer access on %s (usually approved automatically within minutes), then sync again.', 'chatbot' ), $where );
		}
		// Keyword Planner is not open to Test or Explorer access (Google's access-levels page).
		if ( 0 === strpos( (string) $suffix, ':generateKeyword' ) && ( 403 === $code || false !== stripos( $json, 'PERMISSION_DENIED' ) || false !== stripos( $json, 'access level' ) ) ) {
			/* translators: %s: where to apply. */
			return sprintf( __( 'Google refused Keyword Planner data: your Google Cloud project needs Basic or Standard access (Explorer and Test access cannot use Keyword Planner). Apply on %s; Basic access needs brand verification of your Google Cloud project first.', 'chatbot' ), $where );
		}
		if ( 401 === $code || 403 === $code ) {
			return __( 'Google Ads refused access. Check the customer ID (and the manager account ID if you use one), that the Google user you connected can see this Google Ads account, and that the Google Ads API is enabled in your Google Cloud project.', 'chatbot' );
		}
		if ( 429 === $code ) {
			return __( 'The Google Ads daily limit for your Google Cloud project is used up. The sync continues tomorrow.', 'chatbot' );
		}
		return __( 'Google Ads could not return the report. It is tried again at the next sync.', 'chatbot' );
	}


	/**
	 * Account id: digits only (123-456-7890 → 1234567890).
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	private static function account( $value ) {
		return substr( (string) preg_replace( '/\D/', '', (string) $value ), 0, 12 );
	}

	/**
	 * Settings.
	 *
	 * @return array enabled, token (encrypted), customer_id, login_customer_id, error.
	 */
	private static function load() {
		$all = get_option( Stock_Keys::OPTION, array() );
		$set = is_array( $all ) && isset( $all[ self::KEY ] ) && is_array( $all[ self::KEY ] ) ? $all[ self::KEY ] : array();
		return array_merge(
			array(
				'enabled'           => false,
				'token'             => '',
				'customer_id'       => '',
				'login_customer_id' => '',
				'error'             => '',
			),
			$set
		);
	}

	/**
	 * Save settings (never autoloaded).
	 *
	 * @param array $set Settings.
	 * @return void
	 */
	private static function save( array $set ) {
		$all              = get_option( Stock_Keys::OPTION, array() );
		$all              = is_array( $all ) ? $all : array();
		$all[ self::KEY ] = $set;
		update_option( Stock_Keys::OPTION, $all, false );
	}
}
