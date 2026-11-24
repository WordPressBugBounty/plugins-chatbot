<?php
/**
 * Site search: posts, pages (and WooCommerce products) whose titles, excerpts or content
 * contain the visitor's words, shown as cards. Title matches rank first.
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
			// The fallback records the question as a Site Search gap (one word is enough).
			$context->notes['search_missed'] = true;
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

		// Later pages ("Load More") carry only the next results.
		$html = '<div class="wpb-search-result">' . ( 1 === $page ? '<p>' . $intro . '</p>' : '' ) . $cards . '</div>';
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
		$prev  = '';
		foreach ( (array) $words as $word ) {
			$word = trim( $word, "'-" );
			// "How long / much / many / often / soon": the second word asks, it is not the subject.
			$asks = 'how' === $prev && in_array( $word, array( 'long', 'much', 'many', 'often', 'soon' ), true );
			$prev = $word;
			if ( $asks ) {
				continue;
			}
			$size = function_exists( 'mb_strlen' ) ? mb_strlen( $word ) : strlen( $word );
			if ( $size >= 3 && ! in_array( $word, $stop, true ) ) {
				$terms[ $word ] = $word;
			}
		}
		return array_slice( array_values( $terms ), 0, 8 );
	}

	/**
	 * Post types searched (wppt_post_types), plus WooCommerce products when WooCommerce is active
	 * (QA 9.1: "duvet" found nothing because products were always left out).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_option( 'wppt_post_types' );
		$types = is_array( $types ) && $types ? array_map( 'sanitize_key', $types ) : array( 'post', 'page' );
		$types = array_values( array_diff( $types, array( 'product', '' ) ) );
		if ( Options::woocommerce_active() ) {
			$types[] = 'product';
		}
		/**
		 * Post types site search covers.
		 *
		 * @param string[] $types Post types.
		 */
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_chat/search_post_types', $types ) ) ) );
		return $types ? $types : array( 'post', 'page' );
	}

	/**
	 * Whether excerpts and content are searched as well as titles. On unless the owner saved
	 * "Search the content as well as the title" off (active_advance_query '0'); WPBot Free has no
	 * such setting, so it searched titles only (QA 9.1).
	 *
	 * @return bool
	 */
	public static function searches_content() {
		/**
		 * Whether site search matches excerpts and content as well as titles.
		 *
		 * @param bool $content Default: on unless active_advance_query is '0'.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/search_content', '0' !== (string) get_option( 'active_advance_query', '' ) );
	}

	/**
	 * Most rows read for one search. The database finds rows that contain the words anywhere
	 * (titles first); PHP then checks them against the visible text, so this bounds the work.
	 */
	const CANDIDATES = 300;

	/**
	 * Run the search: rows ranked by the words found in the title, then by the words found anywhere
	 * (title, excerpt, content).
	 *
	 * Owner, 24 November 2026: "check if site search is returning relevant results". The database
	 * match alone (LIKE '%word%') found words inside other words ("art" in "Partners") and in the
	 * page builders' markup ("group", "image", "button", "large" are in every block post's HTML), so
	 * it is only the first pass now: each row is then checked against its visible text (tags, block
	 * comments and shortcodes removed), and a word must start a word there (plurals still match).
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
		$content = self::searches_content();
		$match   = array();
		$score   = array();
		foreach ( array_unique( array_map( array( __CLASS__, 'stem' ), $terms ) ) as $term ) {
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$match[] = $content ? $wpdb->prepare( '( post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s )', $like, $like, $like ) : $wpdb->prepare( '( post_title LIKE %s )', $like );
			$score[] = $wpdb->prepare( '( post_title LIKE %s ) * 2', $like );
		}
		$phrase  = '%' . $wpdb->esc_like( Str_Step::normalize( $keyword ) ) . '%';
		$score[] = $wpdb->prepare( '( post_title LIKE %s ) * 5', $phrase );
		$in      = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$where   = $wpdb->prepare( "post_status = 'publish' AND post_type IN ({$in})", $types ) . ' AND ( ' . implode( ' OR ', $match ) . ' )'; // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rank    = implode( ' + ', $score );
		$fields  = $content ? 'ID, post_title, post_excerpt, post_content, post_type, post_date' : 'ID, post_title, post_type, post_date';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fragments above are prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$fields}, ( {$rank} ) AS hits FROM {$wpdb->posts} WHERE {$where} ORDER BY hits DESC, post_date DESC LIMIT %d", self::CANDIDATES ) );
		// phpcs:enable
		$kept = self::rank( is_array( $rows ) ? $rows : array(), $keyword, $terms, $needed, $content );
		return array(
			'rows'  => array_slice( $kept, $offset, $limit ),
			'total' => count( $kept ),
		);
	}

	/**
	 * Keep the rows whose visible text has enough of the words, best first.
	 *
	 * @param object[] $rows    Rows (ID, post_title, post_excerpt, post_content, post_date).
	 * @param string   $keyword Whole text.
	 * @param string[] $terms   Words (with synonyms).
	 * @param int      $needed  Words a result must contain.
	 * @param bool     $content Whether excerpts and content count.
	 * @return object[]
	 */
	public static function rank( array $rows, $keyword, array $terms, $needed, $content = true ) {
		$phrase = self::lower( Str_Step::normalize( $keyword ) );
		$kept   = array();
		foreach ( $rows as $i => $row ) {
			$title   = self::lower( html_entity_decode( (string) ( $row->post_title ?? '' ), ENT_QUOTES, 'UTF-8' ) );
			$body    = $content ? self::visible( ( $row->post_excerpt ?? '' ) . ' ' . ( $row->post_content ?? '' ) ) : '';
			$matched = 0;
			$score   = 0;
			foreach ( $terms as $term ) {
				$term     = self::stem( self::lower( $term ) );
				$in_title = self::has_word( $title, $term );
				if ( $in_title || ( '' !== $body && self::has_word( $body, $term ) ) ) {
					++$matched;
					$score += $in_title ? 3 : 1;
				}
			}
			if ( $matched < $needed ) {
				continue;
			}
			if ( '' !== $phrase && false !== strpos( $title, $phrase ) ) {
				$score += 5;
			}
			$row->qcw_score   = $score;
			$row->qcw_matched = $matched;
			$row->qcw_order   = $i;
			$kept[]           = $row;
		}//end foreach
		usort(
			$kept,
			static function ( $a, $b ) {
				if ( $a->qcw_score !== $b->qcw_score ) {
					return $b->qcw_score - $a->qcw_score;
				}
				if ( $a->qcw_matched !== $b->qcw_matched ) {
					return $b->qcw_matched - $a->qcw_matched;
				}
				return $a->qcw_order - $b->qcw_order;
				// The database's order: newest first.
			}
		);
		return $kept;
	}

	/**
	 * The text a visitor reads: no block comments, shortcodes, tags, scripts or styles.
	 *
	 * @param string $html Post content.
	 * @return string Lowercase text.
	 */
	public static function visible( $html ) {
		$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $html );
		$text = preg_replace( '#<!--.*?-->#s', ' ', (string) $text );
		$text = preg_replace( '#\[/?[a-zA-Z][^\]]*\]#', ' ', (string) $text );
		$text = wp_strip_all_tags( str_replace( '<', ' <', (string) $text ) );
		return self::lower( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Whether a word starts a word in the text ("candle" finds "candles", "art" does not find
	 * "partners"). Scripts written without spaces (Chinese, Japanese, Thai) match anywhere.
	 *
	 * @param string $text Lowercase text.
	 * @param string $term Lowercase word.
	 * @return bool
	 */
	public static function has_word( $text, $term ) {
		if ( '' === $term || false === strpos( $text, $term ) ) {
			return false;
		}
		if ( preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]/u', $term ) ) {
			return true;
		}
		return 1 === preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '/u', $text );
	}

	/**
	 * A plural's stem, so "candles" finds "Candle" and "policies" finds "policy". The stem is always
	 * the start of the word, so it can only find more, never lose a match. Words under 5 letters stay.
	 *
	 * @param string $term Lowercase word.
	 * @return string
	 */
	public static function stem( $term ) {
		$term = (string) $term;
		$size = function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) : strlen( $term );
		if ( $size < 5 || ! preg_match( '/^[a-z\'-]+$/', $term ) ) {
			return $term;
		}
		if ( preg_match( '/ies$/', $term ) ) {
			return substr( $term, 0, -3 );
		}
		if ( preg_match( '/(sses|xes|zes|ches|shes)$/', $term ) ) {
			return substr( $term, 0, -2 );
		}
		if ( preg_match( '/[^su]s$/', $term ) ) {
			return substr( $term, 0, -1 );
		}
		return $term;
	}

	/**
	 * Lowercase, multibyte-safe.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
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
