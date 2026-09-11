<?php
/**
 * Support Page Template for WPbot Automator
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
    :root {
        --wpbot-primary: #4f46e5;
        --wpbot-primary-hover: #4338ca;
        --wpbot-bg: #f8fafc;
        --wpbot-card-bg: #ffffff;
        --wpbot-text-main: #1e293b;
        --wpbot-text-muted: #64748b;
        --wpbot-border: #e2e8f0;
        --wpbot-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --wpbot-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    }

    .wpbot-support-container {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        max-width: 1000px;
        margin: 20px auto;
        padding: 0 20px;
        color: var(--wpbot-text-main);
    }

    .wpbot-support-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .wpbot-support-header h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 15px;
        background: linear-gradient(to right, #4f46e5, #9333ea);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 45px;
    }

    .wpbot-support-header p {
        font-size: 1.1rem;
        color: var(--wpbot-text-muted);
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }

    /* Support Cards */
    .wpbot-support-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }

    .wpbot-support-card {
        background: var(--wpbot-card-bg);
        border: 1px solid var(--wpbot-border);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: var(--wpbot-shadow);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .wpbot-support-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--wpbot-shadow-lg);
        border-color: var(--wpbot-primary);
    }

    .wpbot-support-icon {
        font-size: 3rem;
        margin-bottom: 20px;
        display: block;
    }

    .wpbot-support-card h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .wpbot-support-card p {
        color: var(--wpbot-text-muted);
        line-height: 1.6;
        margin-bottom: 25px;
    }

    .wpbot-btn {
        display: inline-block;
        padding: 12px 25px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .wpbot-btn-primary {
        background: var(--wpbot-primary);
        color: white;
    }

    .wpbot-btn-primary:hover {
        background: var(--wpbot-primary-hover);
        transform: scale(1.02);
        color: #ffffff;
    }

    .wpbot-btn-outline {
        border: 2px solid var(--wpbot-border);
        color: var(--wpbot-text-main);
    }

    .wpbot-btn-outline:hover {
        border-color: var(--wpbot-primary);
        color: var(--wpbot-primary);
    }

    /* Knowledge Base Section */
    .wpbot-kb-banner {
        background: #eff6ff;
        border-radius: 20px;
        padding: 40px;
        display: flex;
        align-items: center;
        gap: 30px;
        margin-bottom: 60px;
        border: 1px solid #dbeafe;
    }

    .wpbot-kb-content h2 {
        font-size: 1.75rem;
        margin-bottom: 10px;
    }

    /* Pro Banner */
    .wpbot-pro-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border-radius: 24px;
        padding: 50px;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .wpbot-pro-banner h2 {
        color: white;
        font-size: 2.25rem;
        margin-bottom: 15px;
    }

    .wpbot-pro-banner p {
        font-size: 1.1rem;
        margin-bottom: 30px;
        opacity: 0.9;
    }

    .wpbot-btn-white {
        background: white;
        color: #4f46e5;
    }

    .wpbot-btn-white:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
        .wpbot-kb-banner {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<div class="wpbot-support-container">
    <div class="wpbot-support-header">
        <h1>Need Some Help?</h1>
        <p>Our team is here to ensure your automation success. Choose the support channel that best fits your needs.</p>
    </div>

    <div class="wpbot-kb-banner">
        <span class="wpbot-support-icon">📚</span>
        <div class="wpbot-kb-content">
            <h2>Self-Service Documentation</h2>
            <p>Check out our comprehensive guides and tutorials to find quick answers to common questions.</p>
        </div>
        <a href="https://wpbot.pro/docs/knowledgebase/workflow-automation-plugin-for-wordpress/" target="_blank" class="wpbot-btn wpbot-btn-outline">Browse Docs</a>
    </div>

    <div class="wpbot-support-grid">
        <div class="wpbot-support-card">
            <div>
                <span class="wpbot-support-icon">⚡</span>
                <h3>Priority Support</h3>
                <p>Pro users get premium, guaranteed quick, one-on-one priority support from our expert developers.</p>
            </div>
            <a href="https://qc.turbopowers.com/" target="_blank" class="wpbot-btn wpbot-btn-primary">Open Priority Ticket</a>
        </div>

        <div class="wpbot-support-card">
            <div>
                <span class="wpbot-support-icon">💬</span>
                <h3>Community Support</h3>
                <p>Using the free version? Join our community forums to get help from other users and our staff.</p>
            </div>
            <a href="https://www.wpbot.pro/free-support/" target="_blank" class="wpbot-btn wpbot-btn-outline">Free Support</a>
        </div>
    </div>

    <div class="wpbot-pro-banner">
        <h2>Unlock 1-on-1 Support</h2>
        <p>Upgrade to WPbot Automator PRO today and get instant access to our priority support channel plus advanced automation features.</p>
        <a href="#" class="wpbot-btn wpbot-btn-white">Upgrade to Pro Now</a>
    </div>
</div>