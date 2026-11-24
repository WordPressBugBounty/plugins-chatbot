<?php
/**
 * Glossary (HC1-10, plan 5.1): terms with a short definition, an A–Z page at
 * <base>/glossary/ and popovers on the first use of each term in an article.
 *
 * Terms are the taxonomy wpbot_glossary (name and description; not attached to
 * articles, no archive pages), edited under Help Center → Glossary. Popovers can
 * be switched off in Help Center → Settings.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Glossary;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Front\Front;
use QuantumCloud\HelpCenter\Front\Urls;
use QuantumCloud\HelpCenter\Languages;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Glossary terms, the A–Z list and linking.
 */
final class Glossary {

	/**
	 * Taxonomy.
	 */
	const TAXONOMY = 'wpbot_glossary';

	/**
	 * Query variable of the A–Z page.
	 */
	const QUERY_VAR = 'wpbot_help_glossary';

	/**
	 * URL word after the address.
	 */
	const SLUG = 'glossary';

	/**
	 * Cache of the terms for linking.
	 */
	const CACHE = 'qcld_wpbot_help_center_glossary';

	/**
	 * Definitions of the terms linked on this page.
	 *
	 * @var string
	 */
	private static $defs = '';

	/**
	 * Elements whose text is never linked.
	 *
	 * @var string[]
	 */
	const SKIP = array( 'a', 'button', 'code', 'pre', 'kbd', 'samp', 'script', 'style', 'textarea', 'select', 'option', 'label', 'svg', 'math', 'dfn', 'abbr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'summary', 'figcaption', 'template' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ), 9 );
		add_action( 'init', array( self::class, 'rewrite' ), 11 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'the_content', array( self::class, 'content' ), 20 );
		add_filter( 'document_title_parts', array( self::class, 'title' ) );
		foreach ( array( 'created_', 'edited_', 'delete_' ) as $hook ) {
			add_action( $hook . self::TAXONOMY, array( self::class, 'forget' ) );
		}
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register() {
		register_taxonomy(
			self::TAXONOMY,
			Post_Type::NAME,
			array(
				'labels'             => array(
					'name'          => __( 'Glossary', 'chatbot' ),
					'singular_name' => __( 'Glossary term', 'chatbot' ),
					'add_new_item'  => __( 'Add term', 'chatbot' ),
					'edit_item'     => __( 'Edit term', 'chatbot' ),
					'search_items'  => __( 'Search terms', 'chatbot' ),
					'not_found'     => __( 'No terms yet.', 'chatbot' ),
					'back_to_items' => __( '← Back to the glossary', 'chatbot' ),
				),
				'description'        => __( 'Words your customers may not know, with a short definition. They get a popover in articles and a place on the A–Z page.', 'chatbot' ),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => false,
				'show_admin_column'  => false,
				'meta_box_cb'        => false,
				'hierarchical'       => false,
				'show_in_rest'       => true,
				'rest_base'          => 'help-glossary',
				'rewrite'            => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_wpbot_article_terms',
					'edit_terms'   => 'manage_wpbot_article_terms',
					'delete_terms' => 'manage_wpbot_article_terms',
					'assign_terms' => 'manage_wpbot_article_terms',
				),
			)
		);
	}

