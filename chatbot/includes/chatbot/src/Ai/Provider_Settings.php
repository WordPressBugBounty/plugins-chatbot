<?php
/**
 * AI provider settings, read from the options WPBot already stores.
 *
 * Every provider gets the same set of settings (the "common option set"):
 * enabled, key, model, url, system prompt, temperature, max tokens, streaming,
 * knowledge base, page context, page suggestions, AI forms, file upload,
 * prepend and append text, rate limit. Each setting comes from the option the
 * provider's settings page saves today; where a provider has no such option yet,
 * a new option `qcld_wpbot_chat_ai_{provider}_{setting}` holds it, and until an
 * owner sets it the value is what that provider does today (for example Grok's
 * fixed model and temperature, Claude's 1024-token ceiling). So reading these
 * settings changes nothing on an existing site.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Provider settings reader.
 */
final class Provider_Settings {

	/**
	 * Common settings and their type.
	 */
	const FIELDS = array(
		'enabled'          => 'bool',
		'key'              => 'string',
		'model'            => 'string',
		'url'              => 'string',
		'system_prompt'    => 'string',
		'temperature'      => 'float',
		'max_tokens'       => 'int',
		'stream'           => 'bool',
		'knowledge_base'   => 'bool',
		'page_context'     => 'bool',
		'page_suggestions' => 'bool',
		'ai_forms'         => 'bool',
		'file_upload'      => 'bool',
		'prepend'          => 'string',
		'append'           => 'string',
		'rate_limit'       => 'bool',
	);

	/**
	 * Order in which the widget picks the provider that answers when several are on
	 * (the legacy widget's else-if chain), DeepSeek last.
	 */
	const ANSWER_ORDER = array( 'openai', 'gemini', 'grok', 'claude', 'openrouter', 'mistral', 'ollama', 'deepseek' );

	/**
	 * Option name per provider and setting. null = no option today (a new one is used).
	 * Shared options (AI forms, file upload, rate limit) are the same for every provider.
	 *
	 * @return array<string,array<string,string|null>>
	 */
	public static function map() {
		$shared = array(
			'ai_forms'    => 'wpbot_ai_forms',
			'file_upload' => 'qcld_wpbot_ai_file_upload_enabled',
			'rate_limit'  => 'is_rate_limiting_enabled',
		);
		$map    = array(
			'openai'     => array(
				'enabled'          => 'ai_enabled',
				'key'              => 'open_ai_api_key',
				'model'            => 'openai_engines',
				'system_prompt'    => 'qcld_openai_system_content',
				'temperature'      => 'openai_temperature',
				'stream'           => 'is_stream_enabled',
				'knowledge_base'   => 'is_page_rag_enabled',
				'page_suggestions' => 'page_suggestion_enabled',
				'append'           => 'qcld_openai_append_content',
			),
			'gemini'     => array(
				'enabled'          => 'qcld_gemini_enabled',
				'key'              => 'qcld_gemini_api_key',
				'model'            => 'qcld_gemini_model',
				'system_prompt'    => 'qcld_gemini_system_content',
				'stream'           => 'qcld_gemini_stream_enabled',
				'knowledge_base'   => 'qcld_gemini_rag_enabled',
				'page_suggestions' => 'qcld_gemini_page_suggestion_enabled',
				'prepend'          => 'qcld_gemini_prepend_content',
				'append'           => 'qcld_gemini_append_content',
			),
			'claude'     => array(
				'enabled'          => 'qcld_claude_enabled',
				'key'              => 'qcld_claude_api_key',
				'model'            => 'qcld_claude_model',
				'system_prompt'    => 'qcld_claude_system_content',
				'stream'           => 'qcld_claude_stream_enabled',
				'knowledge_base'   => 'qcld_claude_rag_enabled',
				'page_suggestions' => 'qcld_claude_page_suggestion_enabled',
				'prepend'          => 'qcld_claude_prepend_content',
				'append'           => 'qcld_claude_append_content',
			),
			'grok'       => array(
				'enabled'          => 'qcld_grok_enabled',
				'key'              => 'qcld_grok_api_key',
				// qcld_grok_model is saved by the wizard but never used (the model is fixed today): new option.
				'system_prompt'    => 'qcld_grok_system_content',
				'stream'           => 'qcld_grok_stream_enabled',
				'knowledge_base'   => 'qcld_grok_rag_enabled',
				'page_suggestions' => 'qcld_grok_page_suggestion_enabled',
				'prepend'          => 'qcld_grok_prepend_content',
				'append'           => 'qcld_grok_append_content',
			),
			'openrouter' => array(
				'enabled'          => 'qcld_openrouter_enabled',
				'key'              => 'qcld_openrouter_api_key',
				'model'            => 'qcld_openrouter_model',
				'url'              => 'qcld_openrouter_endpoint_url',
				'knowledge_base'   => 'qcld_openrouter_rag_enabled',
				'page_suggestions' => 'qcld_openrouter_page_suggestion_enabled',
				'prepend'          => 'qcld_openrouter_prepend_content',
				'append'           => 'qcld_openrouter_append_content',
			),
			'mistral'    => array(
				'enabled'          => 'qcld_mistral_enabled',
				'key'              => 'qcld_mistral_api_key',
				'model'            => 'qcld_mistral_model',
				'knowledge_base'   => 'qcld_mistral_rag_enabled',
				'page_suggestions' => 'qcld_mistral_page_suggestion_enabled',
				'prepend'          => 'qcld_mistral_prepend_content',
				'append'           => 'qcld_mistral_append_content',
			),
			'ollama'     => array(
				'enabled'          => 'qcld_ollama_enabled',
				'model'            => 'qcld_ollama_model',
				'url'              => 'qcld_ollama_url',
				'page_suggestions' => 'qcld_ollama_page_suggestion_enabled',
			),
			'deepseek'   => array(
				'enabled'          => 'qcld_deepseekai_enabled',
				'key'              => 'qcld_deepseekai_api_key',
				'model'            => 'qcld_deepseekai_engines',
				'temperature'      => 'qcld_deepseekai_temperature',
				'max_tokens'       => 'deepseekai_max_tokens',
				'knowledge_base'   => 'qcld_deepseekai_rag_enabled',
				'page_suggestions' => 'qcld_deepseekai_page_suggestion_enabled',
			),
		);
		$out    = array();
		foreach ( $map as $provider => $fields ) {
			foreach ( array_keys( self::FIELDS ) as $field ) {
				$out[ $provider ][ $field ] = isset( $fields[ $field ] ) ? $fields[ $field ] : ( isset( $shared[ $field ] ) ? $shared[ $field ] : null );
			}
		}
		return $out;
	}

