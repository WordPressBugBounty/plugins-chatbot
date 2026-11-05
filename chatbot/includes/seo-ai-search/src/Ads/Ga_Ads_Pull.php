<?php
/**
 * Google Ads data through the linked Google Analytics 4 property (S13,
 * Free, no extra setup): the last 30 days of paid searches.
 *
 * Two GA4 Data API reports:
 * 1. sessionGoogleAdsQuery with advertiserAdClicks, advertiserAdImpressions
 *    and advertiserAdCost (needs Google Ads linked to the property, with
 *    cost data imported);
 * 2. sessionGoogleAdsQuery and landingPagePlusQueryString with sessions
 *    and keyEvents (conversions).
 * When GA4 refuses the first report (cost data not available for this
 * property), the searches are still imported without cost.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Ads;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\WPBotCore\Connections\Analytics;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * GA4 paid search import.
 */
final class Ga_Ads_Pull {

	const STEP  = 'seo_ads_ga';
	const STATE = 'qcld_wpbot_seo_ads_state';
	const DAYS  = 30;
	const ROWS  = 2000;

	/**
	 * Hooks: daily import when Analytics is connected.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'daily' ) );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( self::STEP => array( __CLASS__, 'step' ) );
	}

	/**
	 * Daily: import when Analytics is connected.
	 *
	 * @return void
	 */
	public static function daily() {
		if ( 'connected' === Analytics::status()['status'] ) {
			self::start();
		}
	}

	/**
	 * Start an import.
	 *
	 * @return int|WP_Error Job id.
	 */
	public static function start() {
		$status = Analytics::status();
		if ( 'connected' !== $status['status'] ) {
			return new WP_Error( 'qcld_wpbot_seo_ads_ga', __( 'Google Ads data comes through Google Analytics: connect Analytics in Settings & AI Provider > Connections, and link Google Ads to that Analytics property.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job = (int) Job_Store::create(
			Module::ID,
			'ads_ga',
			array( 'title' => __( 'Import paid searches (Google Ads via Analytics)', 'chatbot' ) ),
			array(
				array(
					'kind'  => self::STEP,
					'label' => __( 'Paid searches, last 30 days', 'chatbot' ),
					'input' => array(),
				),
			)
		);
		return $job ? $job : new WP_Error( 'qcld_wpbot_seo_job_failed', __( 'The import could not be saved to the database. Try again.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * Step: both reports, then the snapshot.
	 *
	 * @return array|WP_Error
	 */
	public static function step() {
		$end    = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) );
		$start  = gmdate( 'Y-m-d', strtotime( $end . ' -' . ( self::DAYS - 1 ) . ' days' ) );
		$ranges = array(
			array(
				'startDate' => $start,
				'endDate'   => $end,
			),
		);
		$filter = array(
			'notExpression' => array(
				'filter' => array(
					'fieldName'    => 'sessionGoogleAdsQuery',
					'stringFilter' => array(
						'matchType' => 'EXACT',
						'value'     => '(not set)',
					),
				),
			),
		);
		$cost   = Analytics::report(
			array(
				'dateRanges'      => $ranges,
				'dimensions'      => array( array( 'name' => 'sessionGoogleAdsQuery' ) ),
				'metrics'         => array(
					array( 'name' => 'advertiserAdClicks' ),
					array( 'name' => 'advertiserAdImpressions' ),
					array( 'name' => 'advertiserAdCost' ),
				),
				'dimensionFilter' => $filter,
				'limit'           => self::ROWS,
			)
		);
		$pages  = Analytics::report(
			array(
				'dateRanges'      => $ranges,
				'dimensions'      => array(
					array( 'name' => 'sessionGoogleAdsQuery' ),
					array( 'name' => 'landingPagePlusQueryString' ),
				),
				'metrics'         => array(
					array( 'name' => 'sessions' ),
					array( 'name' => 'keyEvents' ),
				),
				'dimensionFilter' => $filter,
				'limit'           => self::ROWS,
			)
		);
		if ( is_wp_error( $pages ) && is_wp_error( $cost ) ) {
			self::save_state( array( 'error' => $pages->get_error_message() ) );
			return $pages;
		}
		$rows = array();
		if ( ! is_wp_error( $cost ) ) {
			foreach ( $cost['rows'] as $row ) {
				$rows[] = array(
					'query'       => (string) $row[0],
					'landing'     => '',
					'clicks'      => (int) $row[1],
					'impressions' => (int) $row[2],
					'cost'        => (float) $row[3],
				);
			}
		}
		$known = wp_list_pluck( $rows, 'query' );
		if ( ! is_wp_error( $pages ) ) {
			foreach ( $pages['rows'] as $row ) {
				if ( ! in_array( (string) $row[0], $known, true ) ) {
					// A search seen only in sessions (no cost report): keep a search row without cost.
					$rows[]  = array(
						'query'       => (string) $row[0],
						'landing'     => '',
						'clicks'      => (int) $row[2],
						'impressions' => 0,
						'cost'        => null,
					);
					$known[] = (string) $row[0];
				}
				$rows[] = array(
					'query'       => (string) $row[0],
					'landing'     => (string) $row[1],
					'sessions'    => (int) $row[2],
					'conversions' => (float) $row[3],
				);
			}
		}
		$stored = Ads_Data::replace(
			'ga4',
			$rows,
			array(
				'start' => $start,
				'end'   => $end,
			)
		);
		self::save_state(
			array(
				'error'     => '',
				'no_cost'   => is_wp_error( $cost ) ? $cost->get_error_message() : '',
				'pulled_at' => gmdate( 'c' ),
				'rows'      => $stored,
			)
		);
		return array( 'rows' => $stored );
	}

	/**
	 * Import state (last run, errors, cost availability).
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Save the state.
	 *
	 * @param array $changes Changes.
	 * @return void
	 */
	private static function save_state( array $changes ) {
		update_option( self::STATE, array_merge( self::state(), $changes ), false );
	}
}
