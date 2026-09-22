import { render } from '@wordpress/element';
import App from './App';
import EmailTemplatesApp from './EmailTemplatesApp';
import ActivityLogsApp from './ActivityLogsApp';
import TablesApp from './TablesApp';
import './index.css';

// Main Workflow Builder
const container = document.getElementById('wpbot-automator-root');
if (container) {
    render(<App />, container);
}

// Email Template Builder
const emailBuilderContainer = document.getElementById('wpbot-email-builder-root');
if (emailBuilderContainer) {
    render(<EmailTemplatesApp />, emailBuilderContainer);
}

// Activity Logs
const activityLogContainer = document.getElementById('wpbot-activity-log-root');
if (activityLogContainer) {
    render(<ActivityLogsApp />, activityLogContainer);
}

// Tables Manager
const tablesContainer = document.getElementById('wpbot-tables-root');
if (tablesContainer) {
    render(<TablesApp />, tablesContainer);
}
