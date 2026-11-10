/* global wp */
import { api, boot } from './api';
import { Toggle, Section } from './fields';

const { createElement, useState } = wp.element;
const { __, sprintf } = wp.i18n;

const DAYS = () => [
	__( 'Sunday', 'qcin-textdomain' ),
	__( 'Monday', 'qcin-textdomain' ),
	__( 'Tuesday', 'qcin-textdomain' ),
	__( 'Wednesday', 'qcin-textdomain' ),
	__( 'Thursday', 'qcin-textdomain' ),
	__( 'Friday', 'qcin-textdomain' ),
	__( 'Saturday', 'qcin-textdomain' ),
];

/**
 * Editable copy of the report settings.
 *
 * @param {Object} data Response of GET /reports.
 * @return {Object|null} Form state.
 */
export function reportsFormFrom( data ) {
	if ( ! data ) {
		return null;
	}
	return {
		enabled: !! data.enabled,
		frequency: data.frequency === 'monthly' ? 'monthly' : 'weekly',
		day: parseInt( data.day, 10 ) || 0,
		recipients: data.recipients || '',
	};
}

/**
 * "WordPress handed the test to ..." with the From address it used.
 *
 * @param {Object} r Response of POST /reports/test.
 * @return {string} Message.
 */
function sentMessage( r ) {
	const to = ( r.to || [] ).join( ', ' );
	const msg = r.from
		? sprintf(
			/* translators: 1: email addresses, 2: sender address */
			__( 'Sent to %1$s from %2$s.', 'qcin-textdomain' ),
			to,
			r.from
		)
		: sprintf(
			/* translators: %s: email addresses */
			__( 'Sent to %s.', 'qcin-textdomain' ),
			to
		);
	return msg + ' ' + __( 'WordPress handed the email to this site\'s mail system. If it does not arrive in a few minutes, check the spam folder.', 'qcin-textdomain' );
}

