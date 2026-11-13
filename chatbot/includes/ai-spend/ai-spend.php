<?php
/**
 * AI spend helpers for the host's own AI calls (add-ons, integrations, legacy handlers).
 *
 * Every direct AI call in the host goes through one of these, so WPBot Core can check the
 * owner's spending limits before the call and record what it cost after it
 * (AI Settings > Spending and limits). When WPBot Core is not loaded, the call simply runs.
 *
 * The same file ships in WPBot Free (chatbot) and WPBot Pro (botmaster); the functions are
 * defined once.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_spend_allow' ) ) {
	/**
	 * Whether an AI call may run now.
	 *
	 * @param string $module   Module id (chatbot, knowledge_base, voice, ai_studio, automator…).
	 * @param string $feature  Feature id.
	 * @param float  $estimate Expected cost in USD (0 when unknown).
	 * @return true|WP_Error WP_Error code qcld_wpbot_core_budget_reached when a limit stops it.
	 */
	function qcld_wpbot_spend_allow( $module, $feature, $estimate = 0.0 ) {
		return function_exists( 'qcld_wpbot_core_spend_allow' ) ? qcld_wpbot_core_spend_allow( $module, $feature, $estimate ) : true;
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_post' ) ) {
	/**
	 * Wp_remote_post() to an AI provider, checked against the limits and recorded.
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @param string $url     URL.
	 * @param array  $args    wp_remote_post() arguments.
	 * @param array  $meta    provider, model, task, job_id, estimate, usage (array or callable).
	 * @return array|WP_Error The response, or the limit error.
	 */
	function qcld_wpbot_spend_post( $module, $feature, $url, array $args, array $meta = array() ) {
		return qcld_wpbot_spend_wrap(
			$module,
			$feature,
			static function () use ( $url, $args ) {
				return wp_remote_post( $url, $args );
			},
			qcld_wpbot_spend_meta_from_body( $meta, isset( $args['body'] ) ? $args['body'] : '' )
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_wrap' ) ) {
	/**
	 * Run any AI call through the meter: allowed first, then its usage recorded.
	 *
	 * The callable returns a wp_remote_* response (its JSON body is read), a JSON string (raw cURL
	 * callers; set $meta['json'] = true), a WP_Error, or anything else together with $meta['usage'].
	 *
	 * @param string   $module  Module id.
	 * @param string   $feature Feature id.
	 * @param callable $call    The call.
	 * @param array    $meta    provider, model, task, job_id, estimate, usage, json.
	 * @return mixed|WP_Error
	 */
	function qcld_wpbot_spend_wrap( $module, $feature, $call, array $meta = array() ) {
		if ( ! function_exists( 'qcld_wpbot_core_spend_wrap' ) ) {
			return call_user_func( $call );
		}
		if ( ! empty( $meta['json'] ) && ! isset( $meta['usage'] ) ) {
			$meta['usage'] = static function ( $result ) {
				// Answers without a usage block (errors) are not recorded.
				return is_string( $result ) ? \QuantumCloud\WPBotCore\Spend\Usage_Reader::from_body( json_decode( $result, true ) ) : null;
			};
		}
		unset( $meta['json'] );
		return qcld_wpbot_core_spend_wrap( $module, $feature, $call, $meta );
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_record' ) ) {
	/**
	 * Record a call that was already made (for example a stream read by cURL).
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @param array  $usage   tokens_in, tokens_out, images, characters, audio_seconds, kind.
	 * @param array  $meta    provider, model, task, job_id.
	 * @return void
	 */
	function qcld_wpbot_spend_record( $module, $feature, array $usage, array $meta = array() ) {
		if ( function_exists( 'qcld_wpbot_core_spend_record' ) ) {
			qcld_wpbot_core_spend_record( $module, $feature, $usage, $meta );
		}
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_record_text' ) ) {
	/**
	 * Record a text call whose answer did not report tokens (streams): about four characters per
	 * token for what was sent and what came back.
	 *
	 * @param string $module   Module id.
	 * @param string $feature  Feature id.
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param mixed  $sent     What was sent (string or array; arrays are counted as JSON).
	 * @param string $answer   The answer text.
	 * @return void
	 */
	function qcld_wpbot_spend_record_text( $module, $feature, $provider, $model, $sent, $answer ) {
		$sent = is_string( $sent ) ? $sent : (string) wp_json_encode( $sent );
		qcld_wpbot_spend_record(
			$module,
			$feature,
			array(
				'tokens_in'  => (int) ceil( strlen( $sent ) / 4 ),
				'tokens_out' => (int) ceil( strlen( (string) $answer ) / 4 ),
			),
			array(
				'provider' => $provider,
				'model'    => $model,
			)
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_meta_from_body' ) ) {
	/**
	 * Fill meta['model'] from a JSON request body when the caller did not give it.
	 *
	 * @param array $meta Meta.
	 * @param mixed $body Request body (JSON string or array).
	 * @return array
	 */
	function qcld_wpbot_spend_meta_from_body( array $meta, $body ) {
		if ( empty( $meta['model'] ) ) {
			$data = is_string( $body ) ? json_decode( $body, true ) : $body;
			if ( is_array( $data ) && isset( $data['model'] ) && is_string( $data['model'] ) ) {
				$meta['model'] = $data['model'];
			}
		}
		return $meta;
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_is_limit' ) ) {
	/**
	 * Whether an error is a spending-limit refusal.
	 *
	 * @param mixed $error Value.
	 * @return bool
	 */
	function qcld_wpbot_spend_is_limit( $error ) {
		return is_wp_error( $error ) && 'qcld_wpbot_core_budget_reached' === $error->get_error_code();
	}
}

if ( ! function_exists( 'qcld_wpbot_spend_visitor_reply' ) ) {
	/**
	 * What a visitor sees when the chatbot's AI is paused by a limit: never an error or the word
	 * budget. The chatbot module answers through filter qcld_wpbot_chat/spend_fallback_reply
	 * (saved answers, FAQ, site search, then the no-answer flow).
	 *
	 * @param string $question The visitor's message.
	 * @return string
	 */
	function qcld_wpbot_spend_visitor_reply( $question = '' ) {
		$default = (string) get_option( 'qlcd_wp_chatbot_no_result', '' );
		if ( '' === trim( wp_strip_all_tags( $default ) ) ) {
			$default = __( 'Sorry, I could not find an answer to that. Please leave your email and we will get back to you.', 'chatbot' );
		}
		/**
		 * The chatbot's answer while AI is paused by a spending limit.
		 *
		 * @param string $reply    Default: the "no result" message.
		 * @param string $question Visitor's message.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/spend_fallback_reply', $default, (string) $question ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- The chatbot module's hook name.
	}
}
