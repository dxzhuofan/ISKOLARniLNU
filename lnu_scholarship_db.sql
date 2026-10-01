-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 06:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lnu_scholarship_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_records`
--

CREATE TABLE `academic_records` (
  `record_id` bigint(20) UNSIGNED NOT NULL,
  `applicant_id` bigint(20) UNSIGNED NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `gwa` decimal(5,2) DEFAULT NULL,
  `units_enrolled` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `applicant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city_municipality` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `degree_program_id` int(10) UNSIGNED DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_socioeconomic`
--

CREATE TABLE `applicant_socioeconomic` (
  `socio_id` bigint(20) UNSIGNED NOT NULL,
  `applicant_id` bigint(20) UNSIGNED NOT NULL,
  `family_monthly_income` decimal(12,2) DEFAULT NULL,
  `household_size` tinyint(3) UNSIGNED DEFAULT NULL,
  `number_of_dependents` tinyint(3) UNSIGNED DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `is_4ps_beneficiary` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `application_no` varchar(30) NOT NULL,
  `applicant_id` bigint(20) UNSIGNED NOT NULL,
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `period_id` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','under_review','for_compliance','approved','rejected','withdrawn') NOT NULL DEFAULT 'draft',
  `gwa_at_application` decimal(5,2) DEFAULT NULL,
  `year_level_at_application` tinyint(3) UNSIGNED DEFAULT NULL,
  `family_income_at_application` decimal(12,2) DEFAULT NULL,
  `dependents_at_application` tinyint(3) UNSIGNED DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `document_type_id` int(10) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size_bytes` int(10) UNSIGNED NOT NULL,
  `file_hash` char(64) NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `verification_status` enum('pending','verified','flagged','needs_resubmission') NOT NULL DEFAULT 'pending',
  `staff_remarks` varchar(500) DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_field_values`
--

CREATE TABLE `application_field_values` (
  `value_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `field_id` int(10) UNSIGNED NOT NULL,
  `field_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_periods`
--

CREATE TABLE `application_periods` (
  `period_id` int(10) UNSIGNED NOT NULL,
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `open_date` datetime NOT NULL,
  `close_date` datetime NOT NULL,
  `slot_limit` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','open','closed','archived') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `history_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(10) UNSIGNED NOT NULL,
  `college_code` varchar(20) NOT NULL,
  `college_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `degree_programs`
--

CREATE TABLE `degree_programs` (
  `degree_program_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `program_code` varchar(30) NOT NULL,
  `program_name` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disbursements`
--

CREATE TABLE `disbursements` (
  `disbursement_id` bigint(20) UNSIGNED NOT NULL,
  `grantee_id` bigint(20) UNSIGNED NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','released','on_hold','returned') NOT NULL DEFAULT 'pending',
  `reference_no` varchar(60) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `document_type_id` int(10) UNSIGNED NOT NULL,
  `type_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `ocr_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `ocr_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ocr_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`document_type_id`, `type_name`, `description`, `ocr_enabled`, `ocr_fields`) VALUES
(1, 'Certificate of Registration (COR)', 'Proof of current enrollment', 1, '[\"name\", \"student_number\", \"degree_program\", \"year_level\"]'),
(2, 'Grade Report / Transcript', 'Latest grades or GWA', 1, '[\"name\", \"student_number\", \"gwa\"]'),
(3, 'Certificate of Indigency', 'Issued by barangay / local government', 1, '[\"name\", \"address\"]'),
(4, 'Proof of Family Income', 'ITR, certificate of no income, etc.', 1, '[\"name\", \"income\"]'),
(5, 'Valid ID', 'Government or school-issued ID', 1, '[\"name\", \"birth_date\"]'),
(6, 'Birth Certificate', 'PSA-issued birth certificate', 1, '[\"name\", \"birth_date\"]');

-- --------------------------------------------------------

--
-- Table structure for table `duplicate_flags`
--

CREATE TABLE `duplicate_flags` (
  `flag_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `matched_application_id` bigint(20) UNSIGNED NOT NULL,
  `similarity_score` decimal(5,2) NOT NULL,
  `matched_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matched_fields`)),
  `review_status` enum('pending','confirmed_duplicate','not_duplicate') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `verification_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grantees`
--

CREATE TABLE `grantees` (
  `grantee_id` bigint(20) UNSIGNED NOT NULL,
  `applicant_id` bigint(20) UNSIGNED NOT NULL,
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','on_hold','graduated','terminated','withdrawn') NOT NULL DEFAULT 'active',
  `date_awarded` date DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grantee_terms`
--

CREATE TABLE `grantee_terms` (
  `term_id` bigint(20) UNSIGNED NOT NULL,
  `grantee_id` bigint(20) UNSIGNED NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `gwa` decimal(5,2) DEFAULT NULL,
  `units_enrolled` tinyint(3) UNSIGNED DEFAULT NULL,
  `renewal_status` enum('pending','renewed','not_renewed','completed') NOT NULL DEFAULT 'pending',
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_attempted` varchar(150) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `was_successful` tinyint(1) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ml_models`
--

CREATE TABLE `ml_models` (
  `model_id` int(10) UNSIGNED NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `algorithm` varchar(50) NOT NULL DEFAULT 'Random Forest',
  `model_version` varchar(30) NOT NULL,
  `dataset_source` enum('real','anonymized','synthetic') NOT NULL DEFAULT 'synthetic',
  `accuracy` decimal(5,4) DEFAULT NULL,
  `precision_score` decimal(5,4) DEFAULT NULL,
  `recall_score` decimal(5,4) DEFAULT NULL,
  `f1_score` decimal(5,4) DEFAULT NULL,
  `model_file_path` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `trained_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ml_predictions`
--

CREATE TABLE `ml_predictions` (
  `prediction_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `model_id` int(10) UNSIGNED NOT NULL,
  `review_priority` enum('Low','Medium','High') NOT NULL,
  `prob_low` decimal(5,4) DEFAULT NULL,
  `prob_medium` decimal(5,4) DEFAULT NULL,
  `prob_high` decimal(5,4) DEFAULT NULL,
  `predicted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED DEFAULT NULL,
  `channel` enum('in_app','email','sms') NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `recipient` varchar(150) DEFAULT NULL,
  `delivery_status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `provider_response` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ocr_field_checks`
--

CREATE TABLE `ocr_field_checks` (
  `check_id` bigint(20) UNSIGNED NOT NULL,
  `ocr_id` bigint(20) UNSIGNED NOT NULL,
  `field_name` varchar(60) NOT NULL,
  `applicant_value` varchar(255) DEFAULT NULL,
  `extracted_value` varchar(255) DEFAULT NULL,
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `result` enum('match','mismatch','unreadable') NOT NULL,
  `checked_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ocr_results`
--

CREATE TABLE `ocr_results` (
  `ocr_id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `ocr_engine` varchar(50) NOT NULL DEFAULT 'Tesseract',
  `processing_status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `extracted_text` mediumtext DEFAULT NULL,
  `extracted_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_fields`)),
  `avg_confidence` decimal(5,2) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privacy_consents`
--

CREATE TABLE `privacy_consents` (
  `consent_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `consent_type` enum('data_privacy_notice','terms_of_use') NOT NULL,
  `policy_version` varchar(20) NOT NULL,
  `consented_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_form_fields`
--

CREATE TABLE `program_form_fields` (
  `field_id` int(10) UNSIGNED NOT NULL,
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `field_key` varchar(60) NOT NULL,
  `field_label` varchar(200) NOT NULL,
  `field_type` enum('text','textarea','number','date','select','checkbox') NOT NULL DEFAULT 'text',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_requirements`
--

CREATE TABLE `program_requirements` (
  `requirement_id` int(10) UNSIGNED NOT NULL,
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `document_type_id` int(10) UNSIGNED NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `instructions` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `role_name` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'applicant', 'Student applying for or receiving a scholarship'),
(2, 'administrator', 'Scholarship office personnel / system administrator');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_programs`
--

CREATE TABLE `scholarship_programs` (
  `scholarship_program_id` int(10) UNSIGNED NOT NULL,
  `program_code` varchar(30) NOT NULL,
  `program_name` varchar(200) NOT NULL,
  `provider_type` enum('government','private','institutional') NOT NULL,
  `provider_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `benefit_description` varchar(255) DEFAULT NULL,
  `benefit_amount` decimal(10,2) DEFAULT NULL,
  `eligibility_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scholarship_programs`
--

INSERT INTO `scholarship_programs` (`scholarship_program_id`, `program_code`, `program_name`, `provider_type`, `provider_name`, `description`, `benefit_description`, `benefit_amount`, `eligibility_notes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'TES', 'Tertiary Education Subsidy', 'government', 'CHED-UniFAST', NULL, NULL, NULL, NULL, 1, NULL, '2026-10-02 00:24:24', '2026-10-02 00:24:24'),
(2, 'TDP', 'Tulong Dunong Program', 'government', 'CHED-UniFAST', NULL, NULL, NULL, NULL, 1, NULL, '2026-10-02 00:24:24', '2026-10-02 00:24:24'),
(3, 'SMF', 'SM Foundation Scholarship', 'private', 'SM Foundation', NULL, NULL, NULL, NULL, 1, NULL, '2026-10-02 00:24:24', '2026-10-02 00:24:24');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `staff_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `employee_no` varchar(30) DEFAULT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `position_title` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('pending_verification','active','suspended','deactivated') NOT NULL DEFAULT 'pending_verification',
  `email_verified_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `uq_academic_term` (`applicant_id`,`school_year`,`semester`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`applicant_id`),
  ADD UNIQUE KEY `uq_applicants_user` (`user_id`),
  ADD UNIQUE KEY `uq_applicants_student_no` (`student_number`),
  ADD KEY `idx_applicants_name_birth` (`last_name`,`first_name`,`birth_date`),
  ADD KEY `idx_applicants_college` (`college_id`),
  ADD KEY `idx_applicants_degree` (`degree_program_id`);

--
-- Indexes for table `applicant_socioeconomic`
--
ALTER TABLE `applicant_socioeconomic`
  ADD PRIMARY KEY (`socio_id`),
  ADD UNIQUE KEY `uq_socio_applicant` (`applicant_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `uq_applications_no` (`application_no`),
  ADD UNIQUE KEY `uq_applications_one_per_period` (`applicant_id`,`scholarship_program_id`,`period_id`),
  ADD KEY `idx_applications_status` (`status`),
  ADD KEY `idx_applications_program` (`scholarship_program_id`),
  ADD KEY `idx_applications_period` (`period_id`),
  ADD KEY `idx_applications_decided_by` (`decided_by`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_app_docs_application` (`application_id`),
  ADD KEY `idx_app_docs_type` (`document_type_id`),
  ADD KEY `idx_app_docs_hash` (`file_hash`),
  ADD KEY `fk_app_docs_verifier` (`verified_by`);

--
-- Indexes for table `application_field_values`
--
ALTER TABLE `application_field_values`
  ADD PRIMARY KEY (`value_id`),
  ADD UNIQUE KEY `uq_app_field` (`application_id`,`field_id`),
  ADD KEY `fk_afv_field` (`field_id`);

--
-- Indexes for table `application_periods`
--
ALTER TABLE `application_periods`
  ADD PRIMARY KEY (`period_id`),
  ADD UNIQUE KEY `uq_period_program_term` (`scholarship_program_id`,`school_year`,`semester`);

--
-- Indexes for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_status_hist_app` (`application_id`,`changed_at`),
  ADD KEY `fk_status_hist_user` (`changed_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_user` (`user_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`),
  ADD UNIQUE KEY `uq_colleges_code` (`college_code`),
  ADD UNIQUE KEY `uq_colleges_name` (`college_name`);

--
-- Indexes for table `degree_programs`
--
ALTER TABLE `degree_programs`
  ADD PRIMARY KEY (`degree_program_id`),
  ADD UNIQUE KEY `uq_degree_programs_code` (`program_code`),
  ADD KEY `idx_degree_programs_college` (`college_id`);

--
-- Indexes for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD PRIMARY KEY (`disbursement_id`),
  ADD KEY `idx_disb_grantee` (`grantee_id`,`school_year`,`semester`),
  ADD KEY `fk_disb_processor` (`processed_by`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`document_type_id`),
  ADD UNIQUE KEY `uq_document_types_name` (`type_name`);

--
-- Indexes for table `duplicate_flags`
--
ALTER TABLE `duplicate_flags`
  ADD PRIMARY KEY (`flag_id`),
  ADD UNIQUE KEY `uq_duplicate_pair` (`application_id`,`matched_application_id`),
  ADD KEY `idx_dup_matched` (`matched_application_id`),
  ADD KEY `idx_dup_status` (`review_status`),
  ADD KEY `fk_dup_reviewer` (`reviewed_by`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `uq_email_verif_token` (`token_hash`),
  ADD KEY `idx_email_verif_user` (`user_id`);

--
-- Indexes for table `grantees`
--
ALTER TABLE `grantees`
  ADD PRIMARY KEY (`grantee_id`),
  ADD UNIQUE KEY `uq_grantees_application` (`application_id`),
  ADD KEY `idx_grantees_applicant` (`applicant_id`),
  ADD KEY `idx_grantees_program` (`scholarship_program_id`),
  ADD KEY `idx_grantees_status` (`status`),
  ADD KEY `fk_grantees_creator` (`created_by`);

--
-- Indexes for table `grantee_terms`
--
ALTER TABLE `grantee_terms`
  ADD PRIMARY KEY (`term_id`),
  ADD UNIQUE KEY `uq_grantee_term` (`grantee_id`,`school_year`,`semester`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_login_attempts_email` (`email_attempted`,`attempted_at`),
  ADD KEY `idx_login_attempts_ip` (`ip_address`,`attempted_at`),
  ADD KEY `fk_login_attempts_user` (`user_id`);

--
-- Indexes for table `ml_models`
--
ALTER TABLE `ml_models`
  ADD PRIMARY KEY (`model_id`),
  ADD UNIQUE KEY `uq_ml_model_version` (`model_name`,`model_version`);

--
-- Indexes for table `ml_predictions`
--
ALTER TABLE `ml_predictions`
  ADD PRIMARY KEY (`prediction_id`),
  ADD KEY `idx_pred_application` (`application_id`,`predicted_at`),
  ADD KEY `idx_pred_model` (`model_id`),
  ADD KEY `idx_pred_priority` (`review_priority`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`),
  ADD KEY `idx_notif_application` (`application_id`),
  ADD KEY `idx_notif_delivery` (`delivery_status`);

--
-- Indexes for table `ocr_field_checks`
--
ALTER TABLE `ocr_field_checks`
  ADD PRIMARY KEY (`check_id`),
  ADD KEY `idx_ocr_checks_ocr` (`ocr_id`);

--
-- Indexes for table `ocr_results`
--
ALTER TABLE `ocr_results`
  ADD PRIMARY KEY (`ocr_id`),
  ADD KEY `idx_ocr_document` (`document_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD UNIQUE KEY `uq_pw_reset_token` (`token_hash`),
  ADD KEY `idx_pw_reset_user` (`user_id`);

--
-- Indexes for table `privacy_consents`
--
ALTER TABLE `privacy_consents`
  ADD PRIMARY KEY (`consent_id`),
  ADD KEY `idx_consents_user` (`user_id`);

--
-- Indexes for table `program_form_fields`
--
ALTER TABLE `program_form_fields`
  ADD PRIMARY KEY (`field_id`),
  ADD UNIQUE KEY `uq_form_field_key` (`scholarship_program_id`,`field_key`);

--
-- Indexes for table `program_requirements`
--
ALTER TABLE `program_requirements`
  ADD PRIMARY KEY (`requirement_id`),
  ADD UNIQUE KEY `uq_requirement` (`scholarship_program_id`,`document_type_id`),
  ADD KEY `fk_req_doctype` (`document_type_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_roles_name` (`role_name`);

--
-- Indexes for table `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  ADD PRIMARY KEY (`scholarship_program_id`),
  ADD UNIQUE KEY `uq_sch_programs_code` (`program_code`),
  ADD KEY `fk_sch_programs_creator` (`created_by`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `uq_staff_user` (`user_id`),
  ADD UNIQUE KEY `uq_staff_employee_no` (`employee_no`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_status` (`account_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_records`
--
ALTER TABLE `academic_records`
  MODIFY `record_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `applicant_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_socioeconomic`
--
ALTER TABLE `applicant_socioeconomic`
  MODIFY `socio_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `document_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_field_values`
--
ALTER TABLE `application_field_values`
  MODIFY `value_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_periods`
--
ALTER TABLE `application_periods`
  MODIFY `period_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_status_history`
--
ALTER TABLE `application_status_history`
  MODIFY `history_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `degree_programs`
--
ALTER TABLE `degree_programs`
  MODIFY `degree_program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disbursements`
--
ALTER TABLE `disbursements`
  MODIFY `disbursement_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `document_type_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `duplicate_flags`
--
ALTER TABLE `duplicate_flags`
  MODIFY `flag_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `verification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grantees`
--
ALTER TABLE `grantees`
  MODIFY `grantee_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grantee_terms`
--
ALTER TABLE `grantee_terms`
  MODIFY `term_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ml_models`
--
ALTER TABLE `ml_models`
  MODIFY `model_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ml_predictions`
--
ALTER TABLE `ml_predictions`
  MODIFY `prediction_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ocr_field_checks`
--
ALTER TABLE `ocr_field_checks`
  MODIFY `check_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ocr_results`
--
ALTER TABLE `ocr_results`
  MODIFY `ocr_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privacy_consents`
--
ALTER TABLE `privacy_consents`
  MODIFY `consent_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_form_fields`
--
ALTER TABLE `program_form_fields`
  MODIFY `field_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_requirements`
--
ALTER TABLE `program_requirements`
  MODIFY `requirement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  MODIFY `scholarship_program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `staff_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD CONSTRAINT `fk_academic_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_applicants_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applicants_degree` FOREIGN KEY (`degree_program_id`) REFERENCES `degree_programs` (`degree_program_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `applicant_socioeconomic`
--
ALTER TABLE `applicant_socioeconomic`
  ADD CONSTRAINT `fk_socio_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applications_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applications_period` FOREIGN KEY (`period_id`) REFERENCES `application_periods` (`period_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_applications_program` FOREIGN KEY (`scholarship_program_id`) REFERENCES `scholarship_programs` (`scholarship_program_id`) ON UPDATE CASCADE;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `fk_app_docs_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_docs_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`document_type_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_docs_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `application_field_values`
--
ALTER TABLE `application_field_values`
  ADD CONSTRAINT `fk_afv_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_afv_field` FOREIGN KEY (`field_id`) REFERENCES `program_form_fields` (`field_id`) ON UPDATE CASCADE;

--
-- Constraints for table `application_periods`
--
ALTER TABLE `application_periods`
  ADD CONSTRAINT `fk_period_program` FOREIGN KEY (`scholarship_program_id`) REFERENCES `scholarship_programs` (`scholarship_program_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD CONSTRAINT `fk_status_hist_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_status_hist_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `degree_programs`
--
ALTER TABLE `degree_programs`
  ADD CONSTRAINT `fk_degree_programs_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON UPDATE CASCADE;

--
-- Constraints for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD CONSTRAINT `fk_disb_grantee` FOREIGN KEY (`grantee_id`) REFERENCES `grantees` (`grantee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disb_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `duplicate_flags`
--
ALTER TABLE `duplicate_flags`
  ADD CONSTRAINT `fk_dup_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dup_matched` FOREIGN KEY (`matched_application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dup_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_email_verif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grantees`
--
ALTER TABLE `grantees`
  ADD CONSTRAINT `fk_grantees_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grantees_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grantees_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grantees_program` FOREIGN KEY (`scholarship_program_id`) REFERENCES `scholarship_programs` (`scholarship_program_id`) ON UPDATE CASCADE;

--
-- Constraints for table `grantee_terms`
--
ALTER TABLE `grantee_terms`
  ADD CONSTRAINT `fk_grantee_terms_grantee` FOREIGN KEY (`grantee_id`) REFERENCES `grantees` (`grantee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ml_predictions`
--
ALTER TABLE `ml_predictions`
  ADD CONSTRAINT `fk_pred_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pred_model` FOREIGN KEY (`model_id`) REFERENCES `ml_models` (`model_id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ocr_field_checks`
--
ALTER TABLE `ocr_field_checks`
  ADD CONSTRAINT `fk_ocr_checks_ocr` FOREIGN KEY (`ocr_id`) REFERENCES `ocr_results` (`ocr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ocr_results`
--
ALTER TABLE `ocr_results`
  ADD CONSTRAINT `fk_ocr_document` FOREIGN KEY (`document_id`) REFERENCES `application_documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pw_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `privacy_consents`
--
ALTER TABLE `privacy_consents`
  ADD CONSTRAINT `fk_consents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program_form_fields`
--
ALTER TABLE `program_form_fields`
  ADD CONSTRAINT `fk_form_field_program` FOREIGN KEY (`scholarship_program_id`) REFERENCES `scholarship_programs` (`scholarship_program_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program_requirements`
--
ALTER TABLE `program_requirements`
  ADD CONSTRAINT `fk_req_doctype` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`document_type_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_program` FOREIGN KEY (`scholarship_program_id`) REFERENCES `scholarship_programs` (`scholarship_program_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  ADD CONSTRAINT `fk_sch_programs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
