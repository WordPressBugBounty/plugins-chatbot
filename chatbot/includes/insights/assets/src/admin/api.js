/* global wp */
const data = window.qcInsightsAdmin || {};

wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( data.nonce ) );

function url( path, query ) {
	let u = data.restRoot + path;
	if ( query ) {
		const qs = Object.keys( query )
			.filter( ( k ) => query[ k ] !== '' && query[ k ] !== undefined && query[ k ] !== null )
			.map( ( k ) => encodeURIComponent( k ) + '=' + encodeURIComponent( query[ k ] ) )
			.join( '&' );
		if ( qs ) {
			u += ( u.indexOf( '?' ) === -1 ? '?' : '&' ) + qs;
		}
	}
	return u;
}

export const api = {
	get: ( path, query ) => wp.apiFetch( { url: url( path, query ) } ),
	post: ( path, body ) => wp.apiFetch( { url: url( path ), method: 'POST', data: body || {} } ),
	del: ( path ) => wp.apiFetch( { url: url( path ), method: 'DELETE' } ),
};

export const boot = data;

export function go( path ) {
	window.location.hash = '#/' + path;
}
