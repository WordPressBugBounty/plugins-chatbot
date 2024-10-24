<?php 
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
?>
<style>
.wpbot_dashboard_header {
    margin-right: unset !important;
    margin-left: unset !important;
    background: #32373c;
    color: #fff;
    text-align: center;
    border-radius: 5px 5px 0px 0px;
}
.wpbot_addons_section {
    margin-right: unset !important;
    margin-left: unset !important;
    margin-bottom: 10px;
    background: #32373c;
    padding-bottom: 20px;
    border-radius: 0px 0px 5px 5px;
}
.wpbot_single_addon_wrapper2 {
    background: #32373c !important;
	color: #fff !important;
    padding: 20px;
}
.wpbot_single_addon_wrapper2 .form-table th,.wpbot_single_addon_wrapper2 .form-wrap label{
	color: #fff !important;
}
.wp-chatbot-admin-header, .wp-chatbot-admin-footer {
    padding: 25px;
}
.wpbot_dashboard_header h1 {
    font-size: 30px;
    line-height: 100px;
    margin: 0px;
    color: #fff;
}
.container {
    width: 1170px;
    padding-right: 15px;
    padding-left: 15px;
}
.wrapTexttop {
    background: #464b50;
    padding: 8px 20px;
}
.form-table-str{
	border: 0 !important; 
}
.form-table{
	border: 0 !important; 
}
.form-table-str.form-table  tr:nth-child(odd){
	background: #32373c !important;
}
.form-table tr:nth-child(odd) {
  background: #32373c !important;
}




/* 10-16-2024 - CSS Updated*/

