<?php
/**
 * Actions engine (chatbot features CB1b): AI Actions and function calling as one engine.
 *
 * An Action is a tool the AI can call once it has collected what the Action needs:
 * { id, name, description, tool, fields[], steps[], availability }. The built-in Actions are the
 * site's AI Actions forms (option wpbot_ai_forms, still edited on the AI Actions screen): the
 * form's instructions tell the AI what to ask, and the AI calls the Action's tool with the answers.
 * Other modules add Actions through `qcld_wpbot_chat/actions`.
 *
 * Steps run on the server, in order, after the answers are validated and the visitor's Action
 * rate limit allows it. Step types come from `qcld_wpbot_chat/action_types`:
 * - Free: save_entry (a wpbot_form_entry post and the form's email; fires
 *   qcld_wpbot_chat/ai_form_saved), webhook (POST JSON to the Action's https URL),
 *   marketing_list (fires qcld_wpbot_chat/action_marketing_list for AI Marketing).
 * - Pro adds its own (REST connector, WordPress function, store lookup, …).
 *
 * Models without native tool calling keep the delimiter path (Leads\Ai_Forms).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Actions;

use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Leads\Ai_Forms;
use QuantumCloud\Chatbot\RateLimit\Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Actions registry and runner.
 */
final class Actions {

	/**
	 * Tool name prefix.
	 */
	const PREFIX = 'action_';

