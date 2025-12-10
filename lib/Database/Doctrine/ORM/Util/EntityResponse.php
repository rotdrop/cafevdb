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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * A collection of entities without duplicates.
 */
class EntityResponse extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    /** @var array<string, array<string> > */
    #[TSAttributes\LiteralTypeScriptType('{ [e in keyof Database.Doctrine.ORM.EntityMetadata.EntityMap]: string[] }')]
    public readonly array $entities,
    /** @var array<string, array<string, object> > */
    #[TSAttributes\LiteralTypeScriptType('{ [e in keyof Database.Doctrine.ORM.EntityMetadata.EntityMap]: { [id: string]: Database.Doctrine.ORM.EntityMetadata.EntityDto<e> } }')]
    public readonly array $repositories,
  ) {
  }
}
