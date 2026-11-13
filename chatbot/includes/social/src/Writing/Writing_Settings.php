<?php
/**
 * Settings › Writing (screen 22): budget, cost first, voice, honesty check,
 * hashtags, emojis, saved hashtag sets and per-network techniques.
 *
 * The monthly budget is stored in WPBot Core (budgets.social), which stops
 * every AI call for the "social" module once it is reached.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Writing;

use QuantumCloud\WPBotCore\Settings as Core_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Writing settings.
 */
final class Writing_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_social_writing';

	/**
	 * Module id for WPBot Core keys, usage and budget.
	 */
	const MODULE = 'social';

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'show_cost'    => true,
			'voice'        => '',
			'honesty'      => true,
			'hashtags'     => 'fits',
			'emojis'       => 'few',
			'hashtag_sets' => array(),
			'networks'     => array(),
			'image_model'  => 'auto',
		);
	}

	/**
	 * All settings, with the budget from WPBot Core.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$saved         = get_option( self::OPTION, array() );
		$all           = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		$all['budget'] = class_exists( Core_Settings::class ) ? Core_Settings::budget( self::MODULE ) : 0.0;
		return $all;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Save changes.
	 *
	 * @param array $input Changes.
	 * @return array<string,mixed> All settings.
	 */
	public static function update( array $input ) {
		$clean = self::sanitize( $input );
		if ( array_key_exists( 'budget', $clean ) ) {
			if ( class_exists( Core_Settings::class ) ) {
				Core_Settings::update( array( 'budgets' => array( self::MODULE => $clean['budget'] ) ) );
			}
			unset( $clean['budget'] );
		}
		$saved = get_option( self::OPTION, array() );
		update_option( self::OPTION, array_merge( is_array( $saved ) ? $saved : array(), $clean ), false );
		return self::all();
	}

	/**
	 * Sanitize known keys.
	 *
	 * @param array $input Input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) {
		$out = array();
		foreach ( array( 'show_cost', 'honesty' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$out[ $flag ] = filter_var( $input[ $flag ], FILTER_VALIDATE_BOOLEAN );
			}
		}
		if ( isset( $input['budget'] ) ) {
			$out['budget'] = max( 0, min( 10000, round( (float) $input['budget'], 2 ) ) );
		}
		if ( isset( $input['voice'] ) ) {
			$out['voice'] = substr( sanitize_text_field( (string) $input['voice'] ), 0, 200 );
		}
		if ( isset( $input['hashtags'] ) ) {
			$out['hashtags'] = in_array( $input['hashtags'], array( 'fits', 'none', 'few', 'many' ), true ) ? $input['hashtags'] : 'fits';
		}
		if ( isset( $input['emojis'] ) ) {
			$out['emojis'] = in_array( $input['emojis'], array( 'none', 'few', 'many' ), true ) ? $input['emojis'] : 'few';
		}
		if ( isset( $input['image_model'] ) ) {
			$out['image_model'] = in_array( $input['image_model'], array( 'auto', 'studio', 'off' ), true ) ? $input['image_model'] : 'auto';
		}
		if ( isset( $input['hashtag_sets'] ) && is_array( $input['hashtag_sets'] ) ) {
			$sets = array();
			foreach ( array_slice( $input['hashtag_sets'], 0, 30 ) as $set ) {
				$name = isset( $set['name'] ) ? substr( sanitize_text_field( (string) $set['name'] ), 0, 40 ) : '';
				$tags = array();
				foreach ( preg_split( '/[\s,]+/', isset( $set['tags'] ) ? (string) $set['tags'] : '' ) as $tag ) {
					$tag = ltrim( (string) $tag, '#' );
					if ( preg_match( '/^[\p{L}\p{N}_]{1,60}$/u', $tag ) ) {
						$tags[] = '#' . $tag;
					}
				}
				if ( '' !== $name && array() !== $tags ) {
					$sets[] = array(
						'name' => $name,
						'tags' => array_values( array_unique( array_slice( $tags, 0, 30 ) ) ),
					);
				}
			}
			$out['hashtag_sets'] = $sets;
		}
		if ( isset( $input['networks'] ) && is_array( $input['networks'] ) ) {
			$known      = array_keys( Network_Rules::defaults() );
			$techniques = array_keys( Network_Rules::techniques() );
			$nets       = array();
			foreach ( $input['networks'] as $network => $choice ) {
				$network = sanitize_key( (string) $network );
				if ( in_array( $network, $known, true ) && isset( $choice['technique'] ) && in_array( $choice['technique'], $techniques, true ) ) {
					$nets[ $network ] = array( 'technique' => $choice['technique'] );
				}
			}
			$out['networks'] = $nets;
		}
		return $out;
	}
}
