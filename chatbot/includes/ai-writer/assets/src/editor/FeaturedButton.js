/**
 * An "AI Writer" button right below "Set featured image" in the Post
 * sidebar (CR-10), where it is easy to find. It opens AI Writer's image
 * picker for the featured image (stock, Openverse, Media Library, upload,
 * Create with AI, graphic templates) and offers the full AI Writer sidebar.
 * Also opens the AI Writer sidebar by itself the first time a draft
 * written by AI Writer is opened.
 */
import { Button } from '@wordpress/components';
import { dispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { data } from '../admin/api';
import ImagePicker from './ImagePicker';
import { sparkles } from './icons';

const SIDEBAR = 'qcwr-ai-writer/qcwr-sidebar';

/**
 * Open the AI Writer sidebar.
 */
export const openAiSidebar = () => {
	const iface = dispatch( 'core/interface' );
	if ( iface && iface.enableComplementaryArea ) {
		iface.enableComplementaryArea( 'core', SIDEBAR );
		iface.enableComplementaryArea( 'core/edit-post', SIDEBAR );
		return;
	}
	const editPost = dispatch( 'core/edit-post' );
	if ( editPost && editPost.openGeneralSidebar ) {
		editPost.openGeneralSidebar( SIDEBAR );
	}
};

const FeaturedButton = () => {
	const [ open, setOpen ] = useState( false );
	const title = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ),
		[]
	);
	return (
		<div className="qcwr-featured-button">
			<Button
				variant="secondary"
				icon={ sparkles }
				onClick={ () => setOpen( true ) }
			>
				{ __(
					'AI Writer: find or create an image',
					'chatbot'
				) }
			</Button>
			<Button variant="link" onClick={ openAiSidebar }>
				{ __( 'Open the AI Writer sidebar', 'chatbot' ) }
			</Button>
			{ open && (
				<ImagePicker
					featured
					query={ title || '' }
					alt={ title || '' }
					onPick={ ( image ) => {
						setOpen( false );
						if ( image && image.id ) {
							dispatch( 'core/editor' ).editPost( {
								featured_media: image.id,
							} );
						}
					} }
					onClose={ () => setOpen( false ) }
				/>
			) }
		</div>
	);
};

export const registerFeaturedButton = () => {
	if ( ! data.can || ! data.can.use ) {
		return;
	}
	addFilter(
		'editor.PostFeaturedImage',
		'qcwr/featured-button',
		( Original ) => ( props ) => (
			<>
				<Original { ...props } />
				<FeaturedButton />
			</>
		)
	);
};

/**
 * First open of a draft AI Writer wrote: show its sidebar once. Rendered
 * inside the editor, so it runs after the editor restored its own layout.
 *
 * @return {null} Nothing visible.
 */
export const FirstOpen = () => {
	useEffect( () => {
		if ( ! data.openSidebar ) {
			return undefined;
		}
		const timer = window.setTimeout( openAiSidebar, 500 );
		return () => window.clearTimeout( timer );
	}, [] );
	return null;
};
