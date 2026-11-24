<?php
/**
 * CSV export of contacts (the same filters as the Contacts screen).
 *
 * Runs on admin-post with a nonce and the wpbot_marketing_use capability.
 * Cells that a spreadsheet would run as a formula are prefixed with a quote.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter.
 */
final class Exporter {

	const ACTION = 'qcld_wpbot_marketing_export';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'download' ) );
	}

	/**
	 * Export URL for the app.
	 *
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'_wpnonce' => wp_create_nonce( self::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Make a cell safe for spreadsheets (pure; unit tested).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function cell( $value ) {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	/**
	 * The header row.
	 *
	 * @return string[]
	 */
	public static function headers() {
		return array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'language', 'status', 'source', 'lists', 'tags', 'attributes', 'created_at', 'confirmed_at', 'unsubscribed_at' );
	}

	/**
	 * One contact as a row (pure; unit tested).
	 *
	 * @param array $contact Contact from Contacts::shape( $row, true ).
	 * @return string[]
	 */
	public static function row( array $contact ) {
		$names = static function ( $groups ) {
			return implode( ', ', wp_list_pluck( (array) $groups, 'name' ) );
		};
		$attrs = array();
		foreach ( (array) $contact['attributes'] as $key => $value ) {
			$attrs[] = $key . '=' . ( is_scalar( $value ) ? $value : wp_json_encode( $value ) );
		}
		$out = array(
			$contact['email'],
			$contact['first_name'],
			$contact['last_name'],
			$contact['phone'],
			$contact['company'],
			$contact['website'],
			$contact['language'],
			$contact['status'],
			$contact['source'],
			$names( isset( $contact['lists'] ) ? $contact['lists'] : array() ),
			$names( isset( $contact['tags'] ) ? $contact['tags'] : array() ),
			implode( '; ', $attrs ),
			$contact['created_at'],
			$contact['confirmed_at'],
			$contact['unsubscribed_at'],
		);
		return array_map( array( __CLASS__, 'cell' ), $out );
	}

	/**
	 * Stream the file.
	 *
	 * @return void
	 */
	public static function download() {
		if ( ! current_user_can( 'wpbot_marketing_use' ) ) {
			wp_die( esc_html__( 'You are not allowed to export contacts.', 'chatbot' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$filters = array();
		foreach ( array( 'search', 'status', 'list_id', 'tag_id' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked above.
				$filters[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked above.
			}
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="contacts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming the download.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 mark for spreadsheets.
		fputcsv( $out, self::headers(), ',', '"', '\\' );
		$page = 1;
		do {
			$result = Contacts::query(
				array_merge(
					$filters,
					array(
						'page'     => $page,
						'per_page' => 200,
						'orderby'  => 'created_at',
						'order'    => 'asc',
					)
				)
			);
			foreach ( $result['items'] as $contact ) {
				fputcsv( $out, self::row( $contact ), ',', '"', '\\' );
			}
			++$page;
		} while ( $page <= $result['pages'] );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fopen.
		exit;
	}
}
