<?php
/**
 * Legacy widget AI bridge (chatbot features CB1a): the legacy widget's AI AJAX actions are
 * answered by the module engine (knowledge base, Answer scope, AI forms, citations, cost
 * recorded once, debug view) instead of the hosts' per-provider handlers, in the wire format
 * the widget already reads. The widget's JavaScript does not change.
 *
 * Contract: botmaster docs/chatbot-features/legacy-widget-ai.md.
 * - JSON actions: a JSON string (text/html) { status: success|error, message: HTML }.
 * - Stream actions: `data: {"choices":[{"delta":{"content":...}}]}` events, then `data: [DONE]`.
 *
 * Requests the engine cannot serve yet go on to the old handler: an attached file or image,
 * OpenAI Assistants, Grok document collections, a provider other than the one that answers.
 * Switch: option qcld_wpbot_chat_ai_bridge ('0' = off) and filter qcld_wpbot_chat/ai_bridge.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Legacy;

use QuantumCloud\Chatbot\Ai\Provider_Settings;
use QuantumCloud\Chatbot\Conversation\Chat;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Pipeline;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Steps\Ai_Step;
use QuantumCloud\Chatbot\Conversation\Steps\Fallback_Step;
use QuantumCloud\Chatbot\Conversation\Steps\Knowledge_Step;
use QuantumCloud\Chatbot\Rest\Sse;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy AI bridge.
 */
final class Ai_Bridge {

	/**
	 * Option: '0' turns the bridge off (the old per-provider handlers answer again).
	 */
	const OPTION = 'qcld_wpbot_chat_ai_bridge';

	/**
	 * JSON actions => provider.
	 */
	const JSON_ACTIONS = array(
		'openai_response'      => 'openai',
		'qcld_openai_response' => 'openai',
		'gemini_response'      => 'gemini',
		'qcld_gemini_response' => 'gemini',
		'claude_response'      => 'claude',
		'qcld_grok_response'   => 'grok',
		'openrouter_response'  => 'openrouter',
		'mistral_response'     => 'mistral',
		'ollama_response'      => 'ollama',
		'deepseek_response'    => 'deepseek',
	);

	/**
	 * Stream actions => provider.
	 */
	const STREAM_ACTIONS = array(
		'qcld_stream_openai' => 'openai',
		'qcld_stream_gemini' => 'gemini',
		'qcld_stream_grokai' => 'grok',
		'qcld_stream_claude' => 'claude',
	);

	/**
	 * Transient prefix: reply details waiting for the widget's transcript save (sync_legacy).
	 */
	const PENDING = 'qcld_wpbot_chat_ai_meta_';

	/**
	 * The provider's error in this request ('' when none).
	 *
	 * @var string
	 */
	private static $error = '';

	/**
	 * The widget request being answered: session, text.
	 *
	 * @var array
	 */
	private static $request = array(
		'session' => '',
		'text'    => '',
	);

	/**
	 * Hook the actions right after the guards (priority 1).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'qcld_wpbot_chat/ai_failed', array( __CLASS__, 'failed' ) );
		foreach ( array_keys( self::JSON_ACTIONS ) as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, 'json' ), 2 );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'json' ), 2 );
		}
		foreach ( array_keys( self::STREAM_ACTIONS ) as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, 'stream' ), 2 );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'stream' ), 2 );
		}
	}

	/**
	 * Whether the bridge is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Whether the engine answers the legacy widget's AI actions.
		 *
		 * @param bool $enabled Enabled (option qcld_wpbot_chat_ai_bridge, default on).
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/ai_bridge', '0' !== (string) get_option( self::OPTION, '1' ) );
	}

	/**
	 * Provider an action belongs to ('' for unknown actions).
	 *
	 * @param string $action AJAX action.
	 * @return string
	 */
	public static function provider_for( $action ) {
		$all = array_merge( self::JSON_ACTIONS, self::STREAM_ACTIONS );
		return isset( $all[ $action ] ) ? $all[ $action ] : '';
	}

