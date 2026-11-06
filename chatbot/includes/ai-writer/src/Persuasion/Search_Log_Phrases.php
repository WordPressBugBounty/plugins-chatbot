<?php
/**
 * The site search log as a voice of customer source (Free, round 3): what
 * visitors type into the site search, in their own words. Only terms
 * searched at least twice in the last 90 days are read; WPBot Core redacts
 * every term before it is stored.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

use QuantumCloud\AiWriter\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Search log phrases.
 */
final class Search_Log_Phrases {

	/** Searches a term needs in 90 days to count. */
	const MIN_SEARCHES = 2;

	/**
	 * Register the source.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function register( $sources ) {
		$sources['search'] = array(
			'label'    => __( 'Site search log', 'chatbot' ),
			'edition'  => 'free',
			'callback' => array( __CLASS__, 'items' ),
		);
		return $sources;
	}

	/**
	 * Search terms, most searched first (read in one go; the log keeps
	 * short terms only).
	 *
	 * @param int $cursor Unused.
	 * @param int $limit  Unused.
	 * @return array
	 */
	public static function items( $cursor, $limit ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Source callback signature.
		global $wpdb;
		$table = Schema::table( Schema::SEARCH_LOG );
		$since = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT term, SUM(searches) AS total FROM {$table} WHERE day >= %s GROUP BY term_hash, term HAVING total >= %d ORDER BY total DESC LIMIT 500", $since, self::MIN_SEARCHES ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'ref'       => 'search-' . md5( (string) $row['term'] ),
				'text'      => (string) $row['term'],
				'names'     => array(),
				'min_words' => 2,
			);
		}
		return array(
			'items'  => $items,
			'cursor' => 0,
			'done'   => true,
		);
	}
}
