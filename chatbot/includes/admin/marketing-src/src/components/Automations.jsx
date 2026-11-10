import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Automations = () => {

    const [view, setView] = useState('list'); // 'list' or 'builder'
    const [automations, setAutomations] = useState([]);
    
    const [automationId, setAutomationId] = useState(null);
    const [saving, setSaving] = useState(false);
    
    // Stats
    const [stats, setStats] = useState({ total_automations: 0, total_enrolled: 0, total_active: 0, total_completed: 0 });
    
    // Builder State
    const [title, setTitle] = useState('');
    const [trigger, setTrigger] = useState('');
    const [status, setStatus] = useState('draft');
    const [steps, setSteps] = useState([]);
    const [activeModal, setActiveModal] = useState(null);

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [loading, setLoading] = useState(false);

    const fetchAutomations = () => {
        setLoading(true);
        apiFetch({ path: '/wpbot-crm/v1/automations' })
            .then(res => {
                setAutomations(res.items || []);
                if (res.overview) {
                    setStats(res.overview);
                }
            })
            .catch(err => console.error(err))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (view === 'list') {
            fetchAutomations();
        }
    }, [view]);

    const handleNewAutomation = () => {
        setAutomationId(null);
        setTitle('');
        setTrigger('');
        setStatus('draft');
        setSteps([]);
        setView('builder');
    };

    const handleEditAutomation = (id) => {
        setLoading(true);
        apiFetch({ path: `/wpbot-crm/v1/automations/${id}` })
            .then(res => {
                if (res) {
                    setAutomationId(res.id);
                    setTitle(res.title || '');
                    setTrigger(res.trigger_name || '');
                    setStatus(res.status || 'draft');
                    
                    const loadedSteps = (res.sequences || []).map(seq => {
                        let stepType = seq.action_name || seq.type || 'send_email';
                        if (stepType === 'add_to_list') stepType = 'add_list';
                        if (stepType === 'remove_from_list') stepType = 'remove_list';
                        
                        const seqSettings = seq.settings || {};
                        let unit = 'Minutes';
                        if (seq.delay_unit) {
                            unit = seq.delay_unit.charAt(0).toUpperCase() + seq.delay_unit.slice(1);
                        }

                        return {
                            id: Date.now() + Math.random(),
                            db_id: seq.id,
                            type: stepType,
                            delay: seq.delay_value !== undefined ? seq.delay_value : (seq.delay || 0),
                            unit: unit,
                            subject: seqSettings.subject || seqSettings.email_subject || '',
                            body: seqSettings.body || seqSettings.email_body || '',
                            settings: seqSettings
                        };
                    });
                    
                    setSteps(loadedSteps);
                    setView('builder');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error loading automation: ' + (err.message || 'Unknown error'));
            })
            .finally(() => setLoading(false));
    };

    const handleDeleteAutomation = (id) => {
        if (!confirm('Are you sure you want to delete this automation? This action cannot be undone.')) {
            return;
        }

        apiFetch({
            path: `/wpbot-crm/v1/automations/${id}`,
            method: 'DELETE'
        }).then(() => {
            setAutomations(prev => prev.filter(a => a.id !== id));
            fetchAutomations();
        }).catch(err => {
            console.error(err);
            alert('Error deleting automation: ' + (err.message || 'Unknown error'));
        });
    };

    const handleSaveAutomation = async () => {
        if (!title) {
            alert('Please enter a title');
            return;
        }
        setSaving(true);
        try {
            let currentId = automationId;
            const data = { title, trigger_name: trigger, status: status === 'published' ? 'published' : 'draft', type: 'funnel' };
            
            // 1. Create or Update Automation
            if (currentId) {
                await apiFetch({ path: `/wpbot-crm/v1/automations/${currentId}`, method: 'PUT', data });
            } else {
                const res = await apiFetch({ path: '/wpbot-crm/v1/automations', method: 'POST', data });
                currentId = res.id;
                setAutomationId(currentId);
            }
            
            // 2. Save Sequences (steps)
            const mappedSequences = steps.map((s, index) => {
                let actionName = s.type;
                if (actionName === 'add_list') actionName = 'add_to_list';
                if (actionName === 'remove_list') actionName = 'remove_from_list';
                
                const stepSettings = { ...(s.settings || {}) };
                if (s.subject) {
                    stepSettings.subject = s.subject;
                    stepSettings.email_subject = s.subject;
                }
                if (s.body) {
                    stepSettings.body = s.body;
                    stepSettings.email_body = s.body;
                }

                return {
                    id: s.db_id || undefined,
                    action_name: actionName,
                    type: 'action',
                    delay_value: parseInt(s.delay) || 0,
                    delay_unit: (s.unit || 'minutes').toLowerCase(),
                    settings: stepSettings
                };
            });
            
            await apiFetch({ 
                path: `/wpbot-crm/v1/automations/${currentId}/steps`, 
                method: 'PUT', 
                data: { sequences: mappedSequences } 
            });
            
            alert('Automation saved successfully!');
            setView('list');
        } catch (error) {
            alert('Error saving automation: ' + (error.message || 'Unknown error'));
        } finally {
            setSaving(false);
        }
    };

    const handleAddStep = (type) => {
        const newStep = {
            id: Date.now(),
            type: type,
            delay: 0,
            unit: 'Minutes'
        };
        setSteps([...steps, newStep]);
    };

    const handleRemoveStep = (id) => {
        setSteps(steps.filter(s => s.id !== id));
    };

    const updateStep = (id, field, value) => {
        setSteps(steps.map(s => s.id === id ? { ...s, [field]: value } : s));
    };

    const getIcon = (type) => {
        switch (type) {
            case 'send_email': return 'dashicons-email';
            case 'wait': return 'dashicons-clock';
            case 'add_tag': return 'dashicons-tag';
            case 'remove_tag': return 'dashicons-tag';
            case 'add_list': return 'dashicons-list-view';
            case 'remove_list': return 'dashicons-list-view';
            case 'update_contact': return 'dashicons-admin-users';
            case 'webhook': return 'dashicons-admin-links';
            case 'end': return 'dashicons-dismiss';
            default: return 'dashicons-admin-generic';
        }
    };

    const renderList = () => (
        <div className="qcwpbot-automations-list">
            <div className="qcwpbot-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <div>
                    <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 'bold' }}>Automations</h2>
                </div>
                <Button onClick={handleNewAutomation} style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '6px 16px', borderRadius: '4px' }}>+ New Automation</Button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '20px', marginBottom: '20px' }}>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <div style={{ background: '#6366f1', color: '#fff', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>⚡</div>
                    <div><div style={{ fontSize: '24px', fontWeight: 'bold' }}>{stats.total_automations || 0}</div><div style={{ fontSize: '12px', color: '#64748b' }}>Total Automations</div></div>
                </div>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <div style={{ background: '#3b82f6', color: '#fff', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>👥</div>
                    <div><div style={{ fontSize: '24px', fontWeight: 'bold' }}>{stats.total_enrolled || 0}</div><div style={{ fontSize: '12px', color: '#64748b' }}>Total Enrolled</div></div>
                </div>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <div style={{ background: '#f59e0b', color: '#fff', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>⏳</div>
                    <div><div style={{ fontSize: '24px', fontWeight: 'bold' }}>{stats.total_active || 0}</div><div style={{ fontSize: '12px', color: '#64748b' }}>Active in Funnel</div></div>
                </div>
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <div style={{ background: '#10b981', color: '#fff', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>🎯</div>
                    <div><div style={{ fontSize: '24px', fontWeight: 'bold' }}>{stats.total_completed || 0}</div><div style={{ fontSize: '12px', color: '#64748b' }}>Completed Funnels</div></div>
                </div>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
                    <div style={{ display: 'flex', gap: '10px' }}>
                        <TextControl
                            placeholder="Search automations..."
                            value={search}
                            onChange={setSearch}
                        />
                        <SelectControl
                            value={statusFilter}
                            onChange={setStatusFilter}
                            options={[
                                { label: 'All Statuses', value: 'all' },
                                { label: 'Published / Active', value: 'published' },
                                { label: 'Draft', value: 'draft' }
                            ]}
                        />
                    </div>
                </div>
                {loading ? (
                    <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
                        Loading automations...
                    </div>
                ) : automations.filter(a => {
                    const matchesSearch = !search || (a.title && a.title.toLowerCase().includes(search.toLowerCase()));
                    const matchesStatus = statusFilter === 'all' || a.status === statusFilter;
                    return matchesSearch && matchesStatus;
                }).length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b' }}>
                        {automations.length === 0 ? 'No automations found. Create your first automation!' : 'No matching automations found.'}
                    </div>
                ) : (
                    <table className="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style={{ width: '32%' }}>Title</th>
                                <th style={{ width: '13%' }}>Status</th>
                                <th style={{ width: '18%' }}>Trigger</th>
                                <th style={{ width: '10%' }}>Enrolled</th>
                                <th style={{ width: '10%' }}>Completed</th>
                                <th style={{ width: '17%', textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {automations.filter(a => {
                                const matchesSearch = !search || (a.title && a.title.toLowerCase().includes(search.toLowerCase()));
                                const matchesStatus = statusFilter === 'all' || a.status === statusFilter;
                                return matchesSearch && matchesStatus;
                            }).map(a => (
                                <tr key={a.id}>
                                    <td>
                                        <a
                                            href="#"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                handleEditAutomation(a.id);
                                            }}
                                            style={{ color: '#1e293b', fontWeight: 'bold', textDecoration: 'none' }}
                                            title="Click to edit automation"
                                        >
                                            {a.title}
                                        </a>
                                    </td>
                                    <td>
                                        <span style={{
                                            display: 'inline-block',
                                            padding: '2px 8px',
                                            borderRadius: '10px',
                                            fontSize: '11px',
                                            fontWeight: 600,
                                            background: a.status === 'published' ? '#dcfce7' : '#f1f5f9',
                                            color: a.status === 'published' ? '#15803d' : '#64748b',
                                            textTransform: 'capitalize'
                                        }}>
                                            {a.status || 'draft'}
                                        </span>
                                    </td>
                                    <td>{a.trigger_name ? a.trigger_name.replace(/_/g, ' ').toUpperCase() : '—'}</td>
                                    <td>{a.enrolled_count || 0}</td>
                                    <td>{a.completed_count || 0}</td>
                                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                                        <a
                                            href="#"
                                            style={{ color: '#5b4e96', textDecoration: 'none', fontWeight: 600, marginRight: '14px' }}
                                            onClick={(e) => {
                                                e.preventDefault();
                                                handleEditAutomation(a.id);
                                            }}
                                        >
                                            Edit
                                        </a>
                                        <a
                                            href="#"
                                            style={{ color: '#be1e43', textDecoration: 'none', fontWeight: 600 }}
                                            onClick={(e) => {
                                                e.preventDefault();
                                                handleDeleteAutomation(a.id);
                                            }}
                                        >
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );

    const renderBuilder = () => (
        <div className="qcwpbot-automation-builder">
            <div style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <a href="#" onClick={(e) => { e.preventDefault(); setView('list'); }} style={{ color: '#be1e43', textDecoration: 'none', fontSize: '13px', display: 'block', marginBottom: '5px' }}>← Back to Automations</a>
                    <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 'bold' }}>{automationId ? 'Edit Automation' : 'New Automation'}</h2>
                </div>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <Button style={{ background: '#5b4e96', color: '#fff', border: 'none' }} onClick={handleSaveAutomation} isBusy={saving}>
                        {saving ? 'Saving...' : (automationId ? 'Update Automation' : 'Save Automation')}
                    </Button>
                </div>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', marginBottom: '20px', display: 'flex', gap: '20px' }}>
                <div style={{ flex: 1 }}>
                    <TextControl label="Title" value={title} onChange={setTitle} />
                </div>
                <div style={{ flex: 1 }}>
                    <SelectControl label="TRIGGER" value={trigger} onChange={setTrigger} options={[
                        { label: '— Select Trigger —', value: '' },
                        { label: 'Contact Created', value: 'subscriber_created' },
                        { label: 'Contact Status Changed', value: 'subscriber_status_change' },
                        { label: 'Tag Added to Contact', value: 'tag_added' },
                        { label: 'Tag Removed from Contact', value: 'tag_removed' },
                        { label: 'Added to List', value: 'list_added' },
                        { label: 'Removed from List', value: 'list_removed' },
                        { label: 'Form Submitted', value: 'form_submitted' },
                        { label: 'Email Link Clicked', value: 'link_clicked' },
                        { label: 'Email Opened', value: 'email_opened' },
                        { label: 'WooCommerce Order Done', value: 'woo_order_completed' },
                        { label: 'WordPress User Registered', value: 'user_registered' }
                    ]} />
                </div>
                <div style={{ flex: 1 }}>
                    <SelectControl label="STATUS" value={status === 'published' ? 'published' : 'draft'} onChange={setStatus} options={[
                        { label: 'Draft', value: 'draft' },
                        { label: 'Published / Active', value: 'published' }
                    ]} />
                </div>
            </div>

            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', marginBottom: '20px', background: '#fff' }}>
                <h3 style={{ padding: '15px 20px', margin: 0, fontSize: '14px', borderBottom: '1px solid #e2e8f0', background: '#fafafa' }}>Automation Flow</h3>
                <div style={{ padding: '20px', background: '#f8fafc', overflowX: 'auto' }}>
                    <div style={{ minHeight: '300px', background: '#f1f5f9', border: '1px dashed #cbd5e1', borderRadius: '8px', position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '40px' }}>
                        
                        {/* Flow Diagram Rendering */}
                        <div 
                            onClick={() => setActiveModal({ type: 'trigger' })}
                            style={{ background: '#dcfce7', border: '1px solid #86efac', padding: '10px 20px', borderRadius: '6px', fontSize: '12px', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '5px', cursor: 'pointer', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}
                        >
                            <span>🚀</span> Trigger: {trigger ? trigger.replace('_', ' ').toUpperCase() : 'Not Set'}
                        </div>
                        <div style={{ width: '1px', height: '20px', background: '#94a3b8' }}></div>
                        
                        {steps.map((step, index) => (
                            <div key={step.id} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                <div 
                                    onClick={() => setActiveModal({ type: 'step', id: step.id })}
                                    style={{ background: '#fff', border: '1px solid #cbd5e1', padding: '10px 20px', borderRadius: '6px', fontSize: '12px', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '5px', minWidth: '120px', justifyContent: 'center', boxShadow: '0 1px 2px rgba(0,0,0,0.05)', cursor: 'pointer' }}
                                >
                                    <span className={`dashicons ${getIcon(step.type)}`} style={{ fontSize: '16px', color: '#64748b' }}></span> 
                                    {step.type.replace('_', ' ').toUpperCase()}
                                </div>
                                <div style={{ width: '1px', height: '20px', background: '#94a3b8' }}></div>
                            </div>
                        ))}
                        
                        <div style={{ background: '#fff', border: '1px dashed #cbd5e1', padding: '10px 20px', borderRadius: '6px', fontSize: '12px', color: '#94a3b8' }}>
                            + Add Step Below
                        </div>

                    </div>
                </div>
            </div>

            <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', marginBottom: '20px', background: '#fff' }}>
                <h3 style={{ padding: '15px 20px', margin: 0, fontSize: '14px', borderBottom: '1px solid #e2e8f0', background: '#fafafa' }}>Add Step</h3>
                <div style={{ padding: '20px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                    <Button isSecondary onClick={() => handleAddStep('send_email')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-email" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Send Email</Button>
                    <Button isSecondary onClick={() => handleAddStep('wait')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-clock" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Wait / Delay</Button>
                    <Button isSecondary onClick={() => handleAddStep('add_tag')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-tag" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Add Tag</Button>
                    <Button isSecondary onClick={() => handleAddStep('remove_tag')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-tag" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Remove Tag</Button>
                    <Button isSecondary onClick={() => handleAddStep('add_list')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-list-view" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Add to List</Button>
                    <Button isSecondary onClick={() => handleAddStep('remove_list')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-list-view" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Remove from List</Button>
                    <Button isSecondary onClick={() => handleAddStep('update_contact')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-admin-users" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Update Contact</Button>
                    <Button isSecondary onClick={() => handleAddStep('webhook')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-admin-links" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> Send Webhook</Button>
                    <Button isSecondary onClick={() => handleAddStep('end')} style={{ fontSize: '12px', padding: '4px 10px' }}><span className="dashicons dashicons-dismiss" style={{fontSize:'14px', width:'14px', height:'14px', marginRight:'5px'}}></span> End Automation</Button>
                </div>
            </div>

            {steps.length > 0 && (
                <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', background: '#fff' }}>
                    <h3 style={{ padding: '15px 20px', margin: 0, fontSize: '14px', borderBottom: '1px solid #e2e8f0', background: '#fafafa' }}>Steps Configuration</h3>
                    <div style={{ padding: '20px' }}>
                        {steps.map((step, index) => (
                            <div key={step.id} style={{ display: 'flex', alignItems: 'center', padding: '15px', borderBottom: index < steps.length - 1 ? '1px solid #e2e8f0' : 'none', gap: '15px' }}>
                                <div style={{ background: '#be1e43', color: '#fff', width: '24px', height: '24px', borderRadius: '50%', display: 'flex', justifyContent: 'center', alignItems: 'center', fontSize: '12px', fontWeight: 'bold' }}>
                                    {index + 1}
                                </div>
                                <div style={{ flex: 1, fontWeight: 'bold', fontSize: '14px', display: 'flex', flexDirection: 'column' }}>
                                    {step.type.replace('_', ' ').toUpperCase()}
                                    <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'normal' }}>{step.type}</div>
                                </div>
                                <div style={{ width: '80px' }}>
                                    <TextControl type="number" value={step.delay} onChange={(val) => updateStep(step.id, 'delay', val)} style={{ margin: 0 }} />
                                </div>
                                <div style={{ width: '120px' }}>
                                    <SelectControl value={step.unit} options={[{label:'Minutes', value:'Minutes'}, {label:'Hours', value:'Hours'}, {label:'Days', value:'Days'}]} onChange={(val) => updateStep(step.id, 'unit', val)} style={{ margin: 0 }} />
                                </div>
                                <Button isLink style={{ color: '#ef4444', textDecoration: 'none' }} onClick={() => handleRemoveStep(step.id)}>Remove</Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

        </div>
    );

    const renderModal = () => {
        if (!activeModal) return null;

        const isTrigger = activeModal.type === 'trigger';
        const stepIndex = isTrigger ? -1 : steps.findIndex(s => s.id === activeModal.id);
        const stepData = isTrigger ? null : steps[stepIndex];

        return (
            <div style={{ position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh', background: 'rgba(0,0,0,0.5)', zIndex: 99999, display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                <div style={{ background: '#fff', borderRadius: '12px', width: '500px', padding: '0', overflow: 'hidden', boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1)' }}>
                    <div style={{ padding: '20px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fafafa' }}>
                        <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 'bold' }}>{isTrigger ? 'Select Trigger' : `Configure: ${stepData.type.replace('_', ' ').toUpperCase()}`}</h3>
                        <Button isSmall isLink style={{ color: '#64748b', textDecoration: 'none' }} onClick={() => setActiveModal(null)}><span className="dashicons dashicons-no-alt"></span></Button>
                    </div>
                    <div style={{ padding: '20px' }}>
                        {isTrigger ? (
                            <SelectControl 
                                label="Trigger Event" 
                                value={trigger} 
                                onChange={setTrigger} 
                                options={[
                                    { label: '— Select Trigger —', value: '' },
                                    { label: 'Contact Created', value: 'subscriber_created' },
                                    { label: 'Contact Status Changed', value: 'subscriber_status_change' },
                                    { label: 'Tag Added to Contact', value: 'tag_added' },
                                    { label: 'Tag Removed from Contact', value: 'tag_removed' },
                                    { label: 'Added to List', value: 'list_added' },
                                    { label: 'Removed from List', value: 'list_removed' },
                                    { label: 'Form Submitted', value: 'form_submitted' },
                                    { label: 'Email Link Clicked', value: 'link_clicked' },
                                    { label: 'Email Opened', value: 'email_opened' },
                                    { label: 'WooCommerce Order Done', value: 'woo_order_completed' },
                                    { label: 'WordPress User Registered', value: 'user_registered' }
                                ]} 
                            />
                        ) : (
                            <div>
                                <div style={{ marginBottom: '15px' }}>
                                    <TextControl label="Delay Amount" type="number" value={stepData.delay} onChange={(val) => updateStep(stepData.id, 'delay', val)} />
                                </div>
                                <div style={{ marginBottom: '15px' }}>
                                    <SelectControl label="Delay Unit" value={stepData.unit} options={[{label:'Minutes', value:'Minutes'}, {label:'Hours', value:'Hours'}, {label:'Days', value:'Days'}]} onChange={(val) => updateStep(stepData.id, 'unit', val)} />
                                </div>
                                {stepData.type === 'send_email' && (
                                    <>
                                        <div style={{ marginBottom: '15px' }}>
                                            <TextControl 
                                                label="Email Subject" 
                                                value={stepData.subject || ''} 
                                                onChange={(val) => updateStep(stepData.id, 'subject', val)} 
                                                placeholder="e.g. Welcome {{first_name}}!"
                                            />
                                        </div>
                                        <div style={{ marginBottom: '15px' }}>
                                            <TextareaControl 
                                                label="Email Body / Message" 
                                                rows={5} 
                                                value={stepData.body || ''} 
                                                onChange={(val) => updateStep(stepData.id, 'body', val)} 
                                                placeholder="Write your email content here. You can use {{first_name}}, {{email}}, etc."
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                    <div style={{ padding: '15px 20px', borderTop: '1px solid #e2e8f0', background: '#f8fafc', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                        <Button isSecondary onClick={() => setActiveModal(null)}>Cancel</Button>
                        <Button style={{ background: '#be1e43', color: '#fff', border: 'none' }} onClick={() => setActiveModal(null)}>Done</Button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <>
            {view === 'list' ? renderList() : renderBuilder()}
            {renderModal()}
        </>
    );
};

export default Automations;
