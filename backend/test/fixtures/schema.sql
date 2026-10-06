-- Subset of the team's lnu_scholarship_db (SCRUM-8) needed for authentication tests.
-- Table and key names match the original dump.
CREATE TABLE `roles` (
  `role_id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, `role_name` varchar(30) NOT NULL, `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`role_id`), UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `roles` VALUES (1,'applicant','Student applying for or receiving a scholarship'),(2,'administrator','Scholarship office personnel / system administrator');

CREATE TABLE `users` (
  `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `role_id` tinyint(3) UNSIGNED NOT NULL, `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('pending_verification','active','suspended','deactivated') NOT NULL DEFAULT 'pending_verification',
  `email_verified_at` datetime DEFAULT NULL, `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0, `locked_until` datetime DEFAULT NULL, `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(), `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`), UNIQUE KEY `uq_users_email` (`email`), KEY `idx_users_role` (`role_id`), KEY `idx_users_status` (`account_status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `colleges` (`college_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `college_code` varchar(20) NOT NULL, `college_name` varchar(150) NOT NULL, PRIMARY KEY (`college_id`)) ENGINE=InnoDB;
CREATE TABLE `degree_programs` (`degree_program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `college_id` int(10) UNSIGNED NOT NULL, `program_code` varchar(30) NOT NULL, `program_name` varchar(200) NOT NULL, PRIMARY KEY (`degree_program_id`)) ENGINE=InnoDB;

CREATE TABLE `applicants` (
  `applicant_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED NOT NULL, `student_number` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL, `middle_name` varchar(80) DEFAULT NULL, `last_name` varchar(80) NOT NULL, `suffix` varchar(10) DEFAULT NULL,
  `birth_date` date NOT NULL, `sex` enum('Male','Female') NOT NULL, `mobile_number` varchar(20) NOT NULL,
  `address_line` varchar(255) DEFAULT NULL, `barangay` varchar(100) DEFAULT NULL, `city_municipality` varchar(100) DEFAULT NULL, `province` varchar(100) DEFAULT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL, `degree_program_id` int(10) UNSIGNED DEFAULT NULL, `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(), `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`applicant_id`), UNIQUE KEY `uq_applicants_user` (`user_id`), UNIQUE KEY `uq_applicants_student_no` (`student_number`),
  CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_profiles` (
  `staff_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED NOT NULL, `employee_no` varchar(30) DEFAULT NULL,
  `first_name` varchar(80) NOT NULL, `middle_name` varchar(80) DEFAULT NULL, `last_name` varchar(80) NOT NULL, `position_title` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(), `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`), UNIQUE KEY `uq_staff_user` (`user_id`), UNIQUE KEY `uq_staff_employee_no` (`employee_no`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `attempt_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED DEFAULT NULL, `email_attempted` varchar(150) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL, `user_agent` varchar(255) DEFAULT NULL, `was_successful` tinyint(1) NOT NULL, `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`), KEY `idx_login_attempts_email` (`email_attempted`,`attempted_at`),
  CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED DEFAULT NULL, `action` varchar(60) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL, `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`), KEY `idx_audit_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `reset_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED NOT NULL, `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL, `used_at` datetime DEFAULT NULL, `ip_address` varchar(45) DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`), UNIQUE KEY `uq_pw_reset_token` (`token_hash`),
  CONSTRAINT `fk_pw_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `privacy_consents` (
  `consent_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` bigint(20) UNSIGNED NOT NULL,
  `consent_type` enum('data_privacy_notice','terms_of_use') NOT NULL, `policy_version` varchar(20) NOT NULL,
  `consented_at` datetime NOT NULL DEFAULT current_timestamp(), `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`consent_id`),
  CONSTRAINT `fk_consents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
