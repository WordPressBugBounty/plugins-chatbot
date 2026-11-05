<?php
/**
 * WPBot Free adapter for WPBot Core (the shared services bundled in
 * includes/wpbot-core/): menu parent, WPBot branding and header, and the AI
 * keys to copy once from WPBot > AI Settings.
 *
 * Shared by every module WPBot Free bundles on top of WPBot Core (SEO & AI
 * Search today; AI Writer can reuse it). Knowledge and chat sources are not
 * offered yet: no module in WPBot Free needs them.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QCLD_WPBot_Free_Core_Adapter' ) ) {
	return;
}

/**
 * WPBot Core host adapter for WPBot Free.
 */
class QCLD_WPBot_Free_Core_Adapter extends \QuantumCloud\WPBotCore\Host\Abstract_Host_Adapter {

	/**
	 * Host id, recorded by every bundled module for uninstall.
	 */
	const ID = 'wpbot-free';

	/**
	 * Shared instance (module adapters reuse its branding).
	 *
	 * @var QCLD_WPBot_Free_Core_Adapter|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return QCLD_WPBot_Free_Core_Adapter
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Host id.
	 *
	 * @return string
	 */
	public function get_id() {
		return self::ID;
	}

	/**
	 * Host name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'WPBot';
	}

	/**
	 * WPBot's top-level menu ("wpbot-panel" is only the ChatBot item inside it).
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return 'wpbot_openAi';
	}

	/**
	 * WPBot admin colours (css/admin-style.css: #5b4e96, page #F5F7FD, 16px cards).
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return array(
			'primary'      => '#5B4E96',
			'primary_dark' => '#463a7a',
			'primary_soft' => '#f3f1fa',
			'border'       => '#e4e0f2',
			'background'   => '#F5F7FD',
			'text'         => '#2d2a3a',
			'muted'        => '#6b6680',
			'danger'       => '#e25563',
			'success'      => '#22B573',
			'radius'       => '16px',
		);
	}

	/**
	 * WPBot's own admin stylesheet (same handle its other pages use) and the
	 * header card layout, for every screen of the bundled modules.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return array(
			array(
				'handle' => 'qlcd-wp-chatbot-admin-style',
				'src'    => QCLD_wpCHATBOT_PLUGIN_URL . 'css/admin-style.css',
				'ver'    => QCLD_wpCHATBOT_VERSION,
			),
			array(
				'handle' => 'qcld-wpbot-free-host',
				'src'    => QCLD_wpCHATBOT_PLUGIN_URL . 'css/wpbot-free-host.css',
				'ver'    => QCLD_wpCHATBOT_VERSION,
			),
		);
	}

	/**
	 * Header card with the same markup as WPBot's other admin pages.
	 *
	 * @param string $title Optional sub-title after "WPBot Control Panel".
	 * @return void
	 */
	public function render_admin_header( $title = '' ) {
		?>
		<div class="qcld-wp-chatbot-wrap-header qcld-wpbot-free-host-header">
			<div class="qcld-wp-chatbot-wrap-header-logo">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot-panel' ) ); ?>" class="qcld-wp-chatbot-wrap-site__logo">
					<img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . 'chatbot.png' ); ?>" alt="">
					<?php esc_html_e( 'WPBot Control Panel', 'chatbot' ); ?>
				</a>
				<?php if ( '' !== (string) $title ) : ?>
					<span class="qcld-wpbot-free-host-header__sub"><?php echo esc_html( $title ); ?></span>
				<?php endif; ?>
				<p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
			</div>
			<ul class="qcld-wp-chatbot-wrap-version-wrapper">
				<li><a class="wpchatbot-Upgrade" href="<?php echo esc_url( 'https://www.wpbot.pro/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Keys and models saved in WPBot > AI Settings, copied once into each
	 * module's own key set when that set is empty. WPBot turns on one
	 * provider at a time; that one is "active", the others are copied off.
	 *
	 * @return array
	 */
	public function get_ai_import() {
		$openai_model = (string) get_option( 'qcld_openai_custom_model', '' );
		// Module provider id => enabled option, key option, model.
		$providers = array(
			'openai'     => array( 'ai_enabled', 'open_ai_api_key', '' !== $openai_model ? $openai_model : (string) get_option( 'openai_engines', '' ) ),
			'openrouter' => array( 'qcld_openrouter_enabled', 'qcld_openrouter_api_key', (string) get_option( 'qcld_openrouter_model', '' ) ),
			'gemini'     => array( 'qcld_gemini_enabled', 'qcld_gemini_api_key', (string) get_option( 'qcld_gemini_model', '' ) ),
			'grok'       => array( 'qcld_grok_enabled', 'qcld_grok_api_key', (string) get_option( 'qcld_grok_model', '' ) ),
			'claude'     => array( 'qcld_claude_enabled', 'qcld_claude_api_key', (string) get_option( 'qcld_claude_model', '' ) ),
		);
		$out       = array(
			'active'    => '',
			'providers' => array(),
		);
		foreach ( $providers as $id => $provider ) {
			$key = trim( (string) get_option( $provider[1], '' ) );
			if ( '' === $key ) {
				continue;
			}
			$enabled                 = self::is_on( get_option( $provider[0] ) );
			$out['providers'][ $id ] = array(
				'key'     => $key,
				'model'   => trim( $provider[2] ),
				'enabled' => $enabled,
			);
			if ( '' === $out['active'] && $enabled ) {
				$out['active'] = $id;
			}
		}
		return $out;
	}

	/**
	 * WPBot > AI Settings.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return admin_url( 'admin.php?page=wpbot_openAi' );
	}

	/**
	 * WPBot saves switches as 1, '1', 'on', 'yes' or true.
	 *
	 * @param mixed $value Option value.
	 * @return bool
	 */
	public static function is_on( $value ) {
		return in_array( is_string( $value ) ? strtolower( trim( $value ) ) : $value, array( 1, '1', 'on', 'yes', 'true', true ), true );
	}
}
