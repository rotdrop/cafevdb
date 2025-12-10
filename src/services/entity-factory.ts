/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type {
  EntityDto,
  EntityFieldMetadata,
  EntityMap,
  EntityFieldMappingType,
  // EntityMetadataMap,
} from '../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import type {
  EntityReference,
  EntityReferenceCollection,
} from '../../build/ts-types/php-modules/Database/Doctrine/ORM/Util.ts';
import * as EntityRepository from './entity-repository.ts';

const entityFactory = async <E extends keyof EntityMap>(entityName: E, entityDto: EntityDto<E>): Promise<EntityMap[E]> => {
  const metadata: { [K in keyof EntityMap[E]]: EntityFieldMetadata<E> } =
    (await import(`../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata/${entityName}Metadata.ts`)).default;

  const entity: EntityMap[E] = <EntityMap[E]>{};
  for (const fieldName of Object.keys(metadata)) {
    const fieldInfo: EntityFieldMetadata<E> = metadata[fieldName];
    switch (fieldInfo.mapping as EntityFieldMappingType) {
      case 'to-one': {
        const reference: null|EntityReference<E> = entityDto[fieldName];
        if (reference) {
          const targetEntity = reference.entityClassName as keyof EntityMap;
          const identifier = entityDto[fieldName].flatIdentifier;
          Object.defineProperty(
            entity,
            fieldName, {
              get: () => EntityRepository.find(targetEntity, identifier),
            },
          );
        } else {
          entity[fieldName] = null;
        }
        break;
      }
      case 'to-many': {
        const collection: EntityReferenceCollection<E> = entityDto[fieldName];
        const proxy = new Proxy(
          collection, {
            get(
              collection: EntityReferenceCollection<E>,
              field: string,
              _receiver: unknown,
            ) {
              const entityReference = collection.entities[field];
              return EntityRepository.find(entityReference.entityClassName ?? collection.entityClassName, entityReference.flatIdentifier);
            },
          },
        );
        Object.defineProperty(
          entity,
          fieldName, {
            get: () => proxy,
          },
        );
        break;
      }
      case 'owned':
        entity[fieldName] = entityDto[fieldName];
        break;
    }
  }
  return entity;
};

export default entityFactory;
