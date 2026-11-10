<?php
/**
 * Counts everything an email report shows for one period and the period
 * before it: visits, frustration, top pages, JavaScript errors, devices,
 * traffic sources, countries and the busiest day. Totals only; no visitor
 * data, page URLs without query strings, error messages redacted.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Reports;

use QuantumCloud\Insights\AI\Digest_Builder;
use QuantumCloud\Insights\Processing\Health_Check;
use QuantumCloud\Insights\Processing\Scheduler;
use QuantumCloud\Insights\Settings;
use QuantumCloud\Insights\Storage\Schema;
use QuantumCloud\Insights\Storage\Session_Repository;
use QuantumCloud\Insights\Storage\Signal_Types;

defined( 'ABSPATH' ) || exit;

final class Report_Builder {

	/**
	 * Last database error while counting, if any.
	 *
	 * @var string
	 */
	private $db_error = '';

	/**
	 * The period a report covers, in site time.
	 *
	 * Weekly: the 7 full days before today. Monthly: the previous calendar
	 * month. Each is compared with the period of the same length before it.
	 *
	 * @param string $frequency weekly|monthly.
	 * @param int    $now       Timestamp.
	 * @return array<string,mixed> from, until, prev_from, prev_until (UTC MySQL), days, frequency, start, end (local Y-m-d).
	 */
	public static function period( $frequency, $now ) {
		$tz    = wp_timezone();
		$today = ( new \DateTimeImmutable( '@' . (int) $now ) )->setTimezone( $tz )->setTime( 0, 0 );
		if ( 'monthly' === $frequency ) {
			$until      = $today->modify( 'first day of this month' );
			$from       = $until->modify( '-1 month' );
			$prev_until = $from;
			$prev_from  = $from->modify( '-1 month' );
		} else {
			$until      = $today;
			$from       = $until->modify( '-7 days' );
			$prev_until = $from;
			$prev_from  = $from->modify( '-7 days' );
		}
		$utc = static function ( \DateTimeImmutable $d ) {
			return gmdate( 'Y-m-d H:i:s', $d->getTimestamp() );
		};
		return array(
			'frequency'  => 'monthly' === $frequency ? 'monthly' : 'weekly',
			'from'       => $utc( $from ),
			'until'      => $utc( $until ),
			'prev_from'  => $utc( $prev_from ),
			'prev_until' => $utc( $prev_until ),
			'days'       => (int) round( ( $until->getTimestamp() - $from->getTimestamp() ) / DAY_IN_SECONDS ),
			'start'      => $from->format( 'Y-m-d' ),
			'end'        => $until->modify( '-1 day' )->format( 'Y-m-d' ),
			'offset'     => $from->getOffset(),
		);
	}

	/**
	 * Everything the report shows.
	 *
	 * @param array<string,mixed> $period From period().
	 * @return array<string,mixed>
	 */
	public function facts( array $period ) {
		$now  = $this->totals( $period['from'], $period['until'] );
		$prev = $this->totals( $period['prev_from'], $period['prev_until'] );
		return array(
			'period'    => $period,
			'totals'    => $now,
			'prev'      => $prev,
			'signals'   => $this->signal_labels(),
			'pages'     => $now['sessions'] ? $this->pages( $period['from'], $period['until'], 5 ) : array(),
			'errors'    => $now['sessions'] ? $this->errors( $period['from'], $period['until'], 3 ) : array(),
			'sources'   => $now['sessions'] ? $this->sources( $period['from'], $period['until'], 3 ) : array(),
			'countries' => $now['sessions'] ? $this->countries( $period['from'], $period['until'], 3 ) : array(),
			'busiest'   => $now['sessions'] ? $this->busiest_day( $period['from'], $period['until'], (int) $period['offset'] ) : null,
			'warnings'  => $this->warnings(),
			// A failed query must not look like a period with no visits.
			'db_error'  => $this->db_error,
		);
	}

	/**
	 * Totals for a period.
	 *
	 * @param string $from  UTC datetime (inclusive).
	 * @param string $until UTC datetime (exclusive).
	 * @return array<string,mixed>
	 */
	public function totals( $from, $until ) {
		global $wpdb;
		$s   = Schema::table( 'sessions' );
		$p   = Schema::table( 'pageviews' );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) AS sessions,
				 COALESCE(SUM(has_recording = 1), 0) AS recorded,
				 COALESCE(AVG(pageviews), 0) AS avg_pages,
				 COALESCE(AVG(duration_s), 0) AS avg_duration,
				 COALESCE(SUM(flags > 0), 0) AS frustrated,
				 COALESCE(SUM(is_returning = 1), 0) AS returning_visits,
				 COALESCE(SUM(device = 'desktop'), 0) AS desktop,
				 COALESCE(SUM(device = 'tablet'), 0) AS tablet,
				 COALESCE(SUM(device = 'mobile'), 0) AS mobile
				 FROM {$s} WHERE started_at >= %s AND started_at < %s AND pageviews > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$until
			)
		);
		if ( ! $row && '' !== (string) $wpdb->last_error ) {
			$this->db_error = (string) $wpdb->last_error;
		}
		$scroll = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COALESCE(AVG(max_scroll_pct), 0) FROM {$p} WHERE started_at >= %s AND started_at < %s", $from, $until ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$flags = array();
		foreach ( Signal_Types::flags() as $type => $bit ) {
			$flags[ $type ] = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE started_at >= %s AND started_at < %s AND pageviews > 0 AND (flags & %d) > 0", $from, $until, $bit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}
		$sessions = $row ? (int) $row->sessions : 0;
		$pct      = static function ( $n ) use ( $sessions ) {
			return $sessions ? round( 100 * (int) $n / $sessions, 1 ) : 0;
		};
		return array(
			'sessions'       => $sessions,
			'recorded'       => $row ? (int) $row->recorded : 0,
			'avg_pages'      => $row ? round( (float) $row->avg_pages, 1 ) : 0,
			'avg_duration'   => $row ? (int) round( (float) $row->avg_duration ) : 0,
			'avg_scroll'     => (int) round( $scroll ),
			'frustrated'     => $row ? (int) $row->frustrated : 0,
			'frustrated_pct' => $row ? $pct( $row->frustrated ) : 0,
			'returning'      => $row ? (int) $row->returning_visits : 0,
			'new'            => $row ? $sessions - (int) $row->returning_visits : 0,
			'devices'        => array(
				'desktop' => $row ? $pct( $row->desktop ) : 0,
				'tablet'  => $row ? $pct( $row->tablet ) : 0,
				'mobile'  => $row ? $pct( $row->mobile ) : 0,
			),
			'flags'          => $flags,
		);
	}

	/**
	 * Labels of the signal types (core and extra).
	 *
	 * @return array<string,string>
	 */
	public function signal_labels() {
		$labels = array(
			'rage'      => __( 'Rage clicks', 'qcin-textdomain' ),
			'dead'      => __( 'Dead clicks', 'qcin-textdomain' ),
			'error'     => __( 'JavaScript errors', 'qcin-textdomain' ),
			'quickback' => __( 'Quick backs', 'qcin-textdomain' ),
		);
		foreach ( Signal_Types::extra() as $type => $def ) {
			$labels[ $type ] = $def['label'];
		}
		return $labels;
	}

	/**
	 * Most viewed pages.
	 *
	 * @param string $from  UTC datetime.
	 * @param string $until UTC datetime.
	 * @param int    $limit Rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function pages( $from, $until, $limit ) {
		global $wpdb;
		$p    = Schema::table( 'pageviews' );
		$u    = Schema::table( 'urls' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.url_id, u.url, u.title, COUNT(*) AS views, AVG(p.duration_ms) AS avg_ms, AVG(p.max_scroll_pct) AS avg_scroll
				 FROM {$p} p LEFT JOIN {$u} u ON u.id = p.url_id
				 WHERE p.started_at >= %s AND p.started_at < %s
				 GROUP BY p.url_id, u.url, u.title ORDER BY views DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$until,
				(int) $limit
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'url_id'     => (int) $r->url_id,
				'path'       => self::visitor_text( self::path( (string) $r->url ), 90 ),
				'title'      => self::visitor_text( (string) $r->title, 80 ),
				'views'      => (int) $r->views,
				'avg_time_s' => (int) round( (float) $r->avg_ms / 1000 ),
				'avg_scroll' => (int) round( (float) $r->avg_scroll ),
			);
		}
		return $out;
	}

	/**
	 * Most frequent JavaScript errors, redacted.
	 *
	 * @param string $from  UTC datetime.
	 * @param string $until UTC datetime.
	 * @param int    $limit Rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function errors( $from, $until, $limit ) {
		global $wpdb;
		$sg   = Schema::table( 'signals' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT data, COUNT(DISTINCT session_id) AS sessions FROM {$sg}
				 WHERE type = 'error' AND created_at >= %s AND created_at < %s
				 GROUP BY data ORDER BY sessions DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$until,
				(int) $limit
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$data    = json_decode( (string) $r->data, true );
			$message = is_array( $data ) ? (string) ( $data['message'] ?? '' ) : '';
			$message = self::visitor_text( $message, 120 );
			if ( '' === $message ) {
				$message = __( 'Unknown error', 'qcin-textdomain' );
			}
			$out[] = array(
				'message'  => $message,
				'sessions' => (int) $r->sessions,
			);
		}
		return $out;
	}

	/**
	 * Top traffic sources: campaign source, else the referring site, else direct.
	 *
	 * @param string $from  UTC datetime.
	 * @param string $until UTC datetime.
	 * @param int    $limit Rows.
	 * @return array<int,array{label:string,sessions:int}>
	 */
	public function sources( $from, $until, $limit ) {
		global $wpdb;
		$s    = Schema::table( 'sessions' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(utm_source, ''), NULLIF(referrer_host, ''), '') AS src, COUNT(*) AS n FROM {$s}
				 WHERE started_at >= %s AND started_at < %s AND pageviews > 0
				 GROUP BY src ORDER BY n DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$until,
				(int) $limit
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$label = trim( (string) $r->src );
			if ( '' === $label ) {
				$label = __( 'Direct or unknown', 'qcin-textdomain' );
			} elseif ( ! preg_match( '/^[A-Za-z0-9._+-]{1,40}$/', $label ) ) {
				// Campaign names come from the visitor's link; only plain names are shown.
				$label = __( 'Other campaign', 'qcin-textdomain' );
			}
			$out[] = array(
				'label'    => $label,
				'sessions' => (int) $r->n,
			);
		}
		return $out;
	}

	/**
	 * Top countries (when known).
	 *
	 * @param string $from  UTC datetime.
	 * @param string $until UTC datetime.
	 * @param int    $limit Rows.
	 * @return array<int,array{label:string,sessions:int}>
	 */
	public function countries( $from, $until, $limit ) {
		global $wpdb;
		$s    = Schema::table( 'sessions' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT country, COUNT(*) AS n FROM {$s}
				 WHERE started_at >= %s AND started_at < %s AND pageviews > 0 AND country <> ''
				 GROUP BY country ORDER BY n DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$until,
				(int) $limit
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'label'    => self::country_name( (string) $r->country ),
				'sessions' => (int) $r->n,
			);
		}
		return $out;
	}

	/**
	 * Day with the most visits (site time).
	 *
	 * @param string $from   UTC datetime.
	 * @param string $until  UTC datetime.
	 * @param int    $offset Site UTC offset in seconds.
	 * @return array{day:string,sessions:int}|null
	 */
	public function busiest_day( $from, $until, $offset ) {
		global $wpdb;
		$s   = Schema::table( 'sessions' );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DATE(DATE_ADD(started_at, INTERVAL %d SECOND)) AS day, COUNT(*) AS n FROM {$s}
				 WHERE started_at >= %s AND started_at < %s AND pageviews > 0
				 GROUP BY day ORDER BY n DESC, day ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $offset,
				$from,
				$until
			)
		);
		return $row ? array(
			'day'      => (string) $row->day,
			'sessions' => (int) $row->n,
		) : null;
	}

	/**
	 * Problems worth a line in the report, only when there are any.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$out = array();
		if ( ! Settings::get( 'enabled' ) ) {
			$out[] = __( 'Tracking is switched off, so no new visits are being recorded. Turn it on in Settings > Recording.', 'qcin-textdomain' );
		}
		$cap = (int) Settings::get( 'storage_cap_mb' ) * 1048576;
		if ( $cap > 0 ) {
			$used = Scheduler::used_bytes( new Session_Repository() );
			$pct  = (int) floor( 100 * $used / $cap );
			if ( $pct >= 80 ) {
				$out[] = sprintf(
					/* translators: %d: percent of the storage limit in use */
					__( 'Recordings use %d%% of the storage limit. When it is full, the oldest recordings are deleted first. You can raise the limit in Settings > Storage.', 'qcin-textdomain' ),
					min( 100, $pct )
				);
			}
		}
		$health = Health_Check::report( false );
		if ( ! empty( $health['jobs_overdue'] ) ) {
			$out[] = __( 'Background jobs have not run for over an hour, so visits are not being processed. This usually means WP-Cron is not running on this site.', 'qcin-textdomain' );
		}
		/**
		 * Warnings shown in the email report (plain text).
		 *
		 * @param string[] $out Warnings.
		 */
		return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'qcin/report_warnings', $out ) ) ) );
	}

	/**
	 * Text that comes from visitors' browsers (error messages, page titles):
	 * no tags, no links, no email addresses or phone numbers, short. Anyone
	 * can send such text, so the report never shows it as a link.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function visitor_text( $text, $max ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = (string) preg_replace( '#\b(?:https?|ftp)://\S+|\bwww\.\S+#i', '[link]', $text );
		$text = Digest_Builder::redact( $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . "\u{2026}" : $text;
	}

	/**
	 * Path of a stored URL (no host, no query string).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function path( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	/**
	 * Country name from a two-letter code, in the site's language when the
	 * intl extension is there.
	 *
	 * @param string $code ISO code.
	 * @return string
	 */
	public static function country_name( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', $code ) );
		if ( class_exists( '\Locale' ) && 2 === strlen( $code ) ) {
			$name = \Locale::getDisplayRegion( '-' . $code, get_locale() );
			if ( is_string( $name ) && '' !== $name && $name !== $code ) {
				return $name;
			}
		}
		return $code;
	}
}
