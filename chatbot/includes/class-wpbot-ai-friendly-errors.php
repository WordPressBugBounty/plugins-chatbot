<?php
/**
 * Friendly AI errors for site visitors.
 *
 * When an AI provider fails (wrong or revoked key, no credit, rate limit,
 * provider down), the chat window used to show the provider's raw message,
 * for example "Gemini API Error: API key not valid", "Error: insufficient_quota"
 * or "Gemini stream failed". This class sits in front of every AI chat action
 * (normal and streaming) and, for visitors who cannot manage the site, replaces
 * any such error with one friendly message. Site admins still see the real error,
 * and the last error is saved so admins can see it on the AI settings page.
 *
 * The friendly text is the option `qcld_wpbot_ai_error_text` (filter
 * `qcld_wpbot_ai_friendly_error`). The same file ships in WPBot Free and Pro.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qcld_WPBot_AI_Friendly_Errors' ) ) {

	/**
	 * Replaces AI provider errors with a friendly message for visitors.
	 */
	class Qcld_WPBot_AI_Friendly_Errors {

		/** Option that stores the last AI error (autoload off). */
		const LAST_ERROR_OPTION = 'qcld_wpbot_ai_last_error';

		/** AI chat actions answered with one JSON reply. */
		const JSON_ACTIONS = array( 'openai_response', 'qcld_openai_response', 'gemini_response', 'qcld_gemini_response', 'claude_response', 'qcld_grok_response', 'openrouter_response', 'mistral_response', 'ollama_response', 'deepseek_response' );

		/** AI chat actions answered as a stream (server-sent events). */
		const STREAM_ACTIONS = array( 'qcld_stream_openai', 'qcld_stream_gemini', 'qcld_stream_grokai', 'qcld_stream_claude' );

		/** @var string Action being answered, '' when none. */
		private static $action = '';

		/** @var bool True once the stream error was replaced (only once per request). */
		private static $stream_replaced = false;

		/** @var bool True once the error of this request was saved. */
		private static $recorded = false;

		/** @var int Output buffer level of the stream filter, 0 when none. */
		private static $stream_level = 0;

		/**
		 * Hooks in. Priority 2: after the chatbot module's request guard (1), before the provider handlers (10).
		 *
		 * @return void
		 */
		public static function init() {
			foreach ( self::JSON_ACTIONS as $action ) {
				add_action( 'wp_ajax_' . $action, array( __CLASS__, 'start_json' ), 2 );
				add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'start_json' ), 2 );
			}
			foreach ( self::STREAM_ACTIONS as $action ) {
				add_action( 'wp_ajax_' . $action, array( __CLASS__, 'start_stream' ), 2 );
				add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'start_stream' ), 2 );
			}
			// The chatbot engine (wpbot-chatbot's widget bridge) answers some AI actions itself: keep its errors too.
			add_action( 'qcld_wpbot_chat/ai_failed', array( __CLASS__, 'engine_failed' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			// The notice clears itself: after an AI answer works again, when AI Settings are saved, or on Dismiss.
			add_action( 'qcld_wpbot_chat/message_sent', array( __CLASS__, 'clear_after_answer' ) );
			add_action( 'admin_post_qcld_wpbot_chat_ai_settings', array( __CLASS__, 'clear' ), 1 );
			add_action( 'admin_post_qcld_wpbot_dismiss_ai_error', array( __CLASS__, 'dismiss' ) );
			add_action( 'wp_ajax_qcld_wpbot_save_ai_error_text', array( __CLASS__, 'save_setting' ) );
		}

		/**
		 * Settings field (AI Settings > Common AI Settings): the message visitors see when the AI cannot answer.
		 *
		 * @return void
		 */
		public static function render_setting() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$value   = (string) get_option( 'qcld_wpbot_ai_error_text', '' );
			$default = __( "Sorry, I can't answer that right now. Please try again in a little while.", 'chatbot' );
			$last    = get_option( self::LAST_ERROR_OPTION );
			?>
			<div class="qcld-ai-error-text-setting my-4" style="max-width:760px">
				<h4><?php esc_html_e( 'Message when the AI cannot answer', 'chatbot' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Visitors see this message when the AI provider fails, for example a wrong or expired API key, no credit left, a rate limit, or the provider is down. Site admins still see the real error in the chat. Leave it empty to use the default.', 'chatbot' ); ?></p>
				<textarea id="qcld_wpbot_ai_error_text" class="form-control" rows="2" maxlength="500" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
				<p class="my-2">
					<button type="button" class="qcld-btn-primary" id="qcld_wpbot_ai_error_text_save" data-nonce="<?php echo esc_attr( wp_create_nonce( 'qcld_wpbot_ai_error_text' ) ); ?>"><?php esc_html_e( 'Save message', 'chatbot' ); ?></button>
					<span class="qcld-ai-error-text-status" style="margin-left:10px"></span>
				</p>
				<?php if ( is_array( $last ) && ! empty( $last['time'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: how long ago, 2: error text. */
							esc_html__( 'Last AI error, %1$s ago: %2$s', 'chatbot' ),
							esc_html( human_time_diff( (int) $last['time'] ) ),
							'<code>' . esc_html( (string) $last['message'] ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<script>
			jQuery( function ( $ ) {
				$( document ).on( 'click', '#qcld_wpbot_ai_error_text_save', function ( e ) {
					e.preventDefault();
					var $b = $( this ), $s = $( '.qcld-ai-error-text-status' );
					$b.prop( 'disabled', true );
					$.post( ajaxurl, { action: 'qcld_wpbot_save_ai_error_text', nonce: $b.data( 'nonce' ), text: $( '#qcld_wpbot_ai_error_text' ).val() } )
						.done( function ( r ) { $s.text( r && r.success ? <?php echo wp_json_encode( __( 'Saved.', 'chatbot' ) ); ?> : <?php echo wp_json_encode( __( 'Could not save. Reload the page and try again.', 'chatbot' ) ); ?> ); } )
						.fail( function () { $s.text( <?php echo wp_json_encode( __( 'Could not save. Reload the page and try again.', 'chatbot' ) ); ?> ); } )
						.always( function () { $b.prop( 'disabled', false ); } );
				} );
			} );
			</script>
			<?php
		}

		/**
		 * AJAX: saves the friendly message (admins only). Plain text; empty restores the default.
		 *
		 * @return void
		 */
		public static function save_setting() {
			if ( ! check_ajax_referer( 'qcld_wpbot_ai_error_text', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( null, 403 );
			}
			$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 500 ) : substr( $text, 0, 500 );
			if ( '' === trim( $text ) ) {
				delete_option( 'qcld_wpbot_ai_error_text' );
			} else {
				update_option( 'qcld_wpbot_ai_error_text', $text, false );
			}
			wp_send_json_success( array( 'text' => self::friendly_text() ) );
		}

		/**
		 * The message visitors see when the AI cannot answer.
		 *
		 * @return string Plain text.
		 */
		public static function friendly_text() {
			$text = trim( (string) get_option( 'qcld_wpbot_ai_error_text', '' ) );
			if ( '' === $text ) {
				$text = __( "Sorry, I can't answer that right now. Please try again in a little while.", 'chatbot' );
			}
			/**
			 * Message shown to site visitors when the AI provider fails.
			 *
			 * @param string $text Plain text.
			 */
			return (string) apply_filters( 'qcld_wpbot_ai_friendly_error', $text );
		}

		/**
		 * The friendly text as safe HTML. Only < and > are escaped, so the apostrophe in
		 * "can't" stays readable while the streamed reply is typed out.
		 *
		 * @return string
		 */
		public static function friendly_html() {
			return str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), wp_strip_all_tags( self::friendly_text() ) );
		}

		/**
		 * True when the current user sees real errors (site admins).
		 *
		 * @return bool
		 */
		public static function shows_real_errors() {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Starts buffering a JSON AI reply.
		 *
		 * @return void
		 */
		public static function start_json() {
			if ( '' !== self::$action ) {
				return;
			}
			self::$action = self::current_action();
			ob_start( array( __CLASS__, 'filter_json' ) );
			// wp_send_json() and "echo; wp_die()" end in the AJAX die handler: save the error there,
			// while WordPress is still fully running.
			add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'die_handler' ), 99 );
		}

		/**
		 * Wraps the AJAX die handler: saves the error, then lets the buffer go out (filtered).
		 *
		 * @param callable $handler Original handler.
		 * @return callable
		 */
		public static function die_handler( $handler ) {
			return function ( $message = '', $title = '', $args = array() ) use ( $handler ) {
				$out = (string) ob_get_contents();
				$why = self::json_error( $out );
				if ( null !== $why ) {
					self::record( $why );
				}
				call_user_func( $handler, $message, $title, $args );
			};
		}

		/**
		 * Output buffer callback for JSON replies: replaces an error message for visitors.
		 *
		 * @param string $out JSON text.
		 * @return string
		 */
		public static function filter_json( $out ) {
			$data = json_decode( (string) $out, true );
			if ( ! is_array( $data ) || null === self::json_error( $out ) || self::shows_real_errors() ) {
				return $out;
			}
			$text = self::friendly_html();
			if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! isset( $data['status'] ) ) {
				$data['data']['message'] = $text; // wp_send_json_error() shape.
				if ( isset( $data['data']['msg'] ) ) {
					$data['data']['msg'] = $text;
				}
			} else {
				$data['message'] = $text;
				if ( isset( $data['msg'] ) ) {
					$data['msg'] = $text;
				}
				unset( $data['raw_response'] );
			}
			$data['friendly_error'] = true;
			return (string) wp_json_encode( $data );
		}

		/**
		 * The provider error in a JSON reply, or null when it is not an AI error.
		 * Security-check replies ("reload the page") are kept: reloading fixes them.
		 *
		 * @param string $out JSON text.
		 * @return string|null
		 */
		public static function json_error( $out ) {
			$data = json_decode( (string) $out, true );
			if ( ! is_array( $data ) ) {
				return null;
			}
			$body = ( isset( $data['data'] ) && is_array( $data['data'] ) && ! isset( $data['status'] ) ) ? $data['data'] : $data;
			if ( ! isset( $body['status'] ) || 'error' !== $body['status'] ) {
				return null;
			}
			$why = isset( $body['message'] ) ? $body['message'] : ( isset( $body['msg'] ) ? $body['msg'] : '' );
			$why = is_scalar( $why ) ? (string) $why : (string) wp_json_encode( $why );
			if ( false !== stripos( $why, 'reload the page' ) || false !== stripos( $why, 'security check' ) ) {
				return null;
			}
			return '' === $why ? 'Unknown error' : $why;
		}

		/**
		 * Starts filtering a streamed AI reply. Chunk size 1 keeps streaming working:
		 * the callback runs for every piece the handler prints.
		 *
		 * @return void
		 */
		public static function start_stream() {
			if ( '' !== self::$action ) {
				return;
			}
			self::$action = self::current_action();
			// Streams need every buffer gone (the handlers clear them too): the filter becomes the only one.
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			ob_start( array( __CLASS__, 'filter_stream' ), 1 );
			self::$stream_level = ob_get_level();
		}

		/**
		 * For stream handlers: clears output buffers but keeps the stream filter.
		 * Replaces their `while ( ob_get_level() ) { ob_end_clean(); }`.
		 *
		 * @return void
		 */
		public static function clear_buffers() {
			while ( ob_get_level() > self::$stream_level ) {
				ob_end_clean();
			}
		}

		/**
		 * Output buffer callback for streams: an error line becomes the friendly text.
		 *
		 * Errors look like `data: [ERROR] ...`, `data: {"error": ...}` (also Claude's
		 * `{"type":"error"}`), or a raw provider error body passed through unchanged.
		 *
		 * @param string $chunk Output piece.
		 * @param int    $phase PHP output handler phase.
		 * @return string
		 */
		public static function filter_stream( $chunk, $phase = 0 ) {
			$chunk = (string) $chunk;
			if ( '' === $chunk || false === stripos( $chunk, 'error' ) ) {
				return self::$stream_replaced ? '' : $chunk;
			}
			$why = self::stream_error( $chunk );
			if ( null === $why ) {
				return self::$stream_replaced ? '' : $chunk;
			}
			if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
				self::record( $why );
			}
			if ( self::shows_real_errors() ) {
				return $chunk;
			}
			if ( self::$stream_replaced ) {
				return '';
			}
			self::$stream_replaced = true;
			$piece = array( 'choices' => array( array( 'delta' => array( 'content' => self::friendly_html() ) ) ) );
			return 'data: ' . wp_json_encode( $piece ) . "\n\ndata: [DONE]\n\n";
		}

		/**
		 * The provider error in a stream piece, or null.
		 *
		 * @param string $chunk Output piece.
		 * @return string|null
		 */
		public static function stream_error( $chunk ) {
			foreach ( preg_split( '/\r?\n/', $chunk ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$payload = 0 === strpos( $line, 'data:' ) ? trim( substr( $line, 5 ) ) : $line;
				if ( 0 === strpos( $payload, '[ERROR]' ) ) {
					$why = trim( substr( $payload, 7 ) );
					if ( false !== stripos( $why, 'security check' ) ) {
						return null;
					}
					return '' === $why ? 'Unknown error' : $why;
				}
				if ( '{' !== substr( $payload, 0, 1 ) ) {
					continue;
				}
				$data = json_decode( $payload, true );
				if ( ! is_array( $data ) || isset( $data['choices'] ) ) {
					continue;
				}
				if ( isset( $data['error'] ) || ( isset( $data['type'] ) && 'error' === $data['type'] ) ) {
					$err = isset( $data['error'] ) ? $data['error'] : $data;
					if ( is_array( $err ) ) {
						$err = isset( $err['message'] ) ? $err['message'] : wp_json_encode( $err );
					}
					return (string) $err;
				}
			}
			// A raw error body split over several lines (pretty-printed JSON).
			$data = json_decode( trim( $chunk ), true );
			if ( is_array( $data ) && isset( $data['error'] ) ) {
				$err = $data['error'];
				return is_array( $err ) ? ( isset( $err['message'] ) ? (string) $err['message'] : (string) wp_json_encode( $err ) ) : (string) $err;
			}
			return null;
		}

		/**
		 * Saves the last AI error for the admin notice. Keys never reach this text:
		 * providers mask them in their messages; long texts are cut.
		 *
		 * @param string $why Error text.
		 * @return void
		 */
		public static function record( $why ) {
			if ( self::$recorded ) {
				return;
			}
			self::$recorded = true;
			$why = (string) $why;
			// Handlers escape their messages for the chat bubble; save plain text (escaped on output).
			for ( $i = 0; $i < 3 && false !== strpos( $why, '&' ); $i++ ) {
				$why = html_entity_decode( $why, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
			$why = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $why ) ) );
			$why = preg_replace( '/\b(sk|xai|gsk|AIza)[-_A-Za-z0-9]{12,}/', '$1-…', $why ); // In case a key is echoed back.
			// WPBot's error log (AI Settings > Error log), for the support report.
			if ( function_exists( 'qcld_wpbot_core_log_error' ) ) {
				qcld_wpbot_core_log_error(
					'ai',
					$why,
					array(
						'level'  => 'error',
						'module' => 'chatbot',
						'action' => (string) self::$action,
					)
				);
			}
			update_option(
				self::LAST_ERROR_OPTION,
				array(
					'time'    => time(),
					'action'  => self::$action,
					'message' => function_exists( 'mb_substr' ) ? mb_substr( $why, 0, 300 ) : substr( $why, 0, 300 ),
				),
				false
			);
		}

		/**
		 * Saves an error of the chatbot engine for the site admin.
		 *
		 * @param WP_Error|mixed $error The provider's error.
		 * @return void
		 */
		public static function engine_failed( $error ) {
			if ( ! is_wp_error( $error ) ) {
				return;
			}
			if ( '' === self::$action ) {
				self::$action = self::current_action();
			}
			// WPBot Core keeps the provider's own words (for example the unknown model) as "detail".
			$data = $error->get_error_data();
			self::record( is_array( $data ) && ! empty( $data['detail'] ) ? $data['detail'] : $error->get_error_message() );
		}

		/**
		 * Admin notice on the AI settings pages when an AI error happened in the last 3 days.
		 *
		 * @return void
		 */
		public static function admin_notice() {
			if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! in_array( $page, array( 'wpbot_openai', 'openai-panel_dashboard', 'wpbot-panel', 'wpbot' ), true ) ) {
				return;
			}
			$last = get_option( self::LAST_ERROR_OPTION );
			if ( ! is_array( $last ) || empty( $last['time'] ) || time() - (int) $last['time'] > 3 * DAY_IN_SECONDS ) {
				return;
			}
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p><code>%3$s</code></p><p>%4$s <a href="%5$s">%6$s</a></p></div>',
				esc_html__( 'WPBot AI error:', 'chatbot' ),
				esc_html(
					sprintf(
						/* translators: %s: how long ago, for example "5 mins". */
						__( 'the AI provider refused a chat request %s ago. Visitors saw a friendly message instead.', 'chatbot' ),
						human_time_diff( (int) $last['time'] )
					)
				),
				esc_html( (string) $last['message'] ),
				esc_html( self::hint( (string) $last['message'] ) ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=qcld_wpbot_dismiss_ai_error' ), 'qcld_wpbot_dismiss_ai_error' ) ),
				esc_html__( 'Dismiss', 'chatbot' )
			);
		}

		/**
		 * Forget the last AI error (the notice goes away).
		 *
		 * @return void
		 */
		public static function clear() {
			if ( false !== get_option( self::LAST_ERROR_OPTION, false ) ) {
				delete_option( self::LAST_ERROR_OPTION );
			}
		}

		/**
		 * After a reply the AI wrote, the last error is over.
		 *
		 * @param object $reply The chatbot's reply (QuantumCloud\Chatbot\Conversation\Reply).
		 * @return void
		 */
		public static function clear_after_answer( $reply ) {
			if ( is_object( $reply ) && isset( $reply->source, $reply->text ) && 'ai' === $reply->source && '' !== trim( (string) $reply->text ) ) {
				self::clear();
			}
		}

		/**
		 * The notice's Dismiss link.
		 *
		 * @return void
		 */
		public static function dismiss() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), 403 );
			}
			check_admin_referer( 'qcld_wpbot_dismiss_ai_error' );
			self::clear();
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}

		/**
		 * What to check for this error: an Ollama or connection error is about the server address,
		 * the rest about the key and credit.
		 *
		 * @param string $message The provider's error.
		 * @return string
		 */
		public static function hint( $message ) {
			if ( false !== stripos( $message, 'ollama' ) ) {
				return __( 'Check the Ollama server address and port in AI Settings > Providers, and that the Ollama server is running.', 'chatbot' );
			}
			if ( preg_match( '/could not resolve|connection refused|timed out|protocol|ssl/i', $message ) ) {
				return __( 'The AI provider could not be reached. Check the server address in AI Settings > Providers and this site\'s internet connection.', 'chatbot' );
			}
			return __( 'Check the API key and the credit balance of your AI provider account.', 'chatbot' );
		}

		/**
		 * The AJAX action of this request.
		 *
		 * @return string
		 */
		private static function current_action() {
			return isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}

	Qcld_WPBot_AI_Friendly_Errors::init();
}
