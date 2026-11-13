<?php
/**
 * WPBot Social module: the network registry and boot sequence.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social;

use QuantumCloud\Social\Host\Host_Adapter;
use QuantumCloud\Social\Networks\Network;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the module and keeps the list of networks.
 */
final class Module {

	/**
	 * Module id used in WPBot Core (permissions section, jobs, usage).
	 */
	const ID = 'social';

	/**
	 * Run modes (see mode()).
	 */
	const MODE_FREE   = 'free';
	const MODE_MASTER = 'master';
	const MODE_OFF    = 'off';

	/**
	 * Hosts that ran Social on this site (see uninstall-helper.php).
	 */
	const HOSTS_OPTION = 'qcld_wpbot_social_hosts';

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
	 * Whether the licensed features started.
	 *
	 * @var bool
	 */
	private $started = false;

	/**
	 * Registered networks, keyed by id.
	 *
	 * @var array<string,Network>|null
	 */
	private $networks = null;

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
	 * Boot: wait for the host, then start when its mode allows (see mode()).
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'start' ), 5 );
	}

	/**
	 * Register the host adapter (from qcld_wpbot_social/loaded).
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
	 * Whether Social may run: the host's mode is "free" or "master" (see
	 * mode(); without Host\Mode_Host only the Master license counts,
	 * build-brief A1). Checked on every request,
	 * so a license change pauses or resumes Social without losing data.
	 *
	 * @return bool
	 */
	public function licensed() {
		return in_array( $this->mode(), array( self::MODE_FREE, self::MODE_MASTER ), true );
	}

	/**
	 * How Social runs on this request: "free", "master" or "off".
	 *
	 * A host implementing Host\Mode_Host states the mode (WPBot Free:
	 * "free"). Other hosts keep the original rule: "master" when
	 * get_license() is "master", else "off". The Master module starts only
	 * on "master".
	 *
	 * @return string
	 */
	public function mode() {
		if ( null === $this->host ) {
			return self::MODE_OFF;
		}
		if ( $this->host instanceof Host\Mode_Host ) {
			$mode = $this->host->get_social_mode();
			return in_array( $mode, array( self::MODE_FREE, self::MODE_MASTER ), true ) ? $mode : self::MODE_OFF;
		}
		return 'master' === $this->host->get_license() ? self::MODE_MASTER : self::MODE_OFF;
	}

	/**
	 * Start the licensed features: storage, permissions, screens, REST,
	 * connections and the daily checks. Off Master nothing is registered:
	 * menus and routes are absent and jobs do not run; data is kept.
	 *
	 * @return void
	 */
	public function start() {
		if ( $this->started || ! $this->licensed() ) {
			return;
		}
		$this->started = true;
		self::remember_host( $this->host->get_id() );
		Storage\Schema::maybe_migrate();
		add_filter( 'qcld_wpbot_core/adopted_sections', array( __CLASS__, 'adopt_section' ) );
		Accounts\Connector::hook();
		Accounts\Health::hook();
		Posts\Scheduler::hook();
		Posts\Notifications::hook();
		Posts\Auto_Share::hook();
		Integrations\Ai_Writer::hook();
		Integrations\Automator::hook();
		Calendar\Calendar_Items::hook();
		Admin\Admin_Controller::hook();
		Admin\Google_Card::hook();
		add_action( 'rest_api_init', array( Rest\Rest_Controller::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest\Posts_Controller::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest\Writing_Controller::class, 'register' ) );

		/**
		 * Fires when Social's features have started (mode "free" or "master";
		 * check Module::mode() for Master-only work).
		 */
		do_action( 'qcld_wpbot_social/started' );
	}

	/**
	 * Record a host that runs Social, for the uninstall helper: data goes only
	 * when the last of them is uninstalled.
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
	 * Use WPBot Core's permission grid for the "social" section.
	 *
	 * @param string[] $sections Adopted sections.
	 * @return string[]
	 */
	public static function adopt_section( $sections ) {
		$sections   = (array) $sections;
		$sections[] = self::ID;
		return array_values( array_unique( $sections ) );
	}

	/**
	 * Whether the licensed features started on this request.
	 *
	 * @return bool
	 */
	public function started() {
		return $this->started;
	}

	/**
	 * Every registered network adapter, keyed by id.
	 *
	 * The Master module adds its networks through the
	 * qcld_wpbot_social/networks filter.
	 *
	 * @return array<string,Network>
	 */
	public function networks() {
		if ( null === $this->networks ) {
			$list = array(
				new Networks\Facebook(),
				new Networks\Instagram(),
				new Networks\Threads(),
				new Networks\Google_Business(),
				new Networks\Telegram(),
				Networks\Manual_Network::whatsapp(),
			);

			/**
			 * Filters the network adapters.
			 *
			 * @param Network[] $list Adapters.
			 */
			$list = (array) apply_filters( 'qcld_wpbot_social/networks', $list );

			$this->networks = array();
			foreach ( $list as $network ) {
				if ( $network instanceof Network ) {
					$this->networks[ $network->id() ] = $network;
				}
			}
		}
		return $this->networks;
	}

	/**
	 * One network adapter by id.
	 *
	 * @param string $id Network id.
	 * @return Network|null
	 */
	public function network( $id ) {
		$networks = $this->networks();
		return isset( $networks[ $id ] ) ? $networks[ $id ] : null;
	}
}
