<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use \OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;

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
  private static $junctors = [
    '(|' => [ 'orX' ],
    '(&' => [ 'andX' ],
    '!(|' => [ 'not', 'orX', ],
    '!(&' => [ 'not', 'andX', ],
    ')' => [ ')' ],
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
   * Find one or no entity by given wild-card criteria. This is like findOne()
   * but the criterias may contain '%' or '*', in which case a LIKE
   * comparison is used.
   *
   * @param array $criteria Search criteria
   *
   * @return null|Object
   *
   */
  public function findOneLike($criteria)
  {
    return $this->findLike($criteria, null, 1, null);
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
    foreach ($orderBy??[] as $key => $dir) {
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
   * - logical junctors & and | and parenthesis are also supported: criteria can be
   *   prefixed by '(|' and '(&' to indicate the boolean junctor with the other
   *   criteria. Grouping is required by using '(' and ')'. The final closing
   *   paren may be omitted.
   *   Example:
   *   ```
   *   [
   *     ... STUFF
   *     '!fieldId' => 13,       // search for field_id != 13
   *     '(|optionValue' => ''   // and (option_value the empty string ...
   *       'optionValue' => null //      or option_value is NULL ...
   *       '(&blah' => 2',       //      or (blah is 2 ...
   *        'blub' => 3,         //          and blub is 3 ...
   *       ')(&foo' => 5         //         ) or (foo is 5 ...
   *          'bar' => 6         //               and bar is 6
   *        ')' => ANYTHING      //              ) close the AND-group
   *                             // implicitly close the first or group
   *   ],
   *   ```
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

    $this->generateFindByWhere($qb, $queryParts, $limit, $offset);

    // $this->log('SQL '.$qb->getQuery()->getSql());
    // $this->log('PARAM '.print_r($qb->getQuery()->getParameters()->toArray(), true));

    return $qb->getQuery()->getResult();
  }


  public function findOneBy(array $criteria, ?array $orderBy = null)
  {
    $result = $this->findBy($criteria, $orderBy, 1, 0);
    return $result[0] ?? null;
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
    $modifiers = []; // negation
    $comparators = [];
    $junctors = [];
    $parens = [];
    foreach ($criteria as $key => $value) {
      if (!preg_match('/^[!=<>&|()]+/', $key, $matches)) {
        $modifiers[$key] = [];
        $comparators[$key] = null;
        $junctors[$key] = [];
        $parens[$key] = null;
        continue;
      }
      $operators = $matches[0];
      unset($criteria[$key]);
      $key = substr($key, strlen($operators));
      $criteria[$key] = $value;
      $modifiers[$key] = [];
      $junctors[$key] = [];
      $parens[$key] = [];
      while (!empty($operators)) {
        // reduce multiple ! signs
        while (strpos($operators, '!!') !== false) {
          $operators = str_replace('!!', '', $operators);
        }
        // (&, (|, !(|, !(&
        foreach (self::$junctors as $abbr => $junctor) {
          $pos = strpos($operators, $abbr);
          if ($pos === 0) {
            $operators = substr($operators, strlen($abbr));
            $junctors[$key] = array_merge($junctors[$key], $junctor);
          }
          if (empty($operators)) {
            break 2;
          }
        }
        // this is only !
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
      'junctors' => $junctors,
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
    $indexBy = $queryParts['indexBy']?:['mainTable' => null];
    $qb = $this->createQueryBuilder('mainTable', $indexBy['mainTable']);
    foreach (array_keys($queryParts['joinEntities']) as $association) {
      $qb->leftJoin('mainTable.'.$association, $association, null, null, $indexBy[$association] ?? null);
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
   *
   * @todo Field-type deduction for set-parameter only works for the
   * main-table ATM. This could probably be cured by reading the
   * meta-data for all join tables.
   */
  protected function generateFindByWhere(
    ORM\QueryBuilder $qb
    , array $queryParts
    , ?int $limit = null
    , ?int $offset = null
  ) {
    // unpack parameter array
    foreach ($queryParts as $key => $part) {
      ${$key} = $part;
    }

    if (!empty($criteria)) {
      $groupExpression[0] = [
        'junctor' => 'andX',
        'components' => [],
      ];
      $groupLevel = 0;

      foreach ($criteria as $key => &$value) {

        foreach ($junctors[$key]??[] as $junctor) {
          if ($junctor !== ')') {
            $expression = [
              'junctor' => $junctor,
              'components' => [],
            ];
            $groupExpression[++$groupLevel] = $expression;
          } else {
            $expression = array_pop($groupExpression); $groupLevel--;
            $junctor = $expression['junctor'];
            $components = $expression['components'];
            $compositeExpr = call_user_func_array([ $qb->expr(), $junctor ], $components);
            $groupExpression[$groupLevel]['components'][] = $compositeExpr;
          }
        }
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
        $comparator = $comparators[$key]??'eq';
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
        $groupExpression[$groupLevel]['components'][] = $expr;
      }

      // closing parenthesis maybe omitted at end, can be arbitrary many
      while ($groupLevel >= 0) {
        $expression = array_pop($groupExpression); $groupLevel--;
        $junctor = $expression['junctor'];
        $components = $expression['components'];
        $compositeExpr = call_user_func_array([ $qb->expr(), $junctor ], $components);
        if ($groupLevel > 0) {
          $groupExpression[$groupLevel]['components'][] = $compositeExpr;
        } else {
          $qb->where($compositeExpr);
        }
      }
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

      $fieldType = null;
      if ($tableAlias == 'mainTable') {
        if (!is_array($value)) {
          // try to deduce the type as this is NOT done by ORM here
          $fieldType = $this->getClassMetadata()->getTypeOfField($key);
        }
      }
      $qb->setParameter($param, $value, $fieldType);
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
