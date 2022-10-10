<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the id of a mailing list.
 */
class SanitizeProjectBalanceDocuments extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE Projects ADD COLUMN IF NOT EXISTS financial_balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE Projects ADD CONSTRAINT FK_A5E5D1F2ABE5D3E5 FOREIGN KEY IF NOT EXISTS (financial_balance_documents_folder_id) REFERENCES DatabaseStorageDirectories (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_A5E5D1F2ABE5D3E5 ON Projects (financial_balance_documents_folder_id)',

      'ALTER TABLE CompositePayments ADD COLUMN IF NOT EXISTS project_balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE CompositePayments ADD CONSTRAINT FK_65D9920C5323D5BB FOREIGN KEY IF NOT EXISTS (project_balance_documents_folder_id) REFERENCES DatabaseStorageDirectories (id)',
      'CREATE INDEX IF NOT EXISTS IDX_65D9920C5323D5BB ON CompositePayments (project_balance_documents_folder_id)',

      'ALTER TABLE ProjectPayments ADD COLUMN IF NOT EXISTS project_balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE ProjectPayments ADD CONSTRAINT FK_F6372AE25323D5BB FOREIGN KEY IF NOT EXISTS (project_balance_documents_folder_id) REFERENCES DatabaseStorageDirectories (id)',
      'CREATE INDEX IF NOT EXISTS IDX_F6372AE25323D5BB ON ProjectPayments (project_balance_documents_folder_id)',
    ],
    self::TRANSACTIONAL => [
      // Create the root nodes
      'INSERT IGNORE INTO DatabaseStorageDirectories (`id`, `storage_id`, `name`, `updated`)
SELECT p.id,
  CONCAT("/finance/balances/projects/", p.name),
  "",
  p.financial_balance_supporting_documents_changed
FROM Projects p
LEFT JOIN ProjectBalanceSupportingDocuments pb
ON pb.project_id = p.id
WHERE pb.project_id IS NOT NULL
GROUP BY p.id',
      // Create the subfolders
      'INSERT IGNORE INTO DatabaseStorageDirectories (`id`, `parent_id`, `name`, `updated`)
SELECT 1000 * p.id + pb.sequence,
  p.id,
  CONCAT_WS("-", p.name, LPAD(pb.sequence, 3, "0")),
  COALESCE(pb.documents_changed, GREATEST(pb.created, pb.updated))
FROM ProjectBalanceSupportingDocuments pb
LEFT JOIN Projects p
ON pb.project_id = p.id',
      //Join table with actual documents
      'INSERT IGNORE INTO database_storage_directory_encrypted_file (database_storage_directory_id, encrypted_file_id)
SELECT 1000 * jt.project_id + jt.sequence, jt.encrypted_file_id
FROM project_balance_supporting_document_encrypted_file jt',
      // Link the supporting document folders to the projects
      'UPDATE Projects p
LEFT JOIN DatabaseStorageDirectories dsd
ON dsd.id = p.id
SET p.financial_balance_documents_folder_id = dsd.id
WHERE dsd.id IS NOT NULL',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Cleanup supporting documents for the financial balance of projects');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
