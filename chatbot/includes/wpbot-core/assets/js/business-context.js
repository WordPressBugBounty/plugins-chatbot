/**
 * WPBot > Business Context screen.
 *
 * Plain JS on wp.element (no build step), inside WPBot Core's shared admin
 * shell (host header + left menu). One short page per section:
 * Overview, Your business, Facts & rules, Customer questions, Documents,
 * Data sources and Settings. The shell's menu links switch sections here
 * without reloading (event "wpbot-shell:navigate"); the address keeps
 * &tab=, so links and the back button work.
 *
 * Talks to wpbot-core/v1/context.
 *
 * @param {Object} wp WordPress globals.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.element || ! wp.apiFetch ) {
		return;
	}
	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const useState = wp.element.useState;
	const useEffect = wp.element.useEffect;
	const C = wp.components;
	const __ = wp.i18n.__;
	const sprintf = wp.i18n.sprintf;
	// The product name from the host ("WPBot", or another host such as SEO Help).
	const PRODUCT =
		( window.qcldWpbotBusinessContext &&
			window.qcldWpbotBusinessContext.product ) ||
		'WPBot';
	const root = document.getElementById( 'qcwc-business-context' );
	if ( ! root ) {
		return;
	}
	const shell = document.querySelector( '.qcwpbot-shell' );

	const api = ( path, data ) =>
		wp.apiFetch(
			data
				? { path: '/wpbot-core/v1' + path, method: 'POST', data }
				: { path: '/wpbot-core/v1' + path }
		);
	const errorText = ( e ) => ( e && e.message ) || String( e );
	const cost = ( usd ) =>
		usd < 0.01
			? __( 'less than $0.01', 'chatbot' )
			: '$' + Number( usd ).toFixed( 2 );

	const TEXTS = [ 'business', 'audience', 'places' ];
	const LISTS = [
		'offers',
		'strengths',
		'questions',
		'words',
		'facts',
		'avoid',
	];

	const toForm = ( summary ) => {
		const s = summary || {};
		const form = {};
		TEXTS.forEach( ( key ) => {
			form[ key ] = s[ key ] || '';
		} );
		LISTS.forEach( ( key ) => {
			form[ key ] = ( s[ key ] || [] ).slice();
		} );
		return form;
	};

	/**
	 * Go to a section through the shell (address and menu follow).
	 *
	 * @param {string} id Section id.
	 */
	const go = ( id ) => {
		if ( window.wpbotShell && window.wpbotShell.go ) {
			window.wpbotShell.go( id );
		}
	};

	/**
	 * A list as removable chips; type and press Enter (or paste several lines).
	 *
	 * @param {Object} props Props.
	 * @return {Element} Input.
	 */
	function TagInput( props ) {
		const [ text, setText ] = useState( '' );
		const items = props.value || [];
		// Short items (offers, words, never say) commit on a comma, like
		// WordPress's own tag box. Whole sentences (facts, questions) often
		// hold commas, so those commit on Enter only and say so.
		const split = props.sentences ? /\n|;/ : /\n|;|,/;
		const add = ( raw ) => {
			const next = items.slice();
			raw.split( split ).forEach( ( part ) => {
				const item = part.trim();
				if ( item && ! next.includes( item ) ) {
					next.push( item );
				}
			} );
			props.onChange( next );
			setText( '' );
		};
		return el(
			'div',
			{ className: 'qcwc-bc__field' },
			el( 'label', { className: 'qcwc-bc__label' }, props.label ),
			( props.help || props.sentences ) &&
				el(
					'p',
					{ className: 'qcwc-bc__help' },
					[
						props.help,
						props.sentences &&
							__(
								'Press Enter after each one.',
								'chatbot'
							),
					]
						.filter( Boolean )
						.join( ' ' )
				),
			el(
				'div',
				{ className: 'qcwc-bc__tags' },
				items.map( ( item, index ) =>
					el(
						'span',
						{ key: item, className: 'qcwc-bc__tag' },
						item,
						! props.disabled &&
							el(
								'button',
								{
									type: 'button',
									className: 'qcwc-bc__tag-remove',
									'aria-label': sprintf(
										/* translators: %s: item. */
										__( 'Remove %s', 'chatbot' ),
										item
									),
									onClick: () =>
										props.onChange(
											items.filter(
												( x, i ) => i !== index
											)
										),
								},
								'×'
							)
					)
				),
				! props.disabled &&
					el( 'input', {
						type: 'text',
						className: 'qcwc-bc__tag-input',
						value: text,
						placeholder:
							props.placeholder ||
							( props.sentences
								? __(
										'Type and press Enter',
										'chatbot'
									)
								: __(
										'Type, then press Enter or a comma',
										'chatbot'
									) ),
						onChange: ( e ) => {
							const value = e.target.value;
							// A comma typed or pasted mid-word on mobile
							// keyboards arrives here, not in onKeyDown.
							if ( ! props.sentences && value.includes( ',' ) ) {
								add( value );
								return;
							}
							setText( value );
						},
						onKeyDown: ( e ) => {
							if (
								'Enter' === e.key ||
								( ',' === e.key && ! props.sentences )
							) {
								e.preventDefault();
								if ( text.trim() ) {
									add( text );
								}
							} else if (
								'Backspace' === e.key &&
								! text &&
								items.length
							) {
								props.onChange( items.slice( 0, -1 ) );
							}
						},
						onBlur: () => text.trim() && add( text ),
						onPaste: ( e ) => {
							const pasted = e.clipboardData.getData( 'text' );
							if (
								/\n/.test( pasted ) ||
								( ! props.sentences && pasted.includes( ',' ) )
							) {
								e.preventDefault();
								add( pasted );
							}
						},
					} )
			)
		);
	}

	function Card( props ) {
		return el(
			'div',
			{
				className:
					'qcwc-bc__card' +
					( props.className ? ' ' + props.className : '' ),
			},
			props.title &&
				el( 'h2', { className: 'qcwc-bc__card-title' }, props.title ),
			props.intro &&
				el( 'p', { className: 'qcwc-bc__help' }, props.intro ),
			props.children
		);
	}

	function SaveBar( { dirty, busy, onSave, onReset, disabled } ) {
		return el(
			'div',
			{ className: 'qcwc-bc__savebar' + ( dirty ? ' is-dirty' : '' ) },
			el(
				'span',
				{ className: 'qcwc-bc__help' },
				dirty
					? __(
							'You have changes that are not saved.',
							'chatbot'
						)
					: __(
							'Every AI feature uses what is saved here.',
							'chatbot'
						)
			),
			dirty &&
				el(
					C.Button,
					{ variant: 'tertiary', disabled: busy, onClick: onReset },
					__( 'Undo changes', 'chatbot' )
				),
			el(
				C.Button,
				{
					variant: 'primary',
					isBusy: busy,
					disabled: busy || ! dirty || disabled,
					onClick: onSave,
				},
				__( 'Save changes', 'chatbot' )
			)
		);
	}

	function BuildBox( { view, setView, setMessage, label } ) {
		const [ confirm, setConfirm ] = useState( false );
		const [ busy, setBusy ] = useState( false );
		const ai = view.ai;
		const build = () => {
			setBusy( true );
			setConfirm( false );
			setMessage( '' );
			api( '/context/summarize', {} )
				.then( ( v ) => {
					setView( v );
					setMessage(
						__(
							'Your summary is ready. Check it under "Your business" and "Facts & rules".',
							'chatbot'
						)
					);
				} )
				.catch( ( e ) => setMessage( errorText( e ) ) )
				.finally( () => setBusy( false ) );
		};
		if ( ! ai.ready ) {
			return el(
				'p',
				{ className: 'qcwc-bc__warn' },
				ai.message ||
					__(
						'Set up AI first to build the summary. You can also fill it in yourself.',
						'chatbot'
					),
				ai.url &&
					el(
						Fragment,
						null,
						' ',
						el(
							'a',
							{ href: ai.url },
							__( 'Set up AI', 'chatbot' )
						)
					)
			);
		}
		if ( confirm ) {
			return el(
				'div',
				{ className: 'qcwc-bc__confirm' },
				el(
					'p',
					null,
					sprintf(
						/* translators: 1: cost, 2: module whose AI settings are used. */
						__(
							'Estimated cost: %1$s, with the AI set up in %2$s. Your "Facts to always get right" and "Never say" are kept.',
							'chatbot'
						),
						cost( ai.estimate ),
						ai.label
					)
				),
				el(
					C.Button,
					{ variant: 'primary', onClick: build },
					__( 'Build it', 'chatbot' )
				),
				' ',
				el(
					C.Button,
					{ variant: 'tertiary', onClick: () => setConfirm( false ) },
					__( 'Cancel', 'chatbot' )
				)
			);
		}
		return el(
			C.Button,
			{
				variant: view.summary ? 'secondary' : 'primary',
				isBusy: busy,
				disabled: busy || ! view.can.use,
				onClick: () => setConfirm( true ),
			},
			label ||
				( view.summary
					? __(
							'Update it from my website with AI',
							'chatbot'
						)
					: __( 'Write it for me with AI', 'chatbot' ) )
		);
	}

	/**
	 * What AI will read, in words: "40 pages, 9 products and 5 other data sources".
	 *
	 * @param {Object} view View.
	 * @return {string} Text ('' when there is nothing to read).
	 */
	const readText = ( view ) => {
		const site = view.sources.find( ( x ) => 'site' === x.id );
		const others = view.sources.filter(
			( x ) =>
				'ok' === x.status && 'site' !== x.id && 'woocommerce' !== x.id
		).length;
		const parts = [];
		if ( site && 'ok' === site.status && site.detail ) {
			parts.push( site.detail );
		}
		if ( others > 0 ) {
			parts.push(
				sprintf(
					/* translators: %d: data sources. */
					__( '%d other data sources', 'chatbot' ),
					others
				)
			);
		}
		if ( 2 === parts.length ) {
			return sprintf(
				/* translators: 1: pages and products, 2: other data sources. */
				__( '%1$s and %2$s', 'chatbot' ),
				parts[ 0 ],
				parts[ 1 ]
			);
		}
		return parts.join( '' );
	};

	/**
	 * How to start: let AI write the summary from what WPBot already read,
	 * or fill it in by hand. Once there is a summary: update it.
	 *
	 * @param {Object} props view, setView, setMessage.
	 * @return {Element} Box.
	 */
	function StartBox( props ) {
		const view = props.view;
		if ( view.summary ) {
			return el(
				'div',
				{ className: 'qcwc-bc__start is-done' },
				el(
					'p',
					null,
					view.edited
						? __(
								'Your summary is in place, with your edits.',
								'chatbot'
							)
						: __(
								'Your summary is in place. Check it under "Your business" and "Facts & rules".',
								'chatbot'
							)
				),
				el(
					'div',
					{ className: 'qcwc-bc__actions' },
					el(
						C.Button,
						{ variant: 'primary', onClick: () => go( 'business' ) },
						__( 'Check my summary', 'chatbot' )
					),
					view.ai.ready && el( BuildBox, props )
				)
			);
		}
		const reads = readText( view );
		let aiPart;
		if ( ! view.ai.ready ) {
			aiPart = el(
				'p',
				{ className: 'qcwc-bc__warn' },
				view.ai.message ||
					__(
						'AI is not set up yet. Add an AI key to have it written for you.',
						'chatbot'
					),
				view.ai.url &&
					el(
						Fragment,
						null,
						' ',
						el(
							'a',
							{ href: view.ai.url },
							__( 'Set up AI', 'chatbot' )
						)
					)
			);
		} else if ( '' === reads ) {
			aiPart = el(
				'p',
				{ className: 'qcwc-bc__help' },
				__(
					'There is nothing for AI to read yet: publish some pages or connect a data source first, or write it yourself.',
					'chatbot'
				)
			);
		} else {
			aiPart = el( BuildBox, props );
		}
		return el(
			'div',
			{ className: 'qcwc-bc__start' },
			el(
				'div',
				{ className: 'qcwc-bc__option' },
				el( 'h3', null, __( 'Let AI write it', 'chatbot' ) ),
				el(
					'p',
					{ className: 'qcwc-bc__help' },
					'' !== reads
						? sprintf(
								/* translators: %s: what was read, e.g. "40 pages, 9 products and 5 other data sources". */
								__(
									'AI reads what was already found on your site: %s. You do not need to type anything first.',
									'chatbot'
								),
								reads
							)
						: __(
								'AI reads your website and the data sources you connect. You do not need to type anything first.',
								'chatbot'
							)
				),
				aiPart
			),
			el(
				'div',
				{ className: 'qcwc-bc__option' },
				el( 'h3', null, __( 'Write it yourself', 'chatbot' ) ),
				el(
					'p',
					{ className: 'qcwc-bc__help' },
					__(
						'A few short fields: what you do, your customers, your products. No AI needed.',
						'chatbot'
					)
				),
				el(
					C.Button,
					{ variant: 'secondary', onClick: () => go( 'business' ) },
					__( 'Fill it in myself', 'chatbot' )
				)
			)
		);
	}

	function Overview( props ) {
		const view = props.view;
		const s = view.summary || {};
		const sources = view.sources;
		const used = sources.filter( ( x ) => 'ok' === x.status ).length;
		const facts = s.facts || [];
		const docs = view.documents.items.length;
		const steps = [
			{
				id: 'business',
				done: !! view.summary,
				label: __( 'Your business summary', 'chatbot' ),
				detail: view.summary
					? s.business
					: __( 'Not filled in yet.', 'chatbot' ),
			},
			{
				id: 'rules',
				done: facts.length > 0,
				label: __( 'Facts to always get right', 'chatbot' ),
				detail: facts.length
					? sprintf(
							/* translators: %d: facts. */
							__( '%d facts', 'chatbot' ),
							facts.length
						)
					: __(
							'Prices, hours and guarantees AI tools must repeat exactly.',
							'chatbot'
						),
			},
			{
				id: 'documents',
				done: docs > 0,
				label: __( 'Documents', 'chatbot' ),
				detail: docs
					? sprintf(
							/* translators: %d: documents. */
							__( '%d documents', 'chatbot' ),
							docs
						)
					: __(
							'Price lists, brochures, service sheets.',
							'chatbot'
						),
			},
			{
				id: 'sources',
				done: used === sources.length,
				label: __( 'Data sources', 'chatbot' ),
				detail: sprintf(
					/* translators: 1: sources in use, 2: all sources. */
					__( '%1$d of %2$d in use', 'chatbot' ),
					used,
					sources.length
				),
			},
		];
		const done = steps.filter( ( x ) => x.done ).length;
		return el(
			Fragment,
			null,
			el(
				Card,
				{ className: 'qcwc-bc__hero' },
				el(
					'h2',
					{ className: 'qcwc-bc__card-title' },
					sprintf(
						/* translators: %s: product name, for example WPBot. */
						__( 'Tell %s about your business once', 'chatbot' ),
						PRODUCT
					)
				),
				el(
					'p',
					null,
					__(
						'Every AI feature (writing, marketing emails, SEO, replies to reviews, social posts) reads this before it writes, so the results sound like you and get your facts right.',
						'chatbot'
					)
				),
				el( StartBox, props ),
				view.stale &&
					view.summary &&
					el(
						'p',
						{ className: 'qcwc-bc__help' },
						__(
							'Your site or documents changed since this summary was made. Build it again to include the changes.',
							'chatbot'
						)
					)
			),
			el(
				Card,
				{
					title: sprintf(
						/* translators: 1: steps done, 2: all steps. */
						__( 'Your progress: %1$d of %2$d', 'chatbot' ),
						done,
						steps.length
					),
				},
				el(
					'div',
					{ className: 'qcwc-bc__meter' },
					el( 'span', {
						style: {
							width:
								Math.round( ( 100 * done ) / steps.length ) +
								'%',
						},
					} )
				),
				el(
					'ul',
					{ className: 'qcwc-bc__checklist' },
					steps.map( ( step ) =>
						el(
							'li',
							{
								key: step.id,
								className: step.done ? 'is-done' : '',
							},
							el( 'span', {
								className:
									'dashicons ' +
									( step.done
										? 'dashicons-yes-alt'
										: 'dashicons-marker' ),
								'aria-hidden': true,
							} ),
							el(
								'div',
								null,
								el( 'strong', null, step.label ),
								el(
									'p',
									{ className: 'qcwc-bc__help' },
									step.detail
								)
							),
							el(
								C.Button,
								{
									variant: step.done
										? 'tertiary'
										: 'secondary',
									onClick: () => go( step.id ),
								},
								step.done
									? __( 'Open', 'chatbot' )
									: __( 'Add', 'chatbot' )
							)
						)
					)
				)
			),
			el(
				Card,
				{ title: __( 'Used by', 'chatbot' ) },
				el(
					'div',
					{ className: 'qcwc-bc__chips' },
					[
						__( 'AI Writer', 'chatbot' ),
						__( 'SEO & AI Search', 'chatbot' ),
						__( 'AI Marketing', 'chatbot' ),
						__( 'Social posts', 'chatbot' ),
					].map( ( name ) =>
						el(
							'span',
							{ key: name, className: 'qcwc-bc__chip' },
							name
						)
					)
				)
			)
		);
	}

	function Field( { label, help, value, onChange, rows, disabled } ) {
		if ( rows ) {
			return el( C.TextareaControl, {
				label,
				help,
				rows,
				value,
				onChange,
				disabled,
				__nextHasNoMarginBottom: true,
			} );
		}
		return el( C.TextControl, {
			label,
			help,
			value,
			onChange,
			disabled,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
	}

	function BusinessTab( { form, set, can, buildProps } ) {
		return el(
			Card,
			{
				title: __( 'Your business', 'chatbot' ),
				intro: __(
					'The basics every AI feature should know. Keep it short.',
					'chatbot'
				),
			},
			! buildProps.view.summary &&
				buildProps.view.ai.ready &&
				'' !== readText( buildProps.view ) &&
				el(
					'div',
					{ className: 'qcwc-bc__build-inline' },
					el(
						'span',
						{ className: 'qcwc-bc__help' },
						__(
							'Rather not type? AI can write it from your website.',
							'chatbot'
						)
					),
					el( BuildBox, buildProps )
				),
			el(
				'div',
				{ className: 'qcwc-bc__grid' },
				el( Field, {
					label: __( 'What your business does', 'chatbot' ),
					help: __( 'One or two sentences.', 'chatbot' ),
					rows: 3,
					value: form.business,
					onChange: set( 'business' ),
					disabled: ! can,
				} ),
				el( Field, {
					label: __( 'Your customers', 'chatbot' ),
					help: __( 'Who buys from you.', 'chatbot' ),
					rows: 3,
					value: form.audience,
					onChange: set( 'audience' ),
					disabled: ! can,
				} )
			),
			el( Field, {
				label: __( 'Where you sell or serve', 'chatbot' ),
				help: __( 'Towns or areas, or "online".', 'chatbot' ),
				value: form.places,
				onChange: set( 'places' ),
				disabled: ! can,
			} ),
			el(
				'div',
				{ className: 'qcwc-bc__grid' },
				el( TagInput, {
					label: __( 'Products and services', 'chatbot' ),
					value: form.offers,
					onChange: set( 'offers' ),
					disabled: ! can,
				} ),
				el( TagInput, {
					label: __( 'What you do better', 'chatbot' ),
					value: form.strengths,
					onChange: set( 'strengths' ),
					disabled: ! can,
				} )
			)
		);
	}

	function RulesTab( { form, set, can } ) {
		return el(
			Card,
			{
				title: __( 'Facts & rules', 'chatbot' ),
				intro: __(
					'AI tools repeat your facts exactly and never use what you list under "Never say".',
					'chatbot'
				),
			},
			el( TagInput, {
				label: __( 'Facts to always get right', 'chatbot' ),
				help: __(
					'Prices, opening hours, guarantees, delivery times.',
					'chatbot'
				),
				placeholder: __(
					'For example: Open 9 to 6, closed Friday',
					'chatbot'
				),
				value: form.facts,
				sentences: true,
				onChange: set( 'facts' ),
				disabled: ! can,
			} ),
			el( TagInput, {
				label: __( 'Never say', 'chatbot' ),
				help: __(
					'Claims, words or topics to avoid.',
					'chatbot'
				),
				placeholder: __(
					'For example: cheapest in town',
					'chatbot'
				),
				value: form.avoid,
				onChange: set( 'avoid' ),
				disabled: ! can,
			} ),
			el( TagInput, {
				label: __( 'Words and phrases you use', 'chatbot' ),
				value: form.words,
				onChange: set( 'words' ),
				disabled: ! can,
			} )
		);
	}

	function QuestionsTab( { form, set, can, view, toggle } ) {
		const source = view.sources.find( ( x ) => 'questions' === x.id );
		return el(
			Card,
			{
				title: __( 'Customer questions', 'chatbot' ),
				intro: __(
					'What customers ask most. AI tools answer these in your content and replies.',
					'chatbot'
				),
			},
			source &&
				view.can.settings &&
				el( C.ToggleControl, {
					label: __(
						'Read the questions customers ask your chatbot',
						'chatbot'
					),
					help: __(
						'From chats in the last 6 months, without names or contact details. Build the summary again to add them.',
						'chatbot'
					),
					checked: !! view.settings.questions,
					onChange: toggle( 'questions' ),
					__nextHasNoMarginBottom: true,
				} ),
			el( TagInput, {
				label: __( 'What customers ask', 'chatbot' ),
				value: form.questions,
				sentences: true,
				onChange: set( 'questions' ),
				disabled: ! can,
			} )
		);
	}

	function Doc( { doc, view, setView, setMessage } ) {
		const [ text, setText ] = useState( doc.summary );
		const [ busy, setBusy ] = useState( '' );
		const [ open, setOpen ] = useState( false );
		useEffect( () => {
			setText( doc.summary );
		}, [ doc.summary ] );
		const act = ( key, path, data ) => {
			setBusy( key );
			setMessage( '' );
			api( path, data || {} )
				.then( setView )
				.catch( ( e ) => setMessage( errorText( e ) ) )
				.finally( () => setBusy( '' ) );
		};
		const base = '/context/docs/' + doc.id;
		return el(
			'li',
			{ className: 'qcwc-bc__doc' },
			el(
				'div',
				{ className: 'qcwc-bc__doc-head' },
				el( 'span', {
					className: 'dashicons dashicons-media-document',
					'aria-hidden': true,
				} ),
				el(
					'div',
					null,
					el( 'strong', null, doc.name ),
					el(
						'p',
						{ className: 'qcwc-bc__help' },
						doc.kind.toUpperCase() +
							', ' +
							Math.max( 1, Math.round( doc.size / 1024 ) ) +
							' KB' +
							( doc.edited
								? ' · ' +
									__(
										'summary edited by you',
										'chatbot'
									)
								: '' )
					)
				),
				el(
					C.Button,
					{
						variant: 'tertiary',
						'aria-expanded': open,
						onClick: () => setOpen( ! open ),
					},
					open
						? __( 'Hide summary', 'chatbot' )
						: __( 'Show summary', 'chatbot' )
				)
			),
			open &&
				el(
					'div',
					{ className: 'qcwc-bc__doc-body' },
					el( C.TextareaControl, {
						label: sprintf(
						/* translators: %s: product name, for example WPBot. */
						__( 'What %s keeps from it', 'chatbot' ),
						PRODUCT
					),
						rows: 5,
						value: text,
						onChange: setText,
						disabled: ! view.can.use,
						__nextHasNoMarginBottom: true,
					} ),
					view.can.use &&
						el(
							'div',
							{ className: 'qcwc-bc__actions' },
							el(
								C.Button,
								{
									variant: 'secondary',
									isBusy: 'save' === busy,
									disabled:
										!! busy ||
										text === doc.summary ||
										! text.trim(),
									onClick: () =>
										act( 'save', base, { summary: text } ),
								},
								__( 'Save', 'chatbot' )
							),
							'pdf' !== doc.kind &&
								el(
									C.Button,
									{
										variant: 'tertiary',
										isBusy: 'again' === busy,
										disabled: !! busy || ! view.ai.ready,
										onClick: () =>
											act( 'again', base + '/summarize' ),
									},
									sprintf(
										/* translators: %s: cost. */
										__(
											'Summarise again (%s)',
											'chatbot'
										),
										cost( view.documents.estimate )
									)
								)
						),
					view.can.use &&
						el(
							C.Button,
							{
								variant: 'tertiary',
								isDestructive: true,
								isBusy: 'delete' === busy,
								disabled: !! busy,
								onClick: () =>
									act( 'delete', base + '/delete' ),
							},
							__( 'Delete', 'chatbot' )
						)
				)
		);
	}

	function DocumentsTab( { view, setView, setMessage } ) {
		const [ file, setFile ] = useState( null );
		const [ busy, setBusy ] = useState( false );
		const [ inputKey, setInputKey ] = useState( 0 );
		const docs = view.documents;
		const full = docs.items.length >= docs.max;
		const upload = () => {
			const body = new window.FormData();
			body.append( 'file', file );
			setBusy( true );
			setMessage( '' );
			wp.apiFetch( {
				path: '/wpbot-core/v1/context/docs',
				method: 'POST',
				body,
			} )
				.then( ( v ) => {
					setView( v );
					setFile( null );
					setInputKey( inputKey + 1 );
					setMessage(
						__(
							'Document read and summarised. Build the business summary again (Overview) to include it.',
							'chatbot'
						)
					);
				} )
				.catch( ( e ) => setMessage( errorText( e ) ) )
				.finally( () => setBusy( false ) );
		};
		return el(
			Card,
			{
				title: __( 'Documents', 'chatbot' ),
				intro: sprintf(
					/* translators: 1: cost per file, 2: most files. */
					__(
						'Price lists, brochures, service sheets or your "about us". Each file is read once and summarised by AI (about %1$s per file), then deleted; you can edit the summary. Up to %2$d files of 10 MB: PDF, Word (.docx), text (.txt, .md) or CSV.',
						'chatbot'
					),
					cost( docs.estimate ),
					docs.max
				),
			},
			view.can.use &&
				! full &&
				el(
					'div',
					{ className: 'qcwc-bc__upload' },
					el( 'input', {
						key: inputKey,
						type: 'file',
						accept: '.pdf,.docx,.txt,.md,.csv',
						onChange: ( e ) =>
							setFile(
								e.target.files && e.target.files[ 0 ]
									? e.target.files[ 0 ]
									: null
							),
					} ),
					el(
						C.Button,
						{
							variant: 'primary',
							isBusy: busy,
							disabled: busy || ! file || ! view.ai.ready,
							onClick: upload,
						},
						__( 'Upload and summarise', 'chatbot' )
					)
				),
			! view.ai.ready &&
				el(
					'p',
					{ className: 'qcwc-bc__warn' },
					__(
						'Documents are summarised by AI: set up AI first.',
						'chatbot'
					),
					view.ai.url &&
						el(
							Fragment,
							null,
							' ',
							el(
								'a',
								{ href: view.ai.url },
								__( 'Set up AI', 'chatbot' )
							)
						)
				),
			el(
				'p',
				{ className: 'qcwc-bc__help' },
				docs.reads_pdf
					? __(
							'PDF files are sent to your AI provider to be read. Scanned PDFs (pictures of text) may not be readable.',
							'chatbot'
						)
					: __(
							'Your AI provider cannot read PDF files: save a PDF as a Word or text file first, or use OpenAI, Claude or Gemini.',
							'chatbot'
						)
			),
			full &&
				el(
					'p',
					{ className: 'qcwc-bc__help' },
					__(
						'You have the most documents. Delete one to add another.',
						'chatbot'
					)
				),
			docs.items.length > 0
				? el(
						'ul',
						{ className: 'qcwc-bc__docs' },
						docs.items.map( ( doc ) =>
							el( Doc, {
								key: doc.id,
								doc,
								view,
								setView,
								setMessage,
							} )
						)
					)
				: el(
						'p',
						{ className: 'qcwc-bc__empty' },
						__( 'No documents yet.', 'chatbot' )
					)
		);
	}

	function SourceCard( { s, run, busy, can } ) {
		const labels = {
			ok: __( 'In use', 'chatbot' ),
			missing: __( 'Missing', 'chatbot' ),
			off: __( 'Not used', 'chatbot' ),
		};
		let action = null;
		if ( s.action && s.action.url ) {
			action = el(
				'a',
				{ className: 'button button-secondary', href: s.action.url },
				s.action.label
			);
		} else if ( s.action ) {
			action = el(
				C.Button,
				{
					variant: 'secondary',
					isBusy: busy === s.action.op,
					disabled: !! busy || ! can,
					onClick: () => run( s.action.op ),
				},
				s.action.label
			);
		}
		return el(
			'li',
			{ className: 'qcwc-bc__source is-' + s.status },
			el(
				'div',
				{ className: 'qcwc-bc__source-head' },
				el( 'strong', null, s.label ),
				el(
					'span',
					{ className: 'qcwc-bc__status is-' + s.status },
					labels[ s.status ] || s.status
				)
			),
			s.detail &&
				el( 'p', { className: 'qcwc-bc__source-detail' }, s.detail ),
			'ok' !== s.status &&
				s.adds &&
				el( 'p', { className: 'qcwc-bc__help' }, s.adds ),
			action
		);
	}

	function Job( { jobId, onDone } ) {
		const [ job, setJob ] = useState( null );
		useEffect( () => {
			let stop = false;
			const tick = () =>
				api( '/jobs/' + jobId + '/tick', {} )
					.then( ( j ) => {
						if ( stop ) {
							return;
						}
						setJob( j );
						if (
							[
								'done',
								'failed',
								'cancelled',
								'paused',
							].includes( j.status )
						) {
							onDone();
						} else {
							setTimeout( tick, 800 );
						}
					} )
					.catch( () => ! stop && setTimeout( tick, 3000 ) );
			tick();
			return () => {
				stop = true;
			};
		}, [ jobId ] );
		if ( ! job ) {
			return el( C.Spinner );
		}
		return el(
			'p',
			{ className: 'qcwc-bc__help' },
			sprintf(
				/* translators: 1: pages done, 2: all pages. */
				__(
					'Building the knowledge base: %1$d of %2$d pages…',
					'chatbot'
				),
				job.done || 0,
				job.total || 0
			)
		);
	}

	function SourcesTab( { view, run, busy } ) {
		const [ showUsed, setShowUsed ] = useState( false );
		const todo = view.sources.filter( ( s ) => 'ok' !== s.status );
		const used = view.sources.filter( ( s ) => 'ok' === s.status );
		return el(
			Card,
			{
				title: __( 'Data sources', 'chatbot' ),
				intro: sprintf(
						/* translators: %s: product name, for example WPBot. */
						__( 'The more of these %s can read, the better every AI result.', 'chatbot' ),
						PRODUCT
					),
			},
			todo.length > 0 &&
				el(
					Fragment,
					null,
					el(
						'h3',
						{ className: 'qcwc-bc__subtitle' },
						__( 'Add these', 'chatbot' )
					),
					el(
						'ul',
						{ className: 'qcwc-bc__sources' },
						todo.map( ( s ) =>
							el( SourceCard, {
								key: s.id,
								s,
								run,
								busy,
								can: view.can.use,
							} )
						)
					)
				),
			el(
				'div',
				{ className: 'qcwc-bc__used' },
				el(
					'p',
					null,
					el(
						'strong',
						null,
						sprintf(
							/* translators: %d: sources. */
							__( 'In use (%d):', 'chatbot' ),
							used.length
						)
					),
					' ' + used.map( ( s ) => s.label ).join( ', ' ) + ' ',
					used.length > 0 &&
						el(
							C.Button,
							{
								variant: 'link',
								onClick: () => setShowUsed( ! showUsed ),
							},
							showUsed
								? __( 'Hide details', 'chatbot' )
								: __( 'Show details', 'chatbot' )
						)
				),
				showUsed &&
					el(
						'ul',
						{ className: 'qcwc-bc__sources' },
						used.map( ( s ) =>
							el( SourceCard, {
								key: s.id,
								s,
								run,
								busy,
								can: view.can.use,
							} )
						)
					)
			),
			el(
				'div',
				{ className: 'qcwc-bc__actions' },
				el(
					C.Button,
					{
						variant: 'secondary',
						isBusy: 'scan' === busy,
						disabled: !! busy || ! view.can.use,
						onClick: () => run( 'scan' ),
					},
					__( 'Read everything again', 'chatbot' )
				),
				view.scanned_at &&
					el(
						'span',
						{ className: 'qcwc-bc__help' },
						sprintf(
							/* translators: %s: date. */
							__( 'Last read %s', 'chatbot' ),
							view.scanned_at.slice( 0, 10 )
						)
					)
			)
		);
	}

	function SettingsTab( { view, toggle, run, busy, jobId, onJobDone } ) {
		const knowledge = view.sources.find( ( x ) => 'knowledge' === x.id );
		const questions = view.sources.find( ( x ) => 'questions' === x.id );
		const can = view.can.settings;
		return el(
			Card,
			{ title: __( 'Settings', 'chatbot' ) },
			el( C.ToggleControl, {
				label: __( 'Keep it up to date', 'chatbot' ),
				help: sprintf(
						/* translators: %s: product name, for example WPBot. */
						__( 'Each week %s reads your site again. When it changed, and you have not edited the summary, it builds a new one with AI (a few cents).', 'chatbot' ),
						PRODUCT
					),
				checked: !! view.settings.auto_update,
				onChange: toggle( 'auto_update' ),
				disabled: ! can,
				__nextHasNoMarginBottom: true,
			} ),
			knowledge &&
				el( C.ToggleControl, {
					label: __(
						"Use your chatbot's knowledge base",
						'chatbot'
					),
					help: __(
						'The answers your chatbot already gives: prices, policies, opening hours.',
						'chatbot'
					),
					checked: !! view.settings.knowledge,
					onChange: toggle( 'knowledge' ),
					disabled: ! can,
					__nextHasNoMarginBottom: true,
				} ),
			knowledge &&
				knowledge.action &&
				'knowledge' === knowledge.action.op &&
				el(
					C.Button,
					{
						variant: 'secondary',
						isBusy: 'knowledge' === busy,
						disabled: !! busy || ! view.can.use,
						onClick: () => run( 'knowledge' ),
					},
					knowledge.action.label
				),
			!! jobId && el( Job, { jobId, onDone: onJobDone } ),
			questions &&
				el( C.ToggleControl, {
					label: __(
						'Read the questions customers ask your chatbot',
						'chatbot'
					),
					help: __(
						'From chats in the last 6 months, without names or contact details.',
						'chatbot'
					),
					checked: !! view.settings.questions,
					onChange: toggle( 'questions' ),
					disabled: ! can,
					__nextHasNoMarginBottom: true,
				} )
		);
	}

	const FORM_TABS = [ 'business', 'rules', 'questions' ];

	function App() {
		const [ view, setView ] = useState( null );
		const [ tab, setTab ] = useState(
			root.getAttribute( 'data-tab' ) || 'overview'
		);
		const [ form, setForm ] = useState( toForm( null ) );
		const [ message, setMessage ] = useState( '' );
		const [ busy, setBusy ] = useState( '' );
		const [ saving, setSaving ] = useState( false );
		const [ jobId, setJobId ] = useState( 0 );

		const receive = ( v ) => {
			setView( v );
			setForm( toForm( v.summary ) );
		};
		useEffect( () => {
			api( '/context' )
				.then( receive )
				.catch( ( e ) => setMessage( errorText( e ) ) );
			const onNavigate = ( e ) => {
				if ( e.detail && e.detail.id ) {
					e.preventDefault();
					setTab( e.detail.id );
					setMessage( '' );
					window.scrollTo( 0, 0 );
				}
			};
			if ( shell ) {
				shell.addEventListener( 'wpbot-shell:navigate', onNavigate );
			}
			return () =>
				shell &&
				shell.removeEventListener( 'wpbot-shell:navigate', onNavigate );
		}, [] );
		useEffect( () => {
			if ( view && window.wpbotShell && window.wpbotShell.setBadge ) {
				window.wpbotShell.setBadge(
					'sources',
					view.sources.filter( ( s ) => 'missing' === s.status )
						.length || ''
				);
				window.wpbotShell.setBadge(
					'documents',
					view.documents.items.length || ''
				);
			}
		}, [ view ] );
		if ( ! view ) {
			return message
				? el(
						C.Notice,
						{ status: 'error', isDismissible: false },
						message
					)
				: el( C.Spinner );
		}
		const saved = toForm( view.summary );
		const dirty = JSON.stringify( saved ) !== JSON.stringify( form );
		const set = ( key ) => ( value ) =>
			setForm( Object.assign( {}, form, { [ key ]: value } ) );
		const save = () => {
			setSaving( true );
			setMessage( '' );
			api( '/context/save', {
				summary: Object.assign( {}, view.summary || {}, form ),
			} )
				.then( ( v ) => {
					receive( v );
					setMessage(
						__(
							'Saved. Every AI feature uses it now.',
							'chatbot'
						)
					);
				} )
				.catch( ( e ) => setMessage( errorText( e ) ) )
				.finally( () => setSaving( false ) );
		};
		const run = ( op ) => {
			setBusy( op );
			setMessage( '' );
			let call;
			if ( 'scan' === op ) {
				call = api( '/context/scan', {} ).then( receive );
			} else if ( 'knowledge' === op ) {
				call = api( '/context/knowledge', {} ).then( ( r ) =>
					setJobId( r.job_id )
				);
			} else if ( 0 === op.indexOf( 'tab:' ) ) {
				go( op.slice( 4 ) );
				call = Promise.resolve();
			} else if ( 0 === op.indexOf( 'on:' ) ) {
				call = api( '/context/settings', {
					[ op.slice( 3 ) ]: true,
				} ).then( receive );
			} else {
				call = Promise.resolve();
			}
			call.catch( ( e ) => setMessage( errorText( e ) ) ).finally( () =>
				setBusy( '' )
			);
		};
		const toggle = ( key ) => ( value ) =>
			api( '/context/settings', { [ key ]: value } )
				.then( receive )
				.catch( ( e ) => setMessage( errorText( e ) ) );
		const buildProps = { view, setView: receive, setMessage };
		const can = view.can.use;
		let page;
		switch ( tab ) {
			case 'business':
				page = el( BusinessTab, { form, set, can, buildProps } );
				break;
			case 'rules':
				page = el( RulesTab, { form, set, can } );
				break;
			case 'questions':
				page = el( QuestionsTab, { form, set, can, view, toggle } );
				break;
			case 'documents':
				page = el( DocumentsTab, {
					view,
					setView: receive,
					setMessage,
				} );
				break;
			case 'sources':
				page = el( SourcesTab, { view, run, busy } );
				break;
			case 'settings':
				page = el( SettingsTab, {
					view,
					toggle,
					run,
					busy,
					jobId,
					onJobDone: () => {
						setJobId( 0 );
						run( 'scan' );
					},
				} );
				break;
			default:
				page = el( Overview, buildProps );
		}
		return el(
			'div',
			{ className: 'qcwc-bc__page', 'data-tab': tab },
			message &&
				el(
					C.Notice,
					{ status: 'info', onRemove: () => setMessage( '' ) },
					message
				),
			page,
			FORM_TABS.includes( tab ) &&
				can &&
				el( SaveBar, {
					dirty,
					busy: saving,
					disabled: ! form.business.trim() && ! form.offers.length,
					onSave: save,
					onReset: () => setForm( saved ),
				} )
		);
	}

	const render = wp.element.createRoot
		? ( node ) => wp.element.createRoot( root ).render( node )
		: ( node ) => wp.element.render( node, root );
	render( el( App ) );
} )( window.wp );
