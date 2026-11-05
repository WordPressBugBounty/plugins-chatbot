/**
 * Keywords & Topics: Research and the Keyword Vault (S14, no connection
 * needed), then Keyword Map, Competing pages and Topic Map (S11, on the
 * shared map). Keyword ideas from a provider (S12) are part of Research.
 */
import { TabPanel } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import KeywordMap from '../components/keywords/KeywordMap';
import Competing from '../components/keywords/Competing';
import TopicMap from '../components/keywords/TopicMap';
import Research from '../components/research/Research';
import Vault from '../components/research/Vault';
import PaidSearches from '../components/research/PaidSearches';
import { withPreviewTabs } from '../components/ProPreview';

const KeywordsScreen = () => {
	const tabs = withPreviewTabs(
		'keywords',
		applyFilters( 'qcld_wpbot_seo.keywords.tabs', [
			{
				name: 'research',
				title: __( 'Research', 'chatbot' ),
				render: () => <Research />,
			},
			{
				name: 'vault',
				title: __( 'Vault', 'chatbot' ),
				render: () => <Vault />,
			},
			{
				name: 'map',
				title: __( 'Keyword Map', 'chatbot' ),
				render: () => <KeywordMap />,
			},
			{
				name: 'competing',
				title: __( 'Competing Pages', 'chatbot' ),
				render: () => <Competing />,
			},
			{
				name: 'topics',
				title: __( 'Topic Map', 'chatbot' ),
				render: () => <TopicMap />,
			},
			{
				name: 'paid',
				title: __( 'Paid searches', 'chatbot' ),
				render: () => <PaidSearches />,
			},
		] )
	);
	return (
		<div className="qcseo-keywords">
			<h2 className="qcseo-screen-title">
				{ __( 'Keywords & Topics', 'chatbot' ) }
			</h2>
			<p className="qcseo-muted">
				{ __(
					'Research keywords, keep them in your Vault and map them to pages. Works without any connection; Search Console and a keyword data provider add real numbers.',
					'chatbot'
				) }
			</p>
			<TabPanel
				className="qcseo-tabs"
				tabs={ tabs.map( ( t ) => ( {
					name: t.name,
					title: t.title,
					className: t.className,
				} ) ) }
			>
				{ ( current ) => {
					const found = tabs.find( ( t ) => t.name === current.name );
					return found ? found.render() : null;
				} }
			</TabPanel>
		</div>
	);
};

export default KeywordsScreen;
