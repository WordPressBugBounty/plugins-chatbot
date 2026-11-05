<?php
/**
 * Pages competing for the same search: from Search Console (two or more
 * pages each getting a real share of a search's impressions) and from the
 * keyword map (two pages with the same main search). Each case has a
 * stronger page and fixes for the others: target another search, link to
 * the stronger page, or merge and redirect.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Keywords;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Onpage\Link_Suggestions;
use QuantumCloud\SeoAiSearch\Technical\Redirects;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Cannibalization.
 */
final class Cannibalization {

	/**
	 * Least share of a search's impressions for a page to count as competing.
	 */
	const MIN_SHARE = 0.15;

	/**
	 * Least impressions of the search overall.
	 */
	const MIN_TOTAL = 50;

	/**
	 * Competing pages from page×search rows (pure).
	 *
	 * @param array $rows Rows: url_id, url, query, clicks, impressions, position.
	 * @return array List of query, impressions, strong (url_id), pages[].
	 */
	public static function detect( array $rows ) {
		$by_query = array();
		foreach ( $rows as $row ) {
			$by_query[ (string) $row['query'] ][] = $row;
		}
		$out = array();
		foreach ( $by_query as $query => $pages ) {
			$total = array_sum( array_column( $pages, 'impressions' ) );
			if ( $total < self::MIN_TOTAL || count( $pages ) < 2 ) {
				continue;
			}
			$competing = array();
			foreach ( $pages as $page ) {
				$share = (int) $page['impressions'] / $total;
				if ( $share >= self::MIN_SHARE && (int) $page['impressions'] >= 10 ) {
					$competing[] = array(
						'url_id'      => (int) $page['url_id'],
						'url'         => (string) $page['url'],
						'clicks'      => (int) $page['clicks'],
						'impressions' => (int) $page['impressions'],
						'position'    => round( (float) $page['position'], 1 ),
						'share'       => round( $share, 2 ),
					);
				}
			}
			if ( count( $competing ) < 2 ) {
				continue;
			}
			usort(
				$competing,
				static function ( $a, $b ) {
					return array( $b['clicks'], -$b['position'] ) <=> array( $a['clicks'], -$a['position'] );
				}
			);
			$out[] = array(
				'query'       => (string) $query,
				'impressions' => (int) $total,
				'strong'      => $competing[0]['url_id'],
				'pages'       => $competing,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['impressions'] <=> $a['impressions'];
			}
		);
		return $out;
	}

	/**
	 * Cases for the site: Search Console (28 days) plus same main search in the map.
	 *
	 * @return array
	 */
	public static function cases() {
		$period = Period::last( 28 );
		$cases  = self::detect( Data_Store::period_rows( 'page_query', $period['start'], $period['end'], 10, 20000 ) );
		foreach ( $cases as $index => $case ) {
			foreach ( $case['pages'] as $p => $page ) {
				$post_id                                   = Data_Store::post_for_url( $page['url_id'] );
				$cases[ $index ]['pages'][ $p ]['post_id'] = $post_id;
				$cases[ $index ]['pages'][ $p ]['title']   = $post_id ? get_the_title( $post_id ) : '';
				$cases[ $index ]['pages'][ $p ]['strong']  = $page['url_id'] === $case['strong'];
			}
			$cases[ $index ]['source'] = 'search_console';
		}
		if ( Map_Sync::available() ) {
			$seen = array_flip( array_column( $cases, 'query' ) );
			$main = array();
			foreach ( Keyword_Map::entries( array( 'role' => 'main' ), 5000 ) as $entry ) {
				if ( ! empty( $entry['post_id'] ) && 'publish' === get_post_status( (int) $entry['post_id'] ) ) {
					$main[ $entry['query'] ][] = (int) $entry['post_id'];
				}
			}
			foreach ( $main as $query => $posts ) {
				if ( count( $posts ) < 2 || isset( $seen[ $query ] ) ) {
					continue;
				}
				$pages = array();
				foreach ( $posts as $i => $post_id ) {
					$pages[] = array(
						'post_id' => $post_id,
						'url_id'  => 0,
						'url'     => (string) get_permalink( $post_id ),
						'title'   => get_the_title( $post_id ),
						'clicks'  => 0,
						'strong'  => 0 === $i,
					);
				}
				$cases[] = array(
					'query'       => (string) $query,
					'impressions' => 0,
					'strong'      => 0,
					'pages'       => $pages,
					'source'      => 'map',
				);
			}
		}
		return $cases;
	}

