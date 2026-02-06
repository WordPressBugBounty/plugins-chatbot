<?php

/**
 * AI Assistant Template - RAG Feature Extended
 * @package Botmaster
 */

$wpchatbot_license_valid            = get_option('wpchatbot_license_valid');
// $wpchatbot_license_valid            = 'starter';

?>

    <div class="qcl-openai">



        <h2 class="nav-tab-wrapper">
            <a href="#qcld-rag-settings-tab" class="nav-tab nav-tab-active"> Setting options</a>
            <a href="#rag-sync" class="nav-tab">Sync and upload options</a>
            <a href="#rag-database" class="nav-tab">KnowledgeBase Database</a>
        </h2>

        <div id="qcld-rag-settings-tab" class="qcld-tab-content active">
            <!-- ===========================
         EMBEDDING SOURCE OPTIONS
    ============================ -->
        <div class="wrap">
                <h3>Choose Data Sources to Embed</h3>

                <div class="mb-3">
                    <input type="checkbox" id="rag_embed_pages" <?php checked(get_option('rag_embed_pages'), '1'); ?>>
                    <label for="rag_embed_pages">Pages</label>
                </div>

                <div class="mb-3">
                    <input type="checkbox" id="rag_embed_posts" <?php checked(get_option('rag_embed_posts'), '1'); ?>>
                    <label for="rag_embed_posts">Posts</label>
            </div>
            <div class="mb-3">
                    <input type="checkbox" id="rag_embed_str" <?php checked(get_option('rag_embed_str'), '1'); ?>>
                    <label for="rag_embed_str">Simple Text Responses</label>
            </div>
            <div class="mb-3">
                <?php
                $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
                $selected_cpts = get_option('rag_embed_cpts', []);
                ?>

                <label><strong>Custom Post Types:</strong></label><br>

                <div class="rag_embed_cpts_wrapper">
                    <?php foreach ($custom_post_types as $cpt): ?>
                        <div class="rag_cpt_checkbox">
                            <input type="checkbox" class="rag_embed_cpts_checkbox" 
                                   id="rag_cpt_<?php echo esc_attr($cpt->name); ?>" 
                                   value="<?php echo esc_attr($cpt->name); ?>"
                                   <?php echo in_array($cpt->name, $selected_cpts) ? 'checked' : ''; ?>>
                            <label for="rag_cpt_<?php echo esc_attr($cpt->name); ?>"><?php echo esc_html($cpt->label); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="description">Select multiple custom post types to embed.</p>
            </div>


        </div>
                <!-- ===========================
            EXECUTION BUTTON
        ============================ -->
            <div class="wrap my-4">
                <form method="post" id="rag_embed_form">
                    <input type="hidden" name="embed_all_sources" value="1">
                    <button type="button" id="rag_embed_btn" class="button button-primary">Embed All Selected Sources</button>
                </form>

                <?php if (isset($_POST['embed_all_sources'])): ?>
                    <h3>Embedding started...</h3>
                    <?php Qcld_Bot_Rag::instance()->wp_rag_embed_all_sources(); ?>
                <?php endif; ?>
            </div>
                <!-- ===========================
            SAVE SETTINGS BUTTON
        ============================ -->
            <div class="wrap">
                <button class="qcld-btn-primary" id="save_rag_setting">Save Settings</button>
            </div>
        </div>


        <div id="rag-sync" class="qcld-tab-content">
                        <?php if ( $wpchatbot_license_valid != 'master' && $wpchatbot_license_valid != 'professional'): ?>
                            <div class="wrap">
                                <div style="background-color: #fee; border: 1px solid #c33; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="color: #c33; font-weight: bold; margin: 0; font-size: 14px;">
                                        These options are available with the WPBot Pro <a href="https://www.wpbot.pro/pricing/" target="_blank" style="color: #c33; text-decoration: underline;">Professional</a> and <a href="https://www.wpbot.pro/pricing/" target="_blank" style="color: #c33; text-decoration: underline;">Master</a> Licenses
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="<?php if ( $wpchatbot_license_valid != 'master' && $wpchatbot_license_valid != 'professional'){ echo 'opacity:0.5; pointer-events:none;'; } ?>">
                               <!-- ===========================
                            SYNC SETTINGS
                        ============================ -->
                        <div class="wrap">
                            <h3>Sync Settings</h3>

                            <div class="mb-3">
                                <input type="checkbox" id="rag_auto_sync_enabled" <?php checked(get_option('rag_auto_sync_enabled'), '1'); ?>>
                                <label for="rag_auto_sync_enabled"><strong>Enable Auto Sync (on Save)</strong></label>
                                <p class="description">Automatically update embeddings when a post or product is saved/updated.</p>
                            </div>

                        </div>


                            <!-- ===========================
                            PDF UPLOAD (AJAX)
                        ============================ -->
                        <div class="wrap">
                            <h3>Upload PDF for RAG</h3>

                            <form id="rag-pdf-form">
                                <input type="file" id="rag-pdf-files" name="rag_pdf[]" multiple accept="application/pdf" />
                                <br>
                                <button type="submit" class="button button-primary" id="rag-pdf-submit">Upload & Embed PDF</button>
                                <span id="rag-pdf-status" style="margin-left: 10px;"></span>
                            </form>

                            <div id="rag-pdf-output" style="margin-top: 15px;"></div>
                        </div>

                        <!-- ===========================
                            CSV UPLOAD (AJAX)
                        ============================ -->
                        <div class="wrap">
                            <h3>Upload CSV Data for RAG</h3>
                            <p>Upload CSV files with data to be embedded. Each row will be processed as a separate document. <a href="<?php echo plugin_dir_url(__FILE__).'download/rag_test_data.csv'; ?>">Download Test Data</a></p>

                            <form id="rag-csv-form">
                                <input type="file" id="rag-csv-files" name="rag_csv[]" multiple accept=".csv,text/csv" />
                                <br>
                                <button type="submit" class="button button-primary" id="rag-csv-submit">Upload & Embed CSV</button>
                                <span id="rag-csv-status" style="margin-left: 10px;"></span>
                            </form>

                            <div id="rag-csv-output" style="margin-top: 15px;"></div>
                        </div>

                        <!-- ===========================
                            XAML UPLOAD (AJAX)
                        ============================ -->
                        <div class="wrap">
                            <h3>Upload XML Data for RAG</h3>
                            <p>Upload XML files with data to be embedded.</p>

                            <form id="rag-xaml-form">
                                <input type="file" id="rag-xaml-files" name="rag_xaml[]" multiple accept=".xaml,text/xml,application/xml" />
                                <br>
                                <button type="submit" class="button button-primary" id="rag-xaml-submit">Upload & Embed XAML</button>
                                <span id="rag-xaml-status" style="margin-left: 10px;"></span>
                            </form>

                            <div id="rag-xaml-output" style="margin-top: 15px;"></div>
                        </div>

                            <!-- ===========================
                            SITEMAP SUBMISSION
                        ============================ -->
                        <div class="wrap">
                            <h3>Submit Sitemap for RAG</h3>
                            <p>Enter your XML Sitemap URL to crawl and embed all pages.</p>
                            <input type="url" id="botmaster_sitemap_url" class="regular-text" placeholder="https://example.com/sitemap.xml" style="width: 100%; max-width: 400px;">
                            <button type="button" id="botmaster_submit_sitemap_btn" class="button button-primary">Process Sitemap</button>
                            <div id="botmaster_sitemap_status" style="margin-top: 10px;"></div>
                        </div>
                        </div>
                        
                        <div class="wrap">
                            <button class="qcld-btn-primary" id="save_rag_setting">Save Settings</button>
                        </div>
        </div>

                    <style>
                .qcld-tab-content {
                    display: none !important;
                }

                .qcld-tab-content.active {
                    display: block !important;
                }
                    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#botmaster_submit_sitemap_btn').on('click', function() {
            var sitemapUrl = $('#botmaster_sitemap_url').val();
            var statusDiv = $('#botmaster_sitemap_status');
            
            if (!sitemapUrl) {
                alert('Please enter a Sitemap URL');
                return;
            }
            
            statusDiv.html('Processing... please wait.');
            $(this).prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'botmaster_submit_sitemap',
                sitemap_url: sitemapUrl,
                nonce: '<?php echo wp_create_nonce("botmaster_kb_nonce"); ?>'
            }, function(response) {
                $('#botmaster_submit_sitemap_btn').prop('disabled', false);
                if (response.success) {
                    statusDiv.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    statusDiv.html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            });
        });
    });
    </script>





        <div id="rag-database" class="qcld-tab-content">
                    <!-- ===========================
         KNOWLEDGE BASE MANAGEMENT
    ============================ -->
    <div class="wrap">
        <h3>Knowledge Base</h3>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select id="rag-bulk-action-selector">
                    <option value="-1">Bulk Actions</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="rag-apply-bulk-action" class="button action">Apply</button>
            </div>
            <div class="alignleft actions">
                <button type="button" id="rag-delete-all" class="button button-link-delete" style="margin-left: 10px;">Delete All</button>
           
            
            <div class="alignright actions" style="margin-left: 10px;">
                <form method="get" style="display:inline-block;" action="?page=wpbot_openAi#ai-knowledge-base-tab#rag-database">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    <?php if (isset($_GET['post_type'])): ?>
                        <input type="hidden" name="post_type" value="<?php echo esc_attr($_GET['post_type']); ?>">
                    <?php endif; ?>
                    <p class="search-box" style="margin:0;">
                        <input type="search" id="rag-search-input" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="Search documents...">
                        <input type="submit" id="search-submit" class="button" value="Search">
                    </p>
                </form>
                </div>
            </div>
            
            <br class="clear">
        </div>
                    <?php
            global $wpdb;
            $table_rag_documents = $wpdb->prefix . 'rag_documents';

            // Handle Table Creation
            if (isset($_POST['rag_create_table']) && check_admin_referer('rag_create_table_nonce')) {
                $charset = $wpdb->get_charset_collate();
                $sql_rag_documents = "CREATE TABLE $table_rag_documents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    doc_id VARCHAR(100) DEFAULT NULL,
                    title VARCHAR(255) NOT NULL,
                    content LONGTEXT NOT NULL,
                    embedding LONGTEXT NOT NULL,
                    source_type VARCHAR(20) DEFAULT 'post', 
                    source_url VARCHAR(255) DEFAULT NULL,
                    file_url TEXT DEFAULT NULL,
                    metadata LONGTEXT DEFAULT NULL,
                    status VARCHAR(50) DEFAULT 'complete',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) $charset;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql_rag_documents);
                echo '<div class="notice notice-success inline"><p>Database table created successfully.</p></div>';
            }

            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_rag_documents'") === $table_rag_documents;
            
            if (!$table_exists) {
                ?>
                <div class="wrap">
                    <h3>Knowledge Base Database</h3>
                    <div class="notice notice-warning inline">
                        <p>The <strong><?php echo esc_html($table_rag_documents); ?></strong> table does not exist. Please create it to start using the Knowledge Base.</p>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field('rag_create_table_nonce'); ?>
                        <input type="hidden" name="rag_create_table" value="1">
                        <button type="submit" class="button button-primary">Create Database Table</button>
                    </form>
                </div>
                <?php
            } else {
            ?>
                    <?php
            $table_name = $table_rag_documents;
            
            $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $where_clause = '';
            if ($search_query) {
                $where_clause = $wpdb->prepare(" WHERE title LIKE %s OR content LIKE %s ", '%' . $wpdb->esc_like($search_query) . '%', '%' . $wpdb->esc_like($search_query) . '%');
            }

            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_clause");
            $items_per_page = 50;
            $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
            $offset = ($page - 1) * $items_per_page;
            $total_pages = ceil($total_items / $items_per_page);

            $pagination_args = array(
                'base' => add_query_arg(array('paged' => '%#%', 's' => $search_query), '?page=wpbot_openAi') . '#ai-knowledge-base-tab#rag-database',
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $page,
                'type' => 'plain',
            );
           
            if ($total_pages > 1) {
                echo '<div class="tablenav-pages"><span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)) . '</span>';
                echo paginate_links($pagination_args);
                echo '</div>';
            }
            ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" id="rag-select-all">
                    </td>
                    <th>Title</th>
                    <th>Content</th>
                    <th>Source Type</th>
                    <th>URL/File</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="rag-knowledge-base-list">
                <?php
                $documents = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", $items_per_page, $offset));

                if ($documents) {
                    foreach ($documents as $doc) {
                        ?>
                        <tr id="rag-doc-<?php echo $doc->id; ?>">
                            <th scope="qcld-row" class="check-column">
                                <input type="checkbox" class="rag-doc-checkbox" value="<?php echo $doc->id; ?>">
                            </th>
                            <td><?php echo esc_html($doc->title); ?></td>
                            <td>
                                <div style="max-height: 4.5em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; line-height: 1.5em; font-size: 13px; color: #666;">
                                    <?php echo wp_kses_post($doc->content); ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($doc->source_type); ?></td>
                            <td><?php echo esc_html($doc->source_url ?: $doc->file_url); ?></td>
                            <td><?php echo esc_html($doc->status); ?></td>
                            <td>
                                <?php if (!in_array($doc->source_type, ['csv', 'xml', 'xaml','sitemap'])): ?>
                                    <button class="button button-small rag-sync-doc" data-id="<?php echo $doc->id; ?>" title="Re-sync data from source">Sync</button>
                                <?php endif; ?>
                                <button class="button button-small rag-edit-doc" data-id="<?php echo $doc->id; ?>">Edit</button>
                                <button class="button button-small button-link-delete rag-delete-doc" data-id="<?php echo $doc->id; ?>">Delete</button>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="7">No documents found in knowledge base.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <?php
            if ($total_pages > 1) {
                echo '<div class="tablenav-pages"><span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)) . '</span>';
                echo paginate_links($pagination_args);
                echo '</div>';
            }
            ?>
            <br class="clear">
        </div>
        <?php } ?>
    </div>
        </div>

    <!-- Edit Document Modal -->
    <div id="rag-edit-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:10% auto; padding:20px; width:60%; border-radius:5px;">
            <h4>Edit Knowledge Base Document</h4>
            <input type="hidden" id="edit-doc-id">
            <div class="mb-3">
                <label>Title</label><br>
                <input type="text" id="edit-doc-title" class="regular-text" style="width:100%;">
            </div>
            <div class="mb-3">
                <label>Content</label><br>
                <textarea id="edit-doc-content" rows="10" style="width:100%;"></textarea>
            </div>
            <p class="description">Note: Updating content will re-generate embeddings.</p>
            <div class="mt-3">
                <button id="save-edit-doc" class="button button-primary">Save Changes</button>
                <button id="close-edit-modal" class="button">Cancel</button>
            </div>
        </div>
    </div>
    </div>



<script>
jQuery(document).ready(function($) {
	// PDF Upload Handler
	$('#rag-pdf-form').on('submit', function(e) {
		e.preventDefault();
		
		var fileInput = $('#rag-pdf-files')[0];
		if (!fileInput.files.length) {
			alert('Please select PDF files to upload');
			return;
		}
		
		var formData = new FormData();
		for (var i = 0; i < fileInput.files.length; i++) {
			formData.append('rag_pdf[]', fileInput.files[i]);
		}
		formData.append('action', 'rag_upload_pdf');
		formData.append('nonce', '<?php echo wp_create_nonce('rag_upload_nonce'); ?>');
		
		$('#rag-pdf-submit').prop('disabled', true);
		$('#rag-pdf-status').html('<span class="spinner is-active" style="float:none;"></span> Uploading and processing...');
		$('#rag-pdf-output').html('');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				$('#rag-pdf-submit').prop('disabled', false);
				if (response.success) {
					$('#rag-pdf-status').html('<span style="color:green;">✓ Complete</span>');
					$('#rag-pdf-output').html(response.data.output);
					$('#rag-pdf-files').val('');
				} else {
					$('#rag-pdf-status').html('<span style="color:red;">✗ Error</span>');
					$('#rag-pdf-output').html('<p style="color:red;">' + response.data.message + '</p>');
				}
			},
			error: function(xhr) {
				$('#rag-pdf-submit').prop('disabled', false);
				$('#rag-pdf-status').html('<span style="color:red;">✗ Error</span>');
				$('#rag-pdf-output').html('<p style="color:red;">Upload failed. Please try again.</p>');
			}
		});
	});
	
	// CSV Upload Handler
	$('#rag-csv-form').on('submit', function(e) {
		e.preventDefault();
		
		var fileInput = $('#rag-csv-files')[0];
		if (!fileInput.files.length) {
			alert('Please select CSV files to upload');
			return;
		}
		
		var formData = new FormData();
		for (var i = 0; i < fileInput.files.length; i++) {
			formData.append('rag_csv[]', fileInput.files[i]);
		}
		formData.append('action', 'rag_upload_csv');
		formData.append('nonce', '<?php echo wp_create_nonce('rag_upload_nonce'); ?>');
		
		$('#rag-csv-submit').prop('disabled', true);
		$('#rag-csv-status').html('<span class="spinner is-active" style="float:none;"></span> Uploading and processing...');
		$('#rag-csv-output').html('');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				$('#rag-csv-submit').prop('disabled', false);
				if (response.success) {
					$('#rag-csv-status').html('<span style="color:green;">✓ Complete</span>');
					$('#rag-csv-output').html(response.data.output);
					$('#rag-csv-files').val('');
				} else {
					$('#rag-csv-status').html('<span style="color:red;">✗ Error</span>');
					$('#rag-csv-output').html('<p style="color:red;">' + response.data.message + '</p>');
				}
			},
			error: function(xhr) {
				$('#rag-csv-submit').prop('disabled', false);
				$('#rag-csv-status').html('<span style="color:red;">✗ Error</span>');
				$('#rag-csv-output').html('<p style="color:red;">Upload failed. Please try again.</p>');
			}
		});
	});

	// XAML Upload Handler
	$('#rag-xaml-form').on('submit', function(e) {
		e.preventDefault();
		
		var fileInput = $('#rag-xaml-files')[0];
		if (!fileInput.files.length) {
			alert('Please select XAML files to upload');
			return;
		}
		
		var formData = new FormData();
		for (var i = 0; i < fileInput.files.length; i++) {
			formData.append('rag_xaml[]', fileInput.files[i]);
		}
		formData.append('action', 'rag_upload_xaml');
		formData.append('nonce', '<?php echo wp_create_nonce('rag_upload_nonce'); ?>');
		
		$('#rag-xaml-submit').prop('disabled', true);
		$('#rag-xaml-status').html('<span class="spinner is-active" style="float:none;"></span> Uploading and processing...');
		$('#rag-xaml-output').html('');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				$('#rag-xaml-submit').prop('disabled', false);
				if (response.success) {
					$('#rag-xaml-status').html('<span style="color:green;">✓ Complete</span>');
					$('#rag-xaml-output').html(response.data.output);
					$('#rag-xaml-files').val('');
				} else {
					$('#rag-xaml-status').html('<span style="color:red;">✗ Error</span>');
					$('#rag-xaml-output').html('<p style="color:red;">' + response.data.message + '</p>');
				}
			},
			error: function(xhr) {
				$('#rag-xaml-submit').prop('disabled', false);
				$('#rag-xaml-status').html('<span style="color:red;">✗ Error</span>');
				$('#rag-xaml-output').html('<p style="color:red;">Upload failed. Please try again.</p>');
			}
		});
	});
});
</script>
<script>
jQuery(document).ready(function($) {
    // Tab switching logic
    $('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
        var $tab = $(this);
        var targetId = $tab.attr('href');
        var $content = $(targetId);

        if ($tab.data('disabled')) {
            e.preventDefault();
            return false;
        }

        if ($content.length) {
            e.preventDefault();
            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update content area
            $('.qcld-tab-content').removeClass('active');
            $content.addClass('active');

            // Update URL hash without jumping
            var compositeHash = '#ai-knowledge-base-tab' + targetId;
            if (history.pushState) {
                history.pushState(null, null, compositeHash);
            } else {
                window.location.hash = compositeHash;
            }
        }
    });

    // Handle hash on page load
    var hash = window.location.hash;
    if (hash && hash.includes('#rag-')) {
        var subTabHash = '#' + hash.split('#').pop();
        var $targetTab = $('.nav-tab-wrapper a[href="' + subTabHash + '"]');
        if ($targetTab.length) {
            $targetTab.trigger('click');
        }
    } else if (hash === '#ai-knowledge-base-tab') {
        // Default to first tab if no sub-tab specified
        $('.nav-tab-wrapper a').first().trigger('click');
    }
});
</script>
