-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: maglev.proxy.rlwy.net    Database: railway
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

CREATE DATABASE IF NOT EXISTS `microfin_db`;
USE `microfin_db`;

--
-- Table structure for table `amortization_schedule`
--

DROP TABLE IF EXISTS `amortization_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `amortization_schedule`
--

LOCK TABLES `amortization_schedule` WRITE;
/*!40000 ALTER TABLE `amortization_schedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `amortization_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_documents`
--

DROP TABLE IF EXISTS `application_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_documents`
--

LOCK TABLES `application_documents` WRITE;
/*!40000 ALTER TABLE `application_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,NULL,'SUPER_ADMIN_CREATED','user',NULL,'Created new super admin account: gleo. Profile will be completed during onboarding.',NULL,'2026-04-08 03:46:36'),(2,2,NULL,'PASSWORD_CHANGED','user',NULL,'Super admin completed forced password reset',NULL,'2026-04-08 03:47:56'),(3,2,NULL,'SUPER_ADMIN_ONBOARDING_COMPLETED','user',NULL,'Super admin completed initial profile onboarding',NULL,'2026-04-08 03:48:09'),(4,1,'YRL5G6VV30','TENANT_PROVISIONED','tenant',NULL,'rhob@microfin.com had provisioned Fundline (ID: YRL5G6VV30, Slug: fundline, Plan: Unlimited)',NULL,'2026-04-08 03:49:28'),(5,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-08 03:49:52'),(6,NULL,'YRL5G6VV30','PASSWORD_CHANGED','user',NULL,'User completed forced password reset and profile setup',NULL,'2026-04-08 03:50:17'),(7,3,'YRL5G6VV30','BILLING_SETUP','tenant',NULL,'Payment method added during onboarding',NULL,'2026-04-08 03:50:37'),(8,3,'YRL5G6VV30','BILLING_ACTIVATION','invoice',NULL,'Generated initial activation billing records INV-20260408-7AF3E1 / SUB-7AF3E19124. Amount: 29999. Next billing date: 2026-05-08.',NULL,'2026-04-08 03:50:37'),(9,3,'YRL5G6VV30','STAFF_ADDED','user',NULL,'New staff account created for  ',NULL,'2026-04-08 03:51:05'),(10,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-08 03:52:11'),(11,3,'YRL5G6VV30','STAFF_ADDED','user',NULL,'New staff account created for  ',NULL,'2026-04-08 03:52:36'),(12,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-08 10:24:07'),(13,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-08 11:16:13'),(14,1,'JRE0JW6ZIX','TENANT_PROVISIONED','tenant',NULL,'rhob@microfin.com had provisioned ChinaBank (ID: JRE0JW6ZIX, Slug: chinabank, Plan: Unlimited)',NULL,'2026-04-09 09:09:53'),(15,6,'JRE0JW6ZIX','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-09 09:10:26'),(16,NULL,'JRE0JW6ZIX','PASSWORD_CHANGED','user',NULL,'User completed forced password reset and profile setup',NULL,'2026-04-09 09:10:45'),(17,6,'JRE0JW6ZIX','BILLING_SETUP','tenant',NULL,'Payment method added during onboarding',NULL,'2026-04-09 09:11:08'),(18,6,'JRE0JW6ZIX','BILLING_ACTIVATION','invoice',NULL,'Generated initial activation billing records INV-20260409-702297 / SUB-7022970C5F. Amount: 29999. Next billing date: 2026-05-09.',NULL,'2026-04-09 09:11:08'),(19,6,'JRE0JW6ZIX','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-09 09:27:11'),(20,6,'JRE0JW6ZIX','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 00:22:41'),(21,6,'JRE0JW6ZIX','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-14 00:23:59'),(22,6,'JRE0JW6ZIX','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 00:24:05'),(23,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 00:25:26'),(24,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 01:53:44'),(25,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 11:18:29'),(26,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-14 11:21:05'),(27,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-14 11:22:39'),(28,1,NULL,'SUPER_ADMIN_PROFILE_UPDATED','user',NULL,'Updated personal profile details from the Super Admin settings page',NULL,'2026-04-14 11:29:04'),(29,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-16 11:15:40'),(30,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-17 01:12:49'),(31,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-17 20:07:22'),(32,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-17 23:06:41'),(33,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 00:56:58'),(34,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 01:36:32'),(35,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 01:42:46'),(36,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 01:45:24'),(37,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 01:48:12'),(38,1,'UHKYHQD9YW','TENANT_PROVISIONED','tenant',NULL,'rhob had provisioned Default System MicroFin (ID: UHKYHQD9YW, Slug: defaultsystemmicrofin, Plan: Enterprise)',NULL,'2026-04-18 02:04:41'),(39,9,'UHKYHQD9YW','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 02:05:13'),(40,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-18 02:07:20'),(41,9,'UHKYHQD9YW','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 02:07:42'),(42,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 02:10:29'),(43,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 02:52:59'),(44,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-18 02:56:44'),(45,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 03:01:30'),(46,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-18 04:00:27'),(47,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 04:00:38'),(48,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 05:35:27'),(49,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated',NULL,'2026-04-18 07:21:37'),(50,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated',NULL,'2026-04-18 07:26:02'),(51,3,'YRL5G6VV30','POLICY_CONSOLE_DECISION_RULES_UPDATED','system_settings',NULL,'Policy Console Decision Rules updated',NULL,'2026-04-18 07:26:19'),(52,3,'YRL5G6VV30','POLICY_CONSOLE_COMPLIANCE_UPDATED','system_settings',NULL,'Policy Console Compliance & Documents updated',NULL,'2026-04-18 07:27:50'),(53,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 11:25:57'),(54,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-18 14:29:26'),(55,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 14:29:51'),(56,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 14:30:45'),(57,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 15:51:21'),(58,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-18 15:51:44'),(59,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-18 22:46:24'),(60,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated: limit_assignment.apply_score_changes_immediately changed from \'true\' to \'false\'',NULL,'2026-04-18 23:17:30'),(61,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated: limit_assignment.apply_score_changes_immediately changed from \'true\' to \'false\'',NULL,'2026-04-18 23:19:06'),(62,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated: scoring_setup.detailed_rules.downgrade.late_payments_review.score_points changed from \'12\' to \'10\'',NULL,'2026-04-18 23:54:26'),(63,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 00:31:16'),(64,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated',NULL,'2026-04-19 00:39:30'),(65,3,'YRL5G6VV30','POLICY_CONSOLE_DECISION_RULES_UPDATED','system_settings',NULL,'Policy Console Rules & Requirements updated',NULL,'2026-04-19 00:39:40'),(66,3,'YRL5G6VV30','POLICY_CONSOLE_COMPLIANCE_UPDATED','system_settings',NULL,'Policy Console Required Documents updated',NULL,'2026-04-19 00:39:52'),(67,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 01:15:47'),(68,3,'YRL5G6VV30','POLICY_CONSOLE_DECISION_RULES_UPDATED','system_settings',NULL,'Policy Console Rules & Requirements updated: decision_rules.guardrails.cooling_period_enabled changed from \'false\' to \'true\'',NULL,'2026-04-19 01:22:30'),(69,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated',NULL,'2026-04-19 01:23:43'),(70,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 04:39:15'),(71,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 04:47:41'),(72,3,'YRL5G6VV30','POLICY_CONSOLE_COMPLIANCE_UPDATED','system_settings',NULL,'Policy Console Required Documents updated: document_requirements.0.accepted_document_names.11 changed from \'Senior Citizen ID\' to \'PWD ID\'; document_requirements.0.accepted_document_names.12 changed from \'PWD ID\' to \'Voter\'s ID\'; document_requirements.0.accepted_document_names.13 changed from \'Voter\'s ID\' to \'NBI Clearance\'; document_requirements.0.accepted_document_names.14 changed from \'NBI Clearance\' to \'Police Clearance\'; document_requirements.0.accepted_document_names.15 changed from \'Police Clearance\' to \'TIN ID\'; document_requirements.0.accepted_document_names.16 changed from \'TIN ID\' to \'School ID\'; document_requirements.0.accepted_document_names.17 changed from \'School ID\' to \'Company ID\'; document_requirements.0.accepted_document_names.18 changed from \'Company ID\' to \'Barangay ID\'; document_requirements.0.accepted_document_names.19 changed from \'Barangay ID\' to \'OFW ID\'; document_requirements.0.accepted_document_names.20 changed from \'OFW ID\' to \'OWWA ID\'; document_requirements.0.accepted_document_names.21 changed from \'OWWA ID\' to \'IBP ID\'; document_requirements.0.accepted_document_names.22 changed from \'IBP ID\' to \'Government Office / GOCC ID\'; document_requirements.0.accepted_document_names.23 removed (was \'Government Office / GOCC ID\'); document_requirements.0.document_options.11.is_accepted changed from \'true\' to \'false\'',NULL,'2026-04-19 06:06:08'),(73,3,'YRL5G6VV30','POLICY_CONSOLE_DECISION_RULES_UPDATED','system_settings',NULL,'Policy Console Rules & Requirements updated: decision_rules.exposure.multiple_active_loans_enabled changed from \'true\' to \'false\'; decision_rules.borrowing_access_rules.allow_multiple_active_loans_within_remaining_limit changed from \'true\' to \'false\'',NULL,'2026-04-19 06:15:55'),(74,3,'YRL5G6VV30','POLICY_CONSOLE_CREDIT_LIMITS_UPDATED','system_settings',NULL,'Policy Console Credit & Limits updated',NULL,'2026-04-19 06:33:04'),(75,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 06:44:22'),(76,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-19 06:44:40'),(77,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 06:44:50'),(78,4,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-19 06:45:16'),(79,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 06:45:24'),(80,3,'YRL5G6VV30','POLICY_CONSOLE_COMPLIANCE_UPDATED','system_settings',NULL,'Policy Console Required Documents updated: document_requirements.0.accepted_document_names.11 changed from \'PWD ID\' to \'Senior Citizen ID\'; document_requirements.0.accepted_document_names.12 changed from \'Voter\'s ID\' to \'PWD ID\'; document_requirements.0.accepted_document_names.13 changed from \'NBI Clearance\' to \'Voter\'s ID\'; document_requirements.0.accepted_document_names.14 changed from \'Police Clearance\' to \'NBI Clearance\'; document_requirements.0.accepted_document_names.15 changed from \'TIN ID\' to \'Police Clearance\'; document_requirements.0.accepted_document_names.16 changed from \'School ID\' to \'TIN ID\'; document_requirements.0.accepted_document_names.17 changed from \'Company ID\' to \'School ID\'; document_requirements.0.accepted_document_names.18 changed from \'Barangay ID\' to \'Company ID\'; document_requirements.0.accepted_document_names.19 changed from \'OFW ID\' to \'Barangay ID\'; document_requirements.0.accepted_document_names.20 changed from \'OWWA ID\' to \'OFW ID\'; document_requirements.0.accepted_document_names.21 changed from \'IBP ID\' to \'OWWA ID\'; document_requirements.0.accepted_document_names.22 changed from \'Government Office / GOCC ID\' to \'IBP ID\'; document_requirements.0.accepted_document_names.23 changed from \'\' to \'Government Office / GOCC ID\'; document_requirements.0.document_options.11.is_accepted changed from \'false\' to \'true\'',NULL,'2026-04-19 07:05:48'),(81,3,'YRL5G6VV30','LOAN_PRODUCT_UPDATED','loan_product',NULL,'Loan product settings updated',NULL,'2026-04-19 07:23:01'),(82,6,'JRE0JW6ZIX','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 07:35:56'),(83,6,'JRE0JW6ZIX','STAFF_ADDED','user',NULL,'New staff account created for  ',NULL,'2026-04-19 07:36:42'),(84,6,'JRE0JW6ZIX','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-19 07:36:53'),(85,3,'YRL5G6VV30','LOAN_PRODUCT_UPDATED','loan_product',NULL,'Loan product settings updated',NULL,'2026-04-19 07:53:55'),(86,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 08:57:25'),(87,3,'YRL5G6VV30','POLICY_CONSOLE_COMPLIANCE_UPDATED','system_settings',NULL,'Policy Console Required Documents updated: document_requirements.0.accepted_document_names.11 changed from \'Voter\'s ID\' to \'NBI Clearance\'; document_requirements.0.accepted_document_names.12 changed from \'NBI Clearance\' to \'Police Clearance\'; document_requirements.0.accepted_document_names.13 changed from \'Police Clearance\' to \'TIN ID\'; document_requirements.0.accepted_document_names.14 changed from \'TIN ID\' to \'School ID\'; document_requirements.0.accepted_document_names.15 changed from \'School ID\' to \'Company ID\'; document_requirements.0.accepted_document_names.16 changed from \'Company ID\' to \'Barangay ID\'; document_requirements.0.accepted_document_names.17 changed from \'Barangay ID\' to \'OFW ID\'; document_requirements.0.accepted_document_names.18 changed from \'OFW ID\' to \'OWWA ID\'; document_requirements.0.accepted_document_names.19 changed from \'OWWA ID\' to \'IBP ID\'; document_requirements.0.accepted_document_names.20 changed from \'IBP ID\' to \'Government Office / GOCC ID\'; document_requirements.0.accepted_document_names.21 removed (was \'Government Office / GOCC ID\'); document_requirements.0.document_options.11.is_accepted changed from \'true\' to \'false\'',NULL,'2026-04-19 08:58:05'),(88,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 10:56:52'),(89,3,'YRL5G6VV30','POLICY_CONSOLE_DECISION_RULES_UPDATED','system_settings',NULL,'Policy Console Rules & Requirements updated: decision_rules.demographics.age_enabled changed from \'true\' to \'false\'',NULL,'2026-04-19 10:57:12'),(90,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 12:46:17'),(91,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 13:49:52'),(92,3,'YRL5G6VV30','STAFF_LOGOUT','user',NULL,'Staff logged out of the system',NULL,'2026-04-19 13:50:00'),(93,4,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 13:50:24'),(94,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 17:08:00'),(95,4,'YRL5G6VV30','DOCUMENTS_VERIFIED','client',4,'Admin verified all submitted documents',NULL,'2026-04-19 17:32:07'),(96,4,'YRL5G6VV30','CLIENT_APPROVED','client',4,'Admin manually approved and activated client',NULL,'2026-04-19 17:32:16'),(97,3,'YRL5G6VV30','STAFF_LOGIN','user',NULL,'Staff logged into the system',NULL,'2026-04-19 17:52:38');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
INSERT INTO `backup_logs` VALUES (1,1,'full','microfin_full_backup_2026-04-08_080846.sql',70289,NULL,'Success',NULL,'2026-04-08 06:08:53');
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_documents`
--

DROP TABLE IF EXISTS `client_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_documents`
--

LOCK TABLES `client_documents` WRITE;
/*!40000 ALTER TABLE `client_documents` DISABLE KEYS */;
INSERT INTO `client_documents` VALUES (1,2,'YRL5G6VV30',1,'Scanned_ID','uploads/client_documents/YRL5G6VV30/2026/04/doc_20260419105748_aa07b166.jpg','2026-04-19 11:02:05','0098-9899-9808-9809',40794,'image/jpeg',NULL,NULL,'Pending','Submitted from the mobile verification flow. ID type: national_id__philid_ephilid_. Document number: 0098-9899-9808-9809.',NULL,1),(2,2,'YRL5G6VV30',3,'Document_3','uploads/client_documents/YRL5G6VV30/2026/04/doc_20260419105856_15ba0b13.jpg','2026-04-19 11:02:05',NULL,40794,'image/jpeg',1,'2026-04-19 15:52:54','Verified',NULL,NULL,1),(3,4,'YRL5G6VV30',1,'Scanned_ID','uploads/client_documents/YRL5G6VV30/2026/04/doc_20260419172937_742787e4.jpg','2026-04-19 17:30:52','123123123',40794,'image/jpeg',1,'2026-04-19 17:32:05','Verified',NULL,NULL,1),(4,4,'YRL5G6VV30',4,'Document_4','uploads/client_documents/YRL5G6VV30/2026/04/doc_20260419173038_591bf697.jpg','2026-04-19 17:30:52',NULL,40794,'image/jpeg',1,'2026-04-19 17:32:05','Verified',NULL,NULL,1),(5,4,'YRL5G6VV30',3,'Document_3','uploads/client_documents/YRL5G6VV30/2026/04/doc_20260419173043_a0e4b242.jpg','2026-04-19 17:30:52',NULL,40794,'image/jpeg',1,'2026-04-19 17:32:05','Verified',NULL,NULL,1);
/*!40000 ALTER TABLE `client_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `document_verification_status` enum('Unverified','Pending','Verified','Approved','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Unverified',
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,7,'YRL5G6VV30','CLT2026-00007','Xijinn','','Pingg','','1990-01-01',NULL,NULL,'Filipino','',NULL,'gleomir28@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Active',NULL,NULL,'2026-04-09',NULL,'Unverified',NULL,0.00,0.00,0,0,NULL,NULL,'2026-04-09 11:05:31','2026-04-09 11:05:31'),(2,8,'YRL5G6VV30','CLT2026-00008','Tangina','','bruh','','1982-04-15','Male','Single','Filipino','09876452',NULL,'earlkaizer10@gmail.com','rqw','eqwe','eqwe','qwe','wq','123','rqw','eqwe','eqwe','qwe','wq','123',1,'Student','','',NULL,'',12345.00,NULL,NULL,'','','',0.00,NULL,'',NULL,NULL,NULL,NULL,'national_id__philid_ephilid_','Active',NULL,NULL,'2026-04-09',NULL,'Pending',NULL,0.00,4938.00,0,0,NULL,NULL,'2026-04-09 12:21:55','2026-04-19 15:52:35'),(4,18,'YRL5G6VV30','CLT2026-00018','pangalan','nananaamn naayos nato','e','','2006-04-25','Male','Single','Filipino','0987654321',NULL,'janaryhaileysabas048@gmail.com','123','strt','brgy','cty','pvr','30089','123','strt','brgy','cty','pvr','30089',1,'Unemployed','','',NULL,'',10023.00,NULL,NULL,'','','',0.00,NULL,'',NULL,NULL,NULL,NULL,'gsis_e_card','Active',NULL,NULL,'2026-04-19',NULL,'Approved',NULL,4009.20,0.00,0,0,NULL,NULL,'2026-04-19 17:22:00','2026-04-19 17:32:15');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credit_investigations`
--

DROP TABLE IF EXISTS `credit_investigations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credit_investigations`
--

LOCK TABLES `credit_investigations` WRITE;
/*!40000 ALTER TABLE `credit_investigations` DISABLE KEYS */;
/*!40000 ALTER TABLE `credit_investigations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credit_scores`
--

DROP TABLE IF EXISTS `credit_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credit_scores`
--

LOCK TABLES `credit_scores` WRITE;
/*!40000 ALTER TABLE `credit_scores` DISABLE KEYS */;
INSERT INTO `credit_scores` VALUES (1,2,'YRL5G6VV30',NULL,500,'Fair',0.00,NULL,NULL,'2026-04-19 15:52:34','Tenant credit policy score sync. Base 500. Total 500.','{\"basis\": \"Initial Assessment\", \"timestamp\": \"2026-04-19 11:02:05\", \"applied_cap\": null, \"config_percent\": 40, \"income_at_submission\": 12345}'),(2,4,'YRL5G6VV30',NULL,340,'Poor',4009.20,NULL,NULL,'2026-04-19 17:32:07','Tenant credit policy score sync. Base 320. +20 verified docs. Total 340.','{\"basis\": \"Initial Assessment\", \"timestamp\": \"2026-04-19 17:30:52\", \"applied_cap\": null, \"config_percent\": 40, \"income_at_submission\": 10023}');
/*!40000 ALTER TABLE `credit_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
INSERT INTO `document_types` VALUES (1,'Valid ID Front','Front side of government-issued ID',1,1,NULL,'2026-04-08 03:45:36'),(2,'Valid ID Back','Back side of government-issued ID',1,1,NULL,'2026-04-08 03:45:36'),(3,'Proof of Income','Latest payslip, ITR, or bank statement',1,1,NULL,'2026-04-08 03:45:36'),(4,'Proof of Billing','Upload proof of address (utility bill, barangay certificate, etc.)',1,1,NULL,'2026-04-08 03:45:36'),(5,'Proof of Legitimacy Document','Any valid proof of legitimacy such as business permit, DTI, or SEC registration',1,1,'Business','2026-04-08 03:45:36'),(6,'Business Financial Statements','Latest financial statements or income records',1,1,'Business','2026-04-08 03:45:36'),(7,'Business Plan','Business plan or proposal (for new businesses)',0,1,'Business','2026-04-08 03:45:36'),(8,'School Enrollment Certificate','Certificate of enrollment or admission letter',1,1,'Education','2026-04-08 03:45:36'),(9,'School ID','Valid school ID',1,1,'Education','2026-04-08 03:45:36'),(10,'Tuition Fee Assessment','Official assessment of tuition and fees',1,1,'Education','2026-04-08 03:45:36'),(11,'Land Title/Lease Agreement','Proof of land ownership or lease',1,1,'Agricultural','2026-04-08 03:45:36'),(12,'Farm Plan','Detailed farm plan or proposal',1,1,'Agricultural','2026-04-08 03:45:36'),(13,'Medical Certificate','Medical certificate or hospital bill',1,1,'Medical','2026-04-08 03:45:36'),(14,'Prescription/Treatment Plan','Doctor\'s prescription or treatment plan',0,1,'Medical','2026-04-08 03:45:36'),(15,'Property Documents','Land title, tax declaration, or contract to sell',1,1,'Housing','2026-04-08 03:45:36'),(16,'Construction Estimate','Detailed construction estimate or quotation',1,1,'Housing','2026-04-08 03:45:36'),(17,'DTI/SEC Registration','Business registration documents',0,1,'Business','2026-04-08 03:45:36'),(18,'Barangay Clearance','Clearance from local barangay',0,1,NULL,'2026-04-08 03:45:36'),(19,'Marriage Certificate','For married applicants',0,1,NULL,'2026-04-08 03:45:36'),(20,'Birth Certificate','Birth certificate copy',0,1,NULL,'2026-04-08 03:45:36'),(21,'National ID (PhilID/ePhilID)','Philippine Identification System ID including PhilID or ePhilID',0,1,NULL,'2026-04-18 03:45:36'),(22,'Passport','Philippine or foreign passport accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(23,'Driver\'s License','Driver\'s license or electronic driver\'s license accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(24,'UMID','Unified Multi-Purpose ID accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(25,'SSS ID','Social Security System ID or digitized SSS ID accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(26,'GSIS e-Card','Government Service Insurance System e-Card accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(27,'PRC ID','Professional Regulation Commission ID accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(28,'Postal ID','Postal ID accepted as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(29,'Seaman\'s Book / SIRB','Seaman\'s Book or Seafarer\'s Identification and Record Book',0,1,NULL,'2026-04-18 03:45:36'),(30,'Senior Citizen ID','Senior Citizen ID accepted by some institutions as a valid identity document',0,1,NULL,'2026-04-18 03:45:36'),(31,'PWD ID','Person with Disability ID or NCDA-issued ID accepted by some institutions',0,1,NULL,'2026-04-18 03:45:36'),(32,'Voter\'s ID','Voter\'s ID or similar voter registration ID accepted by some institutions',0,1,NULL,'2026-04-18 03:45:36'),(33,'NBI Clearance','National Bureau of Investigation clearance accepted by some institutions as identity proof',0,1,NULL,'2026-04-18 03:45:36'),(34,'Police Clearance','Police clearance with photo and signature accepted by some institutions',0,1,NULL,'2026-04-18 03:45:36'),(35,'TIN ID','Tax Identification Number ID accepted by some institutions',0,1,NULL,'2026-04-18 03:45:36'),(36,'Company ID','Company ID accepted by some institutions but may not be valid for all loan or housing transactions',0,1,NULL,'2026-04-18 03:45:36'),(37,'Barangay ID','Barangay ID accepted by some institutions but may not be valid for all loan or housing transactions',0,1,NULL,'2026-04-18 03:45:36'),(38,'OFW ID','Overseas Filipino Worker ID accepted in some contexts',0,1,NULL,'2026-04-18 03:45:36'),(39,'OWWA ID','Overseas Workers Welfare Administration ID accepted in some contexts',0,1,NULL,'2026-04-18 03:45:36'),(40,'IBP ID','Integrated Bar of the Philippines ID accepted by some institutions',0,1,NULL,'2026-04-18 03:45:36'),(41,'Government Office / GOCC ID','Government office or GOCC-issued ID such as AFP or similar government entity ID',0,1,NULL,'2026-04-18 03:45:36');
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_delivery_logs`
--

DROP TABLE IF EXISTS `email_delivery_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_delivery_logs`
--

LOCK TABLES `email_delivery_logs` WRITE;
/*!40000 ALTER TABLE `email_delivery_logs` DISABLE KEYS */;
INSERT INTO `email_delivery_logs` VALUES (1,'YRL5G6VV30',7,'registration_otp','gleomir28@gmail.com','Xijinn Pingg','Fundline verification code','brevo','<202604091105.71053703507@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"gleomir28@gmail.com\",\"name\":\"Xijinn Pingg\"}],\"subject\":\"Fundline verification code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Verify your email<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin registration verification code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#0F766E;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Verify your email<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello Xijinn Pingg,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">Use the verification code below to finish creating your account for <strong>Fundline<\\/strong>.<\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">118291<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request this, you can safely ignore this email.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#0F766E;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello Xijinn Pingg,\\n\\nUse this verification code to finish creating your account for Fundline: 118291\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"registration\",\"otp\"]}','{\"messageId\":\"<202604091105.71053703507@smtp-relay.mailin.fr>\"}','2026-04-09 11:05:31'),(2,NULL,NULL,'account_lookup','gleomir28@gmail.com','Xijinn Pingg','Your MicroFin login usernames','brevo','<202604091108.90308857167@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"gleomir28@gmail.com\",\"name\":\"Xijinn Pingg\"}],\"subject\":\"Your MicroFin login usernames\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Find your account<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin login usernames are ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#1D4ED8;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Find your account<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello Xijinn Pingg,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">Here are the login usernames linked to this email address in the shared MicroFin mobile app.<\\/p>\\n        <ul style=\\\"margin:0 0 20px;padding-left:20px;font-size:16px;line-height:1.7;\\\"><li style=\\\"margin:0 0 10px;\\\"><strong>flash@fundline<\\/strong><\\/li><\\/ul>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">Use the exact username above when signing in or resetting your password.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#1D4ED8;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello Xijinn Pingg,\\n\\nHere are your MicroFin login usernames:\\n- flash@fundline\\n\\nUse the exact username above when signing in or resetting your password.\",\"tags\":[\"account-lookup\",\"username-recovery\"]}','{\"messageId\":\"<202604091108.90308857167@smtp-relay.mailin.fr>\"}','2026-04-09 11:08:41'),(3,'YRL5G6VV30',8,'registration_otp','earlkaizer10@gmail.com','Tangina bruh','Fundline verification code','brevo','<202604091221.98592159908@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"earlkaizer10@gmail.com\",\"name\":\"Tangina bruh\"}],\"subject\":\"Fundline verification code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Verify your email<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin registration verification code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#0F766E;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Verify your email<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello Tangina bruh,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">Use the verification code below to finish creating your account for <strong>Fundline<\\/strong>.<\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">535021<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request this, you can safely ignore this email.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#0F766E;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello Tangina bruh,\\n\\nUse this verification code to finish creating your account for Fundline: 535021\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"registration\",\"otp\"]}','{\"messageId\":\"<202604091221.98592159908@smtp-relay.mailin.fr>\"}','2026-04-09 12:21:55'),(4,'YRL5G6VV30',7,'password_reset_otp','gleomir28@gmail.com','Xijinn Pingg','Fundline password reset code','brevo','<202604180928.11512341050@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"gleomir28@gmail.com\",\"name\":\"Xijinn Pingg\"}],\"subject\":\"Fundline password reset code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Reset your password<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin password reset code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#B45309;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Reset your password<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello Xijinn Pingg,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">We received a request to reset the password for your <strong>Fundline<\\/strong> account.<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:14px;line-height:1.7;\\\">Login username: <strong>flash@fundline<\\/strong><\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#fff7ed;border:1px solid #fdba74;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">045151<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This reset code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request a password reset, you can ignore this message.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#B45309;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello Xijinn Pingg,\\n\\nUse this password reset code for Fundline: 045151\\nLogin username: flash@fundline\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"password-reset\",\"otp\"]}','{\"messageId\":\"<202604180928.11512341050@smtp-relay.mailin.fr>\"}','2026-04-18 09:28:11'),(5,'YRL5G6VV30',8,'password_reset_otp','earlkaizer10@gmail.com','Tangina bruh','Fundline password reset code','brevo','<202604190200.15603148483@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"earlkaizer10@gmail.com\",\"name\":\"Tangina bruh\"}],\"subject\":\"Fundline password reset code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Reset your password<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin password reset code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#B45309;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Reset your password<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello Tangina bruh,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">We received a request to reset the password for your <strong>Fundline<\\/strong> account.<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:14px;line-height:1.7;\\\">Login username: <strong>ulollmooo@fundline<\\/strong><\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#fff7ed;border:1px solid #fdba74;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">789134<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This reset code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request a password reset, you can ignore this message.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#B45309;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello Tangina bruh,\\n\\nUse this password reset code for Fundline: 789134\\nLogin username: ulollmooo@fundline\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"password-reset\",\"otp\"]}','{\"messageId\":\"<202604190200.15603148483@smtp-relay.mailin.fr>\"}','2026-04-19 02:00:06'),(6,'YRL5G6VV30',17,'registration_otp','janaryhaileysabas0458@gmail.com','batmanaaa wayneee','Fundline verification code','brevo','<202604191720.95244058902@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"janaryhaileysabas0458@gmail.com\",\"name\":\"batmanaaa wayneee\"}],\"subject\":\"Fundline verification code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Verify your email<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin registration verification code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#0F766E;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Verify your email<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello batmanaaa wayneee,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">Use the verification code below to finish creating your account for <strong>Fundline<\\/strong>.<\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">740693<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request this, you can safely ignore this email.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#0F766E;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello batmanaaa wayneee,\\n\\nUse this verification code to finish creating your account for Fundline: 740693\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"registration\",\"otp\"]}','{\"messageId\":\"<202604191720.95244058902@smtp-relay.mailin.fr>\"}','2026-04-19 17:20:07'),(7,'YRL5G6VV30',18,'registration_otp','janaryhaileysabas048@gmail.com','batmaan waybnee','Fundline verification code','brevo','<202604191722.80631543251@smtp-relay.mailin.fr>','sent','Email queued successfully.','{\"sender\":{\"email\":\"microfin.statements@gmail.com\",\"name\":\"MicroFin\"},\"to\":[{\"email\":\"janaryhaileysabas048@gmail.com\",\"name\":\"batmaan waybnee\"}],\"subject\":\"Fundline verification code\",\"htmlContent\":\"<!DOCTYPE html>\\n<html lang=\\\"en\\\">\\n<head>\\n  <meta charset=\\\"UTF-8\\\">\\n  <meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\n  <title>Verify your email<\\/title>\\n<\\/head>\\n<body style=\\\"margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;\\\">\\n  <div style=\\\"display:none;max-height:0;overflow:hidden;opacity:0;\\\">Your MicroFin registration verification code is ready.<\\/div>\\n  <table role=\\\"presentation\\\" width=\\\"100%\\\" cellspacing=\\\"0\\\" cellpadding=\\\"0\\\" style=\\\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;\\\">\\n    <tr>\\n      <td style=\\\"background:#0F766E;padding:28px 32px;color:#ffffff;\\\">\\n        <div style=\\\"font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;\\\">MicroFin<\\/div>\\n        <div style=\\\"font-size:28px;font-weight:700;margin-top:8px;\\\">Verify your email<\\/div>\\n      <\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:32px;\\\">\\n        <p style=\\\"margin:0 0 16px;font-size:16px;line-height:1.7;\\\">Hello batmaan waybnee,<\\/p>\\n        <p style=\\\"margin:0 0 20px;font-size:16px;line-height:1.7;\\\">Use the verification code below to finish creating your account for <strong>Fundline<\\/strong>.<\\/p>\\n        <div style=\\\"margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;\\\">286628<\\/div>\\n        <p style=\\\"margin:0 0 12px;font-size:14px;line-height:1.7;\\\">This code expires in 15 minutes.<\\/p>\\n        <p style=\\\"margin:0;font-size:14px;line-height:1.7;color:#6b7280;\\\">If you did not request this, you can safely ignore this email.<\\/p><\\/td>\\n    <\\/tr>\\n    <tr>\\n      <td style=\\\"padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;\\\">\\n        This is an automated message from MicroFin. Visit\\n        <a href=\\\"https:\\/\\/microfinweb-production.up.railway.app\\\" style=\\\"color:#0F766E;text-decoration:none;\\\">https:\\/\\/microfinweb-production.up.railway.app<\\/a>\\n        for more details.\\n      <\\/td>\\n    <\\/tr>\\n  <\\/table>\\n<\\/body>\\n<\\/html>\",\"textContent\":\"Hello batmaan waybnee,\\n\\nUse this verification code to finish creating your account for Fundline: 286628\\n\\nThis code expires in 15 minutes.\",\"tags\":[\"registration\",\"otp\"]}','{\"messageId\":\"<202604191722.80631543251@smtp-relay.mailin.fr>\"}','2026-04-19 17:22:01');
/*!40000 ALTER TABLE `email_delivery_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,4,'YRL5G6VV30',NULL,'',NULL,'',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,'2026-04-08','Active',NULL,NULL,'2026-04-08 03:51:05','2026-04-08 03:51:05'),(2,5,'YRL5G6VV30',NULL,'',NULL,'',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,'2026-04-08','Active',NULL,NULL,'2026-04-08 03:52:36','2026-04-08 03:52:36'),(3,10,'JRE0JW6ZIX',NULL,'',NULL,'',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,'2026-04-19','Active',NULL,NULL,'2026-04-19 07:36:42','2026-04-19 07:36:42'),(5,16,'YRL5G6VV30',NULL,'Rhob Gleomir',NULL,'Rivera',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,'2026-04-19','Active',NULL,NULL,'2026-04-19 09:48:07','2026-04-19 09:48:07');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_applications`
--

DROP TABLE IF EXISTS `loan_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_applications`
--

LOCK TABLES `loan_applications` WRITE;
/*!40000 ALTER TABLE `loan_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_products`
--

DROP TABLE IF EXISTS `loan_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `min_amount` decimal(12,2) NOT NULL,
  `max_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `interest_type` enum('Declining Balance','Flat') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Declining Balance',
  `min_term_months` int NOT NULL,
  `max_term_months` int NOT NULL,
  `billing_cycle` enum('Monthly','Quarterly','Semi-Annually','Yearly') COLLATE utf8mb4_unicode_ci DEFAULT 'Monthly',
  `processing_fee_percentage` decimal(5,2) DEFAULT '0.00',
  `service_charge` decimal(10,2) DEFAULT '0.00',
  `documentary_stamp` decimal(10,2) DEFAULT '0.00',
  `insurance_fee_percentage` decimal(5,2) DEFAULT '0.00',
  `penalty_rate` decimal(5,2) DEFAULT '0.00',
  `penalty_type` enum('Daily','Monthly','Flat') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Daily',
  `grace_period_days` int DEFAULT '0',
  `minimum_credit_score` int DEFAULT '500',
  `required_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `early_settlement_fee_type` enum('Percentage','Fixed') COLLATE utf8mb4_unicode_ci DEFAULT 'Percentage',
  `early_settlement_fee_value` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `loan_products_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_products`
--

LOCK TABLES `loan_products` WRITE;
/*!40000 ALTER TABLE `loan_products` DISABLE KEYS */;
INSERT INTO `loan_products` VALUES (2,'YRL5G6VV30','PAUTANGINA','Business Loan','businessspautanginss, fixed early payment shit',5000.00,100000.00,3.00,'Flat',6,24,'Quarterly',2.00,0.00,0.00,0.00,0.00,'Daily',3,500,NULL,1,'2026-04-19 07:53:55','2026-04-19 07:53:55','Fixed',1000.00);
/*!40000 ALTER TABLE `loan_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mobile_install_attributions`
--

DROP TABLE IF EXISTS `mobile_install_attributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mobile_install_attributions`
--

LOCK TABLES `mobile_install_attributions` WRITE;
/*!40000 ALTER TABLE `mobile_install_attributions` DISABLE KEYS */;
INSERT INTO `mobile_install_attributions` VALUES (1,'51385d90eff049d4621f4cf541d1c27d08f31ad773b86df6','YRL5G6VV30','fundline','136.158.61.115','2cedf3cfd2870db087931acdbacf134257a89781fe32ed9739371f17c91d810c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:37:59','2026-04-10 10:37:59'),(2,'9d1316f6fc60f47c0cc117e6478fb78e68fea8ab04778742','YRL5G6VV30','fundline','136.158.61.115','2cedf3cfd2870db087931acdbacf134257a89781fe32ed9739371f17c91d810c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:55:52','2026-04-10 10:55:52'),(3,'0d585b8c1f330a5b474a11801067422c9d491945d185fb1f','YRL5G6VV30','fundline','136.158.61.115','7526c00e18367b920e093b58c42973f7ef17b0623f24232159ddf110f63f8cbf','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:56:54','2026-04-10 10:56:54'),(4,'f40a4b2503a562722cbbffa2928c2e0d5529577f62cd2919','YRL5G6VV30','fundline','136.158.61.115','7526c00e18367b920e093b58c42973f7ef17b0623f24232159ddf110f63f8cbf','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:57:01','2026-04-10 10:57:01'),(5,'3ac6cc871e1696dbfca0fe3e95daa005b66c173e4771aff4','YRL5G6VV30','fundline','136.158.61.115','7526c00e18367b920e093b58c42973f7ef17b0623f24232159ddf110f63f8cbf','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:58:30','2026-04-10 10:58:30'),(6,'5ffdaf8f1f44cebe1a924baa3d64a81bf63036538c3858f4','JRE0JW6ZIX','chinabank','136.158.61.115','7526c00e18367b920e093b58c42973f7ef17b0623f24232159ddf110f63f8cbf','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=chinabank',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:59:25','2026-04-10 10:59:25'),(7,'37c81acb88fab37ac71d61a25cec85afa4c4de6fd7385b98','JRE0JW6ZIX','chinabank','136.158.61.115','2cedf3cfd2870db087931acdbacf134257a89781fe32ed9739371f17c91d810c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=chinabank',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:59:41','2026-04-10 10:59:41'),(8,'b41cc56b90537cf1ee9e6d9649cfe4c1710247f1738e6ce4','YRL5G6VV30','fundline','136.158.61.115','2cedf3cfd2870db087931acdbacf134257a89781fe32ed9739371f17c91d810c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 12:14:03','2026-04-10 12:14:03'),(9,'77dcbdcba236e33fd7f6dff5c0ba33ff98f87dfb511515a3','YRL5G6VV30','fundline','136.158.62.9','b37b08fd2dd73c3401d827b692d0eb30856a4cbbad02d174af2cc4bd15639554','Mozilla/5.0 (Linux; Android 10; SM-N960F Build/QP1A.190711.020; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/146.0.7680.166 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/555.0.0.56.66;]','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-09 13:45:50','2026-04-10 13:45:50'),(10,'970bb0b4cd0e10f090b50a1e378f960ff91a1663f4c30a20','YRL5G6VV30','fundline','136.158.62.9','69ea04d8e46c0585048e36e514e912d7b16736a6296438928c0dc27e78fb438d','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','android','',NULL,NULL,NULL,NULL,NULL,'2026-04-09 13:45:57','2026-04-10 13:45:57'),(11,'f90cc19010229528b319afdbf850778be31e16f200cfdba4','YRL5G6VV30','fundline','180.232.85.242','c0f31c349e479ab385a948f55f14609334d635fc80834d44a51ac5bdcb031ccd','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Mobile Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-10 08:15:10','2026-04-11 08:15:10'),(12,'e9078a60b9ddcc43e0ae4b5934ae97224e00ac74b98d1262','YRL5G6VV30','fundline','180.232.85.242','c0f31c349e479ab385a948f55f14609334d635fc80834d44a51ac5bdcb031ccd','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Mobile Safari/537.36','android','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=fundline',NULL,NULL,NULL,NULL,NULL,'2026-04-10 08:15:33','2026-04-11 08:15:33'),(13,'8182fd666c51c5a889433a62ca74fc3f2e7cb290e0960d4d','JRE0JW6ZIX','chinabank','136.158.62.9','387ae7aef0ff2cb88b68b8790a2f5eb8b677c19a765a3118517b46df17c55193','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=chinabank',NULL,NULL,NULL,NULL,NULL,'2026-04-16 13:30:04','2026-04-17 13:30:04'),(14,'171cc0b2f7d2780d0021ebe101d5f026461fcf2c86913c89','JRE0JW6ZIX','chinabank','136.158.62.9','387ae7aef0ff2cb88b68b8790a2f5eb8b677c19a765a3118517b46df17c55193','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','windows','https://microfinweb-production.up.railway.app/microfin_web/public_website/site.php?site=chinabank',NULL,NULL,NULL,NULL,NULL,'2026-04-16 13:30:07','2026-04-17 13:30:07');
/*!40000 ALTER TABLE `mobile_install_attributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_verifications`
--

DROP TABLE IF EXISTS `otp_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_verifications`
--

LOCK TABLES `otp_verifications` WRITE;
/*!40000 ALTER TABLE `otp_verifications` DISABLE KEYS */;
INSERT INTO `otp_verifications` VALUES (1,'rhobrivera20@gmail.com','511825','Verified','2026-04-08 03:53:51','2026-04-08 03:48:51'),(2,'microfin.otp@gmail.com','837905','Verified','2026-04-09 09:14:11','2026-04-09 09:09:11'),(3,'8heikin8@gmail.com','065570','Verified','2026-04-18 02:08:54','2026-04-18 02:03:54'),(4,'godofredoriverasr1941@gmail.com','440916','Pending','2026-04-19 13:28:44','2026-04-19 13:23:44');
/*!40000 ALTER TABLE `otp_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permission_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. CREATE_ACCOUNT, APPROVE_LOAN',
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `permission_code` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=1214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'VIEW_USERS','Users','Can view the list of users and employees','2026-04-08 03:45:31'),(2,'CREATE_USERS','Users','Can create new users and invite employees','2026-04-08 03:45:31'),(3,'MANAGE_ROLES','Roles','Can create custom roles and assign permissions','2026-04-08 03:45:31'),(4,'VIEW_CLIENTS','Clients','Can view client profiles and history','2026-04-08 03:45:31'),(5,'CREATE_CLIENTS','Clients','Can register new clients','2026-04-08 03:45:31'),(6,'VIEW_LOANS','Loans','Can view loan applications and active loans','2026-04-08 03:45:31'),(7,'CREATE_LOANS','Loans','Can draft new loan applications','2026-04-08 03:45:31'),(8,'APPROVE_LOANS','Loans','Can approve or reject Pending loans','2026-04-08 03:45:31'),(9,'PROCESS_PAYMENTS','Payments','Can receive and post loan payments','2026-04-08 03:45:31'),(10,'VIEW_REPORTS','Reports','Can generate and view financial reports','2026-04-08 03:45:31'),(11,'VIEW_APPLICATIONS','Applications','Can view Pending loan applications','2026-04-08 03:45:31'),(12,'MANAGE_APPLICATIONS','Applications','Can approve, reject, or process Pending loan applications','2026-04-08 03:45:31'),(13,'VIEW_CREDIT_ACCOUNTS','Credit Accounts','Can view borrower credit accounts, limit recommendations, and upgrade eligibility','2026-04-08 03:45:31'),(14,'EDIT_BILLING','System','Can edit billing and subscription settings','2026-04-08 03:50:38');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (3,1,'2026-04-08 03:50:47'),(3,2,'2026-04-08 03:50:47'),(3,4,'2026-04-08 03:50:47'),(3,5,'2026-04-08 03:50:47'),(3,6,'2026-04-08 03:50:47'),(3,7,'2026-04-08 03:50:47'),(3,8,'2026-04-08 03:50:47'),(3,9,'2026-04-08 03:50:47'),(3,10,'2026-04-08 03:50:47'),(3,11,'2026-04-08 03:50:47'),(3,12,'2026-04-08 03:50:47'),(3,13,'2026-04-08 03:50:47'),(7,1,'2026-04-19 07:36:25'),(7,2,'2026-04-19 07:36:25'),(7,4,'2026-04-19 07:36:25'),(7,5,'2026-04-19 07:36:25'),(7,6,'2026-04-19 07:36:25'),(7,7,'2026-04-19 07:36:25'),(7,8,'2026-04-19 07:36:25'),(7,9,'2026-04-19 07:36:25'),(7,10,'2026-04-19 07:36:25'),(7,11,'2026-04-19 07:36:25'),(7,12,'2026-04-19 07:36:25'),(7,13,'2026-04-19 07:36:25');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'YRL5G6VV30','billing_access_user_3','1','Billing',NULL,'Boolean',1,NULL,'2026-04-08 03:49:28'),(3,'YRL5G6VV30','next_billing_date','2026-05-08','Billing',NULL,'String',1,NULL,'2026-04-08 03:50:37'),(4,'YRL5G6VV30','mobile_app_build_status','shared_ready','Mobile App',NULL,'String',1,NULL,'2026-04-18 14:29:10'),(5,'YRL5G6VV30','mobile_app_build_message','The shared company mobile app is already the only supported APK. No tenant-specific build is required.','Mobile App',NULL,'String',1,NULL,'2026-04-18 14:29:10'),(6,'YRL5G6VV30','mobile_app_build_requested_at','2026-04-18T14:29:10+00:00','Mobile App',NULL,'String',1,NULL,'2026-04-18 14:29:10'),(7,'YRL5G6VV30','mobile_app_build_slug','fundline','Mobile App',NULL,'String',1,NULL,'2026-04-18 14:29:10'),(8,'YRL5G6VV30','mobile_app_build_app_name','fundline','Mobile App',NULL,'String',1,NULL,'2026-04-18 14:29:10'),(14,'YRL5G6VV30','support_email','',NULL,NULL,'String',1,NULL,'2026-04-08 03:51:55'),(15,'YRL5G6VV30','support_phone','',NULL,NULL,'String',1,NULL,'2026-04-08 03:51:55'),(16,'JRE0JW6ZIX','billing_access_user_6','1','Billing',NULL,'Boolean',1,NULL,'2026-04-09 09:09:53'),(18,'JRE0JW6ZIX','next_billing_date','2026-05-09','Billing',NULL,'String',1,NULL,'2026-04-09 09:11:08'),(19,'JRE0JW6ZIX','mobile_app_build_status','configuration_required','Mobile App',NULL,'String',1,NULL,'2026-04-09 09:28:48'),(20,'JRE0JW6ZIX','mobile_app_build_message','GitHub Actions token is not configured yet, so the tenant app build could not be started automatically.','Mobile App',NULL,'String',1,NULL,'2026-04-09 09:28:48'),(21,'JRE0JW6ZIX','mobile_app_build_requested_at','2026-04-09T09:28:48+00:00','Mobile App',NULL,'String',1,NULL,'2026-04-09 09:28:48'),(22,'JRE0JW6ZIX','mobile_app_build_slug','chinabank','Mobile App',NULL,'String',1,NULL,'2026-04-09 09:28:48'),(23,'JRE0JW6ZIX','mobile_app_build_app_name','ChinaBank','Mobile App',NULL,'String',1,NULL,'2026-04-09 09:28:48'),(34,'UHKYHQD9YW','billing_access_user_9','1','Billing',NULL,'Boolean',1,NULL,'2026-04-18 02:04:41'),(88,'YRL5G6VV30','policy_console_credit_limits','{\"scoring_setup\":{\"core\":{\"starting_credit_score\":320,\"repayment_score_bonus\":10,\"late_payment_score_penalty\":15},\"detailed_rules\":{\"upgrade\":{\"successful_repayment_cycles\":{\"enabled\":true,\"required_cycles\":3,\"score_points\":5},\"maximum_late_payments_review\":{\"enabled\":true,\"maximum_allowed\":1,\"review_period_days\":90,\"score_points\":5},\"no_active_overdue\":{\"enabled\":true,\"review_period_days\":0,\"score_points\":5}},\"downgrade\":{\"late_payments_review\":{\"enabled\":true,\"trigger_count\":2,\"review_period_days\":90,\"score_points\":12},\"overdue_days_threshold\":{\"enabled\":true,\"days\":15,\"score_points\":25}}}},\"score_bands\":{\"rows\":[{\"id\":\"band_at_risk\",\"label\":\"At-Risk\",\"min_score\":50,\"max_score\":249,\"base_growth_percent\":1,\"micro_percent_per_point\":0.02},{\"id\":\"band_entry\",\"label\":\"Entry\",\"min_score\":250,\"max_score\":449,\"base_growth_percent\":5,\"micro_percent_per_point\":0.034},{\"id\":\"band_standard\",\"label\":\"Standard\",\"min_score\":450,\"max_score\":649,\"base_growth_percent\":10,\"micro_percent_per_point\":0.025},{\"id\":\"band_plus\",\"label\":\"Plus\",\"min_score\":650,\"max_score\":849,\"base_growth_percent\":15,\"micro_percent_per_point\":0.02},{\"id\":\"band_premium\",\"label\":\"Premium\",\"min_score\":850,\"max_score\":null,\"base_growth_percent\":18,\"micro_percent_per_point\":0.01}]},\"limit_assignment\":{\"initial_limit_percent_of_income\":40,\"use_default_lending_cap\":false,\"default_lending_cap_amount\":0,\"apply_score_changes_immediately\":true}}','Credit',NULL,'JSON',1,NULL,'2026-04-19 06:33:04'),(89,'YRL5G6VV30','policy_console_decision_rules','{\"workflow\":{\"approval_mode\":\"semi_automatic\"},\"decision_rules\":{\"demographics\":{\"age_enabled\":false,\"min_age\":21,\"max_age\":65,\"residency_tenure_enabled\":false,\"min_residency_months\":6,\"employment_status_enabled\":true,\"eligible_statuses\":[\"full_time\",\"part_time\",\"contract\",\"freelancer\",\"self_employed\",\"casual\",\"retired\",\"student\",\"unemployed\"]},\"affordability\":{\"income_enabled\":false,\"min_monthly_income\":10000,\"dti_enabled\":false,\"max_dti_percentage\":45,\"pti_enabled\":false,\"max_pti_percentage\":20},\"guardrails\":{\"score_thresholds_enabled\":true,\"auto_reject_floor\":50,\"hard_approval_threshold\":900,\"cooling_period_enabled\":true,\"rejected_cooling_days\":30},\"exposure\":{\"multiple_active_loans_enabled\":false,\"guarantor_required_enabled\":false,\"guarantor_required_above_amount\":null,\"collateral_required_enabled\":false,\"collateral_required_above_amount\":null},\"score_thresholds\":{\"auto_reject_floor\":50,\"hard_approval_threshold\":900},\"loan_capital\":{\"minimum_capital_requirement\":10000},\"borrowing_access_rules\":{\"allow_multiple_active_loans_within_remaining_limit\":false},\"demographic_guardrails\":{\"min_age\":21,\"max_age\":65}}}','Credit',NULL,'JSON',1,NULL,'2026-04-19 10:57:12'),(90,'YRL5G6VV30','policy_console_compliance_documents','{\"document_requirements\":[{\"category_key\":\"identity_document\",\"label\":\"Identity Document\",\"requirement\":\"required\",\"accepted_document_names\":[\"National ID (PhilID/ePhilID)\",\"Passport\",\"Driver\'s License\",\"UMID\",\"SSS ID\",\"GSIS e-Card\",\"PRC ID\",\"Postal ID\",\"Seaman\'s Book / SIRB\",\"Senior Citizen ID\",\"PWD ID\",\"NBI Clearance\",\"Police Clearance\",\"TIN ID\",\"School ID\",\"Company ID\",\"Barangay ID\",\"OFW ID\",\"OWWA ID\",\"IBP ID\",\"Government Office / GOCC ID\"],\"document_options\":[{\"document_name\":\"National ID (PhilID/ePhilID)\",\"is_accepted\":true},{\"document_name\":\"Passport\",\"is_accepted\":true},{\"document_name\":\"Driver\'s License\",\"is_accepted\":true},{\"document_name\":\"UMID\",\"is_accepted\":true},{\"document_name\":\"SSS ID\",\"is_accepted\":true},{\"document_name\":\"GSIS e-Card\",\"is_accepted\":true},{\"document_name\":\"PRC ID\",\"is_accepted\":true},{\"document_name\":\"Postal ID\",\"is_accepted\":true},{\"document_name\":\"Seaman\'s Book / SIRB\",\"is_accepted\":true},{\"document_name\":\"Senior Citizen ID\",\"is_accepted\":true},{\"document_name\":\"PWD ID\",\"is_accepted\":true},{\"document_name\":\"Voter\'s ID\",\"is_accepted\":false},{\"document_name\":\"NBI Clearance\",\"is_accepted\":true},{\"document_name\":\"Police Clearance\",\"is_accepted\":true},{\"document_name\":\"TIN ID\",\"is_accepted\":true},{\"document_name\":\"School ID\",\"is_accepted\":true},{\"document_name\":\"Company ID\",\"is_accepted\":true},{\"document_name\":\"Barangay ID\",\"is_accepted\":true},{\"document_name\":\"OFW ID\",\"is_accepted\":true},{\"document_name\":\"OWWA ID\",\"is_accepted\":true},{\"document_name\":\"IBP ID\",\"is_accepted\":true},{\"document_name\":\"Government Office / GOCC ID\",\"is_accepted\":true}]}]}','Compliance',NULL,'JSON',1,NULL,'2026-04-19 08:58:05');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_billing_invoices`
--

DROP TABLE IF EXISTS `tenant_billing_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_billing_invoices`
--

LOCK TABLES `tenant_billing_invoices` WRITE;
/*!40000 ALTER TABLE `tenant_billing_invoices` DISABLE KEYS */;
INSERT INTO `tenant_billing_invoices` VALUES (1,'YRL5G6VV30','INV-20260408-7AF3E1',29999.00,'2026-04-08','2026-05-07','2026-04-08','Paid',NULL,NULL,'2026-04-08 03:50:37','2026-04-08 03:50:37'),(2,'JRE0JW6ZIX','INV-20260409-702297',29999.00,'2026-04-09','2026-05-08','2026-04-09','Paid',NULL,NULL,'2026-04-09 09:11:08','2026-04-09 09:11:08');
/*!40000 ALTER TABLE `tenant_billing_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_billing_payment_methods`
--

DROP TABLE IF EXISTS `tenant_billing_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_billing_payment_methods`
--

LOCK TABLES `tenant_billing_payment_methods` WRITE;
/*!40000 ALTER TABLE `tenant_billing_payment_methods` DISABLE KEYS */;
INSERT INTO `tenant_billing_payment_methods` VALUES (1,'YRL5G6VV30','1234','CARD','Bruce Wayne',2,2036,_binary 'UzMNrvAUjfUpvdGcGLsrazo6Vh2p35/Vu2BDy/WhlIvsLiOiNA6mTFLnN0/mynw48Z8=',1,'2026-04-08 03:50:37'),(2,'JRE0JW6ZIX','1234','CARD','Xi Jin Ping',2,2037,_binary 'Bmomy3qJ55ynyMtmMJSGdTo6nka4E6qtE6JJmQ0H58A2dYEVhW9inrGkoQoUd0fEV6s=',1,'2026-04-09 09:11:08');
/*!40000 ALTER TABLE `tenant_billing_payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_branding`
--

DROP TABLE IF EXISTS `tenant_branding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_branding`
--

LOCK TABLES `tenant_branding` WRITE;
/*!40000 ALTER TABLE `tenant_branding` DISABLE KEYS */;
INSERT INTO `tenant_branding` VALUES (1,'YRL5G6VV30','/admin-draft-withmobile/admin-draft/microfin_web/uploads/tenant_logos/YRL5G6VV30logo.jpg','Inter','#ff0202','#991b1b','#0f172a','#64748b','#ffe0e0','#ffffff','#ff0000',0,'none','2026-04-08 03:51:39','2026-04-18 16:52:56'),(4,'JRE0JW6ZIX','/admin-draft-withmobile/admin-draft/microfin_web/uploads/tenant_logos/logo_JRE0JW6ZIX_1775726904_61924e84.png','Inter','#f1686d','#991b1b','#0f172a','#64748b','#f8fafc','#ffffff','#e2e8f0',1,'sm','2026-04-09 09:28:24','2026-04-09 09:28:47');
/*!40000 ALTER TABLE `tenant_branding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_feature_toggles`
--

DROP TABLE IF EXISTS `tenant_feature_toggles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_feature_toggles` (
  `toggle_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `toggle_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`toggle_id`),
  UNIQUE KEY `uq_tenant_toggle` (`tenant_id`,`toggle_key`),
  CONSTRAINT `tenant_feature_toggles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_feature_toggles`
--

LOCK TABLES `tenant_feature_toggles` WRITE;
/*!40000 ALTER TABLE `tenant_feature_toggles` DISABLE KEYS */;
INSERT INTO `tenant_feature_toggles` VALUES (1,'YRL5G6VV30','public_website_enabled',1,'2026-04-08 03:51:39'),(3,'YRL5G6VV30','booking_system',0,'2026-04-08 03:51:55'),(4,'YRL5G6VV30','user_registration',0,'2026-04-08 03:51:55'),(5,'YRL5G6VV30','maintenance_mode',0,'2026-04-08 03:51:55'),(6,'YRL5G6VV30','email_notifications',0,'2026-04-08 03:51:55'),(8,'JRE0JW6ZIX','public_website_enabled',1,'2026-04-09 09:28:24');
/*!40000 ALTER TABLE `tenant_feature_toggles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_legitimacy_documents`
--

DROP TABLE IF EXISTS `tenant_legitimacy_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_legitimacy_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `tenant_legitimacy_documents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_legitimacy_documents`
--

LOCK TABLES `tenant_legitimacy_documents` WRITE;
/*!40000 ALTER TABLE `tenant_legitimacy_documents` DISABLE KEYS */;
INSERT INTO `tenant_legitimacy_documents` VALUES (1,'YRL5G6VV30','Reports_Analytics.pdf','../uploads/business_permits/YRL5G6VV30_doc_1_1775620155_1830.pdf','2026-04-08 03:49:15'),(2,'JRE0JW6ZIX','Reports_Analytics.pdf','../uploads/business_permits/JRE0JW6ZIX_doc_1_1775725767_10c6.pdf','2026-04-09 09:09:26'),(3,'UHKYHQD9YW','Pasted text.txt','../uploads/business_permits/UHKYHQD9YW_doc_1_1776477857_f949.txt','2026-04-18 02:04:15');
/*!40000 ALTER TABLE `tenant_legitimacy_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_website_content`
--

DROP TABLE IF EXISTS `tenant_website_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_website_content` (
  `tenant_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `layout_template` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'template1.php',
  `website_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `fk_tenant_website_content_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_website_content`
--

LOCK TABLES `tenant_website_content` WRITE;
/*!40000 ALTER TABLE `tenant_website_content` DISABLE KEYS */;
INSERT INTO `tenant_website_content` VALUES ('JRE0JW6ZIX','template2.php','{\"company_name\":\"ChinaBank\",\"short_name\":\"\",\"hero_image\":\"\",\"text_heading_color\":\"#290f0f\",\"text_body_color\":\"#8b6565\",\"btn_bg_color\":\"#eb2424\",\"btn_text_color\":\"#ffffff\",\"border_radius\":\"16\",\"shadow_intensity\":\"0.1\",\"show_services\":true,\"show_testimonials\":true,\"show_app_promo\":true,\"section_styles\":[],\"services\":[{\"icon\":\"person\",\"title\":\"Personal Loan\",\"description\":\"Fast approval for your personal needs.\"}],\"hero_badge_text\":\"Verified Partner\",\"hero_title\":\"Empowering Your Financial Future\",\"hero_subtitle\":\"Get flexible loans with fast approval and transparent terms.\",\"testi_1_text\":\"\\\"MicroFin OS changed the way we handle our daily operations. Approvals are incredibly fast.\\\"\",\"testi_1_name\":\"- Maria Santos, Small Business Owner\",\"testi_2_text\":\"\\\"Transparent fees and a beautiful app. I recommend them to everyone in my cooperative.\\\"\",\"testi_2_name\":\"- Juan Dela Cruz, Freelancer\",\"app_promo_title\":\"Manage Your Loans on the Go\",\"app_promo_desc\":\"Download our secure mobile app to track payments, apply for new loans, and chat with our support team instantly.\",\"footer_desc\":\"Your trusted microfinance partner.\",\"contact_email\":\"hello@microfin.os\",\"contact_phone\":\"0900-123-4567\"}','2026-04-09 09:28:24','2026-04-09 09:28:47'),('YRL5G6VV30','template3.php','{\"company_name\":\"fundline\",\"short_name\":\"\",\"hero_image\":\"/admin-draft-withmobile/admin-draft/microfin_web/uploads/hero/YRL5G6VV30hero.png\",\"text_heading_color\":\"#0f172a\",\"text_body_color\":\"#64748b\",\"btn_bg_color\":\"#2563eb\",\"btn_text_color\":\"#ffffff\",\"border_radius\":\"16\",\"shadow_intensity\":\"0.1\",\"show_services\":true,\"show_calculator\":true,\"show_stats\":true,\"show_about\":true,\"show_download\":false,\"section_styles\":{\"sec_stats\":{\"bg\":\"#ff0202\",\"gradient\":false}},\"services\":[{\"icon\":\"person\",\"title\":\"Personal Loan\",\"description\":\"Fast approval for your personal needs.\"}],\"hero_badge_text\":\"VERIFIED PARTNER\",\"hero_title\":\"Empowering Your Financial Future\",\"hero_subtitle\":\"Get flexible loans with fast approval and transparent terms.\",\"about_body\":\"We believe in empowering our community through accessible financial tools.\",\"download_description\":\"Track your loans, submit applications, receive notifications...\",\"footer_desc\":\"Your trusted microfinance partner.\",\"contact_address\":\"123 Finance Ave, Business District\",\"contact_phone\":\"0900-123-4567\",\"contact_email\":\"hello@microfin.os\",\"contact_hours\":\"Mon-Fri: 8AM - 5PM\"}','2026-04-08 03:51:39','2026-04-18 16:52:57');
/*!40000 ALTER TABLE `tenant_website_content` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES ('JRE0JW6ZIX','ChinaBank','chinabank',NULL,'2026-05-09 09:09:52','Beijing, China','Unlimited',NULL,NULL,-1,-1,5.00,NULL,29999.00,'Monthly','2026-05-09',1,6,'Active','tenant_application',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:09:25','2026-04-09 09:11:08'),('UHKYHQD9YW','Default System MicroFin','defaultsystemmicrofin',NULL,'2026-05-18 02:04:40','Manila, Capital District, Metro Manila, Philippines','Enterprise',NULL,NULL,10000,5000,5.00,NULL,19999.00,'Monthly',NULL,0,0,'Active','tenant_application',NULL,NULL,NULL,NULL,NULL,'2026-04-18 02:04:15','2026-04-18 02:04:40'),('YRL5G6VV30','Fundline','fundline',NULL,'2026-05-08 03:49:28','Manila, Capital District, Metro Manila, Philippines','Unlimited',NULL,NULL,-1,-1,5.00,NULL,29999.00,'Monthly','2026-05-08',1,6,'Active','tenant_application',NULL,NULL,NULL,NULL,NULL,'2026-04-08 03:49:15','2026-04-08 03:50:37');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,NULL,'Super Admin','Master Platform Administrator',1,NULL,NULL,'2026-04-08 03:45:21','2026-04-08 03:45:21'),(2,'YRL5G6VV30','Admin','Default system administrator',1,NULL,NULL,'2026-04-08 03:49:15','2026-04-08 03:49:15'),(3,'YRL5G6VV30','Manager','Custom role',0,NULL,NULL,'2026-04-08 03:50:47','2026-04-08 03:50:47'),(4,'JRE0JW6ZIX','Admin','Default system administrator',1,NULL,NULL,'2026-04-09 09:09:25','2026-04-09 09:09:25'),(5,'YRL5G6VV30','Client','Default Client Role',0,NULL,NULL,'2026-04-09 11:05:30','2026-04-09 11:05:30'),(6,'UHKYHQD9YW','Admin','Default system administrator',1,NULL,NULL,'2026-04-18 02:04:15','2026-04-18 02:04:15'),(7,'JRE0JW6ZIX','Manager','Custom role',0,NULL,NULL,'2026-04-19 07:36:25','2026-04-19 07:36:25');
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (1,1,NULL,'0b1df48273a5a8195edb30e9926c7b0dc997b8abe47a58e9327bcadb4cb773a8',NULL,NULL,'2026-04-08 03:45:58','2026-04-08 03:48:26','2026-04-08 03:48:26'),(2,2,NULL,'7273dafbc32f309534c655ea3448af26b57862d4b44c7c92b8a39b27a4ece267',NULL,NULL,'2026-04-08 03:47:52','2026-04-08 03:48:20','2026-04-08 04:18:20'),(3,1,NULL,'f5a142b9ecfe01cf1273da80e03e4fba28d392d2b9ae50253d8ced6438dd6d5f',NULL,NULL,'2026-04-08 03:49:25','2026-04-08 03:49:34','2026-04-08 03:49:34'),(4,3,'YRL5G6VV30','1ae3663aafb2019673aae02d71c20841f0bd73f9cae6604dbd27b44d8a43f7ca',NULL,NULL,'2026-04-08 03:49:52','2026-04-08 03:52:36','2026-04-08 04:22:36'),(5,4,'YRL5G6VV30','86f8d0b4c597d3f295744a75f16a5365091f5f4f0eb8f9ab3701f8246d756779',NULL,NULL,'2026-04-08 03:52:11','2026-04-08 03:52:25','2026-04-08 04:22:25'),(6,1,NULL,'5030aec0214e2a22c7dc590a6a06e3eafe775945d311fd72c8be772ffe325662',NULL,NULL,'2026-04-08 04:45:19','2026-04-08 04:45:39','2026-04-08 04:45:39'),(7,1,NULL,'9c2eed59a856d902d850dec6bdc14e17dbb0fa593349547159200fa497f0712c',NULL,NULL,'2026-04-08 05:50:40','2026-04-08 05:55:24','2026-04-08 05:55:24'),(8,1,NULL,'f6f14a18d29e1d4a7f71c5cf1c90204d0602e2720ab46b46dcf21d9f3e15914e',NULL,NULL,'2026-04-08 06:08:34','2026-04-08 06:15:35','2026-04-08 06:15:35'),(9,1,NULL,'5203fa0c2c23f5c668760f6d922e98e05ef9c7ee5d774c4b4189d3aedd12ec9f',NULL,NULL,'2026-04-08 10:15:15','2026-04-08 10:16:23','2026-04-08 10:16:23'),(10,1,NULL,'a140b0568710e1c7cfd2ce4de9d39fe5b1422994ddd576785ff0274d5dd6e0ec',NULL,NULL,'2026-04-08 10:23:28','2026-04-08 11:32:26','2026-04-08 11:32:26'),(11,3,'YRL5G6VV30','6cfd0ad092a08a1e93c95a13de32187da11f8b4ade9272dd6ea78ed9c7b970d3',NULL,NULL,'2026-04-08 10:24:07','2026-04-08 10:25:22','2026-04-08 10:55:22'),(12,3,'YRL5G6VV30','0ef34a2e79a5d598de3719de7bad2835b7e3f7c936e34038efc48edf42f52dc4',NULL,NULL,'2026-04-08 11:16:12','2026-04-08 11:16:20','2026-04-08 11:46:20'),(13,1,NULL,'958c29f165b3c041a22f8e90e6d91fbd48568f96d82f419652df2b7c6dc72d07',NULL,NULL,'2026-04-08 14:45:51','2026-04-08 14:46:04','2026-04-08 14:46:04'),(14,1,NULL,'ab01bd5078bfb2e7f496fcdb862bcbc4af465bcc949f3a4377c0e4b6e45d4aee',NULL,NULL,'2026-04-09 00:59:38','2026-04-09 01:00:33','2026-04-09 01:00:33'),(15,1,NULL,'67cd5053088544a507122871a5e596c0b8b31f8383c0fec47d464b6b808a6b14',NULL,NULL,'2026-04-09 01:03:11','2026-04-09 01:03:11','2026-04-09 01:33:11'),(16,1,NULL,'f43e76ed645107ae7b5f67452e72f9164b51cd76b08a86a8e01196ea92fb6288',NULL,NULL,'2026-04-09 01:03:11','2026-04-09 01:03:26','2026-04-09 01:03:26'),(17,1,NULL,'83f8e094e481dfbbb8bccfbec127aeab54ad6a973edd99858fdfbb0aa7fbc814',NULL,NULL,'2026-04-09 03:03:36','2026-04-09 03:03:56','2026-04-09 03:03:56'),(18,1,NULL,'d2f0c4813fd3694b0818e18e1e96baba64e94281deead80f05042d1e8277d94e',NULL,NULL,'2026-04-09 08:42:17','2026-04-09 08:42:35','2026-04-09 08:42:35'),(19,1,NULL,'6450c72fc5e33bcd0d81f0d41f15e6c5ad307169bca2d4cb16fabd0b2b5e5340',NULL,NULL,'2026-04-09 08:45:44','2026-04-09 08:46:32','2026-04-09 08:46:32'),(20,1,NULL,'0cacdbf1e16198db44646f5c48888169766c72d44ffb8653a7793e2c6d36306a',NULL,NULL,'2026-04-09 08:48:30','2026-04-09 09:00:19','2026-04-09 09:00:19'),(21,1,NULL,'a0238f9970a012c12634766feff288327faf96a3644b3e327f4f532409d36da1',NULL,NULL,'2026-04-09 09:09:38','2026-04-09 12:06:55','2026-04-09 12:06:55'),(22,6,'JRE0JW6ZIX','60035a1734d47da3683a0693d939e658a0c1abc9dfce0b3650b1441793c7768c',NULL,NULL,'2026-04-09 09:10:26','2026-04-09 09:11:55','2026-04-09 09:41:55'),(23,6,'JRE0JW6ZIX','562aa86aeb5fd83a0cb0e4d55b2369f48c2c5ef2d386b6a30bc312b8866791a5',NULL,NULL,'2026-04-09 09:27:11','2026-04-09 09:28:49','2026-04-09 09:58:49'),(24,1,NULL,'71f7fd64b8b957563c06832729cdddbda4f7d281a4aaea3da3c38c1780f638ad',NULL,NULL,'2026-04-09 10:15:55','2026-04-09 11:46:53','2026-04-09 11:46:53'),(25,1,NULL,'904ce3c88580c9bd8d08784b1cf5131d95c933e3e53f7739f1c654153e26422d',NULL,NULL,'2026-04-09 13:20:58','2026-04-09 13:28:39','2026-04-09 13:28:39'),(26,1,NULL,'364aa35d4403a375b61316915534931564fea6fbd3ae36209e58f527351b7d15',NULL,NULL,'2026-04-09 13:28:46','2026-04-09 13:29:10','2026-04-09 13:29:10'),(27,1,NULL,'42d6bdb34c64d6384b09ab5ec7547e4bebeebf4cbcdceed7d8bb0bca7f25af3d',NULL,NULL,'2026-04-10 07:50:11','2026-04-10 07:50:34','2026-04-10 07:50:34'),(28,1,NULL,'7234e93b0af94a8cdbe85fe5dc77920a2bfeae83105725793a17247c288ddb37',NULL,NULL,'2026-04-10 08:14:49','2026-04-10 08:14:55','2026-04-10 08:44:55'),(29,1,NULL,'49cba7183a1b8b7069dcc885321beda4817f7405f0ec02f2d88fa6ef583042bb',NULL,NULL,'2026-04-14 00:22:24','2026-04-14 11:20:12','2026-04-14 11:20:12'),(30,6,'JRE0JW6ZIX','46f894d15d3294d5037748a6327a72130332985e7fac24a832a02c9916bf67fb',NULL,NULL,'2026-04-14 00:22:41','2026-04-14 00:23:59','2026-04-14 00:23:59'),(31,6,'JRE0JW6ZIX','3be733e77525c372341476f367b01c13d37915d5df03221e9029aab19651a1f4',NULL,NULL,'2026-04-14 00:24:05','2026-04-14 00:24:05','2026-04-14 00:54:05'),(32,1,NULL,'95a448c6f8b06709beb2e4c4019524cfb0b9ddc37b5db1e5cdbe0bf60de2de0e',NULL,NULL,'2026-04-14 00:24:58','2026-04-14 11:17:32','2026-04-14 11:17:32'),(33,3,'YRL5G6VV30','23b958be2d1cc8d646e215e23b9b8dd6477c632a5937b4a74b37a37ab0821110',NULL,NULL,'2026-04-14 00:25:26','2026-04-14 01:53:29','2026-04-14 01:53:29'),(34,3,'YRL5G6VV30','03767dcdab19cf95977ce27aab5c00a8389276d19936c02389044b083627c27f',NULL,NULL,'2026-04-14 01:53:44','2026-04-14 11:18:18','2026-04-14 11:18:18'),(35,3,'YRL5G6VV30','d3e3d016a2bdb674f7d9e581c0e0117a8dbe42531364eb7842a98b8bac80ede9',NULL,NULL,'2026-04-14 11:18:28','2026-04-14 11:19:02','2026-04-14 11:49:02'),(36,3,'YRL5G6VV30','ec2fc8208a4f7723ba0e83cd82d8dedc81dbf220a21b3ebdd3e3e35fa6e121f4',NULL,NULL,'2026-04-14 11:21:05','2026-04-14 11:22:39','2026-04-14 11:22:39'),(37,1,NULL,'12624e3fca5b684d6a6f0cae7c30307cd851b7b2aed2d028638762b0fe49bfd5',NULL,NULL,'2026-04-14 11:27:10','2026-04-14 11:31:10','2026-04-14 11:31:10'),(38,1,NULL,'51f6dbcf4e123df0a48fdaaec2a3462ca736d783d1a003692fd002710d44be90',NULL,NULL,'2026-04-14 11:32:50','2026-04-14 11:34:59','2026-04-14 12:04:59'),(39,1,NULL,'ec5c7049f3815838dbd19ed2ac708cc5af2061f46e4b6de1d1f0dc35b8161b6b',NULL,NULL,'2026-04-16 11:15:23','2026-04-16 19:25:45','2026-04-16 19:25:45'),(40,3,'YRL5G6VV30','5eb771a5e4558ece56b3b7620df67dcf8b594cae5a474ffc02d124a238931b76',NULL,NULL,'2026-04-16 11:15:40','2026-04-16 11:15:41','2026-04-16 11:45:41'),(41,1,NULL,'53f3a81dd2d919a12a272bb9a554ac3c9fffccf63fc8da52b2ccaf3a20b9d882',NULL,NULL,'2026-04-16 13:24:44','2026-04-16 13:32:40','2026-04-16 14:02:40'),(42,3,'YRL5G6VV30','23439ac49e4ccc741858c480d8edee5193a6fb2323d3c37495f72bb323d8957b',NULL,NULL,'2026-04-17 01:12:49','2026-04-17 20:07:08','2026-04-17 20:07:08'),(43,3,'YRL5G6VV30','0a3f47a60eb59d6344e5eb9d16db025d0e4f97dee1d07a6bd734c7f349fec166',NULL,NULL,'2026-04-17 20:07:22','2026-04-17 23:06:27','2026-04-17 23:06:27'),(44,3,'YRL5G6VV30','10644b944bd1d3f46f218ea752466dcc4d6c0d7adfe6b560c10f5b76e58942b1',NULL,NULL,'2026-04-17 23:06:41','2026-04-18 01:36:07','2026-04-18 01:36:07'),(45,3,'YRL5G6VV30','a97576a7f9350a7d092efee64991c16fc1da2f406ee3978dea5df1000e375414',NULL,NULL,'2026-04-18 00:56:58','2026-04-18 01:42:33','2026-04-18 01:42:33'),(46,3,'YRL5G6VV30','4d6149fcead25242acea81ae3264a2bc793a2f3de8e3c26e230e6e0cb42ca334',NULL,NULL,'2026-04-18 01:36:32','2026-04-18 01:45:03','2026-04-18 02:15:03'),(47,3,'YRL5G6VV30','8563f425e429cf8df71e3fa34aab8b4b547554227baa7d34cf9136453daf6297',NULL,NULL,'2026-04-18 01:42:46','2026-04-18 01:42:50','2026-04-18 02:12:50'),(48,3,'YRL5G6VV30','da424ef8b0a224026095ab87dc8b23904d189a01c0b035f60a3bf5c12dd58b77',NULL,NULL,'2026-04-18 01:45:24','2026-04-18 01:45:25','2026-04-18 02:15:25'),(49,3,'YRL5G6VV30','5ce7615488794033c679ef0675cdee8bb653d14c3076322a870ebab1084a8142',NULL,NULL,'2026-04-18 01:48:12','2026-04-18 02:07:20','2026-04-18 02:07:20'),(50,1,NULL,'225be0765ea62e5b51b4e228d361ffa74a4d89f12456c12b2caa40b8a9d365c8',NULL,NULL,'2026-04-18 02:04:28','2026-04-18 02:05:02','2026-04-18 02:05:02'),(51,9,'UHKYHQD9YW','7002a2b2ae948cdd37f577034ec007aa5c2f218027ffc64ccc2887da583fda08',NULL,NULL,'2026-04-18 02:05:13','2026-04-18 02:29:57','2026-04-18 02:59:57'),(52,9,'UHKYHQD9YW','b65930a2d01d35b9fe43cb0c109813c6884a75e9034a206fb6a2d2008080cbf2',NULL,NULL,'2026-04-18 02:07:42','2026-04-18 02:07:44','2026-04-18 02:37:44'),(53,3,'YRL5G6VV30','38f5e254b048b303d5f0d285f12b87afe7bfde78320df4dc8276415ac96431e2',NULL,NULL,'2026-04-18 02:10:29','2026-04-18 02:52:50','2026-04-18 02:52:50'),(54,1,NULL,'0d1c578a11b0bda94ffec909eb30e05c588836f533c1769686377e0161d4aa64',NULL,NULL,'2026-04-18 02:44:06','2026-04-18 07:30:14','2026-04-18 07:30:14'),(55,3,'YRL5G6VV30','386107dc29341108a1e5fe7fe9f56c3ab03039f9574ea8cf1e336c6ed4b10cd1',NULL,NULL,'2026-04-18 02:52:59','2026-04-18 02:56:45','2026-04-18 02:56:45'),(56,3,'YRL5G6VV30','f869b3c00c92e9f5b551e9dd3215a9c00198b04900cdccf492217ce15f90070d',NULL,NULL,'2026-04-18 03:01:30','2026-04-18 04:00:27','2026-04-18 04:00:27'),(57,1,NULL,'d0059e5631a2357f2c65baa51aaa4f590e0ec0387aff34cd7ccc3b7efd786975',NULL,NULL,'2026-04-18 03:17:19','2026-04-19 06:44:15','2026-04-19 06:44:15'),(58,3,'YRL5G6VV30','6552dfe545e0bd1bfe2e770cfb6d67443c328caaee8828ca54fe7e4d3eee4a9a',NULL,NULL,'2026-04-18 04:00:38','2026-04-18 05:34:54','2026-04-18 05:34:54'),(59,3,'YRL5G6VV30','a44404fa34d372fadc275c1e4aad297b3fa203145e75cb29099894724b9033e7',NULL,NULL,'2026-04-18 05:35:27','2026-04-18 11:25:32','2026-04-18 11:25:32'),(60,1,NULL,'b91b3844fabed5ebe6112703fab7d529aced8877301e1f7d7fb2bcf4144616b5',NULL,NULL,'2026-04-18 07:30:24','2026-04-19 06:42:54','2026-04-19 07:12:54'),(61,3,'YRL5G6VV30','31a1359ebe2e9a65eaa57693967fdec6a1d651b943d2d72084393bd10f2df7ca',NULL,NULL,'2026-04-18 11:25:57','2026-04-18 14:29:27','2026-04-18 14:29:27'),(62,1,NULL,'067a5bcb174f3134e99dbf545aed01309f1c6728f3c086f35bddee22e2ac17fb',NULL,NULL,'2026-04-18 14:27:17','2026-04-18 14:27:29','2026-04-18 14:27:29'),(63,3,'YRL5G6VV30','5404d27d0f13dba36063560ce6854a4aa0b3224b7dbb89b9839ecd23cb1fd0c2',NULL,NULL,'2026-04-18 14:29:51','2026-04-18 14:29:58','2026-04-18 14:59:58'),(64,3,'YRL5G6VV30','f250ccc2442ff58417d54498ed30dc6040626f85cd802538dc0ad72f2c2009a2',NULL,NULL,'2026-04-18 14:30:45','2026-04-18 22:46:02','2026-04-18 22:46:02'),(65,1,NULL,'69647e91841d910ba5b673bf811aa9f7b71b6f0bc85f9835659eefae684b8b1a',NULL,NULL,'2026-04-18 14:44:21','2026-04-18 15:50:05','2026-04-18 16:20:05'),(66,3,'YRL5G6VV30','96e8a29299aec3d8276d176a15938dfa5eb22ea65e5076b81468188b94e953ec',NULL,NULL,'2026-04-18 15:51:21','2026-04-18 15:51:44','2026-04-18 15:51:44'),(67,3,'YRL5G6VV30','04ab97417fe28c7adca89a4c8c2890f3742295430e2b373ebe345f9c58dc92ee',NULL,NULL,'2026-04-18 22:46:24','2026-04-18 23:56:22','2026-04-19 00:26:22'),(68,3,'YRL5G6VV30','b78769d7406a44e3fa4065d6125d843db4e5123656528b5031291e62d1e6417e',NULL,NULL,'2026-04-19 00:31:16','2026-04-19 01:15:33','2026-04-19 01:15:33'),(69,3,'YRL5G6VV30','5c067ef4dc10bbf5341976087c84b989027d637a1b6fec81069c2165c3828b0a',NULL,NULL,'2026-04-19 01:15:47','2026-04-19 04:47:17','2026-04-19 04:47:17'),(70,4,'YRL5G6VV30','6e1064697bcca88433a8b621ec33ec95340c4993936bee4c7e8685c9f4d64557',NULL,NULL,'2026-04-19 04:39:15','2026-04-19 05:08:17','2026-04-19 05:38:17'),(71,3,'YRL5G6VV30','429dee7f0cf1a893525157f62c5576d8d2cb69c0464d2638f14162be92cacc8b',NULL,NULL,'2026-04-19 04:47:41','2026-04-19 06:44:40','2026-04-19 06:44:40'),(72,3,'YRL5G6VV30','291c1f552a85d9dd3e62b08e20f1d1912ca43d82a5c8b299af01ebc695646e3a',NULL,NULL,'2026-04-19 06:44:22','2026-04-19 08:57:01','2026-04-19 08:57:01'),(73,4,'YRL5G6VV30','346d6b8ac550e04737f8b45aacd3ffadadbd2c79e6bba753c0a994fa5306af22',NULL,NULL,'2026-04-19 06:44:50','2026-04-19 06:45:16','2026-04-19 06:45:16'),(74,4,'YRL5G6VV30','ff961343e3306ebae47cd884fad8d215fc9f26dcf59436df200ffc2d994bd9ab',NULL,NULL,'2026-04-19 06:45:24','2026-04-19 12:45:54','2026-04-19 12:45:54'),(75,1,NULL,'1285a5a4747f106898260b24190b6027ec68c982bd11037d43650be80476b619',NULL,NULL,'2026-04-19 07:35:31','2026-04-19 07:35:49','2026-04-19 07:35:49'),(76,6,'JRE0JW6ZIX','59e5e08cb11e519de7fccd89eed76673fdd824f515021817b88971f276dda525',NULL,NULL,'2026-04-19 07:35:55','2026-04-19 07:36:53','2026-04-19 07:36:53'),(77,3,'YRL5G6VV30','c9f25f9d7d51a1aef3dff5c868dcabd6492d3b71942a95a55aa59cc01c99347a',NULL,NULL,'2026-04-19 08:57:25','2026-04-19 10:56:30','2026-04-19 10:56:30'),(78,3,'YRL5G6VV30','a6a68ac399de225641802860e9213bf018643f62bd62edb07f37300ae712b8c6',NULL,NULL,'2026-04-19 10:56:52','2026-04-19 17:07:54','2026-04-19 17:07:54'),(79,4,'YRL5G6VV30','bfb7f88c6d5c6f9672bdd2d1ff3c2e103e59b1f1b5a2727d6872cc67e723c977',NULL,NULL,'2026-04-19 12:46:17','2026-04-19 13:49:39','2026-04-19 13:49:39'),(80,3,'YRL5G6VV30','801cd171d32b1945b5668181bc53d17015af0d659b31682282ae1fad5bdbbaf3',NULL,NULL,'2026-04-19 13:49:52','2026-04-19 13:50:00','2026-04-19 13:50:00'),(81,4,'YRL5G6VV30','07e5f9cf7ba5ada08dc9b2610f85c0f003cb778fd56ef51a9c10181a34f1c34a',NULL,NULL,'2026-04-19 13:50:24','2026-04-19 17:42:36','2026-04-19 18:12:36'),(82,1,NULL,'dfa3e102e3c15b4ec9527a2d3c48b45b33e5d728de8a3bf9b9e2ae867295983d',NULL,NULL,'2026-04-19 14:17:25','2026-04-19 16:43:57','2026-04-19 16:43:57'),(83,3,'YRL5G6VV30','56b7e5fdda38c375d336056313ac3d535acbc3071ce798168217c701e8a038a0',NULL,NULL,'2026-04-19 17:08:00','2026-04-19 17:42:49','2026-04-19 17:42:49'),(84,3,'YRL5G6VV30','1b730f6109d4cf8238695ddb8d60e8fbf2e5f4df8ca9486387e9e696110fe8f9',NULL,NULL,'2026-04-19 17:52:38','2026-04-19 17:52:38','2026-04-19 18:22:38');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'rhob','gleomir28@gmail.com','+639633569485','$2y$10$WAX5NHe0AGODm6k2skpJT.DrLQHrvwsmEofIb8LBN93x/gtuVYv5a',0,'Bhor','Rivera',NULL,NULL,'2005-12-20',0,1,'Super Admin','Active',0,NULL,NULL,NULL,'2026-04-19 14:17:25',0,0,NULL,NULL,'dark',NULL,'2026-04-08 03:45:26','2026-04-19 14:36:39'),(2,NULL,'gleo','gleomir2005@gmail.com','0987654321','$2y$10$Dl3dzoXijugy69BsrZH9n.LEhfsYPXD7tsoZeFWWB0FX0iD1Q88Fi',0,'Rhob','Gleo',NULL,NULL,'2026-04-08',0,1,'Super Admin','Active',0,NULL,NULL,NULL,'2026-04-08 03:47:52',0,0,NULL,NULL,'light',NULL,'2026-04-08 03:46:35','2026-04-08 03:48:09'),(3,'YRL5G6VV30','batman','rhobrivera20@gmail.com','0987654321','$2y$10$iOF/B2WQxfPFSOn7QCzFDeVeZW9oz8vgOWdwv6qWhxQz1w0URGoWW',0,'Bruce','Wayne',NULL,NULL,'2026-04-08',0,2,'Admin','Active',1,NULL,NULL,NULL,'2026-04-19 17:52:38',0,0,NULL,NULL,'dark',NULL,'2026-04-08 03:49:15','2026-04-19 17:52:38'),(4,'YRL5G6VV30','orekihoutarou123456789','orekihoutarou123456789@gmail.com',NULL,'$2y$10$/BFaNcRmBH6stGrH3ipTg.H5cY54lDoF5YzXW54g/FZIPD8IDmCxu',0,NULL,NULL,NULL,NULL,NULL,0,3,'Employee','Active',0,NULL,NULL,NULL,'2026-04-19 13:50:24',0,0,NULL,NULL,'dark',NULL,'2026-04-08 03:51:05','2026-04-19 13:50:24'),(5,'YRL5G6VV30','lolololol2991882','lolololol2991882@gmail.com',NULL,'$2y$10$TRVTFAy1YqC2ehzINQ.Qxe3Bzg925ZIjOj9WsqL6C9DnFo6NYZFGG',0,NULL,NULL,NULL,NULL,NULL,1,3,'Employee','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-08 03:52:36','2026-04-08 03:52:36'),(6,'JRE0JW6ZIX','ping','microfin.otp@gmail.com','0987654532','$2y$10$1pdO5ED7EsD9bP359T.ymO.hA3UIrlf2LouCneOINa/7y4GU2zGzm',0,'Xi Jin','Ping',NULL,NULL,'2026-04-09',0,4,'Admin','Active',1,NULL,NULL,NULL,'2026-04-19 07:36:53',0,0,NULL,NULL,'dark',NULL,'2026-04-09 09:09:26','2026-04-19 07:36:53'),(7,'YRL5G6VV30','flash','gleomir28@gmail.com',NULL,'$argon2id$v=19$m=65536,t=4,p=1$UUhuS0FkR0JyYVJ2NlhVNA$Gg/KiUOTwrU0dbKpTdj3BK9g+PMA3dqRv+fHKv9PdlY',1,'Xijinn','Pingg','','',NULL,0,5,'Client','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-09 11:05:31','2026-04-18 09:28:35'),(8,'YRL5G6VV30','ulollmooo','earlkaizer10@gmail.com','09876452','$argon2id$v=19$m=65536,t=4,p=1$T3hsV3BzTXY3bm4zWnFmUA$1uDiHWQlAdiWic3aHkIiOoBLIucXGFMz/3AS3m7SZpE',1,'Tangina','bruh','','','1982-04-15',0,5,'Client','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-09 12:21:55','2026-04-19 11:02:05'),(9,'UHKYHQD9YW','allen2','8heikin8@gmail.com','0987456321','$2y$10$k4Wajl90ojMmyU.TNAs2GOEQx5X.cCq5fVtj.975/Y5QpDi7d.Kku',0,'Barry','Allen',NULL,NULL,NULL,1,6,'Admin','Active',1,NULL,NULL,NULL,'2026-04-18 02:07:42',0,0,NULL,NULL,'light',NULL,'2026-04-18 02:04:15','2026-04-18 02:07:42'),(10,'JRE0JW6ZIX','rhobrivera20','rhobrivera20@gmail.com',NULL,'$2y$10$aYmexZ94vpikGYn5KCHKpuf17o29hbJNUu0GBJ2RwerG/zRFzIUgq',0,NULL,NULL,NULL,NULL,NULL,1,7,'Employee','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-19 07:36:42','2026-04-19 07:36:42'),(16,'YRL5G6VV30','microfinotp9453','microfin.otp@gmail.com',NULL,'$argon2id$v=19$m=65536,t=4,p=1$QlF3b2ZlaUlTS1R2N29vNw$JHWLc3tPit1zW0WeGcN+VYsn1xJPyHdc3IZNUJJ8NBI',0,'Rhob Gleomir','Rivera',NULL,NULL,NULL,1,3,'Employee','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-19 09:48:07','2026-04-19 09:48:07'),(18,'YRL5G6VV30','pangalanann','janaryhaileysabas048@gmail.com','0987654321','$argon2id$v=19$m=65536,t=4,p=1$dHFNR2VaanF0aS44MEFlUw$i+Fugb7k4yx5peceHHUnrfVsybj58BG4aLndEGhUdb8',1,'pangalan','e','nananaamn naayos nato','','2006-04-25',0,5,'Client','Active',0,NULL,NULL,NULL,NULL,0,0,NULL,NULL,'light',NULL,'2026-04-19 17:22:00','2026-04-19 17:30:52');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-20  1:55:32
