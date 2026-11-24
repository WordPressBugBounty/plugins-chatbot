<?php
/**
 * Languages (HC1-16, plan 5.1 "Languages": WPML and Polylang, RTL).
 *
 * - Articles, knowledge bases, categories, tags and glossary terms are
 *   translatable in Polylang (pll_get_post_types, pll_get_taxonomies) and WPML
 *   (wpml_config_array, the same as a wpml-config.xml).
 * - Access labels and the product version are copied to translations; votes,
 *   views and the gap a draft came from are not.
 * - The Help Center's own texts (home title and intro) are registered as
 *   strings to translate (Polylang → Languages → Translations; WPML String
 *   Translation).
 * - Caches that hold article words (glossary, search vocabulary) are kept per language.
 *
 * Search, lists and the knowledge base follow the language plugins' own query
 * filters; each article's knowledge item carries its language (Knowledge).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Glossary\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Language plugin support.
 */
final class Languages {

	/**
	 * String group in the language plugins.
	 */
	const GROUP = 'WPBot Help Center';

	/**
	 * Meta copied to translations.
	 *
	 * @var string[]
	 */
	const COPY_META = array( '_wpbot_access', '_wpbot_product_version' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pll_get_post_types', array( self::class, 'post_types' ), 10, 2 );
		add_filter( 'pll_get_taxonomies', array( self::class, 'taxonomies' ), 10, 2 );
		add_filter( 'pll_copy_post_metas', array( self::class, 'copy_metas' ) );
		add_filter( 'wpml_config_array', array( self::class, 'wpml_config' ) );
		add_action( 'admin_init', array( self::class, 'register_strings' ) );
	}

	/**
	 * Polylang: articles are translatable.
	 *
	 * @param array $types       Post types.
	 * @param bool  $is_settings Whether Polylang builds its settings list.
	 * @return array
	 */
	public static function post_types( $types, $is_settings = false ) {
		$types = (array) $types;
		if ( ! $is_settings ) {
			$types[ Post_Type::NAME ] = Post_Type::NAME;
		}
		return $types;
	}

	/**
	 * Polylang: knowledge bases, categories, tags and glossary terms are translatable.
	 *
	 * @param array $taxonomies  Taxonomies.
	 * @param bool  $is_settings Whether Polylang builds its settings list.
	 * @return array
	 */
	public static function taxonomies( $taxonomies, $is_settings = false ) {
		$taxonomies = (array) $taxonomies;
		if ( ! $is_settings ) {
			foreach ( self::taxonomy_names() as $name ) {
				$taxonomies[ $name ] = $name;
			}
		}
		return $taxonomies;
	}

	/**
	 * Polylang: meta copied to translations.
	 *
	 * @param string[] $keys Meta keys.
	 * @return string[]
	 */
	public static function copy_metas( $keys ) {
		return array_values( array_unique( array_merge( (array) $keys, self::COPY_META ) ) );
	}

	/**
	 * WPML: the same settings a wpml-config.xml would give (WPML reads XML files
	 * only from a plugin's root, and the Help Center lives inside its host).
	 *
	 * @param array $config Parsed configuration.
	 * @return array
	 */
	public static function wpml_config( $config ) {
		$config = is_array( $config ) ? $config : array();
		$add    = array(
			'custom-types'  => array(
				'custom-type' => array(
					array(
						'value' => Post_Type::NAME,
						'attr'  => array( 'translate' => 1 ),
					),
				),
			),
			'taxonomies'    => array( 'taxonomy' => array() ),
			'custom-fields' => array( 'custom-field' => array() ),
			'admin-texts'   => array(),
		);
		foreach ( self::taxonomy_names() as $name ) {
			$add['taxonomies']['taxonomy'][] = array(
				'value' => $name,
				'attr'  => array( 'translate' => 1 ),
			);
		}
		$fields = array(
			'_wpbot_alt_questions'   => 'translate',
			'_wpbot_access'          => 'copy',
			'_wpbot_product_version' => 'copy',
			'_wpbot_helpful_yes'     => 'ignore',
			'_wpbot_helpful_no'      => 'ignore',
			'_wpbot_helpful_reasons' => 'ignore',
			'_wpbot_views'           => 'ignore',
			'_wpbot_gap'             => 'ignore',
			'_wpbot_source'          => 'ignore',
		);
		foreach ( $fields as $key => $action ) {
			$add['custom-fields']['custom-field'][] = array(
				'value' => $key,
				'attr'  => array( 'action' => $action ),
			);
		}
		$config['wpml-config'] = isset( $config['wpml-config'] ) && is_array( $config['wpml-config'] ) ? array_merge_recursive( $config['wpml-config'], $add ) : $add;
		return $config;
	}

	/**
	 * Register the Help Center's own texts for translation.
	 *
	 * @return void
	 */
	public static function register_strings() {
		$all = Settings::all();
		foreach ( array(
			'title' => __( 'Help Center title', 'chatbot' ),
			'lede'  => __( 'Help Center introduction', 'chatbot' ),
		) as $key => $label ) {
			$value = trim( (string) $all[ $key ] );
			if ( '' === $value ) {
				continue;
			}
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $label, $value, self::GROUP );
			}
			do_action( 'wpml_register_single_string', self::GROUP, $label, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own action.
		}
	}

	/**
	 * A Help Center text in the current language.
	 *
	 * @param string $key   title or lede.
	 * @param string $value Stored text.
	 * @return string
	 */
	public static function text( $key, $value ) {
		$value = (string) $value;
		if ( '' === trim( $value ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $value;
		}
		if ( function_exists( 'pll__' ) ) {
			return (string) pll__( $value );
		}
		$labels = array(
			'title' => 'Help Center title',
			'lede'  => 'Help Center introduction',
		);
		return (string) apply_filters( 'wpml_translate_single_string', $value, self::GROUP, isset( $labels[ $key ] ) ? __( $labels[ $key ], 'chatbot' ) : $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.NonSingularStringLiteralText -- WPML's own filter; the label is one of the two registered above.
	}

	/**
	 * Current language code, or '' without a language plugin.
	 *
	 * @return string
	 */
	public static function current() {
		if ( function_exists( 'pll_current_language' ) ) {
			return (string) pll_current_language( 'slug' );
		}
		$wpml = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
		return is_string( $wpml ) && 'all' !== $wpml ? $wpml : '';
	}

	/**
	 * A cache key for the current language.
	 *
	 * @param string $key Base key.
	 * @return string
	 */
	public static function key( $key ) {
		$lang = sanitize_key( self::current() );
		return '' === $lang ? $key : $key . '_' . $lang;
	}

	/**
	 * Delete a per-language cache in every language.
	 *
	 * @param string $key Base key.
	 * @return void
	 */
	public static function forget( $key ) {
		delete_transient( $key );
		$codes = array();
		if ( function_exists( 'pll_languages_list' ) ) {
			$codes = (array) pll_languages_list( array( 'fields' => 'slug' ) );
		} else {
			$wpml  = apply_filters( 'wpml_active_languages', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
			$codes = is_array( $wpml ) ? array_keys( $wpml ) : array();
		}
		foreach ( $codes as $code ) {
			delete_transient( $key . '_' . sanitize_key( (string) $code ) );
		}
	}

	/**
	 * Translatable taxonomies.
	 *
	 * @return string[]
	 */
	private static function taxonomy_names() {
		return array( Taxonomies::KB, Taxonomies::CATEGORY, Taxonomies::TAG, Glossary::TAXONOMY );
	}
}
