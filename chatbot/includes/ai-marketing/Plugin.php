<?php
/**
 * CRM module entry point.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM;

use QuantumCloud\WPBot\CRM\Domain\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the schema upgrade, REST controllers and runtime services into WordPress.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boot the module (idempotent).
	 *
	 * @return Plugin
	 */
	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Plugin deactivation: stop the CRM's cron jobs (they are re-created on the next load).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Jobs\Scheduler::HOOK );
	}

	/**
	 * Absolute path of the module folder, with trailing slash.
	 *
	 * @return string
	 */
	public static function dir() {
		return trailingslashit( __DIR__ );
	}

	/**
	 * Public URL of a file inside the module folder.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function url( $path = '' ) {
		return plugins_url( ltrim( (string) $path, '/' ), __FILE__ );
	}

	/**
	 * Fully-qualified class names of REST controllers.
	 *
	 * Each class must extend Http\RestController and implement register_routes().
	 * Classes that do not exist (yet) are skipped.
	 *
	 * @return string[]
	 */
	public static function controllers() {
		$controllers = array(
			// Controllers.
			'QuantumCloud\\WPBot\\CRM\\Http\\ContactsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\GroupsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\CampaignsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\TemplatesController',
			'QuantumCloud\\WPBot\\CRM\\Http\\AutomationsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\AnalyticsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\SettingsController',
			'QuantumCloud\\WPBot\\CRM\\Http\\AiController',
			'QuantumCloud\\WPBot\\CRM\\Http\\SubscribeController',
			'QuantumCloud\\WPBot\\CRM\\Http\\IntegrationsController',
		);

		/**
		 * Filters the REST controller classes registered on rest_api_init.
		 *
		 * @param string[] $controllers Fully-qualified class names.
		 */
		return (array) apply_filters( 'wpbot_crm/controllers', $controllers );
	}

	/**
	 * Fully-qualified class names of runtime services.
	 *
	 * Each class exposes a public static init() that registers its hooks.
	 * It is called on `init` (priority 5). Missing classes are skipped.
	 *
	 * @return string[]
	 */
	public static function services() {
		$services = array(
			// Runtime services (hooks).
			'QuantumCloud\\WPBot\\CRM\\Jobs\\Scheduler',
			'QuantumCloud\\WPBot\\CRM\\Mail\\Tracking',
			'QuantumCloud\\WPBot\\CRM\\Domain\\AutomationEngine',
			'QuantumCloud\\WPBot\\CRM\\Integrations\\WordPressUsers',
			'QuantumCloud\\WPBot\\CRM\\Integrations\\WooCommerce',
			'QuantumCloud\\WPBot\\CRM\\Integrations\\Shortcode',
			'QuantumCloud\\WPBot\\CRM\\Integrations\\FormCapture',
		);

		/**
		 * Filters the runtime service classes initialised on init.
		 *
		 * @param string[] $services Fully-qualified class names.
		 */
		return (array) apply_filters( 'wpbot_crm/services', $services );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function hooks() {
		if ( did_action( 'plugins_loaded' ) ) {
			$this->upgrade();
		} else {
			add_action( 'plugins_loaded', array( $this, 'upgrade' ), 20 );
		}

		if ( did_action( 'init' ) ) {
			$this->init_services();
		} else {
			add_action( 'init', array( $this, 'init_services' ), 5 );
		}

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Create or upgrade database tables when the schema version changed.
	 */
	public function upgrade() {
		Schema::maybe_upgrade();
	}

	/**
	 * Call init() on every runtime service that exists.
	 */
	public function init_services() {
		foreach ( self::services() as $class_name ) {
			if ( is_string( $class_name ) && class_exists( $class_name ) && method_exists( $class_name, 'init' ) ) {
				call_user_func( array( $class_name, 'init' ) );
			}
		}
	}

	/**
	 * Instantiate every controller that exists and register its routes.
	 */
	public function register_routes() {
		foreach ( self::controllers() as $class_name ) {
			if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
				continue;
			}
			$controller = new $class_name();
			if ( method_exists( $controller, 'register_routes' ) ) {
				$controller->register_routes();
			}
		}
	}
}
