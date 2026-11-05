<?php
/**
 * WPBot Core module: host registry and boot order.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore;

use QuantumCloud\WPBotCore\Host\Host_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that owns the host adapter and wires every service to WordPress.
 */
final class Core {

	/**
	 * Option listing the hosts that bundle this module (used by uninstall).
	 */
	const HOSTS_OPTION = 'qcld_wpbot_core_hosts';

	/**
	 * Text domain placeholder; the host's sync script replaces it.
	 */
	const TEXT_DOMAIN = 'chatbot';

	/**
	 * Instance.
	 *
	 * @var Core|null
	 */
	private static $instance = null;

	/**
	 * Registered host adapters, keyed by id.
	 *
	 * @var Host_Adapter[]
	 */
	private $hosts = array();

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the instance.
	 *
	 * @return Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook every service into WordPress. Called once by the loader.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( Storage\Schema::class, 'maybe_install' ), 5 );
		add_action( 'init', array( Library\Item_Post_Type::class, 'register' ), 9 );

		Permissions\Grid::instance()->hook();
		Jobs\Cron_Runner::hook();
		Connections\Google::hook();
		Connections\Google_Ads::hook();
		Connections\YouTube::hook();
		add_action( 'deleted_post', array( Map\Keyword_Map::class, 'forget_post' ) );
		// SEO & AI Search additions keep their own migration track.
		Storage\Seo_Schema::hook();
		require_once __DIR__ . '/Keywords/functions.php';
		require_once __DIR__ . '/Vault/functions.php';
		require_once __DIR__ . '/Competitors/functions.php';
		require_once __DIR__ . '/Context/functions.php';
		Context\Context::hook();
		Context\Business_Context_Page::hook();
		require_once __DIR__ . '/Persuasion/functions.php';
		require_once __DIR__ . '/Experiments/functions.php';
		Experiments\Experiments::hook();
		add_action( 'init', array( Experiments\Front::class, 'hook' ), 20 );
		add_filter( 'qcld_wpbot_core/job_handlers', array( Persuasion\Voice_Of_Customer::class, 'handlers' ) );
		add_filter( 'qcld_wpbot_core/job_handlers', array( Keywords\Keyword_Data::class, 'handlers' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( AI\AI_Readiness::class, 'register_script' ), 5 );
		add_action( 'admin_init', array( AI\Keys::class, 'repair_unsaved' ) );

		if ( is_admin() ) {
			Permissions\Admin_Page::hook();
		}
	}

	/**
	 * Register the module's REST controllers.
	 *
	 * @return void
	 */
	public function register_routes() {
		( new Rest\Keys_Controller() )->register_routes();
		( new Rest\Jobs_Controller() )->register_routes();
		( new Rest\Usage_Controller() )->register_routes();
		( new Rest\Connections_Controller() )->register_routes();
		( new Rest\Keyword_Data_Controller() )->register_routes();
		( new Rest\Persuasion_Controller() )->register_routes();
		( new Rest\Experiments_Controller() )->register_routes();
		( new Rest\Context_Controller() )->register_routes();
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

		$known = get_option( self::HOSTS_OPTION, array() );
		$known = is_array( $known ) ? $known : array();
		if ( ! in_array( $id, $known, true ) ) {
			$known[] = $id;
			update_option( self::HOSTS_OPTION, $known, false );
		}
	}

	/**
	 * The host whose menus and branding are used.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		if ( array() === $this->hosts ) {
			return null;
		}
		$ids = array_keys( $this->hosts );

		/**
		 * Choose the primary host when several plugins bundle WPBot Core.
		 *
		 * @param string   $id  Default: the first registered host.
		 * @param string[] $ids All registered host ids.
		 */
		$id = (string) apply_filters( 'qcld_wpbot_core/primary_host', $ids[0], $ids );

		return isset( $this->hosts[ $id ] ) ? $this->hosts[ $id ] : $this->hosts[ $ids[0] ];
	}

	/**
	 * Absolute URL of a file inside this module.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function url( $path ) {
		return plugins_url( ltrim( $path, '/' ), QCLD_WPBOT_CORE_FILE );
	}
}
