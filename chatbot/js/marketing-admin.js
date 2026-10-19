/**
 * WPBot – Marketing page UI enhancer.
 *
 * Loaded only on the Marketing admin page. It does NOT change how the React
 * Marketing app loads or saves data; it only:
 *  - remembers when the top notice is dismissed,
 *  - opens the upgrade dialog when a PRO badge is clicked,
 *  - shows the app's alert() messages in the plugin's SweetAlert2 dialog style,
 *  - adds presentation classes to the Settings screen (option cards,
 *    accordions, tab pills) so css/marketing-admin.css can style them.
 *
 * Dialogs use SweetAlert2 (js/sweetalrt.js), the same library the rest of the
 * plugin admin already uses. Swal handles the × button, overlay click and ESC.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.qcwpbotMarketingUI || {};
	var i18n = cfg.i18n || {};
	var Swal = window.Swal || window.Sweetalert2 || null;
	var NOTICE_KEY = 'qcwpbot_mk_notice_closed';
	var accordionState = {};

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	function escHtml( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function storage( key, value ) {
		try {
			if ( typeof value === 'undefined' ) {
				return window.localStorage.getItem( key );
			}
			window.localStorage.setItem( key, value );
		} catch ( e ) {
			// Storage unavailable (private mode etc.) — ignore.
		}
		return null;
	}

	function swalOptions( opts ) {
		return $.extend( true, {
			showCloseButton: true,
			allowEscapeKey: true,
			allowOutsideClick: true,
			focusConfirm: false,
			reverseButtons: false,
			customClass: { popup: 'qcwpbot-mk-swal' },
			showClass: { popup: 'qcwpbot-mk-swal-in', backdrop: 'swal2-backdrop-show' },
			hideClass: { popup: 'qcwpbot-mk-swal-out', backdrop: 'swal2-backdrop-hide' }
		}, opts );
	}

	function fire( opts ) {
		if ( ! Swal ) {
			return null;
		}
		return Swal.fire( swalOptions( opts ) );
	}

	/* ------------------------------------------------------------------
	 * 1. Notice banner
	 * ------------------------------------------------------------------ */

	function initNotice() {
		var $notice = $( '.qcwpbot-mk-notice' );
		if ( ! $notice.length ) {
			return;
		}
		if ( storage( NOTICE_KEY ) === '1' ) {
			$notice.addClass( 'is-hidden' );
		}
		// WordPress core adds the × (.notice-dismiss) to .notice.is-dismissible and fades the notice out.
		$( document ).on( 'click', '.qcwpbot-mk-notice .notice-dismiss', function () {
			storage( NOTICE_KEY, '1' );
		} );
	}

	/* ------------------------------------------------------------------
	 * 2. Upgrade dialog
	 * ------------------------------------------------------------------ */

	function openUpgrade( featureName ) {
		var intro = featureName
			? ( i18n.upgradeFeature || '%s is a Pro feature.' ).replace( '%s', featureName )
			: ( i18n.upgradeGeneric || 'This is a Pro feature.' );
		var list = '';

		if ( cfg.proFeatures && cfg.proFeatures.length ) {
			list = '<ul class="qcwpbot-mk-swal-list">';
			cfg.proFeatures.forEach( function ( item ) {
				list += '<li><img src="' + escHtml( cfg.checkIcon ) + '" alt="">' + escHtml( item ) + '</li>';
			} );
			list += '</ul>';
		}

		var dialog = fire( {
			title: i18n.upgradeTitle || 'Upgrade To Pro',
			html: '<p>' + escHtml( intro ) + ' ' + escHtml( i18n.upgradeText || '' ) + '</p>' + list,
			showCancelButton: true,
			confirmButtonText: ( i18n.upgradeButton || 'Upgrade to Pro' ) + ' &#8599;',
			cancelButtonText: i18n.maybeLater || 'Maybe Later'
		} );

		if ( ! dialog ) {
			window.open( cfg.proUrl, '_blank', 'noopener' );
			return;
		}
		dialog.then( function ( result ) {
			if ( result.isConfirmed ) {
				window.open( cfg.proUrl, '_blank', 'noopener' );
			}
		} );
	}

	function initProBadges() {
		if ( cfg.hasPro ) {
			return;
		}
		// Capture phase: runs before React's root listener, so clicking the
		// badge itself opens the dialog; the rest of the item works as before.
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target || ! e.target.closest ) {
				return;
			}

			// Locked card (AI tool, import option, export box): the whole card opens the upgrade dialog.
			var locked = e.target.closest( '#qcwpbot-app .qcwpbot-mk-tool.is-locked, #qcwpbot-app .qcwpbot-mk-check.is-locked, #qcwpbot-app .qcwpbot-mk-ie-card.is-locked' );
			if ( locked ) {
				e.preventDefault();
				e.stopPropagation();
				openUpgrade( locked.getAttribute( 'data-qcwpbot-title' ) || '' );
				return;
			}

			var badge = e.target.closest( '#qcwpbot-app .pro-badge' );
			if ( ! badge ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();

			var holder = badge.closest( 'li, button, .components-button' );
			var name = '';
			if ( holder ) {
				var label = holder.querySelector( '.menu-label' );
				name = ( label ? label.textContent : holder.textContent.replace( badge.textContent, '' ) ).trim();
			}
			openUpgrade( name );
		}, true );
	}

	/* ------------------------------------------------------------------
	 * 4. alert() → plugin dialog (queued, never lost)
	 * ------------------------------------------------------------------ */

	function initAlerts() {
		if ( ! Swal ) {
			return;
		}
		var nativeAlert = window.alert;
		var queue = Promise.resolve();

		window.alert = function ( message ) {
			var text = String( message == null ? '' : message );
			var icon = 'info';
			if ( /error|fail|invalid|required|limit|not allowed/i.test( text ) ) {
				icon = 'error';
			} else if ( /success|saved|updated|created|deleted|added|imported|done/i.test( text ) ) {
				icon = 'success';
			}
			queue = queue.then( function () {
				return fire( { icon: icon, text: text, confirmButtonText: i18n.ok || 'OK' } );
			} ).catch( function () {
				nativeAlert.call( window, text );
			} );
		};
	}

	/* ------------------------------------------------------------------
	 * 5. Settings screen presentation (class tagging only)
	 * ------------------------------------------------------------------ */

	function cardMeta( label ) {
		var key = String( label || '' ).trim().toLowerCase();
		var cards = cfg.cards || {};
		return cards[ key ] || { icon: '', desc: '' }; // dashicons-admin-generic
	}

	function enhanceSubtabs( $wrap ) {
		var $card = $wrap.children( 'div' ).first();
		var $bar = $card.children( 'div' ).first();
		if ( $bar.children( 'button' ).length < 2 ) {
			return;
		}
		$bar.addClass( 'qcwpbot-mk-subtabs' );
		$bar.children( 'button' ).each( function () {
			var w = this.style.fontWeight;
			$( this ).toggleClass( 'is-active', w === 'bold' || w === '700' );
		} );
	}

	function enhanceCards( $wrap ) {
		$wrap.find( '.components-toggle-control' ).each( function () {
			var $ctrl = $( this );
			if ( ! $ctrl.hasClass( 'qcwpbot-mk-card' ) ) {
				var meta = cardMeta( $ctrl.find( '.components-toggle-control__label' ).first().text() );
				$ctrl.addClass( 'qcwpbot-mk-card' )
					.attr( 'data-qcwpbot-icon', meta.icon )
					.attr( 'data-qcwpbot-desc', meta.desc );
				$ctrl.parent().addClass( 'qcwpbot-mk-cards-grid' );
			}
			$ctrl.toggleClass( 'is-selected', $ctrl.find( '.components-form-toggle' ).hasClass( 'is-checked' ) );
		} );
	}

	function enhanceAccordions( $wrap ) {
		// Bordered blocks in the Settings screen: <div><div>Title</div><div>…body…</div></div>
		var $content = $wrap.children( 'div' ).first().children( 'div' ).eq( 1 );
		$content.children( 'div' ).children( 'div' ).each( function () {
			var el = this;
			if ( el.classList.contains( 'qcwpbot-mk-accordion' ) ) {
				return;
			}
			var kids = el.children;
			if ( kids.length !== 2 || kids[ 0 ].tagName !== 'DIV' || kids[ 1 ].tagName !== 'DIV' || kids[ 0 ].children.length ) {
				return;
			}
			var title = kids[ 0 ].textContent.trim();
			if ( ! title ) {
				return;
			}
			var key = title.toLowerCase();
			var collapsed = ( cfg.collapsedSections || [] ).indexOf( key ) !== -1;
			var open = Object.prototype.hasOwnProperty.call( accordionState, key ) ? accordionState[ key ] : ! collapsed;
			var $acc = $( el );
			var $head = $( kids[ 0 ] );
			var $body = $( kids[ 1 ] );

			$acc.addClass( 'qcwpbot-mk-accordion' ).toggleClass( 'is-open', open );
			$head.addClass( 'qcwpbot-mk-accordion__head' ).attr( {
				role: 'button',
				tabindex: '0',
				'aria-expanded': open ? 'true' : 'false'
			} ).append( '<span class="qcwpbot-mk-accordion__icon" aria-hidden="true">+</span>' );
			$body.addClass( 'qcwpbot-mk-accordion__body' );
			if ( ! open ) {
				$body.hide();
			}
		} );
	}

	// Put "Go Premium" under the React tab list so the left card is one block,
	// like the General Settings nav. React never re-creates .qcwpbot-sidebar, so the
	// appended node stays; if it ever does, the next pass moves it again.
	function placeNavFooter() {
		var sidebar = document.querySelector( '#qcwpbot-app .qcwpbot-sidebar' );
		var footer = document.querySelector( '.qcwpbot-mk-nav-footer' );
		if ( sidebar && footer && footer.parentNode !== sidebar ) {
			sidebar.appendChild( footer );
		}
	}

	// AI Tools screen: tag the six tool boxes so they render as option cards.
	// Free card:   <div><h3>Title</h3><div>…fields…</div></div>
	// Locked card: <div><div><h3>Title <PRO></h3>…fields…</div><div>PRO · Upgrade Pro</div></div>
	function enhanceAiTools() {
		var wrap = document.querySelector( '#qcwpbot-app .qcwpbot-ai-tools-wrap' );
		if ( ! wrap ) {
			return;
		}
		var head = wrap.children[ 0 ];
		var grid = wrap.children[ 1 ];
		if ( head ) {
			head.classList.add( 'qcwpbot-mk-tools-head' );
		}
		if ( ! grid ) {
			return;
		}
		grid.classList.add( 'qcwpbot-mk-tools-grid' );

		Array.prototype.forEach.call( grid.children, function ( card ) {
			if ( card.classList.contains( 'qcwpbot-mk-tool' ) ) {
				return;
			}
			var first = card.children[ 0 ];
			if ( ! first ) {
				return;
			}
			var isLocked = first.tagName === 'DIV';
			var h3 = isLocked ? first.querySelector( 'h3' ) : first;
			if ( ! h3 || h3.tagName !== 'H3' ) {
				return;
			}
			var badge = h3.querySelector( 'span' );
			var title = ( badge ? h3.textContent.replace( badge.textContent, '' ) : h3.textContent ).trim();
			var meta = ( cfg.tools || {} )[ title.toLowerCase() ] || { icon: '\uf111', desc: '' };

			card.classList.add( 'qcwpbot-mk-tool' );
			card.setAttribute( 'data-qcwpbot-title', title );
			card.setAttribute( 'data-qcwpbot-icon', meta.icon );
			h3.classList.add( 'qcwpbot-mk-tool__title' );
			if ( badge ) {
				badge.classList.add( 'qcwpbot-mk-tool__badge' );
			}

			if ( isLocked ) {
				card.classList.add( 'is-locked' );
				card.setAttribute( 'role', 'button' );
				card.setAttribute( 'tabindex', '0' );
				first.classList.add( 'qcwpbot-mk-tool__body' );
				if ( card.children[ 1 ] ) {
					card.children[ 1 ].classList.add( 'qcwpbot-mk-tool__footer' );
				}
				// Fields in a locked card are not usable; keep them out of the tab order.
				Array.prototype.forEach.call( first.querySelectorAll( 'input, textarea, select, button' ), function ( f ) {
					f.setAttribute( 'tabindex', '-1' );
				} );
			} else if ( card.children[ 1 ] ) {
				card.children[ 1 ].classList.add( 'qcwpbot-mk-tool__body' );
			}

			if ( meta.desc ) {
				h3.setAttribute( 'data-qcwpbot-desc', meta.desc );
			}
		} );
	}

	function proSpans( root ) {
		return Array.prototype.filter.call( root.querySelectorAll( 'span' ), function ( sp ) {
			return sp.children.length === 0 && sp.textContent.trim() === 'PRO';
		} );
	}

	function textWithoutPro( el ) {
		return el.textContent.replace( /\bPRO\b/g, '' ).replace( /\s+/g, ' ' ).trim();
	}

	// Import / Export screen: two boxes; the validation checkboxes become option cards.
	function enhanceImportExport() {
		var wrap = document.querySelector( '#qcwpbot-app .qcwpbot-import-export-wrap' );
		if ( ! wrap ) {
			return;
		}

		Array.prototype.forEach.call( wrap.children, function ( box ) {
			box.classList.add( 'qcwpbot-mk-ie-card' );
			var h3 = box.querySelector( 'h3' );
			if ( h3 ) {
				h3.classList.add( 'qcwpbot-mk-ie-card__title' );
				if ( proSpans( h3 ).length && ! cfg.hasPro ) {
					box.classList.add( 'is-locked' );
					box.setAttribute( 'data-qcwpbot-title', textWithoutPro( h3 ) );
				}
			}
			proSpans( box ).forEach( function ( sp ) {
				sp.classList.add( 'qcwpbot-mk-pro-pill' );
			} );
		} );

		var heading = Array.prototype.find.call( wrap.querySelectorAll( 'h4' ), function ( h ) {
			return /validation/i.test( h.textContent );
		} );
		if ( heading ) {
			heading.parentElement.classList.add( 'qcwpbot-mk-validation' );
			heading.classList.add( 'qcwpbot-mk-validation__title' );
			if ( heading.nextElementSibling ) {
				heading.nextElementSibling.classList.add( 'qcwpbot-mk-check-grid' );
			}
		}

		Array.prototype.forEach.call( wrap.querySelectorAll( '.components-checkbox-control' ), function ( ctrl ) {
			var input = ctrl.querySelector( 'input[type="checkbox"]' );
			if ( ! input ) {
				return;
			}
			if ( ! ctrl.classList.contains( 'qcwpbot-mk-check' ) ) {
				var label = ctrl.querySelector( '.components-checkbox-control__label, label' );
				ctrl.classList.add( 'qcwpbot-mk-check' );
				ctrl.setAttribute( 'data-qcwpbot-title', label ? textWithoutPro( label ) : '' );
			}
			ctrl.classList.toggle( 'is-locked', input.disabled && ! cfg.hasPro );
			ctrl.classList.toggle( 'is-selected', input.checked );
		} );
	}

	// Friendlier tab names in the left menu (labels come from PHP so they can be translated).
	// Only the visible text changes; React keeps the same tab ids and behaviour.
	function relabelNav() {
		var names = cfg.navLabels || {};
		Array.prototype.forEach.call( document.querySelectorAll( '#qcwpbot-app .qcwpbot-sidebar .menu-label' ), function ( el ) {
			var original = el.getAttribute( 'data-qcwpbot-label' ) || el.textContent.trim();
			var next = names[ original ];
			if ( ! el.hasAttribute( 'data-qcwpbot-label' ) ) {
				el.setAttribute( 'data-qcwpbot-label', original );
			}
			if ( next && el.textContent !== next ) {
				el.textContent = next;
				el.closest( 'li' ).setAttribute( 'title', next );
			}
		} );
	}

	function enhance() {
		placeNavFooter();
		relabelNav();
		enhanceAiTools();
		enhanceImportExport();
		var $wrap = $( '#qcwpbot-app .qcwpbot-settings-wrap' );
		if ( ! $wrap.length ) {
			return;
		}
		enhanceSubtabs( $wrap );
		enhanceAccordions( $wrap );
		enhanceCards( $wrap );
	}

	function initSettingsUI() {
		var app = document.getElementById( 'qcwpbot-app' );
		if ( ! app ) {
			return;
		}

		// Import option card: click anywhere toggles its checkbox.
		$( document ).on( 'click', '#qcwpbot-app .qcwpbot-mk-check:not(.is-locked)', function ( e ) {
			if ( $( e.target ).closest( 'input, label' ).length ) {
				return;
			}
			var input = this.querySelector( 'input[type="checkbox"]' );
			if ( input && ! input.disabled ) {
				input.click();
			}
		} );

		$( document ).on( 'change', '#qcwpbot-app .qcwpbot-mk-check input[type="checkbox"]', function () {
			$( this ).closest( '.qcwpbot-mk-check' ).toggleClass( 'is-selected', this.checked );
		} );

		// Whole card toggles its checkbox.
		$( document ).on( 'click', '#qcwpbot-app .qcwpbot-mk-card', function ( e ) {
			if ( $( e.target ).closest( 'input, label, .components-form-toggle' ).length ) {
				return;
			}
			var input = this.querySelector( '.components-form-toggle__input, input[type="checkbox"]' );
			if ( input && ! input.disabled ) {
				input.click();
			}
		} );

		// Locked AI tool card opened from the keyboard.
		$( document ).on( 'keydown', '#qcwpbot-app .qcwpbot-mk-tool.is-locked', function ( e ) {
			if ( e.target !== this || ( e.key !== 'Enter' && e.key !== ' ' ) ) {
				return;
			}
			e.preventDefault();
			openUpgrade( this.getAttribute( 'data-qcwpbot-title' ) || '' );
		} );

		// Accordion open / close.
		$( document ).on( 'click keydown', '#qcwpbot-app .qcwpbot-mk-accordion__head', function ( e ) {
			if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) {
				return;
			}
			e.preventDefault();
			var $head = $( this );
			var $acc = $head.closest( '.qcwpbot-mk-accordion' );
			var open = ! $acc.hasClass( 'is-open' );
			accordionState[ $head.text().replace( /\+\s*$/, '' ).trim().toLowerCase() ] = open;
			var $body = $acc.children( '.qcwpbot-mk-accordion__body' ).stop( true, true );
			$head.attr( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				$acc.addClass( 'is-open' );
				$body.hide().slideDown( 250 );
			} else {
				$body.slideUp( 250, function () {
					$acc.removeClass( 'is-open' );
				} );
			}
		} );

		// React re-renders on tab changes; re-apply classes (idempotent).
		var scheduled = false;
		var observer = new MutationObserver( function () {
			if ( scheduled ) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame( function () {
				scheduled = false;
				enhance();
			} );
		} );
		observer.observe( app, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style' ], characterData: true } );
		enhance();
	}

	/* ------------------------------------------------------------------ */

	$( function () {
		initNotice();
		initProBadges();
		initAlerts();
		initSettingsUI();
	} );
}( jQuery ) );
