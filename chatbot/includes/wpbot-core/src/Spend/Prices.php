<?php
/**
 * Estimated cost of any AI call: tokens (with cached-input discounts), embeddings, images,
 * speech, transcription and web searches.
 *
 * Token prices come from the model catalogue (AI\Providers, plus the weekly price feed).
 * The other prices are list prices kept here and checked at each release; the filter
 * qcld_wpbot_core/spend_prices lets a site or add-on correct them. Every figure is an estimate:
 * the screens say so and link to the provider's billing page.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\AI\Embeddings;
use QuantumCloud\WPBotCore\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Price list and estimates.
 */
final class Prices {

	/**
	 * Prices that are not per token (USD).
	 *
	 * Keys: image (provider => model => price per image, 1024 px, standard quality; 'hd' doubles),
	 * tts (provider => model => per million characters), stt (provider => model => per minute
	 * of audio), search (provider => per web search), cached (provider => model prefix => share
	 * of the input price paid for cached input), write (provider => share paid for cache writes).
	 *
	 * @return array
	 */
	public static function table() {
		$table = array(
			'image'  => array(
				'openai'     => array(
					'dall-e-3'    => 0.04,
					'dall-e-2'    => 0.02,
					'gpt-image-1' => 0.042,
				),
				'gemini'     => array(
					'gemini-2.5-flash-image' => 0.039,
				),
				'openrouter' => array(),
			),
			'tts'    => array(
				'openai' => array(
					'tts-1'    => 15.00,
					'tts-1-hd' => 30.00,
				),
			),
			'stt'    => array(
				'openai' => array(
					'whisper-1'              => 0.006,
					'gpt-4o-transcribe'      => 0.006,
					'gpt-4o-mini-transcribe' => 0.003,
				),
			),
			'search' => array(
				'openai'     => 0.01,
				'claude'     => 0.01,
				'gemini'     => 0.035,
				'openrouter' => 0.004,
			),
			'cached' => array(
				'openai'   => array(
					'gpt-4o' => 0.5,
					'gpt-4.' => 0.25,
					''       => 0.1,
				),
				'claude'   => array( '' => 0.1 ),
				'gemini'   => array( '' => 0.25 ),
				'deepseek' => array( '' => 0.26 ),
				'grok'     => array( '' => 0.25 ),
			),
			'write'  => array(
				'claude' => 1.25,
			),
		);

		/**
		 * Filter the prices that are not per token (and the cached-input shares).
		 *
		 * @param array $table See Prices::table().
		 */
		return (array) apply_filters( 'qcld_wpbot_core/spend_prices', $table );
	}

