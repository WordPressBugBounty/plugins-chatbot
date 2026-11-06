<?php
/**
 * Uploaded source documents: the text is extracted and stored in the
 * documents table, and the uploaded file is deleted straight away.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Knowledge;

use QuantumCloud\AiWriter\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Document store.
 */
final class Documents {

	/**
	 * Allowed types: extension => MIME.
	 *
	 * @return array<string,string>
	 */
	public static function mimes() {
		return array(
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'  => 'text/plain',
			'md'   => 'text/markdown',
		);
	}

	/**
	 * Store an uploaded file ($_FILES entry) and its text.
	 *
	 * @param array $file    Uploaded file array.
	 * @param int   $max_mb  Size limit in MB.
	 * @return array|WP_Error Document row (no text).
	 */
	public static function upload( array $file, $max_mb = 10 ) {
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_upload', __( 'The file did not upload. Try again.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] > (int) $max_mb * MB_IN_BYTES ) {
			/* translators: %d: size limit in MB. */
			return new WP_Error( 'qcld_wpbot_writer_too_big', sprintf( __( 'The file is larger than %d MB.', 'chatbot' ), (int) $max_mb ), array( 'status' => 413 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$mimes = self::mimes();
		// Markdown is often reported as text/plain.
		$allowed = array(
			'pdf'  => $mimes['pdf'],
			'docx' => $mimes['docx'],
			'txt'  => 'text/plain',
			'md'   => 'text/plain',
		);
		$moved   = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed,
			)
		);
		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_upload', $moved['error'], array( 'status' => 400 ) );
		}

		$extension = strtolower( pathinfo( $moved['file'], PATHINFO_EXTENSION ) );
		$text      = Text_Extractor::extract( $moved['file'], $extension );
		if ( is_wp_error( $text ) ) {
			wp_delete_file( $moved['file'] );
			return $text;
		}

		// Only the text is kept: the uploaded file is deleted so private
		// documents are never reachable at a public uploads address.
		wp_delete_file( $moved['file'] );
		$title = sanitize_text_field( pathinfo( isset( $file['name'] ) ? $file['name'] : $moved['file'], PATHINFO_FILENAME ) );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
			Schema::table( Schema::DOCUMENTS ),
			array(
				'attachment_id' => 0,
				'title'         => substr( $title, 0, 255 ),
				'text'          => $text,
				'tokens'        => (int) ceil( strlen( $text ) / 4 ),
				'created_by'    => get_current_user_id(),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return self::get( (int) $wpdb->insert_id, false );
	}

	/**
	 * A document.
	 *
	 * @param int  $id        Id.
	 * @param bool $with_text Include the text.
	 * @return array|null
	 */
	public static function get( $id, $with_text = true ) {
		global $wpdb;
		$table = Schema::table( Schema::DOCUMENTS );
		$cols  = $with_text ? 'id, title, text, summary, tokens, created_by, created_at' : 'id, title, summary, tokens, created_by, created_at';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT {$cols} FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; columns from code.
		if ( ! $row ) {
			return null;
		}
		$row['id']         = (int) $row['id'];
		$row['tokens']     = (int) $row['tokens'];
		$row['created_by'] = (int) $row['created_by'];
		return $row;
	}

	/**
	 * Recent documents of a user (or everyone).
	 *
	 * @param int $user_id User filter (0 = all).
	 * @return array[]
	 */
	public static function recent( $user_id = 0 ) {
		global $wpdb;
		$table = Schema::table( Schema::DOCUMENTS );
		$sql   = "SELECT id, title, tokens, created_at FROM {$table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
		if ( $user_id > 0 ) {
			$sql .= $wpdb->prepare( ' WHERE created_by = %d', $user_id );
		}
		$sql .= ' ORDER BY id DESC LIMIT 30';
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from code and prepare().
	}

	/**
	 * Save the one-time summary.
	 *
	 * @param int    $id      Id.
	 * @param string $summary Summary.
	 * @return void
	 */
	public static function save_summary( $id, $summary ) {
		global $wpdb;
		$wpdb->update( Schema::table( Schema::DOCUMENTS ), array( 'summary' => sanitize_textarea_field( $summary ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
	}
}
