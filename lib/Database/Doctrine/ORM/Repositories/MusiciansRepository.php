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

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;

class MusiciansRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait;
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * @param string $firstName First-name or display-name.
   *
   * @param string|null $surName
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
   *       'surName' => ...,
   *       'email' => ...);
   */
  public function findName($musicianId)
  {
    $qb = $this->createQueryBuilder('m');
    $name = $qb->select(['m.surName AS surName',
                         'm.firstName AS firstName',
                         'm.email AS email'])
      ->where('m.Id = :id')
      ->setParameter('id', $musicianId)
      ->getQuery()->execute()[0];
    return $name;
  }

  /**
   * Fetch the latest modification date of the given or all musicians.
   *
   * @param array $criteria Criteria as for findBy(). However,
   * wild-cards like '*' and '%' are allowed and internally converted
   * to '%' in a LIKE comparison.
   *
   * @return null|\DateTimeImmutable
   */
  public function fetchLastModifiedDate(array $criteria = [], string $indexBy = 'uuid')
  {
    if (is_int($musicianOrId)) {
      $musicianId = $musicianOrId;
    } else if (empty($musicianOrId)) {
      $musicianId = null;
    } else {
      $musicianId = $musicianOrId['id'];
    }

    $selects = [];
    if (!empty($criteria)) {
      $selects[] = 'm.'.$indexBy.' AS '.$indexBy;
    }
    $selects[]  = "GREATEST(
  MAX(COALESCE(m.updated, '')),
  MAX(COALESCE(pp.updated, '')),
  COALESCE(photo.updated, ''),
  MAX(COALESCE(mi.updated, '')))
  AS lastModified";

    $qb = $this->createQueryBuilder('m', 'm.'.$indexBy);
    $qb->select($selects)
       ->leftJoin('m.projectParticipation', 'pp')
       ->leftJoin('m.photo', 'photo')
       ->leftJoin('m.instruments', 'mi');

    if (!empty($criteria)) {
      $qb->groupBy('m.id');
      $andX = $qb->expr()->andX();
      foreach ($criteria as $key => &$value) {
        $value = str_replace('*', '%', $value);
        if (strpos($value, '%') !== false) {
          $andX->add($qb->expr()->like('m'.'.'.$key, ':'.$key));
        } else {
          $andX->add($qb->expr()->eq('m'.'.'.$key, ':'.$key));
        }
      }
      $qb->where($andX);
      foreach ($criteria as $key => $value) {
        $type = ($key == 'uuid') ? 'uuid_binary' : null;
        $qb->setParameter($key, $value, $type);
      }
    }

    // $this->log('CRIT '.print_r($criteria, true));
    // $this->log('SQL '.$qb->getQuery()->getSql());
    // $this->log('PARAM '.print_r($qb->getQuery()->getParameters(), true));

    return $qb->getQuery()->getResult();
  }

  /**
   * Find musicians by their instruments, identified by the instrument
   * ids.
   *
   * @param array<int, int> $instrumentIds
   *
   * @param array|null $orderBy ```[ KEY => ORDERING ]```
   *
   * @param int|null $limit Result-set limit
   *
   * @param int|null $offset Result-set offset
   *
   * @param string $indexBy Result by default are indexed by id.
   *
   * @return array<int, Entities\Musician>
   */
  public function findByInstruments(
    array $instrumentIds
    , ?array $orderBy = null
    , ?int $limit = null
    , ?int $offset = null
    , string $indexBy = 'id'
  ): array {
    return $this->findBy([ 'instruments.indtrument' => $instrumentIds ],
                         [ 'id' => 'INDEX' ]);
  }

  /**
   * Create search criteria by instrument ids. The search field is
   * "instrument" and generally is a collection of Entities\Instrument.
   *
   * @param array<int, int> $instrumenIds
   *
   * @param string|null $alias Optional alias to add to the field.
   *
   * @return Criteria
   */
  public static function createInstrumentsCriteria(array $instrumentIds, ?string $alias = null): Criteria
  {
    $field = ($alias ? $alias . '.' : '').instrument;
    return Criteria::create()
      ->andWhere(Criteria::expr()->in($field, $instrumentIds));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
