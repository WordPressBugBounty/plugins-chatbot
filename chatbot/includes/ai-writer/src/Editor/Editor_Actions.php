<?php
/**
 * One-call AI actions for the edit screen: titles, excerpt, block actions,
 * writing at a spot (/ai), meta, FAQ, summary, alt text, chat, variations
 * and targeted rewrites of flagged sentences.
 *
 * Every action returns a preview only. The editor shows it, and the owner
 * replaces, inserts or discards it; nothing here saves the post.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Editor;

use QuantumCloud\AiWriter\Output\Block_Builder;
use QuantumCloud\AiWriter\Output\Kses_Policy;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Edit-screen actions.
 */
final class Editor_Actions {

	/**
	 * Block actions and their instructions.
	 *
	 * @return array<string,string>
	 */
	public static function transforms() {
		return array(
			'simplify'  => 'Rewrite it in simpler words and shorter sentences. Keep the meaning and every fact.',
			'shorten'   => 'Make it about 40% shorter. Keep the key facts and the meaning.',
			'expand'    => 'Expand it by about half with concrete detail that follows from the text and the post. Do not invent facts, numbers, names or quotes; where a real detail is needed, write a short placeholder in square brackets.',
			'grammar'   => 'Fix spelling, grammar and punctuation only. Change nothing else.',
			'translate' => 'Translate it into %s. Keep the meaning and the formatting tags.',
			'natural'   => 'Make it sound natural, like a person wrote it: vary sentence length, use contractions, remove clichés and filler. Keep the meaning and every fact.',
			'tone'      => 'Rewrite it in a %s tone. Keep the meaning and every fact.',
		);
	}

	/**
	 * Tones offered by "Change tone".
	 *
	 * @var string[]
	 */
	const TONES = array( 'friendly', 'professional', 'confident', 'playful', 'empathetic', 'direct' );

