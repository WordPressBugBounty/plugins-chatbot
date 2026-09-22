import React, { useState, useEffect } from 'react';
import { ArrowLeft, Save, Plus, Trash2, Settings2, Eye, Layout, Type, Image as ImageIcon, MousePointer2, Minus } from 'lucide-react';

const EmailDesigner = ({ template, onBack }) => {
  const [name, setName] = useState(template?.name || 'Untitled Template');
  const [subject, setSubject] = useState(template?.subject || '');
  const [blocks, setBlocks] = useState(() => {
    if (template?.body_json) {
      try {
        return JSON.parse(template.body_json);
      } catch (e) {
        console.error('Failed to parse body_json:', e);
      }
    }
    return [
      { id: '1', type: 'text', content: '<h1>Welcome to our Newsletter!</h1><p>Start designing your beautiful email here.</p>' }
    ];
  });
  const [selectedBlockId, setSelectedBlockId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [uploadError, setUploadError] = useState(null);
  const [showPreview, setShowPreview] = useState(false);

  const blockTypes = [
    { type: 'text', icon: Type, label: 'Text Block' },
    { type: 'button', icon: MousePointer2, label: 'Button' },
    { type: 'image', icon: ImageIcon, label: 'Image' },
    { type: 'divider', icon: Minus, label: 'Divider' },
    { type: 'spacer', icon: Layout, label: 'Spacer' },
  ];

  const addBlock = (type) => {
    let content = '';
    let styles = {};

    switch (type) {
      case 'text':
        content = '<p>Enter your text here...</p>';
        break;
      case 'button':
        content = 'Click Me';
        styles = { backgroundColor: '#3b82f6', color: '#ffffff', padding: '12px 24px', borderRadius: '6px', url: '#' };
        break;
      case 'image':
        content = 'https://via.placeholder.com/600x300?text=Your+Image';
        break;
      case 'divider':
        content = '';
        styles = { borderTop: '1px solid #e5e7eb', margin: '20px 0' };
        break;
      case 'spacer':
        styles = { height: '30px' };
        break;
    }

    const newBlock = {
      id: Math.random().toString(36).substr(2, 9),
      type,
      content,
      styles
    };
    setBlocks([...blocks, newBlock]);
    setSelectedBlockId(newBlock.id);
  };

  const removeBlock = (id) => {
    setBlocks(blocks.filter(b => b.id !== id));
    if (selectedBlockId === id) setSelectedBlockId(null);
  };

  const updateBlock = (id, updates) => {
    setBlocks(blocks.map(b => b.id === id ? { ...b, ...updates } : b));
  };

  const getMediaApiUrl = () => {
    if (!window.wpbotAutomator?.apiUrl) {
      return '/wp-json/wp/v2/media';
    }

    try {
      const apiUrl = new URL(window.wpbotAutomator.apiUrl);
      const mediaPath = apiUrl.pathname.replace(/\/wpbot-automator\/v1\/?$/, '/wp/v2/');
      return `${apiUrl.origin}${mediaPath}media`;
    } catch (error) {
      return '/wp-json/wp/v2/media';
    }
  };

  const uploadImageFile = async (file) => {
    if (!file) {
      return null;
    }

    setUploadingImage(true);
    setUploadError(null);

    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await fetch(getMediaApiUrl(), {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: formData,
      });

      const result = await response.json();

      if (!response.ok || !result?.source_url) {
        throw new Error(result?.message || 'Failed to upload image');
      }

      if (selectedBlockId) {
        updateBlock(selectedBlockId, { content: result.source_url });
      }

      return result.source_url;
    } catch (error) {
      console.error('Image upload failed:', error);
      setUploadError(error.message || 'Failed to upload image.');
      return null;
    } finally {
      setUploadingImage(false);
    }
  };

  const handleImageFileChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    await uploadImageFile(file);
    event.target.value = '';
  };

  const handleSave = async () => {
    setSaving(true);
    const html = generateHTML();
    const data = {
      name,
      subject,
      body_html: html,
      body_json: JSON.stringify(blocks)
    };

    try {
      const baseUrl = window.wpbotAutomator.apiUrl.replace(/\/$/, '');
      const url = template?.id 
        ? `${baseUrl}/email-templates/${template.id}`
        : `${baseUrl}/email-templates`;
      
      const response = await fetch(url, {
        method: template?.id ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify(data),
      });
      const result = await response.json();
      if (result.success) {
        alert('Template saved successfully!');
        if (!template?.id) onBack(); // Go back if it was new
      } else {
        alert(result.message || 'Failed to save template.');
      }
    } catch (error) {
      console.error('Error saving template:', error);
    } finally {
      setSaving(false);
    }
  };

  const generateHTML = () => {
    let html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f7f9; }
          .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
          .content { padding: 40px; }
          .btn { display: inline-block; text-decoration: none; font-weight: bold; text-align: center; }
          img { max-width: 100%; height: auto; display: block; }
          @media only screen and (max-width: 600px) { .container { margin: 0; border-radius: 0; } }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="content">
    `;

    blocks.forEach(block => {
      switch (block.type) {
        case 'text':
          html += `<div class="block-text">${block.content}</div>`;
          break;
        case 'button':
          const s = block.styles;
          html += `<div style="text-align: center; margin: 20px 0;"><a href="${s.url}" class="btn" style="background-color: ${s.backgroundColor}; color: ${s.color}; padding: ${s.padding}; border-radius: ${s.borderRadius};">${block.content}</a></div>`;
          break;
        case 'image':
          html += `<div style="margin: 20px 0;"><img src="${block.content}" alt="Image"></div>`;
          break;
        case 'divider':
          html += `<hr style="border: none; border-top: ${block.styles.borderTop}; margin: ${block.styles.margin};">`;
          break;
        case 'spacer':
          html += `<div style="height: ${block.styles.height};"></div>`;
          break;
      }
    });

    html += `
          </div>
          <div style="background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #f3f4f6;">
            Sent by ${window.wpbotAutomator.blogname || 'Our Website'}
          </div>
        </div>
      </body>
      </html>
    `;
    return html;
  };

  const selectedBlock = blocks.find(b => b.id === selectedBlockId);

  return (
    <div className="flex flex-col h-[calc(100vh-100px)]">
      {/* Header */}
      <div className="flex justify-between items-center mb-6 bg-card p-4 rounded-xl border border-border shadow-sm">
        <div className="flex items-center space-x-4">
          <button onClick={onBack} className="p-2 hover:bg-muted rounded-full transition-colors">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <input 
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="text-xl font-bold bg-transparent border-none focus:ring-0 p-0 hover:bg-muted/50 rounded px-2 cursor-text transition-colors"
            />
            <div className="flex items-center mt-0.5 px-2">
              <span className="text-xs text-muted-foreground mr-2 font-medium">Subject:</span>
              <input 
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
                placeholder="Enter email subject..."
                className="text-xs text-primary bg-transparent border-none focus:ring-0 p-0 w-64 placeholder:text-muted-foreground/50"
              />
            </div>
          </div>
        </div>
        <div className="flex items-center space-x-3">
          <button 
            onClick={() => setShowPreview(!showPreview)}
            className="flex items-center space-x-2 px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors text-sm font-medium"
          >
            {showPreview ? <Layout className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
            <span>{showPreview ? 'Editor' : 'Preview'}</span>
          </button>
          <button 
            onClick={handleSave}
            disabled={saving}
            className="flex items-center space-x-2 px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-all font-medium disabled:opacity-50 shadow-sm"
          >
            {saving ? <Plus className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            <span>{saving ? 'Saving...' : 'Save Template'}</span>
          </button>
        </div>
      </div>

      {showPreview ? (
        <div className="flex-1 overflow-auto p-8 bg-muted/30 rounded-xl border border-border">
          <div className="max-w-[600px] mx-auto shadow-2xl rounded-lg overflow-hidden bg-white">
            <iframe 
              title="Email Preview"
              srcDoc={generateHTML()}
              className="w-full h-[800px] border-none"
            />
          </div>
        </div>
      ) : (
        <div className="flex flex-1 space-x-6 overflow-hidden">
          {/* Main Canvas */}
          <div className="flex-1 bg-muted/20 border border-border rounded-xl overflow-y-auto p-12 designer-canvas">
            <div className="max-w-[600px] mx-auto bg-white shadow-lg rounded-lg min-h-[600px] flex flex-col p-10 border border-border/50">
              <div className="space-y-4">
                {blocks.map((block) => (
                  <div 
                    key={block.id}
                    onClick={() => setSelectedBlockId(block.id)}
                    className={`relative group cursor-pointer border-2 rounded-lg transition-all ${
                      selectedBlockId === block.id 
                        ? 'border-primary ring-4 ring-primary/10 shadow-md' 
                        : 'border-transparent hover:border-primary/30'
                    }`}
                  >
                    {/* Render Block Content */}
                    <div className="p-4">
                      {block.type === 'text' && (
                        <div dangerouslySetInnerHTML={{ __html: block.content }} />
                      )}
                      {block.type === 'button' && (
                        <div className="text-center my-4">
                          <span 
                            style={{
                              backgroundColor: block.styles.backgroundColor,
                              color: block.styles.color,
                              padding: block.styles.padding,
                              borderRadius: block.styles.borderRadius,
                              display: 'inline-block',
                              fontWeight: 'bold'
                            }}
                          >
                            {block.content}
                          </span>
                        </div>
                      )}
                      {block.type === 'image' && (
                        <div className="my-4">
                          <img src={block.content} alt="Block" className="mx-auto rounded-md shadow-sm" />
                        </div>
                      )}
                      {block.type === 'divider' && (
                        <hr style={block.styles} />
                      )}
                      {block.type === 'spacer' && (
                        <div style={block.styles}></div>
                      )}
                    </div>

                    {/* Block Toolbar */}
                    {selectedBlockId === block.id && (
                      <div className="absolute -right-12 top-0 flex flex-col space-y-2">
                        <button 
                          onClick={(e) => { e.stopPropagation(); removeBlock(block.id); }}
                          className="p-2 bg-rose-500 text-white rounded-lg shadow-lg hover:bg-rose-600 transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>

              {blocks.length === 0 && (
                <div className="flex flex-col items-center justify-center flex-1 text-muted-foreground opacity-50 space-y-4">
                  <Layout className="w-12 h-12" />
                  <p className="text-sm font-medium">Your canvas is empty. Drag or click blocks to start.</p>
                </div>
              )}
            </div>
          </div>

          {/* Right Panels */}
          <div className="w-80 flex flex-col space-y-6">
            {/* Widget Picker */}
            <div className="bg-card border border-border rounded-xl p-5 shadow-sm">
              <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground mb-4 flex items-center">
                <Plus className="w-4 h-4 mr-2" />
                Add Blocks
              </h3>
              <div className="grid grid-cols-2 gap-3">
                {blockTypes.map((bt) => (
                  <button
                    key={bt.type}
                    onClick={() => addBlock(bt.type)}
                    className="flex flex-col items-center justify-center p-4 border border-border rounded-xl hover:bg-primary hover:text-primary-foreground hover:scale-105 hover:shadow-md transition-all group"
                  >
                    <bt.icon className="w-6 h-6 mb-2 group-hover:animate-pulse" />
                    <span className="text-[10px] font-bold uppercase tracking-tight">{bt.label}</span>
                  </button>
                ))}
              </div>
            </div>

            {/* Inspector */}
            <div className="bg-card border border-border rounded-xl flex-1 p-5 shadow-sm overflow-y-auto">
              <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground mb-4 flex items-center border-b border-border pb-2">
                <Settings2 className="w-4 h-4 mr-2" />
                Settings
              </h3>
              
              {selectedBlock ? (
                <div className="space-y-6">
                  <div>
                    <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Content</label>
                    {selectedBlock.type === 'text' ? (
                      <textarea 
                        value={selectedBlock.content}
                        onChange={(e) => updateBlock(selectedBlock.id, { content: e.target.value })}
                        className="w-full p-3 border border-border rounded-lg bg-background text-sm min-h-[150px] focus:ring-2 focus:ring-primary/20 transition-all font-mono"
                        placeholder="Supports HTML..."
                      />
                    ) : (
                      <input 
                        value={selectedBlock.content}
                        onChange={(e) => updateBlock(selectedBlock.id, { content: e.target.value })}
                        className="w-full p-3 border border-border rounded-lg bg-background text-sm focus:ring-2 focus:ring-primary/20 transition-all"
                      />
                    )}
                  </div>

                  {selectedBlock.type === 'image' && (
                    <div className="space-y-4">
                      <div>
                        <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Upload Image</label>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={handleImageFileChange}
                          className="w-full p-2 border border-border rounded-lg bg-background text-sm"
                        />
                        {uploadingImage && (
                          <p className="text-xs text-muted-foreground mt-2">Uploading image…</p>
                        )}
                        {uploadError && (
                          <p className="text-xs text-rose-500 mt-2">{uploadError}</p>
                        )}
                      </div>
                      <div>
                        <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Image URL</label>
                        <input
                          value={selectedBlock.content}
                          onChange={(e) => updateBlock(selectedBlock.id, { content: e.target.value })}
                          placeholder="Paste image URL or upload a file"
                          className="w-full p-3 border border-border rounded-lg bg-background text-sm focus:ring-2 focus:ring-primary/20 transition-all"
                        />
                      </div>
                    </div>
                  )}

                  {selectedBlock.type === 'button' && (
                    <>
                      <div>
                        <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Target URL</label>
                        <input 
                          value={selectedBlock.styles.url}
                          onChange={(e) => updateBlock(selectedBlock.id, { styles: { ...selectedBlock.styles, url: e.target.value } })}
                          className="w-full p-3 border border-border rounded-lg bg-background text-sm font-mono"
                        />
                      </div>
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">BG Color</label>
                          <input 
                            type="color"
                            value={selectedBlock.styles.backgroundColor}
                            onChange={(e) => updateBlock(selectedBlock.id, { styles: { ...selectedBlock.styles, backgroundColor: e.target.value } })}
                            className="w-full h-10 p-1 border border-border rounded-lg bg-background"
                          />
                        </div>
                        <div>
                          <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Text Color</label>
                          <input 
                            type="color"
                            value={selectedBlock.styles.color}
                            onChange={(e) => updateBlock(selectedBlock.id, { styles: { ...selectedBlock.styles, color: e.target.value } })}
                            className="w-full h-10 p-1 border border-border rounded-lg bg-background"
                          />
                        </div>
                      </div>
                    </>
                  )}

                  {selectedBlock.type === 'spacer' && (
                    <div>
                      <label className="text-xs font-bold text-muted-foreground uppercase mb-2 block">Height (px)</label>
                      <input 
                        type="number"
                        value={parseInt(selectedBlock.styles.height)}
                        onChange={(e) => updateBlock(selectedBlock.id, { styles: { ...selectedBlock.styles, height: e.target.value + 'px' } })}
                        className="w-full p-3 border border-border rounded-lg bg-background text-sm"
                      />
                    </div>
                  )}
                </div>
              ) : (
                <div className="py-20 text-center opacity-40">
                  <MousePointer2 className="w-10 h-10 mx-auto mb-3" />
                  <p className="text-xs font-bold">Select a block to edit its properties</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default EmailDesigner;
