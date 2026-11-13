<?php
/**
 * Access labels for knowledge base sources and chunks (chatbot features CB1c).
 *
 * A source or chunk may carry labels such as public, logged_in, role:editor, membership:gold,
 * course:42 or purchased:123. A visitor sees an item when it has no labels (public) or when
 * the visitor holds at least one of its labels. A chunk's own labels win over its source's,
 * so a source's labels can change without re-embedding anything.
 *
 * Labels are stored as ",a,b," so one LIKE per visitor label finds them, and the filter runs
 * in SQL before any scoring.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Access labels.
 */
final class Kb_Access {

	/**
	 * The label everyone holds.
	 */
	const PUBLIC_LABEL = 'public';

	/**
	 * Clean labels: lowercase letters, digits, "_", "-", ":" and "."; unique; sorted.
	 *
	 * @param string|string[] $labels Labels (array or comma-separated).
	 * @return string[]
	 */
	public static function clean( $labels ) {
		if ( ! is_array( $labels ) ) {
			$labels = explode( ',', (string) $labels );
		}
		$out = array();
		foreach ( $labels as $label ) {
			$raw   = trim( (string) $label );
			$label = substr( (string) preg_replace( '/[^a-z0-9_:\.\-]/', '', strtolower( $raw ) ), 0, 60 );
			if ( '' === $label && '' !== $raw ) {
				// A label of other characters only (for example "會員") must not become "public":
				// keep it as a stable hash, so it still has to match exactly.
				$label = 'h:' . substr( md5( function_exists( 'mb_strtolower' ) ? mb_strtolower( $raw, 'UTF-8' ) : strtolower( $raw ) ), 0, 16 );
			}
			if ( '' !== $label ) {
				$out[ $label ] = true;
			}
		}
		$out = array_keys( $out );
		sort( $out );
		return $out;
	}

	/**
	 * Labels as stored: '' for public (no labels, or "public" among them), else ",a,b,".
	 *
	 * @param string|string[] $labels Labels.
	 * @return string
	 */
	public static function encode( $labels ) {
		$labels = self::clean( $labels );
		if ( array() === $labels || in_array( self::PUBLIC_LABEL, $labels, true ) ) {
			return '';
		}
		$encoded = ',' . implode( ',', $labels ) . ',';
		// The column holds 191 characters: drop labels that do not fit rather than cut one in half.
		$fits = strlen( $encoded ) <= 191;
		while ( ! $fits && isset( $labels[1] ) ) {
			array_pop( $labels );
			$encoded = ',' . implode( ',', $labels ) . ',';
			$fits    = strlen( $encoded ) <= 191;
		}
		return strlen( $encoded ) > 191 ? '' : $encoded;
	}

	/**
	 * Stored labels back to a list ([] = public).
	 *
	 * @param string $stored Stored value.
	 * @return string[]
	 */
	public static function decode( $stored ) {
		return self::clean( trim( (string) $stored, ',' ) );
	}

	/**
	 * Whether a visitor with these labels may see an item (pure; mirrors sql()).
	 *
	 * @param string   $chunk_access  Chunk's stored labels.
	 * @param string   $source_access Source's stored labels.
	 * @param string[] $visitor       Visitor labels.
	 * @return bool
	 */
	public static function allows( $chunk_access, $source_access, array $visitor ) {
		$stored = '' !== (string) $chunk_access ? (string) $chunk_access : (string) $source_access;
		if ( '' === $stored ) {
			return true;
		}
		foreach ( self::clean( $visitor ) as $label ) {
			if ( false !== strpos( $stored, ',' . $label . ',' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SQL condition for a search: items with no labels, or with one of the visitor's labels.
	 *
	 * @param string[] $visitor     Visitor labels.
	 * @param string   $chunk_col   Chunk access column.
	 * @param string   $source_col  Source access column.
	 * @return string
	 */
	public static function sql( array $visitor, $chunk_col = 'c.access', $source_col = 's.access' ) {
		global $wpdb;
		$effective = "COALESCE(NULLIF({$chunk_col}, ''), {$source_col}, '')";
		$parts     = array( "{$effective} = ''" );
		foreach ( self::clean( $visitor ) as $label ) {
			$parts[] = $wpdb->prepare( "{$effective} LIKE %s", '%,' . $wpdb->esc_like( $label ) . ',%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column names are fixed by the caller.
		}
		return '( ' . implode( ' OR ', $parts ) . ' )';
	}
}
