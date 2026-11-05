<?php
/**
 * LocalBusiness schema for the main location (front page and its page),
 * added to WPBot's schema graph, which is printed only when no SEO plugin
 * prints schema. With an SEO plugin, the Local SEO screen tells the owner
 * where to add the business in that plugin instead.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Local;

defined( 'ABSPATH' ) || exit;

/**
 * Local schema.
 */
final class Local_Schema {

	/**
	 * Hook the schema graph.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_seo/schema_graph', array( __CLASS__, 'graph' ) );
	}

	/**
	 * Add LocalBusiness nodes on the front page and on location pages.
	 *
	 * @param array $nodes Nodes.
	 * @return array
	 */
	public static function graph( $nodes ) {
		$object = is_singular() ? (int) get_queried_object_id() : 0;
		foreach ( Locations::all() as $location ) {
			$here = ( $location['is_main'] && is_front_page() ) || ( $object && $object === (int) $location['page_id'] );
			if ( $here && '' !== $location['name'] ) {
				$nodes[] = self::node( $location );
			}
		}
		return $nodes;
	}

	/**
	 * Schema node for a location.
	 *
	 * @param array $location Location.
	 * @return array
	 */
	public static function node( array $location ) {
		$node = array(
			'@type' => $location['type'],
			'@id'   => ( $location['page_id'] ? (string) get_permalink( $location['page_id'] ) : home_url( '/' ) ) . '#business-' . (int) $location['id'],
			'name'  => $location['name'],
			'url'   => '' !== $location['url'] ? $location['url'] : ( $location['page_id'] ? (string) get_permalink( $location['page_id'] ) : home_url( '/' ) ),
		);
		if ( '' !== $location['street'] || '' !== $location['city'] ) {
			$node['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $location['street'],
					'addressLocality' => $location['city'],
					'addressRegion'   => $location['region'],
					'postalCode'      => $location['postcode'],
					'addressCountry'  => $location['country'],
				)
			);
		}
		if ( '' !== $location['phone'] ) {
			$node['telephone'] = $location['phone'];
		}
		if ( '' !== $location['email'] ) {
			$node['email'] = $location['email'];
		}
		if ( '' !== $location['price_range'] ) {
			$node['priceRange'] = $location['price_range'];
		}
		if ( '' !== $location['lat'] && '' !== $location['lng'] ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $location['lat'],
				'longitude' => (float) $location['lng'],
			);
		}
		if ( array() !== $location['service_area'] ) {
			$node['areaServed'] = $location['service_area'];
		}
		$hours = array();
		foreach ( $location['hours'] as $day => $slot ) {
			if ( empty( $slot['closed'] ) && ! empty( $slot['open'] ) ) {
				$hours[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day,
					'opens'     => $slot['open'],
					'closes'    => $slot['close'],
				);
			}
		}
		if ( array() !== $hours ) {
			$node['openingHoursSpecification'] = $hours;
		}
		return $node;
	}
}
