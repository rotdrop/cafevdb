<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class MusiciansRepository extends EntityRepository
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
      [ 'firstName' => $firstName, 'surName' => $surName ],
      [ 'surName' => 'ASC', 'firstName' => 'ASC' ]
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
  public function findStreetAddress($musicianId)
  {
    $qb = $this->createQueryBuilder('m');
    $address = $qb->select(['m.surName AS surName',
                            'm.firstName AS firstName',
                            'm.street AS street',
                            'm.city AS city',
                            'm.postalCode AS postalCode',
                            'm.fixedLinePhone AS phone',
                            'm.nobilePhone AS cellphone'])
      ->where('m.Id = :id')
      ->setParameter('id', $musicianId)
      ->getQuery()->execute()[0];
    return $address;
  }

  /**Fetch the name and email of the respective musician.
   *
   * Return value is a flat array:
   *
   * array('firstName' => ...,
   *       'lastName' => ...,
   *       'email' => ...);
   */
  public function findName($musicianId)
  {
    $qb = $this->createQueryBuilder('m');
    $name = $qb->select(['m.lastName AS lastName',
                         'm.firstName AS firstName',
                         'm.email AS email'])
      ->where('m.Id = :id')
      ->setParameter('id', $musicianId)
      ->getQuery()->execute()[0];
    return $name;
  }

  public function findPhoto($musicianId)
  {

  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
