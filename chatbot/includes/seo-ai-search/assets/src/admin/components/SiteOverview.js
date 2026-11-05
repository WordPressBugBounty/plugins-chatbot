/**
 * Overview content that needs no connection (S14): the Setup card,
 * on-page, technical and AI readiness health, Vault progress and the latest
 * research. Search Console, a keyword data provider and Google Ads add real
 * numbers on top; none of them blocks this screen.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { data } from '../api';
import { navigate } from '../nav';

const Step = ( { done, title, text, action, onClick } ) => (
	<li className={ `qcseo-setup__step ${ done ? 'is-done' : '' }` }>
		<span className="qcseo-setup__mark" aria-hidden="true">
			{ done ? '✓' : '•' }
		</span>
		<div>
			<strong>{ title }</strong>
			<p className="qcseo-muted">{ text }</p>
		</div>
		{ ! done && action && (
			<Button variant="secondary" size="compact" onClick={ onClick }>
				{ action }
			</Button>
		) }
	</li>
);

/**
 * Setup: business details and the first scan, then three optional
 * connections, each saying what it adds. Never blocks anything.
 *
 * @param {Object} props       Props.
 * @param {Object} props.setup Setup state from the overview route.
 * @return {Element|null} Card.
 */
export const SetupCard = ( { setup } ) => {
	if ( ! setup ) {
		return null;
	}
	const c = setup.connections || {};
	const allDone =
		setup.business && setup.scan && c.gsc && c.keyword_data && c.ads;
	if ( allDone ) {
		return null;
	}
	return (
		<div className="qcseo-card qcseo-setup">
			<h3>{ __( 'Set up', 'chatbot' ) }</h3>
			<ol className="qcseo-setup__steps">
				<Step
					done={ setup.business }
					title={ __( 'Your business details', 'chatbot' ) }
					text={ __(
						'Name, address and opening hours, used for local SEO, schema and AI answers.',
						'chatbot'
					) }
					action={ __( 'Add details', 'chatbot' ) }
					onClick={ () => navigate( 'local' ) }
				/>
				<Step
					done={ setup.scan }
					title={ __( 'Your first site check', 'chatbot' ) }
					text={ __(
						'A technical check of every page: broken links, missing titles, speed and indexing problems.',
						'chatbot'
					) }
					action={ __( 'Run the check', 'chatbot' ) }
					onClick={ () => navigate( 'technical' ) }
				/>
			</ol>
			<h4>
				{ __(
					'Optional connections (each adds real data)',
					'chatbot'
				) }
			</h4>
			<div className="qcseo-connect-cards">
				{ [
					[
						'gsc',
						__(
							'Search Console with Analytics',
							'chatbot'
						),
						__(
							'Adds your own clicks, positions and searches, and moves Vault keywords to Ranking.',
							'chatbot'
						),
						'connections',
					],
					[
						'keyword_data',
						__( 'Keyword data provider', 'chatbot' ),
						__(
							'Adds real search volumes, costs per click and competitor rankings (your own DataForSEO, Keywords Everywhere or Semrush key).',
							'chatbot'
						),
						'connections',
					],
					[
						'ads',
						__( 'Google Ads', 'chatbot' ),
						__(
							'Adds the searches you pay for and where you could save on ads.',
							'chatbot'
						),
						'connections',
					],
				].map( ( [ id, title, text, tab ] ) => (
					<div
						key={ id }
						className={ `qcseo-connect-card-small ${
							c[ id ] ? 'is-connected' : ''
						}` }
					>
						<strong>{ title }</strong>
						<p className="qcseo-muted">{ text }</p>
						{ c[ id ] ? (
							<span className="qcseo-ok">
								{ __( 'Connected', 'chatbot' ) }
							</span>
						) : (
							data.can.settings && (
								<Button
									variant="link"
									onClick={ () =>
										navigate( 'settings', { tab } )
									}
								>
									{ __( 'Connect', 'chatbot' ) }
								</Button>
							)
						) }
					</div>
				) ) }
			</div>
		</div>
	);
};

