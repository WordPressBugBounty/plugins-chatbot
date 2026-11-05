/**
 * A labelled, inactive preview of a Pro feature (Free hosts only): what it
 * is, the list of what Pro adds, and an optional link from the host. No Pro
 * code runs behind it; the owner can hide previews in Settings.
 */
import { __ } from '@wordpress/i18n';
import { data } from '../api';

const ProPreview = ( { preview, title, as: Heading = 'h2' } ) => {
	const features = preview.features || [];
	return (
		<div className="qcseo-card qcseo-preview">
			<span className="qcseo-pro-pill">
				{ __( 'Pro', 'chatbot' ) }
			</span>
			<Heading>{ title || preview.label }</Heading>
			{ preview.text && (
				<p className="qcseo-preview__lead">{ preview.text }</p>
			) }
			{ features.length > 0 && (
				<ul className="qcseo-preview__features">
					{ features.map( ( feature, index ) => (
						<li key={ index }>{ feature }</li>
					) ) }
				</ul>
			) }
			{ preview.url && (
				<p>
					<a
						className="components-button is-primary"
						href={ preview.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ preview.button ||
							__( 'Learn more', 'chatbot' ) }
					</a>
				</p>
			) }
		</div>
	);
};

/**
 * Add the host's Pro preview tabs for one section (Free hosts only): each
 * preview whose `tab` is "section:name" becomes an inactive tab, marked Pro,
 * that shows the preview card. A tab with that name that an extension already
 * added (WPBot Pro) is never duplicated. Hidden previews send no data, so
 * nothing is added then.
 *
 * @param {string} section Section id, e.g. "keywords".
 * @param {Array}  tabs    Tabs after the section's filter.
 * @return {Array} Tabs with the preview tabs appended.
 */
export const withPreviewTabs = ( section, tabs ) => {
	const names = tabs.map( ( t ) => t.name );
	const extra = ( data.previews || [] )
		.filter( ( p ) => p.tab && p.tab.section === section )
		.filter( ( p ) => ! names.includes( p.tab.name ) )
		.map( ( p ) => ( {
			name: p.tab.name,
			// The tab name with a "Pro" pill (the tab button's ::before and
			// ::after are taken by WordPress for focus and the active line).
			title: (
				<>
					{ p.label }
					<span className="qcseo-pro-pill">
						{ __( 'Pro', 'chatbot' ) }
					</span>
				</>
			),
			className: 'qcseo-tab-pro',
			render: () => <ProPreview preview={ p } as="h3" />,
		} ) );
	return [ ...tabs, ...extra ];
};

export default ProPreview;
