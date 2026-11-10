/* global wp */
const { __, sprintf } = wp.i18n;

export function duration( seconds ) {
	const s = Math.max( 0, Math.round( seconds || 0 ) );
	const h = Math.floor( s / 3600 );
	const m = Math.floor( ( s % 3600 ) / 60 );
	const sec = s % 60;
	if ( h ) {
		return sprintf( '%dh %02dm', h, m );
	}
	if ( m ) {
		return sprintf( '%dm %02ds', m, sec );
	}
	return sprintf( '%ds', sec );
}

export function dateTime( iso ) {
	if ( ! iso ) {
		return '';
	}
	const d = new Date( iso );
	return d.toLocaleString( undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' } );
}

export function relative( iso ) {
	if ( ! iso ) {
		return '';
	}
	const diff = ( Date.now() - new Date( iso ).getTime() ) / 1000;
	if ( diff < 60 ) {
		return __( 'just now', 'qcin-textdomain' );
	}
	if ( diff < 3600 ) {
		/* translators: %d: minutes */
		return sprintf( __( '%d min ago', 'qcin-textdomain' ), Math.floor( diff / 60 ) );
	}
	if ( diff < 86400 ) {
		/* translators: %d: hours */
		return sprintf( __( '%d h ago', 'qcin-textdomain' ), Math.floor( diff / 3600 ) );
	}
	return dateTime( iso );
}

export function bytes( n ) {
	const v = n || 0;
	if ( v >= 1073741824 ) {
		return ( v / 1073741824 ).toFixed( 1 ) + ' GB';
	}
	if ( v >= 1048576 ) {
		return ( v / 1048576 ).toFixed( 1 ) + ' MB';
	}
	if ( v >= 1024 ) {
		return Math.round( v / 1024 ) + ' KB';
	}
	return v + ' B';
}

export function path( url ) {
	try {
		const u = new URL( url );
		return u.pathname + u.search;
	} catch ( e ) {
		return url || '';
	}
}

export function flag( country ) {
	if ( ! country || country.length !== 2 ) {
		return '';
	}
	return String.fromCodePoint( ...country.toUpperCase().split( '' ).map( ( ch ) => 127397 + ch.charCodeAt( 0 ) ) );
}
