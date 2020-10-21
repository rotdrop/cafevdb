<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class MusicianRepository extends EntityRepository
{
  /**
   * @param string $firstName
   *
   * @param string $lastName
   *
   * @return array of \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician
   */
  public function findByName(string $firstName, string $surName)
  {
    return $this->findBy(
      [ 'vorname' => $firstName, 'name' => $surName ],
      [ 'name' => 'ASC', 'vorname' => 'ASC' ]
    );
  }

  /**
   * @param string $uuid
   *
   * @return \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician
   */
  public function findByUUID($uuid)
  {
    return $this->findOneBy([ 'uuid' => $uuid ]);
  }



  /**Fetch the street address of the respected musician. Needed in
   * order to generate automated snail-mails.
   *
   * Return value is a flat array:
   *
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'street' => ...,
   *       'city' => ...,
   *       'ZIP' => ...);
   */
  public static function findStreetAddress($musicianId)
  {
    $qb = $this->createQueryBuilder('m');
    $address = $qb->select(['m.Name AS surName',
                            'm.Vorname AS firstName',
                            'm.Strasse AS street',
                            'm.Stadt AS city',
                            'm.Postleitzahl AS ZIP',
                            'm.FixedLinePhone AS phone',
                            'm.MobilePhone AS cellphone'])
      ->where('m.Id = :id')
      ->setParameter('id', $musicianId)
      ->getQuery()->execute()[0];
    return $address;
  }

  /**Fetch the street address of the respected musician. Needed in
   * order to generate automated snail-mails.
   *
   * Return value is a flat array:
   *
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'street' => ...,
   *       'city' => ...,
   *       'ZIP' => ...);
   */
  public static function findName($musicianId)
  {
    $qb = $this->createQueryBuilder('m');
    $name = $qb->select(['m.Name AS lastName',
                         'm.Vorname AS firstName',
                         'm.Email AS email'])
      ->where('m.Id = :id')
      ->setParameter('id', $musicianId)
      ->getQuery()->execute()[0];
    return $name;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
