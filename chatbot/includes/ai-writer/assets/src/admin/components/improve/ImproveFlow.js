/**
 * "Improve existing content": pick a page (or a task from the decay check),
 * let AI Writer review it, accept or reject each proposal, then apply.
 * Drafts change directly; live pages get an update copy to check and
 * publish, never a silent overwrite.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { RawHTML, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import JobProgress from '../JobProgress';
import PersuasionPicker from '../persuasion/PersuasionPicker';
import {
	fromEditable,
	safePreview,
	toBlocks,
	toEditable,
} from '../write/blocks';
import RegenerateControl from '../regenerate/Regenerate';

const CHANGE_LABELS = () => ( {
	clearer: __( 'Clearer', 'chatbot' ),
	shorter: __( 'Shorter', 'chatbot' ),
	updated: __( 'Up to date', 'chatbot' ),
	reangle: __( 'New angle', 'chatbot' ),
} );

const Pick = ( { initialPost, initialFix, onStart } ) => {
	const [ tasks, setTasks ] = useState( null );
	const [ posts, setPosts ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ chosen, setChosen ] = useState( initialPost || null );
	const [ fix, setFix ] = useState( initialFix || '' );
	// Round 3: re-angle with another reader stage or technique (optional).
	const [ reangle, setReangle ] = useState( false );
	const [ persuasion, setPersuasion ] = useState( {} );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const loadTasks = ( check = false ) => {
		setBusy( check ? 'check' : '' );
		writer(
			check ? 'improve/tasks/check' : 'improve/tasks',
			check ? { method: 'POST' } : {}
		)
			.then( setTasks )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( '' ) );
	};
	const find = ( term ) =>
		writer( `improve/posts?search=${ encodeURIComponent( term ) }` )
			.then( setPosts )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	useEffect( () => {
		loadTasks();
		find( '' );
	}, [] );

	const start = () => {
		setBusy( 'start' );
		setError( '' );
		writer( 'improve/start', {
			method: 'POST',
			data: {
				post_id: chosen.id,
				fix,
				persuasion: reangle ? persuasion : {},
			},
		} )
			.then( ( view ) => onStart( view.job.id ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ tasks && tasks.reviews.length > 0 && (
				<section className="qcwr-card">
					<h2>
						{ __( 'Reviews waiting for you', 'chatbot' ) }
					</h2>
					<ul className="qcwr-recent">
						{ tasks.reviews.map( ( review ) => (
							<li key={ review.job_id }>
								<Button
									variant="link"
									onClick={ () => onStart( review.job_id ) }
								>
									{ review.title }
								</Button>
							</li>
						) ) }
					</ul>
				</section>
			) }
			<section className="qcwr-card qcwr-improve-tasks">
				<h2>
					{ __( 'Pages that need attention', 'chatbot' ) }
				</h2>
				{ ! tasks && <Spinner /> }
				{ tasks && ! tasks.status.sources.length && (
					<p className="qcwr-muted">
						{ __(
							'Connect Search Console or turn on Site Insight and AI Writer will tell you which pages are losing visitors, and why.',
							'chatbot'
						) }
					</p>
				) }
				{ tasks &&
					tasks.status.sources.length > 0 &&
					! tasks.tasks.length && (
						<p className="qcwr-muted">
							{ __(
								'No page is losing visitors right now.',
								'chatbot'
							) }
						</p>
					) }
				<ul className="qcwr-tasks">
					{ tasks &&
						tasks.tasks.map( ( task ) => (
							<li
								key={ `${ task.source_module || '' }-${
									task.post_id
								}-${ task.cause }` }
							>
								<strong>{ task.title }</strong>
								<p>{ task.cause }</p>
								<Button
									variant="secondary"
									onClick={ () => {
										setChosen( {
											id: task.post_id,
											title: task.title,
										} );
										setFix( task.fix || task.cause );
									} }
								>
									{ __( 'Improve', 'chatbot' ) }
								</Button>{ ' ' }
								{ ( ! task.source_module ||
									'ai_writer' === task.source_module ) && (
									<Button
										variant="tertiary"
										onClick={ () =>
											writer(
												`improve/tasks/${ task.post_id }/dismiss`,
												{ method: 'POST' }
											)
												.then( setTasks )
												.catch( ( err ) =>
													setError(
														errorMessage( err )
													)
												)
										}
									>
										{ __( 'Dismiss', 'chatbot' ) }
									</Button>
								) }
							</li>
						) ) }
				</ul>
				{ tasks && tasks.status.sources.length > 0 && (
					<Button
						variant="tertiary"
						isBusy={ 'check' === busy }
						onClick={ () => loadTasks( true ) }
					>
						{ __( 'Check now', 'chatbot' ) }
					</Button>
				) }
			</section>

			<section className="qcwr-card">
				<h2>{ __( 'Choose a page to improve', 'chatbot' ) }</h2>
				{ chosen ? (
					<p>
						<strong>{ chosen.title }</strong>{ ' ' }
						<Button
							variant="link"
							onClick={ () => setChosen( null ) }
						>
							{ __( 'Change', 'chatbot' ) }
						</Button>
					</p>
				) : (
					<>
						<TextControl
							label={ __(
								'Search your posts, pages and products',
								'chatbot'
							) }
							value={ search }
							onChange={ ( value ) => {
								setSearch( value );
								find( value );
							} }
							__nextHasNoMarginBottom
						/>
						<ul className="qcwr-pick">
							{ posts.map( ( post ) => (
								<li key={ post.id }>
									<Button
										variant="link"
										onClick={ () => setChosen( post ) }
									>
										{ post.title ||
											__(
												'(no title)',
												'chatbot'
											) }
									</Button>
									<span className="qcwr-muted">
										{ ' ' }
										· { post.type } ·{ ' ' }
										{ post.live
											? __( 'live', 'chatbot' )
											: post.status }
										{ post.update > 0 &&
											` · ${ __( 'update waiting', 'chatbot' ) }` }
									</span>
								</li>
							) ) }
						</ul>
					</>
				) }
				<TextareaControl
					label={ __(
						'Anything specific to fix? (optional)',
						'chatbot'
					) }
					placeholder={ __(
						'For example: our prices changed, make it friendlier',
						'chatbot'
					) }
					value={ fix }
					onChange={ setFix }
					rows={ 2 }
					__nextHasNoMarginBottom
				/>
				{ data.persuasion && (
					<CheckboxControl
						label={ __(
							'Re-angle it: write for another reader stage or with another technique',
							'chatbot'
						) }
						checked={ reangle }
						onChange={ setReangle }
						__nextHasNoMarginBottom
					/>
				) }
				{ reangle && chosen && (
					<PersuasionPicker
						value={ persuasion }
						onChange={ ( next ) =>
							setPersuasion( { ...next, owner: true } )
						}
						type={
							{ product: 'product', page: 'page_copy' }[
								chosen.post_type
							] || 'blog_post'
						}
						title={ __( 'New angle', 'chatbot' ) }
					/>
				) }
				<div className="qcwr-actions">
					<Button
						variant="primary"
						disabled={ ! chosen || !! busy }
						isBusy={ 'start' === busy }
						onClick={ start }
					>
						{ __( 'Review this page', 'chatbot' ) }
					</Button>
				</div>
				<p className="qcwr-muted">
					{ __(
						'AI Writer suggests changes section by section. Nothing changes until you accept it; live pages get an update for you to check first.',
						'chatbot'
					) }
				</p>
			</section>
		</>
	);
};

/**
 * One proposal: "Now" and "Proposed", both formatted (bold, links, lists).
 * "Edit" opens a text area with the proposed text (simple tags such as
 * <a href>, <strong> and <em> kept); "Save" keeps the owner's version and
 * accepts it, "Reset" goes back to the AI's. Blocked proposals cannot be
 * edited or accepted.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.proposal id, before, after, blocked.
 * @param {string}   props.decision accept | reject | undefined.
 * @param {Function} props.onDecide Decision callback.
 * @param {string}   props.edit     The owner's version (undefined when none).
 * @param {Function} props.onEdit   Receives the new version, or null to reset.
 * @param {boolean}  props.blocks   Block-level text (summary, FAQ).
 * @param {string}   props.preview  Formatted preview to edit (summary, FAQ).
 * @return {Element} Proposal.
 */