	/**
	 * Title suggestions.
	 *
	 * @param array $ctx Context (see context()).
	 * @return array|WP_Error titles.
	 */
	public static function titles( array $ctx ) {
		$result = self::json(
			'editor_titles',
			'titles',
			'Suggest 5 titles for this piece. Each under 65 characters, specific, no clickbait, no colon-and-subtitle pattern in more than one. Answer with JSON: {"titles": ["..."]}.',
			self::describe( $ctx, 6000 ),
			$ctx,
			array(
				'mode'       => 'creative',
				'max_tokens' => 400,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'titles' => self::strings( isset( $result['titles'] ) ? $result['titles'] : array(), 5, 150 ) );
	}

	/**
	 * Excerpt (separate from the SEO description).
	 *
	 * @param array $ctx Context.
	 * @return array|WP_Error excerpt.
	 */
	public static function excerpt( array $ctx ) {
		$result = self::json(
			'editor_excerpt',
			'excerpt',
			'Write the excerpt shown in post lists: one or two sentences, at most 55 words, that tell the reader what they get. No "In this article". Answer with JSON: {"excerpt": "..."}.',
			self::describe( $ctx, 6000 ),
			$ctx,
			array( 'max_tokens' => 300 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'excerpt' => sanitize_textarea_field( isset( $result['excerpt'] ) ? (string) $result['excerpt'] : '' ) );
	}

	/**
	 * Rewrite one block's text (or a selected part of it).
	 *
	 * @param string $html   Inline HTML of the text.
	 * @param string $action Key of transforms().
	 * @param string $option Language or tone.
	 * @param array  $ctx    Context.
	 * @return array|WP_Error html.
	 */
	public static function transform( $html, $action, $option, array $ctx ) {
		$all = self::transforms();
		if ( ! isset( $all[ $action ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_action', __( 'Unknown action.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$instruction = $all[ $action ];
		if ( 'translate' === $action ) {
			$language = Prompts::cut( sanitize_text_field( $option ), 60 );
			if ( '' === $language ) {
				return new WP_Error( 'qcld_wpbot_writer_no_language', __( 'Choose a language.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$instruction = sprintf( $instruction, $language );
		} elseif ( 'tone' === $action ) {
			$tone        = in_array( $option, self::TONES, true ) ? $option : 'friendly';
			$instruction = sprintf( $instruction, $tone );
		}
		$html = Kses_Policy::inline( $html );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_empty', __( 'Select some text first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = self::json(
			'editor_' . $action,
			'block_' . $action,
			$instruction . ' The text may contain inline HTML (links, bold, italics): keep every link and its address exactly. Return only the new text, no heading, no quotes around it. Answer with JSON: {"html": "..."}.',
			'Text to rewrite:' . "\n" . $html . "\n\n" . 'For context, the post:' . "\n" . self::describe( $ctx, 1500 ),
			$ctx,
			array(
				'mode'       => in_array( $action, array( 'grammar', 'translate' ), true ) ? 'strict' : 'creative',
				'max_tokens' => 'expand' === $action ? 1600 : 1000,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$out = Kses_Policy::inline( isset( $result['html'] ) ? (string) $result['html'] : '' );
		if ( '' === trim( wp_strip_all_tags( $out ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return array( 'html' => $out );
	}

	/**
	 * Write at a spot in the post (the /ai block and the Classic Editor box).
	 *
	 * @param string $prompt What to write.
	 * @param string $mode   write, continue or rewrite.
	 * @param array  $around before, after, selected (plain text).
	 * @param bool   $blocks Block markup (true) or Classic Editor HTML.
	 * @param array  $ctx    Context.
	 * @return array|WP_Error content, preview, words.
	 */
	public static function write_here( $prompt, $mode, array $around, $blocks, array $ctx ) {
		$prompt = Prompts::cut( sanitize_textarea_field( $prompt ), 2000 );
		$mode   = in_array( $mode, array( 'write', 'continue', 'rewrite' ), true ) ? $mode : 'write';
		if ( '' === $prompt && 'write' === $mode ) {
			return new WP_Error( 'qcld_wpbot_writer_no_prompt', __( 'Say what to write.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$tasks = array(
			'write'    => 'Write what the owner asks for, to be placed at this spot in the post.',
			'continue' => 'Continue the post from this spot: write the next part so it follows on naturally from the text before it.',
			'rewrite'  => 'Rewrite the selected text as the owner asks.',
		);
		$input = 'Owner request: ' . ( '' !== $prompt ? $prompt : '(none: use your judgement)' ) . "\n";
		foreach ( array(
			'before'   => 'Text before this spot',
			'selected' => 'Selected text',
			'after'    => 'Text after this spot',
		) as $key => $label ) {
			if ( ! empty( $around[ $key ] ) ) {
				$input .= "\n" . $label . ":\n" . Prompts::clip( sanitize_textarea_field( (string) $around[ $key ] ), 3000 ) . "\n";
			}
		}
		$input .= "\nThe post:\n" . self::describe( $ctx, 2500 );

		$result = self::json(
			'editor_write_here',
			'write_here',
			$tasks[ $mode ] . ' Unless asked otherwise, write 80 to 300 words. Answer with JSON: {"blocks": [{"type": "paragraph", "text": "..."}, {"type": "heading", "text": "...", "level": 2}, {"type": "list", "items": ["..."], "ordered": false}]}. Inline HTML allowed in text: <strong>, <em>, <a href>.',
			$input,
			$ctx,
			array(
				'mode'       => 'creative',
				'max_tokens' => 1800,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = Block_Builder::normalise( isset( $result['blocks'] ) ? $result['blocks'] : array() );
		if ( array() === $items ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$html = Block_Builder::to_html( $items );
		return array(
			'content' => $blocks ? Block_Builder::to_blocks( $items ) : $html,
			'preview' => $html,
			'words'   => Prompts::word_count( wp_strip_all_tags( $html ) ),
		);
	}

	/**
	 * Meta title and description.
	 *
	 * @param array  $ctx   Context.
	 * @param string $focus Optional focus phrase.
	 * @return array|WP_Error meta_title, meta_description.
	 */
	public static function meta( array $ctx, $focus = '' ) {
		$focus  = Prompts::cut( sanitize_text_field( $focus ), 100 );
		$result = self::json(
			'editor_meta',
			'meta',
			'Write the search result title (at most 60 characters) and meta description (120 to 155 characters) for this piece. Specific and honest, with a reason to click; no clickbait.' . ( '' !== $focus ? ' Use the phrase "' . $focus . '" naturally in both.' : '' ) . ' Answer with JSON: {"meta_title": "...", "meta_description": "..."}.',
			self::describe( $ctx, 6000 ),
			$ctx,
			array( 'max_tokens' => 400 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'meta_title'       => sanitize_text_field( isset( $result['meta_title'] ) ? (string) $result['meta_title'] : '' ),
			'meta_description' => sanitize_text_field( isset( $result['meta_description'] ) ? (string) $result['meta_description'] : '' ),
		);
	}

	/**
	 * FAQ from the post (answers only from what the post says).
	 *
	 * @param array $ctx Context.
	 * @return array|WP_Error items, content, preview.
	 */
	public static function faq( array $ctx ) {
		$result = self::json(
			'editor_faq',
			'faq',
			'Write 3 to 5 questions readers ask about this topic, with short answers (2 to 4 sentences) based only on what the post says. Skip questions the post cannot answer. Answer with JSON: {"items": [{"q": "...", "a": "..."}]}.',
			self::describe( $ctx, 8000 ),
			$ctx,
			array( 'max_tokens' => 1200 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = array();
		foreach ( isset( $result['items'] ) ? (array) $result['items'] : array() as $item ) {
			$q = Kses_Policy::plain( isset( $item['q'] ) ? (string) $item['q'] : '' );
			$a = Kses_Policy::inline( isset( $item['a'] ) ? (string) $item['a'] : '' );
			if ( '' !== $q && '' !== $a ) {
				$items[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
		if ( array() === $items ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'No questions could be written from this post yet. Add more content first.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$blocks = array(
			array(
				'type'  => 'heading',
				'text'  => __( 'Frequently asked questions', 'chatbot' ),
				'level' => 2,
			),
		);
		foreach ( $items as $item ) {
			$blocks[] = array(
				'type'  => 'heading',
				'text'  => $item['q'],
				'level' => 3,
			);
			$blocks[] = array(
				'type' => 'paragraph',
				'text' => $item['a'],
			);
		}
		return self::package( $blocks ) + array( 'items' => $items );
	}

	/**
	 * Short summary to put at the top.
	 *
	 * @param array $ctx Context.
	 * @return array|WP_Error summary, key_points, content, preview.
	 */
	public static function summary( array $ctx ) {
		$result = self::json(
			'editor_summary',
			'summary',
			'Summarise the post for a reader in a hurry: one or two sentences, then 3 to 5 key points. Only what the post says. Answer with JSON: {"summary": "...", "key_points": ["..."]}.',
			self::describe( $ctx, 8000 ),
			$ctx,
			array( 'max_tokens' => 600 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$summary = Kses_Policy::plain( isset( $result['summary'] ) ? (string) $result['summary'] : '' );
		$points  = self::strings( isset( $result['key_points'] ) ? $result['key_points'] : array(), 5, 300 );
		if ( '' === $summary ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$blocks = array(
			array(
				'type' => 'paragraph',
				'text' => '<strong>' . esc_html__( 'In short:', 'chatbot' ) . '</strong> ' . esc_html( $summary ),
			),
		);
		if ( array() !== $points ) {
			$blocks[] = array(
				'type'    => 'list',
				'items'   => array_map( 'esc_html', $points ),
				'ordered' => false,
			);
		}
		return self::package( $blocks ) + array(
			'summary'    => $summary,
			'key_points' => $points,
		);
	}

	/**
	 * Alt text for images, written from what is known about each image (its
	 * file name, title, caption and the text around it).
	 *
	 * @param array[] $images id, context.
	 * @param array   $ctx    Context.
	 * @return array|WP_Error alts: id => alt.
	 */
	public static function alt_text( array $images, array $ctx ) {
		$lines = array();
		$ids   = array();
		foreach ( array_slice( $images, 0, 20 ) as $image ) {
			$id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$ids[]   = $id;
			$lines[] = sprintf(
				'Image %d: file "%s", title "%s", caption "%s", nearby text "%s"',
				$id,
				wp_basename( (string) get_attached_file( $id ) ),
				get_the_title( $id ),
				wp_strip_all_tags( (string) wp_get_attachment_caption( $id ) ),
				Prompts::clip( sanitize_text_field( isset( $image['context'] ) ? (string) $image['context'] : '' ), 300 )
			);
		}
		if ( array() === $ids ) {
			return new WP_Error( 'qcld_wpbot_writer_no_images', __( 'There are no images from the Media Library to describe.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = self::json(
			'editor_alt',
			'alt_text',
			'Write alt text for each image: what it most likely shows, at most 120 characters, no "image of". Base it on the details given; do not guess brand names or people\'s names. Answer with JSON: {"alts": [{"id": 0, "alt": "..."}]}.',
			implode( "\n", $lines ) . "\n\nThe post title: " . $ctx['title'],
			$ctx,
			array( 'max_tokens' => 800 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$alts = array();
		foreach ( isset( $result['alts'] ) ? (array) $result['alts'] : array() as $entry ) {
			$id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
			if ( in_array( $id, $ids, true ) && ! empty( $entry['alt'] ) ) {
				$alts[ $id ] = Prompts::cut( sanitize_text_field( (string) $entry['alt'] ), 200 );
			}
		}
		return array( 'alts' => $alts );
	}

	/**
	 * AI chat about the post, in the site voice.
	 *
	 * @param array[] $messages role (user|assistant), content.
	 * @param array   $ctx      Context.
	 * @return array|WP_Error reply.
	 */
	public static function chat( array $messages, array $ctx ) {
		$history = array();
		foreach ( array_slice( $messages, -10 ) as $message ) {
			$role = isset( $message['role'] ) && 'assistant' === $message['role'] ? 'Assistant' : 'Owner';
			$text = Prompts::clip( sanitize_textarea_field( isset( $message['content'] ) ? (string) $message['content'] : '' ), 2000 );
			if ( '' !== $text ) {
				$history[] = $role . ': ' . $text;
			}
		}
		if ( array() === $history ) {
			return new WP_Error( 'qcld_wpbot_writer_no_prompt', __( 'Type a message first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$system   = self::system( 'chat', 'You are a writing assistant inside the WordPress editor. You know the post below and the owner\'s voice. Answer the owner\'s last message helpfully and briefly. When you write text for the post, write it in the voice. Plain text; no markdown headings.', $ctx );
		$response = Writer_AI::ask(
			'editor_chat',
			$system,
			"The post:\n" . self::describe( $ctx, 6000 ) . "\n\nConversation:\n" . implode( "\n", $history ) . "\nAssistant:",
			array(
				'mode'       => 'creative',
				'max_tokens' => 1000,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array( 'reply' => sanitize_textarea_field( trim( (string) $response->text ) ) );
	}

	/**
	 * Variations: headlines, hooks, calls to action or meta.
	 *
	 * @param string $kind headline, hook, cta or meta.
	 * @param array  $ctx  Context.
	 * @return array|WP_Error options.
	 */
	public static function variations( $kind, array $ctx ) {
		$asks = array(
			'headline' => 'Write 5 different headlines (under 65 characters), each with a different angle.',
			'hook'     => 'Write 5 different opening lines (one or two sentences each) that make the reader want to continue. No questions in more than two of them.',
			'cta'      => 'Write 5 different calls to action (one sentence each) that fit this post and the business. No pressure tactics.',
			'meta'     => 'Write 5 different pairs of search result title (at most 60 characters) and meta description (120 to 155 characters).',
		);
		if ( ! isset( $asks[ $kind ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_action', __( 'Unknown action.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$shape  = 'meta' === $kind ? '{"options": [{"title": "...", "description": "..."}]}' : '{"options": ["..."]}';
		$result = self::json(
			'editor_variations',
			'variations_' . $kind,
			$asks[ $kind ] . ' Answer with JSON: ' . $shape . '.',
			self::describe( $ctx, 5000 ),
			$ctx,
			array(
				'mode'       => 'creative',
				'max_tokens' => 900,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$options = array();
		foreach ( isset( $result['options'] ) ? (array) $result['options'] : array() as $option ) {
			if ( 'meta' === $kind && is_array( $option ) ) {
				$title = sanitize_text_field( isset( $option['title'] ) ? (string) $option['title'] : '' );
				$desc  = sanitize_text_field( isset( $option['description'] ) ? (string) $option['description'] : '' );
				if ( '' !== $title || '' !== $desc ) {
					$options[] = array(
						'title'       => $title,
						'description' => $desc,
					);
				}
			} elseif ( is_scalar( $option ) && '' !== trim( (string) $option ) ) {
				$options[] = sanitize_text_field( (string) $option );
			}
		}
		return array( 'options' => array_slice( $options, 0, 5 ) );
	}

	/**
	 * Targeted rewrites of flagged sentences only. A rewrite is dropped when
	 * it loses a number or a quoted passage from the original.
	 *
	 * @param array[] $items sentence, reason.
	 * @param array   $ctx   Context.
	 * @return array|WP_Error rewrites: [{original, rewrite}], skipped: [{original, reason}].
	 */
	public static function rewrite_sentences( array $items, array $ctx ) {
		$list = array();
		foreach ( array_slice( $items, 0, 15 ) as $item ) {
			$sentence = sanitize_text_field( isset( $item['sentence'] ) ? (string) $item['sentence'] : '' );
			if ( '' !== $sentence ) {
				$list[] = array(
					'sentence' => $sentence,
					'reason'   => sanitize_text_field( isset( $item['reason'] ) ? (string) $item['reason'] : '' ),
				);
			}
		}
		if ( array() === $list ) {
			return new WP_Error( 'qcld_wpbot_writer_nothing', __( 'Nothing to rewrite.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$input = '';
		foreach ( $list as $index => $item ) {
			$input .= ( $index + 1 ) . '. "' . $item['sentence'] . '" Problem: ' . $item['reason'] . "\n";
		}
		$result = self::json(
			'editor_rewrite',
			'rewrite_sentences',
			'Rewrite each numbered sentence to fix only its problem. Keep the meaning, every number, every name and any quoted words exactly. One sentence in, one sentence out (two at most when splitting a long one). Answer with JSON: {"rewrites": [{"n": 1, "text": "..."}]}.',
			$input . "\nThe post title: " . $ctx['title'],
			$ctx,
			array(
				'mode'       => 'creative',
				'max_tokens' => 1500,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$rewrites = array();
		$skipped  = array();
		foreach ( isset( $result['rewrites'] ) ? (array) $result['rewrites'] : array() as $entry ) {
			$n = isset( $entry['n'] ) ? (int) $entry['n'] - 1 : -1;
			if ( ! isset( $list[ $n ] ) ) {
				continue;
			}
			$original = $list[ $n ]['sentence'];
			$text     = sanitize_text_field( isset( $entry['text'] ) ? (string) $entry['text'] : '' );
			$problem  = self::protected_loss( $original, $text );
			if ( '' !== $problem ) {
				$skipped[] = array(
					'original' => $original,
					'reason'   => $problem,
				);
				continue;
			}
			$rewrites[] = array(
				'original' => $original,
				'rewrite'  => $text,
			);
		}
		return array(
			'rewrites' => $rewrites,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Why a rewrite is unsafe ('' when it is fine).
	 *
	 * @param string $original Original sentence.
	 * @param string $rewrite  Rewrite.
	 * @return string
	 */
	public static function protected_loss( $original, $rewrite ) {
		if ( '' === trim( $rewrite ) ) {
			return __( 'The AI returned nothing for this sentence.', 'chatbot' );
		}
		preg_match_all( '/\d+(?:[.,]\d+)*/u', $original, $numbers );
		foreach ( $numbers[0] as $number ) {
			if ( false === strpos( $rewrite, $number ) ) {
				/* translators: %s: a number. */
				return sprintf( __( 'The rewrite dropped the number %s, so it was not used.', 'chatbot' ), $number );
			}
		}
		preg_match_all( '/["“]([^"”]{3,})["”]/u', $original, $quotes );
		foreach ( $quotes[1] as $quote ) {
			if ( false === strpos( $rewrite, $quote ) ) {
				return __( 'The rewrite changed a quotation, so it was not used.', 'chatbot' );
			}
		}
		$length = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
		if ( $length( $rewrite ) > 2.5 * max( 20, $length( $original ) ) ) {
			return __( 'The rewrite was much longer than the sentence, so it was not used.', 'chatbot' );
		}
		return '';
	}

	/**
	 * Context from the request: the editor's unsaved title and content.
	 *
	 * @param int    $post_id Post id (0 for none).
	 * @param string $title   Title in the editor.
	 * @param string $content Content in the editor (HTML or block markup).
	 * @return array
	 */
	public static function context( $post_id, $title, $content ) {
		$post_id = absint( $post_id );
		$type    = $post_id ? get_post_type( $post_id ) : 'post';
		$object  = $type ? get_post_type_object( $type ) : null;
		$text    = wp_strip_all_tags( function_exists( 'excerpt_remove_blocks' ) ? excerpt_remove_blocks( (string) $content ) : (string) $content );
		if ( '' === trim( $text ) && '' !== trim( (string) $content ) ) {
			$text = wp_strip_all_tags( (string) $content );
		}
		$voice = Voices::site_voice();
		return array(
			'post_id' => $post_id,
			'type'    => $object ? strtolower( $object->labels->singular_name ) : 'post',
			'title'   => Prompts::cut( sanitize_text_field( (string) $title ), 300 ),
			'text'    => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) ),
			'voice'   => $voice ? (array) $voice['profile'] : array(),
			'cache'   => true,
		);
	}

	/**
	 * Post description for prompts.
	 *
	 * @param array $ctx   Context.
	 * @param int   $chars Content budget.
	 * @return string
	 */
	private static function describe( array $ctx, $chars ) {
		$text = '' !== $ctx['text'] ? Prompts::clip( $ctx['text'], $chars ) : '(empty so far)';
		return 'Type: ' . $ctx['type'] . "\nTitle: " . ( '' !== $ctx['title'] ? $ctx['title'] : '(no title yet)' ) . "\nContent: " . $text;
	}

	/**
	 * System prompt with the voice, language and writing rules.
	 *
	 * @param string $task Task name.
	 * @param string $role What to do.
	 * @param array  $ctx  Context.
	 * @return string
	 */
	private static function system( $task, $role, array $ctx ) {
		$system = 'Task: ' . $task . "\n" . $role . "\n" . Prompts::business() . "\n" . Prompts::language() . "\n" . Prompts::voice( $ctx['voice'] ) . "\n" . Prompts::writing_rules();
		// CR-14: a regeneration carries the owner's changes (or just "a new version").
		if ( isset( $ctx['regen'] ) && is_array( $ctx['regen'] ) ) {
			$system .= "\n" . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $ctx['regen'] );
		}
		return $system;
	}

	/**
	 * Ask for JSON with the shared system prompt.
	 *
	 * @param string $feature Usage feature id.
	 * @param string $task    Task name.
	 * @param string $role    Instruction.
	 * @param string $input   User prompt.
	 * @param array  $ctx     Context.
	 * @param array  $args    Writer_AI args.
	 * @return array|WP_Error Decoded data.
	 */
	private static function json( $feature, $task, $role, $input, array $ctx, array $args ) {
		if ( ! isset( $args['cache'] ) ) {
			$args['cache'] = ! empty( $ctx['cache'] );
		}
		$result = Writer_AI::ask_json( $feature, self::system( $task, $role, $ctx ), $input, $args );
		return is_wp_error( $result ) ? $result : (array) $result['data'];
	}

	/**
	 * Block markup and preview HTML for items.
	 *
	 * @param array[] $items Items.
	 * @return array content, html.
	 */
	private static function package( array $items ) {
		return array(
			'content' => Block_Builder::to_blocks( $items ),
			'html'    => Block_Builder::to_html( $items ),
		);
	}

	/**
	 * Clean list of strings.
	 *
	 * @param mixed $raw  Raw list.
	 * @param int   $max   Max entries.
	 * @param int   $chars Max length each.
	 * @return string[]
	 */
	private static function strings( $raw, $max, $chars ) {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $entry ) {
			if ( is_scalar( $entry ) ) {
				$entry = trim( sanitize_text_field( (string) $entry ) );
				if ( '' !== $entry ) {
					$out[] = Prompts::cut( $entry, $chars );
				}
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, $max );
	}
}
