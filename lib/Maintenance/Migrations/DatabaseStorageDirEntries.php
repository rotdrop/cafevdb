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
class DatabaseStorageDirEntries extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS DatabaseStorageDirEntries (
  id INT AUTO_INCREMENT NOT NULL,
  parent_id INT DEFAULT NULL,
  file_id INT DEFAULT NULL,
  name VARCHAR(256) NOT NULL,
  updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  type enum('generic','folder','file') NOT NULL COMMENT 'enum(generic,folder,file)(DC2Type:EnumDirEntryType)',
  INDEX IDX_E123333D727ACA70 (parent_id),
  INDEX IDX_E123333D93CB796C (file_id),
  UNIQUE INDEX UNIQ_E123333D727ACA705E237E06 (parent_id, name),
  PRIMARY KEY(id)
)",
      'ALTER TABLE DatabaseStorageDirEntries ADD CONSTRAINT FK_E123333D727ACA70 FOREIGN KEY IF NOT EXISTS (parent_id) REFERENCES DatabaseStorageDirEntries (id)',
      'ALTER TABLE DatabaseStorageDirEntries ADD CONSTRAINT FK_E123333D93CB796C FOREIGN KEY IF NOT EXISTS (file_id) REFERENCES Files (id)',
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
