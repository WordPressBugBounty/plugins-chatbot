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
	
	elseif(isset($_GET['action']) && wp_unslash($_GET['action'])=='manage-categories'):
	
		include_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH.'/includes/templates/manage-categories.php');
		
	elseif(isset($_GET['action']) && wp_unslash($_GET['action'])=='edit'): 
	
	if(class_exists('Qcld_str_pro')):
		do_action('qcld_str_pro_stredit');
	else:
	
		$hasEdit = false;
		if(isset($_GET['query']) && wp_unslash($_GET['query'])!=''){
			$hasEdit = true;
			$id = sanitize_text_field(wp_unslash($_GET['query']));
			$table = $wpdb->prefix.'wpbot_response';
			$data = $wpdb->get_row($wpdb->prepare("select * from {$table} where id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
		?>
<div class="wpbot-str-page">
<div class="qcld-wp-chatbot-wrap-header">
    <div class="qcld-wp-chatbot-wrap-header-logo"><a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="<?php esc_attr_e( 'WPBot', 'chatbot' ); ?>"> WPBot Control Panel </a>
    <p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
    </div>
    <ul class="qcld-wp-chatbot-wrap-version-wrapper">
        <li>
     <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a>
      </li>
	  </ul>
</div>

<div class="wpbot-str-main">
	<div class="wpbot-str-hero">
		<div class="wpbot-str-hero-copy">
			<h1><?php echo $hasEdit ? esc_html__( 'Edit Response', 'chatbot' ) : esc_html__( 'Add Response', 'chatbot' ); ?></h1>
			<p><?php esc_html_e( 'Query, response, and keywords are all searched. Add keywords so the best match is easier to find.', 'chatbot' ); ?></p>
		</div>
		<a class="wpbot-str-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-text-response' ) ); ?>"><?php esc_html_e( 'Back to responses', 'chatbot' ); ?></a>
	</div>

	<div class="wpbot-str-card">
		<div class="wpbot-str-callout">
			<p><?php esc_html_e( 'Clear browser cache and cookies before testing a new response.', 'chatbot' ); ?></p>
		</div>
		<form method="post" action="">
			<input name="str_nonce" type="hidden" value="<?php echo esc_attr( sanitize_key( wp_create_nonce( 'str-nonce' ) ) ); ?>" />
			<?php if ( $hasEdit ) : ?>
				<input type="hidden" name="qc_bot_str_id" value="<?php echo esc_attr( $data->id ); ?>" />
			<?php endif; ?>

			<div class="wpbot-str-field">
				<span class="wpbot-str-label"><?php esc_html_e( 'Query', 'chatbot' ); ?></span>
				<div>
					<input class="wpbot-str-input" type="text" name="qc_bot_str_query" value="<?php echo esc_attr( $hasEdit ? $data->query : '' ); ?>" required />
					<p class="wpbot-str-hint"><?php esc_html_e( 'Required. Add the question or phrase a visitor might ask.', 'chatbot' ); ?></p>
				</div>
			</div>

			<div class="wpbot-str-field wpbot-str-field-editor">
				<span class="wpbot-str-label"><?php esc_html_e( 'Response', 'chatbot' ); ?></span>
				<div>
					<?php
					$content   = ( $hasEdit ? $data->response : '' );
					$editor_id = 'qc_bot_str_response';
					wp_editor( $content, $editor_id, array() );
					?>
					<p class="wpbot-str-hint"><?php esc_html_e( 'Required. Add the reply the chatbot should send.', 'chatbot' ); ?></p>
				</div>
			</div>

			<div class="wpbot-str-field">
				<span class="wpbot-str-label"><?php esc_html_e( 'Keyword', 'chatbot' ); ?></span>
				<div>
					<input class="wpbot-str-input" type="text" name="qc_bot_str_keyword" value="<?php echo esc_attr( $hasEdit ? $data->keyword : '' ); ?>" />
					<p class="wpbot-str-hint"><?php esc_html_e( 'Optional. Add multiple keywords or phrases, separated by commas, to improve matching.', 'chatbot' ); ?></p>
				</div>
			</div>

			<div class="wpbot-str-form-actions">
				<a class="wpbot-str-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-text-response' ) ); ?>"><?php esc_html_e( 'Cancel', 'chatbot' ); ?></a>
				<button type="submit" class="wpbot-str-btn wpbot-str-btn-primary" name="submit"><?php esc_html_e( 'Save Response', 'chatbot' ); ?></button>
			</div>
		</form>
	</div>
</div>
</div>
	<?php endif; ?>


    <?php else: ?>

<div class="wpbot-str-page">
<div class="qcld-wp-chatbot-wrap-header">
    <div class="qcld-wp-chatbot-wrap-header-logo"><a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="<?php esc_attr_e( 'WPBot', 'chatbot' ); ?>"> WPBot Control Panel </a>
    <p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
    </div>
    <ul class="qcld-wp-chatbot-wrap-version-wrapper">
        <li>
     <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a>
      </li>
	  </ul>
</div>

<div class="wpbot-str-main">
	<div class="wpbot-str-hero">
		<div class="wpbot-str-hero-copy">
			<h1><?php esc_html_e( 'Simple Text Responses', 'chatbot' ); ?></h1>
			<p><?php esc_html_e( 'Create simple text responses and the ChatBot will use an advanced search algorithm to answer user questions. This is a simpler alternative to OpenAI or DialogFlow.', 'chatbot' ); ?></p>
			<span class="wpbot-str-note"><?php esc_html_e( 'Requires MySQL client version 5.6+', 'chatbot' ); ?></span>
		</div>
		<?php if ( class_exists( 'Qcld_str_pro' ) ) : ?>
		<div class="wpbot-str-hero-actions">
			<a href="<?php echo esc_url( add_query_arg( 'action', 'manage-categories', admin_url( 'admin.php?page=simple-text-response' ) ) ); ?>" class="wpbot-str-btn"><?php esc_html_e( 'Manage Categories', 'chatbot' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=qc_str_export' ) ); ?>" class="wpbot-str-btn"><?php esc_html_e( 'Export', 'chatbot' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'action', 'import', admin_url( 'admin.php?page=simple-text-response' ) ) ); ?>" class="wpbot-str-btn"><?php esc_html_e( 'Import', 'chatbot' ); ?></a>
		</div>
		<?php endif; ?>
	</div>

	<div class="wpbot-str-card">
		<div class="wpbot-str-card-head">
			<div>
				<h2><?php esc_html_e( 'Search settings', 'chatbot' ); ?></h2>
				<p><?php esc_html_e( 'Tune how closely a visitor’s phrase must match, and which fields are searched.', 'chatbot' ); ?></p>
			</div>
		</div>

		<form method="post" action="">
			<input name="str_nonce" type="hidden" value="<?php echo esc_attr( sanitize_key( wp_create_nonce( 'str-nonce' ) ) ); ?>" />

			<div class="wpbot-str-field">
				<span class="wpbot-str-label"><?php esc_html_e( 'Phrase matching accuracy', 'chatbot' ); ?></span>
				<div>
					<input class="wpbot-str-input wpbot-str-input-short" type="text" name="qc_bot_str_weight" value="<?php echo esc_attr( ! empty( get_option( 'qc_bot_str_weight' ) ) ? get_option( 'qc_bot_str_weight' ) : '0.4' ); ?>" required />
					<p class="wpbot-str-hint"><?php esc_html_e( 'Enter a value between 0 and 1. A higher value means phrases must match more exactly.', 'chatbot' ); ?></p>
				</div>
			</div>

			<div class="wpbot-str-field">
				<span class="wpbot-str-label"><?php esc_html_e( 'Search fields', 'chatbot' ); ?></span>
				<div>
					<?php $fields = get_option( 'qc_bot_str_fields' ); ?>
					<div class="wpbot-str-checks">
						<label for="qc_bot_str_field_title">
							<input id="qc_bot_str_field_title" type="checkbox" name="qc_bot_str_fields[]" value="query" <?php echo ( ! $fields ? 'checked="checked"' : '' ); ?> <?php echo ( $fields && in_array( 'query', $fields, true ) ? 'checked="checked"' : '' ); ?> />
							<?php esc_html_e( 'Title', 'chatbot' ); ?>
						</label>
						<label for="qc_bot_str_field_keyword">
							<input id="qc_bot_str_field_keyword" type="checkbox" name="qc_bot_str_fields[]" value="keyword" <?php echo ( ! $fields ? 'checked="checked"' : '' ); ?> <?php echo ( $fields && in_array( 'keyword', $fields, true ) ? 'checked="checked"' : '' ); ?> />
							<?php esc_html_e( 'Keyword', 'chatbot' ); ?>
						</label>
						<label for="qc_bot_str_field_response">
							<input id="qc_bot_str_field_response" type="checkbox" name="qc_bot_str_fields[]" value="response" <?php echo ( ! $fields ? 'checked="checked"' : '' ); ?> <?php echo ( $fields && in_array( 'response', $fields, true ) ? 'checked="checked"' : '' ); ?> />
							<?php esc_html_e( 'Response', 'chatbot' ); ?>
						</label>
					</div>
					<p class="wpbot-str-hint"><?php esc_html_e( 'Check a field to include it in search. Uncheck it to leave it out.', 'chatbot' ); ?></p>
				</div>
			</div>

			<div class="wpbot-str-extra" hidden>
				<?php
				$current_user = wp_get_current_user();
				if ( in_array( 'administrator', $current_user->roles, true ) ) :
				?>
				<div class="wpbot-str-field">
					<span class="wpbot-str-label"><?php esc_html_e( 'Editor access', 'chatbot' ); ?></span>
					<div>
						<div class="wpbot-str-checks">
							<label for="qc_bot_str_allow_author_editor">
								<input id="qc_bot_str_allow_author_editor" type="checkbox" name="qc_bot_str_allow_author_editor" value="1" <?php echo ( get_option( 'qc_bot_str_allow_author_editor' ) && get_option( 'qc_bot_str_allow_author_editor' ) == 1 ? 'checked="checked"' : '' ); ?> />
								<?php esc_html_e( 'Allow Author & Editor to edit STR', 'chatbot' ); ?>
							</label>
						</div>
						<p class="wpbot-str-hint"><?php esc_html_e( 'Enable this so Authors and Editors can edit Simple Text Responses.', 'chatbot' ); ?></p>
					</div>
				</div>
				<?php endif; ?>

				<div class="wpbot-str-form-actions">
					<button type="submit" class="wpbot-str-btn wpbot-str-btn-primary" name="submit"><?php esc_html_e( 'Save Settings', 'chatbot' ); ?></button>
				</div>
			</div>
		</form>

		<form class="wpbot-str-reindex wpbot-str-extra" method="post" action="" hidden>
			<input name="str_nonce" type="hidden" value="<?php echo esc_attr( sanitize_key( wp_create_nonce( 'str-nonce' ) ) ); ?>" />
			<div class="wpbot-str-reindex-copy">
				<strong><?php esc_html_e( 'Re-index responses', 'chatbot' ); ?></strong>
				<p class="wpbot-str-hint"><?php esc_html_e( 'Run this if search results are not what you expect.', 'chatbot' ); ?></p>
			</div>
			<button type="submit" class="wpbot-str-btn" name="qc-re-index" id="re-index" value="1"><?php esc_html_e( 'Re-index', 'chatbot' ); ?></button>
		</form>

		<button type="button" class="wpbot-str-expand" aria-expanded="false">
			<span class="wpbot-str-expand-label"><?php esc_html_e( 'Expand for More Options', 'chatbot' ); ?></span>
			<span class="wpbot-str-expand-icon" aria-hidden="true">+</span>
		</button>
	</div>

	<div class="wpbot-str-card wpbot-str-list-card">
		<div class="wpbot-str-list-head">
			<div>
				<h2><?php esc_html_e( 'Responses', 'chatbot' ); ?></h2>
				<p><?php esc_html_e( 'Questions, keywords, and the replies your chatbot can send.', 'chatbot' ); ?></p>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'action', 'edit', admin_url( 'admin.php?page=simple-text-response' ) ) ); ?>" class="wpbot-str-btn wpbot-str-btn-primary"><?php esc_html_e( 'Add New', 'chatbot' ); ?></a>
		</div>
		<form method="post">
			<?php
			$this->response_list->prepare_items();
			$this->response_list->display();
			?>
		</form>
	</div>
</div>
</div>
    <?php endif; ?>

	<style>
/* Scoped to Simple Text Responses. Tokens match the WPBot admin UI. */
.wpbot-str-page {
    --woobot-primary: #5B4E96;
    --woobot-primary-color: #5B4E96;
    --woobot-primary-dark: #463a7a;
    --woobot-primary-soft: #f3f1fa;
    --woobot-primary-border: #e4e0f2;
    --woobot-surface: #ffffff;
    --woobot-page-bg: #F5F7FD;
    --woobot-text: #2d2a3a;
    --woobot-muted: #6b6680;
    --woobot-danger: #e25563;
    --woobot-nav-muted: #988FBD;
    --woobot-primary-rgb: 91, 78, 150;
    display: block;
    width: calc(100% - 20px);
    max-width: calc(100% - 20px);
    margin: 20px 20px 36px 0;
    box-sizing: border-box;
    font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: var(--woobot-text);
    font-size: 14px;
}

body.wpbot-panel_page_simple-text-response,
body.chatbot-wpbot-lite_page_simple-text-response,
body:has(.wpbot-str-page) {
    background-color: #F5F7FD;
}

body:has(.wpbot-str-page) .notice.qcbot-feedback,
body:has(.wpbot-str-page) .notice.qcbot-str-top-notic {
    width: calc(100% - 20px) !important;
    max-width: calc(100% - 20px) !important;
    margin-left: 0 !important;
    margin-right: 20px !important;
    box-sizing: border-box;
    float: none;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    align-self: stretch;
    float: none;
    gap: 16px;
    width: 100%;
    max-width: 100%;
    margin: 0 0 16px;
    padding: 12px 18px;
    background: #fff;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 16px;
    box-sizing: border-box;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header-logo {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header img {
    width: 50px;
    height: 50px;
    max-width: 50px;
    flex: 0 0 50px;
    object-fit: contain;
}

.wpbot-str-page a.qcld-wp-chatbot-wrap-site__logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    padding: 0;
    color: #000;
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header p,
.wpbot-str-page .qcld-wp-chatbot-wrap-header ul,
.wpbot-str-page .qcld-wp-chatbot-wrap-header li {
    margin: 0;
    padding: 0;
    list-style: none;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header p {
    color: var(--woobot-text);
    font-size: 14px;
    white-space: nowrap;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-version-wrapper {
    margin: 0;
    padding: 0;
    list-style: none;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header a.wpchatbot-Upgrade {
    display: inline-block;
    padding: 12px 25px;
    background: #fff;
    color: var(--woobot-primary);
    border: 2px solid var(--woobot-primary);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
}

.wpbot-str-page .qcld-wp-chatbot-wrap-header a.wpchatbot-Upgrade:hover,
.wpbot-str-page .qcld-wp-chatbot-wrap-header a.wpchatbot-Upgrade:focus {
    background: var(--woobot-primary);
    border-color: var(--woobot-primary);
    color: #fff;
    text-decoration: none;
}

.wpbot-str-main {
    display: block;
    width: 100%;
    max-width: 100%;
    margin: 0;
    box-sizing: border-box;
}

.wpbot-str-hero,
.wpbot-str-card {
    background: #fff;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 16px;
    padding: 20px 22px;
    margin: 0 0 16px;
    box-sizing: border-box;
}

.wpbot-str-hero,
.wpbot-str-list-head,
.wpbot-str-card-head,
.wpbot-str-hero-actions,
.wpbot-str-form-actions,
.wpbot-str-reindex {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.wpbot-str-hero-actions,
.wpbot-str-form-actions {
    flex-wrap: wrap;
    justify-content: flex-start;
}

.wpbot-str-card-head {
    margin-bottom: 4px;
}

.wpbot-str-list-head {
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--woobot-primary-border);
}

.wpbot-str-hero h1,
.wpbot-str-card h2 {
    margin: 0 0 6px;
    padding: 0;
    border: 0;
    color: var(--woobot-primary);
    font-weight: 700;
    line-height: 1.3;
}

.wpbot-str-hero h1 {
    font-size: 24px;
}

.wpbot-str-card h2 {
    font-size: 18px;
}

.wpbot-str-hero p,
.wpbot-str-card-head p,
.wpbot-str-list-head p {
    margin: 0;
    max-width: 720px;
    color: var(--woobot-muted);
    font-size: 14px;
    line-height: 1.55;
}

.wpbot-str-note {
    display: inline-flex;
    margin-top: 12px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--woobot-primary-soft);
    border: 1px solid var(--woobot-primary-border);
    color: var(--woobot-primary);
    font-size: 12px;
    font-weight: 600;
}

.wpbot-str-callout {
    margin: 0 0 4px;
    padding: 12px 14px;
    background: var(--woobot-primary-soft);
    border: 1px solid var(--woobot-primary-border);
    border-radius: 10px;
}

.wpbot-str-callout p {
    margin: 0;
    color: var(--woobot-text);
    font-size: 13px;
}

.wpbot-str-field {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 8px 24px;
    align-items: start;
    padding: 16px 0;
    border-bottom: 1px solid #eef0f6;
}

.wpbot-str-label {
    padding-top: 10px;
    color: var(--woobot-text);
    font-size: 14px;
    font-weight: 600;
}

.wpbot-str-hint {
    margin: 6px 0 0;
    color: var(--woobot-muted);
    font-size: 12px;
    font-style: italic;
    font-weight: 400;
}

.wpbot-str-page .wpbot-str-input {
    width: 100%;
    max-width: 100%;
    min-height: 40px;
    margin: 0;
    padding: 8px 12px;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 8px;
    background: #fff;
    color: var(--woobot-text);
    box-shadow: none;
}

.wpbot-str-page .wpbot-str-input-short {
    max-width: 160px;
}

.wpbot-str-page .wpbot-str-input:focus {
    border-color: var(--woobot-primary);
    box-shadow: 0 0 0 3px rgba(var(--woobot-primary-rgb), 0.15);
    outline: none;
}

.wpbot-str-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.wpbot-str-checks label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 8px 12px;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 10px;
    background: var(--woobot-primary-soft);
    color: var(--woobot-text);
    font-size: 13px;
    font-weight: 500;
}

.wpbot-str-page input[type="checkbox"] {
    accent-color: var(--woobot-primary);
}

.wpbot-str-form-actions {
    padding-top: 16px;
}

.wpbot-str-reindex {
    align-items: center;
    margin-top: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--woobot-primary-border);
}

.wpbot-str-reindex-copy strong {
    display: block;
    color: var(--woobot-text);
    font-size: 14px;
}

.wpbot-str-page .wpbot-str-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin: 0;
    padding: 0 16px;
    height: auto;
    min-height: 36px;
    border: 2px solid var(--woobot-primary);
    border-radius: 8px;
    background: #fff;
    color: var(--woobot-primary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
    cursor: pointer;
    box-shadow: none;
}

.wpbot-str-page .wpbot-str-btn:hover,
.wpbot-str-page .wpbot-str-btn:focus {
    background: var(--woobot-primary);
    border-color: var(--woobot-primary);
    color: #fff;
    box-shadow: none;
    outline: none;
}

.wpbot-str-page .wpbot-str-btn-primary {
    background: var(--woobot-primary);
    color: #fff;
}

.wpbot-str-page .wpbot-str-btn-primary:hover,
.wpbot-str-page .wpbot-str-btn-primary:focus {
    background: var(--woobot-primary-dark);
    border-color: var(--woobot-primary-dark);
    color: #fff;
}

.wpbot-str-page .wpbot-str-extra[hidden] {
    display: none !important;
}

.wpbot-str-page .wpbot-str-expand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 16px;
    padding: 10px 14px;
    border: 2px solid var(--woobot-primary);
    border-radius: 8px;
    background: #fff;
    color: var(--woobot-primary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    box-shadow: none;
    box-sizing: border-box;
    /* max-width: 220px; */
    margin: 20px auto 0 auto;
    color: #ffffff;
    position: relative;
    padding: 40px 0 0 0;
    height: 80px;
    text-align: center;
    background: #f1f1f1;
    cursor: pointer;
    background: linear-gradient(to bottom, rgb(255 255 255 / 53%) 0%, rgb(84 68 157) 100%);
    margin: -77px 0 0 0;
    border-radius: 0 0 15px 15px;
    z-index: 99999;
    font-weight: bold;
    border: none;
}

.wpbot-str-page .wpbot-str-expand:hover,
.wpbot-str-page .wpbot-str-expand:focus {
    background: linear-gradient(to bottom, rgb(255 255 255 / 53%) 0%, rgb(84 68 157) 100%);
    color: #fff;
    outline: none;
}

.wpbot-str-page .wpbot-str-expand[aria-expanded="true"] {
    margin: 0 0 0 0;
}


.wpbot-str-page .wpbot-str-expand-icon {
    font-size: 16px;
    line-height: 1;
}

.wpbot-str-page .wp-editor-wrap {
    border: 1px solid var(--woobot-primary-border);
    border-radius: 10px;
}

.wpbot-str-page .tablenav {
    height: auto;
    margin: 0 0 12px;
    padding: 0;
    background: transparent;
}

.wpbot-str-page .tablenav.bottom {
    margin: 12px 0 0;
}

.wpbot-str-page .tablenav .actions select,
.wpbot-str-page .tablenav .tablenav-pages .current-page {
    min-height: 36px;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 8px;
    box-shadow: none;
}

.wpbot-str-page .tablenav .button,
.wpbot-str-page .tablenav .tablenav-pages a,
.wpbot-str-page .tablenav .tablenav-pages span.button {
    min-height: 32px;
    border: 1px solid var(--woobot-primary-border);
    border-radius: 8px;
    background: #fff;
    color: var(--woobot-primary);
    font-weight: 600;
    box-shadow: none;
}

.wpbot-str-page .tablenav .button:hover,
.wpbot-str-page .tablenav .tablenav-pages a:hover {
    border-color: var(--woobot-primary);
    background: var(--woobot-primary);
    color: #fff;
}

.wpbot-str-page .displaying-num {
    color: var(--woobot-muted);
    font-size: 13px;
}

.wpbot-str-page .wp-list-table {
    border: 1px solid var(--woobot-primary-border);
    border-radius: 12px;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    overflow: hidden;
}

.wpbot-str-page .wp-list-table thead th,
.wpbot-str-page .wp-list-table thead td,
.wpbot-str-page .wp-list-table tfoot th,
.wpbot-str-page .wp-list-table tfoot td {
    padding: 12px 10px;
    border: none;
    background: var(--woobot-primary);
    color: #fff;
    font-weight: 600;
}

.wpbot-str-page .wp-list-table thead a,
.wpbot-str-page .wp-list-table tfoot a {
    color: #fff;
}

.wpbot-str-page .wp-list-table thead .sorting-indicator:before,
.wpbot-str-page .wp-list-table thead th.sorted.asc .sorting-indicator.asc:before,
.wpbot-str-page .wp-list-table thead th.sorted.desc .sorting-indicator.desc:before,
.wpbot-str-page .wp-list-table thead th.sorted:hover .sorting-indicator:before,
.wpbot-str-page .wp-list-table thead th.sorted a:hover .sorting-indicator:before {
    color: #fff;
}

.wpbot-str-page .wp-list-table tfoot {
    display: none;
}

.wpbot-str-page .wp-list-table.striped > tbody > tr,
.wpbot-str-page .wp-list-table.striped > tbody > :nth-child(odd),
.wpbot-str-page .wp-list-table.striped > tbody > :nth-child(even) {
    background: #fff;
}

.wpbot-str-page .wp-list-table tbody tr:hover {
    background: var(--woobot-primary-soft);
}

.wpbot-str-page .wp-list-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #eef0f6;
    color: var(--woobot-text);
    vertical-align: middle;
}

.wpbot-str-page .wp-list-table tr:last-child td {
    border-bottom: none;
}

.wpbot-str-page .wp-list-table .row-actions {
    position: static;
    left: auto;
    padding-top: 4px;
    visibility: visible;
}

.wpbot-str-page .wp-list-table .row-actions a {
    color: var(--woobot-primary);
    font-weight: 600;
}

.wpbot-str-page .wp-list-table .row-actions .delete a {
    color: var(--woobot-danger);
}

.wpbot-str-page .no-items td {
    padding: 28px 16px;
    color: var(--woobot-muted);
    text-align: center;
}

form.wpbot-str-reindex.wpbot-str-extra {
    display: flex;
    flex-direction: column;
    align-content: flex-start;
    align-items: flex-start;
}

@media (max-width: 782px) {
    .wpbot-str-page .qcld-wp-chatbot-wrap-header,
    .wpbot-str-page .qcld-wp-chatbot-wrap-header-logo,
    .wpbot-str-hero,
    .wpbot-str-list-head,
    .wpbot-str-reindex,
    .wpbot-str-form-actions {
        flex-direction: column;
        align-items: flex-start;
    }

    .wpbot-str-field {
        grid-template-columns: 1fr;
        gap: 6px;
    }

    .wpbot-str-label {
        padding-top: 0;
    }

    .wpbot-str-page .wpbot-str-input-short {
        max-width: 100%;
    }
}
	</style>
<script>
jQuery(function ($) {
	$(document).on('click', '.wpbot-str-page .wpbot-str-expand', function () {
		var $button = $(this);
		var $card = $button.closest('.wpbot-str-card');
		var isOpen = $button.attr('aria-expanded') === 'true';

		if (isOpen) {
			$card.find('.wpbot-str-extra').attr('hidden', 'hidden');
			$button.attr('aria-expanded', 'false');
			$button.find('.wpbot-str-expand-label').text('<?php echo esc_js( __( 'Expand for More Options', 'chatbot' ) ); ?>');
			$button.find('.wpbot-str-expand-icon').text('+');
		} else {
			$card.find('.wpbot-str-extra').removeAttr('hidden');
			$button.attr('aria-expanded', 'true');
			$button.find('.wpbot-str-expand-label').text('<?php echo esc_js( __( 'Collapse Options', 'chatbot' ) ); ?>');
			$button.find('.wpbot-str-expand-icon').text('\u2212');
		}
	});
});
</script>
