<?php
// Test-only: allow the automated (headless) browser to be recorded and flush faster.
add_filter('qcin/tracker_config', function ($c) { $c['automation'] = true; $c['flushMs'] = 2500; $c['collectMs'] = 2500; $c['sample'] = 100; $c['hsample'] = 100; return $c; });
add_filter('qcin/is_bot', '__return_false');
// Test-only: send AI requests to tests/e2e/mock-ai.php when QCIN_TEST_MOCK_AI is defined in wp-config.php.
if (defined('QCIN_TEST_MOCK_AI')) { add_filter('qcin/ai_endpoint', function ($url, $provider) { return QCIN_TEST_MOCK_AI . '?provider=' . rawurlencode($provider); }, 10, 2); }
// Test-only: hand mail to tests/e2e/fake-sendmail.sh (saves .eml files) when QCIN_TEST_SENDMAIL is defined.
if (defined('QCIN_TEST_SENDMAIL')) { add_action('phpmailer_init', function ($m) { $m->isSendmail(); $m->Sendmail = QCIN_TEST_SENDMAIL; }); }
// Test-only: a feature that sends its own report switches the core one off.
add_filter('qcin/email_reports', function ($on) { return get_option('qcin_test_reports_off') ? false : $on; });
// Test-only: make sending fail (SMTP to a closed port) so the error path can be checked.
add_action('phpmailer_init', function ($m) { if (get_option('qcin_test_mail_fail')) { $m->isSMTP(); $m->Host = '127.0.0.1'; $m->Port = 1; $m->Timeout = 2; } }, 99999);
