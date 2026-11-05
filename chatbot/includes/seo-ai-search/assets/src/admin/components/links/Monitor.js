/**
 * Link monitor: who links to you (Search Console CSV, Bing, Site Insight
 * referrals, links added by hand), with polite on-demand checks.
 */
import {
	Button,
	FormFileUpload,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import JobProgress from '../JobProgress';
import { ORIGIN, REL, STATUS, options } from './labels';

const Monitor = () => {
	const [ view, setView ] = useState( null );
	const [ filter, setFilter ] = useState( { status: '', origin: '' } );
	const [ form, setForm ] = useState( {
		source_url: '',
		target_url: '',
		anchor: '',
		rel: '',
	} );
	const [ job, setJob ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		seo(
			`links?status=${ filter.status }&origin=${ filter.origin }&limit=200`
		)
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [ filter ] );
	useEffect( load, [ load ] );

	const run = ( promise, done ) => {
		setBusy( true );
		setMessage( '' );
		return promise
			.then( ( result ) => {
				if ( done ) {
					setMessage( done( result ) );
				}
				load();
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const upload = ( event ) => {
		const file = event.target.files && event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () =>
			run(
				seo( 'links/import', {
					method: 'POST',
					data: { text: String( reader.result ) },
				} ),
				( r ) =>
					sprintf(
						/* translators: 1: rows added, 2: rows already known, 3: rows skipped. */
						__(
							'Imported: %1$d added, %2$d already known, %3$d skipped.',
							'chatbot'
						),
						r.added,
						r.merged || 0,
						r.skipped
					)
			);
		reader.readAsText( file );
	};

	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const can = data.can || {};
	return (
		<div className="qcseo-links-monitor">
			<div className="qcseo-metrics">
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Known links', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ Object.values( view.counts ).reduce(
							( a, b ) => a + b,
							0
						) }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Linking sites', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ view.sites }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Live', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ view.counts.live || 0 }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Lost or changed', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ ( view.counts.lost || 0 ) +
							( view.counts.changed || 0 ) }
					</strong>
				</div>
			</div>
			<div className="qcseo-columns">
				<div className="qcseo-card">
					<h3>
						{ __( 'Search Console links', 'chatbot' ) }
					</h3>
					<p className="qcseo-muted">
						{ __(
							'Search Console\'s API has no Links report, so upload its export: Search Console > Links > Export external links > "More sample links" or "Latest links" (CSV). "Top linking sites" works too.',
							'chatbot'
						) }
					</p>
					{ can.use && (
						<FormFileUpload
							accept=".csv,text/csv,text/plain"
							onChange={ upload }
							render={ ( { openFileDialog } ) => (
								<Button
									variant="secondary"
									onClick={ openFileDialog }
									isBusy={ busy }
									disabled={ busy }
								>
									{ __( 'Upload CSV', 'chatbot' ) }
								</Button>
							) }
						/>
					) }
					{ view.csv.imported && (
						<p className="qcseo-muted">
							{ sprintf(
								/* translators: %s: date. */
								__( 'Last import: %s', 'chatbot' ),
								view.csv.imported.slice( 0, 10 )
							) }
						</p>
					) }
					{ Object.keys( view.csv.sites ).length > 0 && (
						<ul className="qcseo-movers">
							{ Object.entries( view.csv.sites )
								.slice( 0, 8 )
								.map( ( [ host, n ] ) => (
									<li key={ host }>
										<span>{ host }</span>
										<strong>{ n }</strong>
									</li>
								) ) }
						</ul>
					) }
				</div>
				<div className="qcseo-card">
					<h3>
						{ __( 'Bing Webmaster Tools', 'chatbot' ) }
					</h3>
					{ view.bing.key ? (
						<>
							<p className="qcseo-muted">
								{ view.bing.pulled
									? sprintf(
											/* translators: %s: date. */
											__(
												'Link counts per page and linking pages of your most linked pages. Last read: %s',
												'chatbot'
											),
											view.bing.pulled.slice( 0, 10 )
										)
									: __(
											'Read link counts per page and the linking pages of your most linked pages.',
											'chatbot'
										) }
							</p>
							{ can.use && (
								<Button
									variant="secondary"
									isBusy={ busy }
									disabled={ busy }
									onClick={ () =>
										run(
											seo( 'links/bing', {
												method: 'POST',
											} ),
											( r ) =>
												sprintf(
													/* translators: 1: pages, 2: links. */
													__(
														'Bing: %1$d pages with links, %2$d linking pages added or updated.',
														'chatbot'
													),
													r.pages,
													r.links_added
												)
										)
									}
								>
									{ __(
										'Read from Bing',
										'chatbot'
									) }
								</Button>
							) }
						</>
					) : (
						<p className="qcseo-muted">
							{ __(
								'Optional: add your free Bing Webmaster Tools API key in Settings > Connections to read inbound links.',
								'chatbot'
							) }
						</p>
					) }
					<h3>
						{ __( 'Sites that send visitors', 'chatbot' ) }
					</h3>
					{ ! view.referrals.available && (
						<p className="qcseo-muted">
							{ __(
								'Shown when Site Insight is installed.',
								'chatbot'
							) }
						</p>
					) }
					{ view.referrals.available &&
						0 === view.referrals.sites.length && (
							<p className="qcseo-muted">
								{ __(
									'No referring sites in the last 28 days.',
									'chatbot'
								) }
							</p>
						) }
					<ul className="qcseo-movers">
						{ view.referrals.sites.slice( 0, 10 ).map( ( site ) => (
							<li key={ site.host }>
								<span>
									{ site.host }{ ' ' }
									{ site.known ? (
										''
									) : (
										<em className="qcseo-muted">
											{ __(
												'(no known link yet)',
												'chatbot'
											) }
										</em>
									) }
								</span>
								<strong>{ site.sessions }</strong>
							</li>
						) ) }
					</ul>
				</div>
			</div>
			{ message && <p className="qcseo-message">{ message }</p> }
			<div className="qcseo-card">
				<div className="qcseo-head">
					<h3>{ __( 'Known links', 'chatbot' ) }</h3>
					{ can.use && view.total > 0 && (
						<Button
							variant="secondary"
							disabled={ !! job }
							onClick={ () =>
								seo( 'links/check', { method: 'POST' } )
									.then( ( r ) => setJob( r.job_id ) )
									.catch( ( e ) =>
										setMessage( errorMessage( e ) )
									)
							}
						>
							{ __( 'Check links now', 'chatbot' ) }
						</Button>
					) }
				</div>
				{ job > 0 && (
					<JobProgress
						jobId={ job }
						onChange={ ( state ) => {
							if ( state && 'done' === state.status ) {
								setJob( 0 );
								load();
							}
						} }
					/>
				) }
				<div className="qcseo-filters">
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Status', 'chatbot' ) }
						value={ filter.status }
						options={ options(
							STATUS,
							__( 'All', 'chatbot' )
						) }
						onChange={ ( status ) =>
							setFilter( { ...filter, status } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Source', 'chatbot' ) }
						value={ filter.origin }
						options={ options(
							ORIGIN,
							__( 'All', 'chatbot' )
						) }
						onChange={ ( origin ) =>
							setFilter( { ...filter, origin } )
						}
						__nextHasNoMarginBottom
					/>
				</div>
				{ 0 === view.items.length ? (
					<p className="qcseo-muted">
						{ __(
							'No links yet. Upload a Search Console export, read from Bing, or add a link you know about.',
							'chatbot'
						) }
					</p>
				) : (
					<table className="widefat striped qcseo-table qcseo-links-table">
						<thead>
							<tr>
								<th>
									{ __( 'Linking page', 'chatbot' ) }
								</th>
								<th>
									{ __( 'Your page', 'chatbot' ) }
								</th>
								<th>{ __( 'Anchor', 'chatbot' ) }</th>
								<th>{ __( 'Type', 'chatbot' ) }</th>
								<th>{ __( 'Status', 'chatbot' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ view.items.map( ( link ) => (
								<tr key={ link.id }>
									<td>
										<a
											href={ link.source_url }
											target="_blank"
											rel="noreferrer noopener"
										>
											{ link.source_url }
										</a>
										<br />
										<small className="qcseo-muted">
											{ ORIGIN[ link.origin ] ||
												link.origin }
										</small>
									</td>
									<td>
										{ link.target_url.replace(
											/^https?:\/\/[^/]+/,
											''
										) || '/' }
										{ ! link.target_ok && (
											<small className="qcseo-error">
												{ ' ' }
												{ __(
													'page not working',
													'chatbot'
												) }
											</small>
										) }
									</td>
									<td>{ link.anchor }</td>
									<td>{ link.rel || '—' }</td>
									<td
										className={ `qcseo-link-status is-${ link.status }` }
									>
										{ STATUS[ link.status ] || link.status }
										{ link.last_checked && (
											<small className="qcseo-muted">
												<br />
												{ link.last_checked.slice(
													0,
													10
												) }
											</small>
										) }
									</td>
									<td>
										{ can.use && (
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													run(
														seo(
															`links/known/${ link.id }/delete`,
															{ method: 'POST' }
														)
													)
												}
											>
												{ __(
													'Remove',
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
				{ can.use && (
					<div className="qcseo-grid qcseo-add-link">
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Page that links to you',
								'chatbot'
							) }
							value={ form.source_url }
							onChange={ ( value ) =>
								setForm( { ...form, source_url: value } )
							}
							placeholder="https://"
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Your page (optional)',
								'chatbot'
							) }
							value={ form.target_url }
							onChange={ ( value ) =>
								setForm( { ...form, target_url: value } )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Anchor text (optional)',
								'chatbot'
							) }
							value={ form.anchor }
							onChange={ ( anchor ) =>
								setForm( { ...form, anchor } )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Link type', 'chatbot' ) }
							value={ form.rel }
							options={ REL }
							onChange={ ( rel ) => setForm( { ...form, rel } ) }
							__nextHasNoMarginBottom
						/>
						<Button
							variant="primary"
							disabled={ ! form.source_url || busy }
							onClick={ () =>
								run(
									seo( 'links/known/add', {
										method: 'POST',
										data: form,
									} ),
									() => {
										setForm( {
											source_url: '',
											target_url: '',
											anchor: '',
											rel: '',
										} );
										return __(
											'Link added. It is checked with the next check.',
											'chatbot'
										);
									}
								)
							}
						>
							{ __( 'Add link', 'chatbot' ) }
						</Button>
					</div>
				) }
			</div>
		</div>
	);
};

export default Monitor;
