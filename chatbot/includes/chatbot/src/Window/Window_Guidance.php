<?php
/**
 * Guidance in the chat window (owner, 20 November 2026): "Popular on this page", "Try asking",
 * suggestions while typing, finish-my-question and the nudge (the last two in WPBot Pro).
 *
 * Conservative by design (owner Q2): a suggestion is shown only when it is worthwhile and relevant
 * to this page. The candidates come from what the site already knows, with no AI call:
 *  - the Context Aware page rules that match this page (strong: the owner chose them);
 *  - start menu items related to the page title (Menu_Matcher, with its relevance threshold);
 *  - items visitors clicked on this page (menu_click events, once there are enough of them).
 * Nothing passes, nothing is shown.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

use QuantumCloud\Chatbot\Knowledge\Faq;
use QuantumCloud\Chatbot\Menu\Menu_Items;
use QuantumCloud\Chatbot\Menu\Menu_Matcher;
use QuantumCloud\Chatbot\Menu\Menu_Rules;
use QuantumCloud\Chatbot\RateLimit\Limiter;
use QuantumCloud\Chatbot\Rest\Routes;
use QuantumCloud\Chatbot\Settings\Options;
use QuantumCloud\Chatbot\Visitor\Events;
use QuantumCloud\Chatbot\Visitor\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Window guidance.
 */
final class Window_Guidance {

	/**
	 * Most suggestions shown at once.
	 */
	const MAX = 3;

	/**
	 * Score a candidate needs. A matching page rule gives 10; a related item gives its
	 * word-overlap score (2 per title word); popularity gives up to 6.
	 */
	const STRICT = 2.0;

	/**
	 * Clicks on this page an item needs before popularity counts, and how far back to look.
	 */
	const POPULAR_MIN  = 3;
	const POPULAR_DAYS = 60;

