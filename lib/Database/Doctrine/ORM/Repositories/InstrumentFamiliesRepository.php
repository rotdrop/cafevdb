<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class InstrumentFamiliesRepository extends EntityRepository
{
  /**This is essentially a single value table, just return the values as plain
   * array.
   */
  public function values()
  {
    // $values = $this
    //   ->_em
    //   ->createQuery('SELECT if.family FROM '
    //                 .Entities\InstrumentFamily::class
    //                 .' if ORDER BY if.family ASC')
    //   ->getResult('COLUMN_HYDRATOR');

    $values = $this->createQueryBuilder('if')
      ->select('if.family')
      ->orderBy('if.family', 'ASC')
      ->getQuery()
      ->getResult('COLUMN_HYDRATOR');

    return $values;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
