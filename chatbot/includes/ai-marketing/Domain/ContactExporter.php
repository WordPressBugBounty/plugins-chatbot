<?php
/**
 * Builds the contacts CSV for the Import & Export screen.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Csv;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Reads contacts in id order, a chunk at a time, so large lists don't load into memory at once.
 */
final class ContactExporter {

	const CHUNK = 500;

	const HEADERS = array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status', 'lists', 'tags', 'created_at' );

	/**
	 * Every contact as CSV text (header row first). Cells are formula-injection safe (Support\Csv).
	 *
	 * @return string
	 */
	public static function csv() {
		$contacts = new ContactRepository();
		$groups   = new GroupRepository();

		ob_start();
		Csv::write( array(), self::HEADERS );
		$after = 0;
		do {
			$rows = $contacts->chunk( $after, self::CHUNK );
			if ( empty( $rows ) ) {
				break;
			}
			$memberships = $groups->groups_for_contacts( wp_list_pluck( $rows, 'id' ) );
			$lines       = array();
			foreach ( $rows as $row ) {
				$member  = isset( $memberships[ $row['id'] ] ) ? $memberships[ $row['id'] ] : array();
				$lines[] = array(
					$row['email'],
					$row['first_name'],
					$row['last_name'],
					$row['phone'],
					$row['company'],
					$row['website'],
					$row['status'],
					self::names( isset( $member['list'] ) ? $member['list'] : array() ),
					self::names( isset( $member['tag'] ) ? $member['tag'] : array() ),
					Dates::mysql( $row['created_at'] ),
				);
				$after = (int) $row['id'];
			}
			Csv::write( $lines );
		} while ( count( $rows ) === self::CHUNK );

		return (string) ob_get_clean();
	}

	/**
	 * @param array[] $groups Rows with a `name`.
	 * @return string Names joined with "; ".
	 */
	private static function names( array $groups ) {
		return implode( '; ', wp_list_pluck( $groups, 'name' ) );
	}
}
