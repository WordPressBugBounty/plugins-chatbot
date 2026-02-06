<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>

<div class="wrap qcld-main-wrapper">
  <h1 style="display:none">
    <?php esc_html_e('chatbot', 'chatbot'); ?>
  </h1>

<div class="qcld-wp-chatbot-wrap-header">

    <div class="qcld-wp-chatbot-wrap-header-logo"><a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
    <p><strong>Core Version:</strong> v<?php echo QCLD_wpCHATBOT_VERSION; ?></p>
    </div>
    <ul class="qcld-wp-chatbot-wrap-version-wrapper">
        <li>
     <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a> 
      
      </li>
	  </ul>
</div>

<div class="qcld-wp-chatbot-wrap-header-doc">

    <a href="https://wpbot.pro/docs/" target="_blank" class="qcld-wp-chatbot-wrap--doc_logo">
      <img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/doc.jpg' ); ?>" alt="Dialogflow CX"> 
    <div class="docs_icon"><span class="dashicons dashicons-media-document"></span></div>
    
    </a>
    
    <div class="qcld-wp-chatbot-wrap-header-doc-details">

      <h3><?php esc_html_e('Get Started with WPBot', 'chatbot'); ?></h3>
      <p><?php esc_html_e('Do more than just chat with your ChatBot! More Leads, Conversions & Satisfied customers while saving time & increasing your business opportunities. WPBot is the best ChatBot for WordPress to improve user engagement, Generate Leads, & provide Automated Live customer support across your website & social platforms.', 'chatbot'); ?></p>
      
       <div class="qcld-wp-chatbot-wrap-header-doc-button">
      
      <a href="https://wpbot.pro/docs/knowledgebase/getting-started-with-chatbot/" class="GetStartedwithYourChatBot" target="_blank">Get Started with Your ChatBot</a>
      <a href="https://wpbot.pro/docs/" target="_blank">Documentation</a>
      </div>
    </div>
</div>

<div class="wp-chatbot-wrap">
<div class="icon32"><br>
</div>
<form action="<?php echo esc_url($action,  'chatbot'); ?>" method="POST" id="wp-chatbot-admin-form"
          enctype="multipart/form-data">      
  <div class="form-container">

    <section class="wp-chatbot-tab-container-inner">
      <div class="wp-chatbot-tabs wp-chatbot-tabs-style-flip">
        <nav>
          <ul>
            <li class="tab-current" tab-data="started"><a href="<?php echo esc_url($action).'&tab=started' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-admin-home"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('Getting Started', 'chatbot'); ?>
              </span> </a></li>
            <li  tab-data="general"><a href="<?php echo esc_url($action) .'&tab=general' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-admin-generic"></span></span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('General settings', 'chatbot'); ?>
              </span> </a></li>
            <li tab-data="language"><a href="<?php echo esc_url($action) .'&tab=language' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-translation"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('Change Language', 'chatbot'); ?>
              </span> </a></li>
            <li tab-data="social"><a href="<?php echo esc_url($action) .'&tab=social' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-image-filter"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('Button Configurations', 'chatbot'); ?>
              </span> </a></li>
           <div class="cxsc-settings_openai_border"> <a href="<?php echo esc_url( $action) .'_openAi' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-update"></span>
            </span> <span class=""> <?php esc_html_e('AI Settings', 'chatbot'); ?> </span> 
            </a>
</div>


 
            <li tab-data="themes"><a href="<?php echo esc_url($action) .'&tab=themes' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-share-alt"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('Icons & Themes', 'chatbot'); ?>
              </span> </a></li>
            <li tab-data="support"><a href="<?php echo esc_url($action).'&tab=support' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-info-outline"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('FAQ Builder', 'chatbot'); ?>
              </span> </a></li>
            <li tab-data="startmenu"><a href="<?php echo esc_url($action).'&tab=startmenu' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-menu"></span> </span> <span class="wpwbot-admin-tab-name"><?php esc_html_e('Start Menu', 'chatbot'); ?></span> </a></li>
            
      
         <li tab-data="ai"><a href="<?php echo esc_url($action).'&tab=ai' ?>"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-format-chat"></span> </span> <span class="wpwbot-admin-tab-name">
              <?php esc_html_e('Dialogflow', 'chatbot'); ?>
              </span> </a></li>
            
   
            <li tab-data="<?php echo esc_url('rpl'); ?>" class="conversational"><a href="<?php echo esc_url($action).'&tab=rpl' ?>"> 
              <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-feedback"></span>
            </span> <span class="wpwbot-admin-tab-name"> <?php  esc_html_e('Conversational Form', 'chatbot'); ?> 
            </span> 
          </a>
            </li>




            <li tab-data="custom_css"> <a href="<?php echo esc_url($action).'&tab=ai'?>"> 
              <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-editor-code"></span>
            </span> <span class="wpwbot-admin-tab-name"> <?php esc_html_e('Custom CSS', 'chatbot'); ?></span> 
          </a>
            </li>


          </ul>
          
<div class="text-center mt-5 qcld-goPreemium">

<a href="https://www.wpbot.pro/" target="_blank"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                            <path d="M19.6799 17.28H4.31988C3.52596 17.28 2.87988 17.9261 2.87988 18.72C2.87988 19.5139 3.52596 20.16 4.31988 20.16H19.6799C20.4738 20.16 21.1199 19.5139 21.1199 18.72C21.1199 17.9261 20.4738 17.28 19.6799 17.28Z" fill="#FF9900"></path>
                            <path d="M22.08 2.88C21.0211 2.88 20.16 3.74114 20.16 4.8C20.16 5.51137 20.5536 6.1267 21.1305 6.45886C20.0198 9.08925 18.287 10.703 16.6675 10.5571C14.8665 10.4102 13.3977 8.28094 12.5875 4.71839C13.6262 4.45533 14.4 3.51933 14.4 2.4C14.4 1.07616 13.3238 0 12 0C10.6761 0 9.59995 1.07616 9.59995 2.4C9.59995 3.51938 10.3737 4.45538 11.4124 4.71839C10.6022 8.28094 9.13336 10.4102 7.33242 10.5571C5.71964 10.703 3.97913 9.08925 2.86941 6.45886C3.44634 6.1267 3.83995 5.51133 3.83995 4.8C3.83995 3.74114 2.97881 2.88 1.91995 2.88C0.861141 2.88 0 3.74114 0 4.8C0 5.78498 0.748781 6.58945 1.70494 6.69886L3.55392 16.32H20.4461L22.295 6.69886C23.2512 6.58945 24 5.78498 24 4.8C24 3.74114 23.1389 2.88 22.08 2.88Z" fill="url(#paint0_linear_189_140)"></path>
                            <defs>
                                <linearGradient id="paint0_linear_189_140" x1="12" y1="0" x2="12" y2="16.32" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#FFC045"></stop>
                                    <stop offset="1" stop-color="#FF9900"></stop>
                                </linearGradient>
                            </defs>
                        </svg></span>Go Premium</a>

</div>


          <div class="text-center mt-5 qcld-Resetall">
            <input type="button" class="btn btn-warning submit-button"
                                   id="qcld-wp-chatbot-reset-option"
                                   value="<?php echo esc_html('Reset all options to Default') ; ?>"/>
          </div>
        </nav>




        <div class="content-wrap">
        <section id="section-flip-1">
           
          
           <div class="wrap qcld-swpm-admin-menu-wrap">
            <div class="wrap qcld-swpm-admin-menu-wrap">
                <h3 class="nav-tab-wrapper sld_nav_container wppt_nav_container"> 
                    <a class="nav-tab sld_click_handle nav-tab-active"  href="#general_int"><?php echo esc_html('Getting Started'); ?></a> 
                    <a class="nav-tab sld_click_handle"  href="#general_wp_nutshell"><?php echo esc_html('WPBot – In a Nutshell'); ?></a> 
                </h3>
                <div class="wppt-settings-section" id="general_int">
                    <div class="content form-container qcbot_help_secion" style=""> 
                        <!-- new Section -->
                    
                    <h3 class="qcld-wpbot-main-tabs-title"><?php echo esc_html__('WPBot Interactions', 'chatbot'); ?></h3>
                    <p><?php echo esc_html__('You can use WPBot to both answer user questions and collect information from the users.', 'chatbot'); ?></p>
                    <h4><?php echo esc_html__('To create answers to user questions you can use:', 'chatbot'); ?></h4>
                   <p> <b><?php echo esc_html__('Simple Text Responses', 'chatbot'); ?></b> (built-in),  <b style="font-size: 14px"><?php echo esc_html__('FAQ', 'chatbot'); ?></b>(built-in),  <b style="font-size: 14px"><?php echo esc_html__('Site search', 'chatbot'); ?></b>(built-in),  <b style="font-size: 14px"><?php echo esc_html__('Product search', 'chatbot'); ?></b>(built-in Pro feature),  <b style="font-size: 14px"><?php echo esc_html__('DialogFlow', 'chatbot'); ?></b>(3rd Party) or  <b style="font-size: 14px"><?php echo esc_html__('OpenAI', 'chatbot'); ?></b>(3rd Party)</br>
