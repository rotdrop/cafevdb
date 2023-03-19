<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

/** Small trait-class to fix seom CGI-names. */
trait ParticipantFieldsCgiNameTrait
{
  /**
   * @param int $fieldId
   *
   * @return string
   */
  protected static function participantFieldTableName(int $fieldId):string
  {
    return self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
  }

  /**
   * @param int $fieldId
   *
   * @return string
   */
  protected static function participantFieldValueFieldName(int $fieldId):string
  {
    return self::joinTableFieldName(self::participantFieldTableName($fieldId), 'option_value');
  }

  /**
   * @param int $fieldId
   *
   * @return string
   */
  protected static function participantFieldKeyFieldName(int $fieldId):string
  {
    return self::joinTableFieldName(self::participantFieldTableName($fieldId), 'option_key');
  }

  /**
   * @param int $fieldId
   *
   * @return string
   */
  protected static function participantFieldOptionsTableName(int $fieldId):string
  {
    return self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE.self::VALUES_TABLE_SEP.$fieldId;
  }
}
