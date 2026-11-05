<?php
/**
 * What SEO & AI Search adds to WPBot Core's shared Site Context:
 * the top Search Console searches, the Keyword Vault keywords being worked
 * on, the main location, and the pages with the most clicks (scanned first).
 * Only data the module already has; nothing is fetched.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Cited;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Local\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * SEO facts for the Site Context.
 */
final class Site_Facts {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/context_facts', array( __CLASS__, 'facts' ), 20 );
		add_filter( 'qcld_wpbot_core/context_important_posts', array( __CLASS__, 'important_posts' ) );
	}

	/**
	 * Add the module's facts.
	 *
	 * @param array $facts label => text or list.
	 * @return array
	 */
	public static function facts( $facts ) {
		$facts = (array) $facts;
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
		$top   = array_column( Data_Store::top( 'query', $start, $end, 25, 'clicks' ), 'label' );
		if ( array() !== $top ) {
			$facts['Top searches that bring visitors (Search Console, 90 days)'] = $top;
		}
		if ( function_exists( 'qcld_wpbot_core_vault_query' ) ) {
			$vault = array_column(
				(array) qcld_wpbot_core_vault_query(
					array(
						'status' => array( 'targeted', 'ranking' ),
						'limit'  => 25,
					)
				),
				'keyword'
			);
			if ( array() !== $vault ) {
				$facts['Searches the owner targets (Keyword Vault)'] = $vault;
			}
		}
		$main = Locations::main();
		if ( is_array( $main ) && ( '' !== (string) $main['city'] || '' !== (string) $main['country'] ) ) {
			$types                                  = Locations::types();
			$type                                   = isset( $types[ $main['type'] ] ) ? (string) $types[ $main['type'] ] : (string) $main['type'];
			$where                                  = trim( implode( ', ', array_filter( array( $main['city'], $main['region'], $main['country'] ) ) ) );
			$areas                                  = array_filter( (array) $main['service_area'] );
			$facts['Business location (Local SEO)'] = trim( $type . ' in ' . $where . ( array() !== $areas ? '; serves ' . implode( ', ', $areas ) : '' ) );
		}
		return $facts;
	}

	/**
	 * Pages with the most search clicks (90 days), scanned first.
	 *
	 * @param int[] $ids Post ids.
	 * @return int[]
	 */
	public static function important_posts( $ids ) {
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
		$top   = array_filter( array_map( 'intval', array_column( Data_Store::top( 'page', $start, $end, 20, 'clicks' ), 'post_id' ) ) );
		return array_merge( $top, (array) $ids );
	}
}
