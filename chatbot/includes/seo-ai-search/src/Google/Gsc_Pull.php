<?php
/**
 * Search Console pulls: a 16-month backfill, then a daily top-up.
 *
 * Runs in WPBot Core's job queue in short steps (one week and one row
 * page per step), so it works on shared hosting, survives closed tabs and
 * resumes after errors. Search Console's final data lags about three days.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Google;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Connections\Search_Console;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and runs Search Console pull jobs.
 */
final class Gsc_Pull {

	/**
	 * Option with the pull state.
	 */
	const STATE = 'qcld_wpbot_seo_gsc_state';

	/**
	 * Daily cron hook (pulls, Analytics, pruning, results).
	 */
	const CRON = 'qcld_wpbot_seo_daily';

	/**
	 * Rows per API request and step.
	 */
	const ROWS = 5000;

	/**
	 * Days Search Console's final data lags behind today.
	 */
	const LAG = 3;

	/**
	 * Days per window step.
	 */
	const WINDOW = 7;

	/**
	 * Hook the daily event.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
	}

	/**
	 * Schedule the daily event at night in the site's time zone.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON ) ) {
			return;
		}
		$night = new \DateTime( 'tomorrow 03:00', wp_timezone() );
		wp_schedule_event( $night->getTimestamp(), 'daily', self::CRON );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array<string,callable>
	 */
	public static function handlers() {
		return array(
			'seo_gsc_site' => array( __CLASS__, 'step_site' ),
			'seo_gsc_rows' => array( __CLASS__, 'step_rows' ),
			'seo_gsc_done' => array( __CLASS__, 'step_done' ),
		);
	}

	/**
	 * Daily event: first backfill, else top up the last days; prune old rows.
	 *
	 * @return void
	 */
	public static function daily() {
		if ( Google::connected() && ! self::running() ) {
			self::start( self::backfilled() ? 'daily' : 'backfill' );
		}
		Data_Store::prune( self::keep_from() );
		/**
		 * Daily maintenance for other parts of the module (Analytics pull,
		 * results, 404 pruning) and the Pro module.
		 */
		do_action( 'qcld_wpbot_seo/daily' );
	}

	/**
	 * Start a pull job.
	 *
	 * @param string $mode backfill|daily.
	 * @return int|WP_Error Job id.
	 */
	public static function start( $mode = 'backfill' ) {
		if ( ! Google::connected() ) {
			return new WP_Error( 'qcld_wpbot_seo_not_connected', __( 'Connect Google Search Console first (Settings > Connections).', 'chatbot' ), array( 'status' => 400 ) );
		}
		$running = self::running();
		if ( $running ) {
			return $running;
		}
		$property = Search_Console::property();
		if ( is_wp_error( $property ) ) {
			return $property;
		}
		$plan = self::plan( $mode, current_time( 'Y-m-d' ), (int) Settings::get( 'storage_months' ) );
		$job  = Job_Store::create(
			Module::ID,
			'gsc_pull',
			array(
				'title'   => 'backfill' === $mode ? __( 'Import Search Console data', 'chatbot' ) : __( 'Update Search Console data', 'chatbot' ),
				'payload' => array( 'mode' => $mode ),
			),
			$plan
		);
		if ( is_wp_error( $job ) || ! $job ) {
			return is_wp_error( $job ) ? $job : new WP_Error( 'qcld_wpbot_seo_job', __( 'The import could not be started.', 'chatbot' ) );
		}
		self::save_state(
			array(
				'job_id'  => (int) $job,
				'mode'    => $mode,
				'started' => gmdate( 'c' ),
			)
		);
		return (int) $job;
	}

	/**
	 * Steps for a pull: site totals, then week windows (newest first) for
	 * pages, queries and page x query rows.
	 *
	 * @param string $mode   backfill|daily.
	 * @param string $today  Y-m-d in the site time zone.
	 * @param int    $months Storage limit in months (Search Console keeps 16).
	 * @return array Steps.
	 */
	public static function plan( $mode, $today, $months ) {
		$end    = new \DateTimeImmutable( $today . ' -' . self::LAG . ' days' );
		$start  = 'daily' === $mode
			? $end->modify( '-9 days' )
			: $end->modify( '-' . max( 1, min( 16, (int) $months ) ) . ' months' )->modify( '+1 day' );
		$steps  = array(
			array(
				'kind'  => 'seo_gsc_site',
				'label' => __( 'Site totals', 'chatbot' ),
				'input' => array(
					'start' => $start->format( 'Y-m-d' ),
					'end'   => $end->format( 'Y-m-d' ),
				),
			),
		);
		$cursor = $end;
		while ( $cursor >= $start ) {
			$from = $cursor->modify( '-' . ( self::WINDOW - 1 ) . ' days' );
			$from = $from < $start ? $start : $from;
			foreach ( array( 'page', 'query', 'page_query' ) as $kind ) {
				$steps[] = array(
					'kind'  => 'seo_gsc_rows',
					/* translators: 1: kind of rows, 2: first day, 3: last day. */
					'label' => sprintf( __( '%1$s, %2$s to %3$s', 'chatbot' ), self::kind_label( $kind ), $from->format( 'Y-m-d' ), $cursor->format( 'Y-m-d' ) ),
					'input' => array(
						'start'     => $from->format( 'Y-m-d' ),
						'end'       => $cursor->format( 'Y-m-d' ),
						'kind'      => $kind,
						'start_row' => 0,
					),
				);
			}
			$cursor = $from->modify( '-1 day' );
		}
		$steps[] = array(
			'kind'  => 'seo_gsc_done',
			'label' => __( 'Finish', 'chatbot' ),
			'input' => array(),
		);
		return $steps;
	}

