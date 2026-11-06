<?php
/**
 * Coverage view (brief 7.10, M13; Free): the shared topic map as a simple
 * list inside Topic Finder, so writers can see what the site covers
 * without SEO & AI Search. Each topic shows its coverage (covered, thin,
 * planned, missing), its pages, its missing subtopics and what to write
 * next.
 *
 * Extensions add to each topic through `qcld_wpbot_writer/coverage`
 * (AI Writer Pro: cluster links and a rank).
 *
 * Search volumes come from a keyword data provider when one is connected
 * (wpbot-core's keyword data API from SEO & AI Search, or any extension
 * on `qcld_wpbot_writer/search_volumes`); without one, nothing shows.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Map\Topic_Map;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Coverage view.
 */
final class Coverage {

	/**
	 * Order in the list: what needs work first.
	 */
	const ORDER = array(
		'missing' => 0,
		'thin'    => 1,
		'planned' => 2,
		'covered' => 3,
		''        => 4,
	);

	/**
	 * Every top-level topic with its subtopics, coverage and next piece.
	 *
	 * @return array{available: bool, topics: array[]}
	 */
	public static function items() {
		if ( ! Map_Sync::available() || ! method_exists( Topic_Map::class, 'refresh_coverage' ) ) {
			return array(
				'available' => false,
				'topics'    => array(),
			);
		}
		Topic_Map::refresh_coverage();
		$all = array();
		foreach ( Topic_Map::topics() as $topic ) {
			$all[ $topic['id'] ] = $topic;
		}
		$volumes = self::volumes( wp_list_pluck( $all, 'name' ) );
		// Full numbers (trend, competition, source) where the keyword cache has them (CR-6 W4).
		$numbers = Keyword_Numbers::lookup( wp_list_pluck( $all, 'name' ) );
		$items   = array();
		foreach ( $all as $topic ) {
			if ( $topic['parent_id'] && isset( $all[ $topic['parent_id'] ] ) ) {
				continue;
			}
			$item            = self::item( $topic, $all, $volumes );
			$key             = Keyword_Numbers::normalise( (string) $topic['name'] );
			$item['numbers'] = isset( $numbers[ $key ] ) ? $numbers[ $key ] : null;
			$items[]         = $item;
		}
		usort(
			$items,
			static function ( $a, $b ) {
				$order = self::ORDER[ $a['coverage'] ] <=> self::ORDER[ $b['coverage'] ];
				return 0 !== $order ? $order : strcasecmp( $a['name'], $b['name'] );
			}
		);

		/**
		 * Coverage view items (Pro adds cluster links and a rank).
		 *
		 * @param array[] $items Topics: id, name, coverage, live, planned, pages, pillar, subtopics, missing, next, volume, numbers (or null).
		 */
		$items = (array) apply_filters( 'qcld_wpbot_writer/coverage', $items );
		return array(
			'available' => true,
			'topics'    => array_values( $items ),
		);
	}

