<?php
/**
 * The models an account can use, live from each provider, with current prices.
 *
 * - Recommended models come from the catalogue (Providers, 'pick'): best value (the
 *   default), best quality and cheapest. They ship with each release.
 * - All models: the provider's own model list, read with the saved key on the server,
 *   kept for a day (an hour after an error). Only chat models are kept: embedding, speech,
 *   image, moderation, transcription and API-only models are left out, and so are dated
 *   copies when the plain name exists.
 * - Prices: OpenRouter's public model list (no key) gives current list prices for the
 *   models of every provider here. It is read at most once a week, from admin screens only,
 *   and only fills models the catalogue has no price for. Filter
 *   qcld_wpbot_core/model_price_feed to turn it off.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Live model lists and the price feed.
 */
final class Model_Catalog {

	const PICK_VALUE   = 'value';
	const PICK_QUALITY = 'quality';
	const PICK_CHEAP   = 'cheap';

	/**
	 * Cached model lists: transient prefix + md5( provider | base URL | key ).
	 */
	const LIST_PREFIX = 'qcld_wpbot_core_models_';

	/**
	 * Price feed option (autoload off): [ fetched => time, prices => provider => model => [ in, out ] ].
	 */
	const PRICES_OPTION = 'qcld_wpbot_core_model_prices';

	/**
	 * The public price list.
	 */
	const FEED_URL = 'https://openrouter.ai/api/v1/models';

	/**
	 * OpenRouter's vendor prefixes and the provider each belongs to.
	 */
	const FEED_VENDORS = array(
		'openai'    => 'openai',
		'anthropic' => 'claude',
		'google'    => 'gemini',
		'x-ai'      => 'grok',
		'mistralai' => 'mistral',
		'deepseek'  => 'deepseek',
	);

	/**
	 * Prices read in this request.
	 *
	 * @var array|null
	 */
	private static $prices = null;

