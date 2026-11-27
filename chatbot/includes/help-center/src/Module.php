<?php
/**
 * The booted Help Center module: holds the host adapter and starts the features
 * when the Help Center is switched on.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

use QuantumCloud\HelpCenter\Host\Host_Adapter;

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
	 * Whether the features were started.
	 *
	 * @var bool
	 */
	private $started = false;

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
	 * Called once by the loader. Hosts register their adapter on the action below.
	 *
	 * @return void
	 */
	public function boot() {
		Rewrite_Flush::init();
		Admin\Ai_Settings_Section::init();
		add_action( 'admin_init', array( Feature_Switch::class, 'forget_registry_entry' ) );
		add_action( 'qcld_wpbot_help_center/switched', array( Knowledge::class, 'on_switch' ) );
		/**
		 * The Help Center module is loaded. Hosts call register_host() here.
		 *
		 * @param Module $module The module.
		 */
		do_action( 'qcld_wpbot_help_center/loaded', $this );
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
		if ( Feature_Switch::is_on() ) {
			$this->start();
		}
	}

	/**
	 * Start the features. Only runs when the Help Center is on.
	 *
	 * @return void
	 */
	private function start() {
		if ( $this->started ) {
			return;
		}
		$this->started = true;
		Permissions::init();
		Content\Content_Model::init();
		Knowledge::init();
		Blocks\Blocks::init();
		Admin\Admin::init();
		Front\Front::init();
		Search::init();
		Access::init();
		Feedback\Votes::init();
		Feedback\Widget::init();
		Glossary\Glossary::init();
		Chat\Chat::init();
		Seo\Seo::init();
		Front\Embeds::init();
		Analytics\Stats::init();
		Languages::init();
		Style_Guide::init();
		Mcp\Tools::init();
		Mcp\Review::init();
		Mcp\Server::init();
		Mcp\Abilities::init();
		Mcp\Docs_Server::init();
		/**
		 * The host is known and the Help Center is on. Features and the Pro module start here.
		 *
		 * @param Module $module The module.
		 */
		do_action( 'qcld_wpbot_help_center/host_ready', $this );
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
	 * Whether the features were started (the Help Center is on and a host registered).
	 *
	 * @return bool
	 */
	public function is_started() {
		return $this->started;
	}

	/**
	 * Forget the host (unit tests only).
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$instance = null;
	}
}
