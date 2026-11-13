<?php
/**
 * "Answer scope" setting on AI Settings (Common AI Settings), for every provider.
 *
 * The host prints it with one line in its Common AI Settings template:
 *     do_action( 'qcld_wpbot_chat/answer_scope_setting' );
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Ai\Answer_Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Answer scope field.
 */
final class Answer_Scope_Field {

	/** AJAX action and nonce action. */
	const ACTION = 'qcld_wpbot_chat_save_answer_scope';

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/answer_scope_setting', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'save' ) );
	}

	/**
	 * Help text per choice.
	 *
	 * @return array<string,string>
	 */
	public static function help() {
		return array(
			Answer_Scope::BUSINESS_ONLY  => __( 'Questions about your business, products and services, plus greetings. Anything else gets your message below.', 'chatbot' ),
			Answer_Scope::BUSINESS_FIRST => __( 'Your business first, plus general questions in your field. Unrelated topics are steered back to your business.', 'chatbot' ),
			Answer_Scope::ANYTHING       => __( 'No limit: the AI answers questions on any topic.', 'chatbot' ),
		);
	}

	/**
	 * Print the setting.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$scope   = Answer_Scope::scope();
		$help    = self::help();
		$decline = trim( (string) get_option( Answer_Scope::DECLINE_OPTION, '' ) );
		?>
		<div class="qcld-wpbot-answer-scope my-4" id="qcld-wpbot-answer-scope" style="max-width:760px">
			<h5 style="margin-bottom:4px;"><?php esc_html_e( 'Answer scope', 'chatbot' ); ?></h5>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'What the AI may answer, for every AI provider. Added to your system prompt. When a question about your business is not covered by your content, the AI says it is not sure and suggests contacting you; it never guesses.', 'chatbot' ); ?></p>
			<?php foreach ( Answer_Scope::choices() as $value => $label ) : ?>
				<label style="display:block;margin:6px 0;">
					<input type="radio" name="qcld_wpbot_ai_answer_scope" value="<?php echo esc_attr( $value ); ?>" <?php checked( $scope, $value ); ?> />
					<strong><?php echo esc_html( $label ); ?></strong><?php echo Answer_Scope::DEFAULT_SCOPE === $value ? ' ' . esc_html__( '(recommended)', 'chatbot' ) : ''; ?>
					<span class="description" style="display:block;color:#646970;margin-left:24px;"><?php echo esc_html( $help[ $value ] ); ?></span>
				</label>
			<?php endforeach; ?>
			<div class="qcld-wpbot-answer-scope-decline" style="margin:10px 0 0 24px;<?php echo Answer_Scope::BUSINESS_ONLY === $scope ? '' : 'display:none;'; ?>">
				<label for="qcld_wpbot_ai_scope_decline_text" style="display:block;font-weight:600;"><?php esc_html_e( 'Message for other topics', 'chatbot' ); ?></label>
				<textarea class="form-control" rows="2" id="qcld_wpbot_ai_scope_decline_text" placeholder="<?php echo esc_attr( Answer_Scope::default_decline() ); ?>"><?php echo esc_textarea( $decline ); ?></textarea>
				<span class="description" style="display:block;color:#646970;"><?php esc_html_e( 'Leave empty for the default. Steer the visitor back to what you offer.', 'chatbot' ); ?></span>
			</div>
			<div style="margin-top:12px;">
				<label for="qcld_wpbot_ai_scope_contact" style="display:block;font-weight:600;"><?php esc_html_e( 'Contact details for "not sure" answers', 'chatbot' ); ?></label>
				<input type="text" class="form-control" id="qcld_wpbot_ai_scope_contact" value="<?php echo esc_attr( (string) get_option( Answer_Scope::CONTACT_OPTION, '' ) ); ?>" placeholder="<?php echo esc_attr( '' !== Answer_Scope::business()['contact'] ? Answer_Scope::business()['contact'] : __( 'Email, phone or contact page link', 'chatbot' ) ); ?>" />
				<span class="description" style="display:block;color:#646970;"><?php esc_html_e( 'When the AI cannot answer a question about your business, it suggests these. Leave empty to use your Business Profile, or "through the website".', 'chatbot' ); ?></span>
			</div>
			<div style="margin-top:12px;">
				<label style="display:block;">
					<input type="checkbox" id="qcld_wpbot_citations" value="1" <?php checked( \QuantumCloud\Chatbot\Conversation\Steps\Knowledge_Step::citations_enabled() ); ?> />
					<strong><?php esc_html_e( 'Show source links under answers', 'chatbot' ); ?></strong>
					<span class="description" style="display:block;color:#646970;margin-left:24px;"><?php esc_html_e( 'When an answer comes from your knowledge base, the pages it used are listed under it. One setting for every AI provider.', 'chatbot' ); ?></span>
				</label>
			</div>
			<?php self::render_handoff(); ?>
			<?php self::render_cost(); ?>
			<p style="margin-top:10px;">
				<button type="button" class="btn btn-success" id="qcld_wpbot_answer_scope_save" data-nonce="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>"><?php esc_html_e( 'Save answer scope', 'chatbot' ); ?></button>
				<span class="qcld-wpbot-answer-scope-status" role="status" style="margin-left:8px;"></span>
			</p>
		</div>
		<script>
		( function () {
			var box = document.getElementById( 'qcld-wpbot-answer-scope' );
			if ( ! box ) { return; }
			var decline = box.querySelector( '.qcld-wpbot-answer-scope-decline' );
			box.addEventListener( 'change', function ( e ) {
				if ( 'qcld_wpbot_ai_answer_scope' === e.target.name ) {
					decline.style.display = <?php echo wp_json_encode( Answer_Scope::BUSINESS_ONLY ); ?> === e.target.value ? '' : 'none';
				}
			} );
			box.querySelector( '#qcld_wpbot_answer_scope_save' ).addEventListener( 'click', function () {
				var button = this, status = box.querySelector( '.qcld-wpbot-answer-scope-status' );
				var checked = box.querySelector( 'input[name="qcld_wpbot_ai_answer_scope"]:checked' );
				var body = new URLSearchParams();
				body.append( 'action', <?php echo wp_json_encode( self::ACTION ); ?> );
				body.append( 'nonce', button.getAttribute( 'data-nonce' ) );
				body.append( 'scope', checked ? checked.value : '' );
				body.append( 'decline', box.querySelector( '#qcld_wpbot_ai_scope_decline_text' ).value );
				body.append( 'contact', box.querySelector( '#qcld_wpbot_ai_scope_contact' ).value );
				body.append( 'citations', box.querySelector( '#qcld_wpbot_citations' ).checked ? '1' : '0' );
				body.append( 'handoff_sent', '1' );
				box.querySelectorAll( 'input[name="qcld_wpbot_handoff_items[]"]:checked' ).forEach( function ( el ) { body.append( 'handoff[]', el.value ); } );
				var handoffMax = box.querySelector( '#qcld_wpbot_handoff_max' );
				if ( handoffMax ) { body.append( 'handoff_max', handoffMax.value ); }
				button.disabled = true;
				fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) { status.textContent = r && r.success ? <?php echo wp_json_encode( __( 'Saved.', 'chatbot' ) ); ?> : <?php echo wp_json_encode( __( 'Could not save. Reload the page and try again.', 'chatbot' ) ); ?>; } )
					.catch( function () { status.textContent = <?php echo wp_json_encode( __( 'Could not save. Reload the page and try again.', 'chatbot' ) ); ?>; } )
					.then( function () { button.disabled = false; } );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * "When the bot isn't sure, show": start-menu items as handoff buttons.
	 *
	 * @return void
	 */
	public static function render_handoff() {
		$items = \QuantumCloud\Chatbot\Ai\Safe_Answer::menu_items();
		if ( ! $items ) {
			return;
		}
		$chosen = wp_list_pluck( \QuantumCloud\Chatbot\Ai\Safe_Answer::handoff(), 'label' );
		$max    = (int) get_option( \QuantumCloud\Chatbot\Ai\Safe_Answer::MAX_OPTION, 3 );
		?>
		<div style="margin-top:12px;">
			<strong style="display:block;"><?php esc_html_e( "When the bot isn't sure, show these buttons", 'chatbot' ); ?></strong>
			<span class="description" style="display:block;color:#646970;"><?php esc_html_e( 'Items from your start menu. The question is also added to Questions Not Answered.', 'chatbot' ); ?></span>
			<?php foreach ( array_keys( $items ) as $label ) : ?>
				<label style="display:inline-block;margin:4px 12px 0 0;"><input type="checkbox" name="qcld_wpbot_handoff_items[]" value="<?php echo esc_attr( $label ); ?>" <?php checked( in_array( $label, $chosen, true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			<label style="display:block;margin-top:6px;"><?php esc_html_e( 'Most buttons', 'chatbot' ); ?> <input type="number" id="qcld_wpbot_handoff_max" min="1" max="6" value="<?php echo esc_attr( (string) $max ); ?>" style="width:60px;" /></label>
		</div>
		<?php
	}

	/**
	 * The chatbot's AI cost this month, by provider and model (WPBot Core's usage log).
	 *
	 * @return void
	 */
	public static function render_cost() {
		$usage = '\QuantumCloud\WPBotCore\AI\Usage';
		if ( ! class_exists( $usage ) || ! method_exists( $usage, 'summary' ) ) {
			return;
		}
		$summary = $usage::summary( 'chatbot', 1 );
		$models  = isset( $summary['models'] ) ? (array) $summary['models'] : array();
		?>
		<div class="qcld-wpbot-chat-cost" style="margin-top:14px;">
			<h5 style="margin-bottom:4px;"><?php esc_html_e( 'AI cost this month', 'chatbot' ); ?></h5>
			<?php if ( ! $models ) : ?>
				<p class="description" style="margin-top:0;"><?php esc_html_e( 'No AI answers recorded yet this month.', 'chatbot' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:640px;">
					<thead><tr><th><?php esc_html_e( 'Provider', 'chatbot' ); ?></th><th><?php esc_html_e( 'Model', 'chatbot' ); ?></th><th><?php esc_html_e( 'Answers', 'chatbot' ); ?></th><th><?php esc_html_e( 'Estimated cost (USD)', 'chatbot' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $models as $row ) : ?>
						<?php
						$known = ! method_exists( '\QuantumCloud\WPBotCore\AI\Providers', 'price_known' ) || \QuantumCloud\WPBotCore\AI\Providers::price_known( (string) $row['provider'], (string) $row['model'] );
						?>
						<tr>
							<td><?php echo esc_html( (string) $row['provider'] ); ?></td>
							<td><?php echo esc_html( (string) $row['model'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['calls'] ) ); ?></td>
							<td><?php echo $known ? esc_html( '$' . number_format_i18n( (float) $row['cost'], 4 ) ) : esc_html__( 'price unknown', 'chatbot' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Estimates from the providers\' list prices. Your provider\'s bill is the exact figure.', 'chatbot' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: save the scope and the decline message.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
		if ( ! array_key_exists( $scope, Answer_Scope::choices() ) ) {
			wp_send_json_error( null, 400 );
		}
		$decline = isset( $_POST['decline'] ) ? sanitize_textarea_field( wp_unslash( $_POST['decline'] ) ) : '';
		update_option( Answer_Scope::OPTION, $scope );
		update_option( Answer_Scope::DECLINE_OPTION, mb_substr( $decline, 0, 500 ) );
		$contact = isset( $_POST['contact'] ) ? sanitize_text_field( wp_unslash( $_POST['contact'] ) ) : '';
		update_option( Answer_Scope::CONTACT_OPTION, mb_substr( $contact, 0, 300 ) );
		$citations = isset( $_POST['citations'] ) && '0' === sanitize_key( wp_unslash( $_POST['citations'] ) ) ? '0' : '1';
		update_option( \QuantumCloud\Chatbot\Conversation\Steps\Knowledge_Step::CITATIONS_OPTION, $citations, false );
		if ( isset( $_POST['handoff_sent'] ) ) {
			$labels  = array_keys( \QuantumCloud\Chatbot\Ai\Safe_Answer::menu_items() );
			$chosen  = isset( $_POST['handoff'] ) && is_array( $_POST['handoff'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['handoff'] ) ) : array();
			$chosen  = array_values( array_intersect( $chosen, $labels ) );
			$max_btn = isset( $_POST['handoff_max'] ) ? max( 1, min( 6, absint( $_POST['handoff_max'] ) ) ) : 3;
			update_option( \QuantumCloud\Chatbot\Ai\Safe_Answer::ITEMS_OPTION, $chosen, false );
			update_option( \QuantumCloud\Chatbot\Ai\Safe_Answer::MAX_OPTION, $max_btn, false );
		}
		wp_send_json_success( array( 'scope' => $scope ) );
	}
}
