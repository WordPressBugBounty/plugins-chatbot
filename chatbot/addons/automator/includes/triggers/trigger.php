<?php
/**
 * Abstract Trigger Class
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Trigger
 */
abstract class Trigger {

	/**
	 * Trigger ID
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * Trigger Title
	 * 
	 * @var string
	 */
	protected $title;

	/**
	 * Trigger Group (e.g., 'WordPress', 'WooCommerce')
	 * 
	 * @var string
	 */
	protected $group;

	/**
	 * Get Trigger ID
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get Trigger Title
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get Trigger Group
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * Register the trigger (hooks)
	 */
	abstract public function register();

	/**
	 * WP Cron hook that runs a queued trigger.
	 */
	const QUEUE_HOOK = 'wpbot_automator_run_queued_trigger';

	/**
	 * Triggers answered over HTTP to another server; they run immediately so the reply is accurate.
	 */
	const SYNC_TRIGGERS = array( 'webhook', 'facebook' );

	/**
	 * Process the trigger execution
	 *
	 * Workflows can make slow AI/HTTP calls (up to 60 s each), so triggers fired inside a
	 * visitor's request (checkout, login, form submit, comment) don't run before the response:
	 * - on PHP-FPM / LiteSpeed they run right after the response is sent (same process, no cron);
	 * - otherwise they are queued for WP Cron (with DISABLE_WP_CRON, the site's system cron runs it).
	 * Triggers fired by a workflow that is already running in the background run immediately.
	 * `$data['sync']` or the filter `wpbot_automator_run_async` (bool, $trigger_id) runs a trigger now.
	 *
	 * @param array $data Data passed from the hook.
	 */
	protected function run( $data = array() ) {
		$full_trigger_id = $this->id;
		if ( isset( $data['sub_id'] ) ) {
			$full_trigger_id .= ':' . $data['sub_id'];
		}
		$trigger_data = isset( $data['data'] ) ? $data['data'] : array();

		// Nothing listens to this trigger: no queue entry, no query.
		if ( ! \WPbot_Automator\Engine\Workflow_Runner::candidate_ids( $full_trigger_id ) ) {
			return;
		}

		$async = empty( $data['sync'] ) && ! self::$in_background && ! in_array( $this->id, self::SYNC_TRIGGERS, true ) && ! wp_doing_cron();
		if ( apply_filters( 'wpbot_automator_run_async', $async, $full_trigger_id ) ) {
			if ( null !== self::finish_request_function() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				self::defer( $full_trigger_id, $trigger_data );
				return;
			}
			if ( self::queue( $full_trigger_id, $trigger_data ) ) {
				return;
			}
		}

		\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger( $full_trigger_id, $trigger_data );
	}

	/**
	 * Triggers waiting to run after the response is sent.
	 *
	 * @var array
	 */
	private static $deferred = array();

	/**
	 * True while deferred jobs run after the response; nested triggers then run inline.
	 *
	 * @var bool
	 */
	private static $in_background = false;

	/**
	 * The SAPI function that ends the response but keeps PHP running, if any.
	 *
	 * @return string|null
	 */
	private static function finish_request_function() {
		foreach ( array( 'fastcgi_finish_request', 'litespeed_finish_request' ) as $fn ) {
			if ( function_exists( $fn ) ) {
				return $fn;
			}
		}
		return null;
	}

	/**
	 * Run a trigger at the end of this request, after the response has gone to the visitor.
	 */
	private static function defer( $trigger_id, $trigger_data ) {
		if ( empty( self::$deferred ) ) {
			add_action( 'shutdown', array( __CLASS__, 'run_deferred' ), PHP_INT_MAX );
		}
		self::$deferred[] = array( $trigger_id, $trigger_data );
	}

	/**
	 * Shutdown callback: send the response, then run the deferred triggers.
	 */
	public static function run_deferred() {
		$jobs           = self::$deferred;
		self::$deferred = array();
		if ( empty( $jobs ) ) {
			return;
		}

		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		call_user_func( self::finish_request_function() );

		// Run like the cron queue would: as no user, so content a workflow creates from visitor
		// input is filtered (no unfiltered_html from an admin who happened to fire the trigger).
		wp_set_current_user( 0 );

		self::$in_background = true;
		try {
			foreach ( $jobs as $job ) {
				\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger( $job[0], $job[1] );
			}
		} finally {
			self::$in_background = false;
		}
	}

	/**
	 * Store the trigger data and schedule it for WP Cron.
	 *
	 * @return bool Whether it was queued (false = caller runs it now).
	 */
	private static function queue( $trigger_id, $trigger_data ) {
		$task_id = \WPbot_Automator\Core\Database::add_scheduled_task(
			array(
				'workflow_id'     => 0,
				'trigger_id'      => $trigger_id,
				'trigger_data'    => $trigger_data,
				'remaining_nodes' => array( 'queued_trigger' => 1 ),
				'connections'     => array(),
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( ! $task_id ) {
			return false;
		}

		if ( false === wp_schedule_single_event( time(), self::QUEUE_HOOK, array( (int) $task_id ) ) ) {
			\WPbot_Automator\Core\Database::delete_scheduled_task( $task_id );
			return false;
		}

		// Start the cron run now (non-blocking loopback) instead of waiting for the next page view.
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			spawn_cron();
		}
		return true;
	}

	/**
	 * WP Cron callback: run a queued trigger once.
	 *
	 * @param int $task_id Scheduled task row ID.
	 */
	public static function run_queued( $task_id ) {
		$task = \WPbot_Automator\Core\Database::get_scheduled_task( absint( $task_id ) );
		if ( ! $task || 'pending' !== $task['status'] ) {
			return;
		}
		\WPbot_Automator\Core\Database::complete_scheduled_task( $task['id'] );

		\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger(
			(string) $task['trigger_id'],
			is_array( $task['trigger_data'] ) ? $task['trigger_data'] : array()
		);
	}
}
