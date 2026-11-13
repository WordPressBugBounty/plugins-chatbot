<?php
/**
 * Chat sessions: one row per message in qcld_wpbot_chat_messages, plus the
 * legacy transcript (wpbot_user + wpbot_conversation, entity-encoded HTML) that
 * today's Sessions screens, CSV export and emails read. The legacy transcript is
 * built on the server from the stored messages, so visitors no longer post HTML.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Sessions;

use QuantumCloud\Chatbot\RateLimit\Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Session store.
 */
final class Session_Store {

	/**
	 * Most characters stored per message.
	 */
	const MAX_TEXT = 20000;

	/**
	 * Most messages kept per session.
	 */
	const MAX_MESSAGES = 500;

	/**
	 * Whether sessions are saved (the Chat Sessions switch: when the module is off, nothing is saved).
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Whether chat sessions are saved.
		 *
		 * @param bool $enabled Default: the legacy save handler is loaded (Chat Sessions on).
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/sessions_enabled', function_exists( 'qcld_wb_chatbot_conversation_save' ) );
	}

	/**
	 * Add a message.
	 *
	 * @param string $session_id Session.
	 * @param string $role       user, bot, agent or system.
	 * @param string $text       Text (plain or Markdown; HTML is kept as text).
	 * @param array  $extra      channel, source, payload (array), model, tokens_in, tokens_out.
	 * @return int Row id, 0 when not stored.
	 */
	public static function add( $session_id, $role, $text, array $extra = array() ) {
		global $wpdb;
		$session_id = self::clean_id( $session_id );
		$role       = in_array( $role, array( 'user', 'bot', 'agent', 'system' ), true ) ? $role : '';
		$text       = (string) $text;
		if ( '' === $session_id || '' === $role || '' === trim( $text ) || ! self::enabled() ) {
			return 0;
		}
		if ( self::count( $session_id ) >= self::MAX_MESSAGES ) {
			return 0;
		}
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::messages_table(),
			array(
				'session_id' => $session_id,
				'role'       => $role,
				'channel'    => isset( $extra['channel'] ) ? substr( sanitize_key( $extra['channel'] ), 0, 20 ) : 'web',
				'source'     => isset( $extra['source'] ) ? substr( sanitize_key( $extra['source'] ), 0, 30 ) : '',
				'text'       => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT ) : substr( $text, 0, self::MAX_TEXT ),
				'payload'    => isset( $extra['payload'] ) && $extra['payload'] ? wp_json_encode( $extra['payload'] ) : null,
				'model'      => isset( $extra['model'] ) ? substr( sanitize_text_field( (string) $extra['model'] ), 0, 100 ) : '',
				'tokens_in'  => isset( $extra['tokens_in'] ) ? max( 0, (int) $extra['tokens_in'] ) : 0,
				'tokens_out' => isset( $extra['tokens_out'] ) ? max( 0, (int) $extra['tokens_out'] ) : 0,
				'provider'   => isset( $extra['provider'] ) ? substr( sanitize_key( (string) $extra['provider'] ), 0, 40 ) : '',
				'cost'       => isset( $extra['cost'] ) ? max( 0.0, (float) $extra['cost'] ) : 0.0,
				'created'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * AI answers, tokens and estimated cost per session (Chat Sessions list and detail).
	 *
	 * @param string[] $session_ids Session ids (at most 200).
	 * @return array<string,array> session id => { replies, tokens_in, tokens_out, cost }.
	 */
	public static function costs( array $session_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_id' ), array_slice( $session_ids, 0, 200 ) ) ) ) );
		$out = array();
		if ( ! $ids ) {
			return $out;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; one placeholder per id.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT session_id, COUNT(*) AS replies, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(cost) AS cost FROM %i WHERE role = 'bot' AND model <> '' AND session_id IN ( {$placeholders} ) GROUP BY session_id", array_merge( array( Schema::messages_table() ), $ids ) ), ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[ (string) $row['session_id'] ] = array(
				'replies'    => (int) $row['replies'],
				'tokens_in'  => (int) $row['tokens_in'],
				'tokens_out' => (int) $row['tokens_out'],
				'cost'       => round( (float) $row['cost'], 6 ),
			);
		}
		return $out;
	}

