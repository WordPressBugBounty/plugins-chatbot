<?php
/**
 * Catalogue of AI providers and models with capability flags and prices.
 *
 * Model lists are data, not code: hosts and owners can add models through the
 * qcld_wpbot_core/ai_providers filter, and the settings screen also accepts
 * any model id typed by hand. Prices are estimates in USD per million tokens
 * and are only used for cost estimates; they are filterable too.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Static provider catalogue.
 */
final class Providers {

	/**
	 * Wire formats the client knows how to speak.
	 */
	const FORMAT_OPENAI    = 'openai';
	const FORMAT_ANTHROPIC = 'anthropic';
	const FORMAT_GEMINI    = 'gemini';

	/**
	 * Every provider, filtered.
	 *
	 * Each provider: label, format, base_url, needs_key, needs_url,
	 * models => id => [ label, text, image, web_search, embeddings, price_in, price_out ].
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$providers = array(
			'openai'     => array(
				'label'      => 'OpenAI',
				'format'     => self::FORMAT_OPENAI,
				'web_search' => true,
				'base_url'   => 'https://api.openai.com/v1',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => array(
					'gpt-5-mini'   => self::model( 'GPT-5 mini', true, false, true, 0.25, 2.00 ),
					'gpt-5'        => self::model( 'GPT-5', true, false, true, 1.25, 10.00 ),
					'gpt-4.1-mini' => self::model( 'GPT-4.1 mini', true, false, false, 0.40, 1.60 ),
					'gpt-image-1'  => self::model( 'GPT Image 1', false, true, false, 5.00, 40.00 ),
				),
			),
			'claude'     => array(
				'label'      => 'Anthropic Claude',
				'format'     => self::FORMAT_ANTHROPIC,
				'web_search' => true,
				'base_url'   => 'https://api.anthropic.com/v1',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => array(
					'claude-opus-5-5'   => self::model( 'Claude Opus 5.5', true, false, true, 4.00, 20.00 ),
					'claude-sonnet-5-5' => self::model( 'Claude Sonnet 5.5', true, false, true, 2.00, 10.00 ),
					'claude-haiku-5-5'  => self::model( 'Claude Haiku 5.5', true, false, false, 0.10, 0.50 ),
				),
			),
			'gemini'     => array(
				'label'      => 'Google Gemini',
				'format'     => self::FORMAT_GEMINI,
				'web_search' => true,
				'base_url'   => 'https://generativelanguage.googleapis.com/v1beta',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => array(
					'gemini-2.5-flash'       => self::model( 'Gemini 2.5 Flash', true, false, true, 0.30, 2.50 ),
					'gemini-2.5-pro'         => self::model( 'Gemini 2.5 Pro', true, false, true, 1.25, 10.00 ),
					'gemini-2.5-flash-image' => self::model( 'Gemini 2.5 Flash Image', false, true, false, 0.30, 30.00 ),
				),
			),
			'openrouter' => array(
				'label'      => 'OpenRouter',
				'format'     => self::FORMAT_OPENAI,
				'web_search' => true,
				'base_url'   => 'https://openrouter.ai/api/v1',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => array(
					'openai/gpt-5-mini'           => self::model( 'GPT-5 mini (OpenRouter)', true, false, true, 0.25, 2.00 ),
					'anthropic/claude-sonnet-5.5' => self::model( 'Claude Sonnet 5.5 (OpenRouter)', true, false, true, 2.00, 10.00 ),
					'google/gemini-2.5-flash'     => self::model( 'Gemini 2.5 Flash (OpenRouter)', true, false, true, 0.30, 2.50 ),
				),
			),
			'grok'       => array(
				'label'     => 'xAI Grok',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => 'https://api.x.ai/v1',
				'needs_key' => true,
				'needs_url' => false,
				'models'    => array(
					'grok-4'       => self::model( 'Grok 4', true, false, true, 3.00, 15.00 ),
					'grok-3-mini'  => self::model( 'Grok 3 mini', true, false, false, 0.30, 0.50 ),
					'grok-2-image' => self::model( 'Grok 2 Image', false, true, false, 0.00, 70.00 ),
				),
			),
			'mistral'    => array(
				'label'     => 'Mistral',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => 'https://api.mistral.ai/v1',
				'needs_key' => true,
				'needs_url' => false,
				'models'    => array(
					'mistral-small-latest' => self::model( 'Mistral Small', true, false, false, 0.10, 0.30 ),
					'mistral-large-latest' => self::model( 'Mistral Large', true, false, false, 2.00, 6.00 ),
				),
			),
			'custom'     => array(
				'label'     => 'Custom (OpenAI-compatible)',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => '',
				'needs_key' => false,
				'needs_url' => true,
				'models'    => array(),
			),
		);

		/**
		 * Filter the provider catalogue (add models, change prices).
		 *
		 * @param array $providers Catalogue.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/ai_providers', $providers );
	}

	/**
	 * One provider definition, or null.
	 *
	 * @param string $id Provider id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Whether a provider's API reads PDF documents sent with a request
	 * (Request images with mime application/pdf).
	 *
	 * @param string $id Provider id.
	 * @return bool
	 */
	public static function reads_pdf( $id ) {
		/**
		 * Providers whose API reads PDF documents.
		 *
		 * @param string[] $ids Provider ids.
		 */
		$ids = (array) apply_filters( 'qcld_wpbot_core/providers_reading_pdf', array( 'openai', 'claude', 'gemini', 'openrouter' ) );
		return in_array( (string) $id, $ids, true );
	}

	/**
	 * Whether an id is a known provider.
	 *
	 * @param string $id Provider id.
	 * @return bool
	 */
	public static function exists( $id ) {
		return null !== self::get( $id );
	}

	/**
	 * Capability flags and price of a model. Unknown models (typed by hand)
	 * are assumed to be text-only with no price, so estimates stay honest.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return array
	 */
	public static function model_info( $provider, $model ) {
		$definition = self::get( $provider );
		if ( $definition && isset( $definition['models'][ $model ] ) ) {
			return $definition['models'][ $model ];
		}
		return self::model( $model, true, false, false, 0.0, 0.0 );
	}

	/**
	 * First text model of a provider, used as the default.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function default_text_model( $provider ) {
		$definition = self::get( $provider );
		if ( ! $definition ) {
			return '';
		}
		foreach ( $definition['models'] as $id => $info ) {
			if ( ! empty( $info['text'] ) ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * Build one model entry.
	 *
	 * @param string $label      Label.
	 * @param bool   $text       Writes text.
	 * @param bool   $image      Makes images.
	 * @param bool   $web_search Can search the web.
	 * @param float  $price_in   USD per million input tokens.
	 * @param float  $price_out  USD per million output tokens.
	 * @return array
	 */
	private static function model( $label, $text, $image, $web_search, $price_in, $price_out ) {
		return array(
			'label'      => $label,
			'text'       => (bool) $text,
			'image'      => (bool) $image,
			'web_search' => (bool) $web_search,
			'embeddings' => false,
			'price_in'   => (float) $price_in,
			'price_out'  => (float) $price_out,
		);
	}
}
