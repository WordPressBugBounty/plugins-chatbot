<?php
/**
 * The booted chatbot module: holds the host adapter and the module registry.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot;

use QuantumCloud\Chatbot\Host\Host_Adapter;
use QuantumCloud\Chatbot\Modules\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Module singleton.
 */
final class Module {

	/**
	 * Single instance.
	 *
	 * @var Module|null
	 */
	private static $instance = null;

	/**
	 * Host adapter, once the host registered.
	 *
	 * @var Host_Adapter|null
	 */
	private $host = null;

	/**
	 * Module registry.
	 *
	 * @var Registry
	 */
	private $registry;

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
	 * Set up services.
	 */
	private function __construct() {
		$this->registry = new Registry( new Modules\Option_State_Store() );
	}

	/**
	 * Called once by the loader. Hosts register their adapter on the action below.
	 *
	 * @return void
	 */
	public function boot() {
		/**
		 * The chatbot module is loaded. Hosts call register_host() here.
		 *
		 * @param Module $module The module.
		 */
		do_action( 'qcld_wpbot_chat/loaded', $this );
	}

	/**
	 * Register the host plugin. The first host wins; Free stops itself when Pro
	 * is active, so only one host ever registers on a normal site.
	 *
	 * @param Host_Adapter $host Host adapter.
	 * @return void
	 */
	public function register_host( Host_Adapter $host ) {
		if ( null !== $this->host ) {
			return;
		}
		$this->host = $host;
		$this->registry->set_base_dir( $host->modules_dir() );
		Knowledge\Kb_Bridge::init();
		Knowledge\Faq::init();
		Diagnostics\Error_Log_Screen::init();
		Settings\Options::reset();
		add_action( 'init', array( Sessions\Schema::class, 'maybe_install' ), 5 );
		Train\Gaps::init();
		Ai\Routing::init();
		Ai\Budget::init();
		Spend\Fallback::init();
		Spend\Spend_Screen::init();
		RateLimit\Role_Limits::init();
		Admin\Permission_Sections::init();
		Abilities\Abilities::init();
		Rest\Routes::init();
		Sessions\Privacy::init();
		Widget\Lazy_Loader::init( $host );
		Legacy\Shortcodes::init();
		if ( wp_doing_ajax() ) {
			Legacy\Ajax_Shims::init();
			Legacy\Ai_Bridge::init();
		}
		if ( is_admin() ) {
			Admin\Provider_Fields::init();
			Admin\Kb_Panel::init();
			Admin\Answer_Scope_Field::init();
			Admin\Cost_Widget::init();
			Admin\Retired_Model_Notice::init();
			Admin\Admin_Pages::init();
			Admin\Ai_Settings_Page::init();
			Admin\Answer_Cache_Screen::init();
			Admin\Removed_Settings_Notice::init();
			Admin\Train_Page::init();
			Admin\Features_Page::init();
		}
		/**
		 * The host is known. Pro and add-ons register their features here.
		 *
		 * @param Module $module The module.
		 */
		do_action( 'qcld_wpbot_chat/host_ready', $this );
	}

	/**
	 * Host adapter, or null before a host registered.
	 *
	 * @return Host_Adapter|null
	 */
	public function host() {
		return $this->host;
	}

	/**
	 * Module registry (what Module Manager lists).
	 *
	 * @return Registry
	 */
	public function modules() {
		return $this->registry;
	}
}
