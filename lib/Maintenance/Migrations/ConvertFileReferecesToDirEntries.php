<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use Exception;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the id of a mailing list.
 */
class ConvertFileReferencesToDirEntries extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE SepaBulkTransactionData DROP FOREIGN KEY IF EXISTS FK_1EBA3E5BEC15E76C',
      'DROP INDEX IF EXISTS UNIQ_1EBA3E5BEC15E76C ON SepaBulkTransactionData',
      'ALTER TABLE SepaBulkTransactionData DROP PRIMARY KEY',
      'ALTER TABLE SepaBulkTransactionData CHANGE COLUMN IF EXISTS encrypted_file_id database_storage_file_id INT NOT NULL',
      'ALTER TABLE SepaBulkTransactionData ADD CONSTRAINT FK_1EBA3E5B4D73A4D4 FOREIGN KEY IF NOT EXISTS (database_storage_file_id) REFERENCES DatabaseStorageDirEntries (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_1EBA3E5B4D73A4D4 ON SepaBulkTransactionData (database_storage_file_id)',
      'ALTER TABLE SepaBulkTransactionData ADD PRIMARY KEY (sepa_bulk_transaction_id, database_storage_file_id)',

      'ALTER TABLE SepaDebitMandates DROP FOREIGN KEY IF EXISTS FK_1C50029D26EB11F',
      'ALTER TABLE SepaDebitMandates ADD CONSTRAINT FK_1C50029D26EB11F FOREIGN KEY IF NOT EXISTS (written_mandate_id) REFERENCES DatabaseStorageDirEntries (id)',

      'ALTER TABLE ProjectParticipantFieldsData
  DROP INDEX IF EXISTS IDX_E1AAA1E92423759C',
      'ALTER TABLE ProjectParticipantFieldsData
  ADD UNIQUE INDEX IF NOT EXISTS UNIQ_E1AAA1E92423759C (supporting_document_id)',
      'ALTER TABLE ProjectParticipantFieldsData
  DROP FOREIGN IF EXISTS KEY FK_E1AAA1E92423759C',
      'ALTER TABLE ProjectParticipantFieldsData
  ADD CONSTRAINT FK_E1AAA1E92423759C FOREIGN KEY IF NOT EXISTS (supporting_document_id) REFERENCES DatabaseStorageDirEntries (id)',

      'ALTER TABLE CompositePayments
  DROP INDEX IF EXISTS IDX_65D9920C2423759C',
      'ALTER TABLE CompositePayments
  ADD UNIQUE INDEX IF NOT EXISTS UNIQ_65D9920C2423759C (supporting_document_id)',
      'ALTER TABLE CompositePayments
  DROP FOREIGN KEY IF EXISTS FK_65D9920C2423759C',
      'ALTER TABLE CompositePayments
  ADD CONSTRAINT FK_65D9920C2423759C FOREIGN KEY IF NOT EXISTS (supporting_document_id) REFERENCES DatabaseStorageDirEntries (id)',
    ],
    // The strategy is to follow up the path up to the root-directory of the
    // matching storage which is hopefully enough to make the updates no-ops
    // if applied multiple times.
    self::TRANSACTIONAL => [
      'SET FOREIGN_KEY_CHECKS = 0', // danger zone
      // SepaBulkTransactionData
      'UPDATE SepaBulkTransactions sbt
LEFT JOIN SepaBulkTransactionData sbtd
  ON sbtd.sepa_bulk_transaction_id = sbt.id
LEFT JOIN DatabaseStorages s
  ON s.storage_id = "finance/transactions/"
INNER JOIN Files f
  ON sbtd.database_storage_file_id = f.id
LEFT JOIN DatabaseStorageDirEntries de
  ON de.file_id = f.id
LEFT JOIN DatabaseStorageDirEntries parents
  ON parents.id = de.parent_id

SET sbtd.database_storage_file_id = de.id

WHERE de.id IS NOT NULL
  AND parents.parent_id = s.root_id',

      // SepaDebitMandates, written mandate
      'UPDATE SepaDebitMandates sdm
LEFT JOIN Musicians m
  ON m.id = sdm.musician_id
LEFT JOIN Projects p
  ON p.id = sdm.project_id
LEFT JOIN DatabaseStorages s
  ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
INNER JOIN Files f
  ON sdm.written_mandate_id = f.id
LEFT JOIN DatabaseStorageDirEntries de
  ON de.file_id = f.id
LEFT JOIN DatabaseStorageDirEntries parents
  ON parents.id = de.parent_id

SET sdm.writen_mandate_id = de.id

WHERE de.id IS NOT NULL
  AND parents.parent_id = s.root_id',
      // CompositePayments
      // follow the directory chain up to the root and only update if
      // everything matches
      'UPDATE CompositePayments cp
LEFT JOIN Musicians m
  ON m.id = cp.musician_id
LEFT JOIN Projects p
  ON p.id = cp.project_id
LEFT JOIN DatabaseStorages s
  ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
INNER JOIN Files f
  ON cp.supporting_document_id = f.id
LEFT JOIN DatabaseStorageDirEntries de
  ON de.file_id = f.id
LEFT JOIN DatabaseStorageDirEntries parents
  ON parents.id = de.parent_id
LEFT JOIN DatabaseStorageDirEntries gp
  ON gp.id = parents.parent_id

SET cp.supporting_document_id = de.id

WHERE de.id IS NOT NULL
  AND gp.parent_id = s.root_id',
      // ProjectParticipantFieldsData, supporting documents
      'UPDATE ProjectParticipantFieldsData ppfd
LEFT JOIN ProjectParticipantFields ppf
  ON ppfd.field_id = ppf.id
LEFT JOIN Musicians m
  ON m.id = ppfd.musician_id
LEFT JOIN Projects p
  ON p.id = ppf.project_id
LEFT JOIN DatabaseStorages s
  ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
INNER JOIN Files f
  ON ppfd.supporting_document_id = f.id
LEFT JOIN DatabaseStorageDirEntries de
  ON de.file_id = f.id
LEFT JOIN DatabaseStorageDirEntries parents
  ON parents.id = de.parent_id
LEFT JOIN DatabaseStorageDirEntries grand_parents
  ON grand_parents.id = parents.parent_id
LEFT JOIN DatabaseStorageDirEntries ancestors
  ON grand_parents.parent_id = ancestors.id

  SET ppfd.supporting_document_id = de.d

WHERE de.id IS NOT NULL
  AND ppf.data_type = "service-fee"
  AND ((ppf.multiplicity = "simple" AND grand_parents.parent_id = s.root_id)
       OR ancestors.parent_id = s.root_id)',
      // ProjectParticipantsFieldData, data type DB_FILE
      // update if the entire document chain matches, up to the root-id
      'UPDATE ProjectParticipantFieldsData ppfd
LEFT JOIN ProjectParticipantFields ppf
  ON ppfd.field_id = ppf.id
LEFT JOIN Projects p
  ON ppf.project_d = p.id
LEFT JOIN Musicians m
  ON ppfd.musician_id = m.id
LEFT JOIN DatabaseStorages s
  ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
INNER JOIN Files f
  ON ppfd.option_value = f.id
LEFT JOIN DatabaseStorageDirEntries de
  ON de.file_id = f.id
LEFT JOIN DatabaseStorageDirEntries parents
  ON parents.id = de.parent_id

SET ppfd.option_value = de.id

WHERE ppf.data_type = "db-file"
  AND ppfd.option_value > 0
  AND de.id IS NOT NULL
  AND ppf.data_type = "db-file"
  AND ((ppf.multiplicity = "simple" AND parents.id = s.root_id)
       OR
       parents.parent_id = s.root_id)',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Convert references to file entities to references to dir-entries.');
  }
}

