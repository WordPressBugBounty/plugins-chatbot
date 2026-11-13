/**
 * Fixes for one page: issues with Fix with AI (search title and
 * description, reviewed before saving), AI Writer for content issues,
 * internal link suggestions, and the page's recent changes with undo.
 */
import {
	Button,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo, product } from '../api';
import { AiReason, aiBlocked, useAiReady } from '../ai';

const META_FIX = [ 'meta_title', 'meta_description' ];

const PageFixes = ( { postId, onChanged } ) => {
	const aiOff = aiBlocked( useAiReady() );
	const [ view, setView ] = useState( null );
	const [ proposal, setProposal ] = useState( null );
	const [ links, setLinks ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( '' );

	const load = () =>
		seo( `fixes/${ postId }` )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );

	useEffect( () => {
		load();
		seo( `links/suggest/${ postId }` )
			.then( setLinks )
			.catch( () => setLinks( null ) );
	}, [ postId ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const run = ( name, promise ) => {
		setBusy( name );
		setMessage( '' );
		return promise
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const propose = () =>
		run(
			'propose',
			seo( `fixes/${ postId }/propose`, { method: 'POST' } ).then(
				( result ) =>
					setProposal( {
						title: result.title,
						description: result.description,
					} )
			)
		);

	const save = ( field ) =>
		run(
			`save-${ field }`,
			seo( `fixes/${ postId }/apply`, {
				method: 'POST',
				data: { field, value: proposal[ field ] },
			} ).then( () => {
				setMessage(
					view.seoPlugin
						? sprintf(
								/* translators: %s: SEO plugin name. */
								__( 'Saved in %s.', 'chatbot' ),
								view.seoPlugin
							)
						: __( 'Saved.', 'chatbot' )
				);
				load();
				if ( onChanged ) {
					onChanged();
				}
			} )
		);

	const writer = ( issue ) =>
		run(
			`writer-${ issue }`,
			seo( `fixes/${ postId }/writer`, {
				method: 'POST',
				data: { issue },
			} ).then( ( result ) => {
				window.location.href = result.url;
			} )
		);

	const addLink = ( link, direction ) =>
		run(
			`link-${ link.post_id }`,
			seo( 'links/add', {
				method: 'POST',
				data:
					'out' === direction
						? {
								source_id: postId,
								target_id: link.post_id,
								anchor: link.anchor,
							}
						: {
								source_id: link.post_id,
								target_id: postId,
								anchor: link.anchor,
							},
			} ).then( () => {
				setMessage(
					__(
						'Link added. You can undo it below.',
						'chatbot'
					)
				);
				setLinks( {
					...links,
					[ direction ]: links[ direction ].filter(
						( item ) => item !== link
					),
				} );
				load();
			} )
		);

	const undo = ( id ) =>
		run(
			`undo-${ id }`,
			seo( `changes/${ id }/undo`, { method: 'POST' } ).then( () => {
				setMessage( __( 'Undone.', 'chatbot' ) );
				load();
			} )
		);

	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const issues = view.audit ? view.audit.issues : [];
	const editable = !! view.fields;

	return (
		<div className="qcseo-page-fixes">
			{ view.fields && (
				<div className="qcseo-fields">
					<p>
						<strong>
							{ __( 'Search title:', 'chatbot' ) }
						</strong>{ ' ' }
						{ view.fields.title ||
							__( '(not set)', 'chatbot' ) }
					</p>
					<p>
						<strong>
							{ __( 'Search description:', 'chatbot' ) }
						</strong>{ ' ' }
						{ view.fields.description ||
							__( '(not set)', 'chatbot' ) }
					</p>
					<p className="qcseo-muted">
						{ view.seoPlugin
							? sprintf(
									/* translators: %s: SEO plugin name. */
									__(
										'Saved through %s.',
										'chatbot'
									),
									view.seoPlugin
								)
							: sprintf(
									/* translators: %s: product name, for example WPBot. */
									__(
										'No SEO plugin is active; %s prints these on the page.',
										'chatbot'
									),
									product
								) }
					</p>
				</div>
			) }
			<ul className="qcseo-issues">
				{ issues.map( ( issue ) => (
					<li
						key={ issue.id }
						className={ `qcseo-issue is-${ issue.severity }` }
					>
						<div>
							<span
								className={ `qcseo-sev qcseo-sev--${ issue.severity }` }
							>
								{ issue.severity }
							</span>{ ' ' }
							<strong>{ issue.label }</strong>
							<p>{ issue.message }</p>
							<p className="qcseo-muted">{ issue.why }</p>
						</div>
						{ editable && data.can.use && (
							<div className="qcseo-issue__actions">
								{ META_FIX.includes( issue.fix ) && (
									<Button
										variant="primary"
										size="compact"
										isBusy={ 'propose' === busy }
										disabled={ !! busy || !! aiOff }
										onClick={ propose }
									>
										{ __(
											'Fix with AI',
											'chatbot'
										) }
									</Button>
								) }
								{ META_FIX.includes( issue.fix ) && aiOff && (
									<AiReason />
								) }
								{ ! META_FIX.includes( issue.fix ) &&
									'links' !== issue.fix &&
									data.writer && (
										<Button
											variant="primary"
											size="compact"
											isBusy={
												`writer-${ issue.id }` === busy
											}
											disabled={ !! busy }
											onClick={ () => writer( issue.id ) }
										>
											{ __(
												'Fix with AI Writer',
												'chatbot'
											) }
										</Button>
									) }
							</div>
						) }
					</li>
				) ) }
			</ul>
			{ 0 === issues.length && (
				<p>{ __( 'No on-page issues.', 'chatbot' ) }</p>
			) }

			{ proposal && (
				<div className="qcseo-card qcseo-proposal">
					<h4>
						{ __( 'Review before saving', 'chatbot' ) }
					</h4>
					<TextControl
						__next40pxDefaultSize
						label={ sprintf(
							/* translators: %d: characters. */
							__(
								'Search title (%d characters)',
								'chatbot'
							),
							proposal.title.length
						) }
						value={ proposal.title }
						onChange={ ( value ) =>
							setProposal( { ...proposal, title: value } )
						}
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						isBusy={ 'save-title' === busy }
						disabled={ !! busy || ! proposal.title }
						onClick={ () => save( 'title' ) }
					>
						{ __( 'Save title', 'chatbot' ) }
					</Button>
					<TextareaControl
						label={ sprintf(
							/* translators: %d: characters. */
							__(
								'Search description (%d characters)',
								'chatbot'
							),
							proposal.description.length
						) }
						value={ proposal.description }
						onChange={ ( value ) =>
							setProposal( { ...proposal, description: value } )
						}
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						isBusy={ 'save-description' === busy }
						disabled={ !! busy || ! proposal.description }
						onClick={ () => save( 'description' ) }
					>
						{ __( 'Save description', 'chatbot' ) }
					</Button>
				</div>
			) }

			{ links &&
				( links.out.length > 0 || links.in.length > 0 ) &&
				editable && (
					<div className="qcseo-card">
						<h4>
							{ __(
								'Internal link suggestions',
								'chatbot'
							) }
						</h4>
						{ links.out.map( ( link ) => (
							<p key={ `out-${ link.post_id }` }>
								{ sprintf(
									/* translators: 1: anchor text, 2: page title. */
									__(
										'Link "%1$s" here to "%2$s"',
										'chatbot'
									),
									link.anchor,
									link.title
								) }{ ' ' }
								<Button
									variant="link"
									disabled={ !! busy }
									onClick={ () => addLink( link, 'out' ) }
								>
									{ __( 'Add link', 'chatbot' ) }
								</Button>
							</p>
						) ) }
						{ links.in.map( ( link ) => (
							<p key={ `in-${ link.post_id }` }>
								{ sprintf(
									/* translators: 1: page title, 2: anchor text. */
									__(
										'Link from "%1$s" ("%2$s") to this page',
										'chatbot'
									),
									link.title,
									link.anchor
								) }{ ' ' }
								{ link.can_edit && (
									<Button
										variant="link"
										disabled={ !! busy }
										onClick={ () => addLink( link, 'in' ) }
									>
										{ __( 'Add link', 'chatbot' ) }
									</Button>
								) }
							</p>
						) ) }
					</div>
				) }

			{ view.changes.length > 0 && (
				<div className="qcseo-card">
					<h4>{ __( 'Recent changes', 'chatbot' ) }</h4>
					<ul className="qcseo-changes">
						{ view.changes.map( ( change ) => (
							<li key={ change.id }>
								<span>
									{ change.field }: { change.before || '–' } →{ ' ' }
									{ change.after || '–' }
								</span>
								{ change.undone_at ? (
									<em>
										{ __( 'undone', 'chatbot' ) }
									</em>
								) : (
									data.can.publish && (
										<Button
											variant="link"
											disabled={ !! busy }
											onClick={ () => undo( change.id ) }
										>
											{ __( 'Undo', 'chatbot' ) }
										</Button>
									)
								) }
							</li>
						) ) }
					</ul>
				</div>
			) }
			{ message && <p className="qcseo-message">{ message }</p> }
		</div>
	);
};

export default PageFixes;