const Proposal = ( {
	proposal,
	decision,
	onDecide,
	edit,
	onEdit,
	blocks = false,
	preview = '',
} ) => {
	const [ editing, setEditing ] = useState( false );
	const [ text, setText ] = useState( '' );
	const edited = undefined !== edit && null !== edit;
	const source = blocks ? preview : proposal.after;
	const shown = safePreview(
		edited ? ( blocks && fromEditable( edit ) ) || edit : source
	);
	const start = () => {
		let initial = source;
		if ( edited ) {
			initial = edit;
		} else if ( blocks ) {
			initial = toEditable( source );
		}
		setText( initial );
		setEditing( true );
	};
	return (
		<div
			className={ `qcwr-proposal is-${ decision || 'open' }${ proposal.blocked ? ' is-blocked' : '' }` }
		>
			{ ( proposal.before || source ) && (
				<div className="qcwr-proposal__texts">
					{ proposal.before && (
						<div>
							<span className="qcwr-muted">
								{ __( 'Now', 'chatbot' ) }
							</span>
							<RawHTML className="qcwr-proposal__before">
								{ safePreview( proposal.before ) }
							</RawHTML>
						</div>
					) }
					<div>
						<span className="qcwr-muted">
							{ __( 'Proposed', 'chatbot' ) }{ ' ' }
							{ edited && (
								<span className="qcwr-badge qcwr-badge--edited">
									{ __( 'Edited by you', 'chatbot' ) }
								</span>
							) }
						</span>
						{ editing ? (
							<TextareaControl
								label={ __(
									'Your version',
									'chatbot'
								) }
								help={
									blocks
										? __(
												'Separate paragraphs with a blank line. Tags such as <strong>, <em>, <a href="…">, <h3> and <li> are kept.',
												'chatbot'
											)
										: __(
												'Tags such as <strong>, <em> and <a href="…"> are kept.',
												'chatbot'
											)
								}
								value={ text }
								onChange={ setText }
								rows={ blocks ? 8 : 4 }
								__nextHasNoMarginBottom
							/>
						) : (
							<RawHTML className="qcwr-proposal__after">
								{ shown }
							</RawHTML>
						) }
					</div>
				</div>
			) }
			{ proposal.blocked ? (
				<p className="qcwr-over">
					{ __( 'Not offered:', 'chatbot' ) }{ ' ' }
					{ proposal.blocked }
				</p>
			) : (
				<div className="qcwr-actions">
					{ editing ? (
						<>
							<Button
								variant="primary"
								disabled={ ! text.trim() }
								onClick={ () => {
									onEdit( text );
									onDecide( 'accept' );
									setEditing( false );
								} }
							>
								{ __( 'Save', 'chatbot' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => setEditing( false ) }
							>
								{ __( 'Cancel', 'chatbot' ) }
							</Button>
						</>
					) : (
						<>
							<Button
								variant={
									'accept' === decision
										? 'primary'
										: 'secondary'
								}
								onClick={ () => onDecide( 'accept' ) }
							>
								{ __( 'Accept', 'chatbot' ) }
							</Button>
							<Button
								variant={
									'reject' === decision
										? 'primary'
										: 'tertiary'
								}
								onClick={ () => onDecide( 'reject' ) }
							>
								{ __( 'Reject', 'chatbot' ) }
							</Button>
							{ onEdit && (
								<Button variant="link" onClick={ start }>
									{ __( 'Edit', 'chatbot' ) }
								</Button>
							) }
							{ onEdit && edited && (
								<Button
									variant="link"
									isDestructive
									onClick={ () => onEdit( null ) }
								>
									{ __( 'Reset', 'chatbot' ) }
								</Button>
							) }
						</>
					) }
				</div>
			) }
		</div>
	);
};

/**
 * The search title and description: shown, and editable as two fields.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.meta     Proposal (title, description, before).
 * @param {string}   props.decision Decision.
 * @param {Function} props.onDecide Decision callback.
 * @param {Object}   props.edit     The owner's version (title, description).
 * @param {Function} props.onEdit   Receives the new version, or null.
 * @return {Element} Proposal.
 */
const MetaProposal = ( { meta, decision, onDecide, edit, onEdit } ) => {
	const [ editing, setEditing ] = useState( false );
	const [ form, setForm ] = useState( { title: '', description: '' } );
	const current = edit || {
		title: meta.title,
		description: meta.description,
	};
	return (
		<div className={ `qcwr-proposal is-${ decision || 'open' }` }>
			<div className="qcwr-proposal__texts">
				<div>
					<span className="qcwr-muted">
						{ __( 'Now', 'chatbot' ) }
					</span>
					<p className="qcwr-proposal__before">
						{ `${ meta.before.title || '—' } · ${
							meta.before.description || '—'
						}` }
					</p>
				</div>
				<div>
					<span className="qcwr-muted">
						{ __( 'Proposed', 'chatbot' ) }{ ' ' }
						{ edit && (
							<span className="qcwr-badge qcwr-badge--edited">
								{ __( 'Edited by you', 'chatbot' ) }
							</span>
						) }
					</span>
					{ editing ? (
						<>
							<TextControl
								label={ __(
									'Search title',
									'chatbot'
								) }
								value={ form.title }
								onChange={ ( title ) =>
									setForm( { ...form, title } )
								}
								__nextHasNoMarginBottom
							/>
							<TextareaControl
								label={ __(
									'Search description',
									'chatbot'
								) }
								value={ form.description }
								onChange={ ( description ) =>
									setForm( { ...form, description } )
								}
								rows={ 2 }
								__nextHasNoMarginBottom
							/>
						</>
					) : (
						<p className="qcwr-proposal__after">
							{ `${ current.title } · ${ current.description }` }
						</p>
					) }
				</div>
			</div>
			<div className="qcwr-actions">
				{ editing ? (
					<>
						<Button
							variant="primary"
							disabled={ ! form.title.trim() }
							onClick={ () => {
								onEdit( form );
								onDecide( 'accept' );
								setEditing( false );
							} }
						>
							{ __( 'Save', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setEditing( false ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</>
				) : (
					<>
						<Button
							variant={
								'accept' === decision ? 'primary' : 'secondary'
							}
							onClick={ () => onDecide( 'accept' ) }
						>
							{ __( 'Accept', 'chatbot' ) }
						</Button>
						<Button
							variant={
								'reject' === decision ? 'primary' : 'tertiary'
							}
							onClick={ () => onDecide( 'reject' ) }
						>
							{ __( 'Reject', 'chatbot' ) }
						</Button>
						<Button
							variant="link"
							onClick={ () => {
								setForm( { ...current } );
								setEditing( true );
							} }
						>
							{ __( 'Edit', 'chatbot' ) }
						</Button>
						{ edit && (
							<Button
								variant="link"
								isDestructive
								onClick={ () => onEdit( null ) }
							>
								{ __( 'Reset', 'chatbot' ) }
							</Button>
						) }
					</>
				) }
			</div>
		</div>
	);
};

const Review = ( { jobId, review, onApplied } ) => {
	const [ decisions, setDecisions ] = useState( {} );
	// CR-14: a section's proposals can be written again.
	const [ sections, setSections ] = useState( review.sections );
	const [ regenerating, setRegenerating ] = useState( -1 );
	const regenerateSection = ( index, regenerate ) => {
		setRegenerating( index );
		writer( `improve/jobs/${ jobId }/regenerate`, {
			method: 'POST',
			data: { index, regenerate },
		} )
			.then( ( body ) =>
				setSections(
					sections.map( ( section ) =>
						section.index === index ? body.section : section
					)
				)
			)
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setRegenerating( -1 ) );
	};
	// The owner's own versions (CR-7): id => text; meta => { title, description }.
	const [ edits, setEdits ] = useState( {} );
	const editFor = ( id ) => ( value ) => {
		const next = { ...edits };
		if ( null === value ) {
			delete next[ id ];
		} else {
			next[ id ] = value;
		}
		setEdits( next );
	};
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const labels = CHANGE_LABELS();
	const extras = review.extras || {};
	const decide = ( id, value ) =>
		setDecisions( { ...decisions, [ id ]: value } );
	const open = [];
	sections.forEach( ( section ) =>
		section.proposals.forEach(
			( proposal ) => ! proposal.blocked && open.push( proposal.id )
		)
	);
	[ 'summary', 'faq', 'meta' ].forEach(
		( id ) => extras[ id ] && open.push( id )
	);
	const accepted = Object.keys( decisions ).filter(
		( id ) => 'accept' === decisions[ id ]
	);

	const apply = () => {
		setBusy( true );
		setError( '' );
		writer( `improve/jobs/${ jobId }/apply`, {
			method: 'POST',
			data: {
				accepted,
				// Only edits of accepted proposals; summary and FAQ become blocks.
				edits: Object.fromEntries(
					Object.entries( edits )
						.filter( ( [ id ] ) => accepted.includes( id ) )
						.map( ( [ id, value ] ) => [
							id,
							'summary' === id || 'faq' === id
								? toBlocks( fromEditable( value ) )
								: value,
						] )
				),
			},
		} )
			.then( onApplied )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! open.length ) {
		return (
			<div className="qcwr-card">
				<h2>{ review.title }</h2>
				<p>
					{ __(
						'AI Writer found nothing worth changing. The page reads well as it is.',
						'chatbot'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="qcwr-improve-review">
			<div className="qcwr-card">
				<h2>{ review.title }</h2>
				<p className="qcwr-muted">
					{ review.live
						? __(
								'This page is live. Accepted changes go into an update you check before it replaces the live page.',
								'chatbot'
							)
						: __(
								'This is a draft. Accepted changes are saved into it.',
								'chatbot'
							) }
				</p>
				{ review.changed && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'The page was edited after this review. Start a new review so nothing you wrote is lost.',
							'chatbot'
						) }
					</Notice>
				) }
				<div className="qcwr-actions">
					<Button
						variant="secondary"
						onClick={ () => {
							const all = {};
							open.forEach( ( id ) => ( all[ id ] = 'accept' ) );
							setDecisions( all );
						} }
					>
						{ __( 'Accept all', 'chatbot' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => setDecisions( {} ) }
					>
						{ __( 'Clear choices', 'chatbot' ) }
					</Button>
				</div>
			</div>

			{ extras.summary && (
				<div className="qcwr-card">
					<h3>
						{ __( 'Add a summary at the top', 'chatbot' ) }
					</h3>
					<Proposal
						proposal={ {
							id: 'summary',
							before: '',
							after: '',
							blocked: '',
						} }
						blocks
						preview={ extras.summary.html }
						decision={ decisions.summary }
						onDecide={ ( value ) => decide( 'summary', value ) }
						edit={ edits.summary }
						onEdit={ editFor( 'summary' ) }
					/>
				</div>
			) }
			{ sections.map( ( section ) => (
				<div key={ section.index } className="qcwr-card">
					<h3>
						{ section.heading ||
							__( 'Introduction', 'chatbot' ) }{ ' ' }
						<span className="qcwr-badge">
							{ labels[ section.change ] || section.change }
						</span>
					</h3>
					<RegenerateControl
						kind="section"
						busy={ regenerating === section.index }
						onRegenerate={ ( options ) =>
							regenerateSection( section.index, options )
						}
					/>
					{ section.reason && (
						<p className="qcwr-muted">{ section.reason }</p>
					) }
					{ section.proposals.map( ( proposal ) => (
						<Proposal
							key={ proposal.id }
							proposal={ proposal }
							decision={ decisions[ proposal.id ] }
							onDecide={ ( value ) =>
								decide( proposal.id, value )
							}
							edit={ edits[ proposal.id ] }
							onEdit={ editFor( proposal.id ) }
						/>
					) ) }
				</div>
			) ) }
			{ extras.faq && (
				<div className="qcwr-card">
					<h3>{ __( 'Add a FAQ at the end', 'chatbot' ) }</h3>
					<Proposal
						proposal={ {
							id: 'faq',
							before: '',
							after: '',
							blocked: '',
						} }
						blocks
						preview={ extras.faq.html }
						decision={ decisions.faq }
						onDecide={ ( value ) => decide( 'faq', value ) }
						edit={ edits.faq }
						onEdit={ editFor( 'faq' ) }
					/>
				</div>
			) }
			{ extras.meta && (
				<div className="qcwr-card">
					<h3>
						{ __(
							'Search title and description',
							'chatbot'
						) }
					</h3>
					{ extras.meta.reason && (
						<p className="qcwr-muted">{ extras.meta.reason }</p>
					) }
					<MetaProposal
						meta={ extras.meta }
						decision={ decisions.meta }
						onDecide={ ( value ) => decide( 'meta', value ) }
						edit={ edits.meta }
						onEdit={ editFor( 'meta' ) }
					/>
				</div>
			) }

			<div className="qcwr-card qcwr-improve-apply">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! accepted.length || review.changed }
					onClick={ apply }
				>
					{ sprintf(
						/* translators: %d: number of accepted changes. */
						_n(
							'Apply %d accepted change',
							'Apply %d accepted changes',
							accepted.length,
							'chatbot'
						),
						accepted.length
					) }
				</Button>
			</div>
		</div>
	);
};

const Applied = ( { result, onDone } ) => {
	const [ state, setState ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const publish = () => {
		setState( 'publishing' );
		writer( `improve/updates/${ result.update_id }/publish`, {
			method: 'POST',
		} )
			.then( ( body ) => setState( body.view_url ) )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				setState( '' );
			} );
	};
	const discard = () =>
		writer( `improve/updates/${ result.update_id }`, { method: 'DELETE' } )
			.then( () => setState( 'discarded' ) )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	return (
		<div className="qcwr-card qcwr-improve-done">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ 'draft' === result.mode && (
				<>
					<h2>
						{ __(
							'Changes saved to the draft',
							'chatbot'
						) }
					</h2>
					<p>
						<a href={ result.edit_url }>
							{ __( 'Open the draft', 'chatbot' ) }
						</a>
					</p>
				</>
			) }
			{ 'update' === result.mode &&
				! /^https?:/.test( state ) &&
				'discarded' !== state && (
					<>
						<h2>
							{ __(
								'Your update is ready to check',
								'chatbot'
							) }
						</h2>
						<p>
							{ __(
								'Nothing on the live page has changed yet. Open the update to read it in the editor, then publish it here or from the editor.',
								'chatbot'
							) }
						</p>
						<div className="qcwr-actions">
							<a
								className="components-button is-secondary"
								href={ result.edit_url }
							>
								{ __( 'Open the update', 'chatbot' ) }
							</a>
							<Button
								variant="primary"
								isBusy={ 'publishing' === state }
								onClick={ publish }
							>
								{ __(
									'Publish the update',
									'chatbot'
								) }
							</Button>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ discard }
							>
								{ __( 'Discard it', 'chatbot' ) }
							</Button>
						</div>
					</>
				) }
			{ /^https?:/.test( state ) && (
				<>
					<h2>
						{ __( 'The live page is updated', 'chatbot' ) }
					</h2>
					<p>
						<a href={ state }>
							{ __( 'View it', 'chatbot' ) }
						</a>{ ' ' }
						{ __(
							'(the previous version is kept in its revisions)',
							'chatbot'
						) }
					</p>
				</>
			) }
			{ 'discarded' === state && (
				<h2>{ __( 'Update discarded', 'chatbot' ) }</h2>
			) }
			<Button variant="link" onClick={ onDone }>
				{ __( 'Improve another page', 'chatbot' ) }
			</Button>
		</div>
	);
};

