CREATE TABLE IF NOT EXISTS `site_measurement_events` (
 `event_id` VARCHAR(64) PRIMARY KEY,
 `session_hash` CHAR(64) NOT NULL,
 `visitor_hash` CHAR(64) NOT NULL,
 `event_name` VARCHAR(32) NOT NULL,
 `page_path` VARCHAR(200) NOT NULL,
 `channel` VARCHAR(24) NOT NULL,
 `source_label` VARCHAR(80) NOT NULL DEFAULT '',
 `medium_label` VARCHAR(80) NOT NULL DEFAULT '',
 `campaign_label` VARCHAR(80) NOT NULL DEFAULT '',
 `device_type` VARCHAR(16) NOT NULL,
 `detail` VARCHAR(32) NOT NULL DEFAULT '',
 `metric_value` DECIMAL(12,4) NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX `idx_analytics_date_event` (`created_at`,`event_name`),
 INDEX `idx_analytics_session` (`session_hash`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `analytics_report_cache` (
 `report_key` CHAR(64) PRIMARY KEY,
 `report_json` LONGTEXT NOT NULL,
 `fetched_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
