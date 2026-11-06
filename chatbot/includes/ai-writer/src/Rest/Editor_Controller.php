<?php
/**
 * REST routes for the edit screens (block and Classic Editor), the image
 * panel and Quick write.
 *
 * Every route needs the AI Writer "Use" level. Routes about a post also need
 * the right to edit that post; image imports need upload_files. All AI
 * routes return previews: nothing here saves a post.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Checks\Ai_Pattern_Check;
use QuantumCloud\AiWriter\Checks\Voice_Check;
use QuantumCloud\AiWriter\Editor\Editor_Actions;
use QuantumCloud\AiWriter\Images\Ai_Image;
use QuantumCloud\AiWriter\Images\Image_Check;
use QuantumCloud\AiWriter\Images\Image_Importer;
use QuantumCloud\AiWriter\Images\Image_Search;
use QuantumCloud\AiWriter\Images\Unsplash;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Quick\Quick_Write;
use QuantumCloud\AiWriter\Quick\Templates;
use QuantumCloud\AiWriter\Seo\Seo_Adapter;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Voice\Voices;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/editor/*, /images/*, /quick/*
 */
final class Editor_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns      = Settings_Controller::NAMESPACE_V1;
		$context = array(
			'post_id'    => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'title'      => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'again'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			// CR-14: options of a regeneration (with again) and the piece's history slot.
			'regenerate' => array(
				'type'    => 'object',
				'default' => array(),
			),
			'slot'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);

		$actions = array(
			'titles'     => array(),
			'excerpt'    => array(),
			'meta'       => array( 'focus' => self::text_arg() ),
			'faq'        => array(),
			'summary'    => array(),
			'transform'  => array(
				'html'   => array(
					'type'     => 'string',
					'required' => true,
				),
				'action' => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array_keys( Editor_Actions::transforms() ),
				),
				'option' => self::text_arg(),
			),
			'write-here' => array(
				'prompt'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'mode'     => array(
					'type'    => 'string',
					'default' => 'write',
					'enum'    => array( 'write', 'continue', 'rewrite' ),
				),
				'before'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'after'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'selected' => array(
					'type'    => 'string',
					'default' => '',
				),
				'format'   => array(
					'type'    => 'string',
					'default' => 'blocks',
					'enum'    => array( 'blocks', 'html' ),
				),
			),
			'alt'        => array( 'images' => self::list_arg() ),
			'chat'       => array( 'messages' => self::list_arg() ),
			'variations' => array(
				'kind' => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array( 'headline', 'hook', 'cta', 'meta' ),
				),
			),
			'rewrite'    => array( 'items' => self::list_arg() ),
			'check'      => array(
				'blocks' => array(
					'type'    => 'object',
					'default' => array(),
				),
			),
		);
		foreach ( $actions as $action => $args ) {
			register_rest_route(
				$ns,
				'/editor/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'action' ),
					'permission_callback' => array( $this, 'can_edit_target' ),
					'args'                => array_merge( $context, $args ),
				)
			);
		}

		register_rest_route(
			$ns,
			'/editor/seo',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'seo' ),
				'permission_callback' => array( $this, 'can_edit_target' ),
				'args'                => array( 'post_id' => $context['post_id'] ),
			)
		);
		register_rest_route(
			$ns,
			'/editor/pending-job',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_pending_job' ),
				'permission_callback' => array( $this, 'can_edit_target' ),
				'args'                => array( 'post_id' => $context['post_id'] ),
			)
		);

		$images = array(
			'search'   => array(
				'source' => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array( 'media', 'openverse', 'pexels', 'pixabay', 'unsplash' ),
				),
				'query'  => self::text_arg(),
			),
			'featured' => array( 'query' => self::text_arg() ),
			'import'   => array(
				'candidate' => array(
					'type'     => 'object',
					'required' => true,
				),
				'alt'       => self::text_arg(),
			),
			'ai'       => array(
				'prompt' => self::text_arg(),
				'alt'    => self::text_arg(),
			),
			'unsplash' => array( 'download' => self::text_arg() ),
			'check'    => array( 'ids' => self::list_arg() ),
		);
		foreach ( $images as $action => $args ) {
			register_rest_route(
				$ns,
				'/images/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'images' ),
					'permission_callback' => in_array( $action, array( 'import', 'ai' ), true ) ? array( $this, 'can_upload' ) : array( $this, 'can_edit_target' ),
					'args'                => array_merge( array( 'post_id' => $context['post_id'] ), $args ),
				)
			);
		}

		register_rest_route(
			$ns,
			'/quick/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'quick_templates' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/quick/items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'quick_items' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		$quick = array(
			'template'    => array(
				'type'     => 'string',
				'required' => true,
			),
			'source_post' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			$ns,
			'/quick/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quick_generate' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array_merge(
					$quick,
					array(
						'fields' => array(
							'type'    => 'object',
							'default' => array(),
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/quick/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quick_save' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array_merge(
					$quick,
					array(
						'text' => array(
							'type'     => 'string',
							'required' => true,
						),
					)
				),
			)
		);
	}

	/**
	 * Short text argument.
	 *
	 * @return array
	 */
	private static function text_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * List argument (items sanitised by the action).
	 *
	 * @return array
	 */
	private static function list_arg() {
		return array(
			'type'              => 'array',
			'default'           => array(),
			'validate_callback' => static function ( $value ) {
				return is_array( $value ) && count( $value ) <= 50;
			},
		);
	}

	/**
	 * AI Writer Use level.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * Use level, plus edit rights on the post when one is given.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_target( WP_REST_Request $request ) {
		if ( ! $this->can_use() ) {
			return false;
		}
		$post_id = absint( $request['post_id'] );
		return $post_id ? current_user_can( 'edit_post', $post_id ) : true;
	}

	/**
	 * Edit rights plus the right to upload files.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_upload( WP_REST_Request $request ) {
		return $this->can_edit_target( $request ) && current_user_can( 'upload_files' );
	}

	/**
	 * POST /editor/{action}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function action( WP_REST_Request $request ) {
		$action       = substr( (string) $request->get_route(), strrpos( (string) $request->get_route(), '/' ) + 1 );
		$ctx          = Editor_Actions::context( (int) $request['post_id'], (string) $request['title'], (string) $request['content'] );
		$ctx['cache'] = ! $request['again'];
		$options      = array();
		if ( $request['again'] ) {
			$options      = \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] );
			$ctx['regen'] = $options;
		}

		switch ( $action ) {
			case 'titles':
				$result = Editor_Actions::titles( $ctx );
				break;
			case 'excerpt':
				$result = Editor_Actions::excerpt( $ctx );
				break;
			case 'meta':
				$result = Editor_Actions::meta( $ctx, (string) $request['focus'] );
				break;
			case 'faq':
				$result = Editor_Actions::faq( $ctx );
				break;
			case 'summary':
				$result = Editor_Actions::summary( $ctx );
				break;
			case 'transform':
				$result = Editor_Actions::transform( (string) $request['html'], (string) $request['action'], (string) $request['option'], $ctx );
				break;
			case 'write-here':
				$result = Editor_Actions::write_here(
					(string) $request['prompt'],
					(string) $request['mode'],
					array(
						'before'   => (string) $request['before'],
						'after'    => (string) $request['after'],
						'selected' => (string) $request['selected'],
					),
					'blocks' === $request['format'],
					$ctx
				);
				break;
			case 'alt':
				$result = Editor_Actions::alt_text( array_filter( (array) $request['images'], 'is_array' ), $ctx );
				break;
			case 'chat':
				$result = Editor_Actions::chat( array_filter( (array) $request['messages'], 'is_array' ), $ctx );
				break;
			case 'variations':
				$result = Editor_Actions::variations( (string) $request['kind'], $ctx );
				break;
			case 'rewrite':
				$result = Editor_Actions::rewrite_sentences( array_filter( (array) $request['items'], 'is_array' ), $ctx );
				break;
			case 'check':
				$result = $this->check( (array) $request['blocks'], (int) $request['post_id'] );
				break;
			default:
				$result = new WP_Error( 'qcld_wpbot_writer_bad_action', __( 'Unknown action.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( is_wp_error( $result ) ) {
			return Rest_Errors::with_status( $result );
		}
		// CR-14: keep the version (the first write too) so the owner can flip back.
		if ( '' !== (string) $request['slot'] && is_array( $result ) && ! in_array( $action, array( 'check', 'chat' ), true ) ) {
			$result['versions'] = \QuantumCloud\AiWriter\Regenerate\History::add( (int) $request['post_id'], (string) $request['slot'], $result, $options );
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Rule-based checks for the live checklist: AI patterns, voice and
	 * style guide. No AI call.
	 *
	 * @param array $raw     Block client id => plain text.
	 * @param int   $post_id Post (for persuasion and honesty; 0 for none).
	 * @return array patterns, voice, score, persuasion, honesty.
	 */
	private function check( array $raw, $post_id = 0 ) {
		$blocks = array();
		foreach ( array_slice( $raw, 0, 400, true ) as $id => $text ) {
			$id = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $id );
			if ( '' !== $id && is_scalar( $text ) ) {
				$blocks[ $id ] = sanitize_textarea_field( (string) $text );
			}
		}
		$patterns = Ai_Pattern_Check::analyse( array_values( $blocks ), array(), (array) Settings::get( 'cliches' ) );
		foreach ( $patterns['flags'] as $index => $flag ) {
			$patterns['flags'][ $index ]['id'] = '';
			if ( '' !== $flag['sentence'] ) {
				foreach ( $blocks as $id => $text ) {
					if ( false !== strpos( $text, $flag['sentence'] ) ) {
						$patterns['flags'][ $index ]['id'] = $id;
						break;
					}
				}
			}
		}
		$voice = Voices::site_voice();
		// Round 3: persuasion checks and honesty flags (flags cannot be hidden).
		$persuasion = array();
		$honesty    = array();
		if ( \QuantumCloud\AiWriter\Persuasion\Persuasion::available() ) {
			$post       = $post_id ? get_post( $post_id ) : null;
			$choices    = $post_id ? get_post_meta( $post_id, \QuantumCloud\AiWriter\Persuasion\Choices::META, true ) : array();
			$type       = $post ? ( 'product' === $post->post_type ? 'product' : ( 'page' === $post->post_type ? 'page' : 'blog_post' ) ) : '';
			$record     = \QuantumCloud\AiWriter\Persuasion\Honesty_Record::get( $post_id );
			$context    = \QuantumCloud\AiWriter\Persuasion\Context::honesty( $record['facts'], 'product' === $type ? $post_id : 0 );
			$persuasion = \QuantumCloud\AiWriter\Persuasion\Persuasion_Checks::analyse( array_values( $blocks ), $type, is_array( $choices ) ? $choices : array(), $context['kit'] );
			$honesty    = qcld_wpbot_core_honesty_check( implode( "\n", $blocks ), $context );
		}
		return array(
			'persuasion' => $persuasion,
			'honesty'    => $honesty,
			'patterns'   => $patterns['flags'],
			'score'      => $patterns['score'],
			'voice'      => Voice_Check::analyse(
				$blocks,
				$voice ? (array) $voice['profile'] : array(),
				array(
					'avoid'    => (array) Settings::get( 'style_avoid_words' ),
					'names'    => (array) Settings::get( 'style_product_names' ),
					'spelling' => (string) Settings::get( 'spelling' ),
					'level'    => (string) Settings::get( 'reading_level' ),
				)
			),
		);
	}

	/**
	 * GET /editor/seo: where meta goes and what it is now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function seo( WP_REST_Request $request ) {
		$post_id          = (int) $request['post_id'];
		$current          = $post_id ? Seo_Adapter::read( $post_id ) : array(
			'title'       => '',
			'description' => '',
			'plugin'      => Seo_Adapter::active(),
		);
		$current['label'] = Seo_Adapter::active_label();
		return rest_ensure_response( $current );
	}

	/**
	 * DELETE /editor/pending-job: the result was inserted or dismissed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function clear_pending_job( WP_REST_Request $request ) {
		delete_post_meta( (int) $request['post_id'], '_qcld_wpbot_writer_pending_job' );
		return rest_ensure_response( array( 'cleared' => true ) );
	}

	/**
	 * POST /images/{action}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function images( WP_REST_Request $request ) {
		$action  = substr( (string) $request->get_route(), strrpos( (string) $request->get_route(), '/' ) + 1 );
		$post_id = (int) $request['post_id'];
		switch ( $action ) {
			case 'search':
				$source = (string) $request['source'];
				if ( 'unsplash' === $source ) {
					$result = Unsplash::search( (string) $request['query'] );
				} elseif ( in_array( $source, array( 'pexels', 'pixabay' ), true ) && ! in_array( $source, Image_Search::stock_sources(), true ) ) {
					$result = new WP_Error( 'qcld_wpbot_writer_no_stock_key', __( 'Add a free key for this service under AI Writer > Settings > Images.', 'chatbot' ), array( 'status' => 400 ) );
				} else {
					$result = Image_Search::search( $source, (string) $request['query'], 9 );
				}
				$result = is_wp_error( $result ) ? $result : array( 'results' => $result );
				break;
			case 'featured':
				$result = array( 'results' => $this->featured( (string) $request['query'] ) );
				break;
			case 'import':
				$candidate = $this->candidate( (array) $request['candidate'] );
				if ( ! empty( $candidate['hotlink'] ) ) {
					$result = new WP_Error( 'qcld_wpbot_writer_hotlink_only', __( 'Unsplash images are used from Unsplash, not copied to the Media Library.', 'chatbot' ), array( 'status' => 400 ) );
					break;
				}
				$id     = Image_Importer::from_url( $candidate, (string) $request['alt'], $post_id );
				$result = is_wp_error( $id ) ? $id : $this->attachment( $id );
				break;
			case 'ai':
				if ( '' === trim( (string) $request['prompt'] ) ) {
					$result = new WP_Error( 'qcld_wpbot_writer_no_prompt', __( 'Describe the image first.', 'chatbot' ), array( 'status' => 400 ) );
					break;
				}
				$id     = Ai_Image::generate( (string) $request['prompt'], '' !== (string) $request['alt'] ? (string) $request['alt'] : (string) $request['prompt'], $post_id );
				$result = is_wp_error( $id ) ? $id : $this->attachment( $id );
				break;
			case 'unsplash':
				$result = Unsplash::track_download( (string) $request['download'] );
				$result = is_wp_error( $result ) ? $result : array( 'tracked' => true );
				break;
			case 'check':
				$result = array( 'images' => array_values( Image_Check::facts( $post_id, array_map( 'absint', (array) $request['ids'] ) ) ) );
				break;
			default:
				$result = new WP_Error( 'qcld_wpbot_writer_bad_action', __( 'Unknown action.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * Three featured image suggestions: stock first, then the Media Library.
	 * Unsplash is never offered as a featured image.
	 *
	 * @param string $query Terms (usually the title).
	 * @return array[]
	 */
	private function featured( $query ) {
		$found = array();
		foreach ( array_merge( Image_Search::stock_sources(), array( 'media' ) ) as $source ) {
			foreach ( Image_Search::search( $source, $query, 3 ) as $candidate ) {
				$found[] = $candidate;
				if ( count( $found ) >= 3 ) {
					return $found;
				}
			}
		}
		return $found;
	}

	/**
	 * Only known candidate fields, cleaned.
	 *
	 * @param array $raw Candidate from the screen.
	 * @return array
	 */
	private function candidate( array $raw ) {
		$source = isset( $raw['source'] ) ? sanitize_key( $raw['source'] ) : '';
		$out    = array(
			'source'      => $source,
			'url'         => isset( $raw['url'] ) ? esc_url_raw( (string) $raw['url'] ) : '',
			'credit'      => isset( $raw['credit'] ) ? sanitize_text_field( (string) $raw['credit'] ) : '',
			'license'     => isset( $raw['license'] ) ? sanitize_text_field( (string) $raw['license'] ) : '',
			'license_url' => isset( $raw['license_url'] ) ? esc_url_raw( (string) $raw['license_url'] ) : '',
			'source_url'  => isset( $raw['source_url'] ) ? esc_url_raw( (string) $raw['source_url'] ) : '',
			'hotlink'     => 'unsplash' === $source || ! empty( $raw['hotlink'] ),
		);
		if ( 'media' === $source && ! empty( $raw['attachment_id'] ) && current_user_can( 'read_post', absint( $raw['attachment_id'] ) ) ) {
			$out['attachment_id'] = absint( $raw['attachment_id'] );
		}
		return $out;
	}

	/**
	 * What the editor needs to insert an attachment.
	 *
	 * @param int $id Attachment id.
	 * @return array
	 */
	private function attachment( $id ) {
		return array(
			'id'      => (int) $id,
			'url'     => (string) wp_get_attachment_image_url( $id, 'large' ),
			'alt'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption' => (string) wp_get_attachment_caption( $id ),
		);
	}

	/**
	 * GET /quick/templates.
	 *
	 * @return \WP_REST_Response
	 */
	public function quick_templates() {
		return rest_ensure_response( array( 'templates' => Templates::public_view() ) );
	}

	/**
	 * GET /quick/items.
	 *
	 * @return \WP_REST_Response
	 */
	public function quick_items() {
		return rest_ensure_response( array( 'items' => Quick_Write::recent() ) );
	}

	/**
	 * POST /quick/generate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function quick_generate( WP_REST_Request $request ) {
		$result = Quick_Write::generate( sanitize_key( (string) $request['template'] ), (array) $request['fields'], (int) $request['source_post'] );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST /quick/save.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function quick_save( WP_REST_Request $request ) {
		$source = (int) $request['source_post'];
		if ( $source && ! current_user_can( 'read_post', $source ) ) {
			$source = 0;
		}
		$id = Quick_Write::save( sanitize_key( (string) $request['template'] ), (string) $request['text'], $source );
		return is_wp_error( $id ) ? Rest_Errors::with_status( $id ) : rest_ensure_response( array( 'id' => (int) $id ) );
	}
}
