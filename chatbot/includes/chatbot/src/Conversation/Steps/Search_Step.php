<?php
/**
 * Site search: posts and pages whose titles (or content, with "advanced query")
 * contain the visitor's words, shown as cards.
 *
 * Fixes from the legacy handler: stop words and words under 3 characters are
 * ignored, and a result must contain at least half of the question's words, so
 * a question no longer matches every post (B2); results are ranked by title
 * matches; no undefined-variable warnings (B9).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Knowledge\Faq;
use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Site search step.
 */
final class Search_Step implements Step {

	/**
	 * Built-in stop words, used with the owner's list (qlcd_wp_chatbot_stop_words).
	 */
	const STOP_WORDS = array( 'a', 'about', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'could', 'do', 'does', 'for', 'from', 'get', 'have', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'please', 'show', 'tell', 'that', 'the', 'there', 'this', 'to', 'was', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'you', 'your' );

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'search';
	}

	/**
	 * On unless "Disable site search" is set.
	 *
	 * @return bool
	 */
	public function enabled() {
		return '' === (string) Options::get( 'disable_sitesearch', '' );
	}

	/**
	 * Search the site.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return Reply|null
	 */
	public function handle( Message $message, Context $context ) {
		$result = $this->search( $message->text, 1, $message->language );
		if ( 'success' !== $result['status'] ) {
			return null;
		}
		return Reply::make(
			'search',
			array(
				'html' => $result['html'],
				'data' => array(
					'results' => $result['results'],
					'total'   => $result['total'],
				),
			)
		);
	}

	/**
	 * Search and build the legacy response ({ status, html }) plus the raw results.
	 *
	 * @param string $keyword  Visitor text.
	 * @param int    $page     Page, from 1.
	 * @param string $language Locale.
	 * @return array { status, html, results, total }
	 */
	public function search( $keyword, $page = 1, $language = '' ) {
		// The ChatBot FAQ first: a question the owner already answered gets that answer.
		$faq = 1 === (int) $page ? self::faq( $keyword, $language ) : null;
		if ( $faq ) {
			return $faq;
		}
		/**
		 * Words searched for (Pro: synonyms added; the number a result must match stays
		 * half of the visitor's own words).
		 *
		 * @param string[] $terms   Words.
		 * @param string   $keyword Visitor text.
		 */
		$base  = self::terms( $keyword );
		$terms = array_values( array_filter( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_chat/search_terms', $base, $keyword ) ), 'strlen' ) );
		/**
		 * Results per page.
		 *
		 * @param int $limit Default: wpbot_search_result_number (5).
		 */
		$limit  = max( 1, min( 50, (int) apply_filters( 'qcld_wpbot_chat/search_limit', (int) Options::get( 'wpbot_search_result_number', 5 ) ) ) );
		$page   = max( 1, (int) $page );
		$found  = $terms ? $this->query( $keyword, $terms, $limit, ( $page - 1 ) * $limit, self::needed( max( 1, count( $base ) ) ) ) : array(
			'rows'  => array(),
			'total' => 0,
		);
		$result = array(
			'status'  => 'fail',
			'html'    => Options::text( 'qlcd_wp_chatbot_no_result', $language, __( 'Sorry, No result found!', 'chatbot' ) ),
			'results' => array(),
			'total'   => $found['total'],
		);
		if ( ! $found['rows'] ) {
			return $result;
		}
		$cards = '';
		foreach ( $found['rows'] as $row ) {
			$item                = $this->item( $row );
			$result['results'][] = $item;
			$cards              .= $this->card( $item );
		}
		$intro = Options::text( 'qlcd_wp_chatbot_we_have_found', $language, __( 'We have found #result results for #keyword', 'chatbot' ) );
		$intro = str_replace( array( '#result', '#keyword' ), array( (string) $found['total'], esc_html( $keyword ) ), wp_kses_post( $intro ) );

		$html = '<div class="wpb-search-result"><p>' . $intro . '</p>' . $cards . '</div>';
		if ( $found['total'] > $page * $limit ) {
			$more  = Options::text( 'qlcd_wp_chatbot_load_more_search', $language, __( 'Load More', 'chatbot' ) );
			$html .= '<button type="button" class="wp-chatbot-loadmore2" data-search-type="default-wp-search" data-keyword="' . esc_attr( $keyword ) . '" data-page="' . esc_attr( (string) ( $page + 1 ) ) . '">' . esc_html( $more ) . ' <span class="wp-chatbot-loadmore-loader"></span></button>';
		}
		$result['status'] = 'success';
		$result['html']   = $html;
		return $result;
	}

	/**
	 * The FAQ answer for a visitor's question, as a search result, or null.
	 *
	 * @param string $keyword  Visitor text.
	 * @param string $language Locale.
	 * @return array|null { status, html, results, total, faq }
	 */
	public static function faq( $keyword, $language = '' ) {
		$entry = Faq::match( (string) $keyword, (string) $language );
		if ( ! $entry ) {
			return null;
		}
		return array(
			'status'  => 'success',
			'html'    => Faq::answer_html( $entry ),
			'results' => array(),
			'total'   => 1,
			'faq'     => $entry['id'],
		);
	}

	/**
	 * Words worth searching for: no stop words, at least 3 characters, at most 8.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function terms( $text ) {
		$text  = strtolower( wp_strip_all_tags( (string) $text ) );
		$words = preg_split( '/[^\p{L}\p{N}\'-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$stop  = self::STOP_WORDS;
		$own   = get_option( 'qlcd_wp_chatbot_stop_words' );
		if ( is_string( $own ) && '' !== $own ) {
			$stop = array_merge( $stop, array_map( 'trim', explode( ',', strtolower( $own ) ) ) );
		}
		$terms = array();
		foreach ( (array) $words as $word ) {
			$word = trim( $word, "'-" );
			$size = function_exists( 'mb_strlen' ) ? mb_strlen( $word ) : strlen( $word );
			if ( $size >= 3 && ! in_array( $word, $stop, true ) ) {
				$terms[ $word ] = $word;
			}
		}
		return array_slice( array_values( $terms ), 0, 8 );
	}

	/**
	 * Post types searched (wppt_post_types; products go through the WooCommerce bridge).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_option( 'wppt_post_types' );
		$types = is_array( $types ) && $types ? array_map( 'sanitize_key', $types ) : array( 'post', 'page' );
		$types = array_values( array_diff( $types, array( 'product', '' ) ) );
		/**
		 * Post types site search covers.
		 *
		 * @param string[] $types Post types.
		 */
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_chat/search_post_types', $types ) ) ) );
		return $types ? $types : array( 'post', 'page' );
	}

	/**
	 * Run the query: rows ranked by the number of words found in the title.
	 *
	 * @param string   $keyword Whole text.
	 * @param string[] $terms   Words.
	 * @param int      $limit   Page size.
	 * @param int      $offset  Offset.
	 * @param int      $needed  Words a result must contain (synonyms count as words).
	 * @return array { rows: object[], total: int }
	 */
	private function query( $keyword, array $terms, $limit, $offset, $needed ) {
		global $wpdb;
		$types   = self::post_types();
		$content = '1' === (string) Options::get( 'active_advance_query', '' );
		$match   = array();
		$found   = array();
		$score   = array();
		foreach ( $terms as $term ) {
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$one     = $content ? $wpdb->prepare( '( post_title LIKE %s OR post_content LIKE %s )', $like, $like ) : $wpdb->prepare( '( post_title LIKE %s )', $like );
			$match[] = $one;
			$found[] = $one;
			$score[] = $wpdb->prepare( '( post_title LIKE %s ) * 2', $like );
		}
		$phrase  = '%' . $wpdb->esc_like( Str_Step::normalize( $keyword ) ) . '%';
		$score[] = $wpdb->prepare( '( post_title LIKE %s ) * 5', $phrase );
		$in      = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$where   = $wpdb->prepare( "post_status = 'publish' AND post_type IN ({$in})", $types ) . ' AND ( ' . implode( ' OR ', $match ) . ' )'; // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$matched = implode( ' + ', $found );
		$rank    = implode( ' + ', $score );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fragments above are prepared.
		$inner = "SELECT ID, post_title, post_type, post_date, ( {$matched} ) AS matched, ( {$rank} ) AS hits FROM {$wpdb->posts} WHERE {$where}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ( {$inner} ) AS found WHERE matched >= %d", $needed ) );
		$rows  = $total ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ( {$inner} ) AS found WHERE matched >= %d ORDER BY hits DESC, matched DESC, post_date DESC LIMIT %d OFFSET %d", $needed, $limit, $offset ) ) : array();
		// phpcs:enable
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Words a result must contain: half of the question's words, rounded up (B2).
	 *
	 * @param int $count Words in the question.
	 * @return int
	 */
	public static function needed( $count ) {
		return max( 1, (int) ceil( $count / 2 ) );
	}

	/**
	 * Result as data.
	 *
	 * @param object $row Row.
	 * @return array
	 */
	private function item( $row ) {
		$size = (string) Options::get( 'wpbot_search_image_size', 'thumbnail' );
		$id   = (int) $row->ID;
		return array(
			'id'    => $id,
			'title' => html_entity_decode( (string) $row->post_title, ENT_QUOTES, 'UTF-8' ),
			'url'   => (string) get_permalink( $id ),
			'image' => (string) get_the_post_thumbnail_url( $id, $size ),
			'type'  => (string) $row->post_type,
		);
	}

	/**
	 * Legacy card markup (wpbot_card_wraper), escaped.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	private function card( array $item ) {
		$new_window = '1' === (string) get_option( 'wpbot_search_result_new_window' );
		$no_image   = '' === $item['image'];
		$html       = '<div class="wpbot_card_wraper"><div class="wpbot_card_image' . ( $no_image ? ' wpbot_card_image_saas' : '' ) . '">';
		$html      .= '<a href="' . esc_url( $item['url'] ) . '"' . ( $new_window ? ' target="_blank" rel="noopener"' : '' ) . '>';
		if ( ! $no_image ) {
			$html .= '<img src="' . esc_url( $item['image'] ) . '" alt="" />';
		}
		$html .= '<div class="wpbot_card_caption' . ( $no_image ? ' wpbot_card_caption_saas' : '' ) . '"><p class="wpbot_card_caption_title">' . esc_html( $item['title'] ) . '</p></div>';
		return $html . '</a></div></div>';
	}
}
