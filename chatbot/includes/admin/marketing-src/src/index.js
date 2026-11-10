import { render } from '@wordpress/element';
import App from './components/App';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('qcwpbot-app');
    if (container) {
        render(<App />, container);
    }
});
