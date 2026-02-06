<?php
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
        global $wpdb;

        $table = $wpdb->prefix . 'wpbot_chat_report';

        $user_id        = intval($_POST['user_id']);
        $conversation_id= intval($_POST['conversation_id']);
        $message        = sanitize_text_field($_POST['message']);
        $feedback       = sanitize_text_field($_POST['feedback']); // "like" or "dislike"
        $meta_info      = sanitize_textarea_field($_POST['meta_info']);
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
            global $wpdb;
            $table_report = $wpdb->prefix . 'wpbot_chat_report';

            $email       = sanitize_email( $_POST['email'] );
            $message     = sanitize_textarea_field( $_POST['message'] );
            $report_text = sanitize_textarea_field( $_POST['report_text'] );

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


    

}

/**
 * Instantiate the class
 */
Qcld_WPBot_Common_Functions::instance();
