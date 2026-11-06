<?php
/**
 * Sections of an article (CR-14 R2): a section is a level-2 heading and
 * the blocks under it, up to the next level-2 heading. A section can be
 * written again with the same or changed options; numbers and quotes that
 * a rewrite would lose are reported, and honesty flags come with it.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Regenerate;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Editor\Editor_Actions;
use QuantumCloud\AiWriter\Persuasion\Choices;
use QuantumCloud\AiWriter\Persuasion\Context;
use QuantumCloud\AiWriter\Persuasion\Persuasion;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

/**
 * Article sections.
 */
final class Sections {

	/**
	 * Split content into sections.
	 *
	 * @param string $content Post content (block markup or HTML).
	 * @return array[] index, heading, text (plain), html, slot.
	 */
	public static function split( $content ) {
		$content  = (string) $content;
		$sections = array();
		if ( false !== strpos( $content, '<!-- wp:' ) && function_exists( 'parse_blocks' ) ) {
			$current = null;
			foreach ( parse_blocks( $content ) as $block ) {
				if ( empty( $block['blockName'] ) ) {
					continue;
				}
				$html  = render_block( $block );
				$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
				if ( 'core/heading' === $block['blockName'] && 2 === $level ) {
					if ( $current ) {
						$sections[] = $current;
					}
					$current = array(
						'heading' => trim( wp_strip_all_tags( $html ) ),
						'html'    => '',
					);
					continue;
				}
				if ( $current ) {
					$current['html'] .= $html;
				}
			}
			if ( $current ) {
				$sections[] = $current;
			}
		} else {
			$parts = preg_split( '#(<h2\b[^>]*>.*?</h2>)#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
			$total = count( $parts );
			for ( $i = 1; $i < $total; $i += 2 ) {
				$sections[] = array(
					'heading' => trim( wp_strip_all_tags( $parts[ $i ] ) ),
					'html'    => isset( $parts[ $i + 1 ] ) ? (string) $parts[ $i + 1 ] : '',
				);
			}
		}
		foreach ( $sections as $i => $section ) {
			$sections[ $i ]['index'] = $i;
			$sections[ $i ]['text']  = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $section['html'] ), ENT_QUOTES, 'UTF-8' ) ) );
			$sections[ $i ]['slot']  = self::slot( $section['heading'] );
			$sections[ $i ]['words'] = str_word_count( $sections[ $i ]['text'] );
		}
		return array_values(
			array_filter(
				$sections,
				static function ( $section ) {
					return '' !== $section['heading'];
				}
			)
		);
	}

	/**
	 * History slot of a section (by its heading).
	 *
	 * @param string $heading Heading.
	 * @return string
	 */
	public static function slot( $heading ) {
		return 'section_' . substr( md5( strtolower( trim( (string) $heading ) ) ), 0, 10 );
	}

	/**
	 * Write one section again.
	 *
	 * @param int    $post_id Post.
	 * @param int    $index   Section index.
	 * @param string $title   Title in the editor.
	 * @param string $content Content in the editor ('' for the saved post).
	 * @param array  $options Clean options.
	 * @return array|WP_Error heading, content (blocks), html, lost, honesty, versions.
	 */
	public static function regenerate( $post_id, $index, $title, $content, array $options ) {
		$content  = '' !== (string) $content ? (string) $content : (string) get_post_field( 'post_content', $post_id );
		$sections = self::split( $content );
		if ( ! isset( $sections[ $index ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_section', __( 'That section was not found. Save the post and try again.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$section = $sections[ $index ];
		$before  = array();
		$after   = array();
		foreach ( $sections as $i => $other ) {
			if ( $i < $index ) {
				$before[] = $other['heading'];
			} elseif ( $i > $index ) {
				$after[] = $other['heading'];
			}
		}
		$plan         = self::plan( $post_id, $section['heading'] );
		$prompt       = 'Write the section under the heading "' . str_replace( '"', "'", $section['heading'] ) . '" again. Keep its purpose and every fact, number, name, quote and link; do not repeat the heading.' . ( '' !== $plan ? "\n" . $plan : '' );
		$ctx          = Editor_Actions::context( $post_id, $title, $content );
		$ctx['cache'] = false;
		$ctx['regen'] = $options;
		$result       = Editor_Actions::write_here(
			$prompt,
			'rewrite',
			array(
				'before'   => 'Sections before: ' . implode( '; ', $before ),
				'after'    => 'Sections after: ' . implode( '; ', $after ),
				'selected' => $section['text'],
			),
			true,
			$ctx
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// "Write here" returns the preview HTML as 'preview'.
		$html            = isset( $result['preview'] ) ? (string) $result['preview'] : ( isset( $result['html'] ) ? (string) $result['html'] : '' );
		$new_text        = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
		$out             = array(
			'index'   => (int) $index,
			'heading' => $section['heading'],
			'content' => isset( $result['content'] ) ? $result['content'] : '',
			'html'    => $html,
			'lost'    => Editor_Actions::protected_loss( $section['text'], $new_text ),
			'honesty' => Persuasion::available() ? qcld_wpbot_core_honesty_check( $new_text, Context::honesty( $section['text'] . "\n" . wp_strip_all_tags( $content ) ) ) : array(),
		);
		$out['versions'] = History::add( $post_id, $section['slot'], $out, $options );
		return $out;
	}

	/**
	 * The section's job and technique from the article's outline (if AI Writer wrote it).
	 *
	 * @param int    $post_id Post.
	 * @param string $heading Heading.
	 * @return string
	 */
	private static function plan( $post_id, $heading ) {
		$job_id = (int) get_post_meta( $post_id, '_qcld_wpbot_writer_job_id', true );
		$job    = $job_id ? Job_Store::get( $job_id ) : null;
		if ( ! $job || empty( $job['payload']['outline']['sections'] ) ) {
			return '';
		}
		foreach ( (array) $job['payload']['outline']['sections'] as $planned ) {
			if ( isset( $planned['heading'] ) && strtolower( trim( $planned['heading'] ) ) === strtolower( trim( $heading ) ) ) {
				return trim( ( ! empty( $planned['purpose'] ) ? 'Purpose: ' . $planned['purpose'] . "\n" : '' ) . Choices::section_prompt( $planned ) );
			}
		}
		return '';
	}
}
