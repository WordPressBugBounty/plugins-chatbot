<?php
/**
 * Public functions of the chatbot module.
 *
 * @package QuantumCloud\Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_chat' ) ) {
	/**
	 * The booted chatbot module.
	 *
	 * @return \QuantumCloud\Chatbot\Module
	 */
	function qcld_wpbot_chat() {
		return \QuantumCloud\Chatbot\Module::instance();
	}
}

if ( ! function_exists( 'qcld_wpbot_chat_modules' ) ) {
	/**
	 * The module registry behind Module Manager.
	 *
	 * @return \QuantumCloud\Chatbot\Modules\Registry
	 */
	function qcld_wpbot_chat_modules() {
		return \QuantumCloud\Chatbot\Module::instance()->modules();
	}
}

if ( ! function_exists( 'qcld_wpbot_chat_scope_system' ) ) {
	/**
	 * An AI provider's system prompt with the answer scope rules added after it.
	 * The hosts' provider handlers call this where they build the system prompt.
	 *
	 * @param string $system Saved system prompt (may be empty).
	 * @return string
	 */
	function qcld_wpbot_chat_scope_system( $system ) {
		return \QuantumCloud\Chatbot\Ai\Answer_Scope::apply( (string) $system );
	}
}
