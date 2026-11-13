<?php
/**
 * Asks the chosen AI provider for a chat reply, with the provider's common settings.
 *
 * One code path for every provider (the legacy widget had one per provider):
 * system prompt, conversation history, temperature, max tokens, prepend and
 * append text all come from Provider_Settings; the HTTP work is WPBot Core's
 * AI client (Provider_Client), which also classifies errors.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chat reply service.
 */
final class Ai_Client {

	/**
	 * Most earlier messages sent with a question (today Gemini and Claude send 10).
	 */
	const HISTORY_LIMIT = 10;

	/**
	 * Ask for a reply.
	 *
	 * @param string        $question Visitor's message.
	 * @param array         $args {
	 *     Optional.
	 *     @type string   $provider Provider id; default the one that answers today.
	 *     @type array[]  $history  Earlier messages: role (user|assistant) and content.
	 *     @type string   $context  Extra system text (knowledge base, page context, form instructions).
	 *     @type bool     $json     Ask for one JSON object (AI forms).
	 *     @type string   $feature  Usage feature id (default 'chat'); WPBot Core's usage log groups costs by it.
	 *     @type array[]  $tools    Tools the model may call (WPBot Core AI\Tool_Calls format); used only when the
	 *                              model supports native tool calling.
	 *     @type callable $run_tool Called with ( $name, $arguments ) for each tool call; returns the result text.
	 *     @type bool     $raw      An admin task (report, summary): the system text is only `context`, without the
	 *                              chatbot's prompt, extra instructions, answer scope or longest-answer setting.
	 *     @type int      $max_tokens With `raw`: the longest answer (default: the provider's own).
	 * }
	 * @param callable|null $on_text  Called with each piece of text when streaming; null for one reply.
	 * @return array|WP_Error { text, provider, model, tokens_in, tokens_out, truncated, cost, price_known }
	 */
	public static function reply( $question, array $args = array(), $on_text = null ) {
		$provider = isset( $args['provider'] ) ? (string) $args['provider'] : Provider_Settings::answering_provider();
		$settings = '' !== $provider ? Provider_Settings::get( $provider ) : null;
		if ( null === $settings ) {
			return new WP_Error( 'qcld_wpbot_chat_no_ai', __( 'No AI provider is turned on.', 'chatbot' ), array( 'status' => 409 ) );
		}
		if ( 'ollama' === $provider && '' === (string) $settings['url'] ) {
			// Without an address the request went to an empty URL ("Protocol "" not supported").
			return new WP_Error(
				'qcld_wpbot_chat_no_ollama_url',
				__( 'Ollama has no server address.', 'chatbot' ),
				array(
					'status' => 409,
					'detail' => __( 'Ollama has no server address. Add it (for example http://localhost:11434) in AI Settings > Providers > Ollama.', 'chatbot' ),
				)
			);
		}
		if ( ! class_exists( '\QuantumCloud\WPBotCore\AI\Provider_Client' ) ) {
			return new WP_Error( 'qcld_wpbot_chat_no_core', __( 'WPBot Core is not loaded.', 'chatbot' ), array( 'status' => 500 ) );
		}

		$feature = isset( $args['feature'] ) ? (string) $args['feature'] : 'chat';
		$refused = self::spend_refusal( $feature );
		if ( null !== $refused ) {
			return $refused;
		}
		$routing = '\QuantumCloud\WPBotCore\AI\Model_Routing';
		if ( class_exists( $routing ) && method_exists( $routing, 'route' ) ) {
			// Pro may send short questions to a cheaper model (WPBot Core filter qcld_wpbot_core/route_model).
			$settings['model'] = (string) $routing::route(
				$provider,
				(string) $settings['model'],
				array(
					'module'   => 'chatbot',
					'feature'  => $feature,
					'question' => (string) $question,
				)
			);
		}
		$request   = self::request( $settings, (string) $question, $args );
		$client    = '\QuantumCloud\WPBotCore\AI\Provider_Client';
		$cache_key = self::cache_key( $provider, $settings, $feature, $request, (string) $question, $args );
		if ( '' !== $cache_key ) {
			$cached = Answer_Cache::get( $cache_key );
			if ( null !== $cached ) {
				// Saved without the provider's prepend and append text; added now, like a fresh answer.
				$cached = self::wrap( $settings, $cached );
				if ( is_callable( $on_text ) ) {
					call_user_func( $on_text, $cached );
				}
				return array(
					'text'        => $cached,
					'provider'    => $provider,
					'model'       => $settings['model'],
					'tokens_in'   => 0,
					'tokens_out'  => 0,
					'truncated'   => false,
					'cost'        => 0.0,
					'price_known' => true,
					'cached'      => true,
				);
			}
		}//end if

		if ( self::tools_ready( $provider, (string) $settings['model'], $args ) ) {
			$result = self::reply_with_tools( $provider, $settings, $request, $args, $on_text );
			if ( ! is_wp_error( $result ) ) {
				if ( '' !== $cache_key && empty( $result['tool_runs'] ) && empty( $result['truncated'] ) && '' !== (string) $result['text'] ) {
					// No tool was called, so the answer depends on the question and context only.
					Answer_Cache::put( $cache_key, (string) $question, (string) $result['text'], $provider, (string) $settings['model'], $feature, isset( $result['cost'] ) ? (float) $result['cost'] : 0.0, isset( $args['visitor_name'] ) ? (string) $args['visitor_name'] : '' );
				}
				return $result;
			}
			$other = self::fallback_provider( $provider, $result, $feature );
			if ( '' === $other ) {
				return $result;
			}
			/** This action is documented in src/Conversation/Steps/Ai_Step.php */
			do_action( 'qcld_wpbot_chat/ai_failed', $result, null );
			$args['provider'] = $other;
			// The fallback answers on its own (with the tools when it supports them); no second failover.
			add_filter( 'qcld_wpbot_core/fallback_provider', '__return_empty_string', 999 );
			$retry = self::reply( $question, $args, $on_text );
			remove_filter( 'qcld_wpbot_core/fallback_provider', '__return_empty_string', 999 );
			return is_wp_error( $retry ) ? $result : $retry;
		}//end if
		$stream = is_callable( $on_text ) && $settings['stream'];

		$response = self::call( $client, $provider, $settings, $request, $stream ? $on_text : null );
		if ( is_wp_error( $response ) ) {
			// Failover (Pro, WPBot Core filter qcld_wpbot_core/fallback_provider): one try with another provider.
			$other    = self::fallback_provider( $provider, $response, $feature );
			$fallback = '' !== $other ? Provider_Settings::get( $other ) : null;
			if ( null !== $fallback ) {
				$failed   = $response;
				$retry    = self::call( $client, $other, $fallback, self::request( $fallback, (string) $question, $args ), is_callable( $on_text ) && $fallback['stream'] ? $on_text : null );
				$response = is_wp_error( $retry ) ? $failed : $retry;
				if ( ! is_wp_error( $retry ) ) {
					/** This action is documented in src/Conversation/Steps/Ai_Step.php */
					do_action( 'qcld_wpbot_chat/ai_failed', $failed, null );
					$provider = $other;
					$settings = $fallback;
					$stream   = is_callable( $on_text ) && $fallback['stream'];
				}
			}
		}//end if
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$text = self::wrap( $settings, (string) $response->text );
		if ( $stream && '' !== $settings['append'] ) {
			call_user_func( $on_text, "\n\n" . $settings['append'] );
		}
		$usage = self::record_usage( $provider, (string) $settings['model'], $response, isset( $args['feature'] ) ? (string) $args['feature'] : 'chat' );
		if ( '' !== $cache_key && ! $response->truncated && ( ! isset( $failed ) || ! is_wp_error( $failed ) ) ) {
			// Not an answer from the backup provider: the key was made for the primary.
			Answer_Cache::put( $cache_key, (string) $question, (string) $response->text, $provider, (string) $settings['model'], $feature, (float) $usage['cost'], isset( $args['visitor_name'] ) ? (string) $args['visitor_name'] : '' );
		}
		return array(
			'text'        => $text,
			'provider'    => $provider,
			'model'       => $settings['model'],
			'tokens_in'   => (int) $response->tokens_in,
			'tokens_out'  => (int) $response->tokens_out,
			'truncated'   => (bool) $response->truncated,
			'cost'        => $usage['cost'],
			'price_known' => $usage['price_known'],
		);
	}

