<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Traits;

trait SloppyTrait
{
  /**
   * Implodes an array assuming it is a list of words. The last word
   * will be joined with the translation of 'and'.
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

    $result = $values[0];
    for ($i = 1; $i < count($values)-1; $i++) {
      $result .= $separators['ordinary'] . $values[$i];
    }
    $result .= $separators['last'] . $values[$i];
    return $result;
  }

  /**
   * Truncate the first word with an ellipsis '...' such that both
   * words fit into a comma separated string.
   */
  protected function ellipsizeFirst($first, $second, $length, $separator = ', '):string
  {
    $ellipsis = '...';
    $excess = strlen($first) + strlen($second) + strlen($separator) - $length;
    if ($excess > 0) {
      $first = substr($first, 0, -$excess - strlen($ellipsis)) . $ellipsis;
    }
    return $first . $separator . $second;
  }
}
