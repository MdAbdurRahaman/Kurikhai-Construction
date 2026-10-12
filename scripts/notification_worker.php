<?php
/**
 * Asynchronous Notification Outbox Worker - Tabeeb Contractor
 * 
 * Processes durable notification_outbox queue items with retry backoff.
 * Strictly CLI-only execution. Run via cron or background trigger.
 * 
 * Usage:
 *   php scripts/notification_worker.php
 * 
 * Recommended cPanel Cron (every minute):
 *   * * * * * /usr/local/bin/php /home/tabeebgroup/public_html/scripts/notification_worker.php >/dev/null 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden: Notification worker may only be executed via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

$res = Mailer::processOutboxQueue(5);

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Outbox Worker Processed: {$res['processed']} | Sent: {$res['sent']} | Simulated: {$res['simulated']} | Failed: {$res['failed']} | Blocked: {$res['blocked']}\n";

$hasErrors = !empty($res['error']) || ($res['failed'] > 0);
exit($hasErrors ? 1 : 0);
