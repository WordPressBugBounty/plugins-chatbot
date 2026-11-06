/**
 * The AI Writer sidebar in the block editor, plus the pre-publish image
 * check. Panels: Write with AI, title and excerpt, search appearance, add
 * to the post, variations, images, checklist, WPBot knowledge, chat and
 * Quick write.
 */
import { PanelBody } from '@wordpress/components';
import * as editPost from '@wordpress/edit-post';
import * as editor from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { data } from '../admin/api';
import AiReadiness, { AiOnly } from '../admin/components/AiReadiness';
import QuickWrite from '../admin/components/QuickWrite';
import { sparkles } from './icons';
import { FeaturedSuggestions, ImageCheck } from './ImageTools';
import { AddBlocks, Variations } from './panels/AddPanel';
import RegeneratePanel from './panels/RegeneratePanel';
import ChatPanel from './panels/ChatPanel';
import ChecklistPanel from './panels/ChecklistPanel';
import KnowledgePanel from './panels/KnowledgePanel';
import LinkFromPanel from './panels/LinkFromPanel';
import SeoPanel from './panels/SeoPanel';
import TitlePanel from './panels/TitlePanel';
import WritePanel from './panels/WritePanel';
import { previewFor, ProSpots } from '../admin/components/pro/ProPreview';

/**
 * Pro tools shown as inactive previews in WPBot Free (none in Pro).
 */
const EDITOR_SPOTS = [
	'editor.internal_links',
	'editor.fact_check',
	'editor.translate',
	'editor.channels',
	'editor.voice',
	'editor.review',
];

// WordPress 6.6 moved these to @wordpress/editor; older versions have them in edit-post.
const PluginSidebar = editor.PluginSidebar || editPost.PluginSidebar;
const PluginSidebarMoreMenuItem =
	editor.PluginSidebarMoreMenuItem || editPost.PluginSidebarMoreMenuItem;
const PluginPrePublishPanel =
	editor.PluginPrePublishPanel || editPost.PluginPrePublishPanel;

export const AiSidebar = () => {
	const empty = useSelect( ( s ) => {
		const blocks = s( 'core/block-editor' ).getBlocks();
		return (
			! blocks.length ||
			( 1 === blocks.length &&
				'core/paragraph' === blocks[ 0 ].name &&
				! String( blocks[ 0 ].attributes.content || '' ).trim() )
		);
	}, [] );
	return (
		<>
			<PluginSidebarMoreMenuItem target="qcwr-sidebar" icon={ sparkles }>
				{ __( 'AI Writer', 'chatbot' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="qcwr-sidebar"
				title={ __( 'AI Writer', 'chatbot' ) }
				icon={ sparkles }
				className="qcwr-sidebar"
			>
				<AiReadiness
					readiness={ data.readiness }
					health={ data.health }
					newTab
				/>
				<PanelBody
					title={ __( 'Write with AI', 'chatbot' ) }
					initialOpen={ empty || !! data.pendingJob }
				>
					<AiOnly>
						<WritePanel />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Regenerate', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<RegeneratePanel />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Title and excerpt', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<TitlePanel />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Search appearance', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<SeoPanel />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Add to the post', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<AddBlocks />
						<Variations />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Images', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<FeaturedSuggestions />
					</AiOnly>
					<p className="qcwr-muted">
						{ __(
							'To replace an image, select it and use the sparkle button in its toolbar.',
							'chatbot'
						) }
					</p>
					<ImageCheck featuredFix={ false } />
				</PanelBody>
				{ data.linkFrom && data.linkFrom.length > 0 && (
					<PanelBody
						title={ __(
							'Link to this article from',
							'chatbot'
						) }
						initialOpen
					>
						<LinkFromPanel />
					</PanelBody>
				) }
				<PanelBody
					title={ __( 'Checklist', 'chatbot' ) }
					initialOpen={ ! empty }
				>
					<ChecklistPanel />
				</PanelBody>
				{ EDITOR_SPOTS.some( previewFor ) && (
					<PanelBody
						title={ __( 'More with WPBot Pro', 'chatbot' ) }
						initialOpen={ false }
					>
						<ProSpots spots={ EDITOR_SPOTS } title={ false } />
					</PanelBody>
				) }
				{ applyFilters( 'qcld_wpbot_writer.editor.panels', [] ).map(
					( panel ) => (
						<PanelBody
							key={ panel.name }
							title={ panel.title }
							initialOpen={ !! panel.initialOpen }
						>
							<AiOnly>{ panel.render() }</AiOnly>
						</PanelBody>
					)
				) }
				{ data.knowledge && data.knowledge.available && (
					<PanelBody
						title={ __( 'WPBot knowledge', 'chatbot' ) }
						initialOpen={ !! data.knowledge.answer }
					>
						<AiOnly>
							<KnowledgePanel />
						</AiOnly>
					</PanelBody>
				) }
				<PanelBody
					title={ __( 'AI chat', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<ChatPanel />
					</AiOnly>
				</PanelBody>
				<PanelBody
					title={ __( 'Quick write', 'chatbot' ) }
					initialOpen={ false }
				>
					<AiOnly>
						<QuickWrite compact sourcePost={ data.postId } />
					</AiOnly>
				</PanelBody>
			</PluginSidebar>
		</>
	);
};

export const ImagePrePublish = () => (
	<PluginPrePublishPanel
		title={ __( 'Image check', 'chatbot' ) }
		initialOpen
		icon={ sparkles }
	>
		<p className="qcwr-muted">
			{ __(
				'Suggestions only: you can publish either way.',
				'chatbot'
			) }
		</p>
		<ImageCheck />
	</PluginPrePublishPanel>
);
