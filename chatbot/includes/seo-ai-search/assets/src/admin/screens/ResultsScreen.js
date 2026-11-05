/**
 * Results & Reports: every change with its before-and-after numbers at the
 * owner's checkpoints (14, 28, 90 days), a summary, and the weekly email.
 */
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo } from '../api';
import { num, pos } from '../format';

const KINDS = () => [
	{ value: '', label: __( 'All changes', 'chatbot' ) },
	{
		value: 'meta',
		label: __( 'Search titles and descriptions', 'chatbot' ),
	},
	{ value: 'link', label: __( 'Internal links', 'chatbot' ) },
	{ value: 'redirect', label: __( 'Redirects', 'chatbot' ) },
	{
		value: 'offsite',
		label: __( 'Posts on other sites', 'chatbot' ),
	},
];

const Result = ( { result } ) => {
	if ( ! result ) {
		return (
			<span className="qcseo-muted">
				{ __( 'waiting', 'chatbot' ) }
			</span>
		);
	}
	const c = result.change;
	const up = c.clicks > 0 || c.position > 0.5;
	const down = c.clicks < 0 && c.position <= 0;
	let tone = '';
	if ( up ) {
		tone = 'is-up';
	} else if ( down ) {
		tone = 'is-down';
	}
	return (
		<span className={ tone }>
			{ ( c.clicks >= 0 ? '+' : '' ) + num( c.clicks ) }{ ' ' }
			{ __( 'clicks', 'chatbot' ) }
			{ c.position !== 0 &&
				`, ${ c.position > 0 ? '↑' : '↓' } ${ pos( Math.abs( c.position ) ) }` }
		</span>
	);
};

/**
 * On-page score before and after a change (no Search Console needed).
 *
 * @param {Object} props        Props.
 * @param {Object} props.onpage { before, after }.
 * @return {Element|string} Cell.
 */
const OnpageResult = ( { onpage } ) => {
	if ( ! onpage || undefined === onpage.before ) {
		return '–';
	}
	if ( undefined === onpage.after ) {
		return (
			<span className="qcseo-muted">
				{ onpage.before } → { __( 'checking', 'chatbot' ) }
			</span>
		);
	}
	let tone = '';
	if ( onpage.after > onpage.before ) {
		tone = 'is-up';
	} else if ( onpage.after < onpage.before ) {
		tone = 'is-down';
	}
	return (
		<span className={ tone }>
			{ onpage.before } → { onpage.after }
		</span>
	);
};

const EmailCard = () => {
	const [ preview, setPreview ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	if ( ! data.can.settings ) {
		return null;
	}
	return (
		<div className="qcseo-card">
			<h3>{ __( 'Weekly email', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'A short summary each week, sent only when there is news. Change the day and recipients in Settings > Email.',
					'chatbot'
				) }
			</p>
			<Button
				variant="secondary"
				onClick={ () =>
					seo( 'email/preview' )
						.then( setPreview )
						.catch( ( e ) => setMessage( errorMessage( e ) ) )
				}
			>
				{ __( "Preview this week's email", 'chatbot' ) }
			</Button>{ ' ' }
			<Button
				variant="secondary"
				onClick={ () =>
					seo( 'email/test', { method: 'POST' } )
						.then( ( r ) =>
							setMessage(
								r.sent
									? sprintf(
											/* translators: %s: email address. */
											__(
												'Test email sent to %s.',
												'chatbot'
											),
											r.to
										)
									: __(
											'The test email could not be sent. Check that this site can send email.',
											'chatbot'
										)
							)
						)
						.catch( ( e ) => setMessage( errorMessage( e ) ) )
				}
			>
				{ __( 'Send me a test email', 'chatbot' ) }
			</Button>
			{ message && <p className="qcseo-message">{ message }</p> }
			{ preview && (
				<>
					<p className="qcseo-muted">
						{ preview.news
							? __(
									'There is news this week; this email will be sent.',
									'chatbot'
								)
							: __(
									'Nothing new this week; no email will be sent.',
									'chatbot'
								) }
					</p>
					{ /* Built and escaped on the server (Weekly_Email::html). */ }
					<div
						className="qcseo-email-preview"
						dangerouslySetInnerHTML={ { __html: preview.html } }
					/>
				</>
			) }
		</div>
	);
};

const ResultsScreen = () => {
	const [ kind, setKind ] = useState( '' );
	const [ view, setView ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = useCallback( () => {
		seo( `results?kind=${ kind }` )
			.then( setView )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ kind ] );
	useEffect( load, [ load ] );
	if ( ! view ) {
		return error ? <p className="qcseo-error">{ error }</p> : <Spinner />;
	}
	const s = view.summary;
	return (
		<div className="qcseo-results">
			<h2 className="qcseo-screen-title">
				{ __( 'Results & Reports', 'chatbot' ) }
			</h2>
			<div className="qcseo-metrics">
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __(
							'Changes in the last 30 days',
							'chatbot'
						) }
					</span>
					<strong className="qcseo-metric__value">
						{ num( s.changes ) }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ sprintf(
							/* translators: %d: days. */
							__( 'Improved after %d days', 'chatbot' ),
							s.checkpoint
						) }
					</span>
					<strong className="qcseo-metric__value">
						{ s.measured
							? `${ num( s.improved ) } / ${ num( s.measured ) }`
							: '–' }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Clicks gained', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ ( s.clicks_gained >= 0 ? '+' : '' ) +
							num( s.clicks_gained ) }
					</strong>
				</div>
			</div>
			{ applyFilters( 'qcld_wpbot_seo.results.cards', null, view ) }
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Show', 'chatbot' ) }
				value={ kind }
				options={ KINDS() }
				onChange={ setKind }
				__nextHasNoMarginBottom
			/>
			<p className="qcseo-muted">
				{ __(
					'Each change shows the page’s on-page score before and after it. With Search Console, it is also compared with the same number of days before it (clicks and position; conversions when Google Analytics is connected).',
					'chatbot'
				) }
			</p>
			{ 0 === view.items.length ? (
				<div className="qcseo-card">
					<p>
						{ __(
							'No changes yet. Fixes, new links and redirects appear here with their results.',
							'chatbot'
						) }
					</p>
				</div>
			) : (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Date', 'chatbot' ) }</th>
							<th>{ __( 'Page', 'chatbot' ) }</th>
							<th>{ __( 'Change', 'chatbot' ) }</th>
							<th>
								{ __( 'On-page score', 'chatbot' ) }
							</th>
							{ view.days.map( ( d ) => (
								<th key={ d }>
									{ sprintf(
										/* translators: %d: days. */
										__(
											'After %d days',
											'chatbot'
										),
										d
									) }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ view.items.map( ( item ) => (
							<tr
								key={ item.id }
								className={ item.undone_at ? 'is-undone' : '' }
							>
								<td>{ item.created_at.slice( 0, 10 ) }</td>
								<td>{ item.title || item.field }</td>
								<td>
									{ item.kind }: { item.field }
									{ item.undone_at &&
										` (${ __( 'undone', 'chatbot' ) })` }
								</td>
								<td>
									<OnpageResult
										onpage={
											item.results && item.results.onpage
										}
									/>
								</td>
								{ view.days.map( ( d ) => (
									<td key={ d }>
										{ item.post_id ? (
											<Result
												result={
													item.results[ String( d ) ]
												}
											/>
										) : (
											'–'
										) }
									</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			<EmailCard />
		</div>
	);
};

export default ResultsScreen;
