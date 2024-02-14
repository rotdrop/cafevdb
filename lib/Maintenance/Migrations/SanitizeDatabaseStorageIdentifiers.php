<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Change participantAccess field to enum.
 */
class SanitizeDatabaseStorageIdentifiers extends AbstractMigration
{
  protected static $sql = [
    self::TRANSACTIONAL => [
      "UPDATE DatabaseStorages ds
  INNER JOIN (
    SELECT
      jds.id,
      EXPLODE('/', jds.storage_id, 1) AS project_name,
      EXPLODE('/', jds.storage_id, 3) AS participant
    FROM `DatabaseStorages` jds
    WHERE EXPLODE('/', jds.storage_id, 2) = 'participants'
  ) exp
  ON ds.id = exp.id
  SET ds.storage_id = CONCAT_WS('/', 'project', 'participant', exp.project_name, exp.participant)
;",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Sanitize some storage identifiers.');
  }
}
