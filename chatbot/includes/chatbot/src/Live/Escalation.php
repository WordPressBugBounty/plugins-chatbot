<?php
/**
 * When the bot offers a person (live chat plan 4.1.1; Context Aware 2.3; owner D9, 19 November 2026).
 *
 * The bot answers first. The offer ("Chat with our team" / "No thanks") appears inside a bot reply,
 * only when a rule fires; never as a permanent button. With nobody available it becomes
 * "Leave a message".
 *
 * Modes: bot_first (default; all rules), cant_answer (not sure and thumbs down only), always (no
 * offers in replies: a live chat button in the start menu). The trigger word starts a live chat in
 * every mode (the hosts' chat script). A thumbs down that reaches the not-sure count offers a person
 * right away (POST /live/thumbs returns the offer).
 * Rules: not sure again (after N, default 2), a request for a person (deflected once), a thumbs
 * down (counts as not sure), topics that always need a person (off), at least N bot answers before
 * an offer (default 2; not for topics). WPBot Pro adds "valuable visitors" on
 * `qcld_wpbot_chat/live_offer_now`.
 *
 * Counts are kept per chat session for a day, one write per answer, only when live chat is on.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Escalation rules.
 */
final class Escalation {

	/**
	 * Settings option.
	 */
	const OPTION = 'qcld_wpbot_chat_live_escalation';

