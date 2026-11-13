<?php
/**
 * Keyword Vault actions in SEO & AI Search (S14). The Vault itself lives in
 * WPBot Core; this class adds what the module does with it:
 *
 * - save keywords from research (with their real numbers only when a
 *   provider, Search Console or Google Ads supplied them);
 * - Write (AI Writer brief), Assign to page (manual, locked entry in the
 *   shared keyword map), Add to calendar (AI Writer ideas);
 * - CSV import (size limit, sanitised) and export (formula cells escaped);
 * - Search Console positions after each import, which move keywords into
 *   and out of "ranking". Never estimated by AI.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Map\Topic_Map;
use QuantumCloud\WPBotCore\Vault\Vault;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Vault actions.
 */
final class Vault_Service {

	/**
	 * CSV limits.
	 */
	const CSV_BYTES = 2097152;
	const CSV_ROWS  = 5000;

	/**
	 * Hook rank updates.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/gsc_pulled', array( __CLASS__, 'ranks_from_search_console' ) );
	}

	/**
	 * Is the Vault available (WPBot Core with S14)?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Vault::class );
	}

	/**
	 * Save keywords from a research run (or typed by hand).
	 *
	 * @param array[] $items       keyword, intent, demand, difficulty, cluster, reason, supporting, questions, competitor_notes.
	 * @param array   $args        source (research, niche, gap, manual), language, country, research_id.
	 * @return array saved (ids), skipped.
	 */
	public static function save_items( array $items, array $args ) {
		$place  = Research::place( $args );
		$source = isset( $args['source'] ) && in_array( $args['source'], Vault::SOURCES, true ) ? $args['source'] : 'research';
		$saved  = array();
		$skip   = 0;
		$facts  = Research_View::facts( array_values( array_filter( array_map( 'strval', wp_list_pluck( $items, 'keyword' ) ) ) ), $place );
		foreach ( array_slice( $items, 0, 500 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['keyword'] ) ) {
				++$skip;
				continue;
			}
			$keyword = Schema_Guard::keyword( (string) $item['keyword'] );
			$topic   = ! empty( $item['cluster'] ) ? Topic_Map::ensure( sanitize_text_field( (string) $item['cluster'] ), 0, 'ai' ) : 0;
			$fields  = array(
				'keyword'          => $keyword,
				'language'         => $place['language'],
				'country'          => $place['country'],
				'intent'           => isset( $item['intent'] ) ? $item['intent'] : '',
				'demand'           => isset( $item['demand'] ) ? $item['demand'] : '',
				'difficulty_label' => isset( $item['difficulty'] ) ? $item['difficulty'] : '',
				'source'           => $source,
				'module'           => Module::ID,
				'context'          => array(
					'supporting'       => isset( $item['supporting'] ) ? (array) $item['supporting'] : array(),
					'questions'        => isset( $item['questions'] ) ? (array) $item['questions'] : array(),
					'competitor_notes' => isset( $item['competitor_notes'] ) ? (string) $item['competitor_notes'] : '',
					'reason'           => isset( $item['reason'] ) ? (string) $item['reason'] : ( isset( $item['why'] ) ? (string) $item['why'] : '' ),
				),
			);
			if ( $topic ) {
				$fields['topic_id'] = $topic;
			}
			// Real numbers only: from the provider's cache or Search Console, never from the AI item.
			$key  = Keyword_Map::normalise( $keyword );
			$real = isset( $facts[ $key ]['real'] ) ? $facts[ $key ]['real'] : array();
			$data = function_exists( 'qcld_wpbot_core_keyword_data' ) ? qcld_wpbot_core_keyword_data( array( $keyword ), $place ) : array();
			if ( isset( $data[ $key ] ) && null !== $data[ $key ]['volume'] ) {
				$fields['volume']        = (int) $data[ $key ]['volume'];
				$fields['cpc']           = $data[ $key ]['cpc'];
				$fields['volume_source'] = (string) $data[ $key ]['provider'];
			}
			if ( isset( $real['position'] ) ) {
				$fields['rank']        = $real['position']['value'];
				$fields['rank_source'] = 'search_console';
				$fields['rank_at']     = current_time( 'mysql', true );
			}
			$page = Research_View::page_for( $key );
			if ( $page ) {
				$fields['post_id'] = $page['id'];
				$fields['status']  = 'targeted';
			}
			$id = Vault::add( $fields );
			if ( is_wp_error( $id ) ) {
				++$skip;
				continue;
			}
			if ( isset( $fields['rank'] ) ) {
				Vault::record_rank( $id, $fields['rank'], 'search_console' );
			}
			$saved[] = (int) $id;
		}
		return array(
			'saved'   => $saved,
			'skipped' => $skip,
		);
	}

	/**
	 * Rows for the Vault screen, with page and topic names.
	 *
	 * @param array $args Query args.
	 * @return array items, total, totals.
	 */
	public static function listing( array $args ) {
		$rows   = Vault::query( $args );
		$topics = array();
		foreach ( Topic_Map::all() as $topic ) {
			$topics[ (int) $topic['id'] ] = (string) $topic['name'];
		}
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['topic']        = isset( $topics[ $row['topic_id'] ] ) ? $topics[ $row['topic_id'] ] : '';
			$rows[ $index ]['page']         = $row['post_id'] && get_post( $row['post_id'] ) ? array(
				'id'    => $row['post_id'],
				'title' => get_the_title( $row['post_id'] ),
				'url'   => (string) get_permalink( $row['post_id'] ),
				'edit'  => current_user_can( 'edit_post', $row['post_id'] ) ? (string) get_edit_post_link( $row['post_id'], 'raw' ) : '',
			) : null;
			$rows[ $index ]['volume_label'] = '' !== $row['volume_source'] ? Research_View::provider_label( $row['volume_source'] ) : '';
			$rows[ $index ]['rank_label']   = 'search_console' === $row['rank_source'] ? __( 'Search Console', 'chatbot' ) : ( '' !== $row['rank_source'] ? Research_View::provider_label( $row['rank_source'] ) : '' );
		}
		$count_args = $args;
		unset( $count_args['limit'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		return array(
			'items'  => $rows,
			'total'  => Vault::count( $count_args ),
			'totals' => Vault::totals(),
		);
	}

	/**
	 * Assign a Vault keyword to a page: a manual, locked main (or supporting)
	 * search in the shared keyword map, and the keyword becomes Targeted.
	 *
	 * @param int    $id      Vault id.
	 * @param int    $post_id Page.
	 * @param string $role    main or supporting.
	 * @return true|WP_Error
	 */
	public static function assign( $id, $post_id, $role = 'main' ) {
		$entry   = Vault::get( $id );
		$post_id = absint( $post_id );
		if ( ! $entry ) {
			return new WP_Error( 'qcld_wpbot_seo_vault_missing', __( 'This keyword is no longer in the Vault.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_cannot_edit', __( 'Choose a page you can edit.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( 'supporting' === $role ) {
			$main       = Keyword_Map::main_query( $post_id );
			$supporting = array();
			foreach ( Keyword_Map::entries(
				array(
					'post_id' => $post_id,
					'role'    => 'supporting',
				)
			) as $row ) {
				$supporting[] = $row['query'];
			}
			$supporting[] = $entry['keyword'];
			Keyword_Map::set_post(
				$post_id,
				$main ? $main['query'] : $entry['keyword'],
				array(
					'source'     => 'manual',
					'locked'     => true,
					'module'     => Module::ID,
					'topic_id'   => $main ? (int) $main['topic_id'] : (int) $entry['topic_id'],
					'supporting' => array_slice( array_unique( $supporting ), -5 ),
				)
			);
		} else {
			Keyword_Map::set_post(
				$post_id,
				$entry['keyword'],
				array(
					'source'   => 'manual',
					'locked'   => true,
					'module'   => Module::ID,
					'topic_id' => (int) $entry['topic_id'],
				)
			);
		}
		$done = Vault::update(
			$id,
			array(
				'post_id' => $post_id,
				'status'  => 'ranking' === $entry['status'] ? 'ranking' : 'targeted',
			)
		);
		return is_wp_error( $done ) ? $done : true;
	}

	/**
	 * AI Writer brief for a Vault keyword (pre-filled; no brief generator here).
	 *
	 * @param int $id Vault id.
	 * @return string|WP_Error URL.
	 */
	public static function write_url( $id ) {
		$entry = Vault::get( $id );
		if ( ! $entry ) {
			return new WP_Error( 'qcld_wpbot_seo_vault_missing', __( 'This keyword is no longer in the Vault.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$url = self::brief_url( $entry['keyword'], $entry );
		if ( ! is_wp_error( $url ) && 'researched' === $entry['status'] ) {
			Vault::update( $id, array( 'status' => 'targeted' ) );
		}
		return $url;
	}

	/**
	 * AI Writer brief URL from a keyword and research context.
	 *
	 * @param string $keyword Keyword.
	 * @param array  $info    intent, context (supporting, questions, competitor_notes, reason), topic_id.
	 * @return string|WP_Error
	 */
	public static function brief_url( $keyword, array $info ) {
		if ( ! Writer_Bridge::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$context = isset( $info['context'] ) ? (array) $info['context'] : array();
		$notes   = array();
		if ( ! empty( $info['intent'] ) ) {
			/* translators: %s: search intent. */
			$notes[] = sprintf( __( 'Search intent: %s.', 'chatbot' ), $info['intent'] );
		}
		if ( ! empty( $context['questions'] ) ) {
			$notes[] = __( 'Answer these questions:', 'chatbot' ) . ' ' . implode( ' | ', array_slice( (array) $context['questions'], 0, 10 ) );
		}
		if ( ! empty( $context['competitor_notes'] ) ) {
			$notes[] = __( 'Competitor notes:', 'chatbot' ) . ' ' . $context['competitor_notes'];
		}
		if ( ! empty( $context['reason'] ) ) {
			$notes[] = $context['reason'];
		}
		$topic = '';
		if ( ! empty( $info['topic_id'] ) ) {
			$row   = Topic_Map::get( (int) $info['topic_id'] );
			$topic = $row ? (string) $row['name'] : '';
		}
		// Round 2 (R5): real numbers travel with the brief, each with its source.
		$numbers = array();
		if ( isset( $info['volume'] ) && null !== $info['volume'] && ! empty( $info['volume_source'] ) ) {
			$numbers[ __( 'Monthly searches', 'chatbot' ) ] = number_format_i18n( (int) $info['volume'] );
			$numbers[ __( 'Source', 'chatbot' ) ]           = Research_View::provider_label( (string) $info['volume_source'] );
			if ( ! empty( $info['trend'] ) ) {
				$numbers[ __( 'Trend', 'chatbot' ) ] = (string) $info['trend'];
			}
			if ( ! empty( $info['competition'] ) ) {
				$numbers[ __( 'Competition', 'chatbot' ) ] = (string) $info['competition'];
			}
		}
		return (string) qcld_wpbot_writer_open_brief_url(
			array(
				'mode'               => 'new',
				'topic'              => sanitize_text_field( (string) $keyword ),
				'main_query'         => sanitize_text_field( (string) $keyword ),
				'supporting_queries' => isset( $context['supporting'] ) ? (array) $context['supporting'] : array(),
				'map_topic'          => $topic,
				'notes'              => implode( "\n", $notes ),
				'numbers'            => $numbers,
				'source_module'      => Module::ID,
			)
		);
	}

	/**
	 * Add Vault keywords to AI Writer's ideas (Topic Finder and calendar).
	 *
	 * @param int[] $ids Vault ids.
	 * @return array|WP_Error added, url.
	 */
	public static function to_ideas( array $ids ) {
		if ( ! class_exists( '\QuantumCloud\AiWriter\Topics\Ideas' ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$added = 0;
		foreach ( array_slice( array_map( 'absint', $ids ), 0, 100 ) as $id ) {
			$entry = Vault::get( $id );
			if ( ! $entry ) {
				continue;
			}
			$idea = \QuantumCloud\AiWriter\Topics\Ideas::add(
				$entry['keyword'],
				'seo',
				array(
					'source_ref' => 'vault:' . $entry['id'],
					'reason'     => trim( ( '' !== (string) $entry['context']['reason'] ? $entry['context']['reason'] : __( 'Saved in your Keyword Vault.', 'chatbot' ) ) . ( null !== $entry['volume'] && '' !== $entry['volume_source'] ? ' ' . sprintf( /* translators: 1: monthly searches, 2: data source. */ __( 'About %1$s searches a month (%2$s).', 'chatbot' ), number_format_i18n( (int) $entry['volume'] ), Research_View::provider_label( $entry['volume_source'] ) ) : '' ) ),
					'relevance'  => 'high' === $entry['demand'] ? 80 : ( 'low' === $entry['demand'] ? 40 : 60 ),
					'main_query' => $entry['keyword'],
				)
			);
			if ( ! is_wp_error( $idea ) ) {
				++$added;
				if ( 'researched' === $entry['status'] ) {
					Vault::update( $id, array( 'status' => 'targeted' ) );
				}
			}
		}
		return array(
			'added' => $added,
			'url'   => \QuantumCloud\SeoAiSearch\Module::writer_url(),
		);
	}

	/**
	 * Apply a bulk action.
	 *
	 * @param int[]  $ids    Vault ids.
	 * @param string $action status, topic, archive, delete, assign.
	 * @param mixed  $value  New status, topic name, or post id.
	 * @return array|WP_Error changed.
	 */
	public static function bulk( array $ids, $action, $value ) {
		$ids     = array_slice( array_filter( array_map( 'absint', $ids ) ), 0, 500 );
		$changed = 0;
		$topic   = 0;
		if ( 'topic' === $action ) {
			$name = sanitize_text_field( (string) $value );
			if ( '' === $name ) {
				return new WP_Error( 'qcld_wpbot_seo_vault_topic', __( 'Enter a topic name.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$topic = Topic_Map::ensure( $name, 0, 'manual' );
		}
		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'status':
					$done = Vault::update( $id, array( 'status' => sanitize_key( (string) $value ) ) );
					break;
				case 'archive':
					$done = Vault::update( $id, array( 'status' => 'archived' ) );
					break;
				case 'topic':
					$done = Vault::update( $id, array( 'topic_id' => $topic ) );
					break;
				case 'assign':
					$done = self::assign( $id, absint( $value ), 'supporting' );
					break;
				case 'delete':
					$done = Vault::delete( $id );
					break;
				default:
					return new WP_Error( 'qcld_wpbot_seo_vault_action', __( 'Unknown action.', 'chatbot' ), array( 'status' => 400 ) );
			}
			if ( true === $done ) {
				++$changed;
			} elseif ( is_wp_error( $done ) && 'status' === $action ) {
				return $done;
			}
		}
		return array( 'changed' => $changed );
	}

	/**
	 * Import a CSV: keyword plus optional language, country and notes.
	 * A header row is detected; bad rows are skipped and counted.
	 *
	 * @param string $csv CSV text.
	 * @return array|WP_Error added, updated, skipped, errors (line => reason).
	 */
	public static function import_csv( $csv ) {
		$csv = (string) $csv;
		if ( strlen( $csv ) > self::CSV_BYTES ) {
			return new WP_Error( 'qcld_wpbot_seo_csv_size', __( 'The file is larger than 2 MB. Split it and import the parts.', 'chatbot' ), array( 'status' => 413 ) );
		}
		$csv   = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );
		$lines = preg_split( '/\r\n|\r|\n/', (string) $csv );
		if ( count( $lines ) > self::CSV_ROWS + 1 ) {
			return new WP_Error( 'qcld_wpbot_seo_csv_rows', __( 'The file has more than 5,000 rows. Split it and import the parts.', 'chatbot' ), array( 'status' => 413 ) );
		}
		$columns = array( 'keyword', 'language', 'country', 'notes' );
		$added   = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();
		$place   = Research::place( array() );
		foreach ( $lines as $number => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line, ',', '"', '' );
			if ( 0 === $number && in_array( strtolower( trim( (string) $cells[0] ) ), array( 'keyword', 'keywords', 'keyphrase' ), true ) ) {
				$columns = array();
				foreach ( $cells as $cell ) {
					$columns[] = sanitize_key( strtolower( trim( (string) $cell ) ) );
				}
				continue;
			}
			$row = array();
			foreach ( $columns as $index => $name ) {
				$row[ $name ] = isset( $cells[ $index ] ) ? self::uncell( (string) $cells[ $index ] ) : '';
			}
			$keyword = isset( $row['keyword'] ) ? Schema_Guard::keyword( $row['keyword'] ) : '';
			if ( '' === $keyword ) {
				++$skipped;
				$errors[ $number + 1 ] = __( 'No keyword.', 'chatbot' );
				continue;
			}
			$language = ! empty( $row['language'] ) ? (string) $row['language'] : $place['language'];
			$country  = ! empty( $row['country'] ) ? (string) $row['country'] : $place['country'];
			$exists   = Vault::find( $keyword, $language, $country );
			$id       = Vault::add(
				array(
					'keyword'  => $keyword,
					'language' => $language,
					'country'  => $country,
					'notes'    => isset( $row['notes'] ) ? $row['notes'] : '',
					'source'   => 'csv',
					'module'   => Module::ID,
				)
			);
			if ( is_wp_error( $id ) ) {
				++$skipped;
				$errors[ $number + 1 ] = $id->get_error_message();
			} elseif ( $exists ) {
				++$updated;
			} else {
				++$added;
			}
		}
		return array(
			'added'   => $added,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => array_slice( $errors, 0, 50, true ),
		);
	}

	/**
	 * Export the Vault (or a filtered part) as CSV. Cells that a spreadsheet
	 * would run as a formula (=, +, -, @, tab, return) are prefixed with '.
	 *
	 * @param array $args Query args.
	 * @return string CSV.
	 */
	public static function export_csv( array $args ) {
		$args['limit']  = 500;
		$args['offset'] = 0;
		$head           = array( 'keyword', 'language', 'country', 'status', 'intent', 'topic', 'page', 'demand (AI estimate)', 'difficulty (AI estimate)', 'volume', 'volume source', 'cost per click', 'rank', 'rank source', 'rank date', 'notes', 'source', 'added' );
		$out            = self::csv_line( $head );
		$topics         = array();
		foreach ( Topic_Map::all() as $topic ) {
			$topics[ (int) $topic['id'] ] = (string) $topic['name'];
		}
		do {
			$rows = Vault::query( $args );
			foreach ( $rows as $row ) {
				$out .= self::csv_line(
					array(
						$row['keyword'],
						$row['language'],
						$row['country'],
						$row['status'],
						$row['intent'],
						isset( $topics[ $row['topic_id'] ] ) ? $topics[ $row['topic_id'] ] : '',
						$row['post_id'] ? (string) get_permalink( $row['post_id'] ) : '',
						$row['demand'],
						$row['difficulty_label'],
						null === $row['volume'] ? '' : (string) $row['volume'],
						$row['volume_source'],
						null === $row['cpc'] ? '' : (string) $row['cpc'],
						null === $row['rank'] ? '' : (string) $row['rank'],
						$row['rank_source'],
						$row['rank_at'],
						$row['notes'],
						$row['source'],
						$row['created_at'],
					)
				);
			}
			$args['offset'] += 500;
			$more            = 500 === count( $rows ) && $args['offset'] < 50000;
		} while ( $more );
		return $out;
	}

	/**
	 * One CSV line with formula cells escaped.
	 *
	 * @param string[] $cells Cells.
	 * @return string
	 */
	public static function csv_line( array $cells ) {
		$out = array();
		foreach ( $cells as $cell ) {
			$cell = (string) $cell;
			if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				$cell = "'" . $cell;
			}
			$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}
		return implode( ',', $out ) . "\n";
	}

	/**
	 * Clean an imported cell (plain text; a leading formula sign is dropped).
	 *
	 * @param string $cell Cell.
	 * @return string
	 */
	private static function uncell( $cell ) {
		$cell = trim( sanitize_text_field( $cell ) );
		return ltrim( $cell, "=+@\t" );
	}

	/**
	 * After a Search Console import: real positions for Targeted and
	 * Ranking keywords (and Researched ones the site already appears for).
	 *
	 * @return int Keywords updated.
	 */
	public static function ranks_from_search_console() {
		if ( ! self::available() ) {
			return 0;
		}
		$positions = array();
		foreach ( Data_Store::period_rows( 'query', gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ), 1, 20000 ) as $row ) {
			$positions[ Keyword_Map::normalise( $row['query'] ) ] = (float) $row['position'];
		}
		$updated = 0;
		$offset  = 0;
		do {
			$rows = Vault::query(
				array(
					'status' => array( 'researched', 'targeted', 'ranking' ),
					'limit'  => 500,
					'offset' => $offset,
				)
			);
			foreach ( $rows as $row ) {
				$key = Keyword_Map::normalise( $row['keyword'] );
				if ( isset( $positions[ $key ] ) ) {
					Vault::record_rank( $row['id'], $positions[ $key ], 'search_console' );
					++$updated;
				} elseif ( 'ranking' === $row['status'] && 'search_console' === $row['rank_source'] ) {
					Vault::record_rank( $row['id'], null, 'search_console' );
					++$updated;
				}
			}
			$offset += 500;
			$more    = 500 === count( $rows ) && $offset < 20000;
		} while ( $more );
		return $updated;
	}
}