const Score = ( { label, value, note, onClick, action } ) => (
	<div className="qcseo-metric qcseo-health-card">
		<span className="qcseo-metric__label">{ label }</span>
		<strong className="qcseo-metric__value">
			{ null === value || undefined === value ? '—' : `${ value }/100` }
		</strong>
		<span className="qcseo-muted">{ note }</span>
		{ action && (
			<Button variant="link" onClick={ onClick }>
				{ action }
			</Button>
		) }
	</div>
);

/**
 * Health, Vault and research panels.
 *
 * @param {Object} props      Props.
 * @param {Object} props.site Site facts from the overview route.
 * @return {Element|null} Panels.
 */
export const SiteHealth = ( { site } ) => {
	if ( ! site ) {
		return null;
	}
	const v = site.vault;
	return (
		<>
			<div className="qcseo-metrics">
				<Score
					label={ __( 'On-page', 'chatbot' ) }
					value={ site.onpage.average }
					note={
						site.onpage.pages
							? sprintf(
									/* translators: 1: pages checked, 2: weak pages. */
									__(
										'%1$d pages checked, %2$d need work',
										'chatbot'
									),
									site.onpage.pages,
									site.onpage.weak
								)
							: __(
									'Pages are checked when you publish or update them.',
									'chatbot'
								)
					}
					action={ __( 'Open Fixes', 'chatbot' ) }
					onClick={ () => navigate( 'fixes' ) }
				/>
				<Score
					label={ __( 'Technical', 'chatbot' ) }
					value={ site.technical.score }
					note={
						null === site.technical.score
							? __( 'No site check yet.', 'chatbot' )
							: sprintf(
									/* translators: %d: critical issues. */
									__(
										'%d critical issues',
										'chatbot'
									),
									site.technical.critical
								)
					}
					action={ __( 'Open Technical SEO', 'chatbot' ) }
					onClick={ () => navigate( 'technical' ) }
				/>
				<Score
					label={ __( 'AI search readiness', 'chatbot' ) }
					value={ site.readiness.average }
					note={ sprintf(
						/* translators: %d: pages. */
						__( 'Average of %d recent pages', 'chatbot' ),
						site.readiness.pages
					) }
					action={ __( 'Open AI Search', 'chatbot' ) }
					onClick={ () => navigate( 'ai-search' ) }
				/>
			</div>
			<div className="qcseo-columns">
				<div className="qcseo-card">
					<h3>{ __( 'Keyword Vault', 'chatbot' ) }</h3>
					{ v ? (
						<p className="qcseo-big">
							{ sprintf(
								/* translators: 1: targeted keywords, 2: ranking keywords. */
								__(
									'%1$d targeted · %2$d ranking',
									'chatbot'
								),
								v.targeted || 0,
								v.ranking || 0
							) }
						</p>
					) : (
						<p className="qcseo-muted">
							{ __(
								'The Vault needs a newer WPBot Core.',
								'chatbot'
							) }
						</p>
					) }
					{ v && (
						<p className="qcseo-muted">
							{ sprintf(
								/* translators: %d: researched keywords. */
								__(
									'%d more researched, not targeted yet.',
									'chatbot'
								),
								v.researched || 0
							) }
						</p>
					) }
					<Button
						variant="link"
						onClick={ () => navigate( 'keywords' ) }
					>
						{ __( 'Open the Vault', 'chatbot' ) }
					</Button>
				</div>
				<div className="qcseo-card">
					<h3>{ __( 'Latest research', 'chatbot' ) }</h3>
					{ site.research.length ? (
						<ul>
							{ site.research.map( ( r ) => (
								<li key={ r.id }>
									{ r.seed }{ ' ' }
									<small className="qcseo-muted">
										{ r.created_at }
									</small>
								</li>
							) ) }
						</ul>
					) : (
						<p className="qcseo-muted">
							{ __(
								'Find keywords for a topic, a niche or against your competitors. No connection needed.',
								'chatbot'
							) }
						</p>
					) }
					<Button
						variant="link"
						onClick={ () => navigate( 'keywords' ) }
					>
						{ __( 'Research keywords', 'chatbot' ) }
					</Button>
				</div>
			</div>
		</>
	);
};