	/**
	 * Most phrases sent for suggestions while typing.
	 */
	const PHRASES = 60;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * The click route (a visitor tapped a start menu button; used to rank "Popular on this page").
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			Routes::NS,
			'/window/click',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'click' ),
				'permission_callback' => array( __CLASS__, 'click_allowed' ),
				'args'                => array(
					'label' => array(
						'type'     => 'string',
						'required' => true,
					),
					'page'  => array( 'type' => 'integer' ),
					'place' => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * Rate limit for the click route (anyone may call it; it only counts real menu items).
	 *
	 * @return true|\WP_Error
	 */
	public static function click_allowed() {
		if ( Limiter::hit( 'window_click' ) ) {
			return true;
		}
		return new \WP_Error( 'qcld_wpbot_chat_rate_limited', __( 'Too many requests. Please wait a little and try again.', 'chatbot' ), array( 'status' => 429 ) );
	}

	/**
	 * Record a click on a start menu item. Unknown labels are ignored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|array
	 */
	public static function click( $request ) {
		$id = self::item_for_label( (string) $request->get_param( 'label' ) );
		if ( '' === $id ) {
			return array( 'recorded' => false );
		}
		$place = sanitize_key( (string) $request->get_param( 'place' ) );
		Events::emit(
			'menu_click',
			array(
				'object_type' => 'page',
				'object_id'   => max( 0, (int) $request->get_param( 'page' ) ),
				'data'        => array(
					'item'  => $id,
					'place' => in_array( $place, array( 'chat', 'bar', 'page', 'try', 'typing', 'nudge' ), true ) ? $place : 'chat',
				),
			)
		);
		return array( 'recorded' => true );
	}

	/**
	 * The id of the available item with this label (site language), or ''.
	 *
	 * @param string $label Label as shown.
	 * @return string
	 */
	public static function item_for_label( $label ) {
		$label = strtolower( trim( wp_strip_all_tags( $label ) ) );
		if ( '' === $label ) {
			return '';
		}
		$locale = Options::locale();
		foreach ( Menu_Items::items() as $item ) {
			if ( Menu_Items::available( $item ) && strtolower( trim( Menu_Items::label( $item, $locale ) ) ) === $label ) {
				return (string) $item['id'];
			}
		}
		return '';
	}

	/**
	 * The suggestions for the current page, best first. Empty when nothing is worth showing.
	 *
	 * @param string $locale  Locale ('' for the site's).
	 * @param array  $context Page context (null: the current page).
	 * @return array[] { id, label, score, why }.
	 */
	public static function candidates( $locale = '', $context = null ) {
		$locale  = '' !== $locale ? $locale : Options::locale();
		$context = null === $context ? Menu_Rules::context() : (array) $context;
		$found   = array();
		$add     = static function ( $id, $score, $why ) use ( &$found ) {
			$id = (string) $id;
			if ( ! isset( $found[ $id ] ) ) {
				$found[ $id ] = array(
					'score' => 0.0,
					'why'   => $why,
				);
			}
			$found[ $id ]['score'] += (float) $score;
		};

		// The owner's page rules for this page.
		foreach ( Menu_Rules::rules() as $rule ) {
			if ( 'hide' !== $rule['action'] && Menu_Rules::matches( $rule, $context ) ) {
				$add( $rule['item'], 10, 'rule' );
			}
		}
		// Items related to the page title (the matcher's own threshold applies).
		if ( ! empty( $context['title'] ) ) {
			foreach ( Menu_Matcher::related( (string) $context['title'], '', $locale, 4 ) as $match ) {
				$add( $match['id'], $match['score'], 'related' );
			}
		}
		// What visitors pick on this page.
		foreach ( self::popular( isset( $context['object_id'] ) ? (int) $context['object_id'] : 0 ) as $id => $count ) {
			$add( $id, min( 6, $count / 2 ), 'popular' );
		}

		$by_id = array();
		foreach ( Menu_Items::items() as $item ) {
			$by_id[ (string) $item['id'] ] = $item;
		}
		$out = array();
		foreach ( $found as $id => $hit ) {
			$item = isset( $by_id[ $id ] ) ? $by_id[ $id ] : null;
			if ( null === $item || ! Menu_Items::available( $item ) || $hit['score'] < self::STRICT ) {
				continue;
			}
			$label = Menu_Items::label( $item, $locale );
			if ( '' === $label ) {
				continue;
			}
			$out[] = array(
				'id'    => $id,
				'label' => $label,
				'score' => round( $hit['score'], 2 ),
				'why'   => $hit['why'],
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		/**
		 * The window's suggestions for this page (strict: an empty list shows nothing).
		 *
		 * @param array[] $out     { id, label, score, why }, best first.
		 * @param array   $context Page context.
		 */
		$out = (array) apply_filters( 'qcld_wpbot_chat/window_suggestions', $out, $context );
		return array_slice( $out, 0, self::MAX );
	}

	/**
	 * Clicks per item on one page in the last POPULAR_DAYS days, only items with POPULAR_MIN or more.
	 *
	 * @param int $page Post id of the page (0: none).
	 * @return array<string,int>
	 */
	public static function popular( $page ) {
		global $wpdb;
		if ( $page <= 0 || ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return array();
		}
		$since  = gmdate( 'Y-m-d H:i:s', time() - self::POPULAR_DAYS * DAY_IN_SECONDS );
		$table  = Schema::events_table();
		$sql    = "SELECT data FROM {$table} WHERE type = %s AND object_type = %s AND object_id = %d AND created_at >= %s ORDER BY id DESC LIMIT 500";
		$rows   = $wpdb->get_col( $wpdb->prepare( $sql, 'menu_click', 'page', $page, $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name from the schema, values prepared.
		$counts = array();
		foreach ( (array) $rows as $json ) {
			$data = json_decode( (string) $json, true );
			if ( is_array( $data ) && ! empty( $data['item'] ) ) {
				$id            = (string) $data['item'];
				$counts[ $id ] = isset( $counts[ $id ] ) ? $counts[ $id ] + 1 : 1;
			}
		}
		return array_filter(
			$counts,
			static function ( $n ) {
				return $n >= self::POPULAR_MIN;
			}
		);
	}

	/**
	 * Phrases for suggestions while typing and finish-my-question: start menu labels and FAQ
	 * questions. Menu items are marked so the window can press their button.
	 *
	 * @param string $locale Locale ('' for the site's).
	 * @return array[] { t: text, m: 1 for a menu item }.
	 */
	public static function phrases( $locale = '' ) {
		$locale = '' !== $locale ? $locale : Options::locale();
		$out    = array();
		$seen   = array();
		foreach ( Menu_Items::ordered( $locale ) as $item ) {
			$label = Menu_Items::available( $item ) ? trim( Menu_Items::label( $item, $locale ) ) : '';
			if ( '' !== $label && ! isset( $seen[ strtolower( $label ) ] ) ) {
				$seen[ strtolower( $label ) ] = true;
				$out[]                        = array(
					't' => $label,
					'm' => 1,
				);
			}
		}
		if ( class_exists( Faq::class ) ) {
			foreach ( Faq::entries( $locale ) as $entry ) {
				$question = trim( wp_strip_all_tags( (string) $entry['question'] ) );
				if ( '' !== $question && strlen( $question ) <= 160 && ! isset( $seen[ strtolower( $question ) ] ) ) {
					$seen[ strtolower( $question ) ] = true;
					$out[]                           = array( 't' => $question );
				}
			}
		}
		return array_slice( $out, 0, self::PHRASES );
	}

	/**
	 * What the window template and script need, for the window's guidance settings.
	 *
	 * @param array $theme Effective theme.
	 * @return array { candidates: array[], buttons: string HTML, phrases: array[], page: bool, tryask: bool, composer: bool, ghost: bool, nudge: bool }
	 */
	public static function data( array $theme ) {
		$guide = isset( $theme['guide'] ) ? (array) $theme['guide'] : array();
		$on    = static function ( $key ) use ( $guide ) {
			return ! empty( $guide[ $key ] );
		};
		$out   = array(
			'candidates' => array(),
			'buttons'    => '',
			'phrases'    => array(),
			'page'       => false,
			'tryask'     => false,
			'composer'   => $on( 'composer' ),
			'ghost'      => $on( 'ghost' ),
			'nudge'      => false,
		);
		if ( $on( 'page' ) || $on( 'tryask' ) || $on( 'nudge' ) ) {
			$out['candidates'] = self::candidates();
			$has               = (bool) $out['candidates'];
			$out['page']       = $has && $on( 'page' );
			$out['tryask']     = $has && $on( 'tryask' );
			$out['nudge']      = $has && $on( 'nudge' );
			if ( $has ) {
				$out['buttons'] = Menu_Items::render_ids( wp_list_pluck( $out['candidates'], 'id' ) );
			}
		}
		if ( $out['composer'] || $out['ghost'] ) {
			$out['phrases'] = self::phrases();
		}
		return $out;
	}
}
