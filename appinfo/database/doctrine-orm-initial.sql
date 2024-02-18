-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 04, 2023 at 09:31 AM
-- Server version: 10.6.11-MariaDB-log
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cafevdb_musicians_insurances_25`
--

DELIMITER $$
--
-- Functions
--
CREATE FUNCTION `BIN2UUID` (`b` BINARY(16)) RETURNS CHAR(36) CHARSET ascii DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN
  RETURN BIN_TO_UUID(b, 0);
END$$

CREATE FUNCTION `BIN_TO_UUID` (`b` BINARY(16), `f` BOOLEAN) RETURNS CHAR(36) CHARSET ascii DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN
DECLARE hexStr CHAR(32);
SET hexStr = HEX(b);
RETURN LOWER(CONCAT(
        IF(f,SUBSTR(hexStr, 9, 8),SUBSTR(hexStr, 1, 8)), '-',
        IF(f,SUBSTR(hexStr, 5, 4),SUBSTR(hexStr, 9, 4)), '-',
        IF(f,SUBSTR(hexStr, 1, 4),SUBSTR(hexStr, 13, 4)), '-',
        SUBSTR(hexStr, 17, 4), '-',
        SUBSTR(hexStr, 21)
    ));
END$$

CREATE FUNCTION `UUID2BIN` (`uuid` CHAR(36)) RETURNS BINARY(16) DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN
  RETURN UUID_TO_BIN(uuid, 0);
END$$

CREATE FUNCTION `UUID_TO_BIN` (`uuid` CHAR(36), `f` BOOLEAN) RETURNS BINARY(16) DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN
  RETURN UNHEX(CONCAT(
  IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
  SUBSTRING(uuid, 10, 4),
  IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
  SUBSTRING(uuid, 20, 4),
  SUBSTRING(uuid, 25))
  );
END$$

CREATE FUNCTION `MUSICIAN_USER_ID` () RETURNS VARCHAR(256) CHARSET ascii DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN
  RETURN COALESCE(@CLOUD_USER_ID, SUBSTRING_INDEX(USER(), '@', 1));
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ChangeLog`
--

CREATE TABLE IF NOT EXISTS `ChangeLog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `updated` datetime(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `operation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tab` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rowkey` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `col` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oldval` blob DEFAULT NULL,
  `newval` blob DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CompositePayments`
--

CREATE TABLE IF NOT EXISTS `CompositePayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sepa_transaction_id` int(11) DEFAULT NULL,
  `musician_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `bank_account_sequence` int(11) DEFAULT NULL,
  `debit_mandate_sequence` int(11) DEFAULT NULL,
  `amount` decimal(7,2) NOT NULL DEFAULT 0.00,
  `date_of_receipt` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  `subject` varchar(1024) NOT NULL,
  `notification_message_id` varchar(512) DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `supporting_document_id` int(11) DEFAULT NULL,
  `balance_documents_folder_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_65D9920C2423759C` (`supporting_document_id`),
  UNIQUE KEY `UNIQ_65D9920CA808B60B` (`notification_message_id`),
  KEY `IDX_65D9920CD5560045` (`sepa_transaction_id`),
  KEY `IDX_65D9920C9523AA8A2301E184` (`musician_id`,`bank_account_sequence`),
  KEY `IDX_65D9920C9523AA8A544C02F9` (`musician_id`,`debit_mandate_sequence`),
  KEY `IDX_65D9920C9523AA8A` (`musician_id`),
  KEY `IDX_65D9920C166D1F9C` (`project_id`),
  KEY `IDX_65D9920C166D1F9C9523AA8A` (`project_id`,`musician_id`),
  KEY `IDX_65D9920C8A034ED2` (`balance_documents_folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `DatabaseStorageDirEntries`
--

CREATE TABLE IF NOT EXISTS `DatabaseStorageDirEntries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `file_id` int(11) DEFAULT NULL,
  `name` varchar(256) NOT NULL,
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `type` enum('generic','folder','file') NOT NULL COMMENT 'enum(generic,folder,file)(DC2Type:EnumDirEntryType)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_E123333D727ACA705E237E06` (`parent_id`,`name`),
  KEY `IDX_E123333D727ACA70` (`parent_id`),
  KEY `IDX_E123333D93CB796C` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `DatabaseStorages`
--

