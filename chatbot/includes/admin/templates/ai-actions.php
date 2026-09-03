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
<div class="wrap qcld-main-wrapper">
    <div class="qcld-wp-chatbot-wrap-header-aisection">
        <div class="qcld-wp-chatbot-wrap-header">
            <div class="qcld-wp-chatbot-wrap-header-logo">
                <a href="#" class="qcld-wp-chatbot-wrap-site__logo">
                    <img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel 
                </a>
                <p><strong>Core Version:</strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
            </div>
            <ul class="qcld-wp-chatbot-wrap-version-wrapper">
                <li><a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a></li>
            </ul>
        </div>
    </div>
    
    <div class="qcl-openai" style="margin-top: 20px;">
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            
            <!-- LEFT SIDEBAR -->
            <div style="width: 250px; flex-shrink: 0; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <ul class="qcld-ai-sidebar-menu" style="margin: 0; padding: 0; list-style: none;">
                    <li style="border-bottom: 1px solid #eee;">
                        <a href="#ai-new-action-tab" class="ai-sidebar-link active" style="display: block; padding: 15px; text-decoration: none; color: #444; font-weight: 600; cursor: pointer; transition: 0.2s;">+ <?php esc_html_e('Add New Action', 'chatbot'); ?></a>
                    </li>
                    
                    <li style="border-bottom: 1px solid #eee;">
                        <a href="#" class="ai-sidebar-parent-link" style="display: flex; justify-content: space-between; padding: 15px; text-decoration: none; color: #444; font-weight: 600; cursor: pointer; background: #fcfcfc;">
                            <?php esc_html_e('Saved Actions', 'chatbot'); ?> 
                            <span class="dashicons dashicons-arrow-down-alt2" style="margin-top: 2px; transition: 0.2s;"></span>
                        </a>
                        <ul class="ai-saved-actions-submenu" style="margin: 0; padding: 0; list-style: none; display: block; border-top: 1px solid #eee;">
                            <?php 
                            $counter = 1;
                            if ( ! empty( $saved_forms ) ) {
                                foreach ( $saved_forms as $form ) {
                                    $tab_id = 'ai-action-tab-' . $counter;
                                    echo '<li><a href="#' . esc_attr($tab_id) . '" class="ai-sidebar-link ai-sidebar-saved-link" data-id="'.esc_attr($tab_id).'" style="display: block; padding: 10px 15px 10px 30px; text-decoration: none; color: #555; font-size: 13px; border-bottom: 1px solid #f5f5f5;">' . esc_html( $form['title'] ) . '</a></li>';
                                    $counter++;
                                }
                            } else {
                                echo '<li class="ai-no-saved-actions" style="padding: 10px 15px 10px 30px; color: #999; font-size: 12px; font-style: italic;">' . esc_html__('No saved actions', 'chatbot') . '</li>';
                            }
                            ?>
                        </ul>
                    </li>
                    
                </ul>
            </div>
            
            <!-- MAIN CONTENT AREA -->
            <div style="flex-grow: 1; min-width: 0; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <form action="" method="POST" id="ai-actions-main-form" style="margin: 0; padding: 0;">
                    <?php wp_nonce_field( 'wp_chatbot_ai_actions', '_wpnonce' ); ?>
                    
                    <!-- NEW ACTION TAB -->
                    <div id="ai-new-action-tab" class="ai-content-pane active" style="padding: 20px;">
                        <?php 
                        $no_ai_active = (get_option( 'ai_enabled' ) != 1 &&
                            get_option( 'qcld_openrouter_enabled' ) != 1 &&
                            get_option( 'qcld_gemini_enabled' ) != 1 &&
                            get_option( 'qcld_grok_enabled' ) != 1 &&
                            get_option( 'qcld_claude_enabled' ) != 1);
                        if($no_ai_active): 
                        ?>
                        <div style="color: #fff; background: #e64340; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <?php esc_html_e('No AI connection is active. Please enable AI integration.', 'chatbot'); ?>
                        </div>
                        <?php endif; ?>
                        <h3 style="margin-top: 0; font-size: 18px;"><?php esc_html_e('Choose a Template', 'chatbot'); ?></h3>
                        <p style="color: #666;"><?php esc_html_e('Select a template below to create a new AI Action. You can customize it on the next screen.', 'chatbot'); ?></p>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                            <!-- Blank -->
                            <div class="ai-template-card" data-title="" data-prompt="" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-plus-alt2" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Blank Prompt</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Start from scratch and build your own custom AI action.</p>
                            </div>
                            
                            <!-- 1. Web Agency Quote -->
                            <div class="ai-template-card" data-title="Web Agency Quote" data-prompt="You are a project discovery assistant for a web development agency.
