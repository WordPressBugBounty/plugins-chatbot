<?php
/**
 * Simple Text Responses: the answers the owner wrote in the Response list.
 *
 * Matching order (Free's and Pro's together, with their SQL bugs fixed):
 * chosen answer id, intent, exact question, category, keyword list,
 * full-text score above the "weight" setting, question containing the words.
 * Works on Free's table (no lang/hidden/users_answer columns) and Pro's.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Settings\Db;
use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Text Responses step.
 */
final class Str_Step implements Step {

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'str';
	}

	/**
	 * On unless the owner turned Simple Text Responses off.
	 *
	 * @return bool
	 */
	public function enabled() {
		return Options::on( 'qc_bot_enable_str' );
	}

	/**
	 * Answer from the Response list.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return Reply|null
	 */
	public function handle( Message $message, Context $context ) {
		$result = $this->match( $message->text, $message->language, $message->extra( 'strid' ), $message->extra( 'intent' ) );
		if ( 'success' !== $result['status'] ) {
			return null;
		}
		$first = $result['data'][0];
		return Reply::make(
			'str',
			array(
				'html' => $result['multiple'] ? '' : (string) $first['response'],
				'data' => $result,
			)
		);
	}

	/**
	 * Find answers. Returns the legacy JSON shape of wpbo_search_response.
	 *
	 * @param string $keyword  Visitor text.
	 * @param string $language Locale ('' = any).
	 * @param string $strid    Chosen answer id.
	 * @param string $intent   Intent name.
	 * @return array { status: success|fail, multiple: bool, category?: bool, data: array[] }
	 */
	public function match( $keyword, $language = '', $strid = '', $intent = '' ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'wpbot_response';
		$limit   = max( 1, (int) Options::get( 'qc_bot_str_max_limit', 3 ) );
		$keyword = self::normalize( $keyword );
		$where   = $this->filters( $table, $language );
		$fail    = array(
			'status'   => 'fail',
			'multiple' => false,
			'data'     => array(),
		);

		if ( '' !== $strid && ctype_digit( $strid ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $strid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $rows ) {
				return $this->result( $table, $rows, false );
			}
		}
		if ( '' !== $intent && Db::has( $table, 'intent' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE intent = %s LIMIT 1', $table, $intent ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $rows ) {
				return $this->result( $table, $rows, false );
			}
		}
		/**
		 * Answer before the built-in matching (Pro: Simple Text Response categories).
		 *
		 * @param array|null $result   Legacy result shape, or null to continue.
		 * @param string     $keyword  Visitor text (normalized).
		 * @param string     $language Locale.
		 */
		$early = apply_filters( 'qcld_wpbot_chat/str_pre_match', null, $keyword, $language );
		if ( is_array( $early ) && isset( $early['status'] ) ) {
			return $early;
		}
		/**
		 * Visitor text used for matching (Pro: stop words removed).
		 *
		 * @param string $keyword  Visitor text.
		 * @param string $language Locale.
		 */
		$keyword = trim( (string) apply_filters( 'qcld_wpbot_chat/str_keyword', $keyword, $language ) );
		if ( '' === $keyword ) {
			return $fail;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from prepared fragments.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `query` = %s {$where} LIMIT %d", $table, $keyword, $limit ) );
		if ( $rows ) {
			return $this->result( $table, $rows, false );
		}
		if ( Db::has( $table, 'category' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `category` = %s {$where} LIMIT 50", $table, $keyword ) );
			if ( $rows ) {
				return $this->result( $table, $rows, true );
			}
		}
		$list = ',' . strtolower( $keyword ) . ',';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE LOWER(CONCAT(',', REPLACE(REPLACE(`keyword`, ', ', ','), ' ,', ','), ',')) LIKE %s {$where} LIMIT %d", $table, '%' . $wpdb->esc_like( $list ) . '%', $limit ) );
		if ( $rows ) {
			return $this->result( $table, $rows, false );
		}
		if ( 'off' !== (string) Options::get( 'qc_bot_str_turnoff_fulltext', '' ) ) {
			// The FULLTEXT index covers exactly query, keyword and response (qc_bot_str_fields subsets have no index of their own).
			$fields = '`query`,`keyword`,`response`';
			$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT *, MATCH({$fields}) AGAINST(%s IN NATURAL LANGUAGE MODE) AS score FROM %i WHERE MATCH({$fields}) AGAINST(%s IN NATURAL LANGUAGE MODE) {$where} ORDER BY score DESC LIMIT 15", $keyword, $table, $keyword ) );
			$rows   = self::above_weight( is_array( $rows ) ? $rows : array(), (float) Options::get( 'qc_bot_str_weight', '0.4' ) );
			$rows   = self::covering( $rows, $keyword );
			if ( $rows ) {
				return $this->result( $table, array_slice( $rows, 0, $limit ), false );
			}
		}
		if ( self::length( $keyword ) >= 3 ) {
			$words = preg_split( '/[\s?]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY );
			$like  = '%' . implode( '%', array_map( array( $wpdb, 'esc_like' ), (array) $words ) ) . '%';
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE `query` LIKE %s {$where} LIMIT %d", $table, $like, $limit ) );
			if ( $rows ) {
				return $this->result( $table, $rows, false );
			}
		}
		// phpcs:enable
		return $fail;
	}

	/**
	 * Visitor text as the legacy code compared it: no trailing "?", trimmed, unslashed quotes.
	 *
	 * @param string $keyword Text.
	 * @return string
	 */
	public static function normalize( $keyword ) {
		$keyword = trim( str_replace( "\\'", "'", (string) $keyword ) );
		$keyword = preg_replace( '/\s*\?+$/', '', $keyword );
		return trim( (string) $keyword );
	}

	/**
	 * Keep rows whose score, relative to the best, reaches the weight.
	 *
	 * @param object[] $rows   Rows with a score.
	 * @param float    $weight 0..1.
	 * @return object[]
	 */
	public static function above_weight( array $rows, $weight ) {
		if ( ! $rows ) {
			return array();
		}
		$max = max( array_map( 'floatval', array_column( array_map( 'get_object_vars', $rows ), 'score' ) ) );
		$max = $max > 0 ? $max : 1.0;
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $max, $weight ) {
					return ( (float) $row->score / $max ) >= $weight;
				}
			)
		);
	}

	/**
	 * Keep full-text rows that contain at least half of the question's words
	 * (the weight is relative to the best row, so the best row always passed).
	 *
	 * @param object[] $rows    Rows.
	 * @param string   $keyword Question.
	 * @return object[]
	 */
	public static function covering( array $rows, $keyword ) {
		$terms = Search_Step::terms( $keyword );
		if ( ! $terms ) {
			return $rows;
		}
		$needed = Search_Step::needed( count( $terms ) );
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $terms, $needed ) {
					$text  = strtolower( wp_strip_all_tags( ( isset( $row->query ) ? $row->query : '' ) . ' ' . ( isset( $row->keyword ) ? $row->keyword : '' ) . ' ' . ( isset( $row->response ) ? $row->response : '' ) ) );
					$found = 0;
					foreach ( $terms as $term ) {
						if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '(?![\p{L}\p{N}])/u', $text ) ) {
							++$found;
						}
					}
					return $found >= $needed;
				}
			)
		);
	}

	/**
	 * SQL for language and hidden answers, on the columns the table has.
	 *
	 * @param string $table    Table.
	 * @param string $language Locale.
	 * @return string Starts with " AND" or is empty.
	 */
	private function filters( $table, $language ) {
		global $wpdb;
		$sql = '';
		if ( '' !== $language && Db::has( $table, 'lang' ) ) {
			$sql .= $wpdb->prepare( " AND ( `lang` = %s OR `lang` IS NULL OR `lang` = '' )", $language );
		}
		if ( Db::has( $table, 'hidden' ) ) {
			$sql .= ' AND `hidden` = 0';
		}
		return $sql;
	}

	/**
	 * Legacy JSON shape.
	 *
	 * @param string   $table    Table.
	 * @param object[] $rows     Rows.
	 * @param bool     $category Matched a category.
	 * @return array
	 */
	private function result( $table, array $rows, $category ) {
		$pro       = Db::has( $table, 'users_answer' );
		$shortcode = Options::on( 'qc_bot_str_allow_shortcode' );
		$data      = array();
		foreach ( $rows as $row ) {
			$item = array(
				'id'       => (string) $row->id,
				'query'    => (string) $row->query,
				'response' => self::render( (string) $row->response, $shortcode ),
				'score'    => isset( $row->score ) ? (float) $row->score : 1,
			);
			if ( $pro ) {
				$item['followup']       = self::render( isset( $row->custom ) ? (string) $row->custom : '', $shortcode );
				$item['trigger_intent'] = isset( $row->trigger_intent ) ? (string) $row->trigger_intent : '';
				$item['users_answer']   = self::users_answer( isset( $row->users_answer ) ? $row->users_answer : '', $shortcode );
			}
			$data[] = $item;
		}
		$result = array(
			'status'   => 'success',
			'multiple' => count( $data ) > 1,
			'data'     => $data,
		);
		if ( $category ) {
			$result['category'] = true;
		}
		return $result;
	}

	/**
	 * The follow-up question settings of a Pro answer, with defaults.
	 *
	 * @param string $raw       Stored value.
	 * @param bool   $shortcode Run shortcodes.
	 * @return array
	 */
	public static function users_answer( $raw, $shortcode = false ) {
		$value              = '' !== (string) $raw ? maybe_unserialize( $raw ) : array();
		$defaults           = array(
			'answer'             => array(),
			'feedback'           => array(),
			'not_match'          => '',
			'trigger_intent'     => '',
			'entity_name'        => '',
			'entity_is_required' => 0,
			'response_type'      => 2,
			'prompt_message'     => '',
		);
		$value              = array_merge( $defaults, is_array( $value ) ? $value : array() );
		$value['not_match'] = self::render( (string) $value['not_match'], $shortcode );
		return $value;
	}

	/**
	 * Answer HTML, through the_content when shortcodes are allowed.
	 *
	 * @param string $html      Answer.
	 * @param bool   $shortcode Run shortcodes.
	 * @return string
	 */
	private static function render( $html, $shortcode ) {
		return $shortcode && '' !== $html ? (string) apply_filters( 'the_content', $html ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			: $html;
	}

	/**
	 * Characters in a string.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
