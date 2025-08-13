<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('qcld_wpgemini_addons')){


    /**
     * Main Class.
     */
    final class qcld_wpgemini_addons
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
            add_action('wp_ajax_gemini_response',[$this,'gemini_response_callback']);
            add_action('wp_ajax_nopriv_gemini_response', [$this, 'gemini_response_callback']);
            add_action('wp_ajax_qcld_gemini_settings_option',[$this,'qcld_gemini_settings_option_callback']);

            add_action('wp_ajax_update_settings_option', [$this, 'update_settings_option_callback']);

            if (is_admin() && !empty($_GET["page"]) && (($_GET["page"] == "openai-panel_dashboard") || ($_GET["page"] == "openai-panel_file") || ($_GET["page"] == "openai-panel_help"))) {
                add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_admin_scripts'));
            }
            //add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_gemini_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_gemini_admin_scripts'));
        }



        public function qcld_wb_chatbot_gemini_admin_scripts() {
              if ( ! current_user_can( 'manage_options' ) ) {
                    return ;
                }
            wp_register_script(
                'qcld-wp-chatbot-gemini-admin-js', 
                QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/gemini/assets/js/qcld-wp-gemini-admin.js', 
                array('jquery'), 
                QCLD_wpCHATBOT_VERSION, 
                true
            );

            // Localize the script with necessary data
            wp_localize_script('qcld-wp-chatbot-gemini-admin-js', 'ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('wp_chatbot'),
                'gemini_api_key' => get_option('qcld_gemini_api_key'),
                'gemini_enabled' => get_option('qcld_gemini_enabled'),
                'qcld_gemini_append_content' => get_option('qcld_gemini_append_content'),
                'qcld_gemini_prepend_content' => get_option('qcld_gemini_prepend_content')
            ));
            
            wp_enqueue_script('qcld-wp-chatbot-gemini-admin-js');
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
        public function qcld_gemini_settings_option_callback() {
                $nonce = sanitize_text_field($_POST['nonce']);
                if (!wp_verify_nonce($nonce, 'wp_chatbot')) {
                    wp_send_json(array('success' => false, 'msg' => esc_html__('Failed in Security check', 'sm')));
                    wp_die();
                } else {
                    $gemini_api_key = sanitize_text_field($_POST['gemini_api_key']);
                    $gemini_enabled = sanitize_text_field($_POST['gemini_enabled']);
                    $qcld_gemini_page_suggestion_enabled = sanitize_text_field($_POST['qcld_gemini_page_suggestion_enabled']);
                    $qcld_gemini_append_content = sanitize_text_field($_POST['qcld_gemini_append_content']) ?? '';
                    $qcld_gemini_prepend_content = sanitize_text_field($_POST['qcld_gemini_prepend_content']) ?? '';
                    if($gemini_api_key != '') {
                        update_option('qcld_gemini_api_key', $gemini_api_key);
                    }
                    if($gemini_enabled != '') {
                        update_option('qcld_gemini_enabled', $gemini_enabled);
                    }
                    if($gemini_enabled == '1') {
                        update_option('ai_enabled', 0);
                        update_option('qcld_openrouter_enabled', 0);
                    
                    } else {
                        update_option('ai_enabled', 1);
                    }
                    update_option('qcld_gemini_page_suggestion_enabled', $qcld_gemini_page_suggestion_enabled);
                    update_option( 'qcld_openai_relevant_post', $_POST['openai_post_type'] );
                    
                    update_option('qcld_gemini_append_content', $qcld_gemini_append_content);
                    update_option('qcld_gemini_prepend_content', $qcld_gemini_prepend_content);
                    
                }
                echo json_encode($gemini_enabled);
                wp_die();
        }
		public function gemini_response_callback() {
		
			$gemini_api_key   = get_option( 'qcld_gemini_api_key' );
			$keyword          = isset($_POST['keyword']) ? $_POST['keyword'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword );
			$relevant_pagelink = array_slice( $relevant_pagelink, 0, 5, true );

			if ( ( get_option( 'page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {
				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
				if ( is_array( $relevant_post_link[ get_wpbot_locale() ] ) ) {
					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ get_wpbot_locale() ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					$relevant_pagelinks = '<br><br><p><em>' . $relevant_post_link[ get_wpbot_locale() ] . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			} else {
				$relevant_pagelinks = '';
			}

			$Parsedown = new Parsedown();

			// Gemini API expects a different payload and endpoint
			$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash'  . ':generateContent';

			$data = array(
				'contents' => array(
					array(
						'parts' => array(
							array(
								'text' => $keyword,
							),
						),
					),
				),
			);

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $api_url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					'Content-Type: application/json',
					'X-goog-api-key: ' . $gemini_api_key,
				)
			);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
			$result = curl_exec( $ch );

			if ( curl_errno( $ch ) ) {
				$response['status']  = 'error';
				$response['message'] = 'Curl error: ' . curl_error( $ch );
			} else {
				$msg = json_decode( $result );
				// Gemini API returns candidates[0]->content->parts[0]->text
				if (
					isset($msg->candidates[0]->content->parts[0]->text)
					&& !empty($msg->candidates[0]->content->parts[0]->text)
				) {
					$response['status']  = 'success';
					$response['message'] = $Parsedown->text( $msg->candidates[0]->content->parts[0]->text ) . $relevant_pagelinks;
				} else {
					$response['status']  = 'error';
					$response['message'] = 'Invalid response format from Gemini API';
				}
			}

			curl_close( $ch );
			echo json_encode( $response );
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
    if(!function_exists('qcld_wpgemini_addons')){
        function qcld_wpgemini_addons() {
            $qcld_wpgemini_addon = new qcld_wpgemini_addons();
            return $qcld_wpgemini_addon->instance();
        
        }
    }
  
    //fire off the plugin
    qcld_wpgemini_addons();

}