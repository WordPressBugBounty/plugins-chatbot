/**
 * Regenerate a whole article (CR-14): keep the outline and rewrite the
 * sections, or write a new outline, with the same or changed options. The
 * cost is always shown first. A published post gets a proposed update (the
 * live post does not change); a draft is rewritten in place, with its
 * earlier text kept in Revisions.
 *
 * Used in the editor sidebar and by WPBot Pro's lists of drafts.
 */
import { Button, Notice, RadioControl, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, writer } from '../../api';
import JobProgress from '../JobProgress';
import { OptionsForm } from './Regenerate';

/**
 * The control.
 *
 * @param {Object}                   props          Props.
 * @param {number}                   props.postId   Post.
 * @param {boolean}                  props.isDraft  The post is not published yet.
 * @param {boolean}                  props.inEditor Shown in the open editor (offer to reload the draft).
 * @param {(result: Object) => void} props.onDone   Called with the job result.
 * @return {Element} Control.
 */
const ArticleRegenerate = ( { postId, isDraft, inEditor = false, onDone } ) => {
	const [ mode, setMode ] = useState( 'keep_outline' );
	const [ options, setOptions ] = useState( {
		voice: '',
		tone: '',
		length: 'same',
		level: '',
		technique: '',
		language: '',
		change: '',
	} );
	const [ estimate, setEstimate ] = useState( null );
	const [ jobId, setJobId ] = useState( 0 );
	const [ done, setDone ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const call = ( step ) => {
		setBusy( true );
		setError( '' );
		return writer( 'regenerate/article', {
			method: 'POST',
			data: {
				post_id: postId,
				mode,
				step,
				...( 'start' === step ? { regenerate: options } : {} ),
			},
		} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				return null;
			} )
			.finally( () => setBusy( false ) );
	};
	const ask = () =>
		call( 'estimate' ).then(
			( body ) => body && setEstimate( body.estimate )
		);
	const start = () =>
		call( 'start' ).then( ( body ) => body && setJobId( body.job_id ) );

	if ( done && done.in_place ) {
		return (
			<Notice status="success" isDismissible={ false }>
				{ __(
					'The draft was rewritten. The earlier text is kept in Revisions.',
					'chatbot'
				) }{ ' ' }
				{ inEditor ? (
					<Button
						variant="link"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload the draft', 'chatbot' ) }
					</Button>
				) : (
					done.edit_url && (
						<a href={ done.edit_url }>
							{ __( 'Open the draft', 'chatbot' ) }
						</a>
					)
				) }
			</Notice>
		);
	}
	if ( done ) {
		return (
			<Notice status="success" isDismissible={ false }>
				{ __(
					'The rewritten article is ready as a proposed update. Your post has not changed.',
					'chatbot'
				) }{ ' ' }
				{ done.update_url && (
					<a href={ done.update_url }>
						{ __( 'Review the update', 'chatbot' ) }
					</a>
				) }
			</Notice>
		);
	}
	if ( jobId ) {
		return (
			<JobProgress
				jobId={ jobId }
				onChange={ ( job ) => {
					if ( job && 'done' === job.status ) {
						writer( `write/jobs/${ jobId }` ).then( ( view ) => {
							const result = view.result || {};
							setDone( result );
							if ( onDone ) {
								onDone( result );
							}
						} );
					}
				} }
			/>
		);
	}
	return (
		<div className="qcwr-regen-article">
			<RadioControl
				label={ __(
					'Regenerate the whole article',
					'chatbot'
				) }
				selected={ mode }
				options={ [
					{
						value: 'keep_outline',
						label: __(
							'Keep the outline, rewrite the sections',
							'chatbot'
						),
					},
					{
						value: 'new_outline',
						label: __( 'Write a new outline', 'chatbot' ),
					},
				] }
				onChange={ ( next ) => {
					setMode( next );
					setEstimate( null );
				} }
			/>
			<OptionsForm
				value={ options }
				onChange={ setOptions }
				onSubmit={ ask }
				submitLabel={ __( 'Show the cost', 'chatbot' ) }
			/>
			{ busy && <Spinner /> }
			{ estimate && (
				<div className="qcwr-regen-article__estimate">
					<p>
						{ isDraft
							? sprintf(
									/* translators: 1: AI calls, 2: cost in US dollars. */
									__(
										'About %1$d AI calls, about $%2$s. The draft is rewritten (save your changes first); the earlier text is kept in Revisions.',
										'chatbot'
									),
									estimate.calls,
									Number( estimate.usd ).toFixed( 2 )
								)
							: sprintf(
									/* translators: 1: AI calls, 2: cost in US dollars. */
									__(
										'About %1$d AI calls, about $%2$s. It arrives as a proposed update; nothing changes until you publish it.',
										'chatbot'
									),
									estimate.calls,
									Number( estimate.usd ).toFixed( 2 )
								) }
					</p>
					<Button
						variant="primary"
						onClick={ start }
						disabled={ busy }
					>
						{ __( 'Start', 'chatbot' ) }
					</Button>
				</div>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</div>
	);
};

export default ArticleRegenerate;
