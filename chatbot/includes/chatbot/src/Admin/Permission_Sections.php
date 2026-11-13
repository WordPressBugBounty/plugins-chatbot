<?php
/**
 * The chatbot's sections on WPBot Core's Permissions grid (chatbot features CB1d).
 *
 * The section chatbot_train (Train: knowledge gaps, the approval queue, MCP and Abilities) uses the grid now;
 * chatbot_inbox is listed for the Inbox (CB4) and adopted there. Help Desk adds its own section.
 * Without WPBot Core's Permissions, everything needs manage_options.
 *
 * Levels: view (read), use (propose changes), publish (approve, undo).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Permission sections.
 */
final class Permission_Sections {

	/**
	 * Train section id.
	 */
	const TRAIN = 'chatbot_train';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/permission_sections', array( __CLASS__, 'sections' ) );
		add_filter( 'qcld_wpbot_core/adopted_sections', array( __CLASS__, 'adopted' ) );
	}

	/**
	 * Add the sections.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public static function sections( $sections ) {
		$sections[ self::TRAIN ]   = array(
			'label'       => __( 'ChatBot: Train and AI tools', 'chatbot' ),
			'description' => __( 'Knowledge gaps, the approval queue, and what AI assistants (MCP, WordPress Abilities) may read and change.', 'chatbot' ),
		);
		$sections['chatbot_inbox'] = array(
			'label'       => __( 'ChatBot: Inbox', 'chatbot' ),
			'description' => __( 'Conversations handed to a person.', 'chatbot' ),
		);
		return $sections;
	}

	/**
	 * Adopt the Train section.
	 *
	 * @param string[] $ids Ids.
	 * @return string[]
	 */
	public static function adopted( $ids ) {
		$ids[] = self::TRAIN;
		return $ids;
	}

	/**
	 * Capability for a level of the Train section.
	 *
	 * @param string $level view, use or publish.
	 * @return string
	 */
	public static function cap( $level ) {
		if ( class_exists( '\QuantumCloud\WPBotCore\Permissions\Sections' ) ) {
			return \QuantumCloud\WPBotCore\Permissions\Sections::cap( self::TRAIN, $level );
		}
		return 'manage_options';
	}
}
