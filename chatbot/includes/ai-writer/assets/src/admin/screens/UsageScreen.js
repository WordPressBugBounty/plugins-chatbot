/**
 * Usage and cost page (estimates from the provider price list).
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { core, data, errorMessage } from '../api';

const money = ( value ) => `$${ Number( value || 0 ).toFixed( 2 ) }`;

const Table = ( { columns, rows, empty } ) =>
	rows.length ? (
		<table className="widefat striped qcwr-table">
			<thead>
				<tr>
					{ columns.map( ( column ) => (
						<th key={ column.key } scope="col">
							{ column.label }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( row, index ) => (
					<tr key={ index }>
						{ columns.map( ( column ) => (
							<td key={ column.key }>
								{ column.format
									? column.format( row[ column.key ] )
									: row[ column.key ] }
							</td>
						) ) }
					</tr>
				) ) }
			</tbody>
		</table>
	) : (
		<p className="qcwr-muted">{ empty }</p>
	);

const UsageScreen = () => {
	const [ usage, setUsage ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		core( `usage/${ data.module }` )
			.then( setUsage )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	}, [] );

	if ( error ) {
		return <p className="qcwr-error">{ error }</p>;
	}
	if ( ! usage ) {
		return <Spinner />;
	}

	const empty = __( 'No AI calls yet.', 'chatbot' );

	return (
		<div className="qcwr-usage">
			<div className="qcwr-card qcwr-usage__month">
				<h2>{ __( 'This month', 'chatbot' ) }</h2>
				<p className="qcwr-big">{ money( usage.month_cost ) }</p>
				<p className="qcwr-muted">
					{ usage.budget > 0
						? sprintf(
								/* translators: %s: monthly budget. */
								__( 'Monthly budget: %s', 'chatbot' ),
								money( usage.budget )
							)
						: __( 'No monthly budget set.', 'chatbot' ) }
				</p>
				<p className="qcwr-muted">
					{ __(
						'Costs are estimates from public provider prices. Your provider bill is the final word.',
						'chatbot'
					) }
				</p>
			</div>
			<div className="qcwr-card">
				<h2>{ __( 'By month', 'chatbot' ) }</h2>
				<Table
					rows={ usage.months }
					empty={ empty }
					columns={ [
						{
							key: 'month',
							label: __( 'Month', 'chatbot' ),
						},
						{
							key: 'calls',
							label: __( 'Calls', 'chatbot' ),
						},
						{
							key: 'tokens_in',
							label: __( 'Tokens in', 'chatbot' ),
						},
						{
							key: 'tokens_out',
							label: __( 'Tokens out', 'chatbot' ),
						},
						{
							key: 'cost',
							label: __( 'Estimated cost', 'chatbot' ),
							format: money,
						},
					] }
				/>
			</div>
			<div className="qcwr-card">
				<h2>{ __( 'By feature', 'chatbot' ) }</h2>
				<Table
					rows={ usage.features }
					empty={ empty }
					columns={ [
						{
							key: 'feature',
							label: __( 'Feature', 'chatbot' ),
						},
						{
							key: 'calls',
							label: __( 'Calls', 'chatbot' ),
						},
						{
							key: 'cost',
							label: __( 'Estimated cost', 'chatbot' ),
							format: money,
						},
					] }
				/>
			</div>
			<div className="qcwr-card">
				<h2>{ __( 'By model', 'chatbot' ) }</h2>
				<Table
					rows={ usage.models }
					empty={ empty }
					columns={ [
						{
							key: 'provider',
							label: __( 'Provider', 'chatbot' ),
						},
						{
							key: 'model',
							label: __( 'Model', 'chatbot' ),
						},
						{
							key: 'calls',
							label: __( 'Calls', 'chatbot' ),
						},
						{
							key: 'cost',
							label: __( 'Estimated cost', 'chatbot' ),
							format: money,
						},
					] }
				/>
			</div>
		</div>
	);
};

export default UsageScreen;
