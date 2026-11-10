/* global wp */
const { createElement } = wp.element;

export function Toggle( { id, checked, onChange, label, help } ) {
	return (
		<div className="qcin-field qcin-field--toggle">
			<label className="qcin-switch" htmlFor={ id }>
				<input id={ id } type="checkbox" role="switch" checked={ !! checked } onChange={ ( e ) => onChange( e.target.checked ) } />
				<span className="qcin-switch__track" aria-hidden="true" />
				<span className="qcin-switch__label">{ label }</span>
			</label>
			{ help && <p className="qcin-help">{ help }</p> }
		</div>
	);
}

export function NumberField( { id, label, value, min, max, suffix, help, onChange } ) {
	return (
		<div className="qcin-field">
			<label htmlFor={ id }>{ label }</label>
			<div className="qcin-input-suffix">
				<input id={ id } type="number" min={ min } max={ max } value={ value } onChange={ ( e ) => onChange( e.target.value ) } />
				{ suffix && <span>{ suffix }</span> }
			</div>
			{ help && <p className="qcin-help">{ help }</p> }
		</div>
	);
}

export function TextArea( { id, label, value, help, placeholder, onChange } ) {
	return (
		<div className="qcin-field">
			<label htmlFor={ id }>{ label }</label>
			<textarea id={ id } rows="3" value={ value } placeholder={ placeholder } onChange={ ( e ) => onChange( e.target.value ) } />
			{ help && <p className="qcin-help">{ help }</p> }
		</div>
	);
}

export function Section( { id, title, desc, children } ) {
	return (
		<section id={ id } className="qcin-card qcin-section">
			<div className="qcin-section__head">
				<h2 className="qcin-card__title">{ title }</h2>
				{ desc && <p className="qcin-card__sub">{ desc }</p> }
			</div>
			<div className="qcin-section__body">{ children }</div>
		</section>
	);
}
