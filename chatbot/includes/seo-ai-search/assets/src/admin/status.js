/**
 * Connection status shared by screens (loaded once per page view).
 */
import { seo } from './api';

let pending = null;

/**
 * Status of Google, Analytics and the Search Console import.
 *
 * @param {boolean} fresh Reload instead of reusing the last answer.
 * @return {Promise<Object>} Status.
 */
export const loadStatus = ( fresh = false ) => {
	if ( ! pending || fresh ) {
		pending = seo( 'status' );
	}
	return pending;
};
