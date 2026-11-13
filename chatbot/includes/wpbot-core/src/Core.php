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
		Connections\Social_Auth::hook();
		add_action( 'deleted_post', array( Map\Keyword_Map::class, 'forget_post' ) );
		// SEO & AI Search additions keep their own migration track.
		Storage\Seo_Schema::hook();
		Knowledge\Kb::hook();
		// AI spend (rebuild 6) keeps its own migration track.
		Spend\Spend::hook();
		Diagnostics\Error_Log::hook();
		Diagnostics\Error_Capture::hook();
		Diagnostics\Js_Reporter::hook();
		Diagnostics\Support_Report::hook();
		require_once __DIR__ . '/Diagnostics/functions.php';
		add_action( 'qcld_wpbot_core/log_error', 'qcld_wpbot_core_log_error', 10, 3 );
		require_once __DIR__ . '/Spend/functions.php';
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
		add_filter( 'qcld_wpbot_core/module_pages', array( __CLASS__, 'core_pages' ), 5 );
		add_action( 'admin_enqueue_scripts', array( AI\AI_Readiness::class, 'register_script' ), 5 );
		add_action( 'admin_init', array( AI\Keys::class, 'repair_unsaved' ) );

		if ( is_admin() ) {
			Admin\Controls::hook();
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
		( new Rest\Spend_Controller() )->register_routes();
		( new Rest\Diagnostics_Controller() )->register_routes();
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
	 * The host whose menus and branding are used: the primary host (see primary_host_id()).
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		$id = $this->primary_host_id();
		return '' !== $id ? $this->hosts[ $id ] : null;
	}

	/**
	 * Id of the primary host: of the hosts registered in this request, the one stored first in
	 * qcld_wpbot_core_hosts, which is the plugin installed first. Menus then stay where they
	 * are when a second plugin that bundles WPBot Core is activated. Every module follows this
	 * one rule (SEO & AI Search and AI Writer ask for it), so all pages sit under one host.
	 *
	 * @return string '' when no host is registered.
	 */
	public function primary_host_id() {
		if ( array() === $this->hosts ) {
			return '';
		}
		$ids     = array_keys( $this->hosts );
		$default = $ids[0];
		$known   = get_option( self::HOSTS_OPTION, array() );
		foreach ( is_array( $known ) ? $known : array() as $known_id ) {
			if ( isset( $this->hosts[ (string) $known_id ] ) ) {
				$default = (string) $known_id;
				break;
			}
		}

		/**
		 * Choose the primary host when several plugins bundle WPBot Core.
		 *
		 * @param string   $id  Default: the registered host that was installed first.
		 * @param string[] $ids All registered host ids, in registration order.
		 */
		$id = (string) apply_filters( 'qcld_wpbot_core/primary_host', $default, $ids );

		return isset( $this->hosts[ $id ] ) ? $id : $default;
	}

	/**
	 * Every registered host, primary first, then in install order.
	 *
	 * @return Host_Adapter[] id => adapter.
	 */
	public function hosts() {
		$primary = $this->primary_host_id();
		if ( '' === $primary ) {
			return array();
		}
		$ordered = array( $primary => $this->hosts[ $primary ] );
		$known   = get_option( self::HOSTS_OPTION, array() );
		foreach ( array_merge( is_array( $known ) ? $known : array(), array_keys( $this->hosts ) ) as $id ) {
			$id = (string) $id;
			if ( isset( $this->hosts[ $id ] ) && ! isset( $ordered[ $id ] ) ) {
				$ordered[ $id ] = $this->hosts[ $id ];
			}
		}
		return $ordered;
	}

	/**
	 * The host whose AI Settings hold the AI keys: the primary host when it has AI Settings,
	 * else the first other host that does. A host has AI Settings when it gives an AI settings
	 * page or providers to import. Null when no host has them (for example SEO Help on its own):
	 * modules then use their own keys.
	 *
	 * @return Host_Adapter|null
	 */
	public function ai_host() {
		foreach ( $this->hosts() as $host ) {
			if ( '' !== (string) $host->get_ai_settings_url() ) {
				return $host;
			}
			$import = (array) $host->get_ai_import();
			if ( ! empty( $import['providers'] ) ) {
				return $host;
			}
		}
		return null;
	}

	/**
	 * The shared module pages, with their URL under the primary host, for a host that is not
	 * primary to link to from its own menu. Modules add theirs through the filter.
	 *
	 * @return array<int,array{id:string,title:string,url:string}>
	 */
	public static function module_pages() {
		/**
		 * Shared module pages: id, title (translated) and admin URL.
		 *
		 * @param array $pages Pages.
		 */
		$pages = (array) apply_filters( 'qcld_wpbot_core/module_pages', array() );
		$out   = array();
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && ! empty( $page['id'] ) && ! empty( $page['url'] ) ) {
				$out[] = array(
					'id'    => sanitize_key( (string) $page['id'] ),
					'title' => isset( $page['title'] ) ? wp_strip_all_tags( (string) $page['title'] ) : '',
					'url'   => esc_url_raw( (string) $page['url'] ),
				);
			}
		}
		return $out;
	}

	/**
	 * WPBot Core's own shared pages for module_pages(): Business Context and Permissions.
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function core_pages( $pages ) {
		$pages   = (array) $pages;
		$pages[] = array(
			'id'    => 'business-context',
			'title' => __( 'Business Context', 'chatbot' ),
			'url'   => Context\Business_Context_Page::url(),
		);
		$pages[] = array(
			'id'    => 'permissions',
			'title' => __( 'Permissions', 'chatbot' ),
			'url'   => admin_url( 'admin.php?page=' . Permissions\Admin_Page::SLUG ),
		);
		return $pages;
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
