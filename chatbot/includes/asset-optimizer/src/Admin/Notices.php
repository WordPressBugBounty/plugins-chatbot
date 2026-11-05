<?php
/**
 * Admin notices: unwritable cache folder, invitation after an update, and
 * the page-cache purge reminder.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Admin;

use QuantumCloud\AssetOptimizer\Compat;
use QuantumCloud\AssetOptimizer\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the module's notices.
 */
class Notices {

	/**
	 * User meta: the folder path for which this user dismissed the unwritable notice.
	 */
	const META_UNWRITABLE = 'qcld_wpbot_ao_unwritable_dismissed';

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Module $module The module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print the notices that apply.
	 *
	 * @return void
	 */
	public function render() {
		$host = $this->module->host();
		if ( null === $host || ! current_user_can( $host->get_capability() ) ) {
			return;
		}
		$this->unwritable( $host->is_host_screen() );
		if ( $host->is_host_screen() ) {
			$this->invitation();
		}
	}

	/**
	 * URL of a notice button.
	 *
	 * @param string $action enable, dismiss-invite or dismiss-unwritable.
	 * @return string
	 */
	private function action_url( $action ) {
		return wp_nonce_url( add_query_arg( 'do', $action, admin_url( 'admin-post.php?action=qcld_wpbot_ao_notice' ) ), Admin::NONCE_NOTICE );
	}

	/**
	 * The cache is on but its folder cannot be written. Always shown on WPBot
	 * screens; dismissible elsewhere (until the folder changes).
	 *
	 * @param bool $on_host_screen Whether this is a WPBot screen.
	 * @return void
	 */
	private function unwritable( $on_host_screen ) {
		$storage = $this->module->storage();
		if ( ! $this->module->settings()->is_enabled() || $storage->is_writable() ) {
			return;
		}
		$folder = $storage->path();
		if ( ! $on_host_screen && get_user_meta( get_current_user_id(), self::META_UNWRITABLE, true ) === $folder ) {
			return;
		}
		echo '<div class="notice notice-warning qcld-wpbot-ao-notice"><p><strong>' . esc_html__( 'WPBot asset cache: the cache folder is not writable.', 'chatbot' ) . '</strong> ';
		printf(
			/* translators: %s: folder path. */
			esc_html__( 'WPBot needs write access to %s to save smaller copies of its files. The chatbot keeps using its original files meanwhile, so nothing is broken.', 'chatbot' ),
			'<code>' . esc_html( $folder ) . '</code>'
		);
		echo ' <a href="' . esc_url( 'https://developer.wordpress.org/advanced-administration/server/file-permissions/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to fix folder permissions', 'chatbot' ) . '</a>';
		if ( ! $on_host_screen ) {
			echo ' · <a href="' . esc_url( $this->action_url( 'dismiss-unwritable' ) ) . '">' . esc_html__( 'Dismiss', 'chatbot' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * After an update from a version without the cache: offer to turn it on.
	 *
	 * @return void
	 */
	private function invitation() {
		if ( $this->module->settings()->is_enabled() || ! $this->module->state()->get( 'invite' ) ) {
			return;
		}
		echo '<div class="notice notice-info qcld-wpbot-ao-notice"><p><strong>' . esc_html__( 'New: WPBot asset cache.', 'chatbot' ) . '</strong> ';
		esc_html_e( 'WPBot can serve smaller copies of its own scripts and styles and load the chatbot without slowing down your pages. When it is off, or anything goes wrong, the original files are used.', 'chatbot' );
		echo '</p><p><a class="button button-primary" href="' . esc_url( $this->action_url( 'enable' ) ) . '">' . esc_html__( 'Turn on the asset cache', 'chatbot' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $this->module->host()->get_settings_url() ) . '">' . esc_html__( 'Review the Performance settings', 'chatbot' ) . '</a> ';
		echo '<a href="' . esc_url( $this->action_url( 'dismiss-invite' ) ) . '">' . esc_html__( 'Not now', 'chatbot' ) . '</a></p></div>';
	}

	/**
	 * Reminder to purge the page cache after clearing WPBot's, when a page
	 * cache plugin is active. Empty when none is detected.
	 *
	 * @return string Plain text.
	 */
	public static function purge_reminder_text() {
		$found = Compat::detect();
		if ( array() === $found ) {
			return '';
		}
		/* translators: %s: comma-separated names of caching plugins. */
		return sprintf( __( 'Also clear the page cache in %s, so cached pages pick up the new files.', 'chatbot' ), implode( ', ', $found ) );
	}
}
