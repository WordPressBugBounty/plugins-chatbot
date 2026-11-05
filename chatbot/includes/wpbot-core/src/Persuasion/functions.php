<?php
/**
 * Public persuasion API (round 3, CR-13): Persuasion Kit, technique
 * library, reader stages, voice of customer and honesty checks. Shared by
 * AI Writer, AI Marketing, Social and later the ChatBot. Callers check
 * their own module's permission level before changing anything.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_persuasion_kit' ) ) {
	/**
	 * Persuasion Kit entries usable now (expired offers are never returned
	 * unless include_expired is set, for admin lists).
	 *
	 * @param array $args types (string[]), quotable (bool), include_expired (bool), product_id (int: adds live WooCommerce data).
	 * @return array{entries: array[], woo: array|null}
	 */
	function qcld_wpbot_core_persuasion_kit( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Persuasion\Kit::query( $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kit_save' ) ) {
	/**
	 * Add or update a kit entry.
	 *
	 * @param array $entry type, label, body, value, source, url, permission, expires_on; id to update.
	 * @return array|WP_Error
	 */
	function qcld_wpbot_core_kit_save( array $entry ) {
		return \QuantumCloud\WPBotCore\Persuasion\Kit::save( $entry );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kit_delete' ) ) {
	/**
	 * Delete a kit entry.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	function qcld_wpbot_core_kit_delete( $id ) {
		return \QuantumCloud\WPBotCore\Persuasion\Kit::delete( $id );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_techniques' ) ) {
	/**
	 * Techniques that fit ("No specific technique" first).
	 *
	 * @param array $args kind, type, stage, job, channel, edition.
	 * @return array[]
	 */
	function qcld_wpbot_core_techniques( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Persuasion\Techniques::query( $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_technique' ) ) {
	/**
	 * One technique.
	 *
	 * @param string $id Id.
	 * @return array|null
	 */
	function qcld_wpbot_core_technique( $id ) {
		return \QuantumCloud\WPBotCore\Persuasion\Techniques::get( (string) $id );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_reader_stages' ) ) {
	/**
	 * Reader stages, least to most aware.
	 *
	 * @return array<string,array>
	 */
	function qcld_wpbot_core_reader_stages() {
		return \QuantumCloud\WPBotCore\Persuasion\Stages::all();
	}
}

if ( ! function_exists( 'qcld_wpbot_core_voice_of_customer' ) ) {
	/**
	 * Customer phrases (already redacted), most used first.
	 *
	 * @param array $args topic, source, search, limit.
	 * @return array[]
	 */
	function qcld_wpbot_core_voice_of_customer( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Persuasion\Voice_Of_Customer::query( $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_voc_add' ) ) {
	/**
	 * Add phrases the owner approved to the voice of customer bank.
	 *
	 * @param string[] $phrases Phrases.
	 * @param string   $source  Source id (default 'import').
	 * @return int Phrases added.
	 */
	function qcld_wpbot_core_voc_add( array $phrases, $source = 'import' ) {
		return \QuantumCloud\WPBotCore\Persuasion\Voice_Of_Customer::add( $phrases, $source );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_voc_refresh' ) ) {
	/**
	 * Start reading the sources (and grouping with AI when $group is true;
	 * show Voice_Of_Customer::estimate() first).
	 *
	 * @param string $module Module paying for grouping.
	 * @param bool   $group  Group with AI.
	 * @return int|WP_Error Job id.
	 */
	function qcld_wpbot_core_voc_refresh( $module, $group = false ) {
		return \QuantumCloud\WPBotCore\Persuasion\Voice_Of_Customer::refresh( $module, $group );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_honesty_check' ) ) {
	/**
	 * Honesty flags for a text.
	 *
	 * @param string $text    Copy.
	 * @param array  $context kit, expired, woo, prices, facts.
	 * @return array[] code, label, message, words.
	 */
	function qcld_wpbot_core_honesty_check( $text, array $context = array() ) {
		return \QuantumCloud\WPBotCore\Persuasion\Honesty::check( $text, $context );
	}
}
