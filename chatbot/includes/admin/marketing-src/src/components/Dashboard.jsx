import { Button, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Dashboard = ({ setActiveTab }) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [days, setDays] = useState(30);

    const style = `
    .qcwpbot-header .notice.notice-error {
        display: none !important;
    }
    `;

    useEffect(() => {
        setLoading(true);
        // Attempt to fetch from the backend analytics endpoint
        apiFetch({ path: `/wpbot-crm/v1/analytics/overview?days=${days}` })
            .then(res => {
                setData(res);
                setLoading(false);
            })
            .catch(err => {
                console.error("Analytics fetch error:", err);
                // Fallback to empty state if API is not registered yet
                setData({ totals: { subscribers: 0, sent: 0, opened: 0, clicks: 0, unsubscribes: 0 } });
                setLoading(false);
            });
    }, [days]);

    if (loading) {
        return (
            <div className="qcwpbot-analytics" style={{ display: 'flex', justifyContent: 'center', marginTop: '100px' }}>
                <Spinner />
            </div>
        );
    }

    const hasSubscribers = data && data.totals && data.totals.subscribers > 0;

    return (
        <>
        <style>{style}</style>
        <div className="qcwpbot-analytics">
            <div className="qcwpbot-header">
                <h2>Email Analytics</h2>
                <div className="header-actions">
                    <div className="date-filters">
                        <button className={days === 7 ? "active" : ""} onClick={() => setDays(7)}>7d</button>
                        <button className={days === 30 ? "active" : ""} onClick={() => setDays(30)}>30d</button>
                        <button className={days === 90 ? "active" : ""} onClick={() => setDays(90)}>90d</button>
                    </div>
                </div>
            </div>

            {hasSubscribers ? (
                <div className="qcwpbot-stats-grid">
                    <div className="stat-card">
                        <h3>Total Subscribers</h3>
                        <p>{data.totals.subscribers}</p>
                    </div>
                    <div className="stat-card">
                        <h3>Emails Sent</h3>
                        <p>{data.totals.sent}</p>
                    </div>
                    <div className="stat-card">
                        <h3>Opened</h3>
                        <p>{data.totals.opened}</p>
                    </div>
                </div>
            ) : (
                <div className="qcwpbot-empty-state-card">
                    <h3>Nobody on the list yet.</h3>
                    <p>Email Marketing measures what happens to the mail you send.<br/>
                    Add or import your first subscribers and every send, open and<br/>
                    click lands here.</p>
                    <div className="empty-actions">
                        <Button className="qcwpbot-primary-btn" onClick={() => setActiveTab('contacts')}>Add Subscribers</Button>
                        <Button isSecondary className="qcwpbot-secondary-btn" onClick={() => setActiveTab('contacts')}>Import a List</Button>
                    </div>
                </div>
            )}
        </div>
        </>
    );
};

export default Dashboard;
