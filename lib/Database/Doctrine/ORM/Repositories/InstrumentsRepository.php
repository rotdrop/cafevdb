<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;
use Doctrine\DBAL\Logging\DebugStack;

class InstrumentsRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * Find an instrument by its name.
   *
   * @return Entities\Instrument
   */
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'instrument' => $name ], [ 'sortOrder' => 'ASC' ]);
  }

  /**
   * Sort by configured sorting column and omit disabled entries.
   *
   * @return array<int, Entities\Instrument>
   */
  public function findAll()
  {
    return $this->findBy(['disabled' => false], [ 'sortOrder' => 'ASC']);
  }

  /**
   * Prepare ofor grouping select options by instrument family.
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
  public function describeAll()
  {
    $byId = $byName = $nameGroups = $idGroups = $familyCollector = [];

    $logger = new DebugStack();
    $this->getEntityManager()->getConfiguration()->setSQLLogger($logger);

    $all = $this->findAll();

    foreach($all as $entity) {
      $id         = $entity['id'];
      $instrument = $entity['instrument'];
      $families   = $entity['families']->map(function($entity) {
        return $entity['family'];
      })->toArray();
      sort($families);
      //$this->log('ID '.$id.' INST '.$instrument.' FAM '.print_r($families, true));
      $family = implode(',', $families);
      $byName[$instrument] = $byId[$id] = $instrument;
      $nameGroups[$instrument] = $idGroups[$id] = $family;
      $familiesCollector[] = $family;
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
