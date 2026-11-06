<?php
/**
 * Inactive "Pro" previews offered by the host (WPBot Free). The core never
 * names an edition: it shows whatever the host lists, and a host that
 * includes Pro lists nothing. The owner hides them all with one setting.
 *
 * Each item, as the host gives it:
 *   id        string   Stable id (also the campaign in the link).
 *   spot      string   Where it shows: 'menu' (a left-menu page; the default)
 *                      or a spot id such as 'write.interview' (see
 *                      docs/integration-readme.md, "Pro previews").
 *   label     string   Feature name.
 *   text      string   One sentence on what it does.
 *   benefits  string[] Why it matters.
 *   features  string[] What it includes.
 *   url       string   Where "Get WPBot Pro" goes (https only).
 *   button    string   Link text.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Admin;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and cleans the host's Pro preview list.
 */
final class Pro_Previews {

	/**
	 * Most items in each text list.
	 */
	const MAX_LIST = 8;

	/**
	 * Clean previews to show now (empty when hidden or offered by no host).
	 *
	 * @return array[]
	 */
	public static function items() {
		if ( Settings::get( 'hide_pro_previews' ) ) {
			return array();
		}
		return self::offered();
	}

	/**
	 * Clean previews the host offers, whether shown or hidden (the Settings
	 * switch appears only when there is something to hide).
	 *
	 * @return array[]
	 */
	public static function offered() {
		$host = Module::instance()->host();
		if ( ! $host ) {
			return array();
		}
		$out = array();
		foreach ( (array) $host->get_pro_previews() as $raw ) {
			$item = self::clean( $raw );
			if ( null !== $item && ! isset( $out[ $item['id'] ] ) ) {
				$out[ $item['id'] ] = $item;
			}
		}
		return array_values( $out );
	}

	/**
	 * Left-menu previews (spot 'menu').
	 *
	 * @return array[]
	 */
	public static function menu_items() {
		return array_values(
			array_filter(
				self::items(),
				static function ( $item ) {
					return 'menu' === $item['spot'];
				}
			)
		);
	}

	/**
	 * One clean item, or null when it has no id or label.
	 *
	 * @param mixed $raw Item from the host.
	 * @return array|null
	 */
	public static function clean( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['label'] ) ) {
			return null;
		}
		$id   = sanitize_key( (string) $raw['id'] );
		$spot = isset( $raw['spot'] ) ? strtolower( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $raw['spot'] ) ) : '';
		$url  = isset( $raw['url'] ) ? esc_url_raw( (string) $raw['url'], array( 'https' ) ) : '';
		if ( '' === $id ) {
			return null;
		}
		return array(
			'id'       => $id,
			'spot'     => '' !== $spot ? $spot : 'menu',
			'label'    => sanitize_text_field( (string) $raw['label'] ),
			'text'     => isset( $raw['text'] ) ? sanitize_text_field( (string) $raw['text'] ) : '',
			'benefits' => self::texts( isset( $raw['benefits'] ) ? $raw['benefits'] : array() ),
			'features' => self::texts( isset( $raw['features'] ) ? $raw['features'] : array() ),
			'url'      => $url,
			'button'   => isset( $raw['button'] ) ? sanitize_text_field( (string) $raw['button'] ) : '',
		);
	}

	/**
	 * A clean list of short texts.
	 *
	 * @param mixed $raw Raw list.
	 * @return string[]
	 */
	private static function texts( $raw ) {
		$out = array();
		foreach ( (array) $raw as $text ) {
			$text = sanitize_text_field( is_scalar( $text ) ? (string) $text : '' );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		return array_slice( $out, 0, self::MAX_LIST );
	}
}
