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

namespace OCA\CAFEVDB\Maintenance\Migrations\Legacy;

/**
 * Do not use floats for monetary values and other exact fractions.
 */
class SepaBulkTransactionsBalancingData extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS SepaBulkTransactionBalancingData (
  sepa_bulk_transaction_id INT NOT NULL,
  database_storage_file_id INT NOT NULL,
  INDEX IDX_6EC2B172ED6D4895 (sepa_bulk_transaction_id),
  UNIQUE INDEX UNIQ_6EC2B1724D73A4D4 (database_storage_file_id),
  PRIMARY KEY(sepa_bulk_transaction_id, database_storage_file_id)
)",
      "ALTER TABLE SepaBulkTransactionBalancingData ADD CONSTRAINT FK_6EC2B172ED6D4895 FOREIGN KEY IF NOT EXISTS
   (sepa_bulk_transaction_id)
   REFERENCES SepaBulkTransactions (id)
   ON DELETE CASCADE",
      "ALTER TABLE SepaBulkTransactionBalancingData ADD CONSTRAINT FK_6EC2B1724D73A4D4 FOREIGN KEY (database_storage_file_id) REFERENCES DatabaseStorageDirEntries (id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add balancing items export data for bank bulk transactions.');
  }
}
