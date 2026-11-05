/**
 * Opportunities: one ranked list with type filters, dismissed list, refresh.
 */
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../api';
import { loadStatus } from '../status';
import VolumeLookup from '../components/VolumeLookup';
import ConnectCard from '../components/ConnectCard';
import OpportunityItem from '../components/OpportunityItem';
import { AiReason, aiBlocked, useAiReady } from '../ai';

const OpportunitiesScreen = () => {
	const [ type, setType ] = useState( '' );
	const [ status, setStatus ] = useState( 'open' );
	const [ view, setView ] = useState( null );
	const [ conn, setConn ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setError( '' );
		seo( `opportunities?type=${ type }&status=${ status }&limit=100` )
			.then( setView )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [ type, status ] );

	useEffect( () => {
		loadStatus()
			.then( setConn )
			.catch( () => setConn( null ) );
	}, [] );
	useEffect( load, [ load ] );

	const ai = useAiReady();
	const aiOff = aiBlocked( ai );
	// While the first scan runs, check for new opportunities every 20 seconds.
	const scanning = !! ( view && view.first_scan && view.first_scan.running );
	useEffect( () => {
		if ( ! scanning ) {
			return undefined;
		}
		const timer = setInterval( load, 20000 );
		return () => clearInterval( timer );
	}, [ scanning, load ] );
	const findGaps = () => {
		setBusy( 'gaps' );
		setError( '' );
		seo( 'opportunities/topic-gaps', { method: 'POST' } )
			.then( load )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const refresh = () => {
		setBusy( true );
		seo( 'opportunities/refresh', { method: 'POST' } )
			.then( load )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const counts = view ? view.counts : {};
	const total = Object.values( counts ).reduce( ( a, b ) => a + b, 0 );

	return (
		<div className="qcseo-opps">
			<div className="qcseo-head">
				<h2 className="qcseo-screen-title">
					{ __( 'Opportunities', 'chatbot' ) }
				</h2>
				{ data.can.use && (
					<Button
						variant="secondary"
						isBusy={ true === busy }
						disabled={ !! busy }
						onClick={ refresh }
					>
						{ __( 'Check again', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcseo-muted">
				{ __(
					'Where you can win search traffic, ranked by how much each page matters to your site (and, with Search Console, the clicks you could gain) and the work it takes.',
					'chatbot'
				) }
			</p>
			<ConnectCard status={ conn } strong />
			{ view && view.first_scan && view.first_scan.running && (
				<div className="qcseo-card qcseo-first-scan" role="status">
					<Spinner />{ ' ' }
					{ __(
						'First scan of your site is running: page audits, a technical check and a first Topic Map from your categories. Opportunities appear here within minutes; this page updates as they come in.',
						'chatbot'
					) }
				</div>
			) }
			{ view && data.can.use && (
				<div className="qcseo-actions qcseo-topic-gaps">
					<Button
						variant="secondary"
						isBusy={ 'gaps' === busy }
						disabled={ !! busy || !! aiOff }
						onClick={ findGaps }
					>
						{ __( 'Find topic gaps with AI', 'chatbot' ) }
					</Button>
					<span className="qcseo-muted">
						{ view.topic_gaps && view.topic_gaps.generated_at
							? sprintf(
									/* translators: 1: number of topics, 2: date. */
									__(
										'%1$d AI suggestions from %2$s. They show as "Topic gap (AI suggestion)" with no numbers.',
										'chatbot'
									),
									view.topic_gaps.count,
									view.topic_gaps.generated_at.slice( 0, 10 )
								)
							: __(
									'Topics your site is missing, suggested by your AI provider (uses AI credits; runs only when you click).',
									'chatbot'
								) }
					</span>
					{ aiOff && <AiReason /> }
				</div>
			) }
			{ view && view.keywords && data.can.use && (
				<VolumeLookup onDone={ load } />
			) }
			{ error && <p className="qcseo-error">{ error }</p> }
			{ ! view && ! error && <Spinner /> }
			{ view && (
				<>
					<div className="qcseo-filters">
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Type', 'chatbot' ) }
							value={ type }
							options={ [
								{
									value: '',
									label: sprintf(
										/* translators: %d: number of open opportunities. */
										__(
											'All types (%d)',
											'chatbot'
										),
										total
									),
								},
								...view.types.map( ( t ) => ( {
									value: t.id,
									label: `${ t.label } (${ counts[ t.id ] || 0 })`,
								} ) ),
							] }
							onChange={ setType }
							__nextHasNoMarginBottom
						/>
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Show', 'chatbot' ) }
							value={ status }
							options={ [
								{
									value: 'open',
									label: __( 'Open', 'chatbot' ),
								},
								{
									value: 'dismissed',
									label: __(
										'Dismissed',
										'chatbot'
									),
								},
							] }
							onChange={ setStatus }
							__nextHasNoMarginBottom
						/>
					</div>
					{ 0 === view.items.length ? (
						<div className="qcseo-card">
							<p>
								{ 'open' === status
									? __(
											'No opportunities right now. They are checked again every day, after every page audit and after every Search Console update.',
											'chatbot'
										)
									: __(
											'Nothing dismissed.',
											'chatbot'
										) }
							</p>
						</div>
					) : (
						<ul className="qcseo-opp-list">
							{ view.items.map( ( item ) => (
								<OpportunityItem
									key={ item.id }
									item={ item }
									writer={ view.writer }
									onChange={ load }
								/>
							) ) }
						</ul>
					) }
				</>
			) }
		</div>
	);
};

export default OpportunitiesScreen;
