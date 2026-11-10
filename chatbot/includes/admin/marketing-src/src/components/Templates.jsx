import { useState, useMemo, useEffect, useRef } from '@wordpress/element';
import { Button, TextControl, Modal, SelectControl, TextareaControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Templates = () => {

    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState('All Categories');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingTemplateId, setEditingTemplateId] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    
    // Form state
    const [newTitle, setNewTitle] = useState('');
    const [newCategory, setNewCategory] = useState('Marketing');
    const [newSubject, setNewSubject] = useState('');
    const [newBody, setNewBody] = useState('');
    const [editorMode, setEditorMode] = useState('visual'); // 'visual' or 'code'

    const visualEditorRef = useRef(null);
    const textareaRef = useRef(null);

    const categories = ['All Categories', 'General', 'Marketing', 'Transactional', 'Notification', 'Welcome', 'Newsletter', 'Promotional'];

    const [templates, setTemplates] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    const fetchTemplates = () => {
        setIsLoading(true);
        apiFetch({ path: '/wpbot-crm/v1/templates' })
            .then(res => {
                setTemplates(res.items || res || []);
                setIsLoading(false);
            })
            .catch(err => {
                console.error(err);
                setIsLoading(false);
            });
    };

    useEffect(() => {
        fetchTemplates();
    }, []);

    useEffect(() => {
        if (isModalOpen && editorMode === 'visual' && visualEditorRef.current) {
            if (visualEditorRef.current.innerHTML !== (newBody || '')) {
                visualEditorRef.current.innerHTML = newBody || '';
            }
        }
    }, [isModalOpen, editorMode, newBody]);

    const executeFormat = (command, value = null, fallbackStart = '', fallbackEnd = '') => {
        if (editorMode === 'visual' && visualEditorRef.current) {
            visualEditorRef.current.focus();
            document.execCommand(command, false, value);
            setNewBody(visualEditorRef.current.innerHTML);
        } else {
            insertTag(fallbackStart, fallbackEnd);
        }
    };

    const insertTag = (startTag, endTag = '') => {
        if (!textareaRef.current) return;
        const textarea = textareaRef.current;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = newBody || '';
        const before = text.substring(0, start);
        const selected = text.substring(start, end);
        const after = text.substring(end);
        
        const newText = before + startTag + selected + endTag + after;
        setNewBody(newText);
        
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(start + startTag.length, start + startTag.length + selected.length);
        }, 0);
    };

    const handleAddMedia = () => {
        if (typeof wp !== 'undefined' && wp.media) {
            const mediaUploader = wp.media({
                title: 'Select Media',
                button: { text: 'Insert into template' },
                multiple: false
            });
            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const imgTag = `<img src="${attachment.url}" alt="${attachment.alt || ''}" style="max-width: 100%; height: auto; margin: 10px 0;" />`;
                if (editorMode === 'visual' && visualEditorRef.current) {
                    visualEditorRef.current.focus();
                    document.execCommand('insertHTML', false, imgTag);
                    setNewBody(visualEditorRef.current.innerHTML);
                } else {
                    setNewBody(prev => (prev || '') + imgTag);
                }
            });
            mediaUploader.open();
        } else {
            alert('Media library is not available.');
        }
    };

    const getCategoryColor = (cat) => {
        const c = (cat || '').toLowerCase();
        switch (c) {
            case 'general': return { bg: '#dcfce7', text: '#166534' };
            case 'newsletter': return { bg: '#e0f2fe', text: '#0369a1' };
            case 'welcome': return { bg: '#f3e8ff', text: '#6b21a8' };
            case 'promotional': return { bg: '#fee2e2', text: '#991b1b' };
            case 'transactional': return { bg: '#e0e7ff', text: '#3730a3' };
            case 'marketing': return { bg: '#fce7f3', text: '#9d174d' };
            case 'notification': return { bg: '#fffbeb', text: '#b45309' };
            default: return { bg: '#f1f5f9', text: '#475569' };
        }
    };

    const filteredTemplates = useMemo(() => {
        return templates.filter(tpl => {
            const title = tpl.title || tpl.name || '';
            const cat = tpl.category || '';
            const matchesSearch = title.toLowerCase().includes(search.toLowerCase());
            const matchesCategory = activeCategory === 'All Categories' || cat.toLowerCase() === activeCategory.toLowerCase();
            return matchesSearch && matchesCategory;
        });
    }, [templates, search, activeCategory]);

    const handleOpenCreateModal = () => {
        setEditingTemplateId(null);
        setNewTitle('');
        setNewCategory('Marketing');
        setNewSubject('');
        setNewBody('');
        setEditorMode('visual');
        setIsModalOpen(true);
    };

    const handleEditTemplate = (tpl) => {
        setEditingTemplateId(tpl.id);
        setNewTitle(tpl.title || tpl.name || '');

        const rawCat = tpl.category || 'General';
        const matched = categories.find(c => c.toLowerCase() === rawCat.toLowerCase());
        setNewCategory(matched || (rawCat.charAt(0).toUpperCase() + rawCat.slice(1)));

        setNewSubject(tpl.description || tpl.subject || '');
        setNewBody(tpl.content || '');
        setEditorMode('visual');
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setEditingTemplateId(null);
        setNewTitle('');
        setNewCategory('Marketing');
        setNewSubject('');
        setNewBody('');
        setEditorMode('visual');
    };

    const handleSaveTemplate = () => {
        if (!newTitle.trim()) {
            alert('Template Name is required.');
            return;
        }

        setIsSaving(true);
        let contentToSave = newBody;
        if (editorMode === 'visual' && visualEditorRef.current) {
            contentToSave = visualEditorRef.current.innerHTML;
        }

        const payload = {
            name: newTitle.trim(),
            category: newCategory,
            description: newSubject,
            content: contentToSave,
            type: 'custom'
        };

        if (editingTemplateId) {
            apiFetch({
                path: `/wpbot-crm/v1/templates/${editingTemplateId}`,
                method: 'PUT',
                data: payload
            }).then(() => {
                setIsSaving(false);
                handleCloseModal();
                fetchTemplates();
            }).catch(err => {
                setIsSaving(false);
                alert('Error updating template: ' + (err.message || 'Failed to update template.'));
            });
        } else {
            apiFetch({
                path: '/wpbot-crm/v1/templates',
                method: 'POST',
                data: payload
            }).then(() => {
                setIsSaving(false);
                handleCloseModal();
                fetchTemplates();
            }).catch(err => {
                setIsSaving(false);
                alert('Error creating template: ' + (err.message || 'Failed to create template.'));
            });
        }
    };

    const handleDeleteTemplate = (tpl) => {
        if (window.confirm('Are you sure you want to delete this template?')) {
            apiFetch({
                path: `/wpbot-crm/v1/templates/${tpl.id}`,
                method: 'DELETE'
            }).then(() => {
                fetchTemplates();
            }).catch(err => {
                alert('Error deleting template: ' + (err.message || 'Failed to delete template.'));
            });
        }
    };

    return (
        <div className="qcwpbot-templates-page" style={{ padding: '20px', background: '#fff', borderRadius: '8px', boxShadow: '0 2px 4px rgba(0,0,0,0.05)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', borderBottom: '1px solid #e2e8f0', paddingBottom: '15px' }}>
                <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 'bold' }}>Template Library</h2>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <Button 
                        onClick={handleOpenCreateModal} 
                        className="qcwpbot-primary-btn" 
                        style={{ background: '#5b4e96', color: '#fff', border: 'none', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                    >
                        + New Template
                    </Button>
                </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px', flexWrap: 'wrap', gap: '15px' }}>
                <div style={{ width: '300px' }}>
                    <TextControl
                        type="search"
                        placeholder="Search templates..."
                        value={search}
                        onChange={(val) => setSearch(val)}
                        style={{ marginBottom: 0 }}
                    />
                </div>
                <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                    {categories.map(cat => (
                        <button 
                            key={cat} 
                            onClick={() => setActiveCategory(cat)}
                            style={{ 
                                padding: '6px 16px', 
                                borderRadius: '20px', 
                                border: '1px solid #e2e8f0', 
                                background: activeCategory === cat ? '#5b4e96' : '#fff', 
                                color: activeCategory === cat ? '#fff' : '#64748b',
                                cursor: 'pointer',
                                fontSize: '13px',
                                transition: 'all 0.2s'
                            }}
                        >
                            {cat}
                        </button>
                    ))}
                </div>
            </div>

            {isLoading ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>Loading templates...</div>
            ) : filteredTemplates.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                    No templates found matching your criteria.
                </div>
            ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
                    {filteredTemplates.map(tpl => {
                        const catStyle = getCategoryColor(tpl.category);
                        const title = tpl.title || tpl.name || '(No Name)';
                        const date = tpl.date || tpl.created_at || 'Just now';
                        
                        return (
                            <div key={tpl.id} style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
                                <div style={{ height: '180px', width: '100%', overflow: 'hidden', background: '#fff', position: 'relative' }}>
                                    <div 
                                        style={{ 
                                            width: '800px', 
                                            height: '1000px', 
                                            transform: 'scale(0.35)', 
                                            transformOrigin: 'top left', 
                                            pointerEvents: 'none' 
                                        }} 
                                        dangerouslySetInnerHTML={{ __html: tpl.content || '<div style="padding: 20px; color: #94a3b8;">Empty Template</div>' }}
                                    />
                                </div>
                                <div style={{ padding: '15px', flex: 1 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '10px' }}>
                                        <h3 
                                            style={{ margin: 0, fontSize: '16px', fontWeight: 'bold', color: '#1e293b', cursor: 'pointer' }}
                                            onClick={() => handleEditTemplate(tpl)}
                                            title="Click to edit template"
                                        >
                                            {title}
                                        </h3>
                                        <span style={{ fontSize: '12px', color: '#64748b' }}>{date.split(' ')[0]}</span>
                                    </div>
                                    <span style={{ display: 'inline-block', background: catStyle.bg, color: catStyle.text, fontSize: '12px', padding: '4px 10px', borderRadius: '20px', fontWeight: '500' }}>
                                        {tpl.category || 'General'}
                                    </span>
                                </div>
                                <div style={{ padding: '15px', borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div style={{ display: 'flex', gap: '10px' }}>
                                        <span style={{ fontSize: '12px', color: '#16a34a', fontWeight: '600', display: 'inline-flex', alignItems: 'center', gap: '5px' }}>
                                                <span style={{ width: '7px', height: '7px', borderRadius: '50%', background: '#16a34a', display: 'inline-block' }}></span> {tpl.type === 'system' ? 'Built-in Template' : 'Custom Template'}
                                        </span>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                        <button 
                                            type="button"
                                            title="Edit template"
                                            onClick={() => handleEditTemplate(tpl)} 
                                            style={{ 
                                                display: 'inline-flex', 
                                                alignItems: 'center', 
                                                gap: '4px', 
                                                background: '#f1f5f9', 
                                                border: '1px solid #e2e8f0', 
                                                borderRadius: '4px', 
                                                padding: '4px 8px', 
                                                color: '#475569', 
                                                cursor: 'pointer', 
                                                fontSize: '12px', 
                                                fontWeight: '500' 
                                            }}
                                        >
                                            <span className="dashicons dashicons-edit" style={{ fontSize: '15px', width: '15px', height: '15px' }}></span> Edit
                                        </button>
                                        <button 
                                            type="button"
                                            title="Delete template"
                                            onClick={() => handleDeleteTemplate(tpl)} 
                                            style={{ background: 'transparent', border: 'none', color: '#ef4444', cursor: 'pointer', padding: '5px', display: 'inline-flex', alignItems: 'center' }}
                                        >
                                            <span className="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {isModalOpen && (
                <Modal
                    title={
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                            <div style={{ background: '#5b4e96', borderRadius: '4px', width: '24px', height: '24px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                                <span className={`dashicons ${editingTemplateId ? 'dashicons-edit' : 'dashicons-media-document'}`} style={{ color: '#fff', fontSize: '16px', width: '16px', height: '16px' }}></span>
                            </div>
                            <span style={{ fontWeight: 'bold', fontSize: '18px' }}>
                                {editingTemplateId ? 'Edit Template' : 'Create New Template'}
                            </span>
                        </div>
                    }
                    onRequestClose={handleCloseModal}
                    style={{ width: '820px', maxWidth: '95vw', padding: '0' }}
                    className="qcwpbot-template-modal"
                >
                    <div style={{ padding: '0 5px 20px 5px', color: '#64748b', marginTop: '-10px' }}>
                        {editingTemplateId ? 'Update your email template details and HTML content' : 'Design your email template from scratch or generate with AI'}
                    </div>
                    
                    <div style={{ display: 'flex', gap: '20px', marginBottom: '20px' }}>
                        <div style={{ flex: 1 }}>
                            <strong style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>TEMPLATE NAME</strong>
                            <TextControl
                                placeholder="e.g. Welcome Series #1"
                                value={newTitle}
                                onChange={(val) => setNewTitle(val)}
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <strong style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>CATEGORY</strong>
                            <SelectControl
                                value={newCategory}
                                options={categories.filter(c => c !== 'All Categories').map(c => ({ label: c, value: c }))}
                                onChange={(val) => setNewCategory(val)}
                            />
                        </div>
                    </div>

                    <div style={{ marginBottom: '20px' }}>
                        <strong style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>SUBJECT LINE</strong>
                        <TextControl
                            placeholder="Email subject line..."
                            value={newSubject}
                            onChange={(val) => setNewSubject(val)}
                        />
                    </div>

                    <div style={{ marginBottom: '20px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                            <strong style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold' }}>
                                CONTENT ({editorMode === 'visual' ? 'VISUAL EDITOR' : 'HTML CODE'})
                            </strong>
                            <div style={{ display: 'flex', border: '1px solid #c3c4c7', borderRadius: '3px', overflow: 'hidden' }}>
                                <div 
                                    onClick={() => setEditorMode('visual')} 
                                    style={{ 
                                        padding: '3px 12px', 
                                        fontSize: '12px', 
                                        background: editorMode === 'visual' ? '#5b4e96' : '#fff', 
                                        color: editorMode === 'visual' ? '#fff' : '#3c434a', 
                                        borderRight: '1px solid #c3c4c7', 
                                        cursor: 'pointer', 
                                        fontWeight: editorMode === 'visual' ? '600' : 'normal' 
                                    }}
                                >
                                    Visual
                                </div>
                                <div 
                                    onClick={() => setEditorMode('code')} 
                                    style={{ 
                                        padding: '3px 12px', 
                                        fontSize: '12px', 
                                        background: editorMode === 'code' ? '#5b4e96' : '#fff', 
                                        color: editorMode === 'code' ? '#fff' : '#3c434a', 
                                        cursor: 'pointer', 
                                        fontWeight: editorMode === 'code' ? '600' : 'normal' 
                                    }}
                                >
                                    Code (HTML)
                                </div>
                            </div>
                        </div>

                        {/* Editor Box */}
                        <div style={{ border: '1px solid #cbd5e1', borderRadius: '6px', overflow: 'hidden', background: '#fff' }}>
                            {/* Toolbar */}
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0', flexWrap: 'wrap', gap: '8px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                                    <button 
                                        onClick={handleAddMedia} 
                                        type="button" 
                                        style={{ display: 'flex', alignItems: 'center', gap: '4px', background: '#fff', border: '1px solid #cbd5e1', color: '#334155', padding: '3px 8px', borderRadius: '4px', cursor: 'pointer', fontSize: '12px', fontWeight: '500' }}
                                    >
                                        <span className="dashicons dashicons-admin-media" style={{ fontSize: '15px', width: '15px', height: '15px' }}></span> Add Media
                                    </button>

                                    <div style={{ width: '1px', height: '18px', background: '#cbd5e1' }}></div>

                                    <select 
                                        style={{ padding: '2px 8px', fontSize: '12px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff' }} 
                                        onChange={(e) => { 
                                            const val = e.target.value;
                                            if (val) {
                                                executeFormat('formatBlock', `<${val}>`, `<${val}>`, `</${val}>`);
                                            } else {
                                                executeFormat('formatBlock', '<p>', '<p>', '</p>');
                                            }
                                            e.target.value = ""; 
                                        }}
                                    >
                                        <option value="">Paragraph</option>
                                        <option value="h1">Heading 1</option>
                                        <option value="h2">Heading 2</option>
                                        <option value="h3">Heading 3</option>
                                    </select>

                                    <div style={{ display: 'flex', gap: '2px' }}>
                                        <button type="button" title="Bold" onClick={() => executeFormat('bold', null, '<b>', '</b>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 8px', fontWeight: 'bold', fontSize: '12px' }}>B</button>
                                        <button type="button" title="Italic" onClick={() => executeFormat('italic', null, '<i>', '</i>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 8px', fontStyle: 'italic', fontSize: '12px' }}>I</button>
                                        <button type="button" title="Underline" onClick={() => executeFormat('underline', null, '<u>', '</u>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 8px', textDecoration: 'underline', fontSize: '12px' }}>U</button>
                                        <button type="button" title="Strikethrough" onClick={() => executeFormat('strikeThrough', null, '<strike>', '</strike>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 8px', textDecoration: 'line-through', fontSize: '11px' }}>S</button>
                                    </div>

                                    <div style={{ width: '1px', height: '18px', background: '#cbd5e1' }}></div>

                                    <div style={{ display: 'flex', gap: '2px' }}>
                                        <button type="button" title="Bullet List" onClick={() => executeFormat('insertUnorderedList', null, '<ul>\n<li>', '</li>\n</ul>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-ul" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                        <button type="button" title="Numbered List" onClick={() => executeFormat('insertOrderedList', null, '<ol>\n<li>', '</li>\n</ol>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-ol" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                        <button type="button" title="Blockquote" onClick={() => executeFormat('formatBlock', '<blockquote>', '<blockquote>', '</blockquote>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-quote" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                    </div>

                                    <div style={{ width: '1px', height: '18px', background: '#cbd5e1' }}></div>

                                    <div style={{ display: 'flex', gap: '2px' }}>
                                        <button type="button" title="Align Left" onClick={() => executeFormat('justifyLeft', null, '<div style="text-align: left;">', '</div>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-alignleft" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                        <button type="button" title="Align Center" onClick={() => executeFormat('justifyCenter', null, '<div style="text-align: center;">', '</div>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-aligncenter" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                        <button type="button" title="Align Right" onClick={() => executeFormat('justifyRight', null, '<div style="text-align: right;">', '</div>')} style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}><span className="dashicons dashicons-editor-alignright" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span></button>
                                    </div>

                                    <div style={{ width: '1px', height: '18px', background: '#cbd5e1' }}></div>

                                    <button 
                                        type="button" 
                                        title="Insert Link" 
                                        onClick={() => { 
                                            const url = prompt('Enter URL:'); 
                                            if (url) {
                                                executeFormat('createLink', url, `<a href="${url}">`, '</a>'); 
                                            }
                                        }} 
                                        style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: '3px', cursor: 'pointer', padding: '2px 6px', fontSize: '12px' }}
                                    >
                                        <span className="dashicons dashicons-admin-links" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span>
                                    </button>
                                </div>
                            </div>

                            {/* Editing Area */}
                            {editorMode === 'visual' ? (
                                <div 
                                    ref={visualEditorRef}
                                    contentEditable={true}
                                    suppressContentEditableWarning={true}
                                    onInput={(e) => {
                                        setNewBody(e.currentTarget.innerHTML);
                                    }}
                                    onBlur={(e) => {
                                        setNewBody(e.currentTarget.innerHTML);
                                    }}
                                    style={{ 
                                        minHeight: '260px', 
                                        maxHeight: '400px', 
                                        overflowY: 'auto',
                                        padding: '20px', 
                                        outline: 'none', 
                                        lineHeight: '1.6', 
                                        color: '#1e293b', 
                                        background: '#fff' 
                                    }}
                                />
                            ) : (
                                <textarea
                                    ref={textareaRef}
                                    value={newBody}
                                    onChange={(e) => setNewBody(e.target.value)}
                                    placeholder="Paste or write your HTML email code here..."
                                    style={{
                                        width: '100%',
                                        minHeight: '260px',
                                        maxHeight: '400px',
                                        padding: '15px',
                                        border: 'none',
                                        outline: 'none',
                                        fontFamily: 'monospace',
                                        fontSize: '13px',
                                        lineHeight: '1.5',
                                        boxSizing: 'border-box',
                                        resize: 'vertical',
                                        display: 'block'
                                    }}
                                />
                            )}
                        </div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', background: '#f8fafc', padding: '15px', margin: '0 -20px -20px -20px', borderTop: '1px solid #e2e8f0', borderRadius: '0 0 4px 4px' }}>
                        <div></div>
                        <div style={{ display: 'flex', gap: '10px' }}>
                            <Button isSecondary onClick={handleCloseModal}>Cancel</Button>
                            <Button 
                                onClick={handleSaveTemplate} 
                                disabled={isSaving}
                                style={{ background: '#5b4e96', color: '#fff', border: 'none' }}
                            >
                                {isSaving ? 'Saving...' : (editingTemplateId ? 'Save Changes' : 'Create Template')}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Templates;
