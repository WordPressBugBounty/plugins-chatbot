<?php
/**
 * WPBot AI Marketing in WPBot Free: loads the bundled core module
 * (includes/ai-marketing/) on top of WPBot Core and registers WPBot Free as
 * its host, in "essentials" mode (owner, 19 Nov 2026: Free, Starter and
 * Professional get the Essentials; docs/ai-marketing/PLAN.md in botmaster).
 *
 * The folder is a synced copy of https://github.com/qcld/wpbot-ai-marketing
 * (npm run sync:ai-marketing -- --core <sha>); never edit it here. The
 * Master module never ships in WPBot Free.
 *
 * Loaded from qcld-wpwbot.php after the "stop when WPBot Pro is active"
 * block, so it never runs next to WPBot Pro.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_free_marketing_deactivate' ) ) {
	/**
	 * WPBot Free deactivated: remove AI Marketing cron events (new module and
	 * the old one's leftovers). Data is kept.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_marketing_deactivate() {
		$hooks = array();
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'qcld_wpbot_marketing' ) || 0 === strpos( (string) $hook, 'wpbot_crm/' ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/wpbot-core/wpbot-core.php' ) || ! file_exists( __DIR__ . '/ai-marketing/ai-marketing.php' ) ) {
	return;
}

// The loaders inside pick the newest bundled copy of each module.
require_once __DIR__ . '/wpbot-core/wpbot-core.php';
require_once __DIR__ . '/ai-marketing/ai-marketing.php';

if ( ! function_exists( 'qcld_wpbot_free_core_register' ) ) {
	/**
	 * Register WPBot Free as the WPBot Core host (shared with AI Writer,
	 * SEO & AI Search and Social; whichever loader runs first defines it).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_core_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		\QuantumCloud\WPBotCore\Core::instance()->register_host( QCLD_WPBot_Free_Core_Adapter::instance() );
	}
	add_action( 'qcld_wpbot_core/loaded', 'qcld_wpbot_free_core_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_marketing_register' ) ) {
	/**
	 * Register WPBot Free as the AI Marketing host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_marketing_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		require_once __DIR__ . '/class-qcld-wpbot-marketing-lead-source.php';
		require_once __DIR__ . '/class-qcld-wpbot-free-marketing-adapter.php';
		\QuantumCloud\AiMarketing\Module::instance()->register_host( new QCLD_WPBot_Free_Marketing_Adapter() );
	}
	add_action( 'qcld_wpbot_marketing/loaded', 'qcld_wpbot_free_marketing_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_marketing_aside' ) ) {
	/**
	 * The Upgrade To Pro card next to AI Marketing, as on WPBot Free's other
	 * screens (banner and the Marketing Pro lists).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_marketing_aside() {
		?>
		<div class="wp-chatbot-admin-upgrade-pro-sidebar qcld-wpbot-free-mk-aside">
			<div class="wp-chatbot-admingradient-color">
				<img class="wp-chatbot-admin-banner" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/template-sample.webp' ); ?>" alt="">
				<h3 class="wp-chatbot-admincart-title"><?php esc_html_e( 'Upgrade To', 'chatbot' ); ?> <span><?php esc_html_e( 'Pro', 'chatbot' ); ?></span></h3>
				<?php
				if ( function_exists( 'qcld_wpbot_free_pro_lists' ) ) {
					qcld_wpbot_free_pro_lists( 'marketing_pro', array( 'compact' => true ) );
				}
				?>
			</div>
		</div>
		<?php
	}
	add_action( 'qcld_wpbot_marketing/admin_aside', 'qcld_wpbot_free_marketing_aside' );
}