// SELECT s.storage_id, s.root_id,
//   CONCAT_WS("/", ancestors.name, grand_parents.name, parents.name, de.name) AS path,
//   ancestors.parent_id AS ancestor_parent_id, grand_parents.parent_id AS ancestor_id, parents.parent_id AS grand_parent_id, parents.id AS parent_id, de.id AS dir_entry, de.name, f.id AS file_id, f.file_name
// FROM ProjectParticipantFieldsData ppfd
// LEFT JOIN ProjectParticipantFields ppf
//   ON ppfd.field_id = ppf.id
// LEFT JOIN Musicians m
//   ON m.id = ppfd.musician_id
// LEFT JOIN Projects p
//   ON p.id = ppf.project_id
// LEFT JOIN DatabaseStorages s
//   ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
// INNER JOIN Files f
//   ON ppfd.supporting_document_id = f.id
// LEFT JOIN DatabaseStorageDirEntries de
//   ON de.file_id = f.id
// LEFT JOIN DatabaseStorageDirEntries parents
//   ON parents.id = de.parent_id
// LEFT JOIN DatabaseStorageDirEntries grand_parents
//   ON grand_parents.id = parents.parent_id
// LEFT JOIN DatabaseStorageDirEntries ancestors
//   ON grand_parents.parent_id = ancestors.id
// WHERE de.id IS NOT NULL
//   AND ppf.data_type = "service-fee"
//   AND ((ppf.multiplicity = "simple" AND grand_parents.parent_id = s.root_id)
//        OR ancestors.parent_id = s.root_id);

// SELECT s.storage_id, s.root_id,
//   CONCAT_WS("/", parents.name, de.name) AS path,
//   parents.parent_id AS grand_parent_id, parents.id AS parent_id, de.id AS dir_entry, de.name, f.id AS file_id, f.file_name
// FROM SepaDebitMandates sdm
// LEFT JOIN Musicians m
//   ON m.id = sdm.musician_id
// LEFT JOIN Projects p
//   ON p.id = sdm.project_id
// LEFT JOIN DatabaseStorages s
//   ON s.storage_id = CONCAT_WS("/", p.name, "participants", m.user_id_slug, "")
// INNER JOIN Files f
//   ON sdm.written_mandate_id = f.id
// LEFT JOIN DatabaseStorageDirEntries de
//   ON de.file_id = f.id
// LEFT JOIN DatabaseStorageDirEntries parents
//   ON parents.id = de.parent_id
// WHERE de.id IS NOT NULL
//   AND parents.parent_id = s.root_id;

// SELECT sbt.id as transaction_id, s.storage_id, s.root_id,
//   CONCAT_WS("/", parents.name, de.name) AS path,
//   parents.parent_id AS grand_parent_id, parents.id AS parent_id, de.id AS dir_entry, de.name, f.id AS file_id, f.file_name
// FROM SepaBulkTransactions sbt
// LEFT JOIN SepaBulkTransactionData sbtd
//   ON sbtd.sepa_bulk_transaction_id = sbt.id
// LEFT JOIN DatabaseStorages s
//   ON s.storage_id = "finance/transactions/"
// INNER JOIN Files f
//   ON sbtd.encrypted_file_id = f.id
// LEFT JOIN DatabaseStorageDirEntries de
//   ON de.file_id = f.id
// LEFT JOIN DatabaseStorageDirEntries parents
//   ON parents.id = de.parent_id
// WHERE de.id IS NOT NULL
//   AND parents.parent_id = s.root_id;