	/**
	 * The provider to try after a failure ('' = none): WPBot Core Model_Routing::fallback(), only a
	 * different provider that has a key.
	 *
	 * @param string   $provider Provider that failed.
	 * @param WP_Error $error    Its error.
	 * @param string   $feature  Feature id.
	 * @return string
	 */
	private static function fallback_provider( $provider, $error, $feature ) {
		$routing = '\QuantumCloud\WPBotCore\AI\Model_Routing';
		if ( ! class_exists( $routing ) || ! method_exists( $routing, 'fallback' ) ) {
			return '';
		}
		$other = (string) $routing::fallback(
			$provider,
			$error,
			array(
				'module'  => 'chatbot',
				'feature' => $feature,
			)
		);
		// Ollama needs a server address; an Ollama with none is not a backup (it sent requests to an empty URL).
		return '' !== $other && $other !== $provider && Provider_Settings::is_set_up( $other ) ? $other : '';
	}

	/**
	 * One provider call: streamed (with the prepend text first) or one reply.
	 *
	 * @param string        $client   Provider client class.
	 * @param string        $provider Provider id.
	 * @param array         $settings Provider settings.
	 * @param object        $request  Request.
	 * @param callable|null $on_text  Stream callback (null = one reply).
	 * @return object|WP_Error Response.
	 */
	private static function call( $client, $provider, array $settings, $request, $on_text ) {
		if ( is_callable( $on_text ) ) {
			if ( '' !== $settings['prepend'] ) {
				call_user_func( $on_text, $settings['prepend'] . "\n\n" );
			}
			return $client::stream( $provider, $settings['model'], $settings['key'], $request, $on_text, $settings['url'] );
		}
		return $client::send( $provider, $settings['model'], $settings['key'], $request, $settings['url'] );
	}

