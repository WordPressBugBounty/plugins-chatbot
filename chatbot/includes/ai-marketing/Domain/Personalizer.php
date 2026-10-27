<?php
/**
 * Merge-tag replacement.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces {{tag}} and {tag} merge tags with contact data and extra values.
 *
 * Double-brace tags that are unknown become ''. Single-brace tokens are only replaced
 * when known, so CSS/JSON braces in HTML are left alone.
 */
final class Personalizer {

	/**
	 * Replace merge tags.
	 *
	 * @param string $text    Subject or HTML body.
	 * @param array  $contact Contact row (first_name, last_name, email, phone, company, website, attributes).
	 * @param array  $extra   Extra tag => value (e.g. unsubscribe_url). Overrides contact values.
	 * @param bool   $escape  HTML-escape values (true for bodies, false for subjects/plain text).
	 * @return string
	 */
	public static function render( $text, array $contact, array $extra = array(), $escape = true ) {
		$text = (string) $text;
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}
		$tags = self::tags( $contact, $extra );

		$text = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			static function ( $m ) use ( $tags, $escape ) {
				$key = strtolower( $m[1] );
				return isset( $tags[ $key ] ) ? self::value( $tags[ $key ], $escape ) : '';
			},
			$text
		);

		return preg_replace_callback(
			'/\{([a-zA-Z0-9_]+)\}/',
			static function ( $m ) use ( $tags, $escape ) {
				$key = strtolower( $m[1] );
				return isset( $tags[ $key ] ) ? self::value( $tags[ $key ], $escape ) : $m[0];
			},
			$text
		);
	}

	/**
	 * The available tag map for a contact.
	 *
	 * @param array $contact Contact row.
	 * @param array $extra   Extra values.
	 * @return array<string,string>
	 */
	public static function tags( array $contact, array $extra = array() ) {
		$tags = array(
			'site_name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'  => home_url( '/' ),
			'year'      => gmdate( 'Y' ),
		);
		$attributes = isset( $contact['attributes'] ) && is_array( $contact['attributes'] ) ? $contact['attributes'] : array();
		foreach ( $attributes as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$tags[ strtolower( (string) $key ) ] = (string) $value;
			}
		}
		foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'company', 'website' ) as $field ) {
			$tags[ $field ] = isset( $contact[ $field ] ) ? (string) $contact[ $field ] : '';
		}
		$tags['full_name'] = trim( $tags['first_name'] . ' ' . $tags['last_name'] );
		foreach ( $extra as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$tags[ strtolower( (string) $key ) ] = (string) $value;
			}
		}
		return $tags;
	}

	/**
	 * Escape a value for output.
	 *
	 * @param string $value  Value.
	 * @param bool   $escape Whether to escape.
	 * @return string
	 */
	private static function value( $value, $escape ) {
		return $escape ? esc_html( $value ) : $value;
	}
}