	/**
	 * Words that ask for a person (English; the AI tool request_human covers other languages).
	 */
	const REQUEST = '/\b(human|real person|a person|someone real|agent|operator|representative|live (chat|agent|person)|talk to (someone|somebody|a person|support|staff)|speak (to|with) (someone|somebody|a person|support|staff))\b/i';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/reply_extras', array( __CLASS__, 'reply_extras' ), 10, 3 );
		add_filter( 'qcld_wpbot_chat/menu_item_available', array( __CLASS__, 'menu_item_available' ), 10, 2 );
		// After the Context Aware page rules (10): they choose the items; this only adds the button.
		add_filter( 'qcld_wpbot_chat/menu_items_shown', array( __CLASS__, 'menu_button' ), 20, 2 );
	}

	/**
	 * Settings with the defaults (D9).
	 *
	 * @return array
	 */
	public static function settings() {
		$saved               = get_option( self::OPTION, array() );
		$s                   = array_merge(
			array(
				'mode'           => 'bot_first',
				'not_sure_after' => 2,
				'deflect_once'   => true,
				'thumbs_down'    => true,
				'min_turns'      => 2,
				'topics'         => array(),
			),
			is_array( $saved ) ? $saved : array()
		);
		$s['mode']           = in_array( $s['mode'], array( 'bot_first', 'cant_answer', 'always' ), true ) ? $s['mode'] : 'bot_first';
		$s['not_sure_after'] = max( 1, min( 5, (int) $s['not_sure_after'] ) );
		$s['min_turns']      = max( 0, min( 10, (int) $s['min_turns'] ) );
		$s['topics']         = array_values( array_filter( array_map( 'trim', (array) $s['topics'] ) ) );
		return $s;
	}

	/**
	 * Save settings.
	 *
	 * @param array $form mode, not_sure_after, deflect_once, thumbs_down, min_turns, topics (comma list).
	 * @return void
	 */
	public static function save( array $form ) {
		$topics = isset( $form['topics'] ) ? ( is_array( $form['topics'] ) ? $form['topics'] : explode( ',', (string) $form['topics'] ) ) : array();
		update_option(
			self::OPTION,
			array(
				'mode'           => isset( $form['mode'] ) ? sanitize_key( (string) $form['mode'] ) : 'bot_first',
				'not_sure_after' => isset( $form['not_sure_after'] ) ? (int) $form['not_sure_after'] : 2,
				'deflect_once'   => ! empty( $form['deflect_once'] ),
				'thumbs_down'    => ! empty( $form['thumbs_down'] ),
				'min_turns'      => isset( $form['min_turns'] ) ? (int) $form['min_turns'] : 2,
				'topics'         => array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $topics ) ) ) ), 0, 30 ),
			)
		);
	}

	/**
	 * The mode in plain words (Live Chat settings panel).
	 *
	 * @return string
	 */
	public static function describe() {
		$s = self::settings();
		if ( 'always' === $s['mode'] ) {
			return __( 'Always available: visitors can start a live chat from the start menu at any time. They may skip the bot.', 'chatbot' );
		}
		if ( 'cant_answer' === $s['mode'] ) {
			/* translators: %d: number of not-sure answers */
			return sprintf( _n( 'Only when the bot can\'t answer: live chat is offered after %d answer the bot isn\'t sure about, or a thumbs down.', 'Only when the bot can\'t answer: live chat is offered after %d answers the bot isn\'t sure about, or a thumbs down.', $s['not_sure_after'], 'chatbot' ), $s['not_sure_after'] );
		}
		/* translators: 1: number of not-sure answers, 2: minimum bot answers */
		return sprintf( __( 'Bot first: live chat is offered after %1$d answers the bot isn\'t sure about, a thumbs down, or when a visitor asks for a person again after the bot tried, and never before %2$d bot answers.', 'chatbot' ), $s['not_sure_after'], $s['min_turns'] );
	}

	/**
	 * Counts of a chat session.
	 *
	 * @param string $session Session.
	 * @return array answers, not_sure, asked, offered.
	 */
	public static function counts( $session ) {
		$c = get_transient( self::key( $session ) );
		return array_merge(
			array(
				'answers'  => 0,
				'not_sure' => 0,
				'asked'    => 0,
				'offered'  => 0,
			),
			is_array( $c ) ? $c : array()
		);
	}

	/**
	 * Transient key.
	 *
	 * @param string $session Session.
	 * @return string
	 */
	private static function key( $session ) {
		return 'qcld_wpbot_chat_live_esc_' . substr( md5( (string) $session ), 0, 20 );
	}

	/**
	 * Record a bot answer and decide whether it carries the offer.
	 *
	 * @param string $session  Session ('' never offers).
	 * @param string $text     The visitor's question.
	 * @param bool   $not_sure Whether the bot wasn't sure.
	 * @return string '' or the reason (not_sure, asked, topic, valuable).
	 */
	public static function record( $session, $text, $not_sure ) {
		$s = self::settings();
		if ( '' === (string) $session || 'always' === $s['mode'] || ! Live::enabled() ) {
			return '';
		}
		$c = self::counts( $session );
		++$c['answers'];
		if ( $not_sure ) {
			++$c['not_sure'];
		}
		$asks   = 'bot_first' === $s['mode'] && (bool) preg_match( self::REQUEST, (string) $text );
		$reason = '';
		if ( 'bot_first' === $s['mode'] && self::topic( (string) $text, $s['topics'] ) ) {
			$reason = 'topic';
		} elseif ( $c['not_sure'] >= $s['not_sure_after'] ) {
			$reason = 'not_sure';
		} elseif ( $asks ) {
			++$c['asked'];
			if ( ! $s['deflect_once'] || $c['asked'] >= 2 || $c['not_sure'] > 0 ) {
				$reason = 'asked';
			}
		}
		if ( '' === $reason && 'bot_first' === $s['mode'] ) {
			/**
			 * Offer a person now for another reason (WPBot Pro: valuable visitors). Return a reason
			 * id, or ''.
			 *
			 * @param string $reason  Default ''.
			 * @param string $session Session.
			 * @param array  $counts  Counts.
			 */
			$reason = (string) apply_filters( 'qcld_wpbot_chat/live_offer_now', '', $session, $c );
		}
		if ( '' !== $reason && ! in_array( $reason, array( 'topic', 'valuable' ), true ) && $c['answers'] < $s['min_turns'] ) {
			$reason = '';
		}
		if ( '' !== $reason && Conversations::by_session( $session ) ) {
			$reason = '';
			// Already talking to a person.
		}
		if ( '' !== $reason ) {
			++$c['offered'];
			$c['not_sure'] = 0;
			$c['asked']    = 0;
		}
		set_transient( self::key( $session ), $c, DAY_IN_SECONDS );
		return $reason;
	}

	/**
	 * A thumbs down on a bot answer counts like a not-sure answer. When that reaches "Offer after N
	 * answers the bot isn't sure about", the offer comes right away, under the thumbs down (owner,
	 * 23 November 2026: several thumbs downs never offered a person, because the offer waited for
	 * the next bot answer and for the minimum number of bot answers).
	 *
	 * @param string $session Session.
	 * @return string '' or 'thumbs_down' (show the offer now).
	 */
	public static function thumbs_down( $session ) {
		$s = self::settings();
		if ( '' === (string) $session || empty( $s['thumbs_down'] ) || 'always' === $s['mode'] || ! Live::enabled() ) {
			return '';
		}
		$c = self::counts( $session );
		++$c['not_sure'];
		$reason = '';
		if ( $c['not_sure'] >= $s['not_sure_after'] && ! Conversations::by_session( $session ) ) {
			$reason = 'thumbs_down';
			++$c['offered'];
			$c['not_sure'] = 0;
			$c['asked']    = 0;
		}
		set_transient( self::key( $session ), $c, DAY_IN_SECONDS );
		return $reason;
	}

	/**
	 * Whether a question names a topic that always needs a person.
	 *
	 * @param string   $text   Question.
	 * @param string[] $topics Topics.
	 * @return bool
	 */
	private static function topic( $text, array $topics ) {
		$text = strtolower( $text );
		foreach ( $topics as $topic ) {
			if ( '' !== $topic && false !== strpos( $text, strtolower( $topic ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The offer under a reply: a person, or "Leave a message" when nobody is available.
	 *
	 * @param string $reason     Reason.
	 * @param string $department Department.
	 * @return string
	 */
	public static function offer_html( $reason, $department = '' ) {
		if ( ! Routing::available( $department ) ) {
			return '<div class="qcld-wpbot-live-offer qcld-wpbot-live-offline"><p class="qcld-wpbot-live-offer-title">' . esc_html__( 'Our team is away right now. Leave a message and we will reply by email.', 'chatbot' ) . '</p>'
				. '<span class="qcld-chatbot-suggest-email">' . esc_html__( 'Leave a message', 'chatbot' ) . '</span></div>';
		}
		return '<div class="qcld-wpbot-live-offer"><p class="qcld-wpbot-live-offer-title">' . esc_html__( 'Would you like to chat with a person?', 'chatbot' ) . '</p>'
			. '<span class="qcld-chatbot-live" data-live="start" data-reason="' . esc_attr( $reason ) . '">' . esc_html__( 'Chat with our team', 'chatbot' ) . '</span>'
			. '<span class="qcld-chatbot-live" data-live="no">' . esc_html__( 'No thanks', 'chatbot' ) . '</span></div>';
	}

	/**
	 * Filter for the HTML under an answer.
	 *
	 * @param string                                   $extra   HTML so far.
	 * @param \QuantumCloud\Chatbot\Conversation\Reply $reply   Reply.
	 * @param array                                    $request session, text.
	 * @return string
	 */
	public static function reply_extras( $extra, $reply, $request ) {
		$session = isset( $request['session'] ) ? (string) $request['session'] : '';
		$reason  = self::record( $session, isset( $request['text'] ) ? (string) $request['text'] : '', ! empty( $reply->data['not_sure'] ) );
		return '' === $reason ? $extra : $extra . self::offer_html( $reason );
	}

	/**
	 * Live chat start menu items show only in "Always available" mode, when live chat is on.
	 *
	 * @param bool  $available Default.
	 * @param array $item      Item.
	 * @return bool
	 */
	public static function menu_item_available( $available, $item ) {
		if ( ! $available || ! self::is_live_item( (array) $item ) ) {
			return $available;
		}
		return Live::enabled() && 'always' === self::settings()['mode'];
	}

	/**
	 * "Always available" puts a live chat button in the start menu (owner, 23 November 2026): the
	 * menu's own Live chat item when it has one, else "Chat with our team" first. Not in the
	 * admin's menu builder, so it is never saved as an item.
	 *
	 * @param array[] $items  Items shown.
	 * @param string  $locale Locale.
	 * @return array[]
	 */
	public static function menu_button( $items, $locale ) {
		unset( $locale );
		$items = (array) $items;
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! Live::enabled() || 'always' !== self::settings()['mode'] ) {
			return $items;
		}
		foreach ( $items as $item ) {
			if ( empty( $item['hidden'] ) && self::is_live_item( (array) $item ) ) {
				return $items;
			}
		}
		$label = __( 'Chat with our team', 'chatbot' );
		// First: the start menu shows four top-level buttons and puts the rest under "More options".
		array_unshift(
			$items,
			array(
				'id'     => 'live-chat',
				'key'    => 'live:start',
				'type'   => 'live',
				'attrs'  => array(
					'class'     => 'qcld-chatbot-live',
					'data-live' => 'start',
				),
				'labels' => array( 'auto' => $label ),
				'hidden' => false,
			)
		);
		return $items;
	}

	/**
	 * Whether a start menu item opens live chat (the old "livechat" keyword or the new button).
	 *
	 * @param array $item Item.
	 * @return bool
	 */
	public static function is_live_item( array $item ) {
		$attrs = isset( $item['attrs'] ) ? (array) $item['attrs'] : array();
		if ( isset( $attrs['data-live'] ) ) {
			return true;
		}
		$text = isset( $attrs['data-text'] ) ? strtolower( (string) $attrs['data-text'] ) : '';
		return '' !== $text && in_array( $text, array( 'livechat', 'live chat', 'live_chat' ), true );
	}
}
