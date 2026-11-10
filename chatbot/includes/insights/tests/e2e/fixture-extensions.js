/* Test-only fixture for tests/e2e/extensions.py (admin parts + tracker part). */
( function () {
	if ( /tracker=1/.test( ( document.currentScript && document.currentScript.src ) || '' ) || ! window.wp || ! window.wp.hooks ) {
		// Tracker extension: send one item of feature data.
		if ( window.qcInsights && window.qcInsights.push ) {
			window.qcInsights.push( 'demo', { n: 7 } );
			window.__qcinDemoPushed = true;
		}
		return;
	}
	var h = wp.hooks, el = wp.element.createElement, ui = window.qcInsightsUI;
	h.addFilter( 'qcin.admin.screens', 'test/demo', function ( s ) {
		s.demo = function ( p ) {
			return el( 'div', { className: 'qcin-card', id: 'demo-screen' }, 'Demo screen ', ( window.qcinDemo || {} ).hello, ' ', p.ui === ui ? 'ui-ok' : 'ui-missing' );
		};
		return s;
	} );
	h.addFilter( 'qcin.admin.settingsSections', 'test/demo', function ( l ) {
		return l.concat( [ { id: 'demo', render: function () { return el( ui.Section, { id: 'demo-section', title: 'Demo section' }, 'demo body' ); } } ] );
	} );
	h.addFilter( 'qcin.admin.overviewCards', 'test/demo', function ( l ) {
		return l.concat( [ { id: 'demo', render: function ( p ) { return el( 'div', { className: 'qcin-card', id: 'demo-card' }, 'Demo card ' + p.range ); } } ] );
	} );
	h.addFilter( 'qcin.admin.recordingsFilters', 'test/demo', function ( l ) {
		return l.concat( [ { key: 'demo', label: 'Demo filter', options: [ { value: '', label: 'All' }, { value: 'first', label: 'First only' } ] } ] );
	} );
	h.addFilter( 'qcin.admin.recordingBadges', 'test/demo', function ( b, s ) {
		return s.demo ? b.concat( [ { key: 'demo', label: 'Demo#' + s.demo, className: 'demo-badge' } ] ) : b;
	} );
	h.addFilter( 'qcin.admin.playerActions', 'test/demo', function ( l ) {
		return l.concat( [ { id: 'demo', render: function () { return el( 'button', { type: 'button', id: 'demo-action', className: 'qcin-btn qcin-btn--small' }, 'Demo action' ); } } ] );
	} );
	h.addFilter( 'qcin.admin.playerPanels', 'test/demo', function ( l ) {
		return l.concat( [ { id: 'demo', render: function ( p ) { return el( 'div', { id: 'demo-panel' }, 'Demo panel ' + ( p.session && p.session.id ? 'has-session' : '' ) ); } } ] );
	} );
	h.addFilter( 'qcin.admin.playerMarkers', 'test/demo', function ( m ) {
		return m.concat( [ { type: 'demo', offset: 1000, label: 'Demo marker', text: 'chat opened' } ] );
	} );
	h.addFilter( 'qcin.admin.heatmapModes', 'test/demo', function ( l ) {
		return l.concat( [ {
			id: 'demo', label: 'Demo map', help: 'Demo help',
			draw: function ( doc ) { var d = doc.createElement( 'div' ); d.id = 'demo-drawn'; doc.body.appendChild( d ); },
			render: function () { return el( 'div', { id: 'demo-mode-summary' }, 'Demo summary' ); },
		} ] );
	} );
	// 0.5.0: a screen filter (segment) on the Overview, Heatmaps and Recordings.
	h.addFilter( 'qcin.admin.screenFilters', 'test/demo', function ( l ) {
		return l.concat( [ { key: 'demo_seg', label: 'Demo segment', options: [ { value: '', label: 'All visits' }, { value: 'mobile', label: 'Mobile only' } ] } ] );
	} );
	// A part that throws must not break the screen.
	h.addFilter( 'qcin.admin.settingsSections', 'test/broken', function ( l ) {
		return l.concat( [ { id: 'broken', render: function () { throw new Error( 'boom' ); } } ] );
	} );
} )();
