import { useState, useRef, useEffect } from '@wordpress/element';
import { Button, Modal, TextControl, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Subscribers = ({ setActiveTab }) => {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [selectedImportSource, setSelectedImportSource] = useState('csv');
    const [subscriberStatus, setSubscriberStatus] = useState('subscribed');
    const [subscribers, setSubscribers] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    const fetchSubscribers = async () => {
        setIsLoading(true);
        try {
            const data = await apiFetch({ path: '/wpbot-crm/v1/contacts?per_page=100' });
            if (data && data.items) {
                setSubscribers(data.items);
            }
        } catch (error) {
            console.error('Failed to fetch subscribers:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Lists and Tags states
    const [availableLists, setAvailableLists] = useState([]);
    const [availableTags, setAvailableTags] = useState([]);
    const [selectedListIds, setSelectedListIds] = useState([]);
    const [selectedTagIds, setSelectedTagIds] = useState([]);
    const [importListIds, setImportListIds] = useState([]);
    const [importTagIds, setImportTagIds] = useState([]);

    const fetchListsAndTags = async () => {
        try {
            const [listsRes, tagsRes] = await Promise.all([
                apiFetch({ path: '/wpbot-crm/v1/lists' }),
                apiFetch({ path: '/wpbot-crm/v1/tags' })
            ]);
            setAvailableLists(listsRes.items || listsRes || []);
            setAvailableTags(tagsRes.items || tagsRes || []);
        } catch (err) {
            console.error('Failed to fetch lists or tags:', err);
        }
    };

    useEffect(() => {
        fetchSubscribers();
        fetchListsAndTags();
    }, []);

    // Add Contact States
    const [addEmail, setAddEmail] = useState('');
    const [addFirstName, setAddFirstName] = useState('');
    const [addLastName, setAddLastName] = useState('');
    const [addPhone, setAddPhone] = useState('');
    const [addStatus, setAddStatus] = useState('subscribed');
    const [isAddingContact, setIsAddingContact] = useState(false);

    // Edit Contact States
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editingSubscriber, setEditingSubscriber] = useState(null);
    const [isEditingContact, setIsEditingContact] = useState(false);

    // Import States
    const [csvDelimiter, setCsvDelimiter] = useState('comma');
    const [csvFile, setCsvFile] = useState(null);
    const [updateSubscribers, setUpdateSubscribers] = useState('no');
    const [skipDisposable, setSkipDisposable] = useState(false);
    const [skipTest, setSkipTest] = useState(true);
    const [skipRole, setSkipRole] = useState(false);
    const [isImporting, setIsImporting] = useState(false);

    const fileInputRef = useRef(null);

    const handleFileUpload = (e) => {
        if (e.target.files && e.target.files.length > 0) {
            setCsvFile(e.target.files[0]);
        }
    };

    const handleImport = async () => {
        setIsImporting(true);
        try {
            if (selectedImportSource === 'csv') {
                if (!csvFile) {
                    alert('Please select a CSV file to upload.');
                    setIsImporting(false);
                    return;
                }
                const formData = new FormData();
                formData.append('file', csvFile);
                formData.append('source', 'csv');
                
                await apiFetch({
                    path: '/wpbot-crm/v1/contacts/import',
                    method: 'POST',
                    body: formData
                });
                alert('CSV file uploaded! Map Columns step would go here.');
            } else {
                const sourceMap = { wp: 'wp_users', woo: 'woocommerce' };
                await apiFetch({
                    path: '/wpbot-crm/v1/contacts/import',
                    method: 'POST',
                    data: {
                        source: sourceMap[selectedImportSource],
                        status: subscriberStatus,
                        update_existing: updateSubscribers === 'yes',
                        skip_disposable: skipDisposable,
                        skip_test: skipTest,
                        skip_role: skipRole,
                        list_ids: importListIds,
                        tag_ids: importTagIds
                    }
                });
                alert(`${selectedImportSource === 'wp' ? 'WordPress Users' : 'WooCommerce Customers'} successfully imported!`);
                setIsImportModalOpen(false);
                fetchSubscribers();
            }
        } catch (err) {
            console.error('Import failed', err);
            alert('Import failed. Check console for details.');
        } finally {
            setIsImporting(false);
        }
    };

    const handleAddContact = async () => {
        if (!addEmail) {
            alert('Email address is required.');
            return;
        }
        setIsAddingContact(true);
        try {
            await apiFetch({
                path: '/wpbot-crm/v1/contacts',
                method: 'POST',
                data: {
                    email: addEmail,
                    first_name: addFirstName,
                    last_name: addLastName,
                    phone: addPhone,
                    status: addStatus,
                    list_ids: selectedListIds,
                    tag_ids: selectedTagIds
                }
            });
            alert('Contact added successfully!');
            setIsAddModalOpen(false);
            setAddEmail('');
            setAddFirstName('');
            setAddLastName('');
            setAddPhone('');
            setAddStatus('subscribed');
            setSelectedListIds([]);
            setSelectedTagIds([]);
            fetchSubscribers();
        } catch (err) {
            console.error('Failed to add contact', err);
            alert('Failed to add contact: ' + (err.message || 'Unknown error'));
        } finally {
            setIsAddingContact(false);
        }
    };

    const handleEditClick = (subscriber) => {
        setEditingSubscriber(subscriber);
        setAddEmail(subscriber.email || '');
        setAddFirstName(subscriber.first_name || '');
        setAddLastName(subscriber.last_name || '');
        setAddPhone(subscriber.phone || '');
        setAddStatus(subscriber.status || 'subscribed');

        const curListIds = (subscriber.lists || []).map(l => (typeof l === 'object' ? l.id : l));
        const curTagIds = (subscriber.tags || []).map(t => (typeof t === 'object' ? t.id : t));
        setSelectedListIds(curListIds);
        setSelectedTagIds(curTagIds);
        setIsEditModalOpen(true);
    };

    const handleUpdateContact = async () => {
        setIsEditingContact(true);
        try {
            await apiFetch({
                path: `/wpbot-crm/v1/contacts/${editingSubscriber.id}`,
                method: 'PUT',
                data: {
                    email: addEmail,
                    first_name: addFirstName,
                    last_name: addLastName,
                    phone: addPhone,
                    status: addStatus,
                    list_ids: selectedListIds,
                    tag_ids: selectedTagIds
                }
            });
            alert('Contact updated successfully!');
            setIsEditModalOpen(false);
            setAddEmail(''); setAddFirstName(''); setAddLastName(''); setAddPhone('');
            setSelectedListIds([]);
            setSelectedTagIds([]);
            fetchSubscribers();
        } catch (error) {
            alert('Error updating contact: ' + (error.message || 'Unknown error'));
        } finally {
            setIsEditingContact(false);
        }
    };

    const formatDate = (dateVal) => {
        if (!dateVal) return '-';
        if (typeof dateVal === 'number' || (/^\d+$/.test(String(dateVal)) && String(dateVal).length <= 11)) {
            const num = Number(dateVal);
            const d = new Date(num > 1e11 ? num : num * 1000);
            return isNaN(d.getTime()) ? '-' : d.toLocaleDateString();
        }
        const normalized = typeof dateVal === 'string' ? dateVal.replace(' ', 'T') : dateVal;
        const d = new Date(normalized);
        return isNaN(d.getTime()) ? '-' : d.toLocaleDateString();
    };

    const renderListSelector = () => (
        <div className="qcwpbot-field-group">
            <label>LISTS</label>
            <div className="qcwpbot-box">
                <span className="box-title">ASSIGN TO LISTS</span>
                {availableLists.length === 0 ? (
                    <p style={{ color: '#64748b', fontSize: '13px', margin: 0 }}>No lists available. Create lists first.</p>
                ) : (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', paddingTop: '6px' }}>
                        {availableLists.map(list => {
                            const isChecked = selectedListIds.includes(list.id);
                            return (
                                <button
                                    type="button"
                                    key={list.id}
                                    onClick={() => {
                                        setSelectedListIds(prev =>
                                            prev.includes(list.id) ? prev.filter(id => id !== list.id) : [...prev, list.id]
                                        );
                                    }}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: '6px',
                                        padding: '6px 12px',
                                        borderRadius: '20px',
                                        fontSize: '13px',
                                        cursor: 'pointer',
                                        border: '1px solid',
                                        borderColor: isChecked ? '#5b4e96' : '#cbd5e1',
                                        background: isChecked ? '#f3f0fa' : '#fff',
                                        color: isChecked ? '#5b4e96' : '#475569',
                                        fontWeight: isChecked ? 600 : 400,
                                        transition: 'all 0.15s ease'
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        readOnly
                                        style={{ margin: 0, pointerEvents: 'none', accentColor: '#5b4e96' }}
                                    />
                                    <span>{list.title || list.name}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );

    const renderTagSelector = () => (
        <div className="qcwpbot-field-group">
            <label>TAGS</label>
            <div className="qcwpbot-box">
                <span className="box-title">ASSIGN TAGS</span>
                {availableTags.length === 0 ? (
                    <p style={{ color: '#64748b', fontSize: '13px', margin: 0 }}>No tags available. Create tags first.</p>
                ) : (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', paddingTop: '6px' }}>
                        {availableTags.map(tag => {
                            const isChecked = selectedTagIds.includes(tag.id);
                            return (
                                <button
                                    type="button"
                                    key={tag.id}
                                    onClick={() => {
                                        setSelectedTagIds(prev =>
                                            prev.includes(tag.id) ? prev.filter(id => id !== tag.id) : [...prev, tag.id]
                                        );
                                    }}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: '6px',
                                        padding: '6px 12px',
                                        borderRadius: '20px',
                                        fontSize: '13px',
                                        cursor: 'pointer',
                                        border: '1px solid',
                                        borderColor: isChecked ? '#10b981' : '#cbd5e1',
                                        background: isChecked ? '#ecfdf5' : '#fff',
                                        color: isChecked ? '#065f46' : '#475569',
                                        fontWeight: isChecked ? 600 : 400,
                                        transition: 'all 0.15s ease'
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        readOnly
                                        style={{ margin: 0, pointerEvents: 'none', accentColor: '#10b981' }}
                                    />
                                    <span style={{ width: '7px', height: '7px', borderRadius: '50%', background: isChecked ? '#10b981' : '#94a3b8' }}></span>
                                    <span>{tag.title || tag.name}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <div className="qcwpbot-contacts">
            <div className="qcwpbot-header">
                <h2>Contacts</h2>
                <div className="header-actions">
                    <Button isSecondary className="btn-pro" onClick={() => setActiveTab('lead-finder')}>Find Leads <span className="pro-badge">PRO</span></Button>
                    <Button isSecondary onClick={() => setActiveTab('import-export')}>Export Contacts</Button>
                    <Button isSecondary onClick={() => setIsImportModalOpen(true)}>Import Contacts</Button>
                    <Button 
                        className="qcwpbot-primary-btn" 
                        onClick={() => {
                            setAddEmail('');
                            setAddFirstName('');
                            setAddLastName('');
                            setAddPhone('');
                            setAddStatus('subscribed');
                            setSelectedListIds([]);
                            setSelectedTagIds([]);
                            setIsAddModalOpen(true);
                        }}
                    >
                        + Add Contact
                    </Button>
                </div>
            </div>

            <div className="qcwpbot-table-container">
                <div className="table-filters">
                    <div className="search-box">
                        <span className="dashicons dashicons-search"></span>
                        <input type="text" placeholder="Search contacts..." />
                    </div>
                    <select>
                        <option>All Contacts</option>
                    </select>
                    <select>
                        <option>All Statuses</option>
                    </select>
                </div>

                <table className="qcwpbot-table">
                    <thead>
                        <tr>
                            <th className="check-col"><input type="checkbox" /></th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>STATUS</th>
                            <th>TAGS</th>
                            <th>LISTS</th>
                            <th>CREATED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            <tr>
                                <td colSpan="8" className="empty-row">Loading contacts...</td>
                            </tr>
                        ) : subscribers.length > 0 ? (
                            subscribers.map((subscriber) => (
                                <tr key={subscriber.id}>
                                    <td className="check-col"><input type="checkbox" value={subscriber.id} /></td>
                                    <td>
                                        <div className="contact-name">
                                            <strong>{subscriber.first_name} {subscriber.last_name}</strong>
                                            
                                        </div>
                                    </td>
                                    <td>
                                        <div className="contact-name">
                                            <span>{subscriber.email}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span className={`status-badge status-${subscriber.status}`}>{subscriber.status}</span>
                                    </td>
                                    <td>{subscriber.tags?.map(t => t.title || t.name).join(', ') || '-'}</td>
                                    <td>{subscriber.lists?.map(l => l.title || l.name).join(', ') || '-'}</td>
                                    <td>{formatDate(subscriber.created_at)}</td>
                                    <td>
                                        <Button isSmall isSecondary onClick={() => handleEditClick(subscriber)}>Edit</Button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="8" className="empty-row">No contacts found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {isAddModalOpen && (
                <Modal
                    title="Add Contact"
                    onRequestClose={() => setIsAddModalOpen(false)}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content">
                        <TextControl
                            label="EMAIL ADDRESS *"
                            placeholder="email@example.com"
                            value={addEmail}
                            onChange={setAddEmail}
                        />
                        <div className="form-row">
                            <TextControl
                                label="FIRST NAME"
                                placeholder="Enter first name"
                                value={addFirstName}
                                onChange={setAddFirstName}
                            />
                            <TextControl
                                label="LAST NAME"
                                placeholder="Enter last name"
                                value={addLastName}
                                onChange={setAddLastName}
                            />
                        </div>
                        <div className="form-row">
                            <TextControl
                                label="PHONE NUMBER"
                                placeholder="+(000) 0000 0000"
                                value={addPhone}
                                onChange={setAddPhone}
                            />
                            <SelectControl
                                label="STATUS"
                                value={addStatus}
                                onChange={setAddStatus}
                                options={[
                                    { label: 'Subscribed', value: 'subscribed' },
                                    { label: 'Unsubscribed', value: 'unsubscribed' },
                                    { label: 'Pending', value: 'pending' },
                                    { label: 'Bounced', value: 'bounced' },
                                    { label: 'Complained', value: 'complained' },
                                    { label: 'Customer', value: 'customer' }
                                ]}
                            />
                        </div>
                        
                        {renderListSelector()}
                        {renderTagSelector()}

                        <div className="modal-actions">
                            <Button isSecondary onClick={() => setIsAddModalOpen(false)}>Cancel</Button>
                            <Button 
                                className="qcwpbot-primary-btn" 
                                style={{ opacity: addEmail ? 1 : 0.6 }} 
                                isBusy={isAddingContact} 
                                onClick={handleAddContact}
                                disabled={!addEmail || isAddingContact}
                            >
                                Add Contact
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {isImportModalOpen && (
                <Modal
                    title="Import Contacts"
                    onRequestClose={() => setIsImportModalOpen(false)}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content" style={{ padding: '20px' }}>
                        <label className="import-label" style={{ fontWeight: 'bold', display: 'block', marginBottom: '15px', fontSize: '13px', color: '#64748b' }}>SELECT IMPORT SOURCE</label>
                        
                        <div 
                            className="import-option" 
                            style={{ border: selectedImportSource === 'csv' ? '2px solid #5b4e96' : '1px solid #e2e8f0', borderRadius: '8px', padding: '15px', marginBottom: '10px', display: 'flex', alignItems: 'center', cursor: 'pointer', background: selectedImportSource === 'csv' ? '#f3f0fa' : '#fff' }}
                            onClick={() => setSelectedImportSource('csv')}
                        >
                            <span className="dashicons dashicons-media-spreadsheet" style={{ fontSize: '24px', marginRight: '15px', color: selectedImportSource === 'csv' ? '#5b4e96' : '#64748b' }}></span>
                            <div className="import-text">
                                <strong style={{ display: 'block', color: selectedImportSource === 'csv' ? '#5b4e96' : '#0f172a' }}>CSV File</strong>
                                <span style={{ color: '#64748b', fontSize: '13px' }}>Upload a CSV file with contacts</span>
                            </div>
                        </div>
                        
                        <div 
                            className="import-option"
                            style={{ border: selectedImportSource === 'wp' ? '2px solid #5b4e96' : '1px solid #e2e8f0', borderRadius: '8px', padding: '15px', marginBottom: '10px', display: 'flex', alignItems: 'center', cursor: 'pointer', background: selectedImportSource === 'wp' ? '#f3f0fa' : '#fff' }}
                            onClick={() => setSelectedImportSource('wp')}
                        >
                            <span className="dashicons dashicons-admin-users" style={{ fontSize: '24px', marginRight: '15px', color: selectedImportSource === 'wp' ? '#5b4e96' : '#64748b' }}></span>
                            <div className="import-text">
                                <strong style={{ display: 'block', color: selectedImportSource === 'wp' ? '#5b4e96' : '#0f172a' }}>WordPress Users</strong>
                                <span style={{ color: '#64748b', fontSize: '13px' }}>Import all WordPress users</span>
                            </div>
                        </div>

                        <div 
                            className="import-option"
                            style={{ border: selectedImportSource === 'woo' ? '2px solid #5b4e96' : '1px solid #e2e8f0', borderRadius: '8px', padding: '15px', marginBottom: '10px', display: 'flex', alignItems: 'center', cursor: 'pointer', background: selectedImportSource === 'woo' ? '#f3f0fa' : '#fff' }}
                            onClick={() => setSelectedImportSource('woo')}
                        >
                            <span className="dashicons dashicons-cart" style={{ fontSize: '24px', marginRight: '15px', color: selectedImportSource === 'woo' ? '#5b4e96' : '#64748b' }}></span>
                            <div className="import-text">
                                <strong style={{ display: 'block', color: selectedImportSource === 'woo' ? '#5b4e96' : '#0f172a' }}>WooCommerce Customers</strong>
                                <span style={{ color: '#64748b', fontSize: '13px' }}>Import all WooCommerce customers</span>
                            </div>
                        </div>

                        <div 
                            className="import-option"
                            style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '15px', marginBottom: '20px', display: 'flex', alignItems: 'center', cursor: 'pointer', background: '#fff' }}
                            onClick={() => {
                                setIsImportModalOpen(false);
                                setActiveTab('lead-finder');
                            }}
                        >
                            <span className="dashicons dashicons-search" style={{ fontSize: '24px', marginRight: '15px', color: '#64748b' }}></span>
                            <div className="import-text">
                                <strong style={{ display: 'block', color: '#0f172a' }}>B2B Lead Finder <span className="pro-badge" style={{ background: '#f97316', color: '#fff', padding: '2px 6px', borderRadius: '4px', fontSize: '10px' }}>PRO</span></strong>
                                <span style={{ color: '#64748b', fontSize: '13px' }}>Discover verified B2B prospects with AI</span>
                            </div>
                        </div>

                        {selectedImportSource === 'csv' && (
                            <div style={{ marginTop: '20px', borderTop: '1px solid #e2e8f0', paddingTop: '20px' }}>
                                <SelectControl
                                    label="SELECT YOUR CSV DELIMITER"
                                    value={csvDelimiter}
                                    options={[
                                        { label: 'Comma Separated (,)', value: 'comma' },
                                        { label: 'Semicolon Separated (;)', value: 'semicolon' },
                                        { label: 'Tab Separated', value: 'tab' }
                                    ]}
                                    onChange={setCsvDelimiter}
                                    style={{ marginBottom: '20px' }}
                                />
                                
                                <label style={{ display: 'block', marginBottom: '8px', fontWeight: 'bold', fontSize: '13px' }}>Upload CSV</label>
                                <div 
                                    style={{ border: '2px dashed #5b4e96', borderRadius: '8px', padding: '40px 20px', textAlign: 'center', background: '#f3f0fa', cursor: 'pointer' }}
                                    onClick={() => fileInputRef.current && fileInputRef.current.click()}
                                >
                                    <input 
                                        type="file" 
                                        ref={fileInputRef} 
                                        style={{ display: 'none' }} 
                                        accept=".csv"
                                        onChange={handleFileUpload} 
                                    />
                                    <span className="dashicons dashicons-upload" style={{ fontSize: '32px', color: '#94a3b8', width: '32px', height: '32px', marginBottom: '10px' }}></span>
                                    <p style={{ margin: 0, color: '#64748b' }}>
                                        {csvFile ? `Selected: ${csvFile.name}` : <><span style={{ color: '#5b4e96', textDecoration: 'underline', fontWeight: 'bold' }}>click to upload</span> or drop file here</>}
                                    </p>
                                </div>
                                <div style={{ marginTop: '10px' }}>
                                    <a href="#" style={{ color: '#5b4e96', fontSize: '13px', textDecoration: 'none' }}>Download sample file</a>
                                    <p style={{ margin: '5px 0 0 0', color: '#64748b', fontSize: '12px' }}>Please make sure your CSV file has unique headers. Otherwise, it may fail to import.</p>
                                </div>
                            </div>
                        )}

                        {(selectedImportSource === 'wp' || selectedImportSource === 'woo') && (
                            <div style={{ marginTop: '20px', borderTop: '1px solid #e2e8f0', paddingTop: '20px' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '20px', marginBottom: '20px' }}>
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: 'bold', fontSize: '13px', color: '#64748b' }}>LISTS</label>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                                            {availableLists.length === 0 ? (
                                                <span style={{ fontSize: '12px', color: '#94a3b8' }}>No lists available</span>
                                            ) : (
                                                availableLists.map(list => {
                                                    const isChecked = importListIds.includes(list.id);
                                                    return (
                                                        <button
                                                            type="button"
                                                            key={list.id}
                                                            onClick={() => setImportListIds(prev => prev.includes(list.id) ? prev.filter(id => id !== list.id) : [...prev, list.id])}
                                                            style={{
                                                                border: '1px solid',
                                                                borderColor: isChecked ? '#5b4e96' : '#cbd5e1',
                                                                background: isChecked ? '#f3f0fa' : '#fff',
                                                                color: isChecked ? '#5b4e96' : '#334155',
                                                                fontWeight: isChecked ? 600 : 400,
                                                                borderRadius: '20px',
                                                                padding: '4px 12px',
                                                                fontSize: '12px',
                                                                cursor: 'pointer'
                                                            }}
                                                        >
                                                            {list.title || list.name}
                                                        </button>
                                                    );
                                                })
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: 'bold', fontSize: '13px', color: '#64748b' }}>TAGS</label>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                                            {availableTags.length === 0 ? (
                                                <span style={{ fontSize: '12px', color: '#94a3b8' }}>No tags available</span>
                                            ) : (
                                                availableTags.map(tag => {
                                                    const isChecked = importTagIds.includes(tag.id);
                                                    return (
                                                        <button
                                                            type="button"
                                                            key={tag.id}
                                                            onClick={() => setImportTagIds(prev => prev.includes(tag.id) ? prev.filter(id => id !== tag.id) : [...prev, tag.id])}
                                                            style={{
                                                                border: '1px solid',
                                                                borderColor: isChecked ? '#10b981' : '#cbd5e1',
                                                                background: isChecked ? '#ecfdf5' : '#fff',
                                                                color: isChecked ? '#065f46' : '#334155',
                                                                fontWeight: isChecked ? 600 : 400,
                                                                borderRadius: '20px',
                                                                padding: '4px 12px',
                                                                fontSize: '12px',
                                                                cursor: 'pointer'
                                                            }}
                                                        >
                                                            {tag.title || tag.name}
                                                        </button>
                                                    );
                                                })
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: 'bold', fontSize: '13px', color: '#64748b' }}>UPDATE SUBSCRIBERS</label>
                                        <div style={{ display: 'flex', gap: '15px', alignItems: 'center' }}>
                                            <label style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: '14px', cursor: 'pointer' }}>
                                                <input type="radio" name="update_sub" checked={updateSubscribers === 'yes'} onChange={() => setUpdateSubscribers('yes')} /> Yes
                                            </label>
                                            <label style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: '14px', cursor: 'pointer' }}>
                                                <input type="radio" name="update_sub" checked={updateSubscribers === 'no'} onChange={() => setUpdateSubscribers('no')} /> No
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div style={{ marginBottom: '20px', width: '50%' }}>
                                    <SelectControl
                                        label="NEW SUBSCRIBER STATUS"
                                        value={subscriberStatus}
                                        options={[
                                            { label: 'Subscribed', value: 'subscribed' },
                                            { label: 'Unsubscribed', value: 'unsubscribed' },
                                            { label: 'Pending', value: 'pending' },
                                            { label: 'Bounced', value: 'bounced' },
                                            { label: 'Complained', value: 'complained' },
                                            { label: 'Customer', value: 'customer' }
                                        ]}
                                        onChange={setSubscriberStatus}
                                    />
                                </div>

                                <div style={{ background: '#f8fafc', padding: '15px', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', marginBottom: '10px' }}>
                                        <input type="checkbox" id="skip_disposable" checked={skipDisposable} onChange={(e) => setSkipDisposable(e.target.checked)} style={{ marginRight: '10px' }} />
                                        <label htmlFor="skip_disposable" style={{ fontSize: '13px' }}>Skip disposable or temporary email</label>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', marginBottom: '10px' }}>
                                        <input type="checkbox" id="skip_test" checked={skipTest} onChange={(e) => setSkipTest(e.target.checked)} style={{ marginRight: '10px' }} />
                                        <label htmlFor="skip_test" style={{ fontSize: '13px' }}>Skip test, fake, localhost, and placeholder emails</label>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center' }}>
                                        <input type="checkbox" id="skip_role" checked={skipRole} onChange={(e) => setSkipRole(e.target.checked)} style={{ marginRight: '10px' }} />
                                        <label htmlFor="skip_role" style={{ fontSize: '13px' }}>Skip role-based emails like info@, support@, admin@</label>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="modal-actions" style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '30px', paddingTop: '20px', borderTop: '1px solid #e2e8f0' }}>
                            <Button isSecondary onClick={() => setIsImportModalOpen(false)}>Cancel</Button>
                            <Button isBusy={isImporting} onClick={handleImport} style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '8px 24px', borderRadius: '4px', fontWeight: 'bold' }}>
                                {selectedImportSource === 'csv' ? 'Next [Map Columns]' : 'Import'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {isEditModalOpen && (
                <Modal
                    title="Edit Contact"
                    onRequestClose={() => setIsEditModalOpen(false)}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content">
                        <TextControl
                            label="EMAIL ADDRESS *"
                            placeholder="email@example.com"
                            value={addEmail}
                            onChange={setAddEmail}
                        />
                        <div className="form-row">
                            <TextControl
                                label="FIRST NAME"
                                placeholder="Enter first name"
                                value={addFirstName}
                                onChange={setAddFirstName}
                            />
                            <TextControl
                                label="LAST NAME"
                                placeholder="Enter last name"
                                value={addLastName}
                                onChange={setAddLastName}
                            />
                        </div>
                        <div className="form-row">
                            <TextControl
                                label="PHONE NUMBER"
                                placeholder="+(000) 0000 0000"
                                value={addPhone}
                                onChange={setAddPhone}
                            />
                            <SelectControl
                                label="STATUS"
                                value={addStatus}
                                options={[
                                    { label: 'Subscribed', value: 'subscribed' },
                                    { label: 'Unsubscribed', value: 'unsubscribed' },
                                    { label: 'Unconfirmed', value: 'unconfirmed' },
                                    { label: 'Bounced', value: 'bounced' }
                                ]}
                                onChange={setAddStatus}
                            />
                        </div>

                        {renderListSelector()}
                        {renderTagSelector()}

                        <div className="modal-actions" style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                            <Button isSecondary onClick={() => setIsEditModalOpen(false)}>Cancel</Button>
                            <Button className="qcwpbot-primary-btn" isBusy={isEditingContact} onClick={handleUpdateContact}>Save Changes</Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Subscribers;
