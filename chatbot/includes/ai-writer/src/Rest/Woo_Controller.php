<?php
/**
 * REST routes of WooCommerce writing (registered only with WooCommerce).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Editor\Editor_Actions;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Woo\Bulk;
use QuantumCloud\AiWriter\Woo\Product_Writer;
use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/woo/*
 */
final class Woo_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Settings_Controller::NAMESPACE_V1;
		$id = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			$ns,
			'/woo/products/(?P<id>\d+)/write',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'write' ),
				'permission_callback' => array( $this, 'can_edit_product' ),
				'args'                => array_merge(
					$id,
					array(
						'part'       => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'long', 'short', 'faq', 'variations' ),
						),
						'notes'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'regenerate' => array(
							'type'    => 'object',
							'default' => array(),
						),
						'again'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						// Round 3: this product's persuasion choices.
						'persuasion' => array(
							'type'    => 'object',
							'default' => array(),
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/woo/products/(?P<id>\d+)/variations',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_variations' ),
				'permission_callback' => array( $this, 'can_edit_product' ),
				'args'                => array_merge(
					$id,
					array(
						'descriptions' => array(
							'type'     => 'object',
							'required' => true,
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/woo/products/(?P<id>\d+)/alt',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'alt' ),
				'permission_callback' => array( $this, 'can_edit_product' ),
				'args'                => array_merge(
					$id,
					array(
						'apply' => array(
							'type'    => 'object',
							'default' => array(),
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/woo/terms/(?P<taxonomy>product_cat|product_tag)/(?P<id>\d+)/write',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'term' ),
				'permission_callback' => array( $this, 'can_edit_term' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			$ns,
			'/woo/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'job' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			$ns,
			'/woo/jobs/(?P<id>\d+)/decide',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'decide' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array_merge(
					$id,
					array(
						'products' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'integer' ),
						),
						'what'     => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'save', 'skip' ),
						),
					)
				),
			)
		);
	}

	/**
	 * Use level plus edit rights on the product.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_product( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return current_user_can( Module::cap( 'use' ) ) && 'product' === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * Use level plus the right to edit the term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_term( WP_REST_Request $request ) {
		$term = get_term( (int) $request['id'], (string) $request['taxonomy'] );
		return current_user_can( Module::cap( 'use' ) ) && $term && ! is_wp_error( $term ) && current_user_can( 'edit_term', $term->term_id );
	}

	/**
	 * Owner of the bulk job with Use, or anyone with Settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_access_job( WP_REST_Request $request ) {
		$job = Job_Store::get( (int) $request['id'] );
		if ( ! $job || Module::ID !== $job['module'] || Bulk::TYPE !== $job['type'] ) {
			return false;
		}
		return current_user_can( Module::cap( 'settings' ) ) || ( get_current_user_id() === (int) $job['user_id'] && current_user_can( Module::cap( 'use' ) ) );
	}

	/**
	 * POST write a part of the product copy (preview only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$id    = (int) $request['id'];
		$notes = (string) $request['notes'];
		$args  = array( 'cache' => ! $request['again'] );
		if ( $request['again'] ) {
			$args['regenerate'] = \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] );
		}
		if ( array() !== (array) $request['persuasion'] ) {
			$args['persuasion'] = \QuantumCloud\AiWriter\Persuasion\Choices::sanitise( (array) $request['persuasion'] );
			if ( $args['persuasion']['owner'] ) {
				// The choice is kept for this product.
				update_post_meta( $id, \QuantumCloud\AiWriter\Persuasion\Choices::META, wp_slash( $args['persuasion'] ) );
			}
		}
		switch ( (string) $request['part'] ) {
			case 'long':
				$result = Product_Writer::long( $id, $notes, $args );
				break;
			case 'short':
				$result = Product_Writer::short( $id, $notes, $args );
				break;
			case 'faq':
				$result = Product_Writer::faq( $id, $notes, $args );
				break;
			default:
				$result = Product_Writer::variations( $id, $notes );
		}
		if ( is_array( $result ) ) {
			unset( $result['_usage'] );
		}
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST save approved variation descriptions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_variations( WP_REST_Request $request ) {
		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_variations', __( 'This product has no variations.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$children = array_map( 'intval', $product->get_children() );
		$saved    = array();
		foreach ( (array) $request['descriptions'] as $variation_id => $text ) {
			$variation_id = (int) $variation_id;
			if ( ! in_array( $variation_id, $children, true ) || ! is_scalar( $text ) ) {
				continue;
			}
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$variation->set_description( wp_kses_post( (string) $text ) );
				$variation->save();
				$saved[] = $variation_id;
			}
		}
		return rest_ensure_response( array( 'saved' => $saved ) );
	}

	/**
	 * POST alt text for the product image and gallery: suggestions, or
	 * apply the approved ones (apply: id => alt).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function alt( WP_REST_Request $request ) {
		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product ) {
			return new WP_Error( 'qcld_wpbot_writer_no_product', __( 'This product could not be found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$ids   = array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) ) );
		$apply = (array) $request['apply'];
		if ( array() !== $apply ) {
			$saved = array();
			foreach ( $apply as $attachment_id => $alt ) {
				$attachment_id = (int) $attachment_id;
				if ( in_array( $attachment_id, $ids, true ) && is_scalar( $alt ) && current_user_can( 'edit_post', $attachment_id ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( (string) $alt ) ) );
					$saved[] = $attachment_id;
				}
			}
			return rest_ensure_response( array( 'saved' => $saved ) );
		}
		$missing = array();
		foreach ( $ids as $attachment_id ) {
			if ( '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
				$missing[] = array(
					'id'      => $attachment_id,
					'context' => $product->get_name(),
				);
			}
		}
		if ( array() === $missing ) {
			return rest_ensure_response(
				array(
					'alts'    => array(),
					'message' => __( 'Every product image already has alt text.', 'chatbot' ),
				)
			);
		}
		$ctx    = Editor_Actions::context( $product->get_id(), $product->get_name(), wp_strip_all_tags( $product->get_short_description() ) );
		$result = Editor_Actions::alt_text( $missing, $ctx );
		if ( is_wp_error( $result ) ) {
			return Rest_Errors::with_status( $result );
		}
		$out = array();
		foreach ( $result['alts'] as $attachment_id => $alt ) {
			$out[] = array(
				'id'    => (int) $attachment_id,
				'alt'   => $alt,
				'thumb' => (string) wp_get_attachment_image_url( (int) $attachment_id, 'thumbnail' ),
			);
		}
		return rest_ensure_response( array( 'alts' => $out ) );
	}

	/**
	 * POST a description for a product category or tag (preview only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function term( WP_REST_Request $request ) {
		$result = Product_Writer::term( get_term( (int) $request['id'], (string) $request['taxonomy'] ) );
		if ( is_array( $result ) ) {
			unset( $result['_usage'] );
		}
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * GET bulk job progress and review.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function job( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'job'    => Job_Runner::progress( (int) $request['id'] ),
				'review' => Bulk::review( (int) $request['id'] ),
			)
		);
	}

	/**
	 * POST save or skip reviewed products.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function decide( WP_REST_Request $request ) {
		$result = Bulk::decide( (int) $request['id'], array_map( 'intval', (array) $request['products'] ), (string) $request['what'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result + array( 'review' => Bulk::review( (int) $request['id'] ) ) );
	}
}
