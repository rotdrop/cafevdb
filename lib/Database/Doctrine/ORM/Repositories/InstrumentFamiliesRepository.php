<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class InstrumentFamiliesRepository extends EntityRepository
{
  /**Find an instrument-family by its name.*/
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'family' => $name ]);
  }

  /**Sort by configured sorting column and omit disabled entries.
   */
  public function findAll()
  {
    return $this->findBy(['disabled' => false]);
  }

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
                   ->where('if.disabled = :disabled')
                   ->orderBy('if.family', 'ASC')
                   ->setParameter('disabled', false)
                   ->getQuery()
                   ->getResult('COLUMN_HYDRATOR');

    return $values;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
