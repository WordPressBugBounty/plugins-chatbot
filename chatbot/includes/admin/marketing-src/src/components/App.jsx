import { useState } from '@wordpress/element';
import Sidebar from './Sidebar';
import Dashboard from './Dashboard';
import Campaigns from './Campaigns';
import Subscribers from './Subscribers';
import LeadFinder from './LeadFinder';
import FormCapture from './FormCapture';
import Lists from './Lists';
import Tags from './Tags';
import Templates from './Templates';
import Automations from './Automations';
import ImportExport from './ImportExport';
import Smtp from './Smtp';
import Settings from './Settings';

const App = () => {
    const [activeTab, setActiveTabState] = useState(() => {
        try {
            return localStorage.getItem('qcwpbot_active_tab') || 'analytics';
        } catch (e) {
            return 'analytics';
        }
    });

    const setActiveTab = (tab) => {
        try {
            localStorage.setItem('qcwpbot_active_tab', tab);
        } catch (e) {}
        setActiveTabState(tab);
    };

    const renderContent = () => {
        switch (activeTab) {
            case 'analytics':
                return <Dashboard setActiveTab={setActiveTab} />;
            case 'campaigns':
                return <Campaigns />;
            case 'contacts':
                return <Subscribers setActiveTab={setActiveTab} />;
            case 'lists':
                return <Lists />;
            case 'tags':
                return <Tags />;
            case 'form-capture':
                return <FormCapture />;
            case 'lead-finder':
                return <LeadFinder setActiveTab={setActiveTab} />;
            case 'templates':
                return <Templates />;
            case 'automations':
                return <Automations />;
            case 'import-export':
                return <ImportExport />;
            case 'smtp':
                return <Smtp />;
            case 'settings':
                return <Settings />;
            default:
                return <Dashboard setActiveTab={setActiveTab} />;
        }
    };

    return (
        <div className="qcwpbot-marketing-layout wrap">
            <Sidebar activeTab={activeTab} setActiveTab={setActiveTab} />
            <div className="qcwpbot-main-content">
                {renderContent()}
            </div>
        </div>
    );
};

export default App;
