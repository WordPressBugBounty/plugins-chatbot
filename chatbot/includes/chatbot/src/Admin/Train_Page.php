<?php
/**
 * ChatBot → Train (chatbot features CB1c): knowledge gaps, most asked first, with Add answer,
 * Write article and Ignore; a count badge on the menu item and a dashboard card.
 *
 * The page sits under the host's chatbot menu (WPBot Pro "wpbot-panel", WPBot Free
 * "wpbot_openAi"; filter qcld_wpbot_chat/admin_parent).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Abilities\Changes;
use QuantumCloud\Chatbot\Train\Gaps;

defined( 'ABSPATH' ) || exit;

/**
 * Train page.
 */
final class Train_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'qcld-wpbot-train';

	/**
	 * AJAX action and nonce action.
	 */
	const ACTION = 'qcld_wpbot_chat_train';

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ) );
	}

	/**
	 * Capability for the page.
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'qcld_wpbot_chat/train_capability', Permission_Sections::cap( 'view' ) );
	}

	/**
	 * The host's chatbot menu.
	 *
	 * @return string
	 */
	public static function parent() {
		$parent = isset( $GLOBALS['admin_page_hooks']['wpbot-panel'] ) ? 'wpbot-panel' : ( isset( $GLOBALS['admin_page_hooks']['wpbot_openAi'] ) ? 'wpbot_openAi' : '' );
		/**
		 * Parent menu of the module's admin pages ('' = none: the page is reachable by URL only).
		 *
		 * @param string $parent Menu slug.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/admin_parent', $parent );
	}

	/**
	 * Open gaps, cached for the menu badge.
	 *
	 * @return int
	 */
	public static function open_count() {
		$count = get_transient( 'qcld_wpbot_chat_gaps_open' );
		if ( false === $count ) {
			$count = Gaps::open_count() + Changes::pending_count();
			set_transient( 'qcld_wpbot_chat_gaps_open', $count, 10 * MINUTE_IN_SECONDS );
		}
		return (int) $count;
	}

	/**
	 * Add the menu item, with a badge when questions wait.
	 *
	 * @return void
	 */
	public static function menu() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$count = self::open_count();
		$label = esc_html__( 'Train', 'chatbot' );
		if ( $count > 0 ) {
			$label .= ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
		}
		$parent = self::parent();
		add_submenu_page( $parent, esc_html__( 'Train the chatbot', 'chatbot' ), $label, self::capability(), self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Page URL.
	 *
	 * @param string $status Tab.
	 * @return string
	 */
	public static function url( $status = 'open' ) {
		return add_query_arg(
			array(
				'page'   => self::SLUG,
				'status' => $status,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Reason labels.
	 *
	 * @return array<string,string>
	 */
	public static function reasons() {
		return array(
			'no_answer'   => __( 'No answer', 'chatbot' ),
			'not_sure'    => __( 'Not sure', 'chatbot' ),
			'thumbs_down' => __( 'Thumbs down', 'chatbot' ),
			'handoff'     => __( 'Asked for a person', 'chatbot' ),
		);
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A tab.
		$status  = in_array( $status, Gaps::STATUSES, true ) || 'changes' === $status ? $status : 'open';
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Paging.
		$list    = 'changes' === $status ? Changes::items( '', $page, 20 ) : Gaps::items( $status, $page, 20 );
		$tabs    = array(
			'open'     => __( 'Knowledge gaps', 'chatbot' ),
			'answered' => __( 'Answered', 'chatbot' ),
			'article'  => __( 'Article written', 'chatbot' ),
			'ignored'  => __( 'Ignored', 'chatbot' ),
			/* translators: %s: number of changes waiting for approval. */
			'changes'  => sprintf( __( 'Changes (%s waiting)', 'chatbot' ), number_format_i18n( Changes::pending_count() ) ),
		);
		$reasons = self::reasons();
		Admin_Pages::header( self::SLUG, __( 'Train the chatbot', 'chatbot' ) );
		echo '<p class="qcwp-lead">' . esc_html__( 'Questions your visitors asked that the chatbot could not answer, most asked first. Add an answer and the chatbot uses it from now on, write an article about it, or ignore it.', 'chatbot' ) . '</p>';
		echo '<nav class="qcwp-tabs" aria-label="' . esc_attr__( 'Train sections', 'chatbot' ) . '">';
		foreach ( $tabs as $key => $label ) {
			printf( '<a href="%1$s" class="qcwp-tab%2$s"%4$s>%3$s</a>', esc_url( self::url( $key ) ), $key === $status ? ' is-active' : '', esc_html( $label ), $key === $status ? ' aria-current="page"' : '' );
		}
		echo '</nav>';
		if ( 'changes' === $status ) {
			self::render_changes( $list );
			Admin_Pages::footer();
			self::script();
			return;
		}
		echo '<div class="qcwp-card">';
		if ( array() === $list['items'] ) {
			echo '<p class="qcwp-empty">' . esc_html( 'open' === $status ? __( 'No knowledge gaps. When the chatbot cannot answer a question, it shows up here.', 'chatbot' ) : __( 'Nothing here yet.', 'chatbot' ) ) . '</p></div>';
			Admin_Pages::footer();
			return;
		}
		echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr><th>' . esc_html__( 'Question', 'chatbot' ) . '</th><th style="width:140px">' . esc_html__( 'Why', 'chatbot' ) . '</th><th style="width:100px">' . esc_html__( 'Times asked', 'chatbot' ) . '</th><th style="width:140px">' . esc_html__( 'Last asked', 'chatbot' ) . '</th><th style="width:320px">' . esc_html__( 'Actions', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( $list['items'] as $gap ) {
			$id = (int) $gap['id'];
			echo '<tr class="qcld-gap" data-id="' . esc_attr( (string) $id ) . '">';
			echo '<td><strong class="qcld-gap-question">' . esc_html( $gap['question'] ) . '</strong>';
			if ( 'answered' === $status && '' !== (string) $gap['answer'] ) {
				echo '<p class="qcwp-help">' . esc_html( $gap['answer'] ) . '</p>';
			}
			echo '<div class="qcld-gap-answer" hidden><textarea rows="3" class="qcwp-textarea" aria-label="' . esc_attr__( 'Answer', 'chatbot' ) . '"></textarea><div class="qcwp-row" style="margin-top:8px"><button type="button" class="qcwp-btn is-primary is-small qcld-gap-save">' . esc_html__( 'Save answer', 'chatbot' ) . '</button></div></div></td>';
			echo '<td><span class="qcwp-pill">' . esc_html( isset( $reasons[ $gap['reason'] ] ) ? $reasons[ $gap['reason'] ] : $gap['reason'] ) . '</span></td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $gap['hits'] ) ) . '</td>';
			/* translators: %s: how long ago, for example "5 mins". */
			echo '<td>' . esc_html( sprintf( __( '%s ago', 'chatbot' ), human_time_diff( (int) strtotime( $gap['last_seen'] . ' UTC' ) ) ) ) . '</td><td><div class="qcwp-row">';
			if ( 'open' === $status ) {
				echo '<button type="button" class="qcwp-btn is-small qcld-gap-add">' . esc_html__( 'Add answer', 'chatbot' ) . '</button>';
				echo '<button type="button" class="qcwp-btn is-small qcld-gap-op" data-op="article">' . esc_html__( 'Write article', 'chatbot' ) . '</button>';
				echo '<button type="button" class="qcwp-btn is-small is-danger qcld-gap-op" data-op="ignore">' . esc_html__( 'Ignore', 'chatbot' ) . '</button>';
			} else {
				echo '<button type="button" class="qcwp-btn is-small qcld-gap-op" data-op="reopen">' . esc_html__( 'Move back to gaps', 'chatbot' ) . '</button>';
			}
			echo '<span class="qcld-gap-status qcwp-help" role="status"></span></div></td></tr>';
		}//end foreach
		echo '</tbody></table></div>';
		$pages = (int) ceil( $list['total'] / 20 );
		if ( $pages > 1 ) {
			echo '<div class="qcwp-pager">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', self::url( $status ) ),
						'current' => $page,
						'total'   => $pages,
					)
				)
			) . '</div>';
		}
		echo '</div>';
		Admin_Pages::footer();
		self::script();
	}

	/**
	 * The Changes tab: what AI assistants (Abilities, MCP) proposed or did, with approve, reject and undo.
	 *
	 * @param array $changes Changes::items().
	 * @return void
	 */
	private static function render_changes( array $changes ) {
		echo '<div class="qcwp-card"><p class="qcwp-help" style="margin:0 0 4px">' . esc_html__( 'What AI assistants (WordPress Abilities, MCP) asked to change. Approve or reject what waits; undo what was applied.', 'chatbot' ) . '</p>';
		if ( array() === $changes['items'] ) {
			echo '<p class="qcwp-empty">' . esc_html__( 'No changes yet.', 'chatbot' ) . '</p></div>';
			return;
		}
		$labels = array(
			'pending'  => __( 'Waiting', 'chatbot' ),
			'applied'  => __( 'Applied', 'chatbot' ),
			'rejected' => __( 'Rejected', 'chatbot' ),
			'undone'   => __( 'Undone', 'chatbot' ),
			'failed'   => __( 'Failed', 'chatbot' ),
			'done'     => __( 'Done', 'chatbot' ),
		);
		$tones  = array(
			'pending' => ' is-warn',
			'applied' => ' is-good',
			'done'    => ' is-good',
		);
		echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr><th>' . esc_html__( 'Change', 'chatbot' ) . '</th><th style="width:110px">' . esc_html__( 'Status', 'chatbot' ) . '</th><th style="width:170px">' . esc_html__( 'By', 'chatbot' ) . '</th><th style="width:130px">' . esc_html__( 'When', 'chatbot' ) . '</th><th style="width:230px">' . esc_html__( 'Actions', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( $changes['items'] as $change ) {
			$input = json_decode( (string) $change['input'], true );
			$user  = get_userdata( (int) $change['user_id'] );
			$state = (string) $change['status'];
			echo '<tr class="qcld-change" data-id="' . esc_attr( (string) (int) $change['id'] ) . '"><td><strong>' . esc_html( $change['summary'] ) . '</strong>';
			if ( is_array( $input ) && ( isset( $input['content'] ) || isset( $input['answer'] ) ) ) {
				echo '<p class="qcwp-help">' . esc_html( isset( $input['content'] ) ? (string) $input['content'] : (string) $input['answer'] ) . '</p>';
			}
			echo '</td><td><span class="qcwp-pill qcld-change-state' . esc_attr( isset( $tones[ $state ] ) ? $tones[ $state ] : '' ) . '">' . esc_html( isset( $labels[ $state ] ) ? $labels[ $state ] : $state ) . '</span></td>';
			echo '<td>' . esc_html( ( $user ? $user->display_name : __( 'Unknown', 'chatbot' ) ) . ' (' . $change['via'] . ')' ) . '</td>';
			/* translators: %s: how long ago, for example "5 mins". */
			echo '<td>' . esc_html( sprintf( __( '%s ago', 'chatbot' ), human_time_diff( (int) strtotime( $change['created'] . ' UTC' ) ) ) ) . '</td><td><div class="qcwp-row">';
			if ( 'pending' === $state ) {
				echo '<button type="button" class="qcwp-btn is-primary is-small qcld-gap-op" data-op="approve">' . esc_html__( 'Approve', 'chatbot' ) . '</button>';
				echo '<button type="button" class="qcwp-btn is-small is-danger qcld-gap-op" data-op="reject">' . esc_html__( 'Reject', 'chatbot' ) . '</button>';
			} elseif ( 'applied' === $state && '' !== (string) $change['undo_data'] && 'null' !== (string) $change['undo_data'] ) {
				echo '<button type="button" class="qcwp-btn is-small qcld-gap-op" data-op="undo">' . esc_html__( 'Undo', 'chatbot' ) . '</button>';
			}
			echo '<span class="qcld-gap-status qcwp-help" role="status"></span></div></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Page script.
	 *
	 * @return void
	 */
	private static function script() {
		$data = array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::ACTION ),
			'error' => __( 'Could not save. Reload the page and try again.', 'chatbot' ),
		);
		?>
		<script>
		( function ( $, cfg ) {
			function send( $row, op, extra ) {
				var $s = $row.find( '.qcld-gap-status' ).text( '…' );
				return $.post( cfg.url, $.extend( { action: '<?php echo esc_js( self::ACTION ); ?>', nonce: cfg.nonce, id: $row.data( 'id' ), op: op }, extra || {} ) )
					.done( function ( r ) {
						if ( ! r || ! r.success ) { $s.text( r && r.data && r.data.message ? r.data.message : cfg.error ); return; }
						if ( r.data.url ) { window.location.href = r.data.url; return; }
						if ( $row.hasClass( 'qcld-change' ) ) { window.location.reload(); return; }
						$row.fadeOut( 200, function () { $row.remove(); } );
					} )
					.fail( function () { $s.text( cfg.error ); } );
			}
			$( document ).on( 'click', '.qcld-gap-add', function () {
				$( this ).closest( 'tr' ).find( '.qcld-gap-answer' ).prop( 'hidden', false ).find( 'textarea' ).trigger( 'focus' );
			} );
			$( document ).on( 'click', '.qcld-gap-save', function () {
				var $row = $( this ).closest( 'tr' );
				send( $row, 'answer', { answer: $row.find( 'textarea' ).val() } );
			} );
			$( document ).on( 'click', '.qcld-gap-op', function () {
				send( $( this ).closest( 'tr' ), $( this ).data( 'op' ) );
			} );
		} )( jQuery, <?php echo wp_json_encode( $data ); ?> );
		</script>
		<?php
	}

	/**
	 * AJAX: answer, article, ignore, reopen.
	 *
	 * @return void
	 */
	public static function ajax() {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( Permission_Sections::cap( 'publish' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'chatbot' ) ), 403 );
		}
		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$op     = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$result = false;
		$url    = '';
		switch ( $op ) {
			case 'answer':
				$result = Gaps::answer( $id, isset( $_POST['answer'] ) ? wp_unslash( $_POST['answer'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in Gaps::answer().
				break;
			case 'article':
				$result = Gaps::write_article( $id );
				$url    = is_string( $result ) ? $result : '';
				break;
			case 'ignore':
				$result = Gaps::set_status( $id, 'ignored' );
				break;
			case 'reopen':
				$result = Gaps::set_status( $id, 'open' );
				break;
			case 'approve':
				$result = Changes::apply( $id );
				break;
			case 'reject':
				$result = Changes::reject( $id );
				break;
			case 'undo':
				$result = Changes::undo( $id );
				break;
		}//end switch
		delete_transient( 'qcld_wpbot_chat_gaps_open' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save. Reload the page and try again.', 'chatbot' ) ) );
		}
		wp_send_json_success( array( 'url' => esc_url_raw( $url ) ) );
	}

	/**
	 * Dashboard card: the most asked open questions.
	 *
	 * @return void
	 */
	public static function dashboard() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		wp_add_dashboard_widget( 'qcld_wpbot_chat_gaps', esc_html__( 'Chatbot: questions it could not answer', 'chatbot' ), array( __CLASS__, 'render_card' ) );
	}

	/**
	 * Dashboard card content.
	 *
	 * @return void
	 */
	public static function render_card() {
		$list = Gaps::items( 'open', 1, 5 );
		if ( array() === $list['items'] ) {
			echo '<p>' . esc_html__( 'No knowledge gaps: the chatbot answered every question.', 'chatbot' ) . '</p>';
			return;
		}
		echo '<ul>';
		foreach ( $list['items'] as $gap ) {
			/* translators: 1: question, 2: times asked. */
			echo '<li>' . esc_html( sprintf( __( '%1$s (%2$s×)', 'chatbot' ), $gap['question'], number_format_i18n( (int) $gap['hits'] ) ) ) . '</li>';
		}
		echo '</ul><p><a href="' . esc_url( self::url() ) . '">' . esc_html(
			sprintf(
				/* translators: %s: number of open questions. */
				_n( 'Train the chatbot (%s question)', 'Train the chatbot (%s questions)', $list['total'], 'chatbot' ),
				number_format_i18n( $list['total'] )
			)
		) . '</a></p>';
	}
}
