<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Generate some needed procedures and functions. MySQL specific.
 */
class IndexOptimizations extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "DROP INDEX IF EXISTS IDX_E1AAA1E9443707B0166D1F9C ON ProjectParticipantFieldsData;",
      "CREATE INDEX IF NOT EXISTS IDX_E1AAA1E9443707B0166D1F9C ON ProjectParticipantFieldsData (field_id, project_id);",
      "DROP INDEX IF EXISTS IDX_FA443FE8A90ABA9 ON ProjectParticipantFieldsDataOptions;",
      "CREATE INDEX IF NOT EXISTS IDX_FA443FE8A90ABA9 ON ProjectParticipantFieldsDataOptions (`key`);",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Add some additional search indexes');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
