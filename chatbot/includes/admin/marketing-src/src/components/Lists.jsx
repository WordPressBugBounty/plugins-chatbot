import { useState, useEffect } from '@wordpress/element';
import { Button, Modal, TextControl, TextareaControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Lists = () => {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editId, setEditId] = useState(null);
    const [lists, setLists] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    
    // Form states
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');

    const fetchLists = () => {
        setLoading(true);
        apiFetch({ path: '/wpbot-crm/v1/lists' })
            .then(res => {
                setLists(res.items || res || []);
                setLoading(false);
            })
            .catch(err => {
                console.error("Lists fetch error:", err);
                setLists([]);
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchLists();
    }, []);

    const handleCreate = () => {
        if (!title.trim()) return;
        setSaving(true);
        apiFetch({
            path: '/wpbot-crm/v1/lists',
            method: 'POST',
            data: { title, description }
        }).then(() => {
            setSaving(false);
            setIsAddModalOpen(false);
            setTitle('');
            setDescription('');
            fetchLists();
        }).catch(err => {
            console.error("List create error:", err);
            setSaving(false);
        });
    };

    const handleEditClick = (list) => {
        setEditId(list.id);
        setTitle(list.title);
        setDescription(list.description || '');
        setIsEditModalOpen(true);
    };

    const handleUpdate = () => {
        if (!title.trim() || !editId) return;
        setSaving(true);
        apiFetch({
            path: `/wpbot-crm/v1/lists/${editId}`,
            method: 'PUT',
            data: { title, description }
        }).then(() => {
            setSaving(false);
            setIsEditModalOpen(false);
            setEditId(null);
            setTitle('');
            setDescription('');
            fetchLists();
        }).catch(err => {
            console.error("List update error:", err);
            setSaving(false);
        });
    };

    const handleDelete = (id) => {
        if (!confirm('Are you sure you want to delete this list?')) return;
        
        // Optimistic UI update or simple reload
        apiFetch({
            path: `/wpbot-crm/v1/lists/${id}`,
            method: 'DELETE'
        }).then(() => {
            fetchLists();
        }).catch(err => {
            console.error("List delete error:", err);
        });
    };

    return (
        <div className="qcwpbot-contacts">
            <div className="qcwpbot-header">
                <h2 style={{ fontSize: '16px', fontWeight: 'bold' }}>Lists</h2>
                <div className="header-actions">
                    <Button className="qcwpbot-primary-btn" onClick={() => setIsAddModalOpen(true)}>+ New List</Button>
                </div>
            </div>

            <div className="qcwpbot-table-container">
                <table className="qcwpbot-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TITLE</th>
                            <th>SUBSCRIBERS</th>
                            <th>DESCRIPTION</th>
                            <th>CREATED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr>
                                <td colSpan="6" style={{ textAlign: 'center' }}>
                                    <Spinner />
                                </td>
                            </tr>
                        ) : lists.length > 0 ? (
                            lists.map((list) => (
                                <tr key={list.id}>
                                    <td><span style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: '4px', fontSize: '12px' }}>{list.id}</span></td>
                                    <td style={{ fontWeight: 'bold', color: '#1e293b' }}>{list.title}</td>
                                    <td>{list.subscribers_count || 0}</td>
                                    <td>{list.description}</td>
                                    <td>{new Date(list.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                                    <td>
                                        <a href="#" style={{ color: '#64748b', textDecoration: 'none', marginRight: '12px' }} onClick={(e) => { e.preventDefault(); handleEditClick(list); }}>Edit</a>
                                        <a href="#" style={{ color: '#be1e43', textDecoration: 'none', fontWeight: 'bold' }} onClick={(e) => { e.preventDefault(); handleDelete(list.id); }}>Delete</a>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="6" className="empty-row">No lists found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {isAddModalOpen && (
                <Modal
                    title="New List"
                    onRequestClose={() => setIsAddModalOpen(false)}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content">
                        <TextControl
                            label="TITLE"
                            placeholder="List name..."
                            value={title}
                            onChange={(val) => setTitle(val)}
                        />
                        <TextareaControl
                            label="DESCRIPTION"
                            placeholder="Optional description..."
                            rows={4}
                            value={description}
                            onChange={(val) => setDescription(val)}
                        />

                        <div className="modal-actions">
                            <Button isSecondary onClick={() => setIsAddModalOpen(false)}>Cancel</Button>
                            <Button 
                                className="qcwpbot-primary-btn" 
                                style={{opacity: title.trim() ? 1 : 0.6}}
                                onClick={handleCreate}
                                disabled={saving || !title.trim()}
                            >
                                {saving ? <Spinner /> : 'Create'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {isEditModalOpen && (
                <Modal
                    title="Edit List"
                    onRequestClose={() => {
                        setIsEditModalOpen(false);
                        setEditId(null);
                        setTitle('');
                        setDescription('');
                    }}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content">
                        <TextControl
                            label="TITLE"
                            placeholder="List name..."
                            value={title}
                            onChange={(val) => setTitle(val)}
                        />
                        <TextareaControl
                            label="DESCRIPTION"
                            placeholder="Optional description..."
                            rows={4}
                            value={description}
                            onChange={(val) => setDescription(val)}
                        />

                        <div className="modal-actions">
                            <Button isSecondary onClick={() => {
                                setIsEditModalOpen(false);
                                setEditId(null);
                                setTitle('');
                                setDescription('');
                            }}>Cancel</Button>
                            <Button 
                                className="qcwpbot-primary-btn" 
                                style={{opacity: title.trim() ? 1 : 0.6}}
                                onClick={handleUpdate}
                                disabled={saving || !title.trim()}
                            >
                                {saving ? <Spinner /> : 'Update'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Lists;
