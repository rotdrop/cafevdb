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
  EntityFieldMapping,
  EntityFieldMappingType,
  EntityFieldMetadata,
  EntityFieldNames,
  EntityFieldType,
  EntityMap,
  EntityNames,
} from '../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import type {
  EntityReference,
  EntityReferenceCollection,
} from '../../build/ts-types/php-modules/Database/Doctrine/ORM/Util.ts';
import * as EntityRepository from './entity-repository.ts';

export type FrontEndEntity<N extends EntityNames> = {
  [K in EntityFieldNames<N>]: EntityFieldMapping<N, K> extends 'owned'
    ? K extends keyof EntityMap[N]
      ? EntityMap[N][K]
      : never
    : EntityFieldMapping<N, K> extends 'to-one'
      ? Promise<FrontEndEntity<EntityFieldType<N, K> > >
      : Record<string|number, Promise<FrontEndEntity<EntityFieldType<N, K> > > >;
};

const entityFactory = async <E extends keyof EntityMap>(entityName: E, entityDto: EntityDto<E>): Promise<FrontEndEntity<E> > => {
  const metadata: { [K in keyof EntityMap[E]]: EntityFieldMetadata<E> } =
    (await import(`../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata/${entityName}Metadata.ts`)).default;

  const entity: FrontEndEntity<E> = <FrontEndEntity<E> >{};
  for (const fieldName of Object.keys(metadata)) {
    const fieldInfo: EntityFieldMetadata<E> = metadata[fieldName];
    switch (fieldInfo.mapping as EntityFieldMappingType) {
      case 'to-one': {
        const reference: null|EntityReference<E> = entityDto[fieldName];
        if (reference) {
          const targetEntity = reference.entityClassName as keyof EntityMap;
          const identifier = reference.flatIdentifier;
          Object.defineProperty(
            entity,
            fieldName, {
              get: async () => {
                let result = EntityRepository.find(targetEntity, identifier);
                if (result === undefined) {
                  // @todo: this will not work for composite keys and complicated foreign keys
                  await EntityRepository.fetch({
                    entityName: targetEntity,
                    identifier,
                  });
                  result = EntityRepository.find(targetEntity, identifier);
                }
                return result;
              },
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
          collection.entities, {
            get: async (
              entities: EntityReferenceCollection<E>['entities'],
              field: string,
              _receiver: unknown,
            ) => {
              if (entities[field] === undefined) {
                return undefined;
              }
              const entityReference = entities[field];
              const className = entityReference.entityClassName ?? collection.entityClassName;
              let result = EntityRepository.find(className, entityReference.flatIdentifier);
              if (result === undefined) {
                await EntityRepository.fetch({
                  entityName: className,
                  // @todo: this will not work for composite keys and complicated foreign keys
                  identifier: entityReference.flatIdentifier,
                });
                result = EntityRepository.find(className, entityReference.flatIdentifier);
              }
              return result;
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
