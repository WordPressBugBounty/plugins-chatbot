<?php
/**
 * On-page fixes: page list with issues, site-wide check, one page's audit
 * and search fields, AI proposals, saving fields, AI Writer hand-offs,
 * internal link suggestions, the change log and undo.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Onpage\Link_Suggestions;
use QuantumCloud\SeoAiSearch\Onpage\Meta_Fixer;
use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /fixes, /links/suggest, /changes.
 */
final class Fixes_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$post_arg = array(
			'post_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			self::NS,
			'/fixes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_pages' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'severity' => array(
						'type'    => 'string',
						'enum'    => array( '', 'high', 'medium', 'low' ),
						'default' => '',
					),
					'limit'    => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/fixes/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					$job = Audit::start_scan();
					return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => $job ) );
				},
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			self::NS,
			'/fixes/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'page' ),
				'permission_callback' => array( $this, 'can_view_post' ),
				'args'                => $post_arg,
			)
		);
		register_rest_route(
			self::NS,
			'/fixes/(?P<post_id>\d+)/propose',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'propose' ),
				'permission_callback' => array( $this, 'can_use_post' ),
				'args'                => $post_arg,
			)
		);
		register_rest_route(
			self::NS,
			'/fixes/(?P<post_id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( $this, 'can_change_post' ),
				'args'                => array_merge(
					$post_arg,
					array(
						'field' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => Seo_Bridge::FIELDS,
						),
						'value' => array(
							'required' => true,
						),
					)
				),
			)
		);
		register_rest_route(
			self::NS,
			'/fixes/(?P<post_id>\d+)/writer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'writer' ),
				'permission_callback' => array( $this, 'can_use_post' ),
				'args'                => array_merge(
					$post_arg,
					array(
						'issue' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					)
				),
			)
		);
		register_rest_route(
			self::NS,
			'/links/suggest/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return rest_ensure_response( Link_Suggestions::suggest( (int) $request['post_id'] ) );
				},
				'permission_callback' => array( $this, 'can_view_post' ),
				'args'                => $post_arg,
			)
		);
		register_rest_route(
			self::NS,
			'/links/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_link' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'source_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'target_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'anchor'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/changes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return rest_ensure_response(
						Change_Log::query(
							array(
								'post_id' => (int) $request->get_param( 'post_id' ),
								'kind'    => (string) $request->get_param( 'kind' ),
								'limit'   => (int) $request->get_param( 'limit' ),
								'offset'  => (int) $request->get_param( 'offset' ),
							)
						)
					);
				},
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'post_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'kind'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
					'offset'  => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/changes/(?P<id>\d+)/undo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$done = Change_Log::undo( (int) $request['id'] );
					return $this->respond( is_wp_error( $done ) ? $done : Change_Log::get( (int) $request['id'] ) );
				},
				'permission_callback' => array( $this, 'can_publish' ),
			)
		);
	}

	/**
	 * View level and read access to the post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_view_post( WP_REST_Request $request ) {
		$post = get_post( (int) $request['post_id'] );
		return $this->can_view() && $post && ( current_user_can( 'edit_post', $post->ID ) || 'publish' === $post->post_status );
	}

	/**
	 * Use level and edit rights on the post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_use_post( WP_REST_Request $request ) {
		return $this->can_use() && current_user_can( 'edit_post', (int) $request['post_id'] );
	}

	/**
	 * Saving a field: edit rights; a published page needs the Publish level.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_change_post( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		if ( ! $this->can_use_post( $request ) ) {
			return false;
		}
		return 'publish' !== get_post_status( $post_id ) || $this->can_publish();
	}

	/**
	 * Pages with stored audits, weakest first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_pages( WP_REST_Request $request ) {
		global $wpdb;
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", Audit::META ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reading this module's audits.
		$severity = (string) $request->get_param( 'severity' );
		// Page Audit (F3): filter by one check (issue id) or by score band.
		$only   = sanitize_key( (string) $request->get_param( 'issue' ) );
		$band   = sanitize_key( (string) $request->get_param( 'band' ) );
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$pages  = array();
		$groups = array(
			'high'   => 0,
			'medium' => 0,
			'low'    => 0,
		);
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$audit   = json_decode( (string) $row['meta_value'], true );
			$post    = get_post( $post_id );
			if ( ! is_array( $audit ) || ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$issues = (array) $audit['issues'];
			foreach ( $issues as $issue ) {
				if ( isset( $groups[ $issue['severity'] ] ) ) {
					++$groups[ $issue['severity'] ];
				}
			}
			if ( '' !== $type && $post->post_type !== $type ) {
				continue;
			}
			if ( '' !== $band && Audit_Controller::band( (int) $audit['score'] ) !== $band ) {
				continue;
			}
			if ( '' !== $only ) {
				$issues = array_values(
					array_filter(
						$issues,
						static function ( $issue ) use ( $only ) {
							return $issue['id'] === $only;
						}
					)
				);
			}
			if ( '' !== $severity ) {
				$issues = array_values(
					array_filter(
						$issues,
						static function ( $issue ) use ( $severity ) {
							return $issue['severity'] === $severity;
						}
					)
				);
			}
			if ( array() === $issues && '' === $band ) {
				continue;
			}
			$pages[] = array(
				'post_id'    => $post_id,
				'title'      => get_the_title( $post_id ),
				'url'        => (string) get_permalink( $post_id ),
				'edit_link'  => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
				'score'      => (int) $audit['score'],
				'issues'     => $issues,
				'checked_at' => isset( $audit['checked_at'] ) ? (string) $audit['checked_at'] : '',
				'can_edit'   => current_user_can( 'edit_post', $post_id ),
			);
		}
		usort(
			$pages,
			static function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);
		return rest_ensure_response(
			array(
				'pages'     => array_slice( $pages, 0, (int) $request->get_param( 'limit' ) ),
				'total'     => count( $pages ),
				'groups'    => $groups,
				'seoPlugin' => Seo_Plugins::label(),
				'writer'    => Writer_Bridge::available(),
			)
		);
	}

	/**
	 * One page: fresh audit and current search fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function page( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$audit   = current_user_can( 'edit_post', $post_id ) ? Audit::audit( $post_id, 'refresh' ) : Audit::stored( $post_id );
		return rest_ensure_response(
			array(
				'post_id'   => $post_id,
				'title'     => get_the_title( $post_id ),
				'audit'     => $audit,
				'fields'    => current_user_can( 'edit_post', $post_id ) ? Seo_Bridge::read( $post_id ) : null,
				'seoPlugin' => Seo_Plugins::label(),
				'changes'   => Change_Log::query(
					array(
						'post_id' => $post_id,
						'limit'   => 10,
					)
				)['items'],
			)
		);
	}

	/**
	 * AI proposal for the search title and description (nothing saved).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function propose( WP_REST_Request $request ) {
		$result = Meta_Fixer::propose( (int) $request['post_id'] );
		if ( is_wp_error( $result ) ) {
			return $this->respond( $result );
		}
		unset( $result['usage'] );
		$result['current'] = Seo_Bridge::read( (int) $request['post_id'] );
		$result['health']  = \QuantumCloud\WPBotCore\AI\Key_Health::notices( Module::ID );
		return rest_ensure_response( $result );
	}

	/**
	 * Save one search field through the SEO plugin and re-check the page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$change  = Seo_Bridge::set( $post_id, (string) $request->get_param( 'field' ), $request->get_param( 'value' ), 'fix' );
		if ( is_wp_error( $change ) ) {
			return $this->respond( $change );
		}
		return rest_ensure_response(
			array(
				'change_id' => (int) $change,
				'fields'    => Seo_Bridge::read( $post_id ),
				'audit'     => Audit::audit( $post_id, 'fix' ),
			)
		);
	}

	/**
	 * Open AI Writer's Improve flow with the issue filled in.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function writer( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$audit   = Audit::stored( $post_id );
		$issue   = null;
		foreach ( $audit ? (array) $audit['issues'] : array() as $item ) {
			if ( $item['id'] === $request->get_param( 'issue' ) ) {
				$issue = $item;
			}
		}
		if ( ! $issue ) {
			return new WP_Error( 'qcld_wpbot_seo_no_issue', __( 'This issue is not on the page any more.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$url = Writer_Bridge::brief_url(
			array(
				'post_id' => $post_id,
				'query'   => isset( $audit['main_query'] ) ? (string) $audit['main_query'] : '',
				'reason'  => $issue['message'] . ' ' . $issue['why'],
				'numbers' => array(),
				'title'   => get_the_title( $post_id ),
				'url'     => (string) get_permalink( $post_id ),
			),
			'improve'
		);
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Add one suggested internal link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_link( WP_REST_Request $request ) {
		$source = (int) $request->get_param( 'source_id' );
		if ( ! current_user_can( 'edit_post', $source ) || ( 'publish' === get_post_status( $source ) && ! $this->can_publish() ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot change this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$change = Link_Suggestions::add_link( $source, (int) $request->get_param( 'target_id' ), (string) $request->get_param( 'anchor' ) );
		return $this->respond( is_wp_error( $change ) ? $change : array( 'change_id' => (int) $change ) );
	}
}
