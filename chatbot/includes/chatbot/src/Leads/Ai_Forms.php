<?php
/**
 * AI Actions forms: the AI collects answers one question at a time and ends
 * with a __AI_FORM_DATA__{...}__AI_FORM_DATA_END__ block, which is saved as a
 * "wpbot_form_entry" post and emailed (the legacy format, same admin screen).
 *
 * The block comes from the model, so it is untrusted: only forms the owner
 * defined are saved, fields and lengths are capped, and each session saves a
 * form at most once per hour.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * AI Actions forms.
 */
final class Ai_Forms {

	/**
	 * Most fields saved from one form.
	 */
	const MAX_FIELDS = 30;

	/**
	 * Forms the owner defined (wpbot_ai_forms): title, prompt, email, email_addresses.
	 *
	 * @return array[]
	 */
	public static function forms() {
		$forms = get_option( 'wpbot_ai_forms', array() );
		$clean = array();
		foreach ( is_array( $forms ) ? $forms : array() as $form ) {
			if ( is_array( $form ) && ! empty( $form['title'] ) ) {
				$clean[] = $form;
			}
		}
		return $clean;
	}

	/**
	 * System prompt text for the forms (empty when there are none).
	 *
	 * While a form is being filled in, only that form's instructions are sent (chatbot features
	 * round 6): with every form listed, the AI could drift into another form's questions.
	 *
	 * @param bool|string $in_progress The title of the form being filled in (active_ai_action), or
	 *                                 true when one is but its title is unknown. Empty: none is.
	 * @return string
	 */
	public static function instructions( $in_progress = false ) {
		$forms  = self::forms();
		$active = is_string( $in_progress ) && '' !== trim( $in_progress ) ? self::find( $in_progress ) : null;
		if ( null !== $active ) {
			$forms = array( $active );
		}
		if ( ! $forms ) {
			return '';
		}
		$text = "You must handle the following interactive forms when the user asks for them:\n";
		foreach ( $forms as $form ) {
			$text .= "\nForm Title: " . sanitize_text_field( (string) $form['title'] ) . "\nInstructions: " . sanitize_textarea_field( isset( $form['prompt'] ) ? (string) $form['prompt'] : '' ) . "\n";
		}
		$text .= "\nWhen a user triggers a form, ask exactly ONE question at a time and wait for the answer. "
			. 'Once all information is collected, output a final JSON block wrapped exactly in these delimiters, and nothing after it: '
			. '__AI_FORM_DATA__{ "form_title": "<Form Title>", "data": { "Question 1": "Answer 1" } }__AI_FORM_DATA_END__';
		if ( null !== $active ) {
			$text .= "\n\nThe form \"" . sanitize_text_field( (string) $active['title'] ) . "\" is being filled in now: the user's message answers your last question. Ask only this form's questions. Never repeat a question already answered; ask the next missing one, or output the final block when all are answered.";
		} elseif ( $in_progress ) {
			$text .= "\n\nA form is being filled in now: the user's message answers your last question. Never repeat a question already answered; ask the next missing one, or output the final block when all are answered.";
		}
		return $text;
	}

	/**
	 * Split a reply into the visible text and the form data, if any.
	 *
	 * @param string $text AI reply.
	 * @return array { text: string, form: array|null { form_title, data } }
	 */
	public static function extract( $text ) {
		$text = (string) $text;
		if ( false === strpos( $text, 'AI_FORM_DATA' ) ) {
			return array(
				'text' => $text,
				'form' => null,
			);
		}
		$pattern = '/(?:<[^>]+>)*\s*(?:__|\*\*|<strong>|<b>)?AI_FORM_DATA(?:__|\*\*|<\/strong>|<\/b>)?([\s\S]*?)(?:__|\*\*|<strong>|<b>)?AI_FORM_DATA_END(?:__|\*\*|<\/strong>|<\/b>)?\s*(?:<\/[^>]+>)*/i';
		if ( ! preg_match( $pattern, $text, $match ) ) {
			return array(
				'text' => $text,
				'form' => null,
			);
		}
		$visible = trim( str_replace( $match[0], '', $text ) );
		$form    = null;
		if ( preg_match( '/\{[\s\S]*\}/', $match[1], $json ) ) {
			$data = json_decode( trim( $json[0] ), true );
			if ( is_array( $data ) && isset( $data['form_title'], $data['data'] ) && is_array( $data['data'] ) ) {
				$form = array(
					'form_title' => sanitize_text_field( (string) $data['form_title'] ),
					'data'       => self::clean_fields( $data['data'] ),
				);
			}
		}
		return array(
			'text' => $visible,
			'form' => $form,
		);
	}

