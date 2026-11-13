<?php
/**
 * "More options" on each AI provider's existing settings page.
 *
 * Shows only the common settings that provider had no option for (see
 * Ai\Provider_Settings), saved to their new options when the owner clicks the
 * page's own Save button. The provider's own fields and script are untouched.
 *
 * The host prints the panel with one line in each provider template:
 *     do_action( 'qcld_wpbot_chat/provider_settings_fields', 'mistral' );
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Ai\Provider_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Provider "More options" panel.
 */
final class Provider_Fields {

	/**
	 * AJAX action and nonce action.
	 */
	const ACTION = 'qcld_wpbot_chat_save_provider_fields';

	/**
	 * Settings this panel can show (the others have their own fields or are shared).
	 */
	const EDITABLE = array( 'system_prompt', 'temperature', 'max_tokens', 'stream', 'knowledge_base', 'page_context', 'page_suggestions', 'prepend', 'append', 'model' );

	/**
	 * Save button id on each provider's page.
	 */
	const SAVE_BUTTONS = array(
		'openai'     => 'save_setting',
		'gemini'     => 'qcld_save_gemini_setting',
		'claude'     => 'qcld_save_claude_setting',
		'grok'       => 'qcld_save_grok_setting',
		'openrouter' => 'qcld_save_openrouter_setting',
		'mistral'    => 'qcld_save_mistral_setting',
		'ollama'     => 'qcld_save_ollama_setting',
		'deepseek'   => 'qcld_deepseek_save_setting',
	);

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/provider_settings_fields', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'save' ) );
	}

	/**
	 * Settings shown for a provider: editable ones with no option of their own today.
	 *
	 * @param string $provider Provider id.
	 * @return string[]
	 */
	public static function fields_for( $provider ) {
		$map = Provider_Settings::map();
		if ( ! isset( $map[ $provider ] ) ) {
			return array();
		}
		$fields = array();
		foreach ( self::EDITABLE as $field ) {
			if ( null === $map[ $provider ][ $field ] ) {
				$fields[] = $field;
			}
		}
		return $fields;
	}

	/**
	 * Labels and help.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function labels() {
		return array(
			'model'            => array( __( 'Model', 'chatbot' ), __( 'Model id as the provider names it.', 'chatbot' ) ),
			'system_prompt'    => array( __( 'System prompt', 'chatbot' ), __( 'Instructions the AI follows in every reply.', 'chatbot' ) ),
			'temperature'      => array( __( 'Temperature', 'chatbot' ), __( '0 = focused, 2 = creative. Leave empty for the provider default.', 'chatbot' ) ),
			'max_tokens'       => array( __( 'Max tokens', 'chatbot' ), __( 'Longest reply, in tokens. Leave empty for the default.', 'chatbot' ) ),
			'stream'           => array( __( 'Stream replies', 'chatbot' ), __( 'Show the reply while it is being written.', 'chatbot' ) ),
			'knowledge_base'   => array( __( 'Use the knowledge base', 'chatbot' ), __( 'Answer from your site content.', 'chatbot' ) ),
			'page_context'     => array( __( 'Use the current page', 'chatbot' ), __( 'Tell the AI which page the visitor is on.', 'chatbot' ) ),
			'page_suggestions' => array( __( 'Suggest related pages', 'chatbot' ), __( 'Add links to related pages under the reply.', 'chatbot' ) ),
			'prepend'          => array( __( 'Text before every reply', 'chatbot' ), '' ),
			'append'           => array( __( 'Text after every reply', 'chatbot' ), '' ),
		);
	}

	/**
	 * Print the panel for one provider.
	 *
	 * @param string $provider Provider id.
	 * @return void
	 */
	public static function render( $provider ) {
		$provider = sanitize_key( $provider );
		$fields   = self::fields_for( $provider );
		if ( array() === $fields || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Provider_Settings::get( $provider );
		$labels   = self::labels();
		$types    = Provider_Settings::FIELDS;
		?>
		<fieldset class="qcld-wpbot-chat-more-options mb-3" data-provider="<?php echo esc_attr( $provider ); ?>" data-save-button="<?php echo esc_attr( self::SAVE_BUTTONS[ $provider ] ); ?>" style="border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:12px 0;">
			<legend style="font-size:14px;font-weight:600;width:auto;padding:0 6px;"><?php esc_html_e( 'More options', 'chatbot' ); ?></legend>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'The same options every AI provider has. Saved with the Save settings button below.', 'chatbot' ); ?></p>
			<?php foreach ( $fields as $field ) : ?>
				<?php
				$id    = 'qcld_wpbot_chat_' . $provider . '_' . $field;
				$value = $settings[ $field ];
				?>
				<div class="mb-2" style="margin-bottom:10px;">
					<?php if ( 'bool' === $types[ $field ] ) : ?>
						<label for="<?php echo esc_attr( $id ); ?>"><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>" value="1" <?php checked( (bool) $value ); ?> /> <?php echo esc_html( $labels[ $field ][0] ); ?></label>
					<?php else : ?>
						<label for="<?php echo esc_attr( $id ); ?>" style="display:block;font-weight:600;"><?php echo esc_html( $labels[ $field ][0] ); ?></label>
						<?php if ( 'system_prompt' === $field ) : ?>
							<textarea class="form-control" rows="3" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
						<?php elseif ( 'temperature' === $field ) : ?>
							<input class="form-control" style="max-width:120px;" type="number" min="0" max="2" step="0.1" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( null === $value ? '' : (string) $value ); ?>" />
						<?php elseif ( 'max_tokens' === $field ) : ?>
							<input class="form-control" style="max-width:120px;" type="number" min="1" max="200000" step="1" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( null === $value ? '' : (string) $value ); ?>" />
						<?php else : ?>
							<input class="form-control" type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( '' !== $labels[ $field ][1] ) : ?>
						<span class="description" style="display:block;color:#646970;"><?php echo esc_html( $labels[ $field ][1] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<span class="qcld-wpbot-chat-more-options-status" role="status" aria-live="polite"></span>
		</fieldset>
		<?php
		self::print_script();
	}

	/**
	 * Print the save script once per page.
	 *
	 * @return void
	 */
	private static function print_script() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		$config  = array(
			'url'    => admin_url( 'admin-ajax.php' ),
			'action' => self::ACTION,
			'nonce'  => wp_create_nonce( self::ACTION ),
			'saved'  => __( 'More options saved.', 'chatbot' ),
			'failed' => __( 'More options were not saved. Reload the page and try again.', 'chatbot' ),
		);
		// One capturing listener on the document: panels further down the page exist by the time
		// the owner clicks, and the provider's own click handlers cannot stop it.
		$script = 'window.qcldWpbotChatMoreOptions=' . wp_json_encode( $config ) . ';document.addEventListener("click",function(e){var c=window.qcldWpbotChatMoreOptions;document.querySelectorAll(".qcld-wpbot-chat-more-options").forEach(function(box){var btn=document.getElementById(box.dataset.saveButton);if(!btn||!btn.contains(e.target)){return;}var data=new FormData();data.append("action",c.action);data.append("_ajax_nonce",c.nonce);data.append("provider",box.dataset.provider);box.querySelectorAll("input,textarea").forEach(function(el){data.append(el.name,el.type==="checkbox"?(el.checked?"1":"0"):el.value);});var status=box.querySelector(".qcld-wpbot-chat-more-options-status");fetch(c.url,{method:"POST",credentials:"same-origin",body:data}).then(function(r){return r.json();}).then(function(r){status.textContent=r&&r.success?c.saved:c.failed;}).catch(function(){status.textContent=c.failed;});});},true);';
		wp_print_inline_script_tag( $script );
	}

	/**
	 * Save the panel (AJAX).
	 *
	 * @return void
	 */
	public static function save() {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$fields   = self::fields_for( $provider );
		if ( array() === $fields ) {
			wp_send_json_error( null, 400 );
		}
		$saved = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = self::clean( $field, wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by type in clean().
			update_option( Provider_Settings::new_option( $provider, $field ), $value, false );
			$saved[ $field ] = $value;
		}
		wp_send_json_success( $saved );
	}

	/**
	 * Sanitize one value by the setting's type.
	 *
	 * @param string $field Setting.
	 * @param mixed  $raw   Posted value.
	 * @return string Stored as text; '' means "use the default".
	 */
	public static function clean( $field, $raw ) {
		$raw  = is_scalar( $raw ) ? (string) $raw : '';
		$type = Provider_Settings::FIELDS[ $field ];
		switch ( $type ) {
			case 'bool':
				return '1' === $raw ? '1' : '0';
			case 'float':
				return is_numeric( $raw ) ? (string) max( 0, min( 2, round( (float) $raw, 2 ) ) ) : '';
			case 'int':
				return is_numeric( $raw ) && (int) $raw > 0 ? (string) min( 200000, (int) $raw ) : '';
			default:
				return 'system_prompt' === $field ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		}
	}
}
