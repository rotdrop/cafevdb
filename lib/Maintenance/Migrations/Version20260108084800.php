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
final class Version20260108084800 extends AbstractMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Set updated and created to the current time on the database level.');
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
    $this->addSql('ALTER TABLE CompositePayments CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE DatabaseStorageDirEntries CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME, CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE DonationReceipts CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE EmailAttachments CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE EmailDrafts CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE EmailTemplates CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE Files CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE GeoPostalCodes CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME, CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE InstrumentInsurances CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE InvoiceItems CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE Invoices CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE Migrations CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE MusicianEmailAddresses CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE MusicianInstruments CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE MusicianRowAccessTokens CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE Musicians CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME, CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE ProjectApplications CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsDataOptions CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE ProjectParticipants CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE ProjectPayments CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE Projects CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME, CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE SentEmails CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE SepaBankAccounts CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE SepaBulkTransactions CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE SepaDebitMandates CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE TaxExemptionNotices CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE TaxationStatutorySources CHANGE created created DATETIME(6) DEFAULT CURRENT_TIME, CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates CHANGE updated updated DATETIME(6) DEFAULT CURRENT_TIME');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE CompositePayments CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE DatabaseStorageDirEntries CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE DonationReceipts CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE EmailAttachments CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE EmailDrafts CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE EmailTemplates CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE Files CHANGE created created DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE GeoPostalCodes CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE InstrumentInsurances CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE InvoiceItems CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE Invoices CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE Migrations CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE MusicianEmailAddresses CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE MusicianInstruments CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE MusicianRowAccessTokens CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE Musicians CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE ProjectApplications CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsData CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsDataOptions CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE ProjectParticipants CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE ProjectPayments CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE Projects CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE SentEmails CHANGE created created DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE SepaBankAccounts CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE SepaBulkTransactions CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE SepaDebitMandates CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE TaxationStatutorySources CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE TaxExemptionNotices CHANGE created created DATETIME(6) DEFAULT NULL, CHANGE updated updated DATETIME(6) DEFAULT NULL');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates CHANGE updated updated DATETIME(6) DEFAULT NULL');
  }
}
