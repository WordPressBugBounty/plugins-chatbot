<?php
/**
 * Plugin Name: AI ChatBot - WPBot
 * Plugin URI: https://wordpress.org/plugins/chatbot/
 * Description: ChatBot is a native WordPress ChatBot plugin to provide live chat support and lead generation
 * Donate link: https://www.wpbot.pro/
 * Version: 7.8.2
 * @author    QuantumCloud
 * Author: ChatBot for WordPress - WPBot
 * Author URI: https://www.wpbot.pro/
 * Requires at least: 4.6
 * Tested up to: 6.9
 * Text Domain: wpbot
 * Domain Path: /lang
 * License: GPL2
 */



if (!defined('ABSPATH')) exit; // Exit if accessed directly.

add_action( 'plugins_loaded', 'qcld_chatbot_existing_plugin_activate_check_callback' );
if( !function_exists('qcld_chatbot_existing_plugin_activate_check_callback') ){
    function qcld_chatbot_existing_plugin_activate_check_callback(){

        $check_existing_plugin = get_option('qcld_chatbot_existing_plugin_activate_check');


        if ( class_exists( 'qcld_wb_Chatbot' ) && isset($check_existing_plugin) && ($check_existing_plugin !== 'yes') ) {
            update_option('qcld_chatbot_existing_plugin_activate_check', 'yes');
        }else if ( ! class_exists( 'qcld_wb_Chatbot' ) ) {
            delete_option('qcld_chatbot_existing_plugin_activate_check');
        }
        
    }
}

$check_existing_plugin = get_option('qcld_chatbot_existing_plugin_activate_check');
if ( isset($check_existing_plugin) && ($check_existing_plugin == 'yes') || class_exists( 'qcld_wb_Chatbot' ) ) {
    return;
}

