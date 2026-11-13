<?php
/**
 * Backup provider, cheaper model for short questions and the answer cache (AI Settings >
 * Providers and Limits and cost; moved from WPBot Pro's routing pack in round R2, so WPBot
 * Free has them too). The option names are the ones the Pro pack used, so saved settings carry
 * over.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Routing settings and hooks.
 */
final class Routing {

	/**
	 * Option: cheaper model per provider ( provider => model ).
	 */
	const SHORT_MODEL = 'qcld_wpbot_chat_pro_short_model';

	/**
	 * Option: most words in a short question.
	 */
	const SHORT_WORDS = 'qcld_wpbot_chat_pro_short_words';

	/**
	 * Option: backup provider id.
	 */
	const FALLBACK = 'qcld_wpbot_chat_pro_fallback';

	/**
	 * Option: answer cache on ('1') or off.
	 */
	const CACHE = 'qcld_wpbot_chat_pro_cache';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/route_model', array( __CLASS__, 'route' ), 10, 3 );
		add_filter( 'qcld_wpbot_core/fallback_provider', array( __CLASS__, 'fallback' ), 10, 4 );
		add_filter( 'qcld_wpbot_chat/answer_cache', array( __CLASS__, 'cache' ) );
	}

	/**
	 * The backup provider ('' = none).
	 *
	 * @return string
	 */
	public static function backup() {
		$backup = sanitize_key( (string) get_option( self::FALLBACK, '' ) );
		return '' !== $backup && Provider_Settings::answering_provider() !== $backup && in_array( $backup, array_keys( Provider_Settings::map() ), true ) ? $backup : '';
	}

	/**
	 * Cheaper models ( provider => model ).
	 *
	 * @return array<string,string>
	 */
	public static function short_models() {
		$out = array();
		foreach ( (array) get_option( self::SHORT_MODEL, array() ) as $provider => $model ) {
			$model = sanitize_text_field( (string) $model );
			if ( '' !== $model ) {
				$out[ sanitize_key( (string) $provider ) ] = $model;
			}
		}
		return $out;
	}

	/**
	 * Most words in a short question.
	 *
	 * @return int
	 */
	public static function short_words() {
		return max( 1, min( 50, (int) get_option( self::SHORT_WORDS, 8 ) ) );
	}

	/**
	 * Whether the answer cache is on.
	 *
	 * @return bool
	 */
	public static function cache_on() {
		return '1' === (string) get_option( self::CACHE, '0' );
	}

	/**
	 * Cheaper model for short chatbot questions.
	 *
	 * @param string $model    Model.
	 * @param string $provider Provider.
	 * @param array  $context  module, feature, question.
	 * @return string
	 */
	public static function route( $model, $provider, $context ) {
		$context = (array) $context;
		if ( ! isset( $context['module'] ) || 'chatbot' !== $context['module'] || empty( $context['question'] ) ) {
			return $model;
		}
		$short = self::short_models();
		if ( empty( $short[ $provider ] ) ) {
			return $model;
		}
		$words = str_word_count( wp_strip_all_tags( (string) $context['question'] ) );
		return $words > 0 && $words <= self::short_words() ? $short[ $provider ] : $model;
	}

	/**
	 * The backup provider for chatbot answers.
	 *
	 * @param string $fallback Default ''.
	 * @param string $provider Provider that failed.
	 * @param mixed  $error    Error.
	 * @param array  $context  module, feature.
	 * @return string
	 */
	public static function fallback( $fallback, $provider, $error, $context ) {
		$context = (array) $context;
		if ( '' !== (string) $fallback || ! isset( $context['module'] ) || 'chatbot' !== $context['module'] ) {
			return $fallback;
		}
		$other = sanitize_key( (string) get_option( self::FALLBACK, '' ) );
		return $other !== $provider ? $other : '';
	}

	/**
	 * Answer cache: visitors who are not logged in, without personal context.
	 *
	 * @param bool $allowed Default.
	 * @return bool
	 */
	public static function cache( $allowed ) {
		if ( ! self::cache_on() || is_user_logged_in() ) {
			return (bool) $allowed;
		}
		/**
		 * Whether this answer uses personal context and must not be cached (WPBot Pro's customer
		 * context says yes).
		 *
		 * @param bool $personal Default false.
		 */
		return ! apply_filters( 'qcld_wpbot_chat/answer_personal', false );
	}

	/**
	 * Save the routing settings.
	 *
	 * @param array $values backup, short_words, short_models ( provider => model ), cache.
	 * @return void
	 */
	public static function save( array $values ) {
		if ( array_key_exists( 'backup', $values ) ) {
			$backup = sanitize_key( (string) $values['backup'] );
			update_option( self::FALLBACK, in_array( $backup, array_keys( Provider_Settings::map() ), true ) ? $backup : '', false );
		}
		if ( array_key_exists( 'short_words', $values ) ) {
			update_option( self::SHORT_WORDS, max( 1, min( 50, (int) $values['short_words'] ) ), false );
		}
		if ( array_key_exists( 'short_models', $values ) ) {
			$clean = array();
			foreach ( (array) $values['short_models'] as $provider => $model ) {
				$provider = sanitize_key( (string) $provider );
				$model    = sanitize_text_field( (string) $model );
				if ( '' !== $model && in_array( $provider, array_keys( Provider_Settings::map() ), true ) ) {
					$clean[ $provider ] = $model;
				}
			}
			update_option( self::SHORT_MODEL, $clean, false );
		}
		if ( array_key_exists( 'cache', $values ) ) {
			update_option( self::CACHE, ! empty( $values['cache'] ) ? '1' : '0', false );
		}
	}
}
