<?php
/**
 * AI Setup Wizard Popup Template
 * Scoped styling and JS to avoid collision.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all custom post types for Step 7
$custom_post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
$show_wizard_automatically = isset( $show_wizard_automatically ) ? $show_wizard_automatically : false;
?>
<div id="wpbot-wizard-overlay" class="wpbot-wizard-overlay">
	<div class="wpbot-wizard-modal">
		<!-- Progress Bar -->
		<div class="wpbot-wizard-progress-container">
			<div id="wpbot-wizard-progress-bar" class="wpbot-wizard-progress-bar" style="width: 25%;"></div>
		</div>

		<div class="wpbot-wizard-header">
			<h2 class="wpbot-wizard-title">AI Setup Wizard</h2>
			<p class="wpbot-wizard-subtitle">Quickly configure your AI Chatbot in a few steps</p>
			<button type="button" class="wpbot-wizard-close-btn" id="wpbot-wizard-close">&times;</button>
		</div>

		<div class="wpbot-wizard-body">
			<form id="wpbot-wizard-form">
				<!-- Step 1: Select AI Service -->
				<div class="wpbot-wizard-step active" data-step="1">
					<h3>Step 1: Choose your AI Service</h3>
					<p class="wpbot-step-desc">Select which artificial intelligence provider you want to power your chatbot responses.</p>
					<div class="wpbot-wizard-field">
						<label for="wizard_ai_provider">AI Provider</label>
						<select id="wizard_ai_provider" name="ai_provider" class="wpbot-wizard-select">
							<option value="openai">OpenAI</option>
							<option value="gemini">Gemini</option>
							<option value="grok">Grok</option>
							<option value="openrouter">OpenRouter</option>
						</select>
					</div>
				</div>

				<!-- Step 2: Setup API Key -->
				<div class="wpbot-wizard-step" data-step="2">
					<h3>Step 2: API Credentials</h3>
					<p class="wpbot-step-desc" id="wizard_credentials_desc">Configure the connection credentials for your selected AI service.</p>
					
					<!-- API Key Input (for standard providers) -->
					<div class="wpbot-wizard-field" id="wizard_api_key_container">
						<label for="wizard_api_key" id="wizard_api_key_label">API Key</label>
						<input type="password" id="wizard_api_key" name="api_key" class="wpbot-wizard-input" placeholder="sk-..." />
						<p class="wpbot-field-help" id="wizard_api_key_help">Requires an active key from your provider.</p>
						<p class="wpbot-field-help" id="wizard_api_key_paid_note" style="color: #ef4444; font-weight: 600; margin-top: 8px;">Note: A Paid API key is required.</p>
					</div>

					<!-- API verification status elements -->
					<div id="wizard-api-loader" style="display: none; margin-top: 15px; text-align: center; color: #ffffff;">
						<div class="wpbot-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(255,255,255,0.1); border-radius: 50%; border-top-color: #6366f1; animation: wpbotSpin 1s ease-in-out infinite; margin-right: 8px; vertical-align: middle;"></div>
						<span style="font-size: 14px; font-weight: 500; vertical-align: middle;">Verifying API Connection...</span>
					</div>
					<div id="wizard-api-error" style="display: none; margin-top: 15px; padding: 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; color: #f87171; font-size: 13px; line-height: 1.4;">
					</div>
				</div>

				<!-- Step 3: Content Sources for Vector Embedding -->
				<div class="wpbot-wizard-step" data-step="3">
					<h3>Step 3: Content Sources for Vector Embedding</h3>
					<p class="wpbot-step-desc">Select which content types should be automatically prepared for the RAG search database.</p>
					
					<div class="wpbot-wizard-field checkbox-inline-field">
						<label class="wpbot-checkbox-container">
							<input type="checkbox" name="rag_embed_pages" value="1" checked />
							<span class="wpbot-checkmark"></span>
							Pages
						</label>
					</div>
					
					<div class="wpbot-wizard-field checkbox-inline-field">
						<label class="wpbot-checkbox-container">
							<input type="checkbox" name="rag_embed_posts" value="1" checked />
							<span class="wpbot-checkmark"></span>
							Posts
						</label>
					</div>

					<?php if ( ! empty( $custom_post_types ) ) : ?>
						<div class="wpbot-wizard-field">
							<label style="margin-bottom: 8px; display: block; font-weight: 500; color: #e2e8f0;">Custom Post Types</label>
							<div class="wpbot-wizard-cpt-list">
								<?php foreach ( $custom_post_types as $cpt ) : ?>
									<label class="wpbot-checkbox-container">
										<input type="checkbox" name="rag_embed_cpts[]" value="<?php echo esc_attr( $cpt->name ); ?>" />
										<span class="wpbot-checkmark"></span>
										<?php echo esc_html( $cpt->label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Preloader for Embedding -->
					<div id="wizard-embed-preloader" style="display: none; margin-top: 24px; padding: 20px; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
							<span style="font-weight: 600; color: #ffffff;">Vectorizing Content...</span>
							<span id="wizard-embed-percent" style="font-weight: 600; color: #a855f7;">0%</span>
						</div>
						<div style="height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
							<div id="wizard-embed-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #6366f1, #a855f7); transition: width 0.2s ease;"></div>
						</div>
						<div style="display: flex; align-items: center; color: #cbd5e1; font-size: 13px;">
							<div class="wpbot-spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.1); border-radius: 50%; border-top-color: #a855f7; animation: wpbotSpin 1s ease-in-out infinite; margin-right: 8px; flex-shrink: 0; vertical-align: middle;"></div>
							<span id="wizard-embed-status" style="vertical-align: middle;">Preparing queue...</span>
						</div>
					</div>
				</div>

				<!-- Step 4: Completion Screen -->
				<div class="wpbot-wizard-step" data-step="4">
					<div style="text-align: center; padding: 20px 0;">
						<div style="font-size: 60px; line-height: 1; margin-bottom: 20px; color: #10b981;">🎉</div>
						<h3 style="color: #ffffff; font-size: 22px; font-weight: 600; margin-bottom: 12px;">Congratulations!</h3>
						<p id="wizard-embed-success-msg" style="color: #10b981; font-weight: 600; font-size: 16px; margin-top: 10px; margin-bottom: 20px; display: none;">
							We have Embedded <span id="wizard-embed-count">0</span> post types successfully
						</p>
						<p class="wpbot-step-desc" style="font-size: 15px; line-height: 1.6; color: #cbd5e1; max-width: 480px; margin: 0 auto 20px;">
							Now your Chatbot is trained with your website data. You can upload additional training documents from AI Settings -> Knowledge base.
						</p>
					</div>
				</div>
			</form>
		</div>

		<div class="wpbot-wizard-footer">
			<button type="button" class="wpbot-wizard-btn wpbot-wizard-btn-skip" id="wpbot-wizard-skip">Skip Setup</button>
			<div class="wpbot-wizard-nav-btns">
				<button type="button" class="wpbot-wizard-btn wpbot-wizard-btn-back" id="wpbot-wizard-back" disabled>Back</button>
				<button type="button" class="wpbot-wizard-btn wpbot-wizard-btn-next" id="wpbot-wizard-next">Next</button>
			</div>
		</div>
	</div>
</div>

<style>
/* Premium Dark Glassmorphism Styling */
.wpbot-wizard-overlay {
	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(15, 23, 42, 0.75);
	backdrop-filter: blur(12px);
	-webkit-backdrop-filter: blur(12px);
	z-index: 999999;
	display: none;
	align-items: center;
	justify-content: center;
	color: #f8fafc;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	animation: wpbotFadeIn 0.3s ease forwards;
}

