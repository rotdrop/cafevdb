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
class ProjectEventsAddAbsenceFields extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectEvents DROP PRIMARY KEY",
      "DROP INDEX IF EXISTS UNIQ_7E38FC8B166D1F9C4254C3D5 ON ProjectEvents",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS sequence INT DEFAULT 0 NOT NULL",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS recurrence_id VARCHAR(64) DEFAULT '' NOT NULL COLLATE `ascii_bin`",
      "ALTER TABLE ProjectEvents CHANGE COLUMN IF EXISTS recurrence_id recurrence_id VARCHAR(64) DEFAULT '' NOT NULL COLLATE `ascii_bin`",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS absence_field_id INT DEFAULT NULL",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8BA79D8A87 ON ProjectEvents (absence_field_id)",
      "ALTER TABLE ProjectEvents ADD CONSTRAINT FK_7E38FC8BA79D8A87 FOREIGN KEY IF NOT EXISTS (absence_field_id) REFERENCES ProjectParticipantFields (id)",
      "ALTER TABLE ProjectEvents ADD PRIMARY KEY (project_id, calendar_uri, event_uid, sequence, recurrence_id)",
      "CREATE INDEX IF NOT EXISTS IDX_7E38FC8B95D374F2 ON ProjectEvents (event_uri)",
      "CREATE INDEX IF NOT EXISTS IDX_7E38FC8BA40A2C8 ON ProjectEvents (calendar_id)",
    ],
    self::TRANSACTIONAL => [],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Link optional per participant fields to project events in order to record absence.');
  }
}
