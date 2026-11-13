/**
 * REST helpers. Routes are passed as `path`, so WordPress's own root-URL
 * middleware builds the URL for any permalink setting (including plain
 * ?rest_route= URLs). Every call carries the REST nonce; keys never come back.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

export const data = window.qcldWpbotWriter || {};

/** Product names from the host (R8): WPBot, SEO Help, ... */
export const product = data.product || 'WPBot';
export const proProduct =
	data.proProduct ||
	sprintf(
		/* translators: %s: product name, for example WPBot. */
		__( '%s Pro', 'chatbot' ),
		product
	);

apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );

const OUR_ROUTES = /^\/?(wpbot-writer|wpbot-core)\//;

/**
 * The error shown when the server answers with something that is not JSON
 * (a host timeout page, a security filter's page, or a printed PHP
 * warning), naming the status and what the page said.
 *
 * @param {number} status HTTP status.
 * @param {string} text   Raw body.
 * @return {Object} Error in apiFetch's shape.
 */
const notJson = ( status, text ) => {
	const said = String( text || '' )
		.replace( /<(script|style)[\s\S]*?<\/\1>/gi, ' ' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, 160 );
	let cause;
	if ( [ 502, 503, 504, 524 ].includes( status ) ) {
		cause = __(
			'The web host stopped the request before AI answered (a server time limit). Try again; if it keeps happening, ask your host to allow longer requests.',
			'chatbot'
		);
	} else if ( 403 === status || 406 === status ) {
		cause = __(
			'A security filter on the server blocked the request. Ask your host or security plugin to allow requests to /wp-json/wpbot-writer/.',
			'chatbot'
		);
	} else {
		cause = __(
			'The server sent a page instead of an answer, often because a PHP warning or error was printed. Try again; if it keeps happening, check the PHP error log.',
			'chatbot'
		);
	}
	return {
		code: 'invalid_json',
		status,
		message:
			sprintf(
				/* translators: %d: HTTP status code. */
				__(
					'Unexpected reply from the server (HTTP %d).',
					'chatbot'
				),
				status
			) +
			' ' +
			cause +
			( said
				? ' ' +
					sprintf(
						/* translators: %s: start of the page the server sent. */
						__( 'It said: "%s"', 'chatbot' ),
						said
					)
				: '' ),
	};
};

/**
 * Read a response: the parsed body, or a readable "not JSON" error.
 *
 * @param {Response} response Fetch response.
 * @return {Promise<Object>} Parsed body; rejects with notJson().
 */
const readJson = async ( response ) => {
	if ( 204 === response.status ) {
		return null;
	}
	const text = await response.text();
	// WordPress sends a `null` answer as an empty body (for example "no site
	// voice yet"): a success with nothing in it is "nothing", not an error.
	if ( response.ok && '' === text.trim() ) {
		return null;
	}
	try {
		return JSON.parse( text );
	} catch {
		throw notJson( response.status, text );
	}
};

// Once per page, for every bundle that shares wp.apiFetch (Free and Pro).
if ( ! window.qcldWpbotWriterJsonGuard ) {
	window.qcldWpbotWriterJsonGuard = true;
	apiFetch.use( ( options, next ) => {
		if (
			false === options.parse ||
			! OUR_ROUTES.test( options.path || '' )
		) {
			return next( options );
		}
		return next( { ...options, parse: false } ).then(
			readJson,
			( response ) => {
				if ( ! response || 'function' !== typeof response.text ) {
					throw response;
				}
				return readJson( response ).then( ( body ) => {
					throw body;
				} );
			}
		);
	} );
}

/**
 * Call a WPBot Core route.
 *
 * @param {string} path    Path after wpbot-core/v1/.
 * @param {Object} options apiFetch options.
 * @return {Promise<Object>} Response body.
 */
export const core = ( path, options = {} ) =>
	apiFetch( { path: `/wpbot-core/v1/${ path }`, ...options } );

/**
 * Call an AI Writer route.
 *
 * @param {string} path    Path after wpbot-writer/v1/.
 * @param {Object} options apiFetch options.
 * @return {Promise<Object>} Response body.
 */
export const writer = ( path, options = {} ) =>
	apiFetch( { path: `/wpbot-writer/v1/${ path }`, ...options } );

/**
 * Readable message from a failed request.
 *
 * @param {Error|Object} error Error thrown by apiFetch.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	( error && error.message ) || String( error );
