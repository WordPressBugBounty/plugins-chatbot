/**
 * Keywords & Topics > Paid searches (S13): the searches people typed
 * before clicking your Google Ads, with clicks, cost and conversions, your
 * organic position and page. Every number carries its source. Read only:
 * nothing here changes Google Ads.
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo, proProduct } from '../../api';
import { num } from '../../format';
import { navigate } from '../../nav';
import { adsSlotFilled } from '../GoogleAdsSlot';
import JobProgress from '../JobProgress';
import { SourceBadge } from './shared';

const PaidSearches = () => {
	const [ view, setView ] = useState( null );
	const [ job, setJob ] = useState( 0 );
	const [ picked, setPicked ] = useState( {} );
	const [ message, setMessage ] = useState( null );
	const load = () =>
		seo( 'ads' )
			.then( setView )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	useEffect( () => {
		load();
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}
	const pull = () =>
		seo( 'ads/pull', { method: 'POST' } )
			.then( ( r ) => setJob( r.job_id ) )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	const save = () =>
		seo( 'ads/vault', {
			method: 'POST',
			data: {
				queries: Object.keys( picked ).filter( ( q ) => picked[ q ] ),
			},
		} )
			.then( ( r ) =>
				setMessage( {
					status: 'success',
					text: sprintf(
						/* translators: %d: searches saved. */
						__(
							'%d searches saved to the Vault.',
							'chatbot'
						),
						r.saved
					),
				} )
			)
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	const connected = 'connected' === view.analytics;
	return (
		<div className="qcseo-paid">
			<p className="qcseo-muted">
				{ __(
					'The exact searches people typed before clicking your ads, with what they cost and how many converted. Read only: this module never changes anything in Google Ads.',
					'chatbot'
				) }
			</p>
			{ ! connected && 'connected' !== view.api && (
				<div className="qcseo-connect-inline" role="note">
					<p>
						<strong>
							{ __(
								'Google Ads is optional.',
								'chatbot'
							) }
						</strong>{ ' ' }
						{ __(
							'Connect Google Analytics and link your Google Ads account to that Analytics property (no extra setup here).',
							'chatbot'
						) }
						{ adsSlotFilled() &&
							' ' +
								sprintf(
									/* translators: %s: product name, for example WPBot. */
									__(
										'%s can also read Google Ads directly and use Google Keyword Planner: see Settings & AI Provider > Connections.',
										'chatbot'
									),
									proProduct
								) }
					</p>
					{ data.can.settings && adsSlotFilled() && (
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								navigate( 'settings', {
									tab: 'connections',
									anchor: 'google-ads',
								} )
							}
						>
							{ __( 'Open Connections', 'chatbot' ) }
						</Button>
					) }
				</div>
			) }
			{ applyFilters( 'qcld_wpbot_seo.paid.after', null, {
				reload: load,
			} ) }
			{ view.state && view.state.no_cost && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Analytics returned the searches but not their cost. Check that Google Ads cost data is imported into this Analytics property.',
						'chatbot'
					) }
				</Notice>
			) }
			{ view.state && view.state.error && (
				<p className="qcseo-error">{ view.state.error }</p>
			) }
			<div className="qcseo-actions">
				{ connected && data.can.use && (
					<Button
						variant="secondary"
						disabled={ !! job }
						onClick={ pull }
					>
						{ __( 'Import paid searches now', 'chatbot' ) }
					</Button>
				) }
				{ view.state && view.state.pulled_at && (
					<span className="qcseo-muted">
						{ sprintf(
							/* translators: %s: date. */
							__( 'Last import: %s', 'chatbot' ),
							view.state.pulled_at.slice( 0, 10 )
						) }
					</span>
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
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ view.searches.length > 0 && (
				<>
					<table className="widefat striped qcseo-table qcseo-paid-table">
						<thead>
							<tr>
								<th className="check-column">
									<span className="screen-reader-text">
										{ __( 'Select', 'chatbot' ) }
									</span>
								</th>
								<th>{ __( 'Search', 'chatbot' ) }</th>
								<th className="num">
									{ __( 'Ad clicks', 'chatbot' ) }
								</th>
								<th className="num">
									{ __( 'Cost', 'chatbot' ) }
								</th>
								<th className="num">
									{ __( 'Conversions', 'chatbot' ) }
								</th>
								<th className="num">
									{ __(
										'Cost per conversion',
										'chatbot'
									) }
								</th>
								<th className="num">
									{ __(
										'Searches a month',
										'chatbot'
									) }
								</th>
								<th>
									{ __(
										'Organic position',
										'chatbot'
									) }
								</th>
								<th>
									{ __( 'Your page', 'chatbot' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ view.searches.map( ( row ) => (
								<tr key={ row.query }>
									<td>
										<input
											type="checkbox"
											aria-label={ row.query }
											checked={ !! picked[ row.query ] }
											onChange={ ( e ) =>
												setPicked( {
													...picked,
													[ row.query ]:
														e.target.checked,
												} )
											}
										/>
									</td>
									<td>
										<strong>{ row.query }</strong>
										{ row.new && (
											<span className="qcseo-status is-targeted">
												{ __(
													'New',
													'chatbot'
												) }
											</span>
										) }
									</td>
									<td className="num">
										{ num( row.clicks ) }
									</td>
									<td className="num">
										{ null === row.cost
											? '—'
											: `${ Number( row.cost ).toFixed(
													2
												) } ${ row.currency }` }
									</td>
									<td className="num">{ row.conversions }</td>
									<td className="num">
										{ null === row.cost_per_conversion
											? '—'
											: Number(
													row.cost_per_conversion
												).toFixed( 2 ) }
									</td>
									<td className="num">
										{ null === row.volume ||
										undefined === row.volume ? (
											'—'
										) : (
											<>
												{ num( row.volume ) }{ ' ' }
												<SourceBadge
													source={ row.volume_source }
												/>
											</>
										) }
									</td>
									<td>
										{ null === row.position ? (
											'—'
										) : (
											<>
												{ row.position }{ ' ' }
												<SourceBadge
													source={ __(
														'Search Console',
														'chatbot'
													) }
												/>
											</>
										) }
									</td>
									<td>
										{ row.page ? (
											<a
												href={ row.page.url }
												target="_blank"
												rel="noreferrer"
											>
												{ row.page.title }
											</a>
										) : (
											'—'
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
					<p className="qcseo-muted">
						{ sprintf(
							/* translators: %s: data source. */
							__(
								'Last 30 days. Source: %s. Google hides low-volume searches for privacy.',
								'chatbot'
							),
							view.source
						) }
					</p>
					{ data.can.use && (
						<Button
							variant="secondary"
							disabled={
								! Object.values( picked ).some( Boolean )
							}
							onClick={ save }
						>
							{ __( 'Save to Vault', 'chatbot' ) }
						</Button>
					) }
				</>
			) }
			{ 0 === view.searches.length && connected && ! job && (
				<p className="qcseo-muted">
					{ __(
						'No paid searches yet. Import them now; if nothing arrives, link Google Ads to your Analytics property.',
						'chatbot'
					) }
				</p>
			) }
		</div>
	);
};

export default PaidSearches;
