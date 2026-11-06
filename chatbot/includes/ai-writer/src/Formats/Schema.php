<?php
/**
 * CR-16: structured data (JSON-LD) for pieces written in a format, built
 * from the published post's own blocks: ItemList (listicle, checklist),
 * HowTo (how-to, tutorial), FAQPage (FAQ) and Dataset (original research).
 * Article markup is left to the SEO plugin. Google shows rich results for
 * few of these types today; the markup still describes the page to search
 * engines and AI assistants.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

defined( 'ABSPATH' ) || exit;

/**
 * Format structured data.
 */
final class Schema {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'remember' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'print_json_ld' ), 30 );
	}

	/**
	 * Keep the format with the post when the piece is finished.
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function remember( $post_id, $brief ) {
		$format = is_array( $brief ) && ! empty( $brief['format'] ) ? Formats::sanitise( $brief['format'] ) : '';
		if ( '' !== $format ) {
			update_post_meta( (int) $post_id, Formats::META, $format );
		}
	}

	/**
	 * Print the JSON-LD on a published post written in a format.
	 *
	 * @return void
	 */
	public static function print_json_ld() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		$data = self::for_post( $post );
		if ( null === $data ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with tags and ampersands escaped.
	}

	/**
	 * The JSON-LD for a post, or null.
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null
	 */
	public static function for_post( $post ) {
		$format = Formats::get( (string) get_post_meta( $post->ID, Formats::META, true ) );
		if ( null === $format || '' === $format['schema'] ) {
			return null;
		}
		$name   = wp_strip_all_tags( get_the_title( $post ) );
		$url    = (string) get_permalink( $post );
		$blocks = parse_blocks( (string) $post->post_content );
		$data   = null;
		switch ( $format['schema'] ) {
			case 'ItemList':
				$items = self::numbered_headings( $blocks );
				if ( array() === $items ) {
					$items = self::checklist_items( $blocks );
				}
				$data = array() === $items ? null : array(
					'@context'        => 'https://schema.org',
					'@type'           => 'ItemList',
					'name'            => $name,
					'url'             => $url,
					'itemListElement' => array_map(
						static function ( $text, $i ) {
							return array(
								'@type'    => 'ListItem',
								'position' => $i + 1,
								'name'     => $text,
							);
						},
						$items,
						array_keys( $items )
					),
				);
				break;
			case 'HowTo':
				$steps = self::step_headings( $blocks );
				if ( array() === $steps ) {
					$steps = self::ordered_items( $blocks );
				}
				$data = count( $steps ) < 2 ? null : array(
					'@context' => 'https://schema.org',
					'@type'    => 'HowTo',
					'name'     => $name,
					'url'      => $url,
					'step'     => array_map(
						static function ( $text, $i ) {
							return array(
								'@type'    => 'HowToStep',
								'position' => $i + 1,
								'text'     => $text,
							);
						},
						$steps,
						array_keys( $steps )
					),
				);
				break;
			case 'FAQPage':
				$pairs = self::questions( $blocks );
				$data  = array() === $pairs ? null : array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => array_map(
						static function ( $pair ) {
							return array(
								'@type'          => 'Question',
								'name'           => $pair[0],
								'acceptedAnswer' => array(
									'@type' => 'Answer',
									'text'  => $pair[1],
								),
							);
						},
						$pairs
					),
				);
				break;
			case 'Dataset':
				$data = array(
					'@context'    => 'https://schema.org',
					'@type'       => 'Dataset',
					'name'        => $name,
					'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
					'url'         => $url,
					'creator'     => array(
						'@type' => 'Organization',
						'name'  => wp_strip_all_tags( get_bloginfo( 'name' ) ),
						'url'   => home_url( '/' ),
					),
				);
				if ( '' === $data['description'] ) {
					$data['description'] = $name;
				}
				break;
		}
		/**
		 * The structured data printed for a piece written in a format (null for none).
		 *
		 * @param array|null $data   JSON-LD.
		 * @param \WP_Post   $post   Post.
		 * @param string     $schema Schema type.
		 */
		return apply_filters( 'qcld_wpbot_writer/format_schema', $data, $post, $format['schema'] );
	}

	/**
	 * Text of headings like "1. Name" (listicle items).
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string[]
	 */
	private static function numbered_headings( array $blocks ) {
		$out = array();
		foreach ( self::flat( $blocks ) as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$text = self::plain( $block['innerHTML'] );
				if ( preg_match( '/^\s*\d+[.)]\s*(.+)$/u', $text, $m ) ) {
					$out[] = trim( $m[1] );
				}
			}
		}
		return $out;
	}

	/**
	 * Text of headings like "Step 1: Do this".
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string[]
	 */
	private static function step_headings( array $blocks ) {
		$out = array();
		foreach ( self::flat( $blocks ) as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$text = self::plain( $block['innerHTML'] );
				if ( preg_match( '/^\s*step\s+\d+\s*[:.\-–]?\s*(.+)$/iu', $text, $m ) ) {
					$out[] = trim( $m[1] );
				}
			}
		}
		return $out;
	}

	/**
	 * Items of the first numbered list with at least two items.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string[]
	 */
	private static function ordered_items( array $blocks ) {
		foreach ( self::flat( $blocks ) as $block ) {
			if ( 'core/list' === $block['blockName'] && ! empty( $block['attrs']['ordered'] ) ) {
				$items = self::list_items( $block );
				if ( count( $items ) >= 2 ) {
					return $items;
				}
			}
		}
		return array();
	}

	/**
	 * Items of checklist lists.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string[]
	 */
	private static function checklist_items( array $blocks ) {
		$out = array();
		foreach ( self::flat( $blocks ) as $block ) {
			if ( 'core/list' === $block['blockName'] && isset( $block['attrs']['className'] ) && false !== strpos( (string) $block['attrs']['className'], 'qcwr-checklist' ) ) {
				$out = array_merge( $out, self::list_items( $block ) );
			}
		}
		return $out;
	}

	/**
	 * Question headings (ending with "?") and the text that follows each.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return array[] question, answer.
	 */
	private static function questions( array $blocks ) {
		$pairs   = array();
		$current = null;
		foreach ( self::flat( $blocks ) as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				if ( null !== $current && '' !== $current[1] ) {
					$pairs[] = $current;
				}
				$text    = self::plain( $block['innerHTML'] );
				$current = '?' === substr( rtrim( $text ), -1 ) ? array( $text, '' ) : null;
			} elseif ( null !== $current && in_array( $block['blockName'], array( 'core/paragraph', 'core/list' ), true ) ) {
				$current[1] = trim( $current[1] . ' ' . self::plain( $block['innerHTML'] . implode( ' ', wp_list_pluck( $block['innerBlocks'], 'innerHTML' ) ) ) );
			}
		}
		if ( null !== $current && '' !== $current[1] ) {
			$pairs[] = $current;
		}
		return $pairs;
	}

	/**
	 * Text of a list block's items.
	 *
	 * @param array $block List block.
	 * @return string[]
	 */
	private static function list_items( array $block ) {
		$items = array();
		foreach ( (array) $block['innerBlocks'] as $inner ) {
			$text = self::plain( $inner['innerHTML'] );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
		return $items;
	}

	/**
	 * Blocks in document order, inner blocks of groups and columns included.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return array[]
	 */
	private static function flat( array $blocks ) {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}
			$out[] = $block;
			if ( 'core/list' !== $block['blockName'] && ! empty( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, self::flat( $block['innerBlocks'] ) );
			}
		}
		return $out;
	}

	/**
	 * Plain text from block HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function plain( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
