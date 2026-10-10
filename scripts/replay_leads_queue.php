<?php
/**
 * Leads Queue Replay & Safe MySQL Importer - Tabeeb Contractor
 * 
 * Safely validates, deduplicates, and re-imports any queued lead records
 * from data/leads_queue.json into the primary MySQL CRM database.
 * Strictly CLI-only. Supports --dry-run flag.
 * 
 * Usage:
 *   php scripts/replay_leads_queue.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden: Replay utility may only be executed via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/security.php';

$isDryRun = in_array('--dry-run', $argv ?? [], true);

function log_out(string $msg): void {
    echo $msg . "\n";
}

log_out("==========================================================");
log_out("    Tabeeb Leads Queue Replay Utility (MySQL Importer)    ");
log_out("==========================================================");
if ($isDryRun) {
    log_out("🔍 MODE: DRY-RUN (Validation only, no DB writes committed)");
}

$queueFile = DATA_DIR . '/leads_queue.json';
if (!file_exists($queueFile)) {
    log_out("ℹ️ No leads queue file found at {$queueFile}. Nothing to replay.");
    exit(0);
}

$raw = file_get_contents($queueFile);
$queuedLeads = json_decode($raw, true);

if (!is_array($queuedLeads) || empty($queuedLeads)) {
    log_out("ℹ️ Queue file is empty or contains no valid lead records.");
    exit(0);
}

log_out("📋 Found " . count($queuedLeads) . " queued lead record(s) to inspect.");

if (!DB::isConnected()) {
    log_out("❌ Database connection failed. Cannot replay queue without active MySQL connection.");
    exit(1);
}

$replayed = 0;
$skippedDuplicates = 0;
$invalidRecords = 0;

foreach ($queuedLeads as $idx => $lead) {
    $leadNum = $lead['lead_number'] ?? ('TBC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))));
    $name = trim($lead['name'] ?? '');
    $phone = trim($lead['phone'] ?? '');
    $email = trim($lead['email'] ?? '') ?: null;
    $service = trim($lead['service_requested'] ?? 'General Renovation');
    $propertyType = trim($lead['property_type'] ?? 'Unspecified');
    $budget = trim($lead['estimated_budget'] ?? 'Unspecified');
    $desc = trim($lead['project_description'] ?? 'Replayed queued inquiry');
    $prefContact = in_array($lead['preferred_contact_method'] ?? '', ['phone', 'whatsapp', 'email']) ? $lead['preferred_contact_method'] : 'whatsapp';
    $source = trim($lead['source'] ?? 'website_quote_form');
    $landingPage = substr(trim($lead['landing_page'] ?? '/'), 0, 255);
    $referrer = substr(trim($lead['referrer'] ?? ''), 0, 255);
    $createdAt = $lead['created_at'] ?? $lead['submission_timestamp'] ?? date('Y-m-d H:i:s');

    // Validation
    if (empty($name) || empty($phone)) {
        log_out("⚠️ [Record #{$idx}] Skipping invalid record: Missing name or phone.");
        $invalidRecords++;
        continue;
    }

    // Duplicate Check
    $exists = db_val(
        "SELECT 1 FROM `leads` WHERE `lead_number` = ? OR (`phone` = ? AND `service_requested` = ? AND `created_at` >= DATE_SUB(?, INTERVAL 24 HOUR)) LIMIT 1",
        [$leadNum, $phone, $service, $createdAt]
    );

    if ($exists) {
        log_out("ℹ️ [Record #{$idx}] Skipping duplicate: Lead {$leadNum} ({$phone} / {$service}) already persisted.");
        $skippedDuplicates++;
        continue;
    }

    log_out("📥 [Record #{$idx}] Valid lead ready for import: {$leadNum} - {$name} ({$phone})");

    if (!$isDryRun) {
        try {
            DB::transaction(function($pdo) use ($leadNum, $name, $phone, $email, $prefContact, $service, $propertyType, $budget, $desc, $source, $landingPage, $referrer, $createdAt) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `leads` 
                     (`lead_number`, `name`, `phone`, `email`, `preferred_contact_method`, `service_requested`, 
                      `property_type`, `estimated_budget`, `project_description`, `status`, `priority`, 
                      `source`, `landing_page`, `referrer`, `consent_status`, `consent_timestamp`, `submission_timestamp`, `created_at`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'medium', ?, ?, ?, 1, ?, ?, ?)"
                );
                $stmt->execute([
                    $leadNum, $name, $phone, $email, $prefContact, $service,
                    $propertyType, $budget, $desc, $source, $landingPage, $referrer,
                    $createdAt, $createdAt, $createdAt
                ]);

                $leadId = $pdo->lastInsertId();

                $actStmt = $pdo->prepare(
                    "INSERT INTO `lead_activities` (`lead_id`, `activity_type`, `description`, `created_at`) 
                     VALUES (?, 'replayed_from_queue', 'Lead safely replayed and recovered from offline JSON queue', NOW())"
                );
                $actStmt->execute([$leadId]);
            });
            $replayed++;
        } catch (Throwable $t) {
            log_out("❌ [Record #{$idx}] Failed to insert lead {$leadNum}: " . $t->getMessage());
        }
    } else {
        $replayed++;
    }
}

log_out("----------------------------------------------------------");
log_out("Replay Summary: {$replayed} imported/valid, {$skippedDuplicates} duplicates skipped, {$invalidRecords} invalid.");

// If not dry-run and imported, safely archive the queue file with timestamp
if (!$isDryRun && $replayed > 0) {
    $archivePath = DATA_DIR . '/replayed_queue_' . date('Ymd_His') . '.json';
    @copy($queueFile, $archivePath);
    @unlink($queueFile);
    log_out("💾 Original queue safely archived to " . basename($archivePath) . " and cleared.");
}

log_out("==========================================================");
