/**
 * Inactive, labelled preview of a Pro feature (Free hosts only). No Pro
 * code runs behind it; the owner can hide previews in Settings.
 */
import { data } from '../api';
import ProPreview from '../components/ProPreview';

const PreviewScreen = ( { id, label } ) => {
	const preview =
		( data.previews || [] ).find( ( item ) => item.id === id ) || {};
	return <ProPreview preview={ preview } title={ label } />;
};

export default PreviewScreen;
