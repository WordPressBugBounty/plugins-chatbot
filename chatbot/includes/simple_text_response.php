<?php 
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
?>

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


<div class="qcld-wp-chatbot-wrap-header">

    <a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
    <p><strong>Core Version:</strong> v<?php echo QCLD_wpCHATBOT_VERSION; ?></p>
    <ul class="qcld-wp-chatbot-wrap-version-wrapper">
        <li>
     <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a> 
      
      </li>
	  </ul>
</div>





<div class="TextResponsesoutside">

		<div class="qcwrap TextResponses">

			<div class="wp-chatbot-wrap">
			<div class="wpbot_dashboard_header "><h1><?php echo ($hasEdit?'Edit':'Add') ?> Response</h1></div>
			<form method="post" action="">
			<div class="wpbot_addons_section ">
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
							<input type="text" name="qc_bot_str_query" value="<?php echo esc_attr($hasEdit?$data->query:''); ?>" style="width: 100%;" required />
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
							<input type="text" name="qc_bot_str_keyword" value="<?php echo esc_attr($hasEdit?$data->keyword:''); ?>" style="width: 100%;" />
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



<div class="qcld-wp-chatbot-wrap-header">

    <a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
    <p><strong>Core Version:</strong> v<?php echo QCLD_wpCHATBOT_VERSION; ?></p>
    <ul class="qcld-wp-chatbot-wrap-version-wrapper">
        <li>
     <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a> 
      
      </li>
	  </ul>
</div>

<div class="qcld-wp-chatbot-wrap-header_inn">
<div class="qcld-wp-chatbot-wrap-header_inn_heading">
		<h1 class="wp-heading-inline"><?php esc_html_e('Simple Text Responses', 'wpbot'); ?></h1>
		 <a href="<?php echo esc_url( add_query_arg( 'action', 'edit', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Add New', 'wpbot'); ?></a>
	</div>	
		<?php if(class_exists('Qcld_str_pro')): ?>
		
		<a href="<?php echo esc_url( add_query_arg( 'action', 'manage-categories', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Manage Categories', 'wpbot'); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=qc_str_export' ) ); ?>" class="button page-title-action"><?php esc_html_e('Export', 'wpbot'); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'import', admin_url('admin.php?page=simple-text-response') ) ); ?>" class="button page-title-action"><?php esc_html_e('Import', 'wpbot'); ?></a>
	</div>	


	<?php endif; ?>




	

	<div class="TextResponsesouter">
	
    <div class="wrap TextResponses">
	


	<div class="wrapTexttop">
	
    <p><?php esc_html_e('Create simple text responses and the ChatBot will use advanced search algorithm to answer user questions. This is a simpler alternative to OpenAI or DialogFlow.', 'wpbot'); ?><br>
	<b><?php esc_html_e('NB: Simple Text Responses require mySQL Client version 5.6+', 'wpbot'); ?></b></p>
	</div>
    <form method="post" action="">
		<table class="form-table-str form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Phrase matching accuracy', 'wpbot'); ?></th>
					<td>
   						<input name="str_nonce" type="hidden" value="<?php echo sanitize_key( wp_create_nonce('str-nonce') ); ?>" />
						<input type="text" name="qc_bot_str_weight" value="<?php  esc_attr_e((get_option('qc_bot_str_weight')!=''?get_option('qc_bot_str_weight'):'0.4')); ?>" style="width: 100%;" required />
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
					<th scope="row"><?php esc_html_e('Re-Index STR', 'wpbot'); ?></th>
					<td>
						<input name="str_nonce" type="hidden" value="<?php echo sanitize_key( wp_create_nonce('str-nonce') ); ?>" />
						<input type="submit" class="button button-primary" name="qc-re-index" id="re-index" value="Re Index">
						
						<i><?php esc_html_e('Re-Index if results are not as expected.', 'wpbot'); ?></i>
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


	<style>
body {
    background-color: #F5F7FD;
    font-family: "DM Sans", sans-serif !important;
	font-optical-sizing: auto;
	font-size: 16px;
	font-weight: normal;
}
.qcld-wp-chatbot-wrap-header {
    display: flex;
    column-gap: 15px;
    row-gap: 10px;
    background: #fff;
    padding: 15px 15px;
    margin: 20px 20px 20px 0;
    border-radius: 16px;
    align-items: center;
    justify-content: space-between;
		width: 97%;
}

