<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! class_exists( 'Qcld_Wp_Usage_Feedback') ) {
	
	class Qcld_Wp_Usage_Feedback {
		
		private $wpbot_version = '1.0.0';
		private $home_url = '';
		private $plugin_file = '';
		private $plugin_name = '';
		private $options = array();
		private $require_optin = true;
		private $include_goodbye_form = true;

		
		/**
		 * Class constructor
		 *
		 * @param $_home_url				The URL to the site we're sending data to
		 * @param $_plugin_file				The file path for this plugin
		 * @param $_options					Plugin options to track
		 * @param $_require_optin			Whether user opt-in is required (always required on WordPress.org)
		 * @param $_include_goodbye_form	Whether to include a form when the user deactivates
		 * @param $_marketing				Marketing method:
		 *									0: Don't collect email addresses
		 *									1: Request permission same time as tracking opt-in
		 *									2: Request permission after opt-in
		 */
		public function __construct( 
			$_plugin_file,
			$_home_url,
			
			$_require_optin=true,
			$_include_goodbye_form=true) {

			$this->plugin_file = $_plugin_file;
			$this->home_url = 'plugins@quantumcloud.net';
			$this->plugin_name = basename( $this->plugin_file, '.php' );

			$this->require_optin = $_require_optin;
			$this->include_goodbye_form = $_include_goodbye_form;


			// Deactivation hook
			register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate_this_plugin' ) );
			
			// Get it going
			$this->init();
			
		}
		
		public function init() {
			
			// Deactivation
			add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'filter_action_links' ) );
			add_action( 'admin_footer-plugins.php', array( $this, 'goodbye_ajax' ) );
			add_action( 'wp_ajax_qcld_goodbye_form', array( $this, 'qcld_goodbye_form_callback' ) );
			
			
		}

		// In theme's functions.php or plug-in code:

		function set_content_type(){
			return "text/html";
		}
		
		
		/**
		 * Send the data to the home site
		 *
		 * @since 1.0.0
		 */
		public function send_data( $body ) {
			$message = '';
			foreach($body as $key=>$value){
				
				if($key=='active_plugins'){
					$message .='<p> <b>'.$key.'</b>: '.(implode(', ',$value)).' </p>';
				}
				elseif($key=='inactive_plugins'){
					$message .='<p> <b>'.$key.'</b>: '.(implode(', ',$value)).' </p>';
				}else{
					$message .='<p> <b>'.$key.'</b>: '.$value.' </p>';
				}
				
			}
			
			    $title   = 'Plugin Deactivation Notice';
				$headers = array('From: Anonymous <mailer@just-a-fake-from-address.com>');
				
				add_filter( 'wp_mail_content_type', array($this, 'set_content_type') );
				$email = wp_mail($this->home_url, $title, $message, $headers);
				remove_filter('wp_mail_content_type', array($this, 'set_content_type'));

				return $email;

		}
		
		/**
		 * Here we collect most of the data
		 * 
		 * @since 1.0.0
		 */
		public function get_data() {
	
			// Use this to pass error messages back if necessary
			$body['message'] = '';
	
			// Use this array to send data back
			$body = array();


	
			/**
			 * Get our plugin data
			 * Currently we grab plugin name and version
			 * Or, return a message if the plugin data is not available
			 * @since 1.0.0
			 */
			$plugin = $this->plugin_data();
			if( empty( $plugin ) ) {
				// We can't find the plugin data
				// Send a message back to our home site
				$body['message'] .= __( 'We can\'t detect any plugin information. This is most probably because you have not included the code in the plugin main file.', 'chatbot' );
				$body['status'] = 'Data not found'; // Never translated
			} else {
				if( isset( $plugin['Name'] ) ) {
					$body['plugin'] = sanitize_text_field( $plugin['Name'] );
				}
				if( isset( $plugin['Version'] ) ) {
					$body['version'] = sanitize_text_field( $plugin['Version'] );
				}

			}

			// Return the data
			return $body;
	
		}
		
		/**
		 * Return plugin data
		 * @since 1.0.0
		 */
		public function plugin_data() {
			// Being cautious here
			if( ! function_exists( 'get_plugin_data' ) ) {
				include ABSPATH . '/wp-admin/includes/plugin.php';
			}
			// Retrieve current plugin information
			$plugin = get_plugin_data( $this->plugin_file );
			return $plugin;
		}

		/**
		 * Deactivating plugin
		 * @since 1.0.0
		 */
		public function deactivate_this_plugin() {

			$body = $this->get_data();
			$body['status'] = 'Deactivated'; // Never translated
			$body['deactivated_date'] = gmdate( 'Y-m-d' );
			
			// Add deactivation form data
			if( false !== get_option( 'wpbot_deactivation_reason_' . $this->plugin_name ) ) {
				$body['deactivation_reason'] = get_option( 'wpbot_deactivation_reason_' . $this->plugin_name );
				delete_option('wpbot_deactivation_reason_' . $this->plugin_name);
			}
			if( false !== get_option( 'wpbot_deactivation_details_' . $this->plugin_name ) ) {
				$body['deactivation_details'] = get_option( 'wpbot_deactivation_details_' . $this->plugin_name );
				delete_option('wpbot_deactivation_details_' . $this->plugin_name);
			}
			if( false !== get_option( 'wpbot_deactivation_email_' . $this->plugin_name ) ) {
				$body['deactivation_email'] = get_option( 'wpbot_deactivation_email_' . $this->plugin_name );
				delete_option('wpbot_deactivation_email_' . $this->plugin_name);
			}
			
			if(isset($body['deactivation_reason']) or isset($body['deactivation_details']))
				$this->send_data( $body );
			

		}
		
		/**
		 * Filter the deactivation link to allow us to present a form when the user deactivates the plugin
		 * @since 1.0.0
		 */
		public function filter_action_links( $links ) {

			if( isset( $links['deactivate'] ) && $this->include_goodbye_form ) {
				$deactivation_link = $links['deactivate'];
				// Insert an onClick action to allow form before deactivating
				$deactivation_link = str_replace( '<a ', '<div class="wpb-goodbye-form-wrapper"><span class="wpb-goodbye-form" id="wpb-goodbye-form-' . esc_attr( $this->plugin_name ) . '"></span></div><a onclick="javascript:event.preventDefault();" id="wpb-goodbye-link-' . esc_attr( $this->plugin_name ) . '" ', $deactivation_link );
				$links['deactivate'] = $deactivation_link;
			}
			return $links;
		}
		
		/*
		 * Form text strings
		 * These are non-filterable and used as fallback in case filtered strings aren't set correctly
		 * @since 1.0.0
		 */
		public function form_default_text() {
			$form = array();
			$form['heading'] = __( 'Quick Feedback', 'chatbot' );
			$form['body']    = __( 'If you have a moment, please let us know why you are deactivating WPBot so we can improve:', 'chatbot' );
			$form['options'] = array(
				'found_bug' => array(
					'label'       => __( 'I encountered a bug or technical issue', 'chatbot' ),
					'placeholder' => __( 'Please describe what went wrong or which error occurred...', 'chatbot' ),
					'has_details' => true,
				),
				'setup_difficulty' => array(
					'label'       => __( 'Could not get it to work / Setup was difficult', 'chatbot' ),
					'placeholder' => __( 'What was confusing or didn\'t work as expected?', 'chatbot' ),
					'has_details' => true,
				),
				'missing_feature' => array(
					'label'       => __( 'Missing a specific feature or AI integration', 'chatbot' ),
					'placeholder' => __( 'Which feature, LLM model, or capability are you looking for?', 'chatbot' ),
					'has_details' => true,
				),
				'found_better' => array(
					'label'       => __( 'Found a better or alternative plugin', 'chatbot' ),
					'placeholder' => __( 'Which plugin did you choose and what did you like better?', 'chatbot' ),
					'has_details' => true,
				),
				'temporary' => array(
					'label'       => __( 'Temporary deactivation / Troubleshooting', 'chatbot' ),
					'placeholder' => '',
					'has_details' => false,
				),
				'upgraded_pro' => array(
					'label'       => __( 'Upgraded to WPBot Pro', 'chatbot' ),
					'placeholder' => '',
					'has_details' => false,
				),
				'other' => array(
					'label'       => __( 'Other reason', 'chatbot' ),
					'placeholder' => __( 'Please tell us what made you decide to deactivate...', 'chatbot' ),
					'has_details' => true,
				),
			);
			$form['email']   = __( 'Email Address (Optional)', 'chatbot' );
			$form['details'] = __( 'Please share a few details so we can improve', 'chatbot' );
			return $form;
		}
		
		/**
		 * Form text strings
		 * These can be filtered
		 * The filter hook must be unique to the plugin
		 * @since 1.0.0
		 */
		public function form_filterable_text() {
			$form = $this->form_default_text();
			return apply_filters( 'wpbot_form_text_' . esc_attr( $this->plugin_name ), $form );
		}
		
		/**
		 * Render goodbye form modal and scripts
		 * @since 1.0.0
		 */
		public function goodbye_ajax() {
			// Get our strings for the form
			$form = $this->form_filterable_text();
			if( ! isset( $form['heading'] ) || ! isset( $form['options'] ) || ! is_array( $form['options'] ) ) {
				$form = $this->form_default_text();
			}

			// Normalize options list
			$options_data = array();
			foreach( $form['options'] as $key => $option ) {
				if( is_array( $option ) ) {
					$opt_id = is_string( $key ) ? $key : sanitize_key( $option['label'] ?? '' );
					$options_data[] = array(
						'id'          => $opt_id,
						'label'       => $option['label'] ?? $opt_id,
						'placeholder' => $option['placeholder'] ?? '',
						'has_details' => ! empty( $option['has_details'] ),
					);
				} else {
					$opt_id = sanitize_key( $option );
					$has_det = ( 'Deactivating Temporarily' !== $option && 'Upgrading to Pro' !== $option && 'temporary' !== $opt_id && 'upgraded_pro' !== $opt_id );
					$options_data[] = array(
						'id'          => $opt_id,
						'label'       => $option,
						'placeholder' => __( 'Please share any details to help us improve...', 'chatbot' ),
						'has_details' => $has_det,
					);
				}
			}

			$plugin_id_attr = esc_attr( $this->plugin_name );
			?>
			<div class="wpb-goodbye-form-bg" id="wpb-goodbye-bg-<?php echo $plugin_id_attr; ?>"></div>
			<div id="wpb-goodbye-template-<?php echo $plugin_id_attr; ?>" style="display: none;">
				<div class="wpb-goodbye-modal-card">
					<div class="wpb-goodbye-form-head">
						<div class="wpb-goodbye-head-title-wrap">
							<div class="wpb-goodbye-icon-badge">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"></path>
									<rect x="3" y="8" width="18" height="12" rx="4"></rect>
									<line x1="8" y1="13" x2="8.01" y2="13" stroke-width="2.5"></line>
									<line x1="16" y1="13" x2="16.01" y2="13" stroke-width="2.5"></line>
									<line x1="9" y1="17" x2="15" y2="17"></line>
								</svg>
							</div>
							<div class="wpb-goodbye-head-text">
								<h3 class="wpb-goodbye-title"><?php echo esc_html( $form['heading'] ); ?></h3>
								<p class="wpb-goodbye-subtitle"><?php echo esc_html( $form['body'] ); ?></p>
							</div>
						</div>
						<button type="button" class="wpb-goodbye-close-btn" aria-label="<?php esc_attr_e( 'Close', 'chatbot' ); ?>">&times;</button>
					</div>

					<div class="wpb-goodbye-form-body">
						<div class="wpb-goodbye-options-list">
							<?php foreach ( $options_data as $opt ) : ?>
								<label class="wpb-goodbye-option-item" data-id="<?php echo esc_attr( $opt['id'] ); ?>" data-has-details="<?php echo $opt['has_details'] ? '1' : '0'; ?>" data-placeholder="<?php echo esc_attr( $opt['placeholder'] ); ?>">
									<input type="radio" name="wpb-goodbye-option-choice" value="<?php echo esc_attr( $opt['label'] ); ?>" data-id="<?php echo esc_attr( $opt['id'] ); ?>" />
									<span class="wpb-goodbye-radio-circle"></span>
									<span class="wpb-goodbye-option-label"><?php echo esc_html( $opt['label'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="wpb-goodbye-notice-temp" style="display: none;">
							<div class="wpb-goodbye-notice-inner">
								<span class="dashicons dashicons-saved"></span>
								<span><?php esc_html_e( 'No worries! All your chatbot settings, forms, and responses will remain safe for when you return.', 'chatbot' ); ?></span>
							</div>
						</div>

						<div class="wpb-goodbye-notice-pro" style="display: none;">
							<div class="wpb-goodbye-notice-inner">
								<span class="dashicons dashicons-star-filled"></span>
								<span><?php esc_html_e( 'Awesome! Thank you for upgrading to WPBot Pro. Enjoy the advanced AI models and premium features!', 'chatbot' ); ?></span>
							</div>
						</div>

						<div class="wpb-goodbye-details-section">
							<div class="wpb-goodbye-field-group">
								<label for="wpb-goodbye-reasons-input" class="wpb-goodbye-field-label">
									<span class="wpb-goodbye-label-text"><?php echo esc_html( $form['details'] ?? __( 'Please share a few details', 'chatbot' ) ); ?></span>
									<span class="wpb-goodbye-badge-optional"><?php esc_html_e( 'Optional', 'chatbot' ); ?></span>
								</label>
								<textarea name="wpb-goodbye-reasons" id="wpb-goodbye-reasons-input" rows="3" class="wpb-goodbye-textarea" placeholder="<?php esc_attr_e( 'Please share any details to help us improve...', 'chatbot' ); ?>"></textarea>
							</div>

							<div class="wpb-goodbye-field-group">
								<label for="wpb-goodbye-email-input" class="wpb-goodbye-field-label">
									<span><?php echo esc_html( $form['email'] ?? __( 'Email Address', 'chatbot' ) ); ?></span>
									<span class="wpb-goodbye-badge-optional"><?php esc_html_e( 'Optional', 'chatbot' ); ?></span>
								</label>
								<div class="wpb-goodbye-input-wrap">
									<span class="dashicons dashicons-email-alt wpb-goodbye-input-icon"></span>
									<input type="email" name="wpb-goodbye-email" id="wpb-goodbye-email-input" class="wpb-goodbye-input" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" placeholder="email@example.com" />
								</div>
							</div>
							<div id="wpbot_deactivation_error"></div>
						</div>
					</div>

					<div class="wpb-goodbye-loading-spinner" style="display: none;">
						<div class="wpb-spinner-ring"></div>
						<p class="wpb-spinner-text"><?php esc_html_e( 'Submitting feedback and deactivating...', 'chatbot' ); ?></p>
					</div>

					<div class="wpb-goodbye-form-footer">
						<a class="wpbot_just_deactivate" href="#">
							<?php esc_html_e( 'Skip & Deactivate', 'chatbot' ); ?>
						</a>
						<div class="wpb-goodbye-footer-actions">
							<button type="button" class="wpb-goodbye-btn-cancel">
								<?php esc_html_e( 'Cancel', 'chatbot' ); ?>
							</button>
							<button type="button" id="wpb-submit-form" class="wpbot_submit_deactivate">
								<span><?php esc_html_e( 'Submit & Deactivate', 'chatbot' ); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div>

			<style type="text/css">
				:root,
				.wpb-goodbye-form {
					--woobot-primary: #5B4E96;
					--woobot-primary-color: #5B4E96;
					--woobot-primary-dark: #463a7a;
					--woobot-primary-soft: #f3f1fa;
					--woobot-primary-border: #e4e0f2;
					--woobot-primary-rgb: 91, 78, 150;
				}

				/* Background Backdrop */
				.wpb-goodbye-form-bg {
					position: fixed;
					top: 0;
					left: 0;
					width: 100vw;
					height: 100vh;
					background: rgba(15, 23, 42, 0.65);
					backdrop-filter: blur(4px);
					-webkit-backdrop-filter: blur(4px);
					z-index: 999998;
					display: none;
					opacity: 0;
					transition: opacity 0.2s ease-in-out;
				}
				body.wpb-form-active .wpb-goodbye-form-bg {
					display: block;
					opacity: 1;
				}

				/* Form Wrapper / Container */
				.wpb-goodbye-form-wrapper {
					position: relative;
					z-index: 999999;
					display: inline;
				}

				/* Modal Dialog Box */
				.wpb-goodbye-form {
					display: none;
					position: fixed;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%) scale(0.96);
					width: 92%;
					max-width: 530px;
					max-height: 90vh;
					background: #ffffff;
					border-radius: 16px;
					box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(15, 23, 42, 0.08);
					z-index: 999999;
					box-sizing: border-box;
					opacity: 0;
					transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					overflow: hidden;
				}
				body.wpb-form-active .wpb-goodbye-form {
					display: block;
					opacity: 1;
					transform: translate(-50%, -50%) scale(1);
				}

				.wpb-goodbye-modal-card {
					display: flex;
					flex-direction: column;
					max-height: 90vh;
					box-sizing: border-box;
				}

				/* Header */
				.wpb-goodbye-form-head {
					display: flex;
					align-items: flex-start;
					justify-content: space-between;
					padding: 22px 24px 18px;
					background: #ffffff;
					border-bottom: 1px solid #f1f5f9;
					position: relative;
				}
				.wpb-goodbye-head-title-wrap {
					display: flex;
					align-items: flex-start;
					gap: 14px;
				}
				.wpb-goodbye-icon-badge {
					width: 44px;
					height: 44px;
					background: var(--woobot-primary);
					border: 1px solid var(--woobot-primary);
					border-radius: 12px;
					color: #ffffff;
					display: flex;
					align-items: center;
					justify-content: center;
					box-shadow: 0 6px 16px rgba(var(--woobot-primary-rgb), 0.3);
					flex-shrink: 0;
				}
				.wpb-goodbye-head-text {
					display: flex;
					flex-direction: column;
					gap: 4px;
				}
				.wpb-goodbye-title {
					margin: 0;
					font-size: 18px;
					font-weight: 700;
					color: #0f172a;
					line-height: 1.3;
					letter-spacing: -0.01em;
				}
				.wpb-goodbye-subtitle {
					margin: 0;
					font-size: 13.5px;
					color: #64748b;
					line-height: 1.45;
				}
				.wpb-goodbye-close-btn {
					background: transparent;
					border: none;
					color: #94a3b8;
					font-size: 26px;
					line-height: 1;
					cursor: pointer;
					padding: 4px;
					border-radius: 8px;
					transition: all 0.15s ease;
					width: 32px;
					height: 32px;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				.wpb-goodbye-close-btn:hover {
					background: #f1f5f9;
					color: #334155;
				}

				/* Body & Scroll Area */
				.wpb-goodbye-form-body {
					padding: 20px 24px;
					overflow-y: auto;
					flex: 1 1 auto;
					box-sizing: border-box;
				}
				.wpb-goodbye-options-list {
					display: flex;
					flex-direction: column;
					gap: 8px;
					margin-bottom: 16px;
				}
				.wpb-goodbye-option-item {
					display: flex;
					align-items: center;
					gap: 12px;
					padding: 10px 14px;
					border: 1.5px solid #e2e8f0;
					border-radius: 10px;
					background: #ffffff;
					cursor: pointer;
					transition: all 0.15s ease;
					user-select: none;
					position: relative;
				}
				.wpb-goodbye-option-item:hover {
					border-color: var(--woobot-primary);
					background: var(--woobot-primary-soft);
				}
				.wpb-goodbye-option-item:hover:not(.is-selected) .wpb-goodbye-option-label {
					color: var(--woobot-primary);
				}
				.wpb-goodbye-option-item:hover:not(.is-selected) .wpb-goodbye-radio-circle {
					border-color: var(--woobot-primary);
				}
				.wpb-goodbye-option-item.is-selected {
					background: var(--woobot-primary);
					border: 1px solid var(--woobot-primary);
					color: #ffffff;
					box-shadow: 0 4px 12px rgba(var(--woobot-primary-rgb), 0.25);
				}
				.wpb-goodbye-option-item input[type="radio"] {
					display: none;
				}
				.wpb-goodbye-radio-circle {
					width: 18px;
					height: 18px;
					border-radius: 50%;
					border: 2px solid #cbd5e1;
					background: #ffffff;
					display: inline-block;
					position: relative;
					flex-shrink: 0;
					transition: all 0.15s ease;
				}
				.wpb-goodbye-option-item.is-selected .wpb-goodbye-radio-circle {
					border-color: #ffffff;
					background: #ffffff;
				}
				.wpb-goodbye-option-item.is-selected .wpb-goodbye-radio-circle::after {
					content: "";
					position: absolute;
					top: 50%;
					left: 50%;
					width: 6px;
					height: 6px;
					border-radius: 50%;
					background: var(--woobot-primary);
					transform: translate(-50%, -50%);
				}
				.wpb-goodbye-option-label {
					font-size: 13.5px;
					font-weight: 500;
					color: #1e293b;
					line-height: 1.35;
				}
				.wpb-goodbye-option-item.is-selected .wpb-goodbye-option-label {
					font-weight: 600;
					color: #ffffff;
				}

				/* Informative Notices */
				.wpb-goodbye-notice-temp,
				.wpb-goodbye-notice-pro {
					margin-bottom: 16px;
					border-radius: 10px;
					padding: 12px 14px;
					font-size: 13px;
					line-height: 1.45;
					animation: wpb_fadeIn 0.2s ease;
				}
				.wpb-goodbye-notice-temp {
					background: var(--woobot-primary-soft);
					border: 1px solid var(--woobot-primary-border);
					color: var(--woobot-primary-dark);
				}
				.wpb-goodbye-notice-pro {
					background: #f0fdf4;
					border: 1px solid #bbf7d0;
					color: #15803d;
				}
				.wpb-goodbye-notice-inner {
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.wpb-goodbye-notice-inner .dashicons {
					font-size: 18px;
					width: 18px;
					height: 18px;
					flex-shrink: 0;
				}

				/* Form Fields */
				.wpb-goodbye-details-section {
					animation: wpb_fadeIn 0.2s ease;
				}
				.wpb-goodbye-field-group {
					margin-bottom: 14px;
				}
				.wpb-goodbye-field-label {
					display: flex;
					align-items: center;
					justify-content: space-between;
					margin-bottom: 6px;
					font-size: 13px;
					font-weight: 600;
					color: #334155;
				}
				.wpb-goodbye-badge-optional {
					font-size: 11.5px;
					font-weight: 400;
					color: #94a3b8;
				}
				.wpb-goodbye-textarea {
					width: 100%;
					border: 1.5px solid #cbd5e1;
					border-radius: 8px;
					padding: 10px 12px;
					font-size: 13.5px;
					color: #1e293b;
					background: #ffffff;
					resize: vertical;
					min-height: 70px;
					box-sizing: border-box;
					transition: all 0.15s ease;
					font-family: inherit;
				}
				.wpb-goodbye-textarea:focus,
				.wpb-goodbye-input:focus {
					outline: none;
					border-color: var(--woobot-primary);
					box-shadow: 0 0 0 3px rgba(var(--woobot-primary-rgb), 0.18);
				}
				.wpb-goodbye-input-wrap {
					position: relative;
					display: flex;
					align-items: center;
				}
				.wpb-goodbye-input-icon {
					position: absolute;
					left: 10px;
					color: #94a3b8;
					font-size: 17px;
					pointer-events: none;
				}
				.wpb-goodbye-input {
					width: 100%;
					border: 1.5px solid #cbd5e1;
					border-radius: 8px;
					padding: 9px 12px 9px 34px;
					font-size: 13.5px;
					color: #1e293b;
					background: #ffffff;
					box-sizing: border-box;
					transition: all 0.15s ease;
					font-family: inherit;
				}

				/* Validation Error Alert */
				#wpbot_deactivation_error {
					display: none;
					margin-top: 10px;
					padding: 10px 12px;
					background: #fef2f2;
					border: 1px solid #fecaca;
					border-radius: 8px;
					color: #b91c1c;
					font-size: 13px;
					font-weight: 500;
					line-height: 1.4;
				}

				/* Footer */
				.wpb-goodbye-form-footer {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
					padding: 16px 24px;
					border-top: 1px solid #f1f5f9;
					background: #f8fafc;
					border-radius: 0 0 16px 16px;
				}
				.wpbot_just_deactivate {
					color: #64748b;
					text-decoration: none;
					font-size: 13px;
					font-weight: 500;
					transition: color 0.15s ease;
				}
				.wpbot_just_deactivate:hover {
					color: var(--woobot-primary);
					text-decoration: underline;
				}
				.wpb-goodbye-footer-actions {
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.wpb-goodbye-btn-cancel {
					background: #ffffff;
					border: 1px solid #cbd5e1;
					color: #475569;
					padding: 8px 14px;
					border-radius: 8px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					transition: all 0.15s ease;
				}
				.wpb-goodbye-btn-cancel:hover {
					background: #f1f5f9;
					color: #1e293b;
					border-color: #94a3b8;
				}
				.wpbot_submit_deactivate {
					background: var(--woobot-primary);
					border: 1px solid var(--woobot-primary);
					color: #ffffff;
					padding: 8px 20px;
					border-radius: 8px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					box-shadow: 0 2px 6px rgba(var(--woobot-primary-rgb), 0.25);
					transition: all 0.18s ease;
					text-decoration: none;
					display: inline-flex;
					align-items: center;
					justify-content: center;
				}
				.wpbot_submit_deactivate:hover,
				.wpbot_submit_deactivate:focus {
					background: var(--woobot-primary-dark);
					border-color: var(--woobot-primary-dark);
					box-shadow: 0 4px 10px rgba(var(--woobot-primary-rgb), 0.35);
					color: #ffffff;
				}

				/* Loading State */
				.wpb-goodbye-loading-spinner {
					padding: 36px 20px;
					text-align: center;
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					gap: 14px;
				}
				.wpb-spinner-ring {
					width: 38px;
					height: 38px;
					border: 3px solid #e2e8f0;
					border-top-color: var(--woobot-primary);
					border-radius: 50%;
					animation: wpb_spin 0.8s linear infinite;
				}
				.wpb-spinner-text {
					margin: 0;
					font-size: 14px;
					font-weight: 500;
					color: #475569;
				}

				@keyframes wpb_spin {
					to { transform: rotate(360deg); }
				}
				@keyframes wpb_fadeIn {
					from { opacity: 0; transform: translateY(-4px); }
					to { opacity: 1; transform: translateY(0); }
				}
			</style>

			<script type="text/javascript">
				jQuery(document).ready(function($){
					var pluginName = "<?php echo $plugin_id_attr; ?>";
					var $modal = $("#wpb-goodbye-form-" + pluginName);
					var $template = $("#wpb-goodbye-template-" + pluginName);
					var $backdrop = $("#wpb-goodbye-bg-" + pluginName);
					var deactivationUrl = "";

					// Move modal to body to avoid table row containment/clipping
					if ($modal.length && !$modal.parent().is("body")) {
						$modal.appendTo("body");
					}
					if ($backdrop.length && !$backdrop.parent().is("body")) {
						$backdrop.appendTo("body");
					}

					// Intercept deactivation link click
					$(document).on("click", "#wpb-goodbye-link-" + pluginName, function(e){
						e.preventDefault();
						deactivationUrl = $(this).attr("href");

						// Populate modal content from template
						if ($modal.is(":empty") && $template.length) {
							$modal.html($template.html());
						}

						// Set deactivation URL for direct skip link
						$modal.find(".wpbot_just_deactivate").attr("href", deactivationUrl);

						// Show modal
						$("body").addClass("wpb-form-active");
						$modal.show();
					});

					// Radio selection & contextual feedback fields
					$(document).on("click", "#wpb-goodbye-form-" + pluginName + " .wpb-goodbye-option-item", function(){
						var $item = $(this);
						var $radio = $item.find("input[type=radio]");
						var optId = $item.data("id");
						var hasDetails = $item.data("has-details");
						var placeholder = $item.data("placeholder");

						// Toggle selected visual state
						$modal.find(".wpb-goodbye-option-item").removeClass("is-selected");
						$item.addClass("is-selected");
						$radio.prop("checked", true);

						// Clear any previous error
						$modal.find("#wpbot_deactivation_error").hide().text("");

						// Handle notices vs details section
						if (optId === "temporary") {
							$modal.find(".wpb-goodbye-notice-temp").slideDown(150);
							$modal.find(".wpb-goodbye-notice-pro").hide();
							$modal.find(".wpb-goodbye-details-section").slideUp(150);
						} else if (optId === "upgraded_pro") {
							$modal.find(".wpb-goodbye-notice-pro").slideDown(150);
							$modal.find(".wpb-goodbye-notice-temp").hide();
							$modal.find(".wpb-goodbye-details-section").slideUp(150);
						} else {
							$modal.find(".wpb-goodbye-notice-temp").hide();
							$modal.find(".wpb-goodbye-notice-pro").hide();
							$modal.find(".wpb-goodbye-details-section").slideDown(150);

							if (placeholder) {
								$modal.find("#wpb-goodbye-reasons-input").attr("placeholder", placeholder);
							}
						}
					});

					// Close / Dismiss modal
					function closeModal() {
						$("body").removeClass("wpb-form-active");
						$modal.hide();
					}

					$(document).on("click", "#wpb-goodbye-form-" + pluginName + " .wpb-goodbye-close-btn, #wpb-goodbye-form-" + pluginName + " .wpb-goodbye-btn-cancel", function(e){
						e.preventDefault();
						closeModal();
					});

					$backdrop.on("click", function(){
						closeModal();
					});

					// Escape key dismiss
					$(document).on("keydown", function(e){
						if (e.key === "Escape" && $("body").hasClass("wpb-form-active")) {
							closeModal();
						}
					});

					// Submit and Deactivate
					$(document).on("click", "#wpb-goodbye-form-" + pluginName + " #wpb-submit-form", function(e){
						e.preventDefault();

						var selectedOption = $modal.find("input[name=wpb-goodbye-option-choice]:checked").val() || "";
						var details = $modal.find("#wpb-goodbye-reasons-input").val() || "";
						var email = $modal.find("#wpb-goodbye-email-input").val() || "";

						if (!selectedOption && !details) {
							$modal.find("#wpbot_deactivation_error")
								.text("<?php echo esc_js( __( 'Please select an option or click Skip & Deactivate.', 'chatbot' ) ); ?>")
								.fadeIn(150);
							return;
						}

						// Switch to spinner state
						$modal.find(".wpb-goodbye-form-body, .wpb-goodbye-form-footer").hide();
						$modal.find(".wpb-goodbye-loading-spinner").fadeIn(150);

						var postData = {
							"action": "qcld_goodbye_form",
							"values": selectedOption,
							"details": details,
							"email": email,
							"security": "<?php echo sanitize_key( wp_create_nonce( 'wpbot_goodbye_form' ) ); ?>"
						};

						$.ajax({
							url: ajaxurl,
							type: "POST",
							data: postData,
							timeout: 5000
						}).always(function(){
							// Always redirect to deactivation URL so user is never stuck
							window.location.href = deactivationUrl;
						});
					});
				});
			</script>
		<?php }
		
		/**
		 * AJAX callback when the form is submitted
		 * @since 1.0.0
		 */
		public function qcld_goodbye_form_callback() {
			check_ajax_referer( 'wpbot_goodbye_form', 'security' );
		
			if( isset( $_POST['values'] ) ) {
				$values = is_array( $_POST['values'] ) ? implode( ', ', array_map( 'sanitize_text_field', wp_unslash( $_POST['values'] ) ) ) : sanitize_text_field( wp_unslash( $_POST['values'] ) );
				update_option( 'wpbot_deactivation_reason_' . $this->plugin_name, $values );
			}

			if( isset( $_POST['details'] ) ) {
				$details = sanitize_text_field( wp_unslash( $_POST['details'] ) );
				update_option( 'wpbot_deactivation_details_' . $this->plugin_name, $details );
			}

			if( isset( $_POST['email'] ) ) {
				$email = sanitize_email( wp_unslash( $_POST['email'] ) );
				update_option( 'wpbot_deactivation_email_' . $this->plugin_name, $email );
			}

			echo 'success';
			wp_die();
		}
		
	}
	
}
