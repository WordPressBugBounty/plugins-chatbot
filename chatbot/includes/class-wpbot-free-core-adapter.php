<?php
/**
 * WPBot Free adapter for WPBot Core (the shared services bundled in
 * includes/wpbot-core/): menu parent, WPBot branding and header, and the AI
 * keys to copy once from WPBot > AI Settings.
 *
 * Shared by every module WPBot Free bundles on top of WPBot Core (SEO & AI
 * Search and AI Writer). AI Writer also gets read-only access to the
 * ChatBot's knowledge base (search, CSV files) and can add a published post
 * to it through the ChatBot's own sync. Saved chats are not offered: WPBot
 * Free has no Chat Sessions add-on, and every feature that reads chats is
 * in WPBot Pro.
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
			</ul>
		</div>
		<?php
	}

	/**
	 * Keys and models saved in WPBot > AI Settings. Module key sets follow
	 * them live unless the owner gives a module its own keys (WPBot Core, R3). WPBot turns on one
	 * provider at a time; that one is "active", the others are copied off.
	 *
	 * @return array
	 */
	public function get_ai_import() {
		// Round R3: the ChatBot module's AI Settings (primary, backup, every saved key) is the one source.
		if ( class_exists( '\\QuantumCloud\\Chatbot\\Ai\\Ai_Settings' ) && method_exists( '\\QuantumCloud\\Chatbot\\Ai\\Ai_Settings', 'core_import' ) ) {
			return \QuantumCloud\Chatbot\Ai\Ai_Settings::core_import();
		}
		$openai_model = (string) get_option( 'qcld_openai_custom_model', '' );
		// Module provider id => enabled option, key option, model.
		$providers = array(
			'openai'     => array( 'ai_enabled', 'open_ai_api_key', '' !== $openai_model ? $openai_model : (string) get_option( 'openai_engines', '' ) ),
			'openrouter' => array( 'qcld_openrouter_enabled', 'qcld_openrouter_api_key', (string) get_option( 'qcld_openrouter_model', '' ) ),
			'gemini'     => array( 'qcld_gemini_enabled', 'qcld_gemini_api_key', (string) get_option( 'qcld_gemini_model', '' ) ),
			'grok'       => array( 'qcld_grok_enabled', 'qcld_grok_api_key', (string) get_option( 'qcld_grok_model', '' ) ),
			'claude'     => array( 'qcld_claude_enabled', 'qcld_claude_api_key', (string) get_option( 'qcld_claude_model', '' ) ),
			'mistral'    => array( 'qcld_mistral_enabled', 'qcld_mistral_api_key', (string) get_option( 'qcld_mistral_model', '' ) ),
			'deepseek'   => array( 'qcld_deepseekai_enabled', 'qcld_deepseekai_api_key', (string) get_option( 'qcld_deepseekai_engines', '' ) ),
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
	 * Read-only search of the ChatBot knowledge base, through the ChatBot's
	 * own retrieval so the embedding model matches the stored chunks. Null
	 * when the knowledge base is not loaded.
	 *
	 * @return callable|null
	 */
	public function get_knowledge_search() {
		if ( ! function_exists( 'Qcld_Bot_Rag' ) ) {
			return null;
		}
		return static function ( $query, $limit ) {
			$rag = Qcld_Bot_Rag();
			if ( ! is_object( $rag ) || ! method_exists( $rag, 'run_rag_search' ) ) {
				return new WP_Error( 'qcld_wpbot_free_no_rag', __( 'ChatBot knowledge search is not available.', 'chatbot' ) );
			}
			$text = (string) $rag->run_rag_search( (string) $query, max( 1, min( 20, (int) $limit ) ) );
			// The ChatBot answers this sentence when its knowledge base is empty.
			return 'No knowledge base found.' === $text ? '' : $text;
		};
	}

	/**
	 * Add a published post to the ChatBot knowledge base through the
	 * ChatBot's own sync (an owner action after publishing in AI Writer).
	 *
	 * @return callable|null
	 */
	public function get_knowledge_writer() {
		if ( ! function_exists( 'Qcld_Bot_Rag' ) ) {
			return null;
		}
		return static function ( $post_id ) {
			$rag = Qcld_Bot_Rag();
			if ( ! is_object( $rag ) || ! method_exists( $rag, 'wp_rag_sync_post' ) ) {
				return new WP_Error( 'qcld_wpbot_free_no_rag', __( 'ChatBot knowledge is not available.', 'chatbot' ) );
			}
			$result = $rag->wp_rag_sync_post( (int) $post_id, true );
			return is_wp_error( $result ) ? $result : true;
		};
	}

	/**
	 * Read-only access to the CSV files in the ChatBot knowledge base, for AI
	 * Writer's own research data. The ChatBot stores each CSV row as one
	 * document ("column: value" lines, the file name in its metadata); this
	 * lists the files and rebuilds one file's table. AI Writer never reads
	 * the knowledge table itself.
	 *
	 * The callable takes a file id: '' lists the files (id, title, rows); an
	 * id returns array( 'title' => string, 'columns' => string[], 'rows' => array[] )
	 * or a WP_Error. Null when the knowledge table does not exist.
	 *
	 * @return callable|null
	 */
	public function get_knowledge_documents() {
		global $wpdb;
		$table = $wpdb->prefix . 'rag_documents';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return null;
		}
		return static function ( $id = '' ) use ( $table ) {
			global $wpdb;
			$id = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $id ) );
			if ( '' === $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the prefix; no input.
				$files = $wpdb->get_results( "SELECT source_url, MIN(metadata) AS metadata, COUNT(*) AS rows_count FROM {$table} WHERE source_type = 'csv' AND status = 'complete' GROUP BY source_url ORDER BY MAX(created_at) DESC LIMIT 50", ARRAY_A );
				$out   = array();
				foreach ( (array) $files as $file ) {
					$out[] = array(
						'id'    => md5( (string) $file['source_url'] ),
						'title' => self::csv_title( (string) $file['source_url'], (string) $file['metadata'] ),
						'rows'  => (int) $file['rows_count'],
					);
				}
				return $out;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the prefix.
			$url = $wpdb->get_var( $wpdb->prepare( "SELECT source_url FROM {$table} WHERE source_type = 'csv' AND MD5(source_url) = %s LIMIT 1", $id ) );
			if ( null === $url ) {
				return new WP_Error( 'qcld_wpbot_free_no_document', __( 'This knowledge file was not found.', 'chatbot' ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the prefix.
			$docs    = $wpdb->get_results( $wpdb->prepare( "SELECT content, metadata FROM {$table} WHERE source_type = 'csv' AND source_url = %s ORDER BY id ASC LIMIT 20000", $url ), ARRAY_A );
			$rows    = array();
			$columns = array();
			$title   = '';
			foreach ( (array) $docs as $doc ) {
				$meta  = json_decode( (string) $doc['metadata'], true );
				$index = is_array( $meta ) && isset( $meta['row'] ) ? (int) $meta['row'] : count( $rows ) + 1;
				$title = '' !== $title ? $title : self::csv_title( (string) $url, (string) $doc['metadata'] );
				foreach ( preg_split( '/\r\n|\n/', (string) $doc['content'] ) as $line ) {
					$parts = explode( ': ', $line, 2 );
					if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) ) {
						continue;
					}
					$column = trim( $parts[0] );
					if ( ! in_array( $column, $columns, true ) ) {
						$columns[] = $column;
					}
					$rows[ $index ][ $column ] = isset( $rows[ $index ][ $column ] ) ? $rows[ $index ][ $column ] . $parts[1] : $parts[1];
				}
			}
			return array(
				'title'   => $title,
				'columns' => $columns,
				'rows'    => array_values( $rows ),
			);
		};
	}

	/**
	 * A knowledge CSV file's name: the file name the ChatBot recorded, else
	 * the last part of its address.
	 *
	 * @param string $url      File address.
	 * @param string $metadata Row metadata (JSON).
	 * @return string
	 */
	private static function csv_title( $url, $metadata ) {
		$meta = json_decode( $metadata, true );
		$name = is_array( $meta ) && ! empty( $meta['filename'] ) ? (string) $meta['filename'] : wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return sanitize_text_field( $name );
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
