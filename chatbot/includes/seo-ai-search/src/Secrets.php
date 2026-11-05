<?php
/**
 * Encrypted keys the module owns (PageSpeed, Bing Webmaster).
 *
 * Stored with WPBot Core's Secret_Box, autoload off, masked in every view,
 * never sent to the browser.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Module secrets.
 */
final class Secrets {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_seo_secrets';

	/**
	 * Known secret ids.
	 */
	const IDS = array( 'pagespeed', 'bing' );

	/**
	 * Plain value of one secret, or ''.
	 *
	 * @param string $id Secret id.
	 * @return string
	 */
	public static function get( $id ) {
		$all = self::load();
		if ( empty( $all[ $id ] ) ) {
			return '';
		}
		$plain = Secret_Box::decrypt( (string) $all[ $id ] );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Save or clear secrets. Empty strings leave a secret unchanged; null clears it.
	 *
	 * @param array $changes id => string|null.
	 * @return array Masked view.
	 */
	public static function update( array $changes ) {
		$all = self::load();
		foreach ( self::IDS as $id ) {
			if ( ! array_key_exists( $id, $changes ) ) {
				continue;
			}
			if ( null === $changes[ $id ] ) {
				unset( $all[ $id ] );
				continue;
			}
			$value = (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes[ $id ] );
			if ( '' !== $value ) {
				$all[ $id ] = Secret_Box::encrypt( substr( $value, 0, 200 ) );
			}
		}
		update_option( self::OPTION, $all, false );
		return self::public_view();
	}

	/**
	 * Masked view for the settings screen.
	 *
	 * @return array id => [ set => bool, hint => string ]
	 */
	public static function public_view() {
		$out = array();
		foreach ( self::IDS as $id ) {
			$plain      = self::get( $id );
			$out[ $id ] = array(
				'set'  => '' !== $plain,
				'hint' => '' !== $plain ? Secret_Box::mask( $plain ) : '',
			);
		}
		return $out;
	}

	/**
	 * Stored option.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}
}
