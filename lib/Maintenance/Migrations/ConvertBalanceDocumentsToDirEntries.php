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
class ConvertBalanceDocumentsToDirEntries extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE Projects ADD COLUMN IF NOT EXISTS financial_balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE Projects DROP FOREIGN KEY IF EXISTS FK_A5E5D1F2ABE5D3E5',
      'ALTER TABLE Projects ADD CONSTRAINT FK_A5E5D1F2ABE5D3E5 FOREIGN KEY IF NOT EXISTS (financial_balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_A5E5D1F2ABE5D3E5 ON Projects (financial_balance_documents_folder_id)',

      'ALTER TABLE CompositePayments ADD COLUMN IF NOT EXISTS balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE CompositePayments DROP FOREIGN KEY IF EXISTS FK_65D9920C8A034ED2',
      'ALTER TABLE CompositePayments ADD CONSTRAINT FK_65D9920C8A034ED2 FOREIGN KEY IF NOT EXISTS (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)',
      'CREATE INDEX IF NOT EXISTS IDX_65D9920C8A034ED2 ON CompositePayments (balance_documents_folder_id)',

      'ALTER TABLE ProjectPayments ADD COLUMN IF NOT EXISTS balance_documents_folder_id INT DEFAULT NULL',
      'ALTER TABLE ProjectPayments DROP FOREIGN KEY IF EXISTS FK_F6372AE28A034ED2',
      'ALTER TABLE ProjectPayments ADD CONSTRAINT FK_F6372AE28A034ED2 FOREIGN KEY IF NOT EXISTS (balance_documents_folder_id) REFERENCES DatabaseStorageDirEntries (id)',
      'CREATE INDEX IF NOT EXISTS IDX_F6372AE28A034ED2 ON ProjectPayments (balance_documents_folder_id)',
    ],
    self::TRANSACTIONAL => [
      // Create the root nodes
      'INSERT IGNORE INTO DatabaseStorageDirEntries (`id`, `name`, `updated`, `created`, `type`)
SELECT p.id,
  "",
  p.financial_balance_supporting_documents_changed,
  MIN(pb.created),
  "folder"
FROM Projects p
LEFT JOIN ProjectBalanceSupportingDocuments pb
ON pb.project_id = p.id
WHERE pb.project_id IS NOT NULL
GROUP BY p.id',
      // Create the subfolders
      'INSERT IGNORE INTO DatabaseStorageDirEntries (`id`, `parent_id`, `name`, `updated`, `created`, `type`)
SELECT
  1000 * p.id + pb.sequence,
  p.id,
  CONCAT_WS("-", p.name, LPAD(pb.sequence, 3, "0")),
  COALESCE(pb.documents_changed, GREATEST(pb.created, pb.updated)),
  pb.created,
  "folder"
FROM ProjectBalanceSupportingDocuments pb
LEFT JOIN Projects p
  ON pb.project_id = p.id',
      // Create the file entries
      'INSERT IGNORE INTO DatabaseStorageDirEntries (`id`, `parent_id`, `file_id`, `name`, `updated`, `created`, `type`)
SELECT
  1000 * (1000 * jt.project_id + jt.sequence) + f.id,
  1000 * jt.project_id + jt.sequence,
  jt.encrypted_file_id,
  f.file_name,
  f.updated,
  f.created,
  "file"
FROM project_balance_supporting_document_encrypted_file jt
LEFT JOIN Files f
ON jt.encrypted_file_id = f.id',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Convert supporting documents for the financial balance of projects to generic directory entries');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    if (!parent::execute()) {
      return false;
    }

    return true;

    // danger zone:

    self::$sql[self::STRUCTURAL] = [
      'DROP INDEX IF EXISTS IDX_65D9920C166D1F9C6A022FD1 ON CompositePayments',
      'ALTER TABLE CompositePayments DROP COLUMN IF EXISTS balance_document_sequence',
      'ALTER TABLE Projects DROP COLUMN IF EXISTS financial_balance_supporting_documents_changed',
      'DROP INDEX IF EXISTS IDX_F6372AE2166D1F9C6A022FD1 ON ProjectPayments',
      'ALTER TABLE ProjectPayments DROP COLUMN IF EXISTS balance_document_sequence',
    ];
    self::$sql[self::TRANSACTIONAL] = [];

    return parent::execute();
  }
}
