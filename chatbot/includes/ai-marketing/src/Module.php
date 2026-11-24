<?php
/**
 * WPBot AI Marketing module: run mode and boot sequence.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing;

use QuantumCloud\AiMarketing\Host\Host_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the module.
 */
final class Module {

	/**
	 * Module id used in WPBot Core (permissions section, jobs, usage).
	 */
	const ID = 'marketing';

	/**
	 * Run modes (see mode()).
	 */
	const MODE_ESSENTIALS = 'essentials';
	const MODE_MASTER     = 'master';
	const MODE_OFF        = 'off';

	/**
	 * Hosts that ran AI Marketing on this site (see uninstall-helper.php).
	 */
	const HOSTS_OPTION = 'qcld_wpbot_marketing_hosts';

	/**
	 * Daily housekeeping event (Pro adds its own work to it).
	 */
	const DAILY = 'qcld_wpbot_marketing_daily';

	/**
	 * Single instance.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * The host adapter.
	 *
	 * @var Host_Adapter|null
	 */
	private $host = null;

	/**
	 * Whether the features started on this request.
	 *
	 * @var bool
	 */
	private $started = false;

	/**
	 * The module instance.
	 *
	 * @return Module
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Forget the instance (unit tests only).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Boot: wait for the host, then start when its mode allows.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'start' ), 5 );
	}

	/**
	 * Register the host adapter (from qcld_wpbot_marketing/loaded).
	 *
	 * @param Host_Adapter $host Host adapter.
	 * @return void
	 */
	public function register_host( Host_Adapter $host ) {
		$this->host = $host;
	}

	/**
	 * The host adapter, if one is registered.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		return $this->host;
	}

	/**
	 * How AI Marketing runs on this request: "essentials", "master" or "off".
	 * Read from the host on every call, so a license change pauses or resumes
	 * the Master features without losing data.
	 *
	 * @return string
	 */
	public function mode() {
		if ( null === $this->host ) {
			return self::MODE_OFF;
		}
		$mode = (string) $this->host->get_marketing_mode();
		return in_array( $mode, array( self::MODE_ESSENTIALS, self::MODE_MASTER ), true ) ? $mode : self::MODE_OFF;
	}

	/**
	 * Whether AI Marketing may run at all.
	 *
	 * @return bool
	 */
	public function active() {
		return self::MODE_OFF !== $this->mode();
	}

	/**
	 * Whether the Master features may run (checked by every Master route and job).
	 *
	 * @return bool
	 */
	public function master() {
		return self::MODE_MASTER === $this->mode();
	}

	/**
	 * Start the features. Off, nothing is registered: menus and routes are
	 * absent and jobs do not run; data is kept.
	 *
	 * @return void
	 */
	public function start() {
		if ( $this->started || ! $this->active() ) {
			return;
		}
		$this->started = true;
		self::remember_host( $this->host->get_id() );
		Storage\Schema::maybe_migrate();
		add_filter( 'qcld_wpbot_core/adopted_sections', array( __CLASS__, 'adopt_section' ) );
		Migration\From_Crm::hook();
		Capture\Chatbot::hook();
		Capture\Signup_Form::hook();
		Capture\WPForms::hook();
		Sending\Links::hook();
		Sending\Queue::hook();
		Sending\Confirmation::hook();
		Journeys\Engine::hook();
		Privacy\Privacy::hook();
		Audience\Exporter::hook();
		Admin\Admin_Controller::hook();
		add_action( self::DAILY, array( Autopilot\Activity::class, 'prune' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
		add_action( 'rest_api_init', array( Rest\Rest_Controller::class, 'register' ) );

		/**
		 * Fires when AI Marketing's features have started (mode "essentials"
		 * or "master"; check Module::instance()->master() for Master-only work).
		 */
		do_action( 'qcld_wpbot_marketing/started' );
	}

	/**
	 * Whether the features started on this request.
	 *
	 * @return bool
	 */
	public function started() {
		return $this->started;
	}

	/**
	 * Master features the Pro module registered (id => label). Empty outside
	 * Master mode, so the screens show previews instead.
	 *
	 * @return array<string,string>
	 */
	public function pro_features() {
		if ( ! $this->master() ) {
			return array();
		}
		/**
		 * Filters the Master features the Pro module provides.
		 *
		 * @param array<string,string> $features Feature id => label.
		 */
		$features = apply_filters( 'qcld_wpbot_marketing/pro/features', array() );
		return is_array( $features ) ? $features : array();
	}

	/**
	 * Record a host that runs AI Marketing, for the uninstall helper: data can
	 * go only when the last of them is uninstalled.
	 *
	 * @param string $host_id Host id.
	 * @return void
	 */
	public static function remember_host( $host_id ) {
		$host_id = (string) $host_id;
		if ( '' === $host_id ) {
			return;
		}
		$hosts = get_option( self::HOSTS_OPTION, array() );
		$hosts = is_array( $hosts ) ? array_map( 'strval', $hosts ) : array();
		if ( ! in_array( $host_id, $hosts, true ) ) {
			$hosts[] = $host_id;
			update_option( self::HOSTS_OPTION, array_values( $hosts ), true );
		}
	}

	/**
	 * Use WPBot Core's permission grid for the "marketing" section.
	 *
	 * @param string[] $sections Adopted sections.
	 * @return string[]
	 */
	public static function adopt_section( $sections ) {
		$sections   = (array) $sections;
		$sections[] = self::ID;
		return array_values( array_unique( $sections ) );
	}
}
