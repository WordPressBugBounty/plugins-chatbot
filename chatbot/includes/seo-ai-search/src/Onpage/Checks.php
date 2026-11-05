<?php
/**
 * On-page checks for one page (no AI, no outside calls). Pure logic over a
 * page array, so it is unit-tested; Audit gathers the page data.
 *
 * Page array: title (SEO title or post title), seo_title (as set), description,
 * content (HTML), main_query, inbound (internal links pointing here),
 * duplicate_title, duplicate_description (other pages with the same text),
 * long_form_words (threshold for FAQ/summary advice).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the built-in checks and the ones extensions add.
 */
final class Checks {

	/**
	 * Score weight by severity.
	 */
	const WEIGHTS = array(
		'high'   => 20,
		'medium' => 10,
		'low'    => 4,
	);

	/**
	 * Run all checks.
	 *
	 * @param array $page Page data.
	 * @return array score (0–100), issues (id, label, severity, message, why, fix), words.
	 */
	public static function run( array $page ) {
		$page   = array_merge(
			array(
				'title'                 => '',
				'seo_title'             => '',
				'description'           => '',
				'content'               => '',
				'main_query'            => '',
				'inbound'               => -1,
				'duplicate_title'       => 0,
				'duplicate_description' => 0,
			),
			$page
		);
		$text   = self::text( $page['content'] );
		$words  = self::words( $text );
		$issues = array();
		$list   = array();
		foreach ( self::checks() as $id => $check ) {
			$found = call_user_func( $check, $page, $text, $words );
			if ( is_array( $found ) && ! empty( $found['message'] ) ) {
				$found  = array_merge(
					array(
						'id'       => $id,
						'severity' => 'medium',
						'why'      => '',
						'fix'      => 'content',
					),
					$found
				);
				$status = isset( $found['status'] ) ? (string) $found['status'] : ( 'low' === $found['severity'] ? 'warning' : 'fail' );
				$list[] = self::entry( $id, $status, $found );
				// Tips and "add a keyword" notes are never scored.
				if ( ! in_array( $status, array( 'tip', 'info' ), true ) ) {
					$issues[] = $found;
				}
			} else {
				$list[] = self::entry( $id, 'pass', array() );
			}
		}
		/**
		 * Issues after the built-in checks (the Pro module and other
		 * modules add their own through qcld_wpbot_seo/issue_checks).
		 *
		 * @param array $issues Issues.
		 * @param array $page   Page data.
		 */
		$issues = (array) apply_filters( 'qcld_wpbot_seo/page_issues', $issues, $page );
		$score  = 100;
		foreach ( $issues as $issue ) {
			$score -= isset( self::WEIGHTS[ $issue['severity'] ] ) ? self::WEIGHTS[ $issue['severity'] ] : 5;
		}
		usort(
			$issues,
			static function ( $a, $b ) {
				$order = array(
					'high'   => 0,
					'medium' => 1,
					'low'    => 2,
				);
				return ( isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 3 ) <=> ( isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 3 );
			}
		);
		return array(
			'score'  => max( 0, $score ),
			'issues' => $issues,
			'words'  => $words,
			'checks' => $list,
		);
	}

	/**
	 * Group of a check on the Page Audit checklist.
	 *
	 * @param string $id Check id.
	 * @return string search, keyword, content, links, images or tips.
	 */
	public static function group( $id ) {
		$groups = array(
			'title_length'          => 'search',
			'description_length'    => 'search',
			'duplicate_title'       => 'search',
			'duplicate_description' => 'search',
			'title_keyword'         => 'keyword',
			'keyword_title_start'   => 'keyword',
			'keyword_description'   => 'keyword',
			'keyword_url'           => 'keyword',
			'keyword_intro'         => 'keyword',
			'keyword_subheadings'   => 'keyword',
			'keyword_repeats'       => 'keyword',
			'thin_content'          => 'content',
			'headings'              => 'content',
			'readability'           => 'content',
			'faq_summary'           => 'content',
			'links_out'             => 'links',
			'links_in'              => 'links',
			'external_links'        => 'links',
			'image_alt'             => 'images',
			'tip_number'            => 'tips',
			'tip_power_word'        => 'tips',
		);
		return isset( $groups[ $id ] ) ? $groups[ $id ] : 'content';
	}