	/**
	 * A provider's recommended models in order: value, quality, cheap.
	 *
	 * @param string $provider Provider id.
	 * @return array<string,string> model => pick.
	 */
	public static function picks( $provider ) {
		$definition = Providers::get( $provider );
		$out        = array();
		foreach ( array( self::PICK_VALUE, self::PICK_QUALITY, self::PICK_CHEAP ) as $pick ) {
			foreach ( $definition ? (array) $definition['models'] : array() as $model => $info ) {
				if ( isset( $info['pick'] ) && $pick === $info['pick'] && ! empty( $info['text'] ) ) {
					$out[ (string) $model ] = $pick;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * A pick's label.
	 *
	 * @param string $pick Pick.
	 * @return string
	 */
	public static function pick_label( $pick ) {
		$labels = array(
			self::PICK_VALUE   => __( 'Best value', 'chatbot' ),
			self::PICK_QUALITY => __( 'Best quality', 'chatbot' ),
			self::PICK_CHEAP   => __( 'Cheapest', 'chatbot' ),
		);
		return isset( $labels[ $pick ] ) ? $labels[ $pick ] : '';
	}

	/**
	 * The chat models an account offers, newest first, from the provider (cached).
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key.
	 * @param string $base_url Base URL for providers with their own server.
	 * @param bool   $refresh  Ask the provider again now.
	 * @return array{models:string[],error:string,fetched:int}
	 */
	public static function available( $provider, $key, $base_url = '', $refresh = false ) {
		$cache = self::cache_name( $provider, $key, $base_url );
		if ( ! $refresh ) {
			$cached = get_transient( $cache );
			if ( is_array( $cached ) && isset( $cached['models'] ) ) {
				return $cached;
			}
		}
		$ids = Provider_Client::list_models( $provider, (string) $key, (string) $base_url );
		if ( is_wp_error( $ids ) ) {
			$result = array(
				'models'  => array(),
				'error'   => (string) $ids->get_error_message(),
				'fetched' => time(),
			);
			set_transient( $cache, $result, HOUR_IN_SECONDS );
			return $result;
		}
		$result = array(
			'models'  => self::chat_models( $provider, (array) $ids ),
			'error'   => '',
			'fetched' => time(),
		);
		set_transient( $cache, $result, DAY_IN_SECONDS );
		return $result;
	}

	/**
	 * The cached list, without asking the provider (null when there is none).
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key.
	 * @param string $base_url Base URL.
	 * @return array|null
	 */
	public static function cached( $provider, $key, $base_url = '' ) {
		$cached = get_transient( self::cache_name( $provider, $key, $base_url ) );
		return is_array( $cached ) && isset( $cached['models'] ) ? $cached : null;
	}

	/**
	 * Whether the provider still offers a model: true, false, or null when unknown
	 * (no cached list, or the list could not be read). Never asks the provider.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @param string $key      API key.
	 * @param string $base_url Base URL.
	 * @return bool|null
	 */
	public static function offered( $provider, $model, $key, $base_url = '' ) {
		$cached = self::cached( $provider, $key, $base_url );
		if ( null === $cached || '' !== $cached['error'] || ! $cached['models'] || '' === (string) $model ) {
			return null;
		}
		if ( in_array( (string) $model, $cached['models'], true ) ) {
			return true;
		}
		// Dated copies and fine-tunes are left out of the list but still offered.
		return self::is_variant( (string) $model, $cached['models'] ) ? null : false;
	}

	/**
	 * Forget a provider's cached list.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key.
	 * @param string $base_url Base URL.
	 * @return void
	 */
	public static function forget( $provider, $key, $base_url = '' ) {
		delete_transient( self::cache_name( $provider, $key, $base_url ) );
	}

	/**
	 * Keep chat models only, drop dated copies of listed models, newest first.
	 *
	 * @param string   $provider Provider id.
	 * @param string[] $ids      Model ids from the provider.
	 * @return string[]
	 */
	public static function chat_models( $provider, array $ids ) {
		$skip = '/(embed|tts|whisper|transcri|audio|realtime|moderation|image|dall-e|sora|ocr|voxtral|-live|^live|translate|search-api|codex|computer-use|vibe-cli|code-fim|-fim|^veo|imagen|lyria|^aqa|davinci|curie|babbage|^ada|instruct$|nano-banana|leanstral|safeguard|deep-research|:ft-|^ft:|rerank|^gemini-omni)/i';
		if ( 'openai' === $provider ) {
			// Pro models answer only through the Responses API in background mode.
			$skip = substr( $skip, 0, -3 ) . '|-pro$|-pro-\d{4}-\d{2}-\d{2}$|^gpt-live)/i';
		}
		/**
		 * Pattern of model ids that are not chat models.
		 *
		 * @param string $skip     Regular expression.
		 * @param string $provider Provider id.
		 */
		$skip = (string) apply_filters( 'qcld_wpbot_core/non_chat_models', $skip, $provider );
		$keep = array();
		foreach ( $ids as $id ) {
			$id = (string) $id;
			if ( '' !== $id && ! preg_match( $skip, $id ) ) {
				$keep[] = $id;
			}
		}
		$keep = array_values( array_unique( $keep ) );
		$out  = array();
		foreach ( $keep as $id ) {
			$base = self::undated( $id );
			if ( $base !== $id && ( in_array( $base, $keep, true ) || in_array( $base . '-latest', $keep, true ) ) ) {
				continue;
			}
			$out[] = $id;
		}
		usort( $out, array( __CLASS__, 'newest_first' ) );
		return $out;
	}

	/**
	 * Sort order for model ids: highest version number first (gpt-6.1 before gpt-6 before o3),
	 * ids without a number last; ties by name, newest-looking first.
	 *
	 * @param string $a Model id.
	 * @param string $b Model id.
	 * @return int
	 */
	public static function newest_first( $a, $b ) {
		$version = function ( $id ) {
			// Vendor prefix and model sizes (8b, 70b) are not versions.
			$id = (string) preg_replace( array( '#^[^/]+/#', '/-\d+(\.\d+)?b\b/i' ), '', (string) $id );
			return preg_match( '/(\d+(?:\.\d+)?)/', $id, $m ) ? (float) $m[1] : -1.0;
		};
		$va      = $version( $a );
		$vb      = $version( $b );
		if ( $va !== $vb ) {
			return $va < $vb ? 1 : -1;
		}
		return strnatcasecmp( (string) $b, (string) $a );
	}

	/**
	 * Current list price of a model from the feed, or null.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return array{0:float,1:float}|null [ in, out ] USD per million tokens.
	 */
	public static function price( $provider, $model ) {
		if ( null === self::$prices ) {
			$stored       = get_option( self::PRICES_OPTION, array() );
			self::$prices = is_array( $stored ) && isset( $stored['prices'] ) ? (array) $stored['prices'] : array();
		}
		$model = (string) $model;
		if ( isset( self::$prices[ $provider ][ $model ] ) ) {
			return self::$prices[ $provider ][ $model ];
		}
		$base = self::undated( $model );
		return isset( self::$prices[ $provider ][ $base ] ) ? self::$prices[ $provider ][ $base ] : null;
	}

	/**
	 * Read the price feed when it is older than a week (admin screens call this).
	 *
	 * @param bool $force Read it now.
	 * @return bool Whether prices were updated.
	 */
	public static function maybe_refresh_prices( $force = false ) {
		/**
		 * Whether WPBot Core reads current model prices from OpenRouter's public model list.
		 *
		 * @param bool $enabled Enabled.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/model_price_feed', true ) ) {
			return false;
		}
		$stored = get_option( self::PRICES_OPTION, array() );
		$last   = is_array( $stored ) && isset( $stored['fetched'] ) ? (int) $stored['fetched'] : 0;
		if ( ! $force && $last > time() - WEEK_IN_SECONDS ) {
			return false;
		}
		// Note the attempt first, so a slow or failing feed is not asked again on every screen.
		$stored            = is_array( $stored ) ? $stored : array();
		$stored['fetched'] = time();
		update_option( self::PRICES_OPTION, $stored, false );
		$raw = wp_remote_get( self::FEED_URL, array( 'timeout' => 10 ) );
		if ( is_wp_error( $raw ) || 200 !== (int) wp_remote_retrieve_response_code( $raw ) ) {
			return false;
		}
		$prices = self::parse_feed( (string) wp_remote_retrieve_body( $raw ) );
		if ( ! $prices ) {
			return false;
		}
		update_option(
			self::PRICES_OPTION,
			array(
				'fetched' => time(),
				'prices'  => $prices,
			),
			false
		);
		self::$prices = $prices;
		return true;
	}

	/**
	 * Turn OpenRouter's model list into provider => model => [ in, out ] per million tokens.
	 *
	 * @param string $json Feed body.
	 * @return array
	 */
	public static function parse_feed( $json ) {
		$data = json_decode( $json, true );
		$out  = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : array() as $item ) {
			if ( empty( $item['id'] ) || false !== strpos( (string) $item['id'], ':' ) || ! isset( $item['pricing']['prompt'], $item['pricing']['completion'] ) ) {
				continue;
			}
			$in        = (float) $item['pricing']['prompt'] * 1000000;
			$out_price = (float) $item['pricing']['completion'] * 1000000;
			if ( $in < 0 || $out_price < 0 || ( 0.0 === $in && 0.0 === $out_price ) ) {
				continue;
			}
			$id                       = (string) $item['id'];
			$price                    = array( round( $in, 4 ), round( $out_price, 4 ) );
			$out['openrouter'][ $id ] = $price;
			$parts                    = explode( '/', $id, 2 );
			if ( 2 === count( $parts ) && isset( self::FEED_VENDORS[ $parts[0] ] ) ) {
				$provider = self::FEED_VENDORS[ $parts[0] ];
				// Anthropic's own ids use dashes where OpenRouter uses dots (claude-opus-5.5 > claude-opus-5-5).
				$model                      = 'claude' === $provider ? str_replace( '.', '-', $parts[1] ) : $parts[1];
				$out[ $provider ][ $model ] = $price;
			}
		}
		return $out;
	}

	/**
	 * A model id without a date or version-number suffix (gpt-5.5-2026-04-23 > gpt-5.5,
	 * mistral-small-2603 > mistral-small).
	 *
	 * @param string $id Model id.
	 * @return string
	 */
	public static function undated( $id ) {
		return (string) preg_replace( '/-(\d{4}-\d{2}-\d{2}|\d{8}|\d{4})$/', '', (string) $id );
	}

	/**
	 * Whether a model is a dated copy or fine-tune of a listed model.
	 *
	 * @param string   $model  Model id.
	 * @param string[] $listed Listed ids.
	 * @return bool
	 */
	private static function is_variant( $model, array $listed ) {
		if ( 0 === strpos( $model, 'ft:' ) ) {
			return true;
		}
		$base = self::undated( $model );
		return $base !== $model && ( in_array( $base, $listed, true ) || in_array( $base . '-latest', $listed, true ) );
	}

	/**
	 * Transient name for a provider's list.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key.
	 * @param string $base_url Base URL.
	 * @return string
	 */
	private static function cache_name( $provider, $key, $base_url ) {
		return self::LIST_PREFIX . md5( $provider . '|' . $base_url . '|' . $key );
	}
}
