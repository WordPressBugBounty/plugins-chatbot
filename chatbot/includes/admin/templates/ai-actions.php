<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( isset( $_POST['submit'] ) && check_admin_referer( 'wp_chatbot_ai_actions', '_wpnonce' ) && current_user_can( 'manage_options' ) ) {
    
    $ai_forms = array();
    if ( isset( $_POST['ai_form_title'] ) && is_array( $_POST['ai_form_title'] ) ) {
        $titles = $_POST['ai_form_title'];
        $prompts = isset( $_POST['ai_form_prompt'] ) ? $_POST['ai_form_prompt'] : array();
        $interactive = isset( $_POST['ai_form_interactive'] ) ? $_POST['ai_form_interactive'] : array();
        $email = isset( $_POST['ai_form_email'] ) ? $_POST['ai_form_email'] : array();
        $email_addresses = isset( $_POST['ai_form_email_addresses'] ) ? $_POST['ai_form_email_addresses'] : array();
        
        for ( $i = 0; $i < count( $titles ); $i++ ) {
            $title = sanitize_text_field( wp_unslash( $titles[$i] ) );
            $prompt = isset( $prompts[$i] ) ? sanitize_textarea_field( wp_unslash( $prompts[$i] ) ) : '';
            if ( ! empty( $title ) && ! empty( $prompt ) ) {
                $ai_forms[] = array(
                    'title'  => $title,
                    'prompt' => $prompt,
                    'interactive' => isset($interactive[$i]) ? intval($interactive[$i]) : 0,
                    'email' => isset($email[$i]) ? intval($email[$i]) : 1,
                    'email_addresses' => isset($email_addresses[$i]) ? sanitize_textarea_field( wp_unslash( $email_addresses[$i] ) ) : '',
                );
            }
        }
    }
    update_option( 'wpbot_ai_forms', $ai_forms );

    if ( isset( $_POST['qcld_ai_default_email'] ) ) {
        $raw_emails = wp_unslash( $_POST['qcld_ai_default_email'] );
        // Sanitize each email individually, preserving comma-separated list
        $emails = array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw_emails ) ) );
        $emails = array_filter( $emails ); // remove any invalid entries
        update_option( 'qcld_ai_default_email', implode( ', ', $emails ) );
    }

    update_option( 'enable_ai_interactive_form', '1' );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'chatbot' ) . '</p></div>';
}

$saved_forms = get_option( 'wpbot_ai_forms', array() );

