<?php
/**
 * Page Audit & Fixes (F1–F4): /audit/*.
 *
 * - F1 pick a post type and a page, set a target keyword (pre-filled from
 *   the Vault, the Keyword Map or Search Console), optionally save it as
 *   the page's focus keyword, run the audit (instant, no AI);
 * - F2 the full checklist in groups, with the AI search readiness items;
 *   AI fixes for alt text, FAQ or summary and the introduction;
 * - F3 the site summary (good, average, poor; distribution; counts per
 *   issue);
 * - F4 the audit history and score trend.
 *
 * View reads; Use runs audits and proposes fixes; Publish saves fixes to
 * live pages; every page change also needs edit rights on that page.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Onpage\Audit_History;
use QuantumCloud\SeoAiSearch\Onpage\Checks;
use QuantumCloud\SeoAiSearch\Onpage\Content_Fixer;
use QuantumCloud\SeoAiSearch\Readiness\Readiness;
use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Page Audit routes.
 */
final class Audit_Controller extends Rest_Base {

	/**
	 * Score bands.
	 */
	const GOOD    = 70;
	const AVERAGE = 40;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/audit/types'                          => array( WP_REST_Server::READABLE, 'types', 'can_view' ),
			'/audit/content'                        => array( WP_REST_Server::READABLE, 'content', 'can_view' ),
			'/audit/keyword'                        => array( WP_REST_Server::READABLE, 'keyword', 'can_view' ),
			'/audit/run'                            => array( WP_REST_Server::CREATABLE, 'run', 'can_use' ),
			'/audit/page/(?P<post_id>\d+)'          => array( WP_REST_Server::READABLE, 'page', 'can_view' ),
			'/audit/summary'                        => array( WP_REST_Server::READABLE, 'summary', 'can_view' ),
			'/audit/history'                        => array( WP_REST_Server::READABLE, 'history', 'can_view' ),
			'/audit/history/(?P<id>\d+)'            => array( WP_REST_Server::READABLE, 'history_item', 'can_view' ),
			'/fixes/(?P<post_id>\d+)/content'       => array( WP_REST_Server::CREATABLE, 'content_propose', 'can_use' ),
			'/fixes/(?P<post_id>\d+)/content/apply' => array( WP_REST_Server::CREATABLE, 'content_apply', 'can_publish' ),
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
	 * Post types that can be audited (products when WooCommerce is active).
	 *
	 * @return \WP_REST_Response
	 */
	public function types() {
		return rest_ensure_response( self::type_list() );
	}

