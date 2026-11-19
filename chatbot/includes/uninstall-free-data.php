<?php
/**
 * WPBot Free's own data on uninstall (uninstall.php).
 *
 * When the owner turned on "Delete chatbot data when the plugin is deleted" (AI Settings > Chatbot
 * data, option qcld_wpbot_chat_delete_on_uninstall) and no other WPBot plugin is installed, WPBot
 * Free also deletes its own tables (conversations, visitors, subscribers, failed questions, chat
 * reports, Simple Text Responses, sessions, the old knowledge base), its options, transients, user
 * meta and scheduled events. Other plugins' options are never touched: only the names below.
 *
 * Kept on purpose: the email marketing CRM (wpbot_crm_*, contacts and campaigns: its own feature),
 * the Automator (its own block in uninstall.php), the SEO & AI marketing tables (qcld_wpbot_ai_seo_*:
 * generated articles) and settings other QuantumCloud plugins may share (qcld_plugin_info_*).
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_free_uninstall_other_hosts' ) ) {
	/**
	 * Other installed WPBot plugins that use the same data (WPBot Pro, or any plugin with the chatbot module).
	 *
	 * @param string $self_dir WPBot Free's folder.
	 * @return string[] Their main files.
	 */
	function qcld_wpbot_free_uninstall_other_hosts( $self_dir ) {
		$plugins = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( (string) $self_dir );
		$resolve = function ( $path ) {
			$real = realpath( (string) $path );
			return wp_normalize_path( false !== $real ? $real : (string) $path );
		};
		$self    = trailingslashit( $resolve( $self_dir ) );
		$others  = array_filter(
			(array) glob( trailingslashit( $plugins ) . '*/qcld-wpwbot.php' ),
			function ( $file ) use ( $self, $resolve ) {
				return is_string( $file ) && '' !== $file && 0 !== strpos( $resolve( $file ), $self );
			}
		);
		if ( function_exists( 'qcld_wpbot_chat_uninstall_other_hosts' ) ) {
			$others = array_merge( $others, qcld_wpbot_chat_uninstall_other_hosts( $self_dir, $plugins ) );
		}
		return array_values( array_unique( $others ) );
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_wanted' ) ) {
	/**
	 * Whether WPBot Free deletes its data: the chatbot's delete switch is on and no other WPBot plugin
	 * is installed. Call it before the chatbot module's helper, which deletes the switch.
	 *
	 * @param string $self_dir WPBot Free's folder.
	 * @return bool
	 */
	function qcld_wpbot_free_uninstall_wanted( $self_dir ) {
		if ( '1' !== (string) get_option( 'qcld_wpbot_chat_delete_on_uninstall', '' ) ) {
			return false;
		}
		return array() === qcld_wpbot_free_uninstall_other_hosts( $self_dir );
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_core_opt_in' ) ) {
	/**
	 * Ask WPBot Core to delete its data (keys, knowledge base, jobs, error log) when WPBot Free is its
	 * last host. Core has no switch on screen; the chatbot's delete switch stands for it. Call it
	 * before WPBot Core's uninstall helper. When another host is still listed, nothing changes.
	 *
	 * @param string $host_id WPBot Free's id in WPBot Core's host list.
	 * @return bool Whether Core was asked.
	 */
	function qcld_wpbot_free_uninstall_core_opt_in( $host_id = 'wpbot-free' ) {
		$hosts = get_option( 'qcld_wpbot_core_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? $hosts : array(), array( (string) $host_id ) ) );
		if ( array() !== $hosts ) {
			return false;
		}
		$settings                        = get_option( 'qcld_wpbot_core_settings', array() );
		$settings                        = is_array( $settings ) ? $settings : array();
		$settings['delete_on_uninstall'] = true;
		update_option( 'qcld_wpbot_core_settings', $settings, false );
		return true;
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_option_prefixes' ) ) {
	/**
	 * Option name prefixes that only WPBot Free (and WPBot Pro, which then is not installed) uses.
	 *
	 * @return string[]
	 */
	function qcld_wpbot_free_uninstall_option_prefixes() {
		return array(
			'qlcd_wp_chatbot',
			'qlcd_wp_email_',
			'wp_chatbot_',
			'wp_chat_bot_',
			'wp_chat_user_',
			'disable_wp_chatbot',
			'enable_wp_chatbot',
			'wpbot_',
			'wpbot-admin-notice-',
			'qc_wpbot_',
			'qc_bot_str_',
			'qc_wp_db_engine_update_free',
			'qc_wpb_simple_response_db_upgrade_free',
			'qcld_wb_chatbot_',
			'qcld_wbsession_',
			'qcld_wpsession_',
			'qcld_wpbot_free_',
			'qcld_wpbot_ai_file_upload_',
			'rag_embed_',
			'rate_limit_timeframe_',
			// AI provider settings (and their API keys).
			'qcld_openai_',
			'qcld_gemini_',
			'qcld_grok_',
			'qcld_claude_',
			'qcld_mistral_',
			'qcld_openrouter_',
			'qcld_deepseekai_',
			'qcld_ollama_',
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_option_excluded' ) ) {
	/**
	 * Prefixes inside the ones above that are left alone (their own uninstall code or kept on purpose).
	 *
	 * @return string[]
	 */
	function qcld_wpbot_free_uninstall_option_excluded() {
		return array(
			'wpbot_automator_',   // The Automator's own block in uninstall.php.
			'wpbot_crm_',         // Email marketing CRM: contacts and campaigns are kept.
			'wpbot_asset_cache_', // WPBot Asset Cache's uninstall helper.
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_option_names' ) ) {
	/**
	 * Single options WPBot Free saves under general names (settings screens, install, AI providers).
	 *
	 * @return string[]
	 */
	function qcld_wpbot_free_uninstall_option_names() {
		return array(
			'qlcd_wp_site_search',
			'qlcd_wp_custon_intent_label',
			'rag_auto_sync_enabled',
			'rag_top_k',
			'qcld_rag_embedding_provider',
			'open_ai_api_key',
			'ai_enabled',
			'is_page_rag_enabled',
			'is_asst_enabled',
			'is_stream_enabled',
			'is_rate_limiting_enabled',
			'openai_engines',
			'openai_max_tokens',
			'openai_temperature',
			'openai_include_keyword',
			'openai_exclude_keyword',
			'presence_penalty',
			'frequency_penalty',
			'page_suggestion_enabled',
			'context_awareness_enabled',
			'conversation_continuity',
			'gemeni_context_awareness_enabled',
			'opnrouter_context_awareness_enabled',
			'deepseekai_max_tokens',
			'qcld_voyage_api_key',
			'qcld_ai_default_email',
			'qcld_auth_username',
			'qcld_auth_password',
			'qcld_chatbot_avatar_location',
			'qcld_small_talk_imported',
			'qcld_wpbot_ai_error_text',
			'qcld_wpbot_ai_last_error',
			'qcld_wpbot_sample_str_added',
			'qc_email_subscription_offer',
			'qc_wp_ludate_ck',
			'chatbot_content_max_height',
			'chatbot_content_max_height_responsive',
			'disable_floating_button',
			'disable_back_to_start_menu',
			'skip_wp_greetings',
			'skip_wp_greetings_donot_show_menu',
			'skip_wp_greetings_trigger_intent',
			'skip_chat_reactions_menu',
			'ask_email_wp_greetings',
			'show_menu_after_greetings',
			'enable_asking_for_email',
			'enable_chat_report_menu',
			'enable_chat_session',
			'enable_chat_share_menu',
			'enable_ai_interactive_form',
			'open_links_new_window',
			'session_email_notification_update',
			'active_advance_query',
			'wpwbot_hours',
			'wp_custom_client_icon',
			'wppt_number_of_result',
			'wppt_post_types',
			'wppt_result_order',
			'wppt_result_orderby',
			'support_query',
			'support_ans',
			'chatkit_workflow_id',
			'rate_limit_guest',
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_is_free_option' ) ) {
	/**
	 * Whether an option is WPBot Free's (deleted on uninstall with the delete switch on).
	 *
	 * @param string   $name  Option name.
	 * @param string[] $roles Role keys (rate_limit_<role> options).
	 * @return bool
	 */
	function qcld_wpbot_free_uninstall_is_free_option( $name, array $roles = array() ) {
		$name = (string) $name;
		foreach ( qcld_wpbot_free_uninstall_option_excluded() as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return false;
			}
		}
		if ( in_array( $name, qcld_wpbot_free_uninstall_option_names(), true ) ) {
			return true;
		}
		foreach ( $roles as $role ) {
			if ( 'rate_limit_' . $role === $name ) {
				return true;
			}
		}
		foreach ( qcld_wpbot_free_uninstall_option_prefixes() as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_tables' ) ) {
	/**
	 * WPBot Free's tables (with the site prefix).
	 *
	 * @return string[]
	 */
	function qcld_wpbot_free_uninstall_tables() {
		global $wpdb;
		$names  = array(
			'wpbot_conversation',
			'wpbot_Conversation', // WPBot Pro's spelling; a separate table where table names are case-sensitive.
			'wpbot_user',
			'wpbot_subscription',
			'wpbot_failed_response',
			'wpbot_chat_report',
			'wpbot_response',
			'wpbot_response_category',
			'wpbot_response_entities',
			'wpbot_sessions',
			'rag_documents',
			'wpwbot_index',
			'wpwbot_cache',
		);
		$tables = array();
		foreach ( $names as $name ) {
			$tables[] = $wpdb->prefix . $name;
		}
		// Google Sheets imported into the old knowledge base.
		$sheets = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rag_sheet_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		$tables = array_merge( $tables, is_array( $sheets ) ? $sheets : array() );
		return array_values(
			array_unique(
				array_filter(
					$tables,
					function ( $table ) {
						return is_string( $table ) && (bool) preg_match( '/^[A-Za-z0-9_]+$/', $table );
					}
				)
			)
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_clear_events' ) ) {
	/**
	 * WPBot Free's scheduled events (every one of each hook, whatever its arguments).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_uninstall_clear_events() {
		foreach ( array( 'wpbot_delayed_session_email_notification', 'wpbot_send_due_session_email_notifications', 'wpbot_send_delayed_session_email_notification', 'qcld_wpsession_mysql_scraper_event', 'reset_rate_limit_used_counts' ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}
}

if ( ! function_exists( 'qcld_wpbot_free_uninstall_data' ) ) {
	/**
	 * Delete WPBot Free's tables, options, transients and user meta. Only call it when
	 * qcld_wpbot_free_uninstall_wanted() said so, after the modules' uninstall helpers ran.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_uninstall_data() {
		global $wpdb;

		qcld_wpbot_free_uninstall_clear_events();

		foreach ( qcld_wpbot_free_uninstall_tables() as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Fixed names checked above, uninstall only.
		}

		$roles = function_exists( 'wp_roles' ) ? array_keys( (array) wp_roles()->roles ) : array();
		$like  = array();
		foreach ( qcld_wpbot_free_uninstall_option_prefixes() as $prefix ) {
			$like[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( $prefix ) . '%' );
		}
		$names  = array_merge(
			qcld_wpbot_free_uninstall_option_names(),
			array_map(
				function ( $role ) {
					return 'rate_limit_' . $role;
				},
				$roles
			)
		);
		$like[] = 'option_name IN (' . implode(
			',',
			array_map(
				function ( $name ) use ( $wpdb ) {
					return $wpdb->prepare( '%s', $name );
				},
				$names
			)
		) . ')';
		$found  = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE " . implode( ' OR ', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Built from prepare() fragments, uninstall only.
		foreach ( (array) $found as $option ) {
			// The same check again in PHP: the excluded prefixes (CRM, Automator, Asset Cache) stay.
			if ( qcld_wpbot_free_uninstall_is_free_option( $option, $roles ) ) {
				delete_option( $option );
			}
		}

		// Transients (rate limits, lead and form throttles, cached model lists, email lookups).
		foreach ( array( 'wpbot_', 'qcld_lead_', 'qcld_ai_form_', 'wpcs_new_', 'qcld_openrouter_models_', 'qcld_bot_clear_cache' ) as $prefix ) {
			foreach ( array( '_transient_', '_transient_timeout_' ) as $kind ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $kind . $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
			}
		}

		if ( function_exists( 'delete_metadata' ) ) {
			delete_metadata( 'user', 0, 'qcld_openai_user_rate_limit', '', true );
		}
	}
}
