<?php
/**
 * Help Center search (HC1-08): words or their synonyms (WPBot's site search
 * synonyms, through qcld_wpbot_chat/search_terms) in the title, excerpt,
 * content or alternative questions; title matches first; typo tolerance when
 * nothing matches; searches that still find nothing become knowledge gaps.
 *
 * Applies to the results page and to the live suggestions (REST), not to other
 * searches on the site.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Search.
 */
final class Search {

	/**
	 * Most words used from a search.
	 */
	const MAX_WORDS = 8;

	/**
	 * Vocabulary cache (transient).
	 */
	const VOCABULARY = 'qcld_wpbot_help_center_vocabulary';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'posts_search', array( self::class, 'where' ), 10, 2 );
		add_filter( 'posts_search_orderby', array( self::class, 'orderby' ), 10, 2 );
		add_action( 'save_post_' . Post_Type::NAME, array( self::class, 'forget_vocabulary' ) );
	}

	/**
	 * Whether a query is a Help Center search.
	 *
	 * @param \WP_Query $query Query.
	 * @return bool
	 */
	public static function applies( $query ) {
		return $query instanceof \WP_Query && '' !== trim( (string) $query->get( 's' ) ) && Post_Type::NAME === $query->get( 'post_type' );
	}

	/**
	 * Words of a search (at least 2 characters, at most MAX_WORDS).
	 *
	 * @param string $search Search.
	 * @return string[]
	 */
	public static function words( $search ) {
		$words = preg_split( '/[\s,.;:!?"()]+/u', function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $search, 'UTF-8' ) : strtolower( (string) $search ) );
		$words = array_values(
			array_unique(
				array_filter(
					(array) $words,
					static function ( $w ) {
						return ( function_exists( 'mb_strlen' ) ? mb_strlen( $w, 'UTF-8' ) : strlen( $w ) ) >= 2;
					}
				)
			)
		);
		return array_slice( $words, 0, self::MAX_WORDS );
	}

	/**
	 * A word and its synonyms.
	 *
	 * @param string $word   Word.
	 * @param string $search Whole search.
	 * @return string[]
	 */
	public static function alternatives( $word, $search ) {
		/** This filter is documented by WPBot's site search (synonyms). */
		$terms = (array) apply_filters( 'qcld_wpbot_chat/search_terms', array( $word ), $search ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The chatbot module's filter.
		$terms = array_values( array_unique( array_filter( array_map( 'strval', $terms ), 'strlen' ) ) );
		return array() === $terms ? array( $word ) : array_slice( $terms, 0, 6 );
	}

	/**
	 * The WHERE clause: every word (or a synonym) in the title, excerpt, content or alternative questions.
	 *
	 * @param string    $search WordPress's search SQL.
	 * @param \WP_Query $query  Query.
	 * @return string
	 */
	public static function where( $search, $query ) {
		if ( ! self::applies( $query ) ) {
			return $search;
		}
		global $wpdb;
		$text   = (string) $query->get( 's' );
		$groups = array();
		foreach ( self::words( $text ) as $word ) {
			$any = array();
			foreach ( self::alternatives( $word, $text ) as $term ) {
				$like  = '%' . $wpdb->esc_like( $term ) . '%';
				$any[] = $wpdb->prepare(
					"({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} hcm WHERE hcm.post_id = {$wpdb->posts}.ID AND hcm.meta_key = '_wpbot_alt_questions' AND hcm.meta_value LIKE %s))",
					$like,
					$like,
					$like,
					$like
				);
			}
			$groups[] = '(' . implode( ' OR ', $any ) . ')';
		}
		return array() === $groups ? $search : ' AND (' . implode( ' AND ', $groups ) . ') ';
	}

	/**
	 * Order: the whole search in the title, then a word in the title, then the rest.
	 *
	 * @param string    $orderby Order SQL.
	 * @param \WP_Query $query   Query.
	 * @return string
	 */
	public static function orderby( $orderby, $query ) {
		if ( ! self::applies( $query ) ) {
			return $orderby;
		}
		global $wpdb;
		$text  = (string) $query->get( 's' );
		$words = self::words( $text );
		$cases = array( $wpdb->prepare( "WHEN {$wpdb->posts}.post_title LIKE %s THEN 0", '%' . $wpdb->esc_like( $text ) . '%' ) );
		foreach ( $words as $word ) {
			$cases[] = $wpdb->prepare( "WHEN {$wpdb->posts}.post_title LIKE %s THEN 1", '%' . $wpdb->esc_like( $word ) . '%' );
		}
		return '(CASE ' . implode( ' ', $cases ) . " ELSE 2 END) ASC, {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC";
	}

	/**
	 * The search with each word corrected to the closest known word, or '' when nothing changes.
	 *
	 * @param string   $search     Search.
	 * @param string[] $vocabulary Known words (default: from article titles and alternative questions).
	 * @return string
	 */
	public static function correct( $search, $vocabulary = null ) {
		$vocabulary = is_array( $vocabulary ) ? $vocabulary : self::vocabulary();
		if ( array() === $vocabulary ) {
			return '';
		}
		$changed = false;
		$out     = array();
		foreach ( self::words( $search ) as $word ) {
			$best = $word;
			if ( ! in_array( $word, $vocabulary, true ) && strlen( $word ) >= 4 ) {
				$limit = strlen( $word ) >= 7 ? 2 : 1;
				$score = $limit + 1;
				foreach ( $vocabulary as $known ) {
					if ( abs( strlen( $known ) - strlen( $word ) ) > $limit ) {
						continue;
					}
					$distance = levenshtein( $word, $known );
					if ( $distance < $score ) {
						$score = $distance;
						$best  = $known;
					}
				}
			}
			$changed = $changed || $best !== $word;
			$out[]   = $best;
		}
		return $changed ? implode( ' ', $out ) : '';
	}

	/**
	 * Words from published article titles and alternative questions (cached).
	 *
	 * @return string[]
	 */
	public static function vocabulary() {
		$cached = get_transient( Languages::key( self::VOCABULARY ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$words = array();
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Titles only, cached.
				'fields'         => 'ids',
			)
		);
		foreach ( (array) $posts as $id ) {
			$text = get_the_title( $id );
			$alt  = get_post_meta( $id, '_wpbot_alt_questions', true );
			if ( is_array( $alt ) ) {
				$text .= ' ' . implode( ' ', array_map( 'strval', $alt ) );
			}
			foreach ( self::words( $text ) as $w ) {
				$words[ $w ] = true;
			}
		}
		$words = array_keys( $words );
		set_transient( Languages::key( self::VOCABULARY ), $words, DAY_IN_SECONDS );
		return $words;
	}

	/**
	 * Forget the cached vocabulary when an article changes.
	 *
	 * @return void
	 */
	public static function forget_vocabulary() {
		Languages::forget( self::VOCABULARY );
	}

	/**
	 * Results for the results page: the main query's, or, when it found nothing, those of the corrected search.
	 * A search that still finds nothing becomes a knowledge gap (first page only).
	 *
	 * @param \WP_Query $query Main query.
	 * @return array{posts:\WP_Post[],total:int,corrected:string}
	 */
	public static function results( $query ) {
		$text  = (string) $query->get( 's' );
		$posts = (array) $query->posts;
		$total = (int) $query->found_posts;
		$fixed = '';
		if ( 0 === $total ) {
			$fixed = self::correct( $text );
			if ( '' !== $fixed ) {
				$retry = new \WP_Query(
					array(
						'post_type'      => Post_Type::NAME,
						'post_status'    => 'publish',
						's'              => $fixed,
						'posts_per_page' => 20,
					)
				);
				$posts = (array) $retry->posts;
				$total = (int) $retry->found_posts;
			}
		}
		if ( (int) $query->get( 'paged' ) <= 1 ) {
			/**
			 * A search on the Help Center results page (analytics listen here).
			 *
			 * @param string $text    Search.
			 * @param int    $results Number of results (after typo correction).
			 */
			do_action( 'qcld_wpbot_help_center/searched', $text, $total );
		}
		if ( 0 === $total && (int) $query->get( 'paged' ) <= 1 ) {
			/**
			 * A docs search found nothing: it joins the chatbot's knowledge gaps (Train).
			 *
			 * @param string $text   Search.
			 * @param string $reason Reason (docs_search; saved as no_answer until qcld/wpbot-chatbot#29 adds it).
			 * @param mixed  $extra  Unused.
			 */
			do_action( 'qcld_wpbot_chat/knowledge_gap', $text, 'docs_search', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The chatbot module's action.
		}
		return array(
			'posts'     => $posts,
			'total'     => $total,
			'corrected' => 0 === (int) $query->found_posts && $total > 0 ? $fixed : '',
		);
	}
}
