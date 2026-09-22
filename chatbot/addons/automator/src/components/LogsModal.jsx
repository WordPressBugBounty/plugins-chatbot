"use client";

import React, { useState, useEffect } from "react";
import { X, RefreshCw } from "lucide-react";
import { Button } from "./ui/Button";

const LogsModal = ({ isOpen, onClose, workflowId }) => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      let url = `${window.wpbotAutomator.apiUrl}/logs`;
      if (workflowId) {
        url += `?workflow_id=${workflowId}`;
      }
      
      const response = await fetch(url, {
        headers: {
          "X-WP-Nonce": window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      setLogs(data.logs || (Array.isArray(data) ? data : []));
    } catch (error) {
      console.error("Error fetching logs:", error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      fetchLogs();
    }
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-background w-full max-w-4xl h-[80vh] rounded-lg shadow-lg flex flex-col font-sans">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h2 className="text-lg font-semibold">Execution Logs</h2>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={fetchLogs} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-2 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            <Button variant="ghost" size="icon" onClick={onClose}>
              <X className="h-4 w-4" />
            </Button>
          </div>
        </div>
        
        <div className="flex-1 overflow-auto p-4">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-4 py-2 w-48">Date</th>
                <th className="px-4 py-2">Workflow</th>
                <th className="px-4 py-2 w-32">Type</th>
                <th className="px-4 py-2 w-24">Status</th>
                <th className="px-4 py-2">Message</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {logs.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-4 py-8 text-center text-muted-foreground">
                    {loading ? "Loading logs..." : "No logs found."}
                  </td>
                </tr>
              ) : (
                logs.map((log) => (
                  <tr key={log.id} className="hover:bg-muted/10">
                    <td className="px-4 py-2 text-muted-foreground font-mono text-xs">
                      {log.created_at}
                    </td>
                    <td className="px-4 py-2 font-medium">
                      {log.workflow_name || `Workflow #${log.workflow_id}`}
                    </td>
                    <td className="px-4 py-2">
                      {log.trigger_type}
                    </td>
                    <td className="px-4 py-2">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                        log.status === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                      }`}>
                        {log.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-muted-foreground">
                      {log.error_message || log.log_data || "-"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default LogsModal;