// One-time migration: Ensure all existing forms have email enabled by default.
$migrated = false;
if ( is_array( $saved_forms ) ) {
    foreach ( $saved_forms as &$f ) {
        if ( ! isset( $f['email_migrated'] ) ) {
            $f['email'] = 1;
            $f['email_migrated'] = 1;
            $migrated = true;
        }
    }
    if ( $migrated ) {
        update_option( 'wpbot_ai_forms', $saved_forms );
    }
}
if ( ! is_array( $saved_forms ) ) {
    $saved_forms = array();
}
?>
<div class="wrap qcld-main-wrapper qcld-ai-actions-page">
    <div class="qcld-wp-chatbot-wrap-header-aisection">
        <div class="qcld-wp-chatbot-wrap-header">
            <div class="qcld-wp-chatbot-wrap-header-logo">
                <a href="#" class="qcld-wp-chatbot-wrap-site__logo">
                    <img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="WPBot"> WPBot Control Panel
                </a>
                <p><strong>Core Version:</strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
            </div>
            <ul class="qcld-wp-chatbot-wrap-version-wrapper">
                <li><a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a></li>
            </ul>
        </div>
    </div>

    <div class="wp-chatbot-wrap">
        <section class="wp-chatbot-tab-container-inner">
        <div class="wp-chatbot-tabs wp-chatbot-tabs-style-flip qcld-ai-actions-layout">

            <nav>
                <ul class="qcld-ai-sidebar-menu">
                    <li class="tab-current">
                        <a href="#ai-new-action-tab" class="ai-sidebar-link active">
                            <span class="wpwbot-admin-tab-icon"><span class="dashicons dashicons-plus-alt"></span></span>
                            <span class="wpwbot-admin-tab-name"><?php esc_html_e('Add New Action', 'chatbot'); ?></span>
                        </a>
                    </li>
                    <li class="qcld-ai-saved-parent">
                        <a href="#" class="ai-sidebar-parent-link">
                            <span class="wpwbot-admin-tab-icon"><span class="dashicons dashicons-portfolio"></span></span>
                            <span class="wpwbot-admin-tab-name"><?php esc_html_e('Saved Actions', 'chatbot'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 qcld-ai-saved-toggle"></span>
                        </a>
                        <ul class="ai-saved-actions-submenu">
                            <?php
                            $counter = 1;
                            if ( ! empty( $saved_forms ) ) {
                                foreach ( $saved_forms as $form ) {
                                    $tab_id = 'ai-action-tab-' . $counter;
                                    echo '<li><a href="#' . esc_attr( $tab_id ) . '" class="ai-sidebar-link ai-sidebar-saved-link" data-id="' . esc_attr( $tab_id ) . '">' . esc_html( $form['title'] ) . '</a></li>';
                                    $counter++;
                                }
                            } else {
                                echo '<li class="ai-no-saved-actions">' . esc_html__( 'No saved actions', 'chatbot' ) . '</li>';
                            }
                            ?>
                        </ul>
                    </li>
                </ul>
            </nav>

            <div class="content-wrap qcld-ai-actions-content">
                <form action="" method="POST" id="ai-actions-main-form">
                    <?php wp_nonce_field( 'wp_chatbot_ai_actions', '_wpnonce' ); ?>

                    <div id="ai-new-action-tab" class="ai-content-pane active">
                        <?php 
                        $no_ai_active = (get_option( 'ai_enabled' ) != 1 &&
                            get_option( 'qcld_openrouter_enabled' ) != 1 &&
                            get_option( 'qcld_gemini_enabled' ) != 1 &&
                            get_option( 'qcld_grok_enabled' ) != 1 &&
                            get_option( 'qcld_claude_enabled' ) != 1);
                        if($no_ai_active): 
                        ?>
                        <div class="qcld-ai-actions-alert">
                            <?php esc_html_e('No AI connection is active. Please enable AI integration.', 'chatbot'); ?>
                        </div>
                        <?php endif; ?>
                        <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Choose a Template', 'chatbot'); ?></h3>
                        <p><?php esc_html_e('Select a template below to create a new AI Action. You can customize it on the next screen.', 'chatbot'); ?></p>
                        
                        <div class="qcld-ai-template-grid">
                            <!-- Blank -->
                            <div class="ai-template-card" data-title="" data-prompt="" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-plus-alt2" ></span></div>
                                <h4>Blank Prompt</h4>
                                <p>Start from scratch and build your own custom AI action.</p>
                            </div>
                            
                            <!-- 1. Web Agency Quote -->
                            <div class="ai-template-card" data-title="Web Agency Quote" data-prompt="You are a project discovery assistant for a web development agency.
Ask the user the following questions conversationally, one at a time:

1. What type of project are they planning (e.g., WordPress plugin, custom web app, WooCommerce store)?
2. What are the core features or problems they need solved?
3. What is their estimated budget range and target launch timeline?
4. What is their full name, work email, and preferred contact method?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-desktop" ></span></div>
                                <h4>Web Agency Quote</h4>
                                <p>Software, agency, and freelance service inquiries.</p>
                            </div>

                            <!-- 2. Real Estate Inquiry -->
                            <div class="ai-template-card" data-title="Real Estate Inquiry" data-prompt="You are a real estate assistant helping visitors find their ideal property.
Collect these details step-by-step:

1. Are they looking to Buy, Rent, or Sell?
2. What property type and location/neighborhood are they interested in?
3. How many bedrooms and bathrooms do they require?
4. What is their target price or monthly budget range?
5. What is their full name, phone number, and email address to schedule a viewing?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-building" ></span></div>
                                <h4>Real Estate Inquiry</h4>
                                <p>Qualifying buyers and renters for property showings.</p>
                            </div>

                            <!-- 3. Priority Support -->
                            <div class="ai-template-card" data-title="Priority Support" data-prompt="You are a technical support intake agent.
Guide the customer through collecting diagnostic details:

1. What plugin, product, or service is experiencing the issue?
2. Can they briefly describe the error or unexpected behavior?
3. What is the website URL where this occurs, and their environment specs (e.g., WP/PHP versions) if known?
4. What is the urgency level (Low, Medium, or Critical / Site Down)?
5. What is their full name and account email address?

Acknowledge issues empathetically.
" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-sos" ></span></div>
                                <h4>Priority Support</h4>
                                <p>Bug triage and diagnostic data gathering for helpdesks.</p>
                            </div>

                            <!-- 4. SaaS Demo Booking -->
                            <div class="ai-template-card" data-title="SaaS Demo Booking" data-prompt="You are an onboarding specialist for a SaaS platform.
Qualify visitors requesting a product demo by asking:

1. What company do they represent, and what is their current team or company size?
2. What is the primary bottleneck or workflow they want our software to automate?
3. What CRM or marketing tools are in their current tech stack?
4. What is their full name, business email, and preferred day/time for a 15-minute demo?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-chart-pie" ></span></div>
                                <h4>SaaS Demo Booking</h4>
                                <p>Lead qualification and sales routing for software platforms.</p>
                            </div>

                            <!-- 5. E-Commerce Wholesale -->
                            <div class="ai-template-card" data-title="E-Commerce Wholesale" data-prompt="You are a sales assistant helping wholesale and bulk-order customers.
Gather order specifications:

1. What product SKU or item category are they interested in?
2. What estimated quantity or unit count do they plan to purchase?
3. Do they require custom branding/labels or standard packaging?
4. What is their target delivery deadline and destination country/postal code?
5. What is their company name, contact person, and billing email?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-cart" ></span></div>
                                <h4>E-Commerce Wholesale</h4>
                                <p>B2B wholesale pricing and custom merchandise for WooCommerce.</p>
                            </div>

                            <!-- 6. Legal Case Intake -->
                            <div class="ai-template-card" data-title="Legal Case Intake" data-prompt="You are a legal intake assistant for a law firm.
State clearly that this chat does not establish an attorney-client relationship, then collect:

1. What area of law do they need assistance with (e.g., Personal Injury, Family Law, Business Dispute, Estate Planning)?
2. A brief summary of the situation and approximately when it occurred.
3. Are there any upcoming deadlines, court dates, or active lawsuits already filed?
4. What is their full name, phone number, and email address?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-portfolio" ></span></div>
                                <h4>Legal Case Intake</h4>
                                <p>Pre-screening legal leads for practice area viability and deadlines.</p>
                            </div>

                            <!-- 7. Healthcare Booking -->
                            <div class="ai-template-card" data-title="Healthcare Booking" data-prompt="You are a patient coordinator assistant for a dental and medical clinic.
Collect scheduling details without providing medical advice:

1. Are they a new or existing patient, and what is the primary reason for their visit?
2. Do they have medical/dental insurance, and if so, who is the provider?
3. What days of the week and times of day work best for their appointment?
4. What is their full name, date of birth, phone number, and email address?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-heart" ></span></div>
                                <h4>Healthcare Booking</h4>
                                <p>Clinic patient scheduling, primary symptoms, and insurance check.</p>
                            </div>

                            <!-- 8. Event Planning -->
                            <div class="ai-template-card" data-title="Event Planning" data-prompt="You are an event specialist assisting clients with catering and planning inquiries.
Guide them through these questions:

1. What type of event are they planning (e.g., Wedding, Corporate Gala, Birthday)?
2. What is the estimated event date, venue location, and expected guest count?
3. What dining/service style do they prefer (e.g., Plated Multi-Course, Buffet, Cocktail/Hors d'oeuvres)?
4. What is their approximate overall budget?
5. What is their contact name, organization (if applicable), phone number, and email?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-calendar-alt" ></span></div>
                                <h4>Event Planning</h4>
                                <p>Event venue, banquet, and catering inquiries.</p>
                            </div>

                            <!-- 9. Auto Dealership -->
                            <div class="ai-template-card" data-title="Auto Dealership" data-prompt="You are a sales concierge for an automotive dealership.
Collect test drive details:

1. Which vehicle model, trim, or year are they interested in test-driving?
2. Do they have a trade-in vehicle? If yes, ask for the Year, Make, Model, and approximate mileage.
3. What is their preferred payment path (Financing, Leasing, or Cash purchase)?
4. What date and time would they like to schedule their test drive?
5. What is their full name, mobile number, and email address?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-car" ></span></div>
                                <h4>Auto Dealership</h4>
                                <p>Test-drive bookings and vehicle trade-in estimates.</p>
                            </div>

                            <!-- 10. Education Admissions -->
                            <div class="ai-template-card" data-title="Education Admissions" data-prompt="You are an admissions advisor for an educational training academy.
Ask the prospective student:

1. Which program or certification do they want to enroll in?
2. What is their current background or experience level (Beginner, Intermediate, Advanced)?
3. Are they looking for Full-Time (Immersive) or Part-Time study?
4. What is their target cohort start date?
5. What is their full name, WhatsApp/phone number, and email address?

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-welcome-learn-more" ></span></div>
                                <h4>Education Admissions</h4>
                                <p>Online course and bootcamp enrollment qualification.</p>
                            </div>

                            <!-- 11. Senior PHP Programmer Assessment -->
                            <div class="ai-template-card" data-title="Senior PHP Programmer Assessment" data-prompt="You are an expert technical interviewer screening candidates for an in-house Senior PHP Developer position specializing in Laravel and WordPress core architecture.

Role Prerequisites:
- Degree: Must hold a Bachelor's degree in Computer Science (CSC) or Computer Information Systems (CIS).
- Experience: Minimum 2 years of professional PHP development with hands-on Laravel and WordPress plugin engineering.

CRITICAL EXECUTION RULES:
- Ask exactly ONE question per turn.
- NEVER combine multiple questions, items, or prompts into a single message.
- Await the candidate's answer before moving to the next item.
- Do not provide code solutions, hints, or technical corrections during the interview.

Sequential Interview Steps (Strict 1-by-1 Flow):
Step 1: &quot;To get started, what is your full name?&quot;
Step 2: &quot;What is your primary email address?&quot;
Step 3: &quot;What is the best phone number to reach you?&quot;
Step 4: &quot;Could you share the link to your GitHub, GitLab, or portfolio profile?&quot;
Step 5: &quot;What is your exact degree title, major, and graduating university?&quot;
Step 6: &quot;How many total years of professional PHP development experience do you have?&quot;
Step 7: &quot;What commercial projects have you built using Laravel and custom WordPress plugins?&quot;
Step 8: &quot;Technical Q1 (Laravel Architecture): How do Service Providers, the Service Container, and Middleware interact during Laravel's request lifecycle? How do you implement and register a custom deferred service provider?&quot;
Step 9: &quot;Technical Q2 (WordPress Core Internals): Explain how the WordPress Action and Filter hook system works internally under the WP_Hook class. When architecting scalable plugins, how do you manage nonces, custom database tables vs Custom Post Types, and transients for cache invalidation?&quot;
Step 10: &quot;Technical Q3 (Security &amp; Performance): How do you prevent race conditions, memory exhaustion (e.g., Eloquent chunking vs PHP Generators), and SQL injection when executing bulk database operations across Laravel and WordPress environments?&quot;

" >
                                <div class="ai-template-card-icon"><span class="dashicons dashicons-businessman" ></span></div>
                                <h4>Senior PHP Programmer Assessment</h4>
                                <p>Screening candidates for a Senior PHP Developer position.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SAVED ACTIONS TABS (Generated) -->
                    <div id="ai-saved-actions-container">
                        <?php 
                        $counter = 1;
                        if ( ! empty( $saved_forms ) ) :
                            foreach ( $saved_forms as $form ) :
                                $tab_id = 'ai-action-tab-' . $counter;
                                $chk_interactive_id = 'ai_interactive_' . $counter;
                                $chk_email_id = 'ai_email_' . $counter;
                        ?>
                            <div id="<?php echo esc_attr($tab_id); ?>" class="ai-content-pane ai-form-item saved-ai-action" style="display: none;">

                                <div class="qcld-ai-action-edit-head">
                                    <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Edit Action', 'chatbot'); ?></h3>
                                    <div class="qcld-ai-action-edit-actions">
                                        <button type="button" class="qcld-btn-primary qcld-ai-goto-new-action"><?php esc_html_e('Add New Action', 'chatbot'); ?></button>
                                        <button type="button" class="button remove-ai-form"><?php esc_html_e( 'Delete Action', 'chatbot' ); ?></button>
                                    </div>
                                </div>

                                <h2 class="nav-tab-wrapper wpbot-ai-inner-tabs">
                                    <a href="#ai-inner-settings-<?php echo esc_attr($counter); ?>" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'chatbot'); ?></a>
                                    <a href="#" class="nav-tab qcld-ai-history-pro-tab"><?php esc_html_e('History', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span></a>
                                </h2>

                                <div id="ai-inner-settings-<?php echo esc_attr($counter); ?>" class="ai-inner-tab-content">
                                    <div class="form-group">
                                        <label><?php esc_html_e('Action Title', 'chatbot'); ?></label>
                                        <input type="text" name="ai_form_title[]" class="form-control ai-form-title-input" placeholder="<?php esc_attr_e('e.g., Hotel Booking', 'chatbot'); ?>" value="<?php echo esc_attr( $form['title'] ); ?>" />
                                    </div>
                                    <div class="form-group">
                                        <label><?php esc_html_e('AI Prompt', 'chatbot'); ?></label>
                                        <textarea name="ai_form_prompt[]" class="form-control" rows="15" placeholder="<?php esc_attr_e('Enter your prompt here...', 'chatbot'); ?>"><?php echo esc_textarea( $form['prompt'] ); ?></textarea>
                                    </div>

                                    <div class="cxsc-settings-blocks">
                                        <div class="form-group qcld-ai-email-toggle">
                                            <input type="hidden" name="ai_form_email[]" class="ai-email-val" value="<?php echo esc_attr( isset($form['email']) ? $form['email'] : 1 ); ?>">
                                            <input type="checkbox" id="<?php echo esc_attr( $chk_email_id ); ?>" <?php echo ( !isset($form['email']) || $form['email'] == 1 ) ? 'checked' : ''; ?> onchange="jQuery(this).prev('.ai-email-val').val(this.checked ? 1 : 0);">
                                            <label for="<?php echo esc_attr( $chk_email_id ); ?>"><?php esc_html_e('Email the data', 'chatbot'); ?></label>
                                        </div>
                                        <div class="qcld-ai-email-field">
                                            <label><?php esc_html_e('Send to Email(s)', 'chatbot'); ?></label>
                                            <input type="text" name="ai_form_email_addresses[]" class="form-control" placeholder="<?php echo esc_attr( get_option('qlcd_wp_chatbot_admin_email', get_option('admin_email')) ); ?>" value="<?php echo esc_attr( isset($form['email_addresses']) ? $form['email_addresses'] : '' ); ?>">
                                            <p class="qcld-ai-field-hint"><?php esc_html_e('Comma-separated. Leave blank to use the default admin email.', 'chatbot'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div id="ai-inner-history-<?php echo esc_attr($counter); ?>" class="ai-inner-tab-content" style="display: none;">
                                    <div id="ai-inner-history-content-<?php echo esc_attr($counter); ?>">
                                        <p class="qcld-ai-muted"><?php esc_html_e('Loading history...', 'chatbot'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php 
                                $counter++;
                            endforeach;
                        endif;
                        ?>
                    </div>
                    
                    <div id="ai-save-wrapper" class="wp-chatbot-admin-footer qcld-ai-save-footer">
                        <div class="cxsc-settings-blocks-notic">
                            <p><?php esc_html_e("Don't forget to save your changes!", 'chatbot'); ?></p>
                            <p><strong><?php esc_html_e('Create powerful AI Actions with Prompt to collect information and send to your email.', 'chatbot'); ?></strong></p>
                            <p><strong><?php esc_html_e('After creating an AI Action, you can add it to the Active Start Menu from', 'chatbot'); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot&tab=startmenu' ) ); ?>"><?php esc_html_e('Settings -> Start Menu', 'chatbot'); ?></a></strong></p>
                        </div>
                        <input type="submit" name="submit" class="qcld-btn-primary" value="<?php esc_attr_e( 'Save Settings', 'chatbot' ); ?>" />
                    </div>
                </form>
            </div>

            <?php
            $default_provider = 'openai';
            if ( get_option( 'qcld_gemini_enabled' ) == 1 ) {
                $default_provider = 'gemini';
            } elseif ( get_option( 'qcld_claude_enabled' ) == 1 ) {
                $default_provider = 'claude';
            } elseif ( get_option( 'qcld_grok_enabled' ) == 1 ) {
                $default_provider = 'grok';
            } elseif ( get_option( 'qcld_openrouter_enabled' ) == 1 ) {
                $default_provider = 'openrouter';
            }

            if ( get_option( 'wp_chatbot_icon' ) == 'custom.png' ) {
                $wp_chatbot_custom_icon_path = ( ! empty( get_option( 'wp_chatbot_custom_icon_path' ) ) ) ? get_option( 'wp_chatbot_custom_icon_path' ) : QCLD_wpCHATBOT_IMG_URL . 'icon-1.png';
            } elseif ( get_option( 'wp_chatbot_icon' ) ) {
                $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option( 'wp_chatbot_icon' );
            } else {
                $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'icon-1.png';
            }
            ?>
            <aside class="wp-chatbot-admin-upgrade-pro-sidebar qcld-ai-actions-playground">
                <div class="qcld-ai-playground">
                    <div class="qcld-ai-playground-header">
                        <span><?php esc_html_e('Live AI Playground', 'chatbot'); ?></span>
                        <button type="button" id="ai-playground-refresh" title="<?php esc_attr_e('Refresh Chat', 'chatbot'); ?>">
                            <span class="dashicons dashicons-update-alt"></span>
                        </button>
                    </div>
                    <div id="ai-actions-playground-container">
                        <div id="ai-actions-playground-messages">
                            <div class="qcld-ai-pg-bot-row">
                                <div class="qcld-ai-pg-avatar" style="background-image: url('<?php echo esc_url( $wp_chatbot_custom_icon_path ); ?>');"></div>
                                <div class="qcld-ai-pg-bubble">
                                    <p><?php esc_html_e('Hello! I am here to find what you need. What are you looking for?', 'chatbot'); ?></p>
                                    <div id="ai-actions-live-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="qcld-ai-playground-footer">
                        <input type="text" id="playground-input" placeholder="<?php esc_attr_e('Send a message...', 'chatbot'); ?>">
                        <button type="button" id="playground-send-btn">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        </button>
                    </div>
                </div>
            </aside>

        </div>
        </section>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // --- SIDEBAR TAB SWITCHING LOGIC ---
    function switchTab(targetId, linkObj) {
        $('.ai-content-pane').hide();
        $('.ai-sidebar-link').removeClass('active');
        $('.qcld-ai-actions-layout nav > ul > li').removeClass('tab-current');
        $('.ai-saved-actions-submenu li').removeClass('tab-current');
        
        $('#' + targetId).show();
        if (linkObj) {
            linkObj.addClass('active');
        } else {
            linkObj = $('.ai-sidebar-link[href="#' + targetId + '"]');
            linkObj.addClass('active');
        }
        if (linkObj && linkObj.length) {
            linkObj.closest('li').addClass('tab-current');
            if (linkObj.hasClass('ai-sidebar-saved-link')) {
                $('.qcld-ai-saved-parent').addClass('is-open');
            }
        }
        
        // Hide Save button if history tab (if we still had it globally, but we don't)
        $('#ai-save-wrapper').show();
        
        // Save to sessionStorage so it stays open after reload
        sessionStorage.setItem('qcld_ai_active_tab', targetId);
    }

    // Restore active tab on page load
    var savedTab = sessionStorage.getItem('qcld_ai_active_tab');
    if (savedTab) {
        var linkToOpen = null;
        var tabToOpen = null;
        
        if ($('#' + savedTab).length) {
            tabToOpen = savedTab;
            linkToOpen = $('.ai-sidebar-link[href="#' + savedTab + '"]');
        } else if (savedTab.indexOf('ai-action-tab-') === 0) {
            // Was a newly generated tab. After saving, it becomes the last saved action.
            var lastSavedLink = $('.ai-sidebar-saved-link').last();
            if (lastSavedLink.length) {
                tabToOpen = lastSavedLink.attr('href').substring(1);
                linkToOpen = lastSavedLink;
                // Update session storage to the new real ID
                sessionStorage.setItem('qcld_ai_active_tab', tabToOpen);
            }
        }
        
        if (tabToOpen) {
            switchTab(tabToOpen, null);
            if (linkToOpen && linkToOpen.closest('.ai-saved-actions-submenu').length) {
                $('.ai-saved-actions-submenu').show();
                $('.qcld-ai-saved-parent').addClass('is-open');
                $('.qcld-ai-saved-toggle').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        }
    }

    $(document).on('click', '.qcld-ai-goto-new-action', function(e) {
        e.preventDefault();
        switchTab('ai-new-action-tab', $('.ai-sidebar-link[href="#ai-new-action-tab"]'));
    });

    $(document).on('click', '.ai-sidebar-link', function(e) {
        e.preventDefault();
        var href = $(this).attr('href') || '';
        if (href.indexOf('#') === -1) {
            return;
        }
        var target = href.substring(href.indexOf('#') + 1);
        if (!target) {
            return;
        }
        switchTab(target, $(this));
    });
    
    // --- SAVED ACTIONS SUBMENU TOGGLE ---
    $('.ai-sidebar-parent-link').on('click', function(e) {
        e.preventDefault();
        var submenu = $(this).next('.ai-saved-actions-submenu');
        var icon = $(this).find('.qcld-ai-saved-toggle');
        $(this).closest('li').toggleClass('is-open');
        
        submenu.slideToggle(200);
        if (icon.hasClass('dashicons-arrow-down-alt2')) {
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        } else {
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });
    
    // --- INNER TABS (SETTINGS/HISTORY) ---
    $(document).on('click', '.wpbot-ai-inner-tabs .nav-tab', function(e) {
        e.preventDefault();
        
        if ($(this).attr('href') === '#') return false;
        
        var pane = $(this).closest('.ai-content-pane');
        var targetId = $(this).attr('href').substring(1);
        
        // Tab styling
        pane.find('.wpbot-ai-inner-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Tab content
        pane.find('.ai-inner-tab-content').hide();
        pane.find('#' + targetId).fadeIn(200);
        
        // Handle History AJAX loading
        if ($(this).hasClass('ai-load-history-btn')) {
            var contentTarget = $(this).data('target');
            var formTitle = pane.find('.ai-form-title-input').val();
            
            var targetContainer = $('#' + contentTarget);
            targetContainer.html('<p style="color: #666; font-style: italic;"><?php esc_html_e('Loading history...', 'chatbot'); ?></p>');
            
            if (!formTitle || formTitle.trim() === '') {
                targetContainer.html('<p style="color: #a00;"><?php esc_html_e('Please save the form with a title first to preview entries.', 'chatbot'); ?></p>');
                return;
            }
            
            var data = {
                action: 'qcld_get_ai_form_entries',
                form_title: formTitle,
                nonce: '<?php echo esc_js( wp_create_nonce( 'wp_chatbot_ai_actions' ) ); ?>'
            };
            
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    targetContainer.html(response.data.html);
                } else {
                    targetContainer.html('<p style="color: #a00;"><?php esc_html_e('Error loading entries.', 'chatbot'); ?></p>');
                }
            }).fail(function() {
                targetContainer.html('<p style="color: #a00;"><?php esc_html_e('Error connecting to server.', 'chatbot'); ?></p>');
            });
        }
    });

    // --- DELETE HISTORY ENTRY ---
    $(document).on('click', '.qcld-delete-ai-entry', function(e) {
        e.preventDefault();
        var btn = $(this);
        var entryId = btn.data('id');
        
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this history entry?', 'chatbot'); ?>')) {
            return;
        }
        
        btn.prop('disabled', true).text('Deleting...');
        
        var data = {
            action: 'qcld_delete_ai_form_entry',
            entry_id: entryId,
            nonce: '<?php echo esc_js( wp_create_nonce( 'wp_chatbot_ai_actions' ) ); ?>'
        };
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Error: ' + response.data);
                btn.prop('disabled', false).text('Delete');
            }
        }).fail(function() {
            alert('Error connecting to server.');
            btn.prop('disabled', false).text('Delete');
        });
    });

    // --- CREATE NEW ACTION FROM TEMPLATE ---
    $('.ai-template-card').on('click', function() {
        var title = $(this).data('title');
        var prompt = $(this).data('prompt');
        
        var uniqueId = 'ai_form_' + Math.floor(Math.random() * 1000000);
        var tabId = 'ai-action-tab-' + uniqueId;
        var chk_interactive_id = 'ai_interactive_' + uniqueId;
        var chk_email_id = 'ai_email_' + uniqueId;
        var displayTitle = title ? title : 'New Blank Action';
        
        // Remove "No saved actions" if exists
        $('.ai-no-saved-actions').remove();
        
        // 1. Add Sidebar Link
        var linkHtml = '<li><a href="#' + tabId + '" class="ai-sidebar-link ai-sidebar-saved-link" data-id="' + tabId + '">' + displayTitle + '</a></li>';
        $('.ai-saved-actions-submenu').append(linkHtml);
        
        // Ensure submenu is open
        $('.ai-saved-actions-submenu').slideDown(200);
        $('.qcld-ai-saved-parent').addClass('is-open');
        $('.qcld-ai-saved-toggle').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        
        // 2. Add Form Pane
        var template = `
            <div id="` + tabId + `" class="ai-content-pane ai-form-item" style="display: none;">
                <div class="qcld-ai-action-edit-head">
                    <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Edit Action', 'chatbot'); ?></h3>
                    <div class="qcld-ai-action-edit-actions">
                        <button type="button" class="qcld-btn-primary qcld-ai-goto-new-action"><?php esc_html_e('Add New Action', 'chatbot'); ?></button>
                        <button type="button" class="button remove-ai-form"><?php esc_html_e( 'Delete Action', 'chatbot' ); ?></button>
                    </div>
                </div>
                
                <h2 class="nav-tab-wrapper wpbot-ai-inner-tabs">
                    <a href="#ai-inner-settings-` + uniqueId + `" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'chatbot'); ?></a>
                    <a href="#" class="nav-tab qcld-ai-history-pro-tab"><?php esc_html_e('History', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span></a>
                </h2>
                
                <div id="ai-inner-settings-` + uniqueId + `" class="ai-inner-tab-content">
                    <div class="form-group">
                        <label><?php esc_html_e('Action Title', 'chatbot'); ?></label>
                        <input type="text" name="ai_form_title[]" class="form-control ai-form-title-input" placeholder="<?php esc_attr_e('e.g., Hotel Booking', 'chatbot'); ?>" value="` + title + `" />
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('AI Prompt', 'chatbot'); ?></label>
                        <textarea name="ai_form_prompt[]" class="form-control" rows="8" placeholder="<?php esc_attr_e('Enter your prompt here...', 'chatbot'); ?>">` + prompt + `</textarea>
                    </div>
                    
                    <div class="cxsc-settings-blocks">
                        <div class="form-group qcld-ai-email-toggle">
                            <input type="hidden" name="ai_form_email[]" class="ai-email-val" value="1">
                            <input type="checkbox" id="` + chk_email_id + `" checked onchange="jQuery(this).prev(\'.ai-email-val\').val(this.checked ? 1 : 0);">
                            <label for="` + chk_email_id + `"><?php esc_html_e('Email the data', 'chatbot'); ?></label>
                        </div>
                        <div class="qcld-ai-email-field">
                            <label><?php esc_html_e('Send to Email(s)', 'chatbot'); ?></label>
                            <input type="text" name="ai_form_email_addresses[]" class="form-control" placeholder="<?php echo esc_attr( get_option('qlcd_wp_chatbot_admin_email', get_option('admin_email')) ); ?>" value="">
                            <p class="qcld-ai-field-hint"><?php esc_html_e('Comma-separated. Leave blank to use the default admin email.', 'chatbot'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div id="ai-inner-history-` + uniqueId + `" class="ai-inner-tab-content" style="display: none;">
                    <div id="ai-inner-history-content-` + uniqueId + `">
                        <p class="qcld-ai-muted"><?php esc_html_e('Loading history...', 'chatbot'); ?></p>
                    </div>
                </div>
            </div>
        `;
        $('#ai-saved-actions-container').append(template);
        
        // 3. Switch to it immediately
        switchTab(tabId, $('.ai-sidebar-link[href="#' + tabId + '"]'));
        
        // 4. Update preview playground
        updateAiActionsPreview();
    });

    // --- REMOVE FORM ITEM ---
    $(document).on('click', '.remove-ai-form', function(e) {
        e.preventDefault();
        var pane = $(this).closest('.ai-content-pane');
        var tabId = pane.attr('id');
        
        // Remove pane
        pane.remove();
        
        // Remove sidebar link
        $('.ai-sidebar-link[href="#' + tabId + '"]').parent().remove();
        
        // Check if submenu is empty
        if ($('.ai-saved-actions-submenu li').length === 0) {
            $('.ai-saved-actions-submenu').html('<li class="ai-no-saved-actions"><?php esc_html_e('No saved actions', 'chatbot'); ?></li>');
        }
        
        // Switch back to Add New Action tab
        switchTab('ai-new-action-tab', $('.ai-sidebar-link[href="#ai-new-action-tab"]'));
        
        updateAiActionsPreview();
    });

    // --- UPDATE HEADER DYNAMICALLY ---
    $(document).on('keyup', '.ai-form-title-input', function() {
        var newTitle = $(this).val();
        var paneId = $(this).closest('.ai-content-pane').attr('id');
        var sidebarLink = $('.ai-sidebar-link[href="#' + paneId + '"]');
        
        if (newTitle.trim() === '') {
            newTitle = '<?php esc_html_e('Untitled Action', 'chatbot'); ?>';
        }
        sidebarLink.text(newTitle);
        updateAiActionsPreview();
    });

    // --- PREVIEW UPDATING FUNCTION ---
    function updateAiActionsPreview() {
        var previewContainer = $('#ai-actions-live-preview');
        previewContainer.empty();
        
        var titles = [];
        $('.saved-ai-action .ai-form-title-input').each(function() {
            var val = $(this).val().trim();
            if(val) {
                titles.push(val);
            }
        });
        
        if(titles.length === 0) {
            previewContainer.append('<span class="playground-ai-action-btn is-empty"><?php esc_html_e('No Actions Created Yet', 'chatbot'); ?></span>');
        } else {
            $.each(titles, function(index, title) {
                previewContainer.append('<span class="playground-ai-action-btn">' + title + '</span>');
            });
        }
    }

    // --- PLAYGROUND INTERACTIVE LOGIC ---
    var aiContext = [];
    
    var playgroundIcon = '<?php echo esc_url( $wp_chatbot_custom_icon_path ); ?>';

    function appendUserMessage(text) {
        var msgHtml = '<div class="qcld-ai-pg-user-row">' +
                        '<div class="qcld-ai-pg-user-bubble">' + text + '</div>' +
                      '</div>';
        $('#ai-actions-playground-messages').append(msgHtml);
        scrollToBottom();
    }
    
    function appendBotMessage(text) {
        var msgHtml = '<div class="qcld-ai-pg-bot-row">' +
                        '<div class="qcld-ai-pg-avatar" style="background-image: url(\'' + playgroundIcon + '\');"></div>' +
                        '<div class="qcld-ai-pg-bubble">' + text + '</div>' +
                      '</div>';
        $('#ai-actions-playground-messages').append(msgHtml);
        scrollToBottom();
    }
    
    function appendLoader() {
        var msgHtml = '<div id="playground-loader" class="qcld-ai-pg-bot-row">' +
                        '<div class="qcld-ai-pg-avatar" style="background-image: url(\'' + playgroundIcon + '\');"></div>' +
                        '<div class="qcld-ai-pg-bubble is-loading"><i>Thinking...</i></div>' +
                      '</div>';
        $('#ai-actions-playground-messages').append(msgHtml);
        scrollToBottom();
    }
    
    function scrollToBottom() {
        var container = $('#ai-actions-playground-container');
        container.scrollTop(container[0].scrollHeight);
    }
    
    function formatBotResponse(text) {
        // Remove internal tracking flag
        text = text.replace(/__AI_FORM_IN_PROGRESS__/g, '');
        
        // Extract and format AI_FORM_DATA block
        // This regex optionally matches surrounding HTML tags to ensure they are replaced too, preventing broken tags.
        var regex = /(?:<[^>]+>)*\s*AI_FORM_DATA[\s\S]*?__AI_FORM_DATA_END__\s*(?:<\/[^>]+>)*/g;
        text = text.replace(regex, function(match) {
            try {
                // Extract just the JSON part from the match (from the first '{' to the last '}')
                var jsonMatch = match.match(/\{[\s\S]*\}/);
                if (!jsonMatch) {
                    return match;
                }
                var jsonStr = jsonMatch[0];
                var data = JSON.parse(jsonStr.trim());
                var html = '<div class="qcld-ai-pg-form-data">';
                if (data.form_title) {
                    html += '<strong>' + data.form_title + '</strong>';
                }
                if (data.data) {
                    html += '<ul>';
                    for (var key in data.data) {
                        html += '<li style="margin-bottom: 3px;"><strong>' + key + ':</strong> ' + data.data[key] + '</li>';
                    }
                    html += '</ul>';
                }
                html += '</div>';
                return html;
            } catch (e) {
                return match; // Return original if parsing fails
            }
        });

        var cleanResponse = text.replace(/(\w+)\n/g,'$1 '); 
        cleanResponse = cleanResponse.replace(/```(.*?)```/gs, '<pre style="background: #f4f4f4; padding: 8px; border-radius: 5px; overflow-x: auto; font-size: 12px;">$1</pre>');
        cleanResponse = cleanResponse.replace(/`(.*?)`/g, '<code style="background: #f4f4f4; padding: 2px 4px; border-radius: 3px;">$1</code>');
        cleanResponse = cleanResponse.replace(/\n/g, '<br>');
        return cleanResponse;
    }
    
    function sendToAI(text, actionPrompt) {
        appendUserMessage(text);
        appendLoader();
        
        aiContext.push({role: 'user', content: text});
        if (aiContext.length > 10) {
            aiContext.shift();
        }
        
        var activeProvider = '<?php echo esc_js($default_provider); ?>';
        var actionMap = {
            'openai': 'qcld_openai_response',
            'gemini': 'qcld_gemini_response',
            'claude': 'claude_response',
            'grok': 'qcld_grok_response',
            'openrouter': 'openrouter_response'
        };
        var ajaxAction = actionMap[activeProvider] || 'qcld_openai_response';
        
        var data = {
            action: ajaxAction,
            keyword: text,
            ai_history: JSON.stringify(aiContext),
            is_ai_actions_playground: 1,
            action_prompt: actionPrompt || '',
            nonce: '<?php echo esc_js( wp_create_nonce( 'wp_chatbot' ) ); ?>' 
        };
        
        $.post(ajaxurl, data, function(res) {
            $('#playground-loader').remove();
            
            var json = res;
            if (typeof res === 'string') {
                try {
                    json = $.parseJSON(res);
                } catch(e) {}
            }
            
            if (json && json.status === 'success') {
                var reply = json.message;
                aiContext.push({role: 'assistant', content: reply});
                if (aiContext.length > 10) {
                    aiContext.shift();
                }
                appendBotMessage(formatBotResponse(reply));
            } else {
                appendBotMessage('<?php esc_html_e('Sorry, there was an error processing your request.', 'chatbot'); ?>');
            }
        }).fail(function() {
            $('#playground-loader').remove();
            appendBotMessage('<?php esc_html_e('Error connecting to AI service.', 'chatbot'); ?>');
        });
    }

    $(document).on('click', '.playground-ai-action-btn', function() {
        if ($(this).hasClass('is-empty')) {
            return;
        }
        var text = $(this).text().trim();
        
        var matchedPrompt = '';
        $('.ai-form-title-input').each(function() {
            if ($(this).val().trim().toLowerCase() === text.toLowerCase()) {
                matchedPrompt = $(this).closest('.ai-content-pane').find('textarea[name="ai_form_prompt[]"]').val() || '';
            }
        });
        
        aiContext = [];
        sendToAI(text, matchedPrompt);
    });
    
    $('#playground-input').on('keypress', function(e) {
        if(e.which === 13) {
            var text = $(this).val().trim();
            if(text) {
                $(this).val('');
                sendToAI(text);
            }
        }
    });

    $('#playground-send-btn').on('click', function() {
        var text = $('#playground-input').val().trim();
        if(text) {
            $('#playground-input').val('');
            sendToAI(text);
        }
    });
    
    $('#ai-playground-refresh').on('click', function() {
        var $messages = $('#ai-actions-playground-messages');
        $messages.children(':not(:first)').remove();
        updateAiActionsPreview();
    });
    
    $(document).on('click', '.qcld-ai-history-pro-tab', function(e) {
        e.preventDefault();
        return false;
    });
    
    // --- INITIALIZATION ---
    updateAiActionsPreview();
    
    // Make sure initial state shows first tab if history or saved tab isn't already active (fallback)
    if ($('.ai-sidebar-link.active').length === 0) {
        $('.ai-sidebar-link[href="#ai-new-action-tab"]').addClass('active');
        $('#ai-new-action-tab').show();
    }
});
</script>
