import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Spinner, TextControl, SelectControl, TextareaControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const CampaignsList = ({ onCreateNew, onEdit }) => {
    const [campaigns, setCampaigns] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('All');

    const fetchCampaigns = () => {
        setLoading(true);
        apiFetch({ path: '/wpbot-crm/v1/campaigns' })
            .then(res => {
                setCampaigns(res.items || res || []);
                setLoading(false);
            })
            .catch(err => {
                console.error("Campaigns fetch error:", err);
                setCampaigns([]);
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchCampaigns();
    }, []);

    const handleDelete = (id) => {
        if (!confirm('Are you sure you want to delete this campaign?')) return;
        apiFetch({
            path: `/wpbot-crm/v1/campaigns/${id}`,
            method: 'DELETE'
        }).then(() => fetchCampaigns());
    };

    const handleDuplicate = (id) => {
        apiFetch({
            path: `/wpbot-crm/v1/campaigns/${id}/duplicate`,
            method: 'POST'
        }).then(() => fetchCampaigns());
    };

    return (
        <div className="qcwpbot-campaigns-list">
            <div className="qcwpbot-header">
                <h2>Campaigns ({campaigns.length})</h2>
                <Button className="qcwpbot-primary-btn" onClick={onCreateNew}>+ New Campaign</Button>
            </div>


            <div className="qcwpbot-table-container">
                <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '15px', gap: '10px', borderBottom: '1px solid #e2e8f0' }}>
                    <div style={{ width: '250px' }}>
                        <TextControl placeholder="Search campaigns..." value={search} onChange={setSearch} />
                    </div>
                    <div style={{ width: '120px' }}>
                        <SelectControl 
                            value={statusFilter} 
                            options={[{ label: 'All', value: 'All' }, { label: 'Draft', value: 'draft' }, { label: 'Sent', value: 'sent' }]}
                            onChange={setStatusFilter}
                        />
                    </div>
                </div>
                
                <table className="qcwpbot-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>CAMPAIGN</th>
                            <th>STATUS</th>
                            <th>SUBJECT</th>
                            <th>SENT</th>
                            <th>OPEN RATE</th>
                            <th>CLICK RATE</th>
                            <th>CREATED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr><td colSpan="9" style={{ textAlign: 'center' }}><Spinner /></td></tr>
                        ) : campaigns.length > 0 ? (
                            campaigns.map(camp => (
                                <tr key={camp.id}>
                                    <td>{camp.id}</td>
                                    <td style={{ fontWeight: 'bold', color: '#475569', cursor: 'pointer' }} onClick={() => onEdit(camp.id)}>{camp.title || camp.name || '(No Name)'}</td>
                                    <td style={{ fontWeight: 'bold' }}>{camp.status ? camp.status.toUpperCase() : 'DRAFT'}</td>
                                    <td>{camp.subject || '---'}</td>
                                    <td>{camp.sent_count || 0}</td>
                                    <td>{camp.open_rate || '0.0%'}</td>
                                    <td>{camp.click_rate || '0.0%'}</td>
                                    <td>{new Date(camp.created_at).toLocaleDateString()}</td>
                                    <td style={{ whiteSpace: 'nowrap' }}>
                                        <div style={{ display: 'inline-flex', gap: '6px', alignItems: 'center' }}>
                                            <button
                                                type="button"
                                                title="Edit Campaign"
                                                onClick={() => onEdit(camp.id)}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    width: '28px',
                                                    height: '28px',
                                                    borderRadius: '4px',
                                                    background: '#f1f5f9',
                                                    color: '#5b4e96',
                                                    border: '1px solid #cbd5e1',
                                                    cursor: 'pointer',
                                                    padding: 0
                                                }}
                                            >
                                                <span className="dashicons dashicons-edit" style={{ fontSize: '15px', width: '15px', height: '15px', lineHeight: '15px' }}></span>
                                            </button>
                                            <button
                                                type="button"
                                                title="Duplicate Campaign"
                                                onClick={() => handleDuplicate(camp.id)}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    width: '28px',
                                                    height: '28px',
                                                    borderRadius: '4px',
                                                    background: '#f1f5f9',
                                                    color: '#475569',
                                                    border: '1px solid #cbd5e1',
                                                    cursor: 'pointer',
                                                    padding: 0
                                                }}
                                            >
                                                <span className="dashicons dashicons-admin-page" style={{ fontSize: '15px', width: '15px', height: '15px', lineHeight: '15px' }}></span>
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete Campaign"
                                                onClick={() => handleDelete(camp.id)}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    width: '28px',
                                                    height: '28px',
                                                    borderRadius: '4px',
                                                    background: '#fef2f2',
                                                    color: '#ef4444',
                                                    border: '1px solid #fee2e2',
                                                    cursor: 'pointer',
                                                    padding: 0
                                                }}
                                            >
                                                <span className="dashicons dashicons-trash" style={{ fontSize: '15px', width: '15px', height: '15px', lineHeight: '15px' }}></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="9" className="empty-row">No campaigns found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

