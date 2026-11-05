/**
 * "I posted it" (on a place) and the "Posted" card: what the owner posted
 * on other sites, with the page of their site it links to. Posts with a
 * linked page also show in Results & Reports. Pro adds automatic checks.
 */
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';

/**
 * The "I posted it" button and form for one place.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.place    Place.
 * @param {Function} props.onLogged Called after logging.
 * @return {Element} Form.
 */
export const IPostedIt = ( { place, onLogged = () => {} } ) => {
	const [ open, setOpen ] = useState( false );
	const [ form, setForm ] = useState( { url: '', page_url: '', note: '' } );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	if ( ! open ) {
		return (
			<Button
				variant="secondary"
				size="compact"
				onClick={ () => setOpen( true ) }
			>
				{ __( 'I posted it', 'chatbot' ) }
			</Button>
		);
	}
	const save = () => {
		setBusy( true );
		setError( '' );
		seo( 'cited/posts/log', {
			method: 'POST',
			data: { place_id: place.id, ...form },
		} )
			.then( ( post ) => {
				setOpen( false );
				onLogged( post );
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<div className="qcseo-form qcseo-posted-form">
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="url"
				label={ __( 'Link to your post', 'chatbot' ) }
				placeholder="https://"
				value={ form.url }
				onChange={ set( 'url' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="url"
				label={ __(
					'Page of your site it links to (optional)',
					'chatbot'
				) }
				help={ __(
					'Then Results & Reports shows that page’s clicks before and after.',
					'chatbot'
				) }
				value={ form.page_url }
				onChange={ set( 'page_url' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Note (optional)', 'chatbot' ) }
				value={ form.note }
				onChange={ set( 'note' ) }
			/>
			{ error && <p className="qcseo-error">{ error }</p> }
			<div className="qcseo-actions">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! form.url.trim() }
					onClick={ save }
				>
					{ __( 'Save to my log', 'chatbot' ) }
				</Button>
				<Button variant="tertiary" onClick={ () => setOpen( false ) }>
					{ __( 'Cancel', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

/**
 * The "Posted" card.
 *
 * @param {Object} props         Props.
 * @param {number} props.version Bumped when a post was logged.
 * @return {Element} Card.
 */
const PostedLog = ( { version = 0 } ) => {
	const [ view, setView ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'cited/posts' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [ version ] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const remove = ( post ) =>
		seo( 'cited/posts/delete', { method: 'POST', data: { id: post.id } } )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	return (
		<div className="qcseo-card qcseo-cited-posted">
			<h3>{ __( 'Posted', 'chatbot' ) }</h3>
			{ message && (
				<Notice status="error" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ 0 === view.posts.length && (
				<p className="qcseo-muted">
					{ __(
						'After posting, click "I posted it" on the place and paste the link. Your posts are listed here.',
						'chatbot'
					) }
				</p>
			) }
			{ view.posts.length > 0 && (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Post', 'chatbot' ) }</th>
							<th>{ __( 'Links to', 'chatbot' ) }</th>
							<th>{ __( 'Posted', 'chatbot' ) }</th>
							<th>{ __( 'Status', 'chatbot' ) }</th>
							<th>
								<span className="screen-reader-text">
									{ __( 'Actions', 'chatbot' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ view.posts.map( ( post ) => (
							<tr key={ post.id }>
								<td>
									<span className="qcseo-status">
										{ post.platform_label }
									</span>{ ' ' }
									<a
										href={ post.url }
										target="_blank"
										rel="noreferrer noopener"
									>
										{ post.search || post.url }
									</a>
									{ post.note && (
										<div className="qcseo-muted">
											{ post.note }
										</div>
									) }
								</td>
								<td>
									{ post.page ? (
										<a href={ post.page.url }>
											{ post.page.title }
										</a>
									) : (
										'—'
									) }
								</td>
								<td>{ post.posted_at.slice( 0, 10 ) }</td>
								<td>
									{ applyFilters(
										'qcld_wpbot_seo.cited.postStatus',
										'removed' === post.status
											? __(
													'Removed',
													'chatbot'
												)
											: __(
													'Posted',
													'chatbot'
												),
										{ post }
									) }
								</td>
								<td>
									{ view.can_edit && (
										<Button
											variant="link"
											isDestructive
											onClick={ () => remove( post ) }
										>
											{ __(
												'Remove from log',
												'chatbot'
											) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ view.posts.length > 0 && (
				<p className="qcseo-muted">
					{ sprintf(
						/* translators: %d: posts. */
						__(
							'%d posts. Posts that link to a page of your site also show in Results & Reports.',
							'chatbot'
						),
						view.posts.length
					) }
				</p>
			) }
			{ applyFilters( 'qcld_wpbot_seo.cited.posted.after', null, {
				reload: load,
			} ) }
		</div>
	);
};

export default PostedLog;
