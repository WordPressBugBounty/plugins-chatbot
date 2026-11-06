<?php
/**
 * Turns structured content into native blocks (or Classic Editor HTML).
 *
 * Input is a list of items: paragraph{text}, heading{text, level}, list{items,
 * ordered, checklist}, quote{text}, table{head[], rows[][]}, image{id, url,
 * alt, caption}, faq{items[{q,a}]}, placeholder_image{label}. Blocks are serialised with WordPress's own
 * serialize_blocks(); nothing is converted with regular expressions.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Output;

defined( 'ABSPATH' ) || exit;

/**
 * Block builder.
 */
final class Block_Builder {

	/**
	 * Clean and normalise a list of items from the AI.
	 *
	 * @param mixed $items Raw items.
	 * @return array[]
	 */
	public static function normalise( $items ) {
		$out = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['type'] ) ) {
				continue;
			}
			switch ( $item['type'] ) {
				case 'paragraph':
				case 'quote':
					$text = Kses_Policy::inline( isset( $item['text'] ) ? $item['text'] : '' );
					if ( '' !== $text ) {
						$out[] = array(
							'type' => $item['type'],
							'text' => $text,
						);
					}
					break;
				case 'heading':
					$text = Kses_Policy::plain( isset( $item['text'] ) ? $item['text'] : '' );
					if ( '' !== $text ) {
						$out[] = array(
							'type'  => 'heading',
							'text'  => $text,
							'level' => isset( $item['level'] ) ? min( 4, max( 2, (int) $item['level'] ) ) : 3,
						);
					}
					break;
				case 'list':
					$list = array();
					foreach ( isset( $item['items'] ) ? (array) $item['items'] : array() as $entry ) {
						$entry = Kses_Policy::inline( is_array( $entry ) ? implode( ' ', $entry ) : $entry );
						if ( '' !== $entry ) {
							$list[] = $entry;
						}
					}
					if ( array() !== $list ) {
						$out[] = array(
							'type'      => 'list',
							'items'     => $list,
							'ordered'   => ! empty( $item['ordered'] ) && empty( $item['checklist'] ),
							// CR-16: a list to tick off (checklist format).
							'checklist' => ! empty( $item['checklist'] ),
						);
					}
					break;
				case 'table':
					$table = self::clean_table( $item );
					if ( null !== $table ) {
						$out[] = $table;
					}
					break;
			}
		}
		return $out;
	}

	/**
	 * Serialised block markup for items.
	 *
	 * @param array[] $items Normalised items (plus image, faq, placeholder_image).
	 * @return string
	 */
	public static function to_blocks( array $items ) {
		$blocks = array();
		foreach ( $items as $item ) {
			$block = self::block( $item );
			if ( $block ) {
				$blocks[] = $block;
			}
		}
		return serialize_blocks( $blocks );
	}

	/**
	 * Clean HTML for the Classic Editor.
	 *
	 * @param array[] $items Items.
	 * @return string
	 */
	public static function to_html( array $items ) {
		$html = '';
		foreach ( $items as $item ) {
			$block = self::block( $item );
			if ( $block ) {
				$html .= render_block( $block ) . "\n\n";
			}
		}
		return trim( $html );
	}

	/**
	 * A clean table item: up to 8 columns and 40 rows, cells as inline text.
	 *
	 * @param array $item Raw item: head (cells), rows (lists of cells).
	 * @return array|null
	 */
	private static function clean_table( array $item ) {
		$cell = static function ( $value ) {
			return Kses_Policy::inline( is_scalar( $value ) ? (string) $value : '' );
		};
		$head = array_slice( array_map( $cell, isset( $item['head'] ) ? (array) $item['head'] : array() ), 0, 8 );
		$rows = array();
		foreach ( array_slice( isset( $item['rows'] ) ? (array) $item['rows'] : array(), 0, 40 ) as $row ) {
			$row = array_slice( array_map( $cell, (array) $row ), 0, 8 );
			if ( '' !== implode( '', $row ) ) {
				$rows[] = $row;
			}
		}
		if ( array() === $rows ) {
			return null;
		}
		// Every row as wide as the widest, so the table is regular.
		$width = max( count( $head ), max( array_map( 'count', $rows ) ) );
		$head  = array() === $head ? array() : array_pad( $head, $width, '' );
		foreach ( $rows as $i => $row ) {
			$rows[ $i ] = array_pad( $row, $width, '' );
		}
		return array(
			'type' => 'table',
			'head' => $head,
			'rows' => $rows,
		);
	}

	/**
	 * One parsed-block array.
	 *
	 * @param array $item Item.
	 * @return array|null
	 */
	private static function block( array $item ) {
		switch ( isset( $item['type'] ) ? $item['type'] : '' ) {
			case 'paragraph':
				return self::leaf( 'core/paragraph', array(), '<p>' . $item['text'] . '</p>' );
			case 'heading':
				$level = (int) $item['level'];
				$attrs = 2 === $level ? array() : array( 'level' => $level );
				return self::leaf( 'core/heading', $attrs, '<h' . $level . ' class="wp-block-heading">' . esc_html( $item['text'] ) . '</h' . $level . '>' );
			case 'list':
				$children = array();
				foreach ( $item['items'] as $entry ) {
					$children[] = self::leaf( 'core/list-item', array(), '<li>' . $entry . '</li>' );
				}
				$tag   = $item['ordered'] ? 'ol' : 'ul';
				$attrs = $item['ordered'] ? array( 'ordered' => true ) : array();
				$class = 'wp-block-list';
				if ( ! empty( $item['checklist'] ) ) {
					$attrs['className'] = 'qcwr-checklist';
					$class             .= ' qcwr-checklist';
				}
				return self::parent( 'core/list', $attrs, '<' . $tag . ' class="' . $class . '">', '</' . $tag . '>', $children );
			case 'table':
				$head = '';
				if ( array() !== $item['head'] ) {
					$head = '<thead><tr>' . implode(
						'',
						array_map(
							static function ( $cell ) {
								return '<th>' . $cell . '</th>';
							},
							$item['head']
						)
					) . '</tr></thead>';
				}
				$body = '';
				foreach ( $item['rows'] as $row ) {
					$body .= '<tr>' . implode(
						'',
						array_map(
							static function ( $cell ) {
								return '<td>' . $cell . '</td>';
							},
							$row
						)
					) . '</tr>';
				}
				// Fixed layout off, so the markup matches the table block on every supported WordPress version.
				return self::leaf( 'core/table', array( 'hasFixedLayout' => false ), '<figure class="wp-block-table"><table>' . $head . '<tbody>' . $body . '</tbody></table></figure>' );
			case 'chart':
				// CR-16: a bar chart AI Writer drew from approved research (never AI output).
				return self::leaf( 'core/html', array(), '<figure class="qcwr-chart-figure">' . wp_kses( (string) $item['svg'], self::svg_tags() ) . '</figure>' );
			case 'quote':
				return self::parent( 'core/quote', array(), '<blockquote class="wp-block-quote">', '</blockquote>', array( self::leaf( 'core/paragraph', array(), '<p>' . $item['text'] . '</p>' ) ) );
			case 'image':
				$id      = isset( $item['id'] ) ? (int) $item['id'] : 0;
				$url     = esc_url( isset( $item['url'] ) ? $item['url'] : '' );
				$alt     = esc_attr( isset( $item['alt'] ) ? $item['alt'] : '' );
				$caption = isset( $item['caption'] ) && '' !== $item['caption'] ? '<figcaption class="wp-element-caption">' . Kses_Policy::inline( $item['caption'] ) . '</figcaption>' : '';
				$attrs   = $id ? array(
					'id'       => $id,
					'sizeSlug' => 'large',
				) : array();
				$class   = $id ? ' wp-image-' . $id : '';
				return self::leaf( 'core/image', $attrs, '<figure class="wp-block-image size-large"><img src="' . $url . '" alt="' . $alt . '" class="' . trim( $class ) . '"/>' . $caption . '</figure>' );
			case 'placeholder_image':
				$label = esc_html( isset( $item['label'] ) ? $item['label'] : '' );
				return self::leaf( 'core/paragraph', array( 'className' => 'qcwr-image-placeholder' ), '<p class="qcwr-image-placeholder">[' . $label . ']</p>' );
		}
		return null;
	}

	/**
	 * Block without inner blocks.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes.
	 * @param string $html  Inner HTML.
	 * @return array
	 */
	private static function leaf( $name, array $attrs, $html ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block with inner blocks between an opening and closing wrapper.
	 *
	 * @param string $name     Block name.
	 * @param array  $attrs    Attributes.
	 * @param string $open     Opening HTML.
	 * @param string $close    Closing HTML.
	 * @param array  $children Inner blocks.
	 * @return array
	 */
	private static function parent( $name, array $attrs, $open, $close, array $children ) {
		$content = array( $open );
		foreach ( $children as $unused ) {
			$content[] = null;
		}
		$content[] = $close;
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => $open . $close,
			'innerContent' => $content,
		);
	}

	/**
	 * Tags and attributes allowed in research charts.
	 *
	 * @return array
	 */
	public static function svg_tags() {
		$shape = array(
			'x'           => true,
			'y'           => true,
			'width'       => true,
			'height'      => true,
			'rx'          => true,
			'fill'        => true,
			'font-size'   => true,
			'text-anchor' => true,
		);
		return array(
			'svg'   => array(
				'xmlns'   => true,
				'role'    => true,
				'class'   => true,
				'viewbox' => true,
				'width'   => true,
			),
			'title' => array(),
			'rect'  => $shape,
			'text'  => $shape,
		);
	}
}
