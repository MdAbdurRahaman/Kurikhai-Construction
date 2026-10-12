-- Additive migration; older version numbers remain recorded.
SET @db=DATABASE();
SET @q=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='leads' AND COLUMN_NAME='payload_hash')=0,'ALTER TABLE leads ADD COLUMN payload_hash CHAR(64) NULL AFTER idempotency_key','SELECT 1');
PREPARE st FROM @q;
EXECUTE st;
DEALLOCATE PREPARE st;
CREATE TABLE IF NOT EXISTS baseline_snapshots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(150) NOT NULL, window_start DATE NOT NULL, window_end DATE NOT NULL,
 reporting_timezone VARCHAR(50) NOT NULL, observed_at DATETIME NOT NULL,
 metrics_json LONGTEXT NOT NULL, evidence_reference VARCHAR(255) NOT NULL,
 created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS repair_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, repair_key VARCHAR(190) NOT NULL UNIQUE,
 entity_type VARCHAR(80) NOT NULL, entity_id VARCHAR(100) NOT NULL,
 before_json LONGTEXT NOT NULL, after_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS content_review_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(30) NOT NULL,
 entity_id VARCHAR(100) NOT NULL, reason VARCHAR(255) NOT NULL,
 UNIQUE KEY uniq_content_review(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions(slug,name,category,description) VALUES ('projects.approve','Approve project publication','Content','Record dated client approval before publishing');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug='projects.approve' WHERE r.slug IN ('super_admin','administrator');
SET @q=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limits' AND INDEX_NAME='uniq_rate_key_action')=0,'ALTER TABLE rate_limits ADD UNIQUE KEY uniq_rate_key_action(rate_key,action_type)','SELECT 1');
PREPARE st FROM @q;
EXECUTE st;
DEALLOCATE PREPARE st;