	/**
	 * Every Action available now.
	 *
	 * @return array[]
	 */
	public static function all() {
		$actions = array();
		foreach ( Ai_Forms::forms() as $form ) {
			$title = sanitize_text_field( (string) $form['title'] );
			$id    = sanitize_key( str_replace( ' ', '_', strtolower( $title ) ) );
			if ( '' === $id ) {
				$id = substr( md5( $title ), 0, 8 );
			}
			$steps = array( array( 'type' => 'save_entry' ) );
			if ( ! empty( $form['webhook_url'] ) ) {
				$steps[] = array(
					'type' => 'webhook',
					'url'  => (string) $form['webhook_url'],
				);
			}
			if ( ! empty( $form['marketing_list'] ) ) {
				$steps[] = array(
					'type' => 'marketing_list',
					'list' => (string) $form['marketing_list'],
				);
			}
			$actions[ $id ] = array(
				'id'           => $id,
				'name'         => $title,
				'description'  => isset( $form['prompt'] ) ? sanitize_textarea_field( (string) $form['prompt'] ) : '',
				'tool'         => self::PREFIX . $id,
				'fields'       => array(),
				'steps'        => $steps,
				'availability' => array(),
				'form'         => $form,
			);
		}//end foreach
		/**
		 * Actions the AI may run: id => { id, name, description, tool, fields[], steps[], availability;
	 * optional instructions (system text instead of the form wording), limit (rate-limit bucket,
	 * default "action"), limit_users (count logged-in visitors too), no_input (takes no fields) }.
	 * Actions without a `form` are not tied to the AI Forms switch.
		 *
		 * @param array[] $actions Actions.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/actions', $actions );
	}

	/**
	 * Actions available for a message (availability rules: logged_in, pages).
	 *
	 * @param Message $message Message.
	 * @return array[]
	 */
	public static function available( Message $message ) {
		$list = array();
		foreach ( self::all() as $id => $action ) {
			$rules = isset( $action['availability'] ) ? (array) $action['availability'] : array();
			if ( ! empty( $rules['logged_in'] ) && ! is_user_logged_in() ) {
				continue;
			}
			if ( ! empty( $rules['pages'] ) && '' !== $message->page_url ) {
				$match = false;
				foreach ( (array) $rules['pages'] as $fragment ) {
					if ( '' !== (string) $fragment && false !== strpos( $message->page_url, (string) $fragment ) ) {
						$match = true;
					}
				}
				if ( ! $match ) {
					continue;
				}
			}
			$list[ $id ] = $action;
		}
		return $list;
	}

	/**
	 * Tool definitions for WPBot Core's AI client.
	 *
	 * @param array[] $actions Actions.
	 * @return array[]
	 */
	public static function tools( array $actions ) {
		$tools = array();
		foreach ( $actions as $action ) {
			$properties = array();
			$required   = array();
			foreach ( isset( $action['fields'] ) ? (array) $action['fields'] : array() as $field ) {
				if ( empty( $field['name'] ) ) {
					continue;
				}
				$properties[ (string) $field['name'] ] = array(
					'type'        => 'string',
					'description' => isset( $field['label'] ) ? (string) $field['label'] : (string) $field['name'],
				);
				if ( ! empty( $field['required'] ) ) {
					$required[] = (string) $field['name'];
				}
			}
			if ( ! $properties ) {
				$properties = array(
					'data' => array(
						'type'        => 'object',
						'description' => 'Every answer the user gave for this action: question => answer.',
					),
				);
				$required   = array( 'data' );
			}
			$tools[] = array(
				'name'        => (string) $action['tool'],
				'description' => substr( 'Run "' . $action['name'] . '" after collecting every answer it needs, one question at a time. ' . (string) $action['description'], 0, 1000 ),
				'parameters'  => array(
					'type'       => 'object',
					'properties' => $properties,
					'required'   => $required,
				),
			);
		}//end foreach
		return $tools;
	}

	/**
	 * System text for the tool path: how to use the Action tools.
	 *
	 * @param array[] $actions Actions.
	 * @param string  $active  The name of the Action being filled in (the widget's active_ai_action), if any.
	 * @return string
	 */
	public static function instructions( array $actions, $active = '' ) {
		if ( ! $actions ) {
			return '';
		}
		$forms   = array();
		$extra   = array();
		$current = '';
		foreach ( $actions as $action ) {
			if ( ! empty( $action['instructions'] ) ) {
				$extra[] = (string) $action['instructions'];
			} else {
				$forms[] = (string) $action['name'];
			}
			if ( '' !== trim( (string) $active ) && 0 === strcasecmp( trim( (string) $action['name'] ), trim( (string) $active ) ) ) {
				$current = (string) $action['name'];
			}
		}
		$text = $forms ? 'You can run these actions for the user: ' . implode( ', ', $forms ) . '. When the user wants one, follow its instructions and ask exactly ONE question at a time. Never ask again for something already answered. When you have every answer, call the action\'s tool with them, then tell the user it is done. Do not call a tool before the answers are collected.' : '';
		if ( '' !== $current ) {
			// Chatbot features round 6: keep to the action in progress, so another action's questions never show up mid-form.
			$text .= ' The user is filling in "' . sanitize_text_field( $current ) . '" now: their message answers your last question. Ask only this action\'s questions, unless the user clearly asks for something else.';
		}
		return trim( $text . ( $extra ? "\n" . implode( "\n", $extra ) : '' ) );
	}

	/**
	 * Run an Action's steps for a tool call. Returns the tool result the AI reads.
	 *
	 * @param string  $tool      Tool name.
	 * @param array   $arguments Tool arguments.
	 * @param Message $message   Message.
	 * @return array { ok (bool), result (string for the AI), action (id), entry (post id), title }
	 */
	public static function run( $tool, array $arguments, Message $message ) {
		$action = null;
		foreach ( self::available( $message ) as $candidate ) {
			if ( $candidate['tool'] === $tool ) {
				$action = $candidate;
			}
		}
		if ( null === $action ) {
			return self::outcome( false, 'This action is not available.' );
		}
		$data = self::data( $action, $arguments );
		if ( ! $data && empty( $action['no_input'] ) ) {
			return self::outcome( false, 'No answers were given. Ask the user for them first.', $action );
		}
		$who = 'web' === $message->channel ? null : $message->channel . ':' . $message->session_id;
		if ( null === $who && ! empty( $action['limit_users'] ) && is_user_logged_in() ) {
			// Lookups that could be guessed (order numbers) also count logged-in visitors.
			$who = 'user:' . get_current_user_id();
		}
		$bucket = isset( $action['limit'] ) && '' !== (string) $action['limit'] ? (string) $action['limit'] : 'action';
		if ( ! Limiter::hit( $bucket, $who ) ) {
			return self::outcome( false, 'Too many submissions from this visitor. Ask them to try again later.', $action );
		}
		$types   = self::types();
		$entry   = 0;
		$ok      = true;
		$results = array();
		$cards   = array();
		foreach ( (array) $action['steps'] as $step ) {
			$type = isset( $step['type'] ) ? (string) $step['type'] : '';
			if ( ! isset( $types[ $type ] ) || ! is_callable( $types[ $type ] ) ) {
				continue;
			}
			$done = call_user_func( $types[ $type ], $action, $data, $message, $step );
			if ( 'save_entry' === $type ) {
				$entry = (int) $done;
				$ok    = $ok && $entry > 0;
			} elseif ( is_array( $done ) ) {
				// Lookup steps (store search, order status, REST GET) return what the AI should read.
				$ok = $ok && ! ( isset( $done['ok'] ) && false === $done['ok'] );
				if ( isset( $done['result'] ) && '' !== (string) $done['result'] ) {
					$results[] = (string) $done['result'];
				}
				if ( ! empty( $done['cards'] ) && is_array( $done['cards'] ) ) {
					$cards = array_merge( $cards, $done['cards'] );
				}
			} else {
				$ok = $ok && false !== $done;
			}
		}//end foreach
		if ( $results ) {
			$text = implode( "\n\n", $results );
		} else {
			$text = $ok ? 'Done: the details were sent. Tell the user, in one short sentence.' : 'Could not complete it. Apologize and suggest contacting the business directly.';
		}
		$outcome          = self::outcome( $ok, $text, $action );
		$outcome['entry'] = $entry;
		$outcome['cards'] = array_slice( $cards, 0, 6 );
		/**
		 * An Action ran.
		 *
		 * @param array   $outcome { ok, result, action, entry, title }.
		 * @param array   $data    Answers.
		 * @param Message $message Message.
		 */
		do_action( 'qcld_wpbot_chat/action_ran', $outcome, $data, $message );
		return $outcome;
	}

	/**
	 * Step types: type => callable( $action, $data, $message, $step ).
	 *
	 * @return array<string,callable>
	 */
	public static function types() {
		$types = array(
			'save_entry'     => array( __CLASS__, 'step_save_entry' ),
			'webhook'        => array( __CLASS__, 'step_webhook' ),
			'marketing_list' => array( __CLASS__, 'step_marketing_list' ),
		);
		/**
		 * Action step types (Pro adds REST connector, WordPress function, store lookup, …).
		 *
		 * @param array<string,callable> $types Types.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/action_types', $types );
	}

	/**
	 * Step: save a wpbot_form_entry post (and send the form's email).
	 *
	 * @param array   $action  Action.
	 * @param array   $data    Answers.
	 * @param Message $message Message.
	 * @return int Post id (0 when not saved).
	 */
	public static function step_save_entry( array $action, array $data, Message $message ) {
		return Ai_Forms::save(
			array(
				'form_title' => (string) $action['name'],
				'data'       => $data,
			),
			$message->session_id
		);
	}

	/**
	 * Step: POST the answers as JSON to the Action's URL (https, public hosts only).
	 *
	 * @param array   $action  Action.
	 * @param array   $data    Answers.
	 * @param Message $message Message.
	 * @param array   $step    Step (url).
	 * @return bool
	 */
	public static function step_webhook( array $action, array $data, Message $message, array $step ) {
		$url = isset( $step['url'] ) ? esc_url_raw( (string) $step['url'], array( 'https' ) ) : '';
		if ( '' === $url || 0 !== stripos( $url, 'https://' ) ) {
			return false;
		}
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'action'  => (string) $action['name'],
						'data'    => $data,
						'site'    => home_url( '/' ),
						'page'    => $message->page_url,
						'sent_at' => gmdate( 'c' ),
					)
				),
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Step: hand the answers to AI Marketing (or any listener) for a list or tag.
	 *
	 * @param array   $action  Action.
	 * @param array   $data    Answers.
	 * @param Message $message Message.
	 * @param array   $step    Step (list).
	 * @return bool
	 */
	public static function step_marketing_list( array $action, array $data, Message $message, array $step ) {
		$email = '';
		foreach ( $data as $value ) {
			if ( preg_match( '/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', (string) $value, $match ) && is_email( $match[1] ) ) {
				$email = sanitize_email( $match[1] );
				break;
			}
		}
		if ( '' === $email ) {
			return false;
		}
		/**
		 * Add a contact from an Action to a marketing list or tag.
		 *
		 * @param string $email Email.
		 * @param string $list  List or tag.
		 * @param array  $data  Answers.
		 */
		do_action( 'qcld_wpbot_chat/action_marketing_list', $email, isset( $step['list'] ) ? (string) $step['list'] : '', $data );
		return true;
	}

	/**
	 * Clean answers: declared fields, or the free-form `data` object (scalar values, at most 30).
	 *
	 * @param array $action    Action.
	 * @param array $arguments Tool arguments.
	 * @return array<string,string>
	 */
	public static function data( array $action, array $arguments ) {
		$raw = isset( $arguments['data'] ) && is_array( $arguments['data'] ) ? $arguments['data'] : $arguments;
		if ( ! empty( $action['fields'] ) ) {
			$raw = array_intersect_key( $raw, array_flip( wp_list_pluck( (array) $action['fields'], 'name' ) ) );
		} elseif ( ! empty( $action['no_input'] ) ) {
			// An Action that takes nothing gets nothing, whatever the AI sends.
			return array();
		}
		return array_filter(
			Ai_Forms::clean_fields( (array) $raw ),
			static function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * A run's outcome.
	 *
	 * @param bool   $ok     Success.
	 * @param string $result Text for the AI.
	 * @param array  $action Action.
	 * @return array
	 */
	private static function outcome( $ok, $result, array $action = array() ) {
		return array(
			'ok'     => (bool) $ok,
			'result' => (string) $result,
			'action' => isset( $action['id'] ) ? (string) $action['id'] : '',
			'title'  => isset( $action['name'] ) ? (string) $action['name'] : '',
			'entry'  => 0,
		);
	}
}
