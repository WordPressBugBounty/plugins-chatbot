<?php
/**
 * Main Plugin Core Class
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class
 */
class Plugin {

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		// Initialize Registry on init hook to avoid early translation loading.
		add_action( 'init', array( Registry::class, 'init' ) );

		// Initialize deactivation survey.
		if ( is_admin() ) {
			new Deactivation_Survey( WPBOT_AUTOMATOR_PLUGIN_DIR . 'wpbot-automator.php' );
		}

		// Initialize Tables module.
		Tables::init();

		// Initialize hooks.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// Diagnostic hook to catch the source of "wpdb::prepare called incorrectly".
		add_action( 'doing_it_wrong_run', function( $function, $message ) {
			if ( 'wpdb::prepare' === $function || strpos( $message, 'wpdb::prepare' ) !== false ) {
			//	error_log( 'WPBOT-AUTOMATOR-DIAGNOSTIC: ' . $message );
			//	error_log( 'BACKTRACE: ' . wp_debug_backtrace_summary() );
			}
		}, 10, 2 );
	}

	/**
	 * Register admin menu
	 */
	public static function register_admin_menu() {
		// All menu registration is now handled by chatbot (qcld-wpwbot.php)
		// We no longer register submenus here to keep the sidebar clean.
	}

	/**
	 * Render admin page
	 */
	public static function render_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'workflows';
		
		$tabs = array(
			'workflows'       => __( 'Workflows', 'wpbot-automator' ),
			'email-templates' => __( 'Email Templates', 'wpbot-automator' ),
			'logs'            => __( 'Activity Log', 'wpbot-automator' ),
			'tables'          => __( 'Tables', 'wpbot-automator' ),
			'support'         => __( 'Support', 'wpbot-automator' ),
			'help'            => __( 'Help', 'wpbot-automator' ),
		);

		?>
		<div class="wrap wpbot-automator-wrap">
			<h2><?php esc_html_e( 'Automator', 'wpbot-automator' ); ?></h2>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="?page=wpbot-automator&tab=<?php echo esc_attr( $tab_id ); ?>" class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="wpbot-automator-tab-content" style="margin-top: 20px;">
				<?php
				switch ( $active_tab ) {
					case 'email-templates':
						self::render_email_templates_page();
						break;
					case 'logs':
						self::render_logs_page();
						break;
					case 'tables':
						self::render_tables_page();
						break;
					case 'support':
						self::render_support_page();
						break;
					case 'help':
						self::render_help_page();
						break;
					case 'workflows':
					default:
						echo '<div id="wpbot-automator-root"></div>';
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	public static function render_support_page() {
		include_once WPBOT_AUTOMATOR_PLUGIN_DIR . 'includes/templates/support-page.php';
	}

	public static function render_help_page() {
		include_once WPBOT_AUTOMATOR_PLUGIN_DIR . 'includes/templates/help-page.php';
	}

	/**
	 * Render email templates page (Builder Root)
	 */
	public static function render_email_templates_page() {
		echo '<div id="wpbot-email-builder-root"></div>';
	}

	public static function render_logs_page() {
		echo '<div id="wpbot-activity-log-root"></div>';
	}

	public static function render_tables_page() {
		echo '<div id="wpbot-tables-root"></div>';
	}
	public static function wpbotauto_valid_license() {
		$wpbot_automator_license_valid = get_option('wpbot_automator_license_valid');
		if( $wpbot_automator_license_valid === 'automator_master_license' ) {
			return true;
		}
		return false;
	}
	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		// Only enqueue on our specific automator page
		if ( ! isset( $_GET['page'] ) || 'wpbot-automator' !== $_GET['page'] ) {
			return;
		}

		$asset_file = WPBOT_AUTOMATOR_PLUGIN_DIR . 'build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_enqueue_script(
				'wpbot-automator-admin',
				WPBOT_AUTOMATOR_PLUGIN_URL . 'build/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_enqueue_style(
				'wpbot-automator-admin',
				WPBOT_AUTOMATOR_PLUGIN_URL . 'build/index.css',
				array( 'wp-components' ),
				$asset['version']
			);
			
			$is_pro_active = self::wpbotauto_valid_license();

			// Pass data to JavaScript.
			wp_localize_script(
				'wpbot-automator-admin',
				'wpbotAutomator',
				array(
					'apiUrl'   => rest_url( 'wpbot-automator/v1' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'pluginUrl' => WPBOT_AUTOMATOR_PLUGIN_URL,
					'userEmail' => get_option( 'admin_email' ),
					'isProActive' => $is_pro_active,
				)
			);
		}
	}

	/**
	 * Register REST API routes
	 */
	public static function register_rest_routes() {
		// Workflows endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/workflows',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_workflows' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			'wpbot-automator/v1',
			'/workflows',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_workflow' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			'wpbot-automator/v1',
			'/workflows/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_workflow' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			'wpbot-automator/v1',
			'/workflows/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_workflow' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Products endpoint for WooCommerce.
		register_rest_route(
			'wpbot-automator/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_products' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// WPForms forms list.
		register_rest_route(
			'wpbot-automator/v1',
			'/wpforms-forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_wpforms_forms' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Conversational forms list.
		register_rest_route(
			'wpbot-automator/v1',
			'/conversational-forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversational_forms' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Contact Form 7 forms list.
		register_rest_route(
			'wpbot-automator/v1',
			'/cf7-forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_cf7_forms' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Test action endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/test-action',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_action' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Logs endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/logs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_logs' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'clear_logs' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			'wpbot-automator/v1',
			'/logs/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_logs_summary' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Export workflows endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/workflows/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'export_workflows' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Import workflows endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/workflows/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'import_workflows' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// AI Workflow Generator endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/generate-workflow',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'generate_workflow' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Support request endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/support/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'send_support_email' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Credentials endpoint (GET / POST).
		register_rest_route(
			'wpbot-automator/v1',
			'/credentials',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_credential' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				)
			)
		);

		// Credentials delete endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/credentials/(?P<id>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_credential' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// Email Templates endpoint.
		register_rest_route(
			'wpbot-automator/v1',
			'/email-templates',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_email_templates' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_email_template' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				)
			)
		);

		register_rest_route(
			'wpbot-automator/v1',
			'/email-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_email_template' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'save_email_template' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_email_template' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				)
			)
		);

		// Webhook trigger endpoint — registered here (in addition to Webhook class)
		// to guarantee the route is always available regardless of hook timing.
		register_rest_route(
			'wpbot-automator/v1',
			'/webhook/(?P<token>[a-zA-Z0-9\-_]+)',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'handle_webhook_trigger' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming webhook — public entry point registered in register_rest_routes().
	 *
	 * Accepts any GET/POST request, extracts the token from the URL, merges all
	 * request params as trigger data, and fires the matching workflow.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function handle_webhook_trigger( \WP_REST_Request $request ) {
		$token = $request->get_param( 'token' );
		$data  = $request->get_params();

		// target_node_id tells the workflow runner which trigger node to start from.
		$data['target_node_id'] = 'node-' . $token;

		\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger(
			'webhook:incoming_webhook',
			$data
		);

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Webhook received and workflow triggered.',
			'token'   => $token,
		) );
	}

	/**
	 * Get logs
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_logs( $request ) {
		global $wpdb;
		$table_logs      = Database::get_logs_table();
		$table_workflows = Database::get_workflows_table();

		$workflow_id = absint( $request->get_param( 'workflow_id' ) );
		$status      = sanitize_text_field( $request->get_param( 'status' ) );
		$per_page    = min( max( absint( $request->get_param( 'per_page' ) ?: 25 ), 1 ), 200 );
		$page        = max( absint( $request->get_param( 'page' ) ?: 1 ), 1 );
		$offset      = ( $page - 1 ) * $per_page;

		$where_parts  = array();
		$query_params = array();

		if ( $workflow_id ) {
			$where_parts[]  = 'l.workflow_id = %d';
			$query_params[] = $workflow_id;
		}
		if ( $status && in_array( $status, array( 'success', 'failed' ), true ) ) {
			$where_parts[]  = 'l.status = %s';
			$query_params[] = $status;
		}

		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$table_logs} l {$where}";
		$total     = (int) ( $query_params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$query_params ) )
			: $wpdb->get_var( $count_sql ) );

		$list_params   = array_merge( $query_params, array( $per_page, $offset ) );
		$sql           = "SELECT l.*, w.name as workflow_name
						FROM {$table_logs} l
						LEFT JOIN {$table_workflows} w ON l.workflow_id = w.id
						{$where}
						ORDER BY l.created_at DESC
						LIMIT %d OFFSET %d";

		$logs = $wpdb->get_results( $wpdb->prepare( $sql, ...$list_params ), ARRAY_A );

		return rest_ensure_response( array(
			'logs'        => $logs ?: array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		) );
	}

	public static function get_logs_summary() {
		global $wpdb;
		$table   = Database::get_logs_table();
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$success = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'success'" );
		$failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
		$last    = $wpdb->get_var( "SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 1" );

		return rest_ensure_response( array(
			'total'    => $total,
			'success'  => $success,
			'failed'   => $failed,
			'last_run' => $last ?: null,
		) );
	}

	public static function clear_logs() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Database::get_logs_table() );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Check permission for REST API
	 *
	 * @return bool
	 */
	public static function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get workflows
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_workflows( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$workflows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC",
			ARRAY_A
		);

		// Decode workflow_data JSON.
		foreach ( $workflows as &$workflow ) {
			$workflow['workflow_data'] = json_decode( $workflow['workflow_data'], true );
		}

		return rest_ensure_response( $workflows );
	}

	/**
	 * Create workflow
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function create_workflow( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$name          = sanitize_text_field( $request->get_param( 'name' ) );
		$description   = sanitize_textarea_field( $request->get_param( 'description' ) );
		$workflow_data = wp_json_encode( $request->get_param( 'workflow_data' ) );
		$status        = sanitize_text_field( $request->get_param( 'status' ) ) ?: 'active';

		$result = $wpdb->insert(
			$table,
			array(
				'name'          => $name,
				'description'   => $description,
				'workflow_data' => $workflow_data,
				'status'        => $status,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'id'      => $wpdb->insert_id,
				)
			);
		}

		return new \WP_Error( 'create_failed', __( 'Failed to create workflow', 'wpbot-automator' ), array( 'status' => 500 ) );
	}

	/**
	 * Update workflow
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function update_workflow( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$id            = absint( $request->get_param( 'id' ) );
		$name          = sanitize_text_field( $request->get_param( 'name' ) );
		$description   = sanitize_textarea_field( $request->get_param( 'description' ) );
		$workflow_data = wp_json_encode( $request->get_param( 'workflow_data' ) );
		$status        = sanitize_text_field( $request->get_param( 'status' ) );

		$result = $wpdb->update(
			$table,
			array(
				'name'          => $name,
				'description'   => $description,
				'workflow_data' => $workflow_data,
				'status'        => $status,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new \WP_Error( 'update_failed', __( 'Failed to update workflow', 'wpbot-automator' ), array( 'status' => 500 ) );
	}

	/**
	 * Delete workflow
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function delete_workflow( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$id = absint( $request->get_param( 'id' ) );

		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new \WP_Error( 'delete_failed', __( 'Failed to delete workflow', 'wpbot-automator' ), array( 'status' => 500 ) );
	}

	/**
	 * Get WooCommerce products
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_products( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array() );
		}

		$args = array(
			'limit'  => -1,
			'status' => 'publish',
		);

		$products = wc_get_products( $args );
		$response = array();

		foreach ( $products as $product ) {
			$response[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get list of WPForms forms.
	 */
	public static function get_wpforms_forms() {
		if ( ! function_exists( 'wpforms' ) ) {
			return rest_ensure_response( array() );
		}

		$forms    = wpforms()->form->get( '', array( 'fields' => 'id,post_title', 'posts_per_page' => -1 ) );
		$response = array();

		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$response[] = array(
					'id'   => (string) $form->ID,
					'name' => $form->post_title,
				);
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get list of Conversational forms.
	 */
	public static function get_conversational_forms() {
		if ( ! class_exists( 'Qcformbuilder_Forms' ) ) {
			return rest_ensure_response( array() );
		}

		$forms    = \Qcformbuilder_Forms::get_forms( true, false );
		$response = array();

		if ( is_array( $forms ) ) {
			foreach ( $forms as $form_id => $form ) {
				$response[] = array(
					'id'   => (string) $form_id,
					'name' => isset( $form['name'] ) ? $form['name'] : $form_id,
				);
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get list of Contact Form 7 forms.
	 */
	public static function get_cf7_forms() {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return rest_ensure_response( array() );
		}

		$forms    = \WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) );
		$response = array();

		foreach ( $forms as $form ) {
			$response[] = array(
				'id'   => (string) $form->id(),
				'name' => $form->title(),
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Test an action
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function test_action( $request ) {
		$app_id    = sanitize_text_field( $request->get_param( 'appId' ) );
		$action_id = sanitize_text_field( $request->get_param( 'actionId' ) );
		$config    = $request->get_param( 'config' );

		$action_obj = Registry::get_action( $app_id );

		if ( ! $action_obj ) {
			return new \WP_Error( 'action_not_found', __( 'Action not found', 'wpbot-automator' ), array( 'status' => 404 ) );
		}

		// Prepare dummy trigger data for token parsing (optional).
		$trigger_data = array(
			'token' => 'sample_token_value',
			'id'    => 123,
			'name'  => 'Sample Name',
		);

		$action_data = array(
			'actionId' => $action_id,
			'config'   => $config,
		);

		$result = $action_obj->execute( $action_data, $trigger_data );

		return rest_ensure_response( $result );
	}
	/**
	 * Export workflows as a JSON download
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void — sends headers and exits.
	 */
	public static function export_workflows( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$id = absint( $request->get_param( 'id' ) );

		if ( $id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
				ARRAY_A
			);
			$filename = 'wpbot-workflow-' . $id . '.json';
		} else {
			$rows     = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
			$filename = 'wpbot-workflows-all.json';
		}

		if ( empty( $rows ) ) {
			wp_send_json_error( array( 'message' => 'No workflows found.' ), 404 );
			return;
		}

		// Decode workflow_data so the exported JSON is human-readable.
		foreach ( $rows as &$row ) {
			$row['workflow_data'] = json_decode( $row['workflow_data'], true );
		}
		unset( $row );

		$json = wp_json_encode( array( 'workflows' => $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Import workflows from a JSON payload
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import_workflows( $request ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		$workflows = $request->get_param( 'workflows' );

		if ( empty( $workflows ) || ! is_array( $workflows ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid import data. Expected a "workflows" array.', 'wpbot-automator' ),
				array( 'status' => 400 )
			);
		}

		$imported = 0;
		$errors   = array();

		foreach ( $workflows as $index => $workflow ) {
			$name          = isset( $workflow['name'] ) ? sanitize_text_field( $workflow['name'] ) : '';
			$description   = isset( $workflow['description'] ) ? sanitize_textarea_field( $workflow['description'] ) : '';
			$status        = isset( $workflow['status'] ) ? sanitize_text_field( $workflow['status'] ) : 'inactive';
			$workflow_data = isset( $workflow['workflow_data'] ) ? wp_json_encode( $workflow['workflow_data'] ) : wp_json_encode( array() );

			if ( empty( $name ) ) {
				$errors[] = "Workflow at index {$index} is missing a name — skipped.";
				continue;
			}

			$result = $wpdb->insert(
				$table,
				array(
					'name'          => $name,
					'description'   => $description,
					'status'        => $status,
					'workflow_data' => $workflow_data,
				),
				array( '%s', '%s', '%s', '%s' )
			);

			if ( $result ) {
				++$imported;
			} else {
				$errors[] = "Failed to insert workflow \"{$name}\" (index {$index}).";
			}
		}

		return rest_ensure_response(
			array(
				'success'  => $imported > 0,
				'imported' => $imported,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * Send support email
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function send_support_email( $request ) {
		$from_email = sanitize_email( $request->get_param( 'from_email' ) );
		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( ! is_email( $from_email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email address', 'wpbot-automator' ), array( 'status' => 400 ) );
		}

		if ( empty( $message ) ) {
			return new \WP_Error( 'empty_message', __( 'Message cannot be empty', 'wpbot-automator' ), array( 'status' => 400 ) );
		}

		$to      = 'hello@wpbot.pro';
		$subject = 'WPBot Automator Support Request';
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . $from_email,
			'From: ' . $from_email,
		);

		$body = wpautop( $message );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new \WP_Error( 'email_failed', __( 'Failed to send support email', 'wpbot-automator' ), array( 'status' => 500 ) );
	}

	/**
	 * Get credentials
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_credentials( $request ) {
		$app_id = sanitize_text_field( $request->get_param( 'app_id' ) );
		$credentials = get_option( 'wpbot_automator_credentials', array() );

		if ( $app_id ) {
			$filtered = array();
			foreach ( $credentials as $cred ) {
				if ( isset($cred['app_id']) && $cred['app_id'] === $app_id ) {
					// We only send back public safe info to frontend (name, id, app_id).
					$filtered[] = array(
						'id' => $cred['id'],
						'name' => $cred['name'],
						'app_id' => $cred['app_id']
					);
				}
			}
			return rest_ensure_response( $filtered );
		}

		// If no app_id provided, return all credentials (names and IDs only)
		$filtered = array();
		foreach ( $credentials as $cred ) {
			$filtered[] = array(
				'id' => $cred['id'],
				'name' => $cred['name'],
				'app_id' => $cred['app_id']
			);
		}
		return rest_ensure_response( $filtered );
	}

	/**
	 * Save a credential
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_credential( $request ) {
		$name   = sanitize_text_field( $request->get_param( 'name' ) );
		$app_id = sanitize_text_field( $request->get_param( 'app_id' ) );
		$data   = $request->get_param( 'data' );

		//error_log('save_credential params: name=' . $name . ', app_id=' . $app_id . ', data=' . print_r($data, true));

		if ( empty( $name ) || empty( $app_id ) || empty( $data ) ) {
			//error_log('save_credential missing data error');
			return new \WP_Error( 'missing_data', __( 'Name, app_id, and data are required.', 'wpbot-automator' ), array( 'status' => 400 ) );
		}

		$credentials = get_option( 'wpbot_automator_credentials', array() );
		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}
		
		$new_id = uniqid( 'cred_' );
		$credentials[$new_id] = array(
			'id'         => $new_id,
			'name'       => $name,
			'app_id'     => $app_id,
			'data'       => $data,
			'created_at' => current_time( 'mysql' ),
		);

		update_option( 'wpbot_automator_credentials', $credentials );
		//error_log('save_credential saved: ' . $new_id);

		return rest_ensure_response( array(
			'success' => true,
			'credential' => array(
				'id'     => $new_id,
				'name'   => $name,
				'app_id' => $app_id
			)
		) );
	}
	
	/**
	 * Delete a credential
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_credential( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$credentials = get_option( 'wpbot_automator_credentials', array() );
		
		if ( isset( $credentials[$id] ) ) {
			unset( $credentials[$id] );
			update_option( 'wpbot_automator_credentials', $credentials );
			return rest_ensure_response( array( 'success' => true ) );
		}
		
		return new \WP_Error( 'not_found', __( 'Credential not found.', 'wpbot-automator' ), array( 'status' => 404 ) );
	}

	/**
	 * Get all email templates
	 */
	public static function get_email_templates() {
		global $wpdb;
		$table = Database::get_email_templates_table();

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
			return rest_ensure_response( array() );
		}

		$templates = $wpdb->get_results(
			"SELECT id, name FROM {$table} ORDER BY name ASC",
			ARRAY_A
		);

		//error_log( 'WPbot Automator - GET Templates - Table: ' . $table );
		//error_log( 'WPbot Automator - GET Templates - Count: ' . count( $templates ) );
		//error_log( 'WPbot Automator - GET Templates - JSON: ' . wp_json_encode( $templates ) );

		return rest_ensure_response( is_array( $templates ) ? $templates : array() );
	}

	/**
	 * Get a single email template
	 */
	public static function get_email_template( $request ) {
		global $wpdb;
		$id    = absint( $request->get_param( 'id' ) );
		$table = Database::get_email_templates_table();

		$template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $template ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'wpbot-automator' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $template );
	}

	/**
	 * Save an email template (Create or Update)
	 */
	public static function save_email_template( $request ) {
		global $wpdb;
		$table = Database::get_email_templates_table();

		$id        = absint( $request->get_param( 'id' ) );
		$name      = sanitize_text_field( $request->get_param( 'name' ) );
		$subject   = sanitize_text_field( $request->get_param( 'subject' ) );
		$body_html = $request->get_param( 'body_html' ); // Allow HTML here.
		$body_json = $request->get_param( 'body_json' );

		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_name', __( 'Template name is required', 'wpbot-automator' ), array( 'status' => 400 ) );
		}

		// Free plan: limit to 3 templates on creation.
		if ( ! $id ) {
			if ( ! function_exists( 'wpbot_automator_pro_init' ) ) {
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
				if ( $count >= 3 ) {
					return new \WP_Error(
						'template_limit_reached',
						__( 'Free plan is limited to 3 email templates. Upgrade to Pro for unlimited templates.', 'wpbot-automator' ),
						array( 'status' => 403 )
					);
				}
			}
		}

		$data = array(
			'name'      => $name,
			'subject'   => $subject,
			'body_html' => $body_html,
			'body_json' => is_scalar( $body_json ) ? $body_json : wp_json_encode( $body_json ),
		);

		//error_log( 'WPbot Automator - Saving Template - ID: ' . $id );
		//error_log( 'WPbot Automator - Saving Template - Data: ' . wp_json_encode( array_keys( $data ) ) );

		if ( $id ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$result = $wpdb->insert( $table, $data );
			$id     = $wpdb->insert_id;
		}

		if ( false === $result ) {
			//error_log( 'WPbot Automator - Save Template FAILED. Error: ' . $wpdb->last_error );
			return new \WP_Error( 'save_failed', __( 'Failed to save template', 'wpbot-automator' ) . ': ' . $wpdb->last_error, array( 'status' => 500 ) );
		}

		//error_log( 'WPbot Automator - Save Template SUCCESS. ID: ' . $id );

		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/**
	 * Delete an email template
	 */
	public static function delete_email_template( $request ) {
		global $wpdb;
		$table = Database::get_email_templates_table();
		$id    = absint( $request->get_param( 'id' ) );

		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete template', 'wpbot-automator' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * AI Workflow Generator — generate a workflow from a natural-language prompt.
	 *
	 * @param \WP_REST_Request $request
	 */
	public static function generate_workflow( \WP_REST_Request $request ) {
		$provider    = sanitize_text_field( $request->get_param( 'provider' ) );
		$api_key     = sanitize_text_field( $request->get_param( 'api_key' ) );
		$model       = sanitize_text_field( $request->get_param( 'model' ) );
		$user_prompt = sanitize_textarea_field( $request->get_param( 'prompt' ) );

		if ( empty( $api_key ) || empty( $user_prompt ) ) {
			return new \WP_Error( 'missing_params', 'API key and prompt are required.', array( 'status' => 400 ) );
		}

		$system_prompt = self::get_workflow_generator_system_prompt();

		switch ( $provider ) {
			case 'openai':
				$raw = self::call_openai_for_workflow( $api_key, $model ?: 'gpt-4o-mini', $system_prompt, $user_prompt );
				break;
			case 'claude':
				$raw = self::call_claude_for_workflow( $api_key, $model ?: 'claude-3-5-sonnet-20240620', $system_prompt, $user_prompt );
				break;
			case 'gemini':
				$raw = self::call_gemini_for_workflow( $api_key, $model ?: 'gemini-1.5-flash', $system_prompt, $user_prompt );
				break;
			default:
				return new \WP_Error( 'invalid_provider', 'Invalid AI provider. Use openai, claude, or gemini.', array( 'status' => 400 ) );
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// Strip markdown code fences if the model wrapped the JSON.
		$json_text = trim( $raw );
		$json_text = preg_replace( '/^```(?:json)?\s*/i', '', $json_text );
		$json_text = preg_replace( '/\s*```\s*$/', '', $json_text );

		$workflow = json_decode( $json_text, true );

		if ( JSON_ERROR_NONE !== json_last_error() || empty( $workflow['nodes'] ) || empty( $workflow['connections'] ) ) {
			return new \WP_Error( 'parse_error', 'AI returned invalid workflow data. Please try again with a clearer description.', array( 'status' => 422 ) );
		}

		return rest_ensure_response( array(
			'success'  => true,
			'workflow' => $workflow,
		) );
	}

	/**
	 * System prompt sent to every AI provider.
	 */
	private static function get_workflow_generator_system_prompt() {
		return 'You are a workflow automation assistant for WPBot Automator, a WordPress plugin similar to Zapier.
Convert the user\'s plain-English description into a JSON workflow using ONLY the apps and action IDs listed below.

TRIGGER APPS (first node only, type="trigger"):
- wordpress: wp_post_publish, wp_user_register, wp_user_login, wp_comment_added
- woocommerce: wc_order_created, wc_order_status_change, wc_payment_completed
- wpforms: wpforms_submit
- cf7: cf7_submit
- fluent_forms: ff_form_submit
- webhook: incoming_webhook
- facebook: facebook_lead_ads

ACTION APPS (all subsequent nodes, type="action"):
- google_sheets: gs_add_row, gs_update_row
- mail: send_email
- telegram: tg_send_message
- whatsapp: wa_send_message
- facebook: fb_create_post
- instagram: ig_publish_photo
- linkedin: li_create_post
- openai: chat_completion
- claude: chat_completion
- gemini: chat_completion
- fluentcrm: create_contact
- filters: filter_condition
- iterator: iterator_loop
- iterator_end: iterator_end
- webhook: outgoing_webhook
- api: api_request
- mailrefine: mr_verify_email

Return ONLY valid JSON with no explanation, no markdown fences:
{"name":"Short workflow name","description":"One sentence description","nodes":[{"type":"trigger","appId":"wpforms","actionId":"wpforms_submit"},{"type":"action","appId":"google_sheets","actionId":"gs_add_row"}],"connections":[{"from":0,"to":1}]}

Rules:
- Exactly one trigger node at index 0
- All other nodes are actions
- "from" and "to" are zero-based node indices
- Use ONLY the appId/actionId values from the lists above — never invent new ones
- For parallel branches, multiple connection objects may share the same "from" value
- Return ONLY the raw JSON object, nothing else';
	}

	/**
	 * Call OpenAI chat completions API.
	 *
	 * @return string|\WP_Error  Raw text response or error.
	 */
	private static function call_openai_for_workflow( $api_key, $model, $system_prompt, $user_prompt ) {
		$body = array(
			'model'           => $model,
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user',   'content' => $user_prompt ),
			),
			'temperature'     => 0.2,
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['error'] ) ) {
			return new \WP_Error( 'openai_error', $data['error']['message'] ?? 'OpenAI error', array( 'status' => 400 ) );
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Call Anthropic Claude messages API.
	 *
	 * @return string|\WP_Error
	 */
	private static function call_claude_for_workflow( $api_key, $model, $system_prompt, $user_prompt ) {
		$body = array(
			'model'      => $model,
			'max_tokens' => 1024,
			'system'     => $system_prompt,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['error'] ) ) {
			return new \WP_Error( 'claude_error', $data['error']['message'] ?? 'Claude error', array( 'status' => 400 ) );
		}

		return $data['content'][0]['text'] ?? '';
	}

	/**
	 * Call Google Gemini generateContent API.
	 *
	 * @return string|\WP_Error
	 */
	private static function call_gemini_for_workflow( $api_key, $model, $system_prompt, $user_prompt ) {
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $system_prompt . "\n\nUser request: " . $user_prompt ) ),
				),
			),
			'generationConfig' => array(
				'temperature'      => 0.2,
				'responseMimeType' => 'application/json',
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['error'] ) ) {
			return new \WP_Error( 'gemini_error', $data['error']['message'] ?? 'Gemini error', array( 'status' => 400 ) );
		}

		return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}
}
