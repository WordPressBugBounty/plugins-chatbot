/**
 * WPBot AI — shared jQuery core.
 *
 * admin-ajax client, hash router, toasts, modals, form builders and small SVG charts
 * used by the AI Content Generator (and later the AI SEO Analyzer) pages.
 *
 * Exposes window.QcldWpbotAiSeo.
 */
/* global jQuery, wp, qcldWpbotAiSeoData */
( function ( $, window, document ) {
	'use strict';

	const cfg = window.qcldWpbotAiSeoData || {};
	const i18n = ( window.wp && window.wp.i18n ) || null;
	const __ = ( text ) => ( i18n ? i18n.__( text, 'chatbot' ) : text );
	const sprintf = ( fmt, ...args ) => {
		if ( i18n && i18n.sprintf ) {
			return i18n.sprintf( fmt, ...args );
		}
		let i = 0;
		return String( fmt ).replace( /%(\d+\$)?[sd]/g, ( m, pos ) => {
			const idx = pos ? parseInt( pos, 10 ) - 1 : i++;
			return String( args[ idx ] );
		} );
	};

	/* ── Escaping ───────────────────────────────────────── */

	const esc = ( value ) =>
		String( value === null || value === undefined ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );

	const num = ( value ) => ( Number( value ) || 0 ).toLocaleString();

	/* ── admin-ajax client ─────────────────────────────── */

	const slowTimer = { id: null };

	/**
	 * Call an admin-ajax endpoint.
	 *
	 * @param {string} action Action without the qcld_wpbot_ai_seo_ prefix.
	 * @param {Object} data   Parameters (sent as JSON so types survive).
	 * @return {Promise<*>} Resolves with the endpoint data, rejects with Error(message).
	 */
	const api = ( action, data ) =>
		new Promise( ( resolve, reject ) => {
			$.ajax( {
				url: cfg.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'qcld_wpbot_ai_seo_' + action,
					nonce: cfg.nonce,
					payload: JSON.stringify( data || {} ),
				},
			} )
				.done( ( res ) => {
					if ( res && res.success ) {
						resolve( res.data );
						return;
					}
					reject( toError( res, null ) );
				} )
				.fail( ( xhr ) => {
					reject( toError( xhr.responseJSON, xhr ) );
				} );
		} );

	const toError = ( res, xhr ) => {
		const body = res && res.data ? res.data : {};
		let message = body.message || '';
		if ( ! message ) {
			if ( xhr && 0 === xhr.status ) {
				message = __( 'Network error. Check your connection and try again.' );
			} else if ( xhr && 504 === xhr.status ) {
				message = __( 'The server timed out. Your AI provider may need more time — try again.' );
			} else if ( xhr && '-1' === String( xhr.responseText ).trim() ) {
				message = __( 'Your session has expired. Reload the page and try again.' );
			} else {
				message = __( 'An error occurred.' );
			}
		}
		const err = new Error( message );
		err.code = body.code || '';
		err.status = xhr ? xhr.status : 200;
		err.data = body.data || {};
		return err;
	};

	/** Warn after a while that the AI provider is slow (same as the old app). */
	const slow = {
		start( ms ) {
			slow.stop();
			slowTimer.id = window.setTimeout( () => {
				toast( __( 'Please wait — your AI provider may need more time to respond.' ), 'warning', 8000 );
			}, ms || 15000 );
		},
		stop() {
			if ( slowTimer.id ) {
				window.clearTimeout( slowTimer.id );
				slowTimer.id = null;
			}
		},
	};

	/** Where toasts and modals are mounted (inside the app so the skin tokens apply). */
	const host = () => {
		const $layout = $( '.qcld_wpbot_ai_seo-app-layout' ).first();
		return $layout.length ? $layout : $( 'body' );
	};

	/* ── Toasts ───────────────────────────────────────── */

	const toast = ( message, type, duration ) => {
		type = type || 'success';
		let $wrap = $( '.qcld_wpbot_ai_seo-toast-container' );
		if ( ! $wrap.length ) {
			$wrap = $( '<div class="qcld_wpbot_ai_seo-toast-container" role="status" aria-live="polite"></div>' ).appendTo( host() );
		}
		const exists = $wrap.children().filter( function () {
			return $( this ).data( 'msg' ) === message && $( this ).data( 'type' ) === type;
		} );
		if ( exists.length ) {
			return;
		}
		const $t = $(
			'<div class="qcld_wpbot_ai_seo-toast qcld_wpbot_ai_seo-toast--' + esc( type ) + '"><span></span>' +
				'<button type="button" class="qcld_wpbot_ai_seo-toast-dismiss" aria-label="' + esc( __( 'Dismiss' ) ) + '">×</button></div>'
		);
		$t.data( { msg: message, type } ).find( 'span' ).text( message );
		const close = () => $t.fadeOut( 150, () => $t.remove() );
		$t.find( 'button' ).on( 'click', close );
		$wrap.append( $t );
		window.setTimeout( close, duration || 4000 );
	};

	/* ── Modals ───────────────────────────────────────── */

	/**
	 * Open a modal.
	 *
	 * @param {Object} o title, body (html), footer (html), className, width, onOpen($m), onClose().
	 * @return {jQuery} The overlay element (call .trigger('qcld:close') to close).
	 */
	const modal = ( o ) => {
		const $o = $(
			'<div class="qcld_wpbot_ai_seo-premium-modal-overlay">' +
				'<div class="qcld_wpbot_ai_seo-premium-modal ' + esc( o.className || '' ) + '" role="dialog" aria-modal="true"' +
				( o.width ? ' style="width:' + esc( o.width ) + '"' : '' ) + '>' +
				'<div class="qcld_wpbot_ai_seo-premium-modal-header"><h3>' + ( o.icon || '' ) + esc( o.title || '' ) + '</h3>' +
				'<button type="button" class="qcld_wpbot_ai_seo-modal-close" aria-label="' + esc( __( 'Close' ) ) + '">×</button></div>' +
				'<div class="qcld_wpbot_ai_seo-premium-modal-body">' + ( o.body || '' ) + '</div>' +
				( o.footer ? '<div class="qcld_wpbot_ai_seo-premium-modal-footer">' + o.footer + '</div>' : '' ) +
				'</div></div>'
		);
		const close = () => {
			$( document ).off( 'keydown.qcldmodal' );
			$o.remove();
			if ( o.onClose ) {
				o.onClose();
			}
		};
		$o.on( 'qcld:close', close );
		$o.on( 'click', ( e ) => {
			if ( e.target === $o[ 0 ] ) {
				close();
			}
		} );
		$o.find( '.qcld_wpbot_ai_seo-modal-close, [data-modal-cancel]' ).on( 'click', close );
		$( document ).on( 'keydown.qcldmodal', ( e ) => {
			if ( 'Escape' === e.key ) {
				close();
			}
		} );
		host().append( $o );
		if ( o.onOpen ) {
			o.onOpen( $o );
		}
		$o.find( 'input, textarea, select' ).first().trigger( 'focus' );
		return $o;
	};

	const ICON_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
	const ICON_ALERT = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';

	/**
	 * Confirmation dialog.
	 *
	 * Options: title, message, items (array of plain-text lines shown as a
	 * list), note (plain text under the list), confirmLabel, cancelLabel,
	 * destructive. All text is escaped.
	 *
	 * @return {Promise<boolean>}
	 */
	const confirmDialog = ( o ) =>
		new Promise( ( resolve ) => {
			let answered = false;
			const $m = modal( {
				title: o.title || __( 'Confirm' ),
				width: '440px',
				icon:
					'<span class="qcld_wpbot_ai_seo-premium-modal-icon"' +
					( o.destructive ? ' style="background:linear-gradient(135deg,#c62828,#e53935)"' : '' ) + '>' +
					( o.destructive ? ICON_ALERT : ICON_CHECK ) + '</span>',
				body: '<p class="qcld_wpbot_ai_seo-confirm-text">' + esc( o.message || '' ) + '</p>' +
					( o.items && o.items.length
						? '<ul class="qcld_wpbot_ai_seo-confirm-list">' + o.items.map( ( i ) => '<li>' + esc( i ) + '</li>' ).join( '' ) + '</ul>'
						: '' ) +
					( o.note ? '<p class="qcld_wpbot_ai_seo-confirm-note">' + esc( o.note ) + '</p>' : '' ),
				footer:
					'<button type="button" class="qcld_wpbot_ai_seo-btn-cancel" data-modal-cancel>' + esc( o.cancelLabel || __( 'Cancel' ) ) + '</button>' +
					'<button type="button" class="' + ( o.destructive ? 'qcld_wpbot_ai_seo-btn-danger' : 'qcld_wpbot_ai_seo-btn-primary' ) + '" data-confirm>' +
					esc( o.confirmLabel || __( 'Confirm' ) ) + '</button>',
				onClose: () => {
					if ( ! answered ) {
						resolve( false );
					}
				},
			} );
			$m.find( '[data-confirm]' ).on( 'click', () => {
				answered = true;
				$m.trigger( 'qcld:close' );
				resolve( true );
			} );
		} );

	/* ── Markup builders ─────────────────────────────── */

	const ui = {};

	ui.btn = ( label, o ) => {
		o = o || {};
		const cls = [ 'qcld_wpbot_ai_seo-btn', 'qcld_wpbot_ai_seo-btn--' + ( o.variant || 'secondary' ) ];
		if ( o.small ) {
			cls.push( 'qcld_wpbot_ai_seo-btn--small' );
		}
		if ( o.destructive ) {
			cls.push( 'is-destructive' );
		}
		if ( o.className ) {
			cls.push( o.className );
		}
		const attrs = [];
		Object.keys( o.attrs || {} ).forEach( ( k ) => attrs.push( esc( k ) + '="' + esc( o.attrs[ k ] ) + '"' ) );
		if ( o.disabled ) {
			attrs.push( 'disabled' );
		}
		if ( o.title ) {
			attrs.push( 'title="' + esc( o.title ) + '"' );
		}
		const inner = ( o.icon ? '<span class="dashicons ' + esc( o.icon ) + '" aria-hidden="true"></span>' : '' ) +
			( o.html ? label : esc( label ) );
		if ( o.href ) {
			return '<a class="' + cls.join( ' ' ) + '" href="' + esc( o.href ) + '"' + ( o.target ? ' target="' + esc( o.target ) + '" rel="noreferrer"' : '' ) + ' ' + attrs.join( ' ' ) + '>' + inner + '</a>';
		}
		return '<button type="button" class="' + cls.join( ' ' ) + '" ' + attrs.join( ' ' ) + '>' + inner + '</button>';
	};

	ui.iconBtn = ( icon, label, o ) => {
		o = o || {};
		return '<button type="button" class="qcld_wpbot_ai_seo-icon-btn' + ( o.destructive ? ' is-destructive' : '' ) + '" aria-label="' + esc( label ) + '" title="' + esc( label ) + '"' +
			Object.keys( o.attrs || {} ).map( ( k ) => ' ' + esc( k ) + '="' + esc( o.attrs[ k ] ) + '"' ).join( '' ) +
			( o.disabled ? ' disabled' : '' ) + '><span class="dashicons ' + esc( icon ) + '" aria-hidden="true"></span></button>';
	};

	/** Busy state: spinner + label inside a button. */
	ui.busy = ( $btn, busy, label ) => {
		if ( busy ) {
			$btn.data( 'qcld-label', $btn.html() );
			$btn.prop( 'disabled', true ).addClass( 'is-busy' ).html( '<span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span>' + esc( label || '' ) );
		} else {
			if ( undefined !== $btn.data( 'qcld-label' ) ) {
				$btn.html( $btn.data( 'qcld-label' ) );
			}
			$btn.prop( 'disabled', false ).removeClass( 'is-busy' );
		}
	};

	ui.card = ( title, body, o ) => {
		o = o || {};
		const head = title || o.actions
			? '<div class="qcld_wpbot_ai_seo-card-header"><div class="qcld_wpbot_ai_seo-card-title-wrap">' +
				( title ? '<h3 class="qcld_wpbot_ai_seo-card-title">' + ( o.htmlTitle ? title : esc( title ) ) + '</h3>' : '' ) +
				'</div>' + ( o.actions ? '<div class="qcld_wpbot_ai_seo-card-actions">' + o.actions + '</div>' : '' ) + '</div>'
			: '';
		return '<div class="qcld_wpbot_ai_seo-card ' + esc( o.className || '' ) + '"' + ( o.id ? ' id="' + esc( o.id ) + '"' : '' ) + '>' + head +
			'<div class="qcld_wpbot_ai_seo-card-body">' + body + '</div></div>';
	};

	ui.empty = ( title, desc ) =>
		'<div class="qcld_wpbot_ai_seo-empty-block"><p class="qcld_wpbot_ai_seo-empty-block__title">' + esc( title ) + '</p>' +
		( desc ? '<p class="qcld_wpbot_ai_seo-empty-block__desc">' + esc( desc ) + '</p>' : '' ) + '</div>';

	const sk = ( h, w, cls ) => '<span class="qcld_wpbot_ai_seo-skeleton ' + ( cls || '' ) + '" style="height:' + h + 'px;width:' + w + '"></span>';
	const SK_W = [ '72%', '90%', '55%', '80%', '64%' ];

	ui.loading = ( variant, text, count ) => {
		let inner = '';
		if ( 'table' === variant ) {
			inner = '<div class="qcld_wpbot_ai_seo-loading__table"><div class="qcld_wpbot_ai_seo-loading__tr qcld_wpbot_ai_seo-loading__tr--head">' +
				SK_W.map( ( w ) => sk( 12, w ) ).join( '' ) + '</div>';
			for ( let r = 0; r < ( count || 5 ); r++ ) {
				inner += '<div class="qcld_wpbot_ai_seo-loading__tr">' + SK_W.map( ( w, c ) => sk( 13, SK_W[ ( c + r ) % SK_W.length ] ) ).join( '' ) + '</div>';
			}
			inner += '</div>';
		} else if ( 'form' === variant ) {
			inner = '<div class="qcld_wpbot_ai_seo-loading__form">';
			for ( let i = 0; i < ( count || 3 ); i++ ) {
				inner += '<div class="qcld_wpbot_ai_seo-loading__field">' + sk( 12, '28%' ) + sk( 36, '100%', 'qcld_wpbot_ai_seo-skeleton--control' ) + '</div>';
			}
			inner += '</div>';
		} else if ( 'dashboard' === variant ) {
			inner = '<div class="qcld_wpbot_ai_seo-loading__stats">';
			for ( let i = 0; i < 4; i++ ) {
				inner += '<div class="qcld_wpbot_ai_seo-loading__stat">' + sk( 28, '58%' ) + sk( 12, '76%' ) + '</div>';
			}
			inner += '</div><div class="qcld_wpbot_ai_seo-loading__panels">';
			for ( let i = 0; i < 2; i++ ) {
				inner += '<div class="qcld_wpbot_ai_seo-loading__card">' + sk( 14, '34%' ) + sk( 180, '100%', 'qcld_wpbot_ai_seo-skeleton--plot' ) + '</div>';
			}
			inner += '</div>';
		} else if ( 'lines' === variant ) {
			inner = '<div class="qcld_wpbot_ai_seo-loading__lines">' + sk( 14, '46%' ) + sk( 16, '86%' ) + sk( 12, '96%' ) + sk( 12, '62%' ) + '</div>';
		} else {
			inner = '<div class="qcld_wpbot_ai_seo-loading__grid">';
			for ( let i = 0; i < ( count || 3 ); i++ ) {
				inner += '<div class="qcld_wpbot_ai_seo-loading__card">' + sk( 14, '46%' ) + sk( 16, '86%' ) + sk( 12, '96%' ) + sk( 12, '62%' ) + '</div>';
			}
			inner += '</div>';
		}
		return '<div class="qcld_wpbot_ai_seo-loading qcld_wpbot_ai_seo-loading--' + esc( variant || 'cards' ) + '" role="status" aria-busy="true" aria-live="polite">' +
			'<span class="screen-reader-text">' + esc( text || __( 'Loading…' ) ) + '</span>' + inner + '</div>';
	};

	ui.notice = ( type, message ) =>
		'<div class="qcld_wpbot_ai_seo-notice qcld_wpbot_ai_seo-notice-' + esc( type ) + '"><p>' + esc( message ) + '</p>' +
		'<div class="qcld_wpbot_ai_seo-notice-actions"><button type="button" class="qcld_wpbot_ai_seo-notice-dismiss" aria-label="' + esc( __( 'Dismiss' ) ) + '">×</button></div></div>';

	/* Form fields — every input carries data-field="name". */

	let uid = 0;
	const nextId = () => 'qcld-wpbot-ai-seo-f' + ( ++uid );

	const wrapField = ( id, label, control, o ) =>
		'<div class="qcld_wpbot_ai_seo-field' + ( o.className ? ' ' + esc( o.className ) : '' ) + '"' + ( o.wrapAttr || '' ) + '>' +
		( label ? '<label class="qcld_wpbot_ai_seo-field__label" for="' + id + '">' + ( o.htmlLabel ? label : esc( label ) ) + '</label>' : '' ) +
		control +
		( o.help ? '<p class="qcld_wpbot_ai_seo-field__help">' + ( o.htmlHelp ? o.help : esc( o.help ) ) + '</p>' : '' ) +
		( o.error !== undefined ? '<span class="qcld_wpbot_ai_seo-field-error" data-error-for="' + esc( o.field ) + '" hidden></span>' : '' ) +
		'</div>';

	ui.text = ( field, label, value, o ) => {
		o = o || {};
		const id = nextId();
		const control = '<input id="' + id + '" class="qcld_wpbot_ai_seo-input' + ( o.inputClass ? ' ' + esc( o.inputClass ) : '' ) + '" type="' + esc( o.type || 'text' ) + '" data-field="' + esc( field ) + '" value="' + esc( value ) + '"' +
			( o.placeholder ? ' placeholder="' + esc( o.placeholder ) + '"' : '' ) +
			( o.autocomplete ? ' autocomplete="' + esc( o.autocomplete ) + '"' : '' ) +
			( o.min !== undefined ? ' min="' + esc( o.min ) + '"' : '' ) +
			( o.max !== undefined ? ' max="' + esc( o.max ) + '"' : '' ) +
			( o.step !== undefined ? ' step="' + esc( o.step ) + '"' : '' ) +
			( o.disabled ? ' disabled' : '' ) + '>';
		return wrapField( id, label, control, Object.assign( { field }, o ) );
	};

	ui.textarea = ( field, label, value, o ) => {
		o = o || {};
		const id = nextId();
		const control = '<textarea id="' + id + '" class="qcld_wpbot_ai_seo-textarea" data-field="' + esc( field ) + '" rows="' + ( o.rows || 3 ) + '"' +
			( o.placeholder ? ' placeholder="' + esc( o.placeholder ) + '"' : '' ) + '>' + esc( value ) + '</textarea>';
		return wrapField( id, label, control, Object.assign( { field }, o ) );
	};

	ui.select = ( field, label, value, options, o ) => {
		o = o || {};
		const id = nextId();
		const control = '<select id="' + id + '" class="qcld_wpbot_ai_seo-select" data-field="' + esc( field ) + '"' + ( o.disabled ? ' disabled' : '' ) + '>' +
			options.map( ( opt ) => '<option value="' + esc( opt.value ) + '"' + ( String( opt.value ) === String( value ) ? ' selected' : '' ) + '>' + esc( opt.label ) + '</option>' ).join( '' ) +
			'</select>';
		return wrapField( id, label, control, Object.assign( { field }, o ) );
	};

	ui.toggle = ( field, label, checked, o ) => {
		o = o || {};
		const id = nextId();
		return '<div class="qcld_wpbot_ai_seo-toggle' + ( o.disabled ? ' is-disabled' : '' ) + '">' +
			'<label class="qcld_wpbot_ai_seo-toggle__row" for="' + id + '">' +
			'<input id="' + id + '" type="checkbox" role="switch" data-field="' + esc( field ) + '"' + ( checked ? ' checked' : '' ) + ( o.disabled ? ' disabled' : '' ) + '>' +
			'<span class="qcld_wpbot_ai_seo-toggle__track" aria-hidden="true"><span class="qcld_wpbot_ai_seo-toggle__thumb"></span></span>' +
			'<span class="qcld_wpbot_ai_seo-toggle__label">' + ( o.htmlLabel ? label : esc( label ) ) + '</span></label>' +
			( o.help ? '<p class="qcld_wpbot_ai_seo-field__help">' + esc( o.help ) + '</p>' : '' ) + '</div>';
	};

	ui.checkbox = ( label, checked, attrs ) =>
		'<label class="qcld_wpbot_ai_seo-check"><input type="checkbox"' + ( checked ? ' checked' : '' ) +
		Object.keys( attrs || {} ).map( ( k ) => ' ' + esc( k ) + '="' + esc( attrs[ k ] ) + '"' ).join( '' ) + '>' +
		( label ? '<span>' + esc( label ) + '</span>' : '' ) + '</label>';

	/** PRO badge / label / upgrade button (shown only while the Pro add-on is not active). */
	ui.proBadge = () => ( cfg.hasPro ? '' : '<span class="qcld_wpbot_ai_seo-pro-badge">' + esc( __( 'PRO' ) ) + '</span>' );
	ui.proLabel = ( text ) => '<span class="qcld_wpbot_ai_seo-pro-label"><span>' + esc( text ) + '</span>' + ui.proBadge() + '</span>';
	ui.upgradeBtn = ( text ) =>
		cfg.hasPro ? '' : '<a class="qcld_wpbot_ai_seo-pro-upgrade-btn qcld_wpbot_ai_seo-pro-upgrade-btn--small" href="' + esc( cfg.proUrl || '#' ) + '" target="_blank" rel="noopener noreferrer">' + esc( text || __( 'Upgrade Pro' ) ) + '</a>';

	ui.aiNotice = () => {
		if ( cfg.aiConfigured ) {
			return '';
		}
		const paused = cfg.aiPaused || [];
		if ( ! paused.length ) {
			return '<p class="qcld_wpbot_ai_seo-ai-notice">' + esc( __( '⚠ AI features are disabled. Please configure an AI provider API key in' ) ) +
				' <a href="' + esc( cfg.aiSettingsUrl ) + '">' + esc( __( 'Settings' ) ) + '</a>.</p>';
		}
		// Keys exist, but every provider is paused after an error: say which and why.
		return '<div class="qcld_wpbot_ai_seo-ai-notice qcld_wpbot_ai_seo-ai-notice--paused" role="status">' +
			'<p>' + esc( __( '⚠ AI features are paused because every AI provider returned an error:' ) ) + '</p><ul>' +
			paused.map( ( p ) =>
				'<li><strong>' + esc( p.name ) + '</strong> ' + esc( p.label ) +
				( p.retry_at ? ' — ' + esc( sprintf( __( 'retried automatically after %s' ), p.retry_at ) ) : '' ) +
				( p.error ? '<br><span class="qcld_wpbot_ai_seo-ai-notice__error">' + esc( p.error ) + '</span>' : '' ) + '</li>'
			).join( '' ) + '</ul>' +
			'<p>' + esc( __( 'Fix the key in' ) ) + ' <a href="' + esc( cfg.aiSettingsUrl ) + '">' + esc( __( 'AI Settings' ) ) + '</a> ' +
			esc( __( '(saving resets the status), or retry now:' ) ) + ' ' +
			'<button type="button" class="qcld_wpbot_ai_seo-btn qcld_wpbot_ai_seo-btn--secondary qcld_wpbot_ai_seo-btn--small" data-ai-reset>' + esc( __( 'Reset provider status' ) ) + '</button></p>' +
			'</div>';
	};

	$( document ).on( 'click', '[data-ai-reset]', function () {
		const $b = $( this ).prop( 'disabled', true );
		api( 'ai_provider_reset', {} )
			.then( ( res ) => {
				toast( ( res && res.message ) || __( 'AI provider status reset.' ) );
				// Reload so every screen picks up the new AI state.
				window.setTimeout( () => window.location.reload(), 800 );
			} )
			.catch( ( e ) => {
				toast( ( e && e.message ) || __( 'Could not reset provider status.' ), 'error' );
				$b.prop( 'disabled', false );
			} );
	} );

	/* ── Token field (keywords / tags) ───────────────── */

	/**
	 * Chip input with suggestions.
	 *
	 * @param {jQuery}   $mount    Container.
	 * @param {Object}   o         label, values, suggestions(), placeholder, onChange(values).
	 */
	ui.tokenField = ( $mount, o ) => {
		const id = nextId();
		let values = ( o.values || [] ).slice();
		$mount.html(
			'<div class="qcld_wpbot_ai_seo-field qcld_wpbot_ai_seo-tokens">' +
				( o.label ? '<label class="qcld_wpbot_ai_seo-field__label" for="' + id + '">' + esc( o.label ) + '</label>' : '' ) +
				'<div class="qcld_wpbot_ai_seo-tokens__box"><span class="qcld_wpbot_ai_seo-tokens__chips"></span>' +
				'<input id="' + id + '" type="text" class="qcld_wpbot_ai_seo-tokens__input" autocomplete="off" placeholder="' + esc( o.placeholder || '' ) + '"></div>' +
				'<ul class="qcld_wpbot_ai_seo-tokens__suggest" role="listbox" hidden></ul>' +
				( o.help ? '<p class="qcld_wpbot_ai_seo-field__help">' + esc( o.help ) + '</p>' : '' ) +
				'</div>'
		);
		const $chips = $mount.find( '.qcld_wpbot_ai_seo-tokens__chips' );
		const $input = $mount.find( '.qcld_wpbot_ai_seo-tokens__input' );
		const $list = $mount.find( '.qcld_wpbot_ai_seo-tokens__suggest' );

		const render = () => {
			$chips.html(
				values.map( ( v, i ) =>
					'<span class="qcld_wpbot_ai_seo-token">' + esc( v ) +
					'<button type="button" class="qcld_wpbot_ai_seo-token__x" data-i="' + i + '" aria-label="' + esc( sprintf( __( 'Remove %s' ), v ) ) + '">×</button></span>'
				).join( '' )
			);
		};
		const emit = () => {
			render();
			if ( o.onChange ) {
				o.onChange( values.slice() );
			}
		};
		const add = ( raw ) => {
			String( raw ).split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ).forEach( ( v ) => {
				if ( values.indexOf( v ) === -1 ) {
					values.push( v );
				}
			} );
			$input.val( '' );
			$list.attr( 'hidden', true );
			emit();
		};
		const suggest = () => {
			const all = ( o.suggestions ? o.suggestions() : [] ).filter( ( s ) => values.indexOf( s ) === -1 );
			const q = $input.val().trim().toLowerCase();
			const list = ( q ? all.filter( ( s ) => s.toLowerCase().indexOf( q ) !== -1 ) : all ).slice( 0, 12 );
			if ( ! list.length ) {
				$list.attr( 'hidden', true ).empty();
				return;
			}
			$list.html( list.map( ( s ) => '<li role="option" tabindex="-1" data-v="' + esc( s ) + '">' + esc( s ) + '</li>' ).join( '' ) ).removeAttr( 'hidden' );
		};

		$chips.on( 'click', '.qcld_wpbot_ai_seo-token__x', function () {
			values.splice( Number( $( this ).data( 'i' ) ), 1 );
			emit();
		} );
		$input.on( 'keydown', ( e ) => {
			if ( 'Enter' === e.key || ',' === e.key ) {
				e.preventDefault();
				add( $input.val() );
			} else if ( 'Backspace' === e.key && ! $input.val() && values.length ) {
				values.pop();
				emit();
			}
		} );
		$input.on( 'input focus', suggest );
		$input.on( 'blur', () => window.setTimeout( () => {
			if ( $input.val().trim() ) {
				add( $input.val() );
			}
			$list.attr( 'hidden', true );
		}, 150 ) );
		$list.on( 'mousedown', 'li', function ( e ) {
			e.preventDefault();
			add( String( $( this ).data( 'v' ) ) );
			$input.trigger( 'focus' );
		} );
		$mount.find( '.qcld_wpbot_ai_seo-tokens__box' ).on( 'click', ( e ) => {
			if ( ! $( e.target ).is( 'button' ) ) {
				$input.trigger( 'focus' );
			}
		} );
		render();
		return {
			set( v ) {
				values = ( v || [] ).slice();
				render();
			},
			get: () => values.slice(),
		};
	};

	/* ── Small charts (SVG / CSS, no libraries) ─────── */

	const charts = {};

	/** Half-circle gauge (0–max). */
	charts.arc = ( value, o ) => {
		o = o || {};
		const size = o.size || 148;
		const stroke = o.stroke || 10;
		const max = o.max > 0 ? o.max : 100;
		const ratio = Math.min( Math.max( Number( value ) / max, 0 ), 1 );
		const r = ( size - stroke ) / 2;
		const cy = size / 2;
		const h = cy + stroke / 2;
		const len = Math.PI * r;
		const d = 'M ' + stroke / 2 + ' ' + cy + ' A ' + r + ' ' + r + ' 0 0 1 ' + ( size - stroke / 2 ) + ' ' + cy;
		return '<div class="qcld_wpbot_ai_seo-arc" style="width:' + size + 'px">' +
			'<svg class="qcld_wpbot_ai_seo-arc__svg" width="' + size + '" height="' + h + '" viewBox="0 0 ' + size + ' ' + h + '" aria-hidden="true" focusable="false">' +
			'<path class="qcld_wpbot_ai_seo-arc__track" d="' + d + '" fill="none" stroke-width="' + stroke + '" stroke-linecap="round"></path>' +
			'<path class="qcld_wpbot_ai_seo-arc__value" d="' + d + '" fill="none" stroke-width="' + stroke + '" stroke-linecap="' + ( ratio > 0 ? 'round' : 'butt' ) + '"' +
			' stroke-dasharray="' + len + '" stroke-dashoffset="' + len + '" data-offset="' + len * ( 1 - ratio ) + '"></path></svg>' +
			'<div class="qcld_wpbot_ai_seo-arc__center">' + ( o.center || '' ) + '</div></div>';
	};

	/** Animate any gauges inside $root. */
	charts.animate = ( $root ) => {
		window.requestAnimationFrame( () => {
			$root.find( '.qcld_wpbot_ai_seo-arc__value' ).each( function () {
				this.setAttribute( 'stroke-dashoffset', this.getAttribute( 'data-offset' ) );
			} );
		} );
	};

	/** Horizontal rank bars. rows: [{label,count,percent,color}] */
	charts.rankbar = ( rows, extraClass ) =>
		'<ul class="qcld_wpbot_ai_seo-rankbar ' + esc( extraClass || '' ) + '">' +
		rows.map( ( r ) =>
			'<li class="qcld_wpbot_ai_seo-rankbar__row"><span class="qcld_wpbot_ai_seo-rankbar__label"><span class="qcld_wpbot_ai_seo-rankbar__dot" style="background:' + esc( r.color ) + '"></span>' + esc( r.label ) + '</span>' +
			'<span class="qcld_wpbot_ai_seo-rankbar__track"><span class="qcld_wpbot_ai_seo-rankbar__fill" style="width:' + Math.max( r.percent, r.percent > 0 ? 2 : 0 ) + '%;background:' + esc( r.color ) + '"></span></span>' +
			'<span class="qcld_wpbot_ai_seo-rankbar__count">' + num( r.count ) + '</span></li>'
		).join( '' ) + '</ul>';

	/**
	 * Bars (series a) + line (series b) over labelled days.
	 *
	 * @param {Array}  data [{label, a, b}]
	 * @param {Object} o    aName, bName, aColor, bColor, height
	 */
	charts.barLine = ( data, o ) => {
		const W = 640;
		const H = o.height || 260;
		const pad = { t: 10, r: 10, b: 26, l: 34 };
		const iw = W - pad.l - pad.r;
		const ih = H - pad.t - pad.b;
		const maxV = Math.max( 1, ...data.map( ( d ) => Math.max( d.a, d.b ) ) );
		const step = Math.max( 1, Math.ceil( maxV / 4 ) );
		const top = step * Math.ceil( maxV / step );
		const y = ( v ) => pad.t + ih - ( v / top ) * ih;
		const slot = iw / data.length;
		const bw = Math.min( 28, slot * 0.6 );
		const every = Math.max( 1, Math.ceil( data.length / 10 ) );
		let grid = '';
		for ( let v = 0; v <= top; v += step ) {
			grid += '<line x1="' + pad.l + '" x2="' + ( W - pad.r ) + '" y1="' + y( v ) + '" y2="' + y( v ) + '" class="qcld_wpbot_ai_seo-chart__grid"></line>' +
				'<text x="' + ( pad.l - 6 ) + '" y="' + ( y( v ) + 4 ) + '" text-anchor="end" class="qcld_wpbot_ai_seo-chart__tick">' + v + '</text>';
		}
		let bars = '';
		let labels = '';
		const pts = [];
		data.forEach( ( d, i ) => {
			const cx = pad.l + slot * i + slot / 2;
			const bh = ( d.a / top ) * ih;
			if ( d.a > 0 ) {
				bars += '<rect x="' + ( cx - bw / 2 ) + '" y="' + ( pad.t + ih - bh ) + '" width="' + bw + '" height="' + bh + '" rx="3" fill="' + esc( o.aColor ) + '"><title>' +
					esc( d.label + ' — ' + o.aName + ': ' + d.a + ', ' + o.bName + ': ' + d.b ) + '</title></rect>';
			}
			pts.push( [ cx, y( d.b ) ] );
			if ( i % every === 0 ) {
				labels += '<text x="' + cx + '" y="' + ( H - 8 ) + '" text-anchor="middle" class="qcld_wpbot_ai_seo-chart__tick">' + esc( d.label ) + '</text>';
			}
		} );
		const line = '<polyline fill="none" stroke="' + esc( o.bColor ) + '" stroke-width="2" stroke-linejoin="round" points="' + pts.map( ( p ) => p.join( ',' ) ).join( ' ' ) + '"></polyline>';
		const dots = data.length <= 31 ? pts.map( ( p, i ) => '<circle cx="' + p[ 0 ] + '" cy="' + p[ 1 ] + '" r="2.5" fill="' + esc( o.bColor ) + '"><title>' + esc( data[ i ].label + ' — ' + o.bName + ': ' + data[ i ].b ) + '</title></circle>' ).join( '' ) : '';
		return '<div class="qcld_wpbot_ai_seo-chart"><svg viewBox="0 0 ' + W + ' ' + H + '" class="qcld_wpbot_ai_seo-chart__svg" role="img" aria-label="' + esc( o.aName + ' / ' + o.bName ) + '">' +
			grid + bars + line + dots + labels + '</svg>' +
			'<div class="qcld_wpbot_ai_seo-chart__legend"><span><i style="background:' + esc( o.aColor ) + '"></i>' + esc( o.aName ) + '</span>' +
			'<span><i style="background:' + esc( o.bColor ) + '"></i>' + esc( o.bName ) + '</span></div></div>';
	};

	/* ── App shell + hash router ─────────────────────── */

	/**
	 * Mount an app.
	 *
	 * @param {Object} o el, nav [{key,label,icon,badge}], views {key: fn($main, params, app)}, defaultView.
	 */
	const createApp = ( o ) => {
		const $root = $( o.el );
		const app = { cfg, params: {} };

		const parse = () => {
			const hash = window.location.hash.replace( '#', '' );
			if ( ! hash ) {
				return { key: o.defaultView, params: {} };
			}
			const parts = hash.split( '/' );
			const id = parts[ 1 ];
			return { key: parts[ 0 ], params: id ? { id: parseInt( id, 10 ) || id } : {} };
		};

		$root.html(
			'<div class="qcld_wpbot_ai_seo-app-layout" data-module="' + esc( o.module ) + '">' +
				'<div class="qcld_wpbot_ai_seo-body qcld_wpbot_ai_seo-body-with-sidebar">' +
				'<aside class="qcld_wpbot_ai_seo-internal-sidebar"><nav class="qcld_wpbot_ai_seo-sidebar-nav" aria-label="' + esc( __( 'Module navigation' ) ) + '"><ul class="qcld_wpbot_ai_seo-cg-tabnav">' +
				o.nav.map( ( n ) =>
					'<li><button type="button" class="qcld_wpbot_ai_seo-sidebar-item" data-nav="' + esc( n.key ) + '">' +
					'<span class="qcld_wpbot_ai_seo-sidebar-item-icon"><span class="dashicons ' + esc( n.icon ) + '" aria-hidden="true"></span></span>' +
					'<span class="qcld_wpbot_ai_seo-sidebar-item-label">' + esc( n.label ) + '</span>' +
					( n.badge ? '<span class="qcld_wpbot_ai_seo-sidebar-badge">' + esc( n.badge ) + '</span>' : '' ) +
					'</button></li>'
				).join( '' ) +
				'</ul></nav>' +
				( o.navFooter ? '<div class="qcld_wpbot_ai_seo-sidebar-footer">' + o.navFooter + '</div>' : '' ) +
				'</aside>' +
				'<main class="qcld_wpbot_ai_seo-main-content"></main></div>' +
				'</div>'
		);

		const $main = $root.find( '.qcld_wpbot_ai_seo-main-content' );
		let token = 0;

		const render = () => {
			const r = parse();
			const view = o.views[ r.key ] ? r.key : o.defaultView;
			const navKey = o.navFor ? o.navFor( view ) : view;
			$root.find( '[data-nav]' ).each( function () {
				const active = $( this ).data( 'nav' ) === navKey;
				$( this ).toggleClass( 'qcld_wpbot_ai_seo-sidebar-active', active ).attr( 'aria-current', active ? 'page' : null );
			} );
			token++;
			app.token = token;
			app.params = r.params;
			slow.stop();
			$( document ).trigger( 'qcld:leave' );
			$main.off().empty().removeClass( 'animate__animated animate__fadeIn animate__faster' );
			void $main[ 0 ].offsetWidth;
			$main.addClass( 'animate__animated animate__fadeIn animate__faster' );
			o.views[ view ]( $main, r.params, app );
		};

		/** Is this render still the current one (guards late AJAX responses). */
		app.alive = ( t ) => t === token;

		app.go = ( key, params ) => {
			params = params || {};
			const hash = params.id ? key + '/' + params.id : key;
			if ( window.location.hash.replace( '#', '' ) === hash ) {
				render();
			} else {
				window.location.hash = hash;
			}
		};

		$root.on( 'click', '[data-nav]', function () {
			app.go( String( $( this ).data( 'nav' ) ) );
		} );
		$( window ).on( 'hashchange', render );
		$( document ).on( 'click', '.qcld_wpbot_ai_seo-notice-dismiss', function () {
			$( this ).closest( '.qcld_wpbot_ai_seo-notice' ).remove();
		} );
		render();
		return app;
	};

	/**
	 * Add-on extension points.
	 *
	 * The free module ships no Pro features. Where one would appear, the UI
	 * shows an "available in Pro" notice, unless an add-on (WPBot AI SEO Pro)
	 * registered its own implementation here before the app boots.
	 *
	 * content.editor: hooks for the article editor. Each receives the editor
	 *   context object (state, renderers, helpers):
	 *   - fields, toggles, genActions, contentActions: arrays of fn( ctx ) => HTML
	 *   - imagePanel: fn( ctx ) => HTML for the "AI Generate" image tab
	 *   - actions: { [data-act]: fn( ctx ) } click handlers
	 *   - generateData, payload: arrays of fn( data, ctx ) that may add fields
	 *   - load: arrays of fn( ctx ) => Promise run before the editor renders
	 * content.views: { [route]: view } replaces a route's view (e.g. brand-voices)
	 * content.canCreatePresets / content.settingsToggles: capability + toggle list
	 * seo.*: see the SEO screens.
	 */
	const ext = {
		content: {
			editor: { fields: [], toggles: [], genActions: [], contentActions: [], imagePanel: null, actions: {}, generateData: [], payload: [], load: [] },
			views: {},
			canCreatePresets: false,
			settingsToggles: [],
		},
		seo: { countryField: null, countryTags: null, countryValue: null, bindCountry: null, rankActions: null },
	};

	/** An "available in Pro" control: disabled button + PRO badge + upgrade link. */
	ui.proUpsell = ( label, o ) =>
		'<span class="qcld_wpbot_ai_seo-pro-inline-action">' +
		ui.btn( label, Object.assign( { disabled: true, title: __( 'Available in Pro' ) }, o || {} ) ) + ui.proBadge() + ui.upgradeBtn() + '</span>';

	window.QcldWpbotAiSeo = {
		ext,
		cfg,
		__,
		sprintf,
		esc,
		num,
		api,
		slow,
		toast,
		modal,
		confirmDialog,
		ui,
		charts,
		createApp,
	};
}( jQuery, window, document ) );
