<?php
/**
 * CRM adapter over WPBot's Insights AI client.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves provider, key and model (CRM settings first, then WPBot's own AI
 * options) and sends prompts through QuantumCloud\Insights\AI\Provider_Client.
 * No HTTP code for LLMs lives here.
 */
final class Ai {

	const CLIENT_CLASS = 'QuantumCloud\\Insights\\AI\\Provider_Client';

	/**
	 * Provider labels for messages.
	 *
	 * @var array<string,string>
	 */
	const LABELS = array(
		'openai'     => 'OpenAI',
		'gemini'     => 'Gemini',
		'claude'     => 'Claude',
		'openrouter' => 'OpenRouter',
		'grok'       => 'Grok',
	);

	/**
	 * Aliases some screens or older settings use for the same providers.
	 *
	 * @var array<string,string>
	 */
	const ALIASES = array(
		'anthropic' => 'claude',
		'google'    => 'gemini',
		'xai'       => 'grok',
		'x-ai'      => 'grok',
		'chatgpt'   => 'openai',
	);

	/**
	 * True when a provider + key + model are configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$config = self::resolve();
		return '' !== $config['provider'] && '' !== $config['key'] && '' !== $config['model'];
	}

	/**
	 * Plain-text completion.
	 *
	 * @param string $system System prompt.
	 * @param string $user   User prompt.
	 * @param array  $opts   max_tokens, temperature, timeout, provider, key, model.
	 * @return string|\WP_Error
	 */
	public static function text( $system, $user, array $opts = array() ) {
		$text = self::complete( $system, $user, $opts, 'text' );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return trim( self::strip_think( $text ) );
	}

