/**
 * Link building results: links earned per month by type, guest posts and
 * collaborations, visits each linking site and partner sends (Site
 * Insight), and the linked pages' Search Console numbers before and after.
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';
import { EXCHANGE, TYPE } from './labels';

const LinksResults = () => {
	const [ view, setView ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'links/report' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const max = Math.max(
		1,
		...view.months.map(
			( m ) => m.link_request + m.guest_post + m.collaboration
		)
	);
	return (
		<div className="qcseo-links-results">
			<div className="qcseo-metrics">
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Links earned (12 months)', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ view.months.reduce(
							( a, m ) =>
								a +
								m.link_request +
								m.guest_post +
								m.collaboration,
							0
						) }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Guest posts published', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ view.guest_posts }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Collaborations live', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ view.collabs }
					</strong>
				</div>
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Active exchanges', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ ( view.exchanges.counts.live || 0 ) +
							( view.exchanges.counts.proposed || 0 ) }
					</strong>
				</div>
			</div>
			<div className="qcseo-card">
				<h3>{ __( 'Links earned per month', 'chatbot' ) }</h3>
				<table className="widefat qcseo-table qcseo-month-bars">
					<tbody>
						{ view.months.map( ( m ) => {
							const total =
								m.link_request + m.guest_post + m.collaboration;
							return (
								<tr key={ m.month }>
									<th scope="row">{ m.month }</th>
									<td>
										<span
											className="qcseo-bar"
											style={ {
												width: `${ ( total / max ) * 100 }%`,
											} }
											aria-hidden="true"
										/>
									</td>
									<td className="num">{ total }</td>
									<td className="qcseo-muted">
										{ total > 0 &&
											Object.keys( TYPE )
												.filter( ( t ) => m[ t ] )
												.map(
													( t ) =>
														`${ TYPE[ t ] } ${ m[ t ] }`
												)
												.join( ', ' ) }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>
			<div className="qcseo-card">
				<h3>{ __( 'Earned links', 'chatbot' ) }</h3>
				{ 0 === view.earned.length ? (
					<p className="qcseo-muted">
						{ __(
							'Links you win through outreach show here with their results.',
							'chatbot'
						) }
					</p>
				) : (
					<table className="widefat striped qcseo-table">
						<thead>
							<tr>
								<th>{ __( 'From', 'chatbot' ) }</th>
								<th>{ __( 'Type', 'chatbot' ) }</th>
								<th className="num">
									{ __(
										'Visits sent (90 days)',
										'chatbot'
									) }
								</th>
								<th>
									{ __(
										'Your page in search: 28 days before → after',
										'chatbot'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ view.earned.map( ( e ) => (
								<tr key={ e.id }>
									<td>
										<a
											href={ e.link_url }
											target="_blank"
											rel="noreferrer noopener"
										>
											{ e.name || e.link_url }
										</a>
										<br />
										<small className="qcseo-muted">
											{ e.date }
										</small>
									</td>
									<td>{ TYPE[ e.type ] }</td>
									<td className="num">
										{ null === e.visits ? '—' : e.visits }
									</td>
									<td>
										{ e.search && e.search.after ? (
											sprintf(
												/* translators: 1: clicks before, 2: clicks after, 3: position before, 4: position after. */
												__(
													'%1$d → %2$d clicks, position %3$s → %4$s',
													'chatbot'
												),
												e.search.before.clicks,
												e.search.after.clicks,
												e.search.before.position.toFixed(
													1
												),
												e.search.after.position.toFixed(
													1
												)
											)
										) : (
											<span className="qcseo-muted">
												{ __(
													'Waiting for Search Console data',
													'chatbot'
												) }
											</span>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
				{ ! view.insights && (
					<p className="qcseo-muted">
						{ __(
							'Visits per linking site appear when Site Insight is installed.',
							'chatbot'
						) }
					</p>
				) }
			</div>
			{ view.exchanges.partners.length > 0 && (
				<div className="qcseo-card">
					<h3>{ __( 'Exchange partners', 'chatbot' ) }</h3>
					<ul className="qcseo-movers">
						{ view.exchanges.partners.map( ( p, i ) => (
							<li key={ i }>
								<span>
									{ p.partner }{ ' ' }
									<small className="qcseo-muted">
										{ EXCHANGE[ p.status ] }
									</small>
								</span>
								<strong>
									{ null === p.visits ? '—' : p.visits }
								</strong>
							</li>
						) ) }
					</ul>
				</div>
			) }
			{ applyFilters( 'qcld_wpbot_seo.links.results', null, view ) }
		</div>
	);
};

export default LinksResults;
