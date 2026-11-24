<?php
/**
 * Context Aware Start Menu (CA3): page rules and the page-aware order of the first menu.
 *
 * A page rule names a start menu item, an action and conditions:
 *   array( 'item' => <item id>, 'action' => 'first' | 'only' | 'hide',
 *          'conditions' => array( 'match' => 'all' | 'any', 'rules' => array( … ) ),
 *          'greeting' => 'Questions about this product? I can help.' )
 *
 * Conditions use the shared conditions library (contract C-2, WPBot Pro). The engine never checks
 * the edition: without an evaluator on `qcld_wpbot_chat/menu_rule_matches`, rules never match and
 * the menu shows as it is. Rules run where the menu is shown (page render and chat requests); no
 * extra request from the widget and no AI call.
 *
 * - hide: the item is left out where its conditions match;
 * - only: the item shows only where one of its "only" rules matches;
 * - first: the item moves to the front where its conditions match (in rule order).
 * With "Order the rest by page" on, the other items follow by how well they match the page title.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Page rules.
 */
final class Menu_Rules {

	/**
	 * Option: the rules (a list).
	 */
	const OPTION = 'qcld_wpbot_menu_page_rules';

	/**
	 * Allowed actions.
	 */
	const ACTIONS = array( 'first', 'only', 'hide' );

	/**
	 * The page context of this request, once.
	 *
	 * @var array|null
	 */
	private static $context = null;