	/**
	 * Content of one type, newest first, with a search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function content( WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request['type'] );
		if ( ! in_array( $type, wp_list_pluck( self::type_list(), 'id' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_audit_type', __( 'Choose a content type.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$query = new \WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
				's'              => sanitize_text_field( (string) $request['search'] ),
				'posts_per_page' => 50,
				'orderby'        => 'modified',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $query->posts as $post_id ) {
			$stored = Audit::stored( $post_id );
			$out[]  = array(
				'id'     => (int) $post_id,
				'title'  => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
				'status' => (string) get_post_status( $post_id ),
				'score'  => $stored ? (int) $stored['score'] : null,
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Suggested target keyword for a page: the Vault, the Keyword Map, then
	 * Search Console.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function keyword( WP_REST_Request $request ) {
		return rest_ensure_response( self::suggested_keyword( absint( $request['post_id'] ) ) );
	}

	/**
	 * Run an audit now (instant, no AI) for a page and keyword.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		$post    = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, wp_list_pluck( self::type_list(), 'id' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'Choose a page to audit.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot read this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$keyword = trim( sanitize_text_field( (string) $request['keyword'] ) );
		$saved   = false;
		if ( '' !== $keyword && $request['save_focus'] ) {
			$saved = $this->save_focus( $post_id, $keyword );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		Audit::audit( $post_id, 'manual', $keyword );
		$view                = self::view( $post_id );
		$view['focus_saved'] = (bool) $saved;
		return rest_ensure_response( $view );
	}

	/**
	 * The latest audit of a page, with history and readiness.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function page( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! Audit::stored( $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
			Audit::audit( $post_id, 'refresh' );
		}
		return rest_ensure_response( self::view( $post_id ) );
	}

	/**
	 * Site summary: bands, distribution, average, counts per issue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function summary( WP_REST_Request $request ) {
		global $wpdb;
		$type  = sanitize_key( (string) $request['type'] );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, pm.meta_value, p.post_type FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status = 'publish'", Audit::META ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reading this module's audits.
		$bands = array(
			'good'    => 0,
			'average' => 0,
			'poor'    => 0,
		);
		$dist  = array_fill( 0, 10, 0 );
		$total = 0;
		$sum   = 0;
		$count = array();
		foreach ( (array) $rows as $row ) {
			if ( '' !== $type && $row['post_type'] !== $type ) {
				continue;
			}
			$audit = json_decode( (string) $row['meta_value'], true );
			if ( ! is_array( $audit ) || ! isset( $audit['score'] ) ) {
				continue;
			}
			$score = (int) $audit['score'];
			++$total;
			$sum += $score;
			++$bands[ self::band( $score ) ];
			++$dist[ min( 9, (int) floor( $score / 10 ) ) ];
			foreach ( isset( $audit['issues'] ) ? (array) $audit['issues'] : array() as $issue ) {
				$id = (string) $issue['id'];
				if ( ! isset( $count[ $id ] ) ) {
					$count[ $id ] = array(
						'id'       => $id,
						'label'    => isset( $issue['label'] ) ? (string) $issue['label'] : Checks::label( $id ),
						'group'    => Checks::group( $id ),
						'severity' => isset( $issue['severity'] ) ? (string) $issue['severity'] : 'medium',
						'fix'      => isset( $issue['fix'] ) ? (string) $issue['fix'] : '',
						'pages'    => 0,
					);
				}
				++$count[ $id ]['pages'];
			}
		}
		$count = array_values( $count );
		usort(
			$count,
			static function ( $a, $b ) {
				return $b['pages'] <=> $a['pages'];
			}
		);
		$distribution = array();
		foreach ( $dist as $index => $pages ) {
			$distribution[] = array(
				'from'  => $index * 10,
				'to'    => 9 === $index ? 100 : $index * 10 + 9,
				'pages' => $pages,
			);
		}
		return rest_ensure_response(
			array(
				'total'        => $total,
				'bands'        => $bands,
				'average'      => $total ? (int) round( $sum / $total ) : null,
				'distribution' => $distribution,
				'issues'       => $count,
				'types'        => self::type_list(),
			)
		);
	}

	/**
	 * Audit history (a page, or all).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function history( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		$page    = max( 1, (int) $request['page'] );
		$rows    = Audit_History::query( $post_id, 30, ( $page - 1 ) * 30 );
		foreach ( $rows as $index => $row ) {
			unset( $rows[ $index ]['checks'] );
		}
		return rest_ensure_response(
			array(
				'items' => $rows,
				'trend' => $post_id ? Audit_History::trend( $post_id ) : array(),
			)
		);
	}

	/**
	 * One past audit, with check labels.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function history_item( WP_REST_Request $request ) {
		$row = Audit_History::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'qcld_wpbot_seo_audit_missing', __( 'This audit is no longer kept.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$checks = array();
		foreach ( $row['checks'] as $id => $status ) {
			$checks[] = array(
				'id'     => (string) $id,
				'label'  => Checks::label( (string) $id ),
				'group'  => Checks::group( (string) $id ),
				'status' => (string) $status,
			);
		}
		$row['checks'] = $checks;
		return rest_ensure_response( $row );
	}

	/**
	 * Propose an AI fix for alt text, an FAQ or the introduction.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function content_propose( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return $this->respond( Content_Fixer::propose( $post_id, sanitize_key( (string) $request['kind'] ), array( 'keyword' => (string) $request['keyword'] ) ) );
	}

	/**
	 * Save a reviewed content fix (can be undone).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function content_apply( WP_REST_Request $request ) {
		$post_id = absint( $request['post_id'] );
		$value   = $request->get_param( 'value' );
		$change  = Content_Fixer::apply( $post_id, sanitize_key( (string) $request['kind'] ), is_array( $value ) ? $value : array() );
		if ( is_wp_error( $change ) ) {
			return $this->respond( $change );
		}
		$view              = self::view( $post_id );
		$view['change_id'] = $change;
		return rest_ensure_response( $view );
	}

	/**
	 * Everything the audit view of a page shows.
	 *
	 * @param int $post_id Post id.
	 * @return array post_id, title, url, edit, score, band, keyword, groups (checks), readiness, history, trend, can_edit.
	 */
	public static function view( $post_id ) {
		$stored    = Audit::stored( $post_id );
		$readiness = Readiness::for_post( $post_id );
		$checks    = $stored && isset( $stored['checks'] ) ? (array) $stored['checks'] : array();
		foreach ( $readiness ? (array) $readiness['items'] : array() as $item ) {
			$checks[] = array(
				'id'       => 'ai_' . $item['id'],
				'group'    => 'ai',
				'label'    => (string) $item['label'],
				'status'   => $item['pass'] ? 'pass' : 'warning',
				'message'  => (string) $item['message'],
				'why'      => '',
				'fix'      => 'readiness:' . (string) $item['fix'],
				'severity' => '',
			);
		}
		$score = $stored ? (int) $stored['score'] : null;
		return array(
			'post_id'   => (int) $post_id,
			'title'     => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'url'       => (string) get_permalink( $post_id ),
			'edit'      => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
			'can_edit'  => current_user_can( 'edit_post', $post_id ),
			'score'     => $score,
			'band'      => null === $score ? '' : self::band( $score ),
			'keyword'   => $stored && isset( $stored['main_query'] ) ? (string) $stored['main_query'] : '',
			'words'     => $stored && isset( $stored['words'] ) ? (int) $stored['words'] : 0,
			'checked'   => $stored && isset( $stored['checked_at'] ) ? (string) $stored['checked_at'] : '',
			'checks'    => $checks,
			'readiness' => $readiness ? (int) $readiness['score'] : null,
			'history'   => array_map(
				static function ( $row ) {
					unset( $row['checks'] );
					return $row;
				},
				Audit_History::query( $post_id, 10 )
			),
			'trend'     => Audit_History::trend( $post_id ),
			'suggested' => self::suggested_keyword( $post_id ),
		);
	}

