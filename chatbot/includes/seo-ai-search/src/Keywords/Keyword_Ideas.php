<?php
/**
 * Keyword ideas from a seed word or a page (its main search), with whether
 * the site already appears for each, "Add to Topic Finder" and "Write a
 * page". One search at a time in Free; the estimate is shown first.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Keywords;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\WPBotCore\Keywords\Keyword_Data;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ideas.
 */
final class Keyword_Ideas {

	/**
	 * Seed from a page or a word.
	 *
	 * @param string $seed    Seed word.
	 * @param int    $post_id Page (its main search is the seed).
	 * @return string
	 */
	public static function seed( $seed, $post_id = 0 ) {
		if ( $post_id ) {
			$main = Audit::main_query( (int) $post_id );
			return '' !== $main ? $main : (string) get_the_title( (int) $post_id );
		}
		return sanitize_text_field( (string) $seed );
	}

	/**
	 * Estimate.
	 *
	 * @param string $seed Seed.
	 * @return array|WP_Error
	 */
	public static function estimate( $seed ) {
		if ( ! Volumes::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_keyword_data', __( 'Keyword data needs a newer WPBot Core. Update the plugin.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$place = Volumes::place();
		return Keyword_Data::research(
			'ideas',
			$seed,
			array(
				'country'       => $place['country'],
				'language'      => $place['language'],
				'limit'         => 50,
				'estimate_only' => true,
			)
		);
	}

	/**
	 * Ideas with "you already appear for this" from Search Console.
	 *
	 * @param string $seed Seed.
	 * @return array|WP_Error rows (keyword, volume, cpc, trend, potential, ranking, position), cost, cached.
	 */
	public static function ideas( $seed ) {
		if ( ! Volumes::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_keyword_data', __( 'Keyword data needs a newer WPBot Core. Update the plugin.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$place  = Volumes::place();
		$result = Keyword_Data::research(
			'ideas',
			$seed,
			array(
				'country'  => $place['country'],
				'language' => $place['language'],
				'limit'    => 50,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$known = self::site_queries();
		$rows  = array();
		foreach ( $result['rows'] as $row ) {
			$norm   = Keyword_Map::normalise( $row['keyword'] );
			$rows[] = array(
				'keyword'   => $row['keyword'],
				'volume'    => $row['volume'],
				'cpc'       => $row['cpc'],
				'trend'     => $row['trend'],
				'potential' => Volumes::potential_clicks( $row['volume'] ),
				'ranking'   => isset( $known[ $norm ] ),
				'position'  => isset( $known[ $norm ] ) ? $known[ $norm ] : null,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $b['volume'] <=> (int) $a['volume'];
			}
		);
		return array(
			'rows'   => $rows,
			'cost'   => $result['cost'],
			'cached' => $result['cached'],
		);
	}

	/**
	 * Add a keyword to AI Writer's Topic Finder ideas.
	 *
	 * @param string   $keyword Keyword.
	 * @param int|null $volume  Volume.
	 * @return array|WP_Error idea_id, url.
	 */
	public static function add_to_topic_finder( $keyword, $volume = null ) {
		if ( ! class_exists( '\QuantumCloud\AiWriter\Topics\Ideas' ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$keyword = sanitize_text_field( (string) $keyword );
		$id      = \QuantumCloud\AiWriter\Topics\Ideas::add(
			$keyword,
			'seo',
			array(
				'source_ref' => 'kw:' . md5( Keyword_Map::normalise( $keyword ) ),
				/* translators: %s: monthly searches. */
				'reason'     => null === $volume ? __( 'Keyword idea from SEO & AI Search.', 'chatbot' ) : sprintf( __( 'Keyword idea: about %s searches a month.', 'chatbot' ), number_format_i18n( (int) $volume ) ),
				'relevance'  => null === $volume ? 50 : min( 100, 30 + (int) round( log10( max( 1, (int) $volume ) ) * 15 ) ),
				'main_query' => $keyword,
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array(
			'idea_id' => (int) $id,
			'url'     => admin_url( 'admin.php?page=wpbot-ai-writer&tab=topics' ),
		);
	}

	/**
	 * Searches the site already appears for, with average position.
	 *
	 * @return array normalised => position.
	 */
	private static function site_queries() {
		$period = Period::last( 90 );
		$out    = array();
		foreach ( Data_Store::top( 'query', $period['start'], $period['end'], 2000, 'impressions' ) as $row ) {
			$out[ Keyword_Map::normalise( $row['label'] ) ] = round( (float) $row['position'], 1 );
		}
		return $out;
	}
}
