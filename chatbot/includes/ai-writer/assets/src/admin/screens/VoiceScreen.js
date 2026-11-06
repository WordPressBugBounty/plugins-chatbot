/**
 * Brand Voice: the Voices list (CR-12; AI Writer Pro replaces it through
 * `qcld_wpbot_writer.voice.list`), the site's voice profile and the style
 * guide (words to use and avoid, product names, reading level, clichés).
 */
import { applyFilters } from '@wordpress/hooks';
import { data } from '../api';
import VoiceList from '../components/VoiceList';
import VoiceSettings from '../components/VoiceSettings';
import StyleGuideSettings from '../components/StyleGuideSettings';
import { ProSpots } from '../components/pro/ProPreview';

const VoiceScreen = () => (
	<div className="qcwr-settings">
		{ applyFilters( 'qcld_wpbot_writer.voice.list', <VoiceList /> ) }
		<VoiceSettings />
		<ProSpots spots={ [ 'voice.learning', 'voice.site_setup' ] } />
		{ data.can.settings && <StyleGuideSettings /> }
		{ applyFilters( 'qcld_wpbot_writer.voice.panels', null ) }
	</div>
);

export default VoiceScreen;
