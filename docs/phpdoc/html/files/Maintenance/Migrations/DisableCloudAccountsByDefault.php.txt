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

/**
 * Change participantAccess field to enum.
 */
class DisableCloudAccountsByDefault extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE Musicians CHANGE cloud_account_disabled cloud_account_disabled TINYINT(1) DEFAULT '1'",
    ],
    self::TRANSACTIONAL => [
      // see that all accounts are disabled, unless explicitly enabled or the person is a club member
      'UPDATE
    Musicians m
LEFT JOIN(
    SELECT
        GROUP_CONCAT(DISTINCT p.type) AS TYPES,
        pp.musician_id AS musician_id
    FROM
        ProjectParticipants pp
    LEFT JOIN Projects p ON
        p.id = pp.project_id
    GROUP BY
        pp.musician_id
) pt
ON
    m.id = pt.musician_id
SET
    m.cloud_account_disabled = 1
WHERE
    pt.types IS NULL OR pt.types NOT LIKE "%permanent%" AND m.cloud_account_disabled IS NULL',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Disable cloud accounts by default in order to have the cloud user view updatable.');
  }
}
