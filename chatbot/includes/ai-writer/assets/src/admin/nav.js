/**
 * Moving between AI Writer screens. The WPBot Core shell owns the left menu
 * and the address bar; screens ask it to open another item, optionally
 * handing over an intent (for example a job to continue or a brief).
 */
let pending = null;
let fallback = null;

/**
 * Open a menu item.
 *
 * @param {string} id     Item id (overview, write, improve…).
 * @param {Object} intent Optional data for the screen that opens.
 */
export const navigate = ( id, intent = null ) => {
	pending = intent;
	if ( window.wpbotShell ) {
		window.wpbotShell.go( id );
	} else if ( fallback ) {
		fallback( id );
	}
};

/**
 * The intent handed over by navigate() (read once).
 *
 * @return {Object|null} Intent.
 */
export const takeIntent = () => {
	const intent = pending;
	pending = null;
	return intent;
};

/**
 * Used by the app when no shell is present.
 *
 * @param {Function} callback Opens an item.
 */
export const setFallback = ( callback ) => {
	fallback = callback;
};
