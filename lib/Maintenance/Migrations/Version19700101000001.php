<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version19700101000001 extends AbstractMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Initial database setup.');
  }

  /**
   * {@inheritdoc}
   *
   * This is a structural migration and thus cannot be transactional on
   * MariaDB / MySQL.
   */
  public function isTransactional(): bool
  {
    return false;
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            CREATE TABLE ChangeLog (
              updated DATETIME(6) NOT NULL,
              user VARCHAR(255) DEFAULT NULL,
              host VARCHAR(255) DEFAULT NULL,
              operation VARCHAR(255) DEFAULT NULL,
              tab VARCHAR(255) DEFAULT NULL,
              rowkey VARCHAR(255) DEFAULT NULL,
              col VARCHAR(255) DEFAULT NULL,
              oldval BLOB DEFAULT NULL,
              newval BLOB DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE CompositePayments (
              amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
              date_of_receipt DATETIME(6) DEFAULT NULL,
              subject VARCHAR(1024) NOT NULL,
              notification_message_id VARCHAR(512) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              sepa_transaction_id INT DEFAULT NULL,
              musician_id INT NOT NULL,
              bank_account_sequence INT DEFAULT NULL,
              debit_mandate_sequence INT DEFAULT NULL,
              pre_notification_message_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              project_id INT NOT NULL,
              supporting_document_id INT DEFAULT NULL,
              balance_documents_folder_id INT DEFAULT NULL,
              INDEX IDX_65D9920CD5560045 (sepa_transaction_id),
              INDEX IDX_65D9920C9523AA8A2301E184 (
                musician_id, bank_account_sequence
              ),
              INDEX IDX_65D9920C9523AA8A544C02F9 (
                musician_id, debit_mandate_sequence
              ),
              INDEX IDX_65D9920C166D1F9C (project_id),
              INDEX IDX_65D9920C9523AA8A (musician_id),
              INDEX IDX_65D9920C166D1F9C9523AA8A (project_id, musician_id),
              UNIQUE INDEX UNIQ_65D9920C2423759C (supporting_document_id),
              INDEX IDX_65D9920C8A034ED2 (balance_documents_folder_id),
              UNIQUE INDEX UNIQ_65D9920C9B6CD002 (pre_notification_message_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE DatabaseStorageDirEntries (
              name VARCHAR(256) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              parent_id INT DEFAULT NULL,
              type ENUM('generic', 'folder', 'file') NOT NULL,
              file_id INT DEFAULT NULL,
              INDEX IDX_E123333D727ACA70 (parent_id),
              INDEX IDX_E123333D93CB796C (file_id),
              UNIQUE INDEX UNIQ_E123333D727ACA705E237E06 (parent_id, name),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE DatabaseStorages (
              storage_id VARCHAR(512) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              root_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_3594ED235CC5DB90 (storage_id),
              UNIQUE INDEX UNIQ_3594ED2379066886 (root_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE DonationReceipts (
              mailing_date DATETIME(6) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              donation_id INT NOT NULL,
              tax_exemption_notice_id INT DEFAULT NULL,
              supporting_document_id INT DEFAULT NULL,
              notification_message_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              UNIQUE INDEX UNIQ_AD46E7444DC1279C (donation_id),
              INDEX IDX_AD46E74434E7630B (tax_exemption_notice_id),
              UNIQUE INDEX UNIQ_AD46E7442423759C (supporting_document_id),
              UNIQUE INDEX UNIQ_AD46E744A808B60B (notification_message_id),
              UNIQUE INDEX donation_receipt_unique (donation_id, deleted),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE EmailAttachments (
              file_name VARCHAR(512) NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              created_by VARCHAR(255) DEFAULT NULL,
              updated_by VARCHAR(255) DEFAULT NULL,
              draft_id INT DEFAULT NULL,
              INDEX IDX_199F0CDBE2F3C5D1 (draft_id),
              PRIMARY KEY (file_name)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE EmailDrafts (
              subject VARCHAR(256) DEFAULT NULL,
              data JSON NOT NULL COMMENT 'Message Data Without Attachments',
              auto_generated TINYINT DEFAULT 0 NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              created_by VARCHAR(255) DEFAULT NULL,
              updated_by VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE EmailTemplates (
              tag VARCHAR(128) NOT NULL,
              subject VARCHAR(1024) NOT NULL,
              contents LONGTEXT DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              created_by VARCHAR(255) DEFAULT NULL,
              updated_by VARCHAR(255) DEFAULT NULL,
              UNIQUE INDEX UNIQ_51BDDDC389B783 (tag),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ExtLogEntries (
              id INT AUTO_INCREMENT NOT NULL,
              action VARCHAR(8) NOT NULL,
              logged_at DATETIME(6) NOT NULL,
              object_class VARCHAR(191) NOT NULL,
              version INT NOT NULL,
              data LONGTEXT DEFAULT NULL,
              username VARCHAR(191) DEFAULT NULL,
              remote_address VARCHAR(45) DEFAULT NULL,
              object_id VARCHAR(573) DEFAULT NULL,
              INDEX log_class_lookup_idx (object_class),
              INDEX log_date_lookup_idx (logged_at),
              INDEX log_user_lookup_idx (username),
              INDEX log_version_lookup_idx (object_id, object_class, version),
              INDEX log_action_lookup_idx (action, object_class),
              INDEX log_action_class_lookup_idx (action),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ROW_FORMAT = DYNAMIC
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE FileData (
              data_hash CHAR(32) NOT NULL,
              data LONGBLOB NOT NULL,
              file_id INT NOT NULL,
              type ENUM('generic', 'image', 'encrypted') NOT NULL,
              PRIMARY KEY (file_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Files (
              file_name VARCHAR(512) DEFAULT NULL,
              mime_type VARCHAR(128) NOT NULL,
              size INT DEFAULT -1 NOT NULL,
              data_hash CHAR(32) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              type ENUM('generic', 'image', 'encrypted') NOT NULL,
              width INT DEFAULT -1,
              height INT DEFAULT -1,
              INDEX IDX_C7F46F5DD7DF1668 (file_name),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GeoContinents (
              code CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              target CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              l10n_name VARCHAR(1024) NOT NULL,
              PRIMARY KEY (code, target)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GeoCountries (
              iso CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              target CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              l10n_name VARCHAR(1024) NOT NULL,
              continent_code CHAR(2) DEFAULT NULL COLLATE `ascii_general_ci`,
              INDEX IDX_7DF803716C569B466F2FFC (continent_code, target),
              PRIMARY KEY (iso, target)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GeoPostalCodeTranslations (
              target CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              translation VARCHAR(1024) NOT NULL,
              geo_postal_code_id INT NOT NULL,
              INDEX IDX_BC664719E70E684F (geo_postal_code_id),
              PRIMARY KEY (geo_postal_code_id, target)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GeoPostalCodes (
              country CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              state_province CHAR(3) DEFAULT NULL COLLATE `ascii_general_ci`,
              postal_code VARCHAR(32) NOT NULL,
              name VARCHAR(650) NOT NULL,
              latitude DOUBLE PRECISION NOT NULL,
              longitude DOUBLE PRECISION NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              INDEX updated (updated),
              UNIQUE INDEX UNIQ_B50ACD455373C966EA98E3765E237E06 (country, postal_code, name),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GeoStatesProvinces (
              country_iso CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              code CHAR(3) NOT NULL COLLATE `ascii_general_ci`,
              target CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              l10n_name VARCHAR(1024) NOT NULL,
              INDEX IDX_40C5B1885A7049D0466F2FFC (country_iso, target),
              PRIMARY KEY (country_iso, code, target)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashAccounts (
              guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              name VARCHAR(2028) NOT NULL,
              account_type VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
              commodity_scu INT NOT NULL,
              non_std_scu INT NOT NULL,
              code VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
              description VARCHAR(2028) NOT NULL,
              hidden TINYINT NOT NULL,
              placeholder TINYINT NOT NULL,
              commodity_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
              parent_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
              INDEX IDX_1C4A70F24F9CBEC7 (commodity_guid),
              INDEX IDX_1C4A70F2168CF906 (parent_guid),
              PRIMARY KEY (guid)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashBooks (
              guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              root_account_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              root_template_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              UNIQUE INDEX UNIQ_A26D411FD96A93A7 (root_account_guid),
              UNIQUE INDEX UNIQ_A26D411FA501DD19 (root_template_guid),
              PRIMARY KEY (guid)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashCommodities (
              guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              namespace VARCHAR(2024) NOT NULL COLLATE `ascii_general_ci`,
              mnemonic VARCHAR(2028) NOT NULL,
              fullname VARCHAR(2028) NOT NULL,
              cusip VARCHAR(2028) NOT NULL,
              fraction INT NOT NULL,
              quote_flag TINYINT NOT NULL,
              quote_source VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
              quote_tz VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
              PRIMARY KEY (guid)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashSlots (
              obj_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              name VARCHAR(4096) NOT NULL,
              slot_type INT NOT NULL,
              int64_val INT DEFAULT NULL,
              string_val VARCHAR(4096) DEFAULT NULL,
              double_val DOUBLE PRECISION DEFAULT NULL,
              timespec_val DATETIME(6) DEFAULT NULL,
              guid_val CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
              numeric_val_num INT DEFAULT NULL,
              numeric_val_denom INT DEFAULT NULL,
              gdate_val DATETIME(6) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              INDEX slots_guid_index (obj_guid),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashSplits (
              guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              memo VARCHAR(2028) NOT NULL,
              action VARCHAR(2028) NOT NULL,
              reconcile_state CHAR(1) NOT NULL COLLATE `ascii_general_ci`,
              reconcile_date DATETIME(6) DEFAULT NULL,
              value_num INT NOT NULL,
              value_denom INT NOT NULL,
              quantity_num INT NOT NULL,
              quantity_denom INT NOT NULL,
              lot_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
              tx_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              account_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              INDEX splits_tx_guid_index (tx_guid),
              INDEX splits_account_guid_index (account_guid),
              PRIMARY KEY (guid)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE GnuCashTransactions (
              guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              num VARCHAR(2028) NOT NULL,
              post_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL,
              enter_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL,
              description VARCHAR(2028) DEFAULT NULL,
              currency_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
              INDEX IDX_403125FA1D88CC6 (currency_guid),
              INDEX tx_post_date_index (post_date),
              PRIMARY KEY (guid)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE InstrumentFamilies (
              family VARCHAR(255) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              UNIQUE INDEX UNIQ_31147B76A5E6215B (family),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE InstrumentInsurances (
              object VARCHAR(128) NOT NULL,
              accessory TINYINT DEFAULT 0,
              manufacturer VARCHAR(128) NOT NULL,
              year_of_construction VARCHAR(64) NOT NULL,
              insurance_amount INT NOT NULL,
              start_of_insurance DATETIME(6) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              instrument_holder_id INT NOT NULL,
              instrument_owner_id INT DEFAULT NULL,
              bill_to_party_id INT NOT NULL,
              broker_id VARCHAR(40) NOT NULL,
              geographical_scope ENUM(
                'Domestic', 'Continent', 'Germany',
                'Europe', 'World'
              ) NOT NULL,
              INDEX IDX_B9BA7EFA948FBE6 (instrument_holder_id),
              INDEX IDX_B9BA7EFDF95C1F8 (instrument_owner_id),
              INDEX IDX_B9BA7EF9D7A36FA (bill_to_party_id),
              INDEX IDX_B9BA7EF6CC064FCBD069886 (broker_id, geographical_scope),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Instruments (
              name VARCHAR(128) NOT NULL,
              sort_order INT NOT NULL COMMENT 'Orchestral Ordering',
              deleted DATETIME(6) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE instrument_instrument_family (
              instrument_id INT NOT NULL,
              instrument_family_id INT NOT NULL,
              INDEX IDX_2C15852ACF11D9C (instrument_id),
              INDEX IDX_2C15852AB4F8CF5C (instrument_family_id),
              PRIMARY KEY (
                instrument_id, instrument_family_id
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE InsuranceBrokers (
              short_name VARCHAR(40) NOT NULL,
              long_name VARCHAR(512) NOT NULL,
              address VARCHAR(512) NOT NULL,
              PRIMARY KEY (short_name)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE InsuranceRates (
              geographical_scope ENUM(
                'Domestic', 'Continent', 'Germany',
                'Europe', 'World'
              ) DEFAULT 'Germany' NOT NULL,
              rate NUMERIC(4, 4) UNSIGNED NOT NULL COMMENT 'fraction, not percentage, excluding taxes',
              due_date DATETIME(6) DEFAULT NULL COMMENT 'start of the yearly insurance period',
              policy_number VARCHAR(255) DEFAULT NULL,
              broker_id VARCHAR(40) NOT NULL,
              INDEX IDX_CB75C3526CC064FC (broker_id),
              PRIMARY KEY (broker_id, geographical_scope)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE InvoiceItems (
              amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
              subject VARCHAR(1024) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              field_id INT NOT NULL,
              project_id INT NOT NULL,
              debitor_id INT NOT NULL,
              receivable_key BINARY(16) NOT NULL,
              invoice_id INT NOT NULL,
              balance_documents_folder_id INT DEFAULT NULL,
              INDEX IDX_670E0FCF443707B0166D1F9C72757D19D151D1BF (
                field_id, project_id, debitor_id,
                receivable_key
              ),
              INDEX IDX_670E0FCF443707B0D151D1BF (field_id, receivable_key),
              INDEX IDX_670E0FCF2989F1FD (invoice_id),
              INDEX IDX_670E0FCF166D1F9C (project_id),
              INDEX IDX_670E0FCF72757D19 (debitor_id),
              INDEX IDX_670E0FCF166D1F9C72757D19 (project_id, debitor_id),
              INDEX IDX_670E0FCF8A034ED2 (balance_documents_folder_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Invoices (
              invoice_number VARCHAR(255) NOT NULL,
              amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
              invoice_date DATETIME(6) DEFAULT CURRENT_DATE NOT NULL,
              due_date DATETIME(6) DEFAULT NULL,
              balanced_date DATETIME(6) DEFAULT NULL,
              subject VARCHAR(1024) NOT NULL,
              purpose LONGTEXT DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              originator_id INT DEFAULT NULL,
              debitor_id INT NOT NULL,
              sepa_transaction_id INT DEFAULT NULL,
              bank_account_sequence INT DEFAULT NULL,
              debit_mandate_sequence INT DEFAULT NULL,
              project_id INT NOT NULL,
              balance_documents_folder_id INT DEFAULT NULL,
              written_invoice_id INT DEFAULT NULL,
              taxation_statutory_source_id INT NOT NULL,
              notification_message_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              INDEX IDX_93594DC33DA3F86F (originator_id),
              INDEX IDX_93594DC372757D19 (debitor_id),
              INDEX IDX_93594DC3D5560045 (sepa_transaction_id),
              INDEX IDX_93594DC372757D192301E184 (
                debitor_id, bank_account_sequence
              ),
              INDEX IDX_93594DC372757D19544C02F9 (
                debitor_id, debit_mandate_sequence
              ),
              INDEX IDX_93594DC3166D1F9C (project_id),
              INDEX IDX_93594DC3166D1F9C72757D19 (project_id, debitor_id),
              INDEX IDX_93594DC38A034ED2 (balance_documents_folder_id),
              UNIQUE INDEX UNIQ_93594DC397F6692F (written_invoice_id),
              INDEX IDX_93594DC366FAD11 (taxation_statutory_source_id),
              UNIQUE INDEX UNIQ_93594DC3A808B60B (notification_message_id),
              UNIQUE INDEX UNIQ_93594DC32DA68207 (invoice_number),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Migrations (
              version CHAR(14) NOT NULL COLLATE `ascii_general_ci`,
              migration_class_name VARCHAR(512) NOT NULL,
              run_count INT DEFAULT 1 NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              PRIMARY KEY (version)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE MissingTranslations (
              locale VARCHAR(5) NOT NULL,
              translation_key_id INT NOT NULL,
              INDEX IDX_DBBA64EAD07ED992 (translation_key_id),
              PRIMARY KEY (translation_key_id, locale)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE MusicianEmailAddresses (
              address VARCHAR(254) NOT NULL COLLATE `ascii_general_ci`,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              musician_id INT NOT NULL,
              INDEX IDX_13DF84F69523AA8A (musician_id),
              PRIMARY KEY (address, musician_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE MusicianInstruments (
              ranking INT DEFAULT 1 NOT NULL COMMENT 'Ranking of the instrument w.r.t. to the given musician (lower is better)',
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              musician_id INT NOT NULL,
              instrument_id INT NOT NULL,
              INDEX IDX_332855779523AA8A (musician_id),
              INDEX IDX_33285577CF11D9C (instrument_id),
              PRIMARY KEY (musician_id, instrument_id)
            ) DEFAULT CHARACTER SET utf8mb4 COMMENT = 'Join-table Musicians -> Instruments'
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE MusicianRowAccessTokens (
              user_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              access_token_hash CHAR(128) NOT NULL COLLATE `ascii_bin`,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              musician_id INT NOT NULL,
              UNIQUE INDEX UNIQ_64C47A56A76ED395 (user_id),
              UNIQUE INDEX UNIQ_64C47A569982CF5B (access_token_hash),
              PRIMARY KEY (musician_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Musicians (
              sur_name VARCHAR(128) NOT NULL,
              first_name VARCHAR(128) NOT NULL,
              nick_name VARCHAR(128) DEFAULT NULL,
              display_name VARCHAR(256) DEFAULT NULL,
              gender ENUM('male', 'female', 'diverse') DEFAULT NULL,
              user_id_slug VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              user_passphrase VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              city VARCHAR(128) DEFAULT NULL,
              street VARCHAR(128) DEFAULT NULL,
              street_number VARCHAR(32) DEFAULT NULL,
              address_supplement VARCHAR(128) DEFAULT NULL,
              po_box VARCHAR(128) DEFAULT NULL,
              country CHAR(2) DEFAULT NULL COLLATE `ascii_general_ci`,
              postal_code VARCHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
              language CHAR(5) DEFAULT NULL COLLATE `ascii_general_ci`,
              mobile_phone VARCHAR(128) DEFAULT NULL,
              fixed_line_phone VARCHAR(128) DEFAULT NULL,
              birthday DATETIME(6) DEFAULT NULL,
              email VARCHAR(254) DEFAULT NULL COLLATE `ascii_general_ci`,
              default_participation_status ENUM(
                'associated', 'conductor', 'passive',
                'regular', 'soloist', 'temporary'
              ) DEFAULT 'regular' NOT NULL,
              remarks VARCHAR(1024) DEFAULT NULL,
              cloud_account_deactivated TINYINT DEFAULT NULL,
              cloud_account_disabled TINYINT DEFAULT 1,
              updated DATETIME(6) DEFAULT NULL,
              address_book_uri VARCHAR(255) DEFAULT NULL,
              organization VARCHAR(255) DEFAULT NULL,
              job_title VARCHAR(255) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              uuid BINARY(16) NOT NULL,
              UNIQUE INDEX UNIQ_3CC489824BB0996A (user_id_slug),
              UNIQUE INDEX UNIQ_3CC48982D17F50A6 (uuid),
              INDEX country_postal_code_deleted (country, postal_code, deleted),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE EncryptedFileOwners (
              musician_id INT NOT NULL,
              encrypted_file_id INT NOT NULL,
              INDEX IDX_5697DE239523AA8A (musician_id),
              INDEX IDX_5697DE23EC15E76C (encrypted_file_id),
              PRIMARY KEY (musician_id, encrypted_file_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectApplications (
              email VARCHAR(254) NOT NULL COLLATE `ascii_general_ci`,
              password_hash VARCHAR(254) DEFAULT NULL COLLATE `ascii_general_ci`,
              data JSON DEFAULT '{}' NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              project_id INT NOT NULL,
              musician_id INT DEFAULT NULL,
              INDEX IDX_5F0E8E19166D1F9C (project_id),
              INDEX IDX_5F0E8E199523AA8A (musician_id),
              PRIMARY KEY (project_id, email)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectEvents (
              calendar_id INT NOT NULL,
              calendar_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`,
              event_uid VARCHAR(255) NOT NULL COLLATE `ascii_general_ci`,
              series_uid BINARY(16) DEFAULT NULL,
              event_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`,
              recurrence_id INT DEFAULT 0 NOT NULL,
              sequence INT DEFAULT 0 NOT NULL,
              type ENUM(
                'VEVENT', 'VTODO', 'VJOURNAL', 'VCARD'
              ) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              project_id INT NOT NULL,
              absence_field_id INT DEFAULT NULL,
              INDEX IDX_7E38FC8B166D1F9C (project_id),
              UNIQUE INDEX UNIQ_7E38FC8BA79D8A87 (absence_field_id),
              UNIQUE INDEX UNIQ_7E38FC8B166D1F9C7A7DD3924254C3D52C414CE8 (
                project_id, calendar_uri, event_uid,
                recurrence_id
              ),
              UNIQUE INDEX UNIQ_7E38FC8B166D1F9CA40A2C84254C3D52C414CE8 (
                project_id, calendar_id, event_uid,
                recurrence_id
              ),
              UNIQUE INDEX UNIQ_7E38FC8B166D1F9C7A7DD39295D374F22C414CE8 (
                project_id, calendar_uri, event_uri,
                recurrence_id
              ),
              UNIQUE INDEX UNIQ_7E38FC8B166D1F9CA40A2C895D374F22C414CE8 (
                project_id, calendar_id, event_uri,
                recurrence_id
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectInstrumentationNumbers (
              voice INT DEFAULT 0 NOT NULL COMMENT 'Voice specification if applicable, set to 0 if separation by voice is not needed',
              quantity INT DEFAULT 1 NOT NULL COMMENT 'Number of required musicians for this instrument',
              project_id INT NOT NULL,
              instrument_id INT NOT NULL,
              INDEX IDX_D8939186166D1F9C (project_id),
              INDEX IDX_D8939186CF11D9C (instrument_id),
              PRIMARY KEY (project_id, instrument_id, voice)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectInstruments (
              voice INT DEFAULT 0 NOT NULL COMMENT 'Voice specification if applicable, set to 0 if separation by voice is not needed',
              section_leader TINYINT DEFAULT 0 NOT NULL,
              project_id INT NOT NULL,
              musician_id INT NOT NULL,
              instrument_id INT NOT NULL,
              INDEX IDX_436762A6166D1F9C (project_id),
              INDEX IDX_436762A69523AA8A (musician_id),
              INDEX IDX_436762A6CF11D9C (instrument_id),
              INDEX IDX_436762A6166D1F9C9523AA8A (project_id, musician_id),
              INDEX IDX_436762A69523AA8ACF11D9C (musician_id, instrument_id),
              INDEX IDX_436762A6166D1F9CCF11D9CE7FB583B (project_id, instrument_id, voice),
              PRIMARY KEY (
                project_id, musician_id, instrument_id,
                voice
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectParticipantFields (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(128) NOT NULL,
              multiplicity ENUM(
                'simple', 'single', 'multiple', 'parallel',
                'recurring', 'groupofpeople', 'groupsofpeople'
              ) NOT NULL,
              data_type ENUM(
                'boolean', 'cloud-file', 'cloud-folder',
                'date', 'datetime', 'db-file', 'float',
                'html', 'integer', 'liabilities',
                'receivables', 'text'
              ) DEFAULT 'text' NOT NULL,
              due_date DATETIME(6) DEFAULT NULL COMMENT 'Due-date for financial fields.',
              deposit_due_date DATETIME(6) DEFAULT NULL COMMENT 'Due-date of deposit for financial fields.',
              balancing_account VARCHAR(1024) DEFAULT NULL,
              tooltip VARCHAR(4096) DEFAULT NULL,
              tab VARCHAR(256) DEFAULT NULL COMMENT 'Tab to display the field in. If empty, then the project tab is used.',
              display_order INT DEFAULT NULL,
              participation_context ENUM(
                'associates', 'participants', 'unrestricted'
              ) DEFAULT 'unrestricted' NOT NULL,
              encrypted TINYINT DEFAULT 0,
              participant_access ENUM('none', 'read', 'read-write') DEFAULT 'none' NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              project_id INT NOT NULL,
              default_value BINARY(16) DEFAULT NULL,
              INDEX IDX_F6F5D9C6166D1F9C (project_id),
              INDEX IDX_F6F5D9C6BF396750F4510C3A (id, default_value),
              INDEX IDX_F6F5D9C6BF396750166D1F9C (id, project_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectParticipantFieldsData (
              option_key BINARY(16) NOT NULL,
              option_value MEDIUMTEXT DEFAULT NULL,
              deposit NUMERIC(7, 2) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              field_id INT NOT NULL,
              project_id INT NOT NULL,
              musician_id INT NOT NULL,
              supporting_document_id INT DEFAULT NULL,
              INDEX IDX_E1AAA1E9443707B0 (field_id),
              INDEX IDX_E1AAA1E9166D1F9C (project_id),
              INDEX IDX_E1AAA1E99523AA8A (musician_id),
              INDEX IDX_E1AAA1E9443707B03CEE7BEE (field_id, option_key),
              INDEX IDX_E1AAA1E9166D1F9C9523AA8A (project_id, musician_id),
              UNIQUE INDEX UNIQ_E1AAA1E92423759C (supporting_document_id),
              INDEX IDX_E1AAA1E9443707B0166D1F9C (field_id, project_id),
              PRIMARY KEY (
                field_id, project_id, musician_id,
                option_key
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectParticipantFieldsDataOptions (
              `key` BINARY(16) NOT NULL,
              label VARCHAR(128) DEFAULT NULL,
              data VARCHAR(1024) DEFAULT NULL,
              balancing_account VARCHAR(1024) DEFAULT NULL,
              deposit NUMERIC(7, 2) DEFAULT NULL,
              `limit` BIGINT DEFAULT NULL,
              tooltip VARCHAR(4096) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              field_id INT NOT NULL,
              INDEX IDX_FA443FE443707B0 (field_id),
              INDEX IDX_FA443FE8A90ABA9 (`key`),
              PRIMARY KEY (field_id, `key`)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectParticipants (
              registration TINYINT DEFAULT 0 COMMENT 'Participant has confirmed the registration.',
              participation_status ENUM(
                'associated', 'conductor', 'passive',
                'regular', 'soloist', 'temporary'
              ) DEFAULT 'regular' NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              project_id INT NOT NULL,
              musician_id INT NOT NULL,
              database_documents_id INT DEFAULT NULL,
              INDEX IDX_D9AE987B166D1F9C (project_id),
              INDEX IDX_D9AE987B9523AA8A (musician_id),
              UNIQUE INDEX UNIQ_D9AE987BC6073910 (database_documents_id),
              PRIMARY KEY (project_id, musician_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectPayments (
              amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
              is_donation TINYINT DEFAULT 0 NOT NULL,
              subject VARCHAR(1024) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              field_id INT NOT NULL,
              project_id INT NOT NULL,
              musician_id INT NOT NULL,
              receivable_key BINARY(16) NOT NULL,
              composite_payment_id INT NOT NULL,
              balance_documents_folder_id INT DEFAULT NULL,
              INDEX IDX_F6372AE2443707B0166D1F9C9523AA8AD151D1BF (
                field_id, project_id, musician_id,
                receivable_key
              ),
              INDEX IDX_F6372AE2443707B0D151D1BF (field_id, receivable_key),
              INDEX IDX_F6372AE2930D2644 (composite_payment_id),
              INDEX IDX_F6372AE2166D1F9C (project_id),
              INDEX IDX_F6372AE29523AA8A (musician_id),
              INDEX IDX_F6372AE2166D1F9C9523AA8A (project_id, musician_id),
              INDEX IDX_F6372AE28A034ED2 (balance_documents_folder_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE ProjectWebPages (
              article_id INT DEFAULT -1 NOT NULL,
              article_name VARCHAR(128) DEFAULT '' NOT NULL,
              category_id INT DEFAULT -1 NOT NULL,
              priority INT DEFAULT -1 NOT NULL,
              project_id INT NOT NULL,
              INDEX IDX_EB77064F166D1F9C (project_id),
              UNIQUE INDEX UNIQ_EB77064F166D1F9C7294869C (project_id, article_id),
              PRIMARY KEY (project_id, article_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Projects (
              year INT UNSIGNED NOT NULL,
              name VARCHAR(64) NOT NULL,
              type ENUM(
                'temporary', 'permanent', 'template'
              ) DEFAULT 'temporary' NOT NULL,
              mailing_list_id VARCHAR(128) DEFAULT NULL COLLATE `ascii_general_ci`,
              registration_start_date DATETIME(6) DEFAULT NULL,
              registration_deadline DATETIME(6) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              financial_balance_documents_storage_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_A5E5D1F214CA24B1 (
                financial_balance_documents_storage_id
              ),
              UNIQUE INDEX UNIQ_A5E5D1F25E237E06 (name),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SentEmails (
              message_id VARCHAR(256) NOT NULL COLLATE `ascii_bin`,
              created_by VARCHAR(255) DEFAULT NULL,
              bulk_recipients LONGTEXT NOT NULL,
              bulk_recipients_hash CHAR(32) NOT NULL COLLATE `ascii_bin`,
              cc LONGTEXT DEFAULT NULL,
              bcc LONGTEXT DEFAULT NULL,
              subject LONGTEXT NOT NULL,
              subject_hash CHAR(32) NOT NULL COLLATE `ascii_bin`,
              html_body LONGTEXT NOT NULL,
              html_body_hash CHAR(32) NOT NULL COLLATE `ascii_bin`,
              created DATETIME(6) DEFAULT NULL,
              project_id INT DEFAULT NULL,
              reference_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
              sepa_bulk_transaction_id INT DEFAULT NULL,
              INDEX IDX_80F49BA0166D1F9C (project_id),
              INDEX IDX_80F49BA01645DEA9 (reference_id),
              INDEX IDX_80F49BA0ED6D4895 (sepa_bulk_transaction_id),
              PRIMARY KEY (message_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SepaBankAccounts (
              sequence INT NOT NULL,
              iban VARCHAR(2048) NOT NULL COLLATE `ascii_bin`,
              bic VARCHAR(2048) NOT NULL COLLATE `ascii_bin`,
              blz VARCHAR(2048) NOT NULL COLLATE `ascii_bin`,
              bank_account_owner VARCHAR(2048) NOT NULL COLLATE `ascii_bin`,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              musician_id INT NOT NULL,
              INDEX IDX_4F1F148B9523AA8A (musician_id),
              PRIMARY KEY (musician_id, sequence)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SepaBulkTransactions (
              submission_deadline DATETIME(6) NOT NULL,
              submit_date DATETIME(6) DEFAULT NULL,
              due_date DATETIME(6) NOT NULL,
              submission_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
              submission_event_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
              submission_task_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
              submission_task_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
              due_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
              due_event_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
              id INT AUTO_INCREMENT NOT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              sepa_transaction ENUM('debit_note', 'bank_transfer') NOT NULL,
              pre_notification_deadline DATETIME(6) DEFAULT NULL,
              pre_notification_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
              pre_notification_event_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
              pre_notification_task_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
              pre_notification_task_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SepaBulkTransactionData (
              sepa_bulk_transaction_id INT NOT NULL,
              database_storage_file_id INT NOT NULL,
              INDEX IDX_1EBA3E5BED6D4895 (sepa_bulk_transaction_id),
              UNIQUE INDEX UNIQ_1EBA3E5B4D73A4D4 (database_storage_file_id),
              PRIMARY KEY (
                sepa_bulk_transaction_id, database_storage_file_id
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SepaBulkTransactionBalancingData (
              sepa_bulk_transaction_id INT NOT NULL,
              database_storage_file_id INT NOT NULL,
              INDEX IDX_6EC2B172ED6D4895 (sepa_bulk_transaction_id),
              UNIQUE INDEX UNIQ_6EC2B1724D73A4D4 (database_storage_file_id),
              PRIMARY KEY (
                sepa_bulk_transaction_id, database_storage_file_id
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE SepaDebitMandates (
              sequence INT NOT NULL,
              mandate_reference VARCHAR(35) NOT NULL COLLATE `ascii_general_ci`,
              non_recurring TINYINT NOT NULL,
              mandate_date DATETIME(6) DEFAULT NULL,
              pre_notification_calendar_days INT DEFAULT 14 NOT NULL,
              pre_notification_business_days INT DEFAULT NULL,
              last_used_date DATETIME(6) DEFAULT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              musician_id INT NOT NULL,
              bank_account_sequence INT NOT NULL,
              project_id INT NOT NULL,
              written_mandate_id INT DEFAULT NULL,
              INDEX IDX_1C500299523AA8A (musician_id),
              INDEX IDX_1C500299523AA8A2301E184 (
                musician_id, bank_account_sequence
              ),
              INDEX IDX_1C50029166D1F9C (project_id),
              UNIQUE INDEX UNIQ_1C50029D26EB11F (written_mandate_id),
              INDEX IDX_1C500299523AA8A2301E184166D1F9C (
                musician_id, bank_account_sequence,
                project_id
              ),
              UNIQUE INDEX UNIQ_1C50029D0BE4741 (mandate_reference),
              UNIQUE INDEX UNIQ_1C500299523AA8A5286D72B166D1F9C (
                musician_id, sequence, project_id
              ),
              PRIMARY KEY (musician_id, sequence)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TableFieldTranslations (
              id INT AUTO_INCREMENT NOT NULL,
              locale VARCHAR(8) NOT NULL,
              object_class VARCHAR(191) NOT NULL,
              field VARCHAR(32) NOT NULL,
              foreign_key VARCHAR(64) NOT NULL,
              content LONGTEXT DEFAULT NULL,
              INDEX translations_lookup_idx (
                locale, object_class, foreign_key
              ),
              UNIQUE INDEX lookup_unique_idx (
                locale, object_class, field, foreign_key
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ROW_FORMAT = DYNAMIC
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TaxExemptionNotices (
              assessment_period_start INT NOT NULL,
              assessment_period_end INT NOT NULL,
              tax_office VARCHAR(256) NOT NULL,
              tax_number VARCHAR(256) NOT NULL,
              date_issued DATETIME(6) DEFAULT NULL,
              beneficiary_purpose VARCHAR(4096) NOT NULL,
              membership_fees_are_donations TINYINT DEFAULT 0 NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              written_notice_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_6417EA3735D82D9 (written_notice_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TaxExemptionItems (
              tax_exemption_notice_id INT NOT NULL,
              taxation_statutory_source_id INT NOT NULL,
              INDEX IDX_9D0F193734E7630B (tax_exemption_notice_id),
              INDEX IDX_9D0F193766FAD11 (taxation_statutory_source_id),
              PRIMARY KEY (
                tax_exemption_notice_id, taxation_statutory_source_id
              )
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TaxationStatutorySources (
              tax_type ENUM(
                'corporate income tax', 'sales tax',
                'trade tax', 'VAT', 'insurance tax'
              ) DEFAULT 'corporate income tax' NOT NULL,
              rate NUMERIC(2, 2) UNSIGNED DEFAULT '0.00' NOT NULL,
              country CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
              law VARCHAR(255) NOT NULL,
              hint VARCHAR(1024) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              deleted DATETIME(6) DEFAULT NULL,
              created DATETIME(6) DEFAULT NULL,
              updated DATETIME(6) DEFAULT NULL,
              UNIQUE INDEX UNIQ_8F39BDDD905158D1C0B552F (tax_type, law),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TranslationKeys (
              phrase LONGTEXT NOT NULL COMMENT 'Keyword to be translated. Normally the untranslated text in locale en_US, but could be any unique tag',
              phrase_hash CHAR(32) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              UNIQUE INDEX UNIQ_F15EDA495A875D0C (phrase_hash),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE TranslationLocations (
              file VARCHAR(766) NOT NULL,
              line INT NOT NULL,
              translation_key_id INT NOT NULL,
              INDEX IDX_F23942BBD07ED992 (translation_key_id),
              PRIMARY KEY (translation_key_id, file, line)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE Translations (
              locale CHAR(5) NOT NULL COMMENT 'Locale for translation, .e.g. en_US',
              translation VARCHAR(1024) NOT NULL,
              translation_key_id INT NOT NULL,
              INDEX IDX_DE86017FD07ED992 (translation_key_id),
              PRIMARY KEY (translation_key_id, locale)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE WebBrowserHistoryData (
              hash CHAR(64) NOT NULL COLLATE `ascii_general_ci`,
              data LONGBLOB NOT NULL COMMENT 'JSON encrypted',
              PRIMARY KEY (hash)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE WebBrowserHistoryEntries (
              `key` NUMERIC(16, 3) UNSIGNED NOT NULL,
              path VARCHAR(32768) NOT NULL COLLATE `ascii_bin`,
              state_id INT NOT NULL,
              data_hash CHAR(64) NOT NULL COLLATE `ascii_general_ci`,
              INDEX IDX_2059233F5D83CC1 (state_id),
              INDEX IDX_2059233F6AF7A95A (data_hash),
              PRIMARY KEY (state_id, `key`)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            CREATE TABLE WebBrowserHistoryStates (
              user_id VARCHAR(256) NOT NULL,
              created DATETIME(6) NOT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              updated DATETIME(6) DEFAULT NULL,
              pos_state_id INT DEFAULT NULL,
              pos_key NUMERIC(16, 3) UNSIGNED DEFAULT NULL,
              INDEX IDX_FD38B3C74CDC76F1D06B458A (pos_state_id, pos_key),
              UNIQUE INDEX UNIQ_FD38B3C7A76ED395B23DB7B8 (user_id, created),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920CD5560045 FOREIGN KEY (sepa_transaction_id) REFERENCES SepaBulkTransactions (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C9523AA8A2301E184 FOREIGN KEY (
                musician_id, bank_account_sequence
              ) REFERENCES SepaBankAccounts (musician_id, sequence)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C9523AA8A544C02F9 FOREIGN KEY (
                musician_id, debit_mandate_sequence
              ) REFERENCES SepaDebitMandates (musician_id, sequence)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C9B6CD002 FOREIGN KEY (pre_notification_message_id) REFERENCES SentEmails (message_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C9523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C166D1F9C9523AA8A FOREIGN KEY (project_id, musician_id) REFERENCES ProjectParticipants (project_id, musician_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C2423759C FOREIGN KEY (supporting_document_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              CompositePayments
            ADD
              CONSTRAINT FK_65D9920C8A034ED2 FOREIGN KEY (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DatabaseStorageDirEntries
            ADD
              CONSTRAINT FK_E123333D727ACA70 FOREIGN KEY (parent_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DatabaseStorageDirEntries
            ADD
              CONSTRAINT FK_E123333D93CB796C FOREIGN KEY (file_id) REFERENCES Files (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DatabaseStorages
            ADD
              CONSTRAINT FK_3594ED2379066886 FOREIGN KEY (root_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DonationReceipts
            ADD
              CONSTRAINT FK_AD46E7444DC1279C FOREIGN KEY (donation_id) REFERENCES CompositePayments (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DonationReceipts
            ADD
              CONSTRAINT FK_AD46E74434E7630B FOREIGN KEY (tax_exemption_notice_id) REFERENCES TaxExemptionNotices (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DonationReceipts
            ADD
              CONSTRAINT FK_AD46E7442423759C FOREIGN KEY (supporting_document_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              DonationReceipts
            ADD
              CONSTRAINT FK_AD46E744A808B60B FOREIGN KEY (notification_message_id) REFERENCES SentEmails (message_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              EmailAttachments
            ADD
              CONSTRAINT FK_199F0CDBE2F3C5D1 FOREIGN KEY (draft_id) REFERENCES EmailDrafts (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              FileData
            ADD
              CONSTRAINT FK_969FA96893CB796C FOREIGN KEY (file_id) REFERENCES Files (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GeoCountries
            ADD
              CONSTRAINT FK_7DF803716C569B466F2FFC FOREIGN KEY (continent_code, target) REFERENCES GeoContinents (code, target)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GeoPostalCodeTranslations
            ADD
              CONSTRAINT FK_BC664719E70E684F FOREIGN KEY (geo_postal_code_id) REFERENCES GeoPostalCodes (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GeoStatesProvinces
            ADD
              CONSTRAINT FK_40C5B1885A7049D0466F2FFC FOREIGN KEY (country_iso, target) REFERENCES GeoCountries (iso, target)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashAccounts
            ADD
              CONSTRAINT FK_1C4A70F24F9CBEC7 FOREIGN KEY (commodity_guid) REFERENCES GnuCashCommodities (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashAccounts
            ADD
              CONSTRAINT FK_1C4A70F2168CF906 FOREIGN KEY (parent_guid) REFERENCES GnuCashAccounts (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashBooks
            ADD
              CONSTRAINT FK_A26D411FD96A93A7 FOREIGN KEY (root_account_guid) REFERENCES GnuCashAccounts (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashBooks
            ADD
              CONSTRAINT FK_A26D411FA501DD19 FOREIGN KEY (root_template_guid) REFERENCES GnuCashAccounts (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashSplits
            ADD
              CONSTRAINT FK_E2EE9395D252EC5E FOREIGN KEY (tx_guid) REFERENCES GnuCashTransactions (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashSplits
            ADD
              CONSTRAINT FK_E2EE9395A7FC4818 FOREIGN KEY (account_guid) REFERENCES GnuCashAccounts (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              GnuCashTransactions
            ADD
              CONSTRAINT FK_403125FA1D88CC6 FOREIGN KEY (currency_guid) REFERENCES GnuCashCommodities (guid)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InstrumentInsurances
            ADD
              CONSTRAINT FK_B9BA7EFA948FBE6 FOREIGN KEY (instrument_holder_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InstrumentInsurances
            ADD
              CONSTRAINT FK_B9BA7EFDF95C1F8 FOREIGN KEY (instrument_owner_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InstrumentInsurances
            ADD
              CONSTRAINT FK_B9BA7EF9D7A36FA FOREIGN KEY (bill_to_party_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InstrumentInsurances
            ADD
              CONSTRAINT FK_B9BA7EF6CC064FCBD069886 FOREIGN KEY (broker_id, geographical_scope) REFERENCES InsuranceRates (broker_id, geographical_scope)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              instrument_instrument_family
            ADD
              CONSTRAINT FK_2C15852ACF11D9C FOREIGN KEY (instrument_id) REFERENCES Instruments (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              instrument_instrument_family
            ADD
              CONSTRAINT FK_2C15852AB4F8CF5C FOREIGN KEY (instrument_family_id) REFERENCES InstrumentFamilies (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InsuranceRates
            ADD
              CONSTRAINT FK_CB75C3526CC064FC FOREIGN KEY (broker_id) REFERENCES InsuranceBrokers (short_name)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InsuranceRates
            ADD
              CONSTRAINT FK_CB75C3526CC064FC_ONUPDATE_CASCADE FOREIGN KEY (broker_id) REFERENCES InsuranceBrokers (short_name) ON
            UPDATE
              CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF443707B0166D1F9C72757D19D151D1BF FOREIGN KEY (
                field_id, project_id, debitor_id,
                receivable_key
              ) REFERENCES ProjectParticipantFieldsData (
                field_id, project_id, musician_id,
                option_key
              )
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF443707B0D151D1BF FOREIGN KEY (field_id, receivable_key) REFERENCES ProjectParticipantFieldsDataOptions (field_id, `key`)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF2989F1FD FOREIGN KEY (invoice_id) REFERENCES Invoices (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF72757D19 FOREIGN KEY (debitor_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF166D1F9C72757D19 FOREIGN KEY (project_id, debitor_id) REFERENCES ProjectParticipants (project_id, musician_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              InvoiceItems
            ADD
              CONSTRAINT FK_670E0FCF8A034ED2 FOREIGN KEY (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC33DA3F86F FOREIGN KEY (originator_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC372757D19 FOREIGN KEY (debitor_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC3D5560045 FOREIGN KEY (sepa_transaction_id) REFERENCES SepaBulkTransactions (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC372757D192301E184 FOREIGN KEY (
                debitor_id, bank_account_sequence
              ) REFERENCES SepaBankAccounts (musician_id, sequence)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC372757D19544C02F9 FOREIGN KEY (
                debitor_id, debit_mandate_sequence
              ) REFERENCES SepaDebitMandates (musician_id, sequence)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC3166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC3166D1F9C72757D19 FOREIGN KEY (project_id, debitor_id) REFERENCES ProjectParticipants (project_id, musician_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC38A034ED2 FOREIGN KEY (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC397F6692F FOREIGN KEY (written_invoice_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC366FAD11 FOREIGN KEY (taxation_statutory_source_id) REFERENCES TaxationStatutorySources (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Invoices
            ADD
              CONSTRAINT FK_93594DC3A808B60B FOREIGN KEY (notification_message_id) REFERENCES SentEmails (message_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              MissingTranslations
            ADD
              CONSTRAINT FK_DBBA64EAD07ED992 FOREIGN KEY (translation_key_id) REFERENCES TranslationKeys (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              MusicianEmailAddresses
            ADD
              CONSTRAINT FK_13DF84F69523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              MusicianInstruments
            ADD
              CONSTRAINT FK_332855779523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              MusicianInstruments
            ADD
              CONSTRAINT FK_33285577CF11D9C FOREIGN KEY (instrument_id) REFERENCES Instruments (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              MusicianRowAccessTokens
            ADD
              CONSTRAINT FK_64C47A569523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              EncryptedFileOwners
            ADD
              CONSTRAINT FK_5697DE239523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              EncryptedFileOwners
            ADD
              CONSTRAINT FK_5697DE23EC15E76C FOREIGN KEY (encrypted_file_id) REFERENCES Files (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectApplications
            ADD
              CONSTRAINT FK_5F0E8E19166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectApplications
            ADD
              CONSTRAINT FK_5F0E8E199523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectEvents
            ADD
              CONSTRAINT FK_7E38FC8B166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectEvents
            ADD
              CONSTRAINT FK_7E38FC8BA79D8A87 FOREIGN KEY (absence_field_id) REFERENCES ProjectParticipantFields (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstrumentationNumbers
            ADD
              CONSTRAINT FK_D8939186166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstrumentationNumbers
            ADD
              CONSTRAINT FK_D8939186CF11D9C FOREIGN KEY (instrument_id) REFERENCES Instruments (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A6166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A69523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A6CF11D9C FOREIGN KEY (instrument_id) REFERENCES Instruments (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A6166D1F9C9523AA8A FOREIGN KEY (project_id, musician_id) REFERENCES ProjectParticipants (project_id, musician_id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A69523AA8ACF11D9C FOREIGN KEY (musician_id, instrument_id) REFERENCES MusicianInstruments (musician_id, instrument_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectInstruments
            ADD
              CONSTRAINT FK_436762A6166D1F9CCF11D9CE7FB583B FOREIGN KEY (project_id, instrument_id, voice) REFERENCES ProjectInstrumentationNumbers (project_id, instrument_id, voice)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFields
            ADD
              CONSTRAINT FK_F6F5D9C6166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFields
            ADD
              CONSTRAINT FK_F6F5D9C6BF396750F4510C3A FOREIGN KEY (id, default_value) REFERENCES ProjectParticipantFieldsDataOptions (field_id, `key`)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E9443707B0 FOREIGN KEY (field_id) REFERENCES ProjectParticipantFields (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E9166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E99523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E9443707B03CEE7BEE FOREIGN KEY (field_id, option_key) REFERENCES ProjectParticipantFieldsDataOptions (field_id, `key`)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E9166D1F9C9523AA8A FOREIGN KEY (project_id, musician_id) REFERENCES ProjectParticipants (project_id, musician_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsData
            ADD
              CONSTRAINT FK_E1AAA1E92423759C FOREIGN KEY (supporting_document_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipantFieldsDataOptions
            ADD
              CONSTRAINT FK_FA443FE443707B0 FOREIGN KEY (field_id) REFERENCES ProjectParticipantFields (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipants
            ADD
              CONSTRAINT FK_D9AE987B166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipants
            ADD
              CONSTRAINT FK_D9AE987B9523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectParticipants
            ADD
              CONSTRAINT FK_D9AE987BC6073910 FOREIGN KEY (database_documents_id) REFERENCES DatabaseStorages (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE2443707B0166D1F9C9523AA8AD151D1BF FOREIGN KEY (
                field_id, project_id, musician_id,
                receivable_key
              ) REFERENCES ProjectParticipantFieldsData (
                field_id, project_id, musician_id,
                option_key
              )
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE2443707B0D151D1BF FOREIGN KEY (field_id, receivable_key) REFERENCES ProjectParticipantFieldsDataOptions (field_id, `key`)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE2930D2644 FOREIGN KEY (composite_payment_id) REFERENCES CompositePayments (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE2166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE29523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE2166D1F9C9523AA8A FOREIGN KEY (project_id, musician_id) REFERENCES ProjectParticipants (project_id, musician_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectPayments
            ADD
              CONSTRAINT FK_F6372AE28A034ED2 FOREIGN KEY (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              ProjectWebPages
            ADD
              CONSTRAINT FK_EB77064F166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Projects
            ADD
              CONSTRAINT FK_A5E5D1F214CA24B1 FOREIGN KEY (
                financial_balance_documents_storage_id
              ) REFERENCES DatabaseStorages (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SentEmails
            ADD
              CONSTRAINT FK_80F49BA0166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SentEmails
            ADD
              CONSTRAINT FK_80F49BA01645DEA9 FOREIGN KEY (reference_id) REFERENCES SentEmails (message_id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SentEmails
            ADD
              CONSTRAINT FK_80F49BA0ED6D4895 FOREIGN KEY (sepa_bulk_transaction_id) REFERENCES SepaBulkTransactions (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaBankAccounts
            ADD
              CONSTRAINT FK_4F1F148B9523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaBulkTransactionData
            ADD
              CONSTRAINT FK_1EBA3E5BED6D4895 FOREIGN KEY (sepa_bulk_transaction_id) REFERENCES SepaBulkTransactions (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaBulkTransactionData
            ADD
              CONSTRAINT FK_1EBA3E5B4D73A4D4 FOREIGN KEY (database_storage_file_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaBulkTransactionBalancingData
            ADD
              CONSTRAINT FK_6EC2B172ED6D4895 FOREIGN KEY (sepa_bulk_transaction_id) REFERENCES SepaBulkTransactions (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaBulkTransactionBalancingData
            ADD
              CONSTRAINT FK_6EC2B1724D73A4D4 FOREIGN KEY (database_storage_file_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaDebitMandates
            ADD
              CONSTRAINT FK_1C500299523AA8A FOREIGN KEY (musician_id) REFERENCES Musicians (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaDebitMandates
            ADD
              CONSTRAINT FK_1C500299523AA8A2301E184 FOREIGN KEY (
                musician_id, bank_account_sequence
              ) REFERENCES SepaBankAccounts (musician_id, sequence)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaDebitMandates
            ADD
              CONSTRAINT FK_1C50029166D1F9C FOREIGN KEY (project_id) REFERENCES Projects (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              SepaDebitMandates
            ADD
              CONSTRAINT FK_1C50029D26EB11F FOREIGN KEY (written_mandate_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              TaxExemptionNotices
            ADD
              CONSTRAINT FK_6417EA3735D82D9 FOREIGN KEY (written_notice_id) REFERENCES DatabaseStorageDirEntries (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              TaxExemptionItems
            ADD
              CONSTRAINT FK_9D0F193734E7630B FOREIGN KEY (tax_exemption_notice_id) REFERENCES TaxExemptionNotices (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              TaxExemptionItems
            ADD
              CONSTRAINT FK_9D0F193766FAD11 FOREIGN KEY (taxation_statutory_source_id) REFERENCES TaxationStatutorySources (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              TranslationLocations
            ADD
              CONSTRAINT FK_F23942BBD07ED992 FOREIGN KEY (translation_key_id) REFERENCES TranslationKeys (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              Translations
            ADD
              CONSTRAINT FK_DE86017FD07ED992 FOREIGN KEY (translation_key_id) REFERENCES TranslationKeys (id) ON DELETE CASCADE
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              WebBrowserHistoryEntries
            ADD
              CONSTRAINT FK_2059233F5D83CC1 FOREIGN KEY (state_id) REFERENCES WebBrowserHistoryStates (id)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              WebBrowserHistoryEntries
            ADD
              CONSTRAINT FK_2059233F6AF7A95A FOREIGN KEY (data_hash) REFERENCES WebBrowserHistoryData (hash)
        SQL);
    $this->addSql(<<<'SQL'
            ALTER TABLE
              WebBrowserHistoryStates
            ADD
              CONSTRAINT FK_FD38B3C74CDC76F1D06B458A FOREIGN KEY (pos_state_id, pos_key) REFERENCES WebBrowserHistoryEntries (state_id, `key`)
        SQL);
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920CD5560045');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C9523AA8A2301E184');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C9523AA8A544C02F9');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C9B6CD002');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C166D1F9C');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C9523AA8A');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C166D1F9C9523AA8A');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C2423759C');
    $this->addSql('ALTER TABLE CompositePayments DROP FOREIGN KEY FK_65D9920C8A034ED2');
    $this->addSql('ALTER TABLE DatabaseStorageDirEntries DROP FOREIGN KEY FK_E123333D727ACA70');
    $this->addSql('ALTER TABLE DatabaseStorageDirEntries DROP FOREIGN KEY FK_E123333D93CB796C');
    $this->addSql('ALTER TABLE DatabaseStorages DROP FOREIGN KEY FK_3594ED2379066886');
    $this->addSql('ALTER TABLE DonationReceipts DROP FOREIGN KEY FK_AD46E7444DC1279C');
    $this->addSql('ALTER TABLE DonationReceipts DROP FOREIGN KEY FK_AD46E74434E7630B');
    $this->addSql('ALTER TABLE DonationReceipts DROP FOREIGN KEY FK_AD46E7442423759C');
    $this->addSql('ALTER TABLE DonationReceipts DROP FOREIGN KEY FK_AD46E744A808B60B');
    $this->addSql('ALTER TABLE EmailAttachments DROP FOREIGN KEY FK_199F0CDBE2F3C5D1');
    $this->addSql('ALTER TABLE FileData DROP FOREIGN KEY FK_969FA96893CB796C');
    $this->addSql('ALTER TABLE GeoCountries DROP FOREIGN KEY FK_7DF803716C569B466F2FFC');
    $this->addSql('ALTER TABLE GeoPostalCodeTranslations DROP FOREIGN KEY FK_BC664719E70E684F');
    $this->addSql('ALTER TABLE GeoStatesProvinces DROP FOREIGN KEY FK_40C5B1885A7049D0466F2FFC');
    $this->addSql('ALTER TABLE GnuCashAccounts DROP FOREIGN KEY FK_1C4A70F24F9CBEC7');
    $this->addSql('ALTER TABLE GnuCashAccounts DROP FOREIGN KEY FK_1C4A70F2168CF906');
    $this->addSql('ALTER TABLE GnuCashBooks DROP FOREIGN KEY FK_A26D411FD96A93A7');
    $this->addSql('ALTER TABLE GnuCashBooks DROP FOREIGN KEY FK_A26D411FA501DD19');
    $this->addSql('ALTER TABLE GnuCashSplits DROP FOREIGN KEY FK_E2EE9395D252EC5E');
    $this->addSql('ALTER TABLE GnuCashSplits DROP FOREIGN KEY FK_E2EE9395A7FC4818');
    $this->addSql('ALTER TABLE GnuCashTransactions DROP FOREIGN KEY FK_403125FA1D88CC6');
    $this->addSql('ALTER TABLE InstrumentInsurances DROP FOREIGN KEY FK_B9BA7EFA948FBE6');
    $this->addSql('ALTER TABLE InstrumentInsurances DROP FOREIGN KEY FK_B9BA7EFDF95C1F8');
    $this->addSql('ALTER TABLE InstrumentInsurances DROP FOREIGN KEY FK_B9BA7EF9D7A36FA');
    $this->addSql('ALTER TABLE InstrumentInsurances DROP FOREIGN KEY FK_B9BA7EF6CC064FCBD069886');
    $this->addSql('ALTER TABLE instrument_instrument_family DROP FOREIGN KEY FK_2C15852ACF11D9C');
    $this->addSql('ALTER TABLE instrument_instrument_family DROP FOREIGN KEY FK_2C15852AB4F8CF5C');
    $this->addSql('ALTER TABLE InsuranceRates DROP FOREIGN KEY FK_CB75C3526CC064FC');
    $this->addSql('ALTER TABLE InsuranceRates DROP FOREIGN KEY FK_CB75C3526CC064FC_ONUPDATE_CASCADE');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF443707B0166D1F9C72757D19D151D1BF');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF443707B0D151D1BF');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF2989F1FD');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF166D1F9C');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF72757D19');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF166D1F9C72757D19');
    $this->addSql('ALTER TABLE InvoiceItems DROP FOREIGN KEY FK_670E0FCF8A034ED2');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC33DA3F86F');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC372757D19');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC3D5560045');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC372757D192301E184');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC372757D19544C02F9');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC3166D1F9C');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC3166D1F9C72757D19');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC38A034ED2');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC397F6692F');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC366FAD11');
    $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_93594DC3A808B60B');
    $this->addSql('ALTER TABLE MissingTranslations DROP FOREIGN KEY FK_DBBA64EAD07ED992');
    $this->addSql('ALTER TABLE MusicianEmailAddresses DROP FOREIGN KEY FK_13DF84F69523AA8A');
    $this->addSql('ALTER TABLE MusicianInstruments DROP FOREIGN KEY FK_332855779523AA8A');
    $this->addSql('ALTER TABLE MusicianInstruments DROP FOREIGN KEY FK_33285577CF11D9C');
    $this->addSql('ALTER TABLE MusicianRowAccessTokens DROP FOREIGN KEY FK_64C47A569523AA8A');
    $this->addSql('ALTER TABLE EncryptedFileOwners DROP FOREIGN KEY FK_5697DE239523AA8A');
    $this->addSql('ALTER TABLE EncryptedFileOwners DROP FOREIGN KEY FK_5697DE23EC15E76C');
    $this->addSql('ALTER TABLE ProjectApplications DROP FOREIGN KEY FK_5F0E8E19166D1F9C');
    $this->addSql('ALTER TABLE ProjectApplications DROP FOREIGN KEY FK_5F0E8E199523AA8A');
    $this->addSql('ALTER TABLE ProjectEvents DROP FOREIGN KEY FK_7E38FC8B166D1F9C');
    $this->addSql('ALTER TABLE ProjectEvents DROP FOREIGN KEY FK_7E38FC8BA79D8A87');
    $this->addSql('ALTER TABLE ProjectInstrumentationNumbers DROP FOREIGN KEY FK_D8939186166D1F9C');
    $this->addSql('ALTER TABLE ProjectInstrumentationNumbers DROP FOREIGN KEY FK_D8939186CF11D9C');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A6166D1F9C');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A69523AA8A');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A6CF11D9C');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A6166D1F9C9523AA8A');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A69523AA8ACF11D9C');
    $this->addSql('ALTER TABLE ProjectInstruments DROP FOREIGN KEY FK_436762A6166D1F9CCF11D9CE7FB583B');
    $this->addSql('ALTER TABLE ProjectParticipantFields DROP FOREIGN KEY FK_F6F5D9C6166D1F9C');
    $this->addSql('ALTER TABLE ProjectParticipantFields DROP FOREIGN KEY FK_F6F5D9C6BF396750F4510C3A');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E9443707B0');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E9166D1F9C');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E99523AA8A');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E9443707B03CEE7BEE');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E9166D1F9C9523AA8A');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData DROP FOREIGN KEY FK_E1AAA1E92423759C');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsDataOptions DROP FOREIGN KEY FK_FA443FE443707B0');
    $this->addSql('ALTER TABLE ProjectParticipants DROP FOREIGN KEY FK_D9AE987B166D1F9C');
    $this->addSql('ALTER TABLE ProjectParticipants DROP FOREIGN KEY FK_D9AE987B9523AA8A');
    $this->addSql('ALTER TABLE ProjectParticipants DROP FOREIGN KEY FK_D9AE987BC6073910');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE2443707B0166D1F9C9523AA8AD151D1BF');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE2443707B0D151D1BF');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE2930D2644');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE2166D1F9C');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE29523AA8A');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE2166D1F9C9523AA8A');
    $this->addSql('ALTER TABLE ProjectPayments DROP FOREIGN KEY FK_F6372AE28A034ED2');
    $this->addSql('ALTER TABLE ProjectWebPages DROP FOREIGN KEY FK_EB77064F166D1F9C');
    $this->addSql('ALTER TABLE Projects DROP FOREIGN KEY FK_A5E5D1F214CA24B1');
    $this->addSql('ALTER TABLE SentEmails DROP FOREIGN KEY FK_80F49BA0166D1F9C');
    $this->addSql('ALTER TABLE SentEmails DROP FOREIGN KEY FK_80F49BA01645DEA9');
    $this->addSql('ALTER TABLE SentEmails DROP FOREIGN KEY FK_80F49BA0ED6D4895');
    $this->addSql('ALTER TABLE SepaBankAccounts DROP FOREIGN KEY FK_4F1F148B9523AA8A');
    $this->addSql('ALTER TABLE SepaBulkTransactionData DROP FOREIGN KEY FK_1EBA3E5BED6D4895');
    $this->addSql('ALTER TABLE SepaBulkTransactionData DROP FOREIGN KEY FK_1EBA3E5B4D73A4D4');
    $this->addSql('ALTER TABLE SepaBulkTransactionBalancingData DROP FOREIGN KEY FK_6EC2B172ED6D4895');
    $this->addSql('ALTER TABLE SepaBulkTransactionBalancingData DROP FOREIGN KEY FK_6EC2B1724D73A4D4');
    $this->addSql('ALTER TABLE SepaDebitMandates DROP FOREIGN KEY FK_1C500299523AA8A');
    $this->addSql('ALTER TABLE SepaDebitMandates DROP FOREIGN KEY FK_1C500299523AA8A2301E184');
    $this->addSql('ALTER TABLE SepaDebitMandates DROP FOREIGN KEY FK_1C50029166D1F9C');
    $this->addSql('ALTER TABLE SepaDebitMandates DROP FOREIGN KEY FK_1C50029D26EB11F');
    $this->addSql('ALTER TABLE TaxExemptionNotices DROP FOREIGN KEY FK_6417EA3735D82D9');
    $this->addSql('ALTER TABLE TaxExemptionItems DROP FOREIGN KEY FK_9D0F193734E7630B');
    $this->addSql('ALTER TABLE TaxExemptionItems DROP FOREIGN KEY FK_9D0F193766FAD11');
    $this->addSql('ALTER TABLE TranslationLocations DROP FOREIGN KEY FK_F23942BBD07ED992');
    $this->addSql('ALTER TABLE Translations DROP FOREIGN KEY FK_DE86017FD07ED992');
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP FOREIGN KEY FK_2059233F5D83CC1');
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP FOREIGN KEY FK_2059233F6AF7A95A');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates DROP FOREIGN KEY FK_FD38B3C74CDC76F1D06B458A');
    $this->addSql('DROP TABLE ChangeLog');
    $this->addSql('DROP TABLE CompositePayments');
    $this->addSql('DROP TABLE DatabaseStorageDirEntries');
    $this->addSql('DROP TABLE DatabaseStorages');
    $this->addSql('DROP TABLE DonationReceipts');
    $this->addSql('DROP TABLE EmailAttachments');
    $this->addSql('DROP TABLE EmailDrafts');
    $this->addSql('DROP TABLE EmailTemplates');
    $this->addSql('DROP TABLE ExtLogEntries');
    $this->addSql('DROP TABLE FileData');
    $this->addSql('DROP TABLE Files');
    $this->addSql('DROP TABLE GeoContinents');
    $this->addSql('DROP TABLE GeoCountries');
    $this->addSql('DROP TABLE GeoPostalCodeTranslations');
    $this->addSql('DROP TABLE GeoPostalCodes');
    $this->addSql('DROP TABLE GeoStatesProvinces');
    $this->addSql('DROP TABLE GnuCashAccounts');
    $this->addSql('DROP TABLE GnuCashBooks');
    $this->addSql('DROP TABLE GnuCashCommodities');
    $this->addSql('DROP TABLE GnuCashSlots');
    $this->addSql('DROP TABLE GnuCashSplits');
    $this->addSql('DROP TABLE GnuCashTransactions');
    $this->addSql('DROP TABLE InstrumentFamilies');
    $this->addSql('DROP TABLE InstrumentInsurances');
    $this->addSql('DROP TABLE Instruments');
    $this->addSql('DROP TABLE instrument_instrument_family');
    $this->addSql('DROP TABLE InsuranceBrokers');
    $this->addSql('DROP TABLE InsuranceRates');
    $this->addSql('DROP TABLE InvoiceItems');
    $this->addSql('DROP TABLE Invoices');
    $this->addSql('DROP TABLE Migrations');
    $this->addSql('DROP TABLE MissingTranslations');
    $this->addSql('DROP TABLE MusicianEmailAddresses');
    $this->addSql('DROP TABLE MusicianInstruments');
    $this->addSql('DROP TABLE MusicianRowAccessTokens');
    $this->addSql('DROP TABLE Musicians');
    $this->addSql('DROP TABLE EncryptedFileOwners');
    $this->addSql('DROP TABLE ProjectApplications');
    $this->addSql('DROP TABLE ProjectEvents');
    $this->addSql('DROP TABLE ProjectInstrumentationNumbers');
    $this->addSql('DROP TABLE ProjectInstruments');
    $this->addSql('DROP TABLE ProjectParticipantFields');
    $this->addSql('DROP TABLE ProjectParticipantFieldsData');
    $this->addSql('DROP TABLE ProjectParticipantFieldsDataOptions');
    $this->addSql('DROP TABLE ProjectParticipants');
    $this->addSql('DROP TABLE ProjectPayments');
    $this->addSql('DROP TABLE ProjectWebPages');
    $this->addSql('DROP TABLE Projects');
    $this->addSql('DROP TABLE SentEmails');
    $this->addSql('DROP TABLE SepaBankAccounts');
    $this->addSql('DROP TABLE SepaBulkTransactions');
    $this->addSql('DROP TABLE SepaBulkTransactionData');
    $this->addSql('DROP TABLE SepaBulkTransactionBalancingData');
    $this->addSql('DROP TABLE SepaDebitMandates');
    $this->addSql('DROP TABLE TableFieldTranslations');
    $this->addSql('DROP TABLE TaxExemptionNotices');
    $this->addSql('DROP TABLE TaxExemptionItems');
    $this->addSql('DROP TABLE TaxationStatutorySources');
    $this->addSql('DROP TABLE TranslationKeys');
    $this->addSql('DROP TABLE TranslationLocations');
    $this->addSql('DROP TABLE Translations');
    $this->addSql('DROP TABLE WebBrowserHistoryData');
    $this->addSql('DROP TABLE WebBrowserHistoryEntries');
    $this->addSql('DROP TABLE WebBrowserHistoryStates');
  }
}