.wpbot-wizard-modal {
	background: rgba(30, 41, 59, 0.95);
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 20px;
	width: 90%;
	max-width: 600px;
	box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
	overflow: hidden;
	position: relative;
	display: flex;
	flex-direction: column;
	max-height: 85vh;
	animation: wpbotScaleIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

/* Progress bar styling */
.wpbot-wizard-progress-container {
	height: 6px;
	background: rgba(255, 255, 255, 0.05);
	width: 100%;
}
.wpbot-wizard-progress-bar {
	height: 100%;
	background: linear-gradient(90deg, #6366f1, #a855f7);
	transition: width 0.3s ease;
}

/* Header */
.wpbot-wizard-header {
	padding: 28px 36px 20px;
	border-bottom: 1px solid rgba(255, 255, 255, 0.08);
	position: relative;
}
.wpbot-wizard-title {
	font-size: 24px;
	font-weight: 700;
	margin: 0 0 6px;
	color: #ffffff;
	background: linear-gradient(to right, #6366f1, #a855f7);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	display: inline-block;
}
.wpbot-wizard-subtitle {
	font-size: 14px;
	color: #94a3b8;
	margin: 0;
}
.wpbot-wizard-close-btn {
	position: absolute;
	top: 28px;
	right: 36px;
	background: none;
	border: none;
	color: #94a3b8;
	font-size: 28px;
	cursor: pointer;
	line-height: 1;
	padding: 0;
	transition: color 0.2s, transform 0.2s;
}
.wpbot-wizard-close-btn:hover {
	color: #ffffff;
	transform: scale(1.1);
}

/* Body and Steps */
.wpbot-wizard-body {
	padding: 36px;
	overflow-y: auto;
	flex: 1;
}
.wpbot-wizard-step {
	display: none;
	animation: wpbotSlideIn 0.3s ease forwards;
}
.wpbot-wizard-step.active {
	display: block;
}
.wpbot-wizard-step h3 {
	font-size: 20px;
	color: #ffffff;
	margin: 0 0 10px;
	font-weight: 600;
}
.wpbot-step-desc {
	font-size: 14px;
	color: #94a3b8;
	margin: 0 0 28px;
	line-height: 1.6;
}

/* Form Fields */
.wpbot-wizard-field {
	margin-bottom: 20px;
}
.wpbot-wizard-field label {
	display: block;
	font-size: 14px;
	color: #e2e8f0;
	margin-bottom: 8px;
	font-weight: 500;
}
.wpbot-wizard-input,
.wpbot-wizard-select {
	width: 100%;
	background: rgba(15, 23, 42, 0.6);
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 10px;
	padding: 12px 16px;
	color: #ffffff;
	font-size: 14px;
	box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
	box-sizing: border-box;
	transition: border-color 0.2s, box-shadow 0.2s;
}
.wpbot-wizard-input:focus,
.wpbot-wizard-select:focus {
	border-color: #6366f1;
	outline: none;
	box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.35);
}
.wpbot-wizard-select option {
	background: #1e293b;
	color: #ffffff;
}
.wpbot-field-help {
	font-size: 12px;
	color: #94a3b8;
	margin: 6px 0 0;
}

/* Custom Checkbox Container */
.wpbot-checkbox-container {
	display: inline-flex;
	align-items: center;
	position: relative;
	padding-left: 28px;
	cursor: pointer;
	font-size: 14px;
	user-select: none;
	color: #cbd5e1;
}
.wpbot-checkbox-container input {
	position: absolute;
	opacity: 0;
	cursor: pointer;
	height: 0;
	width: 0;
}
.wpbot-checkmark {
	position: absolute;
	top: 50%;
	left: 0;
	transform: translateY(-50%);
	height: 18px;
	width: 18px;
	background-color: rgba(255, 255, 255, 0.1);
	border: 1px solid rgba(255, 255, 255, 0.15);
	border-radius: 4px;
	transition: all 0.2s;
}
.wpbot-checkbox-container:hover input ~ .wpbot-checkmark {
	background-color: rgba(255, 255, 255, 0.15);
}
.wpbot-checkbox-container input:checked ~ .wpbot-checkmark {
	background-color: #6366f1;
	border-color: #6366f1;
}
.wpbot-checkmark:after {
	content: "";
	position: absolute;
	display: none;
}
.wpbot-checkbox-container input:checked ~ .wpbot-checkmark:after {
	display: block;
}
.wpbot-checkbox-container .wpbot-checkmark:after {
	left: 5px;
	top: 2px;
	width: 5px;
	height: 9px;
	border: solid white;
	border-width: 0 2px 2px 0;
	transform: rotate(45deg);
}

.wpbot-wizard-cpt-list {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 12px;
	background: rgba(0, 0, 0, 0.2);
	border-radius: 8px;
	padding: 16px;
	margin-top: 8px;
}

/* Footer & Buttons */
.wpbot-wizard-footer {
	padding: 24px 36px;
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: rgba(15, 23, 42, 0.4);
}
.wpbot-wizard-btn {
	border: none;
	border-radius: 8px;
	padding: 10px 20px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.2s;
}
.wpbot-wizard-btn-skip {
	background: transparent;
	color: #94a3b8;
}
.wpbot-wizard-btn-skip:hover {
	color: #ffffff;
}
.wpbot-wizard-nav-btns {
	display: flex;
	gap: 12px;
}
.wpbot-wizard-btn-back {
	background: rgba(255, 255, 255, 0.05);
	color: #e2e8f0;
	border: 1px solid rgba(255, 255, 255, 0.08);
}
.wpbot-wizard-btn-back:hover:not(:disabled) {
	background: rgba(255, 255, 255, 0.1);
}
.wpbot-wizard-btn-back:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}
.wpbot-wizard-btn-next {
	background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
	color: #ffffff;
	box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}
.wpbot-wizard-btn-next:hover {
	transform: translateY(-1px);
	box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

/* Animations */
@keyframes wpbotFadeIn {
	from { opacity: 0; }
	to { opacity: 1; }
}
@keyframes wpbotScaleIn {
	from { transform: scale(0.95); opacity: 0; }
	to { transform: scale(1); opacity: 1; }
}
@keyframes wpbotSlideIn {
	from { transform: translateY(10px); opacity: 0; }
	to { transform: translateY(0); opacity: 1; }
}
@keyframes wpbotSpin {
	to { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
	// Move overlay to body to prevent viewport container centering bugs in WP Admin
	$('body').append($('#wpbot-wizard-overlay'));

	var currentStep = 1;
	var totalSteps = 4;

	// Auto open wizard logic
	var wpbot_auto_open_wizard = <?php echo $show_wizard_automatically ? 'true' : 'false'; ?>;
	if (wpbot_auto_open_wizard && sessionStorage.getItem('wpbot_wizard_skipped') !== '1') {
		$('#wpbot-wizard-overlay').css({ display: 'flex', opacity: 1 });
	}

	// Trigger wizard from settings page button
	$(document).on('click', '#wpbot-trigger-wizard', function(e) {
		e.preventDefault();
		goToStep(1);
		$('#wpbot-wizard-overlay')
			.css({ display: 'flex', opacity: 0 })
			.animate({ opacity: 1 }, 300);
	});

	// Provider detail configuration
	var providerConfig = {
		openai: {
			label: "OpenAI API Key",
			placeholder: "sk-...",
			help: "Requires an active key from https://platform.openai.com/settings/organization/api-keys."
		},
		gemini: {
			label: "Gemini API Key",
			placeholder: "Enter Gemini API Key",
			help: "Requires an active key from https://aistudio.google.com/app/api-keys."
		},
		grok: {
			label: "Grok API Key",
			placeholder: "xai-...",
			help: "Requires an API key from https://console.x.ai/"
		},
		openrouter: {
			label: "OpenRouter API Key",
			placeholder: "sk-or-v1-...",
			help: "Requires an API key from https://openrouter.ai/workspaces/default/keys."
		}
	};

	// Handle Provider change to adjust step 2 credentials UI
	$('#wizard_ai_provider').on('change', function() {
		var selected = $(this).val();
		$('#wizard_credentials_desc').text("Configure the API connection credentials for " + $(this).find('option:selected').text() + ".");
		
		var config = providerConfig[selected];
		if (config) {
			$('#wizard_api_key_label').text(config.label);
			$('#wizard_api_key').attr('placeholder', config.placeholder);
			$('#wizard_api_key_help').text(config.help);
		}
	}).trigger('change');

	// Dismiss wizard logic
	function dismissWizard() {
		$('#wpbot-wizard-overlay').animate({ opacity: 0 }, 300, function() {
			$(this).css('display', 'none');
		});
	}

	// Go to step helper function
	function goToStep(step) {
		$('.wpbot-wizard-step').removeClass('active');
		currentStep = step;
		$('.wpbot-wizard-step[data-step="' + currentStep + '"]').addClass('active');

		// Progress bar animation
		var pct = (currentStep / totalSteps) * 100;
		$('#wpbot-wizard-progress-bar').css('width', pct + '%');

		// Enable/disable back button
		if (currentStep === 1) {
			$('#wpbot-wizard-back').prop('disabled', true);
		} else {
			$('#wpbot-wizard-back').prop('disabled', false);
		}

		// Button texts & visibility adjustments
		if (currentStep === 3) {
			$('#wpbot-wizard-next').text('Finish');
			$('#wpbot-wizard-skip').show();
			$('#wpbot-wizard-back').show();
		} else if (currentStep === 4) {
			$('#wpbot-wizard-next').text('Finish Setup');
			$('#wpbot-wizard-back').hide();
			$('#wpbot-wizard-skip').hide();
			// Auto-disable Site Search when wizard completes
			$('#disable_wp_chatbot_site_search').prop('checked', true);
		} else {
			$('#wpbot-wizard-next').text('Next');
			$('#wpbot-wizard-skip').show();
			$('#wpbot-wizard-back').show();
		}
	}

	// Save options via AJAX
	function saveWizardData(isSkipped) {
		var formData = {
			action: 'wpbot_wizard_save',
			nonce: '<?php echo esc_attr( wp_create_nonce("wp_chatbot") ); ?>',
			is_skipped: isSkipped ? 1 : 0
		};

		if (!isSkipped) {
			// Serialize form data
			var serialized = $('#wpbot-wizard-form').serializeArray();
			$.each(serialized, function(i, field) {
				if (field.name.endsWith('[]')) {
					var cleanName = field.name.slice(0, -2);
					if (!formData[cleanName]) {
						formData[cleanName] = [];
					}
					formData[cleanName].push(field.value);
				} else {
					formData[field.name] = field.value;
				}
			});
			
			// Handle checkbox values explicitly
			formData.rag_embed_pages = $('input[name="rag_embed_pages"]').is(':checked') ? 1 : 0;
			formData.rag_embed_posts = $('input[name="rag_embed_posts"]').is(':checked') ? 1 : 0;
		}

		// Disable all buttons in footer during save
		$('.wpbot-wizard-footer button').prop('disabled', true);
		if (!isSkipped) {
			$('#wpbot-wizard-next').text('Saving...');
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					if (isSkipped) {
						dismissWizard();
						location.reload();
					} else {
						startWizardEmbedding();
					}
				} else {
					alert(response.data || 'Failed to save settings. Please try again.');
					$('.wpbot-wizard-footer button').prop('disabled', false);
					$('#wpbot-wizard-next').text('Finish');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$('.wpbot-wizard-footer button').prop('disabled', false);
				$('#wpbot-wizard-next').text('Finish');
			}
		});
	}

	function startWizardEmbedding() {
		// Show preloader
		$('#wizard-embed-preloader').slideDown();
		
		// Disable all controls
		$('.wpbot-wizard-footer button').prop('disabled', true);
		$('#wpbot-wizard-close').prop('disabled', true);
		$('#wpbot-wizard-next').text('Embedding...');
		
		$('#wizard-embed-percent').text('0%');
		$('#wizard-embed-progress-bar').css('width', '0%');
		$('#wizard-embed-status').text('Preparing embedding queue...');

		// Fetch the queue of items to embed
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'qcld_rag_get_embed_queue',
				nonce: '<?php echo esc_attr( wp_create_nonce("wp_chatbot") ); ?>'
			},
			success: function(response) {
				if (response.success) {
					var queue = response.data;
					if (!queue || queue.length === 0) {
						// No items to embed
						$('#wizard-embed-status').text('No items found to embed.');
						setTimeout(function() {
							$('#wizard-embed-count').text('0');
							$('#wizard-embed-success-msg').show();
							goToStep(4);
							$('.wpbot-wizard-footer button').prop('disabled', false);
							$('#wpbot-wizard-close').prop('disabled', false);
						}, 1000);
						return;
					}

					$('#wizard-embed-status').text('Processing 0 / ' + queue.length + ' items...');
					processWizardEmbedQueue(queue, 0, 0);
				} else {
					alert('Failed to fetch the embedding queue. Moving to next step.');
					$('#wizard-embed-count').text('0');
					$('#wizard-embed-success-msg').show();
					goToStep(4);
					$('.wpbot-wizard-footer button').prop('disabled', false);
					$('#wpbot-wizard-close').prop('disabled', false);
				}
			},
			error: function() {
				alert('An error occurred while fetching the embedding queue. Moving to next step.');
				$('#wizard-embed-count').text('0');
				$('#wizard-embed-success-msg').show();
				goToStep(4);
				$('.wpbot-wizard-footer button').prop('disabled', false);
				$('#wpbot-wizard-close').prop('disabled', false);
			}
		});
	}

	function processWizardEmbedQueue(queue, index, successCount) {
		if (index >= queue.length) {
			$('#wizard-embed-status').text('Embedding complete! Total processed: ' + successCount + ' / ' + queue.length);
			setTimeout(function() {
				$('#wizard-embed-count').text(successCount);
				$('#wizard-embed-success-msg').show();
				goToStep(4);
				$('.wpbot-wizard-footer button').prop('disabled', false);
				$('#wpbot-wizard-close').prop('disabled', false);
			}, 1000);
			return;
		}

		var item = queue[index];
		var progress = Math.round(((index + 1) / queue.length) * 100);

		$('#wizard-embed-status').text('Embedding document (' + (index + 1) + ' / ' + queue.length + ')...');
		$('#wizard-embed-progress-bar').css('width', progress + '%');
		$('#wizard-embed-percent').text(progress + '%');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'qcld_rag_process_item',
				nonce: '<?php echo esc_attr( wp_create_nonce("wp_chatbot") ); ?>',
				item_id: item.id,
				item_type: item.type
			},
			success: function(response) {
				// We consider either a successful return or skip as a processed item
				var increment = 0;
				if (response.success) {
					increment = 1;
				}
				processWizardEmbedQueue(queue, index + 1, successCount + increment);
			},
			error: function() {
				// Continue to next item on failure, so wizard doesn't hang
				processWizardEmbedQueue(queue, index + 1, successCount);
			}
		});
	}

	// Skip Button Click
	$('#wpbot-wizard-skip, #wpbot-wizard-close').on('click', function(e) {
		e.preventDefault();
		if (currentStep === 4) {
			dismissWizard();
			location.reload();
			return;
		}
		if (confirm('Are you sure you want to skip the setup wizard? you can set up these configurations manually inside the settings panel.')) {
			sessionStorage.setItem('wpbot_wizard_skipped', '1');
			dismissWizard();
		}
	});

	// Next Button Click
	$('#wpbot-wizard-next').on('click', function(e) {
		e.preventDefault();
		
		// If we are on Step 4 (Congratulations), clicking Next should close and reload.
		if (currentStep === 4) {
			// Ensure Site Search is disabled before closing
			$('#disable_wp_chatbot_site_search').prop('checked', true);
			dismissWizard();
			location.reload();
			return;
		}

		// Basic Validation for credentials step (Step 2) and AJAX verification
		if (currentStep === 2) {
			var provider = $('#wizard_ai_provider').val();
			var apiKey = $('#wizard_api_key').val().trim();

			if (apiKey === '') {
				alert('Please enter your API Key before continuing.');
				return;
			}

			// Show loader and hide old error
			$('#wizard-api-loader').show();
			$('#wizard-api-error').hide();
			$('.wpbot-wizard-footer button').prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpbot_wizard_verify_key',
					nonce: '<?php echo esc_attr( wp_create_nonce("wp_chatbot") ); ?>',
					ai_provider: provider,
					api_key: apiKey
				},
				success: function(response) {
					$('#wizard-api-loader').hide();
					$('.wpbot-wizard-footer button').prop('disabled', false);
					
					if (response.success) {
						goToStep(3);
					} else {
						$('#wizard-api-error').text(response.data || 'Failed to verify API key. Please check your credentials.').show();
					}
				},
				error: function(xhr, status, error) {
					$('#wizard-api-loader').hide();
					$('.wpbot-wizard-footer button').prop('disabled', false);
					$('#wizard-api-error').text('An error occurred during verification: ' + error).show();
				}
			});
			return;
		}

		if (currentStep === 3) {
			// Save wizard data and transition to Step 4 on success
			saveWizardData(false);
			return;
		}

		if (currentStep < totalSteps) {
			goToStep(currentStep + 1);
		}
	});

	// Back Button Click
	$('#wpbot-wizard-back').on('click', function(e) {
		e.preventDefault();
		if (currentStep > 1 && currentStep < 4) {
			goToStep(currentStep - 1);
		}
	});
});
</script>
