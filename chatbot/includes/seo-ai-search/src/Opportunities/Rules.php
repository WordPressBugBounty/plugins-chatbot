<?php
/**
 * Opportunity rules (Free): pure functions over Search Console and
 * Analytics aggregates, so they are unit-tested without WordPress.
 *
 * Each rule returns candidates: type, ref, url_id, query_id, url, query,
 * potential (clicks a month that could be won), effort (1 low – 3 high),
 * numbers (shown to the owner) and action.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

defined( 'ABSPATH' ) || exit;

/**
 * The built-in rules.
 */
final class Rules {

	/**
	 * Pages ranking just below page one for a search (positions 8–20).
	 *
	 * @param array $rows   page x query rows for the period.
	 * @param array $config near_min_position, near_max_position, min_impressions.
	 * @return array Candidates.
	 */
	public static function near_page_one( array $rows, array $config ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( $row['impressions'] < $config['min_impressions'] || $row['position'] < $config['near_min_position'] || $row['position'] > $config['near_max_position'] ) {
				continue;
			}
			// Clicks it would get near the top of page one (position 3).
			$potential = max( 0, $row['impressions'] * ( Ctr_Curve::expected( 3 ) - $row['ctr'] ) );
			$out[]     = self::candidate( 'near_page_one', $row, $potential, 2, 'improve' );
		}
		return $out;
	}

	/**
	 * Pages on page one with far fewer clicks than their position usually gets.
	 *
	 * @param array $rows   page rows for the period.
	 * @param array $config min_impressions.
	 * @return array Candidates.
	 */
	public static function low_ctr( array $rows, array $config ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( $row['impressions'] < $config['min_impressions'] || $row['position'] <= 0 || $row['position'] > 10 ) {
				continue;
			}
			$expected = Ctr_Curve::expected( $row['position'] );
			if ( $row['ctr'] >= 0.5 * $expected ) {
				continue;
			}
			$candidate                            = self::candidate( 'low_ctr', $row, $row['impressions'] * ( $expected - $row['ctr'] ), 1, 'rewrite_meta' );
			$candidate['numbers']['expected_ctr'] = $expected;
			$out[]                                = $candidate;
		}
		return $out;
	}

	/**
	 * Searches with impressions where no page ranks on the first two pages.
	 *
	 * @param array $query_rows Query rows for the period.
	 * @param array $page_query page x query rows (to find the best page per query).
	 * @param array $config     min_impressions.
	 * @return array Candidates.
	 */
	public static function missing_page( array $query_rows, array $page_query, array $config ) {
		$best = array();
		foreach ( $page_query as $row ) {
			$id = $row['query_id'];
			if ( ! isset( $best[ $id ] ) || $row['position'] < $best[ $id ] ) {
				$best[ $id ] = $row['position'];
			}
		}
		$out = array();
		foreach ( $query_rows as $row ) {
			$position = isset( $best[ $row['query_id'] ] ) ? $best[ $row['query_id'] ] : $row['position'];
			if ( $row['impressions'] < $config['min_impressions'] || $position <= 20 ) {
				continue;
			}
			$candidate                             = self::candidate( 'missing_page', $row, $row['impressions'] * Ctr_Curve::expected( 5 ), 3, 'write' );
			$candidate['url_id']                   = 0;
			$candidate['url']                      = '';
			$candidate['ref']                      = 'missing_page:' . $row['query_id'];
			$candidate['numbers']['best_position'] = round( $position, 1 );
			$out[]                                 = $candidate;
		}
		return $out;
	}

	/**
	 * Pages whose clicks fell over the period versus the period before.
	 *
	 * @param array $now    page rows, this period.
	 * @param array $before page rows, previous period (same length).
	 * @param array $config decay_drop_percent.
	 * @return array Candidates.
	 */
	public static function decaying( array $now, array $before, array $config ) {
		$current = array();
		foreach ( $now as $row ) {
			$current[ $row['url_id'] ] = $row;
		}
		$out = array();
		foreach ( $before as $row ) {
			if ( $row['clicks'] < 20 ) {
				continue;
			}
			$after = isset( $current[ $row['url_id'] ] ) ? $current[ $row['url_id'] ]['clicks'] : 0;
			$drop  = ( $row['clicks'] - $after ) / $row['clicks'];
			if ( $drop * 100 < $config['decay_drop_percent'] ) {
				continue;
			}
			$base                                  = isset( $current[ $row['url_id'] ] ) ? $current[ $row['url_id'] ] : array_merge( $row, array( 'clicks' => 0 ) );
			$candidate                             = self::candidate( 'decaying', $base, $row['clicks'] - $after, 2, 'refresh' );
			$candidate['numbers']['clicks_before'] = $row['clicks'];
			$candidate['numbers']['clicks']        = $after;
			$candidate['numbers']['drop']          = round( $drop, 3 );
			$out[]                                 = $candidate;
		}
		return $out;
	}

	/**
	 * Pages with search traffic but low engagement compared with the site.
	 *
	 * @param array $page_rows page rows for the period.
	 * @param array $ga        url_id => sessions, engaged_sessions, conversions.
	 * @return array Candidates.
	 */
	public static function poor_engagement( array $page_rows, array $ga ) {
		$sessions = 0;
		$engaged  = 0;
		foreach ( $ga as $row ) {
			$sessions += $row['sessions'];
			$engaged  += $row['engaged_sessions'];
		}
		if ( $sessions < 100 ) {
			return array();
		}
		$site_rate = $engaged / $sessions;
		$out       = array();
		foreach ( $page_rows as $row ) {
			if ( empty( $ga[ $row['url_id'] ] ) || $row['clicks'] < 10 ) {
				continue;
			}
			$page = $ga[ $row['url_id'] ];
			if ( $page['sessions'] < 30 ) {
				continue;
			}
			$rate = $page['engaged_sessions'] / $page['sessions'];
			if ( $rate >= 0.6 * $site_rate ) {
				continue;
			}
			$candidate                               = self::candidate( 'poor_engagement', $row, $row['clicks'] * ( $site_rate - $rate ), 2, 'improve' );
			$candidate['numbers']['engagement_rate'] = round( $rate, 3 );
			$candidate['numbers']['site_rate']       = round( $site_rate, 3 );
			$candidate['numbers']['sessions']        = $page['sessions'];
			$candidate['numbers']['conversions']     = $page['conversions'];
			$out[]                                   = $candidate;
		}
		return $out;
	}

	/**
	 * Rank score: potential clicks, lighter work first, business relevance.
	 *
	 * @param float $potential Clicks that could be won.
	 * @param int   $effort    1 (low) to 3 (high).
	 * @param float $relevance 0–1 (1 = core business topic).
	 * @return float
	 */
	public static function score( $potential, $effort, $relevance = 1.0 ) {
		$effort = max( 1, min( 3, (int) $effort ) );
		return round( (float) $potential * max( 0.1, (float) $relevance ) / ( 0.5 + 0.5 * $effort ), 2 );
	}

	/**
	 * Common candidate shape.
	 *
	 * @param string $type      Type.
	 * @param array  $row       Data row.
	 * @param float  $potential Potential clicks.
	 * @param int    $effort    Effort.
	 * @param string $action    Main action.
	 * @return array
	 */
	private static function candidate( $type, array $row, $potential, $effort, $action ) {
		return array(
			'type'      => $type,
			'ref'       => $type . ':' . $row['url_id'] . ':' . $row['query_id'],
			'url_id'    => (int) $row['url_id'],
			'query_id'  => (int) $row['query_id'],
			'url'       => (string) $row['url'],
			'query'     => (string) $row['query'],
			'potential' => round( max( 0, (float) $potential ), 1 ),
			'effort'    => (int) $effort,
			'action'    => $action,
			'numbers'   => array(
				'clicks'      => (int) $row['clicks'],
				'impressions' => (int) $row['impressions'],
				'ctr'         => (float) $row['ctr'],
				'position'    => (float) $row['position'],
			),
		);
	}
}
