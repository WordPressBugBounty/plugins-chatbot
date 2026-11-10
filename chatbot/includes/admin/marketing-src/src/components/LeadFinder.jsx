import { Button } from '@wordpress/components';

// The B2B Lead Finder is part of WPBot Pro. The free plugin only describes it; there is no Lead Finder code here.
const LeadFinder = ({ setActiveTab }) => {
    const proUrl = window.qcwpbotData?.proUrl || 'https://www.wpbot.pro/pricing/';

    return (
        <div className="qcwpbot-lead-finder" style={{ padding: '20px' }}>
            <div style={{ maxWidth: '800px', margin: '0 auto', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '48px 30px', textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
                <span className="dashicons dashicons-search" style={{ fontSize: '40px', width: '40px', height: '40px', color: '#5b4e96', marginBottom: '12px' }}></span>
                <h2 style={{ fontSize: '22px', fontWeight: 'bold', color: '#1e293b', margin: '0 0 12px' }}>
                    B2B Lead Finder <span className="pro-badge">PRO</span>
                </h2>
                <p style={{ color: '#64748b', maxWidth: '540px', margin: '0 auto 12px', fontSize: '14px', lineHeight: '1.6' }}>
                    WPBot Pro finds businesses by industry, role and location, discovers their email addresses, checks that the addresses can receive mail, and saves new leads to your contacts. It can also run daily on autopilot and import past form submissions.
                </p>
                <p style={{ color: '#64748b', maxWidth: '540px', margin: '0 auto 24px', fontSize: '14px', lineHeight: '1.6' }}>
                    To collect leads from your own forms and chatbot, use Form Capture.
                </p>
                <div style={{ display: 'flex', gap: '10px', justifyContent: 'center' }}>
                    <Button isSecondary onClick={() => setActiveTab('form-capture')}>Open Form Capture</Button>
                    <a
                        href={proUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        style={{ display: 'inline-block', padding: '8px 18px', fontSize: '14px', background: '#5b4e96', color: '#fff', textDecoration: 'none', borderRadius: '4px', fontWeight: 600 }}
                    >
                        Learn about WPBot Pro
                    </a>
                </div>
            </div>
        </div>
    );
};

export default LeadFinder;
