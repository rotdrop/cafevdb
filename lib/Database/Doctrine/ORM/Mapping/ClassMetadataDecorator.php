<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Mapping;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;

use OCA\CAFEVDB\Database\EntityManager;

class ClassMetadataDecorator implements \OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var array */
  private $simpleColumnMappings_ = null;

  /** @var EntityManager */
  private $entityManager;

  /** @var ClassMetadata */
  private $metaData;

  /** @var IL10N */
  private $l;

  public function __construct(
    ClassMetadata $metaData
    , EntityManager $entityManager
    , ILogger $logger
    , IL10N $l10n
  )
  {
    $this->metaData = $metaData;
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  ////////////////////////////////////
  //
  // Decorator stuff
  //
  // Things ain't that simple, we must implement all methods.

  /**
   * Gets the fully-qualified class name of this persistent class.
   *
   * @return string
   */
  public function getName()
  {
    return $this->metaData->getName();
  }

  /**
   * Gets the mapped identifier field name.
   *
   * The returned structure is an array of the identifier field names.
   *
   * @return mixed[]
   */
  public function getIdentifier()
  {
    return $this->metaData->getIdentifier();
  }

  /**
   * Gets the ReflectionClass instance for this mapped class.
   *
   * @return ReflectionClass
   */
  public function getReflectionClass()
  {
    return $this->metaData->getReflectionClass();
  }

  /**
   * Checks if the given field name is a mapped identifier for this class.
   *
   * @param string $fieldName
   *
   * @return bool
     */
  public function isIdentifier($fieldName)
  {
    return $this->metaData->isIdentifier($fieldName);
  }

  /**
   * Checks if the given field is a mapped property for this class.
   *
   * @param string $fieldName
   *
   * @return bool
   */
  public function hasField($fieldName)
  {
    return $this->metaData->hasField($fieldName);
  }

  /**
   * Checks if the given field is a mapped association for this class.
   *
   * @param string $fieldName
   *
   * @return bool
   */
  public function hasAssociation($fieldName)
  {
    return $this->metaData->hasAssociation($fieldName);
  }

  /**
   * Checks if the given field is a mapped single valued association for this class.
   *
   * @param string $fieldName
   *
   * @return bool
     */
  public function isSingleValuedAssociation($fieldName)
  {
    return $this->metaData->isSingleValuedAssociation($fieldName);
  }

  /**
   * Checks if the given field is a mapped collection valued association for this class.
   *
   * @param string $fieldName
   *
   * @return bool
   */
  public function isCollectionValuedAssociation($fieldName)
  {
    return $this->metaData->isCollectionValuedAssociation($fieldName);
  }

  /**
   * A numerically indexed list of field names of this persistent class.
   *
   * This array includes identifier fields if present on this class.
   *
   * @return string[]
   */
  public function getFieldNames()
  {
    return $this->metaData->getFieldNames();
  }

  /**
   * Returns an array of identifier field names numerically indexed.
   *
   * @return string[]
   */
  public function getIdentifierFieldNames()
  {
    return $this->metaData->getIdentifierFieldNames();
  }

  /**
   * Returns a numerically indexed list of association names of this persistent class.
   *
   * This array includes identifier associations if present on this class.
   *
   * @return string[]
   */
  public function getAssociationNames()
  {
    return $this->metaData->getAssociationNames();
  }

  /**
   * Returns a type name of this field.
   *
   * This type names can be implementation specific but should at least include the php types:
   * integer, string, boolean, float/double, datetime.
   *
   * @param string $fieldName
   *
   * @return string
   */
  public function getTypeOfField($fieldName)
  {
    return $this->metaData->getTypeOfField($fieldName);
  }


  /**
   * Returns the target class name of the given association.
   *
   * @param string $assocName
   *
   * @return string
   */
  public function getAssociationTargetClass($assocName)
  {
    return $this->metaData->getAssociationTargetClass($assocName);
  }

  /**
   * Checks if the association is the inverse side of a bidirectional association.
   *
   * @param string $assocName
   *
   * @return bool
   */
  public function isAssociationInverseSide($assocName)
  {
    return $this->metaData->isAssociationInverseSide($assocName);
  }

  /**
   * Returns the target field of the owning side of the association.
   *
   * @param string $assocName
   *
   * @return string
   */
  public function getAssociationMappedByTargetField($assocName)
  {
    return $this->metaData->getAssociationMappedByTargetField($assocName);
  }

  /**
   * Returns the identifier of this object as an array with field name as key.
   *
   * Has to return an empty array if no identifier isset.
   *
   * @param object $object
   *
   * @return mixed[]
   */
  public function getIdentifierValues($object)
  {
    return $this->metaData->getIdentifierValues($object);
  }

  public function __call($method, $args)
  {
    if (is_callable([ $this->metaData, $method ])) {
      return call_user_func_array([ $this->metaData, $method ], $args);
    }
    throw new \Exception(
      sprintf('Undefined method - %s::%s', get_class($this->metaData), $method)
    );
  }

  public function __get($property)
  {
    if (property_exists($this->metaData, $property)) {
      return $this->metaData->$property;
    }
    return null;
  }

  public function __set($property, $value)
  {
    $this->metaData->$property = $value;
    return $this;
  }

  //
  //
  ////////////////////////////////////

  /**
   * The related MetaData::getIdentifierValues() function does not
   * handle recursion into associations. Extract the column values of
   * primary foreign keys by recursing into the meta-data.
   *
   * @param mixed $entity The entity to extract the values from.
   *
   * @param ClassMetadata $meta The meta-data
   * for the given $entity.
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
  public function getIdentifierColumnValues($entity)
  {
    $columnValues = [];
    foreach ($this->metaData->getIdentifierValues($entity) as $field => $value) {
      if (isset($this->metaData->associationMappings[$field])) {
        $association = $this->metaData->associationMappings[$field];
        $targetEntity = $association['targetEntity'];
        $targetMeta = $this->entityManager->getClassMetadata($targetEntity);
        if (count($association['joinColumns']) != 1) {
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
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
          }
          // try the setter of the entity first
          $method = 'set'.ucfirst($field);
          if (is_callable([ $entity, $method ])) {
            $entity->$method($reference);
          } else {
            $this->metaData->setFieldValue($entity, $field, $reference);
          }
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
   * @return array
   * ```
   * [
   *   PROPERTY => ELEMENTARY_FIELD_VALUE,
   *   ...
   * ]
   * ```
   * @todo $columnValues sometimes contains raw data-base values as it
   * is passed down here from code using the legacyx phpMyEdit
   * stuff. ATM we hack around by converting string values to their
   * proper PHP values, but this is an ugly hack.
   */
  public function extractKeyValues(array $columnValues):array
  {
    $entityId = [];
    foreach ($this->metaData->identifier as $field) {
      $dbalType = null;
      if (isset($this->metaData->associationMappings[$field])) {
        if (count($this->metaData->associationMappings[$field]['joinColumns']) != 1) {
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $columnName = $this->metaData->associationMappings[$field]['joinColumns'][0]['name'];
      } else {
        $columnName = $this->metaData->fieldMappings[$field]['columnName'];
        if (!isset($columnValues[$columnName])) {
          // possibly an attempt to extract from non-existing field.
          if ($this->metaData->usesIdGenerator()) {
            continue;
          }
          throw new \Exception(
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
   * first found association field which refereces the column.
   *
   * @return array A flat mapping column-name => entity-property
   */
  public function simpleColumnMappings():array
  {
    if (empty($this->simpleColumnMappings_)) {
      $meta = $this->metaData;
      $fields = [];
      foreach ($meta->fieldMappings as $field => $info) {
        $column = $info['columnName'];
        $fields[$column] = $field;
      }
      foreach ($meta->associationMappings as $field => $mapping) {
        foreach ($mapping['joinColumns'] as $joinColumn) {
          $column = $joinColumn['name'];
          if (empty($fields[$column])) {
            // this should catch the additional join-columns
            // introduced by associations.
            $fields[$column] = $field;
          }
        }
      }
      $this->simpleColumnMappings_ = $fields;
    }
    return $this->simpleColumnMappings_;
  }

  /**
   * Convert the given value to a reference if $field is a "simple"
   * association field and set it in the entity.
   *
   * If $field has no association just set $field to $value
   *
   * If $value is an instance of the target entity, just set $field to $value
   *
   * If there is a single join-column, use $value as its value and
   * generate a rereference to the target entity.
   *
   * If there are multiple join-columns, then exactly one must have no
   * associated field in the given $entity, all otheres must have a
   * field in the given $entity. The "hidden column" is set to $value,
   * all other join-column values are taken from the entity.
   *
   * More complicated cases cannot easily be handled, as the function
   * only accepts a single value to set.
   */
  public function setSimpleFieldValue($entity, string $field, $value)
  {
    $meta = $this->metaData;
    if (isset($meta->associationMappings[$field])) {
      $association = $meta->associationMappings[$field];
      $targetEntity = $association['targetEntity'];

      if (!($value instanceof $targetEntity) && empty($value)) {
        // avoid generating references with empty identifiers
        $value = null;
      } else if (!($value instanceof $targetEntity)) {

        $targetMeta = $this->entityManager->getClassMetadata($targetEntity);

        $referencedId = [];
        if (count($association['joinColumns']) == 1) {
          $joinInfo = $association['joinColumns'][0];
          $columnName = $joinInfo['name'];
          $targetColumn = $joinInfo['referencedColumnName'];
          $targetField = $targetMeta->fieldNames[$targetColumn];
          $referencedId[$targetField] = $value;
        } else {
          // Fuzzy case. We allow one hidden column where the other
          // values are fed from "simple" columns in the entity.
          foreach ($association['joinColumns'] as $joinInfo) {
            $columnName = $joinInfo['name'];
            $targetColumn = $joinInfo['referencedColumnName'];
            $targetField = $targetMeta->simpleColumnMappings()[$targetColumn];
            if (empty($targetField)) {
              throw new \RuntimeException($this->l->t('Empty target field for column "%s".', $targetColumn));
            }

            if (isset($meta->fieldNames[$columnName])) {
              $joinField = $meta->fieldNames[$columnName];
              $joinValue = $meta->getFieldValue($entity, $joinField);
              if (empty($joinValue)) {
                throw new \RuntimeException($this->l->t('Join-column has empty value.'));
              }
              $referencedId[$targetField] = $joinValue;
            } else {
              if (empty($value)) {
                throw new \RuntimeException($this->l->t('More than one hidden join-column.'));
              }
              $referencedId[$targetField] = $value;
              $value = null;
            }
          }
          if ($value !== null) {
            throw new \RuntimeException($this->l->t('No hidden join column.'));
          }
        }
        // replace the value by a reference
        $value = $this->entityManager->getReference($targetEntity, $referencedId);
      }
    }
    // try first the setter/getter of the entity
    $method = 'set'.ucfirst($field);
    if (is_callable([ $entity, $method ])) {
      $entity->$method($value);
    } else {
      $this->logWarn('Probably missing method "'.$method.'" on an entity '.get_class($entity));
      $meta->setFieldValue($entity, $field, $value);
    }
  }

  /**
   * Convert the given value to a reference if $field is a "simple"
   * association field and set it in the entity.
   */
  public function setSimpleColumnValue($entity, string $column, $value)
  {
    $field = $this->simpleColumnMappings()[$column];
    if (empty($field)) {
      throw new \RuntimeException('Unable to find field for column "'.$column.'"');
    }
    $this->setSimpleFieldValue($entity, $field, $value);
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
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
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
