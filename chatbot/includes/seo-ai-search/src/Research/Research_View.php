<?php
/**
 * What the Research screen shows next to each keyword (S14), added when a
 * run is read so it is always current:
 *
 * - your page: the site already targets the keyword (shared keyword map) or
 *   has a page titled with it;
 * - Vault status, when the keyword is saved;
 * - real numbers only, each with its source: volume, cost per click and
 *   trend from the keyword data provider's cache (never a new cost), and
 *   position and impressions from Search Console. AI keeps its labels
 *   ("AI estimate") and never a number.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Decorates research runs for the screen.
 */
final class Research_View {

	/**
	 * Add page, Vault and real-number fields to every keyword of a run.
	 *
	 * @param array $run Run from Research_Store.
	 * @return array
	 */
	public static function decorate( array $run ) {
		if ( 'done' !== $run['status'] || empty( $run['result'] ) ) {
			return $run;
		}
		$lists    = 'gap' === $run['kind'] ? array( 'gaps', 'shared' ) : ( 'niche' === $run['kind'] ? array( 'first_cluster' ) : array( 'related', 'long_tail', 'questions' ) );
		$keywords = array();
		foreach ( $lists as $list ) {
			foreach ( isset( $run['result'][ $list ] ) ? (array) $run['result'][ $list ] : array() as $item ) {
				if ( ! empty( $item['keyword'] ) ) {
					$keywords[] = (string) $item['keyword'];
				}
			}
		}
		$extra = self::facts(
			array_values( array_unique( $keywords ) ),
			array(
				'language' => $run['language'],
				'country'  => $run['country'],
			)
		);
		foreach ( $lists as $list ) {
			foreach ( isset( $run['result'][ $list ] ) ? (array) $run['result'][ $list ] : array() as $index => $item ) {
				$key = isset( $item['keyword'] ) ? Keyword_Map::normalise( (string) $item['keyword'] ) : '';
				if ( '' !== $key && isset( $extra[ $key ] ) ) {
					$run['result'][ $list ][ $index ] = array_merge( $item, $extra[ $key ] );
				}
			}
		}
		$run['counts'] = array();
		foreach ( array( 'related', 'long_tail', 'questions', 'clusters', 'gaps', 'shared', 'subtopics', 'first_cluster' ) as $list ) {
			if ( isset( $run['result'][ $list ] ) ) {
				$run['counts'][ $list ] = count( (array) $run['result'][ $list ] );
			}
		}
		return $run;
	}

	/**
	 * Facts per keyword.
	 *
	 * @param string[] $keywords Keywords.
	 * @param array    $place    language, country.
	 * @return array normalised keyword => page, vault, real.
	 */
	public static function facts( array $keywords, array $place ) {
		$out = array();
		if ( array() === $keywords ) {
			return $out;
		}
		$cached = function_exists( 'qcld_wpbot_core_keyword_data' ) ? qcld_wpbot_core_keyword_data( $keywords, $place ) : array();
		$gsc    = self::search_console( $keywords );
		foreach ( $keywords as $keyword ) {
			$key  = Keyword_Map::normalise( $keyword );
			$real = array();
			if ( isset( $cached[ $key ] ) && null !== $cached[ $key ]['volume'] ) {
				$real['volume'] = array(
					'value'  => (int) $cached[ $key ]['volume'],
					'source' => self::provider_label( (string) $cached[ $key ]['provider'] ),
				);
				if ( null !== $cached[ $key ]['cpc'] ) {
					$real['cpc'] = array(
						'value'  => (float) $cached[ $key ]['cpc'],
						'source' => self::provider_label( (string) $cached[ $key ]['provider'] ),
					);
				}
				if ( ! empty( $cached[ $key ]['trend'] ) ) {
					$real['trend'] = array(
						'value'  => array_map( 'intval', (array) $cached[ $key ]['trend'] ),
						'source' => self::provider_label( (string) $cached[ $key ]['provider'] ),
					);
				}
			}
			if ( isset( $gsc[ $key ] ) ) {
				$real['position']    = array(
					'value'  => $gsc[ $key ]['position'],
					'source' => __( 'Search Console', 'chatbot' ),
				);
				$real['impressions'] = array(
					'value'  => $gsc[ $key ]['impressions'],
					'source' => __( 'Search Console', 'chatbot' ),
				);
			}
			$vault       = function_exists( 'qcld_wpbot_core_vault_query' ) ? self::vault_entry( $key, $place ) : null;
			$out[ $key ] = array(
				'page'  => self::page_for( $key ),
				'vault' => $vault ? array(
					'id'     => $vault['id'],
					'status' => $vault['status'],
				) : null,
				'real'  => $real,
			);
		}
		return $out;
	}

	/**
	 * A page the site already has for this keyword.
	 *
	 * @param string $keyword Normalised keyword.
	 * @return array|null id, title, url, edit.
	 */
	public static function page_for( $keyword ) {
		$post_id = 0;
		foreach ( Keyword_Map::posts_for_query( $keyword ) as $entry ) {
			if ( $entry['post_id'] && 'publish' === get_post_status( $entry['post_id'] ) ) {
				$post_id = (int) $entry['post_id'];
				break;
			}
		}
		if ( ! $post_id ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One cheap title lookup per keyword, not cached on purpose (fresh after edits).
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page','product') AND LOWER(post_title) = %s LIMIT 1", $keyword ) );
		}
		if ( ! $post_id ) {
			return null;
		}
		return array(
			'id'    => $post_id,
			'title' => get_the_title( $post_id ),
			'url'   => (string) get_permalink( $post_id ),
			'edit'  => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
		);
	}

	/**
	 * Search Console facts for keywords (last 28 days), when connected.
	 *
	 * @param string[] $keywords Keywords.
	 * @return array normalised keyword => position, impressions.
	 */
	private static function search_console( array $keywords ) {
		if ( ! Google::connected() ) {
			return array();
		}
		static $rows = null;
		if ( null === $rows ) {
			$rows = array();
			foreach ( Data_Store::period_rows( 'query', gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ), 1, 5000 ) as $row ) {
				$rows[ Keyword_Map::normalise( $row['query'] ) ] = array(
					'position'    => round( (float) $row['position'], 1 ),
					'impressions' => (int) $row['impressions'],
				);
			}
		}
		$out = array();
		foreach ( $keywords as $keyword ) {
			$key = Keyword_Map::normalise( $keyword );
			if ( isset( $rows[ $key ] ) ) {
				$out[ $key ] = $rows[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Vault entry for a keyword in this place (or any place).
	 *
	 * @param string $keyword Normalised keyword.
	 * @param array  $place   language, country.
	 * @return array|null
	 */
	private static function vault_entry( $keyword, array $place ) {
		$entry = \QuantumCloud\WPBotCore\Vault\Vault::find( $keyword, $place['language'], $place['country'] );
		return $entry ? $entry : \QuantumCloud\WPBotCore\Vault\Vault::find( $keyword );
	}

	/**
	 * Display name of a keyword data provider.
	 *
	 * @param string $id Provider id.
	 * @return string
	 */
	public static function provider_label( $id ) {
		$labels = array(
			'dataforseo'         => 'DataForSEO',
			'keywordseverywhere' => 'Keywords Everywhere',
			'semrush'            => 'Semrush',
			'google_ads_planner' => __( 'Google Keyword Planner', 'chatbot' ),
			'bing_free'          => __( 'Bing (free)', 'chatbot' ),
		);
		return isset( $labels[ $id ] ) ? $labels[ $id ] : ucfirst( str_replace( '_', ' ', $id ) );
	}
}
