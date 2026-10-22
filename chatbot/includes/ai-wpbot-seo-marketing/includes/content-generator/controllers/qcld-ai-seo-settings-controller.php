<?php
/**
 * Settings Controller — Content Generator module settings & WP taxonomy helper.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Settings_Controller {

	private const OPTION_KEY = 'qcld_wpbot_ai_seo_content-generator_settings';

	private const DEFAULTS = array(
		'default_tone'          => 'professional',
		'default_language'      => 'en',
		'default_word_count'    => 1000,
		'default_category_id'   => 0,
		'default_post_type'     => 'post',
		'default_post_status'   => 'publish',
		'stock_provider'        => 'pexels',
		'inline_images'         => 0,
		'inline_image_size'     => 'large',
		'image_orientation'     => 'landscape',
		'image_reuse_days'      => 60,
	);

	/**
	 * On/off settings added by add-ons.
	 *
	 * @return array<string,bool> field => default
	 */
	private static function extra_bool_fields(): array {
		/**
		 * Filter the extra on/off settings stored with the Content Generator
		 * settings. Add-ons register their own toggles here.
		 *
		 * @param array<string,bool> $fields field => default.
		 */
		$fields = apply_filters( 'qcld_wpbot_ai_seo_content_settings_bool_fields', array() );
		return is_array( $fields ) ? $fields : array();
	}

	/* ── GET settings ────────────────────────────────── */

	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = get_option( self::OPTION_KEY, array() );
		$merged   = array_merge( self::DEFAULTS, $settings );

		// Resolve provider and key presence across global and module settings.
		$merged['stock_provider']  = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::get_provider();
		$merged['has_pexels_key']  = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::is_configured( 'pexels' );
		$merged['has_pixabay_key'] = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::is_configured( 'pixabay' );
		unset( $merged['pexels_api_key'], $merged['pixabay_api_key'] );

		// On/off settings registered by add-ons (see extra_bool_fields()).
		foreach ( self::extra_bool_fields() as $field => $default ) {
			$merged[ $field ] = array_key_exists( $field, $settings ) ? (bool) $settings[ $field ] : (bool) $default;
		}

		return new \WP_REST_Response( $merged );
	}

	/* ── SAVE settings ───────────────────────────────── */

	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$current = get_option( self::OPTION_KEY, array() );
		$params  = $request->get_json_params();

		$text_fields = array( 'default_tone', 'default_language', 'default_post_type', 'default_post_status' );
		foreach ( $text_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$current[ $field ] = sanitize_text_field( $params[ $field ] );
			}
		}

		if ( isset( $current['default_post_status'] ) && ! in_array( $current['default_post_status'], array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
			$current['default_post_status'] = self::DEFAULTS['default_post_status'];
		}

		if ( isset( $current['default_post_type'] ) && ! post_type_exists( $current['default_post_type'] ) ) {
			$current['default_post_type'] = self::DEFAULTS['default_post_type'];
		}

		if ( isset( $params['default_word_count'] ) ) {
			$current['default_word_count'] = absint( $params['default_word_count'] );
		}

		if ( isset( $params['default_category_id'] ) ) {
			$current['default_category_id'] = absint( $params['default_category_id'] );
		}

		$global_settings = get_option( 'qcld_wpbot_ai_seo_settings', array() );
		if ( ! is_array( $global_settings ) ) {
			$global_settings = array();
		}
		$global_changed = false;

		// Image settings. The old image_source select is gone from the UI —
		// stale stored values are simply ignored on read.
		if ( isset( $params['stock_provider'] ) ) {
			$provider = sanitize_key( $params['stock_provider'] );
			$valid_provider = in_array( $provider, Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::PROVIDERS, true ) ? $provider : 'pexels';
			$current['stock_provider']         = $valid_provider;
			$global_settings['stock_provider'] = $valid_provider;
			$global_changed                    = true;
		}

		if ( isset( $params['inline_images'] ) ) {
			$current['inline_images'] = min( 3, absint( $params['inline_images'] ) );
		}

		if ( isset( $params['inline_image_size'] ) ) {
			$size = sanitize_key( $params['inline_image_size'] );
			$current['inline_image_size'] = in_array( $size, array( 'medium', 'medium_large', 'large', 'full' ), true ) ? $size : 'large';
		}

		if ( isset( $params['image_orientation'] ) ) {
			$orientation = sanitize_key( $params['image_orientation'] );
			$current['image_orientation'] = 'any' === $orientation ? 'any' : 'landscape';
		}

		if ( isset( $params['image_reuse_days'] ) ) {
			$current['image_reuse_days'] = max( 0, min( 365, (int) $params['image_reuse_days'] ) );
		}

		// Stock API keys: stored encrypted, never exported, never echoed back.
		// Empty string clears the key; omitted param leaves it untouched.
		foreach ( array( 'pexels_api_key', 'pixabay_api_key' ) as $key_field ) {
			if ( ! isset( $params[ $key_field ] ) || ! is_string( $params[ $key_field ] ) ) {
				continue;
			}
			$raw = trim( sanitize_text_field( $params[ $key_field ] ) );
			if ( '' === $raw ) {
				unset( $current[ $key_field ], $global_settings[ $key_field ] );
			} else {
				$encrypted                      = Qcld_Wpbot_Ai_Seo_Encryption::encrypt( $raw );
				$current[ $key_field ]          = $encrypted;
				$global_settings[ $key_field ]  = $encrypted;
			}
			$global_changed = true;
		}

		foreach ( array_keys( self::extra_bool_fields() ) as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$current[ $field ] = (bool) $params[ $field ];
			}
		}

		update_option( self::OPTION_KEY, $current, false );
		if ( $global_changed ) {
			update_option( 'qcld_wpbot_ai_seo_settings', $global_settings, false );
			qcld_wpbot_ai_seo_clear_settings_cache( array( self::OPTION_KEY, 'qcld_wpbot_ai_seo_settings' ) );
		} else {
			qcld_wpbot_ai_seo_clear_settings_cache( array( self::OPTION_KEY ) );
		}

		$response = array_merge( self::DEFAULTS, $current );
		$response['stock_provider']  = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::get_provider();
		$response['has_pexels_key']  = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::is_configured( 'pexels' );
		$response['has_pixabay_key'] = Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::is_configured( 'pixabay' );
		unset( $response['pexels_api_key'], $response['pixabay_api_key'] );

		return new \WP_REST_Response( array(
			'message'  => __( 'Settings saved.', 'chatbot' ),
			'settings' => $response,
		) );
	}

	/* ── WP Taxonomies helper ────────────────────────── */

	public function get_wp_taxonomies( \WP_REST_Request $request ): \WP_REST_Response {
		// Categories.
		$categories = get_categories( array(
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		$cats = array();
		foreach ( $categories as $cat ) {
			$cats[] = array(
				'id'     => $cat->term_id,
				'name'   => $cat->name,
				'slug'   => $cat->slug,
				'parent' => $cat->parent,
				'count'  => $cat->count,
			);
		}

		// Tags.
		$tags_list = get_tags( array(
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		$tags = array();
		if ( $tags_list && ! is_wp_error( $tags_list ) ) {
			foreach ( $tags_list as $tag ) {
				$tags[] = array(
					'id'    => $tag->term_id,
					'name'  => $tag->name,
					'slug'  => $tag->slug,
					'count' => $tag->count,
				);
			}
		}

		// Post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$types      = array();
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$types[] = array(
				'name'  => $pt->name,
				'label' => $pt->label,
			);
		}

		return new \WP_REST_Response( array(
			'categories' => $cats,
			'tags'       => $tags,
			'post_types' => $types,
		) );
	}
}
