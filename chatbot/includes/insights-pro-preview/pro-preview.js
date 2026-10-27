/**
 * Site Insight in WPBot Free: inactive previews of the Site Insight Pro
 * features, each marked "Pro" with a link to WPBot Pro.
 *
 * Nothing here runs a Pro feature: these are labels, sample pictures and
 * disabled controls. They plug into the Site Insight screens through the
 * core's wp.hooks filters (see includes/insights/assets/src/admin/extend.js).
 * PHP leaves this script out when the Pro module is active.
 */
( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.hooks || ! wp.i18n ) {
		return;
	}
	var boot = window.qcInsightsAdmin || {};
	var loaded = ( boot.features || [] ).some( function ( id ) {
		return String( id ).indexOf( 'pro.' ) === 0;
	} );
	if ( loaded ) {
		return;
	}

	var h = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var NS = 'wpbot/insights-pro-preview';
	var cfg = data || {};
	var UPGRADE = cfg.upgradeUrl || 'https://www.wpbot.pro/';

	function upgradeUrl( place ) {
		return UPGRADE + ( UPGRADE.indexOf( '?' ) === -1 ? '?' : '&' ) + 'utm_source=wpbot-free&utm_medium=site-insight&utm_campaign=' + encodeURIComponent( place );
	}

	function ui() {
		return window.qcInsightsUI || {};
	}

	// ---- Small parts --------------------------------------------------

	function Badge() {
		return h( 'span', { className: 'wpbot-pro-badge' }, __( 'Pro', 'chatbot' ) );
	}

	function Svg( props, children ) {
		return h(
			'svg',
			{ width: props.size || 18, height: props.size || 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true', focusable: 'false' },
			children
		);
	}

	var Icons = {
		lock: function ( p ) {
			return Svg( p || {}, [ h( 'rect', { key: 1, x: 5, y: 11, width: 14, height: 10, rx: 2 } ), h( 'path', { key: 2, d: 'M8 11V8a4 4 0 0 1 8 0v3' } ) ] );
		},
		issues: function ( p ) {
			return Svg( p || {}, [ h( 'path', { key: 1, d: 'M12 3l9 16H3z' } ), h( 'path', { key: 2, d: 'M12 10v4M12 17v.01' } ) ] );
		},
		segments: function ( p ) {
			return Svg( p || {}, [ h( 'circle', { key: 1, cx: 9, cy: 8, r: 3 } ), h( 'path', { key: 2, d: 'M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6' } ), h( 'path', { key: 3, d: 'M16 5.5a3 3 0 0 1 0 5M18 14.5c1.8.8 3 2.7 3 5' } ) ] );
		},
		funnels: function ( p ) {
			return Svg( p || {}, [ h( 'path', { key: 1, d: 'M3 4h18l-7 8v6l-4 2v-8z' } ) ] );
		},
		forms: function ( p ) {
			return Svg( p || {}, [ h( 'rect', { key: 1, x: 4, y: 3, width: 16, height: 18, rx: 2 } ), h( 'path', { key: 2, d: 'M8 8h8M8 12h8M8 16h5' } ) ] );
		},
		share: function ( p ) {
			return Svg( p || {}, [ h( 'circle', { key: 1, cx: 18, cy: 5, r: 2.5 } ), h( 'circle', { key: 2, cx: 6, cy: 12, r: 2.5 } ), h( 'circle', { key: 3, cx: 18, cy: 19, r: 2.5 } ), h( 'path', { key: 4, d: 'M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4' } ) ] );
		},
		sparkle: function ( p ) {
			return Svg( p || {}, [ h( 'path', { key: 1, d: 'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z' } ), h( 'path', { key: 2, d: 'M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z' } ) ] );
		},
	};

	function UpgradeButton( props ) {
		return h(
			'a',
			{ className: 'qcin-btn qcin-btn--primary' + ( props.small ? ' qcin-btn--small' : '' ), href: upgradeUrl( props.place ), target: '_blank', rel: 'noopener noreferrer' },
			__( 'Get WPBot Pro', 'chatbot' ),
			h( 'span', { className: 'screen-reader-text' }, __( '(opens in a new tab)', 'chatbot' ) )
		);
	}

	/** "Available in WPBot Pro" line with the upgrade button. */
	function ProFoot( props ) {
		return h(
			'div',
			{ className: 'wpbot-pro-foot' },
			h( 'span', { className: 'wpbot-pro-foot__text' }, Icons.lock( { size: 15 } ), ' ', props.text || __( 'Available in WPBot Pro.', 'chatbot' ) ),
			h( UpgradeButton, { place: props.place, small: true } )
		);
	}

	function Title( text ) {
		return h( Fragment, null, text, ' ', h( Badge ) );
	}

	/**
	 * Sample picture with a "Pro" overlay. Sample rows are marked as an
	 * example and never come from the site's data.
	 */
	function Locked( props ) {
		return h(
			'div',
			{ className: 'wpbot-pro-locked' },
			h( 'div', { className: 'wpbot-pro-locked__sample', 'aria-hidden': 'true' }, props.children ),
			h(
				'div',
				{ className: 'wpbot-pro-locked__over' },
				h( 'div', { className: 'wpbot-pro-locked__card' }, h( 'p', null, Icons.lock( { size: 16 } ), ' ', props.text ), h( UpgradeButton, { place: props.place, small: true } ) )
			)
		);
	}

	function Example() {
		return h( 'span', { className: 'wpbot-pro-example' }, __( 'Example', 'chatbot' ) );
	}

	// ---- Pro screens --------------------------------------------------

	function sampleRows( rows ) {
		return h(
			'table',
			{ className: 'qcin-table qcin-table--compact' },
			h(
				'tbody',
				null,
				rows.map( function ( r, i ) {
					return h(
						'tr',
						{ key: i },
						h( 'td', null, h( 'div', { className: 'qcin-strong' }, r[ 0 ] ), r[ 1 ] ? h( 'div', { className: 'qcin-muted' }, r[ 1 ] ) : null ),
						h( 'td', { className: 'qcin-right' }, r[ 2 ] ? h( 'span', { className: 'qcin-badge' + ( r[ 3 ] ? ' qcin-badge--' + r[ 3 ] : '' ) }, r[ 2 ] ) : null )
					);
				} )
			)
		);
	}

	function funnelBars() {
		var steps = [
			[ __( 'Product page', 'chatbot' ), 100 ],
			[ __( 'Cart', 'chatbot' ), 41 ],
			[ __( 'Checkout', 'chatbot' ), 22 ],
			[ __( 'Order received', 'chatbot' ), 14 ],
		];
		return h(
			'div',
			{ className: 'wpbot-pro-funnel' },
			steps.map( function ( s, i ) {
				return h(
					'div',
					{ key: i, className: 'wpbot-pro-funnel__step' },
					h( 'span', { className: 'wpbot-pro-funnel__label' }, s[ 0 ] ),
					h( 'span', { className: 'wpbot-pro-funnel__bar' }, h( 'span', { style: { width: s[ 1 ] + '%' } } ) ),
					h( 'span', { className: 'wpbot-pro-funnel__pct' }, s[ 1 ] + '%' )
				);
			} )
		);
	}

	var SCREENS = {
		'pro-issues': {
			icon: Icons.issues,
			title: function () {
				return __( 'Issues', 'chatbot' );
			},
			lead: function () {
				return __( 'AI turns rage clicks, dead clicks, errors and other signals into a short list of problems to fix, ranked by how many visits they affect.', 'chatbot' );
			},
			points: function () {
				return [
					__( 'Runs every night, or right away with “Find issues now”.', 'chatbot' ),
					__( 'Each issue links to the recordings that show it.', 'chatbot' ),
					__( 'Mark issues fixed or ignored. A fixed issue opens again if newer visits show it.', 'chatbot' ),
					__( 'Uses your own AI key and the cost limits in Settings.', 'chatbot' ),
				];
			},
			sample: function () {
				return sampleRows( [
					[ __( 'The “Place order” button does nothing on mobile', 'chatbot' ), __( '38 visits · /checkout/', 'chatbot' ), __( 'Dead click', 'chatbot' ), 'dead' ],
					[ __( 'Coupon field shows a script error', 'chatbot' ), __( '21 visits · /cart/', 'chatbot' ), __( 'JS error', 'chatbot' ), 'error' ],
					[ __( 'Visitors click the product photo again and again', 'chatbot' ), __( '17 visits · /shop/', 'chatbot' ), __( 'Rage click', 'chatbot' ), 'rage' ],
				] );
			},
		},
		'pro-segments': {
			icon: Icons.segments,
			title: function () {
				return __( 'Segments', 'chatbot' );
			},
			lead: function () {
				return __( 'Save groups of visitors and look at the Overview, Heatmaps and Recordings for just that group.', 'chatbot' );
			},
			points: function () {
				return [
					__( 'Build a group from device, country, traffic source and campaign, pages visited, frustration and more.', 'chatbot' ),
					__( 'For logged-in visitors: their role and whether they bought before.', 'chatbot' ),
					__( 'One segment menu on every screen; your choice stays as you move between screens.', 'chatbot' ),
					__( 'Compare two segments side by side on a heatmap.', 'chatbot' ),
				];
			},
			sample: function () {
				return sampleRows( [
					[ __( 'Mobile visitors from Google', 'chatbot' ), __( 'Device is mobile · Source is google', 'chatbot' ), __( '1,204 visits', 'chatbot' ) ],
					[ __( 'Returning customers', 'chatbot' ), __( 'Has bought before', 'chatbot' ), __( '312 visits', 'chatbot' ) ],
					[ __( 'Saw the pricing page', 'chatbot' ), __( 'Visited /pricing/', 'chatbot' ), __( '889 visits', 'chatbot' ) ],
				] );
			},
		},
		'pro-funnels': {
			icon: Icons.funnels,
			title: function () {
				return __( 'Funnels', 'chatbot' );
			},
			lead: function () {
				return __( 'See where visitors drop out between steps, for example product, cart, checkout and order received.', 'chatbot' );
			},
			points: function () {
				return [
					__( 'Steps are pages or events, counted inside one visit.', 'chatbot' ),
					__( 'Goals count the visits that reach a page or an event.', 'chatbot' ),
					__( 'Watch the recordings of visitors who left at any step.', 'chatbot' ),
					__( 'Ask AI why visitors left a step.', 'chatbot' ),
				];
			},
			sample: funnelBars,
		},
		'pro-forms': {
			icon: Icons.forms,
			title: function () {
				return __( 'Forms', 'chatbot' );
			},
			lead: function () {
				return __( 'Find the form fields that make visitors give up.', 'chatbot' );
			},
			points: function () {
				return [
					__( 'Time spent on each field and how often visitors come back to it.', 'chatbot' ),
					__( 'The last field visitors were on before they left the form.', 'chatbot' ),
					__( 'Only field names are stored, never what visitors type.', 'chatbot' ),
					__( 'Watch the recordings of visitors who left a form.', 'chatbot' ),
				];
			},
			sample: function () {
				return sampleRows( [
					[ __( 'Contact form · Phone', 'chatbot' ), __( 'Average 14 s · returned to by 22%', 'chatbot' ), __( '32% left here', 'chatbot' ), 'rage' ],
					[ __( 'Contact form · Message', 'chatbot' ), __( 'Average 41 s', 'chatbot' ), __( '11% left here', 'chatbot' ) ],
					[ __( 'Contact form · Email', 'chatbot' ), __( 'Average 6 s', 'chatbot' ), __( '4% left here', 'chatbot' ) ],
				] );
			},
		},
	};

	function screenFor( id ) {
		var def = SCREENS[ id ];
		return function ProScreen() {
			return h(
				Fragment,
				null,
				h(
					'div',
					{ className: 'qcin-toolbar' },
					h( 'div', null, h( 'h2', { className: 'qcin-page-title' }, Title( def.title() ) ), h( 'p', { className: 'qcin-muted' }, __( 'Available in WPBot Pro', 'chatbot' ) ) )
				),
				h(
					'div',
					{ className: 'qcin-card wpbot-pro-screen' },
					h(
						'div',
						{ className: 'wpbot-pro-screen__text' },
						h( 'span', { className: 'wpbot-pro-screen__icon' }, def.icon( { size: 26 } ) ),
						h( 'p', { className: 'wpbot-pro-screen__lead' }, def.lead() ),
						h(
							'ul',
							{ className: 'wpbot-pro-list' },
							def.points().map( function ( t, i ) {
								return h( 'li', { key: i }, t );
							} )
						),
						h( UpgradeButton, { place: id } )
					),
					h( 'div', { className: 'wpbot-pro-screen__sample', 'aria-hidden': 'true' }, h( Example ), def.sample() )
				)
			);
		};
	}

	wp.hooks.addFilter( 'qcin.admin.screens', NS, function ( screens ) {
		var out = Object.assign( {}, screens );
		Object.keys( SCREENS ).forEach( function ( id ) {
			out[ id ] = { render: screenFor( id ), icon: SCREENS[ id ].icon };
		} );
		return out;
	} );

	// ---- Overview cards -----------------------------------------------

	function OverviewCard( props ) {
		return h(
			'div',
			{ className: 'qcin-card wpbot-pro-card' },
			h( 'div', { className: 'qcin-card__head' }, h( 'div', null, h( 'h2', { className: 'qcin-card__title' }, Title( props.title ) ), h( 'p', { className: 'qcin-card__sub' }, props.sub ) ) ),
			h( Locked, { text: props.lock, place: props.place }, props.children )
		);
	}

	wp.hooks.addFilter( 'qcin.admin.overviewCards', NS, function ( cards ) {
		var list = ( cards || [] ).slice();
		list.push( {
			id: 'wpbot-pro-issues',
			render: function () {
				return h(
					OverviewCard,
					{ title: __( 'Top issues', 'chatbot' ), sub: __( 'Problems AI found behind your frustration signals.', 'chatbot' ), lock: __( 'AI issue finding is part of WPBot Pro.', 'chatbot' ), place: 'overview-issues' },
					SCREENS[ 'pro-issues' ].sample()
				);
			},
		} );
		if ( cfg.woo ) {
			list.push( {
				id: 'wpbot-pro-shop',
				render: function () {
					var tiles = [
						[ '2,140', __( 'Product views', 'chatbot' ) ],
						[ '877', __( 'Added to cart', 'chatbot' ) ],
						[ '471', __( 'Checkouts', 'chatbot' ) ],
						[ '300', __( 'Orders', 'chatbot' ) ],
					];
					return h(
						OverviewCard,
						{ title: __( 'Shop', 'chatbot' ), sub: __( 'WooCommerce activity from tracked visits.', 'chatbot' ), lock: __( 'WooCommerce insights are part of WPBot Pro.', 'chatbot' ), place: 'overview-shop' },
						h(
							'div',
							{ className: 'qcin-stats' },
							tiles.map( function ( t ) {
								return h( 'div', { key: t[ 1 ], className: 'qcin-stat qcin-card' }, h( 'div', { className: 'qcin-stat__value' }, t[ 0 ] ), h( 'div', { className: 'qcin-stat__label' }, t[ 1 ] ) );
							} )
						)
					);
				},
			} );
		}
		return list;
	} );

	// ---- Recordings ---------------------------------------------------

	wp.hooks.addFilter( 'qcin.admin.recordingsTools', NS, function ( tools ) {
		var list = ( tools || [] ).slice();
		list.push( {
			id: 'wpbot-pro-search',
			render: function () {
				return h(
					'div',
					{ className: 'wpbot-pro-strip' },
					h(
						'div',
						{ className: 'wpbot-pro-strip__search' },
						h( 'span', { className: 'wpbot-pro-strip__icon' }, Icons.sparkle( { size: 16 } ) ),
						h( 'label', { className: 'screen-reader-text', htmlFor: 'wpbot-pro-ask' }, __( 'Ask about your visits (WPBot Pro)', 'chatbot' ) ),
						h( 'input', { id: 'wpbot-pro-ask', type: 'search', disabled: true, placeholder: __( 'Ask about your visits, e.g. “mobile visitors who left the cart”', 'chatbot' ) } ),
						h( Badge )
					),
					h(
						'span',
						{ className: 'qcin-muted wpbot-pro-strip__more' },
						cfg.woo ? __( 'AI search, segments, shop, funnel and form filters are in WPBot Pro.', 'chatbot' ) : __( 'AI search, segments, funnel and form filters are in WPBot Pro.', 'chatbot' )
					),
					h( UpgradeButton, { place: 'recordings', small: true } )
				);
			},
		} );
		return list;
	} );

	// ---- Player -------------------------------------------------------

	wp.hooks.addFilter( 'qcin.admin.playerActions', NS, function ( actions ) {
		var list = ( actions || [] ).slice();
		list.push( {
			id: 'wpbot-pro-share',
			render: function () {
				return h(
					'a',
					{ className: 'qcin-btn qcin-btn--ghost qcin-btn--small wpbot-pro-action', href: upgradeUrl( 'player-share' ), target: '_blank', rel: 'noopener noreferrer', title: __( 'Share a link to this recording. Available in WPBot Pro.', 'chatbot' ) },
					Icons.share( { size: 15 } ),
					' ',
					__( 'Share link', 'chatbot' ),
					' ',
					h( Badge )
				);
			},
		} );
		return list;
	} );

	wp.hooks.addFilter( 'qcin.admin.playerPanels', NS, function ( panels ) {
		var list = ( panels || [] ).slice();
		list.push( {
			id: 'wpbot-pro-notes',
			render: function () {
				return h(
					'section',
					{ className: 'wpbot-pro-panel' },
					h( 'h3', null, Title( __( 'Notes and tags', 'chatbot' ) ) ),
					h( 'label', { className: 'screen-reader-text', htmlFor: 'wpbot-pro-note' }, __( 'Note (WPBot Pro)', 'chatbot' ) ),
					h( 'textarea', { id: 'wpbot-pro-note', rows: 2, disabled: true, placeholder: __( 'Add a note or tag to find this visit later', 'chatbot' ) } ),
					h( ProFoot, { place: 'player-notes' } )
				);
			},
		} );
		return list;
	} );

	// ---- Heatmaps -----------------------------------------------------

	function HeatmapModeNote( props ) {
		return h(
			'div',
			{ className: 'qcin-notice wpbot-pro-hm-note' },
			h( 'p', null, h( 'strong', null, Title( props.name ) ), ' ', props.text ),
			h( UpgradeButton, { place: 'heatmap-' + props.id, small: true } )
		);
	}

	/** Cover the page in the preview frame so the mode reads as inactive. */
	function dimPage( doc, text ) {
		try {
			var de = doc.documentElement;
			var b = doc.body || de;
			var height = Math.max( de.scrollHeight, b.scrollHeight );
			var cover = doc.createElement( 'div' );
			cover.className = 'qcin-hm-ext';
			cover.setAttribute( 'aria-hidden', 'true' );
			cover.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:' + height + 'px;background:rgba(246,245,250,0.72);z-index:2147483645;pointer-events:none;';
			var label = doc.createElement( 'div' );
			label.style.cssText = 'position:sticky;top:40%;margin:0 auto;width:max-content;max-width:80%;padding:14px 22px;border-radius:999px;background:#2d2a3a;color:#fff;font:600 22px/1.3 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center;';
			label.textContent = text;
			cover.appendChild( label );
			de.appendChild( cover );
		} catch ( e ) {}
	}

	var MODES = [
		{
			id: 'pro-attention',
			name: function () {
				return __( 'Attention', 'chatbot' );
			},
			text: function () {
				return __( 'Shows how long visitors look at each part of the page.', 'chatbot' );
			},
		},
		{
			id: 'pro-areas',
			name: function () {
				return __( 'Areas', 'chatbot' );
			},
			text: function () {
				return __( 'Draw areas on the page and compare their clicks and attention.', 'chatbot' );
			},
		},
		{
			id: 'pro-compare',
			name: function () {
				return __( 'Compare', 'chatbot' );
			},
			text: function () {
				return __( 'Two segments side by side on the same page.', 'chatbot' );
			},
		},
		{
			id: 'pro-cart',
			woo: true,
			name: function () {
				return __( 'Add to cart', 'chatbot' );
			},
			text: function () {
				return __( 'Which buttons and products lead to an add to cart.', 'chatbot' );
			},
		},
		{
			id: 'pro-ai',
			name: function () {
				return __( 'AI insight', 'chatbot' );
			},
			text: function () {
				return __( 'AI reads this heatmap and points out what to change, with the elements shown on the page.', 'chatbot' );
			},
		},
	];

	wp.hooks.addFilter( 'qcin.admin.heatmapModes', NS, function ( modes ) {
		var list = ( modes || [] ).slice();
		MODES.forEach( function ( m ) {
			if ( m.woo && ! cfg.woo ) {
				return;
			}
			list.push( {
				id: m.id,
				label: h( Fragment, null, m.name(), ' ', h( Badge ) ),
				help: m.text() + ' ' + __( 'Available in WPBot Pro.', 'chatbot' ),
				draw: function ( doc ) {
					/* translators: %s: heatmap type, e.g. "Attention" */
					dimPage( doc, wp.i18n.sprintf( __( '%s heatmap · WPBot Pro', 'chatbot' ), m.name() ) );
				},
				render: function () {
					return h( HeatmapModeNote, { id: m.id, name: m.name(), text: m.text() } );
				},
			} );
		} );
		return list;
	} );

	// ---- Settings -----------------------------------------------------

	function noop() {}

	function ProSection( props ) {
		var Section = ui().Section;
		if ( ! Section ) {
			return null;
		}
		return h(
			Section,
			{ id: props.id, title: Title( props.title ), desc: props.desc },
			h( 'fieldset', { className: 'wpbot-pro-fields', disabled: true }, h( 'legend', { className: 'screen-reader-text' }, props.title + ' (' + __( 'WPBot Pro', 'chatbot' ) + ')' ), props.children ),
			h( ProFoot, { place: props.id } )
		);
	}

	function ProToggle( props ) {
		var Toggle = ui().Toggle;
		return Toggle ? h( Toggle, { id: props.id, checked: false, onChange: noop, label: props.label, help: props.help } ) : null;
	}

	function Field( props ) {
		return h( 'div', { className: 'qcin-field' }, h( 'label', { htmlFor: props.id }, props.label ), props.children, props.help ? h( 'p', { className: 'qcin-help' }, props.help ) : null );
	}

	function AiSection() {
		var items = [
			[ 'issues', __( 'Find issues', 'chatbot' ), __( 'Every night, and when you click “Find issues now” on the Issues screen.', 'chatbot' ) ],
			[ 'heatmap', __( 'Heatmap insight', 'chatbot' ), __( 'When you open the “AI insight” heatmap for a page.', 'chatbot' ) ],
			[ 'funnel', __( 'Explain funnel drop-offs', 'chatbot' ), __( 'When you click “Why did they leave?” on a funnel step.', 'chatbot' ) ],
			[ 'search', __( 'Search recordings with a question', 'chatbot' ), __( 'When you search on the Recordings screen.', 'chatbot' ) ],
			[ 'report', __( 'AI summary in email reports', 'chatbot' ), __( 'Adds a short AI note to each email report: the top problems, what changed and three things to do next.', 'chatbot' ) ],
			[ 'masking', __( 'Privacy masking suggestions', 'chatbot' ), __( 'After 50 recordings. Only page structure is sent, never page text; nothing changes until you approve.', 'chatbot' ) ],
		];
		return h(
			ProSection,
			{ id: 'wpbot-pro-ai', title: __( 'More AI features', 'chatbot' ), desc: __( 'They use the same AI provider, key and cost limits as above.', 'chatbot' ) },
			items.map( function ( it ) {
				return h( ProToggle, { key: it[ 0 ], id: 'wpbot-pro-ai-' + it[ 0 ], label: it[ 1 ], help: it[ 2 ] } );
			} )
		);
	}

	function StorageSection() {
		var services = [ 'Amazon S3', 'Cloudflare R2', 'Backblaze B2', 'Wasabi', 'DigitalOcean Spaces', 'Google Cloud Storage', 'MinIO' ];
		return h(
			ProSection,
			{ id: 'wpbot-pro-storage', title: __( 'External storage', 'chatbot' ), desc: __( 'Keep recordings off this server: store them with a cloud storage service, and move older ones to your own cloud drive.', 'chatbot' ) },
			h(
				'div',
				{ className: 'qcin-grid-2' },
				h(
					Field,
					{ id: 'wpbot-pro-store', label: __( 'Store recordings on', 'chatbot' ) },
					h(
						'select',
						{ id: 'wpbot-pro-store', defaultValue: 'local' },
						h( 'option', { value: 'local' }, __( 'This server', 'chatbot' ) ),
						services.map( function ( s ) {
							return h( 'option', { key: s, value: s }, s );
						} )
					)
				),
				h(
					Field,
					{ id: 'wpbot-pro-archive', label: __( 'Move older recordings to', 'chatbot' ) },
					h(
						'select',
						{ id: 'wpbot-pro-archive', defaultValue: '' },
						h( 'option', { value: '' }, __( 'Do not move', 'chatbot' ) ),
						h( 'option', { value: 'gdrive' }, 'Google Drive' ),
						h( 'option', { value: 'dropbox' }, 'Dropbox' ),
						h( 'option', { value: 'onedrive' }, 'OneDrive' )
					)
				)
			),
			h(
				Field,
				{ id: 'wpbot-pro-archive-days', label: __( 'Move recordings older than', 'chatbot' ), help: __( 'Moved recordings still play from the Recordings screen.', 'chatbot' ) },
				h( 'div', { className: 'qcin-input-suffix' }, h( 'input', { id: 'wpbot-pro-archive-days', type: 'number', defaultValue: 30 } ), h( 'span', null, __( 'days', 'chatbot' ) ) )
			)
		);
	}

	function ReportsSection() {
		return h(
			ProSection,
			{ id: 'wpbot-pro-reports', title: __( 'Fuller reports, sharing and export', 'chatbot' ), desc: __( 'Add more to the email reports above, share single recordings with people who do not have a login, and export your data.', 'chatbot' ) },
			h( ProToggle, { id: 'wpbot-pro-report-more', label: __( 'Add funnels, shop totals and open issues to email reports', 'chatbot' ), help: __( 'With the top pages of the period attached as a CSV file.', 'chatbot' ) } ),
			h( ProToggle, { id: 'wpbot-pro-share', label: __( 'Allow share links for recordings', 'chatbot' ), help: __( 'A share link opens one recording without a login and stops working after 1, 7 or 30 days, or never.', 'chatbot' ) } ),
			h(
				Field,
				{ id: 'wpbot-pro-export', label: __( 'Export data', 'chatbot' ), help: __( 'Visits, pages, frustration signals, funnels or heatmap data, as CSV or JSON.', 'chatbot' ) },
				h(
					'select',
					{ id: 'wpbot-pro-export', defaultValue: 'sessions' },
					h( 'option', { value: 'sessions' }, __( 'Visits', 'chatbot' ) ),
					h( 'option', { value: 'pages' }, __( 'Pages', 'chatbot' ) ),
					h( 'option', { value: 'signals' }, __( 'Frustration signals', 'chatbot' ) )
				)
			)
		);
	}

	wp.hooks.addFilter( 'qcin.admin.settingsSections', NS, function ( sections ) {
		var list = ( sections || [] ).slice();
		list.push( { id: 'wpbot-pro-ai', render: AiSection } );
		list.push( { id: 'wpbot-pro-storage', render: StorageSection } );
		list.push( { id: 'wpbot-pro-reports', render: ReportsSection } );
		return list;
	} );
} )( window.wp, window.wpbotInsightsProPreview );
