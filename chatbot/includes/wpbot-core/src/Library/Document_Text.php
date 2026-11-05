<?php
/**
 * Text from a document the owner uploaded: Word (.docx), plain text,
 * Markdown and CSV, read with PHP's own tools (no extra library). PDF is not
 * read here: callers send it to an AI provider that reads PDFs
 * (Providers::reads_pdf()).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Library;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Document text.
 */
final class Document_Text {

	/**
	 * Extensions read here.
	 */
	const TYPES = array( 'docx', 'txt', 'md', 'csv' );

	/**
	 * Most characters returned.
	 */
	const MAX_CHARS = 40000;

	/**
	 * Read a file.
	 *
	 * @param string $path Local path.
	 * @param string $name Original file name (for the extension).
	 * @return array|WP_Error text, truncated (bool).
	 */
	public static function read( $path, $name ) {
		$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::TYPES, true ) ) {
			return new WP_Error( 'qcld_wpbot_core_doc_type', __( 'This kind of file cannot be read here.', 'chatbot' ), array( 'status' => 415 ) );
		}
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'qcld_wpbot_core_doc_missing', __( 'The file could not be opened.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$text = 'docx' === $ext ? self::docx( $path ) : self::plain( $path );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = Page_Reader::clean_text( $text );
		if ( '' === $text ) {
			return new WP_Error( 'qcld_wpbot_core_doc_empty', __( 'No text was found in this file.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$long = strlen( $text ) > self::MAX_CHARS;
		$text = $long ? substr( $text, 0, self::MAX_CHARS ) : $text;
		return array(
			'text'      => function_exists( 'mb_scrub' ) ? mb_scrub( $text, 'UTF-8' ) : $text,
			'truncated' => $long,
		);
	}

	/**
	 * Plain text, Markdown or CSV, as UTF-8.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function plain( $path ) {
		$raw = (string) file_get_contents( $path, false, null, 0, 2097152 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload, read once.
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
		}
		return (string) $raw;
	}

	/**
	 * Word document: the paragraphs of word/document.xml.
	 *
	 * @param string $path Path.
	 * @return string|WP_Error
	 */
	private static function docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'qcld_wpbot_core_doc_zip', __( 'This server cannot open Word files (the PHP zip extension is missing). Save the document as text and upload that.', 'chatbot' ), array( 'status' => 501 ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'qcld_wpbot_core_doc_bad', __( 'This Word file could not be opened.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$xml = (string) $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( '' === $xml ) {
			return new WP_Error( 'qcld_wpbot_core_doc_bad', __( 'This Word file could not be opened.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$xml = preg_replace( '/<w:tab\/>/', "\t", $xml );
		$xml = preg_replace( '/<w:(br|cr)\/>/', "\n", (string) $xml );
		$xml = preg_replace( '/<\/w:p>/', "\n", (string) $xml );
		return html_entity_decode( wp_strip_all_tags( (string) $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
