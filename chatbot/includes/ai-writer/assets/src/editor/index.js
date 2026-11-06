/**
 * AI Writer in the block editor: sidebar, pre-publish image check, block
 * toolbar actions, image Replace, and the /ai block.
 */
import { registerPlugin } from '@wordpress/plugins';
import { registerAiBlock } from './AiBlock';
import { registerBlockActions } from './BlockActions';
import { FirstOpen, registerFeaturedButton } from './FeaturedButton';
import { registerImageTools } from './ImageTools';
import { AiSidebar, ImagePrePublish } from './Sidebar';
import UpdateNotices from './UpdateNotices';
import './style.scss';

registerBlockActions();
registerImageTools();
registerAiBlock();
registerFeaturedButton();

registerPlugin( 'qcwr-ai-writer', {
	render: () => (
		<>
			<AiSidebar />
			<ImagePrePublish />
			<UpdateNotices />
			<FirstOpen />
		</>
	),
} );
