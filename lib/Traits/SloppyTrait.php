<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

/** Try to "implode" string arrays to something more or less readable. */
trait SloppyTrait
{
  /**
   * Implodes an array assuming it is a list of words. The last word
   * will be joined with the translation of 'and'.
   *
   * @param array $values
   *
   * @param null|array $separators
   *
   * @return string
   */
  protected function implodeSloppy(array $values, ?array $separators = null):string
  {
    if (empty($values)) {
      return '';
    }
    if (empty($separators)) {
      $separators = [
        'ordinary' => ', ',
        'last' => ' '.$this->l->t('and').' ',
      ];
    }

    $numValues = count($values);
    $result = $values[0];
    for ($i = 1; $i < $numValues - 1; $i++) {
      $result .= $separators['ordinary'] . $values[$i];
    }
    $result .= $separators['last'] . $values[$i];
    return $result;
  }

  /**
   * Truncate the first word with an ellipsis '...' such that both
   * words fit into a comma separated string.
   *
   * @param string $first
   *
   * @param string $second
   *
   * @param int $length
   *
   * @param string $separator
   *
   * @return string
   */
  protected function ellipsizeFirst(string $first, string $second, int $length, string $separator = ', '):string
  {
    $ellipsis = '...';
    $excess = strlen($first) + strlen($second) + strlen($separator) - $length;
    if ($excess > 0) {
      $first = substr($first, 0, -$excess - strlen($ellipsis)) . $ellipsis;
    }
    return $first . $separator . $second;
  }
}
