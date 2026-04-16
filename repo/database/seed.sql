-- Precision Portal - Seed Data

-- Default users (one per role)
-- Passwords are bcrypt hashed
INSERT IGNORE INTO `pp_users` (`username`, `password`, `role`, `status`, `failed_login_attempts`, `created_at`, `updated_at`) VALUES
('admin',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SYSTEM_ADMIN',        'ACTIVE', 0, NOW(), NOW()),
('planner1',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRODUCTION_PLANNER',  'ACTIVE', 0, NOW(), NOW()),
('coordinator1',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SERVICE_COORDINATOR', 'ACTIVE', 0, NOW(), NOW()),
('provider1',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PROVIDER',            'ACTIVE', 0, NOW(), NOW()),
('reviewer1',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'REVIEWER',            'ACTIVE', 0, NOW(), NOW()),
('reviewmanager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'REVIEW_MANAGER',      'ACTIVE', 0, NOW(), NOW()),
('specialist1',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRODUCT_SPECIALIST',  'ACTIVE', 0, NOW(), NOW()),
('operator1',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'OPERATOR',            'ACTIVE', 0, NOW(), NOW()),
('moderator1',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CONTENT_MODERATOR',   'ACTIVE', 0, NOW(), NOW()),
('finance1',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FINANCE_CLERK',       'ACTIVE', 0, NOW(), NOW());

-- NOTE: The bcrypt hash above is for "password" — the seeder PHP files use proper passwords.
-- For Docker testing, we re-seed with proper hashes via PHP after tables are created.

-- Reviewer Pool: seed the shipped `reviewer1` (id=5) with all specialties so
-- out-of-the-box review assignment flows can pass the pool governance gate.
INSERT IGNORE INTO `pp_reviewer_pool` (`reviewer_id`, `specialties`, `status`, `created_at`, `updated_at`) VALUES
(5, '["CPU","GPU","MOTHERBOARD"]', 'ACTIVE', NOW(), NOW());

-- Work Centers
INSERT IGNORE INTO `pp_work_centers` (`name`, `capacity_hours`, `status`, `created_at`, `updated_at`) VALUES
('Assembly Line A',   40.00, 'ACTIVE', NOW(), NOW()),
('Assembly Line B',   40.00, 'ACTIVE', NOW(), NOW()),
('Testing Lab',       35.00, 'ACTIVE', NOW(), NOW()),
('Packaging Station', 30.00, 'ACTIVE', NOW(), NOW());

-- Throttle Config
INSERT IGNORE INTO `pp_throttle_config` (`key`, `value`, `description`, `created_at`, `updated_at`) VALUES
('requests_per_minute',    60, 'Maximum API requests per minute per account',        NOW(), NOW()),
('appointments_per_hour',  10, 'Maximum appointment creations per hour per account',  NOW(), NOW()),
('postings_per_day',       20, 'Threshold for excessive posting anomaly flag',        NOW(), NOW()),
('cancellations_per_week',  5, 'Threshold for excessive cancellation anomaly flag',   NOW(), NOW()),
('step_up_score_below',   50, 'Risk/IP score below which step-up verification is required', NOW(), NOW());
