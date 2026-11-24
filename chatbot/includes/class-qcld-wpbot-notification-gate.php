<?php
/**
 * The one gate every WPBot email passes (owner, 23 November 2026: fine-grained notification
 * settings). No module needs its own setting.
 *
 * - pre_wp_mail (after WPBot Core's wp_mail filter has routed staff emails to the notification
 *   list): an email sent by WPBot's own code (WPBot Core's Error_Capture::is_own() on the caller's
 *   file, so the synced modules and add-ons count) is matched to a notification of
 *   QCLD_WPBot_Notification_Catalog by the files in the call stack.
 * - Emails to visitors and customers (locked notifications, and any email with a recipient who is
 *   not staff) are never touched.
 * - Off: the email is not sent (wp_mail() reports success, so features don't log a failure or retry).
 * - Daily or weekly digest: the email is queued (option, not autoloaded) and one digest email per
 *   recipient goes out from WP-Cron. A run takes a lock and a "last sent" stamp, so cron firing
 *   twice sends nothing twice.
 * - Until the owner saves the settings nothing is held: every email goes out as before.
 *
 * Filters: qcld_wpbot_notification_classify (notification id for an email),
 * qcld_wpbot_notification_decision (instant / daily / weekly / off for an email).
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Notification_Gate' ) ) {

	/**
	 * Gate, queue and digests.
	 */
	final class QCLD_WPBot_Notification_Gate {

		/**
		 * Queue option (not autoloaded).
		 */
		const QUEUE = 'qcld_wpbot_notification_queue';

		/**
		 * Last digest sent, per frequency: [ daily => ts, weekly => ts ].
		 */
		const LAST = 'qcld_wpbot_notification_digest_last';

		/**
		 * Lock option while a digest run sends.
		 */
		const LOCK = 'qcld_wpbot_notification_digest_lock';

		/**
		 * Cron hooks.
		 */
		const CRON_DAILY  = 'qcld_wpbot_notification_digest_daily';
		const CRON_WEEKLY = 'qcld_wpbot_notification_digest_weekly';

		/**
		 * At most this many queued emails (the oldest are dropped first).
		 */
		const MAX_QUEUE = 500;

		/**
		 * Longest message kept in the queue (characters of text).
		 */
		const MAX_TEXT = 2000;

		/**
		 * True while a digest is being sent (the digest itself is not gated).
		 *
		 * @var bool
		 */
		private static $sending = false;

		/**
		 * Staff check cache for this request: lower-case address => bool.
		 *
		 * @var array<string,bool>
		 */
		private static $staff = array();

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function hook() {
			add_filter( 'pre_wp_mail', array( __CLASS__, 'gate' ), 20, 2 );
			add_action( self::CRON_DAILY, array( __CLASS__, 'run_daily' ) );
			add_action( self::CRON_WEEKLY, array( __CLASS__, 'run_weekly' ) );
			add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 30 );
		}

		/**
		 * The gate (pre_wp_mail).
		 *
		 * @param null|bool $short Null to send; a bool skips sending and is what wp_mail() returns.
		 * @param array     $atts  to, subject, message, headers, attachments.
		 * @return null|bool
		 */
		public static function gate( $short, $atts ) {
			if ( null !== $short || self::$sending || ! is_array( $atts ) ) {
				return $short;
			}
			if ( ! QCLD_WPBot_Notification_Settings::is_saved() ) {
				return $short; // Nothing chosen yet: today's behaviour.
			}
			$stack = self::wpbot_stack();
			if ( null === $stack ) {
				return $short; // Not sent by WPBot.
			}
			$nid      = QCLD_WPBot_Notification_Catalog::classify( $stack, $atts );
			$decision = self::decide( $nid, $atts );
			if ( QCLD_WPBot_Notification_Settings::INSTANT === $decision ) {
				return $short;
			}
			if ( QCLD_WPBot_Notification_Settings::OFF !== $decision ) {
				self::enqueue( $nid, $decision, $atts );
			}
			return true;
		}

		/**
		 * What to do with one email.
		 *
		 * @param string $nid  Notification id.
		 * @param array  $atts The email.
		 * @return string instant, daily, weekly or off.
		 */
		public static function decide( $nid, array $atts ) {
			$decision = QCLD_WPBot_Notification_Settings::effective( $nid );
			// A notification for staff that goes to someone else (a visitor or customer) is sent.
			if ( QCLD_WPBot_Notification_Settings::INSTANT !== $decision && ! self::all_staff( isset( $atts['to'] ) ? $atts['to'] : '' ) ) {
				$decision = QCLD_WPBot_Notification_Settings::INSTANT;
			}
			/**
			 * What happens to one WPBot email.
			 *
			 * @param string $decision instant, daily, weekly or off.
			 * @param string $nid      Notification id (QCLD_WPBot_Notification_Catalog).
			 * @param array  $atts     The email.
			 */
			$decision = (string) apply_filters( 'qcld_wpbot_notification_decision', $decision, $nid, $atts );
			$allowed  = array( QCLD_WPBot_Notification_Settings::INSTANT, QCLD_WPBot_Notification_Settings::DAILY, QCLD_WPBot_Notification_Settings::WEEKLY, QCLD_WPBot_Notification_Settings::OFF );
			return in_array( $decision, $allowed, true ) ? $decision : QCLD_WPBot_Notification_Settings::INSTANT;
		}

		/**
		 * The call stack when WPBot's code called wp_mail(), else null: files => plugin-relative
		 * files (the first one called wp_mail()), fns => the functions and "Class::method" names
		 * around the call (innermost first).
		 *
		 * @return array{files:string[],fns:string[]}|null
		 */
		public static function wpbot_stack() {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only file and function names are read, to tell WPBot's emails apart.
			$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 );
			$found = false;
			$files = array();
			$fns   = array();
			foreach ( $trace as $frame ) {
				if ( ! $found ) {
					if ( isset( $frame['function'] ) && 'wp_mail' === $frame['function'] && empty( $frame['class'] ) ) {
						$found = true;
						if ( ! isset( $frame['file'] ) || ! self::is_own( (string) $frame['file'] ) ) {
							return null;
						}
						$files[] = self::relative( (string) $frame['file'] );
					}
					continue;
				}
				if ( isset( $frame['function'] ) ) {
					$fns[] = ( ! empty( $frame['class'] ) ? $frame['class'] . '::' : '' ) . $frame['function'];
				}
				if ( isset( $frame['file'] ) && self::is_own( (string) $frame['file'] ) ) {
					$files[] = self::relative( (string) $frame['file'] );
				}
			}
			if ( ! $found ) {
				return null;
			}
			return array(
				'files' => array_values( array_unique( $files ) ),
				'fns'   => $fns,
			);
		}

		/**
		 * Whether a file belongs to WPBot (WPBot Core's list of WPBot folders, else this plugin).
		 *
		 * @param string $file Absolute path.
		 * @return bool
		 */
		private static function is_own( $file ) {
			if ( class_exists( '\QuantumCloud\WPBotCore\Diagnostics\Error_Capture' ) ) {
				return \QuantumCloud\WPBotCore\Diagnostics\Error_Capture::is_own( $file );
			}
			return 0 === strpos( str_replace( '\\', '/', $file ), self::root() );
		}

		/**
		 * This plugin's folder, with a trailing slash.
		 *
		 * @return string
		 */
		private static function root() {
			return str_replace( '\\', '/', trailingslashit( dirname( __DIR__ ) ) );
		}

		/**
		 * A path relative to the plugins folder's WPBot plugin (e.g. "includes/chatbot/src/Leads/Leads.php").
		 *
		 * @param string $file Absolute path.
		 * @return string
		 */
		public static function relative( $file ) {
			$file = str_replace( '\\', '/', $file );
			$root = self::root();
			if ( 0 === strpos( $file, $root ) ) {
				return substr( $file, strlen( $root ) );
			}
			// Another WPBot plugin (e.g. WPBot Free next to Pro): from its plugin folder on.
			$plugins = str_replace( '\\', '/', trailingslashit( WP_PLUGIN_DIR ) );
			if ( 0 === strpos( $file, $plugins ) ) {
				$rest = substr( $file, strlen( $plugins ) );
				$cut  = strpos( $rest, '/' );
				return false === $cut ? $rest : substr( $rest, $cut + 1 );
			}
			return $file;
		}

		/**
		 * Whether every recipient is staff: an address of the notification list (or its suggestions),
		 * the ChatBot's staff addresses, or a user who can edit posts.
		 *
		 * @param string|string[] $to Recipients as wp_mail() takes them.
		 * @return bool
		 */
		public static function all_staff( $to ) {
			$list = is_array( $to ) ? $to : explode( ',', (string) $to );
			$any  = false;
			foreach ( $list as $recipient ) {
				$recipient = trim( (string) $recipient );
				if ( '' === $recipient ) {
					continue;
				}
				$any = true;
				if ( ! self::is_staff( self::address( $recipient ) ) ) {
					return false;
				}
			}
			return $any;
		}

		/**
		 * One staff address check.
		 *
		 * @param string $email Address.
		 * @return bool
		 */
		private static function is_staff( $email ) {
			$key = strtolower( $email );
			if ( isset( self::$staff[ $key ] ) ) {
				return self::$staff[ $key ];
			}
			$known = array( (string) get_option( 'admin_email', '' ) );
			if ( function_exists( 'qcld_wpbot_core_notification_emails' ) ) {
				$known = array_merge( $known, (array) qcld_wpbot_core_notification_emails() );
			}
			if ( class_exists( '\QuantumCloud\WPBotCore\Notifications\Notification_Emails' ) ) {
				$known = array_merge( $known, \QuantumCloud\WPBotCore\Notifications\Notification_Emails::defaults() );
			}
			/**
			 * Extra addresses counted as staff (their WPBot emails follow the notification settings).
			 *
			 * @param string[] $known Addresses.
			 */
			$known = (array) apply_filters( 'qcld_wpbot_notification_staff_emails', $known );
			$known = array_map( 'strtolower', array_map( 'trim', array_map( 'strval', $known ) ) );
			$staff = in_array( $key, $known, true );
			if ( ! $staff && function_exists( 'get_user_by' ) ) {
				$user  = get_user_by( 'email', $email );
				$staff = $user && user_can( $user, 'edit_posts' );
			}
			self::$staff[ $key ] = $staff;
			return $staff;
		}

		/**
		 * "Name <address>" → address.
		 *
		 * @param string $recipient Recipient.
		 * @return string
		 */
		private static function address( $recipient ) {
			return trim( preg_match( '/<([^>]+)>/', $recipient, $m ) ? $m[1] : $recipient );
		}

		/**
		 * Queue one email for a digest.
		 *
		 * @param string $nid  Notification id.
		 * @param string $freq daily or weekly.
		 * @param array  $atts The email.
		 * @return void
		 */
		public static function enqueue( $nid, $freq, array $atts ) {
			$to = isset( $atts['to'] ) ? $atts['to'] : array();
			$to = is_array( $to ) ? $to : explode( ',', (string) $to );
			$to = array_values( array_filter( array_map( array( __CLASS__, 'address' ), array_map( 'strval', $to ) ) ) );
			if ( ! $to ) {
				return;
			}
			$headers = isset( $atts['headers'] ) ? $atts['headers'] : '';
			$headers = is_array( $headers ) ? implode( "\n", $headers ) : (string) $headers;
			$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
			$is_html = false !== stripos( $headers, 'text/html' ) || wp_strip_all_tags( $message ) !== $message;
			$text    = $is_html ? wp_strip_all_tags( preg_replace( '#<br\s*/?>|</p>|</div>|</li>|</tr>#i', "\n", $message ) ) : $message;
			$text    = trim( preg_replace( "/\n{3,}/", "\n\n", html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
			if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > self::MAX_TEXT ) {
				$text = mb_substr( $text, 0, self::MAX_TEXT ) . '…';
			} elseif ( strlen( $text ) > self::MAX_TEXT ) {
				$text = substr( $text, 0, self::MAX_TEXT ) . '…';
			}
			$queue   = self::queue();
			$queue[] = array(
				'id'      => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'nq', true ),
				'nid'     => (string) $nid,
				'freq'    => (string) $freq,
				'to'      => $to,
				'subject' => sanitize_text_field( isset( $atts['subject'] ) ? (string) $atts['subject'] : '' ),
				'text'    => $text,
				'files'   => ! empty( $atts['attachments'] ),
				'time'    => time(),
			);
			if ( count( $queue ) > self::MAX_QUEUE ) {
				$queue = array_slice( $queue, -self::MAX_QUEUE );
			}
			update_option( self::QUEUE, $queue, false );
			self::schedule();
		}

		/**
		 * The queue.
		 *
		 * @return array[]
		 */
		public static function queue() {
			wp_cache_delete( self::QUEUE, 'options' );
			$queue = get_option( self::QUEUE, array() );
			return is_array( $queue ) ? array_values( array_filter( $queue, 'is_array' ) ) : array();
		}

		/**
		 * Emails waiting for a digest.
		 *
		 * @return int
		 */
		public static function queued_count() {
			return count( self::queue() );
		}

		/**
		 * Daily cron.
		 *
		 * @return int Digest emails sent.
		 */
		public static function run_daily() {
			return self::run( QCLD_WPBot_Notification_Settings::DAILY );
		}

		/**
		 * Weekly cron.
		 *
		 * @return int Digest emails sent.
		 */
		public static function run_weekly() {
			return self::run( QCLD_WPBot_Notification_Settings::WEEKLY );
		}

		/**
		 * Send one frequency's digests: one email per recipient. Safe to run twice: a lock, and a
		 * frequency already sent within its period (minus a few hours' slack) sends nothing.
		 *
		 * @param string $freq  daily or weekly.
		 * @param bool   $force Skip the "already sent this period" check (the lock still applies).
		 * @return int Digest emails sent.
		 */
		public static function run( $freq, $force = false ) {
			$periods = array(
				QCLD_WPBot_Notification_Settings::DAILY  => DAY_IN_SECONDS - 3 * HOUR_IN_SECONDS,
				QCLD_WPBot_Notification_Settings::WEEKLY => WEEK_IN_SECONDS - 12 * HOUR_IN_SECONDS,
			);
			if ( ! isset( $periods[ $freq ] ) ) {
				return 0;
			}
			$last = get_option( self::LAST, array() );
			$last = is_array( $last ) ? $last : array();
			if ( ! $force && ! empty( $last[ $freq ] ) && time() - (int) $last[ $freq ] < $periods[ $freq ] ) {
				return 0;
			}
			if ( ! self::lock() ) {
				return 0;
			}
			$sent = 0;
			try {
				$last[ $freq ] = time();
				update_option( self::LAST, $last, false );
				list( $take, $keep ) = self::split( self::queue(), $freq );
				// Claim before sending: a second run finds nothing to send.
				update_option( self::QUEUE, $keep, false );
				$sent = self::send_digests( $take, $freq );
			} finally {
				self::unlock();
			}
			return $sent;
		}

		/**
		 * Split the queue into what this run sends and what stays.
		 *
		 * - A notification switched off since it was queued is dropped.
		 * - One now set to Instantly goes out with the next run of either frequency.
		 * - One whose frequency changed waits for its new frequency.
		 *
		 * @param array[] $queue Queue.
		 * @param string  $freq  daily or weekly.
		 * @return array{0:array[],1:array[]}
		 */
		public static function split( array $queue, $freq ) {
			$take = array();
			$keep = array();
			foreach ( $queue as $entry ) {
				$nid = isset( $entry['nid'] ) ? (string) $entry['nid'] : '';
				$now = QCLD_WPBot_Notification_Settings::effective( $nid );
				if ( QCLD_WPBot_Notification_Settings::OFF === $now ) {
					continue;
				}
				if ( $now === $freq || QCLD_WPBot_Notification_Settings::INSTANT === $now ) {
					$take[] = $entry;
				} else {
					$keep[] = $entry;
				}
			}
			return array( $take, $keep );
		}

		/**
		 * Send the digests: one email per recipient.
		 *
		 * @param array[] $entries Queued emails.
		 * @param string  $freq    daily or weekly.
		 * @return int Emails sent.
		 */
		public static function send_digests( array $entries, $freq ) {
			$by = array();
			foreach ( $entries as $entry ) {
				foreach ( (array) $entry['to'] as $to ) {
					$key = strtolower( (string) $to );
					if ( '' === $key ) {
						continue;
					}
					if ( ! isset( $by[ $key ] ) ) {
						$by[ $key ] = array(
							'to'      => (string) $to,
							'entries' => array(),
						);
					}
					$by[ $key ]['entries'][ $entry['id'] ] = $entry;
				}
			}
			$sent = 0;
			foreach ( $by as $one ) {
				$mail          = self::compose( array_values( $one['entries'] ), $freq );
				self::$sending = true;
				try {
					if ( wp_mail( $one['to'], $mail['subject'], $mail['html'], array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
						++$sent;
					}
				} finally {
					self::$sending = false;
				}
			}
			return $sent;
		}

		/**
		 * One digest email.
		 *
		 * @param array[] $entries Queued emails for one recipient.
		 * @param string  $freq    daily or weekly.
		 * @return array{subject:string,html:string}
		 */
		public static function compose( array $entries, $freq ) {
			$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
			$count   = count( $entries );
			$subject = QCLD_WPBot_Notification_Settings::WEEKLY === $freq
				/* translators: 1: site name, 2: number of notifications. */
				? sprintf( _n( '[%1$s] WPBot weekly digest: %2$d notification', '[%1$s] WPBot weekly digest: %2$d notifications', $count, 'chatbot' ), $site, $count )
				/* translators: 1: site name, 2: number of notifications. */
				: sprintf( _n( '[%1$s] WPBot daily digest: %2$d notification', '[%1$s] WPBot daily digest: %2$d notifications', $count, 'chatbot' ), $site, $count );
			// Group by feature, in the catalog's order.
			$groups = array();
			foreach ( $entries as $entry ) {
				$item             = QCLD_WPBot_Notification_Catalog::item( $entry['nid'] );
				$gid              = $item ? $item['group'] : 'other';
				$groups[ $gid ][] = $entry;
			}
			$order = array_keys( QCLD_WPBot_Notification_Catalog::groups() );
			uksort(
				$groups,
				static function ( $a, $b ) use ( $order ) {
					return (int) array_search( $a, $order, true ) - (int) array_search( $b, $order, true );
				}
			);
			$all   = QCLD_WPBot_Notification_Catalog::groups();
			$html  = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:14px;line-height:1.5;color:#0f172a;max-width:680px">';
			$html .= '<h1 style="font-size:20px;margin:0 0 4px;color:#065f46">' . esc_html( $subject ) . '</h1>';
			$html .= '<p style="margin:0 0 16px;color:#475569">' . esc_html__( 'Your WPBot notifications, bundled as you chose in AI / Settings → Notifications.', 'chatbot' ) . '</p>';
			foreach ( $groups as $gid => $list ) {
				usort(
					$list,
					static function ( $a, $b ) {
						return (int) $a['time'] - (int) $b['time'];
					}
				);
				$label = isset( $all[ $gid ] ) ? $all[ $gid ]['label'] : __( 'Other', 'chatbot' );
				$html .= '<h2 style="font-size:16px;margin:20px 0 8px;padding-bottom:4px;border-bottom:2px solid #059669">' . esc_html( $label ) . ' (' . count( $list ) . ')</h2>';
				foreach ( $list as $entry ) {
					$item  = QCLD_WPBot_Notification_Catalog::item( $entry['nid'] );
					$when  = function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] ) : gmdate( 'Y-m-d H:i', (int) $entry['time'] );
					$html .= '<div style="margin:0 0 12px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px">';
					$html .= '<div style="font-weight:600">' . esc_html( '' !== $entry['subject'] ? $entry['subject'] : ( $item ? $item['label'] : '' ) ) . '</div>';
					$html .= '<div style="font-size:12px;color:#64748b">' . esc_html( ( $item ? $item['label'] . ' · ' : '' ) . $when ) . '</div>';
					if ( '' !== (string) $entry['text'] ) {
						$html .= '<div style="margin-top:6px;white-space:pre-wrap">' . esc_html( (string) $entry['text'] ) . '</div>';
					}
					if ( ! empty( $entry['files'] ) ) {
						$html .= '<div style="margin-top:6px;font-size:12px;color:#64748b">' . esc_html__( 'This email had an attachment, which digests do not include.', 'chatbot' ) . '</div>';
					}
					$html .= '</div>';
				}
			}
			$link  = admin_url( 'admin.php?page=wpbot_openAi&section=notifications' );
			$html .= '<p style="margin-top:20px;font-size:12px;color:#64748b">' . esc_html__( 'Change which emails you get and how often:', 'chatbot' ) . ' <a href="' . esc_url( $link ) . '" style="color:#047857">' . esc_html__( 'AI / Settings → Notifications', 'chatbot' ) . '</a></p></div>';
			return array(
				'subject' => $subject,
				'html'    => $html,
			);
		}

		/**
		 * Take the lock (add_option is atomic: only one run gets it). A lock older than 15 minutes
		 * is stale (a run that died) and is taken over.
		 *
		 * @return bool
		 */
		private static function lock() {
			if ( add_option( self::LOCK, time(), '', false ) ) {
				return true;
			}
			wp_cache_delete( self::LOCK, 'options' );
			$since = (int) get_option( self::LOCK, 0 );
			if ( $since && time() - $since > 15 * MINUTE_IN_SECONDS ) {
				delete_option( self::LOCK );
				return (bool) add_option( self::LOCK, time(), '', false );
			}
			return false;
		}

		/**
		 * Release the lock.
		 *
		 * @return void
		 */
		private static function unlock() {
			delete_option( self::LOCK );
		}

		/**
		 * Schedule the digests in use, and unschedule the others.
		 *
		 * @return void
		 */
		public static function schedule() {
			$used = self::frequencies_in_use();
			$jobs = array(
				self::CRON_DAILY  => array( QCLD_WPBot_Notification_Settings::DAILY, 'daily', self::next_time( false ) ),
				self::CRON_WEEKLY => array( QCLD_WPBot_Notification_Settings::WEEKLY, 'weekly', self::next_time( true ) ),
			);
			foreach ( $jobs as $hook => $job ) {
				$next = wp_next_scheduled( $hook );
				// A queued email waits for its frequency even after the owner changed it back.
				$needed = in_array( $job[0], $used, true ) || self::has_queued( $job[0] );
				if ( $needed && ! $next ) {
					wp_schedule_event( $job[2], $job[1], $hook );
				} elseif ( ! $needed && $next ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}

		/**
		 * Keep the schedule right (cheap: only when settings were saved).
		 *
		 * @return void
		 */
		public static function maybe_schedule() {
			if ( ! QCLD_WPBot_Notification_Settings::is_saved() ) {
				return;
			}
			$used = self::frequencies_in_use();
			if ( ( in_array( QCLD_WPBot_Notification_Settings::DAILY, $used, true ) && ! wp_next_scheduled( self::CRON_DAILY ) )
				|| ( in_array( QCLD_WPBot_Notification_Settings::WEEKLY, $used, true ) && ! wp_next_scheduled( self::CRON_WEEKLY ) ) ) {
				self::schedule();
			}
		}

		/**
		 * Remove the scheduled digests (deactivation, uninstall).
		 *
		 * @return void
		 */
		public static function unschedule() {
			wp_clear_scheduled_hook( self::CRON_DAILY );
			wp_clear_scheduled_hook( self::CRON_WEEKLY );
		}

		/**
		 * Digest frequencies some notification uses now.
		 *
		 * @return string[]
		 */
		public static function frequencies_in_use() {
			$used = array();
			foreach ( QCLD_WPBot_Notification_Catalog::groups() as $group ) {
				foreach ( array_keys( $group['items'] ) as $nid ) {
					$f = QCLD_WPBot_Notification_Settings::effective( $nid );
					if ( in_array( $f, array( QCLD_WPBot_Notification_Settings::DAILY, QCLD_WPBot_Notification_Settings::WEEKLY ), true ) ) {
						$used[ $f ] = $f;
					}
				}
			}
			return array_values( $used );
		}

		/**
		 * Whether emails are queued for a frequency.
		 *
		 * @param string $freq daily or weekly.
		 * @return bool
		 */
		private static function has_queued( $freq ) {
			foreach ( self::queue() as $entry ) {
				if ( isset( $entry['freq'] ) && $freq === $entry['freq'] ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Next 08:00 site time (weekly: next Monday 08:00), as a timestamp.
		 *
		 * @param bool $weekly Weekly.
		 * @return int
		 */
		private static function next_time( $weekly ) {
			try {
				$tz   = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
				$now  = new DateTimeImmutable( 'now', $tz );
				$next = $now->setTime( 8, 0 );
				if ( $weekly ) {
					$next = $now->modify( 'monday this week' )->setTime( 8, 0 );
					if ( $next <= $now ) {
						$next = $next->modify( '+1 week' );
					}
				} elseif ( $next <= $now ) {
					$next = $next->modify( '+1 day' );
				}
				return $next->getTimestamp();
			} catch ( Exception $e ) {
				return time() + ( $weekly ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
			}
		}
	}
}
