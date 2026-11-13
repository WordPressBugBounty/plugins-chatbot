<?php
/**
 * Catalogue of embedding providers and models (knowledge base vectors).
 *
 * Kept apart from Providers because some embedding services (Voyage, the one
 * Anthropic recommends for Claude sites) do not write text, and some text
 * providers (Claude, Grok, DeepSeek) have no embeddings API. Each model has a
 * fixed vector size, stored with every knowledge chunk so sizes never mix.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Static embeddings catalogue.
 */
final class Embeddings {

	/**
	 * Every embedding provider, filtered.
	 *
	 * Each provider: label, format ("openai" for POST /embeddings, "gemini" for
	 * batchEmbedContents), base_url, needs_key, needs_url, models => id => [ label, dimensions, price ]
	 * (price in USD per million input tokens), default model.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$providers = array(
			'openai'     => array(
				'label'     => 'OpenAI',
				'format'    => 'openai',
				'base_url'  => 'https://api.openai.com/v1',
				'needs_key' => true,
				'needs_url' => false,
				'default'   => 'text-embedding-3-small',
				'models'    => array(
					'text-embedding-3-small' => self::model( 'text-embedding-3-small', 1536, 0.02 ),
					'text-embedding-3-large' => self::model( 'text-embedding-3-large', 3072, 0.13 ),
				),
			),
			'gemini'     => array(
				'label'     => 'Google Gemini',
				'format'    => 'gemini',
				'base_url'  => 'https://generativelanguage.googleapis.com/v1beta',
				'needs_key' => true,
				'needs_url' => false,
				'default'   => 'gemini-embedding-001',
				'models'    => array(
					// 3072 (the model's full size) is what WPBot's knowledge base stored before, so old vectors are reused.
					'gemini-embedding-001' => self::model( 'Gemini Embedding', 3072, 0.15 ),
				),
			),
			'mistral'    => array(
				'label'     => 'Mistral',
				'format'    => 'openai',
				'base_url'  => 'https://api.mistral.ai/v1',
				'needs_key' => true,
				'needs_url' => false,
				'default'   => 'mistral-embed',
				'models'    => array(
					'mistral-embed' => self::model( 'Mistral Embed', 1024, 0.10 ),
				),
			),
			'voyage'     => array(
				'label'     => 'Voyage AI',
				'format'    => 'openai',
				'base_url'  => 'https://api.voyageai.com/v1',
				'needs_key' => true,
				'needs_url' => false,
				'default'   => 'voyage-4',
				'models'    => array(
					'voyage-4'        => self::model( 'Voyage 4', 1024, 0.06 ),
					'voyage-3.5-lite' => self::model( 'Voyage 3.5 lite', 1024, 0.02 ),
					'voyage-3.5'      => self::model( 'Voyage 3.5', 1024, 0.06 ),
				),
			),
			'openrouter' => array(
				'label'     => 'OpenRouter',
				'format'    => 'openai',
				'base_url'  => 'https://openrouter.ai/api/v1',
				'needs_key' => true,
				'needs_url' => false,
				'default'   => 'openai/text-embedding-3-small',
				'models'    => array(
					'openai/text-embedding-3-small' => self::model( 'text-embedding-3-small (OpenRouter)', 1536, 0.02 ),
				),
			),
			'ollama'     => array(
				'label'     => 'Ollama',
				'format'    => 'openai',
				'base_url'  => '',
				'needs_key' => false,
				'needs_url' => true,
				'default'   => 'nomic-embed-text',
				'models'    => array(
					'nomic-embed-text' => self::model( 'nomic-embed-text', 768, 0.0 ),
				),
			),
		);

		/**
		 * Filter the embeddings catalogue.
		 *
		 * @param array $providers Catalogue.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/embedding_providers', $providers );
	}

	/**
	 * One provider, or null.
	 *
	 * @param string $id Provider id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Models the providers retired, with the model that replaces each: provider => [ retired => current ].
	 * Google retired text-embedding-004 (legacy audit R10, November 2026).
	 */
	const RETIRED = array(
		'gemini' => array( 'text-embedding-004' => 'gemini-embedding-001' ),
	);

	/**
	 * The model to use: a retired model's replacement, else the model itself.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return string
	 */
	public static function current_model( $provider, $model ) {
		$model = (string) $model;
		return isset( self::RETIRED[ (string) $provider ][ $model ] ) ? self::RETIRED[ (string) $provider ][ $model ] : $model;
	}

	/**
	 * Vector size of a model, or 0 when unknown (learned from the first answer).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return int
	 */
	public static function dimensions( $provider, $model ) {
		$definition = self::get( $provider );
		return $definition && isset( $definition['models'][ $model ] ) ? (int) $definition['models'][ $model ]['dimensions'] : 0;
	}

	/**
	 * Build one model entry.
	 *
	 * @param string $label      Label.
	 * @param int    $dimensions Vector size.
	 * @param float  $price      USD per million input tokens.
	 * @return array
	 */
	private static function model( $label, $dimensions, $price ) {
		return array(
			'label'      => $label,
			'dimensions' => (int) $dimensions,
			'price'      => (float) $price,
		);
	}
}
