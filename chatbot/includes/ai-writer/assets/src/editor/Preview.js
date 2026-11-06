/**
 * Preview of an AI result before anything changes in the post. The owner
 * picks an action (Replace, Insert below…), regenerates it (the same
 * options, or changed ones, CR-14), flips between versions, or discards it.
 */
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RegenerateControl, {
	VersionPager,
} from '../admin/components/regenerate/Regenerate';

const Preview = ( {
	title,
	html,
	text,
	busy,
	error,
	actions = [],
	onTryAgain,
	onDiscard,
	children,
	regenerateKind = 'transform',
	regenerateHide = [],
	versions = [],
	versionIndex = 0,
	onVersion,
} ) => (
	<Modal
		title={ title }
		onRequestClose={ onDiscard }
		className="qcwr-preview"
		shouldCloseOnClickOutside={ false }
		size="medium"
	>
		{ error && (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) }
		{ onVersion && (
			<VersionPager
				versions={ versions }
				index={ versionIndex }
				onChange={ onVersion }
			/>
		) }
		{ busy && (
			<p className="qcwr-preview__busy">
				<Spinner /> { __( 'Writing…', 'chatbot' ) }
			</p>
		) }
		{ ! busy && html && (
			// The server sanitises this HTML (kses) before sending it.
			<RawHTML className="qcwr-preview__body">{ html }</RawHTML>
		) }
		{ ! busy && text && (
			<div className="qcwr-preview__body qcwr-preview__text">
				{ text }
			</div>
		) }
		{ children }
		<div className="qcwr-preview__actions">
			{ actions.map( ( action, index ) => (
				<Button
					key={ action.label }
					variant={ 0 === index ? 'primary' : 'secondary' }
					disabled={
						!! busy || ( ! html && ! text && ! action.always )
					}
					onClick={ action.onClick }
				>
					{ action.label }
				</Button>
			) ) }
			{ onTryAgain && (
				<RegenerateControl
					kind={ regenerateKind }
					hide={ regenerateHide }
					busy={ !! busy }
					onRegenerate={ ( options ) => onTryAgain( options ) }
				/>
			) }
			<Button variant="tertiary" isDestructive onClick={ onDiscard }>
				{ __( 'Discard', 'chatbot' ) }
			</Button>
		</div>
		<p className="qcwr-muted">
			{ __(
				'Nothing is saved until you save the post. Undo (Ctrl+Z) works as usual.',
				'chatbot'
			) }
		</p>
	</Modal>
);

export default Preview;
