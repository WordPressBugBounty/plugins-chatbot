<div class="accordion" id="qcldopenaiaccordion">
	<div class="card">
		<div class="card-header" id="panelsStayOpen-headingZero-gemini">
			<h2 class="mb-0">
				<button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#panelsStayOpen-collapseZero-gemini" aria-expanded="true" aria-controls="panelsStayOpen-collapseZero-gemini">
					<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
esc_html_e( 'Getting Started with Grok', 'chatbot'); ?>
				</button>
			</h2>
		</div>
		<div id="panelsStayOpen-collapseZero-gemini" class="collapse show" aria-labelledby="panelsStayOpen-headingZero-gemini" data-parent="#qcldopenaiaccordion">
			<div class="card-body">
				
					<?php esc_html_e( 'Please make sure that DialogFlow and OpenAI are disabled if you want Gemini to work.', 'chatbot'); ?></br></br>
					<b><?php esc_html_e( 'Please go to the following link after logging in:', 'chatbot'); ?></b></br>
					<a href="https://console.x.ai/team/default/api-keys" target="_blank"><?php esc_html_e( 'https://console.x.ai/team/default/api-keys', 'chatbot'); ?></a></br>
					<?php esc_html_e( 'Create a new API key, then add it to the settings and save the changes.
					Once saved, Grok will be ready to use.', 'chatbot'); ?></br>
					<?php esc_html_e( 'Add credits/billing (required to actually use it)', 'chatbot'); ?></br>
					<?php esc_html_e( 'The API is pay-per-use (very low cost per token).', 'chatbot'); ?></br>
					<?php esc_html_e( 'There used to be $25 free monthly credits during the 2024 beta, but check current status in the console under billing → you almost certainly need to add a payment method now.', 'chatbot'); ?></br>
					<?php esc_html_e( 'Without credits the key exists but requests will fail.', 'chatbot'); ?></br></br>
					
					
					<b><?php esc_html_e( 'Steps to Create a Collection (Easiest Way:', 'chatbot'); ?></b></br> <?php esc_html_e( 'via Web Console)
					Go to the xAI Console:', 'chatbot'); ?><a href="https://console.x.ai/" target="_blank"><?php esc_html_e( 'https://console.x.ai/', 'chatbot'); ?></a><?php esc_html_e( '(Log in with your X account or the same credentials you use for Grok.)', 'chatbot'); ?></br>


					<?php esc_html_e( 'Make sure you have an API key already (if not, go to API Keys section → Create one, as covered before).', 'chatbot'); ?></br></br>
					<b><?php esc_html_e( 'Navigate to the Collections tab/section:', 'chatbot'); ?></b></br>
					<?php esc_html_e( 'It`s usually in the sidebar or main dashboard (look for "Collections", "Files", or "Knowledge Bases").', 'chatbot'); ?></br></br>
					<b><?php esc_html_e( 'Direct path if available:', 'chatbot'); ?></b></br> 
					<a href="https://console.x.ai/collections" target="_blank"><?php esc_html_e( 'https://console.x.ai/collections', 'chatbot'); ?></a><?php esc_html_e( '(or similar—check the menu after login).', 'chatbot'); ?></br>
					<?php esc_html_e( 'Click Create Collection (or "New Collection" / "+ Create").', 'chatbot'); ?></br></br>
					<b><?php esc_html_e( 'Fill in the details:', 'chatbot'); ?></b></br>
				    <?php esc_html_e( 'Name: Give it a clear name (e.g., "Research Papers", "Company Docs", "Codebase 2026").', 'chatbot'); ?></br></br>
					<b><?php esc_html_e( 'Description (optional): Brief note about what`s inside.', 'chatbot'); ?></b></br>
					<?php esc_html_e( 'Embedding/Index settings (optional): Choose defaults or select an embedding model (like grok-embedding-small) so Grok can vectorize/search the content automatically.', 'chatbot'); ?></br>
					<?php esc_html_e( 'Some options for chunk size/overlap if advanced.', 'chatbot'); ?></br>
					<?php esc_html_e( 'Save/create it. → You`ll get a collection ID.', 'chatbot'); ?></br>
				</p>
			</div>
		</div>
	</div>
</div>