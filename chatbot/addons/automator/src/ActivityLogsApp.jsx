import React, { useState, useEffect, useRef } from 'react';
import {
  RefreshCw, Trash2, CheckCircle, XCircle, Clock,
  Filter, ChevronDown, ChevronRight, Activity
} from 'lucide-react';

const PER_PAGE = 25;

const ActivityLogsApp = () => {
  const [logs, setLogs] = useState([]);
  const [summary, setSummary] = useState({ total: 0, success: 0, failed: 0, last_run: null });
  const [workflows, setWorkflows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ status: '', workflow_id: '' });
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [expandedRow, setExpandedRow] = useState(null);
  const [autoRefresh, setAutoRefresh] = useState(false);

  const pageRef    = useRef(page);
  const filtersRef = useRef(filters);
  const intervalRef = useRef(null);
  useEffect(() => { pageRef.current = page; }, [page]);
  useEffect(() => { filtersRef.current = filters; }, [filters]);

  const apiUrl = () => window.wpbotAutomator.apiUrl.replace(/\/$/, '');
  const headers = () => ({ 'X-WP-Nonce': window.wpbotAutomator.nonce });

  const fetchSummary = async () => {
    try {
      const res = await fetch(`${apiUrl()}/logs/summary`, { headers: headers() });
      const data = await res.json();
      setSummary(data);
    } catch (e) { console.error(e); }
  };

  const fetchWorkflows = async () => {
    try {
      const res = await fetch(`${apiUrl()}/workflows`, { headers: headers() });
      const data = await res.json();
      setWorkflows(Array.isArray(data) ? data : []);
    } catch (e) { console.error(e); }
  };

  const fetchLogs = async (currentPage, currentFilters) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ per_page: PER_PAGE, page: currentPage });
      if (currentFilters.status)      params.set('status', currentFilters.status);
      if (currentFilters.workflow_id) params.set('workflow_id', currentFilters.workflow_id);

      const res = await fetch(`${apiUrl()}/logs?${params}`, { headers: headers() });
      const data = await res.json();
      setLogs(data.logs || []);
      setTotal(data.total || 0);
      setTotalPages(data.total_pages || 1);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchWorkflows();
    fetchSummary();
    fetchLogs(1, { status: '', workflow_id: '' });
  }, []);

  useEffect(() => {
    clearInterval(intervalRef.current);
    if (!autoRefresh) return;
    intervalRef.current = setInterval(() => {
      fetchSummary();
      fetchLogs(pageRef.current, filtersRef.current);
    }, 10000);
    return () => clearInterval(intervalRef.current);
  }, [autoRefresh]);

  const applyFilter = (key, value) => {
    const next = { ...filtersRef.current, [key]: value };
    setFilters(next);
    setPage(1);
    fetchSummary();
    fetchLogs(1, next);
  };

  const clearFilters = () => {
    const next = { status: '', workflow_id: '' };
    setFilters(next);
    setPage(1);
    fetchLogs(1, next);
  };

  const goToPage = (p) => {
    setPage(p);
    fetchLogs(p, filtersRef.current);
  };

  const handleRefresh = () => {
    fetchSummary();
    fetchLogs(pageRef.current, filtersRef.current);
  };

  const handleClearLogs = async () => {
    if (!window.confirm('Clear all logs? This cannot be undone.')) return;
    await fetch(`${apiUrl()}/logs`, { method: 'DELETE', headers: headers() });
    setSummary({ total: 0, success: 0, failed: 0, last_run: null });
    setLogs([]);
    setTotal(0);
    setTotalPages(1);
    setPage(1);
  };

  const formatRelative = (datetime) => {
    if (!datetime) return '-';
    const d = new Date(datetime.replace(' ', 'T') + 'Z');
    const diff = Math.floor((Date.now() - d) / 1000);
    if (diff < 60)    return `${diff}s ago`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return d.toLocaleDateString();
  };

  const formatAbsolute = (datetime) => {
    if (!datetime) return '';
    return new Date(datetime.replace(' ', 'T') + 'Z').toLocaleString();
  };

  const humanize = (str) => {
    if (!str) return '-';
    return str.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  };

  const formatTrigger = (trigger) => {
    if (!trigger) return '-';
    const parts = trigger.split(':');
    return humanize(parts[parts.length - 1]);
  };

  const parseLogData = (raw) => {
    if (!raw) return null;
    try { return JSON.parse(raw); } catch { return raw; }
  };

  const successRate = summary.total > 0
    ? Math.round((summary.success / summary.total) * 100)
    : null;

  const hasFilters = filters.status || filters.workflow_id;

  return (
    <div className="p-8 bg-background min-h-screen text-foreground font-sans">
      <div className="max-w-7xl mx-auto">

        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold tracking-tight flex items-center gap-3">
              <Activity className="w-8 h-8 text-primary" />
              Activity Log
            </h1>
            <p className="text-muted-foreground mt-1">Real-time view of every workflow execution — what ran, what succeeded, and what failed.</p>
          </div>
          <div className="flex items-center gap-3">
            {/* Auto-refresh toggle */}
            <label className="flex items-center gap-2 text-sm text-muted-foreground cursor-pointer select-none">
              <div
                onClick={() => setAutoRefresh(v => !v)}
                className={`w-9 h-5 rounded-full relative cursor-pointer transition-colors ${autoRefresh ? 'bg-primary' : 'bg-border'}`}
              >
                <div className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${autoRefresh ? 'translate-x-4' : 'translate-x-0.5'}`} />
              </div>
              Auto-refresh
            </label>
            <button
              onClick={handleRefresh}
              disabled={loading}
              className="flex items-center gap-2 px-3 py-2 border border-border rounded-lg hover:bg-muted transition-colors text-sm"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
              Refresh
            </button>
            <button
              onClick={handleClearLogs}
              className="flex items-center gap-2 px-3 py-2 border border-rose-200 text-rose-600 rounded-lg hover:bg-rose-50 transition-colors text-sm"
            >
              <Trash2 className="w-4 h-4" />
              Clear All
            </button>
          </div>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-card border border-border rounded-xl p-5">
            <div className="text-sm text-muted-foreground mb-1">Total Runs</div>
            <div className="text-3xl font-bold">{summary.total.toLocaleString()}</div>
            <div className="text-xs text-muted-foreground mt-2">
              {summary.last_run ? `Last run: ${formatRelative(summary.last_run)}` : 'No runs yet'}
            </div>
          </div>
          <div className="bg-card border border-emerald-200 rounded-xl p-5">
            <div className="text-sm text-emerald-600 mb-1 font-medium">Successful</div>
            <div className="text-3xl font-bold text-emerald-600">{summary.success.toLocaleString()}</div>
            <div className="text-xs text-muted-foreground mt-2">
              {successRate !== null ? `${successRate}% success rate` : 'No data yet'}
            </div>
          </div>
          <div className="bg-card border border-rose-200 rounded-xl p-5">
            <div className="text-sm text-rose-600 mb-1 font-medium">Failed</div>
            <div className="text-3xl font-bold text-rose-600">{summary.failed.toLocaleString()}</div>
            <div className="text-xs text-muted-foreground mt-2">
              {successRate !== null ? `${100 - successRate}% failure rate` : 'No data yet'}
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="flex items-center gap-3 mb-4 flex-wrap">
          <Filter className="w-4 h-4 text-muted-foreground shrink-0" />
          <select
            value={filters.status}
            onChange={e => applyFilter('status', e.target.value)}
            className="border border-border rounded-lg px-3 py-1.5 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Statuses</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
          </select>
          <select
            value={filters.workflow_id}
            onChange={e => applyFilter('workflow_id', e.target.value)}
            className="border border-border rounded-lg px-3 py-1.5 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Workflows</option>
            {workflows.map(w => (
              <option key={w.id} value={w.id}>{w.name}</option>
            ))}
          </select>
          {hasFilters && (
            <button
              onClick={clearFilters}
              className="text-xs text-primary hover:underline"
            >
              Clear filters
            </button>
          )}
          <span className="ml-auto text-sm text-muted-foreground">
            {total.toLocaleString()} {total === 1 ? 'entry' : 'entries'}
          </span>
        </div>

        {/* Table */}
        <div className="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
          {loading && logs.length === 0 ? (
            <div className="py-24 text-center text-muted-foreground">
              <RefreshCw className="w-8 h-8 mx-auto mb-3 animate-spin opacity-40" />
              Loading logs…
            </div>
          ) : logs.length === 0 ? (
            <div className="py-24 text-center">
              <Clock className="w-12 h-12 mx-auto mb-4 text-muted-foreground opacity-25" />
              <p className="font-medium text-muted-foreground">No logs found{hasFilters ? ' for this filter' : ''}</p>
              <p className="text-xs text-muted-foreground mt-1">
                {hasFilters ? 'Try clearing the filters.' : 'Logs appear here automatically when your workflows run.'}
              </p>
            </div>
          ) : (
            <>
              <table className="w-full text-sm">
                <thead className="bg-muted/40 border-b border-border">
                  <tr>
                    <th className="w-6 px-3 py-3" />
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Time</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Workflow</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Trigger</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Action</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Status</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wide">Message</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {logs.map((log) => {
                    const expanded = expandedRow === log.id;
                    const parsed   = parseLogData(log.log_data);
                    const isFailed = log.status !== 'success';

                    return (
                      <React.Fragment key={log.id}>
                        <tr
                          onClick={() => setExpandedRow(expanded ? null : log.id)}
                          className={`cursor-pointer transition-colors hover:bg-muted/20 ${expanded ? 'bg-muted/10' : ''} ${isFailed ? 'border-l-2 border-l-rose-400' : ''}`}
                        >
                          <td className="px-3 py-3 text-muted-foreground">
                            {expanded
                              ? <ChevronDown className="w-3.5 h-3.5" />
                              : <ChevronRight className="w-3.5 h-3.5" />}
                          </td>
                          <td className="px-4 py-3">
                            <span
                              className="text-xs font-mono text-muted-foreground"
                              title={formatAbsolute(log.created_at)}
                            >
                              {formatRelative(log.created_at)}
                            </span>
                          </td>
                          <td className="px-4 py-3 font-medium max-w-[160px] truncate">
                            {log.workflow_name || `Workflow #${log.workflow_id}`}
                          </td>
                          <td className="px-4 py-3">
                            <span className="bg-muted/70 text-muted-foreground px-2 py-0.5 rounded text-xs font-mono">
                              {formatTrigger(log.trigger_type)}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            {log.action_id ? (
                              <span className="bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded text-xs">
                                {humanize(log.action_id)}
                              </span>
                            ) : (
                              <span className="text-muted-foreground text-xs">—</span>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            {isFailed ? (
                              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700">
                                <XCircle className="w-3 h-3" /> Failed
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                <CheckCircle className="w-3 h-3" /> Success
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3 text-muted-foreground text-xs max-w-[220px] truncate">
                            {log.error_message || (parsed && parsed.message) || '—'}
                          </td>
                        </tr>

                        {expanded && (
                          <tr className="bg-muted/5">
                            <td colSpan="7" className="px-6 py-4">
                              <div className="space-y-3 max-w-4xl">
                                {/* Meta row */}
                                <div className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                                  <span><strong className="text-foreground">Log ID:</strong> {log.id}</span>
                                  <span><strong className="text-foreground">Time:</strong> {formatAbsolute(log.created_at)}</span>
                                  <span><strong className="text-foreground">Workflow:</strong> {log.workflow_name || `#${log.workflow_id}`}</span>
                                  <span><strong className="text-foreground">Trigger:</strong> {log.trigger_type || '—'}</span>
                                  {log.action_id && <span><strong className="text-foreground">Action:</strong> {log.action_id}</span>}
                                </div>

                                {/* Error box */}
                                {log.error_message && (
                                  <div className="bg-rose-50 border border-rose-200 rounded-lg p-3">
                                    <div className="text-xs font-bold text-rose-700 mb-1">Error</div>
                                    <div className="text-xs text-rose-600 font-mono break-all">{log.error_message}</div>
                                  </div>
                                )}

                                {/* Result data */}
                                {parsed && (
                                  <div className="bg-muted/50 border border-border rounded-lg p-3">
                                    <div className="text-xs font-bold text-muted-foreground mb-2">Result Data</div>
                                    <pre className="text-xs font-mono text-foreground/80 overflow-auto max-h-48 whitespace-pre-wrap break-all">
                                      {typeof parsed === 'object'
                                        ? JSON.stringify(parsed, null, 2)
                                        : parsed}
                                    </pre>
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="flex items-center justify-between px-4 py-3 border-t border-border">
                  <div className="text-xs text-muted-foreground">
                    Page {page} of {totalPages} &mdash; {total.toLocaleString()} total entries
                  </div>
                  <div className="flex items-center gap-1">
                    <button onClick={() => goToPage(1)} disabled={page === 1}
                      className="px-2 py-1 text-xs border border-border rounded hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed">«</button>
                    <button onClick={() => goToPage(page - 1)} disabled={page === 1}
                      className="px-2 py-1 text-xs border border-border rounded hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed">‹</button>
                    {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                      const start = Math.max(1, Math.min(page - 2, totalPages - 4));
                      const p = start + i;
                      return p <= totalPages ? (
                        <button key={p} onClick={() => goToPage(p)}
                          className={`px-2.5 py-1 text-xs border rounded transition-colors ${
                            p === page
                              ? 'bg-primary text-primary-foreground border-primary'
                              : 'border-border hover:bg-muted'
                          }`}
                        >{p}</button>
                      ) : null;
                    })}
                    <button onClick={() => goToPage(page + 1)} disabled={page === totalPages}
                      className="px-2 py-1 text-xs border border-border rounded hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed">›</button>
                    <button onClick={() => goToPage(totalPages)} disabled={page === totalPages}
                      className="px-2 py-1 text-xs border border-border rounded hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed">»</button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default ActivityLogsApp;
