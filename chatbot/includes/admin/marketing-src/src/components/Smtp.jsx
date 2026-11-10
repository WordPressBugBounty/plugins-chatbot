// Custom SMTP sending is part of WPBot Pro. The free plugin only describes it; there is no SMTP code here.
const Smtp = () => {
    const proUrl = window.qcwpbotData?.proUrl || 'https://www.wpbot.pro/pricing/';

    return (
        <div className="qcwpbot-smtp-wrap" style={{ maxWidth: '800px', margin: '0 auto', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '48px 30px', textAlign: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
            <span className="dashicons dashicons-email" style={{ fontSize: '40px', width: '40px', height: '40px', color: '#5b4e96', marginBottom: '12px' }}></span>
            <h2 style={{ fontSize: '22px', fontWeight: 'bold', color: '#1e293b', margin: '0 0 12px' }}>
                Email Sending (SMTP) <span className="pro-badge">PRO</span>
            </h2>
            <p style={{ color: '#64748b', maxWidth: '520px', margin: '0 auto 12px', fontSize: '14px', lineHeight: '1.6' }}>
                WPBot Pro can send your campaigns and automated emails through your own SMTP server, such as SendGrid, Amazon SES, Mailgun or Google Workspace, for authenticated sending and better deliverability.
            </p>
            <p style={{ color: '#64748b', maxWidth: '520px', margin: '0 auto 24px', fontSize: '14px', lineHeight: '1.6' }}>
                Without it, emails are sent the same way as the rest of your site's email, through WordPress.
            </p>
            <a
                href={proUrl}
                target="_blank"
                rel="noopener noreferrer"
                style={{ display: 'inline-block', padding: '10px 22px', fontSize: '14px', background: '#5b4e96', color: '#fff', textDecoration: 'none', borderRadius: '6px', fontWeight: 600 }}
            >
                Learn about WPBot Pro
            </a>
        </div>
    );
};

export default Smtp;
