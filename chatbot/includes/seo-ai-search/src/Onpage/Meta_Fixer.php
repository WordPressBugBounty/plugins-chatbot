<?php
/**
 * AI-written search titles and descriptions (Fix with AI and auto meta).
 *
 * Uses the module's own AI key set through WPBot Core. Sends only the page
 * title, its opening text and the main search; no personal data.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Writes title and description proposals.
 */
final class Meta_Fixer {

	/**
	 * Propose a search title and description for a post.
	 *
	 * @param int   $post_id Post id.
	 * @param array $args    job_id, main_query.
	 * @return array|WP_Error title, description, usage.
	 */
	public static function propose( $post_id, array $args = array() ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$text     = Checks::text( $post->post_content );
		$text     = Redactor::redact( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1800 ) : substr( $text, 0, 1800 ) );
		$query    = isset( $args['main_query'] ) ? (string) $args['main_query'] : Audit::main_query( $post_id );
		$system   = 'You write search titles and meta descriptions for a small business website. Plain, specific, honest; no clickbait, no keyword stuffing, no quotes around the text. Same language as the page.';
		$prompt   = "Task: fix_meta\n"
			. 'Site: ' . get_bloginfo( 'name' ) . "\n"
			. 'Page title: ' . $post->post_title . "\n"
			. ( '' !== $query ? 'Main search people use to find it: ' . $query . "\n" : '' )
			. "Page text (start):\n" . $text . "\n\n"
			. 'Return JSON {"title": "...", "description": "..."}: title 30 to 60 characters and containing the main search naturally when given; description 120 to 155 characters, saying what the page offers and why to click.';
		$response = AI_Service::generate(
			Module::ID,
			'fix_meta',
			Request::make(
				$system,
				$prompt,
				array(
					'max_tokens' => 300,
					'json'       => true,
					'mode'       => 'strict',
				)
			),
			array(
				'job_id' => isset( $args['job_id'] ) ? (int) $args['job_id'] : 0,
				'cache'  => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json        = $response->json();
		$title       = is_array( $json ) && isset( $json['title'] ) ? trim( wp_strip_all_tags( (string) $json['title'] ) ) : '';
		$description = is_array( $json ) && isset( $json['description'] ) ? trim( wp_strip_all_tags( (string) $json['description'] ) ) : '';
		if ( '' === $title && '' === $description ) {
			return new WP_Error( 'qcld_wpbot_seo_ai_empty', __( 'The AI did not return a title or description. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return array(
			'title'       => self::cut( trim( $title, '"\' ' ), 65 ),
			'description' => self::cut( trim( $description, '"\' ' ), 160 ),
			'usage'       => array(
				'tokens_in'  => $response->tokens_in,
				'tokens_out' => $response->tokens_out,
				'cost'       => $response->cost,
			),
		);
	}

	/**
	 * Cut at a word boundary.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Most characters.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) <= $chars ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $chars ) : substr( $text, 0, $chars );
		$at  = strrpos( $cut, ' ' );
		return rtrim( false !== $at && $at > $chars * 0.6 ? substr( $cut, 0, $at ) : $cut, ' ,;:-' );
	}
}
