/**
 * Exchange records: links agreed by hand with real partners, both sides
 * recorded and checked. No marketplace or partner search, by design.
 */
import {
	Button,
	CheckboxControl,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { EXCHANGE, REL, STATUS, options } from './labels';

const BLANK = {
	partner: '',
	contact: '',
	their_url: '',
	their_anchor: '',
	their_rel: 'follow',
	our_url: '',
	our_target: '',
	our_anchor: '',
	our_rel: 'follow',
	paid: false,
	agreed_on: '',
	ends_on: '',
	agreement: '',
	notes: '',
	status: 'proposed',
};

const Exchanges = () => {
	const [ view, setView ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ busy, setBusy ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'exchanges' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const check = ( id ) => {
		setBusy( id );
		seo( `exchanges/${ id }/check`, { method: 'POST' } )
			.then( load )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( 0 ) );
	};
	return (
		<div className="qcseo-card qcseo-exchanges">
			<div className="qcseo-head">
				<h3>{ __( 'Exchange records', 'chatbot' ) }</h3>
				{ data.can.use && ! form && (
					<Button
						variant="secondary"
						onClick={ () => setForm( { ...BLANK } ) }
					>
						{ __( 'Record an exchange', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcseo-muted">
				{ __(
					'For links you agreed with real partners, suppliers or associations. Both sides are recorded and checked. There is no marketplace or partner search: excessive link exchanges count as a link scheme.',
					'chatbot'
				) }
			</p>
			{ view.warnings.map( ( w ) => (
				<p key={ w } className="qcseo-notice-card">
					{ w }
				</p>
			) ) }
			{ message && <p className="qcseo-error">{ message }</p> }
			{ form && (
				<div className="qcseo-grid qcseo-exchange-form">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Partner', 'chatbot' ) }
						value={ form.partner }
						onChange={ set( 'partner' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Contact', 'chatbot' ) }
						value={ form.contact }
						onChange={ set( 'contact' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Their page linking to you',
							'chatbot'
						) }
						value={ form.their_url }
						onChange={ set( 'their_url' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Their anchor text', 'chatbot' ) }
						value={ form.their_anchor }
						onChange={ set( 'their_anchor' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Their link type', 'chatbot' ) }
						value={ form.their_rel }
						options={ REL }
						onChange={ set( 'their_rel' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Your page linking to them',
							'chatbot'
						) }
						value={ form.our_url }
						onChange={ set( 'our_url' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Their page you link to',
							'chatbot'
						) }
						value={ form.our_target }
						onChange={ set( 'our_target' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Your link type', 'chatbot' ) }
						help={ __(
							'Add the link in the editor yourself; never hidden.',
							'chatbot'
						) }
						value={ form.our_rel }
						options={ REL }
						onChange={ set( 'our_rel' ) }
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __(
							'Something was paid or given in return',
							'chatbot'
						) }
						checked={ !! form.paid }
						onChange={ set( 'paid' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						type="date"
						label={ __( 'Agreed on', 'chatbot' ) }
						value={ form.agreed_on }
						onChange={ set( 'agreed_on' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						type="date"
						label={ __( 'Ends on (optional)', 'chatbot' ) }
						value={ form.ends_on }
						onChange={ set( 'ends_on' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Status', 'chatbot' ) }
						value={ form.status }
						options={ options( EXCHANGE ) }
						onChange={ set( 'status' ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __(
							'Agreement (paste the text)',
							'chatbot'
						) }
						value={ form.agreement }
						onChange={ set( 'agreement' ) }
						rows={ 3 }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Notes', 'chatbot' ) }
						value={ form.notes }
						onChange={ set( 'notes' ) }
						rows={ 2 }
						__nextHasNoMarginBottom
					/>
					{ form.paid && 'sponsored' !== form.our_rel && (
						<p className="qcseo-notice-card">
							{ __(
								'Because something was paid or given, mark your link sponsored.',
								'chatbot'
							) }
						</p>
					) }
					<div>
						<Button
							variant="primary"
							onClick={ () =>
								seo( 'exchanges/save', {
									method: 'POST',
									data: form,
								} )
									.then( () => {
										setForm( null );
										load();
									} )
									.catch( ( e ) =>
										setMessage( errorMessage( e ) )
									)
							}
						>
							{ __( 'Save', 'chatbot' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							onClick={ () => setForm( null ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ 0 === view.items.length ? (
				<p className="qcseo-muted">
					{ __( 'No exchanges recorded.', 'chatbot' ) }
				</p>
			) : (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Partner', 'chatbot' ) }</th>
							<th>
								{ __(
									'Their link to you',
									'chatbot'
								) }
							</th>
							<th>
								{ __(
									'Your link to them',
									'chatbot'
								) }
							</th>
							<th>{ __( 'Status', 'chatbot' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ view.items.map( ( x ) => {
							const last = x.checks[ 0 ];
							return (
								<tr key={ x.id }>
									<td>
										<strong>{ x.partner }</strong>
										{ x.warnings.map( ( w ) => (
											<p
												key={ w }
												className="qcseo-error"
											>
												{ w }
											</p>
										) ) }
									</td>
									<td>
										<a
											href={ x.their_url }
											target="_blank"
											rel="noreferrer noopener"
										>
											{ x.their_url }
										</a>
										{ last && (
											<>
												<br />
												<small>
													{ STATUS[
														last.theirs.status
													] ||
														last.theirs
															.status }{ ' ' }
													{ last.theirs.rel }
												</small>
											</>
										) }
									</td>
									<td>
										{ x.our_url }
										{ last && (
											<>
												<br />
												<small>
													{ STATUS[
														last.ours.status
													] ||
														last.ours
															.status }{ ' ' }
													{ last.ours.rel }
												</small>
											</>
										) }
									</td>
									<td>
										{ EXCHANGE[ x.status ] }
										{ x.last_checked && (
											<>
												<br />
												<small className="qcseo-muted">
													{ sprintf(
														/* translators: %s: date. */
														__(
															'Checked %s',
															'chatbot'
														),
														x.last_checked.slice(
															0,
															10
														)
													) }
												</small>
											</>
										) }
									</td>
									<td>
										{ data.can.use && (
											<Button
												variant="secondary"
												size="compact"
												isBusy={ busy === x.id }
												disabled={ !! busy }
												onClick={ () => check( x.id ) }
											>
												{ __(
													'Check both sides',
													'chatbot'
												) }
											</Button>
										) }{ ' ' }
										{ data.can.use && (
											<Button
												variant="link"
												onClick={ () =>
													setForm( {
														...BLANK,
														...x,
													} )
												}
											>
												{ __(
													'Edit',
													'chatbot'
												) }
											</Button>
										) }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

export default Exchanges;
