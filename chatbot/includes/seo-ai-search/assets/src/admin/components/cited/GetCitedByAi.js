/**
 * Links > Get cited by AI: show up, with genuine content from your own
 * accounts, on the sites AI assistants and Google trust. Milestone A: the
 * shared Site Context, your focus and places to show up; drafts and the
 * posted log follow.
 */
import { applyFilters } from '@wordpress/hooks';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import SiteContext from './SiteContext';
import BusinessContextCard from './BusinessContextCard';
import { data } from '../../api';
import YourFocus from './YourFocus';
import PlacesToShowUp from './PlacesToShowUp';
import PostedLog from './PostedLog';

const GetCitedByAi = () => {
	const [ context, setContext ] = useState( null );
	const [ version, setVersion ] = useState( 0 );
	const [ posted, setPosted ] = useState( 0 );
	return (
		<div className="qcseo-cited">
			<p className="qcseo-muted">
				{ __(
					'Show up on the sites AI assistants and Google trust: Reddit, YouTube, Quora, LinkedIn and more. You post from your own accounts; nothing is posted for you.',
					'chatbot'
				) }
			</p>
			{ data.businessContext ? (
				<BusinessContextCard
					onChange={ ( next ) => {
						setContext( next );
						setVersion( ( v ) => v + 1 );
					} }
				/>
			) : (
				<SiteContext
					onChange={ ( next ) => {
						setContext( next );
						setVersion( ( v ) => v + 1 );
					} }
				/>
			) }
			<YourFocus
				version={ version }
				onSaved={ () => setVersion( ( v ) => v + 1 ) }
			/>
			<PlacesToShowUp
				version={ version }
				onPosted={ () => setPosted( ( v ) => v + 1 ) }
			/>
			<PostedLog version={ posted } />
			{ applyFilters( 'qcld_wpbot_seo.cited.after', null, { context } ) }
		</div>
	);
};

export default GetCitedByAi;
