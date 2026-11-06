/**
 * "Add to WPBot knowledge": after a page is published, the owner can add
 * it to the chatbot's knowledge so the bot can answer with it.
 */
import { Button, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../admin/api';

const KnowledgePanel = () => {
	const knowledge = data.knowledge || {};
	const [ added, setAdded ] = useState( knowledge.added || '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const { postId, published, dirty } = useSelect( ( s ) => {
		const ed = s( 'core/editor' );
		return {
			postId: ed.getCurrentPostId(),
			published: 'publish' === ed.getCurrentPostAttribute( 'status' ),
			dirty: ed.isEditedPostDirty(),
		};
	}, [] );

	const add = () => {
		setBusy( true );
		setError( '' );
		writer( 'knowledge/add', {
			method: 'POST',
			data: { post_id: postId },
		} )
			.then( () => setAdded( new Date().toISOString() ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-knowledge">
			{ knowledge.answer && (
				<p className="qcwr-muted">
					{ __( 'Answers the visitor question:', 'chatbot' ) }{ ' ' }
					<em>{ knowledge.answer }</em>
				</p>
			) }
			<p className="qcwr-muted">
				{ __(
					'Let your chatbot answer with this page. It is read again when you click the button after changes.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ added && ! error && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Added to WPBot knowledge.', 'chatbot' ) }
				</Notice>
			) }
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy || ! published || dirty }
				onClick={ add }
			>
				{ added
					? __( 'Add again (after changes)', 'chatbot' )
					: __( 'Add to WPBot knowledge', 'chatbot' ) }
			</Button>
			{ ( ! published || dirty ) && (
				<p className="qcwr-muted">
					{ published
						? __( 'Save your changes first.', 'chatbot' )
						: __(
								'Publish the page first, then add it.',
								'chatbot'
							) }
				</p>
			) }
		</div>
	);
};

export default KnowledgePanel;
