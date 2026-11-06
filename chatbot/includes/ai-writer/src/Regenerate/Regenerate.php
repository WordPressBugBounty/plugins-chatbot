<?php
/**
 * Regenerate (CR-14): what can be written again and what it costs. Kinds
 * map to a rough size so the button can show an estimate before any AI
 * call; modules add kinds with qcld_wpbot_writer/regenerate_kinds.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Regenerate;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;

/**
 * Regenerate service.
 */
final class Regenerate {

	/**
	 * Kinds of text that can be regenerated: label, tokens in and out of one run.
	 *
	 * @return array[]
	 */
	public static function kinds() {
		$kinds = array(
			'titles'     => array( __( 'Titles', 'chatbot' ), 2500, 250 ),
			'excerpt'    => array( __( 'Excerpt', 'chatbot' ), 2500, 200 ),
			'meta'       => array( __( 'Search title and description', 'chatbot' ), 2500, 200 ),
			'faq'        => array( __( 'FAQ', 'chatbot' ), 3500, 900 ),
			'summary'    => array( __( 'Summary', 'chatbot' ), 3500, 400 ),
			'variations' => array( __( 'Variations', 'chatbot' ), 3000, 600 ),
			'transform'  => array( __( 'Rewrite', 'chatbot' ), 2500, 700 ),
			'write-here' => array( __( 'Written text', 'chatbot' ), 3500, 1200 ),
			'quick'      => array( __( 'Quick write', 'chatbot' ), 2500, 900 ),
			'product'    => array( __( 'Product copy', 'chatbot' ), 3500, 1000 ),
			'section'    => array( __( 'Section', 'chatbot' ), 4500, 900 ),
			'outline'    => array( __( 'Outline', 'chatbot' ), 3000, 900 ),
			'voice'      => array( __( 'Voice profile', 'chatbot' ), 6000, 900 ),
			'voice-try'  => array( __( 'Voice sample', 'chatbot' ), 1500, 300 ),
			// CR-15: one page or document read for the Persuasion Kit or Customer words.
			'import'     => array( __( 'Import from your content', 'chatbot' ), 9000, 1500 ),
			// CR-16: facts gathered from the owner's material for a format.
			'gather'     => array( __( 'Real data for a format', 'chatbot' ), 12000, 1800 ),
		);
		/**
		 * Kinds of text that can be regenerated: id => [ label, tokens in, tokens out ].
		 *
		 * @param array[] $kinds Kinds.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/regenerate_kinds', $kinds );
	}

	/**
	 * Cost estimate of one regeneration.
	 *
	 * @param string $kind  Kind.
	 * @param int    $count Pieces (several for batch runs).
	 * @return array usd, model, confirm (above the owner's threshold), threshold.
	 */
	public static function estimate( $kind, $count = 1 ) {
		$kinds    = self::kinds();
		$size     = isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : array( '', 3000, 800 );
		$order    = Keys::call_order( Module::ID );
		$provider = isset( $order[0] ) ? $order[0] : '';
		$model    = '';
		$usd      = 0.0;
		if ( '' !== $provider ) {
			$set   = Keys::get_set( Module::ID );
			$model = ! empty( $set['providers'][ $provider ]['model'] ) ? $set['providers'][ $provider ]['model'] : Providers::default_text_model( $provider );
			$info  = Providers::model_info( $provider, $model );
			$usd   = max( 1, (int) $count ) * ( (int) $size[1] * $info['price_in'] + (int) $size[2] * $info['price_out'] ) / 1000000;
		}
		$threshold = self::threshold();
		return array(
			'usd'       => round( $usd, 4 ),
			'model'     => $model,
			'threshold' => $threshold,
			'confirm'   => $usd > $threshold,
		);
	}

	/**
	 * The owner's threshold for asking before a regeneration (US dollars).
	 *
	 * @return float
	 */
	public static function threshold() {
		return max( 0.0, (float) Settings::get( 'regenerate_confirm_usd' ) );
	}

	/**
	 * What the Regenerate form needs in the browser.
	 *
	 * @return array voices: [ value, label ] choices for the Voice option (WPBot Pro fills it).
	 */
	public static function app_data() {
		/**
		 * Voices the owner can choose when regenerating (empty: the option is not shown).
		 *
		 * @param array[] $voices Each [ 'value' => id, 'label' => name ].
		 */
		$voices = (array) apply_filters( 'qcld_wpbot_writer/regenerate_voices', array() );
		$clean  = array();
		foreach ( $voices as $voice ) {
			if ( is_array( $voice ) && ! empty( $voice['value'] ) ) {
				$clean[] = array(
					'value' => sanitize_key( (string) $voice['value'] ),
					'label' => isset( $voice['label'] ) ? sanitize_text_field( (string) $voice['label'] ) : '',
				);
			}
		}
		return array( 'voices' => $clean );
	}
}
