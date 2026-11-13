<?php
/**
 * Write with AI: one version per network from one source, in each
 * network's format and limits, with the brand voice and Business Context,
 * the cost shown first and the honesty check on every version.
 *
 * Core writes for one network at a time; the Master module turns on writing
 * for several networks in one go (qcld_wpbot_social/write_multi).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Writing;

use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\AI\Usage;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Writer.
 */
final class Writer {

	/**
	 * Most tokens one version may use.
	 */
	const MAX_TOKENS = 900;

	/**
	 * Test seam: callable( $feature, Request $request ) returning an object with
	 * text, cost, or a WP_Error. Null uses WPBot Core's AI_Service.
	 *
	 * @var callable|null
	 */
	private static $generator = null;

	/**
	 * Replace the AI call (tests only).
	 *
	 * @param callable|null $generator Generator.
	 * @return void
	 */
	public static function set_generator( $generator ) {
		self::$generator = $generator;
	}

	/**
	 * Whether AI writing can run (a provider is on, or the test seam is set).
	 *
	 * @return bool
	 */
	public static function ready() {
		return null !== self::$generator || '' !== self::model()['provider'];
	}

	/**
	 * Whether several networks may be written in one go (Master).
	 *
	 * @return bool
	 */
	public static function multi() {
		/**
		 * Whether Write with AI may write for several networks at once.
		 *
		 * @param bool $multi Default false (core: one network per write).
		 */
		return (bool) apply_filters( 'qcld_wpbot_social/write_multi', false );
	}

	/**
	 * Networks to write for, limited to known ones (one unless Master).
	 *
	 * @param array $networks Requested network ids.
	 * @return string[]
	 */
	public static function networks( array $networks ) {
		$known = array_keys( Network_Rules::defaults() );
		$out   = array_values( array_intersect( array_unique( array_map( 'sanitize_key', $networks ) ), $known ) );
		return self::multi() ? $out : array_slice( $out, 0, 1 );
	}

