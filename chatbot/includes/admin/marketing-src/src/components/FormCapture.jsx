import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// Display order and labels. Sources the server has not registered are shown as WPBot Pro cards;
// the free plugin captures from WPForms and Chatbot Sessions.
const GROUPS = [
    { title: 'Contact Forms Live Capture', sources: [['cf7', 'Contact Form 7'], ['wpforms', 'WPForms'], ['gravity', 'Gravity Forms']] },
    { title: 'Chatbot & Built-in Forms', sources: [['chatbot', 'Chatbot Sessions'], ['conversational', 'Conversational Forms']] },
];

const proBadge = (
    <span style={{ background: '#f97316', color: '#fff', fontSize: '10px', padding: '1px 5px', borderRadius: '3px', fontWeight: 700 }}>PRO</span>
);

const FormCapture = () => {
    const proUrl = window.qcwpbotData?.proUrl || 'https://www.wpbot.pro/pricing/';
    const [status, setStatus] = useState(null);
    const [toggling, setToggling] = useState(null);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        apiFetch({ path: '/wpbot-crm/v1/integrations/status' })
            .then(setStatus)
            .catch(err => {
                setStatus({ capturing: {} });
                setNotice({ type: 'error', text: 'Could not load capture status: ' + (err.message || 'Unknown error') });
            });
    }, []);

    const toggle = async (source, enabled) => {
        setToggling(source);
        try {
            const res = await apiFetch({ path: '/wpbot-crm/v1/integrations/capture', method: 'POST', data: { source, enabled } });
            setStatus(prev => ({ ...prev, capturing: { ...prev.capturing, [source]: !!res.capturing } }));
            setNotice({ type: res.capturing ? 'success' : 'info', text: res.message });
            setTimeout(() => setNotice(null), 5000);
        } catch (e) {
            setNotice({ type: 'error', text: 'Error updating capture setting: ' + (e.message || 'Unknown error') });
        } finally {
            setToggling(null);
        }
    };

    const renderCard = ([key, label]) => {
        const registered = status && Object.prototype.hasOwnProperty.call(status, key);
        const isActive = registered && !!status[key];
        const isCapturing = registered && !!status.capturing?.[key];
        const isToggling = toggling === key;

        let state;
        if (!registered) {
            state = <span style={{ fontSize: '11px', color: '#ea580c', background: '#ffedd5', padding: '2px 8px', borderRadius: '12px', fontWeight: 600 }}>WPBot Pro</span>;
        } else if (!isActive) {
            state = <span style={{ color: '#ef4444', fontSize: '12px', fontWeight: 500 }}>(Not Active)</span>;
        } else if (isCapturing) {
            state = <span style={{ fontSize: '11px', color: '#15803d', background: '#dcfce7', padding: '2px 8px', borderRadius: '12px', fontWeight: 600 }}>Live Capturing</span>;
        } else {
            state = <span style={{ fontSize: '11px', color: '#64748b', background: '#f1f5f9', padding: '2px 8px', borderRadius: '12px', fontWeight: 500 }}>Capture Inactive</span>;
        }

        let help;
        if (!registered) {
            help = `Live capture from ${label} comes with WPBot Pro.`;
        } else if (isCapturing) {
            help = `New ${label} submissions are added to the "${label} Leads" list.`;
        } else {
            help = `Turn on Capture to add new ${label} submissions to your contacts.`;
        }

        return (
            <div
                key={key}
                style={{
                    marginBottom: '15px', padding: '16px', borderRadius: '8px', border: '1px solid',
                    borderColor: !registered ? '#fed7aa' : (isCapturing ? '#86efac' : '#e2e8f0'),
                    background: !registered ? '#fffaf5' : (isCapturing ? '#f0fdf4' : '#fff'),
                }}
            >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '15px' }}>
                    <div style={{ flex: 1 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            <strong style={{ fontSize: '14px', color: '#1e293b' }}>{label}</strong>
                            {!registered && proBadge}
                            {state}
                        </div>
                        <p style={{ margin: '5px 0 0 0', fontSize: '12px', color: '#64748b', lineHeight: '1.4' }}>{help}</p>
                    </div>
                    {registered && (
                        <Button
                            isPrimary={!isCapturing && isActive}
                            isDestructive={isCapturing}
                            isSecondary={!isActive}
                            disabled={!isActive || isToggling}
                            onClick={() => toggle(key, !isCapturing)}
                            style={{ minWidth: '95px', justifyContent: 'center', fontWeight: 600 }}
                        >
                            {isToggling ? 'Saving...' : (isCapturing ? 'Disable' : 'Capture')}
                        </Button>
                    )}
                </div>
            </div>
        );
    };

    const noticeColors = {
        success: { background: '#dcfce7', color: '#166534', border: '#86efac' },
        error: { background: '#fef2f2', color: '#991b1b', border: '#fecaca' },
        info: { background: '#f1f5f9', color: '#334155', border: '#cbd5e1' },
    };

    return (
        <div className="qcwpbot-form-capture" style={{ padding: '20px' }}>
            <h2 style={{ margin: '0 0 5px 0', fontSize: '24px' }}>Form Capture</h2>
            <p style={{ color: '#64748b', margin: '0 0 20px 0' }}>
                Add people who fill in your forms or chat with your bot to your contacts automatically. Capture from Contact Form 7, Gravity Forms and Conversational Forms is part of{' '}
                <a href={proUrl} target="_blank" rel="noopener noreferrer">WPBot Pro</a>.
            </p>

            {notice && (
                <div style={{ marginBottom: '20px', padding: '12px 16px', borderRadius: '8px', fontSize: '13px', fontWeight: 500, background: noticeColors[notice.type].background, color: noticeColors[notice.type].color, border: `1px solid ${noticeColors[notice.type].border}` }}>
                    {notice.text}
                </div>
            )}

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '30px' }}>
                {GROUPS.map(group => (
                    <div key={group.title}>
                        <h4 style={{ margin: '0 0 15px 0', fontSize: '15px', color: '#334155' }}>{group.title}</h4>
                        {status ? group.sources.map(renderCard) : <p style={{ color: '#64748b' }}>Loading…</p>}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default FormCapture;