	/**
	 * Whether the engine answers this request (otherwise the old handler does).
	 *
	 * @param string $action   AJAX action.
	 * @param array  $post     Request fields.
	 * @param string $answers  Provider that answers on this site.
	 * @return bool
	 */
	public static function should_bridge( $action, array $post, $answers ) {
		$provider = self::provider_for( $action );
		$bridge   = '' !== $provider && $provider === $answers;
		if ( ! $bridge && '' !== $provider && '' !== $answers && ! Provider_Settings::is_set_up( $provider ) && Provider_Settings::is_set_up( $answers ) ) {
			// The widget asked a provider that isn't set up (for example Ollama with no server address,
			// whose old handler then called an empty URL): the provider that answers replies instead.
			$bridge = true;
		}
		if ( $bridge && ! empty( $post['ai_file_url'] ) ) {
			$bridge = false;
			// Attached files and images: the old handlers send them to the provider.
		}
		// OpenAI Assistants are no longer handed to the old handler: OpenAI shut the Assistants API
		// down, so those sites now get the engine's answer (round R2; Admin\Removed_Settings_Notice).
		if ( $bridge && 'grok' === $provider && '' !== trim( (string) get_option( 'qcld_grok_collection_id', '' ) ) ) {
			$bridge = false;
			// Grok document collections.
		}
		if ( $bridge && '' === self::text( $post ) ) {
			$bridge = false;
		}
		/**
		 * Whether the engine answers this legacy AI request.
		 *
		 * @param bool   $bridge Bridge.
		 * @param string $action AJAX action.
		 * @param array  $post   Request fields.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/ai_bridge_request', $bridge, $action, $post );
	}

	/**
	 * Visitor text: `keyword` (JSON actions, Free streams) or `message` (Pro streams).
	 *
	 * @param array $post Request fields.
	 * @return string
	 */
	public static function text( array $post ) {
		foreach ( array( 'keyword', 'message' ) as $field ) {
			if ( isset( $post[ $field ] ) && is_string( $post[ $field ] ) && '' !== trim( $post[ $field ] ) ) {
				return trim( $post[ $field ] );
			}
		}
		return '';
	}

	/**
	 * The engine message for a legacy request. Nothing is stored here: the widget saves its own
	 * transcript (Session_Store::sync_legacy()), which then picks up this reply's details.
	 *
	 * @param array $post Request fields.
	 * @return Message
	 */
	public static function message( array $post ) {
		self::$request = array(
			'session' => isset( $post['session_id'] ) && is_string( $post['session_id'] ) ? substr( (string) preg_replace( '/[^A-Za-z0-9_\-.:]/', '', $post['session_id'] ), 0, 100 ) : '',
			'text'    => self::text( $post ),
		);
		$history       = array();
		if ( isset( $post['ai_history'] ) && is_string( $post['ai_history'] ) ) {
			$decoded = json_decode( $post['ai_history'], true );
			// Already unslashed by the caller.
			$history = is_array( $decoded ) ? $decoded : array();
		}
		$text = self::text( $post );
		// The widget already added the visitor's message as the last history item.
		$last = end( $history );
		if ( is_array( $last ) && isset( $last['role'], $last['content'] ) && 'user' === $last['role'] && trim( (string) $last['content'] ) === $text ) {
			array_pop( $history );
		}
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		return Message::from_array(
			array(
				'text'       => $text,
				'name'       => isset( $post['name'] ) && is_string( $post['name'] ) ? $post['name'] : '',
				'channel'    => 'web',
				'page_url'   => $referer,
				'page_title' => isset( $post['page_title'] ) && is_string( $post['page_title'] ) ? $post['page_title'] : '',
				'history'    => $history,
				'extra'      => array(
					'ai_action'      => isset( $post['active_ai_action'] ) && is_scalar( $post['active_ai_action'] ) ? (string) $post['active_ai_action'] : '',
					// The widget's session, for the hub's transcript when the hub answers (not stored here).
					'legacy_session' => isset( $post['session_id'] ) && is_string( $post['session_id'] ) ? substr( (string) preg_replace( '/[^A-Za-z0-9_\-.:]/', '', $post['session_id'] ), 0, 100 ) : '',
				),
			)
		);
	}

	/**
	 * Engine pipeline for the widget's AI requests.
	 *
	 * @return Pipeline
	 */
	private static function pipeline() {
		/**
		 * Steps that answer the legacy widget's AI questions (Pro puts its own steps first, for
		 * example a shared knowledge base hub that answers this site's chats).
		 *
		 * @param \QuantumCloud\Chatbot\Conversation\Step[] $steps Steps.
		 */
		$steps = (array) apply_filters( 'qcld_wpbot_chat/ai_bridge_steps', array( new Knowledge_Step(), new Ai_Step() ) );
		$steps = array_values(
			array_filter(
				$steps,
				static function ( $step ) {
					return $step instanceof \QuantumCloud\Chatbot\Conversation\Step;
				}
			)
		);
		return new Pipeline( array() !== $steps ? $steps : array( new Knowledge_Step(), new Ai_Step() ) );
	}

	/**
	 * JSON actions.
	 *
	 * @return void
	 */
	public static function json() {
		$action = (string) current_action();
		$action = (string) preg_replace( '/^wp_ajax_(nopriv_)?/', '', $action );
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guarded by Ajax_Shims (same-site check, rate limit) at priority 1; full-page caches serve expired nonces.
		if ( ! self::should_bridge( $action, (array) $post, Provider_Settings::answering_provider() ) ) {
			return;
		}
		$message = self::message( (array) $post );
		$reply   = Chat::answer( $message, null, self::pipeline() );
		self::remember( $post, $reply );
		if ( \QuantumCloud\Chatbot\Spend\Fallback::limited() && 'ai' !== $reply->source ) {
			// AI paused by a spending limit: answer without AI, as a normal reply.
			echo wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON; the text is escaped.
				array(
					'status'  => 'success',
					'message' => esc_html( \QuantumCloud\Chatbot\Spend\Fallback::answer( $message ) ),
				)
			);
			exit;
		}
		echo wp_json_encode( self::json_payload( $reply ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON for the widget (text/html, as before); the message HTML is built by html().
		exit;
	}

	/**
	 * Keep the provider's error of this request (for site admins).
	 *
	 * @param \WP_Error|mixed $error Error.
	 * @return void
	 */
	public static function failed( $error ) {
		self::$error = '';
		if ( is_wp_error( $error ) ) {
			$data        = $error->get_error_data();
			self::$error = is_array( $data ) && ! empty( $data['detail'] ) ? (string) $data['detail'] : (string) $error->get_error_message();
		}
	}

	/**
	 * What the widget shows when the AI failed: the friendly message, and for site admins the
	 * provider's real error under it (visitors never see it).
	 *
	 * @return string HTML.
	 */
	public static function error_html() {
		$html = esc_html( Fallback_Step::ai_error_text() );
		if ( '' !== self::$error && current_user_can( 'manage_options' ) ) {
			$why   = (string) preg_replace( '/\b(sk|xai|gsk|AIza)[-_A-Za-z0-9]{12,}/', '$1-…', wp_strip_all_tags( self::$error ) );
			$html .= '<br><small class="qcld-wpbot-admin-error">' . esc_html(
				/* translators: %s: the AI provider's error message. */
				sprintf( __( 'Only site admins see this: %s', 'chatbot' ), $why )
			) . '</small>';
		}
		return $html;
	}

	/**
	 * The JSON the widget reads.
	 *
	 * @param Reply $reply Reply.
	 * @return array
	 */
	public static function json_payload( Reply $reply ) {
		if ( 'ai' !== $reply->source || '' === trim( $reply->text ) ) {
			// No AI could answer: the same contact buttons as a "not sure" answer, so the visitor
			// is not left at a dead end (QA 9.1). Also on its own key, for hosts that replace the
			// error text.
			$handoff = self::handoff_html();
			$payload = array(
				'status'  => 'error',
				'message' => self::error_html() . $handoff,
			);
			if ( '' !== $handoff ) {
				$payload['handoff'] = $handoff;
			}
			return $payload;
		}
		return array(
			'status'  => 'success',
			'message' => self::html( $reply ),
		);
	}

	/**
	 * Stream actions.
	 *
	 * @return void
	 */
	public static function stream() {
		$action = (string) preg_replace( '/^wp_ajax_(nopriv_)?/', '', (string) current_action() );
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See json().
		if ( ! self::should_bridge( $action, (array) $post, Provider_Settings::answering_provider() ) ) {
			return;
		}
		Sse::start();
		$sent    = false;
		$message = self::message( (array) $post );
		$reply   = Chat::answer(
			$message,
			static function ( $text ) use ( &$sent ) {
				$sent = true;
				Sse::json( self::chunk( (string) $text ) );
			},
			self::pipeline()
		);
		if ( 'ai' !== $reply->source && \QuantumCloud\Chatbot\Spend\Fallback::limited() ) {
			// AI paused by a spending limit: answer without AI, never an error.
			Sse::json( self::chunk( esc_html( \QuantumCloud\Chatbot\Spend\Fallback::answer( $message ) ) ) );
		} elseif ( 'ai' !== $reply->source ) {
			Sse::json( self::chunk( self::error_html() . self::handoff_html() ) );
		} elseif ( ! $sent && '' !== $reply->text ) {
			Sse::json( self::chunk( $reply->text ) );
			// A provider with streaming off: one piece.
		}
		$extra = self::extras_html( $reply );
		if ( '' !== $extra ) {
			Sse::json( self::chunk( $extra ) );
		}
		self::remember( $post, $reply );
		Sse::send( '[DONE]' );
		exit;
	}

	/**
	 * One stream event in the format every legacy parser reads.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function chunk( $text ) {
		return array( 'choices' => array( array( 'delta' => array( 'content' => (string) $text ) ) ) );
	}

	/**
	 * The reply as HTML for the widget: Markdown rendered, citations and the AI form note added.
	 *
	 * @param Reply $reply Reply.
	 * @return string
	 */
	public static function html( Reply $reply ) {
		$text = (string) $reply->text;
		// Pro ships Parsedown, Free the same library as Qcld_Parsedown (owner, 17 Nov 2026: Free
		// replies used the minimal fallback, so web addresses stayed plain text).
		$class = class_exists( '\Parsedown' ) ? '\Parsedown' : ( class_exists( '\Qcld_Parsedown' ) ? '\Qcld_Parsedown' : '' );
		if ( $class ) {
			$parsedown = new $class();
			if ( method_exists( $parsedown, 'setSafeMode' ) ) {
				$parsedown->setSafeMode( true );
			}
			$html = (string) $parsedown->text( $text );
		} else {
			$html = self::markdown( $text );
		}
		$html = self::links( $html );
		/**
		 * HTML of a bridged JSON reply (hosts append page suggestions, product cards).
		 *
		 * @param string $html  HTML.
		 * @param Reply  $reply Reply.
		 */
		$html = (string) apply_filters( 'qcld_wpbot_chat/ai_bridge_html', $html . self::extras_html( $reply ), $reply );
		return wp_kses_post( $html );
	}

	/**
	 * Web links open in a new tab, and web and email addresses written as plain text become links.
	 * Text inside links, code and pre blocks is left as it is.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function links( $html ) {
		$parts = preg_split( '/(<[^>]*>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$skip  = 0;
		$out   = '';
		foreach ( (array) $parts as $part ) {
			if ( '' !== $part && '<' === $part[0] ) {
				if ( preg_match( '#^<(a|code|pre)\b#i', $part ) ) {
					++$skip;
				} elseif ( preg_match( '#^</(a|code|pre)\s*>#i', $part ) ) {
					$skip = max( 0, $skip - 1 );
				}
				if ( preg_match( '#^<a\s#i', $part ) && ! preg_match( '/\starget=/i', $part ) && preg_match( '/\shref=(["\'])https?:/i', $part ) ) {
					$part = preg_replace( '/\s*\/?>$/', ' target="_blank" rel="noopener noreferrer">', $part );
				}
				$out .= $part;
				continue;
			}
			$out .= $skip ? $part : self::autolink( $part );
		}
		return $out;
	}

	/**
	 * Plain web and email addresses in escaped text to links; a trailing "." or ")" stays outside.
	 *
	 * @param string $text Escaped text (no tags).
	 * @return string
	 */
	public static function autolink( $text ) {
		$text = (string) preg_replace_callback(
			'#\b(?:https?://|www\.)[^\s<>"]+#iu',
			static function ( $m ) {
				$url  = $m[0];
				$tail = '';
				while ( preg_match( '/(?:[.,;:!?\'*_~]|&quot;|&#0?39;|&gt;)$/', $url, $end ) || ( ')' === substr( $url, -1 ) && substr_count( $url, '(' ) < substr_count( $url, ')' ) ) ) {
					$cut  = isset( $end[0] ) ? $end[0] : ')';
					$tail = $cut . $tail;
					$url  = substr( $url, 0, -strlen( $cut ) );
					$end  = array();
				}
				if ( ! preg_match( '/[\p{L}\p{N}]/u', preg_replace( '#^(?:https?://|www\.)#i', '', $url ) ) ) {
					return $m[0];
				}
				$href = 0 === stripos( $url, 'www.' ) ? 'https://' . $url : $url;
				return '<a href="' . esc_url( html_entity_decode( $href, ENT_QUOTES ) ) . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $tail;
			},
			$text
		);
		return (string) preg_replace_callback(
			'/\b[a-z0-9][a-z0-9._%+-]*@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}\b/i',
			static function ( $m ) {
				return '<a href="mailto:' . esc_attr( $m[0] ) . '">' . $m[0] . '</a>';
			},
			$text
		);
	}

	/**
	 * Minimal, safe Markdown to HTML when the host has no Parsedown loaded: the text is escaped
	 * first, then headings, bold, italics, strikethrough, inline code, links (http and https
	 * only), lists and paragraphs. links() adds plain addresses afterwards.
	 *
	 * @param string $text Markdown.
	 * @return string
	 */
	public static function markdown( $text ) {
		$html = esc_html( (string) $text );
		$html = (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
		$html = (string) preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
		$html = (string) preg_replace( '/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s', '<em>$1</em>', $html );
		$html = (string) preg_replace( '/(?<![\w])_(?![\s_])([^_\n]+?)(?<!\s)_(?!\w)/', '<em>$1</em>', $html );
		$html = (string) preg_replace( '/~~(?!\s)(.+?)(?<!\s)~~/', '<del>$1</del>', $html );
		$html = (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( $m ) {
				return '<a href="' . esc_url( html_entity_decode( $m[2], ENT_QUOTES ) ) . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
			},
			$html
		);
		// A heading is a block of its own.
		$html   = (string) preg_replace( '/^(#{1,6}\s.+)$/m', "\n$1\n", $html );
		$blocks = preg_split( '/\n\s*\n/', trim( $html ) );
		$out    = '';
		foreach ( (array) $blocks as $block ) {
			$lines = array_map( 'trim', explode( "\n", (string) $block ) );
			if ( $lines && count( $lines ) === count( preg_grep( '/^(?:[-*]|\d+\.)\s+/', $lines ) ) ) {
				$tag  = preg_match( '/^\d+\./', $lines[0] ) ? 'ol' : 'ul';
				$out .= '<' . $tag . '>';
				foreach ( $lines as $line ) {
					$out .= '<li>' . preg_replace( '/^(?:[-*]|\d+\.)\s+/', '', $line ) . '</li>';
				}
				$out .= '</' . $tag . '>';
			} elseif ( 1 === count( $lines ) && preg_match( '/^(#{1,6})\s+(.+?)\s*#*$/', $lines[0], $heading ) ) {
				$out .= '<h' . strlen( $heading[1] ) . '>' . $heading[2] . '</h' . strlen( $heading[1] ) . '>';
			} elseif ( '' !== trim( (string) $block ) ) {
				$out .= '<p>' . implode( '<br>', $lines ) . '</p>';
			}
		}
		return $out;
	}

	/**
	 * HTML added under the answer: source links (citations setting) and a note when an AI form
	 * was saved.
	 *
	 * @param Reply $reply Reply.
	 * @return string
	 */
	public static function extras_html( Reply $reply ) {
		$html = '';
		if ( ! empty( $reply->data['form_title'] ) && ! empty( $reply->data['form_entry'] ) ) {
			$html .= '<div class="ai-form-summary-card"><p>' . esc_html(
				/* translators: %s: AI Action (form) title. */
				sprintf( __( 'Thanks, your details for "%s" were sent.', 'chatbot' ), (string) $reply->data['form_title'] )
			) . '</p></div>';
		}
		if ( ! empty( $reply->data['cards'] ) && is_array( $reply->data['cards'] ) ) {
			$html .= self::cards_html( $reply->data['cards'] );
		}
		if ( ! empty( $reply->data['not_sure'] ) ) {
			$html .= self::handoff_html();
		} else {
			$html .= self::related_html( $reply );
		}
		/**
		 * More HTML under an answer (live chat escalation offer, LC2/CA4).
		 *
		 * @param string $extra   Default ''.
		 * @param Reply  $reply   Reply.
		 * @param array  $request session (the widget's), text (the visitor's question).
		 */
		$html .= (string) apply_filters( 'qcld_wpbot_chat/reply_extras', '', $reply, self::$request );
		if ( ! empty( $reply->data['citations'] ) && is_array( $reply->data['citations'] ) ) {
			$links = array();
			foreach ( array_slice( $reply->data['citations'], 0, 5 ) as $citation ) {
				if ( empty( $citation['url'] ) ) {
					continue;
				}
				$links[] = '<a href="' . esc_url( $citation['url'] ) . '" target="_blank" rel="noopener">' . esc_html( '' !== (string) $citation['title'] ? (string) $citation['title'] : (string) $citation['url'] ) . '</a>';
			}
			if ( $links ) {
				$html .= '<div class="qcld-wpbot-citations"><p><em>' . esc_html__( 'Sources:', 'chatbot' ) . '</em><br>' . implode( '<br>', $links ) . '</p></div>';
			}
		}
		if ( ! empty( $reply->data['choices'] ) && is_array( $reply->data['choices'] ) ) {
			$html .= self::choices_html( $reply->data['choices'] );
		}
		if ( ! empty( $reply->data['debug'] ) && is_array( $reply->data['debug'] ) ) {
			$html .= self::debug_html( $reply->data['debug'] );
		}
		return $html;
	}

	/**
	 * Answer buttons for a typed AI Action field (Store plan 7b, chat buttons contract C-4): the
	 * widget's quick replies, so a click sends the option as the visitor's answer in WPBot Free and
	 * Pro alike; the hosts' chat button component adds keyboard access and the used state. '' when
	 * there are no options.
	 *
	 * @param array $choices field, label, multiple, options[ value, label ].
	 * @return string
	 */
	public static function choices_html( array $choices ) {
		$buttons = '';
		foreach ( array_slice( isset( $choices['options'] ) ? (array) $choices['options'] : array(), 0, 20 ) as $option ) {
			if ( ! is_array( $option ) || ! isset( $option['label'] ) || '' === (string) $option['label'] ) {
				continue;
			}
			$buttons .= '<span class="wpb-quick-reply qcld-chat-common qcld-chatbot-choice-option" data-value="' . esc_attr( isset( $option['value'] ) ? (string) $option['value'] : (string) $option['label'] ) . '">' . esc_html( (string) $option['label'] ) . '</span>';
		}
		if ( '' === $buttons ) {
			return '';
		}
		$hint = ! empty( $choices['multiple'] ) ? '<p class="qcld-wpbot-choices-hint"><small>' . esc_html__( 'You can choose more than one.', 'chatbot' ) . '</small></p>' : '';
		return '<div class="qcld-wpbot-choices" data-field="' . esc_attr( isset( $choices['field'] ) ? (string) $choices['field'] : '' ) . '"' . ( ! empty( $choices['multiple'] ) ? ' data-multiple="1"' : '' ) . '>' . $hint . $buttons . '</div>';
	}

	/**
	 * Context Aware Start Menu: the start menu items related to an answered question, then "More
	 * options" (the widget's Back to Start button, which shows the whole menu). The widget sees
	 * `.qcld-wpbot-related` and doesn't repeat the whole menu after this answer. '' when there are none.
	 *
	 * @param Reply $reply Reply.
	 * @return string
	 */
	public static function related_html( Reply $reply ) {
		$ids = array();
		foreach ( isset( $reply->data['next_steps'] ) ? (array) $reply->data['next_steps'] : array() as $step ) {
			if ( is_array( $step ) && isset( $step['type'], $step['id'] ) && 'related' === $step['type'] ) {
				$ids[] = (string) $step['id'];
			}
		}
		$buttons = $ids ? \QuantumCloud\Chatbot\Menu\Menu_Items::render_ids( $ids ) : '';
		if ( '' === $buttons ) {
			return '';
		}
		$more = \QuantumCloud\Chatbot\Menu\Menu_Matcher::settings()['more'] ? '<span class="qcld-chatbot-wildcard qcld_back_to_start" data-wildcart="back">' . esc_html__( 'More options', 'chatbot' ) . '</span>' : '';
		return '<div class="qcld-wpbot-related"><p class="qcld-wpbot-related-title">' . esc_html__( 'Related', 'chatbot' ) . '</p>' . $buttons . $more . '</div>';
	}

	/**
	 * The handoff buttons the owner chose (Send us email, Leave your number, ...): the widget's own
	 * start-menu items, so its click handlers run them. '' when there are none.
	 *
	 * @return string
	 */
	public static function handoff_html() {
		$buttons = \QuantumCloud\Chatbot\Ai\Safe_Answer::handoff();
		return $buttons ? wp_kses_post( '<div class="qcld-wpbot-handoff">' . implode( '', wp_list_pluck( $buttons, 'html' ) ) . '</div>' ) : '';
	}

	/**
	 * Cards from tools (products, orders): title, url, image, price, note, button { label, url }.
	 *
	 * @param array[] $cards Cards.
	 * @return string
	 */
	public static function cards_html( array $cards ) {
		$items = array();
		foreach ( array_slice( $cards, 0, 6 ) as $card ) {
			if ( ! is_array( $card ) || empty( $card['title'] ) ) {
				continue;
			}
			$url   = ! empty( $card['url'] ) ? esc_url( (string) $card['url'] ) : '';
			$title = esc_html( (string) $card['title'] );
			$item  = '<div class="qcld-wpbot-card">';
			if ( ! empty( $card['image'] ) ) {
				$item .= '<img src="' . esc_url( (string) $card['image'] ) . '" alt="" loading="lazy" style="max-width:100%;height:auto">';
			}
			$item .= '<p><strong>' . ( '' !== $url ? '<a href="' . $url . '" target="_blank" rel="noopener">' . $title . '</a>' : $title ) . '</strong>';
			if ( ! empty( $card['price'] ) ) {
				$item .= '<br><span class="qcld-wpbot-card-price">' . wp_kses(
					(string) $card['price'],
					array(
						'span' => array( 'class' => true ),
						'del'  => array(),
						'ins'  => array(),
						'bdi'  => array(),
					)
				) . '</span>';
			}
			if ( ! empty( $card['note'] ) ) {
				$item .= '<br><small>' . esc_html( (string) $card['note'] ) . '</small>';
			}
			$item .= '</p>';
			if ( ! empty( $card['button']['url'] ) && ! empty( $card['button']['label'] ) ) {
				$item .= '<p><a class="qcld-wpbot-card-button" href="' . esc_url( (string) $card['button']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $card['button']['label'] ) . '</a></p>';
			}
			$items[] = $item . '</div>';
		}//end foreach
		return $items ? '<div class="qcld-wpbot-cards">' . implode( '', $items ) . '</div>' : '';
	}

	/**
	 * The admin debug view under an answer (only present for administrators, see
	 * Pipeline::debug_allowed()): model, tokens, cost, steps and knowledge base matches.
	 *
	 * @param array $debug Pipeline::debug() data.
	 * @return string
	 */
	public static function debug_html( array $debug ) {
		$lines   = array();
		$lines[] = esc_html(
			sprintf(
				/* translators: 1: provider, 2: model, 3: tokens in, 4: tokens out, 5: cost in USD. */
				__( '%1$s %2$s · %3$d in / %4$d out tokens · $%5$s', 'chatbot' ),
				isset( $debug['provider'] ) ? (string) $debug['provider'] : '',
				isset( $debug['model'] ) ? (string) $debug['model'] : '',
				isset( $debug['tokens_in'] ) ? (int) $debug['tokens_in'] : 0,
				isset( $debug['tokens_out'] ) ? (int) $debug['tokens_out'] : 0,
				number_format( isset( $debug['cost'] ) ? (float) $debug['cost'] : 0.0, 5 )
			)
		);
		if ( ! empty( $debug['steps'] ) && is_array( $debug['steps'] ) ) {
			$steps = array();
			foreach ( $debug['steps'] as $step => $state ) {
				$steps[] = $step . ': ' . $state;
			}
			$lines[] = esc_html__( 'Steps:', 'chatbot' ) . ' ' . esc_html( implode( ', ', $steps ) );
		}
		foreach ( isset( $debug['sources'] ) ? (array) $debug['sources'] : array() as $source ) {
			$lines[] = esc_html( number_format( isset( $source['score'] ) ? (float) $source['score'] : 0.0, 3 ) ) . ' · ' . esc_html( isset( $source['title'] ) ? (string) $source['title'] : '' );
		}
		return '<details class="qcld-wpbot-debug"><summary>' . esc_html__( 'Debug (admins only)', 'chatbot' ) . '</summary><small>' . implode( '<br>', $lines ) . '</small></details>';
	}

	/**
	 * Keep the reply's model, tokens, cost and debug view until the widget saves its transcript;
	 * Session_Store::sync_legacy() attaches them to the matching bot message.
	 *
	 * @param array $post  Request fields (session_id, sent by the widget).
	 * @param Reply $reply Reply.
	 * @return void
	 */
	public static function remember( $post, Reply $reply ) {
		$session = isset( $post['session_id'] ) && is_string( $post['session_id'] ) ? substr( preg_replace( '/[^A-Za-z0-9_\-.:]/', '', $post['session_id'] ), 0, 128 ) : '';
		if ( '' === $session || 'ai' !== $reply->source ) {
			return;
		}
		$key     = self::PENDING . md5( $session );
		$pending = get_transient( $key );
		$pending = is_array( $pending ) ? $pending : array();
		$payload = array();
		foreach ( array( 'citations', 'debug', 'form_entry' ) as $field ) {
			if ( isset( $reply->data[ $field ] ) ) {
				$payload[ $field ] = $reply->data[ $field ];
			}
		}
		$pending[] = array(
			'match'      => self::fingerprint( $reply->text ),
			'provider'   => isset( $reply->meta['provider'] ) ? (string) $reply->meta['provider'] : '',
			'model'      => isset( $reply->meta['model'] ) ? (string) $reply->meta['model'] : '',
			'tokens_in'  => isset( $reply->meta['tokens_in'] ) ? (int) $reply->meta['tokens_in'] : 0,
			'tokens_out' => isset( $reply->meta['tokens_out'] ) ? (int) $reply->meta['tokens_out'] : 0,
			'cost'       => isset( $reply->meta['cost'] ) ? (float) $reply->meta['cost'] : 0.0,
			'payload'    => $payload,
		);
		set_transient( $key, array_slice( $pending, -10 ), HOUR_IN_SECONDS );
	}

	/**
	 * Reply details waiting for a bot message of a session's transcript, or null. Removes them.
	 *
	 * @param string $session Session id.
	 * @param string $text    Bot message text (as saved from the widget's transcript).
	 * @return array|null
	 */
	public static function claim( $session, $text ) {
		$key     = self::PENDING . md5( (string) $session );
		$pending = get_transient( $key );
		if ( ! is_array( $pending ) || ! $pending ) {
			return null;
		}
		// The saved message also holds what the bridge added under the answer (sources, buttons),
		// so it starts with the answer's fingerprint.
		$saved = self::fingerprint( $text, 400 );
		foreach ( $pending as $i => $item ) {
			$match = isset( $item['match'] ) ? (string) $item['match'] : '';
			if ( strlen( $match ) >= 8 && 0 === strpos( $saved, $match ) ) {
				unset( $pending[ $i ] );
				set_transient( $key, array_values( $pending ), HOUR_IN_SECONDS );
				return $item;
			}
		}
		return null;
	}

	/**
	 * A text's first 60 letters and digits, lowercased: the same for the Markdown reply and the
	 * text saved from the widget's HTML.
	 *
	 * @param string $text   Text.
	 * @param int    $length Letters and digits kept.
	 * @return string
	 */
	public static function fingerprint( $text, $length = 60 ) {
		$plain = strtolower( wp_strip_all_tags( (string) $text ) );
		$plain = (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $plain );
		return function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, (int) $length ) : substr( $plain, 0, (int) $length );
	}
}
