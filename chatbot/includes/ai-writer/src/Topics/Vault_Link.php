<?php
/**
 * AI Writer's side of the Keyword Vault (CR-4).
 *
 * The Vault lives in WPBot Core and is filled by SEO & AI Search: keywords
 * with a status (researched, targeted, ranking, archived), an intent, a
 * topic, the page that targets them and notes for a brief. AI Writer
 * - lists targeted keywords without a page as Topic Finder ideas,
 * - pre-fills briefs from a Vault keyword (intent, supporting keywords,
 *   questions, competitor notes, numbers),
 * - warns in the duplicate check when another page owns the keyword,
 * - assigns the finished post to the keyword (status Targeted).
 * Everything goes through WPBot Core's Vault functions, never its table.
 * Without them (no SEO & AI Search, older WPBot Core) nothing runs.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword Vault link.
 */
final class Vault_Link {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'on_finalized' ), 20, 2 );
	}

	/**
	 * Listener: a finished piece.
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function on_finalized( $post_id, $brief ) {
		if ( $post_id ) {
			self::finished( (int) $post_id, (array) $brief );
		}
	}

	/**
	 * Whether the Vault API is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_core_vault_query' ) && function_exists( 'qcld_wpbot_core_vault_get' );
	}

	/**
	 * Vault id from an idea's source reference ("vault:12"), else 0.
	 *
	 * @param string $ref Source reference.
	 * @return int
	 */
	public static function id_from_ref( $ref ) {
		return preg_match( '/^vault:(\d+)$/', (string) $ref, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * One Vault row, or null.
	 *
	 * @param int $id Vault id.
	 * @return array|null
	 */
	public static function get( $id ) {
		if ( ! self::available() || $id <= 0 ) {
			return null;
		}
		$row = qcld_wpbot_core_vault_get( (int) $id );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * The Vault row for a keyword (exact match), or null.
	 *
	 * @param string $keyword Keyword.
	 * @return array|null
	 */
	public static function for_keyword( $keyword ) {
		$norm = Keyword_Numbers::normalise( $keyword );
		if ( ! self::available() || '' === $norm ) {
			return null;
		}
		$rows = qcld_wpbot_core_vault_query(
			array(
				'search' => $norm,
				'limit'  => 20,
			)
		);
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_array( $row ) && Keyword_Numbers::normalise( (string) $row['keyword'] ) === $norm && 'archived' !== $row['status'] ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Targeted keywords with no page yet, best demand first.
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public static function targeted_without_page( $limit = 40 ) {
		if ( ! self::available() ) {
			return array();
		}
		$rows = qcld_wpbot_core_vault_query(
			array(
				'status'   => 'targeted',
				'has_page' => false,
				'orderby'  => 'volume',
				'order'    => 'DESC',
				'limit'    => max( 1, min( 100, (int) $limit ) ),
			)
		);
		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();
	}

	/**
	 * Search numbers of a Vault row in the ideas' demand shape. Real numbers
	 * keep their source; the Vault's own demand is labelled "AI estimate".
	 *
	 * @param array $row Vault row.
	 * @return array Extras for Ideas::add() (volume, volume_source, trend, competition, estimate).
	 */
	public static function demand_extras( array $row ) {
		$extras = array();
		if ( null !== $row['volume'] && '' !== (string) $row['volume_source'] ) {
			$extras['volume']        = (int) $row['volume'];
			$extras['volume_source'] = (string) $row['volume_source'];
		}
		if ( ! empty( $row['trend'] ) ) {
			$extras['trend'] = (string) $row['trend'];
		}
		if ( ! empty( $row['competition'] ) ) {
			$extras['competition'] = (string) $row['competition'];
		}
		if ( ! isset( $extras['volume'] ) && in_array( $row['demand'], array( 'high', 'medium', 'low' ), true ) ) {
			$extras['estimate'] = (string) $row['demand'];
		}
		return $extras;
	}

	/**
	 * Brief fields from a Vault row (for qcld_wpbot_writer_open_brief_url()).
	 *
	 * @param array $row Vault row.
	 * @return array main_query, intent, supporting_queries, questions, notes, numbers, vault_id.
	 */
	public static function brief_fields( array $row ) {
		$context = isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array();
		$notes   = array();
		if ( ! empty( $context['competitor_notes'] ) ) {
			$notes[] = __( 'Competitor notes:', 'chatbot' ) . ' ' . ( is_array( $context['competitor_notes'] ) ? implode( '; ', array_map( 'strval', $context['competitor_notes'] ) ) : (string) $context['competitor_notes'] );
		}
		if ( '' !== (string) $row['notes'] ) {
			$notes[] = (string) $row['notes'];
		}
		$numbers = array();
		if ( null !== $row['volume'] && '' !== (string) $row['volume_source'] ) {
			$numbers[ __( 'Monthly searches', 'chatbot' ) ] = number_format_i18n( (int) $row['volume'] );
			$numbers[ __( 'Source', 'chatbot' ) ]           = Keyword_Numbers::source_label( (string) $row['volume_source'] );
		} elseif ( in_array( $row['demand'], array( 'high', 'medium', 'low' ), true ) ) {
			$numbers[ __( 'Demand', 'chatbot' ) ] = sprintf(
				/* translators: %s: high, medium or low. */
				__( '%s (AI estimate)', 'chatbot' ),
				self::level_label( (string) $row['demand'] )
			);
		}
		if ( ! empty( $row['trend'] ) ) {
			$numbers[ __( 'Trend', 'chatbot' ) ] = self::trend_label( (string) $row['trend'] );
		}
		if ( ! empty( $row['competition'] ) ) {
			$numbers[ __( 'Competition', 'chatbot' ) ] = self::level_label( (string) $row['competition'] );
		}
		return array(
			'main_query'         => (string) $row['keyword'],
			'intent'             => (string) $row['intent'],
			'supporting_queries' => isset( $context['supporting'] ) ? array_values( (array) $context['supporting'] ) : array(),
			'questions'          => isset( $context['questions'] ) ? array_values( array_map( 'strval', (array) $context['questions'] ) ) : array(),
			'notes'              => implode( "\n", $notes ),
			'numbers'            => $numbers,
			'vault_id'           => (int) $row['id'],
		);
	}

	/**
	 * Another page that owns this keyword in the Vault, or null.
	 *
	 * @param string $keyword Keyword.
	 * @param int    $exclude Post being written (0 for a new one).
	 * @return array|null post_id, title, url.
	 */
	public static function owner( $keyword, $exclude = 0 ) {
		$row = self::for_keyword( $keyword );
		if ( ! $row || empty( $row['post_id'] ) || (int) $row['post_id'] === (int) $exclude ) {
			return null;
		}
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post || 'trash' === $post->post_status ) {
			return null;
		}
		return array(
			'post_id' => (int) $post->ID,
			'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'url'     => (string) get_permalink( $post ),
		);
	}

	/**
	 * A finished piece written for a Vault keyword: assign the page and mark
	 * the keyword Targeted (a Ranking keyword keeps its status).
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief (vault_id, main_query).
	 * @return void
	 */
	public static function finished( $post_id, array $brief ) {
		if ( ! self::available() || ! function_exists( 'qcld_wpbot_core_vault_update' ) ) {
			return;
		}
		$row = ! empty( $brief['vault_id'] ) ? self::get( (int) $brief['vault_id'] ) : null;
		if ( ! $row && ! empty( $brief['main_query'] ) ) {
			$row = self::for_keyword( (string) $brief['main_query'] );
		}
		if ( ! $row || ( ! empty( $row['post_id'] ) && (int) $row['post_id'] !== (int) $post_id ) ) {
			return;
		}
		$fields = array( 'post_id' => (int) $post_id );
		if ( in_array( $row['status'], array( 'researched', 'targeted' ), true ) ) {
			$fields['status'] = 'targeted';
		}
		qcld_wpbot_core_vault_update( (int) $row['id'], $fields );
	}

	/**
	 * Readable level.
	 *
	 * @param string $level low|medium|high.
	 * @return string
	 */
	public static function level_label( $level ) {
		$labels = array(
			'low'    => __( 'Low', 'chatbot' ),
			'medium' => __( 'Medium', 'chatbot' ),
			'high'   => __( 'High', 'chatbot' ),
		);
		return isset( $labels[ $level ] ) ? $labels[ $level ] : (string) $level;
	}

	/**
	 * Readable trend.
	 *
	 * @param string $trend rising|steady|falling.
	 * @return string
	 */
	public static function trend_label( $trend ) {
		$labels = array(
			'rising'  => __( 'Rising', 'chatbot' ),
			'steady'  => __( 'Steady', 'chatbot' ),
			'falling' => __( 'Falling', 'chatbot' ),
		);
		return isset( $labels[ $trend ] ) ? $labels[ $trend ] : (string) $trend;
	}
}
