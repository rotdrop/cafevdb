<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024, 2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/** Entity repository for projects. */
class ProjectsRepository extends EntityRepository
{
  // use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  const ALIAS = 'proj';

  /**
   * Sort by configured sorting column.
   *
   * @return iterable
   */
  public function findAll():array
  {
    return $this->findBy([], [
      'year' => 'DESC',
      'name' => 'ASC'
    ]);
  }

  /**
   * Find a project by its Id.
   *
   * @param array|int $projectIdentifier This may either be an integer -- the
   * plain id -- or "something" array-like with an 'id' index.
   *
   * @return null|Entities\Project
   *
   * @throws Exceptions\DatabaseMissingIdentifierException
   */
  public function findById(int|array $projectIdentifier):?Entities\Project
  {
    return $this->findByIdOrName($projectIdentifier);
  }

  /**
   * Find a project by its Id or name.
   *
   * @param int|string|array $projectIdentifier A project-id, a project-name
   * or an array with keys 'id' or 'name'.
   *
   * @return null|Entities\Project
   *
   * @throws Exceptions\DatabaseMissingIdentifierException
   */
  public function findByIdOrName(int|string|array $projectIdentifier):?Entities\Project
  {
    $id = null;
    $name = null;
    if (filter_var($projectIdentifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
      $id = $projectIdentifier;
    } elseif (is_string($projectIdentifier)) {
      $name = $projectIdentifier;
    } elseif (is_array($projectIdentifier)) {
      $id = $projectIdentifier['id'] ?? null;
      $name = $projectIdentifier['name'] ?? null;
    }
    if ($id === null && $name === null) {
      throw new Exceptions\DatabaseMissingIdentifierException(
        sprintf(self::t('The identifier is missing for a query to find an instance of "%1$s".'), $this->entityName),
        entityClassName: $this->entityName,
        incompleteIdentifier: $projectIdentifier,
      );
    }
    $criteria = [];
    if (!empty($id)) {
      $criteria[] = ['id' => $id];
    }
    if (!empty($name)) {
      $criteria[] = ['name' => $name];
    }
    return $this->findOneBy($criteria);
  }

  /**
   * Convenience function: just return the argument if it is already a
   * project entity, otherwise fetch the project, repectively generate
   * a reference.
   *
   * @param int|string|array|Entities\Project $projectOrId
   *
   * @return null|Entities\Project
   */
  public function ensureProject(int|string|array|Entities\Project $projectOrId):?Entities\Project
  {
    if ($projectOrId instanceof Entities\Project) {
      return $projectOrId;
    }
    return $this->findByIdOrName($projectOrId);
  }

  /**
   * Fetch a short description for all projects.
   *
   * @return array
   *
   * @code
   * [
   *   'projects' => [ ID => [ 'name' => NAME, 'year' => YEAR ], ... ],
   *   'nameByName' => [ NAME => NAME, ... ],
   *   'yearByName' => [ NAME => YEAR, ... ],
   * ]
   * @endcode
   *
   * nameByName is used by PME in order to construct select options etc.
   *
   * @todo Could make this a custom hydrator.
   */
  public function shortDescription()
  {
    $byId = [];
    $nameByName = [];
    $yearByName = [];
    foreach ($this->findAll() as $entity) {
      $name = $entity['Name'];
      $year = $entity['Year'];
      $byId[$entity['Id']] = [ 'name' => $name, 'year' => $year, ];
      $nameByName[$name] = $name;
      $yearByName[$name] = $year;
    }
    return [
      'projects' => $byId,
      'nameByName' => $nameByName,
      'yearByName' => $yearByName,
    ];
  }

  /**
   * Return minimum and maximum year of all projects.
   *
   * @return array<string, int>
   *
   * ```php
   * [
   *   'min' => MIN_YEAR,
   *   'max' => MAX_YEAR,
   * ]
   * ```
   */
  public function findYearRange()
  {
    $range = $this->createQueryBuilder('p')
           ->select('MIN(p.year) AS min, MAX(p.year) AS max')
           ->getQuery()
           ->getResult();
    return $range[0]; // ????
  }

  /**
   * Fetch a flat array of mailing list ids associated with the matching projects.
   *
   * @param array $criteria
   *
   * @return array
   */
  public function fetchMailingListIds(array $criteria = []):array
  {
    $criteria['!mailingListId'] = null;
    $queryParts = $this->prepareFindBy($criteria, [
      'mailingListId' => 'ASC',
    ]);

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [ 'mainTable.mailingListId' ]);
    $qb = $this->generateFindByWhere($qb, $queryParts);

    $query = $qb->getQuery();

    return $query->getResult('COLUMN_HYDRATOR');
  }