	/**
	 * One topic of the view.
	 *
	 * @param array $topic   Topic (Topic_Map::topics() shape).
	 * @param array $all     Every topic by id.
	 * @param array $volumes Normalised name => monthly searches.
	 * @return array
	 */
	private static function item( array $topic, array $all, array $volumes ) {
		$subtopics = array();
		foreach ( $all as $other ) {
			if ( $other['parent_id'] === $topic['id'] ) {
				$subtopics[] = array(
					'id'       => $other['id'],
					'name'     => $other['name'],
					'coverage' => $other['coverage'],
					'live'     => $other['live'],
				);
			}
		}
		$pages = array();
		foreach ( Topic_Map::pages( $topic['id'] ) as $page ) {
			$pages[] = array(
				'post_id' => $page['post_id'],
				'title'   => '' !== $page['title'] ? $page['title'] : $page['query'],
				'status'  => $page['status'],
				'query'   => $page['query'],
				'pillar'  => $page['post_id'] && $page['post_id'] === $topic['pillar_post_id'],
				'url'     => $page['post_id'] && 'publish' === $page['status'] ? (string) get_permalink( $page['post_id'] ) : '',
			);
		}
		$missing = array_values(
			array_filter(
				$subtopics,
				static function ( $sub ) {
					return 'missing' === $sub['coverage'];
				}
			)
		);
		$next    = null;
		if ( array() !== $missing ) {
			$next = array(
				'topic_id' => $missing[0]['id'],
				'name'     => $missing[0]['name'],
			);
		} elseif ( in_array( $topic['coverage'], array( 'missing', 'thin' ), true ) ) {
			$next = array(
				'topic_id' => $topic['id'],
				'name'     => $topic['name'],
			);
		} else {
			foreach ( $subtopics as $sub ) {
				if ( 'thin' === $sub['coverage'] ) {
					$next = array(
						'topic_id' => $sub['id'],
						'name'     => $sub['name'],
					);
					break;
				}
			}
		}
		$key = Keyword_Map::normalise( $topic['name'] );
		return array(
			'id'        => $topic['id'],
			'name'      => $topic['name'],
			'coverage'  => $topic['coverage'],
			'locked'    => $topic['locked'],
			'source'    => $topic['source'],
			'live'      => $topic['live'],
			'planned'   => $topic['planned'],
			'pages'     => $pages,
			'pillar'    => $topic['pillar_post_id'],
			'subtopics' => $subtopics,
			'missing'   => wp_list_pluck( $missing, 'name' ),
			'next'      => $next,
			'volume'    => isset( $volumes[ $key ] ) ? (int) $volumes[ $key ] : null,
		);
	}

	/**
	 * Address of a brief for a topic ("Write next").
	 *
	 * @param int $topic_id Topic or subtopic.
	 * @return string|WP_Error
	 */
	public static function write_url( $topic_id ) {
		if ( ! Map_Sync::available() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_map', __( 'The topic map needs a newer WPBot Core.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$topic = Topic_Map::get( (int) $topic_id );
		if ( ! $topic ) {
			return new WP_Error( 'qcld_wpbot_writer_no_topic', __( 'That topic no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$parent = $topic['parent_id'] ? Topic_Map::get( $topic['parent_id'] ) : null;
		$notes  = $parent ? sprintf(
			/* translators: %s: parent topic. */
			__( 'Part of the topic "%s". Link to its main guide.', 'chatbot' ),
			$parent['name']
		) : '';
		return qcld_wpbot_writer_open_brief_url(
			array(
				'topic'         => $topic['name'],
				'main_query'    => $topic['name'],
				'map_topic'     => $parent ? $parent['name'] : $topic['name'],
				'notes'         => $notes,
				'source_module' => 'coverage',
			)
		);
	}

	/**
	 * Monthly search volumes for searches, when a keyword data provider is
	 * connected (empty otherwise).
	 *
	 * @param string[] $queries Searches.
	 * @return array Normalised search => monthly searches.
	 */
	public static function volumes( array $queries ) {
		$queries = array_values( array_unique( array_filter( array_map( array( Keyword_Map::class, 'normalise' ), $queries ) ) ) );
		if ( array() === $queries || ! Map_Sync::available() ) {
			return array();
		}
		$volumes = array();
		// WPBot Core's keyword data cache (SEO & AI Search), when present.
		foreach ( Keyword_Numbers::lookup( $queries ) as $query => $numbers ) {
			$volumes[ $query ] = $numbers['volume'];
		}

		/**
		 * Monthly search volumes (normalised search => number).
		 *
		 * @param array    $volumes Volumes found so far.
		 * @param string[] $queries Normalised searches.
		 */
		$volumes = (array) apply_filters( 'qcld_wpbot_writer/search_volumes', $volumes, $queries );
		$out     = array();
		foreach ( $volumes as $query => $volume ) {
			if ( is_numeric( $volume ) ) {
				$out[ Keyword_Map::normalise( (string) $query ) ] = max( 0, (int) $volume );
			}
		}
		return $out;
	}
}
