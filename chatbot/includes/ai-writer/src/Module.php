<?php
/**
 * AI Writer module: host registry, boot order, feature registry.
 *
 * The core never checks which edition is installed. Pro features register
 * through qcld_wpbot_writer/register_features and the screens show whatever
 * has been registered.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter;

use QuantumCloud\AiWriter\Features\Feature_Registry;
use QuantumCloud\AiWriter\Host\Host_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that wires AI Writer into WordPress.
 */
final class Module {

	/**
	 * Module id: also the permissions section and the key-set id in WPBot Core.
	 */
	const ID = 'writer';

	const HOSTS_OPTION = 'qcld_wpbot_writer_hosts';

	/**
	 * Instance.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * Hosts, keyed by id.
	 *
	 * @var Host_Adapter[]
	 */
	private $hosts = array();

	/**
	 * Feature registry.
	 *
	 * @var Feature_Registry
	 */
	private $features;

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the instance.
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
	 * Hook everything. Called once by the loader.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted   = true;
		$this->features = new Feature_Registry();

		add_filter( 'qcld_wpbot_core/adopted_sections', array( $this, 'adopt_section' ) );
		add_filter( 'qcld_wpbot_core/job_handlers', array( $this, 'job_handlers' ) );

		add_action( 'init', array( Storage\Schema::class, 'maybe_install' ), 5 );
		add_action( 'init', array( $this, 'register_features' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		Rest\Output_Guard::hook();
		Admin\Ai_Readiness::hook();
		Admin\Jsx_Runtime::hook();
		Seo\Seo_Adapter::hook();
		Improve\Updates::hook();
		Improve\Decay_Alerts::hook();
		Woo\Woo::hook();
		Topics\Topic_Finder::hook();
		Topics\Map_Sync::hook();
		Topics\Vault_Link::hook();
		Writing\Link_From::hook();
		Writing\Publish_Events::hook();
		Persuasion\Persuasion::hook();
		Formats\Schema::hook();
		Formats\Gather::hook();
		Formats\Research_Data::hook();
		Formats\Downloads::hook();
		Formats\Stale_Figures::hook();
		Editor\Editor_Assets::hook();

		if ( is_admin() ) {
			( new Admin\Admin_Controller() )->hook();
		}
	}

	/**
	 * Switch the AI Writer section to the WPBot permissions grid.
	 *
	 * @param string[] $ids Adopted section ids.
	 * @return string[]
	 */
	public function adopt_section( $ids ) {
		$ids[] = self::ID;
		return $ids;
	}

	/**
	 * Give WPBot Core the step handlers of AI Writer (and of its extensions).
	 *
	 * @param array $handlers kind => callable.
	 * @return array
	 */
	public function job_handlers( $handlers ) {
		$own = array_merge(
			array(
				'writer_test_wait' => array( Jobs\Test_Steps::class, 'wait' ),
				'writer_test_ai'   => array( Jobs\Test_Steps::class, 'ai_ping' ),
			),
			Writing\Article_Steps::handlers(),
			Improve\Improve_Job::handlers(),
			Topics\Topic_Finder::handlers()
		);

		/**
		 * Add AI Writer step handlers: kind => callable( array $step, array $job ).
		 *
		 * @param array $own Handlers.
		 */
		$own = (array) apply_filters( 'qcld_wpbot_writer/job_steps', $own );

		return array_merge( (array) $handlers, $own );
	}

	/**
	 * Let extensions (the Pro module) register features, then run them.
	 *
	 * @return void
	 */
	public function register_features() {
		/**
		 * Register features.
		 *
		 * @param Feature_Registry $registry Registry.
		 */
		do_action( 'qcld_wpbot_writer/register_features', $this->features );
		$this->features->register_all( $this );
	}

	/**
	 * Register REST controllers.
	 *
	 * @return void
	 */
	public function register_routes() {
		( new Rest\Settings_Controller() )->register_routes();
		( new Rest\Diagnostics_Controller() )->register_routes();
		( new Rest\Write_Controller() )->register_routes();
		( new Rest\Regenerate_Controller() )->register_routes();
		( new Rest\Import_Controller() )->register_routes();
		( new Rest\Research_Controller() )->register_routes();
		( new Rest\Voice_Controller() )->register_routes();
		( new Rest\Editor_Controller() )->register_routes();
		( new Rest\Improve_Controller() )->register_routes();
		( new Rest\Calendar_Controller() )->register_routes();
		( new Rest\Topics_Controller() )->register_routes();
		( new Rest\Library_Controller() )->register_routes();
	}

	/**
	 * Register the host that bundles this copy.
	 *
	 * @param Host_Adapter $host Adapter.
	 * @return void
	 */
	public function register_host( Host_Adapter $host ) {
		$id                 = sanitize_key( $host->get_id() );
		$this->hosts[ $id ] = $host;
		$known              = get_option( self::HOSTS_OPTION, array() );
		$known              = is_array( $known ) ? $known : array();
		if ( ! in_array( $id, $known, true ) ) {
			$known[] = $id;
			update_option( self::HOSTS_OPTION, $known, false );
		}
	}

	/**
	 * The primary host.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		if ( array() === $this->hosts ) {
			return null;
		}
		$ids = array_keys( $this->hosts );

		/**
		 * Choose the primary host when several plugins bundle AI Writer.
		 *
		 * @param string   $id  Default: first registered.
		 * @param string[] $ids All host ids.
		 */
		$id = (string) apply_filters( 'qcld_wpbot_writer/primary_host', $ids[0], $ids );
		return isset( $this->hosts[ $id ] ) ? $this->hosts[ $id ] : $this->hosts[ $ids[0] ];
	}

	/**
	 * Feature registry.
	 *
	 * @return Feature_Registry
	 */
	public function features() {
		return $this->features;
	}

	/**
	 * Capability for a level of the AI Writer section.
	 *
	 * @param string $level view, use, publish or settings.
	 * @return string
	 */
	public static function cap( $level ) {
		return 'wpbot_' . self::ID . '_' . $level;
	}

	/**
	 * URL of a file inside this module.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function url( $path ) {
		return plugins_url( ltrim( $path, '/' ), QCLD_WPBOT_WRITER_FILE );
	}
}
