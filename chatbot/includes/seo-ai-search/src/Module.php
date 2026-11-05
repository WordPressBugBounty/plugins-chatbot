<?php
/**
 * SEO & AI Search module: wires settings, data, screens and hooks.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch;

use QuantumCloud\SeoAiSearch\Features\Feature_Registry;
use QuantumCloud\SeoAiSearch\Host\Host_Adapter;
use QuantumCloud\WPBotCore\Connections\Google as Google_Connection;

defined( 'ABSPATH' ) || exit;

/**
 * The running module (one instance).
 */
final class Module {

	/**
	 * Permissions section and WPBot Core key-set id.
	 */
	const ID = 'seo';

	/**
	 * Option listing the hosts that bundle the module.
	 */
	const HOSTS_OPTION = 'qcld_wpbot_seo_hosts';

	/**
	 * Instance.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * Registered hosts by id.
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
	 * The instance.
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
	 * Add the module's hooks.
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
		add_filter( 'qcld_wpbot_core/google_scopes', array( $this, 'google_scopes' ) );
		add_filter( 'qcld_wpbot_core/ai_settings_url', array( $this, 'ai_settings_url' ), 10, 2 );

		add_action( 'init', array( Storage\Schema::class, 'maybe_install' ), 5 );
		add_action( 'init', array( $this, 'register_features' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		Admin\Jsx_Runtime::hook();
		Google\Gsc_Pull::hook();
		Google\Ga_Pull::hook();
		Opportunities\Opportunities::hook();
		Opportunities\Site_Types::hook();
		Opportunities\Content_Types::hook();
		Opportunities\First_Scan::hook();
		Llms\Llms_Txt::hook();
		Onpage\Audit_History::hook();
		Ads\Ga_Ads_Pull::hook();
		Ads\Paid_Searches::hook();
		Writer\Writer_Bridge::hook();
		Research\Vault_Service::hook();
		Research\Competitor_List::hook();
		Onpage\Audit::hook();
		Onpage\Auto_Meta::hook();
		Onpage\Editor_Panel::hook();
		Seo\Head_Output::hook();
		Technical\Not_Found_Log::hook();
		Technical\Redirects::hook();
		Technical\Schema_Output::hook();
		Readiness\Ai_Crawlers::hook();
		Local\Local_Schema::hook();
		Results\Results::hook();
		Reports\Weekly_Email::hook();
		Seo\Legacy_Output::hook();
		Links\Links::hook();
		Cited\Site_Facts::hook();
		Cited\Context_Sources_Seo::hook();
		Keywords\Free_Data::hook();
		Local\Gbp::hook();
		Keywords\Map_Sync::hook();
		add_filter( 'qcld_wpbot_seo/opportunity_types', array( Keywords\Cannibalization::class, 'opportunity_type' ) );
		add_filter( 'qcld_wpbot_seo/opportunity_reason', array( Keywords\Cannibalization::class, 'reason' ), 10, 3 );

		if ( is_admin() ) {
			( new Admin\Admin_Controller() )->hook();
		}
	}

	/**
	 * Use the WPBot > Permissions grid for the "seo" section.
	 *
	 * @param array $ids Adopted section ids.
	 * @return array
	 */
	public function adopt_section( $ids ) {
		$ids[] = self::ID;
		return $ids;
	}

	/**
	 * Job step handlers (core first, then extensions').
	 *
	 * @param array $handlers Handlers from other modules.
	 * @return array
	 */
	public function job_handlers( $handlers ) {
		$own = array_merge( Google\Gsc_Pull::handlers(), Google\Ga_Pull::handlers(), Onpage\Audit::handlers(), Onpage\Auto_Meta::handlers(), Technical\Scan::handlers(), Links\Link_Checker::handlers(), Keywords\Map_Sync::handlers(), Research\Competitor_Fetch::handlers(), Ads\Ga_Ads_Pull::handlers(), Cited\Places::handlers() );
		/**
		 * Job step handlers of the module; the Pro module adds its own.
		 *
		 * @param array $own kind => callable( array $step, array $job ).
		 */
		$own = (array) apply_filters( 'qcld_wpbot_seo/job_steps', $own );
		return array_merge( (array) $handlers, $own );
	}

	/**
	 * This module's "Settings & AI Provider" page, for WPBot Core's shared
	 * AI notice (follow-up 3.0).
	 *
	 * @param string $url    Default URL.
	 * @param string $module Module id.
	 * @return string
	 */
	public function ai_settings_url( $url, $module ) {
		return self::ID === $module ? admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=settings&section=ai' ) : $url;
	}

	/**
	 * Ask for read-only Google Analytics access with Search Console.
	 *
	 * @param array $scopes Scopes.
	 * @return array
	 */
	public function google_scopes( $scopes ) {
		if ( Settings::get( 'use_analytics' ) ) {
			$scopes[] = Google_Connection::SCOPE_ANALYTICS;
		}
		return $scopes;
	}

	/**
	 * Let the module and extensions register features.
	 *
	 * @return void
	 */
	public function register_features() {
		/**
		 * Register features (the Pro module adds its own here).
		 *
		 * @param Feature_Registry $registry Registry.
		 */
		do_action( 'qcld_wpbot_seo/register_features', $this->features );
		$this->features->register_all( $this );
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		( new Rest\Settings_Controller() )->register_routes();
		( new Rest\Data_Controller() )->register_routes();
		( new Rest\Opportunities_Controller() )->register_routes();
		( new Rest\Fixes_Controller() )->register_routes();
		( new Rest\Technical_Controller() )->register_routes();
		( new Rest\Readiness_Controller() )->register_routes();
		( new Rest\Results_Controller() )->register_routes();
		( new Rest\Links_Controller() )->register_routes();
		( new Rest\Research_Controller() )->register_routes();
		( new Rest\Audit_Controller() )->register_routes();
		( new Rest\Ads_Controller() )->register_routes();
		( new Rest\Keywords_Controller() )->register_routes();
		( new Rest\Map_Controller() )->register_routes();
		( new Rest\Competitors_Controller() )->register_routes();
		( new Rest\Llms_Controller() )->register_routes();
		( new Rest\Cited_Controller() )->register_routes();
		( new Rest\Gbp_Controller() )->register_routes();
	}

	/**
	 * Register a host plugin.
	 *
	 * @param Host_Adapter $host Host.
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
	 * The primary host, or null.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		if ( array() === $this->hosts ) {
			return null;
		}
		$ids = array_keys( $this->hosts );
		/**
		 * Which host leads when several bundle the module.
		 *
		 * @param string   $id  Default: the first registered.
		 * @param string[] $ids Registered ids.
		 */
		$id = (string) apply_filters( 'qcld_wpbot_seo/primary_host', $ids[0], $ids );
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
	 * Capability for a permission level (view, use, publish, settings).
	 *
	 * @param string $level Level.
	 * @return string
	 */
	public static function cap( $level ) {
		return 'wpbot_' . self::ID . '_' . $level;
	}

	/**
	 * URL of a file inside the module.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function url( $path ) {
		return plugins_url( ltrim( $path, '/' ), QCLD_WPBOT_SEO_FILE );
	}
}
