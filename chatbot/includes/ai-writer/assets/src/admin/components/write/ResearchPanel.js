/**
 * CR-16 F3: original research in the Real data step. The owner picks the
 * data (an uploaded CSV, a CSV in the chatbot knowledge, or a WPBot Pro
 * source), checks the numbers AI Writer computed (findings, a bar chart,
 * the method), and approves them. Only approved numbers are written.
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { data, errorMessage, writer } from '../../api';
import { ProSpot } from '../pro/ProPreview';

const Bars = ( { bars } ) => {
	const max = Math.max( 1, ...bars.map( ( bar ) => Number( bar.value ) ) );
	return (
		<div className="qcwr-bars" aria-hidden="true">
			{ bars.map( ( bar ) => (
				<div className="qcwr-bars__row" key={ bar.label }>
					<span className="qcwr-bars__label">{ bar.label }</span>
					<span
						className="qcwr-bars__bar"
						style={ {
							width: `${ Math.max(
								2,
								( Number( bar.value ) / max ) * 100
							) }%`,
						} }
					/>
					<span className="qcwr-bars__value">
						{ Number( bar.value ).toLocaleString() }
					</span>
				</div>
			) ) }
		</div>
	);
};

const Findings = ( { result } ) => {
	const [ tab, setTab ] = useState( 0 );
	const finding = result.findings[ tab ] || result.findings[ 0 ];
	return (
		<div className="qcwr-research__result">
			<div className="qcwr-research__tabs" role="tablist">
				{ result.findings.map( ( item, index ) => (
					<button
						type="button"
						role="tab"
						key={ item.id }
						aria-selected={ index === tab }
						className={ index === tab ? 'is-on' : undefined }
						onClick={ () => setTab( index ) }
					>
						{ item.title }
					</button>
				) ) }
			</div>
			<div role="tabpanel" className="qcwr-research__panel">
				<p className="qcwr-research__finding">{ finding.text }</p>
				{ finding.chart && finding.chart.length > 0 && (
					<Bars bars={ finding.chart } />
				) }
				<table className="widefat striped">
					<thead>
						<tr>
							{ finding.head.map( ( cell ) => (
								<th key={ cell }>{ cell }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ finding.rows.map( ( row, index ) => (
							<tr key={ index }>
								{ row.map( ( cell, col ) => (
									<td key={ col }>{ cell }</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			<div className="qcwr-research__method">
				<strong>{ __( 'Method', 'chatbot' ) }</strong>
				<p>{ result.method }</p>
			</div>
		</div>
	);
};

const ResearchPanel = ( { brief, setBrief } ) => {
	const [ sources, setSources ] = useState( null );
	const [ tab, setTab ] = useState( 'upload' );
	const [ file, setFile ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ working, setWorking ] = useState( false );
	const [ error, setError ] = useState( '' );
	const approved = !! result && brief.research_id === result.id;

	useEffect( () => {
		writer( 'write/research/sources' )
			.then( setSources )
			.catch( () => setSources( { knowledge: [], pro: [], min: 10 } ) );
		if ( brief.research_id ) {
			writer( `write/research/${ brief.research_id }` )
				.then( setResult )
				.catch( () =>
					setBrief( ( current ) => ( {
						...current,
						research_id: '',
					} ) )
				);
		}
		// Load once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const run = ( request ) => {
		setWorking( true );
		setError( '' );
		setResult( null );
		setBrief( ( current ) => ( { ...current, research_id: '' } ) );
		request
			.then( setResult )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setWorking( false ) );
	};

	const upload = () => {
		const form = new window.FormData();
		form.append( 'file', file );
		run(
			apiFetch( {
				path: '/wpbot-writer/v1/write/research/file',
				method: 'POST',
				body: form,
			} )
		);
	};

	const pick = ( type, id ) =>
		run(
			writer( 'write/research/source', {
				method: 'POST',
				data: { type, id },
			} )
		);

	if ( ! sources ) {
		return <Spinner />;
	}
	const tabs = [ [ 'upload', __( 'Upload a CSV', 'chatbot' ) ] ];
	if ( sources.knowledge.length ) {
		tabs.push( [
			'knowledge',
			__( 'Chatbot knowledge', 'chatbot' ),
		] );
	}
	sources.pro.forEach( ( source ) =>
		tabs.push( [ source.id, source.label ] )
	);

	return (
		<div className="qcwr-research">
			<h3>{ __( 'Your research data', 'chatbot' ) }</h3>
			<p className="qcwr-muted">
				{ sprintf(
					/* translators: %d: smallest group shown. */
					__(
						'AI Writer counts and averages the data itself; the AI only writes about the numbers you approve. Groups smaller than %d rows are hidden.',
						'chatbot'
					),
					sources.min
				) }
			</p>
			<div className="qcwr-research__tabs" role="tablist">
				{ tabs.map( ( [ id, label ] ) => (
					<button
						type="button"
						role="tab"
						key={ id }
						aria-selected={ id === tab }
						className={ id === tab ? 'is-on' : undefined }
						onClick={ () => setTab( id ) }
					>
						{ label }
					</button>
				) ) }
				{ ! sources.pro.length && (
					<ProSpot spot="research.activity" variant="link" />
				) }
			</div>
			<div className="qcwr-research__source">
				{ 'upload' === tab && (
					<p>
						<label htmlFor="qcwr-research-file">
							{ __( 'CSV file', 'chatbot' ) }
						</label>{ ' ' }
						<input
							id="qcwr-research-file"
							type="file"
							accept=".csv,.tsv,.txt"
							onChange={ ( event ) =>
								setFile( event.target.files[ 0 ] || null )
							}
						/>{ ' ' }
						<Button
							variant="secondary"
							onClick={ upload }
							disabled={ ! file || working }
						>
							{ __( 'Analyse', 'chatbot' ) }
						</Button>
						<br />
						<span className="qcwr-muted">
							{ __(
								'First row = column names. Up to 5 MB. No AI call, no cost. The file is read and then deleted.',
								'chatbot'
							) }
						</span>
					</p>
				) }
				{ 'knowledge' === tab && (
					<ul>
						{ sources.knowledge.map( ( item ) => (
							<li key={ item.id }>
								<Button
									variant="link"
									onClick={ () =>
										pick( 'knowledge', item.id )
									}
									disabled={ working }
								>
									{ item.title }
								</Button>{ ' ' }
								<span className="qcwr-muted">
									{ sprintf(
										/* translators: %d: rows. */
										_n(
											'%d row',
											'%d rows',
											item.rows,
											'chatbot'
										),
										item.rows
									) }
								</span>
							</li>
						) ) }
					</ul>
				) }
				{ sources.pro
					.filter( ( source ) => source.id === tab )
					.map( ( source ) => (
						<p key={ source.id }>
							<span className="qcwr-muted">
								{ source.available
									? source.help
									: source.reason }
							</span>{ ' ' }
							<Button
								variant="secondary"
								onClick={ () => pick( 'pro', source.id ) }
								disabled={ ! source.available || working }
							>
								{ __( 'Analyse', 'chatbot' ) }
							</Button>
						</p>
					) ) }
			</div>
			{ working && <Spinner /> }
			{ error && (
				<Notice status="warning" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ result && (
				<>
					<h3>
						{ sprintf(
							/* translators: 1: data name, 2: amount, 3: unit (rows, chats…). */
							__(
								'Check the numbers: %1$s (%2$s %3$s)',
								'chatbot'
							),
							result.label,
							Number( result.rows ).toLocaleString(),
							result.unit
						) }
					</h3>
					<Findings result={ result } />
					<div className="qcwr-actions">
						{ approved ? (
							<span className="qcwr-research__approved">
								{ __(
									'Approved: these numbers will be written, with their tables, charts and method.',
									'chatbot'
								) }
							</span>
						) : (
							<Button
								variant="primary"
								onClick={ () =>
									setBrief( ( current ) => ( {
										...current,
										research_id: result.id,
									} ) )
								}
							>
								{ __(
									'Approve these numbers',
									'chatbot'
								) }
							</Button>
						) }
					</div>
				</>
			) }
		</div>
	);
};

export default ResearchPanel;
