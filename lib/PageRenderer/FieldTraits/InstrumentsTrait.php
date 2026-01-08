<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

use UnexpectedValueException;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Some convenience methods for instruments and instrument families
 */
trait InstrumentsTrait
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  protected ?array $instrumentInfo = null;

  protected ?array $nonInstruments = null;

  /**
   * Fetch instruments info, @see OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository.
   *
   * @return array
   */
  protected function getInstrumentInfo():array
  {
    if ($this->instrumentInfo === null) {
      $this->instrumentInfo = $this->getDatabaseRepository(Entities\Instrument::class)->describeAll();
    }
    return $this->instrumentInfo;
  }

  /**
   * @return array<int, string> An array of non-instruments as id => name string.
   *
   * @throws UnexpectedValueException
   */
  protected function getNonInstruments():array
  {
    if ($this->nonInstruments === null) {
      $instrumentInfo = $this->getInstrumentInfo();
      $ids = array_filter(
        $instrumentInfo['idGroups'],
        fn(string $families) => !empty(in_array($this->l->t(Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY), explode(',', $families))),
      );
      $this->nonInstruments = array_intersect_key($instrumentInfo['byId'], $ids);
    }

    if (empty($this->nonInstruments)) {
      throw new UnexpectedValueException($this->l->t('Unable to find the standard non-instruments AKA roles.'));
    }

    return $this->nonInstruments;
  }
}