const ImproveFlow = ( {
	initialJobId = 0,
	initialPost = null,
	initialFix = '',
	onExit,
} ) => {
	const [ jobId, setJobId ] = useState( initialJobId );
	const [ view, setView ] = useState( null );
	const [ applied, setApplied ] = useState( null );
	const [ error, setError ] = useState( '' );

	const refresh = ( id ) =>
		writer( `improve/jobs/${ id }` )
			.then( ( next ) => {
				setView( next );
				if ( next.review && next.review.applied ) {
					setApplied( next.review.applied );
				}
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	useEffect( () => {
		if ( jobId ) {
			refresh( jobId );
		}
	}, [ jobId ] );

	const reset = () => {
		setJobId( 0 );
		setView( null );
		setApplied( null );
	};

	return (
		<div className="qcwr-flow qcwr-improve">
			<div className="qcwr-flow__head">
				<Button variant="link" onClick={ onExit }>
					{ __( '← Back to start', 'chatbot' ) }
				</Button>
				<strong className="qcwr-flow__topic">
					{ __( 'Improve existing content', 'chatbot' ) }
				</strong>
			</div>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ ! jobId && (
				<Pick
					initialPost={ initialPost }
					initialFix={ initialFix }
					onStart={ setJobId }
				/>
			) }
			{ jobId > 0 && ! applied && view && ! view.review && (
				<div className="qcwr-card">
					<h2>{ __( 'Reviewing the page', 'chatbot' ) }</h2>
					<JobProgress
						key={ jobId }
						jobId={ jobId }
						onChange={ ( job ) =>
							'done' === job.status && refresh( jobId )
						}
					/>
				</div>
			) }
			{ jobId > 0 && ! view && ! error && <Spinner /> }
			{ jobId > 0 && ! applied && view && view.review && (
				<Review
					jobId={ jobId }
					review={ view.review }
					onApplied={ setApplied }
				/>
			) }
			{ applied && <Applied result={ applied } onDone={ reset } /> }
		</div>
	);
};

export default ImproveFlow;