Ask the user the following questions conversationally, one at a time:

1. What type of project are they planning (e.g., WordPress plugin, custom web app, WooCommerce store)?
2. What are the core features or problems they need solved?
3. What is their estimated budget range and target launch timeline?
4. What is their full name, work email, and preferred contact method?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-desktop" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Web Agency Quote</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Software, agency, and freelance service inquiries.</p>
                            </div>

                            <!-- 2. Real Estate Inquiry -->
                            <div class="ai-template-card" data-title="Real Estate Inquiry" data-prompt="You are a real estate assistant helping visitors find their ideal property.
Collect these details step-by-step:

1. Are they looking to Buy, Rent, or Sell?
2. What property type and location/neighborhood are they interested in?
3. How many bedrooms and bathrooms do they require?
4. What is their target price or monthly budget range?
5. What is their full name, phone number, and email address to schedule a viewing?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-building" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Real Estate Inquiry</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Qualifying buyers and renters for property showings.</p>
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
" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-sos" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Priority Support</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Bug triage and diagnostic data gathering for helpdesks.</p>
                            </div>

                            <!-- 4. SaaS Demo Booking -->
                            <div class="ai-template-card" data-title="SaaS Demo Booking" data-prompt="You are an onboarding specialist for a SaaS platform.
Qualify visitors requesting a product demo by asking:

1. What company do they represent, and what is their current team or company size?
2. What is the primary bottleneck or workflow they want our software to automate?
3. What CRM or marketing tools are in their current tech stack?
4. What is their full name, business email, and preferred day/time for a 15-minute demo?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-chart-pie" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">SaaS Demo Booking</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Lead qualification and sales routing for software platforms.</p>
                            </div>

                            <!-- 5. E-Commerce Wholesale -->
                            <div class="ai-template-card" data-title="E-Commerce Wholesale" data-prompt="You are a sales assistant helping wholesale and bulk-order customers.
Gather order specifications:

1. What product SKU or item category are they interested in?
2. What estimated quantity or unit count do they plan to purchase?
3. Do they require custom branding/labels or standard packaging?
4. What is their target delivery deadline and destination country/postal code?
5. What is their company name, contact person, and billing email?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-cart" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">E-Commerce Wholesale</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">B2B wholesale pricing and custom merchandise for WooCommerce.</p>
                            </div>

                            <!-- 6. Legal Case Intake -->
                            <div class="ai-template-card" data-title="Legal Case Intake" data-prompt="You are a legal intake assistant for a law firm.
State clearly that this chat does not establish an attorney-client relationship, then collect:

1. What area of law do they need assistance with (e.g., Personal Injury, Family Law, Business Dispute, Estate Planning)?
2. A brief summary of the situation and approximately when it occurred.
3. Are there any upcoming deadlines, court dates, or active lawsuits already filed?
4. What is their full name, phone number, and email address?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-portfolio" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Legal Case Intake</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Pre-screening legal leads for practice area viability and deadlines.</p>
                            </div>

                            <!-- 7. Healthcare Booking -->
                            <div class="ai-template-card" data-title="Healthcare Booking" data-prompt="You are a patient coordinator assistant for a dental and medical clinic.
Collect scheduling details without providing medical advice:

1. Are they a new or existing patient, and what is the primary reason for their visit?
2. Do they have medical/dental insurance, and if so, who is the provider?
3. What days of the week and times of day work best for their appointment?
4. What is their full name, date of birth, phone number, and email address?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-heart" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Healthcare Booking</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Clinic patient scheduling, primary symptoms, and insurance check.</p>
                            </div>

                            <!-- 8. Event Planning -->
                            <div class="ai-template-card" data-title="Event Planning" data-prompt="You are an event specialist assisting clients with catering and planning inquiries.
Guide them through these questions:

1. What type of event are they planning (e.g., Wedding, Corporate Gala, Birthday)?
2. What is the estimated event date, venue location, and expected guest count?
3. What dining/service style do they prefer (e.g., Plated Multi-Course, Buffet, Cocktail/Hors d'oeuvres)?
4. What is their approximate overall budget?
5. What is their contact name, organization (if applicable), phone number, and email?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-calendar-alt" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Event Planning</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Event venue, banquet, and catering inquiries.</p>
                            </div>

                            <!-- 9. Auto Dealership -->
                            <div class="ai-template-card" data-title="Auto Dealership" data-prompt="You are a sales concierge for an automotive dealership.
Collect test drive details:

1. Which vehicle model, trim, or year are they interested in test-driving?
2. Do they have a trade-in vehicle? If yes, ask for the Year, Make, Model, and approximate mileage.
3. What is their preferred payment path (Financing, Leasing, or Cash purchase)?
4. What date and time would they like to schedule their test drive?
5. What is their full name, mobile number, and email address?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-car" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Auto Dealership</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Test-drive bookings and vehicle trade-in estimates.</p>
                            </div>

                            <!-- 10. Education Admissions -->
                            <div class="ai-template-card" data-title="Education Admissions" data-prompt="You are an admissions advisor for an educational training academy.
Ask the prospective student:

1. Which program or certification do they want to enroll in?
2. What is their current background or experience level (Beginner, Intermediate, Advanced)?
3. Are they looking for Full-Time (Immersive) or Part-Time study?
4. What is their target cohort start date?
5. What is their full name, WhatsApp/phone number, and email address?

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-welcome-learn-more" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Education Admissions</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Online course and bootcamp enrollment qualification.</p>
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

" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
                                <div style="color: #0073aa; margin-bottom: 15px;"><span class="dashicons dashicons-businessman" style="font-size: 40px; width: 40px; height: 40px;"></span></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 15px;">Senior PHP Programmer Assessment</h4>
                                <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.4;">Screening candidates for a Senior PHP Developer position.</p>
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
                            <div id="<?php echo esc_attr($tab_id); ?>" class="ai-content-pane ai-form-item saved-ai-action" style="display: none; padding: 20px;">
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                                    <h3 style="margin: 0; font-size: 18px;"><?php esc_html_e('Edit Action', 'chatbot'); ?></h3>
                                    <button type="button" class="button remove-ai-form" style="color: #a00; border-color: #a00;"><?php esc_html_e( 'Delete Action', 'chatbot' ); ?></button>
                                </div>
                                
                                <h2 class="nav-tab-wrapper wpbot-ai-inner-tabs" style="margin-bottom: 20px;">
                                    <a href="#ai-inner-settings-<?php echo esc_attr($counter); ?>" class="nav-tab nav-tab-active" style="cursor: pointer;"><?php esc_html_e('Settings', 'chatbot'); ?></a>
                                    <a href="#" class="nav-tab" style="cursor: not-allowed; opacity: 0.6;" onclick="event.preventDefault(); return false;"><?php esc_html_e('History', 'chatbot'); ?> <span style="background: #e64340; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 5px; vertical-align: middle;">PRO</span></a>
                                </h2>
                                
                                <div id="ai-inner-settings-<?php echo esc_attr($counter); ?>" class="ai-inner-tab-content">
                                    <div style="margin-bottom: 15px;">
                                        <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Action Title', 'chatbot'); ?></label>
                                        <input type="text" name="ai_form_title[]" class="form-control ai-form-title-input" style="width: 100%; max-width: 500px;" placeholder="<?php esc_attr_e('e.g., Hotel Booking', 'chatbot'); ?>" value="<?php echo esc_attr( $form['title'] ); ?>" />
                                    </div>
                                    <div style="margin-bottom: 15px;">
                                        <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('AI Prompt', 'chatbot'); ?></label>
                                        <textarea name="ai_form_prompt[]" class="form-control" style="width: 100%;" rows="15" placeholder="<?php esc_attr_e('Enter your prompt here...', 'chatbot'); ?>"><?php echo esc_textarea( $form['prompt'] ); ?></textarea>
                                    </div>
                                    
                                    <div class="cxsc-settings-blocks" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                                        <div>
                                            <input type="hidden" name="ai_form_email[]" class="ai-email-val" value="<?php echo esc_attr( isset($form['email']) ? $form['email'] : 1 ); ?>">
                                            <input type="checkbox" id="<?php echo esc_attr( $chk_email_id ); ?>" <?php echo ( !isset($form['email']) || $form['email'] == 1 ) ? 'checked' : ''; ?> onchange="jQuery(this).prev('.ai-email-val').val(this.checked ? 1 : 0);">
                                            <label for="<?php echo esc_attr( $chk_email_id ); ?>"><strong><?php esc_html_e('Email the data', 'chatbot'); ?></strong></label>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <label style="font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px;"><?php esc_html_e('Send to Email(s)', 'chatbot'); ?></label>
                                            <input type="text" name="ai_form_email_addresses[]" class="form-control" style="width: 100%; max-width: 460px; font-size: 13px;" placeholder="<?php echo esc_attr( get_option('qlcd_wp_chatbot_admin_email', get_option('admin_email')) ); ?>" value="<?php echo esc_attr( isset($form['email_addresses']) ? $form['email_addresses'] : '' ); ?>">
                                            <p style="margin: 4px 0 0; font-size: 11px; color: #888;"><?php esc_html_e('Comma-separated. Leave blank to use the default admin email.', 'chatbot'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="ai-inner-history-<?php echo esc_attr($counter); ?>" class="ai-inner-tab-content" style="display: none;">
                                    <div id="ai-inner-history-content-<?php echo esc_attr($counter); ?>">
                                        <p style="color: #666; font-style: italic;"><?php esc_html_e('Loading history...', 'chatbot'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php 
                                $counter++;
                            endforeach;
                        endif;
                        ?>
                    </div>
                    
                    <div id="ai-save-wrapper" style="padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #eee; display: flex;flex-direction: column;">

                        <span style="color: #666; font-size: 13px;">Don't forget to save your changes!</span>

                        <span style="color: #f10b0bff; font-size: 16px; font-weight: bold; margin-top: 5px; margin-bottom: 5px;">Create powerful AI Actions with Prompt to collect information and send to your email.</span>
                         <span style="color: #f10b0bff; font-size: 16px; font-weight: bold; margin-top: 5px; margin-bottom: 10px;">After creating an AI Action, you can add it to the Active Start Menu from <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot&tab=startmenu' ) ); ?>">Settings->Start Menu</a></span>
                        <input style="max-width: 224px;" type="submit" name="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Save Settings', 'chatbot' ); ?>" />
                    </div>
                </form>
            </div>
            
            <!-- RIGHT PLAYGROUND PREVIEW -->
            <div class="card right-preview" style="width: 480px; flex-shrink: 0; background: #f5f5f5; border: 1px solid #e5e5e5; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
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
                ?>
                <div style="background: #4a154b; color: white; padding: 15px; font-weight: bold; text-align: center; border-top-left-radius: 9px; border-top-right-radius: 9px; position: relative;">
                    <?php esc_html_e('Live AI Playground', 'chatbot'); ?>
                    <button type="button" id="ai-playground-refresh" title="<?php esc_attr_e('Refresh Chat', 'chatbot'); ?>" style="position: absolute; right: 15px; top: 12px; background: transparent; border: none; color: white; cursor: pointer; padding: 0;">
                        <span class="dashicons dashicons-image-rotate"></span>
                    </button>
                </div>
                <div id="ai-actions-playground-container" style="padding: 15px; display: flex; flex-direction: column; height: 400px; overflow-y: auto;">
                    <div id="ai-actions-playground-messages" style="display: flex; flex-direction: column; justify-content: flex-end; flex: 1;">
                        <div style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px;">
                            <div style="width: 30px; height: 30px; background: #4a154b; border-radius: 50%; flex-shrink: 0; background-image: url('<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-1.png'); background-size: cover; background-position: center;"></div>
                            <div style="background: white; padding: 12px; border-radius: 15px; border-bottom-left-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); width: 100%;">
                                <p style="margin-top: 0; margin-bottom: 15px; font-size: 13px; color: #333; line-height: 1.4;"><?php esc_html_e('Hello! I am here to find what you need. What are you looking for?', 'chatbot'); ?></p>
                                <div id="ai-actions-live-preview" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <!-- Javascript will populate this -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="padding: 12px; background: white; border-top: 1px solid #eee; display: flex; gap: 8px; border-bottom-left-radius: 9px; border-bottom-right-radius: 9px;">
                    <input type="text" id="playground-input" placeholder="<?php esc_attr_e('Send a message...', 'chatbot'); ?>" style="width: 100%; background: #f0f0f0; border: none; border-radius: 20px; padding: 8px 12px; color: #333; font-size: 13px; outline: none;">
                    <button type="button" id="playground-send-btn" class="button button-primary" style="border-radius: 50%; padding: 0; width: 40px; height: 34px; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span class="dashicons dashicons-arrow-right-alt" style="line-height: 1.8;"></span>
                    </button>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
/* CSS for the new layout */
.ai-sidebar-link:hover, .ai-sidebar-link.active {
    background: #f0f0f1;
    color: #0073aa !important;
    box-shadow: inset 4px 0 0 #0073aa;
}
.ai-sidebar-saved-link:hover, .ai-sidebar-saved-link.active {
    background: #eef7fd;
    color: #0073aa !important;
    box-shadow: inset 4px 0 0 #0073aa;
}
.ai-template-card:hover {
    border-color: #0073aa !important;
    box-shadow: 0 5px 15px rgba(0,115,170,0.1);
    transform: translateY(-2px);
}
.ai-content-pane {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
jQuery(document).ready(function($) {
    // --- SIDEBAR TAB SWITCHING LOGIC ---
    function switchTab(targetId, linkObj) {
        $('.ai-content-pane').hide();
        $('.ai-sidebar-link').removeClass('active');
        
        $('#' + targetId).show();
        if (linkObj) {
            linkObj.addClass('active');
        } else {
            $('.ai-sidebar-link[href="#' + targetId + '"]').addClass('active');
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
                $('.ai-sidebar-parent-link .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        }
    }

    $(document).on('click', '.ai-sidebar-link', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        switchTab(target, $(this));
    });
    
    // --- SAVED ACTIONS SUBMENU TOGGLE ---
    $('.ai-sidebar-parent-link').on('click', function(e) {
        e.preventDefault();
        var submenu = $(this).next('.ai-saved-actions-submenu');
        var icon = $(this).find('.dashicons');
        
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
        var linkHtml = '<li><a href="#' + tabId + '" class="ai-sidebar-link ai-sidebar-saved-link" data-id="' + tabId + '" style="display: block; padding: 10px 15px 10px 30px; text-decoration: none; color: #555; font-size: 13px; border-bottom: 1px solid #f5f5f5;">' + displayTitle + '</a></li>';
        $('.ai-saved-actions-submenu').append(linkHtml);
        
        // Ensure submenu is open
        $('.ai-saved-actions-submenu').slideDown(200);
        $('.ai-sidebar-parent-link .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        
        // 2. Add Form Pane
        var template = `
            <div id="` + tabId + `" class="ai-content-pane ai-form-item" style="display: none; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 18px;"><?php esc_html_e('Edit Action', 'chatbot'); ?></h3>
                    <button type="button" class="button remove-ai-form" style="color: #a00; border-color: #a00;"><?php esc_html_e( 'Delete Action', 'chatbot' ); ?></button>
                </div>
                
                <h2 class="nav-tab-wrapper wpbot-ai-inner-tabs" style="margin-bottom: 20px;">
                    <a href="#ai-inner-settings-` + uniqueId + `" class="nav-tab nav-tab-active" style="cursor: pointer;"><?php esc_html_e('Settings', 'chatbot'); ?></a>
                    <a href="#" class="nav-tab" style="cursor: not-allowed; opacity: 0.6;" onclick="event.preventDefault(); return false;"><?php esc_html_e('History', 'chatbot'); ?> <span style="background: #e64340; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 5px; vertical-align: middle;">PRO</span></a>
                </h2>
                
                <div id="ai-inner-settings-` + uniqueId + `" class="ai-inner-tab-content">
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Action Title', 'chatbot'); ?></label>
                        <input type="text" name="ai_form_title[]" class="form-control ai-form-title-input" style="width: 100%; max-width: 500px;" placeholder="<?php esc_attr_e('e.g., Hotel Booking', 'chatbot'); ?>" value="` + title + `" />
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('AI Prompt', 'chatbot'); ?></label>
                        <textarea name="ai_form_prompt[]" class="form-control" style="width: 100%;" rows="8" placeholder="<?php esc_attr_e('Enter your prompt here...', 'chatbot'); ?>">` + prompt + `</textarea>
                    </div>
                    
                    <div class="cxsc-settings-blocks" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">

                        <div>
                            <input type="hidden" name="ai_form_email[]" class="ai-email-val" value="1">
                            <input type="checkbox" id="` + chk_email_id + `" checked onchange="jQuery(this).prev(\'.ai-email-val\').val(this.checked ? 1 : 0);">
                            <label for="` + chk_email_id + `"><strong><?php esc_html_e('Email the data', 'chatbot'); ?></strong></label>
                        </div>
                        <div style="margin-top: 10px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px;"><?php esc_html_e('Send to Email(s)', 'chatbot'); ?></label>
                            <input type="text" name="ai_form_email_addresses[]" class="form-control" style="width: 100%; max-width: 460px; font-size: 13px;" placeholder="<?php echo esc_attr( get_option('qlcd_wp_chatbot_admin_email', get_option('admin_email')) ); ?>" value="">
                            <p style="margin: 4px 0 0; font-size: 11px; color: #888;"><?php esc_html_e('Comma-separated. Leave blank to use the default admin email.', 'chatbot'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div id="ai-inner-history-` + uniqueId + `" class="ai-inner-tab-content" style="display: none;">
                    <div id="ai-inner-history-content-` + uniqueId + `">
                        <p style="color: #666; font-style: italic;"><?php esc_html_e('Loading history...', 'chatbot'); ?></p>
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
            $('.ai-saved-actions-submenu').html('<li class="ai-no-saved-actions" style="padding: 10px 15px 10px 30px; color: #999; font-size: 12px; font-style: italic;"><?php esc_html_e('No saved actions', 'chatbot'); ?></li>');
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
            previewContainer.append('<span style="background: #f0f0f0; border: 1px solid #ddd; padding: 5px 12px; border-radius: 15px; font-size: 12px; color: #555; cursor: default;"><?php esc_html_e('No Actions Created Yet', 'chatbot'); ?></span>');
        } else {
            $.each(titles, function(index, title) {
                previewContainer.append('<span class="playground-ai-action-btn" style="background: #e9f0ff; border: 1px solid #b3ccff; padding: 5px 12px; border-radius: 15px; font-size: 12px; color: #0044cc; cursor: pointer; display: inline-block; transition: all 0.2s;">' + title + '</span>');
            });
        }
    }

    // --- PLAYGROUND INTERACTIVE LOGIC ---
    var aiContext = [];
    
    function appendUserMessage(text) {
        var msgHtml = '<div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">' +
                        '<div style="background: #0044cc; color: white; padding: 8px 12px; border-radius: 15px; border-bottom-right-radius: 5px; max-width: 80%; font-size: 13px;">' + 
                        text + 
                        '</div>' +
                      '</div>';
        $('#ai-actions-playground-messages').append(msgHtml);
        scrollToBottom();
    }
    
    function appendBotMessage(text) {
        var msgHtml = '<div style="display: flex; gap: 8px; align-items: flex-end; margin-bottom: 12px;">' +
                        '<div style="width: 30px; height: 30px; background: #4a154b; border-radius: 50%; flex-shrink: 0; background-image: url(\'<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-1.png\'); background-size: cover; background-position: center;"></div>' +
                        '<div style="background: white; padding: 8px 12px; border-radius: 15px; border-bottom-left-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); max-width: 80%; font-size: 13px; color: #333; line-height: 1.4; overflow-wrap: break-word;">' + 
                        text + 
                        '</div>' +
                      '</div>';
        $('#ai-actions-playground-messages').append(msgHtml);
        scrollToBottom();
    }
    
    function appendLoader() {
        var msgHtml = '<div id="playground-loader" style="display: flex; gap: 8px; align-items: flex-end; margin-bottom: 12px;">' +
                        '<div style="width: 30px; height: 30px; background: #4a154b; border-radius: 50%; flex-shrink: 0; background-image: url(\'<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-1.png\'); background-size: cover; background-position: center;"></div>' +
                        '<div style="background: white; padding: 8px 12px; border-radius: 15px; border-bottom-left-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 13px; color: #999;">' + 
                        '<i>Thinking...</i>' + 
                        '</div>' +
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
                var html = '<div style="background: #f4f4f4; padding: 10px; border-left: 3px solid #0044cc; margin: 10px 0; border-radius: 3px;">';
                if (data.form_title) {
                    html += '<strong style="display:block; margin-bottom: 5px; color: #0044cc; text-transform: capitalize;">' + data.form_title + '</strong>';
                }
                if (data.data) {
                    html += '<ul style="margin: 0; padding-left: 15px; font-size: 13px; color: #333;">';
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
    
    $(document).on('mouseenter', '.playground-ai-action-btn', function() {
        $(this).css({background: '#d0e0ff', borderColor: '#8ab4f8'});
    }).on('mouseleave', '.playground-ai-action-btn', function() {
        $(this).css({background: '#e9f0ff', borderColor: '#b3ccff'});
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