if ( ! defined( 'QCLD_wpCHATBOT_VERSION' ) ) {
    define('QCLD_wpCHATBOT_VERSION', '7.8.2');
}
if ( ! defined( 'QCLD_wpCHATBOT_REQUIRED_wpCOMMERCE_VERSION' ) ) {
    define('QCLD_wpCHATBOT_REQUIRED_wpCOMMERCE_VERSION', 2.2);
}
if ( ! defined( 'QCLD_wpCHATBOT_PLUGIN_DIR_PATH' ) ) {
    define('QCLD_wpCHATBOT_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
}
if ( ! defined( 'QCLD_wpCHATBOT_PLUGIN_URL' ) ) {
    define('QCLD_wpCHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if ( ! defined( 'QCLD_wpCHATBOT_IMG_URL' ) ) {
    define('QCLD_wpCHATBOT_IMG_URL', QCLD_wpCHATBOT_PLUGIN_URL . "images/");
}
if ( ! defined( 'QCLD_wpCHATBOT_IMG_ABSOLUTE_PATH' ) ) {
    define('QCLD_wpCHATBOT_IMG_ABSOLUTE_PATH', plugin_dir_path(__FILE__) . "images");
}
if ( ! defined( 'QCLD_wpCHATBOT_INDEX_TABLE' ) ) {
    define('QCLD_wpCHATBOT_INDEX_TABLE', 'wpwbot_index');
}



//define('QCLD_wpCHATBOT_CACHE_TABLE', 'wpwbot_cache');

if ( ! defined( 'QCLD_wpCHATBOT_GC_DIRNAME' ) ) {
    $gcdirpath = __DIR__.'/../../wpbot-dfv2-client';
    define('QCLD_wpCHATBOT_GC_DIRNAME', $gcdirpath);
}
if ( ! defined( 'QCLD_wpCHATBOT_GC_ROOT' ) ) {
    $wpcontentpath = __DIR__.'/../../';
    define('QCLD_wpCHATBOT_GC_ROOT', $wpcontentpath);
}

require_once("qcld-wpwbot-search.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/class-qcld-bot-rag.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/integration/openai/qcld-bot-openai.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/integration/openrouter/qcld-bot-openrouter.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/integration/gemini/qcld-bot-gemini.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."class-qc-free-plugin-upgrade-notice.php");
require_once("class-plugin-deactivate-feedback.php");
require_once("qc-support-promo-page/class-qc-support-promo-page.php");
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."/functions.php");
require_once('qcld_df_api.php');
require_once('includes/class-wpbot-gc-download.php');
require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/class-common-function.php");
require_once('includes/class-response-list.php');
require_once('qc-rating-feature/qc-rating-class.php');
// if ( is_admin() ) {
//     require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/inc/parsedown.php' );
//     require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/inc/qcld-floating-openai-style-filter.php' );
//     require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/inc/qcld_openai_floating_content.php' );
// }

/**
 * Main Class.
 */

class qcld_wb_Chatbot_free
{
    private $id = 'wpbot';
    private static $instance;
	public $mysql_version = '';
    public $promotion;
    public $response_list;
    
    /**
     *  Get Instance creates a singleton class that's cached to stop duplicate instances
     */
    public static function qcld_wb_chatbot_get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->qcld_wb_chatbot_init();
        }
        return self::$instance;
    }
    /**
     *  Construct empty on purpose
     */
    private function __construct()
    {
        $this->promotion = QCLD_wpCHATBOT_IMG_URL . "/NY-26-wpbot.jpg";
    }
    /**
     *  Init behaves like, and replaces, construct
     */
    public function qcld_wb_chatbot_init()
    {
        // Check if wpCommerce is active, and is required wpCommerce version.
        /*if (!class_exists('wpCommerce') || version_compare(get_option('wpcommerce_db_version'), QCLD_wpCHATBOT_REQUIRED_wpCOMMERCE_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wpcommerce_inactive_notice_for_wp_chatbot'));
            return;
        }*/
        add_action('admin_menu', array($this, 'qcld_wb_chatbot_admin_menu'));
        
        if ((!empty($_GET["page"])) && ($_GET["page"] == "wpbot")) {
            add_action('admin_init', array($this, 'qcld_wb_chatbot_save_options'));
           
        }

        if( ( !empty($_GET['page']) && $_GET["page"] == "wpbot") || ( !empty($_GET['page']) && $_GET["page"] == "wpbot-panel")|| ( !empty($_GET['page']) && $_GET['page'] == 'wpbot_openAi') || ( !empty($_GET['page']) && $_GET['page'] == 'simple-text-response')  ){
         //  add_action( 'admin_notices', array( $this, 'promotion_notice' ) );
        }

        if (is_admin() && !empty($_GET["page"]) && ($_GET["page"] == "wpbot") || (!empty($_GET['page']) && $_GET['page']=='wpbot_help_page')

            || (!empty($_GET['page']) && $_GET['page']=='wpbot_openAi')

         || (!empty($_GET['page']) && $_GET['page']=='wpbot-panel') || ( !empty($_GET['page']) &&  $_GET["page"] == "wbcs-botsessions-page") ) {
            add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_admin_scripts'));
            if( get_option('wp_chatbot_index_count')<=0 && get_option('qlcd_wp_chatbot_search_option')=='advanced'){
                
                add_action( 'admin_notices', array( $this, 'admin_notice_reindex' ) );
            }
        }
		//loading frontend scripts
		add_action('wp', array($this, 'qcld_wpchatbot_init_fnc'));
		add_action('init', array($this, 'qcld_wpchatbot_init2_fnc'));
		
		
    }
	
	
	
	public function qcld_wpchatbot_init_fnc(){
		if (!is_admin() && get_option('disable_wp_chatbot') != 1 && wp_chatbot_load_controlling() === true) {
            add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_frontend_scripts'));
        }
	}
	public function qcld_wpchatbot_init2_fnc(){
        global $wpdb;
		if( is_admin() ){

            $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
			if($connection === false){
				return;
			}
            $content = $connection->server_info;
            
            $mysql_server_info = $wpdb->db_server_info();

            // Check for the MariaDB.
            $is_mariadb = false;
			if ( ! empty( $mysql_server_info ) && strpos( strtolower( $mysql_server_info ), 'maria' ) !== false ) {
				$is_mariadb = true;
			}
			
			preg_match_all('/\d+\.\d+/', $content, $matches);
            
            if( !empty( $matches ) && isset( $matches[0] ) && !empty( $matches[0] ) && is_array( $matches[0] ) && ! $is_mariadb ){
                $versions = $matches[0];
                $notice = true;
                foreach( $versions as $version ){
                    if (version_compare($version, '5.5', '>')) {
                        $this->mysql_version = $version;
                        $notice = false;
                    }else{
                        $this->mysql_version = $version;
                    }
                }

                if( $notice ){
                    add_action('admin_notices', array($this, 'mysql_version_notice') );
                }

            }
			
            $connection->close();
        }
	}
	
	public function mysql_version_notice(){
        $class="notice notice-error is-dismissible qc-notice-error";
        $message = "Your server's MySQL version is **".$this->mysql_version."**. MySQL version 5.6+ is required for Simple Text Responses to work. Please contact your hosting support to upgrade the MySQL to the latest version.";
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
    }
	
    /**
     * Add a submenu item to the wpCommerce menu
     */
    public function qcld_wb_chatbot_admin_menu()
    {
       /* add_submenu_page('wpcommerce',
            __('wpwBot Pro', 'chatbot'),
            __('wpwBot Pro', 'chatbot'),
            'manage_wpcommerce',
            $this->id,
            array($this, 'qcld_wb_chatbot_admin_page'));*/
		
        add_menu_page( esc_html('ChatBot WPBot Lite'), esc_html('ChatBot WPBot Lite'), 'manage_options','wpbot-panel', array($this, 'qcld_wb_chatbot_admin_page'),'dashicons-format-status', 6 );

		add_submenu_page( 'wpbot-panel', esc_html('Settings'), esc_html('Settings'), 'manage_options','wpbot', array($this, 'qcld_wb_chatbot_admin_page_settings') );

        add_submenu_page( 'wpbot-panel', esc_html('OpenAI Settings'), esc_html('AI Settings'), 'manage_options','wpbot_openAi', 'wpbot_openAi_setting_func' );

		$hook = add_submenu_page( 'wpbot-panel', esc_html('Simple Text Responses'), esc_html('Simple Text Responses'), 'manage_options','simple-text-response', array($this, 'qcld_wb_chatbot_admin_str') );

        add_action( "load-$hook", [ $this, 'screen_option' ] );

   //     add_submenu_page( 'wpbot-panel', esc_html('Conversational Form '), esc_html('Conversational Form'), 'manage_options','wpbots', [$this, 'qcld_wb_chatbot_admin_conversational_settings'] );
		
		add_submenu_page( 'wpbot-panel', esc_html('Support'), esc_html('Support'), 'manage_options','wpbot_support_page', 'qcpromo_wpbot_free_support_page_callback_func' );
		
		add_submenu_page( 'wpbot-panel', esc_html('Help and Debugging'), esc_html('Help and Debugging'), 'manage_options','wpbot_help_page', 'wpbot_help_page_callback_func' );


    }
	
	function screen_option(){
        if( isset($_POST['wp_screen_options']) && !empty($_POST['wp_screen_options'])){
            $per_page_str = (int)$_POST['wp_screen_options']["value"];

        }else{
            $per_page_str = 20;
        }
        $option = 'per_page';
		$args   = [
			'label'   => 'Response',
			'default' => $per_page_str,
			'option'  => 'responses_per_page'
		];
		
		$this->response_list = new Response_list();
    }
	
	public function qcld_wb_chatbot_admin_str(){

        require_once("includes/simple_text_response.php");

    }

        

    /**
     * Include admin scripts
     */
    public function qcld_wb_chatbot_admin_scripts($hook)
    {
        global $wpcommerce, $wp_scripts;
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        if (((!empty($_GET["page"])) && ($_GET["page"] == "wpbot")) || ($hook == "widgets.php") || $_GET['page']=='wpbot_help_page' || $_GET['page']=='wpbot_openAi' || $_GET['page']=='simple-text-response'
            || $_GET['page']=='wpbot-panel' || $_GET["page"] == "wbcs-botsessions-page" ) {
            
            wp_enqueue_script('jquery');
            //wp_enqueue_style('wpcommerce_admin_styles', $wpcommerce->plugin_url() . '/assets/css/admin.css');
     
            wp_register_style('qlcd-wp-chatbot-admin-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/admin-style.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('qlcd-wp-chatbot-admin-style');
            wp_register_style('qlcd-wp-chatbot-tabs-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/wp-chatbot-tabs.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('qlcd-wp-chatbot-tabs-style');
            wp_register_style('jquery.fontpicker.min.css', QCLD_wpCHATBOT_PLUGIN_URL . 'css/fontpicker.min.css', '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('jquery.fontpicker.min.css');

           
            //wp_register_style('qlcd-openai-bootstap',  plugins_url(basename(plugin_dir_path(__FILE__)) . '/openai/css/openai-admin-style.css', basename(__FILE__)), array(), true);
            //wp_enqueue_style('qlcd-openai-bootstap');
          
           

            wp_enqueue_script('jquery');
			wp_enqueue_script( 'jquery-ui-draggable' );
            wp_enqueue_script( 'jquery-ui-droppable' );
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_style( 'wp-color-picker');
            wp_enqueue_script( 'wp-color-picker');
            wp_enqueue_script( 'jquery-ui-sortable');
            wp_register_script('qcld-wp-fontpicker', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/fontpicker.js', basename(__FILE__)), array(), true);
            wp_enqueue_script('qcld-wp-fontpicker');
            wp_register_script('qcld-wp-chatbot-cbpFWTabs', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/cbpFWTabs.js', basename(__FILE__)), array(), true);
            wp_enqueue_script('qcld-wp-chatbot-cbpFWTabs');
            wp_register_script('qcld-wp-chatbot-modernizr-custom', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/modernizr.custom.js', basename(__FILE__)), array(), true);
            wp_enqueue_script('qcld-wp-chatbot-modernizr-custom');
            wp_register_script('qcld-wp-chatbot-bootstrap-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/bootstrap.js', basename(__FILE__)), array('jquery'), true);
            wp_enqueue_script('qcld-wp-chatbot-bootstrap-js');

            // wp_register_script('qcld-wp-openai-setting-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/openai_settings.js', basename(__FILE__)), array('jquery'), true);
            // wp_enqueue_script('qcld-wp-openai-setting-js');

            wp_localize_script( 'qcld-wp-openai-setting-js', 'openai_ajax', array(
                'url' => admin_url( 'admin-ajax.php' ),
            ) );

            wp_register_style('qcld-wp-chatbot-bootstrap-css', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/bootstrap.min.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('qcld-wp-chatbot-bootstrap-css');
            //jquery time picker
            wp_register_script('qcld-wp-chatbot-timepicker-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/jquery.timepicker.js', basename(__FILE__)), array('jquery'), true);
            wp_enqueue_script('qcld-wp-chatbot-timepicker-js');
            wp_register_style('qcld-wp-chatbot-timepicker-css', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/jquery.timepicker.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('qcld-wp-chatbot-timepicker-css');
			wp_register_script('qcld-wp-chatbot-sweetalrt', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/sweetalrt.js', basename(__FILE__)), array(), true);
			wp_enqueue_script('qcld-wp-chatbot-sweetalrt');
            wp_register_script('qcld-wp-chatbot-admin-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/qcld-wp-chatbot-admin.js', basename(__FILE__)), array('jquery', 'jquery-ui-core','jquery-ui-sortable','wp-color-picker','qcld-wp-chatbot-timepicker-js'), '10.9.9', true);
            wp_enqueue_script('qcld-wp-chatbot-admin-js');
            wp_localize_script('qcld-wp-chatbot-admin-js', 'qcld_gemini_admin_data',
                array('ajax_url' => admin_url('admin-ajax.php'),'ajax_nonce' => wp_create_nonce('wp_chatbot'),'image_path' => QCLD_wpCHATBOT_IMG_URL));
            // WordPress  Media library
            wp_enqueue_media();



        }

    }


	public function qcld_get_formbuilder_forms(){
        global $wpdb;
        $forms = array();
        if(class_exists('Qcformbuilder_Forms_Admin')){
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms WHERE type=%s", 'primary')); //DB Call OK, No Caching OK
            if(!empty($results)){
                foreach($results as $result){
                    $form = maybe_unserialize($result->config);
                    $forms[] = trim($form['name']);
                }
                return $forms;
            }else{
                return array();   
            }
        }else{
            return array();
        }
    }
    public function qcld_wpbot_simple_response_intent(){
        global $wpdb;
        $table = $wpdb->prefix.'wpbot_response';
        $results = $wpdb->get_results("SELECT `intent` FROM `$table` WHERE 1 and `intent` !=''"); //DB Call OK, No Caching OK
        $response = array();
        if(!empty($results)){
            foreach($results as $result){
                $response[] = $result->intent;
            }
        }
        return $response;
    }
    public function qcld_get_formbuilder_form_commands(){
        global $wpdb;
        $command = array();
        if(class_exists('Qcformbuilder_Forms_Admin')){
            $primary = 'primary';
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms WHERE type = %s", $primary)); //DB Call OK, No Caching OK
            
            if(!empty($results)){
                foreach($results as $result){
                    $form = maybe_unserialize($result->config);
                    
                    if(isset($form['command'])){
                        $command[] = array_map('trim', explode(',', strtolower($form['command'])));
                    } 
                    
                }
                return $command;
            }else{
                return array();   
            }
        }else{
            return array();
        }
    }
	public function promotion_notice(){
        $screen = get_current_screen();
       // var_dump($screen->base );
       // if( isset($screen->base) && (( $screen->base == 'wpbot-lite_page_wpbot') || ( $screen->base == 'toplevel_page_wpbot-panel"'))){
        ?>
        <div id="promotion-wpchatbot" data-dismiss-type="qcbot-feedback-notice" class="notice is-dismissible qcbot-feedback" style="background: #120976 !important">
            <div class="">
                
                <div class="qc-review-text" >
                <a href="<?php echo esc_url('https://www.wpbot.pro/pricing/'); ?>" target="_blank">
                    <img src="<?php echo esc_url($this->promotion); ?>" alt="promotion" style="position: flex !important;"></a>
                </div>
                </div>
        </div>
        <?php
      //  }
    }
	public function qcld_get_formbuilder_form_ids(){
        global $wpdb;
        $forms = array();
        if(class_exists('Qcformbuilder_Forms_Admin')){
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms WHERE type=%s", 'primary')); //DB Call OK, No Caching OK
            
            if(!empty($results)){
                foreach($results as $result){
                    $form = maybe_unserialize($result->config);
                    $forms[] = trim($form['ID']);
                }
                return $forms;
            }else{
                return array();   
            }
        }else{
            return array();
        }
    }
    
    public function qcld_wb_chatbot_frontend_scripts()
    {
        global $wpcommerce, $wp_scripts, $wpdb, $current_user;
		
		$display_name = '';
        $display_email = '';
        $user_image = get_option('wp_custom_client_icon');
        $user_id = 0;
        $user_image = get_option('wp_custom_client_icon');
		if ( is_user_logged_in() ) { 
            $display_name = $current_user->display_name;
            $display_email = $current_user->user_email;
            $user_image = esc_url( get_avatar_url( $current_user->ID ) );
            $user_id = $current_user->ID;
		}
		
		$conversation_form_ids = array();
		$conversation_form_names = array();
		
		if(class_exists('Qcformbuilder_Forms_Admin')){
			$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms WHERE type=%s", 'primary')); //DB Call OK, No Caching OK
			if(!empty($results)){

				foreach($results as $result){
					$form = unserialize($result->config);
					$conversation_form_ids[] = $form['ID'];
					$conversation_form_names[] = $form['name'];
				}

			}
		}
       
    //   var_dump( str_replace('<', '',esc_html(get_option('qlcd_wp_chatbot_agent'))));wp_die();
        $wp_chatbot_obj = array(
            'wp_chatbot_position_x' => get_option('wp_chatbot_position_x'), 
            'wp_chatbot_position_y' => get_option('wp_chatbot_position_y'),
            'disable_icon_animation' => get_option('disable_wp_chatbot_icon_animation'),
            'disable_featured_product' => get_option('disable_wp_chatbot_featured_product'),
            'disable_product_search' => get_option('disable_wp_chatbot_product_search'),
            'disable_catalog' => get_option('disable_wp_chatbot_catalog'),
            'disable_order_status' => get_option('disable_wp_chatbot_order_status'),
            'disable_sale_product' => get_option('disable_wp_chatbot_sale_product'),
            'open_product_detail' => get_option('wp_chatbot_open_product_detail'),
            'order_user' => get_option('qlcd_wp_chatbot_order_user'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'image_path' => QCLD_wpCHATBOT_IMG_URL,
            'yes' => str_replace('\\', '',get_option('qlcd_wp_chatbot_yes')),
            'no' => str_replace('\\', '',get_option('qlcd_wp_chatbot_no')),
            'or' => str_replace('\\', '',get_option('qlcd_wp_chatbot_or')),
            'host' => str_replace('\\', '',get_option('qlcd_wp_chatbot_host')),
            'agent' => str_replace(['\\','<','>'], '',esc_html(get_option('qlcd_wp_chatbot_agent'))),
            'agent_image' => get_option('wp_chatbot_agent_image'),
            'agent_image_path' => (!empty(get_option('wp_chatbot_custom_icon_path')) && !is_404(get_option('wp_chatbot_custom_icon_path'))) ? $this->qcld_wb_chatbot_agent_icon() : QCLD_wpCHATBOT_IMG_URL . 'icon-1.png',
            'shopper_demo_name' => str_replace('\\', '',get_option('qlcd_wp_chatbot_shopper_demo_name')),
            'agent_join' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_agent_join'))),
            'welcome' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_welcome'))),
            'welcome_back' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_welcome_back'))),
            'hi_there' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_hi_there'))),
            'hello' => $this->qcld_wb_chatbot_str_replace(maybe_unserialize(get_option('qlcd_wp_chatbot_hello'))),
            'asking_name' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_asking_name'))),
            'i_am' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_i_am'))),
            'name_greeting' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_name_greeting'))),
            'wildcard_msg' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_wildcard_msg'))),
            'empty_filter_msg' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_empty_filter_msg'))),
            'did_you_mean' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_did_you_mean'))),
            'is_typing' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_is_typing'))),
            'send_a_msg' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_send_a_msg'))),

            'viewed_products' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_viewed_products'))),

            'shopping_cart' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_shopping_cart'))),
            'cart_updating' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_cart_updating'))),
            'cart_removing' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_cart_removing'))),
			'imgurl' => QCLD_wpCHATBOT_IMG_URL,
            'sys_key_help' => get_option('qlcd_wp_chatbot_sys_key_help'),
            'sys_key_product' => get_option('qlcd_wp_chatbot_sys_key_product'),
            'sys_key_catalog' => get_option('qlcd_wp_chatbot_sys_key_catalog'),
            'sys_key_order' => get_option('qlcd_wp_chatbot_sys_key_order'),
            'sys_key_support' => get_option('qlcd_wp_chatbot_sys_key_support'),
            'sys_key_reset' => get_option('qlcd_wp_chatbot_sys_key_reset'),
            'sys_key_email' => get_option('qlcd_wp_chatbot_sys_key_email'),
            'help_welcome' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_help_welcome'))),
            'back_to_start' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_back_to_start'))),
            'help_msg' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_help_msg'))),
            'reset' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_reset'))),
            'wildcard_product' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_wildcard_product'))),
            'wildcard_catalog' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_wildcard_catalog'))),
            'featured_products' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_featured_products'))),
            'sale_products' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_sale_products'))),
            'wildcard_order' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_wildcard_order'))),
            'wildcard_support' => get_option('qlcd_wp_chatbot_wildcard_support'),
            'product_asking' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_product_asking'))),
            'product_suggest' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_product_suggest'))),
            'product_infinite' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_product_infinite'))),
            'product_success' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_product_success'))),
            'product_fail' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_product_fail'))),
            'support_welcome' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_support_welcome'))),
            'support_email' => get_option('qlcd_wp_chatbot_support_email'),
            'support_option_again' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_support_option_again'))),
            'asking_email' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_asking_email'))),
            'asking_msg' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_asking_msg'))),
            'no_result' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_no_result'))),
            'support_phone' => get_option('qlcd_wp_chatbot_support_phone'),
            'asking_phone' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_asking_phone'))),
            'thank_for_phone' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_thank_for_phone'))),
            'support_query' => ((gettype(get_option('support_query')) == 'string') ? $this->qcld_wb_chatbot_str_replace(unserialize( get_option('support_query'))) : $this->qcld_wb_chatbot_str_replace(( get_option('support_query')))),
            'support_ans' => (gettype(get_option('support_ans')) == 'string') ? $this->qcld_wb_chatbot_str_replace(unserialize(get_option('support_ans'))) : $this->qcld_wb_chatbot_str_replace((get_option('support_ans'))),
            'notification_interval' => get_option('qlcd_wp_chatbot_notification_interval'),
            'notifications' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_notifications'))),
            'order_welcome' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_order_welcome'))),
            'order_username_asking' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_order_username_asking'))),
            'order_username_password' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_order_username_password'))),
            'order_user' => get_option('qlcd_wp_chatbot_order_user'),
            'order_login' => is_user_logged_in(),
            'is_chat_session_active' => qcld_wpbot_is_active_chat_history(),
            'order_nonce' => wp_create_nonce("wpwbot-order-nonce"),
            'order_email_support' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_order_email_support'))),
            'email_fail' => str_replace('\\', '', get_option('qlcd_wp_chatbot_email_fail')),
            'invalid_email' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_invalid_email'))),
            'stop_words' => str_replace('\\', '', get_option('qlcd_wp_chatbot_stop_words')),
            'currency_symbol' => '',
            'enable_messenger' => get_option('enable_wp_chatbot_messenger'),
            'messenger_label' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_messenger_label'))),
            'fb_page_id' => get_option('qlcd_wp_chatbot_fb_page_id'),
            'enable_skype' => get_option('enable_wp_chatbot_skype'),
            'enable_whats' => get_option('enable_wp_chatbot_whats'),
            'whats_label' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_whats_label'))),
            'whats_num' => get_option('qlcd_wp_chatbot_whats_num'),
            'ret_greet' => get_option('qlcd_wp_chatbot_ret_greet'),
            'enable_exit_intent' => get_option('enable_wp_chatbot_exit_intent'),
            'exit_intent_msg' => str_replace('\\', '', get_option('wp_chatbot_exit_intent_msg')),
            'exit_intent_once' => get_option('wp_chatbot_exit_intent_once'),
            'enable_scroll_open' => get_option('enable_wp_chatbot_scroll_open'),
            'scroll_open_msg' => str_replace('\\', '', get_option('wp_chatbot_scroll_open_msg')),
            'scroll_open_percent' => get_option('wp_chatbot_scroll_percent'),
            'scroll_open_once' => get_option('wp_chatbot_scroll_once'),
            'enable_auto_open' => get_option('enable_wp_chatbot_auto_open'),
            'auto_open_msg' => str_replace('\\', '', get_option('wp_chatbot_auto_open_msg')),
            'auto_open_time' => get_option('wp_chatbot_auto_open_time'),
            'auto_open_once' => get_option('wp_chatbot_auto_open_once'),
            'proactive_bg_color' => get_option('wp_chatbot_proactive_bg_color'),
            'disable_feedback' => get_option('disable_wp_chatbot_feedback'),
            'disable_faq' => get_option('disable_wp_chatbot_faq'),
            'feedback_label' => $this->qcld_wb_chatbot_str_replace(unserialize(get_option('qlcd_wp_chatbot_feedback_label'))),
            'enable_meta_title' =>get_option('enable_wp_chatbot_meta_title'),
            'meta_label' =>str_replace('\\', '', get_option('qlcd_wp_chatbot_meta_label')),
            'phone_number' => get_option('qlcd_wp_chatbot_phone'),
            'disable_site_search' => get_option('disable_wp_chatbot_site_search'),
            'search_keyword' => get_option('qlcd_wp_chatbot_asking_search_keyword'),
            'ajax_nonce'=> wp_create_nonce('qcsecretbotnonceval123qc'),
			'site_search' => get_option('qlcd_wp_site_search'),
            'open_links_newtab' => get_option('open_links_new_window'),
            'call_gen' => get_option('disable_wp_chatbot_call_gen'),
            'call_sup' => get_option('disable_wp_chatbot_call_sup'),
            'enable_ret_sound' => get_option('enable_wp_chatbot_ret_sound'),
            'found_result_message' => get_option('qlcd_wp_chatbot_found_result'),
            'enable_ret_user_show' => get_option('enable_wp_chatbot_ret_user_show'),
            'enable_inactive_time_show' => get_option('enable_wp_chatbot_inactive_time_show'),
            'ret_inactive_user_once' => get_option('wp_chatbot_inactive_once'),
            'mobile_full_screen' => '1',
            'botpreloadingtime' => (get_option('wpbot_preloading_time')?get_option('wpbot_preloading_time'):800),
            'inactive_time' => get_option('wp_chatbot_inactive_time'),
            'checkout_msg' => str_replace('\\', '', get_option('wp_chatbot_checkout_msg')),
            'ai_df_enable' => get_option('enable_wp_chatbot_dailogflow'),
            'ai_df_token' => get_option('qlcd_wp_chatbot_dialogflow_client_token'),
            'df_defualt_reply' => str_replace('\\', '', get_option('qlcd_wp_chatbot_dialogflow_defualt_reply')),
			'df_agent_lan' => get_option('qlcd_wp_chatbot_dialogflow_agent_language'),
            'openai_enabled' => get_option('ai_enabled'),
            'qcld_openai_append_content' => get_option('qcld_openai_append_content'),
            'openrouter_enabled' => (get_option('qcld_openrouter_enabled')=='1'? get_option('qcld_openrouter_enabled') : '0'),
            'gemini_enabled' => (get_option('qcld_gemini_enabled')=='1'? get_option('qcld_gemini_enabled') : '0'),
            'qcld_gemini_prepend_content' => get_option('qcld_gemini_prepend_content'),
            'qcld_gemini_append_content' => get_option('qcld_gemini_append_content'),
            'qcld_openrouter_append_content' => get_option('qcld_openrouter_append_content'),
            'qcld_openrouter_prepend_content' => get_option('qcld_openrouter_prepend_content'),
			'start_menu'    => wp_unslash(get_option('qc_wpbot_menu_order')),
			'conversation_form_ids' => $conversation_form_ids,
			'conversation_form_names' => $conversation_form_names,
            'simple_response_intent' => $this->qcld_wpbot_simple_response_intent(),
            'forms' => $this->qcld_get_formbuilder_forms(),
            'form_ids'  =>$this->qcld_get_formbuilder_form_ids(),
            'form_commands' => $this->qcld_get_formbuilder_form_commands(),
			'df_api_version' => (get_option('wp_chatbot_df_api')==''?'v1':get_option('wp_chatbot_df_api')),
			'v2_client_url'=> esc_url(get_site_url().'/?action=qcld_dfv2_api'),
			'show_menu_after_greetings'=> (get_option('show_menu_after_greetings')!=''?get_option('show_menu_after_greetings'):0),
            'current_user_id'  => $user_id,
            'skip_wp_greetings' => get_option('skip_wp_greetings'),
            'skip_greetings_and_menu' => get_option('skip_wp_greetings_donot_show_menu'),
            'skip_chat_reactions_menu' => get_option('skip_chat_reactions_menu'),
            'qlcd_wp_chatbot_like_text' => get_option('qlcd_wp_chatbot_like_text'),
            'qlcd_wp_chatbot_dislike_text' => get_option('qlcd_wp_chatbot_dislike_text'),
            'enable_chat_report_menu' => get_option('enable_chat_report_menu'),
            'qlcd_wp_chatbot_report_text' => get_option('qlcd_wp_chatbot_report_text'),
            'enable_chat_share_menu' => get_option('enable_chat_share_menu'),
            'qlcd_wp_chatbot_share_text' => get_option('qlcd_wp_chatbot_share_text'),
			
        );  
        $user_font = get_option('wp_chatbot_user_font') != '' ? get_option('wp_chatbot_user_font') : '';
        if($user_font != '' ){
            $parts = explode(':', $user_font);
            // Create an object
            $user_font_family = new stdClass();
            // Assign values
            $user_font_family->fontfamily = $parts[0];
            $user_font_family->fontWeight = isset($parts[1]) ? $parts[1] : '400'; // default weight
            $user_font_family->fontStyle  = 'normal';
            if(get_option('enable_wp_chatbot_custom_color')==1){  
                $user_enqueue_font = 'https://fonts.googleapis.com/css2?family='. $user_font_family->fontfamily;
                wp_enqueue_style( 'qcld-chatbot-user-google-fonts', $user_enqueue_font, false );
                wp_enqueue_style( 'qcld-chatbot-user-google-fonts');
            }
        }

        $bot_font = get_option('wp_chatbot_bot_font') != '' ? get_option('wp_chatbot_bot_font') : '';
        if($bot_font != '' ){
              $parts = explode(':', $bot_font);
            // Create an object
            $bot_font_family = new stdClass();
            // Assign values
            $bot_font_family->fontFamily = $parts[0];
            $bot_font_family->fontWeight = isset($parts[1]) ? $parts[1] : '400'; // default weight
            $bot_font_family->fontStyle  = 'normal';
            


            if(get_option('enable_wp_chatbot_custom_color')==1){  
                $bot_enqueue_font = 'https://fonts.googleapis.com/css2?family='.$bot_font_family->fontFamily;
                wp_enqueue_style( 'qcld-chatbot-bot-google-fonts', $bot_enqueue_font, false );
                wp_enqueue_style( 'qcld-chatbot-bot-google-fonts');
            }
        }

        

        wp_enqueue_style( 'dashicons' );
        wp_register_script('qcld-wp-chatbot-slimscroll-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/jquery.slimscroll.min.js', basename(__FILE__)), array('jquery'), QCLD_wpCHATBOT_VERSION, true);
        wp_enqueue_script('qcld-wp-chatbot-slimscroll-js');
        wp_register_script('qcld-wp-chatbot-jquery-cookie', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/jquery.cookie.js', basename(__FILE__)), array('jquery'), QCLD_wpCHATBOT_VERSION, true);
        wp_enqueue_script('qcld-wp-chatbot-jquery-cookie');
        wp_register_script('qcld-wp-chatbot-magnify-popup', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/jquery.magnific-popup.min.js', basename(__FILE__)), array('jquery'), QCLD_wpCHATBOT_VERSION, true);
        wp_enqueue_script('qcld-wp-chatbot-magnify-popup');
        wp_register_script('qcld-wp-chatbot-plugin', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/qcld-wp-chatbot-plugin.js', basename(__FILE__)), array('jquery', 'qcld-wp-chatbot-jquery-cookie','qcld-wp-chatbot-magnify-popup'), QCLD_wpCHATBOT_VERSION, true);
        wp_enqueue_script('qcld-wp-chatbot-plugin');

        $nonce = wp_create_nonce('wp_chatbot');
        // Pass data to JS
        wp_localize_script('qcld-wp-chatbot-plugin', 'qcld_chatbot_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $nonce,
        ]);

        wp_register_script('qcld-wp-chatbot-front-js', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/qcld-wp-chatbot-front.js', basename(__FILE__)), array('jquery', 'qcld-wp-chatbot-jquery-cookie'), QCLD_wpCHATBOT_VERSION, true);
        wp_enqueue_script('qcld-wp-chatbot-front-js');
        wp_localize_script('qcld-wp-chatbot-front-js', 'wp_chatbot_obj', $wp_chatbot_obj);
        //wp_register_script('qcld-wp-chatbot-frontend', plugins_url(basename(plugin_dir_path(__FILE__)) . '/js/qcld-wp-chatbot-frontend.js', basename(__FILE__)), array('jquery','qcld-wp-chatbot-jquery-cookie'), QCLD_wpCHATBOT_VERSION, true);
        //wp_enqueue_script('qcld-wp-chatbot-frontend');
        //wp_localize_script('qcld-wp-chatbot-frontend', 'wp_chatbot_obj', $wp_chatbot_obj);
        wp_localize_script('qcld-wp-chatbot-frontend', 'wp_chatbot_obj', $wp_chatbot_obj);

        if ( ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_enqueue_style' ) || ! function_exists( 'plugins_url' ) || ! function_exists( 'plugin_dir_path' ) || ! function_exists( 'get_option' ) || ! function_exists( 'sanitize_hex_color' ) || ! function_exists( 'wp_add_inline_style' ) ) {
            // Return early if critical WordPress functions are not available.
            // This indicates the code needs to be hooked into an appropriate action.
            return;
        }

        wp_register_style('qcld-wp-chatbot-common-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/common-style.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
        wp_enqueue_style('qcld-wp-chatbot-common-style');
		
		$floating_icon_bg_color = get_option('wp_chatbot_floatingiconbg_color');
        $floating_icon_bg_color = sanitize_hex_color($floating_icon_bg_color); // ensures it's a valid hex color like #ffffff

        if ( ! empty($floating_icon_bg_color) ) {
            $inline_floating_icon_styles = "
                .wp-chatbot-ball {
                    background: {$floating_icon_bg_color} !important;
                }
                .wp-chatbot-ball:hover, 
                .wp-chatbot-ball:focus {
                    background: {$floating_icon_bg_color} !important;
                }";

            wp_add_inline_style( 'qcld-wp-chatbot-common-style', $inline_floating_icon_styles );
        }

        wp_register_style('qcld-wp-chatbot-magnific-popup', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/magnific-popup.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
        wp_enqueue_style('qcld-wp-chatbot-magnific-popup');
        
        $qcld_wb_chatbot_theme = get_option('qcld_wb_chatbot_theme');
        /* if (file_exists(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/style.css')) {
             wp_register_style('qcld-wp-chatbot-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/templates/' . $qcld_wb_chatbot_theme . '/style.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
             wp_enqueue_style('qcld-wp-chatbot-style');
         }*/
        //Loading shortcode style
        if (file_exists(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/shortcode.css')) {
            wp_register_style('qcld-wp-chatbot-shortcode-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/templates/' . $qcld_wb_chatbot_theme . '/shortcode.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
            wp_enqueue_style('qcld-wp-chatbot-shortcode-style');
        }
        
        $inline_chat_custom_styles = ''; // Initialize a new variable for general chat custom styles.
        if(get_option('enable_wp_chatbot_custom_color')==1){ 
            // Sanitize all color options before using them in CSS to prevent invalid values.
            $text_color                 = sanitize_hex_color( get_option('wp_chatbot_text_color') );
            $link_color                 = sanitize_hex_color( get_option('wp_chatbot_link_color') );
            $link_hover_color           = sanitize_hex_color( get_option('wp_chatbot_link_hover_color') );
            $bot_msg_text_color         = sanitize_hex_color( get_option('wp_chatbot_bot_msg_text_color') );
            $bot_msg_bg_color           = sanitize_hex_color( get_option('wp_chatbot_bot_msg_bg_color') );
            $buttons_text_color         = sanitize_hex_color( get_option('wp_chatbot_buttons_text_color') );
            $buttons_bg_color           = sanitize_hex_color( get_option('wp_chatbot_buttons_bg_color') );
            $buttons_text_color_hover   = sanitize_hex_color( get_option('wp_chatbot_buttons_text_color_hover') );
            $buttons_bg_color_hover     = sanitize_hex_color( get_option('wp_chatbot_buttons_bg_color_hover') );
            $user_msg_text_color        = sanitize_hex_color( get_option('wp_chatbot_user_msg_text_color') );
            $wp_chatbot_text_color        = sanitize_hex_color( get_option('wp_chatbot_text_color') );
            $user_msg_bg_color          = sanitize_hex_color( get_option('wp_chatbot_user_msg_bg_color') );
            $header_background_color    = sanitize_hex_color( get_option('wp_chatbot_header_background_color') );
                      
            $inline_chat_custom_styles .= "
                #wp-chatbot-chat-container, .wp-chatbot-product-description, .wp-chatbot-product-description p,.wp-chatbot-product-quantity label, .wp-chatbot-product-variable label {
                    color: ". $text_color ." !important;
                }
                #wp-chatbot-chat-container a {
                    color: ". $link_color ." !important;
                }

                #wp-chatbot-chat-container a p{
                    color: ". $link_color ." !important;
                }
                #wp-chatbot-chat-container a p span{
                    color: ". $link_color ." !important;
                }
                #wp-chatbot-chat-container a:hover {
                    color: ". $link_hover_color ." !important;
                }
                
                ul.wp-chatbot-messages-container > li.wp-chatbot-msg .wp-chatbot-paragraph,
                .wp-chatbot-agent-profile .wp-chatbot-bubble {
                    color: ". $bot_msg_text_color ." !important;
                    background-color: ". $bot_msg_bg_color ." !important;
                    word-break: break-word;
                }
                span.qcld-chatbot-product-category, span.qcld-chatbot-support-items, span.qcld-chatbot-wildcard, span.qcld-chatbot-suggest-email, span.qcld-chatbot-reset-btn, #woo-chatbot-loadmore, .wp-chatbot-shortcode-template-container span.qcld-chatbot-product-category, .wp-chatbot-shortcode-template-container span.qcld-chatbot-support-items, .wp-chatbot-shortcode-template-container span.qcld-chatbot-wildcard, .wp-chatbot-shortcode-template-container span.wp-chatbot-card-button, .wp-chatbot-shortcode-template-container span.qcld-chatbot-suggest-email, span.qcld-chatbot-suggest-phone, .wp-chatbot-shortcode-template-container span.qcld-chatbot-reset-btn, .wp-chatbot-shortcode-template-container #wp-chatbot-loadmore, .wp-chatbot-ball-cart-items, .wpbd_subscription, .qcld-chatbot-site-search, .qcld_subscribe_confirm, .qcld-chat-common, .qcld-chatbot-custom-intent {
                    color: ". $buttons_text_color ." !important;
                    background-color: ". $buttons_bg_color ." !important;
                background-image: none !important;
                }

                span.qcld-chatbot-product-category:hover, span.qcld-chatbot-support-items:hover, span.qcld-chatbot-wildcard:hover, span.qcld-chatbot-suggest-email:hover, span.qcld-chatbot-reset-btn:hover, #woo-chatbot-loadmore:hover, .wp-chatbot-shortcode-template-container:hover span.qcld-chatbot-product-category:hover, .wp-chatbot-shortcode-template-container:hover span.qcld-chatbot-support-items:hover, .wp-chatbot-shortcode-template-container:hover span.qcld-chatbot-wildcard:hover, .wp-chatbot-shortcode-template-container:hover span.wp-chatbot-card-button:hover, .wp-chatbot-shortcode-template-container:hover span.qcld-chatbot-suggest-email:hover, span.qcld-chatbot-suggest-phone:hover, .wp-chatbot-shortcode-template-container:hover span.qcld-chatbot-reset-btn:hover, .wp-chatbot-shortcode-template-container:hover #wp-chatbot-loadmore:hover, .wp-chatbot-ball-cart-items:hover, .wpbd_subscription:hover, .qcld-chatbot-site-search:hover, .qcld_subscribe_confirm:hover, .qcld-chat-common:hover, .qcld-chatbot-custom-intent:hover {
                    color: ". $buttons_text_color_hover ." !important;
                    background-color: ". $buttons_bg_color_hover ." !important;
                background-image: none !important;
                }

                li.wp-chat-user-msg .wp-chatbot-paragraph {
                    color: ". $user_msg_text_color ." !important;
                    background: ". $user_msg_bg_color ." !important;
                } 

                ul.wp-chatbot-messages-container li:first-child.wp-chatbot-msg .wp-chatbot-paragraph  {
                    color: ". $wp_chatbot_text_color ." !important;  
                }

                ul.wp-chatbot-messages-container > li.wp-chatbot-msg > .wp-chatbot-paragraph:before,
                .wp-chatbot-bubble:before {
                    border-right: 10px solid ". $bot_msg_bg_color ." !important;

                }
                ul.wp-chatbot-messages-container > li.wp-chat-user-msg > .wp-chatbot-paragraph:before {
                    border-left: 10px solid ". $user_msg_bg_color ." !important;
                }
                #wp-chatbot-chat-container .wp-chatbot-header{
                    background: ". $header_background_color ." !important;
                }
                .wp-chatbot-container ul.wp-chatbot-messages-container > li.wp-chatbot-msg .chat-container, .wp-chatbot-container .wp-chatbot-agent-profile .wp-chatbot-bubble {
                    color:  ". $bot_msg_text_color ." !important;
                    background: ". $bot_msg_bg_color ." !important;
                }
            ";
            // Apply the accumulated general chat custom styles.
            // This ensures these styles are added if the custom color option is enabled.
            wp_add_inline_style( 'qcld-wp-chatbot-common-style', $inline_chat_custom_styles );
        }
      $custom_colors ="";
       if((get_option('enable_wp_chatbot_custom_color')==1) && $user_font != ''){    
        $custom_colors .="


        #wp-chatbot-messages-container > li.wp-chatbot-msg > .wp-chatbot-paragraph,
                #wp-chatbot-messages-container > li.wp-chatbot-msg > span{
                    font-family: ".$bot_font_family->fontFamily.";
                    font-weight: ".$bot_font_family->fontWeight.";
                    font-style: ".$bot_font_family->fontStyle.";
                    font-size: ". get_option('wp_chatbot_font_size'). "px;
                }

                ul.wp-chatbot-messages-container > li.wp-chatbot-msg .wp-chatbot-paragraph, .wp-chatbot-agent-profile .wp-chatbot-bubble {
                    font-family: ".$bot_font_family->fontFamily.";
                    font-weight: ".$bot_font_family->fontWeight.";
                    font-style: ".$bot_font_family->fontStyle.";
                    font-size: ". get_option('wp_chatbot_font_size'). "px;
                }

                ul#wp-chatbot-messages-container .wp-chatbot-textanimation span {
                    font-family: ".$bot_font_family->fontFamily.";
                    font-weight: ".$bot_font_family->fontWeight.";
                    font-style: ".$bot_font_family->fontStyle.";
                    font-size: ". get_option('wp_chatbot_font_size'). "px;
                }

                ";
        }
        if ( get_option('enable_wp_chatbot_custom_color') == 1 && ! empty($bot_font) ) {

            // Sanitize each CSS-related value
            $font_family  = isset( $user_font_family->fontfamily ) ? esc_attr( $user_font_family->fontfamily ) : 'inherit';
            $font_weight  = isset( $user_font_family->fontWeight ) ? esc_attr( $user_font_family->fontWeight ) : 'normal';
            $font_style   = isset( $user_font_family->fontStyle )  ? esc_attr( $user_font_family->fontStyle )  : 'normal';
            $font_size    = get_option( 'wp_chatbot_font_size' );
            $font_size    = esc_attr( trim( $font_size ) );

            // Optional: ensure font size includes units.
            if ( ! preg_match( '/(px|em|rem|%)$/', $font_size ) ) {
                $font_size .= 'px'; // fallback to px if unit missing.
            }

            $custom_colors  .= "
                #wp-chatbot-messages-container > li.wp-chat-user-msg > .wp-chatbot-paragraph {
                    font-family: {$font_family};
                    font-weight: {$font_weight};
                    font-style: {$font_style};
                    font-size: {$font_size};
                }
            ";

            wp_add_inline_style( 'qcld-wp-chatbot-common-style', $custom_colors );
        }

        if(get_option('wp_chatbot_custom_css')!=""){
            
            wp_add_inline_style( 'qcld-wp-chatbot-common-style', get_option('wp_chatbot_custom_css') );
        }
    }
    public function qcld_wb_chatbot_str_replace($messages=array()){
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array()
            ),
            'div' => array(
                'class' => array()
            ),
            'span' => array(
                'class' => array()
            ), 
            'img' => array(
                'src' => array()
            ), 
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'b' => array(),
            'br' => array(),
            'em' => array(),
            'ul' => array(),
            'li' => array(),
            'strong' => array(),
        );
        $refined_mesgses=array();
        if(!empty($messages) && is_array($messages)){
            foreach ($messages as $message){
                $from = array('&lt;', '&gt;');
                $to = array('<', '>');
                if(!is_array($message)){
                    $message = str_replace($from, $to, $message);
                    $msg = wp_kses( ($message), $allowed_html);
                    $refined_msg=str_replace('\\', '', $msg);
                    array_push($refined_mesgses,$refined_msg);
                }
            }
        }else{
            $from = array('&lt;', '&gt;');
            $to = array('<', '>');
            
            $message = str_replace($from, $to, $messages);
            if(!empty($messages )){
                $msg = wp_kses( $messages, $allowed_html);
            }else{
                $msg = '';
            }
            $refined_msg=str_replace('\\', '', $msg);
            array_push($refined_mesgses,$refined_msg);
        
        }
        return $refined_mesgses;
    
    }
    //getting exact agent icon path
    public  function qcld_wb_chatbot_agent_icon(){
		
        if(get_option('wp_chatbot_custom_agent_path')!="" && get_option('wp_chatbot_agent_image')=="custom-agent.png"  ){
            $wp_chatbot_custom_icon_path=get_option('wp_chatbot_custom_agent_path');
        }
		else if(get_option('wp_chatbot_custom_agent_path')!="" && get_option('wp_chatbot_agent_image')!="custom-agent.png"){
            $wp_chatbot_custom_icon_path=QCLD_wpCHATBOT_IMG_URL.get_option('wp_chatbot_agent_image');
        }
		else
		{
			if(get_option('wp_chatbot_agent_image')!=''){
				$wp_chatbot_custom_icon_path=QCLD_wpCHATBOT_IMG_URL.get_option('wp_chatbot_agent_image');
			}else{
				$wp_chatbot_custom_icon_path=QCLD_wpCHATBOT_IMG_URL.'custom-agent.png';
			}
            
        }
		
        return $wp_chatbot_custom_icon_path;
    }
    /**
     * Render the admin page
     */
	 
    public function qcld_wb_chatbot_admin_page()
    {
        global $wpcommerce;
        $action = 'admin.php?page=wpbot-panel';
        require_once("admin_ui2.php");
    }
	
	public function qcld_wb_chatbot_admin_page_settings()
    {
        global $wpcommerce;
        $action = 'admin.php?page=wpbot';
        
        require_once("admin_ui.php");
    }

    public function qcld_wb_chatbot_dynamic_multi_option($options, $option_name, $option_text)
    {
        ?>

<h4 class="qc-opt-title">
  <?php echo esc_html($option_text); ?>
</h4>
<div class="wp-chatbot-lng-items">
  <?php
            if (is_array($options) && count($options) > 0) {
                foreach ($options as $key => $value) {
                    ?>
  <div class="row" class="wp-chatbot-lng-item">
  <div class="col-xs-11">
    <input type="text"
                                   class="form-control qc-opt-dcs-font"
                                   name="<?php echo esc_attr( $option_name ); ?>[]"
                                   value="<?php echo esc_attr(str_replace('\\', '', $value)); ?>">
  </div>
  <div class="col-xs-1">
    <button type="button" class="btn btn-danger btn-sm wp-chatbot-lng-item-remove"> <span class="glyphicon glyphicon-remove"></span> </button>
  </div>
</div>
<?php
                }
            } else { ?>
<div class="row" class="wp-chatbot-lng-item">
<div class="col-xs-11">
  <input type="text"
                               class="form-control qc-opt-dcs-font"
                               name="<?php echo esc_attr( $option_name ); ?>[]"
                               value="<?php echo esc_attr($option_text); ?>">
</div>
<div class="col-xs-1"> <span class="wp-chatbot-lng-item-remove">
  <?php esc_html_e('X', 'chatbot'); ?>
  </span> </div>
</div>
<?php } ?>
</div>
<div class="row">
  <div class="col-sm-1 col-sm-offset-11">
    <button type="button" class="btn btn-success btn-sm wp-chatbot-lng-item-add"> <span class="glyphicon glyphicon-plus"></span> </button>
  </div>
</div>
<?php
    }
 
    function qcld_wb_chatbot_save_options()
    {
        //global $wpcommerce;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wp_chatbot')) {
            // Check if the form is submitted or not.
            $submit = sanitize_text_field($_POST['submit']);
            if (isset($submit)) {
                //wpwboticon position settings.
                if (isset($_POST["wp_chatbot_position_x"])) {
                    $wp_chatbot_position_x = intval(sanitize_text_field($_POST["wp_chatbot_position_x"]));
                    update_option('wp_chatbot_position_x', $wp_chatbot_position_x);
                }
                if (isset($_POST["wp_chatbot_position_y"])) {
                    $wp_chatbot_position_y = intval(sanitize_text_field($_POST["wp_chatbot_position_y"]));
                    update_option('wp_chatbot_position_y', $wp_chatbot_position_y);
                }
                //product search options
				if(isset($_POST['qlcd_wp_chatbot_search_option'])){
					$qlcd_wp_chatbot_search_option = sanitize_text_field($_POST['qlcd_wp_chatbot_search_option']);
					update_option('qlcd_wp_chatbot_search_option', $qlcd_wp_chatbot_search_option);
				}
                

                if(isset( $_POST["skip_wp_greetings_donot_show_menu"])){
                    $skip_wp_greetings_donot_show_menu = sanitize_text_field($_POST["skip_wp_greetings_donot_show_menu"]);
                }else{ $skip_wp_greetings_donot_show_menu='';}
                update_option('skip_wp_greetings_donot_show_menu', wp_unslash($skip_wp_greetings_donot_show_menu));

                if(isset( $_POST["skip_wp_greetings"])){
                    $skip_wp_greetings = sanitize_text_field($_POST["skip_wp_greetings"]);
                }else{ $skip_wp_greetings='';}
                update_option('skip_wp_greetings', wp_unslash($skip_wp_greetings));
                //Enable /disable wpwbot
               if(isset( $_POST["disable_wp_chatbot"])){
                   $disable_wp_chatbot = sanitize_text_field($_POST["disable_wp_chatbot"]);
               }else{ $disable_wp_chatbot='';}
                update_option('disable_wp_chatbot', wp_unslash($disable_wp_chatbot));
				
				if(isset( $_POST["qlcd_wp_chatbot_admin_email"])){
                   $qlcd_wp_chatbot_admin_email = sanitize_email($_POST["qlcd_wp_chatbot_admin_email"]);
               }else{ $qlcd_wp_chatbot_admin_email='';}
                update_option('qlcd_wp_chatbot_admin_email', wp_unslash($qlcd_wp_chatbot_admin_email));


                if(isset( $_POST["open_links_new_window"])){
                    $open_links_new_window = sanitize_text_field($_POST["open_links_new_window"]);
                }else{ $open_links_new_window='';}
                 update_option('open_links_new_window', wp_unslash($open_links_new_window));
				
				
				if(isset( $_POST["qlcd_wp_chatbot_from_email"])){
                   $qlcd_wp_chatbot_from_email = sanitize_email($_POST["qlcd_wp_chatbot_from_email"]);
               }else{ $qlcd_wp_chatbot_from_email='';}
                update_option('qlcd_wp_chatbot_from_email', wp_unslash($qlcd_wp_chatbot_from_email));
				
                if(isset( $_POST["disable_wp_chatbot_on_mobile"])) {
                    $disable_wp_chatbot_on_mobile = sanitize_text_field($_POST["disable_wp_chatbot_on_mobile"]);
                }else{ $disable_wp_chatbot_on_mobile='';}
                update_option('disable_wp_chatbot_on_mobile', wp_unslash($disable_wp_chatbot_on_mobile));
                if(isset( $_POST["disable_wp_chatbot_product_search"])) {
                $disable_wp_chatbot_product_search = sanitize_text_field($_POST["disable_wp_chatbot_product_search"]);
                }else{ $disable_wp_chatbot_product_search='';}
                update_option('disable_wp_chatbot_product_search', wp_unslash($disable_wp_chatbot_product_search));
                if(isset( $_POST["disable_wp_chatbot_catalog"])) {
                $disable_wp_chatbot_catalog= sanitize_text_field($_POST["disable_wp_chatbot_catalog"]);
                }else{ $disable_wp_chatbot_catalog='';}
                update_option('disable_wp_chatbot_catalog', wp_unslash($disable_wp_chatbot_catalog));
                if(isset( $_POST["disable_wp_chatbot_order_status"])) {
                    $disable_wp_chatbot_order_status = sanitize_text_field($_POST["disable_wp_chatbot_order_status"]);
                }else{ $disable_wp_chatbot_order_status='';}
                update_option('disable_wp_chatbot_order_status', wp_unslash($disable_wp_chatbot_order_status));
                if(isset( $_POST["disable_wp_chatbot_notification"])) {
                    $disable_wp_chatbot_notification = sanitize_text_field($_POST["disable_wp_chatbot_notification"]);
                }else{ $disable_wp_chatbot_notification='1';}
                update_option('disable_wp_chatbot_notification', wp_unslash($disable_wp_chatbot_notification));

                if(isset( $_POST["enable_wp_chatbot_rtl"])) {
                    $enable_wp_chatbot_rtl = sanitize_text_field($_POST["enable_wp_chatbot_rtl"]);
                }else{ $enable_wp_chatbot_rtl='';}
                update_option('enable_wp_chatbot_rtl', wp_unslash($enable_wp_chatbot_rtl));
				
				if(isset( $_POST["show_menu_after_greetings"])) {
                    $show_menu_after_greetings = sanitize_text_field($_POST["show_menu_after_greetings"]);
                }else{ $show_menu_after_greetings='';}
                update_option('show_menu_after_greetings', wp_unslash($show_menu_after_greetings));
                if(isset( $_POST["enable_chat_session"])) {
                    $enable_chat_session = sanitize_text_field($_POST["enable_chat_session"]);
                }else{ $enable_chat_session='';}
                update_option('enable_chat_session', wp_unslash($enable_chat_session));
                

               if(isset( $_POST["enable_wp_chatbot_mobile_full_screen"])) {
                    $enable_wp_chatbot_mobile_full_screen = sanitize_text_field($_POST["enable_wp_chatbot_mobile_full_screen"]);
                }else{ $enable_wp_chatbot_mobile_full_screen='';}
                update_option('enable_wp_chatbot_mobile_full_screen', wp_unslash($enable_wp_chatbot_mobile_full_screen));
                
                if(isset( $_POST["wpbot_preloading_time"])) {
                    $wpbot_preloading_time = sanitize_text_field($_POST["wpbot_preloading_time"]);
                }else{ $wpbot_preloading_time='800';}
                update_option('wpbot_preloading_time', wp_unslash($wpbot_preloading_time));

                if(isset( $_POST["disable_wp_chatbot_icon_animation"])) {
                    $disable_wp_chatbot_icon_animation = sanitize_text_field($_POST["disable_wp_chatbot_icon_animation"]);
                }else{ $disable_wp_chatbot_icon_animation='';}
                update_option('disable_wp_chatbot_icon_animation', wp_unslash($disable_wp_chatbot_icon_animation));
                //Enable /disable Cart Item Number
                if(isset( $_POST["disable_wp_chatbot_cart_item_number"])) {
                    $disable_wp_chatbot_cart_item_number = sanitize_text_field($_POST["disable_wp_chatbot_cart_item_number"]);
                }else{ $disable_wp_chatbot_cart_item_number='';}
                update_option('disable_wp_chatbot_cart_item_number', wp_unslash($disable_wp_chatbot_cart_item_number));
                //Enable /disable featured products button.
                if(isset( $_POST["disable_wp_chatbot_featured_product"])) {
                    $disable_wp_chatbot_featured_product = sanitize_text_field($_POST["disable_wp_chatbot_featured_product"]);
                }else{ $disable_wp_chatbot_featured_product='';}
                update_option('disable_wp_chatbot_featured_product', wp_unslash($disable_wp_chatbot_featured_product));
                //Enable /disable sale products button
                if(isset( $_POST["disable_wp_chatbot_sale_product"])) {
                    $disable_wp_chatbot_sale_product = sanitize_text_field($_POST["disable_wp_chatbot_sale_product"]);
                }else{ $disable_wp_chatbot_sale_product='';}
                update_option('disable_wp_chatbot_sale_product', wp_unslash($disable_wp_chatbot_sale_product));
                //Enable Product details page.
                if(isset( $_POST["wp_chatbot_open_product_detail"])) {
                    $wp_chatbot_open_product_detail = sanitize_text_field($_POST["wp_chatbot_open_product_detail"]);
                }else{ $wp_chatbot_open_product_detail='';}
                update_option('wp_chatbot_open_product_detail', wp_unslash($wp_chatbot_open_product_detail));
                //product order and order by
				if(isset($_POST['qlcd_wp_chatbot_product_orderby'])){
					$qlcd_wp_chatbot_product_orderby = sanitize_text_field($_POST['qlcd_wp_chatbot_product_orderby']);
					update_option('qlcd_wp_chatbot_product_orderby', sanitize_text_field($qlcd_wp_chatbot_product_orderby));
				}
				if(isset($_POST['qlcd_wp_chatbot_product_order'])){
					$qlcd_wp_chatbot_product_order = sanitize_text_field($_POST['qlcd_wp_chatbot_product_order']);
					update_option('qlcd_wp_chatbot_product_order', sanitize_text_field($qlcd_wp_chatbot_product_order));
				}
                
				
                //Product per page settings.
                if (isset($_POST["qlcd_wp_chatbot_ppp"])) {
                    $qlcd_wp_chatbot_ppp = sanitize_text_field($_POST["qlcd_wp_chatbot_ppp"]);
                    update_option('qlcd_wp_chatbot_ppp', intval($qlcd_wp_chatbot_ppp));
                }
                if(isset( $_POST["wp_chatbot_exclude_stock_out_product"])) {
                $wp_chatbot_exclude_stock_out_product = sanitize_text_field($_POST['wp_chatbot_exclude_stock_out_product']);
                }else{ $wp_chatbot_exclude_stock_out_product='';}
                update_option('wp_chatbot_exclude_stock_out_product', wp_unslash($wp_chatbot_exclude_stock_out_product));
                if(isset( $_POST["wp_chatbot_show_parent_category"])) {
                    $wp_chatbot_show_parent_category = sanitize_text_field($_POST['wp_chatbot_show_parent_category']);
                }else{ $wp_chatbot_show_parent_category='';}
                update_option('wp_chatbot_show_parent_category', wp_unslash($wp_chatbot_show_parent_category));
                if(isset( $_POST["wp_chatbot_show_sub_category"])) {
                    $wp_chatbot_show_sub_category = sanitize_text_field($_POST['wp_chatbot_show_sub_category']);
                }else{ $wp_chatbot_show_sub_category='';}
                update_option('wp_chatbot_show_sub_category', wp_unslash($wp_chatbot_show_sub_category));
                if (isset($_POST["qlcd_wp_chatbot_order_user"])) {
                    $qlcd_wp_chatbot_order_user = sanitize_text_field($_POST["qlcd_wp_chatbot_order_user"]);
                    update_option('qlcd_wp_chatbot_order_user', sanitize_text_field($qlcd_wp_chatbot_order_user));
                }
				
				if(isset( $_POST["qc_wpbot_menu_order"]) && !empty($_POST["qc_wpbot_menu_order"])) {
                    $qc_wpbot_menu_order = wp_kses_post($_POST["qc_wpbot_menu_order"]);
                }else{ $qc_wpbot_menu_order='';}
                update_option('qc_wpbot_menu_order', ($qc_wpbot_menu_order));
				
                //wpwBot Load control
				if(isset($_POST["wp_chatbot_show_home_page"])){
					$wp_chatbot_show_home_page = sanitize_key(($_POST["wp_chatbot_show_home_page"]));
					update_option('wp_chatbot_show_home_page', $wp_chatbot_show_home_page);
				}
               
				
				if(isset($_POST["wp_chatbot_show_posts"])){
					$wp_chatbot_show_posts = sanitize_key(($_POST["wp_chatbot_show_posts"]));
					update_option('wp_chatbot_show_posts', $wp_chatbot_show_posts);
				}
                
				
				if(isset($_POST["wp_chatbot_show_pages"])){
					$wp_chatbot_show_pages = sanitize_key(($_POST["wp_chatbot_show_pages"]));
					update_option('wp_chatbot_show_pages', $wp_chatbot_show_pages);
				}
                
                if(isset( $_POST["wp_chatbot_show_pages_list"])) {
                    $wp_chatbot_show_pages_list = wp_parse_id_list($_POST["wp_chatbot_show_pages_list"]);
                    update_option('wp_chatbot_show_pages_list', maybe_serialize(sanitize_array($wp_chatbot_show_pages_list)));
                }else{
                    $wp_chatbot_show_pages_list='';
                    update_option('wp_chatbot_show_pages_list', maybe_serialize(sanitize_array($wp_chatbot_show_pages_list)));
                }
                if(isset( $_POST["wp_chatbot_exclude_post_list"])) {
                    $wp_chatbot_exclude_post_list = $_POST["wp_chatbot_exclude_post_list"];
                    update_option('wp_chatbot_exclude_post_list', maybe_serialize(sanitize_array($wp_chatbot_exclude_post_list)));
                }else{ 
                    $wp_chatbot_exclude_post_list='';
                    update_option('wp_chatbot_exclude_post_list', maybe_serialize(sanitize_array($wp_chatbot_exclude_post_list)));
                }

				if(isset($_POST["wp_chatbot_show_wpcommerce"])){
					$wp_chatbot_show_wpcommerce = sanitize_key(($_POST["wp_chatbot_show_wpcommerce"]));
					update_option('wp_chatbot_show_wpcommerce', $wp_chatbot_show_wpcommerce);
				}
                
				
                //Stop Words Settings
                if (isset($_POST["qlcd_wp_chatbot_stop_words_name"])) {
                    $qlcd_wp_chatbot_stop_words_name = sanitize_text_field($_POST["qlcd_wp_chatbot_stop_words_name"]);
                    update_option('qlcd_wp_chatbot_stop_words_name', $qlcd_wp_chatbot_stop_words_name);
                }
                if (isset($_POST["qlcd_wp_chatbot_stop_words"])) {
                    $qlcd_wp_chatbot_stop_words = sanitize_text_field($_POST["qlcd_wp_chatbot_stop_words"]);
                    update_option('qlcd_wp_chatbot_stop_words', $qlcd_wp_chatbot_stop_words);
                }
                //wpwbot icon settings.
                $wp_chatbot_icon = isset( $_POST['wp_chatbot_icon'] ) ? sanitize_text_field($_POST['wp_chatbot_icon']) : 'icon-3.png';
                update_option('wp_chatbot_icon', $wp_chatbot_icon);
				
				$wp_chatbot_floatingiconbg_color = isset( $_POST['wp_chatbot_floatingiconbg_color'] ) ? sanitize_text_field($_POST['wp_chatbot_floatingiconbg_color']) : '#fff';
                update_option('wp_chatbot_floatingiconbg_color', $wp_chatbot_floatingiconbg_color);
				
                // upload custom wpwbot icon path
                 $wp_chatbot_custom_icon_path = sanitize_text_field($_POST['wp_chatbot_custom_icon_path']);
                 update_option('wp_chatbot_custom_icon_path', $wp_chatbot_custom_icon_path);
                 //Agent image
                //wpwbot icon settings.
                $wp_chatbot_icon = (isset($_POST['wp_chatbot_agent_image']) ? sanitize_text_field($_POST['wp_chatbot_agent_image']) : 'agent-13.png');
                 update_option('wp_chatbot_agent_image', $wp_chatbot_icon);
                // upload custom wpwbot icon
				if(isset($_POST['wp_chatbot_custom_agent_path'])){
					$wp_chatbot_custom_agent_path = sanitize_text_field($_POST['wp_chatbot_custom_agent_path']);
					update_option('wp_chatbot_custom_agent_path', $wp_chatbot_custom_agent_path);
				}
                
                //Theming
                $qcld_wb_chatbot_theme = (isset($_POST['qcld_wb_chatbot_theme']) ? sanitize_text_field($_POST['qcld_wb_chatbot_theme']) : 'template-00');
                 update_option('qcld_wb_chatbot_theme', $qcld_wb_chatbot_theme);
                //Theme custom background option
                if(isset( $_POST["qcld_wb_chatbot_change_bg"])) {
                    $qcld_wb_chatbot_change_bg = sanitize_text_field($_POST["qcld_wb_chatbot_change_bg"]);
                }else{$qcld_wb_chatbot_change_bg='';}
                update_option('qcld_wb_chatbot_change_bg', $qcld_wb_chatbot_change_bg);
				if(isset($_POST["qcld_wb_chatbot_board_bg_path"])){
					$qcld_wb_chatbot_board_bg_path = sanitize_text_field($_POST["qcld_wb_chatbot_board_bg_path"]);
					update_option('qcld_wb_chatbot_board_bg_path', wp_unslash($qcld_wb_chatbot_board_bg_path));
				}
                
       
        
				//To override style use custom css.
				if(isset($_POST["wp_chatbot_custom_css"])){
					$wp_chatbot_custom_css = wp_unslash($_POST["wp_chatbot_custom_css"]);
					update_option('wp_chatbot_custom_css',  sanitize_text_field($wp_chatbot_custom_css));
				}
                
				if (isset($_POST["qlcd_wp_chatbot_dialogflow_project_id"])) {
                    $qlcd_wp_chatbot_dialogflow_project_id = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_dialogflow_project_id"]));
                } else {
                    $qlcd_wp_chatbot_dialogflow_project_id = '';
                }
                update_option('qlcd_wp_chatbot_dialogflow_project_id', $qlcd_wp_chatbot_dialogflow_project_id);

                if (isset($_POST["wp_chatbot_df_api"])) {
                    $wp_chatbot_df_api = sanitize_text_field(wp_unslash($_POST["wp_chatbot_df_api"]));
                } else {
                    $wp_chatbot_df_api = '';
                }
                update_option('wp_chatbot_df_api', $wp_chatbot_df_api);

                if (isset($_POST["qlcd_wp_chatbot_dialogflow_project_key"])) {
                    $qlcd_wp_chatbot_dialogflow_project_key = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_dialogflow_project_key"]));
                } else {
                    $qlcd_wp_chatbot_dialogflow_project_key = '';
                }
                update_option('qlcd_wp_chatbot_dialogflow_project_key', $qlcd_wp_chatbot_dialogflow_project_key);
				
                /****Language center settings.   ****/
                //identity
                if (isset($_POST["qlcd_wp_chatbot_host"])) {
                    $qlcd_wp_chatbot_host = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_host"]));
                } else {
                    $qlcd_wp_chatbot_host = '';
                }
                update_option('qlcd_wp_chatbot_host', $qlcd_wp_chatbot_host);

                if (isset($_POST["qlcd_wp_chatbot_agent"])) {
                    $qlcd_wp_chatbot_agent = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_agent"]));
                } else {
                    $qlcd_wp_chatbot_agent = '';
                }
                update_option('qlcd_wp_chatbot_agent', $qlcd_wp_chatbot_agent);

                if (isset($_POST["qlcd_wp_chatbot_shopper_demo_name"])) {
                    $qlcd_wp_chatbot_shopper_demo_name = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_shopper_demo_name"]));
                } else {
                    $qlcd_wp_chatbot_shopper_demo_name = '';
                }
                update_option('qlcd_wp_chatbot_shopper_demo_name', $qlcd_wp_chatbot_shopper_demo_name);

                if (isset($_POST["qlcd_wp_chatbot_yes"])) {
                    $qlcd_wp_chatbot_yes = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_yes"]));
                } else {
                    $qlcd_wp_chatbot_yes = '';
                }
                update_option('qlcd_wp_chatbot_yes', $qlcd_wp_chatbot_yes);

                if (isset($_POST["qlcd_wp_chatbot_no"])) {
                    $qlcd_wp_chatbot_no = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_no"]));
                } else {
                    $qlcd_wp_chatbot_no = '';
                }
                update_option('qlcd_wp_chatbot_no', $qlcd_wp_chatbot_no);

                if (isset($_POST["qlcd_wp_chatbot_or"])) {
                    $qlcd_wp_chatbot_or = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_or"]));
                } else {
                    $qlcd_wp_chatbot_or = '';
                }
                update_option('qlcd_wp_chatbot_or', $qlcd_wp_chatbot_or);

                if (isset($_POST["qlcd_wp_chatbot_sorry"])) {
                    $qlcd_wp_chatbot_sorry = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_sorry"]));
                } else {
                    $qlcd_wp_chatbot_sorry = '';
                }
                update_option('qlcd_wp_chatbot_sorry', $qlcd_wp_chatbot_sorry);

                if (isset($_POST["skip_chat_reactions_menu"])) {
                    $skip_chat_reactions_menu = sanitize_text_field(wp_unslash($_POST["skip_chat_reactions_menu"]));
                } else {
                    $skip_chat_reactions_menu = '';
                }
                update_option('skip_chat_reactions_menu', $skip_chat_reactions_menu);

                if (isset($_POST["qlcd_wp_chatbot_report_text"])) {
                    $qlcd_wp_chatbot_report_text = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_report_text"]));
                } else {
                    $qlcd_wp_chatbot_report_text = '';
                }
                update_option('qlcd_wp_chatbot_report_text', $qlcd_wp_chatbot_report_text);

                if (isset($_POST["qlcd_wp_chatbot_report_text"])) {
                    $qlcd_wp_chatbot_report_text = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_report_text"]));
                } else {
                    $qlcd_wp_chatbot_report_text = '';
                }
                update_option('qlcd_wp_chatbot_report_text', $qlcd_wp_chatbot_report_text);

                if (isset($_POST["enable_chat_report_menu"])) {
                    $enable_chat_report_menu = sanitize_text_field(wp_unslash($_POST["enable_chat_report_menu"]));
                } else {
                    $enable_chat_report_menu = '';
                }
                update_option('enable_chat_report_menu', $enable_chat_report_menu);

                if (isset($_POST["qlcd_wp_chatbot_like_text"])) {
                    $qlcd_wp_chatbot_like_text = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_like_text"]));
                } else {
                    $qlcd_wp_chatbot_like_text = '';
                }
                update_option('qlcd_wp_chatbot_like_text', $qlcd_wp_chatbot_like_text);

                if (isset($_POST["qlcd_wp_chatbot_dislike_text"])) {
                    $qlcd_wp_chatbot_dislike_text = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_dislike_text"]));
                } else {
                    $qlcd_wp_chatbot_dislike_text = '';
                }
                update_option('qlcd_wp_chatbot_dislike_text', $qlcd_wp_chatbot_dislike_text);

                if (isset($_POST["enable_chat_share_menu"])) {
                    $enable_chat_share_menu = sanitize_text_field(wp_unslash($_POST["enable_chat_share_menu"]));
                } else {
                    $enable_chat_share_menu = '';
                }
                update_option('enable_chat_share_menu', $enable_chat_share_menu);

                if (isset($_POST["qlcd_wp_chatbot_share_text"])) {
                    $qlcd_wp_chatbot_share_text = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_share_text"]));
                } else {
                    $qlcd_wp_chatbot_share_text = '';
                }
                update_option('qlcd_wp_chatbot_share_text', $qlcd_wp_chatbot_share_text);

                if (isset($_POST["qlcd_wp_chatbot_agent_join"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_agent_join =  wp_unslash($_POST["qlcd_wp_chatbot_agent_join"]);
                    $qlcd_wp_chatbot_agent_join = sanitize_array($qlcd_wp_chatbot_agent_join);
                } else {
                    $qlcd_wp_chatbot_agent_join = array();
                }
                update_option('qlcd_wp_chatbot_agent_join', maybe_serialize($qlcd_wp_chatbot_agent_join));

                //Greeting.
                if (isset($_POST["qlcd_wp_chatbot_welcome"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_welcome =  wp_unslash($_POST["qlcd_wp_chatbot_welcome"]);
                    $qlcd_wp_chatbot_welcome = sanitize_array($qlcd_wp_chatbot_welcome);
                } else {
                    $qlcd_wp_chatbot_welcome = array();
                }
                update_option('qlcd_wp_chatbot_welcome', maybe_serialize($qlcd_wp_chatbot_welcome));

                if (isset($_POST["qlcd_wp_chatbot_back_to_start"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_back_to_start =  wp_unslash($_POST["qlcd_wp_chatbot_back_to_start"]);
                    $qlcd_wp_chatbot_back_to_start = sanitize_array($qlcd_wp_chatbot_back_to_start);
                } else {
                    $qlcd_wp_chatbot_back_to_start = array();
                }
                update_option('qlcd_wp_chatbot_back_to_start', maybe_serialize($qlcd_wp_chatbot_back_to_start));

                if (isset($_POST["qlcd_wp_chatbot_hi_there"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_hi_there =  wp_unslash($_POST["qlcd_wp_chatbot_hi_there"]);
                    $qlcd_wp_chatbot_hi_there = sanitize_array($qlcd_wp_chatbot_hi_there);
                } else {
                    $qlcd_wp_chatbot_hi_there = array();
                }
                update_option('qlcd_wp_chatbot_hi_there', maybe_serialize($qlcd_wp_chatbot_hi_there));

                if (isset($_POST["qlcd_wp_chatbot_hello"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_hello =  wp_unslash($_POST["qlcd_wp_chatbot_hello"]);
                    $qlcd_wp_chatbot_hello = sanitize_array($qlcd_wp_chatbot_hello);
                } else {
                    $qlcd_wp_chatbot_hello = array();
                }
                update_option('qlcd_wp_chatbot_hello', maybe_serialize($qlcd_wp_chatbot_hello));

                if (isset($_POST["qlcd_wp_chatbot_welcome_back"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_welcome_back =  wp_unslash( $_POST["qlcd_wp_chatbot_welcome_back"] );
                    $qlcd_wp_chatbot_welcome_back = sanitize_array( $qlcd_wp_chatbot_welcome_back );
                } else {
                    $qlcd_wp_chatbot_welcome_back = array();
                }
                update_option('qlcd_wp_chatbot_welcome_back', maybe_serialize($qlcd_wp_chatbot_welcome_back));

                if (isset($_POST["qlcd_wp_chatbot_asking_name"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_asking_name = wp_unslash( $_POST["qlcd_wp_chatbot_asking_name"] );
                    $qlcd_wp_chatbot_asking_name = sanitize_array( $qlcd_wp_chatbot_asking_name );
                } else {
                    $qlcd_wp_chatbot_asking_name = array();
                }
                update_option('qlcd_wp_chatbot_asking_name', maybe_serialize($qlcd_wp_chatbot_asking_name));

                if (isset($_POST["qlcd_wp_chatbot_name_greeting"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_name_greeting = wp_unslash( $_POST["qlcd_wp_chatbot_name_greeting"] );
                    $qlcd_wp_chatbot_name_greeting = sanitize_array( $qlcd_wp_chatbot_name_greeting );
                } else {
                    $qlcd_wp_chatbot_name_greeting = array();
                }
                update_option('qlcd_wp_chatbot_name_greeting', maybe_serialize($qlcd_wp_chatbot_name_greeting));

                if (isset($_POST["qlcd_wp_chatbot_i_am"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_i_am = wp_unslash( $_POST["qlcd_wp_chatbot_i_am"] );
                    $qlcd_wp_chatbot_i_am = sanitize_array( $qlcd_wp_chatbot_i_am );
                } else {
                    $qlcd_wp_chatbot_i_am = array();
                }
                update_option('qlcd_wp_chatbot_i_am', maybe_serialize($qlcd_wp_chatbot_i_am));

                if (isset($_POST["qlcd_wp_chatbot_is_typing"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_is_typing = wp_unslash( $_POST["qlcd_wp_chatbot_is_typing"] );
                    $qlcd_wp_chatbot_is_typing = sanitize_array( $qlcd_wp_chatbot_is_typing );
                } else {
                    $qlcd_wp_chatbot_is_typing = array();
                }
                update_option('qlcd_wp_chatbot_is_typing', maybe_serialize($qlcd_wp_chatbot_is_typing));

                if (isset($_POST["qlcd_wp_chatbot_send_a_msg"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_send_a_msg = wp_unslash( $_POST["qlcd_wp_chatbot_send_a_msg"] );
                    $qlcd_wp_chatbot_send_a_msg = sanitize_array( $qlcd_wp_chatbot_send_a_msg );
                } else {
                    $qlcd_wp_chatbot_send_a_msg = array();
                }
                update_option('qlcd_wp_chatbot_send_a_msg', maybe_serialize($qlcd_wp_chatbot_send_a_msg));

                if (isset($_POST["qlcd_wp_chatbot_choose_option"])) {
                    // Assuming 'sanitize_array' is a custom function that properly sanitizes each element of the array.
                    $qlcd_wp_chatbot_choose_option = wp_unslash( $_POST["qlcd_wp_chatbot_choose_option"] );
                    $qlcd_wp_chatbot_choose_option = sanitize_array( $qlcd_wp_chatbot_choose_option );
                } else {
                    $qlcd_wp_chatbot_choose_option = array();
                }
                update_option('qlcd_wp_chatbot_choose_option', maybe_serialize($qlcd_wp_chatbot_choose_option));

                if (isset($_POST["qlcd_wp_chatbot_viewed_products"])) {
        
                    $qlcd_wp_chatbot_viewed_products = wp_unslash( $_POST["qlcd_wp_chatbot_viewed_products"] );
                    $qlcd_wp_chatbot_viewed_products = sanitize_array( $qlcd_wp_chatbot_viewed_products );
                } else {
                    $qlcd_wp_chatbot_viewed_products = array();
                }
                update_option('qlcd_wp_chatbot_viewed_products', maybe_serialize($qlcd_wp_chatbot_viewed_products));
                
                if(isset($_POST["qlcd_wp_chatbot_shopping_cart"])){
                    $qlcd_wp_chatbot_shopping_cart= esc_html((@$_POST["qlcd_wp_chatbot_shopping_cart"]));
                    update_option('qlcd_wp_chatbot_shopping_cart', maybe_serialize(sanitize_array($qlcd_wp_chatbot_shopping_cart)));
                }

                if(isset($_POST["qlcd_wp_chatbot_add_to_cart"])){
                    $qlcd_wp_chatbot_add_to_cart= (@$_POST["qlcd_wp_chatbot_add_to_cart"]);
                    update_option('qlcd_wp_chatbot_add_to_cart', maybe_serialize(sanitize_array($qlcd_wp_chatbot_add_to_cart)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_link"])){
                    $qlcd_wp_chatbot_cart_link= @$_POST["qlcd_wp_chatbot_cart_link"];
                    update_option('qlcd_wp_chatbot_cart_link', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_link)));
                }
                if(isset($_POST["qlcd_wp_chatbot_checkout_link"])){
                    $qlcd_wp_chatbot_checkout_link= @$_POST["qlcd_wp_chatbot_checkout_link"];
                    update_option('qlcd_wp_chatbot_checkout_link', maybe_serialize(sanitize_array($qlcd_wp_chatbot_checkout_link)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_welcome"])){
                    $qlcd_wp_chatbot_cart_welcome= (@$_POST["qlcd_wp_chatbot_cart_welcome"]);
                    update_option('qlcd_wp_chatbot_cart_welcome', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_welcome)));
                }
                if(isset($_POST["qlcd_wp_chatbot_featured_product_welcome"])){
                    $qlcd_wp_chatbot_featured_product_welcome= @$_POST["qlcd_wp_chatbot_featured_product_welcome"];
                    update_option('qlcd_wp_chatbot_featured_product_welcome', maybe_serialize(sanitize_array($qlcd_wp_chatbot_featured_product_welcome)));
                }

                if(isset($_POST["qlcd_wp_chatbot_viewed_product_welcome"])){
                    $qlcd_wp_chatbot_viewed_product_welcome= @$_POST["qlcd_wp_chatbot_viewed_product_welcome"];
                    update_option('qlcd_wp_chatbot_viewed_product_welcome', maybe_serialize(sanitize_array($qlcd_wp_chatbot_viewed_product_welcome)));
                }
                if(isset($_POST["qlcd_wp_chatbot_latest_product_welcome"])){
                    $qlcd_wp_chatbot_latest_product_welcome= @$_POST["qlcd_wp_chatbot_latest_product_welcome"];
                    update_option('qlcd_wp_chatbot_latest_product_welcome', maybe_serialize(sanitize_array($qlcd_wp_chatbot_latest_product_welcome)));
                }

                if(isset($_POST["qlcd_wp_chatbot_cart_title"])){
                    $qlcd_wp_chatbot_cart_title= @$_POST["qlcd_wp_chatbot_cart_title"];
                    update_option('qlcd_wp_chatbot_cart_title', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_title)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_quantity"])){
                $qlcd_wp_chatbot_cart_quantity= @$_POST["qlcd_wp_chatbot_cart_quantity"];
                    update_option('qlcd_wp_chatbot_cart_quantity', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_quantity)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_price"])){
                    $qlcd_wp_chatbot_cart_price= @$_POST["qlcd_wp_chatbot_cart_price"];
                    update_option('qlcd_wp_chatbot_cart_price', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_price)));
                }
                if(isset($_POST["qlcd_wp_chatbot_no_cart_items"])){
                    $qlcd_wp_chatbot_no_cart_items= wp_unslash(@$_POST["qlcd_wp_chatbot_no_cart_items"]);
                    update_option('qlcd_wp_chatbot_no_cart_items', maybe_serialize(sanitize_array($qlcd_wp_chatbot_no_cart_items)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_updating"])){
                    $qlcd_wp_chatbot_cart_updating= @$_POST["qlcd_wp_chatbot_cart_updating"];
                    update_option('qlcd_wp_chatbot_cart_updating', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_updating)));
                }
                if(isset($_POST["qlcd_wp_chatbot_cart_removing"])){
                    $qlcd_wp_chatbot_cart_removing= @$_POST["qlcd_wp_chatbot_cart_removing"];
                    update_option('qlcd_wp_chatbot_cart_removing', maybe_serialize(sanitize_array($qlcd_wp_chatbot_cart_removing)));
                }
                //wpwBot wildcard  settings
                if (isset($_POST["qlcd_wp_chatbot_wildcard_msg"])) {
                    $qlcd_wp_chatbot_wildcard_msg = wp_unslash( $_POST["qlcd_wp_chatbot_wildcard_msg"] );
                    $qlcd_wp_chatbot_wildcard_msg = sanitize_array( $qlcd_wp_chatbot_wildcard_msg );

                    update_option('qlcd_wp_chatbot_wildcard_msg', maybe_serialize($qlcd_wp_chatbot_wildcard_msg));
                } else {
                    update_option('qlcd_wp_chatbot_wildcard_msg', maybe_serialize(array()));
                }
                //empty filter message repeat.
                if (isset($_POST["qlcd_wp_chatbot_empty_filter_msg"])) {
                    $qlcd_wp_chatbot_empty_filter_msg = wp_unslash( $_POST["qlcd_wp_chatbot_empty_filter_msg"] );
                    $qlcd_wp_chatbot_empty_filter_msg = sanitize_array( $qlcd_wp_chatbot_empty_filter_msg );

                    update_option('qlcd_wp_chatbot_empty_filter_msg', maybe_serialize($qlcd_wp_chatbot_empty_filter_msg));
                } else {
                    update_option('qlcd_wp_chatbot_empty_filter_msg', maybe_serialize(array()));
                }
				
				if (isset($_POST["qlcd_wp_chatbot_did_you_mean"])) {
                    $qlcd_wp_chatbot_did_you_mean = wp_unslash( $_POST["qlcd_wp_chatbot_did_you_mean"] );
                    $qlcd_wp_chatbot_did_you_mean = sanitize_array( $qlcd_wp_chatbot_did_you_mean );
                    update_option('qlcd_wp_chatbot_did_you_mean', maybe_serialize($qlcd_wp_chatbot_did_you_mean));
                } else {
                    update_option('qlcd_wp_chatbot_did_you_mean', maybe_serialize(array()));
                }
               //help welcome and message
                if (isset($_POST["qlcd_wp_chatbot_help_welcome"])) {
                    $qlcd_wp_chatbot_help_welcome = wp_unslash( $_POST["qlcd_wp_chatbot_help_welcome"] );
                    $qlcd_wp_chatbot_help_welcome = sanitize_array( $qlcd_wp_chatbot_help_welcome );
                    update_option('qlcd_wp_chatbot_help_welcome', maybe_serialize($qlcd_wp_chatbot_help_welcome));
                } else {
                    update_option('qlcd_wp_chatbot_help_welcome', maybe_serialize(array()));
                }
                if (isset($_POST["qlcd_wp_chatbot_help_msg"])) {
                    $qlcd_wp_chatbot_help_msg = wp_unslash( $_POST["qlcd_wp_chatbot_help_msg"] );
                    $qlcd_wp_chatbot_help_msg = sanitize_array( $qlcd_wp_chatbot_help_msg );
                    update_option('qlcd_wp_chatbot_help_msg', maybe_serialize($qlcd_wp_chatbot_help_msg));
                } else {
                    update_option('qlcd_wp_chatbot_help_msg', maybe_serialize(array()));
                }
                //To clear Conversation history.
                if (isset($_POST["qlcd_wp_chatbot_reset"])) {
                    $qlcd_wp_chatbot_reset = wp_unslash( $_POST["qlcd_wp_chatbot_reset"] );
                    $qlcd_wp_chatbot_reset = sanitize_array( $qlcd_wp_chatbot_reset );
                    update_option('qlcd_wp_chatbot_reset', maybe_serialize($qlcd_wp_chatbot_reset));
                } else {
                    update_option('qlcd_wp_chatbot_reset', maybe_serialize(array()));
                }
                //systems keyword.
                if (isset($_POST["qlcd_wp_chatbot_sys_key_help"])) {
                    $qlcd_wp_chatbot_sys_key_help = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_sys_key_help"]));
                    update_option('qlcd_wp_chatbot_sys_key_help', $qlcd_wp_chatbot_sys_key_help);
                } else {
                    update_option('qlcd_wp_chatbot_sys_key_help', '');
                }
                if(isset($_POST["qlcd_wp_chatbot_sys_key_product"])){
                    $qlcd_wp_chatbot_sys_key_product = esc_html((@$_POST["qlcd_wp_chatbot_sys_key_product"]));
                    update_option('qlcd_wp_chatbot_sys_key_product', sanitize_text_field($qlcd_wp_chatbot_sys_key_product));
                }
                if(isset($_POST["qlcd_wp_chatbot_sys_key_catalog"])){
                    $qlcd_wp_chatbot_sys_key_catalog = (@$_POST["qlcd_wp_chatbot_sys_key_catalog"]);
                    update_option('qlcd_wp_chatbot_sys_key_catalog', sanitize_text_field($qlcd_wp_chatbot_sys_key_catalog));
                }
                if(isset($_POST["qlcd_wp_chatbot_sys_key_order"])){
                    $qlcd_wp_chatbot_sys_key_order = (@$_POST["qlcd_wp_chatbot_sys_key_order"]);
                    update_option('qlcd_wp_chatbot_sys_key_order', sanitize_text_field($qlcd_wp_chatbot_sys_key_order));
                }
                if (isset($_POST["qlcd_wp_chatbot_sys_key_support"])) {
                    $qlcd_wp_chatbot_sys_key_support = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_sys_key_support"]));
                    update_option('qlcd_wp_chatbot_sys_key_support', $qlcd_wp_chatbot_sys_key_support);
                } else {
                    update_option('qlcd_wp_chatbot_sys_key_support', '');
                }
                if (isset($_POST["qlcd_wp_chatbot_sys_key_reset"])) {
                    $qlcd_wp_chatbot_sys_key_reset = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_sys_key_reset"]));
                    update_option('qlcd_wp_chatbot_sys_key_reset', $qlcd_wp_chatbot_sys_key_reset);
                } else {
                    update_option('qlcd_wp_chatbot_sys_key_reset', '');
                }
                if (isset($_POST["qlcd_wp_chatbot_sys_key_email"])) {
                    $qlcd_wp_chatbot_sys_key_email = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_sys_key_email"]));
                    update_option('qlcd_wp_chatbot_sys_key_email', $qlcd_wp_chatbot_sys_key_email);
                } else {
                    update_option('qlcd_wp_chatbot_sys_key_email', '');
                }
                if(isset($_POST["qlcd_wp_chatbot_wildcard_product"])){
                    $qlcd_wp_chatbot_wildcard_product = (@$_POST["qlcd_wp_chatbot_wildcard_product"]);
                    update_option('qlcd_wp_chatbot_wildcard_product', maybe_serialize(sanitize_array($qlcd_wp_chatbot_wildcard_product)));
                }
                if(isset($_POST["qlcd_wp_chatbot_wildcard_catalog"])){
                    $qlcd_wp_chatbot_wildcard_catalog = (@$_POST["qlcd_wp_chatbot_wildcard_catalog"]);
                    update_option('qlcd_wp_chatbot_wildcard_catalog', maybe_serialize(sanitize_array($qlcd_wp_chatbot_wildcard_catalog)));
                }
                if(isset($_POST["qlcd_wp_chatbot_featured_products"])){
                    $qlcd_wp_chatbot_featured_products = (@$_POST["qlcd_wp_chatbot_featured_products"]);
                    update_option('qlcd_wp_chatbot_featured_products', maybe_serialize(sanitize_array($qlcd_wp_chatbot_featured_products)));
                }
                if(isset($_POST["qlcd_wp_chatbot_sale_products"])){
                    $qlcd_wp_chatbot_sale_products = (@$_POST["qlcd_wp_chatbot_sale_products"]);
                    update_option('qlcd_wp_chatbot_sale_products', maybe_serialize(sanitize_array($qlcd_wp_chatbot_sale_products)));
                }
                
                if (isset($_POST["qlcd_wp_chatbot_wildcard_support"])) {
                    $qlcd_wp_chatbot_wildcard_support = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_wildcard_support"]));
                    update_option('qlcd_wp_chatbot_wildcard_support', $qlcd_wp_chatbot_wildcard_support);
                } else {
                    update_option('qlcd_wp_chatbot_wildcard_support', '');
                }

                if (isset($_POST["qlcd_wp_chatbot_wildcard_site_search"])) {
                    $qlcd_wp_chatbot_wildcard_site_search = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_wildcard_site_search"]));
                    update_option('qlcd_wp_chatbot_wildcard_site_search', $qlcd_wp_chatbot_wildcard_site_search);
                } else {
                    update_option('qlcd_wp_chatbot_wildcard_site_search', '');
                }
                
                if(isset($_POST["qlcd_wp_chatbot_messenger_label"])){
                    $qlcd_wp_chatbot_messenger_label = (@$_POST["qlcd_wp_chatbot_messenger_label"]);
                    update_option('qlcd_wp_chatbot_messenger_label', maybe_serialize(sanitize_array($qlcd_wp_chatbot_messenger_label)));
                }
                //Products search .
                if (isset($_POST["qlcd_wp_chatbot_product_success"])) {
                    // Unslash the input first, then sanitize deeply as text, assuming it's an array of texts or a single text.
                    $unslashed_data = wp_unslash($_POST["qlcd_wp_chatbot_product_success"]);
                    $qlcd_wp_chatbot_product_success = map_deep($unslashed_data, 'sanitize_text_field');
                    update_option('qlcd_wp_chatbot_product_success', maybe_serialize($qlcd_wp_chatbot_product_success));
                }
                if (isset($_POST["qlcd_wp_chatbot_product_fail"])) {
                    $qlcd_wp_chatbot_product_fail = @$_POST["qlcd_wp_chatbot_product_fail"];
                    update_option('qlcd_wp_chatbot_product_fail', maybe_serialize(sanitize_array($qlcd_wp_chatbot_product_fail)));
                }
                if(isset($_POST["qlcd_wp_chatbot_product_asking"])){
                    $qlcd_wp_chatbot_product_asking = @$_POST["qlcd_wp_chatbot_product_asking"];
                    update_option('qlcd_wp_chatbot_product_asking', maybe_serialize(sanitize_array($qlcd_wp_chatbot_product_asking)));
                }
                if(isset($_POST["qlcd_wp_chatbot_product_suggest"])){   
                    $qlcd_wp_chatbot_product_suggest = @$_POST["qlcd_wp_chatbot_product_suggest"];
                    update_option('qlcd_wp_chatbot_product_suggest', maybe_serialize(sanitize_array($qlcd_wp_chatbot_product_suggest)));
                }
                if(isset($_POST["qlcd_wp_chatbot_product_infinite"])){
                    $qlcd_wp_chatbot_product_infinite = @$_POST["qlcd_wp_chatbot_product_infinite"];
                    update_option('qlcd_wp_chatbot_product_infinite', maybe_serialize(sanitize_array($qlcd_wp_chatbot_product_infinite)));
                }
                if(isset($_POST["qlcd_wp_chatbot_load_more"])){
                    $qlcd_wp_chatbot_load_more = @$_POST["qlcd_wp_chatbot_load_more"];
                    update_option('qlcd_wp_chatbot_load_more', maybe_serialize(sanitize_array($qlcd_wp_chatbot_load_more)));
                }
                //Order
                if(isset($_POST["qlcd_wp_chatbot_wildcard_order"])){
                    $qlcd_wp_chatbot_wildcard_order = @$_POST["qlcd_wp_chatbot_wildcard_order"];
                    update_option('qlcd_wp_chatbot_wildcard_order', maybe_serialize(sanitize_array($qlcd_wp_chatbot_wildcard_order)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_welcome"])){
                    $qlcd_wp_chatbot_order_welcome = @$_POST["qlcd_wp_chatbot_order_welcome"];
                    update_option('qlcd_wp_chatbot_order_welcome', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_welcome)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_username_asking"])){
                    $qlcd_wp_chatbot_order_username_asking = @$_POST["qlcd_wp_chatbot_order_username_asking"];
                    update_option('qlcd_wp_chatbot_order_username_asking', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_username_asking)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_username_not_exist"])){
                    $qlcd_wp_chatbot_order_username_not_exist = @$_POST["qlcd_wp_chatbot_order_username_not_exist"];
                    update_option('qlcd_wp_chatbot_order_username_not_exist', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_username_not_exist)));
                }
                if (isset($_POST["qlcd_wp_chatbot_order_username_thanks"])) {
                    // Unslash the input first, then sanitize deeply as text, assuming it's an array of texts or a single text.
                    $unslashed_data = wp_unslash($_POST["qlcd_wp_chatbot_order_username_thanks"]);
                    $qlcd_wp_chatbot_order_username_thanks = map_deep($unslashed_data, 'sanitize_text_field');
                    update_option('qlcd_wp_chatbot_order_username_thanks', maybe_serialize($qlcd_wp_chatbot_order_username_thanks));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_username_password"])){
                    $qlcd_wp_chatbot_order_username_password = @$_POST["qlcd_wp_chatbot_order_username_password"];
                    update_option('qlcd_wp_chatbot_order_username_password', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_username_password)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_password_incorrect"])){
                    $qlcd_wp_chatbot_order_password_incorrect= @$_POST["qlcd_wp_chatbot_order_password_incorrect"];
                    update_option('qlcd_wp_chatbot_order_password_incorrect', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_password_incorrect)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_not_found"])){
                    $qlcd_wp_chatbot_order_not_found= @$_POST["qlcd_wp_chatbot_order_not_found"];
                    update_option('qlcd_wp_chatbot_order_not_found', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_not_found)));
                }
                if(isset($_POST["qlcd_wp_chatbot_order_found"])){
                    $qlcd_wp_chatbot_order_found= @$_POST["qlcd_wp_chatbot_order_found"];
                    update_option('qlcd_wp_chatbot_order_found', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_found)));
                }
                if(isset( $_POST["qlcd_wp_chatbot_order_email_support"])){
                    $qlcd_wp_chatbot_order_email_support= @$_POST["qlcd_wp_chatbot_order_email_support"];
                    update_option('qlcd_wp_chatbot_order_email_support', maybe_serialize(sanitize_array($qlcd_wp_chatbot_order_email_support)));
                }
                //Support
                if (isset($_POST["qlcd_wp_chatbot_support_welcome"])) {
                    $qlcd_wp_chatbot_support_welcome = sanitize_array($_POST["qlcd_wp_chatbot_support_welcome"]);
                    update_option('qlcd_wp_chatbot_support_welcome', maybe_serialize($qlcd_wp_chatbot_support_welcome));
                }
                if (isset($_POST["qlcd_wp_chatbot_support_email"])) {
                    $qlcd_wp_chatbot_support_email = sanitize_text_field($_POST["qlcd_wp_chatbot_support_email"]);
                    update_option('qlcd_wp_chatbot_support_email', $qlcd_wp_chatbot_support_email);
                }
                if (isset($_POST["qlcd_wp_chatbot_asking_email"])) {
                    $qlcd_wp_chatbot_asking_email = sanitize_array($_POST["qlcd_wp_chatbot_asking_email"]);
                    update_option('qlcd_wp_chatbot_asking_email', maybe_serialize($qlcd_wp_chatbot_asking_email));
                }
                if (isset($_POST["qlcd_wp_chatbot_asking_msg"])) {
                    $qlcd_wp_chatbot_asking_msg = sanitize_array($_POST["qlcd_wp_chatbot_asking_msg"]);
                    update_option('qlcd_wp_chatbot_asking_msg', maybe_serialize($qlcd_wp_chatbot_asking_msg));
                }
				
                if(isset($_POST["qlcd_wp_chatbot_no_result"])){
                    $qlcd_wp_chatbot_no_result = @$_POST["qlcd_wp_chatbot_no_result"];
                    update_option('qlcd_wp_chatbot_no_result', maybe_serialize(sanitize_array($qlcd_wp_chatbot_no_result)));
                }
				
                if (isset($_POST["qlcd_wp_chatbot_support_option_again"])) {
                    $qlcd_wp_chatbot_support_option_again = $_POST["qlcd_wp_chatbot_support_option_again"];
                    update_option('qlcd_wp_chatbot_support_option_again', maybe_serialize(sanitize_array($qlcd_wp_chatbot_support_option_again)));
                } else {
                    update_option('qlcd_wp_chatbot_support_option_again', maybe_serialize(array()));
                }

                if (isset($_POST["qlcd_wp_chatbot_invalid_email"])) {
                    $qlcd_wp_chatbot_invalid_email = $_POST["qlcd_wp_chatbot_invalid_email"];
                    update_option('qlcd_wp_chatbot_invalid_email', maybe_serialize(sanitize_array($qlcd_wp_chatbot_invalid_email)));
                } else {
                    update_option('qlcd_wp_chatbot_invalid_email', maybe_serialize(array()));
                }

                if (isset($_POST["qlcd_wp_chatbot_support_phone"])) {
                    $qlcd_wp_chatbot_support_phone = sanitize_text_field($_POST["qlcd_wp_chatbot_support_phone"]);
                    update_option('qlcd_wp_chatbot_support_phone', $qlcd_wp_chatbot_support_phone);
                } else {
                    update_option('qlcd_wp_chatbot_support_phone', '');
                }
				
                if (isset($_POST["qlcd_wp_chatbot_asking_phone"])) {
                    $qlcd_wp_chatbot_asking_phone = $_POST["qlcd_wp_chatbot_asking_phone"];
                    update_option('qlcd_wp_chatbot_asking_phone', maybe_serialize(sanitize_array($qlcd_wp_chatbot_asking_phone)));
                } else {
                    update_option('qlcd_wp_chatbot_asking_phone', maybe_serialize(array()));
                }

                if (isset($_POST["qlcd_wp_chatbot_thank_for_phone"])) {
                    $qlcd_wp_chatbot_thank_for_phone = $_POST["qlcd_wp_chatbot_thank_for_phone"];
                    update_option('qlcd_wp_chatbot_thank_for_phone', maybe_serialize(sanitize_array($qlcd_wp_chatbot_thank_for_phone)));
                } else {
                    update_option('qlcd_wp_chatbot_thank_for_phone', maybe_serialize(array()));
                }

                if (isset($_POST["qlcd_wp_chatbot_admin_email"])) {
                    $qlcd_wp_chatbot_admin_email = sanitize_email($_POST["qlcd_wp_chatbot_admin_email"]);
                } else {
                    $qlcd_wp_chatbot_admin_email = '';
                }
                
                update_option('qlcd_wp_chatbot_admin_email', $qlcd_wp_chatbot_admin_email);
				
                // Sanitize early for qlcd_wp_chatbot_email_sub
                $qlcd_wp_chatbot_email_sub = '';
                if (isset($_POST["qlcd_wp_chatbot_email_sub"])) {
                    $qlcd_wp_chatbot_email_sub = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_email_sub"]));
                }
                update_option('qlcd_wp_chatbot_email_sub', $qlcd_wp_chatbot_email_sub);
				
                // Sanitize early for qlcd_wp_site_search, only update if set
                if (isset($_POST["qlcd_wp_site_search"])) {
                    $qlcd_wp_site_search = sanitize_text_field(wp_unslash($_POST["qlcd_wp_site_search"]));
                    update_option('qlcd_wp_site_search', $qlcd_wp_site_search);
                }
				
                // Sanitize early for qlcd_wp_chatbot_email_sent
                $qlcd_wp_chatbot_email_sent = '';
                if (isset($_POST["qlcd_wp_chatbot_email_sent"])) {
                    $qlcd_wp_chatbot_email_sent = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_email_sent"]));
                }
                update_option('qlcd_wp_chatbot_email_sent', $qlcd_wp_chatbot_email_sent);

                // Sanitize early for qlcd_wp_chatbot_email_fail
                $qlcd_wp_chatbot_email_fail = '';
                if (isset($_POST["qlcd_wp_chatbot_email_fail"])) {
                    $qlcd_wp_chatbot_email_fail = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_email_fail"]));
                }
                update_option('qlcd_wp_chatbot_email_fail', $qlcd_wp_chatbot_email_fail);

                // Sanitize early for qlcd_wp_chatbot_relevant_post_link_openai
                $qlcd_wp_chatbot_relevant_post_link_openai = '';
                if (isset($_POST["qlcd_wp_chatbot_relevant_post_link_openai"])) {
                    $qlcd_wp_chatbot_relevant_post_link_openai = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_relevant_post_link_openai"]));
                }
                update_option('qlcd_wp_chatbot_relevant_post_link_openai', $qlcd_wp_chatbot_relevant_post_link_openai);
                //Notifications messages building.
                if (isset($_POST["qlcd_wp_chatbot_notification_interval"])) {
                    $qlcd_wp_chatbot_notification_interval = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_notification_interval"]));
                    update_option('qlcd_wp_chatbot_notification_interval', $qlcd_wp_chatbot_notification_interval);
                }

                if(isset($_POST["qlcd_wp_chatbot_notifications"])){
                    $qlcd_wp_chatbot_notifications = @$_POST["qlcd_wp_chatbot_notifications"];
                    update_option('qlcd_wp_chatbot_notifications', maybe_serialize(sanitize_array($qlcd_wp_chatbot_notifications)));
                }
                //Support building part.
               
                $allowed_html = array(
                    'a' => array(
                        'href' => array(),
                        'title' => array()
                    ),
                    'br' => array(),
                    'em' => array(),
                    'strong' => array(),
                );
                $support_query = @$_POST["support_query"];
                $clean_support_query = [];
                if(!empty($support_query)){
                    foreach($support_query as $key => $val) {
                    
                        $clean_support_query[wp_kses($key,$allowed_html)] = wp_kses($val,$allowed_html);
                    }
                }
                update_option('support_query', (maybe_serialize($clean_support_query)));
                $support_ans = @$_POST["support_ans"];
                $clean_support_ans = [];
                if(!empty($support_query)){
                    foreach($support_ans as $key => $val) {
                        $clean_support_ans[wp_kses($key,$allowed_html)] = wp_kses($val,$allowed_html);
                    }
                }
                update_option('support_ans', (maybe_serialize($clean_support_ans)));
                //Create Mobile app pages.
                if(isset( $_POST["wp_chatbot_app_pages"])) {
                    $wp_chatbot_app_pages = $_POST["wp_chatbot_app_pages"];
                }else{ $wp_chatbot_app_pages='';}
                update_option('wp_chatbot_app_pages', wp_unslash($wp_chatbot_app_pages));
                //Messenger Options
                if(isset( $_POST["enable_wp_chatbot_messenger"])) {
                    $enable_wp_chatbot_messenger = $_POST["enable_wp_chatbot_messenger"];
                }else{ $enable_wp_chatbot_messenger='';}
                update_option('enable_wp_chatbot_messenger', wp_unslash($enable_wp_chatbot_messenger));
                if(isset( $_POST["enable_wp_chatbot_messenger_floating_icon"])) {
                    $enable_wp_chatbot_messenger_floating_icon = $_POST["enable_wp_chatbot_messenger_floating_icon"];
                }else{ $enable_wp_chatbot_messenger_floating_icon='';}
                update_option('enable_wp_chatbot_messenger_floating_icon', wp_unslash($enable_wp_chatbot_messenger_floating_icon));
                if(isset($_POST["qlcd_wp_chatbot_fb_app_id"])){
                    $qlcd_wp_chatbot_fb_app_id = @$_POST["qlcd_wp_chatbot_fb_app_id"];
                    update_option('qlcd_wp_chatbot_fb_app_id', sanitize_text_field($qlcd_wp_chatbot_fb_app_id));
                }
                if(isset($_POST["qlcd_wp_chatbot_fb_page_id"])){
                    $qlcd_wp_chatbot_fb_page_id = @$_POST["qlcd_wp_chatbot_fb_page_id"];
                    update_option('qlcd_wp_chatbot_fb_page_id', sanitize_text_field($qlcd_wp_chatbot_fb_page_id));
                }
                if(isset($_POST["qlcd_wp_chatbot_fb_color"])){
                    $qlcd_wp_chatbot_fb_color= @$_POST["qlcd_wp_chatbot_fb_color"];
                    update_option('qlcd_wp_chatbot_fb_color', wp_unslash($qlcd_wp_chatbot_fb_color));
                }
                if(isset($_POST["qlcd_wp_chatbot_fb_in_msg"])){
                    $qlcd_wp_chatbot_fb_in_msg = @$_POST["qlcd_wp_chatbot_fb_in_msg"];
                    update_option('qlcd_wp_chatbot_fb_in_msg', stripslashes(sanitize_text_field($qlcd_wp_chatbot_fb_in_msg)));
                }
                if(isset($_POST["qlcd_wp_chatbot_fb_out_msg"])){
                    $qlcd_wp_chatbot_fb_out_msg = @$_POST["qlcd_wp_chatbot_fb_out_msg"];
                    update_option('qlcd_wp_chatbot_fb_out_msg', stripslashes(sanitize_text_field($qlcd_wp_chatbot_fb_out_msg)));
                }
                //Skype option
                if(isset( $_POST["enable_wp_chatbot_skype_floating_icon"])) {
                $enable_wp_chatbot_skype_floating_icon = $_POST["enable_wp_chatbot_skype_floating_icon"];
                }else{ $enable_wp_chatbot_skype_floating_icon='';}
                update_option('enable_wp_chatbot_skype_floating_icon', sanitize_text_field($enable_wp_chatbot_skype_floating_icon));
                if(isset( $_POST["enable_wp_chatbot_skype_id"])) {
                    $enable_wp_chatbot_skype_id = $_POST["enable_wp_chatbot_skype_id"];
                }else{ $enable_wp_chatbot_skype_id='';}
                update_option('enable_wp_chatbot_skype_id', sanitize_text_field($enable_wp_chatbot_skype_id));
                //WhatsApp
                if(isset( $_POST["enable_wp_chatbot_whats"])) {
                    $enable_wp_chatbot_whats= $_POST["enable_wp_chatbot_whats"];
                }else{ $enable_wp_chatbot_whats='';}
                update_option('enable_wp_chatbot_whats', sanitize_text_field($enable_wp_chatbot_whats));
                if(isset($_POST["qlcd_wp_chatbot_whats_label"])){
                    $qlcd_wp_chatbot_whats_label = @$_POST["qlcd_wp_chatbot_whats_label"];
                    update_option('qlcd_wp_chatbot_whats_label', maybe_serialize(sanitize_array($qlcd_wp_chatbot_whats_label)));
                }
                if(isset( $_POST["enable_wp_chatbot_floating_whats"])) {
                    $enable_wp_chatbot_floating_whats= $_POST["enable_wp_chatbot_floating_whats"];
                }else{ $enable_wp_chatbot_floating_whats='';}
                update_option('enable_wp_chatbot_floating_whats', sanitize_text_field($enable_wp_chatbot_floating_whats));
                if(isset($_POST["qlcd_wp_chatbot_whats_num"])){
                    $qlcd_wp_chatbot_whats_num = @$_POST["qlcd_wp_chatbot_whats_num"];
                    update_option('qlcd_wp_chatbot_whats_num', sanitize_text_field($qlcd_wp_chatbot_whats_num));
                }
               //Viber
                if(isset( $_POST["enable_wp_chatbot_floating_viber"])) {
                    $enable_wp_chatbot_floating_viber = $_POST["enable_wp_chatbot_floating_viber"];
                }else{ $enable_wp_chatbot_floating_viber='';}
                update_option('enable_wp_chatbot_floating_viber', sanitize_text_field($enable_wp_chatbot_floating_viber));
                if(isset($_POST["qlcd_wp_chatbot_viber_acc"])){
                    $qlcd_wp_chatbot_viber_acc = @$_POST["qlcd_wp_chatbot_viber_acc"];
                    update_option('qlcd_wp_chatbot_viber_acc', sanitize_text_field($qlcd_wp_chatbot_viber_acc));
                }
                //Others integration
                if(isset( $_POST["enable_wp_chatbot_floating_phone"])) {
                    $enable_wp_chatbot_floating_phone = $_POST["enable_wp_chatbot_floating_phone"];
                }else{ $enable_wp_chatbot_floating_phone='';}
                update_option('enable_wp_chatbot_floating_phone', sanitize_text_field($enable_wp_chatbot_floating_phone));
                if(isset($_POST["qlcd_wp_chatbot_phone"])){
                    $qlcd_wp_chatbot_phone = @$_POST["qlcd_wp_chatbot_phone"];
                    update_option('qlcd_wp_chatbot_phone', sanitize_text_field($qlcd_wp_chatbot_phone));
                }

                if(isset( $_POST["enable_wp_chatbot_floating_link"])) {
                    $enable_wp_chatbot_floating_link = $_POST["enable_wp_chatbot_floating_link"];
                }else{ $enable_wp_chatbot_floating_link='';}
                update_option('enable_wp_chatbot_floating_link', sanitize_text_field($enable_wp_chatbot_floating_link));
                if(isset($_POST["qlcd_wp_chatbot_weblink"])){
                    $qlcd_wp_chatbot_weblink = @$_POST["qlcd_wp_chatbot_weblink"];
                    update_option('qlcd_wp_chatbot_weblink', sanitize_text_field($qlcd_wp_chatbot_weblink));
                }

                //Re Targetting.
                if(isset($_POST["qlcd_wp_chatbot_ret_greet"])){
                    $qlcd_wp_chatbot_ret_greet = @$_POST["qlcd_wp_chatbot_ret_greet"];
                    update_option('qlcd_wp_chatbot_ret_greet', sanitize_text_field($qlcd_wp_chatbot_ret_greet));
                }

                if(isset( $_POST["enable_wp_chatbot_exit_intent"])) {
                    $enable_wp_chatbot_exit_intent = $_POST["enable_wp_chatbot_exit_intent"];
                }else{ $enable_wp_chatbot_exit_intent='';}
                update_option('enable_wp_chatbot_exit_intent', sanitize_text_field($enable_wp_chatbot_exit_intent));

                if(isset($_POST["wp_chatbot_exit_intent_msg"])){
                    $wp_chatbot_exit_intent_msg = @$_POST["wp_chatbot_exit_intent_msg"];
                    update_option('wp_chatbot_exit_intent_msg', wp_unslash($wp_chatbot_exit_intent_msg));
                }

                if(isset( $_POST["wp_chatbot_exit_intent_once"])) {
                    $wp_chatbot_exit_intent_once = $_POST["wp_chatbot_exit_intent_once"];
                }else{ $wp_chatbot_exit_intent_once='';}
                update_option('wp_chatbot_exit_intent_once', sanitize_text_field($wp_chatbot_exit_intent_once));

                if(isset( $_POST["enable_wp_chatbot_scroll_open"])) {
                    $enable_wp_chatbot_scroll_open = $_POST["enable_wp_chatbot_scroll_open"];
                }else{ $enable_wp_chatbot_scroll_open='';}
                update_option('enable_wp_chatbot_scroll_open', sanitize_text_field($enable_wp_chatbot_scroll_open));

                if(isset($_POST["wp_chatbot_scroll_open_msg"])){
                    $wp_chatbot_scroll_open_msg= @$_POST["wp_chatbot_scroll_open_msg"];
                    update_option('wp_chatbot_scroll_open_msg', wp_unslash($wp_chatbot_scroll_open_msg));
                }

                if(isset($_POST["wp_chatbot_scroll_percent"])){
                    $wp_chatbot_scroll_percent= @$_POST["wp_chatbot_scroll_percent"];
                    update_option('wp_chatbot_scroll_percent', wp_unslash($wp_chatbot_scroll_percent));
                }

                if(isset( $_POST["wp_chatbot_scroll_once"])) {
                    $wp_chatbot_scroll_once = sanitize_text_field($_POST["wp_chatbot_scroll_once"]);
                }else{ $wp_chatbot_scroll_once='';}
                update_option('wp_chatbot_scroll_once', $wp_chatbot_scroll_once);

                if(isset( $_POST["enable_wp_chatbot_auto_open"])) {
                    $enable_wp_chatbot_auto_open = sanitize_text_field($_POST["enable_wp_chatbot_auto_open"]);
                }else{ $enable_wp_chatbot_auto_open='';}
                update_option('enable_wp_chatbot_auto_open', $enable_wp_chatbot_auto_open);

                if(isset( $_POST["enable_wp_chatbot_ret_sound"])) {
                    $enable_wp_chatbot_ret_sound = sanitize_text_field($_POST["enable_wp_chatbot_ret_sound"]);
                }else{ $enable_wp_chatbot_ret_sound='';}
                update_option('enable_wp_chatbot_ret_sound', $enable_wp_chatbot_ret_sound);

                if(isset( $_POST["enable_wp_chatbot_sound_initial"])) {
                    $enable_wp_chatbot_sound_initial = sanitize_text_field($_POST["enable_wp_chatbot_sound_initial"]);
                }else{ $enable_wp_chatbot_sound_initial='';}
                update_option('enable_wp_chatbot_sound_initial', $enable_wp_chatbot_sound_initial);


                if(isset($_POST["wp_chatbot_auto_open_msg"])){
                    $wp_chatbot_auto_open_msg = @$_POST["wp_chatbot_auto_open_msg"];
                    update_option('wp_chatbot_auto_open_msg', wp_unslash($wp_chatbot_auto_open_msg));
                }

                if(isset($_POST["wp_chatbot_auto_open_time"])){
                    $wp_chatbot_auto_open_time = @$_POST["wp_chatbot_auto_open_time"];
                    update_option('wp_chatbot_auto_open_time', wp_unslash($wp_chatbot_auto_open_time));
                }
                //to complate checkout.
                if(isset( $_POST["enable_wp_chatbot_ret_user_show"])) {
                    $enable_wp_chatbot_ret_user_show = sanitize_text_field($_POST["enable_wp_chatbot_ret_user_show"]);
                }else{ $enable_wp_chatbot_ret_user_show='';}
                update_option('enable_wp_chatbot_ret_user_show', $enable_wp_chatbot_ret_user_show);

                if(isset( $_POST["enable_wp_chatbot_inactive_time_show"])) {
                    $enable_wp_chatbot_inactive_time_show = sanitize_text_field($_POST["enable_wp_chatbot_inactive_time_show"]);
                }else{ $enable_wp_chatbot_inactive_time_show='';}
                update_option('enable_wp_chatbot_inactive_time_show', $enable_wp_chatbot_inactive_time_show);

                if(isset($_POST["wp_chatbot_inactive_time"])){
                    $wp_chatbot_inactive_time = @$_POST["wp_chatbot_inactive_time"];
                    update_option('wp_chatbot_inactive_time', sanitize_text_field($wp_chatbot_inactive_time));
                }

                if(isset($_POST["wp_chatbot_checkout_msg"])){
                    $wp_chatbot_checkout_msg = @$_POST["wp_chatbot_checkout_msg"];
                    update_option('wp_chatbot_checkout_msg', wp_unslash($wp_chatbot_checkout_msg));
                }

                if(isset( $_POST["wp_chatbot_auto_open_once"])) {
                    $wp_chatbot_auto_open_once = sanitize_text_field($_POST["wp_chatbot_auto_open_once"]);
                }else{ $wp_chatbot_auto_open_once='';}
                update_option('wp_chatbot_auto_open_once', $wp_chatbot_auto_open_once);

                if(isset( $_POST["wp_chatbot_inactive_once"])) {
                    $wp_chatbot_inactive_once = sanitize_text_field($_POST["wp_chatbot_inactive_once"]);
                }else{ $wp_chatbot_inactive_once='';}
                update_option('wp_chatbot_inactive_once', $wp_chatbot_inactive_once);


                if(isset($_POST["wp_chatbot_proactive_bg_color"])){
                    $wp_chatbot_proactive_bg_color = @$_POST["wp_chatbot_proactive_bg_color"];
                    update_option('wp_chatbot_proactive_bg_color', sanitize_text_field($wp_chatbot_proactive_bg_color));
                }

                if(isset( $_POST["disable_wp_chatbot_call_gen"])) {
                    $disable_wp_chatbot_call_gen = sanitize_text_field($_POST["disable_wp_chatbot_call_gen"]);
                }else{ $disable_wp_chatbot_call_gen='';}
                update_option('disable_wp_chatbot_call_gen', $disable_wp_chatbot_call_gen);
				
				if(isset( $_POST["disable_wp_chatbot_site_search"])) {
                    $disable_wp_chatbot_site_search = sanitize_text_field($_POST["disable_wp_chatbot_site_search"]);
                    update_option('enable_wp_chatbot_post_content', '');
                }else{ $disable_wp_chatbot_site_search='';}
                update_option('disable_wp_chatbot_site_search', $disable_wp_chatbot_site_search);

                if(isset( $_POST["enable_wp_chatbot_post_content"])) {
                    $enable_wp_chatbot_post_content = sanitize_text_field($_POST["enable_wp_chatbot_post_content"]);
                    
                }else{ $enable_wp_chatbot_post_content='';}
                update_option('enable_wp_chatbot_post_content', $enable_wp_chatbot_post_content);

                if(isset( $_POST["disable_wp_chatbot_call_sup"])) {
                    $disable_wp_chatbot_call_sup = sanitize_text_field($_POST["disable_wp_chatbot_call_sup"]);
                }else{ $disable_wp_chatbot_call_sup='';}
                update_option('disable_wp_chatbot_call_sup', $disable_wp_chatbot_call_sup);

                if(isset( $_POST["disable_wp_chatbot_feedback"])) {
                    $disable_wp_chatbot_feedback = sanitize_text_field($_POST["disable_wp_chatbot_feedback"]);
                }else{ $disable_wp_chatbot_feedback='';}
                update_option('disable_wp_chatbot_feedback', $disable_wp_chatbot_feedback);
				
				if(isset( $_POST["disable_wp_chatbot_faq"])) {
                    $disable_wp_chatbot_faq = sanitize_text_field($_POST["disable_wp_chatbot_faq"]);
                }else{ $disable_wp_chatbot_faq='';}
                update_option('disable_wp_chatbot_faq', $disable_wp_chatbot_faq);

                if(isset($_POST["qlcd_wp_chatbot_feedback_label"])){
                    $qlcd_wp_chatbot_feedback_label = (@$_POST["qlcd_wp_chatbot_feedback_label"]);
                    update_option('qlcd_wp_chatbot_feedback_label', maybe_serialize(sanitize_array($qlcd_wp_chatbot_feedback_label)));
                }

                if(isset( $_POST["enable_wp_chatbot_meta_title"])) {
                    $enable_wp_chatbot_meta_title = sanitize_text_field($_POST["enable_wp_chatbot_meta_title"]);
                }else{ $enable_wp_chatbot_meta_title='';}
                update_option('enable_wp_chatbot_meta_title', $enable_wp_chatbot_meta_title);

                if(isset($_POST["qlcd_wp_chatbot_meta_label"])){
                    $qlcd_wp_chatbot_meta_label = esc_html(@$_POST["qlcd_wp_chatbot_meta_label"]);
                    update_option('qlcd_wp_chatbot_meta_label', stripslashes(sanitize_text_field($qlcd_wp_chatbot_meta_label)));
                }

                if (isset($_POST["qlcd_wp_chatbot_phone_sent"])) {
                    $qlcd_wp_chatbot_phone_sent = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_phone_sent"]));
                    update_option('qlcd_wp_chatbot_phone_sent', $qlcd_wp_chatbot_phone_sent);
                }

                if (isset($_POST["qlcd_wp_chatbot_phone_fail"])) {
                    $qlcd_wp_chatbot_phone_fail = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_phone_fail"]));
                    update_option('qlcd_wp_chatbot_phone_fail', $qlcd_wp_chatbot_phone_fail);
                }

                if (isset($_POST["qlcd_wp_chatbot_asking_search_keyword"])) {
                    $qlcd_wp_chatbot_asking_search_keyword = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_asking_search_keyword"]));
                    update_option('qlcd_wp_chatbot_asking_search_keyword', $qlcd_wp_chatbot_asking_search_keyword);
                }

                if (isset($_POST["qlcd_wp_chatbot_found_result"])) {
                    $qlcd_wp_chatbot_found_result = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_found_result"]));
                    update_option('qlcd_wp_chatbot_found_result', $qlcd_wp_chatbot_found_result);
                }
                
                if(isset( $_POST["enable_wp_chatbot_opening_hour"])) {
                    $enable_wp_chatbot_opening_hour = stripslashes(sanitize_text_field($_POST["enable_wp_chatbot_opening_hour"]));
                }else{ $enable_wp_chatbot_opening_hour='';}
                update_option('enable_wp_chatbot_opening_hour', esc_html($enable_wp_chatbot_opening_hour));

                if(isset($_POST["wpwbot_hours"])){
                    $wpwbot_hours= @$_POST["wpwbot_hours"];
                    update_option('wpwbot_hours', maybe_serialize(sanitize_array($wpwbot_hours)));
                }

                if(isset( $_POST["enable_wp_chatbot_dailogflow"])) {
                    $enable_wp_chatbot_dailogflow = stripslashes(sanitize_text_field($_POST["enable_wp_chatbot_dailogflow"]));
                }else{ $enable_wp_chatbot_dailogflow='';}
                update_option('enable_wp_chatbot_dailogflow', $enable_wp_chatbot_dailogflow);

                if(isset($_POST["qlcd_wp_chatbot_dialogflow_client_token"])){
                    $qlcd_wp_chatbot_dialogflow_client_token= esc_html(@$_POST["qlcd_wp_chatbot_dialogflow_client_token"]);
                    update_option('qlcd_wp_chatbot_dialogflow_client_token', stripslashes(sanitize_text_field($qlcd_wp_chatbot_dialogflow_client_token)));
                }

                if (isset($_POST["qlcd_wp_chatbot_dialogflow_defualt_reply"])) {
                    $qlcd_wp_chatbot_dialogflow_defualt_reply = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_dialogflow_defualt_reply"]));
                    update_option('qlcd_wp_chatbot_dialogflow_defualt_reply', $qlcd_wp_chatbot_dialogflow_defualt_reply);
                }
				
				if (isset($_POST["qlcd_wp_chatbot_dialogflow_agent_language"])) {
                    $qlcd_wp_chatbot_dialogflow_agent_language = sanitize_text_field(wp_unslash($_POST["qlcd_wp_chatbot_dialogflow_agent_language"]));
                    update_option('qlcd_wp_chatbot_dialogflow_agent_language', $qlcd_wp_chatbot_dialogflow_agent_language);
                }
                // style option save.
                if(isset( $_POST["enable_wp_chatbot_custom_color"])) {
                    $enable_wp_chatbot_custom_color = esc_html($_POST["enable_wp_chatbot_custom_color"]);
                }else{ $enable_wp_chatbot_custom_color='';}
                update_option('enable_wp_chatbot_custom_color', stripslashes(sanitize_text_field($enable_wp_chatbot_custom_color)));
                if (isset($_POST["wp_chatbot_text_color"])) {
                    $wp_chatbot_text_color = sanitize_hex_color($_POST["wp_chatbot_text_color"]);
                    update_option('wp_chatbot_text_color', $wp_chatbot_text_color);
                }
                
                if (isset($_POST["wp_chatbot_floatingiconbg_color"])) {
                    $wp_chatbot_floatingiconbg_color = sanitize_hex_color($_POST["wp_chatbot_floatingiconbg_color"]);
                    update_option('wp_chatbot_floatingiconbg_color', $wp_chatbot_floatingiconbg_color);
                }

                if (isset($_POST["wp_chatbot_link_color"])) {
                    $wp_chatbot_link_color = sanitize_hex_color($_POST["wp_chatbot_link_color"]);
                    update_option('wp_chatbot_link_color', $wp_chatbot_link_color);
                }

                if (isset($_POST["wp_chatbot_link_hover_color"])) {
                    $wp_chatbot_link_hover_color = sanitize_hex_color($_POST["wp_chatbot_link_hover_color"]);
                    update_option('wp_chatbot_link_hover_color', $wp_chatbot_link_hover_color);
                }

                if (isset($_POST["wp_chatbot_bot_msg_bg_color"])) {
                    $wp_chatbot_bot_msg_bg_color = sanitize_hex_color($_POST["wp_chatbot_bot_msg_bg_color"]);
                    update_option('wp_chatbot_bot_msg_bg_color', $wp_chatbot_bot_msg_bg_color);
                }

                if (isset($_POST["wp_chatbot_bot_msg_text_color"])) {
                    $wp_chatbot_bot_msg_text_color = sanitize_hex_color($_POST["wp_chatbot_bot_msg_text_color"]);
                    update_option('wp_chatbot_bot_msg_text_color', $wp_chatbot_bot_msg_text_color);
                }

                if (isset($_POST["wp_chatbot_user_msg_bg_color"])) {
                    $wp_chatbot_user_msg_bg_color = sanitize_hex_color($_POST["wp_chatbot_user_msg_bg_color"]);
                    update_option('wp_chatbot_user_msg_bg_color', $wp_chatbot_user_msg_bg_color);
                }

                if (isset($_POST["wp_chatbot_user_msg_text_color"])) {
                    $wp_chatbot_user_msg_text_color = sanitize_hex_color($_POST["wp_chatbot_user_msg_text_color"]);
                    update_option('wp_chatbot_user_msg_text_color', $wp_chatbot_user_msg_text_color);
                }

				if (isset($_POST["wp_chatbot_buttons_bg_color"])) {
                    $wp_chatbot_buttons_bg_color = sanitize_hex_color($_POST["wp_chatbot_buttons_bg_color"]);
                    update_option('wp_chatbot_buttons_bg_color', $wp_chatbot_buttons_bg_color);
                }

                if (isset($_POST["wp_chatbot_buttons_text_color"])) {
                    $wp_chatbot_buttons_text_color = sanitize_hex_color($_POST["wp_chatbot_buttons_text_color"]);
                    update_option('wp_chatbot_buttons_text_color', $wp_chatbot_buttons_text_color);
                }

                if (isset($_POST["wp_chatbot_buttons_bg_color_hover"])) {
                    $wp_chatbot_buttons_bg_color_hover = sanitize_hex_color($_POST["wp_chatbot_buttons_bg_color_hover"]);
                    update_option('wp_chatbot_buttons_bg_color_hover', $wp_chatbot_buttons_bg_color_hover);
                }

                if (isset($_POST["wp_chatbot_buttons_text_color_hover"])) {
                    $wp_chatbot_buttons_text_color_hover = sanitize_hex_color($_POST["wp_chatbot_buttons_text_color_hover"]);
                    update_option('wp_chatbot_buttons_text_color_hover', $wp_chatbot_buttons_text_color_hover);
                }

                if (isset($_POST["wp_chatbot_theme_secondary_color"])) {
                    $wp_chatbot_theme_secondary_color = sanitize_hex_color($_POST["wp_chatbot_theme_secondary_color"]);
                    update_option('wp_chatbot_theme_secondary_color', $wp_chatbot_theme_secondary_color);
                }
                if (isset($_POST["wp_chatbot_header_background_color"])) {
                    $wp_chatbot_header_background_color = sanitize_hex_color($_POST["wp_chatbot_header_background_color"]);
                    update_option('wp_chatbot_header_background_color', $wp_chatbot_header_background_color);
                }

                if (isset($_POST["wp_chatbot_font_size"])) {
                    $wp_chatbot_font_size = sanitize_text_field($_POST["wp_chatbot_font_size"]);
                    // Additional validation for font size format (e.g., '14px', '1.2em') could be added here if strict adherence to CSS units is required.
                    update_option('wp_chatbot_font_size', $wp_chatbot_font_size);
                }
                if (isset($_POST["wp_chat_bot_font_family"])) {
                    $wp_chat_bot_font_family = sanitize_text_field($_POST["wp_chat_bot_font_family"]);
                    update_option('wp_chat_bot_font_family', $wp_chat_bot_font_family);
                }
                if (isset($_POST["wp_chat_user_font_family"])) {
                    $wp_chat_user_font_family = sanitize_text_field($_POST["wp_chat_user_font_family"]);
                    update_option('wp_chat_user_font_family', $wp_chat_user_font_family);
                }
                if (isset($_POST['wp_chatbot_user_font'])) {
                    $wp_chatbot_user_font = sanitize_text_field($_POST['wp_chatbot_user_font']);
                    update_option('wp_chatbot_user_font', $wp_chatbot_user_font);
                }
                if (isset($_POST['wp_chatbot_bot_font'])) {
                    $wp_chatbot_bot_font = sanitize_text_field($_POST['wp_chatbot_bot_font']);
                    update_option('wp_chatbot_bot_font', $wp_chatbot_bot_font);
                }

                set_transient( 'qcld_bot_clear_cache', 1, DAY_IN_SECONDS );

                wp_enqueue_script( 'wp_chatbot_bot-front-js', plugins_url(basename(plugin_dir_path(__FILE__))) . '/js/sweetalrt.js', array('jquery'), '', true);
                $script = "
                    console.log('sdaas');
                    function callsweetalert(){
                        Swal.fire({
                            title: 'Your settings are saved.',
                            html: '<p style=font-size:14px>Please clear your browser <b>cache</b> and <b>cookies</b> both and reload the front end before testing. Alternatively, you can launch a new browser window in <b>Incognito</b>/Private mode (Ctrl+Shift+N in chrome) to test.</p>',
                            width: 450,
                            icon: 'success',
                         confirmButtonText: 'Got it',
                        confirmButtonWidth: 100,
                        confirmButtonClass: 'btn btn-lg',    
                        }).then((result) => {
                           // location.reload();
                        })
                    }
                  callsweetalert();
                ";

                wp_add_inline_script( 'wp_chatbot_bot-front-js', $script );
            }
        }
    }
    /**
     * Display Notifications on specific criteria.
     *
     * @since    2.14
     */
    public static function wpcommerce_inactive_notice_for_wp_chatbot()
    {
        if (current_user_can('activate_plugins')) :
            if (!class_exists('wpCommerce')) :
                deactivate_plugins(plugin_basename(__FILE__));
                ?>
<div id="message" class="error">
  <p>
    <?php
                        printf(
                            '%s WPBot for wpCommerce REQUIRES wpCommerce%s %swpCommerce%s must be active for WPBot to work. Please install & activate wpCommerce.',
                            '<strong>',
                            '</strong><br>',
                            '<a href="http://wordpress.org/extend/plugins/wpcommerce/" target="_blank" >',
                            '</a>'
                        );
                        ?>
  </p>
</div>
<?php
            elseif (version_compare(get_option('wpcommerce_db_version'), QCLD_wpCHATBOT_REQUIRED_wpCOMMERCE_VERSION, '<')) :
                ?>
<div id="message" class="error"> 
  <!--<p style="float: right; color: #9A9A9A; font-size: 13px; font-style: italic;">For more information <a href="http://cxthemes.com/plugins/update-notice.html" target="_blank" style="color: inheret;">click here</a></p>-->
  <p>
    <?php
                        printf(
                            '%WPBot for wpCommerce is inactive%s This version of WpBot requires wpCommerce %s or newer. For more information about our wpCommerce version support %sclick here%s.',
                            '<strong>',
                            '</strong><br>',
                            esc_html( QCLD_wpCHATBOT_REQUIRED_wpCOMMERCE_VERSION )
                        );
                        ?>
  </p>
  <div style="clear:both;"></div>
</div>
<?php
            endif;
        endif;
    }
    /**
     * Admin notice for table reindex
     */
    public function admin_notice_reindex() { ?>
<div class="updated notice is-dismissible">
  <p><?php
    // translators: %s: A link to the Re-Index Products page.
    printf( esc_html__( 'WPBot Pro : To Enable Title, Content, Excerpt, Categories, Tag and SKU Search Re-Index Products is required. %s', 'chatbot' ),'<a class="button button-secondary" href="'.esc_url( admin_url( 'admin.php?page=wpbot') ).'">'.esc_html__( 'Re-Index Products', 'chatbot' ).'</a>'); ?></p>
</div>
<?php }
}
/**
 * Instantiate plugin.
 *
 */
if (!function_exists('qcld_wb_chatboot_plugin_init')) {
    function qcld_wb_chatboot_plugin_init()
    {
        global $qcld_wb_chatbot;
        $qcld_wb_chatbot = qcld_wb_Chatbot_free::qcld_wb_chatbot_get_instance();
    }
}
add_action('plugins_loaded', 'qcld_wb_chatboot_plugin_init');
/*
 * Initial Options will be insert as defualt data
 */
register_activation_hook(__FILE__, 'qcld_wb_chatboot_defualt_options');
if( !function_exists('qcld_wb_chatboot_defualt_options') ){
function qcld_wb_chatboot_defualt_options(){
	
	global $wpdb;
	$collate = '';
	
	if ( $wpdb->has_cap( 'collation' ) ) {

		if ( ! empty( $wpdb->charset ) ) {

			$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {

			$collate .= " COLLATE $wpdb->collate";

		}
	}
	
    //Bot User Table
    $table1    = $wpdb->prefix.'wpbot_sessions';
	$sql_sliders_Table1 = "
		CREATE TABLE IF NOT EXISTS `$table1` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `session` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sliders_Table1 );
	
	//Bot Response Table
    $table1    = $wpdb->prefix.'wpbot_response';
    $sql_sliders_Table1 = "
        CREATE TABLE IF NOT EXISTS `$table1` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `query` TEXT NOT NULL,
        `keyword` TEXT NOT NULL,
        `response` TEXT NOT NULL,
        `category` varchar(256) NOT NULL,
        `intent` varchar(256) NOT NULL,
        `custom` varchar(256) NOT NULL,
        `lang`	   varchar(25) NULL,
        FULLTEXT(`query`, `keyword`, `response`)
        )  $collate AUTO_INCREMENT=1 ENGINE=InnoDB";
        
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sliders_Table1 );

    //Bot Response Table
			$table_rag_documents = $wpdb->prefix . "rag_documents";

			$charset = $wpdb->get_charset_collate();
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_rag_documents'") != $table_rag_documents) {
				$sql_rag_documents = "CREATE TABLE $table_rag_documents (
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				doc_id VARCHAR(100) DEFAULT NULL,
				title VARCHAR(255) NOT NULL,
				content LONGTEXT NOT NULL,
				embedding LONGTEXT NOT NULL,
				source_type VARCHAR(20) DEFAULT 'post', 
				source_url VARCHAR(255) DEFAULT NULL,
				file_url TEXT DEFAULT NULL,
				metadata LONGTEXT DEFAULT NULL,
				status VARCHAR(50) DEFAULT 'complete',
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			) $charset;";
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta($sql_rag_documents);
			}
                        $table_report = $wpdb->prefix . 'wpbot_chat_report';

			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_report'" ) != $table_report ) {
			$sql_report = "
				CREATE TABLE `$table_report` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`conversation_id` int(11) DEFAULT NULL,
					`message` longtext NOT NULL,
					`feedback` varchar(20) DEFAULT NULL,
					`meta_info` text DEFAULT NULL,
					`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`)
				) $charset_collate AUTO_INCREMENT=1;
			";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql_report );
			}
	
	$sqlqry = $wpdb->get_results($wpdb->prepare("select * from $table1 where id = %d", 1)); //DB Call OK, No Caching OK
	if(empty($sqlqry)){
	
		$query = 'What Can WPBot do for you?';
		$response = 'WPBot can converse fluidly with users on website and FB messenger. It can search your website, send/collect eMails, user feedback & phone numbers . You can create Custom Intents from DialogFlow with Rich Messages & Card responses!';

		$data = array('query' => $query, 'keyword' => '', 'response'=> $response, 'intent'=> '');
		$format = array('%s','%s', '%s', '%s');
		$wpdb->insert($table1,$data,$format); //DB Call OK, No Caching OK
	}
	
    $url = get_site_url();
    $url = wp_parse_url($url);
    $domain = $url['host'];
    //$admin_email = "admin@" . $domain;
    $admin_email = get_option('admin_email');

    if(!get_option('wp_chatbot_position_x')) {
        update_option('wp_chatbot_position_x', 50);
    }
    if(!get_option('wp_chatbot_position_y')) {
        update_option('wp_chatbot_position_y', 50);
    }
    if(!get_option('disable_wp_chatbot')) {
        update_option('disable_wp_chatbot', '');
    }
    if(!get_option('disable_wp_chatbot_icon_animation')) {
        update_option('disable_wp_chatbot_icon_animation', '');
    }
    if(!get_option('disable_wp_chatbot_on_mobile')) {
        update_option('disable_wp_chatbot_on_mobile', '');
    }
	if(!get_option('qlcd_wp_chatbot_admin_email')) {
        update_option('qlcd_wp_chatbot_admin_email', get_option('admin_email'));
    }
    if(!get_option('disable_wp_chatbot_product_search')) {
        update_option('disable_wp_chatbot_product_search', '');
    }
    if(!get_option('disable_wp_chatbot_catalog')) {
        update_option('disable_wp_chatbot_catalog', '');
    }
    if(!get_option('disable_wp_chatbot_order_status')) {
        update_option('disable_wp_chatbot_order_status', '');
    }
    if(!get_option('enable_wp_chatbot_rtl')) {
        update_option('enable_wp_chatbot_rtl', '');
    }
	if(!get_option('show_menu_after_greetings')) {
        update_option('show_menu_after_greetings', 1);
    }
    if(!get_option('enable_chat_session')) {
        update_option('enable_chat_session', '');
    }
    
    if(!get_option('enable_wp_chatbot_mobile_full_screen')) {
        update_option('enable_wp_chatbot_mobile_full_screen', 1);
    }
    if(!get_option('wpbot_preloading_time')) {
        update_option('wpbot_preloading_time', '800');
    }

     if(!get_option('disable_wp_chatbot_notification')) {
        update_option('disable_wp_chatbot_notification', '1');
    }
    if(!get_option('disable_wp_chatbot_cart_item_number')) {
        update_option('disable_wp_chatbot_cart_item_number', '');
    }
    if(!get_option('disable_wp_chatbot_featured_product')) {
        update_option('disable_wp_chatbot_featured_product', '');
    }
    if(!get_option('disable_wp_chatbot_sale_product')) {
        update_option('disable_wp_chatbot_sale_product', '');
    }
     if(!get_option('wp_chatbot_open_product_detail')) {
        update_option('wp_chatbot_open_product_detail', '');
    }
    if(!get_option('qlcd_wp_chatbot_product_orderby')) {
        update_option('qlcd_wp_chatbot_product_orderby', sanitize_text_field('title'));
    }
    if(!get_option('qlcd_wp_chatbot_product_order')) {
        update_option('qlcd_wp_chatbot_product_order', sanitize_text_field('ASC'));
    }
    if(!get_option('qlcd_wp_chatbot_ppp')) {
        update_option('qlcd_wp_chatbot_ppp', intval(6));
    }
    if(!get_option('wp_chatbot_exclude_stock_out_product')) {
        update_option('wp_chatbot_exclude_stock_out_product', '');
    }
    if(!get_option('wp_chatbot_show_sub_category')) {
        update_option('wp_chatbot_show_sub_category', '');
    }
    if(!get_option('wp_chatbot_vertical_custom')){
        update_option('wp_chatbot_vertical_custom', 'Go To');
    }
    if(!get_option('wp_chatbot_show_home_page')) {
        update_option('wp_chatbot_show_home_page', 'on');
    }
	if(!get_option('qc_wpbot_menu_order')) {
        update_option('qc_wpbot_menu_order', '');
    }
	
    if(!get_option('wp_chatbot_show_posts')) {
        update_option('wp_chatbot_show_posts', 'on');
    }
    if(!get_option('wp_chatbot_show_pages')){
        update_option('wp_chatbot_show_pages', 'on');
    }
    if(!get_option('wp_chatbot_show_pages_list')) {
        update_option('wp_chatbot_show_pages_list', maybe_serialize(array()));
    }
    if(!get_option('wp_chatbot_exclude_post_list')) {
        update_option('wp_chatbot_exclude_post_list', maybe_serialize(array()));
    }
    
    if(!get_option('wp_chatbot_show_wpcommerce')) {
        update_option('wp_chatbot_show_wpcommerce', 'on');
    }
    if(!get_option('qlcd_wp_chatbot_stop_words_name')) {
        update_option('qlcd_wp_chatbot_stop_words_name', 'english');
    }
    if(!get_option('qlcd_wp_chatbot_stop_words')) {
        update_option('qlcd_wp_chatbot_stop_words', "a,able,about,above,abst,accordance,according,accordingly,across,act,actually,added,adj,affected,affecting,affects,after,afterwards,again,against,ah,all,almost,alone,along,already,also,although,always,am,among,amongst,an,and,announce,another,any,anybody,anyhow,anymore,anyone,anything,anyway,anyways,anywhere,apparently,approximately,are,aren,arent,arise,around,as,aside,ask,asking,at,auth,available,away,awfully,b,back,be,became,because,become,becomes,becoming,been,before,beforehand,begin,beginning,beginnings,begins,behind,being,believe,below,beside,besides,between,beyond,biol,both,brief,briefly,but,by,c,ca,came,can,cannot,can't,cause,causes,certain,certainly,co,com,come,comes,contain,containing,contains,could,couldnt,d,date,did,didn't,different,do,does,doesn't,doing,done,don't,down,downwards,due,during,e,each,ed,edu,effect,eg,eight,eighty,either,else,elsewhere,end,ending,enough,especially,et,et-al,etc,even,ever,every,everybody,everyone,everything,everywhere,ex,except,f,far,few,ff,fifth,first,five,fix,followed,following,follows,for,former,formerly,forth,found,four,from,further,furthermore,g,gave,get,gets,getting,give,given,gives,giving,go,goes,gone,got,gotten,h,had,happens,hardly,has,hasn't,have,haven't,having,he,hed,hence,her,here,hereafter,hereby,herein,heres,hereupon,hers,herself,hes,hi,hid,him,himself,his,hither,home,how,howbeit,however,hundred,i,id,ie,if,i'll,im,immediate,immediately,importance,important,in,inc,indeed,index,information,instead,into,invention,inward,is,isn't,it,itd,it'll,its,itself,i've,j,just,k,keep,keeps,kept,kg,km,know,known,knows,l,largely,last,lately,later,latter,latterly,least,less,lest,let,lets,like,liked,likely,line,little,'ll,look,looking,looks,ltd,m,made,mainly,make,makes,many,may,maybe,me,mean,means,meantime,meanwhile,merely,mg,might,million,miss,ml,more,moreover,most,mostly,mr,mrs,much,mug,must,my,myself,n,na,name,namely,nay,nd,near,nearly,necessarily,necessary,need,needs,neither,never,nevertheless,new,next,nine,ninety,no,nobody,non,none,nonetheless,noone,nor,normally,nos,not,noted,nothing,now,nowhere,o,obtain,obtained,obviously,of,off,often,oh,ok,okay,old,omitted,on,once,one,ones,only,onto,or,ord,other,others,otherwise,ought,our,ours,ourselves,out,outside,over,overall,owing,own,p,page,pages,part,particular,particularly,past,per,perhaps,placed,please,plus,poorly,possible,possibly,potentially,pp,predominantly,present,previously,primarily,probably,promptly,proud,provides,put,q,que,quickly,quite,qv,r,ran,rather,rd,re,readily,really,recent,recently,ref,refs,regarding,regardless,regards,related,relatively,research,respectively,resulted,resulting,results,right,run,s,said,same,saw,say,saying,says,sec,section,see,seeing,seem,seemed,seeming,seems,seen,self,selves,sent,seven,several,shall,she,shed,she'll,shes,should,shouldn't,show,showed,shown,showns,shows,significant,significantly,similar,similarly,since,six,slightly,so,some,somebody,somehow,someone,somethan,something,sometime,sometimes,somewhat,somewhere,soon,sorry,specifically,specified,specify,specifying,still,stop,strongly,sub,substantially,successfully,such,sufficiently,suggest,sup,sure,t,take,taken,taking,tell,tends,th,than,thank,thanks,thanx,that,that'll,thats,that've,the,their,theirs,them,themselves,then,thence,there,thereafter,thereby,thered,therefore,therein,there'll,thereof,therere,theres,thereto,thereupon,there've,these,they,theyd,they'll,theyre,they've,think,this,those,thou,though,thoughh,thousand,throug,through,throughout,thru,thus,til,tip,to,together,too,took,toward,towards,tried,tries,truly,try,trying,ts,twice,two,u,un,under,unfortunately,unless,unlike,unlikely,until,unto,up,upon,ups,us,use,used,useful,usefully,usefulness,uses,using,usually,v,value,various,'ve,very,via,viz,vol,vols,vs,w,want,wants,was,wasnt,way,we,wed,welcome,we'll,went,were,werent,we've,what,whatever,what'll,whats,when,whence,whenever,where,whereafter,whereas,whereby,wherein,wheres,whereupon,wherever,whether,which,while,whim,whither,who,whod,whoever,whole,who'll,whom,whomever,whos,whose,why,widely,willing,wish,with,within,without,wont,words,world,would,wouldnt,www,x,y,yes,yet,you,youd,you'll,your,youre,yours,yourself,yourselves,you've,z,zero");
    }
    if(!get_option('qlcd_wp_chatbot_order_user')) {
        update_option('qlcd_wp_chatbot_order_user', sanitize_text_field('login'));
    }
    if(!get_option('wp_chatbot_custom_agent_path')) {
        update_option('wp_chatbot_custom_agent_path', '');
    }
    if(!get_option('wp_chatbot_custom_icon_path')) {
        update_option('wp_chatbot_custom_icon_path', '');
    }

    if(!get_option('wp_chatbot_icon')) {
        update_option('wp_chatbot_icon', sanitize_text_field('icon-13.png'));
    }
	if(!get_option('wp_chatbot_floatingiconbg_color')) {
        update_option('wp_chatbot_floatingiconbg_color', '#fff');
    }
    if(!get_option('wp_chatbot_agent_image')){
        update_option('wp_chatbot_agent_image',sanitize_text_field('agent-0.png'));
    }
    if(!get_option('qcld_wb_chatbot_theme')) {
        update_option('qcld_wb_chatbot_theme', sanitize_text_field('template-00'));
    }
    if(!get_option('qcld_wb_chatbot_change_bg')) {
        update_option('qcld_wb_chatbot_change_bg', '');
    }
    if(!get_option('wp_chatbot_custom_css')) {
        update_option('wp_chatbot_custom_css', '');
    }
    if(!get_option('qlcd_wp_chatbot_host')) {
        update_option('qlcd_wp_chatbot_host', stripslashes(sanitize_text_field('Our Website')));
    }
    if(!get_option('qlcd_wp_chatbot_agent')) {
        update_option('qlcd_wp_chatbot_agent', stripslashes(sanitize_text_field('Carrie')));
    }
    if(!get_option('qlcd_wp_chatbot_host')) {
        update_option('qlcd_wp_chatbot_host', stripslashes(sanitize_text_field('Our Website')));
    }
    if(!get_option('qlcd_wp_chatbot_shopper_demo_name')) {
        update_option('qlcd_wp_chatbot_shopper_demo_name', stripslashes(sanitize_text_field('Amigo')));
    }
    if(!get_option('qlcd_wp_chatbot_yes')) {
        update_option('qlcd_wp_chatbot_yes', stripslashes(sanitize_text_field('YES')));
    }
    if(!get_option('qlcd_wp_chatbot_no')) {
        update_option('qlcd_wp_chatbot_no', stripslashes(sanitize_text_field('NO')));
    }
    if(!get_option('qlcd_wp_chatbot_or')) {
        update_option('qlcd_wp_chatbot_or', stripslashes(sanitize_text_field('OR')));
    }
    if(!get_option('qlcd_wp_chatbot_sorry')) {
        update_option('qlcd_wp_chatbot_sorry', stripslashes(sanitize_text_field('Sorry')));
    }
	
	 if(!get_option('qlcd_wp_chatbot_dialogflow_project_id')) {
        update_option('qlcd_wp_chatbot_dialogflow_project_id', '');
    }
    if(!get_option('wp_chatbot_df_api')) {
        update_option('wp_chatbot_df_api', 'v1');
    }

    
    if(!get_option('qlcd_wp_chatbot_dialogflow_project_key')) {
        update_option('qlcd_wp_chatbot_dialogflow_project_key', '');
    }
	
    if(!get_option('qlcd_wp_chatbot_agent_join')) {
        update_option('qlcd_wp_chatbot_agent_join', maybe_serialize(array('has joined the conversation')));
    }
    if(!get_option('qlcd_wp_chatbot_welcome')) {
        update_option('qlcd_wp_chatbot_welcome', maybe_serialize(array('Welcome to', 'Glad to have you at')));
    }
    if(!get_option('qlcd_wp_chatbot_back_to_start')) {
        update_option('qlcd_wp_chatbot_back_to_start', maybe_serialize(array('Back to Start')));
    }
    if(!get_option('qlcd_wp_chatbot_hi_there')) {
        update_option('qlcd_wp_chatbot_hi_there', maybe_serialize(array('Hi There!')));
    }
    if(!get_option('qlcd_wp_chatbot_hello')) {
        update_option('qlcd_wp_chatbot_hello', maybe_serialize(array('Hi There!')));
    }
    if(!get_option('qlcd_wp_chatbot_welcome_back')) {
        update_option('qlcd_wp_chatbot_welcome_back', maybe_serialize(array('Welcome back', 'Good to see your again')));
    }
    if(!get_option('qlcd_wp_chatbot_asking_name')) {
        update_option('qlcd_wp_chatbot_asking_name', maybe_serialize(array('May I know your name?', 'What should I call you?')));
    }
    if(!get_option('qlcd_wp_chatbot_name_greeting')) {
        update_option('qlcd_wp_chatbot_name_greeting', maybe_serialize(array('Nice to meet you')));
    }
    if(!get_option('qlcd_wp_chatbot_i_am')) {
        update_option('qlcd_wp_chatbot_i_am', maybe_serialize(array('I am', 'This is')));
    }
    if(!get_option('qlcd_wp_chatbot_is_typing')) {
        update_option('qlcd_wp_chatbot_is_typing', maybe_serialize(array('is typing...')));
    }
    if(!get_option('qlcd_wp_chatbot_send_a_msg')) {
        update_option('qlcd_wp_chatbot_send_a_msg', maybe_serialize(array('Send a message.')));
    }
    if(!get_option('qlcd_wp_chatbot_choose_option')) {
        update_option('qlcd_wp_chatbot_choose_option', maybe_serialize(array('Choose an option.')));
    }
    if(!get_option('qlcd_wp_chatbot_viewed_products')) {
        update_option('qlcd_wp_chatbot_viewed_products', maybe_serialize(array('Recently viewed products')));
    }
    if(!get_option('qlcd_wp_chatbot_add_to_cart')) {
        update_option('qlcd_wp_chatbot_add_to_cart', maybe_serialize(array('Add to Cart')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_link')) {
        update_option('qlcd_wp_chatbot_cart_link', maybe_serialize(array('Cart')));
    }
    if(!get_option('qlcd_wp_chatbot_checkout_link')) {
        update_option('qlcd_wp_chatbot_checkout_link', maybe_serialize(array('Checkout')));
    }
    if(!get_option('qlcd_wp_chatbot_featured_product_welcome')) {
        update_option('qlcd_wp_chatbot_featured_product_welcome', maybe_serialize(array('I have found following featured products')));
    }
    if(!get_option('qlcd_wp_chatbot_viewed_product_welcome')) {
        update_option('qlcd_wp_chatbot_viewed_product_welcome', maybe_serialize(array('I have found following recently viewed products')));
    }
    if(!get_option('qlcd_wp_chatbot_latest_product_welcome')) {
        update_option('qlcd_wp_chatbot_latest_product_welcome', maybe_serialize(array('I have found following latest products')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_welcome')) {
        update_option('qlcd_wp_chatbot_cart_welcome', maybe_serialize(array('I have found following items from Shopping Cart.')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_title')) {
        update_option('qlcd_wp_chatbot_cart_title', maybe_serialize(array('Title')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_quantity')) {
        update_option('qlcd_wp_chatbot_cart_quantity', maybe_serialize(array('Qty')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_price')) {
        update_option('qlcd_wp_chatbot_cart_price', maybe_serialize(array('Price')));
    }
    if(!get_option('qlcd_wp_chatbot_no_cart_items')) {
        update_option('qlcd_wp_chatbot_no_cart_items', maybe_serialize(array('No items in the cart')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_updating')) {
        update_option('qlcd_wp_chatbot_cart_updating', maybe_serialize(array('Updating cart items ...')));
    }
    if(!get_option('qlcd_wp_chatbot_cart_removing')) {
        update_option('qlcd_wp_chatbot_cart_removing', maybe_serialize(array('Removing cart items ...')));
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_msg')) {
        update_option('qlcd_wp_chatbot_wildcard_msg', maybe_serialize(array('I am here to find what you need. What are you looking for?')));
    }
    if(!get_option('qlcd_wp_chatbot_empty_filter_msg')) {
        update_option('qlcd_wp_chatbot_empty_filter_msg', maybe_serialize(array('Sorry, I did not understand you.')));
    }
	if(!get_option('qlcd_wp_chatbot_did_you_mean')) {
        update_option('qlcd_wp_chatbot_did_you_mean', maybe_serialize(array('Did you mean?')));
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_help')) {
        update_option('qlcd_wp_chatbot_sys_key_help', 'start');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_product')) {
        update_option('qlcd_wp_chatbot_sys_key_product', 'product');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_catalog')) {
        update_option('qlcd_wp_chatbot_sys_key_catalog', 'catalog');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_order')) {
        update_option('qlcd_wp_chatbot_sys_key_order', 'order');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_support')) {
        update_option('qlcd_wp_chatbot_sys_key_support', 'faq');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_reset')) {
        update_option('qlcd_wp_chatbot_sys_key_reset', 'reset');
    }
    if(!get_option('qlcd_wp_chatbot_sys_key_email')) {
        update_option('qlcd_wp_chatbot_sys_key_email', 'email');
    }
    if(!get_option('qlcd_wp_chatbot_help_welcome')) {
        update_option('qlcd_wp_chatbot_help_welcome', maybe_serialize(array('Welcome to Help Section.')));
    }
    if(!get_option('qlcd_wp_chatbot_help_msg')) {
        update_option('qlcd_wp_chatbot_help_msg', maybe_serialize(array('<b>Type and Hit Enter</b><br><ul><li> <b>start</b> to Get back to the main menu. </li><li> <b>faq</b> for FAQ. </li><li> <b>email </b> to send eMail </li><li> <b>reset</b> to restart the chat</li></ul>')));
     }
    if(!get_option('qlcd_wp_chatbot_reset')) {
        update_option('qlcd_wp_chatbot_reset', maybe_serialize(array('Do you want to clear our chat history and start over?')));
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_product')) {
        update_option('qlcd_wp_chatbot_wildcard_product', maybe_serialize(array('Product Search')));
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_catalog')) {
        update_option('qlcd_wp_chatbot_wildcard_catalog', maybe_serialize(array('Catalog')));
    }
    if(!get_option('qlcd_wp_chatbot_featured_products')) {
        update_option('qlcd_wp_chatbot_featured_products', maybe_serialize(array('Featured Products')));
    }
    if(!get_option('qlcd_wp_chatbot_sale_products')) {
        update_option('qlcd_wp_chatbot_sale_products', maybe_serialize(array('Products on  Sale')));
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_support')) {
        update_option('qlcd_wp_chatbot_wildcard_support', 'FAQ');
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_site_search')) {
        update_option('qlcd_wp_chatbot_wildcard_site_search', 'Site Search');
    }
    if(!get_option('qlcd_wp_chatbot_messenger_label')) {
        update_option('qlcd_wp_chatbot_messenger_label', maybe_serialize(array('Chat with Us on Facebook Messenger')));
    }
    if(!get_option('qlcd_wp_chatbot_product_success')) {
        update_option('qlcd_wp_chatbot_product_success', maybe_serialize(array('Great! We have these products for', 'Found these products for')));
    }
    if(!get_option('qlcd_wp_chatbot_product_fail')) {
        update_option('qlcd_wp_chatbot_product_fail', maybe_serialize(array('Sorry, I found nothing')));
    }
    if(!get_option('qlcd_wp_chatbot_product_asking')) {
        update_option('qlcd_wp_chatbot_product_asking', maybe_serialize(array('What are you shopping for?')));
    }
    if(!get_option('qlcd_wp_chatbot_product_suggest')) {
        update_option('qlcd_wp_chatbot_product_suggest', maybe_serialize(array('You can browse our extensive catalog. Just pick a category from below:')));
    }
    if(!get_option('qlcd_wp_chatbot_product_infinite')) {
        update_option('qlcd_wp_chatbot_product_infinite', maybe_serialize(array('Too many choices? Let\'s try another search term', 'I may have something else for you. Why not search again?')));
    }
    if(!get_option('qlcd_wp_chatbot_load_more')) {
        update_option('qlcd_wp_chatbot_load_more', maybe_serialize(array('Load More')));
    }
    if(!get_option('qlcd_wp_chatbot_wildcard_order')) {
        update_option('qlcd_wp_chatbot_wildcard_order', maybe_serialize(array('Order Status')));
    }
    if(!get_option('qlcd_wp_chatbot_order_welcome')) {
        update_option('qlcd_wp_chatbot_order_welcome', maybe_serialize(array('Welcome to Order status section!')));
    }
    if(!get_option('qlcd_wp_chatbot_order_username_asking')) {
        update_option('qlcd_wp_chatbot_order_username_asking', maybe_serialize(array('Please type your username?')));
    }
    if(!get_option('qlcd_wp_chatbot_order_username_password')) {
        update_option('qlcd_wp_chatbot_order_username_password', maybe_serialize(array('Please type your password')));
    }
    if(!get_option('qlcd_wp_chatbot_order_username_not_exist')) {
        update_option('qlcd_wp_chatbot_order_username_not_exist', maybe_serialize(array('This username does not exist.')));
    }
    if(!get_option('qlcd_wp_chatbot_order_username_thanks')) {
        update_option('qlcd_wp_chatbot_order_username_thanks', maybe_serialize(array('Thank you for the username')));
    }
    if(!get_option('qlcd_wp_chatbot_order_password_incorrect')) {
        update_option('qlcd_wp_chatbot_order_password_incorrect', maybe_serialize(array('Sorry Password is not correct!')));
    }
    if(!get_option('qlcd_wp_chatbot_asking_email')) {
        update_option('qlcd_wp_chatbot_asking_email', maybe_serialize(array('Please provide your email address')));
    }
    if(!get_option('qlcd_wp_chatbot_order_not_found')) {
        update_option('qlcd_wp_chatbot_order_not_found', maybe_serialize(array('I did not find any order by you')));
    }
     if(!get_option('qlcd_wp_chatbot_order_found')) {
        update_option('qlcd_wp_chatbot_order_found', maybe_serialize(array('I have found the following orders')));
    }
    if(!get_option('qlcd_wp_chatbot_order_email_support')) {
        update_option('qlcd_wp_chatbot_order_email_support', maybe_serialize(array('Email our support center about your order.')));
    }
    if(!get_option('qlcd_wp_chatbot_support_welcome')) {
        update_option('qlcd_wp_chatbot_support_welcome', maybe_serialize(array('Welcome to FAQ Section')));
    }
    if(!get_option('qlcd_wp_chatbot_support_email')) {
        update_option('qlcd_wp_chatbot_support_email', 'Send us Email.');
    }
    if(!get_option('qlcd_wp_chatbot_asking_msg')) {
        update_option('qlcd_wp_chatbot_asking_msg', maybe_serialize(array('Thank you for email address. Please write your message now.')));
    }
	if(!get_option('qlcd_wp_chatbot_no_result')) {
        update_option('qlcd_wp_chatbot_no_result', maybe_serialize(array('Sorry, No result found!')));
    }
    if(!get_option('qlcd_wp_chatbot_invalid_email')) {
        update_option('qlcd_wp_chatbot_invalid_email', maybe_serialize(array('Sorry, Email address is not valid! Please provide a valid email.')));
    }
    if(!get_option('qlcd_wp_chatbot_support_phone')) {
        update_option('qlcd_wp_chatbot_support_phone', 'Leave your number. We will call you back!');
    }
    if(!get_option('qlcd_wp_chatbot_asking_phone')) {
        update_option('qlcd_wp_chatbot_asking_phone', maybe_serialize(array('Please provide your Phone number')));
    }
    if(!get_option('qlcd_wp_chatbot_thank_for_phone')) {
        update_option('qlcd_wp_chatbot_thank_for_phone', maybe_serialize(array('Thank you for Phone number')));
    }
    if(!get_option('qlcd_wp_chatbot_support_option_again')) {
        update_option('qlcd_wp_chatbot_support_option_again', maybe_serialize(array('You may choose option from below.')));
    }
    if(!get_option('qlcd_wp_chatbot_admin_email')) {
        update_option('qlcd_wp_chatbot_admin_email', $admin_email);
    }
    if(!get_option('qlcd_wp_chatbot_email_sub')) {
        update_option('qlcd_wp_chatbot_email_sub', sanitize_text_field('WPBot Support Mail'));
    }
	if(!get_option('qlcd_wp_site_search')) {
        update_option('qlcd_wp_site_search', sanitize_text_field('Site Search'));
    }
    if(!get_option('qlcd_wp_chatbot_email_sent')) {
        update_option('qlcd_wp_chatbot_email_sent', sanitize_text_field('Your email was sent successfully.Thanks!'));
    }
    if(!get_option('qlcd_wp_chatbot_email_fail')) {
        update_option('qlcd_wp_chatbot_email_fail', sanitize_text_field('Sorry! I could not send your mail! Please contact the webmaster.'));
    }
    if(!get_option('qlcd_wp_chatbot_relevant_post_link_openai')) {
        update_option('qlcd_wp_chatbot_relevant_post_link_openai', sanitize_text_field('Check the relevant pages for more details and up to date information:'));
    }
    if(!get_option('qlcd_wp_chatbot_notification_interval')) {
        update_option('qlcd_wp_chatbot_notification_interval', sanitize_text_field(5));
    }
    if(!get_option('qlcd_wp_chatbot_notifications')) {
        update_option('qlcd_wp_chatbot_notifications', maybe_serialize(array('Welcome to WPBot')));
    }
    if(!get_option('support_query')) {
        update_option('support_query', maybe_serialize(array('What is WPBot?')));
    }
    if(!get_option('support_ans')) {
        update_option('support_ans', maybe_serialize(array('WPBot is a stand alone Chat Bot with zero configuration or bot training required. This plug and play chatbot also does not require any 3rd party service integration like Facebook. This chat bot helps shoppers find the products they are looking for easily and increase store sales! WPBot is a must have plugin for trending conversational commerce or conversational shopping.')));
    }
    if(!get_option('qlcd_wp_chatbot_search_option')) {
        update_option('qlcd_wp_chatbot_search_option', 'standard');
    }
    if(!get_option('wp_chatbot_index_count')) {
        update_option('wp_chatbot_index_count', 0);
    }
    if(!get_option('wp_chatbot_app_pages')) {
        update_option('wp_chatbot_app_pages', 0);
    }
    //messenger options.
    if(!get_option('enable_wp_chatbot_messenger')) {
        update_option('enable_wp_chatbot_messenger', '');
    }
    if(!get_option('enable_wp_chatbot_messenger_floating_icon')) {
        update_option('enable_wp_chatbot_messenger_floating_icon', '');
    }
    if(!get_option('qlcd_wp_chatbot_fb_app_id')) {
        update_option('qlcd_wp_chatbot_fb_app_id', '');
    }
    if(!get_option('qlcd_wp_chatbot_fb_page_id')) {
        update_option('qlcd_wp_chatbot_fb_page_id', '');
    }
    if(!get_option('qlcd_wp_chatbot_fb_color')) {
        update_option('qlcd_wp_chatbot_fb_color', '#0084ff');
    }
    if(!get_option('qlcd_wp_chatbot_fb_in_msg')) {
        update_option('qlcd_wp_chatbot_fb_in_msg', 'Welcome to WPBot!');
    }
    if(!get_option('qlcd_wp_chatbot_fb_out_msg')) {
        update_option('qlcd_wp_chatbot_fb_out_msg', 'You are not logged in');
    }
    //Skype option
    if(!get_option('enable_wp_chatbot_skype_floating_icon')) {
        update_option('enable_wp_chatbot_skype_floating_icon', '');
    }
    if(!get_option('enable_wp_chatbot_skype_id')) {
        update_option('enable_wp_chatbot_skype_id', '');
    }
     //Whats App
    if(!get_option('enable_wp_chatbot_whats')) {
        update_option('enable_wp_chatbot_whats', '');
    }
    if(!get_option('qlcd_wp_chatbot_whats_label')) {
        update_option('qlcd_wp_chatbot_whats_label', maybe_serialize(array('Chat with Us on WhatsApp')));
    }
    if(!get_option('enable_wp_chatbot_floating_whats')) {
        update_option('enable_wp_chatbot_floating_whats', '');
    }
    if(!get_option('qlcd_wp_chatbot_whats_num')) {
        update_option('qlcd_wp_chatbot_whats_num', '');
    }
    //Viber
    if(!get_option('enable_wp_chatbot_floating_viber')) {
        update_option('enable_wp_chatbot_floating_viber', '');
    }
    if(!get_option('qlcd_wp_chatbot_viber_acc')) {
        update_option('qlcd_wp_chatbot_viber_acc', '');
    }
    //Integration others
    if(!get_option('enable_wp_chatbot_floating_phone')) {
        update_option('enable_wp_chatbot_floating_phone', '');
    }
    if(!get_option('qlcd_wp_chatbot_phone')) {
        update_option('qlcd_wp_chatbot_phone', '');
    }
    if(!get_option('enable_wp_chatbot_floating_link')) {
        update_option('enable_wp_chatbot_floating_link', '');
    }

    if(!get_option('qlcd_wp_chatbot_weblink')) {
        update_option('qlcd_wp_chatbot_weblink', '');
    }
    //Re-Tagetting
    if(!get_option('qlcd_wp_chatbot_ret_greet')) {
        update_option('qlcd_wp_chatbot_ret_greet', 'Hello');
    }
    if(!get_option('enable_wp_chatbot_exit_intent')) {
        update_option('enable_wp_chatbot_exit_intent', '');
    }
    if(!get_option('wp_chatbot_exit_intent_msg')) {
        update_option('wp_chatbot_exit_intent_msg', 'WAIT, WE HAVE A SPECIAL OFFER FOR YOU! Get Your 50% Discount Now. Use Coupon Code QC50 during checkout.');
    }
    if(!get_option('wp_chatbot_exit_intent_once')) {
        update_option('wp_chatbot_exit_intent_once', '');
    }

    if(!get_option('enable_wp_chatbot_scroll_open')) {
        update_option('enable_wp_chatbot_scroll_open', '');
    }
    if(!get_option('wp_chatbot_scroll_open_msg')) {
        update_option('wp_chatbot_scroll_open_msg', 'WE HAVE A VERY SPECIAL OFFER FOR YOU! Get Your 50% Discount Now. Use Coupon Code QC50 during checkout.');
    }
    if(!get_option('wp_chatbot_scroll_percent')) {
        update_option('wp_chatbot_scroll_percent', 50);
    }
    if(!get_option('wp_chatbot_scroll_once')) {
        update_option('wp_chatbot_scroll_once', '');
    }

    if(!get_option('enable_wp_chatbot_auto_open')) {
        update_option('enable_wp_chatbot_auto_open', '');
    }

    if(!get_option('enable_wp_chatbot_ret_sound')) {
        update_option('enable_wp_chatbot_ret_sound', '');
    }
    if(!get_option('enable_wp_chatbot_sound_initial')) {
        update_option('enable_wp_chatbot_sound_initial', '');
    }


    if(!get_option('wp_chatbot_auto_open_msg')) {
        update_option('wp_chatbot_auto_open_msg', 'A SPECIAL OFFER FOR YOU! Get Your 50% Discount Now. Use Coupon Code QC50 during checkout.');
    }
    if(!get_option('wp_chatbot_auto_open_time')) {
        update_option('wp_chatbot_auto_open_time', 10);
    }
    if(!get_option('wp_chatbot_auto_open_once')) {
        update_option('wp_chatbot_auto_open_once', '');
    }
     if(!get_option('wp_chatbot_inactive_once')) {
        update_option('wp_chatbot_inactive_once', '');
    }

    //To complete checkout.
    if(!get_option('enable_wp_chatbot_ret_user_show')) {
        update_option('enable_wp_chatbot_ret_user_show', '');
    }
    if(!get_option('wp_chatbot_auto_open_msg')) {
        update_option('wp_chatbot_checkout_msg', 'You have products in shopping cart, please complete your order.');
    }
    if(!get_option('wp_chatbot_inactive_time')) {
        update_option('wp_chatbot_inactive_time', 300);
    }
    if(!get_option('enable_wp_chatbot_inactive_time_show')) {
        update_option('enable_wp_chatbot_inactive_time_show', '');
    }

    if(!get_option('wp_chatbot_proactive_bg_color')) {
        update_option('wp_chatbot_proactive_bg_color', '#ffffff');
    }
    if(!get_option('disable_wp_chatbot_feedback')) {
        update_option('disable_wp_chatbot_feedback','');
    }
	if(!get_option('disable_wp_chatbot_faq')) {
        update_option('disable_wp_chatbot_faq','');
    }
    if(!get_option('qlcd_wp_chatbot_feedback_label')) {
        update_option('qlcd_wp_chatbot_feedback_label',maybe_serialize(array('Send Feedback')));
    }

    if(!get_option('enable_wp_chatbot_meta_title')) {
        update_option('enable_wp_chatbot_meta_title','');
    }
    if(!get_option('qlcd_wp_chatbot_meta_label')) {
        update_option('qlcd_wp_chatbot_meta_label','*New Messages');
    }

    if(!get_option('disable_wp_chatbot_call_gen')) {
        update_option('disable_wp_chatbot_call_gen', '');
    }
	
	if(!get_option('disable_wp_chatbot_site_search')) {
        update_option('disable_wp_chatbot_site_search', '');
    }
    if(!get_option('disable_wp_chatbot_call_sup')) {
        update_option('disable_wp_chatbot_call_sup', '');
    }

    if(!get_option('qlcd_wp_chatbot_phone_sent')) {
        update_option('qlcd_wp_chatbot_phone_sent',  'Thanks for your phone number. We will call you ASAP!');
    }
    if(!get_option('qlcd_wp_chatbot_phone_fail')) {
        update_option('qlcd_wp_chatbot_phone_fail', 'Sorry! I could not collect your phone number!');
    }
    if(!get_option('qlcd_wp_chatbot_asking_search_keyword')){
        update_option('qlcd_wp_chatbot_asking_search_keyword', 'Please enter your keyword for searching');
    }
    if(!get_option('qlcd_wp_chatbot_found_result')){
        update_option('qlcd_wp_chatbot_found_result', 'We have found these results');
    }
    if(!get_option('enable_wp_chatbot_opening_hour')) {
        update_option('enable_wp_chatbot_opening_hour', '');
    }
    if(!get_option('enable_wp_chatbot_opening_hour')) {
        update_option('wpwbot_hours', array());
    }

    if(!get_option('enable_wp_chatbot_dailogflow')) {
        update_option('enable_wp_chatbot_dailogflow', '');
    }
    if(!get_option('qlcd_wp_chatbot_dialogflow_client_token')) {
        update_option('qlcd_wp_chatbot_dialogflow_client_token', '');
    }
    if(!get_option('qlcd_wp_chatbot_dialogflow_defualt_reply')) {
        update_option('qlcd_wp_chatbot_dialogflow_defualt_reply', 'Sorry, I did not understand you. You may browse');
    }
    if(!get_option('openai_max_tokens')) {
        update_option('openai_max_tokens', '200');
    }
    if(!get_option('qcld_openai_suffix')) {
        update_option('qcld_openai_suffix', 'qcld');
    }
	if(!get_option('qlcd_wp_chatbot_dialogflow_agent_language')) {
        update_option('qlcd_wp_chatbot_dialogflow_agent_language', 'en');
    }
    if(!get_option('enable_wp_chatbot_post_content')) {
        update_option('enable_wp_chatbot_post_content', '1');
    }
    if(!get_option('openai_engines')) {
        update_option('openai_engines', 'gpt-4o');
    }
   // if(!get_option('skip_wp_greetings')) {
        update_option('skip_wp_greetings', '');
    //}
    set_transient( 'qcld_bot_clear_cache', 1, DAY_IN_SECONDS );
}
}
/*
 * Reset Options will be insert as defualt data
 */
add_action('wp_ajax_qcld_wb_chatboot_delete_all_options', 'qcld_wb_chatboot_delete_all_options');
//add_action('wp_ajax_nopriv_qcld_wb_chatboot_delete_all_options', 'qcld_wb_chatboot_delete_all_options');
//Jarvis all option will be delete during uninstlling.
if( !function_exists('qcld_wb_chatboot_delete_all_options') ){
function qcld_wb_chatboot_delete_all_options(){
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized access' );
    }
    
    if ( ! isset( $_POST['wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ), 'wp_chatbot' ) ) {
        wp_die( 'No cheating' );
    }
    delete_option('disable_wp_chatbot');
    delete_option('disable_wp_chatbot_icon_animation');
    delete_option('disable_wp_chatbot_on_mobile');
    delete_option('qlcd_wp_chatbot_admin_email');
    delete_option('qlcd_wp_chatbot_from_email');
    
    delete_option('disable_wp_chatbot_product_search');
    delete_option('disable_wp_chatbot_catalog');
    delete_option('disable_wp_chatbot_order_status');
    delete_option('disable_wp_chatbot_notification');
    delete_option('enable_wp_chatbot_rtl');
    delete_option('show_menu_after_greetings');
    delete_option('enable_chat_session');
    
    delete_option('enable_wp_chatbot_mobile_full_screen');
    delete_option('wpbot_preloading_time');
    delete_option('disable_wp_chatbot_cart_item_number');
    delete_option('disable_wp_chatbot_featured_product');
    delete_option('disable_wp_chatbot_sale_product');
    delete_option('wp_chatbot_open_product_detail');
    delete_option('qlcd_wp_chatbot_product_orderby');
    delete_option('qlcd_wp_chatbot_product_order');
    delete_option('qlcd_wp_chatbot_ppp');
    delete_option('wp_chatbot_show_parent_category');
    delete_option('wp_chatbot_show_sub_category');
    delete_option('wp_chatbot_exclude_stock_out_product');
    delete_option('wp_chatbot_show_home_page');
    delete_option('qc_wpbot_menu_order');
	
    delete_option('wp_chatbot_show_posts');
    delete_option('wp_chatbot_show_pages');
    delete_option('wp_chatbot_show_pages_list');
    delete_option('wp_chatbot_exclude_post_list');
    delete_option('wp_chatbot_show_wpcommerce');
    delete_option('qlcd_wp_chatbot_stop_words_name');
    delete_option('qlcd_wp_chatbot_stop_words');
    delete_option('qlcd_wp_chatbot_order_user');
    delete_option('wp_chatbot_icon');
    delete_option('wp_chatbot_floatingiconbg_color');
    delete_option('wp_chatbot_agent_image');
    delete_option('qcld_wb_chatbot_theme');
    delete_option('qcld_wb_chatbot_change_bg');
    delete_option('wp_chatbot_custom_css');
    delete_option('qlcd_wp_chatbot_host');
    delete_option('qlcd_wp_chatbot_agent');
    delete_option('qlcd_wp_chatbot_yes');
    delete_option('qlcd_wp_chatbot_no');
    delete_option('qlcd_wp_chatbot_or');
    delete_option('qlcd_wp_chatbot_sorry');
    delete_option('qlcd_wp_chatbot_agent_join');
    delete_option('qlcd_wp_chatbot_welcome');
    delete_option('qlcd_wp_chatbot_back_to_start');
    delete_option('qlcd_wp_chatbot_hi_there');
    delete_option('qlcd_wp_chatbot_hello');
    delete_option('qlcd_wp_chatbot_welcome_back');
    delete_option('qlcd_wp_chatbot_asking_name');
    delete_option('qlcd_wp_chatbot_name_greeting');
    delete_option('qlcd_wp_chatbot_i_am');
    delete_option('qlcd_wp_chatbot_wildcard_msg');
    delete_option('qlcd_wp_chatbot_empty_filter_msg');
    delete_option('qlcd_wp_chatbot_did_you_mean');
    delete_option('qlcd_wp_chatbot_wildcard_product');
    delete_option('qlcd_wp_chatbot_wildcard_catalog');
    delete_option('qlcd_wp_chatbot_featured_products');
    delete_option('qlcd_wp_chatbot_sale_products');
    delete_option('qlcd_wp_chatbot_wildcard_support');
    delete_option('qlcd_wp_chatbot_wildcard_site_search');
    delete_option('qlcd_wp_chatbot_messenger_label');
    delete_option('qlcd_wp_chatbot_product_success');
    delete_option('qlcd_wp_chatbot_product_fail');
    delete_option('qlcd_wp_chatbot_product_asking');
    delete_option('qlcd_wp_chatbot_product_suggest');
    delete_option('qlcd_wp_chatbot_product_infinite');
    delete_option('qlcd_wp_chatbot_load_more');
    delete_option('qlcd_wp_chatbot_wildcard_order');
    delete_option('qlcd_wp_chatbot_order_welcome');
    delete_option('qlcd_wp_chatbot_order_username_asking');
    delete_option('qlcd_wp_chatbot_order_username_password');
    delete_option('qlcd_wp_chatbot_support_welcome');
    delete_option('qlcd_wp_chatbot_support_email');
    delete_option('qlcd_wp_chatbot_asking_email');
    delete_option('qlcd_wp_chatbot_asking_msg');
    delete_option('qlcd_wp_chatbot_no_result');
    delete_option('qlcd_wp_chatbot_admin_email');
    delete_option('open_links_new_window');
    delete_option('qlcd_wp_chatbot_email_sub');
    delete_option('qlcd_wp_site_search');
    delete_option('qlcd_wp_chatbot_email_sent');
    delete_option('qlcd_wp_chatbot_support_phone');
    delete_option('qlcd_wp_chatbot_asking_phone');
    delete_option('qlcd_wp_chatbot_thank_for_phone');
    delete_option('qlcd_wp_chatbot_sys_key_help');
    delete_option('qlcd_wp_chatbot_sys_key_product');
    delete_option('qlcd_wp_chatbot_sys_key_catalog');
    delete_option('qlcd_wp_chatbot_sys_key_order');
    delete_option('qlcd_wp_chatbot_sys_key_support');
    delete_option('qlcd_wp_chatbot_sys_key_reset');
    delete_option('qlcd_wp_chatbot_sys_key_email');
    delete_option('qlcd_wp_chatbot_order_username_not_exist');
    delete_option('qlcd_wp_chatbot_order_username_thanks');
    delete_option('qlcd_wp_chatbot_order_password_incorrect');
    delete_option('qlcd_wp_chatbot_order_not_found');
    delete_option('qlcd_wp_chatbot_order_found');
    delete_option('qlcd_wp_chatbot_order_email_support');
    delete_option('qlcd_wp_chatbot_support_option_again');
    delete_option('qlcd_wp_chatbot_invalid_email');
    delete_option('qlcd_wp_chatbot_shopping_cart');
    delete_option('qlcd_wp_chatbot_add_to_cart');
    delete_option('qlcd_wp_chatbot_cart_link');
    delete_option('qlcd_wp_chatbot_checkout_link');
    delete_option('qlcd_wp_chatbot_cart_welcome');
    delete_option('qlcd_wp_chatbot_featured_product_welcome');
    delete_option('qlcd_wp_chatbot_viewed_product_welcome');
    delete_option('qlcd_wp_chatbot_latest_product_welcome');
    delete_option('qlcd_wp_chatbot_cart_title');
    delete_option('qlcd_wp_chatbot_cart_quantity');
    delete_option('qlcd_wp_chatbot_cart_price');
    delete_option('qlcd_wp_chatbot_no_cart_items');
    delete_option('qlcd_wp_chatbot_cart_updating');
    delete_option('qlcd_wp_chatbot_cart_removing');
    delete_option('qlcd_wp_chatbot_email_fail');
    delete_option('qlcd_wp_chatbot_relevant_post_link_openai');
    delete_option('support_query');
    delete_option('support_ans');
    delete_option('qlcd_wp_chatbot_notification_interval');
    delete_option('qlcd_wp_chatbot_notifications');
    delete_option( 'qlcd_wp_chatbot_search_option');
    delete_option( 'wp_chatbot_index_count');
    delete_option( 'wp_chatbot_app_pages');
    //messenger option
    delete_option( 'enable_wp_chatbot_messenger');
    delete_option( 'enable_wp_chatbot_messenger_floating_icon');
    delete_option( 'qlcd_wp_chatbot_fb_app_id');
    delete_option( 'qlcd_wp_chatbot_fb_page_id');
    delete_option( 'qlcd_wp_chatbot_fb_color');
    delete_option( 'qlcd_wp_chatbot_fb_in_msg');
    delete_option( 'qlcd_wp_chatbot_fb_out_msg');
    //skype option
    delete_option( 'enable_wp_chatbot_skype_floating_icon');
    delete_option( 'enable_wp_chatbot_skype_id');
    //whats app
    delete_option( 'enable_wp_chatbot_whats');
    delete_option( 'qlcd_wp_chatbot_whats_label');
    delete_option( 'enable_wp_chatbot_floating_whats');
    delete_option( 'qlcd_wp_chatbot_whats_num');
    // Viber
    delete_option( 'enable_wp_chatbot_floating_viber');
    delete_option( 'qlcd_wp_chatbot_viber_acc');
    //Integration others
    delete_option( 'enable_wp_chatbot_floating_phone');
    delete_option( 'qlcd_wp_chatbot_phone');
    delete_option( 'enable_wp_chatbot_floating_link');
    delete_option( 'qlcd_wp_chatbot_weblink');
    //Re Targetting
    delete_option( 'qlcd_wp_chatbot_ret_greet');
    delete_option( 'enable_wp_chatbot_exit_intent');
    delete_option( 'wp_chatbot_exit_intent_msg');
    delete_option( 'wp_chatbot_exit_intent_once');

    delete_option( 'enable_wp_chatbot_scroll_open');
    delete_option( 'wp_chatbot_scroll_open_msg');
    delete_option( 'wp_chatbot_scroll_percent');
    delete_option( 'wp_chatbot_scroll_once');

    delete_option( 'enable_wp_chatbot_auto_open');
    delete_option( 'enable_wp_chatbot_ret_sound');
    delete_option( 'enable_wp_chatbot_sound_initial');
    delete_option( 'disable_wp_chatbot_feedback');
    delete_option( 'disable_wp_chatbot_faq');
    delete_option( 'qlcd_wp_chatbot_feedback_label');
    delete_option( 'enable_wp_chatbot_meta_title');
    delete_option( 'qlcd_wp_chatbot_meta_label');
    delete_option( 'wp_chatbot_auto_open_msg');
    delete_option( 'wp_chatbot_auto_open_time');
    delete_option( 'wp_chatbot_auto_open_once');
    delete_option( 'wp_chatbot_inactive_once');
    delete_option( 'wp_chatbot_proactive_bg_color');
    delete_option( 'qlcd_wp_chatbot_phone_sent');
    delete_option( 'qlcd_wp_chatbot_phone_fail');
    delete_option('qlcd_wp_chatbot_asking_search_keyword');
    delete_option('qlcd_wp_chatbot_found_result');
    delete_option( 'disable_wp_chatbot_call_gen');
    delete_option( 'disable_wp_chatbot_site_search');
    delete_option( 'enable_wp_chatbot_post_content');
    delete_option( 'disable_wp_chatbot_call_sup');

    delete_option( 'enable_wp_chatbot_ret_user_show');
    delete_option( 'enable_wp_chatbot_inactive_time_show');
    delete_option( 'wp_chatbot_inactive_time');
    delete_option( 'wp_chatbot_checkout_msg');
    delete_option( 'qlcd_wp_chatbot_shopper_demo_name');
    delete_option( 'qlcd_wp_chatbot_is_typing');
    delete_option( 'qlcd_wp_chatbot_send_a_msg');
    delete_option( 'qlcd_wp_chatbot_choose_option');
    delete_option( 'qlcd_wp_chatbot_viewed_products');
    delete_option( 'qlcd_wp_chatbot_help_welcome');
    delete_option( 'qlcd_wp_chatbot_help_msg');
    delete_option( 'qlcd_wp_chatbot_reset');
    delete_option( 'enable_wp_chatbot_opening_hour');
    delete_option( 'wpwbot_hours');
    delete_option( 'enable_wp_chatbot_dailogflow');
    delete_option( 'qlcd_wp_chatbot_dialogflow_client_token');
    delete_option( 'qlcd_wp_chatbot_dialogflow_defualt_reply');
    delete_option( 'qlcd_wp_chatbot_dialogflow_agent_language');

	delete_option( 'qlcd_wp_chatbot_dialogflow_project_id');
    delete_option( 'wp_chatbot_df_api');    
    delete_option( 'qlcd_wp_chatbot_dialogflow_project_key');

    delete_option( 'wp_chatbot_bot_msg_bg_color');
    delete_option( 'wp_chatbot_bot_msg_text_color');
    delete_option( 'wp_chatbot_user_msg_bg_color');
    delete_option( 'wp_chatbot_user_msg_text_color');
    delete_option( 'wp_chatbot_buttons_bg_color');
    delete_option( 'wp_chatbot_buttons_text_color');

    delete_option( 'wp_chatbot_buttons_bg_color_hover');
    delete_option( 'wp_chatbot_buttons_text_color_hover');
    
    delete_option( 'wp_chatbot_theme_secondary_color');
    delete_option( 'wp_chatbot_theme_primary_color');
    delete_option( 'wp_chatbot_header_background_color');
    delete_option('wp_chatbot_font_size');
    delete_option('wp_chat_user_font_family');
    delete_option('wp_chat_bot_font_family');
    delete_option('wp_chatbot_bot_font');
    delete_option('wp_chatbot_user_font');
	set_transient( 'qcld_bot_clear_cache', 1, DAY_IN_SECONDS );
    qcld_wb_chatboot_defualt_options();
    $html='Reset all options to default successfully.';
    wp_send_json($html);
}
}

if( !function_exists('wpbot_free_qc_upgrade_completed') ){
    function wpbot_free_qc_upgrade_completed( $upgrader_object, $options ) {
        // The path to our plugin's main file
        $our_plugin = plugin_basename( __FILE__ );
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            // Iterate through the plugins being updated and check if ours is there
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == $our_plugin ) {
                    set_transient( 'qcld_bot_clear_cache', 1, DAY_IN_SECONDS );
                }
            }
        }
        update_option( 'qcld_openai_relevant_post', ['post','page'] );
    }
}
add_action( 'upgrader_process_complete', 'wpbot_free_qc_upgrade_completed', 10, 2 );

 

/**
 *
 * Open Ai integration
 *
 */
if( !function_exists('wpbot_openAi_setting_func') ){
function wpbot_openAi_setting_func (){

    require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."includes/admin/templates/ai-admin.php");
   // require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH."qcld-openai-bot.php");

}
}

/**
 *
 * Function to load translation files.
 *
 */

if( !function_exists('wp_chatbot_lang_init') ){
    function wp_chatbot_lang_init() {
        load_plugin_textdomain( 'chatbot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}
add_action( 'plugins_loaded', 'wp_chatbot_lang_init');

$wpbot_feedback = new Qcld_Wp_Usage_Feedback(
			__FILE__,
			'plugins@quantumcloud.com',
			false,
			true

		);
if( !function_exists('wpbot_help_page_callback_func') ){
function wpbot_help_page_callback_func(){
	?>



<div class="wrap qcld-main-wrapper">
   <div class="qcld-wp-chatbot-wrap-header">
      <a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
      <p><strong>Core Version:</strong> v<?php echo QCLD_wpCHATBOT_VERSION; ?></p>
      <ul class="qcld-wp-chatbot-wrap-version-wrapper">
         <li>
            <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a> 
         </li>
      </ul>
   </div>
   <div class="qcld-wrap swpm-admin-menu-wrap">
      <div class="nav-tab-wrapper sld_nav_container wppt_nav_container qcld_help_wppt_nav_container"> 
         <a class="nav-tab sld_click_handle nav-tab-active"  href="#general_int"><span class="wpwbot-admin-tab-icon "> <i class="fa fa-rocket"></i> </span><?php echo esc_html('Getting Started'); ?></a> 
         <a class="nav-tab sld_click_handle "  href="#general_wp_nutshell"><span class="wpwbot-admin-tab-icon "> <i class="fa fa-hourglass-start"></i> </span><?php echo esc_html('WPBot – In a Nutshell'); ?></a> 
         <a class="nav-tab sld_click_handle" id="general_debuggings"  href="#general_debugging"><span class="wpwbot-admin-tab-icon "> <i class="fa fa-question-circle-o"></i> </span><?php echo esc_html('Troubleshooting & FAQ'); ?></a> 
      </div>
      <div class="content_qcbot_help_secion">
         <div class=" wppt-settings-section" id="general_wp_nutshell" style="display:none;">
            <div class="content form-container qcbot_help_secion" style="">
               <!-- new Section -->
               <h3 class="qcld-wpbot-main-tabs-title"><?php echo esc_html__('WPBot – In a Nutshell', 'chatbot'); ?></h3>
               <p><?php echo esc_html__('This is by no means a comprehensive list of WPBot features. But knowing these core terms will help you understand how WPBot was designed to work.', 'chatbot'); ?></p>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="IntentheadingOne">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#IntentcollapseOne" aria-expanded="false" aria-controls="IntentcollapseOne"> <?php esc_html_e('Intents', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="IntentcollapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="IntentheadingOne">
                     <div class="panel-body"> 
                        <?php echo esc_html_e(' Intent is all about what the user wants to get out of the interaction. Whenever a user types something or clicks a button, the ChatBot will try to understand what the user wants and fulfill the request with appropriate responses.', 'chatbot'); ?></br></br>
                        <?php echo esc_html_e('You have to create possible Intent Responses using different features of the WPBot so the bot can respond accordingly. You can create Responses for various Intents using:', 'chatbot'); ?><b>
                        <?php echo esc_html_e('Simple Text Responses, Conversational form builder, FAQ, Site Search, Send an eMail, Newsletter Subscription, DialogFlow, OpenAI etc.', 'chatbot'); ?></b></br></br>
                        <?php echo esc_html_e('Please check this article for', 'chatbot'); ?> <span class="wppt_nav_container qcld-plan-tab-text"> 
                        <a  href="#general_int"><?php echo esc_html_e('more details', 'chatbot'); ?></a> </span>  <?php echo esc_html_e('on how you can create Intents and Responses.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingSix">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseSix" aria-expanded="false" aria-controls="collapseSix"> <?php esc_html_e('Start Menu', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseSix" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingSix">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('While using a ChatBot, users can get lost or not know how to Interact with the Bot. That is why we have a Start menu to always give the user', 'chatbot'); ?> <b><?php echo esc_html_e('options to do more', 'chatbot'); ?></b>. <?php echo esc_html_e('From ChatBot->Settings->Start Menu you can drag Available Menu Items (Intents) to the Active Menu Items area.', 'chatbot'); ?></br></br>
                        <?php echo esc_html_e('Besides the built-in Intents, you can also create custom Intents for your Start Menu using', 'chatbot'); ?> <b><?php echo esc_html_e('Simple Text Responses', 'chatbot'); ?></b> and <b><?php echo esc_html_e('Conversational form builder', 'chatbot'); ?></b>. <?php echo esc_html_e('You can create almost any kind of response with the combinations of the two.', 'chatbot'); ?></br></br>
                        <?php echo esc_html_e('We recommend enabling', 'chatbot'); ?><b><?php echo esc_html_e(' Show Start Menu After Greetings ', 'chatbot'); ?></b><?php echo esc_html_e('from ChatBot Pro->Settings->General settings.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingSeven">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven"> <?php esc_html_e('Settings', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseSeven" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingSeven">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('Head over to ChatBot Pro->Settings->General and make sure to Enable the Floating Icon. As soon as you do that, the ChatBot can start working for your users. Make sure to drag some items to the Active Menu area under the Start Menu.', 'chatbot'); ?></br></br>
                        <?php echo esc_html_e('The ChatBot settings area is full of options. Do not be intimidated by that. You do not need to use all the options – just what you need. Head over to the Settings->', 'chatbot'); ?><b><?php echo esc_html_e('Icons and Themes', 'chatbot'); ?></b> <?php echo esc_html_e('for options to customize your ChatBot. You will also find options to embed the ChatBot on a page, click to chat, FAQ builder etc. under the Setting options.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingEight">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseEight" aria-expanded="false" aria-controls="collapseEight"> <?php esc_html_e('Language Center', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseEight" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingEight">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('You can use the ChatBot in', 'chatbot'); ?> <b><?php echo esc_html_e('ANY language', 'chatbot'); ?></b>. <?php echo esc_html_e('Just translate the texts used by the ChatBot from the WordPress dashboard ChatBot Pro->', 'chatbot'); ?><b><?php echo esc_html_e('Language Center. Multi language', 'chatbot'); ?></b> <?php echo esc_html_e('module is available in the Master License..', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingtwo">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapsetwo" aria-expanded="false" aria-controls="collapseOne"> <?php esc_html_e('Simple Text Responses', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapsetwo" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingtwo">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('You can use ChatBot Pro->Simple Text Responses to create', 'chatbot'); ?> <b><?php echo esc_html_e('text-based responses', 'chatbot'); ?></b> <?php echo esc_html_e('that users may ask your ChatBot. Just define the questions, answers, and some keywords and you are done. This is a much simpler', 'chatbot'); ?>  <b><?php echo esc_html_e('alternative ', 'chatbot'); ?></b> <?php echo esc_html_e('to DialogFlow or OpenAI.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingThree">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseThree" aria-expanded="false" aria-controls="collapseThree"> <?php esc_html_e('Conversational Forms', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseThree" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingThree">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('Use conversational forms to collect information from the users. This is also great for Button driven workflow. Create conditional conversations and forms for a native WordPress ChatBot experience. Build Standard Forms, Dynamic Forms with', 'chatbot'); ?> <b> <?php echo esc_html_e('conditional fields, Calculators, Appointment booking', 'chatbot'); ?></b> <?php echo esc_html_e('etc.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingten">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseten" aria-expanded="false" aria-controls="collapseten"> <?php esc_html_e('Retargeting (Pro feature)', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseten" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingten">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('Retargeting is a powerful feature to grab your user’s attention with motivating information (a sale, coupon, ebook etc.). You can trigger a Retargeting message and the ChatBot window will automatically', 'chatbot'); ?> <b> <?php echo esc_html_e('automatically ', 'chatbot'); ?></b><?php echo esc_html_e('open up with your message.  You can trigger Retargeting for ', 'chatbot'); ?><b> <?php echo esc_html_e('Exit Intent, Exit Intent, Scroll Intent, Auto After “X” Seconds, Checkout', 'chatbot'); ?></b> <?php echo esc_html_e('etc.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingFour">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseFour" aria-expanded="false" aria-controls="collapseFour"> <?php esc_html_e('OpenAI or DialogFlow', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseFour" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFour">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('If you need a bot that can understand natural language better, use either OpenAI or DialogFlow. Between the two', 'chatbot'); ?> <b> <?php echo esc_html_e('DialogFlow', 'chatbot'); ?></b> <?php echo esc_html_e('is better if you want to', 'chatbot'); ?> <b> <?php echo esc_html_e('provide customer support', 'chatbot'); ?></b>. <?php echo esc_html_e('OpenAI is better at generic questions and training OpenAI also requires a large dataset. But you do not have to use either 3rd party service. Using OpenAI or DialogFlow requires some patience and', 'chatbot'); ?> <b> <?php echo esc_html_e('effort', 'chatbot'); ?></b>. <?php echo esc_html_e('You may very well achieve what you need using ', 'chatbot'); ?><b> <?php echo esc_html_e('Simple Text Responses', 'chatbot'); ?></b> <?php echo esc_html_e('and/or', 'chatbot'); ?> <b> <?php echo esc_html_e('Conversational form builder', 'chatbot'); ?></b> <?php echo esc_html_e('instead.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingFive">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseFive" aria-expanded="false" aria-controls="collapseFive"> <?php esc_html_e('Getting Help', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseFive" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFive">
                     <div class="panel-body"> 
                        <?php echo esc_html_e('We have built-in Help section under each module. Please check them out and you will get many answers to the questions you may have. If you cannot find the answer to something particular, just contact us.', 'chatbot'); ?> <b><?php echo esc_html_e('Pro version ', 'chatbot'); ?></b><?php echo esc_html_e('users can open a support ticket from here. We are ', 'chatbot'); ?><b><?php echo esc_html_e('friendly ', 'chatbot'); ?></b><?php echo esc_html_e('and always here to help.', 'chatbot'); ?>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class=" wppt-settings-section" id="general_int">
            <div class="content form-container qcbot_help_secion" style="">
               <!-- new Section -->
               <h3 class="qcld-wpbot-main-tabs-title"><?php echo esc_html__('WPBot Interactions', 'chatbot'); ?></h3>
               <p><?php echo esc_html__('You can use WPBot to both answer user questions and collect information from the users.', 'chatbot'); ?></p>
               <h4><?php echo esc_html__('To create answers to user questions you can use:', 'chatbot'); ?></h4>
               <p> <b> <?php echo esc_html__('Simple Text Responses (built-in), FAQ(built-in), Site search(built-in), Product search(built-in Pro feature), DialogFlow(3rd Party) or OpenAI(3rd Party)', 'chatbot'); ?></b></p>
               <h4> <?php echo esc_html__('To collect information from your users you can use:', 'chatbot'); ?></h4>
               <p><?php echo esc_html__('Conversational forms(built-in), Mail us(built-in), Call me back(built-in), Collect feedback(built-in) features', 'chatbot'); ?></p>
                <hr>
                     <p>
                   <b> <?php echo esc_html__('When you activate the plugin, by default only the Site search option will work. Site search displays links to your website pages that contain the keywords in the user query. ', 'chatbot'); ?>
                    </b></p>
                    <p><b><?php echo esc_html__('To generate direct text responses, you need to use either Simple Text Responses or AI services. ', 'chatbot'); ?>
                    </b></p>              
               <hr>
               <h4><?php echo esc_html__('You can create user interactions in the following ways:', 'chatbot'); ?></h4>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingOne">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> <?php esc_html_e('Predefined intents - Built-in ChatBot Features', 'chatbot'); ?>  </a>
                     </h4>
                  </div>
                  <div id="collapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
                     <div class="panel-body">
                        <?php esc_html_e('Predefined intents can work without integration to DialogFlow API and AI. These are readily available as soon as you install the plugin and can be turned on or off individually.', 'chatbot'); ?>  
                        <div class="section-container">
                           <div class="wpb_column vc_column_container vc_col-sm-6">
                              <div class="vc_column-inner ">
                                 <div class="wpb_wrapper">
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span><?php esc_html_e('Simple Text Responses', 'chatbot'); ?> 
                                          </h3>
                                          <p><?php esc_html_e('Create unlimited text responses from your WordPress backend. The ChatBot uses advanced search algorithm for natural language phrase matching with user input.', 'chatbot'); ?> </p>
                                       </div>
                                    </div>
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span><?php esc_html_e('Send eMail, Call Me Back &amp; Feedback Collection', 'chatbot'); ?>
                                          </h3>
                                          <p><?php esc_html_e('Users can send a email to the site admin directly from the Chat window for customer support. The Call Me Back feature lets you get call requests from your customers which will be emailed to you. You can also use WPBot to collect Feedback from your customers regarding anything! You can disable/enable these features from the Start Menu.', 'chatbot'); ?></p>
                                       </div>
                                    </div>
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span><?php esc_html_e('Advanced Site Search', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                          </h3>
                                          <p><?php esc_html_e('If no matching text response is found WPBot will conduct an advanced website search and try to match user queries with your website contents and show results.', 'chatbot'); ?>  </p>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <div class="wpb_column vc_column_container vc_col-sm-6">
                              <div class="vc_column-inner ">
                                 <div class="wpb_wrapper">
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span><?php esc_html_e('Frequently Asked Questions', 'chatbot'); ?>
                                          </h3>
                                          <p><?php esc_html_e('Create a set of Frequently Asked Questions or FAQ so users can quickly find answers to the most common questions they have.', 'chatbot'); ?></p>
                                       </div>
                                    </div>
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span>Ask for name, email, phone number etc.
                                          </h3>
                                          <p><?php esc_html_e('Asking for the name is the default workflow. In the pro version, you can also ask for an email and phone number if you want to or skip the Greetings part altogether and load any intent of your choice.', 'chatbot'); ?></p>
                                       </div>
                                    </div>
                                    <div class="to-icon-box  left txt-left">
                                       <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                             <span>// </span><?php esc_html_e('Newsletter Subscription', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                          </h3>
                                          <p><?php esc_html_e('WPBot can prompt User for eMail subscription. Link this with your Retargeting ChatBot window popup and a special offer. People can register their email address that you can later export as CSV!', 'chatbot'); ?> <strong>GDPR compliant</strong> with unsubscribe option from the ChatBot! </p>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="headingTwo">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo"><?php esc_html_e(' Menu Driven - Created with Conversational Form Builder Addon', 'chatbot'); ?> </a>
                     </h4>
                  </div>
                  <div id="collapseTwo" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingTwo">
                     <div class="panel-body">
                        <p><?php esc_html_e('Extend the Start Menu with the', 'chatbot'); ?> <strong><?php esc_html_e('powerful Conversational Forms', 'chatbot'); ?></strong>&nbsp;<?php esc_html_e(' Addon for WPBot. It extends WPBot’s functionality and adds the ability to create', 'chatbot'); ?> <strong><?php esc_html_e('conditional conversations', 'chatbot'); ?></strong> <?php esc_html_e('and/or', 'chatbot'); ?> <strong><?php esc_html_e('forms', 'chatbot'); ?></strong> <?php esc_html_e('for the WPBot. It is a visual,', 'chatbot'); ?> <strong><?php esc_html_e('drag and drop', 'chatbot'); ?></strong><?php esc_html_e(' form builder that is easy to use and very flexible. Supports conditional logic and use of variables to build all types of forms or just', 'chatbot'); ?> <strong><?php esc_html_e('menu driven', 'chatbot'); ?></strong>
                           <strong><?php esc_html_e('conversations', 'chatbot'); ?> </strong><?php esc_html_e('with if else logic', 'chatbot'); ?>  <strong>. </strong><?php esc_html_e('Conversations or forms can be', 'chatbot'); ?> <strong><?php esc_html_e('eMailed', 'chatbot'); ?></strong> <?php esc_html_e('to you and', 'chatbot'); ?>  <strong><?php esc_html_e('saved in the database', 'chatbot'); ?></strong>.
                        </p>
                        <h4><?php esc_html_e('Conversational Form Builder Free or Pro version works with the WPBot Free or Pro versions.', 'chatbot'); ?></h4>
                        <a class="FormBuilder" href="https://wordpress.org/plugins/conversational-forms/" target="_blank"><?php esc_html_e('Download Free Version', 'chatbot'); ?></a>
                        <a class="FormBuilder" href="https://www.quantumcloud.com/products/conversations-and-form-builder/" target="_blank"><?php esc_html_e('Grab the Pro version', 'chatbot'); ?></a>
                        <h4><?php esc_html_e('What Can You Do with it?', 'chatbot'); ?></h4>
                        <p><?php esc_html_e('Conversation Forms allows you to create a wide variety of forms, that might include:', 'chatbot'); ?></p>
                        <ul>
                           <li><?php esc_html_e('Create menu or button driven conversations', 'chatbot'); ?></li>
                           <li><?php esc_html_e('Conditional <strong>Menu Driven Conversations', 'chatbot'); ?></strong>
                              <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                           </li>
                           <li><?php esc_html_e('Standard Contact Forms', 'chatbot'); ?></li>
                           <li><?php esc_html_e('Dynamic,', 'chatbot'); ?> <strong><?php esc_html_e('conditional Forms', 'chatbot'); ?></strong> <?php esc_html_e('– where fields can change based on the user selections', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;">PRO</span>
                           </li>
                           <li>Job <strong><?php esc_html_e('Application Forms', 'chatbot'); ?></strong>
                           </li>
                           <li>
                              <strong><?php esc_html_e('Lead Capture', 'chatbot'); ?></strong> <?php esc_html_e('Forms', 'chatbot'); ?>
                           </li>
                           <li><?php esc_html_e('Various types of', 'chatbot'); ?> <strong><?php esc_html_e('Calculators', 'chatbot'); ?></strong>
                              <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                           </li>
                           <li><?php esc_html_e('Feedback', 'chatbot'); ?> <strong>Survey</strong><?php esc_html_e(' Forms etc.', 'chatbot'); ?> </li>
                        </ul>
                     </div>
                  </div>
               </div>
               <div class="panel panel-default">
                  <div class="panel-heading" role="tab" id="AIheadingThree">
                     <h4 class="panel-title">
                        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#AIcollapseThree" aria-expanded="false" aria-controls="AIcollapseThree"> <?php esc_html_e('DialogFlow ES and CX, OpenAI', 'chatbot'); ?> </a>
                     </h4>
                  </div>
                  <div id="AIcollapseThree" class="panel-collapse collapse" role="tabpanel" aria-labelledby="AIheadingThree">
                     <div class="panel-body">
                        <div class="section-container">
                           <div class="wpb_column vc_column_container vc_col-sm-6">
                              <div class="wpb_wrapper">
                                 <h3><?php esc_html_e('DialogFlow Essential', 'chatbot'); ?></h3>
                                 <?php esc_html_e('Intents created in Dialogflow give you the power to build a truly human like, intelligent and comprehensive chatbot. Build any type of Intents and Responses (including rich message responses) directly in DialogFlow and train the bot accordingly. When you create custom intents and responses in DialogFlow, WPBot will automatically display them when user inputs match with your Custom Intents along with the responses you created. You can also build Rich responses by enabling Facebook messenger Response option.', 'chatbot'); ?>
                                 <p><?php esc_html_e('In addition you can also Enable ', 'chatbot'); ?> <?php esc_html_e('Advanced Chained Question and Answers using follow up Intents, Contexts, Entities etc. and then have resulting answers from your users emailed to you. This feature lets you create a a series of questions in DialogFlow that will be asked by the bot and based on the user inputs a response will be displayed.', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;">PRO</span>
                                 </p>
                                 <p><?php esc_html_e('WPBot also supports Rich responses using Facebook Messenger integration. This allows you to display Image,', 'chatbot'); ?> Cards<?php esc_html_e(', Quick Text Reply or Custom PayLoad inside the ChatBot window. You can also insert an ', 'chatbot'); ?><?php esc_html_e('image', 'chatbot'); ?><?php esc_html_e(' or', 'chatbot'); ?> <?php esc_html_e('youtube video', 'chatbot'); ?><?php esc_html_e(' link inside the DialogFlow responses and they will be automatically rendered by the WPBot!', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                                 </p>
                                 <h3><?php esc_html_e('OpenAI', 'chatbot'); ?></h3>
                                 <?php esc_html_e('Connect the ChatBot to OpenAI. OpenAI’s API provides access to GPT-3, for a wide variety of natural language tasks. Train your ChatBot with (pre-trained) GPT-3 to answer any user questions using. Select your preferred Engine from DaVinci, Ada, Curie or Babbag! Add your own API key to the addon to connect to your OpenAI account. To go live, you need to apply to OpenAI.', 'chatbot'); ?>
                              </div>
                           </div>
                           <div class="wpb_column vc_column_container vc_col-sm-6">
                              <div class="wpb_wrapper">
                                 <h3><?php esc_html_e('DialogFlow CX', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                 </h3>
                                 <p><?php esc_html_e('WPBot supports', 'chatbot'); ?> <?php esc_html_e('visual workflow builder', 'chatbot'); ?><?php esc_html_e(' Dialogflow CX. It provides a new way of designing agents, taking a state machine approach to agent design. This gives you clear and explicit control over a conversation, a better end-user experience, and a better development', 'chatbot'); ?> <?php esc_html_e('workflow', 'chatbot'); ?>. </p>
                                 <ul>
                                    <li>
                                       <?php esc_html_e('Console visualization', 'chatbot'); ?><?php esc_html_e(': A new', 'chatbot'); ?> <?php esc_html_e('visual builder', 'chatbot'); ?> <?php esc_html_e('makes building and maintaining agents easier. Conversation paths are graphed as a state machine model, which makes conversations easier to design, enhance, and maintain.', 'chatbot'); ?>
                                    </li>
                                    <li>
                                       <?php esc_html_e('Intuitive and powerful conversation control', 'chatbot'); ?>: <?php esc_html_e('Conversation states and state transitions are first-class types that provide explicit and powerful control over conversation paths. You can clearly define a series of steps that you want the end-user to go through.', 'chatbot'); ?>
                                    </li>
                                    <li>
                                       <?php esc_html_e('Flows for agent partitions', 'chatbot'); ?>: <?php esc_html_e('With flows, you can partition your agent into smaller conversation topics. Different team members can own different flows, which makes large and complex agents easy to build.', 'chatbot'); ?>
                                    </li>
                                    <img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/dialogflow-cx-1024x676.jpg' );?>" alt="Dialogflow CX">
                                 </ul>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class=" wppt-settings-section" id="general_debugging" style="display:none;">
            <div class="content form-container qcbot_help_secion" >
               <div class="" >
                  <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Tips', 'chatbot'); ?></h3>
                  <h3><?php esc_html_e('Tutorial', 'chatbot'); ?></h3>
                  <p><?php esc_html_e('You will find some helpful video tutorials and the ChatBot workflow on this', 'chatbot'); ?> <a href="https://www.wpbot.pro/chatbot-workflow/" target="_blank">page</a>.</p>
                  <h3><?php esc_html_e('Simple Text Responses', 'chatbot'); ?></h3>
                  <p><?php esc_html_e('Create simple text responses easily for your chatbot. The ChatBot will use advanced search algorithm for natural language phrase matching with user input. You can also adjust the Phrase matching accuracy for better user experience.', 'chatbot'); ?></p>
                  <h3><?php esc_html_e('Setting Updates', 'chatbot'); ?></h3>
                  <p><?php esc_html_e('After making changes in the language center or settings, please type reset and hit enter in the ChatBot to start testing from the beginning or open a new Incognito window (Ctrl+Shit+N in chrome).', 'chatbot'); ?></p>
                  <h3><?php esc_html_e('Note', 'chatbot'); ?></h3>
                  <p><?php esc_html_e('You could use &lt;br&gt; tag in Language Center & Dialogflow Responses for line break.', 'chatbot'); ?></p>
               </div>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: I changed language and/or some settings but do not see the changes.', 'chatbot'); ?></h3>
               <p><?php esc_html_e('WPBot saves a lot of information in the browser`s local storage. After making any language or settings change you must clear browser cache and cookies both and reload the page for testing. An easier alternative is to always launch a new browser window in Incognito mode (Ctrl+Shift+N in chrome) and test there. Also, you need to purge cache plugin and CDN caching if you have any.', 'chatbot'); ?></p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: I cannot connect to the DialogFlow', 'chatbot'); ?></h3>
               <p><?php esc_html_e('To Debug: 1. Make sure that you have created the Google Project and the Service account as an Owner', 'chatbot'); ?><br>
                  <?php esc_html_e('2. Make sure that you have connected to the correct Dialogflow agent', 'chatbot'); ?><br>
                  <?php esc_html_e('3. Follow the steps in this tutorial correctly:', 'chatbot'); ?> <a href="https://www.wpbot.pro/dialogflow-integration" target="_blank">https://www.wpbot.pro/dialogflow-integration</a><br>
                  <?php esc_html_e(' 4. Make sure that the Google Client Package is Installed on Your Website.<br>
                     5. For DialogFlow agent region, try choosing any region other than the EU region which has known issues.<br>
                     6. Make sure to download and import the sample DialogFlow agent to your agent<br>
                     7. Test the ChatBot in the browser Incognito mode', 'chatbot'); ?>
               </p>
            </div>
            <div class="content form-container qcbot_help_secion" >
               <h3><?php esc_html_e('Problem: I am not getting emails from the ChatBot', 'chatbot'); ?></h3>
               <p>
                  <?php esc_html_e('The WPBot ChatBot uses the WordPress` default email function. If you are not getting emails from the ChatBot`s email feature, it is likely that no emails are getting through from your WordPress site or they are ending up in the Spam box. Try using an SMTP mailer plugin. Also, try changing the to and from email addresses in the ChatBot`s general settings area.', 'chatbot'); ?>
               </p>
            </div>
            <div class="content form-container qcbot_help_secion" >
               <h3><?php esc_html_e('Problem: Simple text responses are not working or getting an error', 'chatbot'); ?></h3>
               <p>
                  <?php esc_html_e('WPBot requires mysql version 5.6+ for the simple text responses to work. If your server has a version below that, you might see some PHP error or the Simple Text Responses will not work at all. Please request your hosting support to update the mysql version on your server.', 'chatbot'); ?>
               </p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: I changed language or some other settings but do not see them when testing', 'chatbot'); ?></h3>
               <p>
                  <?php esc_html_e('Please clear the browser cache and <strong>cookies</strong> to see any change you have made. Alternatively, you can open a fresh browser window in incognito mode (Ctrl+Shift+N in chrome) to test your changes. Also, you may need to purge any cache plugin and CDN caching.', 'chatbot'); ?>
               </p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: The ChatBot is NOT working in the front end.', 'chatbot'); ?></h3>
               <p>
                  <?php esc_html_e('The most common reason for this is if the theme is coded incorrectly and jQuery is loaded from external source. jQuery is included with WordPress core and according to WordPress standard, jQuery must be included using wp_enqueue_script.', 'chatbot'); ?> <a href="https://developer.wordpress.org/reference/functions/wp_enqueue_script/" target="_blank">https://developer.wordpress.org/reference/functions/wp_enqueue_script/</a> <?php esc_html_e('. Please make sure if that is the case in your theme.', 'chatbot'); ?><br>
                  <?php esc_html_e(' Also go to Simple Text Responses and press the Re-Index button.', 'chatbot'); ?></br>
                  <?php esc_html_e(' After that try purging any cache and test the chatbot in Incognito mode', 'chatbot'); ?><br>
                  <?php esc_html_e('  Please contact us if you need [further help]', 'chatbot'); ?>(<a href="https://www.wpbot.pro/free-support/" target="_blank">https://www.wpbot.pro/free-support/</a>). <?php esc_html_e('We take all user feedback sriously.', 'chatbot'); ?> 
               </p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: The ChatBot is stuck on typing or loading', 'chatbot'); ?></h3>
               <p>
                  <?php esc_html_e('This usually happens if you enabled DialogFlow but did not complete the set up. Please make sure that you have carefully followed all the steps for DialogFlow integration in the Settings->DialogFlow section.', 'chatbot'); ?><br>
                  <?php esc_html_e('This can also happen if there is any empty language fields or Simple Text Responses database needs updating because of mysql version changes. Try saving both the Language Center and Simple Text Responses and test again.', 'chatbot'); ?><br>
                  <?php esc_html_e('Also go to Simple Text Responses and press the Re-Index button.', 'chatbot'); ?></br>
                  <?php esc_html_e('After that remember to test in a browser Incognito mode to avoid cache and cookies.', 'chatbot'); ?> 
               </p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: How do I add new conversations to the ChatBot?', 'chatbot'); ?></h3>
               <p><?php esc_html_e('Please check the plugin`s Help Section for details on this', 'chatbot'); ?></p>
            </div>
            <div class="content form-container qcbot_help_secion" >
               <h3><?php esc_html_e('Problem: How do I add Line Breaks?', 'chatbot'); ?></h3>
               <p><?php esc_html_e('Please use the &lt;br&gt; tag for line breaks.', 'chatbot'); ?></p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: Are HTML tags supported?', 'chatbot'); ?> </h3>
               <p><?php esc_html_e('Yes, common HTML tags link link href, strong, br etc. are supported.', 'chatbot'); ?></p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('Problem: I want to add images, GIFs, Videos', 'chatbot'); ?></h3>
               <p><?php esc_html_e('Images, GIFs and Youtube Videos are supprted in the pro version. Pro version also includes a handy giphy floating search feature for easy embed in the language center.', 'chatbot'); ?></p>
            </div>
            <div class="content form-container qcbot_help_secion">
               <h3><?php esc_html_e('How to disable Predefined Intent?', 'chatbot'); ?></h3>
               <p><?php esc_html_e('You can disable predefined intents FAQ, eMail, Call me from WPBot Lite > Settings page`s Start Menu Section.', 'chatbot'); ?></p>
            </div>
         </div>
      </div>
   </div>
</div>
<script type="text/javascript">  
    jQuery(document).ready(function($){
        var url=document.URL;
        var arr=url.split('#');
        var tab_tar = '.'+arr[1];
        setTimeout(function(){
            jQuery(tab_tar).trigger('click');
        }, 500);
        jQuery('.wppt_nav_container .nav-tab').on('click', function(e){
            e.preventDefault();
            var section_id = jQuery(this).attr('href');
            jQuery('.wppt_nav_container .nav-tab').removeClass('nav-tab-active');
            jQuery(this).addClass('nav-tab-active');
            jQuery('.wppt-settings-section').hide();
            jQuery('.wppt-settings-section').each(function(){
                jQuery(section_id).show();
            });
        });
    })
</script>
<?php
}
}

add_action('init', 'qc_wp_latest_update_check');
if( !function_exists('qc_wp_latest_update_check') ){
function qc_wp_latest_update_check(){
	global $wpdb;
    if (current_user_can( 'manage_options' )) {
        if (isset($_POST['str_nonce'])) {
            $sanitized_nonce = sanitize_text_field(wp_unslash($_POST['str_nonce']));
            if (wp_verify_nonce($sanitized_nonce, 'str-nonce')) {
            
            if(!get_option('qc_wp_ludate_ck')){
                update_option('qlcd_wp_chatbot_support_phone', 'Leave your number. We will call you back!');
                update_option('qlcd_wp_chatbot_support_email', 'Send us Email');
                update_option('qlcd_wp_chatbot_wildcard_support', 'FAQ');
                update_option('qlcd_wp_chatbot_wildcard_site_search', 'Site Search');
                update_option('qc_wp_ludate_ck', 'done');
            }
            
            if( ! get_option( 'wpbot-admin-notice-oninstallation' ) ){
                update_option('wpbot-admin-notice-oninstallation', 'show');
            }
            
            if(!get_option('qc_wpb_simple_response_db_upgrade_free2')){
                
                $collate = '';
                if ( $wpdb->has_cap( 'collation' ) ) {
            
                    if ( ! empty( $wpdb->charset ) ) {
            
                        $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
                    }
                    if ( ! empty( $wpdb->collate ) ) {
            
                        $collate .= " COLLATE $wpdb->collate";
            
                    }
                }
                //Bot Response Table
                $table1    = $wpdb->prefix.'wpbot_response';
                $sql_sliders_Table1 = "
                    CREATE TABLE IF NOT EXISTS `$table1` (
                    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
                    `query`    TEXT NOT NULL,
                    `keyword`  TEXT NOT NULL,
                    `response` TEXT NOT NULL,
                    `category` varchar(256) NOT NULL,
                    `intent`   varchar(256) NOT NULL,
                    `custom`   varchar(256) NOT NULL,
                    `lang`	   varchar(25) NULL,
                    FULLTEXT(`query`, `keyword`, `response`)
                    )  $collate AUTO_INCREMENT=1 ENGINE=InnoDB";
                    
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql_sliders_Table1 );

                if(!get_option('qlcd_wp_chatbot_did_you_mean')) {
                    update_option('qlcd_wp_chatbot_did_you_mean', maybe_serialize(array('Did you mean?')));
                }

                if(!get_option('qlcd_wp_chatbot_did_you_mean')) {
                    update_option('qlcd_wp_chatbot_did_you_mean', maybe_serialize(array('Did you mean?')));
                }

                $sqlqry = $wpdb->get_results( $wpdb->prepare( "select * from $table1 where id = %d", 1 ) ); //DB Call OK, No Caching OK
                if(empty($sqlqry)){
                
                    $query = 'What Can WPBot do for you?';
                    $response = 'WPBot can converse fluidly with users on website and FB messenger. It can search your website, send/collect eMails, user feedback & phone numbers . You can create Custom Intents from DialogFlow with Rich Messages & Card responses!';

                    $data = array('query' => $query, 'keyword' => '', 'response'=> $response, 'intent'=> '');
                    $format = array('%s','%s', '%s', '%s');
                    $wpdb->insert($table1,$data,$format); //DB Call OK, No Caching OK
                }
                
                update_option('qc_wpb_simple_response_db_upgrade_free2', 'done');

            }
            
            if(!get_option('qc_wp_db_engine_update_free')){

                $table1    = $wpdb->prefix.'wpbot_response';

                // phpcs:ignore
                $wpdb->query("ALTER TABLE $table1 ENGINE = InnoDB");
                
                update_option('qc_wp_db_engine_update_free', 'done');
            }
            if(!get_option('qc_wp_db_engine_update_free_unassign')){

                $table1    = $wpdb->prefix.'wpbot_response';

                // phpcs:ignore
                $wpdb->query("ALTER TABLE $table1 CHANGE `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;");

                update_option('qc_wp_db_engine_update_free_unassign', 'done');
            }
            
            if(isset($_POST['qc_bot_str_query']) && $_POST['qc_bot_str_query']!='' && !class_exists('Qcld_str_pro')){
                
                
                if ( ! isset( $_POST['str_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['str_nonce'] ) ), 'str-nonce' ) ) {
                    die( esc_html__( 'Security check failed', 'chatbot' ) ); 
                } 
                $query = sanitize_text_field(wp_unslash($_POST['qc_bot_str_query']));
                $keyword = sanitize_text_field(wp_unslash($_POST['qc_bot_str_keyword']));
                $intent = sanitize_text_field(wp_unslash($_POST['qc_bot_str_intent']));
                
                $category = '';
                
                $response = wp_kses(wp_unslash($_POST['qc_bot_str_response']), 'post');
                
                $table = $wpdb->prefix.'wpbot_response';
                $data = array('query' => $query, 'keyword' => $keyword, 'response'=> $response, 'intent'=> $intent, 'category'=> $category);
                $format = array('%s','%s', '%s', '%s', '%s');
                
                if(isset($_POST['qc_bot_str_id']) && $_POST['qc_bot_str_id']!=''){
                    $id = sanitize_text_field($_POST['qc_bot_str_id']);
                    $where = array('id'=>$id);
                    $whereformat = array('%d');
                    $wpdb->update( $table, $data, $where, $format, $whereformat ); //DB Call OK, No Caching OK
                }else{
                    $wpdb->insert($table,$data,$format); //DB Call OK, No Caching OK
                }

                qcld_mysql_remove_existing_indexes();

                // phpcs:ignore
                $wpdb->query("ALTER TABLE $table ADD FULLTEXT(`query`, `keyword`, `response`)");
                
                wp_redirect(admin_url('admin.php?page=simple-text-response'));exit;
                
            }
            $table = $wpdb->prefix.'wpbot_response';
            
            if(isset($_POST['qc-re-index'])){

                qcld_mysql_remove_existing_indexes();

                // phpcs:ignore
                $wpdb->query("ALTER TABLE $table ADD FULLTEXT(`query`, `keyword`)");

                add_action('admin_notices', 'general_admin_notice_str' );

            }
            if(isset($_POST['qc_bot_str_weight']) && $_POST['qc_bot_str_weight']!=''){
                $weight = sanitize_text_field($_POST['qc_bot_str_weight']);
                update_option('qc_bot_str_weight', $weight);
            }
            if(isset($_POST['qc_bot_str_remove_stopwords']) && $_POST['qc_bot_str_remove_stopwords']!=''){
                
                $stopwords = sanitize_text_field($_POST['qc_bot_str_remove_stopwords']);
                update_option('qc_bot_str_remove_stopwords', '1');
            }
            if(isset($_POST['qc_bot_str_fields']) && !empty($_POST['qc_bot_str_fields'])){
                $table = $wpdb->prefix.'wpbot_response';
                $fields = rest_sanitize_array($_POST['qc_bot_str_fields']);
                update_option('qc_bot_str_fields', $fields);
                qcld_mysql_remove_existing_indexes();
                
                if($fields && !empty($fields)){

                    // phpcs:ignore
                    $wpdb->query("ALTER TABLE $table ADD FULLTEXT(".implode(', ', $fields).")");

                }
            }
            
            if(!get_option('wpbot_preloading_time')) {
                update_option('wpbot_preloading_time', '800');
            }
        }
    }
}
}
if( !function_exists('general_admin_notice_str') ){
    function general_admin_notice_str(){
    	if ( isset($_GET['page']) && $_GET['page'] == 'simple-text-response' ) {
    		 echo '<div class="notice notice-success is-dismissible">
    			 <p>Re-Indexing has been completed!</p>
    		 </div>';
    	}
    }
}
}
if( !function_exists('qcld_wpbot_simple_response_intent') ){
    function qcld_wpbot_simple_response_intent(){
        global $wpdb;
        $table = $wpdb->prefix.'wpbot_response';
        $results = $wpdb->get_results("SELECT `intent` FROM `$table` WHERE 1 and `intent` !=''"); //DB Call OK, No Caching OK
        $response = array();
        if(!empty($results)){
            foreach($results as $result){
                $response[] = $result->intent;
            }
        }
        return $response;
    }
}
if( !function_exists('qcld_mysql_remove_existing_indexes') ){
    function qcld_mysql_remove_existing_indexes(){
        global $wpdb;
        $table = $wpdb->prefix.'wpbot_response';
        
        $results = $wpdb->get_results("SHOW INDEX FROM $table"); //DB Call OK, No Caching OK
        $indexes = array();
        foreach($results as $result){
            
            
            
            if("PRIMARY" != $result->Key_name && !in_array($result->Key_name, $indexes)){

                // phpcs:ignore
                $wpdb->query("ALTER TABLE $table DROP INDEX `".$result->Key_name."`;");
                
                $indexes[] = $result->Key_name;
            }
            
        }
    }
}

add_action( 'activated_plugin', 'qc_wpbotfree_activation_redirect' );
if( !function_exists('qc_wpbotfree_activation_redirect') ){
    function qc_wpbotfree_activation_redirect( $plugin ){
        $screen = get_current_screen();
        if( ( isset( $screen->base ) && $screen->base == 'plugins' ) && $plugin == plugin_basename( __FILE__ ) ) {
            if( $plugin == plugin_basename( __FILE__ ) ) {
                // phpcs:ignore
                exit( wp_redirect( esc_url( admin_url('admin.php?page=wpbot') ) ) );
            }
        }
    }
}


