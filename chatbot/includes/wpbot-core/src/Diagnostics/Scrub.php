<?php
/**
 * Removes secrets from what the error log stores and the support report sends: API keys,
 * tokens, passwords, authorization headers, secret query parameters and email addresses.
 * File paths are shortened to the part inside wp-content.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Secret scrubber.
 */
final class Scrub {

	/**
	 * Patterns and their replacements.
	 *
	 * @return array<string,string>
	 */
	public static function patterns() {
		$patterns = array(
			// Provider keys and tokens: OpenAI/Anthropic/DeepSeek (sk-), Google (AIza), xAI, Groq, OpenRouter, Mistral-style long tokens after "key".
			'/\b(sk|sk-ant|sk-or|sk-proj|xai|gsk|pk|rk|whsec|EAA[A-Za-z0-9]*|ya29)[-_][A-Za-z0-9_\-]{8,}/' => '$1-[secret]',
			'/\bAIza[0-9A-Za-z_\-]{20,}/'                => 'AIza[secret]',
			'/\b(Bearer|Basic|Token)\s+[A-Za-z0-9_\-\.=+\/]{8,}/i' => '$1 [secret]',
			'/("?(?:authorization|x-api-key|api[-_]?key|xi-api-key|x-goog-api-key)"?\s*[:=]\s*"?)[^"\s,}&]+/i' => '$1[secret]',
			'/([?&](?:key|api_key|apikey|token|access_token|secret|password|pass|pwd|signature|sig|auth|code)=)[^&\s"\']+/i' => '$1[secret]',
			'/("?(?:password|passwd|secret|client_secret|token|access_token|refresh_token)"?\s*[:=]\s*"?)[^"\s,}&]+/i' => '$1[secret]',
			'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[email]',
		);
		/**
		 * Patterns that remove secrets from the error log and the support report (pattern => replacement).
		 *
		 * @param array<string,string> $patterns Patterns.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/error_scrub_patterns', $patterns );
	}

	/**
	 * Scrub a text and cut it to a length.
	 *
	 * @param string $text Text.
	 * @param int    $max  Most characters.
	 * @return string
	 */
	public static function text( $text, $max = 2000 ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return '';
		}
		foreach ( self::patterns() as $pattern => $replacement ) {
			$result = @preg_replace( $pattern, $replacement, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A bad filtered pattern must not break logging.
			$text   = is_string( $result ) ? $result : $text;
		}
		$text = self::paths( $text );
		$text = trim( (string) preg_replace( '/[ \t]+/', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, (int) $max ) : substr( $text, 0, (int) $max );
	}

	/**
	 * A file path relative to wp-content (or the site root).
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function path( $path ) {
		return self::paths( str_replace( '\\', '/', (string) $path ) );
	}

	/**
	 * Shorten absolute paths inside a text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function paths( $text ) {
		$roots = array();
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots[ str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ] = 'wp-content/';
		}
		if ( defined( 'ABSPATH' ) ) {
			$roots[ str_replace( '\\', '/', ABSPATH ) ] = '';
		}
		return str_replace( array_keys( $roots ), array_values( $roots ), str_replace( '\\', '/', (string) $text ) );
	}

	/**
	 * Scrub every string in a detail array (two levels deep; longer values are cut).
	 *
	 * @param array $detail Detail.
	 * @param int   $depth  Depth.
	 * @return array
	 */
	public static function detail( array $detail, $depth = 0 ) {
		$clean = array();
		foreach ( $detail as $key => $value ) {
			$key = is_string( $key ) ? substr( sanitize_key( $key ), 0, 40 ) : $key;
			if ( is_array( $value ) ) {
				$clean[ $key ] = $depth < 2 ? self::detail( $value, $depth + 1 ) : '[…]';
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$clean[ $key ] = $value;
			} else {
				$clean[ $key ] = self::text( (string) $value, 'stack' === $key ? 3000 : 1000 );
			}
		}
		return $clean;
	}
}
