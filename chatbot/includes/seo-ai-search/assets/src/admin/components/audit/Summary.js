/**
 * Page Audit (F3): site summary — good, average and poor pages, score
 * distribution, average score, and how many pages have each issue. Each
 * card and issue filters the page list.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const BANDS = {
	good: __( 'Good (70+)', 'chatbot' ),
	average: __( 'Average (40–69)', 'chatbot' ),
	poor: __( 'Poor (under 40)', 'chatbot' ),
};

/**
 * Score band of a value.
 *
 * @param {number} score Score.
 * @return {string} good, average or poor.
 */
const bandOf = ( score ) => {
	if ( score >= 70 ) {
		return 'good';
	}
	return score >= 40 ? 'average' : 'poor';
};

/**
 * @param {Object}                   props          Props.
 * @param {Object}                   props.summary  Summary from /audit/summary.
 * @param {Object}                   props.filter   Current filter { band, issue }.
 * @param {(filter: Object) => void} props.onFilter Set the filter.
 * @return {Element|null} Summary.
 */
const Summary = ( { summary, filter, onFilter } ) => {
	if ( ! summary ) {
		return null;
	}
	if ( 0 === summary.total ) {
		return (
			<div className="qcseo-card">
				<p className="qcseo-muted">
					{ __(
						'No pages audited yet. Run an audit above, or check every page.',
						'chatbot'
					) }
				</p>
			</div>
		);
	}
	const top = Math.max( 1, ...summary.distribution.map( ( d ) => d.pages ) );
	return (
		<>
			<div className="qcseo-audit-cards">
				<div className="qcseo-metric">
					<span className="qcseo-metric__label">
						{ __( 'Pages audited', 'chatbot' ) }
					</span>
					<strong className="qcseo-metric__value">
						{ summary.total }
					</strong>
				</div>
				{ Object.keys( BANDS ).map( ( band ) => (
					<button
						type="button"
						key={ band }
						className={ `qcseo-metric qcseo-band-card is-${ band } ${
							filter.band === band ? 'is-active' : ''
						}` }
						aria-pressed={ filter.band === band }
						onClick={ () =>
							onFilter( {
								band: filter.band === band ? '' : band,
								issue: '',
							} )
						}
					>
						<span className="qcseo-metric__label">
							{ BANDS[ band ] }
						</span>
						<strong className="qcseo-metric__value">
							{ summary.bands[ band ] }
						</strong>
					</button>
				) ) }
			</div>
			<div className="qcseo-columns">
				<div className="qcseo-card">
					<h3>{ __( 'Score distribution', 'chatbot' ) }</h3>
					<div
						className="qcseo-histogram"
						role="img"
						aria-label={ __(
							'Pages per score range',
							'chatbot'
						) }
					>
						{ summary.distribution.map( ( d ) => (
							<div
								key={ d.from }
								className="qcseo-histogram__bar"
								title={ sprintf(
									/* translators: 1: from, 2: to, 3: pages. */
									__(
										'%1$d–%2$d: %3$d pages',
										'chatbot'
									),
									d.from,
									d.to,
									d.pages
								) }
							>
								<span
									className={ `is-${ bandOf( d.from ) }` }
									style={ {
										height: `${ Math.round(
											( d.pages / top ) * 100
										) }%`,
									} }
								/>
								<small>{ d.from }</small>
							</div>
						) ) }
					</div>
				</div>
				<div className="qcseo-card">
					<h3>{ __( 'Average score', 'chatbot' ) }</h3>
					<p className="qcseo-big">
						{ null === summary.average
							? '—'
							: `${ summary.average }/100` }
					</p>
					<div
						className="qcseo-meter"
						role="meter"
						aria-valuemin="0"
						aria-valuemax="100"
						aria-valuenow={ summary.average || 0 }
					>
						<span
							style={ { width: `${ summary.average || 0 }%` } }
						/>
					</div>
				</div>
			</div>
			{ summary.issues.length > 0 && (
				<div className="qcseo-card">
					<h3>
						{ __( 'Issues across the site', 'chatbot' ) }
					</h3>
					<ul className="qcseo-issue-counts">
						{ summary.issues.slice( 0, 12 ).map( ( issue ) => (
							<li key={ issue.id }>
								<Button
									variant={
										filter.issue === issue.id
											? 'primary'
											: 'tertiary'
									}
									size="compact"
									onClick={ () =>
										onFilter( {
											band: '',
											issue:
												filter.issue === issue.id
													? ''
													: issue.id,
										} )
									}
								>
									{ sprintf(
										/* translators: 1: pages, 2: issue. */
										__(
											'%1$d pages: %2$s',
											'chatbot'
										),
										issue.pages,
										issue.label
									) }
								</Button>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</>
	);
};

export default Summary;
