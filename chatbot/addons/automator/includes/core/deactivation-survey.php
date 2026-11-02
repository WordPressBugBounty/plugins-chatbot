<?php
/**
 * Deactivation Survey Class for WPbot Automator
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation Survey Handler
 */
class Deactivation_Survey {

	/**
	 * Main plugin file path
	 *
	 * @var string
	 */
	private $plugin_file = '';

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $plugin_name = 'wpbot-automator';

	/**
	 * Destination email for feedback
	 *
	 * @var string
	 */
	private $home_url = 'plugins@quantumcloud.net';

	/**
	 * Class constructor
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		//error_log( 'WPbot Automator - Deactivation_Survey initialized with file: ' . $this->plugin_file );

		// Deactivation hook.
		register_deactivation_hook( $this->plugin_file, array( $this, 'handle_deactivation' ) );

		$this->init();
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		global $pagenow;
		$is_admin = is_admin() ? 'Yes' : 'No';
		//error_log( 'WPbot Automator - Deactivation_Survey init. pagenow: ' . $pagenow . ', is_admin: ' . $is_admin );
		
		add_filter( 'plugin_action_links', array( $this, 'filter_action_links' ), 20, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'filter_action_links' ), 20, 2 );
		add_action( 'admin_footer', array( $this, 'render_form' ), 20 );
		add_action( 'wp_ajax_wpbot_automator_goodbye_form', array( $this, 'ajax_callback' ) );
	}

	/**
	 * Set email content type to HTML
	 *
	 * @return string
	 */
	public function set_content_type() {
		return 'text/html';
	}

	/**
	 * Send deactivation feedback via email
	 *
	 * @param array $body Feedback data.
	 * @return bool
	 */
	private function send_feedback( $body ) {
		$message = '';
		foreach ( $body as $key => $value ) {
			$message .= '<p><b>' . esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ) . '</b>: ' . esc_html( $value ) . '</p>';
		}

		$title   = 'WPbot Automator - Plugin Deactivation Notice';
		$headers = array( 'From: Anonymous <mailer@quantumcloud.net>' );

