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
class ProjectEventsAddAbsenceFields extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectEvents DROP FOREIGN KEY IF EXISTS FK_7E38FC8B166D1F9C",
      //
      // "ALTER TABLE ProjectEvents DROP PRIMARY KEY",
      "ALTER TABLE ProjectEvents DROP INDEX IF EXISTS `PRIMARY`, ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS absence_field_id INT DEFAULT NULL",
      //
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS recurrence_id INT DEFAULT 0 NOT NULL",
      "ALTER TABLE ProjectEvents CHANGE recurrence_id recurrence_id INT DEFAULT 0 NOT NULL",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS sequence INT DEFAULT 0 NOT NULL",
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE ProjectEvents CHANGE type type enum('VEVENT','VTODO','VJOURNAL','VCARD') NOT NULL
  COMMENT 'enum(VEVENT,VTODO,VJOURNAL,VCARD)(DC2Type:EnumVCalendarType)'",
      //
      "CREATE INDEX IF NOT EXISTS IDX_7E38FC8B166D1F9C ON ProjectEvents (project_id)",
      "ALTER TABLE ProjectEvents ADD CONSTRAINT FK_7E38FC8B166D1F9C FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES Projects (id)",
      //
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8BA79D8A87 ON ProjectEvents (absence_field_id)",
      "ALTER TABLE ProjectEvents ADD CONSTRAINT FK_7E38FC8BA79D8A87 FOREIGN KEY IF NOT EXISTS (absence_field_id) REFERENCES ProjectParticipantFields (id)",
      //
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8B166D1F9C7A7DD3924254C3D52C414CE8 ON ProjectEvents (project_id, calendar_uri, event_uid, recurrence_id)",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8B166D1F9CA40A2C84254C3D52C414CE8 ON ProjectEvents (project_id, calendar_id, event_uid, recurrence_id)",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8B166D1F9C7A7DD39295D374F22C414CE8 ON ProjectEvents (project_id, calendar_uri, event_uri, recurrence_id)",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7E38FC8B166D1F9CA40A2C895D374F22C414CE8 ON ProjectEvents (project_id, calendar_id, event_uri, recurrence_id)",
      //
      "ALTER TABLE ProjectEvents ADD COLUMN IF NOT EXISTS series_uid BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary)'",
    ],
    self::TRANSACTIONAL => [],
  ];


  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Link optional per participant fields to project events in order to record absence.');
  }
}
