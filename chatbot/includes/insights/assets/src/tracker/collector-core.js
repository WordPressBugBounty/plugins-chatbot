/**
 * Clicks, scroll depth and frustration signals, shared by the light
 * collector (visits that are not recorded) and the recorder.
 *
 * Clicks: CSS selector of the clicked element, position inside it
 * (0-9999 on each axis), page position and page size.
 * Signals detected here: rage clicks, dead clicks, JavaScript errors.
 * Quick backs are detected on the server from the order of pages.
 */

const FIELDS = 'input,select,textarea,[contenteditable],[contenteditable="true"]';
const TARGETS = 'a,button,input,select,textarea,label,summary,[role="button"],[role="link"],[role="tab"],[role="menuitem"],[role="checkbox"],[onclick],svg';
const SAFE_ID = /^[A-Za-z][\w-]*$/;

function clamp( v, min, max ) {
	return Math.max( min, Math.min( max, Math.round( v ) || 0 ) );
}

function docSize() {
	const de = document.documentElement;
	const b = document.body || de;
	return {
		w: Math.max( de.scrollWidth, b.scrollWidth ),
		h: Math.max( de.scrollHeight, b.scrollHeight ),
	};
}

/**
 * Stable CSS selector: tag names with :nth-of-type, anchored at the nearest
 * ancestor with a usable id. The same selector finds the element again in the
 * live page or in a recorded snapshot when the heatmap is drawn.
 *
 * @param {Element} el Element.
 * @return {string} Selector.
 */
export function selectorFor( el ) {
	const parts = [];
	while ( el && el.nodeType === 1 && parts.length < 14 ) {
		const tag = el.tagName.toLowerCase();
		if ( tag === 'html' ) {
			break;
		}
		if ( el.id && SAFE_ID.test( el.id ) && ! /\d{4,}/.test( el.id ) ) {
			parts.unshift( '#' + el.id );
			return parts.join( '>' ).slice( 0, 255 );
		}
		let part = tag;
		const parent = el.parentElement;
		if ( parent && tag !== 'body' ) {
			let index = 0;
			let count = 0;
			for ( let i = 0; i < parent.children.length; i++ ) {
				if ( parent.children[ i ].tagName === el.tagName ) {
					count++;
					if ( parent.children[ i ] === el ) {
						index = count;
					}
				}
			}
			if ( count > 1 ) {
				part += ':nth-of-type(' + index + ')';
			}
		}
		parts.unshift( part );
		if ( tag === 'body' ) {
			break;
		}
		el = parent;
	}
	return parts.join( '>' ).slice( 0, 255 );
}

export function endpointUrl( c ) {
	const sep = c.endpoint.indexOf( '?' ) === -1 ? '?' : '&';
	return c.endpoint + sep + 'token=' + encodeURIComponent( c.token );
}

export function meta() {
	const utm = {};
	try {
		const q = new URLSearchParams( window.location.search );
		[ 'source', 'medium', 'campaign' ].forEach( ( k ) => {
			const v = q.get( 'utm_' + k );
			if ( v ) {
				utm[ k ] = v.slice( 0, 100 );
			}
		} );
	} catch ( e ) {}
	return {
		url: window.location.href,
		title: document.title,
		ref: document.referrer,
		vw: window.innerWidth,
		vh: window.innerHeight,
		utm,
	};
}

/**
 * Start collecting.
 *
 * @param {Object}   opts          Options.
 * @param {Object}   opts.rules    Frustration thresholds.
 * @param {Function} opts.onSignal Called with each signal as it happens.
 * @return {Object} Collector.
 */
