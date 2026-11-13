<?php
/**
 * Internal link suggestions (Free): pages to link to and from, with an
 * anchor that already appears in the text. Accepted links are saved into
 * the post as normal links (logged; undo removes them).
 *
 * Never inside headings, existing links, shortcodes or block comments;
 * never to the page itself.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Results\Change_Log;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Link suggestion engine.
 */
final class Link_Suggestions {

	/**
	 * Words ignored when matching topics.
	 */
	const STOP = array( 'the', 'and', 'for', 'with', 'your', 'you', 'how', 'what', 'why', 'are', 'from', 'that', 'this', 'our', 'can', 'best', 'guide', 'tips', 'into', 'about', 'when', 'will' );

	/**
	 * Suggestions for a post: links out (to related pages) and in (from them).
	 *
	 * @param int $post_id Post id.
	 * @param int $limit   Per direction.
	 * @return array out[], in[] with post_id, title, url, anchor, score.
	 */
	public static function suggest( $post_id, $limit = 5 ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'out' => array(),
				'in'  => array(),
			);
		}
		$source_terms = self::terms( $post->post_title . ' ' . Audit::main_query( $post_id ) );
		// One extra row, then drop the current post in PHP (same result as excluding it in the query).
		$candidates  = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 301, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded candidate set for suggestions on one page.
				'orderby'        => 'modified',
			)
		);
		$candidates  = array_slice(
			array_values(
				array_filter(
					$candidates,
					static function ( $candidate ) use ( $post_id ) {
						return (int) $candidate->ID !== (int) $post_id;
					}
				)
			),
			0,
			300
		);
		$out         = array();
		$in          = array();
		$source_text = Checks::text( $post->post_content );
		foreach ( $candidates as $candidate ) {
			$terms   = self::terms( $candidate->post_title );
			$overlap = count( array_intersect( $source_terms, $terms ) );
			if ( 0 === $overlap ) {
				continue;
			}
			$url = (string) get_permalink( $candidate );
			// Out: this post mentions the candidate's topic and does not link there yet.
			if ( ! self::links_to( $post->post_content, $url, $candidate->ID ) ) {
				$anchor = self::anchor( $source_text, $candidate->post_title );
				if ( '' !== $anchor ) {
					$out[] = array(
						'post_id' => $candidate->ID,
						'title'   => $candidate->post_title,
						'url'     => $url,
						'anchor'  => $anchor,
						'score'   => $overlap,
					);
				}
			}
			// In: the candidate mentions this post's topic and does not link here.
			if ( ! self::links_to( $candidate->post_content, (string) get_permalink( $post ), $post_id ) ) {
				$anchor = self::anchor( Checks::text( $candidate->post_content ), $post->post_title );
				if ( '' !== $anchor ) {
					$in[] = array(
						'post_id'  => $candidate->ID,
						'title'    => $candidate->post_title,
						'url'      => $url,
						'anchor'   => $anchor,
						'score'    => $overlap,
						'can_edit' => current_user_can( 'edit_post', $candidate->ID ),
					);
				}
			}
		}
		$sort = static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		};
		usort( $out, $sort );
		usort( $in, $sort );
		return array(
			'out' => array_slice( $out, 0, $limit ),
			'in'  => array_slice( $in, 0, $limit ),
		);
	}

	/**
	 * Add one link: the first plain-text mention of the anchor in the source
	 * post becomes a link to the target.
	 *
	 * @param int    $source_id Post that gets the link.
	 * @param int    $target_id Linked post.
	 * @param string $anchor    Anchor text (as it appears).
	 * @param string $source    Change source (fix|autolink).
	 * @param string $group     Group ref (for rule undo).
	 * @return int|WP_Error Change id.
	 */
	public static function add_link( $source_id, $target_id, $anchor, $source = 'fix', $group = '' ) {
		$post   = get_post( $source_id );
		$target = get_post( $target_id );
		if ( ! $post instanceof \WP_Post || ! $target instanceof \WP_Post || $source_id === $target_id ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_link', __( 'This link cannot be added.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$url     = (string) get_permalink( $target );
		$content = self::link_first( $post->post_content, (string) $anchor, $url );
		if ( null === $content ) {
			return new WP_Error( 'qcld_wpbot_seo_no_anchor', __( 'The text was not found outside headings and links; the page may have changed.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$saved = wp_update_post(
			wp_slash(
				array(
					'ID'           => $source_id,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return Change_Log::record(
			array(
				'post_id'   => $source_id,
				'kind'      => 'link',
				'field'     => 'content',
				'before'    => (string) $anchor,
				'after'     => $url,
				'source'    => $source,
				'group_ref' => $group,
			)
		);
	}

	/**
	 * Undo a link change: unwrap that link, keep the text.
	 *
	 * @param array $change Change (Change_Log::shape()).
	 * @return true|WP_Error
	 */
	public static function remove_link( array $change ) {
		$post = get_post( $change['post_id'] );
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$pattern = '#<a href="' . preg_quote( esc_url( $change['after'] ), '#' ) . '"( data-wpbot-seo="1")?>(' . preg_quote( esc_html( $change['before'] ), '#' ) . '|' . preg_quote( $change['before'], '#' ) . ')</a>#iu';
		$content = preg_replace( $pattern, '$2', $post->post_content, 1, $count );
		if ( ! $count ) {
			return new WP_Error( 'qcld_wpbot_seo_link_gone', __( 'The link is no longer in the page (it was edited or removed).', 'chatbot' ), array( 'status' => 409 ) );
		}
		$saved = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post->ID,
					'post_content' => $content,
				)
			),
			true
		);
		return is_wp_error( $saved ) ? $saved : true;
	}

	/**
	 * Wrap the first plain-text match of the anchor in a link.
	 *
	 * Skips text inside links, headings, shortcodes and HTML comments.
	 *
	 * @param string $content HTML.
	 * @param string $anchor  Anchor text.
	 * @param string $url     Link URL.
	 * @return string|null New content, or null when not found.
	 */
	public static function link_first( $content, $anchor, $url ) {
		$anchor = trim( $anchor );
		if ( '' === $anchor ) {
			return null;
		}
		$parts   = preg_split( '#(<!--.*?-->|<[^>]+>|\[[^\]]+\])#s', (string) $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		$blocked = 0;
		foreach ( $parts as $index => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] || '[' === $part[0] ) {
				if ( preg_match( '#^<(a|h[1-6])\b#i', $part ) ) {
					++$blocked;
				} elseif ( preg_match( '#^</(a|h[1-6])>#i', $part ) ) {
					$blocked = max( 0, $blocked - 1 );
				}
				continue;
			}
			if ( $blocked > 0 ) {
				continue;
			}
			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $anchor, '/' ) . '(?![\p{L}\p{N}])/iu', $part, $match, PREG_OFFSET_CAPTURE ) ) {
				$found           = $match[0][0];
				$parts[ $index ] = substr( $part, 0, $match[0][1] ) . '<a href="' . esc_url( $url ) . '" data-wpbot-seo="1">' . $found . '</a>' . substr( $part, $match[0][1] + strlen( $found ) );
				return implode( '', $parts );
			}
		}
		return null;
	}

	/**
	 * Whether content already links to a URL (or ?p=ID).
	 *
	 * @param string $content Content.
	 * @param string $url     URL.
	 * @param int    $post_id Post id.
	 * @return bool
	 */
	private static function links_to( $content, $url, $post_id ) {
		$path = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return ( '' !== $path && false !== strpos( $content, 'href="' . untrailingslashit( $url ) ) ) || ( '' !== $path && false !== strpos( $content, $path . '/"' ) ) || false !== strpos( $content, '?p=' . (int) $post_id . '"' );
	}

	/**
	 * Best anchor: the longest run of the target title's words (2–5 words, or
	 * the whole title) found in the text.
	 *
	 * @param string $text  Plain text.
	 * @param string $title Target title.
	 * @return string Anchor as it appears in the text, or ''.
	 */
	public static function anchor( $text, $title ) {
		$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( $title ) ) );
		$count = count( $words );
		for ( $size = min( 5, $count ); $size >= 2; $size-- ) {
			for ( $start = 0; $start + $size <= $count; $start++ ) {
				$slice  = array_slice( $words, $start, $size );
				$phrase = trim( implode( ' ', $slice ), " \t,.:;!?-" );
				// Start and end on meaningful words ("balance bikes", not "balance bikes for").
				if ( array() === self::terms( (string) reset( $slice ) ) || array() === self::terms( (string) end( $slice ) ) ) {
					continue;
				}
				if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $phrase, '/' ) . '(?![\p{L}\p{N}])/iu', $text, $match ) ) {
					return $match[0];
				}
			}
		}
		return '';
	}

	/**
	 * Significant lower-case words of a text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function terms( $text ) {
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( (array) $words as $word ) {
			$word = rtrim( $word, 's' );
			if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $word ) : strlen( $word ) ) > 2 && ! in_array( $word, self::STOP, true ) ) {
				$out[] = $word;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
