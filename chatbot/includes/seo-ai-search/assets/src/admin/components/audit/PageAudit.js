/**
 * Page Audit (F2, F4): one page's score, the full checklist in groups
 * (pass, warning, fail, tips), AI fixes for alt text, an FAQ and the
 * introduction (reviewed before saving, undoable), and the audit history
 * with the score trend.
 */
import {
	Button,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { AiReason, aiBlocked, useAiReady } from '../../ai';
import Sparkline from '../Sparkline';

const GROUPS = [
	[ 'search', __( 'Search appearance', 'chatbot' ) ],
	[ 'keyword', __( 'Keyword', 'chatbot' ) ],
	[ 'content', __( 'Content', 'chatbot' ) ],
	[ 'links', __( 'Links', 'chatbot' ) ],
	[ 'images', __( 'Images', 'chatbot' ) ],
	[ 'ai', __( 'AI search readiness', 'chatbot' ) ],
	[ 'tips', __( 'Tips (not scored)', 'chatbot' ) ],
];

const STATUS = {
	pass: [ '✓', __( 'Passed', 'chatbot' ) ],
	warning: [ '!', __( 'Warning', 'chatbot' ) ],
	fail: [ '✗', __( 'Needs a fix', 'chatbot' ) ],
	tip: [ 'i', __( 'Tip', 'chatbot' ) ],
	info: [ '?', __( 'Not checked', 'chatbot' ) ],
};

const TRIGGERS = {
	manual: __( 'Run by hand', 'chatbot' ),
	publish: __( 'Saved', 'chatbot' ),
	scan: __( 'Site check', 'chatbot' ),
	scheduled: __( 'Scheduled', 'chatbot' ),
	fix: __( 'After a fix', 'chatbot' ),
	refresh: __( 'Refreshed', 'chatbot' ),
};

/**
 * Review panel for an AI content fix.
 *
 * @param {Object}                 props          Props.
 * @param {number}                 props.postId   Page.
 * @param {string}                 props.kind     alt, faq or intro.
 * @param {string}                 props.keyword  Target keyword.
 * @param {(view: Object) => void} props.onSaved  After saving.
 * @param {() => void}             props.onCancel Close.
 * @return {Element} Panel.
 */
const ContentFix = ( { postId, kind, keyword, onSaved, onCancel } ) => {
	const [ proposal, setProposal ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		seo( `fixes/${ postId }/content`, {
			method: 'POST',
			data: { kind, keyword },
		} )
			.then( setProposal )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [ postId, kind, keyword ] );
	if ( ! proposal ) {
		return (
			<div className="qcseo-proposal">
				{ message ? (
					<p className="qcseo-error">{ message }</p>
				) : (
					<Spinner />
				) }
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Close', 'chatbot' ) }
				</Button>
			</div>
		);
	}
	const save = () => {
		setBusy( true );
		let value = {};
		if ( 'alt' === kind ) {
			value = { images: proposal.images };
		} else if ( 'faq' === kind ) {
			value = { faq: proposal.faq };
		} else {
			value = { intro: proposal.intro };
		}
		seo( `fixes/${ postId }/content/apply`, {
			method: 'POST',
			data: { kind, value },
		} )
			.then( onSaved )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<div className="qcseo-proposal">
			<h4>{ __( 'Review before saving', 'chatbot' ) }</h4>
			{ 'alt' === kind &&
				proposal.images.map( ( image, index ) => (
					<div key={ image.src } className="qcseo-alt-row">
						<img src={ image.src } alt="" width="64" height="64" />
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Alt text', 'chatbot' ) }
							value={ image.alt }
							onChange={ ( alt ) => {
								const images = [ ...proposal.images ];
								images[ index ] = { ...image, alt };
								setProposal( { ...proposal, images } );
							} }
							__nextHasNoMarginBottom
						/>
					</div>
				) ) }
			{ 'faq' === kind &&
				proposal.faq.map( ( pair, index ) => (
					<div key={ index } className="qcseo-faq-row">
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Question', 'chatbot' ) }
							value={ pair.q }
							onChange={ ( q ) => {
								const faq = [ ...proposal.faq ];
								faq[ index ] = { ...pair, q };
								setProposal( { ...proposal, faq } );
							} }
							__nextHasNoMarginBottom
						/>
						<TextareaControl
							label={ __( 'Answer', 'chatbot' ) }
							value={ pair.a }
							rows={ 2 }
							onChange={ ( a ) => {
								const faq = [ ...proposal.faq ];
								faq[ index ] = { ...pair, a };
								setProposal( { ...proposal, faq } );
							} }
							__nextHasNoMarginBottom
						/>
					</div>
				) ) }
			{ 'intro' === kind && (
				<>
					<p className="qcseo-muted">
						{ __( 'Now:', 'chatbot' ) }{ ' ' }
						{ proposal.current ||
							__( '(no introduction)', 'chatbot' ) }
					</p>
					<TextareaControl
						label={ __( 'New introduction', 'chatbot' ) }
						value={ proposal.intro }
						rows={ 4 }
						onChange={ ( intro ) =>
							setProposal( { ...proposal, intro } )
						}
						__nextHasNoMarginBottom
					/>
				</>
			) }
			<p className="qcseo-muted">
				{ 'faq' === kind
					? __(
							'The FAQ is added at the end of the page. Check every answer is true for your business.',
							'chatbot'
						)
					: __(
							'Saved straight to the page; you can undo it under Recent changes.',
							'chatbot'
						) }
			</p>
			{ data.can.publish ? (
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ save }
				>
					{ __( 'Save to the page', 'chatbot' ) }
				</Button>
			) : (
				<p className="qcseo-muted">
					{ __(
						'Saving needs the "Publish" permission.',
						'chatbot'
					) }
				</p>
			) }{ ' ' }
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'chatbot' ) }
			</Button>
			{ message && <p className="qcseo-error">{ message }</p> }
		</div>
	);
};

