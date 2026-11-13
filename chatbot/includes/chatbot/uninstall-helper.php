<?php
/**
 * Uninstall helper for host plugins (WPBot Pro, WPBot Free).
 *
 * Call from the host's uninstall.php:
 *
 *     require_once __DIR__ . '/includes/chatbot/uninstall-helper.php';
 *     qcld_wpbot_chat_uninstall_for_host( 'wpbot-pro' );
 *
 * The chatbot module's data is shared by every WPBot plugin that bundles it. It is
 * deleted only when no other WPBot plugin with the module is installed (active or
 * not) and the owner turned on "Delete chatbot data when the plugin is deleted"
 * (AI Settings > Chatbot data, off by default). Then the module's tables (messages,
 * knowledge gaps, change log, answer cache), its qcld_wpbot_chat_* options and its
 * transients are removed. WPBot Core's data (keys, knowledge base) follows WPBot
 * Core's own setting. On multisite it cleans the site it runs on (WordPress runs uninstall once,
 * on the main site); other sites' chatbot tables are left in place.
 *
 * @package QuantumCloud\Chatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_chat_uninstall_other_hosts' ) ) {
	/**
	 * Main files of the other plugins that bundle the chatbot module.
	 *
	 * @param string $self_dir This plugin's folder (the one being deleted).
	 * @param string $plugins  The plugins folder.
	 * @return string[]
	 */
	function qcld_wpbot_chat_uninstall_other_hosts( $self_dir, $plugins ) {
		// realpath() so a symlinked plugin folder still recognises its own copy; the plain path when
		// realpath() fails, so a failure never makes every other plugin look like this one.
		$resolve = function ( $path ) {
			$real = realpath( (string) $path );
			return wp_normalize_path( false !== $real ? $real : (string) $path );
		};
		$self    = trailingslashit( $resolve( $self_dir ) );
		$found   = (array) glob( trailingslashit( (string) $plugins ) . '*/includes/chatbot/chatbot.php' );
		if ( '/' === $self ) {
			// Unknown folder: treat every copy as another plugin, so nothing is deleted.
			return $found;
		}
		return array_values(
			array_filter(
				$found,
				function ( $file ) use ( $self, $resolve ) {
					return is_string( $file ) && '' !== $file && 0 !== strpos( $resolve( $file ), $self );
				}
			)
		);
	}
}//end if

if ( ! function_exists( 'qcld_wpbot_chat_uninstall_tables' ) ) {
	/**
	 * The module's tables (Sessions\Schema), plus any later table with the same prefix.
	 *
	 * @return string[]
	 */
	function qcld_wpbot_chat_uninstall_tables() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'qcld_wpbot_chat_';
		$tables = array();
		foreach ( array( 'messages', 'gaps', 'changes', 'answer_cache' ) as $name ) {
			$tables[] = $prefix . $name;
		}
		$found  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		$tables = array_unique( array_merge( $tables, is_array( $found ) ? $found : array() ) );
		return array_values(
			array_filter(
				$tables,
				function ( $table ) use ( $prefix ) {
					return is_string( $table ) && 0 === strpos( $table, $prefix ) && (bool) preg_match( '/^[A-Za-z0-9_]+$/', $table );
				}
			)
		);
	}
}//end if

if ( ! function_exists( 'qcld_wpbot_chat_uninstall_for_host' ) ) {
	/**
	 * Delete the chatbot module's data when this is the last WPBot plugin with the module and the owner opted in.
	 *
	 * @param string $host_id     Host id ('wpbot-pro', 'wpbot-free'), for the filter.
	 * @param string $plugin_dir  The host plugin's folder. Defaults to two folders up from this file.
	 * @return bool True when the data was deleted.
	 */
	function qcld_wpbot_chat_uninstall_for_host( $host_id, $plugin_dir = '' ) {
		global $wpdb;

		$plugin_dir = '' !== (string) $plugin_dir ? (string) $plugin_dir : dirname( __DIR__, 2 );
		$plugins    = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( $plugin_dir );
		if ( array() !== qcld_wpbot_chat_uninstall_other_hosts( $plugin_dir, $plugins ) ) {
			// Another WPBot plugin keeps the chatbot running: its own uninstall decides.
			return false;
		}

		// Scheduled events of the module, whatever the switch says: nothing is left to run them.
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'qcld_wpbot_chat_' ) || 0 === strpos( (string) $hook, 'qcld_wpbot_chat/' ) ) {
					// Every event of the hook, whatever its arguments.
					wp_unschedule_hook( $hook );
				}
			}
		}

		if ( '1' !== (string) get_option( 'qcld_wpbot_chat_delete_on_uninstall', '' ) ) {
			return false;
		}

		foreach ( qcld_wpbot_chat_uninstall_tables() as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Names checked against the module prefix, uninstall only.
		}

		// Options (the db version too, so a reinstall recreates the tables), then transients.
		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'qcld_wpbot_chat_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		foreach ( (array) $options as $option ) {
			delete_option( $option );
		}
		foreach ( array( '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_' ) as $kind ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $kind . 'qcld_wpbot_chat_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		}

		return true;
	}
}//end if
