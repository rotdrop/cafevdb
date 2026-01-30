<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/**
 * Extract the ranking of the played instrument from the ordering of the instruments-list.
 */
trait MusicianInstrumentsRankingTrait
{

  /**
   * The ranking of the mussician's instruments is implicitly stored
   * in the order of the instrument ids. Change the coressponding
   * field to include the ranking explicitly.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function extractInstrumentRanking(
    PHPMyEdit &$pme,
    string $op,
    string $step,
    array &$oldValues,
    ?array &$changed,
    ?array &$newValues
  ):bool {
    $keyField = self::joinTableFieldName(self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id');
    $rankingField = self::joinTableFieldName(self::MUSICIAN_INSTRUMENTS_TABLE, 'ranking');

    $this->debug('FIELDS: ' . $keyField . ' / ' . $rankingField);
    $this->debugPrintValues($oldValues, $changed, $newValues, [ $keyField, $rankingField ]);

    foreach (['old', 'new'] as $dataSet) {
      $keys = Util::explode(self::VALUES_SEP, Util::removeSpaces(${$dataSet.'Values'}[$keyField ] ?? ''));
      $ranking = [];
      foreach ($keys as $key) {
        $ranking[] = $key.self::JOIN_KEY_SEP.(count($ranking)+1);
      }
      ${$dataSet.'Values'}[$rankingField] = implode(self::VALUES_SEP, $ranking);
    }

    // as the ordering is implied by the ordering of keys the ranking
    // changes whenever the keys change.
    if (array_search($keyField, $changed) !== false) {
      $changed[] = $rankingField;
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, [ $keyField, $rankingField ], 'after');

    return true;
  }
}