	/**
	 * What each provider does today when it has no option for a setting.
	 *
	 * @param string $provider Provider id.
	 * @return array<string,mixed>
	 */
	public static function defaults( $provider ) {
		$base  = array(
			'enabled'          => false,
			'key'              => '',
			'model'            => '',
			'url'              => '',
			'system_prompt'    => '',
			'temperature'      => null,
			'max_tokens'       => null,
			'stream'           => false,
			'knowledge_base'   => false,
			'page_context'     => false,
			'page_suggestions' => false,
			'ai_forms'         => true,
			'file_upload'      => false,
			'prepend'          => '',
			'append'           => '',
			'rate_limit'       => false,
		);
		$today = array(
			'openai'     => array( 'model' => 'gpt-4o-mini' ),
			'gemini'     => array(
				'model'        => 'gemini-2.5-flash',
				'page_context' => true,
		// Sends the page title today.
			),
			'claude'     => array(
				'model'        => 'claude-haiku-5-5',
				'max_tokens'   => 1024,
				'page_context' => true,
		// Sends the page title today.
			),
			'grok'       => array(
				'model'       => 'grok-3-latest',
				'temperature' => 0.0,
			),
			'openrouter' => array( 'model' => 'openai/gpt-4o-mini' ),
			'mistral'    => array( 'model' => 'mistral-small-latest' ),
			'ollama'     => array( 'model' => 'llama3' ),
			'deepseek'   => array( 'model' => 'deepseek-chat' ),
		);
		return array_merge( $base, isset( $today[ $provider ] ) ? $today[ $provider ] : array() );
	}

	/**
	 * New option that holds a setting a provider had no option for.
	 *
	 * @param string $provider Provider id.
	 * @param string $field    Setting.
	 * @return string
	 */
	public static function new_option( $provider, $field ) {
		return 'qcld_wpbot_chat_ai_' . $provider . '_' . $field;
	}

	/**
	 * All common settings of one provider.
	 *
	 * Since round R2 the answer settings (system prompt, extra instructions, temperature, longest
	 * answer, streaming, knowledge base, current page) come from the shared AI Settings
	 * (Ai_Settings), so every provider answers the same way; prepend and append are empty (the old
	 * fields are instructions now, never shown to visitors).
	 *
	 * @param string $provider Provider id.
	 * @param bool   $shared   Apply the shared AI Settings (false = this provider's own options).
	 * @return array<string,mixed>|null Null for an unknown provider.
	 */
	public static function get( $provider, $shared = true ) {
		$map = self::map();
		if ( ! isset( $map[ $provider ] ) ) {
			return null;
		}
		$defaults = self::defaults( $provider );
		$settings = array( 'provider' => $provider );
		foreach ( self::FIELDS as $field => $type ) {
			$option             = null !== $map[ $provider ][ $field ] ? $map[ $provider ][ $field ] : self::new_option( $provider, $field );
			$raw                = get_option( $option, null );
			$settings[ $field ] = self::cast( $raw, $type, $defaults[ $field ], $field );
		}
		$settings['instructions'] = '';
		if ( $shared ) {
			$common = Ai_Settings::get();
			foreach ( array( 'system_prompt', 'instructions', 'stream', 'knowledge_base', 'page_context' ) as $field ) {
				$settings[ $field ] = $common[ $field ];
			}
			foreach ( array( 'temperature', 'max_tokens' ) as $field ) {
				// Empty = the provider's own default (Claude's 1024-token ceiling, Grok's 0).
				if ( null !== $common[ $field ] ) {
					$settings[ $field ] = $common[ $field ];
				}
			}
			$settings['prepend'] = '';
			$settings['append']  = '';
		}
		return self::adjust( $provider, $settings );
	}

