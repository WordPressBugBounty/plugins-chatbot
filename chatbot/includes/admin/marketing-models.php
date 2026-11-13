<?php
/**
 * Email Marketing: the AI models offered in its settings, from WPBot Core.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_marketing_models' ) ) {
	/**
	 * Models per provider for the Email Marketing AI settings, from WPBot Core's catalogue.
	 *
	 * @return array<string,array<int,array{label:string,value:string}>>
	 */
	function qcld_wpbot_marketing_models() {
		$out = array();
		if ( ! class_exists( '\\QuantumCloud\\WPBotCore\\AI\\Providers' ) ) {
			return $out;
		}
		$picks_api = class_exists( '\\QuantumCloud\\WPBotCore\\AI\\Model_Catalog' );
		foreach ( array( 'openai', 'gemini', 'claude', 'openrouter', 'grok' ) as $id ) {
			$definition = \QuantumCloud\WPBotCore\AI\Providers::get( $id );
			if ( ! $definition ) {
				continue;
			}
			$picks = $picks_api ? \QuantumCloud\WPBotCore\AI\Model_Catalog::picks( $id ) : array();
			$first = array();
			$rest  = array();
			foreach ( (array) $definition['models'] as $model => $info ) {
				if ( empty( $info['text'] ) ) {
					continue;
				}
				$label = isset( $info['label'] ) ? (string) $info['label'] : (string) $model;
				if ( isset( $picks[ $model ] ) ) {
					$first[ $model ] = array(
						'label' => '★ ' . $label . ' · ' . \QuantumCloud\WPBotCore\AI\Model_Catalog::pick_label( $picks[ $model ] ),
						'value' => (string) $model,
					);
				} else {
					$rest[] = array(
						'label' => $label,
						'value' => (string) $model,
					);
				}
			}
			$ordered = array();
			foreach ( array_keys( $picks ) as $model ) {
				if ( isset( $first[ $model ] ) ) {
					$ordered[] = $first[ $model ];
				}
			}
			$out[ $id ] = array_merge( $ordered, $rest );
		}
		return $out;
	}
}
