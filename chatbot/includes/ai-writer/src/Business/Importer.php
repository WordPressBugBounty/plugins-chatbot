<?php
/**
 * CR-15: fill the Persuasion Kit and Customer words from the owner's own
 * content — pages and posts of the site, any web page, or an uploaded
 * document — instead of typing every item.
 *
 * Flow: estimate (cost first) → one source at a time: read its text, one AI
 * call proposes items → the owner ticks → apply saves only ticked items
 * from the stored proposal. A quote or customer phrase is proposed only when
 * its exact words are in the source text (checked here, not by the AI).
 * PDFs go to the AI provider as a document (no PDF library); their items
 * can't be checked word for word and are marked so.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Business;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Regenerate\Regenerate;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\Library\Document_Text;
use QuantumCloud\WPBotCore\Library\Page_Reader;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer {

	/**
	 * What can be filled.
	 */
	const TARGETS = array( 'kit', 'words' );

	/**
	 * Proposals kept per user and target (a day).
	 */
	const TRANSIENT = 'qcwr_import_';

	/**
	 * Most items kept from one source.
	 */
	const MAX_ITEMS = 30;

	/**
	 * Kit types the AI may propose. Offers are left out: they need a real end date.
	 */
	const KIT_TYPES = array( 'review', 'testimonial', 'customer_count', 'case_study', 'certification', 'media_mention', 'years_in_business', 'free_offer', 'guarantee', 'founder_story', 'values', 'local_identity' );

	/**
	 * Quote types (checked word for word; permission is the owner's tick).
	 */
	const QUOTES = array( 'review', 'testimonial' );

	/**
	 * Whether imports can run (needs the readers from WPBot Core).
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Page_Reader::class ) && class_exists( Document_Text::class ) && function_exists( 'qcld_wpbot_core_kit_save' ) && function_exists( 'qcld_wpbot_core_voc_add' );
	}

	/**
	 * Most pages in one import (Pro raises it).
	 *
	 * @return int
	 */
	public static function max_pages() {
		$chosen = absint( Settings::get( 'import_max_pages' ) );
		if ( $chosen > 0 ) {
			// The owner's own number (Brand & Business, Settings level).
			return min( 100, $chosen );
		}
		return self::default_max_pages();
	}

	/**
	 * Pages per import while the owner has not set a number.
	 *
	 * @return int
	 */
	public static function default_max_pages() {
		/**
		 * Default for the most pages and web addresses read in one import,
		 * while the owner has not set a number (Settings → Writing).
		 *
		 * @param int $max Default 5.
		 */
		return max( 1, min( 100, (int) apply_filters( 'qcld_wpbot_writer/import_max_pages', 5 ) ) );
	}

	/**
	 * Allowed upload extensions.
	 *
	 * @return string[]
	 */
	public static function types() {
		/**
		 * File types that can be uploaded for an import.
		 *
		 * @param string[] $types Extensions.
		 */
		$types = (array) apply_filters( 'qcld_wpbot_writer/import_types', array( 'docx', 'txt', 'md', 'csv', 'pdf' ) );
		return array_values( array_intersect( array_map( 'strtolower', $types ), array( 'docx', 'txt', 'md', 'csv', 'pdf' ) ) );
	}

	/**
	 * Whether the active AI provider reads PDF documents.
	 *
	 * @return bool
	 */
	public static function reads_pdf() {
		if ( ! method_exists( Providers::class, 'reads_pdf' ) ) {
			return false;
		}
		$order = Keys::call_order( Module::ID );
		return isset( $order[0] ) && Providers::reads_pdf( $order[0] );
	}

	/**
	 * Cost of an import, shown before anything is read.
	 *
	 * @param int $sources Pages, addresses and files.
	 * @return array usd, calls, model, max_pages, reads_pdf, types.
	 */
	public static function estimate( $sources ) {
		$sources = max( 1, (int) $sources );
		$one     = Regenerate::estimate( 'import', $sources );
		return array(
			'usd'       => (float) $one['usd'],
			'calls'     => $sources,
			'model'     => $one['model'],
			'max_pages' => self::max_pages(),
			'reads_pdf' => self::reads_pdf(),
			'types'     => self::types(),
		);
	}

	/**
	 * Text of a source: a post or page of this site, or a web address.
	 *
	 * @param array $source type (post|url), id, url.
	 * @return array|WP_Error label, url, text.
	 */
	public static function read_source( array $source ) {
		$type = isset( $source['type'] ) ? sanitize_key( (string) $source['type'] ) : '';
		if ( 'post' === $type ) {
			$post = get_post( isset( $source['id'] ) ? absint( $source['id'] ) : 0 );
			if ( ! $post || ! current_user_can( 'read_post', $post->ID ) ) {
				return new WP_Error( 'qcld_wpbot_writer_import_post', __( 'This page could not be found.', 'chatbot' ), array( 'status' => 404 ) );
			}
			$text = Page_Reader::html_to_text( do_blocks( strip_shortcodes( (string) $post->post_content ) ) );
			// Reviews and comments left on it (approved only) are the customers' own words.
			$comments = get_comments(
				array(
					'post_id' => $post->ID,
					'status'  => 'approve',
					'number'  => 50,
				)
			);
			foreach ( $comments as $comment ) {
				$text .= "\n" . Page_Reader::clean_text( $comment->comment_content ) . ' — ' . sanitize_text_field( $comment->comment_author );
			}
			return array(
				'label' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $post ),
				'text'  => $text,
			);
		}
		if ( 'url' === $type ) {
			$page = Page_Reader::read( isset( $source['url'] ) ? (string) $source['url'] : '' );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			return array(
				'label' => '' !== $page['title'] ? $page['title'] : $page['url'],
				'url'   => $page['url'],
				'text'  => $page['text'],
			);
		}
		return new WP_Error( 'qcld_wpbot_writer_import_source', __( 'Unknown source.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * Text (or the PDF itself) of an uploaded file. The file is deleted here.
	 *
	 * @param string $path Uploaded file path.
	 * @param string $name Original name.
	 * @return array|WP_Error label, url (''), text, pdf (base64, PDFs only).
	 */
	public static function read_file( $path, $name ) {
		$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
		$out = array(
			'label' => sanitize_file_name( (string) $name ),
			'url'   => '',
			'text'  => '',
			'pdf'   => '',
		);
		if ( 'pdf' === $ext ) {
			if ( ! self::reads_pdf() ) {
				wp_delete_file( $path );
				return new WP_Error( 'qcld_wpbot_writer_import_pdf', __( 'Your AI provider can\'t read PDF files. Save the document as Word (.docx) or text and upload that, or use OpenAI, Claude, Gemini or OpenRouter.', 'chatbot' ), array( 'status' => 415 ) );
			}
			$data = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload, read once.
			wp_delete_file( $path );
			if ( 0 !== strpos( $data, '%PDF' ) ) {
				return new WP_Error( 'qcld_wpbot_writer_import_pdf_bad', __( 'This file is not a PDF.', 'chatbot' ), array( 'status' => 415 ) );
			}
			$out['pdf'] = base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The AI provider receives the document as base64.
			return $out;
		}
		$read = Document_Text::read( $path, $name );
		wp_delete_file( $path );
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		$out['text'] = $read['text'];
		return $out;
	}

	/**
	 * Ask the AI for items from one source and keep the ones that check out.
	 *
	 * @param string $target kit or words.
	 * @param array  $source label, url, text, pdf.
	 * @return array|WP_Error items (new proposals), dropped (count), label.
	 */
	public static function propose( $target, array $source ) {
		$target = in_array( $target, self::TARGETS, true ) ? $target : 'kit';
		$text   = isset( $source['text'] ) ? (string) $source['text'] : '';
		$pdf    = isset( $source['pdf'] ) ? (string) $source['pdf'] : '';
		if ( '' === trim( $text ) && '' === $pdf ) {
			return new WP_Error( 'qcld_wpbot_writer_import_empty', __( 'No text was found there.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$system = 'kit' === $target ? self::kit_system() : self::words_system();
		$prompt = 'Source: ' . sanitize_text_field( (string) $source['label'] ) . "\n" . Prompts::business() . "\n\n"
			. ( '' !== $pdf ? 'The source is the attached PDF document.' : "Source text:\n" . Prompts::clip( $text, 40000 ) );
		$args   = array(
			'max_tokens' => 1800,
			'cache'      => false,
		);
		if ( '' !== $pdf ) {
			$args['images'] = array(
				array(
					'mime' => 'application/pdf',
					'data' => $pdf,
				),
			);
		}
		$result = Writer_AI::ask_json( 'business_import', $system, $prompt, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$raw     = isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array();
		$checked = '' === $pdf;
		$items   = array();
		$dropped = 0;
		foreach ( array_slice( $raw, 0, self::MAX_ITEMS ) as $item ) {
			$clean = 'kit' === $target ? self::clean_kit( $item, $text, $checked ) : self::clean_words( $item, $text, $checked );
			if ( null === $clean ) {
				++$dropped;
				continue;
			}
			$clean['source_label'] = sanitize_text_field( (string) $source['label'] );
			$clean['source_url']   = esc_url_raw( (string) $source['url'] );
			$clean['checked']      = $checked;
			$clean['id']           = substr( md5( $target . wp_json_encode( $clean ) ), 0, 12 );
			$items[]               = $clean;
		}
		$items = self::without_known( $target, $items );
		self::remember( $target, $items );
		return array(
			'items'   => $items,
			'dropped' => $dropped,
			'label'   => sanitize_text_field( (string) $source['label'] ),
		);
	}

	/**
	 * Save the ticked proposals.
	 *
	 * @param string   $target     kit or words.
	 * @param string[] $ids      Proposal ids.
	 * @param string[] $quotable Ids of quotes the owner may publish.
	 * @return array saved, failed (messages).
	 */
	public static function apply( $target, array $ids, array $quotable = array() ) {
		$target  = in_array( $target, self::TARGETS, true ) ? $target : 'kit';
		$ids     = array_map( 'sanitize_key', $ids );
		$stored  = self::stored( $target );
		$saved   = 0;
		$failed  = array();
		$phrases = array();
		$left    = array();
		foreach ( $stored as $item ) {
			if ( ! in_array( $item['id'], $ids, true ) ) {
				$left[] = $item;
				continue;
			}
			if ( 'words' === $target ) {
				$phrases[] = $item['phrase'];
				continue;
			}
			$entry = qcld_wpbot_core_kit_save(
				array(
					'type'       => $item['type'],
					'label'      => $item['label'],
					'body'       => $item['body'],
					'value'      => $item['value'],
					'source'     => '' !== $item['who'] ? $item['who'] : $item['source_label'],
					'url'        => $item['source_url'],
					'permission' => in_array( $item['type'], self::QUOTES, true ) && in_array( $item['id'], array_map( 'sanitize_key', $quotable ), true ),
				)
			);
			if ( is_wp_error( $entry ) ) {
				$failed[] = $item['label'] . ': ' . $entry->get_error_message();
				$left[]   = $item;
			} else {
				++$saved;
			}
		}
		if ( array() !== $phrases ) {
			$saved += (int) qcld_wpbot_core_voc_add( $phrases, 'import' );
		}
		self::save_stored( $target, $left );
		return array(
			'saved'  => $saved,
			'failed' => $failed,
			'left'   => $left,
		);
	}

	/**
	 * Proposals waiting for the owner.
	 *
	 * @param string $target kit or words.
	 * @return array[]
	 */
	public static function stored( $target ) {
		$list = get_transient( self::TRANSIENT . $target . '_' . get_current_user_id() );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Forget the waiting proposals.
	 *
	 * @param string $target kit or words.
	 * @return void
	 */
	public static function clear( $target ) {
		delete_transient( self::TRANSIENT . $target . '_' . get_current_user_id() );
	}

	/**
	 * Add new proposals to the waiting ones (no duplicates; 200 at most).
	 *
	 * @param string  $target kit or words.
	 * @param array[] $items  Proposals.
	 * @return void
	 */
	private static function remember( $target, array $items ) {
		$list = self::stored( $target );
		$have = wp_list_pluck( $list, 'id' );
		foreach ( $items as $item ) {
			if ( ! in_array( $item['id'], $have, true ) ) {
				$list[] = $item;
			}
		}
		self::save_stored( $target, array_slice( $list, -200 ) );
	}

	/**
	 * Store the waiting proposals.
	 *
	 * @param string  $target kit or words.
	 * @param array[] $items  Proposals.
	 * @return void
	 */
	private static function save_stored( $target, array $items ) {
		set_transient( self::TRANSIENT . $target . '_' . get_current_user_id(), array_values( $items ), DAY_IN_SECONDS );
	}

	/**
	 * A kit proposal, or null when it is invalid or its words aren't in the source.
	 *
	 * @param mixed  $raw     Raw item.
	 * @param string $text    Source text.
	 * @param bool   $checked Check the words against the text.
	 * @return array|null
	 */
	private static function clean_kit( $raw, $text, $checked ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : '';
		if ( ! in_array( $type, self::KIT_TYPES, true ) ) {
			return null;
		}
		$item = array(
			'type'     => $type,
			'label'    => self::cut( sanitize_text_field( isset( $raw['label'] ) ? (string) $raw['label'] : '' ), 120 ),
			'body'     => self::cut( sanitize_textarea_field( isset( $raw['body'] ) ? (string) $raw['body'] : '' ), 1000 ),
			'value'    => self::cut( sanitize_text_field( isset( $raw['value'] ) ? (string) $raw['value'] : '' ), 100 ),
			'who'      => self::cut( sanitize_text_field( isset( $raw['who'] ) ? (string) $raw['who'] : '' ), 120 ),
			'evidence' => self::cut( sanitize_textarea_field( isset( $raw['evidence'] ) ? (string) $raw['evidence'] : '' ), 600 ),
		);
		if ( '' === $item['body'] && '' === $item['value'] ) {
			return null;
		}
		if ( '' === $item['label'] ) {
			$item['label'] = self::cut( '' !== $item['body'] ? $item['body'] : $item['value'], 80 );
		}
		if ( $checked ) {
			// Quotes must be the customer's exact words; other items must cite a sentence that is really there.
			$words = in_array( $type, self::QUOTES, true ) ? $item['body'] : ( '' !== $item['evidence'] ? $item['evidence'] : $item['body'] );
			if ( ! self::found( $words, $text ) ) {
				return null;
			}
		}
		return $item;
	}

	/**
	 * A customer phrase, or null when its words aren't in the source.
	 *
	 * @param mixed  $raw     Raw item.
	 * @param string $text    Source text.
	 * @param bool   $checked Check the words against the text.
	 * @return array|null
	 */
	private static function clean_words( $raw, $text, $checked ) {
		$phrase = is_array( $raw ) && isset( $raw['phrase'] ) ? trim( sanitize_text_field( (string) $raw['phrase'] ) ) : '';
		$count  = count( preg_split( '/\s+/u', $phrase ) );
		if ( '' === $phrase || $count < 2 || $count > 40 ) {
			return null;
		}
		if ( $checked && ! self::found( $phrase, $text ) ) {
			return null;
		}
		return array(
			'phrase'   => self::cut( $phrase, 300 ),
			'evidence' => self::cut( sanitize_textarea_field( isset( $raw['evidence'] ) ? (string) $raw['evidence'] : '' ), 600 ),
		);
	}

	/**
	 * Whether words appear in a text (case, spacing, quote marks and dashes ignored).
	 *
	 * @param string $needle Words.
	 * @param string $text   Text.
	 * @return bool
	 */
	public static function found( $needle, $text ) {
		$needle = self::norm( $needle );
		return strlen( $needle ) >= 8 && false !== strpos( self::norm( $text ), $needle );
	}

	/**
	 * Lower case, plain quotes and dashes, single spaces, no edge punctuation.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function norm( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( array( "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{00A0}" ), array( "'", "'", '"', '"', '-', '-', ' ' ), $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text, " \t\n\r\0\x0B\"'.,;:!?-" );
	}

	/**
	 * Proposals that are not already in the Kit or the customer words.
	 *
	 * @param string  $target kit or words.
	 * @param array[] $items  Proposals.
	 * @return array[]
	 */
	private static function without_known( $target, array $items ) {
		$known = array();
		if ( 'kit' === $target && function_exists( 'qcld_wpbot_core_persuasion_kit' ) ) {
			$kit = (array) qcld_wpbot_core_persuasion_kit( array( 'include_expired' => true ) );
			foreach ( isset( $kit['entries'] ) ? (array) $kit['entries'] : array() as $entry ) {
				$known[] = self::norm( isset( $entry['body'] ) && '' !== $entry['body'] ? $entry['body'] : ( isset( $entry['value'] ) ? $entry['value'] : '' ) );
			}
		} elseif ( 'words' === $target && function_exists( 'qcld_wpbot_core_voice_of_customer' ) ) {
			foreach ( (array) qcld_wpbot_core_voice_of_customer( array( 'limit' => 500 ) ) as $row ) {
				$known[] = self::norm( isset( $row['phrase'] ) ? $row['phrase'] : '' );
			}
		}
		return array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $target, $known ) {
					$key = self::norm( 'words' === $target ? $item['phrase'] : ( '' !== $item['body'] ? $item['body'] : $item['value'] ) );
					return ! in_array( $key, $known, true );
				}
			)
		);
	}

	/**
	 * System prompt: proof for the Persuasion Kit.
	 *
	 * @return string
	 */
	private static function kit_system() {
		return "Task: business_import\nTarget: kit\n"
			. 'You collect proof a small business can use honestly in its marketing, from its own content. Find only what the source really says: '
			. 'reviews and testimonials (the customer\'s exact words), numbers of customers or jobs, results for customers, certifications and awards, media mentions, year founded, free offers, guarantees and return policies, the founder\'s story, values, and local identity. '
			. 'Never invent, round or improve anything. Copy quotes word for word. For every item, copy into "evidence" the exact sentence from the source it comes from. Leave out time-limited offers.'
			. "\nAnswer with JSON: {\"items\": [{\"type\": \"" . implode( '|', self::KIT_TYPES ) . '", "label": "short title", "body": "the quote or the claim, as written", "value": "rating, number, year, period or name, as the type needs", "who": "who said it (name or role) if the source says", "evidence": "exact sentence from the source"}]}. Return {"items": []} when there is nothing.';
	}

	/**
	 * System prompt: customer words.
	 *
	 * @return string
	 */
	private static function words_system() {
		return "Task: business_import\nTarget: words\n"
			. 'You collect the words customers themselves use: phrases from reviews, testimonials, questions and comments that show how customers describe their problems, needs and results. '
			. 'Leave out the business\'s own marketing copy and anything personal (names, emails, phone numbers). Copy each phrase word for word from the source (2 to 20 words).'
			. "\nAnswer with JSON: {\"items\": [{\"phrase\": \"exact words\", \"evidence\": \"the sentence it is in\"}]}. Return {\"items\": []} when there is nothing.";
	}

	/**
	 * Cut a string at a character limit.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Limit.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $chars, 'UTF-8' ) : substr( (string) $text, 0, $chars );
	}
}
