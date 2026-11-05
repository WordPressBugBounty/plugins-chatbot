<?php
/**
 * One shared answer to "is AI ready for this module?" (follow-up 3.0).
 *
 * Every module asks the same question before it offers an AI action, and
 * shows the same bold notice when the answer is no: the cause in plain
 * words and one button that opens the module's "Settings & AI Provider"
 * page at the right provider. That way AI never fails silently.
 *
 * Statuses, in the order they are checked:
 * - no_key:          no provider has a key (or an address, for keyless ones).
 * - not_enabled:     keys are saved but no provider is turned on.
 * - no_model:        the main provider has no model and no default.
 * - failing:         the providers that would be called all fail for a
 *                    reason the owner must fix (cause: invalid_key,
 *                    no_credit, rate_limit, model_removed, key_unreadable).
 * - budget_reached:  the module's monthly AI budget is used up.
 * - ready.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * AI readiness per module.
 */
final class AI_Readiness {

	const SCRIPT = 'qcld-wpbot-core-ai-readiness';

	/**
	 * Readiness of a module.
	 *
	 * @param string $module Module id.
	 * @return array status, ready, cause, message, action, action_label, provider, provider_label, url, warning.
	 */
	public static function check( $module ) {
		$module = sanitize_key( $module );
		$view   = Keys::public_view( $module );
		$order  = Keys::call_order( $module );

		$usable = array();
		foreach ( $view['providers'] as $id => $entry ) {
			if ( $entry['has_key'] || ( ! $entry['needs_key'] && '' !== (string) $entry['url'] ) ) {
				$usable[] = (string) $id;
			}
		}

		if ( array() === $usable ) {
			return self::result( $module, 'no_key', '', 'set_up', '' );
		}
		if ( array() === $order ) {
			return self::result( $module, 'not_enabled', '', 'turn_on', $usable[0] );
		}

		$primary = $order[0];
		$creds   = Keys::credentials( $module, $primary );
		if ( is_wp_error( $creds ) ) {
			$data  = $creds->get_error_data();
			$cause = is_array( $data ) && ! empty( $data['cause'] ) ? (string) $data['cause'] : Error_Classifier::NO_KEY;
			return self::result( $module, Error_Classifier::NO_KEY === $cause ? 'no_key' : 'failing', $cause, self::action_for( $cause ), $primary );
		}
		if ( '' === (string) $creds['model'] ) {
			return self::result( $module, 'no_model', '', 'pick_model', $primary );
		}

		$health = array();
		foreach ( Key_Health::notices( $module ) as $notice ) {
			$health[ $notice['provider'] ] = $notice['cause_code'];
		}
		$working = array_values( array_diff( $order, array_keys( $health ) ) );
		if ( array() === $working ) {
			$cause = $health[ $primary ];
			return self::result( $module, 'failing', $cause, self::action_for( $cause ), $primary );
		}

		if ( Usage::budget_reached( $module ) ) {
			return self::result( $module, 'budget_reached', '', 'raise_budget', $primary );
		}

		$result = self::result( $module, 'ready', '', '', $working[0] );
		if ( isset( $health[ $primary ] ) ) {
			// The backup is answering for a failing primary: still ready, but say so.
			$definition        = Providers::get( $primary );
			$explained         = Error_Classifier::explain( $health[ $primary ], $definition ? $definition['label'] : $primary );
			$result['warning'] = $explained['cause'];
		}
		return $result;
	}

	/**
	 * URL of a module's "Settings & AI Provider" page.
	 *
	 * @param string $module   Module id.
	 * @param string $provider Provider to jump to ('' for the page).
	 * @return string
	 */
	public static function settings_url( $module, $provider = '' ) {
		$host = Core::instance()->host();
		$url  = $host ? (string) $host->get_ai_settings_url() : '';

		/**
		 * URL of a module's AI settings ("Settings & AI Provider") page.
		 *
		 * @param string $url    Default: the host's main AI settings.
		 * @param string $module Module id.
		 */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_settings_url', $url, $module );
		if ( '' !== $url && '' !== $provider ) {
			$url .= '#provider-' . sanitize_key( $provider );
		}
		return $url;
	}

	/**
	 * Bold notice HTML for a module, or '' when AI is ready.
	 *
	 * @param string $module Module id.
	 * @return string
	 */
	public static function notice_html( $module ) {
		$state = self::check( $module );
		if ( $state['ready'] ) {
			return '';
		}
		$button = '';
		if ( '' !== $state['url'] && '' !== $state['action_label'] ) {
			$button = sprintf(
				' <a class="button button-primary qcld-ai-readiness__button" href="%1$s">%2$s</a>',
				esc_url( $state['url'] ),
				esc_html( $state['action_label'] )
			);
		}
		return sprintf(
			'<div class="notice notice-error qcld-ai-readiness" role="alert"><p><strong>%1$s</strong>%2$s</p></div>',
			esc_html( $state['message'] ),
			$button
		);
	}

