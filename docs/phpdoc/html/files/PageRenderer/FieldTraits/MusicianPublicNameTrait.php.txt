<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

/**
 * Provide an SQL snippet which composes the public display name of the musician.
 */
trait MusicianPublicNameTrait
{
  /**
   * Generate an SQL fragment which composes a display name from the available
   * name-parts sur_name, first_name, nick_name, display_name.
   *
   * @param string $tableAlias Table to refer to, defaults to placeholder
   * '$table'.
   *
   * @param bool $firstNameFirst
   *
   * @return string SQL fragment.
   */
  public static function musicianPublicNameSql(string $tableAlias = '$table', bool $firstNameFirst = false):string
  {
    if ($firstNameFirst) {
      return "CONCAT_WS(
  ' ',
  IF($tableAlias.nick_name IS NULL OR $tableAlias.nick_name = '',
    $tableAlias.first_name,
    $tableAlias.nick_name
  ),
  $tableAlias.sur_name)";
    } else {
      return "IF($tableAlias.display_name IS NULL OR $tableAlias.display_name = '',
      CONCAT(
        $tableAlias.sur_name,
        ', ',
        IF($tableAlias.nick_name IS NULL OR $tableAlias.nick_name = '',
          $tableAlias.first_name,
          $tableAlias.nick_name
        )
      ),
      $tableAlias.display_name
    )";
    }
  }
}
