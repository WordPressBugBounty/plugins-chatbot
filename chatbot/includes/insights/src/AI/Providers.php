<?php
/**
 * The AI providers Insights can call, with their default and suggested models.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

final class Providers {

	/**
	 * Provider catalog. Model lists are suggestions only; any model id the
	 * provider accepts can be typed in.
	 *
	 * Optional keys: needs_key (default true), needs_url (default false: the
	 * provider runs on a server whose address the owner enters, e.g. Ollama),
	 * default_url.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		$providers = array(
			'openai'     => array(
				'label'    => 'OpenAI',
				'api'      => 'openai',
				'endpoint' => 'https://api.openai.com/v1/chat/completions',
				'model'    => 'gpt-4o-mini',
				'models'   => array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1' ),
				'key_url'  => 'https://platform.openai.com/api-keys',
			),
			'claude'     => array(
				'label'    => 'Claude',
				'api'      => 'anthropic',
				'endpoint' => 'https://api.anthropic.com/v1/messages',
				'model'    => 'claude-haiku-4-5',
				'models'   => array( 'claude-haiku-4-5', 'claude-sonnet-4-6', 'claude-sonnet-4-5' ),
				'key_url'  => 'https://console.anthropic.com/settings/keys',
			),
			'gemini'     => array(
				'label'    => 'Gemini',
				'api'      => 'gemini',
				'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
				'model'    => 'gemini-2.5-flash',
				'models'   => array( 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.5-pro' ),
				'key_url'  => 'https://aistudio.google.com/app/apikey',
			),
			'grok'       => array(
				'label'    => 'Grok',
				'api'      => 'openai',
				'endpoint' => 'https://api.x.ai/v1/chat/completions',
				'model'    => 'grok-3-mini',
				'models'   => array( 'grok-3-mini', 'grok-3-latest', 'grok-4' ),
				'key_url'  => 'https://console.x.ai/',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'api'      => 'openai',
				'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
				'model'    => 'openai/gpt-4o-mini',
				'models'   => array( 'openai/gpt-4o-mini', 'anthropic/claude-haiku-4.5', 'google/gemini-2.5-flash' ),
				'key_url'  => 'https://openrouter.ai/keys',
			),
			'mistral'    => array(
				'label'    => 'Mistral',
				'api'      => 'openai',
				'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
				'model'    => 'mistral-small-latest',
				'models'   => array( 'mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest' ),
				'key_url'  => 'https://console.mistral.ai/api-keys',
			),
			'ollama'     => array(
				'label'       => 'Ollama',
				'api'         => 'openai',
				// {base} is the server address saved in the settings.
				'endpoint'    => '{base}/v1/chat/completions',
				'model'       => 'llama3.1',
				'models'      => array( 'llama3.1', 'llama3.2', 'qwen2.5', 'mistral' ),
				'key_url'     => 'https://ollama.com/download',
				'needs_key'   => false,
				'needs_url'   => true,
				'default_url' => 'http://localhost:11434',
			),
		);

		/**
		 * Change the provider catalog (labels, endpoints, suggested models).
		 *
		 * @param array<string,array<string,mixed>> $providers Providers by id.
		 */
		return apply_filters( 'qcin/ai_providers', $providers );
	}

	/**
	 * One provider, or null.
	 *
	 * @param string $id Provider id.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Whether a provider needs an API key.
	 *
	 * @param array<string,mixed> $def Provider definition.
	 * @return bool
	 */
	public static function needs_key( array $def ) {
		return ! isset( $def['needs_key'] ) || (bool) $def['needs_key'];
	}

	/**
	 * Whether a provider needs a server address.
	 *
	 * @param array<string,mixed> $def Provider definition.
	 * @return bool
	 */
	public static function needs_url( array $def ) {
		return ! empty( $def['needs_url'] );
	}

	/**
	 * Provider ids.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::all() );
	}
}