const CampaignWizard = ({ onBack, campaignId, setCampaignId }) => {
    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [aiExpanded, setAiExpanded] = useState(true);
    const [aiPrompt, setAiPrompt] = useState('');
    const [aiTone, setAiTone] = useState('professional');
    const [aiLayout, setAiLayout] = useState('simple-html');
    const [generatingAi, setGeneratingAi] = useState(false);
    const [templatesExpanded, setTemplatesExpanded] = useState(true);
    const [systemTemplates, setSystemTemplates] = useState([]);
    const [availableLists, setAvailableLists] = useState([]);
    const [availableTags, setAvailableTags] = useState([]);
    const [editorMode, setEditorMode] = useState('visual');
    const textareaRef = useRef(null);
    const visualEditorRef = useRef(null);

    const [data, setData] = useState({
        title: '',
        email_subject: '',
        email_pre_header: '',
        email_body: '',
        utm_status: false,
        settings: {
            send_to: 'all',
            lists: [],
            tags: [],
            exclude_lists: [],
            exclude_tags: [],
            enable_ab: false,
            send_throttle: 0
        }
    });

    useEffect(() => {
        // Fetch templates
        apiFetch({ path: '/wpbot-crm/v1/templates' })
            .then(res => {
                setSystemTemplates(res.items || res || []);
            })
            .catch(console.error);

        // Fetch lists
        apiFetch({ path: '/wpbot-crm/v1/lists' })
            .then(res => {
                setAvailableLists(res.items || res || []);
            })
            .catch(console.error);

        // Fetch tags
        apiFetch({ path: '/wpbot-crm/v1/tags' })
            .then(res => {
                setAvailableTags(res.items || res || []);
            })
            .catch(console.error);

        if (campaignId) {
            apiFetch({ path: `/wpbot-crm/v1/campaigns/${campaignId}` })
                .then(res => {
                    if (res) {
                        const settings = res.settings ? (typeof res.settings === 'string' ? JSON.parse(res.settings) : res.settings) : {};
                        setData({
                            title: res.title || res.name || '',
                            email_subject: res.email_subject || res.subject || '',
                            email_pre_header: res.email_pre_header || res.preview_text || '',
                            email_body: res.email_body || '',
                            utm_status: !!res.utm_status,
                            settings: {
                                ...data.settings,
                                ...settings,
                                lists: settings.lists || settings.list_ids || [],
                                tags: settings.tags || settings.tag_ids || []
                            }
                        });
                    }
                });
        }
    }, [campaignId]);

    useEffect(() => {
        if (visualEditorRef.current && editorMode === 'visual') {
            if (visualEditorRef.current.innerHTML !== (data.email_body || '')) {
                visualEditorRef.current.innerHTML = data.email_body || '';
            }
        }
    }, [editorMode, data.email_body]);

    const executeFormat = (command, value = null, fallbackStart = '', fallbackEnd = '') => {
        if (editorMode === 'visual' && visualEditorRef.current) {
            visualEditorRef.current.focus();
            document.execCommand(command, false, value);
            setData(prev => ({ ...prev, email_body: visualEditorRef.current.innerHTML }));
        } else {
            insertTag(fallbackStart, fallbackEnd);
        }
    };

    const insertTag = (startTag, endTag = '') => {
        if (!textareaRef.current) return;
        const textarea = textareaRef.current;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = data.email_body || '';
        const before = text.substring(0, start);
        const selected = text.substring(start, end);
        const after = text.substring(end);
        
        const newText = before + startTag + selected + endTag + after;
        setData(prev => ({ ...prev, email_body: newText }));
        
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(start + startTag.length, start + startTag.length + selected.length);
        }, 0);
    };

    const handleAddMedia = () => {
        if (typeof wp !== 'undefined' && wp.media) {
            const mediaUploader = wp.media({
                title: 'Select Media',
                button: { text: 'Insert into email' },
                multiple: false
            });
            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const imgTag = `<img src="${attachment.url}" alt="${attachment.alt || ''}" style="max-width: 100%; height: auto; margin: 10px 0;" />`;
                if (editorMode === 'visual' && visualEditorRef.current) {
                    visualEditorRef.current.focus();
                    document.execCommand('insertHTML', false, imgTag);
                    setData(prev => ({ ...prev, email_body: visualEditorRef.current.innerHTML }));
                } else {
                    setData(prev => ({ ...prev, email_body: (prev.email_body || '') + imgTag }));
                }
            });
            mediaUploader.open();
        } else {
            alert('Media library is not available.');
        }
    };

    const insertMergeTag = (tag) => {
        if (editorMode === 'visual' && visualEditorRef.current) {
            visualEditorRef.current.focus();
            document.execCommand('insertText', false, tag);
            setData(prev => ({ ...prev, email_body: visualEditorRef.current.innerHTML }));
        } else if (textareaRef.current) {
            insertTag(tag, '');
        } else {
            setData(prev => ({ ...prev, email_body: (prev.email_body || '') + tag }));
        }
    };

    const updateData = (field, value) => {
        setData(prev => ({ ...prev, [field]: value }));
    };

    const updateSettings = (field, value) => {
        setData(prev => ({ ...prev, settings: { ...prev.settings, [field]: value } }));
    };

    const toggleSelectedList = (id) => {
        const current = data.settings.lists || [];
        const next = current.includes(id) ? current.filter(item => item !== id) : [...current, id];
        updateSettings('lists', next);
    };

    const selectAllLists = () => {
        updateSettings('lists', availableLists.map(l => l.id));
    };

    const clearAllLists = () => {
        updateSettings('lists', []);
    };

    const toggleSelectedTag = (id) => {
        const current = data.settings.tags || [];
        const next = current.includes(id) ? current.filter(item => item !== id) : [...current, id];
        updateSettings('tags', next);
    };

    const selectAllTags = () => {
        updateSettings('tags', availableTags.map(t => t.id));
    };

    const clearAllTags = () => {
        updateSettings('tags', []);
    };

    const selectedListNames = availableLists
        .filter(l => (data.settings.lists || []).includes(l.id))
        .map(l => l.title || l.name);

    const selectedTagNames = availableTags
        .filter(t => (data.settings.tags || []).includes(t.id))
        .map(t => t.title || t.name);

    const handleGenerateEmail = () => {
        if (!aiPrompt || !aiPrompt.trim()) {
            alert('Please enter what this email should be about.');
            return;
        }

        setGeneratingAi(true);
        const layout_mode = aiLayout === 'table-safe' ? 'table-safe' : 'simple-html';
        
        apiFetch({
            path: '/wpbot-crm/v1/ai/generate-email',
            method: 'POST',
            data: {
                prompt: aiPrompt.trim(),
                style: aiTone,
                layout_mode: layout_mode
            }
        }).then(res => {
            setGeneratingAi(false);
            const content = res?.html || res?.content || (typeof res === 'string' ? res : '');
            if (content) {
                setData(prev => ({ ...prev, email_body: content }));
                if (editorMode === 'visual' && visualEditorRef.current) {
                    visualEditorRef.current.innerHTML = content;
                }
                alert('Email content generated successfully!');
            } else {
                alert('No content received from AI. Please check your API key in General Settings.');
            }
        }).catch(err => {
            setGeneratingAi(false);
            console.error('AI generation error:', err);
            const errMsg = err?.message || err?.code || 'Failed to generate email content. Please check your AI API key in General Settings.';
            alert('AI Generation Error: ' + errMsg);
        });
    };

    const handleSave = (action = 'draft') => {
        setSaving(true);
        const payload = { ...data, status: 'draft' };
        payload.settings.all = payload.settings.send_to === 'all';
        
        const sendCampaign = (id) => {
            if (action === 'send') {
                return apiFetch({ path: `/wpbot-crm/v1/campaigns/${id}/send`, method: 'POST' })
                    .then(() => apiFetch({ path: `/wpbot-crm/v1/campaigns/${id}/process`, method: 'POST' }));
            }
            return Promise.resolve();
        };

        if (campaignId) {
            apiFetch({ path: `/wpbot-crm/v1/campaigns/${campaignId}`, method: 'PUT', data: payload })
                .then(() => sendCampaign(campaignId))
                .then(() => {
                    setSaving(false);
                    if (action === 'send') {
                        alert('Campaign is queued and will start sending shortly!');
                    }
                    onBack();
                })
                .catch(err => { 
                    console.error(err); 
                    alert('Error: ' + (err.message || 'Failed to save or send campaign.'));
                    setSaving(false); 
                });
        } else {
            apiFetch({ path: '/wpbot-crm/v1/campaigns', method: 'POST', data: payload })
                .then(res => { 
                    if (res && res.id) {
                        return sendCampaign(res.id);
                    }
                    return Promise.resolve();
                })
                .then(() => {
                    setSaving(false);
                    if (action === 'send') {
                        alert('Campaign is queued and will start sending shortly!');
                    }
                    onBack();
                })
                .catch(err => { 
                    console.error(err); 
                    alert('Error: ' + (err.message || 'Failed to save or send campaign.'));
                    setSaving(false); 
                });
        }
    };

    return (
        <div className="qcwpbot-campaign-wizard">
            <div className="qcwpbot-header">
                <div style={{ display: 'flex', gap: '10px' }}>
                    <a href="#" style={{ color: '#be1e43', textDecoration: 'none', fontSize: '12px', fontWeight: 'bold', alignSelf: 'center', marginRight: '15px' }} onClick={(e) => { e.preventDefault(); onBack(); }}>&larr; Back to Campaigns</a>
                    <h2 style={{ fontSize: '24px', fontWeight: 'bold', margin: 0 }}>{campaignId ? 'Edit Campaign' : 'New Campaign'}</h2>
                </div>
                <Button isSecondary onClick={() => handleSave('draft')}>{saving ? <Spinner /> : 'Save Draft'}</Button>
            </div>
            
            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '30px', marginTop: '20px' }}>
                <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', marginBottom: '40px', position: 'relative' }}>
                    <div style={{ width: '60%', height: '2px', background: '#e2e8f0', position: 'absolute', top: '20px', zIndex: 0 }}></div>
                    {[1, 2, 3, 4].map(s => (
                        <div key={s} style={{ zIndex: 1, background: '#fff', padding: '0 20px', display: 'flex', flexDirection: 'column', alignItems: 'center', cursor: 'pointer' }} onClick={() => setStep(s)}>
                            <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: step === s ? '#be1e43' : '#fff', border: `2px solid ${step === s ? '#be1e43' : '#cbd5e1'}`, color: step === s ? '#fff' : '#64748b', display: 'flex', justifyContent: 'center', alignItems: 'center', fontWeight: 'bold', marginBottom: '10px' }}>
                                {step > s ? '✓' : s}
                            </div>
                            <span style={{ fontSize: '12px', fontWeight: 'bold', color: step === s ? '#be1e43' : '#64748b' }}>
                                {s === 1 ? 'Compose' : s === 2 ? 'Subject & Settings' : s === 3 ? 'Recipients' : 'Review & Send'}
                            </span>
                        </div>
                    ))}
                </div>
                
                <div style={{ textAlign: 'left', minHeight: '300px', maxWidth: '900px', margin: '0 auto' }}>
                    {step === 1 && (
                        <div>
                            {/* AI Email Writer Section */}
                            <div style={{ border: '1px solid #e8eaed', borderRadius: '8px', marginBottom: '20px', background: '#fafafa' }}>
                                <div 
                                    style={{ padding: '15px 20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer', background: '#f5f3ff', borderRadius: '8px' }}
                                    onClick={() => setAiExpanded(!aiExpanded)}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                        <span style={{ fontSize: '16px' }}>✨</span>
                                        <strong style={{ fontSize: '14px', color: '#1e293b' }}>AI Email Writer</strong>
                                        <span style={{ fontSize: '12px', color: '#64748b' }}>Describe your email and let AI write it for you</span>
                                    </div>
                                    <span style={{ transform: aiExpanded ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>▼</span>
                                </div>
                                {aiExpanded && (
                                    <div style={{ padding: '20px', borderTop: '1px solid #e8eaed' }}>
                                        <TextareaControl 
                                            label="WHAT SHOULD THIS EMAIL BE ABOUT?" 
                                            placeholder="e.g. A spring sale announcement with 30% off all products, urgency-driven, include a hero section and CTA button..."
                                            rows={3}
                                            value={aiPrompt}
                                            onChange={setAiPrompt}
                                        />
                                        <div style={{ display: 'flex', gap: '20px', marginTop: '15px' }}>
                                            <div style={{ flex: 1 }}>
                                                <SelectControl 
                                                    label="TONE / STYLE" 
                                                    value={aiTone}
                                                    options={[
                                                        { label: 'Professional', value: 'professional' },
                                                        { label: 'Friendly', value: 'friendly' },
                                                        { label: 'Formal', value: 'formal' },
                                                        { label: 'Humorous', value: 'humorous' },
                                                        { label: 'Minimalist', value: 'minimalist' }
                                                    ]}
                                                    onChange={setAiTone}
                                                />
                                            </div>
                                            <div style={{ flex: 1 }}>
                                                <SelectControl 
                                                    label="LAYOUT MODE" 
                                                    value={aiLayout}
                                                    options={[
                                                        { label: 'Simple HTML', value: 'simple-html' },
                                                        { label: 'Table-Safe (Responsive)', value: 'table-safe' }
                                                    ]}
                                                    onChange={setAiLayout}
                                                />
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'flex-end', paddingBottom: '2px' }}>
                                                <Button 
                                                    className="qcwpbot-primary-btn"
                                                    onClick={handleGenerateEmail}
                                                    isBusy={generatingAi}
                                                    disabled={generatingAi}
                                                >
                                                    {generatingAi ? 'Generating...' : '✨ Generate Email'}
                                                </Button>
                                            </div>
                                        </div>
                                        <p style={{ fontSize: '11px', color: '#64748b', marginTop: '10px' }}>Generating will replace the current email body.</p>
                                    </div>
                                )}
                            </div>

                            <div style={{ border: '1px solid #e8eaed', borderRadius: '8px', background: '#fff' }}>
                                <h3 style={{ padding: '15px 20px', margin: 0, fontSize: '14px', borderBottom: '1px solid #e8eaed', background: '#fafafa' }}>Email Body</h3>
                                
                                <div style={{ padding: '20px' }}>
                                    {/* Import Template Section */}
                                    <div style={{ border: '1px solid #e8eaed', borderRadius: '8px', marginBottom: '20px', background: '#f8fafc' }}>
                                        <div 
                                            style={{ padding: '15px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer' }}
                                            onClick={() => setTemplatesExpanded(!templatesExpanded)}
                                        >
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                <span style={{ color: '#94a3b8' }}>📄</span>
                                                <strong style={{ fontSize: '14px', color: '#1e293b' }}>Import Template</strong>
                                                <span style={{ fontSize: '12px', color: '#64748b' }}>Choose a pre-designed template to start with</span>
                                            </div>
                                            <span style={{ transform: templatesExpanded ? 'rotate(180deg)' : 'none', transition: '0.2s', fontSize: '10px' }}>▼</span>
                                        </div>
                                        {templatesExpanded && (
                                            <div style={{ padding: '15px', borderTop: '1px solid #e8eaed', display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '15px' }}>
                                                {systemTemplates.length === 0 && <div style={{ color: '#64748b', fontSize: '13px' }}>No templates found. Go to Templates menu to create one.</div>}
                                                {systemTemplates.map(tpl => {
                                                    const title = tpl.title || tpl.name || '(No Name)';
                                                    return (
                                                        <div 
                                                            key={tpl.id} 
                                                            style={{ border: '1px solid #e2e8f0', borderRadius: '6px', background: '#fff', overflow: 'hidden', cursor: 'pointer' }}
                                                            onClick={() => updateData('email_body', tpl.content || '')}
                                                        >
                                                            <div style={{ height: '80px', width: '100%', overflow: 'hidden', background: '#fff', position: 'relative' }}>
                                                                <div 
                                                                    style={{ 
                                                                        width: '600px', 
                                                                        height: '800px', 
                                                                        transform: 'scale(0.25)', 
                                                                        transformOrigin: 'top left', 
                                                                        pointerEvents: 'none' 
                                                                    }} 
                                                                    dangerouslySetInnerHTML={{ __html: tpl.content || '<div style="padding: 20px; color: #94a3b8;">Empty Template</div>' }}
                                                                />
                                                            </div>
                                                            <div style={{ padding: '8px 10px', fontSize: '12px', fontWeight: 'bold', borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between' }}>
                                                                {title}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>

                                    <div style={{ border: '1px solid #c3c4c7', background: '#fff', marginBottom: '10px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 8px' }}>
                                            <button onClick={handleAddMedia} type="button" style={{ display: 'flex', alignItems: 'center', gap: '5px', background: '#fff', border: '1px solid #d63638', color: '#d63638', padding: '3px 8px', borderRadius: '3px', cursor: 'pointer', fontSize: '13px', fontWeight: '500' }}>
                                                <span className="dashicons dashicons-admin-media" style={{ fontSize: '16px', width: '16px', height: '16px' }}></span> Add media
                                            </button>
                                            <div style={{ display: 'flex', border: '1px solid #c3c4c7', borderRadius: '3px', overflow: 'hidden' }}>
                                                <div onClick={() => setEditorMode('visual')} style={{ padding: '3px 10px', fontSize: '13px', background: editorMode === 'visual' ? '#f0f0f1' : '#fff', color: '#3c434a', borderRight: '1px solid #c3c4c7', cursor: 'pointer', fontWeight: editorMode === 'visual' ? '600' : 'normal' }}>Visual</div>
                                                <div onClick={() => setEditorMode('code')} style={{ padding: '3px 10px', fontSize: '13px', background: editorMode === 'code' ? '#f0f0f1' : '#fff', color: '#3c434a', cursor: 'pointer', fontWeight: editorMode === 'code' ? '600' : 'normal' }}>Code</div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', padding: '4px 8px', background: '#f0f0f1', borderTop: '1px solid #c3c4c7', borderBottom: '1px solid #c3c4c7', gap: '10px', flexWrap: 'wrap' }}>
                                            <select style={{ padding: '2px 20px 2px 8px', fontSize: '13px', border: '1px solid #8c8f94', borderRadius: '3px', background: '#fff' }} onChange={(e) => { 
                                                const val = e.target.value;
                                                if (val) {
                                                    executeFormat('formatBlock', `<${val}>`, `<${val}>`, `</${val}>`);
                                                } else {
                                                    executeFormat('formatBlock', '<p>', '<p>', '</p>');
                                                }
                                                e.target.value = ""; 
                                            }}>
                                                <option value="">Paragraph</option>
                                                <option value="h1">Heading 1</option>
                                                <option value="h2">Heading 2</option>
                                                <option value="h3">Heading 3</option>
                                            </select>
                                            <div style={{ display: 'flex', gap: '4px', color: '#3c434a' }}>
                                                <button type="button" title="Bold" onClick={() => executeFormat('bold', null, '<b>', '</b>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', fontWeight: 'bold' }}>B</button>
                                                <button type="button" title="Italic" onClick={() => executeFormat('italic', null, '<i>', '</i>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', fontStyle: 'italic' }}>I</button>
                                                <button type="button" title="Underline" onClick={() => executeFormat('underline', null, '<u>', '</u>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', textDecoration: 'underline' }}>U</button>
                                                <button type="button" title="Strikethrough" onClick={() => executeFormat('strikeThrough', null, '<strike>', '</strike>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', textDecoration: 'line-through', fontSize: '11px' }}>ABE</button>
                                            </div>
                                            <div style={{ width: '1px', height: '16px', background: '#c3c4c7' }}></div>
                                            <div style={{ display: 'flex', gap: '4px', color: '#3c434a' }}>
                                                <button type="button" title="Bullet List" onClick={() => executeFormat('insertUnorderedList', null, '<ul>\n<li>', '</li>\n</ul>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-ul"></span></button>
                                                <button type="button" title="Numbered List" onClick={() => executeFormat('insertOrderedList', null, '<ol>\n<li>', '</li>\n</ol>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-ol"></span></button>
                                                <button type="button" title="Quote" onClick={() => executeFormat('formatBlock', '<blockquote>', '<blockquote>', '</blockquote>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-quote"></span></button>
                                            </div>
                                            <div style={{ width: '1px', height: '16px', background: '#c3c4c7' }}></div>
                                            <div style={{ display: 'flex', gap: '4px', color: '#3c434a' }}>
                                                <button type="button" title="Align Left" onClick={() => executeFormat('justifyLeft', null, '<div style="text-align: left;">', '</div>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-alignleft"></span></button>
                                                <button type="button" title="Align Center" onClick={() => executeFormat('justifyCenter', null, '<div style="text-align: center;">', '</div>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-aligncenter"></span></button>
                                                <button type="button" title="Align Right" onClick={() => executeFormat('justifyRight', null, '<div style="text-align: right;">', '</div>')} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-editor-alignright"></span></button>
                                            </div>
                                            <div style={{ width: '1px', height: '16px', background: '#c3c4c7' }}></div>
                                            <div style={{ display: 'flex', gap: '4px', color: '#3c434a' }}>
                                                <button type="button" title="Insert Link" onClick={() => { 
                                                    const url = prompt('Enter URL'); 
                                                    if (url) {
                                                        executeFormat('createLink', url, `<a href="${url}">`, '</a>'); 
                                                    }
                                                }} style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px' }}><span className="dashicons dashicons-admin-links"></span></button>
                                            </div>
                                        </div>
                                        <div style={{ background: '#f8fafc', padding: '20px', minHeight: '300px', display: 'flex', justifyContent: 'center' }}>
                                            {editorMode === 'visual' ? (
                                                <div 
                                                    ref={visualEditorRef}
                                                    contentEditable={true}
                                                    suppressContentEditableWarning={true}
                                                    onInput={(e) => {
                                                        const html = e.currentTarget.innerHTML;
                                                        setData(prev => ({ ...prev, email_body: html }));
                                                    }}
                                                    onBlur={(e) => {
                                                        const html = e.currentTarget.innerHTML;
                                                        setData(prev => ({ ...prev, email_body: html }));
                                                    }}
                                                    style={{ 
                                                        width: '100%', 
                                                        background: '#fff', 
                                                        border: '1px dashed #cbd5e1', 
                                                        padding: '30px', 
                                                        minHeight: '300px', 
                                                        boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                                                        outline: 'none',
                                                        cursor: 'text',
                                                        lineHeight: '1.6',
                                                        color: '#1e293b'
                                                    }}
                                                />
                                            ) : (
                                                <textarea 
                                                    ref={textareaRef}
                                                    style={{ width: '100%', background: '#fff', border: '1px dashed #cbd5e1', padding: '30px', resize: 'vertical', minHeight: '300px', outline: 'none', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
                                                    value={data.email_body}
                                                    onChange={(e) => updateData('email_body', e.target.value)}
                                                    placeholder="Type your email content here..."
                                                />
                                            )}
                                        </div>
                                    </div>
                                    <div style={{ marginTop: '10px', display: 'flex', gap: '10px', alignItems: 'center' }}>
                                        <strong style={{ fontSize: '10px', color: '#64748b', fontWeight: 'bold' }}>INSERT:</strong>
                                        <Button isSmall isSecondary onClick={() => insertMergeTag('{{first_name}}')}>&#123;&#123;first_name&#125;&#125;</Button>
                                        <Button isSmall isSecondary onClick={() => insertMergeTag('{{last_name}}')}>&#123;&#123;last_name&#125;&#125;</Button>
                                        <Button isSmall isSecondary onClick={() => insertMergeTag('{{email}}')}>&#123;&#123;email&#125;&#125;</Button>
                                        <Button isSmall isSecondary onClick={() => insertMergeTag('{{unsubscribe_url}}')}>&#123;&#123;unsubscribe_url&#125;&#125;</Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div>
                            <div style={{ border: '1px solid #e2e8f0', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
                                <h3 style={{ marginTop: 0, marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px' }}>Campaign Details</h3>
                                <TextControl label="CAMPAIGN NAME *" value={data.title} onChange={(val) => updateData('title', val)} placeholder="e.g. Spring Sale Newsletter" />
                                <TextControl label="SUBJECT LINE *" value={data.email_subject} onChange={(val) => updateData('email_subject', val)} placeholder="Enter email subject line" />
                                <TextControl label="PREVIEW TEXT" value={data.email_pre_header} onChange={(val) => updateData('email_pre_header', val)} placeholder="Text shown in inbox preview" />
                            </div>

                            <div style={{ border: '1px solid #e2e8f0', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
                                <h3 style={{ marginTop: 0, marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px' }}>UTM Tracking</h3>
                                <div style={{ display: 'flex', alignItems: 'center', fontSize: '14px' }}>
                                    <input 
                                        type="checkbox" 
                                        checked={!!data.utm_status} 
                                        onChange={(e) => updateData('utm_status', e.target.checked)} 
                                        style={{ marginRight: '10px' }} 
                                    />
                                    <strong>Enable UTM Tracking</strong>
                                </div>
                            </div>

                            <div style={{ border: '1px solid #e2e8f0', padding: '20px', borderRadius: '8px' }}>
                                <h3 style={{ marginTop: 0, marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px' }}>Send Throttle</h3>
                                <TextControl 
                                    type="number" 
                                    label="EMAILS PER MINUTE (0 = NO LIMIT)" 
                                    value={data.settings.send_throttle} 
                                    onChange={(val) => updateSettings('send_throttle', val)} 
                                />
                                <p style={{ fontSize: '12px', color: '#64748b' }}>
                                    Throttle sending to avoid provider rate limits. Set 0 for maximum speed.
                                </p>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div>
                            <div style={{ border: '1px solid #e2e8f0', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
                                <h3 style={{ marginTop: 0, marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px' }}>Include Recipients</h3>
                                <div style={{ marginBottom: '15px' }}>
                                    <strong style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '10px' }}>SEND TO</strong>
                                    <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                                        <Button className={data.settings.send_to === 'all' ? 'qcwpbot-primary-btn' : ''} isSecondary={data.settings.send_to !== 'all'} onClick={() => updateSettings('send_to', 'all')}>All Contacts</Button>
                                        <Button className={data.settings.send_to === 'lists' ? 'qcwpbot-primary-btn' : ''} isSecondary={data.settings.send_to !== 'lists'} onClick={() => updateSettings('send_to', 'lists')}>Lists</Button>
                                        <Button className={data.settings.send_to === 'tags' ? 'qcwpbot-primary-btn' : ''} isSecondary={data.settings.send_to !== 'tags'} onClick={() => updateSettings('send_to', 'tags')}>Tags</Button>
                                    </div>
                                </div>

                                {data.settings.send_to === 'lists' && (
                                    <div style={{ marginTop: '20px', padding: '16px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                            <div>
                                                <strong style={{ fontSize: '13px', color: '#1e293b', display: 'block' }}>Select Target Lists</strong>
                                                <span style={{ fontSize: '12px', color: '#64748b' }}>Choose one or more contact lists to receive this campaign.</span>
                                            </div>
                                            {availableLists.length > 0 && (
                                                <div style={{ display: 'flex', gap: '8px' }}>
                                                    <button
                                                        type="button"
                                                        onClick={selectAllLists}
                                                        style={{ background: 'none', border: 'none', color: '#5b4e96', fontSize: '12px', fontWeight: 600, cursor: 'pointer', textDecoration: 'underline', padding: 0 }}
                                                    >
                                                        Select All
                                                    </button>
                                                    <span style={{ color: '#cbd5e1' }}>|</span>
                                                    <button
                                                        type="button"
                                                        onClick={clearAllLists}
                                                        style={{ background: 'none', border: 'none', color: '#64748b', fontSize: '12px', cursor: 'pointer', textDecoration: 'underline', padding: 0 }}
                                                    >
                                                        Clear
                                                    </button>
                                                </div>
                                            )}
                                        </div>

                                        {availableLists.length === 0 ? (
                                            <p style={{ margin: 0, color: '#94a3b8', fontSize: '13px' }}>No lists available. Please create lists in the Lists section first.</p>
                                        ) : (
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                                {availableLists.map(list => {
                                                    const isSelected = (data.settings.lists || []).includes(list.id);
                                                    return (
                                                        <button
                                                            type="button"
                                                            key={list.id}
                                                            onClick={() => toggleSelectedList(list.id)}
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                gap: '8px',
                                                                padding: '8px 14px',
                                                                borderRadius: '20px',
                                                                fontSize: '13px',
                                                                cursor: 'pointer',
                                                                border: '1px solid',
                                                                borderColor: isSelected ? '#5b4e96' : '#cbd5e1',
                                                                background: isSelected ? '#f3f0fa' : '#fff',
                                                                color: isSelected ? '#5b4e96' : '#334155',
                                                                fontWeight: isSelected ? 600 : 400,
                                                                transition: 'all 0.15s ease'
                                                            }}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={isSelected}
                                                                readOnly
                                                                style={{ margin: 0, pointerEvents: 'none', accentColor: '#5b4e96' }}
                                                            />
                                                            <span>{list.title || list.name}</span>
                                                            {list.subscribers_count !== undefined && (
                                                                <span style={{ fontSize: '11px', background: isSelected ? '#e0d8f5' : '#f1f5f9', padding: '1px 6px', borderRadius: '10px', color: '#64748b' }}>
                                                                    {list.subscribers_count}
                                                                </span>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {data.settings.send_to === 'tags' && (
                                    <div style={{ marginTop: '20px', padding: '16px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                            <div>
                                                <strong style={{ fontSize: '13px', color: '#1e293b', display: 'block' }}>Select Target Tags</strong>
                                                <span style={{ fontSize: '12px', color: '#64748b' }}>Choose one or more tags to target specific contacts.</span>
                                            </div>
                                            {availableTags.length > 0 && (
                                                <div style={{ display: 'flex', gap: '8px' }}>
                                                    <button
                                                        type="button"
                                                        onClick={selectAllTags}
                                                        style={{ background: 'none', border: 'none', color: '#10b981', fontSize: '12px', fontWeight: 600, cursor: 'pointer', textDecoration: 'underline', padding: 0 }}
                                                    >
                                                        Select All
                                                    </button>
                                                    <span style={{ color: '#cbd5e1' }}>|</span>
                                                    <button
                                                        type="button"
                                                        onClick={clearAllTags}
                                                        style={{ background: 'none', border: 'none', color: '#64748b', fontSize: '12px', cursor: 'pointer', textDecoration: 'underline', padding: 0 }}
                                                    >
                                                        Clear
                                                    </button>
                                                </div>
                                            )}
                                        </div>

                                        {availableTags.length === 0 ? (
                                            <p style={{ margin: 0, color: '#94a3b8', fontSize: '13px' }}>No tags available. Please create tags in the Tags section first.</p>
                                        ) : (
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                                {availableTags.map(tag => {
                                                    const isSelected = (data.settings.tags || []).includes(tag.id);
                                                    return (
                                                        <button
                                                            type="button"
                                                            key={tag.id}
                                                            onClick={() => toggleSelectedTag(tag.id)}
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                gap: '8px',
                                                                padding: '8px 14px',
                                                                borderRadius: '20px',
                                                                fontSize: '13px',
                                                                cursor: 'pointer',
                                                                border: '1px solid',
                                                                borderColor: isSelected ? '#10b981' : '#cbd5e1',
                                                                background: isSelected ? '#ecfdf5' : '#fff',
                                                                color: isSelected ? '#065f46' : '#334155',
                                                                fontWeight: isSelected ? 600 : 400,
                                                                transition: 'all 0.15s ease'
                                                            }}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={isSelected}
                                                                readOnly
                                                                style={{ margin: 0, pointerEvents: 'none', accentColor: '#10b981' }}
                                                            />
                                                            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: isSelected ? '#10b981' : '#94a3b8' }}></span>
                                                            <span>{tag.title || tag.name}</span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div style={{ background: '#fdf2f8', padding: '15px', borderRadius: '6px', color: '#be1e43', fontSize: '14px', marginTop: '15px', border: '1px solid #fbcfe8' }}>
                                    👥 Campaign will be sent to: {' '}
                                    <strong>
                                        {data.settings.send_to === 'all'
                                            ? 'All subscribed contacts'
                                            : data.settings.send_to === 'lists'
                                                ? (selectedListNames.length ? `${selectedListNames.length} list(s) (${selectedListNames.join(', ')})` : 'No lists selected yet (please choose at least one list above)')
                                                : (selectedTagNames.length ? `${selectedTagNames.length} tag(s) (${selectedTagNames.join(', ')})` : 'No tags selected yet (please choose at least one tag above)')
                                        }
                                    </strong>
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 4 && (
                        <div>
                            <div style={{ border: '1px solid #e2e8f0', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
                                <h3 style={{ marginTop: 0, marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '10px' }}>Review Campaign</h3>
                                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '14px' }}>
                                    <tbody>
                                        <tr style={{ borderBottom: '1px solid #e2e8f0' }}><td style={{ padding: '15px 0', width: '150px', color: '#64748b', fontWeight: 'bold' }}>Campaign Name</td><td>{data.title}</td></tr>
                                        <tr style={{ borderBottom: '1px solid #e2e8f0' }}><td style={{ padding: '15px 0', color: '#64748b', fontWeight: 'bold' }}>Subject Line</td><td>{data.email_subject}</td></tr>
                                        <tr style={{ borderBottom: '1px solid #e2e8f0' }}><td style={{ padding: '15px 0', color: '#64748b', fontWeight: 'bold' }}>Preview Text</td><td>{data.email_pre_header}</td></tr>
                                        <tr style={{ borderBottom: '1px solid #e2e8f0' }}><td style={{ padding: '15px 0', color: '#64748b', fontWeight: 'bold' }}>Email Body</td><td><span style={{ background: '#dcfce7', color: '#166534', padding: '2px 8px', borderRadius: '10px', fontSize: '12px', fontWeight: 'bold' }}>CONTENT ADDED</span></td></tr>
                                        <tr>
                                            <td style={{ padding: '15px 0', color: '#64748b', fontWeight: 'bold' }}>Recipients</td>
                                            <td>
                                                {data.settings.send_to === 'all' ? (
                                                    <span style={{ background: '#dcfce7', color: '#166534', padding: '2px 8px', borderRadius: '10px', fontSize: '12px', fontWeight: 'bold' }}>ALL CONTACTS</span>
                                                ) : data.settings.send_to === 'lists' ? (
                                                    <span style={{ background: '#f3f0fa', color: '#5b4e96', padding: '2px 8px', borderRadius: '10px', fontSize: '12px', fontWeight: 'bold' }}>
                                                        LISTS ({data.settings.lists?.length || 0} selected: {selectedListNames.join(', ') || 'None'})
                                                    </span>
                                                ) : (
                                                    <span style={{ background: '#ecfdf5', color: '#065f46', padding: '2px 8px', borderRadius: '10px', fontSize: '12px', fontWeight: 'bold' }}>
                                                        TAGS ({data.settings.tags?.length || 0} selected: {selectedTagNames.join(', ') || 'None'})
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
                
                <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e2e8f0', paddingTop: '20px', marginTop: '20px' }}>
                    <Button isSecondary disabled={step === 1} onClick={() => setStep(step - 1)}>&larr; Back</Button>
                    <div style={{ display: 'flex', gap: '10px' }}>
                        <Button isSecondary onClick={() => handleSave('draft')}>{saving ? <Spinner /> : 'Save Draft'}</Button>
                        <Button 
                            className="qcwpbot-primary-btn" 
                            onClick={() => {
                                if (step === 3) {
                                    if (data.settings.send_to === 'lists' && (!data.settings.lists || data.settings.lists.length === 0)) {
                                        alert('Please select at least one list to send this campaign to.');
                                        return;
                                    }
                                    if (data.settings.send_to === 'tags' && (!data.settings.tags || data.settings.tags.length === 0)) {
                                        alert('Please select at least one tag to send this campaign to.');
                                        return;
                                    }
                                }
                                step < 4 ? setStep(step + 1) : handleSave('send');
                            }} 
                            disabled={saving}
                        >
                            {saving ? <Spinner /> : (step < 4 ? 'Continue \u2192' : 'Send Now')}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
};

const Campaigns = () => {
    const [view, setView] = useState('list'); // 'list' | 'wizard'
    const [editId, setEditId] = useState(null);

    if (view === 'wizard') {
        return <CampaignWizard onBack={() => { setView('list'); setEditId(null); }} campaignId={editId} setCampaignId={setEditId} />;
    }

    return (
        <CampaignsList 
            onCreateNew={() => { setEditId(null); setView('wizard'); }} 
            onEdit={(id) => { setEditId(id); setView('wizard'); }} 
        />
    );
};

export default Campaigns;