const CONTENT_FIXES = {
	alt: __( 'Write alt text with AI', 'chatbot' ),
	faq: __( 'Write an FAQ with AI', 'chatbot' ),
	intro: __( 'Rewrite the introduction with AI', 'chatbot' ),
};

/**
 * @param {Object}                 props          Props.
 * @param {Object}                 props.view     Audit view (from /audit/run or /audit/page).
 * @param {(view: Object) => void} props.onChange New view after a fix.
 * @return {Element} Page audit.
 */
const PageAudit = ( { view, onChange } ) => {
	const aiOff = aiBlocked( useAiReady() );
	const [ fixing, setFixing ] = useState( '' );
	const [ past, setPast ] = useState( null );
	const counts = { pass: 0, warning: 0, fail: 0 };
	view.checks.forEach( ( c ) => {
		if ( undefined !== counts[ c.status ] ) {
			counts[ c.status ]++;
		}
	} );
	const kindFor = ( check ) => {
		if ( 'alt' === check.fix ) {
			return 'alt';
		}
		if ( 'faq' === check.fix || 'ai_faq' === check.id ) {
			return 'faq';
		}
		return 'intro' === check.fix ? 'intro' : '';
	};
	return (
		<div className="qcseo-page-audit">
			<div className="qcseo-audit-head">
				<div className={ `qcseo-score is-${ view.band }` }>
					<strong>{ null === view.score ? '—' : view.score }</strong>
					<span>{ __( 'SEO score', 'chatbot' ) }</span>
				</div>
				<div>
					<h3>
						<a href={ view.url } target="_blank" rel="noreferrer">
							{ view.title }
						</a>
					</h3>
					<p className="qcseo-muted">
						{ view.keyword
							? sprintf(
									/* translators: %s: keyword. */
									__(
										'Target keyword: %s',
										'chatbot'
									),
									view.keyword
								)
							: __(
									'No target keyword: keyword checks are skipped.',
									'chatbot'
								) }
						{ ' · ' }
						{ sprintf(
							/* translators: 1: passed, 2: warnings, 3: to fix. */
							__(
								'%1$d passed, %2$d warnings, %3$d to fix',
								'chatbot'
							),
							counts.pass,
							counts.warning,
							counts.fail
						) }
						{ null !== view.readiness &&
							` · ${ sprintf(
								/* translators: %d: readiness score. */
								__(
									'AI search readiness %d/100',
									'chatbot'
								),
								view.readiness
							) }` }
					</p>
				</div>
				{ view.trend && view.trend.length > 1 && (
					<Sparkline
						points={ view.trend.map( ( t ) => t.score ) }
						label={ __( 'Score over time', 'chatbot' ) }
					/>
				) }
			</div>

			{ GROUPS.map( ( [ group, title ] ) => {
				const items = view.checks.filter( ( c ) => c.group === group );
				if ( ! items.length ) {
					return null;
				}
				return (
					<div key={ group } className="qcseo-check-group">
						<h4>{ title }</h4>
						<div className="qcseo-check-grid">
							{ items.map( ( check ) => {
								const kind = kindFor( check );
								return (
									<div
										key={ check.id }
										className={ `qcseo-check is-${ check.status }` }
									>
										<span
											className="qcseo-check__mark"
											title={
												STATUS[ check.status ][ 1 ]
											}
											aria-label={
												STATUS[ check.status ][ 1 ]
											}
										>
											{ STATUS[ check.status ][ 0 ] }
										</span>
										<div>
											<strong>{ check.label }</strong>
											{ check.message && (
												<p>{ check.message }</p>
											) }
											{ 'pass' !== check.status &&
												kind &&
												view.can_edit &&
												data.can.use && (
													<>
														<Button
															variant="secondary"
															size="compact"
															disabled={
																!! aiOff
															}
															onClick={ () =>
																setFixing(
																	kind
																)
															}
														>
															{
																CONTENT_FIXES[
																	kind
																]
															}
														</Button>
														{ aiOff && (
															<AiReason />
														) }
													</>
												) }
										</div>
									</div>
								);
							} ) }
						</div>
					</div>
				);
			} ) }

			{ fixing && (
				<ContentFix
					key={ fixing }
					postId={ view.post_id }
					kind={ fixing }
					keyword={ view.keyword }
					onCancel={ () => setFixing( '' ) }
					onSaved={ ( next ) => {
						setFixing( '' );
						onChange( next );
					} }
				/>
			) }

			{ view.history && view.history.length > 0 && (
				<div className="qcseo-card qcseo-audit-history">
					<h3>{ __( 'Audit history', 'chatbot' ) }</h3>
					<table className="widefat striped qcseo-table">
						<thead>
							<tr>
								<th>{ __( 'Date', 'chatbot' ) }</th>
								<th>{ __( 'Keyword', 'chatbot' ) }</th>
								<th>{ __( 'Score', 'chatbot' ) }</th>
								<th>{ __( 'How', 'chatbot' ) }</th>
								<th />
							</tr>
						</thead>
						<tbody>
							{ view.history.map( ( row ) => (
								<tr key={ row.id }>
									<td>{ row.created_at }</td>
									<td>{ row.keyword || '—' }</td>
									<td>{ row.score }</td>
									<td>
										{ TRIGGERS[ row.trigger ] ||
											row.trigger }
									</td>
									<td>
										<Button
											variant="link"
											onClick={ () =>
												seo(
													`audit/history/${ row.id }`
												)
													.then( setPast )
													.catch( () => {} )
											}
										>
											{ __( 'View', 'chatbot' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
					{ past && (
						<div className="qcseo-past-audit">
							<h4>
								{ sprintf(
									/* translators: 1: date, 2: score. */
									__(
										'Audit of %1$s: score %2$d',
										'chatbot'
									),
									past.created_at,
									past.score
								) }
							</h4>
							<ul>
								{ past.checks
									.filter( ( c ) => 'pass' !== c.status )
									.map( ( c ) => (
										<li key={ c.id }>
											{ STATUS[ c.status ]
												? STATUS[ c.status ][ 0 ]
												: '' }{ ' ' }
											{ c.label }
										</li>
									) ) }
							</ul>
							<Button
								variant="tertiary"
								onClick={ () => setPast( null ) }
							>
								{ __( 'Close', 'chatbot' ) }
							</Button>
						</div>
					) }
				</div>
			) }
		</div>
	);
};

export default PageAudit;
