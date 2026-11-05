<?php
/**
 * Page Audit (F2): checks for the page's target keyword, links to other
 * sites, and two unscored title tips. Pure logic over the page array, so it
 * is unit-tested.
 *
 * Each check returns null (passed), an issue (label, severity, message,
 * why, fix) or a non-issue with status "info" (no keyword to check) or
 * "tip" (never scored). Keyword repeats are only a warning, never a
 * density target.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword placement, external links, title tips.
 */
final class Keyword_Checks {

	/**
	 * Words that make titles more clickable (tips only, never scored).
	 */
	const POWER_WORDS = array( 'best', 'easy', 'simple', 'quick', 'proven', 'complete', 'ultimate', 'essential', 'free', 'new', 'guide', 'secret', 'step-by-step', 'expert', 'top', 'fast', 'how to', 'why', 'checklist', 'mistakes' );

	/**
	 * Check callbacks.
	 *
	 * @return array id => callable.
	 */
	public static function checks() {
		return array(
			'keyword_title_start' => array( __CLASS__, 'title_start' ),
			'keyword_description' => array( __CLASS__, 'description' ),
			'keyword_url'         => array( __CLASS__, 'url' ),
			'keyword_intro'       => array( __CLASS__, 'intro' ),
			'keyword_subheadings' => array( __CLASS__, 'subheadings' ),
			'keyword_repeats'     => array( __CLASS__, 'repeats' ),
			'external_links'      => array( __CLASS__, 'external_links' ),
			'tip_number'          => array( __CLASS__, 'tip_number' ),
			'tip_power_word'      => array( __CLASS__, 'tip_power_word' ),
		);
	}

