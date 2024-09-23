<?php
/*
* QuantumCloud Promo + Support Page
* Revised On: 18-10-2023
*/

if ( ! defined( 'qcld_wpbot_free_support_path' ) ) {
    define('qcld_wpbot_free_support_path', plugin_dir_path(__FILE__));
}

if ( ! defined( 'qcld_wpbot_free_support_url' ) )
    define('qcld_wpbot_free_support_url', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'qcld_support_img_url' ) )
    define('qcld_support_img_url', qcld_wpbot_free_support_url . "/images" );


/*Callback function to add the menu */
// function qcld_wpbot_free_show_promo_page_callback_func(){

//     add_submenu_page(
//         "comment-link-remove",
//         esc_html__('More WordPress Goodies for You!'),
//         esc_html__('Support'),
//         'manage_options',
//         "qcclr_comment_supports",
//         'qcld_wpbot_free_promo_support_page_callback_func'
//     );
    
// } //show_promo_page_callback_func

// add_action( 'admin_menu', 'qcld_wpbot_free_show_promo_page_callback_func', 110 );


/*******************************
 * Main Class to Display Support
 * form and the promo pages
 *******************************/

if ( ! function_exists( 'qcld_wpbot_free_include_promo_page_scripts' ) ) {	
	function qcld_wpbot_free_include_promo_page_scripts( ) {   


        if( isset($_GET["page"]) && !empty($_GET["page"]) && (   $_GET["page"] == "wpbot_support_page"  ) ){

            wp_enqueue_style( 'qcld-support-fontawesome-css', qcld_wpbot_free_support_url . "css/font-awesome.min.css");                              
            wp_enqueue_style( 'qcld-support-style-css', qcld_wpbot_free_support_url . "css/style.css");

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-core');
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'jquery-custom-form-processor', qcld_wpbot_free_support_url . 'js/support-form-script.js',  array('jquery', 'jquery-ui-core','jquery-ui-tabs') );

            wp_add_inline_script( 'jquery-custom-form-processor', 
                                    'var qcld_wpbot_free_ajaxurl    = "' . admin_url('admin-ajax.php') . '";
                                    var qcld_wpbot_free_ajax_nonce  = "'. wp_create_nonce( 'qc-clr' ).'";   
                                ', 'before');
            
        }
	   
	}
	add_action('admin_enqueue_scripts', 'qcld_wpbot_free_include_promo_page_scripts');
	
}
		
/*******************************
 * Callback function to show the HTML
 *******************************/

include_once qcld_wpbot_free_support_path . '/qc-clr-recommendbot-support-plugin.php';

if ( ! function_exists( 'qcpromo_wpbot_free_support_page_callback_func' ) ) {

	function qcpromo_wpbot_free_support_page_callback_func() {
		
?>


        <div class="qc-wpbot-free-support qcld-support-new-page">
            <div class="support-btn-main justify-content-center">
                <div class="col text-center">
                    <h2 class="py-3"><?php esc_html_e('Check Out Some of Our Other Works that Might Make Your Website Better', 'qc-clr'); ?></h2>
                    <h5><?php esc_html_e('All our Pro Version users get Premium, Guaranteed Quick, One on One Priority Support.', 'qc-clr'); ?></h5>
                    <div class="support-btn">
                        <a class="premium-support" href="<?php echo esc_url('https://qc.ticksy.com/'); ?>" target="_blank"><?php esc_html_e('Get Priority Support ', 'qc-clr'); ?></a>
                        <a style="width:282px" class="premium-support" href="<?php echo esc_url('https://www.quantumcloud.com/resources/kb-sections/comment-tools/'); ?>" target="_blank"><?php esc_html_e('Online KnowledgeBase', 'qc-clr'); ?></a>
                    </div>
                </div>
            
                <div class="qc-column-12" >
                    <div class="support-btn">
                        
                        <a class="premium-support premium-support-free" href="<?php echo esc_url('https://www.wpbot.pro/free-support/','qc-clr') ?>" target="_blank"><?php esc_html_e('Get Support for Free Version','qc-clr') ?></a>
                    </div>
                </div>
            </div>
            
            <div class="qcld-plugins-lists">
                <div class="qcld-plugins-loading">
                    <img src="<?php echo esc_url(qcld_support_img_url); ?>/loading.gif" alt="loading">
                </div>
            </div>
        </div>
			
		
<?php
            
       
    }
}