	/**
	 * Whether a provider can be called: it has an API key, or for Ollama a server address.
	 *
	 * @param string $provider Provider id.
	 * @return bool
	 */
	public static function is_set_up( $provider ) {
		$settings = self::get( (string) $provider );
		if ( ! is_array( $settings ) ) {
			return false;
		}
		return 'ollama' === $provider ? '' !== (string) $settings['url'] : '' !== (string) $settings['key'];
	}

	/**
	 * The provider that answers: the first one switched on, in the widget's order.
	 *
	 * @return string Provider id, or '' when none is on.
	 */
	public static function answering_provider() {
		$map = self::map();
		foreach ( self::ANSWER_ORDER as $provider ) {
			$option = $map[ $provider ]['enabled'];
			if ( self::cast( get_option( $option, null ), 'bool', false, 'enabled' ) ) {
				return $provider;
			}
		}
		return '';
	}

	/**
	 * Cast a stored value. Empty values fall back to the default.
	 *
	 * @param mixed  $raw      Stored value (null when the option does not exist).
	 * @param string $type     bool, int, float or string.
	 * @param mixed  $fallback Default.
	 * @param string $field    Setting (AI forms are "on" when any form exists).
	 * @return mixed
	 */
	private static function cast( $raw, $type, $fallback, $field ) {
		if ( 'ai_forms' === $field && is_array( $raw ) ) {
			return array() !== $raw;
		}
		if ( null === $raw || '' === $raw || ( false === $raw && 'bool' !== $type ) ) {
			return $fallback;
		}
		switch ( $type ) {
			case 'bool':
				return in_array( $raw, array( 1, '1', true, 'true', 'yes', 'on' ), true );
			case 'int':
				return is_numeric( $raw ) && (int) $raw > 0 ? (int) $raw : $fallback;
			case 'float':
				return is_numeric( $raw ) ? max( 0.0, min( 2.0, (float) $raw ) ) : $fallback;
			default:
				return is_scalar( $raw ) ? trim( (string) $raw ) : $fallback;
		}
	}

	/**
	 * Provider-specific readings of today's options.
	 *
	 * @param string $provider Provider id.
	 * @param array  $settings Settings.
	 * @return array
	 */
	private static function adjust( $provider, array $settings ) {
		$settings['key'] = self::clean_key( (string) $settings['key'] );
		// Round R2: the separate OpenAI fine-tuned model field is gone (owner, 10 November 2026);
		// its saved value is no longer used (Admin\Removed_Settings_Notice tells the owner).
		if ( 'ollama' === $provider && '' !== $settings['url'] ) {
			// Stored as host and port; the client speaks Ollama's OpenAI-compatible API under /v1.
			$port = get_option( 'qcld_ollama_port', '' );
			$url  = preg_replace( '#/v1$#', '', rtrim( $settings['url'], '/' ) );
			if ( is_scalar( $port ) && '' !== (string) $port && null === wp_parse_url( $url, PHP_URL_PORT ) ) {
				$url .= ':' . (int) $port;
			}
			$settings['url'] = $url . '/v1';
		}
		// A retired model is swapped for its replacement so the site keeps answering (CB1b);
		// the owner sees a notice (Admin\Retired_Model_Notice).
		$routing = '\QuantumCloud\WPBotCore\AI\Model_Routing';
		if ( '' !== (string) $settings['model'] && class_exists( $routing ) ) {
			$replacement = (string) $routing::replacement( $provider, (string) $settings['model'] );
			if ( '' !== $replacement ) {
				$settings['retired_model'] = (string) $settings['model'];
				$settings['model']         = $replacement;
			}
		}
		return $settings;
	}

	/**
	 * An API key as pasted: printable ASCII only, no "Bearer " prefix (same as the host's
	 * qcld_wpbot_clean_api_key(), PR #409).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function clean_key( $key ) {
		$key = trim( (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $key ) );
		return (string) preg_replace( '/^Bearer(?=\S)/i', '', preg_replace( '/^Bearer\s+/i', '', $key ) );
	}
}
