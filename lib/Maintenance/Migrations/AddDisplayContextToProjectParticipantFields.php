<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

/**
 * Remember the id of a mailing list.
 */
class AddDisplayContextToProjectParticipantFields extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectParticipantFields
  ADD COLUMN IF NOT EXISTS display_context
    enum('associates','participants','unrestricted')
    DEFAULT 'unrestricted'
    NOT NULL
    COMMENT 'enum(associates,participants,unrestricted)(DC2Type:EnumDisplayContext)'",
    ],
    self::TRANSACTIONAL => [
      "UPDATE ProjectParticipantFields ppf
  INNER JOIN ProjectEvents pe
  ON ppf.id = pe.absence_field_id
  SET ppf.display_context = 'participants'",
      "UPDATE ProjectParticipantFields ppf
  SET ppf.display_context = 'participants'
  WHERE ppf.tab = 'Absence'",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add a display_context field to the extra participant fields table.');
  }
}
