<?php
/**
 * The widget's old admin-ajax actions, answered by the new engine.
 *
 * Each shim runs at priority 1 on the action, before the legacy handler, and
 * either answers (and exits) or returns so the legacy handler runs. Legacy
 * handlers stay for what the engine does not cover yet (STR Pro categories,
 * Extended Search, provider extras such as OpenAI Assistants); they go in M10.
 *
 * Security for these public actions: requests from other sites are refused
 * (Origin/Referer) and the rate limits apply. Nonces are not required, so pages
 * from a full-page cache keep working; the new widget (M5) uses REST with a
 * visitor token instead.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Legacy;

use QuantumCloud\Chatbot\Conversation\Steps\Ai_Step;
use QuantumCloud\Chatbot\Conversation\Steps\DialogFlow_Step;
use QuantumCloud\Chatbot\Conversation\Steps\Fallback_Step;
use QuantumCloud\Chatbot\Conversation\Steps\Search_Step;
use QuantumCloud\Chatbot\Conversation\Steps\Str_Step;
use QuantumCloud\Chatbot\Leads\Leads;
use QuantumCloud\Chatbot\RateLimit\Limiter;
use QuantumCloud\Chatbot\Sessions\Session_Store;
use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy AJAX shims.
 */
final class Ajax_Shims {

	/**
	 * AI actions of the legacy widget (JSON answers).
	 */
	const AI_ACTIONS = array( 'openai_response', 'qcld_openai_response', 'gemini_response', 'qcld_gemini_response', 'claude_response', 'qcld_grok_response', 'openrouter_response', 'mistral_response', 'ollama_response', 'deepseek_response' );

	/**
	 * Streaming AI actions (server-sent events).
	 */
	const STREAM_ACTIONS = array( 'qcld_stream_openai', 'qcld_stream_gemini', 'qcld_stream_grokai', 'qcld_stream_claude' );

