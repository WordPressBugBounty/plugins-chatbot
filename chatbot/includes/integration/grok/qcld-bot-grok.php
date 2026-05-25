<?php
/**
 * grok AI
 *
 * @package Botmaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'qcld_wpgrok_addons' ) ) {


	/**
	 * Main Class.
	 */
	final class qcld_wpgrok_addons {

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
			add_action( 'wp_ajax_qcld_grok_response', array( $this, 'grok_response_callback' ) );
			add_action( 'wp_ajax_nopriv_qcld_grok_response', array( $this, 'grok_response_callback' ) );
            add_action('wp_ajax_qcld_stream_grokai', array($this, 'qcld_stream_grok_callback'));
			add_action( 'wp_ajax_qcld_grok_add_collection', array( $this, 'qcld_grok_add_collection_callback' ) );
			add_action('wp_ajax_qcld_grok_collectionlist', array($this, 'qcld_grok_collectionlist_callback'));
			add_action('wp_ajax_nopriv_qcld_stream_grokai', array($this, 'qcld_stream_grok_callback'));
			add_action('wp_ajax_qcld_grok_remove_collection', array($this, 'qcld_grok_remove_collection_callback'));
			add_action( 'wp_ajax_qcld_grok_settings_option', array( $this, 'qcld_grok_settings_option_callback' ) );
			add_action( 'wp_ajax_qcld_grok_file_upload', array( $this, 'qcld_grok_file_upload_callback' ) );
			if ( is_admin() && ! empty( $_GET['page'] ) && ( ( $_GET['page'] == 'openai-panel_dashboard' ) || ( $_GET['page'] == 'openai-panel_file' ) || ( $_GET['page'] == 'wpbot_openAi' ) || ( $_GET['page'] == 'openai-panel_help' ) ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
				add_action( 'admin_enqueue_scripts', array( $this, 'qcld_wb_chatbot_grok_admin_scripts' ) );
			}
			// add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_grok_scripts'));
			
		}

		public function qcld_wb_chatbot_grok_admin_scripts() {
			wp_register_script(
				'qcld-wp-chatbot-grok-admin',
				QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/grok/js/qcld-wp-grok-admin.js',
				array( 'jquery' ),
				QCLD_wpCHATBOT_VERSION,
				true
			);

			// Localize the script with necessary data.
			wp_localize_script(
				'qcld-wp-chatbot-grok-admin',
				'ajax_object',
				array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'                   => wp_create_nonce( 'wp_chatbot' ),
					'grok_api_key'                 => get_option( 'qcld_grok_api_key' ),
					'grok_model'                   => get_option( 'qcld_grok_model' ),
					'grok_enabled'                 => get_option( 'qcld_grok_enabled' ),
					'qcld_grok_rag_enabled'        => get_option( 'qcld_grok_rag_enabled' ),
					'qcld_grok_append_content'     => get_option( 'qcld_grok_append_content' ),
					'qcld_grok_prepend_content'    => get_option( 'qcld_grok_prepend_content' ),
				)
			);

			wp_enqueue_script( 'qcld-wp-chatbot-grok-admin' );
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

		public function qcld_grok_settings_option_callback() {
			$nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot'),
					)
				);
				wp_die();
			} else {
				$grok_api_key                      = sanitize_text_field( wp_unslash($_POST['grok_api_key']) ?? '' );
				$grok_model                        = sanitize_text_field( wp_unslash($_POST['grok_model']) ?? '' );
				$grok_enabled                      = sanitize_text_field(wp_unslash($_POST['grok_enabled']));
				$qcld_grok_page_suggestion_enabled = sanitize_text_field(wp_unslash($_POST['qcld_grok_page_suggestion_enabled']));
				$qcld_grok_append_content          = sanitize_text_field(wp_unslash($_POST['qcld_grok_append_content'])) ?? '';
				$qcld_grok_prepend_content         = sanitize_text_field(wp_unslash($_POST['qcld_grok_prepend_content'])) ?? '';
				$grok_rag_enabled				   = sanitize_text_field(wp_unslash($_POST['grok_rag_enabled'])) ?? '';
                $qcld_grok_system_content          = sanitize_text_field(wp_unslash($_POST['qcld_grok_system_content'])) ?? '';
                $grok_stream_enabled               = sanitize_text_field(wp_unslash($_POST['grok_stream_enabled'])) ?? '';
				$grok_management_api_key		   = sanitize_text_field( wp_unslash($_POST['grok_management_api_key']) ?? '' );
				$grok_collection_id			       = sanitize_text_field( wp_unslash($_POST['grok_collection_id']) ?? '' );

				if ( $grok_management_api_key != '' ) {
					update_option( 'qcld_grok_management_api_key', $grok_management_api_key );
				}	
				if ( $grok_rag_enabled != '' ) {
					update_option( 'qcld_grok_rag_enabled', $grok_rag_enabled );
				}
                if ( $grok_stream_enabled != '' ) {
                    update_option( 'qcld_grok_stream_enabled', $grok_stream_enabled );
                }
				if ( $grok_api_key != '' ) {
					update_option( 'qcld_grok_api_key', $grok_api_key );
				}
				if ( $grok_model != '' ) {
					update_option( 'qcld_grok_model', $grok_model );
				}
				if ( $grok_enabled != '' ) {
					update_option( 'qcld_grok_enabled', $grok_enabled );
				}
				if ( $grok_collection_id != '' ) {
					update_option( 'qcld_grok_collection_id', $grok_collection_id );
				}
				if ( $grok_enabled == '1' ) {
					update_option( 'ai_enabled', 0 );
					update_option( 'qcld_ollama_enabled', 0 );

					update_option( 'qcld_openrouter_enabled', 0 );
				} else {
					update_option( 'ai_enabled', 1 );
				}
                if ( $qcld_grok_system_content != '' ) {
                    update_option( 'qcld_grok_system_content', $qcld_grok_system_content );
                }
				update_option( 'qcld_grok_page_suggestion_enabled', $qcld_grok_page_suggestion_enabled );
				if ( isset( $_POST['openai_post_type'] ) ) {
					$openai_post_types = is_array( $_POST['openai_post_type'] )
						? array_map( 'sanitize_text_field', wp_unslash( $_POST['openai_post_type'] ) )
						: sanitize_text_field( wp_unslash( $_POST['openai_post_type'] ) );
					update_option( 'qcld_openai_relevant_post', $openai_post_types );
				}

				update_option( 'qcld_grok_append_content', $qcld_grok_append_content );
				update_option( 'qcld_grok_prepend_content', $qcld_grok_prepend_content );
			}
				wp_send_json( $grok_enabled );
		}

		public function grok_response_callback() {


			if (get_option('is_rate_limiting_enabled') == '1') {
                do_action('rate_limit_checker');
			}
			$grok_api_key   = get_option( 'qcld_grok_api_key' );
			$keyword          = isset($_POST['keyword']) ? sanitize_text_field( wp_unslash($_POST['keyword']) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$grok_system_content = get_option( 'qcld_grok_system_content' );
			$rag_context = "";
			if (get_option('qcld_grok_rag_enabled') == '1') {
				$rag_context = Qcld_Bot_Rag()->run_rag_search($keyword);
			}
			$relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword );
			$relevant_pagelink = array_slice( $relevant_pagelink, 0, 5, true );
			if ( ( get_option( 'page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {
				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
				if ( is_array( $relevant_post_link[ get_wpbot_locale() ] ) ) {
					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ get_wpbot_locale() ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					$relevant_pagelinks = '<br><br><p><em>' . $relevant_post_link[ get_wpbot_locale() ] . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			} else {
				$relevant_pagelinks = '';
			}
			$collection_id = get_option( 'qcld_grok_collection_id' ) ?? '';
            if( $collection_id != '' ) {
				$result = $this->direct_search_collection( $keyword );
				$response['status']  = 'success';
				$response['message'] = $result ;
				wp_send_json( $response );
			} else {
                $response_mess = wp_remote_post('https://api.x.ai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $grok_api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'messages'    => [
                            ['role' => 'system', 'content' => $grok_system_content ?? 'You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.' ],
                            ['role' => 'user',   'content' => $keyword ]
                        ],
                        'model'       => 'grok-3-latest',
                        'temperature' => 0,
                        'stream'      => false
                    ]),
                    'timeout'     => 60,
                ]);
                $response['message'] = '';
                $status_code = wp_remote_retrieve_response_code($response_mess);
                // Step 2: Get the raw body (this is almost always what you want)
                $data = wp_remote_retrieve_body($response_mess);
                $data = json_decode($data, true); // Decode as associative array
                $response['status']  = 'success';
                $response['message']  = $data['choices'][0]['message']['content'] ?? 'No content received'; // true = assoc array
				do_action('qcld_openai_user_rate_cal', 1);
				wp_send_json( $response );
            }
		}
        public function qcld_stream_grok_callback()
		{
                
                if (!defined('ABSPATH')) {
                    // Optional: protect direct access or require wp-load.php
                    // For security → better to use admin-ajax or custom rewrite rule in production
                    http_response_code(403);
                    exit;
                }
				if (get_option('is_rate_limiting_enabled') == '1') {
					do_action('rate_limit_checker');
				}
                $api_key = get_option('qcld_grok_api_key'); // ← Use get_option() or env in real code!

                // Disable compression (critical for streaming)
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', 1);
                }
                @ini_set('zlib.output_compression', 0);
                while (ob_get_level() > 0) { ob_end_clean(); }

                set_time_limit(180); // Allow long-running requests

                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key,
                ];
                $system_content =  'You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.';
                $messages = [];
                // Load previous conversation from cookie if continuity is enabled
				if ($conversation_continuity == 1 && !empty($_COOKIE['last_five_prompt'])) {
					$decoded_cookie = json_decode(stripslashes($_COOKIE['last_five_prompt']), true);
					if (is_array($decoded_cookie)) {
						$messages = $decoded_cookie;
					}
				}
				// Ensure system message is the first message.
				if (empty($messages) || (isset($messages[0]) && $messages[0]['role'] !== 'system')) {
					array_unshift($messages, ['role' => 'system', 'content' => $system_content]);
				} elseif (isset($messages[0]) && $messages[0]['role'] === 'system' && $messages[0]['content'] !== $system_content) {
					$messages[0]['content'] = $system_content;
				}

                array_push($messages, ['role' => 'user', 'content' => sanitize_text_field( wp_unslash( $_POST['message'] ) )]);
				$postData = json_encode([
					'model'    => 'grok-3-latest',
					'messages' => $messages,
					'stream' => true
				]);

				// phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming requires native cURL
				$ch = curl_init('https://api.x.ai/v1/chat/completions');
				curl_setopt($ch, CURLOPT_POST, true); // phpcs:ignore WordPress.WP.AlternativeFunctions
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // phpcs:ignore WordPress.WP.AlternativeFunctions
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // phpcs:ignore WordPress.WP.AlternativeFunctions
				curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$full_assistant_response) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					echo $chunk;
					echo str_repeat(' ', 1024);
					flush();
					ob_flush();

					// Parse the chunk to extract assistant's content for continuity.
					$lines = explode("\n", $chunk);
					foreach ($lines as $line) {
						if (strpos($line, 'data: ') === 0) {
							$json_data = substr($line, 6);
							if ($json_data === '[DONE]') {
								continue;
							}
							$data = json_decode($json_data, true);
							if (isset($data['choices'][0]['delta']['content'])) {
								$full_assistant_response .= $data['choices'][0]['delta']['content'];
							}
						}
					}
					return strlen($chunk);
				});
				curl_exec($ch); // phpcs:ignore WordPress.WP.AlternativeFunctions
				curl_close($ch); // phpcs:ignore WordPress.WP.AlternativeFunctions
				// phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming requires native cURL
                $ch = curl_init('https://api.x.ai/v1/chat/completions');

				// phpcs:ignore WordPress.WP.AlternativeFunctions
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => $postData,
                    CURLOPT_RETURNTRANSFER => false,           // We echo ourselves
                    CURLOPT_WRITEFUNCTION  => function ($curl, $chunk) {
                        echo $chunk;
                        flush();                                 // Push to browser immediately
                        return strlen($chunk);
                    },
                    CURLOPT_TIMEOUT        => 150,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_ENCODING       => '',              // Let server decide
                ]);

                $success = curl_exec($ch); // phpcs:ignore WordPress.WP.AlternativeFunctions
				do_action('qcld_openai_user_rate_cal', 1);
                if (curl_errno($ch)) { // phpcs:ignore WordPress.WP.AlternativeFunctions
                    $err = curl_error($ch); // phpcs:ignore WordPress.WP.AlternativeFunctions
                    echo "data: " . wp_json_encode( array( 'error' => 'cURL error: ' . $err ) ) . "\n\n";
                    flush();
                }

                curl_close($ch); // phpcs:ignore WordPress.WP.AlternativeFunctions

                // Final done signal (optional but nice)
                echo "data: [DONE]\n\n";
                flush();
		}
		public function qcld_grok_collectionlist_callback() {
			$nonce = sanitize_text_field( wp_unslash($_POST['nonce']) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot'),
					)
				);
				wp_die();
			}
			$grok_api_key   = get_option( 'qcld_grok_api_key' );
			$response_raw = wp_remote_get(
				'https://api.x.ai/v1/collections',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $grok_api_key,
						'Content-Type'  => 'application/json',
					),
					'timeout' => 60,
				)
			);
			$response = json_decode( wp_remote_retrieve_body( $response_raw ), true );
			wp_send_json( $response );
		}
		public function qcld_grok_file_upload_callback()
		{
			
			$nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
			if (! wp_verify_nonce($nonce, 'wp_chatbot') || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__('Failed in Security check', 'chatbot'),
					)
				);
				wp_die();
			} else {
				// $uploadedfile = $_FILES['file'];
				// $collection_id = sanitize_text_field(wp_unslash($_POST['collection_id']));
				// $api_key = get_option( 'qcld_grok_api_key' );
	
				// $ch = curl_init();

				// $file = new CURLFile(
				// 	$uploadedfile['tmp_name'],
				// 	$uploadedfile['type'] ?: 'application/octet-stream',
				// 	$uploadedfile['name']
				// );

				// $post_data = ['file' => $file];

				// curl_setopt_array($ch, [
				// 	CURLOPT_URL            => 'https://api.x.ai/v1/files',
				// 	CURLOPT_POST           => true,
				// 	CURLOPT_POSTFIELDS     => $post_data,
				// 	CURLOPT_RETURNTRANSFER => true,
				// 	CURLOPT_HTTPHEADER     => [
				// 		'Authorization: Bearer ' . $api_key,
				// 		// Do NOT add Content-Type here!!
				// 	],
				// 	CURLOPT_TIMEOUT        => 180,
				// 	CURLOPT_CONNECTTIMEOUT => 30,
				// ]);

				// $result = json_decode(curl_exec($ch));
				// $error  = curl_error($ch);
				// curl_close($ch);
				// $file_id = $result->id
				$collection_id = "collection_22aa88f1-80e8-4e1a-8e13-c119afb0481c";
				$file_id =  "file_06208083-0e27-44ff-af29-5faa211f4892";
				$this->add_file_to_collection($collection_id, $file_id, []);
				if (! empty($res->error)) {
					$response['status']  = 'error';
					$response['message'] = $res->error->message;
				}

				if (! empty($res->status)) {
					$response['status']  = 'success';
					$response['message'] = 'Successfully Created file' . $res->id;
				}
				// echo wp_send_json(array($response));
				// wp_die();
			}
		}
		public function add_file_to_collection($collection_id, $file_id, $metadata = []) {
			$url = "https://management-api.x.ai/v1/collections/{$collection_id}/documents/{$file_id}";
			$grok_management_api_key = get_option( 'qcld_grok_management_api_key' );
			
			$args = [
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => [
					'Authorization' => 'Bearer ' . $grok_management_api_key,  // Management API key required
					'Content-Type'  => 'application/json',
				],
				// 'body'        => json_encode([
				// 	'fields' => $metadata  // Optional: matches your field_definitions
				// ]),
			]; 
				// return ['success' => false, 'error' => $response->get_error_message()];
			$response = wp_remote_post( $url, $args );
			$body = wp_remote_retrieve_body( $response );
			$status_code = wp_remote_retrieve_response_code( $response );
			if ($status_code >= 200 && $status_code < 300) {
				return ['success' => true, 'message' => 'File added to collection', 'body' => $body];
			} else {
				return ['success' => false, 'status' => $status_code, 'error' => $body];
			}
		}
		public function qcld_grok_remove_collection_callback() {
			$nonce = sanitize_text_field( wp_unslash($_POST['nonce']) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot'),
					)
				);
				wp_die();
			}
			$grok_api_key   = get_option( 'qcld_grok_api_key' );
			$collection_id = sanitize_text_field( wp_unslash($_POST['collection_id']) ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$url = 'https://api.x.ai/v1/collections/' . $collection_id; // Check exact endpoint in docs.x.ai
			$args = [
				'method' => 'DELETE',
				'headers' => [
					'Authorization' => 'Bearer ' . $grok_api_key,
					'Content-Type' => 'application/json'
				],
				'timeout' => 60,
			];
			$response = wp_remote_request( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_send_json( $response_data );
		}
		public function qcld_grok_add_collection_callback() {
			$nonce = sanitize_text_field( wp_unslash($_POST['nonce']) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot'),
					)
				);
				wp_die();
			}
			$grok_api_key    = get_option( 'qcld_grok_api_key' );
			$collection_name = isset( $_POST['collection_name'] ) ? sanitize_text_field( wp_unslash( $_POST['collection_name'] ) ) : ( site_url() . wp_rand( 1, 999 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$response_raw = wp_remote_post(
				'https://api.x.ai/v1/collections',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $grok_api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'collection_name' => $collection_name, 'field_definitions' => array() ) ),
					'timeout' => 60,
				)
			);
			$response = json_decode( wp_remote_retrieve_body( $response_raw ), true );
			wp_send_json( $response );
		}
		public function direct_search_collection( $query ) {
			
			$collection_id = get_option( 'qcld_grok_collection_id' ); 
			$grok_api_key   = get_option( 'qcld_grok_api_key' );
			$url = 'https://api.x.ai/v1/documents/search';
			$payload = [
				'query' => $query,
				'source' => ['collection_ids' => [$collection_id]],
				'retrieval_mode' => ['type' => 'hybrid']  // or 'semantic', 'keyword'
			];
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $grok_api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode($payload),
				'timeout' => 30,
			];
			$response = wp_remote_post($url, $args);
            $result = json_decode(wp_remote_retrieve_body($response), true);
			if (is_wp_error($response)) {
				return ['error' => $response->get_error_message()];
			}
			foreach ($result['matches'] as $key => $match) {
				if ($key >= 1) break;
				$responses = $match ["chunk_content"];
			}
			return $responses;
		}
	}

	/**
	 * @return qcld_wpopenai_addon
	 */
	if ( ! function_exists( 'qcld_wpgrok_addons' ) ) {

		function qcld_wpgrok_addons() {
			$qcld_wpgrok_addon = new qcld_wpgrok_addons();
			return $qcld_wpgrok_addon->instance();
		}
	}

	// fire off the plugin.
	qcld_wpgrok_addons();

}