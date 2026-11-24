<?php
/**
 * Log of every change an AI assistant made through the Help Center tools (HC3-A).
 *
 * Kept in one option (not autoloaded), newest first, at most 300 entries.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Change log.
 */
final class Change_Log {

	/**
	 * Option.
	 */
	const OPTION = 'qcld_wpbot_help_center_ai_log';

	/**
	 * Entries kept.
	 */
	const KEEP = 300;

	/**
	 * Add an entry.
	 *
	 * @param string $action  created, updated, suggested, unpublish_suggested, ordered, applied, dismissed.
	 * @param string $type    article, category, knowledge_base.
	 * @param string $slug    Slug.
	 * @param int    $id      Post or term id.
	 * @param string $note    Short note.
	 * @return void
	 */
	public static function add( $action, $type, $slug, $id = 0, $note = '' ) {
		$log = self::all();
		array_unshift(
			$log,
			array(
				'time'   => time(),
				'user'   => get_current_user_id(),
				'action' => sanitize_key( $action ),
				'type'   => sanitize_key( $type ),
				'slug'   => sanitize_title( $slug ),
				'id'     => (int) $id,
				'note'   => substr( sanitize_text_field( $note ), 0, 200 ),
			)
		);
		update_option( self::OPTION, array_slice( $log, 0, self::KEEP ), false );
	}

	/**
	 * Every entry, newest first.
	 *
	 * @param int $limit Maximum.
	 * @return array[]
	 */
	public static function all( $limit = 0 ) {
		$log = get_option( self::OPTION, array() );
		$log = is_array( $log ) ? array_values( $log ) : array();
		return $limit > 0 ? array_slice( $log, 0, $limit ) : $log;
	}
}
