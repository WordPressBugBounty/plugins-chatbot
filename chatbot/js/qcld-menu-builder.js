/**
 * Start Menu > Items builder (Start Menu reorganization SM2).
 *
 * Works on the existing builder (Active menu ← Available menu items, drag and drop): its HTML is
 * still what is saved (`qc_wpbot_menu_order[locale]`), and the chatbot module turns it into items.
 *
 * - Select an item in the Active menu to edit it: label (custom items; built-in labels come from the
 *   Language Center), icon, group, hide, move up or down, remove. The controls live in a panel next
 *   to the list, never inside the saved HTML.
 * - Badges: hidden items and built-in items whose feature is switched off.
 * - Remove: each Active menu item has a − button (and Delete on a selected item); the buttons live
 *   only on the screen and are stripped from the saved HTML (owner, 25 November 2026).
 * - Add picker: search the available items and add one with a click or Enter.
 * - Preview: the first top-level buttons (Start Menu > Behaviour) and "More options".
 */
( function ( $ ) {
	'use strict';

	var i18n = window.qcldMenuBuilder || {};
	var t = function ( key, fallback ) {
		return i18n[ key ] || fallback;
	};

	$( function () {
		var $area = $( '#qc_menu_area' );
		if ( ! $area.length ) {
			return;
		}
		// WPBot Pro wraps the builder in .qcld_wpbot_startmenu_area with a .qc_wpbot_menu_order field;
		// WPBot Free in .qc_menu_setup_area with #qc_wpbot_menu_order.
		var $box = $area.closest( '.qcld_wpbot_startmenu_area' );
		if ( ! $box.length ) {
			$box = $area.closest( '.qc_menu_setup_area' );
		}
		var $order = $box.find( '.qc_wpbot_menu_order' );
		if ( ! $order.length ) {
			$order = $( '#qc_wpbot_menu_order' );
		}
		var $list = $box.find( '.qc_menu_list_container' );
		var selected = null;

		$box.addClass( 'qcld-mb' );

		// Editor panel, search and preview.
		var $editor = $(
			'<div class="qcld-mb-editor" aria-live="polite">' +
				'<h3>' + t( 'editTitle', 'Edit the selected item' ) + '</h3>' +
				'<p class="qcld-mb-empty">' + t( 'selectHint', 'Select an item in the Active menu to edit it.' ) + '</p>' +
				'<div class="qcld-mb-fields" hidden>' +
					'<p class="qcld-mb-status"></p>' +
					'<label>' + t( 'label', 'Label' ) + ' <input type="text" class="qcld-mb-label"></label>' +
					'<p class="qcld-mb-builtin-note" hidden>' + t( 'builtinNote', 'Built-in button: change its label in the Language Center.' ) + '</p>' +
					'<label>' + t( 'icon', 'Icon (an emoji, optional)' ) + ' <input type="text" class="qcld-mb-icon" maxlength="4"></label>' +
					'<label>' + t( 'group', 'Group (optional)' ) + ' <input type="text" class="qcld-mb-group" list="qcld-mb-groups"></label>' +
					'<datalist id="qcld-mb-groups"></datalist>' +
					'<label class="qcld-mb-check"><input type="checkbox" class="qcld-mb-hidden"> ' + t( 'hide', 'Hide in the chat (kept here)' ) + '</label>' +
					'<div class="qcld-mb-actions">' +
						'<button type="button" class="button qcld-mb-up">' + t( 'up', 'Move up' ) + '</button> ' +
						'<button type="button" class="button qcld-mb-down">' + t( 'down', 'Move down' ) + '</button> ' +
						'<button type="button" class="button qcld-mb-remove">' + t( 'remove', 'Remove' ) + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);
		$box.find( '.qc_menu_area_box' ).after( $editor );
		// WPBot Free's chat shows neither icons nor groups: no fields for them there.
		if ( i18n.simple ) {
			$editor.find( '.qcld-mb-icon, .qcld-mb-group' ).closest( 'label' ).remove();
		}

		var $search = $( '<input type="search" class="qcld-mb-search">' ).attr( 'placeholder', t( 'search', 'Search items to add' ) ).attr( 'aria-label', t( 'search', 'Search items to add' ) );
		$list.before( $search );

		var $preview = $( '<div class="qcld-mb-preview"><h3>' + t( 'previewTitle', 'Preview in the chat' ) + '</h3><div class="qcld-mb-preview-row"></div><p class="qcld-mb-preview-help"></p></div>' );
		$editor.after( $preview );

		function items() {
			return $area.children( 'span' );
		}

		// The saved HTML: the spans without the builder's selection and keyboard attributes.
		function html() {
			var $copy = $area.clone();
			$copy.find( '.qcld-mb-x' ).remove();
			$copy.children( 'span' ).removeClass( 'qcld-mb-selected' ).removeAttr( 'tabindex role aria-pressed' );
			return $copy.html().trim();
		}

		function save() {
			$order.val( html() );
			preview();
		}

		function topCount() {
			var n = parseInt( $( 'input[name="qcld_wpbot_menu_behaviour[top_count]"]' ).val(), 10 );
			return n >= 2 ? n : parseInt( i18n.top, 10 ) || 4;
		}

		function preview() {
			var $row = $preview.find( '.qcld-mb-preview-row' ).empty();
			var shown = items().filter( function () {
				return '1' !== $( this ).attr( 'data-qcld-hidden' ) && '1' !== $( this ).attr( 'data-qcld-off' );
			} );
			var top = topCount();
			var limit = shown.length > top + 1 ? top : shown.length;
			shown.slice( 0, limit ).each( function () {
				var icon = $( this ).attr( 'data-qcld-icon' );
				// data-qa-skip: the admin theme leaves the chip's own colours alone.
				$( '<span class="qcld-mb-chip" data-qa-skip=""></span>' ).text( ( icon ? icon + ' ' : '' ) + $.trim( $( this ).text() ) ).appendTo( $row );
			} );
			if ( shown.length > limit ) {
				$( '<span class="qcld-mb-chip qcld-mb-more" data-qa-skip=""></span>' ).text( t( 'more', 'More options' ) ).appendTo( $row );
			}
			$preview.find( '.qcld-mb-preview-help' ).text(
				t( 'previewHelp', '%1$s buttons show first; %2$s more wait behind "More options". Hidden and switched-off items are left out.' )
					.replace( '%1$s', String( limit ) ).replace( '%2$s', String( shown.length - limit ) )
			);
			var groups = {};
			items().each( function () {
				var g = $( this ).attr( 'data-qcld-group' );
				if ( g ) {
					groups[ g ] = true;
				}
			} );
			var $dl = $( '#qcld-mb-groups' ).empty();
			$.each( groups, function ( g ) {
				$( '<option>' ).attr( 'value', g ).appendTo( $dl );
			} );
		}

		function mark( $span ) {
			// New items (dropped or added) take part in the builder's hidden flags.
			if ( ! $span.attr( 'data-qcld-id' ) ) {
				$span.attr( 'data-qcld-id', 'new' );
			}
			if ( ! $span.attr( 'data-qcld-hidden' ) ) {
				$span.attr( 'data-qcld-hidden', '0' );
			}
			$span.attr( { tabindex: '0', role: 'button' } );
			removeButton( $span );
		}

		// The − button on an Active menu item (no text of its own: the item's label stays its text).
		function removeButton( $span ) {
			if ( $span.children( '.qcld-mb-x' ).length ) {
				return;
			}
			var name = $.trim( $span.text() );
			$( '<button type="button" class="qcld-mb-x"></button>' )
				.attr( 'aria-label', t( 'removeItem', 'Remove from the menu' ) + ': ' + name )
				.attr( 'title', t( 'removeItem', 'Remove from the menu' ) )
				.appendTo( $span );
		}

		function removeItem( $span ) {
			if ( selected && selected[ 0 ] === $span[ 0 ] ) {
				select( null );
			}
			var $next = $span.next( 'span' ).length ? $span.next( 'span' ) : $span.prev( 'span' );
			$span.remove();
			save();
			if ( $next.length ) {
				$next.trigger( 'focus' );
			}
		}

		function select( span ) {
			items().removeClass( 'qcld-mb-selected' ).attr( 'aria-pressed', 'false' );
			selected = span ? $( span ) : null;
			var $fields = $editor.find( '.qcld-mb-fields' );
			if ( ! selected || ! selected.length ) {
				$fields.prop( 'hidden', true );
				$editor.find( '.qcld-mb-empty' ).prop( 'hidden', false );
				return;
			}
			selected.addClass( 'qcld-mb-selected' ).attr( 'aria-pressed', 'true' );
			$editor.find( '.qcld-mb-empty' ).prop( 'hidden', true );
			$fields.prop( 'hidden', false );
			var builtin = !! selected.attr( 'data-qcld-builtin' );
			$editor.find( '.qcld-mb-label' ).val( $.trim( selected.text() ) ).prop( 'disabled', builtin );
			$editor.find( '.qcld-mb-builtin-note' ).prop( 'hidden', ! builtin );
			$editor.find( '.qcld-mb-icon' ).val( selected.attr( 'data-qcld-icon' ) || '' );
			$editor.find( '.qcld-mb-group' ).val( selected.attr( 'data-qcld-group' ) || '' );
			$editor.find( '.qcld-mb-hidden' ).prop( 'checked', '1' === selected.attr( 'data-qcld-hidden' ) );
			var status = [];
			if ( '1' === selected.attr( 'data-qcld-off' ) ) {
				status.push( t( 'offNote', 'Switched off: this feature is turned off in its settings, so the chat leaves it out.' ) );
			}
			$editor.find( '.qcld-mb-status' ).text( status.join( ' ' ) ).prop( 'hidden', ! status.length );
		}

		function setAttr( name, value ) {
			if ( ! selected ) {
				return;
			}
			if ( value ) {
				selected.attr( name, value );
			} else {
				selected.removeAttr( name );
			}
			save();
		}

		$area.on( 'click', '.qcld-mb-x', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			removeItem( $( this ).closest( 'span' ) );
		} );
		$area.on( 'dblclick', '.qcld-mb-x', function ( e ) {
			e.stopPropagation(); // The old builder removes an item on double click: one removal is enough.
		} );
		$area.on( 'click', '> span', function ( e ) {
			e.preventDefault();
			select( this );
		} );
		$area.on( 'keydown', '> span', function ( e ) {
			if ( e.target !== this ) {
				return; // Keys on the − button act on the button.
			}
			if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				select( this );
			} else if ( 'Delete' === e.key ) {
				e.preventDefault();
				removeItem( $( this ) );
			}
		} );
		$editor.on( 'input', '.qcld-mb-label', function () {
			if ( selected && ! selected.attr( 'data-qcld-builtin' ) && $.trim( this.value ) !== '' ) {
				selected.text( $.trim( this.value ) );
				removeButton( selected );
				save();
			}
		} );
		$editor.on( 'input', '.qcld-mb-icon', function () {
			setAttr( 'data-qcld-icon', $.trim( this.value ) );
		} );
		$editor.on( 'input', '.qcld-mb-group', function () {
			setAttr( 'data-qcld-group', $.trim( this.value ) );
		} );
		$editor.on( 'change', '.qcld-mb-hidden', function () {
			if ( selected ) {
				selected.attr( 'data-qcld-hidden', this.checked ? '1' : '0' );
				save();
			}
		} );
		$editor.on( 'click', '.qcld-mb-up', function () {
			if ( selected && selected.prev( 'span' ).length ) {
				selected.insertBefore( selected.prev( 'span' ) );
				save();
				selected.trigger( 'focus' );
			}
		} );
		$editor.on( 'click', '.qcld-mb-down', function () {
			if ( selected && selected.next( 'span' ).length ) {
				selected.insertAfter( selected.next( 'span' ) );
				save();
				selected.trigger( 'focus' );
			}
		} );
		$editor.on( 'click', '.qcld-mb-remove', function () {
			if ( selected ) {
				removeItem( selected );
			}
		} );

		// Add picker: search, and add with a click or Enter (the same as a double click).
		$list.find( 'li' ).each( function () {
			var $li = $( this );
			if ( ! $li.find( 'span' ).length ) {
				return;
			}
			$( '<button type="button" class="qcld-mb-add"></button>' )
				.attr( 'aria-label', t( 'add', 'Add' ) + ': ' + $.trim( $li.find( 'span' ).first().text() ) )
				.text( '+' )
				.appendTo( $li );
		} );
		$list.on( 'click', '.qcld-mb-add', function ( e ) {
			e.preventDefault();
			$( this ).closest( 'li' ).trigger( 'dblclick' );
		} );
		$search.on( 'input', function () {
			var q = $.trim( this.value ).toLowerCase();
			$list.find( 'li' ).each( function () {
				var text = $.trim( $( this ).find( 'span' ).first().text() ).toLowerCase();
				$( this ).toggle( '' === q || text.indexOf( q ) !== -1 );
			} );
		} );

		// Items added by drag and drop or the picker; the old handlers save the HTML themselves.
		if ( window.MutationObserver ) {
			new window.MutationObserver( function () {
				items().each( function () {
					mark( $( this ) );
				} );
				$order.val( html() );
				preview();
			} ).observe( $area[ 0 ], { childList: true } );
		}
		$( document ).on( 'input change', 'input[name="qcld_wpbot_menu_behaviour[top_count]"]', preview );

		$area.closest( 'form' ).on( 'submit', function () {
			$order.val( html() );
		} );
		$area.on( 'sortupdate', save );

		items().each( function () {
			mark( $( this ) );
		} );
		$order.val( html() );
		preview();

		// Start menu items / Predefined intents tabs: reopen the tab that was open before saving.
		var $tabs = $( '.qcld-sm-tabs' );
		if ( $tabs.length ) {
			var key = 'qcldStartMenuTab';
			try {
				var last = window.sessionStorage.getItem( key );
				if ( last && $tabs.find( 'a[href="' + last + '"]' ).length && $.fn.tab ) {
					$tabs.find( 'a[href="' + last + '"]' ).tab( 'show' );
				}
			} catch ( e ) {}
			$tabs.on( 'click', 'a[data-toggle="tab"]', function () {
				try {
					window.sessionStorage.setItem( key, $( this ).attr( 'href' ) );
				} catch ( e ) {}
			} );
		}
	} );
}( window.jQuery ) );
