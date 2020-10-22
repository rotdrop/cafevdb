<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class InstrumentsRepository extends EntityRepository
{
  /**Sort by configured sorting column and omit disabled entries.
   */
  public function findAll()
  {
    return $this->findBy(['disabled' => false], ['sortierung' => 'ASC']);
  }

  /**
   * Prepare ofor grouping select options by instrument family.
   *
   * @return array('families' => array(FAMILIES),
   *               'byId' => array(ID => NAME),
   *               'byName' => array(NAME => NAME),
   *               'nameGroups' => array(NAME => FAMILY),
   *               'idGroups' => array(ID => FAMILY))
   *
   * @todo Does such a function belong into the entity repository? OTOH ...
   */
  public function describeAll()
  {
    $byId = $byName = $nameGroups = $idGroups = $familyCollector = [];
    foreach($this->findAll() as $entity) {
      $id         = $entity['Id'];
      $instrument = $entity['Instrument'];
      $families   = $entity['families']->map(function($entity) {
        return $entity['family'];
      })->toArray();
      $family = implode(',', sort($families));
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
