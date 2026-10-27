<?php
/**
 * Device, browser, OS, country and bot detection from the request.
 * Nothing here is stored except the short labels it returns.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Ingest;

defined( 'ABSPATH' ) || exit;

final class Client_Info {

	/**
	 * Request user agent.
	 *
	 * @return string
	 */
	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '';
	}

	/**
	 * Client IP (only used for rate limiting, never stored).
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Whether the user agent looks like a bot or monitor.
	 *
	 * @param string $ua User agent.
	 * @return bool
	 */
	public static function is_bot( $ua ) {
		if ( '' === $ua ) {
			return true;
		}
		$pattern = '/bot|crawl|spider|slurp|facebookexternalhit|preview|lighthouse|pagespeed|pingdom|uptime|monitor|headlesschrome|phantomjs|python-requests|curl|wget|httpclient/i';
		/**
		 * Treat a user agent as a bot.
		 *
		 * @param bool   $is_bot Default decision.
		 * @param string $ua     User agent.
		 */
		return (bool) apply_filters( 'qcin/is_bot', (bool) preg_match( $pattern, $ua ), $ua );
	}

	/**
	 * Device class from user agent and viewport width.
	 *
	 * @param string $ua User agent.
	 * @param int    $vw Viewport width.
	 * @return string desktop|tablet|mobile
	 */
	public static function device( $ua, $vw ) {
		if ( preg_match( '/iPad|Tablet|Nexus 7|Nexus 10|SM-T|Kindle|Silk/i', $ua ) || ( preg_match( '/Android/i', $ua ) && ! preg_match( '/Mobile/i', $ua ) ) ) {
			return 'tablet';
		}
		if ( preg_match( '/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua ) ) {
			return 'mobile';
		}
		if ( $vw > 0 && $vw < 768 ) {
			return 'mobile';
		}
		if ( $vw > 0 && $vw < 1024 ) {
			return 'tablet';
		}
		return 'desktop';
	}

	/**
	 * Browser family.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	public static function browser( $ua ) {
		$map = array(
			'/Edg\//'             => 'Edge',
			'/OPR\/|Opera/'       => 'Opera',
			'/SamsungBrowser/'    => 'Samsung Internet',
			'/Firefox\//'         => 'Firefox',
			'/CriOS|Chrome\//'    => 'Chrome',
			'/Safari\//'          => 'Safari',
		);
		foreach ( $map as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return 'Other';
	}

	/**
	 * Operating system family.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	public static function os( $ua ) {
		$map = array(
			'/Windows/'           => 'Windows',
			'/iPhone|iPad|iPod/'  => 'iOS',
			'/Mac OS X|Macintosh/' => 'macOS',
			'/Android/'           => 'Android',
			'/CrOS/'              => 'ChromeOS',
			'/Linux/'             => 'Linux',
		);
		foreach ( $map as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return 'Other';
	}

	/**
	 * Two-letter country code from CDN or server headers, if any.
	 * No IP lookup is done, so no IP leaves the server or is stored.
	 *
	 * @return string
	 */
	public static function country() {
		$headers = array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_GEOIP_COUNTRY' );
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$code = strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ), 0, 2 ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code && 'T1' !== $code ) {
					return $code;
				}
			}
		}
		/**
		 * Country code for the current request (e.g. from a GeoIP plugin).
		 *
		 * @param string $code Two-letter code or empty.
		 */
		$code = (string) apply_filters( 'qcin/country', '' );
		return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : '';
	}
}
