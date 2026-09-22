import React, { useState } from 'react';
import { templates } from '../constants/templates';
import Modal from './ui/Modal';
import { Button } from './ui/Button';
import { Plus, Lock, Sparkles } from 'lucide-react';
import AiWorkflowGenerator from './AiWorkflowGenerator';

const TemplatesModal = ({ isOpen, onClose, onSelect, isProActive }) => {
  const [tab, setTab] = useState('templates');

  const handleSelect = (template) => {
    setTab('templates');
    onSelect(template);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Create New Workflow"
      maxWidth="max-w-4xl"
    >
      <div className="space-y-5">

        {/* Tab switcher */}
        <div className="flex gap-1 bg-muted/40 border border-border rounded-lg p-1 w-fit -mt-1">
          <button
            onClick={() => setTab('templates')}
            className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
              tab === 'templates'
                ? 'bg-background shadow-sm text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Templates
          </button>
          <button
            onClick={() => setTab('ai')}
            className={`flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
              tab === 'ai'
                ? 'bg-background shadow-sm text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            <Sparkles className="w-3.5 h-3.5" />
            AI Generator
          </button>
        </div>

        {tab === 'templates' ? (
          <>
            <p className="text-sm text-muted-foreground -mt-2">
              Start with a pre-configured workflow or start from scratch.
            </p>

            <div className="grid grid-cols-2 md:grid-cols-2 gap-4">
              {/* Start from Scratch Option */}
              <div
                onClick={() => handleSelect(null)}
                className="flex flex-col items-center justify-center p-6 border-2 border-dashed border-border rounded-xl hover:border-primary hover:bg-primary/5 cursor-pointer transition-all group text-center"
              >
                <div className="w-12 h-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition-colors mb-3">
                  <Plus className="w-6 h-6" />
                </div>
                <h3 className="font-bold text-lg text-foreground">Start from Scratch</h3>
                <p className="text-sm text-muted-foreground mt-1">Begin with an empty canvas and build your own workflow.</p>
              </div>

              {/* Prebuilt Templates */}
              {templates.map((template) => {
                const locked = template.isPro && !isProActive;
                return (
                  <div
                    key={template.id}
                    onClick={() => !locked && handleSelect(template)}
                    className={`relative flex flex-col p-6 border rounded-xl transition-all bg-card/50 group ${
                      locked
                        ? 'border-border opacity-60 cursor-not-allowed select-none'
                        : 'border-border hover:border-primary hover:shadow-lg cursor-pointer'
                    }`}
                  >
                    {/* PRO badge */}
                    {template.isPro && (
                      <span className="absolute top-3 right-3 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700 border border-amber-300 rounded px-1.5 py-0.5">
                        {locked && <Lock className="w-2.5 h-2.5" />}
                        PRO
                      </span>
                    )}

                    <div className="flex items-center space-x-4 mb-4">
                      <div className={`w-24 h-12 rounded-xl ${template.iconBg} flex items-center justify-center text-white text-sm font-bold shadow-sm ${!locked ? 'group-hover:scale-110' : ''} transition-transform`}>
                        {template.icon}
                      </div>
                      <div className="pr-8">
                        <h3 className="font-bold text-foreground leading-tight">{template.name}</h3>
                        <span className="text-[10px] uppercase tracking-wider text-muted-foreground font-semibold">Prebuilt</span>
                      </div>
                    </div>
                    <p className="text-sm text-muted-foreground line-clamp-3">
                      {template.description}
                    </p>

                    {locked ? (
                      <div className="mt-6 flex items-center text-xs font-medium text-amber-600">
                        <Lock className="w-3.5 h-3.5 mr-1.5" />
                        <span>Requires Pro license</span>
                        <a
                          href="https://wpbot.pro"
                          target="_blank"
                          rel="noopener noreferrer"
                          onClick={(e) => e.stopPropagation()}
                          className="ml-1 underline hover:text-amber-800"
                        >
                          Upgrade
                        </a>
                      </div>
                    ) : (
                      <div className="mt-6 flex items-center text-xs font-medium text-primary opacity-0 group-hover:opacity-100 transition-opacity">
                        <span>Use this template</span>
                        <svg className="ml-2 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </>
        ) : (
          <AiWorkflowGenerator onSelect={handleSelect} />
        )}

        <div className="flex justify-end pt-2">
          <Button variant="ghost" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </Modal>
  );
};

export default TemplatesModal;
