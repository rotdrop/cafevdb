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
use \Doctrine\Common\Collections;

trait FindLikeTrait
{
  // use LogTrait;

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
    , ?array $orderBy = null
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
  protected static function addOrderBy(ORM\QueryBuilder $qb, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $alias = null): ORM\QueryBuilder
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

  /**
   * Add some "missing" convenience stuff to findBy()
   *
   * - allow automatic filtering by association fields
   * - allow sorting by association fields
   * - allow indexing by adding a 'INDEX' option to $orderBy.
   * - allow wild-cards, uses "LIKE" in comparison. '*' and '%' are
   *   allowed wild-cards, wher '*' is internally simply replaced by
   *   '%'.
   * - ```'!FIELD' => SOMETHING``` will just invert the criterion
   * - supports Collections\Criteria, these are applied at the end.
   *
   * In order to filter by empty collections a left-join with the
   * main-table is performed.
   *
   * @return array The found entities.
   */
  public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
  {
    // filter out instances of criteria
    $collectionCriteria = [];
    foreach ($criteria as $key => $value) {
      if ($value instanceof Collections\Criteria ) {
        $collectionCriteria[] = $value;
        unset($criteria[$key]);
      }
    }
    // find "nots" for convenience
    $nots = [];
    foreach ($criteria as $key => $value) {
      $notPos = strpos($key, '!');
      if ($notPos === 0) {
        unset($criteria[$key]);
        $key = substr($key, 1);
        $nots[$key] = true;
        $criteria[$key] = $value;
      }
    }
    // walk through criteria and find associations ASSOCIATION.FIELD
    $joinEntities = [];
    foreach ($criteria as $key => $value) {
      $dotPos = strpos($key, '.');
      if ($dotPos !== false) {
        $joinEntities[substr($key, 0, $dotPos)] = true;
      }
    }
    $indexBy = [];
    foreach ($orderBy as $key => $ordering) {
      $dotPos = strpos($key, '.');
      if ($dotPos !== false) {
        $tableAlias = substr($key, 0, $dotPos);
        $field = $key;
        $joinEntities[$tableAlias] = true;
      } else {
        $tableAlias = 'mainTable';
        $field = $tableAlias.'.'.$key;
      }
      if (strtoupper($ordering) == 'INDEX') {
        $indexBy[$tableAlias] = $field;
        unset($orderBy[$key]);
      }
    }

    // $this->log(print_r($joinEntities, true).' / '.print_r($indexBy, true));

    if (empty($joinEntities) && empty($indexBy)) {
      // vanilla
      return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    $qb = $this->createQueryBuilder('mainTable', $indexBy['mainTable']);
    foreach (array_keys($joinEntities) as $association) {
      $qb->leftJoin('mainTable.'.$association, $association, null, null, $indexBy[$associaion]);
    }
    $andX = $qb->expr()->andX();
    foreach ($criteria as $key => &$value) {
      $dotPos = strpos($key, '.');
      if ($dotPos !== false) {
        $tableAlias = substr($key, 0, $dotPos);
        $field = $key;
      } else {
        $tableAlias = 'mainTable';
        $field = $tableAlias.'.'.$key;
      }
      $param = str_replace('.', '_', $field);
      if ($value === null) {
        $expr = $qb->expr()->isNull($field);
      } else if (is_array($value)) {
        $expr = $qb->expr()->in($field, ':'.$param);
      } else if (is_string($value)) {
        $value = str_replace('*', '%', $value);
        if (strpos($value, '%') !== false) {
          $expr = $qb->expr()->like($field, ':'.$param);
        } else {
          $expr = $qb->expr()->eq($field, ':'.$param);
        }
      } else {
        $expr = $qb->expr()->eq($field, ':'.$param);
      }
      if (!empty($nots[$key])) {
        $expr = $qb->expr()->not($expr);
      }
      $andX->add($expr);
    }
    $qb->where($andX);
    foreach ($criteria as $key => $value) {
      if ($value === null)  {
        continue;
      }
      if ($dotPos !== false) {
        $tableAlias = substr($key, 0, $dotPos);
        $field = $key;
      } else {
        $tableAlias = 'mainTable';
        $field = $tableAlias.'.'.$key;
      }
      $param = str_replace('.', '_', $field);
      $qb->setParameter($param, $value);
    }
    self::addOrderBy($qb, $orderBy, $limit, $offset, 'mainTable');

    foreach ($collectionCriteria as $criteria) {
      $qb->addCriteria($criteria);
    }

    // $this->log('SQL '.$qb->getQuery()->getSql());
    // $this->log('PARAM '.print_r($qb->getQuery()->getParameters(), true));

    return $qb->getQuery()->getResult();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
