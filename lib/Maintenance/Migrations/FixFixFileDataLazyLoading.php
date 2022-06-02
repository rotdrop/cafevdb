<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class FixFixFileDataLazyLoading extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE FileData ADD COLUMN IF NOT EXISTS type enum('generic','image','encrypted') NOT NULL COMMENT 'enum(generic,image,encrypted)(DC2Type:EnumFileType)'",
      "ALTER TABLE FileData DROP COLUMN IF EXISTS transformation",
    ],
    self::TRANSACTIONAL => [
      "UPDATE FileData fd INNER JOIN Files f ON f.id = fd.file_id
SET fd.type = f.type",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Fix the fix for file-data lazy loading.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
