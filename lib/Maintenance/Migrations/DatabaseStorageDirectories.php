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
 * Overlay generic files on top of database-backed storages.
 */
class DatabaseStorageDirectories extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'CREATE TABLE IF NOT EXISTS DatabaseStorageDirectories (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, storage_id VARCHAR(64) DEFAULT NULL, name VARCHAR(256) NOT NULL, updated DATETIME(6) DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_D1CC6CA1727ACA70 (parent_id), PRIMARY KEY(id))',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_D1CC6CA15CC5DB90727ACA705E237E06 ON DatabaseStorageDirectories (storage_id, parent_id, name)',
      'CREATE TABLE IF NOT EXISTS database_storage_directory_encrypted_file (database_storage_directory_id INT NOT NULL, encrypted_file_id INT NOT NULL, INDEX IDX_9DDA237BCEB6337 (database_storage_directory_id), INDEX IDX_9DDA237BEC15E76C (encrypted_file_id), PRIMARY KEY(database_storage_directory_id, encrypted_file_id))',
      'ALTER TABLE DatabaseStorageDirectories ADD CONSTRAINT FK_D1CC6CA1727ACA70 FOREIGN KEY IF NOT EXISTS (parent_id) REFERENCES DatabaseStorageDirectories (id)',
      'ALTER TABLE database_storage_directory_encrypted_file ADD CONSTRAINT FK_9DDA237BCEB6337 FOREIGN KEY IF NOT EXISTS (database_storage_directory_id) REFERENCES DatabaseStorageDirectories (id) ON DELETE CASCADE',
      'ALTER TABLE database_storage_directory_encrypted_file ADD CONSTRAINT FK_9DDA237BEC15E76C FOREIGN KEY IF NOT EXISTS (encrypted_file_id) REFERENCES Files (id) ON DELETE CASCADE',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Overlay generic and in particular README.md files over database-storage folders.');
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
