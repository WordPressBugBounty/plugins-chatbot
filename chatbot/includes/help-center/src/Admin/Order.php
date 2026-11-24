<?php
/**
 * Article and category order (the Organize screen and the REST route behind it).
 *
 * Articles keep their order in menu_order; categories in the term meta
 * wpbot_order. Reordering others' articles needs the publish level; categories
 * need the right to manage terms.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Order storage and REST route.
 */
final class Order {

	/**
	 * Term meta key for category order.
	 */
	const TERM_META = 'wpbot_order';

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'wpbot-help-center/v1';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/order',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'save' ),
				'permission_callback' => array( self::class, 'can_save' ),
				'args'                => array(
					'type' => array(
						'type'     => 'string',
						'enum'     => array( 'articles', 'terms' ),
						'required' => true,
					),
					'ids'  => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
						'maxItems' => 500,
					),
				),
			)
		);
	}

	/**
	 * Who may reorder.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_save( $request ) {
		return 'terms' === $request->get_param( 'type' ) ? current_user_can( 'manage_wpbot_article_terms' ) : current_user_can( 'publish_wpbot_articles' );
	}

	/**
	 * Save an order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save( $request ) {
		$ids   = self::clean_ids( $request->get_param( 'ids' ) );
		$saved = 'terms' === $request->get_param( 'type' ) ? self::save_terms( $ids ) : self::save_articles( $ids );
		return array( 'saved' => $saved );
	}

	/**
	 * Positive, unique ids in their order.
	 *
	 * @param mixed $ids Ids.
	 * @return int[]
	 */
	public static function clean_ids( $ids ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Store articles' menu_order.
	 *
	 * @param int[] $ids Article ids in order.
	 * @return int Articles saved.
	 */
	public static function save_articles( array $ids ) {
		$saved = 0;
		foreach ( $ids as $position => $id ) {
			$post = get_post( $id );
			if ( ! $post || Post_Type::NAME !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			if ( (int) $post->menu_order !== (int) $position ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'menu_order' => (int) $position,
					)
				);
			}
			++$saved;
		}
		return $saved;
	}

	/**
	 * Store categories' order.
	 *
	 * @param int[] $ids Term ids in order.
	 * @return int Terms saved.
	 */
	public static function save_terms( array $ids ) {
		$saved = 0;
		foreach ( $ids as $position => $id ) {
			$term = get_term( $id );
			if ( ! $term || is_wp_error( $term ) || ! in_array( $term->taxonomy, array( Taxonomies::CATEGORY, Taxonomies::KB ), true ) ) {
				continue;
			}
			update_term_meta( $id, self::TERM_META, (int) $position );
			++$saved;
		}
		return $saved;
	}

	/**
	 * Sort terms by their stored order, then by name (terms without an order go last).
	 *
	 * @param object[] $terms Terms.
	 * @return object[]
	 */
	public static function sort_terms( array $terms ) {
		$order = array();
		foreach ( $terms as $term ) {
			$value                   = get_term_meta( $term->term_id, self::TERM_META, true );
			$order[ $term->term_id ] = '' === $value ? PHP_INT_MAX : (int) $value;
		}
		usort(
			$terms,
			static function ( $a, $b ) use ( $order ) {
				return array( $order[ $a->term_id ], strtolower( $a->name ) ) <=> array( $order[ $b->term_id ], strtolower( $b->name ) );
			}
		);
		return $terms;
	}
}