	/**
	 * The A–Z page's URL rule (before the article rules, which would take "glossary" as an article path).
	 *
	 * @return void
	 */
	public static function rewrite() {
		add_rewrite_rule( '^' . preg_quote( Settings::base(), '#' ) . '/' . self::SLUG . '/?$', 'index.php?post_type=' . Post_Type::NAME . '&' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Allow the query variable.
	 *
	 * @param string[] $vars Variables.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Page title of the A–Z page.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title( $parts ) {
		if ( self::is_page() ) {
			$parts['title'] = __( 'Glossary', 'chatbot' );
		}
		return $parts;
	}

	/**
	 * URL of the A–Z page.
	 *
	 * @param string $slug Term slug to jump to, or ''.
	 * @return string
	 */
	public static function url( $slug = '' ) {
		$url = Urls::home();
		$url = get_option( 'permalink_structure' ) ? trailingslashit( $url ) . self::SLUG . '/' : add_query_arg( self::QUERY_VAR, '1', $url );
		return '' !== $slug ? $url . '#g-' . rawurlencode( $slug ) : $url;
	}

	/**
	 * Whether this request is the A–Z page.
	 *
	 * @return bool
	 */
	public static function is_page() {
		return '' !== (string) get_query_var( self::QUERY_VAR );
	}

	/**
	 * All terms, by name.
	 *
	 * @return array[] Each: name, slug, definition (HTML), text (plain).
	 */
	public static function terms() {
		$cached = get_transient( Languages::key( self::CACHE ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 2000,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out[] = array(
				'name'       => (string) $term->name,
				'slug'       => (string) $term->slug,
				'definition' => (string) $term->description,
				'text'       => trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $term->description ) ) ),
			);
		}
		set_transient( Languages::key( self::CACHE ), $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Forget the cached terms.
	 *
	 * @return void
	 */
	public static function forget() {
		Languages::forget( self::CACHE );
	}

	/**
	 * Terms grouped by first letter: A–Z first, then other letters, then "#" for digits and signs.
	 *
	 * @param array[] $terms Terms (name, slug, ...).
	 * @return array<string,array[]> Letter => terms.
	 */
	public static function group( array $terms ) {
		$groups = array();
		foreach ( $terms as $term ) {
			$first = function_exists( 'mb_substr' ) ? mb_substr( (string) $term['name'], 0, 1, 'UTF-8' ) : substr( (string) $term['name'], 0, 1 );
			$first = function_exists( 'remove_accents' ) ? remove_accents( $first ) : $first;
			$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first );
			if ( 1 !== preg_match( '/^\p{L}$/u', $first ) ) {
				$first = '#';
			}
			$groups[ $first ][] = $term;
		}
		uksort(
			$groups,
			static function ( $a, $b ) {
				$rank = static function ( $letter ) {
					if ( '#' === $letter ) {
						return 2;
					}
					return 1 === preg_match( '/^[A-Z]$/', $letter ) ? 0 : 1;
				};
				return array( $rank( $a ), $a ) <=> array( $rank( $b ), $b );
			}
		);
		foreach ( $groups as &$list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a['name'], (string) $b['name'] );
				}
			);
		}
		unset( $list );
		return $groups;
	}

	/**
	 * Link the first use of each term in an article's content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		if ( 'single' !== Front::view() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || Post_Type::NAME !== $post->post_type || ! self::popovers_on() ) {
			return $content;
		}
		$terms = self::terms();
		if ( array() === $terms ) {
			return $content;
		}
		$html = self::link( (string) $content, $terms, self::url(), 'wpbot-hc-g-' . (int) $post->ID );
		// The definitions go at the end of the page, so text taken from the content (descriptions, reading time) leaves them out.
		$at = strrpos( $html, '<div class="wpbot-hc-term-defs"' );
		if ( false !== $at ) {
			self::$defs = substr( $html, $at );
			$html       = substr( $html, 0, $at );
			if ( ! has_action( 'wp_footer', array( self::class, 'footer' ) ) ) {
				add_action( 'wp_footer', array( self::class, 'footer' ) );
			}
		}
		return $html;
	}

	/**
	 * Print the definitions the popovers read.
	 *
	 * @return void
	 */
	public static function footer() {
		echo wp_kses(
			self::$defs,
			array(
				'div'  => array(
					'class'  => true,
					'hidden' => true,
				),
				'span' => array( 'id' => true ),
			)
		);
	}

	/**
	 * Whether popovers are on (Settings).
	 *
	 * @return bool
	 */
	public static function popovers_on() {
		return '0' !== (string) Settings::all()['glossary_popovers'];
	}

	/**
	 * Mark the first use of each term in HTML: a link to the A–Z entry, described by the definition.
	 *
	 * Text inside links, buttons, code, headings and similar elements is left alone.
	 *
	 * @param string  $html   HTML.
	 * @param array[] $terms  Terms (name, slug, text).
	 * @param string  $page   URL of the A–Z page.
	 * @param string  $prefix Id prefix for the definitions.
	 * @return string
	 */
	public static function link( $html, array $terms, $page, $prefix ) {
		$by_name = array();
		foreach ( $terms as $term ) {
			$name = trim( (string) $term['name'] );
			if ( '' !== $name && '' !== (string) $term['text'] ) {
				$by_name[ self::lower( $name ) ] = $term;
			}
		}
		if ( array() === $by_name || '' === trim( $html ) ) {
			return $html;
		}
		$names = array_map( 'strval', array_keys( $by_name ) );
		usort(
			$names,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$patterns = array();
		foreach ( array_chunk( $names, 200 ) as $chunk ) {
			$patterns[] = '/(?<![\p{L}\p{N}_-])(' . implode(
				'|',
				array_map(
					static function ( $n ) { // phpcs:ignore Universal.FunctionDeclarations.NoLongClosures -- Short.
						return preg_quote( $n, '/' );
					},
					$chunk
				)
			) . ')(?![\p{L}\p{N}_-])/iu';
		}
		$used  = array();
		$defs  = '';
		$skip  = 0;
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( (array) $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( 1 === preg_match( '#^<(/?)([a-z][a-z0-9-]*)#i', $part, $m ) && in_array( strtolower( $m[2] ), self::SKIP, true ) && '/>' !== substr( $part, -2 ) ) {
					$skip = max( 0, $skip + ( '/' === $m[1] ? -1 : 1 ) );
				}
				continue;
			}
			if ( $skip > 0 || '' === trim( $part ) ) {
				continue;
			}
			foreach ( $patterns as $pattern ) {
				$part = (string) preg_replace_callback(
					$pattern,
					static function ( $found ) use ( &$used, &$defs, $by_name, $page, $prefix ) {
						$key = self::lower( $found[1] );
						if ( isset( $used[ $key ] ) || ! isset( $by_name[ $key ] ) ) {
							return $found[0];
						}
						$term         = $by_name[ $key ];
						$used[ $key ] = true;
						$id           = $prefix . '-' . count( $used );
						$defs        .= '<span id="' . esc_attr( $id ) . '">' . esc_html( $term['text'] ) . '</span>';
						return '<a class="wpbot-hc-term" href="' . esc_url( $page . '#g-' . rawurlencode( (string) $term['slug'] ) ) . '" aria-describedby="' . esc_attr( $id ) . '" data-term="' . esc_attr( (string) $term['name'] ) . '">' . $found[1] . '</a>';
					},
					$part
				);
			}
			$parts[ $i ] = $part;
		}//end foreach
		if ( '' === $defs ) {
			return $html;
		}
		return implode( '', (array) $parts ) . '<div class="wpbot-hc-term-defs" hidden>' . $defs . '</div>';
	}

	/**
	 * Lower case.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	}
}
