/**
 * Block editor: SEO score and top issues (sidebar panel) and advice before
 * publishing. Advice never blocks publishing.
 */
import apiFetch from '@wordpress/api-fetch';
import { PanelBody, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import * as editPost from '@wordpress/edit-post';
import * as editor from '@wordpress/editor';

const settings = window.qcldWpbotSeoEditor || {};

// WordPress 6.6+ moved these components to @wordpress/editor.
const DocumentPanel =
	editor.PluginDocumentSettingPanel || editPost.PluginDocumentSettingPanel;
const PrePublishPanel =
	editor.PluginPrePublishPanel || editPost.PluginPrePublishPanel;

/**
 * Audit of the post (re-checked after each save).
 *
 * @return {Object} audit, loading.
 */
const useAudit = () => {
	const { postId, saving, status } = useSelect( ( select ) => {
		const store = select( 'core/editor' );
		return {
			postId: store.getCurrentPostId(),
			saving: store.isSavingPost() && ! store.isAutosavingPost(),
			status: store.getEditedPostAttribute( 'status' ),
		};
	}, [] );
	const [ audit, setAudit ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	useEffect( () => {
		if ( ! postId || saving || 'auto-draft' === status ) {
			setLoading( false );
			return;
		}
		setLoading( true );
		apiFetch( { path: `/qcld-wpbot-seo/v1/fixes/${ postId }` } )
			.then( ( result ) => setAudit( result.audit ) )
			.catch( () => setAudit( null ) )
			.finally( () => setLoading( false ) );
	}, [ postId, saving, status ] );
	return { audit, loading, postId };
};

const Issues = ( { audit, postId } ) => (
	<>
		<p>
			<strong>
				{ sprintf(
					/* translators: %d: score out of 100. */
					__( 'SEO score: %d/100', 'chatbot' ),
					audit.score
				) }
			</strong>
		</p>
		{ 0 === audit.issues.length ? (
			<p>{ __( 'No on-page issues found.', 'chatbot' ) }</p>
		) : (
			<ul className="qcseo-editor-issues">
				{ audit.issues.slice( 0, 5 ).map( ( issue ) => (
					<li key={ issue.id }>
						<strong>{ issue.label }:</strong> { issue.message }
					</li>
				) ) }
			</ul>
		) }
		{ settings.canUse && audit.issues.length > 0 && (
			<a href={ `${ settings.fixesUrl }&post=${ postId }` }>
				{ __( 'Fix with AI in SEO & AI Search', 'chatbot' ) }
			</a>
		) }
	</>
);

const SeoPanel = () => {
	const { audit, loading, postId } = useAudit();
	return (
		<DocumentPanel
			name="qcld-wpbot-seo"
			title={ __( 'SEO & AI Search', 'chatbot' ) }
		>
			{ loading && <Spinner /> }
			{ ! loading && ! audit && (
				<p>
					{ __(
						'Save the page to see its SEO checks.',
						'chatbot'
					) }
				</p>
			) }
			{ ! loading && audit && (
				<Issues audit={ audit } postId={ postId } />
			) }
		</DocumentPanel>
	);
};

const PrePublish = () => {
	const { audit } = useAudit();
	if ( ! audit || 0 === audit.issues.length ) {
		return null;
	}
	return (
		<PrePublishPanel
			title={ __( 'SEO advice', 'chatbot' ) }
			initialOpen
		>
			<PanelBody>
				<p>
					{ __(
						'You can publish now; these can be fixed any time.',
						'chatbot'
					) }
				</p>
				<ul>
					{ audit.issues.slice( 0, 3 ).map( ( issue ) => (
						<li key={ issue.id }>{ issue.message }</li>
					) ) }
				</ul>
			</PanelBody>
		</PrePublishPanel>
	);
};

if ( DocumentPanel ) {
	registerPlugin( 'qcld-wpbot-seo', {
		render: () => (
			<>
				<SeoPanel />
				{ PrePublishPanel && <PrePublish /> }
			</>
		),
	} );
}
