<?php
/**
 * Local SEO, round 3 L: the service and area planner. A grid of the
 * business's services (Site Context, product categories, or the owner's own
 * list) against the areas it serves (the towns of its locations and the
 * areas served), with the monthly searches for "service + area" when known,
 * the page that already covers it (Keyword Map, location page), and a plain
 * verdict. "Deserves its own page" only when the area is served and has a
 * location with real local details (address and phone); otherwise the area
 * belongs on the service page, because pages that only swap the town name
 * are doorway pages, which Google's spam policies forbid.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Local;

use QuantumCloud\SeoAiSearch\Keywords\Volumes;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Service and area planner.
 */
final class Area_Planner {

	const OPTION       = 'qcld_wpbot_seo_area_plan';
	const MAX_SERVICES = 12;
	const MAX_AREAS    = 12;
	const MIN_DEMAND   = 10;

	/**
	 * The owner's own lists (empty = use the suggestions).
	 *
	 * @return array services, areas.
	 */
	public static function saved() {
		$plan = get_option( self::OPTION );
		$plan = is_array( $plan ) ? $plan : array();
		return array(
			'services' => isset( $plan['services'] ) ? array_values( (array) $plan['services'] ) : array(),
			'areas'    => isset( $plan['areas'] ) ? array_values( (array) $plan['areas'] ) : array(),
		);
	}

	/**
	 * Save the owner's lists.
	 *
	 * @param array $lists services, areas (string[] each).
	 * @return array Saved lists.
	 */
	public static function save( array $lists ) {
		$clean = static function ( $items, $max ) {
			$out = array();
			foreach ( (array) $items as $item ) {
				$item = trim( sanitize_text_field( (string) $item ) );
				if ( '' !== $item && strlen( $item ) <= 80 && ! isset( $out[ strtolower( $item ) ] ) ) {
					$out[ strtolower( $item ) ] = $item;
				}
			}
			return array_slice( array_values( $out ), 0, $max );
		};
		$plan  = array(
			'services' => $clean( isset( $lists['services'] ) ? $lists['services'] : array(), self::MAX_SERVICES ),
			'areas'    => $clean( isset( $lists['areas'] ) ? $lists['areas'] : array(), self::MAX_AREAS ),
		);
		update_option( self::OPTION, $plan, false );
		return $plan;
	}

