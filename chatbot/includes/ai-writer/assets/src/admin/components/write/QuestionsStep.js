/**
 * Step 2: "Make it yours" — answers become facts the draft must use.
 */
import {
	Button,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage, writer } from '../../api';

const QuestionsStep = ( { brief, setBrief, onNext, onBack, busy } ) => {
	const [ questions, setQuestions ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		writer( 'write/questions', { method: 'POST', data: { brief } } )
			.then( ( result ) => setQuestions( result.questions ) )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				setQuestions( [] );
			} );
		// Ask once for this brief.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const answerOf = ( question ) => {
		const found = brief.answers.find( ( item ) => item.q === question );
		return found ? found.a : '';
	};
	const setAnswer = ( question, answer ) =>
		setBrief( {
			...brief,
			answers: [
				...brief.answers.filter( ( item ) => item.q !== question ),
				{ q: question, a: answer },
			],
		} );

	return (
		<div className="qcwr-card qcwr-questions">
			<h2>{ __( 'Make it yours', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'Your answers are what make the piece sound like you and not like everyone else. Skip any question you like.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ null === questions && <Spinner /> }
			{ ( questions || [] ).map( ( question ) => (
				<TextareaControl
					key={ question }
					label={ question }
					value={ answerOf( question ) }
					onChange={ ( value ) => setAnswer( question, value ) }
					rows={ 3 }
					__nextHasNoMarginBottom
				/>
			) ) }
			<div className="qcwr-actions">
				<Button variant="secondary" onClick={ onBack }>
					{ __( 'Back', 'chatbot' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || null === questions }
					onClick={ onNext }
				>
					{ __( 'Plan the outline', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default QuestionsStep;