	/**
	 * JSON completion, decoded to an array.
	 *
	 * @param string $system System prompt (should ask for JSON).
	 * @param string $user   User prompt.
	 * @param array  $opts   See text().
	 * @return array|\WP_Error
	 */
	public static function json( $system, $user, array $opts = array() ) {
		$text = self::complete( $system, $user, $opts, 'json' );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$data = self::decode_json( $text );
		if ( null === $data ) {
			return new \WP_Error( 'wpbot_crm_ai_failed', __( 'The AI reply could not be read. Please try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return $data;
	}

	/**
	 * Test unsaved credentials with a tiny prompt.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key (a masked value means "the saved key").
	 * @param string $model    Model ('' = provider default).
	 * @return array{success:bool,message:string,reply:string}
	 */
	public static function test( $provider, $key, $model ) {
		$provider = self::normalize_provider( $provider );
		if ( '' === $provider ) {
			return self::test_result( false, __( 'Choose a supported AI provider.', 'chatbot' ) );
		}
		$key = trim( (string) $key );
		if ( Settings::is_masked( $key ) ) {
			$key = Settings::api_key( $provider );
		}
		if ( '' === $key ) {
			return self::test_result( false, self::no_key_message( $provider ) );
		}
		$model = trim( (string) $model );
		if ( '' === $model ) {
			$model = self::default_model( $provider );
		}
		$reply = self::text(
			Prompts::test_system(),
			Prompts::test_user(),
			array(
				'provider'    => $provider,
				'key'         => $key,
				'model'       => $model,
				'max_tokens'    => 20,
				'temperature'   => 0,
				'timeout'       => 20,
				'spend_feature' => 'key_test',
			)
		);
		if ( is_wp_error( $reply ) ) {
			return self::test_result( false, $reply->get_error_message() );
		}
		/* translators: %s: provider name. */
		return self::test_result( true, sprintf( __( 'Connected to %s successfully.', 'chatbot' ), self::label( $provider ) ), $reply );
	}

	/**
	 * Provider/key/model to use. $opts may override any of them.
	 *
	 * Order: explicit opts → CRM settings (ai_provider, ai_model, <provider>_api_key)
	 * → WPBot's AI options for the same provider → WPBot's active AI provider.
	 *
	 * @param array $opts Overrides: provider, key, model.
	 * @return array{provider:string,key:string,model:string,source:string}
	 */
	public static function resolve( array $opts = array() ) {
		$provider = self::normalize_provider( isset( $opts['provider'] ) ? $opts['provider'] : Settings::string( 'ai_provider' ) );
		$key      = isset( $opts['key'] ) ? trim( (string) $opts['key'] ) : '';
		$model    = isset( $opts['model'] ) ? trim( (string) $opts['model'] ) : '';
		$source   = '' !== $key ? 'override' : 'crm';

		if ( '' !== $provider && '' === $key ) {
			$key = Settings::api_key( $provider );
			if ( '' === $model && '' !== $key && self::normalize_provider( Settings::string( 'ai_provider' ) ) === $provider ) {
				$model = trim( Settings::string( 'ai_model' ) );
			}
		}

		if ( '' === $key && ! isset( $opts['key'] ) ) {
			$wpbot  = self::wpbot_ai();
			$source = 'wpbot';
			if ( '' !== $provider && isset( $wpbot['providers'][ $provider ] ) ) {
				$key   = $wpbot['providers'][ $provider ]['key'];
				$model = '' !== $model ? $model : $wpbot['providers'][ $provider ]['model'];
			} elseif ( ! isset( $opts['provider'] ) && '' !== $wpbot['active'] ) {
				$provider = $wpbot['active'];
				$key      = $wpbot['providers'][ $provider ]['key'];
				$model    = $wpbot['providers'][ $provider ]['model'];
			}
		}

		if ( '' === $provider ) {
			$provider = 'openai';
		}
		if ( '' === $model ) {
			$model = self::default_model( $provider );
		}

		return array(
			'provider' => $provider,
			'key'      => $key,
			'model'    => $model,
			'source'   => $source,
		);
	}

	/**
	 * WPBot's own AI options (same names WPBot_Insights_Adapter::get_ai_import() reads).
	 *
	 * @return array{active:string,providers:array<string,array{key:string,model:string}>}
	 */
	public static function wpbot_ai() {
		$openai_model = get_option( 'qcld_openai_custom_model' );
		$map          = array(
			'openai'     => array( 'ai_enabled', 'open_ai_api_key', $openai_model ? $openai_model : get_option( 'openai_engines' ) ),
			'openrouter' => array( 'qcld_openrouter_enabled', 'qcld_openrouter_api_key', get_option( 'qcld_openrouter_model' ) ),
			'gemini'     => array( 'qcld_gemini_enabled', 'qcld_gemini_api_key', get_option( 'qcld_gemini_model' ) ),
			'grok'       => array( 'qcld_grok_enabled', 'qcld_grok_api_key', get_option( 'qcld_grok_model' ) ),
			'claude'     => array( 'qcld_claude_enabled', 'qcld_claude_api_key', get_option( 'qcld_claude_model' ) ),
		);
		$out          = array(
			'active'    => '',
			'providers' => array(),
		);
		foreach ( $map as $id => $option ) {
			$key = trim( (string) get_option( $option[1] ) );
			if ( '' === $key ) {
				continue;
			}
			$out['providers'][ $id ] = array(
				'key'   => $key,
				'model' => is_string( $option[2] ) ? trim( $option[2] ) : '',
			);
			if ( '' === $out['active'] && 1 === (int) get_option( $option[0] ) ) {
				$out['active'] = $id;
			}
		}
		if ( '' === $out['active'] && ! empty( $out['providers'] ) ) {
			$out['active'] = (string) key( $out['providers'] );
		}
		return $out;
	}

	/**
	 * Remove <think>…</think> reasoning blocks (and an unterminated leading one).
	 *
	 * @param string $text Model output.
	 * @return string
	 */
	public static function strip_think( $text ) {
		$text = preg_replace( '#<think(?:ing)?\b[^>]*>.*?</think(?:ing)?>#is', '', (string) $text );
		$text = preg_replace( '#^\s*<think(?:ing)?\b[^>]*>.*$#is', '', (string) $text );
		return (string) $text;
	}

	/**
	 * Decode a JSON reply: strips think blocks and ``` fences, then falls back
	 * to the outermost {…} or […] span.
	 *
	 * @param string $text Raw reply.
	 * @return array|null
	 */
	public static function decode_json( $text ) {
		$text = trim( self::strip_think( $text ) );
		if ( preg_match( '/```(?:json|JSON)?\s*(.*?)```/s', $text, $m ) ) {
			$text = trim( $m[1] );
		} else {
			$text = trim( preg_replace( '/^```(?:json|JSON)?|```$/', '', $text ) );
		}
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		foreach ( array( array( '{', '}' ), array( '[', ']' ) ) as $pair ) {
			$start = strpos( $text, $pair[0] );
			$end   = strrpos( $text, $pair[1] );
			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
				if ( is_array( $data ) ) {
					return $data;
				}
			}
		}
		return null;
	}

	/**
	 * Map a provider id or alias to an Insights provider id ('' when unsupported).
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function normalize_provider( $provider ) {
		$provider = sanitize_key( (string) $provider );
		if ( isset( self::ALIASES[ $provider ] ) ) {
			$provider = self::ALIASES[ $provider ];
		}
		return isset( self::LABELS[ $provider ] ) ? $provider : '';
	}

	/**
	 * Human label for a provider.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function label( $provider ) {
		return isset( self::LABELS[ $provider ] ) ? self::LABELS[ $provider ] : ucfirst( (string) $provider );
	}

	/**
	 * Whether the Insights AI client can be used, loading it when needed.
	 *
	 * @return bool
	 */
	public static function client_available() {
		if ( class_exists( self::CLIENT_CLASS ) ) {
			return true;
		}
		$autoload = dirname( __DIR__, 2 ) . '/insights/src/autoload.php';
		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}
		return class_exists( self::CLIENT_CLASS ) && class_exists( 'QuantumCloud\\Insights\\AI\\AI_Request' );
	}

	/**
	 * Send one system + user exchange.
	 *
	 * @param string $system System prompt.
	 * @param string $user   User prompt.
	 * @param array  $opts   Options.
	 * @param string $format text|json.
	 * @return string|\WP_Error Raw reply text.
	 */
	private static function complete( $system, $user, array $opts, $format ) {
		if ( ! self::client_available() ) {
			return new \WP_Error( 'wpbot_crm_ai_unavailable', __( 'The AI module of WPBot is not available on this site.', 'chatbot' ), array( 'status' => 503 ) );
		}
		$config = self::resolve( $opts );
		if ( '' === $config['key'] ) {
			return new \WP_Error( 'wpbot_crm_ai_no_key', self::no_key_message( $config['provider'] ), array( 'status' => 400 ) );
		}

		$messages = array();
		if ( '' !== trim( (string) $system ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => (string) $system,
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => (string) $user,
		);

		$request = new \QuantumCloud\Insights\AI\AI_Request(
			$messages,
			array(
				'max_tokens'      => isset( $opts['max_tokens'] ) ? max( 1, (int) $opts['max_tokens'] ) : 1200,
				'temperature'     => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.7,
				'response_format' => 'json' === $format ? 'json' : 'text',
				'timeout'         => isset( $opts['timeout'] ) ? max( 5, (int) $opts['timeout'] ) : 45,
				'feature'         => isset( $opts['spend_feature'] ) && '' !== sanitize_key( (string) $opts['spend_feature'] ) ? sanitize_key( (string) $opts['spend_feature'] ) : 'crm',
				'module'          => 'ai_marketing',
			)
		);
		$client   = new \QuantumCloud\Insights\AI\Provider_Client( $config['provider'], $config['key'], $config['model'] );
		// Site Insight's client checks the AI spending limits and records the call (module ai_marketing).
		$response = $client->complete( $request );

		if ( ! $response->ok() ) {
			$message = trim( (string) $response->error_message );
			if ( '' === $message ) {
				/* translators: %s: provider name. */
				$message = sprintf( __( '%s did not return a reply. Please try again.', 'chatbot' ), self::label( $config['provider'] ) );
			}
			return new \WP_Error( 'wpbot_crm_ai_failed', $message, array( 'status' => 502 ) );
		}
		if ( '' === trim( self::strip_think( $response->text ) ) ) {
			/* translators: %s: provider name. */
			return new \WP_Error( 'wpbot_crm_ai_failed', sprintf( __( '%s returned an empty reply. Try another model or a shorter request.', 'chatbot' ), self::label( $config['provider'] ) ), array( 'status' => 502 ) );
		}
		return (string) $response->text;
	}

	/**
	 * Default model for a provider (Insights catalog, else the CRM preset).
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	private static function default_model( $provider ) {
		// R10: WPBot Core's catalogue knows the current recommended model, so a model change needs no edit here.
		if ( class_exists( '\\QuantumCloud\\WPBotCore\\AI\\Providers' ) && method_exists( '\\QuantumCloud\\WPBotCore\\AI\\Providers', 'default_text_model' ) ) {
			$model = (string) \QuantumCloud\WPBotCore\AI\Providers::default_text_model( (string) $provider );
			if ( '' !== $model ) {
				return $model;
			}
		}
		if ( self::client_available() && class_exists( 'QuantumCloud\\Insights\\AI\\Providers' ) ) {
			$def = \QuantumCloud\Insights\AI\Providers::get( $provider );
			if ( is_array( $def ) && ! empty( $def['model'] ) ) {
				return (string) $def['model'];
			}
		}
		return 'openai' === $provider ? 'gpt-4o-mini' : '';
	}

	/**
	 * "No key" message for a provider.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	private static function no_key_message( $provider ) {
		/* translators: %s: provider name. */
		return sprintf( __( 'No API key configured for %s. Add one in Email Marketing → Settings → AI Provider & API.', 'chatbot' ), self::label( $provider ) );
	}

	/**
	 * Shape a test() result.
	 *
	 * @param bool   $success Success.
	 * @param string $message Message.
	 * @param string $reply   Reply text.
	 * @return array{success:bool,message:string,reply:string}
	 */
	private static function test_result( $success, $message, $reply = '' ) {
		return array(
			'success' => (bool) $success,
			'message' => (string) $message,
			'reply'   => trim( wp_strip_all_tags( (string) $reply ) ),
		);
	}
}