	/**
	 * A session's AI answers with model, tokens, cost and the stored citations and debug view.
	 *
	 * @param string $session_id Session.
	 * @return array[] text, created, provider, model, tokens_in, tokens_out, cost, citations, debug.
	 */
	public static function ai_replies( $session_id ) {
		$list = array();
		foreach ( self::messages( $session_id ) as $row ) {
			if ( 'bot' !== $row['role'] || '' === (string) $row['model'] ) {
				continue;
			}
			$payload = is_array( $row['payload'] ) ? $row['payload'] : array();
			$list[]  = array(
				'text'       => (string) $row['text'],
				'created'    => (string) $row['created'],
				'provider'   => (string) $row['provider'],
				'model'      => (string) $row['model'],
				'tokens_in'  => (int) $row['tokens_in'],
				'tokens_out' => (int) $row['tokens_out'],
				'cost'       => (float) $row['cost'],
				'citations'  => isset( $payload['citations'] ) && is_array( $payload['citations'] ) ? $payload['citations'] : array(),
				'debug'      => isset( $payload['debug'] ) && is_array( $payload['debug'] ) ? $payload['debug'] : array(),
			);
		}
		return $list;
	}

	/**
	 * Messages of a session, oldest first.
	 *
	 * @param string $session_id Session.
	 * @param int    $limit      Most rows.
	 * @return array[] id, role, channel, source, text, payload (array), model, provider, tokens_in, tokens_out, cost, created.
	 */
	public static function messages( $session_id, $limit = self::MAX_MESSAGES ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, role, channel, source, text, payload, model, provider, tokens_in, tokens_out, cost, created FROM %i WHERE session_id = %s ORDER BY id ASC LIMIT %d', Schema::messages_table(), self::clean_id( $session_id ), (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$row['id']      = (int) $row['id'];
			$row['payload'] = null !== $row['payload'] ? json_decode( $row['payload'], true ) : null;
			foreach ( array( 'tokens_in', 'tokens_out' ) as $int_key ) {
				$row[ $int_key ] = isset( $row[ $int_key ] ) ? (int) $row[ $int_key ] : 0;
			}
			$row['cost'] = isset( $row['cost'] ) ? (float) $row['cost'] : 0.0;
			$list[]      = $row;
		}
		return $list;
	}

	/**
	 * Number of messages in a session.
	 *
	 * @param string $session_id Session.
	 * @return int
	 */
	public static function count( $session_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE session_id = %s', Schema::messages_table(), self::clean_id( $session_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Write the legacy transcript rows for a session from its stored messages.
	 *
	 * @param string $session_id Session.
	 * @param array  $visitor    name, email, phone, source_url, user_agent, rate_key (limit key instead of the IP).
	 * @return int wpbot_user row id, 0 when nothing was saved.
	 */
	public static function save_legacy( $session_id, array $visitor = array() ) {
		global $wpdb;
		$session_id = self::clean_id( $session_id );
		if ( '' === $session_id || ! self::enabled() ) {
			return 0;
		}
		$messages    = self::messages( $session_id );
		$interaction = count(
			array_filter(
				$messages,
				static function ( $m ) {
					return 'user' === $m['role'];
				}
			)
		);
		if ( 0 === $interaction ) {
			return 0;
			// Sessions start when the visitor writes (B4: page loads no longer create sessions).
		}

		$users   = $wpdb->prefix . 'wpbot_user';
		$convs   = $wpdb->prefix . 'wpbot_conversation';
		$wp_user = get_current_user_id();
		$name    = isset( $visitor['name'] ) ? substr( sanitize_text_field( (string) $visitor['name'] ), 0, 100 ) : '';
		$email   = isset( $visitor['email'] ) ? sanitize_email( (string) $visitor['email'] ) : '';
		$phone   = isset( $visitor['phone'] ) ? substr( sanitize_text_field( (string) $visitor['phone'] ), 0, 40 ) : '';
		$source  = isset( $visitor['source_url'] ) ? esc_url_raw( (string) $visitor['source_url'] ) : '';
		$agent   = isset( $visitor['user_agent'] ) ? substr( sanitize_text_field( (string) $visitor['user_agent'] ), 0, 500 ) : '';
		if ( '' === $source ) {
			foreach ( $messages as $message ) {
				if ( ! empty( $message['payload']['page_url'] ) ) {
					$source = esc_url_raw( (string) $message['payload']['page_url'] );
					break;
				}
			}
		}
		$html = self::render( $messages, $source );
		if ( $wp_user && ( '' === $name || '' === $email ) ) {
			$user  = wp_get_current_user();
			$name  = '' !== $name ? $name : $user->display_name;
			$email = '' !== $email ? $email : $user->user_email;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id, interaction FROM %i WHERE session_id = %s', $users, $session_id ) );
		if ( ! $existing ) {
			if ( ! Limiter::hit( 'session', isset( $visitor['rate_key'] ) ? (string) $visitor['rate_key'] : null ) ) {
				return 0;
			}
			$wpdb->insert(
				$users,
				array(
					'date'        => current_time( 'mysql' ),
					'name'        => $name,
					'email'       => $email,
					'phone'       => $phone,
					'session_id'  => $session_id,
					'interaction' => $interaction,
					'user_id'     => $wp_user,
				)
			);
			$user_id = (int) $wpdb->insert_id;
			if ( ! $user_id ) {
				return 0;
			}
			$wpdb->insert(
				$convs,
				array(
					'user_id'          => $user_id,
					'conversation'     => $html,
					'interaction'      => $interaction,
					'environment_info' => $agent,
				)
			);
			self::after_save( $session_id, $user_id, true );
		} else {
			$user_id = (int) $existing->id;
			$update  = array(
				'date'        => current_time( 'mysql' ),
				'interaction' => $interaction,
			);
			foreach ( array(
				'name'  => $name,
				'email' => $email,
				'phone' => $phone,
			) as $field => $value ) {
				if ( '' !== $value ) {
					$update[ $field ] = $value;
					// Never blank out what an earlier save stored.
				}
			}
			$wpdb->update( $users, $update, array( 'id' => $user_id ) );
			$wpdb->update(
				$convs,
				array(
					'conversation' => $html,
					'interaction'  => $interaction,
				),
				array( 'user_id' => $user_id )
			);
			if ( $interaction > (int) $existing->interaction && class_exists( 'QCLD_WPCS_Session_Store' ) && method_exists( 'QCLD_WPCS_Session_Store', 'mark_unread' ) ) {
				\QCLD_WPCS_Session_Store::mark_unread( $user_id );
			}
			self::after_save( $session_id, $user_id, false );
		}//end if
		// phpcs:enable
		return $user_id;
	}

	/**
	 * Notifications and hooks after a save (the legacy add-on's behaviour).
	 *
	 * @param string $session_id Session.
	 * @param int    $user_id    wpbot_user id.
	 * @param bool   $is_new     First save.
	 * @return void
	 */
	private static function after_save( $session_id, $user_id, $is_new ) {
		if ( $is_new && 'checked' === get_option( 'session_email_notification_update' ) && function_exists( 'wpbot_schedule_delayed_session_email_notification' ) ) {
			wpbot_schedule_delayed_session_email_notification( $session_id );
		}
		$args = array( $session_id );
		if ( wp_next_scheduled( 'wpbot_automator_delayed_trigger', $args ) ) {
			wp_clear_scheduled_hook( 'wpbot_automator_delayed_trigger', $args );
		}
		wp_schedule_single_event( time() + 3 * MINUTE_IN_SECONDS, 'wpbot_automator_delayed_trigger', $args );

		/**
		 * A chat session was saved.
		 *
		 * @param string $session_id Session.
		 * @param int    $user_id    wpbot_user row id.
		 * @param bool   $is_new     First save.
		 */
		do_action( 'qcld_wpbot_chat/session_saved', $session_id, $user_id, $is_new );
	}

	/**
	 * Legacy transcript HTML (entity-encoded, as qc_wpbot_input_validation() stored it).
	 *
	 * @param array[] $messages   Messages.
	 * @param string  $source_url Page the chat started on.
	 * @return string
	 */
	public static function render( array $messages, $source_url = '' ) {
		$html = '';
		if ( '' !== $source_url ) {
			$html .= '<ul><li class="session_start_url"><span>Source URL: </span><a href="' . esc_url( $source_url ) . '">' . esc_html( $source_url ) . '</a></li></ul>';
		}
		$html .= '<ul id="wp-chatbot-messages-container" class="wp-chatbot-messages-container">';
		foreach ( $messages as $message ) {
			$class = 'user' === $message['role'] ? 'wp-chat-user-msg' : 'wp-chatbot-msg';
			$time  = '' !== (string) $message['created'] ? mysql2date( get_option( 'time_format', 'g:i a' ), $message['created'] ) : '';
			$html .= '<li class="' . $class . '" data-source="' . esc_attr( (string) $message['source'] ) . '"><div class="wp-chatbot-paragraph">' . self::message_html( (string) $message['text'] ) . '</div>';
			if ( '' !== $time ) {
				$html .= '<span class="wp-chatbot-timestamp">' . esc_html( $time ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		return htmlspecialchars( $html, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Message text as safe HTML (kses with the post tags; line breaks kept).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function message_html( $text ) {
		return nl2br( wp_kses_post( $text ) );
	}

	/**
	 * Read a transcript the legacy widget posted (HTML) into messages: role and text.
	 *
	 * @param string $html Transcript HTML (raw or entity-encoded).
	 * @return array[] role, text.
	 */
	public static function parse_legacy( $html ) {
		$html = (string) $html;
		if ( false === strpos( $html, '<li' ) ) {
			$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );
		}
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return array();
		}
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$list = array();
		foreach ( $doc->getElementsByTagName( 'li' ) as $li ) {
			$class = ' ' . $li->getAttribute( 'class' ) . ' ';
			if ( false !== strpos( $class, ' wp-chat-user-msg ' ) || false !== strpos( $class, ' woo-chat-user-msg ' ) ) {
				$role = 'user';
			} elseif ( false !== strpos( $class, ' wp-chatbot-msg ' ) || false !== strpos( $class, ' woo-chatbot-msg ' ) ) {
				$role = 'bot';
			} else {
				continue;
			}
			$text = '';
			foreach ( $li->getElementsByTagName( 'div' ) as $div ) {
				if ( false !== strpos( ' ' . $div->getAttribute( 'class' ) . ' ', ' wp-chatbot-paragraph ' ) ) {
					$text = $div->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					break;
				}
			}
			if ( '' === $text ) {
				$text = $li->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
			$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
			if ( '' !== $text ) {
				$list[] = array(
					'role' => $role,
					'text' => $text,
				);
			}
		}//end foreach
		return $list;
	}

	/**
	 * Store the messages of a posted legacy transcript that are not stored yet.
	 *
	 * @param string $session_id Session.
	 * @param string $html       Transcript.
	 * @return int Messages added.
	 */
	public static function sync_legacy( $session_id, $html ) {
		$parsed = self::parse_legacy( $html );
		$stored = self::count( $session_id );
		$added  = 0;
		foreach ( array_slice( $parsed, $stored ) as $message ) {
			$extra = array( 'source' => 'legacy' );
			// A reply the engine gave through the legacy widget (Legacy\Ai_Bridge): keep its model, cost and debug view.
			$meta = 'bot' === $message['role'] ? \QuantumCloud\Chatbot\Legacy\Ai_Bridge::claim( $session_id, $message['text'] ) : null;
			if ( $meta ) {
				$extra = array(
					'source'     => 'ai',
					'provider'   => $meta['provider'],
					'model'      => $meta['model'],
					'tokens_in'  => $meta['tokens_in'],
					'tokens_out' => $meta['tokens_out'],
					'cost'       => $meta['cost'],
					'payload'    => $meta['payload'],
				);
			}
			if ( self::add( $session_id, $message['role'], $message['text'], $extra ) ) {
				++$added;
			}
		}
		return $added;
	}

	/**
	 * Session id as stored: letters, digits, _ - . : and at most 128 characters.
	 *
	 * @param string $session_id Raw id.
	 * @return string
	 */
	public static function clean_id( $session_id ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $session_id ), 0, 128 );
	}

	/**
	 * Delete the messages of sessions (privacy eraser, session deletion).
	 *
	 * @param string[] $session_ids Sessions.
	 * @return int Rows deleted.
	 */
	public static function delete( array $session_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( array( __CLASS__, 'clean_id' ), $session_ids ), 'strlen' ) );
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE session_id IN ({$in})", array_merge( array( Schema::messages_table() ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}
}
