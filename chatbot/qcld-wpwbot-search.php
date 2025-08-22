<?php
/**
 * Product indexing, caching & searching features concept is taken from open source 'Advanced wp Search' Wp plugin by ILLID.
 */
//include_once( 'includes/class-wpwbot-cache.php' );

include_once( 'includes/class-wpwbot-table.php' );
include_once( 'includes/class-wpwbot-search.php' );

function wpbo_search_site() {
	
	global $wpdb;
	// Limit results to only 3 items.
	$limit = 5;

	if(get_option('enable_wp_chatbot_post_content') == 1){
		$keyword 			= isset( $_POST['keyword'] )    ? sanitize_text_field($_POST['keyword']) : '';
		// $enable_post_types 	= array( 'post', 'page');
		$total_items 		= $limit; // Force limit to 3
		$query_arg 			= array(
		//	'post_type'     => $enable_post_types,
			'post_status'   => 'publish',
			'posts_per_page'=> $total_items,
			's'             => stripslashes( $keyword ),
			'paged'			=> 1,
			'suppress_filters' => true
		);
		$resultss = new WP_Query( $query_arg );
		$results = $resultss->posts;
	}else{
		$keyword 	= isset( $_POST['keyword'] )    ? sanitize_text_field($_POST['keyword']) : '';
		$sql 		= $wpdb->prepare("SELECT * FROM ". $wpdb->prefix."posts where post_status='publish' and ((post_title LIKE %s)) order by ID DESC LIMIT %d", '%' . $wpdb->esc_like($keyword) . '%', $limit);
		$results 	= $wpdb->get_results( $sql ); //DB Call OK, No Caching OK
	}
	
	if(!empty( $results )){
		$default_language = get_option('qlcd_wp_chatbot_default_language');
		$response['status'] = 'success';
		$response['html'] 	= '<div class="wpb-search-result">';
		$total_post 		= 0;
		$responses 			= '';
		
		foreach ( $results as $result ) {
			$featured_img_url = get_the_post_thumbnail_url( $result->ID, 'full' );
			$excerpt = '';
			if ( isset( $result->ID ) ) {
				$post_obj = get_post( $result->ID );
				if ( $post_obj ) {
					if ( has_excerpt( $result->ID ) ) {
						$excerpt = get_the_excerpt( $result->ID );
					} else {
						$excerpt = wp_trim_words( strip_shortcodes( $post_obj->post_content ), 20, '...' );
					}
				}
			}
			$url_check = str_replace(site_url(), '', get_permalink($result->ID));
			$url_check = explode('/',$url_check);
			$url_check = str_replace('/', '', $url_check);
		
			$total_post = $total_post + 1;
			$responses .='<div class="wpbot_card_wraper">';
			$responses .=	'<div class="wpbot_card_image '.($result->post_type=='product'?'wp-chatbot-product':'').' '.( isset($featured_img_url) && $featured_img_url==''?'wpbot_card_image_saas':'').'"><a href="'.esc_url(get_permalink($result->ID)).'" target="_blank" '.($result->post_type=='product'?'wp-chatbot-pid="'.$result->ID.'"':'').'>';
			if( isset($featured_img_url) && $featured_img_url!=''){
				$responses .=		'<img src="'.esc_url_raw($featured_img_url).'" />';
			}
			$responses .=		'<div class="wpbot_card_caption '.( isset($featured_img_url) && $featured_img_url==''?'wpbot_card_caption_saas':'').'">';
			$responses .=			'<p><span style="padding: 0 5px;color: #1d73b4;display: inline-block;margin: 0 5px 0 0;width: 18px;height: 18px;border-radius: 50%;font-size: 20px;line-height: 22px;"> ✓ </span> '.esc_html($result->post_title).'</p>';
			$responses .=			'<p>'.esc_html($excerpt).'</p>';
			if($result->post_type=='product'){
				if ( class_exists( 'WooCommerce' ) ) {
					if ( $result->ID ) {
						$product = wc_get_product( $result->ID );
						$responses .=			'<p class="wpbot_product_price">'.get_woocommerce_currency_symbol().$product->get_price_html().'</p>';
					}
				}
			}
			$responses .=		'</div>';
			$responses .=	'</a></div>';
				$responses .='</div>';
			
		}
		$response['html'] .= $responses;
		$response['html'] .='</div>';
		if($total_post >= 5 ){
			$load_more = maybe_unserialize(get_option('qlcd_wp_chatbot_load_more_search'));
		
			$response['html'] .='<button type="button" class="wp-chatbot-loadmore" data-search-type="default-wp-search" data-keyword="'.$keyword.'" data-page="2">'.'Load More'.'  <span id="wp-chatbot-loadmore-loader" class="wp-chatbot-loadmore-loader"></span></button>';
			
		}
	}else{
		$response['status'] = 'success';
		$q = (explode(" ",$keyword)); 
		$n = '%';
		$search =
		$searchand = '';
		$results = [];
		$default_language = get_option('qlcd_wp_chatbot_default_language');
		foreach ( (array) $q as $term ) {
			$term = esc_sql( $wpdb->esc_like( $term ) );
			

			$sql = $wpdb->prepare("SELECT * FROM ". $wpdb->prefix."posts where post_type in ('page', 'post') and post_status='publish' and ((post_title LIKE %s)) order by ID DESC", '%'. $term .'%');

			$results[] = $wpdb->get_results( $sql ); //DB Call OK, No Caching OK

		}
		if(  !empty( $results) ){
			$count = 0;
			$response['html'] = '<div class="wpb-search-result">';
			$total_post = 0;
			$responses = '';
			$featured_img_url = '';
			foreach ($results as $value) {
				if(!empty($value[0]->guid)){
					$url_check = str_replace(site_url(), '', get_permalink($value[0]->ID));
					$url_check = explode('/',$url_check);
					$url_check = str_replace('/', '', $url_check);
					$selected_lan = get_option('qlcd_wp_chatbot_default_language');
					if($url_check[1] == $selected_lan){
						$total_post = $total_post + 1;
						$responses .='<div class="wpbot_card_wraper">';
						$responses .=	'<div class="wpbot_card_image '.($featured_img_url==''?'wpbot_card_image_saas':'').'"><a href="'.$value[0]->guid.'" target="_blank">';
						if($featured_img_url!=''){
							$responses .=		'<img src="'.$featured_img_url.'" />';
						}
						$responses .=		'<div class="wpbot_card_caption '.($featured_img_url==''?'wpbot_card_caption_saas':'').'">';
						$responses .=			'<p><span style="padding: 0 5px;color: #1d73b4;display: inline-block;margin: 0 5px 0 0;width: 18px;height: 18px;border-radius: 50%;font-size: 20px;line-height: 22px;"> ✓ </span>'.$value[0]->post_title.'</p>';
						$responses .=		'</div>';
						$responses .=	'</a></div>';
						$responses .='</div>';
					}		
				}
			}
			if($total_post > 2 ){
				$load_more = maybe_unserialize(get_option('qlcd_wp_chatbot_load_more_search'));
				$response['html'] .='<button type="button" class="wp-chatbot-loadmore2" data-search-type="default-wp-search" data-keyword="'.$keyword.'" data-page="2">'. (($load_more !='') ? $load_more[$default_language] :'Load More').'  <span id="wp-chatbot-loadmore-loader" class="wp-chatbot-loadmore-loader"></span></button>';
				$response['status'] = 'success';
			}else{
				$response['status'] = 'fail';
			}
			
			$response['html'] .= $responses;
			$response['html'] .='</div>';
		

			
		}
	}
	echo json_encode($response);
	wp_die();
}

