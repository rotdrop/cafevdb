<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Repository for instruments. */
class InstrumentsRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * Return a collection of non-instruments, that is, instruments which belong
   * to the family Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY.
   *
   * @param null|array $only If not empty restrict to the given instrument names.
   *
   * @return array<int, Entities\Instrument> The found instruments indexed by id.
   */
  public function findNonInstruments(?array $only = null)
  {
    $criteria = [
      'families.family' => Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY,
    ];
    if (!empty($only)) {
      $criteria['name'] = $only;
    }
    return $this->findBy($criteria, orderBy: [ 'id' => 'INDEX' ]);
  }

  /**
   * Find an instrument by its name.
   *
   * @param string $name
   *
   * @return Entities\Instrument
   */
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'name' => $name ], [ 'sortOrder' => 'ASC', 'name' => 'ASC' ]);
  }

  /**
   * Sort by configured sorting column and omit disabled entries.
   *
   * @return array<int, Entities\Instrument>
   */
  public function findAll():array
  {
    return $this->findBy(['deleted' => null], [ 'sortOrder' => 'ASC', 'name' => 'ASC' ]);
  }

  /**
   * Return instrument names as flat array.
   *
   * @param null|array $only Only instruments belonging to these family names.
   *
   * @param null|array $exclude Exclude instrument belonging to these families.
   *
   * @return array<int, string>
   */
  public function findNames(?array $only = null, ?array $exclude = null):array
  {
    $criteria = [ 'deleted' => null ];
    if (!empty($only)) {
      $criteria[] = [ 'family.family' => $only ];
    }
    if (!empty($exclude)) {
      $criteria[] = [ '!family.family' => $exclude ];
    }
    $result = $this->findBy($criteria, [ 'sortOrder' => 'ASC', 'name' => 'ASC' ]);
    $names = array_map(fn(Entities\Instrument $entity) => $entity->getName(), $result);

    return $names;
  }

  /**
   * Prepare ofor grouping select options by instrument family.
   *
   * @param bool $useEntities Use full entites instead of mere names in the
   * look-up tables.
   *
   * @return array<string, array<string|int, string>>
   *
   * ```php
   * [
   *   'families' => array(FAMILIES),
   *   'byId' => array(ID => NAME),
   *   'byName' => array(NAME => NAME),
   *   'nameGroups' => array(NAME => FAMILY),
   *   'idGroups' => array(ID => FAMILY)
   * ]
   * ```
   *
   * @todo Does such a function belong into the entity repository? OTOH ...
   */
  public function describeAll(bool $useEntities = false)
  {
    $byId = $byName = $nameGroups = $idGroups = $familyCollector = [];

    $all = $this->findAll();

    foreach ($all as $entity) {
      $id         = $entity['id'];
      $instrument = $entity['name'];
      $families   = $entity['families']->map(function($entity) {
        return $entity['family'];
      })->toArray();
      sort($families);
      //$this->log('ID '.$id.' INST '.$instrument.' FAM '.print_r($families, true));
      $family = implode(',', $families);
      $byName[$instrument] = $byId[$id] = $useEntities ? $entity : $instrument;
      $nameGroups[$instrument] = $idGroups[$id] = $family;
      $familyCollector[] = $family;
    }

    return [
      'families' => array_values(array_unique($familyCollector)),
      'byId' => $byId,
      'byName' => $byName,
      'idGroups' => $idGroups,
      'nameGroups' => $nameGroups
    ];
  }
}
