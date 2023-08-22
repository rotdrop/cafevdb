<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Criteria;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\AbstractQuery;

/**
 * Repository for musicians.
 *
 * @method null|Entities\Musician find(int $d)
 */
class MusiciansRepository extends EntityRepository
{
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
   * @param array $criteria
   *
   * @return mixed
   */
  private function generateIdQuery(array $criteria = [])
  {
    $queryParts = $this->prepareFindBy($criteria, [
      'id' => 'ASC',
    ]);

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [ 'mainTable.id' ]);
    $qb = $this->generateFindByWhere($qb, $queryParts);

    return $qb->getQuery();
  }

  /**
   * @param array $criteria
   *
   * @return array
   */
  public function fetchIds(array $criteria = [])
  {
    $query = $this->generateIdQuery($criteria);

    return $query->getResult('COLUMN_HYDRATOR');
  }

  /**
   * @param mixed $uuid
   *
   * @return Entities\Musician
   */
  public function findIdByUUID(mixed $uuid)
  {
    $query = $this->generateIdQuery([ 'uuid' => $uuid ]);
    return $query->getSingleScalarResult();
  }

  /**
   * @param string $userId
   *
   * @return null|Entities\Musician
   */
  public function findIdByUserId(string $userId)
  {
    $query = $this->generateIdQuery([ 'userIdSlug' => $userId ]);
    $result = $query->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR);

    return $result['id'] ?? null;
  }

  /**
   * @param mixed $uuid
   *
   * @return Entities\Musician
   */
  public function findByUUID(mixed $uuid)
  {
    return $this->findOneBy([ 'uuid' => $uuid ]);
  }

  /**
   * @param string $userId
   *
   * @return Entities\Musician
   */
  public function findByUserId(string $userId)
  {
    return $this->findOneBy([ 'userIdSlug' => $userId ]);
  }

  /**
   * Fetch the street address of the respected musician. Needed in
   * order to generate automated snail-mails.
   *
   * @param int $musicianId
   *
   * @return array
   * Return value is a flat array:
   * ```
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'street' => ...,
   *       'city' => ...,
   *       'ZIP' => ...);
   * ```.
   */
  public function findStreetAddress(int $musicianId)
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

  /**
   * Fetch the name and email of the respective musician.
   *
   * @param int $musicianId
   *
   * @return array Return value is a flat array:
   * ```
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'email' => ...);
   * ```.
   */
  public function findName(int $musicianId)
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
   * @param string $indexBy
   *
   * @return null|array
   */
  public function fetchLastModifiedDate(array $criteria = [], string $indexBy = 'uuid')
  {
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

    return $qb->getQuery()->getOneOrNullResult();
  }

  /**
   * Find musicians by their instruments, identified by the instrument
   * ids.
   *
   * @param array<int, int> $instrumentIds
   *
   * @param array|null $orderBy ```[ KEY => ORDERING ]```.
   *
   * @param int|null $limit Result-set limit.
   *
   * @param int|null $offset Result-set offset.
   *
   * @param string $indexBy Result by default are indexed by id.
   *
   * @return array<int, Entities\Musician>
   */
  public function findByInstruments(
    array $instrumentIds,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $offset = null,
    string $indexBy = 'id',
  ): array {
    return $this->findBy(
      [ 'instruments.indtrument' => $instrumentIds ],
      [ 'id' => 'INDEX' ]);
  }

  /**
   * Create search criteria by instrument ids. The search field is
   * "instrument" and generally is a collection of Entities\Instrument.
   *
   * @param array<int, int> $instrumentIds
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