add_action( 'wp_ajax_wpbo_search_site',        'wpbo_search_site' );
add_action( 'wp_ajax_nopriv_wpbo_search_site', 'wpbo_search_site' );

if ( ! function_exists( 'qcld_wpbot_modified_keyword' ) ) {
	function qcld_wpbot_modified_keyword( $keyword ) {
		$keyword = rtrim( $keyword, '!' );
		$pattern = '/[?\/]/';
		$strings = preg_split( $pattern, $keyword );
		$strings = array_filter( array_map( 'trim', $strings ) );
		$keyword = rtrim( $strings[0], '!' );
		return htmlspecialchars_decode( $keyword );
	}
}

add_action( 'wp_ajax_wpbo_search_responseby_intent',        'qc_wpbo_search_responseby_intent' );
add_action( 'wp_ajax_nopriv_wpbo_search_responseby_intent', 'qc_wpbo_search_responseby_intent' );



function wpbo_search_site_pagination() {
	global $wpdb;

	$keyword           = sanitize_text_field( $_POST['keyword'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post_type         = sanitize_text_field( $_POST['type'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$page              = sanitize_text_field( $_POST['page'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$enable_post_types = get_option( 'wppt_post_types' );
	$load_more         = maybe_unserialize( get_option( 'qlcd_wp_chatbot_load_more' ) );

	if ( is_array( $load_more ) && isset( $load_more[ get_locale() ] ) ) {
		$load_more = $load_more[ get_locale() ];
	}
	if ( is_array( $load_more ) ) {
		$load_more = $load_more[ array_rand( $load_more ) ];
	}
	$searchlimit = ( get_option( 'wppt_number_of_result' ) == '' ? '5' : get_option( 'wppt_number_of_result' ) );
	$orderby     = ( get_option( 'wppt_result_orderby' ) == '' ? 'none' : get_option( 'wppt_result_orderby' ) );
	$order       = ( get_option( 'wppt_result_order' ) == '' ? 'ASC' : get_option( 'wppt_result_order' ) );
	$thumb       = ( get_option( 'wpbot_search_image_size' ) ? get_option( 'wpbot_search_image_size' ) : 'thumbnail' );
	// order by setup
	$new_window = get_option( 'wpbot_search_result_new_window' );

	$total_items = get_option( 'wppt_number_of_result' );

	$searchkeyword = qcld_wpbot_modified_keyword( $keyword );

	$response           = array();
	$response['status'] = 'fail';
	$response['html']   = '';

	// $sql = "SELECT * FROM ". $wpdb->prefix."posts where post_type in ('".$post_type."') and post_status='publish' and ((post_title REGEXP '\\b".$searchkeyword."\\b'))";
	if ( get_option( 'active_advance_query' ) != '1' ) {
		$sql   = 'SELECT * FROM ' . $wpdb->prefix . "posts where post_type in ('" . $post_type . "') and post_status='publish' and ((post_title LIKE '%" . $searchkeyword . "%')) order by ID DESC";
		$limit = ' Limit 0, ' . $searchlimit;
	} else {
		// advance query building
		$sql   = 'SELECT * FROM ' . $wpdb->prefix . "posts where post_type in ('" . $post_type . "') and post_status='publish' and ((post_title REGEXP '\\b" . $searchkeyword . "\\b') or (post_content REGEXP '\\b" . $searchkeyword . "\\b')) order by ID DESC";
		$limit = ' Limit 0, ' . $searchlimit;
	}
	$total_results = $wpdb->get_results( $sql );

	if ( ! empty( $total_results ) ) {

		if ( $orderby == 'title' ) {
			$orderby = 'post_title';
		}
		if ( $orderby == 'date' ) {
			$orderby = 'post_date';
		}
		if ( $orderby == 'modified' ) {
			$orderby = 'post_modified';
		}

		if ( $orderby != 'none' or $orderby != 'rand' ) {
			$sql .= " order by $orderby $order";
		}
		$limit = ' Limit ' . ( $total_items * $page ) . ", $total_items";

		$results = $wpdb->get_results( $sql . $limit );
	} else {
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$selected_lan = sanitize_text_field( $_POST['language'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$selected_lan = explode( '_', $selected_lan );
			$sitepress->switch_lang( $selected_lan[0], true );

		}
		$query_arg = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $total_items,
			's'              => stripslashes( $keyword ),
			'paged'          => ( $page + 1 ),
			'orderby'        => $orderby,
		);
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$selected_lan = sanitize_text_field( $_POST['language'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$selected_lan = explode( '_', $selected_lan );
			$sitepress->switch_lang( $selected_lan[0], true );

		}

		$query_arg['suppress_filters'] = true;
		if ( $orderby != 'none' or $orderby != 'rand' ) {
			$query_arg['order'] = $order;
		}

		$totalresults = new WP_Query(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				's'           => stripslashes( $keyword ),

			)
		);
		$resultss      = new WP_Query( $query_arg );
		$total_results = $totalresults->posts;
		$resultss      = new WP_Query( $query_arg );
		$results       = $resultss->posts;
	}





	if ( ! empty( $total_results ) ) {

		$selected_lan     = sanitize_text_field( $_POST['language'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$urlss            = get_option( 'wpbotml_url_urls' ) ? get_option( 'wpbotml_url_urls' ) : '';
		$imagesize        = ( get_option( 'wpbot_search_image_size' ) != '' ? get_option( 'wpbot_search_image_size' ) : 'thumbnail' );


		$response['html'] .= '<div class="wpb-search-result">';

				foreach ( $total_results as $result ) {

					if ( $result->post_type == 'product' ) {
						if ( ! class_exists( 'WooCommerce' ) ) {
							continue;
						}
					}

					$featured_img_url = get_the_post_thumbnail_url( $result->ID, $thumb );
					$excerpt = '';
					if ( isset( $result->ID ) ) {
						$post_obj = get_post( $result->ID );
						if ( $post_obj ) {
							if ( has_excerpt( $result->ID ) ) {
								$excerpt = get_the_excerpt( $result->ID );
							} else {
								$excerpt = wp_trim_words( strip_shortcodes( $post_obj->post_content ), 20, '...' );
							}
						}
					}
					
					
					$response['html'] .= '<div class="wpbot_card_wraper">';
					$response['html'] .= '<div class="wpbot_card_image ' . ( $result->post_type == 'product' ? 'wp-chatbot-product' : '' ) . ' ' . ( $featured_img_url == '' ? 'wpbot_card_image_saas' : '' ) . '"><a href="' . esc_url( get_permalink( $result->ID ) ) . '" ' . ( $new_window == 1 ? 'target="_blank"' : '' ) . ' ' . ( $result->post_type == 'product' ? 'wp-chatbot-pid="' . $result->ID . '"' : '' ) . '>';
					if ( $featured_img_url != '' ) {
						$response['html'] .= '<img src="' . esc_url_raw( $featured_img_url ) . '" />';
					}

					$response['html'] .= '<div class="wpbot_card_caption ' . ( $featured_img_url == '' ? 'wpbot_card_caption_saas' : '' ) . '">';
					$response['html'] .= '<p class="wpbot_card_caption_title"><span style="padding: 0 5px;color: #1d73b4;display: inline-block;margin: 0 5px 0 0;width: 18px;height: 18px;border-radius: 50%;font-size: 20px;line-height: 22px;"> ✓ </span> ' . esc_html( $result->post_title ) . '</p>';
					$response['html'] .= '<p class="wpbot_card_description">' . esc_html( $excerpt ) . '</p>';
					if ( $result->post_type == 'product' ) {
						if ( class_exists( 'WooCommerce' ) ) {
							$product           = wc_get_product( $result->ID );
							$response['html'] .= '<p class="wpbot_product_price">' . get_woocommerce_currency_symbol() . $product->get_price_html() . '</p>';
						}
					}
					$response['html'] .= '</div>';
					$response['html'] .= '</a></div>';
					$response['html'] .= '</div>';
					
				}
				
		
		$response['html']  .= '</div>';
		$response['status'] = 'success';
		// if ( count( $total_results ) > ( $total_items * ( $page + 1 ) ) ) {

		// 	$response['html'] .= '<button type="button" class="wp-chatbot-loadmore" data-post_type="' . $result->post_type . '" data-keyword="' . $keyword . '" data-page="' . ( $page + 1 ) . '">' . 'Load More' . '<span class="wp-chatbot-loadmore-loader"></span></button>';
		// }
	}
		wp_reset_query();

	if ( $response['status'] != 'success' ) {
		$texts            = maybe_unserialize( get_option( 'qlcd_wp_chatbot_no_result' ) );
		$selected_lan     = sanitize_text_field( $_POST['language'] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$texts            = str_replace( "\'", "'", $texts[ $selected_lan ][0] );
		$response['html'] = array( $texts );

	}
	echo json_encode( $response );
	die();
}

add_action( 'wp_ajax_wpbo_search_site_pagination', 'wpbo_search_site_pagination' );
add_action( 'wp_ajax_nopriv_wpbo_search_site_pagination', 'wpbo_search_site_pagination' );
function qc_wpbo_search_responseby_intent(){

	global $wpdb;

	$keyword 	= isset( $_POST['keyword'] )    ? sanitize_text_field($_POST['keyword']) : '';

	$table 		= $wpdb->prefix.'wpbot_response';

	$result = $wpdb->get_row( $wpdb->prepare("SELECT `response` FROM `$table` WHERE 1 and `intent` = '%s'", $keyword) ); //DB Call OK, No Caching OK
	
	$response = array('status'=>'fail');
	
	if(!empty($result)){

		$response['status'] = 'success';
		$response['html'] = $result->response;

	}

	echo json_encode($response);

	die();

}

add_action( 'wp_ajax_wpbo_search_response_catlist',        'wpbo_search_response_catlist' );
add_action( 'wp_ajax_nopriv_wpbo_search_response_catlist', 'wpbo_search_response_catlist' );

function wpbo_search_response_catlist(){
	global $wpdb;
	$table 		= $wpdb->prefix.'wpbot_response_category';
	$status 	= array('status'=>'fail');
	$results 	= $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table`")); //DB Call OK, No Caching OK
	$response_result = array();
	
	if(!empty($results)){
		foreach($results as $result){
			
			$response_result[] = array('name'=>$result->name);
			
		}
	}
	
	if(!empty($response_result)){

		$status = array('status'=>'success', 'data'=>$response_result);
		

	}
	
	echo json_encode($status);

	die();
	
}

add_action( 'wp_ajax_wpbo_search_response',        'qc_wpbo_search_response' );
add_action( 'wp_ajax_nopriv_wpbo_search_response', 'qc_wpbo_search_response' );



function qc_wpbo_search_response(){
	global $wpdb;
	$keyword 	= isset( $_POST['keyword'] )    ? (sanitize_text_field($_POST['keyword'])) : '';
	$strid 		= isset( $_POST['strid'] )    	? (sanitize_text_field($_POST['strid'])) : '';
	$table 		= $wpdb->prefix.'wpbot_response';
	

	$response_result = array();

	$status = array('status'=>'fail', 'multiple'=>false);
	$field = "ID";
	if(($strid != '') && empty($response_result)){
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i = %d",$table,$field,$strid)); //DB Call OK, No Caching OK
		if(!empty($results)){
			foreach($results as $result){
				
				$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
				
			}
		}
	}
	$field = "query";
	$sql_text = $wpdb->prepare("SELECT `id`, `query`, `response` FROM %i WHERE 1 and %i =  %s", $table, $field,$keyword);
	$results = $wpdb->get_results($sql_text); //DB Call OK, No Caching OK
	
	
	if(!empty($results)){
		foreach($results as $result){
			
			$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
			
		}
	}

	$field = "category";
	if(empty($response_result)){
		$sql = $wpdb->prepare("SELECT `id`, `query`, `response` FROM %i  WHERE 1 and %i = %s", $table,$field, $keyword);
		$results = $wpdb->get_results($sql ); //DB Call OK, No Caching OK
		
		
		if(!empty($results)){
			foreach($results as $result){
				$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
			}
			if(count($response_result)>1){
				$status = array('status'=>'success','category'=> true, 'multiple'=>true, 'data'=>$response_result);
			}else{
				$status = array('status'=>'success', 'category'=> true, 'multiple'=>false, 'data'=>$response_result);
			}
			
			echo json_encode($status);

			die();
		}
		
	}
	
	if(class_exists('Qcld_str_pro')){
		if(get_option('qc_bot_str_remove_stopwords') && get_option('qc_bot_str_remove_stopwords')==1){
			$keyword = qc_strpro_remove_stopwords($keyword);
		}
	}
	
	
	if(empty($response_result)){

		$fields = get_option('qc_bot_str_fields');

		if($fields && !empty($fields)){
			$qfields = implode(', ', $fields);
		}else{
			$qfields = '`query`,`keyword`,`response`';
		}

		$sql = "ALTER TABLE `{$table}` ADD FULLTEXT($qfields);";

		$wpdb->query( $sql ); //DB Call OK, No Caching OK
		
		$sql_text = $wpdb->prepare("SELECT `id`, `query`, `response`, MATCH($qfields) AGAINST(%s IN NATURAL LANGUAGE MODE) as score FROM %i WHERE MATCH($qfields) AGAINST(%s IN NATURAL LANGUAGE MODE) order by score desc limit 15",$keyword,$table,$keyword);

		$results = $wpdb->get_results($sql_text); //DB Call OK, No Caching OK
		
		$weight = get_option('qc_bot_str_weight')!=''?get_option('qc_bot_str_weight'):'0.4';
		
		if(!empty($results)){
			$max_score = max(array_column($results, 'score'));
			if ($max_score <= 0) {
				$max_score = 1; // Set to 1 to avoid division by zero
			}
			foreach($results as $result){
				if(($result->score/$max_score) >= $weight){
					$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>$result->score);
				}
			}
		}
	}
	$field = "keyword";
	if( empty( $response_result ) ){
		
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i REGEXP %s", $table,$field,$keyword)); //DB Call OK, No Caching OK
		
		
		if(!empty($results)){
			foreach($results as $result){
				$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
			}
		}
	}
	if(!empty($response_result)){
		
		if(count($response_result)>1){
			$status = array('status'=>'success', 'multiple'=>true, 'data'=>$response_result);
		}else{
			$status = array('status'=>'success', 'multiple'=>false, 'data'=>$response_result);
		}

	}
	if(empty($result->query)){
		$status = array('status'=>'fail', 'multiple'=>false, 'data'=>$response_result);
	}
	if(empty($status['data']) || (isset($status['status']) && $status['status']==='fail')){
		// Check for space before question mark and try again.
		if(preg_match('/ \?$/', $keyword)){
			$keyword2 = preg_replace('/ \?$/', '?', $keyword);
			// Try again with new keyword.
			// Repeat the main search logic with $keyword2.
			$response_result = array();
			$field = "query";
			$sql_text = $wpdb->prepare("SELECT `id`, `query`, `response` FROM %i WHERE 1 and %i =  %s", $table, $field, $keyword2);
			$results = $wpdb->get_results($sql_text);
			if(!empty($results)){
				foreach($results as $result){
					$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
				}
				if(count($response_result)>1){
					$status = array('status'=>'success', 'multiple'=>true, 'data'=>$response_result);
				}else{
					$status = array('status'=>'success', 'multiple'=>false, 'data'=>$response_result);
				}
			}else{
				$status = array('status'=>'fail', 'multiple'=>false, 'data'=>[]);
			}
		}
	}
	if(empty($status['data']) || (isset($status['status']) && $status['status']==='fail')){
		// Try a partial match if still nothing found.
		if(empty($status['data'])) {
			$keyword_like = '%' . preg_replace('/[\\s\\?]+/', '%', $keyword) . '%';
			$sql_text = $wpdb->prepare("SELECT `id`, `query`, `response` FROM $table WHERE `query` LIKE %s", $keyword_like);
			$results = $wpdb->get_results($sql_text);
			$response_result = array();
			if(!empty($results)){
				foreach($results as $result){
					$response_result[] = array('id'=>$result->id,'query'=>$result->query, 'response'=>$result->response, 'score'=>1);
				}
				if(count($response_result)>1){
					$status = array('status'=>'success', 'multiple'=>true, 'data'=>$response_result);
				}else{
					$status = array('status'=>'success', 'multiple'=>false, 'data'=>$response_result);
				}
			} else {
				$status = array('status'=>'fail', 'multiple'=>false, 'data'=>[], 'message'=>'Sorry, I found nothing');
			}
		}
	}
	if(empty($status['data'])){
		$status = array('status'=>'fail', 'multiple'=>false, 'data'=>[], 'message'=>'no result found');
	}
	echo json_encode($status);

	die();

}

function qc_strpro_remove_stopwords($keyword){
	
	if(get_option('qlcd_wp_chatbot_stop_words') && get_option('qlcd_wp_chatbot_stop_words')!=''){
		$commonWords = explode(',', get_option('qlcd_wp_chatbot_stop_words'));
		return preg_replace('/\b('.implode('|',$commonWords).')\b/','',$keyword);
	}else{
		return $keyword;
	}
	
 
	
}