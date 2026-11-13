<?php
/**
 * "Check live file" (round 2, R4): load the public /llms.txt the way a
 * visitor gets it and compare it with what WPBot generated. When it differs,
 * a second request with a cache-busting parameter tells a stale cache or CDN
 * copy apart from another source answering (a real file, another plugin).
 * Runs on a click, daily, shortly after the file changes and after a caching
 * plugin is turned on or off.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Llms;

defined( 'ABSPATH' ) || exit;

/**
 * Live llms.txt check.
 */
final class Llms_Check {

	const STATE = 'qcld_wpbot_seo_llms_check';
	const EVENT = 'qcld_wpbot_seo_llms_check_soon';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'daily' ) );
		add_action( 'qcld_wpbot_seo/llms_txt_rebuilt', array( __CLASS__, 'soon' ) );
		add_action( 'activated_plugin', array( __CLASS__, 'soon' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'soon' ) );
		add_action( self::EVENT, array( __CLASS__, 'run' ) );
	}

	/**
	 * Check in two minutes (after caches settle).
	 *
	 * @return void
	 */
	public static function soon() {
		if ( Llms_Txt::enabled() && ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, self::EVENT );
		}
	}

	/**
	 * Daily check.
	 *
	 * @return void
	 */
	public static function daily() {
		if ( Llms_Txt::enabled() ) {
			self::run();
		}
	}

	/**
	 * Last result.
	 *
	 * @return array status, message, checked_at.
	 */
	public static function state() {
		$state = get_option( self::STATE );
		return is_array( $state ) ? $state : array(
			'status'     => '',
			'message'    => '',
			'checked_at' => '',
		);
	}

	/**
	 * Check now.
	 *
	 * @return array status (match|stale_cache|other_source|missing|error), message, checked_at.
	 */
	public static function run() {
		$expected = Llms_Txt::current();
		$live     = self::fetch( Llms_Txt::url() );
		if ( is_wp_error( $live ) ) {
			return self::save( 'error', self::error_text( $live ) );
		}
		if ( 404 === $live['code'] ) {
			return self::save( 'missing', __( 'Visitors get "not found" at /llms.txt. A security or caching rule may block the address; ask your host to let WordPress answer it.', 'chatbot' ) );
		}
		if ( 200 === $live['code'] && self::same( $live['body'], $expected['text'] ) ) {
			return self::save( 'match', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'The live file matches what %s generated.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ) );
		}
		$fresh = self::fetch( add_query_arg( 'qcseo-check', (string) time(), Llms_Txt::url() ) );
		if ( ! is_wp_error( $fresh ) && 200 === $fresh['code'] && self::same( $fresh['body'], $expected['text'] ) ) {
			return self::save( 'stale_cache', __( 'Your cache or CDN is serving an old copy. Clear the cache for /llms.txt (or the whole site) in your caching plugin or CDN, then check again.', 'chatbot' ) );
		}
		return self::save( 'other_source', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Something other than %s answers /llms.txt (a real file on the server or another plugin). See the notes above.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ) );
	}

	/**
	 * Load a URL as a visitor (no cookies).
	 *
	 * @param string $url URL.
	 * @return array|\WP_Error code, body.
	 */
	private static function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'text/plain' ),
				// The site's own address: local and staging sites often use a
				// self-signed certificate, so follow WordPress's own rule for
				// requests to this site (as the scan does).
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter for requests to this site.
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Why the live file could not be loaded, in plain words. A certificate
	 * error (usual on a local or staging site with a self-signed
	 * certificate) gets its own explanation instead of the raw cURL text.
	 *
	 * @param \WP_Error $error Request error.
	 * @return string
	 */
	public static function error_text( \WP_Error $error ) {
		$raw = (string) $error->get_error_message();
		if ( preg_match( '/\b(ssl|tls|certificate)\b|curl error (35|51|58|60|77)\b/i', $raw ) ) {
			return __( "This server does not trust the site's own security certificate, so it could not load the file to compare it. That is usual on a local or staging site with a self-signed certificate, and is not a problem on a site with a valid certificate.", 'chatbot' );
		}
		/* translators: %s: error. */
		return sprintf( __( 'The live file could not be loaded: %s', 'chatbot' ), $raw );
	}

	/**
	 * Same file (line endings and a final newline ignored)?
	 *
	 * @param string $a Text.
	 * @param string $b Text.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		$norm = static function ( $text ) {
			return trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
		};
		return $norm( $a ) === $norm( $b );
	}

	/**
	 * Store and return a result.
	 *
	 * @param string $status  Status.
	 * @param string $message Message.
	 * @return array
	 */
	private static function save( $status, $message ) {
		$state = array(
			'status'     => $status,
			'message'    => $message,
			'checked_at' => gmdate( 'c' ),
		);
		update_option( self::STATE, $state, false );
		return $state;
	}
}
