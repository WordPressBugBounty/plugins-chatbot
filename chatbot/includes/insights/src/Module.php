<?php
/**
 * Service container and entry point of WPBot Insights.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights;

use QuantumCloud\Insights\Admin\Admin_Controller;
use QuantumCloud\Insights\Admin\Rest_Admin_Controller;
use QuantumCloud\Insights\AI\AI_Client;
use QuantumCloud\Insights\AI\AI_Settings;
use QuantumCloud\Insights\AI\Null_AI_Client;
use QuantumCloud\Insights\Features\Feature_Registry;
use QuantumCloud\Insights\Host\Host_Adapter;
use QuantumCloud\Insights\Ingest\Rest_Controller;
use QuantumCloud\Insights\Privacy\Privacy_Tools;
use QuantumCloud\Insights\Processing\Scheduler;
use QuantumCloud\Insights\Reports\Reports;
use QuantumCloud\Insights\Storage\Local_File_Store;
use QuantumCloud\Insights\Storage\Recording_Store;
use QuantumCloud\Insights\Storage\Schema;
use QuantumCloud\Insights\Tracker\Tracker_Loader;

defined( 'ABSPATH' ) || exit;

final class Module {

	const HOSTS_OPTION = 'qcin_hosts';
	const TOKEN_OPTION = 'qcin_site_token';

	/**
	 * Singleton.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * Registered hosts by id.
	 *
	 * @var array<string,Host_Adapter>
	 */
	private $hosts = array();

	/**
	 * Feature registry.
	 *
	 * @var Feature_Registry
	 */
	private $registry;

	/**
	 * Recording store.
	 *
	 * @var Recording_Store|null
	 */
	private $store = null;

	/**
	 * Whether hooks are in place.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Instance.
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
	 * Constructor.
	 */
	private function __construct() {
		$this->registry = new Feature_Registry();
	}

	/**
	 * Register a host plugin. The first host becomes the primary host
	 * (owns the admin menu) unless `qcin/primary_host` says otherwise.
	 *
	 * @param Host_Adapter $adapter Adapter.
	 */
	public function register_host( Host_Adapter $adapter ) {
		$this->hosts[ $adapter->get_id() ] = $adapter;

		$known = (array) get_option( self::HOSTS_OPTION, array() );
		if ( ! in_array( $adapter->get_id(), $known, true ) ) {
			$known[] = $adapter->get_id();
			update_option( self::HOSTS_OPTION, array_values( $known ), false );
		}

		if ( ! $this->booted ) {
			$this->boot();
		}
	}

	/**
	 * Primary host.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		$first = $this->hosts ? reset( $this->hosts ) : null;
		/**
		 * Choose which registered host owns the admin screens.
		 *
		 * @param Host_Adapter|null           $host  Default (first registered).
		 * @param array<string,Host_Adapter> $hosts All hosts.
		 */
		$host = apply_filters( 'qcin/primary_host', $first, $this->hosts );
		return $host instanceof Host_Adapter ? $host : $first;
	}

	/**
	 * AI client for Insights' own AI settings (active provider, key, model).
	 * Returns a client that is not available while AI is switched off.
	 *
	 * @return AI_Client
	 */
	public function ai() {
		$client = AI_Settings::is_ready() ? AI_Settings::client() : new Null_AI_Client();
		/**
		 * Replace the AI client (e.g. a Pro feature or a custom gateway).
		 *
		 * @param AI_Client $client Client.
		 */
		$filtered = apply_filters( 'qcin/ai_client', $client );
		return $filtered instanceof AI_Client ? $filtered : $client;
	}

	/**
	 * Recording store.
	 *
	 * @return Recording_Store
	 */
	public function store() {
		if ( null === $this->store ) {
			/**
			 * Replace where recordings are stored.
			 *
			 * @param Recording_Store $store Default local file store.
			 */
			$store       = apply_filters( 'qcin/recording_store', new Local_File_Store() );
			$this->store = $store instanceof Recording_Store ? $store : new Local_File_Store();
		}
		return $this->store;
	}

	/**
	 * Feature registry.
	 *
	 * @return Feature_Registry
	 */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Public token the tracker sends with every batch.
	 *
	 * @return string
	 */
	public function site_token() {
		$token = get_option( self::TOKEN_OPTION );
		if ( ! is_string( $token ) || 32 !== strlen( $token ) ) {
			$token = wp_generate_password( 32, false, false );
			update_option( self::TOKEN_OPTION, $token, true );
		}
		return $token;
	}

	/**
	 * Put all hooks in place.
	 */
	private function boot() {
		$this->booted = true;

		add_action( 'init', array( Schema::class, 'maybe_install' ), 5 );
		add_action( 'init', array( Settings::class, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( $this, 'register_features' ), 20 );

		( new Tracker_Loader() )->register();
		( new Scheduler() )->register();
		( new Reports() )->register();
		( new Privacy_Tools() )->register();

		add_action(
			'rest_api_init',
			static function () {
				( new Rest_Controller() )->register_routes();
				( new Rest_Admin_Controller() )->register_routes();
			}
		);

		if ( is_admin() ) {
			( new Admin_Controller() )->register();
		}
	}

	/**
	 * Let core and extension features register themselves.
	 */
	public function register_features() {
		/**
		 * Add features (WPBot Pro, other hosts).
		 *
		 * @param Feature_Registry $registry Registry.
		 */
		do_action( 'qcin/register_features', $this->registry );
		$this->registry->register_all( $this );
	}

	/**
	 * Capability needed for Insights screens and admin endpoints.
	 *
	 * Contexts used by the core: 'view' (screens, recordings, heatmaps) and
	 * 'settings' (changing settings, AI keys, deleting all data). Features
	 * may ask for their own contexts; unknown contexts get the host default.
	 *
	 * @param string $context What the capability is for.
	 * @return string
	 */
	public function capability( $context = 'view' ) {
		$host = $this->host();
		$cap  = $host ? $host->get_capability() : 'manage_options';
		/**
		 * Capability for one part of Insights (role-based access).
		 *
		 * @param string $cap     Capability. Default: the host's capability.
		 * @param string $context 'view', 'settings' or a feature's own context.
		 */
		$filtered = apply_filters( 'qcin/capability', $cap, (string) $context );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $cap;
	}

	/**
	 * Whether the current user may use one part of Insights.
	 *
	 * @param string $context Context, see capability().
	 * @return bool
	 */
	public function can( $context = 'view' ) {
		return current_user_can( $this->capability( $context ) );
	}
}