	/**
	 * What a write will cost, before it runs.
	 *
	 * @param array $input Writer input (kind, about, networks, goal, …).
	 * @return array{calls:int, cost:float, provider:string, model:string, budget:float, spent:float, ready:bool}|WP_Error
	 */
	public static function estimate( array $input ) {
		$source = Source::build( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$networks = self::networks( isset( $input['networks'] ) ? (array) $input['networks'] : array() );
		$model    = self::model();
		$cost     = 0.0;
		foreach ( $networks as $network ) {
			$request = self::request( $network, $source, $input );
			$in      = (int) ceil( ( strlen( $request->system ) + strlen( $request->messages[0]['content'] ) ) / 4 );
			$cost   += '' !== $model['provider'] && class_exists( Usage::class ) ? Usage::estimate( $model['provider'], $model['model'], $in, (int) ( self::MAX_TOKENS * 0.6 ) ) : 0.0;
		}
		return array(
			'calls'    => count( $networks ),
			'cost'     => round( $cost, 4 ),
			'provider' => $model['provider'],
			'model'    => $model['model'],
			'budget'   => (float) Writing_Settings::get( 'budget' ),
			'spent'    => class_exists( Usage::class ) ? round( Usage::month_cost( Writing_Settings::MODULE ), 4 ) : 0.0,
			'ready'    => '' !== $model['provider'],
		);
	}

	/**
	 * Write the versions.
	 *
	 * @param array $input Writer input: kind, about, post_id, product_id, url, event, link,
	 *                     networks, goal, voice.
	 * @return array{source:array, versions:array<string,array>, cost:float}|WP_Error
	 */
	public static function write( array $input ) {
		$source = Source::build( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$networks = self::networks( isset( $input['networks'] ) ? (array) $input['networks'] : array() );
		if ( array() === $networks ) {
			return new WP_Error( 'qcld_wpbot_social_networks', __( 'Choose a network to write for.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$versions = array();
		$total    = 0.0;
		foreach ( $networks as $network ) {
			if ( 'blank' === $source['kind'] ) {
				$versions[ $network ] = self::version( $network, '', array(), $source );
				continue;
			}
			$result = self::write_one( $network, $source, $input );
			if ( is_wp_error( $result ) ) {
				// The budget, a missing key or a refusal stop the rest: they would fail the same way.
				if ( array() === $versions ) {
					return $result;
				}
				$versions[ $network ] = array(
					'network' => $network,
					'error'   => $result->get_error_message(),
				);
				break;
			}
			$total               += $result['cost'];
			$versions[ $network ] = $result;
		}
		return array(
			'source'   => array(
				'kind'     => $source['kind'],
				'title'    => $source['title'],
				'link'     => $source['link'],
				'image_id' => $source['image_id'],
			),
			'versions' => $versions,
			'cost'     => round( $total, 4 ),
		);
	}

	/**
	 * Write (or rewrite) one network's version.
	 *
	 * @param string $network Network id.
	 * @param array  $source  Source::build() result.
	 * @param array  $input   Writer input.
	 * @return array|WP_Error Version.
	 */
	public static function write_one( $network, array $source, array $input ) {
		$request = self::request( $network, $source, $input );
		$result  = self::generate( 'write_' . $network, $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data = json_decode( self::strip_fences( (string) $result->text ), true );
		if ( ! is_array( $data ) || ! isset( $data['text'] ) ) {
			$data = array( 'text' => trim( (string) $result->text ) );
		}
		$version         = self::version( $network, (string) $data['text'], $data, $source );
		$version['cost'] = isset( $result->cost ) ? (float) $result->cost : 0.0;
		return $version;
	}

	/**
	 * Shape a version and run the checks.
	 *
	 * @param string $network Network id.
	 * @param string $text    Text.
	 * @param array  $data    AI output (title, slides, posts, claims).
	 * @param array  $source  Source.
	 * @return array
	 */
	public static function version( $network, $text, array $data, array $source ) {
		$rule  = Network_Rules::get( $network );
		$text  = trim( sanitize_textarea_field( $text ) );
		$flags = array();
		if ( '' !== $text && Writing_Settings::get( 'honesty' ) ) {
			$support = $source['text'] . "\n" . $source['link'] . "\n" . self::context();
			$flags   = Honesty::check( $text, $support );
			if ( ! Honesty::on_topic( $text, $source['text'] ) ) {
				array_unshift( $flags, __( 'This version may not be about your source. Rewrite it or check it before using it.', 'chatbot' ) );
			}
			foreach ( isset( $data['claims'] ) ? array_slice( (array) $data['claims'], 0, 5 ) : array() as $claim ) {
				$claim = trim( sanitize_text_field( (string) $claim ) );
				if ( '' !== $claim ) {
					/* translators: %s: a claim the writer could not support. */
					$flags[] = sprintf( __( 'Not in the source: %s', 'chatbot' ), $claim );
				}
			}
		}
		$max = $rule ? (int) $rule['max'] : 0;
		return array(
			'network'   => $network,
			'label'     => $rule ? $rule['label'] : $network,
			'manual'    => $rule ? (bool) $rule['manual'] : false,
			'text'      => $text,
			'title'     => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'slides'    => isset( $data['slides'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) $data['slides'], 0, 10 ) ) ) ) : array(),
			'posts'     => isset( $data['posts'] ) ? array_values( array_filter( array_map( 'sanitize_textarea_field', array_slice( (array) $data['posts'], 0, 10 ) ) ) ) : array(),
			'variants'  => isset( $data['variants'] ) ? array_values( array_filter( array_map( 'sanitize_textarea_field', array_slice( (array) $data['variants'], 0, 3 ) ) ) ) : array(),
			'length'    => mb_strlen( $text ),
			'max'       => $max,
			'too_long'  => $max > 0 && mb_strlen( $text ) > $max,
			'technique' => $rule ? Network_Rules::techniques()[ $rule['technique'] ] : '',
			'flags'     => array_values( array_unique( $flags ) ),
			'cost'      => 0.0,
		);
	}

	/**
	 * Build the AI request for one network.
	 *
	 * @param string $network Network id.
	 * @param array  $source  Source.
	 * @param array  $input   Writer input.
	 * @return Request
	 */
	public static function request( $network, array $source, array $input ) {
		$rule     = Network_Rules::get( $network );
		$settings = Writing_Settings::all();
		$voice    = isset( $input['voice'] ) && '' !== trim( (string) $input['voice'] ) ? sanitize_text_field( (string) $input['voice'] ) : (string) $settings['voice'];
		$brand    = isset( $input['brand_id'] ) ? absint( $input['brand_id'] ) : 0;
		if ( $brand && ( ! isset( $input['voice'] ) || '' === trim( (string) $input['voice'] ) ) ) {
			/**
			 * A brand's voice (the Master module answers from its Brands).
			 *
			 * @param string $voice    Default ''.
			 * @param int    $brand_id Brand id.
			 */
			$brand_voice = trim( (string) apply_filters( 'qcld_wpbot_social/brand_voice', '', $brand ) );
			$voice       = '' !== $brand_voice ? sanitize_text_field( $brand_voice ) : $voice;
		}
		/**
		 * How many alternative versions to ask for in the same call (Master: variants).
		 *
		 * @param int    $variants Default 0.
		 * @param string $network  Network id.
		 */
		$variants = min( 3, max( 0, (int) apply_filters( 'qcld_wpbot_social/write_variants', 0, $network ) ) );
		$goal     = isset( $input['goal'] ) ? sanitize_key( (string) $input['goal'] ) : '';
		$hashtags = array(
			'fits' => 'Use hashtags only as this network usually does.',
			'none' => 'No hashtags.',
			'few'  => 'At most 2 hashtags.',
			'many' => 'Up to 8 relevant hashtags where the network allows them.',
		);
		$emojis   = array(
			'none' => 'No emojis.',
			'few'  => 'At most one or two emojis.',
			'many' => 'Emojis are welcome where they fit.',
		);
		$system   = implode(
			"\n",
			array_filter(
				array(
					'You write one social media post for a business. Write in the language of the source.',
					'The post is about the SOURCE the user gives (its topic, news, product or offer). Never turn it into a general advert for the business.',
					'Use only facts from the source and the business facts below. Never invent prices, numbers, dates, codes, names, quotes or claims. If something is missing, leave it out.',
					'Business facts (Business Context, background only: use a fact only when it fits this post\'s topic, such as the business name, voice or where it is):',
					self::context(),
					'' !== $voice ? 'Brand voice: ' . $voice . '.' : 'Brand voice: clear, warm and plain.',
					$hashtags[ $settings['hashtags'] ] ?? $hashtags['fits'],
					$emojis[ $settings['emojis'] ] ?? $emojis['few'],
					'Reply with JSON only: {"text": "the post", "title": "a title if the network uses one, else empty", "slides": [], "posts": [], "variants": [], "claims": ["any statement in your text that is not directly in the source or business facts"]}.',
					$variants > 0 ? 'Also put ' . $variants . ' alternative versions of "text" in "variants", each with a different opening.' : '',
				)
			)
		);
		$lines    = array(
			'Write the post about this source:',
			$source['text'],
			'---',
			'Network: ' . ( $rule ? $rule['label'] : $network ),
			$rule ? 'Rules: ' . $rule['rules'] : '',
			$rule ? 'Technique: ' . Network_Rules::technique_prompt( $rule['technique'] ) : '',
			$rule && $rule['max'] > 0 ? 'Keep the text under ' . (int) $rule['max'] . ' characters.' : '',
			'' !== $goal ? 'Goal: ' . $goal . '.' : '',
			'' !== $source['link'] ? 'Link to use (exactly as given, where the network allows a link): ' . $source['link'] : 'There is no link.',
		);
		return Request::make(
			$system,
			implode( "\n", array_filter( $lines ) ),
			array(
				'max_tokens' => self::MAX_TOKENS,
				'json'       => true,
				'mode'       => 'creative',
			)
		);
	}

	/**
	 * Business Context text from WPBot Core ('' when not set up).
	 *
	 * @return string
	 */
	public static function context() {
		return function_exists( 'qcld_wpbot_core_context_text' ) ? (string) qcld_wpbot_core_context_text() : '';
	}

	/**
	 * Provider and model Social will call first.
	 *
	 * @return array{provider:string, model:string}
	 */
	public static function model() {
		if ( ! class_exists( Keys::class ) ) {
			return array(
				'provider' => '',
				'model'    => '',
			);
		}
		$order    = Keys::call_order( Writing_Settings::MODULE );
		$provider = isset( $order[0] ) ? (string) $order[0] : '';
		$model    = '';
		if ( '' !== $provider ) {
			$view  = Keys::public_view( Writing_Settings::MODULE );
			$model = ! empty( $view['providers'][ $provider ]['model'] ) ? (string) $view['providers'][ $provider ]['model'] : Providers::default_text_model( $provider );
		}
		return array(
			'provider' => $provider,
			'model'    => $model,
		);
	}

	/**
	 * Call the AI.
	 *
	 * @param string  $feature Feature id.
	 * @param Request $request Request.
	 * @return object|WP_Error
	 */
	private static function generate( $feature, Request $request ) {
		if ( null !== self::$generator ) {
			return call_user_func( self::$generator, $feature, $request );
		}
		if ( ! class_exists( AI_Service::class ) ) {
			return new WP_Error( 'qcld_wpbot_social_ai', __( 'AI writing needs WPBot Core.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return AI_Service::generate( Writing_Settings::MODULE, $feature, $request, array( 'cache' => false ) );
	}

	/**
	 * Remove ```json fences some models add.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function strip_fences( $text ) {
		return trim( (string) preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( $text ) ) );
	}
}
