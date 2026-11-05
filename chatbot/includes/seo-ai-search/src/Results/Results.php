<?php
/**
 * Before-and-after results for every logged change: clicks, impressions,
 * click-through rate, position (Search Console) and conversions (Analytics,
 * when connected), compared with the same number of days before the change,
 * at 14, 28 and 90 days (owner's setting). Checked daily.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Results;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Results tracker.
 */
final class Results {

	/**
	 * Changes updated per daily run.
	 */
	const BATCH = 200;

	/**
	 * Hook the daily update.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'update' ) );
	}

	/**
	 * Fill results that have become due.
	 *
	 * @param string $today Y-m-d (site time), for tests.
	 * @return int Changes updated.
	 */
	public static function update( $today = '' ) {
		global $wpdb;
		$today   = '' !== $today ? $today : current_time( 'Y-m-d' );
		$days    = (array) Settings::get( 'results_days' );
		$latest  = Data_Store::coverage()['last'];
		$table   = Schema::table( Schema::CHANGES );
		$horizon = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( max( $days ) + 30 ) . ' days' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id > 0 AND undone_at IS NULL AND created_at >= %s ORDER BY id DESC LIMIT %d", $horizon . ' 00:00:00', self::BATCH * 5 ), ARRAY_A );
		$updated = 0;
		foreach ( $rows as $row ) {
			$change  = Change_Log::shape( $row );
			$results = $change['results'];
			$changed = false;
			// On-page score after the change (no Search Console needed): re-check the page once.
			if ( isset( $results['onpage']['before'] ) && ! isset( $results['onpage']['after'] ) && strtotime( $change['created_at'] . ' UTC' ) < time() - 10 * MINUTE_IN_SECONDS ) {
				$audit = \QuantumCloud\SeoAiSearch\Onpage\Audit::audit( (int) $change['post_id'], 'refresh' );
				if ( is_array( $audit ) && isset( $audit['score'] ) ) {
					$results['onpage']['after'] = (int) $audit['score'];
					$changed                    = true;
				}
			}
			foreach ( $days as $day ) {
				$key = (string) (int) $day;
				if ( isset( $results[ $key ] ) ) {
					continue;
				}
				$result = self::measure( (int) $change['post_id'], substr( $change['created_at'], 0, 10 ), (int) $day, $latest );
				if ( null !== $result ) {
					$results[ $key ] = $result;
					$changed         = true;
				}
			}
			if ( $changed ) {
				Change_Log::set_results( $change['id'], $results );
				++$updated;
			}
			if ( $updated >= self::BATCH ) {
				break;
			}
		}
		return $updated;
	}

	/**
	 * Numbers N days after a change versus N days before it, or null when
	 * the "after" period is not complete in Search Console yet.
	 *
	 * @param int    $post_id Post id.
	 * @param string $day     Change day (Y-m-d).
	 * @param int    $days    Period length.
	 * @param string $latest  Last day with Search Console data.
	 * @return array|null before, after, change.
	 */
	public static function measure( $post_id, $day, $days, $latest ) {
		$periods = self::periods( $day, $days );
		if ( '' === $latest || $periods['after_end'] > $latest ) {
			return null;
		}
		$url_id = Data_Store::url_for_post( $post_id );
		$before = $url_id ? Data_Store::totals( $periods['before_start'], $periods['before_end'], $url_id, 0 ) : Data_Store::shape( array() );
		$after  = $url_id ? Data_Store::totals( $periods['after_start'], $periods['after_end'], $url_id, 0 ) : Data_Store::shape( array() );
		if ( $url_id ) {
			$ga_before             = Data_Store::ga_period( $periods['before_start'], $periods['before_end'] );
			$ga_after              = Data_Store::ga_period( $periods['after_start'], $periods['after_end'] );
			$before['conversions'] = isset( $ga_before[ $url_id ] ) ? $ga_before[ $url_id ]['conversions'] : null;
			$after['conversions']  = isset( $ga_after[ $url_id ] ) ? $ga_after[ $url_id ]['conversions'] : null;
		}
		return array(
			'before'  => $before,
			'after'   => $after,
			'change'  => self::difference( $before, $after ),
			'periods' => $periods,
		);
	}

	/**
	 * Before and after windows of equal length around a change (the change
	 * day itself belongs to neither).
	 *
	 * @param string $day  Change day.
	 * @param int    $days Length.
	 * @return array before_start, before_end, after_start, after_end.
	 */
	public static function periods( $day, $days ) {
		$change = new \DateTimeImmutable( $day );
		$days   = max( 1, (int) $days );
		return array(
			'before_start' => $change->modify( '-' . $days . ' days' )->format( 'Y-m-d' ),
			'before_end'   => $change->modify( '-1 day' )->format( 'Y-m-d' ),
			'after_start'  => $change->modify( '+1 day' )->format( 'Y-m-d' ),
			'after_end'    => $change->modify( '+' . $days . ' days' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Changes in each measure (position: positive = moved up).
	 *
	 * @param array $before Before.
	 * @param array $after  After.
	 * @return array clicks, clicks_pct, impressions, ctr, position, conversions.
	 */
	public static function difference( array $before, array $after ) {
		$position = 0.0;
		if ( $before['position'] > 0 && $after['position'] > 0 ) {
			$position = round( $before['position'] - $after['position'], 1 );
		}
		return array(
			'clicks'      => $after['clicks'] - $before['clicks'],
			'clicks_pct'  => $before['clicks'] > 0 ? round( ( $after['clicks'] - $before['clicks'] ) / $before['clicks'], 3 ) : null,
			'impressions' => $after['impressions'] - $before['impressions'],
			'ctr'         => round( $after['ctr'] - $before['ctr'], 4 ),
			'position'    => $position,
			'conversions' => isset( $before['conversions'], $after['conversions'] ) && null !== $before['conversions'] && null !== $after['conversions'] ? round( $after['conversions'] - $before['conversions'], 2 ) : null,
		);
	}

	/**
	 * Summary for screens and the dashboard.
	 *
	 * @param int $days Results checkpoint to summarise (default 28).
	 * @return array changes (last 30 days), measured, improved, clicks_gained.
	 */
	public static function summary( $days = 28 ) {
		global $wpdb;
		$table = Schema::table( Schema::CHANGES );
		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND undone_at IS NULL", $since ) );
		$rows   = (array) $wpdb->get_col( "SELECT results FROM {$table} WHERE results IS NOT NULL AND undone_at IS NULL ORDER BY id DESC LIMIT 1000" );
		// phpcs:enable
		$measured = 0;
		$improved = 0;
		$gained   = 0;
		foreach ( $rows as $raw ) {
			$results = json_decode( (string) $raw, true );
			$key     = (string) (int) $days;
			if ( ! isset( $results[ $key ]['change'] ) ) {
				continue;
			}
			++$measured;
			$change = $results[ $key ]['change'];
			if ( $change['clicks'] > 0 || $change['position'] > 0.5 ) {
				++$improved;
			}
			$gained += (int) $change['clicks'];
		}
		return array(
			'changes'       => $recent,
			'measured'      => $measured,
			'improved'      => $improved,
			'clicks_gained' => $gained,
			'checkpoint'    => (int) $days,
		);
	}
}
