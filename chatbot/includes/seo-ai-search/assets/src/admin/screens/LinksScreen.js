/**
 * Links: Link Monitor, Prospects, Outreach, Exchange Records and Results.
 * Helps earn links within Google's spam policies (no buying, no schemes).
 */
import { TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import Monitor from '../components/links/Monitor';
import Prospects from '../components/links/Prospects';
import Outreach from '../components/links/Outreach';
import Exchanges from '../components/links/Exchanges';
import LinksResults from '../components/links/LinksResults';
import GetCitedByAi from '../components/cited/GetCitedByAi';
import { withPreviewTabs } from '../components/ProPreview';

const LinksScreen = () => {
	// A link can open a tab directly (&section=cited, from Pro's AI visibility).
	const [ tab, setTab ] = useState(
		() =>
			new URLSearchParams( window.location.search ).get( 'section' ) ||
			'monitor'
	);
	const [ openId, setOpenId ] = useState( 0 );
	const tabs = withPreviewTabs(
		'links',
		applyFilters( 'qcld_wpbot_seo.links.tabs', [
			{
				name: 'monitor',
				title: __( 'Link Monitor', 'chatbot' ),
				render: () => <Monitor />,
			},
			{
				name: 'cited',
				title: __( 'Get cited by AI', 'chatbot' ),
				render: () => <GetCitedByAi />,
			},
			{
				name: 'prospects',
				title: __( 'Prospects', 'chatbot' ),
				render: () => (
					<Prospects
						onStarted={ ( item ) => {
							setOpenId( item.id );
							setTab( 'outreach' );
						} }
					/>
				),
			},
			{
				name: 'outreach',
				title: __( 'Outreach', 'chatbot' ),
				render: () => <Outreach openId={ openId } />,
			},
			{
				name: 'exchanges',
				title: __( 'Exchange Records', 'chatbot' ),
				render: () => <Exchanges />,
			},
			{
				name: 'results',
				title: __( 'Results', 'chatbot' ),
				render: () => <LinksResults />,
			},
		] )
	);
	return (
		<div className="qcseo-links">
			<h2 className="qcseo-screen-title">
				{ __( 'Links', 'chatbot' ) }
			</h2>
			<p className="qcseo-muted">
				{ __(
					"Earn links from other sites and keep them, within Google's spam policies: no buying links, no link schemes, and paid links always marked sponsored.",
					'chatbot'
				) }
			</p>
			<TabPanel
				key={ tab }
				className="qcseo-tabs"
				initialTabName={ tab }
				onSelect={ ( name ) => {
					if ( name !== tab ) {
						setTab( name );
					}
				} }
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

export default LinksScreen;
