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
	 * models => id => [ label, text, image, web_search, embeddings, price_in, price_out, price_known, pick ].
	 * pick: 'value' (best value, the default model), 'quality' (best quality) or 'cheap' (cheapest), else ''.
	 * The recommended models come first. Model_Catalog adds the models the account offers and current prices.
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
					'gpt-6.1-terra' => self::model( 'GPT-6.1 Terra', true, false, true, 1.00, 5.00, 'value' ),
					'gpt-6-astra'   => self::model( 'GPT-6 Astra', true, false, true, 10.00, 50.00, 'quality' ),
					'gpt-6-luna'    => self::model( 'GPT-6 Luna', true, false, true, 0.10, 0.50, 'cheap' ),
					'gpt-6.1-sol'   => self::model( 'GPT-6.1 Sol', true, false, true, 2.00, 10.00 ),
					'gpt-6-sol'     => self::model( 'GPT-6 Sol', true, false, true, 2.00, 10.00 ),
					'gpt-5.6-sol'   => self::model( 'GPT-5.6 Sol', true, false, true, 4.00, 20.00 ),
					'gpt-5.6-terra' => self::model( 'GPT-5.6 Terra', true, false, true, 2.00, 12.00 ),
					'gpt-5.6-luna'  => self::model( 'GPT-5.6 Luna', true, false, true, 0.20, 1.20 ),
					'gpt-5.5'       => self::model( 'GPT-5.5', true, false, true, 5.00, 30.00 ),
					'gpt-5.4'       => self::model( 'GPT-5.4', true, false, true, 2.50, 15.00 ),
					'gpt-5.4-mini'  => self::model( 'GPT-5.4 mini', true, false, true, 0.75, 4.50 ),
					'gpt-5.4-nano'  => self::model( 'GPT-5.4 nano', true, false, true, 0.20, 1.25 ),
					'gpt-5.2'       => self::model( 'GPT-5.2', true, false, true, 1.75, 14.00 ),
					'gpt-5.1'       => self::model( 'GPT-5.1', true, false, true, 1.25, 10.00 ),
					'gpt-5'         => self::model( 'GPT-5', true, false, true, 1.25, 10.00 ),
					'gpt-5-mini'    => self::model( 'GPT-5 mini', true, false, true, 0.25, 2.00 ),
					'gpt-5-nano'    => self::model( 'GPT-5 nano', true, false, true, 0.05, 0.40 ),
					'o3'            => self::model( 'o3', true, false, true, 2.00, 8.00 ),
					'gpt-4.1'       => self::model( 'GPT-4.1', true, false, false, 2.00, 8.00 ),
					'gpt-4.1-mini'  => self::model( 'GPT-4.1 mini', true, false, false, 0.40, 1.60 ),
					'gpt-4o-mini'   => self::model( 'GPT-4o mini', true, false, false, 0.15, 0.60 ),
					'gpt-4o'        => self::model( 'GPT-4o', true, false, false, 2.50, 10.00 ),
					'gpt-image-2'   => self::unpriced( self::model( 'GPT Image 2', false, true, false, 0.00, 0.00 ) ),
					'gpt-image-1.5' => self::unpriced( self::model( 'GPT Image 1.5', false, true, false, 0.00, 0.00 ) ),
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
					'claude-sonnet-5-5' => self::model( 'Claude Sonnet 5.5', true, false, true, 2.00, 10.00, 'value' ),
					'claude-fable-5-2'  => self::model( 'Claude Fable 5.2', true, false, true, 8.00, 40.00, 'quality' ),
					'claude-haiku-5-5'  => self::model( 'Claude Haiku 5.5', true, false, false, 0.10, 0.50, 'cheap' ),
					'claude-opus-5-5'   => self::model( 'Claude Opus 5.5', true, false, true, 4.00, 20.00 ),
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
					'gemini-3.8-flash'       => self::model( 'Gemini 3.8 Flash', true, false, true, 0.75, 3.75, 'value' ),
					'gemini-4-argon'         => self::model( 'Gemini 4 Argon', true, false, true, 2.00, 10.00, 'quality' ),
					'gemini-3.1-flash-lite'  => self::model( 'Gemini 3.1 Flash-Lite', true, false, true, 0.25, 1.50, 'cheap' ),
					'gemini-3.7-flash'       => self::model( 'Gemini 3.7 Flash', true, false, true, 0.75, 3.75 ),
					'gemini-3.5-flash'       => self::model( 'Gemini 3.5 Flash', true, false, true, 1.50, 9.00 ),
					'gemini-3.5-flash-lite'  => self::model( 'Gemini 3.5 Flash-Lite', true, false, true, 0.30, 2.50 ),
					'gemini-3.1-pro-preview' => self::model( 'Gemini 3.1 Pro (preview)', true, false, true, 2.00, 12.00 ),
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
					'openai/gpt-6.1-terra'         => self::model( 'GPT-6.1 Terra (OpenRouter)', true, false, true, 1.00, 5.00, 'value' ),
					'anthropic/claude-fable-5.2'   => self::model( 'Claude Fable 5.2 (OpenRouter)', true, false, true, 8.00, 40.00, 'quality' ),
					'google/gemini-3.1-flash-lite' => self::model( 'Gemini 3.1 Flash-Lite (OpenRouter)', true, false, true, 0.25, 1.50, 'cheap' ),
					'anthropic/claude-sonnet-5.5'  => self::model( 'Claude Sonnet 5.5 (OpenRouter)', true, false, true, 2.00, 10.00 ),
					'openai/gpt-5-mini'            => self::model( 'GPT-5 mini (OpenRouter)', true, false, true, 0.25, 2.00 ),
					'google/gemini-2.5-flash'      => self::model( 'Gemini 2.5 Flash (OpenRouter)', true, false, true, 0.30, 2.50 ),
					'openai/gpt-4o-mini'           => self::model( 'GPT-4o mini (OpenRouter)', true, false, false, 0.15, 0.60 ),
				),
			),
			'grok'       => array(
				'label'     => 'xAI Grok',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => 'https://api.x.ai/v1',
				'needs_key' => true,
				'needs_url' => false,
				'models'    => array(
					'grok-4.3'      => self::model( 'Grok 4.3', true, false, true, 1.25, 2.50, 'value' ),
					'grok-4.7'      => self::model( 'Grok 4.7', true, false, true, 2.00, 6.00, 'quality' ),
					'grok-4.6'      => self::model( 'Grok 4.6', true, false, true, 2.00, 6.00 ),
					'grok-4.5'      => self::model( 'Grok 4.5', true, false, true, 2.00, 6.00 ),
					'grok-4'        => self::model( 'Grok 4', true, false, true, 3.00, 15.00 ),
					'grok-3-mini'   => self::model( 'Grok 3 mini', true, false, false, 0.30, 0.50 ),
					'grok-3-latest' => self::model( 'Grok 3', true, false, false, 3.00, 15.00 ),
					'grok-2-image'  => self::model( 'Grok 2 Image', false, true, false, 0.00, 70.00 ),
				),
			),
			'mistral'    => array(
				'label'     => 'Mistral',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => 'https://api.mistral.ai/v1',
				'needs_key' => true,
				'needs_url' => false,
				'models'    => array(
					'mistral-small-latest'  => self::model( 'Mistral Small', true, false, false, 0.15, 0.60, 'value' ),
					'mistral-large-latest'  => self::model( 'Mistral Large', true, false, false, 0.70, 2.10, 'quality' ),
					'ministral-8b-latest'   => self::model( 'Ministral 8B', true, false, false, 0.15, 0.15, 'cheap' ),
					'mistral-medium-latest' => self::model( 'Mistral Medium', true, false, false, 1.50, 7.50 ),
				),
			),
			'deepseek'   => array(
				'label'     => 'DeepSeek',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => 'https://api.deepseek.com/v1',
				'needs_key' => true,
				'needs_url' => false,
				'models'    => array(
					'deepseek-chat'     => self::model( 'DeepSeek Chat', true, false, false, 0.27, 1.10, 'value' ),
					'deepseek-reasoner' => self::model( 'DeepSeek Reasoner', true, false, false, 0.55, 2.19, 'quality' ),
				),
			),
			// Ollama runs on the owner's own server; its OpenAI-compatible API lives under /v1.
			'ollama'     => array(
				'label'     => 'Ollama',
				'format'    => self::FORMAT_OPENAI,
				'base_url'  => '',
				'needs_key' => false,
				'needs_url' => true,
				'models'    => array(),
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
	 * Whether a model can call tools natively (AI\Tool_Calls). Providers: OpenAI, Claude, Gemini,
	 * Grok, OpenRouter, Mistral and DeepSeek; a model entry may set 'tools' => false. Ollama and
	 * custom endpoints: off unless the filter says so (callers use their text fallback).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return bool
	 */
	public static function supports_tools( $provider, $model ) {
		$providers = array( 'openai', 'claude', 'gemini', 'grok', 'openrouter', 'mistral', 'deepseek' );
		$supported = in_array( (string) $provider, $providers, true );
		$info      = self::model_info( $provider, $model );
		if ( isset( $info['tools'] ) ) {
			$supported = (bool) $info['tools'];
		} elseif ( empty( $info['text'] ) ) {
			$supported = false;
		}
		/**
		 * Whether a model can call tools natively.
		 *
		 * @param bool   $supported Supported.
		 * @param string $provider  Provider id.
		 * @param string $model     Model id.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/supports_tools', $supported, $provider, $model );
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
	 * Capability flags and price of a model. Models not in the catalogue take the price
	 * feed's current list price (Model_Catalog); others (typed by hand) are assumed to be
	 * text-only with no price, so estimates stay honest.
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
		// Not in the catalogue: the current list price from the price feed, when it has one.
		$price = Model_Catalog::price( (string) $provider, (string) $model );
		if ( null !== $price ) {
			return self::model( $model, true, false, false, $price[0], $price[1] );
		}
		$unknown                = self::model( $model, true, false, false, 0.0, 0.0 );
		$unknown['price_known'] = false;
		return $unknown;
	}

	/**
	 * Whether the catalogue has a price for a model, so a cost estimate means something.
	 * Models typed by hand, and filtered-in models without prices, have none. Models on the
	 * owner's own server (Ollama) cost nothing to call and count as known.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return bool
	 */
	public static function price_known( $provider, $model ) {
		if ( 'ollama' === $provider ) {
			return true;
		}
		$info = self::model_info( $provider, $model );
		return ! isset( $info['price_known'] ) || (bool) $info['price_known'];
	}

	/**
	 * A provider's default model: its "best value" pick, else its first text model.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function default_text_model( $provider ) {
		$definition = self::get( $provider );
		if ( ! $definition ) {
			return '';
		}
		$picks = Model_Catalog::picks( $provider );
		$value = array_search( Model_Catalog::PICK_VALUE, $picks, true );
		if ( false !== $value ) {
			return (string) $value;
		}
		foreach ( $definition['models'] as $id => $info ) {
			if ( ! empty( $info['text'] ) ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * A model entry whose price is not known yet (estimates say "price unknown").
	 *
	 * @param array $model Model entry.
	 * @return array
	 */
	private static function unpriced( array $model ) {
		$model['price_known'] = false;
		return $model;
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
	 * @param string $pick       Recommended as: value (the default), quality or cheap; '' = not a pick.
	 * @return array
	 */
	private static function model( $label, $text, $image, $web_search, $price_in, $price_out, $pick = '' ) {
		return array(
			'label'       => $label,
			'text'        => (bool) $text,
			'image'       => (bool) $image,
			'web_search'  => (bool) $web_search,
			'embeddings'  => false,
			'price_in'    => (float) $price_in,
			'price_out'   => (float) $price_out,
			'price_known' => true,
			'pick'        => (string) $pick,
		);
	}
}
