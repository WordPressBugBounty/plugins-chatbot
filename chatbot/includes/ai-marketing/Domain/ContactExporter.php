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
 * Reads contacts a page at a time, so large lists don't load into memory at once.
 */
final class ContactExporter {

	const PAGE = 500;

	/**
	 * @return string[] Header row.
	 */
	public static function headers() {
		return array(
			__( 'Email', 'chatbot' ),
			__( 'First Name', 'chatbot' ),
			__( 'Last Name', 'chatbot' ),
			__( 'Phone', 'chatbot' ),
			__( 'Status', 'chatbot' ),
			__( 'Assigned Lists', 'chatbot' ),
			__( 'Assigned Tags', 'chatbot' ),
			__( 'Lead Source', 'chatbot' ),
			__( 'Subscription Date', 'chatbot' ),
		);
	}

	/**
	 * Contacts as CSV text (UTF-8 BOM + header row first). Cells are formula-injection safe (Support\Csv).
	 *
	 * @param array $filters status (contact status) and list_id (0 = all lists).
	 * @return array{csv:string,total:int}
	 */
	public static function csv( array $filters = array() ) {
		$contacts = new ContactRepository();
		$groups   = new GroupRepository();
		$args     = array(
			'per_page' => self::PAGE,
			'status'   => isset( $filters['status'] ) ? (string) $filters['status'] : '',
			'list_id'  => isset( $filters['list_id'] ) ? absint( $filters['list_id'] ) : 0,
			'orderby'  => 'id',
			'order'    => 'ASC',
		);

		ob_start();
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UTF-8 BOM so Excel reads accents correctly.
		Csv::write( array(), self::headers() );
		$total = 0;
		$page  = 1;
		do {
			$found = $contacts->search( array_merge( $args, array( 'page' => $page ) ) );
			$rows  = $found['items'];
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
					$row['status'],
					self::names( isset( $member['list'] ) ? $member['list'] : array() ),
					self::names( isset( $member['tag'] ) ? $member['tag'] : array() ),
					$row['source'],
					Dates::mysql( $row['created_at'] ),
				);
			}
			Csv::write( $lines );
			$total += count( $rows );
			++$page;
		} while ( count( $rows ) === self::PAGE );

		return array(
			'csv'   => (string) ob_get_clean(),
			'total' => $total,
		);
	}

	/**
	 * @param array[] $groups Rows with a `name`.
	 * @return string Names joined with ", ".
	 */
	private static function names( array $groups ) {
		return implode( ', ', wp_list_pluck( $groups, 'name' ) );
	}
}
