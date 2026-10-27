<?php
/**
 * Frustration signal types and their flag bits on sessions.flags.
 * The core has rage, dead, quickback and error; features can add more
 * (e.g. Pro "excessive scrolling") through the qcin/signal_types filter.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

final class Signal_Types {

	/**
	 * Cached extra types.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $extra = null;

	/**
	 * Extra signal types added by features.
	 *
	 * @return array<string,array<string,mixed>> type => bit, label, short, help.
	 */
	public static function extra() {
		if ( null !== self::$extra ) {
			return self::$extra;
		}
		/**
		 * Extra frustration signal types. The browser sends them with
		 * qcInsights.registerDetector( ( api ) => api.signal( type, selector, data ) ).
		 *
		 * Each entry: type (a-z, 2-16 chars) => array(
		 *   'bit'   => one bit from 256 upward (lower bits belong to the core),
		 *   'label' => 'Excessive scrolling', 'short' => 'Scroll', 'help' => '...'
		 * ).
		 *
		 * @param array<string,array<string,mixed>> $types Types.
		 */
		$raw  = (array) apply_filters( 'qcin/signal_types', array() );
		$out  = array();
		$used = array_sum( Session_Repository::FLAGS );
		foreach ( $raw as $type => $def ) {
			$type = (string) $type;
			$bit  = is_array( $def ) ? (int) ( $def['bit'] ?? 0 ) : 0;
			if ( ! preg_match( '/^[a-z]{2,16}$/', $type ) || isset( Session_Repository::FLAGS[ $type ] ) || 'custom' === $type ) {
				continue;
			}
			// One bit, 256 or higher, not used yet.
			if ( $bit < 256 || $bit > 1073741824 || ( $bit & ( $bit - 1 ) ) || ( $used & $bit ) ) {
				continue;
			}
			$used        |= $bit;
			$out[ $type ] = array(
				'bit'   => $bit,
				'label' => (string) ( $def['label'] ?? $type ),
				'short' => (string) ( $def['short'] ?? $type ),
				'help'  => (string) ( $def['help'] ?? '' ),
			);
		}
		self::$extra = $out;
		return $out;
	}

	/**
	 * Flag bit per signal type: core and extra.
	 *
	 * @return array<string,int>
	 */
	public static function flags() {
		$flags = Session_Repository::FLAGS;
		foreach ( self::extra() as $type => $def ) {
			$flags[ $type ] = $def['bit'];
		}
		return $flags;
	}

	/**
	 * Types the browser may send (quickback is found on the server).
	 *
	 * @return string[]
	 */
	public static function browser_types() {
		return array_merge( array( 'rage', 'dead', 'error', 'custom' ), array_keys( self::extra() ) );
	}

	/**
	 * Forget the cache (tests).
	 */
	public static function reset() {
		self::$extra = null;
	}
}
