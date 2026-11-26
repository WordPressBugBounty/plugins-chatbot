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
				'reply'        => isset( $form['reply'] ) ? sanitize_textarea_field( (string) $form['reply'] ) : '',
				'form'         => $form,
			);
		}//end foreach
		/**
		 * Actions the AI may run: id => { id, name, description, tool, fields[], steps[], availability;
	 * optional instructions (system text instead of the form wording), limit (rate-limit bucket,
	 * default "action"), limit_users (count logged-in visitors too), no_input (takes no fields),
	 * reply (how the AI writes its answer once the Action has run, for example a recommendation
	 * built from the answers; empty: one short sentence) }.
	 * Actions without a `form` are not tied to the AI Forms switch.
	 * Fields may be typed (Store plan 7b, see Fields): type choice (options, multiple) or range
	 * (min, max, step or bands), and a condition { field, values }.
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
			$typed      = Fields::typed( $action );
			foreach ( isset( $action['fields'] ) ? (array) $action['fields'] : array() as $field ) {
				if ( empty( $field['name'] ) ) {
					continue;
				}
				if ( $typed ) {
					// Typed fields (Store plan 7b): enum, minimum and maximum; the runner asks for what is missing.
					$properties[ (string) $field['name'] ] = Fields::property( $field );
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
			$lead    = $typed ? 'Run "' . $action['name'] . '". Call it as soon as the user\'s messages give any of its answers (or none yet): it replies with the next question to ask, and shows the answer options as buttons, until it can run. ' : 'Run "' . $action['name'] . '" after collecting every answer it needs, one question at a time' . ( ! empty( $action['form'] ) ? ' (its full instructions are in the system message). ' : '. ' );
			$tools[] = array(
				'name'        => (string) $action['tool'],
				'description' => self::cut( $lead . (string) $action['description'], 1000 ),
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
	 * @param string  $said    The user's message (when it is the Action's name, the Action just started).
	 * @return string
	 */
	public static function instructions( array $actions, $active = '', $said = '' ) {
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
		$text  = $forms ? 'You can run these actions for the user: ' . implode( ', ', $forms ) . '. When the user wants one, follow its instructions and ask exactly ONE question at a time. Never ask again for something already answered: an answer counts even when it does not match the example answers exactly ("5" answers "How many sites? (1, 2 to 5, 6 to 10)"), and the same question is never asked twice in a row. When you have every answer, call the action\'s tool with them, then answer the user as the tool\'s result says. Do not call a tool before the answers are collected.' : '';
		$steps = array();
		foreach ( $actions as $action ) {
			if ( empty( $action['form'] ) || ! is_array( $action['form'] ) || ( '' !== $current && $current !== $action['name'] ) ) {
				continue;
			}
			// The full instructions go here: a tool description is cut at 1,000 characters, which left
			// the AI only the first questions of a long form (QA, 17 November 2026).
			$prompt = isset( $action['form']['prompt'] ) ? trim( sanitize_textarea_field( (string) $action['form']['prompt'] ) ) : '';
			$rule   = Ai_Forms::steps_rule( $action['form'] );
			if ( '' !== $prompt ) {
				$steps[] = 'Instructions for "' . sanitize_text_field( (string) $action['name'] ) . "\":\n" . $prompt . ( '' !== $rule ? "\n" . $rule . ' Call its tool only then, with each answer as its own field.' : '' );
			}
		}
		if ( '' !== $current ) {
			// Chatbot features round 6: keep to the action in progress, so another action's questions never show up mid-form.
			if ( 0 === strcasecmp( trim( (string) $said ), trim( $current ) ) ) {
				// The start menu button sends the Action's name: nothing is answered yet (owner's staging,
				// 25 November 2026: "their message answers your last question" made the model repeat).
				$intro = ' The user just started "' . sanitize_text_field( $current ) . '": ask its first question now, once.';
			} else {
				$intro = ' The user is filling in "' . sanitize_text_field( $current ) . '" now: their message answers your last question. Ask only this action\'s questions, unless the user clearly asks for something else.';
			}
			$text .= $intro;
		}
		return trim( $text . ( $steps ? "\n\n" . implode( "\n\n", $steps ) : '' ) . ( $extra ? "\n" . implode( "\n", $extra ) : '' ) );
	}

	/**
	 * Run an Action's steps for a tool call. Returns the tool result the AI reads.
	 *
	 * @param string  $tool      Tool name.
	 * @param array   $arguments Tool arguments.
	 * @param Message $message   Message.
	 * @return array { ok (bool), result (string for the AI), action (id), entry (post id), title,
	 *               wait (true: called before the visitor answered; the turn ends with a question) }
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
		if ( Fields::typed( $action ) ) {
			// Typed Actions (Store plan 7b): ask for the next shown, required answer, with its buttons.
			$next = Fields::next( $action, $data );
			if ( null !== $next ) {
				return self::ask( $action, $next );
			}
		}
		if ( ! $data && empty( $action['no_input'] ) ) {
			$outcome         = self::outcome( false, 'No answers were given. Ask the user for them first.', $action );
			$outcome['wait'] = true;
			return $outcome;
		}
		$steps = ! empty( $action['form'] ) && is_array( $action['form'] ) ? Ai_Forms::step_count( $action['form'] ) : 0;
		if ( $steps > 1 && ( count( $data ) < $steps || self::answers( $action, $message ) < $steps ) ) {
			// Called before the form is done (QA, 17 November 2026: sent after name, email and phone
			// of a 10-question assessment). Nothing is saved; the AI keeps asking.
			// Models also invent answers to get here sooner (owner's staging, 25 November 2026: gpt-6-luna
			// called the tool three times in one turn with made-up answers, so questions were skipped,
			// asked out of order or twice). `wait` ends the turn: the visitor answers first.
			$outcome         = self::outcome( false, sprintf( 'Not finished: the user has not answered all %1$d questions yet. Never guess or invent an answer. Do not tell the user anything failed. Ask ONE question now: the first question from the instructions that the user has not answered in their own messages (an answer that does not match the example answers exactly still counts). Reply with only that question, as the user should see it: no notes about these instructions. Call this tool again only after the user has answered question %1$d, with every answer as its own field.', $steps ), $action );
			$outcome['wait'] = true;
			return $outcome;
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
		$reply = $ok ? self::reply_rule( $action ) : '';
		if ( $results ) {
			$text = implode( "\n\n", $results ) . ( '' !== $reply ? "\n\n" . $reply : '' );
		} elseif ( '' !== $reply ) {
			$text = 'Done: the answers were saved. ' . $reply;
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
	 * How many messages the visitor sent since the Action started: the messages after the last one
	 * that is the Action's name (its start menu button sends it), with this one; without such a
	 * message, every visitor message in the history. A model that fills in the answers itself still
	 * has to wait for the visitor (owner on wpbot.pro, 25 November 2026: the entry was saved and
	 * emailed after two of six questions; with one message less allowed, it skipped the last question).
	 * A numbered form needs one visitor message per question.
	 *
	 * @param array   $action  Action (name).
	 * @param Message $message Message (history, text).
	 * @return int
	 */
	public static function answers( array $action, Message $message ) {
		$name  = strtolower( trim( (string) $action['name'] ) );
		$count = 0;
		foreach ( (array) $message->history as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['role'] ) || 'user' !== $item['role'] ) {
				continue;
			}
			$text  = strtolower( trim( isset( $item['content'] ) ? (string) $item['content'] : '' ) );
			$count = $text === $name ? 0 : $count + 1;
		}
		if ( strtolower( trim( (string) $message->text ) ) !== $name ) {
			++$count;
		}
		return $count;
	}

	/**
	 * What the AI is told about its answer after the Action ran ('' when the Action has no
	 * reply instructions). Owner, 25 November 2026: an Action can end with a full answer built
	 * from the visitor's answers (for example "which plan suits you"), not only "it was sent".
	 *
	 * @param array $action Action (reply).
	 * @return string
	 */
	public static function reply_rule( array $action ) {
		$reply = isset( $action['reply'] ) ? trim( sanitize_textarea_field( (string) $action['reply'] ) ) : '';
		if ( '' === $reply ) {
			return '';
		}
		return "Now write your answer to the user. Base it on every answer they gave in this conversation, and follow the site owner's instructions for this answer:\n" . self::cut( $reply, Ai_Forms::REPLY_MAX ) . "\nDo not ask this action's questions again, and do not call this tool again for the same answers.";
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
		if ( ! empty( $action['fields'] ) && Fields::typed( $action ) ) {
			// Choice and range answers are checked against their options; conditional fields drop out.
			return Fields::clean( $action, array_intersect_key( $raw, array_flip( wp_list_pluck( (array) $action['fields'], 'name' ) ) ) );
		}
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
	 * The outcome that asks for a typed Action's next answer: what to ask, and the answer buttons
	 * (`choices`: field, label, multiple, options[ value, label ]) the widget shows under the reply.
	 *
	 * @param array $action Action.
	 * @param array $field  Normalized field (Fields::next()).
	 * @return array
	 */
	public static function ask( array $action, array $field ) {
		$options = Fields::choices( $field );
		$text    = 'Not finished. Ask the user this ONE question now, in your own words: "' . $field['label'] . '".';
		if ( $options ) {
			$text .= ' Buttons with the answers (' . implode( ', ', wp_list_pluck( $options, 'label' ) ) . ') are shown under your message, so do not list them all' . ( $field['multiple'] ? '; the user may pick more than one' : '' ) . '.';
		}
		$text   .= ' Do not tell the user anything failed. When they answer, call this tool again with every answer so far.';
		$outcome = self::outcome( false, $text, $action );
		if ( $options ) {
			$outcome['choices'] = array(
				'field'    => (string) $field['name'],
				'label'    => (string) $field['label'],
				'multiple' => ! empty( $field['multiple'] ),
				'options'  => $options,
			);
		}
		return $outcome;
	}

	/**
	 * Cut text to at most $max bytes without splitting a letter.
	 *
	 * @param string $text Text.
	 * @param int    $max  Most bytes.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return \QuantumCloud\Chatbot\Text\Utf8::cut_bytes( $text, $max );
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
