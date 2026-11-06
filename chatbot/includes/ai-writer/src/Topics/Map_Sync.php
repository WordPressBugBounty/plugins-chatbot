<?php
/**
 * AI Writer's side of the shared keyword and topic map (brief 7.10, CR-1).
 *
 * - A finished piece records its main search, supporting searches and
 *   topic (listener on qcld_wpbot_writer/content_finalized).
 * - Saved ideas (the calendar's ideas list) are recorded as planned pages;
 *   an idea placed on the calendar becomes its dated draft's entry; a
 *   dismissed idea is removed.
 * - Ideas and briefs read the map for "Covered by" and "Planned" labels.
 *
 * Everything is optional: with an older WPBot Core (no map) nothing runs,
 * and ideas and posts without map fields keep working.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Map\Topic_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Map sync.
 */
final class Map_Sync {

	/**
	 * Hook the listeners.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'finalized' ), 10, 2 );
	}

	/**
	 * Whether the running WPBot Core has the map.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Keyword_Map::class ) && class_exists( Topic_Map::class );
	}

	/**
	 * Plan reference of an idea.
	 *
	 * @param int $idea_id Idea.
	 * @return string
	 */
	public static function ref( $idea_id ) {
		return 'writer-idea-' . (int) $idea_id;
	}

	/**
	 * A finished piece: record its searches and topic.
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function finalized( $post_id, $brief ) {
		if ( ! self::available() || ! $post_id ) {
			return;
		}
		$brief    = (array) $brief;
		$title    = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		$query    = ! empty( $brief['main_query'] ) ? (string) $brief['main_query'] : ( ! empty( $brief['topic'] ) ? (string) $brief['topic'] : $title );
		$keywords = isset( $brief['keywords'] ) ? array_map( 'strval', (array) $brief['keywords'] ) : array();
		$topic    = ! empty( $brief['map_topic'] ) ? (string) $brief['map_topic'] : Topic_Finder::topic_for( $query . ' ' . $title );
		Keyword_Map::set_post(
			$post_id,
			$query,
			array(
				'source'     => 'ai',
				'topic'      => $topic,
				'supporting' => array_slice( $keywords, 1 ),
				'module'     => Module::ID,
			)
		);
		if ( ! empty( $brief['idea_id'] ) ) {
			Keyword_Map::remove_planned( self::ref( (int) $brief['idea_id'] ) );
		}
	}

	/**
	 * A saved idea: record it as a planned page.
	 *
	 * @param array $idea Idea (Ideas::get()).
	 * @return void
	 */
	public static function plan_idea( array $idea ) {
		if ( ! self::available() ) {
			return;
		}
		$query = '' !== $idea['main_query'] ? $idea['main_query'] : $idea['title'];
		Keyword_Map::set_planned(
			self::ref( $idea['id'] ),
			$query,
			array(
				'topic'  => '' !== $idea['topic'] ? $idea['topic'] : Topic_Finder::topic_for( $idea['title'] ),
				'module' => Module::ID,
			)
		);
	}

	/**
	 * An idea was dismissed: forget its planned page.
	 *
	 * @param int $idea_id Idea.
	 * @return void
	 */
	public static function unplan_idea( $idea_id ) {
		if ( self::available() ) {
			Keyword_Map::remove_planned( self::ref( $idea_id ) );
		}
	}

	/**
	 * An idea became a dated draft (calendar): the draft takes its entry.
	 *
	 * @param array $idea    Idea.
	 * @param int   $post_id Draft.
	 * @return void
	 */
	public static function idea_placed( array $idea, $post_id ) {
		if ( ! self::available() ) {
			return;
		}
		self::plan_idea( $idea );
		Keyword_Map::attach_planned( self::ref( $idea['id'] ), $post_id );
	}

	/**
	 * What the map says about a search: a live page that targets it, and
	 * a planned page or draft that will.
	 *
	 * @param string $query   Search (or title).
	 * @param array  $exclude post_ids, plan_refs.
	 * @return array{covered: array|null, planned: array|null}
	 */
	public static function lookup( $query, array $exclude = array() ) {
		$out = array(
			'covered' => null,
			'planned' => null,
		);
		if ( ! self::available() || '' === trim( (string) $query ) ) {
			return $out;
		}
		foreach ( Keyword_Map::posts_for_query( $query, $exclude ) as $entry ) {
			if ( 'publish' === $entry['status'] && ! $out['covered'] ) {
				$out['covered'] = $entry;
			} elseif ( 'publish' !== $entry['status'] && ! $out['planned'] ) {
				$out['planned'] = $entry;
			}
		}
		if ( $out['planned'] && 'planned' === $out['planned']['status'] && 0 === strpos( $out['planned']['plan_ref'], 'writer-idea-' ) ) {
			$idea                    = Ideas::get( (int) substr( $out['planned']['plan_ref'], 12 ) );
			$out['planned']['title'] = $idea ? $idea['title'] : $out['planned']['query'];
		}
		return $out;
	}
}
