/**
 * Step 5: the draft is ready for review in the normal editor.
 */
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ArticleRegenerate from '../regenerate/ArticleRegenerate';
import ArticleView from './ArticleView';

const DoneStep = ( { result, onNew, answer = false } ) => {
	// CR-14: after a regenerate, show the rewritten draft.
	const [ round, setRound ] = useState( 0 );
	return (
		<div className="qcwr-card qcwr-done">
			<h2>{ __( 'Your draft is ready', 'chatbot' ) }</h2>
			<p>
				{ sprintf(
					/* translators: 1: words written, 2: target words. */
					__(
						'%1$d words (target %2$d). Nothing is published until you publish it.',
						'chatbot'
					),
					result.words,
					result.target
				) }
			</p>
			<ul className="qcwr-done__checks">
				<li>
					{ sprintf(
						/* translators: %d: number of placeholders. */
						__(
							'Personal touches to add: %d [placeholders]',
							'chatbot'
						),
						result.placeholders
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: number of claims. */
						__( 'Claims to check: %d', 'chatbot' ),
						result.claims
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: number of flags. */
						__(
							'Sentences that may sound like AI: %d',
							'chatbot'
						),
						result.ai_patterns
					) }
				</li>
				{ result.images && (
					<li>
						{ sprintf(
							/* translators: 1: images added, 2: image placeholders. */
							__(
								'Images: %1$d added, %2$d to replace',
								'chatbot'
							),
							result.images.filled,
							result.images.placeholders
						) }
					</li>
				) }
			</ul>
			{ result.format_flags && result.format_flags.length > 0 && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="qcwr-format-flags"
				>
					<p>
						<strong>
							{ __(
								'Format checks to look at',
								'chatbot'
							) }
						</strong>
					</p>
					<ul>
						{ result.format_flags.map( ( flag ) => (
							<li key={ flag.code }>{ flag.message }</li>
						) ) }
					</ul>
				</Notice>
			) }
			{ answer && (
				<p className="qcwr-muted qcwr-done__knowledge">
					{ __(
						'After you publish it, open the AI Writer sidebar and click "Add to chatbot knowledge" so your chatbot can answer with it.',
						'chatbot'
					) }
				</p>
			) }
			<div className="qcwr-actions">
				<Button variant="primary" href={ result.edit_url }>
					{ __( 'Open the draft', 'chatbot' ) }
				</Button>
				<Button variant="secondary" onClick={ onNew }>
					{ __( 'Write something else', 'chatbot' ) }
				</Button>
			</div>
			{ result.post_id > 0 && ! result.into_draft && (
				<details className="qcwr-done__regenerate">
					<summary>
						{ __( 'Regenerate the article', 'chatbot' ) }
					</summary>
					<ArticleRegenerate
						postId={ result.post_id }
						isDraft
						onDone={ () => setRound( round + 1 ) }
					/>
				</details>
			) }
			{ result.post_id > 0 && ! result.into_draft && (
				<ArticleView key={ round } postId={ result.post_id } />
			) }
		</div>
	);
};

export default DoneStep;
