import React, { useState, useEffect } from 'react';
import EmailTemplateList from './components/email-builder/EmailTemplateList';
import EmailDesigner from './components/email-builder/EmailDesigner';
import { Loader2 } from 'lucide-react';

const FREE_TEMPLATE_LIMIT = 3;

const EmailTemplatesApp = () => {
  const [view, setView] = useState('list'); // 'list' | 'designer'
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedTemplate, setSelectedTemplate] = useState(null);

  const isProActive = !! window.wpbotAutomator?.isProActive;

  useEffect(() => {
    fetchTemplates();
  }, []);

  const fetchTemplates = async () => {
    setLoading(true);
    try {
      const baseUrl = window.wpbotAutomator.apiUrl.replace(/\/$/, '');
      const response = await fetch(`${baseUrl}/email-templates`, {
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      setTemplates(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching templates:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateNew = () => {
    if (!isProActive && templates.length >= FREE_TEMPLATE_LIMIT) {
      return; // blocked — list UI shows the upgrade prompt
    }
    setSelectedTemplate(null);
    setView('designer');
  };

  const handleEdit = async (template) => {
    setLoading(true);
    try {
      const baseUrl = window.wpbotAutomator.apiUrl.replace(/\/$/, '');
      const response = await fetch(`${baseUrl}/email-templates/${template.id}`, {
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const fullTemplate = await response.json();
      setSelectedTemplate(fullTemplate);
      setView('designer');
    } catch (error) {
      console.error('Error fetching template details:', error);
      alert('Failed to load template data.');
    } finally {
      setLoading(false);
    }
  };

  const handleBack = () => {
    setView('list');
    fetchTemplates();
  };

  if (loading && view === 'list') {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  return (
    <div className="p-8 bg-background min-h-screen text-foreground font-sans">
      <div className="max-w-6xl mx-auto">
        {view === 'list' ? (
          <EmailTemplateList
            templates={templates}
            onCreateNew={handleCreateNew}
            onEdit={handleEdit}
            onDelete={fetchTemplates}
            isProActive={isProActive}
            freeLimit={FREE_TEMPLATE_LIMIT}
          />
        ) : (
          <EmailDesigner
            template={selectedTemplate}
            onBack={handleBack}
          />
        )}
      </div>
    </div>
  );
};

export default EmailTemplatesApp;
