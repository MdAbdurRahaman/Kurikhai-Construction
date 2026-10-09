-- =============================================================================
-- Tabeeb Contractor Portal - Database Schema (MySQL 5.7+ / MariaDB 10.3+)
-- Character Set: utf8mb4, Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. ROLES & PERMISSIONS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `category` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. USERS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id` INT UNSIGNED NOT NULL DEFAULT 2,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `force_password_change` TINYINT(1) NOT NULL DEFAULT 0,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `two_factor_secret_encrypted` VARCHAR(255) NULL,
    `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_users_role` (`role_id`),
    INDEX `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. LEADS & CRM PIPELINE
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_number` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(40) NOT NULL,
    `email` VARCHAR(150) NULL,
    `preferred_contact_method` ENUM('phone', 'whatsapp', 'email', 'any') NOT NULL DEFAULT 'whatsapp',
    `service_requested` VARCHAR(120) NOT NULL,
    `property_type` VARCHAR(80) NULL,
    `project_location` VARCHAR(150) NULL,
    `estimated_budget` VARCHAR(80) NULL,
    `desired_commencement` VARCHAR(80) NULL,
    `project_description` TEXT NOT NULL,
    `status` ENUM(
        'new', 'unreviewed', 'contacted', 'qualified',
        'site_visit_scheduled', 'site_visit_completed',
        'quotation_preparing', 'quotation_sent',
        'negotiation', 'won', 'lost', 'spam', 'archived'
    ) NOT NULL DEFAULT 'new',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `quality` ENUM('unrated', 'poor', 'fair', 'good', 'excellent') NOT NULL DEFAULT 'unrated',
    `assigned_user_id` INT UNSIGNED NULL,
    `source` VARCHAR(80) NOT NULL DEFAULT 'website_direct',
    `medium` VARCHAR(80) NULL,
    `campaign` VARCHAR(100) NULL,
    `search_term` VARCHAR(150) NULL,
    `landing_page` VARCHAR(255) NULL,
    `referrer` VARCHAR(255) NULL,
    `utm_params` JSON NULL,
    `consent_status` TINYINT(1) NOT NULL DEFAULT 1,
    `consent_timestamp` DATETIME NOT NULL,
    `submission_timestamp` DATETIME NOT NULL,
    `last_contacted_at` DATETIME NULL,
    `next_followup_at` DATETIME NULL,
    `closed_at` DATETIME NULL,
    `lost_reason` VARCHAR(255) NULL,
    `estimated_deal_value` DECIMAL(12,2) NULL,
    `final_deal_value` DECIMAL(12,2) NULL,
    `internal_notes_summary` TEXT NULL,
    `ip_hash` VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_leads_status` (`status`),
    INDEX `idx_leads_priority` (`priority`),
    INDEX `idx_leads_assigned` (`assigned_user_id`),
    INDEX `idx_leads_created` (`created_at`),
    INDEX `idx_leads_phone` (`phone`),
    CONSTRAINT `fk_leads_assigned` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `note` TEXT NOT NULL,
    `visibility` ENUM('internal', 'management_only') NOT NULL DEFAULT 'internal',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ln_lead` (`lead_id`),
    CONSTRAINT `fk_ln_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ln_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_activities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `activity_type` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_la_lead` (`lead_id`),
    INDEX `idx_la_type` (`activity_type`),
    CONSTRAINT `fk_la_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_la_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. NOTIFICATION ROUTING & LOGS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notification_type` VARCHAR(60) NOT NULL DEFAULT 'all_leads',
    `service_filter` VARCHAR(120) NULL,
    `source_filter` VARCHAR(80) NULL,
    `recipient_type` ENUM('to', 'cc', 'bcc') NOT NULL DEFAULT 'to',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_nr_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `service_filter` VARCHAR(120) NULL,
    `min_budget` DECIMAL(12,2) NULL,
    `lead_source` VARCHAR(80) NULL,
    `target_recipient_id` INT UNSIGNED NOT NULL,
    `escalate_to_id` INT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_nrule_target` FOREIGN KEY (`target_recipient_id`) REFERENCES `notification_recipients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NULL,
    `recipient_email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `delivery_status` ENUM('queued', 'sent', 'failed', 'retrying') NOT NULL DEFAULT 'queued',
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `provider_response` TEXT NULL,
    `error_message` TEXT NULL,
    `sent_at` DATETIME NULL,
    `retry_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nl_status` (`delivery_status`),
    INDEX `idx_nl_lead` (`lead_id`),
    CONSTRAINT `fk_nl_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. BUSINESS INFORMATION TRACKER
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_information` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_key` VARCHAR(80) NOT NULL UNIQUE,
    `category` VARCHAR(60) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `current_value` TEXT NULL,
    `draft_value` TEXT NULL,
    `status` ENUM(
        'pending',
        'received_needs_clarification',
        'client_confirmed',
        'document_verified',
        'needs_update',
        'not_applicable'
    ) NOT NULL DEFAULT 'pending',
    `evidence_ref` VARCHAR(255) NULL,
    `attachment_path` VARCHAR(255) NULL,
    `received_at` DATE NULL,
    `confirmed_at` DATE NULL,
    `review_expiry_at` DATE NULL,
    `owner_id` INT UNSIGNED NULL,
    `next_action` VARCHAR(255) NULL,
    `internal_notes` TEXT NULL,
    `publication_state` ENUM('draft', 'hidden', 'published') NOT NULL DEFAULT 'hidden',
    `published_at` DATETIME NULL,
    `approved_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_bi_cat` (`category`),
    INDEX `idx_bi_status` (`status`),
    INDEX `idx_bi_pub` (`publication_state`),
    CONSTRAINT `fk_bi_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bi_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_information_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT UNSIGNED NOT NULL,
    `previous_value` TEXT NULL,
    `new_value` TEXT NULL,
    `changed_by` INT UNSIGNED NULL,
    `change_summary` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bih_item` (`item_id`),
    CONSTRAINT `fk_bih_item` FOREIGN KEY (`item_id`) REFERENCES `business_information` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bih_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. SETTINGS & CONFIGURATION
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_group` VARCHAR(50) NOT NULL,
    `setting_key` VARCHAR(80) NOT NULL UNIQUE,
    `setting_value` LONGTEXT NULL,
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_grp` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. SERVICES
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id` VARCHAR(80) PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category_name` VARCHAR(100) NOT NULL,
    `overview` TEXT NOT NULL,
    `features_json` JSON NULL,
    `process_json` JSON NULL,
    `faqs_json` JSON NULL,
    `og_title` VARCHAR(200) NULL,
    `og_description` VARCHAR(255) NULL,
    `image` VARCHAR(255) NOT NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_srv_slug` (`slug`),
    INDEX `idx_srv_pub` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. BLOG POSTS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
    `id` VARCHAR(80) PRIMARY KEY,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `author` VARCHAR(100) NOT NULL,
    `excerpt` TEXT NULL,
    `content` LONGTEXT NOT NULL,
    `read_time` VARCHAR(30) NULL,
    `tags_json` JSON NULL,
    `image` VARCHAR(255) NULL,
    `status` ENUM('published', 'draft', 'archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL,
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_posts_slug` (`slug`),
    INDEX `idx_posts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. AUDIT LOGS & SECURITY LOGS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(80) NOT NULL,
    `entity_type` VARCHAR(60) NOT NULL,
    `entity_id` VARCHAR(80) NULL,
    `before_state` JSON NULL,
    `after_state` JSON NULL,
    `ip_hash` VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_al_action` (`action`),
    INDEX `idx_al_entity` (`entity_type`, `entity_id`),
    INDEX `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rate_key` VARCHAR(100) NOT NULL,
    `action_type` VARCHAR(40) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
    `reset_at` DATETIME NOT NULL,
    INDEX `idx_rl_lookup` (`rate_key`, `action_type`),
    INDEX `idx_rl_reset` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(60) NOT NULL,
    `page_path` VARCHAR(255) NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `lead_id` INT UNSIGNED NULL,
    `referrer` VARCHAR(255) NULL,
    `utm_source` VARCHAR(80) NULL,
    `utm_medium` VARCHAR(80) NULL,
    `utm_campaign` VARCHAR(80) NULL,
    `device_category` VARCHAR(30) NULL,
    `ip_hash` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ae_type` (`event_type`),
    INDEX `idx_ae_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. SEED INITIAL ROLES & PERMISSIONS
-- -----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full platform control, access control, audit, and settings management'),
(2, 'Administrator', 'administrator', 'Management of leads, operations, and published content'),
(3, 'Sales Manager', 'sales_manager', 'Lead assignment, pipeline escalation, and operational analytics'),
(4, 'Sales Representative', 'sales_rep', 'Lead follow-up, status updates, and note tracking'),
(5, 'Content Editor', 'editor', 'Blog articles and service social content management'),
(6, 'Analyst (Read Only)', 'analyst', 'Reporting and read-only pipeline analytics')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `permissions` (`slug`, `name`, `category`, `description`) VALUES
('leads.view', 'View Leads', 'Leads', 'Can view incoming leads and pipeline'),
('leads.create', 'Create Lead', 'Leads', 'Can manually enter a new inquiry'),
('leads.edit', 'Edit Lead', 'Leads', 'Can update lead details and status'),
('leads.assign', 'Assign Lead', 'Leads', 'Can assign leads to staff members'),
('leads.delete', 'Delete Lead', 'Leads', 'Can archive or soft-delete a lead'),
('leads.export', 'Export Leads', 'Leads', 'Can export lead records to CSV'),
('users.manage', 'Manage Users', 'Access Control', 'Can create, edit, and deactivate users'),
('settings.manage', 'Manage Settings', 'Settings', 'Can update website settings and integrations'),
('notifications.manage', 'Manage Notifications', 'Settings', 'Can configure routing and recipients'),
('analytics.view', 'View Analytics', 'Analytics', 'Can inspect performance metrics and trends'),
('content.manage', 'Manage Content', 'Content', 'Can write and update blog posts & services'),
('audit.view', 'View Audit Logs', 'Security', 'Can inspect the administrative audit trail'),
('business_info.manage', 'Manage Business Info', 'Business Tracker', 'Can review and publish business information')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Assign all permissions to Super Admin
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

SET FOREIGN_KEY_CHECKS = 1;
