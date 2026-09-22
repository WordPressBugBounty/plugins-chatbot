"use client";

import React, { useState, useEffect } from "react";
import { ChevronRight, Database, Settings, Activity, Search, ArrowLeft, Lock } from "lucide-react";
import Modal from "./ui/Modal";

export default function NodeConfigModal({ node, isOpen, onClose, onUpdateNode }) {
  const [step, setStep] = useState(1);
  const [search, setSearch] = useState("");
  const [selectedAction, setSelectedAction] = useState(null);
  const [fieldValues, setFieldValues] = useState({});
  const [fieldOptions, setFieldOptions] = useState({});
  const [loadingOptions, setLoadingOptions] = useState({});
  const [isTesting, setIsTesting] = useState(false);
  const [testResponse, setTestResponse] = useState(null);

  const [credentials, setCredentials] = useState([]);
  const [isLoadingCredentials, setIsLoadingCredentials] = useState(false);
  const [isAddingCredential, setIsAddingCredential] = useState(false);
  const [newCredential, setNewCredential] = useState({ name: "", data: {} });
  const [isSavingCredential, setIsSavingCredential] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setStep(1);
      setSearch("");
      setTestResponse(null);
      if (node?.data?.actionId) {
        // Find action in metadata based on node.data.actionId
        let foundAction = null;
        if (node.appData?.categories) {
          node.appData.categories.forEach(cat => {
            const act = cat.actions.find(a => a.id === node.data.actionId);
            if (act) foundAction = act;
          });
        } else if (node.appData?.actions) {
          foundAction = node.appData.actions.find(a => a.id === node.data.actionId);
        }
        setSelectedAction(foundAction);
        setFieldValues(node.data?.config || {});
      } else {
        setSelectedAction(null);
        setFieldValues({});
      }
    }
  }, [isOpen, node]);

  useEffect(() => {
    if (isOpen && node?.appData?.id) {
      const fetchCredentials = async () => {
        setIsLoadingCredentials(true);
        try {
          const response = await fetch(`${window.wpbotAutomator.apiUrl}/credentials?app_id=${node.appData.id}`, {
            headers: { 'X-WP-Nonce': window.wpbotAutomator.nonce },
          });
          const data = await response.json();
          if (Array.isArray(data)) setCredentials(data);
        } catch (error) {
          console.error("Failed to fetch credentials:", error);
        } finally {
          setIsLoadingCredentials(false);
        }
      };

      setIsAddingCredential(false);
      setNewCredential({ name: "", data: {} });
      fetchCredentials();
    }
  }, [isOpen, node?.appData?.id]);

  const handleSaveCredential = async () => {
    try {
      if (!newCredential.name) {
        alert("Validation Error: Please enter a typical Name for this credential.");
        return;
      }

      setIsSavingCredential(true);
      const payload = {
        name: newCredential.name,
        app_id: node.appData.id,
        data: newCredential.data
      };

      const response = await fetch(`${window.wpbotAutomator.apiUrl}/credentials`, {
        method: "POST",
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.success) {
        setCredentials(prev => [...prev, result.credential]);
        if (selectedAction) {
          const credField = selectedAction.fields?.find(f => f.type === 'credential');
          if (credField) handleFieldChange(credField.id, result.credential.id);
        }
        setIsAddingCredential(false);
        setNewCredential({ name: "", data: {} });
        alert("Credential saved successfully!");
      } else {
        alert("Failed to save credential on server: " + (result.message || JSON.stringify(result)));
      }
    } catch (error) {
      console.error("Error saving credential:", error);
      alert("Error saving credential. Check your browser console: " + error.message);
    } finally {
      setIsSavingCredential(false);
    }
  };

  useEffect(() => {
    if (selectedAction?.fields) {
      selectedAction.fields.forEach(field => {
        if (field.type === 'select' && field.optionsUrl && !fieldOptions[field.id]) {
          fetchOptions(field);
        }
      });
    }
  }, [selectedAction]);

  const fetchOptions = async (field) => {
    setLoadingOptions(prev => ({ ...prev, [field.id]: true }));
    try {
      const baseUrl = window.wpbotAutomator.apiUrl.replace(/\/$/, '');
      const response = await fetch(`${baseUrl}${field.optionsUrl}`, {
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      setFieldOptions(prev => ({ ...prev, [field.id]: Array.isArray(data) ? data : [] }));
    } catch (error) {
      console.error(`Error fetching options for ${field.id}:`, error);
    } finally {
      setLoadingOptions(prev => ({ ...prev, [field.id]: false }));
    }
  };

  if (!node) return null;

  const handleSelectAction = (action) => {
    setSelectedAction(action);
    setStep(2);
  };

  const handleSave = () => {
    onUpdateNode({
      ...node,
      subtitle: selectedAction ? selectedAction.name : "Configure action",
      data: {
        ...node.data,
        actionId: selectedAction?.id,
        config: fieldValues
      }
    });
    onClose();
  };

  const handleFieldChange = (fieldId, value) => {
    setFieldValues(prev => ({ ...prev, [fieldId]: value }));
  };

  const handleTestRun = async () => {
    setIsTesting(true);
    setTestResponse(null);
    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/test-action`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify({
          appId: node.appData.id,
          actionId: selectedAction.id,
          config: fieldValues,
        }),
      });
      const data = await response.json();
      setTestResponse(data);
    } catch (error) {
      console.error("Test Run error:", error);
      setTestResponse({ success: false, message: "Request failed. Check your connection or URL." });
    } finally {
      setIsTesting(false);
    }
  };

  const renderStep1 = () => {
    const categories = node.appData?.categories || (node.appData?.actions ? [{ id: "main", name: "Actions", actions: node.appData.actions }] : []);

    return (
      <div className="space-y-6">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <input
            placeholder="Search actions..."
            className="w-full pl-10 bg-input border border-border rounded-lg py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>

        <div className="space-y-8 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
          {categories.map((category) => {
            const filteredActions = category.actions.filter(a =>
              a.name.toLowerCase().includes(search.toLowerCase())
            );

            if (filteredActions.length === 0) return null;

            return (
              <div key={category.id} className="space-y-3">
                <h4 className="text-xs font-bold text-muted-foreground uppercase tracking-wider px-1">
                  {category.name}
                </h4>
                <div className="grid grid-cols-1 gap-2">
                  {filteredActions.map((action) => {
                    const isLocked = action.isPro && !window.wpbotAutomator.isProActive;

                    return (
                      <button
                        key={action.id}
                        onClick={() => !isLocked && handleSelectAction(action)}
                        className={`flex items-center justify-between p-3 rounded-xl border transition-all text-left group ${isLocked ? "opacity-75 cursor-not-allowed bg-sidebar-accent/30" :
                            selectedAction?.id === action.id
                              ? "bg-primary/10 border-primary text-primary shadow-sm"
                              : "bg-card border-border hover:border-muted-foreground/30 hover:shadow-sm"
                          }`}
                      >
                        <div className="flex items-center gap-3">
                          <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold ${selectedAction?.id === action.id ? "bg-primary text-white" : "bg-sidebar-accent text-muted-foreground group-hover:bg-primary/20 group-hover:text-primary"
                            } transition-colors`}>
                            {isLocked ? <Lock className="w-4 h-4" /> : <Activity className="w-4 h-4" />}
                          </div>
                          <span className="text-sm font-medium">{action.name}</span>
                          {isLocked && (
                            <span className="text-[10px] px-2 py-0.5 bg-amber-500/10 text-amber-500 rounded-full font-bold ml-2">PRO</span>
                          )}
                          {(action.isPro && window.wpbotAutomator.isProActive) && (
                            <span className="text-[10px] px-2 py-0.5 bg-green-500/10 text-green-600 rounded-full font-bold ml-2">PRO</span>
                          )}
                        </div>
                        <ChevronRight className={`w-4 h-4 transition-transform ${selectedAction?.id === action.id ? "translate-x-1" : "text-muted-foreground group-hover:translate-x-1"}`} />
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  // Renders one labeled input per column of the selected table.
  // fieldOptions[tableIdField] is the /tables array already fetched for the table select.
  const TableColumnsMapper = ({ field }) => {
    const tableId  = fieldValues[field.tableIdField] || '';
    const allTables = fieldOptions[field.tableIdField] || [];
    const selected  = allTables.find(t => String(t.id) === String(tableId));
    const columns   = selected?.columns || [];

    let current = {};
    try { current = JSON.parse(fieldValues[field.id] || '{}') || {}; } catch {}

    const update = (slug, val) => {
      const next = { ...current, [slug]: val };
      handleFieldChange(field.id, JSON.stringify(next));
    };

    if (!tableId) return (
      <div className="p-3 rounded-lg bg-muted/40 border border-border text-xs text-muted-foreground text-center">
        Select a table above to see its fields.
      </div>
    );

    if (columns.length === 0) return (
      <div className="p-3 rounded-lg bg-muted/40 border border-border text-xs text-muted-foreground">
        {allTables.length === 0 ? 'Loading table columns…' : 'This table has no columns.'}
      </div>
    );

    return (
      <div className="space-y-2.5">
        {columns.map(col => (
          <div key={col.slug} className="flex items-center gap-2.5">
            <div className="w-28 shrink-0">
              <p className="text-xs font-semibold text-foreground truncate" title={col.name}>
                {col.name}{col.required && <span className="text-rose-500 ml-0.5">*</span>}
              </p>
              <p className="text-[10px] text-muted-foreground/60">{col.type}</p>
            </div>
            <input
              type="text"
              className="flex-1 bg-input border border-border rounded-lg py-2 px-3 text-xs outline-none focus:ring-2 focus:ring-primary/20 transition-all"
              placeholder={`{${col.slug}} or a fixed value`}
              value={current[col.slug] || ''}
              onChange={e => update(col.slug, e.target.value)}
            />
          </div>
        ))}
        <p className="text-[10px] text-muted-foreground pl-1 pt-0.5">
          Use <code className="bg-muted px-1 py-0.5 rounded font-mono">{'{token}'}</code> to map values from the trigger.
        </p>
      </div>
    );
  };

  const KeyValueField = ({ values, onChange, placeholder }) => {
    const pairs = values ? (typeof values === 'string' ? JSON.parse(values) : values) : [{ key: '', value: '' }];

    const updatePair = (index, k, v) => {
      const newPairs = [...pairs];
      newPairs[index] = { ...newPairs[index], key: k, value: v };
      onChange(newPairs);
    };

    const addPair = () => {
      onChange([...pairs, { key: '', value: '' }]);
    };

    const removePair = (index) => {
      if (pairs.length === 1) {
        onChange([{ key: '', value: '' }]);
      } else {
        const newPairs = pairs.filter((_, i) => i !== index);
        onChange(newPairs);
      }
    };

    return (
      <div className="space-y-2">
        {pairs.map((pair, index) => (
          <div key={index} className="flex gap-2">
            <input
              className="flex-1 bg-input border border-border rounded-lg py-2 px-3 text-xs outline-none focus:ring-2 focus:ring-primary/20"
              placeholder="Key"
              value={pair.key}
              onChange={(e) => updatePair(index, e.target.value, pair.value)}
            />
            <input
              className="flex-1 bg-input border border-border rounded-lg py-2 px-3 text-xs outline-none focus:ring-2 focus:ring-primary/20"
              placeholder="Value"
              value={pair.value}
              onChange={(e) => updatePair(index, pair.key, e.target.value)}
            />
            <button
              onClick={() => removePair(index)}
              className="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors"
            >
              <ChevronRight className="w-4 h-4 rotate-45" />
            </button>
          </div>
        ))}
        <button
          onClick={addPair}
          className="flex items-center gap-2 text-xs font-bold text-primary hover:text-primary/80 transition-colors pl-1"
        >
          + {placeholder || "Add Pair"}
        </button>
      </div>
    );
  };

  const renderStep2 = () => (
    <div className="space-y-6 animate-in slide-in-from-right-4 duration-300">
      <div className="flex items-center gap-3 p-4 bg-primary/5 border border-primary/10 rounded-xl">
        <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white shadow-lg shadow-primary/20">
          <Activity className="w-5 h-5" />
        </div>
        <div>
          <h4 className="text-sm font-bold text-foreground leading-tight">{selectedAction?.name}</h4>
          <p className="text-xs text-muted-foreground">Configure the details for this action</p>
          <p>{selectedAction?.description}</p>
        </div>
      </div>

      {node.type === 'trigger' && selectedAction?.id === 'incoming_webhook' && (
        <div className="p-4 bg-violet-500/5 border border-violet-500/10 rounded-xl space-y-2">
          <label className="text-xs font-bold text-violet-600 uppercase tracking-wider">Your Webhook URL</label>
          <div className="flex gap-2">
            <input
              readOnly
              className="flex-1 bg-white/50 border border-violet-500/20 rounded-lg py-2 px-3 text-xs font-mono text-violet-700 outline-none"
              value={`${window.location.origin}/wp-json/wpbot-automator/v1/webhook/${node.id.replace('node-', '')}`}
            />
            <button
              onClick={() => navigator.clipboard.writeText(`${window.location.origin}/wp-json/wpbot-automator/v1/webhook/${node.id.replace('node-', '')}`)}
              className="px-3 py-2 bg-violet-600 text-white rounded-lg text-xs font-bold hover:bg-violet-700 transition-colors"
            >
              Copy
            </button>
          </div>
          <p className="text-[10px] text-muted-foreground italic">Use this URL to send GET or POST requests to trigger this workflow.</p>
        </div>
      )}

      {node.type === 'trigger' && selectedAction?.id === 'facebook_lead_ads' && (
        <div className="p-4 bg-blue-500/5 border border-blue-500/10 rounded-xl space-y-2">
          <label className="text-xs font-bold text-blue-600 uppercase tracking-wider">Your Facebook Webhook URL</label>
          <div className="flex gap-2">
            <input
              readOnly
              className="flex-1 bg-white/50 border border-blue-500/20 rounded-lg py-2 px-3 text-xs font-mono text-blue-700 outline-none"
              value={`${window.location.origin}/wp-json/wpbot-automator/v1/facebook-lead-ads/${node.id.replace('node-', '')}`}
            />
            <button
              onClick={() => navigator.clipboard.writeText(`${window.location.origin}/wp-json/wpbot-automator/v1/facebook-lead-ads/${node.id.replace('node-', '')}`)}
              className="px-3 py-2 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 transition-colors"
            >
              Copy
            </button>
          </div>
          <p className="text-[10px] text-muted-foreground italic">Use this URL in your Facebook Developer App configuration.</p>
        </div>
      )}

      <div className="space-y-4">
        <label className="flex items-center gap-2 text-xs font-bold text-muted-foreground uppercase tracking-wider">
          <Settings className="w-4 h-4" />
          Settings & Options
        </label>

        {selectedAction?.fields ? (
          <div className="space-y-4">
            {selectedAction.fields.map(field => {
              const fieldLocked = field.isPro && !window.wpbotAutomator?.isProActive;
              return (
              <div key={field.id} className="space-y-1.5">
                <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider pl-1 pb-2 flex items-center gap-2">
                  {field.name}
                  {field.isPro && (
                    <span className="text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700 border border-amber-300 rounded px-1.5 py-0.5">PRO</span>
                  )}
                </label>
                {fieldLocked ? (
                  <div className="space-y-1">
                    <div className="w-full flex items-center gap-2 bg-muted/40 border border-border rounded-lg py-2.5 px-3 cursor-not-allowed select-none">
                      <Lock className="w-4 h-4 text-amber-500 shrink-0" />
                      <span className="text-sm text-muted-foreground">{field.placeholder || 'Pro feature'}</span>
                    </div>
                    <p className="text-xs text-muted-foreground pl-1">
                      <a href="https://wpbot.pro" target="_blank" rel="noopener noreferrer" className="text-amber-600 underline hover:text-amber-800">Upgrade to Pro</a> to unlock this field.
                    </p>
                  </div>
                ) : field.type === 'credential' ? (
                  <div className="space-y-3">
                    {!isAddingCredential ? (
                      <div className="flex gap-2 items-center">
                        <select
                          className="flex-1 bg-input border border-border rounded-lg py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 appearance-none cursor-pointer"
                          value={fieldValues[field.id] || ""}
                          onChange={(e) => handleFieldChange(field.id, e.target.value)}
                          disabled={isLoadingCredentials}
                        >
                          <option value="">{isLoadingCredentials ? "Loading credentials..." : "Select a credential..."}</option>
                          {credentials.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                          ))}
                        </select>
                        <button
                          type="button"
                          onClick={() => setIsAddingCredential(true)}
                          className="px-4 py-2.5 bg-sidebar-accent text-foreground font-semibold rounded-lg text-sm hover:bg-sidebar-accent/80 transition-colors border shadow-sm shrink-0"
                        >
                          Set up credential
                        </button>
                      </div>
                    ) : (
                      <div className="p-4 bg-muted/30 border border-border rounded-xl space-y-4 shadow-sm">
                        <div className="flex items-center justify-between pb-2 border-b border-border">
                          <h4 className="text-sm font-bold flex items-center gap-2"><Lock className="w-4 h-4 text-primary" /> Add New Credential</h4>
                          <button type="button" onClick={() => setIsAddingCredential(false)} className="text-muted-foreground hover:text-foreground text-xs font-semibold px-2 py-1 rounded bg-black/5 dark:bg-white/5 transition-colors">Cancel</button>
                        </div>
                        <div className="space-y-4">
                          <div>
                            <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider pl-1">Credential Name</label>
                            <input
                              type="text"
                              placeholder="e.g., My Personal Bot, Company Page"
                              className="w-full mt-1.5 bg-input border border-border rounded-lg py-2.5 px-3 text-sm focus:ring-2 focus:ring-primary/20 outline-none transition-all"
                              value={newCredential.name}
                              onChange={(e) => setNewCredential(prev => ({ ...prev, name: e.target.value }))}
                            />
                          </div>
                          {node.appData?.credentialConfig?.fields?.map(cField => (
                            <div key={cField.id}>
                              <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider pl-1">{cField.name}</label>
                              {cField.type === 'textarea' ? (
                                <textarea
                                  placeholder={cField.placeholder}
                                  className="w-full mt-1.5 bg-input border border-border rounded-lg py-2.5 px-3 text-sm min-h-[80px] focus:ring-2 focus:ring-primary/20 outline-none transition-all"
                                  value={newCredential.data[cField.id] || ""}
                                  onChange={(e) => setNewCredential(prev => ({ ...prev, data: { ...prev.data, [cField.id]: e.target.value } }))}
                                />
                              ) : (
                                <input
                                  type="text"
                                  placeholder={cField.placeholder}
                                  className="w-full mt-1.5 bg-input border border-border rounded-lg py-2.5 px-3 text-sm focus:ring-2 focus:ring-primary/20 outline-none transition-all"
                                  value={newCredential.data[cField.id] || ""}
                                  onChange={(e) => setNewCredential(prev => ({ ...prev, data: { ...prev.data, [cField.id]: e.target.value } }))}
                                />
                              )}
                            </div>
                          ))}
                          <button
                            type="button"
                            onClick={handleSaveCredential}
                            disabled={isSavingCredential}
                            className="w-full py-2.5 bg-primary text-primary-foreground font-bold rounded-lg text-sm hover:bg-primary/90 transition-colors shadow-sm disabled:opacity-50 flex items-center justify-center gap-2"
                          >
                            {isSavingCredential ? "Saving..." : "Save Credential"}
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                ) : field.type === 'select' ? (
                  <select
                    className="w-full bg-input border border-border rounded-lg py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all appearance-none cursor-pointer disabled:opacity-60"
                    value={fieldValues[field.id] || ""}
                    onChange={(e) => handleFieldChange(field.id, e.target.value)}
                    disabled={loadingOptions[field.id] || (field.optionsUrl && !fieldOptions[field.id])}
                  >
                    <option value="">
                      {loadingOptions[field.id] || (field.optionsUrl && !fieldOptions[field.id])
                        ? "Loading…"
                        : (field.placeholder || "Select option")}
                    </option>
                    {(Array.isArray(field.options) ? field.options : Array.isArray(fieldOptions[field.id]) ? fieldOptions[field.id] : [])?.map(opt => (
                      <option key={opt.id} value={opt.id}>{opt.name}</option>
                    ))}
                  </select>
                ) : field.type === 'textarea' ? (
                  <textarea
                    placeholder={field.placeholder}
                    className="w-full bg-input border border-border rounded-lg py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all min-h-[100px]"
                    value={fieldValues[field.id] || ""}
                    onChange={(e) => handleFieldChange(field.id, e.target.value)}
                  />
                ) : field.type === 'checkbox' ? (
                  <label className="flex items-center gap-3 p-3 bg-input border border-border rounded-lg cursor-pointer hover:bg-sidebar-accent/50 transition-colors">
                    <input
                      type="checkbox"
                      className="w-4 h-4 rounded border-border text-primary focus:ring-primary/20"
                      checked={fieldValues[field.id] !== undefined ? fieldValues[field.id] : (field.defaultValue || false)}
                      onChange={(e) => handleFieldChange(field.id, e.target.checked)}
                    />
                    <span className="text-sm font-medium">{field.placeholder || "Enable this option"}</span>
                  </label>
                ) : field.type === 'table_columns_mapper' ? (
                  <TableColumnsMapper field={field} />
                ) : field.type === 'keyvalue' ? (
                  <KeyValueField
                    values={fieldValues[field.id]}
                    onChange={(val) => handleFieldChange(field.id, val)}
                    placeholder={field.placeholder}
                  />
                ) : (
                  <input
                    type={field.type || "text"}
                    placeholder={field.placeholder}
                    className="w-full bg-input border border-border rounded-lg py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                    value={fieldValues[field.id] || ""}
                    onChange={(e) => handleFieldChange(field.id, e.target.value)}
                  />
                )}
                {field.variables && field.variables.length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mt-1.5">
                    {field.variables.map(v => (
                      <button
                        key={v.token}
                        type="button"
                        onClick={() => {
                          const current = fieldValues[field.id] || "";
                          handleFieldChange(field.id, current + v.token);
                        }}
                        className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20 rounded-md hover:bg-primary/20 transition-colors cursor-pointer"
                        title={`Click to insert ${v.token}`}
                      >
                        <span className="opacity-60">{'{ }'}</span> {v.label}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            );
            })}
          </div>
        ) : (
          <div className="p-8 border-2 border-dashed border-border rounded-2xl bg-card/30 flex flex-col items-center justify-center text-center space-y-3">
            <div className="w-12 h-12 rounded-full bg-sidebar-accent flex items-center justify-center">
              <Database className="w-6 h-6 text-muted-foreground opacity-30" />
            </div>
            <div>
              <p className="text-sm font-medium text-foreground">Next phase details</p>
              <p className="text-xs text-muted-foreground max-w-[200px] mt-1">Specific options for this action will appear here in the final implementation.</p>
            </div>
          </div>
        )}
      </div>


      {testResponse && (
        <div className={`p-4 rounded-xl border ${testResponse.success ? 'bg-emerald-500/5 border-emerald-500/10' : 'bg-rose-500/5 border-rose-500/10'} space-y-2 animate-in fade-in slide-in-from-top-2 duration-300`}>
          <div className="flex items-center justify-between">
            <span className={`text-xs font-bold uppercase tracking-wider ${testResponse.success ? 'text-emerald-600' : 'text-rose-600'}`}>
              {testResponse.success ? 'Success' : 'Failed'}
              {testResponse.code && ` (Status: ${testResponse.code})`}
            </span>
            <button
              onClick={() => setTestResponse(null)}
              className="text-[10px] text-muted-foreground hover:text-foreground underline"
            >
              Clear
            </button>
          </div>
          {testResponse.body && (
            <div className="max-h-[150px] overflow-y-auto bg-card/50 rounded-lg p-2 border border-border/50">
              <pre className="text-[10px] font-mono text-muted-foreground whitespace-pre-wrap break-all">
                {typeof testResponse.body === 'string' ? testResponse.body : JSON.stringify(testResponse.body, null, 2)}
              </pre>
            </div>
          )}
          {testResponse.message && !testResponse.body && (
            <p className="text-xs text-muted-foreground">{testResponse.message}</p>
          )}
        </div>
      )}

      <div className="flex items-center gap-3 pt-4 border-t border-sidebar-border">
        <button
          onClick={() => setStep(1)}
          className="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 bg-sidebar-accent hover:bg-sidebar-accent/80 text-foreground rounded-xl text-sm font-semibold transition-all border border-transparent hover:border-border"
        >
          <ArrowLeft className="w-4 h-4" />
          Change Action
        </button>
        {selectedAction?.id && (selectedAction.id.includes('webhook') || selectedAction.id.includes('api') || node.appData.id === 'google_sheets' || node.appData.id === 'openai' || node.appData.id === 'claude' || node.appData.id === 'gemini' || node.appData.id === 'mistral' || node.appData.id === 'groq' || node.appData.id === 'openrouter') && node.type !== 'trigger' && (
          <button
            onClick={handleTestRun}
            disabled={isTesting}
            className="flex-1 py-2.5 px-4 bg-secondary text-secondary-foreground hover:bg-secondary/80 rounded-xl text-sm font-semibold transition-all border border-border flex items-center justify-center gap-2 disabled:opacity-50"
          >
            {isTesting ? (
              <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
            ) : (
              <Activity className="w-4 h-4" />
            )}
            Test Run
          </button>
        )}
        <button
          onClick={handleSave}
          className="flex-1 py-2.5 px-4 bg-primary text-white hover:bg-primary/90 rounded-xl text-sm font-bold transition-all shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98]"
        >
          Save Configuration
        </button>
      </div>
    </div>
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={step === 1 ? `Configure ${node.title}` : selectedAction?.name}
      maxWidth="max-w-xl"
    >
      {step === 1 ? renderStep1() : renderStep2()}
    </Modal>
  );
}
