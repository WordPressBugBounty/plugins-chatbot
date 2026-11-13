<?php
/**
 * Experiments service (round 3, P4): one engine for A/B tests across
 * modules (AI Writer now; AI Marketing and Social later). An experiment has
 * two to four variants, a method (split per visitor, before and after,
 * by time or by channel), a metric, a maximum run time, and a winner mode
 * (applied automatically or by the owner's click).
 *
 * Counts are daily aggregates per variant and metric; no personal data is
 * stored. One running test per page (object) at a time, across modules.
 *
 * The module that owns an experiment renders and applies its variants:
 * - action qcld_wpbot_core/experiment_apply ( $experiment, $variant ) when a winner is applied;
 * - action qcld_wpbot_core/experiment_switch ( $experiment, $variant ) when a before-and-after test changes period.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Experiments;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\WPBotCore\Storage\Schema;
use WP_Error;

/**
 * Experiment storage and lifecycle.
 */
final class Experiments {

	/** Option caching what the front end needs (running split tests). */
	const FRONT = 'qcld_wpbot_core_experiments_front';

	/** Daily check (winners, end dates, period switches). */
	const CRON = 'qcld_wpbot_core_experiments_daily';

	/** Methods. */
	const METHODS = array( 'split', 'before_after', 'time', 'channel' );

	/** Metrics a test can be judged on. */
	const METRICS = array( 'clicks', 'cta', 'scroll', 'engaged', 'add_to_cart', 'orders', 'leads', 'ctr', 'opens', 'link_clicks' );

	/** What each metric is counted against (its exposures). */
	const EXPOSURE = array(
		'clicks'      => 'impressions',
		'cta'         => 'views',
		'scroll'      => 'views',
		'engaged'     => 'views',
		'add_to_cart' => 'views',
		'orders'      => 'views',
		'leads'       => 'views',
		'ctr'         => 'impressions',
		'opens'       => 'sent',
		'link_clicks' => 'sent',
	);