export function createCollector( opts ) {
	const R = Object.assign( { rageClicks: 3, rageMs: 1000, rageRadius: 30, deadMs: 500, maxErrors: 20 }, opts.rules || {} );
	const listeners = {};
	let clicks = [];
	let signals = [];
	// Feature data (window.qcInsights.push), sent with the next batch.
	let ext = {};
	let extCount = 0;
	let waiting = [];
	let recent = [];
	let errors = {};
	let errorCount = 0;
	let lastRage = 0;
	let lastDead = {};
	let maxScroll = 0;
	let docH = 0;
	let pvStart = Date.now();
	let dirty = true;
	let stopped = false;

	function emit( name, data ) {
		( listeners[ name ] || [] ).forEach( ( fn ) => {
			try {
				fn( data );
			} catch ( e ) {}
		} );
	}

	function signal( k, s, d ) {
		if ( stopped ) {
			return;
		}
		const sig = { k, t: Date.now() - pvStart, s: s || '', d: d || {} };
		signals.push( sig );
		dirty = true;
		if ( opts.onSignal ) {
			opts.onSignal( sig );
		}
		emit( 'signal', sig );
		if ( k !== 'custom' ) {
			emit( 'frustration', sig );
		}
	}

	function settle( item, dead ) {
		const i = waiting.indexOf( item );
		if ( i === -1 ) {
			return;
		}
		waiting.splice( i, 1 );
		if ( dead ) {
			item.d = 1;
			const now = Date.now();
			// One dead-click signal per element every 3 seconds.
			if ( ! lastDead[ item.s ] || now - lastDead[ item.s ] > 3000 ) {
				lastDead[ item.s ] = now;
				signal( 'dead', item.s );
			}
		}
		clicks.push( item );
		dirty = true;
	}

	function watchDead( item, el ) {
		if ( el === document.body || el === document.documentElement || ( el.closest && el.closest( FIELDS ) ) ) {
			settle( item, false );
			return;
		}
		let changed = false;
		let observer = null;
		try {
			// Scripts, styles and <head> changes are not visible: a loader adding a
			// script right after a click must not turn a dead click into a live one.
			const invisible = ( n ) => n && n.nodeType === 1 && /^(SCRIPT|STYLE|LINK|META|NOSCRIPT|TEMPLATE)$/.test( n.nodeName );
			observer = new MutationObserver( ( records ) => {
				for ( let i = 0; i < records.length; i++ ) {
					const r = records[ i ];
					if ( document.head && ( r.target === document.head || document.head.contains( r.target ) ) ) {
						continue;
					}
					if ( r.type === 'childList' ) {
						const nodes = Array.prototype.slice.call( r.addedNodes ).concat( Array.prototype.slice.call( r.removedNodes ) );
						if ( nodes.length && nodes.every( invisible ) ) {
							continue;
						}
					}
					changed = true;
					return;
				}
			} );
			observer.observe( document.documentElement, { subtree: true, childList: true, attributes: true, characterData: true } );
		} catch ( e ) {}
		const x = window.scrollX;
		const y = window.scrollY;
		const href = window.location.href;
		setTimeout( () => {
			if ( observer ) {
				observer.disconnect();
			}
			let selected = '';
			try {
				selected = String( window.getSelection ? window.getSelection() : '' );
			} catch ( e ) {}
			const dead = ! changed && window.scrollX === x && window.scrollY === y && window.location.href === href && ! selected && ! document.hidden;
			settle( item, dead );
		}, R.deadMs );
	}

	function onClick( e ) {
		if ( stopped || ! e.isTrusted ) {
			return;
		}
		let el = e.target;
		if ( el && el.nodeType !== 1 ) {
			el = el.parentElement;
		}
		if ( ! el || ! el.getBoundingClientRect ) {
			return;
		}
		el = ( el.closest && el.closest( TARGETS ) ) || el;
		const s = selectorFor( el );
		if ( ! s ) {
			return;
		}
		const r = el.getBoundingClientRect();
		const size = docSize();
		const item = {
			s,
			x: r.width ? clamp( ( ( e.clientX - r.left ) / r.width ) * 10000, 0, 9999 ) : 0,
			y: r.height ? clamp( ( ( e.clientY - r.top ) / r.height ) * 10000, 0, 9999 ) : 0,
			px: clamp( e.clientX + window.scrollX, 0, 1e6 ),
			py: clamp( e.clientY + window.scrollY, 0, 1e6 ),
			dw: size.w,
			dh: size.h,
			t: Date.now() - pvStart,
			r: 0,
			d: 0,
		};

		// Rage click: several clicks close together in a short time.
		const now = Date.now();
		recent = recent.filter( ( c ) => now - c.t <= R.rageMs );
		recent.push( { t: now, x: e.clientX, y: e.clientY } );
		const near = recent.filter( ( c ) => Math.abs( c.x - e.clientX ) <= R.rageRadius && Math.abs( c.y - e.clientY ) <= R.rageRadius );
		if ( near.length >= R.rageClicks ) {
			item.r = 1;
			if ( now - lastRage > R.rageMs ) {
				signal( 'rage', s, { n: near.length } );
			}
			lastRage = now;
		}

		waiting.push( item );
		watchDead( item, el );
	}

	function onError( e ) {
		if ( e.target && e.target !== window ) {
			return; // A failed image or script load, not a JavaScript error.
		}
		addError( e.message, e.filename, e.lineno, e.colno );
	}

	function onRejection( e ) {
		const r = e.reason;
		addError( 'Unhandled promise rejection: ' + ( ( r && r.message ) || String( r ) ), '', 0, 0 );
	}

	function addError( message, file, line, col ) {
		if ( errorCount >= R.maxErrors ) {
			return;
		}
		const m = String( message || 'Error' ).slice( 0, 300 );
		const f = String( file || '' ).split( /[?#]/ )[ 0 ].slice( 0, 255 );
		const key = m + '|' + f;
		if ( errors[ key ] ) {
			return;
		}
		errors[ key ] = 1;
		errorCount++;
		signal( 'error', '', { m, f, l: line || 0, c: col || 0 } );
	}

	let ticking = false;
	function onScroll() {
		if ( ticking ) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame( () => {
			ticking = false;
			measure();
		} );
	}

	function measure() {
		const h = docSize().h;
		if ( h > docH ) {
			docH = h;
			dirty = true;
		}
		if ( h > 0 ) {
			const pct = Math.min( 100, ( ( window.scrollY + window.innerHeight ) / h ) * 100 );
			if ( pct > maxScroll + 0.5 ) {
				maxScroll = pct;
				dirty = true;
			}
		}
	}

	window.addEventListener( 'click', onClick, true );
	window.addEventListener( 'error', onError, true );
	window.addEventListener( 'unhandledrejection', onRejection );
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	measure();

	return {
		on( name, fn ) {
			( listeners[ name ] = listeners[ name ] || [] ).push( fn );
		},
		emit,
		signal,
		/** Custom event from the public API. */
		custom( name ) {
			signal( 'custom', '', { n: String( name ).slice( 0, 64 ) } );
		},
		/** Treat clicks still being watched as not dead (page is going away). */
		flushWaiting() {
			waiting.slice().forEach( ( item ) => settle( item, false ) );
		},
		/** Queue one item of feature data under a namespace. */
		push( ns, item ) {
			if ( stopped || typeof ns !== 'string' || ! /^[a-z][a-z0-9_.-]{0,31}$/.test( ns ) || ! item || typeof item !== 'object' ) {
				return false;
			}
			const list = ext[ ns ] || ( Object.keys( ext ).length < 8 ? ( ext[ ns ] = [] ) : null );
			if ( ! list || list.length >= 200 ) {
				return false;
			}
			list.push( item );
			extCount++;
			return true;
		},
		pending() {
			return clicks.length > 0 || signals.length > 0 || extCount > 0;
		},
		dirty() {
			return dirty || clicks.length > 0 || signals.length > 0 || extCount > 0;
		},
		elapsed() {
			return Date.now() - pvStart;
		},
		scroll() {
			return Math.round( maxScroll );
		},
		drain() {
			measure();
			const out = { clicks, signals, scroll: Math.round( maxScroll ), docH, ext: extCount ? ext : undefined };
			clicks = [];
			signals = [];
			ext = {};
			extCount = 0;
			dirty = false;
			return out;
		},
		newPageview() {
			waiting = [];
			recent = [];
			errors = {};
			errorCount = 0;
			lastDead = {};
			maxScroll = 0;
			docH = 0;
			pvStart = Date.now();
			dirty = true;
			measure();
		},
		stop() {
			stopped = true;
			window.removeEventListener( 'click', onClick, true );
			window.removeEventListener( 'error', onError, true );
			window.removeEventListener( 'unhandledrejection', onRejection );
			window.removeEventListener( 'scroll', onScroll );
		},
	};
}

/**
 * Public browser API shared by both bundles.
 *
 * @param {Object} col   Collector.
 * @param {Object} extra Bundle-specific methods.
 * @return {Object} API.
 */
export function publicApi( col, extra ) {
	return Object.assign(
		{
			on: col.on,
			push: col.push,
			registerDetector( detector ) {
				if ( typeof detector === 'function' ) {
					try {
						detector( { signal: col.signal, on: col.on } );
					} catch ( e ) {}
				}
			},
		},
		extra
	);
}
