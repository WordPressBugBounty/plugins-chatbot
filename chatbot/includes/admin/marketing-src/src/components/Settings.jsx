import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, TextareaControl, CheckboxControl, ToggleControl, SelectControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const PROVIDERS = [
    { label: 'OpenAI (ChatGPT)', value: 'openai', defaultModel: 'gpt-4o-mini', help: 'Industry standard for AI text and template generation.' },
    { label: 'Google Gemini', value: 'gemini', defaultModel: 'gemini-2.5-flash', help: 'Google\'s high-speed, cost-effective multimodal model.' },
    { label: 'Anthropic Claude', value: 'claude', defaultModel: 'claude-haiku-4-5', help: 'Top-tier natural writing quality and nuanced email styles.' },
    { label: 'OpenRouter (Multi-Model Gateway)', value: 'openrouter', defaultModel: 'openai/gpt-4o-mini', help: 'Access hundreds of models with a single unified API key.' },
    { label: 'xAI Grok', value: 'grok', defaultModel: 'grok-3-mini', help: 'xAI\'s state-of-the-art fast model.' },
];

// Keep in sync with includes/insights/src/AI/Providers.php (the client that sends these requests).
const MODEL_PRESETS = {
    openai: [
        { label: 'GPT-4o mini (Recommended - Fast & Cost-effective)', value: 'gpt-4o-mini' },
        { label: 'GPT-4o', value: 'gpt-4o' },
        { label: 'GPT-4.1 mini', value: 'gpt-4.1-mini' },
        { label: 'GPT-4.1 (Most Capable)', value: 'gpt-4.1' },
    ],
    gemini: [
        { label: 'Gemini 2.5 Flash (Recommended - Very Fast)', value: 'gemini-2.5-flash' },
        { label: 'Gemini 2.5 Flash-Lite (Cheapest)', value: 'gemini-2.5-flash-lite' },
        { label: 'Gemini 2.5 Pro (High Quality & Reasoning)', value: 'gemini-2.5-pro' },
    ],
    claude: [
        { label: 'Claude Haiku 4.5 (Recommended - Fast & Lightweight)', value: 'claude-haiku-4-5' },
        { label: 'Claude Sonnet 4.6 (Outstanding Quality)', value: 'claude-sonnet-4-6' },
        { label: 'Claude Sonnet 4.5', value: 'claude-sonnet-4-5' },
    ],
    openrouter: [
        { label: 'OpenAI GPT-4o mini (via OpenRouter)', value: 'openai/gpt-4o-mini' },
        { label: 'Anthropic Claude Haiku 4.5 (via OpenRouter)', value: 'anthropic/claude-haiku-4.5' },
        { label: 'Google Gemini 2.5 Flash (via OpenRouter)', value: 'google/gemini-2.5-flash' },
    ],
    grok: [
        { label: 'Grok 3 mini (Recommended)', value: 'grok-3-mini' },
        { label: 'Grok 3', value: 'grok-3-latest' },
        { label: 'Grok 4', value: 'grok-4' },
    ],
};

const PROVIDER_METAS = {
    openai: {
        name: 'OpenAI',
        keyDoc: 'https://platform.openai.com/api-keys',
        placeholder: 'sk-proj-...',
    },
    gemini: {
        name: 'Google Gemini',
        keyDoc: 'https://aistudio.google.com/app/apikey',
        placeholder: 'AIzaSy...',
    },
    claude: {
        name: 'Anthropic Claude',
        keyDoc: 'https://console.anthropic.com/settings/keys',
        placeholder: 'sk-ant-api03-...',
    },
    openrouter: {
        name: 'OpenRouter',
        keyDoc: 'https://openrouter.ai/keys',
        placeholder: 'sk-or-v1-...',
    },
    grok: {
        name: 'xAI Grok',
        keyDoc: 'https://console.x.ai/',
        placeholder: 'xai-...',
    },
};

