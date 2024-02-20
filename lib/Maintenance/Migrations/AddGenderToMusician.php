<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
class AddGenderToMusician extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS
   gender
     enum('male','female','diverse')
     COMMENT 'enum(male,female,diverse)(DC2Type:EnumGender)'",
      "ALTER TABLE Musicians CHANGE member_status
   member_status
     enum('regular','passive','soloist','conductor','temporary')
     DEFAULT 'regular'
     NOT NULL
     COMMENT 'enum(regular,passive,soloist,conductor,temporary)(DC2Type:EnumMemberStatus)'",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add a gender field to the musician entity for the sake of mail-merging.');
  }
}
