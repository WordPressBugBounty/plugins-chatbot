/**
 * Overview: search totals with trend, the import status, top pages and
 * searches. Later milestones add the health score and top actions.
 */
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo } from '../api';
import { num, pct, pos, trend } from '../format';
import { loadStatus } from '../status';
import ConnectCard from '../components/ConnectCard';
import { SetupCard, SiteHealth } from '../components/SiteOverview';
import JobProgress from '../components/JobProgress';
import Sparkline from '../components/Sparkline';
import OpportunityItem from '../components/OpportunityItem';
import { navigate } from '../nav';

const Metric = ( { label, value, change, better, series, invert } ) => {
	let tone = '';
	if ( null !== change && undefined !== change && 0 !== change ) {
		const up = change > 0;
		tone = ( invert ? ! up : up ) ? 'is-up' : 'is-down';
	}
	return (
		<div className="qcseo-metric">
			<span className="qcseo-metric__label">{ label }</span>
			<strong className="qcseo-metric__value">{ value }</strong>
			<span className={ `qcseo-metric__trend ${ tone }` }>
				{ better }
			</span>
			<Sparkline points={ series } label={ label } />
		</div>
	);
};

const OverviewScreen = () => {
	const [ days, setDays ] = useState( 28 );
	const [ view, setView ] = useState( null );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setError( '' );
		loadStatus( true )
			.then( setStatus )
			.catch( () => setStatus( null ) );
		seo( `overview?days=${ days }` )
			.then( setView )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ days ] );

	useEffect( load, [ load ] );

	const startImport = () => {
		setBusy( true );
		seo( 'pull', { method: 'POST', data: { mode: 'backfill' } } )
			.then( load )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const connected = status && 'connected' === status.google.status;
	const pull = view ? view.pull : null;
	const totals = view ? view.totals : null;
	const series = view ? view.series : [];

	return (
		<div className="qcseo-overview">
			<div className="qcseo-head">
				<h2 className="qcseo-screen-title">
					{ __( 'Overview', 'chatbot' ) }
				</h2>
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Period', 'chatbot' ) }
					hideLabelFromVision
					value={ String( days ) }
					options={ [
						{
							value: '7',
							label: __( 'Last 7 days', 'chatbot' ),
						},
						{
							value: '28',
							label: __( 'Last 28 days', 'chatbot' ),
						},
						{
							value: '90',
							label: __( 'Last 3 months', 'chatbot' ),
						},
					] }
					onChange={ ( value ) => setDays( Number( value ) ) }
					__nextHasNoMarginBottom
				/>
			</div>
			<ConnectCard status={ status } />
			{ error && <p className="qcseo-error">{ error }</p> }
			{ ! view && ! error && <Spinner /> }

			{ connected && pull && pull.job_id > 0 && (
				<div className="qcseo-card">
					<h3>
						{ __(
							'Importing your Search Console data',
							'chatbot'
						) }
					</h3>
					<p className="qcseo-muted">
						{ __(
							'Up to 16 months, one week at a time. You can leave this page; the import continues in the background.',
							'chatbot'
						) }
					</p>
					<JobProgress
						jobId={ pull.job_id }
						onChange={ ( job ) => {
							if ( job && 'done' === job.status ) {
								load();
							}
						} }
					/>
				</div>
			) }

			{ connected && pull && ! pull.job_id && ! pull.coverage.days && (
				<div className="qcseo-card">
					<h3>
						{ __( 'Import your search data', 'chatbot' ) }
					</h3>
					<p>
						{ __(
							'Search Console is connected. Import the last 16 months to see your clicks, searches and opportunities.',
							'chatbot'
						) }
					</p>
					{ data.can.settings && (
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy }
							onClick={ startImport }
						>
							{ __( 'Import now', 'chatbot' ) }
						</Button>
					) }
				</div>
			) }

			{ view && <SetupCard setup={ view.setup } /> }

			{ view &&
				status &&
				( ! connected || ! ( pull && pull.coverage.days > 0 ) ) && (
					<>
						<SiteHealth site={ view.site } />
						<div className="qcseo-card">
							<h3>{ __( 'Top actions', 'chatbot' ) }</h3>
							{ 0 === view.actions.length ? (
								<p className="qcseo-muted">
									{ __(
										'Nothing urgent. Save keywords to your Vault or run a site check to get actions.',
										'chatbot'
									) }
								</p>
							) : (
								<ul className="qcseo-opp-list">
									{ view.actions.map( ( item ) => (
										<OpportunityItem
											key={ item.id }
											item={ item }
											writer={ data.writer }
											compact
										/>
									) ) }
								</ul>
							) }
							<Button
								variant="link"
								onClick={ () => navigate( 'opportunities' ) }
							>
								{ __(
									'See all opportunities',
									'chatbot'
								) }
							</Button>
						</div>
					</>
				) }

			{ totals && pull && pull.coverage.days > 0 && (
				<>
					<p className="qcseo-muted">
						{ sprintf(
							/* translators: 1: first day, 2: last day. */
							__(
								'Search Console, %1$s to %2$s (final data lags about three days).',
								'chatbot'
							),
							view.period.start,
							view.period.end
						) }
					</p>
					<div className="qcseo-metrics">
						<Metric
							label={ __( 'Clicks', 'chatbot' ) }
							value={ num( totals.clicks ) }
							change={ totals.trend.clicks }
							better={ trend( totals.trend.clicks ) }
							series={ series.map( ( row ) => row.clicks ) }
						/>
						<Metric
							label={ __( 'Impressions', 'chatbot' ) }
							value={ num( totals.impressions ) }
							change={ totals.trend.impressions }
							better={ trend( totals.trend.impressions ) }
							series={ series.map( ( row ) => row.impressions ) }
						/>
						<Metric
							label={ __(
								'Click-through rate',
								'chatbot'
							) }
							value={ pct( totals.ctr ) }
							change={ totals.trend.ctr }
							better={ trend( totals.trend.ctr ) }
							series={ series.map( ( row ) => row.ctr ) }
						/>
						<Metric
							label={ __(
								'Average position',
								'chatbot'
							) }
							value={ pos( totals.position ) }
							change={ totals.trend.position }
							invert
							better={ sprintf(
								/* translators: %s: position change (lower is better). */
								__( '%s places', 'chatbot' ),
								( totals.trend.position > 0 ? '+' : '' ) +
									totals.trend.position
							) }
							series={ series.map( ( row ) => row.position ) }
						/>
					</div>
					<div className="qcseo-columns">
						<div className="qcseo-card">
							<h3>
								{ __(
									'Top actions this week',
									'chatbot'
								) }
							</h3>
							{ 0 === view.actions.length ? (
								<p className="qcseo-muted">
									{ __(
										'Nothing urgent. New opportunities appear after each Search Console update.',
										'chatbot'
									) }
								</p>
							) : (
								<ul className="qcseo-opp-list">
									{ view.actions.map( ( item ) => (
										<OpportunityItem
											key={ item.id }
											item={ item }
											writer={ data.writer }
											compact
										/>
									) ) }
								</ul>
							) }
							<Button
								variant="link"
								onClick={ () => navigate( 'opportunities' ) }
							>
								{ __(
									'See all opportunities',
									'chatbot'
								) }
							</Button>
						</div>
						<div className="qcseo-card">
							{ null !== view.score &&
								undefined !== view.score && (
									<p className="qcseo-big">
										{ sprintf(
											/* translators: %d: health score. */
											__(
												'Site health: %d/100',
												'chatbot'
											),
											view.score
										) }
									</p>
								) }
							<h3>{ __( 'Connections', 'chatbot' ) }</h3>
							<ul className="qcseo-health">
								{ [
									[
										'gsc',
										__(
											'Search Console',
											'chatbot'
										),
									],
									[
										'ga',
										__(
											'Google Analytics (optional)',
											'chatbot'
										),
									],
									[
										'pagespeed',
										__(
											'PageSpeed key (optional)',
											'chatbot'
										),
									],
									[
										'keyword_data',
										__(
											'Keyword data (optional)',
											'chatbot'
										),
									],
								].map( ( [ id, label ] ) => (
									<li
										key={ id }
										className={ `qcseo-health__item is-${ view.health[ id ] }` }
									>
										<span>{ label }</span>
										<strong>
											{ 'connected' === view.health[ id ]
												? __(
														'Connected',
														'chatbot'
													)
												: __(
														'Not connected',
														'chatbot'
													) }
										</strong>
									</li>
								) ) }
							</ul>
							{ view.movers.length > 0 && (
								<>
									<h3>
										{ __(
											'Biggest movers',
											'chatbot'
										) }
									</h3>
									<ul className="qcseo-movers">
										{ view.movers.map( ( move ) => (
											<li key={ move.url }>
												<span>
													{ move.title || move.url }
												</span>
												<strong
													className={
														move.change >= 0
															? 'is-up'
															: 'is-down'
													}
												>
													{ ( move.change > 0
														? '+'
														: '' ) +
														num( move.change ) }
												</strong>
											</li>
										) ) }
									</ul>
								</>
							) }
						</div>
					</div>
					{ ( data.previews || [] )
						.filter( ( p ) => 'preview-visibility' === p.id )
						.map( ( p ) => (
							<div
								key={ p.id }
								className="qcseo-card qcseo-preview-card"
							>
								<span className="qcseo-pro-pill">
									{ __( 'Pro', 'chatbot' ) }
								</span>
								<h3>{ p.label }</h3>
								<p className="qcseo-muted">{ p.text }</p>
							</div>
						) ) }
					{ applyFilters(
						'qcld_wpbot_seo.overview.cards',
						null,
						view
					) }
					<div className="qcseo-columns">
						<div className="qcseo-card">
							<h3>{ __( 'Top pages', 'chatbot' ) }</h3>
							<table className="widefat striped qcseo-table">
								<thead>
									<tr>
										<th>
											{ __( 'Page', 'chatbot' ) }
										</th>
										<th className="num">
											{ __(
												'Clicks',
												'chatbot'
											) }
										</th>
										<th className="num">
											{ __(
												'Position',
												'chatbot'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ view.pages.map( ( page ) => (
										<tr key={ page.id }>
											<td>
												<a
													href={ page.label }
													target="_blank"
													rel="noreferrer"
												>
													{ page.title || page.label }
												</a>
											</td>
											<td className="num">
												{ num( page.clicks ) }
											</td>
											<td className="num">
												{ pos( page.position ) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
						<div className="qcseo-card">
							<h3>
								{ __( 'Top searches', 'chatbot' ) }
							</h3>
							<table className="widefat striped qcseo-table">
								<thead>
									<tr>
										<th>
											{ __(
												'Search',
												'chatbot'
											) }
										</th>
										<th className="num">
											{ __(
												'Clicks',
												'chatbot'
											) }
										</th>
										<th className="num">
											{ __(
												'Position',
												'chatbot'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ view.queries.map( ( query ) => (
										<tr key={ query.id }>
											<td>{ query.label }</td>
											<td className="num">
												{ num( query.clicks ) }
											</td>
											<td className="num">
												{ pos( query.position ) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</div>
				</>
			) }
		</div>
	);
};

export default OverviewScreen;
