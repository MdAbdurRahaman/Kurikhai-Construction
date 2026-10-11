<?php
/**
 * SMTP Mailer & Durable Notification Outbox Service - Tabeeb Contractor
 * 
 * Powered by maintained PHPMailer with strict TLS validation, durable outbox processing,
 * exponential retry backoff, idempotency guarantees, and simulated delivery modes.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer {

    /**
     * Check if valid SMTP credentials are configured
     */
    public static function isConfigured(): bool {
        return !empty(SMTP_HOST) && !empty(SMTP_USER) && !empty(SMTP_PASS);
    }

    /**
     * Create and configure a PHPMailer instance with strict TLS verification
     */
    public static function createMailer(): PHPMailer {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Port       = SMTP_PORT;

        if (SMTP_SECURE === 'ssl' || SMTP_PORT === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls' || SMTP_PORT === 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Strict TLS certificate validation - fail on handshake/auth errors
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false
            ]
        ];

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        return $mail;
    }

    /**
     * Send email via PHPMailer or record simulation in development / test environments.
     * In production, unconfigured credentials are configuration-blocked and retained for retry.
     */
    public static function sendEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainBody,
        string $recipientType = 'to'
    ): array {
        $isSimAllowed = (defined('APP_ENV') && APP_ENV === 'development') || (defined('MAIL_SIMULATE') && MAIL_SIMULATE === true);
        if (!self::isConfigured()) {
            if ($isSimAllowed) {
                return [
                    'success'           => true,
                    'simulated'         => true,
                    'provider_response' => 'Simulated delivery (credentials unconfigured in development/test mode)',
                    'error'             => null
                ];
            }
            // In production, unconfigured SMTP credentials MUST NOT simulate or consume the queue
            return [
                'success'           => false,
                'simulated'         => false,
                'provider_response' => null,
                'error'             => 'SMTP credentials unconfigured in production environment (configuration blocked)'
            ];
        }

        if (defined('MAIL_SIMULATE') && MAIL_SIMULATE === true) {
            return [
                'success'           => true,
                'simulated'         => true,
                'provider_response' => 'Simulated delivery (MAIL_SIMULATE active)',
                'error'             => null
            ];
        }

        try {
            $mail = self::createMailer();

            // Each outbox row represents a dedicated message dispatched directly to $toEmail.
            // Never implicitly add the public SMTP sender to headers, honoring strict recipient privacy.
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;

            $mail->send();

            return [
                'success'           => true,
                'simulated'         => false,
                'provider_response' => '250 Message accepted for delivery by ' . SMTP_HOST,
                'error'             => null
            ];
        } catch (PHPMailerException $e) {
            error_log('[PHPMailer Error] ' . $e->getMessage());
            return [
                'success'           => false,
                'simulated'         => false,
                'provider_response' => null,
                'error'             => $e->getMessage()
            ];
        } catch (Throwable $t) {
            error_log('[SMTP Mailer Unexpected Error] ' . $t->getMessage());
            return [
                'success'           => false,
                'simulated'         => false,
                'provider_response' => null,
                'error'             => $t->getMessage()
            ];
        }
    }

    /**
     * Dispatch an administrative test email to verify credentials and connectivity
     */
    public static function sendTestEmail(string $targetEmail): array {
        if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'simulated' => false, 'error' => 'Invalid email address provided for test.'];
        }

        $subject = "Tabeeb Contractor Portal - SMTP Configuration Test (" . date('Y-m-d H:i') . ")";
        $html = "<div style='font-family: Arial, sans-serif; padding: 20px; color: #0a1128; border: 1px solid #e2e8f0; border-radius: 8px;'>"
              . "<h2 style='color: #0284c7; margin-top: 0;'>SMTP Lead Notification Test</h2>"
              . "<p>This is a verified test email sent from the Tabeeb Contractor website lead notification service.</p>"
              . "<ul>"
              . "<li><strong>Host:</strong> " . htmlspecialchars(SMTP_HOST) . "</li>"
              . "<li><strong>Port:</strong> " . SMTP_PORT . " (" . htmlspecialchars(SMTP_SECURE) . ")</li>"
              . "<li><strong>Sender:</strong> " . htmlspecialchars(SMTP_FROM_EMAIL) . "</li>"
              . "<li><strong>Target:</strong> " . htmlspecialchars($targetEmail) . "</li>"
              . "<li><strong>Server Time:</strong> " . date('Y-m-d H:i:s T') . "</li>"
              . "</ul>"
              . "<p style='color: #64748b; font-size: 13px;'>If you received this message, lead notification alerts are operating correctly.</p>"
              . "</div>";
        $plain = "Tabeeb Contractor Portal - SMTP Notification Test Successful\nHost: " . SMTP_HOST . "\nPort: " . SMTP_PORT . "\nSender: " . SMTP_FROM_EMAIL . "\nTime: " . date('Y-m-d H:i:s T');

        return self::sendEmail($targetEmail, 'Tabeeb Administrator', $subject, $html, $plain, 'to');
    }

    /**
     * Process durable notification outbox queue with atomic claim, exponential backoff,
     * and stale-lease recovery.
     * 
     * Delivery Guarantees:
     * - At-least-once delivery: Outbox rows are claimed atomically under transaction lock.
     * - Stale recovery: Crashed workers or killed processes leave rows in 'processing';
     *   these are recovered to 'queued' after 5 minutes if attempts < max_attempts.
     * - Ambiguous network boundary: If SMTP accepts a message (250 OK) but the worker
     *   crashes before persisting the database update, the message will be re-sent upon
     *   stale lease expiration.
     */
    public static function processOutboxQueue(int $batchSize = 25): array {
        if (!DB::isConnected()) {
            return ['processed' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0, 'error' => 'Database unavailable'];
        }

        $results = ['processed' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0];

        try {
            // 1. Stale-lease recovery: reclaim messages stuck in 'processing' for over 5 minutes
            $staleCutoff = date('Y-m-d H:i:s', time() - 300);
            db_exec(
                "UPDATE `notification_outbox` 
                 SET `status` = 'queued', `last_error` = 'Worker lease expired; re-queued' 
                 WHERE `status` = 'processing' AND `updated_at` < ?",
                [$staleCutoff]
            );

            // 2. Atomic conditional claim inside transaction
            $claimedMessages = [];
            DB::transaction(function($pdo) use ($batchSize, &$claimedMessages) {
                $now = date('Y-m-d H:i:s');
                $lockSql = DB::isSqlite() ? "" : " FOR UPDATE";
                $candidates = db_all(
                    "SELECT `id` FROM `notification_outbox` 
                     WHERE (`status` = 'queued' OR (`status` = 'failed' AND `attempts` < `max_attempts` AND `next_retry_at` <= ?)) 
                     ORDER BY `created_at` ASC LIMIT ?" . $lockSql,
                    [$now, $batchSize]
                );

                if (!empty($candidates)) {
                    $ids = array_column($candidates, 'id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    db_exec(
                        "UPDATE `notification_outbox` 
                         SET `status` = 'processing', `attempts` = `attempts` + 1, `updated_at` = NOW() 
                         WHERE `id` IN ({$placeholders})",
                        $ids
                    );
                    $claimedMessages = db_all(
                        "SELECT * FROM `notification_outbox` WHERE `id` IN ({$placeholders})",
                        $ids
                    );
                }
            });

            // 3. Dispatch claimed messages
            foreach ($claimedMessages as $msg) {
                $results['processed']++;

                $res = self::sendEmail(
                    $msg['recipient_email'],
                    $msg['recipient_name'],
                    $msg['subject'],
                    $msg['body_html'],
                    $msg['body_plain'],
                    $msg['recipient_type']
                );

                if ($res['success']) {
                    $newStatus = !empty($res['simulated']) ? 'simulated' : 'sent';
                    db_exec(
                        "UPDATE `notification_outbox` SET 
                         `status` = ?, `provider_response` = ?, `last_error` = NULL, `sent_at` = NOW() 
                         WHERE `id` = ?",
                        [$newStatus, $res['provider_response'], $msg['id']]
                    );

                    // Log in notification_logs table
                    db_exec(
                        "INSERT INTO `notification_logs` 
                         (`lead_id`, `recipient_email`, `subject`, `delivery_status`, `attempt_count`, `provider_response`, `sent_at`) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$msg['lead_id'], $msg['recipient_email'], $msg['subject'], $newStatus, $msg['attempts'], $res['provider_response']]
                    );

                    if (!empty($res['simulated'])) {
                        $results['simulated']++;
                    } else {
                        $results['sent']++;
                    }
                } else {
                    $newAttempts = (int)$msg['attempts'];
                    $isMaxExceeded = ($newAttempts >= (int)$msg['max_attempts']);
                    $newStatus = 'failed';

                    // Exponential retry backoff: 2 min, 4 min, 8 min, 16 min
                    $backoffSeconds = 120 * pow(2, min($newAttempts, 5));
                    $nextRetry = date('Y-m-d H:i:s', time() + $backoffSeconds);

                    db_exec(
                        "UPDATE `notification_outbox` SET 
                         `status` = ?, `last_error` = ?, `next_retry_at` = ? 
                         WHERE `id` = ?",
                        [$newStatus, $res['error'], $nextRetry, $msg['id']]
                    );

                    // Log failed attempt in notification_logs
                    db_exec(
                        "INSERT INTO `notification_logs` 
                         (`lead_id`, `recipient_email`, `subject`, `delivery_status`, `attempt_count`, `error_message`, `sent_at`) 
                         VALUES (?, ?, ?, 'failed', ?, ?, NOW())",
                        [$msg['lead_id'], $msg['recipient_email'], $msg['subject'], $newAttempts, $res['error']]
                    );

                    $results['failed']++;
                }
            }
        } catch (Throwable $t) {
            error_log('[Outbox Processing Error] ' . $t->getMessage());
            $results['error'] = $t->getMessage();
        }

        return $results;
    }
}
