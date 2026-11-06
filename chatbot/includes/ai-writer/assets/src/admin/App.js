/**
 * AI Writer app: key notices, then the screen of the open menu item. The
 * left menu itself is WPBot Core's admin shell (brief 2.4), printed by PHP;
 * the app shows the screen the shell asks for, so menu items keep their own
 * URLs and the back button works.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { core, data } from './api';
import { setFallback, takeIntent } from './nav';
import AiReadiness from './components/AiReadiness';
import QuickWrite from './components/QuickWrite';
import LaterScreen from './screens/LaterScreen';
import OverviewScreen from './screens/OverviewScreen';
import WriteScreen from './screens/WriteScreen';
import ImproveScreen from './screens/ImproveScreen';
import TopicsScreen from './screens/TopicsScreen';
import CalendarScreen from './screens/CalendarScreen';
import LibraryScreen from './screens/LibraryScreen';
import WooScreen from './screens/WooScreen';
import BrandBusinessScreen from './screens/BrandBusinessScreen';
import TemplatesScreen from './screens/TemplatesScreen';
import UsageScreen from './screens/UsageScreen';
import SettingsScreen from './screens/SettingsScreen';
import PreviewScreen from './screens/PreviewScreen';

/**
 * Screens that need the AI Writer "Use" permission show this instead.
 *
 * @param {string} title Screen title.
 * @return {Element} Notice.
 */
const needsUse = ( title ) => (
	<LaterScreen
		title={ title }
		text={ __(
			'Ask an administrator for the AI Writer "Use" permission.',
			'chatbot'
		) }
	/>
);

/**
 * Quick Write, opened with a template from the Templates screen.
 *
 * @return {Element} Screen.
 */
const QuickScreen = () => {
	const [ intent ] = useState( () => takeIntent() || {} );
	return <QuickWrite initialTemplate={ intent.template || '' } />;
};

const baseScreens = () => ( {
	overview: () => <OverviewScreen />,
	write: () =>
		data.can.use ? (
			<WriteScreen />
		) : (
			needsUse( __( 'Write', 'chatbot' ) )
		),
	quick: () =>
		data.can.use ? (
			<QuickScreen />
		) : (
			needsUse( __( 'Quick Write', 'chatbot' ) )
		),
	improve: () =>
		data.can.use ? (
			<ImproveScreen />
		) : (
			needsUse( __( 'Improve Content', 'chatbot' ) )
		),
	topics: () => <TopicsScreen />,
	calendar: () => <CalendarScreen />,
	library: () => <LibraryScreen />,
	woo: () => <WooScreen />,
	// CR-15: Brand Voice and Business Profile on one page.
	voice: () => <BrandBusinessScreen />,
	profile: () => <BrandBusinessScreen />,
	templates: () => <TemplatesScreen />,
	usage: ( props ) => <UsageScreen { ...props } />,
	settings: ( props ) => <SettingsScreen { ...props } />,
} );

const App = () => {
	const [ health, setHealth ] = useState( data.health || [] );
	// WPBot Core's "is AI ready?" answer (null with an older core).
	const [ readiness, setReadiness ] = useState( data.readiness || null );
	// After keys are saved or tested: new failing-key list and readiness.
	const onHealth = ( next ) => {
		setHealth( next );
		if ( data.readiness ) {
			core( `ai-readiness/${ data.module || 'writer' }` )
				.then( setReadiness )
				.catch( () => {} );
		}
	};
	const [ current, setCurrent ] = useState( data.current || 'overview' );
	// A new key per visit, so a screen opened again starts fresh.
	const [ visit, setVisit ] = useState( 0 );

	/**
	 * Screens by menu item id. Extensions (the Pro module) add screens for
	 * the items they add with the qcld_wpbot_writer/menu_items PHP filter.
	 *
	 * Each: id => render( { setHealth } ).
	 */
	const screens = applyFilters(
		'qcld_wpbot_writer.admin.screens',
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
	let body = null;
	if ( item && item.pro ) {
		body = <PreviewScreen id={ item.id } label={ item.label } />;
	} else if ( screens[ current ] ) {
		body = screens[ current ]( { setHealth: onHealth } );
	} else {
		body = screens.overview( { setHealth: onHealth } );
	}

	return (
		<div className="qcwr-shell">
			<h1 className="screen-reader-text">
				{ item ? item.label : __( 'AI Writer', 'chatbot' ) }
			</h1>
			{ /* AI screens show why AI can't run and the one-click fix. */ }
			{ ( ! readiness || ( item ? item.ai : true ) ) && (
				<AiReadiness readiness={ readiness } health={ health } />
			) }
			<div className="qcwr-screen" key={ `${ current }-${ visit }` }>
				{ body }
			</div>
		</div>
	);
};

export default App;
