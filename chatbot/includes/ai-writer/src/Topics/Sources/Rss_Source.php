<?php
/**
 * Topic source: industry and competitor RSS feeds the owner adds. Only
 * headlines and links are kept (no article text is copied); items from
 * the last 14 days become ideas that link to the original.
 *
 * Feeds are read with WordPress's own fetch_feed() (SimplePie, cached by
 * WordPress for 12 hours).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * RSS source.
 */
final class Rss_Source {

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function status() {
		$feeds = (array) Settings::get( 'rss_feeds' );
		if ( array() === $feeds ) {
			return array(
				'status'  => 'not_connected',
				'message' => __( 'Add the news or blog feeds of your industry and competitors.', 'chatbot' ),
				'fix'     => array(
					'label' => __( 'Add feeds', 'chatbot' ),
					'tab'   => 'settings',
				),
			);
		}
		$failing = get_option( 'qcld_wpbot_writer_rss_failing', array() );
		if ( is_array( $failing ) && array() !== array_intersect( $feeds, array_keys( $failing ) ) ) {
			return array(
				'status'  => 'failing',
				/* translators: %s: feed URLs. */
				'message' => sprintf( __( 'These feeds could not be read: %s', 'chatbot' ), implode( ', ', array_intersect( $feeds, array_keys( $failing ) ) ) ),
				'fix'     => array(
					'label' => __( 'Check feeds', 'chatbot' ),
					'tab'   => 'settings',
				),
			);
		}
		return array(
			'status'  => 'connected',
			/* translators: %d: number of feeds. */
			'message' => sprintf( _n( '%d feed', '%d feeds', count( $feeds ), 'chatbot' ), count( $feeds ) ),
		);
	}

	/**
	 * Ideas: recent headlines.
	 *
	 * @param array $context Context.
	 * @return array[]|WP_Error
	 */
	public static function ideas( array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}
		$ideas   = array();
		$failing = array();
		$since   = time() - 14 * DAY_IN_SECONDS;
		foreach ( (array) Settings::get( 'rss_feeds' ) as $url ) {
			$feed = fetch_feed( $url );
			if ( is_wp_error( $feed ) ) {
				$failing[ $url ] = $feed->get_error_message();
				continue;
			}
			$name = wp_strip_all_tags( (string) $feed->get_title() );
			$name = '' !== $name ? $name : (string) wp_parse_url( $url, PHP_URL_HOST );
			foreach ( $feed->get_items( 0, 10 ) as $item ) {
				$time  = (int) $item->get_date( 'U' );
				$title = trim( html_entity_decode( wp_strip_all_tags( (string) $item->get_title() ), ENT_QUOTES, 'UTF-8' ) );
				$link  = esc_url_raw( (string) $item->get_permalink(), array( 'http', 'https' ) );
				if ( '' === $title || ( $time && $time < $since ) ) {
					continue;
				}
				$ideas[] = array(
					'title'      => $title,
					'ref'        => md5( '' !== $link ? $link : $title ),
					/* translators: 1: feed name, 2: date. */
					'reason'     => sprintf( __( 'New on %1$s (%2$s). Write your own take; link to the original if you mention it.', 'chatbot' ), $name, $time ? wp_date( get_option( 'date_format' ), $time ) : __( 'recent', 'chatbot' ) ),
					'trend'      => 'rising',
					'rank_score' => $time ? max( 1, 14 - ( time() - $time ) / DAY_IN_SECONDS ) : 1,
					'link'       => $link,
					'metrics'    => array( 'feed' => $name ),
				);
			}
		}
		update_option( 'qcld_wpbot_writer_rss_failing', $failing, false );
		return array_slice( $ideas, 0, 40 );
	}
}