const Settings = () => {
    const defaultSiteName = window.qcwpbotData?.siteName || '';
    const defaultAdminEmail = window.qcwpbotData?.adminEmail || '';
    const defaultFooter = `<p>&copy; ${new Date().getFullYear()} ${defaultSiteName}. All rights reserved.</p>`;

    const [activeTab, setActiveTabState] = useState(() => {
        try {
            return localStorage.getItem('qcwpbot_settings_tab') || 'general';
        } catch (e) {
            return 'general';
        }
    });

    const setActiveTab = (tab) => {
        try {
            localStorage.setItem('qcwpbot_settings_tab', tab);
        } catch (e) {}
        setActiveTabState(tab);
    };

    const [saving, setSaving] = useState(false);

    // Form Fields - General
    const [fromName, setFromName] = useState(defaultSiteName);
    const [fromEmail, setFromEmail] = useState(defaultAdminEmail);
    const [replyTo, setReplyTo] = useState(defaultAdminEmail);
    const [emailsPerSecond, setEmailsPerSecond] = useState('10');
    const [companyName, setCompanyName] = useState(defaultSiteName);
    const [companyAddress, setCompanyAddress] = useState('');
    const [emailFooter, setEmailFooter] = useState(defaultFooter);
    const [unsubscribeText, setUnsubscribeText] = useState('Unsubscribe');
    const [doubleOptIn, setDoubleOptIn] = useState(false);
    
    // Form Fields - Sending & Tracking
    const [batchSize, setBatchSize] = useState('50');
    const [batchInterval, setBatchInterval] = useState('60');
    const [trackOpens, setTrackOpens] = useState(false);
    const [trackClicks, setTrackClicks] = useState(false);
    const [gdprMode, setGdprMode] = useState(false);

    // Form Fields - AI Provider & API
    const [aiProvider, setAiProvider] = useState('openai');
    const [aiModel, setAiModel] = useState('gpt-4o-mini');
    const [openaiApiKey, setOpenaiApiKey] = useState('');
    const [geminiApiKey, setGeminiApiKey] = useState('');
    const [claudeApiKey, setClaudeApiKey] = useState('');
    const [openrouterApiKey, setOpenrouterApiKey] = useState('');
    const [grokApiKey, setGrokApiKey] = useState('');
    const [showKeyToggle, setShowKeyToggle] = useState(false);
    const [showAllKeys, setShowAllKeys] = useState(false);

    // Test connection states
    const [testingConnection, setTestingConnection] = useState(false);
    const [testResult, setTestResult] = useState(null); // { type: 'success' | 'error', message: string }

    useEffect(() => {
        apiFetch({ path: '/wpbot-crm/v1/settings' })
            .then(res => {
                if (res) {
                    setFromName(res.from_name || defaultSiteName);
                    setFromEmail(res.from_email || defaultAdminEmail);
                    setReplyTo(res.reply_to || res.from_email || defaultAdminEmail);
                    if (res.emails_per_second) setEmailsPerSecond(res.emails_per_second.toString());
                    setCompanyName(res.company_name || defaultSiteName);
                    if (res.company_address) setCompanyAddress(res.company_address);
                    setEmailFooter(res.email_footer || defaultFooter);
                    setUnsubscribeText(res.unsubscribe_text || 'Unsubscribe');
                    if (res.double_opt_in !== undefined) setDoubleOptIn(!!res.double_opt_in);
                    if (res.batch_size) setBatchSize(res.batch_size.toString());
                    if (res.batch_interval) setBatchInterval(res.batch_interval.toString());
                    if (res.track_opens !== undefined) setTrackOpens(!!res.track_opens);
                    if (res.track_clicks !== undefined) setTrackClicks(!!res.track_clicks);
                    if (res.gdpr_mode !== undefined) setGdprMode(!!res.gdpr_mode);

                    // AI Settings
                    if (res.ai_provider) setAiProvider(res.ai_provider);
                    if (res.ai_model) setAiModel(res.ai_model);
                    if (res.openai_api_key) setOpenaiApiKey(res.openai_api_key);
                    if (res.gemini_api_key) setGeminiApiKey(res.gemini_api_key);
                    if (res.claude_api_key) setClaudeApiKey(res.claude_api_key);
                    if (res.openrouter_api_key) setOpenrouterApiKey(res.openrouter_api_key);
                    if (res.grok_api_key) setGrokApiKey(res.grok_api_key);
                }
            })
            .catch(err => console.error('Error loading settings', err));
    }, []);

    const handleProviderChange = (newProvider) => {
        setAiProvider(newProvider);
        setTestResult(null);
        // Default to recommended model for newly selected provider if current model doesn't belong
        const preset = MODEL_PRESETS[newProvider];
        if (preset && preset.length > 0) {
            setAiModel(preset[0].value);
        }
    };

    const handleSave = async (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        setSaving(true);
        const data = {
            from_name: fromName,
            from_email: fromEmail,
            reply_to: replyTo,
            emails_per_second: emailsPerSecond,
            company_name: companyName,
            company_address: companyAddress,
            email_footer: emailFooter,
            unsubscribe_text: unsubscribeText,
            double_opt_in: doubleOptIn ? 1 : 0,
            batch_size: batchSize,
            batch_interval: batchInterval,
            track_opens: trackOpens ? 1 : 0,
            track_clicks: trackClicks ? 1 : 0,
            gdpr_mode: gdprMode ? 1 : 0,
            // AI Settings
            ai_provider: aiProvider,
            ai_model: aiModel,
            openai_api_key: openaiApiKey,
            gemini_api_key: geminiApiKey,
            claude_api_key: claudeApiKey,
            openrouter_api_key: openrouterApiKey,
            grok_api_key: grokApiKey,
        };

        try {
            await apiFetch({
                path: '/wpbot-crm/v1/settings',
                method: 'POST',
                data: data
            });
            alert('Settings saved successfully!');
        } catch (e) {
            alert('Error saving settings: ' + (e.message || 'Unknown error'));
        } finally {
            setSaving(false);
        }
    };

    const handleTestConnection = async (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        setTestingConnection(true);
        setTestResult(null);

        let activeKey = '';
        if (aiProvider === 'openai') activeKey = openaiApiKey;
        else if (aiProvider === 'gemini') activeKey = geminiApiKey;
        else if (aiProvider === 'claude') activeKey = claudeApiKey;
        else if (aiProvider === 'openrouter') activeKey = openrouterApiKey;
        else if (aiProvider === 'grok') activeKey = grokApiKey;

        try {
            const res = await apiFetch({
                path: '/wpbot-crm/v1/ai/test',
                method: 'POST',
                data: {
                    provider: aiProvider,
                    api_key: activeKey,
                    model: aiModel
                }
            });

            if (res && res.success) {
                setTestResult({
                    type: 'success',
                    message: res.message + (res.reply ? ` (AI responded: "${res.reply.trim()}")` : '')
                });
            } else {
                setTestResult({
                    type: 'error',
                    message: (res && res.message) ? res.message : 'Connection test returned an unexpected response.'
                });
            }
        } catch (e) {
            console.error('Test Connection Error:', e);
            setTestResult({
                type: 'error',
                message: e?.message || e?.code || 'Failed to connect. Please check your API key, model, and network.'
            });
        } finally {
            setTestingConnection(false);
        }
    };

    const tabs = [
        { id: 'general', label: 'General' },
        { id: 'ai', label: 'AI Provider & API' },
        { id: 'sending', label: 'Sending & Tracking' },
    ];

    const currentPresets = MODEL_PRESETS[aiProvider] || [];
    const activeMeta = PROVIDER_METAS[aiProvider] || PROVIDER_METAS.openai;

    // Active key getter & setter
    const getActiveKey = () => {
        if (aiProvider === 'openai') return openaiApiKey;
        if (aiProvider === 'gemini') return geminiApiKey;
        if (aiProvider === 'claude') return claudeApiKey;
        if (aiProvider === 'openrouter') return openrouterApiKey;
        if (aiProvider === 'grok') return grokApiKey;
        return '';
    };

    const setActiveKey = (val) => {
        if (aiProvider === 'openai') setOpenaiApiKey(val);
        else if (aiProvider === 'gemini') setGeminiApiKey(val);
        else if (aiProvider === 'claude') setClaudeApiKey(val);
        else if (aiProvider === 'openrouter') setOpenrouterApiKey(val);
        else if (aiProvider === 'grok') setGrokApiKey(val);
    };

    return (
        <div className="qcwpbot-settings-wrap">
            <h2 style={{ fontSize: '20px', fontWeight: 'bold', margin: '0 0 20px 0' }}>General Settings</h2>

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden' }}>
                {/* Tabs Header */}
                <div style={{ borderBottom: '1px solid #e2e8f0', display: 'flex', padding: '0 20px' }}>
                    {tabs.map(tab => (
                        <button
                            type="button"
                            key={tab.id}
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                setActiveTab(tab.id);
                            }}
                            style={{
                                background: 'transparent',
                                border: 'none',
                                borderBottom: activeTab === tab.id ? '2px solid #5b4e96' : '2px solid transparent',
                                color: activeTab === tab.id ? '#5b4e96' : '#000',
                                fontWeight: activeTab === tab.id ? 'bold' : 'normal',
                                padding: '15px 20px',
                                cursor: 'pointer',
                                fontSize: '14px',
                                transition: 'all 0.2s'
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Tab Content */}
                <div style={{ padding: '30px' }}>
                    {activeTab === 'general' && (
                        <div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '30px', marginBottom: '20px' }}>
                                <TextControl
                                    label="From Name"
                                    value={fromName}
                                    onChange={setFromName}
                                />
                                <TextControl
                                    label="From Email"
                                    value={fromEmail}
                                    onChange={setFromEmail}
                                />
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '30px', marginBottom: '20px' }}>
                                <TextControl
                                    label="Reply-To"
                                    value={replyTo}
                                    onChange={setReplyTo}
                                />
                                <TextControl
                                    label="Emails Per Second"
                                    value={emailsPerSecond}
                                    onChange={setEmailsPerSecond}
                                />
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '30px', marginBottom: '20px' }}>
                                <TextControl
                                    label="Company Name"
                                    value={companyName}
                                    onChange={setCompanyName}
                                />
                                <TextControl
                                    label="Company Address"
                                    value={companyAddress}
                                    onChange={setCompanyAddress}
                                />
                            </div>

                            <div style={{ marginBottom: '20px' }}>
                                <TextareaControl
                                    label="Email Footer"
                                    value={emailFooter}
                                    onChange={setEmailFooter}
                                    rows={4}
                                />
                            </div>

                            <div style={{ marginBottom: '20px', maxWidth: '50%' }}>
                                <TextControl
                                    label="Unsubscribe Text"
                                    value={unsubscribeText}
                                    onChange={setUnsubscribeText}
                                />
                            </div>

                            <button 
                                type="button"
                                disabled={saving} 
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleSave(e);
                                }} 
                                style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '8px 24px', borderRadius: '4px', fontWeight: 'bold', cursor: saving ? 'not-allowed' : 'pointer' }}
                            >
                                {saving ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    )}

                    {activeTab === 'ai' && (
                        <div>
                            <p style={{ color: '#64748b', marginBottom: '24px', fontSize: '14px', lineHeight: '1.6' }}>
                                Select your preferred AI Provider and configure your API key. This AI engine powers the AI Email Writer in campaigns and reads contact details from unusual form submissions.
                            </p>

                            {/* Active Provider Card */}
                            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '24px', marginBottom: '24px', background: '#fafbfc' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px', marginBottom: '20px' }}>
                                    <div>
                                        <SelectControl
                                            label="AI PROVIDER"
                                            value={aiProvider}
                                            options={PROVIDERS.map(p => ({ label: p.label, value: p.value }))}
                                            onChange={handleProviderChange}
                                            help={PROVIDERS.find(p => p.value === aiProvider)?.help}
                                        />
                                    </div>
                                    <div>
                                        <SelectControl
                                            label="MODEL / ENGINE"
                                            value={aiModel}
                                            options={[
                                                ...currentPresets,
                                                { label: 'Custom Model ID (enter below)', value: 'custom' }
                                            ]}
                                            onChange={(val) => {
                                                if (val !== 'custom') setAiModel(val);
                                            }}
                                            help="Select a recommended model or customize below."
                                        />
                                    </div>
                                </div>

                                <div style={{ marginBottom: '20px' }}>
                                    <TextControl
                                        label="MODEL ID (OR CUSTOM MODEL)"
                                        value={aiModel}
                                        onChange={setAiModel}
                                        placeholder="e.g. gpt-4o-mini, gemini-2.5-flash, claude-haiku-4-5"
                                        help="Specify the exact model identifier used by the selected provider."
                                    />
                                </div>

                                {/* Active API Key Input */}
                                <div style={{ marginBottom: '20px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                                        <label style={{ fontWeight: 'bold', fontSize: '13px', color: '#1e293b' }}>
                                            {activeMeta.name} API KEY
                                        </label>
                                        <div style={{ display: 'flex', gap: '15px', alignItems: 'center' }}>
                                            <a 
                                                href={activeMeta.keyDoc} 
                                                target="_blank" 
                                                rel="noopener noreferrer" 
                                                style={{ fontSize: '12px', color: '#be1e43', textDecoration: 'none', fontWeight: 'bold' }}
                                            >
                                                Get {activeMeta.name} API Key ↗
                                            </a>
                                            <button
                                                type="button"
                                                onClick={() => setShowKeyToggle(!showKeyToggle)}
                                                style={{ background: 'none', border: 'none', fontSize: '12px', color: '#64748b', cursor: 'pointer', padding: 0 }}
                                            >
                                                {showKeyToggle ? 'Hide Key' : 'Show Key'}
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        type={showKeyToggle ? 'text' : 'password'}
                                        value={getActiveKey()}
                                        onChange={(e) => setActiveKey(e.target.value)}
                                        placeholder={activeMeta.placeholder}
                                        style={{
                                            width: '100%',
                                            padding: '8px 12px',
                                            border: '1px solid #cbd5e1',
                                            borderRadius: '4px',
                                            fontSize: '14px',
                                            fontFamily: 'monospace',
                                            boxSizing: 'border-box'
                                        }}
                                    />
                                    <p style={{ fontSize: '12px', color: '#64748b', marginTop: '6px', marginBotton: 0 }}>
                                        Your API key is securely saved and used only on your server to communicate with {activeMeta.name}.
                                    </p>
                                </div>

                                {/* Test Connection button & Feedback */}
                                <div style={{ display: 'flex', alignItems: 'center', gap: '15px', paddingTop: '10px', borderTop: '1px solid #e2e8f0' }}>
                                    <button
                                        type="button"
                                        className="components-button is-secondary"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            handleTestConnection(e);
                                        }}
                                        disabled={testingConnection || !getActiveKey()}
                                        style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', cursor: (testingConnection || !getActiveKey()) ? 'not-allowed' : 'pointer' }}
                                    >
                                        {testingConnection ? <Spinner /> : '⚡ Test Connection'}
                                    </button>
                                    {!getActiveKey() && (
                                        <span style={{ fontSize: '12px', color: '#94a3b8' }}>Enter an API key to test connection</span>
                                    )}
                                </div>

                                {testResult && (
                                    <div style={{
                                        marginTop: '15px',
                                        padding: '12px 16px',
                                        borderRadius: '6px',
                                        fontSize: '13px',
                                        lineHeight: '1.5',
                                        background: testResult.type === 'success' ? '#f0fdf4' : '#fef2f2',
                                        color: testResult.type === 'success' ? '#166534' : '#991b1b',
                                        border: `1px solid ${testResult.type === 'success' ? '#bbf7d0' : '#fecaca'}`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '8px'
                                    }}>
                                        <span>{testResult.type === 'success' ? '✅' : '❌'}</span>
                                        <span>{testResult.message}</span>
                                    </div>
                                )}
                            </div>

                            <button 
                                type="button"
                                disabled={saving} 
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleSave(e);
                                }} 
                                style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '8px 24px', borderRadius: '4px', fontWeight: 'bold', cursor: saving ? 'not-allowed' : 'pointer' }}
                            >
                                {saving ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    )}

                    {activeTab === 'sending' && (
                        <div>
                            {/* Sending Configuration Card */}
                            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', marginBottom: '30px' }}>
                                <div style={{ padding: '15px 20px', borderBottom: '1px solid #e2e8f0', fontWeight: 'bold', fontSize: '16px' }}>
                                    Sending Configuration
                                </div>
                                <div style={{ padding: '20px' }}>
                                    <p style={{ color: '#64748b', marginBottom: '20px', fontSize: '14px', lineHeight: '1.5' }}>
                                        Batch size controls how many queued emails are attempted in one cron run. Batch interval is the waiting time between those runs, which helps protect your server and mail provider from sending too many emails at once.
                                    </p>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '30px' }}>
                                        <div>
                                            <TextControl
                                                label="Batch Size"
                                                value={batchSize}
                                                onChange={setBatchSize}
                                                help="Example: 50 sends up to 50 emails each time the queue runs."
                                            />
                                        </div>
                                        <div>
                                            <TextControl
                                                label="Batch Interval (seconds)"
                                                value={batchInterval}
                                                onChange={setBatchInterval}
                                                help="Example: 60 waits about one minute before the next batch can start."
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Tracking & Compliance Card */}
                            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', marginBottom: '30px' }}>
                                <div style={{ padding: '15px 20px', borderBottom: '1px solid #e2e8f0', fontWeight: 'bold', fontSize: '16px' }}>
                                    Tracking & Compliance
                                </div>
                                <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                    <ToggleControl
                                        label="Track Opens"
                                        checked={trackOpens}
                                        onChange={setTrackOpens}
                                    />
                                    <ToggleControl
                                        label="Track Clicks"
                                        checked={trackClicks}
                                        onChange={setTrackClicks}
                                    />
                                    <ToggleControl
                                        label="Double Opt-In"
                                        checked={doubleOptIn}
                                        onChange={setDoubleOptIn}
                                    />
                                    <ToggleControl
                                        label="GDPR Mode"
                                        checked={gdprMode}
                                        onChange={setGdprMode}
                                    />
                                </div>
                            </div>

                            <button 
                                type="button"
                                disabled={saving} 
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleSave(e);
                                }} 
                                style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '8px 24px', borderRadius: '4px', fontWeight: 'bold', cursor: saving ? 'not-allowed' : 'pointer' }}
                            >
                                {saving ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Settings;
