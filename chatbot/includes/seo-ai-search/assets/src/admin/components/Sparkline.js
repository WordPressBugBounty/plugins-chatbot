/**
 * Small line chart of a daily series (SVG, no library). The numbers are
 * also given as text, so the chart is decorative for screen readers.
 *
 * @param {Object}   props        Props.
 * @param {number[]} props.points Values, oldest first.
 * @param {string}   props.label  Accessible name.
 * @return {Element|null} Chart.
 */
const Sparkline = ( { points, label } ) => {
	const values = ( points || [] ).map( ( value ) => Number( value ) || 0 );
	if ( values.length < 2 ) {
		return null;
	}
	const width = 240;
	const height = 48;
	const max = Math.max( ...values, 1 );
	const step = width / ( values.length - 1 );
	const path = values
		.map(
			( value, index ) =>
				`${ index ? 'L' : 'M' }${ ( index * step ).toFixed( 1 ) },${ ( height - 2 - ( value / max ) * ( height - 4 ) ).toFixed( 1 ) }`
		)
		.join( ' ' );
	return (
		<svg
			className="qcseo-spark"
			viewBox={ `0 0 ${ width } ${ height }` }
			preserveAspectRatio="none"
			role="img"
			aria-label={ label }
		>
			<path
				d={ path }
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				vectorEffect="non-scaling-stroke"
			/>
		</svg>
	);
};

export default Sparkline;
