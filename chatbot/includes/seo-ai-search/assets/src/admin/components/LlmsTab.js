/**
 * Technical SEO > llms.txt (round 2, R4): turn it on, see exactly what is
 * listed and what was left out (and why), check the live file, and set the
 * summary, notes, most links, post types and pinned or kept-out pages. Pro
 * adds AI descriptions, the daily link check and Markdown copies below.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../api';

const CHECK = {
	match: 'success',
	stale_cache: 'warning',
	other_source: 'warning',
	missing: 'error',
	error: 'error',
};

const LlmsTab = () => {
	const [ view, setView ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( null );

	const show = ( r ) => {
		setView( r );
		setForm( {
			...r.settings,
			llms_pinned: ( r.settings.llms_pinned || [] ).join( ', ' ),
			llms_excluded: ( r.settings.llms_excluded || [] ).join( ', ' ),
		} );
	};
	const load = () =>
		seo( 'llms' )
			.then( show )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	useEffect( () => {
		load();
	}, [] );
	if ( ! view || ! form ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}
	const call = ( what, path, payload ) => {
		setBusy( what );
		setMessage( null );
		return seo( path, { method: 'POST', data: payload } )
			.then( ( r ) => {
				if ( 'check' === what ) {
					setView( { ...view, check: r } );
				} else {
					show( r );
				}
				if ( 'save' === what ) {
					setMessage( {
						status: 'success',
						text: __( 'Saved.', 'chatbot' ),
					} );
				}
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( '' ) );
	};
	const save = ( changes ) =>
		call( 'save', 'llms/settings', { ...form, ...changes } );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );

	return (
		<div className="qcseo-llms">
			<div className="qcseo-card">
				<h3>{ __( 'llms.txt', 'chatbot' ) }</h3>
				<p className="qcseo-muted">
					{ __(
						'A short, always-current index of your public pages for AI tools, at /llms.txt. WordPress builds it live from your content: nothing is saved on your server, so deleted pages drop out at once. Drafts, private, password-protected, noindexed, members-only and almost empty pages are never listed. llms.txt is a proposed standard: Google says it does not use it and no AI search engine has confirmed reading it, so treat it as a clean extra, not a ranking boost.',
						'chatbot'
					) }
				</p>
				{ message && (
					<Notice
						status={ message.status }
						onRemove={ () => setMessage( null ) }
					>
						{ message.text }
					</Notice>
				) }
				{ view.conflict.map( ( c ) => (
					<Notice
						key={ c.id }
						status="warning"
						isDismissible={ false }
					>
						{ c.message }
					</Notice>
				) ) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Serve llms.txt', 'chatbot' ) }
					checked={ !! view.enabled }
					disabled={ ! view.can_save || !! busy }
					onChange={ ( on ) => save( { llms_enabled: on } ) }
				/>
				{ view.enabled && (
					<>
						<p>
							<a
								href={ view.url }
								target="_blank"
								rel="noreferrer noopener"
							>
								{ view.url }
							</a>{ ' ' }
							<span className="qcseo-muted">
								{ sprintf(
									/* translators: 1: links listed, 2: date. */
									_n(
										'%1$d page listed. Built %2$s.',
										'%1$d pages listed. Built %2$s.',
										view.count,
										'chatbot'
									),
									view.count,
									( view.built_at || '' )
										.replace( 'T', ' ' )
										.slice( 0, 16 )
								) }
							</span>
						</p>
						<div className="qcseo-actions">
							<Button
								variant="secondary"
								isBusy={ 'check' === busy }
								disabled={ !! busy }
								onClick={ () => call( 'check', 'llms/check' ) }
							>
								{ __( 'Check live file', 'chatbot' ) }
							</Button>
							<Button
								variant="tertiary"
								isBusy={ 'rebuild' === busy }
								disabled={ !! busy }
								onClick={ () =>
									call( 'rebuild', 'llms/rebuild' )
								}
							>
								{ __( 'Rebuild now', 'chatbot' ) }
							</Button>
						</div>
						{ view.check && view.check.status && (
							<Notice
								status={ CHECK[ view.check.status ] || 'info' }
								isDismissible={ false }
							>
								{ view.check.message }{ ' ' }
								<span className="qcseo-muted">
									{ view.check.checked_at
										.replace( 'T', ' ' )
										.slice( 0, 16 ) }
								</span>
							</Notice>
						) }
						<details className="qcseo-llms-preview" open>
							<summary>
								{ __(
									'What AI tools get',
									'chatbot'
								) }
							</summary>
							<pre>{ view.text }</pre>
						</details>
						{ view.left_out && view.left_out.length > 0 && (
							<details className="qcseo-llms-left-out">
								<summary>
									{ sprintf(
										/* translators: %d: pages left out. */
										_n(
											'%d page left out, and why',
											'%d pages left out, and why',
											view.left_out.length,
											'chatbot'
										),
										view.left_out.length
									) }
								</summary>
								<table className="widefat striped qcseo-table">
									<tbody>
										{ view.left_out.map( ( row ) => (
											<tr key={ row.post_id }>
												<td>{ row.title }</td>
												<td>{ row.why }</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</details>
						) }
					</>
				) }
			</div>
			{ view.enabled && view.can_save && (
				<div className="qcseo-card">
					<h3>{ __( 'Settings', 'chatbot' ) }</h3>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Summary (one sentence about your site)',
							'chatbot'
						) }
						help={ __(
							'Empty: your tagline, else your business from Local SEO.',
							'chatbot'
						) }
						value={ form.llms_summary }
						onChange={ set( 'llms_summary' ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'Notes for AI tools (optional, no headings)',
							'chatbot'
						) }
						value={ form.llms_details }
						onChange={ set( 'llms_details' ) }
						rows={ 3 }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 5 }
						max={ 1000 }
						label={ __( 'Most pages listed', 'chatbot' ) }
						value={ form.llms_max }
						onChange={ set( 'llms_max' ) }
					/>
					<fieldset>
						<legend>
							{ __( 'List these kinds', 'chatbot' ) }
						</legend>
						{ view.types.map( ( t ) => (
							<CheckboxControl
								key={ t.id }
								__nextHasNoMarginBottom
								label={ t.label }
								checked={ ( form.llms_types || [] ).includes(
									t.id
								) }
								onChange={ ( on ) =>
									setForm( {
										...form,
										llms_types: on
											? [
													...( form.llms_types ||
														[] ),
													t.id,
												]
											: ( form.llms_types || [] ).filter(
													( x ) => x !== t.id
												),
									} )
								}
							/>
						) ) }
					</fieldset>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Always list these pages (post IDs, comma-separated)',
							'chatbot'
						) }
						help={ __(
							'They still have to pass every rule above.',
							'chatbot'
						) }
						value={ form.llms_pinned }
						onChange={ set( 'llms_pinned' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Never list these pages (post IDs, comma-separated)',
							'chatbot'
						) }
						value={ form.llms_excluded }
						onChange={ set( 'llms_excluded' ) }
					/>
					<Button
						variant="primary"
						isBusy={ 'save' === busy }
						disabled={ !! busy }
						onClick={ () => save( {} ) }
					>
						{ __( 'Save', 'chatbot' ) }
					</Button>
				</div>
			) }
			{ applyFilters( 'qcld_wpbot_seo.llms.after', null, {
				view,
				reload: load,
				canSave: !! view.can_save && !! data.can.settings,
			} ) }
		</div>
	);
};

export default LlmsTab;
