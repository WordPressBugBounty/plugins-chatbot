<?php
/**
 * What may be listed in llms.txt (round 2, R4). The file is a public index:
 * noindex is not access control, so a page is listed only when every rule
 * passes. It must be published, public, without a password, not noindexed
 * (WPBot, Yoast, Rank Math, AIOSEO), not blocked in robots.txt, canonical to
 * itself, of a listed post type, not an attachment, not a hidden or private
 * WooCommerce product, not locked by a membership plugin, and a real page
 * (not an empty placeholder). Anything that fails is left out, with the
 * reason recorded for the screen.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Llms;

use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Inclusion rules for llms.txt.
 */
final class Llms_Rules {

	/**
	 * Disallow rules for all agents from robots.txt (cached per request).
	 *
	 * @var string[]|null
	 */
	private static $disallow = null;

	/**
	 * Why a post may not be listed ('' when it may).
	 *
	 * @param \WP_Post|int $post Post.
	 * @return string
	 */
	public static function refusal( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return __( 'It no longer exists.', 'chatbot' );
		}
		if ( 'publish' !== $post->post_status ) {
			return __( 'Not published (draft, scheduled, pending or private).', 'chatbot' );
		}
		if ( '' !== (string) $post->post_password ) {
			return __( 'Protected by a password.', 'chatbot' );
		}
		$type = get_post_type_object( $post->post_type );
		if ( 'attachment' === $post->post_type || ! $type || ! $type->public ) {
			return __( 'Not a public page.', 'chatbot' );
		}
		if ( ! in_array( $post->post_type, self::types(), true ) ) {
			return __( 'This kind of content is not listed (Settings of this tab).', 'chatbot' );
		}
		$seo = Seo_Bridge::read( $post->ID );
		if ( ! empty( $seo['noindex'] ) ) {
			return __( 'Set to noindex.', 'chatbot' );
		}
		$canonical = trim( (string) $seo['canonical'] );
		if ( '' !== $canonical && untrailingslashit( $canonical ) !== untrailingslashit( (string) get_permalink( $post ) ) ) {
			return __( 'Its canonical address points to another page.', 'chatbot' );
		}
		if ( 'product' === $post->post_type && taxonomy_exists( 'product_visibility' ) && has_term( array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility', $post ) ) {
			return __( 'A hidden WooCommerce product.', 'chatbot' );
		}
		if ( self::gated( $post ) ) {
			return __( 'Locked to members: visitors cannot read it.', 'chatbot' );
		}
		if ( self::blocked( (string) get_permalink( $post ) ) ) {
			return __( 'Blocked in robots.txt.', 'chatbot' );
		}
		if ( self::placeholder( $post ) ) {
			return __( 'Almost empty (a placeholder, not a real page).', 'chatbot' );
		}
		/**
		 * Another reason to leave a page out (Pro: the daily link check found it broken or behind a login). '' keeps it.
		 *
		 * @param string   $why  Reason, '' to list it.
		 * @param \WP_Post $post Post.
		 */
		return (string) apply_filters( 'qcld_wpbot_seo/llms_txt_refusal', '', $post );
	}

	/**
	 * Post types listed (setting; only public ones).
	 *
	 * @return string[]
	 */
	public static function types() {
		return array_values( array_filter( (array) Settings::get( 'llms_types' ), 'post_type_exists' ) );
	}

	/**
	 * Locked by a membership plugin? Paid Memberships Pro is checked for
	 * required levels; others answer through the filter.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function gated( \WP_Post $post ) {
		$gated = false;
		if ( function_exists( 'pmpro_has_membership_access' ) ) {
			$access = pmpro_has_membership_access( $post->ID, null, true );
			$gated  = is_array( $access ) && ! empty( $access[1] );
		}
		/**
		 * Is this post locked to members or otherwise hidden from visitors? Membership plugins answer here.
		 *
		 * @param bool     $gated Default from the checks above.
		 * @param \WP_Post $post  Post.
		 */
		return (bool) apply_filters( 'qcld_wpbot_seo/llms_txt_gated', $gated, $post );
	}

	/**
	 * An empty placeholder: almost no text, and not the home page or a menu page.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function placeholder( \WP_Post $post ) {
		if ( (int) get_option( 'page_on_front' ) === (int) $post->ID || 'product' === $post->post_type ) {
			return false;
		}
		$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
		return $words < 30 && ! in_array( (int) $post->ID, \QuantumCloud\SeoAiSearch\Opportunities\Page_Importance::menu_posts(), true );
	}

	/**
	 * Is this URL blocked for all agents in robots.txt? Simple rules: path
	 * prefixes, "*" wildcards and "$" ends, for "User-agent: *"; an Allow
	 * that is longer than the matching Disallow wins.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function blocked( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '' === $path ? '/' : $path;
		$best = array( 0, 'allow' );
		foreach ( self::robots_rules() as $rule ) {
			list( $kind, $pattern ) = $rule;
			if ( '' === $pattern ) {
				continue;
			}
			$regex = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $pattern, '#' ) ) . '#';
			if ( preg_match( $regex, $path ) && strlen( $pattern ) >= $best[0] ) {
				$best = array( strlen( $pattern ), $kind );
			}
		}
		return 'disallow' === $best[1];
	}

	/**
	 * Allow and Disallow lines for "User-agent: *" (a real robots.txt file, else WordPress's virtual one).
	 *
	 * @return array[] [ allow|disallow, pattern ].
	 */
	public static function robots_rules() {
		if ( null !== self::$disallow ) {
			return self::$disallow;
		}
		$file = trailingslashit( ABSPATH ) . 'robots.txt';
		if ( is_readable( $file ) ) {
			$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		} else {
			$public = (string) get_option( 'blog_public' );
			$text   = "User-agent: *\n" . ( '0' === $public ? "Disallow: /\n" : "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" );
			$text   = (string) apply_filters( 'robots_txt', $text, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
		}
		self::$disallow = array();
		$applies        = false;
		$group_started  = false;
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
				continue;
			}
			$field = strtolower( $m[1] );
			$value = trim( $m[2] );
			if ( 'user-agent' === $field ) {
				// Consecutive User-agent lines form one group.
				$applies       = $group_started ? ( '*' === $value ) : ( $applies || '*' === $value );
				$group_started = false;
				continue;
			}
			if ( ! in_array( $field, array( 'allow', 'disallow' ), true ) ) {
				continue;
			}
			$group_started = true;
			if ( $applies ) {
				self::$disallow[] = array( $field, $value );
			}
		}
		return self::$disallow;
	}

	/**
	 * Forget cached robots rules.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$disallow = null;
	}
}
