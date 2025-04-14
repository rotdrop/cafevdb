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
class ParticipantFieldsAddLiabilities extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectParticipantFields
  CHANGE data_type data_type enum('boolean','cloud-file','cloud-folder','date','datetime','db-file','float','html','integer','service-fee','liabilities','receivables','text')
  DEFAULT 'text' NOT NULL
  COMMENT 'enum(boolean,cloud-file,cloud-folder,date,datetime,db-file,float,html,integer,service-fee,liabilities,receivables,text)(DC2Type:EnumParticipantFieldDataType)'",
    ],
    self::TRANSACTIONAL => [
      "UPDATE ProjectParticipantFields SET data_type = 'receivables' WHERE data_type = 'service-fee'",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add "liabilities" as new field-type in order to avoid negative numbers in the UI.');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    if (!parent::execute()) {
      return false;
    }
    static::$sql[self::TRANSACTIONAL] = [];
    static::$sql[self::STRUCTURAL] = [
      "ALTER TABLE ProjectParticipantFields
  CHANGE data_type data_type enum('boolean','cloud-file','cloud-folder','date','datetime','db-file','float','html','integer','liabilities','receivables','text')
  DEFAULT 'text' NOT NULL
  COMMENT 'enum(boolean,cloud-file,cloud-folder,date,datetime,db-file,float,html,integer,liabilities,receivables,text)(DC2Type:EnumParticipantFieldDataType)'",
    ];

    return parent::execute();
  }

}
