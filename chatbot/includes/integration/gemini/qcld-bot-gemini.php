<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('qcld_wpgemini_addons')){


    /**
     * Main Class.
     */
    final class qcld_wpgemini_addons
    {
        private $id = 'Open AI';

        /**
         * WPBot Pro version.
         *
         * @var string
         */
        public $version = '1.0.6';
        
        /**
         * WPBot Pro helper.
         *
         * @var object
         */
        public $helper;

        /**
         * The single instance of the class.
         *
         * @var qcld_wb_Chatbot
         * @since 1.0.0
         */
        protected static $_instance = null;
        
        /**
         * Main wpbot Instance.
         *
         * Ensures only one instance of wpbot is loaded or can be loaded.
         *
         * @return qcld_wb_Chatbot - Main instance.
         * @since 1.0.0
         * @static
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        public $response_list;

        /**
         *  Constructor
         */
        public function __construct()
        {

            $this->includes();
            add_action('wp_ajax_qcld_gemini_response',[$this,'qcld_gemini_response_callback']);
            add_action('wp_ajax_nopriv_qcld_gemini_response', [$this, 'qcld_gemini_response_callback']);
            add_action('wp_ajax_qcld_gemini_settings_option',[$this,'qcld_gemini_settings_option_callback']);

            add_action('wp_ajax_update_settings_option', [$this, 'qcld_update_settings_option_callback']);

            if (is_admin() && !empty($_GET["page"]) && (($_GET["page"] == "openai-panel_dashboard") || ($_GET["page"] == "openai-panel_file") || ($_GET["page"] == "openai-panel_help"))) {
                add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_admin_scripts'));
            }
            //add_action('wp_enqueue_scripts', array($this, 'qcld_wb_chatbot_gemini_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_gemini_admin_scripts'));
        }



        public function qcld_wb_chatbot_gemini_admin_scripts() {
              if ( ! current_user_can( 'manage_options' ) ) {
                    return ;
                }
            wp_register_script(
                'qcld-wp-chatbot-gemini-admin-js', 
                QCLD_wpCHATBOT_PLUGIN_URL . 'includes/integration/gemini/assets/js/qcld-wp-gemini-admin.js', 
                array('jquery'), 
                QCLD_wpCHATBOT_VERSION, 
                true
            );

            // Localize the script with necessary data
            wp_localize_script('qcld-wp-chatbot-gemini-admin-js', 'qcld_gemini_admin_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('wp_chatbot'),
                'gemini_api_key' => get_option('qcld_gemini_api_key'),
                'gemini_enabled' => get_option('qcld_gemini_enabled'),
                'qcld_gemini_append_content' => get_option('qcld_gemini_append_content'),
                'qcld_gemini_prepend_content' => get_option('qcld_gemini_prepend_content')
            ));
            
            wp_enqueue_script('qcld-wp-chatbot-gemini-admin-js');
        }
        
        /**
         * Define wpbot Constants.
         *
         * @return void
         * @since 1.0.0
         */
        public function includes() {
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/Parsedown.php" );
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/class-common-function.php" );
        }
        public function qcld_gemini_settings_option_callback() {
                $nonce = sanitize_text_field($_POST['nonce']);
                if (!wp_verify_nonce($nonce, 'wp_chatbot')) {
                    wp_send_json(array('success' => false, 'msg' => esc_html__('Failed in Security check', 'chatbot')));
                    wp_die();
                } elseif ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Unauthorized user', 'chatbot' ) ) );
					wp_die();
				} else {
                    $gemini_api_key = sanitize_text_field($_POST['gemini_api_key']);
                    $gemini_enabled = sanitize_text_field($_POST['gemini_enabled']);
                    $qcld_gemini_page_suggestion_enabled = sanitize_text_field($_POST['qcld_gemini_page_suggestion_enabled']);
                    $gemini_is_context_awareness_enabled = sanitize_text_field($_POST['gemini_is_context_awareness_enabled']);
                    $qcld_gemini_append_content = sanitize_text_field($_POST['qcld_gemini_append_content']) ?? '';
                    $qcld_gemini_prepend_content = sanitize_text_field($_POST['qcld_gemini_prepend_content']) ?? '';
                    if($gemini_api_key != '') {
                        update_option('qcld_gemini_api_key', $gemini_api_key);
                    }
                    if($gemini_enabled != '') {
                        update_option('qcld_gemini_enabled', $gemini_enabled);
                    }
                    if($gemini_enabled == '1') {
                        update_option('ai_enabled', 0);
                        update_option('qcld_openrouter_enabled', 0);
                    
                    } else {
                        update_option('ai_enabled', 1);
                    }
                    update_option('qcld_gemini_page_suggestion_enabled', $qcld_gemini_page_suggestion_enabled);
                    update_option('gemeni_context_awareness_enabled', $gemini_is_context_awareness_enabled);
                    $openai_post_types = array();
                    if (isset($_POST['openai_post_type'])) {
                        $raw_post_types = wp_unslash($_POST['openai_post_type']);
                        if (is_array($raw_post_types)) {
                            $openai_post_types = array_map('sanitize_text_field', $raw_post_types);
                        } else {
                            $openai_post_types = sanitize_text_field($raw_post_types);
                        }
                    }
                    $is_page_rag_enabled = sanitize_text_field($_POST['is_page_rag_enabled']);
                    update_option('qcld_openai_relevant_post', $openai_post_types);
                    update_option('is_page_rag_enabled', $is_page_rag_enabled);
                    
                    update_option('qcld_gemini_append_content', $qcld_gemini_append_content);
                    update_option('qcld_gemini_prepend_content', $qcld_gemini_prepend_content);
                    
                }
                echo wp_json_encode($gemini_enabled);
                wp_die();
        }
		public function qcld_gemini_response_callback() {
		
			$gemini_api_key   = get_option( 'qcld_gemini_api_key' );
			$keyword          = isset($_POST['keyword']) ? $_POST['keyword'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$relevant_pagelink = Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword );
			$relevant_pagelink = array_slice( $relevant_pagelink, 0, 5, true );

			if ( ( get_option( 'page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {
				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
				// Always use get_locale() to avoid undefined function error
				$locale = get_locale();
				if ( is_array( $relevant_post_link ) && isset( $relevant_post_link[ $locale ] ) && is_array( $relevant_post_link[ $locale ] ) ) {
					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ $locale ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					// Fallback: try to use $locale, but check if key exists
					$em_text = '';
					if ( is_array( $relevant_post_link ) && isset( $relevant_post_link[ $locale ] ) ) {
						$em_text = is_array( $relevant_post_link[ $locale ] ) ? implode( '', $relevant_post_link[ $locale ] ) : $relevant_post_link[ $locale ];
					}
					$relevant_pagelinks = '<br><br><p><em>' . $em_text . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			} else {
				$relevant_pagelinks = '';
			}

			$Qcld_Parsedown = new Qcld_Parsedown();

			// Build context-aware content with system instructions
			$system_instructions = '';
			if ( get_option('gemeni_context_awareness_enabled') == '1' ) {
				$site_name = get_bloginfo('name');
				$site_desc = get_bloginfo('description');
				
				// Get current page URL and title more reliably
				$current_url = '';
				$page_title = '';
				$page_summary = '';
				
				// Try to get from referrer first
				$ref = wp_get_referer();
				if ( ! $ref && isset($_SERVER['HTTP_REFERER']) ) {
					$ref = esc_url_raw( $_SERVER['HTTP_REFERER'] );
				}
				
				if ( $ref ) {
					$current_url = $ref;
					
					// Try to get post/page by URL, fallback to query param if needed.
					$post_id = url_to_postid( $ref );
					if ( ! $post_id && isset( $_GET['p'] ) ) {
						$post_id = intval( $_GET['p'] );
					}
					if ( $post_id ) {
						$page_title = get_the_title( $post_id );
						$raw_content = get_post_field( 'post_content', $post_id );
						$text_content = wp_strip_all_tags( $raw_content );
						$page_summary = wp_trim_words( $text_content, 120, '…' );
					} else {
						// If not a post/page, try to extract title from URL or use current page
						$parsed_url = parse_url( $ref );
						if ( isset($parsed_url['path']) ) {
							$path = trim($parsed_url['path'], '/');
							if ( ! empty($path) ) {
								// Try to get title from current page if we're on it
								if ( is_singular() ) {
									$page_title = get_the_title();
									$raw_content = get_the_content();
									$text_content = wp_strip_all_tags( $raw_content );
									$page_summary = wp_trim_words( $text_content, 120, '…' );
								} elseif ( is_archive() ) {
									$page_title = get_the_archive_title();
								} elseif ( is_search() ) {
									$page_title = 'Search Results';
								} elseif ( is_404() ) {
									$page_title = 'Page Not Found';
								}
							}
						}
					}
				} else {
					// Fallback to current page info.
					$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");

					// Sanitize HTTP_HOST and REQUEST_URI from $_SERVER.
					$sanitized_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
					$sanitized_request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';

					// Construct the URL with sanitized components.
					$current_url_temp = $scheme . '://' . $sanitized_host . $sanitized_request_uri;

					// Final sanitization of the entire URL string.
					$current_url = esc_url_raw($current_url_temp);
					
					if ( is_singular() ) {
						$page_title = get_the_title();
						$raw_content = get_the_content();
						$text_content = wp_strip_all_tags( $raw_content );
						$page_summary = wp_trim_words( $text_content, 120, '…' );
					} elseif ( is_archive() ) {
						$page_title = get_the_archive_title();
					} elseif ( is_search() ) {
						$page_title = 'Search Results';
					} elseif ( is_404() ) {
						$page_title = 'Page Not Found';
					}
				}

				$context_bits = array();
				if ( $site_name ) { $context_bits[] = 'Site: ' . $site_name; }
				if ( $site_desc ) { $context_bits[] = 'Tagline: ' . $site_desc; }
				if ( $page_title ) { $context_bits[] = 'Page title: ' . $page_title; }
				if ( $current_url ) { $context_bits[] = 'URL: ' . $current_url; }
				if ( $page_summary ) { $context_bits[] = 'Page summary: ' . $page_summary; }

				if ( ! empty( $context_bits ) ) {
					$system_instructions = 'Context Information: ' . implode( '. ', $context_bits ) . '. Please use this context to provide more relevant and accurate responses.';
				}
			}

			// RAG Integration
			if (get_option('is_page_rag_enabled') == '1') {
				$rag_context_text = Qcld_Bot_Rag::instance()->run_rag_search($keyword);
				if (!empty($rag_context_text) && $rag_context_text != "No knowledge base found.") {
						$rag_context = "Relevant Knowledge Base Information:\n";
						$rag_context .= $rag_context_text;
						$rag_context .= "\n\nUse the above information to answer the user's question. If the answer is not in the Knowledge Base, rely on your general knowledge but mention that this information is not in the local knowledge base.";
						
						if (!empty($system_instructions)) {
							$system_instructions .= "\n\n" . $rag_context;
						} else {
							$system_instructions = $rag_context;
						}
				}
			}

			if ( ( get_option( 'page_suggestion_enabled' ) == '1' ) && count( $relevant_pagelink ) > 0 ) {
				$relevant_post_link = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
				// Always use get_locale() to avoid undefined function error
				$locale = get_locale();
				if ( is_array( $relevant_post_link ) && isset( $relevant_post_link[ $locale ] ) && is_array( $relevant_post_link[ $locale ] ) ) {
					$relevant_pagelinks = '<br><br><p><em>' . implode( '', $relevant_post_link[ $locale ] ) . '</em><p>' . implode( '</br>', $relevant_pagelink );
				} else {
					// Fallback: try to use $locale, but check if key exists
					$em_text = '';
					if ( is_array( $relevant_post_link ) && isset( $relevant_post_link[ $locale ] ) ) {
						$em_text = is_array( $relevant_post_link[ $locale ] ) ? implode( '', $relevant_post_link[ $locale ] ) : $relevant_post_link[ $locale ];
					}
					$relevant_pagelinks = '<br><br><p><em>' . $em_text . '</em><p>' . implode( '</br>', $relevant_pagelink );
				}
			} else {
				$relevant_pagelinks = '';
			}

			// Gemini API expects a different payload and endpoint
			$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash' . ':generateContent';

			// Build formatted messages with system instructions
			$formatted_messages = [];
			
			// Add system instructions as first user message if context is enabled
			if ( ! empty( $system_instructions ) ) {
				$formatted_messages[] = [
					'role' => 'user',
					'parts' => [
						['text' => "[System Instructions] " . $system_instructions]
					]
				];
				
				// Add model response to acknowledge system instructions
				$formatted_messages[] = [
					'role' => 'model',
					'parts' => [
						['text' => "I understand and will follow these instructions."]
					]
				];
			}
			
			// Add the actual user query
			$formatted_messages[] = [
				'role' => 'user',
				'parts' => [
					['text' => $keyword]
				]
			];

			$data = array(
				'contents' => $formatted_messages,
			);

			// Use WordPress wp_remote_post for better error handling and consistency
			$args = array(
				'body'        => json_encode($data),
				'headers'     => array(
					'Content-Type' => 'application/json',
					'X-goog-api-key' => $gemini_api_key,
				),
				'timeout'     => 60,
				'redirection' => 5,
				'blocking'    => true,
				'httpversion' => '1.0',
				'sslverify'   => true,
			);

			$result = wp_remote_post($api_url, $args);
			
			// Check for WordPress errors
			if (is_wp_error($result)) {
				$response['status']  = 'error';
				$response['message'] = 'API request failed: ' . $result->get_error_message();
			} else {
				$http_code = wp_remote_retrieve_response_code($result);
				$response_body = wp_remote_retrieve_body($result);
				
				if ($http_code === 200) {
					$msg = json_decode($response_body);
					// Gemini API returns candidates[0]->content->parts[0]->text
					if (
						isset($msg->candidates[0]->content->parts[0]->text)
						&& !empty($msg->candidates[0]->content->parts[0]->text)
					) {
						$response['status']  = 'success';
						$response['message'] = $Qcld_Parsedown->text( $msg->candidates[0]->content->parts[0]->text ) . $relevant_pagelinks;
					} else {
						$response['status']  = 'error';
						$response['message'] = 'Invalid response format from Gemini API';
					}
				} else {
					$response['status']  = 'error';
					$response['message'] = 'API request failed with HTTP code: ' . $http_code;
				}
			}
			wp_send_json( $response );
			wp_die();
		}
        public function qcld_update_settings_option_callback(){
			// Verify nonce for CSRF protection
			$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
			if (!wp_verify_nonce($nonce, 'wp_chatbot')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed', 'chatbot')));
				wp_die();
			}
			
			// Check user capability - only administrators can modify settings
			if (!current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Unauthorized access', 'chatbot')));
				wp_die();
			}
			
			// Proceed with option updates
			update_option('disable_wp_chatbot_site_search', 1);
			update_option('enable_wp_chatbot_post_content', '');
			
			// Send success response
			wp_send_json_success(array('message' => esc_html__('Settings updated successfully', 'chatbot')));
			wp_die();
        }
    }

    /**
     * @return qcld_wpopenai_addon
     */
    if(!function_exists('qcld_wpgemini_addons')){
        function qcld_wpgemini_addons() {
            $qcld_wpgemini_addon = new qcld_wpgemini_addons();
            return $qcld_wpgemini_addon->instance();
        
        }
    }
  
    //fire off the plugin
    qcld_wpgemini_addons();

}