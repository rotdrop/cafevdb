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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Doctrine\ORM;

trait FindLikeTrait
{
  /**
   * Find entities by given wild-card criteria. This is like findBy()
   * but the criterias may contain '%' or '*', in which case a LIKE
   * comparison is used.
   *
   * @param array $criteria Search criteria
   *
   * @param array $orderBy Order-by criteria
   *
   * @param int|null $limit
   *
   * @param int|null $offset
   *
   * @return array
   *
   */
  public function findLike(
    array $criteria
    , array $orderBy = []
    , ?int $limit = null
    , ?int $offset = null): array
  {
    $qb = $this->createQueryBuilder('table');
    $andX = $qb->expr()->andX();
    foreach ($criteria as $key => &$value) {
      $value = str_replace('*', '%', $value);
      if (strpos($value, '%') !== false) {
        $andX->add($qb->expr()->like('table'.'.'.$key, ':'.$key));
      } else {
        $andX->add($qb->expr()->eq('table'.'.'.$key, ':'.$key));
      }
    }
    foreach ($criteria as $key => $value) {
      $qb->setParameter($key, $value);
    }
    $qb->where($andX);

    self::addOrderBy($qb, $orderBy, $limit, $offset, 'table');

    return $qb->getQuery()->execute();
  }

  /**
   * Adds an order-by phrase and limits to the given query-builder.
   *
   * @parm ORM\QueryBuilder $qb
   *
   * @param array $orderBy Order-by criteria
   *
   * @param int|null $limit
   *
   * @param int|null $offset
   *
   * @param string|null $alias Table alias to prepend to the
   * field-names in $orderBy. The alias is not added if the field
   * names already contain a field-separator.
   *
   * @return ORM\QueryBuilder
   */
  protected static function addOrderBy(ORM\QueryBuilder $qb, array $orderBy = [], ?int $limit = null, ?int $offset = null, ?string $alias = null): ORM\QueryBuilder
  {
    foreach ($orderBy as $key => $dir) {
      if (strpos($key, '.') === false && !empty($alias)) {
        $key = $alias . '.' . $key;
      }
      $qb->addOrderBy($key, $dir);
    }
    if (!empty($limit)) {
      $qb->setMaxResults($limit);
    }
    if (!empty($offset)) {
      $qb->setFirstResult($offset);
    }
    return $qb;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
