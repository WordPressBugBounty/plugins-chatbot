<?php
/**
 * Google Analytics 4 pulls: sessions, engaged sessions, key events
 * (conversions) and revenue per landing page and day.
 *
 * GA4's daily token quota is tight, so pulls are small: the last 90 days
 * once, then the last 3 days every night, one week per job step.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Google;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\WPBotCore\Connections\Analytics;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and runs Analytics pull jobs.
 */
final class Ga_Pull {

	/**
	 * Option with the pull state.
	 */
	const STATE = 'qcld_wpbot_seo_ga_state';

	/**
	 * Rows per request.
	 */
	const ROWS = 10000;

	/**
	 * Hook the daily maintenance.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'daily' ) );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array<string,callable>
	 */
	public static function handlers() {
		return array( 'seo_ga_rows' => array( __CLASS__, 'step_rows' ) );
	}

	/**
	 * Nightly: first 90 days, then the last 3.
	 *
	 * @return void
	 */
	public static function daily() {
		if ( 'connected' === Analytics::status()['status'] && ! self::running() ) {
			$state = self::state();
			self::start( empty( $state['backfilled'] ) ? 90 : 3 );
		}
	}

	/**
	 * Start a pull of the last N days.
	 *
	 * @param int $days Days.
	 * @return int|WP_Error Job id.
	 */
	public static function start( $days = 90 ) {
		$status = Analytics::status();
		if ( 'connected' !== $status['status'] ) {
			return new WP_Error( 'qcld_wpbot_seo_ga_' . $status['status'], $status['message'], array( 'status' => 400 ) );
		}
		$running = self::running();
		if ( $running ) {
			return $running;
		}
		$end    = new \DateTimeImmutable( current_time( 'Y-m-d' ) . ' -1 day' );
		$start  = $end->modify( '-' . ( max( 1, (int) $days ) - 1 ) . ' days' );
		$steps  = array();
		$cursor = $end;
		while ( $cursor >= $start ) {
			$from    = $cursor->modify( '-6 days' );
			$from    = $from < $start ? $start : $from;
			$steps[] = array(
				'kind'  => 'seo_ga_rows',
				/* translators: 1: first day, 2: last day. */
				'label' => sprintf( __( 'Analytics, %1$s to %2$s', 'chatbot' ), $from->format( 'Y-m-d' ), $cursor->format( 'Y-m-d' ) ),
				'input' => array(
					'start'  => $from->format( 'Y-m-d' ),
					'end'    => $cursor->format( 'Y-m-d' ),
					'offset' => 0,
				),
			);
			$cursor  = $from->modify( '-1 day' );
		}
		$job = Job_Store::create(
			Module::ID,
			'ga_pull',
			array(
				'title'   => __( 'Import Google Analytics data', 'chatbot' ),
				'payload' => array( 'days' => (int) $days ),
			),
			$steps
		);
		if ( ! $job ) {
			return new WP_Error( 'qcld_wpbot_seo_job', __( 'The import could not be started.', 'chatbot' ) );
		}
		update_option( self::STATE, array_merge( self::state(), array( 'job_id' => (int) $job ) ), false );
		return (int) $job;
	}

	/**
	 * Step: one window of landing-page rows.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step_rows( array $step, array $job ) {
		$input  = (array) $step['input'];
		$report = Analytics::report(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $input['start'],
						'endDate'   => $input['end'],
					),
				),
				'dimensions' => array(
					array( 'name' => 'date' ),
					array( 'name' => 'landingPagePlusQueryString' ),
				),
				'metrics'    => array(
					array( 'name' => 'sessions' ),
					array( 'name' => 'engagedSessions' ),
					array( 'name' => 'keyEvents' ),
					array( 'name' => 'totalRevenue' ),
				),
				'limit'      => self::ROWS,
				'offset'     => (int) $input['offset'],
			)
		);
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		$put = array();
		foreach ( $report['rows'] as $row ) {
			$day   = substr( (string) $row[0], 0, 4 ) . '-' . substr( (string) $row[0], 4, 2 ) . '-' . substr( (string) $row[0], 6, 2 );
			$put[] = array(
				'day'              => $day,
				'url_id'           => Data_Store::url_id( self::landing_url( (string) $row[1] ) ),
				'sessions'         => (int) $row[2],
				'engaged_sessions' => (int) $row[3],
				'conversions'      => (float) $row[4],
				'revenue'          => (float) $row[5],
			);
		}
		Data_Store::put_ga( $put );
		if ( count( $report['rows'] ) >= self::ROWS ) {
			$next           = $input;
			$next['offset'] = (int) $input['offset'] + self::ROWS;
			Job_Store::add_steps(
				(int) $job['id'],
				array(
					array(
						'kind'  => 'seo_ga_rows',
						'label' => $step['label'] . ' (+)',
						'input' => $next,
					),
				)
			);
		} elseif ( self::is_last( $job, $step ) ) {
			update_option( self::STATE, array_merge( self::state(), array( 'backfilled' => gmdate( 'c' ) ) ), false );
			/**
			 * Fires after Google Analytics data was imported or updated.
			 */
			do_action( 'qcld_wpbot_seo/ga_pulled' );
		}
		return array(
			'rows'  => count( $report['rows'] ),
			'quota' => $report['quota'],
		);
	}

	/**
	 * Full URL for a GA landing path, matching Search Console's URLs.
	 *
	 * @param string $path Landing page with query string ("/blog/?p=1").
	 * @return string
	 */
	public static function landing_url( $path ) {
		if ( preg_match( '#^https?://#', $path ) ) {
			return $path;
		}
		return home_url( '/' . ltrim( $path, '/' ) );
	}

	/**
	 * Id of a pull job still queued or running, else 0.
	 *
	 * @return int
	 */
	public static function running() {
		$state = self::state();
		if ( empty( $state['job_id'] ) ) {
			return 0;
		}
		$job = Job_Store::get( (int) $state['job_id'] );
		return is_array( $job ) && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ? (int) $job['id'] : 0;
	}

	/**
	 * Stored state.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Whether a step is the job's last queued step.
	 *
	 * @param array $job  Job.
	 * @param array $step Step.
	 * @return bool
	 */
	private static function is_last( array $job, array $step ) {
		$steps = Job_Store::steps( (int) $job['id'] );
		$last  = end( $steps );
		return is_array( $last ) && (int) $last['id'] === (int) $step['id'];
	}
}
