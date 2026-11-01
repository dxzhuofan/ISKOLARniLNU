-- Run against lnu_scholarship_db after backing up the database.
-- Academic choices are kept in academic_catalog.php and validated against that
-- server-side catalog; these tables persist each student's selected choices.

CREATE TABLE IF NOT EXISTS applicant_academic_profiles (
    applicant_id INT NOT NULL PRIMARY KEY,
    year_level TINYINT UNSIGNED NOT NULL,
    college_code VARCHAR(12) NOT NULL,
    program_code VARCHAR(24) NOT NULL,
    program_type VARCHAR(20) NOT NULL,
    specialization VARCHAR(120) NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_academic_profile_program (college_code, program_code)
);

CREATE TABLE IF NOT EXISTS auth_otps (
    otp_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(150) NOT NULL,
    purpose VARCHAR(32) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_otp_lookup (email, purpose, used_at, expires_at),
    INDEX idx_auth_otp_user (user_id, purpose, used_at)
);

CREATE TABLE IF NOT EXISTS trusted_login_devices (
    device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trusted_device_token (token_hash),
    INDEX idx_trusted_device_user (user_id)
);

CREATE TABLE IF NOT EXISTS academic_change_requests (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    requested_year_level TINYINT UNSIGNED NOT NULL,
    requested_college_code VARCHAR(12) NOT NULL,
    requested_program_code VARCHAR(24) NOT NULL,
    requested_specialization VARCHAR(120) NULL,
    reason VARCHAR(1000) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    INDEX idx_academic_request_status (status, requested_at),
    INDEX idx_academic_request_applicant (applicant_id)
);