	/**
	 * Keyword near the start of the search title.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function title_start( array $page ) {
		if ( '' === trim( (string) $page['main_query'] ) ) {
			return self::no_keyword( __( 'Keyword near the start of the title', 'chatbot' ) );
		}
		$title = self::lower( $page['title'] );
		$first = self::first_term( $page['main_query'] );
		$at    = '' === $first ? false : strpos( $title, $first );
		if ( false !== $at && $at <= max( 10, (int) floor( strlen( $title ) / 2 ) ) ) {
			return null;
		}
		return array(
			'label'    => __( 'Keyword near the start of the title', 'chatbot' ),
			'severity' => 'low',
			/* translators: %s: keyword. */
			'message'  => sprintf( __( 'Start the search title with "%s" (or put it in the first half).', 'chatbot' ), $page['main_query'] ),
			'why'      => __( 'People scan the first words of a result; the keyword there confirms it matches their search.', 'chatbot' ),
			'fix'      => 'meta_title',
		);
	}

	/**
	 * Keyword in the search description.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function description( array $page ) {
		if ( '' === trim( (string) $page['main_query'] ) ) {
			return self::no_keyword( __( 'Keyword in the description', 'chatbot' ) );
		}
		if ( '' !== trim( (string) $page['description'] ) && Checks::covers( (string) $page['description'], (string) $page['main_query'] ) ) {
			return null;
		}
		return array(
			'label'    => __( 'Keyword in the description', 'chatbot' ),
			'severity' => 'medium',
			/* translators: %s: keyword. */
			'message'  => sprintf( __( 'Mention "%s" in the search description.', 'chatbot' ), $page['main_query'] ),
			'why'      => __( 'Search engines show the words people searched in bold in the description.', 'chatbot' ),
			'fix'      => 'meta_description',
		);
	}

	/**
	 * Keyword in the page address.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function url( array $page ) {
		if ( '' === trim( (string) $page['main_query'] ) ) {
			return self::no_keyword( __( 'Keyword in the URL', 'chatbot' ) );
		}
		$slug = str_replace( array( '-', '_' ), ' ', self::lower( isset( $page['slug'] ) ? rawurldecode( (string) $page['slug'] ) : '' ) );
		if ( '' !== $slug && Checks::covers( $slug, (string) $page['main_query'] ) ) {
			return null;
		}
		return array(
			'label'    => __( 'Keyword in the URL', 'chatbot' ),
			'severity' => 'low',
			'message'  => __( 'The page address does not contain the keyword. Change it only on new pages, or add a redirect from the old address.', 'chatbot' ),
			'why'      => __( 'A short address with the keyword is clearer to people and search engines.', 'chatbot' ),
			'fix'      => 'manual',
		);
	}

	/**
	 * Keyword in the introduction (first paragraph or first 100 words).
	 *
	 * @param array  $page Page.
	 * @param string $text Plain text.
	 * @return array|null
	 */
	public static function intro( array $page, $text ) {
		if ( '' === trim( (string) $page['main_query'] ) ) {
			return self::no_keyword( __( 'Keyword in the introduction', 'chatbot' ) );
		}
		$first = '';
		if ( preg_match( '#<p\b[^>]*>(.*?)</p>#is', (string) $page['content'], $match ) ) {
			$first = Checks::text( $match[1] );
		}
		$opening = implode( ' ', array_slice( preg_split( '/\s+/u', trim( (string) $text ) ), 0, 100 ) );
		if ( Checks::covers( $first . ' ' . $opening, (string) $page['main_query'] ) ) {
			return null;
		}
		return array(
			'label'    => __( 'Keyword in the introduction', 'chatbot' ),
			'severity' => 'medium',
			/* translators: %s: keyword. */
			'message'  => sprintf( __( 'Use "%s" in the first paragraph.', 'chatbot' ), $page['main_query'] ),
			'why'      => __( 'The opening tells readers, search engines and AI assistants straight away what the page answers.', 'chatbot' ),
			'fix'      => 'intro',
		);
	}

	/**
	 * Keyword in at least one subheading (H2 or H3).
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function subheadings( array $page ) {
		if ( '' === trim( (string) $page['main_query'] ) ) {
			return self::no_keyword( __( 'Keyword in subheadings', 'chatbot' ) );
		}
		preg_match_all( '#<h[23]\b[^>]*>(.*?)</h[23]>#is', (string) $page['content'], $found );
		if ( array() === $found[1] ) {
			return array(
				'label'    => __( 'Keyword in subheadings', 'chatbot' ),
				'severity' => 'low',
				'message'  => __( 'The page has no H2 or H3 subheadings.', 'chatbot' ),
				'why'      => __( 'Subheadings help people scan and help search engines understand the parts of the page.', 'chatbot' ),
				'fix'      => 'content',
			);
		}
		foreach ( $found[1] as $heading ) {
			if ( Checks::covers( Checks::text( $heading ), (string) $page['main_query'] ) ) {
				return null;
			}
		}
		return array(
			'label'    => __( 'Keyword in subheadings', 'chatbot' ),
			'severity' => 'low',
			/* translators: %s: keyword. */
			'message'  => sprintf( __( 'Use "%s" (or close words) in at least one H2 or H3.', 'chatbot' ), $page['main_query'] ),
			'why'      => __( 'A subheading with the keyword shows which part of the page answers the search.', 'chatbot' ),
			'fix'      => 'content',
		);
	}

	/**
	 * Keyword repeated too often (a warning only; there is no target density).
	 *
	 * @param array  $page  Page.
	 * @param string $text  Plain text.
	 * @param int    $words Word count.
	 * @return array|null
	 */
	public static function repeats( array $page, $text, $words ) {
		$query = self::lower( trim( (string) $page['main_query'] ) );
		if ( '' === $query || $words < 100 ) {
			return null;
		}
		$count = substr_count( self::lower( (string) $text ), $query );
		$per   = $count / max( 1, $words ) * 100;
		if ( $count < 6 || $per <= 3 ) {
			return null;
		}
		return array(
			'label'    => __( 'Keyword repeats', 'chatbot' ),
			'severity' => 'low',
			'status'   => 'warning',
			/* translators: 1: keyword, 2: times, 3: words. */
			'message'  => sprintf( __( '"%1$s" appears %2$d times in %3$d words. Use natural variations instead of repeating it.', 'chatbot' ), $page['main_query'], $count, $words ),
			'why'      => __( 'Search engines do not reward repetition; text that repeats a phrase reads poorly and can look like stuffing.', 'chatbot' ),
			'fix'      => 'content',
		);
	}

	/**
	 * At least one link to another site on longer pages.
	 *
	 * @param array  $page  Page.
	 * @param string $text  Plain text.
	 * @param int    $words Word count.
	 * @return array|null
	 */
	public static function external_links( array $page, $text, $words ) {
		if ( $words < 300 ) {
			return null;
		}
		$host = isset( $page['host'] ) ? strtolower( (string) $page['host'] ) : '';
		preg_match_all( '#<a\b[^>]*href\s*=\s*("|\')(https?://[^"\']+)\1#i', (string) $page['content'], $links );
		foreach ( $links[2] as $href ) {
			$to = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
			if ( '' !== $to && $to !== $host && 'www.' . $host !== $to && 'www.' . $to !== $host ) {
				return null;
			}
		}
		return array(
			'label'    => __( 'Links to other sites', 'chatbot' ),
			'severity' => 'low',
			'status'   => 'warning',
			'message'  => __( 'The page has no link to another website.', 'chatbot' ),
			'why'      => __( 'Linking to a trustworthy source where it helps the reader supports what the page says.', 'chatbot' ),
			'fix'      => 'manual',
		);
	}

	/**
	 * Tip: a number in the title (never scored).
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function tip_number( array $page ) {
		if ( preg_match( '/\d/', (string) $page['title'] ) ) {
			return null;
		}
		return array(
			'label'   => __( 'Number in the title', 'chatbot' ),
			'status'  => 'tip',
			'message' => __( 'Optional: for lists, guides or yearly updates, a number in the title can earn more clicks.', 'chatbot' ),
			'fix'     => 'meta_title',
		);
	}

	/**
	 * Tip: an engaging word in the title (never scored).
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function tip_power_word( array $page ) {
		$title = self::lower( (string) $page['title'] );
		foreach ( self::POWER_WORDS as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/u', $title ) ) {
				return null;
			}
		}
		return array(
			'label'   => __( 'Engaging word in the title', 'chatbot' ),
			'status'  => 'tip',
			'message' => __( 'Optional: a word such as "guide", "easy" or "complete" can make the title more inviting, if it is true for the page.', 'chatbot' ),
			'fix'     => 'meta_title',
		);
	}

	/**
	 * Not checked: the page has no target keyword yet.
	 *
	 * @param string $label Check label.
	 * @return array
	 */
	private static function no_keyword( $label ) {
		return array(
			'label'   => $label,
			'status'  => 'info',
			'message' => __( 'Add a target keyword to check this.', 'chatbot' ),
			'fix'     => 'keyword',
		);
	}

	/**
	 * First meaningful word of the keyword.
	 *
	 * @param string $query Keyword.
	 * @return string
	 */
	private static function first_term( $query ) {
		foreach ( preg_split( '/\s+/u', self::lower( trim( (string) $query ) ) ) as $term ) {
			if ( strlen( $term ) > 2 ) {
				return rtrim( $term, 's' );
			}
		}
		return '';
	}

	/**
	 * Lower case.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
	}
}
