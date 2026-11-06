<?php
/**
 * CR-16 F3: original research from the owner's own data. A table (an
 * uploaded CSV, a CSV in the chatbot knowledge, or a dataset WPBot Pro
 * builds from activity) is analysed here, in PHP: counts, shares,
 * averages, groups compared and a monthly trend. The AI never computes a
 * number. Groups smaller than the owner's minimum are hidden, the method
 * is written down, and nothing reaches the writing until the owner
 * approves the numbers.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

use QuantumCloud\WPBotCore\Core;
use QuantumCloud\AiWriter\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Research from tables.
 */
final class Research_Data {

	/**
	 * Post meta: the approved research the piece was written from.
	 */
	const META = '_qcld_wpbot_writer_research';

	/**
	 * Waiting analyses (per user, one day).
	 */
	const TRANSIENT = 'qcld_wpbot_writer_research_';

	/**
	 * Limits.
	 */
	const MAX_ROWS    = 20000;
	const MAX_COLUMNS = 30;
	const MAX_BYTES   = 5242880;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'remember' ), 10, 2 );
	}

	/**
	 * Keep the approved research with the post (for the CSV download).
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function remember( $post_id, $brief ) {
		if ( is_array( $brief ) && ! empty( $brief['research'] ) ) {
			update_post_meta( (int) $post_id, self::META, $brief['research'] );
		}
	}

	/**
	 * Smallest group shown (owner setting, at least 5).
	 *
	 * @return int
	 */
	public static function min_group() {
		/**
		 * Smallest group original research shows; smaller groups are hidden.
		 *
		 * @param int $min Minimum rows per group (Settings → Writing; default 10).
		 */
		return max( 5, (int) apply_filters( 'qcld_wpbot_writer/research_min_group', (int) Settings::get( 'research_min_group' ) ) );
	}

	/**
	 * Read a CSV (or tab-separated) text into a table.
	 *
	 * @param string $text  File contents.
	 * @param string $label Name shown to the owner.
	 * @return array|WP_Error label, columns, rows.
	 */
	public static function parse_csv( $text, $label ) {
		$text = (string) $text;
		if ( strlen( $text ) > self::MAX_BYTES ) {
			return new WP_Error( 'qcld_wpbot_writer_research_big', __( 'This file is larger than 5 MB.', 'chatbot' ), array( 'status' => 413 ) );
		}
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		$first     = (string) strtok( $text, "\n" );
		$delimiter = substr_count( $first, "\t" ) > substr_count( $first, ',' ) ? "\t" : ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',' );
		$handle    = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );
		$header = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
		if ( ! is_array( $header ) || count( array_filter( $header, 'strlen' ) ) < 1 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			return new WP_Error( 'qcld_wpbot_writer_research_empty', __( 'This file has no header row.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$columns = array();
		foreach ( array_slice( $header, 0, self::MAX_COLUMNS ) as $i => $name ) {
			$name      = trim( sanitize_text_field( (string) $name ) );
			$columns[] = '' !== $name ? substr( $name, 0, 80 ) : sprintf( 'Column %d', $i + 1 );
		}
		$rows  = array();
		$count = 0;
		while ( $count < self::MAX_ROWS ) {
			$line = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
			if ( false === $line ) {
				break;
			}
			if ( array( null ) === $line ) {
				continue;
			}
			++$count;
			$row = array();
			foreach ( $columns as $i => $name ) {
				$row[ $name ] = isset( $line[ $i ] ) ? substr( trim( (string) $line[ $i ] ), 0, 200 ) : '';
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return array(
			'label'   => sanitize_text_field( (string) $label ),
			'columns' => $columns,
			'rows'    => $rows,
		);
	}

	/**
	 * CSV files in the chatbot knowledge, through the host (WPBot Pro).
	 *
	 * @return array[] id, title, rows.
	 */
	public static function knowledge_files() {
		$read = self::knowledge_reader();
		if ( null === $read ) {
			return array();
		}
		$list = call_user_func( $read, '' );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $file ) {
			if ( is_array( $file ) && ! empty( $file['id'] ) ) {
				$out[] = array(
					'id'    => sanitize_key( (string) $file['id'] ),
					'title' => sanitize_text_field( (string) $file['title'] ),
					'rows'  => isset( $file['rows'] ) ? (int) $file['rows'] : 0,
				);
			}
		}
		return $out;
	}

	/**
	 * One knowledge CSV as a table.
	 *
	 * @param string $id File id.
	 * @return array|WP_Error label, columns, rows.
	 */
	public static function knowledge_table( $id ) {
		$read = self::knowledge_reader();
		if ( null === $read ) {
			return new WP_Error( 'qcld_wpbot_writer_no_knowledge_files', __( 'Chatbot knowledge files are not available on this site.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$doc = call_user_func( $read, sanitize_key( (string) $id ) );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		if ( ! is_array( $doc ) || empty( $doc['columns'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_research_empty', __( 'This knowledge file has no rows.', 'chatbot' ), array( 'status' => 422 ) );
		}
		return self::table( isset( $doc['title'] ) ? $doc['title'] : '', (array) $doc['columns'], (array) $doc['rows'] );
	}

	/**
	 * A table from columns and rows given by code (knowledge, WPBot Pro),
	 * capped and sanitised like an upload.
	 *
	 * @param string  $label   Name.
	 * @param array   $columns Column names.
	 * @param array[] $rows    Rows keyed by column name.
	 * @return array label, columns, rows.
	 */
	public static function table( $label, array $columns, array $rows ) {
		$clean = array();
		foreach ( array_slice( $columns, 0, self::MAX_COLUMNS ) as $name ) {
			$name = substr( trim( sanitize_text_field( (string) $name ) ), 0, 80 );
			if ( '' !== $name && ! in_array( $name, $clean, true ) ) {
				$clean[] = $name;
			}
		}
		$out = array();
		foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $row ) {
			$line = array();
			foreach ( $clean as $name ) {
				$line[ $name ] = is_array( $row ) && isset( $row[ $name ] ) && is_scalar( $row[ $name ] ) ? substr( trim( sanitize_text_field( (string) $row[ $name ] ) ), 0, 200 ) : '';
			}
			$out[] = $line;
		}
		return array(
			'label'   => sanitize_text_field( (string) $label ),
			'columns' => $clean,
			'rows'    => $out,
		);
	}

	/**
	 * The host's reader of knowledge files, or null.
	 *
	 * @return callable|null
	 */
	private static function knowledge_reader() {
		$host = Core::instance()->host();
		if ( ! $host || ! method_exists( $host, 'get_knowledge_documents' ) ) {
			return null;
		}
		$read = $host->get_knowledge_documents();
		return is_callable( $read ) ? $read : null;
	}

	/**
	 * Analyse a table: numbers computed here, small groups hidden.
	 *
	 * @param array  $table  label, columns, rows.
	 * @param string $source Where it came from (upload, knowledge or a Pro source id).
	 * @return array|WP_Error id, label, source, rows, columns, findings, hidden, method, period.
	 */
	public static function analyse( array $table, $source = 'upload' ) {
		$min  = self::min_group();
		$rows = isset( $table['rows'] ) ? (array) $table['rows'] : array();
		// A weight column counts each row that many times (clicks, items sold); the unit names what is counted.
		$weight  = isset( $table['weight'] ) && in_array( $table['weight'], (array) $table['columns'], true ) ? (string) $table['weight'] : '';
		$unit    = ! empty( $table['unit'] ) ? sanitize_text_field( (string) $table['unit'] ) : __( 'rows', 'chatbot' );
		$weights = array();
		foreach ( $rows as $i => $row ) {
			$weights[ $i ] = '' === $weight ? 1 : max( 0, (int) round( (float) self::number( $row[ $weight ] ) ) );
		}
		$n = (int) array_sum( $weights );
		if ( $n < $min ) {
			/* translators: 1: amount found, 2: unit (rows, chats…), 3: amount needed. */
			return new WP_Error( 'qcld_wpbot_writer_research_small', sprintf( __( 'Not enough data yet: %1$d %2$s, and research needs at least %3$d. Small samples give misleading numbers.', 'chatbot' ), $n, $unit, $min ), array( 'status' => 422 ) );
		}
		$types = array();
		foreach ( (array) $table['columns'] as $column ) {
			$types[ $column ] = $column === $weight ? 'weight' : self::column_type( array_column( $rows, $column ), count( $rows ) );
		}
		$cats     = array_keys( $types, 'category', true );
		$nums     = array_keys( $types, 'number', true );
		$dates    = array_keys( $types, 'date', true );
		$findings = array();
		$hidden   = array(
			'groups' => 0,
			'rows'   => 0,
		);

		foreach ( array_slice( $cats, 0, 3 ) as $column ) {
			$counts = array();
			foreach ( $rows as $i => $row ) {
				$value = (string) $row[ $column ];
				if ( '' !== $value ) {
					$counts[ $value ] = ( isset( $counts[ $value ] ) ? $counts[ $value ] : 0 ) + $weights[ $i ];
				}
			}
			arsort( $counts );
			$shown = array();
			foreach ( $counts as $value => $count ) {
				if ( $count < $min ) {
					++$hidden['groups'];
					$hidden['rows'] += $count;
					continue;
				}
				$shown[ (string) $value ] = $count;
			}
			if ( count( $shown ) < 2 ) {
				continue;
			}
			$top   = array_slice( $shown, 0, 10, true );
			$first = (string) array_key_first( $top );
			$grid  = array();
			$chart = array();
			foreach ( $top as $value => $count ) {
				$grid[]  = array( (string) $value, number_format_i18n( $count ), self::pct( $count, $n ) );
				$chart[] = array(
					'label' => (string) $value,
					'value' => (int) $count,
				);
			}
			$findings[] = array(
				'id'    => 'share_' . sanitize_key( $column ),
				/* translators: %s: column name. */
				'title' => sprintf( __( '%s: how often', 'chatbot' ), $column ),
				/* translators: 1: column, 2: value, 3: share, 4: total, 5: unit (rows, chats…). */
				'text'  => sprintf( __( 'The most common %1$s is %2$s: %3$s of %4$s %5$s.', 'chatbot' ), strtolower( $column ), $first, self::pct( $top[ $first ], $n ), number_format_i18n( $n ), $unit ),
				'head'  => array( $column, ucfirst( $unit ), __( 'Share', 'chatbot' ) ),
				'rows'  => $grid,
				'chart' => $chart,
			);
		}

		foreach ( array_slice( $nums, 0, 3 ) as $column ) {
			$values = self::numbers( array_column( $rows, $column ) );
			if ( count( $values ) < $min ) {
				continue;
			}
			sort( $values );
			$avg        = array_sum( $values ) / count( $values );
			$median     = self::median( $values );
			$findings[] = array(
				'id'    => 'average_' . sanitize_key( $column ),
				/* translators: %s: column name. */
				'title' => sprintf( __( '%s: typical value', 'chatbot' ), $column ),
				/* translators: 1: column, 2: average, 3: median, 4: lowest, 5: highest, 6: rows. */
				'text'  => sprintf( __( 'The average %1$s is %2$s (median %3$s, from %4$s to %5$s, across %6$s rows).', 'chatbot' ), strtolower( $column ), self::num( $avg ), self::num( $median ), self::num( $values[0] ), self::num( end( $values ) ), number_format_i18n( count( $values ) ) ),
				'head'  => array( __( 'Measure', 'chatbot' ), $column ),
				'rows'  => array(
					array( __( 'Average', 'chatbot' ), self::num( $avg ) ),
					array( __( 'Median', 'chatbot' ), self::num( $median ) ),
					array( __( 'Lowest', 'chatbot' ), self::num( $values[0] ) ),
					array( __( 'Highest', 'chatbot' ), self::num( end( $values ) ) ),
				),
				'chart' => array(),
			);
		}

		if ( array() !== $cats && array() !== $nums ) {
			$group  = $cats[0];
			$metric = $nums[0];
			$sets   = array();
			foreach ( $rows as $row ) {
				$key   = (string) $row[ $group ];
				$value = self::number( $row[ $metric ] );
				if ( '' !== $key && null !== $value ) {
					$sets[ $key ][] = $value;
				}
			}
			$avgs = array();
			foreach ( $sets as $key => $values ) {
				if ( count( $values ) >= $min ) {
					$avgs[ $key ] = array_sum( $values ) / count( $values );
				}
			}
			arsort( $avgs );
			if ( count( $avgs ) >= 2 ) {
				$best       = (string) array_key_first( $avgs );
				$findings[] = array(
					'id'    => 'compare_' . sanitize_key( $group . '_' . $metric ),
					/* translators: 1: number column, 2: group column. */
					'title' => sprintf( __( '%1$s by %2$s', 'chatbot' ), $metric, strtolower( $group ) ),
					/* translators: 1: group value, 2: number column, 3: average. */
					'text'  => sprintf( __( '%1$s has the highest average %2$s: %3$s.', 'chatbot' ), $best, strtolower( $metric ), self::num( $avgs[ $best ] ) ),
					'head'  => array( $group, __( 'Average', 'chatbot' ) . ' ' . strtolower( $metric ), __( 'Rows', 'chatbot' ) ),
					'rows'  => array_map(
						static function ( $key ) use ( $avgs, $sets ) {
							return array( (string) $key, self::num( $avgs[ $key ] ), number_format_i18n( count( $sets[ $key ] ) ) );
						},
						array_slice( array_keys( $avgs ), 0, 10 )
					),
					'chart' => array_map(
						static function ( $key ) use ( $avgs ) {
							return array(
								'label' => (string) $key,
								'value' => round( $avgs[ $key ], 2 ),
							);
						},
						array_slice( array_keys( $avgs ), 0, 10 )
					),
				);
			}
		}

		$period = array();
		if ( array() !== $dates ) {
			$months = array();
			foreach ( $rows as $i => $row ) {
				$time = strtotime( (string) $row[ $dates[0] ] );
				if ( false !== $time ) {
					$month            = gmdate( 'Y-m', $time );
					$months[ $month ] = ( isset( $months[ $month ] ) ? $months[ $month ] : 0 ) + $weights[ $i ];
				}
			}
			ksort( $months );
			if ( array() !== $months ) {
				$period = array( array_key_first( $months ), array_key_last( $months ) );
			}
			$shown = array_filter(
				$months,
				static function ( $count ) use ( $min ) {
					return $count >= $min;
				}
			);
			if ( count( $shown ) >= 2 ) {
				$keys       = array_keys( $shown );
				$from       = $keys[0];
				$to         = end( $keys );
				$change     = $shown[ $to ] - $shown[ $from ];
				$findings[] = array(
					'id'    => 'trend_' . sanitize_key( $dates[0] ),
					/* translators: %s: unit (rows, chats…). */
					'title' => sprintf( __( '%s per month', 'chatbot' ), ucfirst( $unit ) ),
					'text'  => sprintf(
						/* translators: 1: unit, 2: rose/fell/stayed, 3: first count, 4: first month, 5: last count, 6: last month. */
						__( '%1$s per month %2$s from %3$s in %4$s to %5$s in %6$s.', 'chatbot' ),
						ucfirst( $unit ),
						$change > 0 ? __( 'rose', 'chatbot' ) : ( $change < 0 ? __( 'fell', 'chatbot' ) : __( 'stayed level', 'chatbot' ) ),
						number_format_i18n( $shown[ $from ] ),
						$from,
						number_format_i18n( $shown[ $to ] ),
						$to
					),
					'head'  => array( __( 'Month', 'chatbot' ), ucfirst( $unit ) ),
					'rows'  => array_map(
						static function ( $month ) use ( $shown ) {
							return array( $month, number_format_i18n( $shown[ $month ] ) );
						},
						array_slice( $keys, -12 )
					),
					'chart' => array_map(
						static function ( $month ) use ( $shown ) {
							return array(
								'label' => $month,
								'value' => (int) $shown[ $month ],
							);
						},
						array_slice( $keys, -12 )
					),
				);
			}
		}

		if ( array() === $findings ) {
			return new WP_Error( 'qcld_wpbot_writer_research_none', __( 'No finding could be computed: the table needs a column of categories (for example product or city), numbers, or dates, with enough rows in each group.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$label            = '' !== (string) $table['label'] ? (string) $table['label'] : __( 'your data', 'chatbot' );
		$result           = array(
			'label'    => $label,
			'source'   => sanitize_key( (string) $source ),
			'rows'     => $n,
			'unit'     => $unit,
			'columns'  => array_map(
				static function ( $name ) use ( $types ) {
					return array(
						'name' => $name,
						'type' => $types[ $name ],
					);
				},
				array_keys( $types )
			),
			'findings' => array_slice( $findings, 0, 8 ),
			'hidden'   => $hidden,
			'period'   => $period,
			'min'      => $min,
		);
		$result['method'] = self::method( $result );
		$result['id']     = substr( md5( wp_json_encode( $result ) ), 0, 16 );
		return $result;
	}

	/**
	 * The method in plain words (the article's method section uses it).
	 *
	 * @param array $result Analysis.
	 * @return string
	 */
	public static function method( array $result ) {
		$parts = array(
			/* translators: 1: amount, 2: unit (rows, chats…), 3: source name. */
			sprintf( __( 'Based on %1$s %2$s from %3$s.', 'chatbot' ), number_format_i18n( (int) $result['rows'] ), $result['unit'], $result['label'] ),
		);
		if ( ! empty( $result['period'] ) ) {
			/* translators: 1: first month, 2: last month. */
			$parts[] = sprintf( __( 'Period: %1$s to %2$s.', 'chatbot' ), $result['period'][0], $result['period'][1] );
		}
		/* translators: 1: minimum group size, 2: unit. */
		$parts[] = sprintf( __( 'Groups with fewer than %1$d %2$s are not shown.', 'chatbot' ), (int) $result['min'], $result['unit'] );
		if ( ! empty( $result['hidden']['groups'] ) ) {
			/* translators: 1: groups hidden, 2: amount in them, 3: unit. */
			$parts[] = sprintf( _n( '%1$d small group (%2$d %3$s) was left out.', '%1$d small groups (%2$d %3$s) were left out.', (int) $result['hidden']['groups'], 'chatbot' ), (int) $result['hidden']['groups'], (int) $result['hidden']['rows'], $result['unit'] );
		}
		$parts[] = __( 'Counts, shares and averages were calculated directly from the data, not estimated by AI.', 'chatbot' );
		return implode( ' ', $parts );
	}

	/**
	 * Keep an analysis for the owner to approve (one day).
	 *
	 * @param array $result Analysis.
	 * @return void
	 */
	public static function store( array $result ) {
		set_transient( self::TRANSIENT . get_current_user_id() . '_' . $result['id'], $result, DAY_IN_SECONDS );
	}

	/**
	 * An analysis this user ran, by id (null when gone).
	 *
	 * @param string $id Id.
	 * @return array|null
	 */
	public static function stored( $id ) {
		$id = preg_replace( '/[^a-f0-9]/', '', (string) $id );
		if ( '' === $id || ! get_current_user_id() ) {
			return null;
		}
		$result = get_transient( self::TRANSIENT . get_current_user_id() . '_' . $id );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * The approved findings as plain text for the prompts and the checks.
	 *
	 * @param array $result Analysis.
	 * @return string
	 */
	public static function text( array $result ) {
		$lines = array( 'Original research from ' . $result['label'] . ' (approved by the owner; use these numbers exactly, and no others):' );
		foreach ( (array) $result['findings'] as $finding ) {
			$lines[] = '- ' . $finding['text'];
			foreach ( (array) $finding['rows'] as $row ) {
				$lines[] = '  ' . implode( ' | ', array_map( 'strval', $row ) );
			}
		}
		$lines[] = 'Method: ' . $result['method'];
		return implode( "\n", $lines );
	}

	/**
	 * The data blocks for the article: per finding a table and, where it
	 * has one, a bar chart (inline SVG, no script).
	 *
	 * @param array $result Analysis.
	 * @return array[] Block_Builder items, already escaped (they bypass normalise()).
	 */
	public static function blocks( array $result ) {
		$items = array();
		foreach ( (array) $result['findings'] as $finding ) {
			$items[] = array(
				'type'  => 'heading',
				'text'  => esc_html( $finding['title'] ),
				'level' => 3,
			);
			if ( ! empty( $finding['chart'] ) ) {
				$items[] = array(
					'type' => 'chart',
					'svg'  => self::chart_svg( $finding['chart'], $finding['title'] ),
				);
			}
			$items[] = array(
				'type' => 'table',
				'head' => array_map( 'esc_html', array_map( 'strval', (array) $finding['head'] ) ),
				'rows' => array_map(
					static function ( $row ) {
						return array_map( 'esc_html', array_map( 'strval', (array) $row ) );
					},
					(array) $finding['rows']
				),
			);
		}
		$items[] = array(
			'type' => 'paragraph',
			'text' => esc_html( $result['method'] ),
		);
		return $items;
	}

	/**
	 * A horizontal bar chart as SVG.
	 *
	 * @param array[] $bars  label, value.
	 * @param string  $title Accessible title.
	 * @return string
	 */
	public static function chart_svg( array $bars, $title ) {
		$bars  = array_slice( $bars, 0, 12 );
		$max   = max( 1, max( array_map( 'floatval', wp_list_pluck( $bars, 'value' ) ) ) );
		$row   = 28;
		$width = 640;
		$label = 180;
		$out   = '<svg xmlns="http://www.w3.org/2000/svg" role="img" class="qcwr-chart" viewBox="0 0 ' . $width . ' ' . ( count( $bars ) * $row + 8 ) . '" width="100%"><title>' . esc_html( $title ) . '</title>';
		foreach ( array_values( $bars ) as $i => $bar ) {
			$y      = $i * $row + 4;
			$length = max( 2, (int) round( ( (float) $bar['value'] / $max ) * ( $width - $label - 70 ) ) );
			$text   = function_exists( 'mb_strimwidth' ) ? mb_strimwidth( (string) $bar['label'], 0, 26, '…' ) : substr( (string) $bar['label'], 0, 26 );
			$out   .= '<text x="' . ( $label - 8 ) . '" y="' . ( $y + 17 ) . '" text-anchor="end" font-size="13" fill="currentColor">' . esc_html( $text ) . '</text>'
				. '<rect x="' . $label . '" y="' . ( $y + 4 ) . '" width="' . $length . '" height="18" rx="3" fill="#5b4e96"></rect>'
				. '<text x="' . ( $label + $length + 6 ) . '" y="' . ( $y + 17 ) . '" font-size="13" fill="currentColor">' . esc_html( self::num( (float) $bar['value'] ) ) . '</text>';
		}
		return $out . '</svg>';
	}

	/**
	 * CSV of the approved findings (each finding's table), cells escaped
	 * against formula injection.
	 *
	 * @param array $result Analysis.
	 * @return string
	 */
	public static function to_csv( array $result ) {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		foreach ( (array) $result['findings'] as $finding ) {
			fputcsv( $handle, array( self::cell( $finding['title'] ) ), ',', '"', '\\' );
			fputcsv( $handle, array_map( array( __CLASS__, 'cell' ), (array) $finding['head'] ), ',', '"', '\\' );
			foreach ( (array) $finding['rows'] as $row ) {
				fputcsv( $handle, array_map( array( __CLASS__, 'cell' ), (array) $row ), ',', '"', '\\' );
			}
			fputcsv( $handle, array(), ',', '"', '\\' );
		}
		fputcsv( $handle, array( self::cell( $result['method'] ) ), ',', '"', '\\' );
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return $csv;
	}

	/**
	 * A CSV cell that a spreadsheet will not run as a formula (OWASP).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function cell( $value ) {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}

	/**
	 * What a column holds: number, date, category or text.
	 *
	 * @param array $values Values.
	 * @param int   $n      Rows.
	 * @return string
	 */
	private static function column_type( array $values, $n ) {
		$filled = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );
		$count  = count( $filled );
		if ( $count < max( 2, (int) ( $n * 0.5 ) ) ) {
			return 'text';
		}
		$numbers = count( self::numbers( $filled ) );
		if ( $numbers >= $count * 0.8 ) {
			return 'number';
		}
		$dates = 0;
		foreach ( $filled as $value ) {
			if ( preg_match( '/^\d{4}-\d{2}(-\d{2})?|^\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}/', $value ) && false !== strtotime( $value ) ) {
				++$dates;
			}
		}
		if ( $dates >= $count * 0.8 ) {
			return 'date';
		}
		$distinct = count( array_unique( $filled ) );
		return $distinct >= 2 && $distinct <= 50 && $distinct <= $count * 0.6 ? 'category' : 'text';
	}

	/**
	 * Numbers among values (currency signs, thousands separators and % allowed).
	 *
	 * @param array $values Values.
	 * @return float[]
	 */
	private static function numbers( array $values ) {
		$out = array();
		foreach ( $values as $value ) {
			$number = self::number( $value );
			if ( null !== $number ) {
				$out[] = $number;
			}
		}
		return $out;
	}

	/**
	 * One value as a number, or null.
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private static function number( $value ) {
		$value = trim( str_replace( array( ',', '%', '$', '£', '€', ' ' ), '', (string) $value ) );
		return '' !== $value && is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Median of sorted numbers.
	 *
	 * @param float[] $values Sorted values.
	 * @return float
	 */
	private static function median( array $values ) {
		$count = count( $values );
		$mid   = (int) floor( $count / 2 );
		return $count % 2 ? $values[ $mid ] : ( $values[ $mid - 1 ] + $values[ $mid ] ) / 2;
	}

	/**
	 * A share as a percentage.
	 *
	 * @param int $part  Part.
	 * @param int $whole Whole.
	 * @return string
	 */
	private static function pct( $part, $whole ) {
		return number_format_i18n( $whole > 0 ? $part * 100 / $whole : 0, 0 ) . '%';
	}

	/**
	 * A number for reading (no decimals for whole numbers, else up to 2).
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private static function num( $value ) {
		return number_format_i18n( (float) $value, floor( (float) $value ) === (float) $value ? 0 : ( abs( $value ) >= 100 ? 0 : 2 ) );
	}
}
