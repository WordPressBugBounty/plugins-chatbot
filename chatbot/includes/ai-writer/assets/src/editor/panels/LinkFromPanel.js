/**
 * "Link to this article from…" (CR-6 W1, Free): pages the brief named as
 * ones that should link here. Once the article is published, each page
 * has an "Add link" button that adds a short "Read next" line with the
 * link at the end of that page. Nothing changes without a click.
 */
import { Button, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../admin/api';

const LinkFromPanel = () => {
	const [ pages, setPages ] = useState( data.linkFrom || [] );
	const [ busy, setBusy ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const live = useSelect(
		( select ) =>
			'publish' ===
				select( 'core/editor' ).getCurrentPostAttribute( 'status' ) &&
			! select( 'core/editor' ).isEditedPostDirty(),
		[]
	);

	const add = ( id ) => {
		setBusy( id );
		setError( '' );
		writer( `editor/${ data.postId }/link-from/${ id }`, {
			method: 'POST',
		} )
			.then( ( result ) => setPages( result.suggestions || [] ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( 0 ) );
	};

	return (
		<div className="qcwr-link-from">
			<p className="qcwr-muted">
				{ live
					? __(
							'These pages should link to this article. "Add link" adds a short "Read next" line at the end of the page.',
							'chatbot'
						)
					: __(
							'Publish (and save) this article first; then you can add a link to it from these pages.',
							'chatbot'
						) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<ul>
				{ pages.map( ( page ) => (
					<li key={ page.id }>
						<a href={ page.url } target="_blank" rel="noreferrer">
							{ page.title }
						</a>{ ' ' }
						{ page.done && (
							<span className="qcwr-badge">
								{ __( 'Linked', 'chatbot' ) }
							</span>
						) }
						{ ! page.done && page.can_edit && (
							<Button
								variant="secondary"
								size="small"
								isBusy={ busy === page.id }
								disabled={ ! live || !! busy }
								onClick={ () => add( page.id ) }
							>
								{ __( 'Add link', 'chatbot' ) }
							</Button>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
};

export default LinkFromPanel;
