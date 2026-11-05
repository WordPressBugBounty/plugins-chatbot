<?php
/**
 * The suite's permission sections and levels.
 *
 * Every WPBot area is a section. A section "adopts" the grid when its module
 * registers through the qcld_wpbot_core/permission_sections filter; until then
 * the Permissions screen shows it as still using Menu Access.
 *
 * Capabilities are named wpbot_{section}_{level}, for example
 * wpbot_writer_use. They are granted by a user_has_cap filter, never written
 * into roles.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Section and level registry.
 */
final class Sections {

	/**
	 * Levels in increasing order of power.
	 *
	 * @var string[]
	 */
	const LEVELS = array( 'view', 'use', 'publish', 'settings' );

	/**
	 * Every section, with adoption state.
	 *
	 * @return array<string,array{label: string, adopted: bool, description: string}>
	 */
	public static function all() {
		$sections = array(
			'chatbot'   => self::section( __( 'ChatBot settings', 'chatbot' ) ),
			'context'   => self::section( __( 'Business Context', 'chatbot' ) ),
			'writer'    => self::section( __( 'AI Writer', 'chatbot' ) ),
			'studio'    => self::section( __( 'AI Studio', 'chatbot' ) ),
			'marketing' => self::section( __( 'AI Marketing', 'chatbot' ) ),
			'social'    => self::section( __( 'Social', 'chatbot' ) ),
			'insight'   => self::section( __( 'Site Insight', 'chatbot' ) ),
			'seo'       => self::section( __( 'SEO & AI Search', 'chatbot' ) ),
			'dashboard' => self::section( __( 'Growth Dashboard', 'chatbot' ) ),
		);

		/**
		 * Add sections (label and description). Adoption is set through
		 * qcld_wpbot_core/adopted_sections.
		 *
		 * @param array $sections id => array( label, description ).
		 */
		$sections = (array) apply_filters( 'qcld_wpbot_core/permission_sections', $sections );

		$adopted = self::adopted_ids();
		foreach ( $sections as $id => $section ) {
			$sections[ $id ]            = array_merge( self::section( (string) $id ), (array) $section );
			$sections[ $id ]['adopted'] = in_array( (string) $id, $adopted, true );
		}
		return $sections;
	}

	/**
	 * Ids of sections whose module uses the grid.
	 *
	 * Kept separate from all() so the capability filter never loads
	 * translations (it can run before init).
	 *
	 * @return string[]
	 */
	public static function adopted_ids() {
		/**
		 * Modules add their section id here to switch it to the grid.
		 *
		 * @param string[] $ids Section ids.
		 */
		return array_values( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_core/adopted_sections', array() ) ) );
	}

	/**
	 * Owner-facing labels of the levels.
	 *
	 * @return array<string,string>
	 */
	public static function level_labels() {
		return array(
			'view'     => __( 'View', 'chatbot' ),
			'use'      => __( 'Use', 'chatbot' ),
			'publish'  => __( 'Publish or send', 'chatbot' ),
			'settings' => __( 'Manage settings and keys', 'chatbot' ),
		);
	}

	/**
	 * Capability name for a section and level.
	 *
	 * @param string $section Section id.
	 * @param string $level   Level.
	 * @return string
	 */
	public static function cap( $section, $level ) {
		return 'wpbot_' . $section . '_' . $level;
	}

	/**
	 * Split a capability into section and level, or null when it is not ours.
	 *
	 * @param string $cap Capability.
	 * @return array{0: string, 1: string}|null
	 */
	public static function parse_cap( $cap ) {
		if ( ! is_string( $cap ) || 0 !== strpos( $cap, 'wpbot_' ) ) {
			return null;
		}
		foreach ( self::LEVELS as $level ) {
			$suffix = '_' . $level;
			if ( substr( $cap, -strlen( $suffix ) ) === $suffix ) {
				$section = substr( $cap, 6, -strlen( $suffix ) );
				if ( '' !== $section ) {
					return array( $section, $level );
				}
			}
		}
		return null;
	}

	/**
	 * Default section entry.
	 *
	 * @param string $label Label.
	 * @return array
	 */
	private static function section( $label ) {
		return array(
			'label'       => $label,
			'adopted'     => false,
			'description' => '',
		);
	}
}
