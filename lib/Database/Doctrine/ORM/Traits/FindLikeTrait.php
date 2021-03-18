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
   * - allow automatic filtering by association fields
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
    // filter out instances of criteria
    $collectionCriteria = [];
    foreach ($criteria as $key => $value) {
      if ($value instanceof Collections\Criteria ) {
        $collectionCriteria[] = $value;
        unset($criteria[$key]);
      }
    }
    // find modifiers (only [ '!' => 'not' ] ATM)
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

    // $this->log(print_r($joinEntities, true).' / '.print_r($indexBy, true));

    if (empty($joinEntities) && empty($indexBy) && empty($modifiers)) {
      // vanilla
      return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    $qb = $this->createQueryBuilder('mainTable', $indexBy['mainTable']);
    foreach (array_keys($joinEntities) as $association) {
      $qb->leftJoin('mainTable.'.$association, $association, null, null, $indexBy[$associaion]);
    }

    // joining may produce excessive extra columns, try to group by the primary keys.
    foreach ($this->getClassMetadata()->identifier as $field) {
      $qb->groupBy('mainTable.'.$field);
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
      $comparator = $comparators[$key]?:'eq';
      if ($value === null) {
        $expr = $qb->expr()->isNull($field);
      } else if (is_array($value)) {
        $expr = $qb->expr()->in($field, ':'.$param);
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
    foreach ($criteria as $key => $value) {
      if ($value === null)  {
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
