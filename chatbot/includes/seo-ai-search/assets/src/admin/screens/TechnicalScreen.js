/**
 * Technical SEO: scan report by category (count, examples, fix), Scan now,
 * browser checks when the host blocks loopback, 404 log, redirects, speed.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import LlmsTab from '../components/LlmsTab';
import { takeIntent } from '../nav';
import { data, errorMessage, seo, product } from '../api';
import JobProgress from '../components/JobProgress';
import { withPreviewTabs } from '../components/ProPreview';

const Report = ( { onRedirect } ) => {
	const [ view, setView ] = useState( null );
	const [ job, setJob ] = useState( 0 );
	const [ open, setOpen ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( '' );

	const load = useCallback(
		() =>
			seo( 'technical' )
				.then( ( result ) => {
					setView( result );
					setJob( result.running ? result.running.job_id : 0 );
				} )
				.catch( ( e ) => setMessage( errorMessage( e ) ) ),
		[]
	);
	useEffect( () => {
		load();
	}, [ load ] );

	const scan = () =>
		seo( 'technical/scan', { method: 'POST' } )
			.then( ( result ) => setJob( result.job_id ) )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );

	const browserChecks = async () => {
		const scanId = view.done.id;
		setBusy( 'browser' );
		let after = 0;
		let checked = 0;
		try {
			for (;;) {
				const pages = await seo(
					`technical/scan/${ scanId }/next?after=${ after }`
				);
				if ( ! pages.length ) {
					break;
				}
				for ( const page of pages ) {
					after = page.id;
					let status = 0;
					let html = '';
					let finalUrl = page.url;
					try {
						const res = await window.fetch( page.url, {
							credentials: 'omit',
						} );
						status = res.status;
						finalUrl = res.url;
						html = ( await res.text() ).slice( 0, 1000000 );
					} catch {
						// Unreachable page: saved with status 0.
						status = 0;
					}
					await seo( `technical/scan/${ scanId }/page`, {
						method: 'POST',
						data: {
							page_id: page.id,
							status,
							html,
							final_url: finalUrl,
						},
					} );
					checked++;
					setMessage(
						sprintf(
							/* translators: %d: pages checked. */
							__(
								'%d pages checked from this browser…',
								'chatbot'
							),
							checked
						)
					);
				}
			}
			await seo( `technical/scan/${ scanId }/rebuild`, {
				method: 'POST',
			} );
			setMessage(
				__(
					'Done. The report now uses what visitors see.',
					'chatbot'
				)
			);
			load();
		} catch ( e ) {
			setMessage( errorMessage( e ) );
		}
		setBusy( '' );
	};

	const fix = ( example ) =>
		seo( `technical/fix/${ example.issue_id }`, { method: 'POST' } )
			.then( () =>
				setMessage(
					__(
						'Fixed. It is gone from the next scan.',
						'chatbot'
					)
				)
			)
			.catch( ( e ) => setMessage( errorMessage( e ) ) );

	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const done = view.done;
	return (
		<div className="qcseo-tech-report">
			<div className="qcseo-head">
				<div>
					{ done && null !== done.score && (
						<p className="qcseo-big">
							{ sprintf(
								/* translators: %d: health score. */
								__(
									'Health score: %d/100',
									'chatbot'
								),
								done.score
							) }
						</p>
					) }
					{ done && (
						<p className="qcseo-muted">
							{ sprintf(
								/* translators: 1: pages, 2: date. */
								__(
									'%1$d pages checked, finished %2$s (UTC).',
									'chatbot'
								),
								done.pages,
								done.finished_at
							) }
						</p>
					) }
				</div>
				{ data.can.use && (
					<Button
						variant="primary"
						disabled={ !! job }
						onClick={ scan }
					>
						{ __( 'Scan now', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ job > 0 && (
				<JobProgress
					jobId={ job }
					onChange={ ( state ) => {
						if (
							state &&
							[ 'done', 'failed' ].includes( state.status )
						) {
							setJob( 0 );
							load();
						}
					} }
				/>
			) }
			{ done && 'database' === done.mode && (
				<div className="qcseo-card qcseo-notice-card">
					<p>
						{ __(
							'Your host does not allow the site to fetch its own pages, so this scan read them from the database. Check them from this browser for full results (status codes, head tags, structured data).',
							'chatbot'
						) }
					</p>
					{ data.can.use && (
						<Button
							variant="secondary"
							isBusy={ 'browser' === busy }
							disabled={ !! busy }
							onClick={ browserChecks }
						>
							{ __(
								'Check pages from this browser',
								'chatbot'
							) }
						</Button>
					) }
				</div>
			) }
			{ message && <p className="qcseo-message">{ message }</p> }
			{ ! done && ! job && (
				<div className="qcseo-card">
					<p>
						{ __(
							'No scan yet. Scan your site to find indexing, link, redirect, duplicate, structure, schema, speed and security problems.',
							'chatbot'
						) }
					</p>
				</div>
			) }
			{ view.categories.map( ( cat ) => (
				<div
					key={ cat.id }
					className={ `qcseo-card qcseo-cat ${ cat.count ? 'has-issues' : 'is-clear' }` }
				>
					<button
						type="button"
						className="qcseo-cat__head"
						aria-expanded={ open === cat.id }
						onClick={ () =>
							setOpen( open === cat.id ? '' : cat.id )
						}
					>
						<strong>{ cat.label }</strong>
						<span className="qcseo-badge">
							{ cat.count
								? cat.count
								: __( 'OK', 'chatbot' ) }
						</span>
					</button>
					{ open === cat.id &&
						cat.checks.map( ( check ) => (
							<div key={ check.id } className="qcseo-check">
								<p>
									<span
										className={ `qcseo-sev qcseo-sev--${ check.severity }` }
									>
										{ check.severity }
									</span>{ ' ' }
									<strong>{ check.label }</strong> (
									{ check.count })
								</p>
								<p className="qcseo-muted">{ check.fix }</p>
								<ul>
									{ check.examples.map( ( ex ) => (
										<li key={ ex.issue_id }>
											{ ex.url && (
												<a
													href={ ex.url }
													target="_blank"
													rel="noreferrer"
												>
													{ ex.title || ex.url }
												</a>
											) }
											{ ex.details &&
												ex.details.target &&
												` → ${ ex.details.target }` }
											{ ex.details &&
												ex.details.missing &&
												` (${ ex.details.type }: ${ ex.details.missing.join( ', ' ) })` }{ ' ' }
											{ ex.edit_link && (
												<a href={ ex.edit_link }>
													{ __(
														'Edit',
														'chatbot'
													) }
												</a>
											) }{ ' ' }
											{ 'noindex' === check.id &&
												data.can.publish && (
													<Button
														variant="link"
														onClick={ () =>
															fix( ex )
														}
													>
														{ __(
															'Remove noindex',
															'chatbot'
														) }
													</Button>
												) }
											{ [
												'page_error',
												'broken_internal',
											].includes( check.id ) &&
												data.can.settings && (
													<Button
														variant="link"
														onClick={ () =>
															onRedirect(
																( ex.details &&
																	ex.details
																		.target ) ||
																	ex.url
															)
														}
													>
														{ __(
															'Add a redirect',
															'chatbot'
														) }
													</Button>
												) }
										</li>
									) ) }
								</ul>
							</div>
						) ) }
				</div>
			) ) }
		</div>
	);
};

const RedirectForm = ( { source, onSaved } ) => {
	const [ from, setFrom ] = useState( source || '' );
	const [ to, setTo ] = useState( '' );
	const [ code, setCode ] = useState( '301' );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => setFrom( source || '' ), [ source ] );
	const save = () =>
		seo( 'redirects/save', {
			method: 'POST',
			data: { source: from, target: to, code: Number( code ) },
		} )
			.then( () => {
				setMessage( __( 'Redirect saved.', 'chatbot' ) );
				setTo( '' );
				if ( onSaved ) {
					onSaved();
				}
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	return (
		<div className="qcseo-grid qcseo-redirect-form">
			<TextControl
				__next40pxDefaultSize
				label={ __( 'Old address (path)', 'chatbot' ) }
				value={ from }
				onChange={ setFrom }
				__nextHasNoMarginBottom
			/>
			<TextControl
				__next40pxDefaultSize
				label={ __( 'New address', 'chatbot' ) }
				value={ to }
				onChange={ setTo }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Type', 'chatbot' ) }
				value={ code }
				options={ [
					{
						value: '301',
						label: __( 'Moved for good (301)', 'chatbot' ),
					},
					{
						value: '302',
						label: __( 'Moved for now (302)', 'chatbot' ),
					},
					{
						value: '410',
						label: __( 'Gone for good (410)', 'chatbot' ),
					},
				] }
				onChange={ setCode }
				__nextHasNoMarginBottom
			/>
			<Button variant="primary" onClick={ save }>
				{ __( 'Save redirect', 'chatbot' ) }
			</Button>
			{ message && <p className="qcseo-message">{ message }</p> }
		</div>
	);
};

const RedirectsTab = ( { source } ) => {
	const [ view, setView ] = useState( null );
	const load = () => seo( 'redirects' ).then( setView );
	useEffect( () => {
		load();
	}, [] );
	if ( ! view ) {
		return <Spinner />;
	}
	if ( view.handled ) {
		return (
			<p>
				{ sprintf(
					/* translators: %s: product name, for example WPBot. */
					__(
						"Another plugin handles redirects on this site, so %s's simple redirects are off (they would compete). Add redirects there.",
						'chatbot'
					),
					product
				) }
			</p>
		);
	}
	return (
		<div className="qcseo-card">
			{ data.can.settings && (
				<RedirectForm source={ source } onSaved={ load } />
			) }
			<table className="widefat striped qcseo-table">
				<thead>
					<tr>
						<th>{ __( 'From', 'chatbot' ) }</th>
						<th>{ __( 'To', 'chatbot' ) }</th>
						<th className="num">
							{ __( 'Hits', 'chatbot' ) }
						</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					{ view.items.map( ( item ) => (
						<tr key={ item.id }>
							<td>{ item.source }</td>
							<td>
								{ 410 === item.code
									? __( 'Gone (410)', 'chatbot' )
									: item.target }
							</td>
							<td className="num">{ item.hits }</td>
							<td>
								{ data.can.settings && (
									<Button
										variant="link"
										isDestructive
										onClick={ () =>
											seo(
												`redirects/${ item.id }/delete`,
												{ method: 'POST' }
											).then( load )
										}
									>
										{ __( 'Delete', 'chatbot' ) }
									</Button>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
};

const NotFoundTab = ( { onRedirect } ) => {
	const [ rows, setRows ] = useState( null );
	const load = () => seo( 'not-found' ).then( setRows );
	useEffect( () => {
		load();
	}, [] );
	if ( ! rows ) {
		return <Spinner />;
	}
	return (
		<div className="qcseo-card">
			<p className="qcseo-muted">
				{ __(
					'Addresses visitors asked for that do not exist. Only the address, the referring site and counts are kept (no IP addresses).',
					'chatbot'
				) }
			</p>
			{ 0 === rows.length ? (
				<p>{ __( 'No 404s logged.', 'chatbot' ) }</p>
			) : (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Address', 'chatbot' ) }</th>
							<th>{ __( 'From', 'chatbot' ) }</th>
							<th className="num">
								{ __( 'Hits', 'chatbot' ) }
							</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.id }>
								<td>{ row.url }</td>
								<td>{ row.referrer }</td>
								<td className="num">{ row.hits }</td>
								<td>
									{ row.redirected
										? __( 'Redirected', 'chatbot' )
										: data.can.settings && (
												<Button
													variant="link"
													onClick={ () =>
														onRedirect( row.url )
													}
												>
													{ __(
														'Add a redirect',
														'chatbot'
													) }
												</Button>
											) }{ ' ' }
									{ data.can.use && (
										<Button
											variant="link"
											onClick={ () =>
												seo(
													`not-found/${ row.id }/delete`,
													{ method: 'POST' }
												).then( load )
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
		</div>
	);
};

const SpeedTab = () => {
	const [ url, setUrl ] = useState( data.siteUrl );
	const [ results, setResults ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'technical' ).then( ( view ) => setResults( view.speed ) );
	}, [] );
	const check = () => {
		setBusy( true );
		setMessage( '' );
		seo( 'pagespeed', { method: 'POST', data: { url } } )
			.then( ( result ) =>
				setResults( { [ url ]: result, ...( results || {} ) } )
			)
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const metric = ( m, unit ) =>
		m && null !== m.p75 ? `${ m.p75 }${ unit } (${ m.category })` : '–';
	return (
		<div className="qcseo-card">
			<p className="qcseo-muted">
				{ __(
					'Google PageSpeed Insights: a lab score, and Core Web Vitals from real Chrome visitors when Google has enough data. Fixes for speed are in Site Insight.',
					'chatbot'
				) }
			</p>
			<div className="qcseo-grid">
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Page address', 'chatbot' ) }
					value={ url }
					onChange={ setUrl }
					__nextHasNoMarginBottom
				/>
				{ data.can.use && (
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ check }
					>
						{ __( 'Check speed', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ message && <p className="qcseo-error">{ message }</p> }
			{ results &&
				Object.entries( results ).map( ( [ page, r ] ) => (
					<div key={ page } className="qcseo-speed">
						<h4>{ page }</h4>
						<p>
							{ __( 'Lab score:', 'chatbot' ) }{ ' ' }
							<strong>
								{ null !== r.lab.score ? r.lab.score : '–' }
							</strong>
							{ ' · ' }
							{ __(
								'Real visitors:',
								'chatbot'
							) }{ ' ' }
							<strong>
								{ r.field.overall ||
									__(
										'not enough data',
										'chatbot'
									) }
							</strong>
						</p>
						<p className="qcseo-muted">
							LCP { metric( r.field.lcp, ' ms' ) } · INP{ ' ' }
							{ metric( r.field.inp, ' ms' ) } · CLS{ ' ' }
							{ metric( r.field.cls, '' ) }
						</p>
					</div>
				) ) }
		</div>
	);
};

const TechnicalScreen = () => {
	const [ redirectFor, setRedirectFor ] = useState( '' );
	// A tab asked for by another screen (navigate intent) or a link (&section=llms).
	const [ tab, setTab ] = useState( () => {
		const taken = takeIntent();
		if ( taken && taken.tab ) {
			return taken.tab;
		}
		return (
			new URLSearchParams( window.location.search ).get( 'section' ) ||
			'report'
		);
	} );
	const toRedirect = ( url ) => {
		try {
			setRedirectFor( new window.URL( url, data.siteUrl ).pathname );
		} catch {
			setRedirectFor( url );
		}
		setTab( 'redirects' );
	};
	/**
	 * Tabs; the Pro module adds schedule, alerts and pattern redirects.
	 */
	const tabs = withPreviewTabs(
		'technical',
		applyFilters( 'qcld_wpbot_seo.technical.tabs', [
			{
				name: 'report',
				title: __( 'Scan', 'chatbot' ),
				render: () => <Report onRedirect={ toRedirect } />,
			},
			{
				name: 'notfound',
				title: __( '404 log', 'chatbot' ),
				render: () => <NotFoundTab onRedirect={ toRedirect } />,
			},
			{
				name: 'redirects',
				title: __( 'Redirects', 'chatbot' ),
				render: () => <RedirectsTab source={ redirectFor } />,
			},
			{
				name: 'speed',
				title: __( 'Speed', 'chatbot' ),
				render: () => <SpeedTab />,
			},
			{
				name: 'llms',
				title: __( 'llms.txt', 'chatbot' ),
				render: () => <LlmsTab />,
			},
		] )
	);
	return (
		<div className="qcseo-technical">
			<h2 className="qcseo-screen-title">
				{ __( 'Technical SEO', 'chatbot' ) }
			</h2>
			<TabPanel
				key={ tab }
				className="qcseo-tabs"
				initialTabName={ tab }
				onSelect={ setTab }
				tabs={ tabs.map( ( t ) => ( {
					name: t.name,
					title: t.title,
					className: t.className,
				} ) ) }
			>
				{ ( current ) => {
					const found = tabs.find( ( t ) => t.name === current.name );
					return found ? found.render() : null;
				} }
			</TabPanel>
		</div>
	);
};

export default TechnicalScreen;
