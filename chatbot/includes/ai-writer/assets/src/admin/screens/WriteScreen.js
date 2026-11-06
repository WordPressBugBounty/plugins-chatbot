/**
 * Write: the "Write something new" flow (brief → questions → outline →
 * writing → done). Opens empty, with a job to continue, a brief handed over
 * by another screen (Overview, Topic Finder) or module, a planned day from
 * the calendar, or in "Answer visitor questions" mode.
 */
import { Button, ButtonGroup, Notice, Spinner } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { data, errorMessage, writer } from '../api';
import WriteFlow, { emptyBrief } from '../components/write/WriteFlow';
import { navigate, takeIntent } from '../nav';
import { ProSpots } from '../components/pro/ProPreview';

/**
 * What the flow starts with.
 *
 * @return {Object} jobId, brief, token (a stored brief to load).
 */
const start = () => {
	const intent = takeIntent() || {};
	if ( intent.jobId ) {
		return { jobId: intent.jobId };
	}
	if ( intent.brief ) {
		return { brief: intent.brief };
	}
	if ( intent.answer ) {
		return { brief: { ...emptyBrief(), mode: 'answer', type: 'faq' } };
	}
	// Links from other screens and modules are used once.
	if ( data.brief ) {
		const token = data.brief;
		data.brief = '';
		return { token };
	}
	if ( data.writeDate ) {
		const date = data.writeDate;
		data.writeDate = '';
		return { brief: { ...emptyBrief(), date } };
	}
	return {};
};

const WriteScreen = () => {
	const [ setup, setSetup ] = useState( start );
	const [ error, setError ] = useState( '' );
	const [ mode, setMode ] = useState( 'one' );

	/**
	 * Other ways to write on this screen (the Pro module adds "Many from a
	 * list"). Each: { id, label, render() }.
	 */
	const modes = applyFilters( 'qcld_wpbot_writer.write.modes', [] );
	const extra = modes.find( ( item ) => item.id === mode );

	// A brief stored by qcld_wpbot_writer_open_brief_url().
	useEffect( () => {
		if ( ! setup.token ) {
			return;
		}
		writer( `write/brief/${ setup.token }` )
			.then( ( brief ) => {
				if (
					( 'improve' === brief.mode || 'refresh' === brief.mode ) &&
					brief.post_id
				) {
					// Numbers from SEO & AI Search go with the fix, so they show in Improve.
					const numbers = Object.entries( brief.numbers || {} )
						.map( ( [ label, value ] ) => `${ label }: ${ value }` )
						.join( ', ' );
					navigate( 'improve', {
						post: {
							id: brief.post_id,
							title: brief.post_title || brief.topic || '',
						},
						fix: [ brief.fix || '', numbers ]
							.filter( Boolean )
							.join( ' — ' ),
					} );
					return;
				}
				setSetup( {
					brief: {
						...emptyBrief(),
						mode: brief.mode,
						topic: brief.topic || '',
						type:
							brief.type ||
							( 'answer' === brief.mode
								? 'faq'
								: emptyBrief().type ),
						sources: {
							...emptyBrief().sources,
							notes: brief.notes || '',
						},
						// Pre-filled from the shared keyword and topic map.
						main_query: brief.main_query || '',
						map_topic: brief.map_topic || '',
						idea_id: brief.idea_id || 0,
						// CR-16: a format chosen elsewhere counts as the owner's choice.
						format: brief.format || '',
						format_picked: !! brief.format,
						keywords: [
							brief.main_query,
							...( brief.supporting_queries || [] ),
						].filter( Boolean ),
						// Round 2 (CR-6 W1).
						intent: brief.intent || '',
						questions: brief.questions || [],
						link_to: brief.link_to || [],
						link_from: brief.link_from || [],
						keyword_numbers: brief.keyword_numbers || {},
						numbers: brief.numbers || {},
						vault_id: brief.vault_id || 0,
						// Round 3: choices sent with the link (Pro plans); else detected.
						persuasion:
							brief.persuasion && brief.persuasion.owner
								? brief.persuasion
								: {},
					},
					// Titles for the link lists (display only).
					linkPosts: {
						to: brief.link_to_posts || [],
						from: brief.link_from_posts || [],
					},
				} );
			} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				setSetup( {} );
			} );
	}, [ setup.token ] );

	if ( setup.token ) {
		return <Spinner />;
	}

	return (
		<>
			{ modes.length > 0 && (
				<ButtonGroup className="qcwr-write-modes">
					<Button
						variant={ 'one' === mode ? 'primary' : 'secondary' }
						onClick={ () => setMode( 'one' ) }
					>
						{ __( 'One piece', 'chatbot' ) }
					</Button>
					{ modes.map( ( item ) => (
						<Button
							key={ item.id }
							variant={
								item.id === mode ? 'primary' : 'secondary'
							}
							onClick={ () => setMode( item.id ) }
						>
							{ item.label }
						</Button>
					) ) }
				</ButtonGroup>
			) }
			{ ! extra && (
				<ProSpots
					spots={ [ 'write.interview', 'write.bulk' ] }
					title={ __(
						'Other ways to write with WPBot Pro:',
						'chatbot'
					) }
				/>
			) }
			{ extra && extra.render() }
			{ ! extra && error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ ! extra && (
				<WriteFlow
					initialJobId={ setup.jobId || 0 }
					initialBrief={ setup.brief || null }
					linkPosts={ setup.linkPosts || null }
					onExit={ () => navigate( 'overview' ) }
				/>
			) }
		</>
	);
};

export default WriteScreen;
