import React, { useState } from 'react';
import { Sparkles, Loader2, ArrowRight, RotateCcw } from 'lucide-react';
import { appsMetadata } from '../constants/appsMetadata';

const LS_KEY = 'wpbot_ai_gen_settings';

const findApp = (id) => appsMetadata.find(a => a.id === id);

const findActionName = (appId, actionId) => {
  const app = findApp(appId);
  if (!app) return actionId;
  if (app.categories) {
    for (const cat of app.categories) {
      const found = cat.actions?.find(a => a.id === actionId);
      if (found) return found.name;
    }
  }
  if (app.actions) {
    const found = app.actions.find(a => a.id === actionId);
    if (found) return found.name;
  }
  return actionId;
};

const APP_VISUAL = {
  wordpress:     { title: 'WordPress',      icon: 'W',   iconBg: 'bg-blue-600' },
  woocommerce:   { title: 'WooCommerce',    icon: 'WC',  iconBg: 'bg-purple-600' },
  wpforms:       { title: 'WPForms',        icon: 'F',   iconBg: 'bg-orange-500' },
  cf7:           { title: 'Contact Form 7', icon: 'C7',  iconBg: 'bg-black' },
  fluent_forms:  { title: 'Fluent Forms',   icon: 'FF',  iconBg: 'bg-green-600' },
  webhook:       { title: 'Webhook',        icon: 'Wh',  iconBg: 'bg-violet-600' },
  facebook:      { title: 'Facebook',       icon: 'fb',  iconBg: 'bg-blue-600' },
  google_sheets: { title: 'Google Sheets',  icon: 'GS',  iconBg: 'bg-green-600' },
  mail:          { title: 'Mail',           icon: 'M',   iconBg: 'bg-gray-700' },
  telegram:      { title: 'Telegram',       icon: 'Tg',  iconBg: 'bg-blue-500' },
  whatsapp:      { title: 'WhatsApp',       icon: 'WA',  iconBg: 'bg-green-500' },
  instagram:     { title: 'Instagram',      icon: 'Ig',  iconBg: 'bg-pink-500' },
  linkedin:      { title: 'LinkedIn',       icon: 'In',  iconBg: 'bg-sky-600' },
  openai:        { title: 'OpenAI',         icon: 'OA',  iconBg: 'bg-emerald-600' },
  claude:        { title: 'Claude',         icon: 'CL',  iconBg: 'bg-amber-700' },
  gemini:        { title: 'Gemini',         icon: 'GE',  iconBg: 'bg-blue-500' },
  grok:          { title: 'Grok AI',        icon: 'GK',  iconBg: 'bg-slate-900' },
  mistral:       { title: 'Mistral AI',     icon: 'MI',  iconBg: 'bg-orange-600' },
  openrouter:    { title: 'OpenRouter',     icon: 'OR',  iconBg: 'bg-indigo-600' },
  fluentcrm:     { title: 'FluentCRM',      icon: 'FC',  iconBg: 'bg-blue-700' },
  filters:       { title: 'Filters',        icon: 'FT',  iconBg: 'bg-yellow-500' },
  iterator:      { title: 'Iterator',       icon: '↻',   iconBg: 'bg-amber-500' },
  iterator_end:  { title: 'Iterator End',   icon: '⏹',   iconBg: 'bg-amber-700' },
  api:           { title: 'API',            icon: 'A',   iconBg: 'bg-slate-700' },
  mailboxlayer:  { title: 'MailboxLayer',   icon: 'ML',  iconBg: 'bg-blue-600' },
  mailrefine:    { title: 'MailRefine',     icon: 'MR',  iconBg: 'bg-violet-600' },
};

