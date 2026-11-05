/**
 * SEO & AI Search app: key notices, then the screen of the open menu item.
 * The left menu is WPBot Core's shell (printed by PHP); the app swaps the
 * screen when the shell asks, so items keep their URLs and Back works.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data } from './api';
import { setFallback } from './nav';
import KeyNotice from './components/KeyNotice';
import { AiReady } from './ai';
import OverviewScreen from './screens/OverviewScreen';
import OpportunitiesScreen from './screens/OpportunitiesScreen';
import FixesScreen from './screens/FixesScreen';
import TechnicalScreen from './screens/TechnicalScreen';
import AiSearchScreen from './screens/AiSearchScreen';
import LocalScreen from './screens/LocalScreen';
import LinksScreen from './screens/LinksScreen';
import KeywordsScreen from './screens/KeywordsScreen';
import ResultsScreen from './screens/ResultsScreen';
import SettingsScreen from './screens/SettingsScreen';
import PreviewScreen from './screens/PreviewScreen';

/**
 * Screens by menu item id. Sections that later milestones fill show what
 * they will do and what to connect first.
 *
 * @return {Object} id => render( props ).
 */
const baseScreens = () => ( {
	overview: ( props ) => <OverviewScreen { ...props } />,
	opportunities: () => <OpportunitiesScreen />,
	keywords: () => <KeywordsScreen />,
	fixes: () => <FixesScreen />,
	technical: () => <TechnicalScreen />,
	'ai-search': () => <AiSearchScreen />,
	links: () => <LinksScreen />,
	local: () => <LocalScreen />,
	results: () => <ResultsScreen />,
	settings: ( props ) => <SettingsScreen { ...props } />,
} );

/**
 * Screens with AI actions: they show the bold AI notice when AI isn't ready.
 */
const AI_SCREENS = [
	'opportunities',
	'keywords',
	'fixes',
	'ai-search',
	'links',
	'local',
];

const App = () => {
	const [ health, setHealth ] = useState( data.health || [] );
	const [ current, setCurrent ] = useState( data.current || 'overview' );
	const [ visit, setVisit ] = useState( 0 );

	/**
	 * Extensions (the Pro module) add or replace screens here.
	 */
	const screens = applyFilters(
		'qcld_wpbot_seo.admin.screens',
		baseScreens()
	);

	useEffect( () => {
		const open = ( id ) => {
			setCurrent( id );
			setVisit( ( n ) => n + 1 );
			window.scrollTo( 0, 0 );
		};
		const shell = document.querySelector( '.qcwpbot-shell' );
		const onNavigate = ( event ) => {
			const id = event.detail && event.detail.id;
			if ( screens[ id ] || 0 === String( id ).indexOf( 'preview-' ) ) {
				event.preventDefault();
				open( id );
			}
		};
		setFallback( open );
		if ( shell ) {
			shell.addEventListener( 'wpbot-shell:navigate', onNavigate );
		}
		return () => {
			if ( shell ) {
				shell.removeEventListener( 'wpbot-shell:navigate', onNavigate );
			}
		};
		// Screens are registered once at load.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const item = ( data.menu || [] ).find( ( entry ) => entry.id === current );
	let body;
	if ( item && item.pro ) {
		body = <PreviewScreen id={ current } label={ item.label } />;
	} else if ( screens[ current ] ) {
		body = screens[ current ]( { setHealth } );
	} else {
		body = screens.overview( { setHealth } );
	}

	return (
		<div className="qcseo-shell">
			<h1 className="screen-reader-text">
				{ item
					? item.label
					: __( 'SEO & AI Search', 'chatbot' ) }
			</h1>
			<KeyNotice notices={ health } />
			{ AI_SCREENS.includes( current ) && <AiReady /> }
			<div className="qcseo-screen" key={ `${ current }-${ visit }` }>
				{ body }
			</div>
		</div>
	);
};

export default App;
