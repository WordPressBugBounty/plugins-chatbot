<?php
/**
 * Search engines and AI assistants (HC1-12, plan 5.1 "SEO", H15).
 *
 * - A Markdown copy of each article at its address + ".md" (for example
 *   /help/setup/connect-store.md), announced with <link rel="alternate">.
 *   Not indexed (X-Robots-Tag), canonical to the article.
 * - <address>/llms.txt: the Help Center's articles by category, linking to the
 *   Markdown copies (public articles only). When the site's own llms.txt (SEO &
 *   AI Search) lists help articles, it links to their Markdown copies too.
 * - 301 redirects from earlier Help Center addresses (Settings keeps the last
 *   ten) to the same path under the current one, when the old URL finds nothing.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Seo;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Admin\Settings_Page;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Front\Data;
use QuantumCloud\HelpCenter\Front\Urls;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Markdown copies, llms.txt and redirects.
 */
final class Seo {

	/**
	 * Query variable: Markdown copy.
	 */
	const MD_VAR = 'wpbot_help_md';

	/**
	 * Query variable: llms.txt.
	 */
	const LLMS_VAR = 'wpbot_help_llms';

	/**
	 * Most articles listed in llms.txt.
	 */
	const LLMS_MAX = 1000;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'rewrite' ), 11 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'redirect_old_address' ), 5 );
		add_action( 'template_redirect', array( self::class, 'serve' ), 6 );
		add_action( 'wp_head', array( self::class, 'alternate' ), 5 );
		add_filter( 'redirect_canonical', array( self::class, 'no_canonical_redirect' ) );
		add_filter( 'qcld_wpbot_seo/llms_txt_url', array( self::class, 'llms_url' ), 10, 2 );
		Schema::init();
	}

	/**
	 * URL rules (before the article rules). The post type is in each query so
	 * Polylang adds its language prefix to these rules too.
	 *
	 * @return void
	 */
	public static function rewrite() {
		$base = preg_quote( Settings::base(), '#' );
		add_rewrite_rule( '^' . $base . '/llms\.txt$', 'index.php?post_type=' . Post_Type::NAME . '&' . self::LLMS_VAR . '=1', 'top' );
		add_rewrite_rule( '^' . $base . '/(.+?)\.md$', 'index.php?post_type=' . Post_Type::NAME . '&' . Post_Type::NAME . '=$matches[1]&' . self::MD_VAR . '=1', 'top' );
	}

	/**
	 * Allow the query variables.
	 *
	 * @param string[] $vars Variables.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::MD_VAR;
		$vars[] = self::LLMS_VAR;
		return $vars;
	}

	/**
	 * Address of an article's Markdown copy.
	 *
	 * @param int|\WP_Post $post Article.
	 * @return string
	 */
	public static function markdown_url( $post ) {
		$url = (string) get_permalink( $post );
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( self::MD_VAR, '1', $url );
		}
		return untrailingslashit( $url ) . '.md';
	}

	/**
	 * Address of the Help Center's llms.txt.
	 *
	 * @return string
	 */
	public static function llms_txt_url() {
		return get_option( 'permalink_structure' ) ? trailingslashit( Urls::home() ) . 'llms.txt' : add_query_arg( self::LLMS_VAR, '1', Urls::home() );
	}

	/**
	 * No trailing-slash redirect for the .md and llms.txt addresses.
	 *
	 * @param string|false $url Redirect.
	 * @return string|false
	 */
	public static function no_canonical_redirect( $url ) {
		return get_query_var( self::MD_VAR ) || get_query_var( self::LLMS_VAR ) ? false : $url;
	}

	/**
	 * Announce the Markdown copy on article pages.
	 *
	 * @return void
	 */
	public static function alternate() {
		if ( is_singular( Post_Type::NAME ) && ! get_query_var( self::MD_VAR ) && Access::can_read( get_queried_object() ) && self::is_public( get_queried_object_id() ) ) {
			echo '<link rel="alternate" type="text/markdown" href="' . esc_url( self::markdown_url( get_queried_object() ) ) . '">' . "\n";
		}
	}

	/**
	 * In the site's llms.txt, help articles link to their Markdown copies.
	 *
	 * @param string $url     URL.
	 * @param int    $post_id Post id.
	 * @return string
	 */
	public static function llms_url( $url, $post_id ) {
		return Post_Type::NAME === get_post_type( (int) $post_id ) && self::is_public( (int) $post_id ) ? self::markdown_url( (int) $post_id ) : (string) $url;
	}

	/**
	 * Send the Markdown copy or llms.txt.
	 *
	 * @return void
	 */
	public static function serve() {
		if ( get_query_var( self::LLMS_VAR ) ) {
			self::send( self::llms_txt(), 'text/plain' );
		}
		if ( ! get_query_var( self::MD_VAR ) ) {
			return;
		}
		$post = is_singular( Post_Type::NAME ) ? get_queried_object() : null;
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return;
			// WordPress shows its 404 page.
		}
		if ( ! Access::can_read( $post ) ) {
			status_header( 403 );
			self::send( '# ' . wp_strip_all_tags( get_the_title( $post ) ) . "\n\n" . __( 'This article is for signed-in customers.', 'chatbot' ) . "\n", 'text/markdown', (string) get_permalink( $post ) );
		}
		self::send( self::markdown( $post ), 'text/markdown', (string) get_permalink( $post ) );
	}

	/**
	 * The Markdown copy of an article.
	 *
	 * @param \WP_Post $post Article.
	 * @return string
	 */
	public static function markdown( $post ) {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Blocks that read the current post render for this article.
		setup_postdata( $post );
		$html = (string) do_blocks( (string) $post->post_content );
		$html = (string) do_shortcode( shortcode_unautop( wpautop( $html ) ) );
		wp_reset_postdata();
		$out   = '# ' . wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . "\n\n";
		$lede  = has_excerpt( $post ) ? trim( wp_strip_all_tags( (string) $post->post_excerpt ) ) : '';
		$out  .= '' !== $lede ? '> ' . $lede . "\n\n" : '';
		$crumb = array();
		foreach ( array_slice( Data::breadcrumbs( $post ), 1, -1 ) as $c ) {
			$crumb[] = wp_strip_all_tags( html_entity_decode( (string) $c[0], ENT_QUOTES, 'UTF-8' ) );
		}
		$meta = array();
		/* translators: %s: link to the article. */
		$meta[] = sprintf( __( 'Source: %s', 'chatbot' ), (string) get_permalink( $post ) );
		if ( $crumb ) {
			/* translators: %s: category path. */
			$meta[] = sprintf( __( 'Section: %s', 'chatbot' ), implode( ' › ', $crumb ) );
		}
		/* translators: %s: date. */
		$meta[] = sprintf( __( 'Updated: %s', 'chatbot' ), get_post_modified_time( 'Y-m-d', true, $post ) );
		return $out . implode( "  \n", $meta ) . "\n\n" . Markdown::from_html( $html );
	}

	/**
	 * The Help Center's llms.txt.
	 *
	 * @return string
	 */
	public static function llms_txt() {
		$design  = Settings::design();
		$lines   = array( '# ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) . ' ' . __( 'Help Center', 'chatbot' ), '' );
		$lede    = '' !== $design['lede'] ? $design['lede'] : sprintf( /* translators: %s: site name. */ __( 'Help articles about %s. Each link is a Markdown copy of the article.', 'chatbot' ), get_bloginfo( 'name' ) );
		$lines[] = '> ' . wp_strip_all_tags( $lede );
		$lines[] = '';
		$listed  = array();
		$count   = 0;
		$add     = static function ( $title, array $posts ) use ( &$lines, &$listed, &$count ) {
			$items = array();
			foreach ( $posts as $post ) {
				if ( $count >= self::LLMS_MAX || isset( $listed[ $post->ID ] ) || ! self::is_public( $post->ID ) || '' !== (string) $post->post_password ) {
					continue;
				}
				$listed[ $post->ID ] = true;
				++$count;
				$about   = has_excerpt( $post ) ? wp_strip_all_tags( (string) $post->post_excerpt ) : '';
				$items[] = '- [' . str_replace( array( '[', ']' ), array( '(', ')' ), wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) ) . '](' . self::markdown_url( $post ) . ')' . ( '' !== $about ? ': ' . trim( (string) preg_replace( '/\s+/', ' ', $about ) ) : '' );
			}
			if ( $items ) {
				$lines[] = '## ' . wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
				$lines[] = '';
				$lines   = array_merge( $lines, $items, array( '' ) );
			}
		};
		$walk    = static function ( $parent_id, $prefix ) use ( &$walk, $add ) {
			foreach ( Data::categories( $parent_id ) as $term ) {
				$name = '' !== $prefix ? $prefix . ' › ' . $term->name : $term->name;
				$add( $name, Data::articles_in( $term->term_id, -1, false ) );
				$walk( $term->term_id, $name );
			}
		};
		$walk( 0, '' );
		$rest = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => self::LLMS_MAX,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		$add( $listed ? __( 'Other articles', 'chatbot' ) : __( 'Articles', 'chatbot' ), $rest );
		if ( \QuantumCloud\HelpCenter\Mcp\Docs_Server::enabled() ) {
			$lines[] = '## ' . __( 'For AI assistants', 'chatbot' );
			$lines[] = '';
			$lines[] = '- [' . __( 'Docs server (MCP, read-only, no key)', 'chatbot' ) . '](' . \QuantumCloud\HelpCenter\Mcp\Docs_Server::url() . '): ' . __( 'search and read these articles from Claude, ChatGPT or any MCP client.', 'chatbot' );
			$lines[] = '';
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * 301 from an earlier Help Center address to the current one, when the old URL finds nothing.
	 *
	 * @return void
	 */
	public static function redirect_old_address() {
		if ( ! is_404() ) {
			return;
		}
		$path   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$target = self::moved( $path, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), Settings::base(), (array) get_option( Settings_Page::PREVIOUS, array() ) );
		if ( '' === $target ) {
			return;
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		wp_safe_redirect( home_url( $target ) . ( '' !== $query ? '?' . $query : '' ), 301, 'WPBot Help Center' );
		exit;
	}

	/**
	 * Where a path under an earlier address lives now.
	 *
	 * @param string   $path     Request path.
	 * @param string   $home     Path of the site's home ("/" or "/sub/").
	 * @param string   $base     Current address.
	 * @param string[] $previous Earlier addresses.
	 * @return string Path relative to the home ("help/setup/x/"), or '' when it is not under an earlier address.
	 */
	public static function moved( $path, $home, $base, array $previous ) {
		$home = '/' . trim( (string) $home, '/' );
		$home = '/' === $home ? '/' : $home . '/';
		$path = '/' . ltrim( (string) $path, '/' );
		if ( 0 !== strpos( $path, $home ) ) {
			return '';
		}
		$rel = substr( $path, strlen( $home ) );
		// Longest earlier address first ("docs/help" before "docs").
		usort(
			$previous,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		foreach ( $previous as $old ) {
			$old = trim( (string) $old, '/' );
			if ( '' === $old || $old === $base ) {
				continue;
			}
			if ( $rel === $old || $rel === $old . '/' || 0 === strpos( $rel, $old . '/' ) ) {
				return $base . '/' . ltrim( (string) substr( $rel, strlen( $old ) ), '/' );
			}
		}
		return '';
	}

	/**
	 * Whether an article is public (no access labels other than "public").
	 *
	 * @param int $post_id Article id.
	 * @return bool
	 */
	public static function is_public( $post_id ) {
		return array() === array_diff( Access::labels( (int) $post_id ), array( 'public' ) );
	}

	/**
	 * Send text and stop.
	 *
	 * @param string $body      Body.
	 * @param string $type      Media type.
	 * @param string $canonical Canonical URL, or ''.
	 * @return void
	 */
	private static function send( $body, $type, $canonical = '' ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $type . '; charset=utf-8' );
			header( 'X-Robots-Tag: noindex, follow' );
			if ( '' !== $canonical ) {
				header( 'Link: <' . esc_url_raw( $canonical ) . '>; rel="canonical"' );
			}
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text or Markdown, not HTML.
		exit;
	}
}
