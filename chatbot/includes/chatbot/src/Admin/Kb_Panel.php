<?php
/**
 * Knowledge base status on the existing knowledge base settings page.
 *
 * Shows what the knowledge base holds, the move from the previous version, and
 * the pieces waiting for vectors with what embedding them would cost, with
 * "Sync now" and "Update vectors" buttons. The host prints it with
 * do_action( 'qcld_wpbot_chat/kb_settings_panel' ) on its knowledge base screen.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge base panel.
 */
final class Kb_Panel {

	/**
	 * AJAX action and nonce action.
	 */
	const ACTION = 'qcld_wpbot_chat_kb';

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		// The settings page shows a one-line status; the list has its own section (owner, 10 November 2026).
		add_action( 'qcld_wpbot_chat/kb_settings_panel', array( __CLASS__, 'render_compact' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Status text lines (also returned by the AJAX action).
	 *
	 * @return array
	 */
	public static function status() {
		$kb     = '\QuantumCloud\WPBotCore\Knowledge\Kb';
		$status = $kb::status();
		$lines  = array();
		/* translators: 1: pieces, 2: items. */
		$lines[] = sprintf( __( 'The knowledge base holds %1$d pieces from %2$d items.', 'chatbot' ), $status['counts']['chunks'], $status['counts']['sources'] );
		if ( $status['embedding'] ) {
			/* translators: 1: pieces, 2: model. */
			$lines[] = sprintf( __( '%1$d pieces are ready for search with %2$s.', 'chatbot' ), $status['counts']['ready'], $status['embedding']['provider'] . ' / ' . $status['embedding']['model'] );
		} else {
			$lines[] = __( 'No AI provider with embeddings is set up, so the knowledge base uses keyword search.', 'chatbot' );
		}
		if ( $status['estimate']['chunks'] > 0 && $status['embedding'] ) {
			/* translators: 1: pieces, 2: tokens, 3: cost in US dollars. */
			$lines[] = sprintf( __( '%1$d pieces are waiting for vectors (about %2$s tokens, about $%3$s). They are prepared in the background after you click Update vectors.', 'chatbot' ), $status['estimate']['chunks'], number_format_i18n( $status['estimate']['tokens'] ), number_format_i18n( $status['estimate']['usd'], 2 ) );
		}
		if ( $status['migration']['needed'] ) {
			$lines[] = __( 'Moving the knowledge from the previous version in the background…', 'chatbot' );
		} elseif ( '' !== $status['migration']['migrated'] ) {
			$lines[] = __( 'Knowledge from the previous version has been moved; saved vectors were kept where possible.', 'chatbot' );
		}
		return array(
			'lines'   => $lines,
			'waiting' => (int) $status['estimate']['chunks'],
		);
	}

	/**
	 * The status in one line: pieces, ready, waiting.
	 *
	 * @return string
	 */
	public static function summary() {
		$kb     = '\QuantumCloud\WPBotCore\Knowledge\Kb';
		$status = $kb::status();
		$ready  = $status['embedding'] ? (int) $status['counts']['ready'] : 0;
		$parts  = array(
			/* translators: %d: pieces of knowledge. */
			sprintf( _n( '%d piece', '%d pieces', (int) $status['counts']['chunks'], 'chatbot' ), (int) $status['counts']['chunks'] ),
			/* translators: %d: pieces ready for search. */
			$status['embedding'] ? sprintf( __( '%d ready for search', 'chatbot' ), $ready ) : __( 'keyword search (no embeddings set up)', 'chatbot' ),
		);
		if ( $status['embedding'] && (int) $status['estimate']['chunks'] > 0 ) {
			/* translators: %d: pieces waiting for vectors. */
			$parts[] = sprintf( __( '%d waiting for vectors', 'chatbot' ), (int) $status['estimate']['chunks'] );
		}
		return implode( ' · ', $parts );
	}

	/**
	 * The settings page's status: one line, Sync now, Update vectors and a link to the list.
	 *
	 * @return void
	 */
	public static function render_compact() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb' ) ) {
			return;
		}
		$list_url = class_exists( Ai_Settings_Page::class ) ? Ai_Settings_Page::url( 'known' ) : '';
		?>
		<div class="qcld-wpbot-chat-kb-panel qcld-wpbot-chat-kb-compact qcwp-card" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
			<strong><?php esc_html_e( 'Knowledge base', 'chatbot' ); ?></strong>
			<span class="qcld-wpbot-chat-kb-summary"><?php echo esc_html( self::summary() ); ?></span>
			<span style="flex:1"></span>
			<button type="button" class="button" data-kb-do="sync"><?php esc_html_e( 'Sync now', 'chatbot' ); ?></button>
			<button type="button" class="button button-primary" data-kb-do="embed"><?php esc_html_e( 'Update vectors', 'chatbot' ); ?></button>
			<?php if ( '' !== $list_url ) : ?>
				<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'What the chatbot knows', 'chatbot' ); ?></a>
			<?php endif; ?>
			<span class="qcld-wpbot-chat-kb-result" role="status" aria-live="polite" style="flex-basis:100%;"></span>
		</div>
		<?php
		self::script();
	}

	/**
	 * Print the panel.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb' ) ) {
			return;
		}
		$status = self::status();
		?>
		<div class="qcld-wpbot-chat-kb-panel" style="border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:12px 0;background:#fff;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Knowledge base status', 'chatbot' ); ?></h3>
			<ul class="qcld-wpbot-chat-kb-lines" style="margin:0 0 10px 18px;list-style:disc;">
				<?php foreach ( $status['lines'] as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="button" data-kb-do="sync"><?php esc_html_e( 'Sync now', 'chatbot' ); ?></button>
			<button type="button" class="button button-primary" data-kb-do="embed"><?php esc_html_e( 'Update vectors', 'chatbot' ); ?></button>
			<span class="qcld-wpbot-chat-kb-result" role="status" aria-live="polite" style="margin-left:8px;"></span>
			<?php self::render_items(); ?>
		</div>
		<?php
		self::script();
	}

	/**
	 * The panel's script (once per page).
	 *
	 * @return void
	 */
	private static function script() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		$config  = array(
			'url'    => admin_url( 'admin-ajax.php' ),
			'action' => self::ACTION,
			'nonce'  => wp_create_nonce( self::ACTION ),
			'failed' => __( 'That did not work. Reload the page and try again.', 'chatbot' ),
		);
		wp_print_inline_script_tag( 'window.qcldWpbotChatKb=' . wp_json_encode( $config ) . ';document.addEventListener("click",function(e){var b=e.target.closest(".qcld-wpbot-chat-kb-panel [data-kb-do]");if(!b){return;}var c=window.qcldWpbotChatKb,p=b.closest(".qcld-wpbot-chat-kb-panel"),out=p.querySelector(".qcld-wpbot-chat-kb-result"),d=new FormData();d.append("action",c.action);d.append("_ajax_nonce",c.nonce);d.append("do",b.dataset.kbDo);b.disabled=true;fetch(c.url,{method:"POST",credentials:"same-origin",body:d}).then(function(r){return r.json();}).then(function(r){out.textContent=r&&r.data&&r.data.message?r.data.message:c.failed;if(r&&r.success&&r.data.lines){var ul=p.querySelector(".qcld-wpbot-chat-kb-lines");if(ul){ul.textContent="";r.data.lines.forEach(function(t){var li=document.createElement("li");li.textContent=t;ul.appendChild(li);});}}if(r&&r.success&&r.data.summary){var sm=p.querySelector(".qcld-wpbot-chat-kb-summary");if(sm){sm.textContent=r.data.summary;}}}).catch(function(){out.textContent=c.failed;}).finally(function(){b.disabled=false;});});' );
	}

	/**
	 * Source filter choices: source type (or prefix ending in ":") => label.
	 *
	 * @return array<string,string>
	 */
	public static function type_filters() {
		$types = array( '' => __( 'All sources', 'chatbot' ) );
		foreach ( array( 'page', 'post', 'product' ) as $post_type ) {
			$object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
			if ( $object ) {
				$types[ 'post:' . $post_type ] = (string) $object->labels->name;
			}
		}
		$types['str']     = __( 'Simple Text Responses', 'chatbot' );
		$types['legacy:'] = __( 'Uploaded files', 'chatbot' );
		/**
		 * Source filter choices of "What the chatbot knows".
		 *
		 * @param array<string,string> $types Source type (or prefix ending in ":") => label.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/kb_type_filters', $types );
	}

	/**
	 * Label of a source type.
	 *
	 * @param string $type Source type (post:page, str, legacy:pdf...).
	 * @return string
	 */
	public static function source_label( $type ) {
		if ( 0 === strpos( $type, 'post:' ) ) {
			$object = get_post_type_object( substr( $type, 5 ) );
			return $object ? (string) $object->labels->singular_name : substr( $type, 5 );
		}
		if ( 'str' === $type ) {
			return __( 'Simple Text Response', 'chatbot' );
		}
		if ( 0 === strpos( $type, 'legacy:' ) ) {
			/* translators: %s: file or source type, such as pdf or csv. */
			return sprintf( __( 'Uploaded (%s)', 'chatbot' ), substr( $type, 7 ) );
		}
		return $type;
	}

	/**
	 * Status of one item in plain words.
	 *
	 * @param array $item Item from qcld_wpbot_core_kb_items().
	 * @return string
	 */
	public static function status_label( array $item ) {
		if ( '' !== (string) $item['error'] || 'error' === $item['status'] ) {
			/* translators: %s: the error. */
			return '' !== (string) $item['error'] ? sprintf( __( 'Error: %s', 'chatbot' ), $item['error'] ) : __( 'Error', 'chatbot' );
		}
		if ( (int) $item['chunks'] > 0 && (int) $item['ready'] >= (int) $item['chunks'] ) {
			return __( 'Ready', 'chatbot' );
		}
		return __( 'Waiting for search vectors (keyword search until then)', 'chatbot' );
	}

	/**
	 * What the knowledge base holds: a searchable, paginated list (synced posts and pages,
	 * uploads, Simple Text Responses). Read only.
	 *
	 * @return void
	 */
	public static function render_items() {
		if ( ! function_exists( 'qcld_wpbot_core_kb_items' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$search = isset( $_GET['kb_s'] ) ? sanitize_text_field( wp_unslash( $_GET['kb_s'] ) ) : '';
		$paged  = isset( $_GET['kb_paged'] ) ? max( 1, absint( $_GET['kb_paged'] ) ) : 1;
		// Keep the case: the AI Settings slug is wpbot_openAi (sanitize_key() would lowercase it).
		$screen  = isset( $_GET['page'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		$type    = isset( $_GET['kb_type'] ) ? sanitize_text_field( wp_unslash( $_GET['kb_type'] ) ) : '';
		$state   = isset( $_GET['kb_status'] ) ? sanitize_key( wp_unslash( $_GET['kb_status'] ) ) : '';
		// phpcs:enable
		$types  = self::type_filters();
		$type   = isset( $types[ $type ] ) ? $type : '';
		$states = array(
			''        => __( 'Any status', 'chatbot' ),
			'ready'   => __( 'Ready', 'chatbot' ),
			'waiting' => __( 'Waiting for vectors', 'chatbot' ),
		);
		$state  = isset( $states[ $state ] ) ? $state : '';
		$list   = qcld_wpbot_core_kb_items(
			array(
				'search'   => $search,
				'type'     => $type,
				'status'   => $state,
				'page'     => $paged,
				'per_page' => 20,
			)
		);
		$pages  = max( 1, (int) ceil( $list['total'] / max( 1, $list['per_page'] ) ) );
		$base   = add_query_arg(
			array_filter(
				array(
					'page'      => $screen,
					'section'   => $section,
					'kb_s'      => $search,
					'kb_type'   => $type,
					'kb_status' => $state,
				),
				'strlen'
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="qcld-wpbot-chat-kb-items" style="margin-top:14px;">
			<h4 style="margin:0 0 6px;"><?php esc_html_e( 'Items in the knowledge base', 'chatbot' ); ?></h4>
			<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Pages and posts appear here when they are published or updated with Auto Sync on, or after Sync now. Answers come from this list.', 'chatbot' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="qcld-wpbot-chat-kb-filters" style="margin:0 0 8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
				<input type="hidden" name="page" value="<?php echo esc_attr( $screen ); ?>" />
				<?php if ( '' !== $section ) : ?>
					<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>" />
				<?php endif; ?>
				<label class="screen-reader-text" for="qcld-wpbot-chat-kb-search"><?php esc_html_e( 'Search the knowledge base', 'chatbot' ); ?></label>
				<input type="search" id="qcld-wpbot-chat-kb-search" name="kb_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by title', 'chatbot' ); ?>" />
				<label class="screen-reader-text" for="qcld-wpbot-chat-kb-type"><?php esc_html_e( 'Source', 'chatbot' ); ?></label>
				<select id="qcld-wpbot-chat-kb-type" name="kb_type">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="qcld-wpbot-chat-kb-status"><?php esc_html_e( 'Status', 'chatbot' ); ?></label>
				<select id="qcld-wpbot-chat-kb-status" name="kb_status">
					<?php foreach ( $states as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'chatbot' ); ?></button>
			</form>
			<table class="widefat striped qcld-wpbot-chat-kb-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'chatbot' ); ?></th>
						<th><?php esc_html_e( 'Source', 'chatbot' ); ?></th>
						<th><?php esc_html_e( 'Ready for search', 'chatbot' ); ?></th>
						<th><?php esc_html_e( 'Status', 'chatbot' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $list['items'] ) : ?>
						<tr><td colspan="5"><?php echo '' !== $search || '' !== $type || '' !== $state ? esc_html__( 'Nothing matches your search.', 'chatbot' ) : esc_html__( 'The knowledge base is empty. Turn on pages or posts above, then click Sync now.', 'chatbot' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $list['items'] as $item ) : ?>
						<tr>
							<td>
								<?php if ( '' !== $item['url'] ) : ?>
									<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( '' !== $item['title'] ? $item['title'] : $item['url'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( '' !== $item['title'] ? $item['title'] : $item['source_id'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( self::source_label( $item['source_type'] ) ); ?></td>
							<td>
								<?php
								/* translators: 1: pieces ready, 2: all pieces. */
								echo esc_html( sprintf( __( '%1$d of %2$d pieces', 'chatbot' ), $item['ready'], $item['chunks'] ) );
								?>
							</td>
							<td><?php echo esc_html( self::status_label( $item ) ); ?></td>
							<td><?php echo esc_html( '' !== $item['updated_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['updated_at'] ) : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $pages > 1 ) : ?>
				<p class="qcld-wpbot-chat-kb-pages" style="margin:8px 0 0;">
					<?php
					/* translators: 1: page, 2: pages, 3: items. */
					echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d items)', 'chatbot' ), $list['page'], $pages, $list['total'] ) ) . ' ';
					if ( $list['page'] > 1 ) {
						echo '<a class="button" href="' . esc_url(
							add_query_arg( 'kb_paged', $list['page'] - 1, $base )
						) . '">' . esc_html__( 'Previous', 'chatbot' ) . '</a> ';
					}
					if ( $list['page'] < $pages ) {
						echo '<a class="button" href="' . esc_url(
							add_query_arg( 'kb_paged', $list['page'] + 1, $base )
						) . '">' . esc_html__( 'Next', 'chatbot' ) . '</a>';
					}
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Run an action (AJAX): sync, embed or status.
	 *
	 * @return void
	 */
	public static function ajax() {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'status';
		$message = '';
		if ( 'sync' === $do ) {
			$job     = \QuantumCloud\WPBotCore\Knowledge\Kb_Sources::sync();
			$message = is_wp_error( $job ) ? $job->get_error_message() : __( 'Sync started. Changed items are read in the background.', 'chatbot' );
		} elseif ( 'embed' === $do ) {
			$job     = \QuantumCloud\WPBotCore\Knowledge\Kb_Embedder::schedule();
			$message = $job ? __( 'Vectors are being prepared in the background.', 'chatbot' ) : __( 'Nothing is waiting, or no AI provider with embeddings is set up.', 'chatbot' );
		}
		$status            = self::status();
		$status['summary'] = self::summary();
		$status['message'] = $message;
		wp_send_json_success( $status );
	}
}
