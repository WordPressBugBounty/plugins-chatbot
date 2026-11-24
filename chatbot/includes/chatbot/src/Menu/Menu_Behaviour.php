<?php
/**
 * Start Menu > Behaviour (Start Menu reorganization SM3): four plain questions instead of the
 * checkboxes that used to be spread over the General tab.
 *
 * The answers are stored in the options the widget already reads, so the chat behaves exactly as
 * before. Every combination of the old checkboxes maps to one answer: the one that describes what
 * the widget actually did with it.
 *
 * Old options:
 * - When the chat opens: skip_wp_greetings, skip_wp_greetings_donot_show_menu,
 *   skip_wp_greetings_trigger_intent, show_menu_after_greetings, qcld_disable_beginning_start_menu.
 * - After each answer: qcld_disable_repited_startmenu (nothing; wins over the next one),
 *   wpbot_disable_repeatative (a Back to Start button), and Context Aware's "Buttons after answers".
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the Behaviour answers to and from the old options, and saves them with the settings form.
 */
final class Menu_Behaviour {

	/**
	 * Form field: an array with `on_open` and `after_answer`.
	 */
	const FIELD = 'qcld_wpbot_menu_behaviour';

	/**
	 * Option: how many start menu buttons show before "More options" (owner M2: 4).
	 */
	const TOP_COUNT = 'qcld_wpbot_menu_top_count';

	/**
	 * Old options that decide what happens when the chat opens, with the values each answer stores.
	 */
	const ON_OPEN = array(
		'name_menu' => array(
			'skip_wp_greetings'                 => '',
			'skip_wp_greetings_donot_show_menu' => '',
			'skip_wp_greetings_trigger_intent'  => '',
			'show_menu_after_greetings'         => '1',
			'qcld_disable_beginning_start_menu' => '',
		),
		'name_only' => array(
			'skip_wp_greetings'                 => '',
			'skip_wp_greetings_donot_show_menu' => '',
			'skip_wp_greetings_trigger_intent'  => '',
			'show_menu_after_greetings'         => '',
			'qcld_disable_beginning_start_menu' => '',
		),
		'menu'      => array(
			'skip_wp_greetings'                 => '1',
			'skip_wp_greetings_donot_show_menu' => '',
			'skip_wp_greetings_trigger_intent'  => '',
			'show_menu_after_greetings'         => '',
			'qcld_disable_beginning_start_menu' => '',
		),
		'greeting'  => array(
			'skip_wp_greetings'                 => '',
			'skip_wp_greetings_donot_show_menu' => '1',
			'skip_wp_greetings_trigger_intent'  => '',
			'show_menu_after_greetings'         => '',
			'qcld_disable_beginning_start_menu' => '',
		),
		// The widget only starts the intent when the start menu doesn't show first.
		'intent'    => array(
			'skip_wp_greetings'                 => '',
			'skip_wp_greetings_donot_show_menu' => '',
			'skip_wp_greetings_trigger_intent'  => '1',
			'show_menu_after_greetings'         => '',
			'qcld_disable_beginning_start_menu' => '1',
		),
	);

	/**
	 * Old options that decide what follows an answer, with the values each answer stores.
	 * `related` keeps the whole menu for answers that have no related buttons.
	 */
	const AFTER_ANSWER = array(
		'related' => array(
			'qcld_disable_repited_startmenu' => '',
			'wpbot_disable_repeatative'      => '',
		),
		'menu'    => array(
			'qcld_disable_repited_startmenu' => '',
			'wpbot_disable_repeatative'      => '',
		),
		'back'    => array(
			'qcld_disable_repited_startmenu' => '',
			'wpbot_disable_repeatative'      => '1',
		),
		'nothing' => array(
			'qcld_disable_repited_startmenu' => '1',
			'wpbot_disable_repeatative'      => '',
		),
	);

	/**
	 * Register the save step (after the host saved the settings form).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ), 11 );
	}

	/**
	 * The old options with their stored values. The settings page posts them back unchanged as
	 * hidden fields, so the host's save keeps them; save() then rewrites only what an answer changed.
	 *
	 * @return array Option name => stored value.
	 */
	public static function old_options() {
		$values = array();
		foreach ( array_merge( array_keys( self::ON_OPEN['name_menu'] ), array_keys( self::AFTER_ANSWER['menu'] ) ) as $name ) {
			$values[ $name ] = (string) get_option( $name, '' );
		}
		return $values;
	}

