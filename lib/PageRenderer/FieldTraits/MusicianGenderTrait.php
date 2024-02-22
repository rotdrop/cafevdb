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

use GenderDetector;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;

/**
 * Try to autodetect the gender of the musician.
 */
trait MusicianGenderTrait
{
  /** @var PHPMyEdit */
  protected $pme;

  /**
   * Return an array of possibilities for the gender of the musician.
   *
   * @param array $row
   *
   * @return array
   */
  protected function guessGender(array $row):array
  {
    if (empty($row)) {
      return [];
    }
    $tableName =
      ($this->pme->fdn[PMETableViewBase::joinTableMasterFieldName(PMETableViewBase::MUSICIANS_TABLE)] ?? null
       ? PMETableViewBase::MUSICIANS_TABLE : null);
    $firstName = $row[$this->joinQueryField($tableName, 'first_name')];
    $nickName = $row[$this->joinQueryField($tableName, 'nick_name')];
    $lang = $row[$this->joinQueryField($tableName, 'language')];
    $country = $row[$this->joinQueryField($tableName, 'country')];
    $detector = new GenderDetector\GenderDetector();
    if (!empty($lang)) {
      $country = substr($lang, 0, 2);
    }
    $country = strtoupper($country);
    $names = array_filter(array_merge(explode(' ', $firstName), explode(' ', $nickName)));
    $genderTypes = array_filter(array_map(fn(string $name) => $detector->getGender($name, $country), $names));
    $genderTypes = array_unique(array_map(fn(GenderDetector\Gender $gender) => str_replace('mostly', '', strtolower($gender->name)), $genderTypes));
    return array_combine($genderTypes, array_map(fn(string $name) => $this->l->t($name), $genderTypes));
  }

  /**
   * Use as display-postfix for legacy PME.
   *
   * @param string $op Operation mode of PME.
   *
   * @param int $k
   *
   * @param array $row
   *
   * @return string HTML fragment to add to the cell.
   */
  protected function genderDisplayPostfix(string $op, int $k, array $row):string
  {
    if (empty($row) || !empty($row[$this->queryField($k)])) {
      return '';
    }
    $genderTypes = $this->guessGender($row);
    if (count($genderTypes) == 0) {
      return '';
    }
    if (count($genderTypes) == 1 && $op == PHPMyEdit::OPERATION_CHANGE) {
      $html = '
<span class="space">&nbsp;</span>
<span class="gender-detector">' . $this->l->t('click to selected the guessed value:') . '</span>
<span class="space">&nbsp;</span>';
      foreach ($genderTypes as $gender => $l10nGender) {
        $html .= '<a href="#" class="button accept-gender-detection" data-value="' . $gender . '">' . $l10nGender . '</a>';
      }
    } else {
      $html .= '
<span class="gender-detector">' . $this->l->t('guessed: %s', implode(', ', $genderTypes)) . '</span>';
    }
    return $html;
  }
}
