-- MicroFin Full Backup (PHP Export)
-- Generated: 2026-04-08 08:08:46
-- Method: PHP PDO Fallback

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `amortization_schedule`;
CREATE TABLE `amortization_schedule` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_number` int NOT NULL,
  `due_date` date NOT NULL,
  `beginning_balance` decimal(12,2) NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_amount` decimal(12,2) NOT NULL,
  `total_payment` decimal(12,2) NOT NULL,
  `ending_balance` decimal(12,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Overdue','Partially Paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `amount_paid` decimal(12,2) DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `days_late` int DEFAULT '0',
  `penalty_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `loan_id` (`loan_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `amortization_schedule_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE,
  CONSTRAINT `amortization_schedule_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
  `app_document_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` int NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`app_document_id`),
  KEY `application_id` (`application_id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `application_documents_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `application_documents_ibfk_3` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_tenant_date` (`tenant_id`,`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `initiated_by` int DEFAULT NULL,
  `backup_type` enum('full','tenant') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'full',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size_bytes` bigint DEFAULT '0',
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Success','Failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Success',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `client_documents`;
CREATE TABLE `client_documents` (
  `client_document_id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` int NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `document_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `verification_status` enum('Pending','Verified','Rejected','Expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `verification_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`client_document_id`),
  KEY `client_id` (`client_id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_tenant_client` (`tenant_id`,`client_id`),
  CONSTRAINT `client_documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `client_documents_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `client_documents_ibfk_3` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`document_type_id`),
  CONSTRAINT `client_documents_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `client_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Filipino',
  `contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternate_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_address` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_house_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_street` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_barangay` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `present_postal_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_house_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_street` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_barangay` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_postal_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `same_as_present` tinyint(1) DEFAULT '0',
  `employment_status` enum('Employed','Self-Employed','Freelancer','Contractual','Part-Time','OFW','Student','Unemployed','Retired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `employer_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `other_income_source` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `other_income_amount` decimal(12,2) DEFAULT NULL,
  `comaker_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_income` decimal(12,2) DEFAULT NULL,
  `comaker_house_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_street` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_barangay` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_postal_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_status` enum('Active','Inactive','Blacklisted','Deceased') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `blacklist_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_date` date NOT NULL,
  `registered_by` int DEFAULT NULL,
  `document_verification_status` enum('Unverified','Pending','Verified','Approved','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Unverified',
  `verification_rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `credit_limit` decimal(15,2) DEFAULT '0.00',
  `last_seen_credit_limit` decimal(15,2) DEFAULT '0.00',
  `seen_approval_modal` tinyint(1) DEFAULT '0',
  `credit_limit_tier` int DEFAULT '0',
  `policy_metadata` json DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`),
  UNIQUE KEY `uq_tenant_user` (`tenant_id`,`user_id`),
  UNIQUE KEY `uq_tenant_clientcode` (`tenant_id`,`client_code`),
  KEY `user_id` (`user_id`),
  KEY `registered_by` (`registered_by`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `clients_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `clients_ibfk_3` FOREIGN KEY (`registered_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_investigations`;
CREATE TABLE `credit_investigations` (
  `ci_id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conducted_by` int NOT NULL,
  `investigation_date` date NOT NULL,
  `business_exists` tinyint(1) DEFAULT NULL,
  `business_location_verified` tinyint(1) DEFAULT NULL,
  `business_operational` tinyint(1) DEFAULT NULL,
  `business_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `character_rating` enum('Excellent','Good','Fair','Poor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reputation_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `income_verified` tinyint(1) DEFAULT NULL,
  `assets_verified` tinyint(1) DEFAULT NULL,
  `existing_obligations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `references_contacted` tinyint(1) DEFAULT NULL,
  `references_feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendation` enum('Highly Recommended','Recommended','Conditional','Not Recommended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `investigation_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ci_report_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ci_id`),
  KEY `client_id` (`client_id`),
  KEY `conducted_by` (`conducted_by`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `credit_investigations_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `credit_investigations_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `credit_investigations_ibfk_3` FOREIGN KEY (`conducted_by`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_scores`;
CREATE TABLE `credit_scores` (
  `score_id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci_id` int DEFAULT NULL,
  `credit_score` int DEFAULT '0',
  `credit_rating` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_loan_amount` decimal(12,2) DEFAULT NULL,
  `recommended_interest_rate` decimal(5,2) DEFAULT NULL,
  `computed_by` int DEFAULT NULL,
  `computation_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `score_metadata` json DEFAULT NULL,
  PRIMARY KEY (`score_id`),
  KEY `client_id` (`client_id`),
  KEY `ci_id` (`ci_id`),
  KEY `computed_by` (`computed_by`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `credit_scores_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `credit_scores_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `credit_scores_ibfk_3` FOREIGN KEY (`ci_id`) REFERENCES `credit_investigations` (`ci_id`) ON DELETE SET NULL,
  CONSTRAINT `credit_scores_ibfk_4` FOREIGN KEY (`computed_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
  `document_type_id` int NOT NULL AUTO_INCREMENT,
  `document_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_required` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `loan_purpose` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_type_id`),
  UNIQUE KEY `document_name` (`document_name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_types` (`document_type_id`, `document_name`, `description`, `is_required`, `is_active`, `loan_purpose`, `created_at`) VALUES
('1', 'Valid ID Front', 'Front side of government-issued ID', '1', '1', NULL, '2026-04-08 03:45:36'),
('2', 'Valid ID Back', 'Back side of government-issued ID', '1', '1', NULL, '2026-04-08 03:45:36'),
('3', 'Proof of Income', 'Latest payslip, ITR, or bank statement', '1', '1', NULL, '2026-04-08 03:45:36'),
('4', 'Proof of Billing', 'Upload proof of address (utility bill, barangay certificate, etc.)', '1', '1', NULL, '2026-04-08 03:45:36'),
('5', 'Proof of Legitimacy Document', 'Any valid proof of legitimacy such as business permit, DTI, or SEC registration', '1', '1', 'Business', '2026-04-08 03:45:36'),
('6', 'Business Financial Statements', 'Latest financial statements or income records', '1', '1', 'Business', '2026-04-08 03:45:36'),
('7', 'Business Plan', 'Business plan or proposal (for new businesses)', '0', '1', 'Business', '2026-04-08 03:45:36'),
('8', 'School Enrollment Certificate', 'Certificate of enrollment or admission letter', '1', '1', 'Education', '2026-04-08 03:45:36'),
('9', 'School ID', 'Valid school ID', '1', '1', 'Education', '2026-04-08 03:45:36'),
('10', 'Tuition Fee Assessment', 'Official assessment of tuition and fees', '1', '1', 'Education', '2026-04-08 03:45:36'),
('11', 'Land Title/Lease Agreement', 'Proof of land ownership or lease', '1', '1', 'Agricultural', '2026-04-08 03:45:36'),
('12', 'Farm Plan', 'Detailed farm plan or proposal', '1', '1', 'Agricultural', '2026-04-08 03:45:36'),
('13', 'Medical Certificate', 'Medical certificate or hospital bill', '1', '1', 'Medical', '2026-04-08 03:45:36'),
('14', 'Prescription/Treatment Plan', 'Doctor\'s prescription or treatment plan', '0', '1', 'Medical', '2026-04-08 03:45:36'),
('15', 'Property Documents', 'Land title, tax declaration, or contract to sell', '1', '1', 'Housing', '2026-04-08 03:45:36'),
('16', 'Construction Estimate', 'Detailed construction estimate or quotation', '1', '1', 'Housing', '2026-04-08 03:45:36'),
('17', 'DTI/SEC Registration', 'Business registration documents', '0', '1', 'Business', '2026-04-08 03:45:36'),
('18', 'Barangay Clearance', 'Clearance from local barangay', '0', '1', NULL, '2026-04-08 03:45:36'),
('19', 'Marriage Certificate', 'For married applicants', '0', '1', NULL, '2026-04-08 03:45:36'),
('20', 'Birth Certificate', 'Birth certificate copy', '0', '1', NULL, '2026-04-08 03:45:36'),
('21', 'National ID (PhilID/ePhilID)', 'Philippine Identification System ID including PhilID or ePhilID', '0', '1', NULL, '2026-04-18 03:45:36'),
('22', 'Passport', 'Philippine or foreign passport accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('23', 'Driver''s License', 'Driver''s license or electronic driver''s license accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('24', 'UMID', 'Unified Multi-Purpose ID accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('25', 'SSS ID', 'Social Security System ID or digitized SSS ID accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('26', 'GSIS e-Card', 'Government Service Insurance System e-Card accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('27', 'PRC ID', 'Professional Regulation Commission ID accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('28', 'Postal ID', 'Postal ID accepted as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('29', 'Seaman''s Book / SIRB', 'Seaman''s Book or Seafarer''s Identification and Record Book', '0', '1', NULL, '2026-04-18 03:45:36'),
('30', 'Senior Citizen ID', 'Senior Citizen ID accepted by some institutions as a valid identity document', '0', '1', NULL, '2026-04-18 03:45:36'),
('31', 'PWD ID', 'Person with Disability ID or NCDA-issued ID accepted by some institutions', '0', '1', NULL, '2026-04-18 03:45:36'),
('32', 'Voter''s ID', 'Voter''s ID or similar voter registration ID accepted by some institutions', '0', '1', NULL, '2026-04-18 03:45:36'),
('33', 'NBI Clearance', 'National Bureau of Investigation clearance accepted by some institutions as identity proof', '0', '1', NULL, '2026-04-18 03:45:36'),
('34', 'Police Clearance', 'Police clearance with photo and signature accepted by some institutions', '0', '1', NULL, '2026-04-18 03:45:36'),
('35', 'TIN ID', 'Tax Identification Number ID accepted by some institutions', '0', '1', NULL, '2026-04-18 03:45:36'),
('36', 'Company ID', 'Company ID accepted by some institutions but may not be valid for all loan or housing transactions', '0', '1', NULL, '2026-04-18 03:45:36'),
('37', 'Barangay ID', 'Barangay ID accepted by some institutions but may not be valid for all loan or housing transactions', '0', '1', NULL, '2026-04-18 03:45:36'),
('38', 'OFW ID', 'Overseas Filipino Worker ID accepted in some contexts', '0', '1', NULL, '2026-04-18 03:45:36'),
('39', 'OWWA ID', 'Overseas Workers Welfare Administration ID accepted in some contexts', '0', '1', NULL, '2026-04-18 03:45:36'),
('40', 'IBP ID', 'Integrated Bar of the Philippines ID accepted by some institutions', '0', '1', NULL, '2026-04-18 03:45:36'),
('41', 'Government Office / GOCC ID', 'Government office or GOCC-issued ID such as AFP or similar government entity ID', '0', '1', NULL, '2026-04-18 03:45:36');

DROP TABLE IF EXISTS `email_delivery_logs`;
CREATE TABLE `email_delivery_logs` (
  `email_log_id` bigint NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `email_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brevo',
  `provider_message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`email_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `employee_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` enum('Admin','Sales and Marketing','Collections','Loan Processing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternate_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date NOT NULL,
  `employment_status` enum('Active','On Leave','Resigned','Terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `uq_tenant_empcode` (`tenant_id`,`employee_code`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loan_applications`;
CREATE TABLE `loan_applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `application_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `loan_term_months` int NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `purpose_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `loan_purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `application_data` json DEFAULT NULL,
  `application_status` enum('Draft','Submitted','Pending Review','Under Review','Document Verification','Credit Investigation','For Approval','Approved','Rejected','Cancelled','Withdrawn') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `submitted_date` datetime DEFAULT NULL,
  `reviewed_by` int DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `review_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` int DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejected_by` int DEFAULT NULL,
  `rejection_date` datetime DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `has_comaker` tinyint(1) DEFAULT '0',
  `comaker_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comaker_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comaker_income` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `client_id` (`client_id`),
  KEY `product_id` (`product_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `approved_by` (`approved_by`),
  KEY `rejected_by` (`rejected_by`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`application_status`),
  CONSTRAINT `loan_applications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `loan_applications_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `loan_applications_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `loan_products` (`product_id`),
  CONSTRAINT `loan_applications_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `loan_applications_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `loan_applications_ibfk_6` FOREIGN KEY (`rejected_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loan_products`;
CREATE TABLE `loan_products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `min_amount` decimal(12,2) NOT NULL,
  `max_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `interest_type` enum('Declining Balance','Flat') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Declining Balance',
  `min_term_months` int NOT NULL,
  `max_term_months` int NOT NULL,
  `billing_cycle` ENUM('Monthly', 'Quarterly', 'Semi-Annually', 'Yearly') DEFAULT 'Monthly',
  `processing_fee_percentage` decimal(5,2) DEFAULT '0.00',
  `service_charge` decimal(10,2) DEFAULT '0.00',
  `documentary_stamp` decimal(10,2) DEFAULT '0.00',
  `insurance_fee_percentage` decimal(5,2) DEFAULT '0.00',
  `early_settlement_fee_type` enum('Percentage','Fixed') DEFAULT 'Percentage',
  `early_settlement_fee_value` decimal(10,2) DEFAULT '0.00',
  `grace_period_days` int DEFAULT '0',
  `minimum_credit_score` int DEFAULT '500',
  `required_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `loan_products_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loans`;
CREATE TABLE `loans` (
  `loan_id` int NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `application_id` int NOT NULL,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_amount` decimal(12,2) NOT NULL,
  `total_loan_amount` decimal(12,2) NOT NULL,
  `processing_fee` decimal(10,2) DEFAULT '0.00',
  `service_charge` decimal(10,2) DEFAULT '0.00',
  `documentary_stamp` decimal(10,2) DEFAULT '0.00',
  `insurance_fee` decimal(10,2) DEFAULT '0.00',
  `other_charges` decimal(10,2) DEFAULT '0.00',
  `total_deductions` decimal(12,2) DEFAULT '0.00',
  `net_proceeds` decimal(12,2) DEFAULT '0.00',
  `interest_rate` decimal(5,2) NOT NULL,
  `loan_term_months` int NOT NULL,
  `monthly_amortization` decimal(12,2) NOT NULL,
  `payment_frequency` enum('Daily','Weekly','Bi-Weekly','Monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Monthly',
  `number_of_payments` int NOT NULL,
  `release_date` date NOT NULL,
  `first_payment_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `loan_status` enum('Active','Fully Paid','Overdue','Restructured','Written Off','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `released_by` int NOT NULL,
  `disbursement_method` enum('Cash','Check','Bank Transfer','GCash') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `disbursement_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_paid` decimal(12,2) DEFAULT '0.00',
  `principal_paid` decimal(12,2) DEFAULT '0.00',
  `interest_paid` decimal(12,2) DEFAULT '0.00',
  `penalty_paid` decimal(12,2) DEFAULT '0.00',
  `remaining_balance` decimal(12,2) DEFAULT NULL,
  `outstanding_principal` decimal(12,2) DEFAULT NULL,
  `outstanding_interest` decimal(12,2) DEFAULT NULL,
  `outstanding_penalty` decimal(12,2) DEFAULT '0.00',
  `last_payment_date` date DEFAULT NULL,
  `next_payment_due` date DEFAULT NULL,
  `days_overdue` int DEFAULT '0',
  `loan_agreement_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promissory_note_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_id`),
  UNIQUE KEY `loan_number` (`loan_number`),
  KEY `application_id` (`application_id`),
  KEY `client_id` (`client_id`),
  KEY `product_id` (`product_id`),
  KEY `released_by` (`released_by`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_tenant_client` (`tenant_id`,`client_id`),
  KEY `idx_loan_status` (`loan_status`),
  KEY `idx_next_payment` (`next_payment_due`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `loan_applications` (`application_id`),
  CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `loans_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `loans_ibfk_4` FOREIGN KEY (`product_id`) REFERENCES `loan_products` (`product_id`),
  CONSTRAINT `loans_ibfk_5` FOREIGN KEY (`released_by`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mobile_install_attributions`;
CREATE TABLE `mobile_install_attributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tracking_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `platform_hint` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `referer_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `claimed_ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_platform_hint` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_token` (`tracking_token`),
  KEY `idx_mobile_install_lookup` (`ip_address`,`platform_hint`,`claimed_at`,`expires_at`,`created_at`),
  KEY `idx_mobile_install_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notification_type` enum('Payment Due','Payment Received','Loan Approved','Loan Rejected','Document Required','Appointment Reminder','Message Received','System Alert','General') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `priority` enum('Low','Medium','High') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Medium',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE `otp_verifications` (
  `otp_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Verified','Expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`otp_id`),
  KEY `idx_email_status` (`email`,`status`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `transaction_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `loan_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Payment gateway source ID',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` enum('regular','early_settlement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
  `status` enum('pending','completed','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `transaction_ref` (`transaction_ref`),
  KEY `idx_transaction_ref` (`transaction_ref`),
  KEY `idx_client_loan` (`client_id`,`loan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `payment_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `loan_id` int NOT NULL,
  `client_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `principal_paid` decimal(12,2) NOT NULL,
  `interest_paid` decimal(12,2) NOT NULL,
  `penalty_paid` decimal(12,2) DEFAULT '0.00',
  `advance_payment` decimal(12,2) DEFAULT '0.00',
  `payment_method` enum('Cash','Check','Bank Transfer','GCash','Online Payment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `official_receipt_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `posted_by` int DEFAULT NULL,
  `posted_date` datetime DEFAULT NULL,
  `payment_status` enum('Pending','Posted','Verified','Cancelled','Bounced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `payment_reference` (`payment_reference`),
  KEY `loan_id` (`loan_id`),
  KEY `client_id` (`client_id`),
  KEY `received_by` (`received_by`),
  KEY `posted_by` (`posted_by`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_tenant_client` (`tenant_id`,`client_id`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `payments_ibfk_5` FOREIGN KEY (`posted_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permission_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. CREATE_ACCOUNT, APPROVE_LOAN',
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `permission_code` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`permission_id`, `permission_code`, `module`, `description`, `created_at`) VALUES
('1', 'VIEW_USERS', 'Users', 'Can view the list of users and employees', '2026-04-08 03:45:31'),
('2', 'CREATE_USERS', 'Users', 'Can create new users and invite employees', '2026-04-08 03:45:31'),
('3', 'MANAGE_ROLES', 'Roles', 'Can create custom roles and assign permissions', '2026-04-08 03:45:31'),
('4', 'VIEW_CLIENTS', 'Clients', 'Can view client profiles and history', '2026-04-08 03:45:31'),
('5', 'CREATE_CLIENTS', 'Clients', 'Can register new clients', '2026-04-08 03:45:31'),
('6', 'VIEW_LOANS', 'Loans', 'Can view loan applications and active loans', '2026-04-08 03:45:31'),
('7', 'CREATE_LOANS', 'Loans', 'Can draft new loan applications', '2026-04-08 03:45:31'),
('8', 'APPROVE_LOANS', 'Loans', 'Can approve or reject Pending loans', '2026-04-08 03:45:31'),
('9', 'PROCESS_PAYMENTS', 'Payments', 'Can receive and post loan payments', '2026-04-08 03:45:31'),
('10', 'VIEW_REPORTS', 'Reports', 'Can generate and view financial reports', '2026-04-08 03:45:31'),
('11', 'VIEW_APPLICATIONS', 'Applications', 'Can view Pending loan applications', '2026-04-08 03:45:31'),
('12', 'MANAGE_APPLICATIONS', 'Applications', 'Can approve, reject, or process Pending loan applications', '2026-04-08 03:45:31'),
('13', 'VIEW_CREDIT_ACCOUNTS', 'Credit Accounts', 'Can view borrower credit accounts, limit recommendations, and upgrade eligibility', '2026-04-08 03:45:31'),
('14', 'EDIT_BILLING', 'System', 'Can edit billing and subscription settings', '2026-04-08 03:50:38');

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `data_type` enum('String','Number','Boolean','JSON') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'String',
  `is_editable` tinyint(1) DEFAULT '1',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uq_tenant_key` (`tenant_id`,`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `system_settings_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenant_billing_invoices`;
CREATE TABLE `tenant_billing_invoices` (
  `invoice_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `billing_period_start` date NOT NULL,
  `billing_period_end` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Draft','Open','Paid','Void','Uncollectible') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Open',
  `stripe_invoice_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_tenant_status_due` (`tenant_id`,`status`,`due_date`),
  CONSTRAINT `tenant_billing_invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_billing_payment_methods`;
CREATE TABLE `tenant_billing_payment_methods` (
  `method_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_four_digits` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_brand` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cardholder_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exp_month` int NOT NULL,
  `exp_year` int NOT NULL,
  `card_number_encrypted` varbinary(255) NOT NULL COMMENT 'AES-256 encrypted full card number',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`method_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `tenant_billing_payment_methods_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_branding`;
CREATE TABLE `tenant_branding` (
  `branding_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `font_family` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Inter',
  `theme_primary_color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#dc2626',
  `theme_secondary_color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#991b1b',
  `theme_text_main` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#0f172a',
  `theme_text_muted` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#64748b',
  `theme_bg_body` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#f8fafc',
  `theme_bg_card` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#ffffff',
  `theme_border_color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#e2e8f0',
  `card_border_width` tinyint DEFAULT '1',
  `card_shadow` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sm',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`branding_id`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `tenant_branding_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenant_feature_toggles`;
CREATE TABLE `tenant_feature_toggles` (
  `toggle_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `toggle_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`toggle_id`),
  UNIQUE KEY `uq_tenant_toggle` (`tenant_id`,`toggle_key`),
  CONSTRAINT `tenant_feature_toggles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenant_legitimacy_documents`;
CREATE TABLE `tenant_legitimacy_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `tenant_legitimacy_documents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenant_website_content`;
CREATE TABLE `tenant_website_content` (
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `layout_template` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'template1.php',
  `website_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `fk_tenant_website_content_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'System-generated immutable tenant identifier',
  `tenant_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL-safe identifier',
  `estimated_loans` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_deadline` datetime DEFAULT NULL,
  `company_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plan_tier` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Starter',
  `scheduled_plan_tier` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_plan_effective_date` date DEFAULT NULL,
  `max_clients` int DEFAULT '1000',
  `max_users` int DEFAULT '250',
  `storage_limit_gb` decimal(5,2) DEFAULT '5.00',
  `modules_enabled` json DEFAULT NULL,
  `mrr` decimal(10,2) DEFAULT '0.00',
  `billing_cycle` enum('Monthly','Quarterly','Yearly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Monthly',
  `next_billing_date` date DEFAULT NULL,
  `setup_completed` tinyint(1) DEFAULT '0',
  `setup_current_step` int DEFAULT '0',
  `status` enum('Pending','Active','Suspended','Rejected','New','In Contact','Closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `request_type` enum('tenant_application','talk_to_expert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tenant_application',
  `concern_category` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_expert_user_id` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `deletion_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `tenant_slug` (`tenant_slug`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_request_type_status` (`request_type`,`status`),
  KEY `idx_request_type_created_at` (`request_type`,`created_at`),
  KEY `idx_assigned_expert_user` (`assigned_expert_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL for platform-level roles like super_admin',
  `role_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_system_role` tinyint(1) DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_tenant_role` (`tenant_id`,`role_name`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_roles` (`role_id`, `tenant_id`, `role_name`, `role_description`, `is_system_role`, `deleted_at`, `deleted_by`, `created_at`, `updated_at`) VALUES
('1', NULL, 'Super Admin', 'Master Platform Administrator', '1', NULL, NULL, '2026-04-08 03:45:21', '2026-04-08 03:45:21')

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `session_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_sessions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL only for Super Admins',
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `force_password_change` tinyint(1) DEFAULT '0',
  `role_id` int NOT NULL,
  `user_type` enum('Employee','Client','Admin','applicant','inquirer','Super Admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive','Suspended','Locked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `can_manage_billing` tinyint(1) NOT NULL DEFAULT '0',
  `verification_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_attempts` int DEFAULT '0',
  `two_fa_enabled` tinyint(1) DEFAULT '0',
  `two_fa_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_fa_recovery_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ui_theme` enum('light','dark') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uq_tenant_email` (`tenant_id`,`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_reset_token` (`reset_token`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`user_id`, `tenant_id`, `username`, `email`, `phone_number`, `password_hash`, `email_verified`, `first_name`, `last_name`, `middle_name`, `suffix`, `date_of_birth`, `force_password_change`, `role_id`, `user_type`, `status`, `can_manage_billing`, `verification_token`, `reset_token`, `reset_token_expiry`, `last_login`, `failed_login_attempts`, `two_fa_enabled`, `two_fa_secret`, `two_fa_recovery_codes`, `ui_theme`, `deleted_at`, `created_at`, `updated_at`) VALUES
('1', NULL, 'rhob@microfin.com', 'gleomir28@gmail.com', NULL, '$2y$10$WAX5NHe0AGODm6k2skpJT.DrLQHrvwsmEofIb8LBN93x/gtuVYv5a', '0', NULL, NULL, NULL, NULL, NULL, '0', '1', 'Super Admin', 'Active', '0', NULL, NULL, NULL, '2026-04-08 06:08:34', '0', '0', NULL, NULL, 'light', NULL, '2026-04-08 03:45:26', '2026-04-08 06:08:42')

SET FOREIGN_KEY_CHECKS = 1;