	/**
	 * Answer cache key, or '' when this answer must not be cached (Answer_Cache::key(): off unless the
	 * Answer cache setting is on; never for logged-in visitors, personal context, follow-up questions
	 * or questions with personal data).
	 *
	 * @param string $provider Provider id.
	 * @param array  $settings Provider settings.
	 * @param string $feature  Feature id.
	 * @param object $request  Request.
	 * @param string $question Question.
	 * @param array  $args     reply() args.
	 * @return string
	 */
	private static function cache_key( $provider, array $settings, $feature, $request, $question, array $args ) {
		return Answer_Cache::key( $provider, (string) $settings['model'], $feature, $request, (string) $question, $args );
	}

	/**
	 * Whether this reply can use native tool calling.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param array  $args     reply() args.
	 * @return bool
	 */
	public static function tools_ready( $provider, $model, array $args ) {
		$providers = '\QuantumCloud\WPBotCore\AI\Providers';
		return ! empty( $args['tools'] ) && isset( $args['run_tool'] ) && is_callable( $args['run_tool'] )
			&& property_exists( '\QuantumCloud\WPBotCore\AI\Request', 'tools' )
			&& method_exists( $providers, 'supports_tools' ) && $providers::supports_tools( $provider, $model );
	}

	/**
	 * Reply with tools: up to three rounds of send(); tool calls run through $args['run_tool'].
	 * The final text is passed to $on_text in one piece (tool rounds cannot stream).
	 *
	 * @param string        $provider Provider id.
	 * @param array         $settings Provider settings.
	 * @param object        $request  WPBot Core AI\Request.
	 * @param array         $args     reply() args.
	 * @param callable|null $on_text  Stream callback.
	 * @return array|WP_Error
	 */
	private static function reply_with_tools( $provider, array $settings, $request, array $args, $on_text ) {
		$client         = '\QuantumCloud\WPBotCore\AI\Provider_Client';
		$request->tools = (array) $args['tools'];
		$feature        = isset( $args['feature'] ) ? (string) $args['feature'] : 'chat';
		$totals         = array(
			'tokens_in'   => 0,
			'tokens_out'  => 0,
			'cost'        => 0.0,
			'price_known' => true,
		);
		$runs           = array();
		$response       = null;
		for ( $round = 0; $round < 3; $round++ ) {
			$response = $client::send( $provider, $settings['model'], $settings['key'], $request, $settings['url'] );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$usage                 = self::record_usage( $provider, (string) $settings['model'], $response, $feature );
			$totals['tokens_in']  += (int) $response->tokens_in;
			$totals['tokens_out'] += (int) $response->tokens_out;
			$totals['cost']       += $usage['cost'];
			$totals['price_known'] = $totals['price_known'] && $usage['price_known'];
			if ( empty( $response->tool_calls ) || 2 === $round ) {
				break;
			}
			$request->messages[] = array(
				'role'       => 'assistant',
				'content'    => (string) $response->text,
				'tool_calls' => $response->tool_calls,
			);
			foreach ( $response->tool_calls as $call ) {
				$result              = (string) call_user_func( $args['run_tool'], (string) $call['name'], (array) $call['arguments'] );
				$runs[]              = array(
					'name'   => (string) $call['name'],
					'result' => $result,
				);
				$request->messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => (string) $call['id'],
					'name'         => (string) $call['name'],
					'content'      => $result,
				);
			}
		}//end for
		$text = self::wrap( $settings, (string) $response->text );
		if ( is_callable( $on_text ) && '' !== $text ) {
			call_user_func( $on_text, $text );
		}
		return array(
			'text'        => $text,
			'provider'    => $provider,
			'model'       => $settings['model'],
			'tokens_in'   => $totals['tokens_in'],
			'tokens_out'  => $totals['tokens_out'],
			'truncated'   => (bool) $response->truncated,
			'cost'        => $totals['cost'],
			'price_known' => $totals['price_known'],
			'tool_runs'   => $runs,
		);
	}

	/**
	 * Record a reply's cost in WPBot Core's usage log (module 'chatbot'), once per reply.
	 *
	 * This is the chatbot's only place that records AI usage: if the chatbot later calls
	 * WPBot Core's AI_Service (which records usage itself), remove this call in the same change.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param object $response WPBot Core AI\Response.
	 * @param string $feature  Feature id.
	 * @return array { cost (float, USD estimate), price_known (bool) }
	 */
	public static function record_usage( $provider, $model, $response, $feature = 'chat' ) {
		$usage     = '\QuantumCloud\WPBotCore\AI\Usage';
		$providers = '\QuantumCloud\WPBotCore\AI\Providers';
		if ( ! class_exists( $usage ) ) {
			return array(
				'cost'        => 0.0,
				'price_known' => false,
			);
		}
		$cost        = (float) $usage::estimate( $provider, $model, (int) $response->tokens_in, (int) $response->tokens_out );
		$price_known = method_exists( $providers, 'price_known' ) ? (bool) $providers::price_known( $provider, $model ) : $cost > 0;
		/**
		 * Whether the chatbot records this reply in WPBot Core's usage log (default yes).
		 *
		 * @param bool   $record   Record.
		 * @param string $provider Provider id.
		 * @param string $feature  Feature id.
		 */
		if ( apply_filters( 'qcld_wpbot_chat/record_usage', true, $provider, $feature ) ) {
			if ( '' === (string) $response->provider ) {
				$response->provider = $provider;
			}
			if ( '' === (string) $response->model ) {
				$response->model = $model;
			}
			$response->cost = $cost;
			$usage::record( 'chatbot', '' !== $feature ? $feature : 'chat', $response );
		}
		return array(
			'cost'        => $cost,
			'price_known' => $price_known,
		);
	}

	/**
	 * Build the WPBot Core request.
	 *
	 * @param array  $settings Provider settings.
	 * @param string $question Visitor's message.
	 * @param array  $args     See reply().
	 * @return \QuantumCloud\WPBotCore\AI\Request
	 */
	public static function request( array $settings, $question, array $args ) {
		$request_class = '\QuantumCloud\WPBotCore\AI\Request';
		// The prompt with {site_name} / {site_url} filled, then the extra instructions (round R2).
		$raw = ! empty( $args['raw'] );
		if ( $raw ) {
			// An admin task (a report, a summary): only the caller's instructions, without the
			// chatbot's personality, answer scope or answer length.
			$system = trim( isset( $args['context'] ) ? (string) $args['context'] : '' );
		} else {
			$prompt = Ai_Settings::fill( Ai_Settings::prompt( (string) $settings['system_prompt'] ) ) . ( ! empty( $settings['instructions'] ) ? "\n\n" . Ai_Settings::fill( (string) $settings['instructions'] ) : '' );
			$system = trim( $prompt . ( ! empty( $args['context'] ) ? "\n\n" . (string) $args['context'] : '' ) );
			// What the AI may answer (one setting for every provider).
			$system = Answer_Scope::apply( $system );
		}
		$options = array( 'json' => ! empty( $args['json'] ) );
		if ( null !== $settings['temperature'] ) {
			$options['temperature'] = $settings['temperature'];
		}
		if ( $raw ) {
			if ( ! empty( $args['max_tokens'] ) ) {
				$options['max_tokens'] = max( 1, (int) $args['max_tokens'] );
			}
		} elseif ( null !== $settings['max_tokens'] ) {
			$options['max_tokens'] = $settings['max_tokens'];
		}
		$request           = $request_class::make( $system, $question, $options );
		$request->messages = array_merge( self::history( isset( $args['history'] ) ? (array) $args['history'] : array() ), $request->messages );
		return $request;
	}

	/**
	 * Clean history: valid roles and text only, the last HISTORY_LIMIT messages.
	 *
	 * @param array $history Messages.
	 * @return array[]
	 */
	public static function history( array $history ) {
		$clean = array();
		foreach ( $history as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['role'], $message['content'] ) || ! in_array( $message['role'], array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$content = trim( wp_strip_all_tags( (string) $message['content'] ) );
			if ( '' !== $content ) {
				$clean[] = array(
					'role'    => $message['role'],
					'content' => $content,
				);
			}
		}
		return array_slice( $clean, -self::HISTORY_LIMIT );
	}

	/**
	 * Add the provider's prepend and append text to a whole reply.
	 *
	 * @param array  $settings Provider settings.
	 * @param string $text     Reply.
	 * @return string
	 */
	public static function wrap( array $settings, $text ) {
		$parts = array_filter( array( $settings['prepend'], $text, $settings['append'] ), 'strlen' );
		return implode( "\n\n", $parts );
	}

	/**
	 * WPBot Core's spending limits (AI Settings > Spending and limits): the refusal, or null.
	 * Older WPBot Core copies only know the chatbot's monthly budget.
	 *
	 * @param string $feature Feature id (chat, chat_whatsapp…).
	 * @return WP_Error|null
	 */
	private static function spend_refusal( $feature ) {
		if ( function_exists( 'qcld_wpbot_core_spend_allow' ) ) {
			$allowed = qcld_wpbot_core_spend_allow( 'chatbot', '' !== $feature ? $feature : 'chat' );
			return is_wp_error( $allowed ) ? $allowed : null;
		}
		$usage = '\QuantumCloud\WPBotCore\AI\Usage';
		if ( class_exists( $usage ) && method_exists( $usage, 'budget_reached' ) && $usage::budget_reached( 'chatbot' ) ) {
			return new WP_Error(
				'qcld_wpbot_core_budget_reached',
				__( 'This month\'s AI budget for the chatbot is used up.', 'chatbot' ),
				array(
					'status' => 429,
					'cause'  => 'budget_reached',
				)
			);
		}
		return null;
	}
}
