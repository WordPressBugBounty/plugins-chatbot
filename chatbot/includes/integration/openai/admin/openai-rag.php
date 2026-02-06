<div class="row g-0">
    <div class="col-sm-12">
          <div class="qcl-openai">

        <!-- ===========================
         RAG MAIN SWITCH
    ============================ -->
        <div class="wrap">
            <h3>OpenAI RAG Settings</h3>
            <p>If you enable RAG, you must configure the <a id="ai-knowledge-base-tab-openai" href="<?php echo admin_url('admin.php?page=wpbot_openAi#ai-knowledge-base-tab'); ?>">Knowledgebase</a> for Post types and other data to embed.</p>
            <div class="form-check form-switch my-4">
                <input class="form-check-input"
                    type="checkbox"
                    id="is_page_rag_enabled"
                    <?php echo (get_option('is_page_rag_enabled') == '1') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_page_rag_enabled">
                    Enable RAG
                </label>
            </div>
        </div>
        <div class="mb-3">
            <a class="btn btn-success" id="save_setting"><?php esc_html_e( 'Save settings','chatbot');?></a>
        </div>
    </div>
    <div class="qcld-row">
		<h2></h2>
		<div class="alert alert-info mt-20" role="alert">
			<p><b><?php echo esc_html__( 'Fine Tuning VS GPT Assistants VS RAG:', 'wpchatbot' ); ?></b></p>
			<p>
			<?php echo esc_html__( 'We suggest using GPT Assistants or RAG instead of Fine Tuning as Fine Tuning requires a lot of properly formatted data and GPT Assistants are easier to set up. You can still use your website data to train the bot.', 'wpchatbot' ); ?>
			</p></br>
			<p>
			<b><?php echo esc_html__( 'How to Use RAG in This Plugin:', 'wpchatbot' ); ?></b>
			<ol>
				<li><?php echo esc_html__( 'Enable RAG from the settings panel', 'wpchatbot' ); ?></li>
				<li><?php echo esc_html__( 'Click “Embed All Selected Sources” button, after selecting the sources from the', 'wpchatbot' ); ?> <a href="<?php echo admin_url('admin.php?page=wpbot_openAi#ai-knowledge-base-tab'); ?>" target="_blank">knowledgebase tab</a></li>
				<li><?php echo esc_html__( '(Optional) Upload PDFs or CSV files for embedding', 'wpchatbot' ); ?></li>
				<li><?php echo esc_html__( 'The system automatically stores embeddings in the database', 'wpchatbot' ); ?></li>
				<li><?php echo esc_html__( 'User questions will now be answered using your site’s knowledge base', 'wpchatbot' ); ?></li> 
				<li><?php echo esc_html__( 'You need to configure the OpenAI API key, AI Model and System Command under the main OpenAI Settings', 'wpchatbot' ); ?></li> 
			</ol>
			<strong><?php echo esc_html__( 'You can update or re-embed content at any time without retraining.', 'wpchatbot' ); ?></strong>
			</p>
			
		</div>

</div>	

    </div>
</div>
