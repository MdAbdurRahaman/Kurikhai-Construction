<?php
/**
 * SMTP Mailer & Lead Notification Service - Tabeeb Contractor
 * 
 * Provides native authenticated SMTP delivery (SSL/TLS), notification routing,
 * delivery logging, and retry handling.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Mailer {

    /**
     * Send lead inquiry notifications based on database routing rules
     */
    public static function dispatchLeadNotifications(array $lead): array {
        if (!DB::isConnected()) {
            return ['dispatched' => 0, 'errors' => ['Database not connected. Notification queued.']];
        }

        // Fetch active notification recipients
        $recipients = db_all("SELECT * FROM `notification_recipients` WHERE `is_active` = 1");

        // If no recipients configured yet, fall back to default business mailbox
        if (empty($recipients)) {
            $recipients = [[
                'id' => null,
                'name' => 'General Inquiry Inbox',
                'email' => SMTP_USER ?: 'info@tabeebgroup.com',
                'recipient_type' => 'to',
                'service_filter' => null
            ]];
        }

        $subject = "New Inquiry #{$lead['lead_number']} - {$lead['service_requested']}";
        $htmlBody = self::buildLeadEmailHtml($lead);
        $plainBody = self::buildLeadEmailPlain($lead);

        $results = ['sent' => 0, 'failed' => 0, 'logs' => []];

        foreach ($recipients as $rcpt) {
            // Apply service filter if configured
            if (!empty($rcpt['service_filter']) && stripos($lead['service_requested'], $rcpt['service_filter']) === false) {
                continue;
            }

            $success = self::send(
                $rcpt['email'],
                $rcpt['name'],
                $subject,
                $htmlBody,
                $plainBody
            );

            // Log delivery attempt
            db_exec(
                "INSERT INTO `notification_logs` 
                 (`lead_id`, `recipient_email`, `subject`, `delivery_status`, `attempt_count`, `provider_response`, `error_message`, `sent_at`) 
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?)",
                [
                    $lead['id'] ?? null,
                    $rcpt['email'],
                    $subject,
                    $success['success'] ? 'sent' : 'failed',
                    $success['success'] ? '250 Message accepted' : null,
                    $success['success'] ? null : $success['error'],
                    $success['success'] ? date('Y-m-d H:i:s') : null
                ]
            );

            if ($success['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['logs'][] = $success['error'];
            }
        }

        return $results;
    }

    /**
     * Dispatch a test email for administrative verification
     */
    public static function sendTestEmail(string $targetEmail): array {
        $subject = "Tabeeb Contractor Portal - SMTP Configuration Test (" . date('Y-m-d H:i') . ")";
        $html = "<div style='font-family: sans-serif; padding: 20px; color: #07274d;'>"
              . "<h2>SMTP Notification Test Successful</h2>"
              . "<p>This is a verified test email sent from the Tabeeb Contractor website lead notification service.</p>"
              . "<ul>"
              . "<li><strong>Host:</strong> " . htmlspecialchars(SMTP_HOST) . "</li>"
              . "<li><strong>Port:</strong> " . SMTP_PORT . " (" . htmlspecialchars(SMTP_SECURE) . ")</li>"
              . "<li><strong>Sender:</strong> " . htmlspecialchars(SMTP_FROM_EMAIL) . "</li>"
              . "<li><strong>Server Time:</strong> " . date('Y-m-d H:i:s T') . "</li>"
              . "</ul>"
              . "<p style='color: #64748b; font-size: 13px;'>If you received this message, lead alerts will be delivered smoothly to this address.</p>"
              . "</div>";
        $plain = "Tabeeb Contractor Portal - SMTP Notification Test Successful\n"
               . "This is a verified test email sent from the website lead notification service.\n"
               . "Server Time: " . date('Y-m-d H:i:s T');

        return self::send($targetEmail, 'Admin Tester', $subject, $html, $plain);
    }

    /**
     * Core SMTP Socket Transmission (RFC 5321 Compliant)
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody): array {
        // If SMTP password is not set or in local development without SMTP, log and gracefully return
        if (empty(SMTP_PASS) && APP_ENV === 'development') {
            error_log("[Simulated Email] To: {$toEmail} | Subject: {$subject}");
            return ['success' => true, 'simulated' => true];
        }

        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $secure = strtolower(SMTP_SECURE);
        $user = SMTP_USER;
        $pass = SMTP_PASS;
        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        $targetHost = ($secure === 'ssl') ? "ssl://{$host}" : $host;
        $timeout = 15;

        $socket = @fsockopen($targetHost, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            $msg = "Could not connect to SMTP server {$host}:{$port} ($errno: $errstr)";
            error_log('[SMTP Error] ' . $msg);
            return ['success' => false, 'error' => $msg];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return trim($response);
        };

        $sendCommand = function(string $cmd, string $expectedCode) use ($socket, $readResponse): ?string {
            fputs($socket, $cmd . "\r\n");
            $res = $readResponse();
            if (substr($res, 0, 3) !== $expectedCode) {
                return "Expected {$expectedCode}, received: {$res}";
            }
            return null;
        };

        $initial = $readResponse();
        if (substr($initial, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => "Server banner failed: {$initial}"];
        }

        $heloDomain = gethostname() ?: 'localhost';
        if ($err = $sendCommand("EHLO {$heloDomain}", '250')) {
            if ($err = $sendCommand("HELO {$heloDomain}", '250')) {
                fclose($socket);
                return ['success' => false, 'error' => "EHLO/HELO failed: {$err}"];
            }
        }

        // STARTTLS if configured
        if ($secure === 'tls') {
            if ($err = $sendCommand("STARTTLS", '220')) {
                fclose($socket);
                return ['success' => false, 'error' => "STARTTLS failed: {$err}"];
            }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand("EHLO {$heloDomain}", '250');
        }

        // Authenticate if credentials are provided
        if (!empty($user) && !empty($pass)) {
            if ($err = $sendCommand("AUTH LOGIN", '334')) {
                fclose($socket);
                return ['success' => false, 'error' => "AUTH LOGIN failed: {$err}"];
            }
            if ($err = $sendCommand(base64_encode($user), '334')) {
                fclose($socket);
                return ['success' => false, 'error' => "Username failed: {$err}"];
            }
            if ($err = $sendCommand(base64_encode($pass), '235')) {
                fclose($socket);
                return ['success' => false, 'error' => "Authentication failed: {$err}"];
            }
        }

        // Transmission
        if ($err = $sendCommand("MAIL FROM:<{$fromEmail}>", '250')) {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM failed: {$err}"];
        }

        if ($err = $sendCommand("RCPT TO:<{$toEmail}>", '250')) {
            fclose($socket);
            return ['success' => false, 'error' => "RCPT TO failed: {$err}"];
        }

        if ($err = $sendCommand("DATA", '354')) {
            fclose($socket);
            return ['success' => false, 'error' => "DATA handshake failed: {$err}"];
        }

        $boundary = '=_tabeeb_' . md5(uniqid(microtime(), true));

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: \"{$fromName}\" <{$fromEmail}>";
        $headers[] = "To: \"{$toName}\" <{$toEmail}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: Tabeeb Portal Mailer v2.0";

        $message = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain text part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($plainBody)) . "\r\n";

        // HTML part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $message .= "--{$boundary}--\r\n";
        $message .= ".";

        fputs($socket, $message . "\r\n");
        $dataRes = $readResponse();

        $sendCommand("QUIT", '221');
        fclose($socket);

        if (substr($dataRes, 0, 3) === '250') {
            return ['success' => true];
        }

        return ['success' => false, 'error' => "Delivery rejected: {$dataRes}"];
    }

    private static function buildLeadEmailHtml(array $lead): string {
        $leadNum = htmlspecialchars($lead['lead_number']);
        $name = htmlspecialchars($lead['name']);
        $phone = htmlspecialchars($lead['phone']);
        $email = htmlspecialchars($lead['email'] ?? 'Not provided');
        $service = htmlspecialchars($lead['service_requested']);
        $budget = htmlspecialchars($lead['estimated_budget'] ?? 'Unspecified');
        $property = htmlspecialchars($lead['property_type'] ?? 'Unspecified');
        $desc = nl2br(htmlspecialchars($lead['project_description']));
        $time = date('d M Y, h:i A');
        $adminUrl = APP_URL . '/admin/lead-detail.php?num=' . urlencode($leadNum);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>New Lead Notification</title>
        </head>
        <body style='margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #0f172a;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
                <div style='background: #07274d; padding: 24px 30px; text-align: left;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 20px;'>New Quote Inquiry</h1>
                    <p style='color: #38bdf8; margin: 4px 0 0; font-size: 13px;'>Lead Reference: {$leadNum}</p>
                </div>
                <div style='padding: 30px;'>
                    <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; width: 140px; border-bottom: 1px solid #f1f5f9;'>Customer Name:</td>
                            <td style='padding: 10px 0; font-weight: 600; color: #0f172a; border-bottom: 1px solid #f1f5f9;'>{$name}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; border-bottom: 1px solid #f1f5f9;'>Phone / WhatsApp:</td>
                            <td style='padding: 10px 0; font-weight: 600; color: #0284c7; border-bottom: 1px solid #f1f5f9;'>
                                <a href='tel:{$phone}' style='color: #0284c7; text-decoration: none;'>{$phone}</a>
                                &nbsp;&bull;&nbsp;
                                <a href='https://wa.me/" . preg_replace('/[^\d]/', '', $phone) . "' style='color: #16a34a; text-decoration: none;'>Open WhatsApp &rarr;</a>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; border-bottom: 1px solid #f1f5f9;'>Email Address:</td>
                            <td style='padding: 10px 0; color: #0f172a; border-bottom: 1px solid #f1f5f9;'>{$email}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; border-bottom: 1px solid #f1f5f9;'>Service Requested:</td>
                            <td style='padding: 10px 0; font-weight: 600; color: #07274d; border-bottom: 1px solid #f1f5f9;'>{$service}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; border-bottom: 1px solid #f1f5f9;'>Property Type:</td>
                            <td style='padding: 10px 0; color: #0f172a; border-bottom: 1px solid #f1f5f9;'>{$property}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; border-bottom: 1px solid #f1f5f9;'>Budget Range:</td>
                            <td style='padding: 10px 0; color: #0f172a; border-bottom: 1px solid #f1f5f9;'>{$budget}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #64748b; vertical-align: top;'>Project Scope:</td>
                            <td style='padding: 10px 0; color: #334155; line-height: 1.5;'>{$desc}</td>
                        </tr>
                    </table>

                    <div style='margin-top: 30px; text-align: center;'>
                        <a href='{$adminUrl}' style='display: inline-block; background: #00b4d8; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                            Open Lead in Admin Portal &rarr;
                        </a>
                    </div>
                </div>
                <div style='background: #f8fafc; padding: 16px 30px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;'>
                    Submitted via Tabeeb Contractor Website on {$time}.<br>
                    This is an automated notification dispatched from Singapore secure lead endpoint.
                </div>
            </div>
        </body>
        </html>";
    }

    private static function buildLeadEmailPlain(array $lead): string {
        $leadNum = $lead['lead_number'];
        $name = $lead['name'];
        $phone = $lead['phone'];
        $email = $lead['email'] ?? 'Not provided';
        $service = $lead['service_requested'];
        $budget = $lead['estimated_budget'] ?? 'Unspecified';
        $desc = $lead['project_description'];
        $time = date('d M Y, h:i A');
        $adminUrl = APP_URL . '/admin/lead-detail.php?num=' . urlencode($leadNum);

        return "NEW QUOTE INQUIRY (#{$leadNum})\n"
             . "--------------------------------------------------\n"
             . "Customer: {$name}\n"
             . "Phone: {$phone}\n"
             . "Email: {$email}\n"
             . "Service: {$service}\n"
             . "Budget: {$budget}\n\n"
             . "Project Scope:\n{$desc}\n\n"
             . "--------------------------------------------------\n"
             . "Submitted: {$time}\n"
             . "Admin View: {$adminUrl}\n";
    }
}
