/**
 * WPBot Core admin shell: menu behaviour.
 *
 * Menu items are real links. Before following one, the shell fires a
 * cancelable "wpbot-shell:navigate" event on the shell element (detail: id,
 * url). A single-page app that shows the screen itself calls
 * preventDefault(); the shell then updates the address (so the back button
 * works) and the active item. Otherwise the browser follows the link.
 * The select menu (narrow screens) does the same.
 *
 * window.wpbotShell: go( id ), setActive( id ), setBadge( id, value ).
 */
( function () {
	const shell = document.querySelector( '.qcwpbot-shell' );
	if ( ! shell ) {
		return;
	}
	const select = shell.querySelector( '.qcwpbot-shell__select' );

	function link( id ) {
		return shell.querySelector(
			'.qcwpbot-shell__item[data-item="' + id + '"]'
		);
	}

	function setActive( id ) {
		const links = shell.querySelectorAll( '.qcwpbot-shell__item' );
		for ( let i = 0; i < links.length; i++ ) {
			const on = links[ i ].getAttribute( 'data-item' ) === id;
			links[ i ].classList.toggle( 'is-active', on );
			if ( on ) {
				links[ i ].setAttribute( 'aria-current', 'page' );
			} else {
				links[ i ].removeAttribute( 'aria-current' );
			}
		}
		if ( select ) {
			select.value = id;
		}
	}

	function announce( id, url, cancelable ) {
		let event;
		try {
			event = new window.CustomEvent( 'wpbot-shell:navigate', {
				cancelable,
				detail: { id, url },
			} );
		} catch {
			return false;
		}
		shell.dispatchEvent( event );
		return event.defaultPrevented;
	}

	// Show a screen: the app handles it, or the browser loads the URL.
	function go( id, push ) {
		const item = link( id );
		if ( ! item ) {
			return;
		}
		const url = item.getAttribute( 'href' );
		if ( announce( id, url, true ) ) {
			if ( false !== push && url !== window.location.href ) {
				window.history.pushState( { wpbotShell: id }, '', url );
			}
			setActive( id );
			return;
		}
		window.location.href = url;
	}

	shell.addEventListener( 'click', function ( event ) {
		const item = event.target.closest
			? event.target.closest( '.qcwpbot-shell__item' )
			: null;
		// New tab / window and modified clicks keep the browser's behaviour.
		if (
			! item ||
			event.defaultPrevented ||
			0 !== event.button ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}
		const id = item.getAttribute( 'data-item' );
		if ( announce( id, item.getAttribute( 'href' ), true ) ) {
			event.preventDefault();
			if ( item.getAttribute( 'href' ) !== window.location.href ) {
				window.history.pushState(
					{ wpbotShell: id },
					'',
					item.getAttribute( 'href' )
				);
			}
			setActive( id );
		}
	} );

	if ( select ) {
		select.addEventListener( 'change', function () {
			go( select.value );
		} );
	}

	// Back and forward buttons: show the item whose URL is now open.
	window.addEventListener( 'popstate', function ( event ) {
		let id =
			event.state && event.state.wpbotShell ? event.state.wpbotShell : '';
		if ( ! id ) {
			const links = shell.querySelectorAll( '.qcwpbot-shell__item' );
			for ( let i = 0; i < links.length; i++ ) {
				if ( links[ i ].href === window.location.href ) {
					id = links[ i ].getAttribute( 'data-item' );
				}
			}
		}
		if ( ! id ) {
			window.location.reload();
			return;
		}
		if ( announce( id, window.location.href, true ) ) {
			setActive( id );
		} else {
			window.location.reload();
		}
	} );

	// The first entry knows its item, so going back to it works.
	const active = shell.querySelector( '.qcwpbot-shell__item.is-active' );
	if ( active && window.history.replaceState ) {
		window.history.replaceState(
			{ wpbotShell: active.getAttribute( 'data-item' ) },
			'',
			window.location.href
		);
	}

	window.wpbotShell = {
		go,
		setActive,
		setBadge( id, value ) {
			const item = link( id );
			if ( ! item ) {
				return;
			}
			let badge = item.querySelector( '.qcwpbot-shell__badge' );
			if ( ! value ) {
				if ( badge ) {
					badge.parentNode.removeChild( badge );
				}
				return;
			}
			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = 'qcwpbot-shell__badge';
				item.appendChild( badge );
			}
			badge.textContent = String( value );
		},
	};
} )();
