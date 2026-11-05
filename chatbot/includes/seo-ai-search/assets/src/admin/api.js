/**
 * REST helpers. Routes go through apiFetch's `path`, so any permalink
 * setting works. Every call carries the REST nonce; secrets never come back.
 */
import apiFetch from '@wordpress/api-fetch';

export const data = window.qcldWpbotSeo || {};

apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );

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
 * Call an SEO & AI Search route.
 *
 * @param {string} path    Path after qcld-wpbot-seo/v1/.
 * @param {Object} options apiFetch options.
 * @return {Promise<Object>} Response body.
 */
export const seo = ( path, options = {} ) =>
	apiFetch( { path: `/qcld-wpbot-seo/v1/${ path }`, ...options } );

/**
 * Readable message from a failed request.
 *
 * @param {Error|Object} error Error thrown by apiFetch.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	( error && error.message ) || String( error );
