import { useState } from '@wordpress/element';
import { Button, CheckboxControl, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const label = (text) => <span style={{ fontSize: '13px', color: '#1e293b' }}>{text}</span>;

const ImportExport = () => {
    const [file, setFile] = useState(null);
    const [updateExisting, setUpdateExisting] = useState(false);
    const [skipTest, setSkipTest] = useState(true);
    const [skipDisposable, setSkipDisposable] = useState(false);
    const [skipRole, setSkipRole] = useState(false);
    const [importing, setImporting] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [notice, setNotice] = useState(null);

    const handleImport = async () => {
        if (!file) {
            setNotice({ status: 'error', text: 'Choose a CSV file first.' });
            return;
        }
        const body = new FormData();
        body.append('file', file);
        body.append('source', 'csv');
        body.append('update_existing', updateExisting ? '1' : '0');
        body.append('skip_test', skipTest ? '1' : '0');
        body.append('skip_disposable', skipDisposable ? '1' : '0');
        body.append('skip_role', skipRole ? '1' : '0');

        setImporting(true);
        setNotice(null);
        try {
            const res = await apiFetch({ path: '/wpbot-crm/v1/contacts/import', method: 'POST', body });
            setNotice({ status: 'success', text: res?.message || 'Import finished.' });
        } catch (err) {
            setNotice({ status: 'error', text: 'Import failed: ' + (err?.message || 'Unknown error') });
        } finally {
            setImporting(false);
        }
    };

    const handleExport = async () => {
        setExporting(true);
        setNotice(null);
        try {
            const res = await apiFetch({ path: '/wpbot-crm/v1/contacts/export' });
            const url = URL.createObjectURL(new Blob([res.csv], { type: 'text/csv;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = res.filename || 'contacts.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            setNotice({ status: 'error', text: 'Export failed: ' + (err?.message || 'Unknown error') });
        } finally {
            setExporting(false);
        }
    };

    return (
        <div>
            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} style={{ marginBottom: '20px' }}>
                    {notice.text}
                </Notice>
            )}
            <div className="qcwpbot-import-export-wrap" style={{ display: 'flex', gap: '20px', alignItems: 'flex-start' }}>

                {/* Import Column */}
                <div style={{ flex: 1, background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                    <h3 style={{ margin: 0, padding: '15px 20px', borderBottom: '1px solid #e2e8f0', fontSize: '16px', fontWeight: 'bold' }}>Import Contacts</h3>

                    <div style={{ padding: '20px' }}>
                        <div style={{ marginBottom: '20px' }}>
                            <label htmlFor="qcwpbot-import-file" style={{ display: 'block', fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', color: '#1e293b' }}>CSV FILE</label>
                            <input
                                id="qcwpbot-import-file"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => setFile(e.target.files && e.target.files[0] ? e.target.files[0] : null)}
                            />
                            <p style={{ fontSize: '12px', color: '#64748b', margin: '8px 0 0' }}>
                                The first row must hold column names. An email column is required; first name, last name, phone and status are picked up when present.
                            </p>
                        </div>

                        <div style={{ background: '#f3fae8', padding: '20px', borderRadius: '6px', marginBottom: '20px' }}>
                            <h4 style={{ margin: '0 0 15px 0', fontSize: '12px', color: '#64748b', fontWeight: 'bold' }}>VALIDATION & CLEANUP</h4>
                            <p style={{ fontSize: '12px', color: '#64748b', margin: '0 0 15px' }}>Rows with an invalid email address are always skipped.</p>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                                <CheckboxControl
                                    label={label('Skip test, fake, localhost, and placeholder emails')}
                                    checked={skipTest}
                                    onChange={setSkipTest}
                                />
                                <CheckboxControl
                                    label={label('Skip disposable or temporary email addresses')}
                                    checked={skipDisposable}
                                    onChange={setSkipDisposable}
                                />
                                <CheckboxControl
                                    label={label('Skip role-based emails like info@, support@, admin@')}
                                    checked={skipRole}
                                    onChange={setSkipRole}
                                />
                                <CheckboxControl
                                    label={label('Update contacts that already exist')}
                                    checked={updateExisting}
                                    onChange={setUpdateExisting}
                                />
                            </div>
                        </div>

                        <Button
                            onClick={handleImport}
                            isBusy={importing}
                            disabled={importing}
                            style={{ background: '#5b4e96', color: '#fff', border: 'none', padding: '8px 16px', borderRadius: '4px', fontWeight: '600' }}
                        >
                            {importing ? 'Importing...' : 'Upload CSV & Import'}
                        </Button>
                    </div>
                </div>

                {/* Export Column */}
                <div style={{ flex: 1, background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                    <h3 style={{ margin: 0, padding: '15px 20px', borderBottom: '1px solid #e2e8f0', fontSize: '16px', fontWeight: 'bold' }}>Export Contacts</h3>

                    <div style={{ padding: '20px' }}>
                        <p style={{ margin: '0 0 20px 0', color: '#4b5563', fontSize: '14px' }}>Download all contacts, with their lists and tags, as a CSV file.</p>
                        <Button isSecondary onClick={handleExport} isBusy={exporting} disabled={exporting} style={{ padding: '6px 16px', fontWeight: '600' }}>
                            {exporting ? 'Preparing...' : 'Download CSV'}
                        </Button>
                    </div>
                </div>

            </div>
        </div>
    );
};

export default ImportExport;
