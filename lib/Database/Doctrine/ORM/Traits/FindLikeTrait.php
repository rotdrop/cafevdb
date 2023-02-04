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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use BackedEnum;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\Expr;
use \OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;
use OCA\CAFEVDB\Exceptions\DatabaseException;

/** Trait for entity repositories which adds kind of a symbolic query "language". */
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
   * @param array $criteria Search criteria.
   *
   * @param array $orderBy Order-by criteria.
   *
   * @param int|null $limit Limit on the number of results.
   *
   * @param int|null $offset Offset into the result set.
   *
   * @return array
   */
  public function findLike(
    array $criteria,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $offset = null,
  ): array {
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
   * @param array $criteria Search criteria.
   *
   * @return null|object
   */
  public function findOneLike(array $criteria)
  {
    $results = $this->findLike($criteria, null, 1, null);
    if (count($results) == 1) {
      return reset($results);
    } else {
      return null;
    }
  }

  /**
   * Adds an order-by phrase and limits to the given query-builder.
   *
   * @param ORM\QueryBuilder $qb The ORM query builder instance.
   *
   * @param null|array $orderBy Order-by criteria.
   *
   * @param int|null $limit Limit the number of results.
   *
   * @param int|null $offset Offset into the result set.
   *
   * @param string|null $alias Table alias to prepend to the
   * field-names in $orderBy. The alias is not added if the field
   * names already contain a field-separator.
   *
   * @return ORM\QueryBuilder
   */
  protected static function addOrderBy(
    ORM\QueryBuilder $qb,
    ?array $orderBy = null,
    ?int $limit = null,
    ?int $offset = null,
    ?string $alias = null
  ): ORM\QueryBuilder {
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
   * - criteria can be passed as FIELD_EXPRESSION => VALUE or if this would
   *   yield non-unique array keys also as pairs [ FIELD_EXPRESSION => VALUE ]
   *
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
   *       ')' => ANYTHING       //              ) close the AND-group
   *                             // implicitly close the first or group
   *   ],
   *   ```
   * - supports Collections\Criteria, these are applied at the end.
   *
   * - support group-functions in order to collect grouped data, e.g.
   *   ```
   *   [ foo.bar@GROUP_CONCAT(%s) => '%SEARCH%' ]
   *   ```
   *   Such search criteria will end up in the having clause.
   *
   * - support ordinary-functions in the where part with the syntax
   *   ```
   *   [ foo.bar#BIN_TO_UUID(%s) => '%SEARCH%' ]
   *   ```
   *   The difference to the '@' syntax is that these criteria will end up in
   *   the WHERE clause.
   *
   * - support explicit type specification for "complicated" cases where a
   *   mere string conversion would leads to wrong results, e.g.
   *   ```[ foo.bar:uuid_binary => Uuid::NIL ]```
   *   Automatic type-deduction is used only for fields of the main-table.
   *
   * In order to filter by empty collections a left-join with the
   * main-table is performed.
   *
   * @param array $criteria Search criteria.
   *
   * @param null|array $orderBy Order-by criteria.
   *
   * @param int|null $limit Limit on the number of results.
   *
   * @param int|null $offset Offset into the result set.
   *
   * @return array The found entities.
   */
  public function findBy(
    array $criteria,
    ?array $orderBy = null,
    $limit = null,
    $offset = null,
  ) {
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

    if (count($queryParts['joinEntities']) > 0) {
      // joining may produce excessive extra columns, try to group by the primary keys.
      foreach ($this->getClassMetadata()->identifier as $field) {
        $qb->addGroupBy('mainTable.'.$field);
      }
    }

    $this->generateFindByWhere($qb, $queryParts, $limit, $offset);

    // $this->log('SQL '.$qb->getQuery()->getSql());
    // $this->log('PARAM '.print_r($qb->getQuery()->getParameters()->toArray(), true));

    return $qb->getQuery()->getResult();
  }

  /**
   * Count the number of rows which would be returned.
   *
   * @param array $criteria Search criteria.
   *
   * @return int The number of found entities.
   */
  public function count(array $criteria):int
  {
    $queryParts = $this->prepareFindBy($criteria);

    // The stock findBy() / findOneBy() functions do not use query-hints, see
    // https://github.com/doctrine/orm/issues/6751
    if (empty($this->getEntityManager()->getConfiguration()->getDefaultQueryHints())
        && empty($queryParts['joinEntities'])
        && empty($queryParts['indexBy'])
        && empty($queryParts['modifiers'])
        && empty($queryParts['collectionCriteria'])) {
      // vanilla
      return parent::count($criteria);
    }

    // using COUNT(DISTINCT) avoids the use of group-by.
    $qb = $this->generateFindBySelect($queryParts, [ 'COUNT(DISTINCT mainTable)', ]);

    $this->generateFindByWhere($qb, $queryParts);

    return $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Find just one or no entity matching the criteria.
   *
   * @param array $criteria Search criteria.
   *
   * @param null|array $orderBy Order-by criteria.
   *
   * @return null|object The single result or null.
   *
   * @see findBy()
   */
  public function findOneBy(array $criteria, ?array $orderBy = null)
  {
    $result = $this->findBy($criteria, $orderBy, 1, 0);
    return $result[0] ?? null;
  }

  /**
   * Parse the criteria.
   *
   * @param array $criteria Search criteria.
   *
   * @param null|array $orderBy Order-by criteria.
   *
   * @return null|object The single result or null.
   *
   * @see findBy()
   */
  protected function prepareFindBy(array $criteria, ?array $orderBy = null)
  {
    $orderBy = $orderBy?:[];

    // filter out instances of criteria
    $collectionCriteria = [];
    foreach ($criteria as $key => $value) {
      if ($value instanceof Collections\Criteria) {
        $collectionCriteria[] = $value;
        unset($criteria[$key]);
      }
    }

    $whereCriteria = [];
    $havingCriteria = [];
    foreach ($criteria as $key => $value) {
      // if key is numeric we assume $value is a pair [ EXPR => VALUE ]
      if (is_numeric($key)) {
        $key = array_key_first($value);
        $value = $value[$key];
      }
      $groupFunction = null;
      $fctPos = strpos($key, '@');
      if ($fctPos !== false) {
        $groupFunction = substr($key, $fctPos + 1);
        $key = substr($key, 0, $fctPos);
      }
      $sqlFunction = null;
      $fctPos = strpos($key, '#');
      if ($fctPos !== false) {
        $sqlFunction = substr($key, $fctPos +1);
        $key = substr($key, 0, $fctPos);
      }
      $fieldType = null;
      $typePos = strpos($key, ':');
      if ($typePos !== false) {
        $fieldType = substr($key, $typePos + 1);
        $key = substr($key, 0, $typePos);
      }

      $criterion = [
        'field' => $key,
        'fieldType' => $fieldType,
        'value' => $value,
        'modifiers' => [],
        'junctors' => [],
        'comparator' => null,
        'literal' => false,
        'index' => count($whereCriteria) + count($havingCriteria),
        'groupFunction' => $groupFunction,
        'sqlFunction' => $sqlFunction,
      ];

      if (preg_match('/^[!=<>&|()]+/', $key, $matches)) {

        $operators = $matches[0];
        $key = substr($key, strlen($operators));

        $criterion['field'] = $key;

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
              $criterion['junctors'] = array_merge($criterion['junctors'], $junctor);
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
              $criterion['modifiers'][] = $modifier;
            }
            if (empty($operators)) {
              break 2;
            }
          }
          foreach (self::$comparisons as $abbr => $comparator) {
            $pos = strpos($operators, $abbr);
            if ($pos === 0) {
              if (!empty($criterion['comparator'])) {
                throw new DatabaseException('Comparison for key "' . $key . '" already set to "' . $criterion['comparator'] . '".');
              }
              if ($abbr == '=' && $value === null) {
                throw new DatabaseException('Comparison with null is undefined, only equality is allowed.');
              }
              if (is_array($value)) {
                throw new DatabaseException('Array-valued comparisons are not allowed.');
              }
              $operators = substr($operators, strlen($abbr));
              $criterion['comparator'] = $comparator;
            }
            if (empty($operators)) {
              break 2;
            }
          }
        }
      } // !empty($operators)

      if ($groupFunction === null) {
        $whereCriteria[] = $criterion;
      } else {
        $havingCriteria[] = $criterion;
      }
    }

    // walk through criteria and find associations ASSOCIATION.FIELD
    $joinEntities = [];
    foreach (array_merge($whereCriteria, $havingCriteria) as $criterion) {
      $field = $criterion['field'];
      $dotPos = strpos($field, '.');
      if ($dotPos !== false) {
        $joinEntities[substr($field, 0, $dotPos)] = true;
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
      'criteria' => [
        'where' => $whereCriteria,
        'having' => $havingCriteria,
      ],
      'orderBy' => $orderBy,
      'joinEntities' => $joinEntities,
      'indexBy' => $indexBy,
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
   * @param ORM\QueryBuilder $qb The ORM query builder instance.
   *
   * @param array $queryParts As returned by prepareFindByWhere().
   *
   * @param int|null $limit Limit the number of results.
   *
   * @param int|null $offset Offset into the result set.
   *
   * @return ORM\QueryBuilder
   *
   * @todo Field-type deduction for set-parameter only works for the
   * main-table ATM. This could probably be cured by reading the
   * meta-data for all join tables.
   *
   * PHPMD cannot handle dynamic variable names
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  protected function generateFindByWhere(
    ORM\QueryBuilder $qb,
    array $queryParts,
    ?int $limit = null,
    ?int $offset = null,
  ):ORM\QueryBuilder {

    // unpack parameter array
    // foreach ($queryParts as $key => $part) {
    //   ${$key} = $part;
    // }
    extract($queryParts);

    foreach (['where', 'having'] as $conditionType) {

      if (!empty($criteria[$conditionType])) {
        $groupExpression = [
          0 => [
            'junctor' => 'andX',
            'components' => [],
          ]
        ];
        $groupLevel = 0;

        foreach ($criteria[$conditionType] as &$criterion) {

          $field = $criterion['field'];
          $value = $criterion['value'];

          // $this->log('FIELD ' . $conditionType . ': ' . $field);

          foreach ($criterion['junctors'] as $junctor) {
            if ($junctor !== ')') {
              $expression = [
                'junctor' => $junctor,
                'components' => [],
              ];
              $groupExpression[++$groupLevel] = $expression;
            } else {
              $expression = array_pop($groupExpression);
              $groupLevel--;
              $junctor = $expression['junctor'];
              $components = $expression['components'];
              $compositeExpr = call_user_func_array([ $qb->expr(), $junctor ], $components);
              $groupExpression[$groupLevel]['components'][] = $compositeExpr;
            }
          }
          if (empty($field)) {
            continue;
          }
          // $this->log('FIELD ' . $field);
          $dotPos = strpos($field, '.');
          if ($dotPos !== false) {
            $tableAlias = substr($field, 0, $dotPos);
          } else {
            $tableAlias = 'mainTable';
            $field = $tableAlias . '.' . $field;
          }
          $param = str_replace('.', '_', $field) . '_' . $criterion['index'];
          if (!empty($criterion['groupFunction'])) {
            $field = sprintf($criterion['groupFunction'], $field);
          } elseif (!empty($criterion['sqlFunction'])) {
            $field = sprintf($criterion['sqlFunction'], $field);
          }
          $comparator = $criterion['comparator'] ?? 'eq';
          if ($value === null) {
            $criterion['literal'] = true;
            $expr = $qb->expr()->isNull($field);
          } elseif (is_array($value)) {
            // special case empty array:
            // - in principle always FALSE (nothing is in an empty set)
            // - FIELD == NULL ? NULL in any given set would be FALSE, we
            //   keep it that way, even if the set is empty
            if (empty($value)) {
              $criterion['literal'] = true;
              // unfortunately, a literal 0 just cannot be modelled with the query builder
              $expr = $qb->expr()->eq(1, 0);
            } elseif ($value instanceof \BackedEnum) {
              $expr = $qb->expr()->in($field, ':' . $param);
            } else {
              // array values could contain wildcards
              if (!empty(array_filter($value, fn($x) => !($x instanceof BackedEnum) && (str_contains($x, '%') || str_contains($x, '*'))))) {
                $value = implode('|', array_map(fn($x) => str_replace(['%', '*'], ['.*', '.*'], preg_quote($x)), $value));
                $value = '^' . $value . '$';
                $expr = $qb->expr()->eq(new Expr\Func('REGEXP', [ $field, ':' . $param ]), 1);
                $criterion['value'] = $value;
              } else {
                $expr = $qb->expr()->in($field, ':' . $param);
              }
            }
          } elseif (is_string($value)) {
            $value = str_replace('*', '%', $value);
            if (strpos($value, '%') !== false) {
              $expr = $qb->expr()->like($field, ':' . $param);
              $criterion['value'] = $value;
            } else {
              $expr = $qb->expr()->$comparator($field, ':' . $param);
            }
          } else {
            $expr = $qb->expr()->$comparator($field, ':' . $param);
          }
          foreach ($criterion['modifiers'] as $modifier) {
            $expr = $qb->expr()->$modifier($expr);
          }
          $groupExpression[$groupLevel]['components'][] = $expr;
        }

        // closing parenthesis maybe omitted at end, can be arbitrary many
        while ($groupLevel >= 0) {
          $expression = array_pop($groupExpression);
          $groupLevel--;
          $junctor = $expression['junctor'];
          $components = $expression['components'];
          $compositeExpr = call_user_func_array([ $qb->expr(), $junctor ], $components);
          if ($groupLevel >= 0) {
            $groupExpression[$groupLevel]['components'][] = $compositeExpr;
          } else {
            $qb->{$conditionType}($compositeExpr);
          }
        }
        unset($criterion); // break reference
      }

      foreach ($criteria[$conditionType] as $criterion) {
        if ($criterion['literal']) {
          continue;
        }
        $field = $criterion['field'];
        if (empty($field)) {
          continue;
        }
        // $this->log('PARAMETER ' . $field);
        $dotPos = strpos($field, '.');
        if ($dotPos !== false) {
          $tableAlias = substr($field, 0, $dotPos);
          $column = substr($field, $dotPos+1);
        } else {
          $tableAlias = 'mainTable';
          $column = $field;
          $field = $tableAlias . '.' . $column;
        }
        $param = str_replace('.', '_', $field) . '_' . $criterion['index'];

        $value = $criterion['value'];
        $fieldType = null;
        if ($criterion['fieldType'] !== null) {
          $fieldType = $criterion['fieldType'];
        } elseif ($tableAlias == 'mainTable') {
          if (!is_array($value)
              && empty($criterion['groupFunction'])
              && empty($criterion['sqlFunction'])) {
            // try to deduce the type as this is NOT done by ORM here
            $fieldType = $this->getClassMetadata()->getTypeOfField($column);
          }
        }
        $qb->setParameter($param, $value, $fieldType);
      }
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
