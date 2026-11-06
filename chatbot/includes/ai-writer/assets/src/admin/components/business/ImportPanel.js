/**
 * "Fill from your content" (CR-15): pick pages of the site, paste web
 * addresses or upload a document; see the cost; AI Writer reads each source
 * and proposes items; the owner ticks what to add. Nothing is saved before
 * "Add selected". Quotes and customer phrases are proposed only when their
 * exact words are in the source; items from a PDF are marked "check the
 * wording" because they can't be checked here.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';

const QUOTES = [ 'review', 'testimonial' ];

/**
 * Web addresses typed one per line.
 *
 * @param {string} text Text.
 * @return {string[]} Addresses.
 */
const addresses = ( text ) =>
	text
		.split( /\s+/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => /^https?:\/\/\S+$/i.test( line ) );

/**
 * One proposal in the review list.
 *
 * @param {Object}                props          Props.
 * @param {Object}                props.item     Proposal.
 * @param {string}                props.target   kit or words.
 * @param {Object}                props.types    Kit type labels.
 * @param {boolean}               props.ticked   Ticked to add.
 * @param {(on: boolean) => void} props.onTick   Tick changed.
 * @param {boolean}               props.quotable Quote may be published.
 * @param {(on: boolean) => void} props.onQuote  Quote tick changed.
 * @return {Element} Row.
 */
const Proposal = ( {
	item,
	target,
	types,
	ticked,
	onTick,
	quotable,
	onQuote,
} ) => {
	const words = 'words' === target;
	const type = ! words && types[ item.type ] ? types[ item.type ].label : '';
	return (
		<li className="qcwr-import__item">
			<CheckboxControl
				label={
					words ? (
						<strong>{ item.phrase }</strong>
					) : (
						<>
							<span className="qcwr-badge">{ type }</span>{ ' ' }
							<strong>{ item.label }</strong>
						</>
					)
				}
				checked={ ticked }
				onChange={ onTick }
				__nextHasNoMarginBottom
			/>
			<div className="qcwr-import__detail">
				{ ! words && item.body && item.body !== item.label && (
					<p>
						{ QUOTES.includes( item.type )
							? `“${ item.body }”`
							: item.body }
					</p>
				) }
				{ ! words && ( item.value || item.who ) && (
					<p className="qcwr-muted">
						{ [
							item.value,
							item.who && item.who !== item.value ? item.who : '',
						]
							.filter( Boolean )
							.join( ' · ' ) }
					</p>
				) }
				<p className="qcwr-muted">
					{ __( 'From:', 'chatbot' ) }{ ' ' }
					{ item.source_url ? (
						<a
							href={ item.source_url }
							target="_blank"
							rel="noreferrer"
						>
							{ item.source_label }
						</a>
					) : (
						item.source_label
					) }
				</p>
				{ ! item.checked && (
					<p className="qcwr-import__check">
						{ __(
							'From a PDF: check the wording against the document before you add it.',
							'chatbot'
						) }
					</p>
				) }
				{ ! words && QUOTES.includes( item.type ) && ticked && (
					<CheckboxControl
						label={ __(
							'May be quoted (the customer agreed)',
							'chatbot'
						) }
						checked={ quotable }
						onChange={ onQuote }
						__nextHasNoMarginBottom
					/>
				) }
			</div>
		</li>
	);
};

/**
 * The panel.
 *
 * @param {Object}     props           Props.
 * @param {string}     props.target    kit or words.
 * @param {Object}     props.types     Kit type labels (kit only).
 * @param {() => void} props.onApplied Called after items were added.
 * @return {Element|null} Panel.
 */
