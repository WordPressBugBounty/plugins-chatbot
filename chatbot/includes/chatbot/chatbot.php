<?php
/**
 * WPBot Chatbot core module.
 *
 * Bundled by host plugins at includes/chatbot/ (WPBot Free and WPBot Pro, the
 * same files). Each host requires this file once from its main plugin file and
 * registers its adapter on `qcld_wpbot_chat/loaded`. The loader boots only the
 * newest bundled copy.
 *
 * @package QuantumCloud\Chatbot
 */

defined( 'ABSPATH' ) || exit;

// PHP 7.4 or newer; on anything older the module stays out and the host works as before.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_chat_register_version( '0.18.1', __DIR__ );
