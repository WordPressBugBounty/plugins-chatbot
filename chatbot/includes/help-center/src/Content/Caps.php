<?php
/**
 * Article capabilities follow the Help Center permission section.
 *
 * The post type and taxonomies use their own primitive capabilities. This maps
 * each one to a level of the section help_center, which WPBot Core grants from
 * its Permissions grid (administrators always have it). Without WPBot Core the
 * levels fall back to the matching WordPress post capabilities, so editors and
 * authors work as they do with posts.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

use QuantumCloud\HelpCenter\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Capability mapping.
 */
final class Caps {

	/**
	 * Primitive capability => array( section level, WordPress fallback ).
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	const MAP = array(
		'edit_wpbot_articles'             => array( 'use', 'edit_posts' ),
		'create_wpbot_articles'           => array( 'use', 'edit_posts' ),
		'delete_wpbot_articles'           => array( 'use', 'delete_posts' ),
		'assign_wpbot_article_terms'      => array( 'use', 'edit_posts' ),
		'publish_wpbot_articles'          => array( 'publish', 'publish_posts' ),
		'edit_published_wpbot_articles'   => array( 'publish', 'edit_published_posts' ),
		'delete_published_wpbot_articles' => array( 'publish', 'delete_published_posts' ),
		'edit_others_wpbot_articles'      => array( 'publish', 'edit_others_posts' ),
		'delete_others_wpbot_articles'    => array( 'publish', 'delete_others_posts' ),
		'edit_private_wpbot_articles'     => array( 'publish', 'edit_private_posts' ),
		'delete_private_wpbot_articles'   => array( 'publish', 'delete_private_posts' ),
		'manage_wpbot_article_terms'      => array( 'publish', 'manage_categories' ),
		'read_private_wpbot_articles'     => array( 'view', 'read_private_posts' ),
	);

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( self::class, 'map' ), 20 );
	}

	/**
	 * Replace the Help Center's primitive capabilities with the section's (or WordPress's).
	 *
	 * @param string[]  $caps Required capabilities.
	 * @param bool|null $core Whether WPBot Core's Permissions are there (null: detect; tests pass it).
	 * @return string[]
	 */
	public static function map( $caps, $core = null ) {
		$core = is_bool( $core ) ? $core : class_exists( '\QuantumCloud\WPBotCore\Permissions\Sections' );
		$out  = array();
		foreach ( (array) $caps as $cap ) {
			if ( isset( self::MAP[ $cap ] ) ) {
				// Same name as WPBot Core's Sections::cap( 'help_center', $level ).
				$out[] = $core ? 'wpbot_' . Permissions::SECTION . '_' . self::MAP[ $cap ][0] : self::MAP[ $cap ][1];
			} else {
				$out[] = $cap;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