</p> 
                   <h4> <?php echo esc_html__('To collect information from your users you can use:', 'chatbot'); ?></h4>
                    <p>  <b>
                    <?php echo esc_html__('Conversational forms', 'chatbot'); ?></b>(built-in),  <b style="font-size: 14px"><?php echo esc_html__('Mail us', 'chatbot'); ?></b>(built-in),  <b style="font-size: 14px"><?php echo esc_html__('Call me back', 'chatbot'); ?></b>(built-in),  <b style="font-size: 14px"><?php echo esc_html__('Collect feedback features', 'chatbot'); ?></b>(built-in)</p>
                     <hr>
                   <p>
                   <b> <?php echo esc_html__('When you activate the plugin, by default only the Site search option will work. Site search displays links to your website pages that contain the keywords in the user query. ', 'chatbot'); ?>
                    </b></p>
                    <p><b><?php echo esc_html__('To generate direct text responses, you need to use either Simple Text Responses or AI services. ', 'chatbot'); ?>
                    </b></p>   
                    <hr>
                    <h4><?php echo esc_html__('You can create user interactions in the following ways:', 'chatbot'); ?></h4>
                  
                    <div class="panel panel-default">
                          <div class="panel-heading" role="tab" id="FeaturesOne">
                              <h4 class="panel-title">
                              <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#FeaturescollapseOne" aria-expanded="false" aria-controls="FeaturescollapseOne"> <?php esc_html_e('Predefined intents - Built-in ChatBot Features', 'chatbot'); ?>  </a>
                              </h4>
                          </div>
                          <div id="FeaturescollapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="FeaturesOne">
                              <div class="panel-body"> <?php esc_html_e('Predefined intents can work without integration to DialogFlow API and AI. These are readily available as soon as you install the plugin and can be turned on or off individually.', 'chatbot'); ?>  <div class="section-container">
                                  <div class="wpb_column vc_column_container vc_col-sm-6">
                                  <div class="vc_column-inner ">
                                      <div class="wpb_wrapper">
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span><?php esc_html_e('Simple Text Responses', 'chatbot'); ?> 
                                          </h3>
                                          <p><?php esc_html_e('Create unlimited text responses from your WordPress backend. The ChatBot uses advanced search algorithm for natural language phrase matching with user input.', 'chatbot'); ?> </p>
                                          </div>
                                      </div>
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span><?php esc_html_e('Send eMail, Call Me Back &amp; Feedback Collection', 'chatbot'); ?>
                                          </h3>
                                          <p><?php esc_html_e('Users can send a email to the site admin directly from the Chat window for customer support. The Call Me Back feature lets you get call requests from your customers which will be emailed to you. You can also use WPBot to collect Feedback from your customers regarding anything! You can disable/enable these features from the Start Menu.', 'chatbot'); ?></p>
                                          </div>
                                      </div>
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span><?php esc_html_e('Advanced Site Search', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                          </h3>
                                          <p><?php esc_html_e('If no matching text response is found WPBot will conduct an advanced website search and try to match user queries with your website contents and show results.', 'chatbot'); ?>  </p>
                                          </div>
                                      </div>
                                      </div>
                                  </div>
                                  </div>
                                  <div class="wpb_column vc_column_container vc_col-sm-6">
                                  <div class="vc_column-inner ">
                                      <div class="wpb_wrapper">
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span><?php esc_html_e('Frequently Asked Questions', 'chatbot'); ?>
                                          </h3>
                                          <p><?php esc_html_e('Create a set of Frequently Asked Questions or FAQ so users can quickly find answers to the most common questions they have.', 'chatbot'); ?></p>
                                          </div>
                                      </div>
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span>Ask for name, email, phone number etc.
                                          </h3>
                                          <p><?php esc_html_e('Asking for the name is the default workflow. In the pro version, you can also ask for an email and phone number if you want to or skip the Greetings part altogether and load any intent of your choice.', 'chatbot'); ?></p>
                                          </div>
                                      </div>
                                      <div class="to-icon-box  left txt-left">
                                          <div class="to-icon-txt fa-4x-txt ">
                                          <h3>
                                              <span>// </span><?php esc_html_e('Newsletter Subscription', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                          </h3>
                                          <p><?php esc_html_e('WPBot can prompt User for eMail subscription. Link this with your Retargeting ChatBot window popup and a special offer. People can register their email address that you can later export as CSV!', 'chatbot'); ?> <strong>GDPR compliant</strong> with unsubscribe option from the ChatBot! </p>
                                          </div>
                                      </div>
                                      </div>
                                  </div>
                                  </div>
                              </div>
                              </div>
                          </div>
                          </div>
                          <div class="panel panel-default">
                          <div class="panel-heading" role="tab" id="headingTwo">
                              <h4 class="panel-title">
                              <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo"><?php esc_html_e(' Menu Driven - Created with Conversational Form Builder Addon', 'chatbot'); ?> </a>
                              </h4>
                          </div>
                          <div id="collapseTwo" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingTwo">
                              <div class="panel-body">
                              <p><?php esc_html_e('Extend the Start Menu with the', 'chatbot'); ?> <?php esc_html_e('powerful Conversational Forms', 'chatbot'); ?>&nbsp;<?php esc_html_e(' Addon for WPBot. It extends WPBot’s functionality and adds the ability to create', 'chatbot'); ?> <?php esc_html_e('conditional conversations', 'chatbot'); ?> <?php esc_html_e('and/or', 'chatbot'); ?> <?php esc_html_e('forms', 'chatbot'); ?> <?php esc_html_e('for the WPBot. It is a visual,', 'chatbot'); ?> <?php esc_html_e('drag and drop', 'chatbot'); ?><?php esc_html_e(' form builder that is easy to use and very flexible. Supports conditional logic and use of variables to build all types of forms or just', 'chatbot'); ?> <?php esc_html_e('menu driven', 'chatbot'); ?>
                                  <?php esc_html_e('conversations', 'chatbot'); ?> <?php esc_html_e('with if else logic', 'chatbot'); ?>  . <?php esc_html_e('Conversations or forms can be', 'chatbot'); ?> <?php esc_html_e('eMailed', 'chatbot'); ?> <?php esc_html_e('to you and', 'chatbot'); ?>  <?php esc_html_e('saved in the database', 'chatbot'); ?>.
                              </p>
                              <h4><?php esc_html_e('Conversational Form Builder Free or Pro version works with the WPBot Free or Pro versions.', 'chatbot'); ?></h4>
                              <a class="FormBuilder" href="https://wordpress.org/plugins/conversational-forms/" target="_blank"><?php esc_html_e('Download Free Version', 'chatbot'); ?></a>
                              <a class="FormBuilder" href="https://www.quantumcloud.com/products/conversations-and-form-builder/" target="_blank"><?php esc_html_e('Grab the Pro version', 'chatbot'); ?></a>
                              <h4><?php esc_html_e('What Can You Do with it?', 'chatbot'); ?></h4>
                              <p><?php esc_html_e('Conversation Forms allows you to create a wide variety of forms, that might include:', 'chatbot'); ?></p>
                              <ul>
                                  <li><?php esc_html_e('Create menu or button driven conversations', 'chatbot'); ?></li>
                                  <li><?php esc_html_e('Conditional Menu Driven Conversations', 'chatbot'); ?>
                                  <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                                  </li>
                                  <li><?php esc_html_e('Standard Contact Forms', 'chatbot'); ?></li>
                                  <li><?php esc_html_e('Dynamic,', 'chatbot'); ?> <?php esc_html_e('conditional Forms', 'chatbot'); ?> <?php esc_html_e('– where fields can change based on the user selections', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;">PRO</span>
                                  </li>
                                  <li>Job <?php esc_html_e('Application Forms', 'chatbot'); ?>
                                  </li>
                                  <li>
                                  <?php esc_html_e('Lead Capture', 'chatbot'); ?> <?php esc_html_e('Forms', 'chatbot'); ?>
                                  </li>
                                  <li><?php esc_html_e('Various types of', 'chatbot'); ?> <?php esc_html_e('Calculators', 'chatbot'); ?>
                                  <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                                  </li>
                                  <li><?php esc_html_e('Feedback', 'chatbot'); ?> Survey<?php esc_html_e(' Forms etc.', 'chatbot'); ?> </li>
                              </ul>
                              </div>
                          </div>
                          </div>
                          <div class="panel panel-default">
                          <div class="panel-heading" role="tab" id="AIheadingThree">
                              <h4 class="panel-title">
                              <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#AIcollapseThree" aria-expanded="false" aria-controls="AIcollapseThree"> <?php esc_html_e('DialogFlow ES and CX, OpenAI', 'chatbot'); ?> </a>
                              </h4>
                          </div>
                          <div id="AIcollapseThree" class="panel-collapse collapse" role="tabpanel" aria-labelledby="AIheadingThree">
                              <div class="panel-body">
                             
                            <div class="section-container">
                                        <div class="wpb_column vc_column_container vc_col-sm-6">
                                        <div class="wpb_wrapper">
                                            <h3 style="font-size: 20px;"><?php esc_html_e('DialogFlow Essential', 'chatbot'); ?></h3> <?php esc_html_e('Intents created in Dialogflow give you the power to build a truly human like, intelligent and comprehensive chatbot. Build any type of Intents and Responses (including rich message responses) directly in DialogFlow and train the bot accordingly. When you create custom intents and responses in DialogFlow, WPBot will automatically display them when user inputs match with your Custom Intents along with the responses you created. You can also build Rich responses by enabling Facebook messenger Response option.', 'chatbot'); ?> <p></p>
                                            <p style="text-align: left;"><?php esc_html_e('In addition you can also Enable ', 'chatbot'); ?><?php esc_html_e('Advanced Chained Question and Answers using follow up Intents, Contexts, Entities etc. and then have resulting answers from your users emailed to you. This feature lets you create a a series of questions in DialogFlow that will be asked by the bot and based on the user inputs a response will be displayed.', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                                            </p>
                                            <p style="text-align: left;"><?php esc_html_e('WPBot also supports Rich responses using Facebook Messenger integration. This allows you to display Image,', 'chatbot'); ?> Cards<?php esc_html_e(', Quick Text Reply or Custom PayLoad inside the ChatBot window. You can also insert an ', 'chatbot'); ?><?php esc_html_e('image', 'chatbot'); ?><?php esc_html_e(' or', 'chatbot'); ?> <?php esc_html_e('youtube video', 'chatbot'); ?><?php esc_html_e(' link inside the DialogFlow responses and they will be automatically rendered by the WPBot!', 'chatbot'); ?> <span class="qc_wpbot_pro" style="font-size: 9px;"><?php esc_html_e('PRO', 'chatbot'); ?></span>
                                            </p>
                                            <h3 style="font-size: 20px;"><?php esc_html_e('OpenAI', 'chatbot'); ?></h3><?php esc_html_e('Connect the ChatBot to OpenAI. OpenAI’s API provides access to GPT-3, for a wide variety of natural language tasks. Train your ChatBot with (pre-trained) GPT-3 to answer any user questions using. Select your preferred Engine from DaVinci, Ada, Curie or Babbag! Add your own API key to the addon to connect to your OpenAI account. To go live, you need to apply to OpenAI.', 'chatbot'); ?>
                                        </div>
                                        </div>
                                        <div class="wpb_column vc_column_container vc_col-sm-6">
                                        <div class="wpb_wrapper">
                                            <h3 style="font-size: 20px;"><?php esc_html_e('DialogFlow CX', 'chatbot'); ?> <span class="qc_wpbot_pro">PRO</span>
                                            </h3>
                                            <p><?php esc_html_e('WPBot supports', 'chatbot'); ?> <?php esc_html_e('visual workflow builder', 'chatbot'); ?><?php esc_html_e(' Dialogflow CX. It provides a new way of designing agents, taking a state machine approach to agent design. This gives you clear and explicit control over a conversation, a better end-user experience, and a better development', 'chatbot'); ?> <?php esc_html_e('workflow', 'chatbot'); ?>. </p>
                                            <ul>
                                            <li>
                                            <?php esc_html_e('Console visualization', 'chatbot'); ?><?php esc_html_e(': A new', 'chatbot'); ?><?php esc_html_e('visual builder', 'chatbot'); ?> <?php esc_html_e('makes building and maintaining agents easier. Conversation paths are graphed as a state machine model, which makes conversations easier to design, enhance, and maintain.', 'chatbot'); ?>
                                            </li>
                                            <li>
                                            <?php esc_html_e('Intuitive and powerful conversation control', 'chatbot'); ?>: <?php esc_html_e('Conversation states and state transitions are first-class types that provide explicit and powerful control over conversation paths. You can clearly define a series of steps that you want the end-user to go through.', 'chatbot'); ?>
                                            </li>
                                            <li>
                                              <?php esc_html_e('Flows for agent partitions', 'chatbot'); ?>: <?php esc_html_e('With flows, you can partition your agent into smaller conversation topics. Different team members can own different flows, which makes large and complex agents easy to build.', 'chatbot'); ?>
                                            </li>
                                            <img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/dialogflow-cx-1024x676.jpg' ); ?>" alt="Dialogflow CX">
                                            </ul>
                                        </div>
                                        </div>
                                    </div>


                          </div>
                      </div>
                    
                    
                    <!-- end new Section --> 
                    
                  </div>
                  </div>
                </div>




                <div class="wppt-settings-section" id="general_wp_nutshell"  style="display:none;">
                    <div class="content form-container qcbot_help_secion"> 
                    <!-- new Section -->
                        <h3 class="qcld-wpbot-main-tabs-title"><?php echo esc_html__('WPBot – In a Nutshell', 'chatbot'); ?></h3>
            
                        <p><?php echo esc_html__('This is by no means a comprehensive list of WPBot features. But knowing these core terms will help you understand how WPBot was designed to work.', 'chatbot'); ?></p>
                        <div style="clear:both"></div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingOne">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> <?php esc_html_e('Intents', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
                                    <div class="panel-body"> 
                                    <?php echo esc_html_e(' Intent is all about what the user wants to get out of the interaction. Whenever a user types something or clicks a button, the ChatBot will try to understand what the user wants and fulfill the request with appropriate responses.', 'chatbot'); ?></br></br>
                                    <?php echo esc_html_e('You have to create possible Intent Responses using different features of the WPBot so the bot can respond accordingly. You can create Responses for various Intents using:', 'chatbot'); ?><b>
                                    <?php echo esc_html_e('Simple Text Responses, Conversational form builder, FAQ, Site Search, Send an eMail, Newsletter Subscription, DialogFlow, OpenAI etc.', 'chatbot'); ?></b></br></br>
                                    <?php echo esc_html_e('Please check this article for','chatbot'); ?> <span class="wppt_nav_container qcld-plan-tab-text"> 
                                        <a href="#general_int"><?php echo esc_html_e('more details', 'chatbot'); ?></a> </span>  <?php echo esc_html_e('on how you can create Intents and Responses.', 'chatbot'); ?>
                                    </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingSix">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseSix" aria-expanded="false" aria-controls="collapseSix"> <?php esc_html_e('Start Menu', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseSix" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingSix">
                                <div class="panel-body"> 
                                    <?php echo esc_html_e('While using a ChatBot, users can get lost or not know how to Interact with the Bot. That is why we have a Start menu to always give the user', 'chatbot'); ?> <b><?php echo esc_html_e('options to do more', 'chatbot'); ?></b>. <?php echo esc_html_e('From ChatBot->Settings->Start Menu you can drag Available Menu Items (Intents) to the Active Menu Items area.', 'chatbot'); ?></br></br>
                                    <?php echo esc_html_e('Besides the built-in Intents, you can also create custom Intents for your Start Menu using','chatbot'); ?> <b><?php echo esc_html_e('Simple Text Responses', 'chatbot'); ?></b> and <b><?php echo esc_html_e('Conversational form builder', 'chatbot'); ?></b>. <?php echo esc_html_e('You can create almost any kind of response with the combinations of the two.', 'chatbot'); ?></br></br>
                                    <?php echo esc_html_e('We recommend enabling','chatbot'); ?><b><?php echo esc_html_e(' Show Start Menu After Greetings ', 'chatbot'); ?></b><?php echo esc_html_e('from ChatBot Pro->Settings->General settings.','chatbot'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingSeven">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven"> <?php esc_html_e('Settings', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseSeven" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingSeven">
                                    <div class="panel-body"> 
                                        <?php echo esc_html_e('Head over to ChatBot Pro->Settings->General and make sure to Enable the Floating Icon. As soon as you do that, the ChatBot can start working for your users. Make sure to drag some items to the Active Menu area under the Start Menu.', 'chatbot'); ?></br></br>
                                        <?php echo esc_html_e('The ChatBot settings area is full of options. Do not be intimidated by that. You do not need to use all the options – just what you need. Head over to the Settings->', 'chatbot'); ?><b><?php echo esc_html_e('Icons and Themes','chatbot'); ?></b> <?php echo esc_html_e('for options to customize your ChatBot. You will also find options to embed the ChatBot on a page, click to chat, FAQ builder etc. under the Setting options.', 'chatbot'); ?>
                                    </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingEight">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseEight" aria-expanded="false" aria-controls="collapseEight"> <?php esc_html_e('Language Center', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseEight" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingEight">
                                    <div class="panel-body"> 
                                    <?php echo esc_html_e('You can use the ChatBot in', 'chatbot'); ?> <b><?php echo esc_html_e('ANY language', 'chatbot'); ?></b>. <?php echo esc_html_e('Just translate the texts used by the ChatBot from the WordPress dashboard ChatBot Pro->', 'chatbot'); ?><b><?php echo esc_html_e('Language Center. Multi language', 'chatbot'); ?></b> <?php echo esc_html_e('module is available in the Master License..','chatbot'); ?>
                                    </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingtwo">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapsetwo" aria-expanded="false" aria-controls="collapseOne"> <?php esc_html_e('Simple Text Responses', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapsetwo" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingtwo">
                                    <div class="panel-body"> 
                                    <?php echo esc_html_e('You can use ChatBot Pro->Simple Text Responses to create', 'chatbot'); ?> <b><?php echo esc_html_e('text-based responses', 'chatbot'); ?></b> <?php echo esc_html_e('that users may ask your ChatBot. Just define the questions, answers, and some keywords and you are done. This is a much simpler', 'chatbot'); ?>  <b><?php echo esc_html_e('alternative ', 'chatbot'); ?></b> <?php echo esc_html_e('to DialogFlow or OpenAI.', 'chatbot'); ?>
                                    </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingThree">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseThree" aria-expanded="false" aria-controls="collapseThree"> <?php esc_html_e('Conversational Forms', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseThree" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingThree">
                                <div class="panel-body"> 
                                <?php echo esc_html_e('Use conversational forms to collect information from the users. This is also great for Button driven workflow. Create conditional conversations and forms for a native WordPress ChatBot experience. Build Standard Forms, Dynamic Forms with', 'chatbot'); ?> <b> <?php echo esc_html_e('conditional fields, Calculators, Appointment booking', 'chatbot'); ?></b> <?php echo esc_html_e('etc.', 'chatbot'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingten">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseten" aria-expanded="false" aria-controls="collapseten"> <?php esc_html_e('Retargeting (Pro feature)', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseten" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingten">
                                <div class="panel-body"> 
                                <?php echo esc_html_e('Retargeting is a powerful feature to grab your user’s attention with motivating information (a sale, coupon, ebook etc.). You can trigger a Retargeting message and the ChatBot window will automatically', 'chatbot'); ?> <b> <?php echo esc_html_e('automatically ', 'chatbot'); ?></b><?php echo esc_html_e('open up with your message.  You can trigger Retargeting for ', 'chatbot'); ?><b> <?php echo esc_html_e('Exit Intent, Exit Intent, Scroll Intent, Auto After “X” Seconds, Checkout', 'chatbot'); ?></b> <?php echo esc_html_e('etc.', 'chatbot'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingFour">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseFour" aria-expanded="false" aria-controls="collapseFour"> <?php esc_html_e('OpenAI or DialogFlow', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseFour" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFour">
                                <div class="panel-body"> 
                                <?php echo esc_html_e('If you need a bot that can understand natural language better, use either OpenAI or DialogFlow. Between the two', 'chatbot'); ?> <b> <?php echo esc_html_e('DialogFlow', 'chatbot'); ?></b> <?php echo esc_html_e('is better if you want to', 'chatbot'); ?> <b> <?php echo esc_html_e('provide customer support', 'chatbot'); ?></b>. <?php echo esc_html_e('OpenAI is better at generic questions and training OpenAI also requires a large dataset. But you do not have to use either 3rd party service. Using OpenAI or DialogFlow requires some patience and', 'chatbot'); ?> <b> <?php echo esc_html_e('effort', 'chatbot'); ?></b>. <?php echo esc_html_e('You may very well achieve what you need using ', 'chatbot'); ?><b> <?php echo esc_html_e('Simple Text Responses', 'chatbot'); ?></b> <?php echo esc_html_e('and/or', 'chatbot'); ?> <b> <?php echo esc_html_e('Conversational form builder', 'chatbot'); ?></b> <?php echo esc_html_e('instead.', 'chatbot'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="headingFive">
                                <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseFive" aria-expanded="false" aria-controls="collapseFive"> <?php esc_html_e('Getting Help', 'chatbot'); ?>  </a>
                                </h4>
                            </div>
                            <div id="collapseFive" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFive">
                                <div class="panel-body"> 
                                    <?php echo esc_html_e('We have built-in Help section under each module. Please check them out and you will get many answers to the questions you may have. If you cannot find the answer to something particular, just contact us.', 'chatbot'); ?> <b><?php echo esc_html_e('Pro version ', 'chatbot'); ?></b><?php echo esc_html_e('users can open a support ticket from here. We are ', 'chatbot'); ?><b><?php echo esc_html_e('friendly ', 'chatbot'); ?></b><?php echo esc_html_e('and always here to help.', 'chatbot'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end new Section --> 


                </div>
            <script type="text/javascript">  
                  jQuery(document).ready(function($){
                      var url=document.URL;
                      var arr=url.split('#');
                      var tab_tar = '.'+arr[1];
                      setTimeout(function(){
                              jQuery(tab_tar).trigger('click');
                          }, 500);
                      
                      jQuery('.wppt_nav_container .nav-tab').on('click', function(e){
                          e.preventDefault();
                          var section_id = jQuery(this).attr('href');
                          jQuery('.wppt_nav_container .nav-tab').removeClass('nav-tab-active');
                          jQuery(this).addClass('nav-tab-active');
                          jQuery('.wppt-settings-section').hide();
                          jQuery('.wppt-settings-section').each(function(){
                              jQuery(section_id).show();
                          });
                      });
                  })
            </script>
          </section>
          <section id="section-flip-2">
            <div class="top-section">
              <div class="row">
                <div class="col-xs-12">
                  <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
                    <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
                  </div> -->

                  <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('General settings', 'chatbot'); ?></h3>

                  <?php if( get_option( 'ai_enabled') != 1 && get_option('qcld_openrouter_enabled') !='1' ){ ?>
                  <div class="qcld-general-settings-connection-notic">

                                    <p>
                  <?php esc_html_e( ' When you activate the plugin, by default only the Site search option will work. Site search displays links to your website pages that contain the keywords in the user query.','chatbot' ); ?>
                      </p>
                      <p> 
                     <?php esc_html_e( 'Use ','chatbot' ); ?><a href="<?php echo esc_url(admin_url('admin.php?page=simple-text-response')); ?>"><b><?php esc_html_e( 'Simple Text Responses','chatbot' ); ?></b></a> <?php esc_html_e( ' or','chatbot' ); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=wpbot_openAi')); ?>"><b><?php esc_html_e( 'Connect to an AI service','chatbot' ); ?> </b></a><?php esc_html_e( 'like OpenAI or OpenRouter. Openrouter supports all the major AI models like Anthropic, Google Gemini, Meta, Mistral, Cohere, xAI, Perplexity AI, DeepSeek etc. and also provides Free Credits.','chatbot' ); ?>
                  
                   </p>
               
                  <a class="qcld-general-settings-close" href="javascript:void(0);" onclick="this.parentElement.style.display='none'; localStorage.setItem('qcld_general_settings_notice_closed', '1');">×</a>
                  </div>
                  <?php } ?>

                  <div class="cxsc-settings-blocks-notic">
                    <p class="d-ib"><i class="dashicons dashicons-yes" aria-hidden="true"></i> <?php esc_html_e('Please see the plugin`s Getting Started section for how to create interactions.', 'chatbot'); ?></p>
                    <p class="d-ib"><i class="dashicons dashicons-yes" aria-hidden="true"></i> <?php esc_html_e('You can change ALL the texts used by the ChatBot to YOUR language here to make it speak French, German, Spanish etc. -> Change Language', 'chatbot'); ?></p>
                    <p class="d-ib"><i class="dashicons dashicons-yes" aria-hidden="true"></i> <?php esc_html_e('Please see the plugin`s Help and Debugging section to troubleshoot common issues.', 'chatbot'); ?></p>
                  </div>
          
                  <b>
                  <!-- <p>Please see the plugin's Help and Debugging section to troubleshoot common issues.</p> -->
                  </b>
                  

                  <div class="cxsc-settings-blocks">
                    <div class="form-group">
                      <?php
                        $url = get_site_url();
                        $url = wp_parse_url($url);
                        $domain = $url['host'];
                        
                        $admin_email = get_option('admin_email');
                      ?>
                      <h4 class="qc-opt-title"><?php esc_html_e('Emails Will be Sent to', 'chatbot'); ?></h4>
                      <input type="text" class="form-control qc-opt-dcs-font"
                                                       name="qlcd_wp_chatbot_admin_email"
                                                       value="<?php echo (get_option('qlcd_wp_chatbot_admin_email') != '' ? esc_attr(get_option('qlcd_wp_chatbot_admin_email')) : sanitize_email($admin_email) )  ?>">
                      <label class="qcld_label_width_full" for="disable_wp_chatbot"><?php esc_html_e('Support and Call Back requests will be sent to this address', 'chatbot'); ?> </label>
                    </div>
                  </div>
                  <div class="cxsc-settings-blocks">
                    <div class="form-group">
                      <?php
                          $url = get_site_url();  
                          $url = wp_parse_url($url);
                          $domain = $url['host'];
                          $fromEmail = "wordpress@" . $domain;
                      ?>
                      <h4 class="qc-opt-title"><?php esc_html_e('From Email Address', 'chatbot'); ?></h4>
                      <input type="text" class="form-control qc-opt-dcs-font"
                                                       name="qlcd_wp_chatbot_from_email"
                                                       value="<?php echo (get_option('qlcd_wp_chatbot_from_email') != '' ?  esc_attr( get_option('qlcd_wp_chatbot_from_email')) :  sanitize_email($fromEmail)); ?> ">
                      <label class="qcld_label_width_full" for="qlcd_wp_chatbot_from_email"><?php esc_html_e('All emails will be sent from this email address. If you change the From Email Address then please make sure the domain remains the same as where WordPress is installed. Otherwise, the emails may not be received.', 'chatbot'); ?> </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-xs-12">
                    <div class="cxsc-settings-blocks">
                      <div class="form-group">
                        <h4 class="qc-opt-title"> <?php echo esc_html__("Skip Greetings (Asking for Name) and Show Start Menu", 'chatbot'); ?> </h4>
                        <div class="cxsc-settings-blocks">
                          <input value="1" id="skip_wp_greetings" type="checkbox"
                          name="skip_wp_greetings" <?php echo(get_option('skip_wp_greetings') == 1 ? 'checked' : ''); ?>>
                          <label for="skip_wp_greetings"><?php echo esc_html__("Skip Greetings (Asking for Name) and Show Start Menu", 'chatbot'); ?> </label>
                        </div>
                      </div>
                    </div>
                </div>
              </div>

              <div class="row">
                <div class="col-xs-12">
                    <div class="cxsc-settings-blocks">
                      <div class="form-group">
                        <h4 class="qc-opt-title"> <?php echo esc_html__("Skip Greetings (Asking for Name) and Disable Start Menu", 'chatbot'); ?> </h4>
                        <div class="cxsc-settings-blocks">
                          <input value="1" id="skip_wp_greetings_donot_show_menu" type="checkbox"
                          name="skip_wp_greetings_donot_show_menu" <?php echo(get_option('skip_wp_greetings_donot_show_menu') == 1 ? 'checked' : ''); ?>>
                          <label for="skip_wp_greetings_donot_show_menu"><?php echo esc_html__("Skip Greetings (Asking for Name) and Disable Start Menu", 'chatbot'); ?> </label>
                        </div>
                      </div>
                    </div>
                </div>
              </div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group"> 
                <h4 class="qc-opt-title"> 
                      <?php esc_html_e('Show Start Menu After Greetings', 'chatbot'); ?> 
                  </h4>
                  <div class="cxsc-settings-blocks">
                    <input value="1" id="show_menu_after_greetings" type="checkbox"
                                                   name="show_menu_after_greetings" <?php echo (get_option('show_menu_after_greetings') == 1 ?  esc_attr('checked' ): ''); ?>>
                    <label for="show_menu_after_greetings">
                        <?php esc_html_e('Show Start Menu After Greetings', 'chatbot'); ?>
                    </label>
                  </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group">  
                <h4 class="qc-opt-title">
                    <?php esc_html_e('Disable WPBot', 'chatbot'); ?>
                  </h4>
                  <div class="cxsc-settings-blocks">
                    <input value="1" id="disable_wp_chatbot" type="checkbox"
                                                   name="disable_wp_chatbot" <?php echo (get_option('disable_wp_chatbot') == 1 ?  esc_attr('checked'): ''); ?>>
                    <label for="disable_wp_chatbot">
                      <?php esc_html_e('Disable WPBot to Load', 'chatbot'); ?>
                    </label>
                  </div>
                </div>
              </div>
              </div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group"> 
                <h4 class="qc-opt-title">
                    <?php esc_html_e('Disable WPbot on Mobile Device', 'chatbot'); ?>
                  </h4>
                  <div class="cxsc-settings-blocks">
                    <input value="1" id="disable_wp_chatbot_on_mobile" type="checkbox"
                                                   name="disable_wp_chatbot_on_mobile" <?php echo (get_option('disable_wp_chatbot_on_mobile') == 1 ?  esc_attr('checked' ) : ''); ?>>
                    <label for="disable_wp_chatbot_on_mobile">
                      <?php esc_html_e('Disable WpBot to Load on Mobile Device', 'chatbot'); ?>
                    </label>
                  </div>
                </div>
              </div>
        </div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group"> 
                <h4 class="qc-opt-title">
                    <?php esc_html_e('Enable RTL', 'chatbot'); ?>
                  </h4>
                  <div class="cxsc-settings-blocks">
                    <input value="1" id="enable_wp_chatbot_rtl" type="checkbox"
                                                   name="enable_wp_chatbot_rtl" <?php echo (get_option('enable_wp_chatbot_rtl') == 1 ?  esc_attr('checked' ): ''); ?>>
                    <label for="enable_wp_chatbot_rtl">
                      <?php esc_html_e('Enable RTL (Right to Left language) Support for Chat', 'chatbot'); ?>
                    </label>
                  </div>
                </div>
              </div>
</div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group"> 
                <h4 class="qc-opt-title">
                    <?php esc_html_e('Open All Links in a New Window', 'chatbot'); ?>
                  </h4>
                  <div class="cxsc-settings-blocks">
                    <input value="1" id="open_links_new_window" type="checkbox" name="open_links_new_window" <?php echo (get_option('open_links_new_window') == 1 ?  esc_attr('checked'): ''); ?>>
                    <label for="open_links_new_window">
                      <?php esc_html_e('Open All Links in a New Window', 'chatbot'); ?>
                    </label>
                  </div>
                </div>
              </div>
</div>
         
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group">
                <h4 class="qc-opt-title"><?php esc_html_e('Bot Response Delay', 'chatbot'); ?> </h4>
                  <div class="cxsc-settings-blocks">
                    <select name="wpbot_preloading_time" id="wpbot_preloading_time">
                      <?php $current_preloading_time = get_option('wpbot_preloading_time'); ?>
                      <option value="100" <?php selected($current_preloading_time, '100'); ?>><?php esc_html_e('0.1 Second', 'chatbot'); ?></option>
                      <option value="500" <?php selected($current_preloading_time, '500'); ?>><?php esc_html_e('0.5 Second', 'chatbot'); ?></option>
                      <option value="1000" <?php selected($current_preloading_time, '1000'); ?>><?php esc_html_e('1 Second', 'chatbot'); ?></option>
                      <option value="2000" <?php selected($current_preloading_time, '2000'); ?>><?php esc_html_e('2 Second', 'chatbot'); ?></option>
                      <option value="3000" <?php selected($current_preloading_time, '3000'); ?>><?php esc_html_e('3 Second', 'chatbot'); ?></option>
                    </select>
                    <label  class="qcld_label_width_full" for="wpbot_preloading_time"><?php esc_html_e('Bot Response Delay', 'chatbot'); ?> </label>
                  </div>
                </div>
                </div>
              </div>
              <div class="row">
                <div class="col-xs-12">
                <div class="form-group"> 
                <h4 class="qc-opt-title">
                    <?php esc_html_e('Override WPBot Icon\'s Position', 'chatbot'); ?>
                  </h4>
                  <div class="cxsc-settings-blocks cxsc-settings-blocks-font-resize">
                    <?php
                      $qcld_wb_chatbot_position_x = get_option('wp_chatbot_position_x');
                      if ((!isset($qcld_wb_chatbot_position_x)) || ($qcld_wb_chatbot_position_x == "")) {
                          $qcld_wb_chatbot_position_x = __("120", 'chatbot');
                      }
                      $qcld_wb_chatbot_position_y = get_option('wp_chatbot_position_y');
                      if ((!isset($qcld_wb_chatbot_position_y)) || ($qcld_wb_chatbot_position_y == "")) {
                          $qcld_wb_chatbot_position_y = __("120", 'chatbot');
                      } 
                    ?>
                    <input type="number" class="qc-opt-dcs-font"
                                                   name="wp_chatbot_position_x"
                                                   value="<?php echo esc_html($qcld_wb_chatbot_position_x);?>"
                                                   placeholder="<?php esc_html_e('From Right In ', 'chatbot'); ?>">
                    <span class="qc-opt-dcs-font">
                    <?php esc_html_e('From Right In px', 'chatbot'); ?>
                    </span>
                    <input type="number" class="qc-opt-dcs-font"
                                                   name="wp_chatbot_position_y"
                                                   value="<?php echo esc_html($qcld_wb_chatbot_position_y); ?>"
                                                   placeholder="<?php esc_html_e('From Bottom In Px', 'chatbot'); ?> ">
                    <span class="qc-opt-dcs-font">
                    <?php esc_html_e('From Bottom In px', 'chatbot'); ?>
                    </span> </div>
                </div>
                <!--.col-sm-12--> 
              </div>
              <!--                                row-->
              </div>
              <div class="row">
                <div class="col-xs-12">

                 <div class="panel panel-default">
                          <div class="panel-heading" role="tab" id="headingTwoControl">
                              <h4 class="panel-title">
                              <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseTwoControl" aria-expanded="false" aria-controls="collapseTwoControl"> <?php esc_html_e('WPBot Loading Control Options', 'chatbot'); ?>  </a>
                              </h4>
                          </div>
                          <div id="collapseTwoControl" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingTwoControl">
                              <div class="panel-body">
                  <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('WPBot Loading Control Options', 'chatbot'); ?></h3>
                  <div class="cxsc-settings-blocks cxsc-settings-blocks-style">
                    <div class="row">
                      <div class="col-sm-4 text-right"> <span class="qc-opt-title-font">
                        <?php esc_html_e('Show on Home Page', 'chatbot'); ?>
                        </span> </div>
                      <div class="col-sm-8">
                        <label class="radio-inline ">
                          <input id="wp-chatbot-show-home-page" type="radio"
                                                               name="wp_chatbot_show_home_page"
                                                               value="on" <?php echo(get_option('wp_chatbot_show_home_page') == 'on' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('YES', 'chatbot'); ?>
                        </label>
                        <label class="radio-inline ">
                          <input id="wp-chatbot-show-home-page" type="radio"
                                                               name="wp_chatbot_show_home_page"
                                                               value="off" <?php echo(get_option('wp_chatbot_show_home_page') == 'off' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('NO', 'chatbot'); ?>
                        </label>
                      </div>
                    </div>
                    <!--  row-->
                    <div class="row">
                      <div class="col-sm-4 text-right"> <span class="qc-opt-title-font">
                        <?php esc_html_e('Show on blog posts', 'chatbot'); ?>
                        </span> </div>
                      <div class="col-sm-8">
                        <label class="radio-inline ">
                          <input class="wp-chatbot-show-posts" type="radio"
                                                               name="wp_chatbot_show_posts"
                                                               value="on" <?php echo(get_option('wp_chatbot_show_posts') == 'on' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('YES', 'chatbot'); ?>
                        </label>
                        <label class="radio-inline ">
                          <input class="wp-chatbot-show-posts" type="radio"
                                                               name="wp_chatbot_show_posts"
                                                               value="off" <?php echo(get_option('wp_chatbot_show_posts') == 'off' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('NO', 'chatbot'); ?>
                        </label>
                      </div>
                    </div>
                    <!-- row-->
                    <div class="row">
                      <div class="col-md-4 text-right"> <span class="qc-opt-title-font">
                        <?php esc_html_e('Show on  pages', 'chatbot'); ?>
                        </span> </div>
                      <div class="col-md-8">
                        <label class="radio-inline ">
                          <input class="wp-chatbot-show-pages" type="radio"
                                                               name="wp_chatbot_show_pages"
                                                               value="on" <?php echo(get_option('wp_chatbot_show_pages') == 'on' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('All Pages', 'chatbot'); ?>
                        </label>
                        <label class="radio-inline ">
                          <input class="wp-chatbot-show-pages" type="radio"
                                                               name="wp_chatbot_show_pages"
                                                               value="off" <?php echo(get_option('wp_chatbot_show_pages') == 'off' ? esc_attr('checked' ): ''); ?>>
                          <?php esc_html_e('Selected Pages Only ', 'chatbot'); ?>
                        </label>
                        <div id="wp-chatbot-show-pages-list">
                          <ul class="checkbox-list">
                            <?php
                              $wp_chatbot_pages = get_pages();
                              $wp_chatbot_select_pages = maybe_unserialize(get_option('wp_chatbot_show_pages_list'));
                              foreach ($wp_chatbot_pages as $wp_chatbot_page) {
                            ?>
                            <li>
                              <input id="<?php echo esc_attr('wp_chatbot_show_page_'. $wp_chatbot_page->ID );  ?>"
                                      type="checkbox"
                                      name="wp_chatbot_show_pages_list[]"
                                      value="<?php echo esc_attr($wp_chatbot_page->ID); ?>" <?php if (!empty($wp_chatbot_select_pages) && in_array($wp_chatbot_page->ID, $wp_chatbot_select_pages) == true) {
                                  echo esc_attr('checked');
                              } ?> >
                              <label for="wp_chatbot_show_page_<?php echo esc_attr($wp_chatbot_page->ID); ?>"> <?php echo esc_html($wp_chatbot_page->post_title); ?></label>
                            </li>
                            <?php } ?>
                          </ul>
                        </div>
                      </div>
                    </div>
                    <!--row-->
                    <div class="row">
                      <div class="col-sm-4 text-right"> <span class="qc-opt-title-font">
                        <?php esc_html_e('Exclude from Custom Post', 'chatbot'); ?>
                        </span></div>
                      <div class="col-sm-8">
                        <div id="wp-chatbot-exclude-post-list" class="wp-chatbot-exclude-post-list">
                          <ul class="checkbox-list">
                            <?php
                              $get_cpt_args = array(
                                  'public'   => true,
                                  '_builtin' => false
                              );
                              
                              $post_types = get_post_types( $get_cpt_args, 'object' );
                              $wp_chatbot_exclude_post_list = maybe_unserialize(get_option('wp_chatbot_exclude_post_list'));
                             
                              foreach ($post_types as $post_type) {
                                  ?>
                            <li>
                              <input
                                      id="<?php echo esc_attr( 'wp_chatbot_exclude_post_'. $post_type->name );  ?>"
                                      type="checkbox"
                                      name="wp_chatbot_exclude_post_list[]"
                                      value="<?php  echo esc_attr($post_type->name); ?>" <?php if (!empty($wp_chatbot_exclude_post_list) && in_array($post_type->name, $wp_chatbot_exclude_post_list) == true) {
                                  echo esc_attr('checked');
                              } ?> >
                            <label
                              for="wp_chatbot_exclude_post_<?php echo esc_attr($post_type->name); ?>"> <?php echo esc_html($post_type->name); ?></label>
                            </li>
                            <?php } ?>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>

                              </div>
                          </div>
                          </div>
                  <!-- cxsc-settings-blocks--> 
                </div>
                <!-- col-xs-12--> 
              </div>
              <!--  row--> 
              
            </div>
            <!-- top-section--> 
          </section>
          <section id="section-flip-5">          
            <div class="wp-chatbot-language-center-summmery"> </div>
             <div class="row">
                      <div class="col-md-12">
                          <h4 class="qc-opt-title"><b><?php echo esc_html__( 'Change Language', 'wpchatbot' ); ?></b></h4>
                          <!-- Language Selection Dropdown -->
                          <div class="cxsc-settings-blocks mb-6">
                            <div class="form-group">
                              <label for="qcld_language_center_select" class="block text-sm font-medium text-gray-700 mb-2 qc-opt-language-title">
                                <?php echo esc_html__( 'Select a Language:', 'wpchatbot' ); ?>
                              </label>
                              <select id="qcld_language_center_select" class="form-select" onchange="qcld_wpbot_languageChange_from_center(this.value)">
                                <option value=""> <?php echo esc_html__( '-- Choose a language --', 'wpchatbot' ); ?></option>
                                <option value="arabic" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'arabic' ? 'selected' : '' ?>><?php echo esc_html__( 'Arabic (ar)', 'wpchatbot' ); ?></option>
                                <option value="bulgarian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'bulgarian' ? 'selected' : '' ?>><?php echo esc_html__( 'Bulgarian (bg)', 'wpchatbot' ); ?></option>
                                <option value="chinese" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'chinese' ? 'selected' : '' ?>><?php echo esc_html__( 'Chinese (zh)', 'wpchatbot' ); ?></option>
                                <option value="chinese_mandarin" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'chinese_mandarin' ? 'selected' : '' ?>><?php echo esc_html__( 'Chinese Mandarin (cmn)', 'wpchatbot' ); ?></option>
                                <option value="danish" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'danish' ? 'selected' : '' ?>><?php echo esc_html__( 'Danish (da)', 'wpchatbot' ); ?></option>
                                <option value="dutch" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'dutch' ? 'selected' : '' ?>><?php echo esc_html__( 'Dutch (nl)', 'wpchatbot' ); ?></option>
                                <option value="english" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'english' ? 'selected' : '' ?>><?php echo esc_html__( 'English (en)', 'wpchatbot' ); ?></option>
                                <option value="estonian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'estonian' ? 'selected' : '' ?>><?php echo esc_html__( 'Estonian (et)', 'wpchatbot' ); ?></option>
                                <option value="filipino" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'filipino' ? 'selected' : '' ?>><?php echo esc_html__( 'Filipino (fil)', 'wpchatbot' ); ?></option>
                                <option value="french" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'french' ? 'selected' : '' ?>><?php echo esc_html__( 'French (fr)', 'wpchatbot' ); ?></option>
                                <option value="german" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'german' ? 'selected' : '' ?>><?php echo esc_html__( 'German (de)', 'wpchatbot' ); ?></option>
                                <option value="greek" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'greek' ? 'selected' : '' ?>><?php echo esc_html__( 'Greek (el)', 'wpchatbot' ); ?></option>
                                <option value="hebrew" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'hebrew' ? 'selected' : '' ?>><?php echo esc_html__( 'Hebrew (he)', 'wpchatbot' ); ?></option>
                                <option value="hindi" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'hindi' ? 'selected' : '' ?>><?php echo esc_html__( 'Hindi (hi)', 'wpchatbot' ); ?></option>
                                <option value='hungarian' <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'hungarian' ? 'selected' : '' ?>><?php echo esc_html__( 'Hungarian (hu)', 'wpchatbot' ); ?></option>
                                <option value="indonesian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'indonesian' ? 'selected' : '' ?>><?php echo esc_html__( 'Indonesian (id)', 'wpchatbot' ); ?></option>
                                <option value="italian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'italian' ? 'selected' : '' ?>><?php echo esc_html__( 'Italian (it)', 'wpchatbot' ); ?></option>
                                <option value="japanese" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'japanese' ? 'selected' : '' ?>><?php echo esc_html__( 'Japanese (ja)', 'wpchatbot' ); ?></option>
                                <option value="korean" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'korean' ? 'selected' : '' ?>><?php echo esc_html__( 'Korean (ko)', 'wpchatbot' ); ?></option>
                                <option value="lithuanian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'lithuanian' ? 'selected' : '' ?>><?php echo esc_html__( 'Lithuanian (lt)', 'wpchatbot' ); ?></option>
                                <option value="malay" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'malay' ? 'selected' : '' ?>><?php echo esc_html__( 'Malay (ms)', 'wpchatbot' ); ?></option>
                                <option value="norwegian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'norwegian' ? 'selected' : '' ?>><?php echo esc_html__( 'Norwegian (no)', 'wpchatbot' ); ?></option>
                                <option value="persian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'persian' ? 'selected' : '' ?>><?php echo esc_html__( 'Persian (fa)', 'wpchatbot' ); ?></option>
                                <option value="polish" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'polish' ? 'selected' : '' ?>><?php echo esc_html__( 'Polish (pl)', 'wpchatbot' ); ?></option>
                                <option value="portuguese" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'portuguese' ? 'selected' : '' ?>><?php echo esc_html__( 'Portuguese (pt)', 'wpchatbot' ); ?></option>
                                <option value="romanian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'romanian' ? 'selected' : '' ?>><?php echo esc_html__( 'Romanian (ro)', 'wpchatbot' ); ?></option>
                                <option value="russian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'russian' ? 'selected' : '' ?>><?php echo esc_html__( 'Russian (ru)', 'wpchatbot' ); ?></option>
                                <option value="sinhala" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'sinhala' ? 'selected' : '' ?>><?php echo esc_html__( 'Sinhala (si)', 'wpchatbot' ); ?></option>
                                <option value="spanish" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'spanish' ? 'selected' : '' ?>><?php echo esc_html__( 'Spanish (es)', 'wpchatbot' ); ?></option>
                                <option value="swedish" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'swedish' ? 'selected' : '' ?>><?php echo esc_html__( 'Swedish (sv)', 'wpchatbot' ); ?></option>
                                <option value="thai" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'thai' ? 'selected' : '' ?>><?php echo esc_html__( 'Thai (th)', 'wpchatbot' ); ?></option>
                                <option value="turkish" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'turkish' ? 'selected' : '' ?>><?php echo esc_html__( 'Turkish (tr)', 'wpchatbot' ); ?></option>
                                <option value="ukrainian" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'ukrainian' ? 'selected' : '' ?>><?php echo esc_html__( 'Ukrainian (uk)', 'wpchatbot' ); ?></option>
                                <option value="vietnamese" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'vietnamese' ? 'selected' : '' ?>><?php echo esc_html__( 'Vietnamese (vi)', 'wpchatbot' ); ?></option>
                                <option value="not_mine" <?php echo  (get_option('wp_chatbot_language_center_language') !== "") && get_option('wp_chatbot_language_center_language') === 'not_mine' ? 'selected' : '' ?>><?php echo esc_html__( 'My language is not here', 'wpchatbot' ); ?></option>
                              </select>
                              <!-- Results Display Area -->
                              <div id="results-container" class="mt-8 p-4 flex items-center justify-center text-gray-600">
                                <p id="initial-message">
                                  <?php echo esc_html__( 'Select your language to auto translate the texts used by the ChatBot. You can fine tune and update them manually from below.', 'wpchatbot' ); ?> <b><?php echo esc_html__( 'Remember to Save the Settings.', 'wpchatbot' ); ?></b>
                                </p>
                                <p id="waring-message"></p>
                              </div>
                            </div>
                          </div>
                        </div>
                  </div>
            <ul class="nav nav-tabs">
              <li class="active"><a data-toggle="tab" href="#wp-chatbot-lng-general">
                <?php esc_html_e('General', 'chatbot'); ?>
                </a></li>
              <li><a data-toggle="tab" href="#wp-chatbot-lng-support">
                <?php esc_html_e('FAQ', 'chatbot'); ?>
                </a></li>
              <li><a data-toggle="tab" href="#wp-chatbot-lng-reserve-keyword">
                <?php esc_html_e('ChatBot Keywords', 'chatbot'); ?>
                </a></li>
            </ul>
            <div class="tab-content">
              <div id="wp-chatbot-lng-general" class="tab-pane fade in active">
                <div class="top-section">

                  <div class="row">
                    <div class="col-xs-12" id="wp-chatbot-language-section">
                      <p style="color: red"><b><?php esc_html_e('You can change ALL the texts used by the ChatBot to YOUR OWN language here (like Spanish, French etc.).',  'chatbot' );  ?></b></p>
                      <p><?php esc_html_e('After making changes in the language center or settings, please type reset and hit enter in the ChatBot to start testing from the beginning or open a new Incognito window (Ctrl+Shit+N in chrome).',  'chatbot' );  ?></p>
                      <p><?php esc_html_e('You could use &lt;br&gt; tag for line break.',  'chatbot' );  ?></p>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Your Company or Website Name', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_host"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_host') != '' ? get_option('qlcd_wp_chatbot_host') : 'Our Store') ); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Agent name', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_agent"
                                                           value="<?php echo (get_option('qlcd_wp_chatbot_agent') != '' ? esc_attr(get_option('qlcd_wp_chatbot_agent'))  : 'Carrie'); ?>">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('User demo name', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_shopper_demo_name"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_shopper_demo_name') != '' ? get_option('qlcd_wp_chatbot_shopper_demo_name')  : 'Amigo')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('YES', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_yes"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_yes') != '' ? get_option('qlcd_wp_chatbot_yes') : 'YES')); ?>">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('NO', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_no"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_no') != '' ? get_option('qlcd_wp_chatbot_no')  : 'NO')); ?>">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('OR', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_or"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_or') != '' ? get_option('qlcd_wp_chatbot_or')  : 'OR')); ?>">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Sorry', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font "
                                                           name="qlcd_wp_chatbot_sorry"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_sorry') != '' ? get_option('qlcd_wp_chatbot_sorry')  : 'Sorry')); ?>">
                      </div>

                      <div class="form-group">
                        <?php
                          $agent_join_options = maybe_unserialize(get_option('qlcd_wp_chatbot_agent_join'));
                          $agent_join_option = 'qlcd_wp_chatbot_agent_join';
                          $agent_join_text = __('has joined the conversation', 'chatbot');
                          $this->qcld_wb_chatbot_dynamic_multi_option($agent_join_options, $agent_join_option, $agent_join_text);
                        ?>
                      </div>
                    </div>
                    <!--col-xs-12-->
                    <div class="col-xs-12" id="wp-chatbot-language-section">
                      <h4 class="text-success">
                        <?php esc_html_e(' Message setting for Greetings: ', 'chatbot'); ?>
                      </h4>
                      <div class="form-group">
                        <?php
                          $welcome_to_options = maybe_unserialize(get_option('qlcd_wp_chatbot_welcome'));
                          $welcome_to_option  = 'qlcd_wp_chatbot_welcome';
                          $welcome_to_text    = esc_html('Welcome to');
                          $this->qcld_wb_chatbot_dynamic_multi_option($welcome_to_options, $welcome_to_option, $welcome_to_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                          $welcome_back_options = maybe_unserialize(get_option('qlcd_wp_chatbot_welcome_back'));
                          $welcome_back_option  = 'qlcd_wp_chatbot_welcome_back';
                          $welcome_back_text    = esc_html('Welcome back');
                          $this->qcld_wb_chatbot_dynamic_multi_option($welcome_back_options, $welcome_back_option, $welcome_back_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                          $back_to_start_options  = maybe_unserialize(get_option('qlcd_wp_chatbot_back_to_start'));
                          $back_to_start_option   = 'qlcd_wp_chatbot_back_to_start';
                          $back_to_start_text     = esc_html('Back to Start');
                          $this->qcld_wb_chatbot_dynamic_multi_option($back_to_start_options, $back_to_start_option, $back_to_start_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                          $hi_there_options = maybe_unserialize(get_option('qlcd_wp_chatbot_hi_there'));
                          $hi_there_option  = 'qlcd_wp_chatbot_hi_there';
                          $hi_there_text    = esc_html('Hi There!');
                          $this->qcld_wb_chatbot_dynamic_multi_option($hi_there_options, $hi_there_option, $hi_there_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                            $hello_options  = maybe_unserialize(get_option('qlcd_wp_chatbot_hello'));
                            $hello_option   = 'qlcd_wp_chatbot_hello';
                            $hello_text     = esc_html('Hello');
                            $this->qcld_wb_chatbot_dynamic_multi_option($hello_options, $hello_option, $hello_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                          $asking_name_options  = maybe_unserialize(get_option('qlcd_wp_chatbot_asking_name'));
                          $asking_name_option   = 'qlcd_wp_chatbot_asking_name';
                          $asking_name_text     = esc_html('May I know your name?');
                          $this->qcld_wb_chatbot_dynamic_multi_option($asking_name_options, $asking_name_option, $asking_name_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                          $i_am_options = maybe_unserialize(get_option('qlcd_wp_chatbot_i_am'));
                          $i_am_option  = 'qlcd_wp_chatbot_i_am';
                          $i_am_text    = esc_html('I am');
                          $this->qcld_wb_chatbot_dynamic_multi_option($i_am_options, $i_am_option, $i_am_text);
                        ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $name_greeting_options = maybe_unserialize(get_option('qlcd_wp_chatbot_name_greeting'));
                                                    $name_greeting_option = 'qlcd_wp_chatbot_name_greeting';
                                                    $name_greeting_text = esc_html('Nice to meet you');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($name_greeting_options, $name_greeting_option, $name_greeting_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $wildcard_msg_options = maybe_unserialize(get_option('qlcd_wp_chatbot_wildcard_msg'));
                                                    $wildcard_msg_option = 'qlcd_wp_chatbot_wildcard_msg';
                                                    $wildcard_msg_text = esc_html('I am here to find  what you need. What are you looking for?');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($wildcard_msg_options, $wildcard_msg_option, $wildcard_msg_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $empty_filter_msgs = maybe_unserialize(get_option('qlcd_wp_chatbot_empty_filter_msg'));
                                                    $empty_filter_msg = 'qlcd_wp_chatbot_empty_filter_msg';
                                                    $empty_filter_msg_text = esc_html('Sorry, I did not understand that');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($empty_filter_msgs, $empty_filter_msg, $empty_filter_msg_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $qlcd_wp_chatbot_did_you_mean = maybe_unserialize(get_option('qlcd_wp_chatbot_did_you_mean'));
                                                    $qlcd_wp_chatbot_did_you_means = 'qlcd_wp_chatbot_did_you_mean';
                                                    $wp_chatbot_no_result_text = esc_html('Did you mean?');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($qlcd_wp_chatbot_did_you_mean, $qlcd_wp_chatbot_did_you_means, $wp_chatbot_no_result_text);
                                                    ?>
                      </div>
                      <h4 class="text-success">
                        <?php esc_html_e('Message setting for Editor Box ', 'chatbot'); ?>
                      </h4>
                      <div class="form-group">
                        <?php
                                                    $is_typing_options = maybe_unserialize(get_option('qlcd_wp_chatbot_is_typing'));
                                                    $is_typing_option = 'qlcd_wp_chatbot_is_typing';
                                                    $is_typing_text = esc_html('is typing...');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($is_typing_options, $is_typing_option, $is_typing_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $send_a_msg_options = maybe_unserialize(get_option('qlcd_wp_chatbot_send_a_msg'));
                                                    $send_a_msg_option = 'qlcd_wp_chatbot_send_a_msg';
                                                    $send_a_msg_text = esc_html('Send a message');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($send_a_msg_options, $send_a_msg_option, $send_a_msg_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $choose_option_options = maybe_unserialize(get_option('qlcd_wp_chatbot_choose_option'));
                                                    $choose_option_option = 'qlcd_wp_chatbot_choose_option';
                                                    $choose_option_text = esc_html('Choose an option');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($choose_option_options, $choose_option_option, $choose_option_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $asking_email_options = maybe_unserialize(get_option('qlcd_wp_chatbot_asking_email'));
                                                    $asking_email_option = 'qlcd_wp_chatbot_asking_email';
                                                    $asking_email_text = esc_html('Please provide your email address');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($asking_email_options, $asking_email_option, $asking_email_text);
                                                 ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $invalid_email_options = maybe_unserialize(get_option('qlcd_wp_chatbot_invalid_email'));
                                                    $invalid_email_option = 'qlcd_wp_chatbot_invalid_email';
                                                    $invalid_email_text = esc_html('Sorry, Email address is not valid! Please provide a valid email.');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($invalid_email_options, $invalid_email_option, $invalid_email_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $asking_msg_options = maybe_unserialize(get_option('qlcd_wp_chatbot_asking_msg'));
                                                    $asking_msg_option = 'qlcd_wp_chatbot_asking_msg';
                                                    $asking_msg_text = esc_html('Thank you for email address. Please write your message now.');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($asking_msg_options, $asking_msg_option, $asking_msg_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('WPBot Support Mail Subject', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_email_sub"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_email_sub') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_email_sub')) : 'Support Email Subject') ); ?>">
                      </div>
                      <div class="form-group">
                        <?php
                                                    $support_option_again_options = maybe_unserialize(get_option('qlcd_wp_chatbot_support_option_again'));
                                                    $support_option_again_option = 'qlcd_wp_chatbot_support_option_again';
                                                    $support_option_again_text = esc_html('You may choose option from below.');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($support_option_again_options, $support_option_again_option, $support_option_again_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Your email was sent successfully.Thanks!', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_email_sent"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_email_sent') != '' ? get_option('qlcd_wp_chatbot_email_sent') : 'Your email was sent successfully.Thanks!')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Sorry! I could not send your mail! Please contact the webmaster.', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font " 
                               name="qlcd_wp_chatbot_email_fail"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_email_fail') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_email_fail')) : 'Sorry! fail to send email')); ?> ">
                      </div>

                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Check the relevant pages for more details and up to date information:', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font " 
                               name="qlcd_wp_chatbot_relevant_post_link_openai"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_relevant_post_link_openai') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_relevant_post_link_openai')) : 'Check the relevant pages for more details and up to date information:')); ?> ">
                      </div>

                      <div class="form-group">
                        <?php
                                                    $asking_phone_options = maybe_unserialize(get_option('qlcd_wp_chatbot_asking_phone'));
                                                    $asking_phone_option = 'qlcd_wp_chatbot_asking_phone';
                                                    $asking_phone_text = esc_html('Please provide your Phone number');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($asking_phone_options, $asking_phone_option, $asking_phone_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $thanks_phone_options = maybe_unserialize(get_option('qlcd_wp_chatbot_thank_for_phone'));
                                                    $thanks_phone_option = 'qlcd_wp_chatbot_thank_for_phone';
                                                    $thanks_phone_text = esc_html('Thank you for Phone number');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($thanks_phone_options, $thanks_phone_option, $thanks_phone_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Thanks for your phone number. We will call you ASAP!', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_phone_sent"
                               value="<?php echo  esc_attr((get_option('qlcd_wp_chatbot_phone_sent') != '' ?get_option('qlcd_wp_chatbot_phone_sent') : 'Thanks for your phone number. We will call you ASAP!')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Sorry! I could not collect phone number! Please contact the webmaster.', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_phone_fail"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_phone_fail') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_phone_fail')) : 'Sorry! I could not collect phone number!')); ?> ">
                      </div>
                      
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Please enter your keyword for searching', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_asking_search_keyword"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_asking_search_keyword') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_asking_search_keyword')) : 'Please enter your keyword for searching')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('We have found these results', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                               name="qlcd_wp_chatbot_found_result"
                               value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_found_result') != '' ? wp_unslash(get_option('qlcd_wp_chatbot_found_result')) : 'We have found these results')); ?> ">
                      </div>
                      <div class="form-group">
                        <?php
                                    $qlcd_wp_chatbot_product_fails = maybe_unserialize(get_option('qlcd_wp_chatbot_product_fail'));
                                    $qlcd_wp_chatbot_product_fail = 'qlcd_wp_chatbot_product_fail';
                                    $fail_text = esc_html('Sorry, I found nothing');
                                    $this->qcld_wb_chatbot_dynamic_multi_option($qlcd_wp_chatbot_product_fails, $qlcd_wp_chatbot_product_fail, $fail_text);
                                    ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="wp-chatbot-lng-support" class="tab-pane fade">
                <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12" id="wp-chatbot-language-section">
                      <div class="form-group">
                        <?php
                            $support_welcome_options = maybe_unserialize(get_option('qlcd_wp_chatbot_support_welcome'));
                            $support_welcome_option = 'qlcd_wp_chatbot_support_welcome';
                            $support_welcome_text = esc_html('Welcome to FAQ Section');
                            $this->qcld_wb_chatbot_dynamic_multi_option($support_welcome_options, $support_welcome_option, $support_welcome_text);
                        ?>
                      </div>
                      
                   
                      
                    </div>
                  </div>
                </div>
              </div>
              <div id="wp-chatbot-lng-reserve-keyword" class="tab-pane fade">
                <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12" id="wp-chatbot-language-section">
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Start Keyword', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_sys_key_help"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_sys_key_help') != '' ? get_option('qlcd_wp_chatbot_sys_key_help'): 'start')) ; ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title"><strong>
                          <?php esc_html_e('FAQ', 'chatbot'); ?>
                          </strong>
                          <?php esc_html_e('Keyword', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_sys_key_support"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_sys_key_support') != '' ? get_option('qlcd_wp_chatbot_sys_key_support') : 'faq')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title"><strong>
                          <?php esc_html_e('Converstion History Clear', 'chatbot'); ?>
                          </strong>
                          <?php esc_html_e('Keyword', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_sys_key_reset"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_sys_key_reset') != '' ? get_option('qlcd_wp_chatbot_sys_key_reset') : 'reset')); ?> ">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title"><strong>
                          <?php esc_html_e('eMail', 'chatbot'); ?>
                          </strong>
                          <?php esc_html_e('Keyword to Send eMail', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_sys_key_email"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_sys_key_email') != '' ? get_option('qlcd_wp_chatbot_sys_key_email'): 'email')); ?>">
                      </div>
                      <div class="form-group">
                        <?php
                                                    $help_welcome_options = maybe_unserialize(wp_kses_post(get_option('qlcd_wp_chatbot_help_welcome')));
                                                    $help_welcome_option = 'qlcd_wp_chatbot_help_welcome';
                                                    $help_welcome_text = esc_html('Welcome to Help Section');
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($help_welcome_options, $help_welcome_option, $help_welcome_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $help_msg_options = maybe_unserialize(wp_kses_post(get_option('qlcd_wp_chatbot_help_msg')));
                                                    $help_msg_option = 'qlcd_wp_chatbot_help_msg';
                                                    $help_msg_text = '<b>Type and Hit Enter</b><br><ul><li> <b>start</b> to Get back to the main menu. </li><li> <b>faq</b> for FAQ. </li><li> <b>email </b> to send eMail </li><li> <b>reset</b> to restart the chat</li></ul>';
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($help_msg_options, $help_msg_option, $help_msg_text);
                                                    ?>
                      </div>
                      <div class="form-group">
                        <?php
                                                    $reset_options = maybe_unserialize(wp_kses_post(get_option('qlcd_wp_chatbot_reset')));
                                                    $reset_option = 'qlcd_wp_chatbot_reset';
                                                    $reset_text = esc_html('Do you want to clear our chat history and start over?' );
                                                    $this->qcld_wb_chatbot_dynamic_multi_option($reset_options, $reset_option, $reset_text);
                                                    ?>
                      </div>
                    </div>
                    <!--                                            col-xs-12--> 
                  </div>
                  <!--                                        row--> 
                </div>
                <!--                                    top-section--> 
              </div>
            </div>
            <!--                            tab-content--> 
          </section>
          <section id="section-flip-51">
           <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->                   
            <div class="wp-chatbot-socail-btn-summmery"> </div>

            <div class="tab-content">
              <div id="wp-chatbot-lng-general" class="tab-pane fade in active">
                <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12" id="wp-chatbot-socila-btn">
               
                 
                             
                      <div class="form-group">		
                        <h4 class="qc-opt-title"> <?php echo esc_html__( 'Enable Chat Reactions for analytics (Like & Dislike)', 'chatbot' ); ?> </h4>
                          <div class="cxsc-settings-blocks">
                            <input value="1" id="skip_chat_reactions_menu" type="checkbox"
                                name="skip_chat_reactions_menu" <?php echo( get_option( 'skip_chat_reactions_menu', 1 ) == 1 ? 'checked' : '' ); ?>>
                              <label for="skip_chat_reactions_menu"><?php echo esc_html__( 'Enable Chat Reactions for analytics (Like & Dislike)', 'chatbot' ); ?> </label>
                          </div>
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Like Text', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font "
                                                           name="qlcd_wp_chatbot_like_text"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_like_text') != '' ? get_option('qlcd_wp_chatbot_like_text')  : 'Like')); ?>">
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Dislike Text', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font "
                                                           name="qlcd_wp_chatbot_dislike_text"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_dislike_text') != '' ? get_option('qlcd_wp_chatbot_dislike_text')  : 'Dislike')); ?>">
                      </div>

                      <div class="form-group">		
                        <h4 class="qc-opt-title"> <?php echo esc_html__( 'Enable Reporting Message', 'wpchatbot' ); ?> </h4>
                          <div class="cxsc-settings-blocks">
                            <input value="1" id="enable_chat_report_menu" type="checkbox"
                                name="enable_chat_report_menu" <?php echo( get_option( 'enable_chat_report_menu', 1 ) == 1 ? 'checked' : '' ); ?>>
                              <label for="enable_chat_report_menu"><?php echo esc_html__( 'Enable Reporting Message', 'wpchatbot' ); ?> </label>
                          </div>
                      </div>
                      <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Report this Message', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font "
                                                           name="qlcd_wp_chatbot_report_text"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_report_text') != '' ? get_option('qlcd_wp_chatbot_report_text')  : 'Report')); ?>">
                      </div>
                    <div class="form-group">		
                      <h4 class="qc-opt-title"> <?php echo esc_html__( 'Enable Chat Share', 'wpchatbot' ); ?> </h4>
                        <div class="cxsc-settings-blocks">
                          <input value="1" id="enable_chat_share_menu" type="checkbox"
                              name="enable_chat_share_menu" <?php echo( get_option( 'enable_chat_share_menu', 1 ) == 1 ? 'checked' : '' ); ?>>
                            <label for="enable_chat_share_menu"><?php echo esc_html__( 'Enable Chat Share', 'wpchatbot' ); ?> </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <h4 class="qc-opt-title">
                          <?php esc_html_e('Share', 'chatbot'); ?>
                        </h4>
                        <input type="text" class="form-control qc-opt-dcs-font "
                                                           name="qlcd_wp_chatbot_share_text"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_share_text') != '' ? get_option('qlcd_wp_chatbot_share_text')  : 'Share')); ?>">
                    </div>
                  
                    </div>
                 
                  </div>
                </div>
              </div>
             
            </div>
            <!-- tab-content--> 
          </section>
                        
          <section id="section-flip-3">
            <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->               
            <ul class="nav nav-tabs">
              <li class="active"><a data-toggle="tab" href="#wp-chatbot-icon-theme-settings"><?php esc_html_e('Icons & Themes', 'chatbot'); ?></a></li>
              <li><a data-toggle="tab" href="#wp-chatbot-custom-color-options"><?php esc_html_e('Custom Style Options', 'chatbot'); ?></a></li>
            </ul>
            <div class="tab-content">
              <div id="wp-chatbot-icon-theme-settings" class="tab-pane fade in active">
                <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12">
                    
                      <h3 class="qcld-wpbot-main-tabs-title">
                        <?php esc_html_e('WPBot Icon', 'chatbot'); ?>
                      </h3>
                      <div class="cxsc-settings-blocks">
                        <ul class="radio-list">
                      
                        <li>
                          <label for="wp_chatbot_icon_0" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-0.png" alt="">
                            <input id="wp_chatbot_icon_0" type="radio"
                                                                              name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-0.png' ? esc_attr('checked' ): ''); ?>
                                                                              value="<?php echo esc_attr('icon-0.png'); ?>">
                            <?php esc_html_e('Icon - 0', 'chatbot'); ?>
                          </label>
                       </li>
                        <li>
                            <label for="wp_chatbot_icon_1" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-1.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_1" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-1.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-1.png'); ?>">
                              <?php esc_html_e('Icon - 1', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_2" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-2.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_2" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-2.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-2.png'); ?>">
                              <?php esc_html_e('Icon - 2', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_3" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-3.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_3" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-3.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-3.png'); ?>">
                              <?php esc_html_e('Icon - 3', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_4" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-4.png"
                                                                alt="' ">
                              <input id="wp_chatbot_icon_4" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-4.png' ? esc_attr('checked'): ''); ?>
                                                                                value="<?php echo esc_attr('icon-4.png'); ?>">
                              <?php esc_html_e('Icon - 4', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_5" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-5.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_5" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-5.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-5.png'); ?>">
                              <?php esc_html_e('Icon - 5', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_6" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-6.png"
                                                                alt=" ">
                              <input id="wp_chatbot_icon_6" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-6.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-6.png'); ?>">
                              <?php esc_html_e('Icon - 6', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_7" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-7.png"
                                                                alt=" ">
                              <input id="wp_chatbot_icon_7" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-7.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-7.png'); ?>">
                              <?php esc_html_e('Icon - 7', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_8" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-8.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_8" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-8.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-8.png'); ?>">
                              <?php esc_html_e('Icon - 8', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_9" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-9.png"
                                                                alt=" ">
                              <input id="wp_chatbot_icon_9" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-9.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-9.png'); ?>">
                              <?php esc_html_e('Icon - 9', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_10" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-10.png"
                                                                alt=" ">
                              <input id="wp_chatbot_icon_10" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-10.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-10.png'); ?>">
                              <?php esc_html_e('Icon - 10', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_11" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-11.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_11" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-11.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-11.png'); ?>">
                              <?php esc_html_e('Icon - 11', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <label for="wp_chatbot_icon_12" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-12.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_12" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-12.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-12.png'); ?>">
                              <?php esc_html_e('Icon - 12', 'chatbot'); ?>
                            </label>
                          </li>
                       
                          <li>
                            <label for="wp_chatbot_icon_13" class="qc-opt-dcs-font"><img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>/icon-13.png"
                                                                alt="">
                              <input id="wp_chatbot_icon_13" type="radio"
                                                                                name="wp_chatbot_icon" <?php echo(get_option('wp_chatbot_icon') == 'icon-13.png' ? esc_attr('checked' ): ''); ?>
                                                                                value="<?php echo esc_attr('icon-13.png'); ?>">
                              <?php esc_html_e('Icon - 13', 'chatbot'); ?>
                            </label>
                          </li>
                          <li>
                            <?php
                              if (get_option('wp_chatbot_custom_icon_path') != "") {
                                  $wp_chatbot_custom_icon_path = get_option('wp_chatbot_custom_icon_path');
                              } else {
                                  $wp_chatbot_custom_icon_path = esc_url(QCLD_wpCHATBOT_IMG_URL) . 'custom.png';
                              }
                            ?>
                            <label for="wp_chatbot_custom_icon_input" class="qc-opt-dcs-font"> <img id="wp_chatbot_custom_icon_src "
                                                                src="<?php echo esc_url($wp_chatbot_custom_icon_path); ?>" alt=" ">
                              <input id="wp_chatbot_custom_icon_input" type="radio"
                                                                name="wp_chatbot_icon"
                                                                value="<?php echo esc_attr('custom.png' ); ?>" <?php echo(get_option('wp_chatbot_icon') == 'custom.png' ? esc_attr('checked' ): ''); ?>>
                              <?php esc_html_e('Custom Icon', 'chatbot'); ?>
                            </label>
                          </li>  
                        </ul>
                      </div>
                      <!--  cxsc-settings-blocks--> 
                     
                      <div class="row">
                        <div class="col-xs-12">
                          <div class="cxsc-settings-blocks">
                            <div class="form-group">
                              <h4 class="qc-opt-title">
                                <?php esc_html_e('WPBot Icon Background Color.', 'chatbot'); ?>
                              </h4>
                              <input id="wp_chatbot_floatingiconbg_color" type="text" name="wp_chatbot_floatingiconbg_color" value="<?php echo esc_attr((get_option('wp_chatbot_floatingiconbg_color') != '' ? get_option('wp_chatbot_floatingiconbg_color'): '#fff') ); ?>"/>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-xs-12">
                        <div class="form-group">  
                        <h4 class="qc-opt-title">
                            <?php esc_html_e(' Upload custom Icon ', 'chatbot'); ?> <span class="small"><span class="text-danger"><?php esc_html_e('** If you select custom icon, you must upload an icon image.', 'chatbot'); ?></span></span>
                          </h4>
                          <div class="cxsc-settings-blocks">
                            <input type="hidden" name="wp_chatbot_custom_icon_path"
                                                            id="wp_chatbot_custom_icon_path"
                                                            value="<?php echo esc_attr( $wp_chatbot_custom_icon_path ); ?>"/>
                            <button type="button" class="qcld_chatbot_custom_icon_button button">
                            <?php esc_html_e('Upload Icon', 'chatbot'); ?>
                            </button>
                          </div>
                        </div>
                        </div>
                      </div>



                      <div class="row">
                          <div class="col-xs-12">
                          <div class="form-group"> 
                          <h4 class="qc-opt-title">
                              <?php esc_html_e(' WPBot Agent Image', 'chatbot'); ?>
                            </h4>
                            <div class="cxsc-settings-blocks">
                              <ul class="radio-list radio-list-custom">
                                <li>
                                  <label for="wp_chatbot_agent_image_def" class="qc-opt-dcs-font"> <img src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>icon-13.png"
                                                                      alt="">
                                    <input id="wp_chatbot_agent_image_def" type="radio"
                                                                                      name="wp_chatbot_agent_image" <?php echo(get_option('wp_chatbot_agent_image') == 'agent-0.png' ? esc_attr('checked' ): ''); ?>
                                                                                      value="agent-0.png">
                                    <?php esc_html_e('Default Agent', 'chatbot'); ?>
                                  </label>
                                </li>
                                <li>
                            
                                      <?php
                                            if (get_option('wp_chatbot_custom_agent_path') != "") {
                                                $wp_chatbot_custom_agent_path = get_option('wp_chatbot_custom_agent_path');
                                            } else {
                                                $wp_chatbot_custom_agent_path = esc_url(QCLD_wpCHATBOT_IMG_URL) . 'custom.png';
                                            }
                                      ?>

                                  <label for="wp_chatbot_agent_image_custom" class="qc-opt-dcs-font"> <img id="wp_chatbot_custom_agent_src"
                                                                      src="<?php echo esc_url($wp_chatbot_custom_agent_path); ?>"
                                                                      alt="Agent">
                                    <input type="radio" name="wp_chatbot_agent_image"
                                                                      id="wp_chatbot_agent_image_custom"
                                                                      value="<?php echo esc_attr('custom-agent.png' ); ?>" <?php echo(get_option('wp_chatbot_agent_image') == 'custom-agent.png' ? esc_attr('checked' ): ''); ?>>
                                    <?php esc_html_e('Custom Agent', 'chatbot'); ?></label>

                                </li>
                              </ul>
                            </div>
                          <!--                                        cxsc-settings-blocks--> 
                        </div>
                      </div>
                    </div>
                     </div>
                  </div>
                </div>
                <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12">
                    <div class="form-group"> 
                    <h4 class="qc-opt-title">
                        <?php esc_html_e('Custom Agent Icon', 'chatbot'); ?>
                      </h4>
                      <div class="cxsc-settings-blocks">
                        <input type="hidden" name="wp_chatbot_custom_agent_path"
                                                        id="wp_chatbot_custom_agent_path"
                                                        value="<?php echo esc_attr( $wp_chatbot_custom_agent_path ); ?>"/>
                        <button type="button" class="wp_chatbot_custom_agent_button button">
                        <?php esc_html_e('Upload Agent Icon', 'chatbot'); ?>
                        </button>
                      </div>
                    </div>
                    </div>
                  </div>
                </div>
                <div id="top-section">
                <div class="form-group">  
                <div class="row">
                    <div class="col-sm-12">
                      <h4 class="qc-opt-title">
                        <?php esc_html_e('WPBot Themes', 'chatbot'); ?>
                      </h4>
                    </div>
                  </div>
                  <div class="row qcld_thumbnail_theme_prev">
                    <div class="col-sm-3">
                      <label for="qcld_wb_chatbot_theme_0"> <img class="thumbnail theme_prev"
                                                    src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>templates/template-00.JPG"
                                                    alt="Theme Basic">
                        <input id="qcld_wb_chatbot_theme_0" type="radio"
                                                    name="qcld_wb_chatbot_theme" <?php echo(get_option('qcld_wb_chatbot_theme') == 'template-00' ? esc_attr('checked' ): ''); ?>
                                                    value="template-00">
                        <?php esc_html_e('Theme Basic', 'chatbot'); ?>
                      </label>
                    </div>
                    <div class="col-sm-3">
                      <label for="qcld_wb_chatbot_theme_1"> <img class="thumbnail theme_prev"  
                                                    src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>templates/template-01.JPG"
                                                    alt="Theme Basic">
                        <input id="qcld_wb_chatbot_theme_1" type="radio"
                                                    name="qcld_wb_chatbot_theme" <?php echo(get_option('qcld_wb_chatbot_theme') == 'template-01' ? esc_attr('checked' ): ''); ?>
                                                    value="template-01">
                        <?php esc_html_e('Theme One', 'chatbot'); ?>
                      </label>
                    </div>
                    <div class="col-sm-6">
                      <label for="qcld_wb_chatbot_theme_horizontal"> <img class="thumbnail theme_prev"  
                                                    src="<?php echo esc_url(QCLD_wpCHATBOT_IMG_URL); ?>templates/horizontal.png"
                                                    alt="Theme horizontal">
                        <input id="qcld_wb_chatbot_theme_horizontal" type="radio" 
                                                    name="qcld_wb_chatbot_theme" <?php echo(get_option('qcld_wb_chatbot_theme') == 'template-horizontal' ? esc_attr('checked' ): ''); ?>
                                                    value="template-horizontal">
                        <?php esc_html_e('Theme Horizontal', 'chatbot'); ?>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
               </div>
   
              
              <div id="wp-chatbot-custom-color-options" class="tab-pane fade">
                <div class="top-section">
                  <div class="row">
   
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks cxsc-settings-blocks-style">
                       <div class="form-group">
                      <h4 class="qc-opt-title">
                        <?php esc_html_e('Custom Style Options', 'chatbot'); ?>
                      </h4>
                      <div class="cxsc-settings-blocks">
                        <input value="1" id="enable_wp_chatbot_custom_color" type="checkbox"
                            name="enable_wp_chatbot_custom_color" <?php echo(get_option('enable_wp_chatbot_custom_color') == 1 ? esc_attr('checked' ): ''); ?>>
                        <label for="enable_wp_chatbot_custom_color">
                          <?php esc_html_e('Enable Custom Styles ', 'wpbot'); ?>
                        </label>
                      </div>
                      </div>
                      </div>
                      </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Text Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_text_color" type="text"
                                name="wp_chatbot_text_color"
                                value="<?php echo esc_attr((get_option('wp_chatbot_text_color') != '' ? get_option('wp_chatbot_text_color') : '#37424c')); ?>"/>
                        </div>
                      </div>
                       </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Link Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_link_color" type="text"
                                    name="wp_chatbot_link_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_link_color') != '' ? get_option('wp_chatbot_link_color') : '#e2cc1f')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Link Hover Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_link_hover_color" type="text"
                                  name="wp_chatbot_link_hover_color"
                                  value="<?php echo esc_attr((get_option('wp_chatbot_link_hover_color') != '' ? get_option('wp_chatbot_link_hover_color') : '#734006')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Bot Message  Background Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_bot_msg_bg_color" type="text"
                                    name="wp_chatbot_bot_msg_bg_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_bot_msg_bg_color') != '' ? get_option('wp_chatbot_bot_msg_bg_color'): '#1f8ceb')) ; ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Bot Message Text Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_bot_msg_text_color" type="text"
                                  name="wp_chatbot_bot_msg_text_color"
                                  value="<?php echo esc_attr((get_option('wp_chatbot_bot_msg_text_color') != '' ? get_option('wp_chatbot_bot_msg_text_color') : '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('User Message  Background Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_user_msg_bg_color" type="text"
                                    name="wp_chatbot_user_msg_bg_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_user_msg_bg_color') != '' ? get_option('wp_chatbot_user_msg_bg_color') : '#ffffff') ); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('User Message Text Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_user_msg_text_color" type="text"
                                    name="wp_chatbot_user_msg_text_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_user_msg_text_color') != '' ? get_option('wp_chatbot_user_msg_text_color') : '#000000')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Buttons Background Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_buttons_bg_color" type="text"
                                    name="wp_chatbot_buttons_bg_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_buttons_bg_color') != '' ?get_option('wp_chatbot_buttons_bg_color'): '#1f8ceb')) ; ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Buttons Hover Background Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_buttons_bg_color" type="text"
                                name="wp_chatbot_buttons_bg_color_hover"
                                value="<?php echo esc_attr((get_option('wp_chatbot_buttons_bg_color_hover') != '' ? get_option('wp_chatbot_buttons_bg_color_hover') : '#1f8ceb')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Buttons Text Color.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_buttons_text_color" type="text"
                                    name="wp_chatbot_buttons_text_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_buttons_text_color') != '' ? get_option('wp_chatbot_buttons_text_color') : '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Buttons Text Color Hover.', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_buttons_text_color" type="text"
                                    name="wp_chatbot_buttons_text_color_hover"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_buttons_text_color_hover') != '' ? get_option('wp_chatbot_buttons_text_color_hover') : '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Header background Color', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_header_background_color" type="text"
                            name="wp_chatbot_header_background_color"
                            value="<?php echo esc_attr((get_option('wp_chatbot_header_background_color') != '' ? get_option('wp_chatbot_header_background_color'): '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Theme Primary Color', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_theme_primary_color" type="text"
                                    name="wp_chatbot_theme_primary_color"
                                    value="<?php echo esc_attr((get_option('wp_chatbot_theme_primary_color') != '' ? get_option('wp_chatbot_theme_primary_color') : '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Theme Secondary Color', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_theme_secondary_color" type="text"
                                name="wp_chatbot_theme_secondary_color"
                                value="<?php echo esc_attr((get_option('wp_chatbot_theme_secondary_color') != '' ? get_option('wp_chatbot_theme_secondary_color') : '#ffffff')); ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Font size', 'chatbot'); ?>
                          </h4>
                          <input id="wp_chatbot_font_size" type="number"
                              name="wp_chatbot_font_size"
                              value="<?php echo esc_attr((get_option('wp_chatbot_font_size') != '' ? get_option('wp_chatbot_font_size'): '16')) ; ?>"/>
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php 
                              esc_html_e('User message Font family ', 'chatbot'); 
                              $user_font = get_option('wp_chat_user_font_family') != '' ? get_option('wp_chat_user_font_family') : '';
                              $user_font_family = str_replace("\\", "",$user_font);
                            ?>
                          </h4>
                          <input id="wp_chatbot_user_font" type="text" name="wp_chatbot_user_font"
                                                        value="<?php echo esc_attr((get_option('wp_chatbot_user_font') != '' ? get_option('wp_chatbot_user_font') : ''));?>">
                          <input id="wp_chat_user_font_family" type="hidden"
                                                               name="wp_chat_user_font_family"
                                                               />
                        </div>
                      </div>
                                            </div>
                      <div class="col-xs-6">
                      <div class="cxsc-settings-blocks">
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php 
                              esc_html_e('Bot message Font family ', 'chatbot');
                              $bot_font = get_option('wp_chat_bot_font_family') != '' ? get_option('wp_chat_bot_font_family') : '';
                              $bot_font_family = str_replace("\\", "",$bot_font); 
                            ?>
                          </h4>
                          <input id="wp_chatbot_bot_font" type="text" name="wp_chatbot_bot_font"
                                                        value="<?php echo esc_attr((get_option('wp_chatbot_bot_font') != '' ? get_option('wp_chatbot_bot_font') : ''));?>">
                          <input id="wp_chat_bot_font_family" type="hidden"
                                                               name="wp_chat_bot_font_family"
                                                               />
                        </div>
                      </div>
                                            </div>
                      
                      <?php
                      $custom_js = "jQuery(function ($) {
                        $('#wp_chat_user_font_family').val(JSON.stringify(". esc_attr($user_font_family) ."));
                        $('#wp_chat_bot_font_family').val(JSON.stringify(". esc_attr($bot_font_family)."));
                        $('#wp_chatbot_user_font')
                        .fontpicker()
                        .on('change', function() {
                            var tmp = this.value.split(':'),
                                family = tmp[0],
                                variant = tmp[1] || '400',
                                weight = parseInt(variant,10),
                                italic = /i$/.test(variant);
                            var css = {
                                fontFamily:family,
                                fontWeight: weight,
                                fontStyle: italic ? 'italic' : 'normal'
                            };
                            $('#wp_chat_user_font_family').val(JSON.stringify(css));
                        });
                          $('#wp_chatbot_bot_font')
                        .fontpicker()
                        .on('change', function() {
                            var tmp = this.value.split(':'),
                                    family = tmp[0],
                                    variant = tmp[1] || '400',
                                    weight = parseInt(variant,10),
                                    italic = /i$/.test(variant);
                                    // Set selected font on body
                            var css = {
                                fontFamily:family, 
                                fontWeight: weight,
                                fontStyle: italic ? 'italic' : 'normal'
                            };
                            $('#wp_chat_bot_font_family').val(JSON.stringify(css));
                        });
                    });
                ";
                wp_add_inline_script('qcld-wp-chatbot-admin-js', $custom_js );
                      
                      ?>
                  </div>
                </div>
              </div>
            </div>
          </section>
          
          <section id="section-flip-4">
            <div class="row">
              <div class="col-xs-12">
              <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->
             </div>
            </div>
            <div class="top-section">
              <h3 class="qcld-wpbot-main-tabs-title">
                <?php esc_html_e('Build FAQ Query and Answers', 'chatbot'); ?>
                
              </h3>

<p><b><?php esc_html_e('These FAQs are not searchable. You can use ChatBot->Simple Text Responses for searchable FAQs', 'chatbot'); ?></b></p>

              <div class="block-inner ui-sortable qcld-faq-block" id="wp-chatbot-support-builder">
                <?php
                  $support_quereis=$this->qcld_wb_chatbot_str_replace(maybe_unserialize( get_option('support_query')));
                  $support_ans=$this->qcld_wb_chatbot_str_replace(maybe_unserialize( get_option('support_ans')));
                  if (($support_quereis[0] != '') && ($support_ans[0] != '') && !empty($support_ans[0]) && (count($support_ans) == count($support_quereis))) {
                      
                      $query_ans_counter=0;
                      foreach (array_combine($support_quereis, $support_ans) as $query => $ans) {
                          ?>
                <div class="row"> <span class="pull-right"> </span>
                  <div class="col-xs-12">
                    <button type="button" class="btn btn-danger btn-sm wp-chatbot-remove-support pull-right"> <span class="dashicons dashicons-clock"></span></button>
                   
                    <div class="cxsc-settings-blocks cxsc-settings-faq-blocks">
                      <p>
                       <b> <?php esc_html_e('FAQ query ', 'chatbot'); ?> </b>
                      </p>
                      <input type="text" class="form-control" name="support_query[]"
                         placeholder="<?php esc_html_e('FAQ query',  'chatbot' );  ?> " value="<?php echo esc_html( $query ); ?>">
                      <br>
                      <p>
                        <b><?php esc_html_e('FAQ answer', 'chatbot'); ?></b>
                      </p>
                      <?php
                      $anss = is_string( $ans ) ? $ans : '';
                      $answer = !is_array($ans) ?  stripcslashes($ans) : $anss;

                      wp_editor(html_entity_decode($answer), 'support_ans'.'_'.$query_ans_counter, array('textarea_name' =>
                      'support_ans[]',
                      'textarea_rows' => 20,
                      'editor_height' => 100,
                      'disabled'      => 'disabled',
                      'media_buttons' => false,
                      'tinymce'       => array(
                      'toolbar1'      => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink',)
                      )); 
                      
                      ?>
                    </div>
                  </div>
                </div>
                <?php
                                            $query_ans_counter++;
                                        }
                                        //}
                                    } else {
                                        ?>
                <div class="row"> <span class="pull-right"> </span>
                  <div class="col-xs-12">
                    <button type="button" class="btn btn-danger btn-sm wp-chatbot-remove-support pull-right"> <span class="dashicons dashicons-clock"></span> </button>
                  
                    <div class="cxsc-settings-blocks">
                      <p>
                       <b> <?php esc_html_e('FAQ Query', 'chatbot'); ?></b>
                      </p>
                      <input type="text" class="form-control" name="support_query[]"
                                                           placeholder="<?php esc_html_e('FAQ Query ', 'chatbot'); ?> ">
                      <br>
                      <p class="qc-opt-dcs-font"><strong>
                        <?php esc_html_e('Support answer', 'chatbot'); ?>
                        </strong></p>
                      <?php 
                          wp_editor(html_entity_decode(stripcslashes('')), 'support_ans_0', array('textarea_name' =>
                              'support_ans[]',
                              'textarea_rows' => 20,
                              'editor_height' => 100,
                              'disabled' => 'disabled',
                              'media_buttons' => false,
                              'tinymce'       => array(
                                  'toolbar1'      => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink',)
                          ));
                       ?>
                    </div>
                    <br>
                  </div>
                </div>
                <?php
                                    }
                                    ?>
              </div>
              <div class="row">
                <div class="col-sm-6 text-left"></div>
                <div class="col-sm-6 text-right">
                  <button class="btn btn-success btn-sm" type="button"
                                                id="add-more-support-query">
               <span class="dashicons dashicons-plus"></span>

                  </button>
                </div>
              </div>
            </div>
          </section>
       
          
          <section id="section-flip-6">
            <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->                     
            <?php 
              wp_enqueue_style('qcld-wp-chatbot-common-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/css/common-style.css', basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
              ?>
            <div class="top-section">
              <div class="notification-block-inner">
                <div class="row">
                  <div class="col-xs-12">
                  <div id="qcld-show-more-wrapper-box">
                    <div class="qcld-show-more-text qcld-show-more-show-more-height">         
                  <div class="row">
                      <div class="col-xs-12">
                        <h3 class="qcld-wpbot-main-tabs-title"><?php echo esc_attr('Predefined Intents'); ?></h3>
                        <div class="row">
                          <div class="col-xs-12">
                           <div class="form-group"> 
                          <h4 class="qc-opt-title">
                              <?php esc_html_e('Site Search', 'chatbot'); ?>
                            </h4>
                            <div class="cxsc-settings-blocks">
                              <input value="<?php echo esc_attr('1' ); ?>" id="disable_wp_chatbot_site_search" type="checkbox"
                                     name="disable_wp_chatbot_site_search" <?php echo(get_option('disable_wp_chatbot_site_search') == 1 ? esc_attr('checked' ): ''); ?>>
                              <label for="disable_wp_chatbot_site_search">
                                <?php esc_html_e('Disable site search feature and button on Start Menu', 'chatbot'); ?>
                              </label>
                              </br><small><?php echo esc_html( '(You can disable searching post contents option below so it searches only the post Titles for most relevancy)'); ?></small></br>  
                            </div>
                            <div class="cxsc-settings-blocks">
                              <input value="<?php echo esc_attr('1' ); ?>" id="enable_wp_chatbot_post_content" type="checkbox"
                                    name="enable_wp_chatbot_post_content" <?php echo(get_option('enable_wp_chatbot_post_content') == 1 ? esc_attr('checked' ): ''); ?>>
                              <label for="enable_wp_chatbot_post_content">
                                <?php esc_html_e('Enable Searching Post Contents as well', 'chatbot'); ?>
                              </label>
                            </div>
                          </div>
                           </div>
                        </div>
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Button Label', 'chatbot'); ?>
                          </h4>
                          <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_wildcard_site_search"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_wildcard_site_search') != '' ? get_option('qlcd_wp_chatbot_wildcard_site_search') : 'Site Search')); ?> ">
                        </div>                     
                        <div class="row">
                          <div class="col-xs-12">
                            <div class="form-group">
                          <h4 class="qc-opt-title">
                              <?php esc_html_e('Call Me', 'chatbot'); ?>
                            </h4>
                            <div class="cxsc-settings-blocks">
                              <input value="<?php echo esc_attr('1'); ?>" id="disable_wp_chatbot_call_gen" type="checkbox"
                                     name="disable_wp_chatbot_call_gen" <?php echo(get_option('disable_wp_chatbot_call_gen') == 1 ? esc_attr('checked' ): ''); ?>>
                              <label for="disable_wp_chatbot_call_gen">
                                <?php esc_html_e('Disable Call Me feature and button on Start Menu', 'chatbot'); ?>
                              </label>
                            </div>
                             </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Button Label', 'chatbot'); ?>
                          </h4>
                          <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_support_phone"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_support_phone') != '' ? get_option('qlcd_wp_chatbot_support_phone') : 'Leave your number. We will call you back!')); ?> ">
                        </div>
                        <div class="row">
                          <div class="col-xs-12">
                            <div class="form-group">
                          <h4 class="qc-opt-title">
                              <?php esc_html_e('Email', 'chatbot'); ?>
                            </h4>
                            <div class="cxsc-settings-blocks">
                              <input value="<?php echo esc_attr('1' ); ?>" id="disable_wp_chatbot_feedback" type="checkbox"
                                     name="disable_wp_chatbot_feedback" <?php echo esc_attr((get_option('disable_wp_chatbot_feedback') == 1 ? 'checked' : ''), 'chatbot' ); ?>>
                              <label for="disable_wp_chatbot_feedback">
                                <?php esc_html_e('Disable Email feature and button on Start Menu', 'chatbot'); ?>
                              </label>
                            </div>
                          </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Button Label', 'chatbot'); ?>
                          </h4>
                          <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_support_email"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_support_email') != '' ? get_option('qlcd_wp_chatbot_support_email') : 'Send us Email')); ?> ">
                        </div>
                        <div class="row">
                          <div class="col-xs-12">
                            <div class="form-group">
                          <h4 class="qc-opt-title">
                              <?php esc_html_e('FAQ', 'chatbot'); ?>
                            </h4>
                            <div class="cxsc-settings-blocks">
                              <input value="<?php echo esc_attr('1' ); ?>" id="disable_wp_chatbot_faq" type="checkbox"
                                     name="disable_wp_chatbot_faq" <?php echo(get_option('disable_wp_chatbot_faq') == 1 ? esc_attr('checked' ): ''); ?>>
                              <label for="disable_wp_chatbot_faq">
                                <?php esc_html_e('Disable FAQ feature and button on Start Menu', 'chatbot'); ?>
                              </label>
                            </div>
                          </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <h4 class="qc-opt-title">
                            <?php esc_html_e('Button Label', 'chatbot'); ?>
                          </h4>
                          <input type="text" class="form-control qc-opt-dcs-font"
                                                           name="qlcd_wp_chatbot_wildcard_support"
                                                           value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_wildcard_support') != '' ? get_option('qlcd_wp_chatbot_wildcard_support') : 'FAQ')); ?> ">
                        </div>
                        
                      </div>
                    </div>
                  </div>
                  <div class="qcld-show-more-show-more"><i class="dashicons dashicons-plus-alt" aria-hidden="true"></i></div>
                  </div>
                    <div class="qc_menu_setup_area">
                      <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Menu Sorting & Customization Area', 'chatbot'); ?></h3>
                      <p style="color:red"><?php esc_html_e('Always open a new Incognito window (Ctrl+Shit+N in chrome) to test after making any change.', 'chatbot'); ?></p>
                      <p><?php esc_html_e('To adjust the Active Menu ordering just drag it up or down.', 'chatbot'); ?><br>
                      <?php esc_html_e('To add a menu item in the', 'chatbot'); ?> <b><?php esc_html_e('Active Menu', 'chatbot'); ?> </b> <?php esc_html_e('simply drag a menu item from Available Menu column to the Active Menu. Double click to remove ', 'chatbot'); ?>.</p>
                      <p style="color:red;font-size:16px"><?php esc_html_e('**If you change any button label like FAQ, remove it from the active Menu Area. Add it back and save.', 'chatbot'); ?></p>
                      
                      <div class="qc_menu_area_box">
                      <div class="qc_menu_area">
                        <h4><?php esc_html_e( 'Acitve Menu', 'chatbot'); ?></h4>
                        <div class="qc_menu_area_container" id="qc_menu_area">
                          <?php 
                              if(get_option('qc_wpbot_menu_order')!=''):
                                if( is_string( get_option('qc_wpbot_menu_order') ) ):
                                  echo wp_kses_post(wp_kses_stripslashes(get_option('qc_wpbot_menu_order')));
                                  else:
                                endif;
                              ?>
              
                          
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="qc_menu_list_area">
                        <h4><?php esc_html_e('Available Menu', 'chatbot'); ?></h4>
                        <div class="qc_menu_list_container">
                          <p><?php esc_html_e('Predefined Intents', 'chatbot'); ?></p>
                          <ul>
                            <li>
                              <?php if(get_option('disable_wp_chatbot_faq')==''): ?>
                              <span class="qcld-chatbot-wildcard qc_draggable_item"  data-wildcart="support"><?php echo esc_attr(get_option('qlcd_wp_chatbot_wildcard_support')); ?></span>
                              <?php endif; ?>
                            </li>
                            <li>
                              <?php if(get_option('disable_wp_chatbot_site_search')==''): ?>
                              <span class="qcld-chatbot-wildcard qc_draggable_item qcld-chatbot-site-search"  data-wildcart="site_search"><?php echo esc_attr(get_option('qlcd_wp_chatbot_wildcard_site_search')); ?></span>
                              <?php endif; ?>
                            </li>
                            <li>
                              <?php if(get_option('enable_wp_chatbot_messenger')=='1'): ?>
                              <span class="qcld-chatbot-wildcard qc_draggable_item"  data-wildcart="messenger"><?php echo wp_kses_post(qcld_choose_random(maybe_unserialize(get_option('qlcd_wp_chatbot_messenger_label')))) ?></span>
                              <?php endif; ?>
                            </li>
                            <li>
                              <?php if(get_option('enable_wp_chatbot_whats')=='1'): ?>
                              <span class="qcld-chatbot-wildcard qc_draggable_item"  data-wildcart="whatsapp"><?php echo wp_kses_post(qcld_choose_random(maybe_unserialize(get_option('qlcd_wp_chatbot_whats_label')))); ?></span>
                              <?php endif; ?>
                            </li>
                            <li>
                              <?php if(get_option('disable_wp_chatbot_feedback')==''): ?>
                              <span class="qcld-chatbot-suggest-email qc_draggable_item"><?php echo esc_attr(get_option('qlcd_wp_chatbot_support_email')); ?></span>
                              <?php endif; ?>
                            </li>
                            <li>
                              <?php if(get_option('disable_wp_chatbot_call_gen')==''): ?>
                              <span class="qcld-chatbot-suggest-phone qc_draggable_item" ><?php echo esc_attr(get_option('qlcd_wp_chatbot_support_phone')); ?></span>
                              <?php endif; ?>
                            </li>
                          </ul>
                          <?php
                            if(class_exists('Qcformbuilder_Forms_Admin')){
                              
                                global $wpdb;

                                //To Do: Need to use prepared statement

                                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms where 1 and type=%s",'primary')); //DB Call OK, No Caching OK.
                                if(!empty($results)){
                                ?>
                          <p><?php esc_html_e('Conversational Form', 'chatbot'); ?></p>
                          <ul>
                            <?php
                                  foreach($results as $result){
                                      $form = maybe_unserialize($result->config);
                                  ?>
                            <li><span class="qcld-chatbot-wildcard qcld-chatbot-form qc_draggable_item" data-form="<?php echo esc_attr($form['ID']); ?>" ><?php echo esc_html($form['name']); ?></span></li>
                            <?php
                                                            }
                                                            ?>
                          </ul>
                          <?php
                                                        }
                                                    }
                                                    ?>
                        </div>
                      </div>

                    </div>


                    </div>
                    <input id="qc_wpbot_menu_order" type="hidden" name="qc_wpbot_menu_order" value='<?php echo esc_attr(wp_kses_stripslashes(get_option('qc_wpbot_menu_order'))); ?>' />
                  </div>
                </div>
              </div>
            </div>
          </section>
          <section id="section-flip-7">
            <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->
            <div class="top-section">
              <div class="wp-chatbot-language-center-summmery">
                <p>
                  <?php esc_html_e('DialogFlow as Artificial Intelligences Engine for wpwBot', 'chatbot'); ?>
                </p>
              </div>
             
              <div class="row wp-chatbot-language-center-summmery-checkbox">
                <div class="col-xs-12">
                  <div class="wrapTexttop-lite">                                   
                    <h3 class="qcld-wpbot-main-tabs-title"><?php esc_html_e('Enable DialogFlow as AI Engine to Detect Intent', 'chatbot'); ?> </h3>
                    <div class="alert alert-warning" role="alert">
                     <h5 style="color: #f00;font-weight:900"><?php esc_html_e('Please do not enable DialogFlow and OpenAI both at the same time.', 'chatbot'); ?> </h5>
                      <h5 style="color: #f00;font-weight:900 "><?php esc_html_e(' Enable either DialogFlow or OpenAI', 'chatbot'); ?> </h5>
                    </div>
                    <div class="cxsc-settings-blocks">
                      <input value="1" id="enable_wp_chatbot_dailogflow" type="checkbox"
                                                    name="enable_wp_chatbot_dailogflow" <?php echo(get_option('enable_wp_chatbot_dailogflow') == 1 ? esc_attr('checked' ): ''); ?>>
                      <label for="enable_wp_chatbot_dailogflow"><?php esc_html_e('Enable DialogFlow AI Engine to process Natural Language commands from users.', 'chatbot'); ?> </label> <br>
                     
                    </div>
                    </div>
                

              <div class="qcld-dialogflow-enable-notice">If you Enable DialogFlow but do not configure it properly, the ChatBot will not respond.</div>
            </div>
                
                <div class="col-xs-12" id="wp-chatbot-dialflow-section">
                  <p><?php esc_html_e('Log in to DialogFlow Console from', 'chatbot'); ?> 
                      <a class="wpbot_df_instruction" href="<?php echo esc_url('https://dialogflow.com/',  'chatbot'); ?>" 
                      target="_blank">
                      <?php esc_html_e('Here', 'chatbot'); ?></a> 
                      <?php esc_html_e('with your gmail account.', 'chatbot'); ?> <a class="wpbot_df_instruction" href="<?php echo esc_url(QCLD_wpCHATBOT_PLUGIN_URL.'download/wpwBot.zip'); ?>" download ><?php esc_html_e('Download', 'chatbot'); ?></a> <?php esc_html_e('the agent training data and import from DialogFlow->Settings->Export and Import tab. You can add your own intents in that agent but do not modify our following training intents which are', 'chatbot'); ?> <b><?php esc_html_e('email, email subscription, faq, get email, get name, help, phone, reset, site search and start.', 'chatbot'); ?></b> </p>
                  <h4 class="qc-opt-title"><?php esc_html_e('DialogFlow API Version', 'chatbot'); ?></h4>
                  <div class="form-group">
                    <label class="radio-inline ">
                      <input id="wp-chatbot-df-api" type="radio"
                                                        name="wp_chatbot_df_api"
                                                        value="v2" checked>
                      <?php esc_html_e('Dialogflow API V2', 'chatbot'); ?> </label>
                  </div>
                  <div id="wp-chatbot-df-section-v2" style="display:block"> 
                    <!-- Dialogflow V2 Configuration -->
                    
                    <?php if(!file_exists(QCLD_wpCHATBOT_GC_DIRNAME.'/autoload.php')): ?>
                    <div class="form-group"> <br>
                      <h4 class="qc-opt-title" style="color:red "><?php esc_html_e('For Interacting with Dialogflow V2 the Google Client Package is Required!', 'chatbot'); ?></h4>
                      <p><?php esc_html_e('Please click the download button below to download the Google Client package. The package will be downloaded inside your Wordpress`s', 'chatbot'); ?> <b><?php esc_html_e('/wp-content', 'chatbot'); ?></b> <?php esc_html_e('folder. This package is around', 'chatbot'); ?> <b><?php esc_html_e('0 MB', 'chatbot'); ?></b><?php esc_html_e(' in zip file format and it will be about', 'chatbot'); ?> <b><?php esc_html_e('49 MB', 'chatbot'); ?></b> <?php esc_html_e('after unzipping. Please make sure that your server has enough space to store that package.', 'chatbot'); ?></p>
                      <div class="qcld-wpbot-gcdownload-area">
                        <button class="btn btn-primary" id="qc_wpbot_gc_download" <?php echo (!is_writable(QCLD_wpCHATBOT_GC_ROOT)?'disabled':''); ?>><?php esc_html_e('Download and Install the Google Client', 'chatbot'); ?></button>
                        <?php 
                            if(!is_writable(QCLD_wpCHATBOT_GC_ROOT)){
                              echo '<span style="color:red;font-size: 12px; "><b>'.esc_html('wp-content').' </b> '.esc_html('folder is not writable.').'</span>';
                            }
                          ?>
                        <br>
                        <br>
                        <p><?php esc_html_e('Alternatively, If the download operation fails for some reason like folder permission or server timeout issue then you can manually upload the ', 'chatbot'); ?> <u title="Google Client"><?php esc_html_e('GC', 'chatbot'); ?></u> <?php esc_html_e('package by following some simple steps.', 'chatbot'); ?></p>
                        <p><?php esc_html_e('1. Download GC package from:', 'chatbot'); ?> <a href="<?php echo esc_url('https://github.com/qcloud/gc/archive/master.zip'); ?>" target="_blank"><?php esc_attr_e('https://github.com/qcloud/gc/archive/master.zip', 'chatbot'); ?></a></p>
                        <p><?php esc_html_e('2. Unzip the', 'chatbot'); ?> <b><?php esc_html_e('wpbotgc.zip', 'chatbot'); ?></b> <?php esc_html_e('inside to your computer.', 'chatbot'); ?></p>
                        <p><?php esc_html_e('3. Create a folder with name ', 'chatbot'); ?><b><?php esc_html_e('wpbot-dfv2-client', 'chatbot'); ?></b> <?php esc_html_e('under ', 'chatbot'); ?><b><?php esc_html_e('wp-content', 'chatbot'); ?></b> <?php esc_html_e('into your server.', 'chatbot'); ?></p>
                        <p><?php esc_html_e('4. Upload the upziped files and folders into', 'chatbot'); ?> <b><?php esc_html_e('wpbot-dfv2-client', 'chatbot'); ?></b><?php esc_html_e(' via FTP.', 'chatbot'); ?></p>
                        <div class="qcld_wpbot_download_statuses"> </div>
                      </div>
                      <br>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                      <h4 class="qc-opt-title" style="color:green "><?php esc_html_e('Google Client Package is Installed on Your Website.', 'chatbot'); ?></h4>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                      <h4 class="qc-opt-title" ><?php esc_html_e('Please follow along with this', 'chatbot'); ?>  <a href="<?php echo esc_url('https://www.wpbot.pro/dialogflow-integration'); ?>" target="_blank"><?php esc_html_e('tutorial.', 'chatbot'); ?> </a><?php esc_html_e(' It will help you to create a project id, private key and integrate WPBot with Dialogflow. For DialogFlow agent region, try choosing any region other than the EU region which has known issues.', 'chatbot'); ?> </h4>
                    </div>
                    <div class="form-group">
                      <h4 class="qc-opt-title"><?php esc_html_e('DialogFlow Project ID', 'chatbot'); ?></h4>
                      <p><?php esc_html_e('You can follow the', 'chatbot'); ?>  <a href="<?php echo esc_url('https://www.youtube.com/watch?v=qY-EHFY2wH8" '); ?>" target="_blank"><?php esc_html_e('tutorial', 'chatbot'); ?> </a><?php esc_html_e(' to get the Project ID.', 'chatbot'); ?>  </p>
                      <input type="text" class="form-control qc-opt-dcs-font"
                                                        name="qlcd_wp_chatbot_dialogflow_project_id"
                                                        value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_dialogflow_project_id') != '' ? get_option('qlcd_wp_chatbot_dialogflow_project_id') : '')); ?>" placeholder="<?php echo esc_attr('DialogFlow Project ID'); ?> ">
                    </div>
                    <?php 

                          $placeholderPrivatekey = '{
                              "type": "service_account",
                              "project_id": "PLACEYOUROWNID",
                              "private_key_id": "31e300128..........c48",
                              "private_key": "-----BEGIN PRIVATE KEY-----\nTHIS IS A DEMO PRIVATE KEY to SHOW HOW IT SHOULD LOOK. Replace with ACTUAL KEY.+OdT09MGEdAjlgSF2HMDA3fl8Ey4dmTxRfAN9No6G3Ugs3BrpZHfY\nSviWzS4JQ0GkCubcJc0DzJ8AqG6xX7E3SfKpdtKEn1eYV7sfQL3C2lm2lTj6nWdt\nxrkhJVHn61kxfPAWChnkdPa5EbMNFnV5mN5rhwaOxR+tEjW9nWBjVFG0NMnOMWF4\nsu6QJVjQMtI99jPBCid1r4XV/sPABSXh8dscWdMinGhZfuCjF4sOGHUUaw+VDGb6\nZpPOh65nw5fsdOHETsb4BN/LW72Khux+870Ig4jkuLIN3PnSF9QfwO8U2qTG5sZK\nn5nxhT9zAgMBAAECggEAX1NSWRGnrcVsu6n1jn9xUpzvxyy+CJe1Z1DvHo1tmHNS\n0Q8OI/Y........THIS IS A DEMO PRIVATE KEY to SHOW HOW IT SHOULD LOOK. Replace with your own key......................................paqQKBgQCJ\nvNCZIHpLGVqwiw4SVYgZW2i+VsZ78sOw0SuuhjZNmOlGjpalbQCjKs9l5dSz5t5D\nVleJVyriFaXyvVty/iF6orqTgv0EhcLO2RI9KSrTpbOXcIkgeunAhRM3oloxSndt\n98H3f1xRvTLIm1enERLkOyGHmm7nWFV0BQWD+CxeCwKBgDtBGn+uBgNgZjSaLnkJ\noemAoIBN6aD4+QWduPldRgTilH6ABQ26SL+t4sa9jbAtkMuD/hWOMLBqmz98tfCC\ndy6cPghea+b0S7lj/wmUaDA1Vmz7luCLm+lO+fe3K6WIlEhAP/9MXVH90Sj6CllF\nAn4stWzIKHrRKA3lIvgJv+9W\n-----END PRIVATE KEY-----\n",
                              "client_email": "dialogflow-evysjn@wpbotpro.iam.gserviceaccount.com",
                              "client_id": "1026.....032997",
                              "auth_uri": "https://accounts.google.com/o/oauth2/auth",
                              "token_uri": "https://oauth2.googleapis.com/token",
                              "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
                              "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/dialogflow-evysjn%40wpbotpro.iam.gserviceaccount.com"
                          }';

                          ?>
                    <div class="form-group">
                      <h4 class="qc-opt-title"><?php esc_html_e('Private Key', 'chatbot'); ?></h4>
                      <p><?php esc_html_e("Paste your google service account's private key JSON file's contents (string) here", 'chatbot'); ?> <a href="<?php echo esc_url('https://www.youtube.com/watch?v=qY-EHFY2wH8'); ?>" target="_blank"><?php esc_html_e('tutorial', 'chatbot'); ?></a> <?php esc_html_e('to get private key JSON file. ', 'chatbot'); ?></p>
                      <textarea class="form-control" rows="20" name="qlcd_wp_chatbot_dialogflow_project_key" placeholder='<?php echo esc_attr($placeholderPrivatekey); ?>'><?php echo esc_html(get_option('qlcd_wp_chatbot_dialogflow_project_key') != '' ? get_option('qlcd_wp_chatbot_dialogflow_project_key') : ''); ?></textarea>
                    </div>
                    
                    <!-- End Dialogflow V2 Configuration --> 
                  </div>
                  <div class="form-group">
                    <h4 class="qc-opt-title"><?php esc_html_e('Please Click the Button Below to Test Dialogflow Connection.', 'chatbot'); ?> </h4>
                    <p style="color:red "><?php esc_html_e('*Save settings before pressing Test Dialogflow Connection', 'chatbot'); ?><br>
                      <?php esc_html_e('*You must have owner permission for the service account your are using in Dialogflow agent.', 'chatbot'); ?></p>
                    <div class="cxsc-settings-blocks">
                      <button class="btn btn-primary" id="qc_wpbot_df_status"><?php esc_html_e('Test Dialogflow Connection', 'chatbot'); ?></button>
                      <div class="qcwp_df_status"></div>
                    </div>
                  </div>
                  <br>
                  <div class="form-group">
                    <h4 class="qc-opt-title">
                      <?php esc_html_e('DialogFlow Default reply', 'chatbot'); ?>
                    </h4>
                    <input type="text" class="form-control qc-opt-dcs-font"
                                                   name="qlcd_wp_chatbot_dialogflow_defualt_reply"
                                                   value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') != '' ? get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') : 'Sorry, I did not understand you. You may browse')); ?>" placeholder="<?php esc_html_e('DialogFlow default reply', 'chatbot'); ?> ">
                  </div>
                  <div class="form-group">
                    <h4 class="qc-opt-title"><?php esc_html_e('DialogFlow Agent Language (Ex: en)', 'chatbot'); ?></h4>
                    <input type="text" class="form-control qc-opt-dcs-font"
                                                   name="qlcd_wp_chatbot_dialogflow_agent_language"
                                                   value="<?php echo esc_attr((get_option('qlcd_wp_chatbot_dialogflow_agent_language') != '' ? get_option('qlcd_wp_chatbot_dialogflow_agent_language') : 'en')); ?>" placeholder="<?php esc_html_e('DialogFlow Agent Language', 'chatbot'); ?>">
                  </div>
                </div>
              </div>
            </div>
          </section>

        <section id="section-flip-9"> 
          <div class="cxsc-settings-blocks-addon"><?php esc_html_e('Install the Conversational Form Builder to Collect Information from the users and create Button (menu) Driven Conversations.', 'chatbot'); ?></br> 
           <?php  esc_html_e('After creating a Conversational form, you can add it to the ChatBot`s Start Menu from the ChatBot Settings->Start Menu', 'chatbot'); ?></b></div>
          
           <div class="qcld-recommendbot-conv-feature">
           <?php include_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/qcld-recommendbot-plugin.php'; ?>
                    </div>
            <div class="qcld-seo-help-feature">
            <h3><?php esc_html_e('What Can You Do with it?', 'chatbot'); ?></h3>
            <p><?php esc_html_e('Conversation Forms Pro allows you to create a wide variety of forms, that might include:', 'chatbot'); ?></p>
          <ul>
            <li><?php esc_html_e('🚀  Conditional Menu Driven Conversations', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Standard Contact Forms', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Dynamic, conditional Forms – where fields can change based on the user selections', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Job Application Forms', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Lead Capture Forms', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Various types of Calculators', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Feedback Survey Forms etc.', 'chatbot'); ?></li>
            <li><?php esc_html_e('🚀  Submit Forms to OpenAI for a Response with your instructions and user submitted data. Supports multi-modal mode. Users can upload an Image or Audio file along with other form fields that you create. Check this article on how Conversational forms work with OpenAI', 'chatbot'); ?>.</li>
          </ul>
        
          <h3><?php esc_html_e('How it Works?', 'chatbot'); ?></h3>
            <p><?php esc_html_e('After creating a Conversation or form, you can show it in the ChatBot in different ways.', 'chatbot'); ?></p>
              <ul>
                <li><?php esc_html_e('🚀  Add it to the Start menu', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Invoke the form intent with a system command to start inside ChatBot any time', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Load the single intent/form inside a ChatBot widget on any page', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Load the intent/form inside the ChatBot using a Click to Chat button that can be placed anywhere inside your content on any page.', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Trigger another conversational form or Intent like Live Chat using conditions', 'chatbot'); ?></li>
              </ul>
          <h3><?php esc_html_e('Feature Highlights', 'chatbot'); ?></h3>
                      

            <div class="qcld-seo-help-feature-left">
              <ul>
                <li><?php esc_html_e('🚀  Easy to use visual drag and drop conversations and form builder', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Create conversational form or just conversation using the HTML element', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Add text fields, single option selection or multiple option selection with checkboxes', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Add HTML contents inside the conversation forms', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  eMail conversations or forms to the admin at the end of conversation', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Set different recipient email addresses for different conversation forms', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Clone form', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Export/Import Forms', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Form Entries saved into database', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Export Form Entries as CSV', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  12 Ready Form Templates to Get Started with (Contact Form, Feedback form. Survey Form, BMR Calculator, Appointment Request, Booking Form, Room Area Calculator)', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  11 Input Fields (Simple Text, Hidden, Email Address, Number, URL, Calculation, Select Option, Checkbox, DateTime Picker, HTML, File Upload, OpenAI Instructions)', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Processor – Auto Responder – Send an auto response e-mail for form submission', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Unlimited Conditional Field creation', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Trigger another conversational form or Intent like Live Chat using conditions (**NEW)', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Unlimited Variables creation', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Form Revisions', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Create system command to initiate form for ChatBot', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Add form to Start Menu', 'chatbot'); ?></li>
                <li><?php esc_html_e('🚀  Submit Forms to OpenAI for a Response with your instructions and user submitted data. Supports multi-modal mode. Users can upload an Image or Audio file along with other form fields that you create. Check this article on how Conversational forms work with OpenAI.', 'chatbot'); ?></li>
              </ul>
              </div>

          </div>
        </section> 
        
          <section id="section-flip-13">
            <!-- <div class="<?php // esc_attr_e( 'cxsc-settings-blocks-notic" style="<?php // echo esc_attr('background: #2271B1'); ?>">
              <p class="<?php // esc_attr_e( 'd-ib"><?php // esc_html_e('New horizontal/wide template is now available. Select from Icons and Themes', 'chatbot'); ?>
            </div> -->           
              <div class="top-section">
                  <div class="row">
                    <div class="col-xs-12">
                      <h4 class="qc-opt-dcs"><?php esc_html_e('You can paste or write your custom css here.', 'chatbot'); ?></h4>
                      <textarea name="wp_chatbot_custom_css"
                                                      class="form-control wp-chatbot-custom-css"
                                                      cols="10"
                                                      rows="16"><?php echo esc_textarea(get_option('wp_chatbot_custom_css')); ?></textarea>
                    </div>
                  </div>
                  <!-- row--> 
                </div>
          </section>

 <section id="section-flip-11"> 
          <div class="cxsc-settings-blocks-addon">
          <?php esc_html_e('Harness the power of AI to generate SEO Freindly contents quickly. Install our SEO Help plugin with One Click Turbo AI Content Writer, YouTube Subtitle to SEO Article Generator with AI, Global AI writing panel, AI Image Generator and RSS Feed AutoBlogging with OpenAI or Google Gemini.', 'chatbot'); ?></div>
            
          <div class="qcld-recommendbot-conv-feature">
          <?php include_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/qcld-recommendbot-ai-plugin.php'; ?>

            </div>
            <div class="qcld-seo-help-feature">
            <h3><?php esc_html_e('SEO Help Features', 'chatbot'); ?></h3>
            <h4><?php esc_html_e('OpenAI ChatGPT or Google Gemini Powered Content Generator', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀  Supports both Google Gemini AI and OpenAI (ChatGPT)', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate Text Contents for your Blog with the Click of a Button – Turbo content generator', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate Single Articles directly from your Blog post edit page', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate BULK articles and schedule each article’s publish time', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Select to auto publish or save in draft', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate summeries for Blog Posts automatically using OpenAI ChatGPT. Automatic content summary (summarizer) or TLDR can be helpful for both your readers and SEO. Blog Post Article Summaries are automatically added to your contents and can make your content look fresh to Search Engines if you enable Generate multiple summaries and use them randomly.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Automatically Create and Insert AI Images inside your articles', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Options to fine tune your articles (How many headings?, Heading Tag, Writing Style, Writing Tone, Image Size, Language, Add Image, Add Tagline, Add Introduction, Add Conclusion, Add Faq, Anchor Text, Target URL, Add Call-to-Action, Call-to-Action Position, Add Keywords, Keywords to Avoid, Make Keywords Bold etc.)', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  A Collection of hundreds of ready Prompts for your ideas', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate royalty free AI Images with many different options (Artist, Style, Photography, Lighting, Subject, Camera, Composition, Resolution, Color, Special Effects, Size9(up to 4K))', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Save AI Generated images to Media Library with one click', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  AI Image Gutenberg Block. Create and Insert Image directly into your blog post', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate Meta Description with one click', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Add meta description tag automatically to the page source', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  OpenAI playground', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  ChatGPT and Google Gemini in WordPress backend', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Humanize article so they do not sound AI written', 'chatbot'); ?></p>       
            <h4><?php esc_html_e('YouTube Video Subtitle to SEO Article Generator with AI', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀  Generate Articles from YouTube Subtitles. Works Best for Long form Videos', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Rewrite subtitle to a SEO Friendly Blog Article', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Fine tune your articles (How many headings?, Heading Tag, Writing Style, Writing Tone, Image Size, Language, Add Image, Add Tagline, Add Introduction, Add Conclusion, Add Faq, Anchor Text, Target URL, Add Call-to-Action, Call-to-Action Position, Add Keywords, Keywords to Avoid, Make Keywords Bold etc.)', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Directly Generate a Blog Post Draft', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Humanize article so they do not sound AI written', 'chatbot'); ?></p>
            <h4><?php esc_html_e('RSS Feed to Post, Auto Blogging', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀  Automatically Import and Convert RSS feed items to WordPress pages, posts, and custom post type.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Automatically import and aggregate unlimited RSS feeds from unlimited sources', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Schedule RSS Feed to Post according to your need for Autoblogging', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Rewrite RSS feeds with OpenAI AI before publishing to prevent duplicate contents', 'chatbot'); ?></p>
            <h4><?php esc_html_e('SEO Features', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀  Outputs all the essential meta tags that are essential for Search Engine Optimization', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Meta tags can be set for each Post type', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Configure Meta Tags Globally and Per Post basis', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Generate and Add Meta Description with one click', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Set the OGP information required for Social Networking Websites such as Facebook and Twitter.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Customize the meta tag information individually for each post, page, and term.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Easily set the Google Analytics measurement code and Webmaster Tools verification code.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Works out of the box', 'chatbot'); ?></p>
            <h4><?php esc_html_e('SEO Optimized Images', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀  Dynamically insert SEO-Friendly “alt” and “title” attributes to your images. Simply activate the plugin, provide the pattern, and you are ready to go.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Dynamically replaces the “alt” and “title” tags. It does not make any changes to the database. This means that if you deactivate the plugin, everything will return to the original settings.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀  Insert an image name, a post title and a post category in the “title” and “alt” attributes of the image.', 'chatbot'); ?></p>
            <h4><?php esc_html_e('No-Index Features', 'chatbot'); ?></h4>
                <p>
                <?php esc_html_e('🚀 Easily add a meta-tag for robots noindex tags to parts of your WordPress site as necessary from a central location', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('1. Pages and Posts', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Front Page: Block the indexing of the website’s front page.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Home Page: Block the indexing of the website’s home page.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Page: Block the indexing of the site’s pages.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Privacy Policy Page: Block the indexing of the website’s privacy policy page.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Single Post: Block the indexing of a post on the site.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('2. Taxonomies', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Categories: Block the indexing of the website categories.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Tags: Block the indexing of the website’s tags.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('3. Dates', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Date: Block the indexing based on any date-based archive pages.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Day, Month, Time, Year: Block the indexing for daily, monthly, by time, or yearly archive of the site.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('4. Archives', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Archive: Block the indexing of any type of Archive page. Category, Tag, Author and Date based pages etc. are all types of Archives.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Author: Block the indexing of the author’s page, where the author’s publications appear.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Post Type Archive: Block the indexing of any post type page.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('5. Pagination and Search', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Pagination: Block the indexing of the pagination, i.e. all pages other than the main page of an archive.', 'chatbot'); ?><br>

                <?php esc_html_e('🚀 Search: Block the indexing of the internal search result pages.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('6. Attachments and Previews', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Attachment: Block the indexing of an attachment document to a post or page.', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Customize Preview: Block the indexing when a content is being displayed in customize mode', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Preview: Block the indexing when a single post is being displayed in draft mode.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('7. Error Page', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Error 404: This will cause an error page to be blocked from being indexed. As it is an error page, it should not be indexed per se, but just in case.', 'chatbot'); ?>
                </p>
            <h5><?php esc_html_e('8. Other Features', 'chatbot'); ?></h5>
                <p>
                <?php esc_html_e('🚀 Works with all modern browsers', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Scan for Broken Links on your website', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Open External links in new window', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Priority, Premium Support', 'chatbot'); ?><br>
                <?php esc_html_e('🚀 Auto Upgrades for Plugin', 'chatbot'); ?></p>
    </div>
        </section> 


 <footer class="wp-chatbot-admin-footer">
        <div class="row">
          
          
            <input type="submit" class="btn btn-primary submit-button" name="submit"
                                   id="submit" value="Save Settings"/>
          
        </div>
        <!--                    row--> 
      </footer>

        </div>



<div class="wp-chatbot-admin-upgrade-pro-sidebar">
			<div class="wp-chatbot-admingradient-color">
				<img class="wp-chatbot-admin-banner" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/template-sample.gif' ); ?>" alt="">
                    <h3 class="wp-chatbot-admincart-title">Upgrade To <span>Pro</span></h3>
					<ul class="feature-list">
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Core WPBot Pro</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Chat Sessions and Histories</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Extended Search Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">OpenAI Basic and DialogFlow</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Simple text Responses Pro Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Conversational Forms Pro Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">WooCommerce Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">OpenAI Pro Adv. (Fine Tuning, Assistant)</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Live (Human) Chat Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Tavily Search API module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Extended UI Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">WebHook & Mailing List Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">White Label Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">FaceBook Messenger Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">WhatsApp through Twilio Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Multi Language Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Settings Export Import Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Voice Message Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Voice Chat Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Telegram Module</li>
            <li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt="">Priority Technical Support</li>
					</ul>

				<a class="wp-chatbot-admin-pro-upgrade-button" target="_blank" href="https://www.wpbot.pro/">Upgrade to Pro <img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/external-white.svg' ); ?>" alt=""></a>
			</div>

		</div>




      </div>
     
    </section>






  </div>
  <?php wp_nonce_field('wp_chatbot'); ?>
</form>


</div>






