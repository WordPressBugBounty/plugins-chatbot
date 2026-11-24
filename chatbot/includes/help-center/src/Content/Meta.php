<?php
/**
 * Article metadata (plan 10.1).
 *
 * Editable over REST by people who may edit the article: product version and
 * alternative questions; access labels too, but a change from someone without
 * the publish level is ignored (the block editor always sends the whole meta
 * object, so refusing it would block their saves). Votes and views are in the
 * read-only REST field help_stats, not in REST meta: a read-only meta key in the
 * editor's meta object makes every save fail. Source, AI write and human-edit
 * markers are internal.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Meta registration.
 */
final class Meta {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ), 11 );
		add_action( 'rest_api_init', array( self::class, 'rest_fields' ) );
		foreach ( array( 'add', 'update', 'delete' ) as $action ) {
			add_filter( $action . '_post_metadata', array( self::class, 'guard_access' ), 10, 3 );
		}
	}

	/**
	 * The read-only REST field help_stats (votes and views).
	 *
	 * @return void
	 */
	public static function rest_fields() {
		register_rest_field(
			Post_Type::NAME,
			'help_stats',
			array(
				'get_callback' => array( self::class, 'stats' ),
				'schema'       => array(
					'description' => __( 'Helpful votes and views.', 'chatbot' ),
					'type'        => 'object',
					'readonly'    => true,
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'helpful_yes' => array( 'type' => 'integer' ),
						'helpful_no'  => array( 'type' => 'integer' ),
						'views'       => array( 'type' => 'integer' ),
						'reasons'     => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Votes and views of an article.
	 *
	 * @param array $post Prepared post (id).
	 * @return array
	 */
	public static function stats( $post ) {
		$id      = isset( $post['id'] ) ? (int) $post['id'] : 0;
		$reasons = get_post_meta( $id, '_wpbot_helpful_reasons', true );
		return array(
			'helpful_yes' => (int) get_post_meta( $id, '_wpbot_helpful_yes', true ),
			'helpful_no'  => (int) get_post_meta( $id, '_wpbot_helpful_no', true ),
			'views'       => (int) get_post_meta( $id, '_wpbot_views', true ),
			'reasons'     => (object) array_map( 'intval', is_array( $reasons ) ? $reasons : array() ),
		);
	}

	/**
	 * During REST requests, ignore changes to access labels from people without the publish level.
	 *
	 * @param null|bool $check    Short-circuit value.
	 * @param int       $post_id  Post id.
	 * @param string    $meta_key Meta key.
	 * @return null|bool True skips the write; null lets it run.
	 */
	public static function guard_access( $check, $post_id, $meta_key ) {
		unset( $post_id );
		if ( '_wpbot_access' !== $meta_key || ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $check;
		}
		return current_user_can( 'publish_wpbot_articles' ) ? $check : true;
	}

	/**
	 * Meta definitions: key => register_post_meta() arguments.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		$string_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		return array(
			'_wpbot_access'          => array(
				'type'          => 'array',
				'single'        => true,
				'default'       => array(),
				'show_in_rest'  => array( 'schema' => $string_list ),
				'auth_callback' => array( self::class, 'can_edit' ),
			),
			'_wpbot_product_version' => array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => array( self::class, 'can_edit' ),
			),
			'_wpbot_alt_questions'   => array(
				'type'          => 'array',
				'single'        => true,
				'default'       => array(),
				'show_in_rest'  => array( 'schema' => $string_list ),
				'auth_callback' => array( self::class, 'can_edit' ),
			),
			'_wpbot_helpful_yes'     => array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
			'_wpbot_helpful_no'      => array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
			'_wpbot_views'           => array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
			'_wpbot_source'          => array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
			'_wpbot_ai_last_write'   => array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
			'_wpbot_human_edited'    => array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			),
		);
	}

	/**
	 * Register every key.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::definitions() as $key => $args ) {
			register_post_meta( Post_Type::NAME, $key, $args );
		}
	}

	/**
	 * Whether the user may edit this article (auth_callback signature).
	 *
	 * @param bool   $allowed  Allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post id.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}
}
