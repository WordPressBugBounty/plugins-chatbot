import React, { useState } from 'react';
import Modal from './ui/Modal';
import { Loader2, CheckCircle2, AlertCircle } from 'lucide-react';

export default function SupportModal({ isOpen, onClose }) {
  const [fromEmail, setFromEmail] = useState(window.wpbotAutomator?.userEmail || '');
  const [message, setMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState(null); // 'success' or 'error'
  const [errorMessage, setErrorMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setStatus(null);

    try {
      const response = await fetch(`${window.wpbotAutomator.apiUrl}/support/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify({ from_email: fromEmail, message }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        setStatus('success');
        setMessage('');
        setTimeout(() => {
          onClose();
          setStatus(null);
        }, 3000);
      } else {
        setStatus('error');
        setErrorMessage(data.message || 'An error occurred while sending your request.');
      }
    } catch (error) {
      console.error('Support request error:', error);
      setStatus('error');
      setErrorMessage('A network error occurred. Please try again later.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="WPBot Automator Support Request" maxWidth="max-w-md">
      {status === 'success' ? (
        <div className="flex flex-col items-center justify-center py-8 text-center">
          <CheckCircle2 className="w-16 h-16 text-green-500 mb-4" />
          <h4 className="text-xl font-semibold mb-2">Message Sent!</h4>
          <p className="text-muted-foreground">We have received your support request and will get back to you shortly.</p>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          {status === 'error' && (
            <div className="p-3 bg-red-50 text-red-700 rounded-md flex items-start text-sm">
               <AlertCircle className="w-5 h-5 mr-2 shrink-0" />
               <span>{errorMessage}</span>
            </div>
          )}
          
          <div>
            <label htmlFor="fromEmail" className="block text-sm font-medium mb-1">From Email</label>
            <input
              id="fromEmail"
              type="email"
              value={fromEmail}
              onChange={(e) => setFromEmail(e.target.value)}
              className="w-full px-3 py-2 border border-input bg-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-ring"
              required
            />
          </div>
          
          <div>
            <label htmlFor="message" className="block text-sm font-medium mb-1">Message</label>
            <textarea
              id="message"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              rows={5}
              className="w-full px-3 py-2 border border-input bg-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-ring resize-y"
              required
              placeholder="Describe your feature request or bug..."
            />
          </div>
          
          <div className="flex justify-end pt-2 space-x-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-foreground bg-secondary hover:bg-secondary/80 rounded-md transition-colors"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 flex items-center justify-center min-w-[100px] transition-colors"
            >
              {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Send Message'}
            </button>
          </div>
        </form>
      )}
    </Modal>
  );
}
