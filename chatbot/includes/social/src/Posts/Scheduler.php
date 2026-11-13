<?php
/**
 * Social's own scheduler (build-brief A2): finds due targets, claims each
 * one atomically and only then creates a WPBot Core job for it, so posts
 * scheduled for later never run early and three parallel ticks make one job
 * per target. Runs every minute on WP-Cron, plus a loopback kick when a
 * post is due soon.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Settings;
use QuantumCloud\WPBotCore\Jobs\Cron_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduler.
 */
final class Scheduler {

	/**
	 * Cron hook.
	 */
	const EVENT = 'qcld_wpbot_social_tick';

	/**
	 * Cron schedule name.
	 */
	const SCHEDULE = 'qcld_wpbot_social_minute';

	/**
	 * Targets claimed per tick (keeps each tick short; WPBot Core runs the jobs).
	 */
	const PER_TICK = 10;

	/**
	 * Hook the tick and the job handlers.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval -- One minute, so posts go out on time.
		add_action( self::EVENT, array( __CLASS__, 'tick' ) );
		add_filter( 'qcld_wpbot_core/job_handlers', array( Publisher::class, 'handlers' ) );
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + 60, self::SCHEDULE, self::EVENT );
		}
	}

	/**
	 * Add the one-minute schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 60,
			'display'  => __( 'Every minute (WPBot Social)', 'chatbot' ),
		);
		return $schedules;
	}

	/**
	 * One tick: recover stale work, claim due targets, reconcile unknown ones.
	 *
	 * @return array{claimed:int, reconciling:int}
	 */
	public static function tick() {
		if ( ! \QuantumCloud\Social\Module::instance()->licensed() ) {
			return array(
				'claimed'     => 0,
				'reconciling' => 0,
			);
		}
		$now = current_time( 'mysql', true );
		Target_Store::recover_stale( gmdate( 'Y-m-d H:i:s', time() - Target_Store::STALE_SENDING ) );
		$missed_after = (int) Settings::get( 'missed_after' );
		if ( $missed_after > 0 ) {
			Target_Store::mark_missed( gmdate( 'Y-m-d H:i:s', time() - $missed_after * HOUR_IN_SECONDS ) );
		}
		$claimed = 0;
		foreach ( Target_Store::due_ids( $now, self::PER_TICK, array_keys( \QuantumCloud\Social\Module::instance()->networks() ) ) as $id ) {
			$claim = Target_Store::claim( $id, $now );
			if ( '' === $claim ) {
				continue;
			}
			self::job( 'social_publish', $id, $claim );
			++$claimed;
		}
		$reconciling = 0;
		foreach ( Target_Store::unknown_ids( self::PER_TICK, $now ) as $id ) {
			$claim = Target_Store::claim_unknown( $id, $now );
			if ( '' !== $claim ) {
				self::job( 'social_reconcile', $id, $claim );
				++$reconciling;
			}
		}
		return array(
			'claimed'     => $claimed,
			'reconciling' => $reconciling,
		);
	}

	/**
	 * Create a one-step Core job.
	 *
	 * @param string $kind  Step kind.
	 * @param int    $id    Target id.
	 * @param string $claim Claim token.
	 * @return int Job id.
	 */
	private static function job( $kind, $id, $claim ) {
		return Job_Store::create(
			'social',
			'social_publish' === $kind ? 'publish' : 'reconcile',
			array(
				'title'       => 'social_publish' === $kind ? __( 'Publish social post', 'chatbot' ) : __( 'Check a social post', 'chatbot' ),
				'object_type' => 'social_target',
				'object_id'   => $id,
			),
			array(
				array(
					'kind'  => $kind,
					'label' => $kind,
					'input' => array(
						'target_id' => $id,
						'claim'     => $claim,
					),
				),
			)
		);
	}

	/**
	 * Ask WPBot Core's runner to tick soon (loopback), for a post due now.
	 *
	 * @return void
	 */
	public static function kick() {
		if ( class_exists( Cron_Runner::class ) && method_exists( Cron_Runner::class, 'kick' ) ) {
			Cron_Runner::kick();
		}
		spawn_cron();
	}
}