	/**
	 * Labels of checks that passed (an issue carries its own label).
	 *
	 * @param string $id Check id.
	 * @return string
	 */
	public static function label( $id ) {
		$labels = array(
			'title_length'          => __( 'Search title length', 'chatbot' ),
			'title_keyword'         => __( 'Main search in the title', 'chatbot' ),
			'description_length'    => __( 'Search description', 'chatbot' ),
			'duplicate_title'       => __( 'Unique title', 'chatbot' ),
			'duplicate_description' => __( 'Unique description', 'chatbot' ),
			'thin_content'          => __( 'Content length', 'chatbot' ),
			'headings'              => __( 'Headings', 'chatbot' ),
			'image_alt'             => __( 'Image alt text', 'chatbot' ),
			'links_out'             => __( 'Links to your other pages', 'chatbot' ),
			'links_in'              => __( 'Links from your other pages', 'chatbot' ),
			'faq_summary'           => __( 'FAQ or summary', 'chatbot' ),
			'readability'           => __( 'Readability', 'chatbot' ),
			'keyword_title_start'   => __( 'Keyword near the start of the title', 'chatbot' ),
			'keyword_description'   => __( 'Keyword in the description', 'chatbot' ),
			'keyword_url'           => __( 'Keyword in the URL', 'chatbot' ),
			'keyword_intro'         => __( 'Keyword in the introduction', 'chatbot' ),
			'keyword_subheadings'   => __( 'Keyword in subheadings', 'chatbot' ),
			'keyword_repeats'       => __( 'Keyword repeats', 'chatbot' ),
			'external_links'        => __( 'Links to other sites', 'chatbot' ),
			'tip_number'            => __( 'Number in the title', 'chatbot' ),
			'tip_power_word'        => __( 'Engaging word in the title', 'chatbot' ),
		);
		return isset( $labels[ $id ] ) ? $labels[ $id ] : ucfirst( str_replace( '_', ' ', $id ) );
	}

	/**
	 * One checklist entry.
	 *
	 * @param string $id     Check id.
	 * @param string $status pass, warning, fail, tip or info.
	 * @param array  $found  Issue fields (empty when passed).
	 * @return array id, group, label, status, message, why, fix, severity.
	 */
	private static function entry( $id, $status, array $found ) {
		return array(
			'id'       => $id,
			'group'    => self::group( $id ),
			'label'    => ! empty( $found['label'] ) ? (string) $found['label'] : self::label( $id ),
			'status'   => $status,
			'message'  => isset( $found['message'] ) ? (string) $found['message'] : '',
			'why'      => isset( $found['why'] ) ? (string) $found['why'] : '',
			'fix'      => isset( $found['fix'] ) ? (string) $found['fix'] : '',
			'severity' => isset( $found['severity'] ) && ! in_array( $status, array( 'tip', 'info', 'pass' ), true ) ? (string) $found['severity'] : '',
		);
	}

