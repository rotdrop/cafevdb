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
  use LogTrait;
  private static $modifiers = [
    '!' => 'not',
  ];
  private static $comparisons = [
    '<=' => 'lte',
    '>=' => 'gte',
    '=' => 'eq',
    '<' => 'lt',
    '>' => 'gt',
  ];

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
   * - allow filtering by association fields. Association-fields are
   *   simply accessed by dot-notation, e.g. 'musician.name' where
   *   'musician' in this example is the name of the property in the
   *   master entity.
   * - allow sorting by association fields
   * - allow indexing by adding an 'INDEX' option to $orderBy.
   * - allow wild-cards, uses "LIKE" in comparison. '*' and '%' are
   *   allowed wild-cards, where '*' is internally simply replaced by
   *   '%'.
   * - allow the basic comparators =, <, >, ! in prefix notation,
   *   e.g. ```!>FIELD => SOMETHING``` will be translated to the
   *   expression ```!(FIELD > SOMETHING)```
   * - in particulular ```'!FIELD' => SOMETHING``` will just negate
   *   the criterion
   * - supports Collections\Criteria, these are applied at the end.
   *
   * In order to filter by empty collections a left-join with the
   * main-table is performed.
   *
   * @return array The found entities.
   */
  public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
  {
    $queryParts = $this->prepareFindBy($criteria, $orderBy);

    // The stock findBy() / findOneBy() functions do not use query-hints, see
    // https://github.com/doctrine/orm/issues/6751
    if (empty($this->getEntityManager()->getConfiguration()->getDefaultQueryHints())
        && empty($queryParts['joinEntities'])
        && empty($queryParts['indexBy'])
        && empty($queryParts['modifiers'])
        && empty($queryParts['collectionCriteria'])) {
      // vanilla
      return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    $qb = $this->generateFindBySelect($queryParts);

    // joining may produce excessive extra columns, try to group by the primary keys.
    foreach ($this->getClassMetadata()->identifier as $field) {
      $qb->addGroupBy('mainTable.'.$field);
    }

    $this->generateFindByWhere($qb, $queryParts);

    // $this->log('SQL '.$qb->getQuery()->getSql());
    // $this->log('PARAM '.print_r($qb->getQuery()->getParameters(), true));

    return $qb->getQuery()->getResult();
  }


  public function findOneBy(array $criteria, ?array $orderBy = null)
  {
    list($result,) = $this->findBy($criteria, $orderBy, 1, 0);
    return $result;
  }

  /**
   * Parse the criteria
   */
  protected function prepareFindBy(array $criteria, ?array $orderBy = null)
  {
    $orderBy = $orderBy?:[];

    // filter out instances of criteria
    $collectionCriteria = [];
    foreach ($criteria as $key => $value) {
      if ($value instanceof Collections\Criteria ) {
        $collectionCriteria[] = $value;
        unset($criteria[$key]);
      }
    }
    // find modifiers, !=<>
    $modifiers = [];
    $comparators = [];
    foreach ($criteria as $key => $value) {
      if (!preg_match('/^[!=<>]+/', $key, $matches)) {
        continue;
      }
      $operators = $matches[0];
      unset($criteria[$key]);
      $key = substr($key, strlen($operators));
      $criteria[$key] = $value;
      while (!empty($operators)) {
        foreach (self::$modifiers as $abbr => $modifier) {
          $pos = strpos($operators, $abbr);
          if ($pos === 0) {
            $operators = substr($operators, strlen($abbr));
            $modifiers[$key][] = $modifier;
          }
          if (empty($operators)) {
            break 2;
          }
        }
        foreach (self::$comparisons as $abbr => $comparator) {
          $pos = strpos($operators, $abbr);
          if ($pos === 0) {
            if (!empty($comparators[$key])) {
              throw new \Exception('Comparison for key "'.$key.'" already set to "'.$comparators[$key].'".');
            }
            if ($abbr == '=' && $value === null) {
              throw new \Exception('Comparison with null is undefined, only equality is allowed.');
            }
            if (is_array($value)) {
              throw new \Exception('Array-valued comparisons are not allowed.');
            }
            $operators = substr($operators, strlen($abbr));
            $comparators[$key] = $comparator;
          }
          if (empty($operators)) {
            break 2;
          }
        }
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

    // modify and also return
    $queryParts = [
      'criteria' => $criteria,
      'orderBy' => $orderBy,
      'joinEntities' => $joinEntities,
      'indexBy' => $indexBy,
      'modifiers' => $modifiers,
      'comparators' => $comparators,
      'collectionCriteria' => $collectionCriteria,
    ];

    return $queryParts;
  }

  /**
   * Compute the select-part of the findBy() query.
   *
   * @param array $queryParts As returned by prepareFindBy().
   *
   * @param array|null $select Non-default select. If not given the
   * only the mainTable entity is selected.
   *
   * @return ORM\QueryBuilder The initialized query-builder.
   */
  protected function generateFindBySelect(array $queryParts, ?array $select = null)
  {
    $indexBy = $queryParts['indexBy']?:[];
    $qb = $this->createQueryBuilder('mainTable', $indexBy['mainTable']);
    foreach (array_keys($queryParts['joinEntities']) as $association) {
      $qb->leftJoin('mainTable.'.$association, $association, null, null, $indexBy[$association]);
    }
    if (!empty($select)) {
      $qb->select($select);
    }
    return $qb;
  }

  /**
   * Compute the where-part of a query. The query builder must have
   * been initialized with the main table alias 'mainTable' and
   * already contain the select part.
   */
  protected function generateFindByWhere(ORM\QueryBuilder $qb, array $queryParts)
  {
    // unpack parameter array
    foreach ($queryParts as $key => $value) {
      ${$key} = $value;
    }

    if (!empty($criteria)) {
      $andX = $qb->expr()->andX();
      foreach ($criteria as $key => &$value) {
        $literal[$key] = false;
        $dotPos = strpos($key, '.');
        if ($dotPos !== false) {
          $tableAlias = substr($key, 0, $dotPos);
          $field = $key;
        } else {
          $tableAlias = 'mainTable';
          $field = $tableAlias.'.'.$key;
        }
        $param = str_replace('.', '_', $field);
        $comparator = $comparators[$key]?:'eq';
        if ($value === null) {
          $literal[$key] = true;
          $expr = $qb->expr()->isNull($field);
        } else if (is_array($value)) {
          // special case empty array:
          // - in principle always FALSE (nothing is in an empty set)
          // - FIELD == NULL ? NULL in any given set would be FALSE, we
          //   keep it that way, even if the set is empty
          if (empty($value)) {
            $literal[$key] = true;
            // unfortunately, a literal 0 just cannot be modelled with the query builder
            $expr = $qb->expr()->eq(1, 0);
          } else {
            $expr = $qb->expr()->in($field, ':'.$param);
          }
        } else if (is_string($value)) {
          $value = str_replace('*', '%', $value);
          if (strpos($value, '%') !== false) {
            $expr = $qb->expr()->like($field, ':'.$param);
          } else {
            $expr = $qb->expr()->$comparator($field, ':'.$param);
          }
        } else {
          $expr = $qb->expr()->$comparator($field, ':'.$param);
        }
        foreach ($modifiers[$key] as $modifier) {
          $expr = $qb->expr()->$modifier($expr);
        }
        $andX->add($expr);
      }
      $qb->where($andX);
      unset($value); // break reference
    }

    foreach ($criteria as $key => $value) {
      if ($literal[$key])  {
        continue;
      }
      $dotPos = strpos($key, '.');
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

    foreach ($collectionCriteria as $selectableCriteria) {
      $qb->addCriteria($selectableCriteria);
    }

    return $qb;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
