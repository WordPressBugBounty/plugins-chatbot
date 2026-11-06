/**
 * Shared helpers for the edit-screen tools.
 */
import { select } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import { errorMessage, writer } from '../admin/api';

export const PENDING_META = '_qcld_wpbot_writer_seo_pending';

/**
 * Plain text of an HTML string, parsed in an inert document (no scripts run).
 *
 * @param {string} html HTML.
 * @return {string} Text.
 */
export const toText = ( html ) => {
	const doc = document.implementation.createHTMLDocument( '' );
	doc.body.innerHTML = String( html || '' );
	return ( doc.body.textContent || '' ).replace( /\s+/g, ' ' ).trim();
};

/**
 * The open post as the server needs it: unsaved title and content.
 *
 * @return {{post_id: number, title: string, content: string}} Context.
 */
export const postContext = () => {
	const editor = select( 'core/editor' );
	return {
		post_id: editor.getCurrentPostId() || 0,
		title: editor.getEditedPostAttribute( 'title' ) || '',
		content: editor.getEditedPostContent() || '',
	};
};

/**
 * Every block in document order, inner blocks included.
 *
 * @param {Object[]} blocks Blocks.
 * @return {Object[]} Flat list.
 */
export const flatBlocks = ( blocks ) =>
	blocks.reduce(
		( all, block ) => [
			...all,
			block,
			...flatBlocks( block.innerBlocks || [] ),
		],
		[]
	);

/**
 * HTML of a block's rich text attribute (RichTextData in newer WordPress).
 *
 * @param {Object} attributes Block attributes.
 * @return {string} HTML.
 */
export const richHtml = ( attributes ) =>
	attributes && attributes.content !== undefined
		? String( attributes.content )
		: '';

/**
 * Run one AI Writer request with busy and error state.
 *
 * @return {Object} { run, busy, error, setError }.
 */
export const useRequest = () => {
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const run = useCallback( ( key, path, payload, method = 'POST' ) => {
		setBusy( key );
		setError( '' );
		return writer( path, { method, data: payload } )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				return null;
			} )
			.finally( () => setBusy( '' ) );
	}, [] );
	return { run, busy, error, setError };
};
