<?php
/**
 * Merge tags with fallbacks: {first_name}, {first_name|there}, {last_name},
 * {email}, {company}, {site_name}, {site_url}, {unsubscribe_url},
 * {attribute:plan|basic}. Values are escaped for HTML; site tags cannot be
 * overridden by contact attributes.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

defined( 'ABSPATH' ) || exit;

/**
 * Personalizer.
 */
final class Personalizer {

	/**
	 * Replace merge tags (pure; unit tested).
	 *
	 * @param string $text    Text or HTML.
	 * @param array  $contact Contact (first_name, last_name, email, company, attributes).
	 * @param array  $site    site_name, site_url, unsubscribe_url.
	 * @param bool   $html    Escape values for HTML.
	 * @return string
	 */
	public static function apply( $text, array $contact, array $site, $html = true ) {
		return (string) preg_replace_callback(
			'/\{\s*([a-z_]+)(?::([a-z0-9_]+))?\s*(?:\|([^}]*))?\}/i',
			static function ( $m ) use ( $contact, $site, $html ) {
				$key      = strtolower( $m[1] );
				$fallback = isset( $m[3] ) ? trim( $m[3] ) : '';
				if ( isset( $site[ $key ] ) ) {
					$value = (string) $site[ $key ];
				} elseif ( 'attribute' === $key && ! empty( $m[2] ) ) {
					$attributes = isset( $contact['attributes'] ) && is_array( $contact['attributes'] ) ? $contact['attributes'] : array();
					$value      = isset( $attributes[ strtolower( $m[2] ) ] ) ? (string) $attributes[ strtolower( $m[2] ) ] : '';
				} elseif ( in_array( $key, array( 'first_name', 'last_name', 'email', 'company', 'phone' ), true ) ) {
					$value = isset( $contact[ $key ] ) ? (string) $contact[ $key ] : '';
				} else {
					return $m[0];
				}
				$value = '' !== trim( $value ) ? $value : $fallback;
				return $html ? esc_html( $value ) : $value;
			},
			(string) $text
		);
	}
}
