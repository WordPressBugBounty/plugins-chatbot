<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('qcld_wpopenrouter_addons')){


    /**
     * Main Class.
     */
    final class qcld_wpopenrouter_addons
    {
        private $id = 'Open AI';

        /**
         * WPBot Pro version.
         *
         * @var string
         */
        public $version = '1.0.6';
        
        /**
         * WPBot Pro helper.
         *
         * @var object
         */
        public $helper;

        /**
         * The single instance of the class.
         *
         * @var qcld_wb_Chatbot
         * @since 1.0.0
         */
        protected static $_instance = null;
        
        /**
         * Main wpbot Instance.
         *
         * Ensures only one instance of wpbot is loaded or can be loaded.
         *
         * @return qcld_wb_Chatbot - Main instance.
         * @since 1.0.0
         * @static
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        public $response_list;

        /**
         *  Constructor
         */
        public function __construct()
        {

            $this->includes();
            add_action('wp_ajax_openai_save_assistant', [$this, 'save_assistant_callback']);
            add_action('wp_ajax_openrouter_response',[$this,'openrouter_response_callback']);
            add_action('wp_ajax_nopriv_openrouter_response', [$this, 'openrouter_response_callback']);
            add_action('wp_ajax_qcld_openrouter_settings_option',[$this,'qcld_openrouter_settings_option_callback']);

            add_action('wp_ajax_update_settings_option', [$this, 'update_settings_option_callback']);

            if (is_admin() && !empty($_GET["page"]) && (($_GET["page"] == "openai-panel_dashboard") || ($_GET["page"] == "openai-panel_file") || ($_GET["page"] == "openai-panel_help"))) {
                add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_admin_scripts'));
            }
            //add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_openrouter_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_openrouter_admin_scripts'));
        }



        public function qcld_wb_chatbot_openrouter_admin_scripts() {
              if ( ! current_user_can( 'manage_options' ) ) {
                    return ;
                }
            wp_register_script(
                'qcld-wp-chatbot-openrouter-admin-js', 
                QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/openrouter/assets/js/qcld-wp-openrouter-admin.js', 
                array('jquery'), 
                QCLD_wpCHATBOT_VERSION, 
                true
            );

            // Localize the script with necessary data
            wp_localize_script('qcld-wp-chatbot-openrouter-admin-js', 'ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('wp_chatbot'),
                'openrouter_api_key' => get_option('qcld_openrouter_api_key'),
                'openrouter_model' => get_option('qcld_openrouter_model'),
                'openrouter_enabled' => get_option('qcld_openrouter_enabled'),
                'qcld_openrouter_append_content' => get_option('qcld_openrouter_append_content'),
                'qcld_openrouter_prepend_content' => get_option('qcld_openrouter_prepend_content')
            ));
            
            wp_enqueue_script('qcld-wp-chatbot-openrouter-admin-js');
        }
        
        /**
         * Define wpbot Constants.
         *
         * @return void
         * @since 1.0.0
         */
        public function includes() {
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/Parsedown.php" );
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/class-common-function.php" );
        }
        public function qcld_openrouter_settings_option_callback() {
                $nonce = sanitize_text_field($_POST['nonce']);
                if (!wp_verify_nonce($nonce, 'wp_chatbot')) {
                    wp_send_json(array('success' => false, 'msg' => esc_html__('Failed in Security check', 'sm')));
                    wp_die();
                } else {
                    $openrouter_api_key = sanitize_text_field($_POST['openrouter_api_key']);
                    $openrouter_model = sanitize_text_field($_POST['openrouter_model']);
                    $openrouter_enabled = sanitize_text_field($_POST['openrouter_enabled']);
                    $qcld_openrouter_page_suggestion_enabled = sanitize_text_field($_POST['qcld_openrouter_page_suggestion_enabled']);
                    $qcld_openrouter_append_content = sanitize_text_field($_POST['qcld_openrouter_append_content']) ?? '';
                    $qcld_openrouter_prepend_content = sanitize_text_field($_POST['qcld_openrouter_prepend_content']) ?? '';
                    if($openrouter_api_key != '') {
                        update_option('qcld_openrouter_api_key', $openrouter_api_key);
                    }
                    if($openrouter_model != '') {
                        update_option('qcld_openrouter_model', $openrouter_model);
                    }
                    if($openrouter_enabled != '') {
                        update_option('qcld_openrouter_enabled', $openrouter_enabled);
                    }
                    if($openrouter_enabled == '1') {
                        update_option('ai_enabled', 0);
                    
                    } else {
                        update_option('ai_enabled', 1);
                    }
                    update_option('qcld_openrouter_page_suggestion_enabled', $qcld_openrouter_page_suggestion_enabled);
                    update_option( 'qcld_openai_relevant_post', $_POST['openai_post_type'] );
                    
                    update_option('qcld_openrouter_append_content', $qcld_openrouter_append_content);
                    update_option('qcld_openrouter_prepend_content', $qcld_openrouter_prepend_content);
                    
                }
                echo json_encode($openrouter_enabled);
                wp_die();
        }
        public function openrouter_response_callback(){
            $openrouter_model = get_option('qcld_openrouter_model');
            $openrouter_api_key = get_option('qcld_openrouter_api_key');
            $keyword = $_POST['keyword'];
            $relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink($keyword);
            $relevant_pagelink = array_slice($relevant_pagelink, 0, 5, true);
           
            if( (get_option('page_suggestion_enabled') == '1') && count($relevant_pagelink) > 0 ){
                
                $relevant_post_link = maybe_unserialize(get_option('qlcd_wp_chatbot_relevant_post_link_openai'));
                
                if(is_array($relevant_post_link[get_wpbot_locale()] )){
                   
                    $relevant_pagelinks = '<br><br><p><em>'. implode('', $relevant_post_link[get_wpbot_locale()]) .'</em><p>'. implode("</br>", $relevant_pagelink);
               }else{
                $relevant_pagelinks = '<br><br><p><em>'. $relevant_post_link[get_wpbot_locale()] .'</em><p>'. implode("</br>", $relevant_pagelink);
               }

               
            }else{
                $relevant_pagelinks = '';
            }
            $Parsedown = new Parsedown();
            $data = json_encode(array(
                'model' => $openrouter_model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $keyword
                    )
                )
            ));

            $ch = curl_init();
            $api_url = 'https://openrouter.ai/api/v1/chat/completions';
       
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openrouter_api_key
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $result = curl_exec($ch);
            
            if(curl_errno($ch)) {
                $response['status'] = 'error';
                $response['message'] = 'Curl error: ' . curl_error($ch);
            } else {
                $msg = json_decode($result);
                if(isset($msg->choices[0]->message->content)) {
                    $response['status'] = 'success';
                    $response['message'] = $Parsedown->text($msg->choices[0]->message->content) . $relevant_pagelinks;
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Sorry, I encountered an error processing your AI request. Please check api key and try again later.';
                }
            }
            
            curl_close($ch);
            echo json_encode($response);
            wp_die();
        }
        public function update_settings_option_callback(){
            update_option('disable_wp_chatbot_site_search',1);
            update_option('enable_wp_chatbot_post_content', '');
        }
    }

    /**
     * @return qcld_wpopenai_addon
     */
    if(!function_exists('qcld_wpopenrouter_addons')){
        function qcld_wpopenrouter_addons() {
            $qcld_wpopenrouter_addon = new qcld_wpopenrouter_addons();
            return $qcld_wpopenrouter_addon->instance();
        
        }
    }
  
    //fire off the plugin
    qcld_wpopenrouter_addons();

}