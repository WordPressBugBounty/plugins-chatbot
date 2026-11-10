# Cross-agent interfaces (binding)

Three feature agents work in parallel. These signatures are fixed so code written by one agent can call another's.
Implement your own side exactly; call the others' side exactly (guard with `class_exists` where the call is optional at runtime).

## Ownership

| Agent | Owns (files under includes/ai-marketing/) |
|---|---|
| B — Sending | `Mail/Mailer.php`, `Mail/SmtpConfigurator.php`, `Mail/Tracking.php`, `Jobs/Scheduler.php`, `Jobs/Tick.php`, `Domain/CampaignService.php`, `Domain/TemplateSeeder.php`, `Http/CampaignsController.php`, `Http/TemplatesController.php`, `Http/AnalyticsController.php`, `Http/SmtpController.php` |
| C — Audience & automation | `Domain/AutomationEngine.php`, `Domain/ContactImporter.php`, `Http/ContactsController.php`, `Http/GroupsController.php`, `Http/AutomationsController.php`, `Http/SettingsController.php`, `Http/SubscribeController.php`, `Integrations/WordPressUsers.php`, `Integrations/WooCommerce.php`, `Integrations/Shortcode.php` |
| D — AI & leads | `Support/Ai.php`, `Support/Prompts.php`, `Http/AiController.php`, `Http/LeadsController.php`, `Domain/Leads/*.php` (provider clients, finder, email discovery, MX check), `Integrations/LeadsAutopilot.php` |
| Lead engineer | `Integrations/FormCapture.php`, `Http/IntegrationsController.php`, wiring, UI build |

Do not edit files owned by another agent or the foundation files. If you need a foundation change, write it in `DECISIONS.md` under "Requests for lead engineer" and work around it.
There is **no** `Integrations/ChatbotLeads` — chatbot lead capture lives in FormCapture.

## B provides

```php
namespace QuantumCloud\WPBot\CRM\Mail;
final class Mailer {
	/**
	 * Personalise, add footer/compliance, tracking (per settings), List-Unsubscribe headers, send via wp_mail.
	 * Creates (or reuses $context['message_id']) a `messages` row, marks it sent/failed, fires wpbot_crm/message_sent.
	 * $context keys: campaign_id?, automation_id?, step_key?, message_id?, from_name?, from_email?, reply_to?, utm?(bool), preview_text?
	 * @return int|\WP_Error message id on success.
	 */
	public static function send_to_contact( array $contact, string $subject, string $html, array $context = array() );
	/** Plain transactional mail (double opt-in confirmation, tests). No tracking, no footer. */
	public static function send_system( string $to, string $subject, string $html ): bool;
}
namespace QuantumCloud\WPBot\CRM\Mail;
final class Tracking {
	public static function unsubscribe_url( array $contact ): string;
	public static function confirm_url( array $contact ): string;
}
namespace QuantumCloud\WPBot\CRM\Jobs;
final class Scheduler {
	/** Runs one tick under the shared lock. Returns false if another tick holds the lock. */
	public static function run_now( int $max_seconds = 20 ): bool;
}
```
B's Mailer hooks `wpbot_crm/send_confirmation` to email the confirm link.
B's `Tick` calls, in order: campaigns → `\QuantumCloud\WPBot\CRM\Domain\AutomationEngine::run_due( 100 )` (if class exists) → housekeeping.
Tracking fires `wpbot_crm/email_opened( array $message, array $contact )` and `wpbot_crm/link_clicked( array $message, array $contact, string $url )`.

## C provides

```php
namespace QuantumCloud\WPBot\CRM\Domain;
final class AutomationEngine {
	public static function init(): void;            // subscribes to foundation + B hooks, user_register, woocommerce_order_status_completed
	public static function run_due( int $limit = 100 ): int; // executes due automation_runs, returns count; sends via Mailer::send_to_contact
	public static function trigger( string $trigger_name, int $contact_id, array $context = array() ): void; // used by FormCapture for form_submitted
}
```
Trigger names exactly as API-CONTRACT §3.7. FormCapture calls `AutomationEngine::trigger( 'form_submitted', $id, ['source'=>…] )`.

## D provides

```php
namespace QuantumCloud\WPBot\CRM\Support;
final class Ai {
	/** True when a provider + key + model are configured (CRM settings, else WPBot's AI settings). */
	public static function is_configured(): bool;
	/** @return string|\WP_Error */
	public static function text( string $system, string $user, array $opts = array() );
	/** Requests JSON, strips <think> blocks and ``` fences, json_decodes. @return array|\WP_Error */
	public static function json( string $system, string $user, array $opts = array() );
	/** Test arbitrary unsaved credentials. @return array{success:bool,message:string,reply:string} */
	public static function test( string $provider, string $key, string $model ): array;
}
```
`$opts`: `max_tokens`, `temperature`, `timeout`, `provider`, `key`, `model` (overrides).

## Options owned
- `wpbot_crm_settings` — foundation `Support\Settings` (C's SettingsController reads/writes through it).
- `wpbot_crm_smtp` — B.
- `wpbot_crm_leads` — D.
- `wpbot_crm_capture` — lead engineer.
