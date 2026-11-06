/**
 * The Voices list at the top of Brand Voice (CR-12). Free has one voice,
 * the site voice, which is the default. AI Writer Pro replaces this list
 * (filter `qcld_wpbot_writer.voice.list`) with its own: several voices,
 * each with Edit, Duplicate, Make default and Delete. In Free, "Add a
 * voice" shows as a labelled, inactive Pro item, only when the host offers
 * Pro previews and the owner has not hidden them.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import { ProSpot } from './pro/ProPreview';

/**
 * Free: "Add a voice" as a labelled Pro preview (host text).
 *
 * @return {Element|null} Preview.
 */
const AddVoicePreview = () => <ProSpot spot="voice.add" />;

const VoiceList = () => (
	<section className="qcwr-card qcwr-voices">
		<div className="qcwr-voices__head">
			<h2>{ __( 'Voices', 'chatbot' ) }</h2>
			<AddVoicePreview />
		</div>
		<ul className="qcwr-voices__list">
			<li>
				<strong>{ __( 'Site voice', 'chatbot' ) }</strong>
				<span className="qcwr-badge">
					{ __( 'Default', 'chatbot' ) }
				</span>
				<span className="qcwr-muted">
					{ ' ' }
					{ __(
						'Used for everything AI Writer writes.',
						'chatbot'
					) }
				</span>{ ' ' }
				<Button variant="link" href="#qcwr-site-voice">
					{ __( 'Edit', 'chatbot' ) }
				</Button>
			</li>
		</ul>
	</section>
);

export default VoiceList;
