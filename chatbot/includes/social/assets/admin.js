/**
 * WPBot Social admin screens: small actions through the REST API.
 * Plain browser JavaScript (no build step). Every text shown comes from
 * the server (escaped) or from qcldWpbotSocial.i18n.
 */
( function () {
	'use strict';

	var cfg = window.qcldWpbotSocial || {};
	var t = cfg.i18n || {};

	function api( method, path, body ) {
		var options = {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' },
		};
		if ( body !== undefined ) {
			options.headers[ 'Content-Type' ] = 'application/json';
			options.body = JSON.stringify( body );
		}
		return fetch( cfg.rest + path, options ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( ( data && data.message ) || t.error );
				}
				return data;
			} );
		} );
	}

	function status( root, text, isError ) {
		var el = root.querySelector( '[data-qcsoc-status]' );
		if ( el ) {
			el.textContent = text || '';
			el.classList.toggle( 'is-error', !! isError );
		}
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'className' ) {
				node.className = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		if ( text instanceof Node ) {
			node.appendChild( text );
		} else if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	/* Accounts (screen 01) */
	document.querySelectorAll( '[data-qcsoc="accounts"] tr[data-account]' ).forEach( function ( row ) {
		var id = row.getAttribute( 'data-account' );
		var toggle = row.querySelector( '[data-qcsoc-autoshare]' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				api( 'PATCH', 'accounts/' + id, { auto_share: toggle.checked } ).catch( function ( error ) {
					toggle.checked = ! toggle.checked;
					window.alert( error.message ); // eslint-disable-line no-alert
				} );
			} );
		}
		var remove = row.querySelector( '[data-qcsoc-disconnect]' );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				if ( ! window.confirm( row.hasAttribute( 'data-qcsoc-in-use' ) ? t.confirmUsed : t.confirmOne ) ) { // eslint-disable-line no-alert
					return;
				}
				api( 'DELETE', 'accounts/' + id ).then( function () {
					row.remove();
				} ).catch( function ( error ) {
					window.alert( error.message ); // eslint-disable-line no-alert
				} );
			} );
		}
	} );

	/* Connect (screen 03) */
	var connect = document.querySelector( '[data-qcsoc="connect"]' );
	if ( connect ) {
		var app = connect.getAttribute( 'data-app' );
		var copy = connect.querySelector( '[data-qcsoc-copy]' );
		if ( copy && navigator.clipboard ) {
			copy.addEventListener( 'click', function () {
				navigator.clipboard.writeText( connect.querySelector( '[data-qcsoc-copy-source]' ).textContent.trim() ).then( function () {
					copy.lastChild.textContent = ' ' + t.copied;
				} );
			} );
		}
		var save = connect.querySelector( '[data-qcsoc-save-app]' );
		if ( save ) {
			save.addEventListener( 'click', function () {
				var secret = connect.querySelector( '[name="client_secret"]' );
				api( 'POST', 'apps/' + app, {
					client_id: connect.querySelector( '[name="client_id"]' ).value,
					client_secret: secret.value,
				} ).then( function ( data ) {
					secret.value = '';
					secret.placeholder = data.secret_hint || '';
					connect.querySelector( '[data-qcsoc-connect]' ).disabled = ! ( data.client_id && data.has_secret );
					status( connect, t.saved );
				} ).catch( function ( error ) {
					status( connect, error.message, true );
				} );
			} );
		}
		var go = connect.querySelector( '[data-qcsoc-connect]' );
		if ( go ) {
			go.addEventListener( 'click', function () {
				var scopes = Array.prototype.map.call( connect.querySelectorAll( '[name="scopes[]"]:checked' ), function ( box ) {
					return box.value;
				} );
				api( 'POST', 'connect/' + app, { scopes: scopes } ).then( function ( data ) {
					window.location.assign( data.url );
				} ).catch( function ( error ) {
					status( connect, error.message, true );
				} );
			} );
		}
		var tg = connect.querySelector( '[data-qcsoc-telegram]' );
		if ( tg ) {
			tg.addEventListener( 'click', function () {
				api( 'POST', 'telegram', { channel: connect.querySelector( '[name="channel"]' ).value } ).then( function () {
					window.location.assign( cfg.admin + '&view=accounts' );
				} ).catch( function ( error ) {
					status( connect, error.message, true );
				} );
			} );
		}
		var tgSave = connect.querySelector( '[data-qcsoc-tg-save]' );
		if ( tgSave ) {
			tgSave.addEventListener( 'click', function () {
				var field = connect.querySelector( '[name="tg_token"]' );
				var box = connect.querySelector( '[data-qcsoc-tg-bot]' );
				api( 'POST', 'telegram/bot', { token: field.value } ).then( function () {
					field.value = '';
					window.location.reload();
				} ).catch( function ( error ) {
					field.value = '';
					status( box, error.message, true );
				} );
			} );
		}
		var tgForget = connect.querySelector( '[data-qcsoc-tg-forget]' );
		if ( tgForget ) {
			tgForget.addEventListener( 'click', function () {
				if ( Number( tgForget.getAttribute( 'data-qcsoc-channels' ) ) > 0 && ! window.confirm( t.tgForget ) ) { // eslint-disable-line no-alert
					return;
				}
				api( 'DELETE', 'telegram/bot' ).then( function () {
					window.location.reload();
				} ).catch( function ( error ) {
					status( connect.querySelector( '[data-qcsoc-tg-bot]' ), error.message, true );
				} );
			} );
		}
		var load = connect.querySelector( '[data-qcsoc-google-load]' );
		if ( load ) {
			var gbox = connect.querySelector( '[data-qcsoc-google]' ) || connect;
			load.addEventListener( 'click', function () {
				var list = connect.querySelector( '[data-qcsoc-google-list]' );
				api( 'GET', 'google/locations' ).then( function ( locations ) {
					list.textContent = '';
					locations.forEach( function ( location ) {
						var item = el( 'li' );
						item.appendChild( el( 'span', {}, location.title ) );
						var add = el( 'button', { type: 'button', className: 'button' }, '+' );
						add.addEventListener( 'click', function () {
							api( 'POST', 'google/locations', location ).then( function () {
								add.disabled = true;
								status( gbox, t.saved );
							} ).catch( function ( error ) {
								status( gbox, error.message, true );
							} );
						} );
						item.appendChild( add );
						list.appendChild( item );
					} );
				} ).catch( function ( error ) {
					status( gbox, error.message, true );
				} );
			} );
		}
	}

	/* Google connection card (WPBot Core's one Google connection, set up in Social) */
	Array.prototype.forEach.call( document.querySelectorAll( '[data-qcsoc-google-card]' ), function ( card ) {
		var purpose = card.getAttribute( 'data-purpose' ) || 'business';
		var copyBtn = card.querySelector( '[data-qcsoc-google-copy]' );
		if ( copyBtn && navigator.clipboard ) {
			copyBtn.addEventListener( 'click', function () {
				navigator.clipboard.writeText( card.querySelector( '[data-qcsoc-google-redirect]' ).textContent.trim() ).then( function () {
					copyBtn.lastChild.textContent = ' ' + t.copied;
				} );
			} );
		}
		var saveClient = card.querySelector( '[data-qcsoc-google-save]' );
		if ( saveClient ) {
			saveClient.addEventListener( 'click', function () {
				var secret = card.querySelector( '[name="google_client_secret"]' );
				api( 'POST', 'google/connection', {
					purpose: purpose,
					client_id: card.querySelector( '[name="google_client_id"]' ).value,
					client_secret: secret.value,
				} ).then( function ( data ) {
					secret.value = '';
					secret.placeholder = data.secret_hint || '';
					card.querySelector( '[data-qcsoc-google-connect]' ).disabled = ! data.client;
					status( card, t.saved );
				} ).catch( function ( error ) {
					status( card, error.message, true );
				} );
			} );
		}
		var connectBtn = card.querySelector( '[data-qcsoc-google-connect]' );
		if ( connectBtn ) {
			connectBtn.addEventListener( 'click', function () {
				api( 'POST', 'google/connect', { return_url: window.location.href } ).then( function ( data ) {
					window.location.assign( data.url );
				} ).catch( function ( error ) {
					status( card, error.message, true );
				} );
			} );
		}
		var disconnectBtn = card.querySelector( '[data-qcsoc-google-disconnect]' );
		if ( disconnectBtn ) {
			disconnectBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( t.gDisconnect ) ) { // eslint-disable-line no-alert
					return;
				}
				api( 'POST', 'google/disconnect' ).then( function () {
					window.location.reload();
				} ).catch( function ( error ) {
					status( card, error.message, true );
				} );
			} );
		}
	} );
	Array.prototype.forEach.call( document.querySelectorAll( '[data-qcsoc-gbp]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var step = button.closest( '[data-qcsoc-gbp-step]' ) || document;
			api( 'POST', 'google/business-profile', { on: 'on' === button.getAttribute( 'data-qcsoc-gbp' ) } ).then( function () {
				window.location.reload();
			} ).catch( function ( error ) {
				status( step, error.message, true );
			} );
		} );
	} );

	/* Choose Pages (screen 04) */
	var pages = document.querySelector( '[data-qcsoc="pages"]' );
	if ( pages ) {
		var brands = JSON.parse( pages.getAttribute( 'data-brands' ) || '{}' );
		var body = pages.querySelector( '[data-qcsoc-pages-body]' );
		var saveBtn = pages.querySelector( '[data-qcsoc-pages-save]' );
		var count = pages.querySelector( '[data-qcsoc-pages-count]' );
		var recount = function () {
			var n = pages.querySelectorAll( 'input[type=checkbox]:checked' ).length;
			count.textContent = ( t.selected || '%d' ).replace( '%d', n );
			saveBtn.disabled = n === 0;
		};
		api( 'GET', 'meta/pages' ).then( function ( data ) {
			pages.querySelector( '[data-qcsoc-pages-who]' ).textContent = data.name;
			body.textContent = '';
			var table = el( 'table', { className: 'qcsoc-table' } );
			var tbody = el( 'tbody' );
			data.pages.forEach( function ( page ) {
				var row = el( 'tr', { 'data-page': page.id } );
				var pick = el( 'input', { type: 'checkbox', 'data-kind': 'page' } );
				pick.checked = page.can_publish;
				pick.disabled = ! page.can_publish;
				var c1 = el( 'td' );
				c1.appendChild( pick );
				row.appendChild( c1 );
				row.appendChild( el( 'td', {}, page.name ) );
				var c3 = el( 'td' );
				if ( page.instagram ) {
					var ig = el( 'input', { type: 'checkbox', 'data-kind': 'instagram' } );
					ig.checked = page.can_publish;
					ig.disabled = ! page.can_publish;
					c3.appendChild( ig );
					c3.appendChild( document.createTextNode( ' @' + page.instagram.username ) );
				} else {
					c3.textContent = '—';
				}
				row.appendChild( c3 );
				var c4 = el( 'td' );
				if ( Object.keys( brands ).length ) {
					var select = el( 'select', { 'data-kind': 'brand' } );
					select.appendChild( el( 'option', { value: '0' }, '—' ) );
					Object.keys( brands ).forEach( function ( id ) {
						select.appendChild( el( 'option', { value: id }, brands[ id ] ) );
					} );
					c4.appendChild( select );
				}
				row.appendChild( c4 );
				row.appendChild( el( 'td', { className: page.can_publish ? '' : 'qcsoc-warn' }, page.can_publish ? '' : 'Can’t publish: needs "Create content"' ) );
				tbody.appendChild( row );
			} );
			table.appendChild( tbody );
			body.appendChild( table );
			body.addEventListener( 'change', recount );
			recount();
		} ).catch( function ( error ) {
			body.textContent = error.message;
		} );
		saveBtn.addEventListener( 'click', function () {
			var selection = [];
			body.querySelectorAll( 'tr[data-page]' ).forEach( function ( row ) {
				var page = row.querySelector( '[data-kind=page]' );
				if ( page && page.checked ) {
					var ig = row.querySelector( '[data-kind=instagram]' );
					var brand = row.querySelector( '[data-kind=brand]' );
					selection.push( {
						page_id: row.getAttribute( 'data-page' ),
						instagram: !! ( ig && ig.checked ),
						brand_id: brand ? parseInt( brand.value, 10 ) : 0,
					} );
				}
			} );
			api( 'POST', 'meta/pages', { pages: selection } ).then( function () {
				window.location.assign( cfg.admin + '&view=accounts' );
			} ).catch( function ( error ) {
				status( pages, error.message, true );
			} );
		} );
	}

	/* Settings › Advanced (screen 26) */
	var settings = document.querySelector( '[data-qcsoc="settings"]' );
	if ( settings ) {
		var endpoint = settings.getAttribute( 'data-qcsoc-endpoint' ) || 'settings';
		settings.querySelectorAll( '[data-qcsoc-setting]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var change = {};
				var value = input.type === 'checkbox' ? input.checked : input.value;
				if ( 'multi' === input.getAttribute( 'data-qcsoc-format' ) ) {
					value = Array.prototype.map.call( settings.querySelectorAll( '[name="' + input.name + '"]:checked' ), function ( box ) {
						return box.value;
					} );
				}
				if ( 'select-multi' === input.getAttribute( 'data-qcsoc-format' ) ) {
					value = Array.prototype.map.call( input.selectedOptions, function ( option ) {
						return option.value;
					} );
				}
				if ( 'sets' === input.getAttribute( 'data-qcsoc-format' ) ) {
					// "Name: #a #b" per line -> [{name, tags}]
					value = value.split( '\n' ).map( function ( line ) {
						var at = line.indexOf( ':' );
						return at > 0 ? { name: line.slice( 0, at ).trim(), tags: line.slice( at + 1 ).trim() } : null;
					} ).filter( Boolean );
				}
				// Dotted names ("networks.facebook.technique") become nested objects.
				var path = input.name.split( '.' );
				var node = change;
				path.slice( 0, -1 ).forEach( function ( key ) {
					node[ key ] = node[ key ] || {};
					node = node[ key ];
				} );
				node[ path[ path.length - 1 ] ] = value;
				if ( 'networks' === path[ 0 ] ) {
					// Send every network's choice so one change doesn't drop the others.
					change.networks = {};
					settings.querySelectorAll( '[name^="networks."]' ).forEach( function ( other ) {
						var parts = other.name.split( '.' );
						change.networks[ parts[ 1 ] ] = { technique: other.value };
					} );
				}
				api( 'POST', endpoint, change ).then( function () {
					status( settings, t.saved );
				} ).catch( function ( error ) {
					status( settings, error.message, true );
				} );
			} );
		} );
		var on = function ( selector, type, handler ) {
			var node = settings.querySelector( selector );
			if ( node ) {
				node.addEventListener( type, handler );
			}
		};
		on( '[data-qcsoc-check]', 'click', function () {
			var out = settings.querySelector( '[data-qcsoc-check-status]' );
			out.textContent = t.checking;
			api( 'POST', 'tokens/check' ).then( function ( data ) {
				out.textContent = data.checked + ' / ' + data.expired;
				window.location.reload();
			} ).catch( function ( error ) {
				out.textContent = error.message;
			} );
		} );
		on( '[data-qcsoc-export]', 'click', function () {
			api( 'GET', 'export' ).then( function ( data ) {
				var link = el( 'a', { href: URL.createObjectURL( new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } ) ), download: 'wpbot-social-settings.json' } );
				link.click();
			} );
		} );
		on( '[data-qcsoc-import]', 'change', function ( event ) {
			var file = event.target.files[ 0 ];
			if ( ! file ) {
				return;
			}
			file.text().then( function ( text ) {
				return api( 'POST', 'import', JSON.parse( text ) );
			} ).then( function () {
				window.location.reload();
			} ).catch( function ( error ) {
				status( settings, error.message, true );
			} );
		} );
		on( '[data-qcsoc-disconnect-all]', 'click', function () {
			if ( ! window.confirm( t.confirmAll ) ) { // eslint-disable-line no-alert
				return;
			}
			api( 'POST', 'disconnect-all' ).then( function () {
				window.location.assign( cfg.admin + '&view=accounts' );
			} ).catch( function ( error ) {
				status( settings, error.message, true );
			} );
		} );
	}
	/* Create post (screen 07) */
	var compose = document.querySelector( '[data-qcsoc="compose"]' );
	if ( compose ) {
		var accounts = JSON.parse( compose.getAttribute( 'data-accounts' ) || '[]' );
		var textarea = compose.querySelector( '[data-qcsoc-text]' );
		var tabs = compose.querySelector( '[data-qcsoc-tabs]' );
		var state = { tab: '', text: '', overrides: {}, images: [] };
		var timer = null;
		var counts = {};
		var labels = {};
		accounts.forEach( function ( a ) {
			labels[ a.network ] = compose.querySelector( 'input[value="' + a.id + '"]' ).parentNode.querySelector( '.qcsoc-chip__net' ).textContent;
		} );
		var params = new URLSearchParams( window.location.search );
		var prefill = compose.getAttribute( 'data-prefill' ) ? JSON.parse( compose.getAttribute( 'data-prefill' ) ) : null;
		if ( prefill ) {
			compose.querySelector( '[name=title]' ).value = prefill.title || '';
			compose.querySelector( '[name=link]' ).value = prefill.link || '';
			textarea.value = prefill.text || '';
			state.text = textarea.value;
			state.overrides = prefill.overrides || {};
			state.draftId = prefill.draft_id;
			state.flags = prefill.flags || {};
			state.source = prefill.source || {};
			state.formats = {};
			Object.keys( prefill.extras || {} ).forEach( function ( net ) {
				var saved = prefill.extras[ net ] || {};
				if ( 'google_business' !== net ) {
					state.formats[ net ] = {
						posts: Array.isArray( saved.posts ) ? saved.posts.join( '\n\n' ) : ( saved.posts || '' ),
						first_comment: saved.first_comment || '',
						ig_format: saved.ig_format || '',
						yt_privacy: saved.yt_privacy || '',
					};
				}
			} );
			var gbpSaved = ( prefill.extras || {} ).google_business || {};
			Object.keys( gbpSaved ).forEach( function ( name ) {
				var input = compose.querySelector( '[name=' + name + ']' );
				if ( input ) {
					input.value = gbpSaved[ name ];
				}
			} );
			compose.querySelectorAll( '[name="accounts[]"]' ).forEach( function ( box ) {
				box.checked = prefill.accounts.indexOf( parseInt( box.value, 10 ) ) >= 0;
			} );
			if ( prefill.schedule ) {
				compose.querySelector( '[name=schedule]' ).value = prefill.schedule;
			}
			state.images = prefill.images || [];
			state.video = prefill.video || null;
			var flagged = Object.keys( prefill.flags || {} );
			var flagBox = compose.querySelector( '[data-qcsoc-flags]' );
			if ( flagBox && flagged.length ) {
				compose.querySelector( '[data-qcsoc-flags-wrap]' ).hidden = false;
				flagged.forEach( function ( net ) {
					prefill.flags[ net ].forEach( function ( flag ) {
						flagBox.appendChild( el( 'li', {}, ( labels[ net ] || net ) + ': ' + flag ) );
					} );
				} );
			}
		}
		if ( params.get( 'date' ) ) {
			var when = compose.querySelector( '[name=schedule]' );
			when.value = params.get( 'date' ) + when.value.slice( 10 );
		}
		var chosen = function () {
			return Array.prototype.map.call( compose.querySelectorAll( '[name="accounts[]"]:checked' ), function ( box ) {
				return { id: parseInt( box.value, 10 ), network: box.getAttribute( 'data-network' ) };
			} );
		};
		var networks = function () {
			var seen = [];
			chosen().forEach( function ( a ) {
				if ( seen.indexOf( a.network ) < 0 ) {
					seen.push( a.network );
				}
			} );
			return seen;
		};
		var drawTabs = function () {
			tabs.querySelectorAll( '[data-tab]:not([data-tab=""])' ).forEach( function ( node ) {
				node.remove();
			} );
			networks().forEach( function ( net ) {
				var link = el( 'a', { href: '#', 'data-tab': net }, labels[ net ] + ( state.overrides[ net ] ? ' ✎' : '' ) );
				tabs.appendChild( link );
			} );
			tabs.querySelectorAll( '[data-tab]' ).forEach( function ( node ) {
				node.classList.toggle( 'is-active', node.getAttribute( 'data-tab' ) === state.tab );
			} );
			if ( state.tab && networks().indexOf( state.tab ) < 0 ) {
				switchTab( '' );
			}
			compose.querySelector( '[data-qcsoc-gbp]' ).hidden = networks().indexOf( 'google_business' ) < 0;
			drawFormats();
		};
		// Formats per network tab (Master): thread follow-ups and a first comment.
		var formatsOf = JSON.parse( compose.getAttribute( 'data-formats' ) || '{}' );
		state.formats = state.formats || {};
		var formatBox = compose.querySelector( '[data-qcsoc-formats]' );
		var keepFormats = function ( net ) {
			if ( ! net || ! formatsOf[ net ] ) {
				return;
			}
			state.formats[ net ] = {
				posts: formatBox.querySelector( '[name=thread_posts]' ).value,
				first_comment: formatBox.querySelector( '[name=first_comment]' ).value,
				ig_format: formatBox.querySelector( '[name=ig_format]' ).value,
				yt_privacy: formatBox.querySelector( '[name=yt_privacy]' ).value,
			};
		};
		var drawFormats = function () {
			var allowed = state.tab ? ( formatsOf[ state.tab ] || [] ) : [];
			formatBox.hidden = ! allowed.length;
			formatBox.querySelectorAll( '[data-qcsoc-format]' ).forEach( function ( node ) {
				node.hidden = allowed.indexOf( node.getAttribute( 'data-qcsoc-format' ) ) < 0;
			} );
			var saved = state.formats[ state.tab ] || {};
			formatBox.querySelector( '[name=thread_posts]' ).value = saved.posts || '';
			formatBox.querySelector( '[name=first_comment]' ).value = saved.first_comment || '';
			formatBox.querySelector( '[name=ig_format]' ).value = saved.ig_format || 'feed';
			formatBox.querySelector( '[name=yt_privacy]' ).value = saved.yt_privacy || 'private';
		};
		var switchTab = function ( tab ) {
			keepFormats( state.tab );
			if ( state.tab ) {
				if ( textarea.value.trim() && textarea.value !== state.text ) {
					state.overrides[ state.tab ] = textarea.value;
				} else {
					delete state.overrides[ state.tab ];
				}
			} else {
				state.text = textarea.value;
			}
			state.tab = tab;
			textarea.value = tab ? ( state.overrides[ tab ] || state.text ) : state.text;
			compose.querySelector( '[data-qcsoc-tabhint]' ).textContent = tab ? labels[ tab ] : '';
			drawTabs();
			queue();
		};
		tabs.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-tab]' );
			if ( link ) {
				event.preventDefault();
				switchTab( link.getAttribute( 'data-tab' ) );
			}
		} );
		var payload = function ( status ) {
			if ( state.tab ) {
				if ( textarea.value !== state.text ) {
					state.overrides[ state.tab ] = textarea.value;
				}
			} else {
				state.text = textarea.value;
			}
			var overrides = {};
			Object.keys( state.overrides ).forEach( function ( net ) {
				overrides[ net ] = { text: state.overrides[ net ] };
			} );
			keepFormats( state.tab );
			var extras = {};
			Object.keys( state.formats ).forEach( function ( net ) {
				extras[ net ] = state.formats[ net ];
			} );
			var gbp = {};
			[ 'gbp_type', 'gbp_title', 'gbp_start', 'gbp_end', 'gbp_coupon', 'gbp_terms' ].forEach( function ( name ) {
				gbp[ name ] = compose.querySelector( '[name=' + name + ']' ).value;
			} );
			var when = compose.querySelector( '[name=when]:checked' ).value;
			var now = 'now' === when;
			var options = {};
			compose.querySelectorAll( '[data-qcsoc-option]' ).forEach( function ( input ) {
				options[ input.name ] = 'checkbox' === input.type ? input.checked : input.value;
			} );
			return {
				title: compose.querySelector( '[name=title]' ).value,
				text: state.text,
				link: compose.querySelector( '[name=link]' ).value,
				accounts: chosen().map( function ( a ) {
					return a.id;
				} ),
				images: state.images.map( function ( i ) {
					return i.id;
				} ),
				video: state.video ? state.video.id : 0,
				overrides: overrides,
				extras: Object.assign( extras, { google_business: gbp } ),
				schedule: now ? '' : ( 'queue' === when ? 'queue' : compose.querySelector( '[name=schedule]' ).value ),
				options: options,
				status: status || 'scheduled',
				draft_id: state.draftId || 0,
				flags: state.flags || {},
				source_type: ( state.source || {} ).type || '',
				source_id: ( state.source || {} ).id || 0,
			};
		};
		var drawChecks = function ( data ) {
			var list = compose.querySelector( '[data-qcsoc-checks]' );
			list.textContent = '';
			counts = {};
			data.checks.forEach( function ( check ) {
				counts[ check.account_id ] = check;
				var ok = ! check.problems.length;
				var item = el( 'li', { className: ok ? 'is-ok' : 'is-bad' }, ( ok ? '✓ ' : '✗ ' ) + check.label + ( check.max ? ' · ' + check.length + ' / ' + check.max : '' ) );
				check.problems.forEach( function ( problem ) {
					item.appendChild( el( 'div', { className: 'qcsoc-muted' }, problem ) );
				} );
				( check.notes || [] ).forEach( function ( note ) {
					item.appendChild( el( 'div', { className: 'qcsoc-note' }, 'ℹ ' + note ) );
				} );
				list.appendChild( item );
			} );
			if ( ! data.checks.length ) {
				list.appendChild( el( 'li', { className: 'qcsoc-muted' }, data.problems.join( ' ' ) ) );
			}
			var go = compose.querySelector( '[data-qcsoc-save="scheduled"]' );
			if ( go ) {
				var n = data.checks.length;
				var now = compose.querySelector( '[name=when]:checked' ).value === 'now';
				go.disabled = ! n || data.problems.length > 0;
				go.textContent = 1 === n ? ( now ? t.postNowOne : t.scheduleOne ) : ( now ? t.postNow : t.schedule ).replace( '%d', n );
			}
			drawPreview( data.drafts || {} );
		};
		var drawPreview = function ( drafts ) {
			var box = compose.querySelector( '[data-qcsoc-preview]' );
			box.textContent = '';
			chosen().forEach( function ( a ) {
				var d = drafts[ a.id ];
				if ( ! d ) {
					return;
				}
				var card = el( 'div', { className: 'qcsoc-preview__card' } );
				card.appendChild( el( 'strong', {}, labels[ a.network ] + ' · ' + accounts.filter( function ( x ) {
					return x.id === a.id;
				} )[ 0 ].name ) );
				card.appendChild( el( 'p', { className: 'qcsoc-preview__text' }, d.text ) );
				if ( d.link ) {
					card.appendChild( el( 'p', { className: 'qcsoc-preview__link' }, d.link ) );
				}
				if ( d.images && d.images.length ) {
					card.appendChild( el( 'img', { src: d.images[ 0 ].url, alt: '' } ) );
				}
				box.appendChild( card );
			} );
		};
		var check = function () {
			var body = payload();
			compose.querySelector( '[data-qcsoc-count]' ).textContent = String( textarea.value.length );
			if ( ! body.accounts.length ) {
				drawChecks( { checks: [], problems: [], drafts: {} } );
				return;
			}
			api( 'POST', 'posts/check', body ).then( drawChecks ).catch( function ( error ) {
				status( compose, error.message, true );
			} );
		};
		var queue = function () {
			clearTimeout( timer );
			timer = setTimeout( check, 500 );
		};
		compose.addEventListener( 'input', queue );
		compose.addEventListener( 'change', function ( event ) {
			if ( event.target.name === 'accounts[]' ) {
				drawTabs();
			}
			if ( event.target.name === 'gbp_type' ) {
				var type = event.target.value;
				compose.querySelectorAll( '[data-gbp-extra]' ).forEach( function ( n ) {
					n.hidden = type === 'update';
				} );
				compose.querySelectorAll( '[data-gbp-offer]' ).forEach( function ( n ) {
					n.hidden = type !== 'offer';
				} );
			}
			queue();
		} );
		compose.querySelector( '[name=gbp_type]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
		var media = compose.querySelector( '[data-qcsoc-media]' );
		var drawMedia = function () {
			media.querySelectorAll( '.qcsoc-media__item' ).forEach( function ( n ) {
				n.remove();
			} );
			state.images.forEach( function ( image, index ) {
				var item = el( 'div', { className: 'qcsoc-media__item' } );
				item.appendChild( el( 'img', { src: image.thumb, alt: image.alt || '' } ) );
				var remove = el( 'button', { type: 'button', 'aria-label': 'Remove' }, '×' );
				remove.addEventListener( 'click', function () {
					state.images.splice( index, 1 );
					drawMedia();
					queue();
				} );
				item.appendChild( remove );
				media.insertBefore( item, media.firstChild );
			} );
		};
		if ( prefill ) {
			drawMedia();
		}
		var addPick = function ( image ) {
			if ( ! state.images.some( function ( i ) {
				return i.id === image.id;
			} ) ) {
				state.images.push( image );
				drawMedia();
				queue();
			}
		};
		var drawPicks = function ( box, images ) {
			box.textContent = '';
			images.forEach( function ( image ) {
				var pick = el( 'button', { type: 'button', className: 'qcsoc-media__pick', 'aria-label': image.alt || t.addImage } );
				pick.appendChild( el( 'img', { src: image.thumb, alt: '' } ) );
				pick.addEventListener( 'click', function () {
					addPick( image );
				} );
				box.appendChild( pick );
			} );
			if ( ! images.length ) {
				box.appendChild( el( 'p', { className: 'qcsoc-muted' }, t.noImages ) );
			}
		};
		var fromPost = compose.querySelector( '#qcsoc-from-post' );
		if ( fromPost ) {
			fromPost.addEventListener( 'change', function () {
				if ( fromPost.value ) {
					api( 'GET', 'images/source?post_id=' + fromPost.value ).then( function ( images ) {
						drawPicks( compose.querySelector( '[data-qcsoc-from-post-images]' ), images );
					} );
				}
			} );
		}
		var aiCreate = compose.querySelector( '[data-qcsoc-ai-create]' );
		if ( aiCreate ) {
			aiCreate.addEventListener( 'click', function () {
				aiCreate.disabled = true;
				var style = compose.querySelector( '[data-qcsoc-ai-style]' );
				api( 'POST', 'images/ai', { prompt: compose.querySelector( '#qcsoc-ai-prompt' ).value, style: style ? style.value : '' } ).then( function ( images ) {
					drawPicks( compose.querySelector( '[data-qcsoc-ai-images]' ), images );
				} ).catch( function ( error ) {
					window.alert( error.message ); // eslint-disable-line no-alert
				} ).finally( function () {
					aiCreate.disabled = false;
				} );
			} );
		}
		var frame = null;
		compose.querySelector( '[data-qcsoc-media-add]' ).addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( { title: t.pickImage, button: { text: t.useImage }, library: { type: 'image' }, multiple: true } );
				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( item ) {
						var a = item.toJSON();
						state.images.push( { id: a.id, thumb: ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url ), alt: a.alt } );
					} );
					drawMedia();
					queue();
				} );
			}
			frame.open();
		} );
		var videoBox = compose.querySelector( '[data-qcsoc-video]' );
		var videoFrame = null;
		var drawVideo = function () {
			if ( ! videoBox ) {
				return;
			}
			videoBox.querySelector( '[data-qcsoc-video-name]' ).textContent = state.video ? state.video.name : '';
			videoBox.querySelector( '[data-qcsoc-video-remove]' ).hidden = ! state.video;
		};
		if ( videoBox ) {
			videoBox.querySelector( '[data-qcsoc-video-add]' ).addEventListener( 'click', function () {
				if ( ! window.wp || ! window.wp.media ) {
					return;
				}
				if ( ! videoFrame ) {
					videoFrame = window.wp.media( { title: t.pickVideo, button: { text: t.useVideo }, library: { type: 'video' }, multiple: false } );
					videoFrame.on( 'select', function () {
						var a = videoFrame.state().get( 'selection' ).first().toJSON();
						state.video = { id: a.id, name: a.filename || a.title };
						drawVideo();
						queue();
					} );
				}
				videoFrame.open();
			} );
			videoBox.querySelector( '[data-qcsoc-video-remove]' ).addEventListener( 'click', function () {
				state.video = null;
				drawVideo();
				queue();
			} );
			drawVideo();
		}
		compose.querySelectorAll( '[data-qcsoc-save]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				button.disabled = true;
				api( 'POST', 'posts', payload( button.getAttribute( 'data-qcsoc-save' ) ) ).then( function ( data ) {
					// A Master add-on can take the save over (approval) and name the outcome.
					var outcome = data && data.status ? '&saved=' + encodeURIComponent( data.status ) : '';
					window.location.assign( cfg.admin + '&view=posts' + outcome );
				} ).catch( function ( error ) {
					button.disabled = false;
					status( compose, error.message, true );
				} );
			} );
		} );
	}

	/* Posts (screen 12) */
	var posts = document.querySelector( '[data-qcsoc="posts"]' );
	if ( posts ) {
		posts.addEventListener( 'click', function ( event ) {
			var action = event.target.closest( '[data-qcsoc-target-action]' );
			if ( action ) {
				var target = action.closest( '[data-target]' ).getAttribute( 'data-target' );
				var body = { action: action.getAttribute( 'data-qcsoc-target-action' ) };
				if ( 'reschedule' === body.action ) {
					body.when = window.prompt( t.rescheduleTo, new Date( Date.now() + 86400000 ).toISOString().slice( 0, 16 ).replace( 'T', ' ' ) ); // eslint-disable-line no-alert
					if ( ! body.when ) {
						return;
					}
				}
				api( 'POST', 'targets/' + target + '/action', body ).then( function () {
					window.location.reload();
				} ).catch( function ( error ) {
					status( posts, error.message, true );
				} );
			}
			var del = event.target.closest( '[data-qcsoc-post-delete]' );
			if ( del && window.confirm( t.confirmDel ) ) { // eslint-disable-line no-alert
				var row = del.closest( '[data-post]' );
				api( 'DELETE', 'posts/' + row.getAttribute( 'data-post' ) ).then( function () {
					row.remove();
				} ).catch( function ( error ) {
					status( posts, error.message, true );
				} );
			}
		} );
	}
	/* Create post › Write with AI (screen 05) */
	var write = document.querySelector( '[data-qcsoc="write"]' );
	if ( write ) {
		var multi = '1' === write.getAttribute( 'data-multi' );
		var showCost = '1' === write.getAttribute( 'data-show-cost' );
		var connected = JSON.parse( write.getAttribute( 'data-connected' ) || '{}' );
		var result = null;
		var estTimer = null;
		var field = function ( name ) {
			var node = write.querySelector( '[name=' + name + ']' );
			return node ? node.value : '';
		};
		var kind = function () {
			return write.querySelector( '[name=kind]:checked' ).value;
		};
		var picked = function () {
			return Array.prototype.map.call( write.querySelectorAll( '[name=networks]:checked' ), function ( box ) {
				return box.value;
			} );
		};
		var input = function () {
			return {
				kind: kind(),
				about: field( 'about' ),
				post_id: field( 'post_id' ),
				product_id: field( 'product_id' ),
				url: field( 'url' ),
				link: field( 'link' ),
				goal: field( 'goal' ),
				voice: field( 'voice' ),
				brand_id: field( 'brand_id' ),
				event: {
					title: field( 'event_title' ),
					date: field( 'event_date' ),
					place: field( 'event_place' ),
					offer: field( 'event_offer' ),
					code: field( 'event_code' ),
					ends: field( 'event_ends' ),
				},
				networks: picked(),
			};
		};
		var showKind = function () {
			var k = kind();
			write.querySelectorAll( '[data-kind-show]' ).forEach( function ( node ) {
				node.hidden = node.getAttribute( 'data-kind-show' ).split( ' ' ).indexOf( k ) < 0;
			} );
			write.querySelectorAll( '[data-kind-hide]' ).forEach( function ( node ) {
				node.hidden = node.getAttribute( 'data-kind-hide' ) === k;
			} );
		};
		var go = write.querySelector( '[data-qcsoc-write]' );
		var est = write.querySelector( '[data-qcsoc-estimate]' );
		var estimate = function () {
			var n = picked().length;
			go.disabled = ! n;
			go.textContent = n > 1 ? t.writeMany.replace( '%d', n ) : t.writeOne;
			if ( ! n ) {
				est.textContent = '';
				return;
			}
			if ( 'blank' === kind() ) {
				est.textContent = t.noAi;
				return;
			}
			if ( ! showCost ) {
				est.textContent = '';
				return;
			}
			clearTimeout( estTimer );
			estTimer = setTimeout( function () {
				api( 'POST', 'write/estimate', input() ).then( function ( data ) {
					if ( ! data.ready ) {
						est.textContent = t.noProvider;
						return;
					}
					var text = t.estimate.replace( '%1$d', data.calls ).replace( '%2$s', '$' + Number( data.cost ).toFixed( 3 ) );
					if ( data.budget > 0 ) {
						text += ' · ' + t.budgetLeft.replace( '%s', '$' + Math.max( 0, data.budget - data.spent ).toFixed( 2 ) );
						if ( data.spent + data.cost > data.budget ) {
							text = t.overBudget;
							go.disabled = true;
						}
					}
					est.textContent = text;
				} ).catch( function ( error ) {
					est.textContent = error.message;
				} );
			}, 400 );
		};
		write.addEventListener( 'change', function ( event ) {
			if ( 'kind' === event.target.name ) {
				showKind();
			}
			estimate();
		} );
		write.addEventListener( 'input', function ( event ) {
			if ( 'about' === event.target.name ) {
				estimate();
			}
		} );
		showKind();
		estimate();

		var list = write.querySelector( '[data-qcsoc-versions]' );
		var card = function ( v ) {
			var node = el( 'article', { className: 'qcsoc-card qcsoc-version' } );
			node.setAttribute( 'data-network', v.network );
			var head = el( 'header', { className: 'qcsoc-version__head' } );
			head.appendChild( el( 'strong', {}, v.label ) );
			if ( v.manual ) {
				head.appendChild( el( 'span', { className: 'qcsoc-pill' }, t.postYourself ) );
			}
			var count = el( 'span', { className: 'qcsoc-muted qcsoc-version__count' }, v.length + ( v.max ? ' / ' + v.max : '' ) );
			head.appendChild( count );
			node.appendChild( head );
			if ( v.error ) {
				node.appendChild( el( 'p', { className: 'qcsoc-bad' }, v.error ) );
				return node;
			}
			if ( v.title ) {
				node.appendChild( el( 'p', {}, el( 'strong', {}, v.title ) ) );
			}
			var area = el( 'textarea', { rows: 6, 'aria-label': v.label } );
			area.value = v.text;
			area.addEventListener( 'input', function () {
				count.textContent = area.value.length + ( v.max ? ' / ' + v.max : '' );
				count.classList.toggle( 'qcsoc-bad', v.max > 0 && area.value.length > v.max );
			} );
			count.classList.toggle( 'qcsoc-bad', !! v.too_long );
			node.appendChild( area );
			[ 'slides', 'posts' ].forEach( function ( key ) {
				if ( v[ key ] && v[ key ].length ) {
					var ol = el( 'ol', { className: 'qcsoc-version__parts' } );
					v[ key ].forEach( function ( part ) {
						ol.appendChild( el( 'li', {}, part ) );
					} );
					node.appendChild( ol );
				}
			} );
			if ( v.variants && v.variants.length ) {
				var alts = el( 'details', { className: 'qcsoc-version__variants' } );
				alts.appendChild( el( 'summary', {}, t.variants.replace( '%d', v.variants.length ) ) );
				v.variants.forEach( function ( alt ) {
					var row = el( 'div', { className: 'qcsoc-version__variant' } );
					row.appendChild( el( 'p', {}, alt ) );
					var use = el( 'button', { type: 'button', className: 'button-link' }, t.useThis );
					use.addEventListener( 'click', function () {
						area.value = alt;
						area.dispatchEvent( new Event( 'input' ) );
					} );
					row.appendChild( use );
					alts.appendChild( row );
				} );
				node.appendChild( alts );
			}
			if ( v.flags && v.flags.length ) {
				var flags = el( 'ul', { className: 'qcsoc-version__flags' } );
				v.flags.forEach( function ( flag ) {
					flags.appendChild( el( 'li', {}, '⚠ ' + flag ) );
				} );
				node.appendChild( flags );
			}
			var foot = el( 'footer', { className: 'qcsoc-version__foot' } );
			foot.appendChild( el( 'span', { className: 'qcsoc-muted' }, v.technique ? t.technique + ' ' + v.technique : '' ) );
			var again = el( 'button', { type: 'button', className: 'button-link' }, t.rewrite );
			again.addEventListener( 'click', function () {
				again.disabled = true;
				var body = input();
				body.network = v.network;
				api( 'POST', 'write/rewrite', body ).then( function ( fresh ) {
					result.versions[ v.network ] = fresh;
					node.replaceWith( card( fresh ) );
				} ).catch( function ( error ) {
					again.disabled = false;
					status( write, error.message, true );
				} );
			} );
			foot.appendChild( again );
			var pick = el( 'label', { className: 'qcsoc-version__pick' } );
			var box = el( 'input', { type: 'checkbox' } );
			box.checked = true;
			box.setAttribute( 'data-qcsoc-pick', v.network );
			pick.appendChild( box );
			pick.appendChild( document.createTextNode( ' ' + t.use ) );
			foot.appendChild( pick );
			node.appendChild( foot );
			return node;
		};
		go.addEventListener( 'click', function () {
			go.disabled = true;
			var label = go.textContent;
			var nets = picked();
			var body = input();
			var total = 0;
			result = null;
			list.textContent = '';
			write.querySelector( '[data-qcsoc-results-head]' ).hidden = true;
			// One request per network: each stays well under hosting time limits
			// (AI calls can take 20 s or more each).
			var step = function ( index ) {
				if ( index >= nets.length ) {
					return Promise.resolve();
				}
				go.textContent = t.writingOf.replace( '%1$d', index + 1 ).replace( '%2$d', nets.length );
				var one = Object.assign( {}, body, { networks: [ nets[ index ] ] } );
				return api( 'POST', 'write', one ).then( function ( data ) {
					if ( ! result ) {
						result = { source: data.source, versions: {}, cost: 0 };
						write.querySelector( '[data-qcsoc-results-head]' ).hidden = false;
					}
					Object.keys( data.versions ).forEach( function ( net ) {
						result.versions[ net ] = data.versions[ net ];
						list.appendChild( card( data.versions[ net ] ) );
					} );
					total += Number( data.cost || 0 );
					var n = Object.keys( result.versions ).length;
					write.querySelector( '[data-qcsoc-results-title]' ).textContent = 1 === n ? t.oneVersion : t.versions.replace( '%d', n );
					return step( index + 1 );
				} );
			};
			step( 0 ).then( function () {
				if ( total ) {
					status( write, t.cost.replace( '%s', '$' + total.toFixed( 4 ) ) );
				}
			} ).catch( function ( error ) {
				status( write, error.message, true );
			} ).finally( function () {
				go.disabled = false;
				go.textContent = label;
			} );
		} );
		var netLabel = function ( net ) {
			var chip = write.querySelector( '[name=networks][value="' + net + '"]' );
			var label = chip ? chip.parentNode.querySelector( '.qcsoc-chip__net' ) : null;
			return label ? label.textContent : net;
		};
		write.querySelector( '[data-qcsoc-continue]' ).addEventListener( 'click', function () {
			if ( ! result ) {
				status( write, t.writeFirst, true );
				return;
			}
			var missing = [];
			var overrides = {};
			var flags = {};
			var extras = {};
			var accountIds = [];
			var first = '';
			list.querySelectorAll( '[data-qcsoc-pick]:checked' ).forEach( function ( box ) {
				var net = box.getAttribute( 'data-qcsoc-pick' );
				var text = box.closest( '.qcsoc-version' ).querySelector( 'textarea' ).value;
				first = first || text;
				overrides[ net ] = { text: text };
				// Thread parts and titles the writer made go into Create post's per-network fields.
				var version = result.versions[ net ];
				if ( ( version.posts && version.posts.length ) || version.title ) {
					extras[ net ] = {};
					if ( version.posts && version.posts.length ) {
						extras[ net ].posts = version.posts;
					}
					if ( version.title ) {
						extras[ net ].title = version.title;
					}
				}
				if ( result.versions[ net ].flags && result.versions[ net ].flags.length ) {
					flags[ net ] = result.versions[ net ].flags;
				}
				if ( result.versions[ net ].manual ) {
					accountIds.push( 'manual:' + net );
				}
				( connected[ net ] || [] ).forEach( function ( a ) {
					accountIds.push( a.id );
				} );
				if ( ! result.versions[ net ].manual && ! ( connected[ net ] || [] ).length ) {
					missing.push( netLabel( net ) );
				}
			} );
			if ( ! list.querySelector( '[data-qcsoc-pick]:checked' ) ) {
				status( write, t.pickOne, true );
				return;
			}
			if ( ! first.trim() ) {
				status( write, t.emptyText, true );
				return;
			}
			// Create post needs at least one account: say which networks have none
			// instead of the server's generic refusal.
			if ( ! accountIds.length ) {
				status( write, t.noAccount.replace( '%s', missing.join( ', ' ) ), true );
				var statusNode = write.querySelector( '[data-qcsoc-status]' );
				statusNode.appendChild( document.createTextNode( ' ' ) );
				statusNode.appendChild( el( 'a', { href: cfg.admin + '&view=accounts', target: '_blank', rel: 'noopener' }, t.goAccounts ) );
				return;
			}
			api( 'POST', 'posts', {
				title: result.source.title || field( 'event_title' ) || '',
				brand_id: field( 'brand_id' ) || 0,
				text: first,
				link: result.source.link || field( 'link' ),
				images: result.source.image_id ? [ result.source.image_id ] : [],
				accounts: accountIds,
				overrides: overrides,
				flags: flags,
				extras: extras,
				status: 'draft',
				source_type: 'ai',
			} ).then( function ( data ) {
				window.location.assign( cfg.admin + '&view=compose&post=' + data.id );
			} ).catch( function ( error ) {
				status( write, error.message, true );
			} );
		} );
	}
	/* Post yourself (screen 08) */
	var manual = document.querySelector( '[data-qcsoc="manual"]' );
	if ( manual ) {
		manual.querySelectorAll( '[data-qcsoc-qr]' ).forEach( function ( box ) {
			if ( ! window.qrcode ) {
				return;
			}
			window.qrcode.stringToBytes = window.qrcode.stringToBytesFuncs[ 'UTF-8' ];
			var qr = window.qrcode( 0, 'L' );
			qr.addData( box.getAttribute( 'data-qcsoc-qr' ) );
			qr.make();
			box.innerHTML = qr.createSvgTag( { cellSize: 3, margin: 2, scalable: true } );
		} );
		manual.addEventListener( 'click', function ( event ) {
			var cardNode = event.target.closest( '[data-target]' );
			if ( ! cardNode ) {
				return;
			}
			var id = cardNode.getAttribute( 'data-target' );
			if ( event.target.closest( '[data-qcsoc-copy-text]' ) ) {
				navigator.clipboard.writeText( cardNode.getAttribute( 'data-copy' ) ).then( function () {
					status( manual, t.copied );
				} );
				return;
			}
			var action = event.target.closest( '[data-qcsoc-posted]' ) ? 'posted' : ( event.target.closest( '[data-qcsoc-skip]' ) ? 'skip' : '' );
			if ( ! action ) {
				return;
			}
			var urlInput = cardNode.querySelector( '[data-qcsoc-posted-url]' );
			api( 'POST', 'targets/' + id + '/action', { action: action, url: urlInput ? urlInput.value : '' } ).then( function ( data ) {
				if ( data.ok ) {
					window.location.reload();
				} else {
					status( manual, t.error, true );
				}
			} ).catch( function ( error ) {
				status( manual, error.message, true );
			} );
		} );
	}
	/* QA #58: dismiss the missed-posts notice */
	var missedDismiss = document.querySelector( '[data-qcsoc-missed-dismiss]' );
	if ( missedDismiss ) {
		missedDismiss.addEventListener( 'click', function () {
			api( 'POST', 'missed/dismiss' ).then( function () {
				document.querySelector( '[data-qcsoc-missed-notice]' ).remove();
			} );
		} );
	}
}() );
