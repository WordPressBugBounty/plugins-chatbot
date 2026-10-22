/**
 * WPBot AI Content Generator — article editor (new / edit).
 *
 * AI generation, outline, TinyMCE content, SEO meta, WordPress settings,
 * featured image (stock / media library), save, schedule and publish.
 * Pro controls come from add-ons through QcldWpbotAiSeo.ext.content.editor;
 * without one they render as "available in Pro" notices.
 */
/* global jQuery, QcldWpbotAiSeo, wp, tinymce */
( function ( $, window ) {
	'use strict';

	const A = window.QcldWpbotAiSeo;
	const C = A.content;
	const { __, esc, api, toast, ui, cfg } = A;
	const X = A.ext.content.editor;

	const EDITOR_ID = 'qcld_wpbot_ai_seo-tinymce-editor';
	const PREFILL_KEY = 'qcld_wpbot_ai_seo_prefill_article';

	const DEFAULTS = {
		title: '', topic: '', keywords: [], tone: 'professional', language: 'en', word_count_target: 1000,
		content: '', excerpt: '', outline: '', meta_title: '', meta_description: '', categories: [], tags: [],
		featured_image_url: '', featured_image_id: 0, post_type: 'post', default_post_status: 'draft',
		brand_voice_id: '', serp_outline_context: '', include_table_of_contents: false, include_quick_answer: true,
		include_faq: true, scheduled_publish_at: '', preset_id: '', status: 'draft',
	};

	const HUMANIZE_FREE = 'Lightly humanize this content: make it sound more natural and conversational. CRITICAL RULES: 1) Preserve the entire article from the very beginning — keep all opening intro paragraphs. 2) Keep ALL sections including the conclusion — do NOT remove any sections. 3) Keep approximately the same word count. 4) Keep the same HTML formatting and structure. 5) Copy all placeholder markers (like [[AI_WPBOT_...]]) in their exact places. Return ONLY the improved HTML content.';

	const ICON_STOCK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h3l1.5-2h9L18 8h3v11H3z"></path><circle cx="12" cy="13" r="3.5"></circle></svg>';
	const ICON_AI = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"></path><path d="M18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9z"></path></svg>';
	const ICON_UPLOAD = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"></path><path d="M8 8l4-4 4 4"></path><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"></path></svg>';

	const readPrefill = () => {
		try {
			const raw = window.sessionStorage.getItem( PREFILL_KEY );
			if ( ! raw ) {
				return null;
			}
			window.sessionStorage.removeItem( PREFILL_KEY );
			return JSON.parse( raw );
		} catch ( e ) {
			return null;
		}
	};

	const wpEditor = () => ( window.wp && ( window.wp.oldEditor || window.wp.editor ) ) || null;
	const mce = () => ( window.tinymce && window.tinymce.get( EDITOR_ID ) ) || null;

	const editorView = ( $main, params, app ) => {
		const t = app.token;
		const id = params.id || 0;
		// ID of the saved article. Starts as the route ID; set as soon as a new
		// article is created (publish / schedule / generate from a new draft),
		// so a retry after a failure updates that article instead of creating
		// another one.
		let savedId = id;
		const rememberId = ( newId ) => {
			if ( ! newId || savedId ) {
				return;
			}
			savedId = Number( newId );
			// Keep a reload on this article without re-rendering the view.
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#edit-article/' + savedId );
			}
		};
		const aiOn = !! cfg.aiConfigured;
		const aiOff = ( cfg.aiPaused || [] ).length
			? __( 'AI providers are paused after an error. See the notice for details and the reset button.' )
			: __( 'Please set up an AI provider API key in Settings first.' );

		const k = Object.assign( {}, DEFAULTS );
		let presets = [];
		let tax = { categories: [], tags: [], post_types: [] };
		let vault = [];
		let analysis = null;
		let imageTab = 'stock';
		let stockReady = null;
		let stockResults = [];
		let stockSearched = false;
		let stockError = '';
		let stockQuery = '';
		let showThinkingHint = true;
		let showSchedule = false;
		let busy = { generating: false, outline: false, meta: false, humanize: false, saving: false, scheduling: false, publishing: '', image: false, importing: '', searching: false };
		let keywordField = null;
		let tagField = null;

		/**
		 * Context handed to add-on hooks (QcldWpbotAiSeo.ext.content.editor).
		 * Functions are wrapped so they resolve at call time.
		 */
		const ctx = {
			k,
			busy,
			$main,
			app,
			aiOn,
			aiOff,
			id: () => savedId,
			alive: () => app.alive( t ),
			region: ( name ) => region( name ),
			currentContent: () => currentContent(),
			setContent: ( html ) => setContent( html ),
			validateAll: () => validateAll(),
			ensureSaved: ( extra, msg ) => ensureSaved( extra, msg ),
			render: {
				all: () => render(),
				gen: () => renderGenActions(),
				outline: () => renderOutline(),
				extras: () => renderContentExtras(),
				image: () => renderImageTabs(),
				featured: () => renderFeatured(),
			},
		};

		$main.html( ui.loading( 'form', __( 'Loading editor...' ) ) );

		/* ── State helpers ─────────────────────────── */

		const set = ( field, value ) => {
			k[ field ] = value;
			return true;
		};

		const currentContent = () => {
			const ed = mce();
			if ( ed && ! ed.isHidden() ) {
				return ed.getContent();
			}
			const $ta = $( '#' + EDITOR_ID );
			return $ta.length ? $ta.val() : k.content;
		};

		const setContent = ( html ) => {
			k.content = html || '';
			const ed = mce();
			if ( ed ) {
				ed.setContent( k.content );
			}
			$( '#' + EDITOR_ID ).val( k.content );
			renderContentExtras();
		};

		const validateTopic = () => {
			const errs = {};
			if ( ! String( k.topic ).trim() ) {
				errs.topic = __( 'Topic / Subject is required.' );
			}
			showErrors( errs );
			return ! Object.keys( errs ).length;
		};
		const validateAll = () => {
			const errs = {};
			if ( ! String( k.topic ).trim() ) {
				errs.topic = __( 'Topic / Subject is required.' );
			}
			if ( ! k.keywords || ! k.keywords.length ) {
				errs.keywords = __( 'Add at least one keyword.' );
			}
			showErrors( errs );
			return ! Object.keys( errs ).length;
		};
		const showErrors = ( errs ) => {
			[ 'topic', 'keywords' ].forEach( ( f ) => {
				const $w = $main.find( '[data-errwrap="' + f + '"]' );
				$w.toggleClass( 'has-error', !! errs[ f ] );
				$w.find( '.qcld_wpbot_ai_seo-field-error' ).text( errs[ f ] || '' ).prop( 'hidden', ! errs[ f ] );
			} );
		};

		const payload = ( extra ) => {
			k.content = currentContent();
			let outline = k.outline || [];
			if ( 'string' === typeof outline ) {
				try {
					outline = JSON.parse( outline );
				} catch ( e ) {
					outline = [];
				}
			}
			let p = Object.assign( {}, k, {
				category_ids: ( k.categories || [] ).map( Number ),
				tag_ids: k.tags,
				outline,
				featured_image_url: k.featured_image_url || '',
				featured_image_id: k.featured_image_id || 0,
			}, extra || {} );
			delete p.categories;
			delete p.tags;
			delete p.category_ids_raw;
			delete p.tag_ids_raw;
			X.payload.forEach( ( fn ) => {
				p = fn( p, ctx ) || p;
			} );
			return p;
		};

		/**
		 * Save what is in the editor right now, then resolve with the article ID.
		 * Publish and schedule read the article from the database, so they must
		 * always save first: an existing article is updated with the current
		 * payload, a new one is created.
		 */
		const ensureSaved = ( extra, failMsg ) => {
			if ( savedId ) {
				return api( 'content_article_update', Object.assign( payload( extra ), { id: savedId } ) ).then( () => savedId );
			}
			return api( 'content_article_create', payload( extra ) ).then( ( res ) => {
				if ( ! res || ! res.id ) {
					throw new Error( failMsg );
				}
				rememberId( res.id );
				return savedId;
			} );
		};

		/* ── Layout ─────────────────────────────────── */

		const toneOptions = C.TONES;
		const hook = ( list ) => list.map( ( fn ) => fn( ctx ) || '' ).join( '' );

		const render = () => {
			const presetOpts = [ { label: __( '— No preset —' ), value: '' } ].concat( presets.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ) );
			const typeOpts = ( tax.post_types || [] ).map( ( p ) => ( { label: p.label, value: p.name } ) );

			$main.html(
				'<div class="qcld_wpbot_ai_seo-article-editor">' +
				'<div class="qcld_wpbot_ai_seo-page-header"><div class="qcld_wpbot_ai_seo-header-left">' + C.backBtn() + '<h2>' + esc( id ? __( 'Edit Article' ) : __( 'New Article' ) ) + '</h2><span data-region="status"></span></div>' +
				'<div class="qcld_wpbot_ai_seo-page-header-actions" data-region="actions"></div></div>' +
				'<div class="qcld_wpbot_ai_seo-content-editor-grid"><div class="qcld_wpbot_ai_seo-content-editor-main">' +

				ui.card( __( 'AI Generation' ),
					'<div class="qcld_wpbot_ai_seo-form-field" data-errwrap="topic">' + ui.text( 'topic', __( 'Topic / Subject' ) + ' *', k.topic, { placeholder: __( 'e.g., Best SEO practices for 2025' ) } ) +
					'<span class="qcld_wpbot_ai_seo-field-error" hidden></span></div>' +
					'<div class="qcld_wpbot_ai_seo-form-field" data-errwrap="keywords"><div data-mount="keywords"></div><span class="qcld_wpbot_ai_seo-field-error" hidden></span></div>' +
					'<div class="qcld_wpbot_ai_seo-gen-controls">' +
					ui.select( 'tone', __( 'Tone' ), k.tone, toneOptions ) +
					ui.select( 'language', __( 'Language' ), k.language, C.LANGUAGES ) +
					'<div class="qcld_wpbot_ai_seo-field qcld_wpbot_ai_seo-range"><label class="qcld_wpbot_ai_seo-field__label">' + esc( __( 'Word Count Target' ) ) + '</label><div class="qcld_wpbot_ai_seo-range__row">' +
					'<input type="range" data-field="word_count_target" min="300" max="5000" step="100" value="' + esc( k.word_count_target ) + '" aria-label="' + esc( __( 'Word Count Target' ) ) + '">' +
					'<input type="number" class="qcld_wpbot_ai_seo-input" data-field="word_count_target" min="300" max="5000" step="100" value="' + esc( k.word_count_target ) + '" aria-label="' + esc( __( 'Word Count Target' ) ) + '"></div></div>' +
					ui.select( 'preset_id', __( 'Preset' ), String( k.preset_id || '' ), presetOpts ) +
					( X.fields.length ? hook( X.fields ) : ui.select( 'brand_voice_id', ui.proLabel( __( 'Brand Voice' ) ), '', [ { label: __( 'Default / None' ), value: '' } ], { disabled: true, htmlLabel: true } ) ) +
					'</div>' +
					'<div class="qcld_wpbot_ai_seo-structure-toggles">' +
					( X.toggles.length ? hook( X.toggles ) : ui.toggle( 'include_table_of_contents', ui.proLabel( __( 'Table of Contents' ) ), false, { help: __( 'Add a linked table of contents before the first main section.' ), disabled: true, htmlLabel: true } ) ) +
					ui.toggle( 'include_quick_answer', __( 'Quick Answer Box (GEO)' ), false !== k.include_quick_answer, { help: __( 'Add direct-answer key takeaways callout for Generative Engine Optimization.' ) } ) +
					ui.toggle( 'include_faq', __( 'FAQ Section (AEO)' ), false !== k.include_faq, { help: __( 'Include structured Q&As for Answer Engine Optimization.' ) } ) +
					'</div>' +
					ui.textarea( 'serp_outline_context', __( 'SERP / Brief Context for Outline' ), k.serp_outline_context || '', { rows: 3, help: __( 'Optional: paste SEO brief notes, SERP questions, competitor gaps, or search intent. This is sent to outline/article generation.' ) } ) +
					'<div class="qcld_wpbot_ai_seo-gen-actions" data-region="gen-actions"></div>' +
					'<p class="qcld_wpbot_ai_seo-field-error qcld_wpbot_ai_seo-gen-error" data-region="gen-error" hidden></p>' +
					ui.aiNotice() +
					'<p class="qcld_wpbot_ai_seo-ai-quality-hint">' + esc( __( 'ℹ Content length and quality depend on your AI model. Larger models produce more accurate word counts and higher-quality content.' ) ) + '</p>'
				) +
				'<div data-region="outline"></div>' +
				ui.card( '', ui.text( 'title', __( 'Article Title' ), k.title, { placeholder: __( 'Enter article title...' ), className: 'qcld_wpbot_ai_seo-title-input' } ) +
					'<p class="qcld_wpbot_ai_seo-field-hint" data-region="title-hint"' + ( k.title ? ' hidden' : '' ) + '>' + esc( __( 'A title will be auto-generated by AI, or you can type one manually.' ) ) + '</p>' ) +
				ui.card( __( 'Content' ),
					'<div class="qcld_wpbot_ai_seo-generating-overlay" data-region="writing" hidden><span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span><p>' + esc( __( 'AI is writing your article...' ) ) + '</p></div>' +
					'<div class="qcld_wpbot_ai_seo-tinymce-wrap" data-region="editor"></div>' +
					'<div data-region="content-extras"></div>'
				) +
				ui.card( '', ui.textarea( 'excerpt', __( 'Excerpt' ), k.excerpt || '', { rows: 3, placeholder: __( 'Short summary (auto-generated with article)' ) } ) ) +
				'</div>' +

				'<div class="qcld_wpbot_ai_seo-content-editor-side">' +
				ui.card( __( 'SEO & Meta' ),
					ui.text( 'meta_title', __( 'Meta Title' ), k.meta_title || '', { placeholder: __( 'SEO title (max 60 chars)' ) } ) +
					'<span class="qcld_wpbot_ai_seo-char-count" data-count="meta_title"></span>' +
					ui.textarea( 'meta_description', __( 'Meta Description' ), k.meta_description || '', { rows: 3, placeholder: __( 'Meta description (max 160 chars)' ) } ) +
					'<span class="qcld_wpbot_ai_seo-char-count" data-count="meta_description"></span>' +
					'<div class="qcld_wpbot_ai_seo-seo-actions">' +
					ui.btn( __( 'AI Generate Meta' ), { disabled: ! aiOn, title: aiOn ? '' : aiOff, attrs: { 'data-act': 'meta' } } ) +
					ui.btn( __( 'SEO Analysis' ), { variant: 'link', disabled: ! aiOn, title: aiOn ? '' : aiOff, attrs: { 'data-act': 'seo' } } ) + '</div>' +
					( aiOn || ( cfg.aiPaused || [] ).length ? '' : '<p class="qcld_wpbot_ai_seo-ai-notice">' + esc( __( '⚠ Configure an AI provider key in Settings to enable AI features.' ) ) + '</p>' ) +
					'<div data-region="scores"></div><div data-region="analysis"></div>'
				) +
				ui.card( __( 'WordPress Settings' ),
					ui.select( 'post_type', __( 'Post Type' ), k.post_type || 'post', typeOpts.length ? typeOpts : [ { label: 'Post', value: 'post' } ] ) +
					'<div data-region="taxonomies"></div>'
				) +
				ui.card( __( 'Featured Image' ), '<div data-region="featured"></div>' +
					'<div class="qcld_wpbot_ai_seo-image-tabs"><div class="qcld_wpbot_ai_seo-image-tabs__nav" role="tablist">' +
					'<button type="button" class="qcld_wpbot_ai_seo-image-tabs__tab" data-imgtab="stock"><span class="qcld_wpbot_ai_seo-image-tabs__icon" aria-hidden="true">' + ICON_STOCK + '</span>' + esc( __( 'Stock Photos' ) ) + '</button>' +
					'<button type="button" class="qcld_wpbot_ai_seo-image-tabs__tab" data-imgtab="ai"><span class="qcld_wpbot_ai_seo-pro-inline-action"><span class="qcld_wpbot_ai_seo-image-tabs__icon" aria-hidden="true">' + ICON_AI + '</span>' + esc( __( 'AI Generate' ) ) + ui.proBadge() + '</span></button>' +
					'<button type="button" class="qcld_wpbot_ai_seo-image-tabs__tab" data-imgtab="upload"><span class="qcld_wpbot_ai_seo-image-tabs__icon" aria-hidden="true">' + ICON_UPLOAD + '</span>' + esc( __( 'Upload' ) ) + '</button>' +
					'</div><div class="qcld_wpbot_ai_seo-image-tabs__content" data-region="image-panel"></div></div>'
				) +
				'</div></div></div>'
			);

			keywordField = ui.tokenField( $main.find( '[data-mount="keywords"]' ), {
				label: __( 'Target Keywords' ) + ' *',
				values: k.keywords,
				placeholder: __( 'Search keyword vault or add custom...' ),
				help: __( 'Separate with commas or the Enter key.' ),
				suggestions: () => vault,
				onChange: ( v ) => {
					k.keywords = v;
					showErrorsClear( 'keywords' );
				},
			} );

			renderStatus();
			renderActions();
			renderGenActions();
			renderOutline();
			renderCounts();
			renderScores();
			renderAnalysis();
			renderTaxonomies();
			renderFeatured();
			renderImageTabs();
			mountEditor();
		};

		const showErrorsClear = ( field ) => {
			const $w = $main.find( '[data-errwrap="' + field + '"]' );
			$w.removeClass( 'has-error' ).find( '.qcld_wpbot_ai_seo-field-error' ).prop( 'hidden', true ).text( '' );
		};

		const region = ( name ) => $main.find( '[data-region="' + name + '"]' );

		const renderStatus = () => {
			region( 'status' ).html( k.status ? '<span class="qcld_wpbot_ai_seo-status-badge" style="margin-left:8px">' + esc( C.STATUS_LABELS[ k.status ] || k.status ) + '</span>' : '' );
		};

		const renderActions = () => {
			const lock = busy.scheduling || busy.saving || !! busy.publishing;
			const published = 'published' === k.status;
			region( 'actions' ).html(
				( published && k.view_url ? ui.btn( __( 'View Post ↗' ), { href: k.view_url, target: '_blank' } ) : '' ) +
				( showSchedule
					? '<div class="qcld_wpbot_ai_seo-schedule-inline"><input type="datetime-local" class="qcld_wpbot_ai_seo-input" data-schedule-at value="' + esc( C.toLocalInput( k.scheduled_publish_at ) ) + '" aria-label="' + esc( __( 'Publish date and time' ) ) + '">' +
						ui.btn( busy.scheduling ? __( 'Scheduling...' ) : __( 'Confirm Schedule' ), { variant: 'primary', disabled: lock, attrs: { 'data-act': 'schedule-confirm' } } ) + '</div>'
					: '' ) +
				ui.btn( __( 'Schedule Publish' ), { disabled: lock, attrs: { 'data-act': 'schedule-toggle' } } ) +
				ui.btn( busy.saving ? __( 'Saving...' ) : __( 'Save' ), { disabled: busy.saving, attrs: { 'data-act': 'save' } } ) +
				ui.btn( 'draft' === busy.publishing ? __( 'Saving Draft...' ) : __( 'Save to WP (Draft)' ), { disabled: !! busy.publishing || busy.saving, attrs: { 'data-act': 'publish', 'data-status': 'draft' } } ) +
				ui.btn( 'publish' === busy.publishing ? ( published ? __( 'Updating...' ) : __( 'Publishing...' ) ) : ( published ? __( 'Update on WP' ) : __( 'Publish to WP' ) ),
					{ variant: 'primary', disabled: !! busy.publishing || busy.saving, attrs: { 'data-act': 'publish', 'data-status': 'publish' } } )
			);
			region( 'actions' ).find( '.qcld_wpbot_ai_seo-btn' ).each( function () {
				const act = $( this ).data( 'act' );
				if ( ( 'publish' === act && busy.publishing === $( this ).data( 'status' ) ) || ( 'save' === act && busy.saving ) || ( 'schedule-confirm' === act && busy.scheduling ) ) {
					$( this ).addClass( 'is-busy' ).prepend( '<span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span>' );
				}
			} );
		};

		const renderGenActions = () => {
			region( 'gen-actions' ).html(
				( X.genActions.length ? hook( X.genActions ) : ui.proUpsell( __( 'Generate Outline' ) ) ) +
				( busy.generating
					? ui.btn( __( 'Generating Article...' ), { variant: 'primary', disabled: true, className: 'is-busy qcld_wpbot_ai_seo-btn-generate' } )
					: ui.btn( __( 'Generate Full Article' ), { variant: 'primary', disabled: ! aiOn || busy.outline, title: aiOn ? '' : aiOff, className: 'qcld_wpbot_ai_seo-btn-generate', attrs: { 'data-act': 'generate' } } ) )
			);
			region( 'gen-actions' ).find( '.is-busy' ).prepend( '<span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span>' );
		};

		const renderOutline = () => {
			const raw = k.outline;
			const $r = region( 'outline' );
			if ( ! raw || '[]' === raw || '{}' === raw || ! String( raw ).trim() ) {
				$r.empty();
				return;
			}
			let list = null;
			try {
				const parsed = JSON.parse( raw );
				if ( Array.isArray( parsed ) && parsed.length ) {
					list = parsed;
				} else if ( parsed && 'object' === typeof parsed && ! Object.keys( parsed ).length ) {
					$r.empty();
					return;
				}
			} catch ( e ) {
				// Ignored on purpose: the browser may block or not support this.
			}
			if ( ! list ) {
				$r.html( ui.card( __( 'Outline' ), '<pre class="qcld_wpbot_ai_seo-outline-preview">' + esc( raw ) + '</pre>' ) );
				return;
			}
			$r.html( ui.card( __( 'Outline' ), '<ul class="qcld_wpbot_ai_seo-outline-list">' + list.map( ( item ) => {
				const text = item.heading || item.title || item.text || item.name || item.section || item.section_title || '';
				if ( ! text ) {
					return '';
				}
				const lvl = parseInt( String( item.level || 'h2' ).replace( /^h/i, '' ), 10 );
				const level = lvl >= 1 && lvl <= 6 ? lvl : 2;
				return '<li style="padding-left:' + 20 * Math.max( 0, level - 2 ) + 'px"><span class="qcld_wpbot_ai_seo-outline-level">H' + level + '</span><span class="qcld_wpbot_ai_seo-outline-heading">' + esc( text ) + '</span></li>';
			} ).join( '' ) + '</ul>' ) );
		};

		const renderContentExtras = () => {
			const html = k.content || '';
			const $r = region( 'content-extras' );
			if ( ! html ) {
				$r.empty();
				return;
			}
			const text = html.replace( /<[^>]*>/g, ' ' );
			$r.html(
				'<div class="qcld_wpbot_ai_seo-content-stats"><span>' + esc( __( 'Words:' ) ) + ' ' + text.split( /\s+/ ).filter( Boolean ).length + '</span>' +
				'<span>' + esc( __( 'Characters:' ) ) + ' ' + html.replace( /<[^>]*>/g, '' ).length + '</span></div>' +
				'<div class="qcld_wpbot_ai_seo-humanize-wrap">' +
				( busy.humanize
					? ui.btn( __( 'Humanizing...' ), { disabled: true, className: 'is-busy' } )
					: ui.btn( '✨ ' + __( 'Humanize' ), { disabled: ! aiOn || busy.generating, title: aiOn ? __( 'Make the text sound more natural.' ) : aiOff, className: 'qcld_wpbot_ai_seo-btn-humanize', attrs: { 'data-act': 'humanize' } } ) ) +
				( X.contentActions.length ? hook( X.contentActions ) : ui.proUpsell( '✨ ' + __( 'Deep Humanize' ) ) ) + '</div>' +
				( showThinkingHint
					? '<div class="qcld_wpbot_ai_seo-ai-thinking-hint" role="status"><span class="qcld_wpbot_ai_seo-ai-thinking-hint__icon" aria-hidden="true">💡</span><div class="qcld_wpbot_ai_seo-ai-thinking-hint__body">' +
						'<strong>' + esc( __( 'Auto-cleaned model reasoning' ) ) + '</strong><p>' + esc( __( 'Some AI models emit internal reasoning before the actual article. The plugin automatically stripped it from this output.' ) ) + '</p>' +
						'<p class="qcld_wpbot_ai_seo-ai-thinking-hint__models"><a href="' + esc( cfg.aiSettingsUrl ) + '">' + esc( __( 'Change AI provider →' ) ) + '</a></p></div>' +
						'<button type="button" class="qcld_wpbot_ai_seo-ai-thinking-hint__dismiss" data-act="dismiss-hint" aria-label="' + esc( __( 'Dismiss' ) ) + '">×</button></div>'
					: '' )
			);
			$r.find( '.is-busy' ).prepend( '<span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span>' );
		};

		const renderCounts = () => {
			$main.find( '[data-count="meta_title"]' ).text( ( k.meta_title || '' ).length + ' / 60' );
			$main.find( '[data-count="meta_description"]' ).text( ( k.meta_description || '' ).length + ' / 160' );
		};

		const renderScores = () => {
			const has = undefined !== k.seo_score && null !== k.seo_score && '' !== k.seo_score;
			region( 'scores' ).html( has
				? '<div class="qcld_wpbot_ai_seo-scores"><div class="qcld_wpbot_ai_seo-score-item"><span>' + esc( __( 'SEO Score' ) ) + '</span><strong>' + ( Number( k.seo_score ) || 0 ) + '/100</strong></div>' +
					'<div class="qcld_wpbot_ai_seo-score-item"><span>' + esc( __( 'Readability' ) ) + '</span><strong>' + ( Number( k.readability_score ) || 0 ) + '/100</strong></div></div>'
				: '' );
		};

		const renderAnalysis = () => {
			if ( ! analysis ) {
				region( 'analysis' ).empty();
				return;
			}
			region( 'analysis' ).html( '<div class="qcld_wpbot_ai_seo-seo-result"><h4>' + esc( __( 'SEO Analysis' ) ) + '</h4>' +
				( analysis.issues || [] ).map( ( i ) => '<div class="qcld_wpbot_ai_seo-seo-issue qcld_wpbot_ai_seo-seo-' + esc( i.severity ) + '"><strong>' + esc( i.severity ) + ':</strong> ' + esc( i.message ) +
					( i.suggestion ? '<p class="qcld_wpbot_ai_seo-seo-suggestion">' + esc( i.suggestion ) + '</p>' : '' ) + '</div>' ).join( '' ) +
				( analysis.content_suggestions || [] ).map( ( s ) => '<p class="qcld_wpbot_ai_seo-seo-suggestion">• ' + esc( s ) + '</p>' ).join( '' ) + '</div>' );
		};

		const renderTaxonomies = () => {
			const $r = region( 'taxonomies' );
			if ( 'post' !== ( k.post_type || 'post' ) ) {
				$r.empty();
				tagField = null;
				return;
			}
			const cats = tax.categories || [];
			$r.html(
				( cats.length
					? '<div class="qcld_wpbot_ai_seo-categories-field"><label class="qcld_wpbot_ai_seo-field__label">' + esc( __( 'Categories' ) ) + '</label><div class="qcld_wpbot_ai_seo-checkbox-list">' +
						cats.map( ( c ) => ui.checkbox( c.name, ( k.categories || [] ).map( Number ).indexOf( Number( c.id ) ) !== -1, { 'data-cat': c.id } ) ).join( '' ) + '</div></div>'
					: '' ) +
				'<div data-mount="tags"></div>'
			);
			tagField = ui.tokenField( $r.find( '[data-mount="tags"]' ), {
				label: __( 'Tags' ),
				values: k.tags || [],
				placeholder: __( 'Add tag...' ),
				help: __( 'Separate with commas or the Enter key.' ),
				suggestions: () => ( tax.tags || [] ).map( ( x ) => x.name ),
				onChange: ( v ) => {
					k.tags = v;
				},
			} );
		};

		const renderFeatured = () => {
			region( 'featured' ).html( k.featured_image_url
				? '<div class="qcld_wpbot_ai_seo-featured-image-preview"><img src="' + esc( k.featured_image_url ) + '" alt="' + esc( k.title || __( 'Featured image' ) ) + '">' +
					ui.btn( __( 'Remove Image' ), { variant: 'link', destructive: true, className: 'qcld_wpbot_ai_seo-remove-image', attrs: { 'data-act': 'remove-image' } } ) + '</div>'
				: '' );
		};

		const renderImageTabs = () => {
			$main.find( '[data-imgtab]' ).each( function () {
				const on = $( this ).data( 'imgtab' ) === imageTab;
				$( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on );
			} );
			const $p = region( 'image-panel' );
			if ( 'stock' === imageTab ) {
				if ( false === stockReady ) {
					$p.html( '<div class="qcld_wpbot_ai_seo-image-tab-panel"><p class="qcld_wpbot_ai_seo-image-tab-desc">' + esc( __( 'Stock photo search needs a provider API key. Add one first — both Pexels and Pixabay are free and take about a minute to set up.' ) ) + '</p>' +
						ui.btn( __( 'Go to Settings → Stock Photos' ), { variant: 'primary', attrs: { 'data-act': 'stock-settings' } } ) + '</div>' );
					return;
				}
				$p.html( '<div class="qcld_wpbot_ai_seo-image-tab-panel"><p class="qcld_wpbot_ai_seo-image-tab-desc">' + esc( __( 'Search free stock photos and set one as the featured image. Photos are saved to your Media Library.' ) ) + '</p>' +
					'<div class="qcld_wpbot_ai_seo-stock-search"><input type="search" class="qcld_wpbot_ai_seo-input" data-stock-q value="' + esc( stockQuery ) + '" placeholder="' + esc( k.topic || k.title || __( 'e.g. laptop coffee desk' ) ) + '" aria-label="' + esc( __( 'Search' ) ) + '">' +
					ui.btn( busy.searching ? __( 'Searching...' ) : __( 'Search' ), { variant: 'primary', disabled: busy.searching, attrs: { 'data-act': 'stock-search' } } ) + '</div>' +
					( stockError ? '<p class="qcld_wpbot_ai_seo-field-error qcld_wpbot_ai_seo-image-error">' + esc( stockError ) + '</p>' : '' ) +
					( ! stockError && stockSearched && ! stockResults.length && ! busy.searching ? '<p class="qcld_wpbot_ai_seo-field-hint">' + esc( __( 'No photos found. Try a broader search term.' ) ) + '</p>' : '' ) +
					( stockResults.length
						? '<div class="qcld_wpbot_ai_seo-stock-grid">' + stockResults.map( ( img ) =>
							'<button type="button" class="qcld_wpbot_ai_seo-stock-grid__item' + ( busy.importing === String( img.id ) ? ' is-importing' : '' ) + '" data-stock-id="' + esc( img.id ) + '" title="' + esc( img.alt || '' ) + '"' + ( busy.importing ? ' disabled' : '' ) + '>' +
							'<img src="' + esc( img.thumb ) + '" alt="' + esc( img.alt || '' ) + '" loading="lazy">' +
							( busy.importing === String( img.id ) ? '<span class="qcld_wpbot_ai_seo-stock-grid__busy"><span class="qcld_wpbot_ai_seo-spin"></span></span>' : '' ) +
							( img.photographer ? '<span class="qcld_wpbot_ai_seo-stock-grid__credit">' + esc( img.photographer ) + '</span>' : '' ) + '</button>' ).join( '' ) + '</div>'
						: '' ) + '</div>' );
			} else if ( 'ai' === imageTab ) {
				$p.html( X.imagePanel
					? X.imagePanel( ctx )
					: '<div class="qcld_wpbot_ai_seo-image-tab-panel"><p class="qcld_wpbot_ai_seo-image-tab-desc">' + esc( __( 'Generate a featured image from your article content with AI.' ) ) + '</p>' + ui.proUpsell( __( 'Generate Image' ), { variant: 'primary' } ) + '</div>' );
				$p.find( '.is-busy' ).prepend( '<span class="qcld_wpbot_ai_seo-spin" aria-hidden="true"></span>' );
			} else {
				$p.html( '<div class="qcld_wpbot_ai_seo-image-tab-panel"><p class="qcld_wpbot_ai_seo-image-tab-desc">' + esc( __( 'Upload an image or pick one from your WordPress Media Library.' ) ) + '</p>' +
					ui.btn( __( 'Open Media Library' ), { variant: 'primary', attrs: { 'data-act': 'media' } } ) + '</div>' );
			}
		};

		/* ── TinyMCE ────────────────────────────────── */

		const removeEditor = () => {
			const ed = wpEditor();
			try {
				if ( ed && mce() ) {
					ed.remove( EDITOR_ID );
				}
			} catch ( e ) {
				// Ignored on purpose: the browser may block or not support this.
			}
			try {
				if ( mce() ) {
					mce().remove();
				}
			} catch ( e ) {
				// Ignored on purpose: the browser may block or not support this.
			}
		};

		const mountEditor = () => {
			removeEditor();
			const $wrap = region( 'editor' );
			$wrap.html( '<textarea id="' + EDITOR_ID + '" class="wp-editor-area" rows="20"></textarea>' );
			$( '#' + EDITOR_ID ).val( k.content || '' ).on( 'input', function () {
				k.content = $( this ).val();
				renderContentExtras();
			} );
			const ed = wpEditor();
			if ( ! ed || ! ed.initialize ) {
				return;
			}
			ed.initialize( EDITOR_ID, {
				tinymce: {
					wpautop: true,
					plugins: 'charmap colorpicker hr lists media paste tabfocus textcolor wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern',
					toolbar1: 'formatselect | bold italic underline strikethrough | bullist numlist | blockquote hr | alignleft aligncenter alignright | link unlink | wp_adv',
					toolbar2: 'strikethrough hr forecolor pastetext removeformat charmap outdent indent undo redo wp_help',
					height: 400,
					statusbar: false,
					setup( editor ) {
						editor.on( 'change keyup', () => {
							k.content = editor.getContent();
							renderContentExtras();
						} );
					},
				},
				quicktags: true,
				mediaButtons: true,
			} );
		};

		$( document ).one( 'qcld:leave', removeEditor );

		/* ── Field binding ──────────────────────────── */

		$main.on( 'input change', '[data-field]', function ( e ) {
			const field = $( this ).data( 'field' );
			if ( ! field || $( this ).closest( '.qcld_wpbot_ai_seo-premium-modal' ).length ) {
				return;
			}
			let value = 'checkbox' === this.type ? this.checked : $( this ).val();
			if ( 'word_count_target' === field ) {
				if ( 'input' === e.type && 'number' === this.type ) {
					return; // Commit typed numbers on change.
				}
				value = parseInt( value, 10 ) || DEFAULTS.word_count_target;
			}
			if ( ! set( field, value ) ) {
				if ( 'checkbox' === this.type ) {
					this.checked = !! k[ field ];
				} else {
					$( this ).val( k[ field ] );
				}
				return;
			}
			if ( 'word_count_target' === field ) {
				$main.find( '[data-field="word_count_target"]' ).not( this ).val( k.word_count_target );
				if ( Number( $( this ).val() ) !== k.word_count_target ) {
					$( this ).val( k.word_count_target );
				}
			} else if ( 'topic' === field ) {
				showErrorsClear( 'topic' );
			} else if ( 'title' === field ) {
				region( 'title-hint' ).prop( 'hidden', !! k.title );
			} else if ( 'meta_title' === field || 'meta_description' === field ) {
				renderCounts();
			} else if ( 'post_type' === field && 'change' === e.type ) {
				if ( 'post' !== value ) {
					k.categories = [];
					k.tags = [];
				}
				renderTaxonomies();
			}
		} );

		$main.on( 'change', '[data-cat]', function () {
			const cid = Number( $( this ).data( 'cat' ) );
			const list = ( k.categories || [] ).map( Number );
			k.categories = this.checked ? ( list.indexOf( cid ) === -1 ? list.concat( [ cid ] ) : list ) : list.filter( ( x ) => x !== cid );
		} );

		$main.on( 'click', '[data-back]', () => app.go( 'articles' ) );
		$main.on( 'click', '[data-imgtab]', function () {
			imageTab = String( $( this ).data( 'imgtab' ) );
			renderImageTabs();
		} );
		$main.on( 'input', '[data-stock-q]', function () {
			stockQuery = $( this ).val();
		} );
		$main.on( 'keydown', '[data-stock-q]', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				stockSearch();
			}
		} );
		$main.on( 'click', '[data-stock-id]', function () {
			const img = stockResults.find( ( x ) => String( x.id ) === String( $( this ).data( 'stock-id' ) ) );
			if ( img ) {
				stockImport( img );
			}
		} );

		$main.on( 'click', '[data-act]', function () {
			const act = $( this ).data( 'act' );
			const handlers = {
				save,
				publish: () => publish( String( $( this ).data( 'status' ) ) || k.default_post_status || 'draft' ),
				'schedule-toggle': () => {
					showSchedule = ! showSchedule;
					renderActions();
				},
				'schedule-confirm': schedule,
				generate,
				humanize,
				meta: generateMeta,
				seo: seoAnalysis,
				'dismiss-hint': () => {
					showThinkingHint = false;
					renderContentExtras();
				},
				'remove-image': () => {
					k.featured_image_url = '';
					k.featured_image_id = 0;
					renderFeatured();
				},
				'stock-settings': () => {
					const url = new window.URL( cfg.contentPageUrl + '#settings/images', window.location.href );
					const same = url.pathname === window.location.pathname && url.search === window.location.search;
					window.location.href = url.href;
					if ( same ) {
						app.go( 'settings', { id: 'images' } );
					}
				},
				'stock-search': () => stockSearch(),
				media: openMedia,
			};
			if ( handlers[ act ] ) {
				handlers[ act ]();
			} else if ( X.actions[ act ] ) {
				X.actions[ act ]( ctx, this );
			}
		} );

		/* ── Actions ────────────────────────────────── */

		function save() {
			if ( ! validateTopic() ) {
				toast( __( 'Please fill in all required fields before saving.' ), 'error' );
				return;
			}
			busy.saving = true;
			renderActions();
			const existing = savedId;
			const req = existing ? api( 'content_article_update', Object.assign( payload(), { id: existing } ) ) : api( 'content_article_create', payload() );
			req.then( ( res ) => {
				if ( existing ) {
					toast( __( 'Article saved.' ) );
				} else {
					toast( __( 'Article created.' ) );
					if ( res && res.id ) {
						app.go( 'edit-article', { id: res.id } );
					}
				}
			} ).catch( ( e ) => toast( e.message, 'error' ) ).finally( () => {
				busy.saving = false;
				if ( app.alive( t ) ) {
					renderActions();
				}
			} );
		}

		function publish( postStatus ) {
			if ( ! validateTopic() ) {
				toast( __( 'Please fill in all required fields before publishing.' ), 'error' );
				return;
			}
			busy.publishing = postStatus;
			renderActions();
			const isNew = ! id;
			ensureSaved( {}, __( 'Could not save article before publishing.' ) )
				.then( ( articleId ) => api( 'content_article_publish', { id: articleId, post_status: postStatus } ).then( ( res ) => ( { res, articleId } ) ) )
				.then( ( { res, articleId } ) => {
					const msg = {
						draft: __( 'Saved as draft in WordPress!' ),
						publish: __( 'Published to WordPress!' ),
						pending: __( 'Submitted for review in WordPress!' ),
						private: __( 'Saved as private in WordPress!' ),
					};
					toast( msg[ postStatus ] || __( 'Sent to WordPress!' ), 'success' );
					if ( isNew ) {
						app.go( 'edit-article', { id: articleId } );
						return;
					}
					[ 'wp_edit_url', 'view_url', 'wp_post_id', 'status' ].forEach( ( f ) => {
						const src = 'wp_edit_url' === f ? 'edit_url' : f;
						if ( res && res[ src ] ) {
							k[ f ] = res[ src ];
						}
					} );
					renderStatus();
				} )
				.catch( ( e ) => toast( e.message || __( 'Publishing failed.' ), 'error' ) )
				.finally( () => {
					busy.publishing = '';
					if ( app.alive( t ) ) {
						renderActions();
					}
				} );
		}

		function schedule() {
			if ( ! validateTopic() ) {
				toast( __( 'Please fill in all required fields before scheduling.' ), 'error' );
				return;
			}
			const when = $main.find( '[data-schedule-at]' ).val();
			if ( ! when ) {
				toast( __( 'Select a publish date and time.' ), 'error' );
				return;
			}
			busy.scheduling = true;
			renderActions();
			$main.find( '[data-schedule-at]' ).val( when );
			const isNew = ! id;
			ensureSaved( { scheduled_publish_at: when }, __( 'Could not save article before scheduling.' ) )
				.then( ( articleId ) => api( 'content_article_schedule', { id: articleId, scheduled_publish_at: when } ).then( ( res ) => ( { res, articleId } ) ) )
				.then( ( { res, articleId } ) => {
					toast( __( 'Article scheduled in WordPress.' ), 'success' );
					if ( isNew ) {
						app.go( 'edit-article', { id: articleId } );
						return;
					}
					k.status = ( res && res.status ) || 'scheduled';
					k.scheduled_publish_at = ( res && res.scheduled_publish_at ) || when.replace( 'T', ' ' ) + ':00';
					showSchedule = false;
					renderStatus();
				} )
				.catch( ( e ) => toast( e.message || __( 'Scheduling failed.' ), 'error' ) )
				.finally( () => {
					busy.scheduling = false;
					if ( app.alive( t ) ) {
						renderActions();
					}
				} );
		}

		function generate() {
			if ( ! validateAll() ) {
				toast( __( 'Please fill in all required fields.' ), 'error' );
				return;
			}
			if ( ! savedId ) {
				runGenerate();
				return;
			}
			// Editing an existing article: regenerate it in place, after asking.
			const live = 'published' === k.status || 'scheduled' === k.status;
			A.confirmDialog( {
				title: __( 'Regenerate Article' ),
				message: __( 'Replace this article\'s title and body with a newly generated version?' ),
				items: [
					__( 'Your current version, including unsaved edits, is saved first and can be restored from Versions.' ),
					live
						? __( 'The WordPress post is not changed until you click Update on WP.' )
						: __( 'Nothing is sent to WordPress.' ),
				],
				confirmLabel: __( 'Regenerate' ),
			} ).then( ( ok ) => {
				if ( ok ) {
					runGenerate();
				}
			} );
		}

		function runGenerate() {
			busy.generating = true;
			region( 'gen-error' ).prop( 'hidden', true ).text( '' );
			renderGenActions();
			region( 'writing' ).prop( 'hidden', false );
			region( 'editor' ).hide();
			toast( __( 'Article generation started. This may take up to 5 minutes depending on your AI model and word count. Please keep this page open.' ), 'warning', 10000 );
			A.slow.start();
			const data = {
				topic: k.topic,
				keywords: k.keywords,
				tone: k.tone,
				word_count: Number( k.word_count_target ) || DEFAULTS.word_count_target,
				language: k.language,
				outline: k.outline,
				serp_context: k.serp_outline_context,
				include_quick_answer: false !== k.include_quick_answer,
				include_faq: false !== k.include_faq,
				post_type: k.post_type || 'post',
				category_ids: ( k.categories || [] ).map( Number ),
				tag_ids: k.tags || [],
			};
			if ( k.preset_id ) {
				data.preset_id = Number( k.preset_id );
			}
			X.generateData.forEach( ( fn ) => fn( data, ctx ) );
			// In edit mode save the current edits first (so the "Before
			// regenerate" version holds them), then regenerate in place.
			const start = savedId
				? ensureSaved( {}, __( 'Could not save article before regenerating.' ) ).then( ( articleId ) => {
					data.article_id = articleId;
				} )
				: Promise.resolve();
			start
				.then( () => api( 'content_generate', data ) )
				.then( ( res ) => {
					if ( res && res.id ) {
						if ( res.partial ) {
							toast( res.message || __( 'Article saved, but it may be incomplete.' ), 'warning' );
						} else if ( res.regenerated ) {
							toast( __( 'Article regenerated. The previous version is saved in Versions.' ) );
						} else if ( res.continued > 0 ) {
							toast( res.message || __( 'Article generated successfully! Redirecting...' ), 'success' );
						} else {
							toast( __( 'Article generated successfully! Redirecting...' ) );
						}
						app.go( 'edit-article', { id: res.id } );
						return;
					}
					if ( res && res.parsed ) {
						k.title = res.parsed.title || k.title;
						$main.find( '[data-field="title"]' ).val( k.title );
						setContent( res.parsed.body || res.content || '' );
						k.excerpt = res.parsed.excerpt || '';
						$main.find( '[data-field="excerpt"]' ).val( k.excerpt );
						if ( res.parsed.outline ) {
							k.outline = JSON.stringify( res.parsed.outline, null, 2 );
							renderOutline();
						}
					} else if ( res && res.content ) {
						setContent( res.content );
					}
					toast( __( 'Article generated! Review and save.' ) );
				} )
				.catch( ( e ) => {
					const msg = ( e && e.message ) || __( 'AI generation failed. Check your AI provider settings.' );
					region( 'gen-error' ).text( msg ).prop( 'hidden', false );
					toast( msg, 'error' );
				} )
				.finally( () => {
					A.slow.stop();
					busy.generating = false;
					if ( app.alive( t ) ) {
						region( 'writing' ).prop( 'hidden', true );
						region( 'editor' ).show();
						renderGenActions();
					}
				} );
		}

		function humanize() {
			const content = currentContent();
			if ( ! content || ! content.trim() ) {
				toast( __( 'Generate or write content first before humanizing.' ), 'error' );
				return;
			}
			busy.humanize = true;
			renderContentExtras();
			A.slow.start();
			api( 'content_generate_improve', { content, instruction: HUMANIZE_FREE, tone: k.tone || 'professional', article_id: savedId || undefined } )
				.then( ( res ) => {
					if ( res && res.content ) {
						setContent( res.content );
						toast( __( 'Content humanized!' ), 'success' );
					}
				} )
				.catch( ( e ) => toast( ( e && e.message ) || __( 'Humanize failed. Please try again.' ), 'error' ) )
				.finally( () => {
					A.slow.stop();
					busy.humanize = false;
					if ( app.alive( t ) ) {
						renderContentExtras();
					}
				} );
		}

		function generateMeta() {
			k.content = currentContent();
			if ( ! k.content ) {
				toast( __( 'Generate or write content first.' ), 'error' );
				return;
			}
			const $b = $main.find( '[data-act="meta"]' );
			ui.busy( $b, true, __( 'Generating Meta...' ) );
			A.slow.start();
			api( 'content_generate_meta', { title: k.title, content: k.content, keywords: k.keywords } )
				.then( ( res ) => {
					if ( res && res.meta ) {
						k.meta_title = res.meta.meta_title || '';
						k.meta_description = res.meta.meta_description || '';
						$main.find( '[data-field="meta_title"]' ).val( k.meta_title );
						$main.find( '[data-field="meta_description"]' ).val( k.meta_description );
						renderCounts();
					}
					toast( __( 'Meta generated!' ) );
				} )
				.catch( ( e ) => toast( e.message, 'error' ) )
				.finally( () => {
					A.slow.stop();
					ui.busy( $b, false );
				} );
		}

		function seoAnalysis() {
			const $b = $main.find( '[data-act="seo"]' );
			ui.busy( $b, true );
			A.slow.start();
			api( 'content_generate_seo_optimize', { content: currentContent(), keywords: k.keywords, title: k.title, article_id: savedId || undefined } )
				.then( ( res ) => {
					if ( res && res.analysis ) {
						analysis = res.analysis;
						if ( undefined !== analysis.seo_score ) {
							k.seo_score = analysis.seo_score;
						}
						if ( undefined !== analysis.readability_score ) {
							k.readability_score = analysis.readability_score;
						}
						renderScores();
						renderAnalysis();
					}
					toast( __( 'SEO analysis complete!' ) );
				} )
				.catch( ( e ) => toast( e.message, 'error' ) )
				.finally( () => {
					A.slow.stop();
					ui.busy( $b, false );
				} );
		}

		function stockSearch() {
			const q = String( stockQuery || '' ).trim() || k.topic || k.title || '';
			if ( ! q ) {
				stockError = __( 'Enter a search term (or set a topic/title first).' );
				renderImageTabs();
				return;
			}
			stockQuery = q;
			busy.searching = true;
			stockError = '';
			renderImageTabs();
			api( 'content_stock_search', { query: q, per_page: 12 } )
				.then( ( res ) => {
					stockResults = ( res && res.images ) || [];
					stockSearched = true;
				} )
				.catch( ( e ) => {
					stockResults = [];
					stockError = ( e && e.message ) || __( 'Stock image search failed.' );
				} )
				.finally( () => {
					busy.searching = false;
					if ( app.alive( t ) ) {
						renderImageTabs();
					}
				} );
		}

		function stockImport( img ) {
			busy.importing = String( img.id );
			stockError = '';
			renderImageTabs();
			api( 'content_stock_import', { url: img.full, alt: img.alt || k.title || '', article_id: savedId || 0 } )
				.then( ( res ) => {
					if ( res && res.attachment_id ) {
						k.featured_image_url = res.url;
						k.featured_image_id = res.attachment_id;
						renderFeatured();
						toast( __( 'Featured image set from stock photo!' ) );
					}
				} )
				.catch( ( e ) => {
					stockError = ( e && e.message ) || __( 'Image import failed.' );
					toast( stockError, 'error' );
				} )
				.finally( () => {
					busy.importing = '';
					if ( app.alive( t ) ) {
						renderImageTabs();
					}
				} );
		}

		function openMedia() {
			if ( ! window.wp || ! window.wp.media ) {
				toast( __( 'The Media Library is not available on this page.' ), 'error' );
				return;
			}
			const frame = window.wp.media( {
				title: __( 'Select Featured Image' ),
				library: { type: 'image' },
				multiple: false,
				button: { text: __( 'Use this image' ) },
			} );
			frame.on( 'select', () => {
				const att = frame.state().get( 'selection' ).first().toJSON();
				k.featured_image_url = att.url;
				k.featured_image_id = att.id || 0;
				renderFeatured();
			} );
			frame.open();
		}

		/* ── Load ───────────────────────────────────── */

		const seoActive = ( cfg.modules || [] ).some( ( m ) => 'seo' === m.id && m.is_active );

		Promise.all( [
			api( 'content_presets_list', {} ),
			api( 'content_wp_taxonomies', {} ),
			api( 'content_settings_get', {} ).catch( () => null ),
			Promise.all( X.load.map( ( fn ) => Promise.resolve( fn( ctx ) ).catch( () => null ) ) ),
		] ).then( ( r ) => {
			presets = r[ 0 ] || [];
			tax = r[ 1 ] || tax;
			const settings = r[ 2 ];
			if ( settings ) {
				const provider = settings.stock_provider || 'pexels';
				stockReady = 'pixabay' === provider ? !! settings.has_pixabay_key : !! settings.has_pexels_key;
			} else {
				stockReady = true;
			}
			const vaultReq = seoActive
				? api( 'seo_keywords_list', { per_page: 200 } ).then( ( res ) => {
					vault = ( ( res && ( res.items || res.data ) ) || [] ).map( ( x ) => x.keyword ).filter( Boolean );
				} ).catch( () => {} )
				: Promise.resolve();

			if ( id ) {
				return vaultReq.then( () => api( 'content_article_get', { id } ) ).then( ( a ) => {
					Object.assign( k, DEFAULTS, a, {
						word_count_target: parseInt( a.word_count_target, 10 ) || DEFAULTS.word_count_target,
						keywords: ( Array.isArray( a.keywords ) ? a.keywords : [] ).filter( ( x ) => 'string' === typeof x && '' !== x ),
						categories: ( Array.isArray( a.category_ids ) ? a.category_ids : [] ).map( Number ).filter( Boolean ),
						tags: ( Array.isArray( a.tag_ids ) ? a.tag_ids : [] ).filter( ( x ) => 'string' === typeof x && '' !== x ),
						featured_image_url: a.featured_image_url || '',
						featured_image_id: a.featured_image_id || 0,
						brand_voice_id: a.brand_voice_id || '',
						outline: a.outline && 'object' === typeof a.outline ? JSON.stringify( a.outline, null, 2 ) : a.outline || '',
					} );
				} );
			}
			if ( settings ) {
				Object.assign( k, {
					tone: settings.default_tone || k.tone,
					language: settings.default_language || k.language,
					word_count_target: parseInt( settings.default_word_count, 10 ) || k.word_count_target,
					post_type: settings.default_post_type || k.post_type,
					default_post_status: settings.default_post_status || k.default_post_status,
					categories: settings.default_category_id ? [ Number( settings.default_category_id ) ] : k.categories,
				} );
			}
			const pre = readPrefill();
			if ( pre ) {
				[ 'topic', 'title', 'outline', 'meta_title', 'meta_description', 'excerpt' ].forEach( ( f ) => {
					if ( pre[ f ] ) {
						k[ f ] = pre[ f ];
					}
				} );
				if ( pre.keywords && pre.keywords.length ) {
					k.keywords = pre.keywords;
				}
				if ( pre.word_count_target ) {
					k.word_count_target = parseInt( pre.word_count_target, 10 ) || k.word_count_target;
				}
			}
			return vaultReq;
		} ).then( () => {
			if ( app.alive( t ) ) {
				render();
			}
		} ).catch( ( e ) => {
			if ( app.alive( t ) ) {
				$main.html( C.pageHeader( '', '', C.backBtn() + '<h2>' + esc( id ? __( 'Edit Article' ) : __( 'New Article' ) ) + '</h2>' ) + ui.notice( 'error', e.message ) );
				$main.on( 'click', '[data-back]', () => app.go( 'articles' ) );
			}
		} );
	};

	C.views[ 'new-article' ] = editorView;
	C.views[ 'edit-article' ] = editorView;
}( jQuery, window ) );
