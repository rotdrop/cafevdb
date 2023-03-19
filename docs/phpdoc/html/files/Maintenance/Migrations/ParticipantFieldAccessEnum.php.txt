<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Change participantAccess field to enum.
 */
class ParticipantFieldAccessEnum extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectParticipantFields DROP COLUMN IF EXISTS readers",
      "ALTER TABLE ProjectParticipantFields DROP COLUMN IF EXISTS writers",
      "ALTER TABLE ProjectParticipantFields ADD COLUMN IF NOT EXISTS participant_access_new enum('none','read','read-write') DEFAULT 'none' COMMENT 'enum(none,read,read-write)(DC2Type:EnumAccessPermission)'",
    ],
    self::TRANSACTIONAL => [
      "UPDATE ProjectParticipantFields ppf SET participant_access_new = 'none' WHERE participant_access IS NULL OR participant_access = 0 OR participant_access LIKE 'none'",
      "UPDATE ProjectParticipantFields ppf SET participant_access_new = 'read' WHERE participant_access = 1 OR participant_access LIKE 'read'",
      "UPDATE ProjectParticipantFields ppf SET participant_access_new = 'read-write' WHERE participant_access = 2 OR participant_access LIKE 'read-write'",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Use an enum for the participant access control');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    if (!parent::execute()) {
      return false;
    }
    static::$sql[self::TRANSACTIONAL] = [];
    static::$sql[self::STRUCTURAL] = [
      "ALTER TABLE ProjectParticipantFields DROP COLUMN IF EXISTS participant_access",
      "ALTER TABLE ProjectParticipantFields CHANGE participant_access_new participant_access enum('none','read','read-write') DEFAULT 'none' COMMENT 'enum(none,read,read-write)(DC2Type:EnumAccessPermission)'",
    ];
    return parent::execute();
  }
};
