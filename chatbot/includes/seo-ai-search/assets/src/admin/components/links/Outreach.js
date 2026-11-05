/**
 * Outreach: one pipeline for link requests, guest posts and
 * collaborations. AI drafts with a "Make it yours" question; copy or open
 * the message in your own email (sending one by one is Pro). Nothing is
 * ever sent without your click.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { STEP, TYPE, options } from './labels';
import { AiReason, aiBlocked, useAiReady } from '../../ai';

const HISTORY = {
	created: __( 'Added', 'chatbot' ),
	status: __( 'Status', 'chatbot' ),
	note: __( 'Note', 'chatbot' ),
	draft: __( 'Draft written', 'chatbot' ),
	copied: __( 'Message copied', 'chatbot' ),
	opened: __( 'Opened in email', 'chatbot' ),
	sent: __( 'Sent', 'chatbot' ),
	link: __( 'Link added to the monitor', 'chatbot' ),
	reminder: __( 'Follow-up reminder', 'chatbot' ),
};

const Contact = ( { id, onBack, sending } ) => {
	const aiOff = aiBlocked( useAiReady() );
	const [ item, setItem ] = useState( null );
	const [ form, setForm ] = useState( {} );
	const [ note, setNote ] = useState( '' );
	const [ answer, setAnswer ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const load = useCallback( () => {
		seo( `outreach/${ id }` ).then( ( r ) => {
			setItem( r );
			setForm( r );
		} );
	}, [ id ] );
	useEffect( load, [ load ] );
	if ( ! item ) {
		return <Spinner />;
	}
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const save = ( extra = {} ) => {
		setMessage( '' );
		return seo( `outreach/${ id }/save`, {
			method: 'POST',
			data: {
				name: form.name,
				website: form.website,
				contact_name: form.contact_name,
				contact_email: form.contact_email,
				target_url: form.target_url,
				link_url: form.link_url,
				anchor: form.anchor,
				follow_up: form.follow_up,
				notes: form.notes,
				reciprocal_url: form.reciprocal_url || '',
				...extra,
			},
		} )
			.then( ( r ) => {
				setItem( r );
				setForm( r );
				setNote( '' );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	};
	const draft = ( args = {} ) => {
		setBusy( true );
		setMessage( '' );
		seo( `outreach/${ id }/draft`, { method: 'POST', data: args } )
			.then( ( r ) => {
				setItem( r );
				setForm( r );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const d = form.draft || null;
	const setDraft = ( key ) => ( value ) =>
		setForm( { ...form, draft: { ...d, [ key ]: value } } );
	const text = d ? `${ d.subject }\n\n${ d.body }` : '';
	const copy = () => {
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard.writeText( text );
		}
		seo( `outreach/${ id }/log`, {
			method: 'POST',
			data: { kind: 'copied' },
		} ).then( setItem );
	};
	const mailto = d
		? `mailto:${ encodeURIComponent( form.contact_email || '' ) }?subject=${ encodeURIComponent( d.subject ) }&body=${ encodeURIComponent( d.body ) }`
		: '';
	const won = {
		link_request: 'won',
		guest_post: 'published',
		collaboration: 'live',
	}[ item.type ];
	const can = data.can;
	return (
		<div className="qcseo-card qcseo-contact">
			<Button variant="link" onClick={ onBack }>
				{ __( '← All contacts', 'chatbot' ) }
			</Button>
			<h3>
				{ item.name || item.website }{ ' ' }
				<span className="qcseo-badge">{ TYPE[ item.type ] }</span>
			</h3>
			<div
				className="qcseo-steps"
				role="group"
				aria-label={ __( 'Status', 'chatbot' ) }
			>
				{ item.steps.map( ( step ) => (
					<Button
						key={ step }
						variant={
							step === item.status ? 'primary' : 'secondary'
						}
						size="compact"
						disabled={ ! can.use || step === item.status }
						onClick={ () => save( { status: step } ) }
					>
						{ STEP[ step ] || step }
					</Button>
				) ) }
			</div>
			{ item.warnings.map( ( w ) => (
				<p key={ w } className="qcseo-notice-card">
					{ w }
				</p>
			) ) }
			{ message && <p className="qcseo-error">{ message }</p> }
			<div className="qcseo-grid">
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Name', 'chatbot' ) }
					value={ form.name || '' }
					onChange={ set( 'name' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Website', 'chatbot' ) }
					value={ form.website || '' }
					onChange={ set( 'website' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Contact name', 'chatbot' ) }
					value={ form.contact_name || '' }
					onChange={ set( 'contact_name' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __(
						'Contact email (from their published contact page)',
						'chatbot'
					) }
					type="email"
					value={ form.contact_email || '' }
					onChange={ set( 'contact_email' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Your page to point to', 'chatbot' ) }
					value={ form.target_url || '' }
					onChange={ set( 'target_url' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __(
						'Anchor text you suggest',
						'chatbot'
					) }
					help={ __(
						'Your brand name or the page title works best.',
						'chatbot'
					) }
					value={ form.anchor || '' }
					onChange={ set( 'anchor' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Follow up on', 'chatbot' ) }
					type="date"
					value={ form.follow_up || '' }
					onChange={ set( 'follow_up' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ sprintf(
						/* translators: %s: the status that means a link was earned. */
						__(
							'Page that links to you (needed for "%s")',
							'chatbot'
						),
						STEP[ won ]
					) }
					value={ form.link_url || '' }
					onChange={ set( 'link_url' ) }
					__nextHasNoMarginBottom
				/>
				{ 'collaboration' === item.type && (
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Your page linking back to them (optional, for exchange records)',
							'chatbot'
						) }
						value={ form.reciprocal_url || '' }
						onChange={ set( 'reciprocal_url' ) }
						__nextHasNoMarginBottom
					/>
				) }
				<TextareaControl
					label={ __( 'Notes', 'chatbot' ) }
					value={ form.notes || '' }
					onChange={ set( 'notes' ) }
					rows={ 2 }
					__nextHasNoMarginBottom
				/>
			</div>
			{ can.use && (
				<Button variant="secondary" onClick={ () => save() }>
					{ __( 'Save details', 'chatbot' ) }
				</Button>
			) }

			<h4>{ __( 'Message', 'chatbot' ) }</h4>
			{ can.use && (
				<Button
					variant={ d ? 'secondary' : 'primary' }
					isBusy={ busy }
					disabled={ busy || !! aiOff }
					onClick={ () => draft() }
				>
					{ d
						? __( 'Write again with AI', 'chatbot' )
						: __( 'Write with AI', 'chatbot' ) }
				</Button>
			) }
			{ can.use && aiOff && <AiReason /> }
			{ d && d.ideas && d.ideas.length > 0 && (
				<ol className="qcseo-ideas">
					{ d.ideas.map( ( idea, index ) => (
						<li
							key={ idea.title }
							className={ index === d.chosen ? 'is-chosen' : '' }
						>
							<strong>{ idea.title }</strong> { idea.angle }
							{ idea.link && (
								<small className="qcseo-muted">
									{ ' ' }
									{ sprintf(
										/* translators: %s: where the link fits. */
										__( 'Link: %s', 'chatbot' ),
										idea.link
									) }
								</small>
							) }{ ' ' }
							{ can.use && (
								<Button
									variant="link"
									disabled={ busy }
									onClick={ () => draft( { idea: index } ) }
								>
									{ 'guest_post' === item.type
										? __( 'Pitch this', 'chatbot' )
										: __(
												'Propose this',
												'chatbot'
											) }
								</Button>
							) }{ ' ' }
							{ can.use && data.writer && (
								<Button
									variant="link"
									onClick={ () =>
										seo( `outreach/${ id }/write`, {
											method: 'POST',
											data: { idea: index },
										} )
											.then( ( r ) => {
												window.location.href = r.url;
											} )
											.catch( ( e ) =>
												setMessage( errorMessage( e ) )
											)
									}
								>
									{ __(
										'Write it in AI Writer',
										'chatbot'
									) }
								</Button>
							) }
						</li>
					) ) }
				</ol>
			) }
			{ d && d.question && (
				<div className="qcseo-make-it-yours">
					<TextareaControl
						label={ sprintf(
							/* translators: %s: question from the AI. */
							__( 'Make it yours: %s', 'chatbot' ),
							d.question
						) }
						value={ answer }
						onChange={ setAnswer }
						rows={ 2 }
						__nextHasNoMarginBottom
					/>
					{ can.use && (
						<Button
							variant="secondary"
							disabled={ ! answer || busy }
							onClick={ () => draft( { answer } ) }
						>
							{ __( 'Use my answer', 'chatbot' ) }
						</Button>
					) }
				</div>
			) }
			{ d && (
				<div className="qcseo-draft">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Subject', 'chatbot' ) }
						value={ d.subject }
						onChange={ setDraft( 'subject' ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Message', 'chatbot' ) }
						value={ d.body }
						onChange={ setDraft( 'body' ) }
						rows={ 9 }
						__nextHasNoMarginBottom
					/>
					<div className="qcseo-actions">
						{ can.use && (
							<Button
								variant="secondary"
								onClick={ () => save( { draft: form.draft } ) }
							>
								{ __( 'Save message', 'chatbot' ) }
							</Button>
						) }
						<Button variant="secondary" onClick={ copy }>
							{ __( 'Copy', 'chatbot' ) }
						</Button>
						<Button
							variant="secondary"
							href={ mailto }
							onClick={ () =>
								seo( `outreach/${ id }/log`, {
									method: 'POST',
									data: { kind: 'opened' },
								} ).then( setItem )
							}
						>
							{ __( 'Open in my email', 'chatbot' ) }
						</Button>
						{ applyFilters(
							'qcld_wpbot_seo.outreach.actions',
							null,
							{ item, form, sending, reload: load }
						) }
					</div>
				</div>
			) }

			<h4>{ __( 'History', 'chatbot' ) }</h4>
			{ can.use && (
				<div className="qcseo-grid">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Add a note', 'chatbot' ) }
						value={ note }
						onChange={ setNote }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						disabled={ ! note }
						onClick={ () => save( { note } ) }
					>
						{ __( 'Add', 'chatbot' ) }
					</Button>
				</div>
			) }
			<ul className="qcseo-history">
				{ item.history.map( ( h, index ) => (
					<li key={ index }>
						<small className="qcseo-muted">
							{ h.created_at.slice( 0, 16 ) }
						</small>{ ' ' }
						{ HISTORY[ h.kind ] || h.kind }
						{ h.to_status &&
							`: ${ STEP[ h.to_status ] || h.to_status }` }
						{ h.detail && ` — ${ h.detail }` }
					</li>
				) ) }
			</ul>
			{ can.use && (
				<Button
					variant="link"
					isDestructive
					onClick={ () =>
						seo( `outreach/${ id }/delete`, {
							method: 'POST',
						} ).then( onBack )
					}
				>
					{ __( 'Delete this contact', 'chatbot' ) }
				</Button>
			) }
		</div>
	);
};

