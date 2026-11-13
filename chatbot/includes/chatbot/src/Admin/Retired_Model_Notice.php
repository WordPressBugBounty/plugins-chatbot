<?php
/**
 * Notice when the AI provider's saved model was retired and the chatbot uses its replacement
 * (chatbot features CB1b). Shown to administrators on WPBot screens.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Ai\Provider_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Retired model notice.
 */
final class Retired_Model_Notice {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * The notice text ('' when the answering provider's model is current).
	 *
	 * @return string
	 */
	public static function text() {
		$provider = Provider_Settings::answering_provider();
		$settings = '' !== $provider ? Provider_Settings::get( $provider ) : null;
		if ( ! $settings || empty( $settings['retired_model'] ) ) {
			return '';
		}
		return sprintf(
			/* translators: 1: retired model, 2: replacement model. */
			__( 'The chatbot\'s AI model %1$s was retired by the provider, so the chatbot now uses %2$s. Choose a model on the AI provider\'s settings page to make this permanent.', 'chatbot' ),
			$settings['retired_model'],
			$settings['model']
		);
	}

	/**
	 * Print the notice on WPBot screens.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( false === strpos( (string) $screen->id, 'wpbot' ) && false === strpos( (string) $screen->id, 'chatbot' ) ) ) {
			return;
		}
		$text = self::text();
		if ( '' !== $text ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $text ) . '</p></div>';
		}
	}
}
