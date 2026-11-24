<?php
/**
 * Chat Sessions date filter: the presets (Last Week, Last Month, Last 3 Months) and a custom
 * From / To range, for the list and for Export All (owner, 24 November 2026: "Is it possible to
 * create a custom date range for export?").
 *
 * The same class ships in WPBot Pro (addons/chat-session-addon/inc) and WPBot Free
 * (includes/chat-sessions/inc); only the text domain differs.
 *
 * @package WPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QCLD_WPCS_Date_Range' ) ) {

	/**
	 * Date range of the Chat Sessions list and its export.
	 */
	final class QCLD_WPCS_Date_Range {

		/**
		 * Request keys.
		 */
		const FROM   = 'wpcs_from';
		const TO     = 'wpcs_to';
		const PRESET = 'FilterDate';

		/**
		 * Presets: days back from today.
		 */
		const PRESETS = array(
			'LastWeek'    => 7,
			'LastMonth'   => 30,
			'Last3Months' => 90,
		);

		/**
		 * The range a request asks for. A custom range wins over a preset; From and To are swapped
		 * when From is later; anything that is not a real date is ignored.
		 *
		 * @param array $src $_GET or $_POST (unslashed or not: only digits and dashes are kept).
		 * @return array from (Y-m-d or ''), to (Y-m-d or ''), preset (key of PRESETS or '').
		 */
		public static function from_request( array $src ) {
			$from   = self::clean_date( isset( $src[ self::FROM ] ) ? $src[ self::FROM ] : '' );
			$to     = self::clean_date( isset( $src[ self::TO ] ) ? $src[ self::TO ] : '' );
			$preset = isset( $src[ self::PRESET ] ) && is_string( $src[ self::PRESET ] ) ? trim( $src[ self::PRESET ] ) : '';
			if ( '' !== $from && '' !== $to && $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			if ( '' !== $from || '' !== $to || ! isset( self::PRESETS[ $preset ] ) ) {
				$preset = '';
			}
			return array(
				'from'   => $from,
				'to'     => $to,
				'preset' => $preset,
			);
		}

		/**
		 * A Y-m-d date that exists, or ''.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public static function clean_date( $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return '';
			}
			return $value;
		}

		/**
		 * Whether a range filters anything.
		 *
		 * @param array $range Range.
		 * @return bool
		 */
		public static function active( array $range ) {
			return '' !== $range['from'] || '' !== $range['to'] || '' !== $range['preset'];
		}

		/**
		 * SQL to add to a WHERE clause: " AND …", or '' for no range. From and To include the
		 * whole day.
		 *
		 * @param array  $range  Range.
		 * @param object $db     $wpdb (anything with prepare()).
		 * @param string $column Date column, already quoted.
		 * @return string
		 */
		public static function where( array $range, $db, $column = '`date`' ) {
			if ( '' !== $range['preset'] ) {
				return ' AND ' . $column . ' >= CURDATE() - INTERVAL ' . (int) self::PRESETS[ $range['preset'] ] . ' DAY';
			}
			$sql = '';
			if ( '' !== $range['from'] ) {
				$sql .= ' AND ' . $column . ' >= ' . $db->prepare( '%s', $range['from'] . ' 00:00:00' );
			}
			if ( '' !== $range['to'] ) {
				$sql .= ' AND ' . $column . ' <= ' . $db->prepare( '%s', $range['to'] . ' 23:59:59' );
			}
			return $sql;
		}

		/**
		 * The range in words, for "Filtering Records by:".
		 *
		 * @param array $range Range.
		 * @return string
		 */
		public static function label( array $range ) {
			$presets = array(
				'LastWeek'    => __( 'LAST WEEK', 'chatbot' ),
				'LastMonth'   => __( 'LAST MONTH', 'chatbot' ),
				'Last3Months' => __( 'LAST 3 MONTHS', 'chatbot' ),
			);
			if ( '' !== $range['preset'] ) {
				return $presets[ $range['preset'] ];
			}
			$from = '' !== $range['from'] ? self::day( $range['from'] ) : '';
			$to   = '' !== $range['to'] ? self::day( $range['to'] ) : '';
			if ( '' !== $from && '' !== $to ) {
				return $from . ' – ' . $to;
			}
			if ( '' !== $from ) {
				/* translators: %s: a date */
				return sprintf( __( 'From %s', 'chatbot' ), $from );
			}
			if ( '' !== $to ) {
				/* translators: %s: a date */
				return sprintf( __( 'Until %s', 'chatbot' ), $to );
			}
			return '';
		}

		/**
		 * A date as the site shows dates.
		 *
		 * @param string $ymd Y-m-d.
		 * @return string
		 */
		private static function day( $ymd ) {
			$time = strtotime( $ymd . ' 12:00:00 UTC' );
			if ( function_exists( 'date_i18n' ) && function_exists( 'get_option' ) ) {
				return date_i18n( (string) get_option( 'date_format', 'j M Y' ), $time, true );
			}
			return gmdate( 'j M Y', $time );
		}

		/**
		 * The export's file name, with the range in it.
		 *
		 * @param array  $range Range.
		 * @param string $today Today (Y-m-d).
		 * @return string
		 */
		public static function filename( array $range, $today ) {
			$base = 'wpbot_chatsession_';
			if ( '' !== $range['from'] || '' !== $range['to'] ) {
				return $base . ( '' !== $range['from'] ? $range['from'] : 'start' ) . '_to_' . ( '' !== $range['to'] ? $range['to'] : $today ) . '.csv';
			}
			if ( '' !== $range['preset'] ) {
				return $base . strtolower( (string) preg_replace( '/(?<!^)([A-Z0-9])/', '-$1', $range['preset'] ) ) . '_' . $today . '.csv';
			}
			return $base . $today . '.csv';
		}

		/**
		 * Query arguments that keep the range (links, the export form).
		 *
		 * @param array $range Range.
		 * @return array
		 */
		public static function args( array $range ) {
			return array_filter(
				array(
					self::FROM   => $range['from'],
					self::TO     => $range['to'],
					self::PRESET => $range['preset'],
				),
				'strlen'
			);
		}

		/**
		 * The From / To form next to the preset buttons. It keeps the page's other filters.
		 *
		 * @param array  $range Range.
		 * @param array  $keep  Other query arguments to keep (page, status, …).
		 * @param string $clear Address of the list without a date filter.
		 * @return void
		 */
		public static function form( array $range, array $keep, $clear ) {
			echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="qcld-wpcs-range">';
			foreach ( $keep as $key => $value ) {
				if ( '' !== (string) $value ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}
			echo '<label>' . esc_html__( 'From', 'chatbot' ) . ' <input type="date" name="' . esc_attr( self::FROM ) . '" value="' . esc_attr( $range['from'] ) . '"></label> ';
			echo '<label>' . esc_html__( 'To', 'chatbot' ) . ' <input type="date" name="' . esc_attr( self::TO ) . '" value="' . esc_attr( $range['to'] ) . '"></label> ';
			echo '<button type="submit" class="btn btn-success qcld-wpcs-range-apply">' . esc_html__( 'Apply', 'chatbot' ) . '</button>';
			if ( self::active( $range ) ) {
				echo ' <a class="qcld-wpcs-range-clear" href="' . esc_url( $clear ) . '">' . esc_html__( 'Clear', 'chatbot' ) . '</a>';
			}
			echo '</form>';
		}
	}
}
