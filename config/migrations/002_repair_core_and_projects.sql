-- =============================================================================
-- Migration 002: Repair Core Integrity, Outbox, RBAC & Projects System
-- Tabeeb Contractor Portal
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Ensure notification_outbox table for asynchronous durable email dispatch
CREATE TABLE IF NOT EXISTS `notification_outbox` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NULL,
    `recipient_email` VARCHAR(150) NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `recipient_type` ENUM('to', 'cc', 'bcc') NOT NULL DEFAULT 'to',
    `subject` VARCHAR(255) NOT NULL,
    `body_html` MEDIUMTEXT NOT NULL,
    `body_plain` MEDIUMTEXT NOT NULL,
    `status` ENUM('queued', 'processing', 'sent', 'failed', 'simulated') NOT NULL DEFAULT 'queued',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
    `next_retry_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `provider_response` VARCHAR(255) NULL,
    `last_error` TEXT NULL,
    `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
    `worker_token` VARCHAR(64) NULL,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_outbox_queue` (`status`, `next_retry_at`),
    INDEX `idx_outbox_lead` (`lead_id`),
    CONSTRAINT `fk_outbox_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Projects & Project Media Schema (Phase F Client Media System)
CREATE TABLE IF NOT EXISTS `projects` (
    `id` VARCHAR(80) PRIMARY KEY,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `location_label` VARCHAR(150) NOT NULL,
    `public_location` VARCHAR(150) NOT NULL,
    `service_id` VARCHAR(80) NULL,
    `factual_summary` TEXT NOT NULL,
    `scope_details` TEXT NULL,
    `duration_label` VARCHAR(100) NULL,
    `materials_used` VARCHAR(255) NULL,
    `source_file` VARCHAR(255) NULL,
    `featured_image` VARCHAR(255) NOT NULL,
    `video_path` VARCHAR(255) NULL,
    `video_poster` VARCHAR(255) NULL,
    `publication_state` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `approval_status` ENUM('pending', 'approved', 'restricted') NOT NULL DEFAULT 'pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `og_title` VARCHAR(200) NULL,
    `og_description` VARCHAR(255) NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_proj_slug` (`slug`),
    INDEX `idx_proj_state` (`publication_state`),
    INDEX `idx_proj_srv` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_media` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` VARCHAR(80) NOT NULL,
    `media_stage` ENUM('before', 'in_progress', 'completed', 'gallery', 'video') NOT NULL DEFAULT 'gallery',
    `media_type` ENUM('image', 'video') NOT NULL DEFAULT 'image',
    `file_path` VARCHAR(255) NOT NULL,
    `thumbnail_path` VARCHAR(255) NULL,
    `caption` VARCHAR(255) NULL,
    `alt_text` VARCHAR(255) NOT NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pm_proj` (`project_id`),
    INDEX `idx_pm_stage` (`media_stage`),
    UNIQUE KEY `uniq_pm_proj_file` (`project_id`, `file_path`),
    CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Marketing & SEO Baseline Table (Day 1 Milestone)
CREATE TABLE IF NOT EXISTS `marketing_baseline` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `metric_key` VARCHAR(80) NOT NULL UNIQUE,
    `metric_category` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `metric_value` VARCHAR(100) NULL,
    `unit` VARCHAR(30) NULL,
    `window_start` DATE NULL,
    `window_end` DATE NULL,
    `comparison_window_start` DATE NULL,
    `comparison_window_end` DATE NULL,
    `source_name` VARCHAR(80) NOT NULL,
    `property_identifier` VARCHAR(150) NULL,
    `reporting_timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Singapore',
    `applied_filters` VARCHAR(255) NULL,
    `collection_status` ENUM('Measured', 'Not available', 'Pending access', 'Not previously tracked') NOT NULL DEFAULT 'Not previously tracked',
    `status_reason` VARCHAR(255) NULL,
    `evidence_reference` VARCHAR(255) NULL,
    `owner` VARCHAR(100) NOT NULL DEFAULT 'Technical SEO Specialist',
    `collected_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mb_cat` (`metric_category`),
    INDEX `idx_mb_key` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Google Search Console & GA4 Verification State (Day 2 & Day 3 Milestones)
CREATE TABLE IF NOT EXISTS `integration_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `service_type` ENUM('gsc_domain', 'gsc_prefix', 'ga4_stream', 'gtm_container') NOT NULL UNIQUE,
    `property_identifier` VARCHAR(150) NOT NULL,
    `verification_method` VARCHAR(80) NOT NULL,
    `token_or_record` TEXT NULL,
    `status` ENUM('Not configured', 'Verification instructions prepared', 'Awaiting DNS', 'Verification pending', 'Verified with dated evidence', 'Requires recheck') NOT NULL DEFAULT 'Not configured',
    `evidence_summary` TEXT NULL,
    `last_checked_at` DATETIME NULL,
    `verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Session Version & Security Columns in Users
-- Safe conditional additions using IF NOT EXISTS logic
SET @dbname = DATABASE();

-- session_version for instant revocation
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_version');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Rate limits unique key for atomic UPSERT
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'rate_limits' AND INDEX_NAME = 'uniq_rl_key_action');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `rate_limits` ADD UNIQUE INDEX `uniq_rl_key_action` (`rate_key`, `action_type`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6b. Idempotency key for atomic lead deduplication
SET @col_ik_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'idempotency_key');
SET @sql = IF(@col_ik_exists = 0, 'ALTER TABLE `leads` ADD COLUMN `idempotency_key` VARCHAR(64) NULL UNIQUE AFTER `ip_hash`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6c. Align notification_logs delivery_status ENUM to include simulated
SET @tbl_nl_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'notification_logs');
SET @sql = IF(@tbl_nl_exists > 0, 'ALTER TABLE `notification_logs` MODIFY COLUMN `delivery_status` ENUM(\'queued\', \'sent\', \'failed\', \'retrying\', \'simulated\') NOT NULL DEFAULT \'queued\'', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Seed Complete Least-Privilege Permissions Matrix for All 6 Roles
-- Ensure all permissions exist
INSERT INTO `permissions` (`slug`, `name`, `category`, `description`) VALUES
('leads.view', 'View Leads', 'Leads', 'Can view incoming leads and pipeline'),
('leads.view_assigned', 'View Assigned Leads', 'Leads', 'Can only view leads assigned to user'),
('leads.create', 'Create Lead', 'Leads', 'Can manually enter a new inquiry'),
('leads.edit', 'Edit Lead', 'Leads', 'Can update lead details and status'),
('leads.edit_assigned', 'Edit Assigned Leads', 'Leads', 'Can only update assigned leads'),
('leads.assign', 'Assign Lead', 'Leads', 'Can assign leads to staff members'),
('leads.delete', 'Delete Lead', 'Leads', 'Can archive or soft-delete a lead'),
('leads.export', 'Export Leads', 'Leads', 'Can export lead records to CSV'),
('users.manage', 'Manage Users', 'Access Control', 'Can create, edit, and deactivate users'),
('settings.manage', 'Manage Settings', 'Settings', 'Can update website settings and integrations'),
('notifications.manage', 'Manage Notifications', 'Settings', 'Can configure routing and recipients'),
('analytics.view', 'View Analytics', 'Analytics', 'Can inspect performance metrics and trends'),
('content.manage', 'Manage Content', 'Content', 'Can write and update blog posts & services'),
('projects.manage', 'Manage Projects', 'Projects', 'Can create, edit, and approve case study projects'),
('audit.view', 'View Audit Logs', 'Security', 'Can inspect the administrative audit trail'),
('business_info.manage', 'Manage Business Info', 'Business Tracker', 'Can review and publish business information'),
('baseline.view', 'View SEO Baseline', 'Marketing Baseline', 'Can view and export marketing baseline metrics'),
('baseline.manage', 'Manage SEO Baseline', 'Marketing Baseline', 'Can record and edit marketing baseline metrics')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Role 1: Super Administrator (All permissions)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Role 2: Administrator
-- Has operations, leads, content, settings, analytics, projects, business info (excludes user management and raw audit logs)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `slug` IN (
    'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.export',
    'settings.manage', 'notifications.manage', 'analytics.view', 'content.manage',
    'projects.manage', 'business_info.manage', 'baseline.view', 'baseline.manage'
);

-- Role 3: Sales Manager
-- Lead management, assignment, export, analytics
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `slug` IN (
    'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.export', 'analytics.view'
);

-- Role 4: Sales Representative
-- Only view and edit assigned leads
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `slug` IN (
    'leads.view_assigned', 'leads.create', 'leads.edit_assigned'
);

-- Role 5: Content Editor
-- Manage blog posts, services, and project case studies
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `id` FROM `permissions` WHERE `slug` IN (
    'content.manage', 'projects.manage'
);

-- Role 6: Analyst (Read-Only)
-- Analytics view and marketing baseline review/export only (cannot edit baseline metrics)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions` WHERE `slug` IN (
    'analytics.view', 'baseline.view'
);

-- 8. Seed Default GSC & GA4 Verification Rows
INSERT INTO `integration_verifications` 
(`service_type`, `property_identifier`, `verification_method`, `token_or_record`, `status`, `evidence_summary`) 
VALUES
('gsc_domain', 'tabeebgroup.com', 'DNS TXT', NULL, 'Verification instructions prepared', 'Awaiting client DNS TXT record deployment on authoritative nameserver'),
('gsc_prefix', 'https://tabeebgroup.com/', 'HTML verification file', 'googlea9c88d1e38989379.html', 'Verification pending', 'Verification file present on server (Account verification pending). Legacy file googlea9c88d1e38989379.html preserved on disk; awaiting client Search Console access'),
('gtm_container', 'GTM-XXXXXXX', 'GTM Container Snippet', NULL, 'Not configured', 'Awaiting authorized client GTM container ID and container publication'),
('ga4_stream', 'G-XXXXXXXXXX', 'GA4 Measurement via GTM', NULL, 'Not configured', 'Awaiting real GA4 Measurement ID and GTM tag configuration')
ON DUPLICATE KEY UPDATE `property_identifier` = VALUES(`property_identifier`);

-- 9. Seed Initial Baseline Metrics Structure (Phase C Day 1 Milestone)
INSERT INTO `marketing_baseline` 
(`metric_key`, `metric_category`, `title`, `unit`, `window_start`, `window_end`, `comparison_window_start`, `comparison_window_end`, `source_name`, `reporting_timezone`, `collection_status`, `status_reason`, `owner`)
VALUES
('ga4_organic_sessions', 'traffic', 'Organic Search Sessions (28 Days)', 'sessions', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Analytics 4', 'Asia/Singapore', 'Not previously tracked', 'Tracking container not previously configured or connected to live stream', 'Technical SEO Specialist'),
('ga4_organic_users', 'traffic', 'Organic Search Active Users (28 Days)', 'users', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Analytics 4', 'Asia/Singapore', 'Not previously tracked', 'Tracking container not previously configured or connected to live stream', 'Technical SEO Specialist'),
('ga4_total_sessions', 'traffic', 'Total Website Sessions (28 Days)', 'sessions', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Analytics 4', 'Asia/Singapore', 'Not previously tracked', 'GTM container and GA4 tag awaiting verified deployment', 'Technical SEO Specialist'),
('gsc_organic_clicks', 'search_performance', 'Search Console Total Clicks (Last 28 Days)', 'clicks', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Search Console', 'Asia/Singapore', 'Pending access', 'Search Console domain property access pending client authorization', 'Technical SEO Specialist'),
('gsc_organic_impressions', 'search_performance', 'Search Console Total Impressions (Last 28 Days)', 'impressions', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Search Console', 'Asia/Singapore', 'Pending access', 'Search Console domain property access pending client authorization', 'Technical SEO Specialist'),
('gsc_organic_ctr', 'search_performance', 'Search Console Average CTR (Last 28 Days)', '%', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Search Console', 'Asia/Singapore', 'Pending access', 'Search Console domain property access pending client authorization', 'Technical SEO Specialist'),
('gsc_average_position', 'search_performance', 'Search Console Average Position (Last 28 Days)', 'position', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Google Search Console', 'Asia/Singapore', 'Pending access', 'Search Console domain property access pending client authorization', 'Technical SEO Specialist'),
('target_keyword_renovation_sg', 'rankings', 'Rank: renovation contractor singapore', 'rank', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Manual SERP Snapshot', 'Asia/Singapore', 'Not available', 'No authenticated third-party rank tracker or pre-repair audit active', 'Technical SEO Specialist'),
('target_keyword_hdb_renovation', 'rankings', 'Rank: hdb renovation contractor singapore', 'rank', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Manual SERP Snapshot', 'Asia/Singapore', 'Not available', 'No authenticated third-party rank tracker or pre-repair audit active', 'Technical SEO Specialist'),
('target_keyword_commercial_reinstatement', 'rankings', 'Rank: commercial reinstatement contractor singapore', 'rank', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Manual SERP Snapshot', 'Asia/Singapore', 'Not available', 'No authenticated third-party rank tracker or pre-repair audit active', 'Technical SEO Specialist'),
('target_keyword_tiling_works', 'rankings', 'Rank: tiling contractor singapore', 'rank', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Manual SERP Snapshot', 'Asia/Singapore', 'Not available', 'No authenticated third-party rank tracker or pre-repair audit active', 'Technical SEO Specialist'),
('target_keyword_waterproofing', 'rankings', 'Rank: waterproofing specialist singapore', 'rank', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Manual SERP Snapshot', 'Asia/Singapore', 'Not available', 'No authenticated third-party rank tracker or pre-repair audit active', 'Technical SEO Specialist'),
('leads_persisted_total', 'conversions', 'Total Persisted Website Enquiries', 'leads', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Tabeeb MySQL CRM', 'Asia/Singapore', 'Not available', 'Pre-repair historical CRM baseline unavailable prior to database activation', 'CRM Administrator'),
('leads_qualified_total', 'conversions', 'Qualified Inquiries in CRM', 'leads', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Tabeeb MySQL CRM', 'Asia/Singapore', 'Not available', 'Pre-repair historical CRM baseline unavailable prior to database activation', 'CRM Administrator'),
('leads_won_total', 'conversions', 'Closed / Won Inquiries in CRM', 'leads', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Tabeeb MySQL CRM', 'Asia/Singapore', 'Not available', 'Pre-repair historical CRM baseline unavailable prior to database activation', 'CRM Administrator'),
('conversion_rate_session', 'conversions', 'Website Lead Conversion Rate (Enquiries / Eligible Sessions)', '%', '2026-09-12', '2026-10-09', '2026-08-15', '2026-09-11', 'Derived (CRM / GA4)', 'Asia/Singapore', 'Not available', 'Eligible session denominator unavailable prior to live GA4 tracking activation', 'Technical SEO Specialist')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 10. Seed Serangoon Avenue 3 Case Study (Draft Pending Approval)
INSERT INTO `projects` 
(`id`, `slug`, `title`, `location_label`, `public_location`, `service_id`, `factual_summary`, `scope_details`, `duration_label`, `materials_used`, `source_file`, `featured_image`, `video_path`, `video_poster`, `publication_state`, `approval_status`, `og_title`, `og_description`, `display_order`, `created_at`, `updated_at`)
VALUES
('proj_serangoon_01', 'serangoon-avenue-3-flooring-works', 'Serangoon Avenue 3 Flooring Works', 'Blk 234 Serangoon Avenue 3', 'Serangoon, Singapore', 'tiling-works',
 'Client-supplied photographs show the existing floor condition, work in progress and the completed tiled finish at Serangoon Avenue 3.',
 'Floor tile replacement and screeding as documented in client project board artwork.',
 NULL, NULL, 'WhatsApp Image 2026-10-09 at 7.11.04 PM.jpeg',
 '/images/projects/serangoon-featured-thumb.webp', '/images/projects/serangoon-board-video.mp4', '/images/projects/serangoon-board-full.webp',
 'draft', 'pending',
 'Serangoon Avenue 3 Flooring Works | Tabeeb Contractor',
 'Client-supplied photographs show the existing floor condition, work in progress and the completed tiled finish at Serangoon Avenue 3.',
 1, '2026-10-10 12:00:00', '2026-10-10 12:00:00')
ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`);

-- 11. Seed Serangoon Avenue 3 Media Items (Generated PKs, deduplicated on project_id and file_path)
INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
VALUES
('proj_serangoon_01', 'before', 'image', '/images/projects/serangoon-before.webp', 'Existing tile condition and floor preparation area', 'Existing floor condition prior to renovation', 1, '2026-10-10 12:00:00'),
('proj_serangoon_01', 'in_progress', 'image', '/images/projects/serangoon-in-progress.webp', 'Floor screeding and surface levelling stage', 'Cement screed flooring work in progress', 2, '2026-10-10 12:00:00'),
('proj_serangoon_01', 'completed', 'image', '/images/projects/serangoon-completed.webp', 'Completed tiled flooring finish with skirting', 'Completed tiled flooring finish', 3, '2026-10-10 12:00:00'),
('proj_serangoon_01', 'gallery', 'image', '/images/projects/serangoon-board-full.webp', 'Complete project documentation board provided by client', 'Full Serangoon Avenue 3 project board', 4, '2026-10-10 12:00:00'),
('proj_serangoon_01', 'gallery', 'image', '/images/projects/renovation-stages-collage.webp', 'Client reference composite showing renovation progression (Preserving anonymization blurs)', 'Renovation process stages collage', 5, '2026-10-10 12:00:00')
ON DUPLICATE KEY UPDATE `caption` = VALUES(`caption`), `alt_text` = VALUES(`alt_text`), `display_order` = VALUES(`display_order`);

SET FOREIGN_KEY_CHECKS = 1;
