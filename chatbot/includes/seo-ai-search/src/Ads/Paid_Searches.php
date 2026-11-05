<?php
/**
 * What paid searches tell the SEO side (S13).
 *
 * - Save on ads (opportunity): a paid search where the site already ranks
 *   in the top 3 organically (test a lower bid or pausing, in Google Ads),
 *   or a converting paid search where the page ranks on page 2 (improve the
 *   page to win the click for free). Each with an estimated monthly saving
 *   and the assumptions behind it.
 * - Paid search with no ranking page (opportunity "Write a page"):
 *   people search this and buy, and no page of the site ranks for it.
 * - Converting paid searches go to the Keyword Vault (source Google Ads);
 *   paid searches become supporting searches of the pages they land on;
 *   paid searches with no page and new terms that show up in ads first
 *   feed AI Writer's Topic Finder.
 *
 * This module never changes anything in Google Ads.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Ads;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Opportunities\Opportunities;
use QuantumCloud\SeoAiSearch\Research\Research_View;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Paid search insights.
 */
final class Paid_Searches {

	const SEEN = 'qcld_wpbot_seo_ads_seen';

	/**
	 * Share of paid clicks assumed to come through the organic result when
	 * the site is already in the top 3 and the ad is paused or bid down.
	 */
	const TOP3_SHARE = 0.5;

