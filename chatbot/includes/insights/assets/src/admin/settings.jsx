/* global wp */
import { api, boot } from './api';
import { bytes, relative } from './format';
import { Toggle, NumberField, TextArea, Section } from './fields';
import { AiSection, aiFormFrom, aiPatch } from './ai-settings';
import { ReportsSection, reportsFormFrom } from './reports-settings';
import { extList, hasRender, ExtPart } from './extend';

const { createElement, Fragment, useState, useEffect } = wp.element;
const { __, sprintf } = wp.i18n;

export function SettingsScreen( { settings, save, notify, query } ) {
	const [ form, setForm ] = useState( settings );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ aiData, setAiData ] = useState( null );
	const [ aiForm, setAiForm ] = useState( null );
	const [ repData, setRepData ] = useState( null );
	const [ repForm, setRepForm ] = useState( null );

	useEffect( () => setForm( settings ), [ settings ] );
	useEffect( () => {
		api.get( '/status', { health: 1 } ).then( setStatus ).catch( () => {} );
		api.get( '/ai' )
			.then( ( r ) => {
				setAiData( r );
				setAiForm( aiFormFrom( r ) );
			} )
			.catch( () => {} );
		if ( boot.reports ) {
			api.get( '/reports' )
				.then( ( r ) => {
					setRepData( r );
					setRepForm( reportsFormFrom( r ) );
				} )
				.catch( () => {} );
		}
	}, [] );


	// "#/settings?section=ai" (from a recording's AI summary box) and
	// "#/settings?section=reports" (the link in the email) jump to that
	// section, once every section that loads on its own is there.
	const sectionsReady = !! aiData && ( ! boot.reports || !! repData );
	useEffect( () => {
		const ids = { ai: 'qcin-ai', reports: 'qcin-reports' };
		if ( sectionsReady && query && ids[ query.section ] ) {
			const el = document.getElementById( ids[ query.section ] );
			if ( el && el.scrollIntoView ) {
				el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}
	}, [ sectionsReady ] );

	const onAiData = ( r ) => {
		setAiData( r );
		setAiForm( aiFormFrom( r ) );
	};

	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const mainDirty = JSON.stringify( form ) !== JSON.stringify( settings );
	const aiDirty = !! ( aiData && aiForm ) && JSON.stringify( aiPatch( aiForm ) ) !== JSON.stringify( aiPatch( aiFormFrom( aiData ) ) );
	const repDirty = !! ( repData && repForm ) && JSON.stringify( repForm ) !== JSON.stringify( reportsFormFrom( repData ) );
	const dirty = mainDirty || aiDirty || repDirty;

	const submit = ( e ) => {
		e.preventDefault();
		setSaving( true );
		const jobs = [];
		if ( mainDirty ) {
			jobs.push( save( form ) );
		}
		if ( aiDirty ) {
			jobs.push(
				api.post( '/ai', aiPatch( aiForm ) ).then( ( r ) => {
					onAiData( r );
					if ( ! mainDirty ) {
						notify( __( 'Settings saved.', 'qcin-textdomain' ), 'success' );
					}
				} ).catch( ( err ) => {
					notify( ( err && err.message ) || __( 'Could not save the AI settings.', 'qcin-textdomain' ), 'error' );
				} )
			);
		}
		if ( repDirty ) {
			jobs.push(
				api.post( '/reports', repForm ).then( ( r ) => {
					setRepData( r );
					setRepForm( reportsFormFrom( r ) );
					if ( ! mainDirty && ! aiDirty ) {
						notify( __( 'Settings saved.', 'qcin-textdomain' ), 'success' );
					}
				} ).catch( ( err ) => {
					notify( ( err && err.message ) || __( 'Could not save the email report settings.', 'qcin-textdomain' ), 'error' );
				} )
			);
		}
		Promise.all( jobs ).catch( () => {} ).finally( () => setSaving( false ) );
	};

	const toggleRole = ( role, on ) => {
		const roles = new Set( form.exclude_roles || [] );
		if ( on ) {
			roles.add( role );
		} else {
			roles.delete( role );
		}
		setForm( { ...form, exclude_roles: Array.from( roles ) } );
	};

	const deleteAll = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete all recordings, heatmaps and statistics? This cannot be undone.', 'qcin-textdomain' ) ) ) {
			return;
		}
		api.post( '/delete-all' ).then( () => {
			notify( __( 'All data deleted.', 'qcin-textdomain' ), 'success' );
			api.get( '/status', { health: 1 } ).then( setStatus );
		} );
	};

	const health = status && status.health;
	const store = ( health && health.store ) || {};
	const lastRun = health && health.jobs && health.jobs.qcin_finalize_sessions ? health.jobs.qcin_finalize_sessions.last : null;
	const usedPct = status && status.capBytes ? Math.min( 100, Math.round( ( status.bytes / status.capBytes ) * 100 ) ) : 0;

	return (
		<form className="qcin-settings" onSubmit={ submit }>
			<Section title={ __( 'Recording', 'qcin-textdomain' ) } desc={ __( 'Choose whether and how much to record.', 'qcin-textdomain' ) }>
				<Toggle id="qcin-enabled" checked={ form.enabled } onChange={ set( 'enabled' ) } label={ __( 'Record visitor sessions', 'qcin-textdomain' ) } help={ __( 'When off, nothing is added to your pages and nothing is recorded.', 'qcin-textdomain' ) } />
				<div className="qcin-grid-2">
					<NumberField id="qcin-sample" label={ __( 'Share of sessions to record', 'qcin-textdomain' ) } value={ form.sample_rate } min="1" max="100" suffix="%" onChange={ set( 'sample_rate' ) } help={ __( 'Lower this on busy sites to save storage.', 'qcin-textdomain' ) } />
					<NumberField id="qcin-cap" label={ __( 'Most new recordings per day', 'qcin-textdomain' ) } value={ form.daily_cap } min="1" onChange={ set( 'daily_cap' ) } help={ __( 'Recording pauses for the rest of the day after this many recordings. Heatmaps keep collecting.', 'qcin-textdomain' ) } />
					<NumberField id="qcin-hsample" label={ __( 'Share of visits in heatmaps and frustration stats', 'qcin-textdomain' ) } value={ form.heatmap_sample_rate } min="0" max="100" suffix="%" onChange={ set( 'heatmap_sample_rate' ) } help={ __( 'Clicks and scroll from these visits feed heatmaps and the Overview. They are tiny, so 100% suits most sites. Recorded visits are always included; 0 collects from recorded visits only.', 'qcin-textdomain' ) } />
				</div>
			</Section>

			<Section title={ __( 'Privacy', 'qcin-textdomain' ) } desc={ __( 'Everything typed into forms is always hidden. These settings control the rest.', 'qcin-textdomain' ) }>
				<fieldset className="qcin-field">
					<legend>{ __( 'Visitor consent', 'qcin-textdomain' ) }</legend>
					{ [
						{ v: 'auto', l: __( 'Follow my consent plugin (WP Consent API) when one is installed', 'qcin-textdomain' ) },
						{ v: 'always', l: __( 'Only record visitors who gave consent; record nobody if no consent plugin is installed', 'qcin-textdomain' ) },
						{ v: 'never', l: __( 'Do not ask for consent', 'qcin-textdomain' ) },
					].map( ( o ) => (
						<label key={ o.v } className="qcin-radio">
							<input type="radio" name="qcin-consent" value={ o.v } checked={ form.consent_mode === o.v } onChange={ () => set( 'consent_mode' )( o.v ) } />
							<span>{ o.l }</span>
						</label>
					) ) }
					<p className="qcin-help">
						{ boot.consentApi
							? __( 'A consent plugin that supports the WP Consent API is active. Recording uses the "statistics" category.', 'qcin-textdomain' )
							: __( 'No consent plugin with WP Consent API support was found.', 'qcin-textdomain' ) }
						{ form.consent_mode === 'never' && ' ' + __( 'Visitors in the EU, UK and similar regions usually must give consent first.', 'qcin-textdomain' ) }
					</p>
				</fieldset>
				<Toggle id="qcin-dnt" checked={ form.respect_dnt } onChange={ set( 'respect_dnt' ) } label={ __( 'Respect Do Not Track and Global Privacy Control', 'qcin-textdomain' ) } />
				<Toggle id="qcin-masktext" checked={ form.mask_all_text } onChange={ set( 'mask_all_text' ) } label={ __( 'Hide all page text in recordings', 'qcin-textdomain' ) } help={ __( 'Strictest option: recordings show layout and clicks, but every piece of text is replaced with asterisks.', 'qcin-textdomain' ) } />
				<div className="qcin-grid-2">
					<TextArea id="qcin-masksel" label={ __( 'Also hide text inside', 'qcin-textdomain' ) } value={ form.mask_selectors } placeholder=".account-balance&#10;#order-summary" onChange={ set( 'mask_selectors' ) } help={ __( 'One CSS selector per line. You can also add the class qcin-mask to any element.', 'qcin-textdomain' ) } />
					<TextArea id="qcin-blocksel" label={ __( 'Leave out of recordings entirely', 'qcin-textdomain' ) } value={ form.block_selectors } placeholder=".payment-widget" onChange={ set( 'block_selectors' ) } help={ __( 'One CSS selector per line, shown as an empty box. Or add the class qcin-block.', 'qcin-textdomain' ) } />
				</div>
			</Section>

			<Section title={ __( 'Who and what to skip', 'qcin-textdomain' ) }>
				<fieldset className="qcin-field">
					<legend>{ __( 'Do not record logged-in users with these roles', 'qcin-textdomain' ) }</legend>
					<div className="qcin-checks">
						{ ( boot.roles || [] ).map( ( r ) => (
							<label key={ r.value } className="qcin-check">
								<input type="checkbox" checked={ ( form.exclude_roles || [] ).indexOf( r.value ) !== -1 } onChange={ ( e ) => toggleRole( r.value, e.target.checked ) } />
								<span>{ r.label }</span>
							</label>
						) ) }
					</div>
				</fieldset>
				<TextArea id="qcin-exurls" label={ __( 'Do not record these pages', 'qcin-textdomain' ) } value={ form.exclude_urls } placeholder="/checkout/*&#10;/my-account/*" onChange={ set( 'exclude_urls' ) } help={ __( 'One path per line. Use * as a wildcard.', 'qcin-textdomain' ) } />
			</Section>

			<Section title={ __( 'Storage', 'qcin-textdomain' ) } desc={ __( 'Recordings are stored as compressed files on this server.', 'qcin-textdomain' ) }>
				<div className="qcin-grid-2">
					<NumberField id="qcin-ret" label={ __( 'Keep recordings for', 'qcin-textdomain' ) } value={ form.retention_days } min="1" suffix={ __( 'days', 'qcin-textdomain' ) } onChange={ set( 'retention_days' ) } help={ __( 'Saved recordings are kept until you remove them.', 'qcin-textdomain' ) } />
					<NumberField id="qcin-storecap" label={ __( 'Storage limit', 'qcin-textdomain' ) } value={ form.storage_cap_mb } min="50" suffix="MB" onChange={ set( 'storage_cap_mb' ) } help={ __( 'The oldest recordings are deleted when the limit is reached.', 'qcin-textdomain' ) } />
				</div>
				{ status && (
					<div className="qcin-usage">
						<div className="qcin-usage__bar">
							<span style={ { width: usedPct + '%' } } />
						</div>
						<div className="qcin-muted">
							{ sprintf(
								/* translators: 1: used, 2: limit */
								__( '%1$s used of %2$s', 'qcin-textdomain' ),
								bytes( status.bytes ),
								bytes( status.capBytes )
							) }
						</div>
					</div>
				) }
				{ health && (
					<ul className="qcin-health">
						<li className={ store.writable ? 'is-ok' : 'is-bad' }>{ store.writable ? __( 'Storage folder is writable', 'qcin-textdomain' ) : __( 'Storage folder is not writable', 'qcin-textdomain' ) }</li>
						<li className={ store.protected === false ? 'is-bad' : store.protected ? 'is-ok' : 'is-unknown' }>
							{ store.protected === false
								? __( 'Recordings can be opened from the web. Ask your host to block access to the folder below (on nginx, add a "deny all" rule).', 'qcin-textdomain' )
								: store.protected
									? __( 'Recordings cannot be opened from the web', 'qcin-textdomain' )
									: __( 'Could not test whether the storage folder is blocked from the web', 'qcin-textdomain' ) }
						</li>
						<li className={ health.jobs_overdue ? 'is-bad' : 'is-ok' }>
							{ health.jobs_overdue
								? __( 'Background jobs are not running, so sessions are not closed and old data is not cleaned up. WP-Cron runs only when the site gets visits; ask your host to set up a real cron job.', 'qcin-textdomain' )
								: lastRun
									? sprintf(
										/* translators: %s: relative time, e.g. "5 min ago" */
										__( 'Background jobs are running (last run %s)', 'qcin-textdomain' ),
										relative( lastRun )
									)
									: __( 'Background jobs are scheduled', 'qcin-textdomain' ) }
						</li>
						{ health.cron_disabled && <li className="is-info">{ __( 'WP-Cron is disabled in wp-config.php (DISABLE_WP_CRON). Make sure a server cron job calls wp-cron.php every few minutes.', 'qcin-textdomain' ) }</li> }
						<li className={ health.ingest_errors > 50 ? 'is-bad' : 'is-ok' }>
							{ sprintf(
								/* translators: %s: number of requests */
								__( 'Rejected tracking requests in the last 24 hours: %s', 'qcin-textdomain' ),
								( health.ingest_errors || 0 ).toLocaleString()
							) }
						</li>
						{ health.heatmap && (
							<li className="is-ok">
								{ sprintf(
									/* translators: 1: number of clicks, 2: number of clicks waiting */
									__( 'Raw clicks kept (7 days): %1$s, waiting for the hourly heatmap job: %2$s', 'qcin-textdomain' ),
									health.heatmap.raw_clicks.toLocaleString(),
									health.heatmap.pending.toLocaleString()
								) }
							</li>
						) }
						<li className="is-info">
							<code>{ store.path }</code>
						</li>
					</ul>
				) }
				<Toggle id="qcin-uninstall" checked={ form.delete_on_uninstall } onChange={ set( 'delete_on_uninstall' ) } label={ sprintf(
					/* translators: %s: menu title, e.g. "Site Insight". */
					__( 'Delete all %s data when the plugin is deleted', 'qcin-textdomain' ),
					boot.title || __( 'Insights', 'qcin-textdomain' )
				) } />
				<button type="button" className="qcin-btn qcin-btn--danger qcin-btn--small" onClick={ deleteAll }>
					{ __( 'Delete all data now', 'qcin-textdomain' ) }
				</button>
			</Section>

			<AiSection data={ aiData } form={ aiForm } setForm={ setAiForm } onData={ onAiData } notify={ notify } />

			{ boot.reports && <ReportsSection data={ repData } form={ repForm } setForm={ setRepForm } dirty={ repDirty } /> }

			{ extList( 'qcin.admin.settingsSections', null, hasRender ).map( ( sec ) => (
				<ExtPart key={ sec.id } part={ sec.render } props={ { settings, save, notify, ui: window.qcInsightsUI } } />
			) ) }

			<div className={ 'qcin-savebar' + ( dirty ? ' is-dirty' : '' ) }>
				<span className="qcin-muted">{ dirty ? __( 'You have unsaved changes.', 'qcin-textdomain' ) : __( 'All changes saved.', 'qcin-textdomain' ) }</span>
				<button type="submit" className="qcin-btn qcin-btn--primary" disabled={ ! dirty || saving }>
					{ saving ? __( 'Saving…', 'qcin-textdomain' ) : __( 'Save settings', 'qcin-textdomain' ) }
				</button>
			</div>
		</form>
	);
}
