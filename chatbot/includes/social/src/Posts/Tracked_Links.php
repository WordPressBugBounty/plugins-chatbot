<?php
/**
 * Tracked links: UTM tags per network (Settings › Links & tracking).
 * Uses AI Writer's builder when it is active, so both tag links the same way.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * UTM link builder.
 */
final class Tracked_Links {

	/**
	 * Tag a link for one network.
	 *
	 * Only links to this site are tagged; other sites' links are left alone.
	 *
	 * @param string $url      Link.
	 * @param string $network  Network id (utm_source).
	 * @param string $campaign   Campaign name (from the post title).
	 * @param int    $account_id Account (utm_id "a{id}", so two accounts' posts with one title stay apart). 0 = none.
	 * @return string
	 */
	public static function tag( $url, $network, $campaign, $account_id = 0 ) {
		$url = (string) $url;
		if ( '' === $url || ! Settings::get( 'utm_enabled' ) || ! self::is_own_site( $url ) ) {
			return $url;
		}
		$params = array(
			'utm_source'   => sanitize_key( $network ),
			'utm_medium'   => sanitize_key( (string) Settings::get( 'utm_medium' ) ),
			'utm_campaign' => self::campaign( $campaign ),
		);
		if ( function_exists( 'qcld_wpbot_writer_tracked_url' ) ) {
			// AI Writer's builder takes source / medium / campaign / content.
			$tagged = qcld_wpbot_writer_tracked_url(
				$url,
				array(
					'source'   => $params['utm_source'],
					'medium'   => $params['utm_medium'],
					'campaign' => $params['utm_campaign'],
					'content'  => 'wpbot_social',
				)
			);
			if ( is_string( $tagged ) && '' !== $tagged ) {
				return $account_id ? add_query_arg( 'utm_id', 'a' . absint( $account_id ), $tagged ) : $tagged;
			}
		}
		if ( $account_id ) {
			$params['utm_id'] = 'a' . absint( $account_id );
		}
		return add_query_arg( array_map( 'rawurlencode', $params ), $url );
	}

	/**
	 * Campaign slug: lowercase letters, digits and dashes, at most 60 characters.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function campaign( $title ) {
		$slug = strtolower( trim( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', remove_accents( (string) $title ) ), '-' ) );
		return '' === $slug ? 'social' : substr( $slug, 0, 60 );
	}

	/**
	 * Whether a URL points at this site.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_own_site( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && is_string( $home ) && strtolower( preg_replace( '/^www\./', '', $host ) ) === strtolower( preg_replace( '/^www\./', '', $home ) );
	}
}
