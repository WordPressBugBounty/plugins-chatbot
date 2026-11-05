<?php
/**
 * AI search readiness per page: is the page easy for AI assistants and
 * Google's answers to quote? Pure logic over page data (unit-tested).
 *
 * Items: answer in the first lines, a short summary, FAQ where useful,
 * structured data, clear entity names, author and date, AI crawler access.
 * Each failed item has a fix (AI Writer for content; schema and crawler
 * fixes in this module).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Readiness;

use QuantumCloud\SeoAiSearch\Onpage\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Readiness checklist.
 */
final class Readiness_Checks {

	/**
	 * Checklist for one page.
	 *
	 * @param array $page content (HTML), title, main_query, is_post, has_author, has_date, schema (bool: printed for this page), business (name), place (city), crawlers_blocked (ids).
	 * @return array score (0–100), items (id, label, pass, message, fix).
	 */
	public static function run( array $page ) {
		$page  = array_merge(
			array(
				'content'          => '',
				'title'            => '',
				'main_query'       => '',
				'is_post'          => false,
				'has_author'       => true,
				'has_date'         => true,
				'schema'           => true,
				'business'         => '',
				'place'            => '',
				'crawlers_blocked' => array(),
			),
			$page
		);
		$text  = Checks::text( $page['content'] );
		$words = Checks::words( $text );
		$items = array();

		// 1. The answer comes first: an opening paragraph of 15–80 words.
		$first   = self::first_paragraph( $page['content'] );
		$count   = Checks::words( $first );
		$items[] = self::item(
			'answer_first',
			__( 'Answers in the first lines', 'chatbot' ),
			$count >= 15 && $count <= 80 && ( '' === $page['main_query'] || Checks::covers( $first . ' ' . $page['title'], $page['main_query'] ) ),
			__( 'Start with a short paragraph that answers the main question directly; assistants quote the opening.', 'chatbot' ),
			'writer'
		);

		// 2. A short summary or key points near the top (pages over 400 words).
		$top     = substr( $page['content'], 0, 2500 );
		$summary = $words < 400 || (bool) preg_match( '#(<ul|<ol|summary|in short|key takeaways|tl;dr|at a glance)#i', $top );
		$items[] = self::item( 'summary', __( 'Short summary', 'chatbot' ), $summary, __( 'Add a few key points near the top.', 'chatbot' ), 'writer' );

		// 3. FAQ where useful (pages over 600 words).
		$faq     = $words < 600 || (bool) preg_match( '#(wp:yoast/faq-block|wp:rank-math/faq|<details|frequently asked|\bfaq\b)#i', $page['content'] );
		$items[] = self::item( 'faq', __( 'FAQ where useful', 'chatbot' ), $faq, __( 'Add three to five real questions customers ask, with short answers.', 'chatbot' ), 'writer' );

		// 4. Structured data printed for the page.
		$items[] = self::item( 'schema', __( 'Structured data', 'chatbot' ), (bool) $page['schema'], __( 'Turn on schema in your SEO plugin, or let WPBot print basic schema.', 'chatbot' ), 'schema' );

		// 5. Clear entity names: the business (and the place, for local businesses).
		$lower    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$business = '' === $page['business'] || false !== strpos( $lower, function_exists( 'mb_strtolower' ) ? mb_strtolower( $page['business'] ) : strtolower( $page['business'] ) );
		$place    = '' === $page['place'] || false !== strpos( $lower, function_exists( 'mb_strtolower' ) ? mb_strtolower( $page['place'] ) : strtolower( $page['place'] ) );
		$items[]  = self::item( 'entities', __( 'Clear names', 'chatbot' ), $business && $place, __( 'Name your business, products and place in the text, so assistants know who and where you are.', 'chatbot' ), 'writer' );

		// 6. Author and date on articles.
		$items[] = self::item( 'author_date', __( 'Author and date', 'chatbot' ), ! $page['is_post'] || ( $page['has_author'] && $page['has_date'] ), __( 'Show who wrote the article and when it was updated.', 'chatbot' ), 'manual' );

		// 7. AI crawlers that show pages in answers are allowed.
		$answer_bots = array( 'oai-searchbot', 'chatgpt-user', 'claude-searchbot', 'claude-user', 'perplexitybot' );
		$blocked     = array_values( array_intersect( $answer_bots, (array) $page['crawlers_blocked'] ) );
		$items[]     = self::item( 'crawlers', __( 'AI search crawlers allowed', 'chatbot' ), array() === $blocked, __( 'Search crawlers of AI assistants are blocked, so they cannot cite this page. Allow them in AI crawler settings.', 'chatbot' ), 'crawlers' );

		$passed = count( array_filter( array_column( $items, 'pass' ) ) );
		return array(
			'score' => (int) round( 100 * $passed / count( $items ) ),
			'items' => $items,
		);
	}

	/**
	 * Text of the first paragraph with real text.
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public static function first_paragraph( $html ) {
		if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', (string) $html, $paragraphs ) ) {
			foreach ( $paragraphs[1] as $paragraph ) {
				$text = Checks::text( $paragraph );
				if ( Checks::words( $text ) >= 5 ) {
					return $text;
				}
			}
		}
		$text = Checks::text( $html );
		return implode( ' ', array_slice( preg_split( '/\s+/u', $text ), 0, 80 ) );
	}

	/**
	 * Checklist item.
	 *
	 * @param string $id      Id.
	 * @param string $label   Label.
	 * @param bool   $pass    Passed.
	 * @param string $message Advice when not passed.
	 * @param string $fix     writer|schema|crawlers|manual.
	 * @return array
	 */
	private static function item( $id, $label, $pass, $message, $fix ) {
		return array(
			'id'      => $id,
			'label'   => $label,
			'pass'    => (bool) $pass,
			'message' => $pass ? '' : $message,
			'fix'     => $fix,
		);
	}
}
