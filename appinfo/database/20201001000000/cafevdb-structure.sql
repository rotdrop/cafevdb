-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 28, 2020 at 12:22 AM
-- Server version: 10.3.22-MariaDB-1ubuntu1
-- PHP Version: 7.4.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cafevdb_musiker`
--

-- --------------------------------------------------------

--
-- Table structure for table `Besetzungen`
--

CREATE TABLE `Besetzungen` (
  `Id` int(11) NOT NULL,
  `ProjektId` int(11) NOT NULL,
  `MusikerId` int(11) NOT NULL,
  `Anmeldung` tinyint(1) NOT NULL DEFAULT 0,
  `Unkostenbeitrag` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT 'Gagen negativ',
  `Anzahlung` decimal(7,2) NOT NULL DEFAULT 0.00,
  `Lastschrift` tinyint(1) NOT NULL DEFAULT 1,
  `Bemerkungen` text NOT NULL COMMENT 'Allgemeine Bermerkungen',
  `Disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `changelog`
--

CREATE TABLE `changelog` (
  `id` int(11) NOT NULL,
  `updated` timestamp NULL DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `operation` varchar(255) DEFAULT NULL,
  `tab` varchar(255) DEFAULT NULL,
  `rowkey` varchar(255) DEFAULT NULL,
  `col` varchar(255) DEFAULT NULL,
  `oldval` blob DEFAULT NULL,
  `newval` blob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `DebitNoteData`
--

CREATE TABLE `DebitNoteData` (
  `Id` int(11) NOT NULL,
  `DebitNoteId` int(11) NOT NULL,
  `FileName` varchar(1024) CHARACTER SET utf32 COLLATE utf32_unicode_ci NOT NULL,
  `MimeType` varchar(1024) CHARACTER SET ascii NOT NULL,
  `Data` mediumtext CHARACTER SET ascii NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `DebitNotes`
--

CREATE TABLE `DebitNotes` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) NOT NULL,
  `DateIssued` datetime NOT NULL,
  `SubmissionDeadline` date NOT NULL,
  `SubmitDate` date DEFAULT NULL,
  `DueDate` date NOT NULL,
  `Job` varchar(128) CHARACTER SET ascii NOT NULL,
  `SubmissionEvent` int(11) NOT NULL COMMENT 'OwnCloud Calendar Object Id',
  `SubmissionTask` int(11) NOT NULL COMMENT 'OwnCloud Calendar Object Id',
  `DueEvent` int(11) NOT NULL COMMENT 'OwnCloud Calendar Object Id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `EmailAttachments`
--

CREATE TABLE `EmailAttachments` (
  `Id` int(11) NOT NULL,
  `MessageId` int(11) NOT NULL DEFAULT -1,
  `User` varchar(512) NOT NULL,
  `FileName` varchar(512) CHARACTER SET ascii NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `EmailDrafts`
--

CREATE TABLE `EmailDrafts` (
  `Id` int(11) NOT NULL,
  `Subject` varchar(256) NOT NULL,
  `Data` longtext NOT NULL COMMENT 'Message Data Without Attachments',
  `Created` datetime NOT NULL,
  `Updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `EmailTemplates`
--

CREATE TABLE `EmailTemplates` (
  `Id` int(11) NOT NULL,
  `Tag` varchar(128) NOT NULL,
  `Subject` varchar(1024) NOT NULL,
  `Contents` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `GeoContinents`
--

CREATE TABLE `GeoContinents` (
  `Code` varchar(4) CHARACTER SET ascii NOT NULL,
  `en` varchar(1024) NOT NULL,
  `de` varchar(1024) DEFAULT NULL,
  `fr` varchar(180) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `GeoCountries`
--

CREATE TABLE `GeoCountries` (
  `ISO` varchar(2) CHARACTER SET ascii NOT NULL,
  `Continent` varchar(4) CHARACTER SET ascii NOT NULL,
  `NativeName` varchar(180) NOT NULL,
  `en` varchar(180) CHARACTER SET utf8mb4 NOT NULL,
  `de` varchar(180) DEFAULT NULL,
  `fr` varchar(180) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `GeoPostalCodes`
--

CREATE TABLE `GeoPostalCodes` (
  `Country` varchar(4) CHARACTER SET ascii NOT NULL,
  `PostalCode` varchar(32) CHARACTER SET ascii NOT NULL,
  `Name` varchar(180) NOT NULL,
  `en` varchar(180) DEFAULT NULL,
  `fr` varchar(180) DEFAULT NULL,
  `de` varchar(180) DEFAULT NULL,
  `Latitude` int(11) NOT NULL,
  `Longitude` int(11) NOT NULL,
  `Updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ImageData`
--

CREATE TABLE `ImageData` (
  `Id` int(11) NOT NULL,
  `ItemId` int(11) NOT NULL,
  `ItemTable` varchar(128) CHARACTER SET ascii NOT NULL,
  `MimeType` varchar(128) CHARACTER SET ascii DEFAULT NULL,
  `MD5` char(32) CHARACTER SET ascii DEFAULT NULL,
  `Data` longtext CHARACTER SET ascii DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `Instrumente`
--

CREATE TABLE `Instrumente` (
  `Id` int(11) NOT NULL,
  `Instrument` varchar(64) NOT NULL,
  `Familie` set('Streich','Saiten','Zupf','Blas','Holz','Blech','Schlag','Tasten','Sonstiges') NOT NULL DEFAULT 'Sonstiges',
  `Sortierung` smallint(6) NOT NULL COMMENT 'Orchestersortierung',
  `Disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `InstrumentInsurance`
--

CREATE TABLE `InstrumentInsurance` (
  `Id` int(11) NOT NULL,
  `MusicianId` int(11) NOT NULL,
  `Broker` varchar(128) NOT NULL,
  `GeographicalScope` varchar(128) NOT NULL,
  `Object` varchar(128) NOT NULL,
  `Accessory` set('false','true') NOT NULL DEFAULT 'false',
  `Manufacturer` varchar(128) NOT NULL,
  `YearOfConstruction` varchar(64) NOT NULL,
  `InsuranceAmount` int(11) NOT NULL,
  `BillToParty` int(11) NOT NULL DEFAULT 0,
  `StartOfInsurance` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `InsuranceBrokers`
--

CREATE TABLE `InsuranceBrokers` (
  `Id` int(11) NOT NULL,
  `ShortName` varchar(40) NOT NULL,
  `LongName` varchar(512) NOT NULL,
  `Address` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `InsuranceRates`
--

CREATE TABLE `InsuranceRates` (
  `Id` int(11) NOT NULL,
  `Broker` varchar(128) NOT NULL,
  `GeographicalScope` set('Germany','Europe','World') NOT NULL,
  `Rate` double NOT NULL COMMENT 'fraction, not percentage, excluding taxes',
  `DueDate` date NOT NULL COMMENT 'start of the yearly insurance period',
  `PolicyNumber` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `MusicianInstruments`
--

CREATE TABLE `MusicianInstruments` (
  `Id` int(11) NOT NULL,
  `MusicianId` int(11) NOT NULL COMMENT 'Index into table Musiker',
  `InstrumentId` int(11) NOT NULL COMMENT 'Index into table Instrumente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pivot-table, instruments a musician plays.';

-- --------------------------------------------------------

--
-- Table structure for table `Musiker`
--

CREATE TABLE `Musiker` (
  `Id` int(11) NOT NULL,
  `Name` varchar(128) NOT NULL,
  `Vorname` varchar(128) NOT NULL,
  `Stadt` varchar(128) NOT NULL,
  `Strasse` varchar(128) NOT NULL,
  `Postleitzahl` int(11) DEFAULT NULL,
  `Land` varchar(128) NOT NULL DEFAULT 'DE',
  `Sprachpräferenz` varchar(128) NOT NULL COMMENT 'Und was es sonst noch so gibt ...',
  `MobilePhone` varchar(128) NOT NULL,
  `FixedLinePhone` varchar(128) NOT NULL,
  `Geburtstag` date DEFAULT NULL,
  `Email` varchar(256) NOT NULL,
  `MemberStatus` enum('regular','passive','soloist','conductor','temporary') DEFAULT 'regular' COMMENT 'passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from "per-project" email unless explicitly selected.',
  `Remarks` varchar(1024) DEFAULT NULL,
  `UUID` char(36) CHARACTER SET ascii DEFAULT NULL,
  `Disabled` tinyint(1) NOT NULL DEFAULT 0,
  `Aktualisiert` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `numbers`
--

CREATE TABLE `numbers` (
  `N` int(11) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectEvents`
--

CREATE TABLE `ProjectEvents` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) DEFAULT NULL,
  `CalendarId` int(11) NOT NULL,
  `EventId` int(11) NOT NULL,
  `Type` enum('VEVENT','VTODO','VJOURNAL','VCARD') CHARACTER SET ascii DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectExtraFields`
--

CREATE TABLE `ProjectExtraFields` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) NOT NULL,
  `FieldIndex` int(11) NOT NULL COMMENT 'Extra-field index into Besetzungen table.',
  `DisplayOrder` int(11) DEFAULT NULL,
  `Name` varchar(128) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Type` int(11) NOT NULL DEFAULT 1,
  `AllowedValues` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Set of allowed values for set and enumerations.',
  `DefaultValue` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Default value.',
  `ToolTip` varchar(4096) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Tab` varchar(256) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tab to display the field in. If empty, then teh projects tab is used.',
  `Encrypted` tinyint(1) DEFAULT 0,
  `Readers` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'If non-empty restrict the visbility to this comma separated list of user-groups.',
  `Writers` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Empty or comma separated list of groups allowed to change the field.',
  `Disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectExtraFieldsData`
--

CREATE TABLE `ProjectExtraFieldsData` (
  `Id` int(11) NOT NULL,
  `BesetzungenId` int(11) NOT NULL,
  `FieldId` int(11) NOT NULL,
  `FieldValue` mediumtext CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectExtraFieldTypes`
--

CREATE TABLE `ProjectExtraFieldTypes` (
  `Id` int(11) NOT NULL,
  `Name` varchar(256) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Multiplicity` enum('simple','single','multiple','parallel','groupofpeople','groupsofpeople') NOT NULL,
  `Kind` enum('choices','surcharge','general','special') NOT NULL DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectInstrumentation`
--

CREATE TABLE `ProjectInstrumentation` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) NOT NULL COMMENT 'Link into table Projekte',
  `InstrumentId` int(11) NOT NULL COMMENT 'Link into table Instrumente',
  `Quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of required musicians for this instrument'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectInstruments`
--

CREATE TABLE `ProjectInstruments` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) NOT NULL COMMENT 'Index into table Projekte',
  `MusicianId` int(11) NOT NULL COMMENT 'Index into table Musiker',
  `InstrumentationId` int(11) NOT NULL COMMENT 'Index into table Besetzungen',
  `InstrumentId` int(11) NOT NULL COMMENT 'Index into table Instrumente',
  `Voice` int(11) DEFAULT NULL,
  `SectionLeader` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pivot-table which connects a given musician to the ist of instruments he or she plays in the given project (maybe multiple instruments)';

-- --------------------------------------------------------

--
-- Table structure for table `ProjectPayments`
--

CREATE TABLE `ProjectPayments` (
  `Id` int(11) NOT NULL,
  `InstrumentationId` int(11) NOT NULL COMMENT 'Link to Besetzungen.Id',
  `Amount` decimal(7,2) NOT NULL DEFAULT 0.00,
  `DateOfReceipt` date DEFAULT NULL,
  `Subject` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DebitNoteId` int(11) DEFAULT NULL COMMENT 'Link to the ProjectDirectDebit table.',
  `MandateReference` varchar(35) CHARACTER SET ascii DEFAULT NULL COMMENT 'Link into the SepaDebitMandates table, this is not the ID but the mandate Id.',
  `DebitMessageId` varchar(1024) CHARACTER SET utf8 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ProjectWebPages`
--

CREATE TABLE `ProjectWebPages` (
  `Id` int(11) NOT NULL,
  `ProjectId` int(11) NOT NULL DEFAULT -1,
  `ArticleId` int(11) NOT NULL DEFAULT -1,
  `ArticleName` varchar(128) NOT NULL DEFAULT '',
  `CategoryId` int(11) NOT NULL DEFAULT -1,
  `Priority` int(11) NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `Projekte`
--

CREATE TABLE `Projekte` (
  `Id` int(11) NOT NULL,
  `Jahr` int(4) UNSIGNED NOT NULL,
  `Name` varchar(64) NOT NULL,
  `Art` enum('temporary','permanent') CHARACTER SET ascii NOT NULL DEFAULT 'temporary',
  `Besetzung` set('Bandoneon','Bassposaune','Becken','Celesta','Cembalo','Englischhorn','Es-Klarinette','Fagott','Flöte','Flügel','Gast','Gesang','Glockenspiel','Große Trommel','Harfe','Kinderbetreuung','Klarinette','Klavier','Kleine Trommel','Kontrabass','Kontrafagott','Oboe','Orgel','Pauke','Piccoloflöte','Posaune','Schlagwerk','Taktstock','Tamtam','Tastatur','Telefon','Tenorsaxophon','Triangel','Trompete','Tuba','Viola','Violine','Violoncello','Waldhorn','Xylophon') DEFAULT NULL COMMENT 'Benötigte Instrumente',
  `Unkostenbeitrag` decimal(7,2) NOT NULL DEFAULT 0.00,
  `Anzahlung` decimal(7,2) NOT NULL DEFAULT 0.00,
  `ExtraFelder` text NOT NULL COMMENT 'Extra-Datenfelder',
  `Disabled` tinyint(1) NOT NULL DEFAULT 0,
  `Aktualisiert` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `SentEmail`
--

CREATE TABLE `SentEmail` (
  `Id` int(11) NOT NULL,
  `Date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user` longtext CHARACTER SET utf8mb4 NOT NULL,
  `host` varchar(64) CHARACTER SET utf8mb4 NOT NULL,
  `BulkRecipients` longtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5BulkRecipients` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Cc` longtext CHARACTER SET utf8mb4 NOT NULL,
  `Bcc` longtext CHARACTER SET utf8mb4 NOT NULL,
  `Subject` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `HtmlBody` longtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Text` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment1` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment1` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment2` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment2` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment3` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment3` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment4` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment4` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment00` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment00` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment01` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment01` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment02` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment02` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment03` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment03` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment04` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment04` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment05` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment05` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment06` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment06` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `Attachment07` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `MD5Attachment07` mediumtext CHARACTER SET utf8mb4 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `SepaDebitMandates`
--

CREATE TABLE `SepaDebitMandates` (
  `id` int(11) NOT NULL,
  `mandateReference` varchar(35) CHARACTER SET ascii NOT NULL,
  `mandateDate` date NOT NULL,
  `lastUsedDate` date DEFAULT NULL,
  `musicianId` int(11) NOT NULL,
  `projectId` int(11) NOT NULL,
  `nonrecurring` tinyint(1) NOT NULL,
  `IBAN` varchar(256) CHARACTER SET ascii NOT NULL,
  `BIC` varchar(256) CHARACTER SET ascii NOT NULL,
  `BLZ` varchar(128) CHARACTER SET ascii NOT NULL,
  `bankAccountOwner` varchar(512) CHARACTER SET ascii NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Besetzungen`
--
ALTER TABLE `Besetzungen`
  ADD UNIQUE KEY `Id` (`Id`),
  ADD UNIQUE KEY `ProjektId` (`ProjektId`,`MusikerId`);

--
-- Indexes for table `changelog`
--
ALTER TABLE `changelog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `DebitNoteData`
--
ALTER TABLE `DebitNoteData`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `DebitNotes`
--
ALTER TABLE `DebitNotes`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `EmailAttachments`
--
ALTER TABLE `EmailAttachments`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `FileName` (`FileName`);

--
-- Indexes for table `EmailDrafts`
--
ALTER TABLE `EmailDrafts`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `EmailTemplates`
--
ALTER TABLE `EmailTemplates`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Id` (`Id`,`Tag`),
  ADD UNIQUE KEY `Tag` (`Tag`);

--
-- Indexes for table `GeoContinents`
--
ALTER TABLE `GeoContinents`
  ADD PRIMARY KEY (`Code`);

--
-- Indexes for table `GeoCountries`
--
ALTER TABLE `GeoCountries`
  ADD PRIMARY KEY (`ISO`);

--
-- Indexes for table `GeoPostalCodes`
--
ALTER TABLE `GeoPostalCodes`
  ADD UNIQUE KEY `Country` (`Country`,`PostalCode`,`Name`);

--
-- Indexes for table `ImageData`
--
ALTER TABLE `ImageData`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ItemId` (`ItemId`,`ItemTable`);

--
-- Indexes for table `Instrumente`
--
ALTER TABLE `Instrumente`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Instrument` (`Instrument`);

--
-- Indexes for table `InstrumentInsurance`
--
ALTER TABLE `InstrumentInsurance`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `MusikerId` (`MusicianId`);

--
-- Indexes for table `InsuranceBrokers`
--
ALTER TABLE `InsuranceBrokers`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ShortName` (`ShortName`);

--
-- Indexes for table `InsuranceRates`
--
ALTER TABLE `InsuranceRates`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Id` (`Id`);

--
-- Indexes for table `MusicianInstruments`
--
ALTER TABLE `MusicianInstruments`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `MusicianId` (`MusicianId`,`InstrumentId`);

--
-- Indexes for table `Musiker`
--
ALTER TABLE `Musiker`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `numbers`
--
ALTER TABLE `numbers`
  ADD PRIMARY KEY (`N`);

--
-- Indexes for table `ProjectEvents`
--
ALTER TABLE `ProjectEvents`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ProjectId` (`ProjectId`,`EventId`);

--
-- Indexes for table `ProjectExtraFields`
--
ALTER TABLE `ProjectExtraFields`
  ADD UNIQUE KEY `Id` (`Id`),
  ADD UNIQUE KEY `ProjectFieldIndex` (`ProjectId`,`FieldIndex`),
  ADD KEY `ProjectId` (`ProjectId`);

--
-- Indexes for table `ProjectExtraFieldsData`
--
ALTER TABLE `ProjectExtraFieldsData`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `BesetzungenId` (`BesetzungenId`,`FieldId`);

--
-- Indexes for table `ProjectExtraFieldTypes`
--
ALTER TABLE `ProjectExtraFieldTypes`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `ProjectInstrumentation`
--
ALTER TABLE `ProjectInstrumentation`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ProjectId` (`ProjectId`,`InstrumentId`);

--
-- Indexes for table `ProjectInstruments`
--
ALTER TABLE `ProjectInstruments`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ProjectId` (`ProjectId`,`MusicianId`,`InstrumentId`),
  ADD UNIQUE KEY `InstrumentationId` (`InstrumentationId`,`InstrumentId`);

--
-- Indexes for table `ProjectPayments`
--
ALTER TABLE `ProjectPayments`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `ProjectWebPages`
--
ALTER TABLE `ProjectWebPages`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `ProjectId` (`ProjectId`,`ArticleId`);

--
-- Indexes for table `Projekte`
--
ALTER TABLE `Projekte`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Name` (`Name`);

--
-- Indexes for table `SentEmail`
--
ALTER TABLE `SentEmail`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `SepaDebitMandates`
--
ALTER TABLE `SepaDebitMandates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mandateReference` (`mandateReference`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Besetzungen`
--
ALTER TABLE `Besetzungen`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `changelog`
--
ALTER TABLE `changelog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `DebitNoteData`
--
ALTER TABLE `DebitNoteData`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `DebitNotes`
--
ALTER TABLE `DebitNotes`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `EmailAttachments`
--
ALTER TABLE `EmailAttachments`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `EmailDrafts`
--
ALTER TABLE `EmailDrafts`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `EmailTemplates`
--
ALTER TABLE `EmailTemplates`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ImageData`
--
ALTER TABLE `ImageData`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Instrumente`
--
ALTER TABLE `Instrumente`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `InstrumentInsurance`
--
ALTER TABLE `InstrumentInsurance`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `InsuranceBrokers`
--
ALTER TABLE `InsuranceBrokers`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `InsuranceRates`
--
ALTER TABLE `InsuranceRates`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MusicianInstruments`
--
ALTER TABLE `MusicianInstruments`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Musiker`
--
ALTER TABLE `Musiker`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `numbers`
--
ALTER TABLE `numbers`
  MODIFY `N` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectEvents`
--
ALTER TABLE `ProjectEvents`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectExtraFields`
--
ALTER TABLE `ProjectExtraFields`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectExtraFieldsData`
--
ALTER TABLE `ProjectExtraFieldsData`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectExtraFieldTypes`
--
ALTER TABLE `ProjectExtraFieldTypes`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectInstrumentation`
--
ALTER TABLE `ProjectInstrumentation`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectInstruments`
--
ALTER TABLE `ProjectInstruments`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectPayments`
--
ALTER TABLE `ProjectPayments`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProjectWebPages`
--
ALTER TABLE `ProjectWebPages`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Projekte`
--
ALTER TABLE `Projekte`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `SentEmail`
--
ALTER TABLE `SentEmail`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `SepaDebitMandates`
--
ALTER TABLE `SepaDebitMandates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
