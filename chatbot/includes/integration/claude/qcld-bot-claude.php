<?php
/**
 * Claude AI
 *
 * @package Botmaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'qcld_wpclaude_addons' ) ) {

	class qcld_wpclaude_addons {

		private $id = 'Claude AI';
		public $version = '1.0.0';
		public $helper;
		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			$this->includes();
			add_action( 'wp_ajax_claude_response', array( $this, 'claude_response_callback' ) );
			add_action( 'wp_ajax_nopriv_claude_response', array( $this, 'claude_response_callback' ) );
			add_action( 'wp_ajax_qcld_stream_claude', array( $this, 'qcld_stream_claude_callback' ) );
			add_action( 'wp_ajax_nopriv_qcld_stream_claude', array( $this, 'qcld_stream_claude_callback' ) );
			add_action( 'wp_ajax_qcld_claude_settings_option', array( $this, 'qcld_claude_settings_option_callback' ) );
			
			if ( is_admin() && ! empty( $_GET['page'] ) && ( ( $_GET['page'] == 'openai-panel_dashboard' ) || ( $_GET['page'] == 'openai-panel_file' ) || ( $_GET['page'] == 'wpbot_openAi' ) || ( $_GET['page'] == 'openai-panel_help' ) ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'qcld_wb_chatbot_claude_admin_scripts' ) );
			}
		}

		public function qcld_wb_chatbot_claude_admin_scripts() {
			wp_register_script(
				'qcld-wp-chatbot-claude-admin-js',
				QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/claude/assets/js/qcld-wp-claude-admin.js',
				array( 'jquery' ),
				QCLD_wpCHATBOT_VERSION,
				true
			);

			wp_localize_script(
				'qcld-wp-chatbot-claude-admin-js',
				'ajax_object',
				array(
					'ajax_url'                    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'                  => wp_create_nonce( 'wp_chatbot' ),
				)
			);
			wp_enqueue_script( 'qcld-wp-chatbot-claude-admin-js' );
		}

		public function includes() {
			require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/Parsedown.php';
			require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-common-function.php';
		}

		public function qcld_claude_settings_option_callback() {
			$nonce = sanitize_text_field( $_POST['nonce'] );
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
				wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Failed in Security check', 'wpchatbot' ) ) );
				wp_die();
			} else {
				$claude_api_key                      = sanitize_text_field( $_POST['claude_api_key'] ?? '' );
				$voyage_api_key                      = sanitize_text_field( $_POST['voyage_api_key'] ?? '' );
				$claude_model                        = sanitize_text_field( $_POST['claude_model'] ?? '' );
				$claude_enabled                      = sanitize_text_field( $_POST['claude_enabled'] );
				$claude_system_content			     = sanitize_text_field( $_POST['claude_system_content'] ) ?? '';
				$qcld_claude_page_suggestion_enabled = sanitize_text_field( $_POST['qcld_claude_page_suggestion_enabled'] );
				$qcld_claude_append_content          = sanitize_text_field( $_POST['qcld_claude_append_content'] ) ?? '';
				$qcld_claude_prepend_content         = sanitize_text_field( $_POST['qcld_claude_prepend_content'] ) ?? '';
				$claude_rag_enabled				     = sanitize_text_field( $_POST['claude_rag_enabled'] ) ?? '';
				$claude_stream_enabled               = sanitize_text_field( $_POST['claude_stream_enabled'] ?? '0' );
				
				update_option( 'qcld_claude_stream_enabled', $claude_stream_enabled );
				if ( $claude_rag_enabled != '' ) { update_option( 'qcld_claude_rag_enabled', $claude_rag_enabled ); }
				update_option( 'qcld_claude_api_key', $claude_api_key );
				update_option( 'qcld_voyage_api_key', $voyage_api_key );
				if ( $claude_model != '' ) { update_option( 'qcld_claude_model', $claude_model ); }
				if ( $claude_system_content != '' ) { update_option( 'qcld_claude_system_content', $claude_system_content ); }
				if ( $claude_enabled != '' ) { update_option( 'qcld_claude_enabled', $claude_enabled ); }
				if ( $claude_enabled == '1' ) {
					update_option( 'ai_enabled', 0 );
					update_option( 'qcld_ollama_enabled', 0 );
					update_option( 'qcld_openrouter_enabled', 0 );
					update_option( 'qcld_gemini_enabled', 0 );
					update_option( 'qcld_mistral_enabled', 0 );
					update_option( 'qcld_grok_enabled', 0 );
				}
				update_option( 'qcld_claude_page_suggestion_enabled', $qcld_claude_page_suggestion_enabled );
				if (isset($_POST['openai_post_type'])) update_option( 'qcld_openai_relevant_post', $_POST['openai_post_type'] );
				update_option( 'qcld_claude_append_content', $qcld_claude_append_content );
				update_option( 'qcld_claude_prepend_content', $qcld_claude_prepend_content );
			}
			
			// Test query
			$claude_api_key = get_option( 'qcld_claude_api_key' );
			$data = array(
				'model' => get_option('qcld_claude_model') ? get_option('qcld_claude_model') : 'claude-3-5-sonnet-latest',
				'max_tokens' => 100,
				'messages' => array(
					array('role' => 'user', 'content' => 'give a confirmation that you are Claude AI (a small response)')
				)
			);

			$args = array(
				'body'        => json_encode($data),
				'headers'     => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $claude_api_key,
					'anthropic-version' => '2023-06-01'
				),
				'timeout'     => 60,
				'sslverify'   => true,
			);
			$api_url = 'https://api.anthropic.com/v1/messages';
			$result = wp_remote_post($api_url, $args);
			$result_body = json_decode(wp_remote_retrieve_body($result), true);
			
			if( isset($result_body['error']) ) {
				wp_send_json( array( 'status' => 'error', 'msg' => esc_html__( $result_body['error']['message'], 'chatbot' ) ) );
			} elseif ( isset($result_body['content']) && is_array($result_body['content']) ) {
				wp_send_json( array( 'status' => 'success', 'msg' => esc_html__( $result_body['content'][0]['text'], 'chatbot' ) ) );
			}
			wp_die();
		}

		public function qcld_stream_claude_callback() {
			if ( get_option( 'is_rate_limiting_enabled' ) == '1' ) { do_action( 'rate_limit_checker' ); }

			if ( function_exists( 'apache_setenv' ) ) { @apache_setenv( 'no-gzip', 1 ); }
			@ini_set( 'zlib.output_compression', 0 );
			@ini_set( 'implicit_flush', 1 );
			while ( ob_get_level() ) { ob_end_clean(); }
			ob_implicit_flush( 1 );

			header( 'Content-Type: text/event-stream' );
			header( 'Cache-Control: no-cache' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' );

			$claude_api_key = get_option( 'qcld_claude_api_key' );
			$message        = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

			if ( empty( $claude_api_key ) || empty( $message ) ) {
				echo "data: [ERROR] Missing API key or message\n\n";
				flush(); wp_die();
			}

			$claude_model = get_option( 'qcld_claude_model' );
			if ( empty( $claude_model ) || strpos( $claude_model, 'latest' ) !== false ) { $claude_model = 'claude-sonnet-4-6'; }

			$history_html = isset( $_POST['wpwHistory'] ) ? wp_unslash( $_POST['wpwHistory'] ) : '';
			$history      = $this->qcld_parse_wpwhistory_to_json( $history_html );
			$history      = $this->qcld_limit_conversation_history( $history, 10 );

			$system_content = get_option( 'qcld_claude_system_content' ) ? get_option( 'qcld_claude_system_content' ) : 'You are a helpful assistant.';
			$page_title     = isset( $_POST['page_title'] ) ? sanitize_text_field( $_POST['page_title'] ) : '';
			$system_content = str_replace( '[page_title]', $page_title, $system_content );

			$rag_context = '';
			if ( get_option( 'qcld_claude_rag_enabled' ) == '1' ) {
				$rag_context = Qcld_Bot_Rag()->run_rag_search( $message );
			}
			if ( ! empty( $rag_context ) && $rag_context != 'No knowledge base found.' ) {
				$system_content .= "\n\nContext from Knowledge Base:\n" . $rag_context;
			}

			$contents   = $history;
			$contents[] = [ 'role'  => 'user', 'content' => $message ];

			$data = [
				'model' => $claude_model,
				'max_tokens' => 1024,
				'system' => $system_content,
				'messages' => $contents,
				'stream' => true
			];

			$api_url = 'https://api.anthropic.com/v1/messages';

			$ch = curl_init( $api_url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'x-api-key: ' . $claude_api_key,
				'anthropic-version: 2023-06-01'
			] );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
			curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch, $chunk ) {
				$lines = explode( "\n", $chunk );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( strpos( $line, 'data: ' ) === 0 ) {
						$json_str = substr( $line, 6 );
						$decoded = json_decode( $json_str, true );
						if ( isset($decoded['type']) && $decoded['type'] === 'message_stop' ) {
							echo "data: [DONE]\n\n"; flush(); continue;
						}
						if ( isset($decoded['type']) && $decoded['type'] === 'content_block_delta' ) {
							$text_delta = $decoded['delta']['text'];
							$payload = json_encode( [ 'choices' => [ [ 'delta' => [ 'content' => $text_delta ] ] ] ] );
							echo 'data: ' . $payload . "\n\n";
							echo str_repeat( ' ', 1024 );
							flush();
						}
					}
				}
				return strlen( $chunk );
			} );

			curl_exec( $ch );
			if ( curl_errno( $ch ) ) {
				echo 'data: [ERROR] ' . curl_error( $ch ) . "\n\n"; flush();
			}
			curl_close( $ch );
			do_action( 'qcld_openai_user_rate_cal', 1 );
			exit;
		}

		public function claude_response_callback() {
			if (get_option('is_rate_limiting_enabled') == '1') { do_action('rate_limit_checker'); }
			$claude_api_key   = get_option( 'qcld_claude_api_key' );
			$keyword          = isset($_POST['keyword']) ? $_POST['keyword'] : '';

			$rag_context = "";
			if (get_option('qcld_claude_rag_enabled') == '1') {
				$rag_context = Qcld_Bot_Rag()->run_rag_search($keyword);
			}
			
			$relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword );
			$relevant_pagelink = array_slice( $relevant_pagelink, 0, 5, true );
			$relevant_pagelinks = '';
			if ( ( get_option( 'qcld_claude_page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {
				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
				if ( is_array( $relevant_post_link[ get_wpbot_locale() ] ) ) {
					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ get_wpbot_locale() ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					$relevant_pagelinks = '<br><br><p><em>' . $relevant_post_link[ get_wpbot_locale() ] . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			}

			$Parsedown = new Parsedown();

			$claude_model = get_option( 'qcld_claude_model' );
			if ( empty( $claude_model ) || strpos( $claude_model, 'latest' ) !== false ) { $claude_model = 'claude-sonnet-4-6'; }
			$api_url = 'https://api.anthropic.com/v1/messages';
			
			$page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
			$history = [];
			if (!empty($_POST['ai_history'])) {
				$parsed_history = json_decode(wp_unslash($_POST['ai_history']), true);
				if (is_array($parsed_history)) {
					foreach ($parsed_history as $h) {
						if (isset($h['role']) && isset($h['content'])) {
							$role = ($h['role'] === 'assistant') ? 'assistant' : 'user';
							$history[] = [
								'role' => $role,
								'content' => sanitize_text_field($h['content'])
							];
						}
					}
				}
			} elseif (!empty($_POST['wpwHistory'])) {
				$history_html = wp_unslash($_POST['wpwHistory']);
				$history = $this->qcld_parse_wpwhistory_to_json($history_html);
			}
			$history = $this->qcld_limit_conversation_history($history, 10);

			$system_content = get_option( 'qcld_claude_system_content' ) ? get_option( 'qcld_claude_system_content' ) : 'You are a helpful assistant.';
			$system_content = str_replace('[page_title]', $page_title, $system_content);
			
			if (!empty($page_title) && strpos($system_content, $page_title) === false) {
				$system_content .= "\n\nThe user is currently browsing the page: \"" . $page_title . "\". Use this information to help them navigate or provide context-aware answers.";
			}

			if (!empty($rag_context) && $rag_context != "No knowledge base found.") {
				$system_content .= "\n\nContext from Knowledge Base:\n" . $rag_context;
			}

			$action_prompt = !empty($_POST['action_prompt']) ? wp_unslash($_POST['action_prompt']) : '';
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
				$system_content .= "3. Once all necessary information is collected for the form, you MUST output a final JSON block summarizing the collected data. The keys inside the \"data\" object MUST be dynamically named based on the specific questions you asked during the form collection (e.g., \"Full Name\", \"Company Size\", \"Email\", etc.). The final JSON block must be wrapped EXACTLY in these delimiters:\n";
				$system_content .= "__AI_FORM_DATA__{ \"form_title\": \"<Form Title>\", \"data\": { \"Question 1\": \"Answer 1\", \"Question 2\": \"Answer 2\" } }__AI_FORM_DATA_END__\n";
				$system_content .= "Do not include any other text after this JSON block once the form is complete.\n";
				$system_content .= "4. If the user provides an invalid, irrelevant, or nonsensical answer to your question, DO NOT apologize or state that you lack information. Instead, respond with 'Invalid answer found' and ask the exact same question again.";
			}

			$contents = $history;
			$last_msg = end($contents);
			if (!$last_msg || $last_msg['role'] !== 'user' || $last_msg['content'] !== $keyword) {
				$contents[] = [ 'role' => 'user', 'content' => $keyword ];
			}

			$data = array(
				'model' => $claude_model,
				'max_tokens' => 1024,
				'system' => $system_content,
				'messages' => $contents
			);

			$max_attempts = 3;
			$attempt = 0;
			$response = ['status' => 'error', 'message' => 'Unknown error'];

			while ($attempt < $max_attempts) {
				$attempt++;
				$args = array(
					'body'        => json_encode($data),
					'headers'     => array(
						'Content-Type'      => 'application/json',
						'x-api-key'         => $claude_api_key,
						'anthropic-version' => '2023-06-01'
					),
					'timeout'     => 60,
					'sslverify'   => true,
				);

				$result = wp_remote_post($api_url, $args);

				if ( is_wp_error( $result ) ) {
					$response['status']  = 'error';
					$response['message'] = 'WP_Error: ' . $result->get_error_message();
					break;
				}

				$http_code = wp_remote_retrieve_response_code($result);
				$body = wp_remote_retrieve_body($result);
				$msg = json_decode($body, true);

				if (isset($msg['content']) && is_array($msg['content']) && !empty($msg['content'][0]['text'])) {
					$response['status']  = 'success';
					$reply_text = $Parsedown->text( $msg['content'][0]['text'] );
					if (strpos($reply_text, 'AI_FORM_DATA') !== false) {
						$reply_text = Qcld_WPBot_Common_Functions::format_and_save_ai_form_response($reply_text);
						$response['message'] = $reply_text;
					} else {
						$response['message'] = $reply_text . $relevant_pagelinks;
					}
					break;
				}

				if (($http_code == 503 || $http_code == 429) && $attempt < $max_attempts) {
					$wait_seconds = $attempt * 2;
					sleep($wait_seconds);
					continue;
				}

				$response['status']  = 'error';
				if ( isset( $msg['error']['message'] ) ) {
					$response['message'] = 'Claude API Error: ' . $msg['error']['message'];
				} else {
					$response['message'] = 'Invalid response format from Claude API';
				}
				$response['raw_response'] = $body;
				break;
			}
			do_action('qcld_openai_user_rate_cal', 1);
			echo json_encode( $response );
			wp_die();
		}

		public function qcld_parse_wpwhistory_to_json($html) {
			if (empty($html)) return [];
			$messages = [];
			preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches);
			if (empty($matches[0])) return [];

			foreach ($matches[0] as $li_html) {
				$is_assistant = (strpos($li_html, 'wp-chatbot-msg') !== false && strpos($li_html, 'wp-chatbot-agent') !== false);
				$content = '';
				if (preg_match('/<div[^>]*class=["\'][^"\']*wp-chatbot-paragraph[^"\']*["\'][^>]*>(.*?)<\/div>/is', $li_html, $p_matches)) {
					$content = $p_matches[1];
				} else if (preg_match('/<div[^>]*class=["\'][^"\']*wpw-chatbot-user-msg[^"\']*["\'][^>]*>(.*?)<\/div>/is', $li_html, $u_matches)) {
					$content = $u_matches[1];
				} else {
					$clean_li = preg_replace('/<div[^>]*class=["\'][^"\']*wp-chatbot-avatar[^"\']*["\'][^>]*>.*?<\/div>/is', '', $li_html);
					$clean_li = preg_replace('/<div[^>]*class=["\'][^"\']*wp-chatbot-agent[^"\']*["\'][^>]*>.*?<\/div>/is', '', $clean_li);
					$content = strip_tags($clean_li);
				}

				$content = preg_replace('/<br\s*\/?>/i', "\n", $content);
				$content = preg_replace('/<div[^>]*class=["\'][^"\']*relevant-links[^"\']*["\'][^>]*>.*?<\/div>/is', '', $content);
				$content = preg_replace('/<span[^>]*class=["\'][^"\']*qcld-chatbot-wildcard[^"\']*["\'][^>]*>.*?<\/span>/is', '', $content);
				$content = trim(strip_tags($content));
				$content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

				if (!empty($content)) {
					$messages[] = [
						'role' => $is_assistant ? 'assistant' : 'user', // Claude uses 'assistant'
						'content' => $content
					];
				}
			}
			return $messages;
		}

		public function qcld_limit_conversation_history($messages, $max_messages = 10) {
			if (count($messages) > $max_messages) {
				return array_slice($messages, -$max_messages);
			}
			return $messages;
		}
	}

	if ( ! function_exists( 'qcld_wpclaude_addons' ) ) {
		function qcld_wpclaude_addons() {
			$qcld_wpclaude_addon = new qcld_wpclaude_addons();
			return $qcld_wpclaude_addon->instance();
		}
	}

	qcld_wpclaude_addons();
}
