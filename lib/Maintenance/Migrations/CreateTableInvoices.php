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

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the id of a mailing list.
 */
class CreateTableInvoices extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS Invoices (
  id VARCHAR(255) NOT NULL,
  debitor_id INT DEFAULT NULL,
  originator_id INT DEFAULT NULL,
  written_invoice_id INT DEFAULT NULL,
  notification_message_id VARCHAR(256) DEFAULT NULL COLLATE `ascii_bin`,
  due_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
  amount NUMERIC(7, 2) NOT NULL,
  purpose VARCHAR(4096) NOT NULL,
  created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX IDX_93594DC372757D19 (debitor_id),
  INDEX IDX_93594DC33DA3F86F (originator_id),
  UNIQUE INDEX UNIQ_93594DC397F6692F (written_invoice_id),
  UNIQUE INDEX UNIQ_93594DC3A808B60B (notification_message_id),
  PRIMARY KEY(id)
)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC397F6692F FOREIGN KEY IF NOT EXISTS (written_invoice_id) REFERENCES DatabaseStorageDirEntries (id)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC3A808B60B FOREIGN KEY IF NOT EXISTS (notification_message_id) REFERENCES SentEmails (message_id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table for recording issued invoices.');
  }
}
