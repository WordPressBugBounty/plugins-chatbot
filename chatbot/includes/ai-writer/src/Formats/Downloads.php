<?php
/**
 * CR-16 F4: downloads for pieces written in a format. Readers of the
 * published post get a small box at the end of the article:
 * - checklist: a PDF (Dompdf, AI Writer's scoped copy) and a print view;
 * - template / framework: Word (.docx, written with PHP's zip) and text;
 * - original research: the approved numbers as CSV (cells escaped against
 *   formula injection).
 *
 * Files are made on request from the published post (and, for research,
 * the approved numbers kept with it); nothing is stored. Only published,
 * public posts are served, and only the downloads their format offers.
 * A forms or marketing module can ask for an email first through the
 * qcld_wpbot_writer/download_gate filter.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

use QuantumCloud\AiWriter\Settings;
use WP_Error;
use WP_Post;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Format downloads.
 */
final class Downloads {

	/**
	 * Query argument of a download link.
	 */
	const ARG = 'qcwr_download';

	/**
	 * Kinds of download.
	 */
	const KINDS = array( 'pdf', 'print', 'docx', 'txt', 'csv' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'the_content', array( __CLASS__, 'box' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'style' ) );
	}

	/**
	 * Downloads a post offers (empty when off, unpublished or none fit).
	 *
	 * @param WP_Post $post Post.
	 * @return string[]
	 */
	public static function offered( $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || post_password_required( $post ) || ! Settings::get( 'format_downloads' ) ) {
			return array();
		}
		$format = Formats::get( (string) get_post_meta( $post->ID, Formats::META, true ) );
		if ( null === $format ) {
			return array();
		}
		$kinds = array_values( array_intersect( (array) $format['downloads'], self::KINDS ) );
		if ( in_array( 'csv', $kinds, true ) && ! is_array( get_post_meta( $post->ID, Research_Data::META, true ) ) ) {
			$kinds = array_values( array_diff( $kinds, array( 'csv' ) ) );
		}
		if ( in_array( 'pdf', $kinds, true ) && ! self::pdf_ready() ) {
			$kinds = array_values( array_diff( $kinds, array( 'pdf' ) ) );
		}
		/**
		 * The downloads a post offers.
		 *
		 * @param string[] $kinds pdf, print, docx, txt, csv.
		 * @param WP_Post  $post  Post.
		 */
		return array_values( array_intersect( (array) apply_filters( 'qcld_wpbot_writer/format_downloads', $kinds, $post ), self::KINDS ) );
	}

	/**
	 * The download box after the article (single view, main query only).
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function box( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post  = get_post();
		$kinds = self::offered( $post );
		if ( array() === $kinds ) {
			return $content;
		}
		$labels = array(
			'pdf'   => __( 'Download PDF', 'chatbot' ),
			'print' => __( 'Print', 'chatbot' ),
			'docx'  => __( 'Download for Word (.docx)', 'chatbot' ),
			'txt'   => __( 'Download as text (.txt)', 'chatbot' ),
			'csv'   => __( 'Download the data (CSV)', 'chatbot' ),
		);
		$links  = '';
		foreach ( $kinds as $kind ) {
			$links .= '<a class="qcwr-downloads__link" rel="nofollow" href="' . esc_url( self::url( $post, $kind ) ) . '"' . ( 'print' === $kind ? ' target="_blank"' : '' ) . '>' . esc_html( $labels[ $kind ] ) . '</a> ';
		}
		$title = 'csv' === $kinds[0] ? __( 'Use the data', 'chatbot' ) : __( 'Take it with you', 'chatbot' );
		return $content . "\n" . '<div class="qcwr-downloads"><p class="qcwr-downloads__title">' . esc_html( $title ) . '</p><p class="qcwr-downloads__links">' . trim( $links ) . '</p></div>';
	}

	/**
	 * Address of a download.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $kind Kind.
	 * @return string
	 */
	public static function url( $post, $kind ) {
		return add_query_arg( self::ARG, $kind, get_permalink( $post ) );
	}

	/**
	 * A little style for the box (only on posts that offer downloads).
	 *
	 * @return void
	 */
	public static function style() {
		if ( ! is_singular() || array() === self::offered( get_post() ) ) {
			return;
		}
		wp_register_style( 'qcld-wpbot-writer-downloads', false, array(), QCLD_WPBOT_WRITER_VERSION );
		wp_enqueue_style( 'qcld-wpbot-writer-downloads' );
		wp_add_inline_style( 'qcld-wpbot-writer-downloads', '.qcwr-downloads{border:1px solid currentColor;border-radius:8px;margin:2em 0;padding:1em 1.25em;opacity:.95}.qcwr-downloads__title{font-weight:600;margin:0 0 .5em}.qcwr-downloads__links{display:flex;flex-wrap:wrap;gap:.5em 1em;margin:0}.qcwr-chart-figure svg{max-width:100%;height:auto}.qcwr-checklist{list-style:none;padding-left:0}.qcwr-checklist li::before{content:"\2610";margin-right:.5em}' );
	}

	/**
	 * Serve a download on the post's own address (?qcwr_download=pdf).
	 *
	 * @return void
	 */
	public static function serve() {
		$kind = isset( $_GET[ self::ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only download of a published post.
		if ( '' === $kind || ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! in_array( $kind, self::offered( $post ), true ) ) {
			wp_die( esc_html__( 'This download is not available.', 'chatbot' ), '', array( 'response' => 404 ) );
		}
		/**
		 * Ask for something before a download (for example an email, through
		 * a forms or marketing module). Return a WP_Error to stop the download
		 * with its message, or null to allow it.
		 *
		 * @param WP_Error|null $gate Gate result.
		 * @param WP_Post       $post Post.
		 * @param string        $kind Kind.
		 */
		$gate = Settings::get( 'download_gate' ) ? apply_filters( 'qcld_wpbot_writer/download_gate', null, $post, $kind ) : null;
		if ( is_wp_error( $gate ) ) {
			wp_die( esc_html( $gate->get_error_message() ), '', array( 'response' => 403 ) );
		}
		$file = self::make( $post, $kind );
		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . $file['type'] );
		if ( 'print' !== $kind ) {
			header( 'Content-Disposition: attachment; filename="' . $file['name'] . '"' );
		}
		echo $file['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A file built from escaped or sanitised parts.
		exit;
	}

	/**
	 * Build a download.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $kind Kind.
	 * @return array|WP_Error name, type, body.
	 */
	public static function make( $post, $kind ) {
		$name = sanitize_file_name( $post->post_name ? $post->post_name : 'download-' . $post->ID );
		switch ( $kind ) {
			case 'csv':
				$research = get_post_meta( $post->ID, Research_Data::META, true );
				if ( ! is_array( $research ) || empty( $research['findings'] ) ) {
					return new WP_Error( 'qcld_wpbot_writer_no_research', __( 'This article has no research data.', 'chatbot' ) );
				}
				return array(
					'name' => $name . '-data.csv',
					'type' => 'text/csv; charset=utf-8',
					'body' => "\xEF\xBB\xBF" . Research_Data::to_csv( $research ),
				);
			case 'txt':
				return array(
					'name' => $name . '.txt',
					'type' => 'text/plain; charset=utf-8',
					'body' => self::text( $post ),
				);
			case 'docx':
				$docx = self::docx( $post );
				return is_wp_error( $docx ) ? $docx : array(
					'name' => $name . '.docx',
					'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'body' => $docx,
				);
			case 'print':
				return array(
					'name' => $name . '.html',
					'type' => 'text/html; charset=utf-8',
					'body' => self::printable( $post, true ),
				);
			case 'pdf':
				$pdf = self::pdf( $post );
				return is_wp_error( $pdf ) ? $pdf : array(
					'name' => $name . '.pdf',
					'type' => 'application/pdf',
					'body' => $pdf,
				);
		}
		return new WP_Error( 'qcld_wpbot_writer_download_kind', __( 'Unknown download.', 'chatbot' ) );
	}

	/**
	 * The post as simple parts: headings, paragraphs, lists (checklists
	 * marked) and tables, in order, as plain text.
	 *
	 * @param WP_Post $post Post.
	 * @return array[] type (heading|paragraph|list|table), text|items|rows, level, checklist.
	 */
	public static function parts( $post ) {
		$parts = array();
		self::walk( parse_blocks( (string) $post->post_content ), $parts );
		return $parts;
	}

	/**
	 * Collect parts from blocks.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @param array[] $parts  Parts (by reference).
	 * @return void
	 */
	private static function walk( array $blocks, array &$parts ) {
		foreach ( $blocks as $block ) {
			$name = (string) $block['blockName'];
			if ( 'core/heading' === $name ) {
				$parts[] = array(
					'type'  => 'heading',
					'text'  => self::plain( $block['innerHTML'] ),
					'level' => isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2,
				);
			} elseif ( 'core/paragraph' === $name ) {
				$text = self::plain( $block['innerHTML'] );
				if ( '' !== $text ) {
					$parts[] = array(
						'type' => 'paragraph',
						'text' => $text,
					);
				}
			} elseif ( 'core/list' === $name ) {
				$items = array();
				foreach ( (array) $block['innerBlocks'] as $inner ) {
					$items[] = self::plain( $inner['innerHTML'] );
				}
				if ( array() === $items && preg_match_all( '#<li[^>]*>(.*?)</li>#is', (string) $block['innerHTML'], $m ) ) {
					$items = array_map( array( __CLASS__, 'plain' ), $m[1] );
				}
				$parts[] = array(
					'type'      => 'list',
					'items'     => array_values( array_filter( $items, 'strlen' ) ),
					'ordered'   => ! empty( $block['attrs']['ordered'] ),
					'checklist' => isset( $block['attrs']['className'] ) && false !== strpos( (string) $block['attrs']['className'], 'qcwr-checklist' ),
				);
			} elseif ( 'core/table' === $name ) {
				$rows = array();
				if ( preg_match_all( '#<tr[^>]*>(.*?)</tr>#is', (string) $block['innerHTML'], $trs ) ) {
					foreach ( $trs[1] as $tr ) {
						preg_match_all( '#<t[hd][^>]*>(.*?)</t[hd]>#is', $tr, $cells );
						$rows[] = array_map( array( __CLASS__, 'plain' ), $cells[1] );
					}
				}
				if ( array() !== $rows ) {
					$parts[] = array(
						'type' => 'table',
						'rows' => $rows,
					);
				}
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $parts );
			}
		}
	}

	/**
	 * Plain text version (template .txt).
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function text( $post ) {
		$title = self::plain( get_the_title( $post ) );
		$lines = array( $title, str_repeat( '=', min( 80, max( 3, strlen( $title ) ) ) ), '' );
		foreach ( self::parts( $post ) as $part ) {
			switch ( $part['type'] ) {
				case 'heading':
					$lines[] = '';
					$lines[] = strtoupper( $part['text'] );
					break;
				case 'paragraph':
					$lines[] = wordwrap( $part['text'], 78 );
					break;
				case 'list':
					foreach ( $part['items'] as $i => $item ) {
						$lines[] = ( $part['checklist'] ? '[ ] ' : ( $part['ordered'] ? ( $i + 1 ) . '. ' : '- ' ) ) . $item;
					}
					break;
				case 'table':
					foreach ( $part['rows'] as $row ) {
						$lines[] = implode( "\t", $row );
					}
					break;
			}
			$lines[] = '';
		}
		$lines[] = self::source_line( $post );
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Word document (.docx) written with PHP's zip.
	 *
	 * @param WP_Post $post Post.
	 * @return string|WP_Error
	 */
	public static function docx( $post ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_zip', __( 'This server cannot create Word files (the PHP zip extension is missing).', 'chatbot' ) );
		}
		$body = self::w_para( self::plain( get_the_title( $post ) ), 'Title' );
		foreach ( self::parts( $post ) as $part ) {
			switch ( $part['type'] ) {
				case 'heading':
					$body .= self::w_para( $part['text'], 'Heading' . min( 3, max( 1, $part['level'] - 1 ) ) );
					break;
				case 'paragraph':
					$body .= self::w_para( $part['text'] );
					break;
				case 'list':
					foreach ( $part['items'] as $i => $item ) {
						$body .= self::w_para( ( $part['checklist'] ? '☐ ' : ( $part['ordered'] ? ( $i + 1 ) . '. ' : '• ' ) ) . $item );
					}
					break;
				case 'table':
					$body .= '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr>';
					foreach ( $part['rows'] as $r => $row ) {
						$body .= '<w:tr>';
						foreach ( $row as $cell ) {
							$body .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>' . self::w_para( $cell, '', 0 === $r ) . '</w:tc>';
						}
						$body .= '</w:tr>';
					}
					$body .= '</w:tbl>' . self::w_para( '' );
					break;
			}
		}
		$body .= self::w_para( self::source_line( $post ) );
		$file  = wp_tempnam( 'qcwr-docx' );
		$zip   = new ZipArchive();
		if ( true !== $zip->open( $file, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'qcld_wpbot_writer_docx', __( 'The Word file could not be created.', 'chatbot' ) );
		}
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' );
		$zip->addFromString( 'word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'word/styles.xml', self::w_styles() );
		$zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr></w:body></w:document>' );
		$zip->close();
		$data = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Our own temporary file.
		wp_delete_file( $file );
		return $data;
	}

	/**
	 * One Word paragraph.
	 *
	 * @param string $text  Plain text.
	 * @param string $style Style id ('' for normal).
	 * @param bool   $bold  Bold.
	 * @return string
	 */
	private static function w_para( $text, $style = '', $bold = false ) {
		$props = '' !== $style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '';
		$run   = '' === $text ? '' : '<w:r>' . ( $bold ? '<w:rPr><w:b/></w:rPr>' : '' ) . '<w:t xml:space="preserve">' . htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</w:t></w:r>';
		return '<w:p>' . $props . $run . '</w:p>';
	}

	/**
	 * Word styles: title and three heading levels.
	 *
	 * @return string
	 */
	private static function w_styles() {
		$style = static function ( $id, $name, $size ) {
			return '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="' . $size . '"/></w:rPr></w:style>';
		};
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			. $style( 'Title', 'Title', 40 ) . $style( 'Heading1', 'heading 1', 32 ) . $style( 'Heading2', 'heading 2', 28 ) . $style( 'Heading3', 'heading 3', 24 )
			. '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style></w:styles>';
	}

	/**
	 * Printable page: title, headings, checklists with boxes, tables.
	 *
	 * @param WP_Post $post  Post.
	 * @param bool    $open_dialog Open the print dialog.
	 * @return string
	 */
	public static function printable( $post, $open_dialog = false ) {
		$html = '';
		foreach ( self::parts( $post ) as $part ) {
			switch ( $part['type'] ) {
				case 'heading':
					$level = min( 4, max( 2, $part['level'] ) );
					$html .= '<h' . $level . '>' . esc_html( $part['text'] ) . '</h' . $level . '>';
					break;
				case 'paragraph':
					$html .= '<p>' . esc_html( $part['text'] ) . '</p>';
					break;
				case 'list':
					$tag   = $part['ordered'] && ! $part['checklist'] ? 'ol' : 'ul';
					$html .= '<' . $tag . ( $part['checklist'] ? ' class="check"' : '' ) . '>';
					foreach ( $part['items'] as $item ) {
						$html .= '<li>' . ( $part['checklist'] ? '<span class="box">&#9744;</span> ' : '' ) . esc_html( $item ) . '</li>';
					}
					$html .= '</' . $tag . '>';
					break;
				case 'table':
					$html .= '<table>';
					foreach ( $part['rows'] as $r => $row ) {
						$cell  = 0 === $r ? 'th' : 'td';
						$html .= '<tr><' . $cell . '>' . implode( '</' . $cell . '><' . $cell . '>', array_map( 'esc_html', $row ) ) . '</' . $cell . '></tr>';
					}
					$html .= '</table>';
					break;
			}
		}
		$css = 'body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:11pt;color:#222;margin:2em}h1{font-size:20pt;margin:0 0 .5em}h2{font-size:14pt;margin:1.2em 0 .4em}h3,h4{font-size:12pt}ul.check{list-style:none;padding-left:0}ul.check li{margin:.35em 0}.box{font-size:13pt}table{border-collapse:collapse;width:100%;margin:.5em 0}th,td{border:1px solid #999;padding:4px 6px;text-align:left}.src{color:#666;font-size:9pt;margin-top:2em}';
		return '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><title>' . esc_html( self::plain( get_the_title( $post ) ) ) . '</title><style>' . $css . '</style></head><body><h1>' . esc_html( self::plain( get_the_title( $post ) ) ) . '</h1>' . $html . '<p class="src">' . esc_html( self::source_line( $post ) ) . '</p>'
			. ( $open_dialog ? '<script>window.addEventListener("load",function(){window.print();});</script>' : '' ) . '</body></html>';
	}

	/**
	 * Whether the PDF library can run here.
	 *
	 * @return bool
	 */
	public static function pdf_ready() {
		return is_readable( self::lib() . '/autoload.php' ) && extension_loaded( 'mbstring' ) && extension_loaded( 'dom' );
	}

	/**
	 * The checklist as PDF (Dompdf; remote files, PHP and scripts off).
	 *
	 * @param WP_Post $post Post.
	 * @return string|WP_Error
	 */
	public static function pdf( $post ) {
		if ( ! self::pdf_ready() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_pdf', __( 'PDF files cannot be created on this server (PHP mbstring and dom are needed).', 'chatbot' ) );
		}
		require_once self::lib() . '/autoload.php';
		$uploads = wp_upload_dir( null, false );
		$cache   = trailingslashit( $uploads['basedir'] ) . 'qcld-wpbot-writer/pdf-fonts';
		if ( ! is_dir( $cache ) ) {
			wp_mkdir_p( $cache );
		}
		$options = new \QuantumCloud\AiWriter\Vendor\Dompdf\Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isPhpEnabled', false );
		$options->set( 'isJavascriptEnabled', false );
		$options->set( 'defaultFont', 'DejaVu Sans' );
		$options->setChroot( array( self::lib() ) );
		$options->setTempDir( get_temp_dir() );
		$options->setFontCache( wp_is_writable( $cache ) ? $cache : get_temp_dir() );
		try {
			$dompdf = new \QuantumCloud\AiWriter\Vendor\Dompdf\Dompdf( $options );
			$dompdf->loadHtml( self::printable( $post, false ), 'UTF-8' );
			$dompdf->setPaper( 'A4' );
			$dompdf->render();
			return (string) $dompdf->output();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'qcld_wpbot_writer_pdf', __( 'The PDF could not be created.', 'chatbot' ) );
		}
	}

	/**
	 * Where the scoped Dompdf lives.
	 *
	 * @return string
	 */
	private static function lib() {
		return dirname( __DIR__, 2 ) . '/lib/dompdf';
	}

	/**
	 * "From <site>: <address>" under every download.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function source_line( $post ) {
		/* translators: 1: site name, 2: address. */
		return sprintf( __( 'From %1$s: %2$s', 'chatbot' ), self::plain( get_bloginfo( 'name' ) ), get_permalink( $post ) );
	}

	/**
	 * Plain text from HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function plain( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
