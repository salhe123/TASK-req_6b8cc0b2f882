-- Precision Portal - Full Database Schema
-- All tables use pp_ prefix

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Users ───
CREATE TABLE IF NOT EXISTS `pp_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('PRODUCTION_PLANNER','OPERATOR','SERVICE_COORDINATOR','PROVIDER','REVIEWER','REVIEW_MANAGER','PRODUCT_SPECIALIST','CONTENT_MODERATOR','FINANCE_CLERK','SYSTEM_ADMIN') NOT NULL,
    `status` ENUM('ACTIVE','LOCKED','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `failed_login_attempts` INT NOT NULL DEFAULT 0,
    `locked_at` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Appointments ───
-- Sensitive street address is stored *only* as AES-256-CBC ciphertext in
-- `location_encrypted`. A truncated non-sensitive `location_hint` (first token
-- up to 16 chars, e.g. "Building A") is kept for list-view display/search.
-- The legacy `location` column is retained as NOT NULL for framework
-- compatibility but the model mutator always writes empty string.
CREATE TABLE IF NOT EXISTS `pp_appointments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `date_time` DATETIME NOT NULL,
    `location` VARCHAR(16) NOT NULL DEFAULT '',
    `location_hint` VARCHAR(64) NOT NULL DEFAULT '',
    `location_encrypted` TEXT NOT NULL,
    `status` ENUM('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_provider` (`provider_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_datetime` (`date_time`),
    FOREIGN KEY (`customer_id`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`provider_id`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Appointment History (immutable) ───
CREATE TABLE IF NOT EXISTS `pp_appointment_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(20) NULL,
    `to_status` VARCHAR(20) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `reason` TEXT NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_appointment` (`appointment_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`appointment_id`) REFERENCES `pp_appointments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only guards on appointment_history (parity with audit_logs).
DROP TRIGGER IF EXISTS pp_appointment_history_no_update;
DROP TRIGGER IF EXISTS pp_appointment_history_no_delete;
DELIMITER //
CREATE TRIGGER pp_appointment_history_no_update
BEFORE UPDATE ON pp_appointment_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'UPDATE not allowed on appointment_history: append-only table';
END//
CREATE TRIGGER pp_appointment_history_no_delete
BEFORE DELETE ON pp_appointment_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'DELETE not allowed on appointment_history: append-only table';
END//
DELIMITER ;

-- ─── Appointment Attachments ───
CREATE TABLE IF NOT EXISTS `pp_appointment_attachments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_appointment` (`appointment_id`),
    FOREIGN KEY (`appointment_id`) REFERENCES `pp_appointments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Work Centers ───
CREATE TABLE IF NOT EXISTS `pp_work_centers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `capacity_hours` DECIMAL(8,2) NOT NULL,
    `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MPS Plans ───
CREATE TABLE IF NOT EXISTS `pp_mps_plans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `work_center_id` INT UNSIGNED NOT NULL,
    `week_start` DATE NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `planned_hours` DECIMAL(8,2) NOT NULL,
    `status` ENUM('DRAFT','ACTIVE','COMPLETED') NOT NULL DEFAULT 'DRAFT',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_workcenter` (`work_center_id`),
    INDEX `idx_week` (`week_start`),
    FOREIGN KEY (`work_center_id`) REFERENCES `pp_work_centers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Work Orders ───
CREATE TABLE IF NOT EXISTS `pp_work_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `mps_id` INT UNSIGNED NOT NULL,
    `work_center_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity_planned` INT UNSIGNED NOT NULL,
    `quantity_completed` INT UNSIGNED NOT NULL DEFAULT 0,
    `quantity_rework` INT UNSIGNED NOT NULL DEFAULT 0,
    `downtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    `reason_code` VARCHAR(50) NULL,
    `status` ENUM('PENDING','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'PENDING',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_mps` (`mps_id`),
    INDEX `idx_workcenter` (`work_center_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`mps_id`) REFERENCES `pp_mps_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`work_center_id`) REFERENCES `pp_work_centers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Products ───
CREATE TABLE IF NOT EXISTS `pp_products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `category` ENUM('CPU','GPU','MOTHERBOARD') NOT NULL,
    `vendor_name` VARCHAR(255) NOT NULL DEFAULT '',
    `specs` JSON NOT NULL,
    `normalized_specs` JSON NULL,
    `fingerprint` VARCHAR(64) NULL,
    `completeness_score` DECIMAL(5,4) NULL,
    `consistency_score` DECIMAL(5,4) NULL,
    `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
    `submitted_by` INT UNSIGNED NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`),
    INDEX `idx_fingerprint` (`fingerprint`),
    INDEX `idx_vendor` (`vendor_name`),
    FOREIGN KEY (`submitted_by`) REFERENCES `pp_users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Product Scores ───
CREATE TABLE IF NOT EXISTS `pp_product_scores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `completeness_score` DECIMAL(5,4) NOT NULL,
    `consistency_score` DECIMAL(5,4) NOT NULL,
    `details` JSON NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`product_id`) REFERENCES `pp_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Moderation Decisions ───
-- `moderator_id` is NULL while a row is queued for review; populated once a
-- human moderator acts on it. `status` tracks the queue lifecycle so
-- similarity-flagged pairs do not look like completed APPROVE decisions.
CREATE TABLE IF NOT EXISTS `pp_moderation_decisions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_type` ENUM('PRODUCT','MERGE') NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `action` ENUM('APPROVE','REJECT','MERGE','DISTINCT','REVIEW') NOT NULL,
    `status` ENUM('PENDING','RESOLVED') NOT NULL DEFAULT 'RESOLVED',
    `moderator_id` INT UNSIGNED NULL,
    `before_snapshot` JSON NULL,
    `after_snapshot` JSON NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `resolved_at` DATETIME NULL,
    INDEX `idx_item` (`item_type`, `item_id`),
    INDEX `idx_moderator` (`moderator_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`moderator_id`) REFERENCES `pp_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Reviewer Pool ───
-- Records that a reviewer user is active in the pool and which product
-- categories they are qualified to review. `specialties` is a JSON array of
-- category values (e.g. ["CPU", "GPU"]).
CREATE TABLE IF NOT EXISTS `pp_reviewer_pool` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reviewer_id` INT UNSIGNED NOT NULL,
    `specialties` JSON NOT NULL,
    `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_reviewer` (`reviewer_id`),
    FOREIGN KEY (`reviewer_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Reviewer Vendor History ───
CREATE TABLE IF NOT EXISTS `pp_reviewer_vendor_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reviewer_id` INT UNSIGNED NOT NULL,
    `vendor_name` VARCHAR(255) NOT NULL,
    `association_start` DATE NOT NULL,
    `association_end` DATE NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_reviewer` (`reviewer_id`),
    INDEX `idx_vendor` (`vendor_name`),
    FOREIGN KEY (`reviewer_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Scorecards ───
CREATE TABLE IF NOT EXISTS `pp_scorecards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`created_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Scorecard Dimensions ───
CREATE TABLE IF NOT EXISTS `pp_scorecard_dimensions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scorecard_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `weight` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_scorecard` (`scorecard_id`),
    FOREIGN KEY (`scorecard_id`) REFERENCES `pp_scorecards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Review Assignments ───
CREATE TABLE IF NOT EXISTS `pp_review_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `reviewer_id` INT UNSIGNED NOT NULL,
    `blind` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('ASSIGNED','SUBMITTED','PUBLISHED') NOT NULL DEFAULT 'ASSIGNED',
    `assigned_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_reviewer` (`reviewer_id`),
    FOREIGN KEY (`product_id`) REFERENCES `pp_products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewer_id`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Review Versions ───
CREATE TABLE IF NOT EXISTS `pp_review_versions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NOT NULL,
    `scorecard_id` INT UNSIGNED NOT NULL,
    `ratings` JSON NOT NULL,
    `total_score` DECIMAL(5,2) NULL,
    `status` ENUM('DRAFT','SUBMITTED','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
    `published_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_assignment` (`assignment_id`),
    INDEX `idx_scorecard` (`scorecard_id`),
    FOREIGN KEY (`assignment_id`) REFERENCES `pp_review_assignments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`scorecard_id`) REFERENCES `pp_scorecards`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Payments ───
CREATE TABLE IF NOT EXISTS `pp_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `import_batch_id` VARCHAR(64) NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payer_name` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255) NOT NULL,
    `payment_date` DATE NOT NULL,
    `status` ENUM('PENDING','RECONCILED','DISPUTED') NOT NULL DEFAULT 'PENDING',
    `checksum` VARCHAR(64) NOT NULL,
    `source_row` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_batch` (`import_batch_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`payment_date`),
    INDEX `idx_checksum` (`checksum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Receipts ───
CREATE TABLE IF NOT EXISTS `pp_receipts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `receipt_number` VARCHAR(50) NOT NULL,
    `signature` VARCHAR(64) NOT NULL,
    `fingerprint` VARCHAR(64) NOT NULL,
    `issued_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_receipt_number` (`receipt_number`),
    INDEX `idx_payment` (`payment_id`),
    INDEX `idx_fingerprint` (`fingerprint`),
    FOREIGN KEY (`payment_id`) REFERENCES `pp_payments`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`appointment_id`) REFERENCES `pp_appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Settlements ───
CREATE TABLE IF NOT EXISTS `pp_settlements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `week_ending` DATE NOT NULL,
    `platform_fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    `total_settled` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `provider_payouts` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `transaction_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('PENDING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_week` (`week_ending`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`created_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Ledger Entries ───
-- `appointment_id` is UNIQUE: each completed appointment can only ever appear
-- in one ledger row across the whole life of the system. This is the hard
-- idempotency guarantee — re-running settlement for the same week cannot
-- create a duplicate payout even if the application-layer filter is bypassed.
CREATE TABLE IF NOT EXISTS `pp_ledger_entries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `settlement_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL,
    `platform_fee` DECIMAL(12,2) NOT NULL,
    `net_amount` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_appointment` (`appointment_id`),
    INDEX `idx_settlement` (`settlement_id`),
    INDEX `idx_provider` (`provider_id`),
    FOREIGN KEY (`settlement_id`) REFERENCES `pp_settlements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`appointment_id`) REFERENCES `pp_appointments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Provider Payout Accounts (encrypted at rest) ───
-- All banking identifiers are stored as AES-256-CBC ciphertext. The legacy
-- "last-4 display" is never stored plaintext — `account_last4_hash` holds an
-- HMAC-SHA256 so equality lookups remain possible without leaking digits, and
-- `account_last4_masked` is a fixed "****" render for UI rows. The real last
-- four can be recovered only by decrypting `account_number_encrypted`.
CREATE TABLE IF NOT EXISTS `pp_provider_payout_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT UNSIGNED NOT NULL,
    `bank_name` VARCHAR(120) NOT NULL,
    `account_number_encrypted` TEXT NOT NULL,
    `routing_number_encrypted` TEXT NOT NULL,
    `account_last4_hash` CHAR(64) NOT NULL,
    `account_last4_masked` CHAR(8) NOT NULL DEFAULT '****',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_provider` (`provider_id`),
    INDEX `idx_last4_hash` (`account_last4_hash`),
    FOREIGN KEY (`provider_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Work Order History (immutable) ───
CREATE TABLE IF NOT EXISTS `pp_work_order_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(20) NULL,
    `to_status` VARCHAR(20) NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `reason` TEXT NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_work_order` (`work_order_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`work_order_id`) REFERENCES `pp_work_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `pp_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only guards on work_order_history
DROP TRIGGER IF EXISTS pp_work_order_history_no_update;
DROP TRIGGER IF EXISTS pp_work_order_history_no_delete;
DELIMITER //
CREATE TRIGGER pp_work_order_history_no_update
BEFORE UPDATE ON pp_work_order_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'UPDATE not allowed on work_order_history: append-only table';
END//
CREATE TRIGGER pp_work_order_history_no_delete
BEFORE DELETE ON pp_work_order_history
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'DELETE not allowed on work_order_history: append-only table';
END//
DELIMITER ;

-- ─── Risk Scores ───
CREATE TABLE IF NOT EXISTS `pp_risk_scores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `success_rate` DECIMAL(5,4) NOT NULL,
    `dispute_rate` DECIMAL(5,4) NOT NULL,
    `cancellation_rate` DECIMAL(5,4) NOT NULL,
    `calculated_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_score` (`score`),
    FOREIGN KEY (`user_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Anomaly Flags ───
CREATE TABLE IF NOT EXISTS `pp_anomaly_flags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `flag_type` VARCHAR(50) NOT NULL,
    `details` JSON NULL,
    `status` ENUM('OPEN','CLEARED') NOT NULL DEFAULT 'OPEN',
    `cleared_by` INT UNSIGNED NULL,
    `cleared_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`flag_type`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cleared_by`) REFERENCES `pp_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Audit Logs (append-only) ───
CREATE TABLE IF NOT EXISTS `pp_audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `before_data` JSON NULL,
    `after_data` JSON NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only triggers
DROP TRIGGER IF EXISTS pp_audit_logs_no_update;
DROP TRIGGER IF EXISTS pp_audit_logs_no_delete;

DELIMITER //

CREATE TRIGGER pp_audit_logs_no_update
BEFORE UPDATE ON pp_audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'UPDATE not allowed on audit_logs: append-only table';
END//

CREATE TRIGGER pp_audit_logs_no_delete
BEFORE DELETE ON pp_audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'DELETE not allowed on audit_logs: append-only table';
END//

DELIMITER ;

-- ─── Device Fingerprints ───
CREATE TABLE IF NOT EXISTS `pp_device_fingerprints` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `fingerprint_hash` VARCHAR(64) NOT NULL,
    `user_agent` VARCHAR(500) NOT NULL,
    `screen_resolution` VARCHAR(20) NULL,
    `timezone` VARCHAR(50) NULL,
    `fonts_hash` VARCHAR(64) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `first_seen_at` DATETIME NOT NULL,
    `last_seen_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_fingerprint` (`fingerprint_hash`),
    INDEX `idx_ip` (`ip_address`),
    FOREIGN KEY (`user_id`) REFERENCES `pp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Throttle Config ───
CREATE TABLE IF NOT EXISTS `pp_throttle_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL,
    `value` INT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
