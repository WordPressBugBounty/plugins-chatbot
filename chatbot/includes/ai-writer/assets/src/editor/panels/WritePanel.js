/**
 * "Write with AI" into the open draft: the full brief → outline → sections
 * flow in a window, with the result inserted as native blocks. Nothing is
 * saved until the owner saves. A result that finished while the owner was
 * away is offered again here.
 */
import { parse } from '@wordpress/blocks';
import { Button, Modal, Notice } from '@wordpress/components';
import { dispatch, select, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, writer } from '../../admin/api';
import WriteFlow, { emptyBrief } from '../../admin/components/write/WriteFlow';
import { PENDING_META } from '../utils';

/**
 * Put a finished result into the editor (undoable edits, not saved).
 *
 * @param {Object} result Job result (into_draft).
 * @return {void}
 */
export const insertResult = ( result ) => {
	const editor = select( 'core/editor' );
	const blocks = parse( result.content );
	const current = select( 'core/block-editor' ).getBlocks();
	const empty =
		! current.length ||
		( 1 === current.length &&
			'core/paragraph' === current[ 0 ].name &&
			! String( current[ 0 ].attributes.content || '' ).trim() );
	if ( empty ) {
		dispatch( 'core/block-editor' ).resetBlocks( blocks );
	} else {
		dispatch( 'core/block-editor' ).insertBlocks( blocks );
	}
	const edits = {};
	if ( ! editor.getEditedPostAttribute( 'title' ) && result.title ) {
		edits.title = result.title;
	}
	if ( ! editor.getEditedPostAttribute( 'excerpt' ) && result.excerpt ) {
		edits.excerpt = result.excerpt;
	}
	if (
		! editor.getEditedPostAttribute( 'featured_media' ) &&
		result.featured_id
	) {
		edits.featured_media = result.featured_id;
	}
	if ( result.meta_title || result.meta_description ) {
		edits.meta = {
			[ PENDING_META ]: JSON.stringify( {
				title: result.meta_title || '',
				description: result.meta_description || '',
			} ),
		};
	}
	dispatch( 'core/editor' ).editPost( edits );
	writer( `editor/pending-job?post_id=${ data.postId }`, {
		method: 'DELETE',
	} ).catch( () => {} );
};

const WritePanel = () => {
	const [ open, setOpen ] = useState( false );
	const [ jobId, setJobId ] = useState( 0 );
	const [ pending, setPending ] = useState( null );
	const [ done, setDone ] = useState( null );
	const title = useSelect(
		( s ) => s( 'core/editor' ).getEditedPostAttribute( 'title' ),
		[]
	);

	useEffect( () => {
		if ( ! data.pendingJob ) {
			return;
		}
		writer( `write/jobs/${ data.pendingJob }` )
			.then( ( view ) => setPending( view ) )
			.catch( () => {} );
	}, [] );

	const finish = ( result ) => {
		insertResult( result );
		setOpen( false );
		setPending( null );
		setDone( result );
	};

	return (
		<div className="qcwr-panel-write">
			{ pending && pending.result && pending.result.into_draft && (
				<Notice status="info" isDismissible={ false }>
					<p>
						{ __(
							'A draft AI Writer wrote for this post earlier is ready.',
							'chatbot'
						) }
					</p>
					<Button
						variant="primary"
						onClick={ () => finish( pending.result ) }
					>
						{ __( 'Insert it', 'chatbot' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						onClick={ () => {
							setPending( null );
							writer(
								`editor/pending-job?post_id=${ data.postId }`,
								{ method: 'DELETE' }
							).catch( () => {} );
						} }
					>
						{ __( 'Dismiss', 'chatbot' ) }
					</Button>
				</Notice>
			) }
			{ pending &&
				! pending.result &&
				pending.job &&
				! [ 'failed', 'cancelled' ].includes( pending.job.status ) && (
					<Notice status="info" isDismissible={ false }>
						<p>
							{ __(
								'AI Writer is still writing this post.',
								'chatbot'
							) }
						</p>
						<Button
							variant="secondary"
							onClick={ () => {
								setJobId( data.pendingJob );
								setOpen( true );
							} }
						>
							{ __( 'Continue', 'chatbot' ) }
						</Button>
					</Notice>
				) }
			{ done && (
				<Notice status="success" onRemove={ () => setDone( null ) }>
					{ sprintf(
						/* translators: 1: words, 2: placeholders. */
						__(
							'Inserted %1$d words. Personal touches to add: %2$d. Review it, then save.',
							'chatbot'
						),
						done.words,
						done.placeholders
					) }
				</Notice>
			) }
			{ ! data.update.of && (
				<p>
					<a href={ `${ data.writerUrl }&improve=${ data.postId }` }>
						{ __(
							'Improve this post section by section',
							'chatbot'
						) }
					</a>
				</p>
			) }
			<p className="qcwr-muted">
				{ __(
					'Plan an outline with you, then write the post section by section into this draft.',
					'chatbot'
				) }
			</p>
			<Button
				variant="primary"
				onClick={ () => {
					setJobId( 0 );
					setOpen( true );
				} }
			>
				{ __( 'Write with AI', 'chatbot' ) }
			</Button>
			{ open && (
				<Modal
					title={ __( 'Write with AI', 'chatbot' ) }
					onRequestClose={ () => setOpen( false ) }
					className="qcwr-write-modal"
					size="large"
					shouldCloseOnClickOutside={ false }
				>
					<div className="qcwr-wrap">
						<WriteFlow
							initialJobId={ jobId }
							initialBrief={ {
								...emptyBrief(),
								topic: title || '',
								post_type:
									'page' === data.postType ? 'page' : 'post',
								target_post: data.postId,
								// CR-16: the format planned for this draft (changeable in the brief).
								format: data.postFormat || '',
								format_picked: !! data.postFormat,
							} }
							exitLabel={ __( 'Close', 'chatbot' ) }
							onExit={ () => setOpen( false ) }
							onResult={ finish }
						/>
					</div>
				</Modal>
			) }
		</div>
	);
};

export default WritePanel;
