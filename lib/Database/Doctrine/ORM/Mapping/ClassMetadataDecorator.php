<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Mapping;

use \Exception;
use \RuntimeException;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\ConversionException;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Proxy\Proxy;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\PersistentCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use OCA\CAFEVDB\Exceptions\DatabaseException;

use OCA\CAFEVDB\Database\EntityManager;

/** Counter part to the decorated entity manager. */
class ClassMetadataDecorator implements ClassMetadataInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var array */
  private $columnAssociationsCache = [];

  /** @var array */
  private $temporaryColumnStorage = [];

  /** @var bool */
  private $debug = false;

  /** {@inheritdoc} */
  public function __construct(
    private ClassMetadata $metaData,
    private EntityManager $entityManager,
    protected ILogger $logger,
    private IL10N $l,
  ) {
  }

  /**
   * @param string $message Message to print.
   *
   * @param array $context Log context.
   *
   * @param bool $showTrace Whether to show a call-stack.
   *
   * @return void
   */
  private function debug(string $message, array $context = [], bool $showTrace = false):void
  {
    if ($this->debug) {
      $this->logInfo($message, $context, 1, $showTrace);
    } else {
      $this->logDebug($message, $context, 1, $showTrace);
    }
  }

  ////////////////////////////////////
  //
  // Decorator stuff
  //
  // Things ain't that simple, we must implement all methods.

  /** {@inheritdoc} */
  public function getName()
  {
    return $this->metaData->getName();
  }

  /** {@inheritdoc} */
  public function getIdentifier()
  {
    return $this->metaData->getIdentifier();
  }

  /** {@inheritdoc} */
  public function getReflectionClass()
  {
    return $this->metaData->getReflectionClass();
  }

  /** {@inheritdoc} */
  public function isIdentifier($fieldName)
  {
    return $this->metaData->isIdentifier($fieldName);
  }

  /** {@inheritdoc} */
  public function hasField($fieldName)
  {
    return $this->metaData->hasField($fieldName);
  }

  /** {@inheritdoc} */
  public function hasAssociation($fieldName)
  {
    return $this->metaData->hasAssociation($fieldName);
  }

  /** {@inheritdoc} */
  public function isSingleValuedAssociation($fieldName)
  {
    return $this->metaData->isSingleValuedAssociation($fieldName);
  }

  /** {@inheritdoc} */
  public function isCollectionValuedAssociation($fieldName)
  {
    return $this->metaData->isCollectionValuedAssociation($fieldName);
  }

  /** {@inheritdoc} */
  public function getFieldNames()
  {
    return $this->metaData->getFieldNames();
  }

  /** {@inheritdoc} */
  public function getIdentifierFieldNames()
  {
    return $this->metaData->getIdentifierFieldNames();
  }

  /** {@inheritdoc} */
  public function getAssociationNames()
  {
    return $this->metaData->getAssociationNames();
  }

  /** {@inheritdoc} */
  public function getTypeOfField($fieldName)
  {
    return $this->metaData->getTypeOfField($fieldName);
  }

  /** {@inheritdoc} */
  public function getAssociationTargetClass($assocName)
  {
    return $this->metaData->getAssociationTargetClass($assocName);
  }

  /** {@inheritdoc} */
  public function isAssociationInverseSide($assocName)
  {
    return $this->metaData->isAssociationInverseSide($assocName);
  }

  /** {@inheritdoc} */
  public function getAssociationMappedByTargetField($assocName)
  {
    return $this->metaData->getAssociationMappedByTargetField($assocName);
  }

  /** {@inheritdoc} */
  public function getIdentifierValues($object)
  {
    return $this->metaData->getIdentifierValues($object);
  }

  /** {@inheritdoc} */
  public function __call($method, $args)
  {
    if (is_callable([ $this->metaData, $method ])) {
      return call_user_func_array([ $this->metaData, $method ], $args);
    }
    throw new Exception(
      sprintf('Undefined method - %s::%s', get_class($this->metaData), $method)
    );
  }

  /** {@inheritdoc} */
  public function __get($property)
  {
    if (property_exists($this->metaData, $property)) {
      return $this->metaData->$property;
    }
    return null;
  }

  /** {@inheritdoc} */
  public function __set($property, $value)
  {
    $this->metaData->$property = $value;
    return $this;
  }

  //
  //
  ////////////////////////////////////

  /**
   * Update the inverse side of an association after auto-installing
   * $targetEntity into $entity (the owning side). This is only done if it
   * does not produce reloads from the data-base, so only "real" entities,
   * initialized proxies and initialized lazy collections are updated.
   *
   * @param object $entity The current $entity which must be the owning side
   * of the association.
   *
   * @param array $association The association mapping of the current field
   * (the field itself is not needed here).
   *
   * @param object $targetEntity The target-entity which must be the inverse
   * side of the association.
   *
   * @param ClassMetadata|ClassMetadataDecorator $targetMeta The class meta-data of the target-entity.
   *
   * @return void
   */
  private function updateInverseSide(
    object $entity,
    array $association,
    object $targetEntity,
    ClassMetadataInterface $targetMeta,
  ):void {
    if (!empty($association['mappedBy'])) {
      $this->debug('WE ARE THE INVERSE SIDE ' . print_r($association, true));
    }
    // try to maintain connectivity if it does not cause additional direct database access
    if (!empty($association['inversedBy'])
        && (!($targetEntity instanceof Proxy) || $targetEntity->__isInitialized())) {
      $inversedBy = $association['inversedBy'];
      $associationType = $association['type'];
      switch ($associationType) {
        case ClassMetadata::ONE_TO_ONE:
          $targetMeta->setFieldValue($targetEntity, $inversedBy, $entity);
          break;
        case ClassMetadata::MANY_TO_ONE:
          $inversedByValue = $targetMeta->getFieldValue($targetEntity, $inversedBy);
          if (!($inversedByValue instanceof Collection)
              || ($inversedByValue instanceof PersistentCollection) && !$inversedByValue->isInitialized()) {
            // skip if the collection would have to be fetches or if
            // it is not there already (the latter should not happen).
            break;
          }
          $targetAssociation = $targetMeta->associationMappings[$inversedBy];
          $indexBy = $targetAssociation['indexBy'] ?? null;
          // $orderBy = $targetAssociation['orderBy']; complicated
          if (!empty($indexBy)) {
            $indexByValue = (string)$this->metaData->getFieldValue($entity, $indexBy);
            $inversedByValue->set($indexByValue, $entity);
          } elseif (!$inversedByValue->contains($entity)) {
            $inversedByValue->add($entity);
          }
          break;
      }
    }
  }

  /**
   * The related MetaData::getIdentifierValues() function does not
   * handle recursion into associations. Extract the column values of
   * primary foreign keys by recursing into the meta-data.
   *
   * @param mixed $entity The entity to extract the values from.
   *
   * @return array
   * ```
   * [ COLUMN1 => VALUE1, ... ]
   * ```
   * The array is indexed by the database column-names.
   *
   * @note As a side-effect, $entity is modified if a given foreign
   * key is just a simple identifier value (like an int) and not an
   * entity instance or reference.
   */
  public function getIdentifierColumnValues(mixed $entity)
  {
    $columnValues = [];
    foreach ($this->metaData->getIdentifierValues($entity) as $field => $value) {
      if (isset($this->metaData->associationMappings[$field])) {
        $association = $this->metaData->associationMappings[$field];
        $targetEntity = $association['targetEntity'];
        /** @var ClassMetadata $targetMeta */
        $targetMeta = $this->entityManager->getClassMetadata($targetEntity);
        if (count($association['joinColumns']) != 1) {
          throw new Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $joinInfo = $association['joinColumns'][0];
        $columnName = $joinInfo['name'];
        $targetColumn = $joinInfo['referencedColumnName'];
        $targetField = $targetMeta->fieldNames[$targetColumn];
        if ($value instanceof $targetEntity) {
          $columnValues[$columnName] = $targetMeta->getFieldValue($value, $targetField);
        } else {
          // avoid references with empty identifiers
          if (empty($value)) {
            $value = null;
            $reference = null;
          } else {
            // replace the value by a reference
            $reference = $this->entityManager->getReference($targetEntity, [ $targetField => $value ]);

            // try to maintain connectivity if it does not cause additional direct database access
            $this->updateInverseSide($entity, $association, $reference, $targetMeta);
          }
          $this->doSetFieldValue($this->metaData, $entity, $field, $reference);
          // assume this is the column value, not the entity of the foreign key
          $columnValues[$columnName] = $value;
        }
      } else {
        $columnName = $this->metaData->fieldMappings[$field]['columnName'];
        $columnValues[$columnName] = $value;
      }
    }
    return $columnValues;
  }

  /**
   * Generate ids for use with find and self::persist() from database
   * column values. $columnValues is allowed to contain excess data
   * which comes in handy when recursing into associations.
   *
   * @param array $columnValues The actual identifier values indexed by
   * the database column names (read: not the entity-class-names, but
   * the raw column names in the database).
   *
   * @param bool $ignoreMissing Ignore missing key values, just continue and
   * return an incomplete set of keys.
   *
   * @return array
   * ```
   * [
   *   PROPERTY => ELEMENTARY_FIELD_VALUE,
   *   ...
   * ]
   * ```
   *
   * @todo $columnValues sometimes contains raw data-base values as it
   * is passed down here from code using the legacy phpMyEdit
   * stuff. ATM we hack around by converting string values to their
   * proper PHP values, but this is an ugly hack.
   */
  public function extractKeyValues(array $columnValues, bool $ignoreMissing = false):array
  {
    $entityId = [];
    foreach ($this->metaData->identifier as $field) {
      $dbalType = null;
      if (isset($this->metaData->associationMappings[$field])) {
        if (count($this->metaData->associationMappings[$field]['joinColumns']) != 1) {
          throw new Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $columnName = $this->metaData->associationMappings[$field]['joinColumns'][0]['name'];
      } else {
        $columnName = $this->metaData->fieldMappings[$field]['columnName'];
        if (!isset($columnValues[$columnName])) {
          // possibly an attempt to extract from non-existing field.
          if ($ignoreMissing || $this->metaData->usesIdGenerator()) {
            continue;
          }
          throw new Exception(
            $this->l->t('Missing value and no generator for identifier field: %s::%s', [ $this->getName(), $field ]));
        }
        $dbalType = Type::getType($this->metaData->fieldMappings[$field]['type']);
      }
      $value = $columnValues[$columnName];
      if (!empty($dbalType) && is_string($value)) {
        $value = $dbalType->convertToPHPValue($value, $this->entityManager->getPlatform());
      }
      $entityId[$field] = $value;
    }
    return $entityId;
  }

  /**
   * Compute a mapping which includes also hidden columns which are
   * introduced through associations. The mapped field is then the
   * first found association field which references the column.
   *
   * @return array A flat mapping column-name => entity-property
   */
  private function columnAssociations():array
  {
    if (empty($this->columnAssociationsCache)) {
      $meta = $this->metaData;
      $fields = [];
      foreach ($meta->associationMappings as $field => $mapping) {
        foreach (($mapping['joinColumns'] ?? []) as $joinColumn) {
          $fields[$joinColumn['name']][$joinColumn['referencedColumnName']] = $field;
        }
      }
      $this->columnAssociationsCache = $fields;
    }
    return $this->columnAssociationsCache;
  }

  /**
   * @param mixed $meta
   *
   * @param mixed $entity
   *
   * @param string $field
   *
   * @param mixed $value
   *
   * @return void
   */
  private function doSetFieldValue(mixed $meta, mixed $entity, string $field, mixed $value):void
  {
    // try first the setter/getter of the entity
    $method = 'set'.ucfirst($field);

    // try to convert string to correct type if possible
    try {
      $dbalTypeName = $meta->getTypeOfField($field);
      if (!empty($dbalTypeName)) {
        $dbalType = Type::getType($dbalTypeName);
        if (!empty($dbalType) && is_string($value)) {
          $value = $dbalType->convertToPHPValue($value, $this->entityManager->getPlatform());
        }
      }
    } catch (ConversionException $e) {
      if (empty($value) && is_callable([ $entity, $method ])) {
        $this->logException($e, 'Type conversion for field ' . $field . ' failed, continuing anyway.');
      } else {
        throw new DatabaseException($this->l->t('Type conversion for field "%s" failed.', $field), 0, $e);
      }
    }
    if (is_callable([ $entity, $method ])) {
      $entity->$method($value);
    } else {
      $this->logWarn('Probably missing method "'.$method.'" on an entity '.get_class($entity));
      $meta->setFieldValue($entity, $field, $value);
    }
  }

  /**
   * @param mixed $entity
   *
   * @param string $column
   *
   * @param mixed $value
   *
   * @return void
   */
  public function setColumnValue(mixed $entity, string $column, mixed $value):void
  {
    $this->debug('Set column value for ' . $column . ' to ' . $value);

    $meta = $this->metaData;
    $numSetters = 0;
    if (isset($meta->fieldNames[$column])) {
      // simple case, just set the entity property
      $this->doSetFieldValue($meta, $entity, $meta->fieldNames[$column], $value);
      ++$numSetters;
    }
    $columnAssociations = $this->columnAssociations();
    foreach (($columnAssociations[$column]??[]) as $field) {
      // If the column is a join-column then try to create references to the
      // respective entity. Multi-column associations are supported if the
      // column is the only one which is not known.
      if (empty($value)) {
        // an empty join-column value must void the association as the
        // join-columns are the identifier of the referenced entity and hence
        // cannot be null unless the referenced entity is null
        $this->debug('DO CLEAR FIELD VALUE FOR ' . $field);
        $this->doSetFieldValue($meta, $entity, $field, null);
        continue;
      }
      $associationMapping = $meta->associationMappings[$field];
      $targetEntity = $associationMapping['targetEntity'];
      $targetMeta = $this->entityManager->getClassMetadata($targetEntity);
      $referencedColumnId = [];
      foreach ($associationMapping['joinColumns'] as $joinInfo) {
        $joinColumnName = $joinInfo['name'];
        $targetColumn = $joinInfo['referencedColumnName'];
        if ($joinColumnName == $column) {
          $fieldValue = $meta->getFieldValue($entity, $field);
          if (!empty($fieldValue) &&
              $value == $targetMeta->getColumnValue($fieldValue, $targetColumn)) {
            // do not destroy already installed associated entities
            continue 2;
          }
          $targetValue = $value;
        } else {
          $targetValue = $this->getColumnValue($entity, $joinColumnName);
        }
        if (empty($targetValue)) {
          continue 2;
        }
        $referencedColumnId[$targetColumn] = $targetValue;
      }
      $referencedId = $targetMeta->extractKeyValues($referencedColumnId);
      $fieldValue = $this->entityManager->getReference($targetEntity, $referencedId);
      $this->doSetFieldValue($meta, $entity, $field, $fieldValue);
      $this->updateInverseSide($entity, $associationMapping, $fieldValue, $targetMeta);
      ++$numSetters;
    }
    if ($numSetters == 0) {
      // throw new \RuntimeException($this->l->t('Unable to feed a single field with the value of the column "' . $column . '".'));
      $this->debug('Remember value for column ' . $column . ': ' . $value);
      $this->temporaryColumnStorage[$column] = $value;
    } else {
      $this->debug('Clear value for column ' . $column);
      unset($this->temporaryColumnStorage[$column]);
    }
  }

  /**
   * Fetch the value of a column, recursing into associations.
   *
   * @param mixed $entity
   *
   * @param string $column
   *
   * @return mixed
   */
  public function getColumnValue(mixed $entity, string $column)
  {
    $meta = $this->metaData;
    // simple case ...
    if (isset($meta->fieldNames[$column])) {
      return $meta->getFieldValue($entity, $meta->fieldNames[$column]);
    }
    // look into the association mappings
    $columnAssociations = $this->columnAssociations();
    foreach (($columnAssociations[$column]??[]) as $targetColumn => $field) {
      $fieldValue = $meta->getFieldValue($entity, $field);
      if (empty($fieldValue)) {
        continue;
      }
      $associationMapping = $meta->associationMappings[$field];
      $targetEntity = $associationMapping['targetEntity'];
      if ($fieldValue instanceof $targetEntity) {
        $targetMeta = $this->entityManager->getClassMetadata($targetEntity);
        return $targetMeta->getColumnValue($fieldValue, $targetColumn);
      } else {
        // assume the field-value is the just the column value of the single join-column
        if (count($associationMapping['joinColumns']) != 1) {
          throw new RuntimeException($this->l->t('Association field must eiter be an entity or the value of the single join column.') . print_r($associationMapping['joinColumns'], true));
        }
        return $fieldValue;
      }
    }
    if (!empty($this->temporaryColumnStorage[$column])) {
      $this->debug('USE TEMPORARY COLUMN STORAGE ' . $column . ' => ' . $this->temporaryColumnStorage[$column]);
      return $this->temporaryColumnStorage[$column];
    }
    return null;
  }

  /**
   * Compute the mapping between entity-properties ("field-name") and
   * plain SQL column-names. This is somewhat complicated when foreign
   * keys are used.
   *
   * @return array
   * ```
   * [
   *   PROPERTY => SQL_COLUMN_NAME
   *   ...
   * ]
   * ```
   */
  public function identifierColumns():array
  {
    $meta = $this->metaData;
    $entityId = [];
    foreach ($meta->identifier as $field) {
      if (isset($meta->associationMappings[$field])) {
        if (count($meta->associationMappings[$field]['joinColumns']) != 1) {
          throw new Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $columnName = $meta->associationMappings[$field]['joinColumns'][0]['name'];
      } else {
        $columnName = $meta->fieldMappings[$field]['columnName'];
      }
      $entityId[$field] = $columnName;
    }
    return $entityId;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