.qcld-wp-chatbot-wrap-header img{
  max-width: 50px;
}

.qcld-wp-chatbot-wrap-header ul, .qcld-wp-chatbot-wrap-header p, .qcld-wp-chatbot-wrap-header li{
  margin: 0;
}
.qcld-wp-chatbot-wrap-header a.wpchatbot-Upgrade {
    padding: 12px 25px;
    background: #ffffff;
    color: #5B4E96;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
}

.qcld-wp-chatbot-wrap-header a.wpchatbot-Upgrade:hover {
    padding: 12px 25px;
    background: #5B4E96;
    color: #fff;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
    text-decoration: none;
}
a.qcld-wp-chatbot-wrap-site__logo {
    font-size: 22px;
    color: #000;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    margin: 0;
    padding: 0;
}

a{
 text-decoration: none;
}
.qcld-wp-chatbot-wrap-header_inn {
    background: #fff;
    border-radius: 16px;
    margin: 0 20px 0 0;
    padding: 20px;
}
.TextResponsesoutside {
	    background: #fff;
    border-radius: 16px;
    margin: 0 20px 0 0;
    padding: 20px;
}


.qcld-wp-chatbot-wrap-header_inn h1.wp-heading-inline {
    border-bottom: 1px solid #eee;
    padding: 0 0 15px 0;
    margin: 20px 0 12px 0;
    font-weight: bold;
    font-size: 22px !important;
}

.TextResponsesoutside h1 {
    border-bottom: 1px solid #eee;
    padding: 0 0 8px 0;
    margin: 20px 0 12px 0;
    font-weight: bold;
    font-size: 22px !important;
}
.TextResponsesoutside ul li {
    font-size: 14px;
}
.wpbot_single_addon_wrapper2 p {
    font-size: 16px;
}
.TextResponsesouter i {
    font-weight: normal;
    font-size: 12px;
    color: red;
}
.TextResponsesoutside  i {
    font-weight: normal;
    font-size: 12px;
    color: red;
}

table.form-table-str.form-table tr {
    background: #fff;
    padding: 2px 10px !important;
    border-radius: 6px;
    margin: 0 0 8px 0;
    border: 5px solid #fff;
    vertical-align: middle;
	    border: 1px solid #eee;
}
table.form-table-str.form-table {
    border: 1px solid #eee;
}
table.form-table-str.form-table tr {
    border: none;
}
table.form-table-str.form-table  th {
    vertical-align: top;
    text-align: center;
    padding: 20px 10px 20px 0;
    width: 200px;
    line-height: 1.3;
    font-weight: 600;
    vertical-align: middle;
}

.TextResponsesoutside footer.wp-chatbot-admin-footer input#submit {
    padding: 12px 20px;
    width: 100%;
    font-size: 16px;
    font-weight: 500;
    color: #988FBD;
    border-radius: 8px;
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
    background: #5B4E96;
    color: #ffffff;
    text-decoration: none;
    border: none;
    max-width: 220px;
    margin: 12px 0 0 0;
	    line-height: 24px;
}
.qcld-wp-chatbot-wrap-header_inn a.button.page-title-action {
    padding: 3px 25px;
    background: #ffffff;
    color: #5B4E96;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
}

.qcld-wp-chatbot-wrap-header_inn a.button.page-title-action:hover {
	padding: 0px 15px;
    background: #5B4E96;
    color: #fff;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
    position: absolute;
    top: 36px;
    left: 280px;
}
.qcld-wp-chatbot-wrap-header_inn a.button.page-title-action {
	padding: 0px 15px;
    background: #ffffff;
    color: #5B4E96;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
    position: absolute;
    top: 36px;
    left: 280px;
}

.qcld-wp-chatbot-wrap-header_inn {
    position: relative;
}
.notice.is-dismissible.qcbot-str-top-notic {
    width: 96%;
}

.TextResponsesouter i {
    position: relative;
    top: 6px;
}

table.form-table-str.form-table th {
    text-align: left !important;
    padding: 20px 10px 20px 20px !important;
}

.qc-review-text img {
    width: 100%;
}

.TextResponsesouter input#submit {
    padding: 0px 15px;
    background: #ffffff;
    color: #5B4E96;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
}
.TextResponsesouter input#re-index {
    padding: 0px 15px;
    background: #ffffff;
    color: #5B4E96;
    border: 2px solid #5B4E96;
    border-radius: 6px;
    font-weight: bold;
}

	</style>