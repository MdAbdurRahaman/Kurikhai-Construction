-- =============================================================================
-- Migration 003: Upgrade Idempotency, Outbox Worker Lease, Baseline Windows & RBAC Reconciliation
-- Tabeeb Contractor Portal
-- Supports non-destructive additive upgrades on previously migrated databases (002 applied).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @dbname = DATABASE();

-- 1. Ensure leads.idempotency_key exists and is uniquely indexed
SET @col_ik_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'idempotency_key');
SET @sql_ik = IF(@col_ik_exists = 0, 'ALTER TABLE `leads` ADD COLUMN `idempotency_key` VARCHAR(64) NULL UNIQUE AFTER `ip_hash`', 'SELECT 1');
PREPARE stmt_ik FROM @sql_ik;
EXECUTE stmt_ik;
DEALLOCATE PREPARE stmt_ik;

-- Ensure index idx_leads_idempotency exists
SET @idx_ik_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'leads' AND INDEX_NAME = 'idx_leads_idempotency');
SET @sql_idx_ik = IF(@idx_ik_exists = 0, 'ALTER TABLE `leads` ADD INDEX `idx_leads_idempotency` (`idempotency_key`)', 'SELECT 1');
PREPARE stmt_idx_ik FROM @sql_idx_ik;
EXECUTE stmt_idx_ik;
DEALLOCATE PREPARE stmt_idx_ik;

-- 2. Ensure worker_token exists in notification_outbox for fenced updates
SET @col_wt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'notification_outbox' AND COLUMN_NAME = 'worker_token');
SET @sql_wt = IF(@col_wt_exists = 0, 'ALTER TABLE `notification_outbox` ADD COLUMN `worker_token` VARCHAR(64) NULL AFTER `idempotency_key`', 'SELECT 1');
PREPARE stmt_wt FROM @sql_wt;
EXECUTE stmt_wt;
DEALLOCATE PREPARE stmt_wt;

-- 3. Ensure notification_logs.delivery_status enum supports 'simulated'
SET @tbl_nl_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'notification_logs');
SET @sql_nl = IF(@tbl_nl_exists > 0, 'ALTER TABLE `notification_logs` MODIFY COLUMN `delivery_status` ENUM(\'queued\', \'sent\', \'failed\', \'retrying\', \'simulated\') NOT NULL DEFAULT \'queued\'', 'SELECT 1');
PREPARE stmt_nl FROM @sql_nl;
EXECUTE stmt_nl;
DEALLOCATE PREPARE stmt_nl;

-- 4. Ensure project_media has unique index on (project_id, file_path) to prevent collisions
SET @idx_pm_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'project_media' AND INDEX_NAME = 'uniq_pm_proj_file');
SET @sql_idx_pm = IF(@idx_pm_exists = 0, 'ALTER TABLE `project_media` ADD UNIQUE INDEX `uniq_pm_proj_file` (`project_id`, `file_path`)', 'SELECT 1');
PREPARE stmt_idx_pm FROM @sql_idx_pm;
EXECUTE stmt_idx_pm;
DEALLOCATE PREPARE stmt_idx_pm;

-- 5. Safe deduplicated insertion of Serangoon Avenue 3 project media (never overwriting IDs 1-5)
INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
SELECT 'proj_serangoon_01', 'before', 'image', '/images/projects/serangoon-before.webp', 'Existing tile condition and floor preparation area', 'Existing floor condition prior to renovation', 1, '2026-10-10 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `project_media` WHERE `project_id` = 'proj_serangoon_01' AND `file_path` = '/images/projects/serangoon-before.webp') AND EXISTS (SELECT 1 FROM projects WHERE id='proj_serangoon_01');

INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
SELECT 'proj_serangoon_01', 'in_progress', 'image', '/images/projects/serangoon-in-progress.webp', 'Floor screeding and surface levelling stage', 'Cement screed flooring work in progress', 2, '2026-10-10 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `project_media` WHERE `project_id` = 'proj_serangoon_01' AND `file_path` = '/images/projects/serangoon-in-progress.webp') AND EXISTS (SELECT 1 FROM projects WHERE id='proj_serangoon_01');

INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
SELECT 'proj_serangoon_01', 'completed', 'image', '/images/projects/serangoon-completed.webp', 'Completed tiled flooring finish with skirting', 'Completed tiled flooring finish', 3, '2026-10-10 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `project_media` WHERE `project_id` = 'proj_serangoon_01' AND `file_path` = '/images/projects/serangoon-completed.webp') AND EXISTS (SELECT 1 FROM projects WHERE id='proj_serangoon_01');

INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
SELECT 'proj_serangoon_01', 'gallery', 'image', '/images/projects/serangoon-board-full.webp', 'Complete project documentation board provided by client', 'Full Serangoon Avenue 3 project board', 4, '2026-10-10 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `project_media` WHERE `project_id` = 'proj_serangoon_01' AND `file_path` = '/images/projects/serangoon-board-full.webp') AND EXISTS (SELECT 1 FROM projects WHERE id='proj_serangoon_01');

INSERT INTO `project_media` 
(`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`)
SELECT 'proj_serangoon_01', 'gallery', 'image', '/images/projects/renovation-stages-collage.webp', 'Client reference composite showing renovation progression (Preserving anonymization blurs)', 'Renovation process stages collage', 5, '2026-10-10 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `project_media` WHERE `project_id` = 'proj_serangoon_01' AND `file_path` = '/images/projects/renovation-stages-collage.webp') AND EXISTS (SELECT 1 FROM projects WHERE id='proj_serangoon_01');

-- 6. Reconcile integration_verifications status for gsc_prefix
UPDATE `integration_verifications`
SET `status` = 'Verification pending',
    `evidence_summary` = 'Verification file present on server (Account verification pending). Legacy file googlea9c88d1e38989379.html preserved on disk; awaiting client Search Console access'
WHERE `service_type` = 'gsc_prefix' 
  AND `status` NOT IN ('Verified with dated evidence');

-- 7. Ensure baseline permissions exist
INSERT INTO `permissions` (`slug`, `name`, `category`, `description`) VALUES
('baseline.view', 'View SEO Baseline', 'Marketing Baseline', 'Can view and export marketing baseline metrics'),
('baseline.manage', 'Manage SEO Baseline', 'Marketing Baseline', 'Can record and edit marketing baseline metrics')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- 8. Reconcile Role Permissions:
-- Super Admin (Role 1): Full access
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Administrator (Role 2): baseline.view and baseline.manage
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `slug` IN ('baseline.view', 'baseline.manage');

-- Analyst (Role 6): Read-only baseline.view and analytics.view
-- Explicitly revoke baseline.manage from Role 6 if previously granted
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE rp.role_id = 6 AND p.slug = 'baseline.manage';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions` WHERE `slug` IN ('analytics.view', 'baseline.view');

-- 9. Reconcile baseline measurement windows to frozen Day 1 period (2026-09-12 to 2026-10-09)
UPDATE `marketing_baseline`
SET `window_start` = '2026-09-12',
    `window_end` = '2026-10-09',
    `comparison_window_start` = '2026-08-15',
    `comparison_window_end` = '2026-09-11'
WHERE `window_start` IS NULL OR `window_start` = '0000-00-00';

SET FOREIGN_KEY_CHECKS = 1;
