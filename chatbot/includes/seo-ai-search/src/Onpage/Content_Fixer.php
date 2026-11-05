<?php
/**
 * Page Audit (F2): AI fixes for image alt text, an FAQ or summary, and the
 * introduction. Like the title and description fixes, every proposal is
 * reviewed (and can be edited) before it is saved, and every save is
 * logged and can be undone.
 *
 * Page text sent to AI is redacted (WPBot Core's Redactor) and capped; no
 * personal data is added.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Alt text, FAQ or summary, introduction.
 */
final class Content_Fixer {

	const KINDS = array( 'alt', 'faq', 'intro' );

	/**
	 * Most images described in one proposal.
	 */
	const MAX_IMAGES = 12;

	/**
	 * Propose a fix.
	 *
	 * @param int    $post_id Post id.
	 * @param string $kind    alt, faq or intro.
	 * @param array  $args    keyword (target keyword), job_id.
	 * @return array|WP_Error kind, plus images (src, alt) | html | intro, and cost.
	 */
	public static function propose( $post_id, $kind, array $args = array() ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_fix_kind', __( 'Unknown fix.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$keyword = isset( $args['keyword'] ) && '' !== (string) $args['keyword'] ? sanitize_text_field( (string) $args['keyword'] ) : Audit::main_query( $post_id );
		$text    = Checks::text( $post->post_content );
		$text    = Redactor::redact( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 2500 ) : substr( $text, 0, 2500 ) );
		$head    = 'Page title: ' . $post->post_title . "\n" . ( '' !== $keyword ? 'Target keyword: ' . $keyword . "\n" : '' );

