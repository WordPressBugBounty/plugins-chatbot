/**
 * "Your focus": what the owner wants to promote or say, in one sentence,
 * and the topics to look for places with. Suggestions come from the site
 * context (one click to use, each with where it came from). The built-in
 * rules always win over the focus.
 */
import {
	Button,
	FormTokenField,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';

const SOURCE = {
	site: __( 'from your pages', 'chatbot' ),
	search: __( 'from your top searches', 'chatbot' ),
	products: __( 'from your products', 'chatbot' ),
	questions: __( 'from customer questions', 'chatbot' ),
	knowledge: __( "from your chatbot's knowledge base", 'chatbot' ),
};

/**
 * @param {Object}   props         Props.
 * @param {number}   props.version Bumped when the context changed (reload suggestions).
 * @param {Function} props.onSaved Called after saving.
 * @return {Element} Card.
 */
const YourFocus = ( { version = 0, onSaved = () => {} } ) => {
	const [ view, setView ] = useState( null );
	const [ focus, setFocus ] = useState( '' );
	const [ topics, setTopics ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const take = ( next ) => {
		setView( next );
		setFocus( next.focus );
		setTopics( next.topics );
	};
	useEffect( () => {
		seo( 'cited/focus' )
			.then( take )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	}, [ version ] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}
	const save = ( nextFocus = focus, nextTopics = topics ) => {
		setBusy( true );
		setMessage( null );
		seo( 'cited/focus/save', {
			method: 'POST',
			data: { focus: nextFocus, topics: nextTopics },
		} )
			.then( ( next ) => {
				take( next );
				setMessage( {
					status: 'success',
					text: __( 'Focus saved.', 'chatbot' ),
				} );
				onSaved( next );
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( false ) );
	};
	const dirty =
		focus !== view.focus ||
		JSON.stringify( topics ) !== JSON.stringify( view.topics );
	return (
		<div className="qcseo-card qcseo-cited-focus">
			<h3>{ __( 'Your focus', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'What you want to promote or say. Places are ranked by it and every draft follows it, within the built-in rules: your own voice, true facts only, disclosure when you promote, and each community’s rules first.',
					'chatbot'
				) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ view.suggestions.length > 0 && (
				<div className="qcseo-focus-ideas">
					<p>
						<strong>
							{ __( 'Suggested for you', 'chatbot' ) }
						</strong>
					</p>
					<ul>
						{ view.suggestions.map( ( idea, index ) => (
							<li key={ index }>
								<span>
									{ idea.focus }{ ' ' }
									<span className="qcseo-muted">
										(
										{ SOURCE[ idea.source ] || idea.source }
										)
									</span>
								</span>
								{ view.can_edit && (
									<span className="qcseo-actions">
										<Button
											variant="secondary"
											size="compact"
											disabled={ busy }
											onClick={ () =>
												save( idea.focus, topics )
											}
										>
											{ __( 'Use', 'chatbot' ) }
										</Button>
										<Button
											variant="tertiary"
											size="compact"
											onClick={ () =>
												setFocus( idea.focus )
											}
										>
											{ __( 'Edit', 'chatbot' ) }
										</Button>
									</span>
								) }
							</li>
						) ) }
					</ul>
				</div>
			) }
			{ 0 === view.suggestions.length && (
				<p className="qcseo-muted">
					{ __(
						'Build your context above to get focus suggestions from your own site.',
						'chatbot'
					) }
				</p>
			) }
			<div className="qcseo-form">
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Focus', 'chatbot' ) }
					help={ __(
						'For example: "Mention WPBot when it truly solves the person’s problem, especially for WooCommerce stores." Up to 500 characters.',
						'chatbot'
					) }
					rows={ 3 }
					value={ focus }
					disabled={ ! view.can_edit }
					onChange={ setFocus }
				/>
				<FormTokenField
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __(
						'Topics to find places for (up to 8)',
						'chatbot'
					) }
					value={ topics }
					suggestions={ view.offers }
					maxLength={ 8 }
					disabled={ ! view.can_edit }
					onChange={ ( tokens ) =>
						setTopics(
							tokens.map( ( t ) =>
								'string' === typeof t ? t : t.value
							)
						)
					}
				/>
				{ view.can_edit && (
					<div className="qcseo-actions">
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || ! dirty }
							onClick={ () => save() }
						>
							{ __( 'Save focus', 'chatbot' ) }
						</Button>
						{ view.updated_at && (
							<span className="qcseo-muted">
								{ sprintf(
									/* translators: %s: date. */
									__( 'Saved %s', 'chatbot' ),
									view.updated_at.slice( 0, 10 )
								) }
							</span>
						) }
					</div>
				) }
			</div>
		</div>
	);
};

export default YourFocus;
