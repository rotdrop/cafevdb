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
 * Remember the id of a mailing list.
 */
class CreateTableInvoicesV2 extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "DROP TABLE IF EXISTS invoice_receivable",
      "DROP TABLE IF EXISTS InvoiceItems",
      "DROP TABLE IF EXISTS Invoices",
      "CREATE TABLE Invoices(
    id INT AUTO_INCREMENT NOT NULL,
    originator_id INT DEFAULT NULL,
    debitor_id INT NOT NULL,
    sepa_transaction_id INT DEFAULT NULL,
    bank_account_sequence INT DEFAULT NULL,
    debit_mandate_sequence INT DEFAULT NULL,
    project_id INT NOT NULL,
    balance_documents_folder_id INT DEFAULT NULL,
    written_invoice_id INT DEFAULT NULL,
    notification_email_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
    amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
    due_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
    balanced_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
    invoice_number VARCHAR(255) NOT NULL,
    subject VARCHAR(1024) NOT NULL,
    created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_93594DC33DA3F86F(originator_id),
    INDEX IDX_93594DC372757D19(debitor_id),
    INDEX IDX_93594DC3D5560045(sepa_transaction_id),
    INDEX IDX_93594DC372757D192301E184(
        debitor_id,
        bank_account_sequence
    ),
    INDEX IDX_93594DC372757D19544C02F9(
        debitor_id,
        debit_mandate_sequence
    ),
    INDEX IDX_93594DC3166D1F9C(project_id),
    INDEX IDX_93594DC3166D1F9C72757D19(project_id, debitor_id),
    INDEX IDX_93594DC38A034ED2(balance_documents_folder_id),
    UNIQUE INDEX UNIQ_93594DC397F6692F(written_invoice_id),
    UNIQUE INDEX UNIQ_93594DC3FD22F96C(notification_email_id),
    UNIQUE INDEX UNIQ_93594DC32DA68207(invoice_number),
    PRIMARY KEY(id)
)",
      "CREATE TABLE InvoiceItems(
    id INT AUTO_INCREMENT NOT NULL,
    field_id INT NOT NULL,
    project_id INT NOT NULL,
    debitor_id INT NOT NULL,
    receivable_key BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
    invoice_id INT NOT NULL,
    balance_documents_folder_id INT DEFAULT NULL,
    amount NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
    SUBJECT VARCHAR(1024) NOT NULL,
    created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_670E0FCF443707B0166D1F9C72757D19D151D1BF(
        field_id,
        project_id,
        debitor_id,
        receivable_key
    ),
    INDEX IDX_670E0FCF443707B0D151D1BF(field_id, receivable_key),
    INDEX IDX_670E0FCF2989F1FD(invoice_id),
    INDEX IDX_670E0FCF166D1F9C(project_id),
    INDEX IDX_670E0FCF72757D19(debitor_id),
    INDEX IDX_670E0FCF166D1F9C72757D19(project_id, debitor_id),
    INDEX IDX_670E0FCF8A034ED2(balance_documents_folder_id),
    PRIMARY KEY(id)
)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC33DA3F86F FOREIGN KEY(originator_id) REFERENCES Musicians(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC372757D19 FOREIGN KEY(debitor_id) REFERENCES Musicians(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC3D5560045 FOREIGN KEY(sepa_transaction_id) REFERENCES SepaBulkTransactions(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC372757D192301E184 FOREIGN KEY(
        debitor_id,
        bank_account_sequence
    ) REFERENCES SepaBankAccounts(musician_id, SEQUENCE)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC372757D19544C02F9 FOREIGN KEY(
        debitor_id,
        debit_mandate_sequence
    ) REFERENCES SepaDebitMandates(musician_id, SEQUENCE)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC3166D1F9C FOREIGN KEY(project_id) REFERENCES Projects(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC3166D1F9C72757D19 FOREIGN KEY(project_id, debitor_id) REFERENCES ProjectParticipants(project_id, musician_id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC38A034ED2 FOREIGN KEY(balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC397F6692F FOREIGN KEY(written_invoice_id) REFERENCES DatabaseStorageDirEntries(id)",
      "ALTER TABLE
    Invoices ADD CONSTRAINT FK_93594DC3FD22F96C FOREIGN KEY(notification_email_id) REFERENCES SentEmails(message_id)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF443707B0166D1F9C72757D19D151D1BF FOREIGN KEY(
        field_id,
        project_id,
        debitor_id,
        receivable_key
    ) REFERENCES ProjectParticipantFieldsData(
        field_id,
        project_id,
        musician_id,
        option_key
    )",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF443707B0D151D1BF FOREIGN KEY(field_id, receivable_key) REFERENCES ProjectParticipantFieldsDataOptions(field_id, `key`)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF2989F1FD FOREIGN KEY(invoice_id) REFERENCES Invoices(id)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF166D1F9C FOREIGN KEY(project_id) REFERENCES Projects(id)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF72757D19 FOREIGN KEY(debitor_id) REFERENCES Musicians(id)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF166D1F9C72757D19 FOREIGN KEY(project_id, debitor_id) REFERENCES ProjectParticipants(project_id, musician_id)",
      "ALTER TABLE
    InvoiceItems ADD CONSTRAINT FK_670E0FCF8A034ED2 FOREIGN KEY(balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries(id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Drop the old unsued "Invoices" and create a new one with a new design.');
  }
}
