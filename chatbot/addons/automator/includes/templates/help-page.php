<?php
/**
 * Help Page Template for WPbot Automator
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

    .wpbot-help-container {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        max-width: 1000px;
        margin: 20px auto;
        padding: 0 20px;
        color: var(--wpbot-text-main);
    }

    .wpbot-help-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .wpbot-help-header h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 10px;
        background: linear-gradient(to right, #4f46e5, #9333ea);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 45px;
    }

    .wpbot-help-header p {
        font-size: 1.1rem;
        color: var(--wpbot-text-muted);
    }

    /* Step by Step Guide */
    .wpbot-steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 60px;
    }

    .wpbot-step-card {
        background: var(--wpbot-card-bg);
        border: 1px solid var(--wpbot-border);
        border-radius: 16px;
        padding: 30px;
        position: relative;
        transition: all 0.3s ease;
        box-shadow: var(--wpbot-shadow);
    }

    .wpbot-step-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--wpbot-shadow-lg);
        border-color: var(--wpbot-primary);
    }

    .wpbot-step-number {
        position: absolute;
        top: -15px;
        left: 20px;
        width: 40px;
        height: 40px;
        background: var(--wpbot-primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.4);
    }

    .wpbot-step-card h3 {
        margin-top: 10px;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .wpbot-step-card p {
        color: var(--wpbot-text-muted);
        line-height: 1.6;
    }

    /* Accordion Section */
    .wpbot-faq-section {
        background: var(--wpbot-card-bg);
        border-radius: 16px;
        padding: 40px;
        box-shadow: var(--wpbot-shadow);
    }

    .wpbot-faq-section h2 {
        font-size: 2rem;
        margin-bottom: 30px;
        text-align: center;
    }

    .wpbot-faq-item {
        border-bottom: 1px solid var(--wpbot-border);
    }

    .wpbot-faq-item:last-child {
        border-bottom: none;
    }

    .wpbot-faq-trigger {
        width: 100%;
        padding: 20px 0;
        background: none;
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        text-align: left;
        font-size: 1.1rem;
        font-weight: 500;
        color: var(--wpbot-text-main);
        transition: color 0.2s ease;
    }

    .wpbot-faq-trigger:hover {
        color: var(--wpbot-primary);
    }

    .wpbot-faq-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        color: var(--wpbot-text-muted);
        line-height: 1.6;
    }

    .wpbot-faq-item.active .wpbot-faq-content {
        max-height: 200px;
        padding-bottom: 20px;
    }

    .wpbot-faq-icon {
        width: 20px;
        height: 20px;
        position: relative;
        transition: transform 0.3s ease;
    }

    .wpbot-faq-icon::before,
    .wpbot-faq-icon::after {
        content: '';
        position: absolute;
        background: currentColor;
        transition: transform 0.3s ease;
    }

    .wpbot-faq-icon::before {
        width: 100%;
        height: 2px;
        top: 9px;
    }

    .wpbot-faq-icon::after {
        width: 2px;
        height: 100%;
        left: 9px;
    }

    .wpbot-faq-item.active .wpbot-faq-icon {
        transform: rotate(45deg);
    }

    /* Premium Banner */
    .wpbot-pro-banner {
        margin-top: 60px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border-radius: 20px;
        padding: 40px;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .wpbot-pro-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        pointer-events: none;
    }

    .wpbot-pro-banner h2 {
        color: white;
        font-size: 2rem;
        margin-bottom: 15px;
    }

    .wpbot-pro-banner p {
        font-size: 1.1rem;
        margin-bottom: 25px;
        opacity: 0.9;
    }

    .wpbot-btn {
        display: inline-block;
        padding: 12px 30px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .wpbot-btn-white {
        background: white;
        color: #4f46e5;
    }

    .wpbot-btn-white:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
    }
</style>

<div class="wpbot-help-container">
    <div class="wpbot-help-header">
        <h1>Welcome to WPbot Automator</h1>
        <p>Automate your WordPress site with the power of workflows and AI.</p>
    </div>

    <div class="wpbot-steps-grid">
        <div class="wpbot-step-card">
            <div class="wpbot-step-number">1</div>
            <h3>Connect Your Apps</h3>
            <p>Start by configuring your API keys and service connections. Navigate to settings to securely link your OpenAI or Webhook credentials.</p>
        </div>
        <div class="wpbot-step-card">
            <div class="wpbot-step-number">2</div>
            <h3>Build Your Workflow</h3>
            <p>Use our intuitive drag-and-drop builder to create your first automation. Click "Create New Workflow" to open the canvas.</p>
        </div>
        <div class="wpbot-step-card">
            <div class="wpbot-step-number">3</div>
            <h3>Define Your Trigger</h3>
            <p>Every workflow needs a start. Choose from triggers like "New Comment", "User Registered", or "Order Placed" to set things in motion.</p>
        </div>
        <div class="wpbot-step-card">
            <div class="wpbot-step-number">4</div>
            <h3>Add Smart Actions</h3>
            <p>Connect actions to your triggers. Use AI to generate content, send notifications, or update site data automatically.</p>
        </div>
        <div class="wpbot-step-card">
            <div class="wpbot-step-number">5</div>
            <h3>Activate & Monitor</h3>
            <p>Once your logic is ready, activate the workflow. You can monitor every execution in real-time from your dashboard logs.</p>
        </div>
    </div>

    <div class="wpbot-faq-section">
        <h2>Common Questions</h2>
        
        <div class="wpbot-faq-item">
            <button class="wpbot-faq-trigger">
                How do I get an OpenAI API key?
                <span class="wpbot-faq-icon"></span>
            </button>
            <div class="wpbot-faq-content">
                <p>Log in to your OpenAI account at platform.openai.com, navigate to the API Keys section, and generate a new secret key. Copy and paste it into our Settings page.</p>
            </div>
        </div>

        <div class="wpbot-faq-item">
            <button class="wpbot-faq-trigger">
                Is it compatible with WooCommerce?
                <span class="wpbot-faq-icon"></span>
            </button>
            <div class="wpbot-faq-content">
                <p>Yes! We have specialized triggers and actions for WooCommerce, allowing you to automate tasks when orders are created or status changes occur.</p>
            </div>
        </div>

        <div class="wpbot-faq-item">
            <button class="wpbot-faq-trigger">
                How many workflows can I created?
                <span class="wpbot-faq-icon"></span>
            </button>
            <div class="wpbot-faq-content">
                <p>In the free version, you can create up to unlimited active workflows. Upgrade to PRO for unlimited workflows and advanced conditional logic.</p>
            </div>
        </div>
    </div>

    <div class="wpbot-pro-banner">
        <h2>Unleash the Full Power of AI</h2>
        <p>Unlock advanced triggers, unlimited workflows, and priority support with WPbot Automator PRO.</p>
        <a href="#" class="wpbot-btn wpbot-btn-white">Upgrade to Pro Now</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const triggers = document.querySelectorAll('.wpbot-faq-trigger');
        
        triggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const item = trigger.parentElement;
                const isActive = item.classList.contains('active');
                
                // Close all others
                document.querySelectorAll('.wpbot-faq-item').forEach(i => i.classList.remove('active'));
                
                // Toggle current
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        });
    });
</script>
