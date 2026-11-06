/**
 * CR-16 F2: "Real data", between the brief and the outline, for a piece
 * written in a format. The owner picks the sources (with the cost first),
 * AI Writer proposes facts with their source, and the owner ticks what to
 * use. Facts the format needs but nobody has become [add: …] notes.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import ResearchPanel from './ResearchPanel';
import { previewFor, ProSpot } from '../pro/ProPreview';

const SHOWN = 12;

const PREVIEW_LABELS = () => ( {
	web: __( 'Web research with sources', 'chatbot' ),
	who_ranks: __( 'Who ranks', 'chatbot' ),
	interview: __( 'Interview me', 'chatbot' ),
	research: __(
		'Research from chats, orders and Search Console',
		'chatbot'
	),
	questions: __( 'Visitor questions from chats', 'chatbot' ),
} );

export const Steps = ( { current } ) => {
	const steps = [
		[ 'brief', __( 'Brief', 'chatbot' ) ],
		[ 'data', __( 'Real data', 'chatbot' ) ],
		[ 'outline', __( 'Outline', 'chatbot' ) ],
		[ 'writing', __( 'Writing', 'chatbot' ) ],
		[ 'done', __( 'Done', 'chatbot' ) ],
	];
	return (
		<ol className="qcwr-steps">
			{ steps.map( ( [ id, label ] ) => (
				<li
					key={ id }
					className={ id === current ? 'is-current' : undefined }
					aria-current={ id === current ? 'step' : undefined }
				>
					{ label }
				</li>
			) ) }
		</ol>
	);
};

const DataStep = ( { brief, setBrief, onBack, onNext, busy } ) => {
	const [ offer, setOffer ] = useState( null );
	const [ picked, setPicked ] = useState( [] );
	const [ found, setFound ] = useState( null );
	const [ ticked, setTicked ] = useState( {} );
	const [ missing, setMissing ] = useState( {} );
	const [ all, setAll ] = useState( false );
	const [ working, setWorking ] = useState( false );
	const [ error, setError ] = useState( '' );
	const format = ( data.formats || [] ).find(
		( item ) => item.value === brief.format
	);

	useEffect( () => {
		writer( 'write/data/sources', { method: 'POST', data: { brief } } )
			.then( ( result ) => {
				setOffer( result );
				setPicked(
					result.sources
						.filter( ( source ) => source.ticked )
						.map( ( source ) => source.id )
				);
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );
		// Load once per visit of the step.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const cost = () => {
		if ( ! offer ) {
			return null;
		}
		const ai = offer.sources.filter(
			( source ) => picked.includes( source.id ) && source.ai
		).length;
		const extra = offer.sources
			.filter(
				( source ) =>
					picked.includes( source.id ) && Number( source.usd ) > 0
			)
			.reduce( ( sum, source ) => sum + Number( source.usd ), 0 );
		const usd =
			( ai
				? Number( offer.estimate.usd_one || offer.estimate.usd )
				: 0 ) + extra;
		return usd > 0
			? sprintf(
					/* translators: %s: cost in US dollars. */
					__(
						'About US$%s with your AI provider.',
						'chatbot'
					),
					usd.toFixed( 3 )
				)
			: __( 'No AI call: free.', 'chatbot' );
	};

	const gather = () => {
		setWorking( true );
		setError( '' );
		writer( 'write/data/gather', {
			method: 'POST',
			data: { brief, sources: picked },
		} )
			.then( ( result ) => {
				setFound( result );
				setTicked(
					Object.fromEntries(
						result.facts.map( ( fact ) => [ fact.id, true ] )
					)
				);
				setMissing(
					Object.fromEntries(
						result.missing.map( ( item ) => [ item, true ] )
					)
				);
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setWorking( false ) );
	};

	const use = () => {
		const facts = found
			? found.facts.filter( ( fact ) => ticked[ fact.id ] )
			: [];
		const still = Object.keys( missing ).filter(
			( item ) => missing[ item ]
		);
		const next = { ...brief, facts, missing: still };
		setBrief( next );
		onNext( next );
	};

	const skip = () => {
		const next = { ...brief, facts: [], missing: [] };
		setBrief( next );
		onNext( next );
	};

	const facts = found ? found.facts : [];
	const shown = all ? facts : facts.slice( 0, SHOWN );
	const chosen = facts.filter( ( fact ) => ticked[ fact.id ] ).length;
	const labels = PREVIEW_LABELS();

	return (
		<div className="qcwr-card qcwr-data">
			<Steps current="data" />
			<h2>{ __( 'Real data', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ format
					? sprintf(
							/* translators: %s: format name. */
							__(
								'Format: %s. It works best with your own facts. Pick where to look; you tick what to use. Nothing is invented: anything missing becomes an [add: …] note for you to fill in.',
								'chatbot'
							),
							format.label
						)
					: '' }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ ! offer && ! error && <Spinner /> }
			{ format && ( format.panels || [] ).includes( 'research' ) && (
				<ResearchPanel brief={ brief } setBrief={ setBrief } />
			) }
			{ offer && ! found && (
				<>
					<ul className="qcwr-data__sources">
						{ offer.sources.map( ( source ) => (
							<li key={ source.id }>
								<CheckboxControl
									label={ source.label }
									help={
										source.available
											? source.help
											: source.reason
									}
									checked={ picked.includes( source.id ) }
									disabled={ ! source.available }
									onChange={ ( on ) =>
										setPicked( ( list ) =>
											on
												? [ ...list, source.id ]
												: list.filter(
														( id ) =>
															id !== source.id
													)
										)
									}
									__nextHasNoMarginBottom
								/>
								{ source.ai && source.available && (
									<span className="qcwr-tag">
										{ __(
											'AI reads it',
											'chatbot'
										) }
									</span>
								) }
							</li>
						) ) }
						{ offer.previews
							.filter( ( id ) => previewFor( 'data.' + id ) )
							.map( ( id ) => (
								<li key={ id } className="is-preview">
									<ProSpot
										spot={ 'data.' + id }
										label={ labels[ id ] || '' }
										variant="link"
									/>
								</li>
							) ) }
					</ul>
					{ applyFilters(
						'qcld_wpbot_writer.write.data_sources',
						null,
						{ brief, picked, setPicked }
					) }
					<p className="qcwr-estimate">{ cost() }</p>
					<div className="qcwr-actions">
						<Button variant="secondary" onClick={ onBack }>
							{ __( 'Back', 'chatbot' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ gather }
							disabled={ working || ! picked.length }
							isBusy={ working }
						>
							{ __( 'Find facts', 'chatbot' ) }
						</Button>
						<Button
							variant="link"
							onClick={ skip }
							disabled={ busy }
						>
							{ brief.research_id
								? __(
										'Continue with the approved research only',
										'chatbot'
									)
								: __(
										'Skip: write without real data',
										'chatbot'
									) }
						</Button>
					</div>
				</>
			) }
			{ found && (
				<>
					{ found.errors.map( ( message ) => (
						<Notice
							key={ message }
							status="warning"
							isDismissible={ false }
						>
							{ message }
						</Notice>
					) ) }
					{ ! facts.length && (
						<p>
							{ __(
								'No facts were found in these sources. You can go back and pick others, or write with [add: …] notes.',
								'chatbot'
							) }
						</p>
					) }
					{ facts.length > 0 && (
						<table className="widefat striped qcwr-data__facts">
							<thead>
								<tr>
									<th className="check-column">
										<span className="screen-reader-text">
											{ __( 'Use', 'chatbot' ) }
										</span>
									</th>
									<th>{ __( 'Fact', 'chatbot' ) }</th>
									<th>
										{ __( 'Source', 'chatbot' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ shown.map( ( fact ) => (
									<tr key={ fact.id }>
										<td>
											<CheckboxControl
												label={
													<span className="screen-reader-text">
														{ fact.text }
													</span>
												}
												checked={ !! ticked[ fact.id ] }
												onChange={ ( on ) =>
													setTicked( ( list ) => ( {
														...list,
														[ fact.id ]: on,
													} ) )
												}
												__nextHasNoMarginBottom
											/>
										</td>
										<td>
											{ fact.text }
											{ fact.quote && (
												<span className="qcwr-tag">
													{ __(
														'may be quoted',
														'chatbot'
													) }
												</span>
											) }
										</td>
										<td>
											{ fact.url ? (
												<a
													href={ fact.url }
													target="_blank"
													rel="noreferrer"
												>
													{ fact.label ||
														fact.source }
												</a>
											) : (
												fact.label || fact.source
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
					{ facts.length > SHOWN && ! all && (
						<Button variant="link" onClick={ () => setAll( true ) }>
							{ sprintf(
								/* translators: %d: number of facts. */
								_n(
									'Show %d more fact',
									'Show %d more facts',
									facts.length - SHOWN,
									'chatbot'
								),
								facts.length - SHOWN
							) }
						</Button>
					) }
					{ found.dropped > 0 && (
						<p className="qcwr-muted">
							{ sprintf(
								/* translators: %d: number of facts left out. */
								_n(
									'%d fact was left out: its words are not in the source.',
									'%d facts were left out: their words are not in the source.',
									found.dropped,
									'chatbot'
								),
								found.dropped
							) }
						</p>
					) }
					{ found.missing.length > 0 && (
						<div className="qcwr-data__missing">
							<h3>
								{ __(
									'Missing: written as [add: …] notes',
									'chatbot'
								) }
							</h3>
							{ found.missing.map( ( item ) => (
								<CheckboxControl
									key={ item }
									label={ `[add: ${ item }]` }
									checked={ !! missing[ item ] }
									onChange={ ( on ) =>
										setMissing( ( list ) => ( {
											...list,
											[ item ]: on,
										} ) )
									}
									__nextHasNoMarginBottom
								/>
							) ) }
						</div>
					) }
					<div className="qcwr-actions">
						<Button
							variant="secondary"
							onClick={ () => setFound( null ) }
						>
							{ __( 'Pick other sources', 'chatbot' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ use }
							disabled={ busy }
							isBusy={ busy }
						>
							{ sprintf(
								/* translators: %d: number of facts ticked. */
								_n(
									'Use %d fact and plan the outline',
									'Use %d facts and plan the outline',
									chosen,
									'chatbot'
								),
								chosen
							) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
};

export default DataStep;