	/**
	 * Estimate one call.
	 *
	 * @param string $provider Provider id (AI\Providers or AI\Embeddings id).
	 * @param string $model    Model id.
	 * @param array  $usage    Usage_Reader::blank() shape (missing keys are 0). Optional:
	 *                         image_quality ('hd' doubles the image price).
	 * @return array{cost: float, price_known: bool}
	 */
	public static function estimate( $provider, $model, array $usage ) {
		$usage = array_merge( Usage_Reader::blank(), $usage );
		if ( null !== $usage['provider_cost'] ) {
			return array(
				'cost'        => round( (float) $usage['provider_cost'], 6 ),
				'price_known' => true,
			);
		}
		if ( 'ollama' === $provider ) {
			return array(
				'cost'        => 0.0,
				'price_known' => true,
			);
		}

		$table = self::table();
		$cost  = 0.0;
		$known = true;

		if ( 'embedding' === $usage['kind'] ) {
			$price = self::embedding_price( $provider, $model );
			if ( null === $price ) {
				$known = false;
			} else {
				$cost += $usage['tokens_in'] * $price / 1000000;
			}
		} elseif ( $usage['tokens_in'] > 0 || $usage['tokens_out'] > 0 ) {
			$info = Providers::model_info( $provider, $model );
			if ( isset( $info['price_known'] ) && ! $info['price_known'] && 0 === (int) $usage['images'] ) {
				$known = false;
			}
			$cached  = (int) $usage['tokens_cached'];
			$write   = (int) $usage['tokens_cache_write'];
			$plain   = max( 0, (int) $usage['tokens_in'] - $cached - $write );
			$c_share = self::cached_share( $table, $provider, $model );
			$w_share = isset( $table['write'][ $provider ] ) ? (float) $table['write'][ $provider ] : 1.0;
			$cost   += ( $plain + $cached * $c_share + $write * $w_share ) * (float) $info['price_in'] / 1000000;
			$cost   += (int) $usage['tokens_out'] * (float) $info['price_out'] / 1000000;
		}

		if ( $usage['images'] > 0 && 'text' !== $usage['kind'] && ! ( $usage['tokens_in'] > 0 && self::token_priced_image( $provider, $model ) ) ) {
			$price = isset( $table['image'][ $provider ][ $model ] ) ? (float) $table['image'][ $provider ][ $model ] : null;
			if ( null === $price ) {
				$known = false;
			} else {
				$cost += $usage['images'] * $price * ( isset( $usage['image_quality'] ) && 'hd' === $usage['image_quality'] ? 2 : 1 );
			}
		}
		// Videos have no list price yet: only a cost the provider reports (OpenRouter) is counted.
		if ( isset( $usage['videos'] ) && (int) $usage['videos'] > 0 ) {
			$known = false;
		}
		if ( $usage['characters'] > 0 ) {
			$price = isset( $table['tts'][ $provider ][ $model ] ) ? (float) $table['tts'][ $provider ][ $model ] : null;
			if ( null === $price ) {
				$known = false;
			} else {
				$cost += $usage['characters'] * $price / 1000000;
			}
		}
		if ( $usage['audio_seconds'] > 0 ) {
			$price = isset( $table['stt'][ $provider ][ $model ] ) ? (float) $table['stt'][ $provider ][ $model ] : null;
			if ( null === $price ) {
				$known = false;
			} else {
				$cost += $usage['audio_seconds'] / 60 * $price;
			}
		}
		if ( $usage['web_searches'] > 0 ) {
			if ( isset( $table['search'][ $provider ] ) ) {
				$cost += $usage['web_searches'] * (float) $table['search'][ $provider ];
			} else {
				$known = false;
			}
		}

		return array(
			'cost'        => round( $cost, 6 ),
			'price_known' => $known,
		);
	}

	/**
	 * Price per million tokens of an embedding model, or null.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return float|null
	 */
	public static function embedding_price( $provider, $model ) {
		$definition = Embeddings::get( $provider );
		if ( ! $definition || ! isset( $definition['models'][ $model ]['price'] ) ) {
			return null;
		}
		$price = (float) $definition['models'][ $model ]['price'];
		// A 0 price in the embeddings list means "not priced" (retired models), except locally.
		return $price > 0 || 'ollama' === $provider ? $price : null;
	}

	/**
	 * Image models whose answer carries token usage that is the bill (gpt-image family).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return bool
	 */
	private static function token_priced_image( $provider, $model ) {
		if ( 'openai' !== $provider || 0 !== strpos( (string) $model, 'gpt-image' ) ) {
			return false;
		}
		$info = Providers::model_info( $provider, $model );
		return ! isset( $info['price_known'] ) || (bool) $info['price_known'];
	}

	/**
	 * Share of the input price paid for cached input.
	 *
	 * @param array  $table    Price table.
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return float
	 */
	private static function cached_share( array $table, $provider, $model ) {
		$shares = isset( $table['cached'][ $provider ] ) ? (array) $table['cached'][ $provider ] : array();
		$best   = null;
		$length = -1;
		foreach ( $shares as $prefix => $share ) {
			$prefix = (string) $prefix;
			if ( ( '' === $prefix || 0 === strpos( (string) $model, $prefix ) ) && strlen( $prefix ) > $length ) {
				$best   = (float) $share;
				$length = strlen( $prefix );
			}
		}
		return null === $best ? 1.0 : $best;
	}

	/**
	 * Provider billing pages (shown next to every estimate).
	 *
	 * @return array<string,string>
	 */
	public static function billing_urls() {
		return array(
			'openai'     => 'https://platform.openai.com/usage',
			'claude'     => 'https://console.anthropic.com/settings/billing',
			'gemini'     => 'https://aistudio.google.com/usage',
			'openrouter' => 'https://openrouter.ai/activity',
			'grok'       => 'https://console.x.ai/',
			'mistral'    => 'https://admin.mistral.ai/organization/usage',
			'deepseek'   => 'https://platform.deepseek.com/usage',
		);
	}
}