		add_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ) );
		$email = wp_mail( $this->home_url, $title, $message, $headers );
		remove_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ) );

		return $email;
	}

	/**
	 * Get plugin data
	 *
	 * @return array
	 */
	private function get_plugin_info() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugin_data( $this->plugin_file );
	}

	/**
	 * Handle plugin deactivation
	 */
	public function handle_deactivation() {
		$plugin_data = $this->get_plugin_info();
		$body        = array(
			'plugin_name'      => $plugin_data['Name'],
			'version'          => $plugin_data['Version'],
			'status'           => 'Deactivated',
			'date'             => date( 'Y-m-d H:i:s' ),
			'site_url'         => get_site_url(),
			'reason'           => get_option( 'wpbot_automator_deactivation_reason' ),
			'feedback_details' => get_option( 'wpbot_automator_deactivation_details' ),
		);

		if ( ! empty( $body['reason'] ) || ! empty( $body['feedback_details'] ) ) {
			$this->send_feedback( $body );
			delete_option( 'wpbot_automator_deactivation_reason' );
			delete_option( 'wpbot_automator_deactivation_details' );
		}
	}

	/**
	 * Add deactivation prompt to plugin action links
	 *
	 * @param array $links Action links.
	 * @param string $file Plugin file slug.
	 * @return array
	 */
	public function filter_action_links( $links, $file ) {
		$my_plugin = plugin_basename( $this->plugin_file );
		
		// error_log( 'WPbot Automator - filter_action_links: Checking ' . $file );

		if ( $file === $my_plugin && isset( $links['deactivate'] ) ) {
			//error_log( 'WPbot Automator - filter_action_links: MATCH FOUND for ' . $file );
			$links['deactivate'] = str_replace(
				'<a ',
				'<a onclick="javascript:event.preventDefault();" id="wpbot-automator-deactivate-link" ',
				$links['deactivate']
			);
		}
		return $links;
	}

	/**
	 * Render the deactivation survey markup and script
	 */
	public function render_form() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		//error_log( 'WPbot Automator - render_form hitting plugins page footer' );
		
		$heading = __( 'Sorry to see you go', 'wpbot-automator' );
		$label   = __( 'Please provide some details so we can improve the plugin', 'wpbot-automator' );
		$submit  = __( 'Submit & Deactivate', 'wpbot-automator' );
		$skip    = __( 'Just Deactivate', 'wpbot-automator' );
		?>
		<div class="wpbot-goodbye-form-bg"></div>
		<div id="wpbot-automator-goodbye-form"></div>
		<style type="text/css">
			.wpbot-goodbye-active .wpbot-goodbye-form-bg {
				background: rgba(0, 0, 0, .5);
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 9998;
			}
			#wpbot-automator-goodbye-form { display: none; }
			.wpbot-goodbye-active #wpbot-automator-goodbye-form {
				display: block;
				position: fixed;
				max-width: 450px;
				width: 90%;
				background: #fff;
				z-index: 9999;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				border-radius: 12px;
				box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				overflow: hidden;
			}
			.wpbot-goodbye-head {
				background: #4f46e5;
				color: #fff;
				padding: 20px;
				text-align: center;
				font-size: 1.25rem;
				font-weight: 700;
			}
			.wpbot-goodbye-body { padding: 25px; }
			.wpbot-goodbye-body label {
				display: block;
				margin-bottom: 10px;
				font-weight: 500;
				color: #1e293b;
			}
			.wpbot-goodbye-body textarea {
				width: 100%;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				padding: 12px;
				font-size: 14px;
				margin-bottom: 5px;
			}
			.wpbot-goodbye-footer {
				padding: 20px;
				border-top: 1px solid #f1f5f9;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.wpbot-submit-btn {
				background: #4f46e5;
				color: #fff;
				border: none;
				padding: 10px 20px;
				border-radius: 8px;
				font-weight: 600;
				cursor: pointer;
				text-decoration: none;
			}
			.wpbot-submit-btn:hover { background: #4338ca; }
			.wpbot-skip-btn {
				color: #64748b;
				text-decoration: none;
				font-size: 14px;
			}
			.wpbot-skip-btn:hover { color: #4f46e5; }
			.wpbot-spinner { display: none; text-align: center; padding: 20px; }
		</style>
		<script>
			jQuery(document).ready(function($){
				$("#wpbot-automator-deactivate-link").on("click", function(e){
					e.preventDefault();
					var url = $(this).attr('href');
					$('body').addClass('wpbot-goodbye-active');
					
					$("#wpbot-automator-goodbye-form").html(`
						<div class="wpbot-goodbye-head"><?php echo esc_html( $heading ); ?></div>
						<div class="wpbot-goodbye-body">
							<label><?php echo esc_html( $label ); ?></label>
							<textarea id="wpbot-feedback-details" rows="4"></textarea>
							<div id="wpbot-survey-error" style="color:#ef4444; font-size: 13px; margin-top: 5px;"></div>
						</div>
						<div class="wpbot-spinner"><span class="spinner is-active"></span> Submitting...</div>
						<div class="wpbot-goodbye-footer">
							<a class="wpbot-skip-btn" href="${url}"><?php echo esc_html( $skip ); ?></a>
							<button id="wpbot-submit-survey" class="wpbot-submit-btn"><?php echo esc_html( $submit ); ?></button>
						</div>
					`);

					$("#wpbot-submit-survey").on("click", function(){
						var details = $("#wpbot-feedback-details").val();
						if(!details) {
							$("#wpbot-survey-error").text("Please share a quick note to help us improve!");
							return;
						}

						$(".wpbot-goodbye-body, .wpbot-goodbye-footer").hide();
						$(".wpbot-spinner").show();

						$.post(ajaxurl, {
							action: 'wpbot_automator_goodbye_form',
							details: details,
							nonce: '<?php echo wp_create_nonce( "wpbot_automator_survey" ); ?>'
						}, function() {
							window.location.href = url;
						});
					});

					$(".wpbot-goodbye-form-bg").on("click", function(){
						$("body").removeClass("wpbot-goodbye-active");
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * AJAX logic for saving survey feedback
	 */
	public function ajax_callback() {
		check_ajax_referer( 'wpbot_automator_survey', 'nonce' );
		if ( isset( $_POST['details'] ) ) {
			update_option( 'wpbot_automator_deactivation_reason', 'Feedback provided' );
			update_option( 'wpbot_automator_deactivation_details', sanitize_text_field( $_POST['details'] ) );
		}
		wp_send_json_success();
	}
}