	/**
	 * Good (70+), average (40–69) or poor (under 40).
	 *
	 * @param int $score Score.
	 * @return string
	 */
	public static function band( $score ) {
		if ( $score >= self::GOOD ) {
			return 'good';
		}
		return $score >= self::AVERAGE ? 'average' : 'poor';
	}

	/**
	 * Suggested keyword and where it comes from.
	 *
	 * @param int $post_id Post id.
	 * @return array keyword, source (vault, map, search_console, seo_plugin, '').
	 */
	public static function suggested_keyword( $post_id ) {
		if ( function_exists( 'qcld_wpbot_core_vault_query' ) ) {
			$rows = qcld_wpbot_core_vault_query(
				array(
					'post_id' => $post_id,
					'status'  => array( 'targeted', 'ranking', 'researched' ),
					'limit'   => 1,
				)
			);
			if ( $rows ) {
				return array(
					'keyword' => $rows[0]['keyword'],
					'source'  => 'vault',
				);
			}
		}
		$focus = (string) Seo_Bridge::get( $post_id, 'focus_keyword' );
		if ( '' !== $focus ) {
			return array(
				'keyword' => $focus,
				'source'  => 'seo_plugin',
			);
		}
		$main = Keyword_Map::main_query( $post_id );
		if ( $main ) {
			return array(
				'keyword' => (string) $main['query'],
				'source'  => 'search_console' === $main['source'] ? 'search_console' : 'map',
			);
		}
		$query = Audit::main_query( $post_id );
		return array(
			'keyword' => $query,
			'source'  => '' !== $query ? 'search_console' : '',
		);
	}

	/**
	 * Audit-able post types.
	 *
	 * @return array[] id, label.
	 */
	public static function type_list() {
		$out = array();
		foreach ( array_unique( array_merge( array( 'post', 'page' ), Audit::post_types(), class_exists( 'WooCommerce' ) && post_type_exists( 'product' ) ? array( 'product' ) : array() ) ) as $type ) {
			$object = get_post_type_object( $type );
			if ( $object && $object->public ) {
				$out[] = array(
					'id'    => $type,
					'label' => (string) $object->labels->name,
				);
			}
		}
		return $out;
	}

	/**
	 * Save a keyword as the page's focus keyword: the SEO plugin's (or the
	 * module's own) field, logged and undoable, and a manual, locked main
	 * search in the shared keyword map.
	 *
	 * @param int    $post_id Post id.
	 * @param string $keyword Keyword.
	 * @return true|WP_Error
	 */
	private function save_focus( $post_id, $keyword ) {
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( Module::cap( 'publish' ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'Saving the focus keyword needs the "Publish" permission and edit rights on this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$before = (string) Seo_Bridge::get( $post_id, 'focus_keyword' );
		if ( $before !== $keyword ) {
			Seo_Bridge::put( $post_id, 'focus_keyword', $keyword );
			Change_Log::record(
				array(
					'post_id' => $post_id,
					'kind'    => 'meta',
					'field'   => 'focus_keyword',
					'before'  => $before,
					'after'   => $keyword,
					'source'  => 'audit',
				)
			);
		}
		Keyword_Map::set_post(
			$post_id,
			$keyword,
			array(
				'source' => 'manual',
				'locked' => true,
				'module' => Module::ID,
			)
		);
		return true;
	}
}
