/**
 * "What we know about your site": WPBot Core's shared Site Context.
 * The free scan runs by itself the first time; the knowledge base and
 * customer questions are switched on with one click; the AI summary shows
 * its cost first and can be edited. Every module uses the same context.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { errorMessage, seo, product } from '../../api';
import { AiReason, aiBlocked, useAiReady } from '../../ai';
import JobProgress from '../JobProgress';

/**
 * Cost line: "about $0.004" or "less than $0.01".
 *
 * @param {number} usd Estimate.
 * @return {string} Text.
 */
export const costText = ( usd ) =>
	usd < 0.01
		? __( 'less than $0.01', 'chatbot' )
		: sprintf(
				/* translators: %s: cost in US dollars. */
				__( 'about $%s', 'chatbot' ),
				Number( usd ).toFixed( 2 )
			);

const LAYER_TEXT = {
	site: {
		title: __( 'Your website', 'chatbot' ),
		help: __(
			'Pages, menus, categories and products. Read on this site, free, no AI.',
			'chatbot'
		),
	},
	connected: {
		title: __( 'Data you already have', 'chatbot' ),
		help: __(
			'Top Search Console searches, Keyword Vault, competitors and your location.',
			'chatbot'
		),
	},
	knowledge: {
		title: __( "Your chatbot's knowledge base", 'chatbot' ),
		help: __(
			'What your chatbot already knows (documents, FAQs). Uses the chatbot’s own search.',
			'chatbot'
		),
	},
	questions: {
		title: __( 'Questions customers ask your chatbot', 'chatbot' ),
		help: __(
			'From saved chat sessions, with names, emails and phone numbers removed first.',
			'chatbot'
		),
	},
};

/**
 * What one layer read.
 *
 * @param {Object} source Layer.
 * @return {string} Text.
 */
const layerCount = ( source ) => {
	if ( 'site' === source.id && source.detail && source.count ) {
		return sprintf(
			/* translators: 1: pages, 2: products. */
			__( '%1$d pages, %2$d products', 'chatbot' ),
			source.detail.pages || 0,
			source.detail.products || 0
		);
	}
	if ( 'questions' === source.id && source.count ) {
		return sprintf(
			/* translators: 1: questions, 2: chat sessions. */
			__( '%1$d questions from %2$d chats', 'chatbot' ),
			source.count,
			( source.detail && source.detail.sessions ) || 0
		);
	}
	return source.count
		? sprintf(
				/* translators: %d: items read. */
				_n( '%d item', '%d items', source.count, 'chatbot' ),
				source.count
			)
		: '';
};

const SummaryView = ( { summary } ) => {
	const rows = [
		[ __( 'Business', 'chatbot' ), summary.business ],
		[ __( 'Offers', 'chatbot' ), summary.offers.join( ', ' ) ],
		[ __( 'Customers', 'chatbot' ), summary.audience ],
		[ __( 'Where', 'chatbot' ), summary.places ],
		[
			__( 'Strengths', 'chatbot' ),
			summary.strengths.join( '; ' ),
		],
		[
			__( 'Customers ask', 'chatbot' ),
			summary.questions.join( ' · ' ),
		],
	].filter( ( row ) => row[ 1 ] );
	return (
		<dl className="qcseo-context-summary">
			{ rows.map( ( [ label, value ] ) => (
				<div key={ label }>
					<dt>{ label }</dt>
					<dd>{ value }</dd>
				</div>
			) ) }
		</dl>
	);
};