const Outreach = ( { openId } ) => {
	const [ filter, setFilter ] = useState( { type: '', status: '' } );
	const [ view, setView ] = useState( null );
	const [ open, setOpen ] = useState( openId || 0 );
	const [ form, setForm ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const load = useCallback( () => {
		seo( `outreach?type=${ filter.type }&status=${ filter.status }` )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [ filter ] );
	useEffect( load, [ load ] );
	useEffect( () => {
		if ( openId ) {
			setOpen( openId );
		}
	}, [ openId ] );
	if ( open ) {
		return (
			<Contact
				id={ open }
				sending={ view ? view.sending : null }
				onBack={ () => {
					setOpen( 0 );
					load();
				} }
			/>
		);
	}
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const steps = filter.type ? view.types[ filter.type ] : [];
	return (
		<div className="qcseo-card qcseo-outreach">
			<div className="qcseo-head">
				<h3>{ __( 'Outreach', 'chatbot' ) }</h3>
				{ data.can.use && ! form && (
					<Button
						variant="secondary"
						onClick={ () =>
							setForm( {
								type: 'link_request',
								name: '',
								website: '',
							} )
						}
					>
						{ __( 'Add a contact', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcseo-muted">
				{ __(
					'Everyone you reach out to, in one list. Not a mass-mail tool: write each message, make it yours, and send it yourself.',
					'chatbot'
				) }
				{ view.due > 0 && ' ' }
				{ view.due > 0 && (
					<strong>
						{ sprintf(
							/* translators: %d: follow-ups due. */
							__( '%d follow-ups due.', 'chatbot' ),
							view.due
						) }
					</strong>
				) }
			</p>
			{ form && (
				<div className="qcseo-grid">
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Type', 'chatbot' ) }
						value={ form.type }
						options={ options( TYPE ) }
						onChange={ ( type ) => setForm( { ...form, type } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Name', 'chatbot' ) }
						value={ form.name }
						onChange={ ( name ) => setForm( { ...form, name } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Website', 'chatbot' ) }
						value={ form.website }
						onChange={ ( website ) =>
							setForm( { ...form, website } )
						}
						__nextHasNoMarginBottom
					/>
					<div>
						<Button
							variant="primary"
							onClick={ () =>
								seo( 'outreach/add', {
									method: 'POST',
									data: form,
								} )
									.then( ( r ) => {
										setForm( null );
										setOpen( r.id );
									} )
									.catch( ( e ) =>
										setMessage( errorMessage( e ) )
									)
							}
						>
							{ __( 'Add', 'chatbot' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							onClick={ () => setForm( null ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
			<div className="qcseo-filters">
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Type', 'chatbot' ) }
					value={ filter.type }
					options={ options(
						TYPE,
						__( 'All types', 'chatbot' )
					) }
					onChange={ ( type ) => setFilter( { type, status: '' } ) }
					__nextHasNoMarginBottom
				/>
				{ filter.type && (
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Status', 'chatbot' ) }
						value={ filter.status }
						options={ [
							{
								value: '',
								label: __( 'All', 'chatbot' ),
							},
							...steps.map( ( s ) => ( {
								value: s,
								label: STEP[ s ] || s,
							} ) ),
						] }
						onChange={ ( status ) =>
							setFilter( { ...filter, status } )
						}
						__nextHasNoMarginBottom
					/>
				) }
			</div>
			{ 0 === view.items.length ? (
				<p className="qcseo-muted">
					{ __(
						'No contacts yet. Start outreach from a prospect, or add a contact.',
						'chatbot'
					) }
				</p>
			) : (
				<table className="widefat striped qcseo-table qcseo-outreach-table">
					<thead>
						<tr>
							<th>{ __( 'Contact', 'chatbot' ) }</th>
							<th>{ __( 'Type', 'chatbot' ) }</th>
							<th>{ __( 'Status', 'chatbot' ) }</th>
							<th>{ __( 'Follow up', 'chatbot' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ view.items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									{ item.name || item.website }
									<br />
									<small className="qcseo-muted">
										{ item.host }
									</small>
								</td>
								<td>{ TYPE[ item.type ] }</td>
								<td>{ STEP[ item.status ] || item.status }</td>
								<td className={ item.due ? 'qcseo-error' : '' }>
									{ item.follow_up }
								</td>
								<td>
									<Button
										variant="secondary"
										size="compact"
										onClick={ () => setOpen( item.id ) }
									>
										{ __( 'Open', 'chatbot' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

export default Outreach;
