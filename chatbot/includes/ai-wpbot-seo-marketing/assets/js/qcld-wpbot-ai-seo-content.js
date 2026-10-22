/**
 * WPBot AI Content Generator — screens (jQuery + admin-ajax).
 *
 * Analytics, Articles, Article preview, Brand Voices, Presets and Settings.
 * The article editor lives in qcld-wpbot-ai-seo-content-editor.js and registers itself
 * on QcldWpbotAiSeo.content.views.
 */
/* global jQuery, QcldWpbotAiSeo */
( function ( $, window ) {
	'use strict';

	const A = window.QcldWpbotAiSeo;
	const { __, sprintf, esc, num, api, toast, ui, charts, cfg } = A;

	/* ── Shared constants ─────────────────────────────── */

	const STATUS_LABELS = {
		draft: __( 'Draft' ),
		generating: __( 'Generating' ),
		ready: __( 'Ready' ),
		review: __( 'Review' ),
		scheduled: __( 'Scheduled' ),
		published: __( 'Published' ),
		archived: __( 'Archived' ),
	};
	const STATUS_COLORS = {
		draft: '#9e9e9e',
		generating: '#2196f3',
		ready: '#4338CA',
		review: '#ff9800',
		scheduled: '#7b61ff',
		published: '#4caf50',
		archived: '#607d8b',
	};
	const STATUS_ORDER = [ 'draft', 'generating', 'ready', 'review', 'scheduled', 'published', 'archived' ];
	const PER_PAGE = 20;

	// Tones come from the server (filterable; add-ons can add more).
	const TONES = Object.keys( cfg.tones || { professional: 'Professional' } ).map( ( value ) => ( { label: ( cfg.tones || {} )[ value ] || value, value } ) );
	const LANGUAGES = [
		[ 'en', 'English' ], [ 'bn', 'Bengali' ], [ 'es', 'Spanish' ], [ 'fr', 'French' ], [ 'de', 'German' ], [ 'hi', 'Hindi' ],
		[ 'ar', 'Arabic' ], [ 'pt', 'Portuguese' ], [ 'zh', 'Chinese' ], [ 'ja', 'Japanese' ], [ 'ko', 'Korean' ], [ 'ru', 'Russian' ],
		[ 'it', 'Italian' ], [ 'nl', 'Dutch' ], [ 'tr', 'Turkish' ], [ 'id', 'Indonesian' ], [ 'vi', 'Vietnamese' ], [ 'th', 'Thai' ],
		[ 'pl', 'Polish' ], [ 'sv', 'Swedish' ], [ 'ur', 'Urdu' ], [ 'ms', 'Malay' ],
	].map( ( l ) => ( { value: l[ 0 ], label: l[ 1 ] } ) );
	const STYLES = [
		{ label: __( 'Blog Post' ), value: 'blog_post' },
		{ label: __( 'How-To Guide' ), value: 'how_to' },
		{ label: __( 'Listicle' ), value: 'listicle' },
		{ label: __( 'Product Review' ), value: 'product_review' },
		{ label: __( 'Case Study' ), value: 'case_study' },
		{ label: __( 'Tutorial' ), value: 'tutorial' },
		{ label: __( 'Opinion Piece' ), value: 'opinion' },
		{ label: __( 'News Article' ), value: 'news' },
	];

	const statusBadge = ( status, extraStyle ) =>
		'<span class="qcld_wpbot_ai_seo-status-badge" style="background:' + esc( STATUS_COLORS[ status ] || '#9e9e9e' ) + ( extraStyle || '' ) + '">' +
		esc( STATUS_LABELS[ status ] || status ) + '</span>';

	const errMsg = ( e, fallback ) => ( e && e.message ) || fallback;

	const pageHeader = ( title, actions, left ) =>
		'<div class="qcld_wpbot_ai_seo-page-header">' +
		( left ? '<div class="qcld_wpbot_ai_seo-header-left">' + left + '</div>' : '<h2>' + title + '</h2>' ) +
		( actions ? '<div class="qcld_wpbot_ai_seo-page-header-actions">' + actions + '</div>' : '' ) + '</div>';

	const backBtn = () => ui.btn( __( 'Back' ), { variant: 'tertiary', icon: 'dashicons-arrow-left-alt2', attrs: { 'data-back': '1' } } );

	/** Turn "2026-10-15 09:30:00" into a datetime-local value. */
	const toLocalInput = ( v ) => ( v ? String( v ).replace( ' ', 'T' ).slice( 0, 16 ) : '' );
	const fromLocalInput = ( v ) => ( v ? String( v ).replace( 'T', ' ' ).slice( 0, 16 ) : '' );

	A.content = {
		views: {},
		STATUS_LABELS,
		STATUS_COLORS,
		TONES,
		LANGUAGES,
		statusBadge,
		errMsg,
		pageHeader,
		backBtn,
		toLocalInput,
	};
	const views = A.content.views;

	/* ══════════════════════════════════════════════════════
	 *  ANALYTICS
	 * ══════════════════════════════════════════════════════ */

	const ymd = ( d ) => d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );

	const buildTimeline = ( days, created, published ) => {
		const map = {};
		const today = new Date();
		today.setHours( 0, 0, 0, 0 );
		for ( let i = days - 1; i >= 0; i-- ) {
			const d = new Date( today );
			d.setDate( d.getDate() - i );
			const k = ymd( d );
			map[ k ] = { date: k, a: 0, b: 0 };
		}
		const fill = ( rows, key ) => ( rows || [] ).forEach( ( r ) => {
			const k = String( r.date || '' ).slice( 0, 10 );
			if ( ! k ) {
				return;
			}
			if ( ! map[ k ] ) {
				map[ k ] = { date: k, a: 0, b: 0 };
			}
			map[ k ][ key ] = Number( r.count ) || 0;
		} );
		fill( created, 'a' );
		fill( published, 'b' );
		return Object.values( map ).sort( ( x, y ) => x.date.localeCompare( y.date ) ).map( ( r ) => Object.assign( r, { label: r.date.slice( 5 ) } ) );
	};

	views.analytics = ( $main, params, app ) => {
		let days = 30;
		const t = app.token;

		const load = () => {
			if ( ! $main.children().length ) {
				$main.html( ui.loading( 'dashboard', __( 'Loading analytics…' ) ) );
			}
			api( 'content_analytics_overview', { days } )
				.then( ( data ) => app.alive( t ) && render( data || {} ) )
				.catch( ( e ) => app.alive( t ) && $main.html( ui.notice( 'error', e.message ) ) );
		};

		const render = ( u ) => {
			const f = u.totals || {};
			const total = Number( f.total_articles || 0 );
			const published = Number( f.total_published || 0 );
			const words = Number( f.total_words || 0 );
			const avgWords = Number( f.avg_word_count || 0 );
			const seo = Math.max( 0, Math.min( Number( f.avg_seo_score || 0 ), 100 ) );
			const read = Math.max( 0, Math.min( Number( f.avg_readability || 0 ), 100 ) );
			const pubPct = total > 0 ? ( published / total ) * 100 : 0;

			const counts = {};
			( u.status_breakdown || [] ).forEach( ( r ) => {
				counts[ r.status ] = Number( r.count ) || 0;
			} );
			const keys = STATUS_ORDER.concat( Object.keys( counts ).filter( ( k ) => STATUS_ORDER.indexOf( k ) === -1 ) );
			const sum = Object.values( counts ).reduce( ( a, b ) => a + b, 0 );
			const maxCount = Math.max( 0, ...Object.values( counts ) );
			const pipeline = keys.filter( ( k ) => ( counts[ k ] || 0 ) > 0 ).map( ( k ) => ( {
				label: STATUS_LABELS[ k ] || k,
				count: counts[ k ],
				color: STATUS_COLORS[ k ] || '#9e9e9e',
				percent: maxCount > 0 ? ( counts[ k ] / maxCount ) * 100 : 0,
			} ) );
			const ready = Number( counts.ready || 0 );
			const headline = ready > 0 ? num( ready ) : Math.round( pubPct ) + '%';
			const headCaption = ready > 0 ? __( 'Finished, not published yet' ) : __( 'Of the library is published' );

			const tones = u.top_tones || [];
			const toneMax = Math.max( 0, ...tones.map( ( x ) => Number( x.count ) || 0 ) );
			const timeline = buildTimeline( days, u.articles_over_time, u.published_over_time );
			const hasTimeline = timeline.some( ( d ) => d.a > 0 || d.b > 0 );

			const range = '<div class="qcld_wpbot_ai_seo-range-toggle" role="group" aria-label="' + esc( __( 'Analytics date range' ) ) + '">' +
				[ 7, 30, 90 ].map( ( d ) => '<button type="button" class="qcld_wpbot_ai_seo-range-toggle__btn' + ( d === days ? ' is-active' : '' ) + '" aria-pressed="' + ( d === days ) + '" data-days="' + d + '">' + esc( sprintf( __( '%dd' ), d ) ) + '</button>' ).join( '' ) +
				'</div>';

			const recent = u.recent_articles || [];
			const recentHtml = recent.length
				? '<table class="qcld_wpbot_ai_seo-table"><thead><tr><th>' + esc( __( 'Title' ) ) + '</th><th>' + esc( __( 'Status' ) ) + '</th><th class="qcld_wpbot_ai_seo-num">' + esc( __( 'Words' ) ) + '</th><th class="qcld_wpbot_ai_seo-num">' + esc( __( 'SEO' ) ) + '</th></tr></thead><tbody>' +
					recent.map( ( r ) => '<tr class="qcld_wpbot_ai_seo-clickable-row" data-edit="' + esc( r.id ) + '"><td>' + esc( r.title || __( 'Untitled' ) ) + '</td><td>' + statusBadge( r.status ) + '</td>' +
						'<td class="qcld_wpbot_ai_seo-num">' + num( r.actual_word_count ) + '</td><td class="qcld_wpbot_ai_seo-num">' + esc( r.seo_score || '—' ) + '</td></tr>' ).join( '' ) +
					'</tbody></table>'
				: ui.empty( __( 'Nothing in this window.' ), total > 0 ? __( 'No articles were created in the selected range. Widen it above, or open the full list.' ) : __( 'Generate your first article and it will appear here.' ) );

			$main.html(
				'<div class="qcld_wpbot_ai_seo-content-analytics">' +
				pageHeader( esc( __( 'Content Generator' ) ), range + ui.btn( __( 'New Article' ), { variant: 'primary', attrs: { 'data-go': 'new-article' } } ) ) +
				'<div class="qcld_wpbot_ai_seo-overview-band">' +
				ui.card( '', '<span class="qcld_wpbot_ai_seo-band-card__label">' + esc( __( 'Library' ) ) + '</span>' +
					charts.arc( pubPct, { size: 176, stroke: 12, center: '<span class="qcld_wpbot_ai_seo-arc__value-text">' + Math.round( pubPct ) + '%</span><span class="qcld_wpbot_ai_seo-arc__caption">' + esc( __( 'Published' ) ) + '</span>' } ) +
					'<div class="qcld_wpbot_ai_seo-band-chips"><div class="qcld_wpbot_ai_seo-band-chip"><span class="qcld_wpbot_ai_seo-band-chip__label">' + esc( __( 'Articles' ) ) + '</span><span class="qcld_wpbot_ai_seo-band-chip__value">' + num( total ) + '</span></div>' +
					'<div class="qcld_wpbot_ai_seo-band-chip"><span class="qcld_wpbot_ai_seo-band-chip__label">' + esc( __( 'Words written' ) ) + '</span><span class="qcld_wpbot_ai_seo-band-chip__value">' + num( words ) + '</span></div></div>',
				{ className: 'qcld_wpbot_ai_seo-band-card qcld_wpbot_ai_seo-band-card--lead' } ) +
				ui.card( '', '<span class="qcld_wpbot_ai_seo-band-card__label">' + esc( __( 'SEO' ) ) + '</span>' +
					charts.arc( seo, { center: '<span class="qcld_wpbot_ai_seo-arc__value-text">' + esc( seo || '—' ) + '</span><span class="qcld_wpbot_ai_seo-arc__caption">' + esc( __( 'Avg SEO score' ) ) + '</span>' } ) +
					'<p class="qcld_wpbot_ai_seo-band-card__hint">' + esc( seo > 0 ? __( 'Averaged across every article that has been scored.' ) : __( 'Nothing scored yet — generate an article to get a score.' ) ) + '</p>',
				{ className: 'qcld_wpbot_ai_seo-band-card' } ) +
				ui.card( '', '<span class="qcld_wpbot_ai_seo-band-card__label">' + esc( __( 'Readability' ) ) + '</span>' +
					charts.arc( read, { center: '<span class="qcld_wpbot_ai_seo-arc__value-text">' + esc( read || '—' ) + '</span><span class="qcld_wpbot_ai_seo-arc__caption">' + esc( __( 'Avg readability' ) ) + '</span>' } ) +
					'<p class="qcld_wpbot_ai_seo-band-card__hint">' + esc( avgWords > 0 ? sprintf( __( 'Across articles averaging %s words each.' ), num( avgWords ) ) : __( 'No finished articles to measure yet.' ) ) + '</p>',
				{ className: 'qcld_wpbot_ai_seo-band-card' } ) +
				'</div>' +
				'<div class="qcld_wpbot_ai_seo-charts-grid qcld_wpbot_ai_seo-charts-grid--wide-first">' +
				ui.card( __( 'Created vs Published' ), hasTimeline
					? charts.barLine( timeline, { aName: __( 'Created' ), bName: __( 'Published' ), aColor: '#A5B4FC', bColor: '#4338CA' } )
					: ui.empty( __( 'Nothing written in this window.' ), __( 'No articles were created or published in the selected range. Widen it above, or start a new article.' ) ) ) +
				ui.card( __( 'Pipeline' ), pipeline.length
					? '<div class="qcld_wpbot_ai_seo-pipeline"><div class="qcld_wpbot_ai_seo-pipeline__headline"><span class="qcld_wpbot_ai_seo-pipeline__value">' + esc( headline ) + '</span><span class="qcld_wpbot_ai_seo-pipeline__caption">' + esc( headCaption ) + '</span></div>' +
						charts.rankbar( pipeline ) + '<p class="qcld_wpbot_ai_seo-pipeline__foot">' + esc( sprintf( __( '%s articles, every status, all time.' ), num( sum ) ) ) + '</p></div>'
					: ui.empty( __( 'No articles yet.' ), __( 'Once you generate something, this shows where each article is between draft and published.' ) ) ) +
				'</div>' +
				'<div class="qcld_wpbot_ai_seo-content-split">' +
				ui.card( __( 'Popular Tones' ), tones.length
					? charts.rankbar( tones.map( ( x ) => ( { label: x.tone, count: Number( x.count ) || 0, percent: toneMax > 0 ? ( ( Number( x.count ) || 0 ) / toneMax ) * 100 : 0, color: '#4338CA' } ) ), 'qcld_wpbot_ai_seo-rankbar--tones' )
					: ui.empty( __( 'No tones recorded.' ), __( 'The tone you pick when generating an article is counted here.' ) ) ) +
				ui.card( __( 'Recent Articles' ), recentHtml, { actions: ui.btn( __( 'View All' ), { variant: 'link', attrs: { 'data-go': 'articles' } } ) } ) +
				'</div></div>'
			);
			charts.animate( $main );
		};

		$main.on( 'click', '[data-days]', function () {
			days = Number( $( this ).data( 'days' ) );
			load();
		} );
		$main.on( 'click', '[data-go]', function () {
			app.go( String( $( this ).data( 'go' ) ) );
		} );
		$main.on( 'click', '[data-edit]', function () {
			app.go( 'edit-article', { id: $( this ).data( 'edit' ) } );
		} );
		load();
	};

	/* ══════════════════════════════════════════════════════
	 *  ARTICLES
	 * ══════════════════════════════════════════════════════ */

	views.articles = ( $main, params, app ) => {
		const t = app.token;
		const st = { page: 1, search: '', status: '', items: [], total: 0, selected: [], schedule: {}, busy: 0, loaded: false };
		const editable = ( a ) => [ 'draft', 'ready', 'review', 'scheduled' ].indexOf( a.status ) !== -1;

		$main.html(
			'<div class="qcld_wpbot_ai_seo-articles-list">' +
			pageHeader( esc( __( 'Articles' ) ) + ' <span class="qcld_wpbot_ai_seo-count">(0)</span>', ui.btn( __( '+ New Article' ), { variant: 'primary', attrs: { 'data-go': 'new-article' } } ) ) +
			ui.card( '',
				'<div class="qcld_wpbot_ai_seo-bulk-actions" hidden><span class="qcld_wpbot_ai_seo-bulk-count"></span>' + ui.btn( __( 'Delete' ), { small: true, destructive: true, attrs: { 'data-bulk-delete': '1' } } ) + '</div>' +
				'<div class="qcld_wpbot_ai_seo-table-toolbar">' +
				'<input type="search" class="qcld_wpbot_ai_seo-input qcld_wpbot_ai_seo-search" placeholder="' + esc( __( 'Search articles...' ) ) + '" aria-label="' + esc( __( 'Search articles...' ) ) + '">' +
				'<select class="qcld_wpbot_ai_seo-select" data-filter-status aria-label="' + esc( __( 'Status' ) ) + '"><option value="">' + esc( __( 'All Statuses' ) ) + '</option>' +
				Object.keys( STATUS_LABELS ).map( ( k ) => '<option value="' + esc( k ) + '">' + esc( STATUS_LABELS[ k ] ) + '</option>' ).join( '' ) + '</select>' +
				'</div><div class="qcld_wpbot_ai_seo-articles-body"></div>' ) +
			'</div>'
		);
		const $body = $main.find( '.qcld_wpbot_ai_seo-articles-body' );

		const renderBulk = () => {
			$main.find( '.qcld_wpbot_ai_seo-bulk-actions' ).prop( 'hidden', ! st.selected.length ).find( '.qcld_wpbot_ai_seo-bulk-count' ).text( st.selected.length + ' ' + __( 'selected' ) );
		};

		const render = () => {
			$main.find( '.qcld_wpbot_ai_seo-count' ).text( '(' + st.total + ')' );
			if ( ! st.items.length ) {
				$body.html( '<p class="qcld_wpbot_ai_seo-empty-msg">' + esc( __( 'No articles found. Create one to get started!' ) ) + '</p>' );
				renderBulk();
				return;
			}
			const pages = Math.ceil( st.total / PER_PAGE );
			const all = st.selected.length === st.items.length;
			$body.html(
				'<table class="qcld_wpbot_ai_seo-table"><thead><tr><th style="width:32px">' + ui.checkbox( '', all, { 'data-check-all': '1', 'aria-label': __( 'Select all' ) } ) + '</th>' +
				'<th>' + esc( __( 'Title' ) ) + '</th><th>' + esc( __( 'Status' ) ) + '</th><th>' + esc( __( 'Schedule' ) ) + '</th><th>' + esc( __( 'SEO' ) ) + '</th><th>' + esc( __( 'Created' ) ) + '</th><th>' + esc( __( 'Actions' ) ) + '</th></tr></thead><tbody>' +
				st.items.map( ( a ) => {
					const sched = editable( a )
						? '<div class="qcld_wpbot_ai_seo-table-schedule-edit"><input type="datetime-local" class="qcld_wpbot_ai_seo-input" data-sched-input="' + esc( a.id ) + '" value="' + esc( st.schedule[ a.id ] !== undefined ? st.schedule[ a.id ] : toLocalInput( a.scheduled_publish_at ) ) + '">' +
							ui.btn( 'scheduled' === a.status ? __( 'Update' ) : __( 'Schedule' ), { small: true, attrs: { 'data-sched': a.id } } ) + '</div>'
						: '<span class="qcld_wpbot_ai_seo-schedule-static">' + esc( a.published_at ? sprintf( __( 'Published %s' ), fromLocalInput( a.published_at ) ) : __( 'Already published' ) ) + '</span>';
					return '<tr><td>' + ui.checkbox( '', st.selected.indexOf( String( a.id ) ) !== -1, { 'data-check': a.id, 'aria-label': __( 'Select' ) } ) + '</td>' +
						'<td class="qcld_wpbot_ai_seo-clickable-row" data-edit="' + esc( a.id ) + '">' + esc( a.title || __( 'Untitled' ) ) + '</td>' +
						'<td>' + statusBadge( a.status ) + '</td><td>' + sched + '</td><td>' + esc( a.seo_score || '—' ) + '</td><td>' + esc( ( a.created_at || '' ).split( ' ' )[ 0 ] ) + '</td>' +
						'<td><div class="qcld_wpbot_ai_seo-row-actions">' +
						ui.iconBtn( 'dashicons-edit', __( 'Edit' ), { attrs: { 'data-edit': a.id } } ) +
						ui.iconBtn( 'dashicons-visibility', __( 'Preview' ), { attrs: { 'data-preview': a.id } } ) +
						ui.iconBtn( 'dashicons-admin-page', __( 'Duplicate' ), { attrs: { 'data-dup': a.id } } ) +
						// Sends a WP draft only. Hidden for published and scheduled articles:
						// on a scheduled one it would turn the future post into a draft.
						( 'published' !== a.status && 'scheduled' !== a.status ? ui.iconBtn( 'dashicons-upload', __( 'Send to WP as draft' ), { attrs: { 'data-pub': a.id } } ) : '' ) +
						ui.iconBtn( 'dashicons-trash', __( 'Delete' ), { destructive: true, attrs: { 'data-del': a.id } } ) +
						'</div></td></tr>';
				} ).join( '' ) +
				'</tbody></table>' +
				( pages > 1
					? '<div class="qcld_wpbot_ai_seo-pagination">' + ui.btn( __( '← Prev' ), { disabled: st.page <= 1, attrs: { 'data-page': st.page - 1 } } ) +
						'<span class="qcld_wpbot_ai_seo-pagination-info">' + st.page + ' / ' + pages + ' (' + st.total + ' ' + esc( __( 'total' ) ) + ')</span>' +
						ui.btn( __( 'Next →' ), { disabled: st.page >= pages, attrs: { 'data-page': st.page + 1 } } ) + '</div>'
					: '' )
			);
			renderBulk();
		};

		const load = () => {
			if ( ! st.loaded ) {
				$body.html( ui.loading( 'table', __( 'Loading articles...' ) ) );
			}
			const q = { page: st.page, per_page: PER_PAGE };
			if ( st.search ) {
				q.search = st.search;
			}
			if ( st.status ) {
				q.status = st.status;
			}
			api( 'content_articles_list', q ).then( ( res ) => {
				if ( ! app.alive( t ) ) {
					return;
				}
				st.loaded = true;
				st.items = res.items || res.data || [];
				st.total = res.total || 0;
				st.selected = st.selected.filter( ( id ) => st.items.some( ( a ) => String( a.id ) === id ) );
				render();
			} ).catch( ( e ) => app.alive( t ) && $body.html( ui.notice( 'error', e.message ) ) );
		};

		let searchTimer = null;
		$main.on( 'input', '.qcld_wpbot_ai_seo-search', function () {
			const v = $( this ).val();
			window.clearTimeout( searchTimer );
			searchTimer = window.setTimeout( () => {
				st.search = v;
				st.page = 1;
				load();
			}, 300 );
		} );
		$main.on( 'change', '[data-filter-status]', function () {
			st.status = $( this ).val();
			st.page = 1;
			load();
		} );
		$main.on( 'click', '[data-go]', function () {
			app.go( String( $( this ).data( 'go' ) ) );
		} );
		$main.on( 'click', '[data-edit]', function () {
			app.go( 'edit-article', { id: $( this ).data( 'edit' ) } );
		} );
		$main.on( 'click', '[data-preview]', function () {
			app.go( 'preview-article', { id: $( this ).data( 'preview' ) } );
		} );
		$main.on( 'click', '[data-page]', function () {
			st.page = Number( $( this ).data( 'page' ) );
			load();
		} );
		$main.on( 'change', '[data-check-all]', function () {
			st.selected = this.checked ? st.items.map( ( a ) => String( a.id ) ) : [];
			render();
		} );
		$main.on( 'change', '[data-check]', function () {
			const id = String( $( this ).data( 'check' ) );
			st.selected = this.checked ? st.selected.concat( [ id ] ) : st.selected.filter( ( x ) => x !== id );
			$main.find( '[data-check-all]' ).prop( 'checked', st.selected.length === st.items.length );
			renderBulk();
		} );
		$main.on( 'input change', '[data-sched-input]', function () {
			st.schedule[ $( this ).data( 'sched-input' ) ] = $( this ).val();
		} );
		$main.on( 'click', '[data-sched]', function () {
			const id = String( $( this ).data( 'sched' ) );
			const a = st.items.find( ( x ) => String( x.id ) === id );
			const when = st.schedule[ id ] !== undefined ? st.schedule[ id ] : toLocalInput( a && a.scheduled_publish_at );
			if ( ! when ) {
				toast( __( 'Select a publish date and time.' ), 'error' );
				return;
			}
			if ( ! a || ! editable( a ) ) {
				toast( __( 'Published articles cannot be scheduled again. Edit the WordPress post date instead.' ), 'error' );
				return;
			}
			const $b = $( this );
			ui.busy( $b, true );
			api( 'content_article_schedule', { id, scheduled_publish_at: when } )
				.then( () => {
					toast( __( 'Schedule updated.' ), 'success' );
					load();
				} )
				.catch( ( e ) => toast( errMsg( e, __( 'Could not update the schedule. Please try again.' ) ), 'error' ) )
				.finally( () => ui.busy( $b, false ) );
		} );
		$main.on( 'click', '[data-dup]', function () {
			api( 'content_article_duplicate', { id: $( this ).data( 'dup' ) } )
				.then( () => {
					toast( __( 'Article duplicated.' ) );
					load();
				} )
				.catch( ( e ) => toast( errMsg( e, __( 'Could not duplicate article. Please try again.' ) ), 'error' ) );
		} );
		$main.on( 'click', '[data-pub]', function () {
			const $b = $( this ).prop( 'disabled', true );
			api( 'content_article_publish', { id: $( this ).data( 'pub' ), post_status: 'draft' } )
				.then( () => {
					toast( __( 'Article saved as a WordPress draft.' ) );
					load();
				} )
				.catch( ( e ) => toast( errMsg( e, __( 'Could not send article to WordPress. Please try again.' ) ), 'error' ) )
				.finally( () => $b.prop( 'disabled', false ) );
		} );
		/**
		 * Ask before deleting articles. Fetches the linked WordPress posts
		 * first so the dialog names each one and says what happens to it.
		 *
		 * @return {Promise<boolean>}
		 */
		const confirmDelete = ( ids ) =>
			api( 'content_articles_delete_preview', { ids } ).then( ( res ) => {
				const items = ( res && res.items ) || [];
				if ( ! items.length ) {
					return true; // Already gone; let the delete call report it.
				}
				const linked = items.filter( ( i ) => i.wp_post );
				const trash = !! res.trash_enabled;
				const quoted = ( t ) => '\u201c' + ( t || __( 'Untitled' ) ) + '\u201d';
				const postLine = ( i ) =>
					sprintf(
						trash ? __( 'WordPress post %1$s (%2$s) will be moved to the Trash.' ) : __( 'WordPress post %1$s (%2$s) will be left as it is.' ),
						quoted( i.wp_post.title ),
						i.wp_post.status_label
					);
				const single = 1 === items.length;
				const MAX = 10;
				const lines = single
					? linked.map( postLine )
					: linked.slice( 0, MAX ).map( ( i ) => postLine( i ) );
				if ( ! single && linked.length > MAX ) {
					lines.push( sprintf( __( '...and %d more linked posts.' ), linked.length - MAX ) );
				}
				if ( ! single && linked.length < items.length ) {
					lines.push( sprintf( __( 'Articles with no WordPress post: %d.' ), items.length - linked.length ) );
				}
				const gone = single
					? __( 'The article, its history and its saved versions are deleted permanently.' )
					: __( 'The articles, their history and their saved versions are deleted permanently.' );
				let note = gone;
				if ( linked.length && trash ) {
					note = ( 1 === linked.length ? __( 'You can restore the post from Posts \u2192 Trash.' ) : __( 'You can restore the posts from Posts \u2192 Trash.' ) ) + ' ' + gone;
				} else if ( linked.length ) {
					note = __( 'This site has no Trash (EMPTY_TRASH_DAYS is 0), so linked posts are not touched. Delete them from Posts if you no longer want them.' ) + ' ' + gone;
				}
				return A.confirmDialog( {
					title: single ? __( 'Delete Article' ) : sprintf( __( 'Delete %d Articles' ), items.length ),
					message: single
						? sprintf( __( 'Delete %s from the AI Content Generator?' ), quoted( items[ 0 ].title ) )
						: sprintf( __( 'Delete these %d articles from the AI Content Generator?' ), items.length ),
					items: lines,
					note,
					confirmLabel: linked.length && trash ? ( 1 === linked.length ? __( 'Delete and Trash Post' ) : __( 'Delete and Trash Posts' ) ) : __( 'Delete' ),
					destructive: true,
				} );
			} );

		$main.on( 'click', '[data-del]', function () {
			const id = Number( $( this ).data( 'del' ) );
			const $b = $( this ).prop( 'disabled', true );
			confirmDelete( [ id ] )
				.then( ( ok ) => {
					if ( ! ok ) {
						return;
					}
					return api( 'content_article_delete', { id } ).then( ( res ) => {
						toast( ( res && res.message ) || __( 'Article deleted.' ) );
						st.selected = st.selected.filter( ( s ) => Number( s ) !== id );
						load();
					} );
				} )
				.catch( ( e ) => toast( errMsg( e, __( 'Could not delete article. Please try again.' ) ), 'error' ) )
				.finally( () => $b.prop( 'disabled', false ) );
		} );
		$main.on( 'click', '[data-bulk-delete]', function () {
			if ( ! st.selected.length ) {
				return;
			}
			const ids = st.selected.map( Number );
			const $b = $( this );
			ui.busy( $b, true );
			confirmDelete( ids )
				.then( ( ok ) => {
					if ( ! ok ) {
						return;
					}
					return api( 'content_articles_bulk', { action: 'delete', ids } ).then( ( res ) => {
						toast( ( res && res.message ) || __( 'Articles deleted.' ) );
						st.selected = [];
						load();
					} );
				} )
				.catch( ( e ) => toast( errMsg( e, __( 'Could not delete selected articles. Please try again.' ) ), 'error' ) )
				.finally( () => ui.busy( $b, false ) );
		} );
		load();
	};

	/* ══════════════════════════════════════════════════════
	 *  PREVIEW
	 * ══════════════════════════════════════════════════════ */

	/** Allow-list HTML for the preview (the old app used DOMPurify). */
	const sanitizeHtml = ( html ) => {
		const ALLOWED = {
			A: [ 'href', 'title', 'target', 'rel' ], P: [], BR: [], STRONG: [], B: [], EM: [], I: [], U: [], S: [], DEL: [], INS: [], SUB: [], SUP: [], MARK: [],
			H1: [ 'id' ], H2: [ 'id' ], H3: [ 'id' ], H4: [ 'id' ], H5: [ 'id' ], H6: [ 'id' ], UL: [], OL: [], LI: [], BLOCKQUOTE: [], CODE: [], PRE: [], HR: [],
			TABLE: [], THEAD: [], TBODY: [], TFOOT: [], TR: [], TH: [ 'colspan', 'rowspan' ], TD: [ 'colspan', 'rowspan' ], CAPTION: [],
			IMG: [ 'src', 'alt', 'title', 'width', 'height', 'loading' ], FIGURE: [], FIGCAPTION: [], DIV: [], SPAN: [], SECTION: [], NAV: [], DETAILS: [], SUMMARY: [],
		};
		const KEEP_CLASS = /^(qcld_wpbot_ai_seo-|wp-|align|size-)/;
		const doc = new window.DOMParser().parseFromString( '<div>' + String( html || '' ) + '</div>', 'text/html' );
		const walk = ( node ) => {
			Array.from( node.childNodes ).forEach( ( child ) => {
				if ( 1 !== child.nodeType ) {
					if ( 3 !== child.nodeType ) {
						child.remove();
					}
					return;
				}
				const tag = child.tagName;
				if ( ! ALLOWED[ tag ] ) {
					if ( [ 'SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'TEXTAREA', 'BUTTON', 'SELECT', 'LINK', 'META' ].indexOf( tag ) !== -1 ) {
						child.remove();
					} else {
						walk( child );
						child.replaceWith( ...Array.from( child.childNodes ) );
					}
					return;
				}
				Array.from( child.attributes ).forEach( ( attr ) => {
					const name = attr.name.toLowerCase();
					if ( 'class' === name ) {
						const kept = attr.value.split( /\s+/ ).filter( ( c ) => KEEP_CLASS.test( c ) ).join( ' ' );
						if ( kept ) {
							child.setAttribute( 'class', kept );
						} else {
							child.removeAttribute( 'class' );
						}
						return;
					}
					if ( ALLOWED[ tag ].indexOf( name ) === -1 ) {
						child.removeAttribute( attr.name );
						return;
					}
					if ( ( 'href' === name || 'src' === name ) && ! /^(https?:|\/|#|mailto:)/i.test( attr.value.trim() ) ) {
						child.removeAttribute( attr.name );
					}
				} );
				if ( 'A' === tag && '_blank' === child.getAttribute( 'target' ) ) {
					child.setAttribute( 'rel', 'noopener noreferrer' );
				}
				walk( child );
			} );
		};
		walk( doc.body.firstChild );
		return doc.body.firstChild.innerHTML;
	};
	A.content.sanitizeHtml = sanitizeHtml;

	views[ 'preview-article' ] = ( $main, params, app ) => {
		const t = app.token;
		const id = params.id;
		$main.html( ui.loading( 'lines', __( 'Loading preview...' ) ) );
		$main.on( 'click', '[data-back]', () => app.go( 'articles' ) );
		$main.on( 'click', '[data-edit]', () => app.go( 'edit-article', { id } ) );
		api( 'content_article_get', { id } ).then( ( c ) => {
			if ( ! app.alive( t ) ) {
				return;
			}
			const kw = Array.isArray( c.keywords ) ? c.keywords : [];
			const cls = ( v, good, ok ) => ( v >= good ? 'qcld_wpbot_ai_seo-score-good' : v >= ok ? 'qcld_wpbot_ai_seo-score-ok' : 'qcld_wpbot_ai_seo-score-bad' );
			$main.html(
				'<div class="qcld_wpbot_ai_seo-article-preview">' +
				pageHeader( '', ui.btn( __( 'Edit' ), { variant: 'primary', icon: 'dashicons-edit', attrs: { 'data-edit': '1' } } ), backBtn() + '<h2>' + esc( __( 'Article Preview' ) ) + '</h2>' ) +
				'<div class="qcld_wpbot_ai_seo-preview-layout"><div class="qcld_wpbot_ai_seo-preview-main">' +
				ui.card( '',
					'<div class="qcld_wpbot_ai_seo-preview-meta-bar">' + statusBadge( c.status ) +
					( c.tone ? '<span class="qcld_wpbot_ai_seo-preview-tag">' + esc( c.tone ) + '</span>' : '' ) +
					( c.language ? '<span class="qcld_wpbot_ai_seo-preview-tag">' + esc( c.language ) + '</span>' : '' ) +
					( c.actual_word_count > 0 ? '<span class="qcld_wpbot_ai_seo-preview-tag">' + esc( c.actual_word_count ) + ' ' + esc( __( 'words' ) ) + '</span>' : '' ) + '</div>' +
					'<h1 class="qcld_wpbot_ai_seo-preview-title">' + esc( c.title || __( 'Untitled' ) ) + '</h1>' +
					( c.excerpt ? '<p class="qcld_wpbot_ai_seo-preview-excerpt">' + esc( c.excerpt ) + '</p>' : '' ) +
					( kw.length ? '<div class="qcld_wpbot_ai_seo-preview-keywords">' + kw.map( ( k ) => '<span class="qcld_wpbot_ai_seo-keyword-tag">' + esc( k ) + '</span>' ).join( '' ) + '</div>' : '' ) +
					'<hr class="qcld_wpbot_ai_seo-preview-divider"><div class="qcld_wpbot_ai_seo-preview-content">' + ( sanitizeHtml( c.content ) || '<p><em>' + esc( __( 'No content yet.' ) ) + '</em></p>' ) + '</div>'
				) +
				'</div><div class="qcld_wpbot_ai_seo-preview-sidebar">' +
				ui.card( __( 'Scores' ), '<div class="qcld_wpbot_ai_seo-scores">' +
					'<div class="qcld_wpbot_ai_seo-score-item"><span>' + esc( __( 'SEO Score' ) ) + '</span><strong class="' + cls( c.seo_score, 70, 40 ) + '">' + esc( c.seo_score || 0 ) + ' / 100</strong></div>' +
					'<div class="qcld_wpbot_ai_seo-score-item"><span>' + esc( __( 'Readability' ) ) + '</span><strong class="' + cls( c.readability_score, 60, 30 ) + '">' + esc( c.readability_score || 0 ) + ' / 100</strong></div></div>' ) +
				( c.meta_title || c.meta_description
					? ui.card( __( 'SEO Meta' ),
						( c.meta_title ? '<div class="qcld_wpbot_ai_seo-meta-field"><label>' + esc( __( 'Title' ) ) + '</label><p>' + esc( c.meta_title ) + '</p></div>' : '' ) +
						( c.meta_description ? '<div class="qcld_wpbot_ai_seo-meta-field"><label>' + esc( __( 'Description' ) ) + '</label><p>' + esc( c.meta_description ) + '</p></div>' : '' ) )
					: '' ) +
				( c.history && c.history.length
					? ui.card( __( 'History' ), '<div class="qcld_wpbot_ai_seo-history-list">' + c.history.map( ( h ) =>
						'<div class="qcld_wpbot_ai_seo-history-item"><span class="qcld_wpbot_ai_seo-history-action">' + esc( h.action ) + '</span><span class="qcld_wpbot_ai_seo-history-date">' + esc( h.created_at ) + '</span>' +
						( h.details ? '<p class="qcld_wpbot_ai_seo-history-details">' + esc( h.details ) + '</p>' : '' ) + '</div>' ).join( '' ) + '</div>' )
					: '' ) +
				( c.wp_post_id ? ui.card( __( 'WordPress Post' ), '<p>' + esc( __( 'Post ID:' ) ) + ' ' + esc( c.wp_post_id ) + '</p>' ) : '' ) +
				'</div></div></div>'
			);
		} ).catch( ( e ) => app.alive( t ) && $main.html( ui.notice( 'error', e.message ) ) );
	};

	/* ══════════════════════════════════════════════════════
	 *  PRESETS
	 * ══════════════════════════════════════════════════════ */

	const PRESET_DEFAULT = { name: '', tone: 'professional', style: 'blog_post', language: 'en', word_count: 1000, prompt_template: '', system_instructions: '' };
	const ICON_DOC = '<span class="qcld_wpbot_ai_seo-premium-modal-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM9 13h6v2H9v-2zm0 4h6v2H9v-2zm0-8h2v2H9V9z"/></svg></span>';

	const skeletonCards = ( cls, label ) =>
		'<div class="qcld_wpbot_ai_seo-presets-grid" aria-busy="true" aria-live="polite"><span class="screen-reader-text">' + esc( label ) + '</span>' +
		[ 0, 1, 2 ].map( () => ui.card( '', '<span class="qcld_wpbot_ai_seo-skeleton" style="height:15px;width:50%;margin-bottom:14px"></span>' +
			'<span class="qcld_wpbot_ai_seo-skeleton" style="height:22px;width:85%;margin-bottom:14px"></span><span class="qcld_wpbot_ai_seo-skeleton" style="height:13px;width:100%;margin-bottom:6px"></span>' +
			'<span class="qcld_wpbot_ai_seo-skeleton" style="height:13px;width:65%"></span>', { className: cls } ) ).join( '' ) + '</div>';

	views.presets = ( $main, params, app ) => {
		const t = app.token;
		// Creating presets comes from an add-on; editing existing ones is free.
		const canCreate = !! A.ext.content.canCreatePresets;
		let items = [];

		$main.html(
			'<div class="qcld_wpbot_ai_seo-presets">' +
			'<div class="qcld_wpbot_ai_seo-page-header"><h2>' + esc( __( 'Generation Presets' ) ) + '</h2>' +
			( canCreate ? ui.btn( __( 'New Preset' ), { variant: 'primary', attrs: { 'data-new': '1' } } ) : ui.proUpsell( __( 'New Preset' ), { variant: 'primary' } ) ) + '</div>' +
			'<div class="qcld_wpbot_ai_seo-presets-body">' + skeletonCards( 'qcld_wpbot_ai_seo-preset-card', __( 'Loading presets…' ) ) + '</div></div>'
		);
		const $body = $main.find( '.qcld_wpbot_ai_seo-presets-body' );

		const render = () => {
			if ( ! items.length ) {
				$body.html( ui.empty( __( 'No presets yet' ), __( 'A preset saves the tone, style, length, and prompt instructions you use most, so a new article starts from your settings instead of the defaults.' ) ) );
				return;
			}
			$body.html( '<div class="qcld_wpbot_ai_seo-presets-grid">' + items.map( ( p ) => {
				return ui.card( '',
					'<div class="qcld_wpbot_ai_seo-preset-header"><h3>' + esc( p.name ) + '</h3>' + ( Number( p.is_default ) ? '<span class="qcld_wpbot_ai_seo-badge-default">' + esc( __( 'Default' ) ) + '</span>' : '' ) + '</div>' +
					'<div class="qcld_wpbot_ai_seo-preset-details"><span>' + esc( __( 'Tone:' ) ) + ' ' + esc( p.tone ) + '</span><span>' + esc( __( 'Style:' ) ) + ' ' + esc( p.style ) + '</span><span>' + esc( __( 'Words:' ) ) + ' ' + esc( p.word_count ) + '</span></div>' +
					( p.prompt_template ? '<p class="qcld_wpbot_ai_seo-preset-template">' + esc( p.prompt_template ) + '</p>' : '' ) +
					'<div class="qcld_wpbot_ai_seo-preset-actions">' + ui.iconBtn( 'dashicons-edit', __( 'Edit' ), { attrs: { 'data-edit': p.id } } ) +
					( ! Number( p.is_default ) ? ui.iconBtn( 'dashicons-trash', __( 'Delete' ), { destructive: true, attrs: { 'data-del': p.id } } ) : '' ) + '</div>',
					{ className: 'qcld_wpbot_ai_seo-preset-card' } );
			} ).join( '' ) + '</div>' );
		};

		const load = () => api( 'content_presets_list', {} ).then( ( res ) => {
			if ( app.alive( t ) ) {
				items = res || [];
				render();
			}
		} ).catch( ( e ) => app.alive( t ) && $body.html( ui.notice( 'error', e.message ) ) );

		const openForm = ( preset ) => {
			const p = Object.assign( {}, PRESET_DEFAULT, preset );
			const sel = ( field, opts, value ) => '<select class="qcld_wpbot_ai_seo-premium-select" data-field="' + field + '">' + opts.map( ( o ) => '<option value="' + esc( o.value ) + '"' + ( o.value === value ? ' selected' : '' ) + '>' + esc( o.label ) + '</option>' ).join( '' ) + '</select>';
			const $m = A.modal( {
				title: p.id ? __( 'Edit Preset' ) : __( 'New Preset' ),
				icon: ICON_DOC,
				body:
					'<div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'Name' ) ) + ' *</label><input type="text" class="qcld_wpbot_ai_seo-premium-input" data-field="name" placeholder="' + esc( __( 'Enter preset name' ) ) + '" value="' + esc( p.name ) + '"></div>' +
					'<div class="qcld_wpbot_ai_seo-premium-form-row"><div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'Tone' ) ) + '</label>' + sel( 'tone', TONES, p.tone ) + '</div>' +
					'<div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'Style' ) ) + '</label>' + sel( 'style', STYLES, p.style ) + '</div></div>' +
					'<div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'Default Word Count' ) ) + '</label><input type="number" class="qcld_wpbot_ai_seo-premium-input" data-field="word_count" value="' + esc( p.word_count ) + '"></div>' +
					'<div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'Prompt Template' ) ) + '</label><textarea class="qcld_wpbot_ai_seo-premium-textarea" rows="4" data-field="prompt_template" placeholder="' + esc( __( 'Additional instructions appended to the AI prompt.' ) ) + '">' + esc( p.prompt_template ) + '</textarea>' +
					'<span class="qcld_wpbot_ai_seo-premium-form-help">' + esc( __( 'Additional instructions appended to the AI prompt.' ) ) + '</span></div>' +
					'<div class="qcld_wpbot_ai_seo-premium-form-group"><label class="qcld_wpbot_ai_seo-premium-form-label">' + esc( __( 'System Instructions' ) ) + '</label><textarea class="qcld_wpbot_ai_seo-premium-textarea" rows="4" data-field="system_instructions" placeholder="' + esc( __( 'Custom system-level instructions for the AI model.' ) ) + '">' + esc( p.system_instructions ) + '</textarea>' +
					'<span class="qcld_wpbot_ai_seo-premium-form-help">' + esc( __( 'Custom system-level instructions for the AI model.' ) ) + '</span></div>',
				footer: '<button type="button" class="qcld_wpbot_ai_seo-btn-cancel" data-modal-cancel>' + esc( __( 'Cancel' ) ) + '</button>' +
					'<button type="button" class="qcld_wpbot_ai_seo-btn-primary" data-save' + ( p.name ? '' : ' disabled' ) + '>' + esc( p.id ? __( 'Update Preset' ) : __( 'Create Preset' ) ) + '</button>',
			} );
			$m.on( 'input', '[data-field="name"]', function () {
				$m.find( '[data-save]' ).prop( 'disabled', ! $( this ).val().trim() );
			} );
			$m.find( '[data-save]' ).on( 'click', function () {
				const data = Object.assign( {}, p );
				$m.find( '[data-field]' ).each( function () {
					data[ $( this ).data( 'field' ) ] = $( this ).val();
				} );
				data.word_count = parseInt( data.word_count, 10 ) || 1000;
				const $b = $( this );
				ui.busy( $b, true );
				( p.id ? api( 'content_preset_update', data ) : api( 'content_preset_create', data ) )
					.then( () => {
						toast( p.id ? __( 'Preset updated.' ) : __( 'Preset created.' ) );
						$m.trigger( 'qcld:close' );
						load();
					} )
					.catch( ( e ) => {
						toast( e.message, 'error' );
						ui.busy( $b, false );
					} );
			} );
		};

		$main.on( 'click', '[data-new]', () => canCreate && openForm( {} ) );
		$main.on( 'click', '[data-edit]', function () {
			const p = items.find( ( x ) => String( x.id ) === String( $( this ).data( 'edit' ) ) );
			if ( ! p ) {
				return;
			}
			openForm( p );
		} );
		$main.on( 'click', '[data-del]', function () {
			const id = $( this ).data( 'del' );
			A.confirmDialog( { title: __( 'Delete Preset' ), message: __( 'Are you sure you want to delete this preset?' ), confirmLabel: __( 'Delete' ), destructive: true } ).then( ( ok ) => {
				if ( ! ok ) {
					return;
				}
				api( 'content_preset_delete', { id } ).then( () => {
					toast( __( 'Preset deleted.' ) );
					load();
				} ).catch( ( e ) => toast( e.message, 'error' ) );
			} );
		} );
		load();
	};

	/* ══════════════════════════════════════════════════════
	 *  BRAND VOICES (Pro add-on; upsell here)
	 * ══════════════════════════════════════════════════════ */

	views[ 'brand-voices' ] = ( $main ) => {
		$main.html(
			'<div class="qcld_wpbot_ai_seo-brand-voices">' +
			'<div class="qcld_wpbot_ai_seo-page-header"><h2 class="qcld_wpbot_ai_seo-pro-card-header">' + esc( __( 'Brand Voices' ) ) + ui.proBadge() + '</h2></div>' +
			ui.empty( __( 'Brand voices are available in Pro' ), __( 'A brand voice pins the tone, style, and phrasing to avoid, so every article sounds like the same company. Brand voices come with the WPBot AI SEO Pro add-on.' ) ) +
			'<p>' + ui.upgradeBtn( __( 'Get Pro' ) ) + '</p></div>'
		);
	};

	/* ══════════════════════════════════════════════════════
	 *  SETTINGS
	 * ══════════════════════════════════════════════════════ */

	views.settings = ( $main, params, app ) => {
		const t = app.token;
		// On/off settings registered by an add-on: [ field, label, help ].
		const extToggles = A.ext.content.settingsToggles || [];
		let s = null;
		let tax = { categories: [], post_types: [] };
		let tab = [ 'generation', 'publishing', 'images' ].indexOf( String( params.id ) ) !== -1 ? String( params.id ) : 'generation';

		$main.html( '<div class="qcld_wpbot_ai_seo-content-settings"><div class="qcld_wpbot_ai_seo-settings-notice"></div><h2>' + esc( __( 'Content Settings' ) ) + '</h2>' +
			ui.card( '', ui.loading( 'form', __( 'Loading settings...' ) ), { className: 'qcld_wpbot_ai_seo-settings-card' } ) + '</div>' );
		const $card = $main.find( '.qcld_wpbot_ai_seo-settings-card .qcld_wpbot_ai_seo-card-body' );

		const saveBtn = () => '<div class="qcld_wpbot_ai_seo-settings-actions">' + ui.btn( __( 'Save Settings' ), { variant: 'primary', attrs: { 'data-save': '1' } } ) + '</div>';

		const panels = {
			generation: () =>
				'<div class="qcld_wpbot_ai_seo-settings-form"><div class="qcld_wpbot_ai_seo-form-grid qcld_wpbot_ai_seo-form-grid-2">' +
				ui.select( 'default_tone', __( 'Default Tone' ), s.default_tone || 'professional', TONES ) +
				ui.select( 'default_language', __( 'Default Language' ), s.default_language || 'en', LANGUAGES ) +
				ui.text( 'default_word_count', __( 'Default Word Count' ), s.default_word_count || 1000, { type: 'number' } ) +
				'</div><div class="qcld_wpbot_ai_seo-settings-section-head"><h4>' + esc( __( 'Automation & Content Intelligence' ) ) + '</h4><p>' + esc( __( 'Automatic meta, excerpt, SEO scoring and internal links after generation.' ) ) + '</p></div>' +
				( extToggles.length
					? '<div class="qcld_wpbot_ai_seo-automation-toggles">' + extToggles.map( ( r ) => '<div class="qcld_wpbot_ai_seo-toggle-card">' + ui.toggle( r[ 0 ], r[ 1 ], false !== s[ r[ 0 ] ], { help: r[ 2 ] } ) + '</div>' ).join( '' ) + '</div>'
					: '<div class="qcld_wpbot_ai_seo-pro-inline-action qcld_wpbot_ai_seo-settings-pro">' + ui.proLabel( __( 'Automation Settings' ) ) + ui.upgradeBtn() + '</div>' ) +
				saveBtn() + '</div>',
			publishing: () => {
				const types = ( tax.post_types || [] ).map( ( p ) => ( { label: p.label, value: p.name } ) );
				const cats = [ { label: __( 'No default category' ), value: '0' } ].concat( ( tax.categories || [] ).map( ( c ) => ( { label: c.name, value: String( c.id ) } ) ) );
				return '<div class="qcld_wpbot_ai_seo-settings-form"><div class="qcld_wpbot_ai_seo-form-grid qcld_wpbot_ai_seo-form-grid-2">' +
					ui.select( 'default_post_type', __( 'Default Post Type' ), s.default_post_type || 'post', types.length ? types : [ { label: 'Post', value: 'post' } ] ) +
					ui.select( 'default_post_status', __( 'Default Post Status' ), s.default_post_status || 'draft', [
						{ label: __( 'Draft' ), value: 'draft' }, { label: __( 'Publish' ), value: 'publish' }, { label: __( 'Pending Review' ), value: 'pending' }, { label: __( 'Private' ), value: 'private' },
					], { help: __( 'Used by the default publish action in the article editor.' ) } ) +
					ui.select( 'default_category_id', __( 'Default Category' ), String( s.default_category_id || 0 ), cats ) +
					'</div>' + saveBtn() + '</div>';
			},
			images: () => {
				const provider = s.stock_provider || 'pexels';
				const name = 'pixabay' === provider ? 'Pixabay' : 'Pexels';
				const active = 'pixabay' === provider ? !! s.has_pixabay_key : !! s.has_pexels_key;
				const anyKey = !! s.has_pexels_key || !! s.has_pixabay_key;
				return '<div class="qcld_wpbot_ai_seo-settings-form">' +
					'<div class="qcld_wpbot_ai_seo-stock-keys' + ( active ? ' is-active' : '' ) + '"><div class="qcld_wpbot_ai_seo-stock-keys__head"><strong>' + esc( __( 'Stock Photo API Keys' ) ) + '</strong>' +
					'<span class="qcld_wpbot_ai_seo-key-pill ' + ( active ? 'is-on' : anyKey ? 'is-partial' : '' ) + '">' + esc( active ? sprintf( __( '✓ %s Key Active' ), name ) : __( 'Key Not Set' ) ) + '</span></div>' +
					'<p>' + esc( __( 'Add a free Pexels or Pixabay API key to search and insert stock photos in your articles. Keys are stored encrypted and never shown again after saving.' ) ) + '</p>' +
					'<div class="qcld_wpbot_ai_seo-form-grid qcld_wpbot_ai_seo-form-grid--top qcld_wpbot_ai_seo-form-grid-2">' +
					ui.select( 'stock_provider', __( 'Stock Photo Provider' ), provider, [ { label: 'Pexels', value: 'pexels' }, { label: 'Pixabay', value: 'pixabay' } ], { help: __( 'Used for stock photo search and in-body images.' ) } ) + '<div></div>' +
					ui.text( 'pexels_api_key', __( 'Pexels API Key' ), '', { type: 'password', autocomplete: 'new-password', placeholder: s.has_pexels_key ? __( 'Key saved — enter a new key to replace it' ) : __( 'Paste your free Pexels API key' ),
						help: '<a href="https://www.pexels.com/api/" target="_blank" rel="noreferrer">' + esc( __( 'Get a free Pexels API key →' ) ) + '</a>', htmlHelp: true } ) +
					ui.text( 'pixabay_api_key', __( 'Pixabay API Key' ), '', { type: 'password', autocomplete: 'new-password', placeholder: s.has_pixabay_key ? __( 'Key saved — enter a new key to replace it' ) : __( 'Paste your free Pixabay API key' ),
						help: '<a href="https://pixabay.com/api/docs/" target="_blank" rel="noreferrer">' + esc( __( 'Get a free Pixabay API key →' ) ) + '</a>', htmlHelp: true } ) +
					'</div></div>' +
					'<div class="qcld_wpbot_ai_seo-form-grid qcld_wpbot_ai_seo-form-grid--top qcld_wpbot_ai_seo-form-grid-2">' +
					ui.select( 'inline_images', __( 'In-body Stock Images' ), String( s.inline_images || 0 ), [ { label: __( 'None' ), value: '0' }, { label: __( '1 image' ), value: '1' }, { label: __( '2 images' ), value: '2' }, { label: __( '3 images' ), value: '3' } ], { help: __( 'AI places relevant stock photos inside generated article text.' ) } ) +
					ui.select( 'inline_image_size', __( 'In-body Image Size' ), s.inline_image_size || 'large', [ { label: __( 'Medium (300px)' ), value: 'medium' }, { label: __( 'Medium Large (768px)' ), value: 'medium_large' }, { label: __( 'Large (1024px)' ), value: 'large' }, { label: __( 'Full Size' ), value: 'full' } ], { help: __( 'Display size of stock photos embedded inside articles.' ) } ) +
					ui.select( 'image_orientation', __( 'Image Orientation' ), s.image_orientation || 'landscape', [ { label: __( 'Landscape (recommended)' ), value: 'landscape' }, { label: __( 'Any orientation' ), value: 'any' } ], { help: __( 'Landscape keeps auto-picked photos wide. Portraits can render as huge vertical blocks.' ) } ) +
					ui.select( 'image_reuse_days', __( 'Avoid Reusing Images Within' ), String( undefined !== s.image_reuse_days && null !== s.image_reuse_days ? s.image_reuse_days : '60' ), [ { label: __( '30 days' ), value: '30' }, { label: __( '60 days (recommended)' ), value: '60' }, { label: __( '90 days' ), value: '90' }, { label: __( 'Allow repeats' ), value: '0' } ], { help: __( 'Auto-pick skips stock photos used within this window so daily posts stay varied.' ) } ) +
					'</div>' + saveBtn() + '</div>';
			},
		};

		const TABS = [ [ 'generation', __( 'Generation' ) ], [ 'publishing', __( 'Publishing' ) ], [ 'images', __( 'Images' ) ] ];
		const render = () => {
			$card.html(
				'<div class="qcld_wpbot_ai_seo-tabs" role="tablist">' + TABS.map( ( x ) => '<button type="button" role="tab" class="qcld_wpbot_ai_seo-tabs__tab' + ( x[ 0 ] === tab ? ' is-active' : '' ) + '" aria-selected="' + ( x[ 0 ] === tab ) + '" data-tab="' + x[ 0 ] + '">' + esc( x[ 1 ] ) + '</button>' ).join( '' ) + '</div>' +
				'<div class="qcld_wpbot_ai_seo-tabs__panel" role="tabpanel">' + panels[ tab ]() + '</div>'
			);
		};

		const INTS = [ 'default_word_count', 'default_category_id', 'inline_images', 'image_reuse_days' ];
		$main.on( 'click', '[data-tab]', function () {
			tab = String( $( this ).data( 'tab' ) );
			render();
		} );
		$main.on( 'change input', '[data-field]', function () {
			const field = $( this ).data( 'field' );
			let value = 'checkbox' === this.type ? this.checked : $( this ).val();
			if ( 'default_word_count' === field ) {
				value = parseInt( value, 10 ) || 1000;
			} else if ( INTS.indexOf( field ) !== -1 ) {
				value = parseInt( value, 10 ) || 0;
			} else if ( ( 'pexels_api_key' === field || 'pixabay_api_key' === field ) && ! value ) {
				delete s[ field ];
				return;
			}
			s[ field ] = value;
			if ( 'stock_provider' === field ) {
				render();
			}
		} );
		$main.on( 'click', '[data-save]', function () {
			const $b = $( this );
			ui.busy( $b, true, __( 'Saving...' ) );
			$main.find( '.qcld_wpbot_ai_seo-settings-notice' ).empty();
			api( 'content_settings_save', s )
				.then( ( res ) => {
					s = ( res && res.settings ) || s;
					delete s.pexels_api_key;
					delete s.pixabay_api_key;
					render();
					$main.find( '.qcld_wpbot_ai_seo-settings-notice' ).html( ui.notice( 'success', __( 'Settings saved.' ) ) );
				} )
				.catch( ( e ) => toast( e.message, 'error' ) )
				.finally( () => ui.busy( $b, false ) );
		} );

		Promise.all( [ api( 'content_settings_get', {} ), api( 'content_wp_taxonomies', {} ) ] )
			.then( ( r ) => {
				if ( ! app.alive( t ) ) {
					return;
				}
				s = r[ 0 ] || {};
				tax = r[ 1 ] || tax;
				render();
			} )
			.catch( ( e ) => app.alive( t ) && $main.find( '.qcld_wpbot_ai_seo-settings-notice' ).html( ui.notice( 'error', e.message ) ) );
	};
}( jQuery, window ) );
