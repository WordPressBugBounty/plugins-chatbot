<?php
/**
 * Brand service (chatbot features CB1d): the product name, logo and link every module shows.
 *
 * One source: the host adapter's get_brand() (keys name, logo, url besides the colours), so a
 * white-label add-on changes the name once, through the host, for menus, screens, emails,
 * reports, the MCP server name and outgoing request names.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Host;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Brand.
 */
final class Brand {

	/**
	 * Name when the host gives none.
	 */
	const DEFAULT_NAME = 'WPBot';

	/**
	 * The host's brand array ([] without a host).
	 *
	 * @return array
	 */
	public static function all() {
		$host  = class_exists( Core::class ) ? Core::instance()->host() : null;
		$brand = $host ? (array) $host->get_brand() : array();
		/**
		 * The brand: colours plus name, logo, url.
		 *
		 * @param array $brand Brand.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/brand', $brand );
	}

	/**
	 * Product name ("WPBot", or the white-label name).
	 *
	 * @return string
	 */
	public static function name() {
		$brand = self::all();
		$name  = isset( $brand['name'] ) ? trim( wp_strip_all_tags( (string) $brand['name'] ) ) : '';
		return '' !== $name ? $name : self::DEFAULT_NAME;
	}

	/**
	 * The product name a site owner sees on module screens, in emails and in outgoing requests:
	 * the primary host's brand name, else its get_name(), else "WPBot". WPBot's hosts give
	 * "WPBot" (or the white-label name); another host, such as SEO Help, gives its own.
	 *
	 * @return string
	 */
	public static function product() {
		$brand = self::all();
		$name  = isset( $brand['name'] ) ? trim( wp_strip_all_tags( (string) $brand['name'] ) ) : '';
		if ( '' === $name ) {
			$host = class_exists( Core::class ) ? Core::instance()->host() : null;
			$name = $host ? trim( wp_strip_all_tags( (string) $host->get_name() ) ) : '';
		}
		/**
		 * The product name shown on module screens.
		 *
		 * @param string $name Name.
		 */
		$name = trim( (string) apply_filters( 'qcld_wpbot_core/product_name', $name ) );
		return '' !== $name ? $name : self::DEFAULT_NAME;
	}

	/**
	 * The paid edition's name for upsell texts: the brand's pro_name, else product() + " Pro".
	 *
	 * @return string
	 */
	public static function pro_product() {
		$brand = self::all();
		$name  = isset( $brand['pro_name'] ) ? trim( wp_strip_all_tags( (string) $brand['pro_name'] ) ) : '';
		/* translators: %s: product name, for example WPBot. */
		return '' !== $name ? $name : sprintf( __( '%s Pro', 'chatbot' ), self::product() );
	}

	/**
	 * Product token for user agents (letters, digits and dashes): "WPBot", "SEOHelp".
	 *
	 * @return string
	 */
	public static function agent_token() {
		$token = (string) preg_replace( '/[^A-Za-z0-9-]/', '', self::product() );
		return '' !== $token ? $token : self::DEFAULT_NAME;
	}

	/**
	 * Logo URL ('' = the module's own logo).
	 *
	 * @return string
	 */
	public static function logo() {
		$brand = self::all();
		return isset( $brand['logo'] ) ? esc_url_raw( (string) $brand['logo'] ) : '';
	}

	/**
	 * Product link ('' = none).
	 *
	 * @return string
	 */
	public static function url() {
		$brand = self::all();
		return isset( $brand['url'] ) ? esc_url_raw( (string) $brand['url'] ) : '';
	}

	/**
	 * Only the style values of a brand array (for CSS variables): without name, logo and url.
	 *
	 * @param array $brand Brand.
	 * @return array
	 */
	public static function styles( array $brand ) {
		unset( $brand['name'], $brand['logo'], $brand['url'], $brand['pro_name'] );
		return $brand;
	}

	/**
	 * Whether the product is white-labelled (the name is not WPBot).
	 *
	 * @return bool
	 */
	public static function is_white_label() {
		return self::DEFAULT_NAME !== self::name();
	}
}
