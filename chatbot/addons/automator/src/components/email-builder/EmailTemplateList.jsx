import React from 'react';
import { Plus, Mail, Pencil, Trash2, Lock } from 'lucide-react';

const EmailTemplateList = ({ templates, onCreateNew, onEdit, onDelete, isProActive, freeLimit }) => {
  const [searchTerm, setSearchTerm] = React.useState('');

  const atFreeLimit = !isProActive && templates.length >= freeLimit;

  const filteredTemplates = (Array.isArray(templates) ? templates : []).filter(t =>
    t.name && t.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (!Array.isArray(templates)) {
    return null;
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this template?')) return;

    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/email-templates/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
      });
      const data = await response.json();
      if (data.success) {
        onDelete();
      } else {
        alert('Failed to delete template.');
      }
    } catch (error) {
      console.error('Error deleting template:', error);
    }
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Email Templates</h1>
          <p className="text-muted-foreground mt-1">Manage and design your transactional and automated emails.</p>
        </div>

        <div className="flex flex-col items-end gap-2">
          {atFreeLimit ? (
            <>
              <div className="flex items-center space-x-2 px-6 py-2.5 bg-muted text-muted-foreground rounded-lg border border-border font-medium cursor-not-allowed select-none">
                <Lock className="w-4 h-4" />
                <span>Create New Template</span>
                <span className="ml-1 text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-300 rounded px-1.5 py-0.5">PRO</span>
              </div>
              <p className="text-xs text-muted-foreground">
                Free plan includes {freeLimit} templates.{' '}
                <a
                  href="https://wpbot.pro"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:underline font-medium"
                >
                  Upgrade to Pro
                </a>{' '}
                for unlimited.
              </p>
            </>
          ) : (
            <div className="flex flex-col items-end gap-1">
              <button
                onClick={onCreateNew}
                className="flex items-center space-x-2 px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-all shadow-sm font-medium"
              >
                <Plus className="w-5 h-5" />
                <span>Create New Template</span>
              </button>
              {!isProActive && (
                <p className="text-xs text-muted-foreground">
                  {templates.length} / {freeLimit} free templates used
                </p>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div className="p-4 border-b border-border bg-muted/30 flex items-center">
          <div className="relative flex-1 max-w-sm">
            <input
              type="text"
              placeholder="Search templates..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-border rounded-md bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm"
            />
          </div>
        </div>

        {filteredTemplates.length === 0 ? (
          <div className="py-20 text-center">
            <div className="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
              <Mail className="w-8 h-8 text-muted-foreground opacity-50" />
            </div>
            <h3 className="text-lg font-medium">No templates found</h3>
            <p className="text-muted-foreground max-w-xs mx-auto mt-1">
              {searchTerm ? `No templates matching "${searchTerm}"` : "You haven't created any email templates yet."}
            </p>
            {!searchTerm && !atFreeLimit && (
              <button
                onClick={onCreateNew}
                className="mt-6 text-primary hover:underline text-sm font-medium"
              >
                Create your first template
              </button>
            )}
          </div>
        ) : (
          <div className="divide-y divide-border">
            {filteredTemplates.map((template) => (
              <div
                key={template.id}
                className="p-6 flex items-center justify-between hover:bg-muted/30 transition-colors group"
              >
                <div className="flex items-center space-x-4">
                  <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center text-primary">
                    <Mail className="w-6 h-6" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-lg hover:text-primary cursor-pointer transition-colors" onClick={() => onEdit(template)}>
                      {template.name}
                    </h3>
                    <p className="text-sm text-muted-foreground flex items-center mt-0.5">
                      <span className="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>
                      ID: {template.id}
                    </p>
                  </div>
                </div>

                <div className="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    onClick={() => onEdit(template)}
                    className="p-2.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors border border-transparent hover:border-blue-100"
                    title="Edit Template"
                  >
                    <Pencil className="w-5 h-5" />
                  </button>
                  <button
                    onClick={() => handleDelete(template.id)}
                    className="p-2.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors border border-transparent hover:border-rose-100"
                    title="Delete Template"
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default EmailTemplateList;
