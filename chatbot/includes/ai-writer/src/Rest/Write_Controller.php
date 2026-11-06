<?php
/**
 * REST routes of the "Write something new" flow, source suggestions,
 * document uploads and the pre-filled brief lookup.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Knowledge\Chatbot_Knowledge;
use QuantumCloud\AiWriter\Knowledge\Documents;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Writing\Article_Job;
use QuantumCloud\AiWriter\Writing\Brief;
use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/write/*, /documents, /knowledge
 */
final class Write_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns    = Settings_Controller::NAMESPACE_V1;
		$brief = array(
			'brief' => array(
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => static function ( $value ) {
					return is_array( $value );
				},
			),
		);
		$id    = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);

		foreach ( array( 'estimate', 'questions', 'start', 'duplicates' ) as $action ) {
			register_rest_route(
				$ns,
				'/write/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => $brief,
				)
			);
		}
		// CR-16 F2: the "Real data" step: sources for the brief's format, then the facts found.
		register_rest_route(
			$ns,
			'/write/data/sources',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'data_sources' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => $brief,
			)
		);
		register_rest_route(
			$ns,
			'/write/data/gather',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'data_gather' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array_merge(
					$brief,
					array(
						'sources' => array(
							'type'              => 'array',
							'required'          => true,
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => static function ( $value ) {
								return array_slice( array_map( 'sanitize_key', (array) $value ), 0, 20 );
							},
						),
					)
				),
			)
		);
		// CR-16: the format AI Writer suggests for a topic (rules only, no AI call).
		register_rest_route(
			$ns,
			'/write/formats/suggest',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'suggest_format' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'topic'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'intent' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'type'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			$ns,
			'/write/jobs/(?P<id>\d+)/outline',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_outline' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array_merge(
					$id,
					array(
						'outline' => array(
							'type'              => 'object',
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_array( $value );
							},
						),
					)
				),
			)
		);
		// CR-10: the finished article on the Write page (read, edit, images).
		$draft_id = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			$ns,
			'/write/draft/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'draft' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => $draft_id,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_draft' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => array_merge(
						$draft_id,
						array(
							'title'   => array(
								'type'    => 'string',
								'default' => '',
							),
							'content' => array(
								'type'     => 'string',
								'required' => true,
							),
						)
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/draft/(?P<id>\d+)/image',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'draft_image' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array_merge(
					$draft_id,
					array(
						'spot'          => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return 'featured' === $value || ctype_digit( (string) $value );
							},
						),
						'attachment_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);
		// CR-14: write the outline again (same or changed options) before writing starts.
		register_rest_route(
			$ns,
			'/write/jobs/(?P<id>\d+)/outline/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate_outline' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array(
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		// CR-9: options pre-filled from free sources; "Suggest with AI" on click.
		// Round 3: the detected persuasion choices of a piece (no AI call).
		register_rest_route(
			$ns,
			'/write/persuasion',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'persuasion' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'type'       => array(
						'type'              => 'string',
						'default'           => 'blog_post',
						'sanitize_callback' => 'sanitize_key',
					),
					'persuasion' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'intent'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'idea_id'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'vault_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
		// Round 3 P3: Compare techniques (estimate first, then generate) and use or mix a version.
		register_rest_route(
			$ns,
			'/persuasion/compare',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compare' ),
				'permission_callback' => array( $this, 'can_compare' ),
				'args'                => array(
					'step'       => array(
						'type'    => 'string',
						'enum'    => array( 'estimate', 'generate' ),
						'default' => 'estimate',
					),
					'kind'       => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'techniques' => array(
						'default'           => 'suggest',
						'validate_callback' => static function ( $value ) {
							return 'suggest' === $value || is_array( $value );
						},
					),
					'source'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'title'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'content'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'post_id'    => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'product_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'network'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'notes'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'stage'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'lab'        => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'per'        => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		// CR-14: one compared version again.
		register_rest_route(
			$ns,
			'/persuasion/compare/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compare_regenerate' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'         => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'version'    => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/persuasion/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compare_apply' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'version' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'parts'   => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/prefill',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'prefill' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'topic'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'main_query' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/prefill/ai',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'prefill_ai' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'topic' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		// CR-8: "Who ranks for this search?" (Free since 0.15; uses the site's AI key with web search).
		register_rest_route(
			$ns,
			'/write/who-ranks',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'who_ranks' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && '' !== trim( $value ) && strlen( $value ) <= 191;
						},
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sources' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/brief/(?P<token>[a-z0-9]{8,40})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'brief_by_token' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/documents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_documents' ),
					'permission_callback' => array( $this, 'can_use' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_document' ),
					'permission_callback' => array( $this, 'can_upload' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/recent',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'recent' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/knowledge',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'knowledge' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
	}

	/**
	 * CR-16 F2: the sources the brief's format can draw on, with the cost.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function data_sources( WP_REST_Request $request ) {
		return rest_ensure_response( \QuantumCloud\AiWriter\Formats\Gather::sources( Brief::sanitise( (array) $request['brief'] ) ) );
	}

	/**
	 * CR-16 F2: facts from the ticked sources (nothing is saved; the owner ticks what to use).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function data_gather( WP_REST_Request $request ) {
		$brief = Brief::sanitise( (array) $request['brief'] );
		if ( '' === $brief['format'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_format', __( 'Choose a format first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( \QuantumCloud\AiWriter\Formats\Gather::collect( $brief, (array) $request['sources'] ) );
	}

	/**
	 * CR-16: the suggested format for a topic, or null, and (WPBot Free) a
	 * Pro format that would fit better.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function suggest_format( WP_REST_Request $request ) {
		$suggestion = \QuantumCloud\AiWriter\Formats\Formats::suggesting()
			? \QuantumCloud\AiWriter\Formats\Formats::suggest( (string) $request['topic'], (string) $request['intent'], (string) $request['type'] )
			: null;
		// WPBot Free: a Pro format that fits better, only while Pro previews show.
		$hint = \QuantumCloud\AiWriter\Formats\Formats::suggesting() && array() !== \QuantumCloud\AiWriter\Admin\Pro_Previews::items()
			? \QuantumCloud\AiWriter\Formats\Formats::hint( (string) $request['topic'] )
			: null;
		return rest_ensure_response(
			array(
				'suggestion' => $suggestion,
				'hint'       => $hint,
			)
		);
	}

	/**
	 * Use level.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * Use level plus the right to upload files.
	 *
	 * @return bool
	 */
	public function can_upload() {
		return $this->can_use() && current_user_can( 'upload_files' );
	}

	/**
	 * Owner of the job with Use, or anyone with Settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_access_job( WP_REST_Request $request ) {
		$job = Job_Store::get( (int) $request['id'] );
		if ( ! $job || Module::ID !== $job['module'] ) {
			return false;
		}
		return current_user_can( Module::cap( 'settings' ) ) || ( get_current_user_id() === (int) $job['user_id'] && $this->can_use() );
	}

	/**
	 * POST estimate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function estimate( WP_REST_Request $request ) {
		return rest_ensure_response( Article_Job::estimate( Brief::sanitise( (array) $request['brief'] ) ) );
	}

	/**
	 * POST duplicate check: pages (or planned pages) that already target
	 * the brief's search (brief 6.2).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function duplicates( WP_REST_Request $request ) {
		$brief = Brief::sanitise( (array) $request['brief'] );
		return rest_ensure_response(
			array(
				'matches' => \QuantumCloud\AiWriter\Writing\Duplicate_Check::find(
					$brief['topic'],
					$brief['main_query'],
					array(
						'post_id' => $brief['target_post'],
						'idea_id' => $brief['idea_id'],
					)
				),
			)
		);
	}

	/**
	 * POST questions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function questions( WP_REST_Request $request ) {
		$brief = Brief::sanitise( (array) $request['brief'] );
		if ( '' === $brief['topic'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_topic', __( 'Write the topic in one sentence first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$questions = Article_Job::questions( $brief );
		if ( is_wp_error( $questions ) ) {
			return Rest_Errors::with_status( $questions );
		}
		return rest_ensure_response( array( 'questions' => $questions ) );
	}

	/**
	 * POST start.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$brief = Brief::sanitise( (array) $request['brief'] );
		$type  = get_post_type_object( $brief['post_type'] );
		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_create', __( 'You are not allowed to create this kind of content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( $brief['target_post'] && ! current_user_can( 'edit_post', $brief['target_post'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		// Sources: only posts this user can read; only their own documents unless they manage settings.
		$brief['sources']['post_ids']     = array_values(
			array_filter(
				$brief['sources']['post_ids'],
				static function ( $post_id ) {
					return current_user_can( 'read_post', $post_id );
				}
			)
		);
		$brief['sources']['document_ids'] = array_values(
			array_filter(
				$brief['sources']['document_ids'],
				static function ( $document_id ) {
					$document = Documents::get( $document_id, false );
					return $document && ( get_current_user_id() === $document['created_by'] || current_user_can( Module::cap( 'settings' ) ) );
				}
			)
		);
		$needed                           = (int) \QuantumCloud\AiWriter\Writing\Content_Types::get( $brief['type'] )['products'];
		if ( $needed > 0 ) {
			$products = array_filter(
				$brief['sources']['post_ids'],
				static function ( $post_id ) {
					return 'product' === get_post_type( $post_id );
				}
			);
			if ( count( $products ) < $needed ) {
				/* translators: %d: number of products. */
				return new WP_Error( 'qcld_wpbot_writer_need_products', sprintf( __( 'Choose at least %d products as sources for this type.', 'chatbot' ), $needed ), array( 'status' => 400 ) );
			}
		}
		$job_id = Article_Job::start( $brief );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}
		if ( $brief['target_post'] ) {
			// The edit screen offers the result again if the owner left before it was inserted.
			update_post_meta( $brief['target_post'], '_qcld_wpbot_writer_pending_job', (int) $job_id );
		}
		return rest_ensure_response( $this->job_view( $job_id ) );
	}

	/**
	 * GET job with the flow's data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_job( WP_REST_Request $request ) {
		return rest_ensure_response( $this->job_view( (int) $request['id'] ) );
	}

	/**
	 * POST approve the outline.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function approve_outline( WP_REST_Request $request ) {
		$result = Article_Job::approve_outline( (int) $request['id'], (array) $request['outline'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $this->job_view( (int) $request['id'] ) );
	}

	/**
	 * GET published posts, pages and products to use as sources.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function sources( WP_REST_Request $request ) {
		$types = array_values( array_intersect( array( 'post', 'page', 'product' ), get_post_types( array( 'public' => true ) ) ) );
		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				's'              => (string) $request['search'],
				'posts_per_page' => 12,
				'orderby'        => '' === (string) $request['search'] ? 'modified' : 'relevance',
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$out[] = array(
					'id'    => (int) $post->ID,
					'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'type'  => $post->post_type,
					'url'   => get_permalink( $post ),
				);
			}
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET the finished article.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function draft( WP_REST_Request $request ) {
		$view = \QuantumCloud\AiWriter\Writing\Draft_View::get( (int) $request['id'] );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
	}

	/**
	 * POST an edit made on the Write page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_draft( WP_REST_Request $request ) {
		$view = \QuantumCloud\AiWriter\Writing\Draft_View::save( (int) $request['id'], (string) $request['title'], (string) $request['content'] );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
	}

	/**
	 * POST an image for the featured image or an image spot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function draft_image( WP_REST_Request $request ) {
		$spot = 'featured' === $request['spot'] ? 'featured' : (int) $request['spot'];
		$view = \QuantumCloud\AiWriter\Writing\Draft_View::set_image( (int) $request['id'], $spot, (int) $request['attachment_id'] );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
	}

	/**
	 * POST write the outline again (CR-14).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function regenerate_outline( WP_REST_Request $request ) {
		$result = Article_Job::regenerate_outline( (int) $request['id'], \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] ) );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST the detected persuasion choices of a piece (round 3; no AI call).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function persuasion( WP_REST_Request $request ) {
		$choices = \QuantumCloud\AiWriter\Persuasion\Choices::resolve(
			(array) $request['persuasion'],
			(string) $request['type'],
			array(
				'intent'   => (string) $request['intent'],
				'idea_id'  => (int) $request['idea_id'],
				'vault_id' => (int) $request['vault_id'],
			)
		);
		return rest_ensure_response(
			array(
				'choices' => $choices,
				'labels'  => \QuantumCloud\AiWriter\Persuasion\Choices::labels( $choices ),
			)
		);
	}

	/**
	 * Use permission, plus edit rights on the post or product being compared.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_compare( WP_REST_Request $request ) {
		if ( ! $this->can_use() ) {
			return false;
		}
		foreach ( array( 'post_id', 'product_id' ) as $key ) {
			$id = absint( $request[ $key ] );
			if ( $id && ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * POST Compare techniques: the cost estimate (no AI call), or the versions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function compare( WP_REST_Request $request ) {
		$req = \QuantumCloud\AiWriter\Persuasion\Compare::sanitise( $request->get_params() );
		if ( is_wp_error( $req ) ) {
			return $req;
		}
		if ( 'generate' !== $request['step'] ) {
			$techniques = array();
			foreach ( $req['techniques'] as $id ) {
				$techniques[] = \QuantumCloud\AiWriter\Persuasion\Choices::view( \QuantumCloud\WPBotCore\Persuasion\Techniques::get( $id ) );
			}
			return rest_ensure_response(
				array(
					'estimate'   => \QuantumCloud\AiWriter\Persuasion\Compare::estimate( $req ),
					'techniques' => $techniques,
					'suggested'  => $req['suggested'],
					'skipped'    => $req['skipped'],
				)
			);
		}
		$result = \QuantumCloud\AiWriter\Persuasion\Compare::generate( $req );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST one compared version again (CR-14).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function compare_regenerate( WP_REST_Request $request ) {
		$result = \QuantumCloud\AiWriter\Persuasion\Compare::regenerate_one( (string) $request['id'], (int) $request['version'], \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] ) );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( array( 'version' => $result ) );
	}

	/**
	 * POST use one compared version, or mix parts (Pro).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function compare_apply( WP_REST_Request $request ) {
		$parts  = array_map( 'absint', array_intersect_key( (array) $request['parts'], array_flip( \QuantumCloud\AiWriter\Persuasion\Compare::ROLES ) ) );
		$result = \QuantumCloud\AiWriter\Persuasion\Compare::apply(
			(string) $request['id'],
			array() !== $parts ? array( 'parts' => $parts ) : array( 'version' => (int) $request['version'] )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * GET prefill suggestions for the brief's options (free sources).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prefill( WP_REST_Request $request ) {
		return rest_ensure_response(
			\QuantumCloud\AiWriter\Writing\Brief_Prefill::suggest(
				(string) $request['topic'],
				array(
					'main_query' => (string) $request['main_query'],
					'type'       => (string) $request['type'],
				)
			)
		);
	}

	/**
	 * POST "Suggest with AI" for the brief's empty options.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function prefill_ai( WP_REST_Request $request ) {
		$result = \QuantumCloud\AiWriter\Writing\Brief_Prefill::suggest_with_ai( (string) $request['topic'], array( 'type' => (string) $request['type'] ) );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST who ranks for a search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function who_ranks( WP_REST_Request $request ) {
		$result = \QuantumCloud\AiWriter\Writing\Who_Ranks::lookup( (string) $request['query'] );
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			return empty( $data['status'] ) ? Rest_Errors::with_status( $result ) : $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * GET a pre-filled brief stored by qcld_wpbot_writer_open_brief_url().
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function brief_by_token( WP_REST_Request $request ) {
		$brief = get_transient( 'qcld_wpbot_writer_brief_' . sanitize_key( $request['token'] ) );
		if ( ! is_array( $brief ) ) {
			return new WP_Error( 'qcld_wpbot_writer_brief_gone', __( 'This link has expired. Start a new piece.', 'chatbot' ), array( 'status' => 404 ) );
		}
		// Improve briefs name a post: only someone who may edit it sees it.
		if ( ! empty( $brief['post_id'] ) ) {
			if ( current_user_can( 'edit_post', (int) $brief['post_id'] ) ) {
				$brief['post_title'] = get_the_title( (int) $brief['post_id'] );
			} else {
				$brief['post_id'] = 0;
			}
		}
		// Titles of the pages to link to and from (only pages this user can see).
		foreach ( array( 'link_to', 'link_from' ) as $field ) {
			$brief[ $field . '_posts' ] = array();
			foreach ( isset( $brief[ $field ] ) ? (array) $brief[ $field ] : array() as $id ) {
				$page = get_post( (int) $id );
				if ( $page && current_user_can( 'read_post', $page->ID ) ) {
					$brief[ $field . '_posts' ][] = array(
						'id'    => (int) $page->ID,
						'title' => html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ),
					);
				}
			}
		}
		return rest_ensure_response( $brief );
	}

	/**
	 * GET documents (own; all for settings level).
	 *
	 * @return \WP_REST_Response
	 */
	public function list_documents() {
		return rest_ensure_response( Documents::recent( current_user_can( Module::cap( 'settings' ) ) ? 0 : get_current_user_id() ) );
	}

	/**
	 * POST upload a document (multipart field "file").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function upload_document( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_file', __( 'Choose a file to upload.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$document = Documents::upload( $files['file'], (int) Settings::get( 'upload_limit_mb' ) );
		return is_wp_error( $document ) ? $document : rest_ensure_response( $document );
	}

	/**
	 * GET recent drafts written with AI Writer that the user may edit.
	 *
	 * @return \WP_REST_Response
	 */
	public function recent() {
		$query = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => array( 'draft', 'pending', 'future', 'publish' ),
			'posts_per_page' => 8,
			'meta_key'       => '_qcld_wpbot_writer_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small, admin-only list.
			'orderby'        => 'modified',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query['author'] = get_current_user_id();
		}
		$out = array();
		foreach ( get_posts( $query ) as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$out[] = array(
					'id'       => (int) $post->ID,
					'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'status'   => $post->post_status,
					'edit_url' => (string) get_edit_post_link( $post->ID, 'raw' ),
					'modified' => get_post_modified_time( 'c', true, $post ),
				);
			}
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET whether ChatBot knowledge can be used.
	 *
	 * @return \WP_REST_Response
	 */
	public function knowledge() {
		return rest_ensure_response(
			array(
				'available' => Chatbot_Knowledge::available(),
				'default'   => (bool) Settings::get( 'chatbot_source' ),
			)
		);
	}

	/**
	 * Job progress plus the flow's payload (no internal data).
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	private function job_view( $job_id ) {
		$progress = Job_Runner::progress( $job_id );
		$job      = Job_Store::get( $job_id );
		$payload  = $job ? $job['payload'] : array();
		return array(
			'job'      => $progress,
			'awaiting' => isset( $payload['awaiting'] ) ? (string) $payload['awaiting'] : '',
			'outline'  => isset( $payload['outline'] ) ? $payload['outline'] : null,
			'result'   => isset( $payload['result'] ) ? $payload['result'] : null,
			'topic'    => isset( $payload['brief']['topic'] ) ? $payload['brief']['topic'] : '',
		);
	}
}
