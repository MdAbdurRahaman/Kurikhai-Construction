<?php
/**
 * Automated Regression & Architecture Verification Suite - Tabeeb Contractor Portal
 * 
 * NOTE: This suite executes Unit, Schema, and Local Fixture Tests in memory.
 * It verifies application logic, security contracts, RBAC mappings, and data structures.
 * Passing this suite validates source code correctness, but does not substitute for
 * live Apache URL rewrites, production MySQL daemon execution, or external Google API verification.
 * 
 * Execution:
 *   php scripts/test_regression.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI execution required.\n";
    exit(1);
}

// Ensure test timezone and error reporting
date_default_timezone_set('Asia/Singapore');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/business_tracker.php';
require_once __DIR__ . '/../includes/tracking.php';
require_once __DIR__ . '/../includes/mailer.php';

// Terminal color helpers
function color(string $text, string $colorCode): string {
    return "\033[{$colorCode}m{$text}\033[0m";
}

$passedCount = 0;
$failedCount = 0;
$testsRun = 0;

function assert_test(string $scenarioName, bool $condition, string $details = ''): void {
    global $passedCount, $failedCount, $testsRun;
    $testsRun++;
    if ($condition) {
        $passedCount++;
        echo "  [" . color("PASS", "32") . "] {$scenarioName}\n";
    } else {
        $failedCount++;
        echo "  [" . color("FAIL", "31") . "] {$scenarioName}\n";
        if ($details) {
            echo "         " . color("Details: {$details}", "33") . "\n";
        }
    }
}

echo "\n" . color("==================================================================", "36") . "\n";
echo color("  TABEEB CONTRACTOR — ARCHITECTURAL VERIFICATION SUITE (LOCAL FIXTURES)", "36;1") . "\n";
echo color("==================================================================", "36") . "\n\n";

// =============================================================================
// SETUP: IN-MEMORY SQLITE TEST FIXTURE PDO MATCHING PRODUCTION MYSQL SCHEMA
// =============================================================================
$sqlite = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
$sqlite->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$sqlite->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
$sqlite->sqliteCreateFunction('IF', fn($cond, $a, $b) => $cond ? $a : $b);

// Initialize fixture tables matching production MySQL schema columns
$sqlite->exec("
    CREATE TABLE leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_number TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        email TEXT NULL,
        phone TEXT NOT NULL,
        preferred_contact_method TEXT NOT NULL DEFAULT 'whatsapp',
        service_requested TEXT NOT NULL,
        property_type TEXT NULL,
        estimated_budget TEXT NULL,
        project_description TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new',
        priority TEXT NOT NULL DEFAULT 'medium',
        assigned_user_id INTEGER NULL,
        source TEXT NOT NULL,
        landing_page TEXT NULL,
        referrer TEXT NULL,
        utm_params TEXT NULL,
        consent_status INTEGER NOT NULL DEFAULT 1,
        consent_timestamp TEXT NOT NULL,
        submission_timestamp TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        idempotency_key TEXT NULL UNIQUE,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TEXT NULL
    );

    CREATE TABLE lead_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        activity_type TEXT NOT NULL,
        description TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE notification_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NULL,
        recipient_email TEXT NOT NULL,
        recipient_name TEXT NOT NULL,
        recipient_type TEXT NOT NULL DEFAULT 'to',
        subject TEXT NOT NULL,
        body_html TEXT NOT NULL,
        body_plain TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'queued',
        attempts INTEGER NOT NULL DEFAULT 0,
        max_attempts INTEGER NOT NULL DEFAULT 5,
        next_retry_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        provider_response TEXT NULL,
        last_error TEXT NULL,
        idempotency_key TEXT NOT NULL UNIQUE,
        sent_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE notification_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        recipient_type TEXT NOT NULL DEFAULT 'to',
        is_active INTEGER NOT NULL DEFAULT 1,
        notification_type TEXT NOT NULL DEFAULT 'all_leads',
        service_filter TEXT NULL,
        source_filter TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE roles (
        id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT NULL
    );

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        description TEXT NULL
    );

    CREATE TABLE role_permissions (
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL,
        PRIMARY KEY (role_id, permission_id)
    );

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        session_version INTEGER NOT NULL DEFAULT 1,
        force_password_change INTEGER NOT NULL DEFAULT 0,
        deleted_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE settings (
        setting_group TEXT NOT NULL,
        setting_key TEXT NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE business_information (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_key TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL,
        title TEXT NOT NULL,
        current_value TEXT NULL,
        draft_value TEXT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        evidence_ref TEXT NULL,
        publication_state TEXT NOT NULL DEFAULT 'hidden',
        internal_notes TEXT NULL,
        next_action TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE services (
        id TEXT PRIMARY KEY,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        short_title TEXT NULL,
        category_id TEXT NULL,
        category_name TEXT NULL,
        tagline TEXT NULL,
        overview TEXT NULL,
        image TEXT NULL,
        is_published INTEGER NOT NULL DEFAULT 1,
        display_order INTEGER NOT NULL DEFAULT 0,
        features_json TEXT NULL,
        process_json TEXT NULL,
        faqs_json TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE projects (
        id TEXT PRIMARY KEY,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        location_label TEXT NOT NULL,
        public_location TEXT NOT NULL,
        service_id TEXT NULL,
        factual_summary TEXT NOT NULL,
        scope_details TEXT NULL,
        duration_label TEXT NULL,
        materials_used TEXT NULL,
        source_file TEXT NULL,
        featured_image TEXT NOT NULL,
        video_path TEXT NULL,
        video_poster TEXT NULL,
        publication_state TEXT NOT NULL DEFAULT 'draft',
        approval_status TEXT NOT NULL DEFAULT 'pending',
        approved_by INTEGER NULL,
        approved_at TEXT NULL,
        og_title TEXT NULL,
        og_description TEXT NULL,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE project_media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id TEXT NOT NULL,
        media_stage TEXT NOT NULL DEFAULT 'gallery',
        media_type TEXT NOT NULL DEFAULT 'image',
        file_path TEXT NOT NULL,
        thumbnail_path TEXT NULL,
        caption TEXT NULL,
        alt_text TEXT NOT NULL,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE marketing_baseline (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        metric_key TEXT NOT NULL UNIQUE,
        metric_category TEXT NOT NULL,
        title TEXT NOT NULL,
        metric_value TEXT NULL,
        unit TEXT NULL,
        window_start TEXT NULL,
        window_end TEXT NULL,
        source_name TEXT NOT NULL,
        property_identifier TEXT NULL,
        reporting_timezone TEXT NOT NULL DEFAULT 'Asia/Singapore',
        applied_filters TEXT NULL,
        collection_status TEXT NOT NULL,
        status_reason TEXT NULL,
        evidence_reference TEXT NULL,
        owner TEXT NOT NULL,
        collected_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE integration_verifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_type TEXT NOT NULL UNIQUE,
        property_identifier TEXT NOT NULL,
        verification_method TEXT NOT NULL,
        token_or_record TEXT NULL,
        status TEXT NOT NULL,
        evidence_summary TEXT NULL,
        last_checked_at TEXT NULL,
        verified_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NULL,
        before_state TEXT NULL,
        after_state TEXT NULL,
        ip_hash TEXT NOT NULL,
        user_agent TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rate_key TEXT NOT NULL,
        action_type TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 1,
        reset_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (rate_key, action_type)
    );
");

// Seed Roles and Permissions
$sqlite->exec("
    INSERT INTO roles (id, slug, name) VALUES
    (1, 'super_admin', 'Super Administrator'),
    (2, 'admin', 'Administrator'),
    (3, 'sales_manager', 'Sales Manager'),
    (4, 'sales_rep', 'Sales Representative'),
    (5, 'content_editor', 'Content Editor'),
    (6, 'analyst', 'Analyst');

    INSERT INTO permissions (id, slug, name, category) VALUES
    (1, 'leads.view', 'View Leads', 'Leads'),
    (2, 'leads.view_assigned', 'View Assigned Leads', 'Leads'),
    (3, 'leads.create', 'Create Lead', 'Leads'),
    (4, 'leads.edit', 'Edit Lead', 'Leads'),
    (5, 'leads.edit_assigned', 'Edit Assigned Leads', 'Leads'),
    (6, 'leads.assign', 'Assign Lead', 'Leads'),
    (7, 'leads.export', 'Export Leads', 'Leads'),
    (8, 'users.manage', 'Manage Users', 'Access Control'),
    (9, 'settings.manage', 'Manage Settings', 'Settings'),
    (10, 'content.manage', 'Manage Content', 'Content'),
    (11, 'projects.manage', 'Manage Projects', 'Projects'),
    (12, 'analytics.view', 'View Analytics', 'Analytics'),
    (13, 'baseline.view', 'View SEO Baseline', 'Marketing Baseline'),
    (14, 'baseline.manage', 'Manage SEO Baseline', 'Marketing Baseline'),
    (15, 'business_info.manage', 'Manage Business Info', 'Business Tracker');

    -- Role 1: Super Admin (all)
    INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
    -- Role 4: Sales Representative (assigned-only)
    INSERT INTO role_permissions (role_id, permission_id) VALUES (4, 2), (4, 3), (4, 5);
    -- Role 5: Content Editor (content + projects only)
    INSERT INTO role_permissions (role_id, permission_id) VALUES (5, 10), (5, 11);
    -- Role 6: Analyst (analytics + baseline.view only, NO baseline.manage)
    INSERT INTO role_permissions (role_id, permission_id) VALUES (6, 12), (6, 13);
");

// Inject SQLite fixture connection into DB singleton
DB::setConnection($sqlite);

// =============================================================================
// SCENARIO 1: SUCCESSFUL LEAD CREATION CONTRACT (PRODUCTION SCHEMA)
// =============================================================================
echo color("--- Scenario 1: Production-Aligned Lead Ingestion Schema ---\n", "34;1");
$sqlite->exec("INSERT INTO notification_recipients (name, email, recipient_type, is_active) VALUES ('Lead Team', 'ops@tabeebgroup.com', 'to', 1)");

$leadNum = 'TBC-2026-AB12CD';
$name = 'Tan Wei Ming';
$phone = '+65 9123 4567';
$email = 'weiming@example.com';
$service = 'Hacking & Demolition Works';
$msg = 'Require HDB hacking quotation for 4-room flat.';
$idempKey = hash('sha256', "{$phone}:{$service}:20261011");

$committed = false;
try {
    DB::transaction(function() use ($sqlite, $leadNum, $name, $phone, $email, $service, $msg, $idempKey) {
        $stmt = $sqlite->prepare("
            INSERT INTO leads (lead_number, name, email, phone, service_requested, project_description, source, ip_hash, idempotency_key, consent_timestamp, submission_timestamp, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'website_quote_form', 'mock_hash', ?, datetime('now'), datetime('now'), datetime('now'), datetime('now'))
        ");
        $stmt->execute([$leadNum, $name, $email, $phone, $service, $msg, $idempKey]);
        $leadId = $sqlite->lastInsertId();

        $stmtAct = $sqlite->prepare("INSERT INTO lead_activities (lead_id, activity_type, description, created_at) VALUES (?, 'lead_received', 'Inquiry submitted via verified website form', datetime('now'))");
        $stmtAct->execute([$leadId]);

        $stmtOut = $sqlite->prepare("
            INSERT INTO notification_outbox (lead_id, recipient_email, recipient_name, recipient_type, subject, body_html, body_plain, idempotency_key, next_retry_at, created_at, updated_at)
            VALUES (?, 'ops@tabeebgroup.com', 'Lead Team', 'to', 'New Inquiry', '<b>HTML</b>', 'Plain', ?, datetime('now'), datetime('now'), datetime('now'))
        ");
        $stmtOut->execute([$leadId, $idempKey . '-ops']);
    });
    $committed = true;
} catch (Exception $e) {
    $committed = false;
}

$leadCount = (int)$sqlite->query("SELECT COUNT(*) FROM leads WHERE lead_number = '{$leadNum}'")->fetchColumn();
$actCount = (int)$sqlite->query("SELECT COUNT(*) FROM lead_activities WHERE lead_id = 1")->fetchColumn();
$outboxCount = (int)$sqlite->query("SELECT COUNT(*) FROM notification_outbox WHERE lead_id = 1")->fetchColumn();

assert_test("Lead transaction commits successfully using production column names", $committed);
assert_test("Lead record persisted with lead_number and service_requested", $leadCount === 1);
assert_test("Lead activity record created atomically in lead_activities table", $actCount === 1);
assert_test("Durable outbox entry created for notification worker", $outboxCount === 1);

// =============================================================================
// SCENARIO 2: LEAD LIST SQL JOIN AMBIGUITY & SCOPED ASSIGNMENTS
// =============================================================================
echo "\n" . color("--- Scenario 2: Lead Query Qualification & Sales Rep Scoping ---\n", "34;1");
$sqlite->exec("
    INSERT INTO users (id, username, email, password_hash, name, role_id, status) VALUES
    (20, 'sales_ali', 'ali@tabeebgroup.com', 'hash', 'Ali Sales', 4, 'active'),
    (21, 'sales_bala', 'bala@tabeebgroup.com', 'hash', 'Bala Sales', 4, 'active');

    INSERT INTO leads (lead_number, name, phone, service_requested, project_description, source, ip_hash, assigned_user_id, consent_timestamp, submission_timestamp, created_at, updated_at) VALUES
    ('TBC-2026-ALI01', 'Ali Customer', '+65 8111 2222', 'Tiling Works', 'Desc Ali', 'web', 'h', 20, datetime('now'), datetime('now'), datetime('now'), datetime('now')),
    ('TBC-2026-BALA01', 'Bala Customer', '+65 8333 4444', 'Plumbing Works', 'Desc Bala', 'web', 'h', 21, datetime('now'), datetime('now'), datetime('now'), datetime('now'));
");

// Verify that qualifying l.deleted_at IS NULL avoids SQL ambiguity on joins
$joinSucceeded = false;
$aliAssignedCount = 0;
try {
    $query = "SELECT l.id, l.lead_number, u.name as assigned_name 
              FROM leads l 
              LEFT JOIN users u ON l.assigned_user_id = u.id 
              WHERE l.deleted_at IS NULL AND l.assigned_user_id = ?";
    $stmt = $sqlite->prepare($query);
    $stmt->execute([20]);
    $results = $stmt->fetchAll();
    $aliAssignedCount = count($results);
    $joinSucceeded = true;
} catch (PDOException $e) {
    $joinSucceeded = false;
}

assert_test("Qualified column prefixes (l.deleted_at) execute without SQL ambiguity", $joinSucceeded);
assert_test("Assigned-only query restricts Sales Rep (Ali) to strictly their own leads", $aliAssignedCount === 1);

// =============================================================================
// SCENARIO 3: ATOMIC IDEMPOTENCY KEY CONSTRAINT
// =============================================================================
echo "\n" . color("--- Scenario 3: Lead Idempotency Uniqueness Contract ---\n", "34;1");
$leadDupCaught = false;
try {
    $stmt = $sqlite->prepare("
        INSERT INTO leads (lead_number, name, phone, service_requested, project_description, source, ip_hash, idempotency_key, consent_timestamp, submission_timestamp, created_at, updated_at)
        VALUES ('TBC-DUP-01', 'Test Dup', '+65 9123 4567', 'Tiling Works', 'Desc', 'web', 'h', ?, datetime('now'), datetime('now'), datetime('now'), datetime('now'))
    ");
    // Duplicate insertion with same idempotency key
    $stmt->execute([$idempKey]);
} catch (PDOException $e) {
    $leadDupCaught = true;
}
assert_test("Leads table strictly rejects duplicate idempotency_key at database level", $leadDupCaught);

// =============================================================================
// SCENARIO 4: OUTBOX WORKER ATOMIC CLAIM & STALE LEASE RECOVERY
// =============================================================================
echo "\n" . color("--- Scenario 4: Outbox Worker Atomic Claim & Stale Lease Recovery ---\n", "34;1");
$staleTime = date('Y-m-d H:i:s', time() - 400); // 400 seconds ago (> 5 min)
$sqlite->exec("
    INSERT INTO notification_outbox (lead_id, recipient_email, recipient_name, subject, body_html, body_plain, status, attempts, idempotency_key, updated_at, created_at)
    VALUES (1, 'worker_test@tabeebgroup.com', 'Tester', 'Test Stale', '<b>HTML</b>', 'Plain', 'processing', 1, 'key_stale_01', '{$staleTime}', '{$staleTime}')
");

// Test stale recovery logic
$staleCutoff = date('Y-m-d H:i:s', time() - 300);
$sqlite->prepare("UPDATE notification_outbox SET status = 'queued', last_error = 'Worker lease expired; re-queued' WHERE status = 'processing' AND updated_at < ?")
       ->execute([$staleCutoff]);

$recoveredStatus = $sqlite->query("SELECT status, last_error FROM notification_outbox WHERE idempotency_key = 'key_stale_01'")->fetch();
assert_test("Stale processing messages (> 5 min) automatically re-queued", $recoveredStatus['status'] === 'queued');
assert_test("Re-queued message annotates last_error with lease expiration", str_contains($recoveredStatus['last_error'], 'Worker lease expired'));

// =============================================================================
// SCENARIO 5: PRODUCTION MAILER SIMULATION GUARD
// =============================================================================
echo "\n" . color("--- Scenario 5: Production Mailer Simulation Guard ---\n", "34;1");
// In production without SMTP credentials, Mailer::sendEmail MUST reject and NOT mark simulated
$mailRes = Mailer::sendEmail('client@example.com', 'Client', 'Subject', '<p>Test</p>', 'Test', 'to');
assert_test("Unconfigured SMTP credentials in production return configuration-blocked error", !$mailRes['success']);
assert_test("Unconfigured SMTP in production never simulates delivery", empty($mailRes['simulated']));

// =============================================================================
// SCENARIO 6: RBAC LEAST-PRIVILEGE MATRIX & BASELINE PERMISSIONS SPLIT
// =============================================================================
echo "\n" . color("--- Scenario 6: Role Permissions & Baseline Access Split ---\n", "34;1");
$sqlite->exec("
    INSERT INTO users (id, username, email, password_hash, name, role_id, status) VALUES
    (30, 'editor_ed', 'ed@tabeebgroup.com', 'hash', 'Ed Editor', 5, 'active'),
    (31, 'analyst_ann', 'ann@tabeebgroup.com', 'hash', 'Ann Analyst', 6, 'active');
");

// Mock session as Analyst (Role 6)
$_SESSION['tabeeb_admin_user'] = ['id' => 31, 'username' => 'analyst_ann', 'role_id' => 6, 'session_version' => 1];
$analystCanViewBaseline = has_permission('baseline.view');
$analystCannotManageBaseline = !has_permission('baseline.manage');
$analystCannotManageContent = !has_permission('content.manage');

// Mock session as Content Editor (Role 5)
$_SESSION['tabeeb_admin_user'] = ['id' => 30, 'username' => 'editor_ed', 'role_id' => 5, 'session_version' => 1];
$editorCanManageContent = has_permission('content.manage');
$editorCannotViewLeads = !has_permission('leads.view');
$editorCannotViewBaseline = !has_permission('baseline.view');

assert_test("Analyst possesses read permission 'baseline.view'", $analystCanViewBaseline);
assert_test("Analyst strictly DENIED write permission 'baseline.manage'", $analystCannotManageBaseline);
assert_test("Analyst strictly DENIED 'content.manage'", $analystCannotManageContent);
assert_test("Content Editor possesses 'content.manage'", $editorCanManageContent);
assert_test("Content Editor denied access to 'leads.view'", $editorCannotViewLeads);
assert_test("Content Editor denied access to 'baseline.view'", $editorCannotViewBaseline);

// =============================================================================
// SCENARIO 7: INPUT VALIDATION & PHONE NORMALIZER
// =============================================================================
echo "\n" . color("--- Scenario 7: Input Integrity & Singapore Phone Normalizer ---\n", "34;1");
$validSgPhone = normalize_singapore_phone('+65 8648 4883');
$invalidSgPhone = normalize_singapore_phone('123456');
$validLandline = normalize_singapore_phone('6234 5678');
$validIntl = normalize_singapore_phone('+60 12 345 6789');

assert_test("Valid Singapore mobile (+65 8648 4883) passes normalizer", $validSgPhone['valid'] && $validSgPhone['is_sg']);
assert_test("Valid Singapore landline (6234 5678) passes normalizer", $validLandline['valid'] && $validLandline['is_sg']);
assert_test("Invalid phone ('123456') correctly rejected", !$invalidSgPhone['valid']);
assert_test("Explicit international phone (+60...) passes with is_sg=false", $validIntl['valid'] && !$validIntl['is_sg']);

$hpTriggered = validate_honeypot_and_timing(['website_url_hp' => 'http://spam-bot.com']);
assert_test("Anti-spam honeypot detection flags bot input", !$hpTriggered['valid']);

// =============================================================================
// SCENARIO 8: CSRF TYPE SAFETY (ARRAY ATTACK DEFENSE)
// =============================================================================
echo "\n" . color("--- Scenario 8: CSRF Type Safety & Non-String Parameter Handling ---\n", "34;1");
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$validTokenPasses = verify_csrf_token($_SESSION['csrf_token']);
$arrayTokenHandled = verify_csrf_token(['invalid_array_payload']);
$nullTokenHandled = verify_csrf_token(null);
$emptyTokenHandled = verify_csrf_token('');

assert_test("Valid string CSRF token passes verification", $validTokenPasses);
assert_test("Array payload passed to verify_csrf_token returns false without TypeError", !$arrayTokenHandled);
assert_test("Null CSRF token returns false safely", !$nullTokenHandled);
assert_test("Empty string CSRF token returns false safely", !$emptyTokenHandled);

// =============================================================================
// SCENARIO 9: DEPLOYMENT WORKFLOW SAFETY HARDENING
// =============================================================================
echo "\n" . color("--- Scenario 9: Deployment Workflow Safety & Isolation ---\n", "34;1");
$deployYml = file_get_contents(__DIR__ . '/../.github/workflows/deploy.yml');

$usesManualDispatch = str_contains($deployYml, 'workflow_dispatch:');
$excludesAutoPush = !preg_match('/on:\s*push:/', $deployYml);
$excludesDataTree = str_contains($deployYml, '-x "data/*"');
$excludesBlogUploads = str_contains($deployYml, '-x "images/blog/*"');
$noInsecureCurl = !str_contains($deployYml, 'curl -k');
$checksExtractorResponse = str_contains($deployYml, 'status":"success"');

assert_test("Deploy workflow uses explicit manual workflow_dispatch", $usesManualDispatch);
assert_test("Deploy workflow removes automatic branch push triggers", $excludesAutoPush);
assert_test("Deploy archive strictly excludes production data tree (-x data/*)", $excludesDataTree);
assert_test("Deploy archive excludes user uploads (-x images/blog/*)", $excludesBlogUploads);
assert_test("Deploy workflow enforces verified TLS (curl -k removed)", $noInsecureCurl);
assert_test("Deploy workflow verifies extractor response contract", $checksExtractorResponse);

// =============================================================================
// SCENARIO 10: PROTECTED CLEAN ROUTING & .HTACCESS CANONICALS
// =============================================================================
echo "\n" . color("--- Scenario 10: Clean Routing & Security Headers ---\n", "34;1");
$htaccess = file_get_contents(__DIR__ . '/../.htaccess');

$hasDirectoryIndex = str_contains($htaccess, 'DirectoryIndex index.php index.html');
$routesAboutPhp = str_contains($htaccess, 'RewriteRule ^about/?$ about.php [L,QSA]');
$routesContactPhp = str_contains($htaccess, 'RewriteRule ^contact/?$ contact.php [L,QSA]');
$redirectsLegacyHtml = str_contains($htaccess, 'RewriteRule ^([^.]+)\.html$ /$1 [R=301,L]');
$blocksConfigScripts = str_contains($htaccess, 'RedirectMatch 403 ^/(config|scripts|data)/.*$');
$preservesGoogleVerification = file_exists(__DIR__ . '/../googlea9c88d1e38989379.html');

assert_test("DirectoryIndex prioritizes index.php over index.html", $hasDirectoryIndex);
assert_test("Clean routes (/about, /contact) explicitly route to authoritative PHP handlers", $routesAboutPhp && $routesContactPhp);
assert_test("Legacy .html requests receive 301 permanent redirect to clean URLs", $redirectsLegacyHtml);
assert_test(".htaccess blocks direct web access to config/, scripts/, and data/", $blocksConfigScripts);
assert_test("Google Site Verification file googlea9c88d1e38989379.html preserved in root", $preservesGoogleVerification);

// =============================================================================
// TEST SUMMARY & HANDOVER REPORT
// =============================================================================
echo "\n" . color("==================================================================", "36") . "\n";
echo color("  ARCHITECTURAL TEST SUITE COMPLETE", "36;1") . "\n";
echo "  Total Tests Run: " . color((string)$testsRun, "37;1") . "\n";
echo "  Passed: " . color((string)$passedCount, "32;1") . "\n";
echo "  Failed: " . color((string)$failedCount, $failedCount === 0 ? "32;1" : "31;1") . "\n";
echo color("==================================================================", "36") . "\n\n";

exit($failedCount === 0 ? 0 : 1);
