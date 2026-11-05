<?php
/**
 * Read-only view of one kind of saved chat data offered by the host.
 *
 * Modules never query the host's chat tables directly; they page through a
 * Chat_Source. Implementations live in the host adapter. Personal data is
 * removed by the caller (Privacy\Redactor) before any text leaves the site.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Chat;

defined( 'ABSPATH' ) || exit;

interface Chat_Source {

	/**
	 * Stable id, for example "wpbot_conversation".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Label for the owner.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether the data exists on this site (the add-on and table are present).
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Approximate number of sessions, for progress and cost estimates.
	 *
	 * @param string $since Optional MySQL datetime lower bound.
	 * @return int
	 */
	public function count_sessions( $since = '' );

	/**
	 * Sessions after a cursor, oldest first.
	 *
	 * Each session: array( 'ref' => stable string, 'updated' => datetime,
	 * 'names' => string[] (names the chat recorded for the visitor, used only
	 * to redact them; never stored or sent),
	 * 'messages' => array( array( 'role' => 'visitor'|'bot'|'agent', 'text' => string ) ),
	 * 'signals' => array( 'dislike' => bool, 'unanswered' => bool ) ).
	 *
	 * @param int    $after_id Cursor (last seen internal id).
	 * @param int    $limit    Page size.
	 * @param string $since    Optional MySQL datetime lower bound.
	 * @return array{sessions: array, cursor: int}
	 */
	public function get_sessions( $after_id, $limit, $since = '' );
}
