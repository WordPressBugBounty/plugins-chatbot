<?php
/**
 * Wires the asset cache together and exposes its public API.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

use QuantumCloud\AssetOptimizer\Host\Host_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * The module (one per request). Reach it with wpbot_asset_cache().
 */
final class Module {

	/**
	 * Daily cleanup cron event.
	 */
	const CRON_CLEANUP = 'wpbot_ao_cleanup';

	/**
	 * The instance.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * Host adapter, once registered.
	 *
	 * @var Host_Adapter|null
	 */
	private $host = null;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Storage.
	 *
	 * @var Storage|null
	 */
	private $storage = null;

	/**
	 * Registry.
	 *
	 * @var Registry|null
	 */
	private $registry = null;

	/**
	 * Processor (builds cached files).
	 *
	 * @var Processor|null
	 */
	private $processor = null;

	/**
	 * The module.
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
		$this->settings = new Settings();
		$this->state    = new State();
	}

	/**
	 * Register the plugin that bundles the module. Only the first host counts:
	 * WPBot Pro blocks WPBot Free's code, so there is one host per site.
	 *
	 * @param Host_Adapter $host Host adapter.
	 * @return bool Whether the host was registered.
	 */
	public function register_host( Host_Adapter $host ) {
		if ( null !== $this->host ) {
			return false;
		}
		$this->host      = $host;
		$this->storage   = new Storage( $host->get_package() );
		$this->registry  = new Registry( $host, $this->settings );
		$this->processor = new Processor( $this );
		$this->boot();
		return true;
	}

	/**
	 * Start the parts this request needs.
	 *
	 * @return void
	 */
	private function boot() {
		$existing = $this->host->is_existing_install();
		if ( $this->settings->initialise( $existing ) && $existing ) {
			$this->state->set( 'invite', true );
		}
		if ( QCLD_WPBOT_AO_VERSION !== $this->state->get( 'version' ) ) {
			$this->state->set( 'version', QCLD_WPBOT_AO_VERSION );
		}

		register_deactivation_hook( $this->host->get_plugin_file(), array( __CLASS__, 'deactivate' ) );
		add_action( self::CRON_CLEANUP, array( $this, 'cleanup' ) );
		$this->processor->register();

		$active = '' === $this->settings->kill_switch();
		if ( $active ) {
			( new Safety( $this ) )->register();
			( new Compat( $this ) )->register();
			Combine::register_always( $this );
		}
		if ( is_admin() ) {
			( new Admin\Admin( $this ) )->register();
			add_action( 'admin_init', array( $this, 'schedule_cleanup' ) );
			if ( $active ) {
				( new Fresh( $this ) )->register();
			}
		} elseif ( $active ) {
			$frontend = new Frontend( $this );
			$frontend->register();
			if ( $this->settings->get( 'combine' ) ) {
				$frontend->set_combine( new Combine( $this, $frontend ) );
			}
			( new Fresh( $this, $frontend ) )->register();
		}

		/**
		 * Fires once the module has a host and is set up for this request.
		 *
		 * @param Module $module The module.
		 */
		do_action( 'qcld_wpbot_ao_ready', $this );
	}

	/**
	 * The host adapter.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		return $this->host;
	}

	/**
	 * Settings.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * State.
	 *
	 * @return State
	 */
	public function state() {
		return $this->state;
	}

	/**
	 * Storage.
	 *
	 * @return Storage|null
	 */
	public function storage() {
		return $this->storage;
	}

	/**
	 * Registry.
	 *
	 * @return Registry|null
	 */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Processor.
	 *
	 * @return Processor|null
	 */
	public function processor() {
		return $this->processor;
	}

	/**
	 * Whether cached files are served on this site right now (the switch is
	 * on, no kill switch is set and a host is registered).
	 *
	 * @return bool
	 */
	public function is_active() {
		return null !== $this->host && '' === $this->settings->kill_switch();
	}

	/**
	 * Delete every cached file of this package.
	 *
	 * @return int Files deleted.
	 */
	public function clear() {
		if ( null === $this->storage ) {
			return 0;
		}
		$deleted = $this->storage->clear();
		$this->state->reset_files();

		/**
		 * Fires after the WPBot asset cache was cleared.
		 *
		 * @param int $deleted Files deleted.
		 */
		do_action( 'wpbot_asset_cache_cleared', $deleted );
		return $deleted;
	}

	/**
	 * Build every known file again now (from the current file map).
	 *
	 * @return array{built: int, failed: int}
	 */
	public function rebuild() {
		if ( null === $this->processor ) {
			return array(
				'built'  => 0,
				'failed' => 0,
			);
		}
		return $this->processor->rebuild_all();
	}

	/**
	 * Everything the status panel shows.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status() {
		$storage = $this->storage;
		$stats   = $storage ? $storage->stats() : array(
			'count' => 0,
			'bytes' => 0,
		);
		return array(
			'version'         => defined( 'QCLD_WPBOT_AO_VERSION' ) ? QCLD_WPBOT_AO_VERSION : '',
			'enabled'         => $this->settings->is_enabled(),
			'active'          => $this->is_active(),
			'kill_switch'     => $this->settings->kill_switch(),
			'folder'          => $storage ? $storage->path() : '',
			'writable'        => $storage ? $storage->is_writable() : false,
			'count'           => $stats['count'],
			'bytes'           => $stats['bytes'],
			'files'           => (array) $this->state->get( 'files' ),
			'repaired'        => (array) $this->state->get( 'repaired' ),
			'skipped'         => (array) $this->state->get( 'skipped' ),
			'last_error'      => (string) $this->state->get( 'last_error' ),
			'detected'        => Compat::detect(),
			'automatic'       => Compat::automatic(),
			'purgeable'       => Compat::purgeable(),
			'nginx'           => self::is_nginx(),
			'folder_url_path' => $storage ? (string) wp_parse_url( $storage->base()['url'], PHP_URL_PATH ) : '',
		);
	}

	/**
	 * Whether the web server is nginx (it ignores .htaccess).
	 *
	 * @return bool
	 */
	public static function is_nginx() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		return false !== stripos( $software, 'nginx' );
	}

	/**
	 * Schedule the daily cleanup (admin requests only, so the front end pays nothing).
	 *
	 * @return void
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_CLEANUP );
		}
	}

	/**
	 * Daily cleanup: remove superseded files older than 30 days.
	 *
	 * @return int Files deleted.
	 */
	public function cleanup() {
		if ( null === $this->storage ) {
			return 0;
		}
		$this->state->refresh();
		return $this->storage->cleanup( $this->state->keys_in_use(), 30 * DAY_IN_SECONDS );
	}

	/**
	 * Host deactivated: stop the cleanup event.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_CLEANUP );
	}
}
