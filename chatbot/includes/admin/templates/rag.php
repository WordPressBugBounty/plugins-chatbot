<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * AI Assistant Template - RAG Feature Extended
 * @package Chatbot
 */

?>

<?php
// Owner, 10 November 2026: AI Settings > Knowledge Base shows the settings tabs; "What the chatbot knows"
// shows the knowledge base content table (the host sets the view before including this file).
$qcld_wpbot_rag_view = ( isset( $GLOBALS['qcld_wpbot_rag_view'] ) && 'known' === $GLOBALS['qcld_wpbot_rag_view'] ) ? 'known' : 'settings';
?>
    <style>
        .qcld-rag-view-settings a[href="#rag-database"], .qcld-rag-view-settings #rag-database { display: none !important; }
        .qcl-openai.qcld-rag-view-known .nav-tab-wrapper, .qcl-openai.qcld-rag-view-known .qcld-tab-content, .qcwp-kb .qcl-openai.qcld-rag-view-known .qcld-tab-content.active { display: none !important; }
        .qcl-openai.qcld-rag-view-known #rag-database, .qcwp-kb .qcl-openai.qcld-rag-view-known #rag-database.qcld-tab-content { display: block !important; }
    </style>
    <div class="qcl-openai qcld-rag-view-<?php echo esc_attr( $qcld_wpbot_rag_view ); ?>">
        <?php
        if ( 'settings' === $qcld_wpbot_rag_view ) {
            do_action( 'qcld_wpbot_chat/kb_settings_panel' ); // Status in one line, Sync now, Update vectors.
        }
        ?>



        <h2 class="nav-tab-wrapper">
            <a href="#qcld-rag-settings-tab" class="nav-tab nav-tab-active">Sources to include</a>
            <a href="#rag-sync" class="nav-tab">Upload and sync</a>
            <a href="#rag-sources" class="nav-tab">Manage sources</a>
            <a href="#rag-database" class="nav-tab">Knowledge Base content</a>
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
                    <input type="checkbox" id="rag_embed_faq" <?php checked( '0' !== (string) get_option( 'rag_embed_faq', '1' ) ); ?>>
                    <label for="rag_embed_faq"><?php esc_html_e( 'FAQ (ChatBot Settings > FAQ)', 'chatbot' ); ?></label>
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
            <?php qcld_wpbot_free_pro_lists( 'kb_sources', array( 'compact' => true ) ); ?>
            <div class="mb-4" style="background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;">
                <h4 style="margin-top: 0;"><i class="fa fa-tags"></i> Post Meta Data Settings <span class="pro-badge" style="display:inline-block; margin-left:8px; padding:2px 6px; background:#f0b429; color:#fff; border-radius:3px; font-size:11px; font-weight:bold; text-transform:uppercase;">PRO</span></h4>
                <div class="mb-3">
                    <input type="checkbox" id="rag_embed_meta" <?php checked(get_option('rag_embed_meta'), '1'); ?> disabled>
                    <label for="rag_embed_meta"><strong>Include Post Meta Data</strong></label>
                    <p class="description">Enable this to include custom fields/meta data in the embeddings.</p>
                </div>

                <div class="mb-3">
                    <label for="rag_embed_meta_keys"><strong>Meta Keys to Include (comma separated):</strong></label><br>
                    <input type="text" id="rag_embed_meta_keys" value="<?php echo esc_attr(get_option('rag_embed_meta_keys', '')); ?>" placeholder="e.g. _price, _sku, custom_field_key" style="width: 100%; max-width: 400px; margin-top: 5px;" disabled>
                    <p class="description">Specify the meta keys you want to include in the context. Leave empty to include all meta (not recommended).</p>
                </div>
            </div>

        </div>
        <div class="wrap"> 
            <h3>Sync Settings</h3>
                            
            <div class="mb-3">
                <input type="checkbox" id="rag_auto_sync_enabled" class="rag_auto_sync_enabled" <?php checked(get_option('rag_auto_sync_enabled'), '1'); ?>>
                <label for="rag_auto_sync_enabled"><strong>Enable Auto Sync (on Save)</strong></label>
                <p class="description">Automatically update embeddings when a post or product is saved/updated.</p>
            </div>
        </div>
       <!-- ===========================
            EXECUTION BUTTON
        ============================ -->
            <div class="wrap my-4">
                <p style="color: red"> <b><?php esc_html_e('Please connect to an AI service like OpenAI or Gemini before embedding. ', 'chatbot'); ?></b><b><a target="_blank" href="https://wpbot.pro/docs/knowledgebase/how-to-use-an-embedded-vector-database-and-rag-to-get-customized-responses-from-ai/"><?php esc_html_e('Check this Tutorial for more details.', 'chatbot'); ?></a></b></p>
                <form method="post" id="rag_embed_form">
                    <input type="hidden" name="embed_all_sources" value="1">
                    <button type="button" id="rag_embed_btn" class="button button-primary">Embed All Selected Sources</button>
                </form>

                <?php 
                    if (isset($_POST['embed_all_sources'])):
                        if( ( get_option( 'ai_enabled') == 1  && get_option('open_ai_api_key') ) || ( get_option('qcld_gemini_enabled') == 1 && get_option('qcld_gemini_api_key') ) || ( get_option('qcld_openrouter_enabled') == 1 && get_option('qcld_openrouter_api_key') ) ){
                ?>
                        <h3>Embedding started...</h3>
                        <?php  Qcld_Bot_Rag::instance()->wp_rag_embed_all_sources(); ?>
                 <?php    }else{ ?>
                    <Script>
                    swal.fire('', 'Please connect to an AI service like OpenAI or Gemini with API key before embedding.', 'warning');
                    </Script>
                <?php 
                    }
                endif;
                ?>
            </div>
                <!-- ===========================
            SAVE SETTINGS BUTTON
        ============================ -->
            <div class="wrap">
                <button class="qcld-btn-primary" id="save_rag_setting">Save Settings</button>
            </div>
        </div>
        <div id="rag-sync" class="qcld-tab-content">
                        
                        <div>
                               <!-- ===========================
                            SYNC SETTINGS
                        ============================ -->
                            <!-- ===========================
                            PDF UPLOAD (AJAX)
                        ============================ -->
                        <div class="wrap">
                            <h3>Sync Settings</h3>
                            
                            <div class="mb-3">
                                <input type="checkbox" id="rag_auto_sync_enabled_tab" class="rag_auto_sync_enabled" <?php checked(get_option('rag_auto_sync_enabled'), '1'); ?>>
                                <label for="rag_auto_sync_enabled_tab"><strong>Enable Auto Sync (on Save)</strong></label>
                                <p class="description">Automatically update embeddings when a post or product is saved/updated.</p>
                            </div>
                         <hr>


                        </div>
                        <div class="wrap">
                            <h3>Upload PDF for RAG</h3>

                            <form id="rag-pdf-form">
                                <input type="file" id="rag-pdf-files" name="rag_pdf[]" multiple accept="application/pdf" />
                                <br><br>
                                <button type="submit" class="button button-primary" id="rag-pdf-submit">Upload & Embed PDF</button>
                                <button type="button" class="button" id="rag-media-pdf-btn">Select PDF from Media Library</button>
                                <button type="button" class="button" id="qcld-rag-list-pdf-btn">List PDF Files from Media Library</button>
                                <span id="rag-pdf-status" style="margin-left: 10px;"></span>
                            </form>

                            <div id="qcld-pdf-list-container" style="display:none; margin-top:20px; border:1px solid #ddd; padding:15px; background:#fff;">
                                <h4>Available PDF Files in Media Library</h4>
                                <div id="qcld-pdf-items" style="max-height:300px; overflow-y:auto; margin-bottom:15px;">
                                    <p>Loading files...</p>
                                </div>
                                <button type="button" class="button button-primary qcld-rag-index-selected-media" data-type="pdf">Index Selected PDF Files</button>
                                <button type="button" class="button qcld-rag-close-media-list">Close List</button>
                            </div>

                            <div id="rag-pdf-output" style="margin-top: 15px;"></div>
                        </div>

                        <!-- ===========================
                            CSV UPLOAD (AJAX)
                        ============================ -->
                        <div class="wrap">
                            <h3>Upload CSV Data for RAG</h3>
                            <p>Upload CSV files with data to be embedded. Each row will be processed as a separate document. <a href="<?php echo esc_url( plugin_dir_url(__FILE__).'download/rag_test_data.csv' ); ?>">Download Test Data</a></p>

                            <form id="rag-csv-form">
                                <input type="file" id="rag-csv-files" name="rag_csv[]" multiple accept=".csv,text/csv" />
                                <br><br>
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
                                <br><br>
                                <button type="submit" class="button button-primary" id="rag-xaml-submit">Upload & Embed XAML</button>
                                <span id="rag-xaml-status" style="margin-left: 10px;"></span>
                            </form>

                            <div id="rag-xaml-output" style="margin-top: 15px;"></div>
                        </div>

                        </div>
                        <h5 style="color: #c33;">Notes: These are based on AI system, so make sure you have enabled any AI features and set up valid API keys. <b><a href="<?php echo esc_url('https://wpbot.pro/docs/knowledgebase/how-to-use-an-embedded-vector-database-and-rag-to-get-customized-responses-from-ai/'); ?>" target="_blank">Check this Tutorial for more details.</a></b></h5>
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

    <div id="rag-sources" class="qcld-tab-content">
                        
            <div>
            <div class="wrap">
                <h3>Manage Knowledge Base Sources</h3>
                <p>Manage entire files or spreadsheets instead of individual rows.</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="rag-sources-select-all"></th>
                            <th>Source Name / URL</th>
                            <th>Type</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rag-sources-list">
                        <?php
                        global $wpdb;
                        $table = $wpdb->prefix . 'rag_documents';
                        $sources = $wpdb->get_results("SELECT source_url, source_type, COUNT(*) as count, MAX(title) as display_name FROM {$table} GROUP BY source_url, source_type ORDER BY count DESC"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        
                        if ($sources) {
                            foreach ($sources as $source) {
                                $display_url = $source->source_url ?: "Manual Upload";
                                ?>
                                <tr data-source-url="<?php echo esc_attr($source->source_url); ?>">
                                    <th scope="row" class="check-column"><input type="checkbox" class="rag-source-checkbox" value="<?php echo esc_attr($source->source_url); ?>"></th>
                                    <td>
                                        <strong><?php echo esc_html($source->display_name); ?></strong><br>
                                        <small style="color:#888;"><?php echo esc_html($display_url); ?></small>
                                    </td>
                                    <td><span class="badge" style="background:#eee; padding:2px 5px; border-radius:3px;"><?php echo esc_html($source->source_type); ?></span></td>
                                    <td><?php echo esc_html($source->count); ?> chunks</td>
                                    <td>
                                        <button class="button rag-source-sync" data-url="<?php echo esc_attr($source->source_url); ?>" data-type="<?php echo esc_attr($source->source_type); ?>">Sync Now</button>
                                        <button class="button button-link-delete rag-source-delete" data-url="<?php echo esc_attr($source->source_url); ?>">Delete All</button>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="5">No sources found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <select id="rag-sources-bulk-action">
                            <option value="-1">Bulk Actions</option>
                            <option value="delete">Delete Selected Sources</option>
                        </select>
                        <button type="button" id="rag-sources-apply-bulk" class="button action">Apply</button>
                    </div>
                </div>
            </div>

        </div>
    </div>




        <div id="rag-database" class="qcld-tab-content">
                    <!-- ===========================
         KNOWLEDGE BASE MANAGEMENT
    ============================ -->
    <div class="wrap">
        <h3>Documents table (previous knowledge base)</h3>
        
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
                <form method="get" style="display:inline-block;" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>#rag-database">
                    <input type="hidden" name="page" value="wpbot_openAi">
                    <input type="hidden" name="section" value="knowledge">
                    <?php if (isset($_GET['post_type'])): // phpcs:ignore WordPress.Security.NonceVerification ?>
                        <input type="hidden" name="post_type" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?>">
                    <?php endif; ?>
                    <p class="search-box" style="margin:0;">
                        <input type="search" id="rag-search-input" name="s" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?>" placeholder="Search documents...">
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
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table_rag_documents))) === $table_rag_documents; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            
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
            
            $search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            
            if ($search_query) {
                $like = '%' . $wpdb->esc_like($search_query) . '%';
                $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table_name} WHERE title LIKE %s OR content LIKE %s", $like, $like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            } else {
                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$table_name}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            }
            $items_per_page = 50;
            $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
            $offset = ($page - 1) * $items_per_page;
            $total_pages = ceil($total_items / $items_per_page);

            $pagination_args = array(
                'base' => add_query_arg(array('page' => 'wpbot_openAi', 'section' => 'knowledge', 'paged' => '%#%', 's' => $search_query), admin_url('admin.php')) . '#rag-database',
                'format' => '',
                'prev_text' => __( '&laquo;', 'chatbot' ),
                'next_text' => __( '&raquo;', 'chatbot' ),
                'total' => $total_pages,
                'current' => $page,
                'type' => 'plain',
            );
           
            if ($total_pages > 1) {
                // translators: %s: number of items.
                echo '<div class="tablenav-pages"><span class="displaying-num">' . sprintf( esc_html( _n( '%s item', '%s items', $total_items, 'chatbot' ) ), esc_html( number_format_i18n($total_items) ) ) . '</span>';
                echo wp_kses_post( paginate_links($pagination_args) );
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
                if ($search_query) {
                    $like = '%' . $wpdb->esc_like($search_query) . '%';
                    $documents = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE title LIKE %s OR content LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $like, $like, $items_per_page, $offset)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                } else {
                    $documents = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d", $items_per_page, $offset)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                }

                if ($documents) {
                    foreach ($documents as $doc) {
                        ?>
                        <tr id="rag-doc-<?php echo esc_attr( $doc->id ); ?>">
                            <th scope="qcld-row" class="check-column">
                                <input type="checkbox" class="rag-doc-checkbox" value="<?php echo esc_attr( $doc->id ); ?>">
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
                                    <button class="button button-small rag-sync-doc" data-id="<?php echo esc_attr( $doc->id ); ?>" title="Re-sync data from source">Sync</button>
                                <?php endif; ?>
                                <button class="button button-small rag-edit-doc" data-id="<?php echo esc_attr( $doc->id ); ?>">Edit</button>
                                <button class="button button-small button-link-delete rag-delete-doc" data-id="<?php echo esc_attr( $doc->id ); ?>">Delete</button>
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
                // translators: %s: number of items.
                echo '<div class="tablenav-pages"><span class="displaying-num">' . sprintf( esc_html( _n( '%s item', '%s items', $total_items, 'chatbot' ) ), esc_html( number_format_i18n($total_items) ) ) . '</span>';
                echo wp_kses_post( paginate_links($pagination_args) );
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
		formData.append('nonce', '<?php echo esc_js( wp_create_nonce('rag_upload_nonce') ); ?>');
		
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
		formData.append('nonce', '<?php echo esc_js( wp_create_nonce('rag_upload_nonce') ); ?>');
		
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
		formData.append('nonce', '<?php echo esc_js( wp_create_nonce('rag_upload_nonce') ); ?>');
		
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