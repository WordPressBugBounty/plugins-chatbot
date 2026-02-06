<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('qcld_wpopenai_addons')){


    /**
     * Main Class.
     */
    final class qcld_wpopenai_addons
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
            $this->define_constants();
            $this->includes();
            add_action('wp_ajax_openai_settings_option', [$this, 'openai_settings_option_callback']);
            add_action('wp_ajax_update_settings_option', [$this, 'qcld_update_settings_option_callback']);
            add_action('wp_ajax_qcld_rag_settings_option', array($this, 'rag_settings_option_callback'));
            add_action('wp_ajax_qcld_openai_response',[$this,'qcld_openai_response_callback']);
            add_action('wp_ajax_nopriv_qcld_openai_response', [$this, 'qcld_openai_response_callback']);
            add_action('wp_ajax_openai_troubleshooting',[$this,'openai_troubleshooting']);
            if (is_admin() && !empty($_GET["page"]) && (($_GET["page"] == "openai-panel_dashboard") || ($_GET["page"] == "openai-panel_file") || ($_GET["page"] == "openai-panel_help"))) {
                add_action('admin_enqueue_scripts', array($this, 'qcld_wb_chatbot_admin_scripts'));
            }
     
        }

        
        /**
         * Define wpbot Constants.
         *
         * @return void
         * @since 1.0.0
         */
        public function define_constants() {
            if( ! defined( 'QCLD_openai_addon_VERSION' ) ){
                define('QCLD_openai_addon_VERSION', $this->version);
            }
           //define('QCLD_openai_addon_REQUIRED_wpCOMMERCE_VERSION', 2.2);

            if( ! defined( 'QCLD_openai_addon_PLUGIN_DIR_PATH' ) ){
                define('QCLD_openai_addon_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
            }
            if( ! defined( 'QCLD_openai_addon_PLUGIN_URL' ) ){
                define('QCLD_openai_addon_PLUGIN_URL', plugin_dir_url(__FILE__));
            }
            if( ! defined( 'QCLD_openai_addon_IMG_URL' ) ){
                define('QCLD_openai_addon_IMG_URL', QCLD_openai_addon_PLUGIN_URL . "images/");
            }
            if( ! defined( 'QCLD_openai_addon_IMG_ABSOLUTE_PATH' ) ){
                define('QCLD_openai_addon_IMG_ABSOLUTE_PATH', plugin_dir_path(__FILE__) . "images");
            }

        }


        public function qcld_wb_chatbot_admin_scripts(){
            // wp_register_style('qlcd-open-ai-bootstap', QCLD_openai_addon_PLUGIN_URL . 'css/openai-bootstrap.css', '', QCLD_openai_addon_VERSION, 'screen');
            // wp_enqueue_style('qlcd-open-ai-bootstap');
            // wp_register_style('qlcd-open-ai-admin-style', QCLD_openai_addon_PLUGIN_URL . 'css/openai-admin-style.css', '', QCLD_openai_addon_VERSION, 'screen');
            // wp_enqueue_style('qlcd-open-ai-admin-style');
            // wp_register_script('qlcd-openai_collapse', QCLD_openai_addon_PLUGIN_URL . 'js/collapse.js', array('jquery'),'',QCLD_openai_addon_VERSION,true);
            // wp_enqueue_script('qlcd-openai_collapse');
            // wp_register_script('qlcd-openai_settings', QCLD_openai_addon_PLUGIN_URL . 'js/openai_settings.js', array('jquery'),'',QCLD_openai_addon_VERSION,true);
            // wp_enqueue_script('qlcd-openai_settings');
            
            // wp_localize_script( 'qlcd-openai_settings', 'openai_ajax', array(
            //     'url' => admin_url( 'admin-ajax.php' ),
            // ) );
            
        }
        /**
         * Include all required files
         *
         * since 1.0.0
         *
         * @return void
         */
        public function includes() {
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/integration/openai/qcld_wp_OpenAI.php" );
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/integration/openai/OpenAi_WPBot_Menu.php" );
            require_once( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . "includes/Parsedown.php" );
            
        }
  
      
        public function buildFormBody( $fields, $boundary )
        {
            $body = '';
            foreach ( $fields as $name => $value ) {
            if ( $name == 'data' ) {
                continue;
            }
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"$name\"";
            if ( $name == 'file' ) {
                $body .= "; filename=\"{$value}\"\r\n";
                $body .= "Content-Type: application/json\r\n\r\n";
                $body .= $fields['data'] . "\r\n";
            }else {
                $body .= "\r\n\r\n$value\r\n";
            }
            }
            $body .= "--$boundary--\r\n";
            return $body;
        }

        // public function openai_file_list_callback(){
        //     $url = 'https://api.openai.com/v1/files';
        //     $apt_key = "Authorization: Bearer ". get_option('open_ai_api_key');
        //     $curl = curl_init();
        //     curl_setopt($curl, CURLOPT_URL, $url);
        //     $headers = array(
        //         "Content-Type: application/json",
        //         $apt_key,
        //     );
        //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //     $response = curl_exec($curl);
        //     curl_close($curl);
        //     wp_send_json( json_decode($response));
		//     wp_die();
        // }
        public function qcld_sanitize_text_or_array_field($array_or_string) {
            if( is_string($array_or_string) ){
                $array_or_string = sanitize_text_field($array_or_string);
            }elseif( is_array($array_or_string) ){
                foreach ( $array_or_string as $key => &$value ) {
                    if ( is_array( $value ) ) {
                        $value = $this->qcld_sanitize_text_or_array_field($value);
                    }
                    else {
                        $value = sanitize_text_field( $value );
                    }
                }
            }

            return $array_or_string;
        }
     
        // public function openai_file_upload_callback(){
        //     $uploadedfile = $_FILES['file'];
        //     $url = 'https://api.openai.com/v1/files';
        //     $apt_key = "Authorization: Bearer ". get_option('open_ai_api_key');
        //     $curl = curl_init($url);
        //     curl_setopt($curl, CURLOPT_URL, $url);
        //     curl_setopt($curl, CURLOPT_POST, true);
        //     $headers = array(
        //         "Content-Type: multipart/form-data",
        //         $apt_key,
        //     );
        //     if (function_exists('curl_file_create')) { 
        //         $tmp_file = curl_file_create($uploadedfile['tmp_name'], 'jsonl', $uploadedfile['name']);
        //     } else { 
        //         $tmp_file = open($uploadedfile['tmp_name']);
        //     }
        //     $data = array('file'=> $tmp_file,'purpose'=> 'fine-tune');
        //     $init = curl_init();
        //     //function parameteres
        //     curl_setopt($init, CURLOPT_URL,$url);
        //     curl_setopt($init, CURLOPT_HTTPHEADER, $headers);
        //     curl_setopt($init, CURLOPT_POSTFIELDS, $data);
        //     curl_setopt($init, CURLOPT_RETURNTRANSFER, true);
        //     $res = json_decode(curl_exec ($init));
            
        //     curl_close ($init);
        //     if(!empty($res->error)){
        //         $response['status'] = 'error';
        //         $response['message'] = $res->error->message;
        //     }
            
        //     if(!empty($res->status)){
        //         $response['status'] = 'success';
        //         $response['message'] = 'Successfully Created file' . $res->id ; 
                
        //     }
        //     echo wp_send_json([$response]);
        //     wp_die();
        // }

        public function openai_finetune_create($file_id,$ft_suffix,$ft_engines){
            $apt_key = "Authorization: Bearer ". get_option('open_ai_api_key');
            $headers = array(
                "Content-Type: application/json",
                $apt_key,
            );
            $curl = curl_init();
            $qcld_openai_suffix = isset($ft_suffix) ? $ft_suffix : get_option('qcld_openai_suffix');
            $openai_engines = isset($ft_engines) ? $ft_engines : get_option('openai_engines');
            $base_engine = explode('-',$openai_engines);
            if( $base_engine[0] == 'gpt'){
                $data = json_encode(array('training_file'=>$file_id,'model' => $openai_engines, 'suffix' => $qcld_openai_suffix ));
                $url = "https://api.openai.com/v1/fine_tuning/jobs";
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                $result = json_decode(curl_exec($curl));
                curl_close($curl);
            }else{

                $data = json_encode(array('training_file'=>$file_id,'model' => $base_engine[1], 'suffix' => $qcld_openai_suffix ));
                $url = "https://api.openai.com/v1/fine-tunes";
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                $result = json_decode(curl_exec($curl));
                curl_close($curl);
            }
            return $result;  
        }
        public function relevant_pagelink($search_query){
			
			$stopwords = explode( ',', get_option('qlcd_wp_chatbot_stop_words') );

            $finalQueryWordsWithoutStopWords = $this->qcpd_remove_wa_stopwords( strtolower($search_query), $stopwords );

            $cleanWordsWithoutPunctuationMarks = preg_replace('/[\p{P}]/u', '', $finalQueryWordsWithoutStopWords);

            $q = trim($cleanWordsWithoutPunctuationMarks);
        
            $links = [];
			
            $post_type_array = get_option('qcld_openai_relevant_post');
        
            //Proceeding with traditional search
        
                $the_query = new WP_Query( array( 'post_status' => 'publish', 'posts_per_page' => 5, 's' => esc_attr( $q ), 'post_type' => $post_type_array ) );
        
                if( $the_query->have_posts() ){
        
                    while( $the_query->have_posts() ){ 
        
                        $the_query->the_post();
        
                        $url  = esc_url( get_permalink() );
        
                        $link = '<li><mark><a style="color: #000" href=' . $url . '>' . get_the_title() . '</a><mark></li>';
        
                        array_push($links, $link);
        
                    } //End of WHILE
        
                    wp_reset_postdata();  
        
                } //End of IF
        
            $links = array_unique($links);
        
            return $links;
            
        } //End of function relevant_pagelink()
        public function openai_retrive_fine_tune($keyword){
           
            $apt_key = "Authorization: Bearer ". get_option('open_ai_api_key');
            $headers = array(
                "Content-Type: application/json",
                $apt_key,
            );
            $curl = curl_init();
            $max_tokens =  (int)get_option( 'openai_max_tokens');
            $temp = (float)get_option( 'openai_temperature');
            $frequency_penalty = (float)get_option( 'frequency_penalty');
            $presence_penalty = (float)get_option( 'presence_penalty');
            $engines = explode('-',get_option( 'openai_engines'));
            $custom_model = get_option( 'qcld_openai_custom_model');
            $custom_model = (explode(":",$custom_model));
            $prompts = $this->get_prompt($keyword);
            
            if($custom_model[1] != 'gpt-3.5-turbo-0613'){
                $data = json_encode(array(
                    'prompt'=>  $prompts,
                    'model'=> get_option( 'qcld_openai_custom_model'),
                    "max_tokens" => $max_tokens,
                    "temperature" => $temp,
                    "top_p" => 1,
                    "presence_penalty" => $frequency_penalty,
                    "frequency_penalty"=> $presence_penalty,
                    "best_of"=> 1,
                    "stop"=> ["\n###\n","###"]
                 )); 
                $url = "https://api.openai.com/v1/completions";
    
                $ch = curl_init();
    
                curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/completions');
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
                $result = (curl_exec($ch));
                $result = str_replace("#","",$result );
               
                return $result; 
                if (curl_errno($ch)) {
                    echo esc_html('Error: ' . curl_error($ch));
                }
                curl_close($ch);
            }else{
                $data = json_encode(array(
                    'model'=> get_option( 'qcld_openai_custom_model'),
                    "messages"=>  [
                      
                          [
                            "role"=> "user",
                            "content"=>$keyword
                          ]
                          
                    ],
                    
                 ));
                $ch = curl_init();
    
                curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
                $results = (curl_exec($ch));
                $results = str_replace("#","",$results );
                $result = (json_decode($results)->choices[0]->message->content);
                return $result; 
            
              
            }
            
        }
        public function response_form_file($keyword){
            $max_tokens =  (int)get_option( 'openai_max_tokens');
            $temp = (float)get_option( 'openai_temperature');
            $frequency_penalty = (float)get_option( 'frequency_penalty');
            $presence_penalty = (float)get_option( 'presence_penalty');
            $engines = explode('-',get_option( 'openai_engines'));
            if($engines[0] != 'gpt'){
               // $prompts = $this->get_prompt($keyword);
            }
         
            $request_body = [
                "prompt" =>   $keyword,
                "model" => get_option( 'qcld_openai_custom_model'),
                "max_tokens" => $max_tokens,
                "temperature" => 0,
                "top_p" => 1,
                "stop" => [], 
                "presence_penalty" => 0,
                "frequency_penalty"=> 0,
                "best_of"=> 1,
            ];
            $postFields = json_encode($request_body);
            $OpenAI =  new qcld_wp_OpenAI();
            $result = $OpenAI->get_response($postFields);

            return $result;
        }
        public function get_prompt($keyword){
          $openai_include_keyword =  get_option( 'openai_include_keyword'); 
          $openai_exclude_keyword = get_option( 'openai_exclude_keyword'); 
          $qcld_openai_prompt = get_option('qcld_openai_prompt',true);
      
        }
        public function include_exclude_prompt($keyword){
            $openai_include_keyword = strtolower(get_option('openai_include_keyword'));
            $openai_exclude_keyword = strtolower(get_option('openai_exclude_keyword'));
           
            if((get_option('openai_include_keyword')  != '') || (get_option('openai_exclude_keyword')  == '')){
                $prompts    = 'If the query is not relevant  to one of the keywords: '.$openai_include_keyword .' then only say DUH. Provide a response only if the following query is relevant to one of the keywords: '.$openai_include_keyword .' The actual query is as follows: '. $keyword;
                return $prompts;
            }else if((get_option('openai_include_keyword')  == '') || (get_option('openai_exclude_keyword')  != '')){
                
                $prompts = 'If the query is relevant to one of the keywords: ' .$openai_exclude_keyword . ',  then do not respond and only say "DUH."   The actual query is as follows: '. $keyword. '?/n';
                return $prompts;
            }else if((get_option('openai_include_keyword')  != '') || (get_option('openai_exclude_keyword')  != '')){
                $prompts    = 'If the query is not relevant  to one of the keywords: '.$openai_include_keyword .' then only say "DUH." Provide a response only if the following query is relevant to one of the keywords: '.$openai_include_keyword .' The actual query is as follows: '. $keyword;
                return $prompts;
            }
        }
        public function qcld_include_keyword_exist( $keyword ){
            $keyword = isset($keyword) ? $keyword : '';
            $openai_include_keywords = strtolower(get_option('openai_include_keyword'));
            if(!empty($keyword)){
                $openai_include_keyword = ( isset( $openai_include_keywords ) ?  $openai_include_keywords : '');
    
                if( !empty($openai_include_keyword)){
                    $include_items = explode(',', $openai_include_keyword);
                    if(!empty($include_items)){
                        foreach($include_items as $k => $item){
                            if((strpos($keyword,trim($item)) !== false) && !empty($item)){
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }
        
            return false;
    
        }

        public function qcld_openai_response_callback() {
             if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp_chatbot' ) ) {
                    wp_send_json_error([
                        'status'  => 'error',
                        'message' => esc_html__( 'Security check failed. Unauthorized request.', 'chatbot' )
                    ]);
                    wp_die();
                }
                $response['status'] = 'success';
                $response['message'] ='A preset message';
                $OpenAI =  new qcld_wp_OpenAI();
                $gptkeyword = [];
                $keyword = sanitize_text_field($_POST['keyword']);
                $relevant_pagelink = $this->relevant_pagelink($keyword);

                // Build context-aware system instructions
                $system_content = get_option('qcld_openai_system_content');
                
                // RAG Integration
                if (get_option('is_page_rag_enabled') == '1') {
                    $rag_context_text = Qcld_Bot_Rag::instance()->run_rag_search($keyword);
                    if (!empty($rag_context_text) && $rag_context_text != "No knowledge base found.") {
                         $rag_context = "Relevant Knowledge Base Information:\n";
                         $rag_context .= $rag_context_text;
                         $rag_context .= "\n\nUse the above information to answer the user's question. If the answer is not in the Knowledge Base, rely on your general knowledge but mention that this information is not in the local knowledge base.";
                         $system_content .= "\n\n" . $rag_context;
                    }
                }

                if ( get_option('context_awareness_enabled') == '1' ) {
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
                        
                        // Try to get post/page by URL
                        $post_id = url_to_postid( $ref );
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
                        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
                        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                        $current_url = esc_url_raw($scheme . '://' . $host . $request_uri);
                        
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
                        $context_info = 'Context Information: ' . implode( '. ', $context_bits ) . '. Please use this context to provide more relevant and accurate responses.';
                        $system_content = $system_content . "\n\n" . $context_info;
                    }
                }

                $relevant_pagelink = array_slice($relevant_pagelink, 0, 5, true);

                if( (get_option('page_suggestion_enabled') == '1') && count($relevant_pagelink) > 0 ){
					
                    $relevant_post_link = get_option('qlcd_wp_chatbot_relevant_post_link_openai');
                    
                    if(is_array($relevant_post_link )){
                       
                        $relevant_pagelinks = '<br><br><p><em>'. implode('', $relevant_post_link) .'</em></p><ul style="list-style: disc;padding-left: 10px;">'. implode(" ", $relevant_pagelink). '</ul>';
                   }else{
                    $relevant_pagelinks = '<br><br><p><em>'. $relevant_post_link .'</em></p><ul style="list-style: disc;padding-left: 10px;">'. implode(" ", $relevant_pagelink) .'</ul>';
                   }
                }else{
                    $relevant_pagelinks = '';
                }
              

                        array_push( $gptkeyword, array(
                            "role" => "system",
                            "content" =>   $system_content
                        ));
                        array_push($gptkeyword, array(
                            "role" => "user",
                            "content" =>  $keyword
                        ));
                        if(((get_option('openai_include_keyword')  != '') ||  (get_option('openai_exclude_keyword')  != '')) && (get_option('qcld_openai_relevant_enabled') == '1') ){
                            $prompts =  $this->include_exclude_prompt($keyword);
                        
                            $gptkeyword = [];
                            array_push($gptkeyword, array(
                                "role" => "user",
                                "content" =>  $prompts,
                            ));
                        }else if(((get_option('openai_include_keyword')  != '') ||  (get_option('openai_exclude_keyword')  != '')) && (get_option('qcld_openai_relevant_enabled') == '0')){
                            if($this->qcld_include_keyword_exist($keyword) == false){
                            
                                $response['message'] = 'Sorry, No result found!';
                                echo wp_json_encode($response);
                                wp_die();
                            }else{
                                array_push($gptkeyword, array(
                                    "role" => "user",
                                    "content" =>  $keyword
                                ));
                            }
                            
                        }
                        $res = $OpenAI->gptcomplete(
                            $gptkeyword
                        );   
                        $mess = json_decode($res); 
                        $Qcld_Parsedown = new Qcld_Parsedown();
                        $msg = $mess->output[0]->content[0]->text;
                        if( $msg == null || empty($msg) ){
                            $msg = $mess->output[1]->content[0]->text;
                        }
                        $msg = $Qcld_Parsedown->text($msg);
                  
                        $response['message'] = $msg ;
                        if(($response['message'] == 'DUH.') || ($response['message'] == 'DUH')){
                            $response['message'] = 'Sorry, No result found!';
                        }else{
                            $Qcld_Parsedown = new Qcld_Parsedown();
                            $msg = $mess->output[0]->content[0]->text;
                            if( $msg == null || empty($msg) ){
                                $msg = $mess->output[1]->content[0]->text;
                            }
                            $msg = $Qcld_Parsedown->text($msg);
                            $response['message'] = $msg . $relevant_pagelinks;
                        }
                   
                echo wp_json_encode($response);
                wp_die();
            //}
        }
        public function openai_settings_option_callback() {
		    $nonce =  sanitize_text_field($_POST['nonce']);

            if (! wp_verify_nonce($nonce,'wp_chatbot')) {
                wp_send_json(array('success' => false, 'msg' => esc_html__('Failed in Security check', 'chatbot')));
                wp_die();

            }else{
               
                $api_key = sanitize_text_field($_POST['api_key']);
                $openai_engines = sanitize_text_field($_POST['openai_engines']);

                $qcld_openai_prompt =  isset( $_POST['qcld_openai_prompt'] ) ? sanitize_text_field($_POST['qcld_openai_prompt']) : '';
                

                $max_tokens = sanitize_text_field($_POST['max_tokens']);
                $qcld_openai_suffix = (!empty($_POST['qcld_openai_suffix'])) ? sanitize_text_field($_POST['qcld_openai_suffix']) : '';

                $qcld_openai_custom_model = isset( $_POST['qcld_openai_custom_model'] ) ?  sanitize_text_field($_POST['qcld_openai_custom_model']) : '';

                

                $frequency_penalty = sanitize_text_field($_POST['frequency_penalty']);
                $presence_penalty = sanitize_text_field($_POST['presence_penalty']);
                $temperature = sanitize_text_field($_POST['temperature']);
                $ai_enabled = sanitize_text_field($_POST['ai_enabled']);
                $suggestion_enabled = sanitize_text_field($_POST['is_page_suggestion_enabled']);
                $context_awareness_enabled = sanitize_text_field($_POST['is_context_awareness_enabled']);
                $is_page_rag_enabled = sanitize_text_field($_POST['is_page_rag_enabled']);


                $is_relevant_enabled = sanitize_text_field($_POST['is_relevant_enabled']);
                $file_id = (!empty($_POST['file_id'])) ? sanitize_text_field($_POST['file_id']) : '';

                $qcld_openai_prompt_custom = isset( $_POST['qcld_openai_prompt_custom'] ) ? sanitize_text_field($_POST['qcld_openai_prompt_custom']) : '';
           
                $openai_post_types = array();
                if (isset($_POST['openai_post_type'])) {
                    $raw_post_types = wp_unslash($_POST['openai_post_type']);
                    if (is_array($raw_post_types)) {
                        $openai_post_types = array_map('sanitize_text_field', $raw_post_types);
                    } else {
                        $openai_post_types = sanitize_text_field($raw_post_types);
                    }
                }
                update_option('qcld_openai_relevant_post', $openai_post_types);

                $conversation_continuity = sanitize_text_field($_POST['conversation_continuity']);
				$qcld_openai_system_content = sanitize_text_field($_POST['qcld_openai_system_content']);
                $qcld_openai_append_content = sanitize_text_field($_POST['qcld_openai_append_content']);

				/* Customized by Kadir on 05-12-2023 : To set empty value for API field */
                $disable_ss = isset( $_POST['disable_ss'] ) ? sanitize_text_field( $_POST['disable_ss'] ) : ''; 

                
                if($api_key  != ''){
                    update_option( 'open_ai_api_key', $api_key );
                }
                else{
                    delete_option( 'open_ai_api_key');
                }
                
                /* Ends: Customized by Kadir on 05-12-2023 : To set empty value for API field */
				
                if($openai_engines  != ''){
                    update_option( 'openai_engines', $openai_engines );
                }
                if($conversation_continuity  != ''){
                    update_option( 'conversation_continuity', $conversation_continuity );
                }
                update_option( 'openai_max_tokens', $max_tokens );
                
                if($qcld_openai_suffix != ''){
                update_option('qcld_openai_suffix', $qcld_openai_suffix);
                }
                if($frequency_penalty  != ''){
                update_option( 'frequency_penalty', $frequency_penalty );
                }
                if($presence_penalty  != ''){
                    update_option( 'presence_penalty', $presence_penalty );
                }
                if($temperature  != ''){
                    update_option( 'openai_temperature', $temperature );
                }
                if($qcld_openai_prompt_custom  != ''){
                    update_option('qcld_openai_prompt_custom', $qcld_openai_prompt_custom );
                }
                update_option('qcld_openai_custom_model',$qcld_openai_custom_model);
                update_option( 'qcld_openai_system_content', stripslashes( $qcld_openai_system_content) );
                update_option( 'qcld_openai_append_content', stripslashes( $qcld_openai_append_content) );

                update_option('ai_enabled',$ai_enabled);
                if( $ai_enabled == 1 ){
          
                    update_option('qcld_openrouter_enabled',0);
                    update_option('qcld_gemini_enabled',0);
                }
               
                update_option('qcld_openai_relevant_enabled',$is_relevant_enabled);
                update_option('page_suggestion_enabled',$suggestion_enabled);
                update_option('context_awareness_enabled',$context_awareness_enabled);
                update_option('is_page_rag_enabled',$is_page_rag_enabled);


                if($file_id  != ''){
                    update_option('file_id',$file_id);
                }
                $openai_include_keyword = sanitize_text_field($_POST['openai_include_keyword']);
                update_option('openai_include_keyword',$openai_include_keyword);
                $openai_exclude_keyword = sanitize_text_field($_POST['openai_exclude_keyword']);
                update_option('openai_exclude_keyword',$openai_exclude_keyword);
				
				
				/* Customized by Kadir on 05-12-2023 : To Disable Site Search*/
                //Disable Site Search
                if( $disable_ss == 1 ){
                    update_option('disable_wp_chatbot_site_search',1);
                    update_option('enable_wp_chatbot_post_content', '');
                }
                /* Ends: Customized by Kadir on 05-12-2023 : To Disable Site Search*/
				
				
              
            }
                if($qcld_openai_prompt != ''){
                    update_option('qcld_openai_prompt', $qcld_openai_prompt);
                }
                $tem = get_option( 'openai_temperature', $temperature );
            
                echo wp_json_encode($ai_enabled);wp_die();
            
        }
        public function rag_settings_option_callback()
		{

			$is_rag_enabled = sanitize_text_field($_POST['is_page_rag_enabled']);
            $rag_embed_pages = sanitize_text_field($_POST['rag_embed_pages'] ?? 0);
            $rag_embed_posts = sanitize_text_field($_POST['rag_embed_posts'] ?? 0);
            $rag_embed_str = sanitize_text_field($_POST['rag_embed_str'] ?? 0);
            $rag_auto_sync_enabled = sanitize_text_field($_POST['rag_auto_sync_enabled'] ?? 0);
         
            $rag_embed_cpts = isset($_POST['rag_embed_cpts']) ? $_POST['rag_embed_cpts'] : [];
            if(is_array($rag_embed_cpts)){
                $rag_embed_cpts = array_map('sanitize_text_field', $rag_embed_cpts);
            }

			update_option('is_page_rag_enabled', $is_rag_enabled);
            update_option('rag_embed_pages', $rag_embed_pages);
            update_option('rag_embed_str', $rag_embed_str);
            update_option('rag_embed_posts', $rag_embed_posts);
            update_option('rag_auto_sync_enabled', $rag_auto_sync_enabled);
            update_option('rag_embed_cpts', $rag_embed_cpts);

            if($is_rag_enabled == 1){
                update_option('is_asst_enabled', 0);
            }

			echo json_encode($is_rag_enabled);
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
        public function qcpd_remove_wa_stopwords($query, $stopwords){
			
            return preg_replace('/\b('.implode('|',$stopwords).')\b/','',$query);
			
        }
		public function openai_troubleshooting() {
			$nonce  = sanitize_text_field( $_POST['nonce'] );
			$OpenAI = new qcld_wp_OpenAI();
			if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Failed in Security check', 'chatbot' ),
					)
				);
				wp_die();

			} elseif ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json(
					array(
						'success' => false,
						'msg'     => esc_html__( 'Unauthorized user', 'chatbot' ),
					)
				);
				wp_die();
			} else {
				$gptkeyword = array();
				array_push(
					$gptkeyword,
					array(
						'role'    => 'user',
						'content' => 'Is our request comes from openAI ?',
					)
				);
				$res = $OpenAI->gptcomplete(
					$gptkeyword
				);

				if ( empty( json_decode( $res )->error ) ) {
					$mess = json_decode( $res );
					$msg  = preg_replace( "/\r\n|\r|\n/", '<br/>', $mess->output[0]->content[0]->text );
					wp_send_json(
						array(
							'success' => true,
							'title'   => esc_html__( 'success', 'chatbot' ),
							'icon'    => esc_html__( 'success', 'chatbot' ),
							'msg'     => esc_html( $msg ),
						)
					);
				} else {

					wp_send_json(
						array(
							'success' => true,
							'title'   => esc_html__( 'Error', 'chatbot' ),
							'icon'    => esc_html__( 'error', 'chatbot' ),
							'msg'     => esc_html( json_decode( $res )->error->message ),
						)
					);
				}
			}
		}
      

    }

    /**
     * @return qcld_wpopenai_addon
     */
    if(!function_exists('qcld_wpopenai_addons')){
        function qcld_openais() {
            $qcld_wpopenai_addon = new qcld_wpopenai_addons();
            return $qcld_wpopenai_addon->instance();
        
        }
    }
  
    //fire off the plugin
    qcld_openais();

}