	/**
	 * Step: daily site totals.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step_site( array $step, array $job ) {
		unset( $job );
		$input = (array) $step['input'];
		$rows  = Search_Console::rows(
			array(
				'start'      => $input['start'],
				'end'        => $input['end'],
				'dimensions' => array( 'date' ),
				'row_limit'  => 25000,
			)
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$put = array();
		foreach ( $rows as $row ) {
			$put[] = array(
				'day'         => $row['keys'][0],
				'url_id'      => 0,
				'query_id'    => 0,
				'clicks'      => $row['clicks'],
				'impressions' => $row['impressions'],
				'position'    => $row['position'],
			);
		}
		Data_Store::put_gsc( $put );
		return array( 'days' => count( $put ) );
	}

	/**
	 * Step: one page of rows for one week window.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step_rows( array $step, array $job ) {
		$input = (array) $step['input'];
		$kind  = in_array( $input['kind'], array( 'page', 'query', 'page_query' ), true ) ? $input['kind'] : 'page';
		$dims  = array(
			'page'       => array( 'date', 'page' ),
			'query'      => array( 'date', 'query' ),
			'page_query' => array( 'date', 'page', 'query' ),
		);
		/**
		 * Rows per Search Console request and job step.
		 *
		 * @param int $rows Rows (default 5000).
		 */
		$limit = max( 10, min( 25000, (int) apply_filters( 'qcld_wpbot_seo/gsc_rows_per_step', self::ROWS ) ) );
		$rows  = Search_Console::rows(
			array(
				'start'      => $input['start'],
				'end'        => $input['end'],
				'dimensions' => $dims[ $kind ],
				'row_limit'  => $limit,
				'start_row'  => (int) $input['start_row'],
			)
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$put = array();
		foreach ( $rows as $row ) {
			$keys  = $row['keys'];
			$put[] = array(
				'day'         => $keys[0],
				'url_id'      => 'query' === $kind ? 0 : Data_Store::url_id( $keys[1] ),
				'query_id'    => 'page' === $kind ? 0 : Data_Store::query_id( 'query' === $kind ? $keys[1] : $keys[2] ),
				'clicks'      => $row['clicks'],
				'impressions' => $row['impressions'],
				'position'    => $row['position'],
			);
		}
		Data_Store::put_gsc( $put );
		if ( count( $rows ) >= $limit ) {
			// More rows in this window: continue in a new short step.
			$next              = $input;
			$next['start_row'] = (int) $input['start_row'] + $limit;
			Job_Store::add_steps(
				(int) $job['id'],
				array(
					array(
						'kind'  => 'seo_gsc_rows',
						'label' => $step['label'] . ' (+)',
						'input' => $next,
					),
				)
			);
		}
		return array( 'rows' => count( $rows ) );
	}

	/**
	 * Step: record that the pull finished.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function step_done( array $step, array $job ) {
		unset( $step );
		$state             = self::state();
		$state['finished'] = gmdate( 'c' );
		if ( isset( $job['payload']['mode'] ) && 'backfill' === $job['payload']['mode'] ) {
			$state['backfilled'] = gmdate( 'c' );
		}
		self::save_state( $state );
		/**
		 * Fires after Search Console data was imported or updated.
		 *
		 * @param string $mode backfill|daily.
		 */
		do_action( 'qcld_wpbot_seo/gsc_pulled', isset( $job['payload']['mode'] ) ? (string) $job['payload']['mode'] : 'daily' );
		return array( 'coverage' => Data_Store::coverage() );
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
	 * Whether the first import completed.
	 *
	 * @return bool
	 */
	public static function backfilled() {
		$state = self::state();
		return ! empty( $state['backfilled'] );
	}

	/**
	 * Oldest day to keep (storage limit; Pro may keep everything).
	 *
	 * @return string Y-m-d.
	 */
	public static function keep_from() {
		$months = (int) Settings::get( 'storage_months' );
		/**
		 * Months of daily data to keep. The Pro module raises this past
		 * Search Console's 16 months when the owner chooses.
		 *
		 * @param int $months Months.
		 */
		$months = max( 3, (int) apply_filters( 'qcld_wpbot_seo/storage_months', $months ) );
		return ( new \DateTimeImmutable( current_time( 'Y-m-d' ) ) )->modify( '-' . $months . ' months' )->format( 'Y-m-d' );
	}

	/**
	 * Public status for screens.
	 *
	 * @return array
	 */
	public static function status() {
		$state = self::state();
		return array(
			'job_id'     => self::running(),
			'backfilled' => ! empty( $state['backfilled'] ),
			'finished'   => isset( $state['finished'] ) ? (string) $state['finished'] : '',
			'coverage'   => Data_Store::coverage(),
		);
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
	 * Merge and save state.
	 *
	 * @param array $state State.
	 * @return void
	 */
	private static function save_state( array $state ) {
		update_option( self::STATE, array_merge( self::state(), $state ), false );
	}

	/**
	 * Readable kind.
	 *
	 * @param string $kind page|query|page_query.
	 * @return string
	 */
	private static function kind_label( $kind ) {
		$labels = array(
			'page'       => __( 'Pages', 'chatbot' ),
			'query'      => __( 'Searches', 'chatbot' ),
			'page_query' => __( 'Searches per page', 'chatbot' ),
		);
		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : $kind;
	}
}
