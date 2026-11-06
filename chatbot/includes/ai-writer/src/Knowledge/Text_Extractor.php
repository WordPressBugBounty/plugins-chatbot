<?php
/**
 * Extracts plain text from uploaded documents without third-party libraries.
 *
 * TXT and Markdown are read as they are. DOCX is a ZIP with XML: the text
 * runs of word/document.xml are read with external entities off. PDF support
 * covers common text PDFs (Flate-compressed or plain content streams with
 * Tj/TJ text operators); scanned PDFs and unusual encodings return little
 * text, and the screen then asks the owner to paste the text instead.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Knowledge;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Text extraction.
 */
final class Text_Extractor {

	/**
	 * Maximum characters kept per document.
	 */
	const MAX_CHARS = 200000;

	/**
	 * Extract text from a file.
	 *
	 * @param string $path      File path.
	 * @param string $extension Lowercase extension (pdf, docx, txt, md).
	 * @return string|WP_Error
	 */
	public static function extract( $path, $extension ) {
		switch ( $extension ) {
			case 'txt':
			case 'md':
				$text = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
				break;
			case 'docx':
				$text = self::docx( $path );
				break;
			case 'pdf':
				$text = self::pdf( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
				break;
			default:
				return new WP_Error( 'qcld_wpbot_writer_bad_type', __( 'Upload a PDF, Word (.docx), text or Markdown file.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = self::tidy( $text );
		if ( strlen( $text ) < 20 ) {
			return new WP_Error( 'qcld_wpbot_writer_no_text', __( 'No readable text was found in this file. If it is a scanned document, paste its text instead.', 'chatbot' ), array( 'status' => 422 ) );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_CHARS ) : substr( $text, 0, self::MAX_CHARS );
	}

	/**
	 * Text of a Word document.
	 *
	 * @param string $path Path.
	 * @return string|WP_Error
	 */
	public static function docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_zip', __( 'This server cannot read Word files (the Zip extension is missing). Paste the text instead.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_docx', __( 'The Word file could not be opened.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( false === $xml || '' === $xml ) {
			return '';
		}
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return '';
		}
		$out = array();
		foreach ( $dom->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p' ) as $paragraph ) {
			$line = '';
			foreach ( $paragraph->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't' ) as $run ) {
				$line .= $run->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			}
			$out[] = $line;
		}
		return implode( "\n", $out );
	}

	/**
	 * Text of a simple PDF.
	 *
	 * @param string $data Raw PDF bytes.
	 * @return string
	 */
	public static function pdf( $data ) {
		if ( 0 !== strpos( $data, '%PDF' ) ) {
			return '';
		}
		$text = array();
		if ( preg_match_all( '/<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $data, $streams, PREG_SET_ORDER ) ) {
			foreach ( $streams as $stream ) {
				$content = $stream[2];
				if ( false !== strpos( $stream[1], '/FlateDecode' ) ) {
					$inflated = function_exists( 'gzuncompress' ) ? @gzuncompress( $content ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt streams are skipped.
					if ( false === $inflated && function_exists( 'gzinflate' ) ) {
						$inflated = @gzinflate( substr( $content, 2 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt streams are skipped.
					}
					if ( false === $inflated ) {
						continue;
					}
					$content = $inflated;
				} elseif ( preg_match( '#/Filter\s*/(DCTDecode|JPXDecode|CCITTFaxDecode|JBIG2Decode)#', $stream[1] ) ) {
					continue;
				}
				$part = self::pdf_text_operators( $content );
				if ( '' !== $part ) {
					$text[] = $part;
				}
			}
		}
		return implode( "\n", $text );
	}

	/**
	 * Read Tj / TJ / ' text operators between BT and ET.
	 *
	 * @param string $content Content stream.
	 * @return string
	 */
	private static function pdf_text_operators( $content ) {
		if ( ! preg_match_all( '/BT(.*?)ET/s', $content, $blocks ) ) {
			return '';
		}
		$lines = array();
		foreach ( $blocks[1] as $block ) {
			$line = '';
			if ( preg_match_all( '/(\[(?:[^\]\\\\]|\\\\.)*\]\s*TJ|\((?:[^()\\\\]|\\\\.)*\)\s*(?:Tj|\'|")|T\*|Td|TD)/s', $block, $ops ) ) {
				foreach ( $ops[1] as $op ) {
					if ( in_array( trim( $op ), array( 'T*', 'Td', 'TD' ), true ) ) {
						$line .= ' ';
						continue;
					}
					if ( preg_match_all( '/\(((?:[^()\\\\]|\\\\.)*)\)/s', $op, $strings ) ) {
						$line .= implode( '', array_map( array( __CLASS__, 'pdf_string' ), $strings[1] ) );
					}
				}
			}
			$lines[] = $line;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Decode a PDF literal string.
	 *
	 * @param string $value Escaped string.
	 * @return string
	 */
	private static function pdf_string( $value ) {
		$value = preg_replace_callback(
			'/\\\\([0-7]{1,3})/',
			static function ( $octal ) {
				return chr( octdec( $octal[1] ) );
			},
			$value
		);
		return strtr(
			$value,
			array(
				'\\n'  => "\n",
				'\\r'  => '',
				'\\t'  => ' ',
				'\\('  => '(',
				'\\)'  => ')',
				'\\\\' => '\\',
			)
		);
	}

	/**
	 * Normalise whitespace and encoding.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function tidy( $text ) {
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' ) : $text;
		}
		$text = preg_replace( "/[ \t]+/", ' ', (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}
}
