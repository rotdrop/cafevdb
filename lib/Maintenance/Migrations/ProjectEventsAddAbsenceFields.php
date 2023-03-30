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
      "CREATE TABLE IF NOT EXISTS project_event_project_participant_field (project_id INT NOT NULL, calendar_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`, event_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`, project_participant_field_id INT NOT NULL, INDEX IDX_CB1BE8A3166D1F9C7A7DD39295D374F2 (project_id, calendar_uri, event_uri), UNIQUE INDEX UNIQ_CB1BE8A36C95B1E2 (project_participant_field_id), PRIMARY KEY(project_id, calendar_uri, event_uri, project_participant_field_id))",
      "ALTER TABLE ProjectEvents DROP PRIMARY KEY",
      "ALTER TABLE ProjectEvents CHANGE event_uri event_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`, CHANGE calendar_uri calendar_uri VARCHAR(764) NOT NULL COLLATE `ascii_bin`, CHANGE event_uid event_uid VARCHAR(255) NOT NULL COLLATE `ascii_general_ci`",
      "ALTER TABLE ProjectEvents ADD PRIMARY KEY (project_id, calendar_uri, event_uri)",
      "ALTER TABLE project_event_project_participant_field ADD CONSTRAINT CB1BE8A3166D1F9C7A7DD39295D374F2 FOREIGN KEY IF NOT EXISTS (project_id, calendar_uri, event_uri) REFERENCES ProjectEvents (project_id, calendar_uri, event_uri)",
      "ALTER TABLE project_event_project_participant_field ADD CONSTRAINT FK_CB1BE8A36C95B1E2 FOREIGN KEY IF NOT EXISTS (project_participant_field_id) REFERENCES ProjectParticipantFields (id)",
    ],
    self::TRANSACTIONAL => [],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Link optional per participant fields to record absence from rehearsals.');
  }
}
