<?php
/**
 * First scan (round 2, R3): the first time someone who can use the module
 * opens the admin after installing (or upgrading), the site audit, the
 * technical scan and a first Topic Map from the site's own categories start
 * in the background, so Opportunities fills within minutes with no research
 * and no connection. None of these use AI or cost anything; they run in the
 * job queue in small steps, which suits shared hosting.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * First scan after installing.
 */
final class First_Scan {

	const OPTION = 'qcld_wpbot_seo_first_scan';
	const TOPICS = 'qcld_wpbot_seo_site_topics';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ), 20 );
		add_action( self::TOPICS, array( Site_Topics::class, 'build' ) );
	}

	/**
	 * Start once, for a person who can use the module.
	 *
	 * @return void
	 */
	public static function maybe_start() {
		if ( wp_doing_ajax() || get_option( self::OPTION ) || ! current_user_can( Module::cap( 'use' ) ) ) {
			return;
		}
		self::start();
	}

	/**
	 * Start the audit, the technical scan and the Topic Map.
	 *
	 * @return array State.
	 */
	public static function start() {
		$audit = Audit::start_scan( 'scan' );
		$scan  = Scan::start( 'auto' );
		$state = array(
			'started_at' => gmdate( 'c' ),
			'audit_job'  => is_wp_error( $audit ) ? 0 : (int) $audit,
			'scan_job'   => is_wp_error( $scan ) || ! is_array( $scan ) ? 0 : (int) $scan['job_id'],
		);
		update_option( self::OPTION, $state, false );
		if ( ! wp_next_scheduled( self::TOPICS ) ) {
			wp_schedule_single_event( time(), self::TOPICS );
		}
		Site_Types::soon();
		return $state;
	}

	/**
	 * For the Opportunities screen: is the first scan still running?
	 *
	 * @return array started (bool), running (bool), started_at.
	 */
	public static function status() {
		$state = get_option( self::OPTION );
		if ( ! is_array( $state ) ) {
			return array(
				'started'    => false,
				'running'    => false,
				'started_at' => '',
			);
		}
		$running = false;
		foreach ( array( 'audit_job', 'scan_job' ) as $key ) {
			$job = ! empty( $state[ $key ] ) ? Job_Store::get( (int) $state[ $key ] ) : null;
			if ( $job && in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
				$running = true;
			}
		}
		return array(
			'started'    => true,
			'running'    => $running,
			'started_at' => (string) $state['started_at'],
		);
	}
}