const SummaryEditor = ( { summary, onSave, onCancel } ) => {
	const [ form, setForm ] = useState( {
		...summary,
		offers: summary.offers.join( '\n' ),
		strengths: summary.strengths.join( '\n' ),
	} );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const lines = ( text ) =>
		text
			.split( '\n' )
			.map( ( s ) => s.trim() )
			.filter( Boolean );
	return (
		<div className="qcseo-form qcseo-context-edit">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Business', 'chatbot' ) }
				rows={ 2 }
				value={ form.business }
				onChange={ set( 'business' ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Offers (one per line)', 'chatbot' ) }
				rows={ 3 }
				value={ form.offers }
				onChange={ set( 'offers' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Customers', 'chatbot' ) }
				value={ form.audience }
				onChange={ set( 'audience' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Where', 'chatbot' ) }
				value={ form.places }
				onChange={ set( 'places' ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Strengths (one per line)', 'chatbot' ) }
				rows={ 3 }
				value={ form.strengths }
				onChange={ set( 'strengths' ) }
			/>
			<div className="qcseo-actions">
				<Button
					variant="primary"
					onClick={ () =>
						onSave( {
							...form,
							offers: lines( form.offers ),
							strengths: lines( form.strengths ),
						} )
					}
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

/**
 * The panel.
 *
 * @param {Object}   props          Props.
 * @param {Function} props.onChange Called with the context after every change.
 * @return {Element} Panel.
 */
const SiteContext = ( { onChange = () => {} } ) => {
	const ai = useAiReady();
	const [ state, setState ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ asking, setAsking ] = useState( false );
	const [ editing, setEditing ] = useState( false );
	const [ job, setJob ] = useState( 0 );
	const [ message, setMessage ] = useState( null );

	const take = ( next ) => {
		setState( next );
		onChange( next );
	};
	const call = ( key, path, body ) => {
		setBusy( key );
		setMessage( null );
		return seo( path, body ? { method: 'POST', data: body } : {} )
			.then( ( next ) => {
				if ( next && next.job_id ) {
					setJob( next.job_id );
				} else {
					take( next );
				}
				return next;
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( '' ) );
	};
	useEffect( () => {
		call( 'load', 'cited/context' );
	}, [] );

	if ( ! state ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<div className="qcseo-card">
				<Spinner /> { __( 'Reading your site…', 'chatbot' ) }
			</div>
		);
	}
	const summary = state.summary;
	const knowledge = state.sources.find( ( s ) => 'knowledge' === s.id );
	return (
		<div className="qcseo-card qcseo-site-context">
			<div className="qcseo-head">
				<h3>
					{ __( 'What we know about your site', 'chatbot' ) }
				</h3>
				<Button
					variant="tertiary"
					size="compact"
					isBusy={ 'scan' === busy }
					disabled={ !! busy }
					onClick={ () => call( 'scan', 'cited/context/scan', {} ) }
				>
					{ __( 'Scan again (free)', 'chatbot' ) }
				</Button>
			</div>
			<p className="qcseo-muted">
				{ sprintf(
					/* translators: %s: product name, for example WPBot. */
					__(
						'One short profile of your business that Get cited by AI, AI Writer and the other %s tools use. The more it knows, the better every suggestion and draft.',
						'chatbot'
					),
					product
				) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<ul className="qcseo-context-layers">
				{ state.sources
					.filter( ( s ) => s.available )
					.map( ( source ) => (
						<li key={ source.id }>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ LAYER_TEXT[ source.id ].title }
								help={
									<>
										{ LAYER_TEXT[ source.id ].help }
										{ source.on && layerCount( source ) && (
											<strong>
												{ ' ' }
												{ layerCount( source ) }
											</strong>
										) }
									</>
								}
								checked={ !! source.on }
								disabled={ ! state.can_edit || !! busy }
								onChange={ ( on ) =>
									call( 'layer', 'cited/context/settings', {
										[ source.id ]: on,
									} )
								}
							/>
						</li>
					) ) }
			</ul>
			{ knowledge && knowledge.writable && state.can_edit && ! job && (
				<p className="qcseo-muted">
					{ knowledge.available && knowledge.count
						? __(
								'Add your main pages and products to the chatbot too, so it answers more questions:',
								'chatbot'
							)
						: __(
								'No chatbot knowledge base yet? Build it from your main pages and products:',
								'chatbot'
							) }{ ' ' }
					<Button
						variant="link"
						disabled={ !! busy }
						onClick={ () =>
							call( 'kb', 'cited/context/knowledge', {} )
						}
					>
						{ __( 'Build it now', 'chatbot' ) }
					</Button>
				</p>
			) }
			{ job > 0 && (
				<JobProgress
					jobId={ job }
					onChange={ ( s ) => {
						if ( s && 'done' === s.status ) {
							setJob( 0 );
							call( 'layer', 'cited/context/settings', {
								knowledge: true,
							} );
						}
					} }
				/>
			) }

			{ summary && ! editing && (
				<>
					<SummaryView summary={ summary } />
					<p className="qcseo-muted">
						{ state.edited
							? __( 'Edited by you.', 'chatbot' )
							: sprintf(
									/* translators: %s: date. */
									__(
										'Built by AI on %s.',
										'chatbot'
									),
									state.summary_at.slice( 0, 10 )
								) }
						{ state.stale &&
							' ' +
								__(
									'Your site changed since then.',
									'chatbot'
								) }
					</p>
				</>
			) }
			{ summary && editing && (
				<SummaryEditor
					summary={ summary }
					onCancel={ () => setEditing( false ) }
					onSave={ ( next ) =>
						call( 'edit', 'cited/context/edit', {
							summary: next,
						} ).then( () => setEditing( false ) )
					}
				/>
			) }
			{ ! editing && (
				<div className="qcseo-actions">
					{ ! asking && (
						<Button
							variant={ summary ? 'secondary' : 'primary' }
							disabled={ !! busy || aiBlocked( ai ) }
							onClick={ () => setAsking( true ) }
						>
							{ summary
								? __( 'Rebuild with AI', 'chatbot' )
								: __( 'Build my context', 'chatbot' ) }
						</Button>
					) }
					{ asking && (
						<>
							<span className="qcseo-muted">
								{ sprintf(
									/* translators: %s: cost. */
									__(
										'One AI request, %s, from your own AI key.',
										'chatbot'
									),
									costText( state.estimate )
								) }
								{ state.edited &&
									' ' +
										__(
											'This replaces your edits.',
											'chatbot'
										) }
							</span>
							<Button
								variant="primary"
								isBusy={ 'summary' === busy }
								disabled={ !! busy }
								onClick={ () =>
									call(
										'summary',
										'cited/context/summary',
										{}
									).then( () => setAsking( false ) )
								}
							>
								{ __( 'Build it', 'chatbot' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => setAsking( false ) }
							>
								{ __( 'Cancel', 'chatbot' ) }
							</Button>
						</>
					) }
					{ summary && state.can_edit && (
						<Button
							variant="tertiary"
							onClick={ () => setEditing( true ) }
						>
							{ __( 'Edit', 'chatbot' ) }
						</Button>
					) }
					{ aiBlocked( ai ) && <AiReason /> }
				</div>
			) }
			{ state.can_edit && (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __(
						'Keep it up to date (rebuilds weekly only when your site changed)',
						'chatbot'
					) }
					checked={ !! state.settings.auto_update }
					disabled={ !! busy }
					onChange={ ( on ) =>
						call( 'layer', 'cited/context/settings', {
							auto_update: on,
						} )
					}
				/>
			) }
		</div>
	);
};

export default SiteContext;
