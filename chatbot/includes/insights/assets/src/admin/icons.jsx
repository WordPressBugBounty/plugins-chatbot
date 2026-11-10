/* global wp */
const { createElement } = wp.element;

function Svg( { children, size = 18, label } ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden={ label ? undefined : 'true' } role={ label ? 'img' : undefined } aria-label={ label }>
			{ children }
		</svg>
	);
}

export const Icon = {
	overview: ( p ) => (
		<Svg { ...p }>
			<rect x="3" y="3" width="7" height="9" rx="1.5" />
			<rect x="14" y="3" width="7" height="5" rx="1.5" />
			<rect x="14" y="12" width="7" height="9" rx="1.5" />
			<rect x="3" y="16" width="7" height="5" rx="1.5" />
		</Svg>
	),
	heatmaps: ( p ) => (
		<Svg { ...p }>
			<path d="M12 3c1 3.5 5 5.5 5 10a5 5 0 0 1-10 0c0-2 1-3.5 2-4.5.3 1.6 1.2 2.5 2.2 2.8C10.6 8.8 11 5.6 12 3z" />
		</Svg>
	),
	alert: ( p ) => (
		<Svg { ...p }>
			<path d="M12 3l9.5 16.5h-19z" />
			<path d="M12 10v4M12 17.5v.01" />
		</Svg>
	),
	refresh: ( p ) => (
		<Svg { ...p }>
			<path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7" />
		</Svg>
	),
	recordings: ( p ) => (
		<Svg { ...p }>
			<rect x="3" y="5" width="18" height="14" rx="3" />
			<path d="M10 9.5v5l4.5-2.5z" fill="currentColor" />
		</Svg>
	),
	settings: ( p ) => (
		<Svg { ...p }>
			<circle cx="12" cy="12" r="3" />
			<path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
		</Svg>
	),
	desktop: ( p ) => (
		<Svg { ...p }>
			<rect x="2" y="4" width="20" height="13" rx="2" />
			<path d="M8 21h8M12 17v4" />
		</Svg>
	),
	tablet: ( p ) => (
		<Svg { ...p }>
			<rect x="4" y="2" width="16" height="20" rx="2" />
			<path d="M11 18h2" />
		</Svg>
	),
	mobile: ( p ) => (
		<Svg { ...p }>
			<rect x="6" y="2" width="12" height="20" rx="2" />
			<path d="M11 18h2" />
		</Svg>
	),
	star: ( p ) => (
		<Svg { ...p }>
			<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" fill={ p && p.filled ? 'currentColor' : 'none' } />
		</Svg>
	),
	trash: ( p ) => (
		<Svg { ...p }>
			<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14" />
		</Svg>
	),
	back: ( p ) => (
		<Svg { ...p }>
			<path d="M15 18l-6-6 6-6" />
		</Svg>
	),
	page: ( p ) => (
		<Svg { ...p }>
			<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" />
			<path d="M14 3v6h6" />
		</Svg>
	),
	shield: ( p ) => (
		<Svg { ...p }>
			<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" />
		</Svg>
	),
	sparkle: ( p ) => (
		<Svg { ...p }>
			<path d="M10 3.5l1.6 4.4 4.4 1.6-4.4 1.6L10 15.5l-1.6-4.4L4 9.5l4.4-1.6z" />
			<path d="M17.5 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" />
		</Svg>
	),
	copy: ( p ) => (
		<Svg { ...p }>
			<rect x="8" y="8" width="12" height="12" rx="2" />
			<path d="M16 8V5.5A1.5 1.5 0 0 0 14.5 4h-9A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8" />
		</Svg>
	),
};

export default Icon;
