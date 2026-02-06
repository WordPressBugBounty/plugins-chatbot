<?php
if ( ! class_exists( 'Qcld_Bot_Rag' ) ) {
    class Qcld_Bot_Rag{
        private $api_key;
        private $baseUrl;

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
            $this->check_api_endpoint();
            add_action('wp_ajax_rag_upload_pdf', array($this, 'ajax_rag_upload_pdf'));
			add_action('wp_ajax_rag_upload_csv', array($this, 'ajax_rag_upload_csv'));
			add_action('wp_ajax_rag_upload_xaml', array($this, 'ajax_rag_upload_xaml'));
			add_action('wp_ajax_qcld_rag_manual_sync', array($this, 'ajax_rag_manual_sync'));
			add_action('wp_ajax_qcld_rag_delete_document', array($this, 'qcld_rag_delete_document_callback'));
			add_action('wp_ajax_qcld_rag_get_document', array($this, 'qcld_rag_get_document_callback'));
			add_action('wp_ajax_qcld_rag_update_document', array($this, 'qcld_rag_update_document_callback'));
			add_action('wp_ajax_qcld_rag_bulk_delete_documents', array($this, 'qcld_rag_bulk_delete_documents_callback'));
			add_action('wp_ajax_qcld_rag_delete_all_documents', array($this, 'qcld_rag_delete_all_documents_callback'));
			add_action('save_post', array($this, 'wp_rag_handle_auto_sync_hook'), 10, 3);
        }

        public function check_api_endpoint() {
            if (get_option('is_page_rag_enabled') == '1') {
                $this->api_key = get_option('open_ai_api_key');
                $this->baseUrl = 'https://api.openai.com/v1/';

            }else if (get_option('qcld_gemini_rag_enabled') == '1') {
            
                $apikey = get_option('qcld_gemini_api_key') ;
                $this->api_key = $apikey;
                $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
            }else if (get_option('qcld_openrouter_rag_enabled') == '1') {
                $this->api_key = get_option('open_ai_api_key');
                $this->baseUrl = 'https://api.openai.com/v1/';
            }else{
                $this->api_key = '';
                $this->baseUrl = '';
            }

            if (empty($this->api_key)) {
                return new WP_Error('no_api_key', 'No API key is not set.');
            }
            return true;
        }
        public function generate_embedding($text) {
            if (get_option('is_page_rag_enabled') == '1' || get_option('qcld_openrouter_rag_enabled') == '1') {
                return $this->generate_openai_embedding($text);
            } else if (get_option('qcld_gemini_rag_enabled') == '1') {
                return $this->generate_gemini_embedding($text);
            }
        }
        public function generate_gemini_embedding($text, $model = 'text-embedding-004') {
            $url = "{$this->baseUrl}/models/{$model}:embedContent?key={$this->api_key}";
            
            $data = [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                // throw new Exception("Embedding failed (HTTP $httpCode): $response");
                return [];
            }
            
            $result = json_decode($response, true);
            if (isset($result['embedding']['values'])) {
                return $result['embedding']['values'];
            }
            
            // throw new Exception("No embedding in response: $response");
            return [];
        }
        // Generate OpenAI embedding for given text
        public function generate_openai_embedding($text) {
			

			$response = wp_remote_post(
				"https://api.openai.com/v1/embeddings",
				[
					'headers' => [
						'Authorization' => "Bearer {$this->api_key}",
						'Content-Type'  => 'application/json'
					],
					'body' => json_encode([
						'model' => 'text-embedding-3-large',
						'input' => mb_substr($text, 0, 15000) // keep within token limits
					])
				]
			);

			if (is_wp_error($response)) return [];

			$body = json_decode(wp_remote_retrieve_body($response), true);

			return $body['data'][0]['embedding'] ?? [];
		}
		// AJAX handler for PDF upload
		public function ajax_rag_upload_pdf() {
			check_ajax_referer('rag_upload_nonce', 'nonce');
			
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
					echo "<p>Error uploading: {$filename}</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: $filename</p>";

				$handle = fopen($file_path, 'r');
				if ($handle === false) {
					echo "<p style='color:red;'>Failed to open CSV file</p>";
					continue;
				}

				$header = fgetcsv($handle);
				if ($header === false) {
					echo "<p style='color:red;'>Empty CSV file</p>";
					fclose($handle);
					continue;
				}

				echo "<p>Columns: " . implode(', ', $header) . "</p>";
				
				global $wpdb;
				$table = $wpdb->prefix . "rag_documents";
				$row_count = 0;
				$success_count = 0;

				while (($row = fgetcsv($handle)) !== false) {
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
					if (empty($embedding)) {
						echo "<p style='color:red;'>Failed embedding for row $row_count</p>";
						continue;
					}

					$title = !empty($row[0]) ? substr($row[0], 0, 100) : "CSV Row $row_count";

					$result = $wpdb->insert($table, [
						'title'       => sanitize_text_field($title),
						'content'     => $content,
						'embedding'   => json_encode($embedding),
						'source_type' => 'csv',
						'source_url'  => $file_url,
						'file_url'    => $file_url,
						'status'      => 'complete',
						'metadata'    => json_encode(['filename' => $filename, 'row' => $row_count]),
						'created_at'  => current_time('mysql')
					]);

					if ($result !== false) {
						$success_count++;
					} else {
						echo "<p style='color:red;'>DB error row $row_count: " . $wpdb->last_error . "</p>";
					}
				}

				fclose($handle);
				echo "<p style='color:green;'>✓ Processed $success_count of $row_count rows</p>";
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
					echo "<p>Error uploading: {$filename}</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: $filename</p>";

				// Extract PDF text (uses Smalot/PdfParser)
				if (!class_exists('\Smalot\PdfParser\Parser')) {
					echo "<p>PDF parser missing! Install `smalot/pdfparser`.</p>";
					return;
				}

				$parser = new \Smalot\PdfParser\Parser();
				$pdf = $parser->parseFile($file_path);
				$text = $pdf->getText();

				echo "<p>Extracted text length: ".strlen($text)."</p>";

				// Generate Embedding
				$embedding = $this->generate_embedding($text);
				
				if (empty($embedding)) {
					echo "<p style='color:red;'>Failed to generate embedding for: $filename</p>";
					continue;
				}

				// Save to DB
				global $wpdb;
				$table = $wpdb->prefix . "rag_documents";

				$result = $wpdb->insert($table, [
					'title'       => sanitize_text_field($filename),
					'content'     => $text,
					'embedding'   => json_encode($embedding),
					'source_type' => 'pdf',
					'source_url'  => $file_url,
					'file_url'    => $file_url,
					'status'      => 'complete',
					'metadata'    => json_encode(['size' => $uploaded_files['size'][$index]]),
					'created_at'  => current_time('mysql')
				]);

				if ($result === false) {
					echo "<p style='color:red;'>Database error: " . $wpdb->last_error . "</p>";
				} else {
					echo "<p style='color:green;'>✓ Saved PDF embedding for: $filename (ID: " . $wpdb->insert_id . ")</p>";
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

				$upload = wp_handle_upload($file_array, [
					'test_form' => false,
					'test_type' => false, // Bypass mime type check
				]);

				if (isset($upload['error'])) {
					echo "<p>Error uploading {$filename}: " . $upload['error'] . "</p>";
					continue;
				}

				$file_url = $upload['url'];
				$file_path = $upload['file'];

				echo "<p>Uploaded: $filename</p>";

				// Read XAML/XML content
				$xml_content = file_get_contents($file_path);
				
				if (empty($xml_content)) {
					echo "<p style='color:red;'>Failed to read file or file is empty: $filename</p>";
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
					
					if (empty($embedding)) {
						echo "<p style='color:red;'>Failed to generate embedding for item: {$item_data['title']}</p>";
						continue;
					}

					// Save to DB
					global $wpdb;
					$table = $wpdb->prefix . "rag_documents";

					$result = $wpdb->insert($table, [
						'title'       => sanitize_text_field($item_data['title']),
						'content'     => $clean_content,
						'embedding'   => json_encode($embedding),
						'source_type' => 'xaml',
						'source_url'  => $item_data['source_url'] ? $item_data['source_url'] : $file_url,
						'file_url'    => $file_url,
						'status'      => 'complete',
						'metadata'    => json_encode(['size' => strlen($clean_content)]),
						'created_at'  => current_time('mysql')
					]);

					if ($result === false) {
						echo "<p style='color:red;'>Database error for item {$item_data['title']}: " . $wpdb->last_error . "</p>";
					} else {
						echo "<p style='color:green;'>✓ Saved embedding for: {$item_data['title']} (ID: " . $wpdb->insert_id . ")</p>";
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

				$wpdb->insert(
					$wpdb->prefix . "rag_documents",
					[
						"title" => $p->post_title,
						"content" => $content,
						"embedding" => json_encode($embedding)
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

				if (empty($embedding)) {
					echo "<li style='color:red;'>Failed to generate embedding for: " . esc_html($p->post_title) . "</li>";
					continue;
				}

				$table = $wpdb->prefix . "rag_documents";

				// Check if this post already exists in the database
				$existing = $wpdb->get_row($wpdb->prepare(
					"SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
					'%"post_id":' . $p->ID . '%',
					$p->post_type
				));

				$data = [
					"title"       => $p->post_title,
					"content"     => $content,
					"embedding"   => json_encode($embedding),
					"source_type" => $p->post_type,
					"source_url"  => get_permalink($p->ID),
					"file_url"    => get_permalink($p->ID),
					"status"      => 'complete',
					"metadata"    => json_encode(['post_id' => $p->ID]),
					"created_at"  => current_time('mysql')
				];

				if ($existing) {
					// Update existing record
					$wpdb->update(
						$table,
						$data,
						['id' => $existing->id]
					);
					echo "<li style='color:blue;'>✓ Updated: " . esc_html($p->post_title) . " (" . esc_html($p->post_type) . ")</li>";
					$updated_count++;
				} else {
					// Insert new record
					$wpdb->insert($table, $data);
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
                $str_results = $wpdb->get_results("SELECT * FROM $str_table");

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
                        if (empty($embedding)) {
                            echo "<li style='color:red;'>Failed to generate embedding for STR: " . esc_html($str->query) . "</li>";
                            continue;
                        }

                        // Check if this STR already exists in the RAG database
                        $existing = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM $table WHERE metadata LIKE %s AND source_type = %s",
                            '%"str_id":' . $str->id . '%',
                            'str'
                        ));

                        $data = [
                            "title"       => $str->query,
                            "content"     => $content,
                            "embedding"   => json_encode($embedding),
                            "source_type" => 'str',
                            "source_url"  => admin_url('admin.php?page=simple-text-response&action=edit&query=' . $str->id),
                            "file_url"    => '',
                            "status"      => 'complete',
                            "metadata"    => json_encode(['str_id' => $str->id]),
                            "created_at"  => current_time('mysql')
                        ];

                        if ($existing) {
                            $wpdb->update($table, $data, ['id' => $existing->id]);
                            echo "<li style='color:blue;'>✓ Updated STR: " . esc_html($str->query) . "</li>";
                            $updated_count++;
                        } else {
                            $wpdb->insert($table, $data);
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
			echo "<li>New entries created: <strong>$inserted_count</strong></li>";
			echo "<li>Existing entries updated: <strong style='color:blue;'>$updated_count</strong></li>";
			echo "<li>Skipped (too short): <strong>$skipped_count</strong></li>";
			echo "</ul>";
		}
		public function wp_rag_create_embedding($text, $apiKey)
		{
            
            $response = $this->generate_embedding($text);
			 return $response;
		}
        public function ajax_rag_manual_sync() {
            check_ajax_referer('wp_chatbot', 'nonce');
            
            $doc_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if (!$doc_id) {
                wp_send_json_error(['message' => 'Invalid document ID']);
            }

            global $wpdb;
            $table = $wpdb->prefix . 'rag_documents';
            $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $doc_id));

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
			
			$deleted = $wpdb->delete($table_name, array('id' => $id));
			
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
			$deleted = $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_string)");
			
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
			
			$deleted = $wpdb->query("TRUNCATE TABLE $table_name");
			
			// Some DBs might not support TRUNCATE on tables with foreign keys or other constraints, 
			// though rag_documents is likely simple. Fallback to DELETE.
			if ($deleted === false) {
				$deleted = $wpdb->query("DELETE FROM $table_name");
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
			
			$document = $wpdb->get_row($wpdb->prepare("SELECT id, title, content FROM $table_name WHERE id = %d", $id));
			
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
			$id = intval($_POST['id']);
			$title = sanitize_text_field($_POST['title']);
			$content = sanitize_textarea_field($_POST['content']);
			$table_name = $wpdb->prefix . 'rag_documents';
			
			// Re-generate embedding if content changed
			$old_content = $wpdb->get_var($wpdb->prepare("SELECT content FROM $table_name WHERE id = %d", $id));
			
			$update_data = array(
				'title' => $title,
				'content' => $content,
				'status' => 'complete'
			);

			if ($old_content !== $content) {
				$embedding = $this->generate_embedding($content);
				if (!empty($embedding)) {
					$update_data['embedding'] = json_encode($embedding);
				} else {
					$update_data['status'] = 'error';
				}
			}

			$updated = $wpdb->update($table_name, $update_data, array('id' => $id));
			
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
		if (empty($embedding)) {
			return new WP_Error('embedding_failed', 'Failed to generate embedding');
		}

		global $wpdb;
		$table = $wpdb->prefix . "rag_documents";

		// Check if it already exists (by source_url or custom metadata if we had it)
		$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE source_url = %s", $url));

		if ($existing) {
			$result = $wpdb->update($table, [
				'title'      => sanitize_text_field($title),
				'content'    => $content,
				'embedding'  => json_encode($embedding),
				'status'     => 'complete',
				'created_at' => current_time('mysql')
			], ['id' => $existing->id]);
		} else {
			$result = $wpdb->insert($table, [
				'title'       => sanitize_text_field($title),
				'content'     => $content,
				'embedding'   => json_encode($embedding),
				'source_type' => ($post->post_type === 'page' || $post->post_type === 'post') ? $post->post_type : 'xaml',
				'source_url'  => $url,
				'file_url'    => $url,
				'status'      => 'complete',
				'metadata'    => json_encode(['post_id' => $post_id, 'post_type' => $post->post_type]),
				'created_at'  => current_time('mysql')
			]);
		}

		return $result;
	}
		public function wp_rag_handle_auto_sync_hook($post_id, $post, $update) {
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
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    public function run_rag_search($user_query, $top_k = 3) {
        global $wpdb;
        $table = $wpdb->prefix . "rag_documents";

        // Get all embeddings and texts
        $rows = $wpdb->get_results("SELECT content, embedding FROM $table WHERE status = 'complete'", ARRAY_A);

        if (empty($rows)) {
            return "No knowledge base found.";
        }

        // Step 1: Get embedding for user query
        $query_vector = $this->generate_embedding($user_query);

        if (empty($query_vector)) {
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

    }
}
if ( ! function_exists( 'Qcld_Bot_Rag' ) ) {

    function Qcld_Bot_Rag() {
        return Qcld_Bot_Rag::instance();
    }
}

// fire off the plugin.
Qcld_Bot_Rag();