/*******************************
 * Handle Ajex Request for Form Processing
 *******************************/
add_action( 'wp_ajax_qcld_wpbot_free_process_qc_promo_form', 'qcld_wpbot_free_process_qc_promo_form' );

if( !function_exists('qcld_wpbot_free_process_qc_promo_form') ){

    function qcld_wpbot_free_process_qc_promo_form(){

        check_ajax_referer( 'qc-clr', 'security');
        
        $data['status']   = 'failed';
        $data['message']  = esc_html__('Problem in processing your form submission request! Apologies for the inconveniences.<br> 
Please email to <span style="color:#22A0C9;font-weight:bold !important;font-size:14px "> quantumcloud@gmail.com </span> with any feedback. We will get back to you right away!', 'qc-clr');

        $name         = isset($_POST['post_name']) ? trim(sanitize_text_field($_POST['post_name'])) : '';
        $email        = isset($_POST['post_email']) ? trim(sanitize_email($_POST['post_email'])) : '';
        $subject      = isset($_POST['post_subject']) ? trim(sanitize_text_field($_POST['post_subject'])) : '';
        $message      = isset($_POST['post_message']) ? trim(sanitize_text_field($_POST['post_message'])) : '';
        $plugin_name  = isset($_POST['post_plugin_name']) ? trim(sanitize_text_field($_POST['post_plugin_name'])) : '';

        if( $name == "" || $email == "" || $subject == "" || $message == "" )
        {
            $data['message'] = esc_html('Please fill up all the requried form fields.', 'qc-clr');
        }
        else if ( filter_var($email, FILTER_VALIDATE_EMAIL) === false ) 
        {
            $data['message'] = esc_html('Invalid email address.', 'qc-clr');
        }
        else
        {

            //build email body

            $bodyContent = "";
                
            $bodyContent .= "<p><strong>".esc_html('Support Request Details:', 'qc-clr')."</strong></p><hr>";

            $bodyContent .= "<p>".esc_html('Name', 'qc-clr')." : ".$name."</p>";
            $bodyContent .= "<p>".esc_html('Email', 'qc-clr')." : ".$email."</p>";
            $bodyContent .= "<p>".esc_html('Subject', 'qc-clr')." : ".$subject."</p>";
            $bodyContent .= "<p>".esc_html('Message', 'qc-clr')." : ".$message."</p>";

            $bodyContent .= "<p>".esc_html('Sent Via the Plugin', 'qc-clr')." : ".$plugin_name."</p>";

            $bodyContent .="<p></p><p>".esc_html('Mail sent from:', 'qc-clr')." <strong>".get_bloginfo('name')."</strong>, ".esc_html('URL:', 'qc-clr')." [".get_bloginfo('url')."].</p>";
            $bodyContent .="<p>".esc_html('Mail Generated on:', 'qc-clr')." " . date("F j, Y, g:i a") . "</p>";           
            
            $toEmail = "quantumcloud@gmail.com"; //Receivers email address
            //$toEmail = "qc.kadir@gmail.com"; //Receivers email address

            //Extract Domain
            $url = get_site_url();
            $url = parse_url($url);
            $domain = $url['host'];
            

            $fakeFromEmailAddress = "wordpress@" . $domain;
            
            $to = $toEmail;
            $body = $bodyContent;
            $headers = array();
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: '.esc_attr($name, 'qc-clr').' <'.esc_attr($fakeFromEmailAddress, 'qc-clr').'>';
            $headers[] = 'Reply-To: '.esc_attr($name, 'qc-clr').' <'.esc_attr($email, 'qc-clr').'>';

            $finalSubject = esc_html('From Plugin Support Page:', 'qc-clr')." " . esc_attr($subject, 'qc-clr');
            
            $result = wp_mail( $to, $finalSubject, $body, $headers );

            if( $result )
            {
                $data['status'] = 'success';
                $data['message'] = esc_html__('Your email was sent successfully. Thanks!', 'qc-clr');
            }

        }

        ob_clean();

        
        echo json_encode($data);
    
        die();
    }
}