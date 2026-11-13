/**
 * WPBot error log: sends browser errors of WPBot's own scripts (the chat widget, WPBot's admin
 * screens) to the site's error log. Other scripts' errors are ignored. At most five reports per
 * page; nothing the visitor typed is sent.
 */
( function () {
	var c = window.qcldWpbotErrors;
	if ( ! c || ! c.url || ! window.JSON ) {
		return;
	}
	var sent = {};
	var count = 0;
	function ours( text ) {
		if ( ! text ) {
			return false;
		}
		for ( var i = 0; i < c.prefixes.length; i++ ) {
			if ( String( text ).indexOf( c.prefixes[ i ] ) !== -1 ) {
				return true;
			}
		}
		return false;
	}
	function send( data ) {
		var key = data.message + '|' + data.source + '|' + data.line;
		if ( sent[ key ] || count >= 5 || ( ! ours( data.source ) && ! ours( data.stack ) ) ) {
			return;
		}
		sent[ key ] = true;
		count++;
		data.where = c.where;
		data.page = window.location.pathname;
		var body = JSON.stringify( data );
		try {
			if ( c.nonce && window.fetch ) {
				window.fetch( c.url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': c.nonce }, body: body } );
			} else if ( navigator.sendBeacon ) {
				navigator.sendBeacon( c.url, new Blob( [ body ], { type: 'application/json' } ) );
			} else if ( window.fetch ) {
				window.fetch( c.url, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: body } );
			}
		} catch ( e ) {}
	}
	window.addEventListener( 'error', function ( e ) {
		if ( ! e || ! e.message ) {
			return;
		}
		send( {
			message: String( e.message ).slice( 0, 500 ),
			source: e.filename || '',
			line: e.lineno || 0,
			col: e.colno || 0,
			stack: e.error && e.error.stack ? String( e.error.stack ).slice( 0, 2000 ) : '',
		} );
	} );
	window.addEventListener( 'unhandledrejection', function ( e ) {
		var reason = e && e.reason;
		var stack = reason && reason.stack ? String( reason.stack ) : '';
		send( {
			message: ( 'Unhandled promise rejection: ' + ( reason && reason.message ? reason.message : String( reason ) ) ).slice( 0, 500 ),
			source: '',
			line: 0,
			col: 0,
			stack: stack.slice( 0, 2000 ),
		} );
	} );
} )();