const ImportPanel = ( { target, types = {}, onApplied } ) => {
	const [ open, setOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState( [] );
	const [ pages, setPages ] = useState( [] );
	const [ urls, setUrls ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ estimate, setEstimate ] = useState( null );
	const [ running, setRunning ] = useState( '' );
	const [ problems, setProblems ] = useState( [] );
	const [ items, setItems ] = useState( [] );
	const [ ticked, setTicked ] = useState( {} );
	const [ quotable, setQuotable ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const enabled = !! (
		data.import &&
		data.can &&
		data.can.settings &&
		data.can.use
	);

	const tickAll = ( list ) =>
		setTicked( ( was ) => {
			const next = { ...was };
			list.forEach( ( item ) => {
				if ( undefined === next[ item.id ] ) {
					next[ item.id ] = !! item.checked;
				}
			} );
			return next;
		} );

	// Proposals still waiting from an earlier visit.
	useEffect( () => {
		if ( ! enabled ) {
			return;
		}
		writer( `business/import?target=${ target }` )
			.then( ( body ) => {
				if ( body && body.items && body.items.length ) {
					setItems( body.items );
					tickAll( body.items );
					setOpen( true );
				}
			} )
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ target ] );

	useEffect( () => {
		if ( ! open || ! enabled ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			writer( `write/sources?search=${ encodeURIComponent( search ) }` )
				.then( setFound )
				.catch( () => {} );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ search, open, enabled ] );

	if ( ! enabled ) {
		return null;
	}
	const max = estimate ? estimate.max_pages : data.importMax || 5;
	const typed = addresses( urls );
	const count = pages.length + typed.length + ( file ? 1 : 0 );
	const tooMany = pages.length + typed.length > max;
	const pdfBlocked =
		file && /\.pdf$/i.test( file.name ) && estimate && ! estimate.reads_pdf;

	const ask = () =>
		writer( 'business/import/estimate', {
			method: 'POST',
			data: { sources: Math.max( 1, count ) },
		} )
			.then( setEstimate )
			.catch( ( err ) =>
				setNotice( { status: 'error', text: errorMessage( err ) } )
			);

	const add = ( body ) => {
		if ( body && body.items ) {
			setItems( ( was ) => {
				const have = was.map( ( item ) => item.id );
				return [
					...was,
					...body.items.filter(
						( item ) => ! have.includes( item.id )
					),
				];
			} );
			tickAll( body.items );
		}
	};

	const run = async () => {
		setProblems( [] );
		setNotice( null );
		const sources = [
			...pages.map( ( page ) => ( {
				label: page.title,
				source: { type: 'post', id: page.id },
			} ) ),
			...typed.map( ( url ) => ( {
				label: url,
				source: { type: 'url', url },
			} ) ),
		];
		const total = sources.length + ( file ? 1 : 0 );
		let step = 0;
		for ( const one of sources ) {
			step++;
			setRunning(
				sprintf(
					/* translators: 1: number, 2: total, 3: page name. */
					__( 'Reading %1$d of %2$d: %3$s', 'chatbot' ),
					step,
					total,
					one.label
				)
			);
			try {
				add(
					await writer( 'business/import', {
						method: 'POST',
						data: { target, source: one.source },
					} )
				);
			} catch ( err ) {
				setProblems( ( was ) => [
					...was,
					`${ one.label }: ${ errorMessage( err ) }`,
				] );
			}
		}
		if ( file ) {
			step++;
			setRunning(
				sprintf(
					/* translators: 1: number, 2: total, 3: file name. */
					__( 'Reading %1$d of %2$d: %3$s', 'chatbot' ),
					step,
					total,
					file.name
				)
			);
			const form = new window.FormData();
			form.append( 'target', target );
			form.append( 'file', file );
			try {
				add(
					await apiFetch( {
						path: '/wpbot-writer/v1/business/import/file',
						method: 'POST',
						body: form,
					} )
				);
			} catch ( err ) {
				setProblems( ( was ) => [
					...was,
					`${ file.name }: ${ errorMessage( err ) }`,
				] );
			}
		}
		setRunning( '' );
		setEstimate( null );
		setPages( [] );
		setUrls( '' );
		setFile( null );
	};

	const chosen = items.filter( ( item ) => ticked[ item.id ] );
	const apply = () =>
		writer( 'business/import/apply', {
			method: 'POST',
			data: {
				target,
				ids: chosen.map( ( item ) => item.id ),
				quotable: chosen
					.filter( ( item ) => quotable[ item.id ] )
					.map( ( item ) => item.id ),
			},
		} )
			.then( ( body ) => {
				setItems( body.left || [] );
				setNotice( {
					status:
						body.failed && body.failed.length
							? 'warning'
							: 'success',
					text: [
						sprintf(
							/* translators: %d: number of items. */
							_n(
								'%d item added.',
								'%d items added.',
								body.saved,
								'chatbot'
							),
							body.saved
						),
						...( body.failed || [] ),
					].join( ' ' ),
				} );
				if ( onApplied ) {
					onApplied();
				}
			} )
			.catch( ( err ) =>
				setNotice( { status: 'error', text: errorMessage( err ) } )
			);
	const discard = () =>
		writer( `business/import?target=${ target }`, { method: 'DELETE' } )
			.then( () => {
				setItems( [] );
				setTicked( {} );
			} )
			.catch( ( err ) =>
				setNotice( { status: 'error', text: errorMessage( err ) } )
			);

	return (
		<div className="qcwr-import">
			<div className="qcwr-import__head">
				<h3>{ __( 'Fill from your content', 'chatbot' ) }</h3>
				{ ! open && (
					<Button variant="primary" onClick={ () => setOpen( true ) }>
						{ __( 'Fill from your content', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcwr-muted">
				{ 'kit' === target
					? __(
							'AI Writer reads your pages, any web page or a document, and proposes reviews, numbers, guarantees and awards it finds there. You choose what to add; quotes are proposed only when their exact words are in the source.',
							'chatbot'
						)
					: __(
							'AI Writer reads your pages, any web page or a document, and proposes the phrases customers use there (exact words only). You choose what to add.',
							'chatbot'
						) }
			</p>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ open && ! running && (
				<div className="qcwr-import__form">
					<div>
						<TextControl
							label={ __(
								'Pages of your site',
								'chatbot'
							) }
							placeholder={ __(
								'Search your pages',
								'chatbot'
							) }
							value={ search }
							onChange={ setSearch }
							__nextHasNoMarginBottom
						/>
						<ul className="qcwr-import__pages">
							{ found.map( ( post ) => (
								<li key={ post.id }>
									<CheckboxControl
										label={ post.title }
										checked={ pages.some(
											( page ) => page.id === post.id
										) }
										onChange={ ( on ) => {
											setEstimate( null );
											setPages(
												on
													? [ ...pages, post ]
													: pages.filter(
															( page ) =>
																page.id !==
																post.id
														)
											);
										} }
										__nextHasNoMarginBottom
									/>
								</li>
							) ) }
						</ul>
					</div>
					<div>
						<TextareaControl
							label={ __(
								'Web addresses (one per line)',
								'chatbot'
							) }
							placeholder="https://"
							value={ urls }
							onChange={ ( value ) => {
								setEstimate( null );
								setUrls( value );
							} }
							rows={ 3 }
							__nextHasNoMarginBottom
						/>
						<p className="qcwr-import__file">
							<label htmlFor={ `qcwr-import-file-${ target }` }>
								{ __( 'Upload a document', 'chatbot' ) }
							</label>
							<input
								id={ `qcwr-import-file-${ target }` }
								type="file"
								accept=".docx,.pdf,.txt,.md,.csv"
								onChange={ ( event ) => {
									setEstimate( null );
									setFile( event.target.files[ 0 ] || null );
								} }
							/>
							<span className="qcwr-muted">
								{ __(
									'Word, PDF, text or CSV, up to 10 MB. The file is read and then deleted; it is not kept.',
									'chatbot'
								) }
							</span>
						</p>
					</div>
					{ tooMany && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: most pages per import. */
								__(
									'Choose at most %d pages and addresses per import.',
									'chatbot'
								),
								max
							) }
						</Notice>
					) }
					{ pdfBlocked && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								"Your AI provider can't read PDF files. Save the document as Word (.docx) or text, or use OpenAI, Claude, Gemini or OpenRouter.",
								'chatbot'
							) }
						</Notice>
					) }
					<div className="qcwr-actions">
						{ ! estimate ? (
							<Button
								variant="secondary"
								disabled={ ! count || tooMany }
								onClick={ ask }
							>
								{ __( 'Show the cost', 'chatbot' ) }
							</Button>
						) : (
							<>
								<span>
									{ sprintf(
										/* translators: 1: number of sources, 2: cost in US dollars. */
										_n(
											'%1$d source, about $%2$s.',
											'%1$d sources, about $%2$s.',
											estimate.calls,
											'chatbot'
										),
										estimate.calls,
										Number( estimate.usd ).toFixed( 2 )
									) }
								</span>
								<Button
									variant="primary"
									disabled={ tooMany || pdfBlocked }
									onClick={ run }
								>
									{ __( 'Find items', 'chatbot' ) }
								</Button>
							</>
						) }
					</div>
				</div>
			) }
			{ running && (
				<p className="qcwr-import__running">
					<Spinner /> { running }
				</p>
			) }
			{ problems.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul className="qcwr-list">
						{ problems.map( ( text ) => (
							<li key={ text }>{ text }</li>
						) ) }
					</ul>
				</Notice>
			) }
			{ items.length > 0 && (
				<div className="qcwr-import__review">
					<h4>
						{ sprintf(
							/* translators: %d: number of proposals. */
							_n(
								'%d proposal',
								'%d proposals',
								items.length,
								'chatbot'
							),
							items.length
						) }
					</h4>
					<ul className="qcwr-import__items">
						{ items.map( ( item ) => (
							<Proposal
								key={ item.id }
								item={ item }
								target={ target }
								types={ types }
								ticked={ !! ticked[ item.id ] }
								onTick={ ( on ) =>
									setTicked( { ...ticked, [ item.id ]: on } )
								}
								quotable={ !! quotable[ item.id ] }
								onQuote={ ( on ) =>
									setQuotable( {
										...quotable,
										[ item.id ]: on,
									} )
								}
							/>
						) ) }
					</ul>
					<div className="qcwr-actions">
						<Button
							variant="primary"
							disabled={ ! chosen.length }
							onClick={ apply }
						>
							{ sprintf(
								/* translators: %d: number of ticked items. */
								__( 'Add selected (%d)', 'chatbot' ),
								chosen.length
							) }
						</Button>
						<Button variant="tertiary" onClick={ discard }>
							{ __( 'Discard all', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
};

export default ImportPanel;
