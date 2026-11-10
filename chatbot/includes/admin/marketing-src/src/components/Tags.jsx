import { useState, useEffect } from '@wordpress/element';
import { Button, Modal, TextControl, TextareaControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Tags = () => {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editId, setEditId] = useState(null);
    const [tags, setTags] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    
    // Form states
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');

    const fetchTags = () => {
        setLoading(true);
        apiFetch({ path: '/wpbot-crm/v1/tags' })
            .then(res => {
                setTags(res.items || res || []);
                setLoading(false);
            })
            .catch(err => {
                console.error("Tags fetch error:", err);
                setTags([]);
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchTags();
    }, []);

    const handleCreate = () => {
        if (!title.trim()) return;
        setSaving(true);
        apiFetch({
            path: '/wpbot-crm/v1/tags',
            method: 'POST',
            data: { title, description }
        }).then(() => {
            setSaving(false);
            setIsAddModalOpen(false);
            setTitle('');
            setDescription('');
            fetchTags();
        }).catch(err => {
            console.error("Tag create error:", err);
            setSaving(false);
        });
    };

    const handleEditClick = (tag) => {
        setEditId(tag.id);
        setTitle(tag.title);
        setDescription(tag.description || '');
        setIsEditModalOpen(true);
    };

    const handleUpdate = () => {
        if (!title.trim() || !editId) return;
        setSaving(true);
        apiFetch({
            path: `/wpbot-crm/v1/tags/${editId}`,
            method: 'PUT',
            data: { title, description }
        }).then(() => {
            setSaving(false);
            setIsEditModalOpen(false);
            setEditId(null);
            setTitle('');
            setDescription('');
            fetchTags();
        }).catch(err => {
            console.error("Tag update error:", err);
            setSaving(false);
        });
    };

    const handleDelete = (id) => {
        if (!confirm('Are you sure you want to delete this tag?')) return;
        
        // Optimistic UI update or simple reload
        apiFetch({
            path: `/wpbot-crm/v1/tags/${id}`,
            method: 'DELETE'
        }).then(() => {
            fetchTags();
        }).catch(err => {
            console.error("Tag delete error:", err);
        });
    };

    return (
        <div className="qcwpbot-contacts">
            <div className="qcwpbot-header">
                <h2 style={{ fontSize: '16px', fontWeight: 'bold' }}>Tags</h2>
                <div className="header-actions">
                    <Button className="qcwpbot-primary-btn" onClick={() => setIsAddModalOpen(true)}>+ New Tag</Button>
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
                        ) : tags.length > 0 ? (
                            tags.map((tag) => (
                                <tr key={tag.id}>
                                    <td><span style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: '4px', fontSize: '12px' }}>{tag.id}</span></td>
                                    <td style={{ fontWeight: 'bold', color: '#1e293b' }}>{tag.title}</td>
                                    <td>{tag.subscribers_count || 0}</td>
                                    <td>{tag.description}</td>
                                    <td>{new Date(tag.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                                    <td>
                                        <a href="#" style={{ color: '#64748b', textDecoration: 'none', marginRight: '12px' }} onClick={(e) => { e.preventDefault(); handleEditClick(tag); }}>Edit</a>
                                        <a href="#" style={{ color: '#be1e43', textDecoration: 'none', fontWeight: 'bold' }} onClick={(e) => { e.preventDefault(); handleDelete(tag.id); }}>Delete</a>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="6" className="empty-row">No tags found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {isAddModalOpen && (
                <Modal
                    title="New Tag"
                    onRequestClose={() => setIsAddModalOpen(false)}
                    className="qcwpbot-modal"
                >
                    <div className="qcwpbot-modal-content">
                        <TextControl
                            label="TITLE"
                            placeholder="Tag name..."
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
                    title="Edit Tag"
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
                            placeholder="Tag name..."
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

export default Tags;
