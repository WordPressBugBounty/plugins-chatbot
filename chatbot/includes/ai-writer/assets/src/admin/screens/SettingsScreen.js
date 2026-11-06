/**
 * Settings: AI keys (administrators), writing defaults, images, Topic
 * Finder, connections (administrators), Pro items in the menu (Free),
 * diagnostics. Brand voice and the style guide have their own menu item.
 */
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import AiSettings from '../components/AiSettings';
import WritingSettings from '../components/WritingSettings';
import Diagnostics from '../components/Diagnostics';
import ImageSettings from '../components/ImageSettings';
import TopicSettings from '../components/TopicSettings';
import ConnectionsSettings from '../components/ConnectionsSettings';
import ProPreviewSettings from '../components/ProPreviewSettings';

const SettingsScreen = ( { setHealth } ) => (
	<div className="qcwr-settings">
		{ data.can.keys ? (
			<AiSettings onHealth={ setHealth } />
		) : (
			<div className="qcwr-card">
				<p className="qcwr-muted">
					{ __(
						'Only administrators can change AI keys.',
						'chatbot'
					) }
				</p>
			</div>
		) }
		<WritingSettings />
		<ImageSettings />
		{ data.can.settings && <TopicSettings /> }
		{ data.can.keys && <ConnectionsSettings /> }
		{ data.can.settings && data.proOffered && <ProPreviewSettings /> }
		{ data.can.use && <Diagnostics /> }
	</div>
);

export default SettingsScreen;
