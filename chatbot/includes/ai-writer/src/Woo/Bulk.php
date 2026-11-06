<?php
/**
 * Products list bulk action "Write descriptions with AI": a job writes a
 * long and short description per product; the owner reviews them and
 * only approved ones are saved.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Woo;

use QuantumCloud\AiWriter\Admin\Admin_Controller;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk product descriptions.
 */
final class Bulk {

	const TYPE   = 'products';
	const ACTION = 'qcwr_write_descriptions';

	/**
	 * Most products in one job.
	 */
	const MAX = 50;

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'register' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle' ), 10, 3 );
	}

	/**
	 * Add the bulk action for users with the Use level.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function register( $actions ) {
		if ( current_user_can( Module::cap( 'use' ) ) ) {
			$actions[ self::ACTION ] = __( 'Write descriptions with AI', 'chatbot' );
		}
		return $actions;
	}

	/**
	 * Start the job and open its review screen. WordPress has already
	 * checked the list's nonce (bulk-posts) before this filter runs.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $ids      Product ids.
	 * @return string
	 */
	public static function handle( $redirect, $action, $ids ) {
		if ( self::ACTION !== $action ) {
			return $redirect;
		}
		$job_id = self::start( (array) $ids );
		if ( is_wp_error( $job_id ) ) {
			return add_query_arg( 'qcwr_error', rawurlencode( $job_id->get_error_message() ), $redirect );
		}
		return admin_url( 'admin.php?page=' . Admin_Controller::SLUG . '&products_job=' . (int) $job_id );
	}

	/**
	 * Create the job: one step per product the user may edit.
	 *
	 * @param int[] $ids Product ids.
	 * @return int|WP_Error Job id.
	 */
	public static function start( array $ids ) {
		if ( ! current_user_can( Module::cap( 'use' ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_forbidden', __( 'You do not have permission to use AI Writer.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$steps = array();
		foreach ( array_slice( array_unique( array_map( 'absint', $ids ) ), 0, self::MAX ) as $id ) {
			if ( 'product' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$steps[] = array(
				'kind'  => 'woo_product',
				/* translators: %s: product name. */
				'label' => sprintf( __( 'Writing: %s', 'chatbot' ), get_the_title( $id ) ),
				'input' => array( 'product_id' => $id ),
			);
		}
		if ( array() === $steps ) {
			return new WP_Error( 'qcld_wpbot_writer_no_products', __( 'Choose products you can edit.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job_id = Job_Store::create(
			Module::ID,
			self::TYPE,
			array(
				/* translators: %d: number of products. */
				'title'       => sprintf( _n( 'Descriptions for %d product', 'Descriptions for %d products', count( $steps ), 'chatbot' ), count( $steps ) ),
				'object_type' => 'product',
				'payload'     => array(
					'saved'   => array(),
					'skipped' => array(),
				),
			),
			$steps
		);
		return $job_id ? $job_id : new WP_Error( 'qcld_wpbot_writer_job_failed', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * Step: write one product's descriptions (nothing is saved).
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step( array $step, array $job ) {
		$id = (int) $step['input']['product_id'];
		if ( ! user_can( (int) $job['user_id'], 'edit_post', $id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this product.', 'chatbot' ) );
		}
		$args = array(
			'job_id' => (int) $job['id'],
			'cache'  => false,
		);
		$long = Product_Writer::long( $id, '', $args );
		if ( is_wp_error( $long ) ) {
			return $long;
		}
		$short = Product_Writer::short( $id, '', $args );
		if ( is_wp_error( $short ) ) {
			return $short;
		}
		$post = get_post( $id );
		return array(
			'product_id'   => $id,
			'name'         => get_the_title( $id ),
			'hash'         => md5( $post->post_content . '|' . $post->post_excerpt ),
			'before_long'  => wp_strip_all_tags( $post->post_content ),
			'before_short' => wp_strip_all_tags( $post->post_excerpt ),
			'long'         => $long['html'],
			'short'        => $short['html'],
			'flags'        => array_merge( $long['flags'], $short['flags'] ),
			'unsupported'  => array_values( array_unique( array_merge( $long['unsupported'], $short['unsupported'] ) ) ),
		);
	}

	/**
	 * Review view: progress plus each finished product's proposal.
	 *
	 * @param int $job_id Job.
	 * @return array|null
	 */
	public static function review( $job_id ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] ) {
			return null;
		}
		$items = array();
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( 'woo_product' !== $step['kind'] ) {
				continue;
			}
			$id    = (int) $step['input']['product_id'];
			$state = 'pending';
			if ( in_array( $id, (array) $job['payload']['saved'], true ) ) {
				$state = 'saved';
			} elseif ( in_array( $id, (array) $job['payload']['skipped'], true ) ) {
				$state = 'skipped';
			} elseif ( 'done' === $step['status'] ) {
				$state = 'ready';
			} elseif ( 'failed' === $step['status'] ) {
				$state = 'failed';
			}
			$item = array(
				'product_id' => $id,
				'name'       => get_the_title( $id ),
				'edit_url'   => (string) get_edit_post_link( $id, 'raw' ),
				'state'      => $state,
				'error'      => (string) $step['error_message'],
			);
			if ( 'done' === $step['status'] && is_array( $step['output'] ) ) {
				$item += array_intersect_key( $step['output'], array_flip( array( 'before_long', 'before_short', 'long', 'short', 'flags', 'unsupported' ) ) );
			}
			$items[] = $item;
		}
		return array(
			'title' => $job['title'],
			'items' => $items,
		);
	}

	/**
	 * Save the approved products' descriptions, or skip them.
	 *
	 * @param int    $job_id Job.
	 * @param int[]  $ids    Product ids.
	 * @param string $what   save or skip.
	 * @return array|WP_Error saved, skipped, errors.
	 */
	public static function decide( $job_id, array $ids, $what ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_job', __( 'This job no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$outputs = array();
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( 'woo_product' === $step['kind'] && 'done' === $step['status'] && is_array( $step['output'] ) ) {
				$outputs[ (int) $step['output']['product_id'] ] = $step['output'];
			}
		}
		$saved   = (array) $job['payload']['saved'];
		$skipped = (array) $job['payload']['skipped'];
		$errors  = array();
		foreach ( array_map( 'absint', $ids ) as $id ) {
			if ( ! isset( $outputs[ $id ] ) || in_array( $id, $saved, true ) || in_array( $id, $skipped, true ) ) {
				continue;
			}
			if ( 'skip' === $what ) {
				$skipped[] = $id;
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
				$errors[ $id ] = __( 'You are not allowed to edit this product.', 'chatbot' );
				continue;
			}
			if ( md5( $post->post_content . '|' . $post->post_excerpt ) !== $outputs[ $id ]['hash'] ) {
				$errors[ $id ] = __( 'The product was edited after this was written; open it and write again.', 'chatbot' );
				continue;
			}
			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => $id,
						'post_content' => $outputs[ $id ]['long'],
						'post_excerpt' => $outputs[ $id ]['short'],
					)
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				$errors[ $id ] = $result->get_error_message();
				continue;
			}
			$saved[] = $id;
		}
		Job_Store::merge_payload(
			$job_id,
			array(
				'saved'   => array_values( array_unique( $saved ) ),
				'skipped' => array_values( array_unique( $skipped ) ),
			)
		);
		return array(
			'saved'   => $saved,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}
}
