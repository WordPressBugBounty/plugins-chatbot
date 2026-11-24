<?php
/**
 * Start menu items stored as data (Start Menu reorganization SM1).
 *
 * The admin's Active Menu was saved as raw HTML, one copy per language, in `qc_wpbot_menu_order`, and
 * the chat showed that HTML as it was. A renamed label didn't reach the menu until the item was
 * removed and added again, each language's menu was built by hand, and a feature that was switched
 * off could stay in the menu.
 *
 * Items are now kept as data in `qcld_wpbot_menu_items`:
 * - one entry per item: id, key, type, built-in id, the span's attributes, a label per language;
 * - built-in items (site search, FAQ, email, call me…) take their label from their own text setting,
 *   so a rename shows at once, in every language, and they disappear when switched off;
 * - the GoodBye button is dropped (owner, 19 November 2026: the bot still understands "bye").
 *
 * Compatibility: every reader of `qc_wpbot_menu_order` (the widget, safe answers, the admin) gets HTML
 * built from the items, through the option filter. The legacy admin builder keeps working: when it
 * saves HTML, the items are rebuilt from it. The stored HTML itself is never changed, so a downgrade
 * still finds the old menu.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Menu;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Start menu items.
 */
final class Menu_Items {

	/**
	 * Option: the items (not autoloaded).
	 */
	const OPTION = 'qcld_wpbot_menu_items';

	/**
	 * Option: the legacy HTML menu (kept, read through the filter).
	 */
	const LEGACY = 'qc_wpbot_menu_order';

	/**
	 * Data format version.
	 */
	const VERSION = 1;

	/**
	 * Classes the admin builder adds while dragging; never part of an item.
	 */
	const BUILDER_CLASSES = array( 'ui-draggable', 'ui-draggable-handle', 'ui-sortable-handle', 'qc_draggable_item', 'qc_draggable_item_remove', 'qcld-mb-selected' );

	/**
	 * Attributes added when a menu is shown (chat or builder) and never stored. data-qcld-hidden is
	 * read by from_legacy() before it is dropped.
	 */
	const ADMIN_MARKERS = array( 'data-qcld-menu', 'data-qcld-id', 'data-qcld-hidden', 'data-qcld-off', 'data-qcld-builtin' );

