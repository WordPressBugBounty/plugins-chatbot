<?php
/**
 * Keywords & Topics: keyword map (Search Console side of the shared map),
 * gaps, unmapped pages, competing pages and the topic map.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Keywords\Cannibalization;
use QuantumCloud\SeoAiSearch\Keywords\Map_Sync;
use QuantumCloud\SeoAiSearch\Keywords\Volumes;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Map\Topic_Map;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /map, /competing, /topics.
 */
final class Map_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/map'                          => array( WP_REST_Server::READABLE, 'map', 'can_view' ),
			'/map/refresh'                  => array( WP_REST_Server::CREATABLE, 'refresh', 'can_use' ),
			'/map/gaps'                     => array( WP_REST_Server::READABLE, 'gaps', 'can_view' ),
			'/map/(?P<post_id>\d+)'         => array( WP_REST_Server::CREATABLE, 'save', 'can_use' ),
			'/map/(?P<post_id>\d+)/accept'  => array( WP_REST_Server::CREATABLE, 'accept', 'can_use' ),
			'/map/(?P<post_id>\d+)/suggest' => array( WP_REST_Server::CREATABLE, 'suggest', 'can_use' ),
			'/map/(?P<post_id>\d+)/focus'   => array( WP_REST_Server::CREATABLE, 'focus', 'can_publish' ),
			'/competing'                    => array( WP_REST_Server::READABLE, 'competing', 'can_view' ),
			'/competing/fix'                => array( WP_REST_Server::CREATABLE, 'fix', 'can_publish' ),
			'/competing/merge'              => array( WP_REST_Server::CREATABLE, 'merge', 'can_manage' ),
			'/topics'                       => array( WP_REST_Server::READABLE, 'topics', 'can_view' ),
			'/topics/move'                  => array( WP_REST_Server::CREATABLE, 'move', 'can_use' ),
			'/topics/group'                 => array( WP_REST_Server::CREATABLE, 'group', 'can_use' ),
			'/topics/(?P<id>\d+)'           => array( WP_REST_Server::CREATABLE, 'rename', 'can_use' ),
			'/topics/(?P<id>\d+)/write'     => array( WP_REST_Server::CREATABLE, 'write', 'can_use' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Is the shared map there?
	 *
	 * @return WP_Error|null
	 */
	private function missing() {
		return Map_Sync::available() ? null : new WP_Error( 'qcld_wpbot_seo_no_map', __( 'The keyword map needs a newer WPBot Core. Update the plugin.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * Pages with their searches, numbers and volumes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function map( WP_REST_Request $request ) {
		$missing = $this->missing();
		if ( $missing ) {
			return $missing;
		}
		$filter  = sanitize_key( (string) $request['filter'] );
		$rows    = Map_Sync::rows_by_post();
		$posts   = get_posts(
			array(
				'post_type'      => (array) apply_filters( 'qcld_wpbot_seo/map_post_types', array( 'post', 'page', 'product' ) ),
				'post_status'    => 'publish',
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- One screen of the site's pages.
				'orderby'        => 'modified',
			)
		);
		$items   = array();
		$queries = array();
		$counts  = array(
			'mapped'   => 0,
			'unmapped' => 0,
			'nodata'   => 0,
		);
		foreach ( $posts as $post ) {
			$main       = Keyword_Map::main_query( $post->ID );
			$supporting = array_column(
				Keyword_Map::entries(
					array(
						'post_id' => $post->ID,
						'role'    => 'supporting',
					),
					10
				),
				'query'
			);
			$page_rows  = isset( $rows[ $post->ID ] ) ? $rows[ $post->ID ] : array();
			$numbers    = null;
			foreach ( $page_rows as $row ) {
				if ( $main && Keyword_Map::normalise( $row['query'] ) === $main['query'] ) {
					$numbers = array(
						'clicks'      => (int) $row['clicks'],
						'impressions' => (int) $row['impressions'],
						'position'    => (float) $row['position'],
					);
				}
			}
			$state = $main ? 'mapped' : ( $page_rows ? 'unmapped' : 'nodata' );
			++$counts[ $state ];
			if ( '' !== $filter && 'all' !== $filter && $filter !== $state ) {
				continue;
			}
			if ( $main ) {
				$queries[] = $main['query'];
			}
			$items[] = array(
				'post_id'    => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'url'        => (string) get_permalink( $post ),
				'type'       => $post->post_type,
				'main'       => $main ? $main['query'] : '',
				'source'     => $main ? $main['source'] : '',
				'locked'     => $main ? (bool) $main['locked'] : false,
				'topic_id'   => $main ? (int) $main['topic_id'] : 0,
				'supporting' => $supporting,
				'numbers'    => $numbers,
				'has_data'   => array() !== $page_rows,
				'focus'      => (string) Seo_Bridge::get( $post->ID, 'focus_keyword' ),
				'can_edit'   => current_user_can( 'edit_post', $post->ID ),
			);
		}
		$volumes = Volumes::cached( $queries );
		foreach ( $items as $index => $item ) {
			$row                       = '' !== $item['main'] ? Volumes::row_for( $volumes, $item['main'] ) : null;
			$items[ $index ]['volume'] = $row ? $row['volume'] : null;
		}
		return rest_ensure_response(
			array(
				'items'    => $items,
				'counts'   => $counts,
				'keywords' => Volumes::connected(),
				'writer'   => Writer_Bridge::available(),
				// S14: grouping reuses AI Writer Pro's topic grouping job (M13) when it is installed.
				'grouping' => class_exists( '\\QuantumCloud\\AiWriterPro\\Authority\\Topic_Grouping' ),
			)
		);
	}

	/**
	 * Group pages into topics with AI Writer Pro's grouping job (M13), the
	 * one shared grouping job; no second one is built here.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function group( WP_REST_Request $request ) {
		if ( ! class_exists( '\\QuantumCloud\\AiWriterPro\\Authority\\Topic_Grouping' ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_grouping', __( 'Grouping pages into topics uses AI Writer Pro. You can still move pages to topics by hand, and keywords saved from Research bring their topics with them.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job = \QuantumCloud\AiWriterPro\Authority\Topic_Grouping::start( 'all' === $request['mode'] ? 'all' : 'new' );
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => (int) $job ) );
	}

	/**
	 * Refresh from Search Console (job queue).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function refresh() {
		$missing = $this->missing();
		return $missing ? $missing : $this->respond( array( 'job_id' => Map_Sync::start() ) );
	}

	/**
	 * Owner sets the searches for a page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$supporting = $request['supporting'];
		$supporting = is_array( $supporting ) ? $supporting : preg_split( '/\R|,/', (string) $supporting );
		$done       = Map_Sync::set_manual( (int) $request['post_id'], (string) $request['main'], array_filter( array_map( 'sanitize_text_field', (array) $supporting ) ), (bool) $request['locked'] );
		return is_wp_error( $done ) ? $done : rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * Accept the current searches (they become manual, so refreshes keep them).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function accept( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$main    = Keyword_Map::main_query( $post_id );
		if ( ! $main ) {
			return $this->invalid( __( 'page', 'chatbot' ) );
		}
		$supporting = array_column(
			Keyword_Map::entries(
				array(
					'post_id' => $post_id,
					'role'    => 'supporting',
				),
				10
			),
			'query'
		);
		$done       = Map_Sync::set_manual( $post_id, $main['query'], $supporting, null !== $request['locked'] ? (bool) $request['locked'] : (bool) $main['locked'] );
		return is_wp_error( $done ) ? $done : rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * AI suggestion for a page without data (not saved).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function suggest( WP_REST_Request $request ) {
		return $this->respond( Map_Sync::ai_suggest( (int) $request['post_id'] ) );
	}

	/**
	 * Put the main search into the SEO plugin's focus keyword when it is empty.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function focus( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$main    = Keyword_Map::main_query( $post_id );
		if ( ! $main || ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->invalid( __( 'page', 'chatbot' ) );
		}
		if ( '' !== trim( (string) Seo_Bridge::get( $post_id, 'focus_keyword' ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_has_focus', __( 'This page already has a focus keyword; it was left as it is.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$change = Seo_Bridge::set( $post_id, 'focus_keyword', $main['query'], 'map', array( 'only_if_empty' => true ) );
		return is_wp_error( $change ) ? $change : rest_ensure_response( array( 'focus' => $main['query'] ) );
	}

	/**
	 * Searches with impressions that no page targets.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function gaps() {
		$missing = $this->missing();
		if ( $missing ) {
			return $missing;
		}
		$period = Period::last( 90 );
		$mapped = array();
		foreach ( Keyword_Map::entries( array(), 5000 ) as $entry ) {
			$mapped[ $entry['query'] ] = true;
		}
		$gaps = array();
		foreach ( Data_Store::period_rows( 'query', $period['start'], $period['end'], 30, 500 ) as $row ) {
			$norm = Keyword_Map::normalise( $row['query'] );
			if ( '' === $norm || isset( $mapped[ $norm ] ) ) {
				continue;
			}
			$gaps[ $norm ] = array(
				'query'       => $row['query'],
				'impressions' => (int) $row['impressions'],
				'clicks'      => (int) $row['clicks'],
				'position'    => (float) $row['position'],
			);
		}
		$volumes = Volumes::cached( array_column( $gaps, 'query' ) );
		foreach ( $gaps as $norm => $gap ) {
			$row                     = Volumes::row_for( $volumes, $gap['query'] );
			$gaps[ $norm ]['volume'] = $row ? $row['volume'] : null;
		}
		$gaps = array_values( $gaps );
		usort(
			$gaps,
			static function ( $a, $b ) {
				return array( (int) $b['volume'], $b['impressions'] ) <=> array( (int) $a['volume'], $a['impressions'] );
			}
		);
		return rest_ensure_response( array( 'gaps' => array_slice( $gaps, 0, 100 ) ) );
	}

	/**
	 * Competing pages.
	 *
	 * @return \WP_REST_Response
	 */
	public function competing() {
		return rest_ensure_response( array( 'cases' => Cannibalization::cases() ) );
	}

	/**
	 * Retarget a weaker page or link it to the stronger one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function fix( WP_REST_Request $request ) {
		$fix = sanitize_key( (string) $request['fix'] );
		if ( ! in_array( $fix, array( 'retarget', 'link' ), true ) ) {
			return $this->invalid( __( 'fix', 'chatbot' ) );
		}
		return $this->respond( Cannibalization::fix( $fix, absint( $request['weak'] ), absint( $request['strong'] ), sanitize_text_field( (string) $request['query'] ), sanitize_text_field( (string) $request['target'] ) ) );
	}

	/**
	 * Merge: redirect the weaker page to the stronger one (Manage settings).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function merge( WP_REST_Request $request ) {
		return $this->respond( Cannibalization::fix( 'merge', absint( $request['weak'] ), absint( $request['strong'] ), sanitize_text_field( (string) $request['query'] ) ) );
	}

	/**
	 * Topic tree with pages and coverage.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function topics() {
		$missing = $this->missing();
		if ( $missing ) {
			return $missing;
		}
		$topics  = array();
		$all     = Topic_Map::topics();
		$entries = array();
		foreach ( $all as $topic ) {
			$entries[ (int) $topic['id'] ] = Topic_Map::pages( (int) $topic['id'] );
		}
		// Round 2 (R5): monthly searches per page and per topic, from cached keyword data (no cost here).
		$queries = array();
		foreach ( $entries as $list ) {
			foreach ( $list as $entry ) {
				$queries[] = (string) $entry['query'];
			}
		}
		$data = function_exists( 'qcld_wpbot_core_keyword_data' ) && array() !== $queries ? qcld_wpbot_core_keyword_data( $queries ) : array();
		foreach ( $all as $topic ) {
			$pages    = array();
			$searches = null;
			$source   = '';
			foreach ( $entries[ (int) $topic['id'] ] as $entry ) {
				$norm   = Keyword_Map::normalise( (string) $entry['query'] );
				$volume = isset( $data[ $norm ]['volume'] ) ? $data[ $norm ]['volume'] : null;
				if ( null !== $volume ) {
					$searches = (int) $searches + (int) $volume;
					$source   = (string) $data[ $norm ]['provider'];
				}
				$pages[] = array(
					'vault'   => 0 === strpos( (string) $entry['plan_ref'], 'vault-' ),
					'post_id' => (int) $entry['post_id'],
					'title'   => ! empty( $entry['post_id'] ) ? get_the_title( (int) $entry['post_id'] ) : ( isset( $entry['title'] ) ? (string) $entry['title'] : $entry['query'] ),
					'query'   => $entry['query'],
					'status'  => isset( $entry['status'] ) ? $entry['status'] : '',
					'volume'  => $volume,
				);
			}
			// Gaps (planned pages) with the most searches first.
			usort(
				$pages,
				static function ( $a, $b ) {
					$planned = array( 'planned' === $a['status'], 'planned' === $b['status'] );
					if ( $planned[0] && $planned[1] ) {
						return (int) $b['volume'] <=> (int) $a['volume'];
					}
					return 0;
				}
			);
			$topic['id']           = (int) $topic['id'];
			$topic['parent_id']    = isset( $topic['parent_id'] ) ? (int) $topic['parent_id'] : 0;
			$topic['pages']        = $pages;
			$topic['searches']     = $searches;
			$topic['source']       = '' !== $source ? \QuantumCloud\SeoAiSearch\Research\Research_View::provider_label( $source ) : '';
			$topic['needs_pillar'] = empty( $topic['pillar_post_id'] ) && count( $pages ) >= 3;
			$topics[]              = $topic;
		}
		/**
		 * Topic map rows for the screen (the Pro module adds competitor coverage).
		 *
		 * @param array $topics Topics.
		 */
		$topics = (array) apply_filters( 'qcld_wpbot_seo/topics', $topics );
		return rest_ensure_response(
			array(
				'topics'   => $topics,
				'unmapped' => count( Keyword_Map::unmapped_posts( array( 'post', 'page' ), 500 ) ),
				'writer'   => Writer_Bridge::available(),
			)
		);
	}

	/**
	 * Move a page to a topic (owner change).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function move( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$topic = absint( $request['topic_id'] );
		if ( ! $topic && '' !== trim( (string) $request['topic'] ) ) {
			$topic = Topic_Map::ensure( sanitize_text_field( (string) $request['topic'] ), 0, 'manual' );
		}
		Keyword_Map::set_topic( $post_id, $topic, true );
		return $this->topics();
	}

	/**
	 * Rename a topic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rename( WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request['name'] );
		if ( '' === $name ) {
			return $this->invalid( __( 'name', 'chatbot' ) );
		}
		Topic_Map::update( (int) $request['id'], array( 'name' => $name ), true );
		return $this->topics();
	}

	/**
	 * Write a page for a topic in AI Writer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$topic = Topic_Map::get( (int) $request['id'] );
		if ( ! $topic || ! function_exists( 'qcld_wpbot_writer_open_brief_url' ) ) {
			return $this->invalid( __( 'topic', 'chatbot' ) );
		}
		return rest_ensure_response(
			array(
				'url' => (string) qcld_wpbot_writer_open_brief_url(
					array(
						'mode'          => 'new',
						'topic'         => $topic['name'],
						'map_topic'     => (int) $topic['id'],
						'source_module' => Module::ID,
					)
				),
			)
		);
	}
}
