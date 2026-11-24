<?php
/**
 * Help Center settings (option qcld_wpbot_help_center_settings).
 *
 * HC1-03 holds the public address. The settings screen comes with the admin
 * (HC1-06) and the design screen (HC1-07).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Settings store.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_help_center_settings';

	/**
	 * Default public address (owner decision H11: /help/, editable).
	 */
	const DEFAULT_BASE = 'help';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'base'              => self::DEFAULT_BASE,
			'layout'            => 'help-center',
			'accent'            => '',
			'scheme'            => 'auto',
			'title'             => '',
			'lede'              => '',
			'contact_url'       => '',
			'glossary_popovers' => '1',
			'ai_publish'        => 'draft',
			'docs_mcp'          => '',
			'docs_mcp_exclude'  => array(),
			'docs_mcp_proxy'    => '',
		);
	}

	/**
	 * Layouts (owner decision D1).
	 *
	 * @var string[]
	 */
	const LAYOUTS = array( 'help-center', 'docs', 'simple' );

	/**
	 * Colour schemes.
	 *
	 * @var string[]
	 */
	const SCHEMES = array( 'auto', 'light', 'dark' );

	/**
	 * Design settings, cleaned, with an optional preview override (people who may change the settings only).
	 *
	 * @param array $override Preview values (layout, accent, scheme).
	 * @return array{layout:string,accent:string,scheme:string,title:string,lede:string,contact_url:string}
	 */
	public static function design( array $override = array() ) {
		$all    = array_merge( self::all(), array_filter( $override, 'strlen' ) );
		$layout = in_array( $all['layout'], self::LAYOUTS, true ) ? $all['layout'] : 'help-center';
		$scheme = in_array( $all['scheme'], self::SCHEMES, true ) ? $all['scheme'] : 'auto';
		$accent = self::color( (string) $all['accent'] );
		if ( '' === $accent ) {
			$accent = self::brand_color();
		}
		return array(
			'layout'      => $layout,
			'accent'      => $accent,
			'scheme'      => $scheme,
			'title'       => Languages::text( 'title', (string) $all['title'] ),
			'lede'        => Languages::text( 'lede', (string) $all['lede'] ),
			'contact_url' => (string) $all['contact_url'],
		);
	}

	/**
	 * A #rrggbb colour, or an empty string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function color( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return 1 === preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * The brand colour from WPBot Core's Brand service (KBx and white label set their own), else the default.
	 *
	 * @return string
	 */
	public static function brand_color() {
		if ( class_exists( '\QuantumCloud\WPBotCore\Host\Brand' ) ) {
			$brand = (array) \QuantumCloud\WPBotCore\Host\Brand::all();
			$color = self::color( isset( $brand['primary'] ) ? (string) $brand['primary'] : '' );
			if ( '' !== $color ) {
				return $color;
			}
		}
		return '#5b4e96';
	}

	/**
	 * All settings, with defaults for missing keys.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * What AI assistants' articles become (HC3-A, owner 20 November 2026): "draft" for review
	 * (default) or "publish" (auto-publish).
	 *
	 * @return string
	 */
	public static function ai_publish() {
		return 'publish' === self::all()['ai_publish'] ? 'publish' : 'draft';
	}

	/**
	 * The public address (a path without slashes, for example "help" or "docs/help").
	 *
	 * @return string
	 */
	public static function base() {
		$base = Address::sanitize( (string) self::all()['base'] );
		return '' === $base ? self::DEFAULT_BASE : $base;
	}
}
