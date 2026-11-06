/**
 * Free only: hide every inactive "Pro" preview (menu pages and Pro badges).
 */
import { Notice, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';

const ProPreviewSettings = () => {
	const [ hidden, setHidden ] = useState(
		!! data.settings.hide_pro_previews
	);
	const [ message, setMessage ] = useState( '' );
	const change = ( value ) => {
		setHidden( value );
		writer( 'settings', {
			method: 'POST',
			data: { settings: { hide_pro_previews: value } },
		} )
			.then( ( next ) => {
				data.settings = next;
				setMessage(
					__(
						'Saved. Reload the page to update the menu and screens.',
						'chatbot'
					)
				);
			} )
			.catch( ( err ) => setMessage( errorMessage( err ) ) );
	};
	return (
		<div className="qcwr-card">
			<h2>{ __( 'Pro previews', 'chatbot' ) }</h2>
			{ message && (
				<Notice status="info" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			<ToggleControl
				label={ __(
					'Hide Pro previews (the "Pro" menu items and badges)',
					'chatbot'
				) }
				checked={ hidden }
				onChange={ change }
				__nextHasNoMarginBottom
			/>
		</div>
	);
};

export default ProPreviewSettings;