	/**
	 * Suggested services: Site Context offers, then product categories.
	 *
	 * @return string[]
	 */
	public static function suggested_services() {
		$out = array();
		if ( function_exists( 'qcld_wpbot_core_context' ) ) {
			$context = qcld_wpbot_core_context();
			$offers  = isset( $context['summary']['offers'] ) && is_array( $context['summary']['offers'] ) ? $context['summary']['offers'] : array();
			foreach ( $offers as $offer ) {
				$offer = trim( (string) preg_replace( '/\s*(?:[(:]|\s[-–—]\s).*$/u', '', (string) $offer ) );
				if ( strlen( $offer ) >= 3 && str_word_count( $offer ) <= 4 ) {
					$out[ strtolower( $offer ) ] = $offer;
				}
			}
		}
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'orderby'    => 'count',
					'order'      => 'DESC',
					'hide_empty' => true,
					'number'     => 10,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( 'uncategorized' !== $term->slug ) {
					$out[ strtolower( $term->name ) ] = $term->name;
				}
			}
		}
		/**
		 * Filter the services suggested for the area planner.
		 *
		 * @param string[] $services Services.
		 */
		$out = (array) apply_filters( 'qcld_wpbot_seo/area_services', array_values( $out ) );
		return array_slice( array_values( array_filter( array_map( 'strval', $out ) ) ), 0, self::MAX_SERVICES );
	}

	/**
	 * Areas from the locations: each location's town, then the areas served.
	 *
	 * @return array[] name, location_id, details (real local details: a location there with address and phone).
	 */
	public static function suggested_areas() {
		$out = array();
		foreach ( Locations::all() as $location ) {
			$city = trim( (string) $location['city'] );
			if ( '' !== $city ) {
				$key   = strtolower( $city );
				$local = '' !== trim( (string) $location['street'] ) && '' !== trim( (string) $location['phone'] );
				if ( ! isset( $out[ $key ] ) || ( $local && ! $out[ $key ]['details'] ) ) {
					$out[ $key ] = array(
						'name'        => $city,
						'location_id' => (int) $location['id'],
						'details'     => $local,
					);
				}
			}
		}
		foreach ( Locations::all() as $location ) {
			foreach ( (array) $location['service_area'] as $area ) {
				$area = trim( (string) $area );
				if ( '' !== $area && ! isset( $out[ strtolower( $area ) ] ) ) {
					$out[ strtolower( $area ) ] = array(
						'name'        => $area,
						'location_id' => 0,
						'details'     => false,
					);
				}
			}
		}
		return array_slice( array_values( $out ), 0, self::MAX_AREAS );
	}

	/**
	 * The services and areas in use (the owner's lists win).
	 *
	 * @return array services (string[]), areas (array[]), custom (bool[]).
	 */
	public static function lists() {
		$saved    = self::saved();
		$services = array() !== $saved['services'] ? $saved['services'] : self::suggested_services();
		$known    = array();
		foreach ( self::suggested_areas() as $area ) {
			$known[ strtolower( $area['name'] ) ] = $area;
		}
		if ( array() !== $saved['areas'] ) {
			$areas = array();
			foreach ( $saved['areas'] as $name ) {
				$areas[] = isset( $known[ strtolower( $name ) ] ) ? $known[ strtolower( $name ) ] : array(
					'name'        => $name,
					'location_id' => 0,
					'details'     => false,
				);
			}
		} else {
			$areas = array_values( $known );
		}
		return array(
			'services' => $services,
			'areas'    => $areas,
			'custom'   => array(
				'services' => array() !== $saved['services'],
				'areas'    => array() !== $saved['areas'],
			),
		);
	}

	/**
	 * The "service + area" searches of the grid.
	 *
	 * @return string[]
	 */
	public static function searches() {
		$lists = self::lists();
		$out   = array();
		foreach ( $lists['services'] as $service ) {
			foreach ( $lists['areas'] as $area ) {
				$out[] = self::search( $service, $area['name'] );
			}
		}
		return $out;
	}

	/**
	 * One search.
	 *
	 * @param string $service Service.
	 * @param string $area    Area.
	 * @return string
	 */
	public static function search( $service, $area ) {
		return strtolower( trim( $service ) . ' ' . trim( $area ) );
	}

	/**
	 * The grid.
	 *
	 * @return array services, areas, cells[service][area] (search, volume, page, verdict), lists.
	 */
	public static function grid() {
		$lists  = self::lists();
		$data   = Volumes::cached( self::searches() );
		$mapped = class_exists( Keyword_Map::class );
		$cells  = array();
		foreach ( $lists['services'] as $si => $service ) {
			foreach ( $lists['areas'] as $ai => $area ) {
				$search = self::search( $service, $area['name'] );
				$row    = $data ? Volumes::row_for( $data, $search ) : null;
				$volume = $row && null !== $row['volume'] ? (int) $row['volume'] : null;
				$page   = $mapped ? self::page_for( $search ) : null;
				if ( ! $page && $area['location_id'] ) {
					$location = Locations::get( $area['location_id'] );
					if ( $location && $location['page_id'] ) {
						$page = array(
							'title' => get_the_title( $location['page_id'] ),
							'url'   => (string) get_permalink( $location['page_id'] ),
							'kind'  => 'location',
						);
					}
				}
				$cells[ $si ][ $ai ] = array(
					'search'  => $search,
					'volume'  => $volume,
					'page'    => $page,
					'verdict' => self::verdict( $area, $volume, null !== $page ),
				);
			}
		}
		return array(
			'services' => $lists['services'],
			'areas'    => $lists['areas'],
			'custom'   => $lists['custom'],
			'cells'    => $cells,
			'volumes'  => Volumes::available(),
		);
	}

	/**
	 * The verdict for one cell (pure).
	 *
	 * @param array    $area    name, location_id, details.
	 * @param int|null $volume  Monthly searches (null = unknown).
	 * @param bool     $covered A page covers it already.
	 * @return string covered | own_page | service_page | low.
	 */
	public static function verdict( array $area, $volume, $covered ) {
		if ( $covered ) {
			return 'covered';
		}
		if ( null !== $volume && $volume < self::MIN_DEMAND ) {
			return 'low';
		}
		return ! empty( $area['details'] ) ? 'own_page' : 'service_page';
	}

	/**
	 * The page (or planned page) whose main search this is.
	 *
	 * @param string $search Search.
	 * @return array|null title, url, kind.
	 */
	private static function page_for( $search ) {
		foreach ( Keyword_Map::posts_for_query( $search ) as $entry ) {
			if ( $entry['post_id'] ) {
				return array(
					'title' => get_the_title( $entry['post_id'] ),
					'url'   => (string) get_permalink( $entry['post_id'] ),
					'kind'  => 'page',
				);
			}
			return array(
				'title' => (string) $entry['title'],
				'url'   => '',
				'kind'  => 'planned',
			);
		}
		return null;
	}
}