.TextResponsesouter .wrap.TextResponses{
  position: relative;
  max-width: 1170px;
}
.TextResponsesouter .wrap.TextResponses:before {
  content: '';
  position: absolute;
  background: url("<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-left-dark.png' );?>") top left;
  left: -6px;
  width: 40px;
  height: 100%;
  z-index: 999;
  top: 0;
  background-repeat: repeat !important;
}
.TextResponsesouter .wrap.TextResponses:after {
  content: '';
  position: absolute;
  background: url("<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-righ-dark.png' );?>") top right;
  
  width: 41px;
  height: 100%;
  right: -22px;
  width: 28px;
  z-index: 999;
  top: 0;
  background-repeat: repeat !important;
}
.TextResponsesouter {
  position: relative;
  max-width: 1190px;
}
.TextResponsesouter:before {
  content: '';
  position: absolute;
  background: url(<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-top-dark_new.png' );?>) top center;
   left: 2px; 
  width: 100%;
  height: 34px;
  z-index: 9999999;
  top: -7px;
  background-repeat: no-repeat;
  background-size: cover;
}
.card-header .wpbot_dashboard_header {
  background: #222222;
}
.card-header .wpbot_addons_section {
  background: #222222;
}

path{stroke-dasharray:300;stroke-dashoffset:300;stroke:#c3a477;stroke-width:2px;-webkit-animation:draw ease-out infinite;animation:draw ease-out infinite}
#l1{-webkit-animation-duration:3s;animation-duration:3s}
#l2{-webkit-animation-duration:5.5s;animation-duration:5.5s}
#l3{-webkit-animation-duration:2s;animation-duration:2s}
#l4{-webkit-animation-duration:5.5s;animation-duration:5.5s}@-webkit-keyframes draw{50%{stroke-dashoffset:0}100%{stroke-dashoffset:-300}}@keyframes draw{50%{stroke-dashoffset:0}100%{stroke-dashoffset:-300}}
.lineanimation{position:absolute;top:-64px;z-index:99999999;left:-10px;right:0;margin:0 auto;width:257px;max-width:257px;height:90px;overflow:hidden;bottom:0}
.lineanimation svg{width:250px;height:95px}.lineanimation svg{width:250px;height:150px;overflow:hidden}.lineanimation path{stroke:#5e5e5e}


.TextResponsesouter .TextResponses {
    background: #222222;
}

.TextResponsesouter {
    max-width: 1230px;
}
.TextResponsesouter .form-table-str.form-table tr:nth-child(odd) {
    background: #222222 !important;
}

.wrapTexttop {
    margin: 20px 0 0 0;
}
.form-table-str.form-table tr:nth-child(odd) {
    background: #32373c !important;
}

.wp-chatbot-wrap .form-table td {
    color: #fff;
}









/* 10-23-2024 - CSS Updated*/

.qcwrap.TextResponses{
  position: relative;
  max-width: 1170px;
}
.qcwrap.TextResponses:before {
  content: '';
  position: absolute;
  background: url("<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-left-dark.png' );?>") top left;
  left: -6px;
  width: 40px;
  height: 100%;
  z-index: 999;
  top: 0;
  background-repeat: repeat !important;
}
.qcwrap.TextResponses:after {
  content: '';
  position: absolute;
  background: url("<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-righ-dark.png' );?>") top right;
  
  width: 41px;
  height: 100%;
  right: -22px;
  width: 28px;
  z-index: 999;
  top: 0;
  background-repeat: repeat !important;
}
.TextResponsesoutside {
  position: relative;
  max-width: 1190px;
}
.TextResponsesoutside:before {
  content: '';
  position: absolute;
  background: url(<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/hud-top-dark_new.png' );?>) top center;
   left: 2px; 
  width: 100%;
  height: 34px;
  z-index: 9999999;
  top: -7px;
  background-repeat: no-repeat;
  background-size: cover;
}
.card-header .wpbot_dashboard_header {
  background: #222222;
}
.card-header .wpbot_addons_section {
  background: #222222;
}

path{stroke-dasharray:300;stroke-dashoffset:300;stroke:#c3a477;stroke-width:2px;-webkit-animation:draw ease-out infinite;animation:draw ease-out infinite}
#l1{-webkit-animation-duration:3s;animation-duration:3s}
#l2{-webkit-animation-duration:5.5s;animation-duration:5.5s}
#l3{-webkit-animation-duration:2s;animation-duration:2s}
#l4{-webkit-animation-duration:5.5s;animation-duration:5.5s}@-webkit-keyframes draw{50%{stroke-dashoffset:0}100%{stroke-dashoffset:-300}}@keyframes draw{50%{stroke-dashoffset:0}100%{stroke-dashoffset:-300}}
.lineanimation{position:absolute;top:-64px;z-index:99999999;left:-10px;right:0;margin:0 auto;width:257px;max-width:257px;height:90px;overflow:hidden;bottom:0}
.lineanimation svg{width:250px;height:95px}.lineanimation svg{width:250px;height:150px;overflow:hidden}.lineanimation path{stroke:#5e5e5e}



.TextResponsesoutside .wpbot_dashboard_header {
    background: #222222;
}
.TextResponsesoutside .wpbot_addons_section {
    background: #222222;
}
.TextResponsesoutside .wpbot_single_addon_wrapper2 {
    background: #222222 !important;
}
.TextResponsesoutside .form-table-str.form-table tr:nth-child(odd) {
    background: #222222 !important;
}

.TextResponsesoutside .TextResponses {
    background: #222222;
}
</style>


    <?php 
	if(isset($_GET['opt']) && $_GET['opt']=='add'): 
		
		do_action('qcld_str_add_category');
		
	elseif(isset($_GET['opt']) && $_GET['opt']=='edit'): 
		
		do_action('qcld_str_edit_category');
	
	elseif(isset($_GET['page']) && $_GET['page']=='simple-text-response' && isset($_GET['action']) && $_GET['action']=='import'): 
	
		do_action('qcld_str_import');
	
	elseif(isset($_GET['action']) && $_GET['action']=='manage-categories'):
	
		include_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH.'/includes/templates/manage-categories.php');
		
	elseif(isset($_GET['action']) && $_GET['action']=='edit'): 
	
	if(class_exists('Qcld_str_pro')):
		do_action('qcld_str_pro_stredit');
	else:
	
		$hasEdit = false;
		if(isset($_GET['query']) && $_GET['query']!=''){
			$hasEdit = true;
			$id = sanitize_text_field($_GET['query']);
			$table = $wpdb->prefix.'wpbot_response';
			$data = $wpdb->get_row($wpdb->prepare("select * from $table where id = %d", $id)); //DB Call OK, No Caching OK
			
		}
		

		?>



<div class="TextResponsesoutside">

<div class="lineanimation">
<svg width="350" height="350" viewBox="0 0 308 309" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<defs>
<circle id="a" cx="150" cy="150" r="150"></circle>
<linearGradient x1="50%" y1="0%" x2="50%" y2="62.304%" id="c">
<stop stop-color="#09DFF3" offset="0%"></stop>
<stop stop-color="#44BEFF" offset="100%"></stop>
</linearGradient>
</defs>
<g>
<path id="l1" d="M0 130 L300 130"></path>
<path id="l2" d="M0 150 L300 150"></path>
<path id="l3" d="M0 170 L300 170"></path>
<path id="l4" d="M0 190 L300 190"></path>
</g>
</svg></div>


		<div class="qcwrap TextResponses">

			<div class="wp-chatbot-wrap">
			<div class="wpbot_dashboard_header container"><h1><?php echo ($hasEdit?'Edit':'Add') ?> Response</h1></div>
			<form method="post" action="">
			<div class="wpbot_addons_section container">
			<div class="wpbot_single_addon_wrapper2">
			<p><b><?php esc_html_e('Please note the following ', 'wpbot'); ?></b></p>
			<ul>
				<li> <?php esc_html_e('1. The Query, Response and Keywords fields will be searched. Add Keywords for best matches', 'wpbot'); ?></li>
				<li> <?php esc_html_e('2. Please clear browser Cache and Cookies both before testing new responses', 'wpbot'); ?></li>
			</ul>
			<table class="form-table-str form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php esc_html_e('Query', 'wpbot'); ?></th>
						<td>
							<input name="str_nonce" type="hidden" value="<?php echo sanitize_key( wp_create_nonce('str-nonce') ); ?>" />
							<input type="text" name="qc_bot_str_query" value="<?php echo esc_attr($hasEdit?$data->query:''); ?>" style="width: 400px;" required />
							<br><i><?php esc_html_e('*Required. Add the query here. ', 'wpbot'); ?></i>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"> <?php esc_html_e('Response', 'wpbot'); ?></th>
						<td>	
						<?php 
							
							$content   = ($hasEdit?$data->response:'');
							$editor_id = 'qc_bot_str_response';
							$settings  = array( );
							 
							wp_editor( $content, $editor_id, $settings );
							
							?>
							<br><i><?php esc_html_e('*Required. Add the response here. ', 'wpbot'); ?></i>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Keyword</th>
						<td>
							<input type="text" name="qc_bot_str_keyword" value="<?php echo esc_attr($hasEdit?$data->keyword:''); ?>" style="width: 400px;" />
							<br><i> <?php esc_html_e('Optional. Add multiple keyword or phrases as comma(,) seperated value. It will help to find the best match result.', 'wpbot'); ?></i>
						</td>
					</tr>
					
					
					<?php if($hasEdit): ?>

					<input type="hidden" name="qc_bot_str_id" value="<?php echo esc_attr($data->id); ?>" />

					<?php endif; ?>
					
					
				</tbody>
			</table>
			</div>
			
			<footer class="wp-chatbot-admin-footer">
				<div class="row">
					<div class="text-left col-sm-3 col-sm-offset-3">
						
					</div>
					<div class="text-right col-sm-6">
						<input type="submit" class="button button-primary" name="submit" id="submit" value="Save Settings">
					</div>
				</div>
				<!--                    row-->
			</footer>


			</div></form>
			</div>
		</div>
		</div>
		<?php endif; ?>
    <?php else: ?>
		<h1 class="wp-heading-inline"><?php esc_html_e('Simple Text Responses', 'wpbot'); ?></h1>
		 <a href="<?php echo esc_url( add_query_arg( 'action', 'edit', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Add New', 'wpbot'); ?></a>
	
		<?php if(class_exists('Qcld_str_pro')): ?>
		
		<a href="<?php echo esc_url( add_query_arg( 'action', 'manage-categories', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Manage Categories', 'wpbot'); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=qc_str_export' ) ); ?>" class="button page-title-action"><?php esc_html_e('Export', 'wpbot'); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'import', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Import', 'wpbot'); ?></a>
		
	<?php endif; ?>

	<div class="TextResponsesouter">


	<div class="lineanimation">
<svg width="350" height="350" viewBox="0 0 308 309" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<defs>
<circle id="a" cx="150" cy="150" r="150"></circle>
<linearGradient x1="50%" y1="0%" x2="50%" y2="62.304%" id="c">
<stop stop-color="#09DFF3" offset="0%"></stop>
<stop stop-color="#44BEFF" offset="100%"></stop>
</linearGradient>
</defs>
<g>
<path id="l1" d="M0 130 L300 130"></path>
<path id="l2" d="M0 150 L300 150"></path>
<path id="l3" d="M0 170 L300 170"></path>
<path id="l4" d="M0 190 L300 190"></path>
</g>
</svg></div>

	
    <div class="wrap TextResponses">
	


	<div class="wrapTexttop">
	
    <p><?php esc_html_e('Create simple text responses and the ChatBot will use advanced search algorithm to answer user questions. This is a simpler alternative to OpenAI or DialogFlow.', 'wpbot'); ?></p>
	<p><b><?php esc_html_e('NB: Simple Text Responses require mySQL Client version 5.6+', 'wpbot'); ?></b></p>
	</div>
    <form method="post" action="">
		<table class="form-table-str form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Phrase matching accuracy', 'wpbot'); ?></th>
					<td>
   						<input name="str_nonce" type="hidden" value="<?php echo sanitize_key( wp_create_nonce('str-nonce') ); ?>" />
						<input type="text" name="qc_bot_str_weight" value="<?php  esc_attr_e((get_option('qc_bot_str_weight')!=''?get_option('qc_bot_str_weight'):'0.4')); ?>" style="width: 400px;" required />
						<br><i><?php esc_html_e('Please enter a value between 0 to 1. Higher value means more exact matching of phrases.', 'wpbot'); ?></i>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Search Fields', 'wpbot'); ?></th>
					<td>
						<?php 
							$fields = get_option('qc_bot_str_fields');
						?>
					
						<label for="qc_bot_str_field_title"><?php esc_html_e('Title', 'wpbot'); ?></label>
						<input id="qc_bot_str_field_title" type="checkbox" name="qc_bot_str_fields[]" value="query" <?php echo (!$fields?'checked="checked"':''); ?> <?php echo ($fields && in_array('query', $fields)?'checked="checked"':''); ?> />
						
						<label for="qc_bot_str_field_keyword"><?php esc_html_e('Keyword', 'wpbot'); ?></label>
						<input id="qc_bot_str_field_keyword" type="checkbox" name="qc_bot_str_fields[]" value="keyword" <?php echo (!$fields?'checked="checked"':''); ?> <?php echo ($fields && in_array('keyword', $fields)?'checked="checked"':''); ?> />
						
						<label for="qc_bot_str_field_response"><?php esc_html_e('Response', 'wpbot'); ?></label>
						<input id="qc_bot_str_field_response" type="checkbox" name="qc_bot_str_fields[]" value="response" <?php echo (!$fields?'checked="checked"':''); ?> <?php echo ($fields && in_array('response', $fields)?'checked="checked"':''); ?> />
						<br><i><?php esc_html_e('Please check/uncheck to allow/disallow searching in that fields', 'wpbot'); ?></i>
					</td>
				</tr>
			
				<tr valign="top">
					<th scope="row"></th>
					<td>
						<input type="submit" class="button button-primary" name="submit" id="submit" value="Save Settings">
					</td>
				</tr>
			</tbody>
		</table>
    </form>
	
	<form method="post" action="">
		<table class="form-table-str form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Re-Index STR Simple Text Responses', 'wpbot'); ?></th>
					<td>
						<input name="str_nonce" type="hidden" value="<?php echo sanitize_key( wp_create_nonce('str-nonce') ); ?>" />
						<input type="submit" class="button button-primary" name="qc-re-index" id="re-index" value="Re Index">
						<br/>
						<i><?php esc_html_e('Re-Indexing may required after migration.', 'wpbot'); ?></i>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
    <div id="poststuff">
		<div id="post-body" class="metabox-holder">
			<div id="post-body-content-edit" class="str-form">
				<div class="meta-box-sortables ui-sortable">
					<form method="post">
						
						<a href="<?php echo esc_url( add_query_arg( 'action', 'edit', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action str-addnew"><?php esc_html_e('Add New', 'wpbot'); ?></a>
                        <?php
                        $this->response_list->prepare_items();
                        $this->response_list->display(); ?>
                    </form>
                </div>
            </div>
        </div>
      
    </div>
    </div>
	</div>
    <?php endif; ?>
<script type="text/javascript">
	// var stopcheckBox = document.getElementById("qc_bot_str_remove_stopwords");
	// stopcheckBox.addEventListener('change', function () {
	// 	if (stopcheckBox.checked == true){
	// 		stopcheckBox.value = "1";
			
	// 	} else {
	// 		stopcheckBox.value = "0";
	// 	}
    // });

	// var small_talk = document.getElementById('small_talk');
	// small_talk.addEventListener( 'click', () => { small_talk_import(); } );
	// function small_talk_import(){
	// 	var req = new XMLHttpRequest();
	// 	req.open('POST', ajaxurl, true);
	// 	req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
	// 	var s = 'action=small_talk_import';
	// 	req.send(s);
	// 	if(req.DONE == 4){
	// 		alert('Smalltalk is imported');
	// 		setTimeout(function(){
	// 			window.location.reload();
	// 		}, 3000);
	// 	}
	// }
	
</script>


<style>


.TextResponses{
  background: #32373c;
  padding: 20px 20px;
  border-radius: 6px;
  width: 100%;
  max-width: 1220px;
}
.TextResponses p{
	color:#fff;
}
.TextResponses i{
	color:#fff;
}
.TextResponses h1{
	color:#fff;
}
.TextResponses .form-table th, .TextResponses .form-wrap label {
	color:#fff;
}
.qcwrap {
    margin: 20px 0 0 0;
}
.TextResponses label {
    color: #fff;
}
.tablenav-pages .current-page {
    display: inline-block;
    vertical-align: baseline;
    min-width: 30px;
    min-height: 31px;
    margin: 0 0 0 0;
    padding: 0 4px;
    font-size: 13px;
    line-height: 1.5;
    text-align: center;
}
.tablenav-pages .current-page {
	border: 1px solid
}
div#poststuff {
    color: #fff;
}
.str-form{
	position: relative;
}
.str-addnew{
	position: absolute !important;
    left: 50%;
}
.qcbot-str-top-notic {
  max-width: 1240px;
  padding: 0 !important;
  margin: 20px 0 0 0;
}

</style>