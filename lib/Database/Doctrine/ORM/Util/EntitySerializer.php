<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Util;

use Psr\Log\LoggerInterface;

use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Utility\IdentifierFlattener;

/**
 * The goal is to serialize entities (... to JSON ...)  such that the JS
 * frontend can reconstruct the association structure without duplicated
 * data. For now this is meant for read-only access. "serialize" in this
 * context means to compute a flat loop-free array representation which can
 * then be safely fed into json_serialize.
 */
class EntitySerializer
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private IdentifierFlattener $identifierFlattener;

  private array $entites = [];

  private array $repositories = [];

  /** {@inheritdoc} */
  public function __construct(
    protected EntityManager $entityManager,
    protected IL10n $l,
    protected LoggerInterface $logger,
  ) {
    $this->identifierFlattener = new IdentifierFlattener(
      $this->entityManager->getUnitOfWork(),
      $this->entityManager->getMetadataFactory(),
    );
  }

  /**
   * Add one Doctrine ORM entity to the serialization structure.
   *
   * @param mixed $entity An entity instance known to the entity manager.
   *
   * @return void
   *
   * @throws EntitySerializationException
   */
  public function addEntity(mixed $entity): void
  {
    try {
      $metaData = $this->entityManager->getClassMetadata($entity);
      $id = $metaData->getIdentifierValues($entity);
      if (!$id) {
        throw Exceptions\DatabaseMissingIdentifierException(
          $this->l->t('Unable to determine the identifier values for an instance of "%s".', get_class($entity)),
          get_class($entity),
        );
      }
      if ($class->containsForeignIdentifier || $class->containsEnumIdentifier) {
        $id = $this->identifierFlattener->flattenIdentifier($class, $id);
      }
      $flatIdentity = $this->identifierFlattener->flattenIdentifier($metaData, $id);

      $flatEntitiy = [];

      // ordinary non-associative fields
      /** @var Mapping\FieldMapping $mapping */
      foreach (array_keys($metaData->fieldMappings) as $field) {
        $flatEntitiy[$field] = $metaData->getFieldValue($field);
      }
      /** @var Mapping\AssociationMapping $associationMapping */
      foreach ($metaData->associationMappings as $associationMapping) {
      }
      // go on ...

    } catch (Throwable $t) {
      throw new Exceptions\EntitySerializationException(
        $this->l->t('Unable to compute a serialization for an instance of "%s".', get_class($entity)),
        $entity,
        previous: $t,
      );
    }
  }
}
