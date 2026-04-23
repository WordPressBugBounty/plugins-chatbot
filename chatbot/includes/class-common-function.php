<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Common functions class
 */
class Qcld_WPBot_Common_Functions {


    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wpbot_save_feedback', array( $this, 'wpbot_save_feedback') );
        add_action('wp_ajax_nopriv_wpbot_save_feedback', array( $this,'wpbot_save_feedback') );
        add_action( 'wp_ajax_wpbot_save_report', array( $this, 'wpbot_save_report') );
        add_action( 'wp_ajax_nopriv_wpbot_save_report', array( $this, 'wpbot_save_report') );
        add_action('wp_ajax_nopriv_get_relevant_pages', array($this, 'qcld_get_relevant_pages_ajax'));
		add_action('wp_ajax_rate_limit_settings_option', array($this, 'rate_limit_settings_option_callback'));
        register_activation_hook(__FILE__, array('qcld_wpopenai_addons', 'schedule_rate_limit_reset_events'));
        add_filter('cron_schedules', array($this, 'qcld_user_role_rate_cron_schedule'));
		add_action('rate_limit_checker', array($this, 'rate_limit_checker'));
        add_action('qcld_openai_user_rate_cal', array($this, 'qcld_openai_user_rate_cal'));
		add_action('reset_rate_limit_used_counts', [$this, 'reset_rate_limit_used_counts'], 10, 1);

    }
    /**
     * Remove stopwords from search query
     * 
     * @param string $query The search query
     * @param array $stopwords Array of stopwords to remove
     * @return string Query with stopwords removed
     */
    public static function qcpd_remove_wa_stopwords($query, $stopwords) {
        return preg_replace('/\b('.implode('|',$stopwords).')\b/','',$query);
    }

    /**
     * Get relevant page links based on search query
     * 
     * @param string $search_query The search query
     * @return array Array of relevant page links
     */
    public static function qcpd_relevant_pagelink($search_query) {
        $stopwords = explode(',', get_option('qlcd_wp_chatbot_stop_words'));
        
        $finalQueryWordsWithoutStopWords = self::qcpd_remove_wa_stopwords(strtolower($search_query), $stopwords);
        
        $cleanWordsWithoutPunctuationMarks = preg_replace('/[\p{P}]/u', '', $finalQueryWordsWithoutStopWords);
        
        $q = trim($cleanWordsWithoutPunctuationMarks);
        
        $links = [];
        
        $post_type_array = get_option('qcld_openai_relevant_post');
        
        $the_query = new WP_Query(array(
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => esc_attr($q),
            'post_type' => $post_type_array
        ));
        
        if($the_query->have_posts()) {
            while($the_query->have_posts()) {
                $the_query->the_post();
                
                $url = esc_url(get_permalink());
                $link = '<a href=' . $url . ' target="_blank">' . get_the_title() . '</a>';
                array_push($links, $link);
            }
            wp_reset_postdata();
        }
        
        $links = array_unique($links);
        return $links;
    }


    public function wpbot_save_feedback() {
        check_ajax_referer('wp_chatbot', 'nonce');
        global $wpdb;

        $table = $wpdb->prefix . 'wpbot_chat_report';

        $user_id        = intval(wp_unslash($_POST['user_id']));
        $conversation_id= intval(wp_unslash($_POST['conversation_id']));
        $message        = sanitize_text_field(wp_unslash($_POST['message']));
        $feedback       = sanitize_text_field(wp_unslash($_POST['feedback'])); // "like" or "dislike"
        $meta_info      = sanitize_textarea_field(wp_unslash($_POST['meta_info']));
        $date           = current_time('mysql');

        $inserted = $wpdb->insert($table, array(
            'user_id'        => $user_id,
            'conversation_id'=> $conversation_id,
            'message'        => $message,
            'feedback'       => $feedback,
            'meta_info'      => $meta_info,
            'created_at'     => $date
        ));

        if ($inserted !== false) {
            wp_send_json_success(array('message' => 'Feedback saved.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save feedback.'));
        }
    }

    /**
     * Save report func
     * 
     */


        public function wpbot_save_report() {
            check_ajax_referer('wp_chatbot', 'nonce');
            global $wpdb;
            $table_report = $wpdb->prefix . 'wpbot_chat_report';

            $email       = sanitize_email(wp_unslash($_POST['email']));
            $message     = sanitize_textarea_field(wp_unslash($_POST['message']));
            $report_text = sanitize_textarea_field(wp_unslash($_POST['report_text']));

            $wpdb->insert(
                $table_report,
                [
                    'user_id' => get_current_user_id(), // or match from wpbot_user.
                    'message' => $message,
                    'meta_info' => maybe_serialize( [ 'email' => $email, 'report_text' => $report_text ] ),
                ],
                [ '%d', '%s', '%s' ]
            );

            // Send report email to admin
            $admin_email = get_option( 'admin_email' );
            $subject = "New Chat Report";
            $body = "Reported Message:\n{$message}\n\nReport Text:\n{$report_text}\n\nUser Email: {$email}";
            wp_mail( $admin_email, $subject, $body );

            wp_send_json_success();
        }
        public function rate_limit_settings_option_callback()
		{
			// Check is admin and verify nonce
			if (! current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
				wp_die();
			}
			check_ajax_referer( 'wp_chatbot_admin', 'nonce' );
			// Save the rate limiting enabled/disabled setting
			if (isset($_POST['is_rate_limiting_enabled'])) {
				$is_rate_limiting_enabled = intval( wp_unslash( $_POST['is_rate_limiting_enabled'] ) );
				update_option('is_rate_limiting_enabled', $is_rate_limiting_enabled);
			}
			// Save the rate limits for each role
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if (isset($_POST['rate_limits']) && is_array( $_POST['rate_limits'] )) {
				$rate_limits = array_map( 'sanitize_text_field', wp_unslash( $_POST['rate_limits'] ) );
				foreach ($rate_limits as $role => $limit) {
					$option_name = 'rate_limit_' . sanitize_key( $role );
					$limit_value = intval($limit);
					update_option($option_name, $limit_value);
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$timeframe_raw = isset( $_POST['rate_limit_timeframes'][ $role ] ) ? sanitize_text_field( wp_unslash( $_POST['rate_limit_timeframes'][ $role ] ) ) : '24';
					$timeframe = intval($timeframe_raw) * 3600; // Convert hours to seconds
					update_option('rate_limit_timeframe_' . $role, $timeframe);
				}
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$guest_timeframe_raw = isset( $_POST['rate_limit_timeframes']['guest'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_limit_timeframes']['guest'] ) ) : '24';
				$timeframe = intval($guest_timeframe_raw) * 3600; // Convert hours to seconds
				update_option('rate_limit_timeframe_guest', $timeframe);
			}
			// In your rate_limit_settings_option_callback(), after updating options:
			$this->schedule_rate_limit_reset_events();
			wp_send_json_success('Settings saved');
			wp_die();
		}
        public function qcld_user_role_rate_cron_schedule($schedules)
		{
			$roles = wp_roles()->roles;
			foreach ($roles as $role => $details) {
				$timeframe =  intval(get_option('rate_limit_timeframe_' . $role, true));
				// Minimum 3600 seconds
				$schedules['session_schedules_rate_limit_' . $role] = array(
					'interval' => $timeframe,
					'display'  => esc_html( 'session_schedules_rate_limit_' . $role ),
				);
				// Schedule the cron job for each role if not already scheduled);
			}
			return $schedules;
		}
        // wpshedule event to reset the rate limit used counts
		public function schedule_rate_limit_reset_events()
		{
			// Ensure WordPress cron functions are available
			if (! function_exists('wp_schedule_event')) {
				require_once ABSPATH . 'wp-includes/wp-cron.php';
			}
			$roles = wp_roles()->roles;
			foreach ($roles as $role => $d) {
				// Unschedule all existing events for this role
				$timestamp = wp_next_scheduled('reset_rate_limit_used_counts', array($role));
				while ($timestamp) {
					wp_unschedule_event($timestamp, 'reset_rate_limit_used_counts', array($role));
					$timestamp = wp_next_scheduled('reset_rate_limit_used_counts', array($role));
				}
				// Schedule new event with updated interval
				wp_schedule_event(time(), 'session_schedules_rate_limit_' . $role, 'reset_rate_limit_used_counts', array($role));
			}
		}
        public function qcld_wpsession_cron_deactivation()
		{
			$timestamp = wp_next_scheduled('reset_rate_limit_used_counts');
			wp_unschedule_event($timestamp, 'reset_rate_limit_used_counts');
		}
        public function rate_limit_checker()
		{
			$qlcd_wp_chatbot_ai_rate_limiting_message = qcld_wb_chatbot_func_str_replace(
				maybe_unserialize(get_option('qlcd_wp_chatbot_ai_rate_limiting_message', true))
			);
			if (get_option('is_stream_enabled') == 1) {
				$response = array(
					'status'  => 'error',
					'message' => esc_html__((is_array($qlcd_wp_chatbot_ai_rate_limiting_message) && !empty($qlcd_wp_chatbot_ai_rate_limiting_message[0]) ? $qlcd_wp_chatbot_ai_rate_limiting_message[0] : 'Rate limit exceeded. Please try again later.'), 'chatbot'),

				);
			} else {
				$response = array(
					'status'  => 'success',
					'message' => esc_html__((is_array($qlcd_wp_chatbot_ai_rate_limiting_message) && !empty($qlcd_wp_chatbot_ai_rate_limiting_message[0]) ? $qlcd_wp_chatbot_ai_rate_limiting_message[0] : 'Rate limit exceeded. Please try again later.'), 'chatbot'),
				);
			}
			if (is_user_logged_in()) {
				$user = wp_get_current_user();
				if (! empty($user->roles) && is_array($user->roles)) {
					$role        = $user->roles[0];
					$option_name = 'rate_limit_' . $role;
					$limit       = get_option($option_name);
					$rate_limit  = get_user_meta(get_current_user_id(), 'qcld_openai_user_rate_limit', true);
					if (($limit >= $rate_limit) || $limit == 0) {
						return; // allowed
					} else {
						if (get_option('is_stream_enabled') == 1) {
							wp_send_json($response); //Proper JSON with header.
						} else {
							echo json_encode($response);
							wp_die();
						}
					}
				}
			} else {
				$option_name = 'rate_limit_guest';
				$limit       = get_option($option_name);

				if (session_status() == PHP_SESSION_NONE) {
					session_start();
				}

				$guest_rate_limit = isset($_SESSION['guest_rate_limit']) ? intval($_SESSION['guest_rate_limit']) : 0;

				if ($limit >= $guest_rate_limit || $limit == 0) {
					return; // allowed
				} else {
					if (get_option('is_stream_enabled') == 1) {
						wp_send_json($response); //Proper JSON with header.
					} else {
						echo json_encode($response);
						// Return role if limit exceeded
						wp_die();
					}
				}
			}
		}

        public function reset_rate_limit_used_counts($role)
		{
			if (empty($role)) {
				return;
			}
			// Resetting rate limit used counts for role.
			// loop through each role
			// for each role get all users with that role
			$roles = wp_roles()->roles;
			foreach ($roles as $list_roll => $details) {
				if ($list_roll != $role) {
					continue;
				} else {
					$option_name = 'rate_limit_' . $role;
					$limit = get_option($option_name); // Default to  if not set
					$users = get_users(array('role' => $role));
					foreach ($users as $user) {
						update_user_meta($user->ID, 'qcld_openai_user_rate_limit', 0);
					}
				}
			}
		}
        public function qcld_openai_user_rate_cal($update)
		{
			if (is_user_logged_in()) {
				$rate_limit = get_user_meta(get_current_user_id(), 'qcld_openai_user_rate_limit', true);
				if ($update != '') {
					$rate_limit = intval($rate_limit) + intval($update);
				}
				update_user_meta(get_current_user_id(), 'qcld_openai_user_rate_limit', $rate_limit);
				return;
			} else {
				// for guest users we will use session to store the rate limit
				// if session is not started then start it
				if (session_status() == PHP_SESSION_NONE) {
					session_start();
				}
				// we will use guest_id to identify the guest user
				if (! isset($_SESSION['guest_id'])) {
					$user_ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
					$session_id = md5($user_ip . time());
					$_SESSION['guest_id'] = $session_id;
					$_SESSION['guest_id_time'][$session_id] = time();
					get_option('rate_', 10);
					// if guest_id_time is set and it is older than 24 hours then unset the guest_id
					// we will store the time when the guest_id was created in session
					$timeframe = get_option('rate_limit_timeframe_guest', true);
					$timeframe = intval($timeframe) * 3600; // convert hours to seconds
					if (isset($_SESSION['guest_id_time'][$session_id]) && (time() - $_SESSION['guest_id_time'][$session_id]) > $timeframe) {
						unset($_SESSION['guest_id']);
						unset($_SESSION['guest_id_time']);
					}
					// we will store the time when the guest_id was created in session
					$_SESSION['guest_id_time'][$session_id] = time();
					$_SESSION['guest_rate_limit'] = 0;
				}
				// update the rate limit
				// if update is not empty then add it to the existing rate limit else set it
				if ($update != '') {
					$guest_rate_limit = isset($_SESSION['guest_rate_limit']) ? intval($_SESSION['guest_rate_limit']) : 0;
					// add the update to the existing rate limit	
					// set the rate limit in session
					$_SESSION['guest_rate_limit'] =  $guest_rate_limit + $update;
					return;
				}
			}
		}

}

/**
 * Instantiate the class
 */
Qcld_WPBot_Common_Functions::instance();
