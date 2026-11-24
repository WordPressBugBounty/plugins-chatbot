<?php
/**
 * The Help Center's section on WPBot Core's Permissions grid.
 *
 * Levels: view (see the Help Center screens and drafts), use (write and edit
 * drafts), publish (publish, approve AI changes, undo), settings (Help Center
 * settings, design, address). Registered only while the Help Center is on.
 * Without WPBot Core's Permissions, every level needs manage_options.
 *
 * The first time it runs, the grid gets a starting point like WordPress posts:
 * editors publish, authors write drafts (administrators always have every
 * level). It is written once; the owner's later changes on the grid stay.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Permission section.
 */
final class Permissions {

	/**
	 * Section id.
	 */
	const SECTION = 'help_center';

	/**
	 * Set once the starting roles were written to the grid.
	 */
	const SEEDED = 'qcld_wpbot_help_center_permissions_seeded';

	/**
	 * Starting roles per level.
	 *
	 * @var array<string,string[]>
	 */
	const DEFAULT_ROLES = array(
		'publish' => array( 'editor' ),
		'use'     => array( 'author' ),
	);

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/permission_sections', array( self::class, 'sections' ) );
		add_filter( 'qcld_wpbot_core/adopted_sections', array( self::class, 'adopted' ) );
		add_action( 'init', array( self::class, 'maybe_seed' ), 20 );
	}

	/**
	 * Write the starting roles to WPBot Core's grid once, unless the owner already set the section.
	 *
	 * @return void
	 */
	public static function maybe_seed() {
		if ( get_option( self::SEEDED ) || ! class_exists( '\QuantumCloud\WPBotCore\Permissions\Grid' ) ) {
			return;
		}
		$grid = \QuantumCloud\WPBotCore\Permissions\Grid::get();
		$seed = self::seed( $grid );
		if ( $seed !== $grid ) {
			\QuantumCloud\WPBotCore\Permissions\Grid::save( $seed );
		}
		update_option( self::SEEDED, 1, false );
	}

	/**
	 * A grid with the starting roles added, unless the section is already set.
	 *
	 * @param array $grid Grid (section => level => roles).
	 * @return array
	 */
	public static function seed( array $grid ) {
		if ( ! isset( $grid[ self::SECTION ] ) ) {
			$grid[ self::SECTION ] = self::DEFAULT_ROLES;
		}
		return $grid;
	}

	/**
	 * Add the section.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public static function sections( $sections ) {
		$sections                  = (array) $sections;
		$sections[ self::SECTION ] = array(
			'label'       => __( 'Help Center', 'chatbot' ),
			'description' => __( 'Help articles, knowledge bases and categories: who may read drafts, write, publish, and change the Help Center settings.', 'chatbot' ),
		);
		return $sections;
	}

	/**
	 * Adopt the section (it uses the grid).
	 *
	 * @param string[] $ids Ids.
	 * @return string[]
	 */
	public static function adopted( $ids ) {
		$ids   = (array) $ids;
		$ids[] = self::SECTION;
		return $ids;
	}

	/**
	 * Capability for a level.
	 *
	 * @param string $level view, use, publish or settings.
	 * @return string
	 */
	public static function cap( $level ) {
		if ( class_exists( '\QuantumCloud\WPBotCore\Permissions\Sections' ) ) {
			return \QuantumCloud\WPBotCore\Permissions\Sections::cap( self::SECTION, (string) $level );
		}
		return 'manage_options';
	}
}
