<?php
/**
 * Streaming CSV helpers.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Reads CSV files row by row (auto-detecting the delimiter) and writes CSV output.
 */
final class Csv {

	/**
	 * Header aliases (lower-case, spaces/dashes/underscores collapsed) → contact field.
	 *
	 * @var array<string,string>
	 */
	const ALIASES = array(
		'email'         => 'email',
		'e-mail'        => 'email',
		'email address' => 'email',
		'e-mail address' => 'email',
		'first name'    => 'first_name',
		'firstname'     => 'first_name',
		'last name'     => 'last_name',
		'lastname'      => 'last_name',
		'surname'       => 'last_name',
		'phone'         => 'phone',
		'mobile'        => 'phone',
		'phone number'  => 'phone',
		'status'        => 'status',
		'company'       => 'company',
		'website'       => 'website',
	);

	/**
	 * Guess the delimiter of a CSV line: comma, semicolon or tab.
	 *
	 * @param string $line First line of the file.
	 * @return string
	 */
	public static function sniff_delimiter( $line ) {
		$best  = ',';
		$count = -1;
		foreach ( array( ',', ';', "\t" ) as $delimiter ) {
			$n = count( str_getcsv( (string) $line, $delimiter ) );
			if ( $n > $count ) {
				$best  = $delimiter;
				$count = $n;
			}
		}
		return $best;
	}

	/**
	 * Map a header cell to a contact field name, or a sanitised key for unknown headers.
	 *
	 * @param string $header Raw header text.
	 * @return string
	 */
	public static function normalize_header( $header ) {
		$header = strtolower( trim( (string) $header, " \t\n\r\0\x0B\"'\xEF\xBB\xBF" ) );
		$spaced = trim( preg_replace( '/[\s_]+/', ' ', $header ) );
		if ( isset( self::ALIASES[ $spaced ] ) ) {
			return self::ALIASES[ $spaced ];
		}
		return sanitize_key( str_replace( ' ', '_', $spaced ) );
	}

	/**
	 * Iterate the rows of a CSV file as arrays keyed by normalised header.
	 *
	 * Empty lines are skipped. Missing cells become ''.
	 *
	 * @param string $path      File path.
	 * @param string $delimiter Delimiter, or '' to auto-detect.
	 * @return \Generator<int,array<string,string>>
	 */
	public static function rows( $path, $delimiter = '' ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming read.
		$handle = fopen( $path, 'r' );
		if ( false === $handle ) {
			return;
		}
		$first = fgets( $handle );
		if ( false === $first ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return;
		}
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		if ( '' === $delimiter ) {
			$delimiter = self::sniff_delimiter( $first );
		}
		$headers = array_map( array( __CLASS__, 'normalize_header' ), str_getcsv( rtrim( $first, "\r\n" ), $delimiter ) );
		$width   = count( $headers );

		while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( array( null ) === $cells || ( 1 === count( $cells ) && '' === trim( (string) $cells[0] ) ) ) {
				continue;
			}
			$cells = array_slice( array_pad( $cells, $width, '' ), 0, $width );
			yield array_combine( $headers, array_map( 'trim', array_map( 'strval', $cells ) ) );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Send download headers for a CSV file.
	 *
	 * @param string $filename File name.
	 */
	public static function send_headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	}

	/**
	 * Write rows to php://output. Cells starting with = + - @ are prefixed with ' to
	 * prevent spreadsheet formula injection.
	 *
	 * @param array $rows    List of rows (associative or indexed arrays).
	 * @param array $headers Optional header row; if empty and rows are associative, keys of the first row are used.
	 */
	public static function write( array $rows, array $headers = array() ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}
		if ( empty( $headers ) && ! empty( $rows ) ) {
			$first = reset( $rows );
			if ( is_array( $first ) && array_keys( $first ) !== range( 0, count( $first ) - 1 ) ) {
				$headers = array_keys( $first );
			}
		}
		if ( ! empty( $headers ) ) {
			fputcsv( $out, $headers );
		}
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), array_values( (array) $row ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Neutralise a cell that would be interpreted as a formula.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( '=+-@', $value[0] ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
