<?php
/**
 * Persuasion in AI Writer (round 3, CR-13): hooks AI Writer into the shared
 * persuasion pieces in WPBot Core (Persuasion Kit, technique library,
 * voice of customer, honesty checks). Every use is feature-detected, so an
 * older WPBot Core simply shows nothing new.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Persuasion bootstrap.
 */
final class Persuasion {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/voc_sources', array( Search_Log_Phrases::class, 'register' ) );
		Honesty_Record::hook();
	}

	/**
	 * Whether WPBot Core has the persuasion pieces.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_core_persuasion_kit' ) && class_exists( '\QuantumCloud\WPBotCore\Persuasion\Techniques' );
	}
}
