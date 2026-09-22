import React, { useState, useEffect, useRef } from 'react';

// ── Constants ──────────────────────────────────────────────────────────────

const COL_TYPES = [
  { id: 'text',     label: 'Text',      badge: 'T' },
  { id: 'textarea', label: 'Long Text', badge: '¶' },
  { id: 'number',   label: 'Number',    badge: '#' },
  { id: 'email',    label: 'Email',     badge: '@' },
  { id: 'url',      label: 'URL',       badge: '🔗' },
  { id: 'date',     label: 'Date',      badge: '📅' },
  { id: 'datetime', label: 'Date+Time', badge: '⏱' },
  { id: 'boolean',  label: 'Boolean',   badge: '✓' },
];

const api = (path, opts = {}) =>
  fetch(`${window.wpbotAutomator.apiUrl}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.wpbotAutomator.nonce,
      ...( opts.headers || {} ),
    },
    ...opts,
  }).then(r => r.json());

// ── Root app ────────────────────────────────────────────────────────────────

const TablesApp = () => {
  const [view, setView]           = useState('list');  // 'list' | 'records'
  const [tables, setTables]       = useState([]);
  const [loading, setLoading]     = useState(true);
  const [activeTable, setActive]  = useState(null);
  const [showCreate, setShowCreate] = useState(false);

  const loadTables = async () => {
    setLoading(true);
    try {
      const data = await api('/tables');
      setTables(Array.isArray(data) ? data : []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadTables(); }, []);

  const openRecords = (table) => { setActive(table); setView('records'); };
  const backToList  = () => { setView('list'); setActive(null); loadTables(); };

  const handleDelete = async (table) => {
    if (!window.confirm(`Delete table "${table.name}" and all its data? This cannot be undone.`)) return;
    await api(`/tables/${table.id}`, { method: 'DELETE' });
    loadTables();
  };

  if (view === 'records' && activeTable) {
    return <RecordsManager table={activeTable} onBack={backToList} />;
  }

  return (
    <div className="p-8 bg-background min-h-screen text-foreground">
      <div className="max-w-6xl mx-auto">

        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <div>
            <h1 className="text-2xl font-bold">Tables</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Create and manage database tables. Use <code className="bg-muted px-1 rounded text-xs">[wpbot_table id="X"]</code> to display on any page.
            </p>
          </div>
          <button
            onClick={() => setShowCreate(true)}
            className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90 transition-colors"
          >
            + Create New Table
          </button>
        </div>

        {/* Tables grid */}
        {loading ? (
          <div className="text-center py-12 text-muted-foreground">Loading tables...</div>
        ) : tables.length === 0 ? (
          <div className="text-center py-16 border-2 border-dashed border-border rounded-xl text-muted-foreground">
            <p className="text-lg font-medium mb-2">No tables yet</p>
            <p className="text-sm">Create your first table to start managing data.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {tables.map(t => (
              <TableCard
                key={t.id}
                table={t}
                onOpen={() => openRecords(t)}
                onDelete={() => handleDelete(t)}
              />
            ))}
          </div>
        )}
      </div>

      {showCreate && (
        <CreateTableModal
          onClose={() => setShowCreate(false)}
          onCreated={() => { setShowCreate(false); loadTables(); }}
        />
      )}
    </div>
  );
};

// ── Table card ──────────────────────────────────────────────────────────────

const TableCard = ({ table, onOpen, onDelete }) => (
  <div className="group p-6 bg-card border border-border rounded-xl hover:shadow-md transition-shadow">
    <div className="flex justify-between items-start mb-3">
      <div>
        <h3 className="font-semibold text-lg leading-tight">{table.name}</h3>
        <p className="text-xs text-muted-foreground mt-0.5">
          {table.columns?.length || 0} columns · {table.record_count ?? 0} records
        </p>
      </div>
      <button
        onClick={onDelete}
        className="opacity-0 group-hover:opacity-100 p-1.5 text-rose-500 hover:bg-rose-50 rounded transition-all"
        title="Delete table"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      </button>
    </div>

    {/* Column pills */}
    <div className="flex flex-wrap gap-1 mb-4 min-h-[24px]">
      {(table.columns || []).slice(0, 5).map(col => (
        <span key={col.slug} className="text-[10px] bg-muted text-muted-foreground px-1.5 py-0.5 rounded font-medium">
          {col.name}
        </span>
      ))}
      {(table.columns?.length || 0) > 5 && (
        <span className="text-[10px] text-muted-foreground">+{table.columns.length - 5} more</span>
      )}
    </div>

    {/* Shortcode */}
    <div className="text-[11px] font-mono bg-muted/60 rounded px-2 py-1 text-muted-foreground mb-4 truncate">
      {`[wpbot_table id="${table.id}"]`}
    </div>

    <button
      onClick={onOpen}
      className="w-full py-2 border border-primary/30 text-primary text-sm font-medium rounded-lg hover:bg-primary hover:text-primary-foreground transition-colors"
    >
      Manage Records →
    </button>
  </div>
);

// ── Create table modal ───────────────────────────────────────────────────────

const CreateTableModal = ({ onClose, onCreated }) => {
  const [name, setName]     = useState('');
  const [columns, setCols]  = useState([{ id: 1, name: '', type: 'text', required: false }]);
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState('');
  const nextId = useRef(2);

  const addCol = () => {
    setCols(c => [...c, { id: nextId.current++, name: '', type: 'text', required: false }]);
  };

  const removeCol = (id) => setCols(c => c.filter(col => col.id !== id));

  const updateCol = (id, patch) =>
    setCols(c => c.map(col => col.id === id ? { ...col, ...patch } : col));

  const handleSubmit = async () => {
    if (!name.trim()) { setError('Table name is required.'); return; }
    const validCols = columns.filter(c => c.name.trim());
    if (!validCols.length) { setError('At least one column with a name is required.'); return; }

    setError('');
    setSaving(true);
    try {
      const res = await api('/tables', {
        method: 'POST',
        body: JSON.stringify({ name: name.trim(), columns: validCols }),
      });
      if (res.success) { onCreated(); }
      else { setError(res.message || 'Failed to create table.'); }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b border-border">
          <h2 className="text-lg font-semibold">Create New Table</h2>
          <button onClick={onClose} className="p-1.5 hover:bg-muted rounded-lg transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        {/* Body */}
        <div className="p-6 overflow-y-auto flex-1 space-y-5">
          {/* Table name */}
          <div>
            <label className="block text-sm font-medium mb-1.5">Table Name</label>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="e.g. Customers, Products, Contacts"
              className="w-full border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/30"
            />
          </div>

          {/* Columns */}
          <div>
            <label className="block text-sm font-medium mb-2">Columns</label>
            <div className="space-y-2">
              {columns.map(col => (
                <div key={col.id} className="flex items-center gap-2">
                  <input
                    type="text"
                    value={col.name}
                    onChange={e => updateCol(col.id, { name: e.target.value })}
                    placeholder="Column name"
                    className="flex-1 border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none"
                  />
                  <select
                    value={col.type}
                    onChange={e => updateCol(col.id, { type: e.target.value })}
                    className="border border-border rounded-lg px-2 py-2 text-sm bg-background focus:outline-none"
                  >
                    {COL_TYPES.map(t => (
                      <option key={t.id} value={t.id}>{t.label}</option>
                    ))}
                  </select>
                  <label className="flex items-center gap-1.5 text-xs text-muted-foreground whitespace-nowrap cursor-pointer">
                    <input
                      type="checkbox"
                      checked={col.required}
                      onChange={e => updateCol(col.id, { required: e.target.checked })}
                      className="rounded"
                    />
                    Required
                  </label>
                  <button
                    onClick={() => removeCol(col.id)}
                    disabled={columns.length === 1}
                    className="p-1.5 text-muted-foreground hover:text-rose-500 hover:bg-rose-50 rounded transition-colors disabled:opacity-30"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                </div>
              ))}
            </div>
            <button
              onClick={addCol}
              className="mt-3 text-sm text-primary hover:text-primary/80 font-medium flex items-center gap-1"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Column
            </button>
          </div>

          {error && <p className="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">{error}</p>}
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-3 p-6 border-t border-border">
          <button onClick={onClose} className="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted transition-colors">
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 transition-colors disabled:opacity-60"
          >
            {saving ? 'Creating…' : 'Create Table'}
          </button>
        </div>
      </div>
    </div>
  );
};

// ── Records manager ──────────────────────────────────────────────────────────

const RecordsManager = ({ table, onBack }) => {
  const [records, setRecords]     = useState([]);
  const [total, setTotal]         = useState(0);
  const [page, setPage]           = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading]     = useState(true);
  const [filterCol, setFilterCol] = useState('');
  const [filterVal, setFilterVal] = useState('');
  const [appliedFilter, setApplied] = useState({ col: '', val: '' });
  const [showAddRow, setShowAdd]  = useState(false);
  const [editRow, setEditRow]     = useState(null);
  const [showImport, setShowImport] = useState(false);
  const perPage = 50;

  const loadRecords = async (pg = 1, fCol = appliedFilter.col, fVal = appliedFilter.val) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: pg, per_page: perPage });
      if (fCol && fVal) { params.set('filter_col', fCol); params.set('filter_val', fVal); }
      const data = await api(`/tables/${table.id}/records?${params}`);
      setRecords(data.records || []);
      setTotal(data.total || 0);
      setTotalPages(data.total_pages || 1);
      setPage(pg);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadRecords(1); }, []);

  const applyFilter = () => {
    setApplied({ col: filterCol, val: filterVal });
    loadRecords(1, filterCol, filterVal);
  };

  const clearFilter = () => {
    setFilterCol(''); setFilterVal('');
    setApplied({ col: '', val: '' });
    loadRecords(1, '', '');
  };

  const handleDelete = async (rowId) => {
    if (!window.confirm('Delete this record?')) return;
    await api(`/tables/${table.id}/records/${rowId}`, { method: 'DELETE' });
    loadRecords(page);
  };

  const handleExport = async () => {
    const data = await api(`/tables/${table.id}/export`);
    if (!data.csv) return;
    const blob = new Blob([data.csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = data.filename || 'export.csv'; a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="p-8 bg-background min-h-screen text-foreground">
      <div className="max-w-7xl mx-auto">

        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <button onClick={onBack} className="p-2 hover:bg-muted rounded-lg transition-colors text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div>
              <h1 className="text-xl font-bold">{table.name}</h1>
              <p className="text-xs text-muted-foreground">
                {total} records · <code className="bg-muted px-1 rounded">{`[wpbot_table id="${table.id}"]`}</code>
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={() => setShowImport(true)} className="flex items-center gap-1.5 px-3 py-2 border border-border text-sm rounded-lg hover:bg-muted transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Import CSV
            </button>
            <button onClick={handleExport} className="flex items-center gap-1.5 px-3 py-2 border border-border text-sm rounded-lg hover:bg-muted transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export CSV
            </button>
            <button onClick={() => setShowAdd(true)} className="px-3 py-2 bg-primary text-primary-foreground text-sm rounded-lg hover:bg-primary/90 transition-colors">
              + Add Record
            </button>
          </div>
        </div>

        {/* Filters */}
        <div className="flex items-center gap-3 mb-5 p-4 bg-muted/30 border border-border rounded-xl">
          <span className="text-sm font-medium text-muted-foreground shrink-0">Filter by:</span>
          <select
            value={filterCol}
            onChange={e => setFilterCol(e.target.value)}
            className="border border-border rounded-lg px-2.5 py-1.5 text-sm bg-background focus:outline-none"
          >
            <option value="">— Column —</option>
            {table.columns?.map(col => (
              <option key={col.slug} value={col.slug}>{col.name}</option>
            ))}
          </select>
          <input
            type="text"
            value={filterVal}
            onChange={e => setFilterVal(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && applyFilter()}
            placeholder="Filter value…"
            className="border border-border rounded-lg px-3 py-1.5 text-sm bg-background focus:outline-none w-48"
          />
          <button onClick={applyFilter} className="px-3 py-1.5 bg-primary text-primary-foreground text-sm rounded-lg hover:bg-primary/90 transition-colors">
            Apply
          </button>
          {(appliedFilter.col || appliedFilter.val) && (
            <button onClick={clearFilter} className="px-3 py-1.5 border border-border text-sm rounded-lg hover:bg-muted transition-colors">
              Clear
            </button>
          )}
          <span className="ml-auto text-xs text-muted-foreground">{total} records</span>
        </div>

        {/* Table */}
        <div className="border border-border rounded-xl overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-muted/50 border-b border-border">
                  <th className="text-left px-4 py-3 font-medium text-muted-foreground w-12">#</th>
                  {table.columns?.map(col => (
                    <th key={col.slug} className="text-left px-4 py-3 font-medium text-muted-foreground whitespace-nowrap">
                      {col.name}
                      <span className="ml-1 text-[10px] text-muted-foreground/60 font-normal">{col.type}</span>
                    </th>
                  ))}
                  <th className="text-left px-4 py-3 font-medium text-muted-foreground w-24">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={( table.columns?.length || 0) + 2} className="text-center py-10 text-muted-foreground">Loading…</td></tr>
                ) : records.length === 0 ? (
                  <tr><td colSpan={(table.columns?.length || 0) + 2} className="text-center py-10 text-muted-foreground">No records found.</td></tr>
                ) : records.map(rec => (
                  <tr key={rec.id} className="border-b border-border/50 hover:bg-muted/20 transition-colors">
                    <td className="px-4 py-3 text-muted-foreground text-xs">{rec.id}</td>
                    {table.columns?.map(col => (
                      <td key={col.slug} className="px-4 py-3 max-w-[240px]">
                        <span className="truncate block" title={rec[col.slug] ?? ''}>
                          {col.type === 'boolean'
                            ? (rec[col.slug] == 1 ? '✓' : '✗')
                            : rec[col.slug] ?? ''}
                        </span>
                      </td>
                    ))}
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => setEditRow(rec)}
                          className="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition-colors"
                          title="Edit"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button
                          onClick={() => handleDelete(rec.id)}
                          className="p-1.5 text-rose-500 hover:bg-rose-50 rounded transition-colors"
                          title="Delete"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex justify-center items-center gap-2 mt-5">
            <button disabled={page <= 1} onClick={() => loadRecords(page - 1)} className="px-3 py-1.5 border border-border rounded-lg text-sm disabled:opacity-40 hover:bg-muted transition-colors">
              ← Prev
            </button>
            <span className="text-sm text-muted-foreground">Page {page} of {totalPages}</span>
            <button disabled={page >= totalPages} onClick={() => loadRecords(page + 1)} className="px-3 py-1.5 border border-border rounded-lg text-sm disabled:opacity-40 hover:bg-muted transition-colors">
              Next →
            </button>
          </div>
        )}
      </div>

      {/* Add / Edit record modal */}
      {(showAddRow || editRow) && (
        <RecordModal
          table={table}
          record={editRow}
          onClose={() => { setShowAdd(false); setEditRow(null); }}
          onSaved={() => { setShowAdd(false); setEditRow(null); loadRecords(page); }}
        />
      )}

      {/* CSV import modal */}
      {showImport && (
        <CsvImportModal
          table={table}
          onClose={() => setShowImport(false)}
          onImported={() => { setShowImport(false); loadRecords(1); }}
        />
      )}
    </div>
  );
};

// ── Record add / edit modal ──────────────────────────────────────────────────

const RecordModal = ({ table, record, onClose, onSaved }) => {
  const isEdit  = !!record;
  const initial = {};
  table.columns?.forEach(col => {
    initial[col.slug] = isEdit ? (record[col.slug] ?? '') : (col.type === 'boolean' ? false : '');
  });

  const [form, setForm]     = useState(initial);
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState('');

  const set = (slug, val) => setForm(f => ({ ...f, [slug]: val }));

  const handleSave = async () => {
    setSaving(true); setError('');
    try {
      const url    = isEdit ? `/tables/${table.id}/records/${record.id}` : `/tables/${table.id}/records`;
      const method = isEdit ? 'PUT' : 'POST';
      const res    = await api(url, { method, body: JSON.stringify({ data: form }) });
      if (res.success) { onSaved(); }
      else { setError(res.message || 'Failed to save record.'); }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col">
        <div className="flex justify-between items-center p-6 border-b border-border">
          <h2 className="text-base font-semibold">{isEdit ? 'Edit Record' : 'Add Record'}</h2>
          <button onClick={onClose} className="p-1.5 hover:bg-muted rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div className="p-6 overflow-y-auto flex-1 space-y-4">
          {table.columns?.map(col => (
            <div key={col.slug}>
              <label className="block text-sm font-medium mb-1">
                {col.name}
                {col.required && <span className="text-rose-500 ml-0.5">*</span>}
                <span className="ml-1.5 text-[10px] text-muted-foreground font-normal">{col.type}</span>
              </label>
              {col.type === 'boolean' ? (
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={!!form[col.slug]}
                    onChange={e => set(col.slug, e.target.checked)}
                    className="w-4 h-4 rounded"
                  />
                  <span className="text-sm">Yes</span>
                </label>
              ) : col.type === 'textarea' ? (
                <textarea
                  value={form[col.slug]}
                  onChange={e => set(col.slug, e.target.value)}
                  rows={3}
                  className="w-full border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none resize-none"
                />
              ) : (
                <input
                  type={col.type === 'number' ? 'number' : col.type === 'date' ? 'date' : col.type === 'datetime' ? 'datetime-local' : col.type === 'email' ? 'email' : col.type === 'url' ? 'url' : 'text'}
                  value={form[col.slug]}
                  onChange={e => set(col.slug, e.target.value)}
                  className="w-full border border-border rounded-lg px-3 py-2 text-sm bg-background focus:outline-none"
                />
              )}
            </div>
          ))}
          {error && <p className="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">{error}</p>}
        </div>
        <div className="flex justify-end gap-3 p-6 border-t border-border">
          <button onClick={onClose} className="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted transition-colors">Cancel</button>
          <button onClick={handleSave} disabled={saving} className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 disabled:opacity-60">
            {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Record'}
          </button>
        </div>
      </div>
    </div>
  );
};

// ── CSV import modal ─────────────────────────────────────────────────────────

const CsvImportModal = ({ table, onClose, onImported }) => {
  const [step, setStep]         = useState('upload'); // 'upload' | 'preview'
  const [csvHeaders, setCsvHeaders] = useState([]);
  const [csvRows, setCsvRows]   = useState([]);
  const [mapping, setMapping]   = useState({});
  const [importing, setImporting] = useState(false);
  const [result, setResult]     = useState(null);
  const [error, setError]       = useState('');
  const fileRef = useRef(null);

  const parseCsv = (text) => {
    const lines = text.trim().split('\n');
    if (!lines.length) return;
    const parse = (line) => {
      const result = []; let cur = ''; let inQ = false;
      for (const ch of line) {
        if (ch === '"') { inQ = !inQ; }
        else if (ch === ',' && !inQ) { result.push(cur); cur = ''; }
        else { cur += ch; }
      }
      result.push(cur);
      return result.map(v => v.trim().replace(/^"|"$/g, ''));
    };
    const headers = parse(lines[0]);
    const rows    = lines.slice(1).map(parse).filter(r => r.some(v => v));
    setCsvHeaders(headers);
    setCsvRows(rows);

    // Auto-map headers to column slugs by name.
    const colByName = {};
    table.columns?.forEach(col => {
      colByName[col.name.toLowerCase()] = col.slug;
      colByName[col.slug.toLowerCase()] = col.slug;
    });
    const autoMap = {};
    headers.forEach((h, i) => {
      const match = colByName[h.toLowerCase()];
      if (match) autoMap[i] = match;
    });
    setMapping(autoMap);
    setStep('preview');
  };

  const handleFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => parseCsv(ev.target.result);
    reader.readAsText(file);
    e.target.value = '';
  };

  const handleImport = async () => {
    setImporting(true); setError('');
    try {
      const headers = csvHeaders.map((h, i) => mapping[i] || null);
      const filteredRows = csvRows.map(row => row.map((v, i) => mapping[i] !== undefined ? v : null));
      const res = await api(`/tables/${table.id}/import`, {
        method: 'POST',
        body: JSON.stringify({ headers: csvHeaders, rows: csvRows }),
      });
      if (res.success) { setResult(res.imported); }
      else { setError(res.message || 'Import failed.'); }
    } finally {
      setImporting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div className="flex justify-between items-center p-6 border-b border-border">
          <h2 className="text-base font-semibold">Import CSV</h2>
          <button onClick={onClose} className="p-1.5 hover:bg-muted rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>

        <div className="p-6 overflow-y-auto flex-1">
          {result !== null ? (
            <div className="text-center py-8">
              <div className="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <p className="text-lg font-semibold">Import Complete</p>
              <p className="text-muted-foreground mt-1">{result} records imported successfully.</p>
              <button onClick={onImported} className="mt-5 px-5 py-2 bg-primary text-primary-foreground rounded-lg text-sm font-medium hover:bg-primary/90">Done</button>
            </div>
          ) : step === 'upload' ? (
            <div
              onClick={() => fileRef.current?.click()}
              className="border-2 border-dashed border-border rounded-xl p-12 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="mx-auto mb-3 text-muted-foreground"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p className="font-medium">Click to select a CSV file</p>
              <p className="text-sm text-muted-foreground mt-1">The first row must contain column headers.</p>
              <input ref={fileRef} type="file" accept=".csv,text/csv" style={{ display: 'none' }} onChange={handleFile} />
            </div>
          ) : (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium">{csvRows.length} rows · {csvHeaders.length} columns detected</p>
                <button onClick={() => setStep('upload')} className="text-xs text-muted-foreground hover:text-foreground">← Change file</button>
              </div>

              {/* Column mapping */}
              <div>
                <p className="text-sm font-medium mb-2">Column Mapping</p>
                <div className="space-y-2">
                  {csvHeaders.map((h, i) => (
                    <div key={i} className="flex items-center gap-3 text-sm">
                      <span className="w-36 truncate font-mono text-xs bg-muted px-2 py-1 rounded">{h}</span>
                      <span className="text-muted-foreground text-xs">→</span>
                      <select
                        value={mapping[i] || ''}
                        onChange={e => setMapping(m => ({ ...m, [i]: e.target.value || undefined }))}
                        className="flex-1 border border-border rounded-lg px-2 py-1 text-sm bg-background focus:outline-none"
                      >
                        <option value="">— skip column —</option>
                        {table.columns?.map(col => (
                          <option key={col.slug} value={col.slug}>{col.name}</option>
                        ))}
                      </select>
                    </div>
                  ))}
                </div>
              </div>

              {/* Preview rows */}
              <div>
                <p className="text-sm font-medium mb-2">Preview (first 3 rows)</p>
                <div className="overflow-x-auto border border-border rounded-lg">
                  <table className="w-full text-xs">
                    <thead><tr className="bg-muted/50">{csvHeaders.map((h, i) => <th key={i} className="text-left px-3 py-2 font-medium">{h}</th>)}</tr></thead>
                    <tbody>{csvRows.slice(0, 3).map((row, ri) => (<tr key={ri} className="border-t border-border">{row.map((v, ci) => <td key={ci} className="px-3 py-2 max-w-[120px] truncate">{v}</td>)}</tr>))}</tbody>
                  </table>
                </div>
              </div>

              {error && <p className="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">{error}</p>}
            </div>
          )}
        </div>

        {step === 'preview' && result === null && (
          <div className="flex justify-end gap-3 p-6 border-t border-border">
            <button onClick={onClose} className="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted transition-colors">Cancel</button>
            <button
              onClick={handleImport}
              disabled={importing || Object.keys(mapping).length === 0}
              className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 disabled:opacity-60"
            >
              {importing ? 'Importing…' : `Import ${csvRows.length} Rows`}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default TablesApp;