	/**
	 * True while the raw legacy option is read (the filter returns it unchanged).
	 *
	 * @var bool
	 */
	private static $raw = false;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'option_' . self::LEGACY, array( __CLASS__, 'filter_legacy' ), 20 );
		add_action( 'update_option_' . self::LEGACY, array( __CLASS__, 'legacy_saved' ), 10, 2 );
		add_action( 'add_option_' . self::LEGACY, array( __CLASS__, 'legacy_added' ), 10, 2 );
	}

	/**
	 * Built-in items: classes (sorted, builder classes removed) or key => id, label option, and the
	 * options that switch it off or on. `removed` items are dropped.
	 *
	 * @return array[]
	 */
	public static function builtins() {
		$builtins = array(
			'faq'            => array(
				'match' => 'action:support',
				'label' => 'qlcd_wp_chatbot_wildcard_support',
				'off'   => 'disable_wp_chatbot_faq',
			),
			'messenger'      => array(
				'match' => 'action:messenger',
				'label' => 'qlcd_wp_chatbot_messenger_label',
				'on'    => 'enable_wp_chatbot_messenger',
			),
			'whatsapp'       => array(
				'match' => 'action:whatsapp',
				'label' => 'qlcd_wp_chatbot_whats_label',
				'on'    => 'enable_wp_chatbot_whats',
			),
			'site_search'    => array(
				'match' => 'classes:qcld-chatbot-site-search',
				'label' => 'qlcd_wp_site_search',
				'off'   => 'disable_wp_chatbot_site_search',
			),
			'subscription'   => array(
				'match' => 'classes:qcld-chatbot-default wpbd_subscription',
				'label' => 'qlcd_wp_email_subscription',
				'off'   => 'disable_email_subscription',
			),
			'send_email'     => array(
				'match' => 'classes:qcld-chatbot-suggest-email',
				'label' => 'qlcd_wp_send_us_email',
				'off'   => 'disable_wp_chatbot_feedback',
			),
			'feedback'       => array(
				'match' => 'classes:qcld-chatbot-suggest-email wpbd_feedback',
				'label' => 'qlcd_wp_leave_feedback',
				'off'   => 'disable_wp_leave_feedback',
			),
			'call_me'        => array(
				'match' => 'classes:qcld-chatbot-suggest-phone',
				'label' => 'qlcd_wp_chatbot_support_phone',
				'off'   => 'disable_wp_chatbot_call_gen',
			),
			'str_categories' => array(
				'match' => 'classes:qcld-chatbot-wildcard wpbd_str_categories',
				'label' => 'qlcd_wp_str_category',
				'off'   => 'disable_str_categories',
			),
			'voice_message'  => array(
				'match' => 'classes:qcld-chatbot-wildcard wpbd_voice_message',
				'label' => 'qlcd_wp_voice_message',
				'off'   => 'disable_voice_message',
			),
			// n8n and OpenAI Agent Builder are no longer start menu buttons (owner, 23 November 2026).
			'n8n'            => array(
				'match'   => 'classes:qcld-chatbot-default qcld_btn_n8n_txt',
				'removed' => true,
			),
			'agent_builder'  => array(
				'match'   => 'classes:qcld-chatbot-default qcld_btn_openai_agentbuilder_txt',
				'removed' => true,
			),
			'goodbye'        => array(
				'match'   => 'classes:qcld-chatbot-default wpbd_good_bye',
				'removed' => true,
			),
		);
		/**
		 * Built-in start menu items (Pro and add-ons add theirs).
		 *
		 * @param array[] $builtins id => { match, label, off, on, removed }.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/menu_builtins', $builtins );
	}

	/**
	 * Identity of an item. Same rule as the host's `qcld_wpbot_menu_item_key()`, the chat's
	 * `wpwKits.menuItemKey()` and the admin's `qcWpbotMenuKey()`.
	 *
	 * @param string $attrs Span attributes.
	 * @param string $label Span inner HTML.
	 * @return string
	 */
	public static function key( $attrs, $label ) {
		$attrs = stripslashes( (string) $attrs );
		$text  = self::plain( stripslashes( (string) $label ) );
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$attr  = self::attr_reader( $attrs );
		foreach ( array(
			'data-form'     => 'form:',
			'data-ai-form'  => 'ai:',
			'data-strid'    => 'str:',
			'data-text'     => 'intent:',
			'data-wildcart' => 'action:',
		) as $name => $prefix ) {
			if ( '' !== $attr( $name ) ) {
				return $prefix . ( 'data-text' === $name ? strtolower( $attr( $name ) ) : $attr( $name ) );
			}
		}
		if ( '' !== $attr( 'data-link' ) ) {
			return 'link:' . $attr( 'data-link' ) . '|' . $text;
		}
		return 'text:' . self::classes( $attr( 'class' ) ) . '|' . $text;
	}

	/**
	 * The items, built once from the legacy HTML menu when there are none yet.
	 *
	 * @return array[]
	 */
	public static function items() {
		$stored = get_option( self::OPTION, null );
		if ( is_array( $stored ) && isset( $stored['items'] ) && is_array( $stored['items'] ) ) {
			return $stored['items'];
		}
		$items = self::from_legacy( self::raw_legacy() );
		if ( $items ) {
			self::save( $items );
		}
		return $items;
	}

	/**
	 * Save the items.
	 *
	 * @param array[] $items Items.
	 * @return void
	 */
	public static function save( array $items ) {
		update_option(
			self::OPTION,
			array(
				'version' => self::VERSION,
				'items'   => array_values( $items ),
			),
			false
		);
	}

	/**
	 * Build items from a legacy menu (HTML string, serialized string, or locale => HTML).
	 *
	 * @param mixed $legacy Stored legacy value.
	 * @return array[]
	 */
	public static function from_legacy( $legacy ) {
		$menus = self::legacy_menus( $legacy );
		if ( ! $menus ) {
			return array();
		}
		$primary = isset( $menus[ Options::locale() ] ) ? Options::locale() : (string) key( $menus );
		$order   = array_merge( array( $primary ), array_diff( array_keys( $menus ), array( $primary ) ) );
		$items   = array();
		foreach ( $order as $locale ) {
			$position = 0;
			foreach ( self::parse( $menus[ $locale ] ) as $span ) {
				$key = self::key( $span['attrs'], $span['label'] );
				$id  = self::builtin_id( $key );
				if ( '' !== $id && ! empty( self::builtins()[ $id ]['removed'] ) ) {
					continue;
				}
				if ( '' !== $id ) {
					$key = 'builtin:' . $id;
					// The same built-in item in every language, whatever its label.
				}
				if ( isset( $items[ $key ]['positions'][ $locale ] ) ) {
					continue;
					// Repeated in this language (the old builder allowed it).
				}
				if ( ! isset( $items[ $key ] ) ) {
					$items[ $key ] = array(
						'id'        => substr( md5( $key ), 0, 12 ),
						'key'       => $key,
						'type'      => '' !== $id ? 'builtin' : strtok( $key, ':' ),
						'builtin'   => $id,
						'attrs'     => self::clean_attrs( $span['attrs'] ),
						'labels'    => array(),
						'positions' => array(),
						'hidden'    => false !== stripos( (string) $span['attrs'], 'data-qcld-hidden="1"' ),
					);
				}
				$items[ $key ]['positions'][ $locale ] = $position++;
				$label                                 = self::plain( stripslashes( $span['label'] ) );
				if ( '' !== $label && ! isset( $items[ $key ]['labels'][ $locale ] ) ) {
					$items[ $key ]['labels'][ $locale ] = $label;
				}
			}//end foreach
		}//end foreach
		return array_values( $items );
	}

	/**
	 * The menu for one language, as the HTML the widget and the admin read.
	 *
	 * @param string $locale Locale ('' for the site's).
	 * @return string
	 */
	public static function render( $locale = '' ) {
		$locale = '' !== $locale ? $locale : Options::locale();
		$html   = '';
		/**
		 * The items of a shown menu, in order (Context Aware page rules reorder and filter them).
		 *
		 * @param array[] $items  Items.
		 * @param string  $locale Locale.
		 */
		$items = (array) apply_filters( 'qcld_wpbot_chat/menu_items_shown', self::ordered( $locale ), $locale );
		foreach ( $items as $item ) {
			if ( ! self::available( $item ) ) {
				continue;
			}
			$label = self::label( $item, $locale );
			if ( '' === $label ) {
				continue;
			}
			// data-qcld-menu: the widget's button component shows the first top-level items and puts
			// the rest under "More options" (owner M2); other button lists are left alone.
			$attrs = array_merge( isset( $item['attrs'] ) ? (array) $item['attrs'] : array(), array( 'data-qcld-menu' => '1' ) );
			$html .= '<span ' . self::attrs_html( $attrs ) . '>' . esc_html( $label ) . '</span>';
		}
		return $html;
	}

	/**
	 * Some items, by id and in the given order, as the widget's own start menu buttons (so its click
	 * handlers run them). Unavailable or unknown ids are skipped.
	 *
	 * @param string[] $ids    Item ids.
	 * @param string   $locale Locale ('' for the site's).
	 * @return string
	 */
	public static function render_ids( array $ids, $locale = '' ) {
		$locale = '' !== $locale ? $locale : Options::locale();
		$by_id  = array();
		foreach ( self::items() as $item ) {
			$by_id[ (string) $item['id'] ] = $item;
		}
		$html = '';
		foreach ( $ids as $id ) {
			$item = isset( $by_id[ (string) $id ] ) ? $by_id[ (string) $id ] : null;
			if ( null === $item || ! self::available( $item ) ) {
				continue;
			}
			$label = self::label( $item, $locale );
			if ( '' !== $label ) {
				$html .= '<span ' . self::attrs_html( isset( $item['attrs'] ) ? $item['attrs'] : array() ) . '>' . esc_html( $label ) . '</span>';
			}
		}
		return $html;
	}

	/**
	 * The items of one language, in that language's order. A language that had its own legacy menu
	 * keeps exactly its items and order; another language gets every item, in the stored order.
	 *
	 * @param string $locale Locale.
	 * @return array[]
	 */
	public static function ordered( $locale ) {
		$items = self::items();
		$own   = array_filter(
			$items,
			static function ( $item ) use ( $locale ) {
				return isset( $item['positions'][ $locale ] );
			}
		);
		if ( ! $own ) {
			return $items;
		}
		usort(
			$own,
			static function ( $a, $b ) use ( $locale ) {
				return $a['positions'][ $locale ] - $b['positions'][ $locale ];
			}
		);
		return $own;
	}

	/**
	 * Whether an item shows: not hidden, and a built-in item's feature is switched on.
	 *
	 * @param array $item Item.
	 * @return bool
	 */
	public static function available( array $item ) {
		if ( ! empty( $item['hidden'] ) ) {
			return false;
		}
		$builtins = self::builtins();
		$id       = isset( $item['builtin'] ) ? (string) $item['builtin'] : '';
		$def      = '' !== $id && isset( $builtins[ $id ] ) ? $builtins[ $id ] : array();
		$on       = true;
		if ( ! empty( $def['removed'] ) ) {
			$on = false;
		} elseif ( ! empty( $def['off'] ) && '1' === (string) get_option( $def['off'], '' ) ) {
			$on = false;
		} elseif ( ! empty( $def['on'] ) && '1' !== (string) get_option( $def['on'], '' ) ) {
			$on = false;
		}
		/**
		 * Whether a start menu item shows now.
		 *
		 * @param bool  $on   Default.
		 * @param array $item Item.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/menu_item_available', $on, $item );
	}

	/**
	 * An item's label in a language: a built-in item's own text setting first, then the label saved
	 * for that language, then the first saved label.
	 *
	 * @param array  $item   Item.
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function label( array $item, $locale ) {
		$label    = '';
		$builtins = self::builtins();
		$id       = isset( $item['builtin'] ) ? (string) $item['builtin'] : '';
		$labels   = isset( $item['labels'] ) && is_array( $item['labels'] ) ? $item['labels'] : array();
		$saved    = isset( $labels[ $locale ] ) ? (string) $labels[ $locale ] : '';
		if ( '' !== $id && ! empty( $builtins[ $id ]['label'] ) ) {
			$variants = self::setting_variants( $builtins[ $id ]['label'], $locale );
			$lower    = array_map( array( __CLASS__, 'lower' ), $variants );
			if ( '' !== $saved && ( ! $variants || in_array( self::lower( $saved ), $lower, true ) ) ) {
				// The setting still says the same thing (a fresh site stores "faq" in the setting and
				// "FAQ" in the menu): keep the menu's own label.
				$label = $saved;
			} elseif ( $variants ) {
				$label = $variants[0];
				// Renamed in its setting: shown at once, without re-adding the item.
			}
		}
		if ( '' === $label ) {
			$label = '' !== $saved ? $saved : ( $labels ? (string) reset( $labels ) : '' );
		}
		/**
		 * A start menu item's label.
		 *
		 * @param string $label  Label.
		 * @param array  $item   Item.
		 * @param string $locale Locale.
		 */
		return trim( (string) apply_filters( 'qcld_wpbot_chat/menu_item_label', $label, $item, $locale ) );
	}

	/**
	 * A text setting's variants in a language (stored as text, a list of variants, or per language).
	 *
	 * @param string $option Option.
	 * @param string $locale Locale.
	 * @return string[]
	 */
	private static function setting_variants( $option, $locale ) {
		$value = maybe_unserialize( get_option( $option, '' ) );
		if ( is_array( $value ) && ! isset( $value[0] ) ) {
			$value = isset( $value[ $locale ] ) ? $value[ $locale ] : reset( $value );
		}
		$value = maybe_unserialize( $value );
		$list  = array();
		foreach ( (array) $value as $variant ) {
			$variant = is_scalar( $variant ) ? self::plain( (string) $variant ) : '';
			if ( '' !== $variant ) {
				$list[] = $variant;
			}
		}
		return $list;
	}

	/**
	 * Lower-case text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	}

	/**
	 * Filter for `option_qc_wpbot_menu_order`: readers get the menu built from the items, in the shape
	 * they expect (locale => HTML when the stored menu is per language, else one HTML string).
	 *
	 * @param mixed $value Stored value.
	 * @return mixed
	 */
	public static function filter_legacy( $value ) {
		if ( self::$raw ) {
			return $value;
		}
		$menus = self::legacy_menus( $value );
		if ( ! $menus || ! self::items() ) {
			return $value;
		}
		$per_language = is_array( maybe_unserialize( $value ) );
		if ( ! $per_language ) {
			return self::render();
		}
		$out = array();
		foreach ( array_keys( $menus ) as $locale ) {
			$out[ $locale ] = self::render( $locale );
		}
		return $out;
	}

	/**
	 * The legacy builder saved a menu: rebuild the items from it, keeping hidden flags.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public static function legacy_saved( $old_value, $value ) {
		unset( $old_value );
		if ( self::from_builder( $value ) ) {
			// The Items builder (SM2) marks hidden items itself, and showing one again clears it.
			self::save( self::from_legacy( $value ) );
			return;
		}
		$hidden = array();
		$stored = get_option( self::OPTION, null );
		if ( is_array( $stored ) && ! empty( $stored['items'] ) ) {
			foreach ( $stored['items'] as $item ) {
				if ( ! empty( $item['hidden'] ) ) {
					$hidden[ $item['key'] ] = true;
				}
			}
		}
		$items = self::from_legacy( $value );
		foreach ( $items as $index => $item ) {
			if ( isset( $hidden[ $item['key'] ] ) ) {
				$items[ $index ]['hidden'] = true;
			}
		}
		self::save( $items );
	}

	/**
	 * Whether a saved legacy menu comes from the Items builder (its spans carry data-qcld-id).
	 *
	 * @param mixed $value Saved value.
	 * @return bool
	 */
	private static function from_builder( $value ) {
		foreach ( self::legacy_menus( $value ) as $html ) {
			if ( false !== strpos( stripslashes( $html ), 'data-qcld-id=' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The menu of one language for the Items builder (SM2): every item in that language's order,
	 * hidden ones included, with the builder's markers (id, hidden, switched off, built-in id).
	 *
	 * @param string $locale Locale ('' for the site's).
	 * @return string
	 */
	public static function admin_html( $locale = '' ) {
		$locale = '' !== $locale ? $locale : Options::locale();
		$html   = '';
		foreach ( self::ordered( $locale ) as $item ) {
			$label = self::label( $item, $locale );
			if ( '' === $label ) {
				continue;
			}
			$hidden = ! empty( $item['hidden'] );
			$attrs  = array_merge(
				isset( $item['attrs'] ) ? (array) $item['attrs'] : array(),
				array(
					'data-qcld-id'      => (string) $item['id'],
					'data-qcld-builtin' => isset( $item['builtin'] ) ? (string) $item['builtin'] : '',
					'data-qcld-hidden'  => $hidden ? '1' : '0',
					'data-qcld-off'     => ! $hidden && ! self::available( $item ) ? '1' : '0',
				)
			);
			$html  .= '<span ' . self::attrs_html( $attrs ) . '>' . esc_html( $label ) . '</span>';
		}
		return $html;
	}

	/**
	 * The legacy menu was added for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public static function legacy_added( $option, $value ) {
		unset( $option );
		self::legacy_saved( null, $value );
	}

	/**
	 * The stored legacy value, without this class's filter.
	 *
	 * @return mixed
	 */
	private static function raw_legacy() {
		self::$raw = true;
		$value     = get_option( self::LEGACY, '' );
		self::$raw = false;
		return $value;
	}

	/**
	 * Legacy value as locale => HTML ('' key for a menu with no language).
	 *
	 * @param mixed $legacy Stored value.
	 * @return string[]
	 */
	private static function legacy_menus( $legacy ) {
		$legacy = maybe_unserialize( $legacy );
		if ( is_string( $legacy ) ) {
			$legacy = '' !== trim( $legacy ) ? array( Options::locale() => $legacy ) : array();
		}
		$menus = array();
		foreach ( (array) $legacy as $locale => $html ) {
			$html = maybe_unserialize( $html );
			if ( is_string( $html ) && false !== stripos( $html, '<span' ) ) {
				$menus[ (string) $locale ] = $html;
			}
		}
		return $menus;
	}

	/**
	 * The spans of a legacy menu.
	 *
	 * @param string $html Menu HTML.
	 * @return array[] { attrs, label }.
	 */
	private static function parse( $html ) {
		$spans = array();
		if ( preg_match_all( '#<span\b([^>]*)>(.*?)</span>#is', stripslashes( (string) $html ), $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$spans[] = array(
					'attrs' => $match[1],
					'label' => $match[2],
				);
			}
		}
		return $spans;
	}

	/**
	 * Built-in id for a key ('' when not built in).
	 *
	 * @param string $key Item key.
	 * @return string
	 */
	private static function builtin_id( $key ) {
		$classes = 0 === strpos( $key, 'text:' ) ? substr( $key, 5, (int) strpos( $key, '|' ) - 5 ) : null;
		foreach ( self::builtins() as $id => $def ) {
			$match = isset( $def['match'] ) ? (string) $def['match'] : '';
			if ( $match === $key || ( null !== $classes && 'classes:' . $classes === $match ) ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * Span attributes as name => value, builder classes removed, unknown attribute names dropped.
	 *
	 * @param string $attrs Attribute string.
	 * @return string[]
	 */
	private static function clean_attrs( $attrs ) {
		$out = array();
		if ( preg_match_all( '/([a-zA-Z][a-zA-Z0-9_:-]*)\s*=\s*(["\'])(.*?)\2/s', stripslashes( (string) $attrs ), $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( $match[1] );
				// No event handlers, styles or ids from stored HTML; the menu marker and the builder's markers
				// are added when the menu is shown and never stored (the builder saves the shown HTML back).
				if ( ( 'class' !== $name && 'title' !== $name && 0 !== strpos( $name, 'data-' ) ) || in_array( $name, self::ADMIN_MARKERS, true ) ) {
					continue;
				}
				$value        = html_entity_decode( $match[3], ENT_QUOTES, 'UTF-8' );
				$out[ $name ] = 'class' === $name ? implode( ' ', array_diff( preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY ), self::BUILDER_CLASSES ) ) : $value;
			}
		}
		return $out;
	}

	/**
	 * Attributes as HTML.
	 *
	 * @param array $attrs name => value.
	 * @return string
	 */
	private static function attrs_html( array $attrs ) {
		$parts = array();
		foreach ( $attrs as $name => $value ) {
			$name = strtolower( (string) $name );
			if ( ! preg_match( '/^(class|title|data-[a-z0-9_:-]+)$/', $name ) ) {
				continue;
			}
			$parts[] = $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Sorted classes without the builder's.
	 *
	 * @param string $class_attr Class attribute.
	 * @return string
	 */
	private static function classes( $class_attr ) {
		$classes = array_diff( preg_split( '/\s+/', (string) $class_attr, -1, PREG_SPLIT_NO_EMPTY ), self::BUILDER_CLASSES );
		sort( $classes );
		return implode( ' ', $classes );
	}

	/**
	 * Reads one attribute from an attribute string.
	 *
	 * @param string $attrs Attributes.
	 * @return callable
	 */
	private static function attr_reader( $attrs ) {
		return static function ( $name ) use ( $attrs ) {
			return preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', ' ' . $attrs, $m ) ? trim( $m[2] ) : '';
		};
	}

	/**
	 * Plain text of a label.
	 *
	 * @param string $html Label HTML.
	 * @return string
	 */
	private static function plain( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
