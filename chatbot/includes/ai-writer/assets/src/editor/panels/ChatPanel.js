/**
 * AI chat that knows the post and the site voice. Replies can be inserted
 * into the post as a paragraph.
 */
import { createBlock } from '@wordpress/blocks';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { postContext, useRequest } from '../utils';

const ChatPanel = () => {
	const [ messages, setMessages ] = useState( [] );
	const [ text, setText ] = useState( '' );
	const request = useRequest();

	const send = () => {
		const next = [ ...messages, { role: 'user', content: text.trim() } ];
		setMessages( next );
		setText( '' );
		request
			.run( 'chat', 'editor/chat', { ...postContext(), messages: next } )
			.then( ( body ) => {
				if ( body ) {
					setMessages( [
						...next,
						{ role: 'assistant', content: body.reply },
					] );
				}
			} );
	};

	const insert = ( content ) => {
		const editor = select( 'core/block-editor' );
		const selected = editor.getSelectedBlockClientId();
		const index = selected
			? editor.getBlockIndex( selected ) + 1
			: editor.getBlockCount();
		const blocks = content
			.split( /\n{2,}/ )
			.map( ( paragraph ) => paragraph.trim() )
			.filter( Boolean )
			.map( ( paragraph ) =>
				createBlock( 'core/paragraph', {
					content: paragraph.replace(
						/[<>&]/g,
						( c ) =>
							( { '<': '&lt;', '>': '&gt;', '&': '&amp;' } )[ c ]
					),
				} )
			);
		dispatch( 'core/block-editor' ).insertBlocks(
			blocks,
			index,
			selected
				? editor.getBlockRootClientId( selected ) || undefined
				: undefined
		);
	};

	return (
		<div className="qcwr-chat">
			<div className="qcwr-chat__log" aria-live="polite">
				{ ! messages.length && (
					<p className="qcwr-muted">
						{ __(
							'Ask anything about this post: "What is missing?", "Give me an example for the second section", "Make the intro friendlier".',
							'chatbot'
						) }
					</p>
				) }
				{ messages.map( ( message, index ) => (
					<div
						key={ index }
						className={ `qcwr-chat__msg qcwr-chat__msg--${ message.role }` }
					>
						<p>{ message.content }</p>
						{ 'assistant' === message.role && (
							<Button
								variant="link"
								onClick={ () => insert( message.content ) }
							>
								{ __(
									'Insert into the post',
									'chatbot'
								) }
							</Button>
						) }
					</div>
				) ) }
			</div>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			<TextareaControl
				label={ __( 'Message', 'chatbot' ) }
				hideLabelFromVision
				placeholder={ __( 'Ask AI Writer…', 'chatbot' ) }
				value={ text }
				onChange={ setText }
				rows={ 2 }
				onKeyDown={ ( event ) => {
					if (
						'Enter' === event.key &&
						! event.shiftKey &&
						text.trim()
					) {
						event.preventDefault();
						send();
					}
				} }
				__nextHasNoMarginBottom
			/>
			<Button
				variant="secondary"
				isBusy={ !! request.busy }
				disabled={ !! request.busy || ! text.trim() }
				onClick={ send }
			>
				{ __( 'Send', 'chatbot' ) }
			</Button>
		</div>
	);
};

export default ChatPanel;
