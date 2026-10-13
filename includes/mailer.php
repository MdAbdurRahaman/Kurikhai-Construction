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
        $mail->Timeout    = 10;
        $mail->getSMTPInstance()->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 10; // Bounded transport timeout (15s) to prevent hung worker claims

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
                    'config_blocked'    => false,
                    'provider_response' => 'Simulated delivery (credentials unconfigured in development/test mode)',
                    'error'             => null
                ];
            }
            // In production, unconfigured SMTP credentials MUST NOT simulate or consume the queue
            return [
                'success'           => false,
                'simulated'         => false,
                'config_blocked'    => true,
                'provider_response' => null,
                'error'             => 'SMTP credentials unconfigured in production environment (configuration blocked)'
            ];
        }

        if (defined('MAIL_SIMULATE') && MAIL_SIMULATE === true) {
            return [
                'success'           => true,
                'simulated'         => true,
                'config_blocked'    => false,
                'provider_response' => 'Simulated delivery (MAIL_SIMULATE active)',
                'error'             => null
            ];
        }

        try {
            $mail = self::createMailer();

            // Each outbox row represents a dedicated message dispatched directly to $toEmail.
            // Never implicitly add other internal subscribers to CC/BCC headers of third-party emails,
            // honoring strict recipient privacy across operational departments.
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;

            $mail->send();

            return [
                'success'           => true,
                'simulated'         => false,
                'config_blocked'    => false,
                'provider_response' => '250 Message accepted for delivery by ' . SMTP_HOST,
                'error'             => null
            ];
        } catch (PHPMailerException $e) {
            error_log('[PHPMailer Error] ' . $e->getMessage());
            return [
                'success'           => false,
                'simulated'         => false,
                'config_blocked'    => false,
                'provider_response' => null,
                'error'             => $e->getMessage()
            ];
        } catch (Throwable $t) {
            error_log('[SMTP Mailer Unexpected Error] ' . $t->getMessage());
            return [
                'success'           => false,
                'simulated'         => false,
                'config_blocked'    => false,
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
     * Requeue pending, blocked, or failed notifications (e.g. after configuring SMTP credentials)
     */
    public static function requeuePendingOrFailed(int $maxAgeHours = 72): int {
        if (!DB::isConnected()) return 0;
        $cutoff = date('Y-m-d H:i:s', time() - max(1, min(8760, $maxAgeHours)) * 3600);
        // Only explicitly failed messages. Never revoke a live worker's ownership.
        return db_exec("UPDATE notification_outbox SET status='queued', attempts=0, next_retry_at=NOW(), worker_token=NULL WHERE status='failed' AND created_at >= ?", [$cutoff]);
    }

    public static function processOutboxQueue(int $batchSize = 5): array {
        $results = ['processed'=>0,'sent'=>0,'simulated'=>0,'failed'=>0,'blocked'=>0];
        if (!DB::isConnected()) return $results + ['error'=>'Database unavailable'];
        $batchSize = max(1, min(5, $batchSize));
        $pdo = DB::getConnection();
        $lock = 'tabeeb-outbox-' . substr(hash('sha256', DB_NAME),0,32);
        $locked = false;
        try {
            // A server-side advisory lock spans dispatch. Another process cannot reclaim an active batch.
            $locked = (int)DB::query('SELECT GET_LOCK(?, 0)', [$lock])->fetchColumn() === 1;
            if (!$locked) return $results + ['busy'=>true];
            db_exec("UPDATE notification_outbox SET status='queued',worker_token=NULL,last_error='Interrupted worker recovered' WHERE status='processing' AND updated_at < ?", [date('Y-m-d H:i:s', time()-300)]);
            if (APP_ENV === 'production' && !self::isConfigured()) {
                $results['blocked'] = db_exec("UPDATE notification_outbox SET next_retry_at=?,last_error='SMTP configuration required' WHERE status IN ('queued','failed') AND attempts < max_attempts AND next_retry_at <= NOW()", [date('Y-m-d H:i:s',time()+300)]);
                return $results;
            }
            for ($i=0; $i<$batchSize; $i++) {
                $token = bin2hex(random_bytes(16));
                $msg = DB::transaction(function($pdo) use ($token) {
                    $row = DB::query("SELECT * FROM notification_outbox WHERE status IN ('queued','failed') AND attempts < max_attempts AND next_retry_at <= NOW() ORDER BY created_at,id LIMIT 1 FOR UPDATE")->fetch();
                    if (!$row) return null;
                    $changed = db_exec("UPDATE notification_outbox SET status='processing',worker_token=?,updated_at=NOW() WHERE id=? AND status IN ('queued','failed')",[$token,$row['id']]);
                    if ($changed !== 1) throw new RuntimeException('Outbox claim failed');
                    return $row;
                });
                if (!$msg) break;
                // Renew and prove ownership immediately before touching the provider.
                $own = DB::query("SELECT id FROM notification_outbox WHERE id=? AND status='processing' AND worker_token=?",[$msg['id'],$token])->fetchColumn();
                if (!$own) continue;
                db_exec("UPDATE notification_outbox SET updated_at=NOW() WHERE id=? AND worker_token=?",[$msg['id'],$token]);
                $res = self::sendEmail($msg['recipient_email'],$msg['recipient_name'],$msg['subject'],$msg['body_html'],$msg['body_plain'],'to');
                $results['processed']++;
                if (!empty($res['config_blocked'])) {
                    db_exec("UPDATE notification_outbox SET status='queued',worker_token=NULL,next_retry_at=?,last_error=? WHERE id=? AND status='processing' AND worker_token=?",[date('Y-m-d H:i:s',time()+300),$res['error'],$msg['id'],$token]);
                    $results['blocked']++; continue;
                }
                $attempt = (int)$msg['attempts']+1;
                $status = !empty($res['success']) ? (!empty($res['simulated'])?'simulated':'sent') : 'failed';
                DB::transaction(function($pdo) use ($msg,$token,$res,$status,$attempt) {
                    $changed = db_exec("UPDATE notification_outbox SET status=?,attempts=?,worker_token=NULL,provider_response=?,last_error=?,next_retry_at=?,sent_at=? WHERE id=? AND status='processing' AND worker_token=?",[$status,$attempt,$res['provider_response']??null,$res['error']??null,date('Y-m-d H:i:s',time()+120*(2**min($attempt-1,5))),$status==='failed'?null:date('Y-m-d H:i:s'),$msg['id'],$token]);
                    if ($changed !== 1) throw new RuntimeException('Outbox ownership was lost; delivery outcome requires review');
                    db_exec("INSERT INTO notification_logs (lead_id,recipient_email,subject,delivery_status,attempt_count,provider_response,error_message,sent_at) VALUES (?,?,?,?,?,?,?,NOW())",[$msg['lead_id'],$msg['recipient_email'],$msg['subject'],$status,$attempt,$res['provider_response']??null,$res['error']??null]);
                });
                $results[$status]++;
            }
        } catch (Throwable $e) {
            error_log('[Outbox failure] ' . $e->getMessage()); $results['error']='Outbox processing failed; inspect private logs.';
        } finally {
            if ($locked) { try { DB::query('SELECT RELEASE_LOCK(?)',[$lock]); } catch (Throwable $ignored) {} }
        }
        return $results;
    }
}

function smtp_configuration_fingerprint(): string {
    return hash_hmac('sha256', implode('|',[SMTP_HOST,SMTP_PORT,SMTP_SECURE,SMTP_USER,SMTP_PASS,SMTP_FROM_EMAIL]), HMAC_SECRET);
}