	/**
	 * Whether a stored value means "on" (the old checkboxes store '1', 1 or '').
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function on( $value ) {
		return '1' === (string) $value;
	}

	/**
	 * The current answers, read from the old options.
	 *
	 * @param array|null $options Option values (null: read them).
	 * @return array { on_open, after_answer, intent_hidden: bool (the old "trigger an intent" had no
	 *               effect because the start menu showed first) }.
	 */
	public static function current( $options = null ) {
		if ( null === $options ) {
			$options                          = self::old_options();
			$options['context_after_answers'] = Menu_Matcher::after_answers_on();
		}
		$get = function ( $name ) use ( $options ) {
			return isset( $options[ $name ] ) && self::on( $options[ $name ] );
		};

		$skip    = $get( 'skip_wp_greetings' ) || $get( 'skip_wp_greetings_donot_show_menu' ) || $get( 'skip_wp_greetings_trigger_intent' );
		$no_menu = $get( 'skip_wp_greetings_donot_show_menu' ) || $get( 'qcld_disable_beginning_start_menu' );
		$hidden  = false;
		if ( $skip ) {
			if ( ! $no_menu ) {
				$on_open = 'menu';
				$hidden  = $get( 'skip_wp_greetings_trigger_intent' );
			} elseif ( $get( 'skip_wp_greetings_trigger_intent' ) ) {
				$on_open = 'intent';
			} else {
				$on_open = 'greeting';
			}
		} else {
			$on_open = $get( 'show_menu_after_greetings' ) && ! $no_menu ? 'name_menu' : 'name_only';
		}

		if ( $get( 'qcld_disable_repited_startmenu' ) ) {
			$after = 'nothing';
		} elseif ( $get( 'wpbot_disable_repeatative' ) ) {
			$after = 'back';
		} elseif ( ! empty( $options['context_after_answers'] ) ) {
			$after = 'related';
		} else {
			$after = 'menu';
		}

		return array(
			'on_open'       => $on_open,
			'after_answer'  => $after,
			'intent_hidden' => $hidden,
		);
	}

	/**
	 * The old option values for a pair of answers. Unknown answers keep today's default.
	 *
	 * @param string $on_open      When the chat opens.
	 * @param string $after_answer After each answer.
	 * @return array Option name => value.
	 */
	public static function options_for( $on_open, $after_answer ) {
		$on_open      = isset( self::ON_OPEN[ $on_open ] ) ? $on_open : 'name_menu';
		$after_answer = isset( self::AFTER_ANSWER[ $after_answer ] ) ? $after_answer : 'menu';
		return array_merge( self::ON_OPEN[ $on_open ], self::AFTER_ANSWER[ $after_answer ] );
	}

	/**
	 * The top-level button count (2 to 8, default 4).
	 *
	 * @return int
	 */
	public static function top_count() {
		$count = (int) get_option( self::TOP_COUNT, 4 );
		return $count > 0 ? max( 2, min( 8, $count ) ) : 4;
	}

	/**
	 * Store posted answers.
	 *
	 * @param array $posted { on_open, after_answer, top_count }; a missing answer is left unchanged.
	 * @return void
	 */
	public static function save( array $posted ) {
		$current = self::current();
		$on_open = isset( $posted['on_open'] ) ? sanitize_key( (string) $posted['on_open'] ) : $current['on_open'];
		$after   = isset( $posted['after_answer'] ) ? sanitize_key( (string) $posted['after_answer'] ) : $current['after_answer'];
		if ( 'related' === $after && ! self::related_available() ) {
			$after = 'menu';
		}
		// An unchanged answer keeps the stored options as they are (no rewrite of an old combination).
		$changed_open  = $on_open !== $current['on_open'];
		$changed_after = $after !== $current['after_answer'];
		foreach ( self::options_for( $on_open, $after ) as $name => $value ) {
			$is_open = array_key_exists( $name, self::ON_OPEN['name_menu'] );
			if ( ( $is_open && $changed_open ) || ( ! $is_open && $changed_after ) ) {
				update_option( $name, $value );
			}
		}
		if ( $changed_after && ( 'related' === $after || 'related' === $current['after_answer'] ) ) {
			$context = get_option( Menu_Matcher::SETTINGS, array() );
			$context = is_array( $context ) ? $context : array();
			if ( 'related' === $after ) {
				$context['enabled']       = true;
				$context['after_answers'] = true;
			} else {
				$context['after_answers'] = false;
			}
			update_option( Menu_Matcher::SETTINGS, $context );
		}
		if ( isset( $posted['top_count'] ) ) {
			update_option( self::TOP_COUNT, max( 2, min( 8, (int) $posted['top_count'] ) ) );
		}
	}

	/**
	 * Whether "Related buttons + More options" can be offered (Context Aware is installed).
	 *
	 * @return bool
	 */
	public static function related_available() {
		/**
		 * Whether the "Related buttons" answer is offered under Start Menu > Behaviour.
		 *
		 * @param bool $available Default false: Context Aware is a WPBot Pro feature, and WPBot Pro
		 *                        switches this on.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/menu_related_available', false );
	}

	/**
	 * Save the answers with the ChatBot Settings form, after the host saved its own fields (the old
	 * options come back unchanged from hidden fields; see old_options()).
	 *
	 * @return void
	 */
	public static function maybe_save() {
		// phpcs:disable WordPress.Security.NonceVerification -- Checked below, the host form's nonce.
		if ( empty( $_POST[ self::FIELD ] ) || ! is_array( $_POST[ self::FIELD ] ) || ! isset( $_POST['submit'] ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! in_array( $page, array( 'wpbot', 'wpbot-panel' ), true ) ) {
			return;
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
			return;
		}
		$capability = function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'wpbot_settings' ) : 'manage_options';
		if ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$posted = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST[ self::FIELD ] ) );
		self::save( $posted );
	}
}
