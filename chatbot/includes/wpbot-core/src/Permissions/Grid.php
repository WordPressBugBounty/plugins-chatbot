<?php
/**
 * The saved permissions grid and the capability filter that applies it.
 *
 * Grid shape: section => level => list of role slugs.
 *
 * Rules:
 * - Users who can manage_options get every level of every section.
 * - A higher level includes the lower ones (publish includes use and view;
 *   use includes view; settings includes view).
 * - "settings" of a section also requires manage_options for API keys; the
 *   key routes check that separately.
 * - Only adopted sections are granted from the grid; for the rest the
 *   capability is not handled here and existing Menu Access still applies.
 * - WordPress per-post rights (edit_post…) still apply on top.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Grid storage and the user_has_cap filter.
 */
final class Grid {

	const OPTION = 'qcld_wpbot_core_permissions';

	/**
	 * Instance.
	 *
	 * @var Grid|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Grid
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add the capability filter.
	 *
	 * @return void
	 */
	public function hook() {
		add_filter( 'user_has_cap', array( $this, 'filter_caps' ), 10, 4 );
	}

	/**
	 * Grant wpbot_* capabilities from the grid.
	 *
	 * @param array    $allcaps All capabilities of the user.
	 * @param string[] $caps    Required primitive capabilities.
	 * @param array    $args    Requested capability and arguments.
	 * @param \WP_User $user    User.
	 * @return array
	 */
	public function filter_caps( $allcaps, $caps, $args, $user ) {
		foreach ( (array) $caps as $cap ) {
			$parsed = Sections::parse_cap( $cap );
			if ( null === $parsed ) {
				continue;
			}
			list( $section, $level ) = $parsed;
			if ( ! in_array( $section, $this->adopted_sections(), true ) ) {
				continue;
			}
			$roles           = $user instanceof \WP_User ? (array) $user->roles : array();
			$allcaps[ $cap ] = ! empty( $allcaps['manage_options'] ) || $this->roles_have( $roles, $section, $level );
		}
		return $allcaps;
	}

	/**
	 * Whether any of the roles has a level (directly or through a higher level).
	 *
	 * @param string[] $roles   Role slugs.
	 * @param string   $section Section id.
	 * @param string   $level   Level.
	 * @return bool
	 */
	public function roles_have( array $roles, $section, $level ) {
		$grid = self::get();
		foreach ( self::levels_granting( $level ) as $granting ) {
			$allowed = isset( $grid[ $section ][ $granting ] ) ? (array) $grid[ $section ][ $granting ] : array();
			if ( array_intersect( $roles, $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Levels that include the requested level.
	 *
	 * @param string $level Level.
	 * @return string[]
	 */
	public static function levels_granting( $level ) {
		switch ( $level ) {
			case 'view':
				return array( 'view', 'use', 'publish', 'settings' );
			case 'use':
				return array( 'use', 'publish' );
			case 'publish':
				return array( 'publish' );
			case 'settings':
				return array( 'settings' );
		}
		return array();
	}

	/**
	 * Saved grid.
	 *
	 * @return array
	 */
	public static function get() {
		$grid = get_option( self::OPTION, array() );
		return is_array( $grid ) ? $grid : array();
	}

	/**
	 * Replace the grid after validating sections, levels and roles.
	 *
	 * @param array $grid Grid from the form.
	 * @return array Saved grid.
	 */
	public static function save( array $grid ) {
		$roles    = array_keys( wp_roles()->roles );
		$sections = Sections::adopted_ids();
		$clean    = array();
		foreach ( $grid as $section => $levels ) {
			$section = sanitize_key( $section );
			if ( ! in_array( $section, $sections, true ) || ! is_array( $levels ) ) {
				continue;
			}
			foreach ( $levels as $level => $role_list ) {
				$level = sanitize_key( $level );
				if ( ! in_array( $level, Sections::LEVELS, true ) ) {
					continue;
				}
				$picked = array_values( array_intersect( array_map( 'sanitize_key', (array) $role_list ), $roles ) );
				// Administrators always have every level; no need to store them.
				$picked = array_values( array_diff( $picked, array( 'administrator' ) ) );
				if ( array() !== $picked ) {
					$clean[ $section ][ $level ] = $picked;
				}
			}
		}
		update_option( self::OPTION, $clean, true );
		return $clean;
	}

	/**
	 * Apply a named preset on top of the saved grid.
	 *
	 * @param string $preset Preset id.
	 * @return array Saved grid.
	 */
	public static function apply_preset( $preset ) {
		$grid = self::get();
		switch ( $preset ) {
			case 'editors_writer':
				$grid['writer']['use'] = array_values( array_unique( array_merge( isset( $grid['writer']['use'] ) ? (array) $grid['writer']['use'] : array(), array( 'editor' ) ) ) );
				break;
			case 'authors_writer':
				$grid['writer']['use'] = array_values( array_unique( array_merge( isset( $grid['writer']['use'] ) ? (array) $grid['writer']['use'] : array(), array( 'author' ) ) ) );
				break;
			case 'admins_only':
				$grid = array();
				break;
		}
		return self::save( $grid );
	}

	/**
	 * Presets for the screen.
	 *
	 * @return array<string,string>
	 */
	public static function presets() {
		return array(
			'editors_writer' => __( 'Let Editors use AI Writer', 'chatbot' ),
			'authors_writer' => __( 'Let Authors use AI Writer on their own posts', 'chatbot' ),
			'admins_only'    => __( 'Administrators only', 'chatbot' ),
		);
	}

	/**
	 * Ids of sections that adopted the grid.
	 *
	 * @return string[]
	 */
	private function adopted_sections() {
		return Sections::adopted_ids();
	}
}