	/**
	 * The greeting of the first matching rule, from the last apply().
	 *
	 * @var string
	 */
	private static $greeting = '';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/menu_items_shown', array( __CLASS__, 'filter_shown' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ), 11 );
		add_action( 'admin_init', array( __CLASS__, 'migrate_v1' ) );
		add_action( 'wp_ajax_qcld_wpbot_menu_preview', array( __CLASS__, 'ajax_preview' ) );
	}

	/**
	 * The stored rules, cleaned.
	 *
	 * @return array[]
	 */
	public static function rules() {
		$rules = get_option( self::OPTION, array() );
		return self::clean( is_array( $rules ) ? $rules : array() );
	}

	/**
	 * Clean a list of rules (from the settings form or the option).
	 *
	 * @param array $rules Rules.
	 * @return array[]
	 */
	public static function clean( array $rules ) {
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['item'] ) ) {
				continue;
			}
			$action = isset( $rule['action'] ) && in_array( $rule['action'], self::ACTIONS, true ) ? $rule['action'] : 'first';
			$set    = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
			$conds  = array();
			foreach ( isset( $set['rules'] ) ? (array) $set['rules'] : array() as $cond ) {
				if ( ! is_array( $cond ) || empty( $cond['type'] ) ) {
					continue;
				}
				$conds[] = array(
					'type'  => sanitize_key( (string) $cond['type'] ),
					'op'    => isset( $cond['op'] ) ? substr( preg_replace( '/[^a-z_<>=]/', '', strtolower( (string) $cond['op'] ) ), 0, 16 ) : 'is',
					'value' => isset( $cond['value'] ) ? sanitize_text_field( (string) $cond['value'] ) : '',
				);
			}
			$out[] = array(
				'item'       => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $rule['item'] ) ),
				'action'     => $action,
				'conditions' => array(
					'match' => isset( $set['match'] ) && 'any' === $set['match'] ? 'any' : 'all',
					'rules' => $conds,
				),
				'greeting'   => isset( $rule['greeting'] ) ? sanitize_text_field( (string) $rule['greeting'] ) : '',
			);
		}//end foreach
		return $out;
	}

	/**
	 * Whether a rule's conditions match a context (through the conditions library).
	 *
	 * @param array $rule    Rule.
	 * @param array $context Page context.
	 * @return bool
	 */
	public static function matches( array $rule, array $context ) {
		if ( empty( $rule['conditions']['rules'] ) ) {
			return true;
			// A rule with no conditions applies everywhere.
		}
		/**
		 * Whether a page rule's conditions match. WPBot Pro evaluates them with the conditions
		 * library (C-2); without an evaluator they never match.
		 *
		 * @param bool  $matches    Default false.
		 * @param array $conditions Rule set (match, rules).
		 * @param array $context    Page context.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/menu_rule_matches', false, $rule['conditions'], $context );
	}

	/**
	 * Apply the page rules and the page-aware order to the items of a menu.
	 *
	 * @param array[] $items   Items in order.
	 * @param array   $context Page context (page_type, url, title, …).
	 * @param array   $rules   Rules (null: the stored ones).
	 * @param string  $locale  Locale for the page-aware order.
	 * @param bool    $force   Apply even when the switches are off (the settings preview).
	 * @return array[]
	 */
	public static function apply( array $items, array $context, $rules = null, $locale = '', $force = false ) {
		self::$greeting = '';
		$settings       = Menu_Matcher::settings();
		if ( ! $force && ( empty( $settings['enabled'] ) || empty( $settings['page_aware'] ) ) ) {
			return $items;
		}
		$rules   = null === $rules ? self::rules() : $rules;
		$hide    = array();
		$first   = array();
		$only    = array();
		$only_ok = array();
		foreach ( $rules as $rule ) {
			$id = (string) $rule['item'];
			if ( 'only' === $rule['action'] ) {
				$only[ $id ] = true;
			}
			if ( ! self::matches( $rule, $context ) ) {
				continue;
			}
			if ( '' === self::$greeting && '' !== $rule['greeting'] && 'hide' !== $rule['action'] ) {
				self::$greeting = $rule['greeting'];
			}
			if ( 'hide' === $rule['action'] ) {
				$hide[ $id ] = true;
			} elseif ( 'only' === $rule['action'] ) {
				$only_ok[ $id ] = true;
			} elseif ( ! in_array( $id, $first, true ) ) {
				$first[] = $id;
			}
		}

		$kept = array();
		foreach ( $items as $item ) {
			$id = (string) $item['id'];
			if ( isset( $hide[ $id ] ) || ( isset( $only[ $id ] ) && ! isset( $only_ok[ $id ] ) ) ) {
				continue;
			}
			$kept[ $id ] = $item;
		}

		$front = array();
		foreach ( $first as $id ) {
			if ( isset( $kept[ $id ] ) ) {
				$front[] = $kept[ $id ];
				unset( $kept[ $id ] );
			}
		}
		$rest = array_values( $kept );
		if ( ! empty( $settings['order_rest'] ) && ! empty( $context['title'] ) ) {
			$rest = self::by_page( $rest, (string) $context['title'], $locale );
		}
		return array_merge( $front, $rest );
	}

	/**
	 * Items ordered by how well they match the page title (stable for items with the same score).
	 *
	 * @param array[] $items  Items.
	 * @param string  $title  Page title.
	 * @param string  $locale Locale.
	 * @return array[]
	 */
	private static function by_page( array $items, $title, $locale ) {
		$page   = array_unique( Menu_Matcher::words( $title ) );
		$scored = array();
		foreach ( $items as $index => $item ) {
			$label    = Menu_Items::label( $item, $locale );
			$words    = Menu_Matcher::keywords( $item, $locale, $label );
			$scored[] = array( count( array_intersect( $page, $words ) ), $index, $item );
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b[0] !== $a[0] ? $b[0] - $a[0] : $a[1] - $b[1];
			}
		);
		return array_column( $scored, 2 );
	}

	/**
	 * The greeting of the first matching rule (after the menu was shown).
	 *
	 * @return string
	 */
	public static function greeting() {
		return self::$greeting;
	}

	/**
	 * The page context of the current request.
	 *
	 * @return array
	 */
	public static function context() {
		if ( null !== self::$context ) {
			return self::$context;
		}
		$context = array(
			'url'   => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'title' => function_exists( 'wp_get_document_title' ) && did_action( 'wp' ) ? wp_get_document_title() : '',
		);
		if ( function_exists( 'get_queried_object_id' ) && did_action( 'wp' ) ) {
			$context['object_id'] = (int) get_queried_object_id();
		}
		/**
		 * Page context for page rules (WPBot Pro adds the conditions library's context: page type,
		 * role, cart, campaign and so on).
		 *
		 * @param array $context url, title, object_id.
		 */
		self::$context = (array) apply_filters( 'qcld_wpbot_chat/menu_rule_context', $context );
		return self::$context;
	}

	/**
	 * Filter for the items a shown menu uses (Menu_Items::render()).
	 *
	 * @param array[] $items  Items in order.
	 * @param string  $locale Locale.
	 * @return array[]
	 */
	public static function filter_shown( $items, $locale ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $items;
		}
		return self::apply( (array) $items, self::context(), null, (string) $locale );
	}

	/**
	 * Save the Context Aware settings, page rules and item keywords with the ChatBot Settings form.
	 *
	 * @return void
	 */
	public static function maybe_save() {
		// phpcs:disable WordPress.Security.NonceVerification -- Checked below, the host form's nonce.
		if ( ! isset( $_POST['qcld_wpbot_context_menu_form'], $_POST['submit'] ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! in_array( $page, array( 'wpbot', 'wpbot-panel' ), true ) || ! self::allowed() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in allowed(); each field is cleaned in save() and clean().
		$form  = isset( $_POST['qcld_wpbot_context_menu'] ) && is_array( $_POST['qcld_wpbot_context_menu'] ) ? wp_unslash( $_POST['qcld_wpbot_context_menu'] ) : array();
		$rules = isset( $_POST['qcld_wpbot_menu_page_rules'] ) ? json_decode( wp_unslash( $_POST['qcld_wpbot_menu_page_rules'] ), true ) : array();
		$words = isset( $_POST['qcld_wpbot_menu_keywords'] ) && is_array( $_POST['qcld_wpbot_menu_keywords'] ) ? wp_unslash( $_POST['qcld_wpbot_menu_keywords'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		self::save( (array) $form, is_array( $rules ) ? $rules : array(), (array) $words );
	}

	/**
	 * Whether the current request may change the settings (the host form's nonce and capability).
	 *
	 * @return bool
	 */
	private static function allowed() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- This is the check.
		if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
			return false;
		}
		$capability = function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'wpbot_settings' ) : 'manage_options';
		return current_user_can( $capability ) || current_user_can( 'manage_options' );
	}

	/**
	 * Store the settings (switches, numbers), the rules and the keywords.
	 *
	 * @param array $form     enabled, page_aware, order_rest, max, threshold, more ('1' or '').
	 * @param array $rules    Rules.
	 * @param array $keywords Item key => keywords (comma separated) for the site language.
	 * @return void
	 */
	public static function save( array $form, array $rules, array $keywords = array() ) {
		$saved    = get_option( Menu_Matcher::SETTINGS, array() );
		$settings = is_array( $saved ) ? $saved : array();
		foreach ( array( 'enabled', 'page_aware', 'order_rest', 'more' ) as $key ) {
			$settings[ $key ] = ! empty( $form[ $key ] );
		}
		// "Related buttons" under Behaviour (saved just before) keeps Context Aware on.
		if ( ! empty( $settings['after_answers'] ) ) {
			$settings['enabled'] = true;
		}
		if ( isset( $form['max'] ) ) {
			$settings['max'] = max( 1, min( 4, (int) $form['max'] ) );
		}
		if ( isset( $form['threshold'] ) && isset( Menu_Matcher::THRESHOLDS[ $form['threshold'] ] ) ) {
			$settings['threshold'] = (string) $form['threshold'];
		}
		update_option( Menu_Matcher::SETTINGS, $settings );
		update_option( self::OPTION, self::clean( $rules ), false );

		if ( $keywords ) {
			$locale   = \QuantumCloud\Chatbot\Settings\Options::locale();
			$profiles = get_option( Menu_Matcher::PROFILES, array() );
			$profiles = is_array( $profiles ) ? $profiles : array();
			foreach ( $keywords as $key => $list ) {
				$key  = sanitize_text_field( (string) $key );
				$list = array_values( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $list ) ) ) ) );
				if ( ! isset( $profiles[ $key ] ) || ! is_array( $profiles[ $key ] ) ) {
					$profiles[ $key ] = array();
				}
				$own                         = isset( $profiles[ $key ][ $locale ] ) && is_array( $profiles[ $key ][ $locale ] ) ? $profiles[ $key ][ $locale ] : array();
				$own['keywords']             = $list;
				$profiles[ $key ][ $locale ] = $own;
			}
			update_option( Menu_Matcher::PROFILES, $profiles, false );
		}
	}

	/**
	 * Version 1 of Context Aware ("Dynamic AI menu", `qcld_dynamic_ai_menu` = 1) turns "Buttons after
	 * answers" on once, when there are no version 2 settings yet (owner C5).
	 *
	 * @return void
	 */
	public static function migrate_v1() {
		if ( '1' !== (string) get_option( 'qcld_dynamic_ai_menu' ) || false !== get_option( Menu_Matcher::SETTINGS, false ) ) {
			return;
		}
		update_option(
			Menu_Matcher::SETTINGS,
			array(
				'enabled'       => true,
				'after_answers' => true,
			)
		);
	}

	/**
	 * Preview for the settings screen: the first menu on a described page and the buttons after a
	 * question. Nothing is sent to visitors.
	 *
	 * @return void
	 */
	public static function ajax_preview() {
		check_ajax_referer( 'qcld_wpbot_menu_preview', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}
		$context = array(
			'page_type' => isset( $_POST['page_type'] ) ? sanitize_key( wp_unslash( $_POST['page_type'] ) ) : '',
			'url'       => isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '',
			'title'     => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'logged_in' => ! empty( $_POST['logged_in'] ),
		);
		/** This filter is documented in context(). */
		$context = (array) apply_filters( 'qcld_wpbot_chat/menu_rule_preview_context', $context );
		$rules   = isset( $_POST['rules'] ) ? json_decode( wp_unslash( $_POST['rules'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, cleaned in clean().
		$rules   = is_array( $rules ) ? self::clean( $rules ) : null;
		$locale  = \QuantumCloud\Chatbot\Settings\Options::locale();
		$items   = array_filter( Menu_Items::ordered( $locale ), array( Menu_Items::class, 'available' ) );
		$menu    = array();
		foreach ( self::apply( array_values( $items ), $context, $rules, $locale, true ) as $item ) {
			$menu[] = Menu_Items::label( $item, $locale );
		}
		$greeting = self::greeting();
		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
		$related  = '' !== $question ? array_column( Menu_Matcher::related( $question, '', $locale ), 'label' ) : array();
		wp_send_json_success(
			array(
				'menu'     => array_values( array_filter( $menu ) ),
				'greeting' => $greeting,
				'related'  => $related,
			)
		);
	}
}
