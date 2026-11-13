<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if ( ! class_exists( 'Qcld_Bot_Rag' ) ) {
    class Qcld_Bot_Rag{
        private $api_key;
        private $baseUrl;

        /** Option: the embedding provider the knowledge base was last built with (autoload off). */
        const EMBEDDING_PROVIDER_OPTION = 'qcld_rag_embedding_provider';

        /** @var bool True while embedding a visitor's question (searching), not content (indexing). */
        private $querying = false;

        /**
         * @var self
         */
        private static $_instance = null;

        /**
         * @return self
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }
        public function __construct() {
            // WPBot Core's knowledge base stores posts itself: add the text the old sync added (post meta).
            add_filter( 'qcld_wpbot_core/kb_post_text', array( $this, 'kb_post_text' ), 10, 2 );
            // Resolve the embedding provider on init, not at file load: its "switch on an AI provider"
            // message is translated, and translating before init logs a notice on every request.
            if ( did_action( 'init' ) ) {
                $this->check_api_endpoint();
            } else {
                add_action( 'init', array( $this, 'check_api_endpoint' ), 1 );
            }
            add_action( 'admin_notices', array( $this, 'embedding_changed_notice' ) );
            add_action('wp_ajax_rag_upload_pdf', array($this, 'ajax_rag_upload_pdf'));
			add_action('wp_ajax_rag_upload_csv', array($this, 'ajax_rag_upload_csv'));
			add_action('wp_ajax_rag_upload_xaml', array($this, 'ajax_rag_upload_xaml'));
			add_action('wp_ajax_qcld_rag_manual_sync', array($this, 'ajax_rag_manual_sync'));
			add_action('wp_ajax_qcld_rag_delete_document', array($this, 'qcld_rag_delete_document_callback'));
			add_action('wp_ajax_qcld_rag_get_document', array($this, 'qcld_rag_get_document_callback'));
			add_action('wp_ajax_qcld_rag_update_document', array($this, 'qcld_rag_update_document_callback'));
			add_action('wp_ajax_qcld_rag_bulk_delete_documents', array($this, 'qcld_rag_bulk_delete_documents_callback'));
			add_action('wp_ajax_qcld_rag_delete_all_documents', array($this, 'qcld_rag_delete_all_documents_callback'));
			add_action('wp_ajax_qcld_rag_get_embed_queue', array($this, 'ajax_qcld_rag_get_embed_queue'));
			add_action('wp_ajax_qcld_rag_process_item', array($this, 'ajax_qcld_rag_process_item'));
			add_action('save_post', array($this, 'wp_rag_handle_auto_sync_hook'), 10, 3);
        }

        public function check_api_endpoint() {
            $provider = $this->embedding_provider();
            if ( is_wp_error( $provider ) ) {
                $this->api_key = '';
                $this->baseUrl = '';
                return $provider;
            }
            $this->api_key = $provider['key'];
            $this->baseUrl = $provider['id'];
            return true;
        }

        /**
         * The AI provider switched on for chat, in the order the chatbot has always checked them.
         *
         * @return string Provider id, '' when none.
         */
        public static function chat_provider() {
            $switches = array(
                'openai'     => 'ai_enabled',
                'gemini'     => 'qcld_gemini_enabled',
                'openrouter' => 'qcld_openrouter_enabled',
                'grok'       => 'qcld_grok_enabled',
                'mistral'    => 'qcld_mistral_enabled',
                'claude'     => 'qcld_claude_enabled',
                'ollama'     => 'qcld_ollama_enabled',
                'deepseek'   => 'qcld_deepseekai_enabled',
            );
            foreach ( $switches as $id => $option ) {
                if ( '1' === (string) get_option( $option ) ) {
                    return $id;
                }
            }
            return '';
        }

        /**
         * Display name of a provider.
         *
         * @param string $id Provider id.
         * @return string
         */
        public static function provider_label( $id ) {
            $labels = array(
                'openai'     => 'OpenAI',
                'gemini'     => 'Google Gemini',
                'openrouter' => 'OpenRouter',
                'grok'       => 'Grok (xAI)',
                'mistral'    => 'Mistral',
                'claude'     => 'Claude',
                'voyage'     => 'Voyage AI',
                'ollama'     => 'Ollama',
                'deepseek'   => 'DeepSeek',
            );
            return isset( $labels[ $id ] ) ? $labels[ $id ] : (string) $id;
        }

        /**
         * Which provider creates the knowledge base embeddings, for every chat provider.
         *
         * OpenAI, Gemini, OpenRouter and Mistral use their own embeddings. Grok (xAI), Claude and DeepSeek
         * have no embedding model the plugin can use: Claude uses Voyage AI when its key is saved, and
         * otherwise all three use the first other key saved on the site (OpenAI, Gemini, Mistral,
         * OpenRouter; filter qcld_wpbot_embedding_fallback_providers). Ollama always uses its own local
         * embedding model (the site chose a local AI). Indexing and searching always use the same provider.
         *
         * @return array{id:string,key:string}|WP_Error
         */
        public function embedding_provider() {
            $own = array(
                'openai'     => 'open_ai_api_key',
                'gemini'     => 'qcld_gemini_api_key',
                'openrouter' => 'qcld_openrouter_api_key',
                'mistral'    => 'qcld_mistral_api_key',
            );
            $read = function ( $option ) {
                return function_exists( 'qcld_wpbot_clean_api_key' ) ? (string) qcld_wpbot_clean_api_key( get_option( $option ) ) : trim( (string) get_option( $option ) );
            };
            $chat = self::chat_provider();
            if ( '' === $chat ) {
                return new WP_Error( 'no_ai_provider', __( 'Switch on an AI provider in AI Settings to build the knowledge base.', 'chatbot' ) );
            }
            if ( isset( $own[ $chat ] ) ) {
                $key = $read( $own[ $chat ] );
                return '' === $key ? new WP_Error( 'no_api_key', __( 'No API key is set.', 'chatbot' ) ) : array( 'id' => $chat, 'key' => $key );
            }
            if ( 'ollama' === $chat ) {
                // Ollama sites chose a local AI: their content is never sent to a cloud provider for embeddings.
                return array( 'id' => 'ollama', 'key' => '' );
            }
            if ( 'claude' === $chat && '' !== $read( 'qcld_voyage_api_key' ) && method_exists( $this, 'generate_claude_embedding' ) ) {
                return array( 'id' => 'voyage', 'key' => $read( 'qcld_voyage_api_key' ) );
            }
            $fallbacks = (array) apply_filters( 'qcld_wpbot_embedding_fallback_providers', apply_filters( 'qcld_wpbot_grok_embedding_providers', array( 'openai' => 'open_ai_api_key', 'gemini' => 'qcld_gemini_api_key', 'mistral' => 'qcld_mistral_api_key', 'openrouter' => 'qcld_openrouter_api_key' ) ), $chat );
            foreach ( $fallbacks as $id => $option ) {
                $key = $read( $option );
                if ( '' !== $key && method_exists( $this, 'generate_' . $id . '_embedding' ) ) {
                    return array( 'id' => $id, 'key' => $key );
                }
            }
            $extra = ( 'claude' === $chat && method_exists( $this, 'generate_claude_embedding' ) ) ? __( ' (or a Voyage AI key)', 'chatbot' ) : '';
            return new WP_Error(
                'no_embedding_provider',
                sprintf(
                    /* translators: 1: AI provider name, 2: "(or a Voyage AI key)" for Claude. */
                    __( '%1$s does not provide embeddings for the knowledge base. Add an OpenAI, Gemini, Mistral or OpenRouter API key%2$s in AI Settings (it is only used to build and search the knowledge base).', 'chatbot' ),
                    self::provider_label( $chat ),
                    $extra
                )
            );
        }

        /**
         * Spend meter feature id: a visitor question ("query") or indexing ("embed").
         *
         * @return string
         */
        private function spend_feature() {
            return $this->querying ? 'query' : 'embed';
        }

        /**
         * Embedding of a text with the provider from embedding_provider().
         *
         * @param string $text Text.
         * @return array|WP_Error
         */
        public function generate_embedding($text) {
            $provider = $this->embedding_provider();
            if ( is_wp_error( $provider ) ) {
                return $provider;
            }
            $method = 'voyage' === $provider['id'] ? 'generate_claude_embedding' : 'generate_' . $provider['id'] . '_embedding';
            $saved_key     = $this->api_key;
            $this->api_key = $provider['key'];
            try {
                $result = $this->$method( $text );
            } catch ( Exception $e ) {
                $result = new WP_Error( 'embedding_error', $e->getMessage() );
            } finally {
                $this->api_key = $saved_key;
            }
            if ( ! $this->querying && is_array( $result ) && ! empty( $result ) && get_option( self::EMBEDDING_PROVIDER_OPTION ) !== $provider['id'] ) {
                update_option( self::EMBEDDING_PROVIDER_OPTION, $provider['id'], false );
            }
            return $result;
        }

        /**
         * True when the knowledge base was built with another embedding provider than the one in use now
         * (the AI provider or the keys changed): it must be synced again.
         *
         * @return array{built:string,now:string}|null
         */
        public function embedding_provider_changed() {
            $built = (string) get_option( self::EMBEDDING_PROVIDER_OPTION, '' );
            if ( '' === $built ) {
                return null;
            }
            $now = $this->embedding_provider();
            if ( is_wp_error( $now ) || $now['id'] === $built ) {
                return null;
            }
            return array( 'built' => $built, 'now' => $now['id'] );
        }

        /**
         * Admin notice: the AI provider (or key) changed since the knowledge base was built, so it must be
         * synced again. Shown on the AI settings and chatbot pages until a sync uses the new provider.
         *
         * @return void
         */
        public function embedding_changed_notice() {
            if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }
            $page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( ! in_array( $page, array( 'wpbot_openai', 'wpbot-panel', 'wpbot', 'wpbot-knowledge-base', 'wpbot_knowledge_base' ), true ) ) {
                return;
            }
            if ( $this->core_kb_notice() ) {
                return;
            }
            $changed = $this->embedding_provider_changed();
            if ( null === $changed ) {
                return;
            }
            printf(
                '<div class="notice notice-warning qcld-rag-resync-notice"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
                esc_html__( 'Sync your knowledge base again.', 'chatbot' ),
                esc_html(
                    sprintf(
                        /* translators: 1: provider the knowledge base was built with, 2: provider in use now. */
                        __( 'It was built with %1$s embeddings, but your AI settings now use %2$s. Until you sync, the chatbot can only match the knowledge base by keywords.', 'chatbot' ),
                        self::provider_label( $changed['built'] ),
                        self::provider_label( $changed['now'] )
                    )
                ),
                esc_url( admin_url( 'admin.php?page=wpbot_openAi&section=knowledge' ) ),
                esc_html__( 'Open the knowledge base', 'chatbot' )
            );
        }

        /**
         * The notice for WPBot Core's knowledge base (used on new and migrated sites). Core stores the
         * embedding model with every piece and only compares pieces from the current model, so after the
         * AI provider changes, pieces built with the old model wait until "Sync now". Prints the notice when
         * some pieces wait.
         *
         * @return bool True when Core's knowledge base is the one in use (the old notice is then skipped).
         */
        private function core_kb_notice() {
            $bridge = '\\QuantumCloud\\Chatbot\\Knowledge\\Kb_Bridge';
            $store  = '\\QuantumCloud\\WPBotCore\\Knowledge\\Kb_Store';
            $embed  = '\\QuantumCloud\\WPBotCore\\Knowledge\\Kb_Embedder';
            if ( ! class_exists( $bridge ) || ! method_exists( $bridge, 'active' ) || ! $bridge::active() || ! class_exists( $store ) || ! class_exists( $embed ) ) {
                return false;
            }
            $profile = $embed::profile();
            if ( ! $profile ) {
                return true; // Keyword search only: nothing to sync (the AI settings say which key to add).
            }
            $stats = $store::stats( $embed::model_id() );
            if ( empty( $stats['chunks'] ) || empty( $stats['pending'] ) ) {
                return true;
            }
            printf(
                '<div class="notice notice-warning qcld-rag-resync-notice"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
                esc_html__( 'Sync your knowledge base.', 'chatbot' ),
                esc_html(
                    sprintf(
                        /* translators: 1: pieces waiting, 2: all pieces, 3: embedding provider. */
                        __( '%1$d of %2$d pieces are not embedded with %3$s yet (new content, or the AI provider changed). Until you run Sync now, the chatbot cannot use them in answers.', 'chatbot' ),
                        (int) $stats['pending'],
                        (int) $stats['chunks'],
                        self::provider_label( (string) $profile['provider'] )
                    )
                ),
                esc_url( admin_url( 'admin.php?page=wpbot_openAi&section=knowledge' ) ),
                esc_html__( 'Open the knowledge base', 'chatbot' )
            );
            return true;
        }

        /**
         * Ollama embeddings from the local Ollama server (model option qcld_ollama_embedding_model,
         * default nomic-embed-text; run "ollama pull nomic-embed-text" first).
         *
         * @param string $text Text.
         * @return array|WP_Error
         */
        public function generate_ollama_embedding( $text ) {
            $url = rtrim( (string) get_option( 'qcld_ollama_url' ), '/' );
            if ( '' === $url ) {
                return new WP_Error( 'ollama_url', __( 'Set the Ollama server URL in AI Settings.', 'chatbot' ) );
            }
            $port  = (string) get_option( 'qcld_ollama_port' );
            $model = (string) get_option( 'qcld_ollama_embedding_model', '' );
            $model = '' === $model ? 'nomic-embed-text' : $model;
            $response = wp_remote_post(
                $url . ( '' !== $port ? ':' . $port : '' ) . '/api/embed',
                array(
                    'timeout' => 60,
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => wp_json_encode( array( 'model' => $model, 'input' => mb_substr( (string) $text, 0, 8000 ) ) ),
                )
            );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['embeddings'][0] ) && is_array( $body['embeddings'][0] ) ) {
                return $body['embeddings'][0];
            }
            $why = isset( $body['error'] ) ? (string) $body['error'] : 'HTTP ' . wp_remote_retrieve_response_code( $response );
            /* translators: 1: model, 2: error. */
            return new WP_Error( 'ollama_embedding', sprintf( __( 'Ollama could not create embeddings with %1$s: %2$s. Run "ollama pull %1$s" on the Ollama server, or add an OpenAI, Gemini, Mistral or OpenRouter key.', 'chatbot' ), $model, $why ) );
        }

        /**
         * Mistral embedding (mistral-embed, 1024 dimensions) for the knowledge base.
         *
         * @param string $text Text to embed.
         * @return array|WP_Error
         */
        public function generate_mistral_embedding($text) {
            $api_key = !empty($this->api_key) ? $this->api_key : get_option('qcld_mistral_api_key');
            if (empty($api_key)) {
                return [];
            }
            $response = qcld_wpbot_spend_post(
                'knowledge_base',
                $this->spend_feature(),
                'https://api.mistral.ai/v1/embeddings',
                [
                    'timeout' => 30,
                    'headers' => [
                        'Authorization' => "Bearer {$api_key}",
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode([
                        'model' => 'mistral-embed',
                        'input' => [mb_substr((string) $text, 0, 15000)], // well within mistral-embed's 8,192-token limit
                    ]),
                ],
                array( 'provider' => 'mistral' )
            );
            if (is_wp_error($response)) return $response;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['data'][0]['embedding']) && is_array($body['data'][0]['embedding'])) {
                return $body['data'][0]['embedding'];
            }
            $error = isset($body['message']) && is_string($body['message']) ? $body['message'] : (isset($body['detail']) ? wp_json_encode($body['detail']) : 'Unknown error');
            return new WP_Error('mistral_error', 'Mistral Error: ' . $error);
        }

        /**
         * Kept for compatibility: xAI has no embedding model the plugin can use, so this uses the
         * provider from embedding_provider() (another saved key).
         *
         * @param string $text  Text.
         * @param string $model Unused.
         * @return array|WP_Error
         */
        public function generate_xai_embedding( $text, $model = '' ) {
            return $this->generate_embedding( $text );
        }

		public function generate_openrouter_embedding( $text, $model = 'openai/text-embedding-3-small' ) {
            
            if (empty($this->api_key)) {
                $this->api_key = get_option('qcld_openrouter_api_key');
            }

            if (empty($this->api_key)) {
                return [];
            }

            $response = qcld_wpbot_spend_post(
                'knowledge_base',
                $this->spend_feature(),
                "https://openrouter.ai/api/v1/embeddings",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->api_key}",
                        'Content-Type'  => 'application/json'
                    ],
                    'body' => wp_json_encode([
                        'model' => $model,
                        'input' => mb_substr($text, 0, 15000) // keep within token limits
                    ])
                ],
                array( 'provider' => 'openrouter' )
            );

            if (is_wp_error($response)) return $response;

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['data'][0]['embedding'])) {
                return $body['data'][0]['embedding'];
            }

            if (isset($body['error']['message'])) {
                return new WP_Error('openrouter_error', 'OpenRouter Error: ' . $body['error']['message']);
            }

            if (isset($body['error']) && is_string($body['error'])) {
                return new WP_Error('openrouter_error', 'OpenRouter Error: ' . $body['error']);
            }

            return new WP_Error('invalid_response', 'Invalid response format from OpenRouter API');
        }
        public function generate_gemini_embedding($text, $model = 'gemini-embedding-001') {
            // gemini-embedding-001 as in WPBot Pro (text-embedding-004 was retired by Google).
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent";
            
            $data = [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
            
            $response_raw = qcld_wpbot_spend_post(
                'knowledge_base',
                $this->spend_feature(),
                $url,
                array(
                    // The key goes in a header, never in the address (addresses end up in logs).
                    'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $this->api_key ),
                    'body'    => wp_json_encode( $data ),
                    'timeout' => 30,
                ),
                array( 'provider' => 'gemini', 'model' => $model )
            );

            if ( is_wp_error( $response_raw ) ) {
                return $response_raw;
            }

            $httpCode = wp_remote_retrieve_response_code( $response_raw );
            if ( $httpCode !== 200 ) {
                $err = json_decode( wp_remote_retrieve_body( $response_raw ), true );
                $why = isset( $err['error']['message'] ) ? $err['error']['message'] : 'HTTP ' . $httpCode;
                return new WP_Error( 'gemini_error', 'Gemini Error: ' . $why );
            }

            $result = json_decode( wp_remote_retrieve_body( $response_raw ), true );
            if (isset($result['embedding']['values'])) {
                return $result['embedding']['values'];
            }
            
            // throw new Exception("No embedding in response: $response");
            return [];
        }
        // Generate OpenAI embedding for given text
        public function generate_openai_embedding($text) {
			

			$response = qcld_wpbot_spend_post(
				'knowledge_base',
				$this->spend_feature(),
				"https://api.openai.com/v1/embeddings",
				[
					'headers' => [
						'Authorization' => "Bearer {$this->api_key}",
						'Content-Type'  => 'application/json'
					],
					'body' => wp_json_encode([
						'model' => 'text-embedding-3-large',
						'input' => mb_substr($text, 0, 15000) // keep within token limits
					])
				],
				array( 'provider' => 'openai' )
			);

			if ( qcld_wpbot_spend_is_limit( $response ) ) return $response; // Spending limit: callers report or skip it.
			if (is_wp_error($response)) return [];

			$body = json_decode(wp_remote_retrieve_body($response), true);

			return $body['data'][0]['embedding'] ?? [];
		}
		// AJAX handler for PDF upload
		public function ajax_rag_upload_pdf() {
			check_ajax_referer('rag_upload_nonce', 'nonce');
			if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 ); } // Security: RAG uploads are admin-only.
			
			if (empty($_FILES['rag_pdf'])) {
				wp_send_json_error(['message' => 'No PDF files uploaded']);
			}

			ob_start();
			$this->wp_rag_process_pdf_upload();
			$output = ob_get_clean();
			
			wp_send_json_success(['message' => 'PDF processing complete', 'output' => $output]);
		}

		// AJAX handler for XAML upload
		public function ajax_rag_upload_xaml() {
			check_ajax_referer('rag_upload_nonce', 'nonce');
			if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 ); } // Security: RAG uploads are admin-only.
			
			if (empty($_FILES['rag_xaml'])) {
				wp_send_json_error(['message' => 'No XAML files uploaded']);
			}

			ob_start();
			$this->wp_rag_process_xaml_upload();
			$output = ob_get_clean();
			
			wp_send_json_success(['message' => 'XAML processing complete', 'output' => $output]);
		}

		// AJAX handler for CSV upload  
		public function ajax_rag_upload_csv() {
					check_ajax_referer('rag_upload_nonce', 'nonce');
					if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 ); } // Security: RAG uploads are admin-only.
					
					if (empty($_FILES['rag_csv'])) {
						wp_send_json_error(['message' => 'No CSV files uploaded']);
					}

			// ob_start();
			$this->wp_rag_process_csv_upload();
			// $output = ob_get_clean();
			
		   //  wp_send_json_success(['message' => 'CSV processing complete', 'output' => $output]);
		}

		// CSV processing method
		public function wp_rag_process_csv_upload() {
			if (empty($_FILES['rag_csv']['name'][0])) {
				echo "<p>No CSV selected.</p>";
				return;
			}

			require_once(ABSPATH . 'wp-admin/includes/file.php');
			$uploaded_files = $_FILES['rag_csv'];
           
			foreach ($uploaded_files['name'] as $index => $filename) {
				
				$file_array = [
					'name' => $uploaded_files['name'][$index],
					'type' => $uploaded_files['type'][$index],
					'tmp_name' => $uploaded_files['tmp_name'][$index],
					'error' => $uploaded_files['error'][$index],
					'size' => $uploaded_files['size'][$index],
				];
                
				$upload = wp_handle_upload($file_array, ['test_form' => false]);

				if (isset($upload['error'])) {
					echo "<p>Error uploading: " . esc_html($filename) . "</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: " . esc_html($filename) . "</p>";

				$handle = fopen($file_path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ($handle === false) {
					echo "<p style='color:red;'>Failed to open CSV file</p>";
					continue;
				}

				$header = fgetcsv($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ($header === false) {
					echo "<p style='color:red;'>Empty CSV file</p>";
					fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions
					continue;
				}

				echo "<p>Columns: " . esc_html( implode(', ', $header) ) . "</p>";
				
				global $wpdb;
				$table = $wpdb->prefix . "rag_documents";
				$row_count = 0;
				$success_count = 0;

				while (($row = fgetcsv($handle)) !== false) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					$row_count++;
					
					$content = '';
					foreach ($header as $i => $col_name) {
						if (isset($row[$i])) {
							$content .= "$col_name: " . $row[$i] . "\n";
						}
					}

					if (strlen(trim($content)) < 10) {
						continue;
					}
					$embedding = $this->generate_embedding($content);
					if (empty($embedding) || is_wp_error($embedding)) {
						echo "<p style='color:red;'>Failed embedding for row " . esc_html($row_count) . "</p>";
						continue;
					}

					$title = !empty($row[0]) ? substr($row[0], 0, 100) : "CSV Row $row_count";

					$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$table, [
						'title'       => sanitize_text_field($title),
						'content'     => $content,
						'embedding'   => wp_json_encode($embedding),
						'source_type' => 'csv',
						'source_url'  => $file_url,
						'file_url'    => $file_url,
						'status'      => 'complete',
						'metadata'    => wp_json_encode(['filename' => $filename, 'row' => $row_count]),
						'created_at'  => current_time('mysql')
					]);

					if ($result !== false) {
						$success_count++;
					} else {
						echo "<p style='color:red;'>DB error row " . esc_html($row_count) . ": " . esc_html($wpdb->last_error) . "</p>";
					}
				}

				fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions
				echo "<p style='color:green;'>✓ Processed " . esc_html($success_count) . " of " . esc_html($row_count) . " rows</p>";
			}

			echo "<h3>CSV Processing Complete!</h3>";
		}
		public function wp_rag_process_pdf_upload() {
			if (empty($_FILES['rag_pdf']['name'][0])) {
				echo "<p>No PDF selected.</p>";
				return;
			}

			require_once(ABSPATH . 'wp-admin/includes/file.php');
			$uploaded_files = $_FILES['rag_pdf'];

			foreach ($uploaded_files['name'] as $index => $filename) {

				// Upload to WP Media
				$file_array = [
					'name' => $uploaded_files['name'][$index],
					'type' => $uploaded_files['type'][$index],
					'tmp_name' => $uploaded_files['tmp_name'][$index],
					'error' => $uploaded_files['error'][$index],
					'size' => $uploaded_files['size'][$index],
				];

				$upload = wp_handle_upload($file_array, ['test_form' => false]);

				if (isset($upload['error'])) {
					echo "<p>Error uploading: " . esc_html($filename) . "</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: " . esc_html($filename) . "</p>";

				// Extract PDF text (uses Smalot/PdfParser)
				if (!class_exists('\Smalot\PdfParser\Parser')) {
					echo "<p>PDF parser missing! Install `smalot/pdfparser`.</p>";
					return;
				}

				$parser = new \Smalot\PdfParser\Parser();
				$pdf = $parser->parseFile($file_path);
				$text = $pdf->getText();

				echo "<p>Extracted text length: " . esc_html(strlen($text)) . "</p>";

				// Generate Embedding
				$embedding = $this->generate_embedding($text);
				
				if (empty($embedding) || is_wp_error($embedding)) {
					echo "<p style='color:red;'>Failed to generate embedding for: " . esc_html($filename) . "</p>";
					continue;
				}

				// Save to DB
				global $wpdb;
				$table = $wpdb->prefix . "rag_documents";

				$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$table, [
					'title'       => sanitize_text_field($filename),
					'content'     => $text,
					'embedding'   => wp_json_encode($embedding),
					'source_type' => 'pdf',
					'source_url'  => $file_url,
					'file_url'    => $file_url,
					'status'      => 'complete',
					'metadata'    => wp_json_encode(['size' => $uploaded_files['size'][$index]]),
					'created_at'  => current_time('mysql')
				]);

				if ($result === false) {
					echo "<p style='color:red;'>Database error: " . esc_html($wpdb->last_error) . "</p>";
				} else {
					echo "<p style='color:green;'>✓ Saved PDF embedding for: " . esc_html($filename) . " (ID: " . esc_html($wpdb->insert_id) . ")</p>";
				}
			}

			echo "<h3>PDF Processing Complete!</h3>";
		}

		// XAML processing method
		public function wp_rag_process_xaml_upload() {
			if (empty($_FILES['rag_xaml']['name'][0])) {
				echo "<p>No XAML selected.</p>";
				return;
			}

			require_once(ABSPATH . 'wp-admin/includes/file.php');
			$uploaded_files = $_FILES['rag_xaml'];

			foreach ($uploaded_files['name'] as $index => $filename) {
				
				$file_array = [
					'name' => $uploaded_files['name'][$index],
					'type' => $uploaded_files['type'][$index],
					'tmp_name' => $uploaded_files['tmp_name'][$index],
					'error' => $uploaded_files['error'][$index],
					'size' => $uploaded_files['size'][$index],
				];

				// Security: test_type is off for XML, so check the extension here (it also skipped the
				// extension check, letting any file type, including .php, be written to uploads).
				if ( 'xml' !== strtolower( pathinfo( $file_array['name'], PATHINFO_EXTENSION ) ) ) {
					echo "<p>Skipped " . esc_html( $file_array['name'] ) . ": only .xml files are accepted.</p>";
					continue;
				}
				$upload = wp_handle_upload($file_array, [
					'test_form' => false,
					'test_type' => false, // Bypass mime type check
				]);

				if (isset($upload['error'])) {
					echo "<p>Error uploading " . esc_html($filename) . ": " . esc_html($upload['error']) . "</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: " . esc_html($filename) . "</p>";

				// Read XAML/XML content
				global $wp_filesystem;
				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
				}
				$xml_content = $wp_filesystem->get_contents( $file_path );
				
				if (empty($xml_content)) {
					echo "<p style='color:red;'>Failed to read file or file is empty: " . esc_html($filename) . "</p>";
					continue;
				}

				// Attempt to parse as XML
				$xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NOCDATA);
				
				$items_to_process = [];

				if ($xml && isset($xml->channel->item)) {
					// It's likely a WordPress export (WXR) file
					echo "<p>Detected WordPress Export format. Extracting items...</p>";
					foreach ($xml->channel->item as $item) {
						$title = (string)$item->title;
						$namespaces = $item->getNameSpaces(true);
						$content = "";
						
						if (isset($namespaces['content'])) {
							$content = (string)$item->children($namespaces['content'])->encoded;
						} else {
							$content = (string)$item->description;
						}

						if (!empty($content)) {
							$items_to_process[] = [
								'title' => !empty($title) ? $title : $filename,
								'content' => $content,
								'source_url' => (string)$item->link
							];
						}
					}
				} else {
					// Treat as generic text/XML
					$items_to_process[] = [
						'title' => $filename,
						'content' => $xml_content,
						'source_url' => $file_url
					];
				}

				foreach ($items_to_process as $item_data) {
					$clean_content = $this->clean_rag_content($item_data['content']);
					
					if (empty($clean_content) || strlen($clean_content) < 20) {
						continue;
					}

					// Generate Embedding
					$embedding = $this->generate_embedding($clean_content);
					
					if (empty($embedding) || is_wp_error($embedding)) {
						echo "<p style='color:red;'>Failed to generate embedding for item: " . esc_html($item_data['title']) . "</p>";
						continue;
					}

					// Save to DB
					global $wpdb;
					$table = $wpdb->prefix . "rag_documents";

					$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$table, [
						'title'       => sanitize_text_field($item_data['title']),
						'content'     => $clean_content,
						'embedding'   => wp_json_encode($embedding),
						'source_type' => 'xaml',
						'source_url'  => $item_data['source_url'] ? $item_data['source_url'] : $file_url,
						'file_url'    => $file_url,
						'status'      => 'complete',
						'metadata'    => wp_json_encode(['size' => strlen($clean_content)]),
						'created_at'  => current_time('mysql')
					]);

					if ($result === false) {
						echo "<p style='color:red;'>Database error for item " . esc_html($item_data['title']) . ": " . esc_html($wpdb->last_error) . "</p>";
					} else {
						echo "<p style='color:green;'>✓ Saved embedding for: " . esc_html($item_data['title']) . " (ID: " . esc_html($wpdb->insert_id) . ")</p>";
					}
				}
			}

			echo "<h3>XAML Processing Complete!</h3>";
		}

		public function wp_rag_embed_all_documents()
		{
			$apiKey = get_option('open_ai_api_key'); // Replace with option if needed
			global $wpdb;

			$posts = get_posts([
				'post_type' => ['post', 'page'],
				'posts_per_page' => -1
			]);

			echo "<ul>";

			foreach ($posts as $p) {
				$content = wp_strip_all_tags($p->post_content);
				if (strlen($content) < 20) continue;

				$embedding = $this->wp_rag_create_embedding($content, $apiKey);
				if ( empty( $embedding ) || is_wp_error( $embedding ) ) {
					echo "<li style='color:red;'>" . esc_html( is_wp_error( $embedding ) ? $embedding->get_error_message() : 'Failed to generate embedding' ) . ': ' . esc_html( $p->post_title ) . '</li>';
					continue;
				}

				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->prefix . "rag_documents",
					[
						"title" => $p->post_title,
						"content" => $content,
						"embedding" => wp_json_encode($embedding)
					]
				);

				echo "<li>Embedded: " . esc_html($p->post_title) . "</li>";
				flush();
			}

			echo "</ul>";
			echo "<strong>Completed!</strong>";
		}

		public function wp_rag_embed_all_sources()
		{
			// $apiKey = get_option('open_ai_api_key');
			global $wpdb;

			$post_types = [];
			if (get_option('rag_embed_pages') == '1') {
				$post_types[] = 'page';
			}
			if (get_option('rag_embed_posts') == '1') {
				$post_types[] = 'post';
			}
	
			$cpts = get_option('rag_embed_cpts', []);
			if (!empty($cpts) && is_array($cpts)) {
				$post_types = array_merge($post_types, $cpts);
			}

            $table = $wpdb->prefix . "rag_documents";
			$updated_count = 0;
			$inserted_count = 0;
			$skipped_count = 0;

            echo "<ul>";

			if (!empty($post_types)) {
                $posts = get_posts([
                    'post_type' => $post_types,
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ]);

                foreach ($posts as $p) {
				$title = $p->post_title;
				$content = "Title: " . $title . "\n";
				$content .= "Date: " . $p->post_date . "\n";

				$main_content = strip_shortcodes($p->post_content);
				$main_content = wp_strip_all_tags($main_content);
				$content .= $main_content;

				// Specific handling for WooCommerce Products
				if ($p->post_type === 'product' && class_exists('WC_Product') && function_exists('wc_get_product')) {
					$_product = wc_get_product($p->ID);
					if ($_product) {
						$price = $_product->get_price();
						$currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
						$content .= "\nPrice: " . $currency . $price;
						
						// Add description if main content is empty
						if (empty(trim($main_content)) && method_exists($_product, 'get_short_description')) {
							$content .= "\nDescription: " . wp_strip_all_tags($_product->get_short_description());
						}
					}
				}

				if (strlen(trim($content)) < 20) {
					$skipped_count++;
					continue;
				}

				$embedding = $this->generate_embedding($content);

				if (empty($embedding) || is_wp_error($embedding)) {
					echo "<li style='color:red;'>Failed to generate embedding for: " . esc_html($p->post_title) . "</li>";
					continue;
				}

				$table = $wpdb->prefix . "rag_documents";

				// Check if this post already exists in the database
				$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->prepare(
					"SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
					'%"post_id":' . $p->ID . '%',
					$p->post_type
				));

				$data = [
					"title"       => $p->post_title,
					"content"     => $content,
					"embedding"   => wp_json_encode($embedding),
					"source_type" => $p->post_type,
					"source_url"  => get_permalink($p->ID),
					"file_url"    => get_permalink($p->ID),
					"status"      => 'complete',
					"metadata"    => wp_json_encode(['post_id' => $p->ID]),
					"created_at"  => current_time('mysql')
				];

				if ($existing) {
					// Update existing record
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$table,
						$data,
						['id' => $existing->id]
					);
					echo "<li style='color:blue;'>✓ Updated: " . esc_html($p->post_title) . " (" . esc_html($p->post_type) . ")</li>";
					$updated_count++;
				} else {
					// Insert new record
					$wpdb->insert($table, $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					echo "<li style='color:green;'>✓ Embedded: " . esc_html($p->post_title) . " (" . esc_html($p->post_type) . ")</li>";
					$inserted_count++;
				}

				if (function_exists('flush')) {
					@flush();
				}
				if (function_exists('ob_flush')) {
					@ob_flush();
                }
                }
            }

            // Simple Text Responses Embedding
            if (get_option('rag_embed_str') == '1') {
                $str_table = $wpdb->prefix . 'wpbot_response';
                $str_results = $wpdb->get_results("SELECT * FROM $str_table"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

                if (!empty($str_results)) {
                    foreach ($str_results as $str) {
                        $content = "Query: " . $str->query . "\n";
                        $content .= "Response: " . wp_strip_all_tags($str->response) . "\n";
                        if (!empty($str->keyword)) {
                            $content .= "Keywords: " . $str->keyword;
                        }

                        if (strlen(trim($content)) < 10) {
                            $skipped_count++;
                            continue;
                        }

                        $embedding = $this->generate_embedding($content);
                        if (empty($embedding) || is_wp_error($embedding)) {
                            echo "<li style='color:red;'>Failed to generate embedding for STR: " . esc_html($str->query) . "</li>";
                            continue;
                        }

                        // Check if this STR already exists in the RAG database
                        $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $wpdb->prepare(
                            "SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
                            '%"str_id":' . $str->id . '%',
                            'str'
                        ));

                        $data = [
                            "title"       => $str->query,
                            "content"     => $content,
                            "embedding"   => wp_json_encode($embedding),
                            "source_type" => 'str',
                            "source_url"  => admin_url('admin.php?page=simple-text-response&action=edit&query=' . $str->id),
                            "file_url"    => '',
                            "status"      => 'complete',
                            "metadata"    => wp_json_encode(['str_id' => $str->id]),
                            "created_at"  => current_time('mysql')
                        ];

                        if ($existing) {
                            $wpdb->update($table, $data, ['id' => $existing->id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            echo "<li style='color:blue;'>✓ Updated STR: " . esc_html($str->query) . "</li>";
                            $updated_count++;
                        } else {
                            $wpdb->insert($table, $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                            echo "<li style='color:green;'>✓ Embedded STR: " . esc_html($str->query) . "</li>";
                            $inserted_count++;
                        }

                        if (function_exists('flush')) { @flush(); }
                        if (function_exists('ob_flush')) { @ob_flush(); }
                    }
                }
            }

			echo "</ul>";
			echo "<h3>All Selected Sources Processed!</h3>";
			echo "<p><strong>Summary:</strong></p>";
			echo "<ul>";
			echo "<li>New entries created: <strong>" . esc_html($inserted_count) . "</strong></li>";
			echo "<li>Existing entries updated: <strong style='color:blue;'>" . esc_html($updated_count) . "</strong></li>";
			echo "<li>Skipped (too short): <strong>" . esc_html($skipped_count) . "</strong></li>";
			echo "</ul>";
		}
		public function wp_rag_create_embedding($text, $apiKey)
		{
            
            $response = $this->generate_embedding($text);
			 return $response;
		}
        public function ajax_rag_manual_sync() {
			
            check_ajax_referer('wp_chatbot', 'nonce');
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized access' );
			}
            
            $doc_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if (!$doc_id) {
                wp_send_json_error(['message' => 'Invalid document ID']);
            }

            global $wpdb;
            $table = $wpdb->prefix . 'rag_documents';
            $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $doc_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if (!$doc) {
                wp_send_json_error(['message' => 'Document not found']);
            }

            // Try to extract post/product info
            $post_id = 0;
            if (!empty($doc->metadata)) {
                $metadata = json_decode($doc->metadata, true);
                if (isset($metadata['post_id'])) {
                    $post_id = intval($metadata['post_id']);
                }
            }

            if (!$post_id && ($doc->source_type === 'page' || $doc->source_type === 'post' || $doc->source_type === 'xaml')) {
                $post_id = url_to_postid($doc->source_url);
            }

            if (!$post_id) {
                wp_send_json_error(['message' => 'Could not determine source post for manual sync']);
            }

            $result = $this->wp_rag_sync_post($post_id, true);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Document synced successfully!']);
        }
		public function qcld_rag_delete_document_callback() {
			check_ajax_referer('wp_chatbot', 'nonce');
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
			}

			global $wpdb;
			$id = intval($_POST['id']);
			$table_name = $wpdb->prefix . 'rag_documents';
			
			$deleted = $wpdb->delete($table_name, array('id' => $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			
			if ($deleted) {
				wp_send_json_success('Document deleted successfully.');
			} else {
				wp_send_json_error('Failed to delete document.');
			}
		}
		public function qcld_rag_bulk_delete_documents_callback() {
			check_ajax_referer('wp_chatbot', 'nonce');
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
			}

			if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
				wp_send_json_error('No documents selected.');
			}

			global $wpdb;
			$ids = array_map('intval', $_POST['ids']);
			$table_name = $wpdb->prefix . 'rag_documents';
			
			$ids_string = implode(',', $ids);
			$deleted = $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_string)"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			
			if ($deleted !== false) {
				wp_send_json_success('Selected documents deleted successfully.');
			} else {
				wp_send_json_error('Failed to delete selected documents.');
			}
		}
		public function qcld_rag_delete_all_documents_callback() {
			check_ajax_referer('wp_chatbot', 'nonce');
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'rag_documents';
			
			$deleted = $wpdb->query("TRUNCATE TABLE $table_name"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			
			// Some DBs might not support TRUNCATE on tables with foreign keys or other constraints, 
			// though rag_documents is likely simple. Fallback to DELETE.
			if ($deleted === false) {
				$deleted = $wpdb->query("DELETE FROM $table_name"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
			
			if ($deleted !== false) {
				wp_send_json_success('All documents deleted successfully.');
			} else {
				wp_send_json_error('Failed to delete all documents.');
			}
		}
		public function qcld_rag_get_document_callback() {
			check_ajax_referer('wp_chatbot', 'nonce');
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
			}

			global $wpdb;
			$id = intval($_POST['id']);
			$table_name = $wpdb->prefix . 'rag_documents';
			
			$document = $wpdb->get_row($wpdb->prepare("SELECT id, title, content FROM $table_name WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			
			if ($document) {
				wp_send_json_success($document);
			} else {
				wp_send_json_error('Document not found.');
			}
		}
		public function qcld_rag_update_document_callback() {
			check_ajax_referer('wp_chatbot', 'nonce');
			if (!current_user_can('manage_options')) {
				wp_send_json_error('Unauthorized');
			}

			global $wpdb;
			$id = intval(wp_unslash($_POST['id']));
			$title = sanitize_text_field(wp_unslash($_POST['title']));
			$content = sanitize_textarea_field(wp_unslash($_POST['content']));
			$table_name = $wpdb->prefix . 'rag_documents';
			
			// Re-generate embedding if content changed
			$old_content = $wpdb->get_var($wpdb->prepare("SELECT content FROM $table_name WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			
			$update_data = array(
				'title' => $title,
				'content' => $content,
				'status' => 'complete'
			);

			if ($old_content !== $content) {
				$embedding = $this->generate_embedding($content);
				if (!empty($embedding) && !is_wp_error($embedding)) {
					$update_data['embedding'] = wp_json_encode($embedding);
				} else {
					$update_data['status'] = 'error';
				}
			}

			$updated = $wpdb->update($table_name, $update_data, array('id' => $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			
			if ($updated !== false) {
				wp_send_json_success('Document updated successfully.');
			} else {
				wp_send_json_error('Failed to update document.');
			}
		}
		public function wp_rag_sync_post($post_id, $force = false) {
		$post = get_post($post_id);
		if (!$post) return new WP_Error('invalid_post', 'Post not found');

		$title = $post->post_title;
		$url = get_permalink($post_id);
		$content = "Title: " . $title . "\n";
		$content .= "Date: " . $post->post_date . "\n";
		
		$main_content = strip_shortcodes($post->post_content);
		$main_content = wp_strip_all_tags($main_content);
		$content .= $main_content;

		// Specific handling for WooCommerce Products
		if ($post->post_type === 'product' && class_exists('WC_Product') && function_exists('wc_get_product')) {
			$_product = wc_get_product($post_id);
			if ($_product) {
				$price = $_product->get_price();
				$currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
				$content .= "\nPrice: " . $currency . $price;
				
				// Add description if main content is empty (sometimes WC uses short description)
				if (empty($main_content) && method_exists($_product, 'get_short_description')) {
					$content .= "\nDescription: " . wp_strip_all_tags($_product->get_short_description());
				}
			}
		}

		if (empty(trim($main_content)) && !($post->post_type === 'product')) {
			return new WP_Error('empty_content', 'No content found to embed');
		}

		// Generate Embedding
		$embedding = $this->generate_embedding($content);
		if (empty($embedding) || is_wp_error($embedding)) {
			return new WP_Error('embedding_failed', 'Failed to generate embedding');
		}

		global $wpdb;
		$table = $wpdb->prefix . "rag_documents";

		// Check if it already exists (by source_url or custom metadata if we had it)
		$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE source_url = %s", $url)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ($existing) {
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table, [
				'title'      => sanitize_text_field($title),
				'content'    => $content,
				'embedding'  => wp_json_encode($embedding),
				'status'     => 'complete',
				'created_at' => current_time('mysql')
			], ['id' => $existing->id]);
		} else {
			$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table, [
				'title'       => sanitize_text_field($title),
				'content'     => $content,
				'embedding'   => wp_json_encode($embedding),
				'source_type' => ($post->post_type === 'page' || $post->post_type === 'post') ? $post->post_type : 'xaml',
				'source_url'  => $url,
				'file_url'    => $url,
				'status'      => 'complete',
				'metadata'    => wp_json_encode(['post_id' => $post_id, 'post_type' => $post->post_type]),
				'created_at'  => current_time('mysql')
			]);
		}

		return $result;
	}
		public function wp_rag_handle_auto_sync_hook($post_id, $post, $update) {
		// Chatbot rebuild (M3): WPBot Core's knowledge base keeps posts in sync itself (as in Pro).
		// Running this too wrote the post to the old table and paid for a second embedding on every save.
		if ( class_exists( '\QuantumCloud\Chatbot\Knowledge\Kb_Bridge' ) && \QuantumCloud\Chatbot\Knowledge\Kb_Bridge::active() ) {
			return;
		}
		// Only run if auto-sync is enabled
		if (get_option('rag_auto_sync_enabled') != '1') {
			return;
		}

		// Avoid autosaves and revisions
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (wp_is_post_revision($post_id)) return;
		if ($post->post_status != 'publish') return;

		// Check if post type is enabled in general embedding settings
		$is_allowed = false;
		if ($post->post_type === 'page' && get_option('rag_embed_pages') == '1') {
			$is_allowed = true;
		} elseif ($post->post_type === 'post' && get_option('rag_embed_posts') == '1') {
			$is_allowed = true;
		} else {
			$cpts = get_option('rag_embed_cpts', []);
			if (is_array($cpts) && in_array($post->post_type, $cpts)) {
				$is_allowed = true;
			}
		}

		if (!$is_allowed) {
			return;
		}

		$this->wp_rag_sync_post($post_id);
	}

    public function clean_rag_content($text) {
        if (empty($text)) return "";

        // Remove WordPress block comments like <!-- wp:paragraph -->
        $text = preg_replace('/<!--\s*\/?[a-z0-9_-]+:[a-z0-9_-]+\s*({.*?})?\s*-->/s', '', $text);
        
        // Remove generic HTML comments
        $text = preg_replace('/<!--(.*?)-->/s', '', $text);
        
        // Strip HTML tags
        $text = wp_strip_all_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    public function run_rag_search($user_query, $top_k = 3, &$rag_links = array()) {
        global $wpdb;
        // Chatbot rebuild (M3, as in Pro): once WPBot Core's knowledge base has taken over (migrated, or
        // nothing to migrate), search it. Posts saved with Auto Sync and "Sync now" are stored there.
        if ( class_exists( '\QuantumCloud\Chatbot\Knowledge\Kb_Bridge' ) && \QuantumCloud\Chatbot\Knowledge\Kb_Bridge::active() && function_exists( 'qcld_wpbot_core_kb_search' ) ) {
            $k       = null === $top_k ? max( 3, (int) get_option( 'rag_top_k', 3 ) ) : (int) $top_k;
            $results = qcld_wpbot_core_kb_search( (string) $user_query, array( 'top_k' => min( 50, max( 1, $k ) ) ) );
            $seen    = array();
            foreach ( $results as $result ) {
                if ( '' !== $result['url'] && ! isset( $seen[ $result['url'] ] ) && filter_var( $result['url'], FILTER_VALIDATE_URL ) ) {
                    $seen[ $result['url'] ] = true;
                    $rag_links[]            = array( 'title' => '' !== $result['title'] ? $result['title'] : $result['url'], 'link' => $result['url'] );
                }
            }
            $context = trim( \QuantumCloud\WPBotCore\Knowledge\Kb_Search::context( $results ) );
            return '' !== $context ? $context : 'No knowledge base found.';
        }
        if ( null === $top_k ) {
            $top_k = 3;
        }
        $table = $wpdb->prefix . "rag_documents";

        // Get all embeddings and texts
        $rows = $wpdb->get_results("SELECT content, embedding FROM $table WHERE status = 'complete'", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($rows)) {
            return "No knowledge base found.";
        }

        // Step 1: Get embedding for user query
        // Vectors from different providers cannot be compared: no knowledge base context until it is synced again.
        if ( $this->embedding_provider_changed() ) {
            return "";
        }
        $this->querying = true;
        $query_vector = $this->generate_embedding($user_query);
        $this->querying = false;

        if (empty($query_vector) || is_wp_error($query_vector)) {
            return "";
        }

        // Step 2: Compute cosine similarity
        $ranked = [];
        foreach ($rows as $row) {
            $doc_embedding = json_decode($row['embedding'], true);
            if (!is_array($doc_embedding) || empty($doc_embedding)) {
                continue;
            }
            $sim = $this->cosine_similarity($query_vector, $doc_embedding);
            $ranked[] = ["score" => $sim, "text" => $row['content']];
        }

        // Step 3: Sort by similarity
        usort($ranked, function ($a, $b) {
            return $a['score'] < $b['score'] ? 1 : -1;
        });

        // Select top k documents
        $top_docs = array_slice($ranked, 0, $top_k);

        $context = "";
        foreach ($top_docs as $doc) {
            $context .= $doc["text"] . "\n\n";
        }

        return trim($context);
    }

    private function cosine_similarity($vecA, $vecB) {
        if (!is_array($vecA) || !is_array($vecB) || count($vecA) !== count($vecB)) {
            return 0.0;
        }
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = count($vecA);
        for ($i = 0; $i < $len; $i++) {
            $dot += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] ** 2;
            $normB += $vecB[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    public function ajax_qcld_rag_get_embed_queue() {
        check_ajax_referer('wp_chatbot', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $queue = [];

        // Posts, Pages, CPTs
        $post_types = [];
        if (get_option('rag_embed_pages') == '1') {
            $post_types[] = 'page';
        }
        if (get_option('rag_embed_posts') == '1') {
            $post_types[] = 'post';
        }

        $cpts = get_option('rag_embed_cpts', []);
        if (!empty($cpts) && is_array($cpts)) {
            $post_types = array_merge($post_types, $cpts);
        }

        if (!empty($post_types)) {
            $posts = get_posts([
                'post_type' => $post_types,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ]);
            foreach ($posts as $post_id) {
                $queue[] = ['id' => $post_id, 'type' => 'post'];
            }
        }

        // Simple Text Responses
        if (get_option('rag_embed_str') == '1') {
            $str_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}wpbot_response"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            foreach ($str_ids as $str_id) {
                $queue[] = ['id' => $str_id, 'type' => 'str'];
            }
        }

        wp_send_json_success($queue);
    }

    public function ajax_qcld_rag_process_item() {
        check_ajax_referer('wp_chatbot', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = intval($_POST['item_id']);
        $type = sanitize_text_field($_POST['item_type']);

        if ($type === 'post') {
            $p = get_post($id);
            if (!$p) {
                wp_send_json_error('Post not found');
            }

            global $wpdb;
            $table = $wpdb->prefix . "rag_documents";
            $apiKey = get_option('open_ai_api_key');

            $title = $p->post_title;
            $content = "Title: " . $title . "\n";
           

            $main_content = strip_shortcodes($p->post_content);
            $main_content = wp_strip_all_tags($main_content);
            $content .= $main_content;

            // Add Post Meta Context
            $content .= $this->get_post_meta_context($id);

            if ($p->post_type === 'product' && class_exists('WC_Product')) {
                $_product = wc_get_product($p->ID);
                if ($_product) {
                    $price = $_product->get_price();
                    $currency = get_woocommerce_currency_symbol();
                    $content .= "\nPrice: " . $currency . $price;
                    if (empty(trim($main_content))) {
                        $content .= "\nDescription: " . wp_strip_all_tags($_product->get_short_description());
                    }
                    $content .= "\nProduct Link: " . get_permalink($p->ID);
					$content .= "\nProduct ID: " . $p->ID;

                }
            }

            if (strlen(trim($content)) < 20) {
                wp_send_json_success(['status' => 'skipped', 'title' => $title]);
            }

            $embedding = $this->wp_rag_create_embedding($content, $apiKey);
            if (empty($embedding) || is_wp_error($embedding)) {
                $error_msg = is_wp_error($embedding) ? $embedding->get_error_message() : 'Failed to generate embedding';
                wp_send_json_error($error_msg);
            }

            $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                "SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
                '%"post_id":' . $p->ID . '%',
                $p->post_type
            ));

            $data = [
                "title"       => $p->post_title,
                "content"     => $content,
                "embedding"   => wp_json_encode($embedding),
                "source_type" => $p->post_type,
                "source_url"  => get_permalink($p->ID),
                "file_url"    => get_permalink($p->ID),
                "status"      => 'complete',
                "metadata"    => wp_json_encode(['post_id' => $p->ID]),
                "created_at"  => current_time('mysql')
            ];

            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing->id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                wp_send_json_success(['status' => 'updated', 'title' => $title]);
            } else {
                $wpdb->insert($table, $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                wp_send_json_success(['status' => 'inserted', 'title' => $title]);
            }

        } elseif ($type === 'str') {
            global $wpdb;
            $str = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpbot_response WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$str) {
                wp_send_json_error('STR not found');
            }

            $content = "Query: " . $str->query . "\n";
            $content .= "Response: " . wp_strip_all_tags($str->response) . "\n";
            if (!empty($str->keyword)) {
                $content .= "Keywords: " . $str->keyword . "\n";
            }
            if (!empty($str->intent)) {
                $content .= "Intent: " . $str->intent . "\n";
            }

            if (strlen(trim($content)) < 20) {
                wp_send_json_error('No content found to embed');
            }

            $embedding = $this->generate_embedding($content);

            if (empty($embedding) || is_wp_error($embedding)) {
                wp_send_json_error('Failed to generate embedding');
            }

            $table = $wpdb->prefix . "rag_documents";

            $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                "SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
                '%"str_id":' . $str->id . '%',
                'str'
            ));

            $data = [
                "title"       => $str->query,
                "content"     => $content,
                "embedding"   => wp_json_encode($embedding),
                "source_type" => 'str',
                "source_url"  => '', 
                "file_url"    => '',
                "status"      => 'complete',
                "metadata"    => wp_json_encode(['str_id' => $str->id]),
                "created_at"  => current_time('mysql')
            ];

            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing->id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                $wpdb->insert($table, $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
            wp_send_json_success(['status' => 'processed', 'title' => 'Simple Text Response ID ' . $id]);
        }

        wp_send_json_error('Invalid item type');
    }

    /**
     * Text of a post in WPBot Core's knowledge base: the post meta chosen in the Knowledge Base
     * settings (Post Meta), as the old sync (wp_rag_sync_post) added them.
     *
     * @param string  $text Text built by WPBot Core (content and excerpt).
     * @param WP_Post $post Post.
     * @return string
     */
    public function kb_post_text( $text, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return $text;
        }
        $text = (string) $text . $this->get_post_meta_context( $post->ID );
        return $text;
    }

    public function get_post_meta_context($post_id) {
        if (get_option('rag_embed_meta') != '1') {
            return "";
        }

        $meta_keys_str = get_option('rag_embed_meta_keys', '');
        $meta_context = "";

        if (!empty($meta_keys_str)) {
            $keys = array_map('trim', explode(',', $meta_keys_str));
            foreach ($keys as $key) {
                $value = get_post_meta($post_id, $key, true);
                if (!empty($value)) {
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }
                    $meta_context .= "\n" . ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
                }
            }
        } else {
            $all_meta = get_post_meta($post_id);
            foreach ($all_meta as $key => $values) {
                if (strpos($key, '_') === 0) continue;
                $value = $values[0];
                if (!empty($value)) {
                    $meta_context .= "\n" . ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
                }
            }
        }

        return $meta_context;
    }

    }
}
if ( ! function_exists( 'Qcld_Bot_Rag' ) ) {

    function Qcld_Bot_Rag() {
        return Qcld_Bot_Rag::instance();
    }
}

// fire off the plugin.
Qcld_Bot_Rag();
