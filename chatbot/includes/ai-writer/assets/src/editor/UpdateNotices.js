/**
 * Notices for Improve updates in the block editor: on an update copy, a
 * "Publish the update" action (it replaces the live page; the copy itself
 * can never be published as a separate post); on a live page with an
 * update waiting, a link to review it.
 */
import { dispatch, select } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../admin/api';

const ID = 'qcwr-update-notice';

const publishUpdate = () => {
	const editor = select( 'core/editor' );
	const notices = dispatch( 'core/notices' );
	const save = editor.isEditedPostDirty()
		? dispatch( 'core/editor' ).savePost()
		: Promise.resolve();
	notices.createInfoNotice(
		__( 'Publishing the update…', 'chatbot' ),
		{ id: ID, isDismissible: false }
	);
	Promise.resolve( save )
		.then( () =>
			writer( `improve/updates/${ data.postId }/publish`, {
				method: 'POST',
			} )
		)
		.then( ( result ) => {
			window.location.href = result.edit_url;
		} )
		.catch( ( err ) =>
			notices.createErrorNotice( errorMessage( err ), { id: ID } )
		);
};

const UpdateNotices = () => {
	useEffect( () => {
		const update = data.update || {};
		const notices = dispatch( 'core/notices' );
		if ( update.of ) {
			// The copy is published through "Publish the update" only.
			document.body.classList.add( 'qcwr-is-update' );
			notices.createWarningNotice(
				sprintf(
					/* translators: %s: title of the live page. */
					__(
						'This is an AI Writer update of "%s". Nothing is live until you publish the update; it then replaces the live page (the old version stays in its revisions).',
						'chatbot'
					),
					update.ofTitle
				),
				{
					id: ID,
					isDismissible: false,
					actions: [
						...( update.canPublish
							? [
									{
										label: __(
											'Publish the update',
											'chatbot'
										),
										onClick: publishUpdate,
									},
								]
							: [] ),
						{
							label: __(
								'Open the live version',
								'chatbot'
							),
							url: update.ofUrl,
						},
					],
				}
			);
		} else if ( update.waiting ) {
			notices.createInfoNotice(
				__(
					'An AI Writer update for this page is waiting for review.',
					'chatbot'
				),
				{
					id: ID,
					actions: [
						{
							label: __( 'Review the update', 'chatbot' ),
							url: update.waitingUrl,
						},
					],
				}
			);
		}
	}, [] );
	return null;
};

export default UpdateNotices;
