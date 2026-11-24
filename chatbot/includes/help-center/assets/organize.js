/**
 * Help Center → Organize: reorder categories and articles by dragging or with
 * the Move up / Move down buttons. Items move only among their siblings.
 */
( function ( wp ) {
	'use strict';

	var cfg = window.qcldWpbotHelpCenterOrganize || {};
	var __ = wp.i18n.__;
	var status = document.querySelector( '.wpbot-help-organize__status' );
	var dragged = null;

	function say( text ) {
		if ( status ) {
			status.textContent = text;
		}
		if ( wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( text );
		}
	}

	function allowed( list ) {
		return 'terms' === list.dataset.type ? cfg.canTerms : cfg.canArticles;
	}

	function save( list ) {
		var ids = Array.prototype.map.call( list.children, function ( li ) {
			return parseInt( li.dataset.id, 10 );
		} );
		say( __( 'Saving the order…', 'chatbot' ) );
		wp.apiFetch( { path: cfg.path, method: 'POST', data: { type: list.dataset.type, ids: ids } } )
			.then( function () {
				say( __( 'Order saved.', 'chatbot' ) );
			} )
			.catch( function ( error ) {
				say( ( error && error.message ) || __( 'The order could not be saved.', 'chatbot' ) );
			} );
	}

	function move( li, step ) {
		var list = li.parentNode;
		var target = step < 0 ? li.previousElementSibling : li.nextElementSibling;
		if ( ! target ) {
			return;
		}
		list.insertBefore( li, step < 0 ? target : target.nextElementSibling );
		save( list );
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-help-tree' ), function ( list ) {
		if ( ! allowed( list ) ) {
			Array.prototype.forEach.call( list.querySelectorAll( ':scope > li > .wpbot-help-tree__row .wpbot-help-tree__move' ), function ( m ) {
				m.remove();
			} );
			return;
		}
		Array.prototype.forEach.call( list.children, function ( li ) {
			var row = li.querySelector( ':scope > .wpbot-help-tree__row' );
			li.draggable = true;
			li.addEventListener( 'dragstart', function ( e ) {
				e.stopPropagation();
				dragged = li;
				li.classList.add( 'is-dragging' );
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData( 'text/plain', li.dataset.id );
			} );
			li.addEventListener( 'dragend', function () {
				li.classList.remove( 'is-dragging' );
				if ( dragged === li ) {
					dragged = null;
					save( list );
				}
			} );
			li.addEventListener( 'dragover', function ( e ) {
				if ( ! dragged || dragged === li || dragged.parentNode !== list ) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				var box = li.getBoundingClientRect();
				list.insertBefore( dragged, e.clientY < box.top + box.height / 2 ? li : li.nextElementSibling );
			} );
			Array.prototype.forEach.call( row.querySelectorAll( '[data-move]' ), function ( button ) {
				button.addEventListener( 'click', function () {
					move( li, parseInt( button.dataset.move, 10 ) );
					button.focus();
				} );
			} );
		} );
	} );
} )( window.wp );
