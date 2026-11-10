/* global wp */
import { boot } from './api';
import { Icon } from './icons';

const { createElement, useState } = wp.element;
const { __, sprintf } = wp.i18n;

export function Onboarding( { save } ) {
	const [ busy, setBusy ] = useState( false );
	const turnOn = () => {
		setBusy( true );
		save( { enabled: true } ).finally( () => setBusy( false ) );
	};
	return (
		<div className="qcin-card qcin-onboarding">
			<div className="qcin-onboarding__icon">
				<Icon.recordings size={ 34 } />
			</div>
			<h2>{ __( 'See how visitors use your site', 'qcin-textdomain' ) }</h2>
			<p className="qcin-lead">
				{ sprintf(
					/* translators: %s: menu title, e.g. "Site Insight". */
					__( '%s records visitor sessions so you can replay clicks, scrolling and page visits, shows click and scroll heatmaps, and points out where visitors get frustrated. Everything is stored on your own server; nothing is sent to an analytics service.', 'qcin-textdomain' ),
					boot.title || __( 'Insights', 'qcin-textdomain' )
				) }
			</p>
			<ul className="qcin-checklist">
				<li>
					<Icon.shield size={ 16 } />
					{ __( 'Everything typed into forms is hidden before it is saved.', 'qcin-textdomain' ) }
				</li>
				<li>
					<Icon.shield size={ 16 } />
					{ __( 'No IP addresses, names or emails are stored.', 'qcin-textdomain' ) }
				</li>
				<li>
					<Icon.shield size={ 16 } />
					{ boot.consentApi
						? __( 'Your consent plugin decides who is recorded (WP Consent API detected).', 'qcin-textdomain' )
						: __( 'Works with consent plugins that support the WP Consent API.', 'qcin-textdomain' ) }
				</li>
				<li>
					<Icon.shield size={ 16 } />
					{ sprintf(
						/* translators: 1: percent of sessions, 2: days kept */
						__( 'Starts by recording %1$d%% of sessions and keeps them for %2$d days. You can change this in Settings.', 'qcin-textdomain' ),
						boot.settings.sample_rate,
						boot.settings.retention_days
					) }
				</li>
			</ul>
			<div className="qcin-actions">
				<button type="button" className="qcin-btn qcin-btn--primary" onClick={ turnOn } disabled={ busy }>
					{ busy ? __( 'Turning on…', 'qcin-textdomain' ) : __( 'Turn on recording', 'qcin-textdomain' ) }
				</button>
				<a className="qcin-btn qcin-btn--ghost" href="#/settings">
					{ __( 'Review settings first', 'qcin-textdomain' ) }
				</a>
			</div>
			<p className="qcin-hint">
				{ __( 'Remember to mention session recording in your privacy policy.', 'qcin-textdomain' ) }{ ' ' }
				<a href={ boot.privacyUrl }>{ __( 'See suggested text', 'qcin-textdomain' ) }</a>
			</p>
		</div>
	);
}