	/**
	 * Share of paid clicks assumed to come free once a page-2 page reaches the top 3.
	 */
	const PAGE2_SHARE = 0.3;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/ads_pulled', array( __CLASS__, 'after_pull' ) );
		add_filter( 'qcld_wpbot_seo/opportunity_types', array( __CLASS__, 'types' ) );
		add_filter( 'qcld_wpbot_seo/opportunity_reason', array( __CLASS__, 'reason' ), 10, 3 );
		add_filter( 'qcld_wpbot_writer/idea_sources', array( __CLASS__, 'idea_source' ) );
		add_filter( 'qcld_wpbot_seo/ads_connected', array( Ads_Data::class, 'has_data' ) );
	}

	/**
	 * Paid searches with their organic position and ranking page.
	 *
	 * @return array[] Ads_Data::searches() rows plus position, page, new.
	 */
	public static function insights() {
		$organic = array();
		foreach ( Data_Store::period_rows( 'query', gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ), 1, 20000 ) as $row ) {
			$organic[ Keyword_Map::normalise( $row['query'] ) ] = round( (float) $row['position'], 1 );
		}
		$seen = self::seen();
		$out  = array();
		foreach ( Ads_Data::searches() as $row ) {
			$key             = Keyword_Map::normalise( $row['query'] );
			$row['position'] = isset( $organic[ $key ] ) ? $organic[ $key ] : null;
			$row['page']     = Research_View::page_for( $key );
			$row['new']      = isset( $seen[ md5( $key ) ] ) && $seen[ md5( $key ) ] > time() - 14 * DAY_IN_SECONDS;
			$out[]           = $row;
		}
		return $out;
	}

	/**
	 * After an import: Vault, supporting searches, new-term tracking,
	 * opportunities.
	 *
	 * @return void
	 */
	public static function after_pull() {
		$rows = self::insights();
		self::remember( $rows );
		foreach ( $rows as $row ) {
			if ( $row['conversions'] > 0 && function_exists( 'qcld_wpbot_core_vault_add' ) ) {
				qcld_wpbot_core_vault_add(
					array(
						'keyword' => $row['query'],
						'source'  => 'google_ads',
						'module'  => 'seo',
						'context' => array(
							/* translators: 1: clicks, 2: conversions, 3: source. */
							'reason' => sprintf( __( 'Paid search: %1$d clicks, %2$s conversions in 30 days (%3$s).', 'chatbot' ), $row['clicks'], number_format_i18n( $row['conversions'], 1 ), Ads_Data::label( $row['source'] ) ),
						),
					)
				);
			}
			foreach ( $row['landing'] as $post_id ) {
				self::add_supporting( (int) $post_id, $row['query'] );
			}
		}
		Opportunities::rebuild();
	}

	/**
	 * Add a paid search as a supporting search of a page, unless the owner
	 * set the page's searches by hand.
	 *
	 * @param int    $post_id Post id.
	 * @param string $query   Paid search.
	 * @return bool
	 */
	public static function add_supporting( $post_id, $query ) {
		$main = Keyword_Map::main_query( $post_id );
		if ( ! $main || 'manual' === $main['source'] || ! empty( $main['locked'] ) || Keyword_Map::normalise( $query ) === $main['query'] ) {
			return false;
		}
		$supporting = array();
		foreach ( Keyword_Map::entries(
			array(
				'post_id' => $post_id,
				'role'    => 'supporting',
			)
		) as $entry ) {
			$supporting[] = $entry['query'];
		}
		if ( in_array( Keyword_Map::normalise( $query ), $supporting, true ) ) {
			return false;
		}
		$supporting[] = $query;
		return Keyword_Map::set_post(
			$post_id,
			$main['query'],
			array(
				'source'     => $main['source'],
				'topic_id'   => (int) $main['topic_id'],
				'module'     => $main['module'],
				'supporting' => array_slice( $supporting, -5 ),
			)
		);
	}

	/**
	 * Opportunity types.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function types( $types ) {
		$types['save_on_ads'] = array(
			'label'    => __( 'Save on ads', 'chatbot' ),
			'callback' => array( __CLASS__, 'save_on_ads' ),
			'action'   => 'improve',
		);
		$types['paid_gap']    = array(
			'label'    => __( 'Paid search with no ranking page', 'chatbot' ),
			'callback' => array( __CLASS__, 'paid_gap' ),
			'action'   => 'write',
		);
		return $types;
	}

	/**
	 * Save on ads candidates with their estimate and assumptions.
	 *
	 * @return array[]
	 */
	public static function save_on_ads() {
		$out = array();
		foreach ( self::insights() as $row ) {
			if ( null === $row['position'] || null === $row['cost'] || $row['cost'] <= 0 ) {
				continue;
			}
			$top3  = $row['position'] <= 3;
			$page2 = $row['position'] > 10 && $row['position'] <= 20 && $row['conversions'] > 0;
			if ( ! $top3 && ! $page2 ) {
				continue;
			}
			$share   = $top3 ? self::TOP3_SHARE : self::PAGE2_SHARE;
			$monthly = round( $row['cost'] * $share * 30 / max( 1, self::days( $row['period'] ) ), 2 );
			$page    = $row['page'] ? (int) $row['page']['id'] : ( $row['landing'] ? (int) $row['landing'][0] : 0 );
			$out[]   = array(
				'type'      => 'save_on_ads',
				'ref'       => 'save_on_ads:' . md5( $row['query'] ),
				'post_id'   => $page,
				'url'       => $page ? (string) get_permalink( $page ) : '',
				'query'     => $row['query'],
				'potential' => round( $monthly, 1 ),
				'effort'    => $top3 ? 1 : 3,
				'action'    => $top3 ? 'review_ads' : 'improve',
				'numbers'   => array(
					'case'        => $top3 ? 'top3' : 'page2',
					'position'    => $row['position'],
					'cost'        => $row['cost'],
					'clicks'      => $row['clicks'],
					'conversions' => $row['conversions'],
					'currency'    => $row['currency'],
					'saving'      => $monthly,
					'share'       => $share,
					'source'      => Ads_Data::label( $row['source'] ),
				),
			);
		}
		return $out;
	}

	/**
	 * Converting paid searches with no ranking page.
	 *
	 * @return array[]
	 */
	public static function paid_gap() {
		$out = array();
		foreach ( self::insights() as $row ) {
			if ( $row['conversions'] <= 0 || $row['page'] || ( null !== $row['position'] && $row['position'] <= 20 ) ) {
				continue;
			}
			$out[] = array(
				'type'      => 'paid_gap',
				'ref'       => 'paid_gap:' . md5( $row['query'] ),
				'query'     => $row['query'],
				'potential' => round( 5 + $row['conversions'] * 5 + $row['clicks'] * 0.1, 1 ),
				'effort'    => 3,
				'action'    => 'write',
				'numbers'   => array(
					'clicks'      => $row['clicks'],
					'conversions' => $row['conversions'],
					'cost'        => $row['cost'],
					'currency'    => $row['currency'],
					'source'      => Ads_Data::label( $row['source'] ),
				),
			);
		}
		return $out;
	}

	/**
	 * Reasons with the assumptions.
	 *
	 * @param string $reason  Reason.
	 * @param string $type    Type.
	 * @param array  $numbers Numbers.
	 * @return string
	 */
	public static function reason( $reason, $type, $numbers ) {
		if ( 'save_on_ads' === $type ) {
			$cost = self::money( $numbers['cost'], $numbers['currency'] );
			$save = self::money( $numbers['saving'], $numbers['currency'] );
			if ( 'top3' === $numbers['case'] ) {
				/* translators: 1: search, 2: organic position, 3: ad cost, 4: source, 5: saving, 6: share in percent. */
				return sprintf( __( 'You pay for ads on "%1$s" but already rank at position %2$s organically. Ad cost: %3$s in 30 days (%4$s). Estimated saving: about %5$s a month, assuming %6$d%% of the paid clicks would come through your organic result if you lower the bid or pause the ad. Test this in Google Ads; this module never changes your ads.', 'chatbot' ), $numbers['query'], $numbers['position'], $cost, $numbers['source'], $save, (int) round( $numbers['share'] * 100 ) );
			}
			/* translators: 1: search, 2: conversions, 3: organic position, 4: ad cost, 5: source, 6: saving, 7: share in percent. */
			return sprintf( __( '"%1$s" brings %2$s conversions from ads, but your page ranks at position %3$s (page 2). Ad cost: %4$s in 30 days (%5$s). Improve the page to win the click for free: about %6$s a month, assuming %7$d%% of the paid clicks come free once you reach the top 3.', 'chatbot' ), $numbers['query'], number_format_i18n( (float) $numbers['conversions'], 1 ), $numbers['position'], $cost, $numbers['source'], $save, (int) round( $numbers['share'] * 100 ) );
		}
		if ( 'paid_gap' === $type ) {
			/* translators: 1: search, 2: clicks, 3: conversions, 4: source. */
			return sprintf( __( 'People search "%1$s" and buy: %2$d ad clicks and %3$s conversions in 30 days (%4$s). You have no page that ranks for it.', 'chatbot' ), $numbers['query'], (int) $numbers['clicks'], number_format_i18n( (float) $numbers['conversions'], 1 ), $numbers['source'] );
		}
		return $reason;
	}

	/**
	 * AI Writer Topic Finder source: converting paid searches without a page
	 * and new terms that show up in ads first.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function idea_source( $sources ) {
		$sources['seo_paid_searches'] = array(
			'label'           => __( 'Paid searches (Google Ads)', 'chatbot' ),
			'description'     => __( 'Searches people click in your ads and buy from, with no page of yours ranking, and new searches that show up in ads first.', 'chatbot' ),
			'callback'        => array( __CLASS__, 'ideas' ),
			'status_callback' => array( __CLASS__, 'idea_status' ),
		);
		return $sources;
	}

	/**
	 * Ideas for Topic Finder.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function ideas( $context = array() ) {
		unset( $context );
		$ideas = array();
		foreach ( self::insights() as $row ) {
			$no_page = ! $row['page'] && ( null === $row['position'] || $row['position'] > 20 );
			if ( ! ( ( $row['conversions'] > 0 && $no_page ) || ( $row['new'] && $no_page ) ) ) {
				continue;
			}
			$ideas[] = array(
				'title'      => $row['query'],
				'source_ref' => 'paid:' . md5( $row['query'] ),
				/* translators: 1: clicks, 2: conversions. */
				'reason'     => $row['conversions'] > 0 ? sprintf( __( 'Paid search that converts (%1$d clicks, %2$s conversions in 30 days) and no page of yours ranks.', 'chatbot' ), $row['clicks'], number_format_i18n( $row['conversions'], 1 ) ) : __( 'New search that showed up in your ads first.', 'chatbot' ),
				'relevance'  => min( 100, 50 + (int) round( $row['conversions'] * 8 ) ),
				'main_query' => $row['query'],
			);
		}
		return array_slice( $ideas, 0, 30 );
	}

	/**
	 * Topic Finder status of the source.
	 *
	 * @return array ready, message.
	 */
	public static function idea_status() {
		$ready = Ads_Data::has_data();
		return array(
			'ready'   => $ready,
			'message' => $ready ? '' : __( 'Link Google Ads to Google Analytics (or connect the Google Ads API in Pro) to get these ideas.', 'chatbot' ),
		);
	}

	/**
	 * Remember when each paid search was first seen (to spot new terms).
	 *
	 * @param array[] $rows Paid searches.
	 * @return void
	 */
	private static function remember( array $rows ) {
		$seen  = self::seen();
		$first = array() === $seen;
		foreach ( $rows as $row ) {
			$key = md5( Keyword_Map::normalise( $row['query'] ) );
			if ( ! isset( $seen[ $key ] ) ) {
				// The very first import sets the baseline: nothing counts as new yet.
				$seen[ $key ] = $first ? 1 : time();
			}
		}
		update_option( self::SEEN, array_slice( $seen, -5000, null, true ), false );
	}

	/**
	 * First-seen times.
	 *
	 * @return array hash => timestamp.
	 */
	private static function seen() {
		$seen = get_option( self::SEEN, array() );
		return is_array( $seen ) ? $seen : array();
	}

	/**
	 * Days in a period.
	 *
	 * @param array $period start, end.
	 * @return int
	 */
	private static function days( array $period ) {
		if ( empty( $period['start'] ) || empty( $period['end'] ) ) {
			return 30;
		}
		return max( 1, (int) round( ( strtotime( $period['end'] ) - strtotime( $period['start'] ) ) / DAY_IN_SECONDS ) + 1 );
	}

	/**
	 * Amount with its currency.
	 *
	 * @param float  $value    Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	private static function money( $value, $currency ) {
		return trim( number_format_i18n( (float) $value, 2 ) . ' ' . (string) $currency );
	}
}
