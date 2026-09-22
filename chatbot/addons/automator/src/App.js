import React, { useState, useEffect, useRef } from 'react';
import Creator from './components/Creator';
import SupportModal from './components/SupportModal';
import TemplatesModal from './components/TemplatesModal';

const App = () => {
  const [view, setView] = useState('list');
  const [workflows, setWorkflows] = useState([]);
  const [selectedWorkflow, setSelectedWorkflow] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isSupportOpen, setIsSupportOpen] = useState(false);
  const [isTemplatesModalOpen, setIsTemplatesModalOpen] = useState(false);
  const [importStatus, setImportStatus] = useState(null); // { type: 'success'|'error', message: '' }
  const importFileRef = useRef(null);

  useEffect(() => {
    fetchWorkflows();
  }, []);

  const fetchWorkflows = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/workflows`, {
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      setWorkflows(data || []);
    } catch (error) {
      console.error('Error fetching workflows:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateNew = () => {
    setIsTemplatesModalOpen(true);
  };

  const handleTemplateSelect = (template) => {
    setIsTemplatesModalOpen(false);
    if (template) {
      // Create a dummy workflow object from template
      setSelectedWorkflow({
        id: null,
        name: template.name,
        description: template.description,
        workflow_data: template.workflow_data,
        status: 'active'
      });
    } else {
      setSelectedWorkflow(null);
    }
    setView('creator');
  };

  const handleEdit = (workflow) => {
    setSelectedWorkflow(workflow);
    setView('creator');
  };

  const handleDelete = async (e, id) => {
    e.stopPropagation();
    if (!window.confirm('Are you sure you want to delete this workflow?')) {
      return;
    }

    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/workflows/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      if (data.success) {
        fetchWorkflows();
      } else {
        alert('Failed to delete workflow.');
      }
    } catch (error) {
      console.error('Error deleting workflow:', error);
    }
  };

  const handleToggleStatus = async (e, workflow) => {
    e.stopPropagation();
    const newStatus = workflow.status === 'active' ? 'inactive' : 'active';
    
    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/workflows/${workflow.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify({
          ...workflow,
          status: newStatus,
        }),
      });
      const data = await response.json();
      if (data.success) {
        fetchWorkflows();
      } else {
        alert('Failed to update workflow status.');
      }
    } catch (error) {
      console.error('Error updating workflow status:', error);
    }
  };

  const handleBack = () => {
    setView('list');
    fetchWorkflows();
  };

  // ── Export helpers ────────────────────────────────────────────────────────

  /**
   * Trigger a JSON file download from a plain-object payload.
   */
  const downloadJson = (data, filename) => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  };

  /**
   * Export a single workflow by ID.
   */
  const handleExportSingle = async (e, workflow) => {
    e.stopPropagation();
    try {
      const response = await fetch(
        `${window.wpbotAutomator.apiUrl}/workflows/export?id=${workflow.id}`,
        { headers: { 'X-WP-Nonce': window.wpbotAutomator.nonce } }
      );
      const data = await response.json();
      const safeName = workflow.name.replace(/[^a-z0-9_-]/gi, '_').toLowerCase();
      downloadJson(data, `wpbot-workflow-${safeName}.json`);
    } catch (error) {
      console.error('Error exporting workflow:', error);
      alert('Failed to export workflow.');
    }
  };

  /**
   * Export all workflows.
   */
  const handleExportAll = async () => {
    try {
      const response = await fetch(
        `${window.wpbotAutomator.apiUrl}/workflows/export`,
        { headers: { 'X-WP-Nonce': window.wpbotAutomator.nonce } }
      );
      const data = await response.json();
      downloadJson(data, 'wpbot-workflows-all.json');
    } catch (error) {
      console.error('Error exporting all workflows:', error);
      alert('Failed to export workflows.');
    }
  };

  // ── Import helpers ────────────────────────────────────────────────────────

  const handleImportClick = () => {
    setImportStatus(null);
    importFileRef.current && importFileRef.current.click();
  };

  const handleImportFile = (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (evt) => {
      let parsed;
      try {
        parsed = JSON.parse(evt.target.result);
      } catch {
        setImportStatus({ type: 'error', message: 'Invalid JSON file. Please select a valid export file.' });
        return;
      }

      if (!parsed.workflows || !Array.isArray(parsed.workflows)) {
        setImportStatus({ type: 'error', message: 'Invalid file format. Expected a "workflows" array.' });
        return;
      }

      try {
        const response = await fetch(`${window.wpbotAutomator.apiUrl}/workflows/import`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpbotAutomator.nonce,
          },
          body: JSON.stringify({ workflows: parsed.workflows }),
        });
        const data = await response.json();

        if (data.success || data.imported > 0) {
          const msg = `Successfully imported ${data.imported} workflow${data.imported !== 1 ? 's' : ''}.`
            + (data.errors && data.errors.length ? ` (${data.errors.length} skipped)` : '');
          setImportStatus({ type: 'success', message: msg });
          fetchWorkflows();
        } else {
          const errMsg = (data.errors && data.errors.join(' ')) || 'Import failed. No workflows were imported.';
          setImportStatus({ type: 'error', message: errMsg });
        }
      } catch (error) {
        console.error('Error importing workflows:', error);
        setImportStatus({ type: 'error', message: 'Network error during import. Please try again.' });
      }
    };
    reader.readAsText(file);
    // Reset so the same file can be selected again
    e.target.value = '';
  };

  if (view === 'creator') {
    return <Creator workflow={selectedWorkflow} onBack={handleBack} />;
  }

  return (
    <div className="p-8 bg-background min-h-screen text-foreground">
      <div className="max-w-6xl mx-auto">

        {/* Import status message */}
        {importStatus && (
          <div className={`rounded-lg p-3 mb-6 flex items-center justify-between text-sm ${
            importStatus.type === 'success'
              ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border border-rose-200 text-rose-800'
          }`}>
            <span>{importStatus.message}</span>
            <button onClick={() => setImportStatus(null)} className="ml-4 opacity-60 hover:opacity-100 transition-opacity">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        )}

        <div className="flex justify-between items-center mb-8">
          <h1 className="text-2xl font-bold">Workflows</h1>
          <div className="flex items-center space-x-2">
            {/* Hidden file input for import */}
            <input
              ref={importFileRef}
              type="file"
              accept=".json,application/json"
              style={{ display: 'none' }}
              onChange={handleImportFile}
            />
            {/* Import button */}
            <button
              onClick={handleImportClick}
              title="Import workflows from a JSON file"
              className="flex items-center space-x-1.5 px-3 py-2 border border-border text-sm rounded-md hover:bg-muted transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>Import</span>
            </button>
            {/* Export All button — only shown when workflows exist */}
            {workflows.length > 0 && (
              <button
                onClick={handleExportAll}
                title="Export all workflows as JSON"
                className="flex items-center space-x-1.5 px-3 py-2 border border-border text-sm rounded-md hover:bg-muted transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export All</span>
              </button>
            )}
            {/* Create New button */}
            <button
              onClick={handleCreateNew}
              className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
            >
              Create New Workflow
            </button>
          </div>
        </div>

        {loading ? (
          <div className="text-center py-12 text-muted-foreground">Loading workflows...</div>
        ) : workflows.length === 0 ? (
          <div className="text-center py-12 border-2 border-dashed border-border rounded-lg text-muted-foreground">
            No workflows found. Create your first one!
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {workflows.map((workflow) => (
              <div
                key={workflow.id}
                className="group relative p-6 bg-card border border-border rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                onClick={() => handleEdit(workflow)}
              >
                <div className="flex justify-between items-start mb-2">
                  <h3 className="text-lg font-semibold">{workflow.name}</h3>
                  <div className="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {/* Toggle active/inactive */}
                    <button
                      onClick={(e) => handleToggleStatus(e, workflow)}
                      title={workflow.status === 'active' ? 'Deactivate' : 'Activate'}
                      className={`p-1.5 rounded-md transition-colors ${
                        workflow.status === 'active' 
                          ? 'text-amber-600 hover:bg-amber-50' 
                          : 'text-emerald-600 hover:bg-emerald-50'
                      }`}
                    >
                      {workflow.status === 'active' ? (
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                      ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                      )}
                    </button>
                    {/* Export single workflow */}
                    <button
                      onClick={(e) => handleExportSingle(e, workflow)}
                      title="Export workflow as JSON"
                      className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </button>
                    {/* Delete */}
                    <button
                      onClick={(e) => handleDelete(e, workflow.id)}
                      title="Delete"
                      className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                  </div>
                </div>
                <p className="text-sm text-muted-foreground mb-4 line-clamp-2">
                  {workflow.description || 'No description'}
                </p>
                <div className="flex justify-between items-center mt-auto">
                  <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                    workflow.status === 'active' 
                      ? 'bg-emerald-100/80 text-emerald-700' 
                      : 'bg-slate-100/80 text-slate-700'
                  }`}>
                    {workflow.status.charAt(0).toUpperCase() + workflow.status.slice(1)}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    ID: {workflow.id}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
      <SupportModal isOpen={isSupportOpen} onClose={() => setIsSupportOpen(false)} />
      <TemplatesModal
        isOpen={isTemplatesModalOpen}
        onClose={() => setIsTemplatesModalOpen(false)}
        onSelect={handleTemplateSelect}
        isProActive={!!window.wpbotAutomator?.isProActive}
      />
    </div>
  );
};

export default App;
