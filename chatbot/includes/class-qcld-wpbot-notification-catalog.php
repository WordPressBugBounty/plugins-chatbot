<?php
/**
 * Every email WPBot Pro sends, grouped by feature (inventory of 23 November 2026: all wp_mail()
 * calls in the plugin, its synced modules and its add-ons).
 *
 * Each notification:
 * - label, to (who gets it), group;
 * - files: plugin-relative files; the email is this notification when one of them is in the call
 *   stack of wp_mail() (the innermost file wins);
 * - fn: functions or methods ("Class::method" or "function", matched at the end) that pick this
 *   notification first, for shared helpers whose file alone does not tell the emails apart;
 * - digest: whether a daily or weekly digest makes sense (event emails yes, scheduled reports no);
 * - locked: why the email is always sent instantly (emails to visitors and customers, emails the
 *   owner sends on purpose);
 * - instant: the note shown for an email that can be switched off but not digested.
 *
 * Filter qcld_wpbot_notification_catalog to add or change notifications (add-ons, other hosts).
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Notification_Catalog' ) ) {

	/**
	 * The notifications.
	 */
	final class QCLD_WPBot_Notification_Catalog {

		/**
		 * Fallback for a WPBot email no notification matches.
		 */
		const OTHER = 'other_wpbot';

		/**
		 * Built catalog for this request.
		 *
		 * @var array|null
		 */
		private static $groups = null;

		/**
		 * Item index: id => item (with its group id).
		 *
		 * @var array|null
		 */
		private static $items = null;

		/**
		 * Groups with their notifications.
		 *
		 * @return array<string,array{label:string,about:string,items:array}>
		 */
		public static function groups() {
			if ( null === self::$groups ) {
				/**
				 * WPBot's notifications, by feature.
				 *
				 * @param array $groups gid => [ label, about, items => [ nid => item ] ].
				 */
				$groups       = (array) apply_filters( 'qcld_wpbot_notification_catalog', self::build() );
				self::$groups = array();
				self::$items  = array();
				foreach ( $groups as $gid => $group ) {
					if ( ! is_array( $group ) || empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
						continue;
					}
					$gid   = sanitize_key( (string) $gid );
					$clean = array(
						'label' => isset( $group['label'] ) ? (string) $group['label'] : $gid,
						'about' => isset( $group['about'] ) ? (string) $group['about'] : '',
						'items' => array(),
					);
					foreach ( $group['items'] as $nid => $item ) {
						$nid = sanitize_key( (string) $nid );
						if ( ! is_array( $item ) || '' === $nid || isset( self::$items[ $nid ] ) ) {
							continue;
						}
						$item                   = array_merge(
							array(
								'label'   => $nid,
								'to'      => '',
								'files'   => array(),
								'fn'      => array(),
								'digest'  => false,
								'locked'  => '',
								'instant' => '',
							),
							$item
						);
						$item['group']          = $gid;
						$clean['items'][ $nid ] = $item;
						self::$items[ $nid ]    = $item;
					}
					if ( $clean['items'] ) {
						self::$groups[ $gid ] = $clean;
					}
				}
			}
			return self::$groups;
		}

		/**
		 * One notification, or null.
		 *
		 * @param string $nid Notification id.
		 * @return array|null
		 */
		public static function item( $nid ) {
			self::groups();
			return isset( self::$items[ (string) $nid ] ) ? self::$items[ (string) $nid ] : null;
		}

		/**
		 * Forget the built catalog (tests, after the language changes).
		 *
		 * @return void
		 */
		public static function reset() {
			self::$groups = null;
			self::$items  = null;
		}

		/**
		 * Which notification an email is.
		 *
		 * @param array $stack files => plugin-relative files (innermost first), fns => functions and
		 *                     "Class::method" names of the call stack.
		 * @param array $atts  The email.
		 * @return string Notification id.
		 */
		public static function classify( array $stack, array $atts = array() ) {
			$files = isset( $stack['files'] ) ? (array) $stack['files'] : array();
			$fns   = isset( $stack['fns'] ) ? (array) $stack['fns'] : array();
			$nid   = self::match( $files, $fns );
			/**
			 * Which notification a WPBot email is.
			 *
			 * @param string $nid   Notification id.
			 * @param array  $stack files and fns of the call stack.
			 * @param array  $atts  The email.
			 */
			$nid = (string) apply_filters( 'qcld_wpbot_notification_classify', $nid, $stack, $atts );
			return null !== self::item( $nid ) ? $nid : self::OTHER;
		}

		/**
		 * Match files and functions to a notification.
		 *
		 * @param string[] $files Plugin-relative files, innermost first.
		 * @param string[] $fns   Functions and methods.
		 * @return string
		 */
		public static function match( array $files, array $fns ) {
			$groups = self::groups();
			// 1. Functions first (shared helpers).
			foreach ( $groups as $group ) {
				foreach ( $group['items'] as $nid => $item ) {
					foreach ( (array) $item['fn'] as $want ) {
						foreach ( $fns as $fn ) {
							if ( self::ends_with( (string) $fn, (string) $want ) ) {
								return $nid;
							}
						}
					}
				}
			}
			// 2. The innermost file that belongs to a notification.
			foreach ( $files as $file ) {
				$file = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
				foreach ( $groups as $group ) {
					foreach ( $group['items'] as $nid => $item ) {
						foreach ( (array) $item['files'] as $want ) {
							if ( $file === $want || self::ends_with( $file, '/' . $want ) ) {
								return $nid;
							}
						}
					}
				}
			}
			return self::OTHER;
		}

		/**
		 * Whether $haystack ends with $needle (a "Class::method" also matches a namespaced class).
		 *
		 * @param string $haystack Value.
		 * @param string $needle   End.
		 * @return bool
		 */
		private static function ends_with( $haystack, $needle ) {
			if ( '' === $needle || strlen( $needle ) > strlen( $haystack ) ) {
				return false;
			}
			if ( substr( $haystack, -strlen( $needle ) ) !== $needle ) {
				return false;
			}
			$before = strlen( $haystack ) === strlen( $needle ) ? '' : substr( $haystack, -strlen( $needle ) - 1, 1 );
			return '' === $before || '\\' === $before || '/' === $before;
		}

		/**
		 * The inventory.
		 *
		 * @return array
		 */
		private static function build() {
			$visitor  = __( 'Goes to your visitor: always sent right away.', 'chatbot' );
			$customer = __( 'Goes to your customer: always sent right away.', 'chatbot' );
			$asked    = __( 'Sent when you ask for it.', 'chatbot' );
			$schedule = __( 'Already arrives on its own schedule.', 'chatbot' );
			$staff    = __( 'You (notification emails)', 'chatbot' );
			$mixed    = __( 'Ticket staff; copies to the customer are always sent', 'chatbot' );
			$tickets  = 'addons/support-ticket-kbx/includes/actions/';

			return array(
				'chatbot'   => array(
					'label' => __( 'Chatbot and leads', 'chatbot' ),
					'about' => __( 'Messages, requests and form answers visitors leave in the chatbot.', 'chatbot' ),
					'items' => array(
						'chatbot_support_request' => array(
							'label'  => __( 'Support request from the chatbot', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'qcld_wb_chatbot_support_email' ),
							'digest' => true,
						),
						'chatbot_callback'        => array(
							'label'  => __( 'Call-back request (phone number)', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'qcld_wb_chatbot_support_phone' ),
							'digest' => true,
						),
						'chatbot_query'           => array(
							'label'  => __( '"Send us your query" details', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'qcld_wb_chatbot_send_query' ),
							'digest' => true,
						),
						'chatbot_ai_form'         => array(
							'label'  => __( 'AI Actions form submissions', 'chatbot' ),
							'to'     => __( 'You, or the addresses set on the AI Action', 'chatbot' ),
							'files'  => array( 'includes/chatbot/src/Leads/Ai_Forms.php', 'includes/class-common-function.php', 'includes/integration/openai/qcld-bot-openai.php' ),
							'fn'     => array( 'qcld_save_ai_form' ),
							'digest' => true,
						),
						'chatbot_report'          => array(
							'label'  => __( 'A visitor reported a chat message', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/chatbot/src/Leads/Leads.php' ),
							'fn'     => array( 'wpbot_save_report' ),
							'digest' => true,
						),
						'chatbot_voice'           => array(
							'label'  => __( 'New voice message', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'addons/voice-message-addon/includes/voice-functions.php' ),
							'digest' => true,
						),
						'chatbot_channels'        => array(
							'label'  => __( 'Feedback and orders from Messenger, Instagram, WhatsApp and Telegram', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'addons/messenger-chatbot-addon/wpbot-fb-messenger-functions.php', 'addons/messenger-chatbot-addon/instagram-chatbot-addon/wpbot-ig-instagram-functions.php', 'addons/telegram-chatbot-addon/includes/class-helper.php', 'addons/whatsapp-chatbot-addon/includes/functions.php' ),
							'digest' => true,
						),
						'chatbot_verification'    => array(
							'label'  => __( 'Email verification code', 'chatbot' ),
							'to'     => __( 'Visitor', 'chatbot' ),
							'files'  => array( 'includes/chatbot/src/Visitor/Verification.php' ),
							'locked' => $visitor,
						),
						'chatbot_subscription'    => array(
							'label'  => __( 'Email subscription offer', 'chatbot' ),
							'to'     => __( 'Visitor', 'chatbot' ),
							'fn'     => array( 'qcld_wb_chatbot_email_subscription' ),
							'locked' => $visitor,
						),
					),
				),
				'sessions'  => array(
					'label' => __( 'Chat sessions and reports', 'chatbot' ),
					'about' => __( 'New chat sessions, session analysis and the weekly bot report.', 'chatbot' ),
					'items' => array(
						'sessions_new'        => array(
							'label'  => __( 'New chat session started', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'wpbot_send_delayed_session_email_notification' ),
							'digest' => true,
						),
						'sessions_analysis'   => array(
							'label'   => __( 'Scheduled AI analysis of chat sessions', 'chatbot' ),
							'to'      => $staff,
							'fn'      => array( 'qcld_wpsession_mysql_scraper_function' ),
							'instant' => $schedule,
						),
						'sessions_weekly'     => array(
							'label'   => __( 'Weekly bot report', 'chatbot' ),
							'to'      => $staff,
							'files'   => array( 'addons/chat-session-addon/reports/reporting-helper-functions.php' ),
							'instant' => $schedule,
						),
						'sessions_manual'     => array(
							'label'  => __( 'Session analysis you run by hand', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'qcld_chatbot_session_mannual_scraper' ),
							'locked' => $asked,
						),
						'sessions_to_visitor' => array(
							'label'  => __( 'Transcripts and replies to visitors', 'chatbot' ),
							'to'     => __( 'Visitor, or the address typed', 'chatbot' ),
							'fn'     => array( 'wpcs_send_email', 'wpbot_send_email_transcript', 'forward_session_to_email', 'wpbot_send_reply_email' ),
							'locked' => $visitor,
						),
					),
				),
				'livechat'  => array(
					'label' => __( 'Live chat', 'chatbot' ),
					'about' => __( 'Live chat sends emails to visitors only; agents are told in the inbox and the browser.', 'chatbot' ),
					'items' => array(
						'livechat_reply'      => array(
							'label'  => __( 'Agent replied (visitor had left)', 'chatbot' ),
							'to'     => __( 'Visitor', 'chatbot' ),
							'files'  => array( 'includes/chatbot/src/Live/Push.php' ),
							'locked' => $visitor,
						),
						'livechat_transcript' => array(
							'label'  => __( 'Chat transcript', 'chatbot' ),
							'to'     => __( 'Visitor, or the address the agent types', 'chatbot' ),
							'files'  => array( 'includes/chatbot/src/Live/Assistant.php' ),
							'locked' => $visitor,
						),
					),
				),
				'forms'     => array(
					'label' => __( 'Conversational forms', 'chatbot' ),
					'about' => __( 'Answers to forms filled in through the chatbot or on your pages.', 'chatbot' ),
					'items' => array(
						'forms_autoresponse' => array(
							'label'  => __( 'Auto-response to the person who filled in the form', 'chatbot' ),
							'to'     => __( 'Visitor', 'chatbot' ),
							'fn'     => array( 'qcld_wb_chatbot_send_autoresponse', 'Qcformbuilder_Forms::send_auto_response' ),
							'locked' => $visitor,
						),
						'forms_submission'   => array(
							'label'  => __( 'New form submission', 'chatbot' ),
							'to'     => __( 'You, or the form\'s recipients', 'chatbot' ),
							'fn'     => array( 'qcld_wb_chatbot_send_form_query' ),
							'files'  => array( 'addons/conversational-forms-pro/classes/save.php', 'addons/conversational-forms-pro/qcformbuilder-core.php', 'addons/conversational-forms-pro/classes/core.php' ),
							'digest' => true,
						),
					),
				),
				'tickets'   => array(
					'label' => __( 'Support tickets', 'chatbot' ),
					'about' => __( 'Ticket emails to you and your agents. Emails to the customer who opened the ticket are always sent.', 'chatbot' ),
					'items' => array(
						'tickets_created'  => array(
							'label'  => __( 'New ticket', 'chatbot' ),
							'to'     => $mixed,
							'files'  => array( $tickets . 'en_ticket_created.php' ),
							'digest' => true,
						),
						'tickets_reply'    => array(
							'label'  => __( 'New reply on a ticket', 'chatbot' ),
							'to'     => $mixed,
							'files'  => array( $tickets . 'en_submit_reply.php' ),
							'digest' => true,
						),
						'tickets_note'     => array(
							'label'  => __( 'Internal note added', 'chatbot' ),
							'to'     => __( 'Ticket staff', 'chatbot' ),
							'files'  => array( $tickets . 'en_submit_note.php' ),
							'digest' => true,
						),
						'tickets_assigned' => array(
							'label'  => __( 'Ticket assigned to an agent', 'chatbot' ),
							'to'     => __( 'Agents', 'chatbot' ),
							'files'  => array( $tickets . 'en_assign_agent.php' ),
							'digest' => true,
						),
						'tickets_changed'  => array(
							'label'  => __( 'Status, priority or category changed, ticket deleted', 'chatbot' ),
							'to'     => $mixed,
							'files'  => array( $tickets . 'en_change_status.php', $tickets . 'en_change_priority.php', $tickets . 'en_change_category.php', $tickets . 'en_delete_ticket.php' ),
							'digest' => true,
						),
						'tickets_queued'   => array(
							'label'  => __( 'Ticket emails sent later by the ticket queue', 'chatbot' ),
							'to'     => $mixed,
							'files'  => array( $tickets . 'qcld_email_notifications.php' ),
							'digest' => true,
						),
						'tickets_customer' => array(
							'label'  => __( 'Ticket received, auto-closed and closed-ticket notices', 'chatbot' ),
							'to'     => __( 'Customer', 'chatbot' ),
							'files'  => array( 'addons/support-ticket-kbx/includes/admin/tickets/create_ticket/submit_ticket.php', 'addons/support-ticket-kbx/addons/wpsc-automatic-close-ticket/includes/atc_cron.php', 'addons/support-ticket-kbx/addons/kbx-support-email-piping/class-qcld-ep-function.php' ),
							'locked' => $customer,
						),
					),
				),
				'store'     => array(
					'label' => __( 'Store (WooCommerce)', 'chatbot' ),
					'about' => __( 'Returns, cart recovery and store reports.', 'chatbot' ),
					'items' => array(
						'store_return_new' => array(
							'label'  => __( 'New return request', 'chatbot' ),
							'to'     => __( 'Returns recipients', 'chatbot' ),
							'fn'     => array( 'Returns\Emails::admin_new' ),
							'digest' => true,
						),
						'store_recovery'   => array(
							'label'   => __( 'Cart recovery report', 'chatbot' ),
							'to'      => __( 'Cart recovery recipients', 'chatbot' ),
							'files'   => array( 'includes/chatbot-pro/src/Store/Recovery/Report.php' ),
							'instant' => $schedule,
						),
						'store_briefing'   => array(
							'label'   => __( 'Store briefing', 'chatbot' ),
							'to'      => $staff,
							'files'   => array( 'includes/chatbot-pro/src/Store/Insights/Briefing.php' ),
							'instant' => $schedule,
						),
						'store_customer'   => array(
							'label'  => __( 'Return updates, cart recovery, back-in-stock and retention emails', 'chatbot' ),
							'to'     => __( 'Customer', 'chatbot' ),
							'files'  => array( 'includes/chatbot-pro/src/Store/Returns/Emails.php', 'includes/chatbot-pro/src/Store/Recovery/Email.php', 'includes/chatbot-pro/src/Store/Growth/Mailer.php' ),
							'locked' => $customer,
						),
					),
				),
				'spend'     => array(
					'label' => __( 'AI usage and spending', 'chatbot' ),
					'about' => __( 'Budget and spending alerts, and the weekly spending summary.', 'chatbot' ),
					'items' => array(
						'spend_weekly' => array(
							'label'   => __( 'Weekly AI spending summary', 'chatbot' ),
							'to'      => __( 'Spending alert recipients', 'chatbot' ),
							'fn'      => array( 'Spend_Pro::weekly_summary' ),
							'instant' => $schedule,
						),
						'spend_alerts' => array(
							'label'  => __( 'Budget, limit and spending spike alerts', 'chatbot' ),
							'to'     => __( 'Spending alert recipients', 'chatbot' ),
							'files'  => array( 'includes/wpbot-core/src/Spend/Alerts.php', 'includes/chatbot-pro/src/Spend/Spend_Pro.php', 'includes/chatbot/src/Ai/Budget.php' ),
							'digest' => true,
						),
					),
				),
				'marketing' => array(
					'label' => __( 'AI Marketing', 'chatbot' ),
					'about' => __( 'Lead Finder, journeys and reports. Campaign emails to your contacts are not changed here.', 'chatbot' ),
					'items' => array(
						'marketing_hot_lead' => array(
							'label'  => __( 'Hot B2B lead', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/ai-marketing-pro/src/Leads/Inbound.php' ),
							'digest' => true,
						),
						'marketing_reply'    => array(
							'label'  => __( 'Lead Finder: a prospect replied with interest', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/ai-marketing-pro/src/Leads/Replies.php' ),
							'digest' => true,
						),
						'marketing_journey'  => array(
							'label'  => __( 'Journey step "Notify me"', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/ai-marketing-pro/src/Journeys/Steps.php' ),
							'digest' => true,
						),
						'marketing_paused'   => array(
							'label'  => __( 'Sending paused to protect your reputation', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/ai-marketing-pro/src/Sending/Safety.php' ),
							'digest' => true,
						),
						'marketing_report'   => array(
							'label'   => __( 'Weekly AI Marketing and Lead Finder reports', 'chatbot' ),
							'to'      => $staff,
							'files'   => array( 'includes/ai-marketing-pro/src/Insight/Report.php', 'includes/ai-marketing-pro/src/Leads/Report.php' ),
							'instant' => $schedule,
						),
						'marketing_contacts' => array(
							'label'  => __( 'Campaigns, journeys, confirmations and outreach', 'chatbot' ),
							'to'     => __( 'Your contacts and prospects', 'chatbot' ),
							'files'  => array( 'includes/ai-marketing/src/Sending/Mailer.php' ),
							'locked' => __( 'Goes to your contacts: sent as scheduled in AI Marketing.', 'chatbot' ),
						),
					),
				),
				'social'    => array(
					'label' => __( 'Social', 'chatbot' ),
					'about' => __( 'Posts that need you, approvals, the inbox and reports.', 'chatbot' ),
					'items' => array(
						'social_failed'   => array(
							'label'  => __( 'A post failed to publish', 'chatbot' ),
							'to'     => __( 'You, or the address set in Social', 'chatbot' ),
							'fn'     => array( 'Posts\Notifications::failed' ),
							'digest' => true,
						),
						'social_due'      => array(
							'label'  => __( 'Time to post by hand', 'chatbot' ),
							'to'     => __( 'The post\'s author', 'chatbot' ),
							'fn'     => array( 'Posts\Notifications::manual_due' ),
							'digest' => true,
						),
						'social_approval' => array(
							'label'  => __( 'Approvals: post sent for approval, approved or rejected', 'chatbot' ),
							'to'     => __( 'Approvers and authors', 'chatbot' ),
							'files'  => array( 'includes/social-pro/src/Team/Approval.php' ),
							'digest' => true,
						),
						'social_inbox'    => array(
							'label'  => __( 'A social message was assigned to you', 'chatbot' ),
							'to'     => __( 'The team member', 'chatbot' ),
							'files'  => array( 'includes/social-pro/src/Inbox/Inbox.php' ),
							'digest' => true,
						),
						'social_trend'    => array(
							'label'  => __( 'Trend alert', 'chatbot' ),
							'to'     => $staff,
							'fn'     => array( 'Trend_Alerts::alert' ),
							'digest' => true,
						),
						'social_reports'  => array(
							'label'   => __( 'Social media report and weekly trend ideas', 'chatbot' ),
							'to'      => $staff,
							'files'   => array( 'includes/social-pro/src/Analytics/Analytics_Screen.php', 'includes/social-pro/src/Trends/Trend_Alerts.php' ),
							'instant' => $schedule,
						),
					),
				),
				'seo'       => array(
					'label' => __( 'SEO AI Search', 'chatbot' ),
					'about' => __( 'SEO alerts, outreach reminders and search reports.', 'chatbot' ),
					'items' => array(
						'seo_alerts'   => array(
							'label'  => __( 'SEO alerts (rankings, broken links, 404s, audits, reviews)', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/seo-ai-search-pro/src/Automation/Alerts.php' ),
							'digest' => true,
						),
						'seo_followup' => array(
							'label'  => __( 'Outreach follow-ups due', 'chatbot' ),
							'to'     => $staff,
							'files'  => array( 'includes/seo-ai-search-pro/src/Links/Link_Watch.php' ),
							'digest' => true,
						),
						'seo_reports'  => array(
							'label'   => __( 'Weekly search report and white-label report', 'chatbot' ),
							'to'      => __( 'Report recipients', 'chatbot' ),
							'files'   => array( 'includes/seo-ai-search/src/Reports/Weekly_Email.php', 'includes/seo-ai-search-pro/src/Reach/White_Label.php' ),
							'instant' => $schedule,
						),
						'seo_customer' => array(
							'label'  => __( 'Review requests and link outreach', 'chatbot' ),
							'to'     => __( 'Customers and site owners you contact', 'chatbot' ),
							'files'  => array( 'includes/seo-ai-search-pro/src/Local/Reviews_Pro.php', 'includes/class-wpbot-pro-seo-ai-search-adapter.php' ),
							'locked' => $customer,
						),
					),
				),
				'writer'    => array(
					'label' => __( 'AI Writer', 'chatbot' ),
					'about' => __( 'Topic ideas for your next posts.', 'chatbot' ),
					'items' => array(
						'writer_topics' => array(
							'label'   => __( 'Weekly topic ideas', 'chatbot' ),
							'to'      => $staff,
							'files'   => array( 'includes/ai-writer/src/Topics/Weekly_Email.php' ),
							'instant' => $schedule,
						),
					),
				),
				'insights'  => array(
					'label' => __( 'Site Insight', 'chatbot' ),
					'about' => __( 'Your traffic report.', 'chatbot' ),
					'items' => array(
						'insights_report' => array(
							'label'   => __( 'Weekly or monthly traffic report', 'chatbot' ),
							'to'      => __( 'Report recipients', 'chatbot' ),
							'files'   => array( 'includes/insights/src/Reports/Report_Mailer.php' ),
							'instant' => $schedule,
						),
					),
				),
				'other'     => array(
					'label' => __( 'Automations and other emails', 'chatbot' ),
					'about' => __( 'Automator rules that email you or your team, and any other WPBot email.', 'chatbot' ),
					'items' => array(
						'automator_email' => array(
							'label'  => __( 'Automator "Send email" actions to staff', 'chatbot' ),
							'to'     => __( 'The addresses in the rule', 'chatbot' ),
							'files'  => array( 'addons/automator/includes/actions/email-actions.php' ),
							'digest' => true,
						),
						self::OTHER       => array(
							'label'  => __( 'Other WPBot notifications to staff', 'chatbot' ),
							'to'     => $staff,
							'digest' => true,
						),
						'support_sent'    => array(
							'label'  => __( 'Support reports and contact forms you send to WPBot', 'chatbot' ),
							'to'     => __( 'WPBot support', 'chatbot' ),
							'files'  => array( 'includes/wpbot-core/src/Diagnostics/Support_Report.php', 'qc-support-promo-page/class-qc-support-promo-page.php', 'addons/automator/includes/core/plugin.php' ),
							'locked' => $asked,
						),
					),
				),
			);
		}
	}
}