function longDate( iso ) {
	if ( ! iso ) {
		return '';
	}
	return new Date( iso ).toLocaleString( undefined, { weekday: 'long', day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit' } );
}

/**
 * Settings > Email reports.
 *
 * @param {Object}   props
 * @param {Object}   props.data    GET /reports.
 * @param {Object}   props.form    Form state.
 * @param {Function} props.setForm Update the form.
 * @param {boolean}  props.dirty   Unsaved changes in this section.
 */
export function ReportsSection( { data, form, setForm, dirty } ) {
	const [ test, setTest ] = useState( null );
	const [ sending, setSending ] = useState( false );

	if ( ! data || ! form ) {
		return null;
	}
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const title = boot.title || __( 'Insights', 'qcin-textdomain' );

	const sendTest = () => {
		setSending( true );
		setTest( null );
		api.post( '/reports/test', { recipients: form.recipients, frequency: form.frequency } )
			.then( ( r ) => setTest( { ok: true, message: sentMessage( r ), hint: r.mailer === 'mail' } ) )
			.catch( ( err ) => setTest( { ok: false, message: ( err && err.message ) || __( 'The test could not be sent.', 'qcin-textdomain' ) } ) )
			.finally( () => setSending( false ) );
	};

	const result = data.lastResult || {};
	let status = '';
	if ( ! data.enabled ) {
		status = __( 'Email reports are off.', 'qcin-textdomain' );
	} else if ( data.nextSend ) {
		/* translators: %s: date and time of the next report */
		status = sprintf( __( 'Next report: %s.', 'qcin-textdomain' ), longDate( data.nextSend ) );
	}
	let last = '';
	if ( result.skipped ) {
		/* translators: %s: date */
		last = sprintf( __( 'The last report (%s) was skipped because there were no visits in that period.', 'qcin-textdomain' ), longDate( result.at ) );
	} else if ( result.ok === false ) {
		/* translators: 1: date, 2: error message */
		last = sprintf( __( 'The last report (%1$s) could not be sent: %2$s', 'qcin-textdomain' ), longDate( result.at ), result.error || '' );
	} else if ( data.lastSent ) {
		/* translators: %s: date */
		last = sprintf( __( 'Last sent: %s.', 'qcin-textdomain' ), longDate( data.lastSent ) );
	}

	return (
		<Section
			id="qcin-reports"
			title={ __( 'Email reports', 'qcin-textdomain' ) }
			desc={ __( 'A short summary by email: visits, frustration signals, top pages, JavaScript errors and where visitors came from, compared with the period before. Only totals are sent, never visitor data.', 'qcin-textdomain' ) }
		>
			<Toggle
				id="qcin-reports-enabled"
				checked={ form.enabled }
				onChange={ set( 'enabled' ) }
				label={ __( 'Send email reports', 'qcin-textdomain' ) }
				help={ __( 'Reports go out at about 6:00 site time. A report is skipped when its period had no visits.', 'qcin-textdomain' ) }
			/>
			<div className="qcin-grid-2">
				<div className="qcin-field">
					<label htmlFor="qcin-reports-frequency">{ __( 'How often', 'qcin-textdomain' ) }</label>
					<select id="qcin-reports-frequency" value={ form.frequency } onChange={ ( e ) => set( 'frequency' )( e.target.value ) } disabled={ ! form.enabled }>
						<option value="weekly">{ __( 'Weekly (the last 7 days)', 'qcin-textdomain' ) }</option>
						<option value="monthly">{ __( 'Monthly (the previous month, sent on the 1st)', 'qcin-textdomain' ) }</option>
					</select>
				</div>
				{ form.frequency === 'weekly' && (
					<div className="qcin-field">
						<label htmlFor="qcin-reports-day">{ __( 'Send on', 'qcin-textdomain' ) }</label>
						<select id="qcin-reports-day" value={ form.day } onChange={ ( e ) => set( 'day' )( parseInt( e.target.value, 10 ) ) } disabled={ ! form.enabled }>
							{ DAYS().map( ( d, i ) => <option key={ i } value={ i }>{ d }</option> ) }
						</select>
					</div>
				) }
			</div>
			<div className="qcin-field">
				<label htmlFor="qcin-reports-to">{ __( 'Send to', 'qcin-textdomain' ) }</label>
				<input id="qcin-reports-to" type="text" value={ form.recipients } onChange={ ( e ) => set( 'recipients' )( e.target.value ) } placeholder={ data.adminEmail } disabled={ ! form.enabled } />
				<p className="qcin-help">
					{ sprintf(
						/* translators: %s: the site's admin email address */
						__( 'Up to 10 addresses, separated by commas. Empty means the admin email from Settings > General (%s).', 'qcin-textdomain' ),
						data.adminEmail
					) }
				</p>
			</div>
			<div className="qcin-reports-actions">
				<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" onClick={ sendTest } disabled={ sending }>
					{ sending ? __( 'Sending…', 'qcin-textdomain' ) : __( 'Send a test now', 'qcin-textdomain' ) }
				</button>
				<a className="qcin-btn qcin-btn--ghost qcin-btn--small" href={ form.frequency === 'monthly' ? data.previewUrlMonthly : data.previewUrl } target="_blank" rel="noopener noreferrer">
					{ __( 'Preview', 'qcin-textdomain' ) }
					<span className="screen-reader-text">{ __( '(opens in a new tab)', 'qcin-textdomain' ) }</span>
				</a>
				{ dirty && <span className="qcin-muted">{ __( 'The test uses the addresses and frequency above, even before you save.', 'qcin-textdomain' ) }</span> }
			</div>
			{ test && (
				<p className={ 'qcin-reports-test ' + ( test.ok ? 'qcin-ai-ok' : 'qcin-ai-bad' ) } role="status">{ test.message }</p>
			) }
			{ test && test.hint && (
				<p className="qcin-notice qcin-notice--warn qcin-reports-hint" style={ { margin: '10px 0 0', fontSize: '13px' } }>
					{ __( 'This site sends email with PHP\'s mail() function. Many hosts block it or the email lands in spam, because it is not signed for your domain. If the test does not arrive, set up an SMTP plugin (for example WP Mail SMTP or FluentSMTP) and send the test again.', 'qcin-textdomain' ) }
				</p>
			) }
			{ ( status || last ) && (
				<p className="qcin-help qcin-reports-status">
					{ status } { last }
				</p>
			) }
			<p className="qcin-help">
				{ sprintf(
					/* translators: %s: menu title, e.g. "Site Insight" */
					__( 'Links in the email open %s, so they only work for people who can sign in here.', 'qcin-textdomain' ),
					title
				) }
			</p>
		</Section>
	);
}