const PROVIDERS = [
  { id: 'openai', name: 'OpenAI',  models: ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'] },
  { id: 'claude', name: 'Claude',  models: ['claude-3-5-sonnet-20240620', 'claude-3-haiku-20240307'] },
  { id: 'gemini', name: 'Gemini',  models: ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'] },
];

function computePositions(nodes, connections) {
  const childrenOf = {};
  connections.forEach(({ from, to }) => {
    if (!childrenOf[from]) childrenOf[from] = [];
    childrenOf[from].push(to);
  });

  const pos = {};
  const visited = new Set();
  const queue = [{ idx: 0, x: 100, y: 200 }];

  while (queue.length) {
    const { idx, x, y } = queue.shift();
    if (visited.has(idx)) continue;
    visited.add(idx);
    pos[idx] = { x, y };
    const kids = childrenOf[idx] || [];
    const spread = (kids.length - 1) * 160;
    kids.forEach((child, i) => {
      if (!visited.has(child)) {
        queue.push({ idx: child, x: x + 300, y: y - spread / 2 + i * 160 });
      }
    });
  }
  return pos;
}

function buildWorkflowData(aiResult) {
  const { nodes: aiNodes, connections: aiConns } = aiResult;
  const positions = computePositions(aiNodes, aiConns);

  const nodes = aiNodes.map((n, idx) => {
    const visual = APP_VISUAL[n.appId] || { title: n.appId, icon: '?', iconBg: 'bg-gray-500' };
    return {
      id: idx === 0 ? 'node-trigger-1' : `node-action-${idx}`,
      type: n.type,
      title: visual.title,
      subtitle: findActionName(n.appId, n.actionId),
      position: positions[idx] || { x: 100 + idx * 300, y: 200 },
      icon: visual.icon,
      iconBg: visual.iconBg,
      actionNumber: idx + 1,
      appData: findApp(n.appId) || null,
    };
  });

  const connections = aiConns.map((c, idx) => ({
    id: `conn-${idx + 1}`,
    from: c.from === 0 ? 'node-trigger-1' : `node-action-${c.from}`,
    to: c.to === 0 ? 'node-trigger-1' : `node-action-${c.to}`,
    fromHandle: 'right',
    toHandle: 'left',
  }));

  return { nodes, connections };
}

const AiWorkflowGenerator = ({ onSelect }) => {
  const [settings, setSettings] = useState(() => {
    try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); }
    catch { return {}; }
  });

  const [prompt, setPrompt]   = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');
  const [result, setResult]   = useState(null);

  const provider = settings.provider || 'openai';
  const apiKey   = settings.api_key  || '';
  const models   = PROVIDERS.find(p => p.id === provider)?.models || [];
  const model    = settings.model && models.includes(settings.model) ? settings.model : models[0];

  const updateSettings = (patch) => {
    const next = { ...settings, ...patch };
    setSettings(next);
    localStorage.setItem(LS_KEY, JSON.stringify(next));
  };

  const handleGenerate = async () => {
    if (!prompt.trim()) { setError('Please describe your workflow.'); return; }
    if (!apiKey.trim()) { setError('Please enter your API key.'); return; }

    setError('');
    setResult(null);
    setLoading(true);

    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/generate-workflow`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify({ provider, api_key: apiKey, model, prompt: prompt.trim() }),
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        setError(data.message || data.code || 'Failed to generate. Please try again.');
        return;
      }
      setResult(data.workflow);
    } catch {
      setError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleUse = () => {
    if (!result) return;
    onSelect({
      id: null,
      name: result.name,
      description: result.description,
      workflow_data: buildWorkflowData(result),
    });
  };

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground -mt-1">
        Describe your automation in plain English and AI will build the workflow for you.
      </p>

      {/* Prompt */}
      <div>
        <label className="block text-sm font-medium mb-1.5">Describe your automation</label>
        <textarea
          rows={4}
          value={prompt}
          onChange={e => setPrompt(e.target.value)}
          placeholder="e.g. When a WPForms form is submitted, save the data to Google Sheets and send a welcome email to the user"
          className="w-full border border-border rounded-lg px-3 py-2.5 text-sm bg-background resize-none focus:outline-none focus:ring-2 focus:ring-primary/30"
          disabled={loading}
        />
      </div>

      {/* Provider + Model + API Key */}
      <div className="grid grid-cols-3 gap-3">
        <div>
          <label className="block text-xs font-medium text-muted-foreground mb-1">AI Provider</label>
          <select
            value={provider}
            onChange={e => updateSettings({ provider: e.target.value, model: '' })}
            disabled={loading}
            className="w-full border border-border rounded-lg px-2.5 py-2 text-sm bg-background focus:outline-none"
          >
            {PROVIDERS.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-muted-foreground mb-1">Model</label>
          <select
            value={model}
            onChange={e => updateSettings({ model: e.target.value })}
            disabled={loading}
            className="w-full border border-border rounded-lg px-2.5 py-2 text-sm bg-background focus:outline-none"
          >
            {models.map(m => <option key={m} value={m}>{m}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-muted-foreground mb-1">API Key</label>
          <input
            type="password"
            value={apiKey}
            onChange={e => updateSettings({ api_key: e.target.value })}
            placeholder="sk-..."
            disabled={loading}
            className="w-full border border-border rounded-lg px-2.5 py-2 text-sm bg-background focus:outline-none"
          />
        </div>
      </div>

      {/* Error */}
      {error && (
        <p className="text-sm text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
          {error}
        </p>
      )}

      {/* Generate button (shown when no result yet) */}
      {!result && (
        <button
          onClick={handleGenerate}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-primary-foreground rounded-lg font-medium text-sm hover:bg-primary/90 transition-colors disabled:opacity-60"
        >
          {loading ? (
            <><Loader2 className="w-4 h-4 animate-spin" /> Generating...</>
          ) : (
            <><Sparkles className="w-4 h-4" /> Generate Workflow</>
          )}
        </button>
      )}

      {/* Result preview */}
      {result && (
        <div className="border border-border rounded-xl overflow-hidden">
          <div className="bg-muted/40 px-4 py-3 border-b border-border">
            <p className="font-semibold text-sm">{result.name}</p>
            {result.description && (
              <p className="text-xs text-muted-foreground mt-0.5">{result.description}</p>
            )}
          </div>
          <div className="px-4 py-3 space-y-2.5">
            {result.nodes?.map((node, idx) => {
              const visual = APP_VISUAL[node.appId] || { icon: '?', iconBg: 'bg-gray-500', title: node.appId };
              return (
                <div key={idx} className="flex items-center gap-3">
                  {idx > 0 && (
                    <div className="absolute ml-3.5 -mt-4 w-px h-3 bg-border" style={{ marginTop: '-10px', position: 'relative', left: '13px', top: '-8px', height: '8px' }} />
                  )}
                  <div className={`w-8 h-8 rounded-lg ${visual.iconBg} flex items-center justify-center text-white text-[10px] font-bold shrink-0`}>
                    {visual.icon}
                  </div>
                  <div className="flex-1 min-w-0">
                    <span className="text-xs font-semibold text-foreground">{visual.title}</span>
                    <span className="text-xs text-muted-foreground ml-1.5">{findActionName(node.appId, node.actionId)}</span>
                  </div>
                  {idx === 0 && (
                    <span className="text-[10px] bg-primary/10 text-primary font-semibold px-1.5 py-0.5 rounded shrink-0">
                      Trigger
                    </span>
                  )}
                </div>
              );
            })}
          </div>
          <div className="flex gap-2 px-4 py-3 border-t border-border bg-muted/20">
            <button
              onClick={() => setResult(null)}
              className="flex items-center gap-1.5 px-3 py-2 text-sm border border-border rounded-lg hover:bg-muted transition-colors"
            >
              <RotateCcw className="w-3.5 h-3.5" /> Regenerate
            </button>
            <button
              onClick={handleUse}
              className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-sm bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 transition-colors"
            >
              Use This Workflow <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default AiWorkflowGenerator;
