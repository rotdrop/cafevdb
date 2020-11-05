<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;
use Doctrine\DBAL\Logging\DebugStack;

class InstrumentsRepository extends EntityRepository
{
  /**Find an instrument by its name.*/
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'instrument' => $name ], ['sortierung' => 'ASC' ]);
  }

  /**Sort by configured sorting column and omit disabled entries.
   */
  public function findAll()
  {
    return $this->findBy(['disabled' => false], ['sortierung' => 'ASC']);
  }

  /**
   * Prepare ofor grouping select options by instrument family.
   *
   * @return array like
   * @code
   * array('families' => array(FAMILIES),
   *       'byId' => array(ID => NAME),
   *       'byName' => array(NAME => NAME),
   *       'nameGroups' => array(NAME => FAMILY),
   *       'idGroups' => array(ID => FAMILY))
   * @end code
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
      $id         = $entity['Id'];
      $instrument = $entity['Instrument'];
      $families   = $entity['families']->map(function($entity) {
        return $entity['family'];
      })->toArray();
      sort($families);
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
