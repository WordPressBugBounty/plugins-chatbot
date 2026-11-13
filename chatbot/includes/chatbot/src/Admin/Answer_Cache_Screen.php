<?php
/**
 * AI Settings > Answer cache: the saved answers, how often each was reused, and their check status.
 * The owner can check answers with AI (one, or every answer not checked yet), keep a flagged answer,
 * delete one, or clear the cache.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Ai\Answer_Cache;
use QuantumCloud\Chatbot\Ai\Answer_Review;
use QuantumCloud\Chatbot\Ai\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * The Answer cache section.
 */
final class Answer_Cache_Screen {

	/**
	 * AJAX action and nonce.
	 */
	const AJAX = 'qcld_wpbot_chat_answer_cache';

	/**
	 * Hook the AJAX handler.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			Answer_Cache::NEW_ANSWER => __( 'Not checked', 'chatbot' ),
			Answer_Cache::CORRECT    => __( 'Correct', 'chatbot' ),
			Answer_Cache::FLAGGED    => __( 'Flagged', 'chatbot' ),
		);
	}

	/**
	 * Print the section.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$stats     = Answer_Cache::stats();
		$list      = Answer_Cache::rows(
			array(
				'status' => $status,
				'search' => $search,
				'page'   => $page,
			)
		);
		$labels    = self::statuses();
		$unchecked = Answer_Cache::unchecked_ids( Answer_Review::BATCH );
		$estimate  = Answer_Review::estimate( count( $unchecked ) );

		echo '<div class="qcwp-answer-cache">';
		if ( ! Routing::cache_on() ) {
			echo '<div class="qcwp-notice" role="status">' . esc_html__( 'The answer cache is off, so no new answers are saved.', 'chatbot' ) . ' <a href="' . esc_url( Ai_Settings_Page::url( 'limits' ) ) . '">' . esc_html( sprintf( /* translators: %s: section name, for example Spending and limits. */ __( 'Turn it on in %s', 'chatbot' ), Ai_Settings_Page::sections()['limits'][0] ) ) . '</a></div>';
		}

		// Summary.
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Last 7 days', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'A saved answer is reused only for the same first question from a visitor who is not logged in, with the same settings and the same knowledge base text. Saved answers expire after 7 days and are cleared when you save AI Settings. Flagged answers are no longer used.', 'chatbot' ) . '</p>';
		echo '<div class="qcwp-cache-stats">';
		$cards = array(
			array( __( 'Saved answers', 'chatbot' ), number_format_i18n( $stats['answers'] ) ),
			array( __( 'Times reused', 'chatbot' ), number_format_i18n( $stats['hits'] ) ),
			/* translators: %s: amount in US dollars. */
			array( __( 'Estimated AI cost saved', 'chatbot' ), sprintf( __( '$%s', 'chatbot' ), number_format_i18n( $stats['saved'], 4 ) ) ),
			array( $labels[ Answer_Cache::NEW_ANSWER ], number_format_i18n( $stats['new'] ) ),
			array( $labels[ Answer_Cache::CORRECT ], number_format_i18n( $stats['correct'] ) ),
			array( $labels[ Answer_Cache::FLAGGED ], number_format_i18n( $stats['flagged'] ) ),
		);
		foreach ( $cards as $card ) {
			echo '<div class="qcwp-cache-stat"><span>' . esc_html( $card[0] ) . '</span><strong>' . esc_html( $card[1] ) . '</strong></div>';
		}
		echo '</div></div>';

		// Actions.
		echo '<div class="qcwp-card qcwp-cache-actions"><h2>' . esc_html__( 'Check answers with AI', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Each check sends the question, the saved answer and the knowledge base text found for the question today to the AI that answers your chatbot. It replies correct, wrong or unsure. Wrong and unsure answers are flagged and stop being used. Checks run only when you click.', 'chatbot' ) . '</p><p>';
		if ( $unchecked ) {
			$cost = $estimate['known']
				/* translators: 1: number of answers, 2: estimated cost in US dollars, 3: model. */
				? sprintf( _n( 'Check %1$d answer (about $%2$s with %3$s)', 'Check %1$d answers (about $%2$s with %3$s)', count( $unchecked ), 'chatbot' ), count( $unchecked ), number_format_i18n( $estimate['cost'], 4 ), $estimate['model'] )
				/* translators: %d: number of answers. */
				: sprintf( _n( 'Check %d answer not checked yet', 'Check %d answers not checked yet', count( $unchecked ), 'chatbot' ), count( $unchecked ) );
			echo '<button type="button" class="qcwp-btn is-primary" data-cache-check-all="' . esc_attr( implode( ',', $unchecked ) ) . '">' . esc_html( $cost ) . '</button> ';
		} else {
			echo '<span class="qcwp-help">' . esc_html__( 'Every saved answer has been checked.', 'chatbot' ) . '</span> ';
		}
		echo '<button type="button" class="qcwp-btn" data-cache-clear>' . esc_html__( 'Clear answer cache', 'chatbot' ) . '</button> <span class="qcwp-state" data-cache-progress role="status"></span></p></div>';

		// Filters.
		echo '<div class="qcwp-card"><div class="qcwp-cache-filters"><nav class="qcwp-tabs" aria-label="' . esc_attr__( 'Filter saved answers', 'chatbot' ) . '">';
		$tabs = array( '' => __( 'All', 'chatbot' ) ) + $labels;
		foreach ( $tabs as $id => $label ) {
			printf( '<a class="qcwp-tab%1$s" href="%2$s">%3$s</a>', $id === $status ? ' is-active' : '', esc_url( Ai_Settings_Page::url( 'cache', '' !== $id ? array( 'status' => $id ) : array() ) ), esc_html( $label ) );
		}
		echo '</nav><form class="qcwp-cache-search" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( Ai_Settings_Page::SLUG ) . '"><input type="hidden" name="section" value="cache">';
		if ( '' !== $status ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
		}
		echo '<label class="screen-reader-text" for="qcwp-cache-search">' . esc_html__( 'Search saved answers', 'chatbot' ) . '</label><input type="search" class="qcwp-input" id="qcwp-cache-search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search questions and answers', 'chatbot' ) . '"> <button type="submit" class="qcwp-btn">' . esc_html__( 'Search', 'chatbot' ) . '</button></form></div>';

		if ( ! $list['rows'] ) {
			echo '<p class="qcwp-empty">' . esc_html( '' !== $search || '' !== $status ? __( 'Nothing matches.', 'chatbot' ) : __( 'No saved answers yet.', 'chatbot' ) ) . '</p>';
		} else {
			echo '<div class="qcwp-table-wrap"><table class="qcwp-table qcwp-cache-table"><thead><tr><th>' . esc_html__( 'Question', 'chatbot' ) . '</th><th>' . esc_html__( 'Saved answer', 'chatbot' ) . '</th><th>' . esc_html__( 'Reused', 'chatbot' ) . '</th><th>' . esc_html__( 'Check', 'chatbot' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'chatbot' ) . '</span></th></tr></thead><tbody>';
			foreach ( $list['rows'] as $row ) {
				self::render_row( $row, $labels );
			}
			echo '</tbody></table></div>';
			$pages = (int) ceil( $list['total'] / 20 );
			if ( $pages > 1 ) {
				echo '<div class="qcwp-pager">';
				for ( $i = 1; $i <= $pages; $i++ ) {
					$args = array( 'paged' => $i );
					if ( '' !== $status ) {
						$args['status'] = $status;
					}
					if ( '' !== $search ) {
						$args['s'] = $search;
					}
					printf( '<a class="qcwp-btn%1$s" href="%2$s"%3$s>%4$d</a> ', $i === $page ? ' is-primary' : '', esc_url( Ai_Settings_Page::url( 'cache', $args ) ), $i === $page ? ' aria-current="page"' : '', (int) $i );
				}
				echo '</div>';
			}
		}//end if
		echo '</div></div>';
		self::script();
	}

	/**
	 * One row.
	 *
	 * @param array $row    Row.
	 * @param array $labels Status labels.
	 * @return void
	 */
	private static function render_row( array $row, array $labels ) {
		$id     = (int) $row['id'];
		$status = isset( $labels[ $row['status'] ] ) ? (string) $row['status'] : Answer_Cache::NEW_ANSWER;
		$answer = trim( wp_strip_all_tags( (string) $row['answer'] ) );
		$short  = function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $answer, 0, 220, '…', 'UTF-8' ) : substr( $answer, 0, 220 );
		echo '<tr data-cache-row="' . esc_attr( (string) $id ) . '"><td>' . esc_html( (string) $row['question'] ) . '<br><small class="qcwp-help">' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_date_from_gmt( (string) $row['created'] ) ) ) . ' · ' . esc_html( (string) $row['model'] ) . '</small></td>';
		echo '<td>' . ( $short !== $answer ? '<details><summary>' . esc_html( $short ) . '</summary><div class="qcwp-cache-full">' . nl2br( esc_html( $answer ) ) . '</div></details>' : nl2br( esc_html( $answer ) ) ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td>';
		echo '<td><span class="qcwp-pill qcwp-cache-' . esc_attr( $status ) . '" data-cache-status>' . esc_html( $labels[ $status ] ) . '</span>' . ( '' !== (string) $row['note'] ? '<br><small class="qcwp-help" data-cache-note>' . esc_html( (string) $row['note'] ) . '</small>' : '<br><small class="qcwp-help" data-cache-note></small>' ) . '</td>';
		echo '<td class="qcwp-cache-buttons"><button type="button" class="qcwp-btn" data-cache-do="check">' . esc_html__( 'Check with AI', 'chatbot' ) . '</button>';
		if ( Answer_Cache::FLAGGED === $status ) {
			echo ' <button type="button" class="qcwp-btn" data-cache-do="keep">' . esc_html__( 'Keep', 'chatbot' ) . '</button>';
		}
		echo ' <button type="button" class="qcwp-btn" data-cache-do="delete">' . esc_html__( 'Delete', 'chatbot' ) . '</button></td></tr>';
	}

	/**
	 * AJAX: check, keep, delete, clear.
	 *
	 * @return void
	 */
	public static function ajax() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::AJAX, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'chatbot' ) ), 403 );
		}
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		switch ( $do ) {
			case 'check':
				$result = Answer_Review::check( $id );
				if ( is_wp_error( $result ) ) {
					$data   = $result->get_error_data();
					$detail = is_array( $data ) && ! empty( $data['detail'] ) ? (string) $data['detail'] : $result->get_error_message();
					wp_send_json_error( array( 'message' => $detail ) );
				}
				$labels = self::statuses();
				$row    = Answer_Cache::row( $id );
				wp_send_json_success(
					array(
						'status' => $result['status'],
						'label'  => $labels[ $result['status'] ],
						'note'   => $row ? (string) $row['note'] : '',
					)
				);
				break;
			case 'keep':
				Answer_Cache::set_status( $id, Answer_Cache::CORRECT, __( 'Kept by you', 'chatbot' ) );
				wp_send_json_success(
					array(
						'status' => Answer_Cache::CORRECT,
						'label'  => self::statuses()[ Answer_Cache::CORRECT ],
						'note'   => __( 'Kept by you', 'chatbot' ),
					)
				);
				break;
			case 'delete':
				Answer_Cache::delete( $id );
				wp_send_json_success();
				break;
			case 'clear':
				Answer_Cache::clear();
				wp_send_json_success();
				break;
		}//end switch
		wp_send_json_error( array( 'message' => __( 'Unknown action.', 'chatbot' ) ) );
	}

	/**
	 * The section's script and styles.
	 *
	 * @return void
	 */
	private static function script() {
		$cfg = array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'action'  => self::AJAX,
			'nonce'   => wp_create_nonce( self::AJAX ),
			'confirm' => __( 'Delete every saved answer? Visitors get fresh AI answers again.', 'chatbot' ),
			'working' => __( 'Checking…', 'chatbot' ),
			/* translators: 1: answers checked so far, 2: all answers. */
			'step'    => __( 'Checked %1$d of %2$d…', 'chatbot' ),
			'done'    => __( 'Done.', 'chatbot' ),
			'fail'    => __( 'Did not work. Try again.', 'chatbot' ),
		);
		?>
		<style>
		.qcwp-cache-filters{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
		.qcwp-cache-filters .qcwp-tabs{margin:0}
		.qcwp-cache-search{display:flex;gap:8px;align-items:center}
		.qcwp-cache-search .qcwp-input{min-width:240px}
		.qcwp-cache-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:12px}
		.qcwp-cache-stat{border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;display:flex;flex-direction:column;gap:4px}
		.qcwp-cache-stat span{color:#475569;font-size:12px}
		.qcwp-cache-stat strong{font-size:20px;color:#0f172a}
		.qcwp-cache-table td{vertical-align:top}
		.qcwp-cache-table summary{cursor:pointer}
		.qcwp-cache-full{margin-top:6px;white-space:normal}
		.qcwp-cache-buttons{white-space:nowrap}
		.qcwp-pill.qcwp-cache-correct{background:#dcfce7;color:#166534}
		.qcwp-pill.qcwp-cache-flagged{background:#fee2e2;color:#991b1b}
		.qcwp-pill.qcwp-cache-new{background:#f1f5f9;color:#334155}
		</style>
		<script>
		( function ( cfg ) {
			function post( data ) {
				var body = new URLSearchParams( Object.assign( { action: cfg.action, nonce: cfg.nonce }, data ) );
				return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
			}
			function show( row, data ) {
				var pill = row.querySelector( '[data-cache-status]' ), note = row.querySelector( '[data-cache-note]' );
				pill.className = 'qcwp-pill qcwp-cache-' + data.status;
				pill.textContent = data.label;
				note.textContent = data.note || '';
			}
			var progress = document.querySelector( '[data-cache-progress]' );
			document.querySelectorAll( '[data-cache-row]' ).forEach( function ( row ) {
				row.addEventListener( 'click', function ( e ) {
					var button = e.target.closest( '[data-cache-do]' );
					if ( ! button ) { return; }
					var what = button.getAttribute( 'data-cache-do' );
					button.disabled = true;
					if ( 'check' === what ) { button.textContent = cfg.working; }
					post( { 'do': what, id: row.getAttribute( 'data-cache-row' ) } ).then( function ( r ) {
						if ( ! r || ! r.success ) { throw new Error( r && r.data && r.data.message ? r.data.message : cfg.fail ); }
						if ( 'delete' === what ) { row.remove(); return; }
						show( row, r.data );
						if ( 'keep' === what ) { button.remove(); }
					} ).catch( function ( err ) { progress.textContent = err.message || cfg.fail; } ).then( function () {
						if ( document.body.contains( button ) ) { button.disabled = false; if ( 'check' === what ) { button.textContent = button.getAttribute( 'data-label' ) || button.textContent; } }
					} );
				} );
				var check = row.querySelector( '[data-cache-do="check"]' );
				if ( check ) { check.setAttribute( 'data-label', check.textContent ); }
			} );
			var all = document.querySelector( '[data-cache-check-all]' );
			if ( all ) {
				all.addEventListener( 'click', function () {
					var ids = all.getAttribute( 'data-cache-check-all' ).split( ',' ).filter( Boolean ), done = 0;
					all.disabled = true;
					( function next() {
						if ( done >= ids.length ) { progress.textContent = cfg.done; window.location.reload(); return; }
						progress.textContent = cfg.step.replace( '%1$d', done ).replace( '%2$d', ids.length );
						post( { 'do': 'check', id: ids[ done ] } ).then( function ( r ) {
							var row = document.querySelector( '[data-cache-row="' + ids[ done ] + '"]' );
							if ( r && r.success && row ) { show( row, r.data ); }
							if ( r && ! r.success ) { progress.textContent = ( r.data && r.data.message ) || cfg.fail; throw new Error( 'stop' ); }
							done++;
							next();
						} ).catch( function () { all.disabled = false; } );
					} )();
				} );
			}
			var clear = document.querySelector( '[data-cache-clear]' );
			if ( clear ) {
				clear.addEventListener( 'click', function () {
					if ( ! window.confirm( cfg.confirm ) ) { return; }
					clear.disabled = true;
					post( { 'do': 'clear' } ).then( function () { window.location.reload(); } ).catch( function () { progress.textContent = cfg.fail; clear.disabled = false; } );
				} );
			}
		} )( <?php echo wp_json_encode( $cfg ); ?> );
		</script>
		<?php
	}
}
