/**
 * An inactive "Pro" menu page (WPBot Free only): the preview card the host
 * gave for it, full size. Nothing behind it runs.
 */
import { __, sprintf } from '@wordpress/i18n';
import { data } from '../api';
import { ProCardBody } from '../components/pro/ProPreview';

const PreviewScreen = ( { id, label } ) => {
	const preview = ( data.proPreviews || [] ).find(
		( item ) => item && 'preview-' + item.id === id
	) || { label, text: '', benefits: [], features: [] };
	return (
		<div className="qcwr-card qcwr-pro-page">
			<h2>
				{ preview.label }
				<span className="qcwr-badge qcwr-badge--pro">
					{ __( 'Pro', 'chatbot' ) }
				</span>
			</h2>
			{ ! preview.text && (
				<p>
					{ sprintf(
						/* translators: %s: feature name. */
						__( '%s is part of WPBot Pro.', 'chatbot' ),
						preview.label
					) }
				</p>
			) }
			<ProCardBody preview={ preview } />
		</div>
	);
};

export default PreviewScreen;
