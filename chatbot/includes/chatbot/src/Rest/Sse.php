<?php
/**
 * Server-sent events output (streamed AI answers).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * SSE writer.
 */
final class Sse {

	/**
	 * Send the headers and turn off output buffering.
	 *
	 * @return void
	 */
	public static function start() {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-store' );
			header( 'X-Accel-Buffering: no' );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		ignore_user_abort( true );
	}

	/**
	 * Send one data line.
	 *
	 * @param string $data Data (already encoded).
	 * @return void
	 */
	public static function send( $data ) {
		echo 'data: ' . str_replace( array( "\r", "\n" ), array( '', '\\n' ), (string) $data ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON or a fixed marker, sent as text/event-stream.
		echo str_repeat( ' ', 1024 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- padding for proxies that buffer small chunks.
		flush();
	}

	/**
	 * Send JSON.
	 *
	 * @param array $data Data.
	 * @return void
	 */
	public static function json( array $data ) {
		self::send( (string) wp_json_encode( $data ) );
	}
}