	/**
	 * Print the notice (for admin_notices on a module's screens).
	 *
	 * @param string $module Module id.
	 * @return void
	 */
	public static function admin_notice( $module ) {
		echo self::notice_html( $module ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in notice_html().
	}

	/**
	 * Register the shared React notice (plain JS on wp.element, no build).
	 * Modules add the handle to their script's dependencies and pass the
	 * readiness object from check() to `window.qcldWpbotCore.AiReadyNotice`.
	 *
	 * @return void
	 */
	public static function register_script() {
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : false;
		wp_register_style( self::SCRIPT, Core::url( 'assets/css/ai-readiness.css' ), array(), $version );
		wp_register_script( self::SCRIPT, Core::url( 'assets/js/ai-readiness.js' ), array( 'wp-element', 'wp-i18n' ), $version, true );
	}

	/**
	 * Build the result.
	 *
	 * @param string $module   Module id.
	 * @param string $status   Status.
	 * @param string $cause    Error_Classifier cause ('' when none).
	 * @param string $action   Action id.
	 * @param string $provider Provider concerned.
	 * @return array
	 */
	private static function result( $module, $status, $cause, $action, $provider ) {
		$definition = '' !== $provider ? Providers::get( $provider ) : null;
		$label      = $definition ? (string) $definition['label'] : '';
		return array(
			'status'         => $status,
			'ready'          => 'ready' === $status,
			'cause'          => $cause,
			'message'        => self::message( $status, $cause, $label ),
			'action'         => $action,
			'action_label'   => self::action_label( $action ),
			'provider'       => $provider,
			'provider_label' => $label,
			'url'            => self::settings_url( $module, $provider ),
			'warning'        => '',
		);
	}

	/**
	 * Which button fixes a failure cause.
	 *
	 * @param string $cause Error_Classifier cause.
	 * @return string
	 */
	private static function action_for( $cause ) {
		switch ( $cause ) {
			case Error_Classifier::NO_CREDIT:
				return 'add_funds';
			case Error_Classifier::MODEL_REMOVED:
				return 'pick_model';
			case Error_Classifier::RATE_LIMIT:
			case Error_Classifier::PROVIDER_DOWN:
			case Error_Classifier::NETWORK:
				return 'wait';
			default:
				return 'update_key';
		}
	}

	/**
	 * Button text per action.
	 *
	 * @param string $action Action id.
	 * @return string
	 */
	private static function action_label( $action ) {
		$labels = array(
			'set_up'       => __( 'Set up AI', 'chatbot' ),
			'turn_on'      => __( 'Turn on', 'chatbot' ),
			'pick_model'   => __( 'Pick a model', 'chatbot' ),
			'update_key'   => __( 'Update key', 'chatbot' ),
			'add_funds'    => __( 'Add funds', 'chatbot' ),
			'raise_budget' => __( 'Raise budget', 'chatbot' ),
			'wait'         => __( 'Open AI settings', 'chatbot' ),
		);
		return isset( $labels[ $action ] ) ? $labels[ $action ] : '';
	}

	/**
	 * Plain-words cause.
	 *
	 * @param string $status Status.
	 * @param string $cause  Failure cause.
	 * @param string $label  Provider name.
	 * @return string
	 */
	private static function message( $status, $cause, $label ) {
		switch ( $status ) {
			case 'ready':
				return '';
			case 'no_key':
				return __( 'AI is not set up: no AI provider key is saved. Add a key to use the AI features.', 'chatbot' );
			case 'not_enabled':
				/* translators: %s: AI provider name. */
				return sprintf( __( 'AI is turned off: your %s key is saved, but no provider is turned on.', 'chatbot' ), $label );
			case 'no_model':
				/* translators: %s: AI provider name. */
				return sprintf( __( 'AI is not ready: pick a model for %s.', 'chatbot' ), $label );
			case 'budget_reached':
				return __( 'AI is paused: this month\'s AI budget is used up.', 'chatbot' );
			default:
				$explained = Error_Classifier::explain( $cause, $label );
				/* translators: %s: reason, for example "The OpenAI API key is invalid or was revoked." */
				return sprintf( __( 'AI is not working: %s', 'chatbot' ), $explained['cause'] );
		}
	}
}