	/**
	 * Hook the shims.
	 *
	 * @return void
	 */
	public static function init() {
		$map = array(
			'wpbo_search_response'              => 'str',
			'wpbo_search_site'                  => 'search',
			'wpbo_failed_response'              => 'failed',
			'qcld_wb_chatbot_session_count'     => 'session_count',
			'qcld_wb_chatbot_conversation_save' => 'conversation_save',
			'wpbot_save_feedback'               => 'feedback',
			'wpbot_save_report'                 => 'report',
			'qcld_wp_df_api_cx'                 => 'dialogflow_cx',
			'qcld_wp_df_api_call'               => 'dialogflow_guard',
		);
		foreach ( self::AI_ACTIONS as $action ) {
			$map[ $action ] = 'ai_guard';
		}
		foreach ( self::STREAM_ACTIONS as $action ) {
			$map[ $action ] = 'ai_stream_guard';
		}
		foreach ( $map as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ), 1 );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ), 1 );
		}
	}

	/**
	 * Simple Text Responses (core path). STR Pro (categories, stop words) keeps its handler.
	 *
	 * @return void
	 */
	public static function str() {
		self::guard();
		/**
		 * Whether the legacy wpbo_search_response handler answers (default: when STR Pro is loaded
		 * and no module handles its categories yet).
		 *
		 * @param bool $legacy Use the legacy handler.
		 */
		if ( apply_filters( 'qcld_wpbot_chat/str_legacy_handler', class_exists( 'Qcld_str_pro' ) ) ) {
			return;
		}
		$step   = new Str_Step();
		$result = $step->match( self::post( 'keyword' ), self::post( 'language' ), self::post( 'strid' ) );
		if ( 'success' !== $result['status'] ) {
			$result['message'] = 'no result found';
		}
		self::json( $result );
	}

	/**
	 * Site search (Extended Search keeps its handler).
	 *
	 * @return void
	 */
	public static function search() {
		self::guard();
		// The ChatBot FAQ answers first, also when an add-on runs the page search.
		$faq = Search_Step::faq( self::post( 'keyword' ), self::post( 'language' ) );
		if ( $faq ) {
			self::json(
				array(
					'status' => $faq['status'],
					'html'   => $faq['html'],
				)
			);
		}
		/**
		 * Whether the legacy wpbo_search_site handler answers (default: Extended Search is on and no
		 * module handles it yet).
		 *
		 * @param bool $legacy Use the legacy handler.
		 */
		if ( apply_filters( 'qcld_wpbot_chat/search_legacy_handler', '1' === (string) Options::get( 'active_advance_query', '' ) && ( function_exists( 'qcpd_wppt_search_fnc' ) || class_exists( 'qc_wpsaas' ) ) ) ) {
			return;
		}
		$step   = new Search_Step();
		$result = $step->search( self::post( 'keyword' ), 1, self::post( 'language' ) );
		self::json(
			array(
				'status' => $result['status'],
				'html'   => $result['html'],
			)
		);
	}

	/**
	 * Unanswered question log.
	 *
	 * @return void
	 */
	public static function failed() {
		self::guard();
		if ( Limiter::hit( 'feedback' ) ) {
			$question = self::post( 'keyword' );
			Fallback_Step::log( $question );
			// Train lists it too (chatbot features round 6): before, only the old "Questions Not
			// Answered" table got the widget's unanswered questions, so Train stayed empty.
			/** This action is documented in src/Conversation/Steps/Ai_Step.php */
			do_action( 'qcld_wpbot_chat/knowledge_gap', $question, 'no_answer', null );
		}
		wp_die( '', '', array( 'response' => 200 ) );
	}

	/**
	 * Visit counter: counts a visitor once per 30 minutes instead of on every page load (B4).
	 *
	 * @return void
	 */
	public static function session_count() {
		$key = 'qcld_wpbot_chat_visit_' . md5( Limiter::ip() . '|' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ) );
		if ( get_transient( $key ) ) {
			self::json( array() );
		}
		set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
	}

	/**
	 * Transcript save: keeps the legacy handler (Sessions screens, emails) and also stores
	 * the new messages as structured rows.
	 *
	 * @return void
	 */
	public static function conversation_save() {
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, 'qcsecretbotnonceval123qc' ) ) {
			return;
			// The legacy handler answers with its own nonce error.
		}
		$session = self::post( 'session_id' );
		$html    = isset( $_POST['conversation'] ) && is_string( $_POST['conversation'] ) ? wp_unslash( $_POST['conversation'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed to plain text by parse_legacy().
		if ( strlen( $html ) > 2 * MB_IN_BYTES ) {
			return;
		}
		if ( '' !== $session && '' !== $html ) {
			Session_Store::sync_legacy( $session, $html );
		}
	}

	/**
	 * Feedback on a bot message (legacy action had no checks).
	 *
	 * @return void
	 */
	public static function feedback() {
		self::guard();
		if ( ! Limiter::hit( 'feedback' ) ) {
			wp_send_json_error( array( 'message' => 'Too many requests.' ), 429 );
		}
		$ok = Leads::feedback(
			array(
				'message'         => self::post( 'message', true ),
				'feedback'        => self::post( 'feedback' ),
				'conversation_id' => self::post( 'conversation_id' ),
				'meta_info'       => self::post( 'meta_info', true ),
			)
		);
		if ( $ok ) {
			wp_send_json_success( array( 'message' => 'Feedback saved.' ) );
		}
		wp_send_json_error( array( 'message' => 'Failed to save feedback.' ) );
	}

	/**
	 * Report a bot message (legacy action emailed the admin on every request).
	 *
	 * @return void
	 */
	public static function report() {
		self::guard();
		if ( ! Limiter::hit( 'report' ) ) {
			wp_send_json_error( null, 429 );
		}
		Leads::report(
			array(
				'message'     => self::post( 'message', true ),
				'report_text' => self::post( 'report_text', true ),
				'email'       => self::post( 'email' ),
			),
			Limiter::ip()
		);
		wp_send_json_success();
	}

	/**
	 * DialogFlow CX: the agent is chosen on the server; the browser's "name" is ignored.
	 *
	 * @return void
	 */
	public static function dialogflow_cx() {
		self::dialogflow_guard();
		$config = DialogFlow_Step::config( self::post( 'language' ) );
		$token  = DialogFlow_Step::access_token( $config['key'] );
		$agent  = is_wp_error( $token ) ? $token : DialogFlow_Step::cx_agent( $config, $token );
		if ( is_wp_error( $agent ) ) {
			self::json( array( 'error' => $agent->get_error_message() ) );
		}
		$_POST['name'] = $agent;
		// The legacy handler builds the URL from it.
	}

	/**
	 * DialogFlow: other sites refused, message limit applied.
	 *
	 * @return void
	 */
	public static function dialogflow_guard() {
		self::guard();
		if ( ! Limiter::hit( 'message' ) ) {
			self::json( array( 'error' => 'Too many requests.' ) );
		}
	}

	/**
	 * AI answer actions: other sites refused; the guest daily AI limit applies.
	 *
	 * @return void
	 */
	public static function ai_guard() {
		self::guard();
		if ( ! Limiter::hit( 'ai' ) ) {
			self::json(
				array(
					'status'  => 'success',
					'message' => Ai_Step::limit_text(),
					'limited' => true,
				)
			);
		}
	}

	/**
	 * Streaming AI actions: same checks, answered as an event stream.
	 *
	 * @return void
	 */
	public static function ai_stream_guard() {
		self::guard();
		if ( Limiter::hit( 'ai' ) ) {
			return;
		}
		\QuantumCloud\Chatbot\Rest\Sse::start();
		\QuantumCloud\Chatbot\Rest\Sse::json( array( 'choices' => array( array( 'delta' => array( 'content' => Ai_Step::limit_text() ) ) ) ) );
		\QuantumCloud\Chatbot\Rest\Sse::send( '[DONE]' );
		exit;
	}

	/**
	 * Refuse requests the browser says come from another site (CSRF). Nonces are
	 * not required: pages served from a full-page cache carry expired ones.
	 *
	 * @return void Exits with HTTP 403 when refused.
	 */
	public static function guard() {
		if ( ! self::same_site() ) {
			wp_send_json( array( 'status' => 'fail' ), 403 );
		}
	}

	/**
	 * True unless the browser says the request came from another site.
	 *
	 * @return bool
	 */
	public static function same_site() {
		$hosts = array(
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( site_url(), PHP_URL_HOST ),
		);
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$hosts[] = wp_parse_url( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ), PHP_URL_HOST );
			// The host this request was sent to is the same site.
		}
		/**
		 * Hosts the chat's admin-ajax requests may come from (other domains of the same site).
		 *
		 * @param string[] $hosts Host names.
		 */
		$hosts = array_filter( (array) apply_filters( 'qcld_wpbot_chat/allowed_hosts', $hosts ) );
		foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			if ( 'null' === $value ) {
				return false;
			}
			$host = wp_parse_url( $value, PHP_URL_HOST );
			return is_string( $host ) && in_array( strtolower( $host ), array_map( 'strtolower', $hosts ), true );
		}
		return true;
	}

	/**
	 * A POST field as text.
	 *
	 * @param string $name      Field.
	 * @param bool   $multiline Keep line breaks.
	 * @return string
	 */
	private static function post( $name, $multiline = false ) {
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		$value = wp_unslash( (string) $_POST[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Send JSON the way the legacy handlers did (plain echo as text/html, no wrapper) and exit.
	 *
	 * @param array $data Data.
	 * @return void
	 */
	private static function json( array $data ) {
		// No JSON content type: the legacy handlers sent text/html, and Free's widget runs
		// JSON.parse() on the reply (jQuery would already have parsed a JSON response).
		echo wp_json_encode( $data );
		wp_die( '', '', array( 'response' => 200 ) );
	}
}
