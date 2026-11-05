/**
 * Page Audit & Fixes: run an audit for any page (F1), the full checklist
 * with AI fixes (F2), the site summary with filters (F3) and the audit
 * history (F4); then the pages with on-page issues, weakest first.
 * "Check every page" runs a site-wide check in the job queue.
 */
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo } from '../api';
import JobProgress from '../components/JobProgress';
import PageFixes from '../components/PageFixes';
import RunAudit from '../components/audit/RunAudit';
import Summary from '../components/audit/Summary';
import PageAudit from '../components/audit/PageAudit';
import { takeIntent } from '../nav';

const FixesScreen = () => {
	const [ severity, setSeverity ] = useState( '' );
	const [ view, setView ] = useState( null );
	const [ open, setOpen ] = useState( () => {
		const intent = takeIntent();
		return ( intent && intent.post_id ) || data.openPost || 0;
	} );
	const [ job, setJob ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const [ filter, setFilter ] = useState( { band: '', issue: '' } );
	const [ summary, setSummary ] = useState( null );
	const [ audit, setAudit ] = useState( null );

	const load = useCallback( () => {
		seo(
			`fixes?severity=${ severity }&band=${ filter.band }&issue=${ filter.issue }&limit=100`
		)
			.then( setView )
			.catch( ( e ) => setError( errorMessage( e ) ) );
		seo( 'audit/summary' )
			.then( setSummary )
			.catch( () => {} );
	}, [ severity, filter ] );
	useEffect( load, [ load ] );
	useEffect( () => {
		if ( ! open ) {
			setAudit( null );
			return;
		}
		if ( audit && audit.post_id === open ) {
			return;
		}
		seo( `audit/page/${ open }` )
			.then( setAudit )
			.catch( ( e ) => setError( errorMessage( e ) ) );
		// The audit only reloads when another page opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open ] );

	const scan = () =>
		seo( 'fixes/scan', { method: 'POST' } )
			.then( ( result ) => setJob( result.job_id ) )
			.catch( ( e ) => setError( errorMessage( e ) ) );

	return (
		<div className="qcseo-fixes">
			<div className="qcseo-head">
				<h2 className="qcseo-screen-title">
					{ __( 'Page Audit & Fixes', 'chatbot' ) }
				</h2>
				{ data.can.use && (
					<Button
						variant="secondary"
						disabled={ !! job }
						onClick={ scan }
					>
						{ __( 'Check every page', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcseo-muted">
				{ __(
					'Audit any page for a target keyword: every check with what passed and what to fix, and AI fixes you review before saving. Pages are also checked when you publish or update them. Search titles and descriptions are saved through your SEO plugin.',
					'chatbot'
				) }
			</p>
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
			{ error && <p className="qcseo-error">{ error }</p> }
			{ 0 === open && (
				<RunAudit
					onDone={ ( next ) => {
						setAudit( next );
						setOpen( next.post_id );
						load();
					} }
				/>
			) }
			{ 0 === open && (
				<Summary
					summary={ summary }
					filter={ filter }
					onFilter={ setFilter }
				/>
			) }
			{ 0 === open &&
				applyFilters( 'qcld_wpbot_seo.fixes.panels', null, {
					reload: load,
					summary,
				} ) }
			{ open > 0 && (
				<div className="qcseo-card">
					<Button variant="link" onClick={ () => setOpen( 0 ) }>
						{ __( '← All pages', 'chatbot' ) }
					</Button>
					{ audit && audit.post_id === open ? (
						<PageAudit
							view={ audit }
							onChange={ ( next ) => {
								setAudit( next );
								load();
							} }
						/>
					) : (
						<Spinner />
					) }
					<h3>
						{ __(
							'Fix titles, descriptions and links',
							'chatbot'
						) }
					</h3>
					<PageFixes postId={ open } onChanged={ load } />
				</div>
			) }
			{ ! view && ! error && <Spinner /> }
			{ view && 0 === open && (
				<>
					{ ( filter.band || filter.issue ) && (
						<p className="qcseo-filter-note">
							{ __( 'Filtered list.', 'chatbot' ) }{ ' ' }
							<Button
								variant="link"
								onClick={ () =>
									setFilter( { band: '', issue: '' } )
								}
							>
								{ __( 'Show all pages', 'chatbot' ) }
							</Button>
						</p>
					) }
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Impact', 'chatbot' ) }
						value={ severity }
						options={ [
							{
								value: '',
								label: __( 'All issues', 'chatbot' ),
							},
							{
								value: 'high',
								label: sprintf(
									/* translators: %d: issues. */
									__(
										'High impact (%d)',
										'chatbot'
									),
									view.groups.high
								),
							},
							{
								value: 'medium',
								label: sprintf(
									/* translators: %d: issues. */
									__( 'Medium (%d)', 'chatbot' ),
									view.groups.medium
								),
							},
							{
								value: 'low',
								label: sprintf(
									/* translators: %d: issues. */
									__( 'Low (%d)', 'chatbot' ),
									view.groups.low
								),
							},
						] }
						onChange={ setSeverity }
						__nextHasNoMarginBottom
					/>
					{ 0 === view.pages.length ? (
						<p>
							{ __(
								'No pages with issues yet. Click "Check every page", or publish or update a page.',
								'chatbot'
							) }
						</p>
					) : (
						<table className="widefat striped qcseo-table qcseo-fixes-list">
							<thead>
								<tr>
									<th>
										{ __( 'Page', 'chatbot' ) }
									</th>
									<th className="num">
										{ __( 'Score', 'chatbot' ) }
									</th>
									<th>
										{ __(
											'Top issue',
											'chatbot'
										) }
									</th>
									<th>
										<span className="screen-reader-text">
											{ __(
												'Actions',
												'chatbot'
											) }
										</span>
									</th>
								</tr>
							</thead>
							<tbody>
								{ view.pages.map( ( page ) => (
									<tr key={ page.post_id }>
										<td>{ page.title }</td>
										<td className="num">{ page.score }</td>
										<td>
											{ page.issues.length > 0
												? page.issues[ 0 ].message
												: __(
														'No issues',
														'chatbot'
													) }
											{ page.issues.length > 1 && ' ' }
											{ page.issues.length > 1 &&
												sprintf(
													/* translators: %d: more issues. */
													__(
														'(+%d more)',
														'chatbot'
													),
													page.issues.length - 1
												) }
										</td>
										<td>
											<Button
												variant="secondary"
												size="compact"
												onClick={ () =>
													setOpen( page.post_id )
												}
											>
												{ __(
													'Audit & fix',
													'chatbot'
												) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</>
			) }
		</div>
	);
};

export default FixesScreen;