	/**
	 * Hook the daily check.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::CRON, array( self::class, 'daily' ) );
		add_action( 'admin_init', array( self::class, 'privacy_text' ) );
		if ( ! wp_next_scheduled( self::CRON ) && get_option( self::FRONT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Suggested text for the site's privacy policy (Settings, Privacy).
	 *
	 * @return void
	 */
	public static function privacy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			/* translators: %s: product name, for example WPBot. */
			sprintf( __( '%s A/B tests', 'chatbot' ), \QuantumCloud\WPBotCore\Host\Brand::product() ),
			'<p>' . esc_html__( 'While an A/B test runs on a page, a first-party cookie (qcx_a) remembers which version of the page a visitor saw, so they see the same one again. It holds only test and version numbers, lasts up to 60 days, and is set only when the site\'s consent settings allow it. The site counts views and clicks per version as daily totals; no personal data is stored or sent to other services.', 'chatbot' ) . '</p>'
		);
	}

	/**
	 * Create an experiment (draft) with its variants.
	 *
	 * @param array $args module, kind, object_type, object_id, title, method, metric, winner_mode (auto|click),
	 *                    min_per_variant, max_days, stage, settings (array), legacy,
	 *                    variants: list of label, technique, content, is_control (2 to 4).
	 * @return int|WP_Error Experiment id.
	 */
	public static function create( array $args ) {
		global $wpdb;
		$module   = isset( $args['module'] ) ? sanitize_key( (string) $args['module'] ) : '';
		$variants = isset( $args['variants'] ) ? array_values( (array) $args['variants'] ) : array();
		if ( '' === $module ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_module', __( 'Name the module that owns the test.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( count( $variants ) < 2 || count( $variants ) > 4 ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_variants', __( 'A test needs two to four versions.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$method = isset( $args['method'] ) && in_array( $args['method'], self::METHODS, true ) ? $args['method'] : 'split';
		$metric = isset( $args['metric'] ) && in_array( $args['metric'], self::METRICS, true ) ? $args['metric'] : 'clicks';
		$now    = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
			Schema::table( Schema::EXPERIMENTS ),
			array(
				'module'          => $module,
				'kind'            => isset( $args['kind'] ) ? sanitize_key( (string) $args['kind'] ) : '',
				'object_type'     => isset( $args['object_type'] ) ? sanitize_key( (string) $args['object_type'] ) : 'post',
				'object_id'       => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
				'title'           => isset( $args['title'] ) ? substr( sanitize_text_field( (string) $args['title'] ), 0, 255 ) : '',
				'method'          => $method,
				'metric'          => $metric,
				'status'          => 'draft',
				'winner_mode'     => isset( $args['winner_mode'] ) && 'auto' === $args['winner_mode'] && in_array( $method, array( 'split', 'before_after' ), true ) ? 'auto' : 'click',
				'stage'           => isset( $args['stage'] ) ? sanitize_key( (string) $args['stage'] ) : '',
				'min_per_variant' => max( 30, min( 100000, isset( $args['min_per_variant'] ) ? (int) $args['min_per_variant'] : 100 ) ),
				'max_days'        => max( 3, min( 120, isset( $args['max_days'] ) ? (int) $args['max_days'] : 30 ) ),
				'settings'        => wp_json_encode( isset( $args['settings'] ) ? (array) $args['settings'] : array() ),
				'result'          => wp_json_encode( array() ),
				'legacy'          => isset( $args['legacy'] ) ? substr( sanitize_text_field( (string) $args['legacy'] ), 0, 100 ) : '',
				'created_by'      => get_current_user_id(),
				'created_at'      => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_db', __( 'The test could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$has_control = false;
		foreach ( $variants as $i => $variant ) {
			$control     = ! $has_control && ( ! empty( $variant['is_control'] ) || ( 0 === $i && ! self::any_control( $variants ) ) );
			$has_control = $has_control || $control;
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
				Schema::table( Schema::VARIANTS ),
				array(
					'experiment_id' => $id,
					'label'         => isset( $variant['label'] ) && '' !== $variant['label'] ? substr( sanitize_text_field( (string) $variant['label'] ), 0, 20 ) : chr( 65 + $i ),
					'technique'     => isset( $variant['technique'] ) ? sanitize_key( (string) $variant['technique'] ) : '',
					'content'       => isset( $variant['content'] ) ? wp_kses_post( (string) $variant['content'] ) : '',
					'is_control'    => $control ? 1 : 0,
					'sort'          => $i,
				)
			);
		}
		return $id;
	}

	/**
	 * Start a draft experiment (one running test per object).
	 *
	 * @param int        $id         Experiment.
	 * @param string|int $started_at Start time (Y-m-d H:i:s UTC) for imported history; '' for now.
	 * @return array|WP_Error View.
	 */
	public static function start( $id, $started_at = '' ) {
		global $wpdb;
		$exp = self::get( $id );
		if ( ! $exp ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_missing', __( 'That test does not exist.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( 'draft' !== $exp['status'] ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_state', __( 'This test has already started.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( $exp['object_id'] ) {
			foreach ( self::query(
				array(
					'status'      => 'running',
					'object_type' => $exp['object_type'],
					'object_id'   => $exp['object_id'],
				)
			) as $other ) {
				if ( (int) $other['id'] !== (int) $exp['id'] ) {
					return new WP_Error( 'qcld_wpbot_core_experiment_busy', __( 'This page already has a test running. One test per page keeps the results clean.', 'chatbot' ), array( 'status' => 409 ) );
				}
			}
		}
		$start = '' !== (string) $started_at ? (string) $started_at : current_time( 'mysql', true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
			Schema::table( Schema::EXPERIMENTS ),
			array(
				'status'     => 'running',
				'started_at' => $start,
				'ends_at'    => gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + $exp['max_days'] * DAY_IN_SECONDS ),
			),
			array( 'id' => (int) $id )
		);
		self::refresh_front();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
		if ( 'before_after' === $exp['method'] ) {
			$exp = self::get( $id );
			/** This action is documented in the class docblock. */
			do_action( 'qcld_wpbot_core/experiment_switch', $exp, self::control( $exp ) );
		}
		return self::view( $id );
	}

	/**
	 * One experiment with its variants.
	 *
	 * @param int $id Experiment.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::EXPERIMENTS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Experiments matching filters (newest first, at most 200).
	 *
	 * @param array $args module, status (string or list), object_type, object_id, legacy, kind.
	 * @return array[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = Schema::table( Schema::EXPERIMENTS );
		$where = array( '1=1' );
		$vals  = array();
		foreach ( array( 'module', 'object_type', 'legacy', 'kind' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$where[] = "{$key} = %s";
				$vals[]  = (string) $args[ $key ];
			}
		}
		if ( ! empty( $args['object_id'] ) ) {
			$where[] = 'object_id = %d';
			$vals[]  = (int) $args['object_id'];
		}
		if ( ! empty( $args['status'] ) ) {
			$statuses = array_map( 'sanitize_key', (array) $args['status'] );
			$where[]  = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$vals     = array_merge( $vals, $statuses );
		}
		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 200';
		$rows = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; placeholders built above.
		return array_map( array( self::class, 'shape' ), (array) $rows );
	}

	/**
	 * Count events for a variant (running experiments only).
	 *
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant.
	 * @param string $metric     Metric or exposure name.
	 * @param int    $n          How many.
	 * @param string $day        Y-m-d (UTC); '' for today.
	 * @return bool Counted.
	 */
	public static function record( $id, $variant_id, $metric, $n = 1, $day = '' ) {
		global $wpdb;
		$metric = sanitize_key( (string) $metric );
		$front  = self::front();
		$known  = isset( $front['tests'][ (int) $id ] ) ? $front['tests'][ (int) $id ] : null;
		if ( ! $known ) {
			$exp   = self::get( $id );
			$known = $exp && 'running' === $exp['status'] ? array( 'v' => wp_list_pluck( $exp['variants'], 'id' ) ) : null;
		}
		if ( ! $known || ! in_array( (int) $variant_id, array_map( 'intval', $known['v'] ), true ) || '' === $metric || $n < 1 ) {
			return false;
		}
		$table = Schema::table( Schema::STATS );
		$day   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $day ) ? (string) $day : gmdate( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (experiment_id, variant_id, day, metric, count) VALUES (%d, %d, %s, %s, %d) ON DUPLICATE KEY UPDATE count = count + VALUES(count)", (int) $id, (int) $variant_id, $day, $metric, (int) $n ) );
		return true;
	}

	/**
	 * Store imported history (older tests moved into the service), whatever
	 * the test's status. The variant must belong to the test.
	 *
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant.
	 * @param string $metric     Metric.
	 * @param int    $n          Count.
	 * @param string $day        Y-m-d.
	 * @return bool
	 */
	public static function record_imported( $id, $variant_id, $metric, $n, $day ) {
		global $wpdb;
		$exp = self::get( $id );
		if ( ! $exp || ! in_array( (int) $variant_id, wp_list_pluck( $exp['variants'], 'id' ), true ) || $n < 1 ) {
			return false;
		}
		$table = Schema::table( Schema::STATS );
		$day   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $day ) ? (string) $day : gmdate( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (experiment_id, variant_id, day, metric, count) VALUES (%d, %d, %s, %s, %d) ON DUPLICATE KEY UPDATE count = count + VALUES(count)", (int) $id, (int) $variant_id, $day, sanitize_key( (string) $metric ), (int) $n ) );
		return true;
	}

	/**
	 * Totals per variant and metric.
	 *
	 * @param int $id Experiment.
	 * @return array<int,array<string,int>> variant id => metric => count.
	 */
	public static function totals( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::STATS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT variant_id, metric, SUM(count) AS total FROM {$table} WHERE experiment_id = %d GROUP BY variant_id, metric", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['variant_id'] ][ $row['metric'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Everything a results screen needs: counts, rates, the assessment in
	 * plain words, the reliability label and the duration estimate.
	 *
	 * @param int $id Experiment.
	 * @return array|null
	 */
	public static function view( $id ) {
		$exp = self::get( $id );
		if ( ! $exp ) {
			return null;
		}
		$counts   = self::counts( $exp );
		$exposure = self::EXPOSURE[ $exp['metric'] ];
		$list     = array();
		foreach ( $exp['variants'] as $variant ) {
			$c      = isset( $counts[ $variant['id'] ] ) ? $counts[ $variant['id'] ] : array();
			$list[] = array(
				'id'      => $variant['id'],
				'n'       => isset( $c[ $exposure ] ) ? (int) $c[ $exposure ] : 0,
				'conv'    => isset( $c[ $exp['metric'] ] ) ? (int) $c[ $exp['metric'] ] : 0,
				'control' => $variant['is_control'],
			);
		}
		$days_run = $exp['started_at'] ? max( 0, ( time() - strtotime( $exp['started_at'] . ' UTC' ) ) / DAY_IN_SECONDS ) : 0;
		$ended    = $exp['ends_at'] && strtotime( $exp['ends_at'] . ' UTC' ) <= time();
		$assess   = in_array( $exp['method'], array( 'time', 'channel' ), true )
			? self::indicator( $list, $exp )
			: Stats::assess( $list, $exp['min_per_variant'], $ended || in_array( $exp['status'], array( 'ended', 'done', 'stopped' ), true ), $days_run, $exp['max_days'] );
		$variants = array();
		foreach ( $exp['variants'] as $i => $variant ) {
			$row        = $list[ $i ];
			$cmp        = isset( $assess['comparisons'][ $variant['id'] ] ) ? $assess['comparisons'][ $variant['id'] ] : null;
			$variants[] = array_merge(
				$variant,
				array(
					'exposures'   => $row['n'],
					'conversions' => $row['conv'],
					'rate'        => $row['n'] ? round( 100 * $row['conv'] / $row['n'], 2 ) : 0,
					'lift'        => $cmp ? round( 100 * $cmp['lift'], 1 ) : null,
					'counts'      => isset( $counts[ $variant['id'] ] ) ? $counts[ $variant['id'] ] : array(),
				)
			);
		}
		$daily    = $days_run >= 1 ? array_sum( wp_list_pluck( $list, 'n' ) ) / $days_run : 0;
		$base     = $list && $list[0]['n'] ? max( 0.005, $list[0]['conv'] / $list[0]['n'] ) : self::base_rate( $exp['metric'] );
		$duration = Stats::duration( $daily, count( $list ), $base, $exp['max_days'], $exp['min_per_variant'] );
		return array_merge(
			$exp,
			array(
				'variants'     => $variants,
				'exposure'     => $exposure,
				'assessment'   => array(
					'state'      => $assess['state'],
					'winner'     => (int) $assess['winner'],
					'confidence' => round( $assess['confidence'], 3 ),
					'leader'     => isset( $assess['leader'] ) ? (int) $assess['leader'] : 0,
					'words'      => self::words( $assess, $exp ),
				),
				'reliability'  => self::reliability( $exp ),
				'duration'     => $duration,
				'days_run'     => round( $days_run, 1 ),
				'can_apply'    => in_array( $exp['status'], array( 'running', 'ended' ), true ),
				'apply_needed' => 'clear' === $assess['state'] && 'click' === $exp['winner_mode'] && in_array( $exp['status'], array( 'running', 'ended' ), true ),
			)
		);
	}

	/**
	 * Duration estimate before starting.
	 *
	 * @param float  $daily    Daily visitors (or impressions) that will see the test.
	 * @param int    $variants Variants.
	 * @param string $metric   Metric.
	 * @param int    $max_days Maximum run time.
	 * @param int    $min      Minimum per variant.
	 * @return array per_variant, days (null when there is no traffic), fits, message.
	 */
	public static function estimate( $daily, $variants, $metric, $max_days = 30, $min = 100 ) {
		$out = Stats::duration( $daily, $variants, self::base_rate( $metric ), $max_days, $min );
		if ( null === $out['days'] ) {
			$out['message'] = __( 'This page has no recent traffic, so the test may never finish.', 'chatbot' );
		} elseif ( ! $out['fits'] ) {
			/* translators: 1: days needed, 2: maximum days. */
			$out['message'] = sprintf( __( 'At the current traffic this needs about %1$d days, longer than the %2$d-day limit. It may end without a clear result.', 'chatbot' ), $out['days'], (int) $max_days );
		} else {
			/* translators: %d: days. */
			$out['message'] = sprintf( _n( 'About %d day at the current traffic.', 'About %d days at the current traffic.', $out['days'], 'chatbot' ), $out['days'] );
		}
		return $out;
	}

	/**
	 * Stop a test without a winner.
	 *
	 * @param int $id Experiment.
	 * @return array|WP_Error View.
	 */
	public static function stop( $id ) {
		$exp = self::get( $id );
		if ( ! $exp ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_missing', __( 'That test does not exist.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( 'before_after' === $exp['method'] && 'running' === $exp['status'] ) {
			/** This action is documented in the class docblock. */
			do_action( 'qcld_wpbot_core/experiment_switch', $exp, self::control( $exp ) );
		}
		self::set_status( $id, 'stopped', 0, self::view( $id )['assessment'] );
		return self::view( $id );
	}

	/**
	 * Apply a variant as the winner (the owner's click or the automatic rule)
	 * and update the per-technique results.
	 *
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant to keep.
	 * @param string $by         owner|auto.
	 * @return array|WP_Error View.
	 */
	public static function apply( $id, $variant_id, $by = 'owner' ) {
		$view = self::view( $id );
		if ( ! $view ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_missing', __( 'That test does not exist.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! $view['can_apply'] ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_state', __( 'This test is already finished.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$variant = null;
		foreach ( $view['variants'] as $row ) {
			if ( (int) $row['id'] === (int) $variant_id ) {
				$variant = $row;
			}
		}
		if ( ! $variant ) {
			return new WP_Error( 'qcld_wpbot_core_experiment_variant', __( 'That version is not part of the test.', 'chatbot' ), array( 'status' => 400 ) );
		}
		/** This action is documented in the class docblock. */
		do_action( 'qcld_wpbot_core/experiment_apply', $view, $variant );
		$result       = $view['assessment'];
		$result['by'] = 'auto' === $by ? 'auto' : 'owner';
		self::set_status( $id, 'done', (int) $variant_id, $result );
		Learning::record_test( $view );
		return self::view( $id );
	}

	/**
	 * Close an imported test as it was (no apply action, no learning): used
	 * when older tests move into the service with their history.
	 *
	 * @param int    $id         Experiment.
	 * @param string $status     done|stopped|ended.
	 * @param int    $variant_id Winner kept earlier (0 for none).
	 * @return void
	 */
	public static function close_imported( $id, $status, $variant_id = 0 ) {
		$view = self::view( $id );
		if ( $view ) {
			self::set_status( $id, in_array( $status, array( 'done', 'stopped', 'ended' ), true ) ? $status : 'done', (int) $variant_id, $view['assessment'] + array( 'imported' => true ) );
		}
	}

	/**
	 * Daily check: automatic winners, end dates and before-and-after periods.
	 *
	 * @return void
	 */
	public static function daily() {
		foreach ( self::query( array( 'status' => 'running' ) ) as $exp ) {
			$view = self::view( $exp['id'] );
			if ( 'clear' === $view['assessment']['state'] && 'auto' === $view['winner_mode'] && 'split' === $view['method'] ) {
				self::apply( $exp['id'], $view['assessment']['winner'], 'auto' );
				continue;
			}
			if ( $exp['ends_at'] && strtotime( $exp['ends_at'] . ' UTC' ) <= time() ) {
				if ( 'before_after' === $exp['method'] ) {
					/** This action is documented in the class docblock. */
					do_action( 'qcld_wpbot_core/experiment_switch', $exp, self::control( $exp ) );
				}
				self::set_status( $exp['id'], 'ended', 0, $view['assessment'] );
				continue;
			}
			if ( 'before_after' === $exp['method'] ) {
				$mid  = self::midpoint( $exp );
				$flag = ! empty( $exp['settings']['switched'] );
				if ( ! $flag && time() >= $mid ) {
					self::update_settings( $exp['id'], array( 'switched' => gmdate( 'Y-m-d H:i:s', $mid ) ) );
					/** This action is documented in the class docblock. */
					do_action( 'qcld_wpbot_core/experiment_switch', self::get( $exp['id'] ), self::other( $exp ) );
				}
			}
		}
	}

	/**
	 * The front-end list of running split tests (cached in an option).
	 *
	 * @return array{tests: array}
	 */
	public static function front() {
		$front = get_option( self::FRONT, array() );
		return is_array( $front ) && isset( $front['tests'] ) ? $front : array( 'tests' => array() );
	}

	/**
	 * Rebuild the front-end cache.
	 *
	 * @return void
	 */
	public static function refresh_front() {
		self::purge();
		$tests = array();
		foreach ( self::query( array( 'status' => 'running' ) ) as $exp ) {
			if ( 'split' !== $exp['method'] ) {
				continue;
			}
			$tests[ (int) $exp['id'] ] = array(
				'k' => $exp['kind'],
				'm' => $exp['module'],
				'o' => (int) $exp['object_id'],
				'g' => $exp['metric'],
				'v' => array_map( 'intval', wp_list_pluck( $exp['variants'], 'id' ) ),
				'c' => (int) self::control( $exp )['id'],
				'l' => $exp['legacy'],
			);
		}
		if ( array() === $tests ) {
			delete_option( self::FRONT );
			return;
		}
		update_option( self::FRONT, array( 'tests' => $tests ), true );
	}

	/**
	 * Tell page caches that pages with tests changed: the post cache of each
	 * tested page is cleaned (cache plugins purge on it) and the action
	 * qcld_wpbot_core/experiments_changed fires for others.
	 *
	 * @return void
	 */
	private static function purge() {
		$ids = array();
		foreach ( self::front()['tests'] as $test ) {
			$ids[] = (int) $test['o'];
		}
		foreach ( self::query( array( 'status' => 'running' ) ) as $exp ) {
			$ids[] = (int) $exp['object_id'];
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		foreach ( $ids as $id ) {
			clean_post_cache( $id );
		}
		/**
		 * Pages with tests started or ended: purge cached copies.
		 *
		 * @param int[] $ids Page ids (headline tests also appear in lists, so a full purge is safest).
		 */
		do_action( 'qcld_wpbot_core/experiments_changed', $ids );
	}

	/**
	 * Running split tests of a module (from the cache).
	 *
	 * @param string $module Module.
	 * @return array<int,array>
	 */
	public static function running_for( $module ) {
		return array_filter(
			self::front()['tests'],
			static function ( $test ) use ( $module ) {
				return $test['m'] === $module;
			}
		);
	}

	/**
	 * The control variant.
	 *
	 * @param array $exp Experiment.
	 * @return array
	 */
	public static function control( array $exp ) {
		foreach ( $exp['variants'] as $variant ) {
			if ( $variant['is_control'] ) {
				return $variant;
			}
		}
		return $exp['variants'][0];
	}

	/**
	 * The first variant that is not the control (before and after: the "after").
	 *
	 * @param array $exp Experiment.
	 * @return array
	 */
	public static function other( array $exp ) {
		foreach ( $exp['variants'] as $variant ) {
			if ( ! $variant['is_control'] ) {
				return $variant;
			}
		}
		return $exp['variants'][0];
	}

	/**
	 * Halfway point of a before-and-after test (timestamp).
	 *
	 * @param array $exp Experiment.
	 * @return int
	 */
	public static function midpoint( array $exp ) {
		$start = strtotime( $exp['started_at'] . ' UTC' );
		$end   = strtotime( $exp['ends_at'] . ' UTC' );
		return (int) ( $start + ( $end - $start ) / 2 );
	}

	/**
	 * Merge settings.
	 *
	 * @param int   $id      Experiment.
	 * @param array $changes Settings to merge.
	 * @return void
	 */
	public static function update_settings( $id, array $changes ) {
		global $wpdb;
		$exp = self::get( $id );
		if ( ! $exp ) {
			return;
		}
		$wpdb->update( Schema::table( Schema::EXPERIMENTS ), array( 'settings' => wp_json_encode( array_merge( $exp['settings'], $changes ) ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Delete an experiment and its counts (the owner's choice).
	 *
	 * @param int $id Experiment.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		foreach ( array(
			Schema::EXPERIMENTS => 'id',
			Schema::VARIANTS    => 'experiment_id',
			Schema::STATS       => 'experiment_id',
		) as $table => $column ) {
			$wpdb->delete( Schema::table( $table ), array( $column => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables.
		}
		self::refresh_front();
	}

	/**
	 * Counts of a test: stored counts, or (before and after) counts from a
	 * measurement adapter for each period.
	 *
	 * @param array $exp Experiment.
	 * @return array<int,array<string,int>>
	 */
	private static function counts( array $exp ) {
		$counts = self::totals( $exp['id'] );
		/**
		 * Counts from a measurement adapter (Search Console for meta title
		 * tests, an email sender for email tests).
		 *
		 * @param array $counts variant id => metric => count.
		 * @param array $exp    Experiment.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/experiment_counts', $counts, $exp );
	}

	/**
	 * Time and channel tests: audiences differ, so there is no statistical
	 * verdict. Once enough clicks are in, the version ahead is named as an
	 * indicator, never as a winner.
	 *
	 * @param array[] $rows Variants (id, n, conv, control).
	 * @param array   $exp  Experiment.
	 * @return array Assessment (state collecting|indicator; leader; winner always 0).
	 */
	private static function indicator( array $rows, array $exp ) {
		$total  = array_sum( wp_list_pluck( $rows, 'conv' ) );
		$leader = null;
		$second = 0;
		foreach ( $rows as $row ) {
			if ( null === $leader || $row['conv'] > $leader['conv'] ) {
				$second = $leader ? $leader['conv'] : $second;
				$leader = $row;
			} elseif ( $row['conv'] > $second ) {
				$second = $row['conv'];
			}
		}
		$enough = $total >= max( 20, min( 1000, (int) $exp['min_per_variant'] ) ) && $leader && $leader['conv'] > $second;
		return array(
			'state'       => $enough ? 'indicator' : 'collecting',
			'winner'      => 0,
			'leader'      => $enough ? (int) $leader['id'] : 0,
			'leader_conv' => $leader ? (int) $leader['conv'] : 0,
			'second_conv' => (int) $second,
			'confidence'  => 0.0,
			'comparisons' => array(),
		);
	}

	/**
	 * Reliability label of the method.
	 *
	 * @param array $exp Experiment.
	 * @return array{level: string, label: string, note: string}
	 */
	private static function reliability( array $exp ) {
		if ( 'before_after' === $exp['method'] ) {
			return array(
				'level' => 'less_reliable',
				'label' => __( 'Less reliable', 'chatbot' ),
				'note'  => __( 'Search titles cannot be split per visitor, so the two periods are compared. Seasons, news and ranking changes also move the numbers.', 'chatbot' ),
			);
		}
		if ( in_array( $exp['method'], array( 'time', 'channel' ), true ) ) {
			return array(
				'level' => 'indicator',
				'label' => __( 'Indicator', 'chatbot' ),
				'note'  => __( 'Versions run at different times or on different channels, so audiences differ. Treat the result as a hint.', 'chatbot' ),
			);
		}
		return array(
			'level' => 'standard',
			'label' => __( 'Standard', 'chatbot' ),
			'note'  => __( 'Each visitor sees one version, the same one every time.', 'chatbot' ),
		);
	}

	/**
	 * The assessment in plain words.
	 *
	 * @param array $assess Assessment.
	 * @param array $exp    Experiment.
	 * @return string
	 */
	private static function words( array $assess, array $exp ) {
		switch ( $assess['state'] ) {
			case 'clear':
				$label = '';
				foreach ( $exp['variants'] as $variant ) {
					if ( (int) $variant['id'] === (int) $assess['winner'] ) {
						$label = $variant['label'];
					}
				}
				/* translators: 1: version letter, 2: confidence words. */
				return sprintf( __( 'Version %1$s is the winner: %2$s.', 'chatbot' ), $label, Stats::confidence_words( $assess['confidence'] ) );
			case 'indicator':
				$label = '';
				foreach ( $exp['variants'] as $variant ) {
					if ( (int) $variant['id'] === (int) $assess['leader'] ) {
						$label = $variant['label'];
					}
				}
				/* translators: 1: version letter, 2: its clicks, 3: the next best clicks. */
				return sprintf( __( 'Version %1$s is ahead: %2$d clicks against %3$d. An indicator only: the audiences were not the same.', 'chatbot' ), $label, $assess['leader_conv'], $assess['second_conv'] );
			case 'low_traffic':
				return __( 'Not enough traffic: at this rate the test will not reach a reliable result before its end date. Nothing will be picked as a winner by chance.', 'chatbot' );
			case 'no_difference':
				return __( 'No clear difference between the versions. Keep the one you prefer.', 'chatbot' );
			case 'not_enough_data':
				return __( 'The test ended without enough data for a reliable result. No winner was picked.', 'chatbot' );
			default:
				return __( 'Not enough data yet.', 'chatbot' );
		}
	}

	/**
	 * Typical rate of a metric (for estimates before there is data).
	 *
	 * @param string $metric Metric.
	 * @return float
	 */
	private static function base_rate( $metric ) {
		$rates = array(
			'clicks'      => 0.05,
			'cta'         => 0.05,
			'scroll'      => 0.4,
			'engaged'     => 0.3,
			'add_to_cart' => 0.05,
			'orders'      => 0.02,
			'leads'       => 0.03,
			'ctr'         => 0.03,
			'opens'       => 0.25,
			'link_clicks' => 0.03,
		);
		return isset( $rates[ $metric ] ) ? $rates[ $metric ] : 0.05;
	}

	/**
	 * Set the status (and winner, result); refresh the front cache.
	 *
	 * @param int    $id      Experiment.
	 * @param string $status Status.
	 * @param int    $winner  Winner variant (0 for none).
	 * @param array  $result  Result snapshot.
	 * @return void
	 */
	private static function set_status( $id, $status, $winner, array $result ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( Schema::EXPERIMENTS ),
			array(
				'status'         => $status,
				'winner_variant' => (int) $winner,
				'result'         => wp_json_encode( $result ),
				'finished_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		self::refresh_front();
	}

	/**
	 * Whether a list of variants names a control.
	 *
	 * @param array[] $variants Variants.
	 * @return bool
	 */
	private static function any_control( array $variants ) {
		foreach ( $variants as $variant ) {
			if ( ! empty( $variant['is_control'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Shape a row (with variants).
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		global $wpdb;
		$table    = Schema::table( Schema::VARIANTS );
		$variants = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE experiment_id = %d ORDER BY sort ASC, id ASC", (int) $row['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$settings = json_decode( (string) $row['settings'], true );
		$result   = json_decode( (string) $row['result'], true );
		return array(
			'id'              => (int) $row['id'],
			'module'          => (string) $row['module'],
			'kind'            => (string) $row['kind'],
			'object_type'     => (string) $row['object_type'],
			'object_id'       => (int) $row['object_id'],
			'title'           => (string) $row['title'],
			'method'          => (string) $row['method'],
			'metric'          => (string) $row['metric'],
			'status'          => (string) $row['status'],
			'winner_mode'     => (string) $row['winner_mode'],
			'winner_variant'  => (int) $row['winner_variant'],
			'stage'           => (string) $row['stage'],
			'min_per_variant' => (int) $row['min_per_variant'],
			'max_days'        => (int) $row['max_days'],
			'settings'        => is_array( $settings ) ? $settings : array(),
			'result'          => is_array( $result ) ? $result : array(),
			'legacy'          => (string) $row['legacy'],
			'created_by'      => (int) $row['created_by'],
			'created_at'      => (string) $row['created_at'],
			'started_at'      => (string) $row['started_at'],
			'ends_at'         => (string) $row['ends_at'],
			'finished_at'     => (string) $row['finished_at'],
			'variants'        => array_map(
				static function ( $variant ) {
					return array(
						'id'         => (int) $variant['id'],
						'label'      => (string) $variant['label'],
						'technique'  => (string) $variant['technique'],
						'content'    => (string) $variant['content'],
						'is_control' => (bool) (int) $variant['is_control'],
					);
				},
				(array) $variants
			),
		);
	}
}
