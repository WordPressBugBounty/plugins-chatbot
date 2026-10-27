<?php
/**
 * Where recording chunks live. The local file store is the default;
 * a Pro feature can swap in S3-compatible storage via `qcin/recording_store`.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

interface Recording_Store {

	/**
	 * Store one gzip-compressed chunk.
	 *
	 * @param string $session_uuid Session id.
	 * @param string $pageview_uuid Pageview id.
	 * @param int    $seq          Chunk sequence number.
	 * @param string $bytes        Gzip bytes exactly as received.
	 * @return int|false Bytes written, or false on failure.
	 */
	public function put( $session_uuid, $pageview_uuid, $seq, $bytes );

	/**
	 * All chunks of a pageview, in sequence order.
	 *
	 * @param string $session_uuid  Session id.
	 * @param string $pageview_uuid Pageview id.
	 * @return string[] Gzip chunks.
	 */
	public function get_pageview( $session_uuid, $pageview_uuid );

	/**
	 * Delete everything stored for a session.
	 *
	 * @param string $session_uuid Session id.
	 */
	public function delete_session( $session_uuid );

	/**
	 * Delete everything.
	 */
	public function delete_all();

	/**
	 * Diagnostics for the Health panel.
	 *
	 * @return array<string,mixed>
	 */
	public function health();
}
