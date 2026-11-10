const Sidebar = ({ activeTab, setActiveTab }) => {
    const menuItems = [
        { id: 'analytics', label: 'Analytics', icon: 'dashicons-chart-bar' },
        { id: 'contacts', label: 'Contacts', icon: 'dashicons-groups' },
        { id: 'lists', label: 'Lists', icon: 'dashicons-list-view' },
        { id: 'tags', label: 'Tags', icon: 'dashicons-tag' },
        { id: 'form-capture', label: 'Form Capture', icon: 'dashicons-feedback' },
        { id: 'lead-finder', label: 'Lead Finder', icon: 'dashicons-search', isPro: true },
        { id: 'campaigns', label: 'Campaigns', icon: 'dashicons-megaphone' },
        { id: 'templates', label: 'Templates', icon: 'dashicons-media-document' },
        { id: 'automations', label: 'Automations', icon: 'dashicons-update' },
        { id: 'import-export', label: 'Import / Export', icon: 'dashicons-migrate' },
        { id: 'smtp', label: 'SMTP', icon: 'dashicons-email', isPro: true },
        { id: 'settings', label: 'General Settings', icon: 'dashicons-admin-settings' },
    ];

    return (
        <div className="qcwpbot-sidebar">
            <ul>
                {menuItems.map(item => (
                    <li 
                        key={item.id} 
                        className={activeTab === item.id ? 'active' : ''}
                        onClick={() => setActiveTab(item.id)}
                    >
                        <span className={`dashicons ${item.icon}`}></span>
                        <span className="menu-label">{item.label}</span>
                        {item.isPro && <span className="pro-badge">PRO</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default Sidebar;
