<?php
/**
 * DeepSeek: settings and chat replies.
 *
 * Replies go through the chatbot module's AI client (WPBot Core's Provider_Client,
 * OpenAI-compatible API at api.deepseek.com) with the knowledge base, AI forms,
 * AI Actions and conversation history, like the other providers. Errors are
 * answered with status "error"; Qcld_WPBot_AI_Friendly_Errors shows visitors
 * the friendly message and admins the real one.
 *
 * @package Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Qcld_WPBot_DeepSeek' ) ) {

	/**
	 * DeepSeek integration.
	 */
	final class Qcld_WPBot_DeepSeek {

		/** Models offered on the settings screen. */
		const MODELS = array( 'deepseek-chat', 'deepseek-reasoner' );

		/** @var Qcld_WPBot_DeepSeek|null */
		protected static $instance = null;

		/**
		 * Single instance.
		 *
		 * @return Qcld_WPBot_DeepSeek
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Hooks.
		 */
		private function __construct() {
			add_action( 'wp_ajax_deepseekai_settings_option', array( $this, 'save_settings' ) );
			add_action( 'wp_ajax_deepseek_response', array( $this, 'response' ) );
			add_action( 'wp_ajax_nopriv_deepseek_response', array( $this, 'response' ) );
		}

		/**
		 * AJAX (admins): save the DeepSeek settings.
		 *
		 * @return void
		 */
		public function save_settings() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot' ),
					)
				);
			}
			$post = function ( $field ) {
				return isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
			};

			update_option( 'qcld_deepseekai_api_key', qcld_wpbot_posted_api_key( 'qcld_deepseekai_api_key', 'qcld_deepseekai_api_key' ) );
			$model = $post( 'qcld_deepseekai_engines' );
			if ( in_array( $model, self::MODELS, true ) ) {
				update_option( 'qcld_deepseekai_engines', $model );
			}
			$max_tokens = $post( 'max_tokens' );
			update_option( 'deepseekai_max_tokens', '' === $max_tokens ? '' : (string) min( 8192, max( 1, absint( $max_tokens ) ) ) );
			$temperature = $post( 'temperature' );
			update_option( 'qcld_deepseekai_temperature', is_numeric( $temperature ) ? (string) max( 0, min( 2, (float) $temperature ) ) : '' );
			update_option( 'qcld_deepseekai_rag_enabled', '1' === $post( 'qcld_deepseekai_rag_enabled' ) ? '1' : '0' );
			update_option( 'qcld_deepseekai_page_suggestion_enabled', '1' === $post( 'qcld_deepseekai_page_suggestion_enabled' ) ? '1' : '0' );

			$enabled = '1' === $post( 'deepseekai_enabled' ) ? '1' : '0';
			update_option( 'qcld_deepseekai_enabled', $enabled );
			if ( '1' === $enabled ) {
				qcld_wpbot_ai_provider_switched_on( 'qcld_deepseekai_enabled' ); // Others off, site search off.
			} else {
				qcld_wpbot_fall_back_to_openai(); // Not while another provider is on.
			}
			wp_send_json( array( 'success' => true ) );
		}

		/**
		 * AJAX (visitors): one chat reply from DeepSeek.
		 *
		 * @return void
		 */
		public function response() {
			// Same nonce as the other providers' replies (wp_chatbot_obj.ajax_nonce).
			if ( ! check_ajax_referer( 'wp_chatbot', 'nonce', false ) ) {
				echo wp_json_encode(
					array(
						'status'  => 'error',
						'message' => esc_html__( 'Security check failed. Please reload the page.', 'chatbot' ),
					)
				);
				wp_die();
			}
			if ( '1' === (string) get_option( 'is_rate_limiting_enabled' ) ) {
				do_action( 'rate_limit_checker' );
			}
			$keyword = ( isset( $_POST['keyword'] ) && is_string( $_POST['keyword'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['keyword'] ) ) : '';
			if ( '' === trim( $keyword ) || ! class_exists( '\QuantumCloud\Chatbot\Ai\Ai_Client' ) ) {
				echo wp_json_encode(
					array(
						'status'  => 'error',
						'message' => esc_html__( 'DeepSeek could not answer.', 'chatbot' ),
					)
				);
				wp_die();
			}

			$settings = \QuantumCloud\Chatbot\Ai\Provider_Settings::get( 'deepseek' );
			$history  = array();
			if ( ! empty( $_POST['ai_history'] ) && is_string( $_POST['ai_history'] ) ) {
				$parsed = json_decode( wp_unslash( $_POST['ai_history'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each message is cleaned by Ai_Client::history().
				if ( is_array( $parsed ) ) {
					$history = $parsed;
					// The widget adds the question to the history before sending it.
					$last = end( $history );
					if ( is_array( $last ) && isset( $last['role'], $last['content'] ) && 'user' === $last['role'] && trim( (string) $last['content'] ) === trim( $keyword ) ) {
						array_pop( $history );
					}
				}
			}

			$result = \QuantumCloud\Chatbot\Ai\Ai_Client::reply(
				apply_filters( 'qcld_wpbot_ai_keyword', $keyword ),
				array(
					'provider' => 'deepseek',
					'history'  => $history,
					'context'  => $this->context( $keyword, $settings ),
				)
			);

			if ( is_wp_error( $result ) ) {
				echo wp_json_encode(
					array(
						'status'  => 'error',
						/* translators: %s: the error. */
						'message' => sprintf( esc_html__( 'DeepSeek error: %s', 'chatbot' ), esc_html( $result->get_error_message() ) ),
					)
				);
				wp_die();
			}

			if ( ! class_exists( 'Qcld_Parsedown' ) ) {
				require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/Parsedown.php';
			}
			$parsedown = class_exists( 'Qcld_Parsedown' ) ? new Qcld_Parsedown() : null;
			$reply     = $parsedown ? $parsedown->text( (string) $result['text'] ) : wpautop( esc_html( (string) $result['text'] ) );
			if ( false !== strpos( $reply, 'AI_FORM_DATA' ) ) {
				$reply = Qcld_WPBot_Common_Functions::format_and_save_ai_form_response( $reply );
			} else {
				$reply .= $this->page_links( $keyword, $settings );
			}
			echo wp_json_encode(
				array(
					'status'  => 'success',
					'message' => $reply,
				)
			);
			wp_die();
		}

		/**
		 * Extra system text: knowledge base, AI Actions, AI forms.
		 *
		 * @param string     $keyword  Visitor's question.
		 * @param array|null $settings DeepSeek settings.
		 * @return string
		 */
		private function context( $keyword, $settings ) {
			$context = '';
			if ( ! empty( $settings['knowledge_base'] ) && function_exists( 'Qcld_Bot_Rag' ) ) {
				$rag_links   = array();
				$rag_context = Qcld_Bot_Rag()->run_rag_search( $keyword, null, $rag_links );
				if ( is_string( $rag_context ) && '' !== $rag_context && 'No knowledge base found.' !== $rag_context ) {
					$context .= "Use the following context to answer the user's question:\n\nContext:\n" . $rag_context;
				}
			}
			// Raw action prompts only from the admin AI Actions playground.
			$action_prompt = ( ! empty( $_POST['action_prompt'] ) && is_string( $_POST['action_prompt'] ) && current_user_can( 'manage_options' ) ) ? wp_unslash( $_POST['action_prompt'] ) : ''; // phpcs:ignore WordPress.Security
			if ( '' !== $action_prompt ) {
				$context .= "\n\nCRITICAL ACTIVE ACTION INSTRUCTION:\n" . $action_prompt;
			}
			$forms = get_option( 'wpbot_ai_forms', array() );
			if ( ! empty( $forms ) && is_array( $forms ) ) {
				$context .= "\n\nYou must handle the following interactive forms when the user asks for them:\n";
				foreach ( $forms as $form ) {
					if ( isset( $form['title'], $form['prompt'] ) ) {
						$context .= "\nForm Title: " . $form['title'] . "\nInstructions: " . $form['prompt'] . "\n";
					}
				}
				$context .= "\n\nCRITICAL INSTRUCTIONS FOR INTERACTIVE FORMS:\n";
				$context .= "When a user triggers an interactive form, you must act as a step-by-step data collection agent.\n";
				$context .= "1. DO NOT ask all questions at once. Ask exactly ONE question at a time.\n";
				$context .= "2. Wait for the user's response before asking the next question.\n";
				$context .= "3. Once all necessary information is collected for the form, you MUST output a final JSON block summarizing the collected data, wrapped EXACTLY in these delimiters:\n";
				$context .= "__AI_FORM_DATA__{ \"form_title\": \"<Form Title>\", \"data\": { \"Question 1\": \"Answer 1\", \"Question 2\": \"Answer 2\" } }__AI_FORM_DATA_END__\n";
				$context .= "Do not include any other text after this JSON block once the form is complete.\n";
				if ( function_exists( 'qcld_wpbot_ai_invalid_answer_rule' ) ) {
					$context .= qcld_wpbot_ai_invalid_answer_rule( 4 );
				}
			}
			return trim( $context );
		}

		/**
		 * Related page links under the reply, when page suggestions are on.
		 *
		 * @param string     $keyword  Visitor's question.
		 * @param array|null $settings DeepSeek settings.
		 * @return string HTML.
		 */
		private function page_links( $keyword, $settings ) {
			if ( empty( $settings['page_suggestions'] ) && '1' !== (string) get_option( 'page_suggestion_enabled' ) ) {
				return '';
			}
			$links = array_slice( (array) Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword ), 0, 5, true );
			if ( ! $links ) {
				return '';
			}
			$label  = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
			$locale = get_wpbot_locale();
			$label  = is_array( $label ) && isset( $label[ $locale ] ) ? $label[ $locale ] : $label;
			$label  = is_array( $label ) ? implode( '', $label ) : (string) $label;
			return '<br><br><p><em>' . esc_html( $label ) . '</em><p>' . implode( '</br>', $links );
		}
	}

	Qcld_WPBot_DeepSeek::instance();
}
