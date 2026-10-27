<?php
/**
 * WPBot Free adapter for the Insights module: menu placement, branding that
 * matches WPBot's own admin pages, and the AI keys to copy from WPBot's AI Settings.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Insights host adapter for WPBot Free.
 */
class QCLD_WPBot_Insights_Adapter extends \QuantumCloud\Insights\Host\Abstract_Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'wpbot';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'WPBot';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_capability() {
		return 'manage_options';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_parent() {
		// WPBot's top-level menu (qcld-wpwbot.php). 'wpbot-panel' is only the "ChatBot" item inside it.
		return 'wpbot_openAi';
	}

	/**
	 * Menu label in the WPBot menu.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return __( 'Site Insight', 'chatbot' );
	}

	/**
	 * Sit just above Automator. When the Automator add-on is not loaded,
	 * fall back to the items that follow it so the position stays the same.
	 *
	 * @return string[]
	 */
	public function get_menu_before() {
		return array( 'wpbot-automator', 'wbcs-botsessions-page', 'simple-text-response' );
	}

	/**
	 * Keys and models saved in WPBot > AI Settings, copied once into Site
	 * Insight's own AI settings. "active" is the provider WPBot uses (the
	 * first enabled one, in the order WPBot checks them).
	 *
	 * @return array<string,mixed>
	 */
	public function get_ai_import() {
		$openai_model = get_option( 'qcld_openai_custom_model' );
		$providers    = array(
			'openai'     => array( 'ai_enabled', 'open_ai_api_key', $openai_model ? $openai_model : get_option( 'openai_engines' ) ),
			'openrouter' => array( 'qcld_openrouter_enabled', 'qcld_openrouter_api_key', get_option( 'qcld_openrouter_model' ) ),
			'gemini'     => array( 'qcld_gemini_enabled', 'qcld_gemini_api_key', get_option( 'qcld_gemini_model' ) ),
			'grok'       => array( 'qcld_grok_enabled', 'qcld_grok_api_key', get_option( 'qcld_grok_model' ) ),
			'claude'     => array( 'qcld_claude_enabled', 'qcld_claude_api_key', get_option( 'qcld_claude_model' ) ),
		);
		$out = array(
			'active'    => '',
			'providers' => array(),
		);
		foreach ( $providers as $id => $p ) {
			$key = trim( (string) get_option( $p[1] ) );
			if ( '' === $key ) {
				continue;
			}
			$out['providers'][ $id ] = array(
				'key'   => $key,
				'model' => is_string( $p[2] ) ? trim( $p[2] ) : '',
			);
			if ( '' === $out['active'] && 1 === (int) get_option( $p[0] ) ) {
				$out['active'] = $id;
			}
		}
		return $out;
	}

	/**
	 * WPBot admin skin tokens (css/admin-style.css, --woobot-* variables).
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return array(
			'primary'        => '#5B4E96',
			'primary_dark'   => '#463a7a',
			'primary_soft'   => '#f3f1fa',
			'primary_border' => '#e4e0f2',
			'page_bg'        => '#F5F7FD',
			'surface'        => '#ffffff',
			'text'           => '#2d2a3a',
			'muted'          => '#6b6680',
			'danger'         => '#e25563',
			'success'        => '#22B573',
			'radius'         => '16px',
		);
	}

	/**
	 * Name in the email report subject and heading ("[Site] WPBot Site Insight
	 * weekly report"), the credit line at the bottom linked to wpbot.pro, and
	 * one line about the WPBot Pro report (left out when Site Insight Pro is active).
	 *
	 * @return array<string,string>
	 */
	public function get_report_branding() {
		$utm      = 'utm_source=wpbot-free&utm_medium=email-report&utm_campaign=';
		$branding = array(
			'report_name'  => __( 'WPBot Site Insight', 'chatbot' ),
			'credit_text'  => __( 'Sent by Site Insight in WPBot', 'chatbot' ),
			'credit_url'   => 'https://www.wpbot.pro/?' . $utm . 'credit',
			'credit_label' => 'wpbot.pro',
		);
		$pro_active = class_exists( 'QCLD_WPBot_Insights_Pro_Preview' ) && ! QCLD_WPBot_Insights_Pro_Preview::enabled();
		if ( ! $pro_active ) {
			$branding['promo_text'] = __( 'Get an AI summary, top issues and a CSV of pages in every report with WPBot Pro.', 'chatbot' );
			$branding['promo_url']  = 'https://www.wpbot.pro/?' . $utm . 'report-upgrade';
		}
		return $branding;
	}

	/**
	 * Same stylesheet (and handle) WPBot's settings pages use, for the header.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_admin_styles() {
		if ( ! defined( 'QCLD_wpCHATBOT_PLUGIN_URL' ) ) {
			return array();
		}
		return array(
			array(
				'handle' => 'qlcd-wp-chatbot-admin-style',
				'src'    => QCLD_wpCHATBOT_PLUGIN_URL . 'css/admin-style.css',
				'ver'    => defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : null,
			),
		);
	}

	/**
	 * WPBot Control Panel header, same markup as WPBot's other admin pages.
	 */
	public function render_admin_header() {
		$logo    = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? QCLD_wpCHATBOT_IMG_URL . 'chatbot.png' : '';
		$version = defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '';
		?>
		<div class="qcld-wp-chatbot-wrap-header qcin-host-header">
			<div class="qcld-wp-chatbot-wrap-header-logo">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot-panel' ) ); ?>" class="qcld-wp-chatbot-wrap-site__logo">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="">
					<?php endif; ?>
					<?php esc_html_e( 'WPBot Control Panel', 'chatbot' ); ?>
				</a>
				<?php if ( $version ) : ?>
					<p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( $version ); ?></p>
				<?php endif; ?>
			</div>
			<ul class="qcld-wp-chatbot-wrap-version-wrapper">
				<li><a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_url() {
		return admin_url( 'admin.php?page=wpbot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ai_settings_url() {
		return admin_url( 'admin.php?page=wpbot_openAi' );
	}
}
