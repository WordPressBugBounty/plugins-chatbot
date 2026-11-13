<?php
/**
 * Mistral AI
 *
 * @package Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; 

// WPBot Free: helpers that WPBot Pro defines in its functions.php.
if ( ! function_exists( 'get_wpbot_locale' ) ) {
	function get_wpbot_locale() {
		return get_locale();
	}
}
if ( ! function_exists( 'qcld_wpbot_ai_invalid_answer_rule' ) ) {
	function qcld_wpbot_ai_invalid_answer_rule( $number = 4 ) {
		return $number . ". If the user provides an invalid, irrelevant, or nonsensical answer to your question, DO NOT apologize or state that you lack information. Instead, politely inform the user in the language of the conversation that their answer is invalid or cannot be assigned, and ask the exact same question again in the user's language. An answer written in a different language than the question is still a valid answer.";
	}
}
// Exit if accessed directly.
}

if ( ! class_exists( 'qcld_wpmistral_addons' ) ) {


	/**
	 * Main Class.
	 */
	final class qcld_wpmistral_addons {

		private $id = 'Open AI';

		/**
		 * WPBot Pro version.
		 *
		 * @var string
		 */
		public $version = '1.0.6';

		/**
		 * WPBot Pro helper.
		 *
		 * @var object
		 */
		public $helper;

		/**
		 * The single instance of the class.
		 *
		 * @var qcld_wb_Chatbot
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Main wpbot Instance.
		 *
		 * Ensures only one instance of wpbot is loaded or can be loaded.
		 *
		 * @return qcld_wb_Chatbot - Main instance.
		 * @since 1.0.0
		 * @static
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public $response_list;

		/**
		 *  Constructor
		 */
		public function __construct() {

			$this->includes();
			add_action( 'wp_ajax_upload_mistral_training_file', array( $this, 'upload_mistral_training_file' ) );

			add_action( 'wp_ajax_mistral_response', array( $this, 'mistral_response_callback' ) );
			add_action( 'wp_ajax_nopriv_mistral_response', array( $this, 'mistral_response_callback' ) );
			add_action( 'wp_ajax_qcld_mistral_settings_option', array( $this, 'qcld_mistral_settings_option_callback' ) );
			add_action( 'wp_ajax_create_fine_tuning_job', array( $this, 'create_fine_tuning_job' ) );
			add_action( 'wp_ajax_mistral_file_list', array( $this, 'mistral_file_list_callback' ) );
			add_action( 'wp_ajax_mistral_job_list', array( $this, 'mistral_job_list_callback' ) );
			add_action( 'wp_ajax_mistral_tuned_model_list', array( $this, 'mistral_tunedmodel_list_callback' ) );
			if ( is_admin() && ! empty( $_GET['page'] ) && ( ( $_GET['page'] == 'openai-panel_dashboard' ) || ( $_GET['page'] == 'openai-panel_file' ) || ( $_GET['page'] == 'wpbot_openAi' ) || ( $_GET['page'] == 'openai-panel_help' ) ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
			
				add_action( 'admin_enqueue_scripts', array( $this, 'qcld_wb_chatbot_mistral_admin_scripts' ) );
			}
			// add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_mistral_scripts'));
			
		}

		public function qcld_wb_chatbot_mistral_admin_scripts() {
			wp_register_script(
				'qcld-wp-chatbot-mistral-admin-js',
				QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/mistral/assets/js/qcld-wp-mistral-admin.js',
				array( 'jquery' ),
				QCLD_wpCHATBOT_VERSION,
				true
			);

			// Localize the script with necessary data.
			wp_localize_script(
				'qcld-wp-chatbot-mistral-admin-js',
				'ajax_object',
				array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'                   => wp_create_nonce( 'wp_chatbot' ),
					'mistral_model'                => get_option( 'qcld_mistral_model' ),
					'mistral_enabled'              => get_option( 'qcld_mistral_enabled' ),
					'qcld_mistral_append_content'  => get_option( 'qcld_mistral_append_content' ),
					'qcld_mistral_prepend_content' => get_option( 'qcld_mistral_prepend_content' ),
				)
			);

			wp_enqueue_script( 'qcld-wp-chatbot-mistral-admin-js' );
		}

		/**
		 * Define wpbot Constants.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function includes() {
			require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/Parsedown.php';
			require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-common-function.php';
		}

		public function qcld_mistral_settings_option_callback() {
				$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot' ),
					)
				);
				wp_die();
			} else {
				$mistral_api_key                      = qcld_wpbot_posted_api_key( 'mistral_api_key', 'qcld_mistral_api_key' );
				$mistral_model                        = sanitize_text_field( $_POST['mistral_model'] ?? '' );
				$mistral_enabled                      = sanitize_text_field( $_POST['mistral_enabled'] ?? '' );
				$qcld_mistral_page_suggestion_enabled = sanitize_text_field( $_POST['qcld_mistral_page_suggestion_enabled'] ?? '' );
				$qcld_mistral_append_content          = sanitize_text_field( $_POST['qcld_mistral_append_content'] ?? '' );
				$qcld_mistral_prepend_content         = sanitize_text_field( $_POST['qcld_mistral_prepend_content'] ?? '' );
				update_option( 'qcld_mistral_api_key', $mistral_api_key );
				if ( $mistral_model != '' ) {
					update_option( 'qcld_mistral_model', $mistral_model );
				}
				if ( $mistral_enabled != '' ) {
					update_option( 'qcld_mistral_enabled', $mistral_enabled );
				}
				if ( $mistral_enabled == '1' ) {
					qcld_wpbot_ai_provider_switched_on( 'qcld_mistral_enabled' ); // Others off, site search off.
				} else {
					qcld_wpbot_fall_back_to_openai(); // Not while another provider is on.
				}
				update_option( 'qcld_mistral_page_suggestion_enabled', $qcld_mistral_page_suggestion_enabled );
				// The settings page already sends these; they were never stored.
				update_option( 'qcld_mistral_rag_enabled', ( isset( $_POST['qcld_mistral_rag_enabled'] ) && '1' === (string) $_POST['qcld_mistral_rag_enabled'] ) ? '1' : '0' );
				update_option( 'qcld_mistral_rag_link_enabled', ( isset( $_POST['qcld_mistral_rag_link_enabled'] ) && '1' === (string) $_POST['qcld_mistral_rag_link_enabled'] ) ? '1' : '0' );
				if ( isset( $_POST['openai_post_type'] ) ) { update_option( 'qcld_openai_relevant_post', array_map( 'sanitize_key', (array) wp_unslash( $_POST['openai_post_type'] ) ) ); } // The Mistral screen does not always send it.

				update_option( 'qcld_mistral_append_content', $qcld_mistral_append_content );
				update_option( 'qcld_mistral_prepend_content', $qcld_mistral_prepend_content );
				$qcld_wpbot_ai_file_upload_enabled = isset( $_POST['qcld_wpbot_ai_file_upload_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['qcld_wpbot_ai_file_upload_enabled'] ?? '' ) ) : '0';
				update_option( 'qcld_wpbot_ai_file_upload_enabled', ( '1' === $qcld_wpbot_ai_file_upload_enabled ) ? '1' : '0' );
			}
				echo json_encode( $mistral_enabled );
				wp_die();
		}

		// Upload a file to Mistral for fine-tuning
		function upload_mistral_training_file() {
			$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot' ),
					)
				);
				wp_die();
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Insufficient permissions', 'chatbot' ),
					)
				);
				wp_die();
			}
			// Check if file is uploaded
			if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'No file uploaded or upload error.', 'chatbot' ),
					)
				);
				wp_die();
			}
			// Prepare upload directory
			$upload     = wp_upload_dir();
			$upload_dir = $upload['basedir'] . '/qcldmistral_site_training';
			if ( ! file_exists( $upload_dir ) ) {
				wp_mkdir_p( $upload_dir );
			}
			// Move uploaded file to the directory
			$filename    = basename( $_FILES['file']['name'] );
			$target_path = $upload_dir . '/' . $filename;
			if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $target_path ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed to move uploaded file.', 'chatbot' ),
					)
				);
				wp_die();
			}
			// Now upload to Mistral API
			$url         = 'https://api.mistral.ai/v1/files';
			$post_fields = array(
				'purpose' => 'fine-tune',
				'file'    => new CURLFile( $target_path ),
			);
			$ch          = curl_init();
			$api_key     = get_option( 'qcld_mistral_api_key' );
			$model       = get_option( 'qcld_mistral_model' );

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			$result = json_decode( $response, true );

			if ( isset( $result['id'] ) ) {
				// Call create_fine_tuning_job with just the file IDs
				$this->create_fine_tuning_job( array( $result['id'] ), array( $result['id'] ) );

				wp_send_json(
					array(
						'success'         => true,
						'msg'             => esc_html__( 'File uploaded and sent to Mistral successfully.', 'chatbot' ),
						'mistral_file_id' => $result['id'],
						'local_path'      => $target_path,
					)
				);
			} else {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed to upload file to Mistral.', 'chatbot' ),
						'error'   => $result,
					)
				);
			}

			wp_die();
		}

		public function mistral_file_list_callback() {
			$url     = 'https://api.mistral.ai/v1/files';
			$apt_key = 'Authorization: Bearer ' . get_option( 'qcld_mistral_api_key' );
			$curl    = curl_init();
			curl_setopt( $curl, CURLOPT_URL, $url );
			$headers = array(
				'Content-Type: application/json',
				$apt_key,
			);
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $curl );
			curl_close( $curl );
			wp_send_json( json_decode( $response ) );
			wp_die();
		}

		public function mistral_job_list_callback() {
			$url     = 'https://api.mistral.ai/v1/fine_tuning/jobs';
			$apt_key = 'Authorization: Bearer ' . get_option( 'qcld_mistral_api_key' );
			$curl    = curl_init();
			curl_setopt( $curl, CURLOPT_URL, $url );
			$headers = array(
				'Content-Type: application/json',
				$apt_key,
			);
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $curl );
			curl_close( $curl );
			wp_send_json( json_decode( $response ) );
			wp_die();
		}

		public function mistral_tunedmodel_list_callback() {
			$url     = 'https://api.mistral.ai/v1/fine_tuning/jobs';
			$apt_key = 'Authorization: Bearer ' . get_option( 'qcld_mistral_api_key' );
			$curl    = curl_init();
			curl_setopt( $curl, CURLOPT_URL, $url );
			$headers = array(
				'Content-Type: application/json',
				$apt_key,
			);
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $curl );
			curl_close( $curl );
			wp_send_json( json_decode( $response ) );
			wp_die();
		}

		// List all uploaded files
		function list_mistral_files() {
			$api_key = get_option( 'qcld_mistral_api_key' );
			$url     = 'https://api.mistral.ai/v1/fine_tuning/jobs';
			$ch      = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
					'Content-Type: application/json',
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			$result = json_decode( $response, true );

			if ( $result && isset( $result['data'] ) ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( 'Failed to fetch fine-tuning jobs' );
			}
			wp_die();
			// $job['id'] is your job ID
		}

		// Create a fine-tuning job (new API)
		function create_fine_tuning_job( $training_file_ids = array(), $validation_file_ids = array() ) {
			// Check if this is an AJAX request
			$is_ajax = wp_doing_ajax();

			if ( $is_ajax ) {
				// Security checks for AJAX call
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wp_chatbot' ) ) {
					wp_send_json_error( 'Nonce verification failed.' );
					wp_die();
				}

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( 'Insufficient permissions' );
					wp_die();
				}

				// Get file IDs from POST data for AJAX requests
				$training_file_id   = isset( $_POST['training_file_id'] ) ? sanitize_text_field( $_POST['training_file_id'] ?? '' ) : '';
				$validation_file_id = isset( $_POST['validation_file_id'] ) ? sanitize_text_field( $_POST['validation_file_id'] ?? '' ) : '';

				if ( empty( $training_file_id ) ) {
					wp_send_json_error( 'Training file ID is required.' );
					wp_die();
				}

				// Convert single IDs to arrays for API request
				$training_files   = array( $training_file_id );
				$validation_files = ! empty( $validation_file_id ) ? array( $validation_file_id ) : array();
			} else {
				// For direct PHP calls, use the passed parameters
				$training_files   = is_array( $training_file_ids ) ? $training_file_ids : array( $training_file_ids );
				$validation_files = is_array( $validation_file_ids ) ? $validation_file_ids : array( $validation_file_ids );
			}

			$url     = 'https://api.mistral.ai/v1/fine_tuning/jobs';
			$api_key = get_option( 'qcld_mistral_api_key' );
			$model   = get_option( 'qcld_mistral_model' );

			// Default hyperparameters
			$hyperparameters = array(
				'training_steps' => 10,
				'learning_rate'  => 0.0001,
			);

			$data = array(
				'model'           => $model,
				'training_files'  => $training_files,
				'hyperparameters' => $hyperparameters,
			);

			// Add validation files if they exist
			if ( ! empty( $validation_files ) ) {
				$data['validation_files'] = $validation_files;
			}

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
					'Content-Type: application/json',
					'Accept: application/json',
				)
			);

			$response   = curl_exec( $ch );
			$http_code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$curl_error = curl_error( $ch );
			curl_close( $ch );

			$result = json_decode( $response, true );
			if ( $http_code >= 200 && $http_code < 300 && isset( $result['id'] ) ) {
				// Training job created: cost is billed later by the provider, so the price is not known here.
				qcld_wpbot_spend_record( 'chatbot', 'fine_tune', array(), array( 'provider' => 'mistral', 'model' => (string) $model, 'job_id' => $result['id'], 'cost' => 0, 'price_known' => false ) );
			}

			if ( $is_ajax ) {
				if ( $http_code >= 200 && $http_code < 300 && isset( $result['id'] ) ) {
					wp_send_json_success( $result );
				} else {
					wp_send_json_error(
						array(
							'message'    => 'Failed to create fine-tuning job.',
							'response'   => $result,
							'http_code'  => $http_code,
							'curl_error' => $curl_error,
						)
					);
				}
				wp_die();
			} else {
				// For direct calls, just return the result
				if ( $http_code >= 200 && $http_code < 300 && isset( $result['id'] ) ) {
					return $result;
				} else {
					error_log( 'Mistral Fine-Tuning Job Creation Failed: ' . print_r( $result, true ) );
					return array(
						'success'  => false,
						'response' => $result,
					);
				}
			}
		}

		// Get fine-tuning job status
		function get_fine_tuning_job( $api_key, $job_id ) {
			$url = "https://api.mistral.ai/v1/fine_tuning/jobs/$job_id";
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			return json_decode( $response, true );
		}

		// Start a fine-tuning job (after validation)
		function start_fine_tuning_job( $api_key, $job_id ) {
			$url = "https://api.mistral.ai/v1/fine_tuning/jobs/$job_id/start";
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			return json_decode( $response, true );
		}

		// Cancel a fine-tuning job
		function cancel_fine_tuning_job( $api_key, $job_id ) {
			$url = "https://api.mistral.ai/v1/fine_tuning/jobs/$job_id/cancel";
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			return json_decode( $response, true );
		}

		// List all fine-tuning jobs
		function list_fine_tuning_jobs( $api_key ) {
			$url = 'https://api.mistral.ai/v1/fine_tuning/jobs';
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Authorization: Bearer $api_key",
				)
			);
			$response = curl_exec( $ch );
			curl_close( $ch );
			return json_decode( $response, true );
		}

		public function mistral_response_callback() {
			// Security: verify the nonce the widget already sends (wp_chatbot_obj.ajax_nonce), like the other providers.
			if ( ! check_ajax_referer( 'wp_chatbot', 'nonce', false ) ) {
				echo wp_json_encode( array( 'status' => 'error', 'message' => esc_html__( 'Security check failed. Please reload the page.', 'chatbot' ) ) );
				wp_die();
			}
			if ( get_option( 'is_rate_limiting_enabled' ) == '1' ) {
				do_action( 'rate_limit_checker' );
			}
			$mistral_model     = get_option( 'qcld_mistral_model' );
			$mistral_api_key   = get_option( 'qcld_mistral_api_key' );
			// Unslashed and sanitized: "What's" used to reach Mistral as "What\'s" and be sent twice.
			$keyword           = ( isset( $_POST['keyword'] ) && is_string( $_POST['keyword'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['keyword'] ) ) : '';
			$relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword );
			$relevant_pagelink = array_slice( $relevant_pagelink, 0, 5, true );

			if ( ( get_option( 'qcld_mistral_page_suggestion_enabled' ) == '1' || get_option( 'page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {

				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );

				if ( is_array( $relevant_post_link[ get_wpbot_locale() ] ) ) {

					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ get_wpbot_locale() ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					$relevant_pagelinks = '<br><br><p><em>' . $relevant_post_link[ get_wpbot_locale() ] . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			} else {
				$relevant_pagelinks = '';
			}
			$Parsedown = new Qcld_Parsedown();

			$system_content = "";
			// Knowledge base (RAG), like the other providers. Uses Mistral embeddings, or keyword matching when none are available.
			if ( get_option( 'qcld_mistral_rag_enabled' ) == '1' && function_exists( 'Qcld_Bot_Rag' ) ) {
				$rag_links   = array();
				$rag_context = Qcld_Bot_Rag()->run_rag_search( $keyword ); // Free's search takes (query, top_k = 3) only.
				if ( is_string( $rag_context ) && '' !== $rag_context && 'No knowledge base found.' !== $rag_context ) {
					$system_content .= "You are a helpful assistant.\n\nUse the following context to answer the user's question:\n\nContext:\n" . $rag_context;
				}
			}
			$action_prompt = ( !empty($_POST['action_prompt']) && current_user_can('manage_options') ) ? wp_unslash($_POST['action_prompt']) : ''; // Security: raw action prompts only from the admin AI Actions playground.
			if (!empty($action_prompt)) {
				$system_content .= "\n\nCRITICAL ACTIVE ACTION INSTRUCTION:\n" . $action_prompt;
			}

			$saved_ai_forms = get_option('wpbot_ai_forms', array());
			if (!empty($saved_ai_forms) && is_array($saved_ai_forms)) {
				$system_content .= "\n\nYou must handle the following interactive forms when the user asks for them:\n";
				foreach ($saved_ai_forms as $form) {
					$system_content .= "\nForm Title: " . $form['title'] . "\nInstructions: " . $form['prompt'] . "\n";
				}
				$system_content .= "\n\nCRITICAL INSTRUCTIONS FOR INTERACTIVE FORMS:\n";
				$system_content .= "When a user triggers an interactive form, you must act as a step-by-step data collection agent.\n";
				$system_content .= "1. DO NOT ask all questions at once. Ask exactly ONE question at a time.\n";
				$system_content .= "2. Wait for the user's response before asking the next question.\n";
				$system_content .= "3. Once all necessary information is collected for the form, you MUST output a final JSON block summarizing the collected data, wrapped EXACTLY in these delimiters:\n";
				$system_content .= "__AI_FORM_DATA__{ \"form_title\": \"<Form Title>\", \"data\": { \"Question 1\": \"Answer 1\", \"Question 2\": \"Answer 2\" } }__AI_FORM_DATA_END__\n";
				$system_content .= "Do not include any other text after this JSON block once the form is complete.\n";
				$system_content .= qcld_wpbot_ai_invalid_answer_rule( 4 );
			}

			$system_content = qcld_wpbot_ai_scope( $system_content ); // Answer scope (AI Settings).
			$messages = array();
			if (!empty($system_content)) {
				$messages[] = array(
					'role'    => 'system',
					'content' => $system_content,
				);
			}

			if (!empty($_POST['ai_history'])) {
				$parsed_history = json_decode(wp_unslash($_POST['ai_history']), true);
				if (is_array($parsed_history)) {
					foreach ($parsed_history as $h) {
						if (isset($h['role']) && isset($h['content'])) {
							$role = ($h['role'] === 'assistant') ? 'assistant' : 'user';
							$messages[] = array(
								'role'    => $role,
								'content' => sanitize_text_field($h['content'])
							);
						}
					}
				}
			}

			$last_msg = end($messages);
			if (!$last_msg || $last_msg['role'] !== 'user' || $last_msg['content'] !== $keyword) {
				$messages[] = array(
					'role'    => 'user',
					'content' => apply_filters( 'qcld_wpbot_ai_keyword', $keyword ),
				);
			}

			$data      = json_encode(
				array(
					'model'    => $mistral_model ? $mistral_model : 'mistral-large-latest',
					'messages' => $messages,
				)
			);

			$ch      = curl_init();
			$api_url = 'https://api.mistral.ai/v1/chat/completions';

			curl_setopt( $ch, CURLOPT_URL, $api_url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Authorization: Bearer ' . $mistral_api_key,
				)
			);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 60 ); // A stalled API no longer leaves the chat spinner running.
			$result = qcld_wpbot_spend_wrap(
				'chatbot',
				'chat',
				static function () use ( $ch ) {
					return curl_exec( $ch );
				},
				array( 'json' => true, 'provider' => 'mistral', 'model' => $mistral_model ? $mistral_model : 'mistral-large-latest' )
			);

			if ( qcld_wpbot_spend_is_limit( $result ) ) {
				// Spending limit: the visitor gets the normal no-answer reply.
				$response['status']  = 'success';
				$response['message'] = qcld_wpbot_spend_visitor_reply( $keyword );
			} elseif ( curl_errno( $ch ) ) {
				$response['status']  = 'error';
				$response['message'] = 'Curl error: ' . curl_error( $ch );
			} else {
				$msg = json_decode( $result );
				if ( isset( $msg->choices[0]->message->content ) ) {
					$response['status']  = 'success';
					$reply_text = $Parsedown->text( $msg->choices[0]->message->content );
					if (strpos($reply_text, 'AI_FORM_DATA') !== false) {
						$reply_text = Qcld_WPBot_Common_Functions::format_and_save_ai_form_response($reply_text);
						$response['message'] = $reply_text;
					} else {
						$response['message'] = $reply_text . $relevant_pagelinks;
					}
				} else {
					$response['status']  = 'error';
					// Show Mistral's own error (wrong key, quota, unknown model) instead of a generic message.
					$api_error = '';
					if ( isset( $msg->message ) && is_string( $msg->message ) ) {
						$api_error = $msg->message;
					} elseif ( isset( $msg->detail ) ) {
						$api_error = is_string( $msg->detail ) ? $msg->detail : wp_json_encode( $msg->detail );
					}
					$response['message'] = '' !== $api_error ? 'Mistral AI error: ' . esc_html( $api_error ) : 'Invalid response format from Mistral AI API';
				}
			}

			curl_close( $ch );
			echo json_encode( $response );
			wp_die();
		}
	}

	/**
	 * @return qcld_wpopenai_addon
	 */
	if ( ! function_exists( 'qcld_wpmistral_addons' ) ) {

		function qcld_wpmistral_addons() {
			$qcld_wpmistral_addon = new qcld_wpmistral_addons();
			return $qcld_wpmistral_addon->instance();
		}
	}

	// fire off the plugin.
	qcld_wpmistral_addons();

}
