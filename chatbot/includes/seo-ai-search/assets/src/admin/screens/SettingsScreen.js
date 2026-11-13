/**
 * Settings: connections, AI keys, data and general options, in tabs.
 * Extensions add tabs through qcld_wpbot_seo.admin.settingsSections.
 */
import {
	Button,
	CheckboxControl,
	RangeControl,
	SelectControl,
	TabPanel,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo, product } from '../api';
import { takeIntent } from '../nav';
import AiSettings from '../components/AiSettings';
import { CompetitorSettings } from '../components/CompetitorPicker';
import GoogleAdsSlot from '../components/GoogleAdsSlot';
import GoogleSettings from '../components/GoogleSettings';
import ServiceKeys from '../components/ServiceKeys';
import KeywordData from '../components/KeywordData';

/**
 * Settings form state with one save button per tab.
 *
 * @return {Object} settings, change, save, busy, message.
 */
const useSettings = () => {
	const [ settings, setSettings ] = useState( data.settings || {} );
	const [ changes, setChanges ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	const change = ( next ) => {
		setSettings( { ...settings, ...next } );
		setChanges( { ...changes, ...next } );
		setMessage( '' );
	};
	const save = ( now = null ) => {
		setBusy( true );
		return seo( 'settings', {
			method: 'POST',
			data: { settings: now || changes },
		} )
			.then( ( saved ) => {
				setSettings( saved );
				data.settings = saved;
				setChanges( {} );
				setMessage( __( 'Saved.', 'chatbot' ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return {
		settings,
		change,
		save,
		busy,
		message,
		dirty: Object.keys( changes ).length > 0,
	};
};

/**
 * A list setting edited as text, one item per line. The text stays exactly as
 * typed (blank lines included, so a new line can be started); the setting
 * gets the clean list. Before, the setting itself held the typed text, and
 * the next render tried to `.join()` a string, which crashed the screen.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.value    Saved list.
 * @param {Function} props.onChange Receives the clean list.
 * @param {Function} props.parse    Optional: item text => value (for example Number).
 * @return {Element} Field.
 */
const ListField = ( { value, onChange, parse, ...rest } ) => {
	const [ text, setText ] = useState( () =>
		( Array.isArray( value ) ? value : [] ).join( '\n' )
	);
	return (
		<TextareaControl
			{ ...rest }
			value={ text }
			onChange={ ( next ) => {
				setText( next );
				const items = next
					.split( /[\n,]+/ )
					.map( ( item ) => item.trim() )
					.filter( Boolean );
				onChange( parse ? items.map( parse ) : items );
			} }
			__nextHasNoMarginBottom
		/>
	);
};

const SaveRow = ( { form } ) => (
	<div className="qcseo-actions">
		<Button
			variant="primary"
			isBusy={ form.busy }
			disabled={ form.busy || ! form.dirty }
			onClick={ () => form.save() }
		>
			{ __( 'Save settings', 'chatbot' ) }
		</Button>
		{ form.message && (
			<span className="qcseo-muted">{ form.message }</span>
		) }
	</div>
);

const DataTab = ( { form } ) => (
	<div className="qcseo-card qcseo-form">
		<h3>{ __( 'Search data', 'chatbot' ) }</h3>
		<RangeControl
			label={ __(
				'Keep daily search data for (months)',
				'chatbot'
			) }
			help={ __(
				'Search Console keeps 16 months. Older rows are removed every night.',
				'chatbot'
			) }
			min={ 3 }
			max={ 16 }
			value={ Math.min( 16, Number( form.settings.storage_months ) ) }
			onChange={ ( value ) => form.change( { storage_months: value } ) }
			__nextHasNoMarginBottom
		/>
		<RangeControl
			label={ __(
				'Keep Page Audit history for (months)',
				'chatbot'
			) }
			help={ __(
				'Every audit is kept with its keyword and score, so you can follow a page over time.',
				'chatbot'
			) }
			min={ 1 }
			max={ 60 }
			value={ Number( form.settings.audit_history_months || 12 ) }
			onChange={ ( value ) =>
				form.change( { audit_history_months: value } )
			}
			__nextHasNoMarginBottom
		/>
		<h3>{ __( 'Scans', 'chatbot' ) }</h3>
		<RangeControl
			label={ __( 'Pages per scan step', 'chatbot' ) }
			help={ __(
				'Lower this if your host is slow; scans take more steps but each one is shorter.',
				'chatbot'
			) }
			min={ 1 }
			max={ 50 }
			value={ Number( form.settings.scan_batch ) }
			onChange={ ( value ) => form.change( { scan_batch: value } ) }
			__nextHasNoMarginBottom
		/>
		<ListField
			label={ __(
				'Paths to leave out of scans (one per line, * as wildcard)',
				'chatbot'
			) }
			value={ form.settings.excluded_paths }
			onChange={ ( list ) => form.change( { excluded_paths: list } ) }
		/>
		<h3>{ __( 'Keyword research', 'chatbot' ) }</h3>
		<TextControl
			type="number"
			min={ 0 }
			step={ 0.05 }
			label={ __(
				'Ask me before a research run that costs more than (USD)',
				'chatbot'
			) }
			help={ __(
				'Runs below this start straight away. Set 0 to be asked every time.',
				'chatbot'
			) }
			value={ form.settings.research_confirm_above }
			onChange={ ( value ) =>
				form.change( { research_confirm_above: value } )
			}
			__nextHasNoMarginBottom
		/>
		<SaveRow form={ form } />
	</div>
);

const EmailTab = ( { form } ) => (
	<div className="qcseo-card qcseo-form">
		<h3>{ __( 'Weekly email summary', 'chatbot' ) }</h3>
		<CheckboxControl
			label={ __(
				'Send a weekly summary when there is news',
				'chatbot'
			) }
			checked={ !! form.settings.email_enabled }
			onChange={ ( value ) => form.change( { email_enabled: value } ) }
			__nextHasNoMarginBottom
		/>
		<ListField
			label={ __(
				'Send to (one address per line; empty = the site admin)',
				'chatbot'
			) }
			rows={ 3 }
			value={ form.settings.email_recipients }
			onChange={ ( list ) => form.change( { email_recipients: list } ) }
		/>
		<SelectControl
			__next40pxDefaultSize
			label={ __( 'Day', 'chatbot' ) }
			value={ form.settings.email_day }
			options={ [
				'monday',
				'tuesday',
				'wednesday',
				'thursday',
				'friday',
				'saturday',
				'sunday',
			].map( ( day ) => ( {
				value: day,
				label: day.charAt( 0 ).toUpperCase() + day.slice( 1 ),
			} ) ) }
			onChange={ ( value ) => form.change( { email_day: value } ) }
			__nextHasNoMarginBottom
		/>
		<CheckboxControl
			label={ __( 'Hide the footer line', 'chatbot' ) }
			help={ sprintf(
				/* translators: %s: product name, for example WPBot. */
				__(
					'The short line from %s at the bottom of each email.',
					'chatbot'
				),
				product
			) }
			checked={ !! form.settings.hide_email_footer }
			onChange={ ( value ) =>
				form.change( { hide_email_footer: value } )
			}
			__nextHasNoMarginBottom
		/>
		<h3>{ __( 'Results', 'chatbot' ) }</h3>
		<ListField
			label={ __(
				'Measure results after (days, one per line)',
				'chatbot'
			) }
			help={ __(
				'After each change you log (a fix, a new page), its clicks, position and conversions are compared with the same number of days before it, at each of these days.',
				'chatbot'
			) }
			rows={ 3 }
			value={ form.settings.results_days }
			parse={ Number }
			onChange={ ( list ) => form.change( { results_days: list } ) }
		/>
		<SaveRow form={ form } />
	</div>
);

const GeneralTab = ( { form } ) => (
	<div className="qcseo-card qcseo-form">
		<h3>{ __( 'General', 'chatbot' ) }</h3>
		{ ( data.previews || [] ).length > 0 && (
			<CheckboxControl
				label={ __(
					'Hide Pro previews in the menu',
					'chatbot'
				) }
				checked={ !! form.settings.hide_pro_previews }
				onChange={ ( value ) =>
					form.change( { hide_pro_previews: value } )
				}
				__nextHasNoMarginBottom
			/>
		) }
		<CheckboxControl
			label={ __(
				"Delete this module's data when the plugin is deleted",
				'chatbot'
			) }
			help={ __(
				"Search data, scans, logs and settings. Your posts and your SEO plugin's fields are never touched.",
				'chatbot'
			) }
			checked={ !! form.settings.delete_on_uninstall }
			onChange={ ( value ) =>
				form.change( { delete_on_uninstall: value } )
			}
			__nextHasNoMarginBottom
		/>
		<p className="qcseo-muted">
			<a href={ `${ data.adminUrl }admin.php?page=wpbot-permissions` }>
				{ sprintf(
					/* translators: %s: product name, for example WPBot. */
					__(
						'Who can use SEO & AI Search: %s > Permissions',
						'chatbot'
					),
					product
				) }
			</a>
		</p>
		<SaveRow form={ form } />
	</div>
);

/**
 * A section other screens can link to (navigate intent `anchor`, or #id):
 * scrolled into view once it is on screen.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.id       Anchor id.
 * @param {boolean} props.target   Scroll here.
 * @param {Element} props.children Content.
 * @return {Element} Section.
 */
const Anchor = ( { id, target, children } ) => {
	const ref = useRef( null );
	useEffect( () => {
		if ( target && ref.current ) {
			// After the cards inside have loaded.
			const timer = setTimeout(
				() =>
					ref.current &&
					ref.current.scrollIntoView( { block: 'start' } ),
				600
			);
			return () => clearTimeout( timer );
		}
		return undefined;
	}, [ target ] );
	return (
		<div id={ id } ref={ ref }>
			{ children }
		</div>
	);
};

const SettingsScreen = ( { setHealth } ) => {
	const form = useSettings();
	const [ intent ] = useState( () => {
		const taken = takeIntent();
		if ( taken ) {
			return taken;
		}
		// Links from the shared AI notice: &section=ai (and #provider-{id}).
		const section = new URLSearchParams( window.location.search ).get(
			'section'
		);
		const hash = window.location.hash.slice( 1 );
		return section ? { tab: section, anchor: hash } : {};
	} );

	if ( ! data.can.settings ) {
		return (
			<p>
				{ __(
					'Ask an administrator for the "Manage settings and keys" permission.',
					'chatbot'
				) }
			</p>
		);
	}

	const tabs = applyFilters(
		'qcld_wpbot_seo.admin.settingsSections',
		[
			{
				name: 'connections',
				title: __( 'Connections', 'chatbot' ),
				render: () =>
					data.can.keys ? (
						<>
							<GoogleSettings
								settings={ form.settings }
								onSettings={ ( next ) => {
									form.change( next );
									form.save( next );
								} }
							/>
							<Anchor
								id="google-ads"
								target={ 'google-ads' === intent.anchor }
							>
								<GoogleAdsSlot settings={ form.settings } />
							</Anchor>
							<ServiceKeys />
							<KeywordData />
						</>
					) : (
						<p>
							{ __(
								'Only administrators can change connections and keys.',
								'chatbot'
							) }
						</p>
					),
			},
			{
				name: 'ai',
				title: __( 'AI', 'chatbot' ),
				render: () =>
					data.can.keys ? (
						<AiSettings onHealth={ setHealth } />
					) : (
						<p>
							{ __(
								'Only administrators can change AI keys.',
								'chatbot'
							) }
						</p>
					),
			},
			{
				name: 'data',
				title: __( 'Data & scans', 'chatbot' ),
				render: () => <DataTab form={ form } />,
			},
			{
				name: 'competitors',
				title: __( 'Competitors', 'chatbot' ),
				render: () => <CompetitorSettings />,
			},
			{
				name: 'email',
				title: __( 'Email & results', 'chatbot' ),
				render: () => <EmailTab form={ form } />,
			},
			{
				name: 'general',
				title: __( 'General', 'chatbot' ),
				render: () => <GeneralTab form={ form } />,
			},
		],
		form
	);

	return (
		<div className="qcseo-settings">
			<h2 className="qcseo-screen-title">
				{ __( 'Settings & AI Provider', 'chatbot' ) }
			</h2>
			<TabPanel
				className="qcseo-tabs"
				initialTabName={ intent.tab || 'connections' }
				tabs={ tabs.map( ( tab ) => ( {
					name: tab.name,
					title: tab.title,
				} ) ) }
			>
				{ ( tab ) => {
					const found = tabs.find(
						( entry ) => entry.name === tab.name
					);
					return found ? found.render() : null;
				} }
			</TabPanel>
		</div>
	);
};

export default SettingsScreen;