CREATE TABLE IF NOT EXISTS `DatabaseStorages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storage_id` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `root_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3594ED235CC5DB90` (`storage_id`),
  UNIQUE KEY `UNIQ_3594ED2379066886` (`root_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `EmailAttachments`
--

CREATE TABLE IF NOT EXISTS `EmailAttachments` (
  `file_name` varchar(512) NOT NULL,
  `draft_id` int(11) DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`file_name`),
  KEY `IDX_199F0CDBE2F3C5D1` (`draft_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `EmailDrafts`
--

CREATE TABLE IF NOT EXISTS `EmailDrafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(256) NOT NULL,
  `data` longtext NOT NULL COMMENT 'Message Data Without Attachments(DC2Type:json)',
  `auto_generated` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created_by` varchar(255) DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `EmailTemplates`
--

CREATE TABLE IF NOT EXISTS `EmailTemplates` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Tag` varchar(128) NOT NULL,
  `Subject` varchar(1024) NOT NULL,
  `Contents` longtext DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created_by` varchar(255) DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `UNIQ_51BDDDC389B783` (`Tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `EncryptedFileOwners`
--

CREATE TABLE IF NOT EXISTS `EncryptedFileOwners` (
  `musician_id` int(11) NOT NULL,
  `encrypted_file_id` int(11) NOT NULL,
  PRIMARY KEY (`musician_id`,`encrypted_file_id`),
  KEY `IDX_5697DE239523AA8A` (`musician_id`),
  KEY `IDX_5697DE23EC15E76C` (`encrypted_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ExtLogEntries`
--

CREATE TABLE IF NOT EXISTS `ExtLogEntries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` varchar(573) DEFAULT NULL,
  `remote_address` varchar(45) DEFAULT NULL,
  `action` varchar(8) NOT NULL,
  `logged_at` datetime(6) NOT NULL,
  `object_class` varchar(191) NOT NULL,
  `version` int(11) NOT NULL,
  `data` longtext DEFAULT NULL COMMENT '(DC2Type:array)',
  `username` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `log_class_lookup_idx` (`object_class`),
  KEY `log_date_lookup_idx` (`logged_at`),
  KEY `log_user_lookup_idx` (`username`),
  KEY `log_version_lookup_idx` (`object_id`,`object_class`,`version`),
  KEY `log_action_lookup_idx` (`action`,`object_class`),
  KEY `log_action_class_lookup_idx` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `FileData`
--

CREATE TABLE IF NOT EXISTS `FileData` (
  `file_id` int(11) NOT NULL,
  `data_hash` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `data` longblob NOT NULL,
  `type` enum('generic','image','encrypted') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'enum(generic,image,encrypted)(DC2Type:EnumFileType)',
  PRIMARY KEY (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Files`
--

CREATE TABLE IF NOT EXISTS `Files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(512) DEFAULT NULL,
  `mime_type` varchar(128) NOT NULL,
  `size` int(11) NOT NULL DEFAULT -1,
  `data_hash` char(32) DEFAULT NULL,
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `type` enum('generic','image','encrypted') NOT NULL COMMENT 'enum(generic,image,encrypted)(DC2Type:EnumFileType)',
  `width` int(11) DEFAULT -1,
  `height` int(11) DEFAULT -1,
  PRIMARY KEY (`id`),
  KEY `IDX_C7F46F5DD7DF1668` (`file_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoContinents`
--

CREATE TABLE IF NOT EXISTS `GeoContinents` (
  `code` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `l10n_name` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`code`,`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoCountries`
--

CREATE TABLE IF NOT EXISTS `GeoCountries` (
  `iso` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `l10n_name` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `continent_code` char(2) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`iso`,`target`),
  KEY `IDX_7DF803716C569B466F2FFC` (`continent_code`,`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoPostalCodes`
--

CREATE TABLE IF NOT EXISTS `GeoPostalCodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postal_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(650) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `country` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `state_province` char(3) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_B50ACD455373C966EA98E3765E237E06` (`country`,`postal_code`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoPostalCodeTranslations`
--

CREATE TABLE IF NOT EXISTS `GeoPostalCodeTranslations` (
  `target` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `geo_postal_code_id` int(11) NOT NULL,
  `translation` varchar(1024) NOT NULL,
  PRIMARY KEY (`geo_postal_code_id`,`target`),
  KEY `IDX_BC664719E70E684F` (`geo_postal_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoStatesProvinces`
--

CREATE TABLE IF NOT EXISTS `GeoStatesProvinces` (
  `country_iso` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `code` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `l10n_name` varchar(1024) NOT NULL,
  PRIMARY KEY (`country_iso`,`code`,`target`),
  KEY `IDX_40C5B1885A7049D0466F2FFC` (`country_iso`,`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `InstrumentFamilies`
--

CREATE TABLE IF NOT EXISTS `InstrumentFamilies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `family` varchar(255) NOT NULL,
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_31147B76A5E6215B` (`family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `InstrumentInsurances`
--

CREATE TABLE IF NOT EXISTS `InstrumentInsurances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrument_holder_id` int(11) NOT NULL,
  `bill_to_party_id` int(11) NOT NULL,
  `broker_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `geographical_scope` enum('Domestic','Continent','Germany','Europe','World') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'enum(Domestic,Continent,Germany,Europe,World)(DC2Type:EnumGeographicalScope)',
  `object` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `accessory` tinyint(1) DEFAULT 0,
  `manufacturer` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `year_of_construction` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `insurance_amount` int(11) NOT NULL,
  `start_of_insurance` date NOT NULL COMMENT '(DC2Type:date_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `instrument_owner_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B9BA7EF9D7A36FA` (`bill_to_party_id`),
  KEY `IDX_B9BA7EF6CC064FCBD069886` (`broker_id`,`geographical_scope`),
  KEY `IDX_B9BA7EFA948FBE6` (`instrument_holder_id`),
  KEY `IDX_B9BA7EFDF95C1F8` (`instrument_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Instruments`
--

CREATE TABLE IF NOT EXISTS `Instruments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int(11) NOT NULL COMMENT 'Orchestral Ordering',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `instrument_instrument_family`
--

CREATE TABLE IF NOT EXISTS `instrument_instrument_family` (
  `instrument_id` int(11) NOT NULL,
  `instrument_family_id` int(11) NOT NULL,
  PRIMARY KEY (`instrument_id`,`instrument_family_id`),
  KEY `IDX_2C15852ACF11D9C` (`instrument_id`),
  KEY `IDX_2C15852AB4F8CF5C` (`instrument_family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `InsuranceBrokers`
--

CREATE TABLE IF NOT EXISTS `InsuranceBrokers` (
  `short_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `long_name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Address` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`short_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `InsuranceRates`
--

CREATE TABLE IF NOT EXISTS `InsuranceRates` (
  `broker_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `geographical_scope` enum('Domestic','Continent','Germany','Europe','World') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Germany' COMMENT 'enum(Domestic,Continent,Germany,Europe,World)(DC2Type:EnumGeographicalScope)',
  `Rate` double NOT NULL COMMENT 'fraction, not percentage, excluding taxes',
  `due_date` date DEFAULT NULL COMMENT 'start of the yearly insurance period(DC2Type:date_immutable)',
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`broker_id`,`geographical_scope`),
  KEY `IDX_CB75C3526CC064FC` (`broker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Migrations`
--

CREATE TABLE IF NOT EXISTS `Migrations` (
  `version` char(14) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `migration_class_name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MissingTranslations`
--

CREATE TABLE IF NOT EXISTS `MissingTranslations` (
  `locale` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_key_id` int(11) NOT NULL,
  PRIMARY KEY (`translation_key_id`,`locale`),
  KEY `IDX_DBBA64EAD07ED992` (`translation_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MusicianEmailAddresses`
--

CREATE TABLE IF NOT EXISTS `MusicianEmailAddresses` (
  `address` varchar(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `musician_id` int(11) NOT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`address`,`musician_id`),
  KEY `IDX_13DF84F69523AA8A` (`musician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MusicianInstruments`
--

CREATE TABLE IF NOT EXISTS `MusicianInstruments` (
  `musician_id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `ranking` int(11) NOT NULL DEFAULT 1 COMMENT 'Ranking of the instrument w.r.t. to the given musician (lower is better)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`musician_id`,`instrument_id`),
  KEY `IDX_332855779523AA8A` (`musician_id`),
  KEY `IDX_33285577CF11D9C` (`instrument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Pivot-table, instruments a musician plays.';

-- --------------------------------------------------------

--
-- Table structure for table `MusicianPhoto`
--

CREATE TABLE IF NOT EXISTS `MusicianPhoto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `image_id` int(11) NOT NULL,
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_A52F29707E3C61F9` (`owner_id`),
  UNIQUE KEY `UNIQ_A52F29703DA5256D` (`image_id`),
  UNIQUE KEY `UNIQ_A52F29707E3C61F93DA5256D` (`owner_id`,`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MusicianRowAccessTokens`
--

CREATE TABLE IF NOT EXISTS `MusicianRowAccessTokens` (
  `musician_id` int(11) NOT NULL,
  `user_id` varchar(256) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `access_token_hash` char(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`musician_id`),
  UNIQUE KEY `UNIQ_64C47A569982CF5B` (`access_token_hash`),
  UNIQUE KEY `UNIQ_64C47A56A76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Musicians`
--

CREATE TABLE IF NOT EXISTS `Musicians` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sur_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nick_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address_supplement` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `street` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `street_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `display_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `city` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` char(2) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `language` char(2) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `mobile_phone` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fixed_line_phone` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  `email` varchar(254) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `member_status` enum('regular','passive','soloist','conductor','temporary') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'regular' COMMENT 'passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from "per-project" email unless explicitly selected. enum(regular,passive,soloist,conductor,temporary)(DC2Type:EnumMemberStatus)',
  `remarks` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uuid` binary(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `user_id_slug` varchar(256) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `user_passphrase` varchar(256) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `cloud_account_deactivated` tinyint(1) DEFAULT NULL,
  `cloud_account_disabled` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3CC48982D17F50A6` (`uuid`),
  UNIQUE KEY `UNIQ_3CC489824BB0996A` (`user_id_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ParticipantReceivablesCache`
--

CREATE TABLE IF NOT EXISTS `ParticipantReceivablesCache` (
  `project_id` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `amount_invoiced` decimal(7,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(7,2) NOT NULL DEFAULT 0.00,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`project_id`,`musician_id`),
  UNIQUE KEY `projectParticipant_uniq` (`project_id`,`musician_id`),
  KEY `IDX_4435E519166D1F9C` (`project_id`),
  KEY `IDX_4435E5199523AA8A` (`musician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectEvents`
--

CREATE TABLE IF NOT EXISTS `ProjectEvents` (
  `project_id` int(11) NOT NULL,
  `calendar_id` int(11) NOT NULL,
  `calendar_uri` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `event_uri` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('VEVENT','VTODO','VJOURNAL','VCARD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'enum(VEVENT,VTODO,VJOURNAL,VCARD)(DC2Type:EnumVCalendarType)',
  `event_uid` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`project_id`,`event_uri`),
  UNIQUE KEY `UNIQ_7E38FC8B166D1F9C4254C3D5` (`project_id`,`event_uid`),
  KEY `IDX_7E38FC8B166D1F9C` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectEventsAdjustUri`
--

CREATE TABLE IF NOT EXISTS `ProjectEventsAdjustUri` (
  `project_id` int(11) NOT NULL,
  `calendar_id` int(11) NOT NULL,
  `calendar_uri` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `event_uri` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('VEVENT','VTODO','VJOURNAL','VCARD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'enum(VEVENT,VTODO,VJOURNAL,VCARD)(DC2Type:EnumVCalendarType)',
  `event_uid` varchar(764) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`project_id`,`event_uri`),
  UNIQUE KEY `UNIQ_7E38FC8B166D1F9C4254C3D5` (`project_id`,`event_uid`),
  KEY `IDX_7E38FC8B166D1F9C` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectInstrumentationNumbers`
--

CREATE TABLE IF NOT EXISTS `ProjectInstrumentationNumbers` (
  `project_id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `voice` int(11) NOT NULL DEFAULT 0 COMMENT 'Voice specification if applicable, set to 0 if separation by voice is not needed',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of required musicians for this instrument',
  PRIMARY KEY (`project_id`,`instrument_id`,`voice`),
  KEY `IDX_D8939186166D1F9C` (`project_id`),
  KEY `IDX_D8939186CF11D9C` (`instrument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectInstruments`
--

CREATE TABLE IF NOT EXISTS `ProjectInstruments` (
  `project_id` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `voice` int(11) NOT NULL DEFAULT 0 COMMENT 'Voice specification if applicable, set to 0 if separation by voice is not needed',
  `section_leader` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`project_id`,`musician_id`,`instrument_id`,`voice`),
  KEY `IDX_436762A6166D1F9C` (`project_id`),
  KEY `IDX_436762A69523AA8A` (`musician_id`),
  KEY `IDX_436762A6CF11D9C` (`instrument_id`),
  KEY `IDX_436762A6166D1F9C9523AA8A` (`project_id`,`musician_id`),
  KEY `IDX_436762A69523AA8ACF11D9C` (`musician_id`,`instrument_id`),
  KEY `IDX_436762A6166D1F9CCF11D9CE7FB583B` (`project_id`,`instrument_id`,`voice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Pivot-table which connects a given musician to the ist of instruments he or she plays in the given project (maybe multiple instruments)';

-- --------------------------------------------------------

--
-- Table structure for table `ProjectParticipantFields`
--

CREATE TABLE IF NOT EXISTS `ProjectParticipantFields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `data_type` enum('text','html','boolean','integer','float','date','datetime','service-fee','cloud-file','cloud-folder','db-file') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'text' COMMENT 'enum(text,html,boolean,integer,float,date,datetime,service-fee,cloud-file,cloud-folder,db-file)(DC2Type:EnumParticipantFieldDataType)',
  `multiplicity` enum('simple','single','multiple','parallel','recurring','groupofpeople','groupsofpeople') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'enum(simple,single,multiple,parallel,recurring,groupofpeople,groupsofpeople)(DC2Type:EnumParticipantFieldMultiplicity)',
  `display_order` int(11) DEFAULT NULL,
  `tooltip` varchar(4096) DEFAULT NULL,
  `tab` varchar(256) DEFAULT NULL COMMENT 'Tab to display the field in. If empty, then the project tab is used.',
  `encrypted` tinyint(1) DEFAULT 0,
  `readers` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'If non-empty restrict the visbility to this comma separated list of user-groups.',
  `writers` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Empty or comma separated list of groups allowed to change the field.',
  `default_value` binary(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary)',
  `due_date` date DEFAULT NULL COMMENT 'Due-date for financial fields.(DC2Type:date_immutable)',
  `deposit_due_date` date DEFAULT NULL COMMENT 'Due-date of deposit for financial fields.(DC2Type:date_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `participant_access` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `defaultValue_uniq` (`id`,`default_value`),
  KEY `IDX_F6F5D9C6166D1F9C` (`project_id`),
  KEY `IDX_F6F5D9C6BF396750166D1F9C` (`id`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectParticipantFieldsData`
--

CREATE TABLE IF NOT EXISTS `ProjectParticipantFieldsData` (
  `field_id` int(11) NOT NULL,
  `option_key` binary(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
  `project_id` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `supporting_document_id` int(11) DEFAULT NULL,
  `option_value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deposit` double DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`field_id`,`project_id`,`musician_id`,`option_key`),
  UNIQUE KEY `UNIQ_E1AAA1E92423759C` (`supporting_document_id`),
  KEY `IDX_E1AAA1E9443707B0` (`field_id`),
  KEY `IDX_E1AAA1E9166D1F9C` (`project_id`),
  KEY `IDX_E1AAA1E99523AA8A` (`musician_id`),
  KEY `IDX_E1AAA1E9443707B03CEE7BEE` (`field_id`,`option_key`),
  KEY `IDX_E1AAA1E9166D1F9C9523AA8A` (`project_id`,`musician_id`),
  KEY `IDX_E1AAA1E9443707B0166D1F9C` (`field_id`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectParticipantFieldsDataOptions`
--

CREATE TABLE IF NOT EXISTS `ProjectParticipantFieldsDataOptions` (
  `field_id` int(11) NOT NULL,
  `key` binary(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
  `label` varchar(128) DEFAULT NULL,
  `data` varchar(1024) DEFAULT NULL,
  `deposit` double DEFAULT NULL,
  `limit` bigint(20) DEFAULT NULL,
  `tooltip` varchar(4096) DEFAULT NULL,
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`field_id`,`key`),
  KEY `IDX_FA443FE443707B0` (`field_id`),
  KEY `IDX_FA443FE8A90ABA9` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectParticipants`
--

CREATE TABLE IF NOT EXISTS `ProjectParticipants` (
  `project_id` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `registration` tinyint(1) DEFAULT 0 COMMENT 'Participant has confirmed the registration.',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `bank_account_sequence` int(11) DEFAULT NULL,
  `debit_mandate_sequence` int(11) DEFAULT NULL,
  `database_documents_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`project_id`,`musician_id`),
  UNIQUE KEY `UNIQ_D9AE987BC6073910` (`database_documents_id`),
  KEY `IDX_D9AE987B166D1F9C` (`project_id`),
  KEY `IDX_D9AE987B9523AA8A` (`musician_id`),
  KEY `IDX_D9AE987B9523AA8A2301E184` (`musician_id`,`bank_account_sequence`),
  KEY `IDX_D9AE987B9523AA8A544C02F9` (`musician_id`,`debit_mandate_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectPayments`
--

CREATE TABLE IF NOT EXISTS `ProjectPayments` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(7,2) NOT NULL DEFAULT 0.00,
  `subject` varchar(1024) NOT NULL,
  `project_id` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `receivable_key` binary(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
  `composite_payment_id` int(11) NOT NULL,
  `balance_documents_folder_id` int(11) DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`Id`),
  KEY `IDX_F6372AE2166D1F9C9523AA8A` (`project_id`,`musician_id`),
  KEY `IDX_F6372AE2443707B0166D1F9C9523AA8AD151D1BF` (`field_id`,`project_id`,`musician_id`,`receivable_key`),
  KEY `IDX_F6372AE2443707B0D151D1BF` (`field_id`,`receivable_key`),
  KEY `IDX_F6372AE2930D2644` (`composite_payment_id`),
  KEY `IDX_F6372AE2166D1F9C` (`project_id`),
  KEY `IDX_F6372AE29523AA8A` (`musician_id`),
  KEY `IDX_F6372AE28A034ED2` (`balance_documents_folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Projects`
--

CREATE TABLE IF NOT EXISTS `Projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(10) UNSIGNED NOT NULL,
  `name` varchar(64) NOT NULL,
  `type` enum('temporary','permanent','template') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'temporary' COMMENT 'enum(temporary,permanent,template)(DC2Type:EnumProjectTemporalType)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `mailing_list_id` varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `financial_balance_documents_storage_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_A5E5D1F25E237E06` (`name`),
  UNIQUE KEY `UNIQ_A5E5D1F214CA24B1` (`financial_balance_documents_storage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectWebPages`
--

CREATE TABLE IF NOT EXISTS `ProjectWebPages` (
  `project_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL DEFAULT -1,
  `article_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `category_id` int(11) NOT NULL DEFAULT -1,
  `Priority` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`project_id`,`article_id`),
  UNIQUE KEY `UNIQ_EB77064F166D1F9C7294869C` (`project_id`,`article_id`),
  KEY `IDX_EB77064F166D1F9C` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SentEmails`
--

CREATE TABLE IF NOT EXISTS `SentEmails` (
  `message_id` varchar(256) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `reference_id` varchar(256) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created_by` varchar(255) DEFAULT NULL,
  `bulk_recipients` longtext NOT NULL,
  `bulk_recipients_hash` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `cc` longtext DEFAULT NULL,
  `bcc` longtext DEFAULT NULL,
  `subject` text NOT NULL,
  `subject_hash` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `html_body` longtext NOT NULL,
  `html_body_hash` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`message_id`),
  KEY `IDX_80F49BA01645DEA9` (`reference_id`),
  KEY `IDX_80F49BA0166D1F9C` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SepaBankAccounts`
--

CREATE TABLE IF NOT EXISTS `SepaBankAccounts` (
  `sequence` int(11) NOT NULL,
  `musician_id` int(11) NOT NULL,
  `iban` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `bic` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `blz` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `bank_account_owner` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`musician_id`,`sequence`),
  KEY `IDX_4F1F148B9523AA8A` (`musician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SepaBulkTransactionData`
--

CREATE TABLE IF NOT EXISTS `SepaBulkTransactionData` (
  `sepa_bulk_transaction_id` int(11) NOT NULL,
  `database_storage_file_id` int(11) NOT NULL,
  PRIMARY KEY (`sepa_bulk_transaction_id`,`database_storage_file_id`),
  UNIQUE KEY `UNIQ_1EBA3E5B4D73A4D4` (`database_storage_file_id`),
  KEY `IDX_1EBA3E5BED6D4895` (`sepa_bulk_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SepaBulkTransactions`
--

CREATE TABLE IF NOT EXISTS `SepaBulkTransactions` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `submission_deadline` date NOT NULL COMMENT '(DC2Type:date_immutable)',
  `submit_date` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  `due_date` date NOT NULL COMMENT '(DC2Type:date_immutable)',
  `submission_event_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  `submission_event_uid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  `submission_task_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  `submission_task_uid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  `due_event_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  `due_event_uid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  `pre_notification_event_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  `pre_notification_event_uid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  `pre_notification_task_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  `pre_notification_task_uid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `sepa_transaction` enum('debit_note','bank_transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'enum(debit_note,bank_transfer)(DC2Type:EnumSepaTransaction)',
  `pre_notification_deadline` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SepaDebitMandates`
--

CREATE TABLE IF NOT EXISTS `SepaDebitMandates` (
  `musician_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `bank_account_sequence` int(11) NOT NULL,
  `mandate_reference` varchar(35) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `mandate_date` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  `last_used_date` date DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  `project_id` int(11) NOT NULL,
  `non_recurring` tinyint(1) NOT NULL,
  `written_mandate_id` int(11) DEFAULT NULL,
  `pre_notification_calendar_days` int(11) NOT NULL DEFAULT 14,
  `pre_notification_business_days` int(11) DEFAULT NULL,
  `deleted` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `created` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated` datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`musician_id`,`sequence`),
  UNIQUE KEY `UNIQ_1C50029D0BE4741` (`mandate_reference`),
  UNIQUE KEY `UNIQ_1C500299523AA8A5286D72B166D1F9C` (`musician_id`,`sequence`,`project_id`),
  UNIQUE KEY `UNIQ_1C50029D26EB11F` (`written_mandate_id`),
  KEY `IDX_1C500299523AA8A` (`musician_id`),
  KEY `IDX_1C500299523AA8A2301E184` (`musician_id`,`bank_account_sequence`),
  KEY `IDX_1C50029166D1F9C` (`project_id`),
  KEY `IDX_1C500299523AA8A2301E184166D1F9C` (`musician_id`,`bank_account_sequence`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TableFieldTranslations`
--

CREATE TABLE IF NOT EXISTS `TableFieldTranslations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `locale` varchar(8) NOT NULL,
  `object_class` varchar(191) NOT NULL,
  `field` varchar(32) NOT NULL,
  `foreign_key` varchar(64) NOT NULL,
  `content` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_unique_idx` (`locale`,`object_class`,`field`,`foreign_key`),
  KEY `translations_lookup_idx` (`locale`,`object_class`,`foreign_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationKeys`
--

CREATE TABLE IF NOT EXISTS `TranslationKeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phrase` longtext NOT NULL COMMENT 'Keyword to be translated. Normally the untranslated text in locale en_US, but could be any unique tag',
  `phrase_hash` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_F15EDA495A875D0C` (`phrase_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TranslationLocations`
--

CREATE TABLE IF NOT EXISTS `TranslationLocations` (
  `file` varchar(766) NOT NULL,
  `line` int(11) NOT NULL,
  `translation_key_id` int(11) NOT NULL,
  PRIMARY KEY (`translation_key_id`,`file`,`line`),
  KEY `IDX_F23942BBD07ED992` (`translation_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Translations`
--

CREATE TABLE IF NOT EXISTS `Translations` (
  `locale` char(5) NOT NULL COMMENT 'Locale for translation, .e.g. en_US',
  `translation_key_id` int(11) NOT NULL,
  `translation` varchar(1024) NOT NULL,
  PRIMARY KEY (`translation_key_id`,`locale`),
  KEY `IDX_DE86017FD07ED992` (`translation_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `CompositePayments`
--
ALTER TABLE `CompositePayments`
  ADD CONSTRAINT `FK_65D9920C166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_65D9920C166D1F9C9523AA8A` FOREIGN KEY (`project_id`,`musician_id`) REFERENCES `ProjectParticipants` (`project_id`, `musician_id`),
  ADD CONSTRAINT `FK_65D9920C2423759C` FOREIGN KEY (`supporting_document_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_65D9920C8A034ED2` FOREIGN KEY (`balance_documents_folder_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_65D9920C9523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_65D9920C9523AA8A2301E184` FOREIGN KEY (`musician_id`,`bank_account_sequence`) REFERENCES `SepaBankAccounts` (`musician_id`, `sequence`),
  ADD CONSTRAINT `FK_65D9920C9523AA8A544C02F9` FOREIGN KEY (`musician_id`,`debit_mandate_sequence`) REFERENCES `SepaDebitMandates` (`musician_id`, `sequence`),
  ADD CONSTRAINT `FK_65D9920CD5560045` FOREIGN KEY (`sepa_transaction_id`) REFERENCES `SepaBulkTransactions` (`Id`);

--
-- Constraints for table `DatabaseStorageDirEntries`
--
ALTER TABLE `DatabaseStorageDirEntries`
  ADD CONSTRAINT `FK_E123333D727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_E123333D93CB796C` FOREIGN KEY (`file_id`) REFERENCES `Files` (`id`);

--
-- Constraints for table `DatabaseStorages`
--
ALTER TABLE `DatabaseStorages`
  ADD CONSTRAINT `FK_3594ED2379066886` FOREIGN KEY (`root_id`) REFERENCES `DatabaseStorageDirEntries` (`id`);

--
-- Constraints for table `EmailAttachments`
--
ALTER TABLE `EmailAttachments`
  ADD CONSTRAINT `FK_199F0CDBE2F3C5D1` FOREIGN KEY (`draft_id`) REFERENCES `EmailDrafts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `EncryptedFileOwners`
--
ALTER TABLE `EncryptedFileOwners`
  ADD CONSTRAINT `FK_5697DE239523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_5697DE23EC15E76C` FOREIGN KEY (`encrypted_file_id`) REFERENCES `Files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `FileData`
--
ALTER TABLE `FileData`
  ADD CONSTRAINT `FK_969FA96893CB796C` FOREIGN KEY (`file_id`) REFERENCES `Files` (`id`);

--
-- Constraints for table `GeoCountries`
--
ALTER TABLE `GeoCountries`
  ADD CONSTRAINT `FK_7DF803716C569B466F2FFC` FOREIGN KEY (`continent_code`,`target`) REFERENCES `GeoContinents` (`code`, `target`);

--
-- Constraints for table `GeoPostalCodeTranslations`
--
ALTER TABLE `GeoPostalCodeTranslations`
  ADD CONSTRAINT `FK_BC664719E70E684F` FOREIGN KEY (`geo_postal_code_id`) REFERENCES `GeoPostalCodes` (`id`);

--
-- Constraints for table `GeoStatesProvinces`
--
ALTER TABLE `GeoStatesProvinces`
  ADD CONSTRAINT `FK_40C5B1885A7049D0466F2FFC` FOREIGN KEY (`country_iso`,`target`) REFERENCES `GeoCountries` (`iso`, `target`);

--
-- Constraints for table `InstrumentInsurances`
--
ALTER TABLE `InstrumentInsurances`
  ADD CONSTRAINT `FK_B9BA7EF6CC064FCBD069886` FOREIGN KEY (`broker_id`,`geographical_scope`) REFERENCES `InsuranceRates` (`broker_id`, `geographical_scope`),
  ADD CONSTRAINT `FK_B9BA7EF9D7A36FA` FOREIGN KEY (`bill_to_party_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_B9BA7EFA948FBE6` FOREIGN KEY (`instrument_holder_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_B9BA7EFDF95C1F8` FOREIGN KEY (`instrument_owner_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `instrument_instrument_family`
--
ALTER TABLE `instrument_instrument_family`
  ADD CONSTRAINT `FK_2C15852AB4F8CF5C` FOREIGN KEY (`instrument_family_id`) REFERENCES `InstrumentFamilies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_2C15852ACF11D9C` FOREIGN KEY (`instrument_id`) REFERENCES `Instruments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `InsuranceRates`
--
ALTER TABLE `InsuranceRates`
  ADD CONSTRAINT `FK_CB75C3526CC064FC` FOREIGN KEY (`broker_id`) REFERENCES `InsuranceBrokers` (`short_name`) ON UPDATE CASCADE;

--
-- Constraints for table `MissingTranslations`
--
ALTER TABLE `MissingTranslations`
  ADD CONSTRAINT `FK_DBBA64EAD07ED992` FOREIGN KEY (`translation_key_id`) REFERENCES `TranslationKeys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `MusicianEmailAddresses`
--
ALTER TABLE `MusicianEmailAddresses`
  ADD CONSTRAINT `FK_13DF84F69523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `MusicianInstruments`
--
ALTER TABLE `MusicianInstruments`
  ADD CONSTRAINT `FK_332855779523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_33285577CF11D9C` FOREIGN KEY (`instrument_id`) REFERENCES `Instruments` (`id`);

--
-- Constraints for table `MusicianPhoto`
--
ALTER TABLE `MusicianPhoto`
  ADD CONSTRAINT `FK_A52F29703DA5256D` FOREIGN KEY (`image_id`) REFERENCES `Files` (`id`),
  ADD CONSTRAINT `FK_A52F29707E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `MusicianRowAccessTokens`
--
ALTER TABLE `MusicianRowAccessTokens`
  ADD CONSTRAINT `FK_64C47A569523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `ParticipantReceivablesCache`
--
ALTER TABLE `ParticipantReceivablesCache`
  ADD CONSTRAINT `FK_4435E519166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_4435E519166D1F9C9523AA8A` FOREIGN KEY (`project_id`,`musician_id`) REFERENCES `ProjectParticipants` (`project_id`, `musician_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_4435E5199523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `ProjectEvents`
--
ALTER TABLE `ProjectEvents`
  ADD CONSTRAINT `FK_7E38FC8B166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`);

--
-- Constraints for table `ProjectInstrumentationNumbers`
--
ALTER TABLE `ProjectInstrumentationNumbers`
  ADD CONSTRAINT `FK_D8939186166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_D8939186CF11D9C` FOREIGN KEY (`instrument_id`) REFERENCES `Instruments` (`id`);

--
-- Constraints for table `ProjectInstruments`
--
ALTER TABLE `ProjectInstruments`
  ADD CONSTRAINT `FK_436762A6166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_436762A6166D1F9C9523AA8A` FOREIGN KEY (`project_id`,`musician_id`) REFERENCES `ProjectParticipants` (`project_id`, `musician_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_436762A6166D1F9CCF11D9CE7FB583B` FOREIGN KEY (`project_id`,`instrument_id`,`voice`) REFERENCES `ProjectInstrumentationNumbers` (`project_id`, `instrument_id`, `voice`),
  ADD CONSTRAINT `FK_436762A69523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_436762A69523AA8ACF11D9C` FOREIGN KEY (`musician_id`,`instrument_id`) REFERENCES `MusicianInstruments` (`musician_id`, `instrument_id`),
  ADD CONSTRAINT `FK_436762A6CF11D9C` FOREIGN KEY (`instrument_id`) REFERENCES `Instruments` (`id`);

--
-- Constraints for table `ProjectParticipantFields`
--
ALTER TABLE `ProjectParticipantFields`
  ADD CONSTRAINT `FK_F6F5D9C6166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_F6F5D9C6BF396750F4510C3A` FOREIGN KEY (`id`,`default_value`) REFERENCES `ProjectParticipantFieldsDataOptions` (`field_id`, `key`);

--
-- Constraints for table `ProjectParticipantFieldsData`
--
ALTER TABLE `ProjectParticipantFieldsData`
  ADD CONSTRAINT `FK_E1AAA1E9166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_E1AAA1E9166D1F9C9523AA8A` FOREIGN KEY (`project_id`,`musician_id`) REFERENCES `ProjectParticipants` (`project_id`, `musician_id`),
  ADD CONSTRAINT `FK_E1AAA1E92423759C` FOREIGN KEY (`supporting_document_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_E1AAA1E9443707B0` FOREIGN KEY (`field_id`) REFERENCES `ProjectParticipantFields` (`id`),
  ADD CONSTRAINT `FK_E1AAA1E9443707B03CEE7BEE` FOREIGN KEY (`field_id`,`option_key`) REFERENCES `ProjectParticipantFieldsDataOptions` (`field_id`, `key`),
  ADD CONSTRAINT `FK_E1AAA1E99523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `ProjectParticipantFieldsDataOptions`
--
ALTER TABLE `ProjectParticipantFieldsDataOptions`
  ADD CONSTRAINT `FK_FA443FE443707B0` FOREIGN KEY (`field_id`) REFERENCES `ProjectParticipantFields` (`id`);

--
-- Constraints for table `ProjectParticipants`
--
ALTER TABLE `ProjectParticipants`
  ADD CONSTRAINT `FK_D9AE987B166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_D9AE987B9523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_D9AE987B9523AA8A2301E184` FOREIGN KEY (`musician_id`,`bank_account_sequence`) REFERENCES `SepaBankAccounts` (`musician_id`, `sequence`),
  ADD CONSTRAINT `FK_D9AE987B9523AA8A544C02F9` FOREIGN KEY (`musician_id`,`debit_mandate_sequence`) REFERENCES `SepaDebitMandates` (`musician_id`, `sequence`),
  ADD CONSTRAINT `FK_D9AE987BC6073910` FOREIGN KEY (`database_documents_id`) REFERENCES `DatabaseStorages` (`id`);

--
-- Constraints for table `ProjectPayments`
--
ALTER TABLE `ProjectPayments`
  ADD CONSTRAINT `FK_F6372AE2166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_F6372AE2166D1F9C9523AA8A` FOREIGN KEY (`project_id`,`musician_id`) REFERENCES `ProjectParticipants` (`project_id`, `musician_id`),
  ADD CONSTRAINT `FK_F6372AE2443707B0166D1F9C9523AA8AD151D1BF` FOREIGN KEY (`field_id`,`project_id`,`musician_id`,`receivable_key`) REFERENCES `ProjectParticipantFieldsData` (`field_id`, `project_id`, `musician_id`, `option_key`),
  ADD CONSTRAINT `FK_F6372AE2443707B0D151D1BF` FOREIGN KEY (`field_id`,`receivable_key`) REFERENCES `ProjectParticipantFieldsDataOptions` (`field_id`, `key`),
  ADD CONSTRAINT `FK_F6372AE28A034ED2` FOREIGN KEY (`balance_documents_folder_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_F6372AE2930D2644` FOREIGN KEY (`composite_payment_id`) REFERENCES `CompositePayments` (`id`),
  ADD CONSTRAINT `FK_F6372AE29523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `Projects`
--
ALTER TABLE `Projects`
  ADD CONSTRAINT `FK_A5E5D1F214CA24B1` FOREIGN KEY (`financial_balance_documents_storage_id`) REFERENCES `DatabaseStorages` (`id`);

--
-- Constraints for table `ProjectWebPages`
--
ALTER TABLE `ProjectWebPages`
  ADD CONSTRAINT `FK_EB77064F166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`);

--
-- Constraints for table `SentEmails`
--
ALTER TABLE `SentEmails`
  ADD CONSTRAINT `FK_80F49BA01645DEA9` FOREIGN KEY (`reference_id`) REFERENCES `SentEmails` (`message_id`),
  ADD CONSTRAINT `FK_80F49BA0166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`);

--
-- Constraints for table `SepaBankAccounts`
--
ALTER TABLE `SepaBankAccounts`
  ADD CONSTRAINT `FK_4F1F148B9523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`);

--
-- Constraints for table `SepaBulkTransactionData`
--
ALTER TABLE `SepaBulkTransactionData`
  ADD CONSTRAINT `FK_1EBA3E5B4D73A4D4` FOREIGN KEY (`database_storage_file_id`) REFERENCES `DatabaseStorageDirEntries` (`id`),
  ADD CONSTRAINT `FK_1EBA3E5BED6D4895` FOREIGN KEY (`sepa_bulk_transaction_id`) REFERENCES `SepaBulkTransactions` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `SepaDebitMandates`
--
ALTER TABLE `SepaDebitMandates`
  ADD CONSTRAINT `FK_1C50029166D1F9C` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`id`),
  ADD CONSTRAINT `FK_1C500299523AA8A` FOREIGN KEY (`musician_id`) REFERENCES `Musicians` (`id`),
  ADD CONSTRAINT `FK_1C500299523AA8A2301E184` FOREIGN KEY (`musician_id`,`bank_account_sequence`) REFERENCES `SepaBankAccounts` (`musician_id`, `sequence`),
  ADD CONSTRAINT `FK_1C50029D26EB11F` FOREIGN KEY (`written_mandate_id`) REFERENCES `DatabaseStorageDirEntries` (`id`);

--
-- Constraints for table `TranslationLocations`
--
ALTER TABLE `TranslationLocations`
  ADD CONSTRAINT `FK_F23942BBD07ED992` FOREIGN KEY (`translation_key_id`) REFERENCES `TranslationKeys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `Translations`
--
ALTER TABLE `Translations`
  ADD CONSTRAINT `FK_DE86017FD07ED992` FOREIGN KEY (`translation_key_id`) REFERENCES `TranslationKeys` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
