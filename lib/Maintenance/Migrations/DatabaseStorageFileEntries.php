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
class DatabaseStorageFileEntries extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'CREATE TABLE IF NOT EXISTS DatabaseStorageFileEntries (id INT AUTO_INCREMENT NOT NULL, file_id INT DEFAULT NULL, storage_id VARCHAR(64) NOT NULL, base_name VARCHAR(256) NOT NULL, dir_name VARCHAR(4000) NOT NULL, INDEX IDX_F4B2C74B93CB796C (file_id), UNIQUE INDEX UNIQ_F4B2C74B5CC5DB90AC632DD4C840B0F6 (storage_id, dir_name, base_name), PRIMARY KEY(id))',
      'ALTER TABLE DatabaseStorageFileEntries ADD CONSTRAINT FK_F4B2C74B93CB796C FOREIGN KEY IF NOT EXISTS (file_id) REFERENCES Files (id)',
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
