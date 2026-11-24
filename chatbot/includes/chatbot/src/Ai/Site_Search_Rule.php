<?php
/**
 * Site Search is off while the AI answers (owner, 23 November 2026: "If AI is enabled and working,
 * Site Search must be disabled. If site search is disabled, it must not show in the Start menu
 * Available menu area").
 *
 * Saving a provider on AI Settings already switches Site Search off (disable_wp_chatbot_site_search),
 * but sites that turned AI on another way (setup wizard, older versions, imports) kept the Site Search
 * button. This reads the option as "1" whenever a provider is switched on and can be called (it has
 * an API key, or for Ollama a server address). Everything that reads the option follows: the start
 * menu on the site, the start menu builder's Available Menu items, and the hosts' settings screens.
 * When no provider answers, the stored value applies again.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Site Search follows the AI.
 */
final class Site_Search_Rule {

	/**
	 * The option the hosts and the start menu read.
	 */
	const OPTION = 'disable_wp_chatbot_site_search';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_option_' . self::OPTION, array( __CLASS__, 'filter' ) );
	}

	/**
	 * The provider answering the chat, when it can be called; '' otherwise.
	 *
	 * @return string
	 */
	public static function ai_provider() {
		$provider = Provider_Settings::answering_provider();
		return '' !== $provider && Provider_Settings::is_set_up( $provider ) ? $provider : '';
	}

	/**
	 * Whether Site Search is off because the AI answers.
	 *
	 * @return bool
	 */
	public static function off_for_ai() {
		return '' !== self::ai_provider();
	}

	/**
	 * Filter for pre_option: "1" while the AI answers, otherwise the stored value.
	 *
	 * @param mixed $pre Value from earlier filters (false: none).
	 * @return mixed
	 */
	public static function filter( $pre ) {
		if ( false !== $pre ) {
			return $pre;
		}
		return self::off_for_ai() ? '1' : false;
	}
}
