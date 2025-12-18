<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * DBAL 4.0.0 has removed the DC2Type comments, account for that.
 */
class DBALFour extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE EmailDrafts
         CHANGE  data data JSON NOT NULL COMMENT 'Message Data Without Attachments',
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ExtLogEntries
         CHANGE  data data LONGTEXT DEFAULT NULL",
      "ALTER TABLE SepaBulkTransactions
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  submission_deadline submission_deadline DATETIME(6) NOT NULL,
         CHANGE  submit_date submit_date DATETIME(6) DEFAULT NULL,
         CHANGE  due_date due_date DATETIME(6) NOT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  sepa_transaction sepa_transaction enum('debit_note','bank_transfer') NOT NULL COMMENT 'enum(debit_note,bank_transfer)',
         CHANGE  pre_notification_deadline pre_notification_deadline DATETIME(6) DEFAULT NULL",
      "ALTER TABLE Files
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  type type enum('generic','image','encrypted') NOT NULL COMMENT 'enum(generic,image,encrypted)'",
      "ALTER TABLE GeoPostalCodes
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL",
      "ALTER TABLE EmailAttachments
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE InvoiceItems
         CHANGE  receivable_key receivable_key BINARY(16) NOT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE SentEmails
         CHANGE  created created DATETIME(6) DEFAULT NULL",
      "ALTER TABLE DatabaseStorageDirEntries
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  type type enum('generic','folder','file') NOT NULL COMMENT 'enum(generic,folder,file)'",
      "ALTER TABLE DonationReceipts
         CHANGE  mailing_date mailing_date DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE GnuCashSplits
         CHANGE  reconcile_date reconcile_date DATETIME(6) DEFAULT NULL",
      "ALTER TABLE GnuCashTransactions
         CHANGE  post_date post_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL,
         CHANGE  enter_date enter_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL",
      "ALTER TABLE FileData
         CHANGE  type type enum('generic','image','encrypted') NOT NULL COMMENT 'enum(generic,image,encrypted)'",
      "ALTER TABLE Migrations
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE MusicianEmailAddresses
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE MusicianRowAccessTokens
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ProjectApplications
         CHANGE  data data JSON DEFAULT '{}' NOT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE TaxationStatutorySources
         CHANGE  tax_type tax_type enum('corporate income tax','sales tax','trade tax','VAT','insurance tax')
           DEFAULT 'corporate income tax' NOT NULL
           COMMENT 'enum(corporate income tax,sales tax,trade tax,VAT,insurance tax)',
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ProjectParticipantFieldsData
         CHANGE  option_key option_key BINARY(16) NOT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE EmailTemplates
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE Instruments
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE InstrumentFamilies
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE MusicianInstruments
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE WebBrowserHistoryStates
         CHANGE  created created DATETIME(6) NOT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ProjectParticipantFieldsDataOptions
         CHANGE  `key` `key` BINARY(16) NOT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE GnuCashSlots
         CHANGE  timespec_val timespec_val DATETIME(6) DEFAULT NULL,
         CHANGE  gdate_val gdate_val DATETIME(6) DEFAULT NULL",
      "ALTER TABLE InstrumentInsurances
         CHANGE  geographical_scope geographical_scope enum('Domestic','Continent','Germany','Europe','World') NOT NULL COMMENT 'enum(Domestic,Continent,Germany,Europe,World)',
         CHANGE  start_of_insurance start_of_insurance DATETIME(6) NOT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE Musicians
         CHANGE  birthday birthday DATETIME(6) DEFAULT NULL,
         CHANGE  default_participation_status default_participation_status
           enum('associated','conductor','passive','regular','soloist','temporary') DEFAULT 'regular'
           NOT NULL
           COMMENT 'enum(associated,conductor,passive,regular,soloist,temporary)',
         CHANGE  uuid uuid BINARY(16) NOT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  gender gender enum('male','female','diverse') DEFAULT NULL COMMENT 'enum(male,female,diverse)'",
      "ALTER TABLE ProjectParticipants
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  participation_status participation_status
           enum('associated','conductor','passive','regular','soloist','temporary')
           DEFAULT 'regular'
           NOT NULL
           COMMENT 'enum(associated,conductor,passive,regular,soloist,temporary)'",
      "ALTER TABLE ProjectPayments
         CHANGE  receivable_key receivable_key BINARY(16) NOT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE SepaBankAccounts
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE SepaDebitMandates
         CHANGE  mandate_date mandate_date DATETIME(6) DEFAULT NULL,
         CHANGE  last_used_date last_used_date DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE Projects
         CHANGE  type type enum('temporary','permanent','template') DEFAULT 'temporary' NOT NULL COMMENT 'enum(temporary,permanent,template)',
         CHANGE  registration_start_date registration_start_date DATETIME(6) DEFAULT NULL,
         CHANGE  registration_deadline registration_deadline DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ProjectEvents
         CHANGE  type type enum('VEVENT','VTODO','VJOURNAL','VCARD') NOT NULL COMMENT 'enum(VEVENT,VTODO,VJOURNAL,VCARD)',
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  series_uid series_uid BINARY(16) DEFAULT NULL",
      "ALTER TABLE ChangeLog
         CHANGE  updated updated DATETIME(6) NOT NULL",
      "ALTER TABLE CompositePayments
         CHANGE  date_of_receipt date_of_receipt DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL",
      "ALTER TABLE ProjectParticipantFields
         CHANGE  data_type data_type
           enum('boolean','cloud-file','cloud-folder','date','datetime','db-file','float','html','integer','liabilities','receivables','text')
           DEFAULT 'text'
           NOT NULL
           COMMENT 'enum(boolean,cloud-file,cloud-folder,date,datetime,db-file,float,html,integer,liabilities,receivables,text)',
         CHANGE  multiplicity multiplicity
           enum('simple','single','multiple','parallel','recurring','groupofpeople','groupsofpeople')
           NOT NULL
           COMMENT 'enum(simple,single,multiple,parallel,recurring,groupofpeople,groupsofpeople)',
         CHANGE  default_value default_value BINARY(16) DEFAULT NULL,
         CHANGE  due_date due_date DATETIME(6) DEFAULT NULL COMMENT 'Due-date for financial fields.',
         CHANGE  deposit_due_date deposit_due_date DATETIME(6) DEFAULT NULL COMMENT 'Due-date of deposit for financial fields.',
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  participant_access participant_access enum('none','read','read-write') DEFAULT 'none' NOT NULL COMMENT 'enum(none,read,read-write)',
         CHANGE  participation_context participation_context
           enum('associates','participants','unrestricted')
           DEFAULT 'unrestricted'
           NOT NULL
           COMMENT 'enum(associates,participants,unrestricted)'",
      "ALTER TABLE InsuranceRates DROP FOREIGN KEY `FK_CB75C3526CC064FC`",
      "ALTER TABLE InsuranceRates
         CHANGE  geographical_scope geographical_scope
           enum('Domestic','Continent','Germany','Europe','World')
           DEFAULT 'Germany'
           NOT NULL
           COMMENT 'enum(Domestic,Continent,Germany,Europe,World)',
         CHANGE  due_date due_date DATETIME(6) DEFAULT NULL COMMENT 'start of the yearly insurance period'",
      "ALTER TABLE InsuranceRates ADD CONSTRAINT FK_CB75C3526CC064FC FOREIGN KEY (broker_id) REFERENCES InsuranceBrokers (short_name)",
      "ALTER TABLE Invoices
         CHANGE  due_date due_date DATETIME(6) DEFAULT NULL,
         CHANGE  balanced_date balanced_date DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL,
         CHANGE  invoice_date invoice_date DATETIME(6) DEFAULT CURRENT_DATE NOT NULL",
      "ALTER TABLE TaxExemptionNotices
         CHANGE  date_issued date_issued DATETIME(6) DEFAULT NULL,
         CHANGE  created created DATETIME(6) DEFAULT NULL,
         CHANGE  updated updated DATETIME(6) DEFAULT NULL,
         CHANGE  deleted deleted DATETIME(6) DEFAULT NULL",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('DBAL has removed DC2Type comments, update the tables accordingly.');
  }
}
