/**
 * "Who ranks for this search?" in the brief (CR-8; Free since 0.15).
 *
 * Lists the pages that rank, from an AI model with web search (the site's
 * own key), on the owner's click. AI Writer Pro adds "Analyse these pages"
 * through the `qcld_wpbot_writer.who_ranks.after` filter. In Free, that
 * place shows a labelled, inactive Pro item with a short preview, only
 * when the host offers Pro previews and the owner has not hidden them.
 */
import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { errorMessage, writer } from '../../api';
import { ProSpot } from '../pro/ProPreview';

/**
 * Free: "Analyse these pages" as a labelled Pro preview (host text).
 *
 * @return {Element|null} Preview.
 */
const ProAnalysePreview = () => <ProSpot spot="who_ranks.analyse" as="div" />;

const WhoRanks = ( { brief, setBrief } ) => {
	const fallback = ( brief.main_query || brief.topic || '' ).trim();
	const [ query, setQuery ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const search = ( query || fallback ).trim();

	const look = () => {
		setBusy( true );
		setError( '' );
		writer( 'write/who-ranks', { method: 'POST', data: { query: search } } )
			.then( setResult )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<details className="qcwr-who-ranks">
			<summary>
				{ __( 'Who ranks for this search?', 'chatbot' ) }
			</summary>
			<div className="qcwr-who-ranks__form">
				<TextControl
					label={ __( 'Search', 'chatbot' ) }
					hideLabelFromVision
					placeholder={
						fallback ||
						__( 'Write the topic first.', 'chatbot' )
					}
					value={ query }
					onChange={ setQuery }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy || ! search }
					onClick={ look }
				>
					{ __( 'Look it up', 'chatbot' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ result && (
				<>
					<ol className="qcwr-who-ranks__list">
						{ result.results.map( ( row ) => (
							<li key={ row.url }>
								<a
									href={ row.url }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ row.title || row.url }
								</a>{ ' ' }
								<span className="qcwr-muted">
									{ row.site }
									{ row.note ? ` · ${ row.note }` : '' }
								</span>
							</li>
						) ) }
					</ol>
					{ applyFilters(
						'qcld_wpbot_writer.who_ranks.after',
						<ProAnalysePreview />,
						{ result, brief, setBrief }
					) }
				</>
			) }
		</details>
	);
};

export default WhoRanks;
