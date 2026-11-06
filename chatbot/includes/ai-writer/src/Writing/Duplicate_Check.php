<?php
/**
 * Duplicate check before writing (brief 6.2, CR-1): another page (or a
 * planned page) that already targets the same search. It reads the shared
 * keyword map first; until the map knows a page, it compares existing
 * titles and the SEO plugin's focus keyword (Yoast SEO, Rank Math).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Topics\Map_Sync;
use QuantumCloud\AiWriter\Topics\Topic_Finder;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Duplicate check.
 */
final class Duplicate_Check {

	/**
	 * Pages that already target this search, best evidence first.
	 *
	 * @param string $topic      Topic sentence of the brief.
	 * @param string $main_query Main search ('' to use the topic).
	 * @param array  $exclude    post_id (the page being written into), idea_id.
	 * @return array[] post_id, title, status, planned (bool), reason (map|vault|title|focus_keyword), query, edit_url, improve_url.
	 */
	public static function find( $topic, $main_query = '', array $exclude = array() ) {
		$query   = '' !== trim( $main_query ) ? $main_query : $topic;
		$skip    = isset( $exclude['post_id'] ) ? absint( $exclude['post_id'] ) : 0;
		$idea_id = isset( $exclude['idea_id'] ) ? absint( $exclude['idea_id'] ) : 0;
		$found   = array();

		// 1. The shared keyword map (pages and planned pages).
		if ( Map_Sync::available() ) {
			foreach ( Keyword_Map::posts_for_query(
				$query,
				array(
					'post_ids'  => $skip ? array( $skip ) : array(),
					'plan_refs' => $idea_id ? array( Map_Sync::ref( $idea_id ) ) : array(),
				)
			) as $entry ) {
				if ( $entry['post_id'] ) {
					self::add( $found, $entry['post_id'], 'map', $entry['query'] );
				} else {
					$planned = Map_Sync::lookup( $query, array( 'plan_refs' => $idea_id ? array( Map_Sync::ref( $idea_id ) ) : array() ) );
					if ( $planned['planned'] && ! $planned['planned']['post_id'] ) {
						$found[ 'plan-' . $entry['plan_ref'] ] = array(
							'post_id'     => 0,
							'title'       => $planned['planned']['title'],
							'status'      => 'planned',
							'planned'     => true,
							'reason'      => 'map',
							'query'       => $entry['query'],
							'edit_url'    => '',
							'improve_url' => '',
						);
					}
				}
			}
		}

		// 1b. The Keyword Vault (SEO & AI Search): the keyword is assigned to another page (CR-4).
		$owner = \QuantumCloud\AiWriter\Topics\Vault_Link::owner( $query, $skip );
		if ( $owner ) {
			self::add( $found, $owner['post_id'], 'vault', $query );
		}

		// 2. SEO plugin focus keywords.
		$normal = Keyword_Map::normalise( $query );
		if ( '' !== $normal ) {
			foreach ( array( '_yoast_wpseo_focuskw', 'rank_math_focus_keyword' ) as $key ) {
				$ids = get_posts(
					array(
						'post_type'      => 'any',
						'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
						'posts_per_page' => 5,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One exact match on the SEO plugin's own key, five rows.
						'meta_query'     => array(
							array(
								'key'     => $key,
								'value'   => $query,
								'compare' => 'LIKE',
							),
						),
					)
				);
				foreach ( $ids as $id ) {
					// Rank Math stores a comma list; the first one is the focus keyword.
					$value = explode( ',', (string) get_post_meta( $id, $key, true ) );
					if ( (int) $id !== $skip && Keyword_Map::normalise( $value[0] ) === $normal ) {
						self::add( $found, $id, 'focus_keyword', $value[0] );
					}
				}
			}
		}

		// 3. Titles: most of the search's words in a page title.
		$words = Topic_Finder::words( $query );
		if ( count( $words ) >= 2 ) {
			$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
			$ids   = get_posts(
				array(
					'post_type'        => $types,
					'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					's'                => implode( ' ', array_slice( $words, 0, 3 ) ),
					'posts_per_page'   => 10,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);
			foreach ( $ids as $id ) {
				$have = Topic_Finder::words( get_the_title( $id ) );
				if ( (int) $id !== $skip && array() !== $have && count( array_intersect( $words, $have ) ) / count( $words ) >= 0.75 ) {
					self::add( $found, $id, 'title', $query );
				}
			}
		}
		return array_slice( array_values( $found ), 0, 5 );
	}

	/**
	 * Add a post once (first evidence wins), if the user may see it.
	 *
	 * @param array  $found   Results by key.
	 * @param int    $post_id Post.
	 * @param string $reason  map, vault, focus_keyword or title.
	 * @param string $query   Query matched.
	 * @return void
	 */
	private static function add( array &$found, $post_id, $reason, $query ) {
		$post = get_post( $post_id );
		if ( ! $post || isset( $found[ 'post-' . $post->ID ] ) || 'trash' === $post->post_status ) {
			return;
		}
		$editable = current_user_can( 'edit_post', $post->ID );
		if ( ! $editable && 'publish' !== $post->post_status ) {
			return;
		}
		$found[ 'post-' . $post->ID ] = array(
			'post_id'     => (int) $post->ID,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status'      => $post->post_status,
			'planned'     => 'publish' !== $post->post_status,
			'reason'      => $reason,
			'query'       => (string) $query,
			'edit_url'    => $editable ? (string) get_edit_post_link( $post->ID, 'raw' ) : (string) get_permalink( $post ),
			'improve_url' => $editable ? admin_url( 'admin.php?page=wpbot-ai-writer&improve=' . (int) $post->ID ) : '',
		);
	}
}