	/**
	 * Wrap a stream callback so the form data block never reaches the visitor.
	 * Holds back the last few characters in case a marker starts there.
	 *
	 * @param callable $on_text Callback.
	 * @return callable[] [ wrapped callback, flush callback (call once at the end) ].
	 */
	public static function stream_guard( $on_text ) {
		$state = array(
			'buffer' => '',
			'hidden' => false,
		);
		$hold  = 20;
		$send  = static function ( $piece ) use ( $on_text, &$state, $hold ) {
			if ( $state['hidden'] ) {
				return;
			}
			$state['buffer'] .= (string) $piece;
			$at               = strpos( $state['buffer'], 'AI_FORM_DATA' );
			if ( false !== $at ) {
				$visible = rtrim( preg_replace( '/(?:__|\*\*|<strong>|<b>)\s*$/', '', substr( $state['buffer'], 0, $at ) ) );
				if ( '' !== $visible ) {
					call_user_func( $on_text, $visible );
				}
				$state['hidden'] = true;
				$state['buffer'] = '';
				return;
			}
			if ( strlen( $state['buffer'] ) > $hold ) {
				call_user_func( $on_text, substr( $state['buffer'], 0, -$hold ) );
				$state['buffer'] = substr( $state['buffer'], -$hold );
			}
		};
		$flush = static function () use ( $on_text, &$state ) {
			if ( ! $state['hidden'] && '' !== $state['buffer'] ) {
				call_user_func( $on_text, $state['buffer'] );
			}
			$state['buffer'] = '';
		};
		return array( $send, $flush );
	}

	/**
	 * Field names and values as short plain text.
	 *
	 * @param array $data Fields.
	 * @return array<string,string>
	 */
	public static function clean_fields( array $data ) {
		$clean = array();
		foreach ( $data as $key => $value ) {
			if ( count( $clean ) >= self::MAX_FIELDS ) {
				break;
			}
			$key   = substr( sanitize_text_field( (string) $key ), 0, 100 );
			$value = is_scalar( $value ) ? substr( sanitize_text_field( (string) $value ), 0, 1000 ) : '';
			if ( '' !== $key ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * The owner's form with this title, or null.
	 *
	 * @param string $title Title.
	 * @return array|null
	 */
	public static function find( $title ) {
		foreach ( self::forms() as $form ) {
			if ( 0 === strcasecmp( trim( (string) $form['title'] ), trim( (string) $title ) ) ) {
				return $form;
			}
		}
		return null;
	}

	/**
	 * Save an entry and email it (legacy format).
	 *
	 * @param array  $form       { form_title, data }.
	 * @param string $session_id Session (one save per form per hour).
	 * @return int Entry post id, 0 when not saved.
	 */
	public static function save( array $form, $session_id = '' ) {
		$definition = self::find( isset( $form['form_title'] ) ? $form['form_title'] : '' );
		if ( null === $definition || empty( $form['data'] ) ) {
			return 0;
		}
		$title = sanitize_text_field( (string) $definition['title'] );
		$once  = 'qcld_wpbot_chat_form_' . md5( $title . '|' . ( '' !== $session_id ? $session_id : \QuantumCloud\Chatbot\RateLimit\Limiter::ip() ) );
		if ( get_transient( $once ) ) {
			return 0;
		}
		set_transient( $once, 1, HOUR_IN_SECONDS );

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title . ' - ' . current_time( 'mysql' ),
				'post_type'   => 'wpbot_form_entry',
				'post_status' => 'publish',
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}
		$rows     = '';
		$reply_to = '';
		foreach ( self::clean_fields( $form['data'] ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
			$rows .= '<tr><td style="background:#f4f4f4;width:40%;"><strong>' . esc_html( ucwords( str_replace( array( '-', '_' ), ' ', $key ) ) ) . '</strong></td><td>' . esc_html( $value ) . '</td></tr>';
			if ( '' === $reply_to && preg_match( '/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $value, $email ) && is_email( $email[1] ) ) {
				$reply_to = sanitize_email( $email[1] );
			}
		}

		$send = ! isset( $definition['email'] ) || 1 === (int) $definition['email'];
		if ( $send ) {
			$list = isset( $definition['email_addresses'] ) ? trim( (string) $definition['email_addresses'] ) : '';
			if ( '' === $list ) {
				$list = (string) get_option( 'qlcd_wp_chatbot_admin_email', '' );
			}
			$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $list ) ) ), 'is_email' );
			if ( ! $to ) {
				$to = array( get_option( 'admin_email' ) );
			}
			$body    = '<h2>' . esc_html__( 'New AI Actions Submission', 'chatbot' ) . '</h2><p><strong>' . esc_html__( 'Action', 'chatbot' ) . ':</strong> ' . esc_html( $title ) . '</p><table border="1" cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:600px;font-family:sans-serif;">' . $rows . '</table>';
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( '' !== $reply_to ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
			wp_mail( $to, $title . ' - ' . __( 'New AI Actions Submission', 'chatbot' ), $body, $headers );
		}

		/**
		 * An AI Actions form was saved.
		 *
		 * @param int    $post_id Entry post id.
		 * @param string $title   Form title.
		 * @param array  $data    Fields.
		 */
		do_action( 'qcld_wpbot_chat/ai_form_saved', $post_id, $title, $form['data'] );
		return (int) $post_id;
	}
}