  /**
   * Return all project ids
   *
   * @return array<int, int>
   */
  public function findProjectIds()
  {
    $projectIds = $this->createQueryBuilder('p')
      ->select('p.id')
      ->orderBy('p.year', 'DESC')
      ->addOrderBy('p.name', 'ASC')
      ->getQuery()
      ->getResult('COLUMN_HYDRATOR');
    return $projectIds;
  }

  /**
   * Find project names, return as flat array.
   *
   * @param null|string|ProjectType $onlyType If given restrict to this type. Default \null.
   *
   * @param  null|string|ProjectType $excludeType If given exclude projects of this type. Defaults to ProjectType::TEMPLATE.
   *
   * @return array<int, string>
   */
  public function findNames(
    null|string|ProjectType $onlyType = null,
    null|string|ProjectType $excludeType = ProjectType::TEMPLATE,
  ): array {
    $criteria = [ 'deleted' => null ];
    if ($onlyType !== null) {
      $criteria[] = [ 'type' => $onlyType ];
    }
    if ($excludeType !== null) {
      $criteria[] = [ '!type' => $excludeType ];
    }
    $queryParts = $this->prepareFindBy(
      $criteria,
      [ 'year' => 'DESC', 'name' => 'ASC' ],
    );

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [ 'mainTable.name' ]);
    $qb = $this->generateFindByWhere($qb, $queryParts);

    $query = $qb->getQuery();

    return $query->getResult('COLUMN_HYDRATOR');
  }

  /**
   * Just query the database for the project name given its id.
   *
   * @param int|string|array $projectIdentifier A project-id, a project-name
   * or an array with keys 'id' or 'name'. Even if the name is given a recurse
   * to the database will ensure that a project with that name exists.
   *
   * @return null|string
   *
   * @throws Exceptions\DatabaseMis
   */
  public function findName(int|string|array $projectIdentifier):?string
  {
    $id = null;
    $name = null;
    if (filter_var($projectIdentifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
      $id = $projectIdentifier;
    } elseif (is_string($projectIdentifier)) {
      $name = $projectIdentifier;
    } elseif (is_array($projectIdentifier)) {
      $id = $projectIdentifier['id'] ?? null;
      $name = $projectIdentifier['name'] ?? null;
    }
    if ($id === null && $name === null) {
      throw new Exceptions\DatabaseMissingIdentifierException(
        sprintf(self::t('The identifier is missing for a query to find an instance of "%1$s".'), $this->entityName),
        entityClassName: $this->entityName,
        incompleteIdentifier: $projectIdentifier,
      );
    }
    $criteria = [];
    if (!empty($id)) {
      $criteria[] = ['id' => $id];
    }
    if (!empty($name)) {
      $criteria[] = ['name' => $name];
    }

    $queryParts = $this->prepareFindBy($criteria);

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [ 'mainTable.name' ]);
    $qb = $this->generateFindByWhere($qb, $queryParts);

    $query = $qb->getQuery();

    $result = $query->getResult('COLUMN_HYDRATOR');

    return count($result) == 1 ? $result[0] : null;
  }
}