	/**
	 * Opportunity type "Competing pages" (one per search, on the weaker page).
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function opportunity_type( $types ) {
		$types['competing_pages'] = array(
			'label'    => __( 'Competing pages', 'chatbot' ),
			'callback' => static function ( $context ) {
				$out = array();
				foreach ( self::detect( isset( $context['page_query'] ) ? (array) $context['page_query'] : array() ) as $case ) {
					$weak  = $case['pages'][1];
					$out[] = array(
						'type'      => 'competing_pages',
						'ref'       => 'competing_pages:' . md5( $case['query'] ),
						'url_id'    => $weak['url_id'],
						'query_id'  => 0,
						'url'       => $weak['url'],
						'query'     => $case['query'],
						'potential' => round( $weak['impressions'] * 0.03, 1 ),
						'effort'    => 2,
						'action'    => 'improve',
						'numbers'   => array(
							'pages'       => count( $case['pages'] ),
							'impressions' => $case['impressions'],
							'stronger'    => $case['pages'][0]['url'],
						),
					);
				}
				return $out;
			},
			'action'   => 'improve',
		);
		return $types;
	}

	/**
	 * Reason text for the opportunity.
	 *
	 * @param string $reason  Reason.
	 * @param string $type    Type.
	 * @param array  $numbers Numbers.
	 * @return string
	 */
	public static function reason( $reason, $type, $numbers ) {
		if ( 'competing_pages' !== $type ) {
			return $reason;
		}
		/* translators: 1: number of pages, 2: search, 3: stronger page. */
		return sprintf( __( '%1$d of your pages compete for "%2$s", so Google splits the traffic. Make %3$s the page for it.', 'chatbot' ), (int) $numbers['pages'], isset( $numbers['query'] ) ? $numbers['query'] : '', isset( $numbers['stronger'] ) ? $numbers['stronger'] : '' );
	}

	/**
	 * Apply a fix to a weaker page.
	 *
	 * @param string $fix     retarget | link | merge.
	 * @param int    $weak    Weaker post.
	 * @param int    $strong  Stronger post.
	 * @param string $query   Search.
	 * @param string $target  New main search (retarget).
	 * @return array|WP_Error done, url (merge opens AI Writer).
	 */
	public static function fix( $fix, $weak, $strong, $query, $target = '' ) {
		if ( ! current_user_can( 'edit_post', $weak ) || ! get_post( $strong ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( 'retarget' === $fix ) {
			$done = Map_Sync::set_manual( $weak, $target, array(), true );
			return is_wp_error( $done ) ? $done : array( 'done' => 'retarget' );
		}
		if ( 'link' === $fix ) {
			$change = Link_Suggestions::add_link( $weak, $strong, $query, 'fix' );
			return is_wp_error( $change ) ? $change : array( 'done' => 'link' );
		}
		if ( 'merge' === $fix ) {
			$from = (string) wp_parse_url( (string) get_permalink( $weak ), PHP_URL_PATH );
			$to   = (string) get_permalink( $strong );
			$id   = Redirects::save( $from, $to, 301 );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			wp_update_post(
				array(
					'ID'          => $weak,
					'post_status' => 'draft',
				)
			);
			$url = Writer_Bridge::available() && function_exists( 'qcld_wpbot_writer_open_brief_url' ) ? (string) qcld_wpbot_writer_open_brief_url(
				array(
					'mode'          => 'improve',
					'post_id'       => $strong,
					'main_query'    => $query,
					/* translators: %s: merged page title. */
					'fix'           => sprintf( __( 'Merge the useful parts of "%s" into this page; that page now redirects here.', 'chatbot' ), get_the_title( $weak ) ),
					'source_module' => \QuantumCloud\SeoAiSearch\Module::ID,
				)
			) : '';
			return array(
				'done' => 'merge',
				'url'  => $url,
			);
		}
		return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Unknown fix.', 'chatbot' ), array( 'status' => 400 ) );
	}
}