		if ( 'alt' === $kind ) {
			$images = self::images_without_alt( $post->post_content );
			if ( array() === $images ) {
				return new WP_Error( 'qcld_wpbot_seo_fix_nothing', __( 'Every image on this page already has alt text.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$list = array();
			foreach ( $images as $index => $image ) {
				$list[] = ( $index + 1 ) . ': file ' . self::file_name( $image['src'] ) . ( '' !== $image['near'] ? ' — nearby text: ' . $image['near'] : '' );
			}
			$ai = self::ask(
				'fix_alt',
				$head . "Images without alt text:\n" . implode( "\n", $list ) . "\nPage text (start):\n" . $text . "\n\nWrite short, literal alt text for each image (at most 120 characters): what the image shows, useful to someone who can't see it. Use the keyword only where it truly describes the image. Return JSON {\"alts\": [\"...\", \"...\"]} in the same order.",
				600
			);
			if ( is_wp_error( $ai ) ) {
				return $ai;
			}
			$alts = isset( $ai['json']['alts'] ) && is_array( $ai['json']['alts'] ) ? array_values( $ai['json']['alts'] ) : array();
			$out  = array();
			foreach ( $images as $index => $image ) {
				$alt   = isset( $alts[ $index ] ) && is_string( $alts[ $index ] ) ? self::cut( sanitize_text_field( $alts[ $index ] ), 120 ) : '';
				$out[] = array(
					'src' => esc_url_raw( $image['src'] ),
					'alt' => $alt,
				);
			}
			return array(
				'kind'   => 'alt',
				'images' => $out,
				'cost'   => $ai['cost'],
			);
		}

		if ( 'faq' === $kind ) {
			$ai = self::ask(
				'fix_faq',
				$head . "Page text (start):\n" . $text . "\n\nWrite a short FAQ for the end of this page: 3 to 5 questions people really ask about this topic, each answered in one to three plain sentences using only facts that are on the page. Never invent prices, numbers, dates or claims that are not in the text. Return JSON {\"faq\": [{\"q\": \"...\", \"a\": \"...\"}]}.",
				900
			);
			if ( is_wp_error( $ai ) ) {
				return $ai;
			}
			$pairs = array();
			foreach ( isset( $ai['json']['faq'] ) && is_array( $ai['json']['faq'] ) ? array_slice( $ai['json']['faq'], 0, 6 ) : array() as $row ) {
				$q = is_array( $row ) && isset( $row['q'] ) && is_string( $row['q'] ) ? self::cut( sanitize_text_field( $row['q'] ), 200 ) : '';
				$a = is_array( $row ) && isset( $row['a'] ) && is_string( $row['a'] ) ? self::cut( sanitize_textarea_field( $row['a'] ), 600 ) : '';
				if ( '' !== $q && '' !== $a ) {
					$pairs[] = array(
						'q' => $q,
						'a' => $a,
					);
				}
			}
			if ( array() === $pairs ) {
				return new WP_Error( 'qcld_wpbot_seo_ai_empty', __( 'The AI did not write an FAQ. Try again.', 'chatbot' ), array( 'status' => 502 ) );
			}
			return array(
				'kind' => 'faq',
				'faq'  => $pairs,
				'html' => self::faq_html( $pairs ),
				'cost' => $ai['cost'],
			);
		}

		$current = self::intro( $post->post_content );
		$ai      = self::ask(
			'fix_intro',
			$head . 'Current introduction: ' . ( '' !== $current['text'] ? $current['text'] : '(none)' ) . "\nPage text (start):\n" . $text . "\n\nRewrite the introduction as one paragraph of 40 to 80 words that says straight away what the page answers" . ( '' !== $keyword ? ', using the target keyword once, naturally' : '' ) . '. Keep the facts of the page; never invent numbers, prices or claims. Same language and tone as the page. Return JSON {"intro": "..."}.',
			400
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		$intro = isset( $ai['json']['intro'] ) && is_string( $ai['json']['intro'] ) ? self::cut( sanitize_textarea_field( $ai['json']['intro'] ), 800 ) : '';
		if ( '' === $intro ) {
			return new WP_Error( 'qcld_wpbot_seo_ai_empty', __( 'The AI did not write an introduction. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return array(
			'kind'    => 'intro',
			'current' => $current['text'],
			'intro'   => $intro,
			'cost'    => $ai['cost'],
		);
	}

	/**
	 * Save a reviewed fix into the page (logged; can be undone).
	 *
	 * @param int    $post_id Post id.
	 * @param string $kind    alt, faq or intro.
	 * @param array  $value   images (src, alt) | faq (q, a) | intro (text).
	 * @param string $group   Change group (bulk runs).
	 * @return int|WP_Error Change id.
	 */
	public static function apply( $post_id, $kind, array $value, $group = '' ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$before = (string) $post->post_content;
		$after  = $before;
		if ( 'alt' === $kind ) {
			$after = self::set_alts( $before, isset( $value['images'] ) ? (array) $value['images'] : array() );
		} elseif ( 'faq' === $kind ) {
			$pairs = array();
			foreach ( isset( $value['faq'] ) ? (array) $value['faq'] : array() as $row ) {
				$q = is_array( $row ) && isset( $row['q'] ) ? sanitize_text_field( (string) $row['q'] ) : '';
				$a = is_array( $row ) && isset( $row['a'] ) ? sanitize_textarea_field( (string) $row['a'] ) : '';
				if ( '' !== $q && '' !== $a ) {
					$pairs[] = array(
						'q' => $q,
						'a' => $a,
					);
				}
			}
			if ( array() === $pairs ) {
				return new WP_Error( 'qcld_wpbot_seo_fix_empty', __( 'Add at least one question and answer.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$after = rtrim( $before ) . "\n\n" . self::faq_html( $pairs );
		} elseif ( 'intro' === $kind ) {
			$text = isset( $value['intro'] ) ? trim( sanitize_textarea_field( (string) $value['intro'] ) ) : '';
			if ( '' === $text ) {
				return new WP_Error( 'qcld_wpbot_seo_fix_empty', __( 'The introduction is empty.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$after = self::set_intro( $before, $text );
		} else {
			return new WP_Error( 'qcld_wpbot_seo_fix_kind', __( 'Unknown fix.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( $after === $before ) {
			return new WP_Error( 'qcld_wpbot_seo_fix_nothing', __( 'Nothing changed on the page.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$done = wp_update_post(
			array(
				'ID'           => (int) $post_id,
				// Only the inserted parts are new, and they are escaped above; the rest is the owner's own content.
				'post_content' => wp_slash( $after ),
			),
			true
		);
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		$saved = (string) get_post_field( 'post_content', $post_id );
		$id    = Change_Log::record(
			array(
				'post_id'   => (int) $post_id,
				'kind'      => 'content',
				'field'     => $kind,
				'before'    => $before,
				'after'     => $saved,
				'source'    => 'fix',
				'group_ref' => $group,
			)
		);
		Audit::audit( (int) $post_id, 'fix' );
		return $id;
	}

	/**
	 * Images without alt text, with a little nearby text for context.
	 *
	 * @param string $html Content.
	 * @return array[] src, near.
	 */
	public static function images_without_alt( $html ) {
		$out = array();
		if ( ! preg_match_all( '#<img\b[^>]*>#i', (string) $html, $found, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}
		foreach ( $found[0] as $match ) {
			$tag = $match[0];
			if ( preg_match( '#\balt\s*=\s*("|\')\s*[^"\'\s]#i', $tag ) || ! preg_match( '#\bsrc\s*=\s*("|\')([^"\']+)\1#i', $tag, $src ) ) {
				continue;
			}
			$near  = Checks::text( substr( (string) $html, max( 0, $match[1] - 300 ), 300 ) );
			$out[] = array(
				'src'  => $src[2],
				'near' => self::cut( trim( $near ), 160 ),
			);
			if ( count( $out ) >= self::MAX_IMAGES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Put alt text on images (matched by src; images with alt keep theirs).
	 *
	 * @param string $html   Content.
	 * @param array  $images src, alt.
	 * @return string
	 */
	public static function set_alts( $html, array $images ) {
		$map = array();
		foreach ( $images as $image ) {
			if ( is_array( $image ) && ! empty( $image['src'] ) && isset( $image['alt'] ) && '' !== trim( (string) $image['alt'] ) ) {
				$map[ (string) $image['src'] ] = self::cut( sanitize_text_field( (string) $image['alt'] ), 120 );
			}
		}
		if ( array() === $map ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#<img\b[^>]*>#i',
			static function ( $found ) use ( $map ) {
				$tag = $found[0];
				if ( preg_match( '#\balt\s*=\s*("|\')\s*[^"\'\s]#i', $tag ) || ! preg_match( '#\bsrc\s*=\s*("|\')([^"\']+)\1#i', $tag, $src ) ) {
					return $tag;
				}
				$key = isset( $map[ $src[2] ] ) ? $src[2] : esc_url_raw( $src[2] );
				if ( ! isset( $map[ $key ] ) ) {
					return $tag;
				}
				$alt = esc_attr( $map[ $key ] );
				// Replace an empty alt, or add one.
				if ( preg_match( '#\balt\s*=\s*("|\')\s*\1#i', $tag ) ) {
					return (string) preg_replace( '#\balt\s*=\s*("|\')\s*\1#i', 'alt="' . $alt . '"', $tag, 1 );
				}
				return (string) preg_replace( '#^<img\b#i', '<img alt="' . $alt . '"', $tag, 1 );
			},
			(string) $html
		);
	}

	/**
	 * The first paragraph (block-editor aware).
	 *
	 * @param string $html Content.
	 * @return array text, html (the matched paragraph, or '').
	 */
	public static function intro( $html ) {
		if ( preg_match( '#<p\b[^>]*>.*?</p>#is', (string) $html, $match ) ) {
			return array(
				'text' => Checks::text( $match[0] ),
				'html' => $match[0],
			);
		}
		return array(
			'text' => '',
			'html' => '',
		);
	}

	/**
	 * Replace the first paragraph (or add one at the top).
	 *
	 * @param string $html Content.
	 * @param string $text New introduction.
	 * @return string
	 */
	public static function set_intro( $html, $text ) {
		$paragraph = '<p>' . esc_html( $text ) . '</p>';
		$current   = self::intro( $html );
		if ( '' !== $current['html'] ) {
			$at = strpos( (string) $html, $current['html'] );
			return substr( (string) $html, 0, $at ) . $paragraph . substr( (string) $html, $at + strlen( $current['html'] ) );
		}
		$block = false !== strpos( (string) $html, '<!-- wp:' ) ? "<!-- wp:paragraph -->\n" . $paragraph . "\n<!-- /wp:paragraph -->\n\n" : $paragraph . "\n\n";
		return $block . (string) $html;
	}

	/**
	 * FAQ markup (a heading and question / answer pairs; block-editor friendly).
	 *
	 * @param array[] $pairs q, a.
	 * @return string
	 */
	public static function faq_html( array $pairs ) {
		$html = "<!-- wp:heading -->\n<h2>" . esc_html__( 'Frequently asked questions', 'chatbot' ) . "</h2>\n<!-- /wp:heading -->\n";
		foreach ( $pairs as $pair ) {
			$html .= "\n<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_html( $pair['q'] ) . "</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $pair['a'] ) . "</p>\n<!-- /wp:paragraph -->\n";
		}
		return $html;
	}

	/**
	 * Ask the module's AI for JSON.
	 *
	 * @param string $task   Task.
	 * @param string $prompt Prompt.
	 * @param int    $tokens Max tokens.
	 * @return array|WP_Error json, cost.
	 */
	private static function ask( $task, $prompt, $tokens ) {
		$response = AI_Service::generate(
			Module::ID,
			$task,
			Request::make(
				'You improve web pages for a small business website. Plain, accurate, helpful; never invent facts, numbers, prices or claims. Answer with JSON only.',
				"Task: {$task}\nSite: " . get_bloginfo( 'name' ) . "\n" . $prompt,
				array(
					'max_tokens' => $tokens,
					'json'       => true,
					'mode'       => 'strict',
				)
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = $response->json();
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'qcld_wpbot_seo_ai_format', __( 'The AI answer could not be read. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return array(
			'json' => $json,
			'cost' => (float) $response->cost,
		);
	}

	/**
	 * File name of an image URL (no query).
	 *
	 * @param string $src URL.
	 * @return string
	 */
	private static function file_name( $src ) {
		return sanitize_file_name( basename( (string) wp_parse_url( (string) $src, PHP_URL_PATH ) ) );
	}

	/**
	 * Cut text to a length.
	 *
	 * @param string $text Text.
	 * @param int    $len  Length.
	 * @return string
	 */
	private static function cut( $text, $len ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $len ) : substr( (string) $text, 0, $len );
	}
}
