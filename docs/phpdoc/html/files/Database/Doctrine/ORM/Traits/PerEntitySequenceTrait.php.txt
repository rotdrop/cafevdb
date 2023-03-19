<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\QueryBuilder;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Types;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager as DecoratedEntityManager;

use OCA\CAFEVDB\Exceptions;

/**
 * A special trait for a repository for entities with one primary foreign key
 * and a sequence wich forms a composite key together with the "primary"
 * foreign key.
 */
trait PerEntitySequenceTrait
{
  /** @var string */
  protected $sequenceFieldName;

  /** @var arry */
  protected $nonSequenceNames = [];

  /**
   * Determine the name of the (single) sequence field.
   *
   * @throws Exceptions\DatabaseInvalidFieldException
   *
   * @return void
   */
  protected function getSequenceField():void
  {
    /** @var ORM\ClassMetadata $meta */
    $meta = $this->getClassMetaData();
    $identifiers = $meta->getIdentifierFieldNames();
    foreach ($identifiers as $identifierField) {
      if (isset($meta->fieldMappings[$identifierField])) {
        if ($meta->fieldMappings[$identifierField]['type'] != Types::INTEGER) {
          throw new Exceptions\DatabaseInvalidFieldException('The single sequence field must have integral type');
        }
        $this->sequenceFieldName = $identifierField;
      } else {
        $this->nonSequenceNames[] = $identifierField;
      }
    }
  }

  /**
   * Get the highest sequence number of the given entity.
   *
   * @param string|array $nonSequenceKeys
   *
   * @return int
   */
  public function sequenceMax(mixed $nonSequenceKeys):int
  {
    if (!is_array($nonSequenceKeys)) {
      $nonSequenceKeys = [ reset($this->nonSequenceNames) => $nonSequenceKeys ];
    }
    /** @var QueryBuilder $qb */
    $qb = $this->createQueryBuilder('e')
      ->select('MAX(e.' . $this->sequenceFieldName . ') AS sequence');
    $andX = $qb->expr()->andX();
    foreach ($this->nonSequenceNames as $key) {
      $andX->add($qb->expr()->eq('e.' . $key, ':' . $key));
    }
    foreach ($nonSequenceKeys as $key => $value) {
      $qb->setParameter($key, $value);
    }
    return $qb->where($andX)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Persist the given entity with the next sequence by using a simple
   * brute-force method. The entity the entity belongs to needs to
   * be set in the entity. If the $entity already has a sequence
   * attached, then it is simply persisted.
   *
   * @param mixed $entity
   *
   * @return mixed Return the persisted entity.
   *
   * @throws Doctrine\DBAL\Exception\UniqueConstraintViolationException
   */
  protected function persistEntity(mixed $entity)
  {
    /** @var EntityManager $entityManager */
    $entityManager = $this->getEntityManager();

    /** @var ORM\ClassMetadata $meta */
    $meta = $this->getClassMetaData();
    $sequenceValue = $meta->getFieldValue($entity, $this->sequenceFieldName);

    if ($sequenceValue !== null) {
      $entityManager->persist($entity);
      return $entity;
    }

    $filters = $entityManager->getFilters();
    $softDeleteable = $filters->isEnabled(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    if ($softDeleteable) {
      $filters->disable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    $nonSequenceValues = [];
    foreach ($this->nonSequenceNames as $nonSequenceField) {
      $nonSequenceValue = $meta->getFieldValue($entity, $nonSequenceField);
      // @todo Is the following really necesary, do we need to convert to references?
      if (isset($meta->associationMappings[$nonSequenceField])) {
        $targetEntity = $meta->associationMappings[$nonSequenceField]['targetEntity'];
        if (!$nonSequenceValue instanceof $targetEntity) {
          $nonSequenceValue = $entityManager->getReference($targetEntity, $nonSequenceValue);
        }
        $meta->setFieldValue($entity, $nonSequenceField, $nonSequenceValue);
      }
      $nonSequenceValues[$nonSequenceField] = $nonSequenceValue;
    }

    $nextSequence = 1 + $this->sequenceMax($nonSequenceValues);
    $entity->setSequence($nextSequence);
    $entityManager->persist($entity);
    $entityManager->flush();

    if ($softDeleteable) {
      $filters->enable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    return $entity;
  }
}
