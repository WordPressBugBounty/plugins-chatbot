<?php
/**
 * Import Search Console's Links report (CSV export uploaded by the owner,
 * because the Search Console API has no Links report). Linking pages become
 * known links; "Top linking sites" and "Top linking text" exports are kept
 * as summaries.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

defined( 'ABSPATH' ) || exit;

/**
 * CSV import.
 */
final class Csv_Import {

	/**
	 * Option with linking sites and anchors from the CSV summaries.
	 */
	const OPTION = 'qcld_wpbot_seo_link_summary';

	/**
	 * Most rows read.
	 */
	const MAX_ROWS = 20000;

	/**
	 * Import a CSV text.
	 *
	 * @param string $text CSV.
	 * @return array kind (pages|targets|sites|anchors|unknown), added, rows, skipped.
	 */
	public static function import( $text ) {
		$rows = self::rows( (string) $text );
		$out  = array(
			'kind'    => 'unknown',
			'rows'    => max( 0, count( $rows ) - 1 ),
			'added'   => 0,
			'merged'  => 0,
			'skipped' => 0,
		);
		if ( count( $rows ) < 2 ) {
			return $out;
		}
		$columns = self::columns( array_shift( $rows ) );
		if ( isset( $columns['source'] ) ) {
			$out['kind'] = 'pages';
			foreach ( $rows as $row ) {
				$source = isset( $row[ $columns['source'] ] ) ? trim( $row[ $columns['source'] ] ) : '';
				$target = isset( $columns['target'], $row[ $columns['target'] ] ) ? trim( $row[ $columns['target'] ] ) : '';
				$anchor = isset( $columns['anchor'], $row[ $columns['anchor'] ] ) ? trim( $row[ $columns['anchor'] ] ) : '';
				if ( '' !== $source && ! preg_match( '#^https?://#i', $source ) ) {
					$source = 'https://' . ltrim( $source, '/' );
				}
				if ( '' === $target && Link_Store::has_source( $source ) ) {
					// The export has no target column and this page is already known.
					++$out['merged'];
					continue;
				}
				$id = Link_Store::add(
					array(
						'source_url' => $source,
						'target_url' => $target,
						'anchor'     => $anchor,
						'origin'     => 'csv',
					)
				);
				if ( is_wp_error( $id ) ) {
					++$out['skipped'];
				} else {
					++$out['added'];
				}
			}
			return $out;
		}
		$summary = self::summary();
		if ( isset( $columns['target'] ) ) {
			// "Top linked pages": incoming links per page of this site.
			$out['kind'] = 'targets';
			$count       = isset( $columns['count'] ) ? $columns['count'] : null;
			foreach ( $rows as $row ) {
				$url = isset( $row[ $columns['target'] ] ) ? esc_url_raw( trim( $row[ $columns['target'] ] ) ) : '';
				if ( '' === $url ) {
					++$out['skipped'];
					continue;
				}
				$summary['targets'][ $url ] = null !== $count && isset( $row[ $count ] ) ? absint( str_replace( array( ',', '.', ' ' ), '', $row[ $count ] ) ) : 0;
				++$out['added'];
			}
		} elseif ( isset( $columns['site'] ) ) {
			$out['kind'] = 'sites';
			foreach ( $rows as $row ) {
				$host = isset( $row[ $columns['site'] ] ) ? Link_Finder::host( 'https://' . preg_replace( '#^https?://#i', '', trim( $row[ $columns['site'] ] ) ) ) : '';
				if ( '' === $host ) {
					++$out['skipped'];
					continue;
				}
				$summary['sites'][ $host ] = isset( $columns['count'], $row[ $columns['count'] ] ) ? absint( str_replace( array( ',', '.', ' ' ), '', $row[ $columns['count'] ] ) ) : 0;
				++$out['added'];
			}
		} elseif ( isset( $columns['anchor'] ) ) {
			$out['kind'] = 'anchors';
			foreach ( $rows as $row ) {
				$anchor = isset( $row[ $columns['anchor'] ] ) ? sanitize_text_field( $row[ $columns['anchor'] ] ) : '';
				if ( '' === $anchor ) {
					++$out['skipped'];
					continue;
				}
				$summary['anchors'][ $anchor ] = isset( $columns['count'], $row[ $columns['count'] ] ) ? absint( str_replace( array( ',', '.', ' ' ), '', $row[ $columns['count'] ] ) ) : 0;
				++$out['added'];
			}
		} else {
			return $out;
		}
		arsort( $summary['sites'] );
		arsort( $summary['anchors'] );
		arsort( $summary['targets'] );
		$summary['targets']  = array_slice( $summary['targets'], 0, 1000, true );
		$summary['sites']    = array_slice( $summary['sites'], 0, 2000, true );
		$summary['anchors']  = array_slice( $summary['anchors'], 0, 500, true );
		$summary['imported'] = current_time( 'mysql', true );
		update_option( self::OPTION, $summary, false );
		return $out;
	}

	/**
	 * Saved summary.
	 *
	 * @return array sites (host => linking pages), anchors (text => count), targets (page => incoming links), imported.
	 */
	public static function summary() {
		$saved = get_option( self::OPTION, array() );
		return array(
			'sites'    => isset( $saved['sites'] ) && is_array( $saved['sites'] ) ? $saved['sites'] : array(),
			'anchors'  => isset( $saved['anchors'] ) && is_array( $saved['anchors'] ) ? $saved['anchors'] : array(),
			'targets'  => isset( $saved['targets'] ) && is_array( $saved['targets'] ) ? $saved['targets'] : array(),
			'imported' => isset( $saved['imported'] ) ? (string) $saved['imported'] : '',
		);
	}

	/**
	 * Which column holds what (header names in English as Search Console exports them, plus common variants).
	 *
	 * @param string[] $header Header row.
	 * @return array role => index.
	 */
	public static function columns( array $header ) {
		$roles = array(
			'source' => array( 'linking page', 'linking pages url', 'source', 'source url', 'from', 'linking url', 'url from', 'referring page' ),
			'target' => array( 'target page', 'target', 'target url', 'to', 'linked page', 'url to' ),
			'anchor' => array( 'linking text', 'anchor', 'anchor text', 'text' ),
			'site'   => array( 'site', 'linking site', 'linking sites', 'domain', 'referring domain' ),
			'count'  => array( 'linking pages', 'incoming links', 'links', 'count', 'target pages' ),
		);
		$out   = array();
		foreach ( $header as $index => $name ) {
			$name = strtolower( trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $name ) ) );
			foreach ( $roles as $role => $names ) {
				if ( ! isset( $out[ $role ] ) && in_array( $name, $names, true ) ) {
					$out[ $role ] = $index;
					break;
				}
			}
		}
		if ( isset( $out['source'], $out['count'] ) && $out['source'] === $out['count'] ) {
			unset( $out['count'] );
		}
		return $out;
	}

	/**
	 * CSV rows (comma, semicolon or tab; quoted fields; BOM removed).
	 *
	 * @param string $text CSV.
	 * @return array[]
	 */
	public static function rows( $text ) {
		$text  = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines = preg_split( '/\R/', $text );
		$first = (string) reset( $lines );
		$delim = ',';
		foreach ( array( "\t", ';' ) as $candidate ) {
			if ( substr_count( $first, $candidate ) > substr_count( $first, $delim ) ) {
				$delim = $candidate;
			}
		}
		$rows = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$rows[] = str_getcsv( $line, $delim, '"', '\\' );
			if ( count( $rows ) > self::MAX_ROWS ) {
				break;
			}
		}
		return $rows;
	}
}
