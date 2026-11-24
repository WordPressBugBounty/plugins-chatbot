<?php
/**
 * The visitor's light poll (live chat plan 3.2): a tiny static JSON file per conversation that the
 * web server serves without PHP. It holds only the last event id; the widget calls the REST API only
 * when that id changes. When the file can't be written, the widget falls back to short REST polls.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Static signal files.
 */
final class Signal {

	/**
	 * Folder in uploads.
	 */
	const DIR = 'wpbot-live';

	/**
	 * File name for a conversation (signed, so nobody can guess another conversation's file).
	 *
	 * @param int $conversation_id Conversation.
	 * @return string
	 */
	public static function name( $conversation_id ) {
		return substr( hash_hmac( 'sha256', 'live-signal|' . (int) $conversation_id, wp_salt( 'auth' ) ), 0, 32 ) . '.json';
	}

	/**
	 * Folder path and URL, or null when uploads are not writable.
	 *
	 * @return array|null { dir, url }.
	 */
	private static function base() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		return array(
			'dir' => trailingslashit( $uploads['basedir'] ) . self::DIR,
			'url' => trailingslashit( $uploads['baseurl'] ) . self::DIR,
		);
	}

	/**
	 * Write the last event id of a conversation.
	 *
	 * @param int $conversation_id Conversation.
	 * @param int $event_id        Last event id.
	 * @return bool Whether the file was written.
	 */
	public static function write( $conversation_id, $event_id ) {
		$base = self::base();
		if ( null === $base ) {
			return false;
		}
		if ( ! is_dir( $base['dir'] ) ) {
			if ( ! wp_mkdir_p( $base['dir'] ) ) {
				return false;
			}
			// No directory listing.
			file_put_contents( $base['dir'] . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A tiny static file, no WP_Filesystem credentials prompt.
		}
		$ok = file_put_contents( $base['dir'] . '/' . self::name( $conversation_id ), wp_json_encode( array( 'e' => (int) $event_id ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- See above.
		return false !== $ok;
	}

	/**
	 * Public URL of a conversation's file ('' when uploads are not writable).
	 *
	 * @param int $conversation_id Conversation.
	 * @return string
	 */
	public static function url( $conversation_id ) {
		$base = self::base();
		return null === $base ? '' : $base['url'] . '/' . self::name( $conversation_id );
	}

	/**
	 * Remove a conversation's file (after it closed and the visitor saw it).
	 *
	 * @param int $conversation_id Conversation.
	 * @return void
	 */
	public static function remove( $conversation_id ) {
		$base = self::base();
		if ( null !== $base ) {
			wp_delete_file( $base['dir'] . '/' . self::name( $conversation_id ) );
		}
	}
}