	/**
	 * Check callbacks: id => callable( $page, $text, $words ): array|null.
	 *
	 * @return array
	 */
	public static function checks() {
		$checks = array(
			'title_length'          => array( __CLASS__, 'title_length' ),
			'title_keyword'         => array( __CLASS__, 'title_keyword' ),
			'description_length'    => array( __CLASS__, 'description_length' ),
			'duplicate_title'       => array( __CLASS__, 'duplicate_title' ),
			'duplicate_description' => array( __CLASS__, 'duplicate_description' ),
			'thin_content'          => array( __CLASS__, 'thin_content' ),
			'headings'              => array( __CLASS__, 'headings' ),
			'image_alt'             => array( __CLASS__, 'image_alt' ),
			'links_out'             => array( __CLASS__, 'links_out' ),
			'links_in'              => array( __CLASS__, 'links_in' ),
			'faq_summary'           => array( __CLASS__, 'faq_summary' ),
			'readability'           => array( __CLASS__, 'readability' ),
		);
		// Page Audit (F2): keyword placement, links to other sites, unscored title tips.
		$checks = array_merge( $checks, Keyword_Checks::checks() );
		/**
		 * On-page and technical checks. Each: id => callable( array $page,
		 * string $text, int $words ): array|null with label, severity,
		 * message, why, fix.
		 *
		 * @param array $checks Checks.
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/issue_checks', $checks );
	}

	/**
	 * Search title length (30–60 characters).
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function title_length( array $page ) {
		$length = self::len( $page['title'] );
		if ( $length >= 30 && $length <= 60 ) {
			return null;
		}
		return array(
			'label'    => __( 'Search title length', 'chatbot' ),
			'severity' => 0 === $length ? 'high' : 'medium',
			/* translators: %d: characters. */
			'message'  => 0 === $length ? __( 'The page has no search title.', 'chatbot' ) : sprintf( __( 'The search title has %d characters; 30 to 60 shows best in Google.', 'chatbot' ), $length ),
			'why'      => __( 'Google shows about 60 characters; a clear title of that length gets more clicks.', 'chatbot' ),
			'fix'      => 'meta_title',
		);
	}

	/**
	 * Main search in the title.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function title_keyword( array $page ) {
		if ( '' === trim( $page['main_query'] ) || self::len( $page['title'] ) === 0 || self::covers( $page['title'], $page['main_query'] ) ) {
			return null;
		}
		return array(
			'label'    => __( 'Main search in the title', 'chatbot' ),
			'severity' => 'medium',
			/* translators: %s: search. */
			'message'  => sprintf( __( 'Most of your visitors search "%s", but the title does not say it.', 'chatbot' ), $page['main_query'] ),
			'why'      => __( 'Searchers click titles that match what they typed.', 'chatbot' ),
			'fix'      => 'meta_title',
		);
	}

	/**
	 * Description length (70–160 characters).
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function description_length( array $page ) {
		$length = self::len( $page['description'] );
		if ( $length >= 70 && $length <= 160 ) {
			return null;
		}
		return array(
			'label'    => __( 'Search description', 'chatbot' ),
			'severity' => 0 === $length ? 'high' : 'low',
			/* translators: %d: characters. */
			'message'  => 0 === $length ? __( 'The page has no search description, so Google picks any text from the page.', 'chatbot' ) : sprintf( __( 'The search description has %d characters; 70 to 160 works best.', 'chatbot' ), $length ),
			'why'      => __( 'A description that answers the search makes people click your result.', 'chatbot' ),
			'fix'      => 'meta_description',
		);
	}

	/**
	 * Same title as other pages.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function duplicate_title( array $page ) {
		if ( (int) $page['duplicate_title'] < 1 ) {
			return null;
		}
		return array(
			'label'    => __( 'Duplicate title', 'chatbot' ),
			'severity' => 'medium',
			/* translators: %d: number of pages. */
			'message'  => sprintf( _n( '%d other page has the same search title.', '%d other pages have the same search title.', (int) $page['duplicate_title'], 'chatbot' ), (int) $page['duplicate_title'] ),
			'why'      => __( 'Pages with the same title compete with each other in search.', 'chatbot' ),
			'fix'      => 'meta_title',
		);
	}

	/**
	 * Same description as other pages.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function duplicate_description( array $page ) {
		if ( (int) $page['duplicate_description'] < 1 ) {
			return null;
		}
		return array(
			'label'    => __( 'Duplicate description', 'chatbot' ),
			'severity' => 'low',
			/* translators: %d: number of pages. */
			'message'  => sprintf( _n( '%d other page has the same search description.', '%d other pages have the same search description.', (int) $page['duplicate_description'], 'chatbot' ), (int) $page['duplicate_description'] ),
			'why'      => __( 'Each page should say what makes it different.', 'chatbot' ),
			'fix'      => 'meta_description',
		);
	}

	/**
	 * Thin content (under 300 words).
	 *
	 * @param array  $page  Page.
	 * @param string $text  Text.
	 * @param int    $words Words.
	 * @return array|null
	 */
	public static function thin_content( array $page, $text, $words ) {
		unset( $page, $text );
		if ( $words >= 300 ) {
			return null;
		}
		return array(
			'label'    => __( 'Thin content', 'chatbot' ),
			'severity' => $words < 100 ? 'high' : 'medium',
			/* translators: %d: words. */
			'message'  => sprintf( __( 'The page has %d words; it may not answer the search fully.', 'chatbot' ), $words ),
			'why'      => __( 'Pages that answer the question completely rank and convert better.', 'chatbot' ),
			'fix'      => 'content',
		);
	}

	/**
	 * Heading structure.
	 *
	 * @param array  $page  Page.
	 * @param string $text  Text.
	 * @param int    $words Words.
	 * @return array|null
	 */
	public static function headings( array $page, $text, $words ) {
		unset( $text );
		preg_match_all( '#<h([1-6])\b#i', $page['content'], $found );
		$levels = array_map( 'intval', $found[1] );
		$issue  = '';
		if ( in_array( 1, $levels, true ) ) {
			$issue = __( 'The content has its own H1 heading; the page title is already the H1.', 'chatbot' );
		} elseif ( $words > 600 && ! in_array( 2, $levels, true ) ) {
			$issue = __( 'A long page with no H2 subheadings is hard to scan.', 'chatbot' );
		} else {
			$previous = 1;
			foreach ( $levels as $level ) {
				if ( $level > $previous + 1 ) {
					/* translators: 1: heading level, 2: previous level. */
					$issue = sprintf( __( 'An H%1$d follows an H%2$d; a level is skipped.', 'chatbot' ), $level, $previous );
					break;
				}
				$previous = $level;
			}
		}
		if ( '' === $issue ) {
			return null;
		}
		return array(
			'label'    => __( 'Headings', 'chatbot' ),
			'severity' => 'low',
			'message'  => $issue,
			'why'      => __( 'Clear headings help readers, Google and AI assistants find the answer.', 'chatbot' ),
			'fix'      => 'content',
		);
	}

	/**
	 * Images without alt text.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function image_alt( array $page ) {
		preg_match_all( '#<img\b[^>]*>#i', $page['content'], $images );
		$missing = 0;
		foreach ( $images[0] as $tag ) {
			if ( ! preg_match( '#\balt\s*=\s*("|\')\s*[^"\'\s]#i', $tag ) ) {
				++$missing;
			}
		}
		if ( 0 === $missing ) {
			return null;
		}
		return array(
			'label'    => __( 'Image alt text', 'chatbot' ),
			'severity' => 'low',
			/* translators: %d: images. */
			'message'  => sprintf( _n( '%d image has no alt text.', '%d images have no alt text.', $missing, 'chatbot' ), $missing ),
			'why'      => __( 'Alt text helps people using screen readers and image search.', 'chatbot' ),
			'fix'      => 'alt',
		);
	}

	/**
	 * Internal links out of the page.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function links_out( array $page ) {
		$count = 0;
		$host  = isset( $page['host'] ) ? (string) $page['host'] : '';
		preg_match_all( '#<a\b[^>]*href\s*=\s*("|\')([^"\']+)\1#i', $page['content'], $links );
		foreach ( $links[2] as $href ) {
			if ( ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) || ( '' !== $host && false !== stripos( $href, '//' . $host ) ) ) {
				++$count;
			}
		}
		if ( $count >= 2 ) {
			return null;
		}
		return array(
			'label'    => __( 'Links to your other pages', 'chatbot' ),
			'severity' => 'low',
			/* translators: %d: links. */
			'message'  => sprintf( _n( 'The page links to %d other page on your site.', 'The page links to %d other pages on your site.', $count, 'chatbot' ), $count ),
			'why'      => __( 'Links to related pages keep readers on your site and help Google understand it.', 'chatbot' ),
			'fix'      => 'links',
		);
	}

	/**
	 * Internal links pointing to the page.
	 *
	 * @param array $page Page.
	 * @return array|null
	 */
	public static function links_in( array $page ) {
		if ( (int) $page['inbound'] < 0 || (int) $page['inbound'] > 0 ) {
			return null;
		}
		return array(
			'label'    => __( 'Links from your other pages', 'chatbot' ),
			'severity' => 'medium',
			'message'  => __( 'No other page on your site links here (an orphan page).', 'chatbot' ),
			'why'      => __( 'Pages with no internal links are found and ranked less.', 'chatbot' ),
			'fix'      => 'links',
		);
	}

	/**
	 * Long pages: FAQ or a short summary near the top.
	 *
	 * @param array  $page  Page.
	 * @param string $text  Text.
	 * @param int    $words Words.
	 * @return array|null
	 */
	public static function faq_summary( array $page, $text, $words ) {
		unset( $text );
		if ( $words < 800 ) {
			return null;
		}
		$content = $page['content'];
		$has_faq = (bool) preg_match( '#(wp:yoast/faq-block|wp:rank-math/faq|<details|faq|frequently asked)#i', $content );
		$top     = substr( $content, 0, 1500 );
		$summary = (bool) preg_match( '#(<ul|<ol|summary|in short|key takeaways|tl;dr)#i', $top );
		if ( $has_faq || $summary ) {
			return null;
		}
		return array(
			'label'    => __( 'FAQ or summary', 'chatbot' ),
			'severity' => 'low',
			'message'  => __( 'A long page with no FAQ and no short summary near the top.', 'chatbot' ),
			'why'      => __( 'Short answers and FAQs are what Google and AI assistants quote.', 'chatbot' ),
			'fix'      => 'faq',
		);
	}

	/**
	 * Readability: long sentences.
	 *
	 * @param array  $page  Page.
	 * @param string $text  Text.
	 * @param int    $words Words.
	 * @return array|null
	 */
	public static function readability( array $page, $text, $words ) {
		unset( $page );
		$sentences = preg_split( '/(?<=[.!?])\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$count     = max( 1, count( $sentences ) );
		$average   = $words / $count;
		if ( $words < 150 || $average <= 25 ) {
			return null;
		}
		return array(
			'label'    => __( 'Readability', 'chatbot' ),
			'severity' => 'low',
			/* translators: %d: words per sentence. */
			'message'  => sprintf( __( 'Sentences average %d words; under 20 is easier to read.', 'chatbot' ), (int) round( $average ) ),
			'why'      => __( 'Plain, short sentences keep visitors reading.', 'chatbot' ),
			'fix'      => 'content',
		);
	}

	/**
	 * Plain text of HTML content.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function text( $html ) {
		$html = preg_replace( '#<!--.*?-->#s', ' ', (string) $html );
		$html = preg_replace( '#<(script|style)\b.*?</\1>#is', ' ', $html );
		$text = html_entity_decode( wp_strip_all_tags( str_replace( '<', ' <', $html ) ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Word count (Unicode letters and digits).
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function words( $text ) {
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', (string) $text );
	}

	/**
	 * Whether a text contains most words of a search (in any order).
	 *
	 * @param string $text  Text.
	 * @param string $query Search.
	 * @return bool
	 */
	public static function covers( $text, $query ) {
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$terms = preg_split( '/\s+/u', function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $query ) ) : strtolower( trim( $query ) ) );
		$terms = array_filter(
			(array) $terms,
			static function ( $term ) {
				return ( function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term ) ) > 2;
			}
		);
		if ( array() === $terms ) {
			return true;
		}
		$hits = 0;
		foreach ( $terms as $term ) {
			if ( false !== strpos( $text, rtrim( $term, 's' ) ) ) {
				++$hits;
			}
		}
		return $hits / count( $terms ) >= 0.6;
	}

	/**
	 * Character length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function len( $text ) {
		$text = trim( (string) $text );
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
