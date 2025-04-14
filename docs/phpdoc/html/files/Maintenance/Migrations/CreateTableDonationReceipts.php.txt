<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
 * Manage donation receipts.
 */
class CreateTableDonationReceipts extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS DonationReceipts (
   id INT AUTO_INCREMENT NOT NULL,
   donation_id INT NOT NULL,
   tax_exemption_notice_id INT DEFAULT NULL,
   supporting_document_id INT DEFAULT NULL,
   notification_message_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
   mailing_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
   created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
   updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
   deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
   UNIQUE INDEX UNIQ_AD46E7444DC1279C (donation_id),
   INDEX IDX_AD46E74434E7630B (tax_exemption_notice_id),
   UNIQUE INDEX UNIQ_AD46E7442423759C (supporting_document_id),
   UNIQUE INDEX UNIQ_AD46E744A808B60B (notification_message_id),
   PRIMARY KEY(id)
)",
      "ALTER TABLE DonationReceipts
   ADD CONSTRAINT FK_AD46E7444DC1279C
   FOREIGN KEY IF NOT EXISTS (donation_id)
   REFERENCES CompositePayments (id)",
      "ALTER TABLE DonationReceipts
   ADD CONSTRAINT FK_AD46E74434E7630B
   FOREIGN KEY IF NOT EXISTS (tax_exemption_notice_id)
   REFERENCES TaxExemptionNotices (id)",
      "ALTER TABLE DonationReceipts
   ADD CONSTRAINT FK_AD46E7442423759C
   FOREIGN KEY IF NOT EXISTS (supporting_document_id)
   REFERENCES DatabaseStorageDirEntries (id)",
      "ALTER TABLE DonationReceipts
   ADD CONSTRAINT FK_AD46E744A808B60B
   FOREIGN KEY IF NOT EXISTS (notification_message_id)
   REFERENCES SentEmails (message_id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table for recording sent out donation receipts.');
  }
}